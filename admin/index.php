<?php
/**
 * InverCar - Panel de Administración - Dashboard Premium
 */
require_once __DIR__ . '/../includes/init.php';

if (!isAdminLogueado()) {
    redirect('login.php');
}

$db = getDB();

// Estadísticas generales de clientes (usando tabla capital)
$statsClientes = $db->query("
    SELECT COUNT(DISTINCT c.id) as total
    FROM clientes c
    WHERE c.activo = 1 AND c.registro_completo = 1
")->fetch();

// Capital por tipo de inversión (desde tabla capital)
$capitalStats = $db->query("
    SELECT
        tipo_inversion,
        COALESCE(SUM(importe_ingresado) - SUM(importe_retirado), 0) as capital_neto,
        COALESCE(SUM(rentabilidad), 0) as rentabilidad_total
    FROM capital
    WHERE activo = 1
    GROUP BY tipo_inversion
")->fetchAll();

$capitalFija = 0;
$capitalVariable = 0;
$rentabilidadAcumuladaFija = 0;
$rentabilidadAcumuladaVariable = 0;
foreach ($capitalStats as $cap) {
    if ($cap['tipo_inversion'] === 'fija') {
        $capitalFija = floatval($cap['capital_neto']);
        $rentabilidadAcumuladaFija = floatval($cap['rentabilidad_total']);
    } else {
        $capitalVariable = floatval($cap['capital_neto']);
        $rentabilidadAcumuladaVariable = floatval($cap['rentabilidad_total']);
    }
}
$capitalTotalClientes = $capitalFija + $capitalVariable;

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
    SELECT id, marca, modelo, version, anio, kilometros, precio_compra, prevision_gastos, gastos, valor_venta_previsto, foto, estado, notas
    FROM vehiculos
    WHERE estado IN ('en_estudio', 'en_preparacion', 'en_venta', 'reservado')
    ORDER BY created_at DESC
")->fetchAll();

// Obtener fotos adicionales de cada vehículo
$fotosPorVehiculo = [];
if (!empty($vehiculosActivos)) {
    $vehiculoIds = array_column($vehiculosActivos, 'id');
    $placeholders = implode(',', array_fill(0, count($vehiculoIds), '?'));
    $stmtFotos = $db->prepare("SELECT vehiculo_id, foto FROM vehiculo_fotos WHERE vehiculo_id IN ($placeholders) ORDER BY vehiculo_id, orden");
    $stmtFotos->execute($vehiculoIds);
    foreach ($stmtFotos->fetchAll() as $f) {
        $fotosPorVehiculo[$f['vehiculo_id']][] = $f['foto'];
    }
}

// Obtener capital invertido por vehículo (desde tabla capital)
$capitalPorVehiculo = [];
$stmtCapVeh = $db->query("
    SELECT vehiculo_id, COALESCE(SUM(importe_ingresado) - SUM(importe_retirado), 0) as capital_invertido
    FROM capital
    WHERE vehiculo_id IS NOT NULL AND activo = 1
    GROUP BY vehiculo_id
");
foreach ($stmtCapVeh->fetchAll() as $cv) {
    $capitalPorVehiculo[$cv['vehiculo_id']] = floatval($cv['capital_invertido']);
}

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
// El capital invertido real es el capital vinculado a vehículos en la tabla capital
$capitalConVehiculo = $db->query("
    SELECT COALESCE(SUM(importe_ingresado) - SUM(importe_retirado), 0) as capital
    FROM capital
    WHERE vehiculo_id IS NOT NULL AND activo = 1
")->fetch();
$capitalInvertidoVehiculos = floatval($capitalConVehiculo['capital'] ?? 0);
$capitalReserva = max(0, $capitalTotalClientes - $capitalInvertidoVehiculos);

// Calcular rentabilidad generada basada en capital invertido real (proporcional)
$proporcionFija = $capitalTotalClientes > 0 ? $capitalFija / $capitalTotalClientes : 0;
$proporcionVariable = $capitalTotalClientes > 0 ? $capitalVariable / $capitalTotalClientes : 0;

$capitalInvertidoFija = $capitalInvertidoVehiculos * $proporcionFija;
$capitalInvertidoVariable = $capitalInvertidoVehiculos * $proporcionVariable;

// Usar rentabilidad acumulada de la tabla capital si existe, sino calcular
$rentabilidadGeneradaFija = $rentabilidadAcumuladaFija > 0 ? $rentabilidadAcumuladaFija : $capitalInvertidoFija * ($rentabilidadFija / 100);
$rentabilidadGeneradaVariable = $rentabilidadAcumuladaVariable > 0 ? $rentabilidadAcumuladaVariable : $capitalInvertidoVariable * ($rentabilidadVariableActual / 100);
$rentabilidadTotalGenerada = $rentabilidadGeneradaFija + $rentabilidadGeneradaVariable;
$rentabilidadMediaPorcentaje = $capitalInvertidoVehiculos > 0 ? ($rentabilidadTotalGenerada / $capitalInvertidoVehiculos) * 100 : 0;

// Últimos clientes registrados (con capital desde tabla capital)
$ultimosClientes = $db->query("
    SELECT c.id, c.nombre, c.apellidos, c.created_at,
           COALESCE(SUM(CASE WHEN cap.tipo_inversion = 'fija' THEN cap.importe_ingresado - cap.importe_retirado ELSE 0 END), 0) as capital_fijo,
           COALESCE(SUM(CASE WHEN cap.tipo_inversion = 'variable' THEN cap.importe_ingresado - cap.importe_retirado ELSE 0 END), 0) as capital_variable,
           COALESCE(SUM(cap.importe_ingresado - cap.importe_retirado), 0) as capital_total,
           COALESCE(SUM(CASE WHEN cap.tipo_inversion = 'fija' THEN cap.rentabilidad ELSE 0 END), 0) as rent_fija,
           COALESCE(SUM(CASE WHEN cap.tipo_inversion = 'variable' THEN cap.rentabilidad ELSE 0 END), 0) as rent_variable
    FROM clientes c
    LEFT JOIN capital cap ON c.id = cap.cliente_id AND cap.activo = 1
    WHERE c.activo = 1 AND c.registro_completo = 1
    GROUP BY c.id, c.nombre, c.apellidos, c.created_at
    ORDER BY c.created_at DESC
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
            height: 180px;
            background: rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        .vehicle-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }
        /* Single image fix */
        .vehicle-card-image > img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }
        /* Tooltip for vehicle notes */
        .vehicle-tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.9);
            color: #fff;
            padding: 10px 12px;
            font-size: 0.75rem;
            line-height: 1.4;
            max-width: 250px;
            z-index: 10;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s, visibility 0.2s;
            pointer-events: none;
            white-space: pre-wrap;
            border: 1px solid var(--gold);
        }
        .vehicle-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: rgba(0,0,0,0.9);
        }
        .vehicle-card-image:hover .vehicle-tooltip {
            opacity: 1;
            visibility: visible;
        }
        /* Investment progress bar */
        .vehicle-investment-bar {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--border-color);
        }
        .vehicle-investment-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        .vehicle-investment-progress {
            height: 8px;
            background: rgba(255,255,255,0.1);
            overflow: hidden;
        }
        .vehicle-investment-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--gold), #c9a227);
            transition: width 0.3s ease;
        }
        .vehicle-card-image .no-image {
            color: var(--text-muted);
            font-size: 2rem;
        }
        /* Photo gallery slider */
        .vehicle-photo-gallery {
            display: flex;
            overflow-x: auto;
            scroll-behavior: smooth;
            scrollbar-width: none;
            -ms-overflow-style: none;
            gap: 0;
            height: 100%;
        }
        .vehicle-photo-gallery::-webkit-scrollbar {
            display: none;
        }
        .vehicle-photo-gallery img {
            flex: 0 0 100%;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }
        .vehicle-photo-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 24px;
            height: 24px;
            background: rgba(0,0,0,0.6);
            color: white;
            border: none;
            cursor: pointer;
            z-index: 3;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .vehicle-card:hover .vehicle-photo-nav {
            opacity: 1;
        }
        .vehicle-photo-nav.prev { left: 5px; }
        .vehicle-photo-nav.next { right: 5px; }
        .vehicle-photo-dots {
            position: absolute;
            bottom: 6px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 4px;
            z-index: 3;
        }
        .vehicle-photo-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: rgba(255,255,255,0.4);
            transition: background 0.2s;
        }
        .vehicle-photo-dot.active {
            background: var(--gold);
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
            border-radius: 0;
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
            border-radius: 0;
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
                                            $capFijo = floatval($cliente['capital_fijo']);
                                            $capVariable = floatval($cliente['capital_variable']);
                                            $capTotal = floatval($cliente['capital_total']);
                                            $rentFijoEuros = floatval($cliente['rent_fija']);
                                            $rentVariableEuros = floatval($cliente['rent_variable']);

                                            // Calcular porcentajes de rentabilidad
                                            $rentFijoPct = $capFijo > 0 ? ($rentFijoEuros / $capFijo) * 100 : ($capFijo > 0 ? $rentabilidadFija : 0);
                                            $rentVariablePct = $capVariable > 0 ? ($rentVariableEuros / $capVariable) * 100 : ($capVariable > 0 ? $rentabilidadVariableActual : 0);

                                            $mediaRent = 0;
                                            if ($capFijo > 0 && $capVariable > 0) {
                                                $mediaRent = (($rentFijoEuros + $rentVariableEuros) / $capTotal) * 100;
                                            } elseif ($capFijo > 0) {
                                                $mediaRent = $rentFijoPct;
                                            } elseif ($capVariable > 0) {
                                                $mediaRent = $rentVariablePct;
                                            }
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo escape($cliente['nombre'] . ' ' . $cliente['apellidos']); ?></strong>
                                            </td>
                                            <td><strong><?php echo formatMoney($capTotal); ?></strong></td>
                                            <td style="color: var(--blue-accent);"><?php echo formatMoney($capFijo); ?></td>
                                            <td style="color: var(--green-accent);"><?php echo formatMoney($capVariable); ?></td>
                                            <td style="color: var(--blue-accent);"><?php echo number_format($rentFijoPct, 1, ',', '.'); ?>%</td>
                                            <td style="color: var(--green-accent);"><?php echo number_format($rentVariablePct, 1, ',', '.'); ?>%</td>
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
                            <?php
                                // Collect all photos for this vehicle
                                $todasFotos = [];
                                if ($vehiculo['foto']) {
                                    $todasFotos[] = $vehiculo['foto'];
                                }
                                if (isset($fotosPorVehiculo[$vehiculo['id']])) {
                                    $todasFotos = array_merge($todasFotos, $fotosPorVehiculo[$vehiculo['id']]);
                                }
                                $vehiculoGalleryId = 'gallery-' . $vehiculo['id'];
                            ?>
                            <div class="vehicle-card">
                                <div class="vehicle-card-status <?php echo $vehiculo['estado']; ?>">
                                    <?php echo $estadoTextos[$vehiculo['estado']] ?? ucfirst(str_replace('_', ' ', $vehiculo['estado'])); ?>
                                </div>
                                <div class="vehicle-card-image">
                                    <?php if (!empty($vehiculo['notas'])): ?>
                                        <div class="vehicle-tooltip"><?php echo escape($vehiculo['notas']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($todasFotos)): ?>
                                        <?php if (count($todasFotos) > 1): ?>
                                            <button class="vehicle-photo-nav prev" onclick="scrollGallery('<?php echo $vehiculoGalleryId; ?>', -1)">‹</button>
                                            <button class="vehicle-photo-nav next" onclick="scrollGallery('<?php echo $vehiculoGalleryId; ?>', 1)">›</button>
                                            <div class="vehicle-photo-dots">
                                                <?php for ($i = 0; $i < count($todasFotos); $i++): ?>
                                                    <span class="vehicle-photo-dot <?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>"></span>
                                                <?php endfor; ?>
                                            </div>
                                            <div class="vehicle-photo-gallery" id="<?php echo $vehiculoGalleryId; ?>" data-count="<?php echo count($todasFotos); ?>">
                                                <?php foreach ($todasFotos as $foto): ?>
                                                    <img src="../<?php echo escape($foto); ?>" alt="<?php echo escape($vehiculo['marca'] . ' ' . $vehiculo['modelo']); ?>">
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <img src="../<?php echo escape($todasFotos[0]); ?>" alt="<?php echo escape($vehiculo['marca'] . ' ' . $vehiculo['modelo']); ?>">
                                        <?php endif; ?>
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
                                    <div class="vehicle-card-prices" style="grid-template-columns: 1fr 1fr;">
                                        <div class="vehicle-price-item">
                                            <div class="vehicle-price-label">Compra</div>
                                            <div class="vehicle-price-value compra"><?php echo formatMoney($vehiculo['precio_compra']); ?></div>
                                        </div>
                                        <div class="vehicle-price-item">
                                            <div class="vehicle-price-label">Venta Prevista</div>
                                            <div class="vehicle-price-value venta"><?php echo formatMoney($vehiculo['valor_venta_previsto']); ?></div>
                                        </div>
                                        <div class="vehicle-price-item">
                                            <div class="vehicle-price-label">Gastos</div>
                                            <div class="vehicle-price-value" style="color: var(--danger);"><?php echo formatMoney($vehiculo['gastos']); ?></div>
                                        </div>
                                        <div class="vehicle-price-item">
                                            <div class="vehicle-price-label">Prev. Gastos</div>
                                            <div class="vehicle-price-value" style="color: var(--text-muted);"><?php echo formatMoney($vehiculo['prevision_gastos'] ?? 0); ?></div>
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
                                    <?php
                                    // Investment progress bar - now using Compra + Prevision de gastos
                                    $capitalInvertidoVeh = $capitalPorVehiculo[$vehiculo['id']] ?? 0;
                                    $inversionNecesaria = floatval($vehiculo['precio_compra']) + floatval($vehiculo['prevision_gastos'] ?? 0);
                                    $porcentajeInvertido = $inversionNecesaria > 0 ? min(100, ($capitalInvertidoVeh / $inversionNecesaria) * 100) : 0;
                                    ?>
                                    <div class="vehicle-investment-bar">
                                        <div class="vehicle-investment-label">
                                            <span>Inversión</span>
                                            <span><?php echo formatMoney($capitalInvertidoVeh); ?> / <?php echo formatMoney($inversionNecesaria); ?></span>
                                        </div>
                                        <div class="vehicle-investment-progress">
                                            <div class="vehicle-investment-progress-fill" style="width: <?php echo number_format($porcentajeInvertido, 1); ?>%;"></div>
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
                        <div class="stat-panel-value"><?php echo formatMoney($capitalInvertidoVehiculos + $capitalReserva); ?></div>

                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin: 15px 0 8px; letter-spacing: 0.5px;">Por tipo de inversión</div>
                        <div class="stat-panel-row">
                            <span class="stat-panel-label fija">Rentabilidad Fija</span>
                            <span class="stat-panel-amount fija"><?php echo formatMoney($capitalFija); ?></span>
                        </div>
                        <div class="stat-panel-row">
                            <span class="stat-panel-label variable">Rentabilidad Variable</span>
                            <span class="stat-panel-amount variable"><?php echo formatMoney($capitalVariable); ?></span>
                        </div>

                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin: 15px 0 8px; letter-spacing: 0.5px;">Estado del capital</div>
                        <div class="stat-panel-row">
                            <span class="stat-panel-label" style="color: var(--green-accent);">
                                <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--green-accent); margin-right:8px;"></span>
                                Capital Invertido
                            </span>
                            <span class="stat-panel-amount" style="color: var(--green-accent);"><?php echo formatMoney($capitalInvertidoVehiculos); ?></span>
                        </div>
                        <div class="stat-panel-row">
                            <span class="stat-panel-label" style="color: var(--gold);">
                                <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--gold); margin-right:8px;"></span>
                                Capital en Reserva
                            </span>
                            <span class="stat-panel-amount" style="color: var(--gold);"><?php echo formatMoney($capitalReserva); ?></span>
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
        // Photo gallery navigation
        function scrollGallery(galleryId, direction) {
            var gallery = document.getElementById(galleryId);
            var imageWidth = gallery.offsetWidth;
            var count = parseInt(gallery.dataset.count);
            var currentScroll = gallery.scrollLeft;
            var currentIndex = Math.round(currentScroll / imageWidth);
            var newIndex = currentIndex + direction;

            if (newIndex < 0) newIndex = count - 1;
            if (newIndex >= count) newIndex = 0;

            gallery.scrollTo({ left: newIndex * imageWidth, behavior: 'smooth' });

            // Update dots
            var card = gallery.closest('.vehicle-card');
            var dots = card.querySelectorAll('.vehicle-photo-dot');
            dots.forEach(function(dot, i) {
                dot.classList.toggle('active', i === newIndex);
            });
        }

        // Update dots on scroll
        document.querySelectorAll('.vehicle-photo-gallery').forEach(function(gallery) {
            gallery.addEventListener('scroll', function() {
                var imageWidth = gallery.offsetWidth;
                var currentIndex = Math.round(gallery.scrollLeft / imageWidth);
                var card = gallery.closest('.vehicle-card');
                var dots = card.querySelectorAll('.vehicle-photo-dot');
                dots.forEach(function(dot, i) {
                    dot.classList.toggle('active', i === currentIndex);
                });
            });
        });

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
