-- =====================================================================
--  Tabla: presupuesto_x_producto  (GRANO MENSUAL)
-- ---------------------------------------------------------------------
--  Presupuesto ($ neto) desagregado por PRODUCTO y MES. El presupuesto se
--  carga por familia/mes (tabla `presupuestos`); aquí se reparte a cada producto
--  según su participación reciente ponderada (alfa / ventana_meses), y se estiman
--  la venta y la cantidad. Guarda también la cantidad real y su diferencia para
--  el seguimiento presupuesto vs real.
--  Grano: una fila por (anio, mes, producto_codigo).
-- =====================================================================

CREATE TABLE `presupuesto_x_producto` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,

  -- Período
  `anio` smallint(4) unsigned NOT NULL,
  `mes`  tinyint(2)  unsigned NOT NULL,

  -- Grupo y producto
  `familia`         varchar(100) DEFAULT NULL,
  `sub_familia`     varchar(100) DEFAULT NULL,
  `producto_codigo` varchar(50)  NOT NULL,
  `producto_nombre` varchar(200) DEFAULT NULL,

  -- Presupuesto y reparto a producto
  `presupuesto_neto`       decimal(15,2) NOT NULL DEFAULT 0.00, -- presupuesto $ del grupo en el mes
  `neto_pond_producto`     decimal(15,2) DEFAULT NULL,          -- neto ponderado del producto
  `neto_pond_grupo`        decimal(15,2) DEFAULT NULL,          -- neto ponderado del grupo
  `cantidad_pond_producto` decimal(15,4) DEFAULT NULL,          -- cantidad ponderada del producto
  `participacion`          decimal(9,8)  DEFAULT NULL,          -- participación del producto en el grupo (0..1)
  `precio_unitario`        decimal(15,4) DEFAULT NULL,          -- precio unitario estimado
  `factor_cumplimiento`    decimal(10,6) DEFAULT NULL,          -- factor de cumplimiento aplicado

  -- Estimaciones y seguimiento vs real
  `venta_estimada`    decimal(15,2) DEFAULT NULL,               -- $ estimado del producto
  `cantidad_estimada` decimal(15,4) NOT NULL DEFAULT 0.0000,    -- unidades estimadas del producto
  `cantidad_real`     decimal(15,4) DEFAULT NULL,               -- unidades reales vendidas
  `diferencia`        decimal(10,4) DEFAULT NULL,               -- real vs estimado

  -- Parámetros del cálculo de participación (ponderación exponencial)
  `alfa`          decimal(4,3) unsigned NOT NULL DEFAULT 0.850, -- decaimiento del ponderado
  `ventana_meses` tinyint(2)  unsigned NOT NULL DEFAULT 12,     -- meses hacia atrás considerados

  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(36) DEFAULT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pxp_periodo_producto` (`anio`,`mes`,`producto_codigo`),
  KEY `idx_pxp_periodo`  (`anio`,`mes`),
  KEY `idx_pxp_grupo`    (`familia`,`sub_familia`),
  KEY `idx_pxp_producto` (`producto_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
