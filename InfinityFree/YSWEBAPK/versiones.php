<?php
// ============================================
// versiones.php — Devuelve el JSON de versiones
// Evita el "browser check" de InfinityFree
// ============================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$archivo = __DIR__ . '/versiones.json';

if (!file_exists($archivo)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'versiones.json no existe']);
    exit;
}

// Leer y devolver el JSON
$contenido = file_get_contents($archivo);

// Validar que sea JSON válido (opcional pero útil)
$data = json_decode($contenido, true);
if ($data === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
}

echo $contenido;