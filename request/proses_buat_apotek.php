<?php
// request/proses_buat_apotek.php
// Memproses form request stok dari Apotek (Role 5) ke Gudang

require_once '../config.php';
require_once '../templates/auth_check.php';

// Hanya Role 5 (Apotek) yang boleh
if ($_SESSION['role_id'] != 5) {
    header("Location: " . BASE_URL . "request/buat_apotek.php?status=gagal&msg=" . urlencode("Akses ditolak."));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST['action'] == 'create_apotek') {

    $pdo->beginTransaction();

    try {
        $tgl_request        = htmlspecialchars($_POST['tgl_request']);
        $keterangan_request = htmlspecialchars($_POST['keterangan_request'] ?? '');
        $id_user_request    = (int)$_SESSION['user_id'];

        // Apotek (id_unit=2) minta ke Gudang (id_unit=1)
        $id_unit_tujuan = 1; // GUDANG
        $tipe_request   = 'Apotek_ke_Gudang';

        $obat_ids = $_POST['obat_id'] ?? [];
        $jumlahs  = $_POST['jumlah_request'] ?? [];

        // Validasi minimal 1 obat
        if (empty($obat_ids) || empty($obat_ids[0])) {
            throw new Exception("Data request tidak lengkap. Pastikan minimal 1 obat ditambahkan.");
        }

        // Insert header request
        $sql_header = "INSERT INTO tbl_request_header 
                         (tgl_request, id_user_request, id_unit_tujuan, tipe_request, status, keterangan_request)
                       VALUES (?, ?, ?, ?, 'Pending', ?)";
        $stmt_header = $pdo->prepare($sql_header);
        $stmt_header->execute([$tgl_request, $id_user_request, $id_unit_tujuan, $tipe_request, $keterangan_request]);

        $id_request_baru = $pdo->lastInsertId();

        // Insert detail obat
        $sql_detail  = "INSERT INTO tbl_request_detail (id_request, id_obat, jumlah_request) VALUES (?, ?, ?)";
        $stmt_detail = $pdo->prepare($sql_detail);

        $obat_count = 0;
        foreach ($obat_ids as $index => $id_obat) {
            $id_obat         = (int)$id_obat;
            $jumlah_request  = (int)($jumlahs[$index] ?? 0);

            if ($id_obat > 0 && $jumlah_request > 0) {
                $stmt_detail->execute([$id_request_baru, $id_obat, $jumlah_request]);
                $obat_count++;
            }
        }

        if ($obat_count == 0) {
            throw new Exception("Tidak ada item obat yang valid untuk diminta.");
        }

        $pdo->commit();

        header("Location: " . BASE_URL . "request/buat_apotek.php?status=tambah_sukses");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: " . BASE_URL . "request/buat_apotek.php?status=gagal&msg=" . urlencode($e->getMessage()));
        exit;
    }

} else {
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}
?>