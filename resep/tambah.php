<?php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. Panggil "satpam" auth_check.php
require_once '../templates/auth_check.php';

// 3. (SATPAM 2: ROLE CHECK)
if ($_SESSION['role_id'] != 2 && $_SESSION['role_id'] != 4) {
    $_SESSION['flash_error'] = 'Akses Ditolak! Anda tidak punya hak akses untuk input resep.'; header("Location: " . BASE_URL . "dashboard.php"); exit;
    exit;
}

$page_title = "Input Pemakaian Obat (Resep)";

// --- LOGIKA MENANGKAP DATA LAMA (JIKA ADA ERROR) ---
$old = $_SESSION['old_input'] ?? [];
unset($_SESSION['old_input']); // Hapus langsung agar tidak nyangkut saat refresh normal

try {
    $stmt_pelayanan = $pdo->query("SELECT id_pelayanan, jenis_pelayanan FROM tbl_pelayanan ORDER BY jenis_pelayanan ASC");
    $pelayanans = $stmt_pelayanan->fetchAll();

    $stmt_poli = $pdo->query("SELECT id_poli, nama_poli FROM tbl_poli ORDER BY nama_poli ASC");
    $polis = $stmt_poli->fetchAll();
    
    // --- PERUBAHAN DI SINI ---
    // Ambil semua dokter langsung tanpa filter poli, karena id_poli di dokter sudah dihapus
    $stmt_dokter = $pdo->query("SELECT id_dokter, nama_dokter FROM tbl_dokter ORDER BY nama_dokter ASC");
    $dokters = $stmt_dokter->fetchAll();

    $stmt_obat_all = $pdo->query("SELECT id_obat, kode_obat, nama_obat FROM tbl_obat ORDER BY id_obat ASC");
    $all_obats = $stmt_obat_all->fetchAll();

} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}

include '../templates/header.php';
?>

<div class="container-fluid">

    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    
    <?php if ($_SESSION['role_id'] == 2): ?>
        <div class="alert alert-info shadow" role="alert">
            <i class="fas fa-user-tie"></i> Anda login sebagai <strong>Admin</strong>. Form ini digunakan untuk menginput resep kertas dari <strong>Poli Depan</strong>.
        </div>
    <?php else: ?>
        <div class="alert alert-info shadow" role="alert">
            <i class="fas fa-user-md"></i> Anda login sebagai <strong><?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?></strong>. Gunakan form ini untuk mencatat pemakaian obat di unit Anda.
        </div>
    <?php endif; ?>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-medical"></i> Input Resep Baru</h6>
        </div>
        <div class="card-body">
            
            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] == 'tambah_sukses'): ?>
                    <div class="alert alert-success" role="alert">
                        Resep berhasil disimpan! Stok telah diupdate.
                    </div>
                <?php elseif ($_GET['status'] == 'gagal'): ?>
                    <div class="alert alert-danger" role="alert">
                        <strong>Operasi Gagal!</strong> <?php echo isset($_GET['msg']) ? htmlspecialchars(urldecode($_GET['msg'])) : 'Silakan coba lagi.'; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <form action="<?php echo BASE_URL; ?>resep/proses_tambah.php" method="POST" id="formResep">

                <h5>Data Resep (Header)</h5>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="tgl_resep">Tanggal Resep</label>
                        <input type="datetime-local" class="form-control" id="tgl_resep" name="tgl_resep" 
                               value="<?php echo htmlspecialchars($old['tgl_resep'] ?? date('Y-m-d\TH:i')); ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="nama_pasien">Nama Pasien</label>
                        <input type="text" class="form-control" id="nama_pasien" name="nama_pasien" placeholder="Nama pasien"
                               value="<?php echo htmlspecialchars($old['nama_pasien'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="id_poli">Diresepkan oleh Poli</label>
                        <?php $selected_poli = $old['id_poli'] ?? ($_SESSION['role_id'] == 4 ? $_SESSION['poli_id'] : ''); ?>
                        <select id="id_poli" name="id_poli" class="form-control select2-static" required
                            <?php if ($_SESSION['role_id'] == 4) { echo " readonly disabled"; } ?>
                        >
                            <option value="">-- Pilih Poli --</option>
                            <?php foreach ($polis as $poli): ?>
                                <option value="<?php echo $poli['id_poli']; ?>"
                                    <?php if ($selected_poli == $poli['id_poli']) { echo " selected"; } ?>
                                >
                                    <?php echo htmlspecialchars($poli['nama_poli']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($_SESSION['role_id'] == 4) : ?>
                            <input type="hidden" name="id_poli" value="<?php echo $_SESSION['poli_id']; ?>" />
                        <?php endif; ?>
                    </div>

                    <div class="form-group col-md-6">
                        <label for="id_pelayanan">Jenis Pelayanan</label>
                        <?php $selected_layan = $old['id_pelayanan'] ?? ''; ?>
                        <select id="id_pelayanan" name="id_pelayanan" class="form-control select2-static" required>
                            <option value="">-- Pilih Pelayanan --</option>
                            <?php foreach ($pelayanans as $pelayanan): ?>
                                <option value="<?php echo $pelayanan['id_pelayanan']; ?>"
                                    <?php if ($selected_layan == $pelayanan['id_pelayanan']) echo " selected"; ?>>
                                    <?php echo htmlspecialchars($pelayanan['jenis_pelayanan']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <hr>

                <h5>Data Dokter</h5>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="id_dokter">Nama Dokter</label>
                        <?php $selected_dok = $old['id_dokter'] ?? ''; ?>
                        <select id="id_dokter" name="id_dokter" class="form-control select2-static" required>
                            <option value="">-- Pilih Dokter --</option>
                            <?php foreach ($dokters as $dokter): ?>
                                <option value="<?php echo $dokter['id_dokter']; ?>"
                                    <?php echo ($selected_dok == $dokter['id_dokter']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dokter['nama_dokter']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <hr>

                <h5>Data Obat (Detail)</h5>
                <div class="table-responsive">
                    <table class="table table-bordered" id="tabel_detail_obat" width="100%" cellspacing="0">
                        <thead class="thead-light">
                            <tr>
                                <th>Nama Obat (Cari...)</th>
                                <th style="width: 15%;">Jumlah Keluar</th>
                                <th style="width: 20%;">Jenis Racikan</th>
                                <th style="width: 10%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_detail_obat">
                            <?php 
                                // Deteksi baris lama (jika ada error) atau default 1 baris
                                $old_obat_ids = $old['obat_id'] ?? [''];
                                $old_jumlahs = $old['jumlah'] ?? ['1'];
                                $old_racikans = $old['racikan'] ?? ['Non Racikan'];
                                
                                for ($i = 0; $i < count($old_obat_ids); $i++):
                                    $curr_obat = $old_obat_ids[$i];
                                    $curr_qty = $old_jumlahs[$i];
                                    $curr_racik = $old_racikans[$i];
                            ?>
                            <tr>
                                <td>
                                    <select name="obat_id[]" class="form-control obat-select" required>
                                        <option value="">-- Pilih / Cari Obat --</option>
                                        <?php foreach ($all_obats as $obat): ?>
                                            <option value="<?php echo $obat['id_obat']; ?>" <?php echo ($curr_obat == $obat['id_obat']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($obat['kode_obat'] . ' - ' . $obat['nama_obat']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="jumlah[]" class="form-control jumlah-obat" min="1" value="<?php echo htmlspecialchars($curr_qty); ?>" required>
                                </td>
                                <td>
                                    <select name="racikan[]" class="form-control" required>
                                        <option value="Non Racikan" <?php echo ($curr_racik == 'Non Racikan') ? 'selected' : ''; ?>>Non Racikan</option>
                                        <option value="Racikan" <?php echo ($curr_racik == 'Racikan') ? 'selected' : ''; ?>>Racikan</option>
                                    </select>
                                </td>
                                <td>
                                    <?php if ($i > 0): ?>
                                        <button type="button" class="btn btn-danger btn-sm hapus-baris"><i class="fas fa-trash"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                
                <button type="button" id="tambah_baris_obat" class="btn btn-primary btn-sm btn-icon-split mt-2">
                    <span class="icon text-white-50"><i class="fas fa-plus"></i></span>
                    <span class="text">Tambah Obat Lain</span>
                </button>

                <hr>

                <h5>Data Sasaran Mutu (Wajib Diisi)</h5>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="kelengkapan_resep">Kelengkapan Resep</label>
                        <?php $kel = $old['kelengkapan_resep'] ?? 'Lengkap'; ?>
                        <select id="kelengkapan_resep" name="kelengkapan_resep" class="form-control select2-static" required>
                            <option value="Lengkap" <?php echo ($kel == 'Lengkap') ? 'selected' : ''; ?>>Lengkap</option>
                            <option value="Tidak Lengkap" <?php echo ($kel == 'Tidak Lengkap') ? 'selected' : ''; ?>>Tidak Lengkap</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="kesalahan_resep">Kesalahan Resep</label>
                        <?php $kes = $old['kesalahan_resep'] ?? 'Tidak Ada'; ?>
                        <select id="kesalahan_resep" name="kesalahan_resep" class="form-control select2-static" required>
                            <option value="Tidak Ada" <?php echo ($kes == 'Tidak Ada') ? 'selected' : ''; ?>>Tidak Ada</option>
                            <option value="Ada" <?php echo ($kes == 'Ada') ? 'selected' : ''; ?>>Ada</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="sesuai_formularium">Sesuai Formularium</label>
                        <?php $form = $old['sesuai_formularium'] ?? 'Sesuai'; ?>
                        <select id="sesuai_formularium" name="sesuai_formularium" class="form-control select2-static" required>
                            <option value="Sesuai" <?php echo ($form == 'Sesuai') ? 'selected' : ''; ?>>Sesuai</option>
                            <option value="Tidak Sesuai" <?php echo ($form == 'Tidak Sesuai') ? 'selected' : ''; ?>>Tidak Sesuai</option>
                        </select>
                    </div>
                </div>

                <hr>

                <button type="submit" class="btn btn-success btn-lg btn-icon-split">
                    <span class="icon text-white-50"><i class="fas fa-save"></i></span>
                    <span class="text">Simpan Resep & Keluarkan Stok</span>
                </button>
            </form>
        </div>
    </div>
</div>


<?php ob_start(); ?>
<script>
    const daftarObatHTML = <?php echo json_encode(array_map(function($obat) {
        return '<option value="' . $obat['id_obat'] . '">' . htmlspecialchars($obat['kode_obat'] . ' - ' . $obat['nama_obat']) . '</option>';
    }, $all_obats)); ?>.join('');

    function inisialisasiSelect2Obat(element) {
        $(element).select2({ width: '100%', placeholder: '-- Pilih / Cari Obat --' });
    }

    $(document).ready(function() {
        
        $('.select2-static').select2({ width: '100%', minimumResultsForSearch: Infinity });

        // Inisialisasi obat pertama (dan obat hasil history error)
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
                        <input type="number" name="jumlah[]" class="form-control jumlah-obat" min="1" value="1" required>
                    </td>
                    <td>
                        <select name="racikan[]" class="form-control" required>
                            <option value="Non Racikan">Non Racikan</option>
                            <option value="Racikan">Racikan</option>
                        </select>
                    </td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm hapus-baris">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            $('#tbody_detail_obat').append(barisBaru);
            inisialisasiSelect2Obat('.obat-select-baru:last');
        });

        $('#tbody_detail_obat').on('click', '.hapus-baris', function() {
            $(this).closest('tr').remove();
        });

        <?php if (isset($_GET['status'])): ?>
            var cleanUrl = "<?php echo BASE_URL . 'resep/tambah.php'; ?>";
            window.history.pushState({path: cleanUrl}, '', cleanUrl);
        <?php endif; ?>
    });
</script>
<?php $extra_scripts = ob_get_clean(); ?>

<?php include '../templates/footer.php'; ?>