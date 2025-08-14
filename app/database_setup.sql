
-- Crear base de datos si no existe
CREATE DATABASE IF NOT EXISTS `api_system` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Usar la base de datos
USE `api_system`;

-- Crear tabla queries para almacenar las consultas SQL
CREATE TABLE IF NOT EXISTS `queries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) UNIQUE NOT NULL COMMENT 'Título único de la consulta',
    `sql_query` TEXT NOT NULL COMMENT 'Consulta SQL completa',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla para almacenar consultas SQL del sistema';

-- Crear índices para mejorar el rendimiento
CREATE INDEX `idx_title` ON `queries` (`title`);
CREATE INDEX `idx_created_at` ON `queries` (`created_at`);

-- Insertar algunas consultas de ejemplo (opcional)
INSERT INTO `queries` (`title`, `sql_query`) VALUES
('usuarios_activos', 'SELECT id, nombre, email, status FROM users WHERE status = "active"'),
('productos_disponibles', 'SELECT id, nombre, precio, stock FROM products WHERE stock > 0'),
('ventas_recientes', 'SELECT id, cliente, monto, fecha FROM sales WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)')
ON DUPLICATE KEY UPDATE `sql_query` = VALUES(`sql_query`);

-- Verificar que la tabla se creó correctamente
DESCRIBE `queries`;

-- Mostrar las consultas insertadas
SELECT * FROM `queries`;
