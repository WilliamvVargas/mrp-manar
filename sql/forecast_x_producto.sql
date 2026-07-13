-- =====================================================================
--  Tabla: forecast_x_producto
-- ---------------------------------------------------------------------
--  Pronóstico de demanda (unidades) por producto para los próximos 12 meses.
--  Enfoque TOP-DOWN (Solución 1):
--    1) Prophet pronostica la demanda de cada grupo (familia, sub-familia)
--       usando el PRESUPUESTO como regresor externo.
--    2) Ese pronóstico del grupo se reparte a los productos según su
--       participación reciente (últimos 12 meses, ponderada).
--    demanda_forecast(producto, mes) = forecast_grupo(mes) * participacion
--
--  Grano: una fila por (anio, mes, producto_codigo).
-- =====================================================================

CREATE TABLE `forecast_x_producto` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,

  -- Período pronosticado
  `anio` smallint(4) unsigned NOT NULL,
  `mes`  tinyint(2) unsigned NOT NULL,

  -- Grupo y producto
  `familia`         varchar(100) DEFAULT NULL,
  `sub_familia`     varchar(100) DEFAULT NULL,
  `producto_codigo` varchar(50)  NOT NULL,
  `producto_nombre` varchar(200) DEFAULT NULL,

  -- Resultado
  `demanda_forecast` decimal(15,4) NOT NULL DEFAULT 0.0000, -- unidades estimadas del producto

  -- Trazabilidad del pronóstico del grupo (Prophet)
  `forecast_grupo`     decimal(18,4) DEFAULT NULL, -- yhat del grupo (unidades)
  `forecast_grupo_min` decimal(18,4) DEFAULT NULL, -- yhat_lower (banda)
  `forecast_grupo_max` decimal(18,4) DEFAULT NULL, -- yhat_upper (banda)
  `participacion`      decimal(9,8)  DEFAULT NULL, -- participación del producto en el grupo (0..1)
  `metodo`             varchar(20)   DEFAULT NULL, -- 'prophet' o 'fallback'

  -- Presupuesto ($). El presupuesto se define a nivel de grupo (familia, sub-familia);
  -- la venta presupuestada del producto se deriva repartiéndolo por la participación.
  -- NULL cuando el grupo no tiene presupuesto cargado ese mes.
  `presupuesto_grupo`   decimal(15,2) DEFAULT NULL, -- presupuesto $ del grupo en el mes (trazabilidad)
  `venta_presupuestada` decimal(15,2) DEFAULT NULL, -- $ del producto = participacion × presupuesto_grupo

  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fxp_periodo_producto` (`anio`,`mes`,`producto_codigo`),
  KEY `idx_fxp_periodo`  (`anio`,`mes`),
  KEY `idx_fxp_grupo`    (`familia`,`sub_familia`),
  KEY `idx_fxp_producto` (`producto_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
