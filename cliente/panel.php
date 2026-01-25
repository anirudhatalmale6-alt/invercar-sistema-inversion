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
if ($cliente['tipo_inversion'] === 'fija') {
    $rentabilidadMensual = floatval(getConfig('rentabilidad_fija', 5));
} else {
    $rentabilidadMensual = floatval(getConfig('rentabilidad_variable_actual', 14.8));
}

// Datos para el gráfico
$rentabilidadMap = [];
foreach ($rentabilidad as $r) {
    $rentabilidadMap[$r['semana']] = $r;
}
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

        @media (max-width: 768px) {
            .chart-container {
                overflow-x: auto;
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
                        <span class="tipo-badge <?php echo $cliente['tipo_inversion']; ?>">
                            Rentabilidad <?php echo ucfirst($cliente['tipo_inversion']); ?>
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
</body>
</html>
