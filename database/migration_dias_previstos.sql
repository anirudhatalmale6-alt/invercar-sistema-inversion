-- Migración: Añadir campo dias_previstos a vehículos
-- Este campo almacena los días previstos para la venta del vehículo
-- Por defecto 75 días, se actualiza con la media del proveedor cuando se vende

ALTER TABLE vehiculos ADD COLUMN IF NOT EXISTS dias_previstos INT UNSIGNED DEFAULT 75;

-- Actualizar vehículos existentes con 75 días si no tienen valor
UPDATE vehiculos SET dias_previstos = 75 WHERE dias_previstos IS NULL;
