-- ============================================================================
--  Empresas: parámetro "Semanas de cobertura" del MRP (2026-11-10)
-- ----------------------------------------------------------------------------
--  Cada pedido sugerido por el MRP cubre la demanda desde la semana actual hasta
--  su llegada (lead time) + estas semanas después, más el stock de seguridad:
--      Pedido = demanda(hoy .. llegada + X) + SS − (stock + en camino)
--  0 = se pide cada semana lo justo; más semanas = pedidos más grandes y espaciados.
--
--  Ejecutar UNA vez en cada base MySQL de la aplicación (desarrollo, producción...).
--  Mientras una base no tenga la columna, el MRP usa 0 semanas.
-- ============================================================================

ALTER TABLE `empresas`
    ADD COLUMN `mrp_semanas_cobertura` tinyint(3) unsigned NOT NULL DEFAULT 0
        COMMENT 'MRP: semanas que cubre cada pedido sugerido despues de su llegada (ademas del stock de seguridad)'
        AFTER `controla_vencimiento`;

-- Verificación
SELECT nombre, mrp_z_seguridad, controla_vencimiento, mrp_semanas_cobertura FROM empresas ORDER BY posicion;
