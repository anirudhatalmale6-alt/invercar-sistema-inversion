<?php
/**
 * InverCar - Logout de Administrador
 */
require_once __DIR__ . '/../includes/init.php';

// Destruir sesión de admin
unset($_SESSION['admin_id']);
unset($_SESSION['admin_nombre']);
unset($_SESSION['admin_usuario']);

// Regenerar ID de sesión por seguridad
session_regenerate_id(true);

redirect('login.php');
