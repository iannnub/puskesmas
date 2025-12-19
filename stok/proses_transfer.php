<?php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. Panggil "satpam" auth_check.php
require_once '../templates/auth_check.php';

// 3. (SATPAM 2: ROLE CHECK)
// Hanya SUPER ADMIN (Role ID 1) dan ADMIN (Role ID 2) yang boleh
if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) {
    header("Location: " . BASE_URL . "stok/transfer.php?status=gagal_akses");
    exit;
}

// 4. Pastikan request adalah POST dan action=create
if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST['action'] == 'create') {

    // ===========================================
    // MULAI TRANSAKSI DATABASE (PENTING!)
    // ===========================================
    $pdo->beginTransaction();

    try {
        // 5. Ambil & Validasi Data Form
        $id_obat = (int)$_POST['id_obat'];
        $jumlah = (int)$_POST['jumlah'];
        $id_unit_asal = (int)$_POST['id_unit_asal'];
        $id_unit_tujuan = (int)$_POST['id_unit_tujuan'];
        $keterangan = htmlspecialchars($_POST['keterangan']);
        $id_user_pencatat = (int)$_SESSION['user_id'];
        $tgl_transfer = date('Y-m-d H:i:s'); // Waktu saat ini

        if ($jumlah <= 0 || empty($id_obat) || empty($id_unit_asal) || empty($id_unit_tujuan)) {
            throw new Exception("Data input tidak valid. Obat, Jumlah, Unit Asal, dan Unit Tujuan wajib diisi.");
        }
        if ($id_unit_asal == $id_unit_tujuan) {
            throw new Exception("Unit Asal dan Unit Tujuan tidak boleh sama.");
        }

        // ========================================================
        // LANGKAH 1: KURANGI STOK DARI UNIT ASAL
        // ========================================================
        
        // Kunci baris stok asal untuk mencegah "race condition"
        $sql_cek_asal = "SELECT id_stok, stok_akhir 
                         FROM tbl_stok_inventori 
                         WHERE id_obat = ? AND id_unit = ? FOR UPDATE";
        $stmt_cek_asal = $pdo->prepare($sql_cek_asal);
        $stmt_cek_asal->execute([$id_obat, $id_unit_asal]);
        $stok_asal = $stmt_cek_asal->fetch();

        // Cek ketersediaan stok
        if (!$stok_asal || $stok_asal['stok_akhir'] < $jumlah) {
            throw new Exception("Stok di unit asal tidak mencukupi. Sisa stok: " . ($stok_asal['stok_akhir'] ?? 0));
        }
        
        $stok_sebelum_asal = $stok_asal['stok_akhir'];
        $stok_sesudah_asal = $stok_sebelum_asal - $jumlah;

        // Update stok asal
        $sql_update_asal = "UPDATE tbl_stok_inventori SET stok_akhir = ? WHERE id_stok = ?";
        $pdo->prepare($sql_update_asal)->execute([$stok_sesudah_asal, $stok_asal['id_stok']]);

        // ========================================================
        // LANGKAH 2: TAMBAH STOK KE UNIT TUJUAN
        // ========================================================
        
        // Kunci baris stok tujuan
        $sql_cek_tujuan = "SELECT id_stok, stok_akhir, stok_minimum 
                           FROM tbl_stok_inventori 
                           WHERE id_obat = ? AND id_unit = ? FOR UPDATE";
        $stmt_cek_tujuan = $pdo->prepare($sql_cek_tujuan);
        $stmt_cek_tujuan->execute([$id_obat, $id_unit_tujuan]);
        $stok_tujuan = $stmt_cek_tujuan->fetch();

        $stok_sebelum_tujuan = 0;

        if ($stok_tujuan) {
            // JIKA ADA (UPDATE)
            $stok_sebelum_tujuan = $stok_tujuan['stok_akhir'];
            $stok_sesudah_tujuan = $stok_sebelum_tujuan + $jumlah;
            
            $sql_update_tujuan = "UPDATE tbl_stok_inventori SET stok_akhir = ? WHERE id_stok = ?";
            $pdo->prepare($sql_update_tujuan)->execute([$stok_sesudah_tujuan, $stok_tujuan['id_stok']]);
        } else {
            // JIKA TIDAK ADA (INSERT BARU)
            // (stok_sebelum = 0)
            $stok_sesudah_tujuan = $jumlah;
            $stok_minimum_default = 10; // Ambil default 10
            
            $sql_insert_tujuan = "INSERT INTO tbl_stok_inventori (id_obat, id_unit, stok_akhir, stok_minimum) 
                                  VALUES (?, ?, ?, ?)";
            $pdo->prepare($sql_insert_tujuan)->execute([$id_obat, $id_unit_tujuan, $stok_sesudah_tujuan, $stok_minimum_default]);
        }

        // ========================================================
        // LANGKAH 3: CATAT JURNAL TRANSAKSI TRANSFER
        // ========================================================
        $sql_transfer = "INSERT INTO tbl_transaksi_transfer 
                           (tgl_transfer, id_obat, id_unit_asal, id_unit_tujuan, jumlah, id_user_pencatat, keterangan) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_transfer = $pdo->prepare($sql_transfer);
        $stmt_transfer->execute([$tgl_transfer, $id_obat, $id_unit_asal, $id_unit_tujuan, $jumlah, $id_user_pencatat, $keterangan]);
        
        // Ambil ID dari transaksi baru ini untuk referensi log
        $id_referensi_transaksi = $pdo->lastInsertId();

        // ========================================================
        // LANGKAH 4: CATAT "MUTASI REKENING" (2 KALI)
        // ========================================================
        
        // Log 1: Pengurangan dari Unit Asal
        $sql_log_keluar = "INSERT INTO tbl_log_stok 
                             (tgl_log, id_obat, id_unit, sumber_data, stok_sebelum, masuk, keluar, stok_sesudah, keterangan, id_referensi_transaksi) 
                           VALUES (?, ?, ?, 'Transfer', ?, 0, ?, ?, ?, ?)";
        $pdo->prepare($sql_log_keluar)->execute([
            $tgl_transfer, $id_obat, $id_unit_asal, 
            $stok_sebelum_asal, $jumlah, $stok_sesudah_asal, 
            "Transfer Keluar ke " . $units[$id_unit_tujuan-1]['nama_unit'], // -1 asumsi ID = index+1
            $id_referensi_transaksi
        ]);

        // Log 2: Penambahan ke Unit Tujuan
        $sql_log_masuk = "INSERT INTO tbl_log_stok 
                            (tgl_log, id_obat, id_unit, sumber_data, stok_sebelum, masuk, keluar, stok_sesudah, keterangan, id_referensi_transaksi) 
                          VALUES (?, ?, ?, 'Transfer', ?, ?, 0, ?, ?, ?)";
        $pdo->prepare($sql_log_masuk)->execute([
            $tgl_transfer, $id_obat, $id_unit_tujuan, 
            $stok_sebelum_tujuan, $jumlah, $stok_sesudah_tujuan, 
            "Transfer Masuk dari " . $units[$id_unit_asal-1]['nama_unit'], 
            $id_referensi_transaksi
        ]);

        // ===========================================
        // LANGKAH 5: SEMUA SUKSES, COMMIT TRANSAKSI
        // ===========================================
        $pdo->commit();
        
        // Redirect dengan pesan sukses
        header("Location: " . BASE_URL . "stok/transfer.php?status=tambah_sukses&new_id=" . $id_referensi_transaksi);
        exit;

    } catch (Exception $e) {
        // ========================
        // JIKA ADA ERROR, ROLLBACK!
        // ========================
        $pdo->rollBack();
        
        // Redirect dengan pesan error
        $error_message = urlencode($e->getMessage());
        header("Location: " . BASE_URL . "stok/transfer.php?status=gagal&msg=" . $error_message);
        exit;
    }

} else {
    // Jika diakses selain POST, tendang
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}
?>