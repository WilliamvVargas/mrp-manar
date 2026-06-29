-- Agrega el campo `estado` a usuarios (1 = activo, 0 = inactivo), igual que en menús.
ALTER TABLE `usuarios`
    ADD COLUMN `estado` tinyint(1) NOT NULL DEFAULT 1 AFTER `id_perfil`;
