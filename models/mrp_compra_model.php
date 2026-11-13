<?php
    /*
     * Modelo de las COMPRAS registradas desde el Pronóstico de Compra (tabla `mrp_compras`).
     *
     * Una compra se registra con el botón "Comprar" de la ficha del producto, en la semana
     * actual, y queda PENDIENTE hasta que el comprador la cierra a mano:
     *   - 'oc_emitida': ya se emitió la OC en SAP (opcionalmente con su número). Desde ahí
     *                   manda la OC de SAP (En Pedido) y la compra deja de contar en el MRP.
     *   - 'anulada'   : se descartó la compra.
     * Mientras está pendiente, el MRP la trata como un pedido sugerido en camino que llega en
     * hoy + lead time (el lead time empieza recién con la OC): se descuenta del sugerido y
     * bloquea un pedido nuevo. Solo puede haber UNA compra pendiente por empresa y producto.
     */
    class MrpCompra
    {
        /** @var PDO */
        private $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        /**
         * Compras PENDIENTES de una empresa, indexadas por código de producto.
         *
         * @return array [codigo => ['id','cantidad','cantidad_sugerida','observacion','fecha',
         *                           'dias','usuario']]
         */
        public function pendientesPorProducto($empresaId)
        {
            $stmt = $this->pdo->prepare(
                "SELECT c.id, c.producto_codigo, c.cantidad, c.cantidad_sugerida, c.observacion,
                        DATE_FORMAT(c.created_at, '%d/%m/%Y %H:%i') AS fecha,
                        DATEDIFF(CURDATE(), DATE(c.created_at))    AS dias,
                        u.usuario
                 FROM mrp_compras c
                 LEFT JOIN usuarios u ON u.id = c.created_by
                 WHERE c.empresa_id = ? AND c.estado = 'pendiente'"
            );
            $stmt->execute([$empresaId]);
            $res = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $res[trim($r['producto_codigo'])] = $r;
            }
            return $res;
        }

        /**
         * Compras PENDIENTES de una empresa para la lista simple de compras (export "Comprados"):
         * una fila por compra, ordenada por proveedor, producto y cantidad. Los proveedores sin
         * asignar quedan al final. No cruza el MRP: sale directo de la tabla.
         *
         * @return array Filas ['proveedor','producto_codigo','producto_nombre','cantidad'].
         */
        public function listarPendientes($empresaId)
        {
            $stmt = $this->pdo->prepare(
                "SELECT proveedor, producto_codigo, producto_nombre, cantidad
                 FROM mrp_compras
                 WHERE empresa_id = ? AND estado = 'pendiente'
                 ORDER BY (proveedor IS NULL OR proveedor = ''), proveedor, producto_codigo, cantidad"
            );
            $stmt->execute([$empresaId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /**
         * ¿El producto ya tiene una compra pendiente en la empresa?
         */
        public function tienePendiente($empresaId, $productoCodigo)
        {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM mrp_compras WHERE empresa_id = ? AND producto_codigo = ? AND estado = 'pendiente' LIMIT 1"
            );
            $stmt->execute([$empresaId, trim($productoCodigo)]);
            return (bool) $stmt->fetchColumn();
        }

        /**
         * Registra una compra pendiente.
         *
         * @return bool
         */
        public function crear($empresaId, $productoCodigo, $productoNombre, $proveedor, $cantidad,
                              $cantidadSugerida, $leadTimeSem, $observacion, $creadoPor)
        {
            $stmt = $this->pdo->prepare(
                "INSERT INTO mrp_compras (empresa_id, producto_codigo, producto_nombre, proveedor, cantidad,
                                          cantidad_sugerida, lead_time_sem, observacion, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            return $stmt->execute([
                $empresaId, trim($productoCodigo), $productoNombre, $proveedor, (int) $cantidad,
                ($cantidadSugerida === null) ? null : (int) $cantidadSugerida,
                ($leadTimeSem === null) ? null : (int) $leadTimeSem,
                ($observacion === '') ? null : $observacion, $creadoPor,
            ]);
        }

        /**
         * Cierra una compra PENDIENTE de la empresa: 'oc_emitida' (con N° de OC opcional) o 'anulada'.
         *
         * @return bool true si se cerró (false si no existe, es de otra empresa o ya estaba cerrada).
         */
        public function cerrar($id, $empresaId, $estado, $numeroOc, $cerradoPor)
        {
            $stmt = $this->pdo->prepare(
                "UPDATE mrp_compras
                 SET estado = ?, numero_oc = ?, fecha_cierre = NOW(), cerrado_por = ?
                 WHERE id = ? AND empresa_id = ? AND estado = 'pendiente'"
            );
            $stmt->execute([$estado, ($numeroOc === '') ? null : $numeroOc, $cerradoPor, (int) $id, $empresaId]);
            return $stmt->rowCount() > 0;
        }
    }
?>
