<?php
// ============================================
// API BLINDADA - Siempre devuelve JSON válido
// ============================================

ob_start();
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$log_dir = __DIR__ . '/logs';
if (is_dir($log_dir) && is_writable($log_dir)) {
    ini_set('error_log', $log_dir . '/api_errors.log');
}

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

set_exception_handler(function ($e) {
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success'      => false,
        'message'      => 'Error del servidor',
        'error_detail' => $e->getMessage() . ' en ' . basename($e->getFile()) . ':' . $e->getLine()
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code(500);
        echo json_encode([
            'success'      => false,
            'message'      => 'Error fatal',
            'error_detail' => $err['message']
        ]);
    }
});

function emitirJSON($data, $status = 200) {
    http_response_code($status);
    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

require_once 'config/database.php';
require_once 'config/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    emitirJSON(['success' => false, 'message' => 'Método no permitido'], 405);
}

$action = $_POST['action'] ?? '';

// ACCIÓN: REGISTRAR DESCARGA (Para el contador desde la App)
if ($action === 'registrar_descarga') {
    $apk_id = intval($_POST['apk_id'] ?? 0);
    if ($apk_id > 0) {
        $pdo->prepare("UPDATE apks SET descargas = descargas + 1 WHERE id = ?")->execute([$apk_id]);
        emitirJSON(['success' => true]);
    }
    emitirJSON(['success' => false]);
}

// ACCIÓN: ESCANEAR CON VIRUSTOTAL (Solo Admin)
if ($action === 'scan_virustotal') {
    if (!esAdmin()) emitirJSON(['success' => false, 'message' => 'No autorizado']);
    if (!validarCSRF($_POST['csrf_token'] ?? '')) emitirJSON(['success' => false, 'message' => 'Token CSRF inválido']);

    $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT archivo_apk FROM solicitudes_apk WHERE id = ?");
    $stmt->execute([$solicitud_id]);
    $sol = $stmt->fetch();

    if (!$sol) emitirJSON(['success' => false, 'message' => 'Solicitud no encontrada']);

    $archivo = $sol['archivo_apk'];
    if (!file_exists($archivo)) emitirJSON(['success' => false, 'message' => 'El archivo físico APK no existe o ya fue procesado.']);

    define('VIRUSTOTAL_API_KEY', 'ec052c3616d9e94ec8eb8f08657fe34b1497c8e49cd583a9713ffb201cb55156');

    if (VIRUSTOTAL_API_KEY === 'TU_API_KEY_AQUI') {
        emitirJSON(['success' => false, 'message' => 'Falta configurar la API KEY de VirusTotal en api.php']);
    }

    $resultado = escanearVirusTotal($archivo, VIRUSTOTAL_API_KEY);

    $pdo->prepare("UPDATE solicitudes_apk SET virustotal_resultado = ? WHERE id = ?")->execute([$resultado, $solicitud_id]);

    emitirJSON([
        'success' => true,
        'resultado' => $resultado
    ]);
}

// ACCIÓN: COMPRIMIR UNA IMAGEN (Admin, una por petición → evita timeouts)
if ($action === 'comprimir_imagen') {
    if (!esAdmin()) emitirJSON(['success' => false, 'message' => 'No autorizado']);
    if (!validarCSRF($_POST['csrf_token'] ?? '')) emitirJSON(['success' => false, 'message' => 'Token CSRF inválido']);

    if (!function_exists('comprimirImagen') || !function_exists('gdDisponible') || !gdDisponible()) {
        emitirJSON(['success' => false, 'message' => 'La librería GD no está activa. Contacta al hosting.']);
    }

    @set_time_limit(120);
    @ini_set('memory_limit', '256M');

    $archivo_rel = trim($_POST['archivo'] ?? '');
    $calidad     = max(30, min(95, intval($_POST['calidad'] ?? 80)));
    $max_ancho   = max(200, min(4000, intval($_POST['max_ancho'] ?? 1200)));

    if ($archivo_rel === '') emitirJSON(['success' => false, 'message' => 'Archivo vacío']);

    $base = realpath(__DIR__);
    if (!$base) $base = __DIR__;
    $real = @realpath($base . '/' . ltrim($archivo_rel, '/\\'));
    if (!$real) emitirJSON(['success' => false, 'message' => 'El archivo no existe o no es accesible']);
    if (strpos($real, $base) !== 0) emitirJSON(['success' => false, 'message' => 'Ruta inválida']);

    $carpetas_ok = [
        $base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'icons',
        $base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'screenshots',
        $base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'perfiles',
    ];
    $permitido = false;
    foreach ($carpetas_ok as $dir) {
        if (strpos($real, $dir) === 0) { $permitido = true; break; }
    }
    if (!$permitido) emitirJSON(['success' => false, 'message' => 'Carpeta no permitida']);
    if (!is_file($real)) emitirJSON(['success' => false, 'message' => 'No es un archivo']);

    $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        emitirJSON(['success' => false, 'message' => 'Extensión no soportada']);
    }

    $antes = @filesize($real);
    if (!$antes) emitirJSON(['success' => false, 'message' => 'Archivo vacío o ilegible']);

    $tmp = $real . '.tmp_' . bin2hex(random_bytes(4));

    if (!comprimirImagen($real, $tmp, $calidad, $max_ancho)) {
        @unlink($tmp);
        emitirJSON(['success' => false, 'message' => 'No se pudo comprimir (GD falló, memoria insuficiente o imagen corrupta)']);
    }

    clearstatcache(true, $tmp);
    $despues = @filesize($tmp);
    if (!$despues) {
        @unlink($tmp);
        emitirJSON(['success' => false, 'message' => 'Error al verificar el archivo comprimido']);
    }

    if ($despues >= $antes) {
        @unlink($tmp);
        emitirJSON([
            'success'    => true,
            'sin_cambio' => true,
            'antes'      => $antes,
            'despues'    => $antes,
            'ahorro'     => 0,
            'message'    => 'Ya estaba optimizada'
        ]);
    }

    if (!@rename($tmp, $real)) {
        @unlink($tmp);
        emitirJSON(['success' => false, 'message' => 'No se pudo reemplazar el archivo (permisos)']);
    }

    emitirJSON([
        'success'    => true,
        'sin_cambio' => false,
        'antes'      => $antes,
        'despues'    => $despues,
        'ahorro'     => $antes - $despues,
        'message'    => 'Comprimido'
    ]);
}

// ACCIÓN: REEMPLAZAR IMAGEN (recibe ya comprimida desde el navegador)
if ($action === 'reemplazar_imagen') {
    if (!esAdmin()) emitirJSON(['success' => false, 'message' => 'No autorizado']);
    if (!validarCSRF($_POST['csrf_token'] ?? '')) emitirJSON(['success' => false, 'message' => 'Token CSRF inválido']);

    if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
        emitirJSON(['success' => false, 'message' => 'No se recibió imagen']);
    }

    $archivo_rel = trim($_POST['archivo'] ?? '');
    if ($archivo_rel === '') emitirJSON(['success' => false, 'message' => 'Archivo vacío']);

    $base = realpath(__DIR__);
    if (!$base) $base = __DIR__;
    $real = @realpath($base . '/' . ltrim($archivo_rel, '/\\'));
    if (!$real) emitirJSON(['success' => false, 'message' => 'El archivo no existe']);
    if (strpos($real, $base) !== 0) emitirJSON(['success' => false, 'message' => 'Ruta inválida']);

    $carpetas_ok = [
        $base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'icons',
        $base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'screenshots',
        $base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'perfiles',
    ];
    $permitido = false;
    foreach ($carpetas_ok as $dir) {
        if (strpos($real, $dir) === 0) { $permitido = true; break; }
    }
    if (!$permitido) emitirJSON(['success' => false, 'message' => 'Carpeta no permitida']);
    if (!is_file($real)) emitirJSON(['success' => false, 'message' => 'No es un archivo']);

    $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        emitirJSON(['success' => false, 'message' => 'Extensión no soportada']);
    }

    // Validar MIME real del archivo subido
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['imagen']['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        emitirJSON(['success' => false, 'message' => 'MIME no válido: ' . $mime]);
    }

    // Comprobar extensión ↔ MIME
    $mapa_ext = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
    if (($mapa_ext[$ext] ?? '') !== $mime) {
        emitirJSON(['success' => false, 'message' => 'MIME no coincide con extensión']);
    }

    $antes = @filesize($real);
    $despues = (int)$_FILES['imagen']['size'];
    if (!$antes) emitirJSON(['success' => false, 'message' => 'Archivo original vacío']);

    if ($despues >= $antes) {
        emitirJSON([
            'success'    => true,
            'sin_cambio' => true,
            'antes'      => $antes,
            'despues'    => $antes,
            'ahorro'     => 0,
            'message'    => 'Ya estaba optimizada'
        ]);
    }

    if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $real)) {
        emitirJSON(['success' => false, 'message' => 'No se pudo reemplazar (permisos)']);
    }

    emitirJSON([
        'success'    => true,
        'sin_cambio' => false,
        'antes'      => $antes,
        'despues'    => $despues,
        'ahorro'     => $antes - $despues,
        'message'    => 'Comprimido'
    ]);
}

$acciones_publicas = ['listar_apks', 'detalle_apk', 'buscar_apk_por_nombre', 'cargar_comentarios'];

if (!in_array($action, $acciones_publicas, true)) {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) {
        emitirJSON(['success' => false, 'message' => 'Token de seguridad inválido. Recarga la página.'], 403);
    }
}

$acciones_privadas = [
    'valorar', 'comentar', 'editar_comentario', 'eliminar_comentario',
    'eliminar_foto_perfil', 'dar_like', 'quitar_like',
    'solicitar_desarrollador', 'reportar_apk',
];
if (in_array($action, $acciones_privadas, true) && !estaLogueado()) {
    emitirJSON(['success' => false, 'message' => 'Debes iniciar sesión']);
}

if ($action === 'listar_apks') {
    $por_pagina = 12;
    $pagina = max(1, intval($_POST['pagina'] ?? 1));
    $busqueda = limpiarEntrada($_POST['q'] ?? '');
    $categoria = limpiarEntrada($_POST['categoria'] ?? '');
    $orden = limpiarEntrada($_POST['orden'] ?? 'id');

    $where = ['1=1'];
    $params = [];

    if ($busqueda !== '') {
        $where[] = "(a.nombre LIKE ? OR a.descripcion LIKE ? OR a.categoria LIKE ?)";
        $like = "%$busqueda%";
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($categoria !== '' && $categoria !== 'all') {
        $where[] = "a.categoria = ?";
        $params[] = $categoria;
    }

    $where_sql = implode(' AND ', $where);
    $ordenes_validos = ['id'=>'a.id DESC','nombre'=>'a.nombre ASC','fecha'=>'a.fecha DESC','tamano'=>'a.tamano ASC','categoria'=>'a.categoria ASC','descargas'=>'a.descargas DESC','valoracion'=>'a.promedio_estrellas DESC'];
    $order_sql = $ordenes_validos[$orden] ?? 'a.id DESC';

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM apks a WHERE $where_sql");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    $pag = calcularPaginacion($total, $por_pagina, $pagina);

    $sql = "SELECT a.*, u.nombre AS creador_nombre, u.rol AS creador_rol, (SELECT COUNT(*) FROM comentarios WHERE apk_id = a.id) AS comentarios_count
            FROM apks a LEFT JOIN usuarios u ON a.creador_id = u.id WHERE $where_sql ORDER BY $order_sql
            LIMIT {$pag['por_pagina']} OFFSET {$pag['offset']}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $apks = $stmt->fetchAll();

    foreach ($apks as &$a) {
        $a['descargable'] = !empty($a['link']) && $a['link'] !== '#' && $a['link'] !== 'apks/' && $a['link'] !== 'apks';
    }
    unset($a);
    emitirJSON(['success' => true, 'apks' => $apks, 'paginacion' => $pag]);
}

if ($action === 'detalle_apk') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) emitirJSON(['success' => false, 'message' => 'ID inválido']);
    $stmt = $pdo->prepare("SELECT a.*, u.nombre AS creador_nombre, u.rol AS creador_rol, (SELECT COUNT(*) FROM comentarios WHERE apk_id = a.id) AS comentarios_count FROM apks a LEFT JOIN usuarios u ON a.creador_id = u.id WHERE a.id = ?");
    $stmt->execute([$id]);
    $apk = $stmt->fetch();
    if (!$apk) emitirJSON(['success' => false, 'message' => 'APK no encontrada']);
    $apk['descargable'] = !empty($apk['link']) && $apk['link'] !== '#' && $apk['link'] !== 'apks/' && $apk['link'] !== 'apks';
    emitirJSON(['success' => true, 'apk' => $apk]);
}

if ($action === 'buscar_apk_por_nombre') {
    $nombre = limpiarEntrada($_POST['nombre'] ?? '');
    if ($nombre === '') emitirJSON(['success' => false, 'message' => 'Nombre vacío']);
    $stmt = $pdo->prepare("SELECT a.*, u.nombre AS creador_nombre, u.rol AS creador_rol, (SELECT COUNT(*) FROM comentarios WHERE apk_id = a.id) AS comentarios_count FROM apks a LEFT JOIN usuarios u ON a.creador_id = u.id WHERE a.nombre = ? LIMIT 1");
    $stmt->execute([$nombre]);
    $apk = $stmt->fetch();
    if (!$apk) emitirJSON(['success' => false, 'message' => 'APK no encontrada']);
    $apk['descargable'] = !empty($apk['link']) && $apk['link'] !== '#' && $apk['link'] !== 'apks/' && $apk['link'] !== 'apks';
    emitirJSON(['success' => true, 'apk' => $apk]);
}

if ($action === 'solicitar_desarrollador') {
    if (!verificarRateLimit('solicitar_dev', 3, 60)) emitirJSON(['success' => false, 'message' => 'Demasiados intentos.']);
    $nombre_dev = limpiarEntrada($_POST['nombre_desarrollador'] ?? '');
    $razon = limpiarEntrada($_POST['razon'] ?? '');
    $usuario_id = $_SESSION['usuario_id'];
    if (mb_strlen($nombre_dev) < 2 || mb_strlen($razon) < 10) emitirJSON(['success' => false, 'message' => 'Completa los campos correctamente']);
    $stmt = $pdo->prepare("SELECT id FROM solicitudes_desarrollador WHERE usuario_id = ? AND estado = 'pendiente'");
    $stmt->execute([$usuario_id]);
    if ($stmt->fetch()) emitirJSON(['success' => false, 'message' => 'Ya tienes una solicitud pendiente']);
    $pdo->prepare("INSERT INTO solicitudes_desarrollador (usuario_id, nombre_desarrollador, razon) VALUES (?, ?, ?)")->execute([$usuario_id, $nombre_dev, $razon]);
    emitirJSON(['success' => true, 'message' => 'Solicitud enviada correctamente']);
}

if ($action === 'valorar') {
    if (!verificarRateLimit('valorar', 20, 5)) emitirJSON(['success' => false, 'message' => 'Demasiadas valoraciones']);
    $apk_id = intval($_POST['apk_id'] ?? 0); $estrellas = intval($_POST['estrellas'] ?? 0); $usuario_id = $_SESSION['usuario_id'];
    if ($apk_id <= 0 || $estrellas < 1 || $estrellas > 5) emitirJSON(['success' => false, 'message' => 'Valoración inválida']);
    $stmt = $pdo->prepare("SELECT id FROM valoraciones WHERE usuario_id = ? AND apk_id = ?"); $stmt->execute([$usuario_id, $apk_id]);
    if ($stmt->fetch()) $pdo->prepare("UPDATE valoraciones SET estrellas = ? WHERE usuario_id = ? AND apk_id = ?")->execute([$estrellas, $usuario_id, $apk_id]);
    else $pdo->prepare("INSERT INTO valoraciones (usuario_id, apk_id, estrellas) VALUES (?, ?, ?)")->execute([$usuario_id, $apk_id, $estrellas]);
    $promedio = actualizarPromedioEstrellas($apk_id);
    emitirJSON(['success' => true, 'message' => 'Valoración guardada', 'promedio' => $promedio]);
}

if ($action === 'comentar') {
    if (!verificarRateLimit('comentar', 10, 5)) emitirJSON(['success' => false, 'message' => 'Espera un momento']);
    $apk_id = intval($_POST['apk_id'] ?? 0); $comentario = limpiarEntrada($_POST['comentario'] ?? ''); $padre_id = !empty($_POST['padre_id']) ? intval($_POST['padre_id']) : null; $usuario_id = $_SESSION['usuario_id'];
    if ($apk_id <= 0 || mb_strlen($comentario) < 1 || mb_strlen($comentario) > 2000) emitirJSON(['success' => false, 'message' => 'Comentario inválido']);
    $stmt = $pdo->prepare("SELECT id, creador_id, nombre FROM apks WHERE id = ?"); $stmt->execute([$apk_id]); $apk = $stmt->fetch();
    if (!$apk) emitirJSON(['success' => false, 'message' => 'APK no encontrada']);
    $pdo->prepare("INSERT INTO comentarios (usuario_id, apk_id, comentario, padre_id) VALUES (?, ?, ?, ?)")->execute([$usuario_id, $apk_id, $comentario, $padre_id]);
    if ($apk['creador_id'] && $apk['creador_id'] != $usuario_id) crearNotificacion($apk['creador_id'], 'comentario', 'Nuevo comentario en ' . $apk['nombre'], mb_substr($comentario, 0, 100));
    emitirJSON(['success' => true, 'message' => 'Comentario publicado', 'comentario_id' => $pdo->lastInsertId()]);
}

if ($action === 'editar_comentario') {
    $comentario_id = intval($_POST['comentario_id'] ?? 0); $comentario = limpiarEntrada($_POST['comentario'] ?? ''); $usuario_id = $_SESSION['usuario_id'];
    if ($comentario_id <= 0 || mb_strlen($comentario) < 1) emitirJSON(['success' => false, 'message' => 'Datos inválidos']);
    $stmt = $pdo->prepare("SELECT usuario_id FROM comentarios WHERE id = ?"); $stmt->execute([$comentario_id]); $coment = $stmt->fetch();
    if (!$coment || ($coment['usuario_id'] != $usuario_id && !esAdmin())) emitirJSON(['success' => false, 'message' => 'No tienes permiso']);
    $pdo->prepare("UPDATE comentarios SET comentario = ?, editado_en = NOW() WHERE id = ?")->execute([$comentario, $comentario_id]);
    emitirJSON(['success' => true, 'message' => 'Comentario actualizado']);
}

if ($action === 'eliminar_comentario') {
    $comentario_id = intval($_POST['comentario_id'] ?? 0); $usuario_id = $_SESSION['usuario_id'];
    $stmt = $pdo->prepare("SELECT usuario_id FROM comentarios WHERE id = ?"); $stmt->execute([$comentario_id]); $coment = $stmt->fetch();
    if (!$coment || ($coment['usuario_id'] != $usuario_id && !esAdmin())) emitirJSON(['success' => false, 'message' => 'No tienes permiso']);
    $pdo->prepare("DELETE FROM comentarios WHERE id = ?")->execute([$comentario_id]);
    emitirJSON(['success' => true, 'message' => 'Comentario eliminado']);
}

if ($action === 'eliminar_foto_perfil') {
    $usuario_id = $_SESSION['usuario_id']; $usuario = obtenerUsuario($usuario_id);
    if ($usuario['foto_perfil'] && $usuario['foto_perfil'] !== 'default.png' && $usuario['foto_perfil'] !== 'assets/default-avatar.png' && file_exists($usuario['foto_perfil'])) @unlink($usuario['foto_perfil']);
    $pdo->prepare("UPDATE usuarios SET foto_perfil = 'default.png' WHERE id = ?")->execute([$usuario_id]);
    $_SESSION['foto_perfil'] = 'default.png';
    emitirJSON(['success' => true, 'message' => 'Foto eliminada']);
}

if ($action === 'dar_like') {
    if (!verificarRateLimit('like', 30, 5)) emitirJSON(['success' => false, 'message' => 'Demasiadas acciones']);
    $usuario_id = intval($_POST['usuario_id'] ?? 0); $liker_id = $_SESSION['usuario_id'];
    if ($usuario_id <= 0 || $usuario_id == $liker_id) emitirJSON(['success' => false, 'message' => 'Acción inválida']);
    $stmt = $pdo->prepare("SELECT id FROM likes_usuario WHERE usuario_id = ? AND liker_id = ?"); $stmt->execute([$usuario_id, $liker_id]);
    if ($stmt->fetch()) emitirJSON(['success' => false, 'message' => 'Ya le diste like']);
    $pdo->prepare("INSERT INTO likes_usuario (usuario_id, liker_id) VALUES (?, ?)")->execute([$usuario_id, $liker_id]);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM likes_usuario WHERE usuario_id = ?"); $stmt->execute([$usuario_id]);
    emitirJSON(['success' => true, 'message' => 'Like agregado', 'total_likes' => (int)$stmt->fetchColumn()]);
}

if ($action === 'quitar_like') {
    $usuario_id = intval($_POST['usuario_id'] ?? 0); $liker_id = $_SESSION['usuario_id'];
    $pdo->prepare("DELETE FROM likes_usuario WHERE usuario_id = ? AND liker_id = ?")->execute([$usuario_id, $liker_id]);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM likes_usuario WHERE usuario_id = ?"); $stmt->execute([$usuario_id]);
    emitirJSON(['success' => true, 'message' => 'Like removido', 'total_likes' => (int)$stmt->fetchColumn()]);
}

if ($action === 'reportar_apk') {
    if (!verificarRateLimit('reportar', 5, 60)) emitirJSON(['success' => false, 'message' => 'Demasiados reportes']);
    $apk_id = intval($_POST['apk_id'] ?? 0); $motivo = limpiarEntrada($_POST['motivo'] ?? ''); $descripcion = limpiarEntrada($_POST['descripcion'] ?? ''); $usuario_id = $_SESSION['usuario_id'];
    $motivos_validos = ['contenido_inapropiado', 'malware', 'spam', 'copyright', 'enlace_roto', 'otro'];
    if ($apk_id <= 0 || !in_array($motivo, $motivos_validos, true)) emitirJSON(['success' => false, 'message' => 'Datos inválidos']);
    $pdo->prepare("INSERT INTO reportes (apk_id, usuario_id, motivo, descripcion) VALUES (?, ?, ?, ?)")->execute([$apk_id, $usuario_id, $motivo, $descripcion]);
    emitirJSON(['success' => true, 'message' => 'Reporte enviado']);
}

if ($action === 'cargar_comentarios') {
    $apk_id = intval($_POST['apk_id'] ?? 0); $pagina = max(1, intval($_POST['pagina'] ?? 1)); $por_pagina = 10;
    if ($apk_id <= 0) emitirJSON(['success' => false, 'message' => 'APK inválida']);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE apk_id = ? AND (padre_id IS NULL OR padre_id = 0)"); $stmt->execute([$apk_id]);
    $total = (int)$stmt->fetchColumn(); $pag = calcularPaginacion($total, $por_pagina, $pagina);
    $stmt = $pdo->prepare("SELECT c.*, u.nombre, u.foto_perfil, u.rol AS usuario_rol FROM comentarios c JOIN usuarios u ON c.usuario_id = u.id WHERE c.apk_id = ? AND (c.padre_id IS NULL OR c.padre_id = 0) ORDER BY c.creado_en DESC LIMIT {$pag['por_pagina']} OFFSET {$pag['offset']}");
    $stmt->execute([$apk_id]); $comentarios = $stmt->fetchAll();
    foreach ($comentarios as &$c) {
        $stmt_r = $pdo->prepare("SELECT c.*, u.nombre, u.foto_perfil, u.rol AS usuario_rol FROM comentarios c JOIN usuarios u ON c.usuario_id = u.id WHERE c.padre_id = ? ORDER BY c.creado_en ASC");
        $stmt_r->execute([$c['id']]); $c['respuestas'] = $stmt_r->fetchAll();
    }
    emitirJSON(['success' => true, 'comentarios' => $comentarios, 'paginacion' => $pag]);
}

if ($action === 'marcar_notificaciones_leidas') {
    $pdo->prepare("UPDATE notificaciones SET leida = TRUE WHERE usuario_id = ?")->execute([$_SESSION['usuario_id']]);
    emitirJSON(['success' => true, 'message' => 'Notificaciones leídas']);
}

emitirJSON(['success' => false, 'message' => 'Acción no válida']);