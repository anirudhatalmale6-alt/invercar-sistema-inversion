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

// Obtener vehículos activos (públicos para clientes)
$vehiculosActivos = $db->query("
    SELECT v.id, v.referencia, v.marca, v.modelo, v.version, v.anio, v.kilometros,
           v.precio_compra, v.valor_venta_previsto, v.foto, v.estado, v.fecha_compra
    FROM vehiculos v
    WHERE v.estado IN ('en_espera', 'en_preparacion', 'en_venta', 'reservado')
    AND v.publico = 1
    ORDER BY v.fecha_compra DESC, v.created_at DESC
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

// Estados y fases del vehículo
$estadoFases = [
    'en_espera' => ['orden' => 1, 'nombre' => 'Espera', 'color' => '#d946ef'],
    'en_preparacion' => ['orden' => 2, 'nombre' => 'Preparación', 'color' => '#eab308'],
    'en_venta' => ['orden' => 3, 'nombre' => 'En Venta', 'color' => '#8b5cf6'],
    'reservado' => ['orden' => 4, 'nombre' => 'Reservado', 'color' => '#1f2937']
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

            <!-- Dashboard Cards -->
            <div class="dashboard-grid">
                <div class="info-card highlight">
                    <h3>Capital Invertido</h3>
                    <div class="value"><?php echo formatMoney($cliente['capital_invertido']); ?></div>
                    <div class="label">Tu inversión actual</div>
                </div>

                <div class="info-card">
                    <h3>Tipo de Inversión</h3>
                    <div class="value">
                        <span class="tipo-badge <?php echo $tipoInversion; ?>">
                            Rentabilidad <?php echo ucfirst($tipoInversion); ?>
                        </span>
                    </div>
                    <div class="label"><?php echo formatPercent($rentabilidadMensual); ?> mensual</div>
                </div>

                <div class="info-card">
                    <h3>Rentabilidad Acumulada</h3>
                    <div class="value"><?php echo formatPercent($rentabilidadAcumuladaPct); ?></div>
                    <div class="label"><?php echo formatMoney($rentabilidadAcumuladaEuros); ?> en beneficios</div>
                </div>

                <div class="info-card">
                    <h3>Capital + Beneficios</h3>
                    <div class="value"><?php echo formatMoney($cliente['capital_invertido'] + $rentabilidadAcumuladaEuros); ?></div>
                    <div class="label">Valor total actual</div>
                </div>
            </div>

            <!-- Vehículos en Activo -->
            <?php if (!empty($vehiculosActivos)): ?>
            <div class="section-title">Vehículos en Cartera</div>
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
                    $barClass = '';
                    if ($porcentajeProgreso > 100) {
                        $barClass = 'phase-bar-danger';
                    } elseif ($porcentajeProgreso > 75) {
                        $barClass = 'phase-bar-warning';
                    }

                    // Estado actual
                    $estadoActual = $vehiculo['estado'];
                    $estadoInfo = $estadoFases[$estadoActual] ?? ['orden' => 0, 'nombre' => ucfirst(str_replace('_', ' ', $estadoActual)), 'color' => '#6b7280'];

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
                                <div class="phase-bar-progress <?php echo $barClass; ?>" style="width: <?php echo min(100, $porcentajeProgreso); ?>%;"></div>
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
    </script>
</body>
</html>
