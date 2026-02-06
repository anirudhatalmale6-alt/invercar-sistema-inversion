<?php
/**
 * InverCar - Cliente Sidebar Component
 */

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!-- Mobile Menu Toggle -->
<button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Abrir menú">☰</button>
<div class="mobile-overlay" id="mobileOverlay"></div>
<?php

// SVG Icons
$icons = [
    'panel' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>',
    'datos' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    'movimientos' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
    'mensajes' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    'config' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    'logout' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
    'chevron' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>'
];

// Get unread messages count for this client
$mensajesNoLeidos = 0;
if (isset($_SESSION['cliente_id'])) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM mensajes_cliente WHERE cliente_id = ? AND remitente = 'admin' AND leido = 0");
        $stmt->execute([$_SESSION['cliente_id']]);
        $mensajesNoLeidos = $stmt->fetch()['total'] ?? 0;
    } catch (Exception $e) {
        // Table may not exist yet
    }
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <a href="panel.php">
                <img src="../assets/images/logo-invercar-text.png" alt="InverCar" class="logo-full">
                <img src="../assets/images/logo-invercar.png" alt="IC" class="logo-mini">
            </a>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle" title="Ocultar menú">
            <?php echo $icons['chevron']; ?>
        </button>
    </div>

    <ul class="sidebar-menu">
        <li><a href="panel.php" <?php echo $currentPage === 'panel.php' ? 'class="active"' : ''; ?>><span class="icon"><?php echo $icons['panel']; ?></span><span class="menu-text">Panel</span></a></li>
        <li><a href="mis-datos.php" <?php echo $currentPage === 'mis-datos.php' ? 'class="active"' : ''; ?>><span class="icon"><?php echo $icons['datos']; ?></span><span class="menu-text">Mis Datos</span></a></li>
        <li><a href="movimientos.php" <?php echo $currentPage === 'movimientos.php' ? 'class="active"' : ''; ?>><span class="icon"><?php echo $icons['movimientos']; ?></span><span class="menu-text">Movimientos</span></a></li>
        <li><a href="mensajes.php" <?php echo $currentPage === 'mensajes.php' ? 'class="active"' : ''; ?>><span class="icon"><?php echo $icons['mensajes']; ?></span><span class="menu-text">Mensajes</span><?php if($mensajesNoLeidos > 0): ?><span class="menu-badge"><?php echo $mensajesNoLeidos; ?></span><?php endif; ?></a></li>
        <li><a href="configuracion.php" <?php echo $currentPage === 'configuracion.php' ? 'class="active"' : ''; ?>><span class="icon"><?php echo $icons['config']; ?></span><span class="menu-text">Configuración</span></a></li>
    </ul>

    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn" title="Cerrar Sesión">
            <span class="icon"><?php echo $icons['logout']; ?></span>
            <span class="menu-text">Salir</span>
        </a>
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const mainContent = document.querySelector('.main-content');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mobileOverlay = document.getElementById('mobileOverlay');

    // Restaurar estado del sidebar (solo en desktop)
    if (window.innerWidth > 768) {
        const isCollapsed = localStorage.getItem('clienteSidebarCollapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
            if (mainContent) mainContent.classList.add('sidebar-collapsed');
        }
    }

    // Desktop toggle
    if (toggle) {
        toggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            if (mainContent) mainContent.classList.toggle('sidebar-collapsed');
            localStorage.setItem('clienteSidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
    }

    // Mobile menu toggle
    if (mobileMenuToggle && mobileOverlay) {
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-open');
            mobileOverlay.classList.toggle('active');
            this.textContent = sidebar.classList.contains('mobile-open') ? '✕' : '☰';
        });

        // Close menu when clicking overlay
        mobileOverlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-open');
            mobileOverlay.classList.remove('active');
            mobileMenuToggle.textContent = '☰';
        });

        // Close menu when clicking a menu link (on mobile)
        sidebar.querySelectorAll('.sidebar-menu a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('mobile-open');
                    mobileOverlay.classList.remove('active');
                    mobileMenuToggle.textContent = '☰';
                }
            });
        });
    }
});
</script>
