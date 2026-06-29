<?php
require_once '../config.php';
require_once '../templates/auth_check.php';

// Proteksi Role: Hanya Super Admin (1) atau Admin (2) atau role 5
if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2 && $_SESSION['role_id'] != 5) {
    $_SESSION['flash_error'] = 'Akses Ditolak!'; header("Location: " . BASE_URL . "dashboard.php"); exit;
    exit;
}

$page_title = "Laporan Pemakaian Bulanan";

// Cek apakah user adalah Admin/Gudang (bisa lihat data Vendor → Gudang)
$is_admin = ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2);

// Filter bulan
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');

// Hitung tanggal awal dan akhir bulan
$tgl_awal  = $filter_bulan . '-01';
$tgl_akhir = date('Y-m-t', strtotime($tgl_awal));

// -----------------------------------------------------------------------
//  Vendor → Gudang  (hanya Admin)
// -----------------------------------------------------------------------
$vendor_ke_gudang    = [];
$total_vendor_gudang = 0;

// -----------------------------------------------------------------------
//  Gudang → Apotek
// -----------------------------------------------------------------------
$gudang_ke_apotek    = [];
$total_gudang_apotek = 0;

// -----------------------------------------------------------------------
//  Apotek → Poli
// -----------------------------------------------------------------------
$apotek_ke_poli    = [];
$total_apotek_poli = 0;

try {
    // Dapatkan id unit GUDANG dan APOTEK
    $stmt_unit = $pdo->query("SELECT id_unit, nama_unit FROM tbl_unit");
    $all_units = $stmt_unit->fetchAll(PDO::FETCH_ASSOC);
    $unit_map  = [];
    $gudang_id = null;
    $apotek_id = null;
    foreach ($all_units as $u) {
        $unit_map[$u['id_unit']] = $u['nama_unit'];
        if (strtoupper($u['nama_unit']) === 'GUDANG') $gudang_id = $u['id_unit'];
        if (strtoupper($u['nama_unit']) === 'APOTEK')  $apotek_id  = $u['id_unit'];
    }

    // ---- Seksi 1: Vendor → Gudang ----
    if ($is_admin && $gudang_id !== null) {
        $sql1 = "
            SELECT
                o.kode_obat,
                o.nama_obat,
                o.satuan,
                tm.keterangan  AS no_faktur,
                SUM(tm.jumlah_masuk) AS total_masuk
            FROM tbl_transaksi_masuk tm
            JOIN tbl_obat o ON tm.id_obat = o.id_obat
            WHERE DATE(tm.tgl_masuk) BETWEEN ? AND ?
              AND tm.id_unit = ?
            GROUP BY o.id_obat, tm.keterangan
            ORDER BY o.nama_obat ASC
        ";
        $stmt1 = $pdo->prepare($sql1);
        $stmt1->execute([$tgl_awal, $tgl_akhir, $gudang_id]);
        $vendor_ke_gudang = $stmt1->fetchAll(PDO::FETCH_ASSOC);
        foreach ($vendor_ke_gudang as $r) {
            $total_vendor_gudang += $r['total_masuk'];
        }
    }

    // ---- Seksi 2: Gudang → Apotek ----
    if ($gudang_id !== null && $apotek_id !== null) {
        $sql2 = "
            SELECT
                o.id_obat,
                o.kode_obat,
                o.nama_obat,
                o.satuan,
                SUM(tt.jumlah) AS total_masuk_apotek
            FROM tbl_transaksi_transfer tt
            JOIN tbl_obat o ON tt.id_obat = o.id_obat
            WHERE DATE(tt.tgl_transfer) BETWEEN ? AND ?
              AND tt.id_unit_asal   = ?
              AND tt.id_unit_tujuan = ?
            GROUP BY o.id_obat
            ORDER BY o.nama_obat ASC
        ";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute([$tgl_awal, $tgl_akhir, $gudang_id, $apotek_id]);
        $gudang_ke_apotek = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        foreach ($gudang_ke_apotek as $r) {
            $total_gudang_apotek += $r['total_masuk_apotek'];
        }
    }

    // ---- Data tambahan Apotek: keluar ke Poli & stok akhir ----
    $apotek_keluar_map = []; // [id_obat] = total keluar apotek ke poli
    $apotek_stok_map   = []; // [id_obat] = stok akhir apotek
    $total_keluar_apotek_ke_poli = 0;
    $total_stok_akhir_apotek     = 0;

    if ($apotek_id !== null) {
        // Keluar apotek → semua poli (bukan ke gudang) pada bulan ini
        $sql_keluar_ap = "
            SELECT tt.id_obat, SUM(tt.jumlah) AS total_keluar
            FROM tbl_transaksi_transfer tt
            WHERE DATE(tt.tgl_transfer) BETWEEN ? AND ?
              AND tt.id_unit_asal   = ?
              AND tt.id_unit_tujuan != ?
            GROUP BY tt.id_obat
        ";
        $stmt_keluar_ap = $pdo->prepare($sql_keluar_ap);
        $stmt_keluar_ap->execute([$tgl_awal, $tgl_akhir, $apotek_id, $gudang_id]);
        foreach ($stmt_keluar_ap->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $apotek_keluar_map[$r['id_obat']] = $r['total_keluar'];
        }

        // Stok akhir apotek dari tbl_log_stok per akhir bulan yang dipilih
        // Ambil stok_sesudah dari transaksi terakhir tiap obat di apotek s/d akhir bulan
        $sql_stok_ap = "
            SELECT ls.id_obat, ls.stok_sesudah AS stok_akhir
            FROM tbl_log_stok ls
            INNER JOIN (
                SELECT id_obat, MAX(tgl_log) AS max_tgl
                FROM tbl_log_stok
                WHERE id_unit = ?
                  AND tgl_log <= ?
                GROUP BY id_obat
            ) last ON ls.id_obat = last.id_obat AND ls.tgl_log = last.max_tgl
            WHERE ls.id_unit = ?
        ";
        $stmt_stok_ap = $pdo->prepare($sql_stok_ap);
        $stmt_stok_ap->execute([$apotek_id, $tgl_akhir . ' 23:59:59', $apotek_id]);
        foreach ($stmt_stok_ap->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $apotek_stok_map[$r['id_obat']] = $r['stok_akhir'];
        }

        // Gabungkan ke $gudang_ke_apotek — tambahkan obat yang hanya punya keluar/stok tapi tidak ada masuk bulan ini
        $obat_id_sudah = array_column($gudang_ke_apotek, 'id_obat');
        $semua_obat_apotek_id = array_unique(array_merge(
            $obat_id_sudah,
            array_keys($apotek_keluar_map),
            array_keys($apotek_stok_map)
        ));

        // Rebuild $gudang_ke_apotek jadi array indexed by id_obat
        $gudang_ke_apotek_map = [];
        foreach ($gudang_ke_apotek as $r) {
            $gudang_ke_apotek_map[$r['id_obat']] = $r;
        }

        // Ambil info obat untuk id yang belum ada
        $missing_ids = array_diff($semua_obat_apotek_id, $obat_id_sudah);
        if (!empty($missing_ids)) {
            $placeholders = implode(',', array_fill(0, count($missing_ids), '?'));
            $stmt_miss = $pdo->prepare("SELECT id_obat, kode_obat, nama_obat, satuan FROM tbl_obat WHERE id_obat IN ($placeholders) ORDER BY nama_obat ASC");
            $stmt_miss->execute(array_values($missing_ids));
            foreach ($stmt_miss->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if (!isset($gudang_ke_apotek_map[$r['id_obat']])) {
                    $gudang_ke_apotek_map[$r['id_obat']] = [
                        'id_obat'            => $r['id_obat'],
                        'kode_obat'          => $r['kode_obat'],
                        'nama_obat'          => $r['nama_obat'],
                        'satuan'             => $r['satuan'],
                        'total_masuk_apotek' => 0,
                    ];
                }
            }
        }

        // Sort by nama_obat dan rebuild array
        uasort($gudang_ke_apotek_map, fn($a, $b) => strcmp($a['nama_obat'], $b['nama_obat']));
        $gudang_ke_apotek = array_values($gudang_ke_apotek_map);

        // Hitung grand total keluar apotek & stok akhir
        foreach ($apotek_keluar_map as $v) $total_keluar_apotek_ke_poli += $v;
        foreach ($apotek_stok_map as $v)   $total_stok_akhir_apotek     += $v;
    }

    // ---- Seksi 3: Apotek → Poli (semua tujuan selain GUDANG) ----
    if ($apotek_id !== null) {
        $sql3 = "
            SELECT
                o.kode_obat,
                o.nama_obat,
                o.satuan,
                ut.nama_unit      AS nama_tujuan,
                SUM(tt.jumlah)    AS total_keluar
            FROM tbl_transaksi_transfer tt
            JOIN tbl_obat o   ON tt.id_obat        = o.id_obat
            JOIN tbl_unit ut  ON tt.id_unit_tujuan = ut.id_unit
            WHERE DATE(tt.tgl_transfer) BETWEEN ? AND ?
              AND tt.id_unit_asal = ?
              AND tt.id_unit_tujuan != ?
            GROUP BY o.id_obat, tt.id_unit_tujuan
            ORDER BY o.nama_obat ASC, ut.nama_unit ASC
        ";
        $stmt3 = $pdo->prepare($sql3);
        $stmt3->execute([$tgl_awal, $tgl_akhir, $apotek_id, $gudang_id]);
        $rows3 = $stmt3->fetchAll(PDO::FETCH_ASSOC);

        // Kelompokkan per obat
        foreach ($rows3 as $r) {
            $key = $r['kode_obat'];
            if (!isset($apotek_ke_poli[$key])) {
                $apotek_ke_poli[$key] = [
                    'kode_obat'  => $r['kode_obat'],
                    'nama_obat'  => $r['nama_obat'],
                    'satuan'     => $r['satuan'],
                    'detail'     => [],
                    'subtotal'   => 0,
                ];
            }
            $apotek_ke_poli[$key]['detail'][]  = [
                'nama_tujuan'  => $r['nama_tujuan'],
                'total_keluar' => $r['total_keluar'],
            ];
            $apotek_ke_poli[$key]['subtotal'] += $r['total_keluar'];
            $total_apotek_poli               += $r['total_keluar'];
        }
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

include '../templates/header.php';
?>

<main class="content">
    <h1 class="h3 mb-2 text-gray-800"><?= htmlspecialchars($page_title); ?></h1>
    <p class="mb-4 text-muted">
        Laporan ini tidak mencatat pemakaian langsung ke pasien, hanya rekap perpindahan stok antar unit per bulan.
    </p>

    <!-- Filter -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Laporan</h6>
        </div>
        <div class="card-body">
            <form action="" method="GET">
                <div class="form-row align-items-end">
                    <div class="col-md-3">
                        <label for="bulan">Pilih Bulan &amp; Tahun</label>
                        <input type="month" id="bulan" name="bulan" class="form-control"
                               value="<?= htmlspecialchars($filter_bulan); ?>" required>
                    </div>
                    <div class="col-md-3 mt-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Tampilkan
                        </button>
                        <a href="?" class="btn btn-secondary ml-1">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php
    $periode_label = date('F Y', strtotime($filter_bulan . '-01'));
    ?>

    <!-- ================================================================
         SEKSI 1: VENDOR → GUDANG  (hanya Admin/Super Admin)
    ================================================================ -->
    <?php if ($is_admin): ?>
    <div class="card shadow mb-4 border-left-warning">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-warning">
                <i class="fas fa-truck"></i>
                  Penerimaan: Vendor &rarr; Gudang
                <small class="text-muted font-weight-normal ml-2">(<?= $periode_label; ?>)</small>
            </h6>
            <div>
                <a href="export_excel.php?type=bulanan_vendor_gudang&bulan=<?= urlencode($filter_bulan); ?>"
                   class="btn btn-success btn-sm">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="export_pdf.php?type=bulanan_vendor_gudang&bulan=<?= urlencode($filter_bulan); ?>"
                   target="_blank" class="btn btn-danger btn-sm">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-sm">
                    <thead class="thead-dark text-center">
                        <tr>
                            <th>Kode Obat</th>
                            <th class="text-left">Nama Obat</th>
                            <th>Satuan</th>
                            <th>No. Faktur / Keterangan</th>
                            <th>Jumlah Masuk</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vendor_ke_gudang)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    Tidak ada penerimaan dari vendor pada bulan ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($vendor_ke_gudang as $r): ?>
                            <tr>
                                <td class="text-center"><?= htmlspecialchars($r['kode_obat']); ?></td>
                                <td><?= htmlspecialchars($r['nama_obat']); ?></td>
                                <td class="text-center"><?= htmlspecialchars($r['satuan']); ?></td>
                                <td><?= htmlspecialchars($r['no_faktur'] ?: '-'); ?></td>
                                <td class="text-right font-weight-bold text-success">
                                    <?= number_format($r['total_masuk']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($vendor_ke_gudang)): ?>
                    <tfoot>
                        <tr class="font-weight-bold" style="background-color:#6c757d; color:#fff;">
                            <td colspan="4" class="text-right">TOTAL MASUK GUDANG</td>
                            <td class="text-right"><?= number_format($total_vendor_gudang); ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    <?php endif; // $is_admin ?>

    <!-- ================================================================
         SEKSI 2: APOTEK — Masuk, Keluar & Stok Akhir
    ================================================================ -->
    <div class="card shadow mb-4 border-left-primary">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-dolly"></i>
                  Rekap Stok Apotek: Masuk, Keluar &amp; Stok Akhir
                <small class="text-muted font-weight-normal ml-2">(<?= $periode_label; ?>)</small>
            </h6>
            <div>
                <a href="export_excel.php?type=bulanan_gudang_apotek&bulan=<?= urlencode($filter_bulan); ?>"
                   class="btn btn-success btn-sm">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="export_pdf.php?type=bulanan_gudang_apotek&bulan=<?= urlencode($filter_bulan); ?>"
                   target="_blank" class="btn btn-danger btn-sm">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-sm">
                    <thead class="thead-dark text-center">
                        <tr>
                            <th>No</th>
                            <th>Kode Obat</th>
                            <th class="text-left">Nama Obat</th>
                            <th>Satuan</th>
                            <th class="text-success">Masuk<br><small>(dari Gudang)</small></th>
                            <th class="text-warning">Keluar<br><small>(ke Poli)</small></th>
                            <th class="text-info">Stok Akhir<br><small>(Apotek)</small></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $ada_data = false;
                        $grand_masuk_apotek  = 0;
                        $grand_keluar_apotek = 0;
                        $grand_stok_apotek   = 0;
                        $no_apotek = 1;
                        foreach ($gudang_ke_apotek as $r):
                            $id_obat    = $r['id_obat'];
                            $masuk      = $r['total_masuk_apotek'] ?? 0;
                            $keluar     = $apotek_keluar_map[$id_obat] ?? 0;
                            $stok_akhir = $apotek_stok_map[$id_obat]  ?? 0;
                            if ($masuk == 0 && $keluar == 0 && $stok_akhir == 0) continue;
                            $ada_data = true;
                            $grand_masuk_apotek  += $masuk;
                            $grand_keluar_apotek += $keluar;
                            $grand_stok_apotek   += $stok_akhir;
                            $stok_class = $stok_akhir == 0 ? 'table-danger' : ($stok_akhir <= 10 ? 'table-warning' : 'table-info');
                        ?>
                        <tr>
                            <td class="text-center"><?= $no_apotek++; ?></td>
                            <td class="text-center"><?= htmlspecialchars($r['kode_obat']); ?></td>
                            <td><?= htmlspecialchars($r['nama_obat']); ?></td>
                            <td class="text-center"><?= htmlspecialchars($r['satuan']); ?></td>
                            <td class="text-right font-weight-bold text-success">
                                <?= $masuk > 0 ? number_format($masuk) : '-'; ?>
                            </td>
                            <td class="text-right font-weight-bold text-danger">
                                <?= $keluar > 0 ? number_format($keluar) : '-'; ?>
                            </td>
                            <td class="text-right font-weight-bold <?= $stok_class; ?>">
                                <?= number_format($stok_akhir); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$ada_data): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    Tidak ada data stok Apotek pada bulan ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if ($ada_data): ?>
                    <tfoot>
                        <tfoot class="thead-dark">
                            <td colspan="4" class="text-right">GRAND TOTAL</td>
                            <td class="text-right"><?= number_format($grand_masuk_apotek); ?></td>
                            <td class="text-right"><?= number_format($grand_keluar_apotek); ?></td>
                            <td class="text-right"><?= number_format($grand_stok_apotek); ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
            <div class="mt-2">
                <small class="text-muted">
                    <span class="badge badge-success">Hijau</span> = Masuk dari Gudang &nbsp;|&nbsp;
                    <span class="badge badge-danger">Merah</span> = Keluar ke Poli / Stok Habis &nbsp;|&nbsp;
                    <span class="badge badge-warning text-dark">Kuning</span> = Stok &le; 10 &nbsp;|&nbsp;
                    <span class="badge badge-info">Biru</span> = Stok Akhir Apotek
                </small>
            </div>
        </div>
    </div>

    <!-- ================================================================
         SEKSI 3: APOTEK → POLI
    ================================================================ -->
    <div class="card shadow mb-4 border-left-success">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-success">
                <i class="fas fa-pills"></i>
                  Distribusi: Apotek &rarr; Poli
                <small class="text-muted font-weight-normal ml-2">(<?= $periode_label; ?>)</small>
            </h6>
            <div>
                <a href="export_excel.php?type=bulanan_apotek_poli&bulan=<?= urlencode($filter_bulan); ?>"
                   class="btn btn-success btn-sm">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="export_pdf.php?type=bulanan_apotek_poli&bulan=<?= urlencode($filter_bulan); ?>"
                   target="_blank" class="btn btn-danger btn-sm">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-sm">
                    <thead class="thead-dark text-center">
                        <tr>
                            <th rowspan="2" class="align-middle">Kode Obat</th>
                            <th rowspan="2" class="align-middle text-left">Nama Obat</th>
                            <th rowspan="2" class="align-middle">Satuan</th>
                            <th rowspan="2" class="align-middle">Tujuan Poli</th>
                            <th>Keluar dari Apotek</th>
                        </tr>
                        <tr>
                            <th>(Jumlah)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($apotek_ke_poli)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    Tidak ada distribusi Apotek &rarr; Poli pada bulan ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($apotek_ke_poli as $obat): ?>
                                <?php $rowspan = count($obat['detail']) + 1; ?>
                                <?php foreach ($obat['detail'] as $idx => $detail): ?>
                                <tr>
                                    <?php if ($idx === 0): ?>
                                        <td rowspan="<?= $rowspan; ?>" class="align-middle text-center">
                                            <?= htmlspecialchars($obat['kode_obat']); ?>
                                        </td>
                                        <td rowspan="<?= $rowspan; ?>" class="align-middle">
                                            <?= htmlspecialchars($obat['nama_obat']); ?>
                                        </td>
                                        <td rowspan="<?= $rowspan; ?>" class="align-middle text-center">
                                            <?= htmlspecialchars($obat['satuan']); ?>
                                        </td>
                                    <?php endif; ?>
                                    <td><?= htmlspecialchars($detail['nama_tujuan']); ?></td>
                                    <td class="text-right text-danger font-weight-bold">
                                        <?= number_format($detail['total_keluar']); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <!-- Subtotal per obat -->
                                <tr class="table-warning font-weight-bold">
                                    <td class="text-right text-muted small">
                                        Subtotal <?= htmlspecialchars($obat['nama_obat']); ?>
                                    </td>
                                    <td class="text-right text-danger">
                                        <?= number_format($obat['subtotal']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($apotek_ke_poli)): ?>
                    <tfoot>
                        <tfoot class="thead-dark">
                            <td colspan="4" class="text-right">TOTAL KELUAR APOTEK</td>
                            <td class="text-right"><?= number_format($total_apotek_poli); ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

</main>

<?php include '../templates/footer.php'; ?>