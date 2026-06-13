-- Migración: columnas de auditoría en la tabla `usuarios`.
-- Aplicar sobre una base de datos EXISTENTE (no recrea la tabla, conserva los datos).
--
--   created_by  -> id del usuario que creó el registro
--   updated_at  -> fecha de última modificación (se actualiza sola)
--   updated_by  -> id del usuario que realizó la última modificación
--
-- created_by / updated_by referencian lógicamente a usuarios.id (UUID).
-- Los registros previos a esta migración quedarán con created_by/updated_by en NULL.

ALTER TABLE `usuarios`
  ADD COLUMN `created_by` varchar(36) DEFAULT NULL AFTER `created_at`,
  ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `created_by`,
  ADD COLUMN `updated_by` varchar(36) DEFAULT NULL AFTER `updated_at`;
