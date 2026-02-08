<?php
/**
 * InverCar - Configuración principal
 * Modificar estos valores según el entorno (Hostalia)
 */

// Evitar acceso directo
if (!defined('INVERCAR')) {
    exit('Acceso no permitido');
}

// Configuración de Base de Datos
define('DB_HOST', 'PMYSQL126.dns-servicio.com');
define('DB_NAME', '8542231_InverCar');
define('DB_USER', 'AlbRod');
define('DB_PASS', 'AlbRodRod*2026');
define('DB_CHARSET', 'utf8mb4');

// Configuración del sitio
define('SITE_URL', 'https://invercar.garaje86.com');
define('SITE_NAME', 'InverCar');

// Configuración de Email (SMTP)
define('SMTP_HOST', 'smtp.servidor-correo.net');
define('SMTP_PORT', 587);
define('SMTP_USER', 'info@garaje86.com');
define('SMTP_PASS', 'Alcaja01*2026');
define('SMTP_FROM', 'info@garaje86.com');
define('SMTP_FROM_NAME', 'InverCar');

// Configuración de seguridad
define('HASH_COST', 12); // Para password_hash
define('TOKEN_EXPIRY_HOURS', 24); // Horas para expiración de token de verificación

// Rutas del sistema
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('TEMPLATES_PATH', ROOT_PATH . '/templates');
define('UPLOADS_PATH', ROOT_PATH . '/assets/uploads');

// Zona horaria
date_default_timezone_set('Europe/Madrid');

// Manejo de errores (cambiar a false en producción)
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    // Si hay cookie de recordar, extender la sesión
    if (isset($_COOKIE['invercar_remember']) && $_COOKIE['invercar_remember'] === '1') {
        session_set_cookie_params(30 * 24 * 3600); // 30 días
    }
    session_start();
}
