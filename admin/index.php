<?php
/**
 * InverCar - Panel de Administración - Dashboard Premium
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
        SUM(CASE WHEN tipo_inversion = 'variable' THEN capital_invertido ELSE 0 END) as capital_variable,
        SUM(CASE WHEN tipo_inversion = 'fija' THEN 1 ELSE 0 END) as clientes_fija,
        SUM(CASE WHEN tipo_inversion = 'variable' THEN 1 ELSE 0 END) as clientes_variable
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

// Configuración
$rentabilidadFija = floatval(getConfig('rentabilidad_fija', 5));
$rentabilidadVariableActual = floatval(getConfig('rentabilidad_variable_actual', 14.8));

// Datos para gráfico de rentabilidad semanal (simulación de las últimas 9 semanas)
$rentabilidadSemanal = [];
for ($i = 1; $i <= 9; $i++) {
    // En producción esto vendría de la base de datos
    $rentabilidadSemanal[] = [
        'semana' => $i,
        'porcentaje' => $rentabilidadVariableActual * (0.7 + (rand(0, 60) / 100))
    ];
}
$maxRentabilidad = max(array_column($rentabilidadSemanal, 'porcentaje'));

// Últimos clientes registrados
$ultimosClientes = $db->query("
    SELECT id, nombre, apellidos, email, capital_invertido, tipo_inversion, created_at
    FROM clientes
    WHERE activo = 1 AND registro_completo = 1
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll();

// Mensajes no leídos
$mensajesNoLeidos = $db->query("SELECT COUNT(*) as total FROM contactos WHERE leido = 0")->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin InverCar</title>
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
                    <p>Bienvenido, administra los capitales invertidos de tus clientes.</p>
                </div>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo escape($_SESSION['admin_nombre']); ?></div>
                    </div>
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['admin_nombre'], 0, 1)); ?></div>
                </div>
            </div>

            <!-- Layout de 2 columnas -->
            <div class="dashboard-layout">
                <!-- Columna Principal -->
                <div class="dashboard-main">
                    <!-- Buscador -->
                    <div class="search-box">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <input type="text" placeholder="Buscar cliente..." id="searchInput">
                    </div>

                    <!-- Tabla de Clientes -->
                    <div class="card">
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Clientes</th>
                                        <th>Email</th>
                                        <th>Capital Invertido</th>
                                        <th>Tipo</th>
                                    </tr>
                                </thead>
                                <tbody id="clientesTable">
                                    <?php if (empty($ultimosClientes)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                            No hay clientes registrados
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($ultimosClientes as $cliente): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo escape($cliente['nombre'] . ' ' . $cliente['apellidos']); ?></strong>
                                            </td>
                                            <td style="color: var(--text-muted);"><?php echo escape($cliente['email']); ?></td>
                                            <td><strong><?php echo formatMoney($cliente['capital_invertido']); ?></strong></td>
                                            <td>
                                                <span class="badge <?php echo $cliente['tipo_inversion'] === 'fija' ? 'badge-info' : 'badge-success'; ?>">
                                                    Rentabilidad <?php echo ucfirst($cliente['tipo_inversion']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (!empty($ultimosClientes)): ?>
                        <div style="padding: 15px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: center; gap: 10px;">
                            <span class="pagination">
                                <a href="#">&laquo; Anterior</a>
                                <span class="active">1</span>
                                <a href="#">2</a>
                                <a href="#">3</a>
                                <a href="#">Siguiente &raquo;</a>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tarjetas de Rentabilidad -->
                    <div class="rent-cards">
                        <div class="rent-card">
                            <div class="rent-card-icon fija">€</div>
                            <div class="rent-card-title">Rentabilidad Fija</div>
                            <div class="rent-card-value fija"><?php echo formatMoney($statsClientes['capital_fija'] ?? 0); ?></div>
                            <div class="rent-card-detail">
                                Acumulado semanal
                                <span class="rent-card-percent up">▲ <?php echo number_format($rentabilidadFija, 1, ',', '.'); ?>%</span>
                            </div>
                        </div>
                        <div class="rent-card">
                            <div class="rent-card-icon variable">€</div>
                            <div class="rent-card-title">Rentabilidad Variable</div>
                            <div class="rent-card-value variable"><?php echo formatMoney($statsClientes['capital_variable'] ?? 0); ?></div>
                            <div class="rent-card-detail">
                                Acumulado semanal
                                <span class="rent-card-percent up">▲ <?php echo number_format($rentabilidadVariableActual, 1, ',', '.'); ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel Lateral -->
                <div class="dashboard-sidebar">
                    <!-- Capital Acumulado -->
                    <div class="stat-panel">
                        <div class="stat-panel-header">
                            <div class="stat-panel-icon">€</div>
                            <div class="stat-panel-title">Capital Acumulado</div>
                        </div>
                        <div class="stat-panel-value"><?php echo formatMoney($statsClientes['capital_total'] ?? 0); ?></div>

                        <div class="stat-panel-row">
                            <span class="stat-panel-label fija">Rentabilidad Fija</span>
                            <span class="stat-panel-amount fija"><?php echo formatMoney($statsClientes['capital_fija'] ?? 0); ?></span>
                        </div>
                        <div class="stat-panel-row">
                            <span class="stat-panel-label variable">Rentabilidad Variable</span>
                            <span class="stat-panel-amount variable"><?php echo formatMoney($statsClientes['capital_variable'] ?? 0); ?></span>
                        </div>
                    </div>

                    <!-- Gráfico de Rentabilidad Semanal -->
                    <div class="chart-card">
                        <div class="chart-title">Rentabilidad Semanal</div>
                        <div class="line-chart">
                            <div class="chart-y-axis">
                                <span>18%</span>
                                <span>12%</span>
                                <span>6%</span>
                                <span>0%</span>
                            </div>
                            <div class="chart-grid">
                                <div class="chart-grid-line"></div>
                                <div class="chart-grid-line"></div>
                                <div class="chart-grid-line"></div>
                                <div class="chart-grid-line"></div>
                            </div>
                            <div class="chart-bars-container">
                                <?php foreach ($rentabilidadSemanal as $renta): ?>
                                <div class="chart-bar-item">
                                    <div class="chart-bar-wrapper">
                                        <div class="chart-bar green" style="height: <?php echo ($renta['porcentaje'] / 18) * 100; ?>px;"></div>
                                    </div>
                                    <div class="chart-bar-label">Sem <?php echo $renta['semana']; ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="chart-legend">
                            <div class="chart-legend-item">
                                <div class="chart-legend-color" style="background: var(--blue-accent);"></div>
                                <span>Rentabilidad Fija</span>
                            </div>
                            <div class="chart-legend-item">
                                <div class="chart-legend-color" style="background: var(--green-accent);"></div>
                                <span>Rentabilidad Variable</span>
                            </div>
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
        // Filtro de búsqueda simple
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const filter = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#clientesTable tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
