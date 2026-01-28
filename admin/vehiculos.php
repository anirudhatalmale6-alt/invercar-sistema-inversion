<?php
/**
 * InverCar - Gestión de Vehículos
 */
require_once __DIR__ . '/../includes/init.php';

if (!isAdminLogueado()) {
    redirect('login.php');
}

$db = getDB();
$error = '';
$exito = '';

// Exportar CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filtro = cleanInput($_GET['filtro'] ?? '');
    $sql = "SELECT * FROM vehiculos";
    $params = [];

    if ($filtro) {
        $sql .= " WHERE marca LIKE ? OR modelo LIKE ?";
        $params = ["%$filtro%", "%$filtro%"];
    }

    $sql .= " ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $vehiculos = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="vehiculos_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Header
    fputcsv($output, ['ID', 'Matrícula', 'Marca', 'Modelo', 'Versión', 'Año', 'Kilómetros', 'Precio Compra', 'Prev. Gastos', 'Gastos Reales', 'Venta Prevista', 'Venta Real', 'Beneficio', 'Estado', 'Fecha Compra', 'Fecha Venta', 'Notas'], ';');

    $estadoTexto = [
        'en_estudio' => 'En Estudio',
        'en_preparacion' => 'En Preparación',
        'en_venta' => 'En Venta',
        'reservado' => 'Reservado',
        'vendido' => 'Vendido'
    ];

    foreach ($vehiculos as $v) {
        $beneficio = $v['beneficio'] ?? ($v['valor_venta_previsto'] - $v['precio_compra'] - $v['gastos']);

        fputcsv($output, [
            $v['id'],
            $v['matricula'] ?? '',
            $v['marca'],
            $v['modelo'],
            $v['version'] ?? '',
            $v['anio'],
            $v['kilometros'] ?? '',
            number_format($v['precio_compra'], 2, ',', ''),
            number_format($v['prevision_gastos'] ?? 0, 2, ',', ''),
            number_format($v['gastos'], 2, ',', ''),
            number_format($v['valor_venta_previsto'], 2, ',', ''),
            $v['precio_venta_real'] ? number_format($v['precio_venta_real'], 2, ',', '') : '',
            number_format($beneficio, 2, ',', ''),
            $estadoTexto[$v['estado']] ?? $v['estado'],
            $v['fecha_compra'] ? date('d/m/Y', strtotime($v['fecha_compra'])) : '',
            $v['fecha_venta'] ? date('d/m/Y', strtotime($v['fecha_venta'])) : '',
            $v['notas'] ?? ''
        ], ';');
    }

    fclose($output);
    exit;
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'crear' || $action === 'editar') {
            $id = intval($_POST['id'] ?? 0);
            $matricula = cleanInput($_POST['matricula'] ?? '');
            $marca = cleanInput($_POST['marca'] ?? '');
            $modelo = cleanInput($_POST['modelo'] ?? '');
            $version = cleanInput($_POST['version'] ?? '');
            $anio = intval($_POST['anio'] ?? date('Y'));
            $kilometros = !empty($_POST['kilometros']) ? intval($_POST['kilometros']) : null;
            $precio_compra = floatval($_POST['precio_compra'] ?? 0);
            $prevision_gastos = floatval($_POST['prevision_gastos'] ?? 0);
            $gastos = floatval($_POST['gastos'] ?? 0);
            $valor_venta_previsto = floatval($_POST['valor_venta_previsto'] ?? 0);
            $precio_venta_real = !empty($_POST['precio_venta_real']) ? floatval($_POST['precio_venta_real']) : null;
            $estado = cleanInput($_POST['estado'] ?? 'en_estudio');
            $fecha_compra = !empty($_POST['fecha_compra']) ? $_POST['fecha_compra'] : null;
            $fecha_venta = !empty($_POST['fecha_venta']) ? $_POST['fecha_venta'] : null;
            $notas = cleanInput($_POST['notas'] ?? '');
            $matricula = !empty($matricula) ? $matricula : null;

            // Validaciones
            if (empty($marca) || empty($modelo) || $precio_compra <= 0) {
                $error = 'Marca, modelo y precio de compra son obligatorios.';
            } else {
                // Procesar imagen principal si se sube
                $foto = null;
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                    if (in_array($_FILES['foto']['type'], $allowed)) {
                        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                        $filename = 'vehiculo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        $uploadPath = ROOT_PATH . '/assets/uploads/vehiculos/';

                        if (!is_dir($uploadPath)) {
                            mkdir($uploadPath, 0755, true);
                        }

                        if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadPath . $filename)) {
                            $foto = 'assets/uploads/vehiculos/' . $filename;
                        }
                    }
                }

                // Procesar fotos adicionales
                $fotosAdicionales = [];
                if (isset($_FILES['fotos_adicionales']) && is_array($_FILES['fotos_adicionales']['name'])) {
                    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                    $uploadPath = ROOT_PATH . '/assets/uploads/vehiculos/';
                    if (!is_dir($uploadPath)) {
                        mkdir($uploadPath, 0755, true);
                    }

                    foreach ($_FILES['fotos_adicionales']['name'] as $key => $name) {
                        if ($_FILES['fotos_adicionales']['error'][$key] === UPLOAD_ERR_OK) {
                            if (in_array($_FILES['fotos_adicionales']['type'][$key], $allowed)) {
                                $ext = pathinfo($name, PATHINFO_EXTENSION);
                                $filename = 'vehiculo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                                if (move_uploaded_file($_FILES['fotos_adicionales']['tmp_name'][$key], $uploadPath . $filename)) {
                                    $fotosAdicionales[] = 'assets/uploads/vehiculos/' . $filename;
                                }
                            }
                        }
                    }
                }

                try {
                    if ($action === 'crear') {
                        $sql = "INSERT INTO vehiculos (matricula, marca, modelo, version, anio, kilometros, precio_compra, prevision_gastos, gastos, valor_venta_previsto, precio_venta_real, estado, fecha_compra, fecha_venta, notas, foto)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$matricula, $marca, $modelo, $version, $anio, $kilometros, $precio_compra, $prevision_gastos, $gastos, $valor_venta_previsto, $precio_venta_real, $estado, $fecha_compra, $fecha_venta, $notas, $foto]);
                        $vehiculoId = $db->lastInsertId();

                        // Guardar fotos adicionales
                        if (!empty($fotosAdicionales)) {
                            $stmtFoto = $db->prepare("INSERT INTO vehiculo_fotos (vehiculo_id, foto, orden) VALUES (?, ?, ?)");
                            foreach ($fotosAdicionales as $orden => $fotoPath) {
                                $stmtFoto->execute([$vehiculoId, $fotoPath, $orden]);
                            }
                        }

                        $exito = 'Vehículo creado correctamente.';
                    } else {
                        // Obtener foto actual si no se sube nueva
                        if (!$foto) {
                            $stmt = $db->prepare("SELECT foto FROM vehiculos WHERE id = ?");
                            $stmt->execute([$id]);
                            $actual = $stmt->fetch();
                            $foto = $actual['foto'] ?? null;
                        }

                        $sql = "UPDATE vehiculos SET matricula=?, marca=?, modelo=?, version=?, anio=?, kilometros=?, precio_compra=?, prevision_gastos=?, gastos=?, valor_venta_previsto=?, precio_venta_real=?, estado=?, fecha_compra=?, fecha_venta=?, notas=?, foto=? WHERE id=?";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$matricula, $marca, $modelo, $version, $anio, $kilometros, $precio_compra, $prevision_gastos, $gastos, $valor_venta_previsto, $precio_venta_real, $estado, $fecha_compra, $fecha_venta, $notas, $foto, $id]);

                        // Guardar fotos adicionales
                        if (!empty($fotosAdicionales)) {
                            // Obtener el último orden
                            $maxOrden = $db->prepare("SELECT COALESCE(MAX(orden), -1) + 1 as next_orden FROM vehiculo_fotos WHERE vehiculo_id = ?");
                            $maxOrden->execute([$id]);
                            $nextOrden = $maxOrden->fetch()['next_orden'];

                            $stmtFoto = $db->prepare("INSERT INTO vehiculo_fotos (vehiculo_id, foto, orden) VALUES (?, ?, ?)");
                            foreach ($fotosAdicionales as $i => $fotoPath) {
                                $stmtFoto->execute([$id, $fotoPath, $nextOrden + $i]);
                            }
                        }

                        $exito = 'Vehículo actualizado correctamente.';
                    }
                } catch (Exception $e) {
                    $error = DEBUG_MODE ? $e->getMessage() : 'Error al guardar el vehículo.';
                }
            }
        } elseif ($action === 'eliminar') {
            $id = intval($_POST['id'] ?? 0);
            try {
                $stmt = $db->prepare("DELETE FROM vehiculos WHERE id = ?");
                $stmt->execute([$id]);
                $exito = 'Vehículo eliminado correctamente.';
            } catch (Exception $e) {
                $error = 'Error al eliminar el vehículo.';
            }
        } elseif ($action === 'eliminar_foto') {
            $fotoId = intval($_POST['foto_id'] ?? 0);
            $vehiculoId = intval($_POST['vehiculo_id'] ?? 0);
            try {
                $stmt = $db->prepare("DELETE FROM vehiculo_fotos WHERE id = ? AND vehiculo_id = ?");
                $stmt->execute([$fotoId, $vehiculoId]);
                $exito = 'Foto eliminada correctamente.';
            } catch (Exception $e) {
                $error = 'Error al eliminar la foto.';
            }
        }
    }
}

// Obtener vehículos
$filtro = cleanInput($_GET['filtro'] ?? '');
$sql = "SELECT * FROM vehiculos";
$params = [];

if ($filtro) {
    $sql .= " WHERE marca LIKE ? OR modelo LIKE ?";
    $params = ["%$filtro%", "%$filtro%"];
}

$sql .= " ORDER BY created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$vehiculos = $stmt->fetchAll();

// Vehículo a editar
$vehiculoEditar = null;
$fotosVehiculo = [];
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM vehiculos WHERE id = ?");
    $stmt->execute([intval($_GET['editar'])]);
    $vehiculoEditar = $stmt->fetch();

    if ($vehiculoEditar) {
        $stmtFotos = $db->prepare("SELECT * FROM vehiculo_fotos WHERE vehiculo_id = ? ORDER BY orden");
        $stmtFotos->execute([$vehiculoEditar['id']]);
        $fotosVehiculo = $stmtFotos->fetchAll();
    }
}

// Mensajes no leídos
$mensajesNoLeidos = $db->query("SELECT COUNT(*) as total FROM contactos WHERE leido = 0")->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehículos - Admin InverCar</title>
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
                    <h1>Gestión de Vehículos</h1>
                    <p>Administra los vehículos de inversión</p>
                </div>
                <button class="btn btn-primary" onclick="document.getElementById('modalVehiculo').classList.add('active')">
                    + Añadir Vehículo
                </button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo escape($error); ?></div>
            <?php endif; ?>
            <?php if ($exito): ?>
                <div class="alert alert-success"><?php echo escape($exito); ?></div>
            <?php endif; ?>

            <!-- Filtro -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-body" style="padding: 15px;">
                    <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                        <input type="text" name="filtro" placeholder="Buscar por marca o modelo..."
                               value="<?php echo escape($filtro); ?>" style="flex: 1; padding: 10px;">
                        <button type="submit" class="btn btn-primary">Buscar</button>
                        <?php if ($filtro): ?>
                            <a href="vehiculos.php" class="btn btn-outline">Limpiar</a>
                        <?php endif; ?>
                        <a href="?export=csv<?php echo $filtro ? '&filtro=' . urlencode($filtro) : ''; ?>" class="btn btn-outline" title="Exportar a CSV">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            CSV
                        </a>
                    </form>
                </div>
            </div>

            <!-- Lista de Vehículos -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($vehiculos)): ?>
                        <div class="empty-state">
                            <div class="icon">🚗</div>
                            <p>No hay vehículos registrados</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Foto</th>
                                        <th>Vehículo</th>
                                        <th>Matrícula</th>
                                        <th>Año / Km</th>
                                        <th>Compra</th>
                                        <th>Prev. Gastos</th>
                                        <th>Gastos</th>
                                        <th>Venta Prev.</th>
                                        <th>Beneficio</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vehiculos as $v): ?>
                                    <tr>
                                        <td>
                                            <?php if ($v['foto']): ?>
                                                <img src="../<?php echo escape($v['foto']); ?>" class="image-preview" alt="Foto">
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">Sin foto</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo escape($v['marca'] . ' ' . $v['modelo']); ?></strong>
                                            <?php if ($v['version']): ?>
                                                <br><small style="color: var(--text-muted);"><?php echo escape($v['version']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $v['matricula'] ? escape($v['matricula']) : '<span style="color:var(--text-muted);">-</span>'; ?></td>
                                        <td>
                                            <?php echo escape($v['anio']); ?>
                                            <?php if ($v['kilometros']): ?>
                                                <br><small style="color: var(--text-muted);"><?php echo number_format($v['kilometros'], 0, ',', '.'); ?> km</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo formatMoney($v['precio_compra']); ?></td>
                                        <td style="color: var(--text-muted);"><?php echo formatMoney($v['prevision_gastos'] ?? 0); ?></td>
                                        <td><?php echo formatMoney($v['gastos']); ?></td>
                                        <td><?php echo formatMoney($v['valor_venta_previsto']); ?></td>
                                        <td>
                                            <?php
                                            $beneficio = $v['beneficio'] ?? ($v['valor_venta_previsto'] - $v['precio_compra'] - $v['gastos']);
                                            $beneficioClass = $beneficio >= 0 ? 'color: var(--success)' : 'color: var(--danger)';
                                            ?>
                                            <span style="<?php echo $beneficioClass; ?>; font-weight: 600;">
                                                <?php echo formatMoney($beneficio); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $estadoBadge = [
                                                'en_estudio' => 'badge-gold',
                                                'en_preparacion' => 'badge-warning',
                                                'en_venta' => 'badge-success',
                                                'reservado' => 'badge-info',
                                                'vendido' => 'badge-info'
                                            ];
                                            $estadoTexto = [
                                                'en_estudio' => 'En Estudio',
                                                'en_preparacion' => 'En Preparación',
                                                'en_venta' => 'En Venta',
                                                'reservado' => 'Reservado',
                                                'vendido' => 'Vendido'
                                            ];
                                            ?>
                                            <span class="badge <?php echo $estadoBadge[$v['estado']] ?? 'badge-info'; ?>">
                                                <?php echo $estadoTexto[$v['estado']] ?? ucfirst(str_replace('_', ' ', $v['estado'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <a href="?editar=<?php echo $v['id']; ?>" class="btn btn-sm btn-outline">Editar</a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar este vehículo?');">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="action" value="eliminar">
                                                    <input type="hidden" name="id" value="<?php echo $v['id']; ?>">
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

    <!-- Modal Crear/Editar Vehículo -->
    <div class="modal-overlay <?php echo ($vehiculoEditar || isset($_GET['crear'])) ? 'active' : ''; ?>" id="modalVehiculo">
        <div class="modal" style="max-width: 700px;">
            <div class="modal-header">
                <h3><?php echo $vehiculoEditar ? 'Editar Vehículo' : 'Añadir Vehículo'; ?></h3>
                <a href="vehiculos.php" class="modal-close">&times;</a>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="<?php echo $vehiculoEditar ? 'editar' : 'crear'; ?>">
                    <?php if ($vehiculoEditar): ?>
                        <input type="hidden" name="id" value="<?php echo $vehiculoEditar['id']; ?>">
                    <?php endif; ?>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Marca *</label>
                            <input type="text" name="marca" required
                                   value="<?php echo escape($vehiculoEditar['marca'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Modelo *</label>
                            <input type="text" name="modelo" required
                                   value="<?php echo escape($vehiculoEditar['modelo'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Versión</label>
                            <input type="text" name="version"
                                   value="<?php echo escape($vehiculoEditar['version'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Matrícula</label>
                            <input type="text" name="matricula" placeholder="Ej: 1234 ABC"
                                   value="<?php echo escape($vehiculoEditar['matricula'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Año *</label>
                            <input type="number" name="anio" required min="1990" max="2030"
                                   value="<?php echo escape($vehiculoEditar['anio'] ?? date('Y')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Kilómetros</label>
                            <input type="number" name="kilometros" min="0" placeholder="Ej: 50000"
                                   value="<?php echo escape($vehiculoEditar['kilometros'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Precio de Compra (€) *</label>
                            <input type="number" name="precio_compra" required step="0.01" min="0"
                                   value="<?php echo escape($vehiculoEditar['precio_compra'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Previsión de Gastos (€)</label>
                            <input type="number" name="prevision_gastos" step="0.01" min="0"
                                   value="<?php echo escape($vehiculoEditar['prevision_gastos'] ?? '0'); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Gastos Reales (€)</label>
                            <input type="number" name="gastos" step="0.01" min="0"
                                   value="<?php echo escape($vehiculoEditar['gastos'] ?? '0'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Valor Venta Previsto (€) *</label>
                            <input type="number" name="valor_venta_previsto" required step="0.01" min="0"
                                   value="<?php echo escape($vehiculoEditar['valor_venta_previsto'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Precio Venta Real (€)</label>
                        <input type="number" name="precio_venta_real" step="0.01" min="0" style="max-width: 200px;"
                               value="<?php echo escape($vehiculoEditar['precio_venta_real'] ?? ''); ?>"
                               placeholder="Solo si ya se vendió">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Estado</label>
                            <select name="estado">
                                <option value="en_estudio" <?php echo ($vehiculoEditar['estado'] ?? 'en_estudio') === 'en_estudio' ? 'selected' : ''; ?>>En Estudio</option>
                                <option value="en_preparacion" <?php echo ($vehiculoEditar['estado'] ?? '') === 'en_preparacion' ? 'selected' : ''; ?>>En Preparación</option>
                                <option value="en_venta" <?php echo ($vehiculoEditar['estado'] ?? '') === 'en_venta' ? 'selected' : ''; ?>>En Venta</option>
                                <option value="reservado" <?php echo ($vehiculoEditar['estado'] ?? '') === 'reservado' ? 'selected' : ''; ?>>Reservado</option>
                                <option value="vendido" <?php echo ($vehiculoEditar['estado'] ?? '') === 'vendido' ? 'selected' : ''; ?>>Vendido</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Foto Principal</label>
                            <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
                            <?php if ($vehiculoEditar && $vehiculoEditar['foto']): ?>
                                <div style="margin-top: 5px;">
                                    <img src="../<?php echo escape($vehiculoEditar['foto']); ?>" style="width: 60px; height: 40px; object-fit: cover; border-radius: 4px;">
                                    <small style="color: var(--text-muted); margin-left: 5px;">Actual</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Fecha de Compra</label>
                            <input type="date" name="fecha_compra"
                                   value="<?php echo escape($vehiculoEditar['fecha_compra'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Fecha de Venta</label>
                            <input type="date" name="fecha_venta"
                                   value="<?php echo escape($vehiculoEditar['fecha_venta'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Fotos Adicionales</label>
                        <input type="file" name="fotos_adicionales[]" accept="image/jpeg,image/png,image/webp" multiple>
                        <small style="color: var(--text-muted);">Puedes seleccionar varias fotos a la vez</small>
                    </div>

                    <?php if ($vehiculoEditar && !empty($fotosVehiculo)): ?>
                    <div class="form-group">
                        <label>Fotos Actuales</label>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 5px;">
                            <?php foreach ($fotosVehiculo as $f): ?>
                            <div style="position: relative;">
                                <img src="../<?php echo escape($f['foto']); ?>" style="width: 80px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border-color);">
                                <button type="button" onclick="eliminarFoto(<?php echo $f['id']; ?>, <?php echo $vehiculoEditar['id']; ?>)"
                                        style="position: absolute; top: -5px; right: -5px; width: 20px; height: 20px; border-radius: 50%; background: var(--danger); color: white; border: none; cursor: pointer; font-size: 12px; line-height: 1;">&times;</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Notas</label>
                        <textarea name="notas" rows="3"><?php echo escape($vehiculoEditar['notas'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="vehiculos.php" class="btn btn-outline">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><?php echo $vehiculoEditar ? 'Guardar cambios' : 'Crear vehículo'; ?></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Cerrar modal al hacer clic fuera
        document.getElementById('modalVehiculo').addEventListener('click', function(e) {
            if (e.target === this) {
                window.location.href = 'vehiculos.php';
            }
        });

        // Eliminar foto adicional
        function eliminarFoto(fotoId, vehiculoId) {
            if (confirm('¿Eliminar esta foto?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<?php echo csrfField(); ?>' +
                    '<input type="hidden" name="action" value="eliminar_foto">' +
                    '<input type="hidden" name="foto_id" value="' + fotoId + '">' +
                    '<input type="hidden" name="vehiculo_id" value="' + vehiculoId + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
