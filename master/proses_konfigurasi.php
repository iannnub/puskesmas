<?php

require_once '../config.php';


require_once '../templates/auth_check.php';


if ($_SESSION['role_id'] != 1) {
    header("Location: " . BASE_URL . "master/konfigurasi.php?status=gagal_akses");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    try {

        $sql = "INSERT INTO tbl_konfigurasi (setting_key, setting_value) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        
        $stmt = $pdo->prepare($sql);

        foreach ($_POST as $key => $value) {

            $setting_key = htmlspecialchars($key);
            $setting_value = htmlspecialchars($value);

            $stmt->execute([$setting_key, $setting_value]);
        }

        header("Location: " . BASE_URL . "master/konfigurasi.php?status=update_sukses");
        exit;

    } catch (Exception $e) {

        header("Location: " . BASE_URL . "master/konfigurasi.php?status=gagal&msg=" . urlencode($e->getMessage()));
        exit;
    }

} else {

    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}
?>