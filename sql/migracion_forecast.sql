-- Migración: tabla `forecast` para los registros de la carga masiva.
-- Aplicar sobre la base de datos mrp_manar (no afecta otras tablas).
--
--   id              -> identificador autoincremental
--   campos de datos -> provenientes del Excel (sin el nombre del producto)
--   created_*/updated_* -> auditoría, igual que en la tabla `usuarios`

CREATE TABLE IF NOT EXISTS `forecast` (
    `id`              int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `empresa`         varchar(50)  NOT NULL,
    `version`         varchar(20)  NOT NULL,
    `codigo_cliente`  varchar(20)  NOT NULL,
    `nombre_cliente`  varchar(256) NOT NULL,
    `fecha`           date         NOT NULL,
    `codigo_producto` varchar(20)  NOT NULL,
    `nombre_producto` varchar(256) NOT NULL,
    `cantidad`        int(11)      NOT NULL,
    `created_at`      timestamp    NOT NULL DEFAULT current_timestamp(),
    `created_by`      varchar(36)  DEFAULT NULL,
    `updated_at`      timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `updated_by`      varchar(36)  DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
