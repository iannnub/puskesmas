<?php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. Panggil "satpam" auth_check.php
require_once '../templates/auth_check.php';

// (SATPAM 2: ROLE CHECK - TIDAK PERLU)
// Semua role (1, 2, 3, 4) boleh melihat halaman ini.

// 4. Set Judul Halaman
$page_title = "Lihat Sisa Stok Obat";

// 5. [LOGIKA BACKEND UTAMA]
// --- TIDAK ADA YANG DIUBAH DARI SINI ---
try {
    // [A] Ambil data Kategori (untuk dropdown Form Filter)
    $sql_kategori = "SELECT 
                        k.id_kategori_obat, 
                        j.nama_jenis_obat, 
                        k.nama_kategori
                    FROM 
                        tbl_kategori_obat k
                    JOIN 
                        tbl_jenis_obat j ON k.id_jenis_obat = j.id_jenis_obat
                    ORDER BY 
                        j.nama_jenis_obat, k.nama_kategori ASC";
    $stmt_kategori = $pdo->query($sql_kategori);
    $kategoris = $stmt_kategori->fetchAll();
    
    // [B] Ambil data Unit (HANYA untuk filter Admin/Super Admin)
    $units = [];
    if ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2) {
        $stmt_unit = $pdo->query("SELECT id_unit, nama_unit FROM tbl_unit ORDER BY nama_unit ASC");
        $units = $stmt_unit->fetchAll();
    }

    // [C] Siapkan Variabel Filter & Search
    $filter_kategori = $_GET['kategori'] ?? '';
    $search_nama = $_GET['search'] ?? '';

    // [D] Siapkan Variabel Pagination
    $data_per_halaman = 20;
    $halaman_saat_ini = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
    if ($halaman_saat_ini < 1) $halaman_saat_ini = 1;
    $offset = ($halaman_saat_ini - 1) * $data_per_halaman;

    // [E] Bangun Query Dinamis (Base Query)
    $sql_base = "FROM 
                    tbl_stok_inventori s
                JOIN 
                    tbl_obat o ON s.id_obat = o.id_obat
                LEFT JOIN 
                    tbl_kategori_obat k ON o.id_kategori_obat = k.id_kategori_obat
                LEFT JOIN
                    tbl_jenis_obat j ON k.id_jenis_obat = j.id_jenis_obat";
    
    $where_conditions = []; // Array untuk menampung kondisi WHERE
    $params = []; // Array untuk menampung parameter PDO

    // [F] (KUNCI LOGIKA RBAC) Tentukan Filter Unit berdasarkan Role
    $filter_unit_id = '';
    
    if ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2) {
        // --- ADMIN / SUPER ADMIN ---
        $filter_unit_id = $_GET['unit'] ?? ''; 
    } else {
        // --- POLI DEPAN (3) / POLI BELAKANG (4) ---
        $filter_unit_id = $_SESSION['unit_stok_id'];
    }
    
    if (!empty($filter_unit_id)) {
        $where_conditions[] = "s.id_unit = ?";
        $params[] = $filter_unit_id;
    }

    if (!empty($filter_kategori)) {
        $where_conditions[] = "o.id_kategori_obat = ?";
        $params[] = $filter_kategori;
    }

    if (!empty($search_nama)) {
        $where_conditions[] = "(o.nama_obat LIKE ? OR o.kode_obat LIKE ?)";
        $params[] = "%" . $search_nama . "%";
        $params[] = "%" . $search_nama . "%";
    }

    $sql_where = "";
    if (!empty($where_conditions)) {
        $sql_where = " WHERE " . implode(" AND ", $where_conditions);
    }

    // [G] Query untuk COUNT (Menghitung Total Data untuk Pagination)
    $sql_count = "SELECT COUNT(s.id_stok) " . $sql_base . $sql_where;
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_data = $stmt_count->fetchColumn();
    $total_halaman = ceil($total_data / $data_per_halaman);

    // [H] Query untuk SELECT DATA (Ambil data stok sesuai halaman)
    $sql_stok = "SELECT 
                    s.id_stok, s.stok_akhir, s.stok_minimum, s.updated_at,
                    o.id_obat, o.kode_obat, o.nama_obat, o.satuan,
                    k.nama_kategori, j.nama_jenis_obat
                " . $sql_base . $sql_where . "
                ORDER BY 
                    o.nama_obat ASC
                LIMIT ? OFFSET ?";
    
    $params_data = $params; // Copy $params
    $params_data[] = $data_per_halaman;
    $params_data[] = $offset;

    $stmt_stok_list = $pdo->prepare($sql_stok);
    $stmt_stok_list->execute($params_data);
    $stoks = $stmt_stok_list->fetchAll();

} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}
// --- SAMPAI SINI LOGIC BACKEND AMAN ---

// 6. Panggil "Kepala" (Template Header)
include '../templates/header.php';
// Panggil Sidebar (jika terpisah)
// include '../templates/sidebar.php';
?>

<div class="container-fluid">

    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    <p class="mb-4">Halaman ini menampilkan sisa stok obat secara *real-time*.</p>
    
    <?php if ($_SESSION['role_id'] == 3): ?>
        <div class="alert alert-success shadow" role="alert">
            <i class="fas fa-info-circle"></i> Anda adalah <strong>Poli Depan</strong>. Stok yang tampil adalah stok dari <strong>APOTEK</strong>.
        </div>
    <?php elseif ($_SESSION['role_id'] == 4): ?>
        <div class="alert alert-warning shadow" role="alert">
            <i class="fas fa-info-circle"></i> Anda adalah <strong>Poli Belakang</strong>. Stok yang tampil adalah stok unit Anda sendiri (<strong><?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?></strong>).
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter"></i> Filter & Cari Stok Obat</h6>
                </div>
                <div class="card-body">
                    <form action="<?php echo BASE_URL; ?>stok/lihat.php" method="GET">
                        <div class="form-row">
                            
                            <?php $isAdmin = ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2); ?>
                            
                            <div class="form-group <?php echo $isAdmin ? 'col-md-3' : 'col-md-4'; ?>">
                                <label for="search">Cari Nama/Kode Obat:</label>
                                <input type="text" name="search" id="search" class="form-control" value="<?php echo htmlspecialchars($search_nama); ?>" placeholder="Ketik nama obat...">
                            </div>
                            
                            <div class="form-group <?php echo $isAdmin ? 'col-md-3' : 'col-md-4'; ?>">
                                <label for="kategori">Filter Kategori:</label>
                                <select name="kategori" id="kategori" class="form-control select2-filter">
                                    <option value="">-- Semua Kategori --</option>
                                    <?php foreach ($kategoris as $kategori): ?>
                                        <option value="<?php echo $kategori['id_kategori_obat']; ?>" 
                                            <?php echo ($filter_kategori == $kategori['id_kategori_obat']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($kategori['nama_jenis_obat'] . ' - ' . $kategori['nama_kategori']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if ($isAdmin): ?>
                            <div class="form-group col-md-3">
                                <label for="unit">Filter Unit Lokasi:</label>
                                <select name="unit" id="unit" class="form-control select2-filter">
                                    <option value="">-- Semua Unit --</option>
                                    <?php foreach ($units as $unit): ?>
                                        <option value="<?php echo $unit['id_unit']; ?>" 
                                            <?php echo ($filter_unit_id == $unit['id_unit']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($unit['nama_unit']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="form-group <?php echo $isAdmin ? 'col-md-3' : 'col-md-4'; ?> d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-icon-split mr-2">
                                    <span class="icon text-white-50"><i class="fas fa-search"></i></span>
                                    <span class="text">Filter</span>
                                </button>
                                <a href="<?php echo BASE_URL; ?>stok/lihat.php" class="btn btn-secondary btn-icon-split">
                                    <span class="icon text-white-50"><i class="fas fa-sync-alt"></i></span>
                                    <span class="text">Reset</span>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-boxes"></i> Daftar Stok Obat (Total: <?php echo $total_data; ?> item)</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Kode Obat</th>
                                    <th>Nama Obat</th>
                                    <th>Kategori</th>
                                    <th>Sisa Stok</th>
                                    <th>Min. Stok</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($stoks)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">
                                            <?php echo (empty($filter_kategori) && empty($search_nama) && empty($filter_unit_id)) ? 'Belum ada data stok.' : 'Data stok tidak ditemukan.'; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($stoks as $stok): ?>
                                        <?php
                                            // [PERBAIKAN] Logic untuk status stok kritis
                                            $status_badge = '<span class="badge badge-success">Aman</span>';
                                            $tr_class = ''; // Default
                                            if ($stok['stok_akhir'] == 0) {
                                                $status_badge = '<span class="badge badge-danger">Habis</span>';
                                                $tr_class = 'table-danger'; // Class Bootstrap
                                            } elseif ($stok['stok_akhir'] < $stok['stok_minimum']) {
                                                $status_badge = '<span class="badge badge-warning">Menipis</span>';
                                                $tr_class = 'table-warning'; // Class Bootstrap
                                            }
                                        ?>
                                        <tr class="<?php echo $tr_class; ?>">
                                            <td><?php echo htmlspecialchars($stok['kode_obat']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($stok['nama_obat']); ?>
                                                <small class="d-block text-muted">Satuan: <?php echo htmlspecialchars($stok['satuan']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars(isset($stok['nama_kategori']) ? $stok['nama_kategori'] : 'N/A'); ?></td>
                                            <td class="font-weight-bold text-right"><?php echo $stok['stok_akhir']; ?></td>
                                            <td class="text-right"><?php echo $stok['stok_minimum']; ?></td>
                                            <td><?php echo $status_badge; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <nav aria-label="Navigasi Halaman" class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php
                            // Siapkan parameter URL agar filter tetap menempel
                            $query_params = [];
                            if (!empty($search_nama)) $query_params['search'] = $search_nama;
                            if (!empty($filter_kategori)) $query_params['kategori'] = $filter_kategori;
                            if (!empty($filter_unit_id) && ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2)) {
                                $query_params['unit'] = $filter_unit_id;
                            }
                            
                            // Tombol "Sebelumnya"
                            if ($halaman_saat_ini > 1) {
                                $query_params['halaman'] = $halaman_saat_ini - 1;
                                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($query_params) . '">&laquo; Sebelumnya</a></li>';
                            } else {
                                echo '<li class="page-item disabled"><span class="page-link">&laquo; Sebelumnya</span></li>';
                            }

                            // Tampilkan Info Halaman (Versi simple)
                            echo '<li class="page-item active" aria-current="page"><span class="page-link">Halaman ' . $halaman_saat_ini . ' dari ' . $total_halaman . '</span></li>';

                            // Tombol "Berikutnya"
                            if ($halaman_saat_ini < $total_halaman) {
                                $query_params['halaman'] = $halaman_saat_ini + 1;
                                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($query_params) . '">Berikutnya &raquo;</a></li>';
                            } else {
                                echo '<li class="page-item disabled"><span class="page-link">Berikutnya &raquo;</span></li>';
                            }
                            ?>
                        </ul>
                    </nav>

                </div>
            </div>
        </div>
    </div>

</div>
<?php
// 8. Panggil "Kaki" (Template Footer)
include '../templates/footer.php';
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    // Aktifkan Select2 untuk dropdown filter
    $('.select2-filter').select2({
        width: '100%'
    });
});
</script>