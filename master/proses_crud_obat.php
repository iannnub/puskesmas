<?php

require_once '../config.php';


require_once '../templates/auth_check.php';

if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) {
    header("Location: " . BASE_URL . "master/data_obat.php?status=gagal_akses");
    exit;
}


$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {

    switch ($action) {

        case 'create':
       
            $id_kategori_obat = (int)$_POST['id_kategori_obat'];
            $kode_obat = htmlspecialchars($_POST['kode_obat']);
            $nama_obat = htmlspecialchars($_POST['nama_obat']);
            $satuan = htmlspecialchars($_POST['satuan']);


            if (empty($id_kategori_obat) || empty($kode_obat) || empty($nama_obat) || empty($satuan)) {
                throw new Exception("Semua field wajib diisi.");
            }

        
            $sql = "INSERT INTO tbl_obat (id_kategori_obat, kode_obat, nama_obat, satuan) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_kategori_obat, $kode_obat, $nama_obat, $satuan]);

   
            header("Location: " . BASE_URL . "master/data_obat.php?status=tambah_sukses");
            exit;


        case 'update':
        
            $id_obat = (int)$_POST['id_obat'];
            $id_kategori_obat = (int)$_POST['id_kategori_obat'];
            $kode_obat = htmlspecialchars($_POST['kode_obat']);
            $nama_obat = htmlspecialchars($_POST['nama_obat']);
            $satuan = htmlspecialchars($_POST['satuan']);


            if (empty($id_obat) || empty($id_kategori_obat) || empty($kode_obat) || empty($nama_obat) || empty($satuan)) {
                throw new Exception("Semua field wajib diisi.");
            }

 
            $sql = "UPDATE tbl_obat SET 
                        id_kategori_obat = ?, 
                        kode_obat = ?, 
                        nama_obat = ?, 
                        satuan = ? 
                    WHERE id_obat = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_kategori_obat, $kode_obat, $nama_obat, $satuan, $id_obat]);


            header("Location: " . BASE_URL . "master/data_obat.php?status=update_sukses");
            exit;


        case 'delete':

            $id_obat_hapus = (int)$_GET['id'];
            
            if (empty($id_obat_hapus)) {
                 throw new Exception("ID Obat tidak valid.");
            }
            

            $sql = "DELETE FROM tbl_obat WHERE id_obat = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_obat_hapus]);


            header("Location: " . BASE_URL . "master/data_obat.php?status=hapus_sukses");
            exit;


        default:
            throw new Exception("Aksi tidak dikenali.");
    }

} catch (PDOException $e) {

    $error_message = "Operasi database gagal.";
    

    if (str_contains($e->getMessage(), 'foreign key constraint')) {
        $error_message = "Gagal menghapus! Obat ini masih digunakan oleh data Transaksi atau Resep.";
    } elseif (str_contains($e->getMessage(), 'Duplicate entry')) {
         $error_message = "Gagal menambah/update. Kode Obat '" . htmlspecialchars($_POST['kode_obat']) . "' sudah ada di database.";
    } else {
        $error_message = $e->getMessage();
    }

    header("Location: " . BASE_URL . "master/data_obat.php?status=gagal&msg=" . urlencode($error_message));
    exit;

} catch (Exception $e) {

    header("Location: " . BASE_URL . "master/data_obat.php?status=gagal&msg=" . urlencode($e->getMessage()));
    exit;
}
?>