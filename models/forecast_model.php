<?php

    /*
     * Modelo de acceso a datos de la tabla `forecast_x_producto`.
     *
     * La vista del mantenedor está AGRUPADA POR PRODUCTO: una fila por
     * producto_codigo, con el total de demanda de los próximos 12 meses y la
     * demanda del primer mes de forecast. Se instancia pasándole una conexión PDO.
     */
    class Forecast
    {
        /** @var PDO */
        private $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        /**
         * Cantidad total de PRODUCTOS distintos con forecast (sin filtro).
         */
        public function contarTodos()
        {
            return (int) $this->pdo->query("SELECT COUNT(DISTINCT producto_codigo) FROM forecast_x_producto")->fetchColumn();
        }

        /**
         * Cantidad de PRODUCTOS distintos que coinciden con los filtros (buscador de
         * producto, familia y sub-familia). Si no hay filtro, equivale al total.
         */
        public function contarFiltrados($busqueda, $familia = '', $subFamilia = '')
        {
            list($where, $params) = $this->construirFiltro($busqueda, $familia, $subFamilia);

            if ($where === '') {
                return $this->contarTodos();
            }

            $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT f.producto_codigo) FROM forecast_x_producto f $where");
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }

        /**
         * Construye el WHERE de los filtros: buscador de producto (nombre o código, LIKE),
         * familia y sub-familia exactas. Compartido por contarFiltrados y listarPagina; por eso
         * las columnas van calificadas con el alias `f` (la tabla forecast_x_producto), ya que
         * listarPagina hace JOIN con la subconsulta `pm` y `producto_codigo` sería ambiguo.
         *
         * @return array [string $where, array $params]
         */
        private function construirFiltro($busqueda, $familia, $subFamilia)
        {
            $condiciones = [];
            $params      = [];

            if ($busqueda !== '') {
                $like          = '%' . $busqueda . '%';
                $condiciones[] = "(f.producto_nombre LIKE ? OR f.producto_codigo LIKE ?)";
                $params[]      = $like;
                $params[]      = $like;
            }

            if ($familia !== '') {
                $condiciones[] = "f.familia = ?";
                $params[]      = $familia;
            }

            if ($subFamilia !== '') {
                $condiciones[] = "f.sub_familia = ?";
                $params[]      = $subFamilia;
            }

            $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

            return [$where, $params];
        }

        /**
         * Familias distintas presentes en el forecast (alfabético), para el filtro de Familia.
         *
         * @return string[]
         */
        public function familiasDisponibles()
        {
            return $this->pdo->query(
                "SELECT DISTINCT familia FROM forecast_x_producto WHERE familia IS NOT NULL AND familia <> '' ORDER BY familia ASC"
            )->fetchAll(PDO::FETCH_COLUMN);
        }

        /**
         * Sub-familias distintas presentes en el forecast (alfabético), para el filtro.
         *
         * @return string[]
         */
        public function subFamiliasDisponibles()
        {
            return $this->pdo->query(
                "SELECT DISTINCT sub_familia FROM forecast_x_producto WHERE sub_familia IS NOT NULL AND sub_familia <> '' ORDER BY sub_familia ASC"
            )->fetchAll(PDO::FETCH_COLUMN);
        }

        /**
         * Devuelve una página de PRODUCTOS para DataTables (server-side), agrupando
         * forecast_x_producto por producto:
         *   - total_forecast      = SUM(demanda_forecast) de todas las semanas (horizonte 52 sem).
         *   - forecast_sig_semana = demanda_forecast de la primera semana de forecast del producto.
         *   - usa_presupuesto     = 1 si el grupo del producto se pronosticó CON presupuesto.
         *
         * @param string $busqueda   Texto a buscar en producto_nombre o producto_codigo.
         * @param string $familia    Familia exacta a filtrar ('' = todas).
         * @param string $subFamilia Sub-familia exacta a filtrar ('' = todas).
         * @param array  $ordenes    Lista de ['col' => nombre lógico, 'dir' => 'asc'|'desc'].
         *                           Si viene vacía, ordena por código de producto ASC.
         * @param int    $inicio     Offset (registro inicial).
         * @param int    $longitud   Cantidad de registros (-1 = todos).
         */
        public function listarPagina($busqueda, $familia, $subFamilia, array $ordenes, $inicio, $longitud)
        {
            // Lista blanca de columnas ordenables (aliases de la consulta): evita inyección.
            $columnasValidas = [
                'producto_codigo'     => 'producto_codigo',
                'producto_nombre'     => 'producto_nombre',
                'familia'             => 'familia',
                'sub_familia'         => 'sub_familia',
                'total_forecast'      => 'total_forecast',
                'forecast_sig_semana' => 'forecast_sig_semana',
            ];

            $piezas = [];
            foreach ($ordenes as $o) {
                $col = $columnasValidas[$o['col'] ?? ''] ?? null;
                if ($col === null) {
                    continue;
                }
                $dir      = (strtolower($o['dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';
                $piezas[] = "$col $dir";
            }
            $orderBy = $piezas ? implode(', ', $piezas) : 'producto_codigo ASC';

            $inicio   = max(0, (int) $inicio);
            $longitud = (int) $longitud;

            list($where, $params) = $this->construirFiltro($busqueda, $familia, $subFamilia);

            $limit = ($longitud < 0) ? '' : "LIMIT $inicio, $longitud";

            // pm: primera semana de forecast de cada producto (menor semana_inicio), para tomar
            // la demanda de la "semana siguiente" (la más próxima del horizonte).
            $sql = "SELECT f.producto_codigo,
                           MAX(f.producto_nombre) AS producto_nombre,
                           MAX(f.familia)         AS familia,
                           MAX(f.sub_familia)     AS sub_familia,
                           SUM(f.demanda_forecast) AS total_forecast,
                           SUM(CASE WHEN f.semana_inicio = pm.min_semana
                                    THEN f.demanda_forecast ELSE 0 END) AS forecast_sig_semana,
                           MAX(f.usa_presupuesto) AS usa_presupuesto
                    FROM forecast_x_producto f
                    JOIN (
                        SELECT producto_codigo, MIN(semana_inicio) AS min_semana
                        FROM forecast_x_producto
                        GROUP BY producto_codigo
                    ) pm ON pm.producto_codigo = f.producto_codigo
                    $where
                    GROUP BY f.producto_codigo
                    ORDER BY $orderBy
                    $limit";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        }
    }
