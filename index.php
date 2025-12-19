<?php
/*
|--------------------------------------------------------------------------
| PINTU GERBANG UTAMA (index.php)
|--------------------------------------------------------------------------
|
| File ini tidak berisi HTML.
| Tugasnya hanya satu: mengecek status login dari session
| dan mengarahkan user ke halaman yang tepat.
|
*/

// 1. Panggil "jantung" config.php (untuk session_start() dan BASE_URL)
require_once 'config.php';

// 2. Cek apakah user SUDAH login? (Session 'user_id' ada?)
if (isset($_SESSION['user_id'])) {
    
    // 3. Jika SUDAH, tendang ke Dashboard
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
    
} else {
    
    // 4. Jika BELUM, tendang ke Halaman Login
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
    
}

// Tidak ada kode HTML di file ini.
?>