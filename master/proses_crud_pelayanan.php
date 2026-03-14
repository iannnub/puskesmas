<?php

require_once '../config.php';

require_once '../templates/auth_check.php';

if ($_SESSION['role_id'] != 1) {
    header("Location: " . BASE_URL . "master/data_pelayanan.php?status=gagal_akses");
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {

    switch ($action) {

        case 'create':

            $jenis_pelayanan = htmlspecialchars($_POST['jenis_pelayanan']);

            if (empty($jenis_pelayanan)) {
                throw new Exception("Jenis Pelayanan wajib diisi.");
            }

            $sql = "INSERT INTO tbl_pelayanan (jenis_pelayanan) VALUES (?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$jenis_pelayanan]);

            header("Location: " . BASE_URL . "master/data_pelayanan.php?status=tambah_sukses");
            exit;

        case 'update':

            $id_pelayanan = (int)$_POST['id_pelayanan'];
            $jenis_pelayanan = htmlspecialchars($_POST['jenis_pelayanan']);

            if (empty($jenis_pelayanan) || empty($id_pelayanan)) {
                throw new Exception("Semua field wajib diisi.");
            }

            $sql = "UPDATE tbl_pelayanan SET jenis_pelayanan = ? WHERE id_pelayanan = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$jenis_pelayanan, $id_pelayanan]);

            header("Location: " . BASE_URL . "master/data_pelayanan.php?status=update_sukses");
            exit;

        case 'delete':

            $id_pelayanan_hapus = (int)$_GET['id'];
            
            if (empty($id_pelayanan_hapus)) {
                 throw new Exception("ID Pelayanan tidak valid.");
            }
            

            $sql = "DELETE FROM tbl_pelayanan WHERE id_pelayanan = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_pelayanan_hapus]);


            header("Location: " . BASE_URL . "master/data_pelayanan.php?status=hapus_sukses");
            exit;

        default:
            throw new Exception("Aksi tidak dikenali.");
    }

} catch (PDOException $e) {

    
    $error_message = "Operasi database gagal.";
    

    if (str_contains($e->getMessage(), 'foreign key constraint')) {
        $error_message = "Gagal menghapus! Jenis Pelayanan ini masih digunakan oleh data Resep.";
    } else {
        $error_message = $e->getMessage();
    }
    

    header("Location: " . BASE_URL . "master/data_pelayanan.php?status=gagal&msg=" . urlencode($error_message));
    exit;

} catch (Exception $e) {

    header("Location: " . BASE_URL . "master/data_pelayanan.php?status=gagal&msg=" . urlencode($e->getMessage()));
    exit;
}
?>