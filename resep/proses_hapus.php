<?php
require_once '../config.php';
require_once '../templates/auth_check.php';

if ($_SESSION['role_id'] != 4 && $_SESSION['role_id'] != 2) {
    header("Location: " . BASE_URL . "resep/history.php?status=gagal_akses");
    exit;
}

$id_resep = (int)($_GET['id'] ?? 0);
if ($id_resep <= 0) {
    header("Location: " . BASE_URL . "resep/history.php?status=gagal&msg=" . urlencode("ID tidak valid."));
    exit;
}

// Poli belakang hanya bisa hapus resep poli sendiri
if ($_SESSION['role_id'] == 4) {
    $stmt_cek = $pdo->prepare("SELECT id_poli FROM tbl_resep_header WHERE id_resep = ?");
    $stmt_cek->execute([$id_resep]);
    $row = $stmt_cek->fetch();
    if (!$row || $row['id_poli'] != $_SESSION['poli_id']) {
        header("Location: " . BASE_URL . "resep/history.php?status=gagal&msg=" . urlencode("Anda tidak berhak menghapus resep ini."));
        exit;
    }
}

$pdo->beginTransaction();
try {
    // Ambil semua detail obat
    $stmt_det = $pdo->prepare("SELECT * FROM tbl_resep_detail WHERE id_resep = ?");
    $stmt_det->execute([$id_resep]);
    $details = $stmt_det->fetchAll();

    $stmt_cek_stok = $pdo->prepare("SELECT id_stok, stok_akhir FROM tbl_stok_inventori WHERE id_obat = ? AND id_unit = ? FOR UPDATE");
    $stmt_upd_stok = $pdo->prepare("UPDATE tbl_stok_inventori SET stok_akhir = ? WHERE id_stok = ?");
    $stmt_del_log  = $pdo->prepare("DELETE FROM tbl_log_stok WHERE id_referensi_transaksi = ? AND sumber_data = 'Resep'");
    $stmt_del_det  = $pdo->prepare("DELETE FROM tbl_resep_detail WHERE id_resep_detail = ?");

    foreach ($details as $d) {
        // Kembalikan stok
        $stmt_cek_stok->execute([$d['id_obat'], $d['id_unit_asal']]);
        $stk = $stmt_cek_stok->fetch();
        if ($stk) {
            $stok_baru = $stk['stok_akhir'] + $d['jumlah_keluar'];
            $stmt_upd_stok->execute([$stok_baru, $stk['id_stok']]);
        }
        // Hapus log
        $stmt_del_log->execute([$d['id_resep_detail']]);
        // Hapus detail
        $stmt_del_det->execute([$d['id_resep_detail']]);
    }

    // Hapus header
    $stmt_del_h = $pdo->prepare("DELETE FROM tbl_resep_header WHERE id_resep = ?");
    $stmt_del_h->execute([$id_resep]);

    $pdo->commit();
    header("Location: " . BASE_URL . "resep/history.php?status=hapus_sukses");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: " . BASE_URL . "resep/history.php?status=gagal&msg=" . urlencode($e->getMessage()));
    exit;
}