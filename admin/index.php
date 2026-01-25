<?php
/**
 * InverCar - Panel de Administración - Dashboard
 */
require_once __DIR__ . '/../includes/init.php';

if (!isAdminLogueado()) {
    redirect('login.php');
}

$db = getDB();

// Estadísticas generales
$statsClientes = $db->query("
    SELECT
        COUNT(*) as total,
        SUM(capital_invertido) as capital_total,
        SUM(CASE WHEN tipo_inversion = 'fija' THEN capital_invertido ELSE 0 END) as capital_fija,
        SUM(CASE WHEN tipo_inversion = 'variable' THEN capital_invertido ELSE 0 END) as capital_variable
    FROM clientes WHERE activo = 1 AND registro_completo = 1
")->fetch();

$statsVehiculos = $db->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'en_venta' THEN 1 ELSE 0 END) as en_venta,
        SUM(CASE WHEN estado = 'vendido' THEN 1 ELSE 0 END) as vendidos,
        SUM(precio_compra + gastos) as capital_invertido,
        SUM(CASE WHEN estado IN ('en_venta', 'reservado') THEN valor_venta_previsto ELSE 0 END) as valor_previsto
    FROM vehiculos
")->fetch();

// Capital en reserva
$capitalReserva = floatval(getConfig('capital_reserva', 0));

// Últimos clientes registrados
$ultimosClientes = $db->query("
    SELECT nombre, apellidos, email, capital_invertido, tipo_inversion, created_at
    FROM clientes
    WHERE activo = 1 AND registro_completo = 1
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll();

// Últimos vehículos
$ultimosVehiculos = $db->query("
    SELECT marca, modelo, anio, precio_compra, valor_venta_previsto, estado
    FROM vehiculos
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll();

// Mensajes de contacto no leídos
$mensajesNoLeidos = $db->query("SELECT COUNT(*) as total FROM contactos WHERE leido = 0")->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin InverCar</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-logo">Inver<span>Car</span></div>
            <div class="sidebar-badge">ADMIN</div>

            <ul class="sidebar-menu">
                <li><a href="index.php" class="active"><span class="icon">📊</span> Dashboard</a></li>
                <li><a href="vehiculos.php"><span class="icon">🚗</span> Vehículos</a></li>
                <li><a href="clientes.php"><span class="icon">👥</span> Clientes</a></li>

                <li class="sidebar-section">Configuración</li>
                <li><a href="contactos.php"><span class="icon">📨</span> Mensajes <?php if($mensajesNoLeidos > 0): ?><span class="badge badge-danger"><?php echo $mensajesNoLeidos; ?></span><?php endif; ?></a></li>
                <li><a href="configuracion.php"><span class="icon">⚙️</span> Ajustes</a></li>

                <li class="sidebar-section">Cuenta</li>
                <li><a href="logout.php"><span class="icon">🚪</span> Cerrar sesión</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1>Dashboard</h1>
                    <p>Resumen general del sistema</p>
                </div>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo escape($_SESSION['admin_nombre']); ?></div>
                        <div class="role">Administrador</div>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($statsClientes['total'], 0, ',', '.'); ?></div>
                            <div class="stat-label">Inversores Activos</div>
                        </div>
                        <div class="stat-icon green">👥</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo formatMoney($statsClientes['capital_total'] ?? 0); ?></div>
                            <div class="stat-label">Capital de Inversores</div>
                        </div>
                        <div class="stat-icon blue">💰</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo formatMoney($statsVehiculos['capital_invertido'] ?? 0); ?></div>
                            <div class="stat-label">Capital Invertido</div>
                        </div>
                        <div class="stat-icon orange">🚗</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo formatMoney($capitalReserva); ?></div>
                            <div class="stat-label">Capital Reserva</div>
                        </div>
                        <div class="stat-icon green">🏦</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo formatMoney($statsVehiculos['valor_previsto'] ?? 0); ?></div>
                            <div class="stat-label">Valor Venta Previsto</div>
                        </div>
                        <div class="stat-icon blue">📈</div>
                    </div>
                </div>
            </div>

            <!-- Capital por tipo -->
            <div class="stats-grid" style="margin-bottom: 30px;">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo formatMoney($statsClientes['capital_fija'] ?? 0); ?></div>
                            <div class="stat-label">Capital Rentabilidad Fija</div>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo formatMoney($statsClientes['capital_variable'] ?? 0); ?></div>
                            <div class="stat-label">Capital Rentabilidad Variable</div>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $statsVehiculos['en_venta'] ?? 0; ?> / <?php echo $statsVehiculos['total'] ?? 0; ?></div>
                            <div class="stat-label">Vehículos en Venta / Total</div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Últimos Clientes -->
                <div class="card">
                    <div class="card-header">
                        <h2>Últimos Inversores</h2>
                        <a href="clientes.php" class="btn btn-sm btn-outline">Ver todos</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($ultimosClientes)): ?>
                            <div class="empty-state">
                                <p>No hay inversores registrados</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Capital</th>
                                            <th>Tipo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ultimosClientes as $cliente): ?>
                                        <tr>
                                            <td><?php echo escape($cliente['nombre'] . ' ' . $cliente['apellidos']); ?></td>
                                            <td><?php echo formatMoney($cliente['capital_invertido']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $cliente['tipo_inversion'] === 'fija' ? 'badge-info' : 'badge-success'; ?>">
                                                    <?php echo ucfirst($cliente['tipo_inversion']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Últimos Vehículos -->
                <div class="card">
                    <div class="card-header">
                        <h2>Últimos Vehículos</h2>
                        <a href="vehiculos.php" class="btn btn-sm btn-outline">Ver todos</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($ultimosVehiculos)): ?>
                            <div class="empty-state">
                                <p>No hay vehículos registrados</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Vehículo</th>
                                            <th>Compra</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ultimosVehiculos as $vehiculo): ?>
                                        <tr>
                                            <td><?php echo escape($vehiculo['marca'] . ' ' . $vehiculo['modelo']); ?></td>
                                            <td><?php echo formatMoney($vehiculo['precio_compra']); ?></td>
                                            <td>
                                                <?php
                                                $estadoBadge = [
                                                    'en_venta' => 'badge-success',
                                                    'vendido' => 'badge-info',
                                                    'reservado' => 'badge-warning'
                                                ];
                                                ?>
                                                <span class="badge <?php echo $estadoBadge[$vehiculo['estado']] ?? 'badge-info'; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $vehiculo['estado'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
