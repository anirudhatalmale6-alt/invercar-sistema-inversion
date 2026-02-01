<?php
/**
 * InverCar - Configuración del Cliente
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

// Verificar/crear tabla de configuración
try {
    $db->query("SELECT 1 FROM cliente_configuracion LIMIT 1");
} catch (Exception $e) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `cliente_configuracion` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `cliente_id` INT UNSIGNED NOT NULL,
          `notificar_vehiculos_nuevos` TINYINT(1) NOT NULL DEFAULT 1,
          `notificar_mensajes` TINYINT(1) NOT NULL DEFAULT 1,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `cliente_unico` (`cliente_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// Obtener configuración actual o crear por defecto
$stmt = $db->prepare("SELECT * FROM cliente_configuracion WHERE cliente_id = ?");
$stmt->execute([$cliente['id']]);
$config = $stmt->fetch();

if (!$config) {
    // Crear configuración por defecto
    $stmt = $db->prepare("INSERT INTO cliente_configuracion (cliente_id) VALUES (?)");
    $stmt->execute([$cliente['id']]);

    $stmt = $db->prepare("SELECT * FROM cliente_configuracion WHERE cliente_id = ?");
    $stmt->execute([$cliente['id']]);
    $config = $stmt->fetch();
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad. Por favor, recarga la página.';
    } else {
        $notificar_vehiculos = isset($_POST['notificar_vehiculos_nuevos']) ? 1 : 0;
        $notificar_mensajes = isset($_POST['notificar_mensajes']) ? 1 : 0;

        try {
            $stmt = $db->prepare("
                UPDATE cliente_configuracion
                SET notificar_vehiculos_nuevos = ?, notificar_mensajes = ?
                WHERE cliente_id = ?
            ");
            $stmt->execute([$notificar_vehiculos, $notificar_mensajes, $cliente['id']]);
            $exito = 'Configuración guardada correctamente.';

            // Recargar configuración
            $stmt = $db->prepare("SELECT * FROM cliente_configuracion WHERE cliente_id = ?");
            $stmt->execute([$cliente['id']]);
            $config = $stmt->fetch();
        } catch (Exception $e) {
            $error = 'Error al guardar la configuración.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - InverCar</title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg">
    <link rel="stylesheet" href="../assets/css/cliente.css">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .config-section {
            margin-bottom: 30px;
        }
        .config-section h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--gold);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .config-section h3 svg {
            width: 20px;
            height: 20px;
        }
        .config-option {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            background: rgba(0,0,0,0.2);
            margin-bottom: 10px;
        }
        .config-option input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-top: 2px;
            cursor: pointer;
            accent-color: var(--gold);
        }
        .config-option-info {
            flex: 1;
        }
        .config-option-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .config-option-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            line-height: 1.5;
        }
        .form-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
    </style>
</head>
<body>
    <div class="cliente-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1>Configuración</h1>
                    <p>Preferencias de notificaciones</p>
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

            <div class="card">
                <div class="card-header">
                    <h2>Notificaciones por Email</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php echo csrfField(); ?>

                        <div class="config-section">
                            <h3>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3zm-8.27 4a2 2 0 0 1-3.46 0"/>
                                </svg>
                                Notificaciones
                            </h3>

                            <div class="config-option">
                                <input type="checkbox" name="notificar_vehiculos_nuevos" id="notificar_vehiculos"
                                       <?php echo $config['notificar_vehiculos_nuevos'] ? 'checked' : ''; ?>>
                                <div class="config-option-info">
                                    <div class="config-option-title">Nuevos Vehículos</div>
                                    <div class="config-option-desc">
                                        Recibir un email cuando se añada un nuevo vehículo de inversión disponible en el sistema.
                                    </div>
                                </div>
                            </div>

                            <div class="config-option">
                                <input type="checkbox" name="notificar_mensajes" id="notificar_mensajes"
                                       <?php echo $config['notificar_mensajes'] ? 'checked' : ''; ?>>
                                <div class="config-option-info">
                                    <div class="config-option-title">Mensajes del Administrador</div>
                                    <div class="config-option-desc">
                                        Recibir un email cuando el administrador te envíe un mensaje nuevo.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Guardar Configuración</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
