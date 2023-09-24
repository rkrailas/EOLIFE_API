<?php

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: *");

    require_once 'response.php';
    require_once 'connect.php';
    require_once 'myFunction.php';

    $received_data = json_decode(file_get_contents("php://input"));

    if ($received_data->action == "AddSales") {
        $have_error = 0;
        $errormsg = "";

        $conn->beginTransaction();

        #region ===Customer Start===
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
            if ( !($received_data->data->customer->corporate == "1" or $received_data->data->customer->corporate == "0")) {
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
                try {
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

                } catch (Exception $e) {
                    $conn->rollBack();
                    $errormsg = $e->getmessage();
                    Response::error($errormsg, 404);
                    die();
                
                }
            }  
        }
        #endregion ===Customer Start===

        #region ===Sales Start===
        //snumber ซ้ำหรือไม่
        $strsql = "SELECT snumber FROM sales WHERE snumber= :snumber";
        $stmt = $conn->prepare($strsql);
        $stmt->execute([
            'snumber' => $received_data->data->snumber
        ]);
        $result = $stmt->fetchAll((PDO::FETCH_ASSOC));

        if (count($result)) {
            if ($errormsg) {
                $errormsg .= ", ";
            }
            $errormsg .= "Sales: This SO Number (" . $received_data->data->snumber . ") already exists.";
            $have_error = 1;
        }

        //Check Numeric
        if ( !(is_numeric($received_data->data->sototal)) or !(is_numeric($received_data->data->salestax)) or !(is_numeric($received_data->data->taxratestd))) {
            if ($errormsg) {
                $errormsg .= ", ";
            }
            $errormsg .= "Sales : Invalid Numeric";
            $have_error = 1;
        }

        //Check Date
        if ( !validateDate($received_data->data->sodate) or !validateDate($received_data->data->expirydate) or !validateDate($received_data->data->duedate)) {
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
            try {
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
                    'sototal' => $received_data->data->sototal,
                    'salestax' => $received_data->data->salestax,
                    'exclusivetax' => '1',
                    'closed' => '1',
                    'taxratestd' => $received_data->data->taxratestd,
                    'salesaccount' => $salesaccount,
                    'employee_id' => "Front-end",
                    'transactiondate' => date("Y-m-d H:i:s"),
                ];

                $strsql = "INSERT INTO sales(customerid,snumber,sonumber,sodate,journaldate,deliverydate,expirydate,duedate,sototal,salestax
                            ,exclusivetax,closed,taxratestd,salesaccount,employee_id,transactiondate)
                            VALUES(:customerid,:snumber,:sonumber,:sodate,:journaldate,:deliverydate,:expirydate,:duedate,:sototal,:salestax
                            ,:exclusivetax,:closed,:taxratestd,:salesaccount,:employee_id,:transactiondate)";
                $stmt = $conn->prepare($strsql);
                $stmt->execute($data);
        
                #region ===Salesdetail Start===
                foreach($received_data->data->salesdetail as $row) {
                    //Check Numeric
                    if ( !(is_numeric($row->quantity)) or !(is_numeric($row->unitprice)) or !(is_numeric($row->amount)) or !(is_numeric($row->taxamount)) or !(is_numeric($row->taxrate)) ){
                        if ($errormsg) {
                            $errormsg .= ", ";
                        }
                        $errormsg .= "Sales Detail: Invalid Numeric";
                        $have_error = 1;
                    }
        
                    //Check Date
                    if ( !validateDate($row->sdate) ) {
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
                        try {
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
                            'amount' => $row->amount,
                            'taxamount' => $row->taxamount,
                            'taxrate' => $row->taxrate,
                            'salesac' => $salesac,
                            'stocktype' => $stocktype,
                            'employee_id' => "Front-end",
                            'transactiondate' => date("Y-m-d H:i:s"),
                        ];
        
                        $strsql = "INSERT INTO salesdetail(snumber,sdate,itemid,description,quantity,quantityord,quantitybac,unitprice,amount,taxamount,taxrate,salesac,stocktype,employee_id,transactiondate)
                                    VALUES(:snumber,:sdate,:itemid,:description,:quantity,:quantityord,:quantitybac,:unitprice,:amount,:taxamount,:taxrate,:salesac,:stocktype,:employee_id,:transactiondate)";
                        $stmt = $conn->prepare($strsql);
                        $stmt->execute($data);

                        //===Inventoryserial Start===
                        foreach($row->serialno as $row2) {
                
                            //Check SN Available
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
                                try {
                                //Update InventorySerial
                                $data = [
                                    "itemid" => $row->itemid,
                                    "serialno" => $row2,
                                    "snumber" => $row->snumber,
                                    "solddate" => $row->sdate,
                                    'employee_id' => "Front-end",
                                    'transactiondate' => date("Y-m-d H:i:s"),
                                ];
                
                                $strsql = "UPDATE inventoryserial SET snumber=:snumber, solddate=:solddate, sold=TRUE, employee_id=:employee_id, transactiondate=:transactiondate WHERE itemid=:itemid and serialno=:serialno";
                                $stmt = $conn->prepare($strsql);
                                $stmt->execute($data);
                                //.Insert SalesDetail
                
                                } catch (Exception $e) {
                                    $conn->rollBack();
                                    $errormsg = $e->getmessage();
                                    Response::error($errormsg, 404);
                                    die();
                
                                }
                            }
                        }
                        //===Inventoryserial End===
        
                        } catch (Exception $e) {
                            $conn->rollBack();
                            $errormsg = $e->getmessage();
                            Response::error($errormsg, 404);
                            die();
        
                        }
                    }
                }
                #endregion ===Salesdetail End===

            } catch (Exception $e) {
                $conn->rollBack();
                $errormsg = $e->getmessage();
                Response::error($errormsg, 404);
                die();

            }
            #endregion ===Sales End===

        }

        $conn->commit();
        Response::success('Success', 200);
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
?>
