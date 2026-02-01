<?php
/**
 * InverCar - Movimientos del Cliente
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

// Obtener movimientos de capital del cliente
$stmt = $db->prepare("
    SELECT c.*,
           v.marca, v.modelo,
           a.descripcion as apunte_descripcion
    FROM capital c
    LEFT JOIN vehiculos v ON c.vehiculo_id = v.id
    LEFT JOIN apuntes a ON c.apunte_id = a.id
    WHERE c.cliente_id = ?
    ORDER BY c.fecha_ingreso DESC, c.created_at DESC
");
$stmt->execute([$cliente['id']]);
$movimientos = $stmt->fetchAll();

// Calcular totales
$totalIngresado = 0;
$totalRetirado = 0;
$totalRentabilidad = 0;
foreach ($movimientos as $m) {
    $totalIngresado += floatval($m['importe_ingresado']);
    $totalRetirado += floatval($m['importe_retirado']);
    $totalRentabilidad += floatval($m['rentabilidad']);
}
$saldoActual = $totalIngresado - $totalRetirado;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos - InverCar</title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg">
    <link rel="stylesheet" href="../assets/css/cliente.css">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="cliente-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1>Movimientos</h1>
                    <p>Historial de movimientos de capital</p>
                </div>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo escape($cliente['nombre']); ?></div>
                    </div>
                    <div class="user-avatar"><?php echo strtoupper(substr($cliente['nombre'], 0, 1)); ?></div>
                </div>
            </div>

            <!-- Resumen -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div class="card" style="padding: 20px; text-align: center;">
                    <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 5px;">Total Ingresado</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--green-accent);"><?php echo formatMoney($totalIngresado); ?></div>
                </div>
                <div class="card" style="padding: 20px; text-align: center;">
                    <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 5px;">Total Retirado</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--danger);"><?php echo formatMoney($totalRetirado); ?></div>
                </div>
                <div class="card" style="padding: 20px; text-align: center;">
                    <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 5px;">Rentabilidad</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--gold);"><?php echo formatMoney($totalRentabilidad); ?></div>
                </div>
                <div class="card" style="padding: 20px; text-align: center;">
                    <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 5px;">Saldo Actual</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--blue-accent);"><?php echo formatMoney($saldoActual); ?></div>
                </div>
            </div>

            <!-- Tabla de movimientos -->
            <div class="card">
                <div class="card-header">
                    <h2>Historial de Movimientos</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($movimientos)): ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                            No hay movimientos registrados
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Ingreso</th>
                                        <th>Retirada</th>
                                        <th>Rentabilidad</th>
                                        <th>Vehículo</th>
                                        <th>Notas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($movimientos as $m): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($m['fecha_ingreso'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $m['tipo_inversion'] === 'fija' ? 'badge-info' : 'badge-success'; ?>">
                                                <?php echo ucfirst($m['tipo_inversion']); ?>
                                            </span>
                                        </td>
                                        <td style="color: var(--green-accent); font-weight: 600;">
                                            <?php echo $m['importe_ingresado'] > 0 ? '+' . formatMoney($m['importe_ingresado']) : '-'; ?>
                                        </td>
                                        <td style="color: var(--danger); font-weight: 600;">
                                            <?php echo $m['importe_retirado'] > 0 ? '-' . formatMoney($m['importe_retirado']) : '-'; ?>
                                        </td>
                                        <td style="color: var(--gold); font-weight: 600;">
                                            <?php echo $m['rentabilidad'] > 0 ? '+' . formatMoney($m['rentabilidad']) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php if ($m['marca'] && $m['modelo']): ?>
                                                <?php echo escape($m['marca'] . ' ' . $m['modelo']); ?>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="max-width: 200px;">
                                            <?php echo $m['notas'] ? escape($m['notas']) : '<span style="color: var(--text-muted);">-</span>'; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
