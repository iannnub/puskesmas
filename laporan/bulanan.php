<?php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. Panggil "satpam" auth_check.php
require_once '../templates/auth_check.php';

// 3. (SATPAM 2: ROLE CHECK)
// Hanya SUPER ADMIN (Role ID 1) dan ADMIN (Role ID 2) yang boleh
if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) {
    echo "<script>alert('Akses Ditolak! Anda bukan Super Admin atau Admin.'); window.location.href = '" . BASE_URL . "dashboard.php';</script>";
    exit;
}

// 4. Set Judul Halaman
$page_title = "Laporan Bulanan (Rekap Stok & Pemakaian per Poli)";

// 5. [PERBAIKAN] Logic untuk AMBIL DATA (READ) + FILTER
// [PERBAIKAN] Ganti '??' dengan 'isset()'
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m'); // Default ke bulan ini

// Variabel untuk menampung data query
$polis_depan = [];
$obat_list = [];
$data_keluar_pivot = [];
$data_masuk_pivot = [];
$data_stok_awal_pivot = [];
$total_stok_keluar_per_poli = []; // Untuk TFOOT

try {
    // [A] Ambil daftar Poli DEPAN (untuk header tabel)
    // Asumsi Poli Depan (ambil stok Apotek) adalah yang ingin ditampilkan
    $stmt_poli = $pdo->query("SELECT id_poli, nama_poli FROM tbl_poli WHERE id_unit_stok_default = 2 ORDER BY id_poli ASC");
    $polis_depan = $stmt_poli->fetchAll();
    
    // Inisialisasi array total footer
    $total_stok_keluar_per_poli = array_fill_keys(array_column($polis_depan, 'id_poli'), 0);
    // [LOGIKA BARU] Tambahkan "Lainnya" untuk Poli Belakang (UGD, dll)
    $total_stok_keluar_per_poli['lainnya'] = 0;

    
    // [B] JIKA user sudah memfilter, jalankan query laporan
    if (isset($_GET['bulan'])) {
        
        // Tentukan tanggal awal dan akhir periode
        $tgl_awal_bulan = $filter_bulan . "-01 00:00:00";
        $tgl_akhir_bulan = $filter_bulan . "-" . date('t', strtotime($tgl_awal_bulan)) . " 23:59:59"; 

        // [C] Ambil daftar Obat (basis laporan)
        $stmt_obat = $pdo->query("SELECT id_obat, kode_obat, nama_obat FROM tbl_obat ORDER BY nama_obat ASC");
        $obat_list = $stmt_obat->fetchAll();

        // [D] Query 1: Ambil data KELUAR (PIVOT)
        $sql_keluar = "SELECT 
                           rh.id_poli, 
                           rd.id_obat, 
                           p.id_unit_stok_default,
                           SUM(rd.jumlah_keluar) as total_keluar 
                       FROM 
                           tbl_resep_detail rd 
                       JOIN 
                           tbl_resep_header rh ON rd.id_resep = rh.id_resep 
                       JOIN
                           tbl_poli p ON rh.id_poli = p.id_poli
                       WHERE 
                           rh.tgl_resep BETWEEN ? AND ?
                       GROUP BY 
                           rd.id_obat, rh.id_poli";
        $stmt_keluar = $pdo->prepare($sql_keluar);
        $stmt_keluar->execute([$tgl_awal_bulan, $tgl_akhir_bulan]);
        $data_keluar_raw = $stmt_keluar->fetchAll();
        
        // [LOGIKA BARU] Ubah data mentah menjadi array pivot
        foreach ($data_keluar_raw as $data) {
            $id_obat = $data['id_obat'];
            $id_poli = $data['id_poli'];
            $id_unit_asal = $data['id_unit_stok_default'];
            $total = $data['total_keluar'];
            
            // [LOGIKA PIVOT] Cek apakah ini Poli Depan (unit 2) atau Poli Belakang (unit lain)
            if ($id_unit_asal == 2) {
                // Jika Poli Depan (Umum, Gigi, dll)
                $data_keluar_pivot[$id_obat][$id_poli] = (isset($data_keluar_pivot[$id_obat][$id_poli]) ? $data_keluar_pivot[$id_obat][$id_poli] : 0) + $total;
            } else {
                // Jika Poli Belakang (UGD, Rawat Inap, dll), gabung ke kolom "Lainnya"
                $data_keluar_pivot[$id_obat]['lainnya'] = (isset($data_keluar_pivot[$id_obat]['lainnya']) ? $data_keluar_pivot[$id_obat]['lainnya'] : 0) + $total;
            }
        }

        // [E] Query 2: Ambil data MASUK (Hanya ke GUDANG)
        $sql_masuk = "SELECT 
                          id_obat, 
                          SUM(masuk) as total_masuk 
                      FROM 
                          tbl_log_stok 
                      WHERE 
                          (sumber_data = 'Penerimaan' OR sumber_data = 'Stok Opname') 
                          AND masuk > 0 
                          AND id_unit = 1 -- Hanya GUDANG
                          AND (tgl_log BETWEEN ? AND ?)
                      GROUP BY 
                          id_obat";
        $stmt_masuk = $pdo->prepare($sql_masuk);
        $stmt_masuk->execute([$tgl_awal_bulan, $tgl_akhir_bulan]);
        $data_masuk_raw = $stmt_masuk->fetchAll();
        foreach ($data_masuk_raw as $data) {
            $data_masuk_pivot[$data['id_obat']] = $data['total_masuk'];
        }

        // [F] Query 3: Ambil data STOK AWAL (Hanya GUDANG)
        $sql_stok_awal = "SELECT 
                              id_obat, 
                              SUM(masuk) - SUM(keluar) AS stok_awal 
                          FROM 
                              tbl_log_stok 
                          WHERE 
                              id_unit = 1 -- Hanya GUDANG
                              AND tgl_log < ?
                          GROUP BY 
                              id_obat";
        $stmt_stok_awal = $pdo->prepare($sql_stok_awal);
        $stmt_stok_awal->execute([$tgl_awal_bulan]);
        $data_stok_awal_raw = $stmt_stok_awal->fetchAll();
        foreach ($data_stok_awal_raw as $data) {
            $data_stok_awal_pivot[$data['id_obat']] = $data['stok_awal'];
        }
    }

} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}

// 6. Panggil Header & Sidebar
include '../templates/header.php';
// (Kita HAPUS 'include sidebar.php' ganda dari sini)
?>

<main class="content">
    <h2><?php echo htmlspecialchars($page_title); ?></h2>
    <p>Halaman ini menampilkan rekap stok bulanan (Gudang) dengan rincian pemakaian per poli. (Pengganti sheet BULANAN).</p>
    <hr>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Laporan</h6>
        </div>
        <div class="card-body">
            <form action="<?php echo BASE_URL; ?>laporan/bulanan.php" method="GET">
                <div class="form-row">
                    <div class="col-md-3">
                        <label for="bulan">Pilih Bulan & Tahun</label>
                        <input type="month" id="bulan" name="bulan" class="form-control" 
                               value="<?php echo htmlspecialchars($filter_bulan); ?>" required>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            Tampilkan Laporan
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
                Laporan Bulanan (Periode: <?php echo htmlspecialchars(date('F Y', strtotime($filter_bulan . '-01'))); ?>)
            </h6>
            <div>
                <button class="btn btn-success btn-sm">
                    <i class="fas fa-file-excel"></i> EXCEL
                </button>
                <button class="btn btn-danger btn-sm">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" style="width: 100%; min-width: 1500px;" cellspacing="0">
                    <thead class="thead-dark">
                        <tr>
                            <th rowspan="2" class="text-center align-middle">Kode Obat</th>
                            <th rowspan="2" class="text-center align-middle">Nama Obat</th>
                            <th rowspan="2" class="text-center align-middle">Stok Awal (Gudang)</th>
                            <th rowspan="2" class="text-center align-middle">Masuk (Gudang)</th>
                            
                            <th colspan="<?php echo count($polis_depan) + 1; ?>" class="text-center">Keluar (Pemakaian Resep per Poli)</th>
                            
                            <th rowspan="2" class="text-center align-middle">Stok Akhir (Gudang)</th>
                        </tr>
                        <tr class="thead-light">
                            <?php foreach ($polis_depan as $poli): ?>
                                <th class="text-center"><?php echo htmlspecialchars($poli['nama_poli']); ?></th>
                            <?php endforeach; ?>
                            <th class="text-center">Poli Belakang (UGD, dll)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($obat_list)): ?>
                            <tr>
                                <td colspan="<?php echo 4 + count($polis_depan) + 1 + 1; ?>" class="text-center">Silakan filter bulan terlebih dahulu.</td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            // Inisialisasi Grand Total
                            $grand_total_stok_awal = 0;
                            $grand_total_masuk = 0;
                            $grand_total_stok_akhir = 0;
                            $ada_data = false;
                            ?>
                            <?php foreach ($obat_list as $obat): ?>
                                <?php
                                $id_obat = $obat['id_obat'];
                                
                                // [LOGIKA PHP] Ambil data dari array pivot
                                $stok_awal = isset($data_stok_awal_pivot[$id_obat]) ? $data_stok_awal_pivot[$id_obat] : 0;
                                $masuk = isset($data_masuk_pivot[$id_obat]) ? $data_masuk_pivot[$id_obat] : 0;
                                
                                $total_keluar_per_obat = 0;
                                
                                // Hitung total keluar per obat (dari semua poli)
                                if (isset($data_keluar_pivot[$id_obat])) {
                                    $total_keluar_per_obat = array_sum($data_keluar_pivot[$id_obat]);
                                }
                                
                                // Hitung Stok Akhir
                                $stok_akhir = $stok_awal + $masuk - $total_keluar_per_obat;
                                
                                // Optimasi: Hanya tampilkan baris jika ada aktivitas
                                if ($stok_awal == 0 && $masuk == 0 && $total_keluar_per_obat == 0 && $stok_akhir == 0) {
                                    continue; // Lewati obat ini jika tidak ada transaksi
                                }

                                $ada_data = true; // Tandai bahwa kita punya data untuk ditampilkan
                                
                                // Update Grand Total
                                $grand_total_stok_awal += $stok_awal;
                                $grand_total_masuk += $masuk;
                                $grand_total_stok_akhir += $stok_akhir;
                                ?>
                                
                                <tr>
                                    <td><?php echo htmlspecialchars($obat['kode_obat']); ?></td>
                                    <td><?php echo htmlspecialchars($obat['nama_obat']); ?></td>
                                    <td class="text-right"><?php echo $stok_awal; ?></td>
                                    <td class="text-right"><?php echo $masuk; ?></td>
                                    
                                    <?php foreach ($polis_depan as $poli): ?>
                                        <?php 
                                        $id_poli = $poli['id_poli'];
                                        $keluar_per_poli = isset($data_keluar_pivot[$id_obat][$id_poli]) ? $data_keluar_pivot[$id_obat][$id_poli] : 0;
                                        // Update total footer per poli
                                        $total_stok_keluar_per_poli[$id_poli] += $keluar_per_poli;
                                        ?>
                                        <td class="text-right"><?php echo ($keluar_per_poli > 0) ? $keluar_per_poli : ''; ?></td>
                                    <?php endforeach; ?>
                                    
                                    <?php 
                                        $keluar_lainnya = isset($data_keluar_pivot[$id_obat]['lainnya']) ? $data_keluar_pivot[$id_obat]['lainnya'] : 0;
                                        $total_stok_keluar_per_poli['lainnya'] += $keluar_lainnya;
                                    ?>
                                    <td class="text-right table-secondary"><?php echo ($keluar_lainnya > 0) ? $keluar_lainnya : ''; ?></td>
                                    
                                    <td class="text-right font-weight-bold"><?php echo $stok_akhir; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (!$ada_data): // Jika loop selesai tapi tidak ada data (semua 0) ?>
                                <tr>
                                    <td colspan="<?php echo 4 + count($polis_depan) + 1 + 1; ?>" class="text-center">Tidak ada aktivitas stok pada periode ini.</td>
                                </tr>
                            <?php endif; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (isset($ada_data) && $ada_data): ?>
                    <tfoot class="thead-dark">
                         <tr>
                            <td colspan="2" class="text-right font-weight-bold">GRAND TOTAL</td>
                            <td class="text-right font-weight-bold"><?php echo $grand_total_stok_awal; ?></td>
                            <td class="text-right font-weight-bold"><?php echo $grand_total_masuk; ?></td>
                            
                            <?php foreach ($polis_depan as $poli): ?>
                                <td class="text-right font-weight-bold">
                                    <?php echo $total_stok_keluar_per_poli[$poli['id_poli']]; ?>
                                </td>
                            <?php endforeach; ?>
                            
                            <td class="text-right font-weight-bold"><?php echo $total_stok_keluar_per_poli['lainnya']; ?></td>
                            
                            <td class="text-right font-weight-bold"><?php echo $grand_total_stok_akhir; ?></td>
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