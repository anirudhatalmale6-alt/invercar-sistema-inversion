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

// Estadísticas últimos 3 meses
$estadisticasMensuales = [];
$mesesNombres = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

for ($i = 2; $i >= 0; $i--) {
    $fecha = date('Y-m', strtotime("-$i months"));
    $mesNum = intval(date('m', strtotime("-$i months")));
    $anio = date('Y', strtotime("-$i months"));
    $mesNombre = $mesesNombres[$mesNum - 1];

    // Nuevos clientes del mes
    $stmt = $db->prepare("
        SELECT COUNT(*) as nuevos, SUM(capital_invertido) as capital_nuevo
        FROM clientes
        WHERE activo = 1 AND registro_completo = 1
        AND DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmt->execute([$fecha]);
    $clientesMes = $stmt->fetch();

    // Vehículos comprados en el mes
    $stmt = $db->prepare("
        SELECT COUNT(*) as comprados, SUM(precio_compra) as total_compra
        FROM vehiculos
        WHERE DATE_FORMAT(fecha_compra, '%Y-%m') = ? OR DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmt->execute([$fecha, $fecha]);
    $vehiculosComprados = $stmt->fetch();

    // Vehículos vendidos en el mes
    $stmt = $db->prepare("
        SELECT COUNT(*) as vendidos, SUM(precio_venta_real) as total_venta, SUM(beneficio) as beneficio_total
        FROM vehiculos
        WHERE estado = 'vendido' AND DATE_FORMAT(fecha_venta, '%Y-%m') = ?
    ");
    $stmt->execute([$fecha]);
    $vehiculosVendidos = $stmt->fetch();

    $estadisticasMensuales[] = [
        'mes' => $mesNombre . ' ' . $anio,
        'mes_corto' => substr($mesNombre, 0, 3) . ' ' . substr($anio, 2),
        'nuevos_clientes' => intval($clientesMes['nuevos'] ?? 0),
        'capital_nuevo' => floatval($clientesMes['capital_nuevo'] ?? 0),
        'vehiculos_comprados' => intval($vehiculosComprados['comprados'] ?? 0),
        'inversion_compra' => floatval($vehiculosComprados['total_compra'] ?? 0),
        'vehiculos_vendidos' => intval($vehiculosVendidos['vendidos'] ?? 0),
        'ingresos_venta' => floatval($vehiculosVendidos['total_venta'] ?? 0),
        'beneficio' => floatval($vehiculosVendidos['beneficio_total'] ?? 0)
    ];
}

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
    <title>Panel - Admin InverCar</title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .chart-container {
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            height: 200px;
            padding: 20px 10px;
            gap: 30px;
        }
        .chart-bar-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
        }
        .chart-bars {
            display: flex;
            align-items: flex-end;
            gap: 4px;
            height: 150px;
        }
        .chart-bar {
            width: 24px;
            min-height: 4px;
            transition: height 0.3s ease;
        }
        .chart-bar.clientes { background: #3b82f6; }
        .chart-bar.compras { background: #d4a84b; }
        .chart-bar.ventas { background: #10b981; }
        .chart-bar-label {
            margin-top: 10px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .chart-legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }
        .chart-legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .chart-legend-color {
            width: 12px;
            height: 12px;
        }
        .monthly-stats-table td {
            vertical-align: middle;
        }
        .trend-up { color: #10b981; }
        .trend-down { color: #ef4444; }
        .trend-neutral { color: var(--text-muted); }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1>Panel</h1>
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
                            <div class="stat-label">Capital en Vehículos</div>
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

            <!-- Estadísticas Últimos 3 Meses -->
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-header">
                    <h2>Estadísticas Últimos 3 Meses</h2>
                </div>
                <div class="card-body">
                    <?php
                    // Calcular máximos para escalar las barras
                    $maxClientes = max(array_column($estadisticasMensuales, 'nuevos_clientes')) ?: 1;
                    $maxCompras = max(array_column($estadisticasMensuales, 'vehiculos_comprados')) ?: 1;
                    $maxVentas = max(array_column($estadisticasMensuales, 'vehiculos_vendidos')) ?: 1;
                    $maxValue = max($maxClientes, $maxCompras, $maxVentas) ?: 1;
                    ?>
                    <div class="chart-container">
                        <?php foreach ($estadisticasMensuales as $mes): ?>
                        <div class="chart-bar-group">
                            <div class="chart-bars">
                                <div class="chart-bar clientes" style="height: <?php echo ($mes['nuevos_clientes'] / $maxValue) * 130 + 4; ?>px;" title="<?php echo $mes['nuevos_clientes']; ?> nuevos clientes"></div>
                                <div class="chart-bar compras" style="height: <?php echo ($mes['vehiculos_comprados'] / $maxValue) * 130 + 4; ?>px;" title="<?php echo $mes['vehiculos_comprados']; ?> vehículos comprados"></div>
                                <div class="chart-bar ventas" style="height: <?php echo ($mes['vehiculos_vendidos'] / $maxValue) * 130 + 4; ?>px;" title="<?php echo $mes['vehiculos_vendidos']; ?> vehículos vendidos"></div>
                            </div>
                            <div class="chart-bar-label"><?php echo $mes['mes_corto']; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="chart-legend">
                        <div class="chart-legend-item">
                            <div class="chart-legend-color" style="background: #3b82f6;"></div>
                            <span>Nuevos Clientes</span>
                        </div>
                        <div class="chart-legend-item">
                            <div class="chart-legend-color" style="background: #d4a84b;"></div>
                            <span>Vehículos Comprados</span>
                        </div>
                        <div class="chart-legend-item">
                            <div class="chart-legend-color" style="background: #10b981;"></div>
                            <span>Vehículos Vendidos</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla detallada de 3 meses -->
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-header">
                    <h2>Detalle Mensual</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="monthly-stats-table">
                            <thead>
                                <tr>
                                    <th>Mes</th>
                                    <th>Nuevos Clientes</th>
                                    <th>Capital Captado</th>
                                    <th>Vehículos Comprados</th>
                                    <th>Inversión Compra</th>
                                    <th>Vehículos Vendidos</th>
                                    <th>Ingresos Venta</th>
                                    <th>Beneficio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estadisticasMensuales as $mes): ?>
                                <tr>
                                    <td><strong><?php echo $mes['mes']; ?></strong></td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $mes['nuevos_clientes']; ?></span>
                                    </td>
                                    <td><?php echo formatMoney($mes['capital_nuevo']); ?></td>
                                    <td>
                                        <span class="badge badge-warning"><?php echo $mes['vehiculos_comprados']; ?></span>
                                    </td>
                                    <td><?php echo formatMoney($mes['inversion_compra']); ?></td>
                                    <td>
                                        <span class="badge badge-success"><?php echo $mes['vehiculos_vendidos']; ?></span>
                                    </td>
                                    <td><?php echo formatMoney($mes['ingresos_venta']); ?></td>
                                    <td>
                                        <span class="<?php echo $mes['beneficio'] >= 0 ? 'trend-up' : 'trend-down'; ?>" style="font-weight: 600;">
                                            <?php echo formatMoney($mes['beneficio']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr style="background: rgba(212, 168, 75, 0.1); font-weight: bold;">
                                    <td>TOTAL 3 MESES</td>
                                    <td><?php echo array_sum(array_column($estadisticasMensuales, 'nuevos_clientes')); ?></td>
                                    <td><?php echo formatMoney(array_sum(array_column($estadisticasMensuales, 'capital_nuevo'))); ?></td>
                                    <td><?php echo array_sum(array_column($estadisticasMensuales, 'vehiculos_comprados')); ?></td>
                                    <td><?php echo formatMoney(array_sum(array_column($estadisticasMensuales, 'inversion_compra'))); ?></td>
                                    <td><?php echo array_sum(array_column($estadisticasMensuales, 'vehiculos_vendidos')); ?></td>
                                    <td><?php echo formatMoney(array_sum(array_column($estadisticasMensuales, 'ingresos_venta'))); ?></td>
                                    <td>
                                        <?php $totalBeneficio = array_sum(array_column($estadisticasMensuales, 'beneficio')); ?>
                                        <span class="<?php echo $totalBeneficio >= 0 ? 'trend-up' : 'trend-down'; ?>">
                                            <?php echo formatMoney($totalBeneficio); ?>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
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
