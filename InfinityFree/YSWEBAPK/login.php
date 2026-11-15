<?php
require_once 'config/database.php';
require_once 'config/funciones.php';

$error = '';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Sesión expirada. Recarga la página.';
    } else {
        // ============ LOGIN ============
        if ($_POST['action'] === 'login') {
            if (!verificarRateLimit('login', 10, 15)) {
                $error = 'Demasiados intentos. Espera 15 minutos.';
            } else {
                $email = limpiarEntrada($_POST['email']);
                $password = $_POST['password'] ?? '';

                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
                $stmt->execute([$email]);
                $usuario = $stmt->fetch();

                if ($usuario && password_verify($password, $usuario['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['nombre'] = $usuario['nombre'];
                    $_SESSION['rol'] = $usuario['rol'];
                    $_SESSION['email'] = $usuario['email'];
                    $_SESSION['foto_perfil'] = $usuario['foto_perfil'];
                    $_SESSION['_created'] = time();
                    header('Location: index.php');
                    exit;
                }
                $error = 'Email o contraseña incorrectos';
            }
        }

        // ============ REGISTRO ============
        if ($_POST['action'] === 'registro') {
            if (!verificarRateLimit('registro', 5, 60)) {
                $error = 'Demasiados registros. Intenta más tarde.';
            } else {
                $email = limpiarEntrada($_POST['email']);
                $nombre = limpiarEntrada($_POST['nombre']);
                $password = $_POST['password'] ?? '';
                $password_confirm = $_POST['password_confirm'] ?? '';

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Email inválido';
                } elseif (mb_strlen($nombre) < 2) {
                    $error = 'El nombre debe tener al menos 2 caracteres';
                } elseif ($password !== $password_confirm) {
                    $error = 'Las contraseñas no coinciden';
                } elseif (strlen($password) < 6) {
                    $error = 'La contraseña debe tener al menos 6 caracteres';
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $error = 'Este email ya está registrado';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO usuarios (email, nombre, password_hash) VALUES (?, ?, ?)");
                        if ($stmt->execute([$email, $nombre, $hash])) {
                            $mensaje = 'Registro exitoso. Ahora puedes iniciar sesión.';
                        } else {
                            $error = 'Error al registrar usuario';
                        }
                    }
                }
            }
        }

        // ============ RECUPERACIÓN ============
        if ($_POST['action'] === 'recuperar') {
            if (!verificarRateLimit('recuperar', 3, 60)) {
                $error = 'Demasiadas solicitudes. Intenta más tarde.';
            } else {
                $email = limpiarEntrada($_POST['email_recuperar']);

                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmt->execute([$email]);
                $usuario = $stmt->fetch();

                if ($usuario) {
                    $stmt = $pdo->prepare("SELECT id FROM recuperaciones WHERE usuario_id = ? AND estado = 'pendiente'");
                    $stmt->execute([$usuario['id']]);
                    if ($stmt->fetch()) {
                        $mensaje = 'Ya tienes una solicitud pendiente. El admin la procesará pronto.';
                    } else {
                        // Contraseña temporal legible (sin caracteres confusos)
                        $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                        $nueva = '';
                        for ($i = 0; $i < 10; $i++) {
                            $nueva .= $chars[random_int(0, strlen($chars) - 1)];
                        }
                        $hash = password_hash($nueva, PASSWORD_DEFAULT);
                        $token = generarToken();

                        $stmt = $pdo->prepare("INSERT INTO recuperaciones (usuario_id, token, nueva_contraseña, password_temporal) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$usuario['id'], $token, $hash, $nueva]);
                        $mensaje = 'Solicitud enviada. El administrador te contactará con tu nueva contraseña.';
                    }
                } else {
                    // No revelar si existe o no (seguridad)
                    $mensaje = 'Si el email existe en el sistema, recibirás tu nueva contraseña pronto.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php renderMetaSEO([
    'title'       => 'Iniciar Sesión - YSWEBAPK',
    'description' => 'Accede a tu cuenta de YSWEBAPK para descargar APKs, valorar aplicaciones y comentar. Regístrate gratis.',
]); ?>
<link rel="icon" href="Ico.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-container { max-width: 480px; margin: 2rem auto; padding: 2rem; background: var(--bg-card); border-radius: 24px; box-shadow: var(--shadow-lg); border: 1px solid var(--border-color); }
        .login-container h1 { text-align: center; margin-bottom: 1.5rem; }
        .login-container .form-group { margin-bottom: 1rem; }
        .login-container label { display: block; font-weight: 500; margin-bottom: 0.3rem; color: var(--text-primary); }
        .login-container input { width: 100%; padding: 0.7rem 1rem; border: 1px solid var(--border-color); border-radius: 12px; font-size: 0.95rem; background: var(--bg-input); color: var(--text-primary); }
        .login-container input:focus { border-color: var(--border-focus); outline: none; }
        .login-container .btn-primary { width: 100%; padding: 0.8rem; background: var(--primary); color: white; border: none; border-radius: 12px; font-weight: 600; font-size: 1rem; cursor: pointer; margin-top: 0.5rem; }
        .login-container .btn-primary:hover { background: var(--primary-hover); }
        .login-container .switch-form { text-align: center; margin-top: 1rem; color: var(--text-secondary); }
        .login-container .switch-form a { color: var(--primary); cursor: pointer; font-weight: 500; }
        .login-container .error { color: var(--danger); background: var(--danger-bg); padding: 0.7rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid var(--danger); }
        .login-container .mensaje { color: var(--success); background: var(--success-bg); padding: 0.7rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid var(--success); }
        .login-container .recuperar-link { text-align: right; margin-top: -0.3rem; margin-bottom: 0.5rem; }
        .login-container .recuperar-link a { color: var(--text-secondary); font-size: 0.85rem; cursor: pointer; }
        .login-container .recuperar-form { display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color); }
        .login-container .recuperar-form.visible { display: block; }
        .login-container .info-recuperacion { margin-top: 1rem; padding: 0.8rem; background: var(--warning-bg); border-radius: 8px; font-size: 0.85rem; color: var(--warning); border-left: 4px solid var(--warning); }
        @media (max-width: 480px) { .login-container { margin: 1rem; padding: 1.5rem; } }
    </style>
</head>
<body>

<div class="login-container">
    <h1><i class="fas fa-cubes" style="color:var(--primary);"></i> YSWEBAPK</h1>

    <?php if ($error): ?><div class="error"><?= escapar($error) ?></div><?php endif; ?>
    <?php if ($mensaje): ?><div class="mensaje"><?= escapar($mensaje) ?></div><?php endif; ?>

    <div id="loginForm">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="login">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required autocomplete="email">
            </div>
            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="password" required autocomplete="current-password">
            </div>
            <div class="recuperar-link">
                <a onclick="mostrarRecuperar()">¿Olvidaste tu contraseña?</a>
            </div>
            <button type="submit" class="btn-primary">Iniciar Sesión</button>
        </form>
        <div class="switch-form">
            ¿No tienes cuenta? <a onclick="mostrarRegistro()">Regístrate</a>
        </div>
    </div>

    <div id="registroForm" style="display:none;">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="registro">
            <div class="form-group"><label>Nombre</label><input type="text" name="nombre" required minlength="2" autocomplete="nickname"></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" required autocomplete="email"></div>
            <div class="form-group"><label>Contraseña</label><input type="password" name="password" required minlength="6" autocomplete="new-password"></div>
            <div class="form-group"><label>Confirmar Contraseña</label><input type="password" name="password_confirm" required minlength="6" autocomplete="new-password"></div>
            <button type="submit" class="btn-primary">Registrarse</button>
        </form>
        <div class="switch-form">¿Ya tienes cuenta? <a onclick="mostrarLogin()">Iniciar Sesión</a></div>
    </div>

    <div id="recuperarForm" class="recuperar-form">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="recuperar">
            <div class="form-group"><label>Email</label><input type="email" name="email_recuperar" required autocomplete="email"></div>
            <button type="submit" class="btn-primary">Solicitar Recuperación</button>
        </form>
        <div class="switch-form" style="margin-top:0.5rem;"><a onclick="mostrarLogin()">Volver a iniciar sesión</a></div>
        <div class="info-recuperacion">
            <i class="fas fa-clock"></i> Un administrador revisará tu solicitud y te enviará una contraseña temporal al correo.
        </div>
    </div>
</div>

<script>
function mostrarRegistro() {
    document.getElementById('loginForm').style.display = 'none';
    document.getElementById('registroForm').style.display = 'block';
    document.getElementById('recuperarForm').classList.remove('visible');
}
function mostrarLogin() {
    document.getElementById('loginForm').style.display = 'block';
    document.getElementById('registroForm').style.display = 'none';
    document.getElementById('recuperarForm').classList.remove('visible');
}
function mostrarRecuperar() {
    document.getElementById('loginForm').style.display = 'none';
    document.getElementById('registroForm').style.display = 'none';
    document.getElementById('recuperarForm').classList.add('visible');
}
</script>

</body>
</html>