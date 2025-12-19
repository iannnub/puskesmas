<?php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. Panggil "satpam" auth_check.php
require_once '../templates/auth_check.php';

// 3. (SATPAM 2: ROLE CHECK)
if ($_SESSION['role_id'] == 3) {
    echo "<script>alert('Akses Ditolak!'); window.location.href = '" . BASE_URL . "dashboard.php';</script>";
    exit;
}

// 4. Set Judul Halaman
$page_title = "Riwayat Request Stok";

// 5. [LOGIKA UTAMA] AMBIL DATA (READ) + FILTER
$riwayat_requests = []; // Array untuk menampung hasil

// Ambil nilai filter dari URL (jika ada)
// $filter_poli_id DIHAPUS
$filter_user_id = $_GET['filter_user'] ?? ''; // HANYA INI FILTERNYA
$filter_status = $_GET['status_filter'] ?? ''; 

try {
    // [A] Ambil data (untuk dropdown Filter Admin/Super Admin)
    $poli_belakang_users = []; // HANYA user yg bisa request
    
    if ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2) {
        
        // ===================================================================
        // QUERY-NYA DI-UPGRADE:
        // Ambil User (Role 4) + Langsung JOIN ke Nama Poli-nya
        // ===================================================================
        $stmt_users_poli = $pdo->query("SELECT u.id_user, u.nama_lengkap, p.nama_poli 
                                        FROM tbl_user u
                                        JOIN tbl_poli p ON u.id_poli = p.id_poli
                                        WHERE u.id_role = 4 
                                        ORDER BY u.nama_lengkap ASC");
        $poli_belakang_users = $stmt_users_poli->fetchAll();
        // ===================================================================
    }

    // [B] Bangun Query Dinamis
    $sql_base = "FROM 
                    tbl_request_header h
                JOIN 
                    tbl_user u_req ON h.id_user_request = u_req.id_user
                JOIN 
                    tbl_unit unit_tuj ON h.id_unit_tujuan = unit_tuj.id_unit
                LEFT JOIN
                    tbl_user u_app ON h.id_user_approve = u_app.id_user";
    
    $where_conditions = []; 
    $params = []; 

    // [C] (KUNCI LOGIKA RBAC) Tentukan Filter berdasarkan Role
    if ($_SESSION['role_id'] == 4) {
        // --- POLI BELAKANG (Role 4) ---
        $where_conditions[] = "h.id_user_request = ?";
        $params[] = $_SESSION['user_id'];
    } else {
        // --- ADMIN / SUPER ADMIN (Role 1 & 2) ---
        
        // Filter: Berdasarkan PEMOHON (Individu: Budi, Ani, dll)
        if (!empty($filter_user_id)) {
            $where_conditions[] = "h.id_user_request = ?";
            $params[] = $filter_user_id;
        }
    }
    
    // [D] Terapkan filter STATUS
    if (!empty($filter_status)) {
        $where_conditions[] = "h.status = ?";
        $params[] = $filter_status;
    }

    // Gabungkan kondisi WHERE
    $sql_where = "";
    if (!empty($where_conditions)) {
        $sql_where = " WHERE " . implode(" AND ", $where_conditions);
    }
    
    // [E] Eksekusi Query
    $sql_resep = "SELECT 
                    h.id_request, h.tgl_request, h.status, h.tgl_approve,
                    u_req.nama_lengkap AS nama_pemohon,
                    unit_tuj.nama_unit AS nama_unit_tujuan,
                    u_app.nama_lengkap AS nama_approver
                    " . $sql_base . $sql_where . "
                ORDER BY 
                    h.id_request DESC"; 
    
    $stmt_resep = $pdo->prepare($sql_resep);
    $stmt_resep->execute($params);
    $riwayat_requests = $stmt_resep->fetchAll();

} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}

// 6. Panggil Header & Sidebar
include '../templates/header.php';
?>

<div class="container-fluid">

    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    
    <?php if ($_SESSION['role_id'] == 4): ?>
        <div class="alert alert-info shadow" role="alert">
            Halaman ini menampilkan riwayat semua permintaan stok yang <strong>Anda ajukan</strong>.
        </div>
    <?php else: ?>
        <div class="alert alert-info shadow" role="alert">
            Halaman ini menampilkan riwayat semua permintaan stok dari <strong>Poli Belakang</strong> (UGD, PONED, Rawat Inap).
        </div>
    <?php endif; ?>

    <?php if ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-filter"></i> Filter Riwayat Request
            </h6>
        </div>
        <div class="card-body">
            <form action="<?php echo BASE_URL; ?>request/riwayat.php" method="GET">
                <div class="form-row">

                    <div class="form-group col-md-4">
                        <label for="filter_user">Filter Pemohon:</label>
                        <select name="filter_user" id="filter_user" class="form-control">
                            <option value="">-- Semua Pemohon --</option>
                            <?php foreach ($poli_belakang_users as $user): ?>
                                <option value="<?php echo $user['id_user']; ?>" 
                                        <?php echo ($filter_user_id == $user['id_user']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['nama_lengkap']); ?> (<?php echo htmlspecialchars($user['nama_poli']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group col-md-4">
                        <label for="status_filter">Filter Status:</label>
                        <select name="status_filter" id="status_filter" class="form-control">
                            <option value="" <?php echo ($filter_status == '') ? 'selected' : ''; ?>>Tampilkan Semua</option>
                            <option value="Pending" <?php echo ($filter_status == 'Pending') ? 'selected' : ''; ?>>Hanya Pending</option>
                            <option value="Completed" <?php echo ($filter_status == 'Completed') ? 'selected' : ''; ?>>Hanya Selesai</option>
                            <option value="Cancelled" <?php echo ($filter_status == 'Cancelled') ? 'selected' : ''; ?>>Hanya Batal</option>
                        </select>
                    </div>
                    
                    <div class="form-group col-md-4 d-flex align-items-end">
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
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Riwayat Request</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="dataTable" width="100%" cellspacing="0">
                    <thead class="thead-light">
                        <tr>
                            <th>ID Request</th>
                            <th>Tgl Request</th>
                            <?php if ($_SESSION['role_id'] != 4): ?>
                                <th>Pemohon (User)</th>
                                <th>Unit Tujuan</th>
                            <?php endif; ?>
                            <th>Status</th>
                            <th>Diproses Oleh (Admin)</th>
                            <th>Tgl Diproses</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($riwayat_requests)): ?>
                            <tr>
                                <td colspan="<?php echo ($_SESSION['role_id'] == 4) ? '5' : '7'; ?>" class="text-center">
                                    Tidak ada data request yang sesuai dengan filter.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($riwayat_requests as $req): ?>
                                <tr class="<?php echo ($req['status'] == 'Pending') ? 'table-warning' : ''; ?>">
                                    <td>#<?php echo $req['id_request']; ?></td>
                                    <td><?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($req['tgl_request']))); ?></td>
                                    
                                    <?php if ($_SESSION['role_id'] != 4): ?>
                                        <td><?php echo htmlspecialchars($req['nama_pemohon']); ?></td>
                                        <td><?php echo htmlspecialchars($req['nama_unit_tujuan']); ?></td>
                                    <?php endif; ?>
                                    
                                    <td>
                                        <?php
                                        $status = $req['status'];
                                        $badge_class = 'badge-secondary'; // Default
                                        if ($status == 'Pending') $badge_class = 'badge-warning';
                                        if ($status == 'Completed') $badge_class = 'badge-success';
                                        if ($status == 'Cancelled') $badge_class = 'badge-danger';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>" style="font-size: 0.9rem;">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($req['nama_approver'] ?? '---'); ?></td>
                                    <td>
                                        <?php echo $req['tgl_approve'] ? htmlspecialchars(date('d-m-Y H:i', strtotime($req['tgl_approve']))) : '---'; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>request/detail.php?id=<?php echo $req['id_request']; ?>" class="btn btn-info btn-sm btn-icon-split">
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
<?php 
// Panggil "Kaki" (Template Footer)
include '../templates/footer.php'; 
?>

<script>
$(document).ready(function() {
    // ===============================================
    // 1. INISIALISASI DATATABLES
    // ===============================================
    $('#dataTable').DataTable({
        "order": [[ 0, "desc" ]], 
        "language": {
            "search": "Cari:",
            "lengthMenu": "Tampilkan _MENU_ data",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            "infoEmpty": "Menampilkan 0 sampai 0 dari 0 data",
            "infoFiltered": "(disaring dari _MAX_ total data)",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Selanjutnya",
                "previous": "Sebelumnya"
            },
            "zeroRecords": "Tidak ada data yang cocok"
        }
    });

    // ===============================================
    // 2. Script CASCADING DROPDOWN sudah DIHAPUS
    // ===============================================
    // (Tidak perlu lagi, karena filter poli sudah hilang)

});
</script>