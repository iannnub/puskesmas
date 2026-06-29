<?php
/**
 * 
 *
 * Parameter wajib:
 *   ?type=bulanan     + &bulan=YYYY-MM
 *   ?type=harian      + &tgl=YYYY-MM-DD
 *   ?type=kartu_stok  + &id_obat=X &id_unit=X &tgl_awal=YYYY-MM-DD &tgl_akhir=YYYY-MM-DD
 *   ?type=kunjungan   + &bulan=YYYY-MM
 *   ?type=sm          + &bulan=YYYY-MM
 */

require_once '../config.php';
require_once '../templates/auth_check.php';

$type = $_GET['type'] ?? '';

if (empty($type)) {
    die('Parameter type tidak ditemukan.');
}

// ─────────────────────────────────────────────────────────────
// BULANAN
// ─────────────────────────────────────────────────────────────
if ($type === 'bulanan') {

    if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) exit('Akses Ditolak');

    $filter_bulan = $_GET['bulan'] ?? date('Y-m');
    $tgl_awal     = $filter_bulan . "-01 00:00:00";
    $tgl_akhir    = date('Y-m-t 23:59:59', strtotime($tgl_awal));

    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Laporan_Bulanan_" . $filter_bulan . ".xls");

    try {
        $stmt_p     = $pdo->query("SELECT id_poli, nama_poli FROM tbl_poli WHERE id_unit_stok_default = 2 ORDER BY id_poli ASC");
        $polis_depan = $stmt_p->fetchAll();

        $sql = "SELECT o.id_obat, o.kode_obat, o.nama_obat,
                (SELECT stok_sesudah FROM tbl_log_stok WHERE id_obat = o.id_obat AND id_unit = 1 AND tgl_log < :t1 ORDER BY tgl_log DESC, id_log DESC LIMIT 1) as stok_awal,
                (SELECT SUM(masuk) FROM tbl_log_stok WHERE id_obat = o.id_obat AND id_unit = 1 AND tgl_log BETWEEN :t2 AND :t3) as total_masuk,
                (SELECT SUM(rd.jumlah_keluar) FROM tbl_resep_detail rd JOIN tbl_resep_header rh ON rd.id_resep = rh.id_resep JOIN tbl_poli pb ON rh.id_poli = pb.id_poli WHERE rd.id_obat = o.id_obat AND pb.id_unit_stok_default != 2 AND rh.tgl_resep BETWEEN :t4 AND :t5) as poli_belakang
                FROM tbl_obat o ORDER BY o.nama_obat ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['t1' => $tgl_awal, 't2' => $tgl_awal, 't3' => $tgl_akhir, 't4' => $tgl_awal, 't5' => $tgl_akhir]);
        $data_bulanan = $stmt->fetchAll();

        $data_resep = [];
        $stmt_r = $pdo->prepare("SELECT rd.id_obat, rh.id_poli, SUM(rd.jumlah_keluar) as jml FROM tbl_resep_detail rd JOIN tbl_resep_header rh ON rd.id_resep = rh.id_resep WHERE rh.tgl_resep BETWEEN ? AND ? GROUP BY rd.id_obat, rh.id_poli");
        $stmt_r->execute([$tgl_awal, $tgl_akhir]);
        while ($row = $stmt_r->fetch()) {
            $data_resep[$row['id_obat']][$row['id_poli']] = $row['jml'];
        }
    } catch (PDOException $e) { die("Error: " . $e->getMessage()); }
    ?>
    <table border="1">
        <tr><th colspan="<?= 5 + count($polis_depan); ?>" style="font-size:14pt;">LAPORAN BULANAN (<?= date('F Y', strtotime($tgl_awal)); ?>)</th></tr>
        <tr style="background-color:#f2f2f2;font-weight:bold;text-align:center;">
            <th>Kode</th><th>Nama Obat</th><th>Stok Awal</th><th>Masuk</th>
            <?php foreach ($polis_depan as $p) echo "<th>" . $p['nama_poli'] . "</th>"; ?>
            <th>Poli Belakang</th><th>Stok Akhir</th>
        </tr>
        <?php foreach ($data_bulanan as $row):
            $total_k = $row['poli_belakang'] ?? 0;
            $td_poli = "";
            foreach ($polis_depan as $p) {
                $val = $data_resep[$row['id_obat']][$p['id_poli']] ?? 0;
                $td_poli .= "<td align='right'>" . ($val ?: 0) . "</td>";
                $total_k += $val;
            }
            $sisa = ($row['stok_awal'] ?? 0) + ($row['total_masuk'] ?? 0) - $total_k;
            if (($row['stok_awal'] + $row['total_masuk'] + $total_k) > 0): ?>
        <tr>
            <td><?= $row['kode_obat'] ?></td>
            <td><?= $row['nama_obat'] ?></td>
            <td align="right"><?= $row['stok_awal'] ?? 0 ?></td>
            <td align="right"><?= $row['total_masuk'] ?? 0 ?></td>
            <?= $td_poli ?>
            <td align="right"><?= $row['poli_belakang'] ?? 0 ?></td>
            <td align="right"><b><?= $sisa ?></b></td>
        </tr>
        <?php endif; endforeach; ?>
    </table>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// HARIAN — VENDOR → GUDANG
// ─────────────────────────────────────────────────────────────
if ($type === 'harian_vendor_gudang') {

    $tgl_awal  = $_GET['tgl_awal']  ?? date('Y-m-d');
    $tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Harian_Vendor_Gudang_" . $tgl_awal . "_sd_" . $tgl_akhir . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    try {
        $stmt_unit = $pdo->query("SELECT id_unit, nama_unit FROM tbl_unit");
        $gudang_id = null;
        foreach ($stmt_unit->fetchAll(PDO::FETCH_ASSOC) as $u) {
            if (strtoupper($u['nama_unit']) === 'GUDANG') $gudang_id = $u['id_unit'];
        }

        $data = [];
        $total_masuk = 0;
        if ($gudang_id) {
            $stmt = $pdo->prepare("
                SELECT o.kode_obat, o.nama_obat, o.satuan, tm.keterangan AS no_faktur,
                       SUM(tm.jumlah_masuk) AS total_masuk
                FROM tbl_transaksi_masuk tm
                JOIN tbl_obat o ON tm.id_obat = o.id_obat
                WHERE DATE(tm.tgl_masuk) BETWEEN ? AND ? AND tm.id_unit = ?
                GROUP BY o.id_obat, tm.keterangan ORDER BY o.nama_obat ASC
            ");
            $stmt->execute([$tgl_awal, $tgl_akhir, $gudang_id]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($data as $r) $total_masuk += $r['total_masuk'];
        }
    } catch (PDOException $e) { die("Error: " . $e->getMessage()); }
    ?>
    <table border="1">
        <tr><th colspan="5" style="font-size:14pt;text-align:center;">PENERIMAAN HARIAN: VENDOR → GUDANG</th></tr>
        <tr><th colspan="5" style="text-align:center;">Periode: <?= date('d-m-Y', strtotime($tgl_awal)) ?> s/d <?= date('d-m-Y', strtotime($tgl_akhir)) ?></th></tr>
        <tr style="background-color:#f2f2f2;font-weight:bold;text-align:center;">
            <th>Kode Obat</th><th>Nama Obat</th><th>Satuan</th><th>No. Faktur / Keterangan</th><th>Jumlah Masuk</th>
        </tr>
        <?php foreach ($data as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['kode_obat']) ?></td>
            <td><?= htmlspecialchars($r['nama_obat']) ?></td>
            <td align="center"><?= htmlspecialchars($r['satuan']) ?></td>
            <td><?= htmlspecialchars($r['no_faktur'] ?: '-') ?></td>
            <td align="right" style="color:green;font-weight:bold;"><?= number_format($r['total_masuk']) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="background-color:#6c757d;color:#fff;font-weight:bold;">
            <td colspan="4" align="right">GRAND TOTAL</td>
            <td align="right"><?= number_format($total_masuk) ?></td>
        </tr>
    </table>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// HARIAN — APOTEK → POLI
// ─────────────────────────────────────────────────────────────
if ($type === 'harian_apotek_poli') {

    $tgl_awal  = $_GET['tgl_awal']  ?? date('Y-m-d');
    $tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Harian_Apotek_Poli_" . $tgl_awal . "_sd_" . $tgl_akhir . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    try {
        $stmt_unit = $pdo->query("SELECT id_unit, nama_unit FROM tbl_unit");
        $gudang_id = null; $apotek_id = null;
        foreach ($stmt_unit->fetchAll(PDO::FETCH_ASSOC) as $u) {
            if (strtoupper($u['nama_unit']) === 'GUDANG') $gudang_id = $u['id_unit'];
            if (strtoupper($u['nama_unit']) === 'APOTEK')  $apotek_id  = $u['id_unit'];
        }

        $data = [];
        $total_keluar = 0;
        if ($apotek_id) {
            $stmt = $pdo->prepare("
                SELECT o.kode_obat, o.nama_obat, o.satuan, ut.nama_unit AS nama_tujuan, SUM(tt.jumlah) AS total_keluar
                FROM tbl_transaksi_transfer tt
                JOIN tbl_obat o ON tt.id_obat = o.id_obat
                JOIN tbl_unit ut ON tt.id_unit_tujuan = ut.id_unit
                WHERE DATE(tt.tgl_transfer) BETWEEN ? AND ?
                  AND tt.id_unit_asal = ? AND tt.id_unit_tujuan != ?
                GROUP BY o.id_obat, tt.id_unit_tujuan ORDER BY o.nama_obat ASC, ut.nama_unit ASC
            ");
            $stmt->execute([$tgl_awal, $tgl_akhir, $apotek_id, $gudang_id ?? 0]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($data as $r) $total_keluar += $r['total_keluar'];
        }
    } catch (PDOException $e) { die("Error: " . $e->getMessage()); }
    ?>
    <table border="1">
        <tr><th colspan="5" style="font-size:14pt;text-align:center;">DISTRIBUSI HARIAN: APOTEK → POLI</th></tr>
        <tr><th colspan="5" style="text-align:center;">Periode: <?= date('d-m-Y', strtotime($tgl_awal)) ?> s/d <?= date('d-m-Y', strtotime($tgl_akhir)) ?></th></tr>
        <tr style="background-color:#f2f2f2;font-weight:bold;text-align:center;">
            <th>Kode Obat</th><th>Nama Obat</th><th>Satuan</th><th>Tujuan Poli</th><th>Jumlah Keluar</th>
        </tr>
        <?php foreach ($data as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['kode_obat']) ?></td>
            <td><?= htmlspecialchars($r['nama_obat']) ?></td>
            <td align="center"><?= htmlspecialchars($r['satuan']) ?></td>
            <td><?= htmlspecialchars($r['nama_tujuan']) ?></td>
            <td align="right" style="color:red;font-weight:bold;"><?= number_format($r['total_keluar']) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="background-color:#6c757d;color:#fff;font-weight:bold;">
            <td colspan="4" align="right">GRAND TOTAL</td>
            <td align="right"><?= number_format($total_keluar) ?></td>
        </tr>
    </table>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// BULANAN — VENDOR → GUDANG
// ─────────────────────────────────────────────────────────────
if ($type === 'bulanan_vendor_gudang') {

    if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) exit('Akses Ditolak');

    $filter_bulan = $_GET['bulan'] ?? date('Y-m');
    $tgl_awal     = $filter_bulan . "-01";
    $tgl_akhir    = date('Y-m-t', strtotime($tgl_awal));

    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Bulanan_Vendor_Gudang_" . $filter_bulan . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    try {
        $stmt_unit = $pdo->query("SELECT id_unit, nama_unit FROM tbl_unit");
        $gudang_id = null;
        foreach ($stmt_unit->fetchAll(PDO::FETCH_ASSOC) as $u) {
            if (strtoupper($u['nama_unit']) === 'GUDANG') $gudang_id = $u['id_unit'];
        }

        $data = [];
        $total_masuk = 0;
        if ($gudang_id) {
            $stmt = $pdo->prepare("
                SELECT o.kode_obat, o.nama_obat, o.satuan, tm.keterangan AS no_faktur,
                       SUM(tm.jumlah_masuk) AS total_masuk
                FROM tbl_transaksi_masuk tm
                JOIN tbl_obat o ON tm.id_obat = o.id_obat
                WHERE DATE(tm.tgl_masuk) BETWEEN ? AND ? AND tm.id_unit = ?
                GROUP BY o.id_obat, tm.keterangan ORDER BY o.nama_obat ASC
            ");
            $stmt->execute([$tgl_awal, $tgl_akhir, $gudang_id]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($data as $r) $total_masuk += $r['total_masuk'];
        }
    } catch (PDOException $e) { die("Error: " . $e->getMessage()); }
    ?>
    <table border="1">
        <tr><th colspan="5" style="font-size:14pt;text-align:center;">PENERIMAAN BULANAN: VENDOR → GUDANG (<?= date('F Y', strtotime($tgl_awal)) ?>)</th></tr>
        <tr style="background-color:#f2f2f2;font-weight:bold;text-align:center;">
            <th>Kode Obat</th><th>Nama Obat</th><th>Satuan</th><th>No. Faktur / Keterangan</th><th>Jumlah Masuk</th>
        </tr>
        <?php foreach ($data as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['kode_obat']) ?></td>
            <td><?= htmlspecialchars($r['nama_obat']) ?></td>
            <td align="center"><?= htmlspecialchars($r['satuan']) ?></td>
            <td><?= htmlspecialchars($r['no_faktur'] ?: '-') ?></td>
            <td align="right" style="color:green;font-weight:bold;"><?= number_format($r['total_masuk']) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="background-color:#6c757d;color:#fff;font-weight:bold;">
            <td colspan="4" align="right">GRAND TOTAL</td>
            <td align="right"><?= number_format($total_masuk) ?></td>
        </tr>
    </table>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// BULANAN — REKAP STOK APOTEK (Masuk, Keluar, Stok Akhir)
// ─────────────────────────────────────────────────────────────
if ($type === 'bulanan_gudang_apotek') {

    $filter_bulan = $_GET['bulan'] ?? date('Y-m');
    $tgl_awal     = $filter_bulan . "-01";
    $tgl_akhir    = date('Y-m-t', strtotime($tgl_awal));

    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Bulanan_Rekap_Stok_Apotek_" . $filter_bulan . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    try {
        $stmt_unit = $pdo->query("SELECT id_unit, nama_unit FROM tbl_unit");
        $gudang_id = null; $apotek_id = null;
        foreach ($stmt_unit->fetchAll(PDO::FETCH_ASSOC) as $u) {
            if (strtoupper($u['nama_unit']) === 'GUDANG') $gudang_id = $u['id_unit'];
            if (strtoupper($u['nama_unit']) === 'APOTEK')  $apotek_id  = $u['id_unit'];
        }

        $masuk_map = [];
        if ($gudang_id && $apotek_id) {
            $stmt_m = $pdo->prepare("SELECT id_obat, SUM(jumlah) AS total FROM tbl_transaksi_transfer WHERE DATE(tgl_transfer) BETWEEN ? AND ? AND id_unit_asal = ? AND id_unit_tujuan = ? GROUP BY id_obat");
            $stmt_m->execute([$tgl_awal, $tgl_akhir, $gudang_id, $apotek_id]);
            foreach ($stmt_m->fetchAll(PDO::FETCH_ASSOC) as $r) $masuk_map[$r['id_obat']] = $r['total'];
        }

        $keluar_map = [];
        if ($apotek_id) {
            $stmt_k = $pdo->prepare("SELECT id_obat, SUM(jumlah) AS total FROM tbl_transaksi_transfer WHERE DATE(tgl_transfer) BETWEEN ? AND ? AND id_unit_asal = ? AND id_unit_tujuan != ? GROUP BY id_obat");
            $stmt_k->execute([$tgl_awal, $tgl_akhir, $apotek_id, $gudang_id ?? 0]);
            foreach ($stmt_k->fetchAll(PDO::FETCH_ASSOC) as $r) $keluar_map[$r['id_obat']] = $r['total'];
        }

        $stok_map = [];
        if ($apotek_id) {
            $stmt_s = $pdo->prepare("SELECT ls.id_obat, ls.stok_sesudah AS stok_akhir FROM tbl_log_stok ls INNER JOIN (SELECT id_obat, MAX(tgl_log) AS max_tgl FROM tbl_log_stok WHERE id_unit = ? AND tgl_log <= ? GROUP BY id_obat) last ON ls.id_obat = last.id_obat AND ls.tgl_log = last.max_tgl WHERE ls.id_unit = ?");
            $stmt_s->execute([$apotek_id, $tgl_akhir . ' 23:59:59', $apotek_id]);
            foreach ($stmt_s->fetchAll(PDO::FETCH_ASSOC) as $r) $stok_map[$r['id_obat']] = $r['stok_akhir'];
        }

        $all_ids = array_unique(array_merge(array_keys($masuk_map), array_keys($keluar_map), array_keys($stok_map)));
        $obat_list = [];
        if (!empty($all_ids)) {
            $ph = implode(',', array_fill(0, count($all_ids), '?'));
            $stmt_o = $pdo->prepare("SELECT id_obat, kode_obat, nama_obat, satuan FROM tbl_obat WHERE id_obat IN ($ph) ORDER BY nama_obat ASC");
            $stmt_o->execute($all_ids);
            $obat_list = $stmt_o->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) { die("Error: " . $e->getMessage()); }

    $grand_masuk = $grand_keluar = $grand_stok = 0;
    ?>
    <table border="1">
        <tr><th colspan="7" style="font-size:14pt;text-align:center;">REKAP STOK APOTEK BULANAN — MASUK, KELUAR &amp; STOK AKHIR</th></tr>
        <tr><th colspan="7" style="text-align:center;">Periode: <?= date('F Y', strtotime($tgl_awal)) ?></th></tr>
        <tr style="background-color:#f2f2f2;font-weight:bold;text-align:center;">
            <th>No</th><th>Kode Obat</th><th>Nama Obat</th><th>Satuan</th>
            <th>Masuk (dari Gudang)</th><th>Keluar (ke Poli)</th><th>Stok Akhir Apotek</th>
        </tr>
        <?php $no = 1; foreach ($obat_list as $obat):
            $id = $obat['id_obat'];
            $m  = $masuk_map[$id]  ?? 0;
            $k  = $keluar_map[$id] ?? 0;
            $s  = $stok_map[$id]   ?? 0;
            $grand_masuk  += $m;
            $grand_keluar += $k;
            $grand_stok   += $s;
            $warna_stok = $s == 0 ? 'background-color:#f8d7da;' : ($s <= 10 ? 'background-color:#fff3cd;' : '');
        ?>
        <tr>
            <td align="center"><?= $no++ ?></td>
            <td><?= htmlspecialchars($obat['kode_obat']) ?></td>
            <td><?= htmlspecialchars($obat['nama_obat']) ?></td>
            <td align="center"><?= htmlspecialchars($obat['satuan']) ?></td>
            <td align="right" style="color:green;"><?= $m > 0 ? number_format($m) : '-' ?></td>
            <td align="right" style="color:red;"><?= $k > 0 ? number_format($k) : '-' ?></td>
            <td align="right" style="font-weight:bold;<?= $warna_stok ?>"><?= number_format($s) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="background-color:#6c757d;color:#fff;font-weight:bold;">
            <td colspan="4" align="right">GRAND TOTAL</td>
            <td align="right"><?= number_format($grand_masuk) ?></td>
            <td align="right"><?= number_format($grand_keluar) ?></td>
            <td align="right"><?= number_format($grand_stok) ?></td>
        </tr>
    </table>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// BULANAN — APOTEK → POLI
// ─────────────────────────────────────────────────────────────
if ($type === 'bulanan_apotek_poli') {

    $filter_bulan = $_GET['bulan'] ?? date('Y-m');
    $tgl_awal     = $filter_bulan . "-01";
    $tgl_akhir    = date('Y-m-t', strtotime($tgl_awal));

    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Bulanan_Apotek_Poli_" . $filter_bulan . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    try {
        $stmt_unit = $pdo->query("SELECT id_unit, nama_unit FROM tbl_unit");
        $gudang_id = null; $apotek_id = null;
        foreach ($stmt_unit->fetchAll(PDO::FETCH_ASSOC) as $u) {
            if (strtoupper($u['nama_unit']) === 'GUDANG') $gudang_id = $u['id_unit'];
            if (strtoupper($u['nama_unit']) === 'APOTEK')  $apotek_id  = $u['id_unit'];
        }

        $data = [];
        $total_keluar = 0;
        if ($apotek_id) {
            $stmt = $pdo->prepare("
                SELECT o.kode_obat, o.nama_obat, o.satuan, ut.nama_unit AS nama_tujuan, SUM(tt.jumlah) AS total_keluar
                FROM tbl_transaksi_transfer tt
                JOIN tbl_obat o ON tt.id_obat = o.id_obat
                JOIN tbl_unit ut ON tt.id_unit_tujuan = ut.id_unit
                WHERE DATE(tt.tgl_transfer) BETWEEN ? AND ?
                  AND tt.id_unit_asal = ? AND tt.id_unit_tujuan != ?
                GROUP BY o.id_obat, tt.id_unit_tujuan ORDER BY o.nama_obat ASC, ut.nama_unit ASC
            ");
            $stmt->execute([$tgl_awal, $tgl_akhir, $apotek_id, $gudang_id ?? 0]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($data as $r) $total_keluar += $r['total_keluar'];
        }
    } catch (PDOException $e) { die("Error: " . $e->getMessage()); }
    ?>
    <table border="1">
        <tr><th colspan="5" style="font-size:14pt;text-align:center;">DISTRIBUSI BULANAN: APOTEK → POLI (<?= date('F Y', strtotime($tgl_awal)) ?>)</th></tr>
        <tr style="background-color:#f2f2f2;font-weight:bold;text-align:center;">
            <th>Kode Obat</th><th>Nama Obat</th><th>Satuan</th><th>Tujuan Poli</th><th>Jumlah Keluar</th>
        </tr>
        <?php foreach ($data as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['kode_obat']) ?></td>
            <td><?= htmlspecialchars($r['nama_obat']) ?></td>
            <td align="center"><?= htmlspecialchars($r['satuan']) ?></td>
            <td><?= htmlspecialchars($r['nama_tujuan']) ?></td>
            <td align="right" style="color:red;font-weight:bold;"><?= number_format($r['total_keluar']) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="background-color:#6c757d;color:#fff;font-weight:bold;">
            <td colspan="4" align="right">GRAND TOTAL</td>
            <td align="right"><?= number_format($total_keluar) ?></td>
        </tr>
    </table>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// HARIAN — REKAP STOK APOTEK (Masuk, Keluar, Stok Akhir)
// ─────────────────────────────────────────────────────────────
if ($type === 'harian_gudang_apotek') {

    $tgl_awal  = $_GET['tgl_awal']  ?? date('Y-m-d');
    $tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Rekap_Stok_Apotek_" . $tgl_awal . "_sd_" . $tgl_akhir . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    try {
        // Dapatkan id unit GUDANG dan APOTEK
        $stmt_unit = $pdo->query("SELECT id_unit, nama_unit FROM tbl_unit");
        $gudang_id = null; $apotek_id = null;
        foreach ($stmt_unit->fetchAll(PDO::FETCH_ASSOC) as $u) {
            if (strtoupper($u['nama_unit']) === 'GUDANG') $gudang_id = $u['id_unit'];
            if (strtoupper($u['nama_unit']) === 'APOTEK')  $apotek_id  = $u['id_unit'];
        }

        // Masuk apotek (dari Gudang) pada periode
        $masuk_map = [];
        if ($gudang_id && $apotek_id) {
            $stmt_m = $pdo->prepare("SELECT id_obat, SUM(jumlah) AS total FROM tbl_transaksi_transfer WHERE DATE(tgl_transfer) BETWEEN ? AND ? AND id_unit_asal = ? AND id_unit_tujuan = ? GROUP BY id_obat");
            $stmt_m->execute([$tgl_awal, $tgl_akhir, $gudang_id, $apotek_id]);
            foreach ($stmt_m->fetchAll(PDO::FETCH_ASSOC) as $r) $masuk_map[$r['id_obat']] = $r['total'];
        }

        // Keluar apotek (ke Poli, bukan ke Gudang) pada periode
        $keluar_map = [];
        if ($apotek_id) {
            $stmt_k = $pdo->prepare("SELECT id_obat, SUM(jumlah) AS total FROM tbl_transaksi_transfer WHERE DATE(tgl_transfer) BETWEEN ? AND ? AND id_unit_asal = ? AND id_unit_tujuan != ? GROUP BY id_obat");
            $stmt_k->execute([$tgl_awal, $tgl_akhir, $apotek_id, $gudang_id ?? 0]);
            foreach ($stmt_k->fetchAll(PDO::FETCH_ASSOC) as $r) $keluar_map[$r['id_obat']] = $r['total'];
        }

        // Stok akhir apotek
        $stok_map = [];
        if ($apotek_id) {
            $stmt_s = $pdo->prepare("SELECT id_obat, stok_akhir FROM tbl_stok_inventori WHERE id_unit = ?");
            $stmt_s->execute([$apotek_id]);
            foreach ($stmt_s->fetchAll(PDO::FETCH_ASSOC) as $r) $stok_map[$r['id_obat']] = $r['stok_akhir'];
        }

        // Gabungkan semua id_obat yang relevan
        $all_ids = array_unique(array_merge(array_keys($masuk_map), array_keys($keluar_map), array_keys($stok_map)));
        $obat_list = [];
        if (!empty($all_ids)) {
            $ph = implode(',', array_fill(0, count($all_ids), '?'));
            $stmt_o = $pdo->prepare("SELECT id_obat, kode_obat, nama_obat, satuan FROM tbl_obat WHERE id_obat IN ($ph) ORDER BY nama_obat ASC");
            $stmt_o->execute($all_ids);
            $obat_list = $stmt_o->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) { die("Error: " . $e->getMessage()); }

    $grand_masuk = $grand_keluar = $grand_stok = 0;
    ?>
    <table border="1">
        <tr><th colspan="7" style="font-size:14pt;text-align:center;">REKAP STOK APOTEK — MASUK, KELUAR &amp; STOK AKHIR</th></tr>
        <tr><th colspan="7" style="text-align:center;">Periode: <?= date('d-m-Y', strtotime($tgl_awal)) ?> s/d <?= date('d-m-Y', strtotime($tgl_akhir)) ?></th></tr>
        <tr style="background-color:#f2f2f2;font-weight:bold;text-align:center;">
            <th>No</th>
            <th>Kode Obat</th>
            <th>Nama Obat</th>
            <th>Satuan</th>
            <th>Masuk (dari Gudang)</th>
            <th>Keluar (ke Poli)</th>
            <th>Stok Akhir Apotek</th>
        </tr>
        <?php $no = 1; foreach ($obat_list as $obat):
            $id   = $obat['id_obat'];
            $m    = $masuk_map[$id]  ?? 0;
            $k    = $keluar_map[$id] ?? 0;
            $s    = $stok_map[$id]   ?? 0;
            $grand_masuk  += $m;
            $grand_keluar += $k;
            $grand_stok   += $s;
            $warna_stok = $s == 0 ? 'background-color:#f8d7da;' : ($s <= 10 ? 'background-color:#fff3cd;' : '');
        ?>
        <tr>
            <td align="center"><?= $no++ ?></td>
            <td><?= htmlspecialchars($obat['kode_obat']) ?></td>
            <td><?= htmlspecialchars($obat['nama_obat']) ?></td>
            <td align="center"><?= htmlspecialchars($obat['satuan']) ?></td>
            <td align="right" style="color:green;"><?= $m > 0 ? '' . number_format($m) : '-' ?></td>
            <td align="right" style="color:red;"><?= $k > 0 ? '' . number_format($k) : '-' ?></td>
            <td align="right" style="font-weight:bold;<?= $warna_stok ?>"><?= number_format($s) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="background-color:#6c757d;color:#fff;font-weight:bold;">
            <td colspan="4" align="right">GRAND TOTAL</td>
            <td align="right"><?= number_format($grand_masuk) ?></td>
            <td align="right"><?= number_format($grand_keluar) ?></td>
            <td align="right"><?= number_format($grand_stok) ?></td>
        </tr>
    </table>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// HARIAN
// ─────────────────────────────────────────────────────────────
if ($type === 'harian') {

    $filter_tgl = $_GET['tgl'] ?? date('Y-m-d');

    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Rekap_Harian_" . $filter_tgl . ".xls");

    try {
        $sql  = "SELECT o.kode_obat, o.nama_obat, p.nama_poli, SUM(rd.jumlah_keluar) AS total FROM tbl_resep_detail rd JOIN tbl_resep_header rh ON rd.id_resep = rh.id_resep JOIN tbl_obat o ON rd.id_obat = o.id_obat JOIN tbl_poli p ON rh.id_poli = p.id_poli WHERE DATE(rh.tgl_resep) = ? GROUP BY o.id_obat, p.id_poli ORDER BY o.nama_obat ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$filter_tgl]);
        $data = $stmt->fetchAll();
    } catch (PDOException $e) { die($e->getMessage()); }
    ?>
    <table border="1">
        <tr><th colspan="4">REKAP PEMAKAIAN HARIAN (<?= $filter_tgl; ?>)</th></tr>
        <tr><th>Kode Obat</th><th>Nama Obat</th><th>Poli</th><th>Jumlah</th></tr>
        <?php foreach ($data as $d): ?>
        <tr>
            <td><?= $d['kode_obat']; ?></td>
            <td><?= $d['nama_obat']; ?></td>
            <td><?= $d['nama_poli']; ?></td>
            <td><?= $d['total']; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// KARTU STOK
// ─────────────────────────────────────────────────────────────
if ($type === 'kartu_stok') {

    $id_obat   = $_GET['id_obat']   ?? null;
    $id_unit   = $_GET['id_unit']   ?? null;
    $tgl_awal  = $_GET['tgl_awal']  ?? date('Y-m-01');
    $tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-t');

    if (!$id_obat || !$id_unit) die('Parameter id_obat dan id_unit wajib diisi.');

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Kartu_Stok.xls");

    try {
        $stmt = $pdo->prepare("SELECT * FROM tbl_log_stok WHERE id_obat = ? AND id_unit = ? AND tgl_log BETWEEN ? AND ? ORDER BY tgl_log ASC");
        $stmt->execute([$id_obat, $id_unit, $tgl_awal . " 00:00:00", $tgl_akhir . " 23:59:59"]);
        $data = $stmt->fetchAll();
    } catch (PDOException $e) { die($e->getMessage()); }
    ?>
    <table border="1">
        <tr><th>Tanggal</th><th>Sumber</th><th>Keterangan</th><th>Masuk</th><th>Keluar</th><th>Sisa</th></tr>
        <?php foreach ($data as $d): ?>
        <tr>
            <td><?= htmlspecialchars($d['tgl_log']); ?></td>
            <td><?= htmlspecialchars($d['sumber_data']); ?></td>
            <td><?= htmlspecialchars($d['keterangan'] ?? '-'); ?></td>
            <td align="right"><?= $d['masuk']; ?></td>
            <td align="right"><?= $d['keluar']; ?></td>
            <td align="right"><?= $d['stok_sesudah']; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// KUNJUNGAN
// ─────────────────────────────────────────────────────────────
if ($type === 'kunjungan') {

    $filter_bulan = $_GET['bulan'] ?? date('Y-m');

    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Rekap_Kunjungan_" . $filter_bulan . ".xls");

    try {
        $stmt = $pdo->prepare("SELECT DATE(tgl_resep) as tanggal, SUM(CASE WHEN id_pelayanan = 1 THEN 1 ELSE 0 END) as UMUM, SUM(CASE WHEN id_pelayanan = 2 THEN 1 ELSE 0 END) as BPJS FROM tbl_resep_header WHERE DATE_FORMAT(tgl_resep, '%Y-%m') = ? GROUP BY DATE(tgl_resep) ORDER BY tanggal ASC");
        $stmt->execute([$filter_bulan]);
        $data = $stmt->fetchAll();
    } catch (PDOException $e) { die($e->getMessage()); }
    ?>
    <table border="1">
        <tr><th colspan="5" style="font-size:14pt;">LAPORAN REKAP KUNJUNGAN (<?= date('F Y', strtotime($filter_bulan)); ?>)</th></tr>
        <tr style="background-color:#f2f2f2;"><th>No</th><th>Tanggal</th><th>UMUM</th><th>BPJS</th><th>Total</th></tr>
        <?php $i = 1; foreach ($data as $d): $t = $d['UMUM'] + $d['BPJS']; ?>
        <tr>
            <td align="center"><?= $i++; ?></td>
            <td><?= date('d-m-Y', strtotime($d['tanggal'])); ?></td>
            <td align="right"><?= $d['UMUM']; ?></td>
            <td align="right"><?= $d['BPJS']; ?></td>
            <td align="right"><b><?= $t; ?></b></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// SASARAN MUTU
// ─────────────────────────────────────────────────────────────
if ($type === 'sm') {

    if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) exit('Akses Ditolak');

    $filter_bulan    = $_GET['bulan'] ?? date('Y-m');
    $id_poli_dipilih = isset($_GET['id_poli']) && $_GET['id_poli'] !== '' ? (int)$_GET['id_poli'] : null;

    // Ambil nama poli jika dipilih
    $nama_poli_sm = 'Semua Poli';
    if ($id_poli_dipilih) {
        try {
            $stmt_poli_sm = $pdo->prepare("SELECT nama_poli FROM tbl_poli WHERE id_poli = ?");
            $stmt_poli_sm->execute([$id_poli_dipilih]);
            $row_poli_sm = $stmt_poli_sm->fetch();
            if ($row_poli_sm) $nama_poli_sm = $row_poli_sm['nama_poli'];
        } catch (PDOException $e) { die($e->getMessage()); }
    }

    $nama_file_poli = $id_poli_dipilih ? '_' . preg_replace('/\s+/', '_', $nama_poli_sm) : '';
    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Laporan_Sasaran_Mutu" . $nama_file_poli . "_" . $filter_bulan . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    try {
        $where_poli_sm = $id_poli_dipilih ? "AND rh.id_poli = ?" : "";
        $where_poli_sub = $id_poli_dipilih ? "AND rh2.id_poli = ?" : "";
        $where_poli_sub3 = $id_poli_dipilih ? "AND rh3.id_poli = ?" : "";

        $sql  = "SELECT DATE(rh.tgl_resep) as tanggal, COUNT(rh.id_resep) as total_resep,
                    SUM(CASE WHEN rh.kelengkapan_resep = 'Lengkap' THEN 1 ELSE 0 END) as lengkap,
                    SUM(CASE WHEN rh.kesalahan_resep = 'Tidak Ada' THEN 1 ELSE 0 END) as tdk_salah,
                    SUM(CASE WHEN rh.sesuai_formularium = 'Sesuai' THEN 1 ELSE 0 END) as sesuai_form,
                    (SELECT COUNT(*) FROM tbl_resep_detail rd2 JOIN tbl_resep_header rh2 ON rd2.id_resep = rh2.id_resep WHERE DATE(rh2.tgl_resep) = DATE(rh.tgl_resep) AND rd2.jenis_racikan = 'Racikan' $where_poli_sub) as total_racikan,
                    (SELECT COUNT(*) FROM tbl_resep_detail rd3 JOIN tbl_resep_header rh3 ON rd3.id_resep = rh3.id_resep WHERE DATE(rh3.tgl_resep) = DATE(rh.tgl_resep) AND rd3.jenis_racikan = 'Non Racikan' $where_poli_sub3) as total_non_racikan
                 FROM tbl_resep_header rh
                 WHERE DATE_FORMAT(rh.tgl_resep, '%Y-%m') = ? $where_poli_sm
                 GROUP BY DATE(rh.tgl_resep) ORDER BY tanggal ASC";

        $params_sm = [$filter_bulan];
        if ($id_poli_dipilih) {
            // bind untuk subquery racikan, subquery non racikan, WHERE utama
            $params_sm = [$filter_bulan];
            // Subquery bind: masing-masing subquery butuh 1 param jika filter poli aktif
            // Gunakan teknik rebuild query agar urutan param benar
            $sql  = "SELECT DATE(rh.tgl_resep) as tanggal, COUNT(rh.id_resep) as total_resep,
                        SUM(CASE WHEN rh.kelengkapan_resep = 'Lengkap' THEN 1 ELSE 0 END) as lengkap,
                        SUM(CASE WHEN rh.kesalahan_resep = 'Tidak Ada' THEN 1 ELSE 0 END) as tdk_salah,
                        SUM(CASE WHEN rh.sesuai_formularium = 'Sesuai' THEN 1 ELSE 0 END) as sesuai_form,
                        (SELECT COUNT(*) FROM tbl_resep_detail rd2 JOIN tbl_resep_header rh2 ON rd2.id_resep = rh2.id_resep WHERE DATE(rh2.tgl_resep) = DATE(rh.tgl_resep) AND rd2.jenis_racikan = 'Racikan' AND rh2.id_poli = ?) as total_racikan,
                        (SELECT COUNT(*) FROM tbl_resep_detail rd3 JOIN tbl_resep_header rh3 ON rd3.id_resep = rh3.id_resep WHERE DATE(rh3.tgl_resep) = DATE(rh.tgl_resep) AND rd3.jenis_racikan = 'Non Racikan' AND rh3.id_poli = ?) as total_non_racikan
                     FROM tbl_resep_header rh
                     WHERE DATE_FORMAT(rh.tgl_resep, '%Y-%m') = ? AND rh.id_poli = ?
                     GROUP BY DATE(rh.tgl_resep) ORDER BY tanggal ASC";
            $params_sm = [$id_poli_dipilih, $id_poli_dipilih, $filter_bulan, $id_poli_dipilih];
        } else {
            $sql  = "SELECT DATE(rh.tgl_resep) as tanggal, COUNT(rh.id_resep) as total_resep,
                        SUM(CASE WHEN rh.kelengkapan_resep = 'Lengkap' THEN 1 ELSE 0 END) as lengkap,
                        SUM(CASE WHEN rh.kesalahan_resep = 'Tidak Ada' THEN 1 ELSE 0 END) as tdk_salah,
                        SUM(CASE WHEN rh.sesuai_formularium = 'Sesuai' THEN 1 ELSE 0 END) as sesuai_form,
                        (SELECT COUNT(*) FROM tbl_resep_detail rd2 JOIN tbl_resep_header rh2 ON rd2.id_resep = rh2.id_resep WHERE DATE(rh2.tgl_resep) = DATE(rh.tgl_resep) AND rd2.jenis_racikan = 'Racikan') as total_racikan,
                        (SELECT COUNT(*) FROM tbl_resep_detail rd3 JOIN tbl_resep_header rh3 ON rd3.id_resep = rh3.id_resep WHERE DATE(rh3.tgl_resep) = DATE(rh.tgl_resep) AND rd3.jenis_racikan = 'Non Racikan') as total_non_racikan
                     FROM tbl_resep_header rh
                     WHERE DATE_FORMAT(rh.tgl_resep, '%Y-%m') = ?
                     GROUP BY DATE(rh.tgl_resep) ORDER BY tanggal ASC";
            $params_sm = [$filter_bulan];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params_sm);
        $data_sm = $stmt->fetchAll();
    } catch (PDOException $e) { die("Error: " . $e->getMessage()); }

    $judul_poli_sm = $id_poli_dipilih ? strtoupper($nama_poli_sm) : 'SEMUA POLI';
    ?>
    <table border="1">
        <tr><th colspan="14" style="font-size:14pt;text-align:center;">LAPORAN SASARAN MUTU — <?= htmlspecialchars($judul_poli_sm) ?> (<?= date('F Y', strtotime($filter_bulan . '-01')); ?>)</th></tr>
        <tr style="background-color:#eee;font-weight:bold;text-align:center;">
            <th rowspan="2">No</th><th rowspan="2">Tanggal</th>
            <th colspan="3">Kelengkapan Resep</th>
            <th colspan="3">Kesalahan Resep</th>
            <th colspan="3">Sesuai Formularium</th>
            <th colspan="3">Racikan / Non Racikan</th>
        </tr>
        <tr style="background-color:#eee;font-weight:bold;text-align:center;">
            <th>Lengkap</th><th>Tdk</th><th>%</th>
            <th>Ada</th><th>Tdk</th><th>%</th>
            <th>Sesuai</th><th>Tdk</th><th>%</th>
            <th>Racik</th><th>Non</th><th>% Non</th>
        </tr>
        <?php $no = 1; foreach ($data_sm as $row):
            $total      = $row['total_resep'];
            $item_total = $row['total_racikan'] + $row['total_non_racikan'];
            $p_lengkap   = $total > 0 ? ($row['lengkap']      / $total) * 100 : 0;
            $p_tdk_salah = $total > 0 ? ($row['tdk_salah']    / $total) * 100 : 0;
            $p_sesuai    = $total > 0 ? ($row['sesuai_form']  / $total) * 100 : 0;
            $p_non_racik = $item_total > 0 ? ($row['total_non_racikan'] / $item_total) * 100 : 0;
        ?>
        <tr>
            <td style="text-align:center;"><?= $no++; ?></td>
            <td style="text-align:center;"><?= date('d-m-Y', strtotime($row['tanggal'])); ?></td>
            <td style="text-align:right;"><?= $row['lengkap']; ?></td>
            <td style="text-align:right;"><?= $total - $row['lengkap']; ?></td>
            <td style="text-align:right;"><?= number_format($p_lengkap, 1); ?>%</td>
            <td style="text-align:right;"><?= $total - $row['tdk_salah']; ?></td>
            <td style="text-align:right;"><?= $row['tdk_salah']; ?></td>
            <td style="text-align:right;"><?= number_format($p_tdk_salah, 1); ?>%</td>
            <td style="text-align:right;"><?= $row['sesuai_form']; ?></td>
            <td style="text-align:right;"><?= $total - $row['sesuai_form']; ?></td>
            <td style="text-align:right;"><?= number_format($p_sesuai, 1); ?>%</td>
            <td style="text-align:right;"><?= $row['total_racikan']; ?></td>
            <td style="text-align:right;"><?= $row['total_non_racikan']; ?></td>
            <td style="text-align:right;"><?= number_format($p_non_racik, 1); ?>%</td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// BULANAN POLI
// ─────────────────────────────────────────────────────────────
if ($type === 'bulanan_poli') {

    $filter_bulan    = $_GET['bulan']   ?? date('Y-m');
    $id_poli_dipilih = isset($_GET['id_poli']) && $_GET['id_poli'] !== '' ? (int)$_GET['id_poli'] : null;

    if (!$id_poli_dipilih) die('Parameter id_poli wajib diisi.');

    // Ambil info poli
    try {
        $stmt_poli = $pdo->prepare("SELECT nama_poli, id_unit_stok_default FROM tbl_poli WHERE id_poli = ?");
        $stmt_poli->execute([$id_poli_dipilih]);
        $poli_row = $stmt_poli->fetch();
    } catch (PDOException $e) { die($e->getMessage()); }

    if (!$poli_row) die('Poli tidak ditemukan.');

    $nama_poli  = $poli_row['nama_poli'];
    $id_unit    = $poli_row['id_unit_stok_default'];

    $tgl_awal_bulan  = $filter_bulan . "-01 00:00:00";
    $tgl_akhir_bulan = date('Y-m-t 23:59:59', strtotime($tgl_awal_bulan));

    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Laporan_Bulanan_Poli_" . preg_replace('/\s+/', '_', $nama_poli) . "_" . $filter_bulan . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    try {
        // Semua obat
        $stmt_obat = $pdo->query("SELECT id_obat, kode_obat, nama_obat FROM tbl_obat ORDER BY nama_obat ASC");
        $obat_list = $stmt_obat->fetchAll();

        // Stok awal (akumulasi sebelum bulan ini)
        $stmt_sa = $pdo->prepare("SELECT id_obat, SUM(masuk) - SUM(keluar) AS stok_awal FROM tbl_log_stok WHERE id_unit = ? AND tgl_log < ? GROUP BY id_obat");
        $stmt_sa->execute([$id_unit, $tgl_awal_bulan]);
        $stok_awal_map = [];
        foreach ($stmt_sa->fetchAll() as $r) $stok_awal_map[$r['id_obat']] = (int)$r['stok_awal'];

        // Masuk bulan ini (Transfer / Stok Opname)
        $stmt_mk = $pdo->prepare("SELECT id_obat, SUM(masuk) AS total_masuk FROM tbl_log_stok WHERE id_unit = ? AND sumber_data IN ('Transfer','Stok Opname') AND masuk > 0 AND tgl_log BETWEEN ? AND ? GROUP BY id_obat");
        $stmt_mk->execute([$id_unit, $tgl_awal_bulan, $tgl_akhir_bulan]);
        $masuk_map = [];
        foreach ($stmt_mk->fetchAll() as $r) $masuk_map[$r['id_obat']] = (int)$r['total_masuk'];

        // Keluar bulan ini (pemakaian resep dari poli ini)
        $stmt_kl = $pdo->prepare("SELECT rd.id_obat, SUM(rd.jumlah_keluar) AS total_keluar FROM tbl_resep_detail rd JOIN tbl_resep_header rh ON rd.id_resep = rh.id_resep WHERE rh.id_poli = ? AND rd.id_unit_asal = ? AND rh.tgl_resep BETWEEN ? AND ? GROUP BY rd.id_obat");
        $stmt_kl->execute([$id_poli_dipilih, $id_unit, $tgl_awal_bulan, $tgl_akhir_bulan]);
        $keluar_map = [];
        foreach ($stmt_kl->fetchAll() as $r) $keluar_map[$r['id_obat']] = (int)$r['total_keluar'];

    } catch (PDOException $e) { die("Error: " . $e->getMessage()); }

    $grand_sa = $grand_mk = $grand_kl = $grand_akhir = 0;
    ?>
    <table border="1">
        <tr><th colspan="7" style="font-size:14pt;text-align:center;">LAPORAN BULANAN POLI <?= strtoupper(htmlspecialchars($nama_poli)) ?></th></tr>
        <tr><th colspan="7" style="text-align:center;">Periode: <?= date('F Y', strtotime($tgl_awal_bulan)) ?></th></tr>
        <tr style="background-color:#f2f2f2;font-weight:bold;text-align:center;">
            <th>No</th>
            <th>Kode Obat</th>
            <th>Nama Obat</th>
            <th>Stok Awal</th>
            <th>Masuk (Transfer/Stok Opname)</th>
            <th>Keluar (Pemakaian Resep)</th>
            <th>Stok Akhir</th>
        </tr>
        <?php
        $no = 1;
        foreach ($obat_list as $obat):
            $id_obat    = $obat['id_obat'];
            $stok_awal  = $stok_awal_map[$id_obat] ?? 0;
            $masuk      = $masuk_map[$id_obat]      ?? 0;
            $keluar     = $keluar_map[$id_obat]      ?? 0;
            $stok_akhir = $stok_awal + $masuk - $keluar;

            if ($stok_awal == 0 && $masuk == 0 && $keluar == 0) continue;

            $grand_sa    += $stok_awal;
            $grand_mk    += $masuk;
            $grand_kl    += $keluar;
            $grand_akhir += $stok_akhir;
        ?>
        <tr>
            <td align="center"><?= $no++ ?></td>
            <td><?= htmlspecialchars($obat['kode_obat']) ?></td>
            <td><?= htmlspecialchars($obat['nama_obat']) ?></td>
            <td align="right"><?= number_format($stok_awal) ?></td>
            <td align="right"><?= ($masuk > 0 ? '+' . number_format($masuk) : '0') ?></td>
            <td align="right"><?= ($keluar > 0 ? '-' . number_format($keluar) : '0') ?></td>
            <td align="right"><b><?= number_format($stok_akhir) ?></b></td>
        </tr>
        <?php endforeach; ?>
        <tr style="background-color:#6c757d;color:#fff;font-weight:bold;">
            <td colspan="3" align="right">GRAND TOTAL</td>
            <td align="right"><?= number_format($grand_sa) ?></td>
            <td align="right">+<?= number_format($grand_mk) ?></td>
            <td align="right">-<?= number_format($grand_kl) ?></td>
            <td align="right"><b><?= number_format($grand_akhir) ?></b></td>
        </tr>
    </table>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// KELUAR MASUK STOK (dari keluar_masuk_polidepan / polibelakang)
// ─────────────────────────────────────────────────────────────
if ($type === 'keluar_masuk_stok') {

    $tgl_awal  = $_GET['tgl_awal']  ?? date('Y-m-01');
    $tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
    $sumber    = $_GET['sumber']    ?? '';
    $id_unit   = isset($_GET['id_unit']) ? (int)$_GET['id_unit'] : null;
    $nama_unit = $_GET['nama_unit'] ?? 'Unit';

    if (!$id_unit) die('Parameter id_unit wajib diisi.');

    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Keluar_Masuk_Stok_" . preg_replace('/\s+/', '_', $nama_unit) . "_" . $tgl_awal . "_sd_" . $tgl_akhir . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    try {
        $query = "SELECT ls.tgl_log, o.kode_obat, o.nama_obat, o.satuan,
                         ls.sumber_data, ls.keterangan, ls.masuk, ls.keluar, ls.stok_sesudah
                  FROM tbl_log_stok ls
                  JOIN tbl_obat o ON ls.id_obat = o.id_obat
                  WHERE ls.id_unit = ?
                    AND ls.tgl_log BETWEEN ? AND ?";
        $params = [$id_unit, $tgl_awal . ' 00:00:00', $tgl_akhir . ' 23:59:59'];
        if (!empty($sumber)) { $query .= " AND ls.sumber_data = ?"; $params[] = $sumber; }
        $query .= " ORDER BY ls.tgl_log ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();
    } catch (PDOException $e) { die($e->getMessage()); }

    $total_masuk = array_sum(array_column($logs, 'masuk'));
    $total_keluar = array_sum(array_column($logs, 'keluar'));
    ?>
    <table border="1">
        <tr><th colspan="9" style="font-size:14pt;text-align:center;">LAPORAN KELUAR MASUK STOK — <?= htmlspecialchars($nama_unit) ?></th></tr>
        <tr><th colspan="9" style="text-align:center;">Periode: <?= date('d-m-Y', strtotime($tgl_awal)) ?> s/d <?= date('d-m-Y', strtotime($tgl_akhir)) ?><?= $sumber ? ' | Sumber: ' . htmlspecialchars($sumber) : '' ?></th></tr>
        <tr style="background-color:#f2f2f2;font-weight:bold;text-align:center;">
            <th>No</th><th>Tanggal</th><th>Kode Obat</th><th>Nama Obat</th>
            <th>Sumber</th><th>Keterangan</th><th>Masuk</th><th>Keluar</th><th>Sisa Stok</th>
        </tr>
        <?php foreach ($logs as $i => $lg): ?>
        <tr>
            <td align="center"><?= $i + 1 ?></td>
            <td align="center"><?= date('d-m-Y H:i', strtotime($lg['tgl_log'])) ?></td>
            <td align="center"><?= htmlspecialchars($lg['kode_obat']) ?></td>
            <td><?= htmlspecialchars($lg['nama_obat']) ?></td>
            <td align="center"><?= htmlspecialchars($lg['sumber_data']) ?></td>
            <td><?= htmlspecialchars($lg['keterangan'] ?? '-') ?></td>
            <td align="right"><?= $lg['masuk'] > 0 ? number_format($lg['masuk']) : '-' ?></td>
            <td align="right"><?= $lg['keluar'] > 0 ? number_format($lg['keluar']) : '-' ?></td>
            <td align="right"><?= number_format($lg['stok_sesudah']) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="background-color:#f2f2f2;font-weight:bold;">
            <td colspan="6" align="right">TOTAL</td>
            <td align="right"><?= number_format($total_masuk) ?></td>
            <td align="right"><?= number_format($total_keluar) ?></td>
            <td></td>
        </tr>
    </table>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// HISTORY DOKTER (dari keluar_masuk_polidepan / polibelakang)
// ─────────────────────────────────────────────────────────────
if ($type === 'history_dokter') {

    $tgl_awal   = $_GET['tgl_awal']  ?? date('Y-m-01');
    $tgl_akhir  = $_GET['tgl_akhir'] ?? date('Y-m-d');
    $id_dokter  = $_GET['id_dokter'] ?? '';
    $id_unit    = isset($_GET['id_unit']) ? (int)$_GET['id_unit'] : null;
    $nama_unit  = $_GET['nama_unit'] ?? 'Unit';

    if (!$id_unit) die('Parameter id_unit wajib diisi.');

    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=History_Dokter_" . preg_replace('/\s+/', '_', $nama_unit) . "_" . $tgl_awal . "_sd_" . $tgl_akhir . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    try {
        $query = "SELECT rh.id_resep, rh.tgl_resep, d.nama_dokter, o.kode_obat, o.nama_obat,
                         rd.jumlah_keluar, rd.jenis_racikan, p.nama_poli
                  FROM tbl_resep_header rh
                  JOIN tbl_dokter d       ON rh.id_dokter  = d.id_dokter
                  JOIN tbl_resep_detail rd ON rh.id_resep   = rd.id_resep
                  JOIN tbl_obat o          ON rd.id_obat    = o.id_obat
                  JOIN tbl_poli p          ON rh.id_poli    = p.id_poli
                  WHERE p.id_unit_stok_default = ?
                    AND rh.tgl_resep BETWEEN ? AND ?";
        $params = [$id_unit, $tgl_awal . ' 00:00:00', $tgl_akhir . ' 23:59:59'];
        if (!empty($id_dokter)) { $query .= " AND d.id_dokter = ?"; $params[] = $id_dokter; }
        $query .= " ORDER BY rh.tgl_resep DESC, d.nama_dokter ASC, o.nama_obat ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        // Nama dokter filter (untuk judul)
        $nama_dokter_filter = 'Semua Dokter';
        if (!empty($id_dokter)) {
            $stmt_d = $pdo->prepare("SELECT nama_dokter FROM tbl_dokter WHERE id_dokter = ?");
            $stmt_d->execute([$id_dokter]);
            $row_d = $stmt_d->fetch();
            if ($row_d) $nama_dokter_filter = $row_d['nama_dokter'];
        }

        // Rekap per dokter
        $rekap = [];
        foreach ($logs as $ld) {
            $key = $ld['nama_dokter'];
            if (!isset($rekap[$key])) $rekap[$key] = ['resep' => [], 'item' => 0, 'qty' => 0];
            $rekap[$key]['resep'][$ld['id_resep']] = true;
            $rekap[$key]['item']++;
            $rekap[$key]['qty'] += $ld['jumlah_keluar'];
        }
    } catch (PDOException $e) { die($e->getMessage()); }

    $total_qty = array_sum(array_column($logs, 'jumlah_keluar'));
    ?>
    <table border="1">
        <tr><th colspan="9" style="font-size:14pt;text-align:center;">HISTORY RESEP DOKTER — <?= htmlspecialchars($nama_unit) ?></th></tr>
        <tr><th colspan="9" style="text-align:center;">Periode: <?= date('d-m-Y', strtotime($tgl_awal)) ?> s/d <?= date('d-m-Y', strtotime($tgl_akhir)) ?> | Dokter: <?= htmlspecialchars($nama_dokter_filter) ?></th></tr>
        <tr style="background-color:#f2f2f2;font-weight:bold;text-align:center;">
            <th>No</th><th>Tanggal</th><th>No. Resep</th><th>Nama Dokter</th>
            <th>Poli</th><th>Kode Obat</th><th>Nama Obat</th><th>Jenis</th><th>Qty</th>
        </tr>
        <?php foreach ($logs as $i => $ld): ?>
        <tr>
            <td align="center"><?= $i + 1 ?></td>
            <td align="center"><?= date('d-m-Y H:i', strtotime($ld['tgl_resep'])) ?></td>
            <td align="center">#<?= $ld['id_resep'] ?></td>
            <td><?= htmlspecialchars($ld['nama_dokter']) ?></td>
            <td align="center"><?= htmlspecialchars($ld['nama_poli']) ?></td>
            <td align="center"><?= htmlspecialchars($ld['kode_obat']) ?></td>
            <td><?= htmlspecialchars($ld['nama_obat']) ?></td>
            <td align="center"><?= htmlspecialchars($ld['jenis_racikan']) ?></td>
            <td align="right"><?= number_format($ld['jumlah_keluar']) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="background-color:#f2f2f2;font-weight:bold;">
            <td colspan="8" align="right">TOTAL QTY</td>
            <td align="right"><?= number_format($total_qty) ?></td>
        </tr>
    </table>
    <br>
    <table border="1">
        <tr><th colspan="5" style="font-weight:bold;background-color:#f2f2f2;text-align:center;">REKAP PER DOKTER</th></tr>
        <tr style="background-color:#f2f2f2;font-weight:bold;text-align:center;">
            <th>No</th><th>Nama Dokter</th><th>Jml Resep</th><th>Jml Item</th><th>Total Qty</th>
        </tr>
        <?php $no = 1; foreach ($rekap as $nama_dr => $rek): ?>
        <tr>
            <td align="center"><?= $no++ ?></td>
            <td><?= htmlspecialchars($nama_dr) ?></td>
            <td align="center"><?= count($rek['resep']) ?></td>
            <td align="center"><?= number_format($rek['item']) ?></td>
            <td align="right"><?= number_format($rek['qty']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php
    exit;
}

// Fallback jika type tidak dikenali
die('Tipe laporan "' . htmlspecialchars($type) . '" tidak dikenali.');