<?php
// request/kelola_gudang.php
// Digunakan oleh Role 1 (Super Admin) dan Role 2 (Admin) untuk memproses
// request stok yang masuk dari Apotek (Apotek → Gudang)

require_once '../config.php';
require_once '../templates/auth_check.php';

// Hanya Super Admin (1) dan Admin Operator (2) yang boleh
if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) {
    $_SESSION['flash_error'] = 'Akses Ditolak! Fitur ini hanya untuk Admin/Gudang.'; header("Location: " . BASE_URL . "dashboard.php"); exit;
    exit;
}

$page_title = "Kelola Request Stok dari Apotek";

try {
    $filter_status = $_GET['status_filter'] ?? 'Pending';

    $where_conditions = ["h.tipe_request = 'Apotek_ke_Gudang'"];
    $params = [];

    if (!empty($filter_status)) {
        $where_conditions[] = "h.status = ?";
        $params[] = $filter_status;
    }

    $sql_where = " WHERE " . implode(" AND ", $where_conditions);

    $sql_requests = "SELECT 
                        h.id_request, h.tgl_request, h.status, h.tgl_approve, h.keterangan_request,
                        u_req.nama_lengkap AS nama_pemohon,
                        unit_tuj.nama_unit AS nama_unit_tujuan,
                        u_app.nama_lengkap AS nama_approver,
                        (SELECT COUNT(*) FROM tbl_request_detail d WHERE d.id_request = h.id_request) AS jumlah_item
                    FROM tbl_request_header h
                    JOIN tbl_user u_req ON h.id_user_request = u_req.id_user
                    JOIN tbl_unit unit_tuj ON h.id_unit_tujuan = unit_tuj.id_unit
                    LEFT JOIN tbl_user u_app ON h.id_user_approve = u_app.id_user
                    $sql_where
                    ORDER BY h.id_request DESC";

    $stmt = $pdo->prepare($sql_requests);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}

include '../templates/header.php';
?>

<div class="container-fluid">

    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    <p class="mb-4">
        Halaman ini menampilkan antrian permintaan stok dari <strong>Apotek</strong> ke <strong>Gudang</strong>.
        Saat disetujui, stok akan otomatis dipotong dari Gudang dan ditambahkan ke Apotek.
    </p>

    <?php
    $sql_count_partial_gudang = "SELECT COUNT(*) FROM tbl_request_header WHERE status = 'Partial' AND tipe_request = 'Apotek_ke_Gudang'";
    $partial_count_gudang = $pdo->query($sql_count_partial_gudang)->fetchColumn();
    if ($partial_count_gudang > 0): ?>
        <div class="alert alert-danger shadow-sm border-left-danger animated--grow-in mb-4" role="alert">
            <h5 class="alert-heading font-weight-bold mb-1"><i class="fas fa-exclamation-triangle"></i> Peringatan Stok Parsial (Belum Lunas)!</h5>
            <p class="mb-0">Terdapat <strong><?php echo $partial_count_gudang; ?></strong> request obat dari Apotek yang belum lunas (status <strong>Partial</strong>). Silakan filter tabel berdasarkan status "Partial" dan klik tombol Detail untuk segera memproses sisa obat yang belum terkirim setelah stok Gudang mencukupi.</p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['status_aksi'])): ?>
        <?php if ($_GET['status_aksi'] == 'sukses_approve'): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle"></i> Request berhasil diproses! Stok Gudang telah ditransfer ke Apotek.
            </div>
        <?php elseif ($_GET['status_aksi'] == 'gagal'): ?>
            <div class="alert alert-danger" role="alert">
                <strong>Operasi Gagal!</strong> <?php echo isset($_GET['msg']) ? htmlspecialchars(urldecode($_GET['msg'])) : 'Silakan coba lagi.'; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Filter -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter"></i> Filter Request</h6>
        </div>
        <div class="card-body">
            <form action="<?php echo BASE_URL; ?>request/kelola_gudang.php" method="GET">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="status_filter">Filter Status:</label>
                        <select name="status_filter" id="status_filter" class="form-control">
                            <option value="Pending"   <?php echo ($filter_status == 'Pending')   ? 'selected' : ''; ?>>Hanya Tampil Pending</option>
                            <option value="Completed" <?php echo ($filter_status == 'Completed') ? 'selected' : ''; ?>>Hanya Tampil Selesai</option>
                            <option value=""          <?php echo ($filter_status == '')           ? 'selected' : ''; ?>>Tampilkan Semua</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-icon-split mr-2">
                            <span class="icon text-white-50"><i class="fas fa-search"></i></span>
                            <span class="text">Filter</span>
                        </button>
                        <a href="<?php echo BASE_URL; ?>request/kelola_gudang.php" class="btn btn-secondary btn-icon-split">
                            <span class="icon text-white-50"><i class="fas fa-sync-alt"></i></span>
                            <span class="text">Reset</span>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Request -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-warehouse"></i> Antrian Request Apotek → Gudang
                <span class="badge badge-<?php echo ($filter_status == 'Pending') ? 'warning' : (($filter_status == 'Completed') ? 'success' : 'info'); ?> ml-2">
                    <?php echo $filter_status ? htmlspecialchars($filter_status) : 'Semua'; ?>
                </span>
            </h6>
            <span class="text-muted small"><?php echo count($requests); ?> request ditemukan</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                                <table class="table table-bordered table-striped" id="dataTable" width="100%" cellspacing="0">
                    <thead class="thead-light">
                        <tr>
                            <th>ID</th>
                            <th>Tgl Request</th>
                            <th>Pemohon (Apotek)</th>
                            <th>Jml Item Obat</th>
                            <th>Keterangan</th>
                            <th>Status</th>
                            <th style="width:22%;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($requests)): ?>
                            <?php foreach ($requests as $req): ?>
                                <?php $is_pending = ($req['status'] == 'Pending'); ?>
                                <tr class="<?php echo $is_pending ? 'table-warning' : ''; ?>">
                                    <td><strong>#<?php echo $req['id_request']; ?></strong></td>
                                    <td><?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($req['tgl_request']))); ?></td>
                                    <td><?php echo htmlspecialchars($req['nama_pemohon']); ?></td>
                                    <td class="text-center">
                                        <span class="badge badge-info"><?php echo $req['jumlah_item']; ?> item</span>
                                    </td>
                                    <td class="text-muted small">
                                        <?php echo htmlspecialchars($req['keterangan_request'] ?: '-'); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status = $req['status'];
                                        $badge_class = 'badge-secondary';
                                        if ($status == 'Pending')   $badge_class = 'badge-warning';
                                        if ($status == 'Completed') $badge_class = 'badge-success';
                                        if ($status == 'Cancelled') $badge_class = 'badge-danger';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>" style="font-size:0.85rem;">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                        <?php if (!$is_pending): ?>
                                            <div class="text-muted small mt-1">
                                                Oleh: <?php echo htmlspecialchars($req['nama_approver'] ?? 'N/A'); ?><br>
                                                <?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($req['tgl_approve']))); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>request/detail.php?id=<?php echo $req['id_request']; ?>"
                                           class="btn btn-info btn-sm btn-icon-split">
                                            <span class="icon text-white-50"><i class="fas fa-eye"></i></span>
                                            <span class="text">Detail</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<?php ob_start(); ?>
<script>
$(document).ready(function () {
    $('#dataTable').DataTable({
        "order": [[0, "desc"]],
        "language": {
            "search": "Cari:",
            "lengthMenu": "Tampilkan _MENU_ data",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            "infoEmpty": "Menampilkan 0 sampai 0 dari 0 data",
            "infoFiltered": "(disaring dari _MAX_ total data)",
            "paginate": {
                "first": "Pertama", "last": "Terakhir",
                "next": "Selanjutnya", "previous": "Sebelumnya"
            },
            "zeroRecords": "Tidak ada data yang cocok"
        }
    });

    <?php if (isset($_GET['status_aksi'])): ?>
        var cleanUrl = "<?php echo BASE_URL . 'request/kelola_gudang.php'; ?>";
        var currentFilters = "status_filter=<?php echo urlencode($filter_status); ?>";
        window.history.pushState({path: cleanUrl + '?' + currentFilters}, '', cleanUrl + '?' + currentFilters);
    <?php endif; ?>
});
</script>
<?php $extra_scripts = ob_get_clean(); ?>

<?php include '../templates/footer.php'; ?>