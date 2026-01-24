<?php
/**
 * InverCar - Logout de Cliente
 */
require_once __DIR__ . '/../includes/init.php';

// Destruir sesión de cliente
unset($_SESSION['cliente_id']);
unset($_SESSION['cliente_nombre']);
unset($_SESSION['verificacion_cliente_id']);

// Regenerar ID de sesión por seguridad
session_regenerate_id(true);

setFlash('success', 'Has cerrado sesión correctamente.');
redirect('login.php');
