<?php
require_once 'config/database.php';
require_once 'config/funciones.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?php renderMetaSEO([
        'title'       => 'Cómo instalar APKs en Android - Guía paso a paso | YSWEBAPK',
        'description' => 'Guía completa para instalar APKs en Android. Aprende a activar orígenes desconocidos, instalar archivos APK y solucionar errores. Compatible con Android 8 al 14.',
        'keywords'    => 'instalar APK, cómo instalar APK, orígenes desconocidos Android, instalar app fuera de Play Store',
    ]); ?>
    <link rel="icon" href="Ico.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "HowTo",
      "name": "Cómo instalar un APK en Android",
      "description": "Guía paso a paso para instalar archivos APK en dispositivos Android.",
      "totalTime": "PT3M",
      "step": [
        {"@type":"HowToStep","name":"Descargar el APK","text":"Descarga el archivo APK desde YSWEBAPK."},
        {"@type":"HowToStep","name":"Permitir orígenes desconocidos","text":"Activa la opción de instalar apps de orígenes desconocidos en los ajustes de tu Android."},
        {"@type":"HowToStep","name":"Abrir el archivo APK","text":"Abre el archivo desde tu gestor de archivos o desde las notificaciones."},
        {"@type":"HowToStep","name":"Instalar","text":"Pulsa Instalar y espera a que termine el proceso."}
      ]
    }
    </script>
    <style>
        .guia-container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .guia-hero { text-align: center; padding: 2rem 1rem; background: linear-gradient(135deg, var(--primary-light), var(--bg-card)); border-radius: var(--radius-xl); border: 1px solid var(--border-color); margin-bottom: 1.5rem; }
        .guia-hero i { font-size: 3rem; color: var(--primary); margin-bottom: 0.5rem; }
        .guia-hero h1 { font-size: 1.8rem; margin-bottom: 0.5rem; }
        .guia-hero p { color: var(--text-secondary); max-width: 560px; margin: 0 auto; }
        .guia-section { background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); padding: 1.5rem; margin-bottom: 1.25rem; box-shadow: var(--shadow-sm); }
        .guia-section h2 { font-size: 1.3rem; margin-bottom: 0.8rem; display: flex; align-items: center; gap: 0.5rem; }
        .guia-section h2 .num { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; font-size: 0.95rem; font-weight: 700; flex-shrink: 0; }
        .guia-section h3 { font-size: 1rem; margin: 1rem 0 0.4rem; color: var(--primary); }
        .guia-section p, .guia-section li { font-size: 0.92rem; color: var(--text-secondary); line-height: 1.65; }
        .guia-section ol, .guia-section ul { padding-left: 1.4rem; margin: 0.4rem 0; }
        .guia-section ol li, .guia-section ul li { margin-bottom: 0.3rem; }
        .paso { background: var(--bg-hover); border-left: 4px solid var(--primary); padding: 0.7rem 1rem; border-radius: var(--radius-md); margin: 0.5rem 0; }
        .paso strong { color: var(--text-primary); }
        .warning-box { background: var(--warning-bg); border: 1px solid var(--warning); padding: 0.8rem 1rem; border-radius: var(--radius-md); margin: 0.8rem 0; color: var(--warning); font-size: 0.88rem; }
        .info-box { background: var(--primary-light); border: 1px solid var(--primary); padding: 0.8rem 1rem; border-radius: var(--radius-md); margin: 0.8rem 0; color: var(--text-primary); font-size: 0.88rem; }
        .danger-box { background: var(--danger-bg); border: 1px solid var(--danger); padding: 0.8rem 1rem; border-radius: var(--radius-md); margin: 0.8rem 0; color: var(--danger); font-size: 0.88rem; }
        .faq-item { background: var(--bg-hover); border-radius: var(--radius-md); padding: 0.8rem 1rem; margin-bottom: 0.6rem; border: 1px solid var(--border-color); }
        .faq-item summary { cursor: pointer; font-weight: 700; font-size: 0.92rem; color: var(--text-primary); list-style: none; display: flex; align-items: center; gap: 0.5rem; }
        .faq-item summary::-webkit-details-marker { display: none; }
        .faq-item summary::after { content: '+'; margin-left: auto; color: var(--primary); font-size: 1.2rem; }
        .faq-item[open] summary::after { content: '−'; }
        .faq-item p { margin-top: 0.5rem; font-size: 0.88rem; }
        .cta-box { text-align: center; padding: 1.5rem; background: var(--success-bg); border: 1px solid var(--success); border-radius: var(--radius-lg); margin-top: 1.5rem; }
        .cta-box a { display: inline-block; padding: 0.7rem 1.6rem; background: var(--primary); color: white; text-decoration: none; border-radius: var(--radius-md); font-weight: 700; margin-top: 0.5rem; }
        @media (max-width: 480px) {
            .guia-hero h1 { font-size: 1.4rem; }
            .guia-section { padding: 1.1rem; }
            .guia-section h2 { font-size: 1.1rem; }
        }
    </style>
</head>
<body>
<div class="guia-container">

    <a href="index.php" class="btn-sm btn-primary" style="padding:.5rem 1rem;display:inline-block;text-decoration:none;border-radius:8px;background:var(--primary);color:white;margin-bottom:1rem;">
        <i class="fas fa-arrow-left"></i> Volver al inicio
    </a>

    <div class="guia-hero">
        <i class="fas fa-mobile-alt"></i>
        <h1>Cómo instalar un APK en Android</h1>
        <p>Guía paso a paso actualizada para Android 8 al 14. Aprende a instalar cualquier APK descargada de YSWEBAPK de forma segura.</p>
    </div>

    <div class="guia-section">
        <h2><i class="fas fa-question-circle"></i> ¿Qué es un APK?</h2>
        <p>Un <strong>APK</strong> (Android Package Kit) es el formato de archivo que utiliza Android para distribuir e instalar aplicaciones. Es el equivalente a un <code>.exe</code> en Windows o un <code>.dmg</code> en macOS.</p>
        <p>Cuando descargas una app desde Google Play Store, tu teléfono descarga un APK y lo instala automáticamente. Al descargar directamente de YSWEBAPK, haces el proceso manualmente, lo que te permite:</p>
        <ul>
            <li>Instalar apps que <strong>no están en Play Store</strong> (por región o por política)</li>
            <li>Probar <strong>versiones anteriores</strong> de una app</li>
            <li>Instalar <strong>mods o versiones premium</strong></li>
            <li>Evitar las <strong>restricciones de Google</strong></li>
        </ul>
    </div>

    <div class="guia-section">
        <h2><span class="num">1</span> Activar orígenes desconocidos</h2>
        <p>Android <strong>bloquea por defecto</strong> la instalación de apps fuera de Play Store por seguridad. Antes de instalar un APK, debes permitirlo.</p>

        <div class="info-box">
            <i class="fas fa-info-circle"></i> La forma de activarlo <strong>cambia según la versión de Android</strong>. Sigue la que corresponda a tu dispositivo.
        </div>

        <h3>📱 Android 12, 13 y 14</h3>
        <div class="paso">
            <strong>1.</strong> Abre <strong>Ajustes</strong> → <strong>Apps</strong> → <strong>Acceso especial a apps</strong><br>
            <strong>2.</strong> Toca <strong>Instalar apps desconocidas</strong><br>
            <strong>3.</strong> Selecciona la app con la que vas a abrir el APK (Chrome, Gestor de Archivos, etc.)<br>
            <strong>4.</strong> Activa <strong>Permitir desde esta fuente</strong>
        </div>

        <h3>📱 Android 9, 10 y 11</h3>
        <div class="paso">
            <strong>1.</strong> Abre <strong>Ajustes</strong> → <strong>Apps y notificaciones</strong> → <strong>Acceso especial a apps</strong><br>
            <strong>2.</strong> Toca <strong>Instalar apps desconocidas</strong><br>
            <strong>3.</strong> Elige la app de origen (Chrome, Gestor, etc.)<br>
            <strong>4.</strong> Activa el permiso
        </div>

        <h3>📱 Android 8.0 y 8.1</h3>
        <div class="paso">
            <strong>1.</strong> Abre <strong>Ajustes</strong> → <strong>Apps y notificaciones</strong><br>
            <strong>2.</strong> Toca el ícono de <strong>3 puntos</strong> arriba a la derecha → <strong>Acceso especial a apps</strong><br>
            <strong>3.</strong> Toca <strong>Instalar apps desconocidas</strong><br>
            <strong>4.</strong> Selecciona la app y activa el permiso
        </div>
    </div>

    <div class="guia-section">
        <h2><span class="num">2</span> Descargar el APK</h2>
        <div class="paso">
            <strong>1.</strong> Ve a la <a href="index.php" style="color:var(--primary);font-weight:700;">página principal de YSWEBAPK</a><br>
            <strong>2.</strong> Busca la app o navega por categorías<br>
            <strong>3.</strong> Toca la tarjeta de la app para ver los detalles<br>
            <strong>4.</strong> Pulsa el botón <strong>Descargar APK</strong>
        </div>
        <div class="info-box">
            <i class="fas fa-shield-alt"></i> <strong>Todas las APKs</strong> pasan por revisión manual y escaneo de VirusTotal antes de publicarse.
        </div>
    </div>

    <div class="guia-section">
        <h2><span class="num">3</span> Instalar el APK</h2>
        <div class="paso">
            <strong>1.</strong> Abre el <strong>Gestor de Archivos</strong> de tu Android<br>
            <strong>2.</strong> Ve a la carpeta <strong>Descargas</strong><br>
            <strong>3.</strong> Toca el archivo <code>.apk</code> que acabas de descargar<br>
            <strong>4.</strong> Pulsa <strong>Instalar</strong><br>
            <strong>5.</strong> Espera a que termine y pulsa <strong>Abrir</strong>
        </div>
    </div>

    <div class="guia-section">
        <h2><span class="num">4</span> Solucionar errores comunes</h2>

        <details class="faq-item">
            <summary>"Análisis del paquete: error"</summary>
            <p>El APK está corrupto o no es compatible con tu versión de Android. Vuelve a descargarlo. Si persiste, la app requiere una versión más reciente de Android.</p>
        </details>

        <details class="faq-item">
            <summary>"App no instalada"</summary>
            <p>Puede ser porque: (1) ya tienes una versión diferente instalada — desinstala la anterior primero, o (2) el APK no es compatible con la arquitectura de tu procesador (ARM, ARM64, x86).</p>
        </details>

        <details class="faq-item">
            <summary>"Bloqueado por Play Protect"</summary>
            <p>Google Play Protect a veces bloquea APKs de orígenes externos. Puedes desactivarlo temporalmente en <strong>Play Store → Menú → Play Protect → Configuración</strong>.</p>
        </details>

        <details class="faq-item">
            <summary>"No se puede instalar: firma incorrecta"</summary>
            <p>La app tiene una firma digital diferente a la versión anterior instalada. Desinstala la versión antigua primero (perderás los datos locales de esa app).</p>
        </details>

        <details class="faq-item">
            <summary>El APK es "más grande de lo permitido"</summary>
            <p>Algunas ROMs modificadas tienen límites. Puedes usar un gestor de archivos root para moverlo a <code>/system</code>.</p>
        </details>
    </div>

    <div class="guia-section">
        <h2><i class="fas fa-shield-alt"></i> Consejos de seguridad</h2>
        <div class="danger-box">
            <i class="fas fa-exclamation-triangle"></i> <strong>Solo descarga APKs de fuentes confiables</strong> como YSWEBAPK. Los APKs de sitios desconocidos pueden contener malware.
        </div>
        <ul>
            <li><strong>Verifica los permisos</strong>: una app de linterna no debería pedir acceso a contactos</li>
            <li><strong>Mantén Play Protect activo</strong> después de instalar</li>
            <li><strong>Lee las reseñas</strong> y valoraciones de otros usuarios antes de instalar</li>
            <li>Si una app se comporta raro (publicidad excesiva, drenaje de batería), desinstálala</li>
            <li>Reporta apps sospechosas desde la ficha de la app</li>
        </ul>
    </div>

    <div class="guia-section">
        <h2><i class="fas fa-question-circle"></i> Preguntas frecuentes</h2>

        <details class="faq-item">
            <summary>¿Instalar APKs es legal?</summary>
            <p>Sí, es legal instalar APKs de apps que distribuyen legalmente sus creadores. Lo que NO es legal es distribuir apps de pago sin permiso del autor.</p>
        </details>

        <details class="faq-item">
            <summary>¿Necesito root para instalar APKs?</summary>
            <p>No. Los APKs normales se instalan como cualquier otra app. Solo los mods con permisos especiales (como apps del sistema) requieren root.</p>
        </details>

        <details class="faq-item">
            <summary>¿Las APKs de YSWEBAPK son seguras?</summary>
            <p>Todas pasan por revisión manual + escaneo de VirusTotal antes de publicarse. Aun así, recomendamos siempre verificar permisos y leer comentarios.</p>
        </details>

        <details class="faq-item">
            <summary>¿Puedo volver a Play Store después?</summary>
            <p>Sí, sin problema. Los APKs instalados manualmente funcionan igual. Si la misma app está en Play Store, la actualización te la ofrecerá Play Store si la firma coincide.</p>
        </details>
    </div>

    <div class="cta-box">
        <i class="fas fa-download" style="font-size:2rem;color:var(--success);margin-bottom:0.4rem;"></i>
        <h3>¿Listo para empezar?</h3>
        <p>Explora nuestro catálogo de APKs verificadas</p>
        <a href="index.php"><i class="fas fa-th-large"></i> Ver todas las APKs</a>
    </div>

</div>
</body>
</html>