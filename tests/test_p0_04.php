<?php
// Note: $_SERVER['HTTP_HOST'] is intentionally NOT set here to test pure CLI environment
require_once __DIR__ . '/../config.php';

echo "=== VERIFIKASI TIKET P0-04: CREDENTIAL HARDENING & CLI DETECTION ===\n";

echo "1. CLI Detection: " . ($is_cli ? "PASS (CLI Terdeteksi)" : "FAIL") . "\n";
echo "2. Localhost Detection: " . ($is_localhost ? "PASS (Local Terdeteksi)" : "FAIL") . "\n";
echo "3. BASE_URL Defined: " . (defined('BASE_URL') ? "PASS (" . BASE_URL . ")" : "FAIL") . "\n";
echo "4. Environment Source: DB_HOST=" . getenv('DB_HOST') . ", DB_NAME=" . getenv('DB_NAME') . "\n";

// Verifikasi koneksi database aktif
$version = $pdo->query("SELECT VERSION()")->fetchColumn();
echo "5. Database Live Connection: PASS (MariaDB/MySQL Version: $version)\n";

// Verifikasi bahwa ATTR_EMULATE_PREPARES adalah false
$emulate = (bool)$pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
echo "6. PDO ATTR_EMULATE_PREPARES: " . ($emulate === false ? "PASS (False - Native Prepared Statements)" : "FAIL ($emulate)") . "\n";

// Verifikasi tidak ada hardcoded credentials di config.php
$config_code = file_get_contents(__DIR__ . '/../config.php');
$has_infinity = (strpos($config_code, 'infinityfree') !== false || strpos($config_code, 'm14s4m14123') !== false);
echo "7. Zero Hardcoded Credentials: " . (!$has_infinity ? "PASS (Bersih dari password produksi)" : "FAIL") . "\n";

if ($is_cli && $is_localhost && defined('BASE_URL') && $version && $emulate === false && !$has_infinity) {
    echo "RESULT: TIKET P0-04 PASS\n";
} else {
    echo "RESULT: TIKET P0-04 FAIL\n";
}
