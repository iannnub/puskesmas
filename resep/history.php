<?php
require_once '../config.php';
require_once '../templates/auth_check.php';

// Hanya Poli Belakang (role 4) dan Admin (role 2) yang boleh akses
if ($_SESSION['role_id'] != 4 && $_SESSION['role_id'] != 2) {
    $_SESSION['flash_error'] = 'Akses Ditolak!'; header("Location: " . BASE_URL . "dashboard.php"); exit;
    exit;
}

$page_title = "History Pemakaian Obat";

// ── Filter ──
$filter_tgl_awal  = $_GET['tgl_awal']  ?? date('Y-m-01');
$filter_tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
$filter_id_dokter = $_GET['id_dokter'] ?? '';

// Jika poli belakang, hanya lihat data poli sendiri
$where_poli = '';
$params_poli = [];
if ($_SESSION['role_id'] == 4) {
    $where_poli = "AND rh.id_poli = ?";
    $params_poli[] = $_SESSION['poli_id'];
}

try {
    // Ambil daftar dokter untuk dropdown filter
    $stmt_dok = $pdo->query("SELECT id_dokter, nama_dokter FROM tbl_dokter ORDER BY nama_dokter ASC");
    $list_dokter = $stmt_dok->fetchAll();

    // Query history resep
    $sql = "
        SELECT 
            rh.id_resep,
            rh.tgl_resep,
            rh.nama_pasien,
            rh.kelengkapan_resep,
            rh.kesalahan_resep,
            rh.sesuai_formularium,
            d.id_dokter,
            d.nama_dokter,
            p.nama_poli,
            pel.jenis_pelayanan,
            u.nama_lengkap AS nama_pencatat,
            COUNT(rd.id_resep_detail) AS jumlah_item,
            SUM(rd.jumlah_keluar) AS total_qty
        FROM tbl_resep_header rh
        LEFT JOIN tbl_dokter d       ON rh.id_dokter = d.id_dokter
        JOIN tbl_poli p              ON rh.id_poli = p.id_poli
        JOIN tbl_pelayanan pel       ON rh.id_pelayanan = pel.id_pelayanan
        JOIN tbl_user u              ON rh.id_user_pencatat = u.id_user
        JOIN tbl_resep_detail rd     ON rh.id_resep = rd.id_resep
        WHERE rh.tgl_resep BETWEEN ? AND ?
        $where_poli
    ";
    $params = [
        $filter_tgl_awal . ' 00:00:00',
        $filter_tgl_akhir . ' 23:59:59',
        ...$params_poli
    ];

    if (!empty($filter_id_dokter)) {
        $sql .= " AND rh.id_dokter = ?";
        $params[] = $filter_id_dokter;
    }

    $sql .= " GROUP BY rh.id_resep ORDER BY rh.tgl_resep DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $histories = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

include '../templates/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">


<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-history text-primary mr-2"></i><?= htmlspecialchars($page_title); ?>
        </h1>
        <a href="<?= BASE_URL; ?>resep/tambah.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-plus fa-sm mr-1"></i> Input Pemakaian Baru
        </a>
    </div>

    <?php if (isset($_GET['status'])): ?>
        <?php if ($_GET['status'] == 'edit_sukses'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle mr-1"></i> Data resep berhasil diperbarui!
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        <?php elseif ($_GET['status'] == 'hapus_sukses'): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-trash mr-1"></i> Data resep berhasil dihapus.
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        <?php elseif ($_GET['status'] == 'gagal'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle mr-1"></i>
                <strong>Gagal!</strong> <?= isset($_GET['msg']) ? htmlspecialchars(urldecode($_GET['msg'])) : 'Terjadi kesalahan.'; ?>
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Filter Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-1"></i> Filter History</h6>
        </div>
        <div class="card-body">
            <form action="" method="GET">
                <div class="form-row align-items-end">
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
                        <label class="font-weight-bold small text-uppercase">Filter Dokter</label>
                        <select name="id_dokter" class="form-control select2-filter">
                            <option value="">-- Semua Dokter --</option>
                            <?php foreach ($list_dokter as $dok): ?>
                                <option value="<?= $dok['id_dokter']; ?>" <?= ($filter_id_dokter == $dok['id_dokter']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($dok['nama_dokter']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-search mr-1"></i> Tampilkan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel History -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list mr-1"></i>
                Daftar Pemakaian —
                <?= date('d-m-Y', strtotime($filter_tgl_awal)); ?> s/d
                <?= date('d-m-Y', strtotime($filter_tgl_akhir)); ?>
            </h6>
        </div>
        <div class="card-body">
            <?php if (empty($histories)): ?>
                <div class="alert alert-info text-center py-4">
                    <i class="fas fa-info-circle mr-1"></i> Tidak ada data pemakaian pada periode ini.
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover table-sm" id="tabelHistory">
                    <thead class="thead-dark text-center">
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>No. Resep</th>
                            <th>Nama Pasien</th>
                            <th>Dokter</th>
                            <th>Poli</th>
                            <th>Pelayanan</th>
                            <th>Jml Item</th>
                            <th>Total Qty</th>
                            <th>Sasaran Mutu</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($histories as $i => $h): ?>
                        <tr>
                            <td class="text-center"><?= $i + 1; ?></td>
                            <td class="text-center small"><?= date('d-m-Y H:i', strtotime($h['tgl_resep'])); ?></td>
                            <td class="text-center">
                                <span class="badge badge-secondary">#<?= $h['id_resep']; ?></span>
                            </td>
                            <td><?= $h['nama_pasien'] ? htmlspecialchars($h['nama_pasien']) : '<span class="text-muted">-</span>'; ?></td>
                            <td>
                                <?php if ($h['nama_dokter']): ?>
                                    <i class="fas fa-user-md text-primary mr-1"></i><?= htmlspecialchars($h['nama_dokter']); ?>
                                <?php else: ?>
                                    <span class="text-muted"><i class="fas fa-question-circle"></i> Tidak ada</span>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= htmlspecialchars($h['nama_poli']); ?></td>
                            <td class="small"><?= htmlspecialchars($h['jenis_pelayanan']); ?></td>
                            <td class="text-center"><span class="badge badge-info"><?= $h['jumlah_item']; ?></span></td>
                            <td class="text-center font-weight-bold text-danger"><?= number_format($h['total_qty']); ?></td>
                            <td class="text-center small">
                                <?php
                                $k = ($h['kelengkapan_resep'] == 'Lengkap') ? 'badge-success' : 'badge-danger';
                                $ks = ($h['kesalahan_resep'] == 'Tidak Ada') ? 'badge-success' : 'badge-danger';
                                $f = ($h['sesuai_formularium'] == 'Sesuai') ? 'badge-success' : 'badge-danger';
                                ?>
                                <span class="badge <?= $k; ?>"><?= $h['kelengkapan_resep']; ?></span><br>
                                <span class="badge <?= $ks; ?>"><?= $h['kesalahan_resep']; ?></span><br>
                                <span class="badge <?= $f; ?>"><?= $h['sesuai_formularium']; ?></span>
                            </td>
                            <td class="text-center" style="white-space:nowrap;">
                                <a href="<?= BASE_URL; ?>resep/edit.php?id=<?= $h['id_resep']; ?>"
                                   class="btn btn-warning btn-sm" title="Edit Resep">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>



<?php ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
$(document).ready(function() {
    // Flatpickr date pickers
    $(".date-picker-container").flatpickr({
        wrap: true,
        altInput: true,
        altFormat: "d-m-Y",
        dateFormat: "Y-m-d",
        allowInput: true
    });

    // Select2 filter dokter
    $('.select2-filter').select2({ width: '100%', placeholder: '-- Semua Dokter --' });

    // DataTable
    if ($('#tabelHistory').length) {
        $('#tabelHistory').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[1, 'desc']],
            language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/id.json' }
        });
    }


    // Auto-dismiss alert setelah 4 detik
    setTimeout(function() { $('.alert-dismissible').fadeOut('slow'); }, 4000);

    // Bersihkan URL dari query status
    <?php if (isset($_GET['status'])): ?>
        var baseUrl = "<?= BASE_URL . 'resep/history.php'; ?>";
        window.history.pushState({}, '', baseUrl);
    <?php endif; ?>
});
</script>
<?php $extra_scripts = ob_get_clean(); ?>

<?php include '../templates/footer.php'; ?>