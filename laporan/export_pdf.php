<?php


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

    $filter_bulan = $_GET['bulan'] ?? date('Y-m');
    $tgl_awal     = $filter_bulan . "-01 00:00:00";
    $tgl_akhir    = date('Y-m-t 23:59:59', strtotime($tgl_awal));

    try {
        $stmt_p      = $pdo->query("SELECT id_poli, nama_poli FROM tbl_poli WHERE id_unit_stok_default = 2 ORDER BY id_poli ASC");
        $polis_depan = $stmt_p->fetchAll();

        $sql  = "SELECT o.id_obat, o.kode_obat, o.nama_obat,
                 (SELECT stok_sesudah FROM tbl_log_stok WHERE id_obat = o.id_obat AND id_unit = 1 AND tgl_log < :t1 ORDER BY tgl_log DESC, id_log DESC LIMIT 1) as stok_awal,
                 (SELECT SUM(masuk) FROM tbl_log_stok WHERE id_obat = o.id_obat AND id_unit = 1 AND tgl_log BETWEEN :t2 AND :t3) as total_masuk,
                 (SELECT SUM(rd.jumlah_keluar) FROM tbl_resep_detail rd JOIN tbl_resep_header rh ON rd.id_resep = rh.id_resep JOIN tbl_poli pb ON rh.id_poli = pb.id_poli WHERE rd.id_obat = o.id_obat AND pb.id_unit_stok_default != 2 AND rh.tgl_resep BETWEEN :t4 AND :t5) as poli_belakang
                 FROM tbl_obat o ORDER BY o.nama_obat ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['t1' => $tgl_awal, 't2' => $tgl_awal, 't3' => $tgl_akhir, 't4' => $tgl_awal, 't5' => $tgl_akhir]);
        $data_bulanan = $stmt->fetchAll();

        $data_resep = [];
        $stmt_r     = $pdo->prepare("SELECT rd.id_obat, rh.id_poli, SUM(rd.jumlah_keluar) as jml FROM tbl_resep_detail rd JOIN tbl_resep_header rh ON rd.id_resep = rh.id_resep WHERE rh.tgl_resep BETWEEN ? AND ? GROUP BY rd.id_obat, rh.id_poli");
        $stmt_r->execute([$tgl_awal, $tgl_akhir]);
        while ($row = $stmt_r->fetch()) {
            $data_resep[$row['id_obat']][$row['id_poli']] = $row['jml'];
        }
    } catch (PDOException $e) { die("Error: " . $e->getMessage()); }
    ?>
    <!DOCTYPE html><html><head>
    <title>Cetak Laporan Bulanan - <?= $filter_bulan ?></title>
    <style>
        @page { size: landscape; margin: 1cm; }
        body { font-family: sans-serif; font-size: 9px; line-height: 1.2; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid black; padding: 4px; }
        th { background-color: #eee; text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        h3, p { text-align: center; margin: 2px; }
    </style>
    </head>
    <body onload="window.print()">
    <h3>REKAP STOK & PEMAKAIAN BULANAN GUDANG</h3>
    <p>Periode: <?= date('F Y', strtotime($tgl_awal)); ?></p>
    <table>
        <thead>
            <tr>
                <th rowspan="2">Kode</th><th rowspan="2">Nama Obat</th>
                <th rowspan="2">Awal</th><th rowspan="2">Masuk</th>
                <th colspan="<?= count($polis_depan) ?>">Keluar (Apotek)</th>
                <th rowspan="2">Belakang</th><th rowspan="2">Sisa</th>
            </tr>
            <tr>
                <?php foreach ($polis_depan as $p): ?>
                    <th><?= htmlspecialchars($p['nama_poli']); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($data_bulanan as $row):
            $total_k        = $row['poli_belakang'] ?? 0;
            $row_html_poli  = "";
            foreach ($polis_depan as $p) {
                $val = $data_resep[$row['id_obat']][$p['id_poli']] ?? 0;
                $row_html_poli .= "<td class='text-right'>" . ($val ?: 0) . "</td>";
                $total_k += $val;
            }
            $awal  = $row['stok_awal']    ?? 0;
            $masuk = $row['total_masuk']  ?? 0;
            $sisa  = $awal + $masuk - $total_k;
            if (($awal + $masuk + $total_k) > 0): ?>
            <tr>
                <td class="text-center"><?= $row['kode_obat'] ?></td>
                <td><?= htmlspecialchars($row['nama_obat']) ?></td>
                <td class="text-right"><?= number_format($awal) ?></td>
                <td class="text-right"><?= number_format($masuk) ?></td>
                <?= $row_html_poli ?>
                <td class="text-right"><?= number_format($row['poli_belakang'] ?? 0) ?></td>
                <td class="text-right"><strong><?= number_format($sisa) ?></strong></td>
            </tr>
            <?php endif; endforeach; ?>
        </tbody>
    </table>
    <div style="margin-top:20px;font-style:italic;">Dicetak pada: <?= date('d/m/Y H:i:s') ?></div>
    </body></html>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// HARIAN — VENDOR → GUDANG
// ─────────────────────────────────────────────────────────────
if ($type === 'harian_vendor_gudang') {

    $tgl_awal  = $_GET['tgl_awal']  ?? date('Y-m-d');
    $tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

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
    <!DOCTYPE html><html><head>
    <title>Penerimaan Harian: Vendor → Gudang</title>
    <style>
        @page { size: portrait; margin: 1.5cm; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #333; }
        h3, .sub { text-align: center; margin: 2px 0; }
        h3 { font-size: 13px; text-transform: uppercase; }
        .sub { font-size: 11px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #555; padding: 5px 6px; }
        thead th { background-color: #343a40; color: #fff; text-align: center; }
        tfoot td { background-color: #6c757d; color: #fff; font-weight: bold; }
        .tc { text-align: center; } .tr { text-align: right; }
        .footer { margin-top: 16px; font-size: 9px; font-style: italic; text-align: right; }
    </style>
    </head>
    <body onload="window.print()">
    <h3>Penerimaan Harian: Vendor &rarr; Gudang</h3>
    <p class="sub">Periode: <?= date('d-m-Y', strtotime($tgl_awal)) ?> s/d <?= date('d-m-Y', strtotime($tgl_akhir)) ?></p>
    <table>
        <thead>
            <tr>
                <th>Kode Obat</th><th style="text-align:left;">Nama Obat</th>
                <th>Satuan</th><th>No. Faktur / Keterangan</th><th>Jumlah Masuk</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($data)): ?>
            <tr><td colspan="5" style="text-align:center;">Tidak ada penerimaan dari vendor pada periode ini.</td></tr>
        <?php else: ?>
            <?php foreach ($data as $r): ?>
            <tr>
                <td class="tc"><?= htmlspecialchars($r['kode_obat']) ?></td>
                <td><?= htmlspecialchars($r['nama_obat']) ?></td>
                <td class="tc"><?= htmlspecialchars($r['satuan']) ?></td>
                <td><?= htmlspecialchars($r['no_faktur'] ?: '-') ?></td>
                <td class="tr" style="color:#155724;font-weight:bold;"><?= number_format($r['total_masuk']) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="tr">GRAND TOTAL</td>
                <td class="tr"><?= number_format($total_masuk) ?></td>
            </tr>
        </tfoot>
    </table>
    <div class="footer">Dicetak pada: <?= date('d/m/Y H:i:s') ?></div>
    </body></html>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// HARIAN — APOTEK → POLI
// ─────────────────────────────────────────────────────────────
if ($type === 'harian_apotek_poli') {

    $tgl_awal  = $_GET['tgl_awal']  ?? date('Y-m-d');
    $tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

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
    <!DOCTYPE html><html><head>
    <title>Distribusi Harian: Apotek → Poli</title>
    <style>
        @page { size: portrait; margin: 1.5cm; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #333; }
        h3, .sub { text-align: center; margin: 2px 0; }
        h3 { font-size: 13px; text-transform: uppercase; }
        .sub { font-size: 11px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #555; padding: 5px 6px; }
        thead th { background-color: #343a40; color: #fff; text-align: center; }
        tfoot td { background-color: #6c757d; color: #fff; font-weight: bold; }
        .tc { text-align: center; } .tr { text-align: right; }
        .footer { margin-top: 16px; font-size: 9px; font-style: italic; text-align: right; }
    </style>
    </head>
    <body onload="window.print()">
    <h3>Distribusi Harian: Apotek &rarr; Poli</h3>
    <p class="sub">Periode: <?= date('d-m-Y', strtotime($tgl_awal)) ?> s/d <?= date('d-m-Y', strtotime($tgl_akhir)) ?></p>
    <table>
        <thead>
            <tr>
                <th>Kode Obat</th><th style="text-align:left;">Nama Obat</th>
                <th>Satuan</th><th>Tujuan Poli</th><th>Jumlah Keluar</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($data)): ?>
            <tr><td colspan="5" style="text-align:center;">Tidak ada distribusi Apotek &rarr; Poli pada periode ini.</td></tr>
        <?php else: ?>
            <?php foreach ($data as $r): ?>
            <tr>
                <td class="tc"><?= htmlspecialchars($r['kode_obat']) ?></td>
                <td><?= htmlspecialchars($r['nama_obat']) ?></td>
                <td class="tc"><?= htmlspecialchars($r['satuan']) ?></td>
                <td><?= htmlspecialchars($r['nama_tujuan']) ?></td>
                <td class="tr" style="color:#721c24;font-weight:bold;"><?= number_format($r['total_keluar']) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="tr">GRAND TOTAL</td>
                <td class="tr"><?= number_format($total_keluar) ?></td>
            </tr>
        </tfoot>
    </table>
    <div class="footer">Dicetak pada: <?= date('d/m/Y H:i:s') ?></div>
    </body></html>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// BULANAN — VENDOR → GUDANG
// ─────────────────────────────────────────────────────────────
if ($type === 'bulanan_vendor_gudang') {

    $filter_bulan = $_GET['bulan'] ?? date('Y-m');
    $tgl_awal     = $filter_bulan . "-01";
    $tgl_akhir    = date('Y-m-t', strtotime($tgl_awal));

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
    <!DOCTYPE html><html><head>
    <title>Penerimaan Bulanan: Vendor → Gudang - <?= $filter_bulan ?></title>
    <style>
        @page { size: portrait; margin: 1.5cm; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #333; }
        h3, .sub { text-align: center; margin: 2px 0; }
        h3 { font-size: 13px; text-transform: uppercase; }
        .sub { font-size: 11px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #555; padding: 5px 6px; }
        thead th { background-color: #343a40; color: #fff; text-align: center; }
        tfoot td { background-color: #6c757d; color: #fff; font-weight: bold; }
        .tc { text-align: center; } .tr { text-align: right; }
        .footer { margin-top: 16px; font-size: 9px; font-style: italic; text-align: right; }
    </style>
    </head>
    <body onload="window.print()">
    <h3>Penerimaan Bulanan: Vendor &rarr; Gudang</h3>
    <p class="sub">Periode: <?= date('F Y', strtotime($tgl_awal)) ?></p>
    <table>
        <thead>
            <tr>
                <th>Kode Obat</th><th style="text-align:left;">Nama Obat</th>
                <th>Satuan</th><th>No. Faktur / Keterangan</th><th>Jumlah Masuk</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($data)): ?>
            <tr><td colspan="5" style="text-align:center;">Tidak ada penerimaan dari vendor pada bulan ini.</td></tr>
        <?php else: ?>
            <?php foreach ($data as $r): ?>
            <tr>
                <td class="tc"><?= htmlspecialchars($r['kode_obat']) ?></td>
                <td><?= htmlspecialchars($r['nama_obat']) ?></td>
                <td class="tc"><?= htmlspecialchars($r['satuan']) ?></td>
                <td><?= htmlspecialchars($r['no_faktur'] ?: '-') ?></td>
                <td class="tr" style="color:#155724;font-weight:bold;"><?= number_format($r['total_masuk']) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="tr">GRAND TOTAL</td>
                <td class="tr"><?= number_format($total_masuk) ?></td>
            </tr>
        </tfoot>
    </table>
    <div class="footer">Dicetak pada: <?= date('d/m/Y H:i:s') ?></div>
    </body></html>
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
    $rows_html = '';
    $no = 1;
    foreach ($obat_list as $obat) {
        $id = $obat['id_obat'];
        $m  = $masuk_map[$id]  ?? 0;
        $k  = $keluar_map[$id] ?? 0;
        $s  = $stok_map[$id]   ?? 0;
        $grand_masuk  += $m;
        $grand_keluar += $k;
        $grand_stok   += $s;
        $warna_stok = $s == 0 ? 'color:red;' : ($s <= 10 ? 'color:#856404;' : '');
        $rows_html .= "<tr>
            <td class='tc'>{$no}</td>
            <td class='tc'>" . htmlspecialchars($obat['kode_obat']) . "</td>
            <td>" . htmlspecialchars($obat['nama_obat']) . "</td>
            <td class='tc'>" . htmlspecialchars($obat['satuan']) . "</td>
            <td class='tr' style='color:#155724;'>" . ($m > 0 ? number_format($m) : '&mdash;') . "</td>
            <td class='tr' style='color:#721c24;'>" . ($k > 0 ? number_format($k) : '&mdash;') . "</td>
            <td class='tr' style='font-weight:bold;{$warna_stok}'>" . number_format($s) . "</td>
        </tr>";
        $no++;
    }
    ?>
    <!DOCTYPE html><html><head>
    <title>Rekap Stok Apotek Bulanan — <?= $filter_bulan ?></title>
    <style>
        @page { size: portrait; margin: 1.5cm; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #333; }
        h3, .sub { text-align: center; margin: 2px 0; }
        h3 { font-size: 13px; text-transform: uppercase; }
        .sub { font-size: 11px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #555; padding: 5px 6px; }
        thead th { background-color: #343a40; color: #fff; text-align: center; }
        tfoot td { background-color: #6c757d; color: #fff; font-weight: bold; }
        .tc { text-align: center; } .tr { text-align: right; }
        .footer { margin-top: 16px; font-size: 9px; font-style: italic; text-align: right; }
    </style>
    </head>
    <body onload="window.print()">
    <h3>Rekap Stok Apotek Bulanan — Masuk, Keluar &amp; Stok Akhir</h3>
    <p class="sub">Periode: <?= date('F Y', strtotime($tgl_awal)) ?></p>
    <table>
        <thead>
            <tr>
                <th style="width:30px;">No</th><th style="width:80px;">Kode Obat</th>
                <th>Nama Obat</th><th style="width:55px;">Satuan</th>
                <th style="width:90px;">Masuk<br><small>(dari Gudang)</small></th>
                <th style="width:90px;">Keluar<br><small>(ke Poli)</small></th>
                <th style="width:90px;">Stok Akhir<br><small>(Apotek)</small></th>
            </tr>
        </thead>
        <tbody>
            <?= $rows_html ?: '<tr><td colspan="7" style="text-align:center;">Tidak ada data stok apotek pada periode ini.</td></tr>' ?>
        </tbody>
        <?php if ($no > 1): ?>
        <tfoot>
            <tr>
                <td colspan="4" class="tr">GRAND TOTAL</td>
                <td class="tr"><?= number_format($grand_masuk) ?></td>
                <td class="tr"><?= number_format($grand_keluar) ?></td>
                <td class="tr"><?= number_format($grand_stok) ?></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
    <div class="footer">Dicetak pada: <?= date('d/m/Y H:i:s') ?></div>
    </body></html>
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
    <!DOCTYPE html><html><head>
    <title>Distribusi Bulanan: Apotek → Poli - <?= $filter_bulan ?></title>
    <style>
        @page { size: portrait; margin: 1.5cm; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #333; }
        h3, .sub { text-align: center; margin: 2px 0; }
        h3 { font-size: 13px; text-transform: uppercase; }
        .sub { font-size: 11px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #555; padding: 5px 6px; }
        thead th { background-color: #343a40; color: #fff; text-align: center; }
        tfoot td { background-color: #6c757d; color: #fff; font-weight: bold; }
        .tc { text-align: center; } .tr { text-align: right; }
        .footer { margin-top: 16px; font-size: 9px; font-style: italic; text-align: right; }
    </style>
    </head>
    <body onload="window.print()">
    <h3>Distribusi Bulanan: Apotek &rarr; Poli</h3>
    <p class="sub">Periode: <?= date('F Y', strtotime($tgl_awal)) ?></p>
    <table>
        <thead>
            <tr>
                <th>Kode Obat</th><th style="text-align:left;">Nama Obat</th>
                <th>Satuan</th><th>Tujuan Poli</th><th>Jumlah Keluar</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($data)): ?>
            <tr><td colspan="5" style="text-align:center;">Tidak ada distribusi Apotek &rarr; Poli pada bulan ini.</td></tr>
        <?php else: ?>
            <?php foreach ($data as $r): ?>
            <tr>
                <td class="tc"><?= htmlspecialchars($r['kode_obat']) ?></td>
                <td><?= htmlspecialchars($r['nama_obat']) ?></td>
                <td class="tc"><?= htmlspecialchars($r['satuan']) ?></td>
                <td><?= htmlspecialchars($r['nama_tujuan']) ?></td>
                <td class="tr" style="color:#721c24;font-weight:bold;"><?= number_format($r['total_keluar']) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="tr">GRAND TOTAL</td>
                <td class="tr"><?= number_format($total_keluar) ?></td>
            </tr>
        </tfoot>
    </table>
    <div class="footer">Dicetak pada: <?= date('d/m/Y H:i:s') ?></div>
    </body></html>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// HARIAN — REKAP STOK APOTEK (Masuk, Keluar, Stok Akhir)
// ─────────────────────────────────────────────────────────────
if ($type === 'harian_gudang_apotek') {

    $tgl_awal  = $_GET['tgl_awal']  ?? date('Y-m-d');
    $tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

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
            $stmt_s = $pdo->prepare("SELECT id_obat, stok_akhir FROM tbl_stok_inventori WHERE id_unit = ?");
            $stmt_s->execute([$apotek_id]);
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
    $rows_html = '';
    $no = 1;
    foreach ($obat_list as $obat) {
        $id = $obat['id_obat'];
        $m  = $masuk_map[$id]  ?? 0;
        $k  = $keluar_map[$id] ?? 0;
        $s  = $stok_map[$id]   ?? 0;
        $grand_masuk  += $m;
        $grand_keluar += $k;
        $grand_stok   += $s;
        $warna_stok = $s == 0 ? 'color:red;' : ($s <= 10 ? 'color:#856404;' : '');
        $rows_html .= "<tr>
            <td class='tc'>{$no}</td>
            <td class='tc'>" . htmlspecialchars($obat['kode_obat']) . "</td>
            <td>" . htmlspecialchars($obat['nama_obat']) . "</td>
            <td class='tc'>" . htmlspecialchars($obat['satuan']) . "</td>
            <td class='tr' style='color:#155724;'>" . ($m > 0 ? number_format($m) : '&mdash;') . "</td>
            <td class='tr' style='color:#721c24;'>" . ($k > 0 ? number_format($k) : '&mdash;') . "</td>
            <td class='tr' style='font-weight:bold;{$warna_stok}'>" . number_format($s) . "</td>
        </tr>";
        $no++;
    }
    ?>
    <!DOCTYPE html><html><head>
    <title>Rekap Stok Apotek — <?= $tgl_awal ?> s/d <?= $tgl_akhir ?></title>
    <style>
        @page { size: portrait; margin: 1.5cm; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #333; }
        h3, .sub { text-align: center; margin: 2px 0; }
        h3 { font-size: 13px; text-transform: uppercase; }
        .sub { font-size: 11px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #555; padding: 5px 6px; }
        thead th { background-color: #343a40; color: #fff; text-align: center; }
        tfoot td { background-color: #6c757d; color: #fff; font-weight: bold; }
        .tc { text-align: center; } .tr { text-align: right; }
        .footer { margin-top: 16px; font-size: 9px; font-style: italic; text-align: right; }
    </style>
    </head>
    <body onload="window.print()">
    <h3>Rekap Stok Apotek Harian — Masuk, Keluar &amp; Stok Akhir</h3>
    <p class="sub">Periode: <?= date('d-m-Y', strtotime($tgl_awal)) ?> s/d <?= date('d-m-Y', strtotime($tgl_akhir)) ?></p>
    <table>
        <thead>
            <tr>
                <th style="width:30px;">No</th>
                <th style="width:80px;">Kode Obat</th>
                <th>Nama Obat</th>
                <th style="width:55px;">Satuan</th>
                <th style="width:90px;">Masuk<br><small>(dari Gudang)</small></th>
                <th style="width:90px;">Keluar<br><small>(ke Poli)</small></th>
                <th style="width:90px;">Stok Akhir<br><small>(Apotek)</small></th>
            </tr>
        </thead>
        <tbody>
            <?= $rows_html ?: '<tr><td colspan="7" style="text-align:center;">Tidak ada data stok apotek pada periode ini.</td></tr>' ?>
        </tbody>
        <?php if ($no > 1): ?>
        <tfoot>
            <tr>
                <td colspan="4" class="tr">GRAND TOTAL</td>
                <td class="tr" style="color:#155724;"><?= number_format($grand_masuk) ?></td>
                <td class="tr" style="color:#721c24;"><?= number_format($grand_keluar) ?></td>
                <td class="tr" style="font-weight:bold;"><?= number_format($grand_stok) ?></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
    <div style="margin-top:8px;font-size:9px;">
        <span style="color:#155724;">&#9632;</span> Masuk = transfer masuk dari Gudang &nbsp;|&nbsp;
        <span style="color:#721c24;">&#9632;</span> Keluar = distribusi ke Poli &nbsp;|&nbsp;
        <span style="color:red;">&#9632;</span> Stok Merah = Habis &nbsp;|&nbsp;
        <span style="color:#856404;">&#9632;</span> Stok Kuning = &le; 10
    </div>
    <div class="footer">Dicetak pada: <?= date('d/m/Y H:i:s') ?></div>
    </body></html>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// HARIAN
// ─────────────────────────────────────────────────────────────
if ($type === 'harian') {

    $filter_tgl = $_GET['tgl'] ?? date('Y-m-d');

    try {
        $sql  = "SELECT o.kode_obat, o.nama_obat, p.nama_poli, SUM(rd.jumlah_keluar) AS total FROM tbl_resep_detail rd JOIN tbl_resep_header rh ON rd.id_resep = rh.id_resep JOIN tbl_obat o ON rd.id_obat = o.id_obat JOIN tbl_poli p ON rh.id_poli = p.id_poli WHERE DATE(rh.tgl_resep) = ? GROUP BY o.id_obat, p.id_poli ORDER BY o.nama_obat ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$filter_tgl]);
        $data = $stmt->fetchAll();
    } catch (PDOException $e) { die($e->getMessage()); }
    ?>
    <!DOCTYPE html><html><head>
    <title>Cetak Laporan Harian</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid black; padding: 8px; }
        th { background-color: #eee; }
        h2 { text-align: center; }
    </style>
    </head>
    <body onload="window.print()">
    <h2>LAPORAN PEMAKAIAN HARIAN OBAT</h2>
    <p style="text-align:center;">Tanggal: <?= date('d-m-Y', strtotime($filter_tgl)); ?></p>
    <table>
        <thead>
            <tr><th>Kode Obat</th><th>Nama Obat</th><th>Poli</th><th>Jumlah</th></tr>
        </thead>
        <tbody>
        <?php foreach ($data as $d): ?>
            <tr>
                <td style="text-align:center;"><?= $d['kode_obat']; ?></td>
                <td><?= $d['nama_obat']; ?></td>
                <td><?= $d['nama_poli']; ?></td>
                <td style="text-align:right;"><?= number_format($d['total']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </body></html>
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

    try {
        $stmt = $pdo->prepare("SELECT * FROM tbl_log_stok WHERE id_obat = ? AND id_unit = ? AND tgl_log BETWEEN ? AND ? ORDER BY tgl_log ASC");
        $stmt->execute([$id_obat, $id_unit, $tgl_awal . " 00:00:00", $tgl_akhir . " 23:59:59"]);
        $data = $stmt->fetchAll();
    } catch (PDOException $e) { die($e->getMessage()); }
    ?>
    <!DOCTYPE html><html><head>
    <title>Cetak Kartu Stok</title>
    <style>
        body { font-family: Arial; }
        h3 { text-align: center; }
        table { width: 100%; border-collapse: collapse; }
        table, th, td { border: 1px solid black; }
        th, td { padding: 8px; text-align: center; }
        @media print { button { display: none; } }
    </style>
    </head>
    <body>
    <h3>KARTU STOK</h3>
    <p>Periode: <?= $tgl_awal ?> s/d <?= $tgl_akhir ?></p>
    <button onclick="window.print()">Cetak / Simpan PDF</button>
    <table>
        <tr><th>Tanggal</th><th>Sumber</th><th>Keterangan</th><th>Masuk</th><th>Keluar</th><th>Sisa</th></tr>
        <?php foreach ($data as $d): ?>
        <tr>
            <td><?= htmlspecialchars($d['tgl_log']); ?></td>
            <td><?= htmlspecialchars($d['sumber_data']); ?></td>
            <td><?= htmlspecialchars($d['keterangan'] ?? '-'); ?></td>
            <td style="text-align:right;"><?= number_format($d['masuk']); ?></td>
            <td style="text-align:right;"><?= number_format($d['keluar']); ?></td>
            <td style="text-align:right;"><?= number_format($d['stok_sesudah']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <script>window.onload = function() { window.print(); }</script>
    </body></html>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// KUNJUNGAN
// ─────────────────────────────────────────────────────────────
if ($type === 'kunjungan') {

    $filter_bulan = $_GET['bulan'] ?? date('Y-m');

    try {
        $stmt = $pdo->prepare("SELECT DATE(tgl_resep) as tanggal, SUM(CASE WHEN id_pelayanan = 1 THEN 1 ELSE 0 END) as UMUM, SUM(CASE WHEN id_pelayanan = 2 THEN 1 ELSE 0 END) as BPJS FROM tbl_resep_header WHERE DATE_FORMAT(tgl_resep, '%Y-%m') = ? GROUP BY DATE(tgl_resep) ORDER BY tanggal ASC");
        $stmt->execute([$filter_bulan]);
        $data = $stmt->fetchAll();
    } catch (PDOException $e) { die($e->getMessage()); }
    ?>
    <!DOCTYPE html><html><head>
    <title>Cetak Rekap Kunjungan</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; margin: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid black; padding: 8px; }
        th { background-color: #eee; }
        .text-center { text-align: center; }
        .text-right  { text-align: right; }
    </style>
    </head>
    <body onload="window.print()">
    <h2 class="text-center">LAPORAN REKAP KUNJUNGAN PASIEN (RESEP)</h2>
    <p class="text-center">Periode: <?= date('F Y', strtotime($filter_bulan)); ?></p>
    <table>
        <thead>
            <tr><th>No</th><th>Tanggal</th><th>UMUM</th><th>BPJS</th><th>Total</th></tr>
        </thead>
        <tbody>
        <?php $i = 1; $g_umum = 0; $g_bpjs = 0;
        foreach ($data as $d):
            $t = $d['UMUM'] + $d['BPJS'];
            $g_umum += $d['UMUM']; $g_bpjs += $d['BPJS'];
        ?>
        <tr>
            <td class="text-center"><?= $i++; ?></td>
            <td class="text-center"><?= date('d-m-Y', strtotime($d['tanggal'])); ?></td>
            <td class="text-right"><?= $d['UMUM']; ?></td>
            <td class="text-right"><?= $d['BPJS']; ?></td>
            <td class="text-right"><strong><?= $t; ?></strong></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot style="background-color:#f9f9f9;font-weight:bold;">
            <tr>
                <td colspan="2" class="text-right">GRAND TOTAL</td>
                <td class="text-right"><?= $g_umum; ?></td>
                <td class="text-right"><?= $g_bpjs; ?></td>
                <td class="text-right"><?= $g_umum + $g_bpjs; ?></td>
            </tr>
        </tfoot>
    </table>
    </body></html>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// SASARAN MUTU
// ─────────────────────────────────────────────────────────────
if ($type === 'sm') {

    $filter_bulan    = $_GET['bulan'] ?? date('Y-m');
    $id_poli_dipilih = isset($_GET['id_poli']) && $_GET['id_poli'] !== '' ? (int)$_GET['id_poli'] : null;

    $nama_poli_sm = 'Semua Poli';
    if ($id_poli_dipilih) {
        try {
            $stmt_poli_sm = $pdo->prepare("SELECT nama_poli FROM tbl_poli WHERE id_poli = ?");
            $stmt_poli_sm->execute([$id_poli_dipilih]);
            $row_poli_sm = $stmt_poli_sm->fetch();
            if ($row_poli_sm) $nama_poli_sm = $row_poli_sm['nama_poli'];
        } catch (PDOException $e) { die($e->getMessage()); }
    }

    try {
        if ($id_poli_dipilih) {
            $sql = "SELECT DATE(rh.tgl_resep) as tanggal, COUNT(rh.id_resep) as total_resep,
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
            $sql = "SELECT DATE(rh.tgl_resep) as tanggal, COUNT(rh.id_resep) as total_resep,
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
    <!DOCTYPE html><html><head>
    <title>Cetak Sasaran Mutu — <?= htmlspecialchars($judul_poli_sm) ?> - <?= htmlspecialchars($filter_bulan) ?></title>
    <style>
        @page { size: landscape; margin: 1cm; }
        body { font-family: sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid black; padding: 5px; text-align: center; }
        th { background-color: #f2f2f2; }
    </style>
    </head>
    <body onload="window.print()">
    <h3 style="text-align:center">LAPORAN SASARAN MUTU PELAYANAN RESEP — <?= htmlspecialchars($judul_poli_sm) ?></h3>
    <p style="text-align:center">Periode: <?= date('F Y', strtotime($filter_bulan . '-01')); ?></p>
    <table>
        <thead>
            <tr>
                <th rowspan="2">No</th><th rowspan="2">Tanggal</th>
                <th colspan="3">Kelengkapan</th>
                <th colspan="3">Kesalahan</th>
                <th colspan="3">Formularium</th>
                <th colspan="3">Racikan</th>
            </tr>
            <tr>
                <th>Lengkap</th><th>Tdk</th><th>%</th>
                <th>Ada</th><th>Tdk</th><th>%</th>
                <th>Sesuai</th><th>Tdk</th><th>%</th>
                <th>Racik</th><th>Non</th><th>% Non</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($data_sm)): ?>
            <tr><td colspan="14">Tidak ada data untuk periode ini.</td></tr>
        <?php else: ?>
            <?php $no = 1; foreach ($data_sm as $row):
                $total      = $row['total_resep'];
                $item_total = $row['total_racikan'] + $row['total_non_racikan'];
                $p_lengkap   = $total > 0 ? ($row['lengkap']     / $total) * 100 : 0;
                $p_tdk_salah = $total > 0 ? ($row['tdk_salah']   / $total) * 100 : 0;
                $p_sesuai    = $total > 0 ? ($row['sesuai_form'] / $total) * 100 : 0;
                $p_non_racik = $item_total > 0 ? ($row['total_non_racikan'] / $item_total) * 100 : 0;
            ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><?= date('d-m-Y', strtotime($row['tanggal'])); ?></td>
                <td><?= $row['lengkap']; ?></td>
                <td><?= $total - $row['lengkap']; ?></td>
                <td><?= number_format($p_lengkap, 1); ?>%</td>
                <td><?= $total - $row['tdk_salah']; ?></td>
                <td><?= $row['tdk_salah']; ?></td>
                <td><?= number_format($p_tdk_salah, 1); ?>%</td>
                <td><?= $row['sesuai_form']; ?></td>
                <td><?= $total - $row['sesuai_form']; ?></td>
                <td><?= number_format($p_sesuai, 1); ?>%</td>
                <td><?= $row['total_racikan']; ?></td>
                <td><?= $row['total_non_racikan']; ?></td>
                <td><?= number_format($p_non_racik, 1); ?>%</td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    </body></html>
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

    try {
        $stmt_poli = $pdo->prepare("SELECT nama_poli, id_unit_stok_default FROM tbl_poli WHERE id_poli = ?");
        $stmt_poli->execute([$id_poli_dipilih]);
        $poli_row = $stmt_poli->fetch();
    } catch (PDOException $e) { die($e->getMessage()); }

    if (!$poli_row) die('Poli tidak ditemukan.');

    $nama_poli = $poli_row['nama_poli'];
    $id_unit   = $poli_row['id_unit_stok_default'];

    $tgl_awal_bulan  = $filter_bulan . "-01 00:00:00";
    $tgl_akhir_bulan = date('Y-m-t 23:59:59', strtotime($tgl_awal_bulan));

    try {
        $stmt_obat = $pdo->query("SELECT id_obat, kode_obat, nama_obat FROM tbl_obat ORDER BY nama_obat ASC");
        $obat_list = $stmt_obat->fetchAll();

        $stmt_sa = $pdo->prepare("SELECT id_obat, SUM(masuk) - SUM(keluar) AS stok_awal FROM tbl_log_stok WHERE id_unit = ? AND tgl_log < ? GROUP BY id_obat");
        $stmt_sa->execute([$id_unit, $tgl_awal_bulan]);
        $stok_awal_map = [];
        foreach ($stmt_sa->fetchAll() as $r) $stok_awal_map[$r['id_obat']] = (int)$r['stok_awal'];

        $stmt_mk = $pdo->prepare("SELECT id_obat, SUM(masuk) AS total_masuk FROM tbl_log_stok WHERE id_unit = ? AND sumber_data IN ('Transfer','Stok Opname') AND masuk > 0 AND tgl_log BETWEEN ? AND ? GROUP BY id_obat");
        $stmt_mk->execute([$id_unit, $tgl_awal_bulan, $tgl_akhir_bulan]);
        $masuk_map = [];
        foreach ($stmt_mk->fetchAll() as $r) $masuk_map[$r['id_obat']] = (int)$r['total_masuk'];

        $stmt_kl = $pdo->prepare("SELECT rd.id_obat, SUM(rd.jumlah_keluar) AS total_keluar FROM tbl_resep_detail rd JOIN tbl_resep_header rh ON rd.id_resep = rh.id_resep WHERE rh.id_poli = ? AND rd.id_unit_asal = ? AND rh.tgl_resep BETWEEN ? AND ? GROUP BY rd.id_obat");
        $stmt_kl->execute([$id_poli_dipilih, $id_unit, $tgl_awal_bulan, $tgl_akhir_bulan]);
        $keluar_map = [];
        foreach ($stmt_kl->fetchAll() as $r) $keluar_map[$r['id_obat']] = (int)$r['total_keluar'];

    } catch (PDOException $e) { die("Error: " . $e->getMessage()); }

    $grand_sa = $grand_mk = $grand_kl = $grand_akhir = 0;
    $rows_html = '';
    $no = 1;
    foreach ($obat_list as $obat) {
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

        $warna_akhir = $stok_akhir < 0 ? 'color:red;' : '';
        $rows_html .= "<tr>
            <td class='text-center'>{$no}</td>
            <td>" . htmlspecialchars($obat['kode_obat']) . "</td>
            <td>" . htmlspecialchars($obat['nama_obat']) . "</td>
            <td class='text-right'>" . number_format($stok_awal) . "</td>
            <td class='text-right text-success'>" . ($masuk > 0 ? '+' . number_format($masuk) : '&mdash;') . "</td>
            <td class='text-right text-danger'>" . ($keluar > 0 ? '-' . number_format($keluar) : '&mdash;') . "</td>
            <td class='text-right' style='font-weight:bold;{$warna_akhir}'>" . number_format($stok_akhir) . "</td>
        </tr>";
        $no++;
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Laporan Bulanan Poli <?= htmlspecialchars($nama_poli) ?> - <?= $filter_bulan ?></title>
        <style>
            @page { size: portrait; margin: 1.5cm; }
            body { font-family: Arial, sans-serif; font-size: 10px; color: #333; }
            h3, .subtitle { text-align: center; margin: 2px 0; }
            h3 { font-size: 13px; text-transform: uppercase; }
            .subtitle { font-size: 11px; margin-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #555; padding: 5px 6px; }
            thead th { background-color: #343a40; color: #fff; text-align: center; }
            tfoot td { background-color: #6c757d; color: #fff; font-weight: bold; }
            .text-center { text-align: center; }
            .text-right  { text-align: right; }
            .text-success { color: #155724; }
            .text-danger  { color: #721c24; }
            .footer-note { margin-top: 16px; font-size: 9px; font-style: italic; text-align: right; }
        </style>
    </head>
    <body onload="window.print()">
        <h3>Laporan Bulanan Stok Obat Poli <?= htmlspecialchars($nama_poli) ?></h3>
        <p class="subtitle">Periode: <?= date('F Y', strtotime($tgl_awal_bulan)) ?></p>
        <table>
            <thead>
                <tr>
                    <th style="width:35px;">No</th>
                    <th style="width:90px;">Kode Obat</th>
                    <th>Nama Obat</th>
                    <th style="width:80px;">Stok Awal</th>
                    <th style="width:100px;">Masuk<br><small>(Transfer/Stok Opname)</small></th>
                    <th style="width:100px;">Keluar<br><small>(Pemakaian Resep)</small></th>
                    <th style="width:80px;">Stok Akhir</th>
                </tr>
            </thead>
            <tbody>
                <?= $rows_html ?: '<tr><td colspan="7" style="text-align:center;">Tidak ada aktivitas stok pada periode ini.</td></tr>' ?>
            </tbody>
            <?php if ($no > 1): ?>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-right">GRAND TOTAL</td>
                    <td class="text-right"><?= number_format($grand_sa) ?></td>
                    <td class="text-right text-success">+<?= number_format($grand_mk) ?></td>
                    <td class="text-right text-danger">-<?= number_format($grand_kl) ?></td>
                    <td class="text-right"><?= number_format($grand_akhir) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
        <div class="footer-note">Dicetak pada: <?= date('d/m/Y H:i:s') ?></div>
    </body>
    </html>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// KELUAR MASUK STOK
// ─────────────────────────────────────────────────────────────
if ($type === 'keluar_masuk_stok') {

    $tgl_awal  = $_GET['tgl_awal']  ?? date('Y-m-01');
    $tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
    $sumber    = $_GET['sumber']    ?? '';
    $id_unit   = isset($_GET['id_unit']) ? (int)$_GET['id_unit'] : null;
    $nama_unit = $_GET['nama_unit'] ?? 'Unit';

    if (!$id_unit) die('Parameter id_unit wajib diisi.');

    try {
        $query = "SELECT ls.tgl_log, o.kode_obat, o.nama_obat,
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

    $total_masuk  = array_sum(array_column($logs, 'masuk'));
    $total_keluar = array_sum(array_column($logs, 'keluar'));
    ?>
    <!DOCTYPE html><html><head>
    <title>Keluar Masuk Stok — <?= htmlspecialchars($nama_unit) ?></title>
    <style>
        @page { size: landscape; margin: 1cm; }
        body { font-family: Arial, sans-serif; font-size: 9px; color: #222; }
        h3, .sub { text-align: center; margin: 2px 0; }
        h3 { font-size: 12px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #555; padding: 4px 5px; }
        thead th { background-color: #343a40; color: #fff; text-align: center; }
        tfoot td { background-color: #f2f2f2; font-weight: bold; }
        .tc { text-align: center; } .tr { text-align: right; }
        .badge { padding: 2px 6px; border-radius: 3px; color: #fff; font-size: 8px; }
        .b-transfer   { background-color: #4e73df; }
        .b-resep      { background-color: #e74a3b; }
        .b-penerimaan { background-color: #1cc88a; }
        .b-opname     { background-color: #f6c23e; color: #333; }
        .b-default    { background-color: #6c757d; }
        .footer { margin-top: 12px; font-size: 8px; font-style: italic; text-align: right; }
    </style>
    </head>
    <body onload="window.print()">
    <h3>Laporan Keluar Masuk Stok — <?= htmlspecialchars($nama_unit) ?></h3>
    <p class="sub">Periode: <?= date('d-m-Y', strtotime($tgl_awal)) ?> s/d <?= date('d-m-Y', strtotime($tgl_akhir)) ?>
        <?= $sumber ? ' &nbsp;|&nbsp; Sumber: <strong>' . htmlspecialchars($sumber) . '</strong>' : '' ?>
    </p>
    <table>
        <thead>
            <tr>
                <th style="width:30px;">No</th>
                <th style="width:110px;">Tanggal</th>
                <th style="width:80px;">Kode Obat</th>
                <th>Nama Obat</th>
                <th style="width:80px;">Sumber</th>
                <th>Keterangan</th>
                <th style="width:55px;">Masuk</th>
                <th style="width:55px;">Keluar</th>
                <th style="width:60px;">Sisa Stok</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $i => $lg):
            $bc = 'b-default';
            if ($lg['sumber_data'] == 'Transfer')    $bc = 'b-transfer';
            elseif ($lg['sumber_data'] == 'Resep')   $bc = 'b-resep';
            elseif ($lg['sumber_data'] == 'Penerimaan') $bc = 'b-penerimaan';
            elseif ($lg['sumber_data'] == 'Stok Opname') $bc = 'b-opname';
        ?>
        <tr>
            <td class="tc"><?= $i + 1 ?></td>
            <td class="tc"><?= date('d-m-Y H:i', strtotime($lg['tgl_log'])) ?></td>
            <td class="tc"><?= htmlspecialchars($lg['kode_obat']) ?></td>
            <td><?= htmlspecialchars($lg['nama_obat']) ?></td>
            <td class="tc"><span class="badge <?= $bc ?>"><?= htmlspecialchars($lg['sumber_data']) ?></span></td>
            <td><?= htmlspecialchars($lg['keterangan'] ?? '-') ?></td>
            <td class="tr" style="color:#155724;"><?= $lg['masuk'] > 0 ? '+' . number_format($lg['masuk']) : '&mdash;' ?></td>
            <td class="tr" style="color:#721c24;"><?= $lg['keluar'] > 0 ? '-' . number_format($lg['keluar']) : '&mdash;' ?></td>
            <td class="tr"><strong><?= number_format($lg['stok_sesudah']) ?></strong></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="tr">TOTAL</td>
                <td class="tr" style="color:#155724;">+<?= number_format($total_masuk) ?></td>
                <td class="tr" style="color:#721c24;">-<?= number_format($total_keluar) ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    <div class="footer">Dicetak pada: <?= date('d/m/Y H:i:s') ?></div>
    </body></html>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────
// HISTORY DOKTER
// ─────────────────────────────────────────────────────────────
if ($type === 'history_dokter') {

    $tgl_awal  = $_GET['tgl_awal']  ?? date('Y-m-01');
    $tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
    $id_dokter = $_GET['id_dokter'] ?? '';
    $id_unit   = isset($_GET['id_unit']) ? (int)$_GET['id_unit'] : null;
    $nama_unit = $_GET['nama_unit'] ?? 'Unit';

    if (!$id_unit) die('Parameter id_unit wajib diisi.');

    try {
        $query = "SELECT rh.id_resep, rh.tgl_resep, d.nama_dokter, o.kode_obat, o.nama_obat,
                         rd.jumlah_keluar, rd.jenis_racikan, p.nama_poli
                  FROM tbl_resep_header rh
                  JOIN tbl_dokter d        ON rh.id_dokter  = d.id_dokter
                  JOIN tbl_resep_detail rd  ON rh.id_resep   = rd.id_resep
                  JOIN tbl_obat o           ON rd.id_obat    = o.id_obat
                  JOIN tbl_poli p           ON rh.id_poli    = p.id_poli
                  WHERE p.id_unit_stok_default = ?
                    AND rh.tgl_resep BETWEEN ? AND ?";
        $params = [$id_unit, $tgl_awal . ' 00:00:00', $tgl_akhir . ' 23:59:59'];
        if (!empty($id_dokter)) { $query .= " AND d.id_dokter = ?"; $params[] = $id_dokter; }
        $query .= " ORDER BY rh.tgl_resep DESC, d.nama_dokter ASC, o.nama_obat ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        $nama_dokter_filter = 'Semua Dokter';
        if (!empty($id_dokter)) {
            $stmt_d = $pdo->prepare("SELECT nama_dokter FROM tbl_dokter WHERE id_dokter = ?");
            $stmt_d->execute([$id_dokter]);
            $row_d = $stmt_d->fetch();
            if ($row_d) $nama_dokter_filter = $row_d['nama_dokter'];
        }

        $rekap = [];
        foreach ($logs as $ld) {
            $key = $ld['nama_dokter'];
            if (!isset($rekap[$key])) $rekap[$key] = ['resep' => [], 'item' => 0, 'qty' => 0];
            $rekap[$key]['resep'][$ld['id_resep']] = true;
            $rekap[$key]['item']++;
            $rekap[$key]['qty'] += $ld['jumlah_keluar'];
        }
    } catch (PDOException $e) { die($e->getMessage()); }

    $total_qty   = array_sum(array_column($logs, 'jumlah_keluar'));
    $total_resep = count(array_unique(array_column($logs, 'id_resep')));
    ?>
    <!DOCTYPE html><html><head>
    <title>History Dokter — <?= htmlspecialchars($nama_unit) ?></title>
    <style>
        @page { size: landscape; margin: 1cm; }
        body { font-family: Arial, sans-serif; font-size: 9px; color: #222; }
        h3, .sub { text-align: center; margin: 2px 0; }
        h3 { font-size: 12px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #555; padding: 4px 5px; }
        thead th { background-color: #343a40; color: #fff; text-align: center; }
        tfoot td { background-color: #f2f2f2; font-weight: bold; }
        .rekap-table thead th { background-color: #4e73df; }
        .tc { text-align: center; } .tr { text-align: right; }
        .badge { padding: 2px 5px; border-radius: 3px; color: #fff; font-size: 8px; }
        .footer { margin-top: 12px; font-size: 8px; font-style: italic; text-align: right; }
    </style>
    </head>
    <body onload="window.print()">
    <h3>History Resep Dokter — <?= htmlspecialchars($nama_unit) ?></h3>
    <p class="sub">
        Periode: <?= date('d-m-Y', strtotime($tgl_awal)) ?> s/d <?= date('d-m-Y', strtotime($tgl_akhir)) ?>
        &nbsp;|&nbsp; Dokter: <strong><?= htmlspecialchars($nama_dokter_filter) ?></strong>
        &nbsp;|&nbsp; Total Resep: <strong><?= number_format($total_resep) ?></strong>
        &nbsp;|&nbsp; Total Qty: <strong><?= number_format($total_qty) ?></strong>
    </p>

    <?php if (!empty($rekap)): ?>
    <table class="rekap-table" style="margin-bottom:10px;">
        <thead>
            <tr>
                <th style="width:30px;">No</th>
                <th>Nama Dokter</th>
                <th style="width:80px;">Jml Resep</th>
                <th style="width:80px;">Jml Item</th>
                <th style="width:80px;">Total Qty</th>
            </tr>
        </thead>
        <tbody>
        <?php $no = 1; foreach ($rekap as $nama_dr => $rek): ?>
        <tr>
            <td class="tc"><?= $no++ ?></td>
            <td><?= htmlspecialchars($nama_dr) ?></td>
            <td class="tc"><?= count($rek['resep']) ?></td>
            <td class="tc"><?= number_format($rek['item']) ?></td>
            <td class="tr"><?= number_format($rek['qty']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th style="width:30px;">No</th>
                <th style="width:110px;">Tanggal</th>
                <th style="width:65px;">No. Resep</th>
                <th style="width:120px;">Nama Dokter</th>
                <th style="width:80px;">Poli</th>
                <th style="width:75px;">Kode Obat</th>
                <th>Nama Obat</th>
                <th style="width:65px;">Jenis</th>
                <th style="width:45px;">Qty</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $i => $ld): ?>
        <tr>
            <td class="tc"><?= $i + 1 ?></td>
            <td class="tc"><?= date('d-m-Y H:i', strtotime($ld['tgl_resep'])) ?></td>
            <td class="tc">#<?= $ld['id_resep'] ?></td>
            <td><?= htmlspecialchars($ld['nama_dokter']) ?></td>
            <td class="tc"><?= htmlspecialchars($ld['nama_poli']) ?></td>
            <td class="tc"><?= htmlspecialchars($ld['kode_obat']) ?></td>
            <td><?= htmlspecialchars($ld['nama_obat']) ?></td>
            <td class="tc">
                <?php if ($ld['jenis_racikan'] == 'Racikan'): ?>
                    <span class="badge" style="background:#f6c23e;color:#333;">Racikan</span>
                <?php else: ?>
                    <span class="badge" style="background:#36b9cc;">Non Racikan</span>
                <?php endif; ?>
            </td>
            <td class="tr" style="color:#721c24;font-weight:bold;"><?= number_format($ld['jumlah_keluar']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="8" class="tr">TOTAL QTY</td>
                <td class="tr"><?= number_format($total_qty) ?></td>
            </tr>
        </tfoot>
    </table>
    <div class="footer">Dicetak pada: <?= date('d/m/Y H:i:s') ?></div>
    </body></html>
    <?php
    exit;
}

// Fallback jika type tidak dikenali
die('Tipe laporan "' . htmlspecialchars($type) . '" tidak dikenali.');