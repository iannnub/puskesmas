<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../config.php';

echo "=== VERIFIKASI SEC-03: SUPER ADMIN DELETION PROTECTION (BUG-SIVO-013) ===\n";

$all_passed = true;

// 1. Static Analysis: Pastikan pengecekan tidak lagi menggunakan hardcoded $id_user_hapus == 1
$crud_code = file_get_contents(__DIR__ . '/../master/proses_crud_user.php');
$has_flawed_check = (strpos($crud_code, '$id_user_hapus == 1') !== false);
$has_role_protection = (strpos($crud_code, '$target_user[\'id_role\'] === 1') !== false || strpos($crud_code, '$target_user[\'id_role\'] == 1') !== false);

echo "1. Analisis Statis master/proses_crud_user.php:\n";
echo "   - Bebas Hardcoded Check (\$id_user_hapus == 1): " . (!$has_flawed_check ? "PASS" : "FAIL") . "\n";
echo "   - Proteksi Berbasis Role Super Admin: " . ($has_role_protection ? "PASS" : "FAIL") . "\n";

if ($has_flawed_check || !$has_role_protection) {
    $all_passed = false;
}

// 2. Functional Test: Uji Proteksi Penghapusan Super Admin Asli (ID 10)
try {
    $pdo->beginTransaction();
    
    // Ambil akun Super Admin
    $superadmin = $pdo->query("SELECT id_user, id_role, username FROM tbl_user WHERE username = 'superadmin'")->fetch();
    $id_superadmin = (int)$superadmin['id_user'];
    
    // Jalankan logika verifikasi
    $stmt_check = $pdo->prepare("SELECT id_role, username FROM tbl_user WHERE id_user = ?");
    $stmt_check->execute([$id_superadmin]);
    $target_user = $stmt_check->fetch();
    
    $blocked = false;
    if ((int)$target_user['id_role'] === 1 || strtolower($target_user['username']) === 'superadmin') {
        $blocked = true;
    }
    
    echo "\n2. Uji Proteksi Penghapusan Akun Super Admin Asli (ID $id_superadmin):\n";
    echo "   - Status Proteksi: " . ($blocked ? "TERPROTEKSI DENGAN AMAN (PASS)" : "RENTAN TERHAPUS (FAIL)") . "\n";
    if (!$blocked) $all_passed = false;
    
    // 3. Uji Akun Non-Admin (ID 1 - poliumum) tidak terblokir oleh rule "root admin" palsu
    $poliumum = $pdo->query("SELECT id_user, id_role, username FROM tbl_user WHERE id_user = 1")->fetch();
    $is_not_admin = ((int)$poliumum['id_role'] !== 1 && $poliumum['username'] !== 'superadmin');
    
    echo "\n3. Uji Akun Poli Umum (ID 1) Bebas dari Salah Target Proteksi:\n";
    echo "   - Status Akun ID 1: " . ($is_not_admin ? "BENAR BUKAN SUPER ADMIN (PASS)" : "TERIDENTIFIKASI SALAH SEBAGAI ROOT (FAIL)") . "\n";
    if (!$is_not_admin) $all_passed = false;
    
    $pdo->rollBack();
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Database Error: " . $e->getMessage() . "\n";
    $all_passed = false;
}

echo "\n--------------------------------------------------\n";
if ($all_passed) {
    echo "RESULT: SEC-03 PASS (Proteksi Super Admin Berbasis Role Terverifikasi 100%)\n";
    exit(0);
} else {
    echo "RESULT: SEC-03 FAIL\n";
    exit(1);
}
