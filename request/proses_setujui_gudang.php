<?php
// request/proses_setujui_gudang.php
// Memproses persetujuan request stok dari Apotek (Role 5) oleh Admin/Gudang (Role 1/2)
// Alur: Stok dipotong dari GUDANG (id_unit=1), ditambahkan ke APOTEK (id_unit=2)

require_once '../config.php';
require_once '../templates/auth_check.php';

// Hanya Super Admin (1) dan Admin Operator (2) yang boleh
if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) {
    header("Location: " . BASE_URL . "request/kelola_gudang.php?status_aksi=gagal&msg=" . urlencode("Akses ditolak."));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_request'])) {

    $pdo->beginTransaction();

    try {
        $id_request      = (int)$_POST['id_request'];
        $id_user_approve = (int)$_SESSION['user_id'];
        $tgl_approve     = date('Y-m-d H:i:s');

        // Unit asal: GUDANG (id=1), Unit tujuan: APOTEK (id=2)
        $id_unit_gudang = 1;
        $id_unit_apotek = 2;

        // -------------------------------------------------------
        // 1. Ambil dan kunci header request (pastikan masih Pending
        //    dan memang tipe Apotek_ke_Gudang)
        // -------------------------------------------------------
        $stmt_header = $pdo->prepare(
            "SELECT * FROM tbl_request_header 
             WHERE id_request = ? 
               AND status = 'Pending' 
               AND tipe_request = 'Apotek_ke_Gudang'
             FOR UPDATE"
        );
        $stmt_header->execute([$id_request]);
        $request_header = $stmt_header->fetch();

        if (!$request_header) {
            throw new Exception("Request #$id_request tidak ditemukan, sudah diproses, atau bukan request Apotek→Gudang.");
        }

        // -------------------------------------------------------
        // 2. Ambil detail item obat
        // -------------------------------------------------------
        $stmt_detail = $pdo->prepare("SELECT * FROM tbl_request_detail WHERE id_request = ?");
        $stmt_detail->execute([$id_request]);
        $request_details = $stmt_detail->fetchAll();

        if (empty($request_details)) {
            throw new Exception("Request #$id_request tidak memiliki item obat.");
        }

        // -------------------------------------------------------
        // 3. Siapkan prepared statements untuk efisiensi
        // -------------------------------------------------------
        $stmt_cek_stok    = $pdo->prepare("SELECT id_stok, stok_akhir FROM tbl_stok_inventori WHERE id_obat = ? AND id_unit = ? FOR UPDATE");
        $stmt_update_stok = $pdo->prepare("UPDATE tbl_stok_inventori SET stok_akhir = ? WHERE id_stok = ?");
        $stmt_insert_stok = $pdo->prepare("INSERT INTO tbl_stok_inventori (id_obat, id_unit, stok_akhir, stok_minimum) VALUES (?, ?, ?, 10)");
        $stmt_transfer    = $pdo->prepare(
            "INSERT INTO tbl_transaksi_transfer 
                (id_request, tgl_transfer, id_obat, id_unit_asal, id_unit_tujuan, jumlah, id_user_pencatat, keterangan)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt_log = $pdo->prepare(
            "INSERT INTO tbl_log_stok 
                (tgl_log, id_obat, id_unit, sumber_data, stok_sebelum, masuk, keluar, stok_sesudah, keterangan, id_referensi_transaksi)
             VALUES (?, ?, ?, 'Transfer', ?, ?, ?, ?, ?, ?)"
        );

        // -------------------------------------------------------
        // 4. Proses per item: potong Gudang, tambah Apotek
        // -------------------------------------------------------
        foreach ($request_details as $item) {
            $id_obat = $item['id_obat'];
            $jumlah  = $item['jumlah_request'];

            // --- Cek & potong stok GUDANG ---
            $stmt_cek_stok->execute([$id_obat, $id_unit_gudang]);
            $stok_gudang = $stmt_cek_stok->fetch();

            if (!$stok_gudang || $stok_gudang['stok_akhir'] < $jumlah) {
                $nama_obat_q = $pdo->prepare("SELECT nama_obat FROM tbl_obat WHERE id_obat = ?");
                $nama_obat_q->execute([$id_obat]);
                $nama_obat = $nama_obat_q->fetchColumn() ?: "ID:$id_obat";
                throw new Exception("Stok obat \"$nama_obat\" tidak mencukupi di Gudang. Sisa: " . ($stok_gudang['stok_akhir'] ?? 0) . ", Diminta: $jumlah");
            }

            $stok_sebelum_gudang  = $stok_gudang['stok_akhir'];
            $stok_sesudah_gudang  = $stok_sebelum_gudang - $jumlah;
            $stmt_update_stok->execute([$stok_sesudah_gudang, $stok_gudang['id_stok']]);

            // --- Tambah stok APOTEK ---
            $stmt_cek_stok->execute([$id_obat, $id_unit_apotek]);
            $stok_apotek = $stmt_cek_stok->fetch();

            if ($stok_apotek) {
                $stok_sebelum_apotek  = $stok_apotek['stok_akhir'];
                $stok_sesudah_apotek  = $stok_sebelum_apotek + $jumlah;
                $stmt_update_stok->execute([$stok_sesudah_apotek, $stok_apotek['id_stok']]);
            } else {
                // Apotek belum pernah punya obat ini → insert baru
                $stok_sebelum_apotek = 0;
                $stok_sesudah_apotek = $jumlah;
                $stmt_insert_stok->execute([$id_obat, $id_unit_apotek, $stok_sesudah_apotek]);
            }

            // --- Catat transaksi transfer ---
            $keterangan_transfer = "Request Apotek #$id_request";
            $stmt_transfer->execute([
                $id_request, $tgl_approve, $id_obat,
                $id_unit_gudang, $id_unit_apotek, $jumlah,
                $id_user_approve, $keterangan_transfer
            ]);
            $id_ref = $pdo->lastInsertId();

            // --- Log stok GUDANG (keluar) ---
            $stmt_log->execute([
                $tgl_approve, $id_obat, $id_unit_gudang,
                $stok_sebelum_gudang, 0, $jumlah, $stok_sesudah_gudang,
                "Request Apotek #$id_request", $id_ref
            ]);

            // --- Log stok APOTEK (masuk) ---
            $stmt_log->execute([
                $tgl_approve, $id_obat, $id_unit_apotek,
                $stok_sebelum_apotek, $jumlah, 0, $stok_sesudah_apotek,
                "Request Apotek #$id_request", $id_ref
            ]);
        }

        // -------------------------------------------------------
        // 5. Update status header → Completed
        // -------------------------------------------------------
        $pdo->prepare(
            "UPDATE tbl_request_header 
             SET status = 'Completed', id_user_approve = ?, tgl_approve = ? 
             WHERE id_request = ?"
        )->execute([$id_user_approve, $tgl_approve, $id_request]);

        $pdo->commit();

        header("Location: " . BASE_URL . "request/kelola_gudang.php?status_aksi=sukses_approve");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: " . BASE_URL . "request/kelola_gudang.php?status_aksi=gagal&msg=" . urlencode($e->getMessage()));
        exit;
    }

} else {
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}
?>