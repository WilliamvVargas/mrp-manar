<?php

    /*
     * Modelo de acceso a datos de la tabla `forecast_x_producto`.
     *
     * La vista del mantenedor está AGRUPADA POR PRODUCTO: una fila por
     * producto_codigo, con el total de demanda de las próximas 52 semanas y la
     * demanda de la primera semana de forecast.
     *
     * TODO el forecast está acotado a la EMPRESA activa: la tabla guarda una corrida
     * por empresa (columna empresa_id + version_presupuesto), así que cada consulta
     * filtra por la empresa recibida en el constructor.
     */
    class Forecast
    {
        /** @var PDO */
        private $pdo;

        /** @var string|null Empresa activa: todo el forecast se filtra por ella. */
        private $empresaId;

        public function __construct(PDO $pdo, $empresaId = null)
        {
            $this->pdo       = $pdo;
            $this->empresaId = $empresaId;
        }

        /**
         * Cantidad total de PRODUCTOS distintos con forecast de la empresa activa.
         */
        public function contarTodos()
        {
            $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT producto_codigo) FROM forecast_x_producto WHERE empresa_id = ?");
            $stmt->execute([$this->empresaId]);
            return (int) $stmt->fetchColumn();
        }

        /**
         * Cantidad de PRODUCTOS distintos que coinciden con los filtros (buscador de
         * producto, familia y sub-familia). Si no hay filtro, equivale al total.
         */
        public function contarFiltrados($busqueda, $familia = '', $subFamilia = '', $calidad = '')
        {
            list($where, $params) = $this->construirFiltro($busqueda, $familia, $subFamilia, $calidad);

            $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT f.producto_codigo) FROM forecast_x_producto f $where");
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }

        /**
         * Construye el WHERE de los filtros: SIEMPRE acota a la empresa activa, más el buscador
         * de producto (nombre o código, LIKE), familia y sub-familia exactas. Compartido por
         * contarFiltrados y listarPagina; las columnas van calificadas con el alias `f`.
         *
         * @return array [string $where, array $params]
         */
        private function construirFiltro($busqueda, $familia, $subFamilia, $calidad = '')
        {
            // El forecast siempre se acota a la empresa activa.
            $condiciones = ['f.empresa_id = ?'];
            $params      = [$this->empresaId];

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

            if ($calidad !== '') {
                $condiciones[] = "f.calidad = ?";
                $params[]      = $calidad;
            }

            $where = 'WHERE ' . implode(' AND ', $condiciones);

            return [$where, $params];
        }

        /**
         * Familias distintas presentes en el forecast de la empresa (alfabético), para el filtro.
         *
         * @return string[]
         */
        public function familiasDisponibles()
        {
            $stmt = $this->pdo->prepare(
                "SELECT DISTINCT familia FROM forecast_x_producto WHERE empresa_id = ? AND familia IS NOT NULL AND familia <> '' ORDER BY familia ASC"
            );
            $stmt->execute([$this->empresaId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        /**
         * Serie semanal del forecast por producto (de la empresa activa), ordenada
         * cronológicamente. Para el MRP: permite sumar la demanda de las próximas N semanas.
         *
         * @return array Filas ['producto_codigo'=>..., 'semana_inicio'=>'yyyy-mm-dd', 'demanda'=>float]
         */
        public function demandaSemanalPorProducto()
        {
            $stmt = $this->pdo->prepare(
                "SELECT producto_codigo, semana_inicio, SUM(demanda_forecast) AS demanda
                 FROM forecast_x_producto
                 WHERE empresa_id = ?
                 GROUP BY producto_codigo, semana_inicio
                 ORDER BY producto_codigo, semana_inicio ASC"
            );
            $stmt->execute([$this->empresaId]);
            return $stmt->fetchAll();
        }

        /**
         * Serie semanal del forecast de UN producto (de la empresa activa), cronológica. Para
         * el detalle del MRP: desglose semana a semana de la "Demanda (Forecast)".
         *
         * @return array Filas ['semana_inicio'=>'yyyy-mm-dd', 'iso_year'=>int, 'iso_week'=>int, 'demanda'=>float]
         */
        public function serieSemanalProducto($productoCodigo)
        {
            $stmt = $this->pdo->prepare(
                "SELECT semana_inicio, iso_year, iso_week, demanda_forecast AS demanda
                 FROM forecast_x_producto
                 WHERE empresa_id = ? AND producto_codigo = ?
                 ORDER BY semana_inicio ASC"
            );
            $stmt->execute([$this->empresaId, $productoCodigo]);
            return $stmt->fetchAll();
        }

        /**
         * Sub-familias distintas presentes en el forecast de la empresa (alfabético), para el filtro.
         *
         * @return string[]
         */
        public function subFamiliasDisponibles()
        {
            $stmt = $this->pdo->prepare(
                "SELECT DISTINCT sub_familia FROM forecast_x_producto WHERE empresa_id = ? AND sub_familia IS NOT NULL AND sub_familia <> '' ORDER BY sub_familia ASC"
            );
            $stmt->execute([$this->empresaId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        /**
         * Devuelve una página de PRODUCTOS para DataTables (server-side), agrupando
         * forecast_x_producto por producto (de la empresa activa):
         *   - total_forecast      = SUM(demanda_forecast) de todas las semanas (horizonte 52 sem).
         *   - forecast_sig_semana = demanda_forecast de la primera semana de forecast del producto.
         *   - version             = versión de presupuesto con la que se calculó.
         *   - usa_presupuesto     = 1 si el grupo del producto se pronosticó CON presupuesto.
         *
         * @param string $busqueda   Texto a buscar en producto_nombre o producto_codigo.
         * @param string $familia    Familia exacta a filtrar ('' = todas).
         * @param string $subFamilia Sub-familia exacta a filtrar ('' = todas).
         * @param array  $ordenes    Lista de ['col' => nombre lógico, 'dir' => 'asc'|'desc'].
         * @param int    $inicio     Offset (registro inicial).
         * @param int    $longitud   Cantidad de registros (-1 = todos).
         */
        public function listarPagina($busqueda, $familia, $subFamilia, $calidad, array $ordenes, $inicio, $longitud)
        {
            // Lista blanca de columnas ordenables (aliases de la consulta): evita inyección.
            $columnasValidas = [
                'producto_codigo'     => 'producto_codigo',
                'producto_nombre'     => 'producto_nombre',
                'familia'             => 'familia',
                'sub_familia'         => 'sub_familia',
                'total_forecast'      => 'total_forecast',
                'forecast_sig_semana' => 'forecast_sig_semana',
                'version'             => 'version',
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

            list($where, $params) = $this->construirFiltro($busqueda, $familia, $subFamilia, $calidad);

            $limit = ($longitud < 0) ? '' : "LIMIT $inicio, $longitud";

            // pm: primera semana de forecast de cada producto (menor semana_inicio) DE LA EMPRESA,
            // para tomar la demanda de la "semana siguiente" (la más próxima del horizonte).
            $sql = "SELECT f.producto_codigo,
                           MAX(f.producto_nombre)        AS producto_nombre,
                           MAX(f.familia)                AS familia,
                           MAX(f.sub_familia)            AS sub_familia,
                           MAX(f.version_presupuesto)    AS version,
                           SUM(f.demanda_forecast)       AS total_forecast,
                           SUM(CASE WHEN f.semana_inicio = pm.min_semana
                                    THEN f.demanda_forecast ELSE 0 END) AS forecast_sig_semana,
                           MAX(f.usa_presupuesto)        AS usa_presupuesto,
                           MAX(f.semanas_historia)       AS semanas_historia,
                           MAX(f.calidad)                AS calidad
                    FROM forecast_x_producto f
                    JOIN (
                        SELECT producto_codigo, MIN(semana_inicio) AS min_semana
                        FROM forecast_x_producto
                        WHERE empresa_id = ?
                        GROUP BY producto_codigo
                    ) pm ON pm.producto_codigo = f.producto_codigo
                    $where
                    GROUP BY f.producto_codigo
                    ORDER BY $orderBy
                    $limit";

            // El primer '?' es el empresa_id de la subconsulta pm; luego van los de $where
            // (que ya empieza por el empresa_id de la consulta externa).
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge([$this->empresaId], $params));

            return $stmt->fetchAll();
        }
    }
