-- Migration: Add recibir_notificaciones field to clientes table
-- Date: 2026-02-01

ALTER TABLE clientes ADD COLUMN IF NOT EXISTS recibir_notificaciones TINYINT(1) NOT NULL DEFAULT 1;

-- If "IF NOT EXISTS" is not supported:
-- ALTER TABLE clientes ADD COLUMN recibir_notificaciones TINYINT(1) NOT NULL DEFAULT 1;
