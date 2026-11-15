<?php
// ============================================
// config/generar_versiones.php
// Función para regenerar versiones.json
// ============================================

function generarVersionesJson($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT id, nombre, version, link, tamano, fecha, categoria, en_desarrollo
            FROM apks
            ORDER BY id ASC
        ");
        $apks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Detectar dominio
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base_url = $protocol . '://' . $host;

        $versiones = [];
        foreach ($apks as $app) {
            if (!empty($app['en_desarrollo'])) continue;

            $versiones[] = [
                'id'        => (int)$app['id'],
                'nombre'    => $app['nombre'],
                'version'   => $app['version'],
                'link'      => $base_url . '/descargas.php?id=' . (int)$app['id'],
                'tamano'    => $app['tamano'],
                'fecha'     => $app['fecha'],
                'categoria' => $app['categoria']
            ];
        }

        $data = [
            'ok'       => true,
            'generado' => date('c'),
            'total'    => count($versiones),
            'apps'     => $versiones
        ];

        $ruta = __DIR__ . '/../versiones.json';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (file_put_contents($ruta, $json) === false) {
            return ['ok' => false, 'error' => 'No se pudo escribir versiones.json'];
        }

        return [
            'ok'     => true,
            'total'  => count($versiones),
            'ruta'   => $ruta,
            'fecha'  => date('d/m/Y H:i:s')
        ];

    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}