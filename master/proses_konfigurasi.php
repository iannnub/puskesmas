<?php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. Panggil "satpam" auth_check.php
require_once '../templates/auth_check.php';

// 3. (SATPAM 2: ROLE CHECK)
// Hanya SUPER ADMIN (Role ID 1) yang boleh melakukan aksi ini
if ($_SESSION['role_id'] != 1) {
    header("Location: " . BASE_URL . "master/konfigurasi.php?status=gagal_akses");
    exit;
}

// 4. Pastikan request adalah POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    try {
        // ===========================================
        // LOGIC UPDATE (Profesional & Scalable)
        // ===========================================

        // 1. Siapkan query "INSERT... ON DUPLICATE KEY UPDATE..."
        // Ini adalah query "ajaib" MySQL:
        // - Coba INSERT 'setting_key' baru.
        // - Jika 'setting_key' (yang UNIQUE) sudah ada, JANGAN error,
        //   tapi lakukan UPDATE pada 'setting_value'.
        // Ini sempurna untuk form konfigurasi.
        $sql = "INSERT INTO tbl_konfigurasi (setting_key, setting_value) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        
        $stmt = $pdo->prepare($sql);

        // 2. Loop semua data yang dikirim dari Form
        // (cth: 'nama_puskesmas', 'alamat_puskesmas', 'telepon_puskesmas')
        foreach ($_POST as $key => $value) {
            
            // 3. Amankan data (key & value)
            $setting_key = htmlspecialchars($key);
            $setting_value = htmlspecialchars($value);

            // 4. Eksekusi query untuk SETIAP setting
            $stmt->execute([$setting_key, $setting_value]);
        }

        // 5. Redirect kembali dengan pesan sukses
        header("Location: " . BASE_URL . "master/konfigurasi.php?status=update_sukses");
        exit;

    } catch (Exception $e) {
        // ========================
        // PENANGANAN ERROR
        // ========================
        header("Location: " . BASE_URL . "master/konfigurasi.php?status=gagal&msg=" . urlencode($e->getMessage()));
        exit;
    }

} else {
    // Jika diakses selain POST, tendang
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}
?>