<?php require_once 'config/database.php'; require_once 'config/funciones.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php renderMetaSEO([
    'title'       => 'Términos y Condiciones - YSWEBAPK',
    'description' => 'Términos y condiciones de uso de YSWEBAPK. Conoce tus derechos y obligaciones al usar nuestra plataforma de APKs.',
]); ?>
<link rel="icon" href="Ico.jpeg">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div style="max-width: 800px; margin: 2rem auto; padding: 0 1rem;">
        <a href="index.php" class="btn-primary" style="text-decoration:none; margin-bottom: 1rem; display:inline-block;">&larr; Volver al inicio</a>
        <div class="admin-section" style="padding: 2rem;">
            <h1>Términos y Condiciones de Uso</h1>
            <p><strong>Última actualización:</strong> <?= date('d/m/Y') ?></p>
            
            <h2>1. Aceptación de los términos</h2>
            <p>Al acceder a YSWEBAPK, aceptas cumplir con estos términos. Si no estás de acuerdo, por favor no utilices el sitio.</p>

            <h2>2. Rol de la plataforma</h2>
            <p>YSWEBAPK es un repositorio donde desarrolladores independientes suben sus aplicaciones Android (APKs). <strong>No creamos ni poseemos los APKs</strong>; solo actuamos como intermediarios para facilitar su descarga.</p>

            <h2>3. Responsabilidad del Desarrollador</h2>
            <p>Si subes una APK, declaras que:</p>
            <ul>
                <li>Eres el creador original o tienes los derechos legales para distribuirla.</li>
                <li>La aplicación no contiene malware, spyware ni código malicioso.</li>
                <li>La aplicación no promueve el odio, la violencia o actividades ilegales.</li>
            </ul>

            <h2>4. Propiedad Intelectual (DMCA)</h2>
            <p>Respetamos los derechos de autor. Si eres dueño de una aplicación y crees que se ha subido sin tu permiso, envía un correo a <strong>yscode03@gmail.com</strong> con las pruebas de propiedad y eliminaremos el archivo en un plazo de 48 horas.</p>

            <h2>5. Uso Aceptable</h2>
            <p>Queda prohibido intentar hackear, saturar (DDoS) o explotar vulnerabilidades del sitio web. Cualquier cuenta que intente comprometer la seguridad será eliminada permanentemente.</p>

            <h2>6. Descargas y Seguridad</h2>
            <p>Aunque revisamos las aplicaciones, <strong>descargas los APKs bajo tu propio riesgo</strong>. YSWEBAPK no se hace responsable de daños a tu dispositivo por instalar software de terceros.</p>
        </div>
    </div>
</body>
</html>