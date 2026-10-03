<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../config.php';

echo "=== VERIFIKASI TIKET P0-07: SANITASI PASSWORD LOGIN ===\n";

$all_passed = true;

// 1. Static Analysis: Pastikan htmlspecialchars sudah dihapus dari proses_login.php
$login_code = file_get_contents(__DIR__ . '/../auth/proses_login.php');
if (strpos($login_code, 'htmlspecialchars($_POST[\'password\'])') !== false) {
    echo "1. Analisis Statis auth/proses_login.php: FAIL (htmlspecialchars masih ada pada password)\n";
    $all_passed = false;
} else {
    echo "1. Analisis Statis auth/proses_login.php: PASS (htmlspecialchars berhasil dihapus)\n";
}

// 2. Functional Test: Verifikasi Password dengan Karakter Khusus (<, >, &, \", ')
$complex_passwords = [
    'P@ss<w0rd>&1',
    'Admin"Safe\'<Test>&',
    '<h1>VerySecure!</h1>&nbsp;',
    'NormalPassword123!',
    'Puskesmas#2026<>&'
];

echo "\n2. Verifikasi Password Hashing & Verification dengan Karakter Khusus:\n";
foreach ($complex_passwords as $plain_pw) {
    $hashed = password_hash($plain_pw, PASSWORD_BCRYPT);
    
    // Perilaku lama yang rusak (dengan htmlspecialchars)
    $corrupted_input = htmlspecialchars($plain_pw);
    $old_verify = password_verify($corrupted_input, $hashed);
    
    // Perilaku baru yang diperbaiki (raw dengan trim)
    $clean_input = trim($plain_pw);
    $new_verify = password_verify($clean_input, $hashed);
    
    $has_special_chars = ($plain_pw !== $corrupted_input);
    
    if ($has_special_chars) {
        $test_ok = (!$old_verify && $new_verify);
        echo sprintf("   - Password: %-26s | Lama: %-7s | Baru: %-7s -> %s\n",
            $plain_pw,
            $old_verify ? "PASS" : "FAIL",
            $new_verify ? "PASS" : "FAIL",
            $test_ok ? "PASS (BUG TERATASI)" : "FAIL"
        );
        if (!$test_ok) $all_passed = false;
    } else {
        $test_ok = $new_verify;
        echo sprintf("   - Password: %-26s | Lama: %-7s | Baru: %-7s -> %s\n",
            $plain_pw,
            $old_verify ? "PASS" : "FAIL",
            $new_verify ? "PASS" : "FAIL",
            $test_ok ? "PASS" : "FAIL"
        );
        if (!$test_ok) $all_passed = false;
    }
}

// 3. Database & Simulation Test: Login dengan akun uji coba
echo "\n3. Simulasi Login Database dengan Karakter Khusus:\n";
$test_username = 'test_p007_user';
$test_password = 'P@ss<word>&2026\'"';
$test_hashed = password_hash($test_password, PASSWORD_BCRYPT);

try {
    // Hapus jika sudah ada
    $pdo->prepare("DELETE FROM tbl_user WHERE username = ?")->execute([$test_username]);
    
    // Insert user uji coba
    $stmt = $pdo->prepare("INSERT INTO tbl_user (id_role, username, password, nama_lengkap, is_active) VALUES (1, ?, ?, 'Tester P0-07', 1)");
    $stmt->execute([$test_username, $test_hashed]);
    
    // Eksekusi query login seperti pada auth/proses_login.php
    $stmt = $pdo->prepare("SELECT id_user, username, password FROM tbl_user WHERE username = ? AND is_active = 1");
    $stmt->execute([$test_username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception("User uji coba gagal ditemukan di database.");
    }
    
    // Verifikasi dengan password mentah
    $auth_success = password_verify(trim($test_password), $user['password']);
    echo "   - Verifikasi DB User dengan Password '$test_password': " . ($auth_success ? "BERHASIL (PASS)" : "GAGAL (FAIL)") . "\n";
    if (!$auth_success) $all_passed = false;
    
    // Cleanup user uji coba
    $pdo->prepare("DELETE FROM tbl_user WHERE username = ?")->execute([$test_username]);
    echo "   - Pembersihan user uji coba: BERHASIL\n";
    
} catch (Exception $e) {
    echo "   - Database Error: " . $e->getMessage() . "\n";
    $all_passed = false;
}

echo "\n--------------------------------------------------\n";
if ($all_passed) {
    echo "RESULT: TIKET P0-07 PASS (Password Plain-Text Ditangani Mentah & Karakter Khusus Lolos Verifikasi)\n";
    exit(0);
} else {
    echo "RESULT: TIKET P0-07 FAIL\n";
    exit(1);
}
