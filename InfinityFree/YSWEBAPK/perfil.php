<?php
require_once 'config/database.php';
require_once 'config/funciones.php';

if (!estaLogueado()) { header('Location: login.php'); exit; }

$usuario = obtenerUsuarioActual();
$error = '';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Token inválido. Recarga la página.';
    } else {
        $action = $_POST['action'] ?? '';

        // Actualizar perfil
        if ($action === 'actualizar_perfil') {
            $nombre = limpiarEntrada($_POST['nombre'] ?? '');
            $email = limpiarEntrada($_POST['email'] ?? '');

            if (mb_strlen($nombre) < 2) $error = 'Nombre demasiado corto';
            elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Email inválido';
            else {
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                $stmt->execute([$email, $usuario['id']]);
                if ($stmt->fetch()) $error = 'Este email ya está en uso';
                else {
                    $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ? WHERE id = ?")
                        ->execute([$nombre, $email, $usuario['id']]);
                    $_SESSION['nombre'] = $nombre;
                    $_SESSION['email'] = $email;
                    $mensaje = 'Perfil actualizado';
                    $usuario = obtenerUsuarioActual();
                }
            }
        }

        // Cambiar contraseña
        if ($action === 'cambiar_password') {
            $actual = $_POST['password_actual'] ?? '';
            $nueva  = $_POST['password_nueva'] ?? '';
            $conf   = $_POST['password_confirm'] ?? '';

            if (!password_verify($actual, $usuario['password_hash'])) $error = 'Contraseña actual incorrecta';
            elseif ($nueva !== $conf) $error = 'Las nuevas contraseñas no coinciden';
            elseif (strlen($nueva) < 6) $error = 'Mínimo 6 caracteres';
            else {
                $hash = password_hash($nueva, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?")->execute([$hash, $usuario['id']]);
                $mensaje = 'Contraseña actualizada';
            }
        }

        // Subir foto
        if ($action === 'subir_foto') {
            $check = validarArchivoSubido($_FILES['foto_perfil'] ?? null, 'imagen', 5);
            if (!$check['ok']) {
                $error = $check['error'];
            } else {
                $dir = 'uploads/perfiles/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);

                if ($usuario['foto_perfil'] && $usuario['foto_perfil'] !== 'default.png'
                    && $usuario['foto_perfil'] !== 'assets/default-avatar.png'
                    && file_exists($usuario['foto_perfil'])) {
                    @unlink($usuario['foto_perfil']);
                }

                $ext = obtenerExtensionSegura($_FILES['foto_perfil']['name'], $check['mime']);
                $nombre = 'user_' . $usuario['id'] . '_' . time() . '.' . $ext;
                $ruta = $dir . $nombre;

                if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $ruta)) {
                    $pdo->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?")->execute([$ruta, $usuario['id']]);
                    $_SESSION['foto_perfil'] = $ruta;
                    $mensaje = 'Foto actualizada';
                    $usuario = obtenerUsuarioActual();
                } else {
                    $error = 'Error al mover el archivo';
                }
            }
        }
    }
}

$foto = $usuario['foto_perfil'] ?? 'assets/default-avatar.png';
if (!filter_var($foto, FILTER_VALIDATE_URL) && !file_exists($foto)) $foto = 'assets/default-avatar.png';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta name="robots" content="noindex, nofollow">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= generarCSRF() ?>">
    <title>Mi Perfil - YSWEBAPK</title>
    <link rel="icon" href="Ico.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .perfil-container { max-width: 600px; margin: 2rem auto; padding: 2rem; background: var(--bg-card); border-radius: var(--radius-xl); box-shadow: var(--shadow-md); border: 1px solid var(--border-color); }
        .foto-perfil { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--primary); display: block; margin: 0 auto .5rem; }
        .rol-badge { display: inline-block; padding: .2rem .8rem; border-radius: 999px; font-size: .8rem; font-weight: 600; background: var(--bg-hover); }
        .rol-badge.admin { background: var(--warning-bg); color: var(--warning); }
        .rol-badge.desarrollador { background: var(--primary-light); color: var(--primary); }
        .seccion { border-top: 1px solid var(--border-color); padding-top: 1.5rem; margin-top: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 500; margin-bottom: .3rem; }
        .form-group input { width: 100%; padding: .7rem 1rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: .95rem; background: var(--bg-input); color: var(--text-primary); }
        .modal-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,.6); backdrop-filter: blur(8px); z-index: 999; display: flex; align-items: center; justify-content: center; padding: 1rem; opacity: 0; visibility: hidden; transition: all .3s; }
        .modal-overlay.open { opacity: 1; visibility: visible; }
        .modal-content { background: var(--bg-card); width: 100%; max-width: 480px; max-height: 90vh; overflow-y: auto; border-radius: var(--radius-xl); padding: 1.5rem; position: relative; border: 1px solid var(--border-color); }
        .modal-close { position: absolute; top: .8rem; right: .8rem; background: var(--bg-hover); border: none; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; color: var(--text-primary); }
    </style>
</head>
<body>

<div class="perfil-container">
    <h1 style="text-align:center;margin-bottom:1.5rem"><i class="fas fa-user" style="color:var(--primary)"></i> Mi Perfil</h1>

    <?php if ($error): ?><div class="error"><?= escapar($error) ?></div><?php endif; ?>
    <?php if ($mensaje): ?><div class="mensaje"><?= escapar($mensaje) ?></div><?php endif; ?>

    <div style="text-align:center;margin-bottom:1rem">
        <img src="<?= escapar($foto) ?>" class="foto-perfil" onerror="this.src='assets/default-avatar.png'">
        <form method="POST" enctype="multipart/form-data" style="display:inline-flex;gap:.5rem;flex-wrap:wrap;justify-content:center;margin-top:.5rem">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="subir_foto">
            <label class="btn-sm btn-secondary" style="cursor:pointer;padding:.5rem 1rem;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-card);display:inline-flex;align-items:center;gap:.4rem">
                <i class="fas fa-camera"></i> Cambiar foto
                <input type="file" name="foto_perfil" accept="image/*" style="display:none" onchange="this.form.submit()">
            </label>
        </form>
    </div>

    <p><strong>Rol:</strong> <span class="rol-badge <?= escapar($usuario['rol']) ?>"><?= ucfirst(escapar($usuario['rol'])) ?></span></p>
    <p><strong>Miembro desde:</strong> <?= date('d/m/Y', strtotime($usuario['creado_en'])) ?></p>

    <form method="POST" class="seccion">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="actualizar_perfil">
        <div class="form-group"><label>Nombre</label><input type="text" name="nombre" value="<?= escapar($usuario['nombre']) ?>" required></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= escapar($usuario['email']) ?>" required></div>
        <button type="submit" class="btn-primary">Actualizar Perfil</button>
    </form>

    <form method="POST" class="seccion">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="cambiar_password">
        <h3>Cambiar Contraseña</h3>
        <div class="form-group"><label>Actual</label><input type="password" name="password_actual" required></div>
        <div class="form-group"><label>Nueva</label><input type="password" name="password_nueva" required minlength="6"></div>
        <div class="form-group"><label>Confirmar</label><input type="password" name="password_confirm" required minlength="6"></div>
        <button type="submit" class="btn-primary">Cambiar Contraseña</button>
    </form>

    <?php if ($usuario['rol'] === 'usuario'): ?>
        <div class="seccion">
            <h3>¿Quieres ser Desarrollador?</h3>
            <button class="btn-primary" id="btnSolicitarDev" style="background:var(--warning)"><i class="fas fa-user-plus"></i> Solicitar</button>
        </div>
    <?php endif; ?>

    <div class="seccion" style="text-align:center;border-top:none">
        <a href="index.php" class="btn-secondary" style="text-decoration:none;display:inline-block;padding:.6rem 1.2rem;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-hover);color:var(--text-primary)">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
</div>

<div class="modal-overlay" id="solicitudDevModal">
    <div class="modal-content">
        <button class="modal-close" onclick="document.getElementById('solicitudDevModal').classList.remove('open')"><i class="fas fa-times"></i></button>
        <h2>Solicitar ser Desarrollador</h2>
        <form id="formSolicitudDev">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="solicitar_desarrollador">
            <div class="form-group"><label>Nombre de Desarrollador *</label><input type="text" name="nombre_desarrollador" required></div>
            <div class="form-group"><label>¿Por qué quieres subir APKs? *</label><textarea name="razon" required rows="4" style="width:100%;padding:.7rem;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-input);color:var(--text-primary);font-family:inherit"></textarea></div>
            <button type="submit" class="btn-primary">Enviar</button>
        </form>
    </div>
</div>

<script>
document.getElementById('btnSolicitarDev')?.addEventListener('click', () => {
    document.getElementById('solicitudDevModal').classList.add('open');
});
document.getElementById('solicitudDevModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
});
document.getElementById('formSolicitudDev')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const body = new URLSearchParams(new FormData(this));
    const r = await fetch('api.php', { method: 'POST', body }).then(x => x.json());
    if (r.success) {
        alert('✅ ' + r.message);
        document.getElementById('solicitudDevModal').classList.remove('open');
    } else {
        alert('❌ ' + r.message);
    }
});
</script>

</body>
</html>