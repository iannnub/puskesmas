<?php

require_once '../config.php';


require_once '../templates/auth_check.php';


if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) {
    echo "<script>alert('Akses Ditolak! Anda bukan Super Admin atau Admin.'); window.location.href = '" . BASE_URL . "dashboard.php';</script>";
    exit;
}

$page_title = "Stok Opname (Penyesuaian Stok Fisik)";


$new_id = $_GET['new_id'] ?? null;


try {
    
    $stmt_obat = $pdo->query("SELECT id_obat, kode_obat, nama_obat FROM tbl_obat ORDER BY id_obat ASC");
    $obats = $stmt_obat->fetchAll();

  
    $stmt_unit = $pdo->query("SELECT id_unit, nama_unit FROM tbl_unit ORDER BY nama_unit ASC");
    $units = $stmt_unit->fetchAll();

    $sql_riwayat = "SELECT 
                        l.id_log, l.tgl_log, o.nama_obat, u.nama_unit,
                        l.stok_sebelum, l.stok_sesudah, (l.masuk - l.keluar) AS selisih,
                        usr.username AS pencatat
                    FROM 
                        tbl_log_stok l
                    JOIN 
                        tbl_obat o ON l.id_obat = o.id_obat
                    JOIN 
                        tbl_unit u ON l.id_unit = u.id_unit
                    JOIN
                        tbl_user usr ON l.id_referensi_transaksi = usr.id_user 
                    WHERE
                        l.sumber_data = 'Stok Opname'
                    ORDER BY 
                        l.id_log DESC
                    LIMIT 20";
    $stmt_riwayat = $pdo->query($sql_riwayat);
    $riwayat_opname = $stmt_riwayat->fetchAll();

} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}

include '../templates/header.php';

?>

<div class="container-fluid">

    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    <p class="mb-4">Gunakan form ini untuk menyesuaikan jumlah stok di sistem agar sama dengan jumlah fisik di lapangan (Gudang/Apotek/dll).</p>

    <div class="row">
        
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-clipboard-check"></i> Input Penyesuaian Stok (Opname)</h6>
                </div>
                <div class="card-body">
                    <?php if (isset($_GET['status'])): ?>
                        <?php if ($_GET['status'] == 'tambah_sukses'): ?>
                            <div class="alert alert-success" role="alert">
                                Stok Opname berhasil dicatat! Stok telah disesuaikan.
                            </div>
                        <?php elseif ($_GET['status'] == 'gagal'): ?>
                            <div class="alert alert-danger" role="alert">
                                <strong>Operasi Gagal!</strong> <?php echo isset($_GET['msg']) ? htmlspecialchars(urldecode($_GET['msg'])) : 'Silakan coba lagi.'; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>stok/proses_opname.php" method="POST" id="formOpname">
                        <input type="hidden" name="action" value="create">

                        <div class="form-group">
                            <label for="id_obat">Nama Obat</label>
                            <select id="id_obat" name="id_obat" class="form-control select2-obat" required>
                                <option value="">-- Pilih / Cari Obat --</option>
                                <?php foreach ($obats as $obat): ?>
                                    <option value="<?php echo $obat['id_obat']; ?>">
                                        <?php echo htmlspecialchars($obat['kode_obat'] . ' - ' . $obat['nama_obat']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="id_unit">Di Unit (Lokasi)</label>
                                <select id="id_unit" name="id_unit" class="form-control select2-unit" required>
                                    <option value="">-- Pilih Unit Lokasi --</option>
                                    <?php foreach ($units as $unit): ?>
                                        <option value="<?php echo $unit['id_unit']; ?>"><?php echo htmlspecialchars($unit['nama_unit']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="jumlah_fisik">Jumlah Fisik (Hasil Hitungan)</label>
                                <input type="number" class="form-control" id="jumlah_fisik" name="jumlah_fisik" min="0" required>
                                <small class="form-text text-muted">Stok di sistem akan DISET/DIUBAH LANGSUNG menjadi angka ini.</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="keterangan">Keterangan/Alasan Penyesuaian</label>
                            <input type="text" class="form-control" id="keterangan" name="keterangan" placeholder="Cth: Hasil hitung ulang, obat rusak/expired, dll." required>
                        </div>
                        
                        <hr>
                        <button type="submit" class="btn btn-warning btn-icon-split">
                            <span class="icon text-white-50">
                                <i class="fas fa-exclamation-triangle"></i>
                            </span>
                            <span class="text">Sesuaikan Stok</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history"></i> 20 Riwayat Stok Opname Terakhir</h6>
                    <a href="<?php echo BASE_URL; ?>stok/opname.php" class="btn btn-primary btn-sm btn-icon-split">
                        <span class="icon text-white-50"><i class="fas fa-sync-alt"></i></span>
                        <span class="text">Refresh</span>
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                            <thead class="thead-light">
                                <tr>
                                    <th>ID Log</th>
                                    <th>Tgl Opname</th>
                                    <th>Nama Obat</th>
                                    <th>Unit</th>
                                    <th>Stok Sistem</th>
                                    <th>Stok Fisik (Baru)</th>
                                    <th>Selisih</th>
                                    <th>Dicatat Oleh</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($riwayat_opname)): ?>
                                    <tr><td colspan="8" class="text-center">Belum ada riwayat stok opname.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($riwayat_opname as $r): ?>
                                        <?php $is_new = ($new_id && $r['id_log'] == $new_id); ?>
                                        <tr class="<?php echo $is_new ? 'table-warning' : ''; ?>">
                                            <td><?php echo $r['id_log']; ?></td>
                                            <td><?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($r['tgl_log']))); ?></td>
                                            <td><?php echo htmlspecialchars($r['nama_obat']); ?></td>
                                            <td><?php echo htmlspecialchars($r['nama_unit']); ?></td>
                                            <td><?php echo htmlspecialchars($r['stok_sebelum']); ?></td>
                                            <td><?php echo htmlspecialchars($r['stok_sesudah']); ?></td>
                                            <td style="font-weight: bold; color: <?php echo ($r['selisih'] >= 0) ? '#1cc88a' : '#e74a3b'; ?>;">
                                                <?php echo ($r['selisih'] > 0) ? '+' : ''; ?><?php echo htmlspecialchars($r['selisih']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($r['pencatat']); ?></td>
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
    
    
    $('#id_obat').select2({
        placeholder: "-- Pilih / Cari Obat --",
        width: '100%' 
    });

    $('#id_unit').select2({
        placeholder: "-- Pilih Unit Lokasi --",
        width: '100%',
        minimumResultsForSearch: Infinity
    });

    
    <?php if (isset($_GET['status']) && $_GET['status'] == 'tambah_sukses'): ?>
        var currentUrl = "<?php echo BASE_URL . 'stok/opname.php'; ?>";
        <?php if ($new_id): ?>
             currentUrl = currentUrl + "?new_id=<?php echo $new_id; ?>";
        <?php endif; ?>

        window.history.pushState({path: currentUrl}, '', currentUrl);

        setTimeout(() => {
            $('#formOpname')[0].reset();
            $('#id_obat').val(null).trigger('change');
            $('#id_unit').val(null).trigger('change');
        }, 500);
    <?php endif; ?>

   
    <?php if (isset($_GET['status']) && $_GET['status'] == 'gagal'): ?>
        var cleanUrl = "<?php echo BASE_URL . 'stok/opname.php'; ?>";
        window.history.pushState({path: cleanUrl}, '', cleanUrl);
    <?php endif; ?>
});
</script>