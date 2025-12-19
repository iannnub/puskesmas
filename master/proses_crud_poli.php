<?php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. Panggil "satpam" auth_check.php
require_once '../templates/auth_check.php';

// 3. (SATPAM 2: ROLE CHECK)
// Hanya SUPER ADMIN (Role ID 1) yang boleh melakukan aksi ini
if ($_SESSION['role_id'] != 1) {
    header("Location: " . BASE_URL . "master/data_poli.php?status=gagal_akses");
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
        // KASUS: TAMBAH POLI (CREATE)
        // ========================
        case 'create':
            // 1. Ambil data dari form POST
            $nama_poli = htmlspecialchars($_POST['nama_poli']);
            $id_unit_stok_default = (int)$_POST['id_unit_stok_default'];

            // 2. Validasi data
            if (empty($nama_poli) || empty($id_unit_stok_default)) {
                throw new Exception("Nama Poli dan Unit Stok Default wajib diisi.");
            }

            // 3. Siapkan & Eksekusi Query (PDO Prepared Statement)
            $sql = "INSERT INTO tbl_poli (nama_poli, id_unit_stok_default) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nama_poli, $id_unit_stok_default]);

            // 4. Redirect kembali ke halaman data_poli dengan pesan sukses
            header("Location: " . BASE_URL . "master/data_poli.php?status=tambah_sukses");
            exit;

        // ========================
        // KASUS: UBAH POLI (UPDATE)
        // ========================
        case 'update':
            // (Logika untuk form 'edit' yang belum kita buat UI-nya)
            // 1. Ambil data dari form POST
            $id_poli = (int)$_POST['id_poli'];
            $nama_poli = htmlspecialchars($_POST['nama_poli']);
            $id_unit_stok_default = (int)$_POST['id_unit_stok_default'];

            // 2. Validasi
            if (empty($nama_poli) || empty($id_unit_stok_default) || empty($id_poli)) {
                throw new Exception("Semua field wajib diisi.");
            }

            // 3. Siapkan & Eksekusi Query
            $sql = "UPDATE tbl_poli SET nama_poli = ?, id_unit_stok_default = ? WHERE id_poli = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nama_poli, $id_unit_stok_default, $id_poli]);

            // 4. Redirect kembali
            header("Location: " . BASE_URL . "master/data_poli.php?status=update_sukses");
            exit;

        // ========================
        // KASUS: HAPUS POLI (DELETE)
        // ========================
        case 'delete':
            // 1. Ambil ID dari URL (GET)
            $id_poli_hapus = (int)$_GET['id'];
            
            if (empty($id_poli_hapus)) {
                 throw new Exception("ID Poli tidak valid.");
            }
            
            // 2. Siapkan & Eksekusi Query
            // CATATAN: InnoDB (Foreign Key) akan OTOMATIS GAGAL
            // jika Poli ini masih dipakai di tbl_user atau tbl_resep_header.
            // Kita akan menangkapnya di block 'catch' global.
            $sql = "DELETE FROM tbl_poli WHERE id_poli = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_poli_hapus]);

            // 3. Redirect kembali
            header("Location: " . BASE_URL . "master/data_poli.php?status=hapus_sukses");
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
        $error_message = "Gagal menghapus! Poli ini masih digunakan oleh data User atau data Resep.";
    } else {
        $error_message = $e->getMessage();
    }
    
    // Redirect dengan pesan error
    header("Location: " . BASE_URL . "master/data_poli.php?status=gagal&msg=" . urlencode($error_message));
    exit;

} catch (Exception $e) {
    // ========================
    // PENANGANAN ERROR UMUM
    // ========================
    // (Misal: validasi gagal atau Aksi tidak dikenali)
    header("Location: " . BASE_URL . "master/data_poli.php?status=gagal&msg=" . urlencode($e->getMessage()));
    exit;
}
?>