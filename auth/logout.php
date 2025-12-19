<?php
/*
|--------------------------------------------------------------------------
| PROSES LOGOUT (auth/logout.php)
|--------------------------------------------------------------------------
|
| File ini menghancurkan session login dan mengembalikan user
| ke halaman login.
|
*/

// 1. Panggil "jantung" config.php (WAJIB untuk session_start())
require_once '../config.php';

// 2. Hancurkan semua data session yang terdaftar
$_SESSION = [];

// 3. Hancurkan session-nya
session_destroy();

// 4. Hapus cookie session (Best practice untuk keamanan)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 5. Redirect paksa kembali ke halaman login
header("Location: " . BASE_URL . "auth/login.php");
exit;
?>