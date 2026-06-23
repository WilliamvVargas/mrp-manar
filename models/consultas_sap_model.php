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
    }
