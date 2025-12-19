<?php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. Panggil "satpam" auth_check.php
require_once '../templates/auth_check.php';

// 3. (SATPAM 2: ROLE CHECK)
// Hanya SUPER ADMIN (Role ID 1) dan ADMIN (Role ID 2) yang boleh
if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) {
    header("Location: " . BASE_URL . "master/data_obat.php?status=gagal_akses");
    exit;
}

// 4. Tentukan Aksi (CREATE, UPDATE, atau DELETE)
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    // ===========================================
    // LOGIC SWITCH: APA YANG HARUS DILAKUKAN?
    // ===========================================
    switch ($action) {

        // ========================
        // KASUS: TAMBAH OBAT (CREATE)
        // ========================
        case 'create':
            // 1. Ambil data dari form POST
            $id_kategori_obat = (int)$_POST['id_kategori_obat'];
            $kode_obat = htmlspecialchars($_POST['kode_obat']);
            $nama_obat = htmlspecialchars($_POST['nama_obat']);
            $satuan = htmlspecialchars($_POST['satuan']);

            // 2. Validasi data
            if (empty($id_kategori_obat) || empty($kode_obat) || empty($nama_obat) || empty($satuan)) {
                throw new Exception("Semua field wajib diisi.");
            }

            // 3. Siapkan & Eksekusi Query (PDO Prepared Statement)
            $sql = "INSERT INTO tbl_obat (id_kategori_obat, kode_obat, nama_obat, satuan) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_kategori_obat, $kode_obat, $nama_obat, $satuan]);

            // 4. Redirect kembali ke halaman data_obat dengan pesan sukses
            header("Location: " . BASE_URL . "master/data_obat.php?status=tambah_sukses");
            exit;

        // ========================
        // KASUS: UBAH OBAT (UPDATE)
        // ========================
        case 'update':
            // (Logika untuk form 'edit' yang belum kita buat UI-nya)
            // 1. Ambil data dari form POST
            $id_obat = (int)$_POST['id_obat'];
            $id_kategori_obat = (int)$_POST['id_kategori_obat'];
            $kode_obat = htmlspecialchars($_POST['kode_obat']);
            $nama_obat = htmlspecialchars($_POST['nama_obat']);
            $satuan = htmlspecialchars($_POST['satuan']);

            // 2. Validasi
            if (empty($id_obat) || empty($id_kategori_obat) || empty($kode_obat) || empty($nama_obat) || empty($satuan)) {
                throw new Exception("Semua field wajib diisi.");
            }

            // 3. Siapkan & Eksekusi Query
            $sql = "UPDATE tbl_obat SET 
                        id_kategori_obat = ?, 
                        kode_obat = ?, 
                        nama_obat = ?, 
                        satuan = ? 
                    WHERE id_obat = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_kategori_obat, $kode_obat, $nama_obat, $satuan, $id_obat]);

            // 4. Redirect kembali
            header("Location: " . BASE_URL . "master/data_obat.php?status=update_sukses");
            exit;

        // ========================
        // KASUS: HAPUS OBAT (DELETE)
        // ========================
        case 'delete':
            // 1. Ambil ID dari URL (GET)
            $id_obat_hapus = (int)$_GET['id'];
            
            if (empty($id_obat_hapus)) {
                 throw new Exception("ID Obat tidak valid.");
            }
            
            // 2. Siapkan & Eksekusi Query
            // CATATAN: InnoDB (Foreign Key) akan OTOMATIS GAGAL
            // jika Obat ini masih dipakai di tbl_resep_detail, tbl_transaksi_masuk, dll.
            $sql = "DELETE FROM tbl_obat WHERE id_obat = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_obat_hapus]);

            // 3. Redirect kembali
            header("Location: " . BASE_URL . "master/data_obat.php?status=hapus_sukses");
            exit;

        // ========================
        // KASUS: DEFAULT (AKSI TIDAK DIKENALI)
        // ========================
        default:
            throw new Exception("Aksi tidak dikenali.");
    }

} catch (PDOException $e) {
    // ========================
    // PENANGANAN ERROR DATABASE
    // ========================
    
    $error_message = "Operasi database gagal.";
    
    // Cek apakah ini error FOREIGN KEY (data terpakai)
    if (str_contains($e->getMessage(), 'foreign key constraint')) {
        $error_message = "Gagal menghapus! Obat ini masih digunakan oleh data Transaksi atau Resep.";
    } elseif (str_contains($e->getMessage(), 'Duplicate entry')) {
         $error_message = "Gagal menambah/update. Kode Obat '" . htmlspecialchars($_POST['kode_obat']) . "' sudah ada di database.";
    } else {
        $error_message = $e->getMessage();
    }
    
    // Redirect dengan pesan error
    header("Location: " . BASE_URL . "master/data_obat.php?status=gagal&msg=" . urlencode($error_message));
    exit;

} catch (Exception $e) {
    // ========================
    // PENANGANAN ERROR UMUM
    // ========================
    header("Location: " . BASE_URL . "master/data_obat.php?status=gagal&msg=" . urlencode($e->getMessage()));
    exit;
}
?>