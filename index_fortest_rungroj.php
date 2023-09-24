<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VueJS CRUD APP with PHP</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">

</head>
<body>
    <div class="container mt-5" id="App">
        <br>
        <h3 align="center">Test Call API</h3>
        <hr>
        <br>
        <div class="row">
            <div class="col md-12">
                <input type="button" class="btn btn-success btn-xs" data-bs-toggle="modal" data-bs-target="#myModal" value="Add Sales Order" @click="addSalesOrder">
                <input type="button" class="btn btn-danger btn-xs" data-bs-toggle="modal" data-bs-target="#myModal" value="Clear Sales Order" @click="ClearSalesOrder">
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/vue/dist/vue.js"></script>
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let app = new Vue({
            el: '#App',
            data: {
            },
            methods: {
                addSalesOrder() {
                    axios.post('http://103.114.200.157/EOLife_API/action.php', 
                            {
                                action: 'AddSales',
                                data: {
                                    snumber: '650499',
                                    sodate: '2023-09-02',
                                    expirydate: '2023-09-02',
                                    duedate: '2023-09-02',
                                    sototal: 62000.00,
                                    salestax: 4056.0747663551,
                                    taxratestd: 7,
                                    sodiscount: 0.00,
                                    customer: {
                                        customerid: '77',
                                        name: 'โรงพยาบาลจุฬาลงกรณ์ สภากาชาดไทย',
                                        address1: '1873 ถนนพระราม 4 แขวงปทุมวัน เขตปทุมวัน กรุงเทพมหานคร 10330',
                                        address2: '',
                                        city: '',
                                        state: '',
                                        zipcode: '',
                                        email: '',
                                        fax: '',
                                        phone: '',
                                        taxid: '0994000160127',
                                        corporate: 0,
                                        branchno: '00000',
                                        creditlimit: 9999999,
                                        taxtype: 'Standard'
                                    },
                                    salesdetail: [{
                                            snumber: '650499',
                                            sdate: '2023-09-02',
                                            itemid: 'EO-001',
                                            description: 'AIRSENSE 10 AUTOSET APAC TRI C',
                                            quantity: 1,
                                            unitprice: 57943.925233645,
                                            amount: 57943.925233645,
                                            taxamount: 4340,
                                            taxrate: 7,
                                            discountamount: 0,
                                            serialno: ['22222074996']
                                        }
                                    ]
                                }
                            }
                    ).then(res => {
                        alert(res.data.message);  
                    }).catch(error => {
                        alert('Error!!! ' + error.response.data.message);
                    })
                    
                },
                ClearSalesOrder() {                    
                    axios.post('http://103.114.200.157/EOLife_API/action.php', {
                        action : 'Clear_AddSales'
                    }
                    ).then(res => {
                        alert(res.data.message);
                    }
                    )
                }
            }
            })
    </script>
</body>
</html>
