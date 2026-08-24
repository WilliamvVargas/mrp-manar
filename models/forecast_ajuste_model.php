<?php

    /*
     * Modelo de la tabla `forecast_ajustes`: ajustes MANUALES de cantidad por
     * producto/semana (ISO), acotados a la empresa activa.
     *
     * Es una tabla independiente del forecast: la explosión de forecast NO la toca, así
     * el ajuste manual sobrevive a las re-proyecciones. Se liga por su clave natural
     * (empresa + producto + semana ISO) y se muestra sobre la semana correspondiente.
     */
    class ForecastAjuste
    {
        /** Máximo de la columna int(10) unsigned. */
        const MAX_CANTIDAD = 4294967295;

        /** @var PDO */
        private $pdo;

        /** @var string|null Empresa activa: todo ajuste se filtra y se estampa por ella. */
        private $empresaId;

        public function __construct(PDO $pdo, $empresaId = null)
        {
            $this->pdo       = $pdo;
            $this->empresaId = $empresaId;
        }

        /**
         * Ajustes de un producto (de la empresa activa) como mapa semana_inicio => cantidad.
         * La clave es el lunes ISO ('yyyy-MM-dd'), para cruzarlo con el detalle del gráfico.
         *
         * @return array<string,int>
         */
        public function mapaPorProducto($productoCodigo)
        {
            $stmt = $this->pdo->prepare(
                "SELECT semana_inicio, cantidad_ajustada
                 FROM forecast_ajustes
                 WHERE empresa_id = ? AND producto_codigo = ?"
            );
            $stmt->execute([$this->empresaId, $productoCodigo]);

            $mapa = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $mapa[(string) $r['semana_inicio']] = (int) $r['cantidad_ajustada'];
            }
            return $mapa;
        }

        /**
         * Upsert del ajuste de una semana (un registro por empresa+producto+semana ISO).
         *
         * @param string      $productoCodigo Código del producto.
         * @param int         $isoYear        Año ISO de la semana.
         * @param int         $isoWeek        Semana ISO (1-53).
         * @param string      $semanaInicio   Lunes ISO ('yyyy-MM-dd').
         * @param int         $cantidad       Cantidad ajustada (>= 0).
         * @param string|null $usuarioId      Id del usuario (auditoría).
         */
        public function guardar($productoCodigo, $isoYear, $isoWeek, $semanaInicio, $cantidad, $usuarioId = null)
        {
            $sql = "INSERT INTO forecast_ajustes
                        (empresa_id, producto_codigo, iso_year, iso_week, semana_inicio,
                         cantidad_ajustada, created_by, updated_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        cantidad_ajustada = VALUES(cantidad_ajustada),
                        semana_inicio     = VALUES(semana_inicio),
                        updated_by        = VALUES(updated_by)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $this->empresaId, $productoCodigo, $isoYear, $isoWeek, $semanaInicio,
                $cantidad, $usuarioId, $usuarioId,
            ]);

            return true;
        }
    }
