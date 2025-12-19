<?php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. (SATPAM 1: JANGAN BIARKAN USER LOGIN 2X)
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}

// 3. Variabel untuk pesan error (jika ada)
$error_message = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] == 1) {
        $error_message = "Login Gagal. Username atau Password salah.";
    } elseif ($_GET['error'] == 2) {
        $error_message = "Anda harus login untuk mengakses halaman itu.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Sistem Informasi Inventori Obat Puskesmas Wuluhan">
    <title>Login - SIVO Puskesmas</title>

    <!-- ======================================================= -->
    <!-- [WAJIB] PANGGIL CSS SB ADMIN 2 & FONTS (dari 'assets/') -->
    <!-- ======================================================= -->
    <link href="<?php echo BASE_URL; ?>assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">
    <!-- Load CSS Utama SB Admin 2 (untuk .form-control, .btn, .alert, dll) -->
    <link href="<?php echo BASE_URL; ?>assets/css/sb-admin-2.min.css" rel="stylesheet">
    
    <!-- ======================================================= -->
    <!-- [BARU] STYLE DARI TEMPLATE YANG KAMU KASIH -->
    <!-- ======================================================= -->
    <style>
        .background-radial-gradient {
            background-color: hsl(218, 41%, 15%);
            background-image: radial-gradient(650px circle at 0% 0%,
                    hsl(218, 41%, 35%) 15%,
                    hsl(218, 41%, 30%) 35%,
                    hsl(218, 41%, 20%) 75%,
                    hsl(218, 41%, 19%) 80%,
                    transparent 100%),
                radial-gradient(1250px circle at 100% 100%,
                    hsl(218, 41%, 45%) 15%,
                    hsl(218, 41%, 30%) 35%,
                    hsl(218, 41%, 20%) 75%,
                    hsl(218, 41%, 19%) 80%,
                    transparent 100%);
            /* [PERBAIKAN] Pastikan section memenuhi layar */
            min-height: 100vh; 
        }

        #radius-shape-1 {
            height: 220px;
            width: 220px;
            top: -60px;
            left: -130px;
            background: radial-gradient(#44006b, #ad1fff);
            overflow: hidden;
        }

        #radius-shape-2 {
            border-radius: 38% 62% 63% 37% / 70% 33% 67% 30%;
            bottom: -60px;
            right: -110px;
            width: 300px;
            height: 300px;
            background: radial-gradient(#44006b, #ad1fff);
            overflow: hidden;
        }

        .bg-glass {
            /* [PERBAIKAN] Turunkan opacity sedikit biar lebih 'glass' */
            background-color: hsla(0, 0%, 100%, 0.85) !important;
            backdrop-filter: saturate(200%) blur(25px);
        }
    </style>

</head>
<body>

<!-- Section: Design Block -->
<section class="background-radial-gradient overflow-hidden">
    <!-- [PERBAIKAN] Tambahkan 'd-flex align-items-center' agar vertikal center -->
    <div class="container px-4 py-5 px-md-5 text-center text-lg-start my-5 d-flex align-items-center" style="min-height: 90vh;">
        <div class="row gx-lg-5 align-items-center mb-5">
            
            <!-- Kolom Teks Kiri (Sudah dimodifikasi) -->
            <div class="col-lg-6 mb-5 mb-lg-0" style="z-index: 10">
                <h1 class="my-5 display-5 fw-bold ls-tight" style="color: hsl(218, 81%, 95%)">
                    SIVO Puskesmas
                    <br />
                    <span style="color: hsl(218, 81%, 75%)">Sistem Informasi Inventori Obat</span>
                </h1>
                <p class="mb-4 opacity-70" style="color: hsl(218, 81%, 85%)">
                    Selamat datang di SIVO (Sistem Informasi Inventori Obat) Puskesmas Wuluhan.
                    Silakan login untuk mengelola stok, melihat riwayat, dan membuat permintaan obat.
                </p>
            </div>

            <!-- Kolom Form Kanan (Sudah dimodifikasi) -->
            <div class="col-lg-6 mb-5 mb-lg-0 position-relative">
                <div id="radius-shape-1" class="position-absolute rounded-circle shadow-5-strong"></div>
                <div id="radius-shape-2" class="position-absolute shadow-5-strong"></div>

                <div class="card bg-glass">
                    <div class="card-body px-4 py-5 px-md-5">
                        
                        <!-- ======================================================= -->
                        <!-- [PERBAIKAN] FORM LOGIN DIMULAI DARI SINI -->
                        <!-- ======================================================= -->
                        <form action="<?php echo BASE_URL; ?>auth/proses_login.php" method="POST">
                            
                            <h3 class="text-center mb-4">Silakan Login</h3>
                            
                            <!-- Tampilkan Error Message (jika ada) -->
                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <?php echo $error_message; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Username input -->
                            <!-- [PERBAIKAN] Ganti 'Email' jadi 'Username' -->
                            <div class="form-group">
                                <!-- [PERBAIKAN] Ganti MDB 'form-outline' jadi 'form-group' standar -->
                                <label class="form-label" for="username">Username</label>
                                <input type="text" id="username" name="username" class="form-control" required />
                            </div>

                            <!-- Password input -->
                            <div class="form-group">
                                <label class="form-label" for="password">Password</label>
                                <input type="password" id="password" name="password" class="form-control" required />
                            </div>

                            <!-- Checkbox (DIHAPUS) -->

                            <!-- Submit button -->
                            <!-- [PERBAIKAN] Ganti 'Sign up' jadi 'Login' -->
                            <button type="submit" class="btn btn-primary btn-block mb-4">
                                Login
                            </button>

                            <!-- Register buttons (DIHAPUS) -->
                            
                        </form>
                        <!-- ======================================================= -->
                        <!-- FORM LOGIN SELESAI -->
                        <!-- ======================================================= -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Section: Design Block -->


<!-- ======================================================= -->
<!-- [WAJIB] PANGGIL JAVASCRIPT SB ADMIN 2 (dari 'assets/') -->
<!-- ======================================================= -->
<script src="<?php echo BASE_URL; ?>assets/vendor/jquery/jquery.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/sb-admin-2.min.js"></script>

</body>
</html>