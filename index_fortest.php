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
                                action : 'AddSales',
                                data : 
                                {
                                    snumber : 'SO-002',
                                    sodate : '2023-07-27',
                                    expirydate : '2023-08-27',
                                    duedate : '2023-08-27',
                                    sototal : 5350,
                                    salestax : 350,
                                    taxratestd : 7,
                                    sodiscount: 0.00,
                                    customer : {
                                                    customerid : 'CUS-002',
                                                    name : 'xxxxxx',
                                                    address1 : 'xxxxxx',
                                                    address2 : 'xxxxxx',
                                                    city : 'xxxxxx',
                                                    state : 'xxxxxx',
                                                    zipcode : 'xxxxxx',
                                                    email : 'xxx@gmail.com',
                                                    fax : '111111',
                                                    phone : '11111',
                                                    taxid : '11111',
                                                    corporate : 0,
                                                    branchno : '00000',
                                                    creditlimit : 9999999,
                                                    taxtype : 'Standard'
                                                },
                                    salesdetail : [
                                                        {
                                                            snumber : 'SO-002',
                                                            sdate : '2023-07-27',
                                                            itemid : 'EO-001',
                                                            description : '2023-07-27',
                                                            quantity : 2,
                                                            unitprice : 1000,
                                                            amount : 2140,
                                                            taxamount : 140,
                                                            taxrate : 7,
                                                            discountamount: 0,
                                                            serialno : ['EO-001-001', 'EO-001-002']
                                                        },
                                                        {
                                                            snumber : 'SO-002',
                                                            sdate : '2023-07-27',
                                                            itemid : 'EO-002',
                                                            description : '2023-07-27',
                                                            quantity : 2,
                                                            unitprice : 1500,
                                                            amount : 3210,
                                                            taxamount : 210,
                                                            taxrate : 7,
                                                            discountamount: 0,
                                                            serialno : ['EO-002-001', 'EO-002-002']
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
