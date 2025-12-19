<?php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. Panggil "satpam" auth_check.php
require_once '../templates/auth_check.php';

// 3. (SATPAM 2: ROLE CHECK)
// Hanya SUPER ADMIN (Role ID 1) dan ADMIN (Role ID 2) yang boleh
if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) {
    header("Location: " . BASE_URL . "request/kelola.php?status=gagal_akses");
    exit;
}

// 4. Pastikan request adalah POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_request'])) {

    // ===========================================
    // MULAI TRANSAKSI DATABASE (PENTING!)
    // ===========================================
    $pdo->beginTransaction();

    try {
        // 5. Ambil Data Request
        $id_request = (int)$_POST['id_request'];
        $id_user_approve = (int)$_SESSION['user_id'];
        $tgl_approve = date('Y-m-d H:i:s');
        
        // Asumsi: Stok SELALU diambil dari GUDANG (ID Unit = 1)
        $id_unit_asal = 1; 

        // 6. Ambil detail request (Header & Detail)
        $stmt_header = $pdo->prepare("SELECT * FROM tbl_request_header WHERE id_request = ? AND status = 'Pending' FOR UPDATE");
        $stmt_header->execute([$id_request]);
        $request_header = $stmt_header->fetch();
        
        if (!$request_header) {
            throw new Exception("Request tidak ditemukan atau sudah diproses.");
        }
        $id_unit_tujuan = $request_header['id_unit_tujuan'];
        
        $stmt_detail = $pdo->prepare("SELECT * FROM tbl_request_detail WHERE id_request = ?");
        $stmt_detail->execute([$id_request]);
        $request_details = $stmt_detail->fetchAll();
        
        if (empty($request_details)) {
             throw new Exception("Request ini tidak memiliki item obat.");
        }
        
        // Siapkan query yang akan dipakai berulang-ulang di loop
        $stmt_cek_stok = $pdo->prepare("SELECT id_stok, stok_akhir FROM tbl_stok_inventori WHERE id_obat = ? AND id_unit = ? FOR UPDATE");
        $stmt_update_stok = $pdo->prepare("UPDATE tbl_stok_inventori SET stok_akhir = ? WHERE id_stok = ?");
        $stmt_insert_stok = $pdo->prepare("INSERT INTO tbl_stok_inventori (id_obat, id_unit, stok_akhir, stok_minimum) VALUES (?, ?, ?, 10)");
        $stmt_transfer = $pdo->prepare("INSERT INTO tbl_transaksi_transfer (id_request, tgl_transfer, id_obat, id_unit_asal, id_unit_tujuan, jumlah, id_user_pencatat) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_log = $pdo->prepare("INSERT INTO tbl_log_stok (tgl_log, id_obat, id_unit, sumber_data, stok_sebelum, masuk, keluar, stok_sesudah, keterangan, id_referensi_transaksi) VALUES (?, ?, ?, 'Transfer', ?, ?, ?, ?, ?, ?)");

        // ========================================================
        // LANGKAH 7: LOOPING SETIAP OBAT YANG DIMINTA
        // ========================================================
        foreach ($request_details as $item) {
            $id_obat = $item['id_obat'];
            $jumlah = $item['jumlah_request'];
            
            // --- 7a. Kurangi Stok GUDANG (Unit Asal) ---
            $stmt_cek_stok->execute([$id_obat, $id_unit_asal]);
            $stok_asal = $stmt_cek_stok->fetch();
            
            if (!$stok_asal || $stok_asal['stok_akhir'] < $jumlah) {
                throw new Exception("Stok obat (ID: $id_obat) tidak mencukupi di GUDANG. Sisa stok: " . ($stok_asal['stok_akhir'] ?? 0));
            }
            
            $stok_sebelum_asal = $stok_asal['stok_akhir'];
            $stok_sesudah_asal = $stok_sebelum_asal - $jumlah;
            $stmt_update_stok->execute([$stok_sesudah_asal, $stok_asal['id_stok']]);
            
            // --- 7b. Tambah Stok UGD/RAWAT INAP (Unit Tujuan) ---
            $stmt_cek_stok->execute([$id_obat, $id_unit_tujuan]);
            $stok_tujuan = $stmt_cek_stok->fetch();
            
            $stok_sebelum_tujuan = 0;
            if ($stok_tujuan) {
                $stok_sebelum_tujuan = $stok_tujuan['stok_akhir'];
                $stok_sesudah_tujuan = $stok_sebelum_tujuan + $jumlah;
                $stmt_update_stok->execute([$stok_sesudah_tujuan, $stok_tujuan['id_stok']]);
            } else {
                $stok_sesudah_tujuan = $jumlah;
                $stmt_insert_stok->execute([$id_obat, $id_unit_tujuan, $stok_sesudah_tujuan]);
            }

            // --- 7c. Catat di Jurnal Transfer ---
            $stmt_transfer->execute([$id_request, $tgl_approve, $id_obat, $id_unit_asal, $id_unit_tujuan, $jumlah, $id_user_approve]);
            $id_ref = $pdo->lastInsertId(); // ID transfer

            // --- 7d. Catat di Log (Mutasi) 2x ---
            // Log Keluar GUDANG
            $stmt_log->execute([$tgl_approve, $id_obat, $id_unit_asal, $stok_sebelum_asal, 0, $jumlah, $stok_sesudah_asal, "Request #$id_request", $id_ref]);
            // Log Masuk UGD
            $stmt_log->execute([$tgl_approve, $id_obat, $id_unit_tujuan, $stok_sebelum_tujuan, $jumlah, 0, $stok_sesudah_tujuan, "Request #$id_request", $id_ref]);
        }
        
        // ========================================================
        // LANGKAH 8: UPDATE STATUS REQUEST (JIKA SEMUA OBAT SUKSES)
        // ========================================================
        $sql_update_header = "UPDATE tbl_request_header 
                              SET status = 'Completed', id_user_approve = ?, tgl_approve = ? 
                              WHERE id_request = ?";
        $pdo->prepare($sql_update_header)->execute([$id_user_approve, $tgl_approve, $id_request]);

        // ===========================================
        // LANGKAH 9: SEMUA SUKSES, COMMIT TRANSAKSI
        // ===========================================
        $pdo->commit();
        
        // Redirect dengan pesan sukses
        header("Location: " . BASE_URL . "request/kelola.php?status=sukses_approve");
        exit;

    } catch (Exception $e) {
        // ========================
        // JIKA ADA ERROR, ROLLBACK!
        // ========================
        $pdo->rollBack();
        
        // Redirect dengan pesan error
        $error_message = urlencode($e->getMessage());
        header("Location: " . BASE_URL . "request/kelola.php?status=gagal&msg=" . $error_message);
        exit;
    }

} else {
    // Jika diakses selain POST, tendang
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}
?>