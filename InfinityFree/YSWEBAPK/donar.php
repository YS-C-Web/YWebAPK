<?php
require_once 'config/database.php';
require_once 'config/funciones.php';

// ============================================================
// CONFIGURACIÓN DE DONACIONES
// Edita SOLO este bloque para actualizar los datos.
// Los campos vacíos ('') no se muestran en la página.
// ============================================================
$DONACIONES = [
    // ----- Transfermóvil (Cuba) -----
    'tarjeta_numero'   => '9238-1299-7811-6475',
    'tarjeta_titular'  => 'Yuniel Sánchez (YS-CODE)',
    'tarjeta_telefono' => '+5351047336',

    // ----- Recarga de saldo (Cuba) -----
    'saldo_telefono'   => '+5351047336',
    'saldo_compania'   => 'CUBACEL',

    // ----- Trust Wallet (exterior) -----
    'wallet' => [
        'USDT' => '0x820f6cc67b7d0aa25bb86e1787e923a16980ae89',
        'USDC' => '0x820f6cc67b7d0aa25bb86e1787e923a16980ae89',
        'SOL'  => '2gxAxgoaSaHSBgbLB5UEn8PPB6rgMdpCRV7ECHhJebSb',
        'BTC'  => 'bc1q82x2dsjh2fuxeap52njtr8xjws6n6juaqwerxn',
    ],

    // ----- Mensaje personal (opcional) -----
    'mensaje' => 'Cada donación me ayuda a pagar el hosting y seguir subiendo APKs',

    // ----- Meta mensual (opcional) -----
    'meta_activa' => false,
    'meta_actual' => 0,
    'meta_total'  => 20,
    'meta_moneda' => 'USD',

    // ----- Servicios de recarga internacional (solo nombre + URL para copiar) -----
    'servicios_recarga' => [
        ['nombre' => 'Ding.com',           'url' => 'https://www.ding.com/'],
        ['nombre' => 'Recharge.com',       'url' => 'https://www.recharge.com/'],
        ['nombre' => 'RecargaCubaCel.com', 'url' => 'https://www.recargacubacel.com/'],
        ['nombre' => 'CubaCell.net',       'url' => 'https://www.cubacell.net/'],
    ],
];

// ===== Cálculos automáticos =====
$tarjeta_display = $DONACIONES['tarjeta_numero'];
$tarjeta_copy    = preg_replace('/[^0-9]/', '', $tarjeta_display);

$tel_raw     = preg_replace('/[^0-9]/', '', $DONACIONES['tarjeta_telefono']);
$tel_sin_53  = preg_replace('/^53/', '', $tel_raw);
$tel_cuba_display = substr($tel_sin_53, 0, 4) . ' ' . substr($tel_sin_53, 4);
$tel_intl_display = '+53 ' . $tel_cuba_display;
$tel_cuba_copy    = $tel_sin_53;
$tel_intl_copy    = '+53' . $tel_sin_53;

$hay_wallets = false;
foreach ($DONACIONES['wallet'] as $addr) {
    if (trim($addr) !== '') { $hay_wallets = true; break; }
}

$meta_pct = 0;
if ($DONACIONES['meta_activa'] && $DONACIONES['meta_total'] > 0) {
    $meta_pct = min(100, round(($DONACIONES['meta_actual'] / $DONACIONES['meta_total']) * 100));
}

// ===== Info por moneda (label, red, comisión, icono, color) =====
$redes_info = [
    'USDT' => [
        'label' => 'USDT', 'sublabel' => 'Tether USD',
        'network' => 'BEP20 (BSC) o ERC20 (Ethereum)',
        'comision' => '~$0.10 en BEP20',
        'icon' => 'fas fa-dollar-sign',
        'color' => '#065f46', 'bg' => '#d1fae5',
    ],
    'USDC' => [
        'label' => 'USDC', 'sublabel' => 'USD Coin',
        'network' => 'BEP20 (BSC) o ERC20 (Ethereum)',
        'comision' => '~$0.10 en BEP20',
        'icon' => 'fas fa-dollar-sign',
        'color' => '#1e40af', 'bg' => '#dbeafe',
    ],
    'SOL' => [
        'label' => 'SOL', 'sublabel' => 'Solana',
        'network' => 'Red Solana',
        'comision' => '~$0.001',
        'icon' => 'fas fa-sun',
        'color' => '#6b21a8', 'bg' => '#f3e8ff',
    ],
    'BTC' => [
        'label' => 'BTC', 'sublabel' => 'Bitcoin',
        'network' => 'Red Bitcoin nativa',
        'comision' => '~$2.00',
        'icon' => 'fab fa-bitcoin',
        'color' => '#9a3412', 'bg' => '#ffedd5',
    ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php renderMetaSEO([
    'title'       => 'Apoyar el proyecto - Donaciones | YSWEBAPK',
    'description' => 'Apoya YSWEBAPK con una donación desde Cuba (Transfermóvil) o desde el exterior (Trust Wallet). Cada aporte mantiene el sitio gratuito.',
]); ?>
<link rel="icon" href="Ico.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .donar-container { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }

        /* ===== HERO ===== */
        .donar-hero {
            background: linear-gradient(135deg, #fee2e2 0%, #fef3c7 100%);
            border: 1px solid #fecaca;
            border-radius: var(--radius-xl);
            padding: 2.5rem 1.5rem;
            text-align: center;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-md);
        }
        [data-theme="dark"] .donar-hero {
            background: linear-gradient(135deg, #450a0a 0%, #422006 100%);
            border-color: #7f1d1d;
        }
        .donar-hero .hero-icon {
            font-size: 3.5rem; color: var(--danger);
            margin-bottom: 0.5rem;
            animation: latido 1.5s ease-in-out infinite;
        }
        @keyframes latido {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.15); }
        }
        .donar-hero h1 { font-size: 2rem; margin-bottom: 0.5rem; color: var(--text-primary); font-weight: 800; }
        .donar-hero p { color: var(--text-secondary); font-size: 1.05rem; max-width: 560px; margin: 0 auto 1rem; }
        .donar-hero .hero-badges { display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap; }
        .donar-hero .hero-badge {
            background: var(--bg-card); border: 1px solid var(--border-color);
            padding: 0.3rem 0.9rem; border-radius: 999px; font-size: 0.8rem;
            font-weight: 600; color: var(--text-primary); display: inline-flex;
            align-items: center; gap: 0.35rem;
        }
        .donar-hero .hero-badge i { color: var(--danger); }

        /* ===== SECCIONES ===== */
        .donar-section {
            background: var(--bg-card); border-radius: var(--radius-lg);
            border: 1px solid var(--border-color); padding: 1.5rem;
            margin-bottom: 1.25rem; box-shadow: var(--shadow-sm);
        }
        .donar-section h2 {
            font-size: 1.25rem; margin-bottom: 1rem;
            display: flex; align-items: center; gap: 0.5rem; color: var(--text-primary);
        }
        .donar-section h2 i { color: var(--primary); }

        /* ===== POR QUÉ DONAR ===== */
        .porque-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.8rem; }
        .porque-card { background: var(--bg-hover); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem; text-align: center; }
        .porque-card .pc-icon { font-size: 1.8rem; color: var(--primary); margin-bottom: 0.4rem; }
        .porque-card .pc-title { font-weight: 700; font-size: 0.9rem; margin-bottom: 0.2rem; color: var(--text-primary); }
        .porque-card .pc-desc { font-size: 0.78rem; color: var(--text-secondary); line-height: 1.4; }

        /* ===== MENSAJE PERSONAL ===== */
        .donar-mensaje {
            background: var(--primary-light); border-left: 4px solid var(--primary);
            padding: 0.8rem 1rem; border-radius: var(--radius-md);
            font-size: 0.9rem; color: var(--text-primary); margin-bottom: 1.25rem;
        }
        .donar-mensaje i { color: var(--primary); margin-right: 0.3rem; }

        /* ===== META ===== */
        .meta-wrap { background: var(--bg-hover); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1.25rem; }
        .meta-info { display: flex; justify-content: space-between; font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem; }
        .meta-bar { height: 14px; background: var(--bg-card); border-radius: 999px; overflow: hidden; border: 1px solid var(--border-color); }
        .meta-bar-fill { height: 100%; background: linear-gradient(90deg, var(--success), #16a34a); transition: width 0.4s ease; border-radius: 999px; }

        /* ===== TABS ===== */
        .donar-tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .donar-tab-btn {
            flex: 1; min-width: 140px; padding: 0.7rem 1rem; border-radius: var(--radius-md);
            border: 2px solid var(--border-color); background: var(--bg-card); color: var(--text-primary);
            font-weight: 700; font-size: 0.9rem; cursor: pointer; transition: var(--transition-fast);
            font-family: inherit; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
        }
        .donar-tab-btn:hover { border-color: var(--primary); }
        .donar-tab-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        .donar-tab-content { display: none; }
        .donar-tab-content.active { display: block; }

        /* ===== MÉTODOS ===== */
        .metodo-card { background: var(--bg-hover); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.2rem; margin-bottom: 1rem; }
        .metodo-card:last-child { margin-bottom: 0; }
        .metodo-card .metodo-head { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.9rem; padding-bottom: 0.6rem; border-bottom: 1px solid var(--border-color); }
        .metodo-card .metodo-head .metodo-icon {
            width: 38px; height: 38px; border-radius: 50%; background: var(--primary-light);
            color: var(--primary); display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0;
        }
        .metodo-card .metodo-head .metodo-icon.transfer { background: var(--danger-bg); color: var(--danger); }
        .metodo-card .metodo-head .metodo-icon.saldo { background: var(--warning-bg); color: var(--warning); }
        .metodo-card .metodo-head .metodo-icon.crypto { background: var(--success-bg); color: var(--success); }
        .metodo-card .metodo-head h3 { font-size: 1rem; margin: 0; color: var(--text-primary); }

        .donar-field { margin-bottom: 0.7rem; }
        .donar-field:last-child { margin-bottom: 0; }
        .donar-field label { display: block; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary); font-weight: 600; margin-bottom: 0.2rem; }
        .donar-field-row {
            display: flex; align-items: center; gap: 0.5rem;
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: var(--radius-md); padding: 0.5rem 0.5rem 0.5rem 0.9rem;
        }
        .donar-field-value {
            flex: 1; font-family: 'Consolas', 'Monaco', monospace;
            font-size: 0.92rem; font-weight: 600; color: var(--text-primary);
            word-break: break-all; user-select: all;
        }

        .copy-btn {
            background: var(--primary); color: white; border: none;
            width: 34px; height: 34px; border-radius: 8px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem; flex-shrink: 0; transition: var(--transition-fast); font-family: inherit;
        }
        .copy-btn:hover { background: var(--primary-hover); transform: scale(1.05); }
        .copy-btn.copiado { background: var(--success); }
        .qr-btn {
            background: var(--success); color: white; border: none;
            width: 34px; height: 34px; border-radius: 8px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem; flex-shrink: 0; transition: var(--transition-fast); font-family: inherit;
        }
        .qr-btn:hover { background: #16a34a; transform: scale(1.05); }

        /* ===== INSTRUCCIONES ===== */
        .metodo-pasos {
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: var(--radius-md); padding: 0.7rem 1rem;
            margin-top: 0.7rem; font-size: 0.82rem; color: var(--text-secondary);
        }
        .metodo-pasos ol { margin: 0.3rem 0 0 1.2rem; padding: 0; line-height: 1.7; }
        .metodo-pasos ol li { margin-bottom: 0.1rem; }

        /* ===== SERVICIOS DE RECARGA (lista con botón copiar) ===== */
        .servicios-list {
            list-style: none;
            padding: 0;
            margin: 0.5rem 0 0;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .servicio-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.6rem;
            padding: 0.55rem 0.7rem;
            background: var(--bg-hover);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            flex-wrap: wrap;
        }
        .servicio-nombre {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-weight: 600;
            font-size: 0.88rem;
            color: var(--text-primary);
            word-break: break-word;
        }
        .servicio-nombre i {
            color: var(--primary);
            font-size: 0.8rem;
        }
        .btn-copy-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            padding: 0.4rem 0.8rem;
            min-width: 120px;
            border-radius: 8px;
            background: var(--primary);
            color: white;
            border: none;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition-fast);
            font-family: inherit;
            white-space: nowrap;
        }
        .btn-copy-link:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }
        .btn-copy-link.copiado { background: var(--success); }

        /* ===== WALLETS ===== */
        .wallet-net {
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: var(--radius-md); padding: 0.8rem; margin-bottom: 0.6rem;
        }
        .wallet-net:last-child { margin-bottom: 0; }
        .wallet-net-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 0.5rem; margin-bottom: 0.5rem; flex-wrap: wrap;
        }
        .wallet-badge {
            display: inline-flex; align-items: center; gap: 0.4rem;
            font-size: 0.8rem; font-weight: 700;
            padding: 0.28rem 0.75rem; border-radius: 999px;
            border: 1px solid transparent;
        }
        .wallet-badge .coin-icon {
            width: 20px; height: 20px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.65rem;
        }
        .wallet-badge .coin-name { font-weight: 800; }
        .wallet-badge .coin-sublabel { font-weight: 500; font-size: 0.7rem; opacity: 0.8; }
        .wallet-comision { font-size: 0.72rem; color: var(--text-secondary); }
        .wallet-network-note {
            font-size: 0.72rem;
            color: var(--text-secondary);
            margin-top: 0.4rem;
            padding-top: 0.4rem;
            border-top: 1px dashed var(--border-color);
        }
        .wallet-network-note i { color: var(--primary); }

        /* ===== QR MODAL ===== */
        .wallet-qr-modal {
            position: fixed; inset: 0; background: rgba(0,0,0,0.8);
            backdrop-filter: blur(6px); z-index: 9999;
            display: flex; align-items: center; justify-content: center;
            padding: 1rem; opacity: 0; visibility: hidden; transition: all 0.25s ease;
        }
        .wallet-qr-modal.open { opacity: 1; visibility: visible; }
        .wallet-qr-inner {
            background: var(--bg-card); border-radius: var(--radius-xl);
            padding: 1.5rem; text-align: center; max-width: 340px;
            width: 100%; border: 1px solid var(--border-color);
        }
        .wallet-qr-inner canvas { display: block; margin: 0 auto; border-radius: 8px; background: white; padding: 6px; }
        .wallet-qr-inner h3 { margin-bottom: 0.8rem; font-size: 1rem; }
        .wallet-qr-inner .qr-hint { font-size: 0.78rem; color: var(--text-secondary); margin-top: 0.4rem; }
        .wallet-qr-inner p { font-size: 0.72rem; color: var(--text-secondary); margin-top: 0.6rem; word-break: break-all; font-family: monospace; }

        /* ===== COMING SOON ===== */
        .coming-soon {
            background: var(--warning-bg); border: 1px dashed var(--warning);
            border-radius: var(--radius-md); padding: 1.5rem; text-align: center; color: var(--warning);
        }
        .coming-soon i { font-size: 2rem; margin-bottom: 0.5rem; display: block; }
        .coming-soon p { font-size: 0.9rem; margin: 0; }

        /* ===== SEGURIDAD ===== */
        .seguridad-list { list-style: none; padding: 0; margin: 0; }
        .seguridad-list li {
            padding: 0.45rem 0 0.45rem 1.8rem; position: relative;
            font-size: 0.88rem; color: var(--text-secondary); line-height: 1.5;
        }
        .seguridad-list li::before {
            content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            position: absolute; left: 0; color: var(--success); font-size: 0.85rem; top: 0.5rem;
        }

        /* ===== OTRAS FORMAS ===== */
        .ayudar-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.7rem; }
        .ayudar-card { background: var(--bg-hover); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 0.9rem; text-align: center; }
        .ayudar-card i { font-size: 1.6rem; color: var(--primary); margin-bottom: 0.35rem; }
        .ayudar-card .ac-title { font-weight: 700; font-size: 0.82rem; color: var(--text-primary); }

        /* ===== GRACIAS ===== */
        .gracias-box {
            text-align: center; padding: 1.5rem 1rem;
            background: var(--success-bg); border: 1px solid var(--success);
            border-radius: var(--radius-lg); margin-bottom: 1.25rem;
        }
        .gracias-box i { font-size: 2.2rem; color: var(--success); margin-bottom: 0.4rem; }
        .gracias-box h3 { color: var(--text-primary); margin-bottom: 0.3rem; }
        .gracias-box p { color: var(--text-secondary); font-size: 0.88rem; margin: 0; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 480px) {
            .donar-hero { padding: 1.8rem 1rem; }
            .donar-hero h1 { font-size: 1.5rem; }
            .donar-hero p { font-size: 0.92rem; }
            .donar-hero .hero-icon { font-size: 2.8rem; }
            .donar-section { padding: 1.1rem; }
            .donar-section h2 { font-size: 1.1rem; }
            .donar-tab-btn { font-size: 0.82rem; padding: 0.6rem 0.7rem; min-width: 120px; }
            .metodo-card { padding: 1rem; }
            .donar-field-value { font-size: 0.8rem; }
            .servicio-item { padding: 0.5rem 0.6rem; }
            .servicio-nombre { font-size: 0.82rem; }
            .btn-copy-link { min-width: 105px; font-size: 0.68rem; padding: 0.35rem 0.6rem; }
        }
    </style>
</head>
<body>

<div class="donar-container">

    <a href="index.php" class="btn-sm btn-primary" style="padding:.5rem 1rem;display:inline-block;text-decoration:none;border-radius:8px;background:var(--primary);color:white;margin-bottom:1rem;">
        <i class="fas fa-arrow-left"></i> Volver al inicio
    </a>

    <!-- ===== HERO ===== -->
    <div class="donar-hero">
        <div class="hero-icon"><i class="fas fa-heart"></i></div>
        <h1>Apoya YSWEBAPK</h1>
        <p>Tu donación mantiene el sitio vivo, gratuito y libre de publicidad invasiva. Cada aporte, por pequeño que sea, cuenta.</p>
        <div class="hero-badges">
            <span class="hero-badge"><i class="fas fa-check-circle"></i> 100% gratis</span>
            <span class="hero-badge"><i class="fas fa-shield-alt"></i> Sin publicidad invasiva</span>
            <span class="hero-badge"><i class="fas fa-flag"></i> Hecho en Cuba 🇨🇺</span>
        </div>
    </div>

    <?php if (!empty($DONACIONES['mensaje'])): ?>
        <div class="donar-mensaje">
            <i class="fas fa-quote-left"></i> <?= escapar($DONACIONES['mensaje']) ?>
        </div>
    <?php endif; ?>

    <?php if ($DONACIONES['meta_activa'] && $DONACIONES['meta_total'] > 0): ?>
        <div class="meta-wrap">
            <div class="meta-info">
                <span><i class="fas fa-bullseye" style="color:var(--primary)"></i> Meta mensual</span>
                <span><?= (float)$DONACIONES['meta_actual'] ?> / <?= (float)$DONACIONES['meta_total'] ?> <?= escapar($DONACIONES['meta_moneda']) ?></span>
            </div>
            <div class="meta-bar">
                <div class="meta-bar-fill" style="width: <?= $meta_pct ?>%"></div>
            </div>
            <p style="font-size:0.75rem;color:var(--text-secondary);margin-top:0.4rem;text-align:center;">
                <?= $meta_pct ?>% de la meta alcanzada · ¡Gracias a todos los que apoyan!
            </p>
        </div>
    <?php endif; ?>

    <!-- ===== POR QUÉ DONAR ===== -->
    <div class="donar-section">
        <h2><i class="fas fa-question-circle"></i> ¿Por qué donar?</h2>
        <div class="porque-grid">
            <div class="porque-card">
                <div class="pc-icon"><i class="fas fa-server"></i></div>
                <div class="pc-title">Hosting y dominio</div>
                <div class="pc-desc">Mantener el sitio en línea 24/7 tiene un costo mensual</div>
            </div>
            <div class="porque-card">
                <div class="pc-icon"><i class="fas fa-shield-virus"></i></div>
                <div class="pc-title">APIs y servicios</div>
                <div class="pc-desc">VirusTotal y otros servicios de seguridad no son gratis</div>
            </div>
            <div class="porque-card">
                <div class="pc-icon"><i class="fas fa-clock"></i></div>
                <div class="pc-title">Tiempo de desarrollo</div>
                <div class="pc-desc">Horas de revisión, código y soporte constante</div>
            </div>
            <div class="porque-card">
                <div class="pc-icon"><i class="fas fa-ban"></i></div>
                <div class="pc-title">Sin publicidad</div>
                <div class="pc-desc">Preferimos pedir apoyo voluntario a llenar de anuncios</div>
            </div>
        </div>
    </div>

    <!-- ===== MÉTODOS ===== -->
    <div class="donar-section">
        <h2><i class="fas fa-hand-holding-usd"></i> Elige un método</h2>

        <div class="donar-tabs" role="tablist">
            <button class="donar-tab-btn active" data-tab="cuba" role="tab" aria-selected="true">
                <i class="fas fa-flag"></i> Desde Cuba 🇨🇺
            </button>
            <button class="donar-tab-btn" data-tab="exterior" role="tab" aria-selected="false">
                <i class="fas fa-globe-americas"></i> Desde el exterior 🌎
            </button>
        </div>

        <!-- ===== TAB CUBA ===== -->
        <div class="donar-tab-content active" id="tab-cuba" role="tabpanel">

            <!-- Transfermóvil -->
            <div class="metodo-card">
                <div class="metodo-head">
                    <div class="metodo-icon transfer"><i class="fas fa-mobile-alt"></i></div>
                    <h3>Transfermóvil</h3>
                </div>

                <div class="donar-field">
                    <label>Número de tarjeta</label>
                    <div class="donar-field-row">
                        <span class="donar-field-value"><?= escapar($tarjeta_display) ?></span>
                        <button class="copy-btn" data-copy="<?= escapar($tarjeta_copy) ?>" onclick="copiarDesdeBtn(this)" title="Copiar tarjeta" aria-label="Copiar tarjeta">
                            <i class="fas fa-copy"></i>
                        </button>
                        <button class="qr-btn" onclick="verQR('<?= escapar($tarjeta_copy) ?>', 'Tarjeta Transfermóvil', 'Escanea o deja que otro escanee para copiar el número de tarjeta')" title="Ver QR de la tarjeta" aria-label="Ver QR de la tarjeta">
                            <i class="fas fa-qrcode"></i>
                        </button>
                    </div>
                </div>

                <div class="donar-field">
                    <label>Titular</label>
                    <div class="donar-field-row">
                        <span class="donar-field-value"><?= escapar($DONACIONES['tarjeta_titular']) ?></span>
                        <button class="copy-btn" data-copy="<?= escapar($DONACIONES['tarjeta_titular']) ?>" onclick="copiarDesdeBtn(this)" title="Copiar titular" aria-label="Copiar titular">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="donar-field">
                    <label>Teléfono asociado</label>
                    <div class="donar-field-row">
                        <span class="donar-field-value"><?= escapar($tel_cuba_display) ?></span>
                        <button class="copy-btn" data-copy="<?= escapar($tel_cuba_copy) ?>" onclick="copiarDesdeBtn(this)" title="Copiar teléfono" aria-label="Copiar teléfono">
                            <i class="fas fa-copy"></i>
                        </button>
                        <button class="qr-btn" onclick="verQR('<?= escapar($tel_cuba_copy) ?>', 'Teléfono Transfermóvil', 'Escanea para copiar el número de teléfono')" title="Ver QR del teléfono" aria-label="Ver QR del teléfono">
                            <i class="fas fa-qrcode"></i>
                        </button>
                    </div>
                </div>

                <div class="metodo-pasos">
                    <strong><i class="fas fa-info-circle"></i> Cómo enviar:</strong>
                    <ol>
                        <li>Abre Transfermóvil → <strong>Operaciones</strong> → <strong>Transferencias</strong></li>
                        <li>Selecciona <strong>A la tarjeta</strong></li>
                        <li>Pega el número de tarjeta y el importe</li>
                        <li>Confirma con tu firma digital</li>
                    </ol>
                    <p style="margin-top:0.4rem;font-size:0.76rem;color:var(--text-secondary);">
                        <i class="fas fa-qrcode" style="color:var(--success)"></i>
                        Toca el botón <strong>QR</strong> para mostrar el código de la tarjeta y que otra persona lo escanee con la cámara.
                    </p>
                </div>
            </div>

            <!-- Recarga de saldo -->
            <div class="metodo-card">
                <div class="metodo-head">
                    <div class="metodo-icon saldo"><i class="fas fa-phone"></i></div>
                    <h3>Recarga de saldo</h3>
                </div>

                <div class="donar-field">
                    <label>Número de teléfono</label>
                    <div class="donar-field-row">
                        <span class="donar-field-value"><?= escapar($tel_cuba_display) ?></span>
                        <button class="copy-btn" data-copy="<?= escapar($tel_cuba_copy) ?>" onclick="copiarDesdeBtn(this)" title="Copiar teléfono" aria-label="Copiar teléfono">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="donar-field">
                    <label>Compañía</label>
                    <div class="donar-field-row">
                        <span class="donar-field-value"><?= escapar($DONACIONES['saldo_compania']) ?></span>
                    </div>
                </div>

                <div class="metodo-pasos">
                    <strong><i class="fas fa-info-circle"></i> Cómo recargar desde Cuba:</strong>
                    <ol>
                        <li>Marca <strong>*234#</strong> desde tu celular</li>
                        <li>Selecciona <strong>Transferencia de saldo</strong></li>
                        <li>Ingresa el número destino y el importe</li>
                        <li>Confirma la operación</li>
                    </ol>
                </div>
            </div>

        </div>

        <!-- ===== TAB EXTERIOR ===== -->
        <div class="donar-tab-content" id="tab-exterior" role="tabpanel">

            <!-- Trust Wallet -->
            <div class="metodo-card">
                <div class="metodo-head">
                    <div class="metodo-icon crypto"><i class="fab fa-bitcoin"></i></div>
                    <h3>Trust Wallet (Criptomonedas)</h3>
                </div>

                <?php if (!$hay_wallets): ?>
                    <div class="coming-soon">
                        <i class="fas fa-hourglass-half"></i>
                        <p>Estamos configurando las direcciones de Trust Wallet. Vuelve pronto o escríbenos a <strong>yscode03@gmail.com</strong> para coordinar una donación.</p>
                    </div>
                <?php else: ?>
                    <p style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:0.8rem;">
                        <i class="fas fa-exclamation-triangle" style="color:var(--warning)"></i>
                        <strong>Importante:</strong> envía siempre por la red indicada. Si usas una red distinta, los fondos se pierden.
                    </p>

                    <?php foreach ($DONACIONES['wallet'] as $moneda => $addr):
                        if (trim($addr) === '') continue;
                        $info = $redes_info[$moneda] ?? [
                            'label' => $moneda, 'sublabel' => '', 'network' => '',
                            'comision' => '—', 'icon' => 'fas fa-coins',
                            'color' => '#1a4b7a', 'bg' => '#dbeafe'
                        ];
                    ?>
                        <div class="wallet-net">
                            <div class="wallet-net-head">
                                <span class="wallet-badge" style="background:<?= escapar($info['bg']) ?>;color:<?= escapar($info['color']) ?>;border-color:<?= escapar($info['color']) ?>33;">
                                    <span class="coin-icon" style="background:<?= escapar($info['color']) ?>;color:white;">
                                        <i class="<?= escapar($info['icon']) ?>"></i>
                                    </span>
                                    <span class="coin-name"><?= escapar($info['label']) ?></span>
                                    <?php if (!empty($info['sublabel'])): ?>
                                        <span class="coin-sublabel">· <?= escapar($info['sublabel']) ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="wallet-comision"><i class="fas fa-gas-pump"></i> <?= escapar($info['comision']) ?></span>
                            </div>
                            <div class="donar-field-row">
                                <span class="donar-field-value"><?= escapar($addr) ?></span>
                                <button class="copy-btn" data-copy="<?= escapar($addr) ?>" onclick="copiarDesdeBtn(this)" title="Copiar dirección" aria-label="Copiar dirección">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <button class="qr-btn" onclick="verQR('<?= escapar($addr) ?>', '<?= escapar($info['label']) ?>', '<?= escapar($info['network']) ?>')" title="Ver QR" aria-label="Ver QR">
                                    <i class="fas fa-qrcode"></i>
                                </button>
                            </div>
                            <?php if (!empty($info['network'])): ?>
                                <div class="wallet-network-note">
                                    <i class="fas fa-network-wired"></i> Red: <strong><?= escapar($info['network']) ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if (trim($DONACIONES['wallet']['USDT'] ?? '') !== '' && trim($DONACIONES['wallet']['USDC'] ?? '') === trim($DONACIONES['wallet']['USDT'] ?? '')): ?>
                        <p style="font-size:0.75rem;color:var(--text-secondary);margin-top:0.5rem;padding:0.5rem 0.7rem;background:var(--bg-card);border-radius:8px;border:1px solid var(--border-color);">
                            <i class="fas fa-info-circle" style="color:var(--primary)"></i>
                            USDT y USDC comparten la misma dirección (es una wallet EVM estándar). Puedes enviar cualquiera de las dos a la misma dirección siempre que uses BEP20 o ERC20.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Recarga internacional (solo copiar enlace) -->
            <div class="metodo-card">
                <div class="metodo-head">
                    <div class="metodo-icon saldo"><i class="fas fa-globe"></i></div>
                    <h3>Recarga de saldo (internacional)</h3>
                </div>

                <div class="donar-field">
                    <label>Número cubano a recargar</label>
                    <div class="donar-field-row">
                        <span class="donar-field-value"><?= escapar($tel_intl_display) ?></span>
                        <button class="copy-btn" data-copy="<?= escapar($tel_intl_copy) ?>" onclick="copiarDesdeBtn(this)" title="Copiar teléfono" aria-label="Copiar teléfono">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="metodo-pasos">
                    <strong><i class="fas fa-info-circle"></i> Servicios que aceptan tarjetas internacionales:</strong>
                    <p style="font-size:0.78rem;color:var(--text-secondary);margin:0.35rem 0 0.5rem;">
                        Copia el enlace del servicio que prefieras y pégalo en tu navegador para abrirlo.
                    </p>
                    <ul class="servicios-list">
                        <?php foreach ($DONACIONES['servicios_recarga'] as $serv): ?>
                            <li class="servicio-item">
                                <span class="servicio-nombre">
                                    <i class="fas fa-globe"></i> <?= escapar($serv['nombre']) ?>
                                </span>
                                <button class="btn-copy-link" data-copy="<?= escapar($serv['url']) ?>" onclick="copiarDesdeBtn(this)" title="Copiar enlace de <?= escapar($serv['nombre']) ?>" aria-label="Copiar enlace de <?= escapar($serv['nombre']) ?>">
                                    <i class="fas fa-copy"></i> Copiar enlace
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

        </div>

    </div>

    <!-- ===== SEGURIDAD ===== -->
    <div class="donar-section">
        <h2><i class="fas fa-shield-alt"></i> Seguridad y transparencia</h2>
        <ul class="seguridad-list">
            <li>Verifica siempre la dirección o el número antes de enviar — una vez enviado no se puede revertir.</li>
            <li>Las donaciones son <strong>100% voluntarias</strong>. Nunca pedimos dinero obligatorio.</li>
            <li>No ofrecemos recompensas, promociones ni privilegios a cambio de donaciones.</li>
            <li>No publicamos información de quién dona ni cuánto — tu privacidad es sagrada.</li>
            <li>Nunca te pediremos contraseñas, códigos de verificación ni datos de tu tarjeta.</li>
            <li>Si recibes mensajes sospechosos pidiéndote donar a otros datos, repórtalos a <strong>yscode03@gmail.com</strong>.</li>
        </ul>
    </div>

    <!-- ===== OTRAS FORMAS DE AYUDAR ===== -->
    <div class="donar-section">
        <h2><i class="fas fa-hands-helping"></i> Otras formas de ayudar (gratis)</h2>
        <p style="font-size:0.88rem;color:var(--text-secondary);margin-bottom:0.8rem;">
            Si no puedes donar, hay muchas maneras de apoyar el proyecto sin gastar dinero:
        </p>
        <div class="ayudar-grid">
            <div class="ayudar-card"><i class="fas fa-share-alt"></i><div class="ac-title">Comparte la web</div></div>
            <div class="ayudar-card"><i class="fas fa-star"></i><div class="ac-title">Deja una valoración</div></div>
            <div class="ayudar-card"><i class="fas fa-upload"></i><div class="ac-title">Sube tu APK</div></div>
            <div class="ayudar-card"><i class="fas fa-bug"></i><div class="ac-title">Reporta bugs</div></div>
            <div class="ayudar-card"><i class="fas fa-comments"></i><div class="ac-title">Comenta y participa</div></div>
            <div class="ayudar-card"><i class="fas fa-user-friends"></i><div class="ac-title">Recomienda a amigos</div></div>
        </div>
    </div>

    <!-- ===== GRACIAS ===== -->
    <div class="gracias-box">
        <i class="fas fa-heart"></i>
        <h3>¡Gracias por apoyar el proyecto!</h3>
        <p>Cada gesto, donación o simple "me gusta" mantiene YSWEBAPK en pie.</p>
        <p style="margin-top:0.5rem;">
            <i class="fas fa-envelope"></i> Contacto: <a href="mailto:yscode03@gmail.com" style="color:var(--primary);font-weight:700;">yscode03@gmail.com</a>
        </p>
    </div>

</div>

<!-- Modal QR -->
<div class="wallet-qr-modal" id="walletQrModal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="wallet-qr-inner">
        <h3 id="qrModalTitle">Escanear QR</h3>
        <canvas id="qrCanvas" width="220" height="220"></canvas>
        <p class="qr-hint" id="qrModalHint"></p>
        <p id="qrModalAddress"></p>
        <button onclick="document.getElementById('walletQrModal').classList.remove('open')" class="btn-sm btn-primary" style="margin-top:0.8rem;padding:0.5rem 1.2rem;">
            <i class="fas fa-times"></i> Cerrar
        </button>
    </div>
</div>

<!-- Librería QR -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>

<script>
/* ===== TABS ===== */
function switchTab(tab) {
    document.querySelectorAll('.donar-tab-btn').forEach(b => {
        const on = b.dataset.tab === tab;
        b.classList.toggle('active', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    document.querySelectorAll('.donar-tab-content').forEach(c => {
        c.classList.toggle('active', c.id === 'tab-' + tab);
    });
    try { localStorage.setItem('yswebapk_donar_tab', tab); } catch(e) {}
}
document.querySelectorAll('.donar-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => switchTab(btn.dataset.tab));
});
(function restoreTab() {
    let tab = 'cuba';
    try { tab = localStorage.getItem('yswebapk_donar_tab') || 'cuba'; } catch(e) {}
    switchTab(tab);
})();

/* ===== COPIAR ===== */
async function copiar(texto, btn) {
    const original = btn.innerHTML;
    try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(texto);
        } else {
            const ta = document.createElement('textarea');
            ta.value = texto;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
        btn.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
        btn.classList.add('copiado');
        setTimeout(() => {
            btn.innerHTML = original;
            btn.classList.remove('copiado');
        }, 1800);
    } catch (e) {
        btn.innerHTML = '<i class="fas fa-times"></i> Error';
        setTimeout(() => btn.innerHTML = original, 1800);
    }
}
function copiarDesdeBtn(btn) {
    copiar(btn.dataset.copy, btn);
}

/* ===== QR ===== */
async function verQR(texto, titulo, hint) {
    const modal  = document.getElementById('walletQrModal');
    const canvas = document.getElementById('qrCanvas');
    const title  = document.getElementById('qrModalTitle');
    const hintEl = document.getElementById('qrModalHint');
    const addr   = document.getElementById('qrModalAddress');

    title.textContent = 'QR · ' + titulo;
    hintEl.textContent = hint || '';
    addr.textContent = texto;
    modal.classList.add('open');

    if (typeof QRCode === 'undefined') {
        addr.textContent = '⚠️ No se pudo cargar el generador de QR. Copia el dato manualmente.';
        return;
    }
    try {
        await QRCode.toCanvas(canvas, texto, {
            width: 220,
            margin: 1,
            color: { dark: '#000000', light: '#ffffff' }
        });
    } catch (err) {
        addr.textContent = 'Error al generar QR: ' + err.message;
    }
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.getElementById('walletQrModal').classList.remove('open');
});
</script>

</body>
</html>