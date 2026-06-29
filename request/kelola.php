<?php
// request/kelola.php
// Digunakan oleh Role 5 (Apotek) untuk mengelola request dari Poli Belakang
// ALUR: Poli Belakang (Role 4) → REQUEST → Apotek (Role 5)

require_once '../config.php';
require_once '../templates/auth_check.php';

if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 5) {
    $_SESSION['flash_error'] = 'Akses Ditolak! Fitur ini hanya untuk Apotek.'; header("Location: " . BASE_URL . "dashboard.php"); exit;
    exit;
}

$page_title = "Kelola Request Stok dari Poli";

try {
    $stmt_users_poli = $pdo->query("SELECT id_user, nama_lengkap 
                                    FROM tbl_user 
                                    WHERE id_role = 4 
                                    ORDER BY nama_lengkap ASC");
    $poli_belakang_users = $stmt_users_poli->fetchAll();

    $filter_user_id = $_GET['filter_user'] ?? '';
    $filter_status  = $_GET['status_filter'] ?? 'Pending';

    $where_conditions = ["h.tipe_request = 'Poli_ke_Apotek'"];
    $params = [];

    if (!empty($filter_user_id)) {
        $where_conditions[] = "h.id_user_request = ?";
        $params[] = $filter_user_id;
    }
    if (!empty($filter_status)) {
        $where_conditions[] = "h.status = ?";
        $params[] = $filter_status;
    }

    $sql_where = " WHERE " . implode(" AND ", $where_conditions);

    $sql_requests = "SELECT 
                        h.id_request, h.tgl_request, h.status, h.tgl_approve,
                        u_req.nama_lengkap AS nama_pemohon,
                        unit_tuj.nama_unit AS nama_unit_tujuan,
                        u_app.nama_lengkap AS nama_approver
                    FROM tbl_request_header h
                    JOIN tbl_user u_req ON h.id_user_request = u_req.id_user
                    JOIN tbl_unit unit_tuj ON h.id_unit_tujuan = unit_tuj.id_unit
                    LEFT JOIN tbl_user u_app ON h.id_user_approve = u_app.id_user
                    $sql_where
                    ORDER BY h.id_request ASC";

    $stmt_requests = $pdo->prepare($sql_requests);
    $stmt_requests->execute($params);
    $requests = $stmt_requests->fetchAll();

} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}

include '../templates/header.php';
?>

<div class="container-fluid">

    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    <p class="mb-4">
        Halaman ini menampilkan antrian permintaan stok yang masuk ke <strong>Apotek</strong> dari unit Poli Belakang (UGD, Rawat Inap, KB PONED, dll).
        Saat diproses, stok akan <strong>otomatis dipotong dari Apotek</strong> dan dikirim ke unit pemohon.
    </p>

    <?php
    $sql_count_partial = "SELECT COUNT(*) FROM tbl_request_header WHERE status = 'Partial' AND tipe_request = 'Poli_ke_Apotek'";
    $partial_count = $pdo->query($sql_count_partial)->fetchColumn();
    if ($partial_count > 0): ?>
        <div class="alert alert-danger shadow-sm border-left-danger animated--grow-in mb-4" role="alert">
            <h5 class="alert-heading font-weight-bold mb-1"><i class="fas fa-exclamation-triangle"></i> Peringatan Stok Parsial (Belum Lunas)!</h5>
            <p class="mb-0">Terdapat <strong><?php echo $partial_count; ?></strong> request obat dari Poli yang belum lunas (status <strong>Partial</strong>) karena stok Apotek sebelumnya tidak cukup. Silakan proses sisa obat tersebut setelah Apotek di-restock dari Gudang.</p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['status_aksi'])): ?>
        <?php if ($_GET['status_aksi'] == 'sukses_approve'): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle"></i> Request berhasil diproses! Stok telah ditransfer ke unit Poli.
            </div>
        <?php elseif ($_GET['status_aksi'] == 'gagal'): ?>
            <div class="alert alert-danger" role="alert">
                <strong>Operasi Gagal!</strong> <?php echo isset($_GET['msg']) ? htmlspecialchars(urldecode($_GET['msg'])) : 'Silakan coba lagi.'; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter"></i> Filter Antrian Request</h6>
                </div>
                <div class="card-body">
                    <form action="<?php echo BASE_URL; ?>request/kelola.php" method="GET">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="filter_user">Filter Pemohon:</label>
                                <select name="filter_user" id="filter_user" class="form-control select2-user">
                                    <option value="">-- Semua Pemohon --</option>
                                    <?php foreach ($poli_belakang_users as $user): ?>
                                        <option value="<?php echo $user['id_user']; ?>"
                                                <?php echo ($filter_user_id == $user['id_user']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['nama_lengkap']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="status_filter">Filter Status:</label>
                                <select name="status_filter" id="status_filter" class="form-control select2-static">
                                    <option value="Pending"   <?php echo ($filter_status == 'Pending')   ? 'selected' : ''; ?>>Hanya Tampil Pending</option>
                                    <option value="Partial"   <?php echo ($filter_status == 'Partial')   ? 'selected' : ''; ?>>Hanya Tampil Parsial</option>
                                    <option value="Completed" <?php echo ($filter_status == 'Completed') ? 'selected' : ''; ?>>Hanya Tampil Selesai</option>
                                    <option value=""          <?php echo ($filter_status == '')           ? 'selected' : ''; ?>>Tampilkan Semua</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-icon-split mr-2">
                                    <span class="icon text-white-50"><i class="fas fa-search"></i></span>
                                    <span class="text">Filter</span>
                                </button>
                                <a href="<?php echo BASE_URL; ?>request/kelola.php" class="btn btn-secondary btn-icon-split">
                                    <span class="icon text-white-50"><i class="fas fa-sync-alt"></i></span>
                                    <span class="text">Reset</span>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-tasks"></i> Daftar Request Poli → Apotek
                        (Filter: <?php echo htmlspecialchars($filter_status ?: 'Semua'); ?>)
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="dataTable" width="100%" cellspacing="0">
                            <thead class="thead-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Tgl Request</th>
                                    <th>Pemohon (User Poli)</th>
                                    <th>Unit Tujuan</th>
                                    <th>Status</th>
                                    <th style="width:15%;">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($requests)): ?>
                                    <?php foreach ($requests as $req): ?>
                                        <?php $is_pending = ($req['status'] == 'Pending'); ?>
                                        <tr class="<?php echo $is_pending ? 'table-warning' : ''; ?>">
                                            <td>#<?php echo $req['id_request']; ?></td>
                                            <td><?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($req['tgl_request']))); ?></td>
                                            <td><?php echo htmlspecialchars($req['nama_pemohon']); ?></td>
                                            <td><?php echo htmlspecialchars($req['nama_unit_tujuan']); ?></td>
                                            <td>
                                                <?php
                                                $status = $req['status'];
                                                $badge_class = 'badge-secondary';
                                                if ($status == 'Pending')   $badge_class = 'badge-warning';
                                                if ($status == 'Partial')   $badge_class = 'badge-info';
                                                if ($status == 'Completed') $badge_class = 'badge-success';
                                                if ($status == 'Cancelled') $badge_class = 'badge-danger';
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>" style="font-size:0.9rem;">
                                                    <?php echo htmlspecialchars($status); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!$is_pending): ?>
                                                    <div class="text-muted small mb-1">
                                                        Oleh: <?php echo htmlspecialchars($req['nama_approver'] ?? 'N/A'); ?>
                                                    </div>
                                                <?php endif; ?>
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
    </div>
</div>


<?php ob_start(); ?>
<script>
$(document).ready(function () {
    $('#dataTable').DataTable({
        "order": [[0, "asc"]],
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

    $('.select2-user').select2({ placeholder: "-- Semua Pemohon --", width: '100%' });
    $('.select2-static').select2({ width: '100%', minimumResultsForSearch: Infinity });

    <?php if (isset($_GET['status_aksi'])): ?>
        var cleanUrl = "<?php echo BASE_URL . 'request/kelola.php'; ?>";
        var currentFilters = "<?php echo http_build_query(['filter_user' => $filter_user_id, 'status_filter' => $filter_status]); ?>";
        window.history.pushState({path: cleanUrl + '?' + currentFilters}, '', cleanUrl + '?' + currentFilters);
    <?php endif; ?>
});
</script>
<?php $extra_scripts = ob_get_clean(); ?>

<?php include '../templates/footer.php'; ?>