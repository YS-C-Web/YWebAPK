<?php
require_once 'config/database.php';
require_once 'config/funciones.php';

$apk_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($apk_id <= 0) { header('Location: index.php'); exit; }

// ===== APK =====
$stmt = $pdo->prepare("SELECT * FROM apks WHERE id = ?");
$stmt->execute([$apk_id]);
$apk = $stmt->fetch();
if (!$apk) { header('Location: index.php'); exit; }

// ===== LINK DE DESCARGA (soporta enlace externo o archivo interno) =====
$apk_link           = $apk['link'] ?? '';
$apk_es_externo     = !empty($apk_link) && filter_var($apk_link, FILTER_VALIDATE_URL);
$apk_descargable    = !empty($apk_link) && $apk_link !== '#' && $apk_link !== 'apks/' && $apk_link !== 'apks';

// Detectar el dominio actual para forzar URLs absolutas
$protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$base_url  = $protocolo . "://" . $_SERVER['HTTP_HOST'];

// URL para navegador → pasa por descargas.php (cuenta + sirve archivo)
$apk_href_descarga_web = $apk_es_externo
    ? $apk_link
    : ($apk_descargable ? $base_url . '/descargas.php?id=' . $apk_id : '#');

// URL para APK (App Inventor) → pasa por descargas.php para evitar el bloqueo de InfinityFree
$apk_href_descarga_app = $apk_es_externo
    ? $apk_link
    : ($apk_descargable ? $base_url . '/descargas.php?id=' . $apk_id : '#');

$apk_en_dev         = !empty($apk['en_desarrollo']);

// ===== CREADOR =====
$creador = null;
if (!empty($apk['creador_id'])) {
    $stmt = $pdo->prepare("SELECT id, nombre, rol, foto_perfil FROM usuarios WHERE id = ?");
    $stmt->execute([$apk['creador_id']]);
    $creador = $stmt->fetch();
}

// ===== MI VALORACIÓN =====
$mi_valoracion = null;
if (estaLogueado()) {
    $stmt = $pdo->prepare("SELECT estrellas FROM valoraciones WHERE usuario_id = ? AND apk_id = ?");
    $stmt->execute([$_SESSION['usuario_id'], $apk_id]);
    $row = $stmt->fetch();
    $mi_valoracion = $row ? (int)$row['estrellas'] : null;
}

// ===== COMENTARIOS (página 1) =====
$por_pagina_com = 10;
$pagina_com = max(1, intval($_GET['pag'] ?? 1));

$stmt = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE apk_id = ? AND (padre_id IS NULL OR padre_id = 0)");
$stmt->execute([$apk_id]);
$total_com_raiz = (int)$stmt->fetchColumn();
$pag_com = calcularPaginacion($total_com_raiz, $por_pagina_com, $pagina_com);

$stmt = $pdo->prepare("
    SELECT c.*, u.nombre, u.foto_perfil, u.rol AS usuario_rol
    FROM comentarios c
    JOIN usuarios u ON c.usuario_id = u.id
    WHERE c.apk_id = ? AND (c.padre_id IS NULL OR c.padre_id = 0)
    ORDER BY c.creado_en DESC
    LIMIT {$pag_com['por_pagina']} OFFSET {$pag_com['offset']}
");
$stmt->execute([$apk_id]);
$comentarios = $stmt->fetchAll();

foreach ($comentarios as &$c) {
    $stmt_r = $pdo->prepare("
        SELECT c.*, u.nombre, u.foto_perfil, u.rol AS usuario_rol
        FROM comentarios c JOIN usuarios u ON c.usuario_id = u.id
        WHERE c.padre_id = ? ORDER BY c.creado_en ASC
    ");
    $stmt_r->execute([$c['id']]);
    $c['respuestas'] = $stmt_r->fetchAll();
}
unset($c);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE apk_id = ?");
$stmt->execute([$apk_id]);
$total_comentarios = (int)$stmt->fetchColumn();

// ===== APKs DEL CREADOR =====
$apks_creador = [];
if ($creador) {
    $stmt = $pdo->prepare("
        SELECT id, nombre, version, icono FROM apks
        WHERE creador_id = ? AND (en_desarrollo = FALSE OR en_desarrollo IS NULL) AND id != ?
        ORDER BY id DESC LIMIT 12
    ");
    $stmt->execute([$creador['id'], $apk_id]);
    $apks_creador = $stmt->fetchAll();
}

// ===== VALORACIONES TOTALES =====
$stmt = $pdo->prepare("SELECT COUNT(*) FROM valoraciones WHERE apk_id = ?");
$stmt->execute([$apk_id]);
$total_valoraciones = (int)$stmt->fetchColumn();

// ===== LIKES DEL CREADOR + si yo le di like =====
$total_likes = 0;
$yo_di_like = false;
if ($creador) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM likes_usuario WHERE usuario_id = ?");
    $stmt->execute([$creador['id']]);
    $total_likes = (int)$stmt->fetchColumn();

    if (estaLogueado()) {
        $stmt = $pdo->prepare("SELECT id FROM likes_usuario WHERE usuario_id = ? AND liker_id = ?");
        $stmt->execute([$creador['id'], $_SESSION['usuario_id']]);
        $yo_di_like = (bool)$stmt->fetch();
    }
}

// ===== ¿YA REPORTÉ? =====
$ya_reporto = false;
if (estaLogueado()) {
    $stmt = $pdo->prepare("SELECT id FROM reportes WHERE apk_id = ? AND usuario_id = ? AND estado = 'pendiente'");
    $stmt->execute([$apk_id, $_SESSION['usuario_id']]);
    $ya_reporto = (bool)$stmt->fetch();
}

$icono_creador = $creador['foto_perfil'] ?? 'assets/default-avatar.png';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= generarCSRF() ?>">
    <?php
$desc_corta = mb_substr(strip_tags($apk['descripcion'] ?? ''), 0, 155);
$apk_url    = $base_url . '/apk-detalle.php?id=' . $apk_id;
$apk_img    = !empty($apk['icono']) && file_exists($apk['icono'])
              ? $base_url . '/' . $apk['icono']
              : 'IcoYSWEBAPK.jpg';

renderMetaSEO([
    'title'       => $apk['nombre'] . ' v' . $apk['version'] . ' APK - Descargar gratis | YSWEBAPK',
    'description' => 'Descarga ' . $apk['nombre'] . ' v' . $apk['version'] . ' APK para Android. ' . $desc_corta . '. Libre de virus y verificado.',
    'canonical'   => $apk_url,
    'image'       => $apk_img,
]);
?>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": <?= json_encode($apk['nombre'], JSON_UNESCAPED_UNICODE) ?>,
  "operatingSystem": "Android",
  "applicationCategory": <?= json_encode($apk['categoria'], JSON_UNESCAPED_UNICODE) ?>,
  "softwareVersion": <?= json_encode($apk['version'], JSON_UNESCAPED_UNICODE) ?>,
  "description": <?= json_encode($desc_corta, JSON_UNESCAPED_UNICODE) ?>,
  "image": <?= json_encode($apk_img, JSON_UNESCAPED_UNICODE) ?>,
  "offers": {
    "@type": "Offer",
    "price": "0",
    "priceCurrency": "USD"
  }
  <?php if ($apk['promedio_estrellas'] > 0 && $total_valoraciones > 0): ?>
  ,"aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "<?= number_format((float)$apk['promedio_estrellas'], 1) ?>",
    "ratingCount": "<?= (int)$total_valoraciones ?>"
  }
  <?php endif; ?>
  <?php if (!empty($creador['nombre'])): ?>
  ,"author": {
    "@type": "Person",
    "name": <?= json_encode($creador['nombre'], JSON_UNESCAPED_UNICODE) ?>
  }
  <?php endif; ?>
}
</script>
    <link rel="icon" href="Ico.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { padding-bottom: 200px; }
        .detalle-container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .detalle-header { display: flex; align-items: center; gap: 1.5rem; background: var(--bg-card); padding: 1.5rem; border-radius: 16px; box-shadow: var(--shadow-sm); margin-bottom: 1.5rem; border: 1px solid var(--border-color); }
        .detalle-header .icono { width: 80px; height: 80px; border-radius: 16px; overflow: hidden; flex-shrink: 0; background: var(--bg-hover); border: 1px solid var(--border-color); display:flex;align-items:center;justify-content:center;font-size:2.5rem;color:var(--primary);}
        .detalle-header .icono img { width: 100%; height: 100%; object-fit: cover; }
        .detalle-header .info h2 { margin: 0; font-size: 1.3rem; }
        .detalle-header .info .meta { font-size: 0.85rem; color: var(--text-secondary); }
        .detalle-header .info .creador { font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.2rem; }
        .detalle-header .info .creador a { color: var(--primary); font-weight: 600; }
        .detalle-actions { display: flex; gap: .5rem; margin-top: .5rem; flex-wrap: wrap; }
        .detalle-actions button { padding: .4rem .9rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-hover); color: var(--text-primary); font-weight: 600; font-size: .8rem; cursor: pointer; display: inline-flex; align-items: center; gap: .3rem; transition: var(--transition); }
        .detalle-actions button:hover { border-color: var(--primary); }
        .detalle-actions .reportar { color: var(--danger); }
        .detalle-actions .reportar:hover { background: var(--danger-bg); border-color: var(--danger); }

        .btn-descarga { padding: .4rem .9rem; text-decoration: none; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: .3rem; font-size: .8rem; font-weight: 600; background: var(--primary); color: white; border: none; cursor: pointer; font-family: inherit; }
        .btn-descarga:hover { background: var(--primary-hover); color: white; }
        .btn-descarga.disabled { background: var(--text-secondary); opacity: .6; cursor: not-allowed; }

        /* ===== SECCIÓN: DESCRIPCIÓN + ENLACE EXTRA ===== */
        .descripcion-card {
            background: var(--bg-card);
            padding: 1.2rem 1.5rem;
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
        }
        .descripcion-card .desc-titulo {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .descripcion-card .desc-titulo i { color: var(--primary); }
        .descripcion-card .desc-texto {
            font-size: 0.95rem;
            line-height: 1.6;
            color: var(--text-primary);
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .descripcion-card .desc-enlace {
            text-align: center;
            margin-top: 0.9rem;
            padding-top: 0.9rem;
            border-top: 1px solid var(--border-color);
        }
        .descripcion-card .desc-enlace a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: underline;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .descripcion-card .desc-enlace a:hover { color: var(--primary-hover); }

        .dev-banner-apk {
            background: var(--warning-bg);
            border: 1px solid var(--warning);
            border-radius: 12px;
            padding: 0.8rem 1rem;
            color: var(--warning);
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .valoracion-section { background: var(--bg-card); padding: 1.5rem; border-radius: 16px; box-shadow: var(--shadow-sm); margin-bottom: 1.5rem; text-align: center; border: 1px solid var(--border-color); }
        .valoracion-section .promedio { font-size: 3rem; font-weight: 700; color: var(--primary); }
        .valoracion-section .estrellas { font-size: 1.8rem; color: #f59e0b; margin: 0.3rem 0; }
        .valoracion-section .estrellas .empty { color: #e2e8f0; }
        .valoracion-section .calificar { margin-top: 1rem; }
        .valoracion-section .calificar .star-btn { background: none; border: none; font-size: 2rem; cursor: pointer; color: #e2e8f0; transition: color 0.2s; }
        .valoracion-section .calificar .star-btn.active { color: #f59e0b; }
        .valoracion-section .calificar .star-btn:hover { color: #f59e0b; }

        .comentarios-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding: 0 0.5rem; }
        .comentarios-header h3 { margin: 0; }
        .comentarios-header .total { font-size: 0.85rem; color: var(--text-secondary); }

        .comentario { background: var(--bg-card); padding: 1rem 1.5rem; border-radius: 16px; box-shadow: var(--shadow-sm); margin-bottom: 1rem; border-left: 4px solid var(--primary); border: 1px solid var(--border-color); }
        .comentario .header { display: flex; align-items: center; gap: 0.8rem; margin-bottom: 0.3rem; flex-wrap: wrap; }
        .comentario .header .avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; background: var(--bg-hover); border: 1px solid var(--border-color); }
        .comentario .header .nombre { font-weight: 600; }
        .comentario .header .fecha { font-size: 0.75rem; color: var(--text-light); }
        .comentario .header .rol-badge { font-size: 0.6rem; padding: 0.1rem 0.5rem; border-radius: 9999px; background: var(--primary-light); color: var(--primary); }
        .comentario .header .rol-badge.admin { background: #fee2e2; color: #dc2626; }
        .comentario .texto { margin: 0.3rem 0 0.5rem; word-wrap: break-word; }
        .comentario .acciones { display: flex; gap: 0.8rem; font-size: 0.8rem; }
        .comentario .acciones button { background: none; border: none; color: var(--text-secondary); cursor: pointer; padding: 0.1rem 0.3rem; transition: color 0.2s; }
        .comentario .acciones button:hover { color: var(--primary); }
        .comentario .acciones .menu-toggle { position: relative; cursor: pointer; }
        .comentario .acciones .menu-dropdown { display: none; position: absolute; background: var(--bg-card); border-radius: 8px; box-shadow: var(--shadow-lg); padding: 0.3rem 0; min-width: 120px; z-index: 10; right: 0; border: 1px solid var(--border-color); }
        .comentario .acciones .menu-dropdown.show { display: block; }
        .comentario .acciones .menu-dropdown button { display: block; width: 100%; padding: 0.4rem 1rem; text-align: left; background: none; border: none; color: var(--text-primary); cursor: pointer; }
        .comentario .acciones .menu-dropdown button:hover { background: var(--bg-hover); }
        .comentario .respuestas { margin-left: 2rem; margin-top: 0.8rem; padding-left: 1rem; border-left: 2px solid var(--border-color); }
        .comentario .respuestas .comentario { border-left-color: var(--text-light); background: var(--bg-hover); }
        .comentario .edit-form { display: none; margin-top: 0.5rem; }
        .comentario .edit-form.visible { display: block; }
        .comentario .edit-form textarea { width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 8px; min-height: 60px; background: var(--bg-input); color: var(--text-primary); font-family: inherit; }
        .comentario .edit-form .btn-sm { margin-top: 0.3rem; padding: 0.3rem 0.8rem; border: none; border-radius: 6px; cursor: pointer; }
        .comentario .edit-form .btn-sm.save { background: var(--primary); color: white; }
        .comentario .edit-form .btn-sm.cancel { background: var(--bg-hover); color: var(--text-primary); }

        .no-login-msg { background: var(--warning-bg); padding: 0.7rem 1rem; border-radius: 8px; color: #92400e; margin-bottom: 1rem; border: 1px solid var(--warning); }
        .no-login-msg a { color: #92400e; font-weight: 600; }

        .comentario-form-fijo { position: fixed; bottom: 0; left: 0; right: 0; background: var(--bg-card); padding: 0.8rem 1rem; box-shadow: 0 -4px 20px rgba(0,0,0,0.08); border-top: 1px solid var(--border-color); z-index: 100; }
        .comentario-form-fijo .form-inner { max-width: 800px; margin: 0 auto; display: flex; gap: 0.5rem; align-items: flex-end; }
        .comentario-form-fijo textarea { width: 100%; padding: 0.6rem 1rem; border: 1px solid var(--border-color); border-radius: 12px; resize: none; height: 50px; font-family: inherit; background: var(--bg-input); color: var(--text-primary); }
        .comentario-form-fijo textarea:focus { border-color: var(--primary); outline: none; }
        .comentario-form-fijo .btn-primary { padding: 0.6rem 1.5rem; background: var(--primary); color: white; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; white-space: nowrap; height: 50px; }
        .comentario-form-fijo .respondiendo-info { display: none; font-size: 0.75rem; color: var(--text-secondary); padding: 0.2rem 0.5rem; background: var(--bg-hover); border-radius: 6px; }
        .comentario-form-fijo .respondiendo-info.visible { display: block; }
        .comentario-form-fijo .respondiendo-info .cancelar { color: var(--danger); cursor: pointer; font-weight: 600; margin-left: 0.5rem; }

        .perfil-creador { background: var(--bg-card); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); }
        .perfil-creador .perfil-header { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .perfil-creador .perfil-header .avatar { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary); }
        .perfil-creador .perfil-header .info { flex: 1; }
        .perfil-creador .perfil-header .info .nombre { font-size: 1.1rem; font-weight: 700; }
        .perfil-creador .perfil-header .info .rol { font-size: 0.8rem; color: var(--text-secondary); }
        .perfil-creador .perfil-header .info .rol .admin { color: #dc2626; }
        .perfil-creador .perfil-header .info .rol .desarrollador { color: var(--primary); }
        .perfil-creador .perfil-header .likes { display: flex; align-items: center; gap: 0.5rem; background: var(--bg-hover); padding: 0.3rem 1rem; border-radius: 9999px; border: 1px solid var(--border-color); }
        .perfil-creador .perfil-header .likes .like-btn { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--text-secondary); transition: all 0.2s; }
        .perfil-creador .perfil-header .likes .like-btn:hover:not(:disabled) { transform: scale(1.2); }
        .perfil-creador .perfil-header .likes .like-btn.liked { color: #dc2626; }
        .perfil-creador .perfil-header .likes .like-btn:disabled { opacity: .4; cursor: not-allowed; }
        .perfil-creador .perfil-header .likes .like-count { font-weight: 600; font-size: 0.9rem; }
        .perfil-creador .apks-creador { margin-top: 1rem; }
        .perfil-creador .apks-creador .apks-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.8rem; margin-top: 0.5rem; }
        .perfil-creador .apks-creador .apks-grid .apk-mini { background: var(--bg-hover); border-radius: 12px; padding: 0.5rem; text-align: center; border: 1px solid var(--border-color); cursor: pointer; transition: all 0.2s; }
        .perfil-creador .apks-creador .apks-grid .apk-mini:hover { border-color: var(--primary); transform: translateY(-2px); }
        .perfil-creador .apks-creador .apks-grid .apk-mini .icon { width: 40px; height: 40px; margin: 0 auto; border-radius: 8px; overflow: hidden; background: var(--bg-card); border: 1px solid var(--border-color); display:flex;align-items:center;justify-content:center;color:var(--primary); }
        .perfil-creador .apks-creador .apks-grid .apk-mini .icon img { width: 100%; height: 100%; object-fit: cover; }
        .perfil-creador .apks-creador .apks-grid .apk-mini .nombre { font-size: 0.7rem; font-weight: 600; margin-top: 0.2rem; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; display:-webkit-box; }
        .perfil-creador .apks-creador .apks-grid .apk-mini .version { font-size: 0.6rem; color: var(--text-secondary); }

        .cargar-mas-btn { display: block; width: 100%; padding: .7rem; margin: 1rem 0; background: var(--bg-hover); border: 1px solid var(--border-color); border-radius: var(--radius-md); font-weight: 600; cursor: pointer; color: var(--text-primary); transition: var(--transition); }
        .cargar-mas-btn:hover { border-color: var(--primary); }

        .link-notification { position: fixed; top: 1.5rem; left: 50%; transform: translateX(-50%) translateY(-20px); background: var(--bg-card); color: var(--text-primary); padding: .8rem 2rem; border-radius: 999px; font-weight: 500; box-shadow: var(--shadow-lg); z-index: 9999; opacity: 0; visibility: hidden; transition: all .4s; border: 1px solid var(--border-color); }
        .link-notification.show { opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0); }

        @media (max-width: 480px) {
            .detalle-header { flex-direction: column; text-align: center; }
            .comentario .respuestas { margin-left: 0.5rem; padding-left: 0.5rem; }
            .comentario-form-fijo .form-inner { flex-direction: column; align-items: stretch; }
            .comentario-form-fijo .btn-primary { height: 44px; }
            body { padding-bottom: 180px; }
            .perfil-creador .perfil-header { flex-direction: column; text-align: center; }
            .perfil-creador .apks-creador .apks-grid { grid-template-columns: repeat(3, 1fr); }
        }
    </style>
</head>
<body>

<div class="link-notification" id="linkNotification"><i class="fas fa-check-circle"></i> <span id="linkNotificationText"></span></div>

<div class="detalle-container">
    <a href="index.php" class="btn-sm btn-primary" style="padding:.5rem 1rem;display:inline-block;text-decoration:none;border-radius:8px;background:var(--primary);color:white;margin-bottom:1rem;">
        <i class="fas fa-arrow-left"></i> Volver
    </a>

    <!-- Banner si está en desarrollo -->
    <?php if ($apk_en_dev): ?>
        <div class="dev-banner-apk">
            <i class="fas fa-tools"></i> Esta aplicación está marcada como <strong>En Desarrollo</strong>. Aún no está disponible.
        </div>
    <?php endif; ?>

    <div class="detalle-header">
        <div class="icono">
            <?php if ($apk['icono'] && file_exists($apk['icono'])): ?>
                <img src="<?= escapar($apk['icono']) ?>" alt="icono" onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\\'fas fa-cube\\'></i>'">
            <?php else: ?>
                <i class="fas fa-cube"></i>
            <?php endif; ?>
        </div>
        <div class="info" style="flex:1;">
            <h2><?= escapar($apk['nombre']) ?></h2>
            <div class="meta">
                <span>v<?= escapar($apk['version']) ?></span>
                <span>· <?= escapar(obtenerCategoriaLabel($apk['categoria'])) ?></span>
                <span>· <?= (int)$apk['descargas'] ?> descargas</span>
            </div>
            <div class="creador">
                <i class="fas fa-user"></i> Creado por:
                <a href="#perfil-creador" onclick="document.getElementById('perfil-creador').scrollIntoView({behavior:'smooth'}); return false;">
                    <?= escapar($creador['nombre'] ?? 'YS-CODE') ?>
                </a>
                <?php if ($creador && $creador['rol'] === 'admin'): ?><span style="color:#dc2626;font-size:.7rem">(Admin)</span>
                <?php elseif ($creador && $creador['rol'] === 'desarrollador'): ?><span style="color:var(--primary);font-size:.7rem">(Desarrollador)</span>
                <?php elseif (!$creador): ?><span style="color:#dc2626;font-size:.7rem">(Admin)</span><?php endif; ?>
            </div>
            <div class="detalle-actions">
                <button onclick="compartirApp()"><i class="fas fa-share-alt"></i> Compartir</button>

                <!-- ✅ BOTÓN DE DESCARGA CON SOPORTE PARA LA APP -->
                <?php if ($apk_descargable && !$apk_en_dev): ?>
                    <?php if (esAppAndroid()): ?>
                        <!-- App Inventor: botón que dispara sendBeacon y luego el downloader nativo -->
                        <button onclick="descargarDesdeApp('<?= escapar($apk_href_descarga_app) ?>', <?= $apk_id ?>)" class="btn-descarga">
                            <i class="fas fa-download"></i> Descargar
                        </button>
                    <?php elseif ($apk_es_externo): ?>
                        <!-- Navegador + externo: contamos por JS y abrimos link -->
                        <a href="<?= escapar($apk_href_descarga_web) ?>" target="_blank" rel="noopener"
                           onclick="registrarDescarga(<?= $apk_id ?>)" class="btn-descarga">
                            <i class="fas fa-download"></i> Descargar (externo)
                        </a>
                    <?php else: ?>
                        <!-- Navegador + interno: descargas.php cuenta automáticamente -->
                        <a href="<?= escapar($apk_href_descarga_web) ?>" class="btn-descarga">
                            <i class="fas fa-download"></i> Descargar
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <button class="btn-descarga disabled" disabled>
                        <i class="fas fa-ban"></i>
                        <?= $apk_en_dev ? 'En desarrollo' : 'No disponible' ?>
                    </button>
                <?php endif; ?>
<div style="text-align:center;margin-top:0.6rem;font-size:0.82rem;">
    <a href="guia-instalacion.php" style="color:var(--primary);font-weight:600;text-decoration:underline;">
        <i class="fas fa-question-circle"></i> ¿Cómo instalar esta APK?
    </a>
</div>
                <?php if (estaLogueado() && !$ya_reporto): ?>
                    <button class="reportar" onclick="abrirReporte()"><i class="fas fa-flag"></i> Reportar</button>
                <?php elseif ($ya_reporto): ?>
                    <button class="reportar" disabled style="opacity:.5;cursor:not-allowed;"><i class="fas fa-check"></i> Ya reportado</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- SECCIÓN: DESCRIPCIÓN + ENLACE EXTRA debajo -->
    <?php if (!empty($apk['descripcion']) || !empty($apk['link_url'])): ?>
        <div class="descripcion-card">
            <?php if (!empty($apk['descripcion'])): ?>
                <div class="desc-titulo"><i class="fas fa-align-left"></i> Descripción</div>
                <div class="desc-texto"><?= nl2br(escapar($apk['descripcion'])) ?></div>
            <?php endif; ?>

            <?php if (!empty($apk['link_url'])): ?>
                <div class="desc-enlace">
                    <a href="<?= escapar($apk['link_url']) ?>" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-link"></i>
                        <?= escapar(!empty($apk['link_texto']) ? $apk['link_texto'] : 'Ver más') ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Perfil creador -->
    <?php if ($creador): ?>
        <div class="perfil-creador" id="perfil-creador">
            <div class="perfil-header">
                <img src="<?= escapar($icono_creador) ?>" alt="avatar" class="avatar" onerror="this.src='assets/default-avatar.png'">
                <div class="info">
                    <div class="nombre"><?= escapar($creador['nombre']) ?></div>
                    <div class="rol">
                        <?php if ($creador['rol'] === 'admin'): ?><span class="admin">🔑 Administrador</span>
                        <?php elseif ($creador['rol'] === 'desarrollador'): ?><span class="desarrollador">💻 Desarrollador</span>
                        <?php else: ?>👤 Usuario<?php endif; ?>
                    </div>
                </div>
                <div class="likes">
                    <?php
                        $es_propio = estaLogueado() && $_SESSION['usuario_id'] == $creador['id'];
                        $disabled = $es_propio ? 'disabled' : '';
                        $clase = $yo_di_like ? 'liked' : '';
                    ?>
                    <button class="like-btn <?= $clase ?>" <?= $disabled ?>
                            onclick="toggleLike(<?= (int)$creador['id'] ?>, this)"
                            title="<?= $es_propio ? 'No puedes darte like' : ($yo_di_like ? 'Quitar like' : 'Dar like') ?>">
                        <i class="fas fa-heart"></i>
                    </button>
                    <span class="like-count" id="likeCount-<?= (int)$creador['id'] ?>"><?= $total_likes ?></span>
                </div>
            </div>

            <?php if (!empty($apks_creador)): ?>
                <div class="apks-creador">
                    <div style="font-size:.9rem;font-weight:600;color:var(--text-secondary);">
                        <i class="fas fa-cubes"></i> Otras APKs de <?= escapar($creador['nombre']) ?>
                    </div>
                    <div class="apks-grid">
                        <?php foreach ($apks_creador as $app): ?>
                            <div class="apk-mini" onclick="window.location.href='apk-detalle.php?id=<?= (int)$app['id'] ?>'">
                                <div class="icon">
                                    <?php if ($app['icono'] && file_exists($app['icono'])): ?>
                                        <img src="<?= escapar($app['icono']) ?>" alt="icono" loading="lazy" onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\\'fas fa-cube\\'></i>'">
                                    <?php else: ?>
                                        <i class="fas fa-cube"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="nombre"><?= escapar($app['nombre']) ?></div>
                                <div class="version">v<?= escapar($app['version']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="apks-creador" style="padding:.5rem 0;">
                    <div style="font-size:.8rem;color:var(--text-secondary);"><i class="fas fa-info-circle"></i> Este usuario no tiene otras APKs publicadas.</div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Valoración -->
    <div class="valoracion-section">
        <div class="promedio" id="promedioBig"><?= number_format((float)$apk['promedio_estrellas'], 1) ?></div>
        <div class="estrellas" id="estrellasBig">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <span class="<?= $i <= round($apk['promedio_estrellas']) ? '' : 'empty' ?>">★</span>
            <?php endfor; ?>
        </div>
        <div style="font-size:.85rem;color:var(--text-secondary);" id="totalValoraciones"><?= $total_valoraciones ?> valoraciones</div>

        <?php if (estaLogueado()): ?>
            <div class="calificar">
                <p style="font-size:.85rem;color:var(--text-secondary);">Tu valoración:</p>
                <div id="starRating">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button class="star-btn <?= ($mi_valoracion && $i <= $mi_valoracion) ? 'active' : '' ?>" data-stars="<?= $i ?>">★</button>
                    <?php endfor; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="no-login-msg" style="margin-top:.5rem;"><i class="fas fa-sign-in-alt"></i> Inicia sesión para valorar esta APK</div>
        <?php endif; ?>
    </div>

    <div class="comentarios-header">
        <h3><i class="fas fa-comments"></i> Comentarios</h3>
        <span class="total"><?= $total_comentarios ?> comentarios</span>
    </div>

    <div id="comentariosList">
        <?php if (empty($comentarios)): ?>
            <div style="text-align:center;padding:2rem 0;color:var(--text-secondary);">
                <i class="fas fa-comment" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                No hay comentarios aún. ¡Sé el primero en comentar!
            </div>
        <?php else: ?>
            <?php foreach ($comentarios as $c): ?>
                <div class="comentario" id="com-<?= (int)$c['id'] ?>">
                    <div class="header">
                        <img src="<?= escapar($c['foto_perfil'] ?? 'assets/default-avatar.png') ?>" alt="avatar" class="avatar" onerror="this.src='assets/default-avatar.png'">
                        <span class="nombre"><?= escapar($c['nombre']) ?></span>
                        <?php if ($c['usuario_rol'] === 'admin'): ?><span class="rol-badge admin">Admin</span>
                        <?php elseif ($c['usuario_rol'] === 'desarrollador'): ?><span class="rol-badge">Desarrollador</span><?php endif; ?>
                        <span class="fecha"><?= escapar(tiempoRelativo($c['creado_en'])) ?></span>
                        <?php if ($c['editado_en']): ?><span class="fecha" style="font-style:italic">(editado)</span><?php endif; ?>
                    </div>
                    <div class="texto" id="texto-<?= (int)$c['id'] ?>"><?= nl2br(escapar($c['comentario'])) ?></div>

                    <div class="acciones">
                        <?php if (estaLogueado()): ?>
                            <button onclick="responderComentario(<?= (int)$c['id'] ?>, <?= json_encode(mb_substr($c['comentario'], 0, 40)) ?>)"><i class="fas fa-reply"></i> Responder</button>
                        <?php endif; ?>
                        <?php if (estaLogueado() && ($_SESSION['usuario_id'] == $c['usuario_id'] || esAdmin())): ?>
                            <div class="menu-toggle" onclick="toggleMenu(this)">
                                <i class="fas fa-ellipsis-v"></i>
                                <div class="menu-dropdown">
                                    <?php if ($_SESSION['usuario_id'] == $c['usuario_id']): ?>
                                        <button onclick="editarComentario(<?= (int)$c['id'] ?>)">✏️ Editar</button>
                                    <?php endif; ?>
                                    <button onclick="eliminarComentario(<?= (int)$c['id'] ?>)">🗑️ Eliminar</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="edit-form" id="edit-<?= (int)$c['id'] ?>">
                        <textarea id="editText-<?= (int)$c['id'] ?>"><?= escapar($c['comentario']) ?></textarea>
                        <div>
                            <button class="btn-sm save" onclick="guardarEdicion(<?= (int)$c['id'] ?>)">Guardar</button>
                            <button class="btn-sm cancel" onclick="cancelarEdicion(<?= (int)$c['id'] ?>)">Cancelar</button>
                        </div>
                    </div>

                    <?php if (!empty($c['respuestas'])): ?>
                        <div class="respuestas">
                            <?php foreach ($c['respuestas'] as $r): ?>
                                <div class="comentario" id="com-<?= (int)$r['id'] ?>">
                                    <div class="header">
                                        <img src="<?= escapar($r['foto_perfil'] ?? 'assets/default-avatar.png') ?>" alt="avatar" class="avatar" onerror="this.src='assets/default-avatar.png'">
                                        <span class="nombre"><?= escapar($r['nombre']) ?></span>
                                        <?php if ($r['usuario_rol'] === 'admin'): ?><span class="rol-badge admin">Admin</span>
                                        <?php elseif ($r['usuario_rol'] === 'desarrollador'): ?><span class="rol-badge">Desarrollador</span><?php endif; ?>
                                        <span class="fecha"><?= escapar(tiempoRelativo($r['creado_en'])) ?></span>
                                        <?php if ($r['editado_en']): ?><span class="fecha" style="font-style:italic">(editado)</span><?php endif; ?>
                                    </div>
                                    <div class="texto" id="texto-<?= (int)$r['id'] ?>"><?= nl2br(escapar($r['comentario'])) ?></div>
                                    <div class="acciones">
                                        <?php if (estaLogueado()): ?>
                                            <button onclick="responderComentario(<?= (int)$r['id'] ?>, <?= json_encode(mb_substr($r['comentario'], 0, 40)) ?>)"><i class="fas fa-reply"></i> Responder</button>
                                        <?php endif; ?>
                                        <?php if (estaLogueado() && ($_SESSION['usuario_id'] == $r['usuario_id'] || esAdmin())): ?>
                                            <div class="menu-toggle" onclick="toggleMenu(this)">
                                                <i class="fas fa-ellipsis-v"></i>
                                                <div class="menu-dropdown">
                                                    <?php if ($_SESSION['usuario_id'] == $r['usuario_id']): ?>
                                                        <button onclick="editarComentario(<?= (int)$r['id'] ?>)">✏️ Editar</button>
                                                    <?php endif; ?>
                                                    <button onclick="eliminarComentario(<?= (int)$r['id'] ?>)">🗑️ Eliminar</button>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="edit-form" id="edit-<?= (int)$r['id'] ?>">
                                        <textarea id="editText-<?= (int)$r['id'] ?>"><?= escapar($r['comentario']) ?></textarea>
                                        <div>
                                            <button class="btn-sm save" onclick="guardarEdicion(<?= (int)$r['id'] ?>)">Guardar</button>
                                            <button class="btn-sm cancel" onclick="cancelarEdicion(<?= (int)$r['id'] ?>)">Cancelar</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if ($pag_com['hay_siguiente']): ?>
                <button class="cargar-mas-btn" id="cargarMasBtn" onclick="cargarMasComentarios()">
                    <i class="fas fa-chevron-down"></i> Cargar más comentarios
                </button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="comentario-form-fijo">
    <div class="form-inner">
        <div style="flex:1;display:flex;flex-direction:column;gap:.3rem;">
            <div class="respondiendo-info" id="respondiendoInfo">
                <i class="fas fa-reply"></i> Respondiendo a: <span id="respondiendoTexto"></span>
                <span class="cancelar" onclick="cancelarRespuestaComentario()">Cancelar</span>
            </div>
            <?php if (estaLogueado()): ?>
                <form id="comentarioForm" style="display:flex;gap:.5rem;width:100%;">
                    <input type="hidden" name="padre_id" id="padreIdInput" value="">
                    <textarea id="comentarioTextarea" placeholder="Escribe tu comentario..." required maxlength="2000"></textarea>
                    <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Publicar</button>
                </form>
            <?php else: ?>
                <div class="no-login-msg" style="margin:0;"><i class="fas fa-sign-in-alt"></i> <a href="login.php">Inicia sesión</a> para comentar</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Reporte -->
<div class="modal-overlay" id="modalReporte" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);backdrop-filter:blur(8px);z-index:999;display:flex;align-items:center;justify-content:center;padding:1rem;opacity:0;visibility:hidden;transition:all .3s;">
    <div style="background:var(--bg-card);width:100%;max-width:480px;border-radius:24px;padding:1.5rem;border:1px solid var(--border-color);position:relative;">
        <button onclick="cerrarReporte()" style="position:absolute;top:.8rem;right:.8rem;background:var(--bg-hover);border:none;width:36px;height:36px;border-radius:50%;cursor:pointer;color:var(--text-primary);"><i class="fas fa-times"></i></button>
        <h2 style="margin-bottom:.5rem;"><i class="fas fa-flag" style="color:var(--danger)"></i> Reportar APK</h2>
        <p style="color:var(--text-secondary);font-size:.9rem;margin-bottom:1rem;">Ayúdanos a mantener la plataforma segura.</p>
        <div class="form-group" style="margin-bottom:.8rem;">
            <label style="display:block;font-weight:500;margin-bottom:.3rem;">Motivo *</label>
            <select id="reportMotivo" style="width:100%;padding:.7rem 1rem;border:1px solid var(--border-color);border-radius:12px;background:var(--bg-input);color:var(--text-primary);">
                <option value="contenido_inapropiado">Contenido inapropiado</option>
                <option value="malware">Malware / virus</option>
                <option value="spam">Spam</option>
                <option value="copyright">Violación de copyright</option>
                <option value="enlace_roto">Enlace roto</option>
                <option value="otro">Otro</option>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:.8rem;">
            <label style="display:block;font-weight:500;margin-bottom:.3rem;">Descripción (opcional)</label>
            <textarea id="reportDescripcion" rows="3" style="width:100%;padding:.7rem 1rem;border:1px solid var(--border-color);border-radius:12px;background:var(--bg-input);color:var(--text-primary);font-family:inherit;"></textarea>
        </div>
        <button class="btn-primary" style="width:100%;" onclick="enviarReporte()"><i class="fas fa-paper-plane"></i> Enviar Reporte</button>
    </div>
</div>

<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
const USUARIO_LOGGED = <?= json_encode(estaLogueado()) ?>;
const USUARIO_ID = <?= json_encode($_SESSION['usuario_id'] ?? null) ?>;
const ES_ADMIN = <?= json_encode(esAdmin()) ?>;
const APK_ID = <?= (int)$apk_id ?>;
const APK_NOMBRE = <?= json_encode($apk['nombre']) ?>;
const APK_VERSION = <?= json_encode($apk['version']) ?>;
const CREADOR_ID = <?= json_encode($creador['id'] ?? null) ?>;

let comentariosPagina = <?= (int)$pag_com['pagina_actual'] ?>;
const TOTAL_PAGINAS_COM = <?= (int)$pag_com['total_paginas'] ?>;

function apiCall(action, data = {}) {
    const body = new URLSearchParams({ action, csrf_token: CSRF_TOKEN, ...data });
    return fetch('api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body
    }).then(r => r.json()).catch(err => ({success:false, message:'Error de red'}));
}

function escHtml(s) { return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

// ============================================
// CONTADOR DE DESCARGAS — sendBeacon
// (llega aunque la pestaña navegue/cierre)
// ============================================
function registrarDescarga(apk_id) {
    if (!apk_id) return;
    const body = new URLSearchParams({ action: 'registrar_descarga', apk_id: apk_id });
    try {
        if (navigator.sendBeacon) {
            navigator.sendBeacon('api.php', body);
        } else {
            fetch('api.php', { method: 'POST', body, keepalive: true }).catch(() => {});
        }
    } catch (e) {}
}

// ============ PUENTE DE DESCARGA PARA APP INVENTOR ============
function descargarDesdeApp(url, apk_id) {
    // 1. Registrar contador ANTES de navegar
    registrarDescarga(apk_id);

    // 2. Asegurar URL absoluta
    if (!url.startsWith('http')) {
        url = window.location.origin + '/' + url.replace(/^\/+/, '');
    }

    // 3. Entregar a App Inventor o fallback
    if (typeof window.AppInventor !== 'undefined') {
        window.AppInventor.setWebViewString("DOWNLOAD:" + url);
    } else {
        window.location.href = url;
    }
}

function notificar(msg) {
    document.getElementById('linkNotificationText').textContent = msg;
    document.getElementById('linkNotification').classList.add('show');
    clearTimeout(window._notifT);
    window._notifT = setTimeout(() => document.getElementById('linkNotification').classList.remove('show'), 3000);
}

// ============ LIKE ============
async function toggleLike(usuarioId, btn) {
    if (!USUARIO_LOGGED) { notificar('❌ Inicia sesión para dar like'); return; }
    if (!usuarioId || usuarioId == USUARIO_ID) return;
    if (btn.disabled) return;

    btn.disabled = true;
    const isLiked = btn.classList.contains('liked');
    const action = isLiked ? 'quitar_like' : 'dar_like';

    const data = await apiCall(action, { usuario_id: usuarioId });
    btn.disabled = false;

    if (data.success) {
        btn.classList.toggle('liked', !isLiked);
        document.getElementById('likeCount-' + usuarioId).textContent = data.total_likes;
        notificar(isLiked ? '💔 Like quitado' : '❤️ Like agregado');
    } else {
        notificar('❌ ' + data.message);
    }
}

// ============ COMPARTIR ============
async function compartirApp() {
    const url = window.location.href;
    const shareData = {
        title: APK_NOMBRE + ' - YSWEBAPK',
        text: 'Descarga ' + APK_NOMBRE + ' v' + APK_VERSION + ' en YSWEBAPK',
        url: url
    };
    if (navigator.share) {
        try { await navigator.share(shareData); return; } catch(e) {}
    }
    try {
        await navigator.clipboard.writeText(url);
        notificar('🔗 Enlace copiado');
    } catch(e) {
        prompt('Copia este enlace:', url);
    }
}

// ============ VALORAR ============
document.querySelectorAll('#starRating .star-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!USUARIO_LOGGED) return;
        const estrellas = parseInt(this.dataset.stars);
        document.querySelectorAll('#starRating .star-btn').forEach(b => {
            b.classList.toggle('active', parseInt(b.dataset.stars) <= estrellas);
        });
        const data = await apiCall('valorar', { apk_id: APK_ID, estrellas });
        if (data.success) {
            document.getElementById('promedioBig').textContent = data.promedio.toFixed(1);
            const cont = document.getElementById('estrellasBig');
            cont.innerHTML = [1,2,3,4,5].map(i => `<span class="${i <= Math.round(data.promedio) ? '' : 'empty'}">★</span>`).join('');
            notificar('⭐ Valoración guardada');
        } else {
            notificar('❌ ' + data.message);
        }
    });
});

// ============ COMENTAR ============
const comentarioForm = document.getElementById('comentarioForm');
if (comentarioForm) {
    comentarioForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const textarea = document.getElementById('comentarioTextarea');
        const texto = textarea.value.trim();
        if (!texto) { notificar('❌ Escribe un comentario'); return; }
        const padre = document.getElementById('padreIdInput').value || '';

        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i>';

        const data = await apiCall('comentar', {
            apk_id: APK_ID,
            comentario: texto,
            padre_id: padre
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Publicar';

        if (data.success) {
            textarea.value = '';
            cancelarRespuestaComentario();
            notificar('✅ Comentario publicado');
            setTimeout(() => location.reload(), 500);
        } else {
            notificar('❌ ' + data.message);
        }
    });
}

function responderComentario(id, texto) {
    document.getElementById('padreIdInput').value = id;
    document.getElementById('respondiendoTexto').textContent = texto + '...';
    document.getElementById('respondiendoInfo').classList.add('visible');
    document.getElementById('comentarioTextarea').focus();
    document.getElementById('comentarioTextarea').scrollIntoView({behavior: 'smooth', block: 'center'});
}
function cancelarRespuestaComentario() {
    document.getElementById('padreIdInput').value = '';
    document.getElementById('respondiendoInfo').classList.remove('visible');
}

// ============ MENÚ COMENTARIO ============
function toggleMenu(el) {
    const dd = el.querySelector('.menu-dropdown');
    dd.classList.toggle('show');
    document.querySelectorAll('.menu-dropdown.show').forEach(d => { if (d !== dd) d.classList.remove('show'); });
}
document.addEventListener('click', e => {
    if (!e.target.closest('.menu-toggle')) {
        document.querySelectorAll('.menu-dropdown.show').forEach(d => d.classList.remove('show'));
    }
});

function editarComentario(id) {
    const ef = document.getElementById('edit-' + id);
    const tx = document.getElementById('texto-' + id);
    ef.classList.toggle('visible');
    tx.style.display = ef.classList.contains('visible') ? 'none' : 'block';
}
function cancelarEdicion(id) {
    document.getElementById('edit-' + id).classList.remove('visible');
    document.getElementById('texto-' + id).style.display = 'block';
}
async function guardarEdicion(id) {
    const texto = document.getElementById('editText-' + id).value.trim();
    if (!texto) { notificar('❌ El comentario no puede estar vacío'); return; }
    const data = await apiCall('editar_comentario', { comentario_id: id, comentario: texto });
    if (data.success) {
        document.getElementById('texto-' + id).innerHTML = escHtml(texto).replace(/\n/g, '<br>');
        cancelarEdicion(id);
        notificar('✅ Comentario actualizado');
    } else {
        notificar('❌ ' + data.message);
    }
}
async function eliminarComentario(id) {
    if (!confirm('¿Eliminar este comentario?')) return;
    const data = await apiCall('eliminar_comentario', { comentario_id: id });
    if (data.success) {
        const el = document.getElementById('com-' + id);
        if (el) el.remove();
        notificar('🗑️ Comentario eliminado');
    } else {
        notificar('❌ ' + data.message);
    }
}

// ============ CARGAR MÁS COMENTARIOS ============
async function cargarMasComentarios() {
    const btn = document.getElementById('cargarMasBtn');
    if (!btn) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Cargando...';

    const nextPage = comentariosPagina + 1;
    const data = await apiCall('cargar_comentarios', { apk_id: APK_ID, pagina: nextPage });

    if (!data.success) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-chevron-down"></i> Cargar más comentarios';
        notificar('❌ ' + data.message);
        return;
    }

    const lista = document.getElementById('comentariosList');

    data.comentarios.forEach(c => {
        let html = `<div class="comentario" id="com-${c.id}">
            <div class="header">
                <img src="${escHtml(c.foto_perfil || 'assets/default-avatar.png')}" class="avatar" onerror="this.src='assets/default-avatar.png'">
                <span class="nombre">${escHtml(c.nombre)}</span>
                ${c.usuario_rol==='admin'?'<span class="rol-badge admin">Admin</span>':(c.usuario_rol==='desarrollador'?'<span class="rol-badge">Desarrollador</span>':'')}
                <span class="fecha">${escHtml(c.creado_en)}</span>
                ${c.editado_en?'<span class="fecha" style="font-style:italic">(editado)</span>':''}
            </div>
            <div class="texto" id="texto-${c.id}">${escHtml(c.comentario).replace(/\n/g,'<br>')}</div>
            <div class="acciones">`;

        if (USUARIO_LOGGED) {
            html += `<button onclick="responderComentario(${c.id}, '${escHtml(c.comentario.substring(0,40))}')"><i class="fas fa-reply"></i> Responder</button>`;
        }
        if (USUARIO_LOGGED && (USUARIO_ID == c.usuario_id || ES_ADMIN)) {
            html += `<div class="menu-toggle" onclick="toggleMenu(this)"><i class="fas fa-ellipsis-v"></i>
                <div class="menu-dropdown">
                    ${USUARIO_ID == c.usuario_id ? `<button onclick="editarComentario(${c.id})">✏️ Editar</button>` : ''}
                    <button onclick="eliminarComentario(${c.id})">🗑️ Eliminar</button>
                </div></div>`;
        }
        html += `</div><div class="edit-form" id="edit-${c.id}">
            <textarea id="editText-${c.id}">${escHtml(c.comentario)}</textarea>
            <div><button class="btn-sm save" onclick="guardarEdicion(${c.id})">Guardar</button>
            <button class="btn-sm cancel" onclick="cancelarEdicion(${c.id})">Cancelar</button></div>
        </div>`;

        if (c.respuestas && c.respuestas.length) {
            html += '<div class="respuestas">';
            c.respuestas.forEach(r => {
                html += `<div class="comentario" id="com-${r.id}">
                    <div class="header">
                        <img src="${escHtml(r.foto_perfil || 'assets/default-avatar.png')}" class="avatar" onerror="this.src='assets/default-avatar.png'">
                        <span class="nombre">${escHtml(r.nombre)}</span>
                        ${r.usuario_rol==='admin'?'<span class="rol-badge admin">Admin</span>':(r.usuario_rol==='desarrollador'?'<span class="rol-badge">Desarrollador</span>':'')}
                        <span class="fecha">${escHtml(r.creado_en)}</span>
                    </div>
                    <div class="texto" id="texto-${r.id}">${escHtml(r.comentario).replace(/\n/g,'<br>')}</div>
                </div>`;
            });
            html += '</div>';
        }
        html += '</div>';
        lista.insertBefore(document.createRange().createContextualFragment(html), btn);
    });

    comentariosPagina = nextPage;

    if (!data.paginacion.hay_siguiente) {
        btn.remove();
    } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-chevron-down"></i> Cargar más comentarios';
    }
}

// ============ REPORTAR ============
function abrirReporte() {
    if (!USUARIO_LOGGED) { notificar('❌ Inicia sesión'); return; }
    document.getElementById('modalReporte').style.opacity = '1';
    document.getElementById('modalReporte').style.visibility = 'visible';
}
function cerrarReporte() {
    document.getElementById('modalReporte').style.opacity = '0';
    document.getElementById('modalReporte').style.visibility = 'hidden';
}
async function enviarReporte() {
    const motivo = document.getElementById('reportMotivo').value;
    const descripcion = document.getElementById('reportDescripcion').value;
    const data = await apiCall('reportar_apk', { apk_id: APK_ID, motivo, descripcion });
    if (data.success) {
        notificar('✅ ' + data.message);
        cerrarReporte();
        setTimeout(() => location.reload(), 800);
    } else {
        notificar('❌ ' + data.message);
    }
}
</script>

</body>
</html>