<?php

    /*
     * Consulta de productos del catálogo SAP (sobre las tablas espejo en MySQL).
     *
     * Trabaja contra `sap_productos_maestros` y `sap_familias`, que se mantienen frescas
     * con el helper SapSync (ver models/sap_sync_model.php). No toca SQL Server.
     */
    class SapProducto
    {
        /** @var PDO Conexión a MySQL. */
        private $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        /**
         * Productos de una familia del negocio, buscando por NOMBRE de familia. La familia
         * del presupuesto es texto; se resuelve contra el catálogo `sap_familias` (espejo
         * del UDF U_Familia / UFD1) para obtener su código, y con ese código se traen los
         * productos cuyo OITM.U_Familia coincide. La colación utf8mb4_general_ci hace el
         * match de nombre case-insensitive.
         *
         * Devuelve [] si la familia no existe en SAP o no tiene productos.
         *
         * @param  string $familiaNombre Nombre de la familia (ej. "Chocolatería").
         * @return array  Lista de ['producto_codigo', 'producto_nombre', 'producto_activo'].
         */
        public function porFamiliaNombre($familiaNombre)
        {
            $sql = "SELECT p.producto_codigo,
                           p.producto_nombre,
                           p.producto_activo
                    FROM sap_familias f
                    INNER JOIN sap_productos_maestros p
                        ON p.producto_familia_codigo = f.familia_codigo
                    WHERE f.familia_nombre = ?
                    ORDER BY p.producto_nombre";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([trim($familiaNombre)]);

            return $stmt->fetchAll();
        }
    }
