<?php
/**
 * InverCar - Login de Cliente
 */
require_once __DIR__ . '/../includes/init.php';

// Si ya está logueado, redirigir
if (isClienteLogueado()) {
    redirect('panel.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad. Por favor, recarga la página.';
    } else {
        $email = cleanInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Por favor, introduce tu email y contraseña.';
        } else {
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT * FROM clientes WHERE email = ? AND activo = 1");
                $stmt->execute([$email]);
                $cliente = $stmt->fetch();

                if (!$cliente || !password_verify($password, $cliente['password'])) {
                    $error = 'Email o contraseña incorrectos.';
                } elseif (!$cliente['email_verificado']) {
                    $error = 'Tu email no ha sido verificado. Revisa tu bandeja de entrada.';
                } else {
                    // Login exitoso
                    $_SESSION['cliente_id'] = $cliente['id'];
                    $_SESSION['cliente_nombre'] = $cliente['nombre'] . ' ' . $cliente['apellidos'];

                    // Si no ha completado el registro, redirigir
                    if (!$cliente['registro_completo']) {
                        $_SESSION['verificacion_cliente_id'] = $cliente['id'];
                        redirect('completar-registro.php');
                    }

                    redirect('panel.php');
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
    <title>Acceso Clientes - InverCar</title>
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
        .auth-footer {
            text-align: center;
            margin-top: 20px;
            color: var(--text-muted);
        }
        .auth-footer a {
            color: var(--primary-color);
        }
        .forgot-link {
            display: block;
            text-align: right;
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: -10px;
            margin-bottom: 20px;
        }
        .forgot-link:hover {
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <a href="../index.php" class="logo"><img src="../assets/images/logo-invercar.png" alt="InverCar" style="height: 80px; max-width: 220px;"></a>
                <p>Acceso para inversores</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo escape($error); ?></div>
            <?php endif; ?>

            <?php
            $flash = getFlash();
            if ($flash):
            ?>
                <div class="alert alert-<?php echo $flash['tipo'] === 'success' ? 'success' : 'error'; ?>">
                    <?php echo escape($flash['mensaje']); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?php echo csrfField(); ?>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo escape($_POST['email'] ?? ''); ?>"
                           placeholder="tu@email.com">
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required
                           placeholder="Tu contraseña">
                </div>

                <a href="recuperar-password.php" class="forgot-link">¿Olvidaste tu contraseña?</a>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Iniciar Sesión</button>
            </form>

            <div class="auth-footer">
                <p>¿No tienes cuenta? <a href="registro.php">Regístrate</a></p>
                <p style="margin-top: 15px;"><a href="../index.php">← Volver al inicio</a></p>
            </div>
        </div>
    </div>
</body>
</html>
