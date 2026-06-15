-- Migración: tabla `menus` (catálogo de menús del sistema).
-- Aplicar sobre la base de datos mrp_manar (no afecta otras tablas).
--
--   id     -> identificador autoincremental
--   nombre -> nombre del menú
--   estado -> activo (1) / inactivo (0)

CREATE TABLE IF NOT EXISTS `menus` (
    `id`       int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`   varchar(30) NOT NULL,
    `estado`   tinyint(1)  NOT NULL DEFAULT 1,
    `posicion` int(11)     NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
