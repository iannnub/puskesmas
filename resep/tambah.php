<?php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. Panggil "satpam" auth_check.php
require_once '../templates/auth_check.php';

// 3. (SATPAM 2: ROLE CHECK)
// Hanya ADMIN (Role 2) dan POLI BELAKANG (Role 4) yang boleh
if ($_SESSION['role_id'] != 2 && $_SESSION['role_id'] != 4) {
    echo "<script>alert('Akses Ditolak! Anda tidak punya hak akses untuk input resep.'); window.location.href = '" . BASE_URL . "dashboard.php';</script>";
    exit;
}

// 4. Set Judul Halaman
$page_title = "Input Pemakaian Obat (Resep)";

// 5. [LOGIKA BACKEND UTAMA]
// --- TIDAK ADA YANG DIUBAH DARI SINI ---
try {
    // Ambil data Pelayanan (UMUM/BPJS) untuk dropdown
    $stmt_pelayanan = $pdo->query("SELECT id_pelayanan, jenis_pelayanan FROM tbl_pelayanan ORDER BY jenis_pelayanan ASC");
    $pelayanans = $stmt_pelayanan->fetchAll();

    // Ambil data Poli untuk dropdown
    $stmt_poli = $pdo->query("SELECT id_poli, nama_poli FROM tbl_poli ORDER BY nama_poli ASC");
    $polis = $stmt_poli->fetchAll();
    
    // [PERUBAHAN] Ambil SEMUA data obat (1117+) untuk dropdown client-side
    $stmt_obat_all = $pdo->query("SELECT id_obat, kode_obat, nama_obat FROM tbl_obat ORDER BY id_obat ASC");
    $all_obats = $stmt_obat_all->fetchAll();

} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}
// --- SAMPAI SINI LOGIC BACKEND AMAN ---

// 6. Panggil Header & Sidebar
include '../templates/header.php';
// Panggil Sidebar (jika terpisah)
// include '../templates/sidebar.php';
?>

<div class="container-fluid">

    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    
    <?php if ($_SESSION['role_id'] == 2): // Jika Admin (Operator) ?>
        <div class="alert alert-info shadow" role="alert">
            <i class="fas fa-user-tie"></i> Anda login sebagai <strong>Admin</strong>. Form ini digunakan untuk menginput resep kertas dari <strong>Poli Depan</strong>.
        </div>
    <?php else: // Jika Poli Belakang ?>
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
                        <input type="datetime-local" class="form-control" id="tgl_resep" name="tgl_resep" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="nama_pasien">Nama Pasien (Opsional)</label>
                        <input type="text" class="form-control" id="nama_pasien" name="nama_pasien" placeholder="Nama pasien (jika ada)">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="id_poli">Diresepkan oleh Poli</label>
                        <select id="id_poli" name="id_poli" class="form-control select2-static" required
                            <?php 
                                // [KUNCI LOGIKA] Jika dia Poli Belakang, kunci dropdown-nya
                                if ($_SESSION['role_id'] == 4) { echo " readonly disabled"; } 
                            ?>
                        >
                            <option value="">-- Pilih Poli --</option>
                            <?php foreach ($polis as $poli): ?>
                                <option value="<?php echo $poli['id_poli']; ?>"
                                    <?php 
                                        // [KUNCI LOGIKA] Auto-select jika dia Poli Belakang
                                        if ($_SESSION['role_id'] == 4 && $_SESSION['poli_id'] == $poli['id_poli']) {
                                            echo " selected";
                                        }
                                    ?>
                                >
                                    <?php echo htmlspecialchars($poli['nama_poli']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php 
                            // Jika Poli Belakang, kirim ID Poli-nya via hidden input
                            if ($_SESSION['role_id'] == 4) :
                        ?>
                            <input type="hidden" name="id_poli" value="<?php echo $_SESSION['poli_id']; ?>" />
                        <?php endif; ?>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="id_pelayanan">Jenis Pelayanan</label>
                        <select id="id_pelayanan" name="id_pelayanan" class="form-control select2-static" required>
                            <option value="">-- Pilih Pelayanan --</option>
                            <?php foreach ($pelayanans as $pelayanan): ?>
                                <option value="<?php echo $pelayanan['id_pelayanan']; ?>"><?php echo htmlspecialchars($pelayanan['jenis_pelayanan']); ?></option>
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
                                    <input type="number" name="jumlah[]" class="form-control jumlah-obat" min="1" value="1" required>
                                </td>
                                <td>
                                    <select name="racikan[]" class="form-control" required>
                                        <option value="Non Racikan">Non Racikan</option>
                                        <option value="Racikan">Racikan</option>
                                    </select>
                                </td>
                                <td>
                                    </td>
                            </tr>
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
                        <select id="kelengkapan_resep" name="kelengkapan_resep" class="form-control select2-static" required>
                            <option value="Lengkap">Lengkap</option>
                            <option value="Tidak Lengkap">Tidak Lengkap</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="kesalahan_resep">Kesalahan Resep</label>
                        <select id="kesalahan_resep" name="kesalahan_resep" class="form-control select2-static" required>
                            <option value="Tidak Ada">Tidak Ada</option>
                            <option value="Ada">Ada</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="sesuai_formularium">Sesuai Formularium</label>
                        <select id="sesuai_formularium" name="sesuai_formularium" class="form-control select2-static" required>
                            <option value="Sesuai">Sesuai</option>
                            <option value="Tidak Sesuai">Tidak Sesuai</option>
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
<?php 
// Panggil "Kaki" (Template Footer)
include '../templates/footer.php'; 
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    // [PERBAIKAN] Variabel global (logic ini sudah Keren!)
    const daftarObatHTML = <?php echo json_encode(array_map(function($obat) {
        return '<option value="' . $obat['id_obat'] . '">' . htmlspecialchars($obat['kode_obat'] . ' - ' . $obat['nama_obat']) . '</option>';
    }, $all_obats)); ?>.join('');

    // Fungsi "ajaib" untuk mengaktifkan Select2 (Client-Side)
    function inisialisasiSelect2Obat(element) {
        $(element).select2({
            width: '100%', // Biar pas di kolom tabel
            placeholder: '-- Pilih / Cari Obat --'
        });
    }

    $(document).ready(function() {
        
        // --- 1. Aktifkan Select2 (standar) untuk dropdown statis
        $('.select2-static').select2({ 
            width: '100%', 
            minimumResultsForSearch: Infinity // Sembunyikan search bar
        });
        
        // --- 2. Aktifkan Select2 (Client-Side) untuk baris obat PERTAMA
        inisialisasiSelect2Obat('.obat-select');

        // --- 3. Logic untuk Tombol "+ Tambah Obat Lain"
        $('#tambah_baris_obat').click(function() {
            
            // [PERBAIKAN] Template baris baru yang sudah full Bootstrap
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
            
            // Tambahkan baris baru ke <tbody>
            $('#tbody_detail_obat').append(barisBaru);
            
            // PENTING: Aktifkan Select2 (Client-Side) pada <select> yang BARU saja dibuat
            inisialisasiSelect2Obat('.obat-select-baru:last');
        });

        // --- 4. Logic untuk Tombol "Hapus" (Event Delegation)
        $('#tbody_detail_obat').on('click', '.hapus-baris', function() {
            // Hapus <tr> (baris) tempat tombol ini berada
            $(this).closest('tr').remove();
        });

        // --- 5. Logic 'gagal' / 'sukses' (untuk reset URL) ---
        <?php if (isset($_GET['status'])): ?>
            var cleanUrl = "<?php echo BASE_URL . 'resep/tambah.php'; ?>";
            window.history.pushState({path: cleanUrl}, '', cleanUrl);
        <?php endif; ?>

    });
</script>