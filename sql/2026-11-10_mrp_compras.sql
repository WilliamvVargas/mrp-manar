-- ============================================================================
--  MRP: compras registradas desde el Pronóstico de Compra (2026-11-10)
-- ----------------------------------------------------------------------------
--  El botón "Comprar" de la ficha del producto registra la compra en la semana
--  actual. Queda PENDIENTE hasta que el comprador la marca "OC emitida" (con el
--  N° de OC opcional) o la anula. Mientras está pendiente, el MRP la cuenta como
--  pedido en camino que llega en hoy + lead time: se descuenta del sugerido y no
--  se sugiere otro pedido hasta que llegue.
--
--  Ejecutar UNA vez en cada base MySQL de la aplicación (desarrollo, producción...).
--  Mientras una base no tenga la tabla, el MRP funciona sin compras registradas.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `mrp_compras` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` varchar(36) NOT NULL,
  `producto_codigo` varchar(50) NOT NULL,
  `producto_nombre` varchar(200) DEFAULT NULL,
  `proveedor` varchar(200) DEFAULT NULL,
  `cantidad` int(10) unsigned NOT NULL COMMENT 'Cantidad comprada (la que cuenta en el MRP mientras esta pendiente)',
  `cantidad_sugerida` int(10) unsigned DEFAULT NULL COMMENT 'Sugerido del MRP al momento de comprar (referencia)',
  `lead_time_sem` smallint(5) unsigned DEFAULT NULL COMMENT 'Lead time (semanas) usado al comprar (referencia)',
  `observacion` varchar(255) DEFAULT NULL,
  `estado` enum('pendiente','oc_emitida','anulada') NOT NULL DEFAULT 'pendiente' COMMENT 'pendiente: cuenta en el MRP como pedido en camino, oc_emitida/anulada: cerrada',
  `numero_oc` varchar(30) DEFAULT NULL COMMENT 'N de OC de SAP (opcional) al marcar OC emitida',
  `fecha_cierre` datetime DEFAULT NULL,
  `cerrado_por` varchar(36) DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mrpc_emp_estado_prod` (`empresa_id`,`estado`,`producto_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Verificación
SHOW COLUMNS FROM `mrp_compras`;
