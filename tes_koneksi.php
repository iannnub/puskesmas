<?php


require 'config.php';

try {
    $stmt = $pdo->query("SELECT nama_role FROM tbl_role WHERE id_role = 3");
    $role = $stmt->fetch();
    
   
    echo "Koneksi Berhasil! <br>";
    echo "Sukses mengambil data: Role ID 3 adalah " . $role['nama_role'];

} catch (\PDOException $e) {
 
    echo "Koneksi Gagal: " . $e->getMessage();
}

?>