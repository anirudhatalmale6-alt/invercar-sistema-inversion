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
        'rentabilidad_anual' => 0,
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

        if (empty($nombre) || empty($telefono) || empty($mensaje)) {
            $error_contacto = 'Por favor, completa todos los campos obligatorios.';
        } elseif (!empty($email) && !validarEmail($email)) {
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
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <a href="index.php" class="logo">
                <img src="assets/images/logo-invercar-text.png" alt="InverCar" class="logo-header">
            </a>
            <nav class="nav">
                <ul>
                    <li><a href="#inicio" class="active">Inicio</a></li>
                    <li><a href="#como-funciona">Cómo Funciona</a></li>
                    <li><a href="#como-invertir">Cómo Invertir</a></li>
                    <li><a href="#servicios">Servicios</a></li>
                    <li><a href="#contacto">Contacto</a></li>
                    <li><a href="cliente/login.php">Acceso Clientes</a></li>
                </ul>
            </nav>
            <button class="mobile-menu-toggle">☰</button>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero" id="inicio">
        <!-- Background Elements -->
        <div class="hero-bg">
            <!-- Geometric Pattern -->
            <div class="geo-pattern">
                <div class="geo-line h h1"></div>
                <div class="geo-line h h2"></div>
                <div class="geo-line h h3"></div>
                <div class="geo-line v v1"></div>
                <div class="geo-line v v2"></div>
                <div class="geo-line v v3"></div>
            </div>
            <div class="hero-logo-bg">
                <img src="assets/images/logo-invercar.png" alt="InverCar">
            </div>
            <div class="hero-glow"></div>
            <!-- Minimal Chart -->
            <div class="minimal-chart">
                <svg viewBox="0 0 1400 400" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="minGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" style="stop-color:#d4a84b;stop-opacity:0.4" />
                            <stop offset="100%" style="stop-color:#d4a84b;stop-opacity:0" />
                        </linearGradient>
                    </defs>
                    <rect x="100" y="280" width="30" height="120" fill="url(#minGrad)"/>
                    <rect x="180" y="240" width="30" height="160" fill="url(#minGrad)"/>
                    <rect x="260" y="260" width="30" height="140" fill="url(#minGrad)"/>
                    <rect x="340" y="200" width="30" height="200" fill="url(#minGrad)"/>
                    <rect x="420" y="220" width="30" height="180" fill="url(#minGrad)"/>
                    <rect x="500" y="160" width="30" height="240" fill="url(#minGrad)"/>
                    <rect x="580" y="180" width="30" height="220" fill="url(#minGrad)"/>
                    <rect x="660" y="120" width="30" height="280" fill="url(#minGrad)"/>
                    <rect x="740" y="140" width="30" height="260" fill="url(#minGrad)"/>
                    <rect x="820" y="80" width="30" height="320" fill="url(#minGrad)"/>
                    <rect x="900" y="100" width="30" height="300" fill="url(#minGrad)"/>
                    <rect x="980" y="60" width="30" height="340" fill="url(#minGrad)"/>
                    <path d="M115,260 L195,220 L275,240 L355,180 L435,200 L515,140 L595,160 L675,100 L755,120 L835,60 L915,80 L995,40"
                          stroke="#d4a84b" stroke-width="2" fill="none"/>
                    <circle cx="355" cy="180" r="4" fill="#d4a84b"/>
                    <circle cx="515" cy="140" r="4" fill="#d4a84b"/>
                    <circle cx="675" cy="100" r="4" fill="#d4a84b"/>
                    <circle cx="835" cy="60" r="4" fill="#d4a84b"/>
                    <circle cx="995" cy="40" r="4" fill="#d4a84b"/>
                </svg>
            </div>
        </div>
        <div class="hero-content">
            <div class="hero-eyebrow">Inversión Premium</div>
            <h1>Buena rentabilidad garantizada a través de la <span>compra de bienes</span> (sector automoción)</h1>
            <p>Nosotros nos encargamos de escoger las mejores operaciones para que obtengas una gran rentabilidad sin ningún tipo de riesgo</p>
            <div class="hero-buttons">
                <a href="#como-funciona" class="btn btn-primary">Saber Más</a>
                <a href="cliente/registro.php" class="btn btn-secondary">Empieza a Invertir</a>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Capital Disponible</h3>
                    <div class="value"><?php echo formatMoney($stats['fondos_disponibles']); ?></div>
                    <div class="label">Fondos Disponibles</div>
                </div>
                <div class="stat-card">
                    <h3>Clientes Actuales</h3>
                    <div class="value"><?php echo number_format($stats['clientes_totales'], 0, ',', '.'); ?></div>
                    <div class="label">Inversionistas Activos</div>
                </div>
                <div class="stat-card">
                    <h3>Rentabilidad Actual</h3>
                    <div class="value"><?php echo formatPercent($stats['rentabilidad_anual']); ?></div>
                    <div class="label">Rendimiento Anual</div>
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

    <!-- How It Works Section - Explanation -->
    <section class="how-it-works" id="como-funciona">
        <div class="container">
            <div class="section-title">
                <h2>Cómo Funciona</h2>
                <p>Tu inversión respaldada por activos reales</p>
            </div>
            <div class="explanation-content">
                <div class="explanation-block">
                    <div class="explanation-icon">🚗</div>
                    <h3>Inversión Respaldada por Bienes Reales</h3>
                    <p>En InverCar agrupamos capital de múltiples inversores para adquirir vehículos de alta rotación. <strong>Tu inversión siempre está respaldada por un bien tangible</strong>, lo que minimiza significativamente el riesgo. A diferencia de otras inversiones, aquí existe una garantía real: el propio vehículo.</p>
                </div>
                <div class="explanation-block">
                    <div class="explanation-icon">📊</div>
                    <h3>Elige Tu Modalidad de Rentabilidad</h3>
                    <p>Al depositar tu capital, decides cómo quieres que trabaje tu dinero:</p>
                    <ul class="explanation-list">
                        <li><strong>Rentabilidad Fija:</strong> Inversión 100% garantizada con un mínimo del 10% anual. Ideal si prefieres seguridad y estabilidad. Consulta la rentabilidad actual en nuestro panel.</li>
                        <li><strong>Rentabilidad Variable:</strong> Participas directamente en los beneficios de la venta de vehículos. Mayor potencial de ganancia, aunque no está garantizada. Consulta el rendimiento medio actual.</li>
                    </ul>
                </div>
                <div class="explanation-block">
                    <div class="explanation-icon">💰</div>
                    <h3>Liquidez Total: Tu Dinero Siempre Disponible</h3>
                    <p>Una de las grandes ventajas de InverCar: <strong>puedes retirar tu capital total o parcialmente cuando lo desees</strong>. Al hacerlo, recibirás la proporción de rentabilidad que te corresponda hasta ese momento. Sin permanencias, sin penalizaciones, con total transparencia.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How To Invest Section - Steps -->
    <section class="data-section" id="como-invertir">
        <div class="container">
            <div class="section-title">
                <h2>Cómo Invertir</h2>
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
                <h2>Contacta con Nosotros</h2>
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
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email"
                               value="<?php echo escape($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="telefono">Teléfono *</label>
                        <input type="tel" id="telefono" name="telefono" required
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
