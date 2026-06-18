-- Migración: tabla `presupuestos` para los registros de la carga masiva.
-- Aplicar sobre la base de datos del proyecto (no afecta otras tablas).
--
-- Campos provenientes de la hoja "base" del Excel de presupuesto, columnas C a M.
-- Todos los campos de datos son NULLABLE: si la celda viene vacía se guarda NULL.
-- Los numéricos son DECIMAL (escala fija) para eliminar el ruido de coma flotante
-- de Excel (p. ej. 298221.65000000002 -> 298221.65).
--
--   anio          -> C (Año)
--   mes           -> D (Mes, 1-12)
--   canal         -> E
--   sub_canal     -> F
--   familia       -> G
--   sub_familia   -> H
--   venta         -> I
--   mg_porcentaje -> J (MG %)  [el Excel lo trae como fracción 0.296; se guarda como 29.60]
--   mg_neto       -> K (MG Neto)
--   pp            -> L
--   kg            -> M

CREATE TABLE IF NOT EXISTS `presupuestos` (
    `id`            int(10) UNSIGNED     NOT NULL AUTO_INCREMENT,
    `anio`          smallint(4) UNSIGNED DEFAULT NULL,
    `mes`           tinyint(2) UNSIGNED  DEFAULT NULL,
    `canal`         varchar(100)         DEFAULT NULL,
    `sub_canal`     varchar(100)         DEFAULT NULL,
    `familia`       varchar(100)         DEFAULT NULL,
    `sub_familia`   varchar(100)         DEFAULT NULL,
    `venta`         decimal(15,2)        DEFAULT NULL,
    `mg_porcentaje` decimal(6,2)         DEFAULT NULL,
    `mg_neto`       decimal(15,2)        DEFAULT NULL,
    `pp`            decimal(15,4)        DEFAULT NULL,
    `kg`            decimal(15,4)        DEFAULT NULL,
    `created_at`    timestamp            NOT NULL DEFAULT current_timestamp(),
    `created_by`    varchar(36)          DEFAULT NULL,
    `updated_at`    timestamp            NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `updated_by`    varchar(36)          DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
