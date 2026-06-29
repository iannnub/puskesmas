<?php
require_once '../config.php';
require_once '../templates/auth_check.php';

if ($_SESSION['role_id'] != 4 && $_SESSION['role_id'] != 2) {
    $_SESSION['flash_error'] = 'Akses Ditolak!'; header("Location: " . BASE_URL . "dashboard.php"); exit;
    exit;
}

$id_resep = (int)($_GET['id'] ?? 0);
if ($id_resep <= 0) {
    header("Location: " . BASE_URL . "resep/history.php?status=gagal&msg=" . urlencode("ID resep tidak valid."));
    exit;
}

try {
    // Ambil header resep
    $stmt_h = $pdo->prepare("
        SELECT rh.*, d.nama_dokter, p.nama_poli, p.id_unit_stok_default,
               pel.jenis_pelayanan
        FROM tbl_resep_header rh
        LEFT JOIN tbl_dokter d   ON rh.id_dokter = d.id_dokter
        JOIN tbl_poli p          ON rh.id_poli = p.id_poli
        JOIN tbl_pelayanan pel   ON rh.id_pelayanan = pel.id_pelayanan
        WHERE rh.id_resep = ?
    ");
    $stmt_h->execute([$id_resep]);
    $resep = $stmt_h->fetch();

    if (!$resep) {
        header("Location: " . BASE_URL . "resep/history.php?status=gagal&msg=" . urlencode("Data resep tidak ditemukan."));
        exit;
    }

    // Poli belakang hanya bisa edit resep poli sendiri
    if ($_SESSION['role_id'] == 4 && $resep['id_poli'] != $_SESSION['poli_id']) {
        header("Location: " . BASE_URL . "resep/history.php?status=gagal&msg=" . urlencode("Anda tidak berhak mengedit resep ini."));
        exit;
    }

    // Ambil detail obat resep
    $stmt_d = $pdo->prepare("
        SELECT rd.*, o.kode_obat, o.nama_obat, o.satuan
        FROM tbl_resep_detail rd
        JOIN tbl_obat o ON rd.id_obat = o.id_obat
        WHERE rd.id_resep = ?
        ORDER BY rd.id_resep_detail ASC
    ");
    $stmt_d->execute([$id_resep]);
    $details = $stmt_d->fetchAll();

    // Ambil semua obat untuk dropdown
    $stmt_obat = $pdo->query("SELECT id_obat, kode_obat, nama_obat FROM tbl_obat ORDER BY id_obat ASC");
    $all_obats = $stmt_obat->fetchAll();

    // Ambil semua dokter
    $stmt_dok = $pdo->query("SELECT id_dokter, nama_dokter FROM tbl_dokter ORDER BY nama_dokter ASC");
    $dokters = $stmt_dok->fetchAll();

    // Ambil daftar poli
    $stmt_poli = $pdo->query("SELECT id_poli, nama_poli FROM tbl_poli ORDER BY nama_poli ASC");
    $polis = $stmt_poli->fetchAll();

    // Ambil daftar pelayanan
    $stmt_pel = $pdo->query("SELECT id_pelayanan, jenis_pelayanan FROM tbl_pelayanan ORDER BY jenis_pelayanan ASC");
    $pelayanans = $stmt_pel->fetchAll();

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$page_title = "Edit Resep #" . $id_resep;
include '../templates/header.php';
?>



<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit text-warning mr-2"></i><?= htmlspecialchars($page_title); ?>
        </h1>
        <a href="<?= BASE_URL; ?>resep/history.php" class="btn btn-secondary btn-sm shadow-sm">
            <i class="fas fa-arrow-left fa-sm mr-1"></i> Kembali ke History
        </a>
    </div>

    <?php if (isset($_GET['status']) && $_GET['status'] == 'gagal'): ?>
        <div class="alert alert-danger shadow-sm">
            <i class="fas fa-times-circle mr-1"></i> <strong>Operasi Gagal!</strong> <?= htmlspecialchars(urldecode($_GET['msg'])); ?>
        </div>
    <?php endif; ?>

    <div class="alert alert-warning shadow-sm" role="alert">
        <i class="fas fa-exclamation-triangle mr-1"></i>
        <strong>Perhatian:</strong> Mengedit jumlah obat akan menyesuaikan stok secara otomatis (selisih akan ditambah atau dikurangi dari stok unit).
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-warning">
                <i class="fas fa-file-medical mr-1"></i> Edit Data Resep
            </h6>
        </div>
        <div class="card-body">

            <form action="<?= BASE_URL; ?>resep/proses_edit.php" method="POST" id="formEditResep">
                <input type="hidden" name="id_resep" value="<?= $id_resep; ?>">

                <h5 class="text-gray-700 border-bottom pb-2 mb-3">Data Resep (Header)</h5>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="tgl_resep">Tanggal Resep</label>
                        <input type="datetime-local" class="form-control" id="tgl_resep" name="tgl_resep"
                               value="<?= date('Y-m-d\TH:i', strtotime($resep['tgl_resep'])); ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="nama_pasien">Nama Pasien (Opsional)</label>
                        <input type="text" class="form-control" id="nama_pasien" name="nama_pasien"
                               value="<?= htmlspecialchars($resep['nama_pasien'] ?? ''); ?>"
                               placeholder="Nama pasien (jika ada)">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="id_poli">Diresepkan oleh Poli</label>
                        <select id="id_poli" name="id_poli" class="form-control select2-static" required
                            <?php if ($_SESSION['role_id'] == 4) { echo "disabled"; } ?>
                        >
                            <?php foreach ($polis as $poli): ?>
                                <option value="<?= $poli['id_poli']; ?>"
                                    <?= ($poli['id_poli'] == $resep['id_poli']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($poli['nama_poli']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($_SESSION['role_id'] == 4): ?>
                            <input type="hidden" name="id_poli" value="<?= $resep['id_poli']; ?>">
                        <?php endif; ?>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="id_pelayanan">Jenis Pelayanan</label>
                        <select id="id_pelayanan" name="id_pelayanan" class="form-control select2-static" required>
                            <?php foreach ($pelayanans as $pel): ?>
                                <option value="<?= $pel['id_pelayanan']; ?>"
                                    <?= ($pel['id_pelayanan'] == $resep['id_pelayanan']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($pel['jenis_pelayanan']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <hr>
                <h5 class="text-gray-700 border-bottom pb-2 mb-3">Data Dokter</h5>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="id_dokter">Nama Dokter</label>
                        <?php $selected_dok = $resep['id_dokter'] ?? ''; ?>
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
                <h5 class="text-gray-700 border-bottom pb-2 mb-3">Data Obat (Detail)</h5>

                <div class="table-responsive">
                    <table class="table table-bordered" id="tabel_detail_obat">
                        <thead class="thead-light">
                            <tr>
                                <th>Nama Obat</th>
                                <th style="width:15%;">Jumlah Keluar</th>
                                <th style="width:20%;">Jenis Racikan</th>
                                <th style="width:10%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_detail_obat">
                            <?php foreach ($details as $idx => $det): ?>
                            <tr>
                                <td>
                                    <input type="hidden" name="id_resep_detail[]" value="<?= $det['id_resep_detail']; ?>">
                                    <input type="hidden" name="jumlah_lama[]" value="<?= $det['jumlah_keluar']; ?>">
                                    <select name="obat_id[]" class="form-control obat-select" required>
                                        <option value="">-- Pilih / Cari Obat --</option>
                                        <?php foreach ($all_obats as $ob): ?>
                                            <option value="<?= $ob['id_obat']; ?>"
                                                <?= ($ob['id_obat'] == $det['id_obat']) ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($ob['kode_obat'] . ' - ' . $ob['nama_obat']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="jumlah[]" class="form-control"
                                           min="1" value="<?= $det['jumlah_keluar']; ?>" required>
                                </td>
                                <td>
                                    <select name="racikan[]" class="form-control" required>
                                        <option value="Non Racikan" <?= ($det['jenis_racikan'] == 'Non Racikan') ? 'selected' : ''; ?>>Non Racikan</option>
                                        <option value="Racikan" <?= ($det['jenis_racikan'] == 'Racikan') ? 'selected' : ''; ?>>Racikan</option>
                                    </select>
                                </td>
                                <td class="text-center">
                                    <?php if ($idx > 0): ?>
                                    <button type="button" class="btn btn-danger btn-sm hapus-baris">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <button type="button" id="tambah_baris_obat" class="btn btn-primary btn-sm btn-icon-split mt-2">
                    <span class="icon text-white-50"><i class="fas fa-plus"></i></span>
                    <span class="text">Tambah Obat Lain</span>
                </button>

                <hr>
                <h5 class="text-gray-700 border-bottom pb-2 mb-3">Data Sasaran Mutu</h5>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Kelengkapan Resep</label>
                        <select name="kelengkapan_resep" class="form-control select2-static" required>
                            <option value="Lengkap" <?= ($resep['kelengkapan_resep'] == 'Lengkap') ? 'selected' : ''; ?>>Lengkap</option>
                            <option value="Tidak Lengkap" <?= ($resep['kelengkapan_resep'] == 'Tidak Lengkap') ? 'selected' : ''; ?>>Tidak Lengkap</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Kesalahan Resep</label>
                        <select name="kesalahan_resep" class="form-control select2-static" required>
                            <option value="Tidak Ada" <?= ($resep['kesalahan_resep'] == 'Tidak Ada') ? 'selected' : ''; ?>>Tidak Ada</option>
                            <option value="Ada" <?= ($resep['kesalahan_resep'] == 'Ada') ? 'selected' : ''; ?>>Ada</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Sesuai Formularium</label>
                        <select name="sesuai_formularium" class="form-control select2-static" required>
                            <option value="Sesuai" <?= ($resep['sesuai_formularium'] == 'Sesuai') ? 'selected' : ''; ?>>Sesuai</option>
                            <option value="Tidak Sesuai" <?= ($resep['sesuai_formularium'] == 'Tidak Sesuai') ? 'selected' : ''; ?>>Tidak Sesuai</option>
                        </select>
                    </div>
                </div>

                <hr>
                <button type="submit" class="btn btn-warning btn-lg btn-icon-split">
                    <span class="icon text-white-50"><i class="fas fa-save"></i></span>
                    <span class="text">Simpan Perubahan</span>
                </button>
                <a href="<?= BASE_URL; ?>resep/history.php" class="btn btn-secondary btn-lg ml-2">
                    <i class="fas fa-times mr-1"></i> Batal
                </a>
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
        // Terapkan select2-static persis seperti di tambah.php
        $('.select2-static').select2({ width: '100%', minimumResultsForSearch: Infinity });

        // Inisialisasi select2 untuk semua baris obat yang sudah ada
        $('.obat-select').each(function() {
            inisialisasiSelect2Obat(this);
        });

        // Tambah baris obat baru
        $('#tambah_baris_obat').click(function() {
            var barisBaru = `
                <tr>
                    <td>
                        <input type="hidden" name="id_resep_detail[]" value="0">
                        <input type="hidden" name="jumlah_lama[]" value="0">
                        <select name="obat_id[]" class="form-control obat-select-baru" required>
                            <option value="">-- Pilih / Cari Obat --</option>
                            ${daftarObatHTML}
                        </select>
                    </td>
                    <td>
                        <input type="number" name="jumlah[]" class="form-control" min="1" value="1" required>
                    </td>
                    <td>
                        <select name="racikan[]" class="form-control" required>
                            <option value="Non Racikan">Non Racikan</option>
                            <option value="Racikan">Racikan</option>
                        </select>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-danger btn-sm hapus-baris">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            $('#tbody_detail_obat').append(barisBaru);
            inisialisasiSelect2Obat('.obat-select-baru:last');
        });

        // Hapus baris obat
        $('#tbody_detail_obat').on('click', '.hapus-baris', function() {
            if ($('#tbody_detail_obat tr').length <= 1) {
                alert('Minimal harus ada 1 obat dalam resep.');
                return;
            }
            $(this).closest('tr').remove();
        });
    });
</script>
<?php
$extra_scripts = ob_get_clean();
include '../templates/footer.php';
?>