<?php
/**
 * InverCar - Gestión de Mensajes de Contacto
 */
require_once __DIR__ . '/../includes/init.php';

if (!isAdminLogueado()) {
    redirect('login.php');
}

$db = getDB();

// Marcar como leído
if (isset($_GET['leer'])) {
    $db->prepare("UPDATE contactos SET leido = 1 WHERE id = ?")->execute([intval($_GET['leer'])]);
    redirect('contactos.php');
}

// Eliminar
if (isset($_GET['eliminar']) && isset($_GET['csrf']) && hash_equals(csrfToken(), $_GET['csrf'])) {
    $db->prepare("DELETE FROM contactos WHERE id = ?")->execute([intval($_GET['eliminar'])]);
    redirect('contactos.php');
}

// Obtener mensajes
$mensajes = $db->query("SELECT * FROM contactos ORDER BY leido ASC, created_at DESC")->fetchAll();

$mensajesNoLeidos = $db->query("SELECT COUNT(*) as total FROM contactos WHERE leido = 0")->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensajes - Admin InverCar</title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1>Mensajes de Contacto</h1>
                    <p><?php echo $mensajesNoLeidos; ?> mensaje(s) sin leer</p>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if (empty($mensajes)): ?>
                        <div class="empty-state">
                            <div class="icon">📨</div>
                            <p>No hay mensajes de contacto</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Estado</th>
                                        <th>Remitente</th>
                                        <th>Mensaje</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mensajes as $m): ?>
                                    <tr style="<?php echo !$m['leido'] ? 'background: rgba(13, 155, 92, 0.05);' : ''; ?>">
                                        <td>
                                            <?php if (!$m['leido']): ?>
                                                <span class="badge badge-warning">Nuevo</span>
                                            <?php else: ?>
                                                <span class="badge badge-info">Leído</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo escape($m['nombre']); ?></strong><br>
                                            <small style="color: var(--text-muted);"><?php echo escape($m['email']); ?></small>
                                            <?php if ($m['telefono']): ?>
                                                <br><small style="color: var(--text-muted);"><?php echo escape($m['telefono']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td style="max-width: 400px;">
                                            <p style="margin: 0;"><?php echo nl2br(escape($m['mensaje'])); ?></p>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($m['created_at'])); ?></td>
                                        <td>
                                            <div class="actions">
                                                <?php if (!$m['leido']): ?>
                                                    <a href="?leer=<?php echo $m['id']; ?>" class="btn btn-sm btn-primary">Marcar leído</a>
                                                <?php endif; ?>
                                                <a href="?eliminar=<?php echo $m['id']; ?>&csrf=<?php echo csrfToken(); ?>"
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('¿Eliminar este mensaje?');">Eliminar</a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
