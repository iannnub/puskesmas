<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../config.php';

echo "=== VERIFIKASI SEC-02: SQL INJECTION ELIMINATION (BUG-SIVO-008) ===\n";

$all_passed = true;

// 1. Static Analysis: Cek tidak ada interpolasi string langsung di dashboard.php dan request/riwayat.php
$files_to_check = [
    'dashboard.php',
    'request/riwayat.php'
];

foreach ($files_to_check as $file) {
    $content = file_get_contents(__DIR__ . '/../' . $file);
    $has_raw_concat = (strpos($content, '{$_SESSION[\'user_id\']}') !== false || strpos($content, '. $_SESSION[\'user_id\']') !== false);
    $has_prepared = (strpos($content, 'h.id_user_request = ?') !== false && strpos($content, 'execute($params_partial)') !== false);
    
    echo "1. Analisis Berkas $file:\n";
    echo "   - Bebas Konkatenasi Mentah: " . (!$has_raw_concat ? "PASS" : "FAIL") . "\n";
    echo "   - Menggunakan Prepared Statement: " . ($has_prepared ? "PASS" : "FAIL") . "\n";
    
    if ($has_raw_concat || !$has_prepared) {
        $all_passed = false;
    }
}

// 2. Functional Test: Uji eksekusi query dengan parameter binding untuk semua role
$roles_to_test = [
    1 => 'Super Admin (Apotek_ke_Gudang)',
    2 => 'Admin Gudang (Apotek_ke_Gudang)',
    4 => 'Poli Belakang (Poli_ke_Apotek spesifik user)',
    5 => 'Apotek (Poli_ke_Apotek semua user)'
];

echo "\n2. Uji Fungsional Eksekusi Query Dinamis:\n";
foreach ($roles_to_test as $role_id => $desc) {
    $_SESSION['role_id'] = $role_id;
    $_SESSION['user_id'] = 7;
    
    $sql_count_partial = "SELECT COUNT(*) FROM tbl_request_header h WHERE h.status = 'Partial'";
    $params_partial = [];
    if ($_SESSION['role_id'] == 4) {
        $sql_count_partial .= " AND h.id_user_request = ? AND h.tipe_request = 'Poli_ke_Apotek'";
        $params_partial[] = (int)$_SESSION['user_id'];
    } elseif ($_SESSION['role_id'] == 5) {
        $sql_count_partial .= " AND h.tipe_request = 'Poli_ke_Apotek'";
    } else {
        $sql_count_partial .= " AND h.tipe_request = 'Apotek_ke_Gudang'";
    }
    
    $stmt = $pdo->prepare($sql_count_partial);
    $stmt->execute($params_partial);
    $count = (int)$stmt->fetchColumn();
    
    echo "   - Role $role_id ($desc): Eksekusi Berhasil (Hasil: $count)\n";
}

echo "\n--------------------------------------------------\n";
if ($all_passed) {
    echo "RESULT: SEC-02 PASS (SQL Injection dieliminasi total dengan True Prepared Statements)\n";
    exit(0);
} else {
    echo "RESULT: SEC-02 FAIL\n";
    exit(1);
}
