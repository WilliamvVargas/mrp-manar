-- =====================================================================
--  Tabla: forecast_x_producto  (GRANO SEMANAL - semana ISO)
-- ---------------------------------------------------------------------
--  Pronóstico de demanda (unidades) por producto para las próximas 52 semanas.
--  Enfoque TOP-DOWN:
--    1) Prophet pronostica la demanda de cada grupo (familia, sub-familia) por
--       SEMANA, usando el PRESUPUESTO semanal como regresor SOLO si el grupo tiene
--       presupuesto en el horizonte (si no, se pronostica sin él y usa_presupuesto=0).
--    2) Ese pronóstico del grupo se reparte a los productos según su participación
--       reciente (últimas 52 semanas, ponderada).
--    demanda_forecast(producto, semana) = forecast_grupo(semana) * participacion
--
--  La semana se identifica por su LUNES ISO (semana_inicio) + iso_year/iso_week.
--  Grano: una fila por (iso_year, iso_week, producto_codigo).
-- =====================================================================

CREATE TABLE `forecast_x_producto` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,

  -- Período pronosticado (semana ISO)
  `iso_year`      smallint(4) unsigned NOT NULL,   -- año ISO (format 'o')
  `iso_week`      tinyint(2)  unsigned NOT NULL,   -- semana ISO 1..53 (format 'W')
  `semana_inicio` date NOT NULL,                   -- lunes ISO de la semana

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

  -- Ajuste/corrección posterior del pronóstico (opcionales, nullable; se llenan si se aplica).
  `factor`                decimal(10,6) DEFAULT NULL, -- factor de corrección aplicado a la demanda
  `demanda_forecast_corr` decimal(15,4) DEFAULT NULL, -- demanda_forecast corregida por el factor

  -- Presupuesto ($) por semana (grupo × participación) + flag de uso como regresor.
  -- usa_presupuesto = 1 si el grupo se pronosticó CON el presupuesto como regresor;
  -- 0 si el grupo no tiene presupuesto cargado (se pronostica igual, pero sin él).
  `presupuesto_grupo`   decimal(15,2) DEFAULT NULL, -- presupuesto $ del grupo en la semana (trazabilidad)
  `venta_presupuestada` decimal(15,2) DEFAULT NULL, -- $ del producto = participacion × presupuesto_grupo
  `usa_presupuesto`     tinyint(1) NOT NULL DEFAULT 0,

  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fxp_semana_producto` (`iso_year`,`iso_week`,`producto_codigo`),
  KEY `idx_fxp_semana`   (`semana_inicio`),
  KEY `idx_fxp_grupo`    (`familia`,`sub_familia`),
  KEY `idx_fxp_producto` (`producto_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
