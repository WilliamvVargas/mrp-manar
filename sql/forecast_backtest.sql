-- =====================================================================
--  Tabla: forecast_backtest
-- ---------------------------------------------------------------------
--  Resultado de validar el forecast contra semanas ya conocidas: se ocultan
--  las últimas N semanas reales, se pronostican y se comparan. Por grupo
--  (familia, sub-familia) guarda el error y el FACTOR de corrección de sesgo:
--
--    factor = suma_real / suma_forecast   (acotado a [0.25, 4])
--    forecast corregido = forecast * factor
-- =====================================================================

CREATE TABLE `forecast_backtest` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `familia`           varchar(100) DEFAULT NULL,
  `sub_familia`       varchar(100) DEFAULT NULL,
  `metodo`            varchar(20)  DEFAULT NULL, -- prophet / fallback (del backtest)
  `semanas_evaluadas` smallint(4) unsigned DEFAULT NULL,
  `desde`             varchar(10) DEFAULT NULL,  -- primera semana evaluada (lunes yyyy-MM-dd)
  `hasta`             varchar(10) DEFAULT NULL,  -- última semana evaluada (lunes yyyy-MM-dd)
  `suma_real`       decimal(18,4) DEFAULT NULL,
  `suma_forecast`   decimal(18,4) DEFAULT NULL,
  `factor`          decimal(10,6) DEFAULT NULL, -- corrección de sesgo (real/forecast, acotado)
  `bias_pct`        decimal(10,4) DEFAULT NULL, -- (forecast/real - 1)*100  (negativo = subestima)
  `mape`            decimal(10,4) DEFAULT NULL, -- error absoluto porcentual medio (%)
  `created_at`      timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bt_grupo` (`familia`,`sub_familia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
