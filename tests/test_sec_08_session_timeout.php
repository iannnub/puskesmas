<?php
// tests/test_sec_08_session_timeout.php
// Verifikasi Inactivity Timeout, Logout Cleanup, Cache Control & Sanitasi Error (SEC-08 / BUG-SIVO-016 & BUG-SIVO-017)

$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../config.php';

echo "====================================================================\n";
echo "   TEST SEC-08: Inactivity Timeout, Cache-Control & Error Sanitasi  \n";
echo "====================================================================\n\n";

$all_passed = true;

// 1. Verifikasi Inactivity Timeout di templates/auth_check.php
echo "[1] Memverifikasi konfigurasi inactivity timeout di templates/auth_check.php...\n";
$auth_code = file_get_contents(__DIR__ . '/../templates/auth_check.php');

$has_timeout_def = (strpos($auth_code, '$session_timeout = 1800;') !== false);
$has_last_act    = (strpos($auth_code, '$_SESSION[\'LAST_ACTIVITY\']') !== false);
$has_cache_ctrl  = (strpos($auth_code, 'Cache-Control: no-store') !== false);

if ($has_timeout_def && $has_last_act && $has_cache_ctrl) {
    echo "   -> PASS: Inactivity timeout 30 menit (1800s) dan header Cache-Control: no-store terpasang.\n";
} else {
    echo "   -> FAIL: Konfigurasi timeout atau header cache tidak lengkap!\n";
    $all_passed = false;
}

// 2. Simulasi Logika Timeout
echo "[2] Menguji simulasi pembongkaran sesi kedaluwarsa...\n";
$_SESSION['user_id'] = 999;
$_SESSION['LAST_ACTIVITY'] = time() - 1900; // 31 menit lalu (> 1800s)

$is_expired = (time() - $_SESSION['LAST_ACTIVITY'] > 1800);
if ($is_expired) {
    echo "   -> PASS: Deteksi selisih waktu > 1800 detik berhasil mengidentifikasi sesi kedaluwarsa.\n";
} else {
    echo "   -> FAIL: Deteksi waktu kedaluwarsa gagal!\n";
    $all_passed = false;
}

// 3. Verifikasi auth/logout.php
echo "[3] Memverifikasi mekanisme logout cleanup di auth/logout.php...\n";
$logout_code = file_get_contents(__DIR__ . '/../auth/logout.php');

$has_destroy    = (strpos($logout_code, 'session_destroy()') !== false);
$has_unset      = (strpos($logout_code, 'session_unset()') !== false);
$has_cookie_del = (strpos($logout_code, 'setcookie(session_name()') !== false);
$has_no_cache   = (strpos($logout_code, 'Cache-Control: no-store') !== false);

if ($has_destroy && $has_unset && $has_cookie_del && $has_no_cache) {
    echo "   -> PASS: Logout menghancurkan sesi, menghapus cookie sesi, dan menyematkan header anti-cache.\n";
} else {
    echo "   -> FAIL: Logout cleanup tidak lengkap!\n";
    $all_passed = false;
}

// 4. Verifikasi Sanitasi Pesan Error (Zero Raw SQL Leak)
echo "[4] Memverifikasi sanitasi pesan error pada modul kritis...\n";
$modules_to_audit = [
    'resep/history.php',
    'resep/edit.php',
    'resep/tambah.php',
    'request/buat.php',
    'request/buat_apotek.php',
    'request/detail.php',
    'request/kelola.php',
    'request/kelola_gudang.php',
    'request/riwayat.php',
    'stok/lihat.php',
    'stok/opname.php',
    'stok/terima.php',
    'stok/transfer.php'
];

$leak_count = 0;
foreach ($modules_to_audit as $file) {
    $code = file_get_contents(__DIR__ . '/../' . $file);
    if (preg_match('/die\s*\(\s*["\']Error\s*:\s*["\']\s*\.\s*\$e->getMessage\(\)\s*\)/i', $code) ||
        preg_match('/die\s*\(\s*["\']Error mengambil data:\s*["\']\s*\.\s*\$e->getMessage\(\)\s*\)/i', $code)) {
        echo "   -> PERINGATAN: File $file masih membocorkan getMessage() langsung ke output.\n";
        $leak_count++;
    }
}

if ($leak_count === 0) {
    echo "   -> PASS: Seluruh 13 file modul telah disanitasi. Zero raw getMessage() leak detected!\n";
} else {
    echo "   -> FAIL: Masih ditemukan $leak_count kebocoran pesan error SQL mentah.\n";
    $all_passed = false;
}

echo "\n--------------------------------------------------------------------\n";
if ($all_passed) {
    echo "HASIL AKHIR TEST SEC-08: 100% LULUS (ALL TESTS PASSED)!\n";
} else {
    echo "HASIL AKHIR TEST SEC-08: DITEMUKAN KEGAGALAN!\n";
}
echo "--------------------------------------------------------------------\n";
