<?php
// ====================================================================
// SIVO Puskesmas - Configuration & Database Initialization
// Environment-Aware, CLI-Safe, and Hardened against Information Leaks
// ====================================================================

// 1. Session Hardening
if (session_status() === PHP_SESSION_NONE) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// 2. Simple Native .env Loader (No external dependencies)
$env_path = __DIR__ . '/.env';
if (file_exists($env_path) && is_readable($env_path)) {
    $env_lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env_lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($env_name, $env_val) = explode('=', $line, 2);
            $env_name = trim($env_name);
            $env_val  = trim($env_val);
            if ((strpos($env_val, '"') === 0 && strrpos($env_val, '"') === strlen($env_val) - 1) ||
                (strpos($env_val, "'") === 0 && strrpos($env_val, "'") === strlen($env_val) - 1)) {
                $env_val = substr($env_val, 1, -1);
            }
            if (!array_key_exists($env_name, $_SERVER) && !array_key_exists($env_name, $_ENV)) {
                putenv("$env_name=$env_val");
                $_ENV[$env_name] = $env_val;
                $_SERVER[$env_name] = $env_val;
            }
        }
    }
}

// 3. Environment & Host Detection (CLI Safe)
$is_cli = (php_sapi_name() === 'cli' || empty($_SERVER['HTTP_HOST']));
$http_host = $is_cli ? 'localhost' : $_SERVER['HTTP_HOST'];
$is_localhost = ($is_cli || $http_host === 'localhost' || $http_host === '127.0.0.1' || strpos($http_host, 'localhost') !== false);

// 4. Base URL Configuration
if (!defined('BASE_URL')) {
    $env_base_url = getenv('BASE_URL');
    if ($env_base_url !== false && $env_base_url !== '') {
        define('BASE_URL', rtrim($env_base_url, '/') . '/');
    } else {
        define('BASE_URL', $is_localhost ? '/puskesmas/' : '/');
    }
}

// 5. Database Connection Parameters (Zero hardcoded credentials)
$db_host = getenv('DB_HOST') ?: ($is_localhost ? '127.0.0.1' : 'localhost');
$db_port = getenv('DB_PORT') ?: '3306';
$db_name = getenv('DB_NAME') ?: ($is_localhost ? 'dbpuskesmas' : '');
$db_user = getenv('DB_USER') ?: ($is_localhost ? 'root' : '');
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$charset = 'utf8mb4';

// 6. PDO Initialization
$dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // True prepared statements
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
    error_log("[SIVO DB ERROR] " . $e->getMessage());
    if (!$is_cli) {
        http_response_code(500);
        die("Kesalahan koneksi ke database. Silakan periksa konfigurasi lingkungan (.env) atau hubungi administrator.");
    } else {
        die("CLI Database Connection Error: " . $e->getMessage() . "\n");
    }
}

// 7. CSRF Protection Helpers
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $token = null): bool {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
        }
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
?>