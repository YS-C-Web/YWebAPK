<?php
require_once 'config/database.php';
require_once 'config/funciones.php';

$ruta_xml = __DIR__ . '/sitemap.xml';

// ============================================================
// FUNCIÓN: genera el contenido XML del sitemap
// ============================================================
function generarSitemapXMLContenido($pdo) {
    try {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'ysw.infinityfree.me';
        $base = $protocol . '://' . $host;
        $hoy = date('Y-m-d');

        $urls = [
            ['loc' => "$base/",                     'priority' => '1.0', 'changefreq' => 'daily',   'lastmod' => $hoy],
            ['loc' => "$base/guia-instalacion.php", 'priority' => '0.8', 'changefreq' => 'monthly', 'lastmod' => $hoy],
            ['loc' => "$base/donar.php",            'priority' => '0.5', 'changefreq' => 'monthly', 'lastmod' => $hoy],
            ['loc' => "$base/terminos.php",         'priority' => '0.2', 'changefreq' => 'yearly',  'lastmod' => $hoy],
            ['loc' => "$base/privacidad.php",       'priority' => '0.2', 'changefreq' => 'yearly',  'lastmod' => $hoy],
        ];

        $stmt = $pdo->query("SELECT id, fecha FROM apks WHERE en_desarrollo = FALSE OR en_desarrollo IS NULL ORDER BY fecha DESC");
        while ($apk = $stmt->fetch()) {
            $lastmod = !empty($apk['fecha']) ? date('Y-m-d', strtotime($apk['fecha'])) : $hoy;
            $urls[] = [
                'loc'        => "$base/apk-detalle.php?id=" . (int)$apk['id'],
                'priority'   => '0.7',
                'changefreq' => 'weekly',
                'lastmod'    => $lastmod,
            ];
        }

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= "    <url>\n";
            $xml .= "        <loc>" . htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
            if (!empty($u['lastmod']))    $xml .= "        <lastmod>{$u['lastmod']}</lastmod>\n";
            if (!empty($u['changefreq'])) $xml .= "        <changefreq>{$u['changefreq']}</changefreq>\n";
            if (!empty($u['priority']))   $xml .= "        <priority>{$u['priority']}</priority>\n";
            $xml .= "    </url>\n";
        }
        $xml .= '</urlset>';

        return $xml;
    } catch (Exception $e) {
        return false;
    }
}

// ============================================================
// MODO OUTPUT: sirve el XML puro (usado por rewrite de .htaccess)
// Acceso directo: /sitemap.php?output=1
// Vía rewrite:    /sitemap.xml  →  sitemap.php?output=1
// ============================================================
if (isset($_GET['output'])) {
    header('Content-Type: application/xml; charset=utf-8');
    header('X-Robots-Tag: noindex');
    header('Cache-Control: public, max-age=3600');

    // Si no existe el archivo físico, lo generamos al vuelo
    if (!file_exists($ruta_xml)) {
        $xml = generarSitemapXMLContenido($pdo);
        if ($xml !== false) {
            @file_put_contents($ruta_xml, $xml, LOCK_EX);
            @chmod($ruta_xml, 0644);
            echo $xml;
            exit;
        }
        // Si falla la generación, devolver XML vacío válido
        http_response_code(500);
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
             '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
        exit;
    }

    // Servir el archivo existente
    readfile($ruta_xml);
    exit;
}

// ============================================================
// MODO ADMIN: UI para generar / regenerar / ver stats
// Acceso directo: /sitemap.php
// ============================================================
$error = '';
$exito = false;
$stats = ['total' => 0, 'apks' => 0, 'paginas' => 0, 'tamano' => 0, 'fecha' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' || !file_exists($ruta_xml) || isset($_GET['regen'])) {
    $xml = generarSitemapXMLContenido($pdo);
    if ($xml === false) {
        $error = 'Error al generar el contenido XML del sitemap.';
    } else {
        $bytes = @file_put_contents($ruta_xml, $xml, LOCK_EX);
        if ($bytes === false) {
            $error = 'No se pudo escribir el archivo. Verifica permisos de la carpeta raíz (chmod 755).';
        } else {
            @chmod($ruta_xml, 0644);
            $exito = true;
            $stats['tamano'] = $bytes;
            $stats['fecha']  = date('d/m/Y H:i:s');
            $stats['total']  = substr_count($xml, '<url>');
            $stats['apks']   = substr_count($xml, 'apk-detalle.php?id=');
            $stats['paginas'] = $stats['total'] - $stats['apks'];
        }
    }
} else {
    // Ya existe: leer stats
    $exito = true;
    $stats['tamano'] = filesize($ruta_xml);
    $stats['fecha']  = date('d/m/Y H:i:s', filemtime($ruta_xml));
    $contenido = @file_get_contents($ruta_xml);
    if ($contenido) {
        $stats['total']   = substr_count($contenido, '<url>');
        $stats['apks']    = substr_count($contenido, 'apk-detalle.php?id=');
        $stats['paginas'] = $stats['total'] - $stats['apks'];
    }
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'ysw.infinityfree.me');
$url_publica = $base_url . '/sitemap.xml';
$url_output  = $base_url . '/sitemap.php?output=1';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar sitemap.xml - YSWEBAPK</title>
    <link rel="icon" href="Ico.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem; overflow-x: hidden; }
        .result-card {
            background: var(--bg-card);
            border-radius: 24px;
            padding: 2rem 1.5rem;
            max-width: 600px;
            width: 100%;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            word-wrap: break-word;
        }
        .result-card .icon { font-size: 4rem; text-align: center; margin-bottom: 0.5rem; }
        .result-card .icon.ok  { color: var(--success); }
        .result-card .icon.err { color: var(--danger); }
        .result-card h1 { text-align: center; font-size: 1.5rem; margin-bottom: 0.5rem; }
        .result-card > p { text-align: center; color: var(--text-secondary); margin-bottom: 1.2rem; font-size: 0.92rem; }
        .info-box {
            background: var(--bg-hover);
            border-radius: 12px;
            padding: 1rem;
            margin: 1rem 0;
            border: 1px solid var(--border-color);
            font-size: 0.88rem;
        }
        .info-box .row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.4rem 0;
            border-bottom: 1px solid var(--border-color);
            gap: 1rem;
        }
        .info-box .row:last-child { border-bottom: none; }
        .info-box .row strong { color: var(--text-primary); flex-shrink: 0; }
        .info-box .row span { color: var(--text-secondary); text-align: right; word-break: break-word; }
        .info-box .row span code {
            background: var(--bg-card);
            padding: 0.1rem 0.4rem;
            border-radius: 4px;
            font-size: 0.82rem;
        }
        .result-card .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.65rem 1.1rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.88rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            white-space: nowrap;
        }
        .result-card .btn-primary { background: var(--primary); color: white; }
        .result-card .btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px); }
        .result-card .btn-secondary { background: var(--bg-hover); color: var(--text-primary); border: 1px solid var(--border-color); }
        .result-card .btn-secondary:hover { background: var(--border-color); }
        .actions-row {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 1rem;
        }
        .link-view {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            margin-top: 0.5rem;
            padding: 0.7rem;
            background: var(--success-bg);
            border: 1px solid var(--success);
            border-radius: 10px;
            color: var(--success);
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            word-break: break-all;
            text-align: center;
        }
        .link-view:hover { background: var(--success); color: white; }
        .link-view.alt {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
            margin-top: 0.4rem;
        }
        .link-view.alt:hover { background: var(--primary); color: white; }
        .warning-box {
            background: var(--warning-bg);
            border: 1px solid var(--warning);
            color: var(--warning);
            padding: 0.8rem 1rem;
            border-radius: 10px;
            margin: 1rem 0;
            font-size: 0.85rem;
            line-height: 1.55;
        }
        .warning-box code {
            background: var(--bg-card);
            padding: 0.1rem 0.35rem;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        .warning-box a { color: var(--warning); font-weight: 700; text-decoration: underline; }
        @media (max-width: 480px) {
            .result-card { padding: 1.5rem 1rem; border-radius: 18px; }
            .result-card h1 { font-size: 1.2rem; }
            .result-card .icon { font-size: 3rem; }
            .info-box { font-size: 0.82rem; padding: 0.8rem; }
            .info-box .row { flex-direction: column; align-items: flex-start; gap: 0.2rem; }
            .info-box .row span { text-align: left; }
            .result-card .btn { padding: 0.55rem 0.9rem; font-size: 0.82rem; }
        }
    </style>
</head>
<body>

<div class="result-card">
    <?php if ($error): ?>
        <div class="icon err"><i class="fas fa-times-circle"></i></div>
        <h1>Error al generar sitemap</h1>
        <p><?= escapar($error) ?></p>
        <div class="warning-box">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Posibles causas:</strong>
            <ul style="margin:.4rem 0 0 1rem;padding:0;">
                <li>Sin permisos de escritura en la carpeta raíz</li>
                <li>El archivo <code>sitemap.xml</code> ya existe y es de solo lectura</li>
                <li>El disco está lleno</li>
            </ul>
            <p style="margin-top:.5rem;">Solución: por FTP o el panel de InfinityFree, cambia los permisos de la carpeta raíz a <strong>755</strong> (o borra <code>sitemap.xml</code> si existe).</p>
        </div>

    <?php elseif ($exito): ?>
        <div class="icon ok"><i class="fas fa-check-circle"></i></div>
        <h1><?= (isset($_GET['regen']) || $_SERVER['REQUEST_METHOD'] === 'POST') ? '¡Sitemap generado!' : 'Sitemap activo' ?></h1>
        <p>El archivo <strong>sitemap.xml</strong> está listo y se sirve correctamente a través de PHP.</p>

        <div class="info-box">
            <div class="row"><strong>URLs totales</strong><span><code><?= $stats['total'] ?></code></span></div>
            <div class="row"><strong>APKs incluidas</strong><span><code><?= $stats['apks'] ?></code></span></div>
            <div class="row"><strong>Páginas estáticas</strong><span><code><?= $stats['paginas'] ?></code></span></div>
            <div class="row"><strong>Tamaño</strong><span><?= number_format($stats['tamano'] / 1024, 2) ?> KB</span></div>
            <div class="row"><strong>Generado</strong><span><?= escapar($stats['fecha']) ?></span></div>
        </div>

        <a href="<?= escapar($url_publica) ?>" target="_blank" class="link-view">
            <i class="fas fa-external-link-alt"></i> Ver sitemap.xml público
        </a>
        <a href="<?= escapar($url_output) ?>" target="_blank" class="link-view alt">
            <i class="fas fa-code"></i> Ver XML directo (sitemap.php?output=1)
        </a>

        <div class="warning-box">
            <i class="fas fa-info-circle"></i>
            <strong>Envía el sitemap a Google</strong> desde <a href="https://search.google.com/search-console" target="_blank">Google Search Console</a>.
            Debes verificar que <code>sitemap.xml</code> devuelva XML puro (no la página admin). Si Google muestra error, revisa que el <code>.htaccess</code> tenga el rewrite correcto.
        </div>

        <div class="actions-row">
            <a href="sitemap.php?regen=1" class="btn btn-primary">
                <i class="fas fa-sync-alt"></i> Regenerar
            </a>
            <a href="admin.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Admin
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Inicio
            </a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>