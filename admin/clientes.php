<?php
/**
 * InverCar - Gestión de Clientes
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

        if ($action === 'toggle_activo') {
            $id = intval($_POST['id'] ?? 0);
            $activo = intval($_POST['activo'] ?? 0);
            $db->prepare("UPDATE clientes SET activo = ? WHERE id = ?")->execute([$activo, $id]);
            $exito = $activo ? 'Cliente activado.' : 'Cliente desactivado.';
        } elseif ($action === 'actualizar_capital') {
            $id = intval($_POST['id'] ?? 0);
            $capital = floatval($_POST['capital_invertido'] ?? 0);
            $tipo = cleanInput($_POST['tipo_inversion'] ?? '');

            if (in_array($tipo, ['fija', 'variable']) && $capital >= 0) {
                $db->prepare("UPDATE clientes SET capital_invertido = ?, tipo_inversion = ? WHERE id = ?")
                   ->execute([$capital, $tipo, $id]);
                $exito = 'Capital actualizado correctamente.';
            } else {
                $error = 'Datos no válidos.';
            }
        } elseif ($action === 'guardar_rentabilidad') {
            $clienteId = intval($_POST['cliente_id'] ?? 0);
            $semana = intval($_POST['semana'] ?? 0);
            $anio = intval($_POST['anio'] ?? date('Y'));
            $porcentaje = floatval($_POST['rentabilidad_porcentaje'] ?? 0);
            $euros = floatval($_POST['rentabilidad_euros'] ?? 0);

            if ($clienteId > 0 && $semana >= 1 && $semana <= 9) {
                // Insertar o actualizar
                $stmt = $db->prepare("
                    INSERT INTO rentabilidad_semanal (cliente_id, semana, anio, rentabilidad_porcentaje, rentabilidad_euros)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE rentabilidad_porcentaje = VALUES(rentabilidad_porcentaje), rentabilidad_euros = VALUES(rentabilidad_euros)
                ");
                $stmt->execute([$clienteId, $semana, $anio, $porcentaje, $euros]);
                $exito = 'Rentabilidad guardada.';
            }
        }
    }
}

// Obtener clientes
$filtro = cleanInput($_GET['filtro'] ?? '');
$sql = "SELECT * FROM clientes WHERE registro_completo = 1";
$params = [];

if ($filtro) {
    $sql .= " AND (nombre LIKE ? OR apellidos LIKE ? OR email LIKE ? OR dni LIKE ?)";
    $params = ["%$filtro%", "%$filtro%", "%$filtro%", "%$filtro%"];
}

$sql .= " ORDER BY created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();

// Cliente detalle
$clienteDetalle = null;
$rentabilidadCliente = [];
if (isset($_GET['ver'])) {
    $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([intval($_GET['ver'])]);
    $clienteDetalle = $stmt->fetch();

    if ($clienteDetalle) {
        $stmt = $db->prepare("SELECT * FROM rentabilidad_semanal WHERE cliente_id = ? ORDER BY anio DESC, semana ASC");
        $stmt->execute([$clienteDetalle['id']]);
        $rentabilidadCliente = $stmt->fetchAll();
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
    <title>Clientes - Admin InverCar</title>
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
                <li><a href="clientes.php" class="active"><span class="icon">◉</span> Clientes</a></li>
                <li><a href="vehiculos.php"><span class="icon">◆</span> Vehículos</a></li>

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
                    <h1>Gestión de Clientes</h1>
                    <p>Administra los inversores de la plataforma</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo escape($error); ?></div>
            <?php endif; ?>
            <?php if ($exito): ?>
                <div class="alert alert-success"><?php echo escape($exito); ?></div>
            <?php endif; ?>

            <?php if ($clienteDetalle): ?>
                <!-- Detalle del cliente -->
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2>Detalle: <?php echo escape($clienteDetalle['nombre'] . ' ' . $clienteDetalle['apellidos']); ?></h2>
                        <a href="clientes.php" class="btn btn-outline">← Volver</a>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                            <div>
                                <h4 style="color: var(--text-muted); margin-bottom: 10px;">Datos personales</h4>
                                <p><strong>Email:</strong> <?php echo escape($clienteDetalle['email']); ?></p>
                                <p><strong>DNI:</strong> <?php echo escape($clienteDetalle['dni']); ?></p>
                                <p><strong>Teléfono:</strong> <?php echo escape($clienteDetalle['telefono']); ?></p>
                                <p><strong>Dirección:</strong> <?php echo escape($clienteDetalle['direccion']); ?></p>
                                <p><strong>Localidad:</strong> <?php echo escape($clienteDetalle['codigo_postal'] . ' ' . $clienteDetalle['poblacion'] . ', ' . $clienteDetalle['provincia']); ?></p>
                            </div>
                            <div>
                                <h4 style="color: var(--text-muted); margin-bottom: 10px;">Inversión</h4>
                                <form method="POST" style="display: flex; flex-direction: column; gap: 10px;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="actualizar_capital">
                                    <input type="hidden" name="id" value="<?php echo $clienteDetalle['id']; ?>">

                                    <div class="form-group" style="margin-bottom: 10px;">
                                        <label>Capital Invertido (€)</label>
                                        <input type="number" name="capital_invertido" step="0.01"
                                               value="<?php echo $clienteDetalle['capital_invertido']; ?>">
                                    </div>
                                    <div class="form-group" style="margin-bottom: 10px;">
                                        <label>Tipo de Inversión</label>
                                        <select name="tipo_inversion">
                                            <option value="fija" <?php echo $clienteDetalle['tipo_inversion'] === 'fija' ? 'selected' : ''; ?>>Fija</option>
                                            <option value="variable" <?php echo $clienteDetalle['tipo_inversion'] === 'variable' ? 'selected' : ''; ?>>Variable</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm">Actualizar</button>
                                </form>
                            </div>
                        </div>

                        <hr style="border-color: var(--border-color); margin: 30px 0;">

                        <h4 style="margin-bottom: 15px;">Rentabilidad Semanal (últimas 9 semanas)</h4>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <?php for ($i = 1; $i <= 9; $i++): ?>
                                            <th>Sem <?php echo $i; ?></th>
                                        <?php endfor; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <?php
                                        $rentabilidadMap = [];
                                        foreach ($rentabilidadCliente as $r) {
                                            $rentabilidadMap[$r['semana']] = $r;
                                        }
                                        for ($i = 1; $i <= 9; $i++):
                                            $renta = $rentabilidadMap[$i] ?? null;
                                        ?>
                                        <td>
                                            <?php if ($renta): ?>
                                                <div style="color: var(--success); font-weight: bold;"><?php echo formatPercent($renta['rentabilidad_porcentaje']); ?></div>
                                                <small style="color: var(--text-muted);"><?php echo formatMoney($renta['rentabilidad_euros']); ?></small>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endfor; ?>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div style="margin-top: 20px;">
                            <form method="POST" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="guardar_rentabilidad">
                                <input type="hidden" name="cliente_id" value="<?php echo $clienteDetalle['id']; ?>">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Semana</label>
                                    <select name="semana" style="width: 80px;">
                                        <?php for ($i = 1; $i <= 9; $i++): ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Año</label>
                                    <input type="number" name="anio" value="<?php echo date('Y'); ?>" style="width: 100px;">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>% Rentabilidad</label>
                                    <input type="number" name="rentabilidad_porcentaje" step="0.01" style="width: 100px;">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>€ Rentabilidad</label>
                                    <input type="number" name="rentabilidad_euros" step="0.01" style="width: 120px;">
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                            </form>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Filtro -->
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-body" style="padding: 15px;">
                        <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                            <input type="text" name="filtro" placeholder="Buscar por nombre, email o DNI..."
                                   value="<?php echo escape($filtro); ?>" style="flex: 1; padding: 10px;">
                            <button type="submit" class="btn btn-primary">Buscar</button>
                            <?php if ($filtro): ?>
                                <a href="clientes.php" class="btn btn-outline">Limpiar</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Lista de Clientes -->
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($clientes)): ?>
                            <div class="empty-state">
                                <div class="icon">👥</div>
                                <p>No hay clientes registrados</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Cliente</th>
                                            <th>Email</th>
                                            <th>Capital</th>
                                            <th>Tipo</th>
                                            <th>Estado</th>
                                            <th>Registro</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($clientes as $c): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo escape($c['nombre'] . ' ' . $c['apellidos']); ?></strong>
                                                <br><small style="color: var(--text-muted);"><?php echo escape($c['dni']); ?></small>
                                            </td>
                                            <td><?php echo escape($c['email']); ?></td>
                                            <td><?php echo formatMoney($c['capital_invertido']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $c['tipo_inversion'] === 'fija' ? 'badge-info' : 'badge-success'; ?>">
                                                    <?php echo ucfirst($c['tipo_inversion']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $c['activo'] ? 'badge-success' : 'badge-danger'; ?>">
                                                    <?php echo $c['activo'] ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($c['created_at'])); ?></td>
                                            <td>
                                                <div class="actions">
                                                    <a href="?ver=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline">Ver</a>
                                                    <form method="POST" style="display: inline;">
                                                        <?php echo csrfField(); ?>
                                                        <input type="hidden" name="action" value="toggle_activo">
                                                        <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                                        <input type="hidden" name="activo" value="<?php echo $c['activo'] ? 0 : 1; ?>">
                                                        <button type="submit" class="btn btn-sm <?php echo $c['activo'] ? 'btn-danger' : 'btn-primary'; ?>">
                                                            <?php echo $c['activo'] ? 'Desactivar' : 'Activar'; ?>
                                                        </button>
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
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
