<?php
require_once '../config.php';


require_once '../templates/auth_check.php';

if ($_SESSION['role_id'] != 4) {
    header("Location: " . BASE_URL . "request/buat.php?status=gagal_akses");
    exit;
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST['action'] == 'create') {

    
    $pdo->beginTransaction();

    try {
        
        $tgl_request = htmlspecialchars($_POST['tgl_request']);
        $id_unit_tujuan = (int)$_POST['id_unit_tujuan'];
        $keterangan_request = htmlspecialchars($_POST['keterangan_request']);
        $id_user_request = (int)$_SESSION['user_id'];
        
        
        if ($id_unit_tujuan != $_SESSION['unit_stok_id']) {
            throw new Exception("Error: Terjadi manipulasi data unit tujuan. Aksi dibatalkan.");
        }

        
        $obat_ids = $_POST['obat_id'];
        $jumlahs = $_POST['jumlah_request'];

        
        if (empty($tgl_request) || empty($id_unit_tujuan) || !is_array($obat_ids) || empty($obat_ids[0])) {
            throw new Exception("Data request tidak lengkap. Pastikan minimal 1 obat ditambahkan.");
        }

        
        $sql_header = "INSERT INTO tbl_request_header 
                         (tgl_request, id_user_request, id_unit_tujuan, status, keterangan_request)
                       VALUES (?, ?, ?, 'Pending', ?)";
        $stmt_header = $pdo->prepare($sql_header);
        $stmt_header->execute([
            $tgl_request, $id_user_request, $id_unit_tujuan, $keterangan_request
        ]);
        
        
        $id_request_baru = $pdo->lastInsertId();

        
        $sql_detail = "INSERT INTO tbl_request_detail (id_request, id_obat, jumlah_request)
                       VALUES (?, ?, ?)";
        $stmt_detail = $pdo->prepare($sql_detail);

        
        $obat_diminta_count = 0;
        foreach ($obat_ids as $index => $id_obat) {
            
            $id_obat = (int)$id_obat;
            $jumlah_request = (int)$jumlahs[$index];
            
            
            if ($id_obat > 0 && $jumlah_request > 0) {
                
                $stmt_detail->execute([$id_request_baru, $id_obat, $jumlah_request]);
                $obat_diminta_count++;
            }
        }
        
        if ($obat_diminta_count == 0) {
   
            throw new Exception("Tidak ada item obat yang valid untuk diminta.");
        }
        
        $pdo->commit();
        

        header("Location: " . BASE_URL . "request/buat.php?status=tambah_sukses");
        exit;

    } catch (Exception $e) {

        $pdo->rollBack();

        $error_message = urlencode($e->getMessage());
        header("Location: " . BASE_URL . "request/buat.php?status=gagal&msg=" . $error_message);
        exit;
    }

} else {

    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}
?>