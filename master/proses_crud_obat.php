<?php
// master/proses_crud_obat.php
// Penanganan CRUD Master Obat Terpadu dengan Inisialisasi Inventori Otomatis (BUG-SIVO-012)
// dan Proteksi Keamanan Transaksi & CSRF (BUG-SIVO-009)

require_once '../config.php';
require_once '../templates/auth_check.php';

// Akses terbatas hanya untuk Super Admin (1) dan Admin (2)
if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) {
    header("Location: " . BASE_URL . "master/data_obat.php?status=gagal_akses");
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {

    switch ($action) {

        case 'create':
            $id_kategori_obat = (int)($_POST['id_kategori_obat'] ?? 0);
            $kode_obat        = trim($_POST['kode_obat'] ?? '');
            $nama_obat        = trim($_POST['nama_obat'] ?? '');
            $satuan           = trim($_POST['satuan'] ?? '');

            if (empty($id_kategori_obat) || empty($kode_obat) || empty($nama_obat) || empty($satuan)) {
                throw new Exception("Semua field wajib diisi.");
            }

            // Validasi format kode obat (tidak boleh kosong atau karakter berbahaya)
            $kode_obat = htmlspecialchars($kode_obat, ENT_QUOTES, 'UTF-8');
            $nama_obat = htmlspecialchars($nama_obat, ENT_QUOTES, 'UTF-8');
            $satuan    = htmlspecialchars($satuan, ENT_QUOTES, 'UTF-8');

            $pdo->beginTransaction();

            $sql = "INSERT INTO tbl_obat (id_kategori_obat, kode_obat, nama_obat, satuan) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_kategori_obat, $kode_obat, $nama_obat, $satuan]);
            $new_id_obat = (int)$pdo->lastInsertId();

            // BUG-SIVO-012: Inisialisasi baris inventori 0 untuk seluruh unit aktif
            // Mencegah duplicate key conflict saat concurrent restock & memastikan data konsisten
            $stmt_units = $pdo->query("SELECT id_unit FROM tbl_unit");
            $units = $stmt_units->fetchAll(PDO::FETCH_COLUMN);

            $stmt_init_stok = $pdo->prepare(
                "INSERT INTO tbl_stok_inventori (id_obat, id_unit, stok_akhir, stok_minimum, updated_at) 
                 VALUES (?, ?, 0, 10, NOW())"
            );
            foreach ($units as $u_id) {
                $stmt_init_stok->execute([$new_id_obat, $u_id]);
            }

            $pdo->commit();

            header("Location: " . BASE_URL . "master/data_obat.php?status=tambah_sukses");
            exit;

        case 'update':
            $id_obat          = (int)($_POST['id_obat'] ?? 0);
            $id_kategori_obat = (int)($_POST['id_kategori_obat'] ?? 0);
            $kode_obat        = trim($_POST['kode_obat'] ?? '');
            $nama_obat        = trim($_POST['nama_obat'] ?? '');
            $satuan           = trim($_POST['satuan'] ?? '');

            if (empty($id_obat) || empty($id_kategori_obat) || empty($kode_obat) || empty($nama_obat) || empty($satuan)) {
                throw new Exception("Semua field wajib diisi.");
            }

            $kode_obat = htmlspecialchars($kode_obat, ENT_QUOTES, 'UTF-8');
            $nama_obat = htmlspecialchars($nama_obat, ENT_QUOTES, 'UTF-8');
            $satuan    = htmlspecialchars($satuan, ENT_QUOTES, 'UTF-8');

            $sql = "UPDATE tbl_obat SET 
                        id_kategori_obat = ?, 
                        kode_obat = ?, 
                        nama_obat = ?, 
                        satuan = ? 
                    WHERE id_obat = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_kategori_obat, $kode_obat, $nama_obat, $satuan, $id_obat]);

            header("Location: " . BASE_URL . "master/data_obat.php?status=update_sukses");
            exit;

        case 'delete':
            // Verifikasi CSRF Token (BUG-SIVO-009)
            $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
            if (!verify_csrf($token)) {
                throw new Exception("Token keamanan tidak valid atau telah kedaluwarsa. Silakan refresh halaman.");
            }

            $id_obat_hapus = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($id_obat_hapus <= 0) {
                 throw new Exception("ID Obat tidak valid.");
            }

            // 1. Cek apakah obat pernah memiliki transaksi mutasi stok di tbl_log_stok
            $stmt_cek_log = $pdo->prepare("SELECT COUNT(*) FROM tbl_log_stok WHERE id_obat = ?");
            $stmt_cek_log->execute([$id_obat_hapus]);
            if ($stmt_cek_log->fetchColumn() > 0) {
                throw new Exception("Gagal menghapus! Obat ini sudah memiliki histori transaksi atau mutasi stok.");
            }

            // 2. Cek apakah obat tercatat di resep atau request
            $stmt_cek_resep = $pdo->prepare("SELECT COUNT(*) FROM tbl_resep_detail WHERE id_obat = ?");
            $stmt_cek_resep->execute([$id_obat_hapus]);
            if ($stmt_cek_resep->fetchColumn() > 0) {
                throw new Exception("Gagal menghapus! Obat ini tercatat dalam data resep pasien.");
            }

            $stmt_cek_req = $pdo->prepare("SELECT COUNT(*) FROM tbl_request_detail WHERE id_obat = ?");
            $stmt_cek_req->execute([$id_obat_hapus]);
            if ($stmt_cek_req->fetchColumn() > 0) {
                throw new Exception("Gagal menghapus! Obat ini tercatat dalam permohonan obat.");
            }

            // 3. Cek apakah ada sisa fisik stok di unit (> 0)
            $stmt_cek_stok = $pdo->prepare("SELECT SUM(stok_akhir) FROM tbl_stok_inventori WHERE id_obat = ?");
            $stmt_cek_stok->execute([$id_obat_hapus]);
            $stok_aktif = (int)$stmt_cek_stok->fetchColumn();
            if ($stok_aktif > 0) {
                throw new Exception("Gagal menghapus! Obat ini masih memiliki saldo stok fisik di unit.");
            }

            // Jika obat baru dan belum pernah ada mutasi transaksi, hapus baris inisialisasi inventori kosong lalu hapus obat
            $pdo->beginTransaction();
            $stmt_del_inv = $pdo->prepare("DELETE FROM tbl_stok_inventori WHERE id_obat = ?");
            $stmt_del_inv->execute([$id_obat_hapus]);

            $sql = "DELETE FROM tbl_obat WHERE id_obat = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_obat_hapus]);
            $pdo->commit();

            header("Location: " . BASE_URL . "master/data_obat.php?status=hapus_sukses");
            exit;

        default:
            throw new Exception("Aksi tidak dikenali.");
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("[SIVO MASTER OBAT DB ERROR] " . $e->getMessage());

    if (str_contains($e->getMessage(), 'foreign key constraint')) {
        $error_message = "Gagal menghapus! Obat ini masih digunakan oleh data Transaksi atau Resep.";
    } elseif (str_contains($e->getMessage(), 'Duplicate entry')) {
        $error_message = "Gagal menambah/update. Kode Obat '" . htmlspecialchars($_POST['kode_obat'] ?? '') . "' sudah ada di database.";
    } else {
        $error_message = "Terjadi kesalahan database saat memproses data obat.";
    }

    header("Location: " . BASE_URL . "master/data_obat.php?status=gagal&msg=" . urlencode($error_message));
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("[SIVO MASTER OBAT ERROR] " . $e->getMessage());
    header("Location: " . BASE_URL . "master/data_obat.php?status=gagal&msg=" . urlencode($e->getMessage()));
    exit;
}