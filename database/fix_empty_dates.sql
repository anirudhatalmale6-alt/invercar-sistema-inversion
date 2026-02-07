-- Corregir fechas vacías o inválidas en la tabla vehiculos
-- Las columnas DATE en MySQL no deben tener cadenas vacías

-- Primero deshabilitar temporalmente el modo SQL estricto si causa problemas
SET sql_mode = '';

-- Actualizar fecha_transporte vacía o inválida a NULL
UPDATE vehiculos SET fecha_transporte = NULL WHERE fecha_transporte = '' OR fecha_transporte = '0000-00-00' OR fecha_transporte IS NOT NULL AND fecha_transporte < '1970-01-01';

-- Actualizar fecha_recepcion vacía o inválida a NULL
UPDATE vehiculos SET fecha_recepcion = NULL WHERE fecha_recepcion = '' OR fecha_recepcion = '0000-00-00' OR fecha_recepcion IS NOT NULL AND fecha_recepcion < '1970-01-01';

-- Actualizar fecha_documentacion vacía o inválida a NULL
UPDATE vehiculos SET fecha_documentacion = NULL WHERE fecha_documentacion = '' OR fecha_documentacion = '0000-00-00' OR fecha_documentacion IS NOT NULL AND fecha_documentacion < '1970-01-01';

-- Actualizar fecha_puesta_venta vacía o inválida a NULL
UPDATE vehiculos SET fecha_puesta_venta = NULL WHERE fecha_puesta_venta = '' OR fecha_puesta_venta = '0000-00-00' OR fecha_puesta_venta IS NOT NULL AND fecha_puesta_venta < '1970-01-01';

-- Actualizar fecha_venta vacía o inválida a NULL
UPDATE vehiculos SET fecha_venta = NULL WHERE fecha_venta = '' OR fecha_venta = '0000-00-00' OR fecha_venta IS NOT NULL AND fecha_venta < '1970-01-01';

-- Verificar
SELECT id, fecha_transporte, fecha_recepcion, fecha_documentacion, fecha_puesta_venta, fecha_venta
FROM vehiculos
WHERE fecha_transporte IS NOT NULL
   OR fecha_recepcion IS NOT NULL
   OR fecha_documentacion IS NOT NULL;
