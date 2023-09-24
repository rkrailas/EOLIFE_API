<?php

$host= 'localhost';
$db = 'Training';
$user = 'postgres';
$password = 'x';

// require_once 'config_db.php';

try{
    $dsn = "pgsql:host=$host;port=5432;dbname=$db;";
	// make a database connection
	$conn = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

} catch (PDOException $e) {
    echo "Connection Failed: " . $e->getMessage();
    exit();
}


?>