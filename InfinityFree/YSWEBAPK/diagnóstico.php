<?php
require_once 'config/database.php';
require_once 'config/funciones.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO COMPRESIÓN ===\n\n";

echo "PHP version: " . PHP_VERSION . "\n";
echo "GD: " . (function_exists('imagecreatefromjpeg') ? "SÍ ✓" : "NO ✗") . "\n";
echo "comprimirImagen existe: " . (function_exists('comprimirImagen') ? "SÍ ✓" : "NO ✗") . "\n";
echo "gdDisponible existe: " . (function_exists('gdDisponible') ? "SÍ" : "NO (usa comprimir.php viejo)") . "\n";
echo "Memory limit: " . ini_get('memory_limit') . "\n";
echo "Max execution: " . ini_get('max_execution_time') . "s\n";
echo "Memory usada ahora: " . round(memory_get_usage() / 1048576, 2) . " MB\n\n";

echo "=== CARPETAS ===\n";
foreach (['uploads/icons', 'uploads/screenshots', 'uploads/perfiles'] as $d) {
    if (!is_dir($d)) { echo "$d → NO EXISTE\n"; continue; }
    echo "$d → escribible: " . (is_writable($d) ? "SÍ ✓" : "NO ✗") . "\n";
}

echo "\n=== ARCHIVOS ===\n";
$archivos = array_merge(
    glob('uploads/icons/*.*') ?: [],
    glob('uploads/screenshots/*.*') ?: [],
    glob('uploads/perfiles/*.*') ?: []
);
echo "Total archivos: " . count($archivos) . "\n\n";

foreach (array_slice($archivos, 0, 5) as $archivo) {
    echo "--- $archivo ---\n";
    echo "  Existe: " . (file_exists($archivo) ? "SÍ" : "NO") . "\n";
    if (!file_exists($archivo)) { echo "\n"; continue; }

    $tam = filesize($archivo);
    echo "  Tamaño original: " . number_format($tam) . " bytes\n";

    $info = @getimagesize($archivo);
    echo "  getimagesize: " . ($info ? "OK ({$info[0]}x{$info[1]}, tipo {$info[2]})" : "FALLÓ ✗") . "\n";
    if (!$info) { echo "\n"; continue; }

    $tmp = $archivo . '.test_tmp';
    $t0 = microtime(true);
    $ok = comprimirImagen($archivo, $tmp, 80, 1200);
    $t1 = microtime(true);

    echo "  comprimirImagen: " . ($ok ? "OK ✓" : "FALLÓ ✗") . " (" . round(($t1-$t0)*1000) . " ms)\n";

    if ($ok && file_exists($tmp)) {
        $tam2 = filesize($tmp);
        echo "  Tamaño comprimido: " . number_format($tam2) . " bytes\n";
        echo "  Ahorro: " . ($tam2 < $tam ? number_format($tam - $tam2) . " bytes ✓" : "¡NINGUNO! (ya estaba optimizada) ⚠️") . "\n";
        @unlink($tmp);
    }
    echo "\n";
}

echo "=== FIN ===\n";