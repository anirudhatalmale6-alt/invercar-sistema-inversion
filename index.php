<?php
/**
 * InverCar - Landing Page
 */
require_once __DIR__ . '/includes/init.php';

// Obtener estadísticas del sistema
try {
    $stats = getEstadisticasSistema();
} catch (Exception $e) {
    // Valores por defecto si hay error de BD
    $stats = [
        'clientes_totales' => 0,
        'capital_total' => 0,
        'fondos_disponibles' => 0,
        'rentabilidad_actual' => 0,
        'capital_invertido_vehiculos' => 0,
    ];
}

// Procesar formulario de contacto
$mensaje_enviado = false;
$error_contacto = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contacto'])) {
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $error_contacto = 'Error de seguridad. Por favor, recarga la página.';
    } else {
        $nombre = cleanInput($_POST['nombre'] ?? '');
        $email = cleanInput($_POST['email'] ?? '');
        $telefono = cleanInput($_POST['telefono'] ?? '');
        $mensaje = cleanInput($_POST['mensaje'] ?? '');

        if (empty($nombre) || empty($email) || empty($mensaje)) {
            $error_contacto = 'Por favor, completa todos los campos obligatorios.';
        } elseif (!validarEmail($email)) {
            $error_contacto = 'El email no es válido.';
        } else {
            try {
                $db = getDB();
                $stmt = $db->prepare("INSERT INTO contactos (nombre, email, telefono, mensaje) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nombre, $email, $telefono, $mensaje]);
                $mensaje_enviado = true;
            } catch (Exception $e) {
                $error_contacto = 'Error al enviar el mensaje. Inténtalo de nuevo.';
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
    <title>InverCar - Maximiza tus Inversiones</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <a href="index.php" class="logo"><img src="assets/images/logo-invercar.png" alt="InverCar" style="height: 70px; max-width: 200px;"></a>
            <nav class="nav">
                <ul>
                    <li><a href="#inicio" class="active">Inicio</a></li>
                    <li><a href="#como-funciona">Cómo Funciona</a></li>
                    <li><a href="#servicios">Servicios</a></li>
                    <li><a href="#contacto">Contacto</a></li>
                    <li><a href="cliente/login.php" class="btn btn-outline" style="padding: 10px 20px;">Acceso Clientes</a></li>
                </ul>
            </nav>
            <button class="mobile-menu-toggle">☰</button>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero" id="inicio">
        <div class="hero-content">
            <h1>Maximiza tus Inversiones con <span>Nosotros</span></h1>
            <p>Obtén la mejor rentabilidad con nuestro sistema de inversión avanzado en el sector automovilístico.</p>
            <a href="#contacto" class="btn btn-primary">Empieza a Invertir</a>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Rentabilidad Actual</h3>
                    <div class="value"><?php echo formatPercent($stats['rentabilidad_actual']); ?></div>
                    <div class="label">Rendimiento Mensual</div>
                </div>
                <div class="stat-card">
                    <h3>Clientes Actuales</h3>
                    <div class="value"><?php echo number_format($stats['clientes_totales'], 0, ',', '.'); ?></div>
                    <div class="label">Inversionistas Activos</div>
                </div>
                <div class="stat-card">
                    <h3>Capital Disponible</h3>
                    <div class="value"><?php echo formatMoney($stats['fondos_disponibles']); ?></div>
                    <div class="label">Fondos Disponibles</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Data Section -->
    <section class="data-section">
        <div class="container">
            <div class="section-title">
                <h2>Datos del Sistema</h2>
                <p>Información en tiempo real desde nuestra base de datos.</p>
            </div>
            <div class="data-grid">
                <div class="data-item">
                    <div class="label">Clientes Totales</div>
                    <div class="value"><?php echo number_format($stats['clientes_totales'], 0, ',', '.'); ?></div>
                </div>
                <div class="data-item">
                    <div class="label">Fondos Usados</div>
                    <div class="value"><?php echo formatMoney($stats['capital_invertido_vehiculos']); ?></div>
                </div>
                <div class="data-item">
                    <div class="label">Capital Disponible</div>
                    <div class="value"><?php echo formatMoney($stats['fondos_disponibles']); ?></div>
                </div>
                <div class="data-item">
                    <div class="label">Capital Total</div>
                    <div class="value"><?php echo formatMoney($stats['capital_total']); ?></div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works" id="como-funciona">
        <div class="container">
            <div class="section-title">
                <h2>Cómo Funciona</h2>
                <p>Proceso sencillo para empezar a invertir con InverCar</p>
            </div>
            <div class="steps-grid">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Regístrate</h3>
                    <p>Crea tu cuenta en pocos minutos. Solo necesitas tus datos básicos para comenzar.</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Elige tu Inversión</h3>
                    <p>Selecciona entre rentabilidad fija o variable según tu perfil de inversor.</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Invierte tu Capital</h3>
                    <p>Deposita el capital que desees invertir de forma segura.</p>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <h3>Recibe Rentabilidad</h3>
                    <p>Observa cómo crece tu inversión y recibe beneficios periódicamente.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="data-section" id="servicios">
        <div class="container">
            <div class="section-title">
                <h2>Nuestros Servicios</h2>
                <p>Dos opciones de inversión adaptadas a tus necesidades</p>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Rentabilidad Fija</h3>
                    <div class="value"><?php echo formatPercent(floatval(getConfig('rentabilidad_fija', 5))); ?></div>
                    <div class="label">Mensual Garantizado</div>
                    <p style="margin-top: 15px; color: var(--text-muted); font-size: 0.9rem;">
                        Ideal para inversores conservadores que buscan estabilidad y seguridad en sus inversiones.
                    </p>
                </div>
                <div class="stat-card">
                    <h3>Rentabilidad Variable</h3>
                    <div class="value"><?php echo formatPercent($stats['rentabilidad_actual']); ?></div>
                    <div class="label">Rendimiento Actual</div>
                    <p style="margin-top: 15px; color: var(--text-muted); font-size: 0.9rem;">
                        Para inversores que buscan maximizar beneficios con el rendimiento real de las operaciones.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section" id="contacto">
        <div class="container">
            <div class="section-title">
                <h2>Empieza a Invertir</h2>
                <p>Contáctanos y te ayudaremos a comenzar tu inversión</p>
            </div>

            <?php if ($mensaje_enviado): ?>
                <div class="contact-form">
                    <div class="alert alert-success">
                        <strong>¡Mensaje enviado!</strong><br>
                        Gracias por contactarnos. Te responderemos lo antes posible.
                    </div>
                    <a href="index.php" class="btn btn-primary" style="width: 100%; text-align: center;">Volver al inicio</a>
                </div>
            <?php else: ?>
                <form class="contact-form" method="POST" action="#contacto">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="contacto" value="1">

                    <?php if ($error_contacto): ?>
                        <div class="alert alert-error"><?php echo escape($error_contacto); ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="nombre">Nombre completo *</label>
                        <input type="text" id="nombre" name="nombre" required
                               value="<?php echo escape($_POST['nombre'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required
                               value="<?php echo escape($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="telefono">Teléfono</label>
                        <input type="tel" id="telefono" name="telefono"
                               value="<?php echo escape($_POST['telefono'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="mensaje">Mensaje *</label>
                        <textarea id="mensaje" name="mensaje" required><?php echo escape($_POST['mensaje'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">Enviar Mensaje</button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> InverCar. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script>
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
