<?php
/**
 * InverCar - Mensajes del Cliente
 */
require_once __DIR__ . '/../includes/init.php';

if (!isClienteLogueado()) {
    redirect('login.php');
}

$db = getDB();
$error = '';
$exito = '';

// Obtener datos del cliente
$stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$_SESSION['cliente_id']]);
$cliente = $stmt->fetch();

if (!$cliente) {
    redirect('logout.php');
}

// Verificar si la tabla existe, si no crearla
try {
    $db->query("SELECT 1 FROM mensajes_cliente LIMIT 1");
} catch (Exception $e) {
    // Crear la tabla si no existe
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

// Procesar envío de mensaje
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad. Por favor, recarga la página.';
    } else {
        $mensaje = trim($_POST['mensaje'] ?? '');

        if (empty($mensaje)) {
            $error = 'El mensaje no puede estar vacío.';
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO mensajes_cliente (cliente_id, remitente, mensaje)
                    VALUES (?, 'cliente', ?)
                ");
                $stmt->execute([$cliente['id'], $mensaje]);
                $exito = 'Mensaje enviado correctamente.';
            } catch (Exception $e) {
                $error = 'Error al enviar el mensaje.';
            }
        }
    }
}

// Marcar mensajes del admin como leídos
$db->prepare("
    UPDATE mensajes_cliente
    SET leido = 1, leido_at = NOW()
    WHERE cliente_id = ? AND remitente = 'admin' AND leido = 0
")->execute([$cliente['id']]);

// Obtener mensajes
$stmt = $db->prepare("
    SELECT * FROM mensajes_cliente
    WHERE cliente_id = ?
    ORDER BY created_at ASC
");
$stmt->execute([$cliente['id']]);
$mensajes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensajes - InverCar</title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg">
    <link rel="stylesheet" href="../assets/css/cliente.css">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .messages-container {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 200px);
            min-height: 500px;
        }
        .messages-list {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            background: rgba(0,0,0,0.2);
        }
        .message-item {
            max-width: 75%;
            padding: 12px 16px;
        }
        .message-item.from-cliente {
            align-self: flex-end;
            background: rgba(212, 168, 75, 0.2);
            border: 1px solid var(--gold);
        }
        .message-item.from-admin {
            align-self: flex-start;
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid var(--blue-accent);
        }
        .message-sender {
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .from-cliente .message-sender { color: var(--gold); }
        .from-admin .message-sender { color: var(--blue-accent); }
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
        .message-form {
            padding: 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 10px;
            background: var(--card-bg);
        }
        .message-form textarea {
            flex: 1;
            resize: none;
            height: 60px;
            padding: 12px;
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--border-color);
            color: var(--text-light);
        }
        .message-form textarea:focus {
            outline: none;
            border-color: var(--gold);
        }
        .empty-messages {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        .empty-messages svg {
            width: 60px;
            height: 60px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="cliente-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1>Mensajes</h1>
                    <p>Comunicación con el administrador</p>
                </div>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo escape($cliente['nombre']); ?></div>
                    </div>
                    <div class="user-avatar"><?php echo strtoupper(substr($cliente['nombre'], 0, 1)); ?></div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo escape($error); ?></div>
            <?php endif; ?>
            <?php if ($exito): ?>
                <div class="alert alert-success"><?php echo escape($exito); ?></div>
            <?php endif; ?>

            <div class="card messages-container">
                <div class="messages-list" id="messagesList">
                    <?php if (empty($mensajes)): ?>
                        <div class="empty-messages">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                            <p>No hay mensajes aún</p>
                            <p style="font-size: 0.85rem;">Envía tu primer mensaje al administrador</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($mensajes as $m): ?>
                            <div class="message-item from-<?php echo $m['remitente']; ?>">
                                <div class="message-sender">
                                    <?php echo $m['remitente'] === 'cliente' ? 'Tú' : 'Administrador'; ?>
                                </div>
                                <div class="message-text"><?php echo nl2br(escape($m['mensaje'])); ?></div>
                                <div class="message-time">
                                    <?php echo date('d/m/Y H:i', strtotime($m['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form method="POST" class="message-form">
                    <?php echo csrfField(); ?>
                    <textarea name="mensaje" placeholder="Escribe tu mensaje..." required></textarea>
                    <button type="submit" class="btn btn-primary">Enviar</button>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Scroll to bottom of messages
        const messagesList = document.getElementById('messagesList');
        if (messagesList) {
            messagesList.scrollTop = messagesList.scrollHeight;
        }
    </script>
</body>
</html>
