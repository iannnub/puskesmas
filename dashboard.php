<?php
// 1. Panggil "jantung" config.php
require_once 'config.php';

// 2. Panggil "satpam" auth_check.php
require_once 'templates/auth_check.php';

// 3. Set Judul Halaman
$page_title = "Dashboard";

// 4. [LOGIKA BARU] Ambil data untuk ringkasan (Cards)
try {
    // Hitung Total User
    $stmt_users = $pdo->query("SELECT COUNT(id_user) FROM tbl_user");
    $total_users = $stmt_users->fetchColumn();

    // Hitung Total Item Obat (Master)
    $stmt_obat = $pdo->query("SELECT COUNT(id_obat) FROM tbl_obat");
    $total_obat = $stmt_obat->fetchColumn();

    // Hitung Item Stok Kritis (stok_akhir < stok_minimum)
    $stmt_kritis = $pdo->query("SELECT COUNT(id_stok) FROM tbl_stok_inventori WHERE stok_akhir < stok_minimum AND stok_akhir > 0");
    $total_kritis = $stmt_kritis->fetchColumn();
    
    // Hitung Item Stok Habis (stok_akhir = 0)
    $stmt_habis = $pdo->query("SELECT COUNT(id_stok) FROM tbl_stok_inventori WHERE stok_akhir = 0");
    $total_habis = $stmt_habis->fetchColumn();

} catch (PDOException $e) {
    // Jika query gagal, set data ke 0
    $total_users = $total_obat = $total_kritis = $total_habis = 0;
    // (Opsional) Catat error
    // error_log($e->getMessage());
}


// 5. Panggil "Kepala" (Template Header)
// (header.php OTOMATIS memanggil sidebar.php)
include 'templates/header.php';

?>

<main class="content">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    </div>

    <p>Selamat Datang, <strong><?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?></strong>! 
       Anda login sebagai: <strong><?php echo htmlspecialchars($_SESSION['role_nama']); ?></strong>.</p>
    
    <hr>

    <div class="row">

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Item Obat (Master)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_obat; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-pills fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Akun Pengguna</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_users; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Item Stok Menipis</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_kritis; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Item Stok Habis</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_habis; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-ban fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </main>
<?php
// 6. Panggil "Kaki" (Template Footer)
include 'templates/footer.php';
?>