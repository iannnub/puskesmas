<?php

require_once '../config.php';

require_once '../templates/auth_check.php';

if ($_SESSION['role_id'] != 4) {
    echo "<script>alert('Akses Ditolak! Fitur ini hanya untuk Poli Belakang.'); window.location.href = '" . BASE_URL . "dashboard.php';</script>";
    exit;
}

$page_title = "Buat Request Stok Obat";


try {
    
    $stmt_obat = $pdo->query("SELECT id_obat, kode_obat, nama_obat FROM tbl_obat ORDER BY id_obat ASC");
    $all_obats = $stmt_obat->fetchAll();

} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}

include '../templates/header.php';

?>

<div class="container-fluid">

    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>

    <div class="alert alert-info shadow mb-4" role="alert">
        <i class="fas fa-user-md"></i> Anda login sebagai <strong><?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?></strong>. Gunakan form ini untuk *meminta* obat dari Admin Farmasi.
    </div>
    
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-signature"></i> Form Request Stok Baru</h6>
                </div>
                <div class="card-body">
                    
                    <?php if (isset($_GET['status'])): ?>
                        <?php if ($_GET['status'] == 'tambah_sukses'): ?>
                            <div class="alert alert-success" role="alert">
                                Request stok berhasil dikirim! (Form telah direset)
                            </div>
                        <?php elseif ($_GET['status'] == 'gagal'): ?>
                            <div class="alert alert-danger" role="alert">
                                <strong>Operasi Gagal!</strong> <?php echo isset($_GET['msg']) ? htmlspecialchars(urldecode($_GET['msg'])) : 'Silakan coba lagi.'; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>request/proses_buat.php" method="POST" id="formRequest">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="id_unit_tujuan" value="<?php echo $_SESSION['unit_stok_id']; ?>">
                        
                        <h5>Data Request</h5>
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Tanggal Request</label>
                                <input type="text" class="form-control" value="<?php echo date('d-m-Y H:i'); ?>" readonly>
                                <input type="hidden" name="tgl_request" value="<?php echo date('Y-m-d H:i:s'); ?>">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Unit Pemohon</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>" readonly>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="keterangan_request">Keterangan (Opsional)</label>
                            <textarea id="keterangan_request" name="keterangan_request" class="form-control" rows="2" placeholder="Cth: Kebutuhan mendesak, stok menipis, dll."></textarea>
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
                                <span class="text">Kirim Request Stok</span>
                            </button>
                        </div>
                    </form>
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
    
    const daftarObatHTML = <?php echo json_encode(array_map(function($obat) {
        return '<option value="' . $obat['id_obat'] . '">' . htmlspecialchars($obat['kode_obat'] . ' - ' . $obat['nama_obat']) . '</option>';
    }, $all_obats)); ?>.join('');

   
    function inisialisasiSelect2Obat(element) {
        $(element).select2({
            width: '100%',
            placeholder: '-- Pilih / Cari Obat --'
        });
    }

    $(document).ready(function() {
        
        
        inisialisasiSelect2Obat('.obat-select');

       
        $('#tambah_baris_obat').click(function() {
            
            
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
                </tr>
            `;
            
            $('#tbody_detail_request').append(barisBaru);
            
            
            inisialisasiSelect2Obat('.obat-select-baru:last');
        });

       
        $('#tbody_detail_request').on('click', '.hapus-baris', function() {
            $(this).closest('tr').remove();
        });

        
        <?php if (isset($_GET['status']) && $_GET['status'] == 'tambah_sukses'): ?>
            var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.pushState({path:newUrl}, '', newUrl);

            setTimeout(() => {
                $('#formRequest')[0].reset();
                
                $('#tbody_detail_request').find('tr:gt(0)').remove();
                
                $('.obat-select').val(null).trigger('change');
            }, 500);
        <?php endif; ?>

        
        <?php if (isset($_GET['status']) && $_GET['status'] == 'gagal'): ?>
            var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.pushState({path:newUrl}, '', newUrl);
        <?php endif; ?>
    });
</script>