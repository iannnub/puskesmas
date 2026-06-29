<?php
require_once '../config.php';
require_once '../templates/auth_check.php';

if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) {
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// TAMBAH DOKTER
if ($action == 'create' && $_SERVER["REQUEST_METHOD"] == "POST") {
    $nama_dokter = trim($_POST['nama_dokter'] ?? '');
    if (!empty($nama_dokter)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO tbl_dokter (nama_dokter) VALUES (?)");
            $stmt->execute([$nama_dokter]);
            header("Location: data_obat.php?status_dokter=tambah_sukses");
            exit;
        } catch (Exception $e) {
            header("Location: data_obat.php?status_dokter=gagal&msg=" . urlencode($e->getMessage()));
            exit;
        }
    }
    header("Location: data_obat.php?status_dokter=gagal&msg=Nama+dokter+tidak+boleh+kosong");
    exit;
}

// EDIT DOKTER
if ($action == 'update' && $_SERVER["REQUEST_METHOD"] == "POST") {
    $id_dokter   = (int)($_POST['id_dokter'] ?? 0);
    $nama_dokter = trim($_POST['nama_dokter'] ?? '');
    if ($id_dokter > 0 && !empty($nama_dokter)) {
        try {
            $stmt = $pdo->prepare("UPDATE tbl_dokter SET nama_dokter = ? WHERE id_dokter = ?");
            $stmt->execute([$nama_dokter, $id_dokter]);
            header("Location: data_obat.php?status_dokter=tambah_sukses");
            exit;
        } catch (Exception $e) {
            header("Location: data_obat.php?status_dokter=gagal&msg=" . urlencode($e->getMessage()));
            exit;
        }
    }
    header("Location: data_obat.php?status_dokter=gagal&msg=Data+tidak+valid");
    exit;
}

// HAPUS DOKTER
if ($action == 'delete' && isset($_GET['id'])) {
    $id_dokter = (int)$_GET['id'];
    if ($id_dokter > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM tbl_dokter WHERE id_dokter = ?");
            $stmt->execute([$id_dokter]);
            header("Location: data_obat.php?status_dokter=tambah_sukses");
            exit;
        } catch (Exception $e) {
            header("Location: data_obat.php?status_dokter=gagal&msg=" . urlencode('Gagal menghapus, dokter mungkin masih dipakai di data transaksi.'));
            exit;
        }
    }
}

header("Location: data_obat.php");
exit;