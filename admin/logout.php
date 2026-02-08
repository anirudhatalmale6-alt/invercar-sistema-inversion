<?php
/**
 * InverCar - Logout de Administrador
 */
require_once __DIR__ . '/../includes/init.php';

// Destruir sesión de admin
unset($_SESSION['admin_id']);
unset($_SESSION['admin_nombre']);
unset($_SESSION['admin_usuario']);

// Borrar cookie de recordar
if (isset($_COOKIE['invercar_remember'])) {
    setcookie('invercar_remember', '', time() - 3600, '/', '', false, true);
}

// Regenerar ID de sesión por seguridad
session_regenerate_id(true);

redirect('login.php');
