-- ============================================================================
--  Empresas: opción "Controla vencimiento" (2026-10-28)
-- ----------------------------------------------------------------------------
--  1 = la empresa controla vencimiento: el MRP y las consultas usan la fecha de
--      vencimiento de cada pallet del WMS (alimentos, ej. Manar).
--  0 = productos no perecibles (ej. Molderil): el WMS graba fechas sin sentido
--      (la de ingreso o 01-01-1900), así que el stock se muestra "Sin vencimiento"
--      y siempre cuenta como vigente.
--
--  Ejecutar UNA vez en cada base MySQL de la aplicación (desarrollo, producción...).
--  Mientras una base no tenga la columna, la aplicación mantiene la corrección
--  anterior (fija para la empresa 2 del WMS), así que no falla.
-- ============================================================================

ALTER TABLE `empresas`
    ADD COLUMN `controla_vencimiento` tinyint(1) NOT NULL DEFAULT 1
        COMMENT 'Controla vencimiento del stock (1) o productos no perecibles (0): sin vencimiento en MRP/consultas'
        AFTER `mrp_z_seguridad`;

-- Molderil (empresa 2 del WMS) distribuye productos no perecibles.
UPDATE `empresas` SET `controla_vencimiento` = 0 WHERE `empresa_wms` = 2;

-- Verificación
SELECT nombre, empresa_wms, controla_vencimiento FROM empresas ORDER BY posicion;
