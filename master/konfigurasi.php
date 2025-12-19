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
$page_title = "Konfigurasi Sistem";

// 5. Logic untuk AMBIL DATA (READ)
try {
    // Ambil SEMUA setting dari tbl_konfigurasi
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM tbl_konfigurasi");
    $all_settings = $stmt->fetchAll();
    
    // [KUNCI LOGIKA] Ubah array-nya agar gampang dipakai di HTML
    $konfigurasi = [];
    foreach ($all_settings as $setting) {
        $konfigurasi[$setting['setting_key']] = $setting['setting_value'];
    }

} catch (PDOException $e) {
    // Tangani error jika query gagal
    die("Error mengambil data konfigurasi: " . $e->getMessage());
}

// 6. Panggil "Kepala" (Template Header)
// (header.php OTOMATIS memanggil sidebar.php)
include '../templates/header.php';

?>

<main class="content">

    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    
    <p>Atur parameter global yang akan digunakan di seluruh sistem, seperti kop surat laporan.</p>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Pengaturan Global</h6>
        </div>
        <div class="card-body">
            
            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] == 'update_sukses'): ?>
                    <div class="alert alert-success">Konfigurasi sistem berhasil diupdate!</div>
                <?php elseif ($_GET['status'] == 'gagal'): ?>
                     <div class="alert alert-danger">
                        Operasi gagal. <?php echo isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : 'Silakan coba lagi.'; ?>
                     </div>
                <?php endif; ?>
            <?php endif; ?>

            <form action="<?php echo BASE_URL; ?>master/proses_konfigurasi.php" method="POST">
                
                <div class="form-row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="nama_puskesmas">Nama Puskesmas</label>
                            <input type="text" id="nama_puskesmas" name="nama_puskesmas" class="form-control" 
                                   value="<?php echo htmlspecialchars(isset($konfigurasi['nama_puskesmas']) ? $konfigurasi['nama_puskesmas'] : ''); ?>" 
                                   required>
                            <small class="form-text text-muted">(Akan tampil di header laporan)</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="telepon_puskesmas">Telepon</label>
                            <input type="text" id="telepon_puskesmas" name="telepon_puskesmas" class="form-control" 
                                   value="<?php echo htmlspecialchars(isset($konfigurasi['telepon_puskesmas']) ? $konfigurasi['telepon_puskesmas'] : ''); ?>" 
                                   required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="alamat_puskesmas">Alamat Puskesmas</label>
                    <textarea id="alamat_puskesmas" name="alamat_puskesmas" class="form-control" rows="3"
                              required><?php echo htmlspecialchars(isset($konfigurasi['alamat_puskesmas']) ? $konfigurasi['alamat_puskesmas'] : ''); ?></textarea>
                </div>
                
                <hr>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
                
            </form>
        </div>
    </div>

</main>
<?php
// 8. Panggil "Kaki" (Template Footer)
include '../templates/footer.php';
?>