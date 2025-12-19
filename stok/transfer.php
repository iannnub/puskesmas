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
$page_title = "Transfer Stok Internal";

// [UPGRADE] Ambil ID baru dari URL (untuk highlight)
$new_id = $_GET['new_id'] ?? null;

// 5. [LOGIKA BACKEND UTAMA]
// --- TIDAK ADA YANG DIUBAH DARI SINI ---
try {
    // 🔹 Ambil semua obat (untuk dropdown) - Urut ID
    $stmt_obat = $pdo->query("SELECT id_obat, kode_obat, nama_obat FROM tbl_obat ORDER BY id_obat ASC");
    $obats = $stmt_obat->fetchAll();

    // 🔹 Ambil semua unit (untuk 2 dropdown: Asal & Tujuan)
    $stmt_unit = $pdo->query("SELECT id_unit, nama_unit FROM tbl_unit ORDER BY nama_unit ASC");
    $units = $stmt_unit->fetchAll();

    // 🔹 Ambil 20 data transfer terakhir
    $sql_riwayat = "SELECT 
                        t.id_transfer, t.tgl_transfer, o.nama_obat, t.jumlah,
                        unit_asal.nama_unit AS nama_unit_asal,
                        unit_tujuan.nama_unit AS nama_unit_tujuan,
                        usr.username AS pencatat
                    FROM 
                        tbl_transaksi_transfer t
                    JOIN 
                        tbl_obat o ON t.id_obat = o.id_obat
                    JOIN 
                        tbl_unit unit_asal ON t.id_unit_asal = unit_asal.id_unit
                    JOIN 
                        tbl_unit unit_tujuan ON t.id_unit_tujuan = unit_tujuan.id_unit
                    JOIN
                        tbl_user usr ON t.id_user_pencatat = usr.id_user
                    ORDER BY 
                        t.id_transfer DESC
                    LIMIT 20";
    $stmt_riwayat = $pdo->query($sql_riwayat);
    $riwayat_transfer = $stmt_riwayat->fetchAll();

} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}
// --- SAMPAI SINI LOGIC BACKEND AMAN ---

// 6. Panggil Header & Sidebar
include '../templates/header.php';
// Panggil Sidebar (jika terpisah)
// include '../templates/sidebar.php';
?>

<div class="container-fluid">

    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    <p class="mb-4">Gunakan form ini untuk memindahkan stok antar unit (cth: dari GUDANG ke APOTEK, atau APOTEK ke UGD).</p>

    <div class="row">

        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-exchange-alt"></i> Input Transfer Stok Baru</h6>
                </div>
                <div class="card-body">
                    <?php if (isset($_GET['status'])): ?>
                        <?php if ($_GET['status'] == 'tambah_sukses'): ?>
                            <div class="alert alert-success" role="alert">
                                Transfer stok berhasil dicatat! Stok di kedua unit telah diupdate.
                            </div>
                        <?php elseif ($_GET['status'] == 'gagal'): ?>
                            <div class="alert alert-danger" role="alert">
                                <strong>Operasi Gagal!</strong> <?php echo isset($_GET['msg']) ? htmlspecialchars(urldecode($_GET['msg'])) : 'Silakan coba lagi.'; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>stok/proses_transfer.php" method="POST" id="formTransfer">
                        <input type="hidden" name="action" value="create">

                        <div class="form-group">
                            <label for="id_obat">Nama Obat</label>
                            <select id="id_obat" name="id_obat" class="form-control select2-obat" required>
                                <option value="">-- Pilih / Cari Obat --</option>
                                <?php foreach ($obats as $obat): ?>
                                    <option value="<?php echo $obat['id_obat']; ?>">
                                        <?php echo htmlspecialchars($obat['kode_obat'] . ' - ' . $obat['nama_obat']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="id_unit_asal">Dari Unit (Asal)</label>
                                <select id="id_unit_asal" name="id_unit_asal" class="form-control select2-unit" required>
                                    <option value="">-- Pilih Unit Asal --</option>
                                    <?php foreach ($units as $unit): ?>
                                        <option value="<?php echo $unit['id_unit']; ?>"><?php echo htmlspecialchars($unit['nama_unit']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="id_unit_tujuan">Ke Unit (Tujuan)</label>
                                <select id="id_unit_tujuan" name="id_unit_tujuan" class="form-control select2-unit" required>
                                    <option value="">-- Pilih Unit Tujuan --</option>
                                    <?php foreach ($units as $unit): ?>
                                        <option value="<?php echo $unit['id_unit']; ?>"><?php echo htmlspecialchars($unit['nama_unit']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="jumlah">Jumlah Transfer</label>
                                <input type="number" class="form-control" id="jumlah" name="jumlah" min="1" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="keterangan">Keterangan (Opsional)</label>
                                <input type="text" class="form-control" id="keterangan" name="keterangan" placeholder="Cth: Restock bulanan Apotek">
                            </div>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-primary btn-icon-split">
                            <span class="icon text-white-50">
                                <i class="fas fa-paper-plane"></i>
                            </span>
                            <span class="text">Proses Transfer</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history"></i> 20 Riwayat Transfer Stok Terakhir</h6>
                    <a href="<?php echo BASE_URL; ?>stok/transfer.php" class="btn btn-primary btn-sm btn-icon-split">
                        <span class="icon text-white-50"><i class="fas fa-sync-alt"></i></span>
                        <span class="text">Refresh</span>
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                            <thead class="thead-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Tgl Transfer</th>
                                    <th>Nama Obat</th>
                                    <th>Jumlah</th>
                                    <th>Dari Unit</th>
                                    <th>Ke Unit</th>
                                    <th>Pencatat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($riwayat_transfer)): ?>
                                    <tr><td colspan="7" class="text-center">Belum ada riwayat transfer stok.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($riwayat_transfer as $r): ?>
                                        <?php $is_new = ($new_id && $r['id_transfer'] == $new_id); ?>
                                        <tr class="<?php echo $is_new ? 'table-info' : ''; ?>">
                                            <td><?php echo $r['id_transfer']; ?></td>
                                            <td><?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($r['tgl_transfer']))); ?></td>
                                            <td><?php echo htmlspecialchars($r['nama_obat']); ?></td>
                                            <td><strong><?php echo htmlspecialchars($r['jumlah']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($r['nama_unit_asal']); ?></td>
                                            <td><?php echo htmlspecialchars($r['nama_unit_tujuan']); ?></td>
                                            <td><?php echo htmlspecialchars($r['pencatat']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php 
// Panggil "Kaki" (Template Footer)
include '../templates/footer.php'; 
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    
    // --- (PERBAIKAN) Aktifkan Select2 ---
    $('#id_obat').select2({
        placeholder: "-- Pilih / Cari Obat --",
        width: '100%' // [PERBAIKAN] ganti 93% jadi 100%
    });

    // [PERBAIKAN] Kita pakai class selector
    $('.select2-unit').select2({
        placeholder: "Pilih Unit", // Placeholder akan diganti oleh dropdown
        width: '100%', // [PERBAIKAN] ganti 93% jadi 100%
        minimumResultsForSearch: Infinity // Sembunyikan search bar
    });
    
    // --- Logic Reset form kamu ---
    <?php if (isset($_GET['status']) && $_GET['status'] == 'tambah_sukses'): ?>
        var currentUrl = "<?php echo BASE_URL . 'stok/transfer.php'; ?>";
        <?php if ($new_id): ?>
             // Jika ada new_id, arahkan ke URL dengan new_id agar highlight tetap ada
             currentUrl = currentUrl + "?new_id=<?php echo $new_id; ?>";
        <?php endif; ?>

        window.history.pushState({path: currentUrl}, '', currentUrl);

        setTimeout(() => {
            $('#formTransfer')[0].reset();
            $('#id_obat').val(null).trigger('change');
            $('#id_unit_asal').val(null).trigger('change');
            $('#id_unit_tujuan').val(null).trigger('change');
        }, 500);
    <?php endif; ?>

    // --- Logic 'gagal' ---
    <?php if (isset($_GET['status']) && $_GET['status'] == 'gagal'): ?>
        var cleanUrl = "<?php echo BASE_URL . 'stok/transfer.php'; ?>";
        window.history.pushState({path: cleanUrl}, '', cleanUrl);
    <?php endif; ?>
});
</script>