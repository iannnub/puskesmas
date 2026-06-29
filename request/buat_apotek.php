<?php
// request/buat_apotek.php
// Digunakan oleh Role 5 (Apotek) untuk membuat request stok ke Gudang (Admin)

require_once '../config.php';
require_once '../templates/auth_check.php';

// Hanya Role 5 (Apotek) yang boleh akses
if ($_SESSION['role_id'] != 5) {
    $_SESSION['flash_error'] = 'Akses Ditolak! Fitur ini hanya untuk Apotek.'; header("Location: " . BASE_URL . "dashboard.php"); exit;
    exit;
}

$page_title = "Buat Request Stok ke Gudang";

// ID unit Gudang (id_unit = 1) dan Apotek (id_unit = 2) sesuai data
define('ID_UNIT_GUDANG', 1);
define('ID_UNIT_APOTEK', 2);

try {
    $stmt_obat = $pdo->query("SELECT id_obat, kode_obat, nama_obat, satuan FROM tbl_obat ORDER BY id_obat ASC");
    $all_obats = $stmt_obat->fetchAll();
} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}

include '../templates/header.php';
?>

<div class="container-fluid">

    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>

    <div class="alert alert-info shadow mb-4" role="alert">
        <i class="fas fa-store"></i> Anda login sebagai <strong><?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?></strong> (Apotek).
        Gunakan form ini untuk <strong>meminta stok obat dari Gudang</strong>.
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-file-signature"></i> Form Request Stok Apotek → Gudang
                    </h6>
                </div>
                <div class="card-body">

                    <?php if (isset($_GET['status'])): ?>
                        <?php if ($_GET['status'] == 'tambah_sukses'): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle"></i> Request stok ke Gudang berhasil dikirim! Admin Gudang akan segera memprosesnya.
                            </div>
                        <?php elseif ($_GET['status'] == 'gagal'): ?>
                            <div class="alert alert-danger" role="alert">
                                <strong>Operasi Gagal!</strong> <?php echo isset($_GET['msg']) ? htmlspecialchars(urldecode($_GET['msg'])) : 'Silakan coba lagi.'; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>request/proses_buat_apotek.php" method="POST" id="formRequestApotek">
                        <input type="hidden" name="action" value="create_apotek">

                        <h5>Data Request</h5>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Tanggal Request</label>
                                <input type="text" class="form-control" value="<?php echo date('d-m-Y H:i'); ?>" readonly>
                                <input type="hidden" name="tgl_request" value="<?php echo date('Y-m-d H:i:s'); ?>">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Pemohon</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?> (Apotek)" readonly>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Unit Pengirim (Sumber)</label>
                            <input type="text" class="form-control bg-light" value="GUDANG" readonly>
                            <small class="text-muted">Stok akan diambil dari unit Gudang.</small>
                        </div>

                        <div class="form-group">
                            <label for="keterangan_request">Keterangan (Opsional)</label>
                            <textarea id="keterangan_request" name="keterangan_request" class="form-control" rows="2"
                                placeholder="Cth: Kebutuhan mendesak, stok Apotek menipis, dll."></textarea>
                        </div>

                        <hr>
                        <h5>Daftar Obat yang Diminta</h5>

                        <div class="table-responsive">
                            <table class="table table-bordered" id="tabel_detail_request" width="100%" cellspacing="0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Nama Obat (Cari...)</th>
                                        <th style="width: 20%;">Jumlah Diminta</th>
                                        <th style="width: 10%;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody_detail_request">
                                    <tr>
                                        <td>
                                            <select name="obat_id[]" class="form-control obat-select" required>
                                                <option value="">-- Pilih / Cari Obat --</option>
                                                <?php foreach ($all_obats as $obat): ?>
                                                    <option value="<?php echo $obat['id_obat']; ?>">
                                                        <?php echo htmlspecialchars($obat['kode_obat'] . ' - ' . $obat['nama_obat']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" name="jumlah_request[]" class="form-control" min="1" value="1" required>
                                        </td>
                                        <td>
                                            <!-- Baris pertama tidak punya tombol hapus -->
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <button type="button" id="tambah_baris_obat" class="btn btn-primary btn-sm btn-icon-split">
                                <span class="icon text-white-50"><i class="fas fa-plus"></i></span>
                                <span class="text">Tambah Obat Lain</span>
                            </button>
                            <button type="submit" class="btn btn-success btn-lg btn-icon-split">
                                <span class="icon text-white-50"><i class="fas fa-paper-plane"></i></span>
                                <span class="text">Kirim Request ke Gudang</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


<?php ob_start(); ?>
<script>
    const daftarObatHTML = <?php echo json_encode(array_map(function($obat) {
        return '<option value="' . $obat['id_obat'] . '">' . htmlspecialchars($obat['kode_obat'] . ' - ' . $obat['nama_obat']) . '</option>';
    }, $all_obats)); ?>.join('');

    function inisialisasiSelect2(element) {
        $(element).select2({
            width: '100%',
            placeholder: '-- Pilih / Cari Obat --'
        });
    }

    $(document).ready(function () {
        inisialisasiSelect2('.obat-select');

        $('#tambah_baris_obat').click(function () {
            var barisBaru = `
                <tr>
                    <td>
                        <select name="obat_id[]" class="form-control obat-select-baru" required>
                            <option value="">-- Pilih / Cari Obat --</option>
                            ${daftarObatHTML}
                        </select>
                    </td>
                    <td>
                        <input type="number" name="jumlah_request[]" class="form-control" min="1" value="1" required>
                    </td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm hapus-baris">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
            $('#tbody_detail_request').append(barisBaru);
            inisialisasiSelect2('.obat-select-baru:last');
        });

        $('#tbody_detail_request').on('click', '.hapus-baris', function () {
            $(this).closest('tr').remove();
        });

        <?php if (isset($_GET['status']) && $_GET['status'] == 'tambah_sukses'): ?>
            var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.pushState({path: newUrl}, '', newUrl);
            setTimeout(() => {
                $('#formRequestApotek')[0].reset();
                $('#tbody_detail_request').find('tr:gt(0)').remove();
                $('.obat-select').val(null).trigger('change');
            }, 500);
        <?php endif; ?>

        <?php if (isset($_GET['status']) && $_GET['status'] == 'gagal'): ?>
            var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.pushState({path: newUrl}, '', newUrl);
        <?php endif; ?>
    });
</script>
<?php $extra_scripts = ob_get_clean(); ?>

<?php include '../templates/footer.php'; ?>