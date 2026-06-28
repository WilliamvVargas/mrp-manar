-- Migración: tabla `perfiles` (perfiles de usuario).
-- Aplicar sobre la base de datos del proyecto (no afecta otras tablas).
--
-- De momento solo `id` + `nombre`. El nombre es ÚNICO: la clave `uq_perfiles_nombre`, con
-- la colación utf8mb4_general_ci, hace la unicidad case-insensitive ("Admin" = "admin").
-- created_by / updated_by referencian lógicamente a usuarios.id (UUID).

CREATE TABLE IF NOT EXISTS `perfiles` (
    `id`         int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`     varchar(50)      NOT NULL,
    `created_at` datetime         NOT NULL DEFAULT current_timestamp(),
    `created_by` char(36)         DEFAULT NULL,
    `updated_at` datetime         DEFAULT NULL ON UPDATE current_timestamp(),
    `updated_by` char(36)         DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_perfiles_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
