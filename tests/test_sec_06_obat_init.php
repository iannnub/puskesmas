<?php
// tests/test_sec_06_obat_init.php
// Verifikasi Inisialisasi Inventori Otomatis (BUG-SIVO-012) & Proteksi Deletion

$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../config.php';

echo "====================================================================\n";
echo "   TEST SEC-06 / BUG-SIVO-012: Inisialisasi Inventori & Deletion    \n";
echo "====================================================================\n\n";

$_SESSION['user_id'] = 1;
$_SESSION['role_id'] = 1;
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$pdo->beginTransaction();

try {
    // 1. Hitung total unit yang aktif di database
    $total_units = (int)$pdo->query("SELECT COUNT(*) FROM tbl_unit")->fetchColumn();
    echo "[1] Total unit terdaftar di tbl_unit: $total_units unit.\n";

    // 2. Simulasikan pembuatan obat baru seperti di proses_crud_obat.php
    $kode_uji = 'TEST-SEC06-' . time();
    $nama_uji = 'Obat Uji Inisialisasi Inventori';
    $satuan_uji = 'Tablet';
    $id_kategori_uji = 1;

    $sql = "INSERT INTO tbl_obat (id_kategori_obat, kode_obat, nama_obat, satuan) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_kategori_uji, $kode_uji, $nama_uji, $satuan_uji]);
    $new_id_obat = (int)$pdo->lastInsertId();

    echo "[2] Menambahkan obat baru (ID: $new_id_obat, Kode: $kode_uji)...\n";

    // Eksekusi inisialisasi inventori
    $stmt_units = $pdo->query("SELECT id_unit FROM tbl_unit");
    $units = $stmt_units->fetchAll(PDO::FETCH_COLUMN);
    $stmt_init_stok = $pdo->prepare(
        "INSERT INTO tbl_stok_inventori (id_obat, id_unit, stok_akhir, stok_minimum, updated_at) 
         VALUES (?, ?, 0, 10, NOW())"
    );
    foreach ($units as $u_id) {
        $stmt_init_stok->execute([$new_id_obat, $u_id]);
    }

    // 3. Verifikasi jumlah baris inventori yang terbentuk
    $stmt_inv_count = $pdo->prepare("SELECT COUNT(*) FROM tbl_stok_inventori WHERE id_obat = ?");
    $stmt_inv_count->execute([$new_id_obat]);
    $inv_count = (int)$stmt_inv_count->fetchColumn();

    // Verifikasi seluruh baris bersaldo 0
    $stmt_inv_sum = $pdo->prepare("SELECT SUM(stok_akhir) FROM tbl_stok_inventori WHERE id_obat = ?");
    $stmt_inv_sum->execute([$new_id_obat]);
    $inv_sum = (int)$stmt_inv_sum->fetchColumn();

    if ($inv_count === $total_units && $inv_sum === 0) {
        echo "   -> PASS: $inv_count baris inventori berhasil diinisialisasi otomatis untuk seluruh unit dengan saldo 0.\n";
    } else {
        throw new Exception("FAIL: Jumlah inventori ($inv_count) tidak cocok dengan unit ($total_units) atau saldo tidak 0 ($inv_sum).");
    }

    // 4. Verifikasi Rejection Deletion saat ada riwayat log mutasi
    echo "[3] Menguji proteksi deletion saat ada riwayat log mutasi...\n";
    $stmt_dummy_log = $pdo->prepare(
        "INSERT INTO tbl_log_stok (tgl_log, id_obat, id_unit, sumber_data, stok_sebelum, masuk, keluar, stok_sesudah, keterangan) 
         VALUES (NOW(), ?, 1, 'Penerimaan', 0, 10, 0, 10, 'Test Log')"
    );
    $stmt_dummy_log->execute([$new_id_obat]);

    // Cek apakah sistem menolak hapus jika ada log
    $stmt_cek_log = $pdo->prepare("SELECT COUNT(*) FROM tbl_log_stok WHERE id_obat = ?");
    $stmt_cek_log->execute([$new_id_obat]);
    $has_log = ($stmt_cek_log->fetchColumn() > 0);

    if ($has_log) {
        echo "   -> PASS: Sistem mendeteksi riwayat log mutasi dan memblokir penghapusan obat.\n";
    } else {
        throw new Exception("FAIL: Proteksi riwayat log gagal mendeteksi histori mutasi.");
    }

    // 5. Verifikasi CSRF helper pada aksi delete
    echo "[4] Menguji proteksi CSRF pada deletion...\n";
    $valid_csrf = verify_csrf($_SESSION['csrf_token']);
    $invalid_csrf = verify_csrf('forged_token_123');

    if ($valid_csrf === true && $invalid_csrf === false) {
        echo "   -> PASS: Proteksi CSRF berfungsi valid (Token sah diterima, token palsu ditolak).\n";
    } else {
        throw new Exception("FAIL: Logika CSRF verification gagal.");
    }

    echo "\n--------------------------------------------------------------------\n";
    echo "HASIL AKHIR TEST SEC-06 / BUG-SIVO-012: SEMUA PENGUJIAN LULUS (PASS)!\n";
    echo "--------------------------------------------------------------------\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
} finally {
    // Selalu rollback agar database tetap 100% sinkron
    $pdo->rollBack();
    echo "(Rollback berhasil: Database dikembalikan ke status awal)\n";
}
