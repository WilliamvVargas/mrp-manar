<?php

    /*
     * Modelo de acceso a datos de la tabla `forecast_configuracion`.
     * Cada fila es una configuración con nombre (único POR EMPRESA) + los tres interruptores de
     * limpieza del forecast (imputar censura / capar outliers / ensamble). Multi-empresa: se aísla
     * por empresa_id. De momento SOLO lo usa su mantenedor; no está conectado al pipeline.
     */
    class ForecastConfig
    {
        /** @var PDO */
        private $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        /** Total de configuraciones de la empresa (sin filtro). */
        public function contarTodos($empresaId)
        {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM forecast_configuracion WHERE empresa_id <=> ?");
            $stmt->execute([$empresaId]);
            return (int) $stmt->fetchColumn();
        }

        /** Total de configuraciones que pasan el filtro de búsqueda (por nombre). */
        public function contarFiltrados($empresaId, $consulta)
        {
            $sql    = "SELECT COUNT(*) FROM forecast_configuracion WHERE empresa_id <=> ?";
            $params = [$empresaId];
            if ($consulta !== '') { $sql .= " AND nombre LIKE ?"; $params[] = '%' . $consulta . '%'; }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        }

        /**
         * Página de configuraciones para la tabla principal (DataTables server-side), por empresa.
         *
         * @return array
         */
        public function listarPagina($empresaId, $consulta, $columnaOrden, $dirOrden, $inicio, $longitud)
        {
            $columnasValidas = [
                'nombre'          => 'nombre',
                'imputar_censura' => 'imputar_censura',
                'capar_outliers'  => 'capar_outliers',
                'ensamble'        => 'ensamble',
                'estabilizar_poco_historico' => 'estabilizar_poco_historico',
            ];
            $col = $columnasValidas[$columnaOrden] ?? 'nombre';
            $dir = (strtolower($dirOrden) === 'desc') ? 'DESC' : 'ASC';

            $sql    = "SELECT id, nombre, imputar_censura, capar_outliers, capar_k, ensamble, ensamble_peso_prophet,
                              estabilizar_poco_historico, estabilizar_n_semanas
                       FROM forecast_configuracion WHERE empresa_id <=> ?";
            $params = [$empresaId];
            if ($consulta !== '') { $sql .= " AND nombre LIKE ?"; $params[] = '%' . $consulta . '%'; }
            $sql .= " ORDER BY $col $dir LIMIT " . (int) $inicio . ', ' . (int) $longitud;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        }

        /**
         * Devuelve una configuración por id (acotada a su empresa), para el formulario de edición.
         *
         * @param int         $id
         * @param string|null $empresaId
         * @return array|false
         */
        public function buscarPorId($id, $empresaId)
        {
            $stmt = $this->pdo->prepare(
                "SELECT id, nombre, imputar_censura, capar_outliers, capar_k, ensamble, ensamble_peso_prophet,
                        estabilizar_poco_historico, estabilizar_n_semanas
                 FROM forecast_configuracion
                 WHERE id = ? AND empresa_id <=> ?"
            );
            $stmt->execute([(int) $id, $empresaId]);

            return $stmt->fetch();
        }

        /**
         * Inserta una configuración.
         *
         * @return int Id creado.
         */
        public function crear($empresaId, $nombre, $imputarCensura, $caparOutliers, $caparK, $ensamble, $ensamblePesoProphet, $estabilizarPocoHistorico, $estabilizarNSemanas, $creadoPor = null)
        {
            $stmt = $this->pdo->prepare(
                "INSERT INTO forecast_configuracion
                    (empresa_id, nombre, imputar_censura, capar_outliers, capar_k, ensamble, ensamble_peso_prophet,
                     estabilizar_poco_historico, estabilizar_n_semanas, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $empresaId,
                $nombre,
                $imputarCensura ? 1 : 0,
                $caparOutliers ? 1 : 0,
                $caparK,
                $ensamble ? 1 : 0,
                $ensamblePesoProphet,
                $estabilizarPocoHistorico ? 1 : 0,
                $estabilizarNSemanas,
                $creadoPor,
            ]);

            return (int) $this->pdo->lastInsertId();
        }

        /**
         * Actualiza una configuración (acotada a su empresa).
         *
         * @return int Filas afectadas.
         */
        public function actualizar($id, $empresaId, $nombre, $imputarCensura, $caparOutliers, $caparK, $ensamble, $ensamblePesoProphet, $estabilizarPocoHistorico, $estabilizarNSemanas, $actualizadoPor = null)
        {
            $stmt = $this->pdo->prepare(
                "UPDATE forecast_configuracion
                 SET nombre = ?, imputar_censura = ?, capar_outliers = ?, capar_k = ?, ensamble = ?, ensamble_peso_prophet = ?,
                     estabilizar_poco_historico = ?, estabilizar_n_semanas = ?, updated_by = ?
                 WHERE id = ? AND empresa_id <=> ?"
            );
            $stmt->execute([
                $nombre,
                $imputarCensura ? 1 : 0,
                $caparOutliers ? 1 : 0,
                $caparK,
                $ensamble ? 1 : 0,
                $ensamblePesoProphet,
                $estabilizarPocoHistorico ? 1 : 0,
                $estabilizarNSemanas,
                $actualizadoPor,
                (int) $id,
                $empresaId,
            ]);

            return $stmt->rowCount();
        }

        /**
         * Elimina una configuración (acotada a su empresa).
         *
         * @return int Filas eliminadas.
         */
        public function eliminar($id, $empresaId)
        {
            $stmt = $this->pdo->prepare(
                "DELETE FROM forecast_configuracion WHERE id = ? AND empresa_id <=> ?"
            );
            $stmt->execute([(int) $id, $empresaId]);

            return $stmt->rowCount();
        }

        /**
         * ¿Existe una configuración con ese nombre EN ESA EMPRESA? (case-insensitive). Permite
         * excluir un id (para la edición). Lo usa la validación de unicidad.
         *
         * @return bool
         */
        public function existeNombre($empresaId, $nombre, $exceptoId = null)
        {
            $sql    = "SELECT COUNT(*) FROM forecast_configuracion WHERE empresa_id <=> ? AND nombre = ?";
            $params = [$empresaId, trim($nombre)];

            if ($exceptoId !== null && ctype_digit((string) $exceptoId)) {
                $sql     .= " AND id <> ?";
                $params[] = (int) $exceptoId;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (int) $stmt->fetchColumn() > 0;
        }
    }
