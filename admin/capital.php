<?php
/**
 * InverCar - Gestión de Capital por Cliente
 */
require_once __DIR__ . '/../includes/init.php';

if (!isAdminLogueado()) {
    redirect('login.php');
}

$db = getDB();
$error = '';
$exito = '';

// Obtener cliente
$clienteId = intval($_GET['cliente'] ?? 0);
if ($clienteId <= 0) {
    redirect('clientes.php');
}

$stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$clienteId]);
$cliente = $stmt->fetch();

if (!$cliente) {
    redirect('clientes.php');
}

// Obtener vehículos para el dropdown
$vehiculos = $db->query("SELECT id, matricula, marca, modelo, anio FROM vehiculos ORDER BY marca, modelo")->fetchAll();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'crear' || $action === 'editar') {
            $id = intval($_POST['id'] ?? 0);
            $fecha_ingreso = cleanInput($_POST['fecha_ingreso'] ?? '');
            $importe_ingresado = floatval($_POST['importe_ingresado'] ?? 0);
            $fecha_retirada = cleanInput($_POST['fecha_retirada'] ?? '') ?: null;
            $importe_retirado = floatval($_POST['importe_retirado'] ?? 0);
            $rentabilidad = floatval($_POST['rentabilidad'] ?? 0);
            $rentabilidad_porcentual = floatval($_POST['rentabilidad_porcentual'] ?? 0);
            $tipo_inversion = cleanInput($_POST['tipo_inversion'] ?? 'fija');
            $vehiculo_id = intval($_POST['vehiculo_id'] ?? 0) ?: null;
            $activo = intval($_POST['activo'] ?? 1);
            $notas = cleanInput($_POST['notas'] ?? '');

            // Validaciones
            if (empty($fecha_ingreso)) {
                $error = 'La fecha de ingreso es obligatoria.';
            } elseif ($importe_ingresado <= 0 && $importe_retirado <= 0) {
                $error = 'Debe indicar un importe de ingreso o retirada.';
            } elseif (!in_array($tipo_inversion, ['fija', 'variable'])) {
                $error = 'Tipo de inversión no válido.';
            } else {
                try {
                    if ($action === 'crear') {
                        $sql = "INSERT INTO capital (cliente_id, fecha_ingreso, importe_ingresado, fecha_retirada, importe_retirado, rentabilidad, rentabilidad_porcentual, tipo_inversion, vehiculo_id, activo, notas)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$clienteId, $fecha_ingreso, $importe_ingresado, $fecha_retirada, $importe_retirado, $rentabilidad, $rentabilidad_porcentual, $tipo_inversion, $vehiculo_id, $activo, $notas]);
                        $exito = 'Movimiento de capital creado correctamente.';
                    } else {
                        $sql = "UPDATE capital SET fecha_ingreso=?, importe_ingresado=?, fecha_retirada=?, importe_retirado=?, rentabilidad=?, rentabilidad_porcentual=?, tipo_inversion=?, vehiculo_id=?, activo=?, notas=? WHERE id=? AND cliente_id=?";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$fecha_ingreso, $importe_ingresado, $fecha_retirada, $importe_retirado, $rentabilidad, $rentabilidad_porcentual, $tipo_inversion, $vehiculo_id, $activo, $notas, $id, $clienteId]);
                        $exito = 'Movimiento actualizado correctamente.';
                    }
                } catch (Exception $e) {
                    $error = DEBUG_MODE ? $e->getMessage() : 'Error al guardar el movimiento.';
                }
            }
        } elseif ($action === 'eliminar') {
            $id = intval($_POST['id'] ?? 0);
            try {
                $stmt = $db->prepare("DELETE FROM capital WHERE id = ? AND cliente_id = ?");
                $stmt->execute([$id, $clienteId]);
                $exito = 'Movimiento eliminado correctamente.';
            } catch (Exception $e) {
                $error = 'Error al eliminar el movimiento.';
            }
        } elseif ($action === 'toggle_activo') {
            $id = intval($_POST['id'] ?? 0);
            $activo = intval($_POST['activo'] ?? 0);
            $db->prepare("UPDATE capital SET activo = ? WHERE id = ? AND cliente_id = ?")->execute([$activo, $id, $clienteId]);
            $exito = $activo ? 'Movimiento activado.' : 'Movimiento desactivado.';
        }
    }
}

// Obtener movimientos de capital del cliente
$stmt = $db->prepare("
    SELECT c.*, v.marca, v.modelo, v.matricula
    FROM capital c
    LEFT JOIN vehiculos v ON c.vehiculo_id = v.id
    WHERE c.cliente_id = ?
    ORDER BY c.fecha_ingreso DESC
");
$stmt->execute([$clienteId]);
$movimientos = $stmt->fetchAll();

// Calcular totales
$stmt = $db->prepare("
    SELECT
        tipo_inversion,
        SUM(importe_ingresado) as total_ingresado,
        SUM(importe_retirado) as total_retirado,
        SUM(rentabilidad) as total_rentabilidad
    FROM capital
    WHERE cliente_id = ? AND activo = 1
    GROUP BY tipo_inversion
");
$stmt->execute([$clienteId]);
$totales = [];
foreach ($stmt->fetchAll() as $row) {
    $totales[$row['tipo_inversion']] = $row;
}

$capitalFija = ($totales['fija']['total_ingresado'] ?? 0) - ($totales['fija']['total_retirado'] ?? 0);
$capitalVariable = ($totales['variable']['total_ingresado'] ?? 0) - ($totales['variable']['total_retirado'] ?? 0);
$capitalTotal = $capitalFija + $capitalVariable;
$rentabilidadTotal = ($totales['fija']['total_rentabilidad'] ?? 0) + ($totales['variable']['total_rentabilidad'] ?? 0);

// Movimiento a editar
$movimientoEditar = null;
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM capital WHERE id = ? AND cliente_id = ?");
    $stmt->execute([intval($_GET['editar']), $clienteId]);
    $movimientoEditar = $stmt->fetch();
}

$mensajesNoLeidos = $db->query("SELECT COUNT(*) as total FROM contactos WHERE leido = 0")->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Capital de <?php echo escape($cliente['nombre']); ?> - Admin InverCar</title>
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
                    <h1>Capital: <?php echo escape($cliente['nombre'] . ' ' . $cliente['apellidos']); ?></h1>
                    <p>Gestión de entradas y salidas de capital</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="clientes.php?ver=<?php echo $clienteId; ?>" class="btn btn-outline">Volver a Cliente</a>
                    <button class="btn btn-primary" onclick="document.getElementById('modalCapital').classList.add('active')">
                        + Añadir Movimiento
                    </button>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo escape($error); ?></div>
            <?php endif; ?>
            <?php if ($exito): ?>
                <div class="alert alert-success"><?php echo escape($exito); ?></div>
            <?php endif; ?>

            <!-- Resumen de Capital -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--gold), #c9a227);">$</div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo formatMoney($capitalTotal); ?></span>
                        <span class="stat-label">Capital Total</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">F</div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo formatMoney($capitalFija); ?></span>
                        <span class="stat-label">Capital Fija</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">V</div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo formatMoney($capitalVariable); ?></span>
                        <span class="stat-label">Capital Variable</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">%</div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo formatMoney($rentabilidadTotal); ?></span>
                        <span class="stat-label">Rentabilidad Total</span>
                    </div>
                </div>
            </div>

            <!-- Lista de Movimientos -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($movimientos)): ?>
                        <div class="empty-state">
                            <div class="icon">$</div>
                            <p>No hay movimientos de capital registrados</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Fecha Ingreso</th>
                                        <th>Ingreso</th>
                                        <th>Fecha Retirada</th>
                                        <th>Retirada</th>
                                        <th>Tipo</th>
                                        <th>Vehículo</th>
                                        <th>Rentabilidad</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($movimientos as $m): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($m['fecha_ingreso'])); ?></td>
                                        <td style="color: var(--success); font-weight: bold;">
                                            <?php echo $m['importe_ingresado'] > 0 ? '+' . formatMoney($m['importe_ingresado']) : '-'; ?>
                                        </td>
                                        <td><?php echo $m['fecha_retirada'] ? date('d/m/Y', strtotime($m['fecha_retirada'])) : '-'; ?></td>
                                        <td style="color: var(--danger); font-weight: bold;">
                                            <?php echo $m['importe_retirado'] > 0 ? '-' . formatMoney($m['importe_retirado']) : '-'; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $m['tipo_inversion'] === 'fija' ? 'badge-info' : 'badge-success'; ?>">
                                                <?php echo ucfirst($m['tipo_inversion']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($m['vehiculo_id']): ?>
                                                <?php echo escape($m['marca'] . ' ' . $m['modelo']); ?>
                                                <?php if ($m['matricula']): ?><br><small><?php echo escape($m['matricula']); ?></small><?php endif; ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($m['rentabilidad'] > 0): ?>
                                                <span style="color: var(--gold);"><?php echo formatMoney($m['rentabilidad']); ?></span>
                                                <br><small>(<?php echo formatPercent($m['rentabilidad_porcentual']); ?>)</small>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $m['activo'] ? 'badge-success' : 'badge-danger'; ?>">
                                                <?php echo $m['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <a href="?cliente=<?php echo $clienteId; ?>&editar=<?php echo $m['id']; ?>" class="btn btn-sm btn-outline">Editar</a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Eliminar este movimiento?');">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="action" value="eliminar">
                                                    <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                                </form>
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

    <!-- Modal Crear/Editar Movimiento -->
    <div class="modal-overlay <?php echo ($movimientoEditar || isset($_GET['crear'])) ? 'active' : ''; ?>" id="modalCapital">
        <div class="modal" style="max-width: 900px;">
            <div class="modal-header">
                <h3><?php echo $movimientoEditar ? 'Editar Movimiento' : 'Añadir Movimiento de Capital'; ?></h3>
                <a href="?cliente=<?php echo $clienteId; ?>" class="modal-close">&times;</a>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="<?php echo $movimientoEditar ? 'editar' : 'crear'; ?>">
                    <?php if ($movimientoEditar): ?>
                        <input type="hidden" name="id" value="<?php echo $movimientoEditar['id']; ?>">
                    <?php endif; ?>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Fecha Ingreso *</label>
                            <input type="date" name="fecha_ingreso" required
                                   value="<?php echo escape($movimientoEditar['fecha_ingreso'] ?? date('Y-m-d')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Importe Ingresado (€)</label>
                            <input type="number" name="importe_ingresado" step="0.01" min="0"
                                   value="<?php echo escape($movimientoEditar['importe_ingresado'] ?? '0'); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Fecha Retirada</label>
                            <input type="date" name="fecha_retirada"
                                   value="<?php echo escape($movimientoEditar['fecha_retirada'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Importe Retirado (€)</label>
                            <input type="number" name="importe_retirado" step="0.01" min="0"
                                   value="<?php echo escape($movimientoEditar['importe_retirado'] ?? '0'); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Tipo de Inversión *</label>
                            <select name="tipo_inversion" required>
                                <option value="fija" <?php echo ($movimientoEditar['tipo_inversion'] ?? '') === 'fija' ? 'selected' : ''; ?>>Fija</option>
                                <option value="variable" <?php echo ($movimientoEditar['tipo_inversion'] ?? '') === 'variable' ? 'selected' : ''; ?>>Variable</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Vehículo Asociado</label>
                            <select name="vehiculo_id">
                                <option value="">-- Sin vehículo --</option>
                                <?php foreach ($vehiculos as $v): ?>
                                    <option value="<?php echo $v['id']; ?>" <?php echo ($movimientoEditar['vehiculo_id'] ?? '') == $v['id'] ? 'selected' : ''; ?>>
                                        <?php echo escape($v['marca'] . ' ' . $v['modelo'] . ' (' . $v['anio'] . ')'); ?>
                                        <?php if ($v['matricula']): ?> - <?php echo escape($v['matricula']); ?><?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Rentabilidad (€)</label>
                            <input type="number" name="rentabilidad" step="0.01"
                                   value="<?php echo escape($movimientoEditar['rentabilidad'] ?? '0'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Rentabilidad (%)</label>
                            <input type="number" name="rentabilidad_porcentual" step="0.01"
                                   value="<?php echo escape($movimientoEditar['rentabilidad_porcentual'] ?? '0'); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Estado</label>
                            <select name="activo">
                                <option value="1" <?php echo ($movimientoEditar['activo'] ?? 1) == 1 ? 'selected' : ''; ?>>Activo (se computa)</option>
                                <option value="0" <?php echo ($movimientoEditar['activo'] ?? 1) == 0 ? 'selected' : ''; ?>>Inactivo (no se computa)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Notas</label>
                        <textarea name="notas" rows="2"><?php echo escape($movimientoEditar['notas'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="?cliente=<?php echo $clienteId; ?>" class="btn btn-outline">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><?php echo $movimientoEditar ? 'Guardar cambios' : 'Crear movimiento'; ?></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('modalCapital').addEventListener('click', function(e) {
            if (e.target === this) {
                window.location.href = '?cliente=<?php echo $clienteId; ?>';
            }
        });
    </script>
</body>
</html>
