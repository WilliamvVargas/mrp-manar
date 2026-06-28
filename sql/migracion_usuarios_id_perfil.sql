-- Migración: agrega `id_perfil` a la tabla `usuarios` (perfil asignado al usuario).
-- Aplicar UNA vez (ALTER no es idempotente).
--
-- Nullable: los usuarios existentes no tienen perfil asignado. FK a `perfiles`; si se
-- elimina un perfil, el id_perfil de los usuarios que lo tenían queda en NULL (SET NULL).

ALTER TABLE `usuarios`
    ADD COLUMN `id_perfil` int(10) UNSIGNED DEFAULT NULL AFTER `apellidos`,
    ADD KEY `idx_usuarios_perfil` (`id_perfil`),
    ADD CONSTRAINT `fk_usuarios_perfil` FOREIGN KEY (`id_perfil`) REFERENCES `perfiles` (`id`) ON DELETE SET NULL;
