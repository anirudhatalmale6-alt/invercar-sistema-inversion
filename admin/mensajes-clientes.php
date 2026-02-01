<?php
/**
 * InverCar - Admin: Mensajes con Clientes
 */
require_once __DIR__ . '/../includes/init.php';

if (!isAdminLogueado()) {
    redirect('login.php');
}

$db = getDB();
$error = '';
$exito = '';

// Verificar/crear tabla de mensajes
try {
    $db->query("SELECT 1 FROM mensajes_cliente LIMIT 1");
} catch (Exception $e) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `mensajes_cliente` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `cliente_id` INT UNSIGNED NOT NULL,
          `remitente` ENUM('cliente', 'admin') NOT NULL,
          `mensaje` TEXT NOT NULL,
          `leido` TINYINT(1) NOT NULL DEFAULT 0,
          `leido_at` DATETIME DEFAULT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_cliente` (`cliente_id`),
          KEY `idx_remitente` (`remitente`),
          KEY `idx_leido` (`leido`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

$clienteSeleccionado = isset($_GET['cliente']) ? (int)$_GET['cliente'] : null;

// Procesar envío de mensaje
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_mensaje'])) {
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad. Por favor, recarga la página.';
    } else {
        $cliente_id = (int)($_POST['cliente_id'] ?? 0);
        $mensaje = trim($_POST['mensaje'] ?? '');

        if (empty($mensaje)) {
            $error = 'El mensaje no puede estar vacío.';
        } elseif ($cliente_id <= 0) {
            $error = 'Cliente no válido.';
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO mensajes_cliente (cliente_id, remitente, mensaje)
                    VALUES (?, 'admin', ?)
                ");
                $stmt->execute([$cliente_id, $mensaje]);
                $exito = 'Mensaje enviado correctamente.';
                $clienteSeleccionado = $cliente_id;
            } catch (Exception $e) {
                $error = 'Error al enviar el mensaje.';
            }
        }
    }
}

// Obtener lista de clientes con conversaciones (incluyendo no leídos)
$clientesConMensajes = $db->query("
    SELECT c.id, c.nombre, c.apellidos, c.email,
           (SELECT COUNT(*) FROM mensajes_cliente mc WHERE mc.cliente_id = c.id AND mc.remitente = 'cliente' AND mc.leido = 0) as no_leidos,
           (SELECT MAX(created_at) FROM mensajes_cliente mc WHERE mc.cliente_id = c.id) as ultimo_mensaje
    FROM clientes c
    WHERE c.activo = 1
    ORDER BY no_leidos DESC, ultimo_mensaje DESC
")->fetchAll();

// Si hay cliente seleccionado, marcar sus mensajes como leídos y obtener conversación
$mensajes = [];
$clienteInfo = null;
if ($clienteSeleccionado) {
    // Marcar mensajes del cliente como leídos
    $db->prepare("
        UPDATE mensajes_cliente
        SET leido = 1, leido_at = NOW()
        WHERE cliente_id = ? AND remitente = 'cliente' AND leido = 0
    ")->execute([$clienteSeleccionado]);

    // Obtener info del cliente
    $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$clienteSeleccionado]);
    $clienteInfo = $stmt->fetch();

    // Obtener mensajes
    $stmt = $db->prepare("
        SELECT * FROM mensajes_cliente
        WHERE cliente_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$clienteSeleccionado]);
    $mensajes = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Clientes - InverCar Admin</title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .chat-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            height: calc(100vh - 180px);
            min-height: 500px;
        }
        .chat-sidebar {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            overflow-y: auto;
        }
        .chat-sidebar-header {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
        }
        .client-list {
            list-style: none;
        }
        .client-item {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .client-item:hover {
            background: rgba(212, 168, 75, 0.1);
        }
        .client-item.active {
            background: rgba(212, 168, 75, 0.2);
            border-left: 3px solid var(--gold);
        }
        .client-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #000;
            flex-shrink: 0;
        }
        .client-info {
            flex: 1;
            min-width: 0;
        }
        .client-name {
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .client-email {
            font-size: 0.75rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .client-badge {
            background: var(--danger);
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 600;
        }
        .chat-main {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
        }
        .chat-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .chat-header-info h3 {
            font-size: 1rem;
            margin-bottom: 3px;
        }
        .chat-header-info p {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .message-item {
            max-width: 75%;
            padding: 12px 16px;
        }
        .message-item.from-cliente {
            align-self: flex-start;
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid var(--blue-accent);
        }
        .message-item.from-admin {
            align-self: flex-end;
            background: rgba(212, 168, 75, 0.2);
            border: 1px solid var(--gold);
        }
        .message-sender {
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .from-cliente .message-sender { color: var(--blue-accent); }
        .from-admin .message-sender { color: var(--gold); }
        .message-text {
            line-height: 1.5;
            white-space: pre-wrap;
        }
        .message-time {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 8px;
            text-align: right;
        }
        .chat-form {
            padding: 15px 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 10px;
        }
        .chat-form textarea {
            flex: 1;
            resize: none;
            height: 60px;
            padding: 12px;
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--border-color);
            color: var(--text-light);
            font-family: inherit;
        }
        .chat-form textarea:focus {
            outline: none;
            border-color: var(--gold);
        }
        .empty-chat {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: var(--text-muted);
            padding: 40px;
            text-align: center;
        }
        .empty-chat svg {
            width: 60px;
            height: 60px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        .empty-messages {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        .empty-messages svg {
            width: 50px;
            height: 50px;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        @media (max-width: 900px) {
            .chat-layout {
                grid-template-columns: 1fr;
            }
            .chat-sidebar {
                max-height: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1>Chat con Clientes</h1>
                    <p>Mensajería directa con clientes</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo escape($error); ?></div>
            <?php endif; ?>
            <?php if ($exito): ?>
                <div class="alert alert-success"><?php echo escape($exito); ?></div>
            <?php endif; ?>

            <div class="chat-layout">
                <div class="chat-sidebar">
                    <div class="chat-sidebar-header">Clientes</div>
                    <ul class="client-list">
                        <?php foreach ($clientesConMensajes as $c): ?>
                            <a href="?cliente=<?php echo $c['id']; ?>">
                                <li class="client-item <?php echo $clienteSeleccionado == $c['id'] ? 'active' : ''; ?>">
                                    <div class="client-avatar"><?php echo strtoupper(substr($c['nombre'], 0, 1)); ?></div>
                                    <div class="client-info">
                                        <div class="client-name"><?php echo escape($c['nombre'] . ' ' . $c['apellidos']); ?></div>
                                        <div class="client-email"><?php echo escape($c['email']); ?></div>
                                    </div>
                                    <?php if ($c['no_leidos'] > 0): ?>
                                        <span class="client-badge"><?php echo $c['no_leidos']; ?></span>
                                    <?php endif; ?>
                                </li>
                            </a>
                        <?php endforeach; ?>
                        <?php if (empty($clientesConMensajes)): ?>
                            <li style="padding: 20px; color: var(--text-muted); text-align: center;">
                                No hay clientes activos
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="chat-main">
                    <?php if ($clienteInfo): ?>
                        <div class="chat-header">
                            <div class="client-avatar"><?php echo strtoupper(substr($clienteInfo['nombre'], 0, 1)); ?></div>
                            <div class="chat-header-info">
                                <h3><?php echo escape($clienteInfo['nombre'] . ' ' . $clienteInfo['apellidos']); ?></h3>
                                <p><?php echo escape($clienteInfo['email']); ?></p>
                            </div>
                        </div>

                        <div class="chat-messages" id="chatMessages">
                            <?php if (empty($mensajes)): ?>
                                <div class="empty-messages">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    </svg>
                                    <p>No hay mensajes con este cliente</p>
                                    <p style="font-size: 0.85rem;">Inicia la conversación enviando un mensaje</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($mensajes as $m): ?>
                                    <div class="message-item from-<?php echo $m['remitente']; ?>">
                                        <div class="message-sender">
                                            <?php echo $m['remitente'] === 'admin' ? 'Tú' : escape($clienteInfo['nombre']); ?>
                                        </div>
                                        <div class="message-text"><?php echo nl2br(escape($m['mensaje'])); ?></div>
                                        <div class="message-time">
                                            <?php echo date('d/m/Y H:i', strtotime($m['created_at'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <form method="POST" class="chat-form">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="cliente_id" value="<?php echo $clienteInfo['id']; ?>">
                            <textarea name="mensaje" placeholder="Escribe tu mensaje..." required></textarea>
                            <button type="submit" name="enviar_mensaje" class="btn btn-primary">Enviar</button>
                        </form>
                    <?php else: ?>
                        <div class="empty-chat">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                            <p>Selecciona un cliente para ver la conversación</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Scroll to bottom of messages
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    </script>
</body>
</html>
