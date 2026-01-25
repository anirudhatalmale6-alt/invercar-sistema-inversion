<?php
/**
 * InverCar - Registro de Cliente (Paso 1)
 * Nombre, Apellidos, Email, Contraseña
 */
require_once __DIR__ . '/../includes/init.php';
require_once INCLUDES_PATH . '/mail.php';

// Si ya está logueado, redirigir
if (isClienteLogueado()) {
    redirect('panel.php');
}

$error = '';
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad. Por favor, recarga la página.';
    } else {
        $nombre = cleanInput($_POST['nombre'] ?? '');
        $apellidos = cleanInput($_POST['apellidos'] ?? '');
        $email = cleanInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        // Validaciones
        if (empty($nombre) || empty($apellidos) || empty($email) || empty($password)) {
            $error = 'Por favor, completa todos los campos.';
        } elseif (!validarEmail($email)) {
            $error = 'El email no es válido.';
        } elseif (strlen($password) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($password !== $password2) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            try {
                $db = getDB();

                // Verificar si el email ya existe
                $stmt = $db->prepare("SELECT id FROM clientes WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Este email ya está registrado. <a href="login.php">Inicia sesión</a>';
                } else {
                    // Crear token de verificación
                    $token = generateToken();
                    $tokenExpira = date('Y-m-d H:i:s', strtotime('+' . TOKEN_EXPIRY_HOURS . ' hours'));

                    // Hash de la contraseña
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT, ['cost' => HASH_COST]);

                    // Insertar cliente
                    $stmt = $db->prepare("
                        INSERT INTO clientes (nombre, apellidos, email, password, token_verificacion, token_expira)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$nombre, $apellidos, $email, $passwordHash, $token, $tokenExpira]);

                    // Enviar email de verificación
                    $nombreCompleto = $nombre . ' ' . $apellidos;
                    if (enviarEmailVerificacion($email, $nombreCompleto, $token)) {
                        $exito = true;
                    } else {
                        // Si falla el email, mostrar el enlace manualmente (para desarrollo)
                        if (DEBUG_MODE) {
                            $enlace = SITE_URL . "/cliente/verificar.php?token=" . urlencode($token);
                            $error = "No se pudo enviar el email. Enlace de verificación: <a href='$enlace'>$enlace</a>";
                        } else {
                            $exito = true; // En producción, mostrar mensaje de éxito de todos modos
                        }
                    }
                }
            } catch (Exception $e) {
                if (DEBUG_MODE) {
                    $error = 'Error: ' . $e->getMessage();
                } else {
                    $error = 'Error al procesar el registro. Inténtalo de nuevo.';
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
    <title>Registro - InverCar</title>
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
            max-width: 450px;
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
        .password-strength {
            height: 4px;
            border-radius: 0;
            margin-top: 8px;
            transition: all 0.3s;
        }
        .strength-weak { background: var(--danger); width: 33%; }
        .strength-medium { background: var(--warning); width: 66%; }
        .strength-strong { background: var(--success); width: 100%; }
        .auth-footer {
            text-align: center;
            margin-top: 20px;
            color: var(--text-muted);
        }
        .auth-footer a {
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <a href="../index.php" class="logo"><img src="../assets/images/logo-invercar.png" alt="InverCar" style="height: 80px; max-width: 220px;"></a>
                <p>Crea tu cuenta de inversor</p>
            </div>

            <?php if ($exito): ?>
                <div class="alert alert-success">
                    <strong>¡Registro exitoso!</strong><br><br>
                    Te hemos enviado un email de verificación a <strong><?php echo escape($email); ?></strong>.<br><br>
                    Por favor, revisa tu bandeja de entrada (y spam) y haz clic en el enlace para activar tu cuenta.
                </div>
                <a href="login.php" class="btn btn-primary" style="width: 100%; text-align: center;">Ir a Iniciar Sesión</a>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <?php echo csrfField(); ?>

                    <div class="form-group">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" required
                               value="<?php echo escape($_POST['nombre'] ?? ''); ?>"
                               placeholder="Tu nombre">
                    </div>

                    <div class="form-group">
                        <label for="apellidos">Apellidos *</label>
                        <input type="text" id="apellidos" name="apellidos" required
                               value="<?php echo escape($_POST['apellidos'] ?? ''); ?>"
                               placeholder="Tus apellidos">
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required
                               value="<?php echo escape($_POST['email'] ?? ''); ?>"
                               placeholder="tu@email.com">
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña *</label>
                        <input type="password" id="password" name="password" required
                               minlength="8" placeholder="Mínimo 8 caracteres">
                        <div class="password-strength" id="passwordStrength"></div>
                    </div>

                    <div class="form-group">
                        <label for="password2">Confirmar contraseña *</label>
                        <input type="password" id="password2" name="password2" required
                               minlength="8" placeholder="Repite la contraseña">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">Crear cuenta</button>
                </form>

                <div class="auth-footer">
                    <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('passwordStrength');

        if (passwordInput && strengthBar) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                strengthBar.className = 'password-strength';

                if (password.length === 0) {
                    strengthBar.style.width = '0';
                    return;
                }

                let strength = 0;
                if (password.length >= 8) strength++;
                if (/[A-Z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^A-Za-z0-9]/.test(password)) strength++;

                if (strength <= 1) {
                    strengthBar.classList.add('strength-weak');
                } else if (strength <= 2) {
                    strengthBar.classList.add('strength-medium');
                } else {
                    strengthBar.classList.add('strength-strong');
                }
            });
        }
    </script>
</body>
</html>
