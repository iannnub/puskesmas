<?php
require_once '../config.php';
require_once '../templates/auth_check.php';

// Proteksi Role: Hanya Poli Belakang (role 4) yang boleh akses
if ($_SESSION['role_id'] != 4) {
    $_SESSION['flash_error'] = 'Akses Ditolak!'; header("Location: " . BASE_URL . "dashboard.php"); exit;
    exit;
}

$page_title = "Laporan Keluar Masuk Stok";

// Ambil id_unit stok default milik user berdasarkan id_user
$stmt_unit = $pdo->prepare("
    SELECT p.id_unit_stok_default, u.nama_unit 
    FROM tbl_user tu
    JOIN tbl_poli p ON tu.id_poli = p.id_poli
    JOIN tbl_unit u ON p.id_unit_stok_default = u.id_unit
    WHERE tu.id_user = ?
");
$stmt_unit->execute([$_SESSION['user_id']]);
$unit_info = $stmt_unit->fetch();

if (!$unit_info) {
    die("Unit tidak ditemukan untuk user ini.");
}

$id_unit     = $unit_info['id_unit_stok_default'];
$nama_unit   = $unit_info['nama_unit'];

// Filter tanggal (Standard Y-m-d untuk query database)
$filter_tgl_awal  = $_GET['tgl_awal']  ?? date('Y-m-01');
$filter_tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
$filter_sumber    = $_GET['sumber'] ?? '';
$active_tab       = $_GET['tab'] ?? 'stok'; // 'stok' atau 'dokter'

try {
    // Query log stok sesuai struktur database tbl_log_stok
    $query = "
        SELECT 
            ls.tgl_log, o.kode_obat, o.nama_obat, o.satuan,
            ls.sumber_data, ls.keterangan, ls.masuk, ls.keluar,
            ls.stok_sebelum, ls.stok_sesudah
        FROM tbl_log_stok ls
        JOIN tbl_obat o ON ls.id_obat = o.id_obat
        WHERE ls.id_unit = ?
          AND ls.tgl_log BETWEEN ? AND ?
    ";
    $params = [
        $id_unit,
        $filter_tgl_awal . ' 00:00:00',
        $filter_tgl_akhir . ' 23:59:59'
    ];

    if (!empty($filter_sumber)) {
        $query .= " AND ls.sumber_data = ?";
        $params[] = $filter_sumber;
    }

    $query .= " ORDER BY ls.tgl_log ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Kalkulasi total masuk, keluar, dan transaksi untuk ringkasan
    $total_masuk  = array_sum(array_column($logs, 'masuk'));
    $total_keluar = array_sum(array_column($logs, 'keluar'));
    $total_transaksi = count($logs);

    // ── Query daftar dokter untuk dropdown ──
    $stmt_dokter = $pdo->query("SELECT id_dokter, nama_dokter FROM tbl_dokter ORDER BY nama_dokter ASC");
    $list_dokter = $stmt_dokter->fetchAll();

    // ── Query history resep per dokter ──
    $query_dokter = "
        SELECT 
            rh.id_resep,
            rh.tgl_resep,
            d.id_dokter,
            d.nama_dokter,
            o.kode_obat,
            o.nama_obat,
            o.satuan,
            rd.jumlah_keluar,
            rd.jenis_racikan,
            p.nama_poli
        FROM tbl_resep_header rh
        JOIN tbl_dokter d       ON rh.id_dokter  = d.id_dokter
        JOIN tbl_resep_detail rd ON rh.id_resep   = rd.id_resep
        JOIN tbl_obat o          ON rd.id_obat    = o.id_obat
        JOIN tbl_poli p          ON rh.id_poli    = p.id_poli
        WHERE p.id_unit_stok_default = ?
          AND rh.tgl_resep BETWEEN ? AND ?
    ";
    $params_dokter = [
        $id_unit,
        $filter_tgl_awal . ' 00:00:00',
        $filter_tgl_akhir . ' 23:59:59'
    ];

    if (!empty($filter_dokter)) {
        $query_dokter .= " AND d.id_dokter = ?";
        $params_dokter[] = $filter_dokter;
    }

    $query_dokter .= " ORDER BY rh.tgl_resep DESC, d.nama_dokter ASC, o.nama_obat ASC";
    $stmt_dr = $pdo->prepare($query_dokter);
    $stmt_dr->execute($params_dokter);
    $logs_dokter = $stmt_dr->fetchAll();

    // Ringkasan per dokter
    $rekap_dokter = [];
    foreach ($logs_dokter as $ld) {
        $key = $ld['id_dokter'];
        if (!isset($rekap_dokter[$key])) {
            $rekap_dokter[$key] = [
                'nama_dokter'   => $ld['nama_dokter'],
                'total_resep'   => 0,
                'total_item'    => 0,
                'total_qty'     => 0,
                'id_resep_list' => [],
            ];
        }
        if (!in_array($ld['id_resep'], $rekap_dokter[$key]['id_resep_list'])) {
            $rekap_dokter[$key]['total_resep']++;
            $rekap_dokter[$key]['id_resep_list'][] = $ld['id_resep'];
        }
        $rekap_dokter[$key]['total_item']++;
        $rekap_dokter[$key]['total_qty'] += $ld['jumlah_keluar'];
    }

    $total_resep_dokter = count(array_unique(array_column($logs_dokter, 'id_resep')));
    $total_item_dokter  = count($logs_dokter);
    $total_qty_dokter   = array_sum(array_column($logs_dokter, 'jumlah_keluar'));

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "");
}

include '../templates/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .input-group-text { cursor: pointer; background-color: #f8f9fa; }
    .flatpickr-input[readonly] { background-color: #fff !important; }
    /* Styling Badge Sumber */
    .badge-transfer { background-color: #4e73df; color: white; } 
    .badge-resep { background-color: #e74a3b; color: white; }    
    .badge-penerimaan { background-color: #1cc88a; color: white; } 
    .badge-opname { background-color: #f6c23e; color: white; }     
</style>

<main class="content"> 
    <h1 class="h3 mb-1 text-gray-800"><?= htmlspecialchars($page_title); ?></h1>
    <p class="mb-4 text-muted">Unit: <strong><?= htmlspecialchars($nama_unit); ?></strong></p>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Laporan</h6>
        </div>
        <div class="card-body">
            <form action="" method="GET">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab); ?>">
                <div class="form-row">
                    <div class="col-md-3 mb-3">
                        <label class="font-weight-bold small text-uppercase">Dari Tanggal</label>
                        <div class="input-group date-picker-container">
                            <input type="text" name="tgl_awal" class="form-control" value="<?= $filter_tgl_awal; ?>" data-input required>
                            <div class="input-group-append" data-toggle>
                                <span class="input-group-text"><i class="fas fa-calendar-alt text-muted"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="font-weight-bold small text-uppercase">Sampai Tanggal</label>
                        <div class="input-group date-picker-container">
                            <input type="text" name="tgl_akhir" class="form-control" value="<?= $filter_tgl_akhir; ?>" data-input required>
                            <div class="input-group-append" data-toggle>
                                <span class="input-group-text"><i class="fas fa-calendar-alt text-muted"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="font-weight-bold small text-uppercase">Sumber Transaksi</label>
                        <select name="sumber" class="form-control">
                            <option value="">-- Semua --</option>
                            <option value="Penerimaan" <?= $filter_sumber == 'Penerimaan' ? 'selected' : ''; ?>>Penerimaan</option>
                            <option value="Transfer" <?= $filter_sumber == 'Transfer' ? 'selected' : ''; ?>>Transfer</option>
                            <option value="Resep" <?= $filter_sumber == 'Resep' ? 'selected' : ''; ?>>Resep</option>
                            <option value="Stok Opname" <?= $filter_sumber == 'Stok Opname' ? 'selected' : ''; ?>>Stok Opname</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="col-12 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary mr-2 shadow-sm">
                            <i class="fas fa-filter"></i> Tampilkan
                        </button>
                        <a href="keluar_masuk_polibelakang.php" class="btn btn-secondary shadow-sm">
                            <i class="fas fa-sync-alt"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($active_tab == 'stok'): ?>
    <!-- ══════════ TAB STOK ══════════ -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-left-success shadow h-100 py-2" style="border-left: .25rem solid #1cc88a !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Stok Masuk</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_masuk); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-arrow-circle-down fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-left-danger shadow h-100 py-2" style="border-left: .25rem solid #e74a3b !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Stok Keluar</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_keluar); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-arrow-circle-up fa-2x text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-left-info shadow h-100 py-2" style="border-left: .25rem solid #36b9cc !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Transaksi</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_transaksi); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-list fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                Detail Transaksi — Periode: 
                <?= date('d-m-Y', strtotime($filter_tgl_awal)); ?> s/d 
                <?= date('d-m-Y', strtotime($filter_tgl_akhir)); ?>
            </h6>
            <div>
                <?php 
                $export_params_stok = http_build_query([
                    'type'      => 'keluar_masuk_stok',
                    'tgl_awal'  => $filter_tgl_awal,
                    'tgl_akhir' => $filter_tgl_akhir,
                    'sumber'    => $filter_sumber,
                    'id_unit'   => $id_unit,
                    'nama_unit' => $nama_unit,
                ]);
                ?>
                <a href="export_excel.php?<?= $export_params_stok ?>" class="btn btn-success btn-sm mr-1">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="export_pdf.php?<?= $export_params_stok ?>" target="_blank" class="btn btn-danger btn-sm">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($logs)): ?>
                <div class="alert alert-info text-center py-4">
                    <i class="fas fa-info-circle mr-1"></i> Tidak ada transaksi pada periode ini.
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="tabelLaporan">
                    <thead class="thead-dark text-center">
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Kode Obat</th>
                            <th>Nama Obat</th>
                            <th>Sumber</th>
                            <th>Keterangan</th>
                            <th>Masuk</th>
                            <th>Keluar</th>
                            <th>Sisa Stok</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $i => $lg): 
                            $badge_class = 'badge-secondary';
                            if ($lg['sumber_data'] == 'Transfer') $badge_class = 'badge-transfer';
                            elseif ($lg['sumber_data'] == 'Resep') $badge_class = 'badge-resep';
                            elseif ($lg['sumber_data'] == 'Penerimaan') $badge_class = 'badge-penerimaan';
                            elseif ($lg['sumber_data'] == 'Stok Opname') $badge_class = 'badge-opname';
                        ?>
                        <tr>
                            <td class="text-center"><?= $i + 1; ?></td>
                            <td class="text-center small"><?= date('d-m-Y', strtotime($lg['tgl_log'])); ?></td>
                            <td class="text-center"><?= htmlspecialchars($lg['kode_obat']); ?></td>
                            <td><?= htmlspecialchars($lg['nama_obat']); ?></td>
                            <td class="text-center">
                                <span class="badge <?= $badge_class; ?> px-2 py-1">
                                    <?= htmlspecialchars($lg['sumber_data']); ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($lg['keterangan'] ?? '-'); ?></td>
                            <td class="text-right text-success font-weight-bold">
                                <?= $lg['masuk'] > 0 ? '+' . number_format($lg['masuk']) : '-'; ?>
                            </td>
                            <td class="text-right text-danger font-weight-bold">
                                <?= $lg['keluar'] > 0 ? '-' . number_format($lg['keluar']) : '-'; ?>
                            </td>
                            <td class="text-right font-weight-bold"><?= number_format($lg['stok_sesudah']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php endif;?>
</main>
    
<?php ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
$(document).ready(function() {
    $(".date-picker-container").flatpickr({
        wrap: true,
        altInput: true,
        altFormat: "d-m-Y",
        dateFormat: "Y-m-d",
        allowInput: true,
        monthSelectorType: "static",
        yearSelectorType: "static"
    });

    if ($('#tabelLaporan').length) {
        $('#tabelLaporan').DataTable({
            responsive: true,
            pageLength: 25,
            language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/id.json' }
        });
    }

    if ($('#tabelDokter').length) {
        $('#tabelDokter').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[1, 'desc']],
            language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/id.json' }
        });
    }
});
</script>
<?php
$extra_scripts = ob_get_clean();
include '../templates/footer.php';
?>