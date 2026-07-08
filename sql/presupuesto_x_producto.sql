-- =====================================================================
--  Tabla: presupuesto_x_producto
-- ---------------------------------------------------------------------
--  Guarda la estimación de demanda POR PRODUCTO a partir de:
--    - el presupuesto ($ venta neta) a nivel grupo (familia, sub-familia)
--    - las ventas reales por producto (V3 SAP: cantidad + neto por año-mes)
--
--  Método (participación ponderada, ventana MÓVIL 12 meses, pesos exponenciales):
--    peso del mes:      w = alfa^k     (k = meses hacia atrás; 0 = el más reciente)
--    tasa del producto: neto_pond_producto = Σw·neto_p / Σw   (media por mes ACTIVO,
--                       no la suma: así un producto nuevo no queda diluido)
--    participación $:   participacion  = neto_pond_producto / neto_pond_grupo
--    precio unitario:   precio_unitario = neto_pond_producto / cantidad_pond_producto
--    factor cumplim.:   factor_cumplimiento = Σw·venta_real_grupo / Σw·presupuesto_grupo (12m)
--    venta estimada $:  venta_estimada  = presupuesto_neto * factor_cumplimiento * participacion
--    cantidad (unid.):  cantidad_estimada = venta_estimada / precio_unitario
--
--  Grano: una fila por (anio, mes, producto_codigo).
--  El presupuesto del grupo se agrega (SUMA) sobre canal/sub_canal hasta el
--  nivel (familia, sub_familia).
--  La unión con el presupuesto es por NOMBRE de familia + sub_familia.
-- =====================================================================

CREATE TABLE `presupuesto_x_producto` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,

  -- Período estimado (coincide con el mes del presupuesto)
  `anio` smallint(4) unsigned NOT NULL,
  `mes`  tinyint(2) unsigned NOT NULL,

  -- Grupo: clave compuesta familia + sub-familia (una sub-familia puede
  -- existir en más de una familia, por eso van juntas).
  `familia`     varchar(100) DEFAULT NULL,
  `sub_familia` varchar(100) DEFAULT NULL,

  -- Producto (código = ItemCode SAP)
  `producto_codigo` varchar(50)  NOT NULL,
  `producto_nombre` varchar(200) DEFAULT NULL,

  -- Presupuesto $ del grupo en ese mes  = presupuesto_neto(g, mes)
  `presupuesto_neto` decimal(15,2) NOT NULL DEFAULT 0.00,

  -- Insumos ponderados (12 meses, alfa) -- se guardan para poder auditar el cálculo.
  -- Son TASAS REALES medias por mes activo (Σw·x / Σw), no sumas acumuladas.
  `neto_pond_producto`     decimal(15,2) DEFAULT NULL, -- neto real medio/mes activo del producto
  `neto_pond_grupo`        decimal(15,2) DEFAULT NULL, -- Σ neto real medio de los vendidos ese mes
  `cantidad_pond_producto` decimal(15,4) DEFAULT NULL, -- cantidad real media/mes activo del producto

  -- Valores calculados
  `participacion`       decimal(9,8)  DEFAULT NULL, -- part_$(p) en 0..1, DESESTACIONALIZADA y renormalizada entre vendidos
  `precio_unitario`     decimal(15,4) DEFAULT NULL, -- $ por unidad (histórico ponderado)
  `factor_cumplimiento` decimal(10,6) DEFAULT NULL, -- venta_real/presupuesto del grupo (ventana 12m)
  `venta_estimada`      decimal(15,2) DEFAULT NULL, -- $ estimado del producto (ajustado por el factor)
  `cantidad_estimada`   decimal(15,4) NOT NULL DEFAULT 0.0000, -- RESULTADO: unidades
  `cantidad_real`       decimal(15,4) DEFAULT NULL, -- cantidad real vendida ese mes (V3), para comparar
  `diferencia`          decimal(10,4) DEFAULT NULL, -- (cantidad_estimada - cantidad_real) / cantidad_real * 100  (%)

  -- Parámetros usados (reproducibilidad / auditoría)
  `alfa`          decimal(4,3) unsigned NOT NULL DEFAULT 0.850,
  `ventana_meses` tinyint(2)   unsigned NOT NULL DEFAULT 12,

  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(36) DEFAULT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pxp_periodo_producto` (`anio`,`mes`,`producto_codigo`),
  KEY `idx_pxp_periodo`  (`anio`,`mes`),
  KEY `idx_pxp_grupo`    (`familia`,`sub_familia`),
  KEY `idx_pxp_producto` (`producto_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
