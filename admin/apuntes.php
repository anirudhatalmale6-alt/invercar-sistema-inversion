<?php
/**
 * InverCar - Gestión de Apuntes Contables
 */
require_once __DIR__ . '/../includes/init.php';

if (!isAdminLogueado()) {
    redirect('login.php');
}

$db = getDB();
$error = '';
$exito = '';

// Obtener conceptos para el dropdown (ordenados por el orden definido por el usuario)
$conceptos = $db->query("SELECT id, concepto, tipologia FROM conceptos WHERE activo = 1 ORDER BY orden ASC, id ASC")->fetchAll();

// Identificar IDs de conceptos por tipología para validación
$conceptosCapital = [];
$conceptosGastoVehiculo = [];
foreach ($conceptos as $c) {
    $conceptoLower = strtolower($c['concepto']);
    // Conceptos de capital (requieren cliente)
    if (strpos($conceptoLower, 'ingreso de capital') !== false || strpos($conceptoLower, 'retirada de capital') !== false) {
        $conceptosCapital[$c['id']] = $c['concepto'];
    }
    // Conceptos de gasto vehículo (requieren vehículo y suman a gastos)
    if (($c['tipologia'] ?? 'gasto') === 'gasto_vehiculo') {
        $conceptosGastoVehiculo[$c['id']] = $c['concepto'];
    }
}

// Obtener clientes para el dropdown
$clientes = $db->query("SELECT id, nombre, apellidos FROM clientes WHERE activo = 1 ORDER BY nombre, apellidos")->fetchAll();

// Obtener vehículos para el dropdown
$vehiculos = $db->query("SELECT id, referencia, matricula, marca, modelo, anio FROM vehiculos ORDER BY marca, modelo")->fetchAll();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'crear' || $action === 'editar') {
            $id = intval($_POST['id'] ?? 0);
            $fecha = cleanInput($_POST['fecha'] ?? '');
            $concepto_id = intval($_POST['concepto_id'] ?? 0);
            $descripcion = cleanInput($_POST['descripcion'] ?? '');
            $cliente_id = intval($_POST['cliente_id'] ?? 0) ?: null;
            $vehiculo_id = intval($_POST['vehiculo_id'] ?? 0) ?: null;
            $importe = floatval($_POST['importe'] ?? 0);
            $tipo_apunte = cleanInput($_POST['tipo_apunte'] ?? 'D');
            $realizado = intval($_POST['realizado'] ?? 0);
            $activo = intval($_POST['activo'] ?? 1);

            // Validaciones
            $esConceptoCapital = isset($conceptosCapital[$concepto_id]);
            $esGastoVehiculo = isset($conceptosGastoVehiculo[$concepto_id]);

            if (empty($fecha)) {
                $error = 'La fecha es obligatoria.';
            } elseif ($concepto_id <= 0) {
                $error = 'Debe seleccionar un concepto.';
            } elseif (!in_array($tipo_apunte, ['D', 'H'])) {
                $error = 'Tipo de apunte no válido.';
            } elseif ($esConceptoCapital && empty($cliente_id)) {
                $error = 'Para Ingreso/Retirada de capital debe seleccionar un cliente.';
            } elseif ($esGastoVehiculo && empty($vehiculo_id)) {
                $error = 'Para Gasto de Vehículo debe seleccionar un vehículo.';
            } else {
                try {
                    if ($action === 'crear') {
                        $sql = "INSERT INTO apuntes (fecha, concepto_id, descripcion, cliente_id, vehiculo_id, importe, tipo_apunte, realizado, activo)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$fecha, $concepto_id, $descripcion, $cliente_id, $vehiculo_id, $importe, $tipo_apunte, $realizado, $activo]);
                        $nuevoApunteId = $db->lastInsertId();

                        // Obtener tipología del concepto
                        $stmtTip = $db->prepare("SELECT tipologia FROM conceptos WHERE id = ?");
                        $stmtTip->execute([$concepto_id]);
                        $tipologia = $stmtTip->fetchColumn() ?: 'gasto';

                        $mensajeExtra = '';

                        // Si el apunte tiene cliente, crear registro de capital
                        // Para conceptos de capital específicos (ingreso/retirada)
                        if ($esConceptoCapital && $cliente_id) {
                            $conceptoTexto = strtolower($conceptosCapital[$concepto_id]);
                            $esIngreso = strpos($conceptoTexto, 'ingreso') !== false;

                            $sqlCap = "INSERT INTO capital (apunte_id, fecha_ingreso, cliente_id, importe_ingresado, importe_retirado, tipo_inversion, vehiculo_id, activo, notas)
                                       VALUES (?, ?, ?, ?, ?, 'variable', ?, 1, ?)";
                            $stmtCap = $db->prepare($sqlCap);
                            if ($esIngreso) {
                                $stmtCap->execute([$nuevoApunteId, $fecha, $cliente_id, $importe, 0, $vehiculo_id, 'Creado desde Apunte: ' . $descripcion]);
                            } else {
                                $stmtCap->execute([$nuevoApunteId, $fecha, $cliente_id, 0, $importe, $vehiculo_id, 'Creado desde Apunte: ' . $descripcion]);
                            }
                            $mensajeExtra .= ' Se ha creado registro de capital.';
                        }
                        // Para otros apuntes con cliente y tipología ingreso, sumar al capital del cliente
                        elseif ($cliente_id && $tipologia === 'ingreso' && !$esConceptoCapital) {
                            $sqlCap = "INSERT INTO capital (apunte_id, fecha_ingreso, cliente_id, importe_ingresado, importe_retirado, tipo_inversion, vehiculo_id, activo, notas)
                                       VALUES (?, ?, ?, ?, 0, 'variable', ?, 1, ?)";
                            $stmtCap = $db->prepare($sqlCap);
                            $stmtCap->execute([$nuevoApunteId, $fecha, $cliente_id, $importe, $vehiculo_id, 'Ingreso desde Apunte: ' . $descripcion]);
                            $mensajeExtra .= ' Se ha sumado al capital del cliente.';
                        }

                        // Si es gasto de vehículo, sumar a los gastos reales del vehículo
                        if ($esGastoVehiculo && $vehiculo_id) {
                            $db->prepare("UPDATE vehiculos SET gastos = gastos + ? WHERE id = ?")->execute([$importe, $vehiculo_id]);
                            $mensajeExtra .= ' Se ha actualizado el gasto del vehículo.';
                        }
                        $exito = 'Apunte creado correctamente.' . $mensajeExtra;
                    } else {
                        // Obtener datos anteriores del apunte para ajustar gastos de vehículo y capital si es necesario
                        $stmtOld = $db->prepare("SELECT a.*, c.tipologia FROM apuntes a LEFT JOIN conceptos c ON a.concepto_id = c.id WHERE a.id = ?");
                        $stmtOld->execute([$id]);
                        $apunteAnterior = $stmtOld->fetch();

                        // Obtener tipología del nuevo concepto
                        $stmtTip = $db->prepare("SELECT tipologia FROM conceptos WHERE id = ?");
                        $stmtTip->execute([$concepto_id]);
                        $tipologia = $stmtTip->fetchColumn() ?: 'gasto';

                        $mensajeExtra = '';

                        // Si el apunte anterior era gasto_vehiculo, restar del vehículo anterior
                        if ($apunteAnterior && ($apunteAnterior['tipologia'] ?? '') === 'gasto_vehiculo' && $apunteAnterior['vehiculo_id']) {
                            $db->prepare("UPDATE vehiculos SET gastos = gastos - ? WHERE id = ?")->execute([$apunteAnterior['importe'], $apunteAnterior['vehiculo_id']]);
                        }

                        // Eliminar registro de capital anterior vinculado a este apunte (si existe)
                        $db->prepare("DELETE FROM capital WHERE apunte_id = ?")->execute([$id]);

                        // Actualizar el apunte
                        $sql = "UPDATE apuntes SET fecha=?, concepto_id=?, descripcion=?, cliente_id=?, vehiculo_id=?, importe=?, tipo_apunte=?, realizado=?, activo=? WHERE id=?";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$fecha, $concepto_id, $descripcion, $cliente_id, $vehiculo_id, $importe, $tipo_apunte, $realizado, $activo, $id]);

                        // Si el nuevo apunte es gasto_vehiculo, sumar al nuevo vehículo
                        if ($esGastoVehiculo && $vehiculo_id) {
                            $db->prepare("UPDATE vehiculos SET gastos = gastos + ? WHERE id = ?")->execute([$importe, $vehiculo_id]);
                            $mensajeExtra .= ' Se ha actualizado el gasto del vehículo.';
                        }

                        // Crear nuevo registro de capital si aplica
                        if ($esConceptoCapital && $cliente_id) {
                            $conceptoTexto = strtolower($conceptosCapital[$concepto_id]);
                            $esIngreso = strpos($conceptoTexto, 'ingreso') !== false;

                            $sqlCap = "INSERT INTO capital (apunte_id, fecha_ingreso, cliente_id, importe_ingresado, importe_retirado, tipo_inversion, vehiculo_id, activo, notas)
                                       VALUES (?, ?, ?, ?, ?, 'variable', ?, 1, ?)";
                            $stmtCap = $db->prepare($sqlCap);
                            if ($esIngreso) {
                                $stmtCap->execute([$id, $fecha, $cliente_id, $importe, 0, $vehiculo_id, 'Creado desde Apunte: ' . $descripcion]);
                            } else {
                                $stmtCap->execute([$id, $fecha, $cliente_id, 0, $importe, $vehiculo_id, 'Creado desde Apunte: ' . $descripcion]);
                            }
                            $mensajeExtra .= ' Se ha actualizado el registro de capital.';
                        } elseif ($cliente_id && $tipologia === 'ingreso' && !$esConceptoCapital) {
                            $sqlCap = "INSERT INTO capital (apunte_id, fecha_ingreso, cliente_id, importe_ingresado, importe_retirado, tipo_inversion, vehiculo_id, activo, notas)
                                       VALUES (?, ?, ?, ?, 0, 'variable', ?, 1, ?)";
                            $stmtCap = $db->prepare($sqlCap);
                            $stmtCap->execute([$id, $fecha, $cliente_id, $importe, $vehiculo_id, 'Ingreso desde Apunte: ' . $descripcion]);
                            $mensajeExtra .= ' Se ha actualizado el capital del cliente.';
                        }

                        $exito = 'Apunte actualizado correctamente.' . $mensajeExtra;
                    }
                } catch (Exception $e) {
                    $error = DEBUG_MODE ? $e->getMessage() : 'Error al guardar el apunte.';
                }
            }
        } elseif ($action === 'eliminar') {
            $id = intval($_POST['id'] ?? 0);
            try {
                // Obtener datos del apunte antes de eliminar para ajustar gastos si es necesario
                $stmtOld = $db->prepare("SELECT a.*, c.tipologia FROM apuntes a LEFT JOIN conceptos c ON a.concepto_id = c.id WHERE a.id = ?");
                $stmtOld->execute([$id]);
                $apunteAEliminar = $stmtOld->fetch();

                $mensajeExtra = '';

                // Si era gasto_vehiculo, restar del vehículo
                if ($apunteAEliminar && ($apunteAEliminar['tipologia'] ?? '') === 'gasto_vehiculo' && $apunteAEliminar['vehiculo_id']) {
                    $db->prepare("UPDATE vehiculos SET gastos = gastos - ? WHERE id = ?")->execute([$apunteAEliminar['importe'], $apunteAEliminar['vehiculo_id']]);
                    $mensajeExtra .= ' Se ha revertido el gasto del vehículo.';
                }

                // Eliminar registro de capital vinculado a este apunte (si existe)
                $stmtCapital = $db->prepare("DELETE FROM capital WHERE apunte_id = ?");
                $stmtCapital->execute([$id]);
                if ($stmtCapital->rowCount() > 0) {
                    $mensajeExtra .= ' Se ha eliminado el registro de capital asociado.';
                }

                $stmt = $db->prepare("DELETE FROM apuntes WHERE id = ?");
                $stmt->execute([$id]);
                $exito = 'Apunte eliminado correctamente.' . $mensajeExtra;
            } catch (Exception $e) {
                $error = 'Error al eliminar el apunte.';
            }
        } elseif ($action === 'toggle_realizado') {
            $id = intval($_POST['id'] ?? 0);
            $realizado = intval($_POST['realizado'] ?? 0);
            $db->prepare("UPDATE apuntes SET realizado = ? WHERE id = ?")->execute([$realizado, $id]);
            $exito = $realizado ? 'Apunte marcado como realizado.' : 'Apunte marcado como pendiente.';
        }
    }
}

// Filtros
$filtroCliente = intval($_GET['cliente'] ?? 0);
$filtroVehiculo = intval($_GET['vehiculo'] ?? 0);
$filtroTipo = cleanInput($_GET['tipo'] ?? '');
$filtroRealizado = isset($_GET['realizado']) ? intval($_GET['realizado']) : -1;

// Obtener apuntes con filtros
$sql = "
    SELECT a.*, c.concepto, cl.nombre as cliente_nombre, cl.apellidos as cliente_apellidos,
           v.marca, v.modelo, v.matricula
    FROM apuntes a
    LEFT JOIN conceptos c ON a.concepto_id = c.id
    LEFT JOIN clientes cl ON a.cliente_id = cl.id
    LEFT JOIN vehiculos v ON a.vehiculo_id = v.id
    WHERE a.activo = 1
";
$params = [];

if ($filtroCliente > 0) {
    $sql .= " AND a.cliente_id = ?";
    $params[] = $filtroCliente;
}
if ($filtroVehiculo > 0) {
    $sql .= " AND a.vehiculo_id = ?";
    $params[] = $filtroVehiculo;
}
if ($filtroTipo === 'D' || $filtroTipo === 'H') {
    $sql .= " AND a.tipo_apunte = ?";
    $params[] = $filtroTipo;
}
if ($filtroRealizado === 0 || $filtroRealizado === 1) {
    $sql .= " AND a.realizado = ?";
    $params[] = $filtroRealizado;
}

$sql .= " ORDER BY a.fecha DESC, a.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$apuntes = $stmt->fetchAll();

// Calcular totales
$totalDebe = 0;
$totalHaber = 0;
foreach ($apuntes as $a) {
    if ($a['tipo_apunte'] === 'D') {
        $totalDebe += $a['importe'];
    } else {
        $totalHaber += $a['importe'];
    }
}
$saldo = $totalDebe - $totalHaber;

// Apunte a editar
$apunteEditar = null;
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM apuntes WHERE id = ?");
    $stmt->execute([intval($_GET['editar'])]);
    $apunteEditar = $stmt->fetch();
}

$mensajesNoLeidos = $db->query("SELECT COUNT(*) as total FROM contactos WHERE leido = 0")->fetch()['total'];

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="apuntes_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Header
    fputcsv($output, ['Fecha', 'Concepto', 'Descripcion', 'Cliente', 'Vehiculo', 'Debe', 'Haber', 'Estado'], ';');

    // Data
    foreach ($apuntes as $a) {
        $cliente = $a['cliente_id'] ? ($a['cliente_nombre'] . ' ' . $a['cliente_apellidos']) : '';
        $vehiculo = $a['vehiculo_id'] ? ($a['marca'] . ' ' . $a['modelo']) : '';
        $debe = $a['tipo_apunte'] === 'D' ? number_format($a['importe'], 2, ',', '') : '';
        $haber = $a['tipo_apunte'] === 'H' ? number_format($a['importe'], 2, ',', '') : '';
        $estado = $a['realizado'] ? 'Realizado' : 'Pendiente';

        fputcsv($output, [
            date('d/m/Y', strtotime($a['fecha'])),
            $a['concepto'],
            $a['descripcion'],
            $cliente,
            $vehiculo,
            $debe,
            $haber,
            $estado
        ], ';');
    }

    // Totals
    fputcsv($output, ['', '', '', '', 'TOTAL', number_format($totalDebe, 2, ',', ''), number_format($totalHaber, 2, ',', ''), ''], ';');
    fputcsv($output, ['', '', '', '', 'SALDO', '', number_format($saldo, 2, ',', ''), ''], ';');

    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apuntes - Admin InverCar</title>
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
                    <h1>Apuntes Contables</h1>
                    <p>Registro de movimientos y operaciones</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="?export=csv<?php echo $filtroCliente ? '&cliente='.$filtroCliente : ''; ?><?php echo $filtroVehiculo ? '&vehiculo='.$filtroVehiculo : ''; ?><?php echo $filtroTipo ? '&tipo='.$filtroTipo : ''; ?><?php echo $filtroRealizado >= 0 ? '&realizado='.$filtroRealizado : ''; ?>" class="btn btn-outline">
                        Exportar CSV
                    </a>
                    <button class="btn btn-primary" onclick="document.getElementById('modalApunte').classList.add('active')">
                        + Añadir Apunte
                    </button>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo escape($error); ?></div>
            <?php endif; ?>
            <?php if ($exito): ?>
                <div class="alert alert-success"><?php echo escape($exito); ?></div>
            <?php endif; ?>

            <!-- Resumen -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px;">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">D</div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo formatMoney($totalDebe); ?></span>
                        <span class="stat-label">Total Debe</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">H</div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo formatMoney($totalHaber); ?></span>
                        <span class="stat-label">Total Haber</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--gold), #c9a227);">=</div>
                    <div class="stat-info">
                        <span class="stat-value" style="color: <?php echo $saldo >= 0 ? 'var(--success)' : 'var(--danger)'; ?>">
                            <?php echo formatMoney($saldo); ?>
                        </span>
                        <span class="stat-label">Saldo</span>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-body" style="padding: 15px;">
                    <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                        <div class="form-group" style="margin-bottom: 0; min-width: 180px;">
                            <label>Cliente</label>
                            <select name="cliente">
                                <option value="">-- Todos --</option>
                                <?php foreach ($clientes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $filtroCliente == $c['id'] ? 'selected' : ''; ?>>
                                        <?php echo escape($c['nombre'] . ' ' . $c['apellidos']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0; min-width: 220px;">
                            <label>Vehículo</label>
                            <select name="vehiculo">
                                <option value="">-- Todos --</option>
                                <?php foreach ($vehiculos as $v): ?>
                                    <option value="<?php echo $v['id']; ?>" <?php echo $filtroVehiculo == $v['id'] ? 'selected' : ''; ?>>
                                        <?php echo escape($v['marca'] . ' ' . $v['modelo']); ?><?php if ($v['referencia']): ?> · <?php echo escape($v['referencia']); ?><?php endif; ?><?php if ($v['matricula']): ?> · <?php echo escape($v['matricula']); ?><?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0; min-width: 120px;">
                            <label>Tipo</label>
                            <select name="tipo">
                                <option value="">-- Todos --</option>
                                <option value="D" <?php echo $filtroTipo === 'D' ? 'selected' : ''; ?>>Debe</option>
                                <option value="H" <?php echo $filtroTipo === 'H' ? 'selected' : ''; ?>>Haber</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0; min-width: 120px;">
                            <label>Estado</label>
                            <select name="realizado">
                                <option value="-1">-- Todos --</option>
                                <option value="0" <?php echo $filtroRealizado === 0 ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="1" <?php echo $filtroRealizado === 1 ? 'selected' : ''; ?>>Realizado</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <?php if ($filtroCliente || $filtroVehiculo || $filtroTipo || $filtroRealizado >= 0): ?>
                            <a href="apuntes.php" class="btn btn-outline">Limpiar</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Lista de Apuntes -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($apuntes)): ?>
                        <div class="empty-state">
                            <div class="icon">$</div>
                            <p>No hay apuntes registrados</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Concepto</th>
                                        <th>Descripción</th>
                                        <th>Cliente</th>
                                        <th>Vehículo</th>
                                        <th>Debe</th>
                                        <th>Haber</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($apuntes as $a): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($a['fecha'])); ?></td>
                                        <td><strong><?php echo escape($a['concepto']); ?></strong></td>
                                        <td><?php echo escape($a['descripcion'] ?: '-'); ?></td>
                                        <td>
                                            <?php if ($a['cliente_id']): ?>
                                                <?php echo escape($a['cliente_nombre'] . ' ' . $a['cliente_apellidos']); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($a['vehiculo_id']): ?>
                                                <?php echo escape($a['marca'] . ' ' . $a['modelo']); ?>
                                                <?php if ($a['matricula']): ?><br><small><?php echo escape($a['matricula']); ?></small><?php endif; ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: var(--success); font-weight: bold;">
                                            <?php echo $a['tipo_apunte'] === 'D' ? formatMoney($a['importe']) : ''; ?>
                                        </td>
                                        <td style="color: var(--danger); font-weight: bold;">
                                            <?php echo $a['tipo_apunte'] === 'H' ? formatMoney($a['importe']) : ''; ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="action" value="toggle_realizado">
                                                <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                                <input type="hidden" name="realizado" value="<?php echo $a['realizado'] ? 0 : 1; ?>">
                                                <button type="submit" class="badge <?php echo $a['realizado'] ? 'badge-success' : 'badge-warning'; ?>" style="cursor: pointer; border: none;">
                                                    <?php echo $a['realizado'] ? 'Realizado' : 'Pendiente'; ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <a href="?editar=<?php echo $a['id']; ?><?php echo $filtroCliente ? '&cliente='.$filtroCliente : ''; ?><?php echo $filtroVehiculo ? '&vehiculo='.$filtroVehiculo : ''; ?>" class="btn btn-sm btn-outline">Editar</a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Eliminar este apunte?');">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="action" value="eliminar">
                                                    <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
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

    <!-- Modal Crear/Editar Apunte -->
    <div class="modal-overlay <?php echo ($apunteEditar || isset($_GET['crear'])) ? 'active' : ''; ?>" id="modalApunte">
        <div class="modal" style="max-width: 900px;">
            <div class="modal-header">
                <h3><?php echo $apunteEditar ? 'Editar Apunte' : 'Añadir Apunte'; ?></h3>
                <a href="apuntes.php" class="modal-close">&times;</a>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="<?php echo $apunteEditar ? 'editar' : 'crear'; ?>">
                    <?php if ($apunteEditar): ?>
                        <input type="hidden" name="id" value="<?php echo $apunteEditar['id']; ?>">
                    <?php endif; ?>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Fecha *</label>
                            <input type="date" name="fecha" required
                                   value="<?php echo escape($apunteEditar['fecha'] ?? date('Y-m-d')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Concepto *</label>
                            <select name="concepto_id" id="concepto_id" required>
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($conceptos as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo ($apunteEditar['concepto_id'] ?? '') == $c['id'] ? 'selected' : ''; ?>>
                                        <?php echo escape($c['concepto']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Descripción</label>
                        <input type="text" name="descripcion" id="descripcion" maxlength="200"
                               value="<?php echo escape($apunteEditar['descripcion'] ?? ''); ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Cliente</label>
                            <select name="cliente_id">
                                <option value="">-- Sin cliente --</option>
                                <?php foreach ($clientes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo ($apunteEditar['cliente_id'] ?? '') == $c['id'] ? 'selected' : ''; ?>>
                                        <?php echo escape($c['nombre'] . ' ' . $c['apellidos']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Vehículo</label>
                            <select name="vehiculo_id">
                                <option value="">-- Sin vehículo --</option>
                                <?php foreach ($vehiculos as $v): ?>
                                    <option value="<?php echo $v['id']; ?>" <?php echo ($apunteEditar['vehiculo_id'] ?? '') == $v['id'] ? 'selected' : ''; ?>>
                                        <?php echo escape($v['marca'] . ' ' . $v['modelo']); ?><?php if ($v['referencia']): ?> · <?php echo escape($v['referencia']); ?><?php endif; ?><?php if ($v['matricula']): ?> · <?php echo escape($v['matricula']); ?><?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Importe (€) *</label>
                            <input type="number" name="importe" step="0.01" min="0" required
                                   value="<?php echo escape($apunteEditar['importe'] ?? '0'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Tipo de Apunte *</label>
                            <select name="tipo_apunte" required>
                                <option value="D" <?php echo ($apunteEditar['tipo_apunte'] ?? 'D') === 'D' ? 'selected' : ''; ?>>Debe (Entrada)</option>
                                <option value="H" <?php echo ($apunteEditar['tipo_apunte'] ?? '') === 'H' ? 'selected' : ''; ?>>Haber (Salida)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Realizado</label>
                            <select name="realizado">
                                <option value="0" <?php echo ($apunteEditar['realizado'] ?? 0) == 0 ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="1" <?php echo ($apunteEditar['realizado'] ?? 0) == 1 ? 'selected' : ''; ?>>Realizado</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Estado</label>
                            <select name="activo">
                                <option value="1" <?php echo ($apunteEditar['activo'] ?? 1) == 1 ? 'selected' : ''; ?>>Activo</option>
                                <option value="0" <?php echo ($apunteEditar['activo'] ?? 1) == 0 ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="apuntes.php" class="btn btn-outline">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><?php echo $apunteEditar ? 'Guardar cambios' : 'Crear apunte'; ?></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('modalApunte').addEventListener('click', function(e) {
            if (e.target === this) {
                window.location.href = 'apuntes.php';
            }
        });

        // IDs de conceptos de capital (requieren cliente)
        var conceptosCapitalIds = <?php echo json_encode(array_keys($conceptosCapital)); ?>;
        // IDs de conceptos de gasto vehículo (requieren vehículo)
        var conceptosGastoVehiculoIds = <?php echo json_encode(array_keys($conceptosGastoVehiculo)); ?>;

        // Validar cliente/vehículo cuando se selecciona concepto
        document.querySelector('select[name="concepto_id"]').addEventListener('change', function() {
            var conceptoId = parseInt(this.value);
            var clienteSelect = document.querySelector('select[name="cliente_id"]');
            var clienteLabel = clienteSelect.previousElementSibling;
            var vehiculoSelect = document.querySelector('select[name="vehiculo_id"]');
            var vehiculoLabel = vehiculoSelect.previousElementSibling;
            var descripcionInput = document.getElementById('descripcion');

            // Auto-rellenar descripción con el texto del concepto seleccionado (solo si está vacío)
            if (this.value && descripcionInput.value.trim() === '') {
                descripcionInput.value = this.options[this.selectedIndex].text;
            }

            // Cliente requerido para conceptos de capital
            if (conceptosCapitalIds.includes(conceptoId)) {
                clienteSelect.required = true;
                clienteLabel.innerHTML = 'Cliente <span style="color: var(--danger);">* (obligatorio para capital)</span>';
            } else {
                clienteSelect.required = false;
                clienteLabel.innerHTML = 'Cliente';
            }

            // Vehículo requerido para conceptos de gasto vehículo
            if (conceptosGastoVehiculoIds.includes(conceptoId)) {
                vehiculoSelect.required = true;
                vehiculoLabel.innerHTML = 'Vehículo <span style="color: var(--danger);">* (obligatorio - se sumará a gastos)</span>';
            } else {
                vehiculoSelect.required = false;
                vehiculoLabel.innerHTML = 'Vehículo';
            }
        });

        // Trigger on load if editing
        document.querySelector('select[name="concepto_id"]').dispatchEvent(new Event('change'));
    </script>
</body>
</html>
