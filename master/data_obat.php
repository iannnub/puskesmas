<?php
require_once '../config.php';

require_once '../templates/auth_check.php';

if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2 ) {
    $_SESSION['flash_error'] = 'Akses Ditolak! Anda bukan Super Admin atau Admin.'; header("Location: " . BASE_URL . "dashboard.php"); exit;
    exit;
}

$page_title = "Kelola Data Obat & Dokter";

try {

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

    $filter_kategori = $_GET['kategori'] ?? '';
    $search_nama = $_GET['search'] ?? '';

    $data_per_halaman = 20; 
    $halaman_saat_ini = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
    if ($halaman_saat_ini < 1) $halaman_saat_ini = 1;
    $offset = ($halaman_saat_ini - 1) * $data_per_halaman;

    $sql_base = "FROM 
                    tbl_obat o
                LEFT JOIN 
                    tbl_kategori_obat k ON o.id_kategori_obat = k.id_kategori_obat
                LEFT JOIN
                    tbl_jenis_obat j ON k.id_jenis_obat = j.id_jenis_obat";
    
    $where_conditions = []; 
    $params = []; 


    if (!empty($filter_kategori)) {
        $where_conditions[] = "o.id_kategori_obat = ?";
        $params[] = $filter_kategori;
    }


    if (!empty($search_nama)) {
        $where_conditions[] = "o.nama_obat LIKE ?";
        $params[] = "%" . $search_nama . "%";
    }

    $sql_where = "";
    if (!empty($where_conditions)) {
        $sql_where = " WHERE " . implode(" AND ", $where_conditions);
    }

    $sql_count = "SELECT COUNT(o.id_obat) " . $sql_base . $sql_where;
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_data = $stmt_count->fetchColumn();
    $total_halaman = ceil($total_data / $data_per_halaman);

    $sql_obat = "SELECT 
                    o.id_obat, o.kode_obat, o.nama_obat, o.satuan, o.id_kategori_obat,
                    k.nama_kategori, j.nama_jenis_obat
                " . $sql_base . $sql_where . "
                ORDER BY 
                    o.id_obat ASC"; 
    
    $sql_obat .= " LIMIT ? OFFSET ?";
    
    $params_data = $params; 
    $params_data[] = $data_per_halaman;
    $params_data[] = $offset;

    $stmt_obat_list = $pdo->prepare($sql_obat);
    $stmt_obat_list->execute($params_data); 
    $obats = $stmt_obat_list->fetchAll();

    // Ambil data dokter
    $stmt_dokter = $pdo->query("SELECT id_dokter, nama_dokter FROM tbl_dokter ORDER BY id_dokter DESC");
    $dokters = $stmt_dokter->fetchAll();

    // ============================================================
    // Ambil kode terakhir IFK dan BLUD dari database
    // Format kode: IFK-XXXX atau BLUD-XXXX
    // ============================================================
    $sql_last_ifk = "SELECT o.kode_obat 
                     FROM tbl_obat o
                     JOIN tbl_kategori_obat k ON o.id_kategori_obat = k.id_kategori_obat
                     JOIN tbl_jenis_obat j ON k.id_jenis_obat = j.id_jenis_obat
                     WHERE j.nama_jenis_obat = 'IFK' AND o.kode_obat LIKE 'IFK-%'
                     ORDER BY CAST(SUBSTRING_INDEX(o.kode_obat, '-', -1) AS UNSIGNED) DESC
                     LIMIT 1";
    $last_ifk_row = $pdo->query($sql_last_ifk)->fetch();
    $last_ifk_kode = $last_ifk_row ? $last_ifk_row['kode_obat'] : null;
    $next_ifk_num  = $last_ifk_row ? (int)preg_replace('/\D/', '', explode('-', $last_ifk_row['kode_obat'])[1] ?? '0') + 1 : 1;
    $next_ifk_kode = 'IFK-' . str_pad($next_ifk_num, 4, '0', STR_PAD_LEFT);

    $sql_last_blud = "SELECT o.kode_obat 
                      FROM tbl_obat o
                      JOIN tbl_kategori_obat k ON o.id_kategori_obat = k.id_kategori_obat
                      JOIN tbl_jenis_obat j ON k.id_jenis_obat = j.id_jenis_obat
                      WHERE j.nama_jenis_obat = 'BLUD' AND o.kode_obat LIKE 'BLUD-%'
                      ORDER BY CAST(SUBSTRING_INDEX(o.kode_obat, '-', -1) AS UNSIGNED) DESC
                      LIMIT 1";
    $last_blud_row = $pdo->query($sql_last_blud)->fetch();
    $last_blud_kode = $last_blud_row ? $last_blud_row['kode_obat'] : null;
    $next_blud_num  = $last_blud_row ? (int)preg_replace('/\D/', '', explode('-', $last_blud_row['kode_obat'])[1] ?? '0') + 1 : 1;
    $next_blud_kode = 'BLUD-' . str_pad($next_blud_num, 4, '0', STR_PAD_LEFT);

} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}

include '../templates/header.php';

?>

<div class="container-fluid">

    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>

    <div class="row">

        <!-- ============================================ -->
        <!-- SECTION: KELOLA DATA OBAT - FORM TAMBAH     -->
        <!-- ============================================ -->
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-plus-circle"></i> Tambah Obat Baru
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (isset($_GET['status'])): ?>
                        <?php if ($_GET['status'] == 'tambah_sukses'): ?>
                            <div class="alert alert-success" role="alert">
                                Obat baru berhasil ditambahkan!
                            </div>
                        <?php elseif ($_GET['status'] == 'gagal'): ?>
                            <div class="alert alert-danger" role="alert">
                                <strong>Operasi Gagal!</strong> <?php echo isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : 'Silakan coba lagi.'; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>master/proses_crud_obat.php" method="POST">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="form-group">
                            <label for="id_kategori_obat">Kategori Obat</label>
                            <select id="id_kategori_obat" name="id_kategori_obat" class="form-control" required>
                                <option value="">-- Pilih Kategori (IFK / BLUD) --</option>
                                <?php foreach ($kategoris as $kategori): ?>
                                    <option value="<?php echo $kategori['id_kategori_obat']; ?>">
                                        <?php echo htmlspecialchars($kategori['nama_jenis_obat'] . ' - ' . $kategori['nama_kategori']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="kode_obat">Pilih Kode Obat</label>
                                
                                <!-- Info kode terakhir & tombol klik otomatis -->
                                <div class="mb-2 d-flex flex-wrap gap-1" style="gap: 6px;">
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-primary font-weight-bold"
                                            id="btn_ifk_next"
                                            onclick="isiKodeObat('ifk')"
                                            title="Klik untuk mengisi kode IFK berikutnya">
                                        <i class="fas fa-tag mr-1"></i>
                                        IFK &rarr; <span id="label_ifk_next"><?php echo $next_ifk_kode; ?></span>
                                    </button>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-success font-weight-bold"
                                            id="btn_blud_next"
                                            onclick="isiKodeObat('blud')"
                                            title="Klik untuk mengisi kode BLUD berikutnya">
                                        <i class="fas fa-tag mr-1"></i>
                                        BLUD &rarr; <span id="label_blud_next"><?php echo $next_blud_kode; ?></span>
                                    </button>
                                </div>
                                
                                <input type="text" class="form-control" id="kode_obat" name="kode_obat" 
                                       placeholder="Klik tombol di atas atau ketik manual (Cth: IFK-0001)" required>
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Klik salah satu tombol di atas untuk mengisi kode secara otomatis dan berurutan.
                                </small>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="satuan">Satuan</label>
                                <input type="text" class="form-control" id="satuan" name="satuan" placeholder="Cth: tube, box, btl" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="nama_obat">Nama Obat</label>
                            <input type="text" class="form-control" id="nama_obat" name="nama_obat" placeholder="Cth: Acyclovir 5% krim 5 gr" required>
                        </div>
                        
                        <hr>
                        <button type="submit" class="btn btn-success btn-icon-split">
                            <span class="icon text-white-50">
                                <i class="fas fa-save"></i>
                            </span>
                            <span class="text">Simpan Obat Baru</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- SECTION: KELOLA DATA DOKTER - FORM TAMBAH   -->
        <!-- ============================================ -->
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-plus-circle"></i> Tambah Dokter Baru
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (isset($_GET['status_dokter'])): ?>
                        <?php if ($_GET['status_dokter'] == 'tambah_sukses'): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                Data dokter berhasil disimpan!
                                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                            </div>
                        <?php elseif ($_GET['status_dokter'] == 'gagal'): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <strong>Gagal!</strong> <?= isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : 'Silakan coba lagi.'; ?>
                                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>master/proses_tambah_dokter.php" method="POST">
                        <input type="hidden" name="action" value="create">
                        <div class="form-group">
                            <label for="nama_dokter_baru">Nama Lengkap Dokter</label>
                            <input type="text" class="form-control" id="nama_dokter_baru" name="nama_dokter"
                                   placeholder="Cth: dr. Andi Kurniawan" required>
                        </div>
                        <hr>
                        <button type="submit" class="btn btn-success btn-icon-split">
                            <span class="icon text-white-50"><i class="fas fa-save"></i></span>
                            <span class="text">Simpan Dokter Baru</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- SECTION: TABEL DAFTAR DOKTER                -->
        <!-- ============================================ -->
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list"></i> Daftar Semua Dokter (Total: <?= count($dokters); ?>)
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" width="100%">
                            <thead class="thead-dark">
                                <tr>
                                    <th width="10%" class="text-center">No</th>
                                    <th>Nama Dokter</th>
                                    <th width="15%" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dokters)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center">Belum ada data dokter.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no_dok = 1; foreach ($dokters as $row): ?>
                                    <tr>
                                        <td class="text-center"><?= $no_dok++; ?></td>
                                        <td><?= htmlspecialchars($row['nama_dokter']); ?></td>
                                        <td class="text-center">
                                            <button class="btn btn-warning btn-sm"
                                                    data-toggle="modal"
                                                    data-target="#editDokterModal"
                                                    data-id="<?= $row['id_dokter']; ?>"
                                                    data-nama="<?= htmlspecialchars($row['nama_dokter']); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="<?php echo BASE_URL; ?>master/proses_tambah_dokter.php?action=delete&id=<?= $row['id_dokter']; ?>"
                                               onclick="return confirm('Hapus dokter \'<?= htmlspecialchars($row['nama_dokter']); ?>\'?');"
                                               class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash"></i>
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
        </div>

        <!-- ============================================ -->
        <!-- SECTION: TABEL DAFTAR OBAT                  -->
        <!-- ============================================ -->
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list"></i> Daftar Obat (Total: <?php echo $total_data; ?>)
                    </h6>
                </div>
                <div class="card-body">
                    <form action="<?php echo BASE_URL; ?>master/data_obat.php" method="GET" class="mb-3">
                        <div class="form-row">
                            <div class="col-md-5">
                                <input type="text" name="search" class="form-control" placeholder="Cari nama obat..." value="<?php echo htmlspecialchars($search_nama); ?>">
                            </div>
                            <div class="col-md-5">
                                <select name="kategori" class="form-control">
                                    <option value="">-- Semua Kategori --</option>
                                    <?php foreach ($kategoris as $kategori): ?>
                                        <option value="<?php echo $kategori['id_kategori_obat']; ?>" 
                                            <?php echo ($filter_kategori == $kategori['id_kategori_obat']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($kategori['nama_jenis_obat'] . ' - ' . $kategori['nama_kategori']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex">
                                <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-search"></i></button>
                                <a href="<?php echo BASE_URL; ?>master/data_obat.php" class="btn btn-secondary btn-sm"><i class="fas fa-sync-alt"></i></a>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                            <thead class="thead-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Kode</th>
                                    <th>Nama Obat</th>
                                    <th>Kategori</th>
                                    <th>Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($obats)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">
                                            <?php echo (empty($filter_kategori) && empty($search_nama)) ? 'Belum ada data obat.' : 'Data obat tidak ditemukan.'; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($obats as $obat): ?>
                                        <tr>
                                            <td><?php echo $obat['id_obat']; ?></td>
                                            <td><?php echo htmlspecialchars($obat['kode_obat']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($obat['nama_obat']); ?>
                                                <small class="d-block text-muted">Satuan: <?php echo htmlspecialchars($obat['satuan']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars(isset($obat['nama_kategori']) ? $obat['nama_kategori'] : 'N/A'); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-warning btn-sm" 
                                                        data-toggle="modal" 
                                                        data-target="#editModal"
                                                        data-id="<?php echo $obat['id_obat']; ?>"
                                                        data-kode="<?php echo htmlspecialchars($obat['kode_obat']); ?>"
                                                        data-nama="<?php echo htmlspecialchars($obat['nama_obat']); ?>"
                                                        data-satuan="<?php echo htmlspecialchars($obat['satuan']); ?>"
                                                        data-id_kategori="<?php echo $obat['id_kategori_obat']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <a href="<?php echo BASE_URL; ?>master/proses_crud_obat.php?action=delete&id=<?php echo $obat['id_obat']; ?>" 
                                                   onclick="return confirm('PERINGATAN! Menghapus obat ini bisa GAGAL jika sudah terpakai di data transaksi.\n\nLanjutkan menghapus (<?php echo htmlspecialchars($obat['nama_obat']); ?>)?');" 
                                                   class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <nav aria-label="Navigasi Halaman" class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php
                            $query_params = [];
                            if (!empty($search_nama)) $query_params['search'] = $search_nama;
                            if (!empty($filter_kategori)) $query_params['kategori'] = $filter_kategori;
                            
                            if ($halaman_saat_ini > 1) {
                                $query_params['halaman'] = $halaman_saat_ini - 1;
                                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($query_params) . '">&laquo; Sebelumnya</a></li>';
                            } else {
                                echo '<li class="page-item disabled"><span class="page-link">&laquo; Sebelumnya</span></li>';
                            }

                            echo '<li class="page-item active" aria-current="page"><span class="page-link">Halaman ' . $halaman_saat_ini . ' dari ' . $total_halaman . '</span></li>';

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

<!-- ============================================ -->
<!-- MODAL EDIT OBAT                             -->
<!-- ============================================ -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Ubah Data Obat</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="<?php echo BASE_URL; ?>master/proses_crud_obat.php" method="POST" id="editForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id_obat" id="edit_id_obat">
                    
                    <div class="form-group">
                        <label for="edit_id_kategori_obat">Kategori Obat</label>
                        <select id="edit_id_kategori_obat" name="id_kategori_obat" class="form-control" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($kategoris as $kategori): ?>
                                <option value="<?php echo $kategori['id_kategori_obat']; ?>">
                                    <?php echo htmlspecialchars($kategori['nama_jenis_obat'] . ' - ' . $kategori['nama_kategori']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="edit_kode_obat">Kode Obat</label>
                            <input type="text" class="form-control" id="edit_kode_obat" name="kode_obat" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="edit_satuan">Satuan</label>
                            <input type="text" class="form-control" id="edit_satuan" name="satuan" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit_nama_obat">Nama Obat</label>
                        <input type="text" class="form-control" id="edit_nama_obat" name="nama_obat" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-dismiss="modal">Batal</button>
                <button class="btn btn-primary" type="submit" form="editForm">Simpan Perubahan</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL EDIT DOKTER                           -->
<!-- ============================================ -->
<div class="modal fade" id="editDokterModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Ubah Data Dokter</h5>
                <button class="close" type="button" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="<?php echo BASE_URL; ?>master/proses_tambah_dokter.php" method="POST" id="editDokterForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id_dokter" id="edit_id_dokter">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Lengkap Dokter</label>
                        <input type="text" class="form-control" name="nama_dokter" id="edit_nama_dokter" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Batal</button>
                    <button class="btn btn-warning" type="submit">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php ob_start(); ?>
<script>
// ============================================================
// Data kode terakhir dari PHP (di-pass ke JavaScript)
// ============================================================
var kodeData = {
    ifk: {
        last: <?php echo json_encode($last_ifk_kode); ?>,
        next: <?php echo json_encode($next_ifk_kode); ?>,
        nextNum: <?php echo $next_ifk_num; ?>
    },
    blud: {
        last: <?php echo json_encode($last_blud_kode); ?>,
        next: <?php echo json_encode($next_blud_kode); ?>,
        nextNum: <?php echo $next_blud_num; ?>
    }
};

/**
 * Isi field kode_obat dengan kode berikutnya (IFK atau BLUD)
 * dan update tampilan label tombol secara lokal (tanpa reload).
 */
function isiKodeObat(jenis) {
    var kodeField = document.getElementById('kode_obat');
    var prefix, data, btnId, labelId;

    if (jenis === 'ifk') {
        prefix  = 'IFK';
        data    = kodeData.ifk;
        btnId   = 'btn_ifk_next';
        labelId = 'label_ifk_next';
    } else {
        prefix  = 'BLUD';
        data    = kodeData.blud;
        btnId   = 'btn_blud_next';
        labelId = 'label_blud_next';
    }

    // Isi field dengan kode berikutnya
    kodeField.value = data.next;
    kodeField.focus();

    // Highlight tombol yang dipilih
    document.getElementById('btn_ifk_next').classList.remove('btn-primary');
    document.getElementById('btn_ifk_next').classList.add('btn-outline-primary');
    document.getElementById('btn_blud_next').classList.remove('btn-success');
    document.getElementById('btn_blud_next').classList.add('btn-outline-success');

    if (jenis === 'ifk') {
        document.getElementById(btnId).classList.remove('btn-outline-primary');
        document.getElementById(btnId).classList.add('btn-primary');
    } else {
        document.getElementById(btnId).classList.remove('btn-outline-success');
        document.getElementById(btnId).classList.add('btn-success');
    }
}

$(document).ready(function() {

    // Modal Edit Obat
    $('#editModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id         = button.data('id');
        var kode       = button.data('kode');
        var nama       = button.data('nama');
        var satuan     = button.data('satuan');
        var id_kategori = button.data('id_kategori');
        
        var modal = $(this);
        modal.find('.modal-title').text('Ubah Data Obat #' + id);
        modal.find('#edit_id_obat').val(id);
        modal.find('#edit_kode_obat').val(kode);
        modal.find('#edit_nama_obat').val(nama);
        modal.find('#edit_satuan').val(satuan);
        modal.find('#edit_id_kategori_obat').val(id_kategori);
    });

    // Modal Edit Dokter
    $('#editDokterModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id   = button.data('id');
        var nama = button.data('nama');
        var modal = $(this);
        modal.find('.modal-title').text('Ubah Data Dokter #' + id);
        modal.find('#edit_id_dokter').val(id);
        modal.find('#edit_nama_dokter').val(nama);
    });

});
</script>
<?php
$extra_scripts = ob_get_clean();
include '../templates/footer.php';
?>