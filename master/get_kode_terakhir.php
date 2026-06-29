<?php
/**
 * get_kode_terakhir.php
 * 
 * Endpoint AJAX untuk mengambil kode obat terakhir (IFK & BLUD) dari database.
 * Dipanggil oleh data_obat.php via JavaScript fetch/AJAX.
 * 
 * Letakkan file ini di folder: master/get_kode_terakhir.php
 * 
 * Response JSON:
 * {
 *   "ifk":  { "last": "IFK-0523",  "next": "IFK-0524",  "nextNum": 524 },
 *   "blud": { "last": "BLUD-0045", "next": "BLUD-0046", "nextNum": 46  }
 * }
 */

require_once '../config.php';
require_once '../templates/auth_check.php';

header('Content-Type: application/json');

if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) {
    echo json_encode(['error' => 'Akses ditolak']);
    exit;
}

try {
    // --- IFK ---
    $sql_ifk = "SELECT o.kode_obat 
                FROM tbl_obat o
                JOIN tbl_kategori_obat k ON o.id_kategori_obat = k.id_kategori_obat
                JOIN tbl_jenis_obat j ON k.id_jenis_obat = j.id_jenis_obat
                WHERE j.nama_jenis_obat = 'IFK' AND o.kode_obat LIKE 'IFK-%'
                ORDER BY CAST(SUBSTRING_INDEX(o.kode_obat, '-', -1) AS UNSIGNED) DESC
                LIMIT 1";
    $last_ifk_row  = $pdo->query($sql_ifk)->fetch();
    $last_ifk_kode = $last_ifk_row ? $last_ifk_row['kode_obat'] : null;
    $next_ifk_num  = $last_ifk_row
        ? (int)preg_replace('/\D/', '', explode('-', $last_ifk_row['kode_obat'])[1] ?? '0') + 1
        : 1;
    $next_ifk_kode = 'IFK-' . str_pad($next_ifk_num, 4, '0', STR_PAD_LEFT);

    // --- BLUD ---
    $sql_blud = "SELECT o.kode_obat 
                 FROM tbl_obat o
                 JOIN tbl_kategori_obat k ON o.id_kategori_obat = k.id_kategori_obat
                 JOIN tbl_jenis_obat j ON k.id_jenis_obat = j.id_jenis_obat
                 WHERE j.nama_jenis_obat = 'BLUD' AND o.kode_obat LIKE 'BLUD-%'
                 ORDER BY CAST(SUBSTRING_INDEX(o.kode_obat, '-', -1) AS UNSIGNED) DESC
                 LIMIT 1";
    $last_blud_row  = $pdo->query($sql_blud)->fetch();
    $last_blud_kode = $last_blud_row ? $last_blud_row['kode_obat'] : null;
    $next_blud_num  = $last_blud_row
        ? (int)preg_replace('/\D/', '', explode('-', $last_blud_row['kode_obat'])[1] ?? '0') + 1
        : 1;
    $next_blud_kode = 'BLUD-' . str_pad($next_blud_num, 4, '0', STR_PAD_LEFT);

    echo json_encode([
        'ifk' => [
            'last'    => $last_ifk_kode,
            'next'    => $next_ifk_kode,
            'nextNum' => $next_ifk_num,
        ],
        'blud' => [
            'last'    => $last_blud_kode,
            'next'    => $next_blud_kode,
            'nextNum' => $next_blud_num,
        ],
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}