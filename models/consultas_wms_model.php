<?php
    /*
     * Modelo de acceso a datos del WMS (SGL WMS, SQL Server) en modo SOLO LECTURA.
     * Se instancia con la conexión PDO al WMS ($pdoWms de config/conexion_wms.php),
     * que es independiente de la de SAP ($pdoSqlsrv) y de la de MySQL ($pdo).
     */
    class ConsultaWms
    {
        /** @var PDO Conexión al WMS (SGL WMS). */
        private $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        /**
         * Inventario de stock en tiempo real por pallet / lote / ubicación (empresa Manar).
         *
         * Cruza el detalle de pallet (PLTDTL) con el maestro de artículos (GRPART) y la
         * cabecera de pallet (PLTCBC, que aporta ubicación y estado). Agrupa por
         * artículo + fecha de ingreso + fecha de vencimiento + lote + ubicación + estado,
         * sumando la cantidad (PltPUAQty).
         *
         * - Solo empresa Manar (Cod_Emp = '1'), pallets normales (PltTipoPallet = '')
         *   que están guardados en ingreso (PltIngOPck = 'I').
         * - La ubicación (PltUbicGuardado) viene como 'Zona/Pasillo/Rack/Nivel' y se
         *   descompone con PARSENAME sobre la cadena con '/' reemplazado por '.'.
         * - Fecha de vencimiento corregida (CROSS APPLY FV): plantilla para la futura
         *   empresa Vertientes (Cod_Emp='2'), donde ciertas fechas inválidas se llevan a
         *   '29991231' (sin vencimiento). Para Manar (Cod_Emp='1') no altera el dato.
         * - Las columnas de texto se limpian con LTRIM(RTRIM(...)) (padding de char en WMS).
         *
         * @return array Lista de filas (arreglos asociativos).
         */
        public function stock()
        {
            $sql = "
                SELECT
                    LTRIM(RTRIM(T1.GrpCod))          AS CodArticulo,
                    LTRIM(RTRIM(T1.Artdsc))          AS Articulo,
                    CAST(T0.PltDtlDateTime AS DATE)  AS FIngreso,
                    FV.FechaVencimientoCorregida     AS FVencimiento,
                    LTRIM(RTRIM(T0.PltLotePrd))      AS Lote,
                    LTRIM(RTRIM(T3.PltUbicGuardado)) AS Ubicacion,

                    PARSENAME(REPLACE(LTRIM(RTRIM(T3.PltUbicGuardado)), '/', '.'), 4) AS Zona,
                    PARSENAME(REPLACE(LTRIM(RTRIM(T3.PltUbicGuardado)), '/', '.'), 3) AS Pasillo,
                    PARSENAME(REPLACE(LTRIM(RTRIM(T3.PltUbicGuardado)), '/', '.'), 2) AS Rack,
                    PARSENAME(REPLACE(LTRIM(RTRIM(T3.PltUbicGuardado)), '/', '.'), 1) AS Nivel,

                    CASE T3.PltEstado
                         WHEN 'G' THEN 'Guardado'
                         WHEN 'P' THEN 'Picking'
                         WHEN ''  THEN 'Sin Estado'
                         ELSE 'Otro'
                    END AS EstadoPallet,

                    CASE WHEN FV.FechaVencimientoCorregida >= CAST(GETDATE() AS DATE)
                         THEN 'Vigente' ELSE 'Vencido'
                    END AS Vencimiento,

                    DATEDIFF(DAY, CAST(GETDATE() AS DATE), FV.FechaVencimientoCorregida) AS DiasParaVencer,
                    DATEDIFF(DAY, CAST(T0.PltDtlDateTime AS DATE), CAST(GETDATE() AS DATE)) AS DiasEnInventario,

                    SUM(ISNULL(T0.PltPUAQty, 0)) AS Cantidad

                FROM PLTDTL T0
                INNER JOIN GRPART T1
                    ON  T0.ArtCod        = T1.GrpCod
                    AND T0.PltDtlEmpresa = T1.Cod_Emp
                INNER JOIN PLTCBC T3
                    ON  T0.PltCod = T3.PltCod

                CROSS APPLY (
                    SELECT CASE
                        WHEN T1.Cod_Emp = '2' AND T0.PltFArtVto <= '20200101'
                             THEN CONVERT(DATE, '29991231')
                        WHEN T1.Cod_Emp = '2'
                             AND CAST(T0.PltDtlDateTime AS DATE) = CAST(T0.PltFArtVto AS DATE)
                             THEN CONVERT(DATE, '29991231')
                        ELSE CAST(T0.PltFArtVto AS DATE)
                    END AS FechaVencimientoCorregida
                ) FV

                WHERE
                    T1.Cod_Emp        IN ('1')
                    AND T3.PltTipoPallet = ''
                    AND T3.PltIngOPck    = 'I'

                GROUP BY
                    T1.GrpCod,
                    T1.Artdsc,
                    T1.Cod_Emp,
                    CAST(T0.PltDtlDateTime AS DATE),
                    T0.PltFArtVto,
                    T0.PltLotePrd,
                    T3.PltUbicGuardado,
                    T3.PltEstado,
                    FV.FechaVencimientoCorregida

                ORDER BY
                    T1.GrpCod,
                    T0.PltLotePrd,
                    CAST(T0.PltDtlDateTime AS DATE)
            ";

            return $this->pdo->query($sql)->fetchAll();
        }

        /**
         * Stock en tiempo real AGREGADO POR PRODUCTO (empresa Manar), sumando todas las
         * líneas de pallet de cada artículo. Excluye las líneas VENCIDAS: solo se suma el
         * stock cuya fecha de vencimiento corregida es hoy o futura (Vigente).
         *
         * Mismas tablas/JOINs y filtros base que stock() (pallets normales guardados en
         * ingreso, empresa Manar); la diferencia es el filtro de no-vencido y el GROUP BY
         * a nivel de artículo. Devuelve: código, nombre, N° de líneas sumadas y cantidad.
         *
         * @return array Lista de filas (arreglos asociativos).
         */
        public function stockPorProducto()
        {
            $sql = "
                SELECT
                    LTRIM(RTRIM(T1.GrpCod)) AS CodArticulo,
                    LTRIM(RTRIM(T1.Artdsc)) AS Articulo,
                    COUNT(*)                AS Lineas,
                    SUM(ISNULL(T0.PltPUAQty, 0)) AS Cantidad

                FROM PLTDTL T0
                INNER JOIN GRPART T1
                    ON  T0.ArtCod        = T1.GrpCod
                    AND T0.PltDtlEmpresa = T1.Cod_Emp
                INNER JOIN PLTCBC T3
                    ON  T0.PltCod = T3.PltCod

                CROSS APPLY (
                    SELECT CASE
                        WHEN T1.Cod_Emp = '2' AND T0.PltFArtVto <= '20200101'
                             THEN CONVERT(DATE, '29991231')
                        WHEN T1.Cod_Emp = '2'
                             AND CAST(T0.PltDtlDateTime AS DATE) = CAST(T0.PltFArtVto AS DATE)
                             THEN CONVERT(DATE, '29991231')
                        ELSE CAST(T0.PltFArtVto AS DATE)
                    END AS FechaVencimientoCorregida
                ) FV

                WHERE
                    T1.Cod_Emp        IN ('1')
                    AND T3.PltTipoPallet = ''
                    AND T3.PltIngOPck    = 'I'
                    AND FV.FechaVencimientoCorregida >= CAST(GETDATE() AS DATE)

                GROUP BY
                    T1.GrpCod,
                    T1.Artdsc

                ORDER BY
                    T1.GrpCod
            ";

            return $this->pdo->query($sql)->fetchAll();
        }

        /**
         * Igual que stockPorProducto() pero devuelto como mapa código_artículo => cantidad
         * (stock vigente del WMS). Útil para cruzar con datos de SAP en otra conexión.
         *
         * @return array<string,float> [CodArticulo => Cantidad]
         */
        public function stockPorProductoMap()
        {
            $mapa = [];
            foreach ($this->stockPorProducto() as $r) {
                $mapa[$r['CodArticulo']] = $r['Cantidad'];
            }
            return $mapa;
        }

        /**
         * Stock vigente por producto con visibilidad de vencimiento (para el MRP, nivel 1):
         *   - Cantidad       = stock vigente total (igual que stockPorProducto()).
         *   - PorVencer      = parte de ese stock que vence dentro de $diasUmbral días.
         *   - DiasProxVencer = días hasta el lote más próximo a vencer (mínimo del producto).
         * No cambia el cálculo del sugerido: es solo información de alerta.
         *
         * @param int $diasUmbral Umbral de "por vencer" en días (por defecto 30).
         * @return array Filas ['CodArticulo'=>..., 'Cantidad'=>..., 'PorVencer'=>..., 'DiasProxVencer'=>...].
         */
        /**
         * Detalle de stock del WMS de UN producto: una fila por pallet/lote/ubicación/fecha,
         * ordenado por vencimiento (los más próximos a vencer primero, FEFO). Incluye vigentes
         * y vencidos (la columna Vencimiento los distingue). Para la pestaña "Stock" del detalle
         * del MRP.
         *
         * @param string $itemCode Código de artículo (GrpCod).
         * @return array Filas por pallet/lote (arreglos asociativos).
         */
        public function stockDetallePorProducto($itemCode)
        {
            $sql = "
                SELECT
                    CAST(T0.PltDtlDateTime AS DATE)  AS FIngreso,
                    FV.FechaVencimientoCorregida     AS FVencimiento,
                    LTRIM(RTRIM(T0.PltLotePrd))      AS Lote,
                    LTRIM(RTRIM(T3.PltUbicGuardado)) AS Ubicacion,

                    CASE T3.PltEstado
                         WHEN 'G' THEN 'Guardado'
                         WHEN 'P' THEN 'Picking'
                         WHEN ''  THEN 'Sin Estado'
                         ELSE 'Otro'
                    END AS EstadoPallet,

                    CASE WHEN FV.FechaVencimientoCorregida >= CAST(GETDATE() AS DATE)
                         THEN 'Vigente' ELSE 'Vencido'
                    END AS Vencimiento,

                    DATEDIFF(DAY, CAST(GETDATE() AS DATE), FV.FechaVencimientoCorregida) AS DiasParaVencer,
                    DATEDIFF(DAY, CAST(T0.PltDtlDateTime AS DATE), CAST(GETDATE() AS DATE)) AS DiasEnInventario,

                    SUM(ISNULL(T0.PltPUAQty, 0)) AS Cantidad

                FROM PLTDTL T0
                INNER JOIN GRPART T1
                    ON  T0.ArtCod        = T1.GrpCod
                    AND T0.PltDtlEmpresa = T1.Cod_Emp
                INNER JOIN PLTCBC T3
                    ON  T0.PltCod = T3.PltCod

                CROSS APPLY (
                    SELECT CASE
                        WHEN T1.Cod_Emp = '2' AND T0.PltFArtVto <= '20200101'
                             THEN CONVERT(DATE, '29991231')
                        WHEN T1.Cod_Emp = '2'
                             AND CAST(T0.PltDtlDateTime AS DATE) = CAST(T0.PltFArtVto AS DATE)
                             THEN CONVERT(DATE, '29991231')
                        ELSE CAST(T0.PltFArtVto AS DATE)
                    END AS FechaVencimientoCorregida
                ) FV

                WHERE
                    T1.Cod_Emp        IN ('1')
                    AND T3.PltTipoPallet = ''
                    AND T3.PltIngOPck    = 'I'
                    AND LTRIM(RTRIM(T1.GrpCod)) = ?

                GROUP BY
                    T0.PltLotePrd,
                    CAST(T0.PltDtlDateTime AS DATE),
                    T0.PltFArtVto,
                    T3.PltUbicGuardado,
                    T3.PltEstado,
                    FV.FechaVencimientoCorregida

                ORDER BY
                    FV.FechaVencimientoCorregida ASC,
                    T0.PltLotePrd
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([trim($itemCode)]);

            return $stmt->fetchAll();
        }

        public function stockVencimientoPorProducto($diasUmbral = 30)
        {
            $dias = (int) $diasUmbral;
            $sql = "
                SELECT
                    LTRIM(RTRIM(T1.GrpCod)) AS CodArticulo,
                    SUM(ISNULL(T0.PltPUAQty, 0)) AS Cantidad,
                    SUM(CASE WHEN DATEDIFF(day, CAST(GETDATE() AS DATE), FV.FechaVencimientoCorregida) <= $dias
                             THEN ISNULL(T0.PltPUAQty, 0) ELSE 0 END) AS PorVencer,
                    MIN(DATEDIFF(day, CAST(GETDATE() AS DATE), FV.FechaVencimientoCorregida)) AS DiasProxVencer

                FROM PLTDTL T0
                INNER JOIN GRPART T1
                    ON  T0.ArtCod        = T1.GrpCod
                    AND T0.PltDtlEmpresa = T1.Cod_Emp
                INNER JOIN PLTCBC T3
                    ON  T0.PltCod = T3.PltCod

                CROSS APPLY (
                    SELECT CASE
                        WHEN T1.Cod_Emp = '2' AND T0.PltFArtVto <= '20200101'
                             THEN CONVERT(DATE, '29991231')
                        WHEN T1.Cod_Emp = '2'
                             AND CAST(T0.PltDtlDateTime AS DATE) = CAST(T0.PltFArtVto AS DATE)
                             THEN CONVERT(DATE, '29991231')
                        ELSE CAST(T0.PltFArtVto AS DATE)
                    END AS FechaVencimientoCorregida
                ) FV

                WHERE
                    T1.Cod_Emp        IN ('1')
                    AND T3.PltTipoPallet = ''
                    AND T3.PltIngOPck    = 'I'
                    AND FV.FechaVencimientoCorregida >= CAST(GETDATE() AS DATE)

                GROUP BY
                    T1.GrpCod
            ";

            return $this->pdo->query($sql)->fetchAll();
        }
    }
?>
