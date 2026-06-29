<?php

require_once '../config.php';
require_once '../templates/auth_check.php';

$page_title = "Detail Request Stok";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_error'] = 'ID Request tidak valid.'; header("Location: " . BASE_URL . "dashboard.php"); exit;
    exit;
}
$id_request = (int)$_GET['id'];

try {
    $sql_header = "SELECT 
                        h.id_request, h.tgl_request, h.status, h.keterangan_request,
                        h.tgl_approve, h.id_user_request, h.tipe_request,
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

    $is_admin   = in_array($_SESSION['role_id'], [1, 2, 5]);
    $is_pemohon = ($_SESSION['user_id'] == $request_header['id_user_request']);

    if (!$is_admin && !$is_pemohon) {
        $_SESSION['flash_error'] = 'Akses Ditolak!'; header("Location: " . BASE_URL . "dashboard.php"); exit;
        exit;
    }

    // Tentukan endpoint tombol proses berdasarkan tipe request
    // Poli_ke_Apotek   -> proses_setujui.php        (stok potong dari Apotek)
    // Apotek_ke_Gudang -> proses_setujui_gudang.php  (stok potong dari Gudang)
    $tipe_request    = $request_header['tipe_request'] ?? 'Poli_ke_Apotek';
    $endpoint_proses = ($tipe_request === 'Apotek_ke_Gudang')
        ? BASE_URL . 'request/proses_setujui_gudang.php'
        : BASE_URL . 'request/proses_setujui.php';

    $sql_detail = "SELECT 
                        d.id_request_detail,
                        d.jumlah_request,
                        d.jumlah_terpenuhi,
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
        <span class="icon text-white-50"><i class="fas fa-arrow-left"></i></span>
        <span class="text">Kembali ke Riwayat</span>
    </a>

    <?php if (isset($_GET['status_aksi'])): $sa = $_GET['status_aksi']; ?>
        <?php if ($sa === 'sukses'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i>
                <strong>Berhasil!</strong> Semua obat telah dikirim penuh. Status request: <strong>Completed</strong>.
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        <?php elseif ($sa === 'parsial'): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Pengiriman Parsial!</strong>
                Stok Apotek tidak mencukupi untuk semua item — obat yang tersedia sudah dikirim.
                Sisanya akan dikirim setelah stok Apotek diisi ulang dari Gudang.
                Status request: <strong>Partial</strong>.
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        <?php elseif ($sa === 'stok_habis'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-times-circle"></i>
                <strong>Stok Habis!</strong>
                Seluruh obat yang diminta stoknya kosong di Apotek — tidak ada yang dapat dikirim.
                Status request: <strong>Cancelled</strong>.
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        <?php elseif ($sa === 'error'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <strong>Error!</strong> <?php echo htmlspecialchars(urldecode($_GET['msg'] ?? 'Terjadi kesalahan.')); ?>
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Detail Request #<?php echo $request_header['id_request']; ?>
                        <span class="badge badge-<?php echo $tipe_request === 'Apotek_ke_Gudang' ? 'primary' : 'secondary'; ?> ml-2" style="font-size:0.75rem;">
                            <?php echo $tipe_request === 'Apotek_ke_Gudang' ? 'Apotek → Gudang' : 'Poli → Apotek'; ?>
                        </span>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2"><strong class="text-dark">Tgl Request:</strong><br>
                                <?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($request_header['tgl_request']))); ?></p>
                            <p class="mb-2"><strong class="text-dark">Pemohon:</strong><br>
                                <?php echo htmlspecialchars($request_header['nama_pemohon']); ?></p>
                            <p class="mb-2"><strong class="text-dark">Unit Tujuan:</strong><br>
                                <?php echo htmlspecialchars($request_header['nama_unit_tujuan']); ?></p>
                            <p class="mb-0"><strong class="text-dark">Keterangan:</strong><br>
                                <?php echo nl2br(htmlspecialchars($request_header['keterangan_request'] ?? 'N/A')); ?></p>
                        </div>

                        <div class="col-md-6">
                            <strong class="text-dark">Status Request:</strong>
                            <p class="mt-1">
                                <?php
                                $status = $request_header['status'];
                                $badge_map = [
                                    'Pending'   => 'badge-warning',
                                    'Partial'   => 'badge-info',
                                    'Completed' => 'badge-success',
                                    'Cancelled' => 'badge-danger',
                                ];
                                $badge_class = $badge_map[$status] ?? 'badge-secondary';
                                ?>
                                <span class="badge <?php echo $badge_class; ?>" style="font-size:1.1rem;">
                                    <?php echo htmlspecialchars($status); ?>
                                </span>
                            </p>

                            <?php if ($status === 'Completed'): ?>
                                <div class="card bg-light p-3 mt-2">
                                    <p class="mb-2"><strong class="text-dark">Diproses Oleh:</strong><br>
                                        <?php echo htmlspecialchars($request_header['nama_approver'] ?? '---'); ?></p>
                                    <p class="mb-0"><strong class="text-dark">Tgl Selesai:</strong><br>
                                        <?php echo $request_header['tgl_approve'] ? htmlspecialchars(date('d-m-Y H:i', strtotime($request_header['tgl_approve']))) : '---'; ?></p>
                                </div>

                            <?php elseif ($status === 'Cancelled'): ?>
                                <div class="card bg-light p-3 mt-2">
                                    <p class="mb-2"><strong class="text-dark">Dibatalkan Oleh:</strong><br>
                                        <?php echo htmlspecialchars($request_header['nama_approver'] ?? '---'); ?></p>
                                    <p class="mb-0"><strong class="text-dark">Tgl Dibatalkan:</strong><br>
                                        <?php echo $request_header['tgl_approve'] ? htmlspecialchars(date('d-m-Y H:i', strtotime($request_header['tgl_approve']))) : '---'; ?></p>
                                </div>

                            <?php elseif ($status === 'Partial'): ?>
                                <div class="alert alert-info mt-2">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Pengiriman Parsial</strong><br>
                                    Sebagian obat sudah dikirim dari Apotek. Sisanya menunggu stok Apotek diisi ulang dari Gudang.
                                    <?php if ($request_header['nama_approver']): ?>
                                        <hr class="my-2">
                                        Terakhir diproses oleh <strong><?php echo htmlspecialchars($request_header['nama_approver']); ?></strong>.
                                    <?php endif; ?>
                                </div>
                                <?php if ($is_admin): ?>
                                    <form action="<?php echo $endpoint_proses; ?>" method="POST" class="mt-2">
                                        <input type="hidden" name="id_request" value="<?php echo $request_header['id_request']; ?>">
                                        <button type="submit"
                                            onclick="return confirm('Kirim sisa stok untuk request #<?php echo $request_header['id_request']; ?>?\n\nSistem akan mengecek stok Apotek kembali dan mengirim obat yang masih tersedia.');"
                                            class="btn btn-info btn-icon-split">
                                            <span class="icon text-white-50"><i class="fas fa-paper-plane"></i></span>
                                            <span class="text">Kirim Sisa Stok</span>
                                        </button>
                                    </form>
                                <?php endif; ?>

                            <?php else: /* Pending */ ?>
                                <div class="alert alert-warning mt-2">
                                    <i class="fas fa-info-circle"></i> Menunggu diproses oleh Apotek.
                                </div>
                                <?php if ($is_admin): ?>
                                    <form action="<?php echo $endpoint_proses; ?>" method="POST" class="mt-2">
                                        <input type="hidden" name="id_request" value="<?php echo $request_header['id_request']; ?>">
                                        <button type="submit"
                                            onclick="return confirm('Proses request #<?php echo $request_header['id_request']; ?>?\n\nSistem akan mengecek stok Apotek:\n✓ Cukup  → kirim semua (Completed)\n~ Kurang → kirim yang ada (Partial)\n✗ Habis  → Cancelled');"
                                            class="btn btn-success btn-icon-split">
                                            <span class="icon text-white-50"><i class="fas fa-check"></i></span>
                                            <span class="text">Proses Request Ini</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel item obat -->
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-pills"></i> Daftar Item Obat yang Diminta
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ($status === 'Partial'): ?>
                        <div class="alert alert-warning py-2 mb-3">
                            <i class="fas fa-exclamation-triangle"></i>
                            Kolom <strong>Terkirim</strong> = jumlah yang sudah dikirim dari Apotek. &nbsp;
                            <span class="badge badge-warning text-dark">Kuning</span> = parsial &nbsp;|&nbsp;
                            <span class="badge badge-danger">Merah</span> = belum terkirim sama sekali.
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Kode Obat</th>
                                    <th>Nama Obat</th>
                                    <th>Satuan</th>
                                    <th class="text-right">Diminta</th>
                                    <?php if (in_array($status, ['Partial', 'Completed'])): ?>
                                    <th class="text-right">Terkirim</th>
                                    <th class="text-right">Sisa</th>
                                    <th class="text-center">Status Item</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($request_details)): ?>
                                    <tr><td colspan="7" class="text-center text-muted">Tidak ada item obat dalam request ini.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($request_details as $item):
                                        $jml_req   = (int)$item['jumlah_request'];
                                        $jml_kirim = (int)$item['jumlah_terpenuhi'];
                                        $jml_sisa  = $jml_req - $jml_kirim;

                                        if (in_array($status, ['Partial', 'Completed'])) {
                                            if ($jml_kirim === 0) {
                                                $tr_class = 'table-danger';
                                                $item_badge = '<span class="badge badge-danger">Belum Terkirim</span>';
                                            } elseif ($jml_kirim < $jml_req) {
                                                $tr_class = 'table-warning';
                                                $item_badge = '<span class="badge badge-warning text-dark">Parsial</span>';
                                            } else {
                                                $tr_class = '';
                                                $item_badge = '<span class="badge badge-success">Terpenuhi</span>';
                                            }
                                        } else {
                                            $tr_class = '';
                                            $item_badge = '';
                                        }
                                    ?>
                                    <tr class="<?php echo $tr_class; ?>">
                                        <td><?php echo htmlspecialchars($item['kode_obat']); ?></td>
                                        <td><?php echo htmlspecialchars($item['nama_obat']); ?></td>
                                        <td><?php echo htmlspecialchars($item['satuan']); ?></td>
                                        <td class="text-right font-weight-bold"><?php echo $jml_req; ?></td>
                                        <?php if (in_array($status, ['Partial', 'Completed'])): ?>
                                        <td class="text-right text-success font-weight-bold"><?php echo $jml_kirim; ?></td>
                                        <td class="text-right <?php echo $jml_sisa > 0 ? 'text-danger font-weight-bold' : 'text-muted'; ?>">
                                            <?php echo $jml_sisa > 0 ? $jml_sisa : '—'; ?>
                                        </td>
                                        <td class="text-center"><?php echo $item_badge; ?></td>
                                        <?php endif; ?>
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

<?php include '../templates/footer.php'; ?>