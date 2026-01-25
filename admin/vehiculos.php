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

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'crear' || $action === 'editar') {
            $id = intval($_POST['id'] ?? 0);
            $marca = cleanInput($_POST['marca'] ?? '');
            $modelo = cleanInput($_POST['modelo'] ?? '');
            $version = cleanInput($_POST['version'] ?? '');
            $anio = intval($_POST['anio'] ?? date('Y'));
            $precio_compra = floatval($_POST['precio_compra'] ?? 0);
            $gastos = floatval($_POST['gastos'] ?? 0);
            $valor_venta_previsto = floatval($_POST['valor_venta_previsto'] ?? 0);
            $precio_venta_real = !empty($_POST['precio_venta_real']) ? floatval($_POST['precio_venta_real']) : null;
            $estado = cleanInput($_POST['estado'] ?? 'en_venta');
            $fecha_compra = !empty($_POST['fecha_compra']) ? $_POST['fecha_compra'] : null;
            $fecha_venta = !empty($_POST['fecha_venta']) ? $_POST['fecha_venta'] : null;
            $notas = cleanInput($_POST['notas'] ?? '');

            // Validaciones
            if (empty($marca) || empty($modelo) || $precio_compra <= 0) {
                $error = 'Marca, modelo y precio de compra son obligatorios.';
            } else {
                // Procesar imagen si se sube
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

                try {
                    if ($action === 'crear') {
                        $sql = "INSERT INTO vehiculos (marca, modelo, version, anio, precio_compra, gastos, valor_venta_previsto, precio_venta_real, estado, fecha_compra, fecha_venta, notas, foto)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$marca, $modelo, $version, $anio, $precio_compra, $gastos, $valor_venta_previsto, $precio_venta_real, $estado, $fecha_compra, $fecha_venta, $notas, $foto]);
                        $exito = 'Vehículo creado correctamente.';
                    } else {
                        // Obtener foto actual si no se sube nueva
                        if (!$foto) {
                            $stmt = $db->prepare("SELECT foto FROM vehiculos WHERE id = ?");
                            $stmt->execute([$id]);
                            $actual = $stmt->fetch();
                            $foto = $actual['foto'] ?? null;
                        }

                        $sql = "UPDATE vehiculos SET marca=?, modelo=?, version=?, anio=?, precio_compra=?, gastos=?, valor_venta_previsto=?, precio_venta_real=?, estado=?, fecha_compra=?, fecha_venta=?, notas=?, foto=? WHERE id=?";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$marca, $modelo, $version, $anio, $precio_compra, $gastos, $valor_venta_previsto, $precio_venta_real, $estado, $fecha_compra, $fecha_venta, $notas, $foto, $id]);
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
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM vehiculos WHERE id = ?");
    $stmt->execute([intval($_GET['editar'])]);
    $vehiculoEditar = $stmt->fetch();
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
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <img src="../assets/images/logo-invercar-text.png" alt="InverCar" style="height: 40px; width: auto;">
            </div>
            <div class="sidebar-badge">ADMIN</div>

            <ul class="sidebar-menu">
                <li><a href="index.php"><span class="icon">◈</span> Panel</a></li>
                <li><a href="clientes.php"><span class="icon">◉</span> Clientes</a></li>
                <li><a href="vehiculos.php" class="active"><span class="icon">◆</span> Vehículos</a></li>

                <li class="sidebar-section">Configuración</li>
                <li><a href="contactos.php"><span class="icon">◇</span> Mensajes <?php if($mensajesNoLeidos > 0): ?><span class="badge badge-danger"><?php echo $mensajesNoLeidos; ?></span><?php endif; ?></a></li>
                <li><a href="configuracion.php"><span class="icon">◎</span> Ajustes</a></li>

                <li class="sidebar-section">Cuenta</li>
                <li><a href="logout.php"><span class="icon">◁</span> Cerrar sesión</a></li>
            </ul>
        </aside>

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
                                        <th>Año</th>
                                        <th>Precio Compra</th>
                                        <th>Gastos</th>
                                        <th>Venta Prevista</th>
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
                                        <td><?php echo escape($v['anio']); ?></td>
                                        <td><?php echo formatMoney($v['precio_compra']); ?></td>
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
                                                'en_venta' => 'badge-success',
                                                'vendido' => 'badge-info',
                                                'reservado' => 'badge-warning'
                                            ];
                                            ?>
                                            <span class="badge <?php echo $estadoBadge[$v['estado']] ?? 'badge-info'; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $v['estado'])); ?>
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
                            <label>Año *</label>
                            <input type="number" name="anio" required min="1990" max="2030"
                                   value="<?php echo escape($vehiculoEditar['anio'] ?? date('Y')); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Precio de Compra (€) *</label>
                            <input type="number" name="precio_compra" required step="0.01" min="0"
                                   value="<?php echo escape($vehiculoEditar['precio_compra'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Gastos (€)</label>
                            <input type="number" name="gastos" step="0.01" min="0"
                                   value="<?php echo escape($vehiculoEditar['gastos'] ?? '0'); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Valor Venta Previsto (€) *</label>
                            <input type="number" name="valor_venta_previsto" required step="0.01" min="0"
                                   value="<?php echo escape($vehiculoEditar['valor_venta_previsto'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Precio Venta Real (€)</label>
                            <input type="number" name="precio_venta_real" step="0.01" min="0"
                                   value="<?php echo escape($vehiculoEditar['precio_venta_real'] ?? ''); ?>"
                                   placeholder="Solo si ya se vendió">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Estado</label>
                            <select name="estado">
                                <option value="en_venta" <?php echo ($vehiculoEditar['estado'] ?? '') === 'en_venta' ? 'selected' : ''; ?>>En Venta</option>
                                <option value="reservado" <?php echo ($vehiculoEditar['estado'] ?? '') === 'reservado' ? 'selected' : ''; ?>>Reservado</option>
                                <option value="vendido" <?php echo ($vehiculoEditar['estado'] ?? '') === 'vendido' ? 'selected' : ''; ?>>Vendido</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Foto del vehículo</label>
                            <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
                            <?php if ($vehiculoEditar && $vehiculoEditar['foto']): ?>
                                <small style="color: var(--text-muted);">Actual: <?php echo basename($vehiculoEditar['foto']); ?></small>
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
    </script>
</body>
</html>
