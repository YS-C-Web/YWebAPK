CREATE TABLE IF NOT EXISTS `mensajes_contacto` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(80) NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `asunto` VARCHAR(30) NOT NULL DEFAULT 'otro',
  `mensaje` TEXT NOT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `leido` TINYINT(1) NOT NULL DEFAULT 0,
  `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_leido` (`leido`),
  INDEX `idx_creado` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;