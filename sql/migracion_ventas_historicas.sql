-- Migración: tabla `ventas_historicas` para la carga masiva de venta histórica (.xlsx).
-- Aplicar sobre la base de datos del proyecto (no afecta otras tablas).
--
-- Cada fila es una LÍNEA de un documento de venta (un mismo Nro.Docto. aparece en varias
-- filas, una por artículo). No hay clave única natural: se usa `id` autoincremental.
--
-- Tablas plana que refleja 1:1 las 39 columnas de la hoja "Consolidado Manar". Todos los
-- campos de datos son NULLABLE (celda vacía -> NULL). Los numéricos son DECIMAL (escala
-- fija) para eliminar el ruido de coma flotante de Excel (p. ej. 18.760000000000002 -> 18.76).
--
-- Conversiones a aplicar en la importación:
--   - Las fechas vienen como número serial de Excel (45293 = 2024-01-31) -> DATE.
--   - `mes` viene como texto en español ("enero") -> número 1-12.
--
-- Mapeo columna Excel -> campo:
--   Nro.Docto.->nro_docto   Tipo Docto.->tipo_docto   Nota Venta->nota_venta
--   Cond.Venta->cond_venta  Fecha Docto.->fecha_docto  R.U.T. Cliente->rut_cliente
--   Razón Social Cliente->razon_social  Tipo Cliente->tipo_cliente  Lista de Precio->lista_precio
--   Cód.Ejec.->cod_ejec   Ejec.Comercial->ejec_comercial
--   F.Creación->f_creacion  F.1ra.Venta->f_primera_venta  F.Ult.Venta->f_ultima_venta
--   Cód.Artículo->cod_articulo  Descripción Artículo->descripcion_articulo
--   Grupo Artículo->grupo_articulo  Familia->familia  Sub-Familia->sub_familia  Proveedor->proveedor
--   Cant.Ped.->cant_pedido  Pr.Pedido->pr_pedido  Sub-Total Pedido->subtotal_pedido
--   Cant.Docto.->cant_docto  Pr.Base->pr_base  % Descuento->pct_descuento  Sub-Total Docto.->subtotal_docto
--   Pr.Prom.Pond.->pr_prom_pond  Costo a Pr.P.P.->costo_pr_pp  Ganan.Bruta->ganancia_bruta
--   %Margen->pct_margen  Gramaje->gramaje  Unid. x Caja->unid_x_caja  Sociedad->sociedad
--   Venta Bruta->venta_bruta  Descuento->descuento  Año->anio  Mes->mes  Pr Vta prom->pr_vta_prom
--
-- `version`  -> identificador de versión/lote de la importación, con formato YYYYV000:
--               YYYY = año, V = literal fijo, 000 = correlativo 001..999 (ej. 2024V001).
--               Cada carga marca sus filas con su versión; permite reemplazar/borrar una
--               carga completa sin tocar las demás (ej. DELETE ... WHERE version = '2024V001').
-- created_by / updated_by referencian lógicamente a usuarios.id (UUID).

CREATE TABLE IF NOT EXISTS `ventas_historicas` (
    `id`                   int(10) UNSIGNED     NOT NULL AUTO_INCREMENT,

    -- Documento
    `nro_docto`            int(10) UNSIGNED     DEFAULT NULL,
    `tipo_docto`           smallint(5) UNSIGNED DEFAULT NULL,
    `nota_venta`           varchar(30)          DEFAULT NULL,
    `cond_venta`           varchar(60)          DEFAULT NULL,
    `fecha_docto`          date                 DEFAULT NULL,

    -- Cliente
    `rut_cliente`          varchar(12)          DEFAULT NULL,
    `razon_social`         varchar(150)         DEFAULT NULL,
    `tipo_cliente`         varchar(40)          DEFAULT NULL,
    `lista_precio`         varchar(40)          DEFAULT NULL,

    -- Ejecutivo
    `cod_ejec`             smallint(5) UNSIGNED DEFAULT NULL,
    `ejec_comercial`       varchar(80)          DEFAULT NULL,

    -- Fechas auxiliares
    `f_creacion`           date                 DEFAULT NULL,
    `f_primera_venta`      date                 DEFAULT NULL,
    `f_ultima_venta`       date                 DEFAULT NULL,

    -- Artículo
    `cod_articulo`         varchar(20)          DEFAULT NULL,
    `descripcion_articulo` varchar(150)         DEFAULT NULL,
    `grupo_articulo`       varchar(60)          DEFAULT NULL,
    `familia`              varchar(60)          DEFAULT NULL,
    `sub_familia`          varchar(60)          DEFAULT NULL,
    `proveedor`            varchar(80)          DEFAULT NULL,

    -- Cantidades / precios / montos
    `cant_pedido`          decimal(12,3)        DEFAULT NULL,
    `pr_pedido`            decimal(14,2)        DEFAULT NULL,
    `subtotal_pedido`      decimal(16,2)        DEFAULT NULL,
    `cant_docto`           decimal(12,3)        DEFAULT NULL,
    `pr_base`              decimal(14,2)        DEFAULT NULL,
    `pct_descuento`        decimal(7,2)         DEFAULT NULL,
    `subtotal_docto`       decimal(16,2)        DEFAULT NULL,
    `pr_prom_pond`         decimal(14,2)        DEFAULT NULL,
    `costo_pr_pp`          decimal(16,2)        DEFAULT NULL,
    `ganancia_bruta`       decimal(16,2)        DEFAULT NULL,
    `pct_margen`           decimal(7,2)         DEFAULT NULL,
    `gramaje`              decimal(10,3)        DEFAULT NULL,
    `unid_x_caja`          smallint(5) UNSIGNED DEFAULT NULL,
    `sociedad`             varchar(40)          DEFAULT NULL,
    `venta_bruta`          decimal(16,2)        DEFAULT NULL,
    `descuento`            decimal(16,2)        DEFAULT NULL,
    `anio`                 smallint(4) UNSIGNED DEFAULT NULL,
    `mes`                  tinyint(2) UNSIGNED  DEFAULT NULL,
    `pr_vta_prom`          decimal(14,2)        DEFAULT NULL,

    -- Control de carga / auditoría
    `version`              varchar(8)           DEFAULT NULL,   -- formato YYYYV000 (ej. 2024V001)
    `created_at`           timestamp            NOT NULL DEFAULT current_timestamp(),
    `created_by`           varchar(36)          DEFAULT NULL,
    `updated_at`           timestamp            NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `updated_by`           varchar(36)          DEFAULT NULL,

    PRIMARY KEY (`id`),
    KEY `idx_vh_fecha`    (`fecha_docto`),
    KEY `idx_vh_periodo`  (`anio`, `mes`),
    KEY `idx_vh_cliente`  (`rut_cliente`),
    KEY `idx_vh_articulo` (`cod_articulo`),
    KEY `idx_vh_docto`    (`nro_docto`),
    KEY `idx_vh_version`  (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
