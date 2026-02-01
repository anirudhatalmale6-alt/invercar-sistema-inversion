-- InverCar - Migration: Mensajes cliente-admin y configuración cliente
-- Ejecutar después de schema.sql

SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Tabla: mensajes_cliente (sistema de mensajería cliente-admin)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mensajes_cliente` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cliente_id` INT UNSIGNED NOT NULL,
  `remitente` ENUM('cliente', 'admin') NOT NULL,
  `mensaje` TEXT NOT NULL,
  `leido` TINYINT(1) NOT NULL DEFAULT 0,
  `leido_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cliente` (`cliente_id`),
  KEY `idx_remitente` (`remitente`),
  KEY `idx_leido` (`leido`),
  CONSTRAINT `fk_mensaje_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: cliente_configuracion (preferencias del cliente)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cliente_configuracion` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cliente_id` INT UNSIGNED NOT NULL,
  `notificar_vehiculos_nuevos` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Recibir email cuando se añade un nuevo vehículo',
  `notificar_mensajes` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Recibir email cuando hay un nuevo mensaje del admin',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cliente_unico` (`cliente_id`),
  CONSTRAINT `fk_config_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
