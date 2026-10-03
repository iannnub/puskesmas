<?php
// resep/proses_hapus.php
// Membatalkan transaksi peresepan dan mengembalikan stok obat ke unit asal
// Menerapkan prinsip IMMUTABLE AUDIT TRAIL: Dilarang keras menghapus baris tbl_log_stok.
// Pembatalan wajib dicatat sebagai baris baru (Reversal Entry) pada tbl_log_stok.

require_once '../config.php';
require_once '../templates/auth_check.php';

// Super Admin (1), Dokter/Poli (2), dan Poli Belakang / Rawat Inap (4) berhak membatalkan
if (!in_array((int)$_SESSION['role_id'], [1, 2, 4], true)) {
    header("Location: " . BASE_URL . "resep/history.php?status=gagal&msg=" . urlencode("Akses ditolak: Anda tidak memiliki wewenang membatalkan resep."));
    exit;
}

$id_resep = (int)($_GET['id'] ?? 0);
if ($id_resep <= 0) {
    header("Location: " . BASE_URL . "resep/history.php?status=gagal&msg=" . urlencode("ID resep tidak valid."));
    exit;
}

$pdo->beginTransaction();

try {
    // 1. Ambil data header resep
    $stmt_header = $pdo->prepare("SELECT * FROM tbl_resep_header WHERE id_resep = ? FOR UPDATE");
    $stmt_header->execute([$id_resep]);
    $resep_header = $stmt_header->fetch(PDO::FETCH_ASSOC);

    if (!$resep_header) {
        throw new Exception("Data resep #$id_resep tidak ditemukan.");
    }

    // Role 4 (Poli Belakang) hanya boleh menghapus resep milik polinya sendiri
    if ((int)$_SESSION['role_id'] === 4 && isset($_SESSION['poli_id']) && $resep_header['id_poli'] != $_SESSION['poli_id']) {
        throw new Exception("Akses ditolak: Anda hanya dapat membatalkan resep dari poli Anda sendiri.");
    }

    // 2. Ambil seluruh detail item obat dalam resep ini
    $stmt_det = $pdo->prepare("SELECT * FROM tbl_resep_detail WHERE id_resep = ?");
    $stmt_det->execute([$id_resep]);
    $details = $stmt_det->fetchAll(PDO::FETCH_ASSOC);

    if (empty($details)) {
        throw new Exception("Resep #$id_resep tidak memiliki item obat.");
    }

    // 3. Siapkan prepared statements
    $stmt_cek_stok = $pdo->prepare("SELECT id_stok, stok_akhir FROM tbl_stok_inventori WHERE id_obat = ? AND id_unit = ? FOR UPDATE");
    $stmt_upd_stok = $pdo->prepare("UPDATE tbl_stok_inventori SET stok_akhir = ? WHERE id_stok = ?");
    $stmt_ins_log  = $pdo->prepare(
        "INSERT INTO tbl_log_stok 
            (tgl_log, id_obat, id_unit, sumber_data, stok_sebelum, masuk, keluar, stok_sesudah, keterangan, id_referensi_transaksi) 
         VALUES (?, ?, ?, 'Resep', ?, ?, 0, ?, ?, ?)"
    );
    $stmt_del_det  = $pdo->prepare("DELETE FROM tbl_resep_detail WHERE id_resep_detail = ?");

    $tgl_batal = date('Y-m-d H:i:s');
    $nama_pasien = !empty($resep_header['nama_pasien']) ? $resep_header['nama_pasien'] : 'Umum';

    // 4. Proses kompensasi stok dan catat Reversal Entry (TANPA DELETE tbl_log_stok)
    foreach ($details as $d) {
        $id_obat      = (int)$d['id_obat'];
        $id_unit_asal = (int)$d['id_unit_asal'];
        $jml_kembali  = (int)$d['jumlah_keluar'];

        // Cek dan kunci stok unit asal
        $stmt_cek_stok->execute([$id_obat, $id_unit_asal]);
        $stk = $stmt_cek_stok->fetch(PDO::FETCH_ASSOC);

        if (!$stk) {
            throw new Exception("Inventori obat ID #$id_obat di unit asal tidak ditemukan.");
        }

        $stok_sebelum = (int)$stk['stok_akhir'];
        $stok_sesudah = $stok_sebelum + $jml_kembali;

        // Kembalikan saldo fisik stok
        $stmt_upd_stok->execute([$stok_sesudah, $stk['id_stok']]);

        // Catat Reversal Entry permanen di tbl_log_stok
        $keterangan_log = "Pembatalan Resep #$id_resep (Pasien: $nama_pasien)";
        $stmt_ins_log->execute([
            $tgl_batal,
            $id_obat,
            $id_unit_asal,
            $stok_sebelum,
            $jml_kembali, // masuk sebagai kompensasi pembalikan
            $stok_sesudah,
            $keterangan_log,
            $id_resep
        ]);

        // Hapus item dari resep detail
        $stmt_del_det->execute([(int)$d['id_resep_detail']]);
    }

    // 5. Hapus header resep
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
?>