<?php
set_time_limit(120); 
require_once 'config/database.php';
require_once 'config/funciones.php';

if (!estaLogueado() || !esDesarrollador()) { header('Location: index.php'); exit; }

$usuario_id = $_SESSION['usuario_id'];
$error = '';
$mensaje = '';
$MAX_MB = 2;
$MAX_MB_APK = 8;

function validarPostMaxSize() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        return "El archivo que intentaste subir es demasiado grande para el servidor (límite de 8MB).";
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error_post = validarPostMaxSize();
    if ($error_post) $error = $error_post;
}

// ===== SUBIR NUEVA APK =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'subir_apk' && !$error) {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) $error = 'Token inválido.';
    elseif (!verificarRateLimit('subir_apk', 5, 60)) $error = 'Demasiados intentos.';
    else {
        $nombre = limpiarEntrada($_POST['nombre'] ?? '');
        $version = limpiarEntrada($_POST['version'] ?? '');
        $categoria = limpiarEntrada($_POST['categoria'] ?? '');
        $descripcion = limpiarEntrada($_POST['descripcion'] ?? '');
        $changelog = limpiarEntrada($_POST['changelog'] ?? '');
        $metodo_subida = $_POST['metodo_subida'] ?? 'archivo';
        $enlace_apk = limpiarEntrada($_POST['enlace_apk'] ?? '');
        $link_url = limpiarEntrada($_POST['link_url'] ?? '');
        $link_texto = limpiarEntrada($_POST['link_texto'] ?? '');

        $categorias_validas = array_keys(obtenerCategoriasDisponibles());

        if (mb_strlen($nombre) < 3) $error = 'Nombre inválido (mínimo 3 letras)';
        elseif (mb_strlen($version) < 1) $error = 'Versión inválida';
        elseif (!in_array($categoria, $categorias_validas, true)) $error = 'Categoría inválida';
        elseif (mb_strlen($descripcion) < 10) $error = 'La descripción debe tener al menos 10 caracteres';
        else {
            $archivo_apk_final = '';
            
            if ($metodo_subida === 'enlace') {
                if (!filter_var($enlace_apk, FILTER_VALIDATE_URL)) {
                    $error = 'El enlace de la APK no es válido (debe empezar con http:// o https://).';
                } else {
                    $archivo_apk_final = $enlace_apk;
                }
            } else {
                $check_apk = validarArchivoSubido($_FILES['archivo_apk'] ?? null, 'apk', $MAX_MB_APK);
                if (!$check_apk['ok']) $error = 'APK: ' . $check_apk['error'];
            }

            $check_ico  = validarArchivoSubido($_FILES['icono'] ?? null, 'imagen', $MAX_MB);
            $check_ss1  = validarArchivoSubido($_FILES['screenshot1'] ?? null, 'imagen', $MAX_MB);
            $check_ss2  = validarArchivoSubido($_FILES['screenshot2'] ?? null, 'imagen', $MAX_MB);

            if (!$check_ico['ok']) $error = 'Icono: ' . $check_ico['error'];
            elseif (!$check_ss1['ok']) $error = 'Captura 1: ' . $check_ss1['error'];
            elseif (!$check_ss2['ok']) $error = 'Captura 2: ' . $check_ss2['error'];
            
            if (!$error) {
                $temp_dir = 'uploads/temp/';
                if (!is_dir($temp_dir)) mkdir($temp_dir, 0777, true);
                $ts = time();
                
                if ($metodo_subida === 'archivo') {
                    $archivo_apk_final = $temp_dir . 'apk_' . $usuario_id . '_' . $ts . '.apk';
                    $ok = move_uploaded_file($_FILES['archivo_apk']['tmp_name'], $archivo_apk_final);
                    if (!$ok) $error = 'Error al mover el APK al servidor.';
                } else {
                    $ok = true;
                }

                if ($ok) {
                    $ico_ext  = obtenerExtensionSegura($_FILES['icono']['name'], $check_ico['mime']);
                    $ss1_ext  = obtenerExtensionSegura($_FILES['screenshot1']['name'], $check_ss1['mime']);
                    $ss2_ext  = obtenerExtensionSegura($_FILES['screenshot2']['name'], $check_ss2['mime']);
                    $ico_file = $temp_dir . 'icon_' . $usuario_id . '_' . $ts . '.' . $ico_ext;
                    $ss1_file = $temp_dir . 'ss1_'  . $usuario_id . '_' . $ts . '.' . $ss1_ext;
                    $ss2_file = $temp_dir . 'ss2_'  . $usuario_id . '_' . $ts . '.' . $ss2_ext;

                    move_uploaded_file($_FILES['icono']['tmp_name'], $ico_file);
                    move_uploaded_file($_FILES['screenshot1']['tmp_name'], $ss1_file);
                    move_uploaded_file($_FILES['screenshot2']['tmp_name'], $ss2_file);

                    try {
                        $stmt = $pdo->prepare("INSERT INTO solicitudes_apk (desarrollador_id, nombre, version, categoria, descripcion, changelog, archivo_apk, icono_temp, screenshot1, screenshot2, tipo, link_url, link_texto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'nueva', ?, ?)");
                        $stmt->execute([$usuario_id, $nombre, $version, $categoria, $descripcion, $changelog, $archivo_apk_final, $ico_file, $ss1_file, $ss2_file, $link_url, $link_texto]);
                        header('Location: mi-desarrollo.php?success=1');
                        exit;
                    } catch (PDOException $e) {
    foreach ([$archivo_apk_final, $ico_file, $ss1_file, $ss2_file] as $f) if (file_exists($f) && $metodo_subida === 'archivo') @unlink($f);
    $error = 'Error en BD: ' . $e->getMessage(); // 👈 Ahora te dirá el error exacto
}
                }
            }
        }
    }
}

if (isset($_GET['success'])) $mensaje = 'Enviado para revisión. Te notificaremos cuando sea aprobado.';

$stmt = $pdo->prepare("SELECT * FROM apks WHERE creador_id = ? ORDER BY creado_en DESC"); $stmt->execute([$usuario_id]); $mis_apks = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT * FROM solicitudes_apk WHERE desarrollador_id = ? ORDER BY creado_en DESC LIMIT 20"); $stmt->execute([$usuario_id]); $solicitudes = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT * FROM notificaciones WHERE usuario_id = ? ORDER BY creado_en DESC LIMIT 5"); $stmt->execute([$usuario_id]); $notifs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta name="robots" content="noindex, nofollow">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= generarCSRF() ?>">
    <title>Mi Desarrollo - YSWEBAPK</title>
    <link rel="icon" href="Ico.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .dev-container { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }
        .dev-section { background: var(--bg-card); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 2rem; box-shadow: var(--shadow-md); border: 1px solid var(--border-color); }
        .file-input-wrapper { border: 2px dashed var(--border-color); padding: 1rem; border-radius: var(--radius-md); text-align: center; cursor: pointer; transition: var(--transition); }
        .file-input-wrapper:hover { border-color: var(--primary); background: var(--bg-hover); }
        .file-input-wrapper input[type="file"] { display: none; }
        .file-input-wrapper .file-label i { font-size: 2rem; display: block; margin-bottom: .3rem; color: var(--primary); }
        .apk-card-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; }
        .apk-item { background: var(--bg-hover); border-radius: var(--radius-md); padding: 1rem; text-align: center; border: 1px solid var(--border-color); }
        .apk-item .app-icon { width: 60px; height: 60px; margin: 0 auto .5rem; border-radius: var(--radius-md); overflow: hidden; background: var(--bg-card); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; color: var(--primary); }
        .apk-item .app-icon img { width: 100%; height: 100%; object-fit: cover; }
        .status { display: inline-block; padding: .1rem .6rem; border-radius: 999px; font-size: .7rem; font-weight: 600; }
        .status.pendiente { background: var(--warning-bg); color: var(--warning); }
        .status.aprobada { background: var(--success-bg); color: var(--success); }
        .status.denegada { background: var(--danger-bg); color: var(--danger); }
        .info-box { background: var(--warning-bg); padding: .7rem 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; color: var(--warning); border-left: 4px solid var(--warning); }
        .btn-submit-upload { padding: 0.7rem 1.5rem; background: var(--primary); color: white; border: none; border-radius: var(--radius-md); font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-family: inherit; transition: var(--transition-fast); }
        .btn-submit-upload:hover { background: var(--primary-hover); transform: translateY(-1px); }
        .error-text { color: var(--danger); font-size: 0.8rem; margin-top: 0.3rem; display: none; }
    </style>
</head>
<body>

<div class="dev-container">
    <h1><i class="fas fa-code" style="color:var(--primary)"></i> Mi Desarrollo</h1>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;">
        <a href="index.php" class="btn-sm btn-primary" style="padding:.5rem 1rem;text-decoration:none;background:var(--primary);color:white;border-radius:8px;"><i class="fas fa-arrow-left"></i> Volver</a>
        <a href="soporte.php" class="btn-sm btn-success" style="padding:.5rem 1rem;text-decoration:none;color:white;border-radius:8px;"><i class="fas fa-headset"></i> Chat Admin</a>
    </div>

    <?php if ($error): ?><div class="error"><?= escapar($error) ?></div><?php endif; ?>
    <?php if ($mensaje): ?><div class="mensaje"><?= escapar($mensaje) ?></div><?php endif; ?>

    <?php if (!empty($notifs)): ?>
        <div class="dev-section">
            <h2><i class="fas fa-bell"></i> Notificaciones recientes</h2>
            <div class="notif-list">
                <?php foreach ($notifs as $n): ?>
                    <div class="notif-mini <?= $n['leida'] ? '' : 'unread' ?>">
                        <strong><?= escapar($n['titulo']) ?></strong>
                        <?php if ($n['mensaje']): ?><br><?= escapar($n['mensaje']) ?><?php endif; ?>
                        <div style="font-size:.7rem;color:var(--text-light);margin-top:.2rem;"><?= escapar(tiempoRelativo($n['creado_en'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="dev-section">
        <h2><i class="fas fa-plus-circle"></i> Subir Nueva APK</h2>
        <div class="info-box"><i class="fas fa-clock"></i> Las APKs pueden tardar hasta 1 mes en ser revisadas.</div>

        <form method="POST" enctype="multipart/form-data" data-upload="nueva" id="formNuevaApk" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="subir_apk">

            <div class="form-group">
                <label>Nombre de la APK * (mín. 3 caracteres)</label>
                <input type="text" name="nombre" id="txt-nombre" required minlength="3" maxlength="100">
                <div id="err-nombre" class="error-text">Mínimo 3 caracteres.</div>
            </div>
            
            <div class="form-group">
                <label>Versión * (Ej: 1.0 o 1.0.5)</label>
                <input type="text" name="version" id="txt-version" required pattern="[0-9]+(\.[0-9]+)*" placeholder="1.0">
                <div id="err-version" class="error-text">Usa solo números y puntos. Ej: 1.0</div>
            </div>

            <div class="form-group">
                <label>Categoría *</label>
                <select name="categoria" id="sel-categoria" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach (obtenerCategoriasDisponibles() as $slug => $label): ?>
                        <option value="<?= escapar($slug) ?>"><?= escapar($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="err-categoria" class="error-text">Selecciona una categoría.</div>
            </div>

            <div class="form-group">
                <label>Descripción * (mín. 10 caracteres)</label>
                <textarea name="descripcion" id="txt-descripcion" required rows="4" minlength="10"></textarea>
                <div id="err-descripcion" class="error-text">Mínimo 10 caracteres.</div>
            </div>

            <!-- ENLACE EXTRA DEBAJO DE LA DESCRIPCIÓN -->
            <div style="background:var(--bg-hover);padding:1rem;border-radius:8px;margin-bottom:1rem;border:1px solid var(--border-color);">
                <p style="font-weight:600;margin-bottom:.5rem;font-size:.9rem;"><i class="fas fa-link"></i> Enlace Extra (Opcional, debajo de la descripción)</p>
                <div class="form-group">
                    <input type="url" name="link_url" placeholder="URL del enlace (Ej: https://ejemplo.com/guia)">
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <input type="text" name="link_texto" placeholder="Texto a mostrar (Ej: Ver Guía)">
                </div>
            </div>

            <div class="form-group">
                <label>Novedades de esta versión (Changelog)</label>
                <textarea name="changelog" rows="3" placeholder="Ej: Se agregó modo oscuro..."></textarea>
            </div>

            <!-- SELECTOR ARCHIVO O ENLACE -->
            <div class="form-group">
                <label>Método de Subida *</label>
                <select name="metodo_subida" id="metodo_subida" onchange="toggleMetodoSubida()" style="background:var(--bg-input);color:var(--text-primary);padding:0.7rem;border-radius:12px;border:1px solid var(--border-color);width:100%;">
                    <option value="archivo">Subir archivo APK (Máx 8MB)</option>
                    <option value="enlace">Enlace Externo (Google Drive, Mediafire, etc.)</option>
                </select>
            </div>

            <!-- INPUT PARA ENLACE EXTERNO -->
            <div id="div_enlace" style="display:none;margin-bottom:1rem;">
                <div class="form-group">
                    <label>Enlace de descarga de la APK *</label>
                    <input type="url" name="enlace_apk" id="enlace_apk" placeholder="https://drive.google.com/...">
                    <div id="err-enlace_apk" class="error-text">Ingresa un enlace válido (http/https).</div>
                </div>
            </div>

            <!-- ✅ INPUTS DE ARCHIVO: solo se oculta el campo del APK cuando es enlace -->
            <div id="div_archivo">
                <div class="form-group"><b style="color:var(--danger)">Límite: 2 MB por imagen · 8 MB para APK</b></div>
                <?php
                    $campos = [
                        'archivo_apk' => ['label' => 'Archivo APK (.apk)', 'max' => $MAX_MB_APK, 'accept' => '.apk', 'tipo' => 'apk'],
                        'icono'       => ['label' => 'Icono (imagen)',       'max' => $MAX_MB, 'accept' => 'image/*', 'tipo' => 'imagen'],
                        'screenshot1' => ['label' => 'Captura 1 (imagen)',   'max' => $MAX_MB, 'accept' => 'image/*', 'tipo' => 'imagen'],
                        'screenshot2' => ['label' => 'Captura 2 (imagen)',   'max' => $MAX_MB, 'accept' => 'image/*', 'tipo' => 'imagen'],
                    ];
                    foreach ($campos as $id => $conf):
                ?>
                    <div class="form-group" id="grupo-<?= $id ?>">
                        <label><?= $conf['label'] ?> *</label>
                        <div class="file-input-wrapper" onclick="document.getElementById('<?= $id ?>').click()">
                            <div class="file-label">
                                <i class="fas fa-<?= $id==='archivo_apk'?'file-archive':'image' ?>"></i>
                                <span id="lbl-<?= $id ?>">Seleccionar <?= $conf['label'] ?></span>
                            </div>
                            <input type="file" id="<?= $id ?>" name="<?= $id ?>" required
                                   data-max-mb="<?= $conf['max'] ?>"
                                   data-label-default="Seleccionar <?= $conf['label'] ?>"
                                   data-label-id="lbl-<?= $id ?>"
                                   data-tipo="<?= $conf['tipo'] ?>"
                                   accept="<?= $conf['accept'] ?>"
                                   onchange="validarArchivoInput(this)">
                        </div>
                        <div id="err-<?= $id ?>" class="error-text">Debes seleccionar un archivo válido.</div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn-submit-upload" id="btnSubmitNueva">
                <i class="fas fa-upload"></i> Enviar para Revisión
            </button>
        </form>
    </div>

    <div class="dev-section">
        <h2><i class="fas fa-check-circle"></i> Mis APKs Publicadas</h2>
        <?php if (empty($mis_apks)): ?><p style="color:var(--text-secondary)">Aún no has publicado ninguna APK.</p>
        <?php else: ?>
            <div class="apk-card-list">
                <?php foreach ($mis_apks as $a): ?>
                    <div class="apk-item">
                        <div class="app-icon"><?php if ($a['icono'] && file_exists($a['icono'])): ?><img src="<?= escapar($a['icono']) ?>"><?php else: ?><i class="fas fa-cube"></i><?php endif; ?></div>
                        <div><strong><?= escapar($a['nombre']) ?></strong></div>
                        <div style="font-size:.8rem;color:var(--text-secondary)">v<?= escapar($a['version']) ?></div>
                        <div style="margin-top:.3rem"><span class="status aprobada">Publicada</span></div>
                        <button class="btn-sm btn-warning" style="margin-top:.5rem;padding:.35rem .8rem;border:none;border-radius:8px;cursor:pointer;background:var(--warning);color:white;font-weight:600;"
                            onclick="abrirActualizar(<?= (int)$a['id'] ?>, '<?= escapar($a['version']) ?>')">
                            <i class="fas fa-sync-alt"></i> Actualizar
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="dev-section">
        <h2><i class="fas fa-clock"></i> Mis Solicitudes</h2>
        <?php if (empty($solicitudes)): ?><p style="color:var(--text-secondary)">No tienes solicitudes.</p>
        <?php else: ?>
            <div class="apk-card-list">
                <?php foreach ($solicitudes as $s): ?>
                    <div class="apk-item">
                        <div><strong><?= escapar($s['nombre']) ?></strong></div>
                        <div style="font-size:.8rem;color:var(--text-secondary)">v<?= escapar($s['version']) ?></div>
                        <div style="margin-top:.3rem"><span class="status <?= escapar($s['estado']) ?>"><?= ucfirst(escapar($s['estado'])) ?></span></div>
                        <?php if ($s['estado']==='denegada' && $s['mensaje_denegacion']): ?><div style="font-size:.75rem;color:var(--danger);margin-top:.3rem"><strong>Motivo:</strong> <?= escapar($s['mensaje_denegacion']) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal actualizar -->
<div class="modal-overlay" id="modalActualizar">
    <div class="modal-content">
        <button class="modal-close" onclick="cerrarActualizar()"><i class="fas fa-times"></i></button>
        <h2>Actualizar APK</h2>
        <p style="font-size:.85rem;color:var(--text-secondary);margin-bottom:1rem">La versión nueva debe ser mayor que la actual.</p>
        <form method="POST" enctype="multipart/form-data" data-upload="actualizacion" id="formActualizar" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="solicitar_actualizacion">
            <input type="hidden" name="apk_id" id="upApkId">
            
            <div class="form-group">
                <label>Nueva versión *</label>
                <input type="text" name="version" id="upVersion" required pattern="[0-9]+(\.[0-9]+)*">
                <div id="err-upVersion" class="error-text">Usa solo números y puntos (Ej: 2.0).</div>
            </div>
            <div class="form-group">
                <label>Novedades de esta versión (Changelog)</label>
                <textarea name="changelog" rows="3" placeholder="¿Qué hay de nuevo?"></textarea>
            </div>
            <div class="form-group">
                <label>Nuevo APK *</label>
                <input type="file" name="archivo_apk" id="up-apk" accept=".apk" required data-tipo="apk" onchange="validarArchivoInput(this)">
                <div id="err-up-apk" class="error-text">Debe ser un archivo .apk válido.</div>
            </div>
            <div class="form-group">
                <label>Nuevo icono (opcional)</label>
                <input type="file" name="icono" id="up-icono" accept="image/*" data-tipo="imagen" onchange="validarArchivoInput(this)">
                <div id="err-up-icono" class="error-text">Debe ser una imagen válida.</div>
            </div>
            <div class="form-group">
                <label>Captura 1 *</label>
                <input type="file" name="screenshot1" id="up-ss1" accept="image/*" required data-tipo="imagen" onchange="validarArchivoInput(this)">
                <div id="err-up-ss1" class="error-text">Debe ser una imagen válida.</div>
            </div>
            <div class="form-group">
                <label>Captura 2 *</label>
                <input type="file" name="screenshot2" id="up-ss2" accept="image/*" required data-tipo="imagen" onchange="validarArchivoInput(this)">
                <div id="err-up-ss2" class="error-text">Debe ser una imagen válida.</div>
            </div>
            <button type="submit" class="btn-primary"><i class="fas fa-upload"></i> Enviar</button>
        </form>
    </div>
</div>

<!-- Modal uploading -->
<div class="upload-modal-overlay" id="uploadModal">
    <div class="upload-modal-content">
        <div class="upload-modal-spinner" id="uploadSpinner"><i class="fas fa-spinner fa-pulse"></i></div>
        <h3 class="upload-modal-title" id="uploadTitle">Subiendo archivos...</h3>
        <p class="upload-modal-message" id="uploadMessage">Por favor, no cierres esta ventana.</p>
        <button class="upload-modal-close-btn" id="uploadCloseBtn" onclick="hideUploadModal()" style="display:none;"><i class="fas fa-times"></i> Cerrar</button>
    </div>
</div>

<script>
// ===== TOGGLE METODO SUBIDA (CORREGIDO) =====
function toggleMetodoSubida() {
    const metodo = document.getElementById('metodo_subida').value;
    const divEnlace = document.getElementById('div_enlace');
    const grupoApk = document.getElementById('grupo-archivo_apk'); // solo el APK
    const fileInput = document.getElementById('archivo_apk');
    const enlaceInput = document.getElementById('enlace_apk');

    if (metodo === 'enlace') {
        grupoApk.style.display = 'none';   // ocultar solo el APK
        divEnlace.style.display = 'block';
        fileInput.required = false;
        enlaceInput.required = true;
    } else {
        grupoApk.style.display = 'block';  // mostrar el APK
        divEnlace.style.display = 'none';
        fileInput.required = true;
        enlaceInput.required = false;
    }
}

// ===== VALIDACIÓN DE ARCHIVOS =====
function validarArchivoInput(input) {
    const tipo = input.dataset.tipo;
    const maxMb = parseInt(input.dataset.maxMb || '8');
    const labelId = input.dataset.labelId;
    const labelDefault = input.dataset.labelDefault;
    const label = labelId ? document.getElementById(labelId) : null;
    const errDiv = document.getElementById('err-' + input.id);

    if (errDiv) errDiv.style.display = 'none';
    if (!input.files || !input.files.length) {
        if (label) label.textContent = labelDefault || 'Seleccionar archivo';
        return false;
    }

    const file = input.files[0];
    const sizeMB = file.size / (1024 * 1024);
    const ext = file.name.split('.').pop().toLowerCase();
    const imgExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (tipo === 'apk' && ext !== 'apk') {
        alert('❌ El archivo debe tener extensión .apk');
        input.value = '';
        if (label) label.textContent = labelDefault;
        return false;
    }
    if (tipo === 'imagen' && !imgExts.includes(ext)) {
        alert('❌ Formato no válido. Usa JPG, PNG, WEBP o GIF.');
        input.value = '';
        if (label) label.textContent = labelDefault;
        return false;
    }
    if (sizeMB > maxMb) {
        alert(`❌ El archivo pesa ${sizeMB.toFixed(2)} MB. El límite es ${maxMb} MB.`);
        input.value = '';
        if (label) label.textContent = labelDefault;
        return false;
    }
    
    if (label) label.textContent = file.name + ' (' + sizeMB.toFixed(2) + ' MB)';
    return true;
}

// ===== VALIDACIÓN GENERAL =====
function validarFormularioCompleto(form) {
    let isValid = true;
    
    const inputsText = form.querySelectorAll('input[required]:not([type="file"]), textarea[required], select[required]');
    
    inputsText.forEach(input => {
        const errDiv = document.getElementById('err-' + input.id);
        if (errDiv) errDiv.style.display = 'none';
        
        const val = input.value.trim();
        
        if (val === '') {
            isValid = false;
            if (errDiv) errDiv.style.display = 'block';
            return;
        }

        if (input.minLength > 0 && val.length < input.minLength) {
            isValid = false;
            if (errDiv) errDiv.style.display = 'block';
        }

        if (input.pattern && !new RegExp('^' + input.pattern + '$').test(val)) {
            isValid = false;
            if (errDiv) errDiv.style.display = 'block';
        }
    });

    // Solo validar el archivo APK si el método es "archivo"
    const metodo = document.getElementById('metodo_subida').value;
    if (metodo === 'archivo') {
        const fileInputs = form.querySelectorAll('input[type="file"][required]');
        fileInputs.forEach(input => {
            if (input.id === 'archivo_apk' || input.id === 'icono' || input.id === 'screenshot1' || input.id === 'screenshot2') {
                if (!input.files || input.files.length === 0) {
                    isValid = false;
                    const errDiv = document.getElementById('err-' + input.id);
                    if (errDiv) errDiv.style.display = 'block';
                }
            }
        });
    } else {
        // Validar solo iconos y capturas
        ['icono', 'screenshot1', 'screenshot2'].forEach(id => {
            const input = document.getElementById(id);
            if (!input.files || input.files.length === 0) {
                isValid = false;
                const errDiv = document.getElementById('err-' + id);
                if (errDiv) errDiv.style.display = 'block';
            }
        });
    }

    return isValid;
}

const uploadModal = document.getElementById('uploadModal');
const uploadSpinner = document.getElementById('uploadSpinner');
const uploadTitle = document.getElementById('uploadTitle');
const uploadMessage = document.getElementById('uploadMessage');
const uploadCloseBtn = document.getElementById('uploadCloseBtn');

function showUploadLoading() {
    uploadSpinner.innerHTML = '<i class="fas fa-spinner fa-pulse"></i>';
    uploadTitle.textContent = 'Subiendo archivos...';
    uploadMessage.textContent = 'Por favor, no cierres esta ventana.';
    uploadCloseBtn.style.display = 'none';
    uploadModal.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function showUploadError(message) {
    uploadSpinner.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:var(--danger)"></i>';
    uploadTitle.textContent = 'Error al subir';
    uploadMessage.textContent = message;
    uploadCloseBtn.style.display = 'inline-flex';
}
function hideUploadModal() {
    uploadModal.classList.remove('open');
    if (!document.querySelector('.modal-overlay.open, .menu-lateral.open')) document.body.style.overflow = '';
}

document.querySelectorAll('form[data-upload]').forEach(function(form) {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (!validarFormularioCompleto(this)) {
            alert('Por favor, revisa los campos resaltados en rojo.');
            return;
        }
        
        if (this.closest('#modalActualizar')) cerrarActualizar();
        showUploadLoading();
        
        const formData = new FormData(this);
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 180000);
            const response = await fetch(window.location.pathname, {
                method: 'POST',
                body: formData,
                signal: controller.signal
            });
            clearTimeout(timeoutId);
            if (response.redirected && response.url.indexOf('success=1') !== -1) {
                hideUploadModal();
                window.location.href = response.url;
                return;
            }
            if (response.ok) {
                const html = await response.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const errorEl = doc.querySelector('.error');
                if (errorEl && errorEl.textContent.trim()) showUploadError(errorEl.textContent.trim());
                else showUploadError('No se pudo completar la subida. Revisa tu conexión.');
            } else {
                showUploadError('Error del servidor (HTTP ' + response.status + ').');
            }
        } catch (err) {
            if (err.name === 'AbortError') showUploadError('La subida tardó demasiado. Revisa tu conexión.');
            else showUploadError('Error de conexión. Revisa tu internet.');
        }
    });
});

function abrirActualizar(id, versionActual) {
    document.getElementById('upApkId').value = id;
    document.getElementById('upVersion').placeholder = 'Actual: ' + versionActual;
    document.getElementById('modalActualizar').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function cerrarActualizar() {
    document.getElementById('modalActualizar').classList.remove('open');
    if (!document.querySelector('.upload-modal-overlay.open, .modal-overlay.open, .menu-lateral.open')) document.body.style.overflow = '';
}
document.getElementById('modalActualizar').addEventListener('click', function(e) {
    if (e.target === this) cerrarActualizar();
});
</script>

</body>
</html>