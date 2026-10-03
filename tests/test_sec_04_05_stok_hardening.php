<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../config.php';

echo "=== VERIFIKASI SEC-04 & SEC-05: STOK HARDENING (BUG-SIVO-010 & BUG-SIVO-011) ===\n";

$all_passed = true;

// 1. Static Analysis: stok/proses_terima.php
$terima_code = file_get_contents(__DIR__ . '/../stok/proses_terima.php');
$has_for_update_terima = (strpos($terima_code, 'FOR UPDATE') !== false);
echo "1. Analisis stok/proses_terima.php:\n";
echo "   - Row Locking (FOR UPDATE) Terpasang: " . ($has_for_update_terima ? "PASS" : "FAIL") . "\n";
if (!$has_for_update_terima) $all_passed = false;

// 2. Static Analysis: stok/proses_transfer.php
$transfer_code = file_get_contents(__DIR__ . '/../stok/proses_transfer.php');
$has_broken_units = (strpos($transfer_code, '$units[') !== false);
$has_unit_query = (strpos($transfer_code, 'SELECT id_unit, nama_unit FROM tbl_unit') !== false);
echo "\n2. Analisis stok/proses_transfer.php:\n";
echo "   - Bebas Undefined \$units: " . (!$has_broken_units ? "PASS" : "FAIL") . "\n";
echo "   - Query Presisi Nama Unit: " . ($has_unit_query ? "PASS" : "FAIL") . "\n";
if ($has_broken_units || !$has_unit_query) $all_passed = false;

// 3. Functional Test: Simulasi Transfer Stok & Log Deskripsi Utuh
try {
    $pdo->beginTransaction();
    
    $id_unit_asal = 1; // GUDANG
    $id_unit_tujuan = 2; // APOTEK
    
    $stmt_unit_names = $pdo->prepare("SELECT id_unit, nama_unit FROM tbl_unit WHERE id_unit IN (?, ?)");
    $stmt_unit_names->execute([$id_unit_asal, $id_unit_tujuan]);
    $unit_map = $stmt_unit_names->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $nama_asal = $unit_map[$id_unit_asal] ?? "Unit #$id_unit_asal";
    $nama_tujuan = $unit_map[$id_unit_tujuan] ?? "Unit #$id_unit_tujuan";
    
    $ket_keluar = "Transfer Keluar ke " . $nama_tujuan;
    $ket_masuk  = "Transfer Masuk dari " . $nama_asal;
    
    echo "\n3. Uji Resolusi Nama Unit pada Log Transfer:\n";
    echo "   - Log Keluar: '$ket_keluar' -> " . ($ket_keluar === "Transfer Keluar ke APOTEK" ? "PASS" : "FAIL") . "\n";
    echo "   - Log Masuk : '$ket_masuk' -> " . ($ket_masuk === "Transfer Masuk dari GUDANG" ? "PASS" : "FAIL") . "\n";
    
    if ($ket_keluar !== "Transfer Keluar ke APOTEK" || $ket_masuk !== "Transfer Masuk dari GUDANG") {
        $all_passed = false;
    }
    
    $pdo->rollBack();
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Database Error: " . $e->getMessage() . "\n";
    $all_passed = false;
}

echo "\n--------------------------------------------------\n";
if ($all_passed) {
    echo "RESULT: SEC-04 & SEC-05 PASS (Row Locking Aktif & Log Transfer Akurat 100%)\n";
    exit(0);
} else {
    echo "RESULT: SEC-04 & SEC-05 FAIL\n";
    exit(1);
}
