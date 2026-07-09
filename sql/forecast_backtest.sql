-- =====================================================================
--  Tabla: forecast_backtest
-- ---------------------------------------------------------------------
--  Resultado de validar el forecast contra meses ya conocidos: se ocultan
--  los últimos N meses reales, se pronostican y se comparan. Por grupo
--  (familia, sub-familia) guarda el error y el FACTOR de corrección de sesgo:
--
--    factor = suma_real / suma_forecast   (acotado a [0.25, 4])
--    forecast corregido = forecast * factor
-- =====================================================================

CREATE TABLE `forecast_backtest` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `familia`         varchar(100) DEFAULT NULL,
  `sub_familia`     varchar(100) DEFAULT NULL,
  `metodo`          varchar(20)  DEFAULT NULL, -- prophet / fallback (del backtest)
  `meses_evaluados` tinyint(2) unsigned DEFAULT NULL,
  `desde`           varchar(7) DEFAULT NULL,   -- primer mes evaluado (yyyy-MM)
  `hasta`           varchar(7) DEFAULT NULL,   -- último mes evaluado (yyyy-MM)
  `suma_real`       decimal(18,4) DEFAULT NULL,
  `suma_forecast`   decimal(18,4) DEFAULT NULL,
  `factor`          decimal(10,6) DEFAULT NULL, -- corrección de sesgo (real/forecast, acotado)
  `bias_pct`        decimal(10,4) DEFAULT NULL, -- (forecast/real - 1)*100  (negativo = subestima)
  `mape`            decimal(10,4) DEFAULT NULL, -- error absoluto porcentual medio (%)
  `created_at`      timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bt_grupo` (`familia`,`sub_familia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
