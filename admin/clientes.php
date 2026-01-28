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

// Exportar CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filtro = cleanInput($_GET['filtro'] ?? '');
    $sql = "SELECT c.* FROM clientes c WHERE c.registro_completo = 1";
    $params = [];

    if ($filtro) {
        $sql .= " AND (c.nombre LIKE ? OR c.apellidos LIKE ? OR c.email LIKE ? OR c.dni LIKE ?)";
        $params = ["%$filtro%", "%$filtro%", "%$filtro%", "%$filtro%"];
    }

    $sql .= " ORDER BY c.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clientes_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Header
    fputcsv($output, ['ID', 'Nombre', 'Apellidos', 'Email', 'DNI', 'Teléfono', 'Dirección', 'CP', 'Población', 'Provincia', 'País', 'Capital Fija', 'Capital Variable', 'Capital Total', 'Activo', 'Fecha Registro'], ';');

    foreach ($clientes as $c) {
        // Obtener capital del cliente
        $stmtCap = $db->prepare("
            SELECT tipo_inversion, SUM(importe_ingresado) - SUM(importe_retirado) as capital
            FROM capital WHERE cliente_id = ? AND activo = 1 GROUP BY tipo_inversion
        ");
        $stmtCap->execute([$c['id']]);
        $capData = [];
        foreach ($stmtCap->fetchAll() as $cap) {
            $capData[$cap['tipo_inversion']] = $cap['capital'];
        }
        $capFija = $capData['fija'] ?? 0;
        $capVariable = $capData['variable'] ?? 0;
        $capTotal = $capFija + $capVariable;

        fputcsv($output, [
            $c['id'],
            $c['nombre'],
            $c['apellidos'],
            $c['email'],
            $c['dni'],
            $c['telefono'],
            $c['direccion'],
            $c['codigo_postal'],
            $c['poblacion'],
            $c['provincia'],
            $c['pais'],
            number_format($capFija, 2, ',', ''),
            number_format($capVariable, 2, ',', ''),
            number_format($capTotal, 2, ',', ''),
            $c['activo'] ? 'Sí' : 'No',
            date('d/m/Y', strtotime($c['created_at']))
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
            $nombre = cleanInput($_POST['nombre'] ?? '');
            $apellidos = cleanInput($_POST['apellidos'] ?? '');
            $email = cleanInput($_POST['email'] ?? '');
            $dni = cleanInput($_POST['dni'] ?? '');
            $telefono = cleanInput($_POST['telefono'] ?? '');
            $direccion = cleanInput($_POST['direccion'] ?? '');
            $codigo_postal = cleanInput($_POST['codigo_postal'] ?? '');
            $poblacion = cleanInput($_POST['poblacion'] ?? '');
            $provincia = cleanInput($_POST['provincia'] ?? '');
            $pais = cleanInput($_POST['pais'] ?? 'España');
            $activo = intval($_POST['activo'] ?? 1);

            // Validaciones
            if (empty($nombre) || empty($apellidos) || empty($email)) {
                $error = 'Nombre, apellidos y email son obligatorios.';
            } elseif (!validarEmail($email)) {
                $error = 'El email no es válido.';
            } else {
                try {
                    if ($action === 'crear') {
                        // Verificar email único
                        $stmt = $db->prepare("SELECT id FROM clientes WHERE email = ?");
                        $stmt->execute([$email]);
                        if ($stmt->fetch()) {
                            $error = 'Ya existe un cliente con ese email.';
                        } else {
                            // Generar password temporal
                            $passwordTemp = bin2hex(random_bytes(4));
                            $passwordHash = password_hash($passwordTemp, PASSWORD_DEFAULT, ['cost' => HASH_COST]);

                            $sql = "INSERT INTO clientes (nombre, apellidos, email, password, dni, telefono, direccion, codigo_postal, poblacion, provincia, pais, activo, registro_completo, email_verificado)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)";
                            $stmt = $db->prepare($sql);
                            $stmt->execute([$nombre, $apellidos, $email, $passwordHash, $dni, $telefono, $direccion, $codigo_postal, $poblacion, $provincia, $pais, $activo]);
                            $exito = "Cliente creado correctamente. Contraseña temporal: $passwordTemp";
                        }
                    } else {
                        // Verificar email único (excepto el propio cliente)
                        $stmt = $db->prepare("SELECT id FROM clientes WHERE email = ? AND id != ?");
                        $stmt->execute([$email, $id]);
                        if ($stmt->fetch()) {
                            $error = 'Ya existe otro cliente con ese email.';
                        } else {
                            $sql = "UPDATE clientes SET nombre=?, apellidos=?, email=?, dni=?, telefono=?, direccion=?, codigo_postal=?, poblacion=?, provincia=?, pais=?, activo=? WHERE id=?";
                            $stmt = $db->prepare($sql);
                            $stmt->execute([$nombre, $apellidos, $email, $dni, $telefono, $direccion, $codigo_postal, $poblacion, $provincia, $pais, $activo, $id]);
                            $exito = 'Cliente actualizado correctamente.';
                        }
                    }
                } catch (Exception $e) {
                    $error = DEBUG_MODE ? $e->getMessage() : 'Error al guardar el cliente.';
                }
            }
        } elseif ($action === 'eliminar') {
            $id = intval($_POST['id'] ?? 0);
            try {
                $stmt = $db->prepare("DELETE FROM clientes WHERE id = ?");
                $stmt->execute([$id]);
                $exito = 'Cliente eliminado correctamente.';
            } catch (Exception $e) {
                $error = 'Error al eliminar el cliente. Es posible que tenga datos asociados.';
            }
        } elseif ($action === 'toggle_activo') {
            $id = intval($_POST['id'] ?? 0);
            $activo = intval($_POST['activo'] ?? 0);
            $db->prepare("UPDATE clientes SET activo = ? WHERE id = ?")->execute([$activo, $id]);
            $exito = $activo ? 'Cliente activado.' : 'Cliente desactivado.';
        } elseif ($action === 'guardar_rentabilidad') {
            $clienteId = intval($_POST['cliente_id'] ?? 0);
            $semana = intval($_POST['semana'] ?? 0);
            $anio = intval($_POST['anio'] ?? date('Y'));
            $porcentaje = floatval($_POST['rentabilidad_porcentaje'] ?? 0);
            $euros = floatval($_POST['rentabilidad_euros'] ?? 0);

            if ($clienteId > 0 && $semana >= 1 && $semana <= 9) {
                $stmt = $db->prepare("
                    INSERT INTO rentabilidad_semanal (cliente_id, semana, anio, rentabilidad_porcentaje, rentabilidad_euros)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE rentabilidad_porcentaje = VALUES(rentabilidad_porcentaje), rentabilidad_euros = VALUES(rentabilidad_euros)
                ");
                $stmt->execute([$clienteId, $semana, $anio, $porcentaje, $euros]);
                $exito = 'Rentabilidad guardada.';
            }
        } elseif ($action === 'reset_password') {
            $id = intval($_POST['id'] ?? 0);
            $passwordTemp = bin2hex(random_bytes(4));
            $passwordHash = password_hash($passwordTemp, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
            $db->prepare("UPDATE clientes SET password = ? WHERE id = ?")->execute([$passwordHash, $id]);
            $exito = "Contraseña reseteada. Nueva contraseña temporal: $passwordTemp";
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

// Cliente a editar
$clienteEditar = null;
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([intval($_GET['editar'])]);
    $clienteEditar = $stmt->fetch();
}

// Cliente detalle
$clienteDetalle = null;
$rentabilidadCliente = [];
$capitalCliente = null;
if (isset($_GET['ver'])) {
    $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([intval($_GET['ver'])]);
    $clienteDetalle = $stmt->fetch();

    if ($clienteDetalle) {
        $stmt = $db->prepare("SELECT * FROM rentabilidad_semanal WHERE cliente_id = ? ORDER BY anio DESC, semana ASC");
        $stmt->execute([$clienteDetalle['id']]);
        $rentabilidadCliente = $stmt->fetchAll();

        // Obtener capital del cliente desde la tabla capital
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
        $stmt->execute([$clienteDetalle['id']]);
        $capitalCliente = [];
        foreach ($stmt->fetchAll() as $row) {
            $capitalCliente[$row['tipo_inversion']] = $row;
        }
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
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1>Gestión de Clientes</h1>
                    <p>Administra los inversores de la plataforma</p>
                </div>
                <?php if (!$clienteDetalle): ?>
                <button class="btn btn-primary" onclick="document.getElementById('modalCliente').classList.add('active')">
                    + Añadir Cliente
                </button>
                <?php endif; ?>
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
                        <div class="actions">
                            <a href="?editar=<?php echo $clienteDetalle['id']; ?>" class="btn btn-sm btn-outline">Editar</a>
                            <a href="clientes.php" class="btn btn-outline">← Volver</a>
                        </div>
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
                                <p><strong>País:</strong> <?php echo escape($clienteDetalle['pais']); ?></p>
                                <p><strong>Estado:</strong>
                                    <span class="badge <?php echo $clienteDetalle['activo'] ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $clienteDetalle['activo'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </p>
                                <p><strong>Registrado:</strong> <?php echo date('d/m/Y H:i', strtotime($clienteDetalle['created_at'])); ?></p>
                            </div>
                            <div>
                                <h4 style="color: var(--text-muted); margin-bottom: 10px;">Capital</h4>
                                <?php
                                $capitalFija = ($capitalCliente['fija']['total_ingresado'] ?? 0) - ($capitalCliente['fija']['total_retirado'] ?? 0);
                                $capitalVariable = ($capitalCliente['variable']['total_ingresado'] ?? 0) - ($capitalCliente['variable']['total_retirado'] ?? 0);
                                $capitalTotal = $capitalFija + $capitalVariable;
                                $rentabilidadTotal = ($capitalCliente['fija']['total_rentabilidad'] ?? 0) + ($capitalCliente['variable']['total_rentabilidad'] ?? 0);
                                ?>
                                <p style="font-size: 2rem; font-weight: bold; color: var(--gold);"><?php echo formatMoney($capitalTotal); ?></p>
                                <p><strong>Fija:</strong> <?php echo formatMoney($capitalFija); ?></p>
                                <p><strong>Variable:</strong> <?php echo formatMoney($capitalVariable); ?></p>
                                <p><strong>Rentabilidad:</strong> <span style="color: var(--success);"><?php echo formatMoney($rentabilidadTotal); ?></span></p>

                                <div style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                                    <a href="capital.php?cliente=<?php echo $clienteDetalle['id']; ?>" class="btn btn-sm btn-primary">
                                        Gestionar Capital
                                    </a>
                                    <form method="POST" style="display: inline;">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="id" value="<?php echo $clienteDetalle['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline" onclick="return confirm('¿Resetear la contraseña de este cliente?');">
                                            Resetear Contraseña
                                        </button>
                                    </form>
                                </div>
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
                                            <th>Capital Total</th>
                                            <th>Fija</th>
                                            <th>Variable</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($clientes as $c):
                                            // Obtener capital del cliente
                                            $stmtCap = $db->prepare("
                                                SELECT tipo_inversion,
                                                       SUM(importe_ingresado) - SUM(importe_retirado) as capital
                                                FROM capital WHERE cliente_id = ? AND activo = 1 GROUP BY tipo_inversion
                                            ");
                                            $stmtCap->execute([$c['id']]);
                                            $capData = [];
                                            foreach ($stmtCap->fetchAll() as $cap) {
                                                $capData[$cap['tipo_inversion']] = $cap['capital'];
                                            }
                                            $capFija = $capData['fija'] ?? 0;
                                            $capVariable = $capData['variable'] ?? 0;
                                            $capTotal = $capFija + $capVariable;
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo escape($c['nombre'] . ' ' . $c['apellidos']); ?></strong>
                                                <br><small style="color: var(--text-muted);"><?php echo escape($c['dni']); ?></small>
                                            </td>
                                            <td><?php echo escape($c['email']); ?></td>
                                            <td style="font-weight: bold; color: var(--gold);"><?php echo formatMoney($capTotal); ?></td>
                                            <td><span class="badge badge-info"><?php echo formatMoney($capFija); ?></span></td>
                                            <td><span class="badge badge-success"><?php echo formatMoney($capVariable); ?></span></td>
                                            <td>
                                                <span class="badge <?php echo $c['activo'] ? 'badge-success' : 'badge-danger'; ?>">
                                                    <?php echo $c['activo'] ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="actions">
                                                    <a href="?ver=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline">Ver</a>
                                                    <a href="capital.php?cliente=<?php echo $c['id']; ?>" class="btn btn-sm btn-primary">Capital</a>
                                                    <a href="?editar=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline">Editar</a>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar este cliente? Esta acción no se puede deshacer.');">
                                                        <?php echo csrfField(); ?>
                                                        <input type="hidden" name="action" value="eliminar">
                                                        <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
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
            <?php endif; ?>
        </main>
    </div>

    <!-- Modal Crear/Editar Cliente -->
    <div class="modal-overlay <?php echo ($clienteEditar || isset($_GET['crear'])) ? 'active' : ''; ?>" id="modalCliente">
        <div class="modal" style="max-width: 700px;">
            <div class="modal-header">
                <h3><?php echo $clienteEditar ? 'Editar Cliente' : 'Añadir Cliente'; ?></h3>
                <a href="clientes.php" class="modal-close">&times;</a>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="<?php echo $clienteEditar ? 'editar' : 'crear'; ?>">
                    <?php if ($clienteEditar): ?>
                        <input type="hidden" name="id" value="<?php echo $clienteEditar['id']; ?>">
                    <?php endif; ?>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre *</label>
                            <input type="text" name="nombre" required
                                   value="<?php echo escape($clienteEditar['nombre'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Apellidos *</label>
                            <input type="text" name="apellidos" required
                                   value="<?php echo escape($clienteEditar['apellidos'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" required
                                   value="<?php echo escape($clienteEditar['email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>DNI</label>
                            <input type="text" name="dni"
                                   value="<?php echo escape($clienteEditar['dni'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="tel" name="telefono"
                                   value="<?php echo escape($clienteEditar['telefono'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>País</label>
                            <input type="text" name="pais"
                                   value="<?php echo escape($clienteEditar['pais'] ?? 'España'); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Dirección</label>
                        <input type="text" name="direccion"
                               value="<?php echo escape($clienteEditar['direccion'] ?? ''); ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Código Postal</label>
                            <input type="text" name="codigo_postal"
                                   value="<?php echo escape($clienteEditar['codigo_postal'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Población</label>
                            <input type="text" name="poblacion"
                                   value="<?php echo escape($clienteEditar['poblacion'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Provincia</label>
                            <input type="text" name="provincia"
                                   value="<?php echo escape($clienteEditar['provincia'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Estado</label>
                        <select name="activo">
                            <option value="1" <?php echo ($clienteEditar['activo'] ?? 1) == 1 ? 'selected' : ''; ?>>Activo</option>
                            <option value="0" <?php echo ($clienteEditar['activo'] ?? 1) == 0 ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>

                    <?php if (!$clienteEditar): ?>
                    <div class="alert" style="background: rgba(212, 168, 75, 0.1); border: 1px solid var(--gold); color: var(--gold);">
                        Al crear el cliente se generará una contraseña temporal que se mostrará en pantalla.
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <a href="clientes.php" class="btn btn-outline">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><?php echo $clienteEditar ? 'Guardar cambios' : 'Crear cliente'; ?></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Cerrar modal al hacer clic fuera
        document.getElementById('modalCliente').addEventListener('click', function(e) {
            if (e.target === this) {
                window.location.href = 'clientes.php';
            }
        });
    </script>
</body>
</html>
