<?php
// templates/auth_check.php
// Pemeriksaan Autentikasi Pengguna, Inactivity Timeout (BUG-SIVO-017), dan Proteksi Cache Browser (CWE-525)

// 1. Validasi Keberadaan Sesi Pengguna
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "auth/login.php?error=2"); 
    exit;
}

// 2. Batas waktu sesi tidak aktif: 30 menit (1800 detik) - TC-AUTH-06 / BUG-SIVO-017
$session_timeout = 1800;

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $session_timeout)) {
    // Musnahkan sesi akibat inaktivitas
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    header("Location: " . BASE_URL . "auth/login.php?error=timeout");
    exit;
}

// Perbarui timestamp aktivitas terakhir
$_SESSION['LAST_ACTIVITY'] = time();

// 3. Cegah browser caching untuk seluruh halaman terautentikasi (TC-AUTH-07 / CWE-525)
if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
}
?>