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
         * Stock por artículo y bodega: existencias (actual, comprometido, pedido),
         * mínimos/máximos y parámetros de abastecimiento/planificación. Solo artículos
         * de inventario y activos.
         *
         * @return array Lista de filas (arreglos asociativos).
         */
        public function stock()
        {
            $sql = "
                SELECT
                    T0.ItemCode        AS CodigoArticulo,
                    T0.ItemName        AS NombreArticulo,
                    T0.ItmsGrpCod      AS CodigoGrupo,
                    T0.InvntryUom      AS UnidadInventario,

                    T1.WhsCode         AS CodigoBodega,
                    T2.WhsName         AS NombreBodega,

                    T1.OnHand          AS StockActual,
                    T1.IsCommited      AS Comprometido,
                    T1.OnOrder         AS Pedido,
                    T1.MinStock        AS StockMinimo,
                    T1.MaxStock        AS StockMaximo,

                    T1.MinOrder        AS CantidadMinimaPedido,
                    --T1.OrderMultiple   AS MultiploPedido,

                    T0.PrcrmntMtd      AS MetodoAbastecimiento,
                    T0.OrdrIntrvl      AS IntervaloPedido,
                    T0.OrdrMulti       AS MultiploPedidoGeneral,
                    T0.MinOrdrQty      AS CantidadMinimaPedidoGeneral,
                    T0.LeadTime        AS TiempoEntregaDias,
                    T0.PlaningSys      AS SistemaPlanificacion,
                    T0.PrchseItem      AS ArticuloCompra,
                    T0.SellItem        AS ArticuloVenta,
                    T0.InvntItem       AS ArticuloInventario,

                    T0.validFor        AS Activo,
                    T0.frozenFor       AS Bloqueado

                FROM OITM T0
                INNER JOIN OITW T1
                    ON T0.ItemCode = T1.ItemCode

                INNER JOIN OWHS T2
                    ON T1.WhsCode = T2.WhsCode

                WHERE
                    T0.InvntItem = 'Y'
                    AND T0.validFor = 'Y'

                ORDER BY
                    T0.ItemCode,
                    T1.WhsCode
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
                    T1.Quantity,
                    T1.PriceBefDi,
                    T1.DiscPrcnt,
                    T1.Price,
                    T1.LineTotal,
                    T1.VatPrcnt,
                    T1.VatSum,
                    T1.GTotal
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
    }
