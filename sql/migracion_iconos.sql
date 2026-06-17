-- Migración: tabla `iconos` (catálogo de iconos del sistema).
-- Aplicar sobre la base de datos del proyecto (no afecta otras tablas).
--
--   id         -> identificador autoincremental
--   nombre     -> nombre visible / etiqueta del icono
--   tipo       -> 'bootstrap' (nativo) o 'personalizado' (SVG subido)
--   valor      -> id del símbolo en el sprite (bootstrap: nombre oficial; personalizado: id con prefijo)
--   archivo    -> nombre del SVG original (solo personalizados; NULL en bootstrap)
--   coloreable -> 1 = monocromático (se tiñe con currentColor); 0 = multicolor
--   posicion   -> orden de aparición (al crear toma MAX(posicion) + 1)
--
-- created_by / updated_by referencian lógicamente a usuarios.id (UUID).

CREATE TABLE IF NOT EXISTS `iconos` (
    `id`         int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`     varchar(60)  NOT NULL,
    `tipo`       enum('bootstrap','personalizado') NOT NULL,
    `valor`      varchar(60)  NOT NULL,
    `archivo`    varchar(255) DEFAULT NULL,
    `coloreable` tinyint(1)   NOT NULL DEFAULT 1,
    `posicion`   int(11)      NOT NULL,
    `created_at` timestamp    NOT NULL DEFAULT current_timestamp(),
    `created_by` varchar(36)  DEFAULT NULL,
    `updated_at` timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `updated_by` varchar(36)  DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_iconos_valor` (`valor`),
    KEY `idx_iconos_tipo` (`tipo`),
    KEY `idx_iconos_posicion` (`posicion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
