<?php

require_once '../config.php';


require_once '../templates/auth_check.php';

$page_title = "Detail Request Stok";


if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

    echo "<script>alert('ID Request tidak valid.'); window.location.href = '" . BASE_URL . "request/kelola.php';</script>";
    exit;
}
$id_request = (int)$_GET['id'];


try {
    
    $sql_header = "SELECT 
                        h.id_request, h.tgl_request, h.status, h.keterangan_request,
                        h.tgl_approve, h.id_user_request,
                        u_req.nama_lengkap AS nama_pemohon,
                        unit_tuj.nama_unit AS nama_unit_tujuan,
                        u_app.nama_lengkap AS nama_approver
                    FROM 
                        tbl_request_header h
                    JOIN 
                        tbl_user u_req ON h.id_user_request = u_req.id_user
                    JOIN 
                        tbl_unit unit_tuj ON h.id_unit_tujuan = unit_tuj.id_unit
                    LEFT JOIN
                        tbl_user u_app ON h.id_user_approve = u_app.id_user
                    WHERE 
                        h.id_request = ?";
    
    $stmt_header = $pdo->prepare($sql_header);
    $stmt_header->execute([$id_request]);
    $request_header = $stmt_header->fetch();


    if (!$request_header) {
        throw new Exception("Data request dengan ID $id_request tidak ditemukan.");
    }

    $is_admin = ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2);
    $is_pemohon = ($_SESSION['user_id'] == $request_header['id_user_request']);

    if (!$is_admin && !$is_pemohon) {
        echo "<script>alert('Akses Ditolak! Anda tidak berhak melihat request ini.'); window.location.href = '" . BASE_URL . "dashboard.php';</script>";
        exit;
    }

    $sql_detail = "SELECT 
                        d.jumlah_request,
                        o.kode_obat,
                        o.nama_obat,
                        o.satuan
                    FROM 
                        tbl_request_detail d
                    JOIN 
                        tbl_obat o ON d.id_obat = o.id_obat
                    WHERE 
                        d.id_request = ?
                    ORDER BY 
                        o.nama_obat ASC";
    
    $stmt_detail = $pdo->prepare($sql_detail);
    $stmt_detail->execute([$id_request]);
    $request_details = $stmt_detail->fetchAll();

} catch (PDOException $e) {

    die("Error mengambil data: " . $e->getMessage()); 
} catch (Exception $e) {

    die($e->getMessage() . " <a href='" . BASE_URL . "request/kelola.php'>Kembali ke Daftar Request</a>");
}

include '../templates/header.php';

?>

<div class="container-fluid">

    <h1 class="h3 mb-3 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>

    <a href="<?php echo BASE_URL; ?>request/riwayat.php" class="btn btn-secondary btn-icon-split btn-sm mb-3">
        <span class="icon text-white-50">
            <i class="fas fa-arrow-left"></i>
        </span>
        <span class="text">Kembali ke Riwayat Request</span>
    </a>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Detail Request #<?php echo $request_header['id_request']; ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2">
                                <strong class="text-dark">Tgl Request:</strong><br>
                                <?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($request_header['tgl_request']))); ?>
                            </p>
                            <p class="mb-2">
                                <strong class="text-dark">Pemohon:</strong><br>
                                <?php echo htmlspecialchars($request_header['nama_pemohon']); ?>
                            </p>
                            <p class="mb-2">
                                <strong class="text-dark">Unit Tujuan:</strong><br>
                                <?php echo htmlspecialchars($request_header['nama_unit_tujuan']); ?>
                            </p>
                            <p class="mb-0">
                                <strong class="text-dark">Keterangan:</strong><br>
                                <?php echo nl2br(htmlspecialchars($request_header['keterangan_request'] ?? 'N/A')); ?>
                            </p>
                        </div>

                        <div class="col-md-6">
                            <strong class="text-dark">Status Request:</strong>
                            <p>
                                <?php
                                
                                $status = $request_header['status'];
                                $badge_class = 'badge-secondary'; 
                                if ($status == 'Pending') $badge_class = 'badge-warning';
                                if ($status == 'Completed') $badge_class = 'badge-success';
                                if ($status == 'Cancelled') $badge_class = 'badge-danger';
                                ?>
                                <span class="badge <?php echo $badge_class; ?>" style="font-size: 1.1rem;">
                                    <?php echo htmlspecialchars($status); ?>
                                </span>
                            </p>
                            
                            <?php if ($status == 'Completed' || $status == 'Cancelled'): ?>
                                <div class="card bg-light p-3 mt-3">
                                    <p class="mb-2">
                                        <strong class="text-dark">Diproses Oleh:</strong><br>
                                        <?php echo htmlspecialchars($request_header['nama_approver'] ?? '---'); ?>
                                    </p>
                                    <p class="mb-0">
                                        <strong class="text-dark">Tgl Diproses:</strong><br>
                                        <?php echo $request_header['tgl_approve'] ? htmlspecialchars(date('d-m-Y H:i', strtotime($request_header['tgl_approve']))) : '---'; ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning mt-3">
                                    <i class="fas fa-info-circle"></i> Menunggu persetujuan dari Admin Gudang.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-pills"></i> Daftar Item Obat yang Diminta
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Kode Obat</th>
                                    <th>Nama Obat</th>
                                    <th>Satuan</th>
                                    <th class="text-right">Jumlah Diminta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($request_details)): ?>
                                    <tr><td colspan="4" class="text-center">Tidak ada item obat dalam request ini.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($request_details as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['kode_obat']); ?></td>
                                            <td><?php echo htmlspecialchars($item['nama_obat']); ?></td>
                                            <td><?php echo htmlspecialchars($item['satuan']); ?></td>
                                            <td class="text-right">
                                                <strong><?php echo htmlspecialchars($item['jumlah_request']); ?></strong>
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
    </div> </div>
<?php 

include '../templates/footer.php'; 
?>