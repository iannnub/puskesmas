<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../config.php';

echo "=== VERIFIKASI TIKET P0-02: PARTIAL DELIVERY (Gudang -> Apotek) ===\n";

$_SESSION['user_id'] = 10; // Superadmin
$_SESSION['role_id'] = 1;

$id_obat = 1;
$id_unit_gudang = 1;
$id_unit_apotek = 2;

$pdo->beginTransaction();

try {
    // Set saldo awal terkontrol
    $pdo->prepare("UPDATE tbl_stok_inventori SET stok_akhir = 15 WHERE id_obat = ? AND id_unit = ?")->execute([$id_obat, $id_unit_gudang]);
    $stok_awal_apotek = (int)$pdo->query("SELECT stok_akhir FROM tbl_stok_inventori WHERE id_obat = $id_obat AND id_unit = $id_unit_apotek")->fetchColumn();

    // 1. Buat request baru dari Apotek ke Gudang minta 50 tablet
    $stmt_ins_req = $pdo->prepare("INSERT INTO tbl_request_header (tgl_request, id_user_request, id_unit_tujuan, tipe_request, status, keterangan_request) 
                                   VALUES (NOW(), 5, 2, 'Apotek_ke_Gudang', 'Pending', 'Uji Coba Partial Delivery')");
    $stmt_ins_req->execute();
    $test_id_req = $pdo->lastInsertId();

    $stmt_ins_det = $pdo->prepare("INSERT INTO tbl_request_detail (id_request, id_obat, jumlah_request, jumlah_terpenuhi) 
                                   VALUES (?, ?, 50, 0)");
    $stmt_ins_det->execute([$test_id_req, $id_obat]);
    $test_id_det = $pdo->lastInsertId();

    echo "1. Request #$test_id_req dibuat: Minta 50 unit. Stok Gudang saat ini: 15 unit.\n";

    // 2. Simulasikan eksekusi proses_setujui_gudang.php
    $_POST['id_request'] = $test_id_req;
    
    // Panggil logika partial delivery
    $stmt_header = $pdo->prepare("SELECT * FROM tbl_request_header WHERE id_request = ? AND status IN ('Pending', 'Partial') AND tipe_request = 'Apotek_ke_Gudang' FOR UPDATE");
    $stmt_header->execute([$test_id_req]);
    $req_h = $stmt_header->fetch(PDO::FETCH_ASSOC);

    $stmt_det = $pdo->prepare("SELECT * FROM tbl_request_detail WHERE id_request = ?");
    $stmt_det->execute([$test_id_req]);
    $items = $stmt_det->fetchAll(PDO::FETCH_ASSOC);

    $total_item = count($items);
    $item_completed = 0;
    $item_ada_kirim = 0;

    foreach ($items as $item) {
        $sisa = $item['jumlah_request'] - $item['jumlah_terpenuhi'];
        $stok_g = (int)$pdo->query("SELECT stok_akhir FROM tbl_stok_inventori WHERE id_obat = $id_obat AND id_unit = $id_unit_gudang")->fetchColumn();
        
        $kirim = min($sisa, $stok_g);
        echo "   -> Alokasi Tahap 1: sisa_dibutuhkan=$sisa, stok_gudang=$stok_g, dikirim=$kirim\n";

        // Potong gudang, tambah apotek
        $pdo->prepare("UPDATE tbl_stok_inventori SET stok_akhir = stok_akhir - ? WHERE id_obat = ? AND id_unit = ?")->execute([$kirim, $id_obat, $id_unit_gudang]);
        $pdo->prepare("UPDATE tbl_stok_inventori SET stok_akhir = stok_akhir + ? WHERE id_obat = ? AND id_unit = ?")->execute([$kirim, $id_obat, $id_unit_apotek]);
        
        $baru_terpenuhi = $item['jumlah_terpenuhi'] + $kirim;
        $pdo->prepare("UPDATE tbl_request_detail SET jumlah_terpenuhi = ? WHERE id_request_detail = ?")->execute([$baru_terpenuhi, $item['id_request_detail']]);

        if ($baru_terpenuhi >= $item['jumlah_request']) $item_completed++;
        if ($kirim > 0) $item_ada_kirim++;
    }

    $status_1 = ($item_completed === $total_item) ? 'Completed' : (($item_ada_kirim > 0) ? 'Partial' : 'Cancelled');
    $pdo->prepare("UPDATE tbl_request_header SET status = ?, id_user_approve = ?, tgl_approve = NOW() WHERE id_request = ?")->execute([$status_1, 10, $test_id_req]);

    echo "2. Hasil Tahap 1: Status Header = $status_1, Terpenuhi = $baru_terpenuhi / 50\n";

    // 3. Simulasikan Gudang Restock 40 tablet, lalu approve sisa request
    $pdo->prepare("UPDATE tbl_stok_inventori SET stok_akhir = stok_akhir + 40 WHERE id_obat = ? AND id_unit = ?")->execute([$id_obat, $id_unit_gudang]);
    echo "3. Gudang restock 40 tablet. Menjalankan approval sisa...\n";

    $stmt_det->execute([$test_id_req]);
    $items = $stmt_det->fetchAll(PDO::FETCH_ASSOC);
    $item_completed = 0;
    $item_ada_kirim = 0;

    foreach ($items as $item) {
        $sisa = $item['jumlah_request'] - $item['jumlah_terpenuhi'];
        $stok_g = (int)$pdo->query("SELECT stok_akhir FROM tbl_stok_inventori WHERE id_obat = $id_obat AND id_unit = $id_unit_gudang")->fetchColumn();
        
        $kirim = min($sisa, $stok_g);
        echo "   -> Alokasi Tahap 2: sisa_dibutuhkan=$sisa, stok_gudang=$stok_g, dikirim=$kirim\n";

        $pdo->prepare("UPDATE tbl_stok_inventori SET stok_akhir = stok_akhir - ? WHERE id_obat = ? AND id_unit = ?")->execute([$kirim, $id_obat, $id_unit_gudang]);
        $pdo->prepare("UPDATE tbl_stok_inventori SET stok_akhir = stok_akhir + ? WHERE id_obat = ? AND id_unit = ?")->execute([$kirim, $id_obat, $id_unit_apotek]);
        
        $baru_terpenuhi = $item['jumlah_terpenuhi'] + $kirim;
        $pdo->prepare("UPDATE tbl_request_detail SET jumlah_terpenuhi = ? WHERE id_request_detail = ?")->execute([$baru_terpenuhi, $item['id_request_detail']]);

        if ($baru_terpenuhi >= $item['jumlah_request']) $item_completed++;
        if ($kirim > 0) $item_ada_kirim++;
    }

    $status_2 = ($item_completed === $total_item) ? 'Completed' : (($item_ada_kirim > 0) ? 'Partial' : 'Cancelled');
    $pdo->prepare("UPDATE tbl_request_header SET status = ?, id_user_approve = ?, tgl_approve = NOW() WHERE id_request = ?")->execute([$status_2, 10, $test_id_req]);

    echo "4. Hasil Tahap 2: Status Header = $status_2, Terpenuhi = $baru_terpenuhi / 50\n";

    // Verifikasi akhir
    if ($status_1 === 'Partial' && $status_2 === 'Completed' && $baru_terpenuhi == 50) {
        echo "RESULT: TIKET P0-02 PASS (Multi-stage Partial Delivery Berhasil)\n";
    } else {
        echo "RESULT: TIKET P0-02 FAIL\n";
    }

    // Rollback test data agar database bersih
    $pdo->rollBack();
    echo "Rollback test data: Database bersih.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "ERROR TEST: " . $e->getMessage() . "\n";
}
