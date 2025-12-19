<?php
// 1. Panggil "jantung" config.php
// (File ini ada di 'auth/', jadi kita harus '../' (naik) dulu)
require_once '../config.php';

// 2. Cek apakah request datang dari form POST?
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 3. Ambil data dari form (Aman dari XSS)
    $username = htmlspecialchars($_POST['username']);
    $password_dari_form = htmlspecialchars($_POST['password']);

    // 4. Validasi Sederhana
    if (empty($username) || empty($password_dari_form)) {
        // Jika kosong, tendang balik ke login dengan pesan error
        header("Location: " . BASE_URL . "auth/login.php?error=1");
        exit;
    }

    try {
        // 5. SIAPKAN QUERY (Sangat Krusial!)
        // Ini adalah "Kunci Ajaib" kita. Kita ambil SEMUA data
        // yang kita butuhkan nanti dalam satu kali query.
        
        $sql = "SELECT 
                    u.id_user, 
                    u.username, 
                    u.password, 
                    u.nama_lengkap, 
                    u.id_role, 
                    u.id_poli,
                    r.nama_role,
                    p.id_unit_stok_default 
                FROM 
                    tbl_user u
                JOIN 
                    tbl_role r ON u.id_role = r.id_role
                LEFT JOIN 
                    tbl_poli p ON u.id_poli = p.id_poli
                WHERE 
                    u.username = ? AND u.is_active = 1";
        
        // 6. EKSEKUSI (PDO Prepared Statement - Aman SQL Injection)
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);

        // 7. Ambil datanya
        $user = $stmt->fetch();

        // 8. Cek User & Verifikasi Password
        // Cek: Apakah $user ditemukan? DAN Apakah password_verify() lolos?
        
        if ($user && password_verify($password_dari_form, $user['password'])) {
            
            // 9. JIKA LOGIN SUKSES
            
            // Regenerasi session ID untuk keamanan
            session_regenerate_id(true);

            // 10. SIMPAN SEMUA DATA PENTING KE SESSION
            $_SESSION['user_id'] = $user['id_user'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role_id'] = $user['id_role'];       // Cth: 4
            $_SESSION['role_nama'] = $user['nama_role'];   // Cth: "Poli Belakang"
            
            // Ini adalah "Kunci Ajaib" untuk membedakan logic
            $_SESSION['poli_id'] = $user['id_poli'];       // Cth: 7 (ID Poli UGD)
            $_SESSION['unit_stok_id'] = $user['id_unit_stok_default']; // Cth: 3 (ID Unit UGD)

            // 11. REDIRECT KE DASHBOARD
            header("Location: " . BASE_URL . "dashboard.php");
            exit;

        } else {
            // 12. JIKA GAGAL (User tidak ada ATAU password salah)
            header("Location: " . BASE_URL . "auth/login.php?error=1");
            exit;
        }

    } catch (PDOException $e) {
        // 13. Jika query database-nya error
        // (Di mode produksi, ini harus dicatat di log, bukan di-echo)
        die("Error query: " . $e->getMessage());
    }

} else {
    // 14. Jika file diakses langsung (bukan via POST), tendang
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}
?>