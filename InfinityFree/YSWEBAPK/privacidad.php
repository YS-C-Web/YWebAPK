<?php require_once 'config/database.php'; require_once 'config/funciones.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php renderMetaSEO([
    'title'       => 'Política de Privacidad - YSWEBAPK',
    'description' => 'Política de privacidad de YSWEBAPK. Cómo recopilamos, usamos y protegemos tus datos personales.',
]); ?>
<link rel="icon" href="Ico.jpeg">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div style="max-width: 800px; margin: 2rem auto; padding: 0 1rem;">
        <a href="index.php" class="btn-primary" style="text-decoration:none; margin-bottom: 1rem; display:inline-block;">&larr; Volver al inicio</a>
        <div class="admin-section" style="padding: 2rem;">
            <h1>Política de Privacidad</h1>
            <p><strong>Última actualización:</strong> <?= date('d/m/Y') ?></p>
            
            <h2>1. Información que recopilamos</h2>
            <p>En YSWEBAPK, recopilamos la siguiente información cuando te registras o interactúas con la plataforma:</p>
            <ul>
                <li>Nombre y dirección de correo electrónico (para crear tu cuenta).</li>
                <li>Contraseña (encriptada con algoritmos de seguridad, nunca la vemos en texto plano).</li>
                <li>Dirección IP y datos de navegación (para prevenir fraudes y limitar la tasa de peticiones).</li>
                <li>Archivos APK, imágenes y descripciones que los desarrolladores suben voluntariamente.</li>
            </ul>

            <h2>2. Uso de la información</h2>
            <p>Utilizamos tus datos para:</p>
            <ul>
                <li>Gestionar tu cuenta y permitirte iniciar sesión.</li>
                <li>Notificarte sobre el estado de tus APKs si eres desarrollador.</li>
                <li>Mejorar la seguridad de la plataforma y prevenir ataques.</li>
                <li>Analizar qué aplicaciones son las más descargadas para mostrar estadísticas públicas.</li>
            </ul>

            <h2>3. Cookies</h2>
            <p>Usamos cookies para mantener tu sesión activa y guardar tus preferencias (como el tema claro/oscuro). No usamos cookies de rastreo de terceros para publicidad invasiva.</p>

            <h2>4. Compartir datos con terceros</h2>
            <p>No vendemos ni alquilamos tu información personal. Sin embargo, cuando un desarrollador sube un APK, este es analizado opcionalmente por la API de VirusTotal para garantizar su seguridad.</p>

            <h2>5. Tus derechos</h2>
            <p>Puedes solicitar la eliminación de tu cuenta y todos tus datos asociados en cualquier momento contactando al administrador en <strong>yscode03@gmail.com</strong>.</p>
        </div>
    </div>
</body>
</html>