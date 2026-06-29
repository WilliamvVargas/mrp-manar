-- Vacía la tabla `iconos` y reinicia su AUTO_INCREMENT a 1.
--
-- No se usa TRUNCATE: `iconos` está referenciada por la FK `fk_item_menus_icono`
-- (item_menus.icono_id -> iconos.id) y TRUNCATE falla con el error #1701 en ese caso.
-- El truco de `SET FOREIGN_KEY_CHECKS = 0` solo sirve dentro de la MISMA sesión/conexión, así que
-- en phpMyAdmin (donde el SET y el TRUNCATE pueden quedar en sesiones distintas) igual falla.
--
-- DELETE + ALTER logran el mismo resultado (vaciar + reiniciar contador) sin esa restricción y
-- funcionan en cualquier herramienta (phpMyAdmin, consola, etc.).
--
-- ¡ADVERTENCIA! Ejecutar solo cuando NINGÚN item_menus referencie un icono.
-- Verificación previa:  SELECT COUNT(*) FROM item_menus WHERE icono_id IS NOT NULL;  -- debe dar 0
-- Si diera > 0, el DELETE fallará por la FK: primero limpiar/ajustar item_menus.icono_id.

DELETE FROM `iconos`;
ALTER TABLE `iconos` AUTO_INCREMENT = 1;
