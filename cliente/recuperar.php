<?php
/**
 * InverCar - Restablecer Contraseña (con token)
 */
require_once __DIR__ . '/../includes/init.php';

if (isClienteLogueado()) {
    redirect('panel.php');
}

$error = '';
$exito = false;
$tokenValido = false;
$token = cleanInput($_GET['token'] ?? '');

// Verificar token
if (!empty($token)) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, nombre, email FROM clientes WHERE token_verificacion = ? AND token_expira > NOW() AND activo = 1");
        $stmt->execute([$token]);
        $cliente = $stmt->fetch();

        if ($cliente) {
            $tokenValido = true;
        } else {
            $error = 'El enlace ha expirado o no es válido. Por favor, solicita uno nuevo.';
        }
    } catch (Exception $e) {
        $error = 'Error al verificar el enlace.';
    }
} else {
    $error = 'Enlace no válido.';
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValido) {
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad.';
    } else {
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        if (empty($password)) {
            $error = 'Por favor, introduce una nueva contraseña.';
        } elseif (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif ($password !== $password_confirm) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            try {
                $db = getDB();
                $passwordHash = password_hash($password, PASSWORD_DEFAULT, ['cost' => HASH_COST]);

                $stmt = $db->prepare("UPDATE clientes SET password = ?, token_verificacion = NULL, token_expira = NULL WHERE id = ?");
                $stmt->execute([$passwordHash, $cliente['id']]);

                $exito = true;
            } catch (Exception $e) {
                $error = 'Error al actualizar la contraseña.';
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
    <title>Restablecer Contraseña - InverCar</title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Raleway', sans-serif; }
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
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <a href="../index.php" class="logo"><img src="../assets/images/logo-invercar.png" alt="InverCar" style="height: 80px; max-width: 220px;"></a>
                <p>Restablecer contraseña</p>
            </div>

            <?php if ($exito): ?>
                <div class="alert alert-success">
                    Tu contraseña ha sido actualizada correctamente.
                </div>
                <a href="login.php" class="btn btn-primary" style="width: 100%; text-align: center;">Iniciar sesión</a>
            <?php elseif ($tokenValido): ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo escape($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <?php echo csrfField(); ?>

                    <div class="form-group">
                        <label for="password">Nueva contraseña</label>
                        <input type="password" id="password" name="password" required
                               placeholder="Mínimo 6 caracteres" minlength="6">
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">Confirmar contraseña</label>
                        <input type="password" id="password_confirm" name="password_confirm" required
                               placeholder="Repite la contraseña">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">Actualizar contraseña</button>
                </form>
            <?php else: ?>
                <div class="alert alert-error"><?php echo escape($error); ?></div>
                <a href="recuperar-password.php" class="btn btn-primary" style="width: 100%; text-align: center;">Solicitar nuevo enlace</a>
            <?php endif; ?>

            <p style="text-align: center; margin-top: 20px;">
                <a href="login.php" style="color: var(--text-muted);">← Volver al login</a>
            </p>
        </div>
    </div>
</body>
</html>
