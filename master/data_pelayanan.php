<?php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. Panggil "satpam" auth_check.php
require_once '../templates/auth_check.php';

// 3. (SATPAM 2: ROLE CHECK)
// Hanya SUPER ADMIN (Role ID 1) yang boleh mengakses halaman ini
if ($_SESSION['role_id'] != 1) {
    echo "<script>alert('Akses Ditolak! Anda bukan Super Admin.'); window.location.href = '" . BASE_URL . "dashboard.php';</script>";
    exit;
}

// 4. Set Judul Halaman
$page_title = "Kelola Data Pelayanan";

// 5. Logic untuk AMBIL DATA (READ)
try {
    // Ambil semua data pelayanan untuk ditampilkan di tabel
    $sql_pelayanan = "SELECT * FROM tbl_pelayanan ORDER BY id_pelayanan ASC";
    $stmt_pelayanan_list = $pdo->query($sql_pelayanan);
    $pelayanans = $stmt_pelayanan_list->fetchAll();

} catch (PDOException $e) {
    // Tangani error jika query gagal
    die("Error mengambil data: " . $e->getMessage());
}

// 6. Panggil "Kepala" (Template Header)
// (header.php OTOMATIS memanggil sidebar.php)
include '../templates/header.php';

?>

<main class="content">

    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    
    <p>Kelola jenis pelayanan pasien (cth: UMUM, BPJS). Data ini akan digunakan di form pemakaian resep.</p>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Tambah Jenis Pelayanan Baru</h6>
        </div>
        <div class="card-body">
            
            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] == 'tambah_sukses'): ?>
                    <div class="alert alert-success">Jenis pelayanan baru berhasil ditambahkan!</div>
                <?php elseif ($_GET['status'] == 'update_sukses'): ?>
                    <div class="alert alert-info">Data pelayanan berhasil diupdate!</div>
                <?php elseif ($_GET['status'] == 'hapus_sukses'): ?>
                    <div class="alert alert-success">Pelayanan berhasil dihapus!</div>
                <?php elseif ($_GET['status'] == 'gagal'): ?>
                     <div class="alert alert-danger">
                        Operasi gagal. <?php echo isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : 'Silakan coba lagi.'; ?>
                     </div>
                <?php endif; ?>
            <?php endif; ?>

            <form action="<?php echo BASE_URL; ?>master/proses_crud_pelayanan.php" method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="form-row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="jenis_pelayanan">Nama Jenis Pelayanan</label>
                            <input type="text" id="jenis_pelayanan" name="jenis_pelayanan" class="form-control" placeholder="Cth: UMUM" required>
                        </div>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-group">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus"></i> Simpan Pelayanan Baru
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Jenis Pelayanan</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="dataTable" width="100%" cellspacing="0">
                    <thead class="thead-dark">
                        <tr>
                            <th>ID Pelayanan</th>
                            <th>Nama Jenis Pelayanan</th>
                            <th>Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pelayanans)): ?>
                            <tr>
                                <td colspan="3" class="text-center">Belum ada data pelayanan.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pelayanans as $pelayanan): ?>
                                <tr>
                                    <td><?php echo $pelayanan['id_pelayanan']; ?></td>
                                    <td><?php echo htmlspecialchars($pelayanan['jenis_pelayanan']); ?></td>
                                    <td>
                                        <a href="#" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i> Ubah
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>master/proses_crud_pelayanan.php?action=delete&id=<?php echo $pelayanan['id_pelayanan']; ?>" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('PERINGATAN! Menghapus ini bisa GAGAL jika masih terpakai oleh data Resep.\n\nLanjutkan menghapus (<?php echo htmlspecialchars($pelayanan['jenis_pelayanan']); ?>)?');">
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
// 8. Panggil "Kaki" (Template Footer)
include '../templates/footer.php';
?>