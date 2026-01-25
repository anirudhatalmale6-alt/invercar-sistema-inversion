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

// Configuración de Email (SMTP Hostalia)
define('SMTP_HOST', 'smtp.hostalia.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'tu_email@tu-dominio.com');
define('SMTP_PASS', 'tu_password_email');
define('SMTP_FROM', 'noreply@tu-dominio.com');
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
    session_start();
}
