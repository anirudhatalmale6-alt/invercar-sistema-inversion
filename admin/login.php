<?php
/**
 * InverCar - Login de Administrador
 */
require_once __DIR__ . '/../includes/init.php';

// Si ya está logueado, redirigir
if (isAdminLogueado()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad. Por favor, recarga la página.';
    } else {
        $usuario = cleanInput($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($usuario) || empty($password)) {
            $error = 'Por favor, introduce usuario y contraseña.';
        } else {
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT * FROM administradores WHERE usuario = ? AND activo = 1");
                $stmt->execute([$usuario]);
                $admin = $stmt->fetch();

                if (!$admin || !password_verify($password, $admin['password'])) {
                    $error = 'Usuario o contraseña incorrectos.';
                } else {
                    // Login exitoso
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_nombre'] = $admin['nombre'];
                    $_SESSION['admin_usuario'] = $admin['usuario'];

                    redirect('index.php');
                }
            } catch (Exception $e) {
                if (DEBUG_MODE) {
                    $error = 'Error: ' . $e->getMessage();
                } else {
                    $error = 'Error al iniciar sesión. Inténtalo de nuevo.';
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
    <title>Acceso Administración - InverCar</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, var(--dark-bg) 0%, var(--secondary-color) 100%);
        }
        .auth-box {
            background: var(--card-bg);
            border-radius: 0;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            border: 1px solid var(--border-color);
        }
        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .auth-header .logo {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .auth-header p {
            color: var(--text-muted);
        }
        .admin-badge {
            display: inline-block;
            background: var(--warning);
            color: #000;
            padding: 3px 10px;
            border-radius: 0;
            font-size: 0.75rem;
            font-weight: bold;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <a href="../index.php" class="logo"><img src="../assets/images/logo-invercar.png" alt="InverCar" style="height: 80px; max-width: 220px;"></a>
                <div class="admin-badge">ADMINISTRACIÓN</div>
                <p>Panel de gestión interna</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo escape($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <?php echo csrfField(); ?>

                <div class="form-group">
                    <label for="usuario">Usuario</label>
                    <input type="text" id="usuario" name="usuario" required
                           value="<?php echo escape($_POST['usuario'] ?? ''); ?>"
                           placeholder="Tu usuario" autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required
                           placeholder="Tu contraseña" autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Acceder</button>
            </form>

            <p style="text-align: center; margin-top: 20px;">
                <a href="../index.php" style="color: var(--text-muted);">← Volver al inicio</a>
            </p>
        </div>
    </div>
</body>
</html>
