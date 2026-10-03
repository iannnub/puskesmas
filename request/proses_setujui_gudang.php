<?php
// request/proses_setujui_gudang.php
// Memproses persetujuan request stok dari Apotek (Role 5) oleh Admin/Gudang (Role 1/2)
// Alur: Stok dipotong dari GUDANG (id_unit=1), ditambahkan ke APOTEK (id_unit=2)
// Mendukung pengiriman bertahap (Partial Delivery) jika stok gudang tidak mencukupi 100%

require_once '../config.php';
require_once '../templates/auth_check.php';

// Hanya Super Admin (1) dan Admin Operator / Gudang (2) yang berhak memproses
if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) {
    header("Location: " . BASE_URL . "request/kelola_gudang.php?status_aksi=gagal&msg=" . urlencode("Akses ditolak: Hanya petugas gudang/admin yang berhak memproses."));
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
        // 1. Ambil dan kunci header request (status Pending atau Partial)
        // -------------------------------------------------------
        $stmt_header = $pdo->prepare(
            "SELECT * FROM tbl_request_header 
             WHERE id_request = ? 
               AND status IN ('Pending', 'Partial') 
               AND tipe_request = 'Apotek_ke_Gudang'
             FOR UPDATE"
        );
        $stmt_header->execute([$id_request]);
        $request_header = $stmt_header->fetch(PDO::FETCH_ASSOC);

        if (!$request_header) {
            throw new Exception("Request #$id_request tidak ditemukan, sudah selesai, atau bukan permintaan Apotek ke Gudang.");
        }

        // -------------------------------------------------------
        // 2. Ambil detail item obat
        // -------------------------------------------------------
        $stmt_detail = $pdo->prepare("SELECT * FROM tbl_request_detail WHERE id_request = ?");
        $stmt_detail->execute([$id_request]);
        $request_details = $stmt_detail->fetchAll(PDO::FETCH_ASSOC);

        if (empty($request_details)) {
            throw new Exception("Request #$id_request tidak memiliki item obat.");
        }

        // -------------------------------------------------------
        // 3. Siapkan prepared statements
        // -------------------------------------------------------
        $stmt_cek_stok = $pdo->prepare("SELECT id_stok, stok_akhir FROM tbl_stok_inventori WHERE id_obat = ? AND id_unit = ? FOR UPDATE");
        $stmt_update_stok = $pdo->prepare("UPDATE tbl_stok_inventori SET stok_akhir = ? WHERE id_stok = ?");
        $stmt_insert_stok = $pdo->prepare("INSERT INTO tbl_stok_inventori (id_obat, id_unit, stok_akhir, stok_minimum) VALUES (?, ?, ?, 10)");
        $stmt_transfer = $pdo->prepare(
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
        // 4. Proses per item obat — algoritma Partial Delivery
        // -------------------------------------------------------
        $total_item      = count($request_details);
        $item_completed  = 0;
        $item_ada_kirim  = 0;
        $item_kosong     = 0;

        foreach ($request_details as $item) {
            $id_detail       = (int)$item['id_request_detail'];
            $id_obat         = (int)$item['id_obat'];
            $jumlah_request  = (int)$item['jumlah_request'];
            $sudah_dikirim   = (int)($item['jumlah_terpenuhi'] ?? 0);
            $sisa_dibutuhkan = $jumlah_request - $sudah_dikirim;

            // Lewati item yang sudah terpenuhi 100% sebelumnya
            if ($sisa_dibutuhkan <= 0) {
                $item_completed++;
                continue;
            }

            // --- Cek stok fisik GUDANG (sumber) ---
            $stmt_cek_stok->execute([$id_obat, $id_unit_gudang]);
            $stok_gudang = $stmt_cek_stok->fetch(PDO::FETCH_ASSOC);
            $stok_tersedia = $stok_gudang ? (int)$stok_gudang['stok_akhir'] : 0;

            if ($stok_tersedia <= 0) {
                // Stok di gudang kosong sama sekali untuk obat ini
                $item_kosong++;
                continue;
            }

            // Hitung kuantitas kirim: min(sisa_dibutuhkan, stok_tersedia)
            $jumlah_dikirim = min($sisa_dibutuhkan, $stok_tersedia);

            if ($jumlah_dikirim <= 0) {
                continue;
            }

            // --- Potong stok GUDANG ---
            $stok_sebelum_gudang = $stok_tersedia;
            $stok_sesudah_gudang = $stok_sebelum_gudang - $jumlah_dikirim;
            $stmt_update_stok->execute([$stok_sesudah_gudang, $stok_gudang['id_stok']]);

            // --- Tambah stok APOTEK ---
            $stmt_cek_stok->execute([$id_obat, $id_unit_apotek]);
            $stok_apotek = $stmt_cek_stok->fetch(PDO::FETCH_ASSOC);

            if ($stok_apotek) {
                $stok_sebelum_apotek = (int)$stok_apotek['stok_akhir'];
                $stok_sesudah_apotek = $stok_sebelum_apotek + $jumlah_dikirim;
                $stmt_update_stok->execute([$stok_sesudah_apotek, $stok_apotek['id_stok']]);
            } else {
                $stok_sebelum_apotek = 0;
                $stok_sesudah_apotek = $jumlah_dikirim;
                $stmt_insert_stok->execute([$id_obat, $id_unit_apotek, $stok_sesudah_apotek]);
            }

            // --- Update jumlah_terpenuhi di tbl_request_detail ---
            $total_baru_dikirim = $sudah_dikirim + $jumlah_dikirim;
            $stmt_update_terpenuhi->execute([$total_baru_dikirim, $id_detail]);

            if ($total_baru_dikirim >= $jumlah_request) {
                $item_completed++;
            }
            $item_ada_kirim++;

            // --- Catat transaksi transfer ---
            $keterangan_transfer = "Distribusi Gudang ke Apotek (Req #$id_request)";
            $stmt_transfer->execute([
                $id_request, $tgl_approve, $id_obat,
                $id_unit_gudang, $id_unit_apotek, $jumlah_dikirim,
                $id_user_approve, $keterangan_transfer
            ]);
            $id_ref = $pdo->lastInsertId();

            // --- Log audit GUDANG (keluar) ---
            $stmt_log->execute([
                $tgl_approve, $id_obat, $id_unit_gudang,
                $stok_sebelum_gudang, 0, $jumlah_dikirim, $stok_sesudah_gudang,
                $keterangan_transfer, $id_ref
            ]);

            // --- Log audit APOTEK (masuk) ---
            $stmt_log->execute([
                $tgl_approve, $id_obat, $id_unit_apotek,
                $stok_sebelum_apotek, $jumlah_dikirim, 0, $stok_sesudah_apotek,
                $keterangan_transfer, $id_ref
            ]);
        }

        // -------------------------------------------------------
        // 5. Tentukan status akhir header request
        // -------------------------------------------------------
        if ($item_completed === $total_item) {
            $status_baru = 'Completed';
        } elseif ($item_ada_kirim > 0) {
            $status_baru = 'Partial';
        } else {
            // Jika tidak ada barang yang bisa dikirim:
            // Jika sebelumnya sudah Partial, tetap Partial; jika Pending dan 0 stok, Cancelled
            $status_baru = ($request_header['status'] === 'Partial') ? 'Partial' : 'Cancelled';
        }

        $stmt_update_header = $pdo->prepare(
            "UPDATE tbl_request_header 
             SET status = ?, id_user_approve = ?, tgl_approve = ? 
             WHERE id_request = ?"
        );
        $stmt_update_header->execute([$status_baru, $id_user_approve, $tgl_approve, $id_request]);

        $pdo->commit();

        $pesan_sukses = ($status_baru === 'Completed') 
            ? "Permintaan #$id_request berhasil disetujui penuh (Completed)." 
            : (($status_baru === 'Partial') 
                ? "Permintaan #$id_request diproses sebagian (Partial). Sisa kebutuhan tetap tersimpan." 
                : "Permintaan #$id_request tidak dapat dipenuhi karena stok gudang kosong (Cancelled).");

        header("Location: " . BASE_URL . "request/kelola_gudang.php?status_aksi=sukses_approve&msg=" . urlencode($pesan_sukses));
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