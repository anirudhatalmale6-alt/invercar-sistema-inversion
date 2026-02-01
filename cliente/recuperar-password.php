<?php
/**
 * InverCar - Recuperar Contraseña
 */
require_once __DIR__ . '/../includes/init.php';
require_once INCLUDES_PATH . '/mail.php';

if (isClienteLogueado()) {
    redirect('panel.php');
}

$error = '';
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad.';
    } else {
        $email = cleanInput($_POST['email'] ?? '');

        if (empty($email) || !validarEmail($email)) {
            $error = 'Por favor, introduce un email válido.';
        } else {
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT id, nombre, apellidos FROM clientes WHERE email = ? AND activo = 1");
                $stmt->execute([$email]);
                $cliente = $stmt->fetch();

                if ($cliente) {
                    // Generar token
                    $token = generateToken();
                    $expira = date('Y-m-d H:i:s', strtotime('+' . TOKEN_EXPIRY_HOURS . ' hours'));

                    // Guardar token
                    $stmt = $db->prepare("UPDATE clientes SET token_verificacion = ?, token_expira = ? WHERE id = ?");
                    $stmt->execute([$token, $expira, $cliente['id']]);

                    // Enviar email
                    $nombre = trim($cliente['nombre'] . ' ' . ($cliente['apellidos'] ?? ''));
                    $emailEnviado = enviarEmailRecuperacion($email, $nombre, $token);
                    if (DEBUG_MODE && !$emailEnviado) {
                        error_log("Error enviando email de recuperación a: $email");
                    }
                }

                // Siempre mostrar éxito (no revelar si el email existe)
                $exito = true;

            } catch (Exception $e) {
                $error = 'Error al procesar la solicitud.';
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
    <title>Recuperar Contraseña - InverCar</title>
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
                <p>Recuperar contraseña</p>
            </div>

            <?php if ($exito): ?>
                <div class="alert alert-success">
                    Si el email existe en nuestro sistema, recibirás un enlace para restablecer tu contraseña.
                </div>
                <a href="login.php" class="btn btn-primary" style="width: 100%; text-align: center;">Volver al login</a>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo escape($error); ?></div>
                <?php endif; ?>

                <form method="POST" id="recuperarForm">
                    <?php echo csrfField(); ?>

                    <div class="form-group">
                        <label for="email">Email de tu cuenta</label>
                        <input type="email" id="email" name="email" required
                               placeholder="tu@email.com">
                    </div>

                    <button type="submit" class="btn btn-primary" id="submitBtn" style="width: 100%;">Enviar enlace</button>
                    <div id="loadingMsg" style="display: none; text-align: center; margin-top: 15px; color: var(--primary-color);">
                        <span style="display: inline-block; animation: pulse 1.5s infinite;">Enviando email de recuperación...</span>
                    </div>
                </form>
                <style>
                    @keyframes pulse {
                        0%, 100% { opacity: 1; }
                        50% { opacity: 0.5; }
                    }
                </style>
                <script>
                document.getElementById('recuperarForm').addEventListener('submit', function() {
                    document.getElementById('submitBtn').disabled = true;
                    document.getElementById('submitBtn').textContent = 'Enviando...';
                    document.getElementById('loadingMsg').style.display = 'block';
                });
                </script>

                <p style="text-align: center; margin-top: 20px;">
                    <a href="login.php" style="color: var(--text-muted);">← Volver al login</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
