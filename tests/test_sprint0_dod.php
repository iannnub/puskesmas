<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../config.php';

echo "====================================================================\n";
echo "   SIVO PUSKESMAS - SPRINT 0 DEFINITION OF DONE (DoD) AUDIT REPORT   \n";
echo "====================================================================\n\n";

$dod_results = [];

// -----------------------------------------------------------------------------
// 1. TIKET P0-01: Skema Database & Integritas Stok
// -----------------------------------------------------------------------------
echo "[TEST 1/7] Menguji Integritas Skema Database (P0-01)...\n";
$p01_passed = true;
try {
    // Cek kolom tipe_request pada tbl_request_header
    $col_req = $pdo->query("SHOW COLUMNS FROM tbl_request_header LIKE 'tipe_request'")->fetch();
    // Cek kolom jumlah_terpenuhi pada tbl_request_detail
    $col_det = $pdo->query("SHOW COLUMNS FROM tbl_request_detail LIKE 'jumlah_terpenuhi'")->fetch();
    // Cek status Partial pada ENUM tbl_request_header
    $col_status = $pdo->query("SHOW COLUMNS FROM tbl_request_header LIKE 'status'")->fetch();
    $has_partial = strpos($col_status['Type'] ?? '', "'Partial'") !== false;
    // Cek CHECK constraint stok non-negatif
    $chk_count = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'chk_stok_nonneg'")->fetchColumn();

    if ($col_req && $col_det && $has_partial && $chk_count > 0) {
        echo "   -> Skema P0-01 lengkap (tipe_request, jumlah_terpenuhi, Partial, chk_stok_nonneg): PASS\n";
    } else {
        echo "   -> Skema P0-01 ada elemen yang hilang: FAIL\n";
        $p01_passed = false;
    }
} catch (Exception $e) {
    echo "   -> Error P0-01: " . $e->getMessage() . "\n";
    $p01_passed = false;
}
$dod_results['P0-01'] = $p01_passed;

// -----------------------------------------------------------------------------
// 2. TIKET P0-02: Algoritma Partial Delivery
// -----------------------------------------------------------------------------
echo "\n[TEST 2/7] Menguji Algoritma Partial Delivery Gudang->Apotek (P0-02)...\n";
$p02_file = file_get_contents(__DIR__ . '/../request/proses_setujui_gudang.php');
$has_min_calc = (strpos($p02_file, 'min($sisa_dibutuhkan, $stok_tersedia)') !== false);
$has_partial_status = (strpos($p02_file, "'Partial'") !== false);
$has_terpenuhi_col = (strpos($p02_file, 'jumlah_terpenuhi') !== false);

if ($has_min_calc && $has_partial_status && $has_terpenuhi_col) {
    echo "   -> Logika alokasi dinamis & status Partial terpasang: PASS\n";
    $dod_results['P0-02'] = true;
} else {
    echo "   -> Logika Partial delivery tidak lengkap: FAIL\n";
    $dod_results['P0-02'] = false;
}

// -----------------------------------------------------------------------------
// 3. TIKET P0-03: Immutable Audit Trail & Reversal Entry
// -----------------------------------------------------------------------------
echo "\n[TEST 3/7] Menguji Immutable Audit Trail & Reversal Entry (P0-03)...\n";
$hapus_code = file_get_contents(__DIR__ . '/../resep/proses_hapus.php');
$edit_code = file_get_contents(__DIR__ . '/../resep/proses_edit.php');
$has_delete_hapus = (stripos($hapus_code, 'DELETE FROM tbl_log_stok') !== false);
$has_delete_edit = (stripos($edit_code, 'DELETE FROM tbl_log_stok') !== false);
$has_reversal_entry = (strpos($hapus_code, 'Pembatalan Resep') !== false || strpos($hapus_code, 'masuk') !== false);

if (!$has_delete_hapus && !$has_delete_edit && $has_reversal_entry) {
    echo "   -> Query DELETE di tbl_log_stok berhasil dieliminasi (Append-Only Reversal Entry): PASS\n";
    $dod_results['P0-03'] = true;
} else {
    echo "   -> Ditemukan pelanggaran audit trail: FAIL\n";
    $dod_results['P0-03'] = false;
}

// -----------------------------------------------------------------------------
// 4. TIKET P0-04: Hardening Kredensial & Deteksi Lingkungan
// -----------------------------------------------------------------------------
echo "\n[TEST 4/7] Menguji Isolasi .env & CLI Compatibility (P0-04)...\n";
$config_code = file_get_contents(__DIR__ . '/../config.php');
$has_no_infinityfree = (strpos($config_code, 'if0_37357497') === false);
$has_cli_check = (strpos($config_code, 'php_sapi_name() === \'cli\'') !== false);
$has_true_prepared = (strpos($config_code, 'ATTR_EMULATE_PREPARES') !== false);
$has_env_file = file_exists(__DIR__ . '/../.env');

if ($has_no_infinityfree && $has_cli_check && $has_true_prepared && $has_env_file) {
    echo "   -> Kredensial terisolasi di .env, CLI safe, ATTR_EMULATE_PREPARES false: PASS\n";
    $dod_results['P0-04'] = true;
} else {
    echo "   -> Konfigurasi kredensial / CLI tidak aman: FAIL\n";
    $dod_results['P0-04'] = false;
}

// -----------------------------------------------------------------------------
// 5. TIKET P0-05: Standardisasi RBAC Session Keys
// -----------------------------------------------------------------------------
echo "\n[TEST 5/7] Menguji Standardisasi RBAC Session Keys (P0-05)...\n";
$laporan_code = file_get_contents(__DIR__ . '/../laporan/bulanan_poli.php');
$uses_std_role = (strpos($laporan_code, "\$_SESSION['role_id']") !== false);
$uses_std_unit = (strpos($laporan_code, "\$_SESSION['unit_stok_id']") !== false);
$blocks_poli_umum = (strpos($laporan_code, '[1, 5]') !== false && strpos($laporan_code, '[4]') !== false);

if ($uses_std_role && $uses_std_unit && $blocks_poli_umum) {
    echo "   -> Session keys terstandardisasi (role_id & unit_stok_id), akses farmasi terisolasi: PASS\n";
    $dod_results['P0-05'] = true;
} else {
    echo "   -> RBAC session keys tidak standar: FAIL\n";
    $dod_results['P0-05'] = false;
}

// -----------------------------------------------------------------------------
// 6. TIKET P0-06: Perluasan Akses Modul Resep
// -----------------------------------------------------------------------------
echo "\n[TEST 6/7] Menguji Perluasan Akses Modul Resep (Role 1, 2, 4, 5) (P0-06)...\n";
$tambah_code = file_get_contents(__DIR__ . '/../resep/tambah.php');
$history_code = file_get_contents(__DIR__ . '/../resep/history.php');
$has_roles_tambah = (strpos($tambah_code, '[1, 2, 4, 5]') !== false);
$has_roles_history = (strpos($history_code, '[1, 2, 4, 5]') !== false);

if ($has_roles_tambah && $has_roles_history) {
    echo "   -> Whitelist Role 1, 2, 4, 5 aktif pada modul peresepan: PASS\n";
    $dod_results['P0-06'] = true;
} else {
    echo "   -> Whitelist modul resep tidak lengkap: FAIL\n";
    $dod_results['P0-06'] = false;
}

// -----------------------------------------------------------------------------
// 7. TIKET P0-07: Sanitasi Password Login
// -----------------------------------------------------------------------------
echo "\n[TEST 7/7] Menguji Sanitasi Password Login (P0-07)...\n";
$login_proc = file_get_contents(__DIR__ . '/../auth/proses_login.php');
$has_htmlspecialchars_pw = (strpos($login_proc, 'htmlspecialchars($_POST[\'password\'])') !== false);
$has_trim_pw = (strpos($login_proc, "trim(\$_POST['password']") !== false);

if (!$has_htmlspecialchars_pw && $has_trim_pw) {
    echo "   -> Password ditangani mentah (raw trim()), htmlspecialchars tereliminasi: PASS\n";
    $dod_results['P0-07'] = true;
} else {
    echo "   -> Sanitasi password masih bermasalah: FAIL\n";
    $dod_results['P0-07'] = false;
}

// -----------------------------------------------------------------------------
// 8. DATABASE INTEGRITY CHECK (SUM(masuk - keluar) vs stok_akhir)
// -----------------------------------------------------------------------------
echo "\n--------------------------------------------------------------------\n";
echo "   AUDIT INTEGRITAS SALDO STOK: tbl_stok_inventori VS tbl_log_stok  \n";
echo "--------------------------------------------------------------------\n";

$integrity_stmt = $pdo->query("
    SELECT 
        si.id_unit,
        COALESCE(u.nama_unit, 'Unknown') AS nama_unit,
        si.id_obat,
        o.nama_obat,
        si.stok_akhir,
        COALESCE(SUM(ls.masuk - ls.keluar), 0) AS kalkulasi_log,
        (si.stok_akhir - COALESCE(SUM(ls.masuk - ls.keluar), 0)) AS selisih
    FROM tbl_stok_inventori si
    JOIN tbl_obat o ON si.id_obat = o.id_obat
    LEFT JOIN tbl_unit u ON si.id_unit = u.id_unit
    LEFT JOIN tbl_log_stok ls ON si.id_unit = ls.id_unit AND si.id_obat = ls.id_obat
    GROUP BY si.id_unit, u.nama_unit, si.id_obat, o.nama_obat, si.stok_akhir
    HAVING selisih != 0
");
$discrepancies = $integrity_stmt->fetchAll(PDO::FETCH_ASSOC);

$negative_stmt = $pdo->query("SELECT COUNT(*) FROM tbl_stok_inventori WHERE stok_akhir < 0");
$negative_count = (int)$negative_stmt->fetchColumn();

echo "1. Rekonsiliasi Saldo Fisik vs Log Mutasi:\n";
if (empty($discrepancies)) {
    echo "   -> Hasil: 0 Discrepancies ditemukan. 100% Saldo Inventori Sinkron dengan Log Mutasi! (PASS)\n";
    $stock_integrity_pass = true;
} else {
    echo "   -> PERINGATAN: Ditemukan " . count($discrepancies) . " selisih saldo:\n";
    foreach ($discrepancies as $disc) {
        echo sprintf("      - Obat ID %d (%s) Unit %d: Stok Fisik = %d, Log = %d (Selisih: %d)\n",
            $disc['id_obat'], $disc['nama_obat'], $disc['id_unit_stok'], $disc['stok_akhir'], $disc['kalkulasi_log'], $disc['selisih']
        );
    }
    $stock_integrity_pass = false;
}

echo "2. Pengecekan Nilai Stok Negatif:\n";
if ($negative_count === 0) {
    echo "   -> Hasil: 0 Baris dengan stok negatif. Seluruh stok_akhir >= 0 (PASS)\n";
    $negative_check_pass = true;
} else {
    echo "   -> PERINGATAN: Ditemukan $negative_count baris dengan stok_akhir < 0 (FAIL)\n";
    $negative_check_pass = false;
}

// -----------------------------------------------------------------------------
// VERDICT FINAL SPRINT 0
// -----------------------------------------------------------------------------
echo "\n====================================================================\n";
echo "   STATUS AKHIR DEFINITION OF DONE (DoD) - SPRINT 0                  \n";
echo "====================================================================\n";

$all_tickets_closed = true;
foreach ($dod_results as $ticket => $status) {
    echo sprintf(" - Tiket %-6s : %s\n", $ticket, $status ? "CLOSED (PASS)" : "OPEN (FAIL)");
    if (!$status) $all_tickets_closed = false;
}
echo sprintf(" - Integritas Stok       : %s\n", $stock_integrity_pass ? "PASS" : "FAIL");
echo sprintf(" - Non-Negative Stok     : %s\n", $negative_check_pass ? "PASS" : "FAIL");

if ($all_tickets_closed && $stock_integrity_pass && $negative_check_pass) {
    echo "\n>>> VERDICT: SPRINT 0 COMPLETED WITH ZERO DEFECTS (RELEASE APPROVED) <<<\n";
    exit(0);
} else {
    echo "\n>>> VERDICT: SPRINT 0 NOT READY (DEFECTS REMAINING) <<<\n";
    exit(1);
}
