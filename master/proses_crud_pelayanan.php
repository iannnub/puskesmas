<?php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. Panggil "satpam" auth_check.php
require_once '../templates/auth_check.php';

// 3. (SATPAM 2: ROLE CHECK)
// Hanya SUPER ADMIN (Role ID 1) yang boleh melakukan aksi ini
if ($_SESSION['role_id'] != 1) {
    header("Location: " . BASE_URL . "master/data_pelayanan.php?status=gagal_akses");
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
        // KASUS: TAMBAH PELAYANAN (CREATE)
        // ========================
        case 'create':
            // 1. Ambil data dari form POST
            $jenis_pelayanan = htmlspecialchars($_POST['jenis_pelayanan']);

            // 2. Validasi data
            if (empty($jenis_pelayanan)) {
                throw new Exception("Jenis Pelayanan wajib diisi.");
            }

            // 3. Siapkan & Eksekusi Query (PDO Prepared Statement)
            $sql = "INSERT INTO tbl_pelayanan (jenis_pelayanan) VALUES (?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$jenis_pelayanan]);

            // 4. Redirect kembali ke halaman data_pelayanan dengan pesan sukses
            header("Location: " . BASE_URL . "master/data_pelayanan.php?status=tambah_sukses");
            exit;

        // ========================
        // KASUS: UBAH PELAYANAN (UPDATE)
        // ========================
        case 'update':
            // (Logika untuk form 'edit' yang belum kita buat UI-nya)
            // 1. Ambil data dari form POST
            $id_pelayanan = (int)$_POST['id_pelayanan'];
            $jenis_pelayanan = htmlspecialchars($_POST['jenis_pelayanan']);

            // 2. Validasi
            if (empty($jenis_pelayanan) || empty($id_pelayanan)) {
                throw new Exception("Semua field wajib diisi.");
            }

            // 3. Siapkan & Eksekusi Query
            $sql = "UPDATE tbl_pelayanan SET jenis_pelayanan = ? WHERE id_pelayanan = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$jenis_pelayanan, $id_pelayanan]);

            // 4. Redirect kembali
            header("Location: " . BASE_URL . "master/data_pelayanan.php?status=update_sukses");
            exit;

        // ========================
        // KASUS: HAPUS PELAYANAN (DELETE)
        // ========================
        case 'delete':
            // 1. Ambil ID dari URL (GET)
            $id_pelayanan_hapus = (int)$_GET['id'];
            
            if (empty($id_pelayanan_hapus)) {
                 throw new Exception("ID Pelayanan tidak valid.");
            }
            
            // 2. Siapkan & Eksekusi Query
            // CATATAN: InnoDB (Foreign Key) akan OTOMATIS GAGAL
            // jika Pelayanan ini masih dipakai di tbl_resep_header.
            // Kita akan menangkapnya di block 'catch' global.
            $sql = "DELETE FROM tbl_pelayanan WHERE id_pelayanan = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_pelayanan_hapus]);

            // 3. Redirect kembali
            header("Location: " . BASE_URL . "master/data_pelayanan.php?status=hapus_sukses");
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
        $error_message = "Gagal menghapus! Jenis Pelayanan ini masih digunakan oleh data Resep.";
    } else {
        $error_message = $e->getMessage();
    }
    
    // Redirect dengan pesan error
    header("Location: " . BASE_URL . "master/data_pelayanan.php?status=gagal&msg=" . urlencode($error_message));
    exit;

} catch (Exception $e) {
    // ========================
    // PENANGANAN ERROR UMUM
    // ========================
    // (Misal: validasi gagal atau Aksi tidak dikenali)
    header("Location: " . BASE_URL . "master/data_pelayanan.php?status=gagal&msg=" . urlencode($e->getMessage()));
    exit;
}
?>