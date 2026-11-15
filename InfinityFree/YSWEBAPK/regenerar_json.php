<?php
require_once 'config/database.php';
require_once 'config/funciones.php';
require_once 'config/generar_versiones.php';

// Solo admin puede regenerar
if (!esAdmin()) {
    header('Location: index.php');
    exit;
}

$resultado = generarVersionesJson($pdo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regenerar versiones.json - YSWEBAPK</title>
    <link rel="icon" href="Ico.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: var(--bg-body, #f4f7fc);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }
        .result-card {
            background: var(--bg-card, #fff);
            border-radius: 24px;
            padding: 2rem;
            max-width: 520px;
            width: 100%;
            text-align: center;
            box-shadow: 0 12px 40px rgba(0,0,0,0.08);
            border: 1px solid var(--border-color, #e2e8f0);
        }
        .result-card .icon {
            font-size: 4rem;
            margin-bottom: 0.5rem;
        }
        .result-card .icon.ok { color: #16a34a; }
        .result-card .icon.err { color: #dc2626; }
        .result-card h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-primary, #1e293b);
        }
        .result-card p {
            color: var(--text-secondary, #64748b);
            font-size: 0.95rem;
            margin-bottom: 1rem;
        }
        .result-card .info-box {
            background: var(--bg-hover, #f1f5f9);
            border-radius: 12px;
            padding: 1rem;
            margin: 1rem 0;
            text-align: left;
            font-size: 0.9rem;
            border: 1px solid var(--border-color, #e2e8f0);
        }
        .result-card .info-box .row {
            display: flex;
            justify-content: space-between;
            padding: 0.3rem 0;
            border-bottom: 1px solid var(--border-color, #e2e8f0);
        }
        .result-card .info-box .row:last-child { border-bottom: none; }
        .result-card .info-box .row strong {
            color: var(--text-primary, #1e293b);
        }
        .result-card .info-box .row span {
            color: var(--text-secondary, #64748b);
            font-family: monospace;
        }
        .result-card .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            margin: 0.3rem;
        }
        .result-card .btn-primary {
            background: #1a4b7a;
            color: #fff;
        }
        .result-card .btn-primary:hover {
            background: #0f3a5f;
            transform: translateY(-2px);
        }
        .result-card .btn-secondary {
            background: var(--bg-hover, #f1f5f9);
            color: var(--text-primary, #1e293b);
            border: 1px solid var(--border-color, #e2e8f0);
        }
        .result-card .btn-secondary:hover {
            background: var(--border-color, #e2e8f0);
        }
        .result-card .link-view {
            display: block;
            margin-top: 1rem;
            font-size: 0.85rem;
            color: var(--primary, #1a4b7a);
            text-decoration: none;
            word-break: break-all;
        }
        .result-card .link-view:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="result-card">
    <?php if ($resultado['ok']): ?>
        <div class="icon ok"><i class="fas fa-check-circle"></i></div>
        <h1>¡JSON regenerado!</h1>
        <p>El archivo <strong>versiones.json</strong> fue actualizado correctamente.</p>

        <div class="info-box">
            <div class="row">
                <strong>APKs incluidas</strong>
                <span><?= (int)$resultado['total'] ?></span>
            </div>
            <div class="row">
                <strong>Fecha</strong>
                <span><?= htmlspecialchars($resultado['fecha']) ?></span>
            </div>
            <div class="row">
                <strong>Ruta</strong>
                <span><?= htmlspecialchars(basename($resultado['ruta'])) ?></span>
            </div>
        </div>

        <?php
            $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                    . '://' . $_SERVER['HTTP_HOST'];
            $url_json = $base . '/versiones.json';
        ?>
        <a href="<?= htmlspecialchars($url_json) ?>" target="_blank" class="link-view">
            <i class="fas fa-external-link-alt"></i> Ver versiones.json
        </a>

    <?php else: ?>
        <div class="icon err"><i class="fas fa-times-circle"></i></div>
        <h1>Error al regenerar</h1>
        <p><?= htmlspecialchars($resultado['error']) ?></p>
    <?php endif; ?>

    <div style="margin-top:1.5rem;">
        <a href="regenerar_json.php" class="btn btn-secondary">
            <i class="fas fa-sync-alt"></i> Regenerar otra vez
        </a>
        <a href="admin.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Volver al panel
        </a>
    </div>
</div>

</body>
</html>