<?php
// request/proses_setujui.php
// Memproses persetujuan request dari Poli Belakang oleh Apotek (Role 5)
// ALUR: Stok dipotong dari APOTEK (id_unit=2), dikirim ke unit Poli pemohon
//
// LOGIKA PENGIRIMAN PARSIAL:
//   - Stok APOTEK cukup untuk semua item  → kirim penuh    → status Completed
//   - Stok APOTEK kurang di sebagian item → kirim yang ada → status Partial
//   - Stok APOTEK habis untuk semua item  → tidak ada yang dikirim → status Cancelled
//   - Saat Partial, tombol "Kirim Sisa" muncul dan akan memanggil file ini lagi

require_once '../config.php';
require_once '../templates/auth_check.php';

// Hanya Role 1 (Super Admin) dan Role 5 (Apotek) yang boleh
if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 5) {
    header("Location: " . BASE_URL . "request/kelola.php?status_aksi=gagal&msg=" . urlencode("Akses ditolak."));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_request'])) {

    $pdo->beginTransaction();

    try {
        $id_request      = (int)$_POST['id_request'];
        $id_user_approve = (int)$_SESSION['user_id'];
        $tgl_approve     = date('Y-m-d H:i:s');

        // Stok diambil dari APOTEK (id_unit=2)
        $id_unit_asal = 2;

        // -------------------------------------------------------
        // 1. Ambil dan kunci header request
        //    Hanya boleh proses jika status Pending ATAU Partial
        // -------------------------------------------------------
        $stmt_header = $pdo->prepare(
            "SELECT * FROM tbl_request_header
             WHERE id_request = ?
               AND status IN ('Pending', 'Partial')
               AND tipe_request = 'Poli_ke_Apotek'
             FOR UPDATE"
        );
        $stmt_header->execute([$id_request]);
        $request_header = $stmt_header->fetch();

        if (!$request_header) {
            throw new Exception("Request tidak ditemukan, sudah selesai/dibatalkan, atau bukan request Poli→Apotek.");
        }
        $id_unit_tujuan = $request_header['id_unit_tujuan'];

        // -------------------------------------------------------
        // 2. Ambil detail item obat yang BELUM TERPENUHI PENUH
        // -------------------------------------------------------
        $stmt_detail = $pdo->prepare(
            "SELECT * FROM tbl_request_detail
             WHERE id_request = ?
             ORDER BY id_obat ASC"
        );
        $stmt_detail->execute([$id_request]);
        $request_details = $stmt_detail->fetchAll();

        if (empty($request_details)) {
            throw new Exception("Request ini tidak memiliki item obat.");
        }

        // -------------------------------------------------------
        // 3. Siapkan prepared statements
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
        $stmt_update_terpenuhi = $pdo->prepare(
            "UPDATE tbl_request_detail SET jumlah_terpenuhi = ? WHERE id_request_detail = ?"
        );

        // -------------------------------------------------------
        // 4. Proses per item obat — kirim sebanyak yang bisa
        // -------------------------------------------------------
        $total_item      = count($request_details);
        $item_completed  = 0; // item yang sudah 100% terpenuhi setelah proses ini
        $item_ada_kirim  = 0; // item yang berhasil dikirim (penuh atau parsial)
        $item_kosong     = 0; // item yang stok apotek-nya 0 (tidak bisa kirim apa pun)

        foreach ($request_details as $item) {
            $id_detail       = $item['id_request_detail'];
            $id_obat         = $item['id_obat'];
            $jumlah_request  = (int)$item['jumlah_request'];
            $sudah_dikirim   = (int)$item['jumlah_terpenuhi'];
            $sisa_dibutuhkan = $jumlah_request - $sudah_dikirim;

            // Lewati item yang sudah terpenuhi penuh sebelumnya
            if ($sisa_dibutuhkan <= 0) {
                $item_completed++;
                continue;
            }

            // --- Cek stok APOTEK (sumber) ---
            $stmt_cek_stok->execute([$id_obat, $id_unit_asal]);
            $stok_asal = $stmt_cek_stok->fetch();

            $stok_tersedia = $stok_asal ? (int)$stok_asal['stok_akhir'] : 0;

            if ($stok_tersedia <= 0) {
                // Stok apotek habis untuk obat ini → lewati
                $item_kosong++;
                continue;
            }

            // Kirim sebanyak yang bisa (maks = sisa dibutuhkan)
            $jumlah_dikirim = min($sisa_dibutuhkan, $stok_tersedia);

            // --- Potong stok APOTEK ---
            $stok_sebelum_asal = $stok_tersedia;
            $stok_sesudah_asal = $stok_sebelum_asal - $jumlah_dikirim;
            $stmt_update_stok->execute([$stok_sesudah_asal, $stok_asal['id_stok']]);

            // --- Tambah stok unit tujuan (Poli) ---
            $stmt_cek_stok->execute([$id_obat, $id_unit_tujuan]);
            $stok_tujuan = $stmt_cek_stok->fetch();

            if ($stok_tujuan) {
                $stok_sebelum_tujuan = (int)$stok_tujuan['stok_akhir'];
                $stok_sesudah_tujuan = $stok_sebelum_tujuan + $jumlah_dikirim;
                $stmt_update_stok->execute([$stok_sesudah_tujuan, $stok_tujuan['id_stok']]);
            } else {
                $stok_sebelum_tujuan = 0;
                $stok_sesudah_tujuan = $jumlah_dikirim;
                $stmt_insert_stok->execute([$id_obat, $id_unit_tujuan, $stok_sesudah_tujuan]);
            }

            // --- Keterangan transfer ---
            $is_parsial_item = ($jumlah_dikirim < $sisa_dibutuhkan);
            $ket_transfer = $is_parsial_item
                ? "Request #$id_request (Parsial: {$jumlah_dikirim}/{$jumlah_request})"
                : "Request #$id_request";

            // --- Catat transaksi transfer ---
            $stmt_transfer->execute([
                $id_request, $tgl_approve, $id_obat,
                $id_unit_asal, $id_unit_tujuan, $jumlah_dikirim,
                $id_user_approve, $ket_transfer
            ]);
            $id_ref = $pdo->lastInsertId();

            // --- Log stok APOTEK (keluar) ---
            $stmt_log->execute([
                $tgl_approve, $id_obat, $id_unit_asal,
                $stok_sebelum_asal, 0, $jumlah_dikirim, $stok_sesudah_asal,
                $ket_transfer, $id_ref
            ]);

            // --- Log stok unit tujuan (masuk) ---
            $stmt_log->execute([
                $tgl_approve, $id_obat, $id_unit_tujuan,
                $stok_sebelum_tujuan, $jumlah_dikirim, 0, $stok_sesudah_tujuan,
                $ket_transfer, $id_ref
            ]);

            // --- Update jumlah_terpenuhi di request_detail ---
            $total_terpenuhi_baru = $sudah_dikirim + $jumlah_dikirim;
            $stmt_update_terpenuhi->execute([$total_terpenuhi_baru, $id_detail]);

            $item_ada_kirim++;

            if ($total_terpenuhi_baru >= $jumlah_request) {
                $item_completed++;
            }
        }

        // -------------------------------------------------------
        // 5. Tentukan status akhir request
        //    Completed : semua item terpenuhi penuh
        //    Partial   : ada yang terkirim tapi belum semua penuh
        //    Cancelled : tidak ada satu item pun yang bisa dikirim
        // -------------------------------------------------------
        if ($item_completed === $total_item) {
            $status_baru      = 'Completed';
            $tgl_approve_set  = $tgl_approve;
            $redirect_kode    = 'sukses';
        } elseif ($item_ada_kirim > 0) {
            $status_baru      = 'Partial';
            $tgl_approve_set  = null; // belum selesai, tidak set tgl_approve
            $redirect_kode    = 'parsial';
        } else {
            // Tidak ada yang terkirim sama sekali
            $status_baru      = 'Cancelled';
            $tgl_approve_set  = $tgl_approve;
            $redirect_kode    = 'stok_habis';
        }

        $pdo->prepare(
            "UPDATE tbl_request_header
             SET status = ?, id_user_approve = ?, tgl_approve = ?
             WHERE id_request = ?"
        )->execute([$status_baru, $id_user_approve, $tgl_approve_set, $id_request]);

        $pdo->commit();

        // Redirect ke halaman detail agar user langsung lihat hasilnya
        header("Location: " . BASE_URL . "request/detail.php?id={$id_request}&status_aksi={$redirect_kode}");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = urlencode($e->getMessage());
        // Coba redirect ke detail jika id_request sudah diset
        if (!empty($id_request)) {
            header("Location: " . BASE_URL . "request/detail.php?id={$id_request}&status_aksi=error&msg={$msg}");
        } else {
            header("Location: " . BASE_URL . "request/kelola.php?status_aksi=gagal&msg={$msg}");
        }
        exit;
    }

} else {
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}
?>