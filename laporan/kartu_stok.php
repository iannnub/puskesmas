<?php
require_once '../config.php';
require_once '../templates/auth_check.php';

if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) {
    echo "<script>alert('Akses Ditolak! Anda bukan Super Admin atau Admin.'); window.location.href = '" . BASE_URL . "dashboard.php';</script>";
    exit;
}

$page_title = "Laporan Kartu Stok";

$logs = [];
$stok_awal = 0;
$data_obat = null;
$data_unit = null;

$filter_obat_id = $_GET['id_obat'] ?? null;
$filter_unit_id = $_GET['id_unit'] ?? null;
$filter_tgl_awal = $_GET['tgl_awal'] ?? date('Y-m-01');
$filter_tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-t');

try {
    $units = $pdo->query("SELECT id_unit, nama_unit FROM tbl_unit ORDER BY nama_unit ASC")->fetchAll();
    $all_obats = $pdo->query("SELECT id_obat, kode_obat, nama_obat FROM tbl_obat ORDER BY id_obat ASC")->fetchAll();

    if ($filter_obat_id && $filter_unit_id) {
        $stmt = $pdo->prepare("SELECT nama_obat, kode_obat, satuan FROM tbl_obat WHERE id_obat = ?");
        $stmt->execute([$filter_obat_id]);
        $data_obat = $stmt->fetch();

        $stmt2 = $pdo->prepare("SELECT nama_unit FROM tbl_unit WHERE id_unit = ?");
        $stmt2->execute([$filter_unit_id]);
        $data_unit = $stmt2->fetch();

        $stmt3 = $pdo->prepare("
            SELECT stok_sesudah FROM tbl_log_stok 
            WHERE id_obat = ? AND id_unit = ? AND tgl_log < ? 
            ORDER BY id_log DESC LIMIT 1
        ");
        $stmt3->execute([$filter_obat_id, $filter_unit_id, $filter_tgl_awal . " 00:00:00"]);
        $prev = $stmt3->fetch();

        $stok_awal = $prev['stok_sesudah'] ?? 0;

        $stmt4 = $pdo->prepare("
            SELECT * FROM tbl_log_stok
            WHERE id_obat = ? AND id_unit = ?
              AND (tgl_log BETWEEN ? AND ?)
            ORDER BY id_log ASC
        ");
        $stmt4->execute([
            $filter_obat_id,
            $filter_unit_id,
            $filter_tgl_awal . " 00:00:00",
            $filter_tgl_akhir . " 23:59:59"
        ]);
        $logs = $stmt4->fetchAll();
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

include '../templates/header.php';
?>

<!-- CSS Fix Select2 / layout -->
<style>
.select2-container .select2-selection--single {
    height: calc(2.25rem + 2px) !important;
    padding: 0.45rem 0.75rem !important;
    border: 1px solid #ced4da !important;
    border-radius: .35rem !important;
    display: flex !important;
    align-items: center !important;
}
.select2-selection__rendered { line-height: 1.4 !important; padding-left: 0 !important; }
.select2-selection__arrow { height: 100% !important; right: 10px !important; top: 8px !important; }
.select2-container--default .select2-selection--single .select2-selection__clear { display: none !important; }
</style>

<main class="content">
    <h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title); ?></h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Kartu Stok</h6>
        </div>

        <div class="card-body">
            <form action="<?= BASE_URL; ?>laporan/kartu_stok.php" method="GET">
                <div class="form-row">
                    <div class="col-md-6">
                        <label class="font-weight-bold">Pilih Obat (Wajib)</label>
                        <select id="id_obat_filter" name="id_obat" class="form-control" required>
                            <option value="">-- Pilih / Cari Obat --</option>
                            <?php foreach ($all_obats as $o): ?>
                                <option value="<?= $o['id_obat']; ?>" <?= ($filter_obat_id == $o['id_obat']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($o['kode_obat'] . " - " . $o['nama_obat']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="font-weight-bold">Pilih Unit (Wajib)</label>
                        <select id="id_unit_filter" name="id_unit" class="form-control" required>
                            <option value="">-- Pilih Unit Lokasi --</option>
                            <?php foreach ($units as $u): ?>
                                <option value="<?= $u['id_unit']; ?>" <?= ($filter_unit_id == $u['id_unit']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($u['nama_unit']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row mt-3">
                    <div class="col-md-3">
                        <label>Dari Tanggal</label>
                        <input type="date" name="tgl_awal" class="form-control" value="<?= htmlspecialchars($filter_tgl_awal); ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label>Sampai Tanggal</label>
                        <input type="date" name="tgl_akhir" class="form-control" value="<?= htmlspecialchars($filter_tgl_akhir); ?>" required>
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-filter"></i> Tampilkan Laporan</button>
                        <a href="<?= BASE_URL; ?>laporan/kartu_stok.php" class="btn btn-secondary"><i class="fas fa-sync-alt"></i> Reset Filter</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($filter_obat_id && $filter_unit_id && $data_obat && $data_unit): ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                Kartu Stok: <?= htmlspecialchars($data_obat['nama_obat']); ?> (<?= htmlspecialchars($data_unit['nama_unit']); ?>)
            </h6>
            <div>
                <button class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> Excel</button>
                <button class="btn btn-danger btn-sm"><i class="fas fa-file-pdf"></i> PDF</button>
            </div>
        </div>

        <div class="card-body">
            <p><strong>Periode:</strong> <?= date('d-m-Y', strtotime($filter_tgl_awal)); ?> s/d <?= date('d-m-Y', strtotime($filter_tgl_akhir)); ?></p>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="thead-dark">
                        <tr><th>Tanggal</th><th>Sumber</th><th>Keterangan</th><th>Masuk</th><th>Keluar</th><th>Sisa Stok</th></tr>
                    </thead>
                    <tbody>
                        <tr class="table-secondary">
                            <td colspan="5" class="text-right font-weight-bold">Stok Awal</td>
                            <td class="text-right font-weight-bold"><?= $stok_awal; ?></td>
                        </tr>

                        <?php
                        // Pastikan $saldo selalu terdefinisi
                        $saldo = $stok_awal;
                        if (empty($logs)):
                        ?>
                            <tr><td colspan="6" class="text-center">Tidak ada mutasi stok.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $lg): ?>
                                <?php $saldo = $lg['stok_sesudah']; ?>
                                <tr>
                                    <td><?= date('d-m-Y H:i', strtotime($lg['tgl_log'])); ?></td>
                                    <td><?= htmlspecialchars($lg['sumber_data']); ?></td>
                                    <td><?= htmlspecialchars($lg['keterangan']); ?></td>
                                    <td class="text-success text-right"><?= $lg['masuk'] > 0 ? "+" . $lg['masuk'] : ""; ?></td>
                                    <td class="text-danger text-right"><?= $lg['keluar'] > 0 ? "-" . $lg['keluar'] : ""; ?></td>
                                    <td class="text-right font-weight-bold"><?= $saldo; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="thead-dark">
                        <tr>
                            <td colspan="5" class="text-right font-weight-bold">Stok Akhir</td>
                            <td class="text-right font-weight-bold h5"><?= $saldo; ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <?php elseif (isset($_GET['id_obat']) || isset($_GET['id_unit'])): ?>

        <div class="alert alert-warning">Silakan pilih <strong>Obat</strong> dan <strong>Unit</strong> terlebih dahulu.</div>

    <?php endif; ?>

</main>

<?php include '../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#id_obat_filter').select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: "-- Pilih Obat --"
    });

    $('#id_unit_filter').select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: "-- Pilih Unit --"
    });
});
</script>
