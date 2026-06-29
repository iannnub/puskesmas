<?php

require_once '../config.php';
require_once '../templates/auth_check.php';

$page_title = "Lihat Sisa Stok Obat";

// ============================================================
// Mapping role ke unit (unit_stok_id di session mungkin tidak
// selalu tersedia untuk semua role, jadi kita definisikan di sini)
// ============================================================
// Role 1 = Super Admin   → bisa lihat semua unit (filter bebas)
// Role 2 = Admin         → bisa lihat semua unit (filter bebas)
// Role 3 = Poli Depan    → hanya lihat APOTEK (id_unit = 2)
// Role 4 = Poli Belakang → hanya lihat unit sendiri (unit_stok_id dari session)
// Role 5 = Apotek        → hanya lihat APOTEK (id_unit = 2)

// Konstanta ID unit APOTEK — sesuaikan jika berbeda di DB Anda
define('ID_UNIT_APOTEK', 2);

try {
    // --- Ambil data kategori untuk dropdown filter ---
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

    // --- Ambil daftar unit (hanya untuk Admin) ---
    $units = [];
    $isAdmin = ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2);
    if ($isAdmin) {
        $stmt_unit = $pdo->query("SELECT id_unit, nama_unit FROM tbl_unit ORDER BY nama_unit ASC");
        $units = $stmt_unit->fetchAll();
    }

    // ============================================================
    // Tentukan filter unit berdasarkan role
    // ============================================================
    $filter_unit_id = '';
    $unit_locked    = false; // true = user tidak bisa ganti unit

    switch ($_SESSION['role_id']) {
        case 1: // Super Admin — bebas pilih unit
        case 2: // Admin (Operator) — bebas pilih unit
            $filter_unit_id = $_GET['unit'] ?? '';
            break;

        case 3: // Poli Depan — paksa APOTEK
            $filter_unit_id = ID_UNIT_APOTEK;
            $unit_locked    = true;
            break;

        case 4: // Poli Belakang — paksa unit sendiri
            $filter_unit_id = $_SESSION['unit_stok_id'] ?? '';
            $unit_locked    = true;
            break;

        case 5: // Apotek — paksa APOTEK
            $filter_unit_id = ID_UNIT_APOTEK;
            $unit_locked    = true;
            break;

        default:
            $filter_unit_id = $_SESSION['unit_stok_id'] ?? '';
            $unit_locked    = true;
    }

    // --- Filter tambahan dari GET ---
    $filter_kategori = $_GET['kategori'] ?? '';
    $search_nama     = $_GET['search']   ?? '';
    $filter_id_obat  = isset($_GET['id_obat']) ? (int)$_GET['id_obat'] : 0;

    // --- Ambil daftar semua obat untuk dropdown pilih obat ---
    $stmt_all_obat = $pdo->query("SELECT id_obat, kode_obat, nama_obat FROM tbl_obat ORDER BY id_obat ASC");
    $all_obat = $stmt_all_obat->fetchAll(PDO::FETCH_ASSOC);

    // --- Pagination ---
    $data_per_halaman  = 20;
    $halaman_saat_ini  = isset($_GET['halaman']) ? max(1, (int)$_GET['halaman']) : 1;
    $offset            = ($halaman_saat_ini - 1) * $data_per_halaman;

    // --- Base query ---
    $sql_base = "FROM 
                    tbl_stok_inventori s
                JOIN 
                    tbl_obat o ON s.id_obat = o.id_obat
                LEFT JOIN 
                    tbl_kategori_obat k ON o.id_kategori_obat = k.id_kategori_obat
                LEFT JOIN
                    tbl_jenis_obat j ON k.id_jenis_obat = j.id_jenis_obat";

    $where_conditions = [];
    $params           = [];

    // Filter unit
    if (!empty($filter_unit_id)) {
        $where_conditions[] = "s.id_unit = ?";
        $params[]           = $filter_unit_id;
    }

    // Filter kategori
    if (!empty($filter_kategori)) {
        $where_conditions[] = "o.id_kategori_obat = ?";
        $params[]           = $filter_kategori;
    }

    // Filter nama / kode obat (via id_obat dari dropdown)
    if (!empty($filter_id_obat)) {
        $where_conditions[] = "o.id_obat = ?";
        $params[]           = $filter_id_obat;
    }

    $sql_where = !empty($where_conditions)
        ? " WHERE " . implode(" AND ", $where_conditions)
        : "";

    // --- Hitung total data ---
    $sql_count = "SELECT COUNT(s.id_stok) " . $sql_base . $sql_where;
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_data    = $stmt_count->fetchColumn();
    $total_halaman = max(1, (int)ceil($total_data / $data_per_halaman));

    // --- Ambil data stok ---
    $sql_stok = "SELECT 
                    s.id_stok, s.stok_akhir, s.stok_minimum, s.updated_at,
                    o.id_obat, o.kode_obat, o.nama_obat, o.satuan,
                    k.nama_kategori, j.nama_jenis_obat,
                    u.nama_unit
                " . $sql_base . "
                LEFT JOIN tbl_unit u ON s.id_unit = u.id_unit"
                . $sql_where . "
                ORDER BY 
                    o.nama_obat ASC
                LIMIT ? OFFSET ?";

    $params_data   = $params;
    $params_data[] = $data_per_halaman;
    $params_data[] = $offset;

    $stmt_stok_list = $pdo->prepare($sql_stok);
    $stmt_stok_list->execute($params_data);
    $stoks = $stmt_stok_list->fetchAll();

} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}

include '../templates/header.php';
?>

<div class="container-fluid">

    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    <p class="mb-4">Halaman ini menampilkan sisa stok obat secara <em>real-time</em>.</p>

    <?php
    // ---- Info banner sesuai role ----
    switch ($_SESSION['role_id']):
        case 3: ?>
            <div class="alert alert-success shadow" role="alert">
                <i class="fas fa-info-circle"></i>
                Anda adalah <strong>Poli Depan</strong>. Stok yang tampil adalah stok dari <strong>APOTEK</strong>.
            </div>
        <?php break;
        case 4: ?>
            <div class="alert alert-warning shadow" role="alert">
                <i class="fas fa-info-circle"></i>
                Anda adalah <strong>Poli Belakang</strong>. Stok yang tampil adalah stok unit Anda sendiri
                (<strong><?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?></strong>).
            </div>
        <?php break;
        case 5: ?>
            <div class="alert alert-info shadow" role="alert">
                <i class="fas fa-clinic-medical"></i>
                Anda adalah <strong>Apotek</strong>. Stok yang tampil adalah stok <strong>APOTEK</strong> Anda sendiri.
            </div>
        <?php break;
    endswitch;
    ?>

    <div class="row">

        <!-- ===== CARD FILTER ===== -->
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-filter"></i> Filter &amp; Cari Stok Obat
                    </h6>
                </div>
                <div class="card-body">
                    <form action="<?php echo BASE_URL; ?>stok/lihat.php" method="GET">
                        <div class="form-row">

                            <!-- Pilih Obat (dropdown Select2) -->
                            <div class="form-group <?php echo $isAdmin ? 'col-md-3' : 'col-md-4'; ?>">
                                <label for="id_obat">Pilih Obat:</label>
                                <select name="id_obat" id="id_obat" class="form-control select2-filter">
                                    <option value="">-- Semua Obat --</option>
                                    <?php foreach ($all_obat as $obat): ?>
                                        <option value="<?php echo $obat['id_obat']; ?>"
                                            <?php echo ($filter_id_obat == $obat['id_obat']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($obat['kode_obat'] . ' - ' . $obat['nama_obat']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Filter kategori -->
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

                            <!-- Filter unit (hanya Admin) -->
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

                            <!-- Tombol -->
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

        <!-- ===== CARD TABEL STOK ===== -->
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-boxes"></i>
                        Daftar Stok Obat (Total: <?php echo $total_data; ?> item)
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Kode Obat</th>
                                    <th>Nama Obat</th>
                                    <th>Kategori</th>
                                    <?php if ($isAdmin && empty($filter_unit_id)): ?>
                                    <th>Unit</th>
                                    <?php endif; ?>
                                    <th class="text-right">Sisa Stok</th>
                                    <th class="text-right">Min. Stok</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($stoks)): ?>
                                    <tr>
                                        <td colspan="<?php echo ($isAdmin && empty($filter_unit_id)) ? 7 : 6; ?>" class="text-center text-muted">
                                            <?php echo (empty($filter_kategori) && empty($filter_id_obat) && empty($filter_unit_id))
                                                ? '<i class="fas fa-box-open fa-2x mb-2 d-block"></i>Belum ada data stok.'
                                                : '<i class="fas fa-search fa-2x mb-2 d-block"></i>Data stok tidak ditemukan dengan filter ini.'; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($stoks as $stok):
                                        // Tentukan badge dan warna baris
                                        $status_badge = '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Aman</span>';
                                        $tr_class     = '';
                                        if ($stok['stok_akhir'] == 0) {
                                            $status_badge = '<span class="badge badge-danger"><i class="fas fa-times-circle"></i> Habis</span>';
                                            $tr_class     = 'table-danger';
                                        } elseif ($stok['stok_akhir'] < $stok['stok_minimum']) {
                                            $status_badge = '<span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> Menipis</span>';
                                            $tr_class     = 'table-warning';
                                        }
                                    ?>
                                    <tr class="<?php echo $tr_class; ?>">
                                        <td><?php echo htmlspecialchars($stok['kode_obat']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($stok['nama_obat']); ?>
                                            <small class="d-block text-muted">
                                                Satuan: <?php echo htmlspecialchars($stok['satuan']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php
                                            $kat = $stok['nama_jenis_obat'] ?? '';
                                            $sub = $stok['nama_kategori']  ?? '';
                                            echo htmlspecialchars($kat ? ($sub ? "$kat — $sub" : $kat) : ($sub ?: 'N/A'));
                                            ?>
                                        </td>
                                        <?php if ($isAdmin && empty($filter_unit_id)): ?>
                                        <td><?php echo htmlspecialchars($stok['nama_unit'] ?? 'N/A'); ?></td>
                                        <?php endif; ?>
                                        <td class="font-weight-bold text-right"><?php echo number_format($stok['stok_akhir']); ?></td>
                                        <td class="text-right"><?php echo number_format($stok['stok_minimum']); ?></td>
                                        <td><?php echo $status_badge; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <nav aria-label="Navigasi Halaman" class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php
                            $query_params = [];
                            if (!empty($filter_id_obat))  $query_params['id_obat']  = $filter_id_obat;
                            if (!empty($filter_kategori)) $query_params['kategori'] = $filter_kategori;
                            if ($isAdmin && !empty($filter_unit_id)) $query_params['unit'] = $filter_unit_id;

                            // Tombol Sebelumnya
                            if ($halaman_saat_ini > 1) {
                                $query_params['halaman'] = $halaman_saat_ini - 1;
                                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($query_params) . '">&laquo; Sebelumnya</a></li>';
                            } else {
                                echo '<li class="page-item disabled"><span class="page-link">&laquo; Sebelumnya</span></li>';
                            }

                            // Info halaman
                            echo '<li class="page-item active" aria-current="page">
                                    <span class="page-link">Halaman ' . $halaman_saat_ini . ' dari ' . $total_halaman . '</span>
                                  </li>';

                            // Tombol Berikutnya
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

    </div><!-- /.row -->

</div><!-- /.container-fluid -->


<?php ob_start(); ?>
<script>
$(document).ready(function () {
    // Select2 untuk filter kategori & unit
    $('.select2-filter').not('#id_obat').select2({ width: '100%' });

    // Select2 khusus untuk pilih obat — dengan pencarian teks
    $('#id_obat').select2({
        width: '100%',
        placeholder: '-- Cari / Pilih Obat --',
        allowClear: true,
        language: {
            noResults: function () { return 'Obat tidak ditemukan'; },
            searching:  function () { return 'Mencari...'; }
        }
    });
});
</script>
<?php
$extra_scripts = ob_get_clean();
include '../templates/footer.php';
?>