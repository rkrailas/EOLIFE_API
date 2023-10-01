<?php
// Version 1.3 (23 Sep 23)
// Fixed ReceiveItems ตรวจสอบว่ามีการรับ Serial No นี้แล้วหรือยัง ตัด posted=true ออกไป
// Fixed TranferItems การแยก Case S/N และ No S/N

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");

require_once 'response.php';
require_once 'connect.php';
require_once 'myFunction.php';

date_default_timezone_set("Asia/Bangkok");

$received_data = json_decode(file_get_contents("php://input"));

if ($received_data->action == "AddSales") {
    $have_error = 0;
    $errormsg = "";
    $successmsg = "";
    $salesAction = ""; // Add, Update

    $conn->beginTransaction();

    try {
        // ตรวจสอบว่ามี Sales Order นี้หรือยัง (ถ้ามีจะเป็น Update ถ้าไม่มีจะเป็น Add)
        $strsql = "SELECT snumber, posted FROM sales WHERE snumber=:snumber";
        $stmt = $conn->prepare($strsql);
        $stmt->execute([
            'snumber' => $received_data->data->snumber
        ]);
        $result = $stmt->fetchAll((PDO::FETCH_ASSOC));

        if (count($result)) {
            if ($result[0]['posted']) {
                $errormsg .= "Sales: This SO (" . $received_data->data->snumber . ") was posted";
                Response::error($errormsg, 428);
                die();
            } else {
                $salesAction = "Update"; // ถ้ามี SO นี้ แต่ posted=false ให้ Update
                $successmsg = "Update Success";
            }
        } else {
            $salesAction = "Add"; // ถ้าไม่มี SO นี้ ให้ Add
            $successmsg = "Insert Success";
        }

        #region ===Customer===
        //customerid ซ้ำหรือไม่
        $strsql = "SELECT customerid FROM customer WHERE customerid= :customerid";
        $stmt = $conn->prepare($strsql);
        $stmt->execute([
            'customerid' => $received_data->data->customer->customerid
        ]);
        $result = $stmt->fetchAll((PDO::FETCH_ASSOC));

        if (count($result)) {
            // ถ้ามี Customer อยู่แล้วไม่ต้องทำอะไร

        } else {
            //Check BOOLEAN
            if (!($received_data->data->customer->corporate == "1" or $received_data->data->customer->corporate == "0")) {
                if ($errormsg) {
                    $errormsg .= ", ";
                }
                $errormsg .= "Customer: Invalid Boolean";
                $have_error = 1;
            }

            //Check NUMERIC
            if (!(is_numeric($received_data->data->customer->creditlimit))) {
                if ($errormsg) {
                    $errormsg .= ", ";
                }
                $errormsg .= "Customer: Invalid Numeric";
                $have_error = 1;
            }

            if ($have_error) {
                Response::error($errormsg, 428);
                die();
            } else {
                //Insert Customer
                $data = [
                    'customerid' => $received_data->data->customer->customerid,
                    'name' => $received_data->data->customer->name,
                    'name1' => $received_data->data->customer->name,
                    'names' => $received_data->data->customer->name,
                    'address11' => $received_data->data->customer->address1,
                    'address12' => $received_data->data->customer->address2,
                    'city1' => $received_data->data->customer->city,
                    'state1' => $received_data->data->customer->state,
                    'zipcode1' => $received_data->data->customer->zipcode,
                    'email1' => $received_data->data->customer->email,
                    'fax1' => $received_data->data->customer->fax,
                    'phone1' => $received_data->data->customer->phone,
                    'debtor' => '1',
                    'taxid' => $received_data->data->customer->taxid,
                    'corporate' => $received_data->data->customer->corporate,
                    'branchno' => $received_data->data->customer->branchno,
                    'employee_id' => "Front-end",
                    'transactiondate' => date("Y-m-d H:i:s"),
                ];

                $strsql = "INSERT INTO customer(customerid,name,name1,names,address11,address12,city1,state1,zipcode1,email1,fax1,phone1
            ,debtor,taxid,corporate,branchno,employee_id,transactiondate
            ) 
            VALUES(:customerid,:name,:name1,:names,:address11,:address12,:city1,:state1,:zipcode1,:email1,:fax1,:phone1
            ,:debtor,:taxid,:corporate,:branchno,:employee_id,:transactiondate
            )";

                $stmt = $conn->prepare($strsql);
                $stmt->execute($data);

                //Insert Buyer
                $data = [
                    'customerid' => $received_data->data->customer->customerid,
                    'creditlimit' => $received_data->data->customer->creditlimit,
                    'tax' => $received_data->data->customer->taxtype,
                    'employee_id' => "Front-end",
                    'transactiondate' => date("Y-m-d H:i:s"),
                ];

                $strsql = "INSERT INTO buyer(customerid,creditlimit,tax,employee_id,transactiondate)
            VALUES(:customerid,:creditlimit,:tax,:employee_id,:transactiondate)";

                $stmt = $conn->prepare($strsql);
                $stmt->execute($data);
            }
        }
        #endregion ===Customer===

        #region ===Sales===
        //Check Numeric
        if (!(is_numeric($received_data->data->sototal)) or !(is_numeric($received_data->data->salestax)) or !(is_numeric($received_data->data->taxratestd))) {
            if ($errormsg) {
                $errormsg .= ", ";
            }
            $errormsg .= "Sales : Invalid Numeric";
            $have_error = 1;
        }

        //Check Date
        if (!validateDate($received_data->data->sodate) or !validateDate($received_data->data->expirydate) or !validateDate($received_data->data->duedate)) {
            if ($errormsg) {
                $errormsg .= ", ";
            }
            $errormsg .= "Sales : Invalid Date";
            $have_error = 1;
        }

        if ($have_error) {
            Response::error($errormsg, 428);
            die();
        } else {
            //Account Code บัญชีลูกหนี้ จาก 1.buyer 2.controldef(AR)
            $salesaccount = "";
            $strsql = "SELECT account FROM buyer a WHERE customerid=:customerid";
            $stmt = $conn->prepare($strsql);
            $stmt->execute([
                'customerid' => $received_data->data->customer->customerid
            ]);
            $result = $stmt->fetch((PDO::FETCH_ASSOC));

            if ($result and $result['account'] != "") {
                $salesaccount = $result['account'];
            } else {
                $strsql = "SELECT account FROM controldef WHERE id='AR'";
                $stmt = $conn->prepare($strsql);
                $stmt->execute();
                $result = $stmt->fetch((PDO::FETCH_ASSOC));

                if (count($result)) {
                    $salesaccount = $result['account'];
                }
            }
            //.Account Code บัญชีลูกหนี้

            $data = [
                'customerid' => $received_data->data->customer->customerid,
                'snumber' => $received_data->data->snumber,
                'sonumber' => $received_data->data->snumber,
                'sodate' => $received_data->data->sodate,
                'journaldate' => $received_data->data->sodate,
                'deliverydate' => $received_data->data->sodate,
                'expirydate' => $received_data->data->expirydate,
                'duedate' => $received_data->data->duedate,
                'sodiscount' => $received_data->data->sodiscount,
                'sototal' => $received_data->data->sototal,
                'salestax' => $received_data->data->salestax,
                'exclusivetax' => '1',
                'closed' => '1',
                'taxratestd' => $received_data->data->taxratestd,
                'salesaccount' => $salesaccount,
                'employee_id' => "Front-end",
                'transactiondate' => date("Y-m-d H:i:s"),
            ];

            // ถ้า salesAction เป็น Update ต้องลบ Sales กับ SalesDetail ก่อน
            if ($salesAction == "Update") {
                // Delete Sales
                $strsql = "DELETE FROM sales WHERE snumber=:snumber";
                $stmt = $conn->prepare($strsql);
                $stmt->execute([
                    'snumber' => $received_data->data->snumber
                ]);

                // Delete Salesdetail
                $strsql = "DELETE FROM salesdetail WHERE snumber=:snumber";
                $stmt = $conn->prepare($strsql);
                $stmt->execute([
                    'snumber' => $received_data->data->snumber
                ]);

                // Update InventorySerial
                $strsql = "UPDATE inventoryserial SET snumber='', solddate=null, sold=FALSE, employee_id=:employee_id, transactiondate=:transactiondate 
                        WHERE snumber=:snumber";
                $stmt = $conn->prepare($strsql);
                $stmt->execute([
                    'employee_id' => "Front-end",
                    'transactiondate' => date("Y-m-d H:i:s"),
                    'snumber' => $received_data->data->snumber
                ]);
            }

            $strsql = "INSERT INTO sales(customerid,snumber,sonumber,sodate,journaldate,deliverydate,expirydate,duedate,sodiscount,sototal,salestax
                        ,exclusivetax,closed,taxratestd,salesaccount,employee_id,transactiondate)
                        VALUES(:customerid,:snumber,:sonumber,:sodate,:journaldate,:deliverydate,:expirydate,:duedate,:sodiscount,:sototal,:salestax
                        ,:exclusivetax,:closed,:taxratestd,:salesaccount,:employee_id,:transactiondate)";
            $stmt = $conn->prepare($strsql);
            $stmt->execute($data);

            #region ===Salesdetail===
            foreach ($received_data->data->salesdetail as $row) {
                //Check Numeric
                if (!(is_numeric($row->quantity)) or !(is_numeric($row->unitprice)) or !(is_numeric($row->amount)) or !(is_numeric($row->taxamount)) or !(is_numeric($row->taxrate))) {
                    if ($errormsg) {
                        $errormsg .= ", ";
                    }
                    $errormsg .= "Sales Detail: Invalid Numeric";
                    $have_error = 1;
                }

                //Check Date
                if (!validateDate($row->sdate)) {
                    if ($errormsg) {
                        $errormsg .= ", ";
                    }
                    $errormsg .= "Sales Detail: Invalid Date";
                    $have_error = 1;
                }

                if ($have_error) {
                    Response::error($errormsg, 428);
                    die();
                } else {
                    //Insert SalesDetail

                    //Account Code บัญชีขาย จาก 1.inventory 2.controldef(SA)
                    $salesac = "";
                    $stocktype = "";
                    $strsql = "SELECT salesac, stocktype FROM inventory a WHERE itemid=:itemid";
                    $stmt = $conn->prepare($strsql);
                    $stmt->execute([
                        'itemid' => $row->itemid
                    ]);
                    $result = $stmt->fetch((PDO::FETCH_ASSOC));

                    if ($result) {
                        $stocktype = $result['stocktype'];

                        if ($result['salesac'] != "") {
                            $inventoryac = $result['salesac'];
                        }
                    } else {
                        $strsql = "SELECT account FROM controldef WHERE id='SA'";
                        $stmt = $conn->prepare($strsql);
                        $stmt->execute();
                        $result = $stmt->fetch((PDO::FETCH_ASSOC));

                        if ($result) {
                            $salesac = $result['account'];
                        }
                    }
                    //.Account Code บัญชีขาย

                    $data = [
                        'snumber' => $row->snumber,
                        'sdate' => $row->sdate,
                        'itemid' => $row->itemid,
                        'description' => $row->description,
                        'quantity' => $row->quantity,
                        'quantityord' => $row->quantity,
                        'quantitybac' => $row->quantity,
                        'unitprice' => $row->unitprice,
                        'discountamount' => $row->discountamount,
                        'amount' => $row->amount,
                        'taxamount' => $row->taxamount,
                        'taxrate' => $row->taxrate,
                        'salesac' => $salesac,
                        'stocktype' => $stocktype,
                        'employee_id' => "Front-end",
                        'transactiondate' => date("Y-m-d H:i:s"),
                    ];

                    $strsql = "INSERT INTO salesdetail(snumber,sdate,itemid,description,quantity,quantityord,quantitybac,unitprice,discountamount,amount,taxamount,taxrate,salesac,stocktype,employee_id,transactiondate)
                                VALUES(:snumber,:sdate,:itemid,:description,:quantity,:quantityord,:quantitybac,:unitprice,:discountamount,:amount,:taxamount,:taxrate,:salesac,:stocktype,:employee_id,:transactiondate)";
                    $stmt = $conn->prepare($strsql);
                    $stmt->execute($data);

                    //===Inventoryserial Start===
                    foreach ($row->serialno as $row2) {

                        // Check stocktype = 4 (มี Serial No หรือไม่)
                        $strsql = "SELECT stocktype FROM inventory WHERE itemid=:itemid";
                        $stmt = $conn->prepare($strsql);
                        $stmt->execute([
                            "itemid" => $row->itemid
                        ]);
                        $result = $stmt->fetch((PDO::FETCH_ASSOC));

                        if ($result['stocktype'] == 4) {
                            // Check SN Available
                            $strsql = "SELECT id FROM inventoryserial WHERE itemid=:itemid and serialno=:serialno and sold=false";
                            $stmt = $conn->prepare($strsql);
                            $stmt->execute([
                                "itemid" => $row->itemid,
                                "serialno" => $row2,
                            ]);
                            $result = $stmt->fetch((PDO::FETCH_ASSOC));

                            if ($result == false) { //ถ้าใช้ fetch แล้ว Query แล้วไม่พบข้อมูลมันจะเป็น False จะใช้ Count ไม่ได้ แต่ถ้าใช้ fetchAll จะสามารถใช้ count ได้ แต่การอ้างถึงค่าจะต้องระบุตำแหน่งเสมอ เช่น $result[0][account]
                                $errormsg .= "Item ID: " . $row->itemid . " SN: " . $row2 . " Not Available";
                                $have_error = 1;
                            }

                            if ($have_error) {
                                $conn->rollBack();
                                Response::error($errormsg, 428);
                                die();
                            } else {
                                //Update InventorySerial
                                $data = [
                                    "itemid" => $row->itemid,
                                    "serialno" => $row2,
                                    "snumber" => $row->snumber,
                                    "solddate" => $row->sdate,
                                    'employee_id' => "Front-end",
                                    'transactiondate' => date("Y-m-d H:i:s"),
                                ];

                                $strsql = "UPDATE inventoryserial SET snumber=:snumber, solddate=:solddate, employee_id=:employee_id, transactiondate=:transactiondate WHERE itemid=:itemid and serialno=:serialno";
                                $stmt = $conn->prepare($strsql);
                                $stmt->execute($data);
                            }
                        }
                    }
                    //===Inventoryserial End===
                }
            }
            #endregion ===Salesdetail===
            #endregion ===Sales===

            $conn->commit();
            Response::success($successmsg, 200);
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $errormsg = $e->getmessage();
        Response::error($errormsg, 404);
        die();
    }
}

if ($received_data->action == "Clear_AddSales") {
    //Delete Customer
    $strsql = "DELETE FROM customer WHERE customerid = 'CUS-002'";
    $stmt = $conn->prepare($strsql);
    $stmt->execute();

    //Delete Buyer
    $strsql = "DELETE FROM buyer WHERE customerid = 'CUS-002'";
    $stmt = $conn->prepare($strsql);
    $stmt->execute();

    //Delete Sales
    $strsql = "DELETE FROM sales WHERE snumber = 'SO-002'";
    $stmt = $conn->prepare($strsql);
    $stmt->execute();

    //Delete SalesDetail
    $strsql = "DELETE FROM salesdetail WHERE snumber = 'SO-002'";
    $stmt = $conn->prepare($strsql);
    $stmt->execute();

    //Update inventoryserial
    $strsql = "UPDATE inventoryserial SET sold=false, snumber='', solddate=null WHERE serialno in ('EO-001-001', 'EO-001-002', 'EO-002-001', 'EO-002-002')";
    $stmt = $conn->prepare($strsql);
    $stmt->execute();

    Response::success('Clear AddSales Success', 200);
}


if ($received_data->action == "ReceiveItems") {
    $have_error = 0;
    $errormsg = "";

    $conn->beginTransaction();

    #region ===purchasedetail===
    try {
        foreach ($received_data->data->purchasedetail as $row) {
            // ตรวจสอบจำนวนคงเหลือว่ารับได้อีกเท่าไหร่
            $strsql = "SELECT quantitybac, unitprice, taxrate, podate FROM purchasedetail WHERE ponumber=:ponumber AND itemid=:itemid AND poreturn='N'";
            $stmt = $conn->prepare($strsql);
            $stmt->execute([
                'ponumber' => $received_data->data->ponumber,
                'itemid' => $row->itemid
            ]);
            $result_podetail = $stmt->fetchAll((PDO::FETCH_ASSOC));

            if (count($result_podetail)) {
                if ($row->quantity <= $result_podetail[0]['quantitybac']) {
                    $strsql = "UPDATE purchasedetail SET quantity=:quantity WHERE ponumber=:ponumber AND itemid=:itemid AND poreturn='N'";
                    $stmt = $conn->prepare($strsql);
                    $stmt->execute([
                        'quantity' => $row->quantity,
                        'ponumber' => $received_data->data->ponumber,
                        'itemid' => $row->itemid,
                    ]);

                    // Check stocktype = 4 (มี Serial No หรือไม่)
                    $strsql = "SELECT stocktype FROM inventory WHERE itemid=:itemid";
                    $stmt = $conn->prepare($strsql);
                    $stmt->execute([
                        "itemid" => $row->itemid
                    ]);
                    $result_stocktype = $stmt->fetch((PDO::FETCH_ASSOC));

                    if ($result_stocktype['stocktype'] == 4) {
                        foreach ($row->serialno as $row2) {
                            // ตรวจสอบว่ามีการรับ Serial No นี้แล้วหรือยัง
                            $strsql = "SELECT serialno FROM inventoryserial WHERE itemid=:itemid AND serialno=:serialno";
                            $stmt = $conn->prepare($strsql);
                            $stmt->execute([
                                'itemid' => $row->itemid,
                                'serialno' => $row2
                            ]);
                            $result_sn = $stmt->fetchAll((PDO::FETCH_ASSOC));

                            if (count($result_sn)) {
                                $errormsg .= "The serial no : " . $row2 . " already exists.";
                                $have_error = 1;
                            } else {
                                $strsql = "INSERT INTO inventoryserial(itemid, serialno, cost, location, ponumber, orderdate, employee_id, transactiondate)
                                        VALUES(:itemid, :serialno, :cost, :location, :ponumber, :orderdate, :employee_id, :transactiondate)";
                                $stmt = $conn->prepare($strsql);
                                $stmt->execute([
                                    'itemid' => $row->itemid,
                                    'serialno' => $row2,
                                    'cost' => $result_podetail[0]['unitprice'] * (100 + $result_podetail[0]['taxrate']),
                                    'location' => $row->location,
                                    'ponumber' => $received_data->data->ponumber,
                                    'orderdate' => $result_podetail[0]['podate'],
                                    'employee_id' => "Front-end",
                                    'transactiondate' => date("Y-m-d H:i:s")
                                ]);
                            }
                        }
                    }
                } else {
                    $errormsg .= "The remaining quantity back order is not enough to receive";
                    $have_error = 1;
                }
            } else {
                $errormsg .= "Not Found PO or Item in PO";
                $have_error = 1;
            }

            if ($have_error) {
                $conn->rollBack();
                Response::error($errormsg, 428);
                die();
            }
        }

        $conn->commit();
        Response::success('Success', 200);
    } catch (Exception $e) {
        $conn->rollBack();
        $errormsg = $e->getmessage();
        Response::error($errormsg, 404);
        die();
    }

    // Update purchasedetail.quantity 

    #endregion ===purchasedetail===
}

if ($received_data->action == "TransferItems") {

    $trtotal = 0; // เก็บค่าต้นทุนสินค้าที่จะโอนทั้งเอกสาร สำหรับ inventorytr
    $errormsg = "";

    // ตรวจสอบว่าเลขที่ย้ายสินค้าซ้ำหรือไม่
    $strsql = "SELECT trnumber FROM inventorytr WHERE trnumber=:trnumber";
    $stmt = $conn->prepare($strsql);
    $stmt->execute([
        'trnumber' => $received_data->data->trnumber
    ]);
    $result_inventorytr = $stmt->fetchAll((PDO::FETCH_ASSOC));

    // ถ้ามีเลขที่โอนสินค้าอยู่แล้ว
    if (count($result_inventorytr)) {
        $errormsg .= "This trnumber (" . $received_data->data->trnumber . ") already exists.";
        Response::error($errormsg, 404);
        die();
    }

    $conn->beginTransaction();

    try {
        #region ===Start Loop in Items===
        foreach ($received_data->data->item as $row) {
            //ตรวจสอบว่าเป็นสินค้าแบบมี SN หรือไม่
            $strsql = "SELECT stocktype, averagecost AS cost FROM inventory WHERE itemid=:itemid"; //Cost ใช้เป็น averagecost
            $stmt = $conn->prepare($strsql);
            $stmt->execute([
                'itemid' => $row->itemid
            ]);
            $result_inventory = $stmt->fetchAll((PDO::FETCH_ASSOC));

            // ถ้า SN            
            if ($result_inventory[0]['stocktype'] == 4) {
            
                // ตรวจสอบว่า SN นี้ยัง available หรือไม่ และหา cost 
                $strsql = "SELECT serialno, cost FROM inventoryserial WHERE itemid=:itemid AND serialno=:serialno AND sold=FALSE AND posted=TRUE"; //รับเข้าแล้ว (posted=TRUE) และยังไม่ได้ขาย (sold=FALSE)
                $stmt = $conn->prepare($strsql);
                $stmt->execute([
                    'itemid' => $row->itemid,
                    'serialno' => $row->serialno
                ]);
                $result_sn = $stmt->fetchAll((PDO::FETCH_ASSOC));

                if (count($result_sn)) {
                    $strsql = "INSERT INTO inventorytrdetail(trnumber, trdate, itemid, quantity,unitprice, amount, stocktype, isserial, employee_id, transactiondate)
                                VALUES(:trnumber, :trdate, :itemid, :quantity, :unitprice, :amount, :stocktype, :isserial, :employee_id, :transactiondate)";
                    $stmt = $conn->prepare($strsql);
                    $stmt->execute([
                        'trnumber' => $received_data->data->trnumber,
                        'trdate' => $received_data->data->trdate,
                        'itemid' => $row->itemid,
                        'quantity' => $row->quantity,
                        'unitprice' => $result_sn[0]['cost'],
                        'amount' => $result_sn[0]['cost'],
                        'stocktype' => 4,
                        'isserial' => 1,
                        'employee_id' => "Front-end",
                        'transactiondate' => date("Y-m-d H:i:s")
                    ]);

                    $strsql = "UPDATE inventoryserial SET trnumber=:trnumber, employee_id=:employee_id, transactiondate=:transactiondate WHERE itemid=:itemid AND serialno=:serialno";
                    $stmt = $conn->prepare($strsql);
                    $stmt->execute([
                        'trnumber' => $received_data->data->trnumber,
                        'itemid' => $row->itemid,
                        'serialno' => $row->serialno,
                        'employee_id' => "Front-end",
                        'transactiondate' => date("Y-m-d H:i:s")
                    ]);

                    // Sum trtotal
                    $trtotal = $trtotal + $result_sn[0]['cost'];

                } else {
                    $errormsg .= "This item (" . $row->itemid . "/" . $row->serialno . ") not found or sold";
                    $conn->rollBack();
                    Response::error($errormsg, 428);
                    die();
                }
            } else { 
                // ถ้า No SN
                $strsql = "INSERT INTO inventorytrdetail(trnumber, trdate, itemid, quantity,unitprice, amount, stocktype, isserial, employee_id, transactiondate)
                                VALUES(:trnumber, :trdate, :itemid, :quantity, :unitprice, :amount, :stocktype, :isserial, :employee_id, :transactiondate)";
                $stmt = $conn->prepare($strsql);
                $stmt->execute([
                    'trnumber' => $received_data->data->trnumber,
                    'trdate' => $received_data->data->trdate,
                    'itemid' => $row->itemid,
                    'quantity' => $row->quantity,
                    'unitprice' => $result_inventory[0]['cost'],
                    'amount' => $row->quantity * $result_inventory[0]['cost'],
                    'stocktype' => $result_inventory[0]['stocktype'],
                    'isserial' => 0,
                    'employee_id' => "Front-end",
                    'transactiondate' => date("Y-m-d H:i:s")
                ]);

                // Sum trtotal
                $trtotal = $trtotal + ($row->quantity * $result_inventory[0]['cost']);
            }
        }
        #endregion ===Start Loop in Items===

        // Insert into inventorytr
        $strsql = "INSERT INTO inventorytr(trnumber, trdate, locationfr, locationto, trtotal, employee_id, transactiondate)
                        VALUES(:trnumber, :trdate, :locationfr, :locationto, :trtotal, :employee_id, :transactiondate)";
        $stmt = $conn->prepare($strsql);
        $stmt->execute([
            'trnumber' => $received_data->data->trnumber,
            'trdate' => $received_data->data->trdate,
            'locationfr' => $received_data->data->locationfr,
            'locationto' => $received_data->data->locationto,
            'trtotal' => $trtotal,
            'employee_id' => "Front-end",
            'transactiondate' => date("Y-m-d H:i:s")
        ]);

        $conn->commit();
        Response::success('Success', 200);

    } catch (Exception $e) {
        
        $conn->rollBack();
        $errormsg = $e->getmessage();
        Response::error($errormsg, 404);
        die();
    }
}

if ($received_data->action == "DeleteSales") {
    $have_error = 0;
    $errormsg = "";
    $successmsg = "Delete Success";
    $snumber = $received_data->data->snumber;

    $conn->beginTransaction();

    try {
        // ตรวจสอบว่า Sales Order ปิดรายการแล้วหรือยัง
        $strsql = "SELECT posted FROM sales WHERE snumber=:snumber";
        $stmt = $conn->prepare($strsql);
        $stmt->execute([
            'snumber' => $snumber
        ]);
        $result = $stmt->fetchAll((PDO::FETCH_ASSOC));

        if (count($result)) {
            // ตรวจสอบว่าปิดรายการหรือยัง
            if ($result[0]['posted']) {
                // ถ้าปิดรายการแล้ว
                $errormsg .= "This Sales Order No:(" . $snumber . ") was posted.";
                Response::error($errormsg, 428);
                die();
            } else {
                // ถ้ายังไม่ปิดรายการ (ลบ Sales และ SalesDetail)
                $strsql = "DELETE FROM sales WHERE snumber=:snumber";
                $stmt = $conn->prepare($strsql);
                $stmt->execute([
                    'snumber' => $snumber
                ]);

                $strsql = "DELETE FROM salesdetail WHERE snumber=:snumber";
                $stmt = $conn->prepare($strsql);
                $stmt->execute([
                    'snumber' => $snumber
                ]);

                $strsql = "UPDATE inventoryserial SET sold=false, solddate=null, snumber='' WHERE snumber=:snumber";
                $stmt = $conn->prepare($strsql);
                $stmt->execute([
                    'snumber' => $snumber
                ]);

                $conn->commit();
                Response::success($successmsg, 200);
            }
        } else {
            // ไม่พบ Sales Order
            $errormsg .= "This Sales Order No:(" . $snumber . ") not found.";
            Response::error($errormsg, 428);
            die();
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $errormsg = $e->getmessage();
        Response::error($errormsg, 404);
        die();
    }
}
