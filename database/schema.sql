-- InverCar Database Schema
-- Compatible con MySQL 5.7+ / MariaDB 10.x (Hostalia)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Tabla: administradores
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `administradores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: clientes
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `clientes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `apellidos` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `email_verificado` TINYINT(1) NOT NULL DEFAULT 0,
  `token_verificacion` VARCHAR(100) DEFAULT NULL,
  `token_expira` DATETIME DEFAULT NULL,
  `dni` VARCHAR(20) DEFAULT NULL,
  `direccion` VARCHAR(255) DEFAULT NULL,
  `codigo_postal` VARCHAR(10) DEFAULT NULL,
  `poblacion` VARCHAR(100) DEFAULT NULL,
  `provincia` VARCHAR(100) DEFAULT NULL,
  `pais` VARCHAR(100) DEFAULT 'España',
  `telefono` VARCHAR(20) DEFAULT NULL,
  `capital_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Capital total aportado por el cliente',
  `capital_invertido` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Capital actualmente invertido en vehículos',
  `capital_reserva` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Capital pendiente de invertir (en reserva)',
  `tipo_inversion` ENUM('fija', 'variable') DEFAULT NULL,
  `registro_completo` TINYINT(1) NOT NULL DEFAULT 0,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_tipo_inversion` (`tipo_inversion`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: rentabilidad_semanal (histórico de rentabilidad por cliente)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rentabilidad_semanal` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cliente_id` INT UNSIGNED NOT NULL,
  `semana` TINYINT UNSIGNED NOT NULL COMMENT 'Número de semana (1-9)',
  `anio` YEAR NOT NULL,
  `rentabilidad_porcentaje` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `rentabilidad_euros` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cliente_semana_anio` (`cliente_id`, `semana`, `anio`),
  CONSTRAINT `fk_rentabilidad_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: rentabilidad_historico (histórico global de rentabilidad semanal)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rentabilidad_historico` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `semana` TINYINT UNSIGNED NOT NULL COMMENT 'Número de semana del año (1-52)',
  `anio` YEAR NOT NULL,
  `tipo` ENUM('fija', 'variable') NOT NULL,
  `porcentaje` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `rentabilidad_generada` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Rentabilidad absoluta generada en euros',
  `capital_base` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Capital invertido esa semana',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `semana_anio_tipo` (`semana`, `anio`, `tipo`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_anio_semana` (`anio`, `semana`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: vehiculos
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vehiculos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `matricula` VARCHAR(20) DEFAULT NULL COMMENT 'Matrícula del vehículo (opcional)',
  `marca` VARCHAR(50) NOT NULL,
  `modelo` VARCHAR(100) NOT NULL,
  `version` VARCHAR(100) DEFAULT NULL,
  `anio` YEAR NOT NULL,
  `kilometros` INT UNSIGNED DEFAULT NULL COMMENT 'Kilómetros del vehículo',
  `precio_compra` DECIMAL(15,2) NOT NULL,
  `prevision_gastos` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Previsión de gastos estimados',
  `gastos` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Gastos reales',
  `valor_venta_previsto` DECIMAL(15,2) NOT NULL,
  `precio_venta_real` DECIMAL(15,2) DEFAULT NULL,
  `beneficio` DECIMAL(15,2) GENERATED ALWAYS AS (
    CASE
      WHEN precio_venta_real IS NOT NULL THEN precio_venta_real - precio_compra - gastos
      ELSE valor_venta_previsto - precio_compra - gastos
    END
  ) STORED,
  `foto` VARCHAR(255) DEFAULT NULL,
  `estado` ENUM('en_estudio', 'en_preparacion', 'en_venta', 'vendido', 'reservado') NOT NULL DEFAULT 'en_estudio',
  `fecha_compra` DATE DEFAULT NULL,
  `fecha_venta` DATE DEFAULT NULL,
  `notas` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_marca` (`marca`),
  KEY `idx_matricula` (`matricula`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: inversiones_vehiculo (vincula capital de clientes con vehículos)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inversiones_vehiculo` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vehiculo_id` INT UNSIGNED NOT NULL,
  `cliente_id` INT UNSIGNED NOT NULL,
  `importe_invertido` DECIMAL(15,2) NOT NULL COMMENT 'Cantidad del capital del cliente invertida en este vehículo',
  `porcentaje_participacion` DECIMAL(5,2) NOT NULL COMMENT 'Porcentaje de participación en el vehículo',
  `tipo_inversion` ENUM('fija', 'variable') NOT NULL,
  `fecha_inversion` DATE NOT NULL,
  `fecha_desinversion` DATE DEFAULT NULL COMMENT 'Fecha cuando se vendió el vehículo y se liberó el capital',
  `rentabilidad_obtenida` DECIMAL(15,2) DEFAULT NULL COMMENT 'Rentabilidad obtenida tras venta',
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vehiculo` (`vehiculo_id`),
  KEY `idx_cliente` (`cliente_id`),
  KEY `idx_activo` (`activo`),
  CONSTRAINT `fk_inversion_vehiculo` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inversion_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: contactos (formulario de contacto landing)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contactos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `telefono` VARCHAR(20) DEFAULT NULL,
  `mensaje` TEXT NOT NULL,
  `leido` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_leido` (`leido`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: configuracion (parámetros del sistema)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `configuracion` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clave` VARCHAR(50) NOT NULL,
  `valor` TEXT NOT NULL,
  `descripcion` VARCHAR(255) DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Datos iniciales de configuración
-- --------------------------------------------------------
INSERT INTO `configuracion` (`clave`, `valor`, `descripcion`) VALUES
('rentabilidad_fija', '5.00', 'Porcentaje de rentabilidad fija mensual'),
('rentabilidad_variable_actual', '14.80', 'Porcentaje de rentabilidad variable actual'),
('capital_reserva', '500000.00', 'Capital en reserva del sistema'),
('nombre_empresa', 'InverCar', 'Nombre de la empresa'),
('email_empresa', 'info@invercar.com', 'Email de contacto'),
('telefono_empresa', '+34 900 000 000', 'Teléfono de contacto');

-- --------------------------------------------------------
-- Usuario administrador por defecto (password: admin123 - CAMBIAR EN PRODUCCIÓN)
-- --------------------------------------------------------
INSERT INTO `administradores` (`usuario`, `password`, `nombre`, `email`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'admin@invercar.com');

SET FOREIGN_KEY_CHECKS = 1;
