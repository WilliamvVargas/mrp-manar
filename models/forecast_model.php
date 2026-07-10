<?php

    /*
     * Modelo de acceso a datos de la tabla `forecast_x_producto`.
     *
     * Centraliza las consultas SQL del forecast por producto. Se instancia
     * pasándole una conexión PDO, igual que el modelo de usuarios.
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
         * Cantidad total de registros de forecast (sin filtro).
         */
        public function contarTodos()
        {
            return (int) $this->pdo->query("SELECT COUNT(*) FROM forecast_x_producto")->fetchColumn();
        }

        /**
         * Cantidad de registros que coinciden con los filtros (buscador de producto,
         * familia, sub-familia, año y mes). Si no hay ningún filtro, equivale al total.
         */
        public function contarFiltrados($busqueda, $familia = '', $subFamilia = '', $anio = '', $mes = '')
        {
            list($where, $params) = $this->construirFiltro($busqueda, $familia, $subFamilia, $anio, $mes);

            if ($where === '') {
                return $this->contarTodos();
            }

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM forecast_x_producto $where");
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }

        /**
         * Construye el WHERE de los filtros de la tabla: buscador de producto (nombre o
         * código, LIKE), familia y sub-familia exactas, año y mes.
         * Compartido por contarFiltrados y listarPagina.
         *
         * @return array [string $where, array $params]
         */
        private function construirFiltro($busqueda, $familia, $subFamilia, $anio, $mes)
        {
            $condiciones = [];
            $params      = [];

            if ($busqueda !== '') {
                $like          = '%' . $busqueda . '%';
                $condiciones[] = "(producto_nombre LIKE ? OR producto_codigo LIKE ?)";
                $params[]      = $like;
                $params[]      = $like;
            }

            if ($familia !== '') {
                $condiciones[] = "familia = ?";
                $params[]      = $familia;
            }

            if ($subFamilia !== '') {
                $condiciones[] = "sub_familia = ?";
                $params[]      = $subFamilia;
            }

            if ($anio !== '' && ctype_digit((string) $anio)) {
                $condiciones[] = "anio = ?";
                $params[]      = (int) $anio;
            }

            if ($mes !== '' && ctype_digit((string) $mes)) {
                $condiciones[] = "mes = ?";
                $params[]      = (int) $mes;
            }

            $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

            return [$where, $params];
        }

        /**
         * Años distintos presentes en el forecast (descendente), para el filtro de Año.
         *
         * @return int[]
         */
        public function aniosDisponibles()
        {
            return array_map(
                'intval',
                $this->pdo->query("SELECT DISTINCT anio FROM forecast_x_producto ORDER BY anio DESC")
                          ->fetchAll(PDO::FETCH_COLUMN)
            );
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
         * Devuelve una página de registros para DataTables (server-side).
         *
         * @param string $busqueda   Texto a buscar en producto_nombre o producto_codigo.
         * @param string $familia    Familia exacta a filtrar ('' = todas).
         * @param string $subFamilia Sub-familia exacta a filtrar ('' = todas).
         * @param string $anio       Año a filtrar ('' = todos).
         * @param string $mes        Mes (1-12) a filtrar ('' = todos).
         * @param array  $ordenes    Lista de ['col' => nombre lógico, 'dir' => 'asc'|'desc']
         *                           para el ORDER BY (multi-columna). Si viene vacía, se usa
         *                           el orden de negocio por defecto: producto, año y mes ASC.
         * @param int    $inicio     Offset (registro inicial).
         * @param int    $longitud   Cantidad de registros (-1 = todos).
         */
        public function listarPagina($busqueda, $familia, $subFamilia, $anio, $mes, array $ordenes, $inicio, $longitud)
        {
            // Lista blanca de columnas ordenables: evita inyección en el ORDER BY.
            $columnasValidas = [
                'anio'             => 'anio',
                'mes'              => 'mes',
                'producto_codigo'  => 'producto_codigo',
                'producto_nombre'  => 'producto_nombre',
                'familia'          => 'familia',
                'sub_familia'      => 'sub_familia',
                'demanda_forecast' => 'demanda_forecast',
                'created_at'       => 'created_at',
            ];

            // Compone el ORDER BY a partir de las columnas válidas recibidas.
            $piezas = [];
            foreach ($ordenes as $o) {
                $col = $columnasValidas[$o['col'] ?? ''] ?? null;
                if ($col === null) {
                    continue;
                }
                $dir      = (strtolower($o['dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';
                $piezas[] = "$col $dir";
            }
            // Orden de negocio por defecto: Código Producto, Año y Mes (ascendente).
            $orderBy = $piezas ? implode(', ', $piezas) : 'producto_codigo ASC, anio ASC, mes ASC';

            $inicio   = max(0, (int) $inicio);
            $longitud = (int) $longitud;

            list($where, $params) = $this->construirFiltro($busqueda, $familia, $subFamilia, $anio, $mes);

            $limit = ($longitud < 0) ? '' : "LIMIT $inicio, $longitud";

            $sql = "SELECT anio,
                           mes,
                           producto_codigo,
                           producto_nombre,
                           familia,
                           sub_familia,
                           demanda_forecast,
                           created_at
                    FROM forecast_x_producto
                    $where
                    ORDER BY $orderBy
                    $limit";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        }
    }
