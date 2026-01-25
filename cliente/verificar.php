<?php
/**
 * InverCar - Verificación de Email
 */
require_once __DIR__ . '/../includes/init.php';

$error = '';
$exito = false;
$cliente = null;

$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = 'Token de verificación no proporcionado.';
} else {
    try {
        $db = getDB();

        // Buscar cliente con este token
        $stmt = $db->prepare("
            SELECT id, nombre, apellidos, email, email_verificado, token_expira
            FROM clientes
            WHERE token_verificacion = ?
        ");
        $stmt->execute([$token]);
        $cliente = $stmt->fetch();

        if (!$cliente) {
            $error = 'Token de verificación inválido o ya utilizado.';
        } elseif ($cliente['email_verificado']) {
            $error = 'Este email ya ha sido verificado. <a href="login.php">Inicia sesión</a>';
        } elseif (strtotime($cliente['token_expira']) < time()) {
            $error = 'El token de verificación ha expirado. Por favor, <a href="registro.php">regístrate de nuevo</a>.';
        } else {
            // Verificar email
            $stmt = $db->prepare("
                UPDATE clientes
                SET email_verificado = 1, token_verificacion = NULL, token_expira = NULL
                WHERE id = ?
            ");
            $stmt->execute([$cliente['id']]);

            // Guardar en sesión para completar registro
            $_SESSION['verificacion_cliente_id'] = $cliente['id'];

            $exito = true;
        }
    } catch (Exception $e) {
        if (DEBUG_MODE) {
            $error = 'Error: ' . $e->getMessage();
        } else {
            $error = 'Error al verificar el email. Inténtalo de nuevo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Email - InverCar</title>
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
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            border: 1px solid var(--border-color);
            text-align: center;
        }
        .auth-header {
            margin-bottom: 30px;
        }
        .auth-header .logo {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <a href="../index.php" class="logo"><img src="../assets/images/logo-invercar.png" alt="InverCar" style="height: 60px;"></a>
            </div>

            <?php if ($exito): ?>
                <div class="success-icon">✓</div>
                <h2>¡Email Verificado!</h2>
                <p style="color: var(--text-muted); margin: 20px 0;">
                    Hola <strong><?php echo escape($cliente['nombre']); ?></strong>, tu email ha sido verificado correctamente.
                </p>
                <p style="color: var(--text-muted); margin-bottom: 30px;">
                    Ahora necesitamos que completes tu perfil para poder invertir.
                </p>
                <a href="completar-registro.php" class="btn btn-primary" style="width: 100%;">Completar mi perfil</a>
            <?php else: ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
                <a href="../index.php" class="btn btn-outline" style="margin-top: 20px;">Volver al inicio</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
