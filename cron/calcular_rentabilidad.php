<?php
/**
 * InverCar - Cálculo automático de rentabilidad semanal
 * Este script debe ejecutarse una vez por semana (cron job)
 *
 * Uso: php calcular_rentabilidad.php
 * Cron: 0 2 * * 1 php /ruta/a/calcular_rentabilidad.php
 */

// Prevenir ejecución desde navegador
if (php_sapi_name() !== 'cli' && !defined('INVERCAR_CRON')) {
    die('Este script solo puede ejecutarse desde CLI o mediante cron');
}

require_once __DIR__ . '/../includes/init.php';

$db = getDB();

// Obtener semana y año actual
$semanaActual = (int) date('W');
$anioActual = (int) date('Y');

// Obtener configuración de rentabilidad
$rentabilidadFija = floatval(getConfig('rentabilidad_fija', 5));

// Calcular rentabilidad variable obtenida (vehículos vendidos)
$rentabilidadObtenida = $db->query("
    SELECT COALESCE(SUM(precio_venta_real - precio_compra - gastos), 0) as total
    FROM vehiculos
    WHERE estado = 'vendido' AND precio_venta_real IS NOT NULL
")->fetch();
$rentabilidadObtenidaTotal = floatval($rentabilidadObtenida['total']);

// Capital invertido en vehículos vendidos
$inversionVendidos = $db->query("
    SELECT COALESCE(SUM(precio_compra + gastos), 0) as total
    FROM vehiculos
    WHERE estado = 'vendido' AND precio_venta_real IS NOT NULL
")->fetch();
$inversionVendidosTotal = floatval($inversionVendidos['total']);

// Porcentaje de rentabilidad variable obtenida
$porcentajeVariableObtenida = $inversionVendidosTotal > 0 ? ($rentabilidadObtenidaTotal / $inversionVendidosTotal) * 100 : 0;

// Calcular rentabilidad variable prevista (vehículos activos)
$rentabilidadPrevista = $db->query("
    SELECT COALESCE(SUM(valor_venta_previsto - precio_compra - prevision_gastos), 0) as total
    FROM vehiculos
    WHERE estado IN ('en_espera', 'en_preparacion', 'en_venta', 'reservado')
")->fetch();
$rentabilidadPrevistaTotal = floatval($rentabilidadPrevista['total']);

// Capital invertido en vehículos activos
$inversionActivos = $db->query("
    SELECT COALESCE(SUM(precio_compra + prevision_gastos), 0) as total
    FROM vehiculos
    WHERE estado IN ('en_espera', 'en_preparacion', 'en_venta', 'reservado')
")->fetch();
$inversionActivosTotal = floatval($inversionActivos['total']);

// Porcentaje de rentabilidad variable prevista
$porcentajeVariablePrevista = $inversionActivosTotal > 0 ? ($rentabilidadPrevistaTotal / $inversionActivosTotal) * 100 : 0;

// Capital total por tipo de inversión
$capitalFija = $db->query("
    SELECT COALESCE(SUM(importe_ingresado) - SUM(importe_retirado), 0) as total
    FROM capital
    WHERE tipo_inversion = 'fija' AND activo = 1
")->fetch()['total'];

$capitalVariable = $db->query("
    SELECT COALESCE(SUM(importe_ingresado) - SUM(importe_retirado), 0) as total
    FROM capital
    WHERE tipo_inversion = 'variable' AND activo = 1
")->fetch()['total'];

// Rentabilidad generada en euros (aproximación semanal)
$rentabilidadGeneradaFija = floatval($capitalFija) * ($rentabilidadFija / 100) / 52; // Dividir por semanas del año
$rentabilidadGeneradaVariable = $rentabilidadObtenidaTotal / 52; // Promedio semanal

// Insertar o actualizar registro para rentabilidad fija
$stmtFija = $db->prepare("
    INSERT INTO rentabilidad_historico (semana, anio, tipo, porcentaje, rentabilidad_generada, capital_base)
    VALUES (?, ?, 'fija', ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        porcentaje = VALUES(porcentaje),
        rentabilidad_generada = VALUES(rentabilidad_generada),
        capital_base = VALUES(capital_base),
        updated_at = CURRENT_TIMESTAMP
");
$stmtFija->execute([$semanaActual, $anioActual, $rentabilidadFija, $rentabilidadGeneradaFija, $capitalFija]);

// Contar vehículos
$numVehiculosActivos = $db->query("
    SELECT COUNT(*) as total FROM vehiculos WHERE estado IN ('en_espera', 'en_preparacion', 'en_venta', 'reservado')
")->fetch()['total'];

$numVehiculosVendidos = $db->query("
    SELECT COUNT(*) as total FROM vehiculos WHERE estado = 'vendido'
")->fetch()['total'];

// Insertar o actualizar registro para rentabilidad variable
$stmtVariable = $db->prepare("
    INSERT INTO rentabilidad_historico (semana, anio, tipo, porcentaje, porcentaje_previsto, rentabilidad_generada, capital_base, inversion_vehiculos, num_vehiculos_activos, num_vehiculos_vendidos)
    VALUES (?, ?, 'variable', ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        porcentaje = VALUES(porcentaje),
        porcentaje_previsto = VALUES(porcentaje_previsto),
        rentabilidad_generada = VALUES(rentabilidad_generada),
        capital_base = VALUES(capital_base),
        inversion_vehiculos = VALUES(inversion_vehiculos),
        num_vehiculos_activos = VALUES(num_vehiculos_activos),
        num_vehiculos_vendidos = VALUES(num_vehiculos_vendidos),
        updated_at = CURRENT_TIMESTAMP
");
$stmtVariable->execute([$semanaActual, $anioActual, $porcentajeVariableObtenida, $porcentajeVariablePrevista, $rentabilidadGeneradaVariable, $capitalVariable, $inversionActivosTotal, $numVehiculosActivos, $numVehiculosVendidos]);

// Log del resultado
$mensaje = sprintf(
    "[%s] Rentabilidad semana %d/%d calculada:\n" .
    "  - Fija: %.2f%% (€%.2f generado sobre €%.2f)\n" .
    "  - Variable Obtenida: %.2f%% (€%.2f generado sobre €%.2f)\n" .
    "  - Variable Prevista: %.2f%%\n",
    date('Y-m-d H:i:s'),
    $semanaActual,
    $anioActual,
    $rentabilidadFija,
    $rentabilidadGeneradaFija,
    $capitalFija,
    $porcentajeVariableObtenida,
    $rentabilidadGeneradaVariable,
    $capitalVariable,
    $porcentajeVariablePrevista
);

echo $mensaje;

// Guardar log
$logFile = __DIR__ . '/logs/rentabilidad_' . date('Y-m') . '.log';
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}
file_put_contents($logFile, $mensaje, FILE_APPEND);
