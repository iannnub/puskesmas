<?php

require_once '../config.php';


require_once '../templates/auth_check.php';


if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) {
    header("Location: " . BASE_URL . "stok/terima.php?status=gagal_akses");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST['action'] == 'create') {

    
    $pdo->beginTransaction();

    try {
       
        $id_obat = (int)$_POST['id_obat'];
        $id_unit = (int)$_POST['id_unit'];
        $jumlah_masuk = (int)$_POST['jumlah_masuk'];
        $tgl_masuk = htmlspecialchars($_POST['tgl_masuk']);
        $keterangan = htmlspecialchars($_POST['keterangan']);
        $id_user_pencatat = (int)$_SESSION['user_id'];

        if ($jumlah_masuk <= 0 || empty($id_obat) || empty($id_unit) || empty($tgl_masuk)) {
            throw new Exception("Data input tidak valid. Jumlah harus lebih dari 0.");
        }

        
        $sql_transaksi = "INSERT INTO tbl_transaksi_masuk 
                            (tgl_masuk, id_obat, id_unit, jumlah_masuk, id_user_pencatat, keterangan) 
                          VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_transaksi = $pdo->prepare($sql_transaksi);
        $stmt_transaksi->execute([$tgl_masuk, $id_obat, $id_unit, $jumlah_masuk, $id_user_pencatat, $keterangan]);
        
       
        $id_referensi_transaksi = $pdo->lastInsertId();

        
        $sql_cek_stok = "SELECT id_stok, stok_akhir 
                         FROM tbl_stok_inventori 
                         WHERE id_obat = ? AND id_unit = ?";
        $stmt_cek_stok = $pdo->prepare($sql_cek_stok);
        $stmt_cek_stok->execute([$id_obat, $id_unit]);
        $stok_saat_ini = $stmt_cek_stok->fetch();

        $stok_sebelum = 0;
        
        if ($stok_saat_ini) {
            
            $stok_sebelum = $stok_saat_ini['stok_akhir'];
            $stok_sesudah = $stok_sebelum + $jumlah_masuk;
            
            $sql_update_stok = "UPDATE tbl_stok_inventori 
                                SET stok_akhir = ?, updated_at = NOW() 
                                WHERE id_stok = ?";
            $stmt_update_stok = $pdo->prepare($sql_update_stok);
            $stmt_update_stok->execute([$stok_sesudah, $stok_saat_ini['id_stok']]);
            
        } else {
            
            $stok_sesudah = $jumlah_masuk;
            
            
            $stok_minimum_default = 10; 
            
            $sql_insert_stok = "INSERT INTO tbl_stok_inventori 
                                  (id_obat, id_unit, stok_akhir, stok_minimum) 
                                VALUES (?, ?, ?, ?)";
            $stmt_insert_stok = $pdo->prepare($sql_insert_stok);
            $stmt_insert_stok->execute([$id_obat, $id_unit, $stok_sesudah, $stok_minimum_default]);
        }

        
        $sql_log = "INSERT INTO tbl_log_stok 
                      (tgl_log, id_obat, id_unit, sumber_data, stok_sebelum, masuk, keluar, stok_sesudah, keterangan, id_referensi_transaksi) 
                    VALUES (?, ?, ?, 'Penerimaan', ?, ?, 0, ?, ?, ?)";
        
        $stmt_log = $pdo->prepare($sql_log);
        $stmt_log->execute([
            $tgl_masuk, 
            $id_obat, 
            $id_unit, 
            $stok_sebelum, 
            $jumlah_masuk, 
            $stok_sesudah, 
            $keterangan, 
            $id_referensi_transaksi
        ]);

      
        $pdo->commit();
       
        header("Location: " . BASE_URL . "stok/terima.php?status=tambah_sukses");
        exit;

    } catch (Exception $e) {

        $pdo->rollBack();
        

        $error_message = urlencode($e->getMessage());
        header("Location: " . BASE_URL . "stok/terima.php?status=gagal&msg=" . $error_message);
        exit;
    }

} else {

    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}
?>