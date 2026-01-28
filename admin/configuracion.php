<?php
/**
 * InverCar - Configuración del Sistema
 */
require_once __DIR__ . '/../includes/init.php';

if (!isAdminLogueado()) {
    redirect('login.php');
}

$db = getDB();
$error = '';
$exito = '';

// Guardar configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad.';
    } else {
        $action = $_POST['action'] ?? 'guardar_config';

        if ($action === 'guardar_config') {
            $campos = [
                'rentabilidad_fija' => floatval($_POST['rentabilidad_fija'] ?? 5),
                'rentabilidad_variable_actual' => floatval($_POST['rentabilidad_variable_actual'] ?? 14.8),
                'capital_reserva' => floatval($_POST['capital_reserva'] ?? 0),
                'nombre_empresa' => cleanInput($_POST['nombre_empresa'] ?? 'InverCar'),
                'email_empresa' => cleanInput($_POST['email_empresa'] ?? ''),
                'telefono_empresa' => cleanInput($_POST['telefono_empresa'] ?? ''),
            ];

            try {
                foreach ($campos as $clave => $valor) {
                    $stmt = $db->prepare("UPDATE configuracion SET valor = ? WHERE clave = ?");
                    $stmt->execute([$valor, $clave]);
                }
                $exito = 'Configuración guardada correctamente.';
            } catch (Exception $e) {
                $error = 'Error al guardar la configuración.';
            }
        } elseif ($action === 'crear_concepto') {
            $concepto = cleanInput($_POST['concepto'] ?? '');
            if (empty($concepto)) {
                $error = 'El concepto no puede estar vacío.';
            } elseif (strlen($concepto) > 50) {
                $error = 'El concepto no puede superar 50 caracteres.';
            } else {
                try {
                    $stmt = $db->prepare("INSERT INTO conceptos (concepto) VALUES (?)");
                    $stmt->execute([$concepto]);
                    $exito = 'Concepto creado correctamente.';
                } catch (Exception $e) {
                    $error = 'Error al crear el concepto.';
                }
            }
        } elseif ($action === 'editar_concepto') {
            $id = intval($_POST['id'] ?? 0);
            $concepto = cleanInput($_POST['concepto'] ?? '');
            if ($id > 0 && !empty($concepto) && strlen($concepto) <= 50) {
                $stmt = $db->prepare("UPDATE conceptos SET concepto = ? WHERE id = ?");
                $stmt->execute([$concepto, $id]);
                $exito = 'Concepto actualizado.';
            }
        } elseif ($action === 'eliminar_concepto') {
            $id = intval($_POST['id'] ?? 0);
            try {
                $stmt = $db->prepare("DELETE FROM conceptos WHERE id = ?");
                $stmt->execute([$id]);
                $exito = 'Concepto eliminado.';
            } catch (Exception $e) {
                $error = 'No se puede eliminar el concepto porque tiene apuntes asociados.';
            }
        } elseif ($action === 'toggle_concepto') {
            $id = intval($_POST['id'] ?? 0);
            $activo = intval($_POST['activo'] ?? 0);
            $db->prepare("UPDATE conceptos SET activo = ? WHERE id = ?")->execute([$activo, $id]);
            $exito = $activo ? 'Concepto activado.' : 'Concepto desactivado.';
        }
    }
}

// Obtener configuración actual
$config = [];
$stmt = $db->query("SELECT clave, valor FROM configuracion");
foreach ($stmt->fetchAll() as $row) {
    $config[$row['clave']] = $row['valor'];
}

// Obtener conceptos
$conceptos = $db->query("SELECT * FROM conceptos ORDER BY concepto")->fetchAll();

$mensajesNoLeidos = $db->query("SELECT COUNT(*) as total FROM contactos WHERE leido = 0")->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Admin InverCar</title>
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
                    <h1>Configuración</h1>
                    <p>Ajustes generales del sistema</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo escape($error); ?></div>
            <?php endif; ?>
            <?php if ($exito): ?>
                <div class="alert alert-success"><?php echo escape($exito); ?></div>
            <?php endif; ?>

            <form method="POST">
                <?php echo csrfField(); ?>

                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2>Rentabilidad</h2>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Rentabilidad Fija Mensual (%)</label>
                                <input type="number" name="rentabilidad_fija" step="0.01"
                                       value="<?php echo escape($config['rentabilidad_fija'] ?? 5); ?>">
                                <small style="color: var(--text-muted);">Porcentaje garantizado para inversores con rentabilidad fija</small>
                            </div>
                            <div class="form-group">
                                <label>Rentabilidad Variable Actual (%)</label>
                                <input type="number" name="rentabilidad_variable_actual" step="0.01"
                                       value="<?php echo escape($config['rentabilidad_variable_actual'] ?? 14.8); ?>">
                                <small style="color: var(--text-muted);">Rentabilidad actual mostrada en la landing page</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2>Capital</h2>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Capital en Reserva (€)</label>
                            <input type="number" name="capital_reserva" step="0.01"
                                   value="<?php echo escape($config['capital_reserva'] ?? 0); ?>">
                            <small style="color: var(--text-muted);">Fondos de reserva del sistema</small>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2>Datos de la Empresa</h2>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Nombre de la Empresa</label>
                            <input type="text" name="nombre_empresa"
                                   value="<?php echo escape($config['nombre_empresa'] ?? 'InverCar'); ?>">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Email de Contacto</label>
                                <input type="email" name="email_empresa"
                                       value="<?php echo escape($config['email_empresa'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Teléfono de Contacto</label>
                                <input type="text" name="telefono_empresa"
                                       value="<?php echo escape($config['telefono_empresa'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="action" value="guardar_config">
                <button type="submit" class="btn btn-primary">Guardar Configuración</button>
            </form>

            <!-- Gestión de Conceptos -->
            <div class="card" style="margin-top: 30px;">
                <div class="card-header">
                    <h2>Conceptos de Apuntes</h2>
                </div>
                <div class="card-body">
                    <p style="color: var(--text-muted); margin-bottom: 15px;">Los conceptos se utilizan para clasificar los apuntes contables.</p>

                    <!-- Añadir nuevo concepto -->
                    <form method="POST" style="display: flex; gap: 10px; margin-bottom: 20px;">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="crear_concepto">
                        <input type="text" name="concepto" placeholder="Nuevo concepto (máx. 50 caracteres)" maxlength="50" style="flex: 1;">
                        <button type="submit" class="btn btn-primary">Añadir</button>
                    </form>

                    <!-- Lista de conceptos -->
                    <?php if (empty($conceptos)): ?>
                        <p style="color: var(--text-muted);">No hay conceptos definidos.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Concepto</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($conceptos as $c): ?>
                                    <tr>
                                        <td><?php echo $c['id']; ?></td>
                                        <td>
                                            <form method="POST" style="display: flex; gap: 5px;">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="action" value="editar_concepto">
                                                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                                <input type="text" name="concepto" value="<?php echo escape($c['concepto']); ?>" maxlength="50" style="width: 200px;">
                                                <button type="submit" class="btn btn-sm btn-outline">Guardar</button>
                                            </form>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="action" value="toggle_concepto">
                                                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                                <input type="hidden" name="activo" value="<?php echo $c['activo'] ? 0 : 1; ?>">
                                                <button type="submit" class="badge <?php echo $c['activo'] ? 'badge-success' : 'badge-danger'; ?>" style="cursor: pointer; border: none;">
                                                    <?php echo $c['activo'] ? 'Activo' : 'Inactivo'; ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Eliminar este concepto? Solo es posible si no tiene apuntes asociados.');">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="action" value="eliminar_concepto">
                                                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                            </form>
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
