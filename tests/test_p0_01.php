<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../config.php';

echo "=== VERIFIKASI TIKET P0-01 ===\n";

// 1. Cek Kolom tbl_request_header
$stmt = $pdo->query("SHOW COLUMNS FROM tbl_request_header LIKE 'tipe_request'");
$col_tipe = $stmt->fetch(PDO::FETCH_ASSOC);
echo "1. tbl_request_header.tipe_request: " . ($col_tipe ? "OK (" . $col_tipe['Type'] . ")" : "FAIL") . "\n";

$stmt = $pdo->query("SHOW COLUMNS FROM tbl_request_header LIKE 'status'");
$col_status = $stmt->fetch(PDO::FETCH_ASSOC);
echo "2. tbl_request_header.status: " . ($col_status ? "OK (" . $col_status['Type'] . ")" : "FAIL") . "\n";

// 2. Cek Kolom tbl_request_detail
$stmt = $pdo->query("SHOW COLUMNS FROM tbl_request_detail LIKE 'jumlah_terpenuhi'");
$col_terpenuhi = $stmt->fetch(PDO::FETCH_ASSOC);
echo "3. tbl_request_detail.jumlah_terpenuhi: " . ($col_terpenuhi ? "OK (" . $col_terpenuhi['Type'] . ")" : "FAIL") . "\n";

// 3. Cek CHECK constraint pada tbl_stok_inventori
$stmt = $pdo->query("SELECT CONSTRAINT_NAME, CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'chk_stok_nonneg'");
$chk = $stmt->fetch(PDO::FETCH_ASSOC);
echo "4. CHECK Constraint chk_stok_nonneg: " . ($chk ? "OK (" . $chk['CHECK_CLAUSE'] . ")" : "FAIL") . "\n";

// 4. Cek Metrik Dashboard
$q_stok = $pdo->query("SELECT SUM(stok_akhir) as total_stok FROM tbl_stok_inventori")->fetch(PDO::FETCH_ASSOC);
$q_resep = $pdo->query("SELECT COUNT(*) as total_resep FROM tbl_resep_header")->fetch(PDO::FETCH_ASSOC);
$q_req = $pdo->query("SELECT COUNT(*) as total_req FROM tbl_request_header WHERE status = 'Pending'")->fetch(PDO::FETCH_ASSOC);
$q_obat = $pdo->query("SELECT COUNT(*) as total_obat FROM tbl_obat")->fetch(PDO::FETCH_ASSOC);

echo "5. Dashboard Metrics:\n";
echo "   - Total Stok Fisik: " . ($q_stok['total_stok'] ?? 0) . "\n";
echo "   - Total Resep: " . ($q_resep['total_resep'] ?? 0) . "\n";
echo "   - Total Request Pending: " . ($q_req['total_req'] ?? 0) . "\n";
echo "   - Total Master Obat: " . ($q_obat['total_obat'] ?? 0) . "\n";

if ($col_tipe && strpos($col_status['Type'], 'Partial') !== false && $col_terpenuhi && $chk) {
    echo "RESULT: TIKET P0-01 PASS\n";
} else {
    echo "RESULT: TIKET P0-01 FAIL\n";
}
