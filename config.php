<?php

session_start();


// Cek apakah berjalan di localhost (lokal) atau di hosting (produksi)
$is_localhost = ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1' || strpos($_SERVER['HTTP_HOST'], 'localhost') !== false);

if ($is_localhost) {
    // Konfigurasi LOKAL (XAMPP)
    define('BASE_URL', '/puskesmas/');
    $db_host = "127.0.0.1";
    $db_name = "dbpuskesmas";
    $db_user = "root";
    $db_pass = "";
} else {
    // Konfigurasi PRODUKSI (InfinityFree)
    // Jika Anda meletakkan semua isi folder puskesmas langsung ke dalam htdocs di hosting, BASE_URL harus '/'
    define('BASE_URL', '/'); 
    $db_host = "sql105.infinityfree.com";
    $db_name = "if0_42295308_dbpuskesmas";
    $db_user = "if0_42295308";
    $db_pass = "m14s4m14123";
}

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
    error_log($e->getMessage());
    http_response_code(500);
    die("Kesalahan koneksi ke database. Silakan hubungi administrator.");
}

?>