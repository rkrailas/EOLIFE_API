<?php

    require_once 'response.php';
    require_once 'connect.php';
    require_once 'myFunction.php';

    $received_data = json_decode(file_get_contents("php://input"));

    foreach($received_data->data as $row) {
        print $row->itemid . "\n";
    }
    
    print $received_data->action . "\n";
    
?>  