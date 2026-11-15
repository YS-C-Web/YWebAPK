<?php
require_once 'config/database.php';
require_once 'config/funciones.php';

if (!esAdmin()) { header('Location: index.php'); exit; }

// ============================================================
// HELPERS
// ============================================================
function formatearTamano($bytes) {
    $bytes = (int)$bytes;
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)    return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

function renderDbInput($col, $value) {
    $f = $col['Field'];
    $name = 'db_' . $f;
    $type = strtolower($col['Type']);
    $null = $col['Null'] === 'YES';
    $val = $value ?? '';
    $style = "width:100%;max-width:100%;padding:.55rem .7rem;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-input);color:var(--text-primary);font-family:inherit;font-size:.85rem;box-sizing:border-box;";

    if (strpos($f, 'password') !== false && strpos($f, 'hash') !== false) {
        return '<input type="password" name="' . escapar($name) . '" placeholder="' . ($value ? 'Dejar vacío para no cambiar' : 'Contraseña nueva') . '" style="' . $style . '" autocomplete="new-password">'
             . '<small style="font-size:.72rem;color:var(--text-secondary);display:block;margin-top:.2rem;"><i class="fas fa-info-circle"></i> El valor se hashea automáticamente al guardar.</small>';
    }

    if (preg_match('/^enum\((.*)\)$/i', $type, $m)) {
        $opts = array_map(fn($s) => trim($s, " '\""), explode(',', $m[1]));
        $html = '<select name="' . escapar($name) . '" style="' . $style . '">';
        if ($null) $html .= '<option value="">-- NULL --</option>';
        foreach ($opts as $o) {
            $sel = ($val == $o) ? ' selected' : '';
            $html .= '<option value="' . escapar($o) . '"' . $sel . '>' . escapar($o) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    if (strpos($type, 'text') !== false && strpos($type, 'tinyint') === false) {
        return '<textarea name="' . escapar($name) . '" rows="3" style="' . $style . '">' . escapar($val) . '</textarea>';
    }

    if (preg_match('/^tinyint\(1\)/', $type)) {
        $checked = $val ? ' checked' : '';
        return '<label style="display:inline-flex;align-items:center;gap:.4rem;font-size:.85rem;">'
             . '<input type="hidden" name="' . escapar($name) . '" value="0">'
             . '<input type="checkbox" name="' . escapar($name) . '" value="1"' . $checked . '> Activado</label>';
    }

    if (preg_match('/(int|decimal|float|double)/', $type)) {
        return '<input type="number" step="any" name="' . escapar($name) . '" value="' . escapar($val) . '" style="' . $style . '">';
    }

    if (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) {
        $v = $val ? date('Y-m-d\TH:i', strtotime($val)) : '';
        return '<input type="datetime-local" name="' . escapar($name) . '" value="' . escapar($v) . '" style="' . $style . '">';
    }
    if (strpos($type, 'date') !== false) {
        $v = $val ? date('Y-m-d', strtotime($val)) : '';
        return '<input type="date" name="' . escapar($name) . '" value="' . escapar($v) . '" style="' . $style . '">';
    }
    if (strpos($type, 'time') !== false) {
        return '<input type="time" name="' . escapar($name) . '" value="' . escapar($val) . '" style="' . $style . '">';
    }

    return '<input type="text" name="' . escapar($name) . '" value="' . escapar($val) . '" style="' . $style . '">';
}

// ============================================================
// LISTA DE TABLAS
// ============================================================
$tablas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$tabla_actual = $_GET['tabla'] ?? $_POST['db_table'] ?? '';
if (!in_array($tabla_actual, $tablas, true)) $tabla_actual = $tablas[0] ?? '';

$columnas_info = [];
$pk_field = null;
if ($tabla_actual) {
    $cols = $pdo->query("SHOW COLUMNS FROM `$tabla_actual`")->fetchAll();
    foreach ($cols as $c) {
        $columnas_info[] = $c;
        if ($c['Key'] === 'PRI') $pk_field = $c['Field'];
    }
}

$error = '';
$mensaje = '';
if (!empty($_GET['db_ok'])) $mensaje = $_GET['db_ok'];

// ============================================================
// POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido. Recarga la página.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'aceptar_desarrollador') {
                $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("SELECT * FROM solicitudes_desarrollador WHERE id = ? AND estado = 'pendiente'");
                    $stmt->execute([$solicitud_id]);
                    $solicitud = $stmt->fetch();
                    if (!$solicitud) throw new Exception('Solicitud no encontrada');
                    $pdo->prepare("UPDATE usuarios SET rol = 'desarrollador' WHERE id = ?")->execute([$solicitud['usuario_id']]);
                    $pdo->prepare("UPDATE solicitudes_desarrollador SET estado = 'aprobada' WHERE id = ?")->execute([$solicitud_id]);
                    $pdo->commit();
                    crearNotificacion($solicitud['usuario_id'], 'solicitud', '¡Solicitud aprobada!', 'Ahora eres desarrollador.');
                    $mensaje = 'Solicitud aprobada';
                } catch (Exception $e) { $pdo->rollBack(); $error = 'Error: ' . $e->getMessage(); }
            }

            if ($action === 'denegar_desarrollador') {
                $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
                $motivo = limpiarEntrada($_POST['motivo'] ?? '');
                $stmt = $pdo->prepare("SELECT usuario_id FROM solicitudes_desarrollador WHERE id = ? AND estado = 'pendiente'");
                $stmt->execute([$solicitud_id]);
                $row = $stmt->fetch();
                if ($row) {
                    $pdo->prepare("UPDATE solicitudes_desarrollador SET estado = 'denegada', mensaje_denegacion = ? WHERE id = ?")->execute([$motivo, $solicitud_id]);
                    crearNotificacion($row['usuario_id'], 'solicitud', 'Solicitud denegada', 'Motivo: ' . ($motivo ?: 'No especificado'));
                    $mensaje = 'Solicitud denegada';
                }
            }

            if ($action === 'notificar_recuperacion') {
                $rec_id = intval($_POST['recuperacion_id'] ?? 0);
                $stmt = $pdo->prepare("SELECT r.*, u.email, u.nombre, u.id AS uid FROM recuperaciones r JOIN usuarios u ON r.usuario_id = u.id WHERE r.id = ? AND r.estado = 'pendiente'");
                $stmt->execute([$rec_id]);
                $rec = $stmt->fetch();
                if ($rec) {
                    $pdo->beginTransaction();
                    try {
                        $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?")->execute([$rec['nueva_contraseña'], $rec['uid']]);
                        $pdo->prepare("UPDATE recuperaciones SET estado = 'notificado' WHERE id = ?")->execute([$rec_id]);
                        $pdo->commit();
                        crearNotificacion($rec['uid'], 'solicitud', 'Contraseña actualizada', 'Revisa tu correo.');
                        $mensaje = 'Contraseña aplicada.';
                    } catch (Exception $e) { $pdo->rollBack(); $error = 'Error: ' . $e->getMessage(); }
                }
            }

            if ($action === 'aprobar_apk') {
                $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
                $stmt = $pdo->prepare("SELECT * FROM solicitudes_apk WHERE id = ?");
                $stmt->execute([$solicitud_id]);
                $solicitud = $stmt->fetch();

                if (!$solicitud) $error = 'Solicitud no encontrada';
                elseif ($solicitud['estado'] !== 'pendiente') $error = 'Ya procesada';
                else {
                    $apks_dir = 'uploads/apks/'; $iconos_dir = 'uploads/icons/'; $screenshots_dir = 'uploads/screenshots/';
                    foreach ([$apks_dir, $iconos_dir, $screenshots_dir] as $d) { if (!is_dir($d)) mkdir($d, 0777, true); }
                    $timestamp = time();
                    $dev_id = $solicitud['desarrollador_id'];
                    $apk_actual = null;
                    if ($solicitud['tipo'] === 'actualizacion' && $solicitud['apk_id']) {
                        $stmt_old = $pdo->prepare("SELECT link, icono, screenshots FROM apks WHERE id = ?");
                        $stmt_old->execute([$solicitud['apk_id']]);
                        $apk_actual = $stmt_old->fetch();
                    }
                    $error_subida = false; $ruta_icono = null; $icono_actualizado = false;
                    if (!empty($solicitud['icono_temp']) && file_exists($solicitud['icono_temp'])) {
                        $mime = function_exists('finfo_open') ? (function($p){ $f=finfo_open(FILEINFO_MIME_TYPE); $m=finfo_file($f,$p); finfo_close($f); return $m; })($solicitud['icono_temp']) : 'image/png';
                        $ext = obtenerExtensionSegura(basename($solicitud['icono_temp']), $mime);
                        $ruta_icono = $iconos_dir . 'icon_' . $dev_id . '_' . $timestamp . '.' . $ext;
                        if (!copy($solicitud['icono_temp'], $ruta_icono)) { $error = 'Error al guardar el icono'; $error_subida = true; }
                        else { $icono_actualizado = true; @unlink($solicitud['icono_temp']); }
                    }
                    if ($solicitud['tipo'] === 'actualizacion' && !$icono_actualizado && $apk_actual) { $ruta_icono = $apk_actual['icono']; }
                    $screenshots = [];
                    foreach (['screenshot1', 'screenshot2'] as $i => $campo) {
                        if ($error_subida) break;
                        $tmp = $solicitud[$campo] ?? null;
                        if ($tmp && file_exists($tmp)) {
                            $mime = function_exists('finfo_open') ? (function($p){ $f=finfo_open(FILEINFO_MIME_TYPE); $m=finfo_file($f,$p); finfo_close($f); return $m; })($tmp) : 'image/jpeg';
                            $ext = obtenerExtensionSegura(basename($tmp), $mime);
                            $nombre = 'ss' . ($i+1) . '_' . $dev_id . '_' . $timestamp . '.' . $ext;
                            $ruta = $screenshots_dir . $nombre;
                            if (copy($tmp, $ruta)) { $screenshots[] = $ruta; @unlink($tmp); }
                            else { $error = 'Error al guardar captura'; $error_subida = true; }
                        }
                    }
                    $screenshots_json = json_encode($screenshots);
                    if (!$error_subida) {
                        $archivo_apk = $solicitud['archivo_apk'];
                        $es_enlace_externo = filter_var($archivo_apk, FILTER_VALIDATE_URL);
                        if ($es_enlace_externo) {
                            $link_apk = $archivo_apk; $tamano = 'Externo';
                            if ($solicitud['tipo'] === 'actualizacion' && $solicitud['apk_id']) {
                                $pdo->prepare("UPDATE apks SET version=?, link=?, tamano=?, fecha=?, icono=?, screenshots=?, changelog=?, descripcion=?, link_url=?, link_texto=? WHERE id=?")
                                    ->execute([$solicitud['version'], $link_apk, $tamano, date('Y-m-d'), $ruta_icono, $screenshots_json, $solicitud['changelog'], $solicitud['descripcion'] ?: '', $solicitud['link_url'], $solicitud['link_texto'], $solicitud['apk_id']]);
                                if ($apk_actual) {
                                    if ($icono_actualizado && !empty($apk_actual['icono']) && file_exists($apk_actual['icono'])) @unlink($apk_actual['icono']);
                                    if (!empty($apk_actual['screenshots'])) { $ss = json_decode($apk_actual['screenshots'], true); if (is_array($ss)) foreach ($ss as $s) if ($s && file_exists($s)) @unlink($s); }
                                }
                                $mensaje = 'APK actualizada (enlace externo)';
                            } else {
                                $pdo->prepare("INSERT INTO apks (nombre, version, categoria, descripcion, changelog, icono, tamano, fecha, link, screenshots, creador_id, es_oficial, link_url, link_texto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                                    ->execute([$solicitud['nombre'], $solicitud['version'], $solicitud['categoria'], $solicitud['descripcion'] ?: '', $solicitud['changelog'], $ruta_icono, $tamano, date('Y-m-d'), $link_apk, $screenshots_json, $dev_id, false, $solicitud['link_url'], $solicitud['link_texto']]);
                                $mensaje = 'APK publicada (enlace externo)';
                            }
                            $pdo->prepare("UPDATE solicitudes_apk SET estado='aprobada' WHERE id=?")->execute([$solicitud_id]);
                            if (!empty($solicitud['icono_temp']) && file_exists($solicitud['icono_temp'])) @unlink($solicitud['icono_temp']);
                            crearNotificacion($dev_id, 'apk', 'APK aprobada', $solicitud['nombre'] . ' v' . $solicitud['version'] . ' ya está publicada.');
                            regenerarSitemap();
                        } elseif ($archivo_apk && file_exists($archivo_apk)) {
                            $nombre_apk = 'apk_' . $dev_id . '_' . $timestamp . '.apk';
                            $ruta_apk = $apks_dir . $nombre_apk;
                            if (copy($archivo_apk, $ruta_apk)) {
                                $tamano = round(filesize($archivo_apk) / (1024 * 1024), 2) . ' MB';
                                $link_apk = $ruta_apk; @unlink($archivo_apk);
                                if ($solicitud['tipo'] === 'actualizacion' && $solicitud['apk_id']) {
                                    $pdo->prepare("UPDATE apks SET version=?, link=?, tamano=?, fecha=?, icono=?, screenshots=?, changelog=?, descripcion=?, link_url=?, link_texto=? WHERE id=?")
                                        ->execute([$solicitud['version'], $link_apk, $tamano, date('Y-m-d'), $ruta_icono, $screenshots_json, $solicitud['changelog'], $solicitud['descripcion'] ?: '', $solicitud['link_url'], $solicitud['link_texto'], $solicitud['apk_id']]);
                                    if ($apk_actual) {
                                        if (!empty($apk_actual['link']) && file_exists($apk_actual['link'])) @unlink($apk_actual['link']);
                                        if ($icono_actualizado && !empty($apk_actual['icono']) && file_exists($apk_actual['icono'])) @unlink($apk_actual['icono']);
                                        if (!empty($apk_actual['screenshots'])) { $ss = json_decode($apk_actual['screenshots'], true); if (is_array($ss)) foreach ($ss as $s) if ($s && file_exists($s)) @unlink($s); }
                                    }
                                    $mensaje = 'APK actualizada correctamente';
                                } else {
                                    $pdo->prepare("INSERT INTO apks (nombre, version, categoria, descripcion, changelog, icono, tamano, fecha, link, screenshots, creador_id, es_oficial, link_url, link_texto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                                        ->execute([$solicitud['nombre'], $solicitud['version'], $solicitud['categoria'], $solicitud['descripcion'] ?: '', $solicitud['changelog'], $ruta_icono, $tamano, date('Y-m-d'), $link_apk, $screenshots_json, $dev_id, false, $solicitud['link_url'], $solicitud['link_texto']]);
                                    $mensaje = 'APK publicada correctamente';
                                }
                                $pdo->prepare("UPDATE solicitudes_apk SET estado='aprobada' WHERE id=?")->execute([$solicitud_id]);
                                if (!empty($solicitud['icono_temp']) && file_exists($solicitud['icono_temp'])) @unlink($solicitud['icono_temp']);
                                crearNotificacion($dev_id, 'apk', 'APK aprobada', $solicitud['nombre'] . ' v' . $solicitud['version'] . ' ya está publicada.');
                            } else { $error = 'Error al guardar el APK'; }
                        } else { $error = 'El archivo APK no existe'; }
                    }
                }
            }

            if ($action === 'denegar_apk') {
                $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
                $motivo = limpiarEntrada($_POST['motivo'] ?? '');
                $stmt = $pdo->prepare("SELECT desarrollador_id, nombre FROM solicitudes_apk WHERE id = ? AND estado='pendiente'");
                $stmt->execute([$solicitud_id]);
                $sol = $stmt->fetch();
                if ($sol) {
                    $pdo->prepare("UPDATE solicitudes_apk SET estado='denegada', mensaje_denegacion=? WHERE id=?")->execute([$motivo, $solicitud_id]);
                    crearNotificacion($sol['desarrollador_id'], 'apk', 'APK denegada', $sol['nombre'] . ': ' . ($motivo ?: 'No especificado'));
                    $mensaje = 'Solicitud denegada';
                }
            }

            if ($action === 'eliminar_comentario') { $pdo->prepare("DELETE FROM comentarios WHERE id = ?")->execute([intval($_POST['comentario_id'] ?? 0)]); $mensaje = 'Comentario eliminado'; }
            if ($action === 'marcar_reporte') { if (in_array($_POST['estado'] ?? '', ['revisado', 'ignorado'])) { $pdo->prepare("UPDATE reportes SET estado = ? WHERE id = ?")->execute([$_POST['estado'], intval($_POST['reporte_id'] ?? 0)]); $mensaje = 'Reporte actualizado'; } }
            if ($action === 'toggle_destacado') {
                $aid = intval($_POST['apk_id'] ?? 0);
                $stmt = $pdo->prepare("SELECT destacado FROM apks WHERE id = ?"); $stmt->execute([$aid]); $row = $stmt->fetch();
                if ($row) { $nuevo = $row['destacado'] ? 0 : 1; $pdo->prepare("UPDATE apks SET destacado = ? WHERE id = ?")->execute([$nuevo, $aid]); $mensaje = $nuevo ? 'Destacada' : 'Quitada'; }
            }
            if ($action === 'eliminar_apk') {
                $aid = intval($_POST['apk_id'] ?? 0);
                $stmt = $pdo->prepare("SELECT link, icono, screenshots FROM apks WHERE id = ?"); $stmt->execute([$aid]); $a = $stmt->fetch();
                if ($a) {
                    if (!empty($a['link']) && !filter_var($a['link'], FILTER_VALIDATE_URL) && file_exists($a['link'])) @unlink($a['link']);
                    if (!empty($a['icono']) && file_exists($a['icono'])) @unlink($a['icono']);
                    if (!empty($a['screenshots'])) { $ss = json_decode($a['screenshots'], true); if (is_array($ss)) foreach ($ss as $s) if ($s && file_exists($s)) @unlink($s); }
                    $pdo->prepare("DELETE FROM reportes WHERE apk_id = ?")->execute([$aid]);
                    $pdo->prepare("DELETE FROM comentarios WHERE apk_id = ?")->execute([$aid]);
                    $pdo->prepare("DELETE FROM valoraciones WHERE apk_id = ?")->execute([$aid]);
                    $pdo->prepare("DELETE FROM descargas WHERE apk_id = ?")->execute([$aid]);
                    $pdo->prepare("DELETE FROM apks WHERE id = ?")->execute([$aid]);
                    $mensaje = 'APK eliminada';
                    regenerarSitemap();
                }
            }

            if (in_array($action, ['db_update', 'db_insert', 'db_delete'], true)) {
                $t = $_POST['db_table'] ?? '';
                if (!in_array($t, $tablas, true)) throw new Exception('Tabla inválida');

                $cols_db = $pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll();
                $pk_db = null;
                foreach ($cols_db as $c) { if ($c['Key'] === 'PRI') { $pk_db = $c['Field']; break; } }
                if (!$pk_db) throw new Exception('La tabla no tiene clave primaria única');

                if ($action === 'db_delete') {
                    $id = $_POST['db_pk'] ?? null;
                    if ($id === null) throw new Exception('PK requerida');
                    $stmt = $pdo->prepare("DELETE FROM `$t` WHERE `$pk_db` = ?");
                    $stmt->execute([$id]);
                    $mensaje = "Registro eliminado de `$t`";
                }

                if ($action === 'db_update') {
                    $id = $_POST['db_pk'] ?? null;
                    if ($id === null) throw new Exception('PK requerida');
                    $sets = []; $params = [];
                    foreach ($cols_db as $c) {
                        $f = $c['Field'];
                        if ($f === $pk_db) continue;
                        if (!isset($_POST['db_' . $f])) continue;
                        $val = $_POST['db_' . $f];

                        if (strpos($f, 'password') !== false && strpos($f, 'hash') !== false) {
                            if ($val === '') continue;
                            $val = password_hash($val, PASSWORD_DEFAULT);
                        }
                        if ($val === '' && $c['Null'] === 'YES') $val = null;

                        $sets[] = "`$f` = ?";
                        $params[] = $val;
                    }
                    if (empty($sets)) throw new Exception('Nada que actualizar');
                    $params[] = $id;
                    $stmt = $pdo->prepare("UPDATE `$t` SET " . implode(', ', $sets) . " WHERE `$pk_db` = ?");
                    $stmt->execute($params);
                    $mensaje = "Registro actualizado en `$t`";
                }

                if ($action === 'db_insert') {
                    $fields = []; $placeholders = []; $params = [];
                    foreach ($cols_db as $c) {
                        $f = $c['Field'];
                        if (stripos($c['Extra'], 'auto_increment') !== false) continue;
                        if (!isset($_POST['db_' . $f])) continue;
                        $val = $_POST['db_' . $f];

                        if (strpos($f, 'password') !== false && strpos($f, 'hash') !== false) {
                            if ($val === '') throw new Exception("Contraseña requerida para crear usuario");
                            $val = password_hash($val, PASSWORD_DEFAULT);
                        }
                        if ($val === '') {
                            if ($c['Null'] === 'YES') $val = null;
                            elseif ($c['Default'] !== null) continue;
                        }
                        $fields[] = "`$f`";
                        $placeholders[] = "?";
                        $params[] = $val;
                    }
                    $stmt = $pdo->prepare("INSERT INTO `$t` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")");
                    $stmt->execute($params);
                    $mensaje = "Registro creado en `$t` (ID: " . $pdo->lastInsertId() . ")";
                }

                $redirect = 'admin.php?tabla=' . urlencode($t);
                if (!empty($_POST['db_pag'])) $redirect .= '&db_pag=' . intval($_POST['db_pag']);
                $redirect .= '&db_ok=' . urlencode($mensaje);
                header('Location: ' . $redirect);
                exit;
            }

        } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
    }
}

// ============================================================
// EDIT / NEW MODAL
// ============================================================
$edit_row = null;
$edit_mode = null;
if ($tabla_actual && $pk_field) {
    if (isset($_GET['edit']) && $_GET['edit'] !== '') {
        $stmt = $pdo->prepare("SELECT * FROM `$tabla_actual` WHERE `$pk_field` = ? LIMIT 1");
        $stmt->execute([$_GET['edit']]);
        $edit_row = $stmt->fetch();
        if ($edit_row) $edit_mode = 'update';
        else $error = 'Registro no encontrado';
    } elseif (isset($_GET['new'])) {
        $edit_mode = 'insert';
        $edit_row = [];
        foreach ($columnas_info as $c) $edit_row[$c['Field']] = null;
    }
}

// ============================================================
// CONSULTAS
// ============================================================
$solicitudes_dev = $pdo->query("SELECT sd.*, u.nombre AS usuario_nombre, u.email FROM solicitudes_desarrollador sd JOIN usuarios u ON sd.usuario_id = u.id WHERE sd.estado = 'pendiente' ORDER BY sd.creado_en DESC")->fetchAll();
$recuperaciones = $pdo->query("SELECT r.*, u.nombre AS usuario_nombre, u.email FROM recuperaciones r JOIN usuarios u ON r.usuario_id = u.id WHERE r.estado = 'pendiente' ORDER BY r.creado_en DESC")->fetchAll();
$solicitudes_apk = $pdo->query("SELECT sa.*, u.nombre AS usuario_nombre, u.email AS usuario_email FROM solicitudes_apk sa JOIN usuarios u ON sa.desarrollador_id = u.id WHERE sa.estado = 'pendiente' ORDER BY sa.creado_en DESC")->fetchAll();
$reportes = $pdo->query("SELECT r.*, u.nombre AS reportador, a.nombre AS apk_nombre FROM reportes r LEFT JOIN usuarios u ON r.usuario_id = u.id LEFT JOIN apks a ON r.apk_id = a.id WHERE r.estado = 'pendiente' ORDER BY r.creado_en DESC")->fetchAll();
$comentarios = $pdo->query("SELECT c.*, u.nombre, a.nombre AS apk_nombre FROM comentarios c JOIN usuarios u ON c.usuario_id = u.id JOIN apks a ON c.apk_id = a.id ORDER BY c.creado_en DESC LIMIT 50")->fetchAll();
$apks_todas = $pdo->query("SELECT a.id, a.nombre, a.version, a.destacado, u.nombre AS creador_nombre FROM apks a LEFT JOIN usuarios u ON a.creador_id = u.id ORDER BY a.id DESC LIMIT 100")->fetchAll();

$imagenes_para_comprimir = [];
$dirs_scan = ['uploads/icons' => 'Iconos APK', 'uploads/screenshots' => 'Capturas', 'uploads/perfiles' => 'Perfiles'];
$exts_validas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
foreach ($dirs_scan as $dir_rel => $etiqueta) {
    $dir_abs = __DIR__ . '/' . $dir_rel;
    if (!is_dir($dir_abs)) continue;
    foreach (scandir($dir_abs) as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir_abs . '/' . $file;
        if (!is_file($path)) continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, $exts_validas)) continue;
        $imagenes_para_comprimir[] = [
            'path_abs' => $path, 'path_rel' => $dir_rel . '/' . $file,
            'nombre' => $file, 'carpeta' => $etiqueta,
            'tamano' => filesize($path), 'ext' => $ext, 'mtime' => filemtime($path),
        ];
    }
}
usort($imagenes_para_comprimir, fn($a, $b) => $b['tamano'] - $a['tamano']);
$total_peso_imagenes = array_sum(array_column($imagenes_para_comprimir, 'tamano'));

$db_pagina = max(1, intval($_GET['db_pag'] ?? 1));
$db_por_pagina = 25;
$db_offset = ($db_pagina - 1) * $db_por_pagina;
$db_total = 0;
$db_rows = [];
if ($tabla_actual) {
    $db_total = (int)$pdo->query("SELECT COUNT(*) FROM `$tabla_actual`")->fetchColumn();
    $db_rows = $pdo->query("SELECT * FROM `$tabla_actual` LIMIT $db_por_pagina OFFSET $db_offset")->fetchAll();
}
$db_total_paginas = max(1, (int)ceil($db_total / $db_por_pagina));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= generarCSRF() ?>">
    <title>Admin - YSWEBAPK</title>
    <link rel="icon" href="Ico.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* ===== FIX GLOBAL RESPONSIVE ===== */
        *, *::before, *::after { box-sizing: border-box; }
        body { overflow-x: hidden; }
        img { max-width: 100%; height: auto; }
        pre, code { white-space: pre-wrap; word-break: break-word; }

        .admin-container { max-width: 1300px; margin: 2rem auto; padding: 0 1rem; width: 100%; }

        .admin-section { background: var(--bg-card); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 2rem; box-shadow: var(--shadow-md); border: 1px solid var(--border-color); overflow: hidden; }

        /* Wrapper responsive para tablas */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: var(--radius-md);
            margin-bottom: .5rem;
        }
        .table-responsive::-webkit-scrollbar { height: 6px; }
        .table-responsive::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 3px; }

        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table th, .admin-table td { padding: .55rem .7rem; text-align: left; border-bottom: 1px solid var(--border-color); font-size: .85rem; word-break: break-word; overflow-wrap: anywhere; }
        .admin-table th { background: var(--bg-hover); font-weight: 700; }

        .admin-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(180px, 100%), 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .admin-stat { background: var(--bg-hover); padding: 1rem; border-radius: var(--radius-md); text-align: center; border: 1px solid var(--border-color); min-width: 0; }

        .solicitud-apk { background: var(--bg-hover); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid var(--border-color); }
        .solicitud-apk .header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: .5rem; }
        .solicitud-apk .header > div { min-width: 0; }
        .solicitud-apk .header > div:last-child { flex-shrink: 0; }
        .solicitud-apk .detalles { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem; }
        .solicitud-apk .imagenes { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(120px, 100%), 1fr)); gap: .5rem; margin-top: 1rem; }
        .solicitud-apk .imagenes img { max-width: 80px; max-height: 80px; border-radius: 8px; border: 1px solid var(--border-color); cursor: pointer; object-fit: cover; }

        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.6); backdrop-filter: blur(8px); z-index: 999; display: flex; align-items: center; justify-content: center; padding: 1rem; opacity: 0; visibility: hidden; transition: all .3s; }
        .modal-overlay.open { opacity: 1; visibility: visible; }
        .modal-content { background: var(--bg-modal); width: 100%; max-width: min(560px, calc(100vw - 2rem)); max-height: 92vh; overflow-y: auto; border-radius: var(--radius-xl); padding: 1.5rem; border: 1px solid var(--border-color); box-sizing: border-box; }

        .recuperacion-box { background: var(--bg-hover); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1rem; border: 1px solid var(--border-color); }
        .pass-temp { font-family: monospace; font-size: 1.1rem; background: var(--bg-card); padding: .3rem .6rem; border-radius: 6px; border: 1px dashed var(--warning); display: inline-block; color: var(--warning); font-weight: 700; word-break: break-all; max-width: 100%; }

        .vt-result { margin-top: 0.5rem; padding: 0.6rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; text-align: center; display: none; }
        .vt-result.vt-limpio { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); display: block; }
        .vt-result.vt-peligro { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); display: block; }
        .vt-result.vt-error { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); display: block; }

        .section-header-wrap { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1rem; padding-bottom: .5rem; border-bottom: 1px solid var(--border-color); }
        .section-header-wrap h2 { margin: 0; flex: 1; display: flex; align-items: center; gap: .5rem; cursor: pointer; min-width: 0; word-break: break-word; }
        .toggle-section-btn { background: var(--bg-hover); border: 1px solid var(--border-color); width: 34px; height: 34px; border-radius: 8px; cursor: pointer; color: var(--text-primary); font-size: .8rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: var(--transition-fast); font-family: inherit; }
        .toggle-section-btn:hover { border-color: var(--primary); background: var(--primary); color: white; }
        .admin-section.collapsed .section-body { display: none; }
        .admin-section.collapsed { padding-bottom: 1rem; }

        .img-preview-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-color); cursor: pointer; transition: var(--transition-fast); }
        .img-preview-thumb:hover { transform: scale(1.15); border-color: var(--primary); }
        .img-tag { font-size: .72rem; padding: .15rem .5rem; background: var(--bg-hover); border-radius: 4px; color: var(--text-secondary); }
        .img-size-badge { font-weight: 700; color: var(--warning); }
        .img-size-badge.big { color: var(--danger); }
        .chk-img { width: 16px; height: 16px; cursor: pointer; }
        #previewImgModal .modal-content { background: transparent; border: none; box-shadow: none; padding: 0; max-width: 95vw; display: flex; flex-direction: column; align-items: center; }
        #previewImgModal img { max-width: 90vw; max-height: 82vh; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,.6); }
        #previewImgModal .preview-close { position: fixed; top: 1rem; right: 1rem; width: 46px; height: 46px; border-radius: 50%; background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.3); color: white; font-size: 1.2rem; cursor: pointer; z-index: 1001; display: flex; align-items: center; justify-content: center; }
        #previewImgModal .preview-name { color: white; text-align: center; margin-top: .8rem; font-size: .9rem; font-weight: 600; word-break: break-all; }

        #compresionOverlay .modal-content { max-width: min(640px, calc(100vw - 2rem)); }
        #compresionBarWrap { width: 100%; height: 22px; background: var(--bg-hover); border-radius: 12px; overflow: hidden; margin-bottom: 1rem; border: 1px solid var(--border-color); }
        #compresionBar { height: 100%; width: 0%; background: var(--primary); color: white; text-align: center; font-size: .75rem; font-weight: 700; line-height: 22px; transition: width .2s; }
        #compresionLog { max-height: 260px; overflow-y: auto; font-size: .82rem; background: var(--bg-hover); padding: .6rem .9rem; border-radius: 8px; border: 1px solid var(--border-color); font-family: monospace; line-height: 1.6; word-break: break-word; }

        /* Enlaces grid */
        .enlaces-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(200px, 100%), 1fr)); gap: .7rem; }
        .enlace-card { display: flex; align-items: center; gap: .7rem; padding: .75rem 1rem; background: var(--bg-hover); border: 1px solid var(--border-color); border-radius: var(--radius-md); text-decoration: none; color: var(--text-primary); transition: var(--transition-fast); min-width: 0; }
        .enlace-card:hover { border-color: var(--primary); transform: translateY(-2px); box-shadow: var(--shadow-sm); }
        .enlace-card i { font-size: 1.2rem; color: var(--primary); width: 24px; text-align: center; flex-shrink: 0; }
        .enlace-card .enlace-info { flex: 1; min-width: 0; }
        .enlace-card .enlace-titulo { font-weight: 700; font-size: .88rem; word-break: break-word; }
        .enlace-card .enlace-url { font-size: .7rem; color: var(--text-secondary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* DB browser */
        .db-toolbar { display: flex; gap: .7rem; flex-wrap: wrap; align-items: center; margin-bottom: 1rem; padding: .8rem; background: var(--bg-hover); border-radius: var(--radius-md); border: 1px solid var(--border-color); }
        .db-toolbar form { display: flex; gap: .5rem; align-items: center; margin: 0; flex-wrap: wrap; }
        .db-toolbar select { padding: .5rem 1rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); font-family: inherit; font-weight: 600; max-width: 100%; box-sizing: border-box; }
        .db-table-wrap { overflow-x: auto; max-height: 500px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: var(--radius-md); -webkit-overflow-scrolling: touch; }
        .db-table-wrap .admin-table th, .db-table-wrap .admin-table td { white-space: nowrap; font-family: monospace; font-size: .78rem; word-break: normal; overflow-wrap: normal; }
        .db-table-wrap .admin-table th { position: sticky; top: 0; z-index: 2; }
        .db-null { color: var(--text-light); font-style: italic; }
        .db-truncate { max-width: 250px; overflow: hidden; text-overflow: ellipsis; display: inline-block; vertical-align: middle; }
        .db-pk-badge { background: var(--warning-bg); color: var(--warning); padding: .1rem .4rem; border-radius: 4px; font-size: .65rem; font-weight: 700; margin-left: .3rem; }
        .db-btn-icon { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; border: none; cursor: pointer; font-size: .75rem; font-family: inherit; }
        .db-form-help { font-size: .7rem; color: var(--text-secondary); margin-top: .2rem; display: block; }

        /* ===== MEDIA QUERIES ===== */
        @media (max-width: 768px) {
            .admin-container { padding: 0 .7rem; margin: 1rem auto; }
            .admin-section { padding: 1rem; margin-bottom: 1.2rem; }
            .section-header-wrap h2 { font-size: 1.05rem; }
            .db-toolbar form { flex: 1 1 100%; }
        }

        @media (max-width: 600px) {
            .solicitud-apk .detalles { grid-template-columns: 1fr; }
            .db-toolbar { flex-direction: column; align-items: stretch; }
            .db-toolbar form { width: 100%; }
            .db-toolbar select { width: 100%; }
            .db-toolbar .btn-sm { width: 100%; justify-content: center; }
            .admin-table th, .admin-table td { padding: .4rem .5rem; font-size: .78rem; }
        }

        @media (max-width: 480px) {
            .admin-container { padding: 0 .4rem; margin: .5rem auto; }
            .admin-section { padding: .8rem; border-radius: 12px; }
            .section-header-wrap { gap: .5rem; margin-bottom: .7rem; padding-bottom: .4rem; }
            .section-header-wrap h2 { font-size: .95rem; gap: .3rem; }
            .toggle-section-btn { width: 30px; height: 30px; font-size: .7rem; }
            .admin-grid { gap: .5rem; }
            .admin-stat { padding: .6rem .3rem; }
            .admin-stat > div:first-child { font-size: 1.4rem !important; }
            .admin-stat > div:last-child { font-size: .72rem; }
            .enlaces-grid { grid-template-columns: 1fr; }
            .enlace-card { padding: .55rem .75rem; gap: .55rem; }
            .enlace-card i { font-size: 1rem; width: 20px; }
            .enlace-card .enlace-titulo { font-size: .82rem; }
            .enlace-card .enlace-url { font-size: .65rem; }
            .modal-content { padding: 1rem; max-width: calc(100vw - 1rem); border-radius: 14px; }
            .solicitud-apk { padding: .9rem; border-radius: 12px; }
            .solicitud-apk .header { flex-direction: column; }
            .solicitud-apk .header > div:last-child { width: 100%; align-items: stretch !important; }
            .solicitud-apk .header > div:last-child > div:first-child { width: 100%; }
            .solicitud-apk .header form { flex: 1; }
            .solicitud-apk .header form button { width: 100%; }
            .recuperacion-box { padding: .7rem; }
            .pass-temp { font-size: .9rem; }
            #compresionBarWrap { height: 18px; }
            #compresionBar { font-size: .65rem; line-height: 18px; }
        }

        @media (max-width: 360px) {
            .admin-container { padding: 0 .25rem; }
            .admin-section { padding: .6rem; }
            .admin-table th, .admin-table td { padding: .3rem .35rem; font-size: .72rem; }
            .enlace-card .enlace-titulo { font-size: .78rem; }
            .enlace-card .enlace-url { font-size: .6rem; }
        }
    </style>
</head>
<body>

<div class="admin-container">
    <h1 style="font-size:1.5rem;"><i class="fas fa-shield-alt" style="color:var(--primary)"></i> Panel de Administración</h1>

    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;">
        <a href="index.php" class="btn-sm btn-primary" style="padding:.5rem 1rem;text-decoration:none;"><i class="fas fa-arrow-left"></i> Volver</a>
        <a href="regenerar_json.php" class="btn-sm btn-warning" style="padding:.5rem 1rem;text-decoration:none;"><i class="fas fa-sync-alt"></i> Regenerar JSON</a>
    </div>

    <?php if ($error): ?><div class="error"><?= escapar($error) ?></div><?php endif; ?>
    <?php if ($mensaje): ?><div class="mensaje"><?= escapar($mensaje) ?></div><?php endif; ?>

    <div class="admin-grid">
        <div class="admin-stat"><div style="font-size:2rem;font-weight:700;color:var(--primary)"><?= count($solicitudes_dev) ?></div><div>Solicitudes Dev</div></div>
        <div class="admin-stat"><div style="font-size:2rem;font-weight:700;color:var(--primary)"><?= count($recuperaciones) ?></div><div>Recuperaciones</div></div>
        <div class="admin-stat"><div style="font-size:2rem;font-weight:700;color:var(--primary)"><?= count($solicitudes_apk) ?></div><div>APKs por revisar</div></div>
        <div class="admin-stat"><div style="font-size:2rem;font-weight:700;color:var(--primary)"><?= count($reportes) ?></div><div>Reportes</div></div>
    </div>

    <!-- ENLACES ÚTILES -->
    <div class="admin-section collapsed">
        <h2><i class="fas fa-link"></i> Enlaces útiles</h2>
        <div class="enlaces-grid">
            <a href="index.php" class="enlace-card" target="_blank"><i class="fas fa-home"></i><div class="enlace-info"><div class="enlace-titulo">Sitio público</div><div class="enlace-url">index.php</div></div></a>
            <a href="sitemap.xml" class="enlace-card" target="_blank"><i class="fas fa-sitemap"></i><div class="enlace-info"><div class="enlace-titulo">Sitemap XML</div><div class="enlace-url">sitemap.xml</div></div></a>
            <a href="sitemap.php" class="enlace-card" target="_blank"><i class="fas fa-file-code"></i><div class="enlace-info"><div class="enlace-titulo">Sitemap (PHP)</div><div class="enlace-url">sitemap.php</div></div></a>
            <a href="robots.txt" class="enlace-card" target="_blank"><i class="fas fa-robot"></i><div class="enlace-info"><div class="enlace-titulo">robots.txt</div><div class="enlace-url">robots.txt</div></div></a>
            <a href="diagnostico.php" class="enlace-card" target="_blank"><i class="fas fa-stethoscope"></i><div class="enlace-info"><div class="enlace-titulo">Diagnóstico</div><div class="enlace-url">diagnostico.php</div></div></a>
            <a href="guia-instalacion.php" class="enlace-card" target="_blank"><i class="fas fa-book-open"></i><div class="enlace-info"><div class="enlace-titulo">Guía de instalación</div><div class="enlace-url">guia-instalacion.php</div></div></a>
            <a href="donar.php" class="enlace-card" target="_blank"><i class="fas fa-heart"></i><div class="enlace-info"><div class="enlace-titulo">Donaciones</div><div class="enlace-url">donar.php</div></div></a>
            <a href="regenerar_json.php" class="enlace-card" target="_blank"><i class="fas fa-sync-alt"></i><div class="enlace-info"><div class="enlace-titulo">Regenerar JSON</div><div class="enlace-url">regenerar_json.php</div></div></a>
            <a href="soporte.php" class="enlace-card" target="_blank"><i class="fas fa-headset"></i><div class="enlace-info"><div class="enlace-titulo">Soporte</div><div class="enlace-url">soporte.php</div></div></a>
            <a href="terminos.php" class="enlace-card" target="_blank"><i class="fas fa-file-contract"></i><div class="enlace-info"><div class="enlace-titulo">Términos</div><div class="enlace-url">terminos.php</div></div></a>
            <a href="privacidad.php" class="enlace-card" target="_blank"><i class="fas fa-user-shield"></i><div class="enlace-info"><div class="enlace-titulo">Privacidad</div><div class="enlace-url">privacidad.php</div></div></a>
            <a href="versiones.json" class="enlace-card" target="_blank"><i class="fas fa-file-code"></i><div class="enlace-info"><div class="enlace-titulo">versiones.json</div><div class="enlace-url">versiones.json</div></div></a>
        </div>
        <p style="font-size:.75rem;color:var(--text-secondary);margin-top:.8rem;">
            <i class="fas fa-info-circle"></i> Los enlaces se abren en pestaña nueva. <code>sitemap.xml</code> y <code>diagnostico.php</code> requieren que esos archivos existan en la raíz.
        </p>
    </div>

    <!-- RECUPERACIONES -->
    <div class="admin-section collapsed">
        <h2><i class="fas fa-key"></i> Recuperaciones de Contraseña</h2>
        <?php if (empty($recuperaciones)): ?><p style="color:var(--text-secondary)">No hay solicitudes pendientes.</p>
        <?php else: foreach ($recuperaciones as $r):
            $mailto = 'mailto:' . rawurlencode($r['email']) . '?subject=Recuperación&body=Hola%20' . rawurlencode($r['usuario_nombre']) . ',%0A%0ATu%20nueva%20contraseña%20es:%20' . rawurlencode($r['password_temporal'] ?? '');
        ?>
            <div class="recuperacion-box">
                <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
                    <div style="min-width:0;word-break:break-word;"><strong><?= escapar($r['usuario_nombre']) ?></strong><br><small><?= escapar($r['email']) ?></small></div>
                    <div style="text-align:right;min-width:0;"><div style="font-size:.75rem">Contraseña temporal:</div><div class="pass-temp"><?= escapar($r['password_temporal'] ?? '—') ?></div></div>
                </div>
                <div style="display:flex;gap:.5rem;margin-top:.8rem;flex-wrap:wrap;">
                    <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="notificar_recuperacion"><input type="hidden" name="recuperacion_id" value="<?= (int)$r['id'] ?>"><button class="btn-sm btn-primary" onclick="return confirm('¿Aplicar?')">Aplicar</button></form>
                    <a href="<?= escapar($mailto) ?>" class="btn-sm btn-success" target="_blank">Enviar correo</a>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- SOLICITUDES DEV -->
    <div class="admin-section collapsed">
        <h2><i class="fas fa-user-plus"></i> Solicitudes de Desarrollador</h2>
        <?php if (empty($solicitudes_dev)): ?><p>No hay solicitudes.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <tr><th>Usuario</th><th>Dev</th><th>Razón</th><th>Acciones</th></tr>
                    <?php foreach ($solicitudes_dev as $s): ?>
                        <tr>
                            <td><?= escapar($s['usuario_nombre']) ?><br><small><?= escapar($s['email']) ?></small></td>
                            <td><?= escapar($s['nombre_desarrollador']) ?></td>
                            <td><?= escapar(mb_substr($s['razon'], 0, 50)) ?>...</td>
                            <td>
                                <form method="POST" style="display:inline"><?= csrfField() ?><input type="hidden" name="action" value="aceptar_desarrollador"><input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>"><button class="btn-sm btn-success">Aceptar</button></form>
                                <button class="btn-sm btn-danger" onclick="mostrarDenegarDev(<?= $s['id'] ?>)">Denegar</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- SOLICITUDES APK -->
    <div class="admin-section collapsed">
        <h2><i class="fas fa-upload"></i> Solicitudes de APK</h2>
        <?php if (empty($solicitudes_apk)): ?><p>No hay solicitudes.</p>
        <?php else: foreach ($solicitudes_apk as $s): ?>
            <div class="solicitud-apk">
                <div class="header">
                    <div>
                        <h3 style="font-size:1rem;word-break:break-word;"><?= escapar($s['nombre']) ?> (v<?= escapar($s['version']) ?>)</h3>
                        <div style="font-size:.85rem;color:var(--text-secondary);word-break:break-word;">Por: <?= escapar($s['usuario_nombre']) ?> · <?= date('d/m/Y H:i', strtotime($s['creado_en'])) ?></div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:.5rem;align-items:flex-end;min-width:0;">
                        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                            <form method="POST" onsubmit="return confirm('¿Aprobar y publicar esta APK?')"><?= csrfField() ?><input type="hidden" name="action" value="aprobar_apk"><input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>"><button class="btn-sm btn-success">Aprobar y Publicar</button></form>
                            <button class="btn-sm btn-danger" onclick="mostrarDenegarApk(<?= $s['id'] ?>)">Denegar</button>
                        </div>
                        <div style="width:100%;margin-top:.5rem;">
                            <button type="button" class="btn-sm btn-secondary" style="width:100%;" onclick="escanearVirusTotal(<?= $s['id'] ?>, this)" <?= !empty($s['virustotal_resultado']) ? 'disabled' : '' ?>>
                                <i class="fas fa-shield-virus"></i> <?= !empty($s['virustotal_resultado']) ? 'Ya escaneado' : 'Escanear con VirusTotal' ?>
                            </button>
                            <div id="vt-result-<?= $s['id'] ?>" class="vt-result <?= !empty($s['virustotal_resultado']) ? (strpos($s['virustotal_resultado'], 'limpio') !== false ? 'vt-limpio' : (strpos($s['virustotal_resultado'], 'peligro') !== false ? 'vt-peligro' : 'vt-error')) : '' ?>">
                                <?= !empty($s['virustotal_resultado']) ? 'Resultado: ' . escapar($s['virustotal_resultado']) : '' ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="detalles">
                    <div style="min-width:0;word-break:break-word;"><strong>Descripción:</strong><br><?= nl2br(escapar($s['descripcion'])) ?></div>
                    <div style="min-width:0;word-break:break-word;"><strong>Changelog:</strong><br><?= nl2br(escapar($s['changelog'] ?? 'Sin changelog')) ?></div>
                </div>
                <div class="imagenes">
                    <div><strong>APK / Enlace:</strong><br>
                        <?php if (filter_var($s['archivo_apk'], FILTER_VALIDATE_URL)): ?>
                            <a href="<?= escapar($s['archivo_apk']) ?>" target="_blank" class="btn-sm btn-success" style="margin-top:.3rem">Abrir Enlace</a>
                        <?php else: ?>
                            <a href="<?= escapar($s['archivo_apk']) ?>" target="_blank" class="btn-sm btn-primary" style="margin-top:.3rem">Descargar APK</a>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($s['icono_temp']) && file_exists($s['icono_temp'])): ?><div><strong>Icono:</strong><br><img src="<?= escapar($s['icono_temp']) ?>" onclick="mostrarPreview('<?= escapar($s['icono_temp']) ?>', 'Icono APK')"></div><?php endif; ?>
                    <?php if (!empty($s['screenshot1']) && file_exists($s['screenshot1'])): ?><div><strong>SS1:</strong><br><img src="<?= escapar($s['screenshot1']) ?>" onclick="mostrarPreview('<?= escapar($s['screenshot1']) ?>', 'Captura 1')"></div><?php endif; ?>
                    <?php if (!empty($s['screenshot2']) && file_exists($s['screenshot2'])): ?><div><strong>SS2:</strong><br><img src="<?= escapar($s['screenshot2']) ?>" onclick="mostrarPreview('<?= escapar($s['screenshot2']) ?>', 'Captura 2')"></div><?php endif; ?>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- REPORTES -->
    <div class="admin-section collapsed">
        <h2><i class="fas fa-flag"></i> Reportes</h2>
        <?php if (empty($reportes)): ?><p>No hay reportes.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <?php foreach ($reportes as $r): ?>
                        <tr>
                            <td><?= escapar($r['apk_nombre']) ?></td>
                            <td><?= escapar($r['motivo']) ?></td>
                            <td><?= escapar(mb_substr($r['descripcion'], 0, 50)) ?></td>
                            <td>
                                <form method="POST" style="display:inline"><?= csrfField() ?><input type="hidden" name="action" value="marcar_reporte"><input type="hidden" name="reporte_id" value="<?= $r['id'] ?>"><input type="hidden" name="estado" value="revisado"><button class="btn-sm btn-primary">Revisado</button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- APKS PUBLICADAS -->
    <div class="admin-section collapsed">
        <h2><i class="fas fa-star"></i> APKs Publicadas</h2>
        <div class="table-responsive">
            <table class="admin-table">
                <?php foreach ($apks_todas as $a): ?>
                    <tr>
                        <td><?= escapar($a['nombre']) ?> v<?= escapar($a['version']) ?></td>
                        <td><?= escapar($a['creador_nombre'] ?? 'Admin') ?></td>
                        <td>
                            <form method="POST" style="display:inline"><?= csrfField() ?><input type="hidden" name="action" value="toggle_destacado"><input type="hidden" name="apk_id" value="<?= $a['id'] ?>"><button class="btn-sm <?= $a['destacado'] ? 'btn-secondary' : 'btn-warning' ?>"><?= $a['destacado'] ? 'Quitar ⭐' : 'Destacar ⭐' ?></button></form>
                            <form method="POST" style="display:inline"><?= csrfField() ?><input type="hidden" name="action" value="eliminar_apk"><input type="hidden" name="apk_id" value="<?= $a['id'] ?>"><button class="btn-sm btn-danger" onclick="return confirm('¿Eliminar?')">Eliminar</button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <!-- COMENTARIOS -->
    <div class="admin-section collapsed">
        <h2><i class="fas fa-comments"></i> Comentarios Recientes</h2>
        <div class="table-responsive">
            <table class="admin-table">
                <?php foreach ($comentarios as $c): ?>
                    <tr>
                        <td><?= escapar($c['nombre']) ?></td>
                        <td><?= escapar(mb_substr($c['comentario'], 0, 60)) ?>...</td>
                        <td><form method="POST" style="display:inline"><?= csrfField() ?><input type="hidden" name="action" value="eliminar_comentario"><input type="hidden" name="comentario_id" value="<?= $c['id'] ?>"><button class="btn-sm btn-danger">Eliminar</button></form></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <!-- COMPRIMIR IMÁGENES -->
    <div class="admin-section collapsed">
        <h2><i class="fas fa-compress-arrows-alt"></i> Comprimir Imágenes</h2>
        <?php if (empty($imagenes_para_comprimir)): ?>
            <p style="color:var(--text-secondary)"><i class="fas fa-info-circle"></i> No hay imágenes todavía.</p>
        <?php else: ?>
            <div style="background:var(--success-bg);border:1px solid var(--success);color:var(--success);padding:.7rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:.88rem;word-break:break-word;">
                <i class="fas fa-check-circle"></i> <strong>Compresión en el navegador.</strong> No necesita GD ni librerías del servidor.
            </div>
            <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;padding:1rem;background:var(--bg-hover);border-radius:8px;border:1px solid var(--border-color);">
                <div style="flex:1;min-width:140px;">
                    <label style="display:block;font-weight:600;font-size:.85rem;margin-bottom:.3rem;"><i class="fas fa-sliders-h"></i> Calidad:</label>
                    <input type="number" id="comCalidad" value="80" min="30" max="95" style="width:100%;padding:.55rem;border-radius:6px;border:1px solid var(--border-color);background:var(--bg-input);color:var(--text-primary);font-family:inherit;box-sizing:border-box;">
                </div>
                <div style="flex:1;min-width:140px;">
                    <label style="display:block;font-weight:600;font-size:.85rem;margin-bottom:.3rem;"><i class="fas fa-arrows-alt-h"></i> Ancho máximo (px):</label>
                    <input type="number" id="comMaxAncho" value="256" min="64" max="4000" style="width:100%;padding:.55rem;border-radius:6px;border:1px solid var(--border-color);background:var(--bg-input);color:var(--text-primary);font-family:inherit;box-sizing:border-box;">
                </div>
                <div style="display:flex;align-items:flex-end;">
                    <button type="button" class="btn-sm btn-primary" style="padding:.6rem 1.2rem;" onclick="comprimirSeleccionadas()"><i class="fas fa-compress"></i> Comprimir seleccionadas</button>
                </div>
            </div>
            <div style="display:flex;gap:.5rem;margin-bottom:.7rem;align-items:center;flex-wrap:wrap;">
                <button type="button" class="btn-sm btn-secondary" onclick="seleccionarTodas(true)"><i class="fas fa-check-square"></i> Seleccionar todas</button>
                <button type="button" class="btn-sm btn-secondary" onclick="seleccionarTodas(false)"><i class="fas fa-square"></i> Deseleccionar</button>
                <span style="font-size:.8rem;color:var(--text-secondary);margin-left:auto;">
                    <i class="fas fa-database"></i> Total: <strong><?= count($imagenes_para_comprimir) ?></strong> · Peso: <strong style="color:var(--danger)"><?= formatearTamano($total_peso_imagenes) ?></strong>
                </span>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead><tr><th style="width:30px;"><input type="checkbox" id="chkAll" class="chk-img" onchange="seleccionarTodas(this.checked)"></th><th style="width:70px;">Preview</th><th>Archivo</th><th>Carpeta</th><th style="width:110px;">Peso</th></tr></thead>
                    <tbody>
                        <?php foreach ($imagenes_para_comprimir as $img):
                            $es_pesada = $img['tamano'] > 500 * 1024;
                            $es_muy_pesada = $img['tamano'] > 1024 * 1024;
                        ?>
                            <tr>
                                <td><input type="checkbox" name="archivos[]" value="<?= escapar($img['path_rel']) ?>" class="chk-img" <?= $es_pesada ? 'checked' : '' ?>></td>
                                <td><img src="<?= escapar($img['path_rel']) ?>?v=<?= (int)$img['mtime'] ?>" class="img-preview-thumb" alt="" onclick="mostrarPreview('<?= escapar($img['path_rel']) ?>', '<?= escapar($img['nombre']) ?>', '<?= formatearTamano($img['tamano']) ?>')" onerror="this.style.display='none'; this.parentElement.innerHTML='<span style=\'color:var(--danger);font-size:.7rem\'>Error</span>';"></td>
                                <td style="font-size:.82rem;word-break:break-all;"><?= escapar($img['nombre']) ?></td>
                                <td><span class="img-tag"><?= escapar($img['carpeta']) ?></span></td>
                                <td><span class="img-size-badge <?= $es_muy_pesada ? 'big' : '' ?>"><?= formatearTamano($img['tamano']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p style="font-size:.78rem;color:var(--text-secondary);margin-top:.8rem;">
                <i class="fas fa-lightbulb" style="color:var(--warning)"></i> Las imágenes &gt;500 KB vienen pre-marcadas. Los PNG ya optimizados aparecerán como <em>sin cambios</em>.
            </p>
        <?php endif; ?>
    </div>

    <!-- BASE DE DATOS -->
    <div class="admin-section collapsed">
        <h2><i class="fas fa-database"></i> Base de Datos</h2>
        <div class="db-toolbar">
            <form method="GET">
                <label style="font-weight:600;font-size:.85rem;white-space:nowrap;">Tabla:</label>
                <select name="tabla" onchange="this.form.submit()">
                    <?php foreach ($tablas as $t): ?>
                        <option value="<?= escapar($t) ?>" <?= $t === $tabla_actual ? 'selected' : '' ?>><?= escapar($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if ($tabla_actual && $pk_field): ?>
                <a href="admin.php?tabla=<?= urlencode($tabla_actual) ?>&new=1<?= $db_pagina > 1 ? '&db_pag=' . $db_pagina : '' ?>" class="btn-sm btn-success">
                    <i class="fas fa-plus"></i> Nuevo registro
                </a>
            <?php endif; ?>

            <span style="margin-left:auto;font-size:.8rem;color:var(--text-secondary);word-break:break-word;">
                <strong><?= number_format($db_total) ?></strong> registros
                <?php if ($tabla_actual): ?> en <code style="background:var(--bg-card);padding:.1rem .4rem;border-radius:4px;"><?= escapar($tabla_actual) ?></code><?php endif; ?>
            </span>
        </div>

        <?php if (!$tabla_actual): ?>
            <p>No hay tablas en la base de datos.</p>
        <?php elseif (!$pk_field): ?>
            <div class="error" style="border-left-color:var(--warning)">
                <i class="fas fa-exclamation-triangle"></i>
                La tabla <strong><?= escapar($tabla_actual) ?></strong> no tiene clave primaria única.
            </div>
        <?php else: ?>
            <div class="db-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <?php foreach ($columnas_info as $c): ?>
                                <th><?= escapar($c['Field']) ?><?php if ($c['Key'] === 'PRI'): ?><span class="db-pk-badge">PK</span><?php endif; ?></th>
                            <?php endforeach; ?>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($db_rows)): ?>
                            <tr><td colspan="<?= count($columnas_info) + 1 ?>" style="text-align:center;padding:1.5rem;color:var(--text-secondary);">Tabla vacía</td></tr>
                        <?php else: foreach ($db_rows as $row): ?>
                            <tr>
                                <?php foreach ($columnas_info as $c):
                                    $v = $row[$c['Field']] ?? null;
                                    if ($v === null) {
                                        $display = '<span class="db-null">NULL</span>';
                                    } elseif (strpos($c['Field'], 'password') !== false && strpos($c['Field'], 'hash') !== false) {
                                        $display = '<span style="color:var(--text-light)">••••••</span>';
                                    } else {
                                        $display = '<span class="db-truncate" title="' . escapar($v) . '">' . escapar(mb_strimwidth((string)$v, 0, 60, '...')) . '</span>';
                                    }
                                ?>
                                    <td><?= $display ?></td>
                                <?php endforeach; ?>
                                <td style="white-space:nowrap;">
                                    <a href="admin.php?tabla=<?= urlencode($tabla_actual) ?>&edit=<?= urlencode($row[$pk_field]) ?><?= $db_pagina > 1 ? '&db_pag=' . $db_pagina : '' ?>" class="db-btn-icon btn-primary" title="Editar">
                                        <i class="fas fa-pencil-alt"></i>
                                    </a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este registro de <?= escapar($tabla_actual) ?>?');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="db_delete">
                                        <input type="hidden" name="db_table" value="<?= escapar($tabla_actual) ?>">
                                        <input type="hidden" name="db_pk" value="<?= escapar($row[$pk_field]) ?>">
                                        <?php if ($db_pagina > 1): ?><input type="hidden" name="db_pag" value="<?= $db_pagina ?>"><?php endif; ?>
                                        <button class="db-btn-icon btn-danger" title="Eliminar"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($db_total_paginas > 1): ?>
                <div style="display:flex;justify-content:center;align-items:center;gap:.3rem;margin-top:1rem;flex-wrap:wrap;">
                    <?php if ($db_pagina > 1): ?>
                        <a href="?tabla=<?= urlencode($tabla_actual) ?>&db_pag=<?= $db_pagina - 1 ?>" class="btn-sm btn-secondary"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    <?php
                        $ini = max(1, $db_pagina - 2);
                        $fin = min($db_total_paginas, $db_pagina + 2);
                        if ($ini > 1) { echo '<a href="?tabla=' . urlencode($tabla_actual) . '&db_pag=1" class="btn-sm btn-secondary">1</a>'; if ($ini > 2) echo '<span style="color:var(--text-secondary)">…</span>'; }
                        for ($i = $ini; $i <= $fin; $i++) {
                            $cls = $i === $db_pagina ? 'btn-primary' : 'btn-secondary';
                            echo '<a href="?tabla=' . urlencode($tabla_actual) . '&db_pag=' . $i . '" class="btn-sm ' . $cls . '">' . $i . '</a>';
                        }
                        if ($fin < $db_total_paginas) { if ($fin < $db_total_paginas - 1) echo '<span style="color:var(--text-secondary)">…</span>'; echo '<a href="?tabla=' . urlencode($tabla_actual) . '&db_pag=' . $db_total_paginas . '" class="btn-sm btn-secondary">' . $db_total_paginas . '</a>'; }
                    ?>
                    <?php if ($db_pagina < $db_total_paginas): ?>
                        <a href="?tabla=<?= urlencode($tabla_actual) ?>&db_pag=<?= $db_pagina + 1 ?>" class="btn-sm btn-secondary"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL EDITAR / NUEVO -->
<?php if ($edit_mode && $tabla_actual && $pk_field): ?>
<div class="modal-overlay open" id="dbEditModal">
    <div class="modal-content" style="max-width:min(700px, calc(100vw - 2rem));">
        <button class="modal-close" onclick="window.location='admin.php?tabla=<?= urlencode($tabla_actual) ?><?= $db_pagina > 1 ? '&db_pag=' . $db_pagina : '' ?>'"><i class="fas fa-times"></i></button>
        <h2 style="margin-bottom:1rem;font-size:1.1rem;word-break:break-word;">
            <i class="fas fa-<?= $edit_mode === 'update' ? 'pencil-alt' : 'plus-circle' ?>"></i>
            <?= $edit_mode === 'update' ? 'Editar' : 'Nuevo' ?> registro en <code style="background:var(--bg-hover);padding:.15rem .5rem;border-radius:4px;font-size:.85rem;"><?= escapar($tabla_actual) ?></code>
        </h2>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="db_<?= $edit_mode === 'update' ? 'update' : 'insert' ?>">
            <input type="hidden" name="db_table" value="<?= escapar($tabla_actual) ?>">
            <?php if ($edit_mode === 'update'): ?><input type="hidden" name="db_pk" value="<?= escapar($edit_row[$pk_field]) ?>"><?php endif; ?>
            <?php if ($db_pagina > 1): ?><input type="hidden" name="db_pag" value="<?= $db_pagina ?>"><?php endif; ?>

            <div style="max-height:60vh;overflow-y:auto;padding-right:.4rem;">
                <?php foreach ($columnas_info as $c):
                    $f = $c['Field'];
                    $isAutoInc = stripos($c['Extra'], 'auto_increment') !== false;
                    $isPk = $c['Key'] === 'PRI';
                    $val = $edit_row[$f] ?? null;

                    if ($edit_mode === 'update' && $isPk) {
                        echo '<div class="form-group"><label style="word-break:break-word;">' . escapar($f) . ' <small style="color:var(--text-light)">(PK - solo lectura)</small></label><input type="text" value="' . escapar($val ?? '') . '" readonly disabled style="width:100%;padding:.55rem .7rem;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-hover);color:var(--text-secondary);font-family:inherit;cursor:not-allowed;box-sizing:border-box;"></div>';
                        continue;
                    }
                    if ($edit_mode === 'insert' && $isAutoInc) {
                        echo '<div class="form-group"><label style="word-break:break-word;">' . escapar($f) . ' <small style="color:var(--text-light)">(AUTO_INCREMENT)</small></label><input type="text" value="Se genera automáticamente" readonly disabled style="width:100%;padding:.55rem .7rem;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-hover);color:var(--text-secondary);font-family:inherit;cursor:not-allowed;box-sizing:border-box;"></div>';
                        continue;
                    }
                    ?>
                    <div class="form-group">
                        <label style="word-break:break-word;"><?= escapar($f) ?> <small style="color:var(--text-light)">(<?= escapar($c['Type']) ?><?= $c['Null'] === 'YES' ? ' · NULL' : ' · NOT NULL' ?>)</small></label>
                        <?= renderDbInput($c, $val) ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="display:flex;gap:.5rem;margin-top:1rem;justify-content:flex-end;flex-wrap:wrap;">
                <a href="admin.php?tabla=<?= urlencode($tabla_actual) ?><?= $db_pagina > 1 ? '&db_pag=' . $db_pagina : '' ?>" class="btn-sm btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
                <button type="submit" class="btn-sm btn-success"><i class="fas fa-save"></i> Guardar cambios</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modales auxiliares -->
<div class="modal-overlay" id="denegarApkModal">
    <div class="modal-content">
        <h2>Denegar APK</h2>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="denegar_apk">
            <input type="hidden" name="solicitud_id" id="denegarApkId">
            <div class="form-group"><label>Motivo *</label><textarea name="motivo" required rows="3" style="width:100%;box-sizing:border-box;"></textarea></div>
            <button type="submit" class="btn-sm btn-danger">Denegar</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="denegarDevModal">
    <div class="modal-content">
        <h2>Denegar Solicitud</h2>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="denegar_desarrollador">
            <input type="hidden" name="solicitud_id" id="denegarDevId">
            <div class="form-group"><label>Motivo *</label><textarea name="motivo" required rows="3" style="width:100%;box-sizing:border-box;"></textarea></div>
            <button type="submit" class="btn-sm btn-danger">Denegar</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="previewImgModal">
    <button class="preview-close" onclick="cerrarPreview()"><i class="fas fa-times"></i></button>
    <img id="previewImgElement" src="" alt="Preview">
    <p class="preview-name" id="previewImgName"></p>
</div>

<div class="modal-overlay" id="compresionOverlay">
    <div class="modal-content">
        <h2 style="font-size:1.1rem;"><i class="fas fa-compress"></i> Comprimiendo imágenes</h2>
        <p style="color:var(--text-secondary);font-size:.9rem;margin-bottom:.8rem;word-break:break-word;" id="compresionStatus">Preparando…</p>
        <div id="compresionBarWrap"><div id="compresionBar"></div></div>
        <div id="compresionLog"></div>
        <div style="display:flex;justify-content:flex-end;margin-top:1rem;">
            <button type="button" id="compresionCloseBtn" class="btn-sm btn-primary" style="display:none;" onclick="document.getElementById('compresionOverlay').classList.remove('open'); window.location.reload();">
                <i class="fas fa-check"></i> Cerrar y recargar
            </button>
        </div>
    </div>
</div>

<script>
/* Collapse */
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.admin-section').forEach(function(sec) {
        const header = sec.querySelector('h2');
        if (!header) return;
        const body = document.createElement('div');
        body.className = 'section-body';
        let next = header.nextSibling;
        while (next) { const toMove = next; next = next.nextSibling; body.appendChild(toMove); }
        sec.appendChild(body);
        const headerWrap = document.createElement('div');
        headerWrap.className = 'section-header-wrap';
        header.parentNode.insertBefore(headerWrap, header);
        headerWrap.appendChild(header);

        const isCollapsed = sec.classList.contains('collapsed');
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'toggle-section-btn';
        btn.title = isCollapsed ? 'Maximizar' : 'Minimizar';
        btn.innerHTML = isCollapsed ? '<i class="fas fa-plus"></i>' : '<i class="fas fa-minus"></i>';
        headerWrap.appendChild(btn);

        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const collapsed = sec.classList.toggle('collapsed');
            btn.innerHTML = collapsed ? '<i class="fas fa-plus"></i>' : '<i class="fas fa-minus"></i>';
            btn.title = collapsed ? 'Maximizar' : 'Minimizar';
        });
        header.addEventListener('click', function(e) {
            if (e.target !== btn && !btn.contains(e.target)) btn.click();
        });
    });
});

function escHtml(s) { return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function formatBytes(b) { b = Number(b) || 0; if (b >= 1048576) return (b / 1048576).toFixed(2) + ' MB'; if (b >= 1024) return (b / 1024).toFixed(1) + ' KB'; return b + ' B'; }

document.querySelectorAll('.modal-overlay').forEach(m => { m.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); }); });

function mostrarDenegarApk(id) { document.getElementById('denegarApkId').value = id; document.getElementById('denegarApkModal').classList.add('open'); }
function mostrarDenegarDev(id) { document.getElementById('denegarDevId').value = id; document.getElementById('denegarDevModal').classList.add('open'); }

function mostrarPreview(src, nombre, peso) {
    document.getElementById('previewImgElement').src = src;
    document.getElementById('previewImgName').textContent = nombre + (peso ? ' · ' + peso : '');
    document.getElementById('previewImgModal').classList.add('open');
}
function cerrarPreview() { document.getElementById('previewImgModal').classList.remove('open'); }
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') cerrarPreview(); });

function seleccionarTodas(v) {
    document.querySelectorAll('.chk-img').forEach(c => c.checked = v);
    const chkAll = document.getElementById('chkAll');
    if (chkAll) chkAll.checked = v;
}

async function comprimirEnCliente(url, calidad, maxAncho) {
    if (/\.gif$/i.test(url)) throw new Error('GIF no soportado');
    let mime = 'image/jpeg';
    if (/\.png$/i.test(url)) mime = 'image/png';
    else if (/\.webp$/i.test(url)) mime = 'image/webp';

    const img = await new Promise((resolve, reject) => {
        const i = new Image();
        i.onload = () => resolve(i);
        i.onerror = () => reject(new Error('No se pudo cargar la imagen'));
        i.src = url + (url.includes('?') ? '&' : '?') + 'v=' + Date.now();
    });

    let { width, height } = img;
    if (width > maxAncho) { height = Math.round(height * maxAncho / width); width = maxAncho; }

    const canvas = document.createElement('canvas');
    canvas.width = width; canvas.height = height;
    const ctx = canvas.getContext('2d');
    if (mime === 'image/png' || mime === 'image/webp') ctx.clearRect(0, 0, width, height);
    ctx.drawImage(img, 0, 0, width, height);

    return new Promise((resolve, reject) => {
        canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('Error al exportar')), mime, calidad / 100);
    });
}

async function subirComprimida(archivo_rel, blob, csrf) {
    const fd = new FormData();
    fd.append('action', 'reemplazar_imagen');
    fd.append('archivo', archivo_rel);
    fd.append('csrf_token', csrf);
    fd.append('imagen', blob, archivo_rel.split('/').pop());
    const res = await fetch('api.php', { method: 'POST', body: fd });
    const text = await res.text();
    try { return JSON.parse(text); }
    catch (e) { return { success: false, message: 'Respuesta inválida (HTTP ' + res.status + ')' }; }
}

async function comprimirSeleccionadas() {
    const checks = Array.from(document.querySelectorAll('input[name="archivos[]"]:checked'));
    if (checks.length === 0) { alert('Selecciona al menos una imagen.'); return; }
    const calidad = document.getElementById('comCalidad').value;
    const maxAncho = parseInt(document.getElementById('comMaxAncho').value) || 256;
    if (!confirm(`¿Comprimir ${checks.length} imagen(es)?`)) return;

    const overlay = document.getElementById('compresionOverlay');
    const bar = document.getElementById('compresionBar');
    const status = document.getElementById('compresionStatus');
    const log = document.getElementById('compresionLog');
    const closeBtn = document.getElementById('compresionCloseBtn');

    log.innerHTML = ''; bar.style.width = '0%'; bar.textContent = '';
    closeBtn.style.display = 'none';
    overlay.classList.add('open');

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let ok = 0, sinCambio = 0, err = 0, ahorroTotal = 0;

    for (let i = 0; i < checks.length; i++) {
        const archivo = checks[i].value;
        const nombre = archivo.split('/').pop();
        status.textContent = `Procesando ${i + 1}/${checks.length}: ${nombre}`;
        bar.style.width = ((i / checks.length) * 100) + '%';
        try {
            const blob = await comprimirEnCliente(archivo, parseInt(calidad) || 80, maxAncho);
            const result = await subirComprimida(archivo, blob, csrf);
            if (result.success) {
                if (result.sin_cambio) { sinCambio++; log.innerHTML += `<div style="color:var(--text-secondary)">⏭ ${escHtml(nombre)}: ${escHtml(result.message)}</div>`; }
                else { ok++; ahorroTotal += Number(result.ahorro) || 0; log.innerHTML += `<div style="color:var(--success)">✓ ${escHtml(nombre)}: ${formatBytes(result.antes)} → ${formatBytes(result.despues)}</div>`; }
            } else { err++; log.innerHTML += `<div style="color:var(--danger)">✗ ${escHtml(nombre)}: ${escHtml(result.message)}</div>`; }
        } catch (e) { err++; log.innerHTML += `<div style="color:var(--danger)">✗ ${escHtml(nombre)}: ${escHtml(e.message)}</div>`; }
        log.scrollTop = log.scrollHeight;
        bar.style.width = (((i + 1) / checks.length) * 100) + '%';
        bar.textContent = Math.round(((i + 1) / checks.length) * 100) + '%';
    }
    status.innerHTML = `<strong>Listo:</strong> ${ok} comprimidas · ${sinCambio} sin cambios · ${err} errores · Ahorro: <strong style="color:var(--success)">${formatBytes(ahorroTotal)}</strong>`;
    closeBtn.style.display = 'inline-flex';
}

async function escanearVirusTotal(id, btn) {
    const resultBox = document.getElementById('vt-result-' + id);
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Escaneando (15s)...';
    resultBox.className = 'vt-result'; resultBox.style.display = 'none';
    const body = new URLSearchParams({ action: 'scan_virustotal', solicitud_id: id, csrf_token: document.querySelector('meta[name="csrf-token"]').content });
    try {
        const res = await fetch('api.php', { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            resultBox.textContent = 'Resultado: ' + data.resultado;
            if (data.resultado.includes('peligro')) resultBox.className = 'vt-result vt-peligro';
            else if (data.resultado.includes('limpio')) resultBox.className = 'vt-result vt-limpio';
            else resultBox.className = 'vt-result vt-error';
        } else {
            resultBox.textContent = 'Error: ' + data.message;
            resultBox.className = 'vt-result vt-error';
        }
    } catch (err) {
        resultBox.textContent = 'Error de red al conectar con VirusTotal.';
        resultBox.className = 'vt-result vt-error';
    }
    btn.innerHTML = '<i class="fas fa-shield-virus"></i> Ya escaneado';
}
</script>

</body>
</html>