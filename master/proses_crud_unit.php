<?php

require_once '../config.php';


require_once '../templates/auth_check.php';


if ($_SESSION['role_id'] != 1) {
    header("Location: " . BASE_URL . "master/data_unit.php?status=gagal_akses");
    exit;
}


$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {

    switch ($action) {

        case 'create':
          
            $nama_unit = htmlspecialchars($_POST['nama_unit']);

  
            if (empty($nama_unit)) {
                throw new Exception("Nama Unit wajib diisi.");
            }

            $sql = "INSERT INTO tbl_unit (nama_unit) VALUES (?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nama_unit]);

            header("Location: " . BASE_URL . "master/data_unit.php?status=tambah_sukses");
            exit;

        case 'update':

            $id_unit = (int)$_POST['id_unit'];
            $nama_unit = htmlspecialchars($_POST['nama_unit']);

            if (empty($nama_unit) || empty($id_unit)) {
                throw new Exception("Semua field wajib diisi.");
            }

            $sql = "UPDATE tbl_unit SET nama_unit = ? WHERE id_unit = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nama_unit, $id_unit]);

            header("Location: " . BASE_URL . "master/data_unit.php?status=update_sukses");
            exit;

        case 'delete':

            $id_unit_hapus = (int)$_GET['id'];
            
            if (empty($id_unit_hapus)) {
                 throw new Exception("ID Unit tidak valid.");
            }

            $sql = "DELETE FROM tbl_unit WHERE id_unit = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_unit_hapus]);

            header("Location: " . BASE_URL . "master/data_unit.php?status=hapus_sukses");
            exit;

        default:
            throw new Exception("Aksi tidak dikenali.");
    }

} catch (PDOException $e) {

    
    $error_message = "Operasi database gagal.";

    if (str_contains($e->getMessage(), 'foreign key constraint')) {
        $error_message = "Gagal menghapus! Unit ini masih digunakan oleh data Poli, User, atau Transaksi.";
    } else {
        $error_message = $e->getMessage();
    }
    
    header("Location: " . BASE_URL . "master/data_unit.php?status=gagal&msg=" . urlencode($error_message));
    exit;

} catch (Exception $e) {

    header("Location: " . BASE_URL . "master/data_unit.php?status=gagal&msg=" . urlencode($e->getMessage()));
    exit;
}
?>