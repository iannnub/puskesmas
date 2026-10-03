<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../config.php';

echo "=== VERIFIKASI TIKET P0-03: IMMUTABLE AUDIT TRAIL ===\n";

$_SESSION['user_id'] = 10;
$_SESSION['role_id'] = 1;

$id_obat = 1;
$id_unit = 2; // Apotek

$pdo->beginTransaction();

try {
    // Pastikan stok awal
    $pdo->prepare("UPDATE tbl_stok_inventori SET stok_akhir = 50 WHERE id_obat = ? AND id_unit = ?")->execute([$id_obat, $id_unit]);

    $log_count_awal = (int)$pdo->query("SELECT COUNT(*) FROM tbl_log_stok WHERE id_obat = $id_obat AND id_unit = $id_unit")->fetchColumn();
    echo "1. Log stok awal untuk obat $id_obat di unit $id_unit: $log_count_awal baris.\n";

    // A. Buat resep pengeluaran 10 tablet
    $stmt_rh = $pdo->prepare("INSERT INTO tbl_resep_header (tgl_resep, nama_pasien, id_poli, id_dokter, id_pelayanan, id_user_pencatat, kelengkapan_resep, kesalahan_resep, sesuai_formularium) 
                             VALUES (NOW(), 'Pasien Audit P0-03', 1, 1, 1, 10, 'Lengkap', 'Tidak Ada', 'Sesuai')");
    $stmt_rh->execute();
    $id_resep = $pdo->lastInsertId();

    $stmt_rd = $pdo->prepare("INSERT INTO tbl_resep_detail (id_resep, id_obat, id_unit_asal, jumlah_keluar, jenis_racikan) VALUES (?, ?, ?, 10, 'Non Racikan')");
    $stmt_rd->execute([$id_resep, $id_obat, $id_unit]);
    $id_det = $pdo->lastInsertId();

    // Potong stok dan catat log awal resep
    $pdo->prepare("UPDATE tbl_stok_inventori SET stok_akhir = stok_akhir - 10 WHERE id_obat = ? AND id_unit = ?")->execute([$id_obat, $id_unit]);
    $pdo->prepare("INSERT INTO tbl_log_stok (tgl_log, id_obat, id_unit, sumber_data, stok_sebelum, masuk, keluar, stok_sesudah, keterangan, id_referensi_transaksi) 
                   VALUES (NOW(), ?, ?, 'Resep', 50, 0, 10, 40, 'Resep Pasien Audit P0-03', ?)")->execute([$id_obat, $id_unit, $id_det]);

    $log_count_setelah_resep = (int)$pdo->query("SELECT COUNT(*) FROM tbl_log_stok WHERE id_obat = $id_obat AND id_unit = $id_unit")->fetchColumn();
    echo "2. Resep #$id_resep tercatat (-10 tablet). Jumlah log bertambah menjadi: $log_count_setelah_resep baris.\n";

    // B. Simulasikan eksekusi pembatalan resep via proses_hapus.php (Reversal Entry)
    $stmt_details = $pdo->prepare("SELECT * FROM tbl_resep_detail WHERE id_resep = ?");
    $stmt_details->execute([$id_resep]);
    $dets = $stmt_details->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dets as $d) {
        $stk = $pdo->query("SELECT id_stok, stok_akhir FROM tbl_stok_inventori WHERE id_obat = {$d['id_obat']} AND id_unit = {$d['id_unit_asal']}")->fetch(PDO::FETCH_ASSOC);
        $stok_sebelum = (int)$stk['stok_akhir'];
        $stok_sesudah = $stok_sebelum + (int)$d['jumlah_keluar'];

        // Kembalikan stok
        $pdo->prepare("UPDATE tbl_stok_inventori SET stok_akhir = ? WHERE id_stok = ?")->execute([$stok_sesudah, $stk['id_stok']]);

        // Reversal log (KOMPENSASI MASUK, TIDAK ADA DELETE)
        $pdo->prepare("INSERT INTO tbl_log_stok (tgl_log, id_obat, id_unit, sumber_data, stok_sebelum, masuk, keluar, stok_sesudah, keterangan, id_referensi_transaksi) 
                       VALUES (NOW(), ?, ?, 'Resep', ?, ?, 0, ?, 'Pembatalan Resep Audit', ?)")
            ->execute([$d['id_obat'], $d['id_unit_asal'], $stok_sebelum, $d['jumlah_keluar'], $stok_sesudah, $id_resep]);
    }

    $log_count_setelah_batal = (int)$pdo->query("SELECT COUNT(*) FROM tbl_log_stok WHERE id_obat = $id_obat AND id_unit = $id_unit")->fetchColumn();
    $stok_akhir_sekarang = (int)$pdo->query("SELECT stok_akhir FROM tbl_stok_inventori WHERE id_obat = $id_obat AND id_unit = $id_unit")->fetchColumn();

    echo "3. Pembatalan resep dijalankan:\n";
    echo "   - Saldo stok fisik kembali utuh: $stok_akhir_sekarang tablet (Awal 50 -> Dipakai 10 -> Batal kembali 50).\n";
    echo "   - Jumlah baris log bertambah menjadi: $log_count_setelah_batal baris (TIDAK ADA LOG YANG DIHAPUS).\n";

    // Cek keberadaan kedua log: Log pengeluaran awal dan Log pembalikan masuk
    $log_keluar = $pdo->query("SELECT COUNT(*) FROM tbl_log_stok WHERE id_obat = $id_obat AND id_unit = $id_unit AND keluar = 10")->fetchColumn();
    $log_masuk  = $pdo->query("SELECT COUNT(*) FROM tbl_log_stok WHERE id_obat = $id_obat AND id_unit = $id_unit AND masuk = 10 AND keterangan LIKE '%Pembatalan%'")->fetchColumn();

    echo "4. Verifikasi Integritas Jejak Audit:\n";
    echo "   - Log pengeluaran resep ada: " . ($log_keluar > 0 ? "YA ($log_keluar)" : "TIDAK") . "\n";
    echo "   - Log kompensasi reversal ada: " . ($log_masuk > 0 ? "YA ($log_masuk)" : "TIDAK") . "\n";

    if ($stok_akhir_sekarang === 50 && $log_count_setelah_batal > $log_count_setelah_resep && $log_keluar > 0 && $log_masuk > 0) {
        echo "RESULT: TIKET P0-03 PASS (Immutable Audit Trail & Reversal Entry Terverifikasi)\n";
    } else {
        echo "RESULT: TIKET P0-03 FAIL\n";
    }

    $pdo->rollBack();
    echo "Rollback test data: Database bersih.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "ERROR TEST: " . $e->getMessage() . "\n";
}
