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
$page_title = "Laporan Pemakaian Harian";

// 5. [PERBAIKAN] Logic untuk AMBIL DATA (READ) + FILTER
$laporan_harian = []; // Array untuk menampung hasil
$filter_tgl = isset($_GET['tgl']) ? $_GET['tgl'] : date('Y-m-d'); // Default ke hari ini

try {
    if (isset($_GET['tgl'])) { 
        
        $sql_laporan = "SELECT 
                            o.kode_obat, 
                            o.nama_obat, 
                            p.nama_poli,
                            SUM(rd.jumlah_keluar) AS total_keluar
                        FROM 
                            tbl_resep_detail rd
                        JOIN 
                            tbl_resep_header rh ON rd.id_resep = rh.id_resep
                        JOIN 
                            tbl_obat o ON rd.id_obat = o.id_obat
                        JOIN 
                            tbl_poli p ON rh.id_poli = p.id_poli
                        WHERE 
                            DATE(rh.tgl_resep) = ? 
                        GROUP BY 
                            o.id_obat, p.id_poli
                        ORDER BY 
                            o.nama_obat ASC, p.nama_poli ASC";
        
        $stmt_laporan = $pdo->prepare($sql_laporan);
        $stmt_laporan->execute([$filter_tgl]);
        $laporan_harian = $stmt_laporan->fetchAll();
    }

} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}

// 6. Panggil Header & Sidebar
include '../templates/header.php';
?>

<main class="content">

    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    
    <p>Halaman ini menampilkan rekap total pemakaian obat per poli untuk tanggal yang dipilih. (Pengganti sheet HARIAN).</p>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Laporan</h6>
        </div>
        <div class="card-body">
            <form action="<?php echo BASE_URL; ?>laporan/harian.php" method="GET">
                <div class="form-row">
                    <div class="col-md-3">
                        <label for="tgl">Pilih Tanggal Laporan</label>
                        <input type="date" id="tgl" name="tgl" class="form-control" 
                               value="<?php echo htmlspecialchars($filter_tgl); ?>" required>
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

    <?php if (isset($_GET['tgl'])): // Tampilkan hanya jika sudah difilter ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                Rekap Gudang Harian (<?php echo htmlspecialchars(date('d F Y', strtotime($filter_tgl))); ?>)
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
                            <th>Kode Obat</th>
                            <th>Nama Obat</th>
                            <th>Poli</th>
                            <th>Jumlah Keluar</th>
                        </tr>
                    </thead>
                    <tbody>
                        
                        <?php 
                        $current_obat = null; 
                        $total_obat = 0;
                        $grand_total = 0;
                        ?>

                        <?php if (empty($laporan_harian)): ?>
                            <tr>
                                <td colspan="4" class="text-center">Tidak ada data pemakaian pada tanggal <?php echo htmlspecialchars(date('d-m-Y', strtotime($filter_tgl))); ?>.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($laporan_harian as $index => $data): ?>
                                <?php
                                // Logic untuk menampilkan Total per Obat
                                if ($current_obat != $data['nama_obat'] && $current_obat !== null) {
                                    echo '<tr class="table-secondary font-weight-bold">';
                                    echo '<td colspan="3" class="text-right">' . htmlspecialchars($current_obat) . ' Total</td>';
                                    echo '<td class="text-right">' . $total_obat . '</td>';
                                    echo '</tr>';
                                    $total_obat = 0; // Reset total
                                }
                                $current_obat = $data['nama_obat'];
                                $total_obat += $data['total_keluar'];
                                $grand_total += $data['total_keluar'];
                                ?>
                                
                                <tr>
                                    <td><?php echo htmlspecialchars($data['kode_obat']); ?></td>
                                    <td><?php echo htmlspecialchars($data['nama_obat']); ?></td>
                                    <td><?php echo htmlspecialchars($data['nama_poli']); ?></td>
                                    <td class="text-right"><?php echo $data['total_keluar']; ?></td>
                                </tr>

                                <?php 
                                // Jika ini adalah baris TERAKHIR, cetak total terakhir
                                if ($index == count($laporan_harian) - 1): 
                                ?>
                                    <tr class="table-secondary font-weight-bold">
                                        <td colspan="3" class="text-right"><?php echo htmlspecialchars($current_obat); ?> Total</td>
                                        <td class="text-right"><?php echo $total_obat; ?></td>
                                    </tr>
                                <?php endif; ?>

                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    
                    <?php if ($grand_total > 0): ?>
                    <tfoot class="thead-dark">
                        <tr>
                            <td colspan="3" class="text-right font-weight-bold">GRAND TOTAL</td>
                            <td class="text-right font-weight-bold"><?php echo $grand_total; ?></td>
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