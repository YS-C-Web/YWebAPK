<?php
// config/funciones.php
require_once __DIR__ . '/database.php';

if (file_exists(__DIR__ . '/comprimir.php')) {
    require_once __DIR__ . '/comprimir.php';
}
function estaLogueado() { return isset($_SESSION['usuario_id']); }
function esAdmin() { return isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'; }
function esDesarrollador() { return isset($_SESSION['rol']) && ($_SESSION['rol'] === 'desarrollador' || $_SESSION['rol'] === 'admin'); }

function obtenerUsuario($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function obtenerUsuarioActual() {
    if (!estaLogueado()) return null;
    return obtenerUsuario($_SESSION['usuario_id']);
}

function generarToken() { return bin2hex(random_bytes(32)); }
function limpiarEntrada($input) { return trim(strip_tags((string)$input)); }
function escapar($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
function sanitizar($input) { return limpiarEntrada($input); }

// ============ CSRF ============
function generarCSRF() {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function validarCSRF($token) { return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token); }
function csrfField() { return '<input type="hidden" name="csrf_token" value="' . generarCSRF() . '">'; }

// ============ DETECTAR APP ANDROID ============
function esAppAndroid() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Detecta si es tu app personalizada o un WebView de App Inventor
    if (stripos($ua, 'YSWEBAPKApp') !== false) return true;
    if (stripos($ua, 'AppInventor') !== false) return true;
    
    // Detecta WebViews genéricos de Android (contienen "; wv")
    if (stripos($ua, 'wv') !== false && stripos($ua, 'Android') !== false) return true;
    
    return false;
}

// ============ VIRUSTOTAL API ============
function escanearVirusTotal($ruta_archivo, $api_key) {
    if (empty($api_key) || !file_exists($ruta_archivo)) return 'error_api';
    
    // 1. Subir archivo
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.virustotal.com/api/v3/files");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => new CURLFile($ruta_archivo)]);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["x-apikey: $api_key"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // A veces InfinityFree bloquea SSL
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $data = json_decode($response, true);
        $analysis_id = $data['data']['id'] ?? null;
        if (!$analysis_id) return 'error_datos';

        sleep(15); 

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://www.virustotal.com/api/v3/analyses/$analysis_id");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["x-apikey: $api_key"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        curl_close($ch);

        $res_data = json_decode($res, true);
        $stats = $res_data['data']['attributes']['stats'] ?? [];
        
        $maliciosos = $stats['malicious'] ?? 0;
        $sospechosos = $stats['suspicious'] ?? 0;

        if ($maliciosos === 0 && $sospechosos === 0) {
            return 'limpio (0 detecciones)';
        } else {
            return "peligro ($maliciosos detecciones)";
        }
    }
    return 'error_http_' . $http_code;
}

// ============ RATE LIMIT, ARCHIVOS, ETC ============
function verificarRateLimit($accion, $max_intentos = 5, $ventana_min = 15) {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    try {
        $stmt = $pdo->prepare("SELECT intentos, ultimo_intento FROM rate_limits WHERE ip = ? AND accion = ?");
        $stmt->execute([$ip, $accion]);
        $row = $stmt->fetch();
        if ($row) {
            if (time() - strtotime($row['ultimo_intento']) > $ventana_min * 60) {
                $pdo->prepare("UPDATE rate_limits SET intentos = 1, ultimo_intento = NOW() WHERE ip = ? AND accion = ?")->execute([$ip, $accion]);
                return true;
            }
            if ((int)$row['intentos'] >= $max_intentos) return false;
            $pdo->prepare("UPDATE rate_limits SET intentos = intentos + 1 WHERE ip = ? AND accion = ?")->execute([$ip, $accion]);
        } else {
            $pdo->prepare("INSERT INTO rate_limits (ip, accion) VALUES (?, ?)")->execute([$ip, $accion]);
        }
        return true;
    } catch (Exception $e) { return true; }
}

function validarArchivoSubido($file, $tipo, $max_mb = 10) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'Error de subida'];
    if ($file['size'] > $max_mb * 1024 * 1024) return ['ok' => false, 'error' => "El archivo supera el límite de {$max_mb}MB"];
    if ($file['size'] === 0) return ['ok' => false, 'error' => 'El archivo está vacío'];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($tipo === 'apk') {
        if (!in_array($mime, ['application/vnd.android.package-archive', 'application/zip', 'application/octet-stream'], true)) {
            return ['ok' => false, 'error' => 'El archivo no es un APK válido'];
        }
    } elseif ($tipo === 'imagen') {
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return ['ok' => false, 'error' => 'Formato de imagen no permitido'];
        }
    }
    return ['ok' => true, 'mime' => $mime];
}

function obtenerExtensionSegura($nombre, $mime) {
    $mapa = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    if (isset($mapa[$mime])) return $mapa[$mime];
    return 'bin';
}

function crearNotificacion($uid, $tipo, $titulo, $msg) {
    global $pdo;
    $pdo->prepare("INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje) VALUES (?, ?, ?, ?)")->execute([$uid, $tipo, $titulo, $msg]);
}
function contarNotificacionesNoLeidas($uid) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = FALSE");
    $stmt->execute([$uid]);
    return (int)$stmt->fetchColumn();
}

function calcularPromedioEstrellas($apk_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT AVG(estrellas) FROM valoraciones WHERE apk_id = ?");
    $stmt->execute([$apk_id]);
    return round((float)$stmt->fetchColumn(), 1);
}
function actualizarPromedioEstrellas($apk_id) {
    global $pdo;
    $prom = calcularPromedioEstrellas($apk_id);
    $pdo->prepare("UPDATE apks SET promedio_estrellas = ? WHERE id = ?")->execute([$prom, $apk_id]);
    return $prom;
}
function incrementarDescargas($apk_id, $usuario_id = null) {
    global $pdo;

    // 1) PRIMERO lo crítico: subir el contador (esto debe pasar SIEMPRE)
    try {
        $pdo->prepare("UPDATE apks SET descargas = descargas + 1 WHERE id = ?")
            ->execute([$apk_id]);
    } catch (Exception $e) {
        error_log('Error al incrementar descargas: ' . $e->getMessage());
    }

    // 2) DESPUÉS el detalle (opcional, no crítico)
    //    Si falla (por ejemplo, usuario_id NOT NULL), no pasa nada:
    //    el contador ya subió y el archivo se sirve igual.
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $pdo->prepare("INSERT INTO descargas (apk_id, usuario_id, ip) VALUES (?, ?, ?)")
            ->execute([$apk_id, $usuario_id, $ip]);
    } catch (Exception $e) {
        error_log('No se pudo registrar detalle de descarga: ' . $e->getMessage());
    }
}
function obtenerCategoriaLabel($cat) {
    $l = ['juego'=>'Juegos','utilidad'=>'Utilidades','educación'=>'Educación','social'=>'Social','entretenimiento'=>'Entretenimiento'];
    return $l[$cat] ?? $cat;
}
function tiempoRelativo($fecha) {
    $d = time() - strtotime($fecha);
    if ($d < 60) return 'ahora mismo';
    if ($d < 3600) return floor($d / 60) . ' min';
    if ($d < 86400) return floor($d / 3600) . ' h';
    if ($d < 604800) return floor($d / 86400) . ' d';
    return date('d/m/Y', strtotime($fecha));
}
function calcularPaginacion($total, $por_pagina, $actual) {
    $tp = max(1, (int)ceil($total / $por_pagina));
    $actual = max(1, min((int)$actual, $tp));
    return ['total'=>(int)$total,'por_pagina'=>(int)$por_pagina,'pagina_actual'=>$actual,'total_paginas'=>$tp,'offset'=>($actual-1)*$por_pagina,'hay_anterior'=>$actual>1,'hay_siguiente'=>$actual<$tp];
}
function obtenerCategoriasDisponibles() {
    return ['juego'=>'Juegos','utilidad'=>'Utilidades','educación'=>'Educación','social'=>'Social','entretenimiento'=>'Entretenimiento'];
}
function renderMetaSEO($opciones = []) {
    $defaults = [
        'title'       => 'YSWEBAPK',
        'description' => '',
        'canonical'   => '',
        'image'       => 'IcoYSWEBAPK.jpg',
        'type'        => 'website',
        'robots'      => 'index, follow',
        'keywords'    => '',
    ];
    $o = array_merge($defaults, $opciones);

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'ysw.infinityfree.me');

    if (empty($o['canonical'])) {
        $o['canonical'] = $base . ($_SERVER['REQUEST_URI'] ?? '/');
    }
    if (!preg_match('#^https?://#i', $o['image'])) {
        $o['image'] = $base . '/' . ltrim($o['image'], '/');
    }

    $t  = htmlspecialchars($o['title'], ENT_QUOTES, 'UTF-8');
    $d  = htmlspecialchars($o['description'], ENT_QUOTES, 'UTF-8');
    $c  = htmlspecialchars($o['canonical'], ENT_QUOTES, 'UTF-8');
    $i  = htmlspecialchars($o['image'], ENT_QUOTES, 'UTF-8');
    $r  = htmlspecialchars($o['robots'], ENT_QUOTES, 'UTF-8');
    $k  = htmlspecialchars($o['keywords'], ENT_QUOTES, 'UTF-8');
    $ty = htmlspecialchars($o['type'], ENT_QUOTES, 'UTF-8');

    echo "<title>{$t}</title>\n";
    if ($d) echo "<meta name=\"description\" content=\"{$d}\">\n";
    if ($k) echo "<meta name=\"keywords\" content=\"{$k}\">\n";
    echo "<meta name=\"robots\" content=\"{$r}\">\n";
    echo "<link rel=\"canonical\" href=\"{$c}\">\n";
    echo "<meta property=\"og:site_name\" content=\"YSWEBAPK\">\n";
    echo "<meta property=\"og:title\" content=\"{$t}\">\n";
    if ($d) echo "<meta property=\"og:description\" content=\"{$d}\">\n";
    echo "<meta property=\"og:url\" content=\"{$c}\">\n";
    echo "<meta property=\"og:image\" content=\"{$i}\">\n";
    echo "<meta property=\"og:type\" content=\"{$ty}\">\n";
    echo "<meta name=\"twitter:card\" content=\"summary_large_image\">\n";
    echo "<meta name=\"twitter:title\" content=\"{$t}\">\n";
    if ($d) echo "<meta name=\"twitter:description\" content=\"{$d}\">\n";
    echo "<meta name=\"twitter:image\" content=\"{$i}\">\n";
}
function regenerarSitemap() {
    $ruta = __DIR__ . '/../sitemap.xml';
    try {
        global $pdo;
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
            $urls[] = ['loc' => "$base/apk-detalle.php?id=" . (int)$apk['id'], 'priority' => '0.7', 'changefreq' => 'weekly', 'lastmod' => $lastmod];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= "    <url>\n        <loc>" . htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
            if (!empty($u['lastmod']))    $xml .= "        <lastmod>{$u['lastmod']}</lastmod>\n";
            if (!empty($u['changefreq'])) $xml .= "        <changefreq>{$u['changefreq']}</changefreq>\n";
            if (!empty($u['priority']))   $xml .= "        <priority>{$u['priority']}</priority>\n";
            $xml .= "    </url>\n";
        }
        $xml .= '</urlset>';

        return @file_put_contents($ruta, $xml, LOCK_EX) !== false;
    } catch (Exception $e) {
        return false;
    }
}