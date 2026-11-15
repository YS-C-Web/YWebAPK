<?php
require_once 'config/database.php';
require_once 'config/funciones.php';

$usuario_id = $_SESSION['usuario_id'] ?? null;
$es_admin = esAdmin();
$anonimo_token = null;
$chat_id = null;

// ===== RESOLVER CHAT =====
if (isset($_GET['chat']) && ($es_admin || intval($_GET['chat']) > 0)) {
    $chat_id = intval($_GET['chat']);
} elseif ($usuario_id) {
    $stmt = $pdo->prepare("SELECT id FROM chats WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $chat = $stmt->fetch();
    if ($chat) $chat_id = $chat['id'];
    else {
        $pdo->prepare("INSERT INTO chats (usuario_id) VALUES (?)")->execute([$usuario_id]);
        $chat_id = $pdo->lastInsertId();
    }
} else {
    if (isset($_COOKIE['anonimo_token'])) $anonimo_token = $_COOKIE['anonimo_token'];
    else {
        $anonimo_token = generarToken();
        setcookie('anonimo_token', $anonimo_token, time() + 365*24*3600, '/');
    }
    $stmt = $pdo->prepare("SELECT id FROM chats WHERE anonimo_token = ?");
    $stmt->execute([$anonimo_token]);
    $chat = $stmt->fetch();
    if ($chat) $chat_id = $chat['id'];
    else {
        $pdo->prepare("INSERT INTO chats (anonimo_token) VALUES (?)")->execute([$anonimo_token]);
        $chat_id = $pdo->lastInsertId();
    }
}

if ($chat_id === null) { header('Location: index.php'); exit; }

// ===== ENVIAR MENSAJE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'enviar_mensaje') {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) {
        header("Location: soporte.php?chat=$chat_id&error=csrf"); exit;
    }
    if (!verificarRateLimit('chat', 20, 5)) {
        header("Location: soporte.php?chat=$chat_id&error=rate"); exit;
    }

    $mensaje = limpiarEntrada($_POST['mensaje'] ?? '');
    $responder_a = !empty($_POST['responder_a']) ? intval($_POST['responder_a']) : null;

    if ($mensaje !== '' && mb_strlen($mensaje) <= 2000) {
        // BUG CORREGIDO: es_admin según el rol actual
        $pdo->prepare("INSERT INTO mensajes_chat (chat_id, usuario_id, anonimo_token, mensaje, mensaje_respondido_id, es_admin)
            VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([
                $chat_id,
                $es_admin ? null : $usuario_id,
                $es_admin ? null : $anonimo_token,
                $mensaje,
                $responder_a,
                $es_admin ? 1 : 0,
            ]);
    }
    header("Location: soporte.php?chat=$chat_id");
    exit;
}

// ===== MENSAJES =====
$stmt = $pdo->prepare("SELECT mc.*, u.nombre AS usuario_nombre
    FROM mensajes_chat mc
    LEFT JOIN usuarios u ON mc.usuario_id = u.id
    WHERE mc.chat_id = ? ORDER BY mc.creado_en ASC");
$stmt->execute([$chat_id]);
$mensajes = $stmt->fetchAll();

// ===== ADMIN: marcar como leídos los mensajes de usuarios =====
if ($es_admin) {
    $pdo->prepare("UPDATE mensajes_chat SET leido = TRUE WHERE chat_id = ? AND es_admin = FALSE")->execute([$chat_id]);
}

// ===== LISTA DE CHATS (solo admin) =====
$chats_activos = [];
if ($es_admin) {
    $chats_activos = $pdo->query("SELECT c.id, c.usuario_id, c.anonimo_token,
        u.nombre AS usuario_nombre,
        (SELECT COUNT(*) FROM mensajes_chat WHERE chat_id = c.id AND leido = FALSE AND es_admin = FALSE) AS no_leidos
        FROM chats c
        LEFT JOIN usuarios u ON c.usuario_id = u.id
        ORDER BY c.creado_en DESC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta name="robots" content="noindex, nofollow">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= generarCSRF() ?>">
    <title>Soporte - YSWEBAPK</title>
    <link rel="icon" href="Ico.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .soporte-container { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }
        .chat-layout { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        <?php if ($es_admin): ?>
        .chat-layout { grid-template-columns: 260px 1fr; }
        @media (max-width: 768px) { .chat-layout { grid-template-columns: 1fr; } }
        <?php endif; ?>
        .chat-list { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1rem; max-height: 600px; overflow-y: auto; }
        .chat-item { display: block; padding: .5rem .8rem; border-radius: 8px; text-decoration: none; color: var(--text-primary); margin-bottom: .3rem; border: 1px solid transparent; }
        .chat-item:hover { background: var(--bg-hover); }
        .chat-item.active { background: var(--primary-light); border-color: var(--primary); }
        .chat-item .badge { background: var(--danger); color: white; padding: .05rem .5rem; border-radius: 20px; font-size: .7rem; float: right; }
        .chat-area { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; display: flex; flex-direction: column; height: 600px; }
        .chat-header { padding: .8rem 1rem; border-bottom: 1px solid var(--border-color); font-weight: 600; }
        .chat-messages { flex: 1; padding: 1rem; overflow-y: auto; display: flex; flex-direction: column; gap: .5rem; }
        .chat-message { max-width: 80%; padding: .6rem 1rem; border-radius: 16px; position: relative; }
        .chat-message.usuario { align-self: flex-end; background: var(--primary); color: white; }
        .chat-message.admin { align-self: flex-start; background: var(--bg-hover); }
        .chat-message.anonimo { align-self: flex-start; background: var(--warning-bg); color: var(--text-primary); }
        .chat-message .meta { font-size: .7rem; opacity: .75; margin-bottom: .2rem; }
        .chat-message .texto { word-wrap: break-word; }
        .chat-message .cita { background: rgba(0,0,0,.06); padding: .5rem .8rem; border-radius: 8px; margin-bottom: .5rem; border-left: 3px solid var(--primary); font-size: .9rem; opacity: .9; }
        .chat-message .acciones-mensaje { margin-top: .3rem; display: flex; gap: .5rem; }
        .chat-message .acciones-mensaje button { background: none; border: none; color: inherit; opacity: .7; font-size: .7rem; cursor: pointer; }
        .chat-message .acciones-mensaje button:hover { opacity: 1; }
        .chat-input { padding: .8rem 1rem; border-top: 1px solid var(--border-color); }
        .chat-input textarea { width: 100%; padding: .6rem .8rem; border: 1px solid var(--border-color); border-radius: 12px; resize: none; height: 50px; font-family: inherit; background: var(--bg-input); color: var(--text-primary); }
        .chat-input button { margin-top: .4rem; padding: .5rem 1.2rem; background: var(--primary); color: white; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; }
        .chat-input .respondiendo { display: none; background: var(--bg-hover); padding: .3rem .8rem; border-radius: 8px; font-size: .8rem; margin-bottom: .4rem; color: var(--text-secondary); }
        .chat-input .respondiendo.visible { display: block; }
        .chat-input .respondiendo .cancelar { color: var(--danger); cursor: pointer; font-weight: 600; }
        .admin-info { background: var(--warning-bg); padding: .5rem 1rem; border-radius: 8px; font-size: .8rem; color: var(--warning); text-align: center; margin-bottom: 1rem; border-left: 4px solid var(--warning); }
    </style>
</head>
<body>

<div class="soporte-container">
    <h1><i class="fas fa-headset" style="color:var(--primary)"></i> Soporte Técnico</h1>
    <a href="index.php" class="btn-sm btn-primary" style="padding:.5rem 1rem;display:inline-block;text-decoration:none;border-radius:8px;background:var(--primary);color:white;margin-bottom:1rem;">
        <i class="fas fa-arrow-left"></i> Volver
    </a>

    <?php if (!$usuario_id && !$es_admin): ?>
        <div class="admin-info">
            <i class="fas fa-user-secret"></i> Modo anónimo. Tu conversación es privada.
        </div>
    <?php endif; ?>

    <div class="chat-layout">
        <?php if ($es_admin): ?>
            <div class="chat-list">
                <h4>Chats activos</h4>
                <?php foreach ($chats_activos as $c): ?>
                    <a href="soporte.php?chat=<?= (int)$c['id'] ?>" class="chat-item <?= $c['id'] == $chat_id ? 'active' : '' ?>">
                        <strong><?= $c['usuario_id'] ? escapar($c['usuario_nombre'] ?? 'Usuario '.$c['usuario_id']) : 'Anónimo' ?></strong>
                        <?php if ($c['no_leidos'] > 0): ?><span class="badge"><?= (int)$c['no_leidos'] ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <?php if (empty($chats_activos)): ?><p style="color:var(--text-secondary);font-size:.85rem">No hay chats.</p><?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="chat-area">
            <div class="chat-header">
                <i class="fas fa-comments"></i>
                <?= $usuario_id ? 'Chat de ' . escapar($_SESSION['nombre']) : 'Chat Anónimo' ?>
                <?php if ($es_admin): ?><span style="color:var(--danger);font-size:.75rem">(Modo Admin)</span><?php endif; ?>
            </div>

            <div class="chat-messages" id="chatMessages">
                <?php foreach ($mensajes as $m): ?>
                    <div class="chat-message <?= $m['es_admin'] ? 'admin' : ($m['usuario_id'] ? 'usuario' : 'anonimo') ?>" id="msg-<?= (int)$m['id'] ?>">
                        <div class="meta">
                            <?php if ($m['es_admin']): ?><i class="fas fa-shield-alt"></i> Admin
                            <?php elseif ($m['usuario_id']): ?><?= escapar($m['usuario_nombre'] ?? 'Usuario') ?>
                            <?php else: ?><i class="fas fa-user-secret"></i> Anónimo<?php endif; ?>
                            · <?= date('d/m/Y H:i', strtotime($m['creado_en'])) ?>
                        </div>
                        <?php if ($m['mensaje_respondido_id']): ?>
                            <?php
                                $stmt2 = $pdo->prepare("SELECT mensaje FROM mensajes_chat WHERE id = ?");
                                $stmt2->execute([$m['mensaje_respondido_id']]);
                                $resp = $stmt2->fetch();
                            ?>
                            <?php if ($resp): ?>
                                <div class="cita" onclick="document.getElementById('msg-<?= (int)$m['mensaje_respondido_id'] ?>')?.scrollIntoView({behavior:'smooth'})" style="cursor:pointer">
                                    <small>↪ <?= escapar(mb_substr($resp['mensaje'], 0, 100)) ?></small>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="texto"><?= nl2br(escapar($m['mensaje'])) ?></div>
                        <div class="acciones-mensaje">
                            <button onclick="responderMensaje(<?= (int)$m['id'] ?>, <?= json_encode(mb_substr($m['mensaje'], 0, 60)) ?>)">
                                <i class="fas fa-reply"></i> Responder
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($mensajes)): ?>
                    <div style="text-align:center;color:var(--text-secondary);padding:2rem 0">
                        <i class="fas fa-comment" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
                        No hay mensajes aún
                    </div>
                <?php endif; ?>
            </div>

            <div class="chat-input">
                <div class="respondiendo" id="respondiendoDiv">
                    <i class="fas fa-reply"></i> Respondiendo a: <span id="respondiendoTexto"></span>
                    <span class="cancelar" onclick="cancelarRespuesta()">Cancelar</span>
                </div>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="enviar_mensaje">
                    <input type="hidden" name="responder_a" id="responderAInput" value="">
                    <textarea name="mensaje" placeholder="Escribe tu mensaje..." required maxlength="2000"></textarea>
                    <button type="submit"><i class="fas fa-paper-plane"></i> Enviar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function responderMensaje(id, texto) {
    document.getElementById('responderAInput').value = id;
    document.getElementById('respondiendoTexto').textContent = texto + '...';
    document.getElementById('respondiendoDiv').classList.add('visible');
    document.querySelector('.chat-input textarea').focus();
}
function cancelarRespuesta() {
    document.getElementById('responderAInput').value = '';
    document.getElementById('respondiendoDiv').classList.remove('visible');
}
const cm = document.getElementById('chatMessages');
if (cm) cm.scrollTop = cm.scrollHeight;
</script>

</body>
</html>