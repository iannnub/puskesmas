<?php
// master/data_poli.php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. Panggil "satpam" auth_check.php
require_once '../templates/auth_check.php';

// 3. (SATPA M2: ROLE CHECK)
if ($_SESSION['role_id'] != 1) {
    echo "<script>alert('Akses Ditolak! Anda bukan Super Admin.'); window.location.href = '" . BASE_URL . "dashboard.php';</script>";
    exit;
}

// 4. Set Judul Halaman
$page_title = "Kelola Data Poli";

try {
    // Ambil data Unit (Lokasi Stok) untuk mengisi <select> dropdown
    $stmt_unit = $pdo->query("SELECT id_unit, nama_unit FROM tbl_unit ORDER BY nama_unit ASC");
    $units = $stmt_unit->fetchAll(); // Untuk form Tambah dan form Filter

    // --- LOGIKA FILTER ---
    $filter_unit_id = isset($_GET['filter_unit']) ? $_GET['filter_unit'] : ''; // Ambil ID unit dari URL

    // Siapkan query dasar
    $sql_poli = "SELECT 
                   p.id_poli, 
                   p.nama_poli,
                   u.nama_unit AS nama_unit_default
                 FROM 
                   tbl_poli p
                 JOIN 
                   tbl_unit u ON p.id_unit_stok_default = u.id_unit";

    $params = []; // Siapkan array parameter untuk PDO

    // Jika user MEMILIH filter unit
    if (!empty($filter_unit_id)) {
        // Tambahkan kondisi WHERE ke query
        $sql_poli .= " WHERE p.id_unit_stok_default = ?";
        $params[] = $filter_unit_id; // Tambahkan ID unit ke parameter
    }

    $sql_poli .= " ORDER BY p.id_poli ASC";

    // Eksekusi query
    $stmt_poli_list = $pdo->prepare($sql_poli);
    $stmt_poli_list->execute($params);
    $polis = $stmt_poli_list->fetchAll();

} catch (PDOException $e) {
    // Tangani error jika query gagal
    die("Error mengambil data: " . $e->getMessage());
}

// 6. Panggil "Kepala" (Template Header)
// (header.php OTOMATIS memanggil sidebar.php)
include '../templates/header.php';
?>

<!-- CSS Fix Select2 agar tampil seperti form-control (SB Admin compatible) -->
<style>
/* Make select2 look like bootstrap .form-control */
.select2-container--bootstrap4 .select2-selection--single,
.select2-container .select2-selection--single {
    height: calc(2.25rem + 2px) !important;
    padding: .375rem .75rem !important;
    border: 1px solid #ced4da !important;
    border-radius: .35rem !important;
    display: flex !important;
    align-items: center !important;
}

.select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered,
.select2-container .select2-selection__rendered {
    line-height: 1.4 !important;
    padding-left: 0 !important;
}

.select2-container--bootstrap4 .select2-selection__arrow,
.select2-container .select2-selection__arrow {
    height: 100% !important;
    right: 10px !important;
    top: 8px !important;
}

/* Hide clear 'x' if present */
.select2-container--bootstrap4 .select2-selection__clear,
.select2-container .select2-selection__clear {
    display: none !important;
}

/* Ensure select2 container fills width */
.select2-container { width: 100% !important; }
</style>

<main class="content">

    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>

    <p>Kelola unit pelayanan (Poli) dan tentukan dari mana mereka mengambil stok default (Kunci Logika Poli Depan vs. Belakang).</p>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Tambah Poli Baru</h6>
        </div>
        <div class="card-body">

            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] == 'tambah_sukses'): ?>
                    <div class="alert alert-success">Poli baru berhasil ditambahkan!</div>
                <?php elseif ($_GET['status'] == 'update_sukses'): ?>
                    <div class="alert alert-info">Data poli berhasil diupdate!</div>
                <?php elseif ($_GET['status'] == 'hapus_sukses'): ?>
                    <div class="alert alert-success">Poli berhasil dihapus!</div>
                <?php elseif ($_GET['status'] == 'gagal'): ?>
                     <div class="alert alert-danger">
                        Operasi gagal. <?php echo isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : 'Silakan coba lagi.'; ?>
                     </div>
                <?php endif; ?>
            <?php endif; ?>

            <form action="<?php echo BASE_URL; ?>master/proses_crud_poli.php" method="POST">
                <input type="hidden" name="action" value="create">

                <div class="form-row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="nama_poli">Nama Poli</label>
                            <input type="text" id="nama_poli" name="nama_poli" class="form-control" placeholder="Cth: Poli Umum" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                         <div class="form-group">
                            <label for="id_unit_stok_default">Ambil Stok Default Dari</label>
                            <!-- tambahkan class select2bs4 supaya nanti JS dapat target -->
                            <select id="id_unit_stok_default" name="id_unit_stok_default" class="form-control select2bs4" required>
                                <option value="">-- Pilih Unit Stok --</option>
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?php echo $unit['id_unit']; ?>"><?php echo htmlspecialchars($unit['nama_unit']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">(KUNCI LOGIKA: Poli Depan -> APOTEK, Poli Belakang -> Unitnya sendiri)</small>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="fas fa-plus"></i> Simpan Poli Baru
                </button>
            </form>
        </div>
    </div>


    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Poli Sistem</h6>
        </div>
        <div class="card-body">

            <form action="<?php echo BASE_URL; ?>master/data_poli.php" method="GET" class="mb-3">
                <div class="form-row">
                    <div class="col-md-4">
                        <label for="filter_unit">Filter berdasarkan Unit Stok Default:</label>
                        <!-- juga beri class select2bs4 -->
                        <select name="filter_unit" id="filter_unit" class="form-control select2bs4">
                            <option value="">-- Tampilkan Semua --</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?php echo $unit['id_unit']; ?>" <?php echo ($filter_unit_id == $unit['id_unit']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($unit['nama_unit']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-filter"></i> Filter</button>
                        <a href="<?php echo BASE_URL; ?>master/data_poli.php" class="btn btn-secondary"><i class="fas fa-sync-alt"></i> Reset</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="dataTable" width="100%" cellspacing="0">
                    <thead class="thead-dark">
                        <tr>
                            <th>ID Poli</th>
                            <th>Nama Poli</th>
                            <th>Unit Stok Default</th>
                            <th>Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($polis)): ?>
                            <tr>
                                <td colspan="4" class="text-center">
                                    <?php echo empty($filter_unit_id) ? 'Belum ada data poli.' : 'Tidak ada data poli untuk unit yang difilter.'; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($polis as $poli): ?>
                                <tr>
                                    <td><?php echo $poli['id_poli']; ?></td>
                                    <td><?php echo htmlspecialchars($poli['nama_poli']); ?></td>
                                    <td><?php echo htmlspecialchars($poli['nama_unit_default']); ?></td>
                                    <td>
                                        <a href="#" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i> Ubah
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>master/proses_crud_poli.php?action=delete&id=<?php echo $poli['id_poli']; ?>"
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('PERINGATAN! Menghapus poli ini bisa GAGAL jika masih terpakai oleh user atau data resep.\n\nLanjutkan menghapus poli (<?php echo htmlspecialchars($poli['nama_poli']); ?>)?');">
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

<!-- Inisialisasi Select2 (cek dulu apakah Select2 tersedia) -->
<script>
$(document).ready(function() {
    if (typeof $.fn.select2 === 'function') {
        // Terapkan Select2 dengan theme bootstrap4 ke semua elemen yang pake class select2bs4
        $('.select2bs4').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: '-- Pilih --',
            allowClear: true
        });
    } else {
        console.warn('Select2 tidak terdeteksi. Pastikan Select2 JS & CSS sudah diload di footer.php atau header.');
    }
});
</script>
