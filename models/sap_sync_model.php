<?php

    /*
     * Helper de sincronización de tablas "espejo" de SAP en MySQL.
     *
     * La base SAP vive en SQL Server (solo lectura). Para poder hacer consultas 100% MySQL
     * (joins, group by, subconsultas...) replicamos a MySQL, con prefijo `sap_`, solo las
     * columnas que usamos. Antes de una consulta, se llama a `asegurar('sap_xxx')`: si el
     * espejo nunca se pobló o venció el TTL, se refresca desde SAP; si está vigente, no se
     * hace nada y se usa lo que ya está cargado.
     *
     * Uso típico en un controlador:
     *     require_once 'config/conexion.php';            // $pdo (MySQL destino)
     *     require_once 'config/conexion_sqlserver.php';  // $pdoSqlsrv (SAP origen)
     *     require_once 'models/sap_sync_model.php';
     *
     *     $sap = new SapSync($pdoSqlsrv, $pdo);
     *     $sap->asegurar('sap_productos_maestros');
     *     // ...ahora la consulta MySQL puede hacer JOIN a sap_productos_maestros.
     *
     * Agregar un nuevo espejo = sumar su definición en $definiciones (SELECT en SAP +
     * columnas destino) y crear su tabla en sql/migracion_sap_espejo.sql.
     */
    class SapSync
    {
        /** Vigencia por defecto del espejo, en minutos: si se pobló hace más de esto, se refresca. */
        const TTL_MINUTOS = 15;

        /** @var PDO Conexión a SQL Server (origen: SAP). */
        private $sap;

        /** @var PDO Conexión a MySQL (destino: tablas espejo). */
        private $mysql;

        /** @var int Minutos de vigencia del espejo antes de refrescar. */
        private $ttlMinutos;

        /**
         * Definiciones de cada tabla espejo:
         *   'tabla_destino' => [
         *       'origen'   => SELECT a ejecutar en SAP (los alias = nombres de columna destino),
         *       'columnas' => columnas de la tabla destino, EN EL MISMO ORDEN que el SELECT.
         *   ]
         */
        private $definiciones = [
            'sap_productos_maestros' => [
                'origen'   => 'SELECT ItemCode AS producto_codigo, ItemName AS producto_nombre FROM OITM',
                'columnas' => ['producto_codigo', 'producto_nombre'],
            ],
        ];

        public function __construct(PDO $sap, PDO $mysql, $ttlMinutos = self::TTL_MINUTOS)
        {
            $this->sap        = $sap;
            $this->mysql      = $mysql;
            $this->ttlMinutos = (int) $ttlMinutos;
        }

        /**
         * Asegura que la tabla espejo esté fresca: la repuebla desde SAP si nunca se
         * sincronizó o si venció el TTL; si está vigente, no hace nada.
         *
         * @param  string $tabla  Nombre de la tabla espejo (debe existir en $definiciones).
         * @return bool   true si se refrescó, false si se usó la copia vigente.
         */
        public function asegurar($tabla)
        {
            if (!isset($this->definiciones[$tabla])) {
                throw new InvalidArgumentException("Tabla espejo no definida en SapSync: {$tabla}");
            }

            if ($this->estaVigente($tabla)) {
                return false;
            }

            $def   = $this->definiciones[$tabla];
            $filas = $this->reemplazar($def['origen'], $tabla, $def['columnas']);
            $this->registrarSync($tabla, $filas);

            return true;
        }

        /**
         * ¿El espejo se sincronizó dentro de la ventana del TTL?
         * La comparación usa NOW() de MySQL (no de PHP) para evitar desfases de reloj.
         */
        private function estaVigente($tabla)
        {
            $sql = "SELECT 1
                    FROM sap_sync_log
                    WHERE tabla = ?
                      AND ultima_sync > (NOW() - INTERVAL " . (int) $this->ttlMinutos . " MINUTE)";

            $stmt = $this->mysql->prepare($sql);
            $stmt->execute([$tabla]);

            return (bool) $stmt->fetchColumn();
        }

        /**
         * Limpieza + llenado genérico de una tabla espejo: lee del origen (SAP) y reemplaza
         * todo el contenido en MySQL (DELETE + INSERT por lotes) dentro de una transacción.
         * Si algo falla, se hace rollback y queda la información anterior intacta.
         *
         * @return int Cantidad de filas insertadas.
         */
        public function reemplazar($sqlOrigen, $tablaDestino, array $columnas, $lote = 500)
        {
            // FETCH_NUM: nos basta el orden de columnas (coincide con $columnas) y es más liviano.
            $datos = $this->sap->query($sqlOrigen)->fetchAll(PDO::FETCH_NUM);

            $this->mysql->beginTransaction();
            try {
                $this->mysql->exec("DELETE FROM `{$tablaDestino}`");

                if (!empty($datos)) {
                    $cols     = '`' . implode('`, `', $columnas) . '`';
                    $plantFil = '(' . implode(', ', array_fill(0, count($columnas), '?')) . ')';

                    foreach (array_chunk($datos, $lote) as $bloque) {
                        $placeholders = implode(', ', array_fill(0, count($bloque), $plantFil));
                        $sql = "INSERT INTO `{$tablaDestino}` ({$cols}) VALUES {$placeholders}";

                        $valores = [];
                        foreach ($bloque as $fila) {
                            foreach ($fila as $valor) {
                                $valores[] = $valor;
                            }
                        }

                        $this->mysql->prepare($sql)->execute($valores);
                    }
                }

                $this->mysql->commit();
            } catch (Throwable $e) {
                $this->mysql->rollBack();
                throw $e;
            }

            return count($datos);
        }

        /** Registra/actualiza en el log la última sincronización de la tabla. */
        private function registrarSync($tabla, $filas)
        {
            $sql = "INSERT INTO sap_sync_log (tabla, 
                                              ultima_sync, 
                                              filas)
                    VALUES (?, NOW(), ?)
                    ON DUPLICATE KEY UPDATE ultima_sync = NOW(), filas = VALUES(filas)";

            $this->mysql->prepare($sql)->execute([$tabla, $filas]);
        }
    }
