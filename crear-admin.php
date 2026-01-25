<?php
/**
 * InverCar - Crear/Resetear Administrador
 * ELIMINAR ESTE ARCHIVO DESPUÉS DE USAR
 */
require_once __DIR__ . '/includes/init.php';

$mensaje = '';
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = cleanInput($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $nombre = cleanInput($_POST['nombre'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');

    if (empty($usuario) || empty($password) || empty($nombre) || empty($email)) {
        $mensaje = 'Todos los campos son obligatorios.';
    } elseif (strlen($password) < 6) {
        $mensaje = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        try {
            $db = getDB();

            // Verificar si el usuario ya existe
            $stmt = $db->prepare("SELECT id FROM administradores WHERE usuario = ?");
            $stmt->execute([$usuario]);
            $existe = $stmt->fetch();

            $passwordHash = password_hash($password, PASSWORD_DEFAULT, ['cost' => HASH_COST]);

            if ($existe) {
                // Actualizar usuario existente
                $stmt = $db->prepare("UPDATE administradores SET password = ?, nombre = ?, email = ?, activo = 1 WHERE usuario = ?");
                $stmt->execute([$passwordHash, $nombre, $email, $usuario]);
                $mensaje = "Usuario '$usuario' actualizado correctamente.";
            } else {
                // Crear nuevo usuario
                $stmt = $db->prepare("INSERT INTO administradores (usuario, password, nombre, email) VALUES (?, ?, ?, ?)");
                $stmt->execute([$usuario, $passwordHash, $nombre, $email]);
                $mensaje = "Usuario '$usuario' creado correctamente.";
            }
            $exito = true;
        } catch (Exception $e) {
            $mensaje = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Administrador - InverCar</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { font-family: 'Raleway', Arial, sans-serif; }
        .setup-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #000 0%, #1a1a1a 100%);
        }
        .setup-box {
            background: #111;
            border-radius: 0;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            border: 1px solid #333;
        }
        .setup-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .setup-header h1 {
            color: #d4a84b;
            margin-bottom: 10px;
        }
        .setup-header p {
            color: #999;
        }
        .warning {
            background: #ff6b6b;
            color: #fff;
            padding: 15px;
            margin-bottom: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-box">
            <div class="setup-header">
                <h1>Crear Administrador</h1>
                <p>Crea o resetea un usuario administrador</p>
            </div>

            <div class="warning">
                IMPORTANTE: Elimina este archivo después de usar por seguridad.
            </div>

            <?php if ($mensaje): ?>
                <div class="alert <?php echo $exito ? 'alert-success' : 'alert-error'; ?>">
                    <?php echo escape($mensaje); ?>
                </div>
                <?php if ($exito): ?>
                    <a href="admin/login.php" class="btn btn-primary" style="width: 100%; text-align: center; margin-top: 15px;">Ir al Login de Admin</a>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!$exito): ?>
            <form method="POST">
                <div class="form-group">
                    <label for="usuario">Usuario</label>
                    <input type="text" id="usuario" name="usuario" required value="admin" placeholder="admin">
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required minlength="6" placeholder="Mínimo 6 caracteres">
                </div>

                <div class="form-group">
                    <label for="nombre">Nombre completo</label>
                    <input type="text" id="nombre" name="nombre" required value="Administrador" placeholder="Administrador">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required value="admin@invercar.com" placeholder="admin@invercar.com">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Crear/Actualizar Admin</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
