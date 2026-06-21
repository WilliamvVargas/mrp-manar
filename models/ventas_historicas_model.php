<?php

    /*
     * Modelo de acceso a datos de la tabla `ventas_historicas`.
     *
     * Centraliza la inserción masiva de la carga del Excel y la generación de la
     * versión (formato YYYYV000). Se instancia con una conexión PDO.
     */
    class VentaHistorica
    {
        /** @var PDO */
        private $pdo;

        /** @var array Campos (en orden) que se insertan desde el Excel. */
        private $campos = [
            'nro_docto', 'tipo_docto', 'nota_venta', 'cond_venta', 'fecha_docto',
            'rut_cliente', 'razon_social', 'tipo_cliente', 'lista_precio',
            'cod_ejec', 'ejec_comercial', 'f_creacion', 'f_primera_venta', 'f_ultima_venta',
            'cod_articulo', 'descripcion_articulo', 'grupo_articulo', 'familia', 'sub_familia', 'proveedor',
            'cant_pedido', 'pr_pedido', 'subtotal_pedido', 'cant_docto', 'pr_base', 'pct_descuento',
            'subtotal_docto', 'pr_prom_pond', 'costo_pr_pp', 'ganancia_bruta', 'pct_margen',
            'gramaje', 'unid_x_caja', 'sociedad', 'venta_bruta', 'descuento', 'anio', 'mes', 'pr_vta_prom',
        ];

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        /**
         * Genera la siguiente versión con formato YYYYV000 para un año: el correlativo
         * es (máximo del año) + 1 (001 si es la primera carga del año).
         *
         * @param int $anio Año de 4 dígitos (año de la carga).
         * @return string Ej: '2026V001'.
         * @throws RuntimeException si se superan las 999 versiones del año.
         */
        public function siguienteVersion($anio)
        {
            $anio    = (int) $anio;
            $prefijo = sprintf('%04dV', $anio);

            // Correlativo máximo existente para ese año (posiciones 6-8 de la versión).
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(MAX(CAST(SUBSTRING(version, 6, 3) AS UNSIGNED)), 0)
                 FROM ventas_historicas
                 WHERE version LIKE ?"
            );
            $stmt->execute([$prefijo . '%']);
            $siguiente = (int) $stmt->fetchColumn() + 1;

            if ($siguiente > 999) {
                throw new RuntimeException('Se alcanzó el máximo de 999 versiones para el año ' . $anio . '.');
            }

            return sprintf('%04dV%03d', $anio, $siguiente);
        }

        /**
         * Inserta masivamente los registros en lotes (multi-row INSERT) dentro de una
         * transacción (todo o nada). Cada registro es un arreglo asociativo con las
         * claves de $this->campos; todos se marcan con la misma `version`.
         *
         * @param array       $registros Lista de registros.
         * @param string      $version   Versión de la carga (YYYYV000).
         * @param string|null $creadoPor Id del usuario que realiza la carga.
         * @param int         $loteFilas Filas por sentencia INSERT (controla los placeholders).
         * @return int Cantidad de registros insertados.
         */
        public function insertarMasivo(array $registros, $version, $creadoPor = null, $loteFilas = 500)
        {
            if (empty($registros)) {
                return 0;
            }

            $campos           = array_merge($this->campos, ['version', 'created_by']);
            $listaCampos      = '`' . implode('`, `', $campos) . '`';
            $placeholdersFila = '(' . implode(', ', array_fill(0, count($campos), '?')) . ')';

            $this->pdo->beginTransaction();

            try {
                foreach (array_chunk($registros, $loteFilas) as $lote) {
                    $valores = [];
                    $params  = [];

                    foreach ($lote as $r) {
                        $valores[] = $placeholdersFila;
                        foreach ($this->campos as $campo) {
                            $params[] = $r[$campo] ?? null;
                        }
                        $params[] = $version;
                        $params[] = $creadoPor;
                    }

                    $sql = "INSERT INTO ventas_historicas ($listaCampos) VALUES " . implode(', ', $valores);
                    $this->pdo->prepare($sql)->execute($params);
                }

                $this->pdo->commit();
            } catch (Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }

            return count($registros);
        }

        /**
         * Cantidad total de registros (sin filtro).
         */
        public function contarTodos()
        {
            return (int) $this->pdo->query("SELECT COUNT(*) FROM ventas_historicas")->fetchColumn();
        }

        /**
         * Cantidad de registros que coinciden con los filtros (búsqueda, versión y año).
         */
        public function contarFiltrados($busqueda, $version, $anio)
        {
            list($where, $params) = $this->construirFiltro($busqueda, $version, $anio);

            if ($where === '') {
                return $this->contarTodos();
            }

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM ventas_historicas $where");
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }

        /**
         * Página de registros para DataTables (server-side). La búsqueda aplica a
         * cliente, artículo (descripción) y código de artículo; además filtra por
         * versión y por año.
         *
         * @param string $busqueda     Texto a buscar.
         * @param string $version      '' (todas) o la versión exacta.
         * @param string $anio         '' (todos) o el año.
         * @param string $columnaOrden Nombre lógico de la columna a ordenar.
         * @param string $dirOrden     'asc' o 'desc'.
         * @param int    $inicio       Offset.
         * @param int    $longitud     Cantidad (-1 = todos).
         */
        public function listarPagina($busqueda, $version, $anio, $columnaOrden, $dirOrden, $inicio, $longitud)
        {
            $columnasValidas = [
                'id'                   => 'id',
                'version'              => 'version',
                'fecha_docto'          => 'fecha_docto',
                'nro_docto'            => 'nro_docto',
                'razon_social'         => 'razon_social',
                'descripcion_articulo' => 'descripcion_articulo',
                'cant_docto'           => 'cant_docto',
                'venta_bruta'          => 'venta_bruta',
                'anio'                 => 'anio',
                'mes'                  => 'mes',
            ];
            $columna   = $columnasValidas[$columnaOrden] ?? 'id';
            $direccion = (strtolower($dirOrden) === 'asc') ? 'ASC' : 'DESC';

            $inicio   = max(0, (int) $inicio);
            $longitud = (int) $longitud;

            list($where, $params) = $this->construirFiltro($busqueda, $version, $anio);
            $limit = ($longitud < 0) ? '' : "LIMIT $inicio, $longitud";

            $sql = "SELECT id, version, fecha_docto, nro_docto, razon_social,
                           descripcion_articulo, cant_docto, venta_bruta, anio, mes
                    FROM ventas_historicas
                    $where
                    ORDER BY $columna $direccion
                    $limit";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        }

        /**
         * Arma el WHERE y los parámetros de los filtros de la consulta principal.
         *
         * @return array [string $where, array $params]
         */
        private function construirFiltro($busqueda, $version, $anio)
        {
            $condiciones = [];
            $params      = [];

            if ($busqueda !== '') {
                $condiciones[] = '(razon_social LIKE ? OR descripcion_articulo LIKE ? OR cod_articulo LIKE ?)';
                $like = '%' . $busqueda . '%';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }

            if ($version !== '') {
                $condiciones[] = 'version = ?';
                $params[]      = $version;
            }

            if ($anio !== '' && ctype_digit((string) $anio)) {
                $condiciones[] = 'anio = ?';
                $params[]      = (int) $anio;
            }

            $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

            return [$where, $params];
        }

        /**
         * Versiones distintas existentes (para el filtro), de la más nueva a la más vieja.
         *
         * @return array Lista de strings de versión.
         */
        public function versionesDisponibles()
        {
            return $this->pdo
                ->query("SELECT DISTINCT version FROM ventas_historicas WHERE version IS NOT NULL ORDER BY version DESC")
                ->fetchAll(PDO::FETCH_COLUMN);
        }

        /**
         * Años distintos existentes (para el filtro), del más nuevo al más viejo.
         *
         * @return array Lista de años.
         */
        public function aniosDisponibles()
        {
            return $this->pdo
                ->query("SELECT DISTINCT anio FROM ventas_historicas WHERE anio IS NOT NULL ORDER BY anio DESC")
                ->fetchAll(PDO::FETCH_COLUMN);
        }
    }
