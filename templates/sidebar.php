<?php
$current_page = $_SERVER['REQUEST_URI'];
?>

<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?php echo BASE_URL; ?>dashboard.php">
        <div class="sidebar-brand-icon rotate-n-15">
            <i class="fas fa-hospital-user"></i>
        </div>
        <div class="sidebar-brand-text mx-3">SIVO Puskesmas</div>
    </a>

    <hr class="sidebar-divider my-0">

    <li class="nav-item <?php echo (strpos($current_page, 'dashboard.php') !== false) ? 'active' : ''; ?>">
        <a class="nav-link" href="<?php echo BASE_URL; ?>dashboard.php">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>Dashboard</span></a>
    </li>

    <?php 
    // --- MENU UNTUK SUPER ADMIN (Role ID 1) ---
    if ($_SESSION['role_id'] == 1) : 
    ?>
        <hr class="sidebar-divider">
        <div class="sidebar-heading">
            Super Admin Menu
        </div>
        <li class="nav-item <?php echo (strpos($current_page, 'master/data_user.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>master/data_user.php">
                <i class="fas fa-fw fa-users-cog"></i> <span>Kelola Akun User</span></a>
        </li>
        <li class="nav-item <?php echo (strpos($current_page, 'master/data_poli.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>master/data_poli.php">
                <i class="fas fa-fw fa-clinic-medical"></i> <span>Kelola Data Poli</span></a>
        </li>
        <li class="nav-item <?php echo (strpos($current_page, 'master/data_unit.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>master/data_unit.php">
                <i class="fas fa-fw fa-warehouse"></i> <span>Kelola Data Unit</span></a>
        </li>
        <li class="nav-item <?php echo (strpos($current_page, 'master/data_pelayanan.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>master/data_pelayanan.php">
                <i class="fas fa-fw fa-heartbeat"></i> <span>Kelola Pelayanan</span></a>
        </li>
        <li class="nav-item <?php echo (strpos($current_page, 'master/konfigurasi.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>master/konfigurasi.php">
                <i class="fas fa-fw fa-cogs"></i> <span>Konfigurasi Sistem</span></a>
        </li>
         <li class="nav-item <?php echo (strpos($current_page, 'request/riwayat.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>request/riwayat.php">
                <i class="fas fa-fw fa-history"></i> <span>Riwayat Request (Semua)</span></a>
        </li>
    
    <?php 
    // --- MENU UNTUK ADMIN OPERATOR (Role ID 2) ---
    elseif ($_SESSION['role_id'] == 2) : 
    ?>
        <hr class="sidebar-divider">
        <div class="sidebar-heading">
            Admin Menu
        </div>
        <li class="nav-item <?php echo (strpos($current_page, 'master/data_obat.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>master/data_obat.php">
                <i class="fas fa-fw fa-pills"></i> <span>Kelola Data Obat</span></a>
        </li>
        <li class="nav-item <?php echo (strpos($current_page, 'stok/terima.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>stok/terima.php">
                <i class="fas fa-fw fa-truck-loading"></i> <span>Penerimaan Stok (Vendor)</span></a>
        </li>
        <li class="nav-item <?php echo (strpos($current_page, 'stok/transfer.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>stok/transfer.php">
                <i class="fas fa-fw fa-exchange-alt"></i> <span>Transfer Stok Internal</span></a>
        </li>
        <li class="nav-item <?php echo (strpos($current_page, 'stok/opname.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>stok/opname.php">
                <i class="fas fa-fw fa-boxes"></i> <span>Stok Opname</span></a>
        </li>
        <li class="nav-item <?php echo (strpos($current_page, 'resep/tambah.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>resep/tambah.php">
                <i class="fas fa-fw fa-file-medical-alt"></i> <span>Input Resep (Poli Depan)</span></a>
        </li>
        <li class="nav-item <?php echo (strpos($current_page, 'request/kelola.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>request/kelola.php">
                <i class="fas fa-fw fa-tasks"></i> <span>Kelola Request Stok</span></a>
        </li>
        <li class="nav-item <?php echo (strpos($current_page, 'request/riwayat.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>request/riwayat.php">
                <i class="fas fa-fw fa-history"></i> <span>Riwayat Request (Semua)</span></a>
        </li>
    
    <?php 
    // --- MENU UNTUK POLI DEPAN (Role ID 3) ---
    elseif ($_SESSION['role_id'] == 3) : 
    ?>
        <hr class="sidebar-divider">
        <div class="sidebar-heading">
            Poli Depan Menu
        </div>
        <li class="nav-item <?php echo (strpos($current_page, 'stok/lihat.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>stok/lihat.php">
                <i class="fas fa-fw fa-eye"></i> <span>Lihat Stok Apotek</span></a>
        </li>

    <?php 
    // --- MENU UNTUK POLI BELAKANG (Role ID 4) ---
    elseif ($_SESSION['role_id'] == 4) : 
    ?>
        <hr class="sidebar-divider">
        <div class="sidebar-heading">
            Poli Belakang Menu
        </div>
        <li class="nav-item <?php echo (strpos($current_page, 'stok/lihat.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>stok/lihat.php">
                <i class="fas fa-fw fa-eye"></i> <span>Lihat Stok Sendiri</span></a>
        </li>
        <li class="nav-item <?php echo (strpos($current_page, 'resep/tambah.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>resep/tambah.php">
                <i class="fas fa-fw fa-file-medical-alt"></i> <span>Input Pemakaian Sendiri</span></a>
        </li>
        <li class="nav-item <?php echo (strpos($current_page, 'request/buat.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>request/buat.php">
                <i class="fas fa-fw fa-paper-plane"></i> <span>Buat Request Stok</span></a>
        </li>
        <li class="nav-item <?php echo (strpos($current_page, 'request/riwayat.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>request/riwayat.php">
                <i class="fas fa-fw fa-history"></i> <span>Riwayat Request Saya</span></a>
        </li>

    <?php endif; ?>
    
    <?php if ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2) : ?>
        <hr class="sidebar-divider">
        <div class="sidebar-heading">
            Laporan
        </div>
        <li class="nav-item <?php echo (strpos($current_page, 'laporan/kartu_stok.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>laporan/kartu_stok.php">
                <i class="fas fa-fw fa-file-invoice"></i> <span>Laporan Kartu Stok</span></a>
        </li>
        <li class="nav-item <?php echo (strpos($current_page, 'laporan/harian.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>laporan/harian.php">
                <i class="fas fa-fw fa-calendar-day"></i> <span>Laporan Harian</span></a>
        </li>
        <li class="nav-item <?php echo (strpos($current_page, 'laporan/kunjungan.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>laporan/kunjungan.php">
                <i class="fas fa-fw fa-chart-pie"></i> <span>Laporan Kunjungan</span></a>
        </li>
        <li class="nav-item <?php echo (strpos($current_page, 'laporan/bulanan.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>laporan/bulanan.php">
                <i class="fas fa-fw fa-calendar-alt"></i> <span>Laporan Bulanan</span></a>
        </li>
        <li class="nav-item <?php echo (strpos($current_page, 'laporan/sasaran_mutu.php') !== false) ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo BASE_URL; ?>laporan/sasaran_mutu.php">
                <i class="fas fa-fw fa-bullseye"></i> <span>Laporan Sasaran Mutu</span></a>
        </li>
    <?php endif; ?>

    <hr class="sidebar-divider d-none d-md-block">

    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

</ul>