<?php

require_once '../config.php';


require_once '../templates/auth_check.php';


if ($_SESSION['role_id'] != 2 && $_SESSION['role_id'] != 4) {
    header("Location: " . BASE_URL . "resep/tambah.php?status=gagal_akses");
    exit;
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    
    $pdo->beginTransaction();

    try {
        
        $tgl_resep = htmlspecialchars($_POST['tgl_resep']);
        $nama_pasien = htmlspecialchars($_POST['nama_pasien']);
        $id_poli = (int)$_POST['id_poli'];
        $id_pelayanan = (int)$_POST['id_pelayanan'];
        $id_user_pencatat = (int)$_SESSION['user_id'];
        
        
        $kelengkapan_resep = htmlspecialchars($_POST['kelengkapan_resep']);
        $kesalahan_resep = htmlspecialchars($_POST['kesalahan_resep']);
        $sesuai_formularium = htmlspecialchars($_POST['sesuai_formularium']);
        
        
        $obat_ids = $_POST['obat_id'];
        $jumlahs = $_POST['jumlah'];
        $racikans = $_POST['racikan'];

        
        if (empty($tgl_resep) || empty($id_poli) || empty($id_pelayanan) || !is_array($obat_ids) || empty($obat_ids[0])) {
            throw new Exception("Data resep tidak lengkap. Pastikan minimal 1 obat ditambahkan.");
        }

        
        $stmt_unit_poli = $pdo->prepare("SELECT id_unit_stok_default FROM tbl_poli WHERE id_poli = ?");
        $stmt_unit_poli->execute([$id_poli]);
        $unit_poli = $stmt_unit_poli->fetch();
        
        if (!$unit_poli) {
            throw new Exception("Gagal menemukan unit stok default untuk Poli yang dipilih.");
        }
        $id_unit_asal = $unit_poli['id_unit_stok_default']; 

       
        $sql_header = "INSERT INTO tbl_resep_header 
                         (tgl_resep, nama_pasien, id_poli, id_pelayanan, id_user_pencatat, 
                          kelengkapan_resep, kesalahan_resep, sesuai_formularium)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_header = $pdo->prepare($sql_header);
        $stmt_header->execute([
            $tgl_resep, $nama_pasien, $id_poli, $id_pelayanan, $id_user_pencatat,
            $kelengkapan_resep, $kesalahan_resep, $sesuai_formularium
        ]);
        
        
        $id_resep_baru = $pdo->lastInsertId();

        
        $sql_cek_stok = "SELECT id_stok, stok_akhir FROM tbl_stok_inventori WHERE id_obat = ? AND id_unit = ? FOR UPDATE";
        $stmt_cek_stok = $pdo->prepare($sql_cek_stok);
        
        $sql_update_stok = "UPDATE tbl_stok_inventori SET stok_akhir = ? WHERE id_stok = ?";
        $stmt_update_stok = $pdo->prepare($sql_update_stok);

        $sql_detail = "INSERT INTO tbl_resep_detail (id_resep, id_obat, id_unit_asal, jumlah_keluar, jenis_racikan)
                       VALUES (?, ?, ?, ?, ?)";
        $stmt_detail = $pdo->prepare($sql_detail);
        
        $sql_log = "INSERT INTO tbl_log_stok 
                      (tgl_log, id_obat, id_unit, sumber_data, stok_sebelum, masuk, keluar, stok_sesudah, keterangan, id_referensi_transaksi) 
                    VALUES (?, ?, ?, 'Resep', ?, 0, ?, ?, ?, ?)";
        $stmt_log = $pdo->prepare($sql_log);


        
        foreach ($obat_ids as $index => $id_obat) {
            
            $id_obat = (int)$id_obat;
            $jumlah_keluar = (int)$jumlahs[$index];
            $jenis_racikan = htmlspecialchars($racikans[$index]);
            
            if ($jumlah_keluar <= 0) continue; 

            
            $stmt_cek_stok->execute([$id_obat, $id_unit_asal]);
            $stok_saat_ini = $stmt_cek_stok->fetch();

            if (!$stok_saat_ini || $stok_saat_ini['stok_akhir'] < $jumlah_keluar) {
               
                throw new Exception("Stok obat (ID: $id_obat) tidak mencukupi di unit ini. Transaksi dibatalkan.");
            }
            
            $stok_sebelum = $stok_saat_ini['stok_akhir'];
            $stok_sesudah = $stok_sebelum - $jumlah_keluar;
            $id_stok_inventori = $stok_saat_ini['id_stok'];

            
            $stmt_update_stok->execute([$stok_sesudah, $id_stok_inventori]);

            
            $stmt_detail->execute([$id_resep_baru, $id_obat, $id_unit_asal, $jumlah_keluar, $jenis_racikan]);
            $id_referensi_detail = $pdo->lastInsertId(); 

           
            $stmt_log->execute([
                $tgl_resep, $id_obat, $id_unit_asal,
                $stok_sebelum, $jumlah_keluar, $stok_sesudah,
                "Pemakaian Resep Pasien: " . $nama_pasien,
                $id_referensi_detail 
            ]);
        }
        
        $pdo->commit();
        

        header("Location: " . BASE_URL . "resep/tambah.php?status=tambah_sukses");
        exit;

    } catch (Exception $e) {
     
        $pdo->rollBack();
        
       
        $error_message = urlencode($e->getMessage());
        header("Location: " . BASE_URL . "resep/tambah.php?status=gagal&msg=" . $error_message);
        exit;
    }

} else {
   
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}
?>