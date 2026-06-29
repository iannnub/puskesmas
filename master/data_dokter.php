<?php
require_once '../config.php';
require_once '../templates/auth_check.php';

if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2 ) {
    $_SESSION['flash_error'] = 'Akses Ditolak!'; header("Location: " . BASE_URL . "dashboard.php"); exit;
    exit;
}

$page_title = "Kelola Data Dokter";

try {
    $sql_dokter = "SELECT id_dokter, nama_dokter FROM tbl_dokter ORDER BY id_dokter DESC";
    $stmt_dokter = $pdo->query($sql_dokter);
    $dokters = $stmt_dokter->fetchAll();
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

include '../templates/header.php';
?>

<div class="container-fluid">

    <h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title); ?></h1>

    <div class="row">
        <!-- CARD FORM TAMBAH DOKTER -->
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-plus-circle"></i> Tambah Dokter Baru
                    </h6>
                </div>
                <div class="card-body">

                    <?php if (isset($_GET['status'])): ?>
                        <?php if ($_GET['status'] == 'tambah_sukses'): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                Data dokter berhasil disimpan!
                                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                            </div>
                        <?php elseif ($_GET['status'] == 'gagal'): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <strong>Operasi Gagal!</strong> <?= isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : 'Silakan coba lagi.'; ?>
                                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <form action="proses_tambah_dokter.php" method="POST">
                        <input type="hidden" name="action" value="create">

                        <div class="form-group">
                            <label for="nama_dokter">Nama Lengkap Dokter</label>
                            <input type="text" class="form-control" id="nama_dokter" name="nama_dokter"
                                   placeholder="Cth: dr. Andi Kurniawan" required>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-success btn-icon-split">
                            <span class="icon text-white-50">
                                <i class="fas fa-save"></i>
                            </span>
                            <span class="text">Simpan Dokter Baru</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- CARD TABEL DAFTAR DOKTER -->
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list"></i> Daftar Semua Dokter (Total: <?= count($dokters); ?>)
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="dataTable" width="100%">
                            <thead class="thead-dark">
                                <tr>
                                    <th width="10%" class="text-center">No</th>
                                    <th>Nama Dokter</th>
                                    <th width="15%" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dokters)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center">Belum ada data dokter.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; foreach ($dokters as $row): ?>
                                    <tr>
                                        <td class="text-center"><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($row['nama_dokter']); ?></td>
                                        <td class="text-center">
                                            <!-- Tombol Edit -->
                                            <button class="btn btn-warning btn-sm"
                                                    data-toggle="modal"
                                                    data-target="#editDokterModal"
                                                    data-id="<?= $row['id_dokter']; ?>"
                                                    data-nama="<?= htmlspecialchars($row['nama_dokter']); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <!-- Tombol Hapus -->
                                            <a href="proses_tambah_dokter.php?action=delete&id=<?= $row['id_dokter']; ?>"
                                               onclick="return confirm('Hapus dokter \'<?= htmlspecialchars($row['nama_dokter']); ?>\'?');"
                                               class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash"></i>
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

<!-- MODAL EDIT DOKTER -->
<div class="modal fade" id="editDokterModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Ubah Data Dokter</h5>
                <button class="close" type="button" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="proses_tambah_dokter.php" method="POST" id="editDokterForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id_dokter" id="edit_id_dokter">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Lengkap Dokter</label>
                        <input type="text" class="form-control" name="nama_dokter" id="edit_nama_dokter" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Batal</button>
                    <button class="btn btn-warning" type="submit">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php ob_start(); ?>
<script>
$(document).ready(function() {
    // Isi data ke modal edit saat tombol Edit diklik
    $('#editDokterModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id    = button.data('id');
        var nama  = button.data('nama');

        var modal = $(this);
        modal.find('.modal-title').text('Ubah Data Dokter #' + id);
        modal.find('#edit_id_dokter').val(id);
        modal.find('#edit_nama_dokter').val(nama);
    });

    // Bersihkan URL dari parameter status setelah alert tampil
    <?php if (isset($_GET['status'])): ?>
        var cleanUrl = "<?php echo BASE_URL; ?>master/data_dokter.php";
        window.history.pushState({path: cleanUrl}, '', cleanUrl);
    <?php endif; ?>
});
</script>
<?php $extra_scripts = ob_get_clean(); ?>

<?php include '../templates/footer.php'; ?>