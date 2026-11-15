<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);   // solo si tu sitio fuerza HTTPS
ini_set('session.cookie_samesite', 'Lax'); // o 'Strict'
ini_set('session.use_strict_mode', 1);
error_reporting(E_ALL);
// ... el resto de tu database.php
// config/database.php
define('DB_HOST', 'sql106.infinityfree.com');
define('DB_NAME', 'if0_42511369_YWEBAPK');
define('DB_USER', 'if0_42511369');
define('DB_PASS', 'Yuniel031128');

// Detectar producción (cualquier host que no sea local)
$es_produccion = !in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1'], true);

if ($es_produccion) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
    // Intentar escribir log (si no existe la carpeta, se ignora)
    $log_dir = __DIR__ . '/../logs';
    if (is_dir($log_dir) && is_writable($log_dir)) {
        ini_set('error_log', $log_dir . '/php_errors.log');
    }
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    if ($es_produccion) {
        die('Error de conexión. Intenta más tarde.');
    }
    die('Error de conexión: ' . $e->getMessage());
}

// ===== SESIÓN SEGURA =====
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Rotar ID de sesión cada 30 min (anti session fixation)
    if (!isset($_SESSION['_created'])) {
        $_SESSION['_created'] = time();
    } elseif (time() - $_SESSION['_created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }
}