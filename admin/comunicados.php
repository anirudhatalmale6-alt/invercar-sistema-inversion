<?php
/**
 * InverCar - Admin: Comunicados y Emails Masivos
 * Sistema para enviar emails a clientes con plantillas
 */
require_once __DIR__ . '/../includes/init.php';
require_once INCLUDES_PATH . '/mail.php';

if (!isAdminLogueado()) {
    redirect('login.php');
}

$db = getDB();
$error = '';
$exito = '';
$tab = $_GET['tab'] ?? 'enviar';

// Crear tabla de plantillas si no existe
try {
    $db->query("SELECT 1 FROM plantillas_email LIMIT 1");
} catch (Exception $e) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `plantillas_email` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `titulo` VARCHAR(255) NOT NULL,
          `asunto` VARCHAR(255) NOT NULL,
          `contenido` TEXT NOT NULL,
          `activo` TINYINT(1) NOT NULL DEFAULT 1,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// Crear tabla de historial de envíos
try {
    $db->query("SELECT 1 FROM historial_envios LIMIT 1");
} catch (Exception $e) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `historial_envios` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `plantilla_id` INT UNSIGNED NULL,
          `asunto` VARCHAR(255) NOT NULL,
          `contenido` TEXT NOT NULL,
          `destinatarios` VARCHAR(50) NOT NULL,
          `total_enviados` INT NOT NULL DEFAULT 0,
          `total_errores` INT NOT NULL DEFAULT 0,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad. Por favor, recarga la página.';
    } else {
        $action = $_POST['action'] ?? '';

        // Guardar plantilla
        if ($action === 'guardar_plantilla') {
            $titulo = cleanInput($_POST['titulo'] ?? '');
            $asunto = cleanInput($_POST['asunto'] ?? '');
            $contenido = trim($_POST['contenido'] ?? '');
            $activo = isset($_POST['activo']) ? 1 : 0;
            $plantilla_id = intval($_POST['plantilla_id'] ?? 0);

            if (empty($titulo) || empty($asunto) || empty($contenido)) {
                $error = 'Todos los campos son obligatorios.';
            } else {
                try {
                    if ($plantilla_id > 0) {
                        // Actualizar
                        $stmt = $db->prepare("UPDATE plantillas_email SET titulo = ?, asunto = ?, contenido = ?, activo = ? WHERE id = ?");
                        $stmt->execute([$titulo, $asunto, $contenido, $activo, $plantilla_id]);
                        $exito = 'Plantilla actualizada correctamente.';
                    } else {
                        // Crear nueva
                        $stmt = $db->prepare("INSERT INTO plantillas_email (titulo, asunto, contenido, activo) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$titulo, $asunto, $contenido, $activo]);
                        $exito = 'Plantilla creada correctamente.';
                    }
                    $tab = 'plantillas';
                } catch (Exception $e) {
                    $error = 'Error al guardar la plantilla.';
                }
            }
        }

        // Eliminar plantilla
        if ($action === 'eliminar_plantilla') {
            $plantilla_id = intval($_POST['plantilla_id'] ?? 0);
            if ($plantilla_id > 0) {
                $db->prepare("DELETE FROM plantillas_email WHERE id = ?")->execute([$plantilla_id]);
                $exito = 'Plantilla eliminada.';
                $tab = 'plantillas';
            }
        }

        // Enviar email
        if ($action === 'enviar_email') {
            $destinatarios = cleanInput($_POST['destinatarios'] ?? '');
            $cliente_id = intval($_POST['cliente_id'] ?? 0);
            $asunto = cleanInput($_POST['asunto'] ?? '');
            $contenido = trim($_POST['contenido'] ?? '');

            if (empty($asunto) || empty($contenido)) {
                $error = 'El asunto y contenido son obligatorios.';
            } else {
                // Obtener lista de clientes según destinatarios
                $clientes = [];

                switch ($destinatarios) {
                    case 'individual':
                        if ($cliente_id > 0) {
                            $stmt = $db->prepare("SELECT id, nombre, apellidos, email FROM clientes WHERE id = ? AND activo = 1");
                            $stmt->execute([$cliente_id]);
                            $clientes = $stmt->fetchAll();
                        }
                        break;
                    case 'todos':
                        $clientes = $db->query("SELECT id, nombre, apellidos, email FROM clientes WHERE activo = 1 AND registro_completo = 1 AND email_verificado = 1")->fetchAll();
                        break;
                    case 'fija':
                        $clientes = $db->query("SELECT DISTINCT c.id, c.nombre, c.apellidos, c.email FROM clientes c INNER JOIN capital cap ON cap.cliente_id = c.id AND cap.tipo_inversion = 'fija' AND cap.activo = 1 WHERE c.activo = 1 AND c.registro_completo = 1 AND c.email_verificado = 1")->fetchAll();
                        break;
                    case 'variable':
                        $clientes = $db->query("SELECT DISTINCT c.id, c.nombre, c.apellidos, c.email FROM clientes c INNER JOIN capital cap ON cap.cliente_id = c.id AND cap.tipo_inversion = 'variable' AND cap.activo = 1 WHERE c.activo = 1 AND c.registro_completo = 1 AND c.email_verificado = 1")->fetchAll();
                        break;
                }

                if (empty($clientes)) {
                    $error = 'No hay clientes que cumplan los criterios seleccionados.';
                } else {
                    $enviados = 0;
                    $errores = 0;

                    foreach ($clientes as $cliente) {
                        $nombreCompleto = trim($cliente['nombre'] . ' ' . ($cliente['apellidos'] ?? ''));

                        // Reemplazar variables en el contenido
                        $contenidoPersonalizado = str_replace(
                            ['{nombre}', '{email}'],
                            [$nombreCompleto, $cliente['email']],
                            $contenido
                        );

                        // Generar HTML del email
                        $mensajeHtml = generarEmailComunicado($nombreCompleto, $asunto, $contenidoPersonalizado);

                        if (enviarEmail($cliente['email'], $asunto, $mensajeHtml)) {
                            $enviados++;
                        } else {
                            $errores++;
                        }
                    }

                    // Guardar en historial
                    $stmt = $db->prepare("INSERT INTO historial_envios (asunto, contenido, destinatarios, total_enviados, total_errores) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$asunto, $contenido, $destinatarios, $enviados, $errores]);

                    $exito = "Email enviado: {$enviados} exitosos, {$errores} errores.";
                }
            }
        }
    }
}

// Obtener plantillas
$plantillas = $db->query("SELECT * FROM plantillas_email ORDER BY activo DESC, titulo ASC")->fetchAll();

// Obtener historial
$historial = $db->query("SELECT * FROM historial_envios ORDER BY created_at DESC LIMIT 50")->fetchAll();

// Obtener clientes para el selector (con tipo de inversión desde tabla capital)
$clientesActivos = $db->query("
    SELECT c.id, c.nombre, c.apellidos, c.email,
           GROUP_CONCAT(DISTINCT cap.tipo_inversion) as tipos_inversion
    FROM clientes c
    LEFT JOIN capital cap ON cap.cliente_id = c.id AND cap.activo = 1
    WHERE c.activo = 1 AND c.registro_completo = 1
    GROUP BY c.id, c.nombre, c.apellidos, c.email
    ORDER BY c.nombre
")->fetchAll();

// Editar plantilla
$plantillaEditar = null;
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM plantillas_email WHERE id = ?");
    $stmt->execute([intval($_GET['editar'])]);
    $plantillaEditar = $stmt->fetch();
    $tab = 'plantillas';
}

/**
 * Generar HTML del email para comunicados
 */
function generarEmailComunicado($nombre, $asunto, $contenido) {
    $logoUrl = SITE_URL . '/assets/images/logo-invercar.png';
    $contenidoHtml = nl2br(htmlspecialchars($contenido));

    return "
    <html>
    <head>
        <style>
            body { font-family: 'Raleway', Arial, sans-serif; background-color: #1a1a2e; padding: 20px; margin: 0; }
            .container { max-width: 600px; margin: 0 auto; background: #16213e; border-radius: 0; padding: 40px; border: 1px solid #d4a84b; }
            .header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(212, 168, 75, 0.3); }
            .header img { max-width: 200px; height: auto; }
            p { color: #ffffff; line-height: 1.6; }
            .content { color: #ffffff; line-height: 1.8; }
            .btn { display: inline-block; background: linear-gradient(135deg, #d4a84b 0%, #c9a227 100%); color: #1a1a2e !important; padding: 15px 30px; text-decoration: none; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
            .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(212, 168, 75, 0.3); color: #888; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <img src='{$logoUrl}' alt='InverCar' onerror=\"this.style.display='none';this.parentNode.innerHTML='<span style=color:#d4a84b;font-size:28px;font-weight:bold;>INVERCAR</span>'\" />
            </div>
            <p>Hola <strong style='color: #d4a84b;'>{$nombre}</strong>,</p>
            <div class='content'>
                {$contenidoHtml}
            </div>
            <p style='text-align: center; margin: 30px 0;'>
                <a href='" . SITE_URL . "/cliente/panel.php' class='btn'>Acceder a Mi Panel</a>
            </p>
            <div class='footer'>
                <p>&copy; " . date('Y') . " InverCar. Todos los derechos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comunicados - InverCar Admin</title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .tabs {
            display: flex;
            gap: 0;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        .tab-btn {
            padding: 12px 24px;
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }
        .tab-btn:hover {
            color: var(--text-light);
        }
        .tab-btn.active {
            color: var(--gold);
            border-bottom-color: var(--gold);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        .plantilla-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            padding: 20px;
            margin-bottom: 15px;
        }
        .plantilla-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .plantilla-title {
            font-weight: 600;
            font-size: 1.1rem;
        }
        .plantilla-asunto {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-bottom: 10px;
        }
        .plantilla-preview {
            color: var(--text-muted);
            font-size: 0.85rem;
            max-height: 60px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .plantilla-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .badge-inactive {
            background: var(--danger);
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            margin-left: 10px;
        }
        .historial-item {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        .historial-item:last-child {
            border-bottom: none;
        }
        .historial-fecha {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .historial-stats {
            font-size: 0.8rem;
            margin-top: 5px;
        }
        .historial-stats .success { color: var(--success); }
        .historial-stats .error { color: var(--danger); }
        #cliente_id_wrapper {
            display: none;
        }
        .variables-help {
            background: rgba(0,0,0,0.2);
            padding: 10px 15px;
            margin-bottom: 15px;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .variables-help code {
            background: var(--gold);
            color: #000;
            padding: 2px 6px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1>Comunicados</h1>
                    <p>Envío de emails masivos a clientes</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo escape($error); ?></div>
            <?php endif; ?>
            <?php if ($exito): ?>
                <div class="alert alert-success"><?php echo escape($exito); ?></div>
            <?php endif; ?>

            <div class="tabs">
                <button class="tab-btn <?php echo $tab === 'enviar' ? 'active' : ''; ?>" onclick="showTab('enviar')">Enviar Email</button>
                <button class="tab-btn <?php echo $tab === 'plantillas' ? 'active' : ''; ?>" onclick="showTab('plantillas')">Plantillas</button>
                <button class="tab-btn <?php echo $tab === 'historial' ? 'active' : ''; ?>" onclick="showTab('historial')">Historial</button>
            </div>

            <!-- Tab: Enviar Email -->
            <div id="tab-enviar" class="tab-content <?php echo $tab === 'enviar' ? 'active' : ''; ?>">
                <div class="card">
                    <div class="card-header">
                        <h2>Nuevo Comunicado</h2>
                    </div>
                    <div class="card-body">
                        <div class="variables-help">
                            <strong>Variables disponibles:</strong>
                            <code>{nombre}</code> - Nombre completo del cliente,
                            <code>{email}</code> - Email del cliente
                        </div>

                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="enviar_email">

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="destinatarios">Destinatarios</label>
                                    <select name="destinatarios" id="destinatarios" required onchange="toggleClienteSelect()">
                                        <option value="todos">Todos los clientes activos</option>
                                        <option value="fija">Solo inversión Fija</option>
                                        <option value="variable">Solo inversión Variable</option>
                                        <option value="individual">Cliente individual</option>
                                    </select>
                                </div>
                                <div class="form-group" id="cliente_id_wrapper">
                                    <label for="cliente_id">Seleccionar Cliente</label>
                                    <select name="cliente_id" id="cliente_id">
                                        <option value="">-- Seleccionar --</option>
                                        <?php foreach ($clientesActivos as $c): ?>
                                            <option value="<?php echo $c['id']; ?>">
                                                <?php echo escape($c['nombre'] . ' ' . $c['apellidos']); ?>
                                                (<?php echo escape($c['email']); ?>)
                                                - <?php echo escape($c['tipos_inversion'] ?? 'Sin capital'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="plantilla_select">Cargar desde plantilla (opcional)</label>
                                <select id="plantilla_select" onchange="cargarPlantilla()">
                                    <option value="">-- Sin plantilla --</option>
                                    <?php foreach ($plantillas as $p): ?>
                                        <?php if ($p['activo']): ?>
                                            <option value="<?php echo $p['id']; ?>"
                                                    data-asunto="<?php echo escape($p['asunto']); ?>"
                                                    data-contenido="<?php echo escape($p['contenido']); ?>">
                                                <?php echo escape($p['titulo']); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="asunto">Asunto del email *</label>
                                <input type="text" name="asunto" id="asunto" required placeholder="Asunto del email">
                            </div>

                            <div class="form-group">
                                <label for="contenido">Contenido del mensaje *</label>
                                <textarea name="contenido" id="contenido" rows="10" required placeholder="Escribe el contenido del email..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary" onclick="return confirm('¿Enviar el email a los destinatarios seleccionados?');">Enviar Email</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tab: Plantillas -->
            <div id="tab-plantillas" class="tab-content <?php echo $tab === 'plantillas' ? 'active' : ''; ?>">
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2><?php echo $plantillaEditar ? 'Editar Plantilla' : 'Nueva Plantilla'; ?></h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="guardar_plantilla">
                            <?php if ($plantillaEditar): ?>
                                <input type="hidden" name="plantilla_id" value="<?php echo $plantillaEditar['id']; ?>">
                            <?php endif; ?>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="titulo">Título de la plantilla *</label>
                                    <input type="text" name="titulo" id="titulo" required
                                           value="<?php echo $plantillaEditar ? escape($plantillaEditar['titulo']) : ''; ?>"
                                           placeholder="Ej: Bienvenida nuevo cliente">
                                </div>
                                <div class="form-group">
                                    <label for="asunto_plantilla">Asunto del email *</label>
                                    <input type="text" name="asunto" id="asunto_plantilla" required
                                           value="<?php echo $plantillaEditar ? escape($plantillaEditar['asunto']) : ''; ?>"
                                           placeholder="Asunto que verá el cliente">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="contenido_plantilla">Contenido *</label>
                                <textarea name="contenido" id="contenido_plantilla" rows="8" required placeholder="Contenido del email..."><?php echo $plantillaEditar ? escape($plantillaEditar['contenido']) : ''; ?></textarea>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="activo" <?php echo (!$plantillaEditar || $plantillaEditar['activo']) ? 'checked' : ''; ?>>
                                    Plantilla activa
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary"><?php echo $plantillaEditar ? 'Actualizar' : 'Crear'; ?> Plantilla</button>
                            <?php if ($plantillaEditar): ?>
                                <a href="comunicados.php?tab=plantillas" class="btn btn-secondary">Cancelar</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <?php if (!empty($plantillas)): ?>
                <div class="card">
                    <div class="card-header">
                        <h2>Plantillas guardadas</h2>
                    </div>
                    <div class="card-body">
                        <?php foreach ($plantillas as $p): ?>
                            <div class="plantilla-card">
                                <div class="plantilla-header">
                                    <span class="plantilla-title">
                                        <?php echo escape($p['titulo']); ?>
                                        <?php if (!$p['activo']): ?>
                                            <span class="badge-inactive">Inactiva</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="plantilla-asunto">Asunto: <?php echo escape($p['asunto']); ?></div>
                                <div class="plantilla-preview"><?php echo escape(substr($p['contenido'], 0, 200)); ?>...</div>
                                <div class="plantilla-actions">
                                    <a href="?editar=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                    <form method="POST" style="display: inline;">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="eliminar_plantilla">
                                        <input type="hidden" name="plantilla_id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar esta plantilla?');">Eliminar</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Historial -->
            <div id="tab-historial" class="tab-content <?php echo $tab === 'historial' ? 'active' : ''; ?>">
                <div class="card">
                    <div class="card-header">
                        <h2>Historial de Envíos</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($historial)): ?>
                            <div class="empty-state">
                                <p>No hay envíos registrados</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($historial as $h): ?>
                                <div class="historial-item">
                                    <div class="historial-fecha"><?php echo date('d/m/Y H:i', strtotime($h['created_at'])); ?></div>
                                    <strong><?php echo escape($h['asunto']); ?></strong>
                                    <div class="historial-stats">
                                        Destinatarios: <strong><?php
                                            $destLabels = [
                                                'todos' => 'Todos los clientes',
                                                'fija' => 'Solo Fija',
                                                'variable' => 'Solo Variable',
                                                'individual' => 'Individual'
                                            ];
                                            echo $destLabels[$h['destinatarios']] ?? $h['destinatarios'];
                                        ?></strong> |
                                        <span class="success"><?php echo $h['total_enviados']; ?> enviados</span>
                                        <?php if ($h['total_errores'] > 0): ?>
                                            | <span class="error"><?php echo $h['total_errores']; ?> errores</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));

            // Show selected tab
            document.getElementById('tab-' + tabName).classList.add('active');
            event.target.classList.add('active');

            // Update URL without reload
            history.replaceState(null, '', '?tab=' + tabName);
        }

        function toggleClienteSelect() {
            const dest = document.getElementById('destinatarios').value;
            const wrapper = document.getElementById('cliente_id_wrapper');
            wrapper.style.display = dest === 'individual' ? 'block' : 'none';

            if (dest === 'individual') {
                document.getElementById('cliente_id').required = true;
            } else {
                document.getElementById('cliente_id').required = false;
            }
        }

        function cargarPlantilla() {
            const select = document.getElementById('plantilla_select');
            const option = select.options[select.selectedIndex];

            if (option.value) {
                document.getElementById('asunto').value = option.dataset.asunto;
                document.getElementById('contenido').value = option.dataset.contenido;
            }
        }

        // Initialize
        toggleClienteSelect();
    </script>
</body>
</html>
