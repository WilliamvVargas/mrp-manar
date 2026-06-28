-- Migración: tabla `accesos` (qué ítems menú tiene habilitados cada perfil).
-- Un registro por combinación (perfil, ítem menú), única.
--
-- `estado`: 1 = con acceso (marcado), 0 = acceso revocado (desmarcado). Al DESMARCAR NO se
-- elimina el registro: se cambia el estado a 0. Así queda el rastro de quién lo modificó
-- (created_by = quién lo concedió; updated_by = último que lo cambió, p. ej. quién lo revocó).
-- created_by / updated_by referencian lógicamente a usuarios.id (UUID).

CREATE TABLE IF NOT EXISTS `accesos` (
    `id`           int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_perfil`    int(10) UNSIGNED NOT NULL,
    `id_item_menu` int(10) UNSIGNED NOT NULL,
    `estado`       tinyint(1)       NOT NULL DEFAULT 1,   -- 1 = con acceso, 0 = revocado
    `created_at`   datetime         NOT NULL DEFAULT current_timestamp(),
    `created_by`   char(36)         DEFAULT NULL,
    `updated_at`   datetime         DEFAULT NULL ON UPDATE current_timestamp(),
    `updated_by`   char(36)         DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_accesos_perfil_item` (`id_perfil`, `id_item_menu`),
    KEY `idx_accesos_item` (`id_item_menu`),
    CONSTRAINT `fk_accesos_perfil`    FOREIGN KEY (`id_perfil`)    REFERENCES `perfiles` (`id`)   ON DELETE CASCADE,
    CONSTRAINT `fk_accesos_item_menu` FOREIGN KEY (`id_item_menu`) REFERENCES `item_menus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
