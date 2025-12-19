<?php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. Panggil "satpam" auth_check.php
require_once '../templates/auth_check.php';

// 3. (SATPAM 2: ROLE CHECK)
// Hanya SUPER ADMIN (Role ID 1) dan ADMIN (Role ID 2) yang boleh
if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) {
    header("Location: " . BASE_URL . "stok/opname.php?status=gagal_akses");
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
        $id_unit = (int)$_POST['id_unit'];
        $jumlah_fisik = (int)$_POST['jumlah_fisik']; // Ini adalah STOK BARU
        $keterangan = htmlspecialchars($_POST['keterangan']);
        $id_user_pencatat = (int)$_SESSION['user_id'];
        $tgl_opname = date('Y-m-d H:i:s');

        if ($jumlah_fisik < 0 || empty($id_obat) || empty($id_unit) || empty($keterangan)) {
            throw new Exception("Data input tidak valid. Obat, Unit, Jumlah Fisik, dan Keterangan wajib diisi.");
        }

        // ========================================================
        // LANGKAH 1: KUNCI, AMBIL STOK LAMA, & HITUNG SELISIH
        // ========================================================
        
        // Kunci baris stok untuk mencegah "race condition"
        $sql_cek_stok = "SELECT id_stok, stok_akhir 
                         FROM tbl_stok_inventori 
                         WHERE id_obat = ? AND id_unit = ? FOR UPDATE";
        $stmt_cek_stok = $pdo->prepare($sql_cek_stok);
        $stmt_cek_stok->execute([$id_obat, $id_unit]);
        $stok_saat_ini = $stmt_cek_stok->fetch();

        $stok_sebelum = 0;
        $id_stok_inventori = null;

        if ($stok_saat_ini) {
            $stok_sebelum = $stok_saat_ini['stok_akhir'];
            $id_stok_inventori = $stok_saat_ini['id_stok'];
        }
        
        // Ini adalah STOK BARU (hasil hitung fisik)
        $stok_sesudah = $jumlah_fisik;
        
        // Hitung selisihnya (bisa positif/negatif)
        $selisih = $stok_sesudah - $stok_sebelum;
        $masuk = 0;
        $keluar = 0;

        if ($selisih > 0) {
            $masuk = $selisih;
        } else {
            $keluar = abs($selisih);
        }

        // ========================================================
        // LANGKAH 2: UPDATE (ATAU INSERT) "SALDO" DI STOK INVENTORI
        // ========================================================
        
        if ($id_stok_inventori) {
            // JIKA ADA (UPDATE)
            $sql_update_stok = "UPDATE tbl_stok_inventori 
                                SET stok_akhir = ?, updated_at = NOW() 
                                WHERE id_stok = ?";
            $pdo->prepare($sql_update_stok)->execute([$stok_sesudah, $id_stok_inventori]);
            
        } else {
            // JIKA TIDAK ADA (INSERT BARU)
            $stok_minimum_default = 10; 
            
            $sql_insert_stok = "INSERT INTO tbl_stok_inventori 
                                  (id_obat, id_unit, stok_akhir, stok_minimum) 
                                VALUES (?, ?, ?, ?)";
            $pdo->prepare($sql_insert_stok)->execute([$id_obat, $id_unit, $stok_sesudah, $stok_minimum_default]);
        }

        // ===========================================
        // LANGKAH 3: INSERT KE "MUTASI REKENING" (LOG)
        // ===========================================
        $sql_log = "INSERT INTO tbl_log_stok 
                      (tgl_log, id_obat, id_unit, sumber_data, stok_sebelum, masuk, keluar, stok_sesudah, keterangan, id_referensi_transaksi) 
                    VALUES (?, ?, ?, 'Stok Opname', ?, ?, ?, ?, ?, ?)";
        
        $stmt_log = $pdo->prepare($sql_log);
        $stmt_log->execute([
            $tgl_opname, 
            $id_obat, 
            $id_unit, 
            $stok_sebelum, 
            $masuk, 
            $keluar, 
            $stok_sesudah, 
            $keterangan, 
            $id_user_pencatat // Untuk Opname, kita simpan ID user di referensi
        ]);
        
        // Ambil ID log baru untuk highlight
        $new_id_log = $pdo->lastInsertId();

        // ===========================================
        // LANGKAH 4: SEMUA SUKSES, COMMIT TRANSAKSI
        // ===========================================
        $pdo->commit();
        
        // Redirect dengan pesan sukses
        header("Location: " . BASE_URL . "stok/opname.php?status=tambah_sukses&new_id=" . $new_id_log);
        exit;

    } catch (Exception $e) {
        // ========================
        // JIKA ADA ERROR, ROLLBACK!
        // ========================
        $pdo->rollBack();
        
        // Redirect dengan pesan error
        $error_message = urlencode($e->getMessage());
        header("Location: " . BASE_URL . "stok/opname.php?status=gagal&msg=" . $error_message);
        exit;
    }

} else {
    // Jika diakses selain POST, tendang
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}
?>