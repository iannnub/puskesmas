<?php

require_once '../config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}

$error_message = '';
$success_message = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] == 1) {
        $error_message = "Login Gagal. Username atau Password salah.";
    } elseif ($_GET['error'] == 2) {
        $error_message = "Anda harus login untuk mengakses halaman itu.";
    } elseif ($_GET['error'] === 'timeout') {
        $error_message = "Sesi Anda telah berakhir karena tidak ada aktivitas selama 30 menit. Silakan login kembali.";
    }
}
if (isset($_GET['status']) && $_GET['status'] === 'logout') {
    $success_message = "Anda telah berhasil logout dari sistem.";
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

    <link href="<?php echo BASE_URL; ?>assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">

    <link href="<?php echo BASE_URL; ?>assets/css/sb-admin-2.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #0f172a;
            color: #f8fafc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 0;
        }

        .login-wrapper {
            background-color: #0f172a;
            min-height: 100vh;
        }

        .card-login {
            background-color: #ffffff !important;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.2);
            color: #1e293b;
        }

        .card-login h3 {
            color: #0f172a;
            font-weight: 700;
        }

        .card-login .form-label {
            color: #334155;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .card-login .form-control {
            min-height: 44px;
            font-size: 1rem;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            padding: 0.5rem 0.75rem;
        }

        .card-login .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }

        .btn-login {
            min-height: 44px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 8px;
            background-color: #2563eb !important;
            border-color: #2563eb !important;
            color: #ffffff !important;
            transition: background-color 0.2s ease;
        }

        .btn-login:hover,
        .btn-login:focus {
            background-color: #1d4ed8 !important;
            border-color: #1d4ed8 !important;
        }

        :focus-visible {
            outline: 2px solid #2563eb !important;
            outline-offset: 2px !important;
        }
    </style>

</head>
<body>

<section class="login-wrapper overflow-hidden">
    <div class="container px-4 py-5 px-md-5 text-center text-lg-start my-5 d-flex align-items-center" style="min-height: 90vh;">
        <div class="row gx-lg-5 align-items-center mb-5 w-100">
 
            <div class="col-lg-6 mb-5 mb-lg-0" style="z-index: 10">
                <h1 class="my-4 display-5 fw-bold ls-tight text-white">
                    SIVO Puskesmas
                    <br />
                    <span class="font-weight-bold" style="color: #60a5fa !important;">Sistem Informasi Inventori Obat</span>
                </h1>
                <p class="mb-4" style="color: #cbd5e1; font-size: 1.1rem; line-height: 1.6;">
                    Pengelolaan inventori, riwayat distribusi, dan verifikasi permintaan obat antar-unit di Puskesmas Wuluhan.
                </p>
            </div>

            <div class="col-lg-6 mb-5 mb-lg-0 position-relative">
                <div class="card card-login">
                    <div class="card-body px-4 py-5 px-md-5">
                        
                        <form action="<?php echo BASE_URL; ?>auth/proses_login.php" method="POST">
                            
                            <h3 class="text-center mb-4">Masuk ke Sistem</h3>
                            
                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <?php echo htmlspecialchars($error_message); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($success_message)): ?>
                                <div class="alert alert-success" role="alert">
                                    <?php echo htmlspecialchars($success_message); ?>
                                </div>
                            <?php endif; ?>

                            <div class="form-group text-left">
                                <label class="form-label" for="username">Username</label>
                                <input type="text" id="username" name="username" class="form-control" autocomplete="username" required />
                            </div>

                            <div class="form-group text-left">
                                <label class="form-label" for="password">Password</label>
                                <input type="password" id="password" name="password" class="form-control" autocomplete="current-password" required />
                            </div>

                            <button type="submit" class="btn btn-login btn-block mb-4 mt-4">
                                Login
                            </button>
                            
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="<?php echo BASE_URL; ?>assets/vendor/jquery/jquery.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/sb-admin-2.min.js"></script>

</body>
</html>
