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
$page_title = "Kelola Data Obat";

// 5. [LOGIKA BACKEND UTAMA]
// --- TIDAK ADA YANG DIUBAH DARI SINI ---
try {
    // [A] Ambil data Kategori (untuk dropdown Form Tambah & Form Filter)
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

    // [B] Siapkan Variabel Filter & Search
    $filter_kategori = $_GET['kategori'] ?? '';
    $search_nama = $_GET['search'] ?? '';

    // [C] Siapkan Variabel Pagination
    $data_per_halaman = 20; // Sesuai permintaan Anda
    $halaman_saat_ini = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
    if ($halaman_saat_ini < 1) $halaman_saat_ini = 1;
    $offset = ($halaman_saat_ini - 1) * $data_per_halaman;

    // [D] Bangun Query Dinamis (Base Query)
    $sql_base = "FROM 
                    tbl_obat o
                LEFT JOIN 
                    tbl_kategori_obat k ON o.id_kategori_obat = k.id_kategori_obat
                LEFT JOIN
                    tbl_jenis_obat j ON k.id_jenis_obat = j.id_jenis_obat";
    
    $where_conditions = []; // Array untuk menampung kondisi WHERE
    $params = []; // Array untuk menampung parameter PDO

    // Tambahkan kondisi filter KATEGORI
    if (!empty($filter_kategori)) {
        $where_conditions[] = "o.id_kategori_obat = ?";
        $params[] = $filter_kategori;
    }

    // Tambahkan kondisi filter SEARCH NAMA
    if (!empty($search_nama)) {
        $where_conditions[] = "o.nama_obat LIKE ?";
        $params[] = "%" . $search_nama . "%";
    }

    // Gabungkan kondisi WHERE jika ada
    $sql_where = "";
    if (!empty($where_conditions)) {
        $sql_where = " WHERE " . implode(" AND ", $where_conditions);
    }

    // [E] Query untuk COUNT (Menghitung Total Data untuk Pagination)
    $sql_count = "SELECT COUNT(o.id_obat) " . $sql_base . $sql_where;
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_data = $stmt_count->fetchColumn();
    $total_halaman = ceil($total_data / $data_per_halaman);

    // [F] Query untuk SELECT DATA (Ambil data obat sesuai halaman)
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
    $stmt_obat_list->execute($params_data); // Pakai $params_data
    $obats = $stmt_obat_list->fetchAll();

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

    <div class="row">
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
                                <label for="kode_obat">Kode Obat</label>
                                <input type="text" class="form-control" id="kode_obat" name="kode_obat" placeholder="Cth: IFK-0001" required>
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
                            // Siapkan parameter URL agar filter tetap menempel
                            $query_params = [];
                            if (!empty($search_nama)) $query_params['search'] = $search_nama;
                            if (!empty($filter_kategori)) $query_params['kategori'] = $filter_kategori;
                            
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


<?php 
// 8. Panggil "Kaki" (Template Footer)
include '../templates/footer.php';
?>

<script>
// Script ini akan berjalan SETELAH halaman dan library jQuery di footer.php di-load
$(document).ready(function() {
    
    // Yey, jQuery ada. Mari kita buat modal 'Ubah' jadi canggih
    $('#editModal').on('show.bs.modal', function (event) {
        // Tombol yang memicu modal
        var button = $(event.relatedTarget); 
        
        // Ambil data dari atribut 'data-*' tombol
        var id = button.data('id');
        var kode = button.data('kode');
        var nama = button.data('nama');
        var satuan = button.data('satuan');
        var id_kategori = button.data('id_kategori');
        
        // Dapatkan objek modal
        var modal = $(this);
        
        // "Suntikkan" data ke dalam form di modal
        modal.find('.modal-title').text('Ubah Data Obat #' + id);
        modal.find('#edit_id_obat').val(id);
        modal.find('#edit_kode_obat').val(kode);
        modal.find('#edit_nama_obat').val(nama);
        modal.find('#edit_satuan').val(satuan);
        modal.find('#edit_id_kategori_obat').val(id_kategori);
    });

});
</script>