<?php
/**
 * InverCar - Panel del Cliente
 */
require_once __DIR__ . '/../includes/init.php';

if (!isClienteLogueado()) {
    redirect('login.php');
}

$db = getDB();

// Obtener datos del cliente
$stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$_SESSION['cliente_id']]);
$cliente = $stmt->fetch();

if (!$cliente) {
    redirect('logout.php');
}

// Obtener rentabilidad de las últimas 9 semanas
$stmt = $db->prepare("
    SELECT semana, rentabilidad_porcentaje, rentabilidad_euros
    FROM rentabilidad_semanal
    WHERE cliente_id = ?
    ORDER BY anio DESC, semana ASC
    LIMIT 9
");
$stmt->execute([$cliente['id']]);
$rentabilidad = $stmt->fetchAll();

// Calcular rentabilidad acumulada
$rentabilidadAcumuladaPct = 0;
$rentabilidadAcumuladaEuros = 0;
foreach ($rentabilidad as $r) {
    $rentabilidadAcumuladaPct += $r['rentabilidad_porcentaje'];
    $rentabilidadAcumuladaEuros += $r['rentabilidad_euros'];
}

// Rentabilidad según tipo
$tipoInversion = $cliente['tipo_inversion'] ?? 'fija';
if ($tipoInversion === 'fija') {
    $rentabilidadMensual = floatval(getConfig('rentabilidad_fija', 5));
} else {
    $rentabilidadMensual = floatval(getConfig('rentabilidad_variable_actual', 14.8));
}

// Datos para el gráfico
$rentabilidadMap = [];
foreach ($rentabilidad as $r) {
    $rentabilidadMap[$r['semana']] = $r;
}

// Obtener capital del cliente desde la tabla capital (por tipo de inversión)
$stmtCapital = $db->prepare("
    SELECT
        tipo_inversion,
        COALESCE(SUM(importe_ingresado), 0) as total_ingresado,
        COALESCE(SUM(importe_retirado), 0) as total_retirado,
        COALESCE(SUM(rentabilidad), 0) as total_rentabilidad
    FROM capital
    WHERE cliente_id = ? AND activo = 1
    GROUP BY tipo_inversion
");
$stmtCapital->execute([$cliente['id']]);
$capitalData = [];
foreach ($stmtCapital->fetchAll() as $row) {
    $capitalData[$row['tipo_inversion']] = $row;
}

// Calcular capital por tipo
$capitalFija = ($capitalData['fija']['total_ingresado'] ?? 0) - ($capitalData['fija']['total_retirado'] ?? 0);
$capitalVariable = ($capitalData['variable']['total_ingresado'] ?? 0) - ($capitalData['variable']['total_retirado'] ?? 0);
$capitalTotal = $capitalFija + $capitalVariable;
$rentabilidadFijaEuros = $capitalData['fija']['total_rentabilidad'] ?? 0;
$rentabilidadVariableEuros = $capitalData['variable']['total_rentabilidad'] ?? 0;
$rentabilidadTotalEuros = $rentabilidadFijaEuros + $rentabilidadVariableEuros;

// Tasas de rentabilidad configuradas
$tasaFija = floatval(getConfig('rentabilidad_fija', 5));
$tasaVariable = floatval(getConfig('rentabilidad_variable_actual', 14.8));

// Calcular porcentaje de rentabilidad real
$porcentajeRentFija = $capitalFija > 0 ? ($rentabilidadFijaEuros / $capitalFija) * 100 : $tasaFija;
$porcentajeRentVariable = $capitalVariable > 0 ? ($rentabilidadVariableEuros / $capitalVariable) * 100 : $tasaVariable;

// Datos para gráfico de barras de capital (últimas 4 semanas) - igual que admin
$semanaActual = (int) date('W');
$anioActual = (int) date('Y');
$capitalSemanal = [];
for ($i = 3; $i >= 0; $i--) {
    $semNum = $semanaActual - $i;
    $anio = $anioActual;
    if ($semNum <= 0) {
        $semNum += 52;
        $anio--;
    }

    // Calcular fechas de inicio y fin de la semana
    $inicioSemana = new DateTime();
    $inicioSemana->setISODate($anio, $semNum, 1);
    $finSemana = clone $inicioSemana;
    $finSemana->modify('+6 days');

    $fechaInicio = $inicioSemana->format('Y-m-d');
    $fechaFin = $finSemana->format('Y-m-d');

    // Obtener entradas de capital del cliente en esa semana
    $stmtEntrada = $db->prepare("
        SELECT COALESCE(SUM(importe_ingresado), 0) as total
        FROM capital
        WHERE cliente_id = ? AND fecha_ingreso BETWEEN ? AND ? AND activo = 1
    ");
    $stmtEntrada->execute([$cliente['id'], $fechaInicio, $fechaFin]);
    $entrada = floatval($stmtEntrada->fetch()['total']);

    // Obtener salidas de capital del cliente en esa semana
    $stmtSalida = $db->prepare("
        SELECT COALESCE(SUM(importe_retirado), 0) as total
        FROM capital
        WHERE cliente_id = ? AND fecha_retirada BETWEEN ? AND ? AND activo = 1
    ");
    $stmtSalida->execute([$cliente['id'], $fechaInicio, $fechaFin]);
    $salida = floatval($stmtSalida->fetch()['total']);

    $capitalSemanal[] = [
        'semana' => $semNum,
        'label' => 'S' . $semNum,
        'entrada' => $entrada,
        'salida' => $salida
    ];
}

// Datos para el gráfico de líneas (últimas 9 semanas)
$semanasGrafico = [];

// Obtener datos históricos de rentabilidad del cliente
$stmtHistorico = $db->prepare("
    SELECT semana, anio, rentabilidad_porcentaje
    FROM rentabilidad_semanal
    WHERE cliente_id = ?
    ORDER BY anio DESC, semana DESC
    LIMIT 18
");
$stmtHistorico->execute([$cliente['id']]);
$historialRent = $stmtHistorico->fetchAll();

for ($i = 8; $i >= 0; $i--) {
    $semNum = $semanaActual - $i;
    $anio = $anioActual;
    if ($semNum <= 0) {
        $semNum += 52;
        $anio--;
    }

    // Buscar datos en histórico del cliente
    $rentSemana = 0;
    foreach ($historialRent as $hist) {
        if ($hist['semana'] == $semNum && $hist['anio'] == $anio) {
            $rentSemana = floatval($hist['rentabilidad_porcentaje']);
            break;
        }
    }

    // Para semana actual, usar la rentabilidad calculada si no hay histórico
    if ($i === 0 && $rentSemana === 0) {
        if ($capitalTotal > 0) {
            $rentSemana = ($rentabilidadTotalEuros / $capitalTotal) * 100;
        }
    }

    $semanasGrafico[] = [
        'semana' => $semNum,
        'anio' => $anio,
        'label' => 'S' . $semNum,
        'fija' => $capitalFija > 0 ? $tasaFija : 0,
        'variable' => $capitalVariable > 0 ? ($i === 0 ? $porcentajeRentVariable : 0) : 0,
        'media' => $rentSemana
    ];
}

// Filtro de estado para vehículos (solo estados válidos)
$filtroEstado = $_GET['filtro_estado'] ?? 'todos';
$estadosValidos = ['en_espera', 'en_preparacion', 'en_venta', 'reservado', 'vendido', 'todos'];
if (!in_array($filtroEstado, $estadosValidos)) {
    $filtroEstado = 'todos';
}

// Obtener vehículos activos (públicos para clientes) - incluyendo vendidos
if ($filtroEstado === 'todos') {
    $vehiculosActivos = $db->query("
        SELECT v.id, v.referencia, v.marca, v.modelo, v.version, v.anio, v.kilometros,
               v.precio_compra, v.valor_venta_previsto, v.foto, v.estado, v.fecha_compra
        FROM vehiculos v
        WHERE v.estado IN ('en_espera', 'en_preparacion', 'en_venta', 'reservado', 'vendido')
        AND v.publico = 1
        ORDER BY v.fecha_compra DESC, v.created_at DESC
    ")->fetchAll();
} else {
    $stmt = $db->prepare("
        SELECT v.id, v.referencia, v.marca, v.modelo, v.version, v.anio, v.kilometros,
               v.precio_compra, v.valor_venta_previsto, v.foto, v.estado, v.fecha_compra
        FROM vehiculos v
        WHERE v.estado = ?
        AND v.publico = 1
        ORDER BY v.fecha_compra DESC, v.created_at DESC
    ");
    $stmt->execute([$filtroEstado]);
    $vehiculosActivos = $stmt->fetchAll();
}

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

// Calcular media de días de venta global (no hay proveedor_id en la BD)
$mediaVentaGlobal = 75; // Por defecto 75 días
$stmtMedia = $db->query("
    SELECT AVG(DATEDIFF(fecha_venta, fecha_compra)) as media_dias
    FROM vehiculos
    WHERE estado = 'vendido'
    AND fecha_venta IS NOT NULL
    AND fecha_compra IS NOT NULL
");
$mediaResult = $stmtMedia->fetch();
if ($mediaResult && $mediaResult['media_dias']) {
    $mediaVentaGlobal = round($mediaResult['media_dias']);
}

// Estados y fases del vehículo (En Espera = naranja)
$estadoFases = [
    'en_espera' => ['orden' => 1, 'nombre' => 'Espera', 'color' => '#f97316'],
    'en_preparacion' => ['orden' => 2, 'nombre' => 'Preparación', 'color' => '#eab308'],
    'en_venta' => ['orden' => 3, 'nombre' => 'En Venta', 'color' => '#8b5cf6'],
    'reservado' => ['orden' => 4, 'nombre' => 'Reservado', 'color' => '#1f2937'],
    'vendido' => ['orden' => 5, 'nombre' => 'Vendido', 'color' => '#22c55e']
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Panel - InverCar</title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Raleway', sans-serif; }

        .panel-header {
            background: var(--secondary-color);
            padding: 20px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .panel-header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-nav {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .panel-nav a {
            color: var(--text-muted);
            transition: color 0.3s;
        }

        .panel-nav a:hover {
            color: var(--primary-color);
        }

        .panel-content {
            padding: 40px 0;
            min-height: calc(100vh - 80px);
        }

        .welcome-section {
            margin-bottom: 40px;
        }

        .welcome-section h1 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .welcome-section p {
            color: var(--text-muted);
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .info-card {
            background: var(--card-bg);
            border-radius: 0;
            padding: 25px;
            border: 1px solid var(--border-color);
        }

        .info-card h3 {
            color: var(--text-muted);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .info-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .info-card .label {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .info-card.highlight {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
        }

        .info-card.highlight h3,
        .info-card.highlight .value,
        .info-card.highlight .label {
            color: white;
        }

        .chart-section {
            background: var(--card-bg);
            border-radius: 0;
            padding: 30px;
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
        }

        .chart-section h2 {
            margin-bottom: 25px;
            font-size: 1.3rem;
        }

        .chart-container {
            display: flex;
            align-items: flex-end;
            gap: 15px;
            height: 200px;
            padding: 20px 0;
        }

        .chart-bar {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .chart-bar .bar {
            width: 100%;
            max-width: 50px;
            background: linear-gradient(to top, var(--primary-color), var(--primary-dark));
            border-radius: 0;
            min-height: 20px;
            transition: height 0.5s ease;
        }

        .chart-bar .bar-value {
            font-size: 0.75rem;
            color: var(--primary-color);
            font-weight: 600;
        }

        .chart-bar .bar-label {
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        .chart-bar.empty .bar {
            background: var(--border-color);
        }

        .tipo-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 0;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .tipo-badge.fija {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .tipo-badge.variable {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
        }

        /* Rentabilidad cards */
        .rent-big-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        @media (max-width: 768px) {
            .rent-big-cards { grid-template-columns: 1fr; }
        }
        .rent-big-card {
            background: var(--card-bg);
            border-radius: 0;
            border: 1px solid var(--border-color);
            padding: 25px;
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
            color: #3b82f6;
        }
        .rent-big-icon.variable {
            background: rgba(34, 197, 94, 0.2);
            color: var(--success);
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
        .rent-big-value.fija { color: #3b82f6; }
        .rent-big-value.variable { color: var(--success); }
        .rent-big-percent {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            background: rgba(34, 197, 94, 0.15);
            border-radius: 0;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--success);
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

        /* Chart card */
        .chart-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            padding: 25px;
            margin-bottom: 25px;
        }
        .chart-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .chart-card-header h2 {
            font-size: 1.1rem;
            margin: 0;
        }
        .chart-card-header span {
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .line-chart-container {
            position: relative;
            height: 180px;
        }

        /* Stat panel for capital */
        .stat-panel {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            padding: 25px;
            margin-bottom: 25px;
        }
        .stat-panel-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }
        .stat-panel-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(212, 168, 75, 0.2);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
        }
        .stat-panel-title {
            font-size: 1rem;
            font-weight: 600;
        }
        .stat-panel-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        .stat-panel-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .stat-panel-row:last-child {
            border-bottom: none;
        }
        .stat-panel-label {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .stat-panel-label.fija { color: #3b82f6; }
        .stat-panel-label.variable { color: var(--success); }
        .stat-panel-amount {
            font-size: 0.9rem;
            font-weight: 600;
        }
        .stat-panel-amount.fija { color: #3b82f6; }
        .stat-panel-amount.variable { color: var(--success); }

        .profile-section {
            background: var(--card-bg);
            border-radius: 0;
            padding: 30px;
            border: 1px solid var(--border-color);
        }

        .profile-section h2 {
            margin-bottom: 20px;
            font-size: 1.3rem;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .profile-item {
            color: var(--text-muted);
        }

        .profile-item strong {
            display: block;
            color: var(--text-light);
            margin-top: 5px;
        }

        /* Vehicle cards */
        .section-title {
            font-size: 1.2rem;
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
            background: var(--primary-color);
            border-radius: 2px;
        }

        .vehicle-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .vehicle-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            overflow: hidden;
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

        .vehicle-card-image .no-image {
            color: var(--text-muted);
            font-size: 2rem;
        }

        .vehicle-photo-gallery {
            display: flex;
            overflow-x: auto;
            scroll-behavior: smooth;
            scrollbar-width: none;
            height: 100%;
        }

        .vehicle-photo-gallery::-webkit-scrollbar { display: none; }

        .vehicle-photo-gallery img {
            flex: 0 0 100%;
            width: 100%;
            height: 100%;
            object-fit: cover;
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

        .vehicle-card:hover .vehicle-photo-nav { opacity: 1; }

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

        .vehicle-photo-dot.active { background: var(--primary-color); }

        .vehicle-card-body {
            padding: 15px;
        }

        .vehicle-card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-light);
        }

        .vehicle-card-subtitle {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 15px;
        }

        .vehicle-card-prices {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }

        .vehicle-price-item {
            text-align: center;
            padding: 10px;
            background: rgba(0,0,0,0.2);
        }

        .vehicle-price-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .vehicle-price-value {
            font-size: 0.95rem;
            font-weight: 600;
        }

        .vehicle-price-value.venta { color: var(--success); }

        /* Phase timeline */
        .phase-timeline {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }

        .phase-timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .phase-timeline-days {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-light);
        }

        .phase-timeline-expected {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .phase-bar-container {
            height: 24px;
            background: rgba(255,255,255,0.05);
            position: relative;
            overflow: hidden;
        }

        .phase-bar-progress {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
            transition: width 0.3s ease;
        }

        .phase-bar-warning {
            background: linear-gradient(90deg, #f59e0b, #d97706);
        }

        .phase-bar-danger {
            background: linear-gradient(90deg, #ef4444, #dc2626);
        }

        .phase-markers {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
        }

        .phase-marker {
            text-align: center;
            flex: 1;
        }

        .phase-marker-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin: 0 auto 4px;
        }

        .phase-marker-dot.active {
            box-shadow: 0 0 0 3px rgba(212, 168, 75, 0.3);
        }

        .phase-marker-label {
            font-size: 0.65rem;
            color: var(--text-muted);
        }

        @media (max-width: 768px) {
            .chart-container {
                overflow-x: auto;
            }
            .vehicle-grid {
                grid-template-columns: 1fr;
            }
            .dashboard-layout {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="panel-header">
        <div class="container">
            <a href="../index.php" class="logo"><img src="../assets/images/logo-invercar.png" alt="InverCar" style="height: 80px; max-width: 220px;"></a>
            <nav class="panel-nav">
                <span style="color: var(--text-muted);">Hola, <?php echo escape($cliente['nombre']); ?></span>
                <a href="logout.php" class="btn btn-outline" style="padding: 8px 15px;">Cerrar sesión</a>
            </nav>
        </div>
    </header>

    <!-- Panel Content -->
    <div class="panel-content">
        <div class="container">
            <!-- Welcome -->
            <div class="welcome-section">
                <h1>Mi Panel de Inversión</h1>
                <p>Bienvenido a tu área personal. Aquí puedes ver el estado de tu inversión.</p>
            </div>

            <!-- Tarjetas de Rentabilidad (Arriba) - Igual que Admin -->
            <div class="rent-big-cards">
                <div class="rent-big-card">
                    <div class="rent-big-header">
                        <div class="rent-big-icon fija">€</div>
                        <div class="rent-big-title">Rentabilidad Fija</div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <div class="rent-big-value fija"><?php echo formatMoney($rentabilidadFijaEuros); ?></div>
                            <div class="rent-big-percent">▲ <?php echo number_format($tasaFija, 1, ',', '.'); ?>%</div>
                            <div class="rent-big-capital">
                                Capital invertido: <strong><?php echo formatMoney($capitalFija); ?></strong>
                            </div>
                        </div>
                        <div style="border-left: 1px solid var(--border-color); padding-left: 20px;">
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 8px;">Entrada/Salida Capital (4 sem)</div>
                            <div style="height: 80px;">
                                <canvas id="capitalBarChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="rent-big-card">
                    <div class="rent-big-header">
                        <div class="rent-big-icon variable">€</div>
                        <div class="rent-big-title">Rentabilidad Variable</div>
                    </div>
                    <!-- Dividido en dos columnas: Prevista y Obtenida -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 10px;">
                        <div style="border-right: 1px solid var(--border-color); padding-right: 20px;">
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 8px;">Rentabilidad Prevista</div>
                            <div style="font-size: 1.8rem; font-weight: 700; color: #f59e0b;"><?php echo formatMoney($rentabilidadVariableEuros); ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 8px;">Capital variable activo</div>
                            <div style="font-size: 0.9rem; font-weight: 600; color: #f59e0b; margin-top: 4px;"><?php echo number_format($tasaVariable, 1, ',', '.'); ?>%</div>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 8px;">Rentabilidad Obtenida</div>
                            <div style="font-size: 1.8rem; font-weight: 700; color: var(--success);"><?php echo formatMoney($rentabilidadVariableEuros); ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 8px;">Beneficios realizados</div>
                            <div style="font-size: 0.9rem; font-weight: 600; color: var(--success); margin-top: 4px;"><?php echo number_format($porcentajeRentVariable, 1, ',', '.'); ?>%</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Layout principal: Gráfico izquierda + Capital derecha -->
            <div class="dashboard-layout" style="display: grid; grid-template-columns: 1fr 300px; gap: 25px; margin-bottom: 25px;">
                <!-- Columna Principal - Gráfico -->
                <div class="chart-card" style="margin-bottom: 0;">
                    <div class="chart-card-header">
                        <h2>Rentabilidad Media por Semana</h2>
                        <span>Últimas 9 semanas</span>
                    </div>
                    <div class="line-chart-container">
                        <canvas id="rentabilidadLineChart"></canvas>
                    </div>
                </div>

                <!-- Panel Lateral - Mi Capital -->
                <div class="stat-panel" style="margin-bottom: 0;">
                    <div class="stat-panel-header">
                        <div class="stat-panel-icon">€</div>
                        <div class="stat-panel-title">Mi Capital</div>
                    </div>
                    <div class="stat-panel-value"><?php echo formatMoney($capitalTotal); ?></div>
                    <div style="border-top: 1px solid var(--border-color); padding-top: 10px; margin-top: 10px;">
                        <div class="stat-panel-value" style="font-size: 1.6rem; color: #3b82f6;"><?php echo formatMoney($capitalTotal + $rentabilidadTotalEuros); ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">Total + Rentabilidad</div>
                    </div>

                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin: 15px 0 8px; letter-spacing: 0.5px;">Por tipo de inversión</div>
                    <div class="stat-panel-row">
                        <span class="stat-panel-label fija">Rentabilidad Fija</span>
                        <span class="stat-panel-amount fija"><?php echo formatMoney($capitalFija); ?></span>
                    </div>
                    <div class="stat-panel-row">
                        <span class="stat-panel-label variable">Rentabilidad Variable</span>
                        <span class="stat-panel-amount variable"><?php echo formatMoney($capitalVariable); ?></span>
                    </div>

                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin: 15px 0 8px; letter-spacing: 0.5px;">Rentabilidad generada</div>
                    <div class="stat-panel-row">
                        <span class="stat-panel-label fija">Rent. Fija</span>
                        <span class="stat-panel-amount fija"><?php echo formatMoney($rentabilidadFijaEuros); ?></span>
                    </div>
                    <div class="stat-panel-row">
                        <span class="stat-panel-label variable">Rent. Variable</span>
                        <span class="stat-panel-amount variable"><?php echo formatMoney($rentabilidadVariableEuros); ?></span>
                    </div>
                    <div class="stat-panel-row">
                        <span class="stat-panel-label" style="font-weight: 600; color: var(--text-light);">Total Rentabilidad</span>
                        <span class="stat-panel-amount" style="color: var(--primary-color);"><?php echo formatMoney($rentabilidadTotalEuros); ?></span>
                    </div>
                </div>
            </div>

            <!-- Vehículos en Cartera -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <div class="section-title" style="margin-bottom: 0;">Vehículos en Cartera</div>
                <select id="filtroEstado" onchange="window.location.href='panel.php?filtro_estado=' + this.value" style="padding: 8px 12px; background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-light); font-size: 0.85rem; border-radius: 0;">
                    <option value="todos" <?php echo $filtroEstado === 'todos' ? 'selected' : ''; ?>>Todos</option>
                    <option value="en_espera" <?php echo $filtroEstado === 'en_espera' ? 'selected' : ''; ?>>En Espera</option>
                    <option value="en_preparacion" <?php echo $filtroEstado === 'en_preparacion' ? 'selected' : ''; ?>>En Preparación</option>
                    <option value="en_venta" <?php echo $filtroEstado === 'en_venta' ? 'selected' : ''; ?>>En Venta</option>
                    <option value="reservado" <?php echo $filtroEstado === 'reservado' ? 'selected' : ''; ?>>Reservado</option>
                    <option value="vendido" <?php echo $filtroEstado === 'vendido' ? 'selected' : ''; ?>>Vendido</option>
                </select>
            </div>
            <?php if (!empty($vehiculosActivos)): ?>
            <div class="vehicle-grid">
                <?php foreach ($vehiculosActivos as $vehiculo):
                    // Calcular días desde la fecha de compra
                    $diasDesdeCompra = 0;
                    if (!empty($vehiculo['fecha_compra'])) {
                        $fechaCompra = new DateTime($vehiculo['fecha_compra']);
                        $hoy = new DateTime();
                        $diasDesdeCompra = $hoy->diff($fechaCompra)->days;
                    }

                    // Calcular fecha prevista de venta usando media global
                    $diasPrevistos = $mediaVentaGlobal;

                    $fechaPrevista = null;
                    if (!empty($vehiculo['fecha_compra'])) {
                        $fechaPrevista = (new DateTime($vehiculo['fecha_compra']))->modify("+{$diasPrevistos} days");
                    }

                    // Porcentaje de progreso
                    $porcentajeProgreso = min(100, ($diasDesdeCompra / $diasPrevistos) * 100);
                    // Si es vendido, mostrar 100%
                    if ($vehiculo['estado'] === 'vendido') {
                        $porcentajeProgreso = 100;
                    }

                    // Estado actual
                    $estadoActual = $vehiculo['estado'];
                    $estadoInfo = $estadoFases[$estadoActual] ?? ['orden' => 0, 'nombre' => ucfirst(str_replace('_', ' ', $estadoActual)), 'color' => '#6b7280'];
                    // Color de la barra de progreso = color del estado actual
                    $barColor = $estadoInfo['color'];

                    // Collect all photos
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
                    <div class="vehicle-card-status" style="background: <?php echo $estadoInfo['color']; ?>; color: #fff;">
                        <?php echo $estadoInfo['nombre']; ?>
                    </div>
                    <div class="vehicle-card-image">
                        <?php if (!empty($todasFotos)): ?>
                            <?php if (count($todasFotos) > 1): ?>
                                <button class="vehicle-photo-nav prev" onclick="scrollGallery('<?php echo $vehiculoGalleryId; ?>', -1)">&#8249;</button>
                                <button class="vehicle-photo-nav next" onclick="scrollGallery('<?php echo $vehiculoGalleryId; ?>', 1)">&#8250;</button>
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
                            <span class="no-image">&#128663;</span>
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
                                <div class="vehicle-price-label">Venta Prevista</div>
                                <div class="vehicle-price-value venta"><?php echo formatMoney($vehiculo['valor_venta_previsto']); ?></div>
                            </div>
                            <div class="vehicle-price-item">
                                <div class="vehicle-price-label">Fecha Prevista</div>
                                <div class="vehicle-price-value" style="color: var(--text-light);">
                                    <?php echo $fechaPrevista ? $fechaPrevista->format('d/m/Y') : '-'; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Phase Timeline -->
                        <div class="phase-timeline">
                            <div class="phase-timeline-header">
                                <div class="phase-timeline-days">
                                    <span style="color: <?php echo $diasDesdeCompra > $diasPrevistos ? 'var(--danger)' : 'var(--success)'; ?>;">
                                        <?php echo $diasDesdeCompra; ?> días
                                    </span>
                                </div>
                                <div class="phase-timeline-expected">
                                    Previsto: <?php echo $diasPrevistos; ?> días
                                </div>
                            </div>
                            <div class="phase-bar-container">
                                <div class="phase-bar-progress" style="width: <?php echo min(100, $porcentajeProgreso); ?>%; background: <?php echo $barColor; ?>;"></div>
                            </div>
                            <div class="phase-markers">
                                <?php foreach ($estadoFases as $key => $fase): ?>
                                <div class="phase-marker">
                                    <div class="phase-marker-dot <?php echo $estadoActual === $key ? 'active' : ''; ?>"
                                         style="background: <?php echo $fase['color']; ?>;"></div>
                                    <div class="phase-marker-label"><?php echo $fase['nombre']; ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="background: var(--card-bg); border: 1px solid var(--border-color); padding: 40px; text-align: center; color: var(--text-muted); margin-bottom: 30px;">
                No hay vehículos con el filtro seleccionado
            </div>
            <?php endif; ?>

            <!-- Chart Section -->
            <div class="chart-section">
                <h2>Rentabilidad de las últimas 9 semanas</h2>
                <div class="chart-container">
                    <?php
                    $maxRentabilidad = 0;
                    foreach ($rentabilidadMap as $r) {
                        if ($r['rentabilidad_porcentaje'] > $maxRentabilidad) {
                            $maxRentabilidad = $r['rentabilidad_porcentaje'];
                        }
                    }
                    if ($maxRentabilidad == 0) $maxRentabilidad = 10;

                    for ($i = 1; $i <= 9; $i++):
                        $renta = $rentabilidadMap[$i] ?? null;
                        $porcentaje = $renta ? $renta['rentabilidad_porcentaje'] : 0;
                        $altura = $porcentaje > 0 ? ($porcentaje / $maxRentabilidad * 150) : 20;
                    ?>
                    <div class="chart-bar <?php echo $porcentaje == 0 ? 'empty' : ''; ?>">
                        <div class="bar-value"><?php echo $porcentaje > 0 ? formatPercent($porcentaje) : '-'; ?></div>
                        <div class="bar" style="height: <?php echo $altura; ?>px;"></div>
                        <div class="bar-label">Sem <?php echo $i; ?></div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Profile Section -->
            <div class="profile-section">
                <h2>Mis Datos</h2>
                <div class="profile-grid">
                    <div class="profile-item">
                        Nombre completo
                        <strong><?php echo escape($cliente['nombre'] . ' ' . $cliente['apellidos']); ?></strong>
                    </div>
                    <div class="profile-item">
                        Email
                        <strong><?php echo escape($cliente['email']); ?></strong>
                    </div>
                    <div class="profile-item">
                        DNI/NIE
                        <strong><?php echo escape($cliente['dni']); ?></strong>
                    </div>
                    <div class="profile-item">
                        Teléfono
                        <strong><?php echo escape($cliente['telefono']); ?></strong>
                    </div>
                    <div class="profile-item">
                        Dirección
                        <strong><?php echo escape($cliente['direccion']); ?></strong>
                    </div>
                    <div class="profile-item">
                        Localidad
                        <strong><?php echo escape($cliente['codigo_postal'] . ' ' . $cliente['poblacion'] . ', ' . $cliente['provincia']); ?></strong>
                    </div>
                    <div class="profile-item">
                        País
                        <strong><?php echo escape($cliente['pais']); ?></strong>
                    </div>
                    <div class="profile-item">
                        Cliente desde
                        <strong><?php echo date('d/m/Y', strtotime($cliente['created_at'])); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> InverCar. Todos los derechos reservados.</p>
        </div>
    </footer>

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

        // Gráfico de barras de capital (entrada/salida) - igual que admin
        const ctxBar = document.getElementById('capitalBarChart');
        if (ctxBar) {
            new Chart(ctxBar.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($capitalSemanal, 'label')); ?>,
                    datasets: [
                        {
                            label: 'Entrada',
                            data: <?php echo json_encode(array_column($capitalSemanal, 'entrada')); ?>,
                            backgroundColor: '#22c55e',
                            borderWidth: 0
                        },
                        {
                            label: 'Salida',
                            data: <?php echo json_encode(array_column($capitalSemanal, 'salida')); ?>,
                            backgroundColor: '#ef4444',
                            borderWidth: 0
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(20, 20, 20, 0.95)',
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(context.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: '#888', font: { size: 9 } }
                        },
                        y: {
                            display: false,
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Gráfico de Rentabilidad Media por Semana
        const ctx = document.getElementById('rentabilidadLineChart');
        if (ctx) {
            new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($semanasGrafico, 'label')); ?>,
                    datasets: [
                        {
                            label: 'Fija',
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
                            label: 'Variable',
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
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245, 158, 11, 0.15)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#f59e0b',
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
                                padding: 15,
                                font: { size: 10 }
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
                            max: 25,
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
        }
    </script>
</body>
</html>
