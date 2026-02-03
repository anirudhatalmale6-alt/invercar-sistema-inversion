-- Migration: Add tipo_liquidacion field to clientes table
-- Date: 2026-02-02
-- Description: Stores the preferred interest liquidation frequency (trimestral, semestral, anual)

ALTER TABLE clientes ADD COLUMN IF NOT EXISTS tipo_liquidacion ENUM('trimestral', 'semestral', 'anual') NOT NULL DEFAULT 'trimestral';

-- If "IF NOT EXISTS" is not supported:
-- ALTER TABLE clientes ADD COLUMN tipo_liquidacion ENUM('trimestral', 'semestral', 'anual') NOT NULL DEFAULT 'trimestral';
