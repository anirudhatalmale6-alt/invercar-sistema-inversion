<?php
/**
 * InverCar - Configuración del Sistema
 */
require_once __DIR__ . '/../includes/init.php';

if (!isAdminLogueado()) {
    redirect('login.php');
}

$db = getDB();
$error = '';
$exito = '';

// Guardar configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad.';
    } else {
        $campos = [
            'rentabilidad_fija' => floatval($_POST['rentabilidad_fija'] ?? 5),
            'rentabilidad_variable_actual' => floatval($_POST['rentabilidad_variable_actual'] ?? 14.8),
            'capital_reserva' => floatval($_POST['capital_reserva'] ?? 0),
            'nombre_empresa' => cleanInput($_POST['nombre_empresa'] ?? 'InverCar'),
            'email_empresa' => cleanInput($_POST['email_empresa'] ?? ''),
            'telefono_empresa' => cleanInput($_POST['telefono_empresa'] ?? ''),
        ];

        try {
            foreach ($campos as $clave => $valor) {
                $stmt = $db->prepare("UPDATE configuracion SET valor = ? WHERE clave = ?");
                $stmt->execute([$valor, $clave]);
            }
            $exito = 'Configuración guardada correctamente.';
        } catch (Exception $e) {
            $error = 'Error al guardar la configuración.';
        }
    }
}

// Obtener configuración actual
$config = [];
$stmt = $db->query("SELECT clave, valor FROM configuracion");
foreach ($stmt->fetchAll() as $row) {
    $config[$row['clave']] = $row['valor'];
}

$mensajesNoLeidos = $db->query("SELECT COUNT(*) as total FROM contactos WHERE leido = 0")->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Admin InverCar</title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <img src="../assets/images/logo-invercar-text.png" alt="InverCar" style="height: 40px; width: auto;">
            </div>
            <div class="sidebar-badge">ADMIN</div>

            <ul class="sidebar-menu">
                <li><a href="index.php"><span class="icon">◈</span> Panel</a></li>
                <li><a href="clientes.php"><span class="icon">◉</span> Clientes</a></li>
                <li><a href="vehiculos.php"><span class="icon">◆</span> Vehículos</a></li>

                <li class="sidebar-section">Configuración</li>
                <li><a href="contactos.php"><span class="icon">◇</span> Mensajes <?php if($mensajesNoLeidos > 0): ?><span class="badge badge-danger"><?php echo $mensajesNoLeidos; ?></span><?php endif; ?></a></li>
                <li><a href="configuracion.php" class="active"><span class="icon">◎</span> Ajustes</a></li>

                <li class="sidebar-section">Cuenta</li>
                <li><a href="logout.php"><span class="icon">◁</span> Cerrar sesión</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1>Configuración</h1>
                    <p>Ajustes generales del sistema</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo escape($error); ?></div>
            <?php endif; ?>
            <?php if ($exito): ?>
                <div class="alert alert-success"><?php echo escape($exito); ?></div>
            <?php endif; ?>

            <form method="POST">
                <?php echo csrfField(); ?>

                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2>Rentabilidad</h2>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Rentabilidad Fija Mensual (%)</label>
                                <input type="number" name="rentabilidad_fija" step="0.01"
                                       value="<?php echo escape($config['rentabilidad_fija'] ?? 5); ?>">
                                <small style="color: var(--text-muted);">Porcentaje garantizado para inversores con rentabilidad fija</small>
                            </div>
                            <div class="form-group">
                                <label>Rentabilidad Variable Actual (%)</label>
                                <input type="number" name="rentabilidad_variable_actual" step="0.01"
                                       value="<?php echo escape($config['rentabilidad_variable_actual'] ?? 14.8); ?>">
                                <small style="color: var(--text-muted);">Rentabilidad actual mostrada en la landing page</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2>Capital</h2>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Capital en Reserva (€)</label>
                            <input type="number" name="capital_reserva" step="0.01"
                                   value="<?php echo escape($config['capital_reserva'] ?? 0); ?>">
                            <small style="color: var(--text-muted);">Fondos de reserva del sistema</small>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2>Datos de la Empresa</h2>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Nombre de la Empresa</label>
                            <input type="text" name="nombre_empresa"
                                   value="<?php echo escape($config['nombre_empresa'] ?? 'InverCar'); ?>">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Email de Contacto</label>
                                <input type="email" name="email_empresa"
                                       value="<?php echo escape($config['email_empresa'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Teléfono de Contacto</label>
                                <input type="text" name="telefono_empresa"
                                       value="<?php echo escape($config['telefono_empresa'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Guardar Configuración</button>
            </form>
        </main>
    </div>
</body>
</html>
