<?php

require_once '../config.php';


require_once '../templates/auth_check.php';


if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) {
    header("Location: " . BASE_URL . "stok/opname.php?status=gagal_akses");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST['action'] == 'create') {

    
    $pdo->beginTransaction();

    try {
        
        $id_obat = (int)$_POST['id_obat'];
        $id_unit = (int)$_POST['id_unit'];
        $jumlah_fisik = (int)$_POST['jumlah_fisik']; 
        $keterangan = htmlspecialchars($_POST['keterangan']);
        $id_user_pencatat = (int)$_SESSION['user_id'];
        $tgl_opname = date('Y-m-d H:i:s');

        if ($jumlah_fisik < 0 || empty($id_obat) || empty($id_unit) || empty($keterangan)) {
            throw new Exception("Data input tidak valid. Obat, Unit, Jumlah Fisik, dan Keterangan wajib diisi.");
        }

        
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
        
        
        $stok_sesudah = $jumlah_fisik;
        
        
        $selisih = $stok_sesudah - $stok_sebelum;
        $masuk = 0;
        $keluar = 0;

        if ($selisih > 0) {
            $masuk = $selisih;
        } else {
            $keluar = abs($selisih);
        }

        
        
        if ($id_stok_inventori) {
            
            $sql_update_stok = "UPDATE tbl_stok_inventori 
                                SET stok_akhir = ?, updated_at = NOW() 
                                WHERE id_stok = ?";
            $pdo->prepare($sql_update_stok)->execute([$stok_sesudah, $id_stok_inventori]);
            
        } else {
            
            $stok_minimum_default = 10; 
            
            $sql_insert_stok = "INSERT INTO tbl_stok_inventori 
                                  (id_obat, id_unit, stok_akhir, stok_minimum) 
                                VALUES (?, ?, ?, ?)";
            $pdo->prepare($sql_insert_stok)->execute([$id_obat, $id_unit, $stok_sesudah, $stok_minimum_default]);
        }

        
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
            $id_user_pencatat
        ]);
        
        
        $new_id_log = $pdo->lastInsertId();

       
        $pdo->commit();
        
        
        header("Location: " . BASE_URL . "stok/opname.php?status=tambah_sukses&new_id=" . $new_id_log);
        exit;

    } catch (Exception $e) {
        
        $pdo->rollBack();
        
        
        $error_message = urlencode($e->getMessage());
        header("Location: " . BASE_URL . "stok/opname.php?status=gagal&msg=" . $error_message);
        exit;
    }

} else {
    
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}
?>