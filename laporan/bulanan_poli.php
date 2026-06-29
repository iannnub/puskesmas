<?php

require_once '../config.php';
require_once '../templates/auth_check.php';


$id_role_user  = $_SESSION['id_role']  ?? 5;
$id_unit_login = $_SESSION['id_unit']  ?? 5; // ID unit milik user Poli Belakang

$is_admin        = in_array($id_role_user, [1,2,5]);
$is_poli_belakang = in_array($id_role_user, [4]);

// Hanya Admin dan Poli Belakang yang boleh akses
if (!$is_admin && !$is_poli_belakang) {
    $_SESSION['flash_error'] = 'Akses Ditolak! Halaman ini hanya untuk Poli Belakang.'; header("Location: " . BASE_URL . "dashboard.php"); exit;
    exit;
}

// Poli Belakang wajib punya id_unit
if ($is_poli_belakang && !$id_unit_login) {
    $_SESSION['flash_error'] = 'Akses Ditolak! Akun Anda tidak terhubung ke Unit manapun.'; header("Location: " . BASE_URL . "dashboard.php"); exit;
    exit;
}

$page_title = $is_admin ? "Laporan Bulanan Poli Belakang" : "Laporan Bulanan Unit Saya";

// ---------------------------------------------------------------
// AMBIL DAFTAR UNIT POLI BELAKANG
// Poli Belakang = unit yang ada di tbl_unit, KECUALI Apotek (id_unit=2) & Gudang (id_unit=1)
// ---------------------------------------------------------------
$semua_unit = [];
try {
    $stmt_all_unit = $pdo->query("
        SELECT id_unit, nama_unit
        FROM tbl_unit
        WHERE id_unit NOT IN (1, 2)
        ORDER BY nama_unit ASC
    ");
    $semua_unit = $stmt_all_unit->fetchAll();
} catch (PDOException $e) {
    die("Error mengambil daftar unit: " . $e->getMessage());
}

// ---------------------------------------------------------------
// TENTUKAN UNIT YANG DIPILIH
// ---------------------------------------------------------------
if ($is_admin) {
    // Admin: bisa pilih unit apapun dari GET, default ke unit pertama
    $id_unit_dipilih = isset($_GET['id_unit']) && $_GET['id_unit'] !== ''
                        ? (int)$_GET['id_unit']
                        : ($semua_unit[0]['id_unit'] ?? null);
} else {
    // Poli Belakang: hanya bisa lihat unit sendiri, abaikan GET
    $id_unit_dipilih = (int)$id_unit_login;
}

// ---------------------------------------------------------------
// AMBIL NAMA UNIT YANG DIPILIH
// ---------------------------------------------------------------
$nama_unit_dipilih = 'Unit Tidak Ditemukan';

try {
    if ($id_unit_dipilih) {
        $stmt_unit = $pdo->prepare("SELECT nama_unit FROM tbl_unit WHERE id_unit = ?");
        $stmt_unit->execute([$id_unit_dipilih]);
        $unit_row = $stmt_unit->fetch();
        if ($unit_row) {
            $nama_unit_dipilih = $unit_row['nama_unit'];
        }
    }
} catch (PDOException $e) {
    die("Error mengambil data unit: " . $e->getMessage());
}

// ---------------------------------------------------------------
// FILTER BULAN
// ---------------------------------------------------------------
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');

// ---------------------------------------------------------------
// VARIABEL LAPORAN
// ---------------------------------------------------------------
$obat_list            = [];
$data_keluar_pivot    = [];
$data_masuk_pivot     = [];
$data_stok_awal_pivot = [];

$grand_total_stok_awal  = 0;
$grand_total_masuk      = 0;
$grand_total_keluar     = 0;
$grand_total_stok_akhir = 0;
$ada_data = false;

// ---------------------------------------------------------------
// QUERY DATA LAPORAN
// ---------------------------------------------------------------
try {
    if (isset($_GET['bulan']) && $id_unit_dipilih) {

        $tgl_awal_bulan  = $filter_bulan . "-01 00:00:00";
        $tgl_akhir_bulan = $filter_bulan . "-" . date('t', strtotime($tgl_awal_bulan)) . " 23:59:59";

        // Daftar semua obat
        $stmt_obat = $pdo->query("SELECT id_obat, kode_obat, nama_obat FROM tbl_obat ORDER BY nama_obat ASC");
        $obat_list = $stmt_obat->fetchAll();

        // --- STOK AWAL (berdasarkan id_unit) ---
        $sql_stok_awal = "SELECT
                              id_obat,
                              SUM(masuk) - SUM(keluar) AS stok_awal
                          FROM tbl_log_stok
                          WHERE id_unit = ?
                            AND tgl_log < ?
                          GROUP BY id_obat";
        $stmt_stok_awal = $pdo->prepare($sql_stok_awal);
        $stmt_stok_awal->execute([$id_unit_dipilih, $tgl_awal_bulan]);
        foreach ($stmt_stok_awal->fetchAll() as $row) {
            $data_stok_awal_pivot[$row['id_obat']] = (int)$row['stok_awal'];
        }

        // --- MASUK (Transfer/Stok Opname ke unit ini) ---
        $sql_masuk = "SELECT
                          id_obat,
                          SUM(masuk) AS total_masuk
                      FROM tbl_log_stok
                      WHERE id_unit = ?
                        AND sumber_data IN ('Transfer', 'Stok Opname')
                        AND masuk > 0
                        AND tgl_log BETWEEN ? AND ?
                      GROUP BY id_obat";
        $stmt_masuk = $pdo->prepare($sql_masuk);
        $stmt_masuk->execute([$id_unit_dipilih, $tgl_awal_bulan, $tgl_akhir_bulan]);
        foreach ($stmt_masuk->fetchAll() as $row) {
            $data_masuk_pivot[$row['id_obat']] = (int)$row['total_masuk'];
        }

        // --- KELUAR (Pemakaian Resep dari unit ini) ---
        // Poli Belakang menggunakan id_unit_asal di tbl_resep_detail
        $sql_keluar = "SELECT
                           rd.id_obat,
                           SUM(rd.jumlah_keluar) AS total_keluar
                       FROM tbl_resep_detail rd
                       JOIN tbl_resep_header rh ON rd.id_resep = rh.id_resep
                       WHERE rd.id_unit_asal = ?
                         AND rh.tgl_resep BETWEEN ? AND ?
                       GROUP BY rd.id_obat";
        $stmt_keluar = $pdo->prepare($sql_keluar);
        $stmt_keluar->execute([$id_unit_dipilih, $tgl_awal_bulan, $tgl_akhir_bulan]);
        foreach ($stmt_keluar->fetchAll() as $row) {
            $data_keluar_pivot[$row['id_obat']] = (int)$row['total_keluar'];
        }
    }

} catch (PDOException $e) {
    die("Error mengambil data laporan: " . $e->getMessage());
}

include '../templates/header.php';
?>

<main class="content">
    <h2><?php echo htmlspecialchars($page_title); ?></h2>
    <p>
        Menampilkan rekap stok bulanan untuk
        <strong><?php echo htmlspecialchars($nama_unit_dipilih); ?></strong>.
        <?php if (!$is_admin): ?>
            <span class="badge badge-secondary">Hanya Unit Anda</span>
        <?php endif; ?>
    </p>
    <hr>

    <?php if (!$id_unit_dipilih): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            Unit tidak ditemukan atau belum dikonfigurasi. Hubungi Admin.
        </div>
    <?php else: ?>

    <!-- ===== FILTER ===== -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-filter"></i> Filter Laporan
            </h6>
        </div>
        <div class="card-body">
            <form action="<?php echo BASE_URL; ?>laporan/bulanan_poli.php" method="GET">
                <div class="form-row align-items-end">

                    <?php if ($is_admin): ?>
                    <!-- Dropdown Pilih Unit (hanya untuk Admin) -->
                    <div class="col-md-4">
                        <label for="id_unit">
                            <i class="fas fa-hospital"></i> Pilih Unit Poli Belakang
                        </label>
                        <select id="id_unit" name="id_unit" class="form-control" required>
                            <option value="">-- Pilih Unit --</option>
                            <?php foreach ($semua_unit as $unit): ?>
                                <option value="<?php echo $unit['id_unit']; ?>"
                                    <?php echo ($unit['id_unit'] == $id_unit_dipilih) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($unit['nama_unit']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <!-- Hidden input untuk Poli Belakang -->
                    <input type="hidden" name="id_unit" value="<?php echo $id_unit_dipilih; ?>">
                    <?php endif; ?>

                    <div class="col-md-3">
                        <label for="bulan">
                            <i class="fas fa-calendar-alt"></i> Pilih Bulan &amp; Tahun
                        </label>
                        <input type="month" id="bulan" name="bulan" class="form-control"
                               value="<?php echo htmlspecialchars($filter_bulan); ?>" required>
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-search"></i> Tampilkan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($_GET['bulan'])): ?>
    <!-- ===== TABEL LAPORAN ===== -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-table"></i>
                Laporan Bulanan &mdash; <?php echo htmlspecialchars($nama_unit_dipilih); ?>
                &nbsp;|&nbsp; Periode: <?php echo htmlspecialchars(date('F Y', strtotime($filter_bulan . '-01'))); ?>
            </h6>
            <div>
                <a href="export_excel.php?type=bulanan_poli&bulan=<?= urlencode($filter_bulan); ?>&id_unit=<?= $id_unit_dipilih; ?>"
                   class="btn btn-success btn-sm">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="export_pdf.php?type=bulanan_poli&bulan=<?= urlencode($filter_bulan); ?>&id_unit=<?= $id_unit_dipilih; ?>"
                   target="_blank" class="btn btn-danger btn-sm">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" style="width: 100%;" cellspacing="0">
                    <thead class="thead-dark">
                        <tr>
                            <th class="text-center align-middle" style="width: 50px;">No</th>
                            <th class="text-center align-middle">Kode Obat</th>
                            <th class="text-center align-middle">Nama Obat</th>
                            <th class="text-center align-middle">Stok Awal</th>
                            <th class="text-center align-middle">Masuk<br><small>(Transfer / Stok Opname)</small></th>
                            <th class="text-center align-middle">Keluar<br><small>(Pemakaian Resep)</small></th>
                            <th class="text-center align-middle">Stok Akhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($obat_list)): ?>
                            <tr>
                                <td colspan="7" class="text-center">Tidak ada data obat.</td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; ?>
                            <?php foreach ($obat_list as $obat): ?>
                                <?php
                                $id_obat    = $obat['id_obat'];
                                $stok_awal  = $data_stok_awal_pivot[$id_obat] ?? 0;
                                $masuk      = $data_masuk_pivot[$id_obat]     ?? 0;
                                $keluar     = $data_keluar_pivot[$id_obat]    ?? 0;
                                $stok_akhir = $stok_awal + $masuk - $keluar;

                                // Skip jika tidak ada aktivitas sama sekali
                                if ($stok_awal == 0 && $masuk == 0 && $keluar == 0) {
                                    continue;
                                }

                                $ada_data = true;
                                $grand_total_stok_awal  += $stok_awal;
                                $grand_total_masuk      += $masuk;
                                $grand_total_keluar     += $keluar;
                                $grand_total_stok_akhir += $stok_akhir;

                                $row_class = ($stok_akhir < 0) ? 'table-danger' : '';
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($obat['kode_obat']); ?></td>
                                    <td><?php echo htmlspecialchars($obat['nama_obat']); ?></td>
                                    <td class="text-right"><?php echo number_format($stok_awal); ?></td>
                                    <td class="text-right text-success font-weight-bold">
                                        <?php echo ($masuk > 0) ? '+' . number_format($masuk) : '&mdash;'; ?>
                                    </td>
                                    <td class="text-right text-danger font-weight-bold">
                                        <?php echo ($keluar > 0) ? '-' . number_format($keluar) : '&mdash;'; ?>
                                    </td>
                                    <td class="text-right font-weight-bold <?php echo ($stok_akhir < 0) ? 'text-danger' : ''; ?>">
                                        <?php echo number_format($stok_akhir); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$ada_data): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        <i class="fas fa-inbox"></i> Tidak ada aktivitas stok pada periode ini.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endif; ?>
                    </tbody>

                    <?php if ($ada_data): ?>
                    <tfoot class="thead-dark">
                        <tr>
                            <td colspan="3" class="text-right font-weight-bold">GRAND TOTAL</td>
                            <td class="text-right font-weight-bold"><?php echo number_format($grand_total_stok_awal); ?></td>
                            <td class="text-right font-weight-bold text-success">+<?php echo number_format($grand_total_masuk); ?></td>
                            <td class="text-right font-weight-bold text-danger">-<?php echo number_format($grand_total_keluar); ?></td>
                            <td class="text-right font-weight-bold"><?php echo number_format($grand_total_stok_akhir); ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>

            <?php if ($ada_data): ?>
            <!-- ===== KARTU RINGKASAN ===== -->
            <div class="row mt-4">
                <div class="col-md-3 mb-3">
                    <div class="card border-left-secondary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Stok Awal</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($grand_total_stok_awal); ?></div>
                                </div>
                                <div class="col-auto"><i class="fas fa-box fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Masuk</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($grand_total_masuk); ?></div>
                                </div>
                                <div class="col-auto"><i class="fas fa-arrow-down fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Pemakaian</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($grand_total_keluar); ?></div>
                                </div>
                                <div class="col-auto"><i class="fas fa-arrow-up fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Stok Akhir</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($grand_total_stok_akhir); ?></div>
                                </div>
                                <div class="col-auto"><i class="fas fa-boxes fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
    <?php endif; // end if isset bulan ?>

    <?php endif; // end if unit valid ?>

</main>

<?php include '../templates/footer.php'; ?>