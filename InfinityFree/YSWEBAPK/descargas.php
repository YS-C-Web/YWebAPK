<?php
require_once 'config/database.php';
require_once 'config/funciones.php';

$apk_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($apk_id <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM apks WHERE id = ?");
$stmt->execute([$apk_id]);
$apk = $stmt->fetch();
if (!$apk) { header('Location: index.php'); exit; }

$link = $apk['link'] ?? '';

// Validación de seguridad: solo permitir archivos dentro de uploads/apks
$base = realpath(__DIR__ . '/uploads/apks');
$real = realpath($link);

if (!$base || !$real || strpos($real, $base) !== 0 || !is_file($real)) {
    header('Location: index.php?error=archivo_no_disponible');
    exit;
}

// Extensión segura
if (strtolower(pathinfo($real, PATHINFO_EXTENSION)) !== 'apk') {
    header('Location: index.php?error=archivo_no_valido');
    exit;
}

incrementarDescargas($apk_id, $_SESSION['usuario_id'] ?? null);

// Forzar descarga
header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="' . basename($real) . '"');
header('Content-Length: ' . filesize($real));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
readfile($real);
exit;