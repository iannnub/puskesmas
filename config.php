<?php

session_start();


define('BASE_URL', '/puskesmas/');


date_default_timezone_set('Asia/Jakarta');

$db_host = "127.0.0.1";     
$db_name = "dbpuskesmas";   
$db_user = "root";          
$db_pass = "";              
$charset = "utf8mb4";


$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$charset";


$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     
    PDO::ATTR_EMULATE_PREPARES   => false,                  
];


try {

    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {

    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

?>