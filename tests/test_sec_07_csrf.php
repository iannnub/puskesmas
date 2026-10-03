<?php
// tests/test_sec_07_csrf.php
// Verifikasi Implementasi CSRF Token Protection & Hardening State-Changing Deletions (SEC-07 / BUG-SIVO-009)

$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../config.php';

echo "====================================================================\n";
echo "   TEST SEC-07 / BUG-SIVO-009: CSRF Token & Deletion Hardening       \n";
echo "====================================================================\n\n";

$_SESSION['user_id'] = 1;
$_SESSION['role_id'] = 1;

$test_passed = true;

// 1. Verifikasi fungsi csrf_token() dan csrf_field()
echo "[1] Menguji fungsi helper CSRF...\n";
$token1 = csrf_token();
$token2 = csrf_token();
$field  = csrf_field();

if (strlen($token1) === 64 && ctype_xdigit($token1) && $token1 === $token2) {
    echo "   -> PASS: csrf_token() menghasilkan 32-byte hex (64 chars) konsisten dalam sesi.\n";
} else {
    echo "   -> FAIL: Format csrf_token() tidak valid.\n";
    $test_passed = false;
}

if (strpos($field, 'name="csrf_token"') !== false && strpos($field, $token1) !== false) {
    echo "   -> PASS: csrf_field() merender hidden input dengan token yang benar.\n";
} else {
    echo "   -> FAIL: csrf_field() tidak valid.\n";
    $test_passed = false;
}

// 2. Verifikasi verify_csrf()
echo "[2] Menguji mekanisme validasi verify_csrf()...\n";
$valid_check   = verify_csrf($token1);
$invalid_check = verify_csrf('attacker_forged_token');
$empty_check   = verify_csrf('');

if ($valid_check === true && $invalid_check === false && $empty_check === false) {
    echo "   -> PASS: verify_csrf() berhasil memvalidasi token sah dan menolak token palsu/kosong.\n";
} else {
    echo "   -> FAIL: Logika verify_csrf() tidak konsisten.\n";
    $test_passed = false;
}

// 3. Verifikasi proteksi pada file-file CRUD
echo "[3] Memverifikasi kehadiran proteksi verify_csrf() pada file-file proses delete...\n";
$files_to_check = [
    'master/proses_crud_obat.php'      => 'Master Obat',
    'master/proses_crud_user.php'      => 'Master User',
    'master/proses_crud_unit.php'      => 'Master Unit',
    'master/proses_crud_poli.php'      => 'Master Poli',
    'master/proses_crud_pelayanan.php' => 'Master Pelayanan',
    'master/proses_tambah_dokter.php'  => 'Master Dokter',
    'resep/proses_hapus.php'           => 'Pembatalan Resep'
];

foreach ($files_to_check as $rel_path => $label) {
    $full_path = __DIR__ . '/../' . $rel_path;
    $content = file_get_contents($full_path);
    if (strpos($content, 'verify_csrf') !== false) {
        echo "   -> PASS: $label ($rel_path) terproteksi dengan verify_csrf.\n";
    } else {
        echo "   -> FAIL: $label ($rel_path) BELUM memiliki verify_csrf!\n";
        $test_passed = false;
    }
}

// 4. Verifikasi bahwa UI template menyematkan parameter csrf_token pada aksi hapus
echo "[4] Memverifikasi bahwa antarmuka master data menyematkan csrf_token pada link hapus...\n";
$views_to_check = [
    'master/data_obat.php'      => 'csrf_token',
    'master/data_user.php'      => 'csrf_token',
    'master/data_unit.php'      => 'csrf_token',
    'master/data_poli.php'      => 'csrf_token',
    'master/data_pelayanan.php' => 'csrf_token',
    'master/data_dokter.php'    => 'csrf_token'
];

foreach ($views_to_check as $rel_path => $pattern) {
    $full_path = __DIR__ . '/../' . $rel_path;
    $content = file_get_contents($full_path);
    if (strpos($content, $pattern) !== false) {
        echo "   -> PASS: View $rel_path menyematkan parameter $pattern pada link hapus.\n";
    } else {
        echo "   -> FAIL: View $rel_path tidak menyematkan $pattern pada link hapus!\n";
        $test_passed = false;
    }
}

echo "\n--------------------------------------------------------------------\n";
if ($test_passed) {
    echo "HASIL AKHIR TEST SEC-07 / BUG-SIVO-009: 100% LULUS (ALL TESTS PASSED)!\n";
} else {
    echo "HASIL AKHIR TEST SEC-07 / BUG-SIVO-009: DITEMUKAN KEGAGALAN!\n";
}
echo "--------------------------------------------------------------------\n";
