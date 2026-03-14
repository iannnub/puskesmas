<?php

require_once '../config.php';


require_once '../templates/auth_check.php';

if ($_SESSION['role_id'] != 1) {
    echo "<script>alert('Akses Ditolak! Anda bukan Super Admin.'); window.location.href = '" . BASE_URL . "dashboard.php';</script>";
    exit;
}

$page_title = "Kelola Data Unit (Lokasi Stok)";


try {

    $sql_unit = "SELECT * FROM tbl_unit ORDER BY id_unit ASC";
    $stmt_unit_list = $pdo->query($sql_unit);
    $units = $stmt_unit_list->fetchAll();

} catch (PDOException $e) {

    die("Error mengambil data: " . $e->getMessage());
}


include '../templates/header.php';

?>

<main class="content">

    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    
    <p>Kelola lokasi fisik penyimpanan stok (cth: GUDANG, APOTEK, UGD).</p>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Tambah Unit Baru</h6>
        </div>
        <div class="card-body">
            
            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] == 'tambah_sukses'): ?>
                    <div class="alert alert-success">Unit baru berhasil ditambahkan!</div>
                <?php elseif ($_GET['status'] == 'update_sukses'): ?>
                    <div class="alert alert-info">Data unit berhasil diupdate!</div>
                <?php elseif ($_GET['status'] == 'hapus_sukses'): ?>
                    <div class="alert alert-success">Unit berhasil dihapus!</div>
                <?php elseif ($_GET['status'] == 'gagal'): ?>
                     <div class="alert alert-danger">
                        Operasi gagal. <?php echo isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : 'Silakan coba lagi.'; ?>
                     </div>
                <?php endif; ?>
            <?php endif; ?>

            <form action="<?php echo BASE_URL; ?>master/proses_crud_unit.php" method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="form-row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="nama_unit">Nama Unit (Lokasi)</label>
                            <input type="text" id="nama_unit" name="nama_unit" class="form-control" placeholder="Cth: GUDANG" required>
                        </div>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-group">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus"></i> Simpan Unit Baru
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Unit (Lokasi Stok)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="dataTable" width="100%" cellspacing="0">
                    <thead class="thead-dark">
                        <tr>
                            <th>ID Unit</th>
                            <th>Nama Unit</th>
                            <th>Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($units)): ?>
                            <tr>
                                <td colspan="3" class="text-center">Belum ada data unit.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($units as $unit): ?>
                                <tr>
                                    <td><?php echo $unit['id_unit']; ?></td>
                                    <td><?php echo htmlspecialchars($unit['nama_unit']); ?></td>
                                    <td>
                                        <a href="#" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i> Ubah
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>master/proses_crud_unit.php?action=delete&id=<?php echo $unit['id_unit']; ?>" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('PERINGATAN! Menghapus unit ini sangat berbahaya jika masih terpakai oleh Poli atau data Stok.\n\nLanjutkan menghapus unit (<?php echo htmlspecialchars($unit['nama_unit']); ?>)?');">
                                           <i class="fas fa-trash"></i> Hapus
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

</main>
<?php

include '../templates/footer.php';
?>