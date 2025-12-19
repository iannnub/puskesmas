<?php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. Panggil "satpam" auth_check.php
require_once '../templates/auth_check.php';

// 3. (SATPAM 2: ROLE CHECK)
// Hanya POLI BELAKANG (Role ID 4) yang boleh melakukan aksi ini
if ($_SESSION['role_id'] != 4) {
    header("Location: " . BASE_URL . "request/buat.php?status=gagal_akses");
    exit;
}

// 4. Pastikan request adalah POST dan action=create
if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST['action'] == 'create') {

    // ===========================================
    // MULAI TRANSAKSI DATABASE (PENTING!)
    // ===========================================
    $pdo->beginTransaction();

    try {
        // 5. Ambil Data HEADER
        $tgl_request = htmlspecialchars($_POST['tgl_request']);
        $id_unit_tujuan = (int)$_POST['id_unit_tujuan'];
        $keterangan_request = htmlspecialchars($_POST['keterangan_request']);
        $id_user_request = (int)$_SESSION['user_id'];
        
        // [SATPAM 3: VALIDASI LOGIKA]
        // Pastikan user tidak memalsukan data unit tujuan
        if ($id_unit_tujuan != $_SESSION['unit_stok_id']) {
            throw new Exception("Error: Terjadi manipulasi data unit tujuan. Aksi dibatalkan.");
        }

        // Ambil Data DETAIL (sebagai ARRAY)
        $obat_ids = $_POST['obat_id'];
        $jumlahs = $_POST['jumlah_request'];

        // Validasi data (sederhana)
        if (empty($tgl_request) || empty($id_unit_tujuan) || !is_array($obat_ids) || empty($obat_ids[0])) {
            throw new Exception("Data request tidak lengkap. Pastikan minimal 1 obat ditambahkan.");
        }

        // ========================================================
        // LANGKAH 1: INSERT KE "KEPALA REQUEST" (tbl_request_header)
        // ========================================================
        $sql_header = "INSERT INTO tbl_request_header 
                         (tgl_request, id_user_request, id_unit_tujuan, status, keterangan_request)
                       VALUES (?, ?, ?, 'Pending', ?)";
        $stmt_header = $pdo->prepare($sql_header);
        $stmt_header->execute([
            $tgl_request, $id_user_request, $id_unit_tujuan, $keterangan_request
        ]);
        
        // Ambil ID "Request" baru ini
        $id_request_baru = $pdo->lastInsertId();

        // Siapkan query yang akan dipakai berulang-ulang di loop
        $sql_detail = "INSERT INTO tbl_request_detail (id_request, id_obat, jumlah_request)
                       VALUES (?, ?, ?)";
        $stmt_detail = $pdo->prepare($sql_detail);

        // ========================================================
        // LANGKAH 2: LOOPING "ISI REQUEST" (tbl_request_detail)
        // ========================================================
        $obat_diminta_count = 0;
        foreach ($obat_ids as $index => $id_obat) {
            // Ambil data sesuai index-nya
            $id_obat = (int)$id_obat;
            $jumlah_request = (int)$jumlahs[$index];
            
            // Validasi: Hanya proses jika ID obat dan jumlah valid
            if ($id_obat > 0 && $jumlah_request > 0) {
                // Insert ke "Isi Request" (tbl_request_detail)
                $stmt_detail->execute([$id_request_baru, $id_obat, $jumlah_request]);
                $obat_diminta_count++;
            }
        }
        
        if ($obat_diminta_count == 0) {
            // Jika setelah di-loop, ternyata tidak ada obat valid
            throw new Exception("Tidak ada item obat yang valid untuk diminta.");
        }
        
        // ===========================================
        // LANGKAH 3: SEMUA SUKSES, COMMIT TRANSAKSI
        // ===========================================
        $pdo->commit();
        
        // Redirect dengan pesan sukses
        header("Location: " . BASE_URL . "request/buat.php?status=tambah_sukses");
        exit;

    } catch (Exception $e) {
        // ========================
        // JIKA ADA ERROR, ROLLBACK!
        // ========================
        $pdo->rollBack();
        
        // Redirect dengan pesan error
        $error_message = urlencode($e->getMessage());
        header("Location: " . BASE_URL . "request/buat.php?status=gagal&msg=" . $error_message);
        exit;
    }

} else {
    // Jika diakses selain POST, tendang
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}
?>