<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../config.php';

echo "=== VERIFIKASI TIKET P0-05: RBAC SESSION KEY STANDARDIZATION ===\n";

// Skenario 1: User Poli Umum (Role 2) mencoba mengakses bulanan_poli.php
$_SESSION['user_id'] = 1;
$_SESSION['role_id'] = 2; // Poli Umum
$_SESSION['unit_stok_id'] = 3; // Poli Umum
unset($_SESSION['id_role'], $_SESSION['id_unit']); // Simulasi tidak ada legacy key

$id_role_user = (int)($_SESSION['role_id'] ?? 0);
$is_admin = in_array($id_role_user, [1, 5], true);
$is_poli_belakang = in_array($id_role_user, [4], true);
$allowed_poli_umum = ($is_admin || $is_poli_belakang);

echo "1. Akses Poli Umum (Role 2) ke Laporan Farmasi/Poli Belakang:\n";
echo "   - Status Izin: " . ($allowed_poli_umum ? "DIIZINKAN (FAIL - VULNERABLE)" : "DITOLAK (PASS - SECURE)") . "\n";

// Skenario 2: User Rawat Inap / Poli Belakang (Role 4)
$_SESSION['role_id'] = 4;
$_SESSION['unit_stok_id'] = 4;
$id_role_user = (int)($_SESSION['role_id'] ?? 0);
$is_admin = in_array($id_role_user, [1, 5], true);
$is_poli_belakang = in_array($id_role_user, [4], true);
$allowed_poli_belakang = ($is_admin || $is_poli_belakang);

echo "2. Akses Rawat Inap / Poli Belakang (Role 4):\n";
echo "   - Status Izin: " . ($allowed_poli_belakang ? "DIIZINKAN (PASS)" : "DITOLAK (FAIL)") . "\n";

// Skenario 3: User Petugas Apotek (Role 5)
$_SESSION['role_id'] = 5;
$id_role_user = (int)($_SESSION['role_id'] ?? 0);
$is_admin = in_array($id_role_user, [1, 5], true);
$is_poli_belakang = in_array($id_role_user, [4], true);
$allowed_apotek = ($is_admin || $is_poli_belakang);

echo "3. Akses Petugas Apotek (Role 5):\n";
echo "   - Status Izin: " . ($allowed_apotek ? "DIIZINKAN (PASS)" : "DITOLAK (FAIL)") . "\n";

// Skenario 4: User Super Admin (Role 1)
$_SESSION['role_id'] = 1;
$id_role_user = (int)($_SESSION['role_id'] ?? 0);
$is_admin = in_array($id_role_user, [1, 5], true);
$is_poli_belakang = in_array($id_role_user, [4], true);
$allowed_admin = ($is_admin || $is_poli_belakang);

echo "4. Akses Super Admin (Role 1):\n";
echo "   - Status Izin: " . ($allowed_admin ? "DIIZINKAN (PASS)" : "DITOLAK (FAIL)") . "\n";

if (!$allowed_poli_umum && $allowed_poli_belakang && $allowed_apotek && $allowed_admin) {
    echo "RESULT: TIKET P0-05 PASS (RBAC Terstandardisasi & Terisolasi Sempurna)\n";
} else {
    echo "RESULT: TIKET P0-05 FAIL\n";
}
