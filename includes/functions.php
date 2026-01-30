<?php
/**
 * InverCar - Funciones auxiliares
 */

if (!defined('INVERCAR')) {
    exit('Acceso no permitido');
}

/**
 * Escapar HTML para prevenir XSS
 */
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Generar token aleatorio seguro
 */
function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Verificar si el usuario está logueado como cliente
 */
function isClienteLogueado() {
    return isset($_SESSION['cliente_id']) && $_SESSION['cliente_id'] > 0;
}

/**
 * Verificar si el usuario está logueado como admin
 */
function isAdminLogueado() {
    return isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0;
}

/**
 * Redireccionar
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Obtener configuración del sistema
 */
function getConfig($clave, $default = null) {
    static $cache = [];

    if (isset($cache[$clave])) {
        return $cache[$clave];
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave = ?");
        $stmt->execute([$clave]);
        $result = $stmt->fetch();

        $cache[$clave] = $result ? $result['valor'] : $default;
        return $cache[$clave];
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Formatear número como moneda (euros)
 */
function formatMoney($amount) {
    return number_format($amount, 2, ',', '.') . ' €';
}

/**
 * Formatear porcentaje
 */
function formatPercent($percent) {
    return number_format($percent, 2, ',', '.') . '%';
}

/**
 * Obtener estadísticas del sistema para la landing
 */
function getEstadisticasSistema() {
    $db = getDB();

    // Número de clientes activos con registro completo
    $stmt = $db->query("SELECT COUNT(*) as total FROM clientes WHERE activo = 1 AND registro_completo = 1");
    $clientesActivos = $stmt->fetch()['total'] ?? 0;

    // Capital total invertido (desde tabla capital)
    $stmt = $db->query("SELECT COALESCE(SUM(importe_ingresado) - SUM(importe_retirado), 0) as total FROM capital WHERE activo = 1");
    $capitalTotal = $stmt->fetch()['total'] ?? 0;

    // Capital por tipo (desde tabla capital)
    $stmt = $db->query("SELECT tipo_inversion, COALESCE(SUM(importe_ingresado) - SUM(importe_retirado), 0) as total FROM capital WHERE activo = 1 GROUP BY tipo_inversion");
    $capitalPorTipo = [];
    foreach ($stmt->fetchAll() as $row) {
        $capitalPorTipo[$row['tipo_inversion']] = $row['total'];
    }

    // Capital invertido en vehículos (en venta + reservados)
    $stmt = $db->query("SELECT COALESCE(SUM(precio_compra + COALESCE(gastos, 0)), 0) as total FROM vehiculos WHERE estado IN ('en_venta', 'reservado')");
    $capitalEnVehiculos = $stmt->fetch()['total'] ?? 0;

    // Inversión actual: suma de valor_venta_previsto de vehículos activos que NO estén en estudio
    $stmt = $db->query("SELECT COALESCE(SUM(valor_venta_previsto), 0) as total FROM vehiculos WHERE estado IN ('en_espera', 'en_preparacion', 'en_venta', 'reservado')");
    $inversionActual = $stmt->fetch()['total'] ?? 0;

    // Número de vehículos comprados pero no vendidos (bienes raíces)
    $stmt = $db->query("SELECT COUNT(*) as total FROM vehiculos WHERE estado IN ('en_estudio', 'en_espera', 'en_preparacion', 'en_venta', 'reservado')");
    $vehiculosActuales = $stmt->fetch()['total'] ?? 0;

    // Valor de venta previsto de vehículos
    $stmt = $db->query("SELECT COALESCE(SUM(valor_venta_previsto), 0) as total FROM vehiculos WHERE estado IN ('en_venta', 'reservado')");
    $valorVentaPrevisto = $stmt->fetch()['total'] ?? 0;

    // Capital en reserva
    $capitalReserva = floatval(getConfig('capital_reserva', 0));

    // Rentabilidad actual variable
    $rentabilidadVariable = floatval(getConfig('rentabilidad_variable_actual', 0));

    // Fondos disponibles = Capital total - Capital en vehículos
    $fondosDisponibles = $capitalTotal - $capitalEnVehiculos;
    if ($fondosDisponibles < 0) $fondosDisponibles = 0;

    // Calcular rentabilidad anual basada en vehículos vendidos
    // Fórmula: (beneficio total / inversión total) * 100, anualizado
    $stmt = $db->query("SELECT COALESCE(SUM(precio_venta_real - precio_compra - COALESCE(gastos, 0)), 0) as beneficio, COALESCE(SUM(precio_compra + COALESCE(gastos, 0)), 0) as inversion FROM vehiculos WHERE estado = 'vendido' AND precio_venta_real > 0");
    $ventasData = $stmt->fetch();
    $beneficioTotal = $ventasData['beneficio'] ?? 0;
    $inversionTotal = $ventasData['inversion'] ?? 0;

    // Rentabilidad anual = (beneficio / inversión) * 100
    // Si no hay datos, usar el valor de configuración
    $rentabilidadAnual = 0;
    if ($inversionTotal > 0 && $beneficioTotal > 0) {
        $rentabilidadAnual = ($beneficioTotal / $inversionTotal) * 100;
    } else {
        // Valor por defecto de configuración (si es mensual, multiplicar por 12)
        $rentabilidadAnual = $rentabilidadVariable * 12;
    }

    return [
        'clientes_totales' => $clientesActivos,
        'capital_total' => $capitalTotal,
        'capital_fija' => $capitalPorTipo['fija'] ?? 0,
        'capital_variable' => $capitalPorTipo['variable'] ?? 0,
        'capital_invertido_vehiculos' => $capitalEnVehiculos,
        'inversion_actual' => $inversionActual,
        'valor_venta_previsto' => $valorVentaPrevisto,
        'capital_reserva' => $capitalReserva,
        'fondos_disponibles' => $fondosDisponibles,
        'rentabilidad_actual' => $rentabilidadVariable,
        'rentabilidad_anual' => $rentabilidadAnual,
        'vehiculos_actuales' => $vehiculosActuales,
    ];
}

/**
 * Validar email
 */
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validar DNI español
 */
function validarDNI($dni) {
    $dni = strtoupper(trim($dni));

    // DNI: 8 números + letra
    if (preg_match('/^[0-9]{8}[A-Z]$/', $dni)) {
        $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
        $numero = substr($dni, 0, 8);
        $letra = substr($dni, -1);
        return $letras[$numero % 23] === $letra;
    }

    // NIE: X/Y/Z + 7 números + letra
    if (preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $dni)) {
        $dni = str_replace(['X', 'Y', 'Z'], ['0', '1', '2'], $dni);
        $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
        $numero = substr($dni, 0, 8);
        $letra = substr($dni, -1);
        return $letras[$numero % 23] === $letra;
    }

    return false;
}

/**
 * Limpiar y validar input
 */
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    return $data;
}

/**
 * Generar mensaje flash
 */
function setFlash($tipo, $mensaje) {
    $_SESSION['flash'] = [
        'tipo' => $tipo,
        'mensaje' => $mensaje
    ];
}

/**
 * Obtener y limpiar mensaje flash
 */
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Protección CSRF - generar token
 */
function csrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Protección CSRF - verificar token
 */
function csrfVerify($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generar campo hidden con token CSRF
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}
