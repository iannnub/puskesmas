<?php

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Sistem Informasi Inventori Obat Puskesmas Wuluhan">
    <meta name="author" content="Mahasiswa Sistem Informasi">

    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - SIVO' : 'SIVO Puskesmas'; ?></title>

    <link href="<?php echo BASE_URL; ?>assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">

    <link href="<?php echo BASE_URL; ?>assets/css/sb-admin-2.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="<?php echo BASE_URL; ?>assets/vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="<?php echo BASE_URL; ?>assets/vendor/select2.min.css" rel="stylesheet" />
    <link href="<?php echo BASE_URL; ?>assets/vendor/select2-bootstrap4.min.css" rel="stylesheet">

    <script>
        const BASE_URL = "<?php echo BASE_URL; ?>";
    </script>
    
    <style>
        /* Style agar Select2 tidak "meledak" */
        .select2-container { 
            width: 100% !important; /* Ganti dari 93% ke 100% agar pas di card */
        }
    </style>

</head>

<body id="page-top">

    <div id="wrapper">

        <?php 
        // [PERUBAHAN] PANGGIL SIDEBAR.PHP
        // (Akan kita buat di langkah selanjutnya)
        include 'sidebar.php'; 
        ?>

        <div id="content-wrapper" class="d-flex flex-column">

            <div id="content">

                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>

                    <ul class="navbar-nav ml-auto">

                        <div class="topbar-divider d-none d-sm-block"></div>

                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                                    <?php echo htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Pengguna'); ?>
                                    <br>
                                    <small>(<?php echo htmlspecialchars($_SESSION['role_nama'] ?? 'Role'); ?>)</small>
                                </span>
                                <img class="img-profile rounded-circle"
                                    src="<?php echo BASE_URL; ?>assets/img/undraw_profile.svg">
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Logout
                                </a>
                            </div>
                        </li>

                    </ul>

                </nav>
                <div class="container-fluid">

                    <?php if (isset($_SESSION['flash_error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($_SESSION['flash_error']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['flash_error']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['flash_success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($_SESSION['flash_success']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['flash_success']); ?>
                    <?php endif; ?>
