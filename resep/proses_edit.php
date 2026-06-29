<?php
require_once '../config.php';
require_once '../templates/auth_check.php';

if ($_SESSION['role_id'] != 4 && $_SESSION['role_id'] != 2) {
    header("Location: " . BASE_URL . "resep/history.php?status=gagal&msg=" . urlencode("Akses ditolak."));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}

$id_resep = (int)($_POST['id_resep'] ?? 0);
if ($id_resep <= 0) {
    header("Location: " . BASE_URL . "resep/history.php?status=gagal&msg=" . urlencode("ID resep tidak valid."));
    exit;
}

// Ambil data ID Poli yang sedang diedit
$id_poli_input = (int)($_POST['id_poli'] ?? 0);

// ========================================================
// 1. AMBIL ID DOKTER DARI POST
// ========================================================
$id_dokter_final = (int)($_POST['id_dokter'] ?? 0);
if ($id_dokter_final <= 0) {
    header("Location: " . BASE_URL . "resep/edit.php?id=$id_resep&status=gagal&msg=" . urlencode("Dokter wajib dipilih."));
    exit;
}

// Ambil nama dokter untuk keperluan log
try {
    $stmt_nama_dok = $pdo->prepare("SELECT nama_dokter FROM tbl_dokter WHERE id_dokter = ?");
    $stmt_nama_dok->execute([$id_dokter_final]);
    $row_dok = $stmt_nama_dok->fetch();
    $nama_dokter_input = $row_dok ? $row_dok['nama_dokter'] : 'Tidak Diketahui';
} catch (Exception $e) {
    header("Location: " . BASE_URL . "resep/edit.php?id=$id_resep&status=gagal&msg=" . urlencode("Gagal memproses data dokter."));
    exit;
}

// ========================================================
// 2. TRANSAKSI UTAMA
// ========================================================
$pdo->beginTransaction();

try {
    // Ambil data resep lama (untuk cek hak akses poli)
    $stmt_cek = $pdo->prepare("SELECT id_poli FROM tbl_resep_header WHERE id_resep = ?");
    $stmt_cek->execute([$id_resep]);
    $resep_lama = $stmt_cek->fetch();

    if (!$resep_lama) {
        throw new Exception("Data resep tidak ditemukan.");
    }

    if ($_SESSION['role_id'] == 4 && $resep_lama['id_poli'] != $_SESSION['poli_id']) {
        throw new Exception("Anda tidak berhak mengedit resep ini.");
    }

    // Ambil data form
    $tgl_resep          = htmlspecialchars($_POST['tgl_resep']);
    $nama_pasien        = htmlspecialchars($_POST['nama_pasien'] ?? '');
    $id_pelayanan       = (int)$_POST['id_pelayanan'];
    $kelengkapan_resep  = htmlspecialchars($_POST['kelengkapan_resep']);
    $kesalahan_resep    = htmlspecialchars($_POST['kesalahan_resep']);
    $sesuai_formularium = htmlspecialchars($_POST['sesuai_formularium']);

    $obat_ids         = $_POST['obat_id']         ?? [];
    $jumlahs          = $_POST['jumlah']           ?? [];
    $racikans         = $_POST['racikan']          ?? [];
    $id_detail_lamas  = $_POST['id_resep_detail']  ?? [];
    $jumlah_lamas     = $_POST['jumlah_lama']      ?? [];

    if (empty($obat_ids) || empty($obat_ids[0])) {
        throw new Exception("Minimal harus ada 1 obat dalam resep.");
    }

    // Cari unit stok default poli
    $stmt_unit = $pdo->prepare("SELECT id_unit_stok_default FROM tbl_poli WHERE id_poli = ?");
    $stmt_unit->execute([$id_poli_input]);
    $unit_poli = $stmt_unit->fetch();
    if (!$unit_poli) {
        throw new Exception("Gagal menemukan unit stok default untuk poli yang dipilih.");
    }
    $id_unit_asal = $unit_poli['id_unit_stok_default'];

    // Update header resep (memasukkan $id_dokter_final)
    $stmt_upd_h = $pdo->prepare("
        UPDATE tbl_resep_header SET
            tgl_resep           = ?,
            nama_pasien         = ?,
            id_poli             = ?,
            id_dokter           = ?,
            id_pelayanan        = ?,
            kelengkapan_resep   = ?,
            kesalahan_resep     = ?,
            sesuai_formularium  = ?
        WHERE id_resep = ?
    ");
    $stmt_upd_h->execute([
        $tgl_resep, $nama_pasien, $id_poli_input, $id_dokter_final,
        $id_pelayanan, $kelengkapan_resep, $kesalahan_resep,
        $sesuai_formularium, $id_resep
    ]);

    // Siapkan statement stok & log
    $stmt_cek_stok  = $pdo->prepare("SELECT id_stok, stok_akhir FROM tbl_stok_inventori WHERE id_obat = ? AND id_unit = ? FOR UPDATE");
    $stmt_upd_stok  = $pdo->prepare("UPDATE tbl_stok_inventori SET stok_akhir = ? WHERE id_stok = ?");
    $stmt_del_log   = $pdo->prepare("DELETE FROM tbl_log_stok WHERE id_referensi_transaksi = ? AND sumber_data = 'Resep'");
    $stmt_ins_log   = $pdo->prepare("
        INSERT INTO tbl_log_stok
            (tgl_log, id_obat, id_unit, sumber_data, stok_sebelum, masuk, keluar, stok_sesudah, keterangan, id_referensi_transaksi)
        VALUES (?, ?, ?, 'Resep', ?, 0, ?, ?, ?, ?)
    ");
    $stmt_upd_det   = $pdo->prepare("
        UPDATE tbl_resep_detail SET id_obat = ?, id_unit_asal = ?, jumlah_keluar = ?, jenis_racikan = ?
        WHERE id_resep_detail = ?
    ");
    $stmt_ins_det   = $pdo->prepare("
        INSERT INTO tbl_resep_detail (id_resep, id_obat, id_unit_asal, jumlah_keluar, jenis_racikan)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($obat_ids as $index => $id_obat) {
        $id_obat       = (int)$id_obat;
        $jumlah_baru   = (int)$jumlahs[$index];
        $jenis_racikan = htmlspecialchars($racikans[$index]);
        $id_det_lama   = (int)($id_detail_lamas[$index] ?? 0);
        $jumlah_lama   = (int)($jumlah_lamas[$index] ?? 0);

        if ($jumlah_baru <= 0) continue;

        $stmt_cek_stok->execute([$id_obat, $id_unit_asal]);
        $stk = $stmt_cek_stok->fetch();
        if (!$stk) {
            throw new Exception("Data stok obat (ID: $id_obat) tidak ditemukan di unit ini.");
        }

        $selisih = $jumlah_baru - $jumlah_lama; // positif = butuh lebih banyak, negatif = kembalikan

        if ($selisih > 0 && $stk['stok_akhir'] < $selisih) {
            // --- PERBAIKAN: Tampilkan Kode dan Nama Obat jika error saat Edit ---
            $stmt_info = $pdo->prepare("SELECT kode_obat, nama_obat FROM tbl_obat WHERE id_obat = ?");
            $stmt_info->execute([$id_obat]);
            $info_obat = $stmt_info->fetch();
            $nama_lengkap_obat = $info_obat ? $info_obat['kode_obat'] . " - " . $info_obat['nama_obat'] : "ID: " . $id_obat;

            throw new Exception("Stok obat ($nama_lengkap_obat) tidak mencukupi untuk perubahan ini. Sisa stok: " . $stk['stok_akhir']);
        }

        $stok_sebelum = $stk['stok_akhir'];
        $stok_sesudah = $stok_sebelum - $selisih;

        $stmt_upd_stok->execute([$stok_sesudah, $stk['id_stok']]);

        if ($id_det_lama > 0) {
            // Update detail yang sudah ada
            $stmt_del_log->execute([$id_det_lama]);
            $stmt_upd_det->execute([$id_obat, $id_unit_asal, $jumlah_baru, $jenis_racikan, $id_det_lama]);
            $id_ref = $id_det_lama;
        } else {
            // Insert detail baru (obat baru ditambahkan saat edit)
            $stmt_ins_det->execute([$id_resep, $id_obat, $id_unit_asal, $jumlah_baru, $jenis_racikan]);
            $id_ref = $pdo->lastInsertId();
        }

        // --- PERBAIKAN: Masukkan nama dokter di LOG History ---
        $ket_log = "Edit Resep Pasien: " . ($nama_pasien ?: 'Umum') . " (Dr. " . $nama_dokter_input . ")";
        $stmt_ins_log->execute([
            $tgl_resep, $id_obat, $id_unit_asal,
            $stok_sebelum, $jumlah_baru, $stok_sesudah,
            $ket_log,
            $id_ref
        ]);
    }

    $pdo->commit();
    header("Location: " . BASE_URL . "resep/history.php?status=edit_sukses");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: " . BASE_URL . "resep/edit.php?id=$id_resep&status=gagal&msg=" . urlencode($e->getMessage()));
    exit;
}
?>