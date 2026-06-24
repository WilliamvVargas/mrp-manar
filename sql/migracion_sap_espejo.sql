-- Migración: tablas "espejo" de SAP en MySQL + log de control de sincronización.
-- Aplicar sobre la base de datos del proyecto (no afecta otras tablas).
--
-- Estrategia: la base SAP vive en SQL Server. En vez de cruzar servidores en cada
-- consulta, replicamos a MySQL (con prefijo `sap_`) sólo las columnas que usamos, y
-- antes de cada consulta el helper `SapSync` (models/sap_sync_model.php) refresca el
-- espejo SÓLO si está vencido (ver `sap_sync_log`). Así toda consulta queda 100% MySQL
-- (joins, group by, subconsultas, order by) sobre datos razonablemente frescos.
--
-- El llenado es por RECARGA COMPLETA (DELETE + INSERT por lotes en transacción): cada
-- refresco reemplaza todo el contenido del espejo con lo que tiene SAP en ese momento.


-- --------------------------------------------------------------------------------------
-- Log de control: una fila por tabla espejo, con cuándo se pobló por última vez.
-- `SapSync` lo consulta para decidir si refrescar (TTL) y lo actualiza tras cada recarga.
-- --------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sap_sync_log` (
    `tabla`       varchar(64)      NOT NULL,                 -- nombre de la tabla espejo (ej. 'sap_productos_maestros')
    `ultima_sync` datetime         NOT NULL,                 -- momento del último refresco (NOW() de MySQL)
    `filas`       int(10) UNSIGNED NOT NULL DEFAULT 0,       -- filas cargadas en el último refresco (informativo/diagnóstico)
    PRIMARY KEY (`tabla`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------------------------------------
-- Espejo del maestro de artículos de SAP (tabla OITM).
-- Mapeo SAP -> espejo:  ItemCode -> producto_codigo,  ItemName -> producto_nombre,
--                       U_Familia -> producto_familia_codigo,  validFor -> producto_activo.
-- Tamaños tomados de SAP: ItemCode nvarchar(50), ItemName nvarchar(200),
--                         U_Familia nvarchar(10), validFor char(1).
-- `producto_familia_codigo` es el código de la Familia del negocio (UDF U_Familia) y
-- vincula a sap_familias.familia_codigo. OJO: es texto con ceros a la izquierda ("010"),
-- por eso varchar y no numérico.
-- --------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sap_productos_maestros` (
    `producto_codigo`         varchar(50)  NOT NULL,         -- OITM.ItemCode
    `producto_nombre`         varchar(200) DEFAULT NULL,     -- OITM.ItemName
    `producto_familia_codigo` varchar(10)  DEFAULT NULL,     -- OITM.U_Familia -> sap_familias.familia_codigo
    `producto_activo`         char(1)      DEFAULT NULL,     -- OITM.validFor ('Y' activo, 'N' inactivo)
    PRIMARY KEY (`producto_codigo`),
    KEY `idx_spm_familia` (`producto_familia_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------------------------------------
-- Catálogo de Familias del negocio: valores fijos del UDF U_Familia de OITM (FieldID 8),
-- almacenados en la tabla de sistema UFD1 de SAP.
-- Mapeo SAP -> espejo:  UFD1.FldValue -> familia_codigo,  UFD1.Descr -> familia_nombre.
-- El presupuesto cruza por NOMBRE (familia_nombre). familia_codigo es texto con ceros a
-- la izquierda ("010"), por eso varchar y no numérico.
-- --------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sap_familias` (
    `familia_codigo` varchar(10)  NOT NULL,                  -- UFD1.FldValue (código U_Familia)
    `familia_nombre` varchar(100) DEFAULT NULL,              -- UFD1.Descr (nombre de la familia)
    PRIMARY KEY (`familia_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
