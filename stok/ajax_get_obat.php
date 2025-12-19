<?php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. Panggil "satpam" auth_check.php
// (PENTING! Melindungi database obat Anda dari scraping)
require_once '../templates/auth_check.php';

// 3. Set header sebagai JSON
header('Content-Type: application/json');

// 4. Ambil parameter 'q' (ketikan user) dari Select2
$q = $_GET['q'] ?? '';

if (strlen($q) < 2) {
    // Jangan cari jika ketikan kurang dari 2 huruf
    echo json_encode([]);
    exit;
}

$search_term = '%' . $q . '%';

try {
    // 5. Query database (Sesuai logic Anda)
    $stmt = $pdo->prepare("
        SELECT id_obat, kode_obat, nama_obat 
        FROM tbl_obat
        WHERE nama_obat LIKE ? OR kode_obat LIKE ?
        ORDER BY nama_obat ASC
        LIMIT 20
    ");
    $stmt->execute([$search_term, $search_term]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Format data untuk Select2 (Sesuai logic Anda)
    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            'id' => $r['id_obat'],
            'text' => $r['kode_obat'] . ' - ' . $r['nama_obat']
        ];
    }

    // 7. Kembalikan data sebagai JSON
    echo json_encode($data);

} catch (PDOException $e) {
    // Jika error, kembalikan JSON kosong
    echo json_encode([]);
}
?>