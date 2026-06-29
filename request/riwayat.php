<?php
// request/riwayat.php
// Menampilkan riwayat request sesuai role:
//   - Role 4 (Poli Belakang) : hanya request milik sendiri (Poli→Apotek)
//   - Role 5 (Apotek)        : hanya request masuk dari semua Poli → Apotek
//   - Role 1/2 (Admin)       : hanya request dari Apotek → Gudang

require_once '../config.php';
require_once '../templates/auth_check.php';

if ($_SESSION['role_id'] == 3) {
    $_SESSION['flash_error'] = 'Akses Ditolak!'; header("Location: " . BASE_URL . "dashboard.php"); exit;
    exit;
}

$page_title = "Riwayat Request Stok";

$riwayat_requests = [];
$filter_status    = $_GET['status_filter'] ?? '';
$filter_user_id   = $_GET['filter_user']   ?? '';  // hanya untuk Admin filter per pemohon (apotek)
$apotek_users     = [];
$count_belum_terpenuhi = 0; // pengingat khusus role Apotek

try {
    // ---- Pengingat: hitung request Poli->Apotek yang belum terpenuhi (Pending/Partial) ----
    // Dihitung terlepas dari filter yang sedang aktif, supaya pengingat selalu menunjukkan jumlah sebenarnya.
    if ($_SESSION['role_id'] == 5) {
        $stmt_reminder = $pdo->prepare(
            "SELECT COUNT(*) AS jumlah
             FROM tbl_request_header
             WHERE tipe_request = 'Poli_ke_Apotek'
               AND status IN ('Pending', 'Partial')"
        );
        $stmt_reminder->execute();
        $count_belum_terpenuhi = (int) $stmt_reminder->fetch()['jumlah'];
    }

    // Admin (1/2): ambil daftar user Apotek untuk filter pemohon
    if ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2) {
        $stmt_apotek = $pdo->query(
            "SELECT u.id_user, u.nama_lengkap
             FROM tbl_user u
             WHERE u.id_role = 5
             ORDER BY u.nama_lengkap ASC"
        );
        $apotek_users = $stmt_apotek->fetchAll();
    }

    $sql_base = "FROM tbl_request_header h
                 JOIN tbl_user u_req ON h.id_user_request = u_req.id_user
                 JOIN tbl_unit unit_tuj ON h.id_unit_tujuan = unit_tuj.id_unit
                 LEFT JOIN tbl_user u_app ON h.id_user_approve = u_app.id_user";

    $where_conditions = [];
    $params = [];

    // ---- Batasi data berdasarkan role ----
    if ($_SESSION['role_id'] == 4) {
        // Poli Belakang: hanya request yang dia buat sendiri ke Apotek
        $where_conditions[] = "h.id_user_request = ?";
        $where_conditions[] = "h.tipe_request = 'Poli_ke_Apotek'";
        $params[] = $_SESSION['user_id'];

    } elseif ($_SESSION['role_id'] == 5) {
        // Apotek: hanya melihat request yang masuk dari semua Poli
        $where_conditions[] = "h.tipe_request = 'Poli_ke_Apotek'";

    } else {
        // Admin (1/2): hanya melihat request dari Apotek ke Gudang
        $where_conditions[] = "h.tipe_request = 'Apotek_ke_Gudang'";

        // Opsional filter per pemohon (user Apotek)
        if (!empty($filter_user_id)) {
            $where_conditions[] = "h.id_user_request = ?";
            $params[] = $filter_user_id;
        }
    }

    // Filter status berlaku untuk semua role
    if (!empty($filter_status)) {
        $where_conditions[] = "h.status = ?";
        $params[] = $filter_status;
    }

    $sql_where = !empty($where_conditions) ? " WHERE " . implode(" AND ", $where_conditions) : "";

    $sql_resep = "SELECT
                    h.id_request, h.tgl_request, h.status, h.tgl_approve,
                    h.tipe_request,
                    u_req.nama_lengkap AS nama_pemohon,
                    unit_tuj.nama_unit AS nama_unit_tujuan,
                    u_app.nama_lengkap AS nama_approver
                  $sql_base
                  $sql_where
                  ORDER BY h.id_request DESC";

    $stmt_resep = $pdo->prepare($sql_resep);
    $stmt_resep->execute($params);
    $riwayat_requests = $stmt_resep->fetchAll();

} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}

include '../templates/header.php';
?>

<div class="container-fluid">

    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>

    <?php
    // Hitung request partial
    $sql_count_partial = "SELECT COUNT(*) FROM tbl_request_header h WHERE h.status = 'Partial'";
    if ($_SESSION['role_id'] == 4) {
        $sql_count_partial .= " AND h.id_user_request = {$_SESSION['user_id']} AND h.tipe_request = 'Poli_ke_Apotek'";
    } elseif ($_SESSION['role_id'] == 5) {
        $sql_count_partial .= " AND h.tipe_request = 'Poli_ke_Apotek'";
    } else {
        $sql_count_partial .= " AND h.tipe_request = 'Apotek_ke_Gudang'";
    }
    $partial_count = $pdo->query($sql_count_partial)->fetchColumn();
    ?>

    <?php if ($partial_count > 0): ?>
        <div class="alert alert-danger shadow-sm border-left-danger animated--grow-in" role="alert">
            <h5 class="alert-heading font-weight-bold mb-1"><i class="fas fa-exclamation-triangle"></i> Peringatan Stok Parsial (Belum Lunas)!</h5>
            <p class="mb-0">Terdapat <strong><?php echo $partial_count; ?></strong> request obat yang pengirimannya belum lunas (status <strong>Partial</strong>). Silakan filter tabel berdasarkan status "Partial" dan klik tombol Detail untuk segera memproses sisa obat yang belum terkirim.</p>
        </div>
    <?php endif; ?>    <!-- Alert info sesuai role -->
    <?php if ($_SESSION['role_id'] == 4): ?>
        <div class="alert alert-info shadow" role="alert">
            Menampilkan semua permintaan stok yang <strong>Anda ajukan</strong> ke Apotek.
        </div>
    <?php elseif ($_SESSION['role_id'] == 5): ?>
        <div class="alert alert-info shadow" role="alert">
            Menampilkan riwayat request masuk dari <strong>semua Poli</strong> ke Apotek.
        </div>
    <?php else: ?>
        <div class="alert alert-info shadow" role="alert">
            Menampilkan riwayat request yang diajukan <strong>Apotek ke Gudang</strong>.
        </div>
    <?php endif; ?>

    <!-- Filter Status (semua role) + Filter Pemohon (Admin saja) -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter"></i> Filter Riwayat Request</h6>
        </div>
        <div class="card-body">
            <form action="<?php echo BASE_URL; ?>request/riwayat.php" method="GET">
                <div class="form-row">

                    <?php if ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2): ?>
                    <!-- Filter pemohon (user Apotek) — hanya untuk Admin -->
                    <div class="form-group col-md-4">
                        <label for="filter_user">Filter Pemohon (Apotek):</label>
                        <select name="filter_user" id="filter_user" class="form-control">
                            <option value="">-- Semua Pemohon --</option>
                            <?php foreach ($apotek_users as $user): ?>
                                <option value="<?php echo $user['id_user']; ?>"
                                        <?php echo ($filter_user_id == $user['id_user']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['nama_lengkap']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Filter Status -->
                    <div class="form-group col-md-<?php echo ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2) ? '4' : '6'; ?>">
                        <label for="status_filter">Filter Status:</label>
                        <select name="status_filter" id="status_filter" class="form-control">
                            <option value=""          <?php echo ($filter_status == '')          ? 'selected' : ''; ?>>Semua Status</option>
                            <option value="Pending"   <?php echo ($filter_status == 'Pending')   ? 'selected' : ''; ?>>Pending</option>
                            <option value="Partial"   <?php echo ($filter_status == 'Partial')   ? 'selected' : ''; ?>>Parsial (Sebagian Terkirim)</option>
                            <option value="Completed" <?php echo ($filter_status == 'Completed') ? 'selected' : ''; ?>>Selesai</option>
                            <option value="Cancelled" <?php echo ($filter_status == 'Cancelled') ? 'selected' : ''; ?>>Batal</option>
                        </select>
                    </div>

                    <!-- Tombol -->
                    <div class="form-group col-md-<?php echo ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2) ? '4' : '6'; ?> d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-icon-split mr-2">
                            <span class="icon text-white-50"><i class="fas fa-filter"></i></span>
                            <span class="text">Filter</span>
                        </button>
                        <a href="<?php echo BASE_URL; ?>request/riwayat.php" class="btn btn-secondary btn-icon-split">
                            <span class="icon text-white-50"><i class="fas fa-sync-alt"></i></span>
                            <span class="text">Reset</span>
                        </a>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Riwayat -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Riwayat Request</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="dataTable" width="100%" cellspacing="0">
                    <thead class="thead-light">
                        <tr>
                            <th>ID</th>
                            <th>Tgl Request</th>
                            <?php if ($_SESSION['role_id'] != 4): ?>
                                <th>Pemohon</th>
                            <?php endif; ?>
                            <th>Tipe Alur</th>
                            <th>Unit Tujuan</th>
                            <th>Status</th>
                            <th>Diproses Oleh</th>
                            <th>Tgl Diproses</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($riwayat_requests)): ?>
                            <?php foreach ($riwayat_requests as $req): ?>
                                <tr class="<?php echo ($req['status'] == 'Pending') ? 'table-warning' : ''; ?>">
                                    <td>#<?php echo $req['id_request']; ?></td>
                                    <td><?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($req['tgl_request']))); ?></td>

                                    <?php if ($_SESSION['role_id'] != 4): ?>
                                        <td><?php echo htmlspecialchars($req['nama_pemohon']); ?></td>
                                    <?php endif; ?>

                                    <td>
                                        <?php if ($req['tipe_request'] == 'Apotek_ke_Gudang'): ?>
                                            <span class="badge badge-primary">Apotek → Gudang</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Poli → Apotek</span>
                                        <?php endif; ?>
                                    </td>

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
                                        <span class="badge <?php echo $badge_class; ?>" style="font-size:0.85rem;">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($req['nama_approver'] ?? '---'); ?></td>
                                    <td>
                                        <?php echo $req['tgl_approve']
                                            ? htmlspecialchars(date('d-m-Y H:i', strtotime($req['tgl_approve'])))
                                            : '---'; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>request/detail.php?id=<?php echo $req['id_request']; ?>"
                                           class="btn btn-info btn-sm btn-icon-split">
                                            <span class="icon text-white-50"><i class="fas fa-eye"></i></span>
                                            <span class="text">Lihat</span>
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
});
</script>
<?php
$extra_scripts = ob_get_clean();
include '../templates/footer.php';
?>