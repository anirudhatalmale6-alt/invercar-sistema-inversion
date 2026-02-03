<?php
/**
 * InverCar - Completar Registro (Paso 2)
 * DNI, Dirección, Teléfono, Capital, Tipo de inversión
 */
require_once __DIR__ . '/../includes/init.php';

// Verificar que viene de la verificación de email o está logueado sin completar
$clienteId = $_SESSION['verificacion_cliente_id'] ?? null;

if (!$clienteId && isClienteLogueado()) {
    // Verificar si necesita completar el registro
    $db = getDB();
    $stmt = $db->prepare("SELECT registro_completo FROM clientes WHERE id = ?");
    $stmt->execute([$_SESSION['cliente_id']]);
    $cliente = $stmt->fetch();

    if ($cliente && !$cliente['registro_completo']) {
        $clienteId = $_SESSION['cliente_id'];
    } else {
        redirect('panel.php');
    }
}

if (!$clienteId) {
    redirect('login.php');
}

// Obtener datos del cliente
$db = getDB();
$stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$clienteId]);
$cliente = $stmt->fetch();

if (!$cliente) {
    redirect('login.php');
}

$error = '';
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad. Por favor, recarga la página.';
    } else {
        $dni = strtoupper(cleanInput($_POST['dni'] ?? ''));
        $direccion = cleanInput($_POST['direccion'] ?? '');
        $codigo_postal = cleanInput($_POST['codigo_postal'] ?? '');
        $poblacion = cleanInput($_POST['poblacion'] ?? '');
        $provincia = cleanInput($_POST['provincia'] ?? '');
        $pais = cleanInput($_POST['pais'] ?? 'España');
        $telefono = cleanInput($_POST['telefono'] ?? '');
        $capital = floatval($_POST['capital'] ?? 0);
        $tipo_inversion = cleanInput($_POST['tipo_inversion'] ?? '');
        $tipo_liquidacion = cleanInput($_POST['tipo_liquidacion'] ?? 'trimestral');

        // Validaciones
        if (empty($dni) || empty($direccion) || empty($codigo_postal) ||
            empty($poblacion) || empty($provincia) || empty($telefono) ||
            $capital <= 0 || empty($tipo_inversion) || empty($tipo_liquidacion)) {
            $error = 'Por favor, completa todos los campos.';
        } elseif (!validarDNI($dni)) {
            $error = 'El DNI/NIE no es válido.';
        } elseif ($tipo_inversion !== 'fija') {
            $error = 'Tipo de inversión no válido.';
        } elseif (!in_array($tipo_liquidacion, ['trimestral', 'semestral', 'anual'])) {
            $error = 'Tipo de liquidación no válido.';
        } elseif ($capital < 1000) {
            $error = 'El capital mínimo de inversión es de 1.000€.';
        } else {
            try {
                // Verificar que el DNI no esté ya registrado
                $stmt = $db->prepare("SELECT id FROM clientes WHERE dni = ? AND id != ?");
                $stmt->execute([$dni, $clienteId]);
                if ($stmt->fetch()) {
                    $error = 'Este DNI ya está registrado con otra cuenta.';
                } else {
                    // Actualizar cliente - activo = 0 hasta activación manual del admin
                    // capital_previsto es solo informativo, no crea registro en capital
                    $stmt = $db->prepare("
                        UPDATE clientes SET
                            dni = ?,
                            direccion = ?,
                            codigo_postal = ?,
                            poblacion = ?,
                            provincia = ?,
                            pais = ?,
                            telefono = ?,
                            capital_previsto = ?,
                            tipo_liquidacion = ?,
                            registro_completo = 1,
                            activo = 0
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $dni, $direccion, $codigo_postal, $poblacion, $provincia,
                        $pais, $telefono, $capital, $tipo_liquidacion, $clienteId
                    ]);

                    // Limpiar sesión de verificación
                    unset($_SESSION['verificacion_cliente_id']);

                    // Loguear al cliente
                    $_SESSION['cliente_id'] = $clienteId;
                    $_SESSION['cliente_nombre'] = $cliente['nombre'] . ' ' . $cliente['apellidos'];

                    // Enviar notificación al panel de mensajes (contactos)
                    $nombreCompleto = $cliente['nombre'] . ' ' . $cliente['apellidos'];
                    $mensajeNotificacion = "Nuevo cliente registrado:\n\nNombre: {$nombreCompleto}\nEmail: {$cliente['email']}\nCapital previsto: " . number_format($capital, 0, ',', '.') . " €\n\nEl cliente ha completado el proceso de registro y está pendiente de activación.";

                    $stmtContacto = $db->prepare("
                        INSERT INTO contactos (nombre, email, telefono, mensaje, leido, created_at)
                        VALUES (?, ?, ?, ?, 0, NOW())
                    ");
                    $stmtContacto->execute([
                        $nombreCompleto,
                        $cliente['email'],
                        $telefono,
                        $mensajeNotificacion
                    ]);

                    // Enviar email al administrador
                    require_once __DIR__ . '/../includes/mail.php';
                    enviarEmailAdminNuevoCliente($nombreCompleto, $cliente['email'], $capital);

                    $exito = true;
                }
            } catch (Exception $e) {
                if (DEBUG_MODE) {
                    $error = 'Error: ' . $e->getMessage();
                } else {
                    $error = 'Error al guardar los datos. Inténtalo de nuevo.';
                }
            }
        }
    }
}

// Lista de provincias españolas
$provincias = [
    'Álava', 'Albacete', 'Alicante', 'Almería', 'Asturias', 'Ávila', 'Badajoz',
    'Barcelona', 'Burgos', 'Cáceres', 'Cádiz', 'Cantabria', 'Castellón', 'Ciudad Real',
    'Córdoba', 'Cuenca', 'Gerona', 'Granada', 'Guadalajara', 'Guipúzcoa', 'Huelva',
    'Huesca', 'Islas Baleares', 'Jaén', 'La Coruña', 'La Rioja', 'Las Palmas', 'León',
    'Lérida', 'Lugo', 'Madrid', 'Málaga', 'Murcia', 'Navarra', 'Orense', 'Palencia',
    'Pontevedra', 'Salamanca', 'Santa Cruz de Tenerife', 'Segovia', 'Sevilla', 'Soria',
    'Tarragona', 'Teruel', 'Toledo', 'Valencia', 'Valladolid', 'Vizcaya', 'Zamora', 'Zaragoza'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completar Registro - InverCar</title>
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
            max-width: 600px;
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
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 500px) {
            .form-row { grid-template-columns: 1fr; }
        }
        .investment-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 10px;
        }
        .investment-option {
            background: var(--dark-bg);
            border: 2px solid var(--border-color);
            border-radius: 0;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .investment-option:hover {
            border-color: var(--primary-color);
        }
        .investment-option.selected {
            border-color: var(--primary-color);
            background: rgba(13, 155, 92, 0.1);
        }
        .investment-option input {
            display: none;
        }
        .investment-option h4 {
            margin-bottom: 5px;
        }
        .investment-option .rate {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: bold;
        }
        .investment-option p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 5px;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--success);
            border-radius: 0;
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
                <a href="../index.php" class="logo"><img src="../assets/images/logo-invercar.png" alt="InverCar" style="height: 80px; max-width: 220px;"></a>
                <p>Completa tu perfil de inversor</p>
            </div>

            <?php if ($exito): ?>
                <div style="text-align: center;">
                    <div class="success-icon">✓</div>
                    <h2>¡Registro Completado!</h2>
                    <p style="color: var(--text-muted); margin: 20px 0;">
                        Tu solicitud ha sido recibida. Un administrador revisará y activará tu cuenta en breve.
                        Te notificaremos cuando puedas acceder a tu panel de inversor.
                    </p>
                    <a href="../index.php" class="btn btn-primary" style="width: 100%;">Volver al Inicio</a>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo escape($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <?php echo csrfField(); ?>

                    <p style="color: var(--text-muted); margin-bottom: 20px;">
                        Hola <strong><?php echo escape($cliente['nombre']); ?></strong>, completa los siguientes datos para finalizar tu registro.
                    </p>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="dni">DNI/NIE *</label>
                            <input type="text" id="dni" name="dni" required
                                   value="<?php echo escape($_POST['dni'] ?? ''); ?>"
                                   placeholder="12345678A" maxlength="9">
                        </div>

                        <div class="form-group">
                            <label for="telefono">Teléfono *</label>
                            <input type="tel" id="telefono" name="telefono" required
                                   value="<?php echo escape($_POST['telefono'] ?? ''); ?>"
                                   placeholder="+34 600 000 000">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="direccion">Dirección *</label>
                        <input type="text" id="direccion" name="direccion" required
                               value="<?php echo escape($_POST['direccion'] ?? ''); ?>"
                               placeholder="Calle, número, piso...">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="codigo_postal">Código Postal *</label>
                            <input type="text" id="codigo_postal" name="codigo_postal" required
                                   value="<?php echo escape($_POST['codigo_postal'] ?? ''); ?>"
                                   placeholder="28001" maxlength="5">
                        </div>

                        <div class="form-group">
                            <label for="poblacion">Población *</label>
                            <input type="text" id="poblacion" name="poblacion" required
                                   value="<?php echo escape($_POST['poblacion'] ?? ''); ?>"
                                   placeholder="Madrid">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="provincia">Provincia *</label>
                            <select id="provincia" name="provincia" required>
                                <option value="">Selecciona...</option>
                                <?php foreach ($provincias as $prov): ?>
                                    <option value="<?php echo escape($prov); ?>"
                                        <?php echo (($_POST['provincia'] ?? '') === $prov) ? 'selected' : ''; ?>>
                                        <?php echo escape($prov); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="pais">País *</label>
                            <input type="text" id="pais" name="pais" required
                                   value="<?php echo escape($_POST['pais'] ?? 'España'); ?>">
                        </div>
                    </div>

                    <hr style="border-color: var(--border-color); margin: 30px 0;">

                    <h3 style="margin-bottom: 20px;">Datos de Inversión</h3>

                    <div class="form-group">
                        <label for="capital">Capital a invertir (€) *</label>
                        <input type="number" id="capital" name="capital" required
                               value="<?php echo escape($_POST['capital'] ?? ''); ?>"
                               placeholder="10000" min="1000" step="100">
                        <small style="color: var(--text-muted);">Mínimo 1.000€</small>
                    </div>

                    <div class="form-group">
                        <label>Tipo de inversión *</label>
                        <div class="investment-options" style="grid-template-columns: 1fr;">
                            <label class="investment-option selected">
                                <input type="radio" name="tipo_inversion" value="fija" checked>
                                <h4>Rentabilidad Fija</h4>
                                <div class="rate"><?php echo formatPercent(floatval(getConfig('rentabilidad_fija', 5))); ?></div>
                                <p>Anual garantizado</p>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="tipo_liquidacion">Liquidación de intereses *</label>
                        <select id="tipo_liquidacion" name="tipo_liquidacion" required style="width: 100%;">
                            <option value="trimestral" <?php echo (($_POST['tipo_liquidacion'] ?? 'trimestral') === 'trimestral') ? 'selected' : ''; ?>>Trimestral (cada 3 meses)</option>
                            <option value="semestral" <?php echo (($_POST['tipo_liquidacion'] ?? '') === 'semestral') ? 'selected' : ''; ?>>Semestral (cada 6 meses)</option>
                            <option value="anual" <?php echo (($_POST['tipo_liquidacion'] ?? '') === 'anual') ? 'selected' : ''; ?>>Anual (cada 12 meses)</option>
                        </select>
                        <small style="color: var(--text-muted);">Frecuencia de pago de intereses</small>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px;" id="submitBtn">
                        <span class="btn-text">Completar Registro</span>
                        <span class="btn-loading" style="display: none;">
                            <svg class="spinner" width="20" height="20" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="animation: spin 1s linear infinite;">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round" stroke-dasharray="60 30"/>
                            </svg>
                            Procesando...
                        </span>
                    </button>

                    <style>
                        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
                        .spinner { display: inline-block; vertical-align: middle; margin-right: 8px; }
                        .btn-loading { display: flex; align-items: center; justify-content: center; }
                    </style>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Highlight selected investment option
        document.querySelectorAll('.investment-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.investment-option').forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
            });
        });

        // Loading indicator on form submit
        const form = document.querySelector('form');
        const submitBtn = document.getElementById('submitBtn');
        if (form && submitBtn) {
            form.addEventListener('submit', function() {
                submitBtn.disabled = true;
                submitBtn.querySelector('.btn-text').style.display = 'none';
                submitBtn.querySelector('.btn-loading').style.display = 'flex';
            });
        }
    </script>
</body>
</html>
