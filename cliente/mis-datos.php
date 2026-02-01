<?php
/**
 * InverCar - Mis Datos del Cliente (editable)
 */
require_once __DIR__ . '/../includes/init.php';

if (!isClienteLogueado()) {
    redirect('login.php');
}

$db = getDB();
$error = '';
$exito = '';

// Obtener datos del cliente
$stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$_SESSION['cliente_id']]);
$cliente = $stmt->fetch();

if (!$cliente) {
    redirect('logout.php');
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad. Por favor, recarga la página.';
    } else {
        $action = $_POST['action'] ?? 'datos';

        if ($action === 'datos') {
            // Actualizar datos personales
            $nombre = cleanInput($_POST['nombre'] ?? '');
            $apellidos = cleanInput($_POST['apellidos'] ?? '');
            $telefono = cleanInput($_POST['telefono'] ?? '');
            $direccion = cleanInput($_POST['direccion'] ?? '');
            $codigo_postal = cleanInput($_POST['codigo_postal'] ?? '');
            $poblacion = cleanInput($_POST['poblacion'] ?? '');
            $provincia = cleanInput($_POST['provincia'] ?? '');
            $pais = cleanInput($_POST['pais'] ?? 'España');

            if (empty($nombre)) {
                $error = 'El nombre es obligatorio.';
            } else {
                try {
                    $stmt = $db->prepare("
                        UPDATE clientes SET
                            nombre = ?, apellidos = ?, telefono = ?, direccion = ?,
                            codigo_postal = ?, poblacion = ?, provincia = ?, pais = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$nombre, $apellidos, $telefono, $direccion, $codigo_postal, $poblacion, $provincia, $pais, $cliente['id']]);
                    $exito = 'Datos actualizados correctamente.';

                    // Recargar datos
                    $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
                    $stmt->execute([$cliente['id']]);
                    $cliente = $stmt->fetch();
                } catch (Exception $e) {
                    $error = 'Error al actualizar los datos.';
                }
            }
        } elseif ($action === 'password') {
            // Cambiar contraseña
            $password_actual = $_POST['password_actual'] ?? '';
            $password_nueva = $_POST['password_nueva'] ?? '';
            $password_confirmar = $_POST['password_confirmar'] ?? '';

            if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
                $error = 'Todos los campos de contraseña son obligatorios.';
            } elseif (!password_verify($password_actual, $cliente['password'])) {
                $error = 'La contraseña actual no es correcta.';
            } elseif (strlen($password_nueva) < 8) {
                $error = 'La nueva contraseña debe tener al menos 8 caracteres.';
            } elseif ($password_nueva !== $password_confirmar) {
                $error = 'Las contraseñas nuevas no coinciden.';
            } else {
                try {
                    $passwordHash = password_hash($password_nueva, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
                    $stmt = $db->prepare("UPDATE clientes SET password = ? WHERE id = ?");
                    $stmt->execute([$passwordHash, $cliente['id']]);
                    $exito = 'Contraseña actualizada correctamente.';
                } catch (Exception $e) {
                    $error = 'Error al cambiar la contraseña.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Datos - InverCar</title>
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
                    <h1>Mis Datos</h1>
                    <p>Actualiza tu información personal</p>
                </div>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo escape($cliente['nombre']); ?></div>
                    </div>
                    <div class="user-avatar"><?php echo strtoupper(substr($cliente['nombre'], 0, 1)); ?></div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo escape($error); ?></div>
            <?php endif; ?>
            <?php if ($exito): ?>
                <div class="alert alert-success"><?php echo escape($exito); ?></div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px;">
                <!-- Datos personales -->
                <div class="card">
                    <div class="card-header">
                        <h2>Datos Personales</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="datos">

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Nombre y Apellidos *</label>
                                    <input type="text" name="nombre" value="<?php echo escape($cliente['nombre']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Empresa</label>
                                    <input type="text" name="apellidos" value="<?php echo escape($cliente['apellidos']); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" value="<?php echo escape($cliente['email']); ?>" disabled style="opacity: 0.6;">
                                    <small style="color: var(--text-muted);">El email no se puede cambiar</small>
                                </div>
                                <div class="form-group">
                                    <label>DNI/NIE</label>
                                    <input type="text" value="<?php echo escape($cliente['dni']); ?>" disabled style="opacity: 0.6;">
                                    <small style="color: var(--text-muted);">El DNI no se puede cambiar</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Teléfono</label>
                                <input type="tel" name="telefono" value="<?php echo escape($cliente['telefono']); ?>">
                            </div>

                            <div class="form-group">
                                <label>Dirección</label>
                                <input type="text" name="direccion" value="<?php echo escape($cliente['direccion']); ?>">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Código Postal</label>
                                    <input type="text" name="codigo_postal" value="<?php echo escape($cliente['codigo_postal']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Población</label>
                                    <input type="text" name="poblacion" value="<?php echo escape($cliente['poblacion']); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Provincia</label>
                                    <input type="text" name="provincia" value="<?php echo escape($cliente['provincia']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>País</label>
                                    <input type="text" name="pais" value="<?php echo escape($cliente['pais'] ?: 'España'); ?>">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        </form>
                    </div>
                </div>

                <!-- Cambiar contraseña -->
                <div class="card">
                    <div class="card-header">
                        <h2>Cambiar Contraseña</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="password">

                            <div class="form-group">
                                <label>Contraseña Actual *</label>
                                <input type="password" name="password_actual" required>
                            </div>

                            <div class="form-group">
                                <label>Nueva Contraseña *</label>
                                <input type="password" name="password_nueva" required minlength="8">
                                <small style="color: var(--text-muted);">Mínimo 8 caracteres</small>
                            </div>

                            <div class="form-group">
                                <label>Confirmar Contraseña *</label>
                                <input type="password" name="password_confirmar" required minlength="8">
                            </div>

                            <button type="submit" class="btn btn-outline" style="width: 100%;">Cambiar Contraseña</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Info adicional -->
            <div class="card" style="margin-top: 25px;">
                <div class="card-body" style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong>Cliente desde:</strong> <?php echo date('d/m/Y', strtotime($cliente['created_at'])); ?>
                    </div>
                    <div>
                        <strong>Estado:</strong>
                        <span class="badge badge-success">Activo</span>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
        @media (max-width: 900px) {
            .main-content > div:nth-child(3) {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</body>
</html>
