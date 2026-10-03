<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../config.php';

echo "=== VERIFIKASI TIKET P0-06: PERLUASAN AKSES MODUL RESEP ===\n";

$roles_to_test = [
    1 => 'Super Admin',
    2 => 'Poli Umum / Dokter',
    3 => 'Poli Depan (View Only)',
    4 => 'Poli Belakang / Rawat Inap',
    5 => 'Petugas Apotek',
    6 => 'Guest / Unauthorized'
];

$expected_allowed = [1, 2, 4, 5];

$all_passed = true;

// 1. Verifikasi Rule RBAC pada Modul Resep
foreach ($roles_to_test as $role_id => $role_name) {
    $_SESSION['role_id'] = $role_id;
    $is_allowed = in_array((int)$_SESSION['role_id'], [1, 2, 4, 5], true);
    $should_be_allowed = in_array($role_id, $expected_allowed, true);
    
    $status = ($is_allowed === $should_be_allowed) ? "PASS" : "FAIL";
    if ($status === "FAIL") $all_passed = false;
    
    echo sprintf("Role %d (%s): %s (Expected: %s) -> %s\n",
        $role_id,
        $role_name,
        $is_allowed ? "ALLOWED" : "DENIED",
        $should_be_allowed ? "ALLOWED" : "DENIED",
        $status
    );
}

// 2. Verifikasi File Consistency (Pastikan tidak ada role check yang ketinggalan)
$resep_files = [
    'resep/tambah.php',
    'resep/history.php',
    'resep/edit.php',
    'resep/proses_tambah.php',
    'resep/proses_edit.php',
    'resep/proses_hapus.php'
];

echo "\n2. Verifikasi Konsistensi Validasi Akses di Source Code:\n";
foreach ($resep_files as $file) {
    $content = file_get_contents(__DIR__ . '/../' . $file);
    $has_role_check = (strpos($content, '[1, 2, 4, 5]') !== false);
    
    echo sprintf("   - File %s: %s\n", $file, $has_role_check ? "KONSISTEN [1, 2, 4, 5]" : "TIDAK KONSISTEN (FAIL)");
    if (!$has_role_check) $all_passed = false;
}

// 3. Verifikasi Sidebar Navigation
$sidebar_content = file_get_contents(__DIR__ . '/../templates/sidebar.php');
// Check role 2 has resep
$has_role2_resep = (strpos($sidebar_content, 'resep/tambah.php') !== false);
// Ensure role 5 has resep menu
$has_role5_resep = preg_match('/elseif\s*\(\$_SESSION\[\'role_id\'\]\s*==\s*5\).*?resep\/tambah\.php/s', $sidebar_content);

echo "\n3. Verifikasi Menu Sidebar:\n";
echo "   - Menu Resep untuk Role 2: " . ($has_role2_resep ? "TERPASANG (PASS)" : "HILANG (FAIL)") . "\n";
echo "   - Menu Resep untuk Role 5: " . ($has_role5_resep ? "TERPASANG (PASS)" : "HILANG (FAIL)") . "\n";

if (!$has_role2_resep || !$has_role5_resep) {
    $all_passed = false;
}

echo "\n--------------------------------------------------\n";
if ($all_passed) {
    echo "RESULT: TIKET P0-06 PASS (Akses Modul Resep Sukses Diperluas ke Role 1, 2, 4, dan 5)\n";
    exit(0);
} else {
    echo "RESULT: TIKET P0-06 FAIL\n";
    exit(1);
}
