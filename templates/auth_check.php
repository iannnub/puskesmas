<?php
// File ini HARUS dipanggil setelah 'config.php' (karena butuh session_start())

// 1. Cek apakah user BELUM login?
// (Cek apakah session 'user_id' TIDAK ada?)
if (!isset($_SESSION['user_id'])) {
    
    // 2. Jika belum login, tendang paksa ke halaman login
    // Kirim pesan error=2 (Anda harus login)
    header("Location: " . BASE_URL . "auth/login.php?error=2"); // <-- PERBAIKAN DI SINI
    
    // 3. Hentikan script
    exit;
}

// 4. Jika session-nya ADA (sudah login), biarkan script lanjut.
?>