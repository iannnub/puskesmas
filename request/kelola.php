<?php

require_once '../config.php';

require_once '../templates/auth_check.php';

if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) {
    echo "<script>alert('Akses Ditolak! Anda bukan Super Admin atau Admin.'); window.location.href = '" . BASE_URL . "dashboard.php';</script>";
    exit;
}

$page_title = "Kelola Request Stok Internal";

try {

    $stmt_users_poli = $pdo->query("SELECT id_user, nama_lengkap 
                                    FROM tbl_user 
                                    WHERE id_role = 4 
                                    ORDER BY nama_lengkap ASC");
    $poli_belakang_users = $stmt_users_poli->fetchAll();

    $filter_user_id = $_GET['filter_user'] ?? '';

    $filter_status = $_GET['status_filter'] ?? 'Pending'; 

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

    if (!empty($filter_user_id)) {
        $where_conditions[] = "h.id_user_request = ?";
        $params[] = $filter_user_id;
    }

    if (!empty($filter_status)) {
        $where_conditions[] = "h.status = ?";
        $params[] = $filter_status;
    }

    $sql_where = "";
    if (!empty($where_conditions)) {
        $sql_where = " WHERE " . implode(" AND ", $where_conditions);
    }

    $sql_requests = "SELECT 
                        h.id_request, h.tgl_request, h.status, h.tgl_approve,
                        u_req.nama_lengkap AS nama_pemohon,
                        unit_tuj.nama_unit AS nama_unit_tujuan,
                        u_app.nama_lengkap AS nama_approver
                    " . $sql_base . $sql_where . "
                    ORDER BY 
                        h.id_request ASC";
    
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
    <p class="mb-4">Halaman ini menampilkan antrian permintaan stok dari unit Poli Belakang (UGD, Rawat Inap, dll).</p>

    <?php if (isset($_GET['status_aksi'])):  ?>
        <?php if ($_GET['status_aksi'] == 'sukses_approve'): ?>
            <div class="alert alert-success" role="alert">
                Request berhasil diproses! Stok telah ditransfer.
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
                                        <option value="<?php echo $user['id_user']; ?>" <?php echo ($filter_user_id == $user['id_user']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['nama_lengkap']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="status_filter">Filter Status:</label>
                                <select name="status_filter" id="status_filter" class="form-control select2-static">
                                    <option value="Pending" <?php echo ($filter_status == 'Pending') ? 'selected' : ''; ?>>Hanya Tampil Pending</option>
                                    <option value="Completed" <?php echo ($filter_status == 'Completed') ? 'selected' : ''; ?>>Hanya Tampil Selesai</option>
                                    <option value="" <?php echo ($filter_status == '') ? 'selected' : ''; ?>>Tampilkan Semua</option>
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
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-tasks"></i> Daftar Request Stok (Filter: <?php echo htmlspecialchars($filter_status ? $filter_status : 'Semua'); ?>)</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="dataTable" width="100%" cellspacing="0">
                            <thead class="thead-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Tgl Request</th>
                                    <th>Pemohon (User)</th>
                                    <th>Unit Tujuan</th>
                                    <th>Status</th>
                                    <th style="width: 20%;">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($requests)): ?>
                                    <tr><td colspan="6" class="text-center">
                                        Tidak ada data request yang sesuai dengan filter.
                                    </td></tr>
                                <?php else: ?>
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
                                                if ($status == 'Pending') $badge_class = 'badge-warning';
                                                if ($status == 'Completed') $badge_class = 'badge-success';
                                                if ($status == 'Cancelled') $badge_class = 'badge-danger';
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>" style="font-size: 0.9rem;">
                                                    <?php echo htmlspecialchars($status); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($is_pending): ?>
                                                    <form action="<?php echo BASE_URL; ?>request/proses_setujui.php" method="POST" style="display:inline-block; margin-right: 5px;">
                                                        <input type="hidden" name="id_request" value="<?php echo $req['id_request']; ?>">
                                                        <button type="submit" 
                                                                onclick="return confirm('Apakah Anda yakin ingin memproses dan mengirim stok untuk request ini? (ID: #<?php echo $req['id_request']; ?>)\n\nPERINGATAN: Aksi ini akan OTOMATIS memotong stok GUDANG.');"
                                                                class="btn btn-success btn-sm btn-icon-split">
                                                            <span class="icon text-white-50"><i class="fas fa-check"></i></span>
                                                            <span class="text">Proses</span>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <div class="text-muted small">
                                                        Diproses oleh: <?php echo htmlspecialchars($req['nama_approver'] ?? 'N/A'); ?><br>
                                                        (<?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($req['tgl_approve']))); ?>)
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
<?php 

include '../templates/footer.php'; 
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    
    
    $('#dataTable').DataTable({
        "order": [[ 0, "asc" ]], 
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

    
    $('.select2-user').select2({
        placeholder: "-- Semua Pemohon --",
        width: '100%'
    });

    $('.select2-static').select2({
        width: '100%',
        minimumResultsForSearch: Infinity
    });
    
  
    <?php if (isset($_GET['status_aksi'])): ?>
        
        var cleanUrl = "<?php echo BASE_URL . 'request/kelola.php'; ?>";
        
        var currentFilters = "<?php echo http_build_query(['filter_user' => $filter_user_id, 'status_filter' => $filter_status]); ?>";
        window.history.pushState({path: cleanUrl + '?' + currentFilters}, '', cleanUrl + '?' + currentFilters);
    <?php endif; ?>
});
</script>