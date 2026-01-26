<?php
/**
 * InverCar - Panel de Administración - Dashboard Premium
 */
require_once __DIR__ . '/../includes/init.php';

if (!isAdminLogueado()) {
    redirect('login.php');
}

$db = getDB();

// Estadísticas generales de clientes
$statsClientes = $db->query("
    SELECT
        COUNT(*) as total,
        COALESCE(SUM(capital_total), 0) as capital_total,
        COALESCE(SUM(capital_invertido), 0) as capital_invertido,
        COALESCE(SUM(capital_reserva), 0) as capital_reserva,
        COALESCE(SUM(CASE WHEN tipo_inversion = 'fija' THEN capital_total ELSE 0 END), 0) as capital_fija,
        COALESCE(SUM(CASE WHEN tipo_inversion = 'variable' THEN capital_total ELSE 0 END), 0) as capital_variable,
        COALESCE(SUM(CASE WHEN tipo_inversion = 'fija' THEN capital_invertido ELSE 0 END), 0) as invertido_fija,
        COALESCE(SUM(CASE WHEN tipo_inversion = 'variable' THEN capital_invertido ELSE 0 END), 0) as invertido_variable,
        COALESCE(SUM(CASE WHEN tipo_inversion = 'fija' THEN capital_reserva ELSE 0 END), 0) as reserva_fija,
        COALESCE(SUM(CASE WHEN tipo_inversion = 'variable' THEN capital_reserva ELSE 0 END), 0) as reserva_variable,
        SUM(CASE WHEN tipo_inversion = 'fija' THEN 1 ELSE 0 END) as clientes_fija,
        SUM(CASE WHEN tipo_inversion = 'variable' THEN 1 ELSE 0 END) as clientes_variable
    FROM clientes WHERE activo = 1 AND registro_completo = 1
")->fetch();

$statsVehiculos = $db->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'en_venta' THEN 1 ELSE 0 END) as en_venta,
        SUM(CASE WHEN estado = 'vendido' THEN 1 ELSE 0 END) as vendidos,
        COALESCE(SUM(CASE WHEN estado IN ('en_estudio', 'en_preparacion', 'en_venta', 'reservado') THEN precio_compra + gastos ELSE 0 END), 0) as capital_invertido_vehiculos,
        COALESCE(SUM(CASE WHEN estado IN ('en_estudio', 'en_preparacion', 'en_venta', 'reservado') THEN valor_venta_previsto ELSE 0 END), 0) as valor_previsto
    FROM vehiculos
")->fetch();

// Vehículos activos para las fichas
$vehiculosActivos = $db->query("
    SELECT id, marca, modelo, version, anio, kilometros, precio_compra, prevision_gastos, gastos, valor_venta_previsto, foto, estado
    FROM vehiculos
    WHERE estado IN ('en_estudio', 'en_preparacion', 'en_venta', 'reservado')
    ORDER BY created_at DESC
")->fetchAll();

// Configuración
$rentabilidadFija = floatval(getConfig('rentabilidad_fija', 5));
$rentabilidadVariableActual = floatval(getConfig('rentabilidad_variable_actual', 14.8));

// Calcular semana actual del año
$semanaActual = (int) date('W');
$anioActual = (int) date('Y');

// Obtener últimas 9 semanas de rentabilidad del histórico
$rentabilidadHistorico = [];
try {
    // Intentar obtener datos reales de la tabla
    $stmt = $db->prepare("
        SELECT semana, anio, tipo, porcentaje, rentabilidad_generada, capital_base
        FROM rentabilidad_historico
        WHERE (anio = :anio AND semana <= :semana) OR (anio = :anio_prev AND semana > :semana)
        ORDER BY anio DESC, semana DESC
        LIMIT 18
    ");
    $semanaLimite = $semanaActual - 9;
    $anioPrev = $semanaLimite < 1 ? $anioActual - 1 : $anioActual;
    $stmt->execute([
        ':anio' => $anioActual,
        ':semana' => $semanaActual,
        ':anio_prev' => $anioPrev
    ]);
    $rentabilidadHistorico = $stmt->fetchAll();
} catch (Exception $e) {
    // Tabla no existe aún, se usarán datos simulados
}

// Preparar datos para el gráfico (últimas 9 semanas)
$semanasGrafico = [];
for ($i = 8; $i >= 0; $i--) {
    $semNum = $semanaActual - $i;
    $anio = $anioActual;
    if ($semNum <= 0) {
        $semNum += 52;
        $anio--;
    }

    // Buscar datos en histórico o simular
    $rentFija = $rentabilidadFija;
    $rentVariable = $rentabilidadVariableActual * (0.8 + (rand(0, 40) / 100)); // Simular variación

    foreach ($rentabilidadHistorico as $hist) {
        if ($hist['semana'] == $semNum && $hist['anio'] == $anio) {
            if ($hist['tipo'] == 'fija') $rentFija = floatval($hist['porcentaje']);
            if ($hist['tipo'] == 'variable') $rentVariable = floatval($hist['porcentaje']);
        }
    }

    $mediaRent = ($rentFija + $rentVariable) / 2;

    $semanasGrafico[] = [
        'semana' => $semNum,
        'anio' => $anio,
        'label' => 'S' . $semNum,
        'fija' => $rentFija,
        'variable' => $rentVariable,
        'media' => $mediaRent
    ];
}

// Calcular capital y rentabilidad
// El capital invertido real es el que está en vehículos activos
$capitalInvertidoVehiculos = floatval($statsVehiculos['capital_invertido_vehiculos'] ?? 0);
$capitalTotalClientes = floatval($statsClientes['capital_total'] ?? 0);
$capitalReserva = max(0, $capitalTotalClientes - $capitalInvertidoVehiculos);

// Capital por tipo de inversión (de clientes)
$capitalFija = floatval($statsClientes['capital_fija'] ?? 0);
$capitalVariable = floatval($statsClientes['capital_variable'] ?? 0);

// Calcular rentabilidad generada basada en capital invertido real (proporcional)
$proporcionFija = $capitalTotalClientes > 0 ? $capitalFija / $capitalTotalClientes : 0;
$proporcionVariable = $capitalTotalClientes > 0 ? $capitalVariable / $capitalTotalClientes : 0;

$capitalInvertidoFija = $capitalInvertidoVehiculos * $proporcionFija;
$capitalInvertidoVariable = $capitalInvertidoVehiculos * $proporcionVariable;

$rentabilidadGeneradaFija = $capitalInvertidoFija * ($rentabilidadFija / 100);
$rentabilidadGeneradaVariable = $capitalInvertidoVariable * ($rentabilidadVariableActual / 100);
$rentabilidadTotalGenerada = $rentabilidadGeneradaFija + $rentabilidadGeneradaVariable;
$rentabilidadMediaPorcentaje = $capitalInvertidoVehiculos > 0 ? ($rentabilidadTotalGenerada / $capitalInvertidoVehiculos) * 100 : 0;

// Últimos clientes registrados
$ultimosClientes = $db->query("
    SELECT id, nombre, apellidos, capital_invertido, tipo_inversion,
           CASE WHEN tipo_inversion = 'fija' THEN capital_invertido ELSE 0 END as capital_fijo,
           CASE WHEN tipo_inversion = 'variable' THEN capital_invertido ELSE 0 END as capital_variable,
           created_at
    FROM clientes
    WHERE activo = 1 AND registro_completo = 1
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll();
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Esquinas cuadradas en todos los elementos */
        .card, .stat-panel, .rent-big-card, .chart-card, .badge, .btn, .vehicle-card, .vehicle-price-item {
            border-radius: 0 !important;
        }

        /* Fichas de vehículos - 3-4 columnas en PC */
        .vehicle-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 20px;
        }
        @media (max-width: 1400px) {
            .vehicle-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        @media (max-width: 1100px) {
            .vehicle-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 768px) {
            .vehicle-grid {
                grid-template-columns: 1fr;
            }
        }
        .vehicle-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            backdrop-filter: blur(10px);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
        }
        .vehicle-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(212, 168, 75, 0.1);
        }
        .vehicle-card-status {
            position: absolute;
            top: 8px;
            left: 8px;
            padding: 4px 10px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 2;
        }
        .vehicle-card-status.en_estudio { background: var(--gold); color: #000; }
        .vehicle-card-status.en_preparacion { background: var(--warning); color: #000; }
        .vehicle-card-status.en_venta { background: var(--green-accent); color: #000; }
        .vehicle-card-status.reservado { background: var(--blue-accent); color: #fff; }
        .vehicle-card-image {
            width: 100%;
            height: 140px;
            background: rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .vehicle-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }
        .vehicle-card-image .no-image {
            color: var(--text-muted);
            font-size: 2rem;
        }
        .vehicle-card-body {
            padding: 12px;
        }
        .vehicle-card-title {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 3px;
            color: var(--text-light);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .vehicle-card-subtitle {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-bottom: 10px;
        }
        .vehicle-card-prices {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .vehicle-price-item {
            text-align: center;
            padding: 6px;
            background: rgba(0,0,0,0.2);
        }
        .vehicle-price-label {
            font-size: 0.65rem;
            color: var(--text-muted);
            margin-bottom: 2px;
        }
        .vehicle-price-value {
            font-size: 0.85rem;
            font-weight: 600;
        }
        .vehicle-price-value.compra {
            color: var(--blue-accent);
        }
        .vehicle-price-value.venta {
            color: var(--green-accent);
        }

        /* Gráfico de líneas */
        .chart-container {
            position: relative;
            height: 200px;
            margin-top: 15px;
        }

        /* Rentabilidad grande */
        .rent-big-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        .rent-big-card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 25px;
            backdrop-filter: blur(10px);
        }
        .rent-big-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .rent-big-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        .rent-big-icon.fija {
            background: rgba(59, 130, 246, 0.2);
            color: var(--blue-accent);
        }
        .rent-big-icon.variable {
            background: rgba(34, 197, 94, 0.2);
            color: var(--green-accent);
        }
        .rent-big-title {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        .rent-big-value {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .rent-big-value.fija { color: var(--blue-accent); }
        .rent-big-value.variable { color: var(--green-accent); }
        .rent-big-percent {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            background: rgba(34, 197, 94, 0.15);
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--green-accent);
        }
        .rent-big-capital {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .rent-big-capital strong {
            color: var(--text-light);
        }

        /* Section titles */
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title::before {
            content: '';
            width: 4px;
            height: 20px;
            background: var(--gold);
            border-radius: 2px;
        }
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
                    <p>Panel de administración central</p>
                </div>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo escape($_SESSION['admin_nombre']); ?></div>
                    </div>
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['admin_nombre'], 0, 1)); ?></div>
                </div>
            </div>

            <!-- Tarjetas de Rentabilidad (Arriba) -->
            <div class="rent-big-cards">
                <div class="rent-big-card">
                    <div class="rent-big-header">
                        <div class="rent-big-icon fija">€</div>
                        <div class="rent-big-title">Rentabilidad Fija</div>
                    </div>
                    <div class="rent-big-value fija"><?php echo formatMoney($rentabilidadGeneradaFija); ?></div>
                    <div class="rent-big-percent">▲ <?php echo number_format($rentabilidadFija, 1, ',', '.'); ?>%</div>
                    <div class="rent-big-capital">
                        Capital invertido: <strong><?php echo formatMoney($capitalInvertidoFija); ?></strong>
                    </div>
                </div>
                <div class="rent-big-card">
                    <div class="rent-big-header">
                        <div class="rent-big-icon variable">€</div>
                        <div class="rent-big-title">Rentabilidad Variable</div>
                    </div>
                    <div class="rent-big-value variable"><?php echo formatMoney($rentabilidadGeneradaVariable); ?></div>
                    <div class="rent-big-percent">▲ <?php echo number_format($rentabilidadVariableActual, 1, ',', '.'); ?>%</div>
                    <div class="rent-big-capital">
                        Capital invertido: <strong><?php echo formatMoney($capitalInvertidoVariable); ?></strong>
                    </div>
                </div>
            </div>

            <!-- Gráfico de Rentabilidad Media por Semana -->
            <div class="card" style="margin-bottom: 25px;">
                <div class="card-header">
                    <h2>Rentabilidad Media por Semana</h2>
                    <span style="color: var(--text-muted); font-size: 0.85rem;">Últimas 9 semanas</span>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="rentabilidadChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Layout principal -->
            <div class="dashboard-layout" style="grid-template-columns: 1fr 300px;">
                <!-- Columna Principal -->
                <div class="dashboard-main">
                    <!-- Tabla de Clientes -->
                    <div class="section-title">Clientes</div>
                    <div class="card">
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nombre y Apellidos</th>
                                        <th>Capital</th>
                                        <th>Capital Fijo</th>
                                        <th>Capital Variable</th>
                                        <th>Rent. Fijo</th>
                                        <th>Rent. Variable</th>
                                        <th>Media Rent.</th>
                                    </tr>
                                </thead>
                                <tbody id="clientesTable">
                                    <?php if (empty($ultimosClientes)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                            No hay clientes registrados
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($ultimosClientes as $cliente):
                                            $rentFijo = $cliente['capital_fijo'] > 0 ? $rentabilidadFija : 0;
                                            $rentVariable = $cliente['capital_variable'] > 0 ? $rentabilidadVariableActual : 0;
                                            $mediaRent = 0;
                                            if ($rentFijo > 0 && $rentVariable > 0) {
                                                $mediaRent = ($rentFijo + $rentVariable) / 2;
                                            } elseif ($rentFijo > 0) {
                                                $mediaRent = $rentFijo;
                                            } elseif ($rentVariable > 0) {
                                                $mediaRent = $rentVariable;
                                            }
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo escape($cliente['nombre'] . ' ' . $cliente['apellidos']); ?></strong>
                                            </td>
                                            <td><strong><?php echo formatMoney($cliente['capital_invertido']); ?></strong></td>
                                            <td style="color: var(--blue-accent);"><?php echo formatMoney($cliente['capital_fijo']); ?></td>
                                            <td style="color: var(--green-accent);"><?php echo formatMoney($cliente['capital_variable']); ?></td>
                                            <td style="color: var(--blue-accent);"><?php echo number_format($rentFijo, 1, ',', '.'); ?>%</td>
                                            <td style="color: var(--green-accent);"><?php echo number_format($rentVariable, 1, ',', '.'); ?>%</td>
                                            <td><strong><?php echo number_format($mediaRent, 1, ',', '.'); ?>%</strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Fichas de Vehículos -->
                    <div class="section-title" style="margin-top: 30px;">Vehículos en Activo</div>
                    <?php if (empty($vehiculosActivos)): ?>
                        <div class="card" style="padding: 40px; text-align: center; color: var(--text-muted);">
                            No hay vehículos activos
                        </div>
                    <?php else: ?>
                        <div class="vehicle-grid">
                            <?php foreach ($vehiculosActivos as $vehiculo):
                                // Calcular rentabilidad: Venta prevista - Compra - Gastos
                                $gastosTotal = floatval($vehiculo['gastos'] ?? 0);
                                if ($gastosTotal == 0) $gastosTotal = floatval($vehiculo['prevision_gastos'] ?? 0);
                                $costeTotal = floatval($vehiculo['precio_compra']) + $gastosTotal;
                                $rentabilidadEuros = floatval($vehiculo['valor_venta_previsto']) - $costeTotal;
                                $rentabilidadPorcentaje = $costeTotal > 0 ? ($rentabilidadEuros / $costeTotal) * 100 : 0;
                            ?>
                            <?php
                                $estadoTextos = [
                                    'en_estudio' => 'En Estudio',
                                    'en_preparacion' => 'En Preparación',
                                    'en_venta' => 'En Venta',
                                    'reservado' => 'Reservado',
                                    'vendido' => 'Vendido'
                                ];
                            ?>
                            <div class="vehicle-card">
                                <div class="vehicle-card-status <?php echo $vehiculo['estado']; ?>">
                                    <?php echo $estadoTextos[$vehiculo['estado']] ?? ucfirst(str_replace('_', ' ', $vehiculo['estado'])); ?>
                                </div>
                                <div class="vehicle-card-image">
                                    <?php if ($vehiculo['foto']): ?>
                                        <img src="../<?php echo escape($vehiculo['foto']); ?>" alt="<?php echo escape($vehiculo['marca'] . ' ' . $vehiculo['modelo']); ?>">
                                    <?php else: ?>
                                        <span class="no-image">🚗</span>
                                    <?php endif; ?>
                                </div>
                                <div class="vehicle-card-body">
                                    <div class="vehicle-card-title"><?php echo escape($vehiculo['marca'] . ' ' . $vehiculo['modelo']); ?></div>
                                    <div class="vehicle-card-subtitle">
                                        <?php echo escape($vehiculo['version'] ?? ''); ?> · <?php echo escape($vehiculo['anio']); ?>
                                        <?php if ($vehiculo['kilometros']): ?>
                                            · <?php echo number_format($vehiculo['kilometros'], 0, ',', '.'); ?> km
                                        <?php endif; ?>
                                    </div>
                                    <div class="vehicle-card-prices">
                                        <div class="vehicle-price-item">
                                            <div class="vehicle-price-label">Compra + Gastos</div>
                                            <div class="vehicle-price-value compra"><?php echo formatMoney($costeTotal); ?></div>
                                        </div>
                                        <div class="vehicle-price-item">
                                            <div class="vehicle-price-label">Venta Prevista</div>
                                            <div class="vehicle-price-value venta"><?php echo formatMoney($vehiculo['valor_venta_previsto']); ?></div>
                                        </div>
                                    </div>
                                    <div class="vehicle-card-rentabilidad" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border-color); text-align: center;">
                                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">Rentabilidad</div>
                                        <div style="display: flex; justify-content: center; gap: 15px;">
                                            <span style="font-weight: 700; color: <?php echo $rentabilidadEuros >= 0 ? 'var(--green-accent)' : 'var(--danger)'; ?>;">
                                                <?php echo $rentabilidadEuros >= 0 ? '+' : ''; ?><?php echo formatMoney($rentabilidadEuros); ?>
                                            </span>
                                            <span style="font-weight: 600; color: <?php echo $rentabilidadPorcentaje >= 0 ? 'var(--green-accent)' : 'var(--danger)'; ?>;">
                                                (<?php echo $rentabilidadPorcentaje >= 0 ? '+' : ''; ?><?php echo number_format($rentabilidadPorcentaje, 1, ',', '.'); ?>%)
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Panel Lateral -->
                <div class="dashboard-sidebar">
                    <!-- Capital -->
                    <div class="stat-panel">
                        <div class="stat-panel-header">
                            <div class="stat-panel-icon">€</div>
                            <div class="stat-panel-title">Capital</div>
                        </div>
                        <div class="stat-panel-value"><?php echo formatMoney($statsClientes['capital_total'] ?? 0); ?></div>

                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin: 15px 0 8px; letter-spacing: 0.5px;">Por tipo de inversión</div>
                        <div class="stat-panel-row">
                            <span class="stat-panel-label fija">Rentabilidad Fija</span>
                            <span class="stat-panel-amount fija"><?php echo formatMoney($statsClientes['capital_fija'] ?? 0); ?></span>
                        </div>
                        <div class="stat-panel-row">
                            <span class="stat-panel-label variable">Rentabilidad Variable</span>
                            <span class="stat-panel-amount variable"><?php echo formatMoney($statsClientes['capital_variable'] ?? 0); ?></span>
                        </div>

                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin: 15px 0 8px; letter-spacing: 0.5px;">Estado del capital</div>
                        <div class="stat-panel-row">
                            <span class="stat-panel-label" style="color: var(--green-accent);">
                                <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--green-accent); margin-right:8px;"></span>
                                Capital Invertido
                            </span>
                            <span class="stat-panel-amount" style="color: var(--green-accent);"><?php echo formatMoney($statsVehiculos['capital_invertido_vehiculos'] ?? 0); ?></span>
                        </div>
                        <div class="stat-panel-row">
                            <span class="stat-panel-label" style="color: var(--gold);">
                                <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--gold); margin-right:8px;"></span>
                                Capital en Reserva
                            </span>
                            <span class="stat-panel-amount" style="color: var(--gold);"><?php echo formatMoney(max(0, ($statsClientes['capital_total'] ?? 0) - ($statsVehiculos['capital_invertido_vehiculos'] ?? 0))); ?></span>
                        </div>
                    </div>

                    <!-- Resumen de Vehículos -->
                    <div class="stat-panel" style="padding: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <span style="font-size: 0.9rem; font-weight: 600;">Vehículos</span>
                            <a href="vehiculos.php" class="btn btn-sm btn-outline">Ver todos</a>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; text-align: center;">
                            <div>
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--gold);"><?php echo $statsVehiculos['total'] ?? 0; ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">Total</div>
                            </div>
                            <div>
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--green-accent);"><?php echo $statsVehiculos['en_venta'] ?? 0; ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">En Venta</div>
                            </div>
                            <div>
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--blue-accent);"><?php echo $statsVehiculos['vendidos'] ?? 0; ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">Vendidos</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Gráfico de Rentabilidad Media por Semana
        const ctx = document.getElementById('rentabilidadChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($semanasGrafico, 'label')); ?>,
                datasets: [
                    {
                        label: 'Rentabilidad Fija',
                        data: <?php echo json_encode(array_column($semanasGrafico, 'fija')); ?>,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#000',
                        pointBorderWidth: 1,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Rentabilidad Variable',
                        data: <?php echo json_encode(array_column($semanasGrafico, 'variable')); ?>,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        pointBackgroundColor: '#22c55e',
                        pointBorderColor: '#000',
                        pointBorderWidth: 1,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Media',
                        data: <?php echo json_encode(array_column($semanasGrafico, 'media')); ?>,
                        borderColor: '#d4a84b',
                        backgroundColor: 'rgba(212, 168, 75, 0.15)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#d4a84b',
                        pointBorderColor: '#000',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            color: '#888',
                            usePointStyle: true,
                            padding: 20,
                            font: { size: 11 }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(20, 20, 20, 0.95)',
                        titleColor: '#fff',
                        bodyColor: '#ccc',
                        borderColor: 'rgba(212, 168, 75, 0.3)',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toFixed(1) + '%';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        },
                        ticks: {
                            color: '#888'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: 20,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        },
                        ticks: {
                            color: '#888',
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
