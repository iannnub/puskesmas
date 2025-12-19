<?php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. Panggil "satpam" auth_check.php
require_once '../templates/auth_check.php';

// 3. (SATPAM 2: ROLE CHECK)
// Hanya SUPER ADMIN (Role ID 1) yang boleh melakukan aksi ini
if ($_SESSION['role_id'] != 1) {
    header("Location: " . BASE_URL . "master/data_unit.php?status=gagal_akses");
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
        // KASUS: TAMBAH UNIT (CREATE)
        // ========================
        case 'create':
            // 1. Ambil data dari form POST
            $nama_unit = htmlspecialchars($_POST['nama_unit']);

            // 2. Validasi data
            if (empty($nama_unit)) {
                throw new Exception("Nama Unit wajib diisi.");
            }

            // 3. Siapkan & Eksekusi Query (PDO Prepared Statement)
            $sql = "INSERT INTO tbl_unit (nama_unit) VALUES (?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nama_unit]);

            // 4. Redirect kembali ke halaman data_unit dengan pesan sukses
            header("Location: " . BASE_URL . "master/data_unit.php?status=tambah_sukses");
            exit;

        // ========================
        // KASUS: UBAH UNIT (UPDATE)
        // ========================
        case 'update':
            // (Logika untuk form 'edit' yang belum kita buat UI-nya)
            // 1. Ambil data dari form POST
            $id_unit = (int)$_POST['id_unit'];
            $nama_unit = htmlspecialchars($_POST['nama_unit']);

            // 2. Validasi
            if (empty($nama_unit) || empty($id_unit)) {
                throw new Exception("Semua field wajib diisi.");
            }

            // 3. Siapkan & Eksekusi Query
            $sql = "UPDATE tbl_unit SET nama_unit = ? WHERE id_unit = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nama_unit, $id_unit]);

            // 4. Redirect kembali
            header("Location: " . BASE_URL . "master/data_unit.php?status=update_sukses");
            exit;

        // ========================
        // KASUS: HAPUS UNIT (DELETE)
        // ========================
        case 'delete':
            // 1. Ambil ID dari URL (GET)
            $id_unit_hapus = (int)$_GET['id'];
            
            if (empty($id_unit_hapus)) {
                 throw new Exception("ID Unit tidak valid.");
            }
            
            // 2. Siapkan & Eksekusi Query
            // CATATAN: InnoDB (Foreign Key) akan OTOMATIS GAGAL
            // jika Unit ini masih dipakai di tbl_poli, tbl_stok_inventori, dll.
            // Kita akan menangkapnya di block 'catch' global.
            $sql = "DELETE FROM tbl_unit WHERE id_unit = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_unit_hapus]);

            // 3. Redirect kembali
            header("Location: " . BASE_URL . "master/data_unit.php?status=hapus_sukses");
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
    // Tangkap error jika query gagal (misal: Hapus data yang terpakai)
    
    $error_message = "Operasi database gagal.";
    
    // Cek apakah ini error FOREIGN KEY (data terpakai)
    if (str_contains($e->getMessage(), 'foreign key constraint')) {
        $error_message = "Gagal menghapus! Unit ini masih digunakan oleh data Poli, User, atau Transaksi.";
    } else {
        $error_message = $e->getMessage();
    }
    
    // Redirect dengan pesan error
    header("Location: " . BASE_URL . "master/data_unit.php?status=gagal&msg=" . urlencode($error_message));
    exit;

} catch (Exception $e) {
    // ========================
    // PENANGANAN ERROR UMUM
    // ========================
    // (Misal: validasi gagal atau Aksi tidak dikenali)
    header("Location: " . BASE_URL . "master/data_unit.php?status=gagal&msg=" . urlencode($e->getMessage()));
    exit;
}
?>