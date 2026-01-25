<?php
/**
 * InverCar - Admin Sidebar Component
 * Include this file in all admin pages for consistent navigation
 */

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);

// SVG Icons as inline strings for better performance
$icons = [
    'panel' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
    'clientes' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'vehiculos' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C19.1 10.7 19 10 19 10l-2-4H7L5 10s-.1.7-1.5 1.1C2.7 11.3 2 12.1 2 13v3c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/><path d="M9 17h6"/><path d="M5 10h14"/></svg>',
    'mensajes' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    'ajustes' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    'logout' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>'
];

// Get unread messages count
if (!isset($mensajesNoLeidos)) {
    $db = getDB();
    $mensajesNoLeidos = $db->query("SELECT COUNT(*) as total FROM contactos WHERE leido = 0")->fetch()['total'];
}
?>
<aside class="sidebar">
    <div class="sidebar-logo">
        <img src="../assets/images/logo-invercar-text.png" alt="InverCar" style="height: 40px; width: auto;">
    </div>
    <div class="sidebar-badge">ADMIN</div>

    <ul class="sidebar-menu">
        <li><a href="index.php" <?php echo $currentPage === 'index.php' ? 'class="active"' : ''; ?>><span class="icon"><?php echo $icons['panel']; ?></span> Panel</a></li>
        <li><a href="clientes.php" <?php echo $currentPage === 'clientes.php' ? 'class="active"' : ''; ?>><span class="icon"><?php echo $icons['clientes']; ?></span> Clientes</a></li>
        <li><a href="vehiculos.php" <?php echo $currentPage === 'vehiculos.php' ? 'class="active"' : ''; ?>><span class="icon"><?php echo $icons['vehiculos']; ?></span> Vehículos</a></li>

        <li class="sidebar-section">Configuración</li>
        <li><a href="contactos.php" <?php echo $currentPage === 'contactos.php' ? 'class="active"' : ''; ?>><span class="icon"><?php echo $icons['mensajes']; ?></span> Mensajes <?php if($mensajesNoLeidos > 0): ?><span class="badge badge-danger"><?php echo $mensajesNoLeidos; ?></span><?php endif; ?></a></li>
        <li><a href="configuracion.php" <?php echo $currentPage === 'configuracion.php' ? 'class="active"' : ''; ?>><span class="icon"><?php echo $icons['ajustes']; ?></span> Ajustes</a></li>

        <li class="sidebar-section">Cuenta</li>
        <li><a href="logout.php"><span class="icon"><?php echo $icons['logout']; ?></span> Cerrar sesión</a></li>
    </ul>
</aside>
