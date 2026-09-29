<?php
    /*
     * Modelo del Forecast Personalizado (cargado a mano por el usuario).
     * Guarda en la tabla forecast_x_producto_custom, con upsert por
     * (empresa_id, producto_codigo, semana_inicio): una recarga del mismo
     * producto/semana REESCRIBE el registro.
     */
    class ForecastCustom
    {
        private $pdo;
        private $empresaId;

        public function __construct(PDO $pdo, $empresaId = null)
        {
            $this->pdo       = $pdo;
            $this->empresaId = $empresaId;
        }

        /**
         * Nombres de producto conocidos (desde forecast_x_producto de la misma empresa) para un
         * conjunto de códigos. Sirve para rellenar producto_nombre; los códigos nuevos (sin
         * forecast previo) quedan sin nombre (null).
         *
         * @param string[] $codigos
         * @return array [producto_codigo => producto_nombre]
         */
        public function nombresPorCodigo(array $codigos)
        {
            $codigos = array_values(array_unique(array_filter(array_map('trim', $codigos), 'strlen')));
            if (empty($codigos)) { return []; }

            $ph  = implode(',', array_fill(0, count($codigos), '?'));
            $sql = "SELECT producto_codigo, MAX(producto_nombre) AS nombre
                    FROM forecast_x_producto
                    WHERE empresa_id " . ($this->empresaId === null ? "IS NULL" : "= ?") . "
                      AND producto_codigo IN ($ph)
                    GROUP BY producto_codigo";

            $params = ($this->empresaId === null) ? $codigos : array_merge([$this->empresaId], $codigos);
            $stmt   = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $mapa = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $mapa[$r['producto_codigo']] = $r['nombre'];
            }
            return $mapa;
        }

        /**
         * Resumen del forecast personalizado cargado, AGRUPADO por producto (empresa activa):
         * código, nombre y cantidad de semanas/registros cargados.
         *
         * @return array Filas ['producto_codigo', 'producto_nombre', 'cantidad'].
         */
        public function resumenPorProducto()
        {
            $sql = "SELECT producto_codigo,
                           MAX(producto_nombre) AS producto_nombre,
                           COUNT(*)             AS cantidad
                    FROM forecast_x_producto_custom
                    WHERE empresa_id " . ($this->empresaId === null ? "IS NULL" : "= ?") . "
                    GROUP BY producto_codigo
                    ORDER BY producto_codigo";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($this->empresaId === null ? [] : [$this->empresaId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /**
         * Detalle del forecast personalizado de UN producto (empresa activa): una fila por semana.
         *
         * @return array Filas ['semana_inicio' (Y-m-d), 'demanda_forecast'].
         */
        public function detallePorProducto($codigo)
        {
            $sql = "SELECT semana_inicio, demanda_forecast
                    FROM forecast_x_producto_custom
                    WHERE empresa_id " . ($this->empresaId === null ? "IS NULL" : "= ?") . "
                      AND producto_codigo = ?
                    ORDER BY semana_inicio";
            $params = ($this->empresaId === null) ? [trim($codigo)] : [$this->empresaId, trim($codigo)];
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /**
         * Elimina UN registro (una semana) del forecast personalizado de un producto en la empresa
         * activa. Devuelve cuántas filas se borraron (0 o 1).
         */
        public function eliminarLinea($codigo, $semana)
        {
            $sql = "DELETE FROM forecast_x_producto_custom
                    WHERE empresa_id " . ($this->empresaId === null ? "IS NULL" : "= ?") . "
                      AND producto_codigo = ? AND semana_inicio = ?";
            $params = ($this->empresaId === null)
                ? [trim($codigo), $semana]
                : [$this->empresaId, trim($codigo), $semana];
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        }

        /**
         * Elimina TODO el forecast personalizado de un producto (todas sus semanas) en la empresa
         * activa. Devuelve cuántas filas se borraron.
         */
        public function eliminarPorProducto($codigo)
        {
            $sql = "DELETE FROM forecast_x_producto_custom
                    WHERE empresa_id " . ($this->empresaId === null ? "IS NULL" : "= ?") . "
                      AND producto_codigo = ?";
            $params = ($this->empresaId === null) ? [trim($codigo)] : [$this->empresaId, trim($codigo)];
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        }

        /**
         * Inserta/actualiza (upsert) los registros del forecast personalizado en una transacción.
         * Cada registro: ['producto_codigo', 'semana_inicio' (Y-m-d, lunes ISO), 'demanda_forecast'].
         * producto_nombre se completa por lookup (o null si el producto es nuevo).
         *
         * @return int cantidad de filas procesadas
         */
        public function guardarMasivo(array $registros)
        {
            if (empty($registros)) { return 0; }

            $nombres = $this->nombresPorCodigo(array_column($registros, 'producto_codigo'));

            $sql = "INSERT INTO forecast_x_producto_custom
                        (empresa_id, producto_codigo, producto_nombre, semana_inicio, demanda_forecast)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        producto_nombre  = VALUES(producto_nombre),
                        demanda_forecast = VALUES(demanda_forecast)";

            $this->pdo->beginTransaction();
            try {
                $stmt = $this->pdo->prepare($sql);
                $n = 0;
                foreach ($registros as $r) {
                    $cod = trim($r['producto_codigo']);
                    $stmt->execute([
                        $this->empresaId,
                        $cod,
                        $nombres[$cod] ?? null,
                        $r['semana_inicio'],
                        $r['demanda_forecast'],
                    ]);
                    $n++;
                }
                $this->pdo->commit();
                return $n;
            } catch (Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }
    }
?>
