<?php

// Panggil file konfigurasi "jantung" kita
require 'config.php';

// Coba jalankan query sederhana
try {
    $stmt = $pdo->query("SELECT nama_role FROM tbl_role WHERE id_role = 3");
    $role = $stmt->fetch();
    
    // Jika berhasil, tampilkan ini
    echo "Koneksi Berhasil! <br>";
    echo "Sukses mengambil data: Role ID 3 adalah " . $role['nama_role'];

} catch (\PDOException $e) {
    // Jika gagal, tampilkan error
    echo "Koneksi Gagal: " . $e->getMessage();
}

?>