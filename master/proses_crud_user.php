<?php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. Panggil "satpam" auth_check.php
// Pastikan user sudah login
require_once '../templates/auth_check.php';

// 3. (SATPAM 2: ROLE CHECK)
// Hanya SUPER ADMIN (Role ID 1) yang boleh melakukan aksi ini
if ($_SESSION['role_id'] != 1) {
    // Jika bukan Super Admin, tendang paksa
    // Kita bisa kirim status 'gagal' atau 'ditolak'
    header("Location: " . BASE_URL . "master/data_user.php?status=gagal_akses");
    exit;
}

// 4. Tentukan Aksi (CREATE, UPDATE, atau DELETE)
// Kita gunakan 'action' dari POST (untuk Tambah/Update) atau GET (untuk Hapus)
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    // ===========================================
    // LOGIC SWITCH: APA YANG HARUS DILAKUKAN?
    // ===========================================
    switch ($action) {

        // ========================
        // KASUS: TAMBAH USER (CREATE)
        // ========================
        case 'create':
            // 1. Ambil data dari form POST
            $nama_lengkap = htmlspecialchars($_POST['nama_lengkap']);
            $username = htmlspecialchars($_POST['username']);
            $password_dari_form = $_POST['password']; // Jangan htmlspecialchars password
            $id_role = (int)$_POST['id_role'];
            $id_poli_input = $_POST['id_poli']; // Ambil sebagai string dulu

            // 2. Validasi data
            if (empty($nama_lengkap) || empty($username) || empty($password_dari_form) || empty($id_role)) {
                throw new Exception("Semua field (kecuali Poli) wajib diisi.");
            }
            if (strlen($password_dari_form) < 6) {
                throw new Exception("Password minimal 6 karakter.");
            }
            
            // 3. (KUNCI LOGIKA KITA) Cek apakah id_poli harus NULL?
            // Jika Role-nya Super Admin (1) atau Admin (2), id_poli HARUS NULL
            $id_poli = NULL;
            if ($id_role == 3 || $id_role == 4) { // Jika Role Poli Depan atau Belakang
                if ($id_poli_input == "NULL" || empty($id_poli_input)) {
                    throw new Exception("Role Poli wajib memilih 'Terkait Poli'.");
                }
                $id_poli = (int)$id_poli_input;
            }

            // 4. HASH PASSWORD (Wajib Bcrypt)
            $hashed_password = password_hash($password_dari_form, PASSWORD_BCRYPT);

            // 5. Siapkan & Eksekusi Query (PDO Prepared Statement)
            $sql = "INSERT INTO tbl_user (id_role, id_poli, username, password, nama_lengkap, is_active) 
                    VALUES (?, ?, ?, ?, ?, 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_role, $id_poli, $username, $hashed_password, $nama_lengkap]);

            // 6. Redirect kembali ke halaman data_user dengan pesan sukses
            header("Location: " . BASE_URL . "master/data_user.php?status=tambah_sukses");
            exit;

        // ========================
        // KASUS: UBAH USER (UPDATE)
        // ========================
        case 'update':
            // (CATATAN: Ini adalah logic untuk form 'edit' yang belum kita buat UI-nya)
            // 1. Ambil data dari form POST
            $id_user_edit = (int)$_POST['id_user'];
            $nama_lengkap = htmlspecialchars($_POST['nama_lengkap']);
            $username = htmlspecialchars($_POST['username']);
            $password_dari_form = $_POST['password']; // Password baru (opsional)
            $id_role = (int)$_POST['id_role'];
            $id_poli_input = $_POST['id_poli'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // 2. Logic id_poli (Sama seperti 'create')
            $id_poli = NULL;
            if ($id_role == 3 || $id_role == 4) {
                $id_poli = (int)$id_poli_input;
            }
            
            // 3. (KUNCI LOGIKA UPDATE) Cek apakah password diisi?
            if (!empty($password_dari_form)) {
                // JIKA YA: User ingin ganti password
                if (strlen($password_dari_form) < 6) {
                    throw new Exception("Password baru minimal 6 karakter.");
                }
                $hashed_password = password_hash($password_dari_form, PASSWORD_BCRYPT);
                
                // Query UPDATE dengan password baru
                $sql = "UPDATE tbl_user SET id_role=?, id_poli=?, username=?, password=?, nama_lengkap=?, is_active=?
                        WHERE id_user=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_role, $id_poli, $username, $hashed_password, $nama_lengkap, $is_active, $id_user_edit]);
                
            } else {
                // JIKA TIDAK: User TIDAK ingin ganti password
                
                // Query UPDATE TANPA password
                $sql = "UPDATE tbl_user SET id_role=?, id_poli=?, username=?, nama_lengkap=?, is_active=?
                        WHERE id_user=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_role, $id_poli, $username, $nama_lengkap, $is_active, $id_user_edit]);
            }
            
            // 4. Redirect kembali
            header("Location: " . BASE_URL . "master/data_user.php?status=update_sukses");
            exit;

        // ========================
        // KASUS: HAPUS USER (DELETE)
        // ========================
        case 'delete':
            // 1. Ambil ID dari URL (GET)
            $id_user_hapus = (int)$_GET['id'];
            
            // 2. (SATPAM 3: JANGAN HAPUS DIRI SENDIRI)
            if ($id_user_hapus == $_SESSION['user_id']) {
                throw new Exception("Anda tidak bisa menghapus akun Anda sendiri.");
            }
            // (SATPAM 4: JANGAN HAPUS SUPER ADMIN PERTAMA)
            if ($id_user_hapus == 1) { // Asumsi ID 1 adalah root Super Admin
                 throw new Exception("Akun root Super Admin tidak boleh dihapus.");
            }

            // 3. Siapkan & Eksekusi Query (PDO Prepared Statement)
            // (CATATAN: Seharusnya cek dulu apakah user ini punya relasi
            // di tabel transaksi, tapi untuk Fase 1 kita hapus langsung)
            $sql = "DELETE FROM tbl_user WHERE id_user = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_user_hapus]);

            // 4. Redirect kembali
            header("Location: " . BASE_URL . "master/data_user.php?status=hapus_sukses");
            exit;

        // ========================
        // KASUS: DEFAULT (AKSI TIDAK DIKENALI)
        // ========================
        default:
            throw new Exception("Aksi tidak dikenali.");
    }

} catch (Exception $e) {
    // ========================
    // PENANGANAN ERROR GLOBAL
    // ========================
    // Jika ada error (Exception) dari mana pun di atas, tangkap di sini
    // (Misal: username duplikat, query gagal, validasi gagal)
    
    // Tampilkan pesan error (di mode produksi, ini harus dicatat di log)
    echo "Error: " . $e->getMessage();
    echo "<br><a href='" . BASE_URL . "master/data_user.php'>Kembali ke Data User</a>";
    
    // Atau redirect dengan pesan error
    // header("Location: " . BASE_URL . "master/data_user.php?status=gagal&msg=" . urlencode($e->getMessage()));
    // exit;
}
?>