<?php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. Panggil "satpam" auth_check.php
require_once '../templates/auth_check.php';

// 3. (SATPAM 2: ROLE CHECK)
if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) {
    echo "<script>alert('Akses Ditolak! Anda bukan Super Admin atau Admin.'); window.location.href = '" . BASE_URL . "dashboard.php';</script>";
    exit;
}

// 4. Set Judul Halaman
$page_title = "Laporan Rekap Kunjungan (Pelayanan Resep)";

// 5. [PERBAIKAN] Logic untuk AMBIL DATA (READ) + FILTER
$laporan_kunjungan = []; // Array untuk menampung hasil
// [PERBAIKAN] Ganti '??' dengan 'isset()' agar kompatibel
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m'); // Default ke bulan ini

try {
    if (isset($_GET['bulan'])) { 
        
        // --- Query Utama: Mengambil rekap kunjungan harian ---
        // Ini menggantikan sheet 'KUNJ'
        $sql_laporan = "SELECT 
                            DATE(rh.tgl_resep) AS tanggal,
                            SUM(CASE WHEN rh.id_pelayanan = 1 THEN 1 ELSE 0 END) AS total_umum,
                            SUM(CASE WHEN rh.id_pelayanan = 2 THEN 1 ELSE 0 END) AS total_bpjs,
                            COUNT(rh.id_resep) AS total_pelayanan
                        FROM 
                            tbl_resep_header rh
                        WHERE 
                            DATE_FORMAT(rh.tgl_resep, '%Y-%m') = ? 
                        GROUP BY 
                            DATE(rh.tgl_resep)
                        ORDER BY 
                            tanggal ASC";
        
        $stmt_laporan = $pdo->prepare($sql_laporan);
        $stmt_laporan->execute([$filter_bulan]);
        $laporan_kunjungan = $stmt_laporan->fetchAll();
    }

} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}

// 6. Panggil Header & Sidebar
include '../templates/header.php';
?>

<main class="content">

    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    
    <p>Halaman ini menampilkan rekap kunjungan pasien yang menggunakan pelayanan UMUM dan BPJS per hari. (Pengganti sheet KUNJ).</p>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Laporan</h6>
        </div>
        <div class="card-body">
            <form action="<?php echo BASE_URL; ?>laporan/kunjungan.php" method="GET">
                <div class="form-row">
                    <div class="col-md-3">
                        <label for="bulan">Pilih Bulan & Tahun</label>
                        <input type="month" id="bulan" name="bulan" class="form-control" 
                               value="<?php echo htmlspecialchars($filter_bulan); ?>" required>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Tampilkan Laporan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($_GET['bulan'])): // Tampilkan hanya jika sudah difilter ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                Rekap Pelayanan Resep (Periode: <?php echo htmlspecialchars(date('F Y', strtotime($filter_bulan . '-01'))); ?>)
            </h6>
            <div>
                <button class="btn btn-success btn-sm">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
                <button class="btn btn-danger btn-sm">
                    <i class="fas fa-file-pdf"></i> Cetak PDF
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" style="width: 100%;" cellspacing="0">
                    <thead class="thead-dark">
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Jenis Pelayanan (UMUM)</th>
                            <th>Jenis Pelayanan (BPJS)</th>
                            <th>Total Pelayanan</th>
                        </tr>
                    </thead>
                    <tbody>
                        
                        <?php 
                        $nomor = 1;
                        $grand_total_umum = 0;
                        $grand_total_bpjs = 0;
                        $grand_total_semua = 0;
                        ?>

                        <?php if (empty($laporan_kunjungan)): ?>
                            <tr>
                                <td colspan="5" class="text-center">Tidak ada data kunjungan pada periode <?php echo htmlspecialchars(date('F Y', strtotime($filter_bulan . '-01'))); ?>.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($laporan_kunjungan as $data): ?>
                                <?php
                                $grand_total_umum += $data['total_umum'];
                                $grand_total_bpjs += $data['total_bpjs'];
                                $grand_total_semua += $data['total_pelayanan'];
                                ?>
                                <tr>
                                    <td><?php echo $nomor++; ?></td>
                                    <td><?php echo htmlspecialchars(date('d-m-Y', strtotime($data['tanggal']))); ?></td>
                                    <td class="text-right"><?php echo $data['total_umum']; ?></td>
                                    <td class="text-right"><?php echo $data['total_bpjs']; ?></td>
                                    <td class="text-right font-weight-bold"><?php echo $data['total_pelayanan']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    
                    <?php if ($grand_total_semua > 0): ?>
                    <tfoot class="thead-dark">
                        <tr>
                            <td colspan="2" class="text-right font-weight-bold">GRAND TOTAL</td>
                            <td class="text-right font-weight-bold"><?php echo $grand_total_umum; ?></td>
                            <td class="text-right font-weight-bold"><?php echo $grand_total_bpjs; ?></td>
                            <td class="text-right font-weight-bold"><?php echo $grand_total_semua; ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    <?php endif; // Selesai blok "jika sudah difilter" ?>

</main>
<?php 
// Panggil "Kaki" (Template Footer)
include '../templates/footer.php'; 
?>