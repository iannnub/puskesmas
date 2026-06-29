<?php

require_once '../config.php';


require_once '../templates/auth_check.php';


header('Content-Type: application/json');


$q = $_GET['q'] ?? '';

if (strlen($q) < 2) {
    
    echo json_encode([]);
    exit;
}

$search_term = '%' . $q . '%';

try {
    
    $stmt = $pdo->prepare("
        SELECT id_obat, kode_obat, nama_obat 
        FROM tbl_obat
        WHERE nama_obat LIKE ? OR kode_obat LIKE ?
        ORDER BY nama_obat ASC
        LIMIT 20
    ");
    $stmt->execute([$search_term, $search_term]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            'id' => $r['id_obat'],
            'text' => $r['kode_obat'] . ' - ' . $r['nama_obat']
        ];
    }


    echo json_encode($data);

} catch (PDOException $e) {
   
    echo json_encode([]);
}
?>