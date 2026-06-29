<?php

require_once '../config.php';

require_once '../templates/auth_check.php';

if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2 && $_SESSION['role_id'] != 5) {
    $_SESSION['flash_error'] = 'Akses Ditolak! Anda bukan Super Admin atau Admin.'; header("Location: " . BASE_URL . "dashboard.php"); exit;
    exit;
}

$page_title = "Laporan Rekap Sasaran Mutu";

// ---------------------------------------------------------------
// AMBIL DAFTAR SEMUA POLI (untuk dropdown)
// ---------------------------------------------------------------
$semua_poli = [];
try {
    $stmt_all_poli = $pdo->query("SELECT id_poli, nama_poli FROM tbl_poli ORDER BY id_poli ASC");
    $semua_poli = $stmt_all_poli->fetchAll();
} catch (PDOException $e) {
    die("Error mengambil daftar poli: " . $e->getMessage());
}

// ---------------------------------------------------------------
// TENTUKAN POLI YANG DIPILIH
// ---------------------------------------------------------------
$id_poli_dipilih  = isset($_GET['id_poli']) && $_GET['id_poli'] !== '' ? (int)$_GET['id_poli'] : null;
$nama_poli_dipilih = 'Semua Poli';

if ($id_poli_dipilih) {
    foreach ($semua_poli as $p) {
        if ($p['id_poli'] == $id_poli_dipilih) {
            $nama_poli_dipilih = $p['nama_poli'];
            break;
        }
    }
}

// ---------------------------------------------------------------
// FILTER BULAN
// ---------------------------------------------------------------
$laporan_sm = [];
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');

try {

    if (isset($_GET['bulan'])) {

        // Bangun kondisi WHERE poli (opsional)
        $where_poli_header = $id_poli_dipilih ? "AND rh.id_poli = ?" : "";
        $where_poli_detail = $id_poli_dipilih ? "AND rh.id_poli = ?" : "";

        $sql_header = "SELECT 
                            DATE(rh.tgl_resep) AS tanggal,
                            COUNT(rh.id_resep) AS total_resep,
                            SUM(CASE WHEN rh.kelengkapan_resep = 'Lengkap' THEN 1 ELSE 0 END) AS total_lengkap,
                            SUM(CASE WHEN rh.kelengkapan_resep = 'Tidak Lengkap' THEN 1 ELSE 0 END) AS total_tidak_lengkap,
                            SUM(CASE WHEN rh.kesalahan_resep = 'Ada' THEN 1 ELSE 0 END) AS total_ada_kesalahan,
                            SUM(CASE WHEN rh.kesalahan_resep = 'Tidak Ada' THEN 1 ELSE 0 END) AS total_tidak_ada_kesalahan,
                            SUM(CASE WHEN rh.sesuai_formularium = 'Sesuai' THEN 1 ELSE 0 END) AS total_sesuai,
                            SUM(CASE WHEN rh.sesuai_formularium = 'Tidak Sesuai' THEN 1 ELSE 0 END) AS total_tidak_sesuai
                        FROM 
                            tbl_resep_header rh
                        WHERE 
                            DATE_FORMAT(rh.tgl_resep, '%Y-%m') = ?
                            $where_poli_header
                        GROUP BY 
                            DATE(rh.tgl_resep)
                        ORDER BY 
                            tanggal ASC";

        $params_header = [$filter_bulan];
        if ($id_poli_dipilih) $params_header[] = $id_poli_dipilih;

        $stmt_header = $pdo->prepare($sql_header);
        $stmt_header->execute($params_header);
        $data_header = $stmt_header->fetchAll(PDO::FETCH_ASSOC);

        $sql_detail = "SELECT
                            DATE(rh.tgl_resep) AS tanggal,
                            SUM(CASE WHEN rd.jenis_racikan = 'Racikan' THEN 1 ELSE 0 END) AS total_racikan,
                            SUM(CASE WHEN rd.jenis_racikan = 'Non Racikan' THEN 1 ELSE 0 END) AS total_non_racikan
                       FROM
                            tbl_resep_detail rd
                       JOIN
                            tbl_resep_header rh ON rd.id_resep = rh.id_resep
                       WHERE 
                            DATE_FORMAT(rh.tgl_resep, '%Y-%m') = ?
                            $where_poli_detail
                       GROUP BY
                            DATE(rh.tgl_resep)";

        $params_detail = [$filter_bulan];
        if ($id_poli_dipilih) $params_detail[] = $id_poli_dipilih;

        $stmt_detail = $pdo->prepare($sql_detail);
        $stmt_detail->execute($params_detail);
        $data_detail_raw = $stmt_detail->fetchAll(PDO::FETCH_ASSOC);

        $data_detail_pivot = [];
        foreach ($data_detail_raw as $data) {
            $data_detail_pivot[$data['tanggal']] = $data;
        }

        foreach ($data_header as $data_h) {
            $tanggal = $data_h['tanggal'];
            $data_racikan = isset($data_detail_pivot[$tanggal]) ? $data_detail_pivot[$tanggal] : ['total_racikan' => 0, 'total_non_racikan' => 0];
            $laporan_sm[] = array_merge($data_h, $data_racikan);
        }
    }

} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}

include '../templates/header.php';


function hitungPersen($nilai, $total) {
    if ($total == 0) {
        return 0;
    }
    return ($nilai / $total) * 100;
}
?>

<main class="content">
    <h2><?php echo htmlspecialchars($page_title); ?></h2>
    <p>Halaman ini menampilkan rekap kualitas pelayanan resep harian. (Pengganti sheet SM).</p>
    <hr>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Laporan</h6>
        </div>
        <div class="card-body">
            <form action="<?php echo BASE_URL; ?>laporan/sasaran_mutu.php" method="GET">
                <div class="form-row align-items-end">
                    <!-- Dropdown Pilih Poli -->
                    <div class="col-md-4">
                        <label for="id_poli">
                            <i class="fas fa-clinic-medical"></i> Pilih Poli
                        </label>
                        <select id="id_poli" name="id_poli" class="form-control">
                            <option value="">-- Semua Poli --</option>
                            <?php foreach ($semua_poli as $poli): ?>
                                <option value="<?php echo $poli['id_poli']; ?>"
                                    <?php echo ($poli['id_poli'] == $id_poli_dipilih) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($poli['nama_poli']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="bulan"><i class="fas fa-calendar-alt"></i> Pilih Bulan &amp; Tahun</label>
                        <input type="month" id="bulan" name="bulan" class="form-control" 
                               value="<?php echo htmlspecialchars($filter_bulan); ?>" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-search"></i> Tampilkan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($_GET['bulan'])):  ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                Rekap Sasaran Mutu &mdash; <?php echo htmlspecialchars($nama_poli_dipilih); ?>
                &nbsp;|&nbsp; Periode: <?php echo htmlspecialchars(date('F Y', strtotime($filter_bulan . '-01'))); ?>
            </h6>
           <div>
                <a href="export_excel.php?type=sm&bulan=<?= urlencode($filter_bulan); ?>&id_poli=<?= $id_poli_dipilih; ?>" class="btn btn-success btn-sm shadow-sm">
                    <i class="fas fa-file-excel"></i> EXCEL
                </a>
                
                <a href="export_pdf.php?type=sm&bulan=<?= urlencode($filter_bulan); ?>&id_poli=<?= $id_poli_dipilih; ?>" target="_blank" class="btn btn-danger btn-sm shadow-sm">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" style="width: 100%; min-width: 1200px;" cellspacing="0">
                    <thead class="thead-dark">
                        <tr>
                            <th rowspan="2" class="text-center align-middle">No</th>
                            <th rowspan="2" class="text-center align-middle">Tanggal</th>
                            <th colspan="3" class="text-center">Kelengkapan Resep</th>
                            <th colspan="3" class="text-center">Kesalahan Resep</th>
                            <th colspan="3" class="text-center">Sesuai Formularium</th>
                            <th colspan="3" class="text-center">Racikan / Non Racikan (per Item Obat)</th>
                        </tr>
                        <tr class="thead-light">
                            <th class="text-center">Lengkap</th>
                            <th class="text-center">Tidak Lengkap</th>
                            <th class="text-center">% Lengkap</th>
                            
                            <th class="text-center">Ada</th>
                            <th class="text-center">Tidak Ada</th>
                            <th class="text-center">% Tidak Ada</th>
                            
                            <th class="text-center">Sesuai</th>
                            <th class="text-center">Tidak Sesuai</th>
                            <th class="text-center">% Sesuai</th>
                            
                            <th class="text-center">Racikan</th>
                            <th class="text-center">Non Racikan</th>
                            <th class="text-center">% Non Racikan</th>
                        </tr>
                    </thead>
                    <tbody>
                        
                        <?php 
                        $nomor = 1;
           
                        $gt_lengkap = 0; $gt_tidak_lengkap = 0;
                        $gt_ada_salah = 0; $gt_tidak_salah = 0;
                        $gt_sesuai = 0; $gt_tidak_sesuai = 0;
                        $gt_racikan = 0; $gt_non_racikan = 0;
                        $gt_total_resep = 0; $gt_total_item = 0;
                        ?>

                        <?php if (empty($laporan_sm)): ?>
                            <tr>
                                <td colspan="14" class="text-center">Tidak ada data sasaran mutu untuk <strong><?php echo htmlspecialchars($nama_poli_dipilih); ?></strong> pada periode <?php echo htmlspecialchars(date('F Y', strtotime($filter_bulan . '-01'))); ?>.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($laporan_sm as $data): ?>
                                <?php
                            
                                $total_resep_hari = $data['total_resep'];
                                $total_item_hari = $data['total_racikan'] + $data['total_non_racikan'];
                                
                                $persen_lengkap = hitungPersen($data['total_lengkap'], $total_resep_hari);
                                $persen_tidak_salah = hitungPersen($data['total_tidak_ada_kesalahan'], $total_resep_hari);
                                $persen_sesuai = hitungPersen($data['total_sesuai'], $total_resep_hari);
                                $persen_non_racikan = hitungPersen($data['total_non_racikan'], $total_item_hari);
               
                                $gt_lengkap += $data['total_lengkap']; $gt_tidak_lengkap += $data['total_tidak_lengkap'];
                                $gt_ada_salah += $data['total_ada_kesalahan']; $gt_tidak_salah += $data['total_tidak_ada_kesalahan'];
                                $gt_sesuai += $data['total_sesuai']; $gt_tidak_sesuai += $data['total_tidak_sesuai'];
                                $gt_racikan += $data['total_racikan']; $gt_non_racikan += $data['total_non_racikan'];
                                $gt_total_resep += $total_resep_hari;
                                $gt_total_item += $total_item_hari;
                                ?>
                                <tr>
                                    <td><?php echo $nomor++; ?></td>
                                    <td><?php echo htmlspecialchars(date('d-m-Y', strtotime($data['tanggal']))); ?></td>
                                    
                                    <td class="text-right"><?php echo $data['total_lengkap']; ?></td>
                                    <td class="text-right"><?php echo $data['total_tidak_lengkap']; ?></td>
                                    <td class="text-right table-secondary"><?php echo number_format($persen_lengkap, 2); ?>%</td>
                                    
                                    <td class="text-right"><?php echo $data['total_ada_kesalahan']; ?></td>
                                    <td class="text-right"><?php echo $data['total_tidak_ada_kesalahan']; ?></td>
                                    <td class="text-right table-secondary"><?php echo number_format($persen_tidak_salah, 2); ?>%</td>
                                    
                                    <td class="text-right"><?php echo $data['total_sesuai']; ?></td>
                                    <td class="text-right"><?php echo $data['total_tidak_sesuai']; ?></td>
                                    <td class="text-right table-secondary"><?php echo number_format($persen_sesuai, 2); ?>%</td>
                                    
                                    <td class="text-right"><?php echo $data['total_racikan']; ?></td>
                                    <td class="text-right"><?php echo $data['total_non_racikan']; ?></td>
                                    <td class="text-right table-secondary"><?php echo number_format($persen_non_racikan, 2); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    
                    <?php if ($gt_total_resep > 0 || $gt_total_item > 0): ?>
                    <tfoot class="thead-dark">
                        <tr style="font-weight: bold;">
                            <td colspan="2" class="text-right">GRAND TOTAL</td>
                            <td class="text-right"><?php echo $gt_lengkap; ?></td>
                            <td class="text-right"><?php echo $gt_tidak_lengkap; ?></td>
                            <td class="text-right"><?php echo number_format(hitungPersen($gt_lengkap, $gt_total_resep), 2); ?>%</td>
                            
                            <td class="text-right"><?php echo $gt_ada_salah; ?></td>
                            <td class="text-right"><?php echo $gt_tidak_salah; ?></td>
                            <td class="text-right"><?php echo number_format(hitungPersen($gt_tidak_salah, $gt_total_resep), 2); ?>%</td>
                            
                            <td class="text-right"><?php echo $gt_sesuai; ?></td>
                            <td class="text-right"><?php echo $gt_tidak_sesuai; ?></td>
                            <td class="text-right"><?php echo number_format(hitungPersen($gt_sesuai, $gt_total_resep), 2); ?>%</td>
                            
                            <td class="text-right"><?php echo $gt_racikan; ?></td>
                            <td class="text-right"><?php echo $gt_non_racikan; ?></td>
                            <td class="text-right"><?php echo number_format(hitungPersen($gt_non_racikan, $gt_total_item), 2); ?>%</td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    <?php endif;  ?>

</main>
<?php 
include '../templates/footer.php'; 
?>