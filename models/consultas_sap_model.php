<?php

    /*
     * Modelo de acceso a datos de las Consultas SAP.
     *
     * Lee de la base de datos de SAP Business One (SQL Server) en modo SOLO LECTURA.
     * Se instancia con la conexión PDO a SQL Server ($pdoSqlsrv de config/conexion_sqlserver.php).
     *
     * Cada método corresponde a una consulta del mantenedor (ODV, OC, Stock, ...).
     */
    class ConsultaSap
    {
        /** @var PDO */
        private $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        /**
         * Columnas de la consulta de Parámetros para MRP, compartidas por
         * parametrosMrpProducto() (un producto) y parametrosMrp() (todos). Incluye:
         *  - Parámetros estándar SAP: maestro (OITM) y stock por bodega (OITW).
         *  - En Pedido (compras, OC pendiente) y En Producción (órdenes de producción liberadas)
         *    se calculan por subconsulta a bodega 010; antes iban juntos en OITW.OnOrder.
         *  - Comprometido Ventas (OV abiertas) y Comprometido Producción (componentes que
         *    consumirán las órdenes de producción) también por subconsulta a bodega 010;
         *    antes iban juntos en OITW.IsCommited.
         *  - Campos de negocio (UDF de OITM). Los codificados (Origen/Marca Propia/E-Commerce)
         *    se resuelven a su descripción vía UFD1. El proveedor del negocio (U_NX_Proveedor)
         *    guarda un código que se resuelve a su nombre en la tabla de usuario @PROVEEDORES
         *    (alias PV). El lead time del negocio (U_LeadTime, "Negocio") es el que se usa; el
         *    estándar OITM.LeadTime NO se incluye (está vacío para todos los productos).
         * Depende de los alias de tabla T0=OITM, T1=OITW y los joins UF/US/UO/UMP/UEC/PV.
         */
        const COLUMNAS_PARAMETROS_MRP = "
                    T0.ItemCode,
                    T0.ItemName,
                    UF.Descr AS Familia,
                    US.Descr AS SubFamilia,
                    T0.MinOrdrQty,
                    T0.OrdrMulti,
                    T1.MinStock,
                    T1.MaxStock,
                    T1.MinOrder,
                    T1.OnHand,

                    -- Comprometido Ventas = pendiente de despacho de órdenes de venta abiertas, bodega 010.
                    ISNULL((
                        SELECT SUM(r.OpenQty)
                        FROM RDR1 r INNER JOIN ORDR o ON o.DocEntry = r.DocEntry
                        WHERE o.CANCELED = 'N' AND r.LineStatus = 'O' AND r.OpenQty > 0
                          AND r.WhsCode = '010' AND r.ItemCode = T0.ItemCode
                    ), 0) AS CompVentas,

                    -- Comprometido Producción = componentes pendientes de consumir por órdenes de producción liberadas, bodega 010.
                    ISNULL((
                        SELECT SUM(c.PlannedQty - c.IssuedQty)
                        FROM WOR1 c INNER JOIN OWOR w ON w.DocEntry = c.DocEntry
                        WHERE w.Status = 'R' AND (c.PlannedQty - c.IssuedQty) > 0
                          AND c.Warehouse = '010' AND c.ItemCode = T0.ItemCode
                    ), 0) AS CompProduccion,

                    -- En Pedido = pendiente de recepción de OC (compras) en bodega 010.
                    ISNULL((
                        SELECT SUM(p.OpenQty)
                        FROM POR1 p INNER JOIN OPOR o ON o.DocEntry = p.DocEntry
                        WHERE o.CANCELED = 'N' AND p.LineStatus = 'O' AND p.OpenQty > 0
                          AND p.WhsCode = '010' AND p.ItemCode = T0.ItemCode
                    ), 0) AS EnPedido,

                    -- En Producción = pendiente de órdenes de producción LIBERADAS (Status 'R') a bodega 010.
                    ISNULL((
                        SELECT SUM(w.PlannedQty - w.CmpltQty)
                        FROM OWOR w
                        WHERE w.Status = 'R' AND (w.PlannedQty - w.CmpltQty) > 0
                          AND w.Warehouse = '010' AND w.ItemCode = T0.ItemCode
                    ), 0) AS EnProduccion,

                    T0.U_Sta_Art      AS StatusArticulo,
                    UO.Descr          AS Origen,
                    UMP.Descr         AS MarcaPropia,
                    T0.U_Art_Nuevo    AS ArticuloNuevo,
                    UEC.Descr         AS ECommerce,
                    T0.U_Campana      AS Campana,
                    T0.U_NX_Gramaje   AS Gramaje,
                    T0.U_NX_UnidCaja  AS UnidCaja,
                    T0.U_UnidEmbProv  AS UnidEmbProv,
                    T0.U_Kilos        AS Kilos,
                    T0.U_Currency     AS Moneda,
                    T0.U_NX_Proveedor AS ProveedorNegocio,
                    PV.Name           AS ProveedorNombre,
                    T0.U_LeadTime     AS LeadTimeNegocio
        ";

        /**
         * Órdenes de Venta (ODV) con líneas abiertas y su entrega relacionada (si existe).
         * Solo documentos no anulados, líneas abiertas y con cantidad pendiente de despacho.
         *
         * @return array Lista de filas (arreglos asociativos).
         */
        public function ordenesVenta()
        {
            $sql = "
                SELECT
                    ORDR.DocNum        AS OrdenVenta,
                    ORDR.DocDate       AS FechaOV,
                    ORDR.CardCode      AS CodCliente,
                    ORDR.CardName      AS Cliente,

                    RDR1.LineNum       AS LineaOV,
                    RDR1.ItemCode      AS CodArticulo,
                    RDR1.Dscription    AS Articulo,
                    RDR1.WhsCode       AS Almacen,

                    RDR1.Quantity      AS CantidadOrdenada,
                    RDR1.OpenQty       AS CantidadPendienteDespacho,

                    ODLN.DocNum        AS EntregaRelacionada,
                    DLN1.Quantity      AS CantidadDespachadaRelacionada,

                    RDR1.DocEntry      AS OV_DocEntry,
                    RDR1.LineNum       AS OV_LineNum,
                    DLN1.DocEntry      AS Entrega_DocEntry,
                    DLN1.LineNum       AS Entrega_LineNum

                FROM ORDR
                INNER JOIN RDR1
                    ON ORDR.DocEntry = RDR1.DocEntry

                LEFT JOIN DLN1
                    ON DLN1.BaseType  = 17
                   AND DLN1.BaseEntry = RDR1.DocEntry
                   AND DLN1.BaseLine  = RDR1.LineNum

                LEFT JOIN ODLN
                    ON ODLN.DocEntry = DLN1.DocEntry

                WHERE
                    ORDR.CANCELED = 'N'
                    AND RDR1.LineStatus = 'O'
                    AND RDR1.OpenQty > 0

                ORDER BY
                    ORDR.DocNum,
                    RDR1.LineNum
            ";

            return $this->pdo->query($sql)->fetchAll();
        }

        /**
         * Órdenes de Compra (OC) con líneas abiertas y su entrada de mercancía
         * relacionada (si existe). Solo documentos no anulados, líneas abiertas y
         * con cantidad pendiente de recepción.
         *
         * @return array Lista de filas (arreglos asociativos).
         */
        public function ordenesCompra()
        {
            $sql = "
                SELECT
                    OPOR.DocNum        AS OrdenCompra,
                    OPOR.DocDate       AS FechaOC,
                    OPOR.CardCode      AS CodProveedor,
                    OPOR.CardName      AS Proveedor,

                    POR1.LineNum       AS LineaOC,
                    POR1.ItemCode      AS CodArticulo,
                    POR1.Dscription    AS Articulo,
                    POR1.WhsCode       AS Almacen,

                    POR1.Quantity      AS CantidadOrdenada,
                    POR1.OpenQty       AS CantidadPendienteRecepcion,

                    OPDN.DocNum        AS EntradaMercanciaRelacionada,
                    PDN1.Quantity      AS CantidadRecibidaRelacionada,

                    POR1.DocEntry      AS OC_DocEntry,
                    POR1.LineNum       AS OC_LineNum,
                    PDN1.DocEntry      AS Entrada_DocEntry,
                    PDN1.LineNum       AS Entrada_LineNum

                FROM OPOR
                INNER JOIN POR1
                    ON OPOR.DocEntry = POR1.DocEntry

                LEFT JOIN PDN1
                    ON PDN1.BaseType  = 22
                   AND PDN1.BaseEntry = POR1.DocEntry
                   AND PDN1.BaseLine  = POR1.LineNum

                LEFT JOIN OPDN
                    ON OPDN.DocEntry = PDN1.DocEntry

                WHERE
                    OPOR.CANCELED = 'N'
                    AND POR1.LineStatus = 'O'
                    AND POR1.OpenQty > 0

                ORDER BY
                    OPOR.DocNum,
                    POR1.LineNum
            ";

            return $this->pdo->query($sql)->fetchAll();
        }

        /**
         * Facturas de venta (OINV) y Notas de Crédito de cliente (ORIN) resumidas a
         * nivel de cabecera. Las notas de crédito quedan en negativo (Neto/Impuesto/Total)
         * para que un SUM entregue directamente la venta neta. Solo documentos no anulados.
         *
         * Filtro opcional por fecha del documento (DocDate). Se acepta solo formato
         * 'YYYY-MM-DD'; cualquier otro valor se ignora (equivale a sin filtro).
         *
         * @param  string $desde Fecha documento desde (inclusive), '' = sin límite inferior.
         * @param  string $hasta Fecha documento hasta (inclusive), '' = sin límite superior.
         * @return array Lista de filas (arreglos asociativos).
         */
        public function facturasNotasCredito($desde = '', $hasta = '')
        {
            // Solo se aceptan fechas 'YYYY-MM-DD'; el resto se descarta.
            $desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) ? $desde : '';
            $hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta) ? $hasta : '';

            // El filtro se arma una vez y se aplica a ambos SELECT del UNION (mismos alias T0).
            $filtroFecha = '';
            $params      = [];

            if ($desde !== '') {
                $filtroFecha .= " AND T0.DocDate >= ?";
                $params[]     = $desde;
            }
            if ($hasta !== '') {
                $filtroFecha .= " AND T0.DocDate <= ?";
                $params[]     = $hasta;
            }

            $sql = "
                SELECT
                    T0.DocEntry                AS DocEntry,
                    'Factura'                  AS TipoDoc,
                    T0.DocNum                  AS NumDoc,
                    T0.DocDate                 AS FechaDocumento,
                    T0.CardCode                AS CodCliente,
                    T0.CardName                AS Cliente,
                    (T0.DocTotal - T0.VatSum)  AS Neto,
                    T0.VatSum                  AS Impuesto,
                    T0.DocTotal                AS Total,
                    CASE T0.DocStatus
                        WHEN 'O' THEN 'Abierta'
                        WHEN 'C' THEN 'Cerrada'
                        ELSE T0.DocStatus
                    END                        AS Estado
                FROM OINV T0
                WHERE T0.CANCELED = 'N' $filtroFecha

                UNION ALL

                SELECT
                    T0.DocEntry,
                    'Nota de Crédito',
                    T0.DocNum,
                    T0.DocDate,
                    T0.CardCode,
                    T0.CardName,
                    -(T0.DocTotal - T0.VatSum),
                    -T0.VatSum,
                    -T0.DocTotal,
                    CASE T0.DocStatus
                        WHEN 'O' THEN 'Abierta'
                        WHEN 'C' THEN 'Cerrada'
                        ELSE T0.DocStatus
                    END
                FROM ORIN T0
                WHERE T0.CANCELED = 'N' $filtroFecha

                ORDER BY FechaDocumento, TipoDoc, NumDoc
            ";

            // El UNION repite el filtro, así que los parámetros van dos veces (uno por SELECT).
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($params, $params));

            return $stmt->fetchAll();
        }

        /**
         * Líneas de una factura de venta (INV1) o nota de crédito de cliente (RIN1),
         * identificadas por su DocEntry. El tipo determina la tabla mediante whitelist.
         *
         * @param  string $tipo     'Nota de Crédito' usa RIN1; cualquier otro valor usa INV1.
         * @param  int    $docEntry DocEntry (clave interna) del documento de cabecera.
         * @return array Lista de filas (arreglos asociativos).
         */
        public function lineasDocumento($tipo, $docEntry)
        {
            // La tabla se elige por whitelist (no se interpola texto libre del cliente).
            $tabla    = ($tipo === 'Nota de Crédito') ? 'RIN1' : 'INV1';
            $docEntry = (int) $docEntry;

            $sql = "
                SELECT
                    T1.LineNum      AS Linea,
                    T1.ItemCode     AS CodArticulo,
                    T1.Dscription   AS Articulo,
                    T1.unitMsr      AS Unidad,
                    T1.WhsCode      AS Bodega,
                    T1.Quantity     AS Cantidad,
                    T1.PriceBefDi   AS PrecioSinDesc,
                    T1.Price        AS PrecioUnitario,
                    T1.DiscPrcnt    AS PctDescuento,
                    T1.LineTotal    AS TotalNeto,
                    T1.VatPrcnt     AS PctIVA,
                    T1.VatSum       AS IvaMonto,
                    T1.GTotal       AS TotalBruto
                FROM $tabla T1
                WHERE T1.DocEntry = ?
                ORDER BY T1.LineNum
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$docEntry]);

            return $stmt->fetchAll();
        }

        /**
         * Consulta v2: TODAS las líneas de facturas (INV1) y notas de crédito (RIN1)
         * en un solo listado plano, con datos del documento para identificar cada línea.
         * Mismo filtro opcional por fecha del documento (DocDate) que la consulta de cabeceras.
         * Los montos van tal cual (positivos); las sumas del pie son aditivas, no neteadas.
         *
         * @param  string $desde Fecha documento desde (inclusive), '' = sin límite.
         * @param  string $hasta Fecha documento hasta (inclusive), '' = sin límite.
         * @return array Lista de filas (arreglos asociativos).
         */
        public function lineasFacturasNotasCredito($desde = '', $hasta = '')
        {
            $desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) ? $desde : '';
            $hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta) ? $hasta : '';

            $filtroFecha = '';
            $params      = [];

            if ($desde !== '') {
                $filtroFecha .= " AND T0.DocDate >= ?";
                $params[]     = $desde;
            }
            if ($hasta !== '') {
                $filtroFecha .= " AND T0.DocDate <= ?";
                $params[]     = $hasta;
            }

            $sql = "
                SELECT
                    T0.DocEntry     AS DocEntry,
                    'Factura'       AS TipoDoc,
                    T0.DocNum       AS NumDoc,
                    T0.DocDate      AS FechaDocumento,
                    T0.CardName     AS Cliente,
                    T1.LineNum      AS Linea,
                    T1.ItemCode     AS CodArticulo,
                    T1.Dscription   AS Articulo,
                    UF.Descr        AS Familia,
                    US.Descr        AS SubFamilia,
                    T1.unitMsr      AS Unidad,
                    T1.Quantity     AS Cantidad,
                    T1.PriceBefDi   AS PrecioSinDesc,
                    T1.DiscPrcnt    AS PctDescuento,
                    T1.Price        AS PrecioUnitario,
                    T1.LineTotal    AS TotalNeto,
                    T1.VatPrcnt     AS PctIVA,
                    T1.VatSum       AS IvaMonto,
                    T1.GTotal       AS TotalBruto
                FROM OINV T0
                INNER JOIN INV1 T1 ON T0.DocEntry = T1.DocEntry
                LEFT JOIN OITM IT ON IT.ItemCode = T1.ItemCode
                LEFT JOIN UFD1 UF ON UF.TableID = 'OITM' AND UF.FieldID = 8 AND UF.FldValue = IT.U_Familia
                LEFT JOIN UFD1 US ON US.TableID = 'OITM' AND US.FieldID = 9 AND US.FldValue = IT.U_SubFamilia
                WHERE T0.CANCELED = 'N' $filtroFecha

                UNION ALL

                SELECT
                    T0.DocEntry,
                    'Nota de Crédito',
                    T0.DocNum,
                    T0.DocDate,
                    T0.CardName,
                    T1.LineNum,
                    T1.ItemCode,
                    T1.Dscription,
                    UF.Descr,
                    US.Descr,
                    T1.unitMsr,
                    -T1.Quantity,
                    T1.PriceBefDi,
                    T1.DiscPrcnt,
                    T1.Price,
                    -T1.LineTotal,
                    T1.VatPrcnt,
                    -T1.VatSum,
                    -T1.GTotal
                FROM ORIN T0
                INNER JOIN RIN1 T1 ON T0.DocEntry = T1.DocEntry
                LEFT JOIN OITM IT ON IT.ItemCode = T1.ItemCode
                LEFT JOIN UFD1 UF ON UF.TableID = 'OITM' AND UF.FieldID = 8 AND UF.FldValue = IT.U_Familia
                LEFT JOIN UFD1 US ON US.TableID = 'OITM' AND US.FieldID = 9 AND US.FldValue = IT.U_SubFamilia
                WHERE T0.CANCELED = 'N' $filtroFecha

                ORDER BY FechaDocumento, TipoDoc, NumDoc, Linea
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($params, $params));

            return $stmt->fetchAll();
        }

        /**
         * Consulta v3: líneas de facturas (INV1) + NC (RIN1) AGRUPADAS por fecha del documento
         * y código de artículo. Suma cantidad, precio s/desc, neto, IVA y bruto de cada grupo.
         * Familia/Sub-Familia viajan en el resultado (para los filtros) aunque no se muestren
         * como columnas. Mismo filtro por fecha del documento que la v2.
         *
         * @param  string $desde Fecha documento desde (inclusive), '' = sin límite.
         * @param  string $hasta Fecha documento hasta (inclusive), '' = sin límite.
         * @return array Lista de filas (arreglos asociativos).
         */
        public function facturasNotasCreditoPorArticulo($desde = '', $hasta = '')
        {
            $desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) ? $desde : '';
            $hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta) ? $hasta : '';

            $filtroFecha = '';
            $params      = [];

            if ($desde !== '') {
                $filtroFecha .= " AND T0.DocDate >= ?";
                $params[]     = $desde;
            }
            if ($hasta !== '') {
                $filtroFecha .= " AND T0.DocDate <= ?";
                $params[]     = $hasta;
            }

            // Se aplana INV1 + RIN1 (subconsulta) y luego se agrupa por fecha + artículo.
            $sql = "
                SELECT
                    X.FechaDocumento,
                    X.CodArticulo,
                    X.Articulo,
                    X.Familia,
                    X.SubFamilia,
                    SUM(X.Cantidad)      AS Cantidad,
                    SUM(X.PrecioSinDesc) AS PrecioSinDesc,
                    SUM(X.TotalNeto)     AS TotalNeto,
                    SUM(X.IvaMonto)      AS IvaMonto,
                    SUM(X.TotalBruto)    AS TotalBruto
                FROM (
                    SELECT
                        CONVERT(char(7), T0.DocDate, 126) AS FechaDocumento,
                        T1.ItemCode   AS CodArticulo,
                        IT.ItemName   AS Articulo,
                        UF.Descr      AS Familia,
                        US.Descr      AS SubFamilia,
                        T1.Quantity   AS Cantidad,
                        T1.PriceBefDi AS PrecioSinDesc,
                        T1.LineTotal  AS TotalNeto,
                        T1.VatSum     AS IvaMonto,
                        T1.GTotal     AS TotalBruto
                    FROM OINV T0
                    INNER JOIN INV1 T1 ON T0.DocEntry = T1.DocEntry
                    LEFT JOIN OITM IT ON IT.ItemCode = T1.ItemCode
                    LEFT JOIN UFD1 UF ON UF.TableID = 'OITM' AND UF.FieldID = 8 AND UF.FldValue = IT.U_Familia
                    LEFT JOIN UFD1 US ON US.TableID = 'OITM' AND US.FieldID = 9 AND US.FldValue = IT.U_SubFamilia
                    WHERE T0.CANCELED = 'N'
                      AND UF.Descr IS NOT NULL
                      AND US.Descr IS NOT NULL $filtroFecha

                    UNION ALL

                    SELECT
                        CONVERT(char(7), T0.DocDate, 126),
                        T1.ItemCode,
                        IT.ItemName,
                        UF.Descr,
                        US.Descr,
                        -T1.Quantity,
                        T1.PriceBefDi,
                        -T1.LineTotal,
                        -T1.VatSum,
                        -T1.GTotal
                    FROM ORIN T0
                    INNER JOIN RIN1 T1 ON T0.DocEntry = T1.DocEntry
                    LEFT JOIN OITM IT ON IT.ItemCode = T1.ItemCode
                    LEFT JOIN UFD1 UF ON UF.TableID = 'OITM' AND UF.FieldID = 8 AND UF.FldValue = IT.U_Familia
                    LEFT JOIN UFD1 US ON US.TableID = 'OITM' AND US.FieldID = 9 AND US.FldValue = IT.U_SubFamilia
                    WHERE T0.CANCELED = 'N'
                      AND UF.Descr IS NOT NULL
                      AND US.Descr IS NOT NULL $filtroFecha
                ) X
                GROUP BY X.FechaDocumento, X.CodArticulo, X.Articulo, X.Familia, X.SubFamilia
                ORDER BY X.FechaDocumento, X.CodArticulo
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($params, $params));

            return $stmt->fetchAll();
        }

        /**
         * Demanda (Cantidad) DIARIA por artículo: facturas (INV1) menos NC (RIN1), agrupada por
         * DÍA del documento y código de artículo, con familia/sub-familia. Igual que
         * facturasNotasCreditoPorArticulo pero al grano DÍA ('yyyy-MM-dd') y solo Cantidad
         * (la usa el forecast semanal para bucketizar a semana ISO en PHP).
         *
         * @param  string $desde Fecha documento desde (inclusive), '' = sin límite.
         * @param  string $hasta Fecha documento hasta (inclusive), '' = sin límite.
         * @return array Filas: ['Fecha' => 'yyyy-MM-dd', 'CodArticulo', 'Articulo', 'Familia', 'SubFamilia', 'Cantidad'].
         */
        public function demandaDiariaPorArticulo($desde = '', $hasta = '')
        {
            $desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) ? $desde : '';
            $hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta) ? $hasta : '';

            $filtroFecha = '';
            $params      = [];

            if ($desde !== '') {
                $filtroFecha .= " AND T0.DocDate >= ?";
                $params[]     = $desde;
            }
            if ($hasta !== '') {
                $filtroFecha .= " AND T0.DocDate <= ?";
                $params[]     = $hasta;
            }

            $sql = "
                SELECT
                    X.Fecha,
                    X.CodArticulo,
                    X.Articulo,
                    X.Familia,
                    X.SubFamilia,
                    SUM(X.Cantidad) AS Cantidad
                FROM (
                    SELECT
                        CONVERT(char(10), T0.DocDate, 126) AS Fecha,
                        T1.ItemCode AS CodArticulo,
                        IT.ItemName AS Articulo,
                        UF.Descr    AS Familia,
                        US.Descr    AS SubFamilia,
                        T1.Quantity AS Cantidad
                    FROM OINV T0
                    INNER JOIN INV1 T1 ON T0.DocEntry = T1.DocEntry
                    LEFT JOIN OITM IT ON IT.ItemCode = T1.ItemCode
                    LEFT JOIN UFD1 UF ON UF.TableID = 'OITM' AND UF.FieldID = 8 AND UF.FldValue = IT.U_Familia
                    LEFT JOIN UFD1 US ON US.TableID = 'OITM' AND US.FieldID = 9 AND US.FldValue = IT.U_SubFamilia
                    WHERE T0.CANCELED = 'N'
                      AND UF.Descr IS NOT NULL
                      AND US.Descr IS NOT NULL $filtroFecha

                    UNION ALL

                    SELECT
                        CONVERT(char(10), T0.DocDate, 126),
                        T1.ItemCode,
                        IT.ItemName,
                        UF.Descr,
                        US.Descr,
                        -T1.Quantity
                    FROM ORIN T0
                    INNER JOIN RIN1 T1 ON T0.DocEntry = T1.DocEntry
                    LEFT JOIN OITM IT ON IT.ItemCode = T1.ItemCode
                    LEFT JOIN UFD1 UF ON UF.TableID = 'OITM' AND UF.FieldID = 8 AND UF.FldValue = IT.U_Familia
                    LEFT JOIN UFD1 US ON US.TableID = 'OITM' AND US.FieldID = 9 AND US.FldValue = IT.U_SubFamilia
                    WHERE T0.CANCELED = 'N'
                      AND UF.Descr IS NOT NULL
                      AND US.Descr IS NOT NULL $filtroFecha
                ) X
                GROUP BY X.Fecha, X.CodArticulo, X.Articulo, X.Familia, X.SubFamilia
                ORDER BY X.Fecha, X.CodArticulo
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($params, $params));

            return $stmt->fetchAll();
        }

        /**
         * Detalle de la v3: facturas (INV1) y NC (RIN1) donde aparece un artículo dentro de
         * un año-mes ('yyyy-MM'). Devuelve una fila por línea de ese artículo en cada documento.
         *
         * @param  string $anioMes  Año-mes en formato 'yyyy-MM'.
         * @param  string $itemCode Código de artículo (ItemCode).
         * @return array Lista de filas (arreglos asociativos).
         */
        public function documentosPorArticuloMes($anioMes, $itemCode)
        {
            // El año-mes debe venir como 'yyyy-MM'; si no, no se consulta nada.
            if (!preg_match('/^\d{4}-\d{2}$/', $anioMes)) {
                return [];
            }

            $sql = "
                SELECT
                    T0.DocEntry   AS DocEntry,
                    'Factura'     AS TipoDoc,
                    T0.DocNum     AS NumDoc,
                    T0.DocDate    AS FechaDocumento,
                    T0.CardName   AS Cliente,
                    T1.LineNum    AS Linea,
                    T1.Quantity   AS Cantidad,
                    T1.LineTotal  AS TotalNeto,
                    T1.VatSum     AS IvaMonto,
                    T1.GTotal     AS TotalBruto
                FROM OINV T0
                INNER JOIN INV1 T1 ON T0.DocEntry = T1.DocEntry
                WHERE T0.CANCELED = 'N'
                  AND T1.ItemCode = ?
                  AND CONVERT(char(7), T0.DocDate, 126) = ?

                UNION ALL

                SELECT
                    T0.DocEntry,
                    'Nota de Crédito',
                    T0.DocNum,
                    T0.DocDate,
                    T0.CardName,
                    T1.LineNum,
                    -T1.Quantity,
                    -T1.LineTotal,
                    -T1.VatSum,
                    -T1.GTotal
                FROM ORIN T0
                INNER JOIN RIN1 T1 ON T0.DocEntry = T1.DocEntry
                WHERE T0.CANCELED = 'N'
                  AND T1.ItemCode = ?
                  AND CONVERT(char(7), T0.DocDate, 126) = ?

                ORDER BY FechaDocumento, TipoDoc, NumDoc
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$itemCode, $anioMes, $itemCode, $anioMes]);

            return $stmt->fetchAll();
        }

        /**
         * Consulta v4: líneas de facturas (INV1) + NC (RIN1) AGRUPADAS por año-mes y FAMILIA.
         * Suma cantidad, neto, IVA y bruto por familia. Solo líneas de artículos con familia y
         * sub-familia identificadas (mismas exclusiones que la v3). Las NC quedan neteadas.
         *
         * @param  string $desde Fecha documento desde (inclusive), '' = sin límite.
         * @param  string $hasta Fecha documento hasta (inclusive), '' = sin límite.
         * @return array Lista de filas (arreglos asociativos).
         */
        public function facturasNotasCreditoPorFamilia($desde = '', $hasta = '')
        {
            $desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) ? $desde : '';
            $hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta) ? $hasta : '';

            $filtroFecha = '';
            $params      = [];

            if ($desde !== '') {
                $filtroFecha .= " AND T0.DocDate >= ?";
                $params[]     = $desde;
            }
            if ($hasta !== '') {
                $filtroFecha .= " AND T0.DocDate <= ?";
                $params[]     = $hasta;
            }

            $sql = "
                SELECT
                    X.FechaDocumento,
                    X.Familia,
                    SUM(X.Cantidad)   AS Cantidad,
                    SUM(X.TotalNeto)  AS TotalNeto,
                    SUM(X.IvaMonto)   AS IvaMonto,
                    SUM(X.TotalBruto) AS TotalBruto
                FROM (
                    SELECT
                        CONVERT(char(7), T0.DocDate, 126) AS FechaDocumento,
                        UF.Descr     AS Familia,
                        T1.Quantity  AS Cantidad,
                        T1.LineTotal AS TotalNeto,
                        T1.VatSum    AS IvaMonto,
                        T1.GTotal    AS TotalBruto
                    FROM OINV T0
                    INNER JOIN INV1 T1 ON T0.DocEntry = T1.DocEntry
                    LEFT JOIN OITM IT ON IT.ItemCode = T1.ItemCode
                    LEFT JOIN UFD1 UF ON UF.TableID = 'OITM' AND UF.FieldID = 8 AND UF.FldValue = IT.U_Familia
                    LEFT JOIN UFD1 US ON US.TableID = 'OITM' AND US.FieldID = 9 AND US.FldValue = IT.U_SubFamilia
                    WHERE T0.CANCELED = 'N'
                      AND UF.Descr IS NOT NULL
                      AND US.Descr IS NOT NULL $filtroFecha

                    UNION ALL

                    SELECT
                        CONVERT(char(7), T0.DocDate, 126),
                        UF.Descr,
                        -T1.Quantity,
                        -T1.LineTotal,
                        -T1.VatSum,
                        -T1.GTotal
                    FROM ORIN T0
                    INNER JOIN RIN1 T1 ON T0.DocEntry = T1.DocEntry
                    LEFT JOIN OITM IT ON IT.ItemCode = T1.ItemCode
                    LEFT JOIN UFD1 UF ON UF.TableID = 'OITM' AND UF.FieldID = 8 AND UF.FldValue = IT.U_Familia
                    LEFT JOIN UFD1 US ON US.TableID = 'OITM' AND US.FieldID = 9 AND US.FldValue = IT.U_SubFamilia
                    WHERE T0.CANCELED = 'N'
                      AND UF.Descr IS NOT NULL
                      AND US.Descr IS NOT NULL $filtroFecha
                ) X
                GROUP BY X.FechaDocumento, X.Familia
                ORDER BY X.FechaDocumento, X.Familia
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($params, $params));

            return $stmt->fetchAll();
        }

        /**
         * Demanda mensual (cantidad) de un artículo a lo largo de TODA su historia,
         * SIN filtro de fecha. Facturas (INV1) en positivo y NC (RIN1) en negativo (netas).
         * Una fila por año-mes ('yyyy-MM'), en orden ascendente. Pensada para graficar.
         *
         * @param  string $itemCode Código de artículo (ItemCode).
         * @return array Filas: ['FechaDocumento' => 'yyyy-MM', 'Demanda' => float, 'Neto' => float].
         */
        public function demandaMensualProducto($itemCode)
        {
            $sql = "
                SELECT
                    X.FechaDocumento,
                    SUM(X.Cantidad)  AS Demanda,
                    SUM(X.TotalNeto) AS Neto
                FROM (
                    SELECT
                        CONVERT(char(7), T0.DocDate, 126) AS FechaDocumento,
                        T1.Quantity  AS Cantidad,
                        T1.LineTotal AS TotalNeto
                    FROM OINV T0
                    INNER JOIN INV1 T1 ON T0.DocEntry = T1.DocEntry
                    WHERE T0.CANCELED = 'N' AND T1.ItemCode = ?

                    UNION ALL

                    SELECT
                        CONVERT(char(7), T0.DocDate, 126),
                        -T1.Quantity,
                        -T1.LineTotal
                    FROM ORIN T0
                    INNER JOIN RIN1 T1 ON T0.DocEntry = T1.DocEntry
                    WHERE T0.CANCELED = 'N' AND T1.ItemCode = ?
                ) X
                GROUP BY X.FechaDocumento
                ORDER BY X.FechaDocumento
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$itemCode, $itemCode]);

            return $stmt->fetchAll();
        }

        /**
         * Demanda (Cantidad) y neto DIARIOS de un artículo: facturas menos NC, por DÍA del
         * documento ('yyyy-MM-dd'). Igual que demandaMensualProducto pero al grano día, para
         * que el gráfico agregue a SEMANA ISO en PHP.
         *
         * @return array Filas: ['Fecha' => 'yyyy-MM-dd', 'Demanda' => ..., 'Neto' => ...].
         */
        public function demandaDiariaProducto($itemCode)
        {
            $sql = "
                SELECT
                    X.Fecha,
                    SUM(X.Cantidad)  AS Demanda,
                    SUM(X.TotalNeto) AS Neto
                FROM (
                    SELECT
                        CONVERT(char(10), T0.DocDate, 126) AS Fecha,
                        T1.Quantity  AS Cantidad,
                        T1.LineTotal AS TotalNeto
                    FROM OINV T0
                    INNER JOIN INV1 T1 ON T0.DocEntry = T1.DocEntry
                    WHERE T0.CANCELED = 'N' AND T1.ItemCode = ?

                    UNION ALL

                    SELECT
                        CONVERT(char(10), T0.DocDate, 126),
                        -T1.Quantity,
                        -T1.LineTotal
                    FROM ORIN T0
                    INNER JOIN RIN1 T1 ON T0.DocEntry = T1.DocEntry
                    WHERE T0.CANCELED = 'N' AND T1.ItemCode = ?
                ) X
                GROUP BY X.Fecha
                ORDER BY X.Fecha
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$itemCode, $itemCode]);

            return $stmt->fetchAll();
        }

        /**
         * Parámetros para MRP de un producto en la bodega 010: maestro de ítem (OITM),
         * stock por bodega (OITW), nombre de bodega (OWHS) y proveedor predeterminado (OCRD).
         * Devuelve una fila por bodega (con WhsCode fijo en '010', típicamente una).
         *
         * @return array
         */
        public function parametrosMrpProducto($itemCode)
        {
            $sql = "
                SELECT
                    " . self::COLUMNAS_PARAMETROS_MRP . "
                FROM OITM T0
                INNER JOIN OITW T1 ON T0.ItemCode = T1.ItemCode
                LEFT  JOIN UFD1 UF  ON UF.TableID  = 'OITM' AND UF.FieldID  = 8  AND UF.FldValue  = T0.U_Familia
                LEFT  JOIN UFD1 US  ON US.TableID  = 'OITM' AND US.FieldID  = 9  AND US.FldValue  = T0.U_SubFamilia
                LEFT  JOIN UFD1 UO  ON UO.TableID  = 'OITM' AND UO.FieldID  = 7  AND UO.FldValue  = T0.U_Origin
                LEFT  JOIN UFD1 UMP ON UMP.TableID = 'OITM' AND UMP.FieldID = 12 AND UMP.FldValue = T0.U_MPropia
                LEFT  JOIN UFD1 UEC ON UEC.TableID = 'OITM' AND UEC.FieldID = 15 AND UEC.FldValue = T0.U_ECommerce
                LEFT  JOIN [@PROVEEDORES] PV ON LTRIM(RTRIM(PV.Code)) = LTRIM(RTRIM(T0.U_NX_Proveedor))
                WHERE T1.WhsCode = '010' AND T0.ItemCode = ?
                ORDER BY T0.ItemCode, T1.WhsCode
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$itemCode]);

            return $stmt->fetchAll();
        }

        /**
         * Parámetros para MRP de TODOS los productos en la bodega 010 (misma consulta que
         * parametrosMrpProducto, sin el filtro por producto). Para el listado del mantenedor.
         *
         * @return array
         */
        public function parametrosMrp()
        {
            $sql = "
                SELECT
                    " . self::COLUMNAS_PARAMETROS_MRP . "
                FROM OITM T0
                INNER JOIN OITW T1 ON T0.ItemCode = T1.ItemCode
                LEFT  JOIN UFD1 UF  ON UF.TableID  = 'OITM' AND UF.FieldID  = 8  AND UF.FldValue  = T0.U_Familia
                LEFT  JOIN UFD1 US  ON US.TableID  = 'OITM' AND US.FieldID  = 9  AND US.FldValue  = T0.U_SubFamilia
                LEFT  JOIN UFD1 UO  ON UO.TableID  = 'OITM' AND UO.FieldID  = 7  AND UO.FldValue  = T0.U_Origin
                LEFT  JOIN UFD1 UMP ON UMP.TableID = 'OITM' AND UMP.FieldID = 12 AND UMP.FldValue = T0.U_MPropia
                LEFT  JOIN UFD1 UEC ON UEC.TableID = 'OITM' AND UEC.FieldID = 15 AND UEC.FldValue = T0.U_ECommerce
                LEFT  JOIN [@PROVEEDORES] PV ON LTRIM(RTRIM(PV.Code)) = LTRIM(RTRIM(T0.U_NX_Proveedor))
                WHERE T1.WhsCode = '010'
                ORDER BY T0.ItemCode, T1.WhsCode
            ";

            return $this->pdo->query($sql)->fetchAll();
        }

        /**
         * Estado de actividad de los artículos (maestro OITM). Dos nociones de estado:
         *   U_Sta_Art = 'Activo' | 'Descontinuado' -> estado de NEGOCIO (UDF). Criterio del forecast.
         *   validFor = 'Y'/'N', frozenFor = 'Y'/'N' -> flags estándar SAP (referencia).
         * El forecast usa U_Sta_Art: los 'Descontinuado' se excluyen del cálculo. Los flags
         * estándar están desactualizados (muchos descontinuados siguen con validFor='Y').
         *
         * @return array Filas: ['ItemCode'=>..., 'U_Sta_Art'=>..., 'validFor'=>..., 'frozenFor'=>...].
         */
        public function estadoActividadProductos()
        {
            return $this->pdo->query("SELECT ItemCode, U_Sta_Art, validFor, frozenFor FROM OITM")->fetchAll();
        }

        /**
         * Abastecimiento por producto para el MRP (bodega 010), en versión reducida:
         *   - Comprometido = salidas reservadas: OV abiertas + consumo de producción liberada (bodega 010).
         *   - EnPedido     = entradas por compra (OC pendiente): bodegas 010 + IMP01 (Importaciones),
         *                    porque las compras de importados se reciben en IMP01 antes de pasar a 010.
         *   - EnProduccion = entradas por producción (órdenes liberadas hacia 010).
         * Solo artículos con ficha en la bodega 010. Incluye también LeadTime (U_LeadTime, en días).
         *
         * @return array Filas: ['ItemCode'=>..., 'LeadTime'=>..., 'Comprometido'=>..., 'EnPedido'=>..., 'EnProduccion'=>...].
         */
        public function abastecimientoPorProducto()
        {
            $sql = "
                SELECT
                    T0.ItemCode,
                    T0.U_LeadTime AS LeadTime,
                    ISNULL((
                        SELECT SUM(r.OpenQty)
                        FROM RDR1 r INNER JOIN ORDR o ON o.DocEntry = r.DocEntry
                        WHERE o.CANCELED = 'N' AND r.LineStatus = 'O' AND r.OpenQty > 0
                          AND r.WhsCode = '010' AND r.ItemCode = T0.ItemCode
                    ), 0)
                    + ISNULL((
                        SELECT SUM(c.PlannedQty - c.IssuedQty)
                        FROM WOR1 c INNER JOIN OWOR w ON w.DocEntry = c.DocEntry
                        WHERE w.Status = 'R' AND (c.PlannedQty - c.IssuedQty) > 0
                          AND c.Warehouse = '010' AND c.ItemCode = T0.ItemCode
                    ), 0) AS Comprometido,
                    ISNULL((
                        SELECT SUM(p.OpenQty)
                        FROM POR1 p INNER JOIN OPOR op ON op.DocEntry = p.DocEntry
                        WHERE op.CANCELED = 'N' AND p.LineStatus = 'O' AND p.OpenQty > 0
                          AND p.WhsCode IN ('010', 'IMP01') AND p.ItemCode = T0.ItemCode
                    ), 0) AS EnPedido,
                    ISNULL((
                        SELECT SUM(w2.PlannedQty - w2.CmpltQty)
                        FROM OWOR w2
                        WHERE w2.Status = 'R' AND (w2.PlannedQty - w2.CmpltQty) > 0
                          AND w2.Warehouse = '010' AND w2.ItemCode = T0.ItemCode
                    ), 0) AS EnProduccion
                FROM OITM T0
                WHERE EXISTS (SELECT 1 FROM OITW t WHERE t.ItemCode = T0.ItemCode AND t.WhsCode = '010')
            ";

            return $this->pdo->query($sql)->fetchAll();
        }
    }
