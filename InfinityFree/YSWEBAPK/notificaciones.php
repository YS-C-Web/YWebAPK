<?php
require_once 'config/database.php';
require_once 'config/funciones.php';

if (!estaLogueado()) { header('Location: login.php'); exit; }

$usuario_id = $_SESSION['usuario_id'];
$por_pagina = 15;
$pagina = max(1, intval($_GET['pag'] ?? 1));

// Marcar todas como leídas al entrar
$pdo->prepare("UPDATE notificaciones SET leida = TRUE WHERE usuario_id = ? AND leida = FALSE")
    ->execute([$usuario_id]);

// Total + paginación
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$total = (int)$stmt->fetchColumn();
$pag = calcularPaginacion($total, $por_pagina, $pagina);

$stmt = $pdo->prepare("SELECT * FROM notificaciones WHERE usuario_id = ? ORDER BY creado_en DESC LIMIT {$pag['por_pagina']} OFFSET {$pag['offset']}");
$stmt->execute([$usuario_id]);
$notificaciones = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= generarCSRF() ?>">
    <title>Notificaciones - YSWEBAPK</title>
    <link rel="icon" href="Ico.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .notif-container { max-width: 720px; margin: 2rem auto; padding: 0 1rem; }
        .notif-container h1 { margin-bottom: 1.5rem; display: flex; align-items: center; gap: .6rem; }
        .notif-item {
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: 16px; padding: 1rem 1.2rem; margin-bottom: .8rem;
            display: flex; gap: 1rem; align-items: flex-start;
            transition: var(--transition);
        }
        .notif-item:hover { border-color: var(--primary); }
        .notif-item.nueva { border-left: 4px solid var(--primary); }
        .notif-item .icono {
            width: 42px; height: 42px; border-radius: 50%;
            background: var(--primary-light); color: var(--primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; flex-shrink: 0;
        }
        .notif-item .icono.comentario { background: #dbeafe; color: #1e40af; }
        .notif-item .icono.apk { background: #dcfce7; color: #15803d; }
        .notif-item .icono.solicitud { background: #fef3c7; color: #92400e; }
        .notif-item .icono.like { background: #fee2e2; color: #b91c1c; }
        .notif-item .contenido { flex: 1; }
        .notif-item .contenido .titulo { font-weight: 600; margin-bottom: .2rem; }
        .notif-item .contenido .mensaje { color: var(--text-secondary); font-size: .9rem; }
        .notif-item .contenido .fecha { font-size: .75rem; color: var(--text-light); margin-top: .3rem; }
        .sin-notifs { text-align: center; padding: 3rem 1rem; color: var(--text-secondary); }
        .sin-notifs i { font-size: 3rem; display: block; margin-bottom: .5rem; }
        .pagination { display: flex; justify-content: center; align-items: center; gap: .4rem; margin: 2rem 0; flex-wrap: wrap; }
        .pagination a, .pagination span {
            min-width: 38px; height: 38px; padding: 0 .6rem;
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: var(--radius-md); color: var(--text-primary);
            font-weight: 600; text-decoration: none; transition: var(--transition);
            display: inline-flex; align-items: center; justify-content: center;
        }
        .pagination a:hover { border-color: var(--primary); }
        .pagination .active { background: var(--primary); color: white; border-color: var(--primary); }
    </style>
</head>
<body>

<div class="notif-container">
    <a href="index.php" class="btn-sm btn-primary" style="padding:.5rem 1rem;display:inline-block;text-decoration:none;border-radius:8px;background:var(--primary);color:white;margin-bottom:1rem;">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
    <h1><i class="fas fa-bell" style="color:var(--primary)"></i> Notificaciones</h1>

    <?php if (empty($notificaciones)): ?>
        <div class="sin-notifs">
            <i class="fas fa-bell-slash"></i>
            No tienes notificaciones todavía
        </div>
    <?php else: ?>
        <?php foreach ($notificaciones as $n): ?>
            <div class="notif-item <?= $n['leida'] ? '' : 'nueva' ?>">
                <div class="icono <?= escapar($n['tipo']) ?>">
                    <?php
                        switch ($n['tipo']) {
                            case 'comentario': echo '<i class="fas fa-comment"></i>'; break;
                            case 'apk':        echo '<i class="fas fa-cube"></i>'; break;
                            case 'solicitud':  echo '<i class="fas fa-file-signature"></i>'; break;
                            case 'like':       echo '<i class="fas fa-heart"></i>'; break;
                            default:           echo '<i class="fas fa-bell"></i>';
                        }
                    ?>
                </div>
                <div class="contenido">
                    <div class="titulo"><?= escapar($n['titulo']) ?></div>
                    <?php if (!empty($n['mensaje'])): ?>
                        <div class="mensaje"><?= nl2br(escapar($n['mensaje'])) ?></div>
                    <?php endif; ?>
                    <div class="fecha"><?= escapar(tiempoRelativo($n['creado_en'])) ?></div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($pag['total_paginas'] > 1): ?>
            <div class="pagination">
                <?php if ($pag['hay_anterior']): ?>
                    <a href="?pag=<?= $pag['pagina_actual'] - 1 ?>"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php
                    $ini = max(1, $pag['pagina_actual'] - 2);
                    $fin = min($pag['total_paginas'], $pag['pagina_actual'] + 2);
                    for ($i = $ini; $i <= $fin; $i++) {
                        if ($i == $pag['pagina_actual']) echo "<span class='active'>{$i}</span>";
                        else echo "<a href='?pag={$i}'>{$i}</a>";
                    }
                ?>
                <?php if ($pag['hay_siguiente']): ?>
                    <a href="?pag=<?= $pag['pagina_actual'] + 1 ?>"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>