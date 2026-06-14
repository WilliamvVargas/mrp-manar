<?php

    /*
     * Modelo de acceso a datos de la tabla `forecast`.
     *
     * Centraliza las consultas SQL del forecast. Se instancia pasándole una
     * conexión PDO, igual que el modelo de usuarios.
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
         * Inserta masivamente un conjunto de registros dentro de una transacción
         * (todo o nada): si alguno falla, se revierten todos.
         *
         * Cada registro debe traer las claves: empresa, version, codigo_cliente,
         * nombre_cliente, fecha, codigo_producto, nombre_producto, cantidad.
         *
         * @param array       $registros Lista de registros a insertar.
         * @param string|null $creadoPor Id del usuario que realiza la carga (auditoría).
         * @return int Cantidad de registros insertados.
         */
        public function insertarMasivo(array $registros, $creadoPor = null)
        {
            $sql = "INSERT INTO forecast (empresa,
                                         version,
                                         codigo_cliente,
                                         nombre_cliente,
                                         fecha,
                                         codigo_producto,
                                         nombre_producto,
                                         cantidad,
                                         created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);

            $this->pdo->beginTransaction();

            try {
                foreach ($registros as $r) {
                    $stmt->execute([
                        $r['empresa'],
                        $r['version'],
                        $r['codigo_cliente'],
                        $r['nombre_cliente'],
                        $r['fecha'],
                        $r['codigo_producto'],
                        $r['nombre_producto'],
                        $r['cantidad'],
                        $creadoPor,
                    ]);
                }

                $this->pdo->commit();
            } catch (Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }

            return count($registros);
        }
    }
