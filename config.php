<?php
/*
|--------------------------------------------------------------------------
| FILE KONFIGURASI UTAMA (JANTUNG SISTEM)
|--------------------------------------------------------------------------
|
| File ini berisi 4 hal penting:
| 1. Session Start: Memulai sesi untuk login.
| 2. BASE_URL: Untuk path hosting yang gampang di-deploy.
| 3. Zona Waktu: Menyamakan waktu server.
| 4. Koneksi PDO: Jantung koneksi database yang aman.
|
*/

// 1. SESSION START (WAJIB paling atas)
// Untuk menangani status login di semua halaman
session_start();

// 2. BASE_URL (Solusi Path Hosting)
// Ganti ini saat deploy ke hosting asli
define('BASE_URL', '/puskesmas/');

// 3. ZONA WAKTU
date_default_timezone_set('Asia/Jakarta');

// ==================================================================
// 4. KONEKSI DATABASE (PDO)
// ==================================================================

// --- Setting Kredensial Database ---
$db_host = "127.0.0.1";     // atau "localhost"
$db_name = "dbpuskesmas";   // Nama database Anda
$db_user = "root";          // User default XAMPP
$db_pass = "";              // Password default XAMPP (biasanya kosong)
$charset = "utf8mb4";

// --- Setting DSN (Data Source Name) ---
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$charset";

// --- Setting Opsi PDO (Penting untuk Error Handling) ---
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Melempar error jika query gagal
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // Hasil query jadi array asosiatif
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Menggunakan prepared statements asli
];

// --- Membuat Objek Koneksi PDO ---
try {
    // $pdo adalah "koneksi ajaib" yang akan kita pakai di semua file
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
    // Jika koneksi gagal, hentikan script dan tampilkan error
    // (Di mode produksi, matikan tampilan error ini)
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

?>