<?php
require_once 'config/database.php';
require_once 'config/funciones.php';

// ===== FILTROS / PAGINACIÓN INICIAL =====
$por_pagina = 12;
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$busqueda = limpiarEntrada($_GET['q'] ?? '');
$categoria = limpiarEntrada($_GET['categoria'] ?? '');
$orden = limpiarEntrada($_GET['orden'] ?? 'id');

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

$ordenes_validos = [
    'id'         => 'a.id DESC',
    'nombre'     => 'a.nombre ASC',
    'fecha'      => 'a.fecha DESC',
    'tamano'     => 'a.tamano ASC',
    'categoria'  => 'a.categoria ASC',
    'descargas'  => 'a.descargas DESC',
    'valoracion' => 'a.promedio_estrellas DESC',
];
$order_sql = $ordenes_validos[$orden] ?? 'a.id DESC';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM apks a WHERE $where_sql");
$stmt->execute($params);
$total_apks = (int)$stmt->fetchColumn();
$paginacion = calcularPaginacion($total_apks, $por_pagina, $pagina);

$stmt = $pdo->prepare("SELECT a.*, u.nombre AS creador_nombre, u.rol AS creador_rol,
    (SELECT COUNT(*) FROM comentarios WHERE apk_id = a.id) AS comentarios_count
    FROM apks a
    LEFT JOIN usuarios u ON a.creador_id = u.id
    WHERE $where_sql
    ORDER BY $order_sql
    LIMIT {$paginacion['por_pagina']} OFFSET {$paginacion['offset']}");
$stmt->execute($params);
$apks = $stmt->fetchAll();

$apks_destacadas = [];
if ($busqueda === '' && ($categoria === '' || $categoria === 'all')) {
    try {
        $stmt = $pdo->query("SELECT a.*, u.nombre AS creador_nombre, u.rol AS creador_rol
            FROM apks a LEFT JOIN usuarios u ON a.creador_id = u.id
            WHERE a.destacado = TRUE
              AND (a.en_desarrollo = FALSE OR a.en_desarrollo IS NULL)
            ORDER BY a.id DESC LIMIT 6");
        $apks_destacadas = $stmt->fetchAll();
    } catch (Exception $e) {}
}

$notif_count = estaLogueado() ? contarNotificacionesNoLeidas($_SESSION['usuario_id']) : 0;
$pendientes_admin = 0;
if (esAdmin()) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM solicitudes_apk WHERE estado = 'pendiente'");
    $pendientes_admin = (int)$stmt->fetchColumn();
}

$categorias = obtenerCategoriasDisponibles();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<script async src="https://ds.mrmnd.com/d548a478-572f-4a32-b4c7-ef4789a0e637.js"></script>
<script async src="https://ss.mrmnd.com/interstitial.js" data-mndintid="f4eb4c33-1159-4d87-b0f2-f59e685ebd03"></script>
<script async src="https://ss.mrmnd.com/static/b9ff20dc-855f-4964-8950-37db9c8043cf.js"></script>
<script async src="https://ss.mrmnd.com/banner.js"></script>
<meta name="mnd-ver" content="2vg9iiwamwr3eqcplt2rag" />
<meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= generarCSRF() ?>">
    <?php renderMetaSEO([
    'title'       => 'YSWEBAPK - Descarga de APKs gratuitas y seguras para Android',
    'description' => 'Descarga las mejores APKs de Android gratis y verificadas. Juegos, utilidades, apps de educación y entretenimiento. 100% seguro y libre de virus.',
    'keywords'    => 'APK, descargar APK, Android, juegos APK, apps gratis, YSWEBAPK',
]); ?>
    <link rel="icon" href="IcoYSWEBAPK.jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* ===== Rating clickeable DENTRO DEL MODAL (ir a comentarios) ===== */
        .modal-rating-clickable {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            cursor: pointer;
            padding: 5px 14px;
            border-radius: 10px;
            border: 1px solid transparent;
            transition: background 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
            user-select: none;
        }
        .modal-rating-clickable:hover {
            background: var(--bg-hover);
            border-color: var(--primary);
            transform: scale(1.04);
        }
        .modal-rating-clickable:active {
            transform: scale(0.97);
        }
        .modal-rating-clickable .modal-rating-hint {
            font-size: 0.7rem;
            color: var(--primary);
            font-weight: 600;
            margin-left: 0.4rem;
            white-space: nowrap;
        }
        @media (max-width: 480px) {
            .modal-rating-clickable { padding: 4px 10px; gap: 0.2rem; }
            .modal-rating-clickable .modal-rating-hint { display: none; }
        }

        /* ===== Modal INFO ampliado ===== */
        .info-modal-content {
            max-width: 640px;
            text-align: left;
        }
        .info-header {
            text-align: center;
            padding-bottom: 1.1rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1.2rem;
        }
        .info-header .info-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 0.4rem;
        }
        .info-header h2 {
            font-size: 1.7rem;
            margin-bottom: 0.2rem;
            background: linear-gradient(145deg, #0f2b4b, #1a4b7a);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        [data-theme="dark"] .info-header h2 {
            background: linear-gradient(145deg, #60a5fa, #3b82f6);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .info-header .info-sub {
            color: var(--text-secondary);
            margin-bottom: 0.8rem;
            font-size: 0.9rem;
        }
        .info-badges {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .info-badge {
            background: var(--bg-hover);
            border: 1px solid var(--border-color);
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-primary);
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .info-badge i { color: var(--primary); }

        .info-section {
            margin-bottom: 1.25rem;
        }
        .info-section:last-of-type { margin-bottom: 0.5rem; }
        .info-section h3 {
            font-size: 1rem;
            margin-bottom: 0.55rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.45rem;
            font-weight: 700;
        }
        .info-section h3 i {
            color: var(--primary);
            font-size: 1.05rem;
        }
        .info-section p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 0.4rem;
        }
        .info-section .info-list {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }
        .info-section .info-list li {
            font-size: 0.88rem;
            color: var(--text-secondary);
            padding: 0.3rem 0 0.3rem 1.5rem;
            position: relative;
            line-height: 1.5;
        }
        .info-section .info-list li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--success);
            font-weight: 800;
            font-size: 0.9rem;
        }
        .info-section .info-list li i {
            width: 18px;
            color: var(--primary);
            margin-right: 0.35rem;
            text-align: center;
        }
        .info-section .info-list li strong {
            color: var(--text-primary);
        }
        .info-section .info-link {
            color: var(--primary);
            font-weight: 600;
            text-decoration: underline;
            text-decoration-color: rgba(26, 75, 122, 0.4);
            text-underline-offset: 2px;
        }
        .info-section .info-link:hover {
            color: var(--primary-hover);
            text-decoration-color: var(--primary-hover);
        }

        .info-footer {
            text-align: center;
            padding-top: 1.1rem;
            border-top: 1px solid var(--border-color);
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.7rem;
        }
        .info-footer p + p { margin-top: 0.35rem; }
        .info-footer .info-copy {
            font-size: 0.75rem;
            color: var(--text-light);
            font-style: italic;
        }

        @media (max-width: 480px) {
            .info-modal-content { padding: 1.2rem 1rem; }
            .info-header h2 { font-size: 1.4rem; }
            .info-header .info-icon { font-size: 2.4rem; }
            .info-section h3 { font-size: 0.95rem; }
            .info-section p,
            .info-section .info-list li { font-size: 0.85rem; }
        }
    </style>
</head>
<body>

<!-- OVERLAY MENÚ -->
<div class="menu-overlay" id="menuOverlay" onclick="cerrarMenu()"></div>

<!-- MENÚ LATERAL -->
<div class="menu-lateral" id="menuLateral">
    <div class="menu-header">
        <h3><i class="fas fa-bars"></i> Menú</h3>
        <button class="close-menu" onclick="cerrarMenu()"><i class="fas fa-times"></i></button>
    </div>

    <?php if (estaLogueado()): ?>
        <div class="user-info">
            <img src="<?= escapar($_SESSION['foto_perfil'] ?? 'assets/default-avatar.png') ?>" alt="avatar" class="avatar" onerror="this.src='assets/default-avatar.png'">
            <div>
                <div class="name"><?= escapar($_SESSION['nombre']) ?></div>
                <div class="rol">
                    <?php if (esAdmin()): ?><span style="color:#dc2626;">🔑 Administrador</span>
                    <?php elseif (esDesarrollador()): ?><span style="color:var(--primary);">💻 Desarrollador</span>
                    <?php else: ?>👤 Usuario<?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <a href="index.php" class="menu-item"><i class="fas fa-home"></i> Inicio</a>

    <?php if (!esAppAndroid()): ?>
        <a href="YSWEBAPK.apk" class="menu-item btn-app-download" style="margin-top: 0.5rem; margin-bottom: 0.5rem; display:none;">
            <i class="fab fa-android"></i> Descargar YSWEBAPK.apk
        </a>
    <?php endif; ?>

    <?php if (estaLogueado()): ?>
        <a href="perfil.php" class="menu-item"><i class="fas fa-user"></i> Mi Perfil</a>
        <a href="notificaciones.php" class="menu-item">
            <i class="fas fa-bell"></i> Notificaciones
            <?php if ($notif_count > 0): ?><span class="notif-badge"><?= $notif_count ?></span><?php endif; ?>
        </a>
        <?php if (esDesarrollador()): ?>
            <a href="mi-desarrollo.php" class="menu-item"><i class="fas fa-code"></i> Mi Desarrollo</a>
        <?php endif; ?>
        <?php if (esAdmin()): ?>
            <a href="admin.php" class="menu-item">
                <i class="fas fa-shield-alt"></i> Administración
                <?php if ($pendientes_admin > 0): ?><span class="notif-badge"><?= $pendientes_admin ?></span><?php endif; ?>
            </a>
        <?php endif; ?>
        <div class="menu-divider"></div>
        <a href="logout.php" class="menu-item" style="color:var(--danger);"><i class="fas fa-sign-out-alt" style="color:var(--danger);"></i> Cerrar Sesión</a>
    <?php else: ?>
        <a href="login.php" class="menu-item"><i class="fas fa-sign-in-alt"></i> Iniciar Sesión</a>
        <a href="login.php#registro" class="menu-item"><i class="fas fa-user-plus"></i> Registrarse</a>
    <?php endif; ?>

    <div class="menu-divider"></div>
    <a href="soporte.php" class="menu-item"><i class="fas fa-headset"></i> Soporte Técnico</a>
        <a href="guia-instalacion.php" class="menu-item"><i class="fas fa-book-open"></i> Guía de instalación</a>
    <a href="privacidad.php" class="menu-item"><i class="fas fa-user-shield"></i> Privacidad</a>
    <a href="terminos.php" class="menu-item"><i class="fas fa-file-contract"></i> Términos y Condiciones</a>

    <button class="menu-item" onclick="abrirInfo()" style="width:100%;text-align:left;background:none;border:none;cursor:pointer;font-family:inherit;">
        <i class="fas fa-info-circle"></i> Información
    </button>
    <button class="menu-item" onclick="abrirConfig()" style="width:100%;text-align:left;background:none;border:none;cursor:pointer;font-family:inherit;">
        <i class="fas fa-cog"></i> Configuración
    </button>
            <div class="menu-divider"></div>
    <a href="donar.php" class="menu-item" style="background:linear-gradient(135deg,#fef2f2,#fee2e2);color:#dc2626;font-weight:700;border:1px solid #fecaca;margin-top:0.4rem;">
        <i class="fas fa-heart" style="color:#dc2626;"></i> Apoyar el proyecto ❤️
    </a>
</div>

<!-- HEADER -->
<div class="sticky-header" id="stickyHeader">
    <header class="header">
        <div class="logo-area">
            <i class="fas fa-cubes"></i>
            <h1>YSWEBAPK</h1>
        </div>
        <div class="header-buttons">
            <?php if (estaLogueado()): ?>
                <span class="user-greeting">
                    <img src="<?= escapar($_SESSION['foto_perfil'] ?? 'assets/default-avatar.png') ?>" alt="avatar" class="avatar-mini" onerror="this.src='assets/default-avatar.png'">
                    <?= escapar($_SESSION['nombre']) ?>
                </span>
            <?php endif; ?>
            <button class="menu-hamburguesa" onclick="abrirMenu()">
                <i class="fas fa-bars"></i> Menú
                <?php if ($notif_count > 0): ?><span class="notif-badge"><?= $notif_count ?></span><?php endif; ?>
            </button>
            <button class="header-btn" id="toggleSearchBtn"><i class="fas fa-search"></i></button>
        </div>
    </header>

    <div class="search-container" id="searchContainer">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Buscar por nombre, categoría o descripción..." autocomplete="off" value="<?= escapar($busqueda) ?>">
            <button class="search-clear" id="searchClear"><i class="fas fa-times-circle"></i></button>
        </div>
    </div>

    <div class="menu-scroll-wrapper">
        <div class="menu-scroll" id="quickMenu">
            <button data-filter="all" class="<?= ($categoria === '' || $categoria === 'all') ? 'active' : '' ?>"><i class="fas fa-th"></i> Todo</button>
            <?php foreach ($categorias as $slug => $label): ?>
                <button data-filter="<?= escapar($slug) ?>" class="<?= $categoria === $slug ? 'active' : '' ?>"><?= escapar($label) ?></button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="toolbar">
        <div class="filter-badge">
            <i class="fas fa-filter"></i>
            <span id="filterLabel"><?= $busqueda !== '' ? 'Búsqueda' : ($categoria ?: 'Todos') ?></span>
            <span class="badge-count" id="resultCount"><?= $total_apks ?></span>
        </div>
        <select class="sort-selector" id="sortSelect">
            <option value="id" <?= $orden==='id'?'selected':'' ?>>ID (más reciente)</option>
            <option value="nombre" <?= $orden==='nombre'?'selected':'' ?>>Nombre</option>
            <option value="fecha" <?= $orden==='fecha'?'selected':'' ?>>Fecha</option>
            <option value="tamano" <?= $orden==='tamano'?'selected':'' ?>>Tamaño</option>
            <option value="categoria" <?= $orden==='categoria'?'selected':'' ?>>Categoría</option>
            <option value="descargas" <?= $orden==='descargas'?'selected':'' ?>>Más descargadas</option>
            <option value="valoracion" <?= $orden==='valoracion'?'selected':'' ?>>Mejor valoradas</option>
        </select>
    </div>
</div>

<center><div data-mndbanid="2389be5c-7c13-47d0-abb2-9f0c8d5d5158"></div></center>

<!-- NOTIFICACIÓN -->
<div class="link-notification" id="linkNotification">
    <i class="fas fa-check-circle"></i>
    <span id="linkNotificationText"></span>
</div>

<!-- DESTACADAS -->
<?php if (!empty($apks_destacadas)): ?>
    <div class="destacadas-section">
        <h3><i class="fas fa-star"></i> Destacadas</h3>
        <div class="destacadas-scroll">
            <?php foreach ($apks_destacadas as $d): ?>
                <div class="destacada-card" onclick="abrirModal(<?= (int)$d['id'] ?>)">
                    <div class="app-icon">
                        <?php if ($d['icono']): ?>
                            <img src="<?= escapar($d['icono']) ?>" alt="icono" loading="lazy" onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\\'fas fa-cube\\'></i>'">
                        <?php else: ?>
                            <i class="fas fa-cube"></i>
                        <?php endif; ?>
                    </div>
                    <div class="nombre"><?= escapar($d['nombre']) ?></div>
                    <div class="version">v<?= escapar($d['version']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- GRID -->
<div class="app-grid" id="appGrid">
    <?php if (empty($apks)): ?>
        <div class="sin-resultados">
            <i class="fas fa-search"></i>
            No se encontraron APKs
        </div>
    <?php else: ?>
        <?php foreach ($apks as $app): ?>
            <?php
                $dev = !empty($app['en_desarrollo']);
                $promedio = (float)($app['promedio_estrellas'] ?? 0);
                $comentarios = (int)($app['comentarios_count'] ?? 0);
                $llenas = (int)round($promedio);
            ?>
            <div class="app-card <?= $dev ? 'dev-mode' : '' ?>" onclick="abrirModal(<?= (int)$app['id'] ?>)">
                <?php if ($dev): ?><span class="dev-badge">🚧 Desarrollo</span><?php endif; ?>
                <div class="app-icon">
                    <?php if ($app['icono']): ?>
                        <img src="<?= escapar($app['icono']) ?>" alt="icono" loading="lazy" onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\\'fas fa-cube\\'></i>'">
                    <?php else: ?>
                        <i class="fas fa-cube"></i>
                    <?php endif; ?>
                </div>
                <div class="app-name"><?= escapar($app['nombre']) ?></div>
                <div class="app-version">v<?= escapar($app['version']) ?></div>
                <div class="app-category"><?= escapar(obtenerCategoriaLabel($app['categoria'])) ?></div>
                <div class="rating">
                    <span class="stars"><?php for ($i=1;$i<=5;$i++) echo $i<=$llenas?'★':'☆'; ?></span>
                    <span>(<?= number_format($promedio,1) ?>)</span>
                    <span>·</span>
                    <span><i class="fas fa-comment"></i> <?= $comentarios ?></span>
                </div>
                <div class="descargas"><i class="fas fa-download"></i> <?= (int)$app['descargas'] ?> descargas</div>
                <?php if ($dev): ?><div class="app-dev-text">En desarrollo</div><?php endif; ?>
                <?php if ($app['creador_id']): ?>
                    <div class="desarrollador-info">
                        <i class="fas fa-user"></i> <?= escapar($app['creador_nombre'] ?? 'Desarrollador') ?>
                        <?php if (($app['creador_rol'] ?? '') === 'admin'): ?><span style="color:#dc2626;">👑</span><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- PAGINACIÓN -->
<div class="pagination" id="pagination">
    <?php if ($paginacion['total_paginas'] > 1): ?>
        <button onclick="irAPagina(<?= $paginacion['pagina_actual'] - 1 ?>)" <?= !$paginacion['hay_anterior'] ? 'disabled' : '' ?>>
            <i class="fas fa-chevron-left"></i>
        </button>
        <?php
            $inicio = max(1, $paginacion['pagina_actual'] - 2);
            $fin = min($paginacion['total_paginas'], $paginacion['pagina_actual'] + 2);
            if ($inicio > 1) {
                echo '<button onclick="irAPagina(1)">1</button>';
                if ($inicio > 2) echo '<span style="color:var(--text-secondary)">…</span>';
            }
            for ($i = $inicio; $i <= $fin; $i++) {
                echo '<button onclick="irAPagina('.$i.')" class="'.($i==$paginacion['pagina_actual']?'active':'').'">'.$i.'</button>';
            }
            if ($fin < $paginacion['total_paginas']) {
                if ($fin < $paginacion['total_paginas']-1) echo '<span style="color:var(--text-secondary)">…</span>';
                echo '<button onclick="irAPagina('.$paginacion['total_paginas'].')">'.$paginacion['total_paginas'].'</button>';
            }
        ?>
        <button onclick="irAPagina(<?= $paginacion['pagina_actual'] + 1 ?>)" <?= !$paginacion['hay_siguiente'] ? 'disabled' : '' ?>>
            <i class="fas fa-chevron-right"></i>
        </button>
    <?php endif; ?>
</div>

<!-- MODAL APK -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal-content" id="modalContent">
        <button class="modal-close" id="modalClose"><i class="fas fa-times"></i></button>
        <div id="modalBody"><div class="loading-overlay"><i class="fas fa-spinner fa-pulse"></i> Cargando...</div></div>
        <button class="btn-comentarios" id="btnComentarios" style="display:none;"><i class="fas fa-comments"></i> Ver Comentarios</button>
    </div>
</div>

<!-- MODAL DESARROLLO -->
<div class="modal-overlay" id="devModalOverlay">
    <div class="modal-content dev-modal-content">
        <button class="modal-close" id="devModalClose"><i class="fas fa-times"></i></button>
        <div class="dev-icon"><i class="fas fa-code"></i></div>
        <h2>🚧 En Desarrollo</h2>
        <p class="dev-sub">Esta aplicación aún no está disponible</p>
        <div class="dev-info" id="devInfoContent"></div>
    </div>
</div>

<!-- MODAL ZOOM CAPTURA -->
<div class="screenshot-zoom-overlay" id="screenshotZoomOverlay">
    <button class="screenshot-zoom-close" id="screenshotZoomClose"><i class="fas fa-times"></i></button>
    <img id="screenshotZoomImage" src="" alt="Captura ampliada">
</div>

<!-- ============================================================== -->
<!-- MODAL INFO — VERSIÓN COMPLETA                                  -->
<!-- ============================================================== -->
<div class="modal-overlay" id="infoModalOverlay">
    <div class="modal-content info-modal-content">
        <button class="modal-close" id="infoModalClose"><i class="fas fa-times"></i></button>

        <div class="info-header">
            <div class="info-icon"><i class="fas fa-cubes"></i></div>
            <h2>YSWEBAPK</h2>
            <p class="info-sub">Plataforma de descargas de APKs</p>
            <div class="info-badges">
                <span class="info-badge"><i class="fas fa-check-circle"></i> v2.0</span>
                <span class="info-badge"><i class="fas fa-shield-alt"></i> Seguro</span>
                <span class="info-badge"><i class="fas fa-users"></i> Comunidad</span>
            </div>
        </div>

        <div class="info-section">
            <h3><i class="fas fa-info-circle"></i> ¿Qué es YSWEBAPK?</h3>
            <p>YSWEBAPK es una plataforma gratuita de descargas de aplicaciones Android (APKs) creada por YS-CODE. Aquí desarrolladores independientes pueden compartir sus creaciones con la comunidad, y los usuarios pueden descubrir, descargar y valorar aplicaciones de forma segura y sencilla.</p>
        </div>

        <div class="info-section">
            <h3><i class="fas fa-star"></i> Características principales</h3>
            <ul class="info-list">
                <li>Catálogo organizado por categorías (Juegos, Utilidades, Educación, Social, Entretenimiento)</li>
                <li>Sistema de valoraciones con estrellas (1 a 5) y comentarios con respuestas</li>
                <li>Búsqueda por nombre, categoría o descripción</li>
                <li>Contador de descargas en tiempo real</li>
                <li>Tema claro y oscuro configurable</li>
                <li>App Android oficial (disponible en el menú lateral)</li>
                <li>Notificaciones sobre nuevas versiones y actualizaciones</li>
                <li>Panel de administración para revisión manual</li>
            </ul>
        </div>

        <div class="info-section">
            <h3><i class="fas fa-shield-virus"></i> Seguridad y revisión</h3>
            <p>Todas las APKs subidas pasan por un proceso de revisión manual del administrador antes de ser publicadas. Además, se realiza un escaneo automático con la API de VirusTotal para detectar posibles amenazas.</p>
            <p>Los usuarios también pueden reportar aplicaciones sospechosas desde la ficha de cada app. Si detectamos contenido inapropiado, malware o violaciones de copyright, la aplicación se elimina de inmediato.</p>
        </div>

        <div class="info-section">
            <h3><i class="fas fa-users-cog"></i> Roles de usuario</h3>
            <ul class="info-list">
                <li><strong>Usuario:</strong> descarga, valora y comenta apps</li>
                <li><strong>Desarrollador:</strong> sube y actualiza sus propias APKs</li>
                <li><strong>Administrador:</strong> revisa, aprueba o deniega solicitudes</li>
            </ul>
        </div>

        <div class="info-section">
            <h3><i class="fas fa-code"></i> Creador</h3>
            <p><strong>YS-CODE</strong> — Desarrollador independiente apasionado por Android y el software libre. Crea herramientas útiles y comparte sus proyectos con la comunidad de forma totalmente gratuita.</p>
        </div>

        <div class="info-section">
            <h3><i class="fas fa-envelope"></i> Contacto y soporte</h3>
            <ul class="info-list">
                <li><i class="fas fa-envelope"></i> Email: <a href="mailto:yscode03@gmail.com" class="info-link">yscode03@gmail.com</a></li>
                <li><i class="fas fa-headset"></i> Chat: sección "Soporte Técnico" del menú</li>
                <li><i class="fab fa-android"></i> App oficial: descárgala desde el menú lateral</li>
            </ul>
        </div>

        <div class="info-section">
            <h3><i class="fas fa-file-contract"></i> Legal</h3>
            <ul class="info-list">
                <li><a href="terminos.php" class="info-link">Términos y Condiciones</a></li>
                <li><a href="privacidad.php" class="info-link">Política de Privacidad</a></li>
            </ul>
        </div>

        <div class="info-footer">
            <p>© 2026 YS-CODE · Todos los derechos reservados</p>
            <p class="info-copy">YSWEBAPK no se hace responsable del contenido subido por terceros.</p>
        </div>
    </div>
</div>

<!-- MODAL CONFIG -->
<div class="modal-overlay" id="configModalOverlay">
    <div class="modal-content config-modal-content">
        <button class="modal-close" id="configModalClose"><i class="fas fa-times"></i></button>
        <h2><i class="fas fa-cog"></i> Configuración</h2>
        <div class="config-option">
            <label>Tema</label>
            <div class="theme-toggle">
                <button id="themeLight" class="theme-btn active"><i class="fas fa-sun"></i> Claro</button>
                <button id="themeDark" class="theme-btn"><i class="fas fa-moon"></i> Oscuro</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL REPORTE -->
<div class="modal-overlay" id="modalReporte">
    <div class="modal-content" style="max-width:480px;">
        <button class="modal-close" onclick="document.getElementById('modalReporte').classList.remove('open'); document.body.style.overflow='';"><i class="fas fa-times"></i></button>
        <h2 style="margin-bottom:.5rem;"><i class="fas fa-flag" style="color:var(--danger)"></i> Reportar APK</h2>
        <p style="color:var(--text-secondary);font-size:.9rem;margin-bottom:1rem;">Ayúdanos a mantener la plataforma segura.</p>
        <input type="hidden" id="reportApkId" value="">
        <div class="form-group">
            <label>Motivo *</label>
            <select id="reportMotivo" required>
                <option value="contenido_inapropiado">Contenido inapropiado</option>
                <option value="malware">Malware / virus</option>
                <option value="spam">Spam</option>
                <option value="copyright">Violación de copyright</option>
                <option value="enlace_roto">Enlace roto</option>
                <option value="otro">Otro</option>
            </select>
        </div>
        <div class="form-group">
            <label>Descripción (opcional)</label>
            <textarea id="reportDescripcion" rows="3"></textarea>
        </div>
        <button class="btn-primary" style="width:100%;" onclick="enviarReporte()"><i class="fas fa-paper-plane"></i> Enviar Reporte</button>
    </div>
</div>

<!-- FOOTER -->
<div class="footer-info">
    <i class="fas fa-code"></i> Creado por <strong>YS-CODE</strong>
</div>

<script>
// ============================================
// YSWEBAPK - JS principal
// ============================================
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
const USUARIO_LOGGED = <?= json_encode(estaLogueado()) ?>;
const USUARIO_ID = <?= json_encode($_SESSION['usuario_id'] ?? null) ?>;
const ES_ADMIN = <?= json_encode(esAdmin()) ?>;
const ES_APP_ANDROID = <?= json_encode(esAppAndroid()) ?>;

let currentState = {
    filter: <?= json_encode($categoria ?: 'all') ?>,
    sort: <?= json_encode($orden) ?>,
    search: <?= json_encode($busqueda) ?>,
    page: <?= (int)$paginacion['pagina_actual'] ?>,
};

function apiCall(action, data = {}) {
    const body = new URLSearchParams({ action, csrf_token: CSRF_TOKEN, ...data });
    return fetch('api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body
    })
    .then(async r => {
        const text = await r.text();
        if (!text) return { success: false, message: 'Respuesta vacía del servidor' };
        try { return JSON.parse(text); } catch (e) { return { success: false, message: 'JSON inválido' }; }
    })
    .catch(err => ({ success: false, message: 'Error de conexión: ' + err.message }));
}

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

function descargarDesdeApp(url, apk_id) {
    registrarDescarga(apk_id);
    if (!url.startsWith('http')) {
        url = window.location.origin + '/' + url.replace(/^\/+/, '');
    }
    if (typeof window.AppInventor !== 'undefined') {
        window.AppInventor.setWebViewString("DOWNLOAD:" + url);
    } else {
        window.location.href = url;
    }
}

function mostrarNotificacion(msg) {
    const n = document.getElementById('linkNotification');
    document.getElementById('linkNotificationText').textContent = msg;
    n.classList.add('show');
    clearTimeout(n._t);
    n._t = setTimeout(() => n.classList.remove('show'), 3200);
}

function abrirMenu() {
    document.getElementById('menuLateral').classList.add('open');
    document.getElementById('menuOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function cerrarMenu() {
    document.getElementById('menuLateral').classList.remove('open');
    document.getElementById('menuOverlay').classList.remove('open');
    document.body.style.overflow = '';
}
function abrirInfo() { cerrarMenu(); document.getElementById('infoModalOverlay').classList.add('open'); document.body.style.overflow='hidden'; }
function abrirConfig() { cerrarMenu(); document.getElementById('configModalOverlay').classList.add('open'); document.body.style.overflow='hidden'; }

function loadTheme() {
    const t = localStorage.getItem('yswebapk_theme') || 'light';
    if (t === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        document.getElementById('themeDark').classList.add('active');
        document.getElementById('themeLight').classList.remove('active');
    } else {
        document.documentElement.removeAttribute('data-theme');
        document.getElementById('themeLight').classList.add('active');
        document.getElementById('themeDark').classList.remove('active');
    }
}
function setTheme(t) { localStorage.setItem('yswebapk_theme', t); loadTheme(); }
document.getElementById('themeLight').addEventListener('click', () => setTheme('light'));
document.getElementById('themeDark').addEventListener('click', () => setTheme('dark'));
loadTheme();

const stickyHeader = document.getElementById('stickyHeader');
window.addEventListener('scroll', () => {
    stickyHeader.classList.toggle('scrolled', window.scrollY > 10);
}, { passive: true });

let searchVisible = false;
const searchContainer = document.getElementById('searchContainer');
const searchInput = document.getElementById('searchInput');
const searchClear = document.getElementById('searchClear');
const toggleSearchBtn = document.getElementById('toggleSearchBtn');
let searchDebounce = null;

if (searchInput.value) searchClear.classList.add('visible');

toggleSearchBtn.addEventListener('click', () => {
    searchVisible = !searchVisible;
    if (searchVisible) {
        searchContainer.classList.add('visible');
        toggleSearchBtn.classList.add('active-btn');
        toggleSearchBtn.innerHTML = '<i class="fas fa-times"></i>';
        setTimeout(() => searchInput.focus(), 350);
    } else {
        searchContainer.classList.remove('visible');
        toggleSearchBtn.classList.remove('active-btn');
        toggleSearchBtn.innerHTML = '<i class="fas fa-search"></i>';
    }
});

searchInput.addEventListener('input', function() {
    const v = this.value;
    if (v.length > 0) searchClear.classList.add('visible');
    else searchClear.classList.remove('visible');
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        currentState.search = v;
        currentState.page = 1;
        cargarApks();
    }, 350);
});

searchClear.addEventListener('click', () => {
    searchInput.value = '';
    currentState.search = '';
    currentState.page = 1;
    searchClear.classList.remove('visible');
    cargarApks();
});

document.getElementById('quickMenu').addEventListener('click', function(e) {
    const btn = e.target.closest('button[data-filter]');
    if (!btn) return;
    this.querySelectorAll('button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentState.filter = btn.dataset.filter;
    currentState.page = 1;
    cargarApks();
});

document.getElementById('sortSelect').addEventListener('change', function() {
    currentState.sort = this.value;
    currentState.page = 1;
    cargarApks();
});

function cargarApks() {
    const grid = document.getElementById('appGrid');
    grid.innerHTML = '<div class="loading-overlay"><i class="fas fa-spinner fa-pulse"></i> Cargando...</div>';

    apiCall('listar_apks', {
        q: currentState.search,
        categoria: currentState.filter,
        orden: currentState.sort,
        pagina: currentState.page
    }).then(data => {
        if (!data.success) {
            grid.innerHTML = '<div class="sin-resultados"><i class="fas fa-exclamation-triangle"></i>' + escHtml(data.message || 'Error al cargar') + '</div>';
            return;
        }
        renderGrid(data.apks);
        renderPagination(data.paginacion);
        document.getElementById('resultCount').textContent = data.paginacion.total;
        actualizarURL();
    });
}

function renderGrid(apks) {
    const grid = document.getElementById('appGrid');
    if (!apks || apks.length === 0) {
        grid.innerHTML = '<div class="sin-resultados"><i class="fas fa-search"></i>No se encontraron APKs</div>';
        return;
    }

    const cats = {'juego':'Juegos','utilidad':'Utilidades','educación':'Educación','social':'Social','entretenimiento':'Entretenimiento'};
    grid.innerHTML = apks.map(app => {
        const dev = !!app.en_desarrollo;
        const promedio = parseFloat(app.promedio_estrellas) || 0;
        const estrellas = [1,2,3,4,5].map(i => i <= Math.round(promedio) ? '★' : '☆').join('');
        const icono = app.icono
            ? `<img src="${escAttr(app.icono)}" alt="icono" loading="lazy" onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\\'fas fa-cube\\'></i>'">`
            : '<i class="fas fa-cube"></i>';
        const devBadge = dev ? '<span class="dev-badge">🚧 Desarrollo</span>' : '';
        const devText = dev ? '<div class="app-dev-text">En desarrollo</div>' : '';
        const creadorInfo = app.creador_id
            ? `<div class="desarrollador-info"><i class="fas fa-user"></i> ${escHtml(app.creador_nombre||'Desarrollador')}${app.creador_rol==='admin'?' <span style="color:#dc2626;">👑</span>':''}</div>`
            : '';
        return `<div class="app-card ${dev?'dev-mode':''}" onclick="abrirModal(${app.id})">
            ${devBadge}
            <div class="app-icon">${icono}</div>
            <div class="app-name">${escHtml(app.nombre)}</div>
            <div class="app-version">v${escHtml(app.version||'1.0')}</div>
            <div class="app-category">${escHtml(cats[app.categoria]||app.categoria||'Sin categoría')}</div>
            <div class="rating"><span class="stars">${estrellas}</span><span>(${promedio.toFixed(1)})</span><span>·</span><span><i class="fas fa-comment"></i> ${app.comentarios_count||0}</span></div>
            <div class="descargas"><i class="fas fa-download"></i> ${app.descargas||0} descargas</div>
            ${devText}${creadorInfo}
        </div>`;
    }).join('');
}

function renderPagination(pag) {
    const cont = document.getElementById('pagination');
    if (!pag || pag.total_paginas <= 1) { cont.innerHTML = ''; return; }
    let html = '';
    html += `<button onclick="irAPagina(${pag.pagina_actual-1})" ${!pag.hay_anterior?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;
    const ini = Math.max(1, pag.pagina_actual - 2);
    const fin = Math.min(pag.total_paginas, pag.pagina_actual + 2);
    if (ini > 1) { html += `<button onclick="irAPagina(1)">1</button>`; if (ini>2) html += '<span style="color:var(--text-secondary)">…</span>'; }
    for (let i = ini; i <= fin; i++) html += `<button onclick="irAPagina(${i})" class="${i===pag.pagina_actual?'active':''}">${i}</button>`;
    if (fin < pag.total_paginas) { if (fin < pag.total_paginas-1) html += '<span style="color:var(--text-secondary)">…</span>'; html += `<button onclick="irAPagina(${pag.total_paginas})">${pag.total_paginas}</button>`; }
    html += `<button onclick="irAPagina(${pag.pagina_actual+1})" ${!pag.hay_siguiente?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;
    cont.innerHTML = html;
}

function irAPagina(p) {
    if (p < 1) return;
    currentState.page = p;
    cargarApks();
    window.scrollTo({top: 0, behavior: 'smooth'});
}

function actualizarURL() {
    const p = new URLSearchParams();
    if (currentState.search) p.set('q', currentState.search);
    if (currentState.filter && currentState.filter !== 'all') p.set('categoria', currentState.filter);
    if (currentState.sort && currentState.sort !== 'id') p.set('orden', currentState.sort);
    if (currentState.page > 1) p.set('pagina', currentState.page);
    const qs = p.toString();
    history.replaceState(null, '', qs ? '?'+qs : window.location.pathname);
}

function escHtml(s) { return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function escAttr(s) { return escHtml(s); }

const modalOverlay = document.getElementById('modalOverlay');
const modalBody = document.getElementById('modalBody');
const btnComentarios = document.getElementById('btnComentarios');

function abrirModal(id) {
    modalBody.innerHTML = '<div class="loading-overlay"><i class="fas fa-spinner fa-pulse"></i> Cargando...</div>';
    modalOverlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    btnComentarios.style.display = 'none';

    apiCall('detalle_apk', { id }).then(data => {
        if (!data.success) {
            modalBody.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--danger)">' + escHtml(data.message || 'Error') + '</div>';
            return;
        }
        renderModal(data.apk);
    });
}

function renderModal(app) {
    if (app.en_desarrollo) {
        modalOverlay.classList.remove('open');
        document.body.style.overflow = '';
        document.getElementById('devInfoContent').innerHTML =
            `<p><i class="fas fa-spinner fa-pulse"></i> <strong>${escHtml(app.nombre)}</strong> aún no está disponible.</p>
             <p style="margin-top:.5rem;font-size:.9rem;color:var(--text-secondary)">${escHtml(app.descripcion||'Sin descripción')}</p>`;
        document.getElementById('devModalOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
        return;
    }

    const promedio = parseFloat(app.promedio_estrellas) || 0;
    const estrellas = [1,2,3,4,5].map(i => i <= Math.round(promedio) ? '★' : '☆').join('');
    const cats = {'juego':'Juegos','utilidad':'Utilidades','educación':'Educación','social':'Social','entretenimiento':'Entretenimiento'};
    const catLabel = cats[app.categoria] || app.categoria || 'Sin categoría';
    const icono = app.icono ? `<img src="${escAttr(app.icono)}" alt="icono">` : '<i class="fas fa-cube"></i>';

    let screenshots = [];
    if (app.screenshots) {
        try { screenshots = typeof app.screenshots === 'string' ? JSON.parse(app.screenshots) : app.screenshots; } catch(e) {}
    }
    const ssHtml = (Array.isArray(screenshots) && screenshots.length && screenshots[0])
        ? screenshots.map(s => `<img src="${escAttr(s)}" alt="captura" loading="lazy" class="screenshot-thumb" data-src="${escAttr(s)}" style="cursor:pointer;">`).join('')
        : '<span style="color:var(--text-secondary);font-size:.8rem">Sin capturas</span>';

    const descargable = app.descargable !== false;
    const linkSeguro = app.link || '';
    const esExterno = linkSeguro.startsWith('http');
    const baseUrl = window.location.origin;

    const urlWeb = esExterno ? linkSeguro : `${baseUrl}/descargas.php?id=${app.id}`;
    const urlApp = esExterno ? linkSeguro : `${baseUrl}/descargas.php?id=${app.id}`;

    const creadorHtml = app.creador_id
        ? `${escHtml(app.creador_nombre||'Desarrollador')} ${app.creador_rol==='admin'?'<span style="color:#dc2626;font-size:.7rem">(Admin)</span>':''}`
        : 'YS-CODE';

    let changelogHtml = '';
    if (app.changelog && app.changelog.trim() !== '') {
        changelogHtml = `
            <div style="background:var(--bg-card); border:1px solid var(--border-color); padding:1rem; border-radius:var(--radius-md); margin-top:0.8rem;">
                <strong style="color:var(--primary); font-size:0.9rem;"><i class="fas fa-list-ul"></i> Novedades de esta versión:</strong>
                <div style="font-size:0.85rem; color:var(--text-secondary); margin-top:0.3rem;">${escHtml(app.changelog).replace(/\n/g, '<br>')}</div>
            </div>
        `;
    }

    let downloadButtonHtml = '';
    if (descargable) {
        if (ES_APP_ANDROID) {
            downloadButtonHtml = `<button onclick="descargarDesdeApp('${escAttr(urlApp)}', ${app.id})" class="download-btn" style="border:none;width:100%;font-family:inherit;cursor:pointer;">
                <i class="fas fa-download"></i> Descargar APK<br>${escHtml(app.nombre)} v${escHtml(app.version||'1.0')}.apk
            </button>`;
        } else if (esExterno) {
            downloadButtonHtml = `<a href="${escAttr(linkSeguro)}" target="_blank" rel="noopener" onclick="registrarDescarga(${app.id})" class="download-btn">
                <i class="fas fa-download"></i> Descargar APK (externo)<br>${escHtml(app.nombre)} v${escHtml(app.version||'1.0')}.apk
            </a>`;
        } else {
            downloadButtonHtml = `<a href="${escAttr(urlWeb)}" class="download-btn">
                <i class="fas fa-download"></i> Descargar APK<br>${escHtml(app.nombre)} v${escHtml(app.version||'1.0')}.apk
            </a>`;
        }
    } else {
        downloadButtonHtml = `<button class="download-btn disabled" onclick="return false;" disabled>
            <i class="fas fa-ban"></i> No disponible
        </button>`;
    }

    // ✅ BLOQUE DE ESTRELLAS/COMENTARIOS CLICKEABLE — va a apk-detalle.php
    const ratingModalHtml = `
        <div style="text-align:center;margin:.5rem 0 .6rem;">
            <div class="modal-rating-clickable"
                 title="Ver comentarios y valoraciones"
                 role="button"
                 tabindex="0"
                 onclick="irAComentarios(${app.id})"
                 onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();irAComentarios(${app.id});}">
                <span style="color:#f59e0b;font-size:1.2rem">${estrellas}</span>
                <span style="font-size:.85rem;color:var(--text-secondary)">(${promedio.toFixed(1)}) · <i class="fas fa-comment"></i> ${app.comentarios_count||0}</span>
                <span class="modal-rating-hint"><i class="fas fa-arrow-right"></i> Ver comentarios</span>
            </div>
        </div>
    `;

    modalBody.innerHTML = `
        <div class="modal-icon">${icono}</div>
        <div class="modal-title">${escHtml(app.nombre)}</div>
        <div class="modal-creador"><i class="fas fa-user"></i> Creado por: <strong>${creadorHtml}</strong></div>
        ${ratingModalHtml}
        <div class="modal-meta">
            <span><i class="fas fa-tag"></i> ${escHtml(catLabel)}</span>
            <span><i class="fas fa-code-branch"></i> v${escHtml(app.version||'1.0')}</span>
            <span><i class="fas fa-hdd"></i> ${escHtml(app.tamano||'0 MB')}</span>
            <span><i class="fas fa-download"></i> ${app.descargas||0} descargas</span>
        </div>
        <div class="modal-desc">${escHtml(app.descripcion||'Sin descripción')}</div>
    ${app.link_url ? `<div style="text-align:center; margin-top: -0.5rem; margin-bottom: 0.8rem;"><a href="${escHtml(app.link_url)}" target="_blank" rel="noopener" style="color:var(--primary); font-weight:700; text-decoration:underline;"><i class="fas fa-link"></i> ${escHtml(app.link_texto || 'Ver más')}</a></div>` : ''}
        ${changelogHtml}
        <div class="modal-screenshots" style="margin-top:1rem;">${ssHtml}</div>
        ${downloadButtonHtml}
        <div class="modal-actions-row">
            <button class="btn-compartir" onclick="compartirApp(${app.id})"><i class="fas fa-share-alt"></i> Compartir</button>
            ${USUARIO_LOGGED ? `<button class="btn-danger-soft" onclick="abrirReporte(${app.id})"><i class="fas fa-flag"></i> Reportar</button>` : ''}
        </div>
    `;

    modalBody.querySelectorAll('.screenshot-thumb').forEach(img => {
        img.addEventListener('click', e => {
            e.stopPropagation();
            document.getElementById('screenshotZoomImage').src = img.dataset.src;
            document.getElementById('screenshotZoomOverlay').classList.add('open');
        });
    });

    btnComentarios.style.display = 'block';
    btnComentarios.onclick = () => irAComentarios(app.id);
}

/* Ir a la página de comentarios (cierra el modal antes de navegar) */
function irAComentarios(apkId) {
    cerrarModal();
    window.location.href = 'apk-detalle.php?id=' + apkId;
}

function cerrarModal() {
    modalOverlay.classList.remove('open');
    document.body.style.overflow = '';
    btnComentarios.style.display = 'none';
}

document.getElementById('modalClose').addEventListener('click', cerrarModal);
modalOverlay.addEventListener('click', e => { if (e.target === modalOverlay) cerrarModal(); });

document.getElementById('screenshotZoomClose').addEventListener('click', () => {
    document.getElementById('screenshotZoomOverlay').classList.remove('open');
});
document.getElementById('screenshotZoomOverlay').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
});

['devModalClose','infoModalClose','configModalClose'].forEach(id => {
    document.getElementById(id).addEventListener('click', function() {
        this.closest('.modal-overlay').classList.remove('open');
        document.body.style.overflow = '';
    });
});
['infoModalOverlay','configModalOverlay','devModalOverlay'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) { this.classList.remove('open'); document.body.style.overflow = ''; }
    });
});

async function compartirApp(id) {
    const data = await apiCall('detalle_apk', { id });
    if (!data.success) return;
    const app = data.apk;
    const url = window.location.origin + window.location.pathname + '?app=' + encodeURIComponent(app.nombre);
    const shareData = { title: app.nombre + ' - YSWEBAPK', text: 'Descarga ' + app.nombre + ' v' + (app.version || '1.0'), url: url };
    if (navigator.share) {
        try { await navigator.share(shareData); return; } catch(e) {}
    }
    try { await navigator.clipboard.writeText(url); mostrarNotificacion('🔗 Enlace copiado'); } catch(e) { prompt('Copia este enlace:', url); }
}

function abrirReporte(id) {
    if (!USUARIO_LOGGED) { mostrarNotificacion('❌ Inicia sesión'); return; }
    document.getElementById('reportApkId').value = id;
    document.getElementById('reportMotivo').value = 'contenido_inapropiado';
    document.getElementById('reportDescripcion').value = '';
    document.getElementById('modalReporte').classList.add('open');
    document.body.style.overflow = 'hidden';
}
async function enviarReporte() {
    const apk_id = document.getElementById('reportApkId').value;
    const motivo = document.getElementById('reportMotivo').value;
    const descripcion = document.getElementById('reportDescripcion').value;
    const r = await apiCall('reportar_apk', { apk_id, motivo, descripcion });
    mostrarNotificacion(r.success ? '✅ ' + r.message : '❌ ' + r.message);
    if (r.success) {
        document.getElementById('modalReporte').classList.remove('open');
        document.body.style.overflow = '';
    }
}
document.getElementById('modalReporte').addEventListener('click', function(e) {
    if (e.target === this) { this.classList.remove('open'); document.body.style.overflow = ''; }
});

(function deepLink() {
    const appParam = new URLSearchParams(window.location.search).get('app');
    if (appParam) {
        apiCall('buscar_apk_por_nombre', { nombre: appParam }).then(data => {
            if (data.success) setTimeout(() => { modalOverlay.classList.add('open'); document.body.style.overflow = 'hidden'; renderModal(data.apk); }, 300);
        });
    }
})();

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        cerrarModal();
        document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('open'));
        document.getElementById('screenshotZoomOverlay').classList.remove('open');
        cerrarMenu();
        document.body.style.overflow = '';
    }
});
</script>

</body>
</html>