<?php

require_once '../config.php';

require_once '../templates/auth_check.php';


if ($_SESSION['role_id'] != 1) {
    header("Location: " . BASE_URL . "master/data_poli.php?status=gagal_akses");
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {

    switch ($action) {

        case 'create':

            $nama_poli = htmlspecialchars($_POST['nama_poli']);
            $id_unit_stok_default = (int)$_POST['id_unit_stok_default'];

            if (empty($nama_poli) || empty($id_unit_stok_default)) {
                throw new Exception("Nama Poli dan Unit Stok Default wajib diisi.");
            }

            $sql = "INSERT INTO tbl_poli (nama_poli, id_unit_stok_default) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nama_poli, $id_unit_stok_default]);

            header("Location: " . BASE_URL . "master/data_poli.php?status=tambah_sukses");
            exit;

        case 'update':

            $id_poli = (int)$_POST['id_poli'];
            $nama_poli = htmlspecialchars($_POST['nama_poli']);
            $id_unit_stok_default = (int)$_POST['id_unit_stok_default'];

            if (empty($nama_poli) || empty($id_unit_stok_default) || empty($id_poli)) {
                throw new Exception("Semua field wajib diisi.");
            }

            $sql = "UPDATE tbl_poli SET nama_poli = ?, id_unit_stok_default = ? WHERE id_poli = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nama_poli, $id_unit_stok_default, $id_poli]);

            header("Location: " . BASE_URL . "master/data_poli.php?status=update_sukses");
            exit;

        case 'delete':

            $id_poli_hapus = (int)$_GET['id'];
            
            if (empty($id_poli_hapus)) {
                 throw new Exception("ID Poli tidak valid.");
            }

            $sql = "DELETE FROM tbl_poli WHERE id_poli = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_poli_hapus]);

            header("Location: " . BASE_URL . "master/data_poli.php?status=hapus_sukses");
            exit;

        default:
            throw new Exception("Aksi tidak dikenali.");
    }

} catch (PDOException $e) {

    $error_message = "Operasi database gagal.";

    if (str_contains($e->getMessage(), 'foreign key constraint')) {
        $error_message = "Gagal menghapus! Poli ini masih digunakan oleh data User atau data Resep.";
    } else {
        $error_message = $e->getMessage();
    }

    header("Location: " . BASE_URL . "master/data_poli.php?status=gagal&msg=" . urlencode($error_message));
    exit;

} catch (Exception $e) {

    header("Location: " . BASE_URL . "master/data_poli.php?status=gagal&msg=" . urlencode($e->getMessage()));
    exit;
}
?>