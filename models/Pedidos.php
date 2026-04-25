<?php
/**
 * Modelo Pedidos
 * 
 * Adaptado del modelo/pedidos.php original.
 * 
 * IMPORTANTE — ESTRUCTURA DE LA TABLA:
 * Cada fila de `pedidos` es una LÍNEA de item, no una orden completa.
 * Una "compra" son varias filas que comparten el mismo IdPedido.
 * Clave compuesta: (IdPedido, IdProducto)
 * 
 * Columnas:
 *   IdProducto  INT   → FK a productos
 *   Cantidad    INT
 *   Total       DECIMAL  (subtotal de la línea = precio * cantidad)
 *   Fecha       DATETIME
 *   IdPedido    INT   → agrupa líneas de la misma compra
 *   id_usuario  INT   → FK a usuarios
 * 
 * Mejora sobre el original: `crearCompra()` usa transacción + MAX+1
 * para garantizar que una compra = un IdPedido único.
 * El original no asignaba IdPedido al insertar (quedaba NULL).
 */

require_once __DIR__ . '/../config/database.php';

class Pedidos extends Conexion {

    public $IdPedido;
    public $IdProducto;
    public $Cantidad;
    public $Total;
    public $Fecha;
    public $id_usuario;

    public function __construct($data = array()) {
        parent::__construct();
        if (!empty($data)) {
            $this->IdPedido   = $data['IdPedido']   ?? null;
            $this->IdProducto = $data['IdProducto'] ?? null;
            $this->Cantidad   = $data['Cantidad']   ?? null;
            $this->Total      = $data['Total']      ?? null;
            $this->Fecha      = $data['Fecha']      ?? null;
            $this->id_usuario = $data['id_usuario'] ?? null;
        }
    }

    public function __destruct() {
        $this->Disconnect();
    }

    /**
     * Crea una compra completa: varios items que comparten un mismo IdPedido.
     * Usa transacción para que o se insertan todos los items o ninguno.
     * 
     * @param array $items Array de items: cada uno {IdProducto, Cantidad, Precio}
     * @param int $idUsuario
     * @return int El IdPedido generado
     */
    public static function crearCompra($items, $idUsuario) {
        $tmp = new Pedidos();
        $tmp->datab->beginTransaction();

        try {
            // Calcular el siguiente IdPedido (el original usaba un método similar)
            $row = $tmp->getRow("SELECT COALESCE(MAX(IdPedido), 0) + 1 AS nuevo FROM pedidos");
            $nuevoIdPedido = (int)$row['nuevo'];

            $fecha = date('Y-m-d H:i:s');

            foreach ($items as $item) {
                $cantidad = (int)$item['Cantidad'];
                $precio   = (float)$item['Precio'];
                $total    = $precio * $cantidad;

                $tmp->insertRow(
                    "INSERT INTO pedidos (IdProducto, Cantidad, Total, Fecha, id_usuario)
                     VALUES (?, ?, ?, ?, ?)",
                    array(
                        $item['IdProducto'],
                        $cantidad,
                        $total,
                        $fecha,
                        $idUsuario,
                    )
                );
            }

            $tmp->datab->commit();
            $tmp->Disconnect();
            return $nuevoIdPedido;
        } catch (Exception $e) {
            $tmp->datab->rollBack();
            $tmp->Disconnect();
            throw $e;
        }
    }

    // READ — todos los pedidos (admin), con nombre del producto
    public static function getAll() {
        $tmp = new Pedidos();
        $rows = $tmp->getRows(
            "SELECT p.IdPedido, p.IdProducto, p.Cantidad, p.Total, p.Fecha, p.id_usuario,
                    pr.Producto AS nombre_producto
             FROM pedidos p
             LEFT JOIN productos pr ON p.IdProducto = pr.IdProducto
             ORDER BY p.IdPedido DESC, p.IdProducto"
        );
        $tmp->Disconnect();
        return $rows;
    }

    // READ — una línea por (IdPedido, IdProducto)
    public static function getByClave($idPedido, $idProducto) {
        $tmp = new Pedidos();
        $row = $tmp->getRow(
            "SELECT p.*, pr.Producto AS nombre_producto
             FROM pedidos p
             LEFT JOIN productos pr ON p.IdProducto = pr.IdProducto
             WHERE p.IdPedido = ? AND p.IdProducto = ?",
            array($idPedido, $idProducto)
        );
        $tmp->Disconnect();
        return $row ?: null;
    }

    // READ — historial de un usuario (con JOIN para traer nombre del producto)
    public static function buscarPorUsuario($idUsuario) {
        $tmp = new Pedidos();
        $rows = $tmp->getRows(
            "SELECT p.IdPedido, p.IdProducto, p.Cantidad, p.Total, p.Fecha,
                    pr.Producto AS nombre_producto
             FROM pedidos p
             LEFT JOIN productos pr ON p.IdProducto = pr.IdProducto
             WHERE p.id_usuario = ?
             ORDER BY p.Fecha DESC",
            array($idUsuario)
        );
        $tmp->Disconnect();
        return $rows;
    }

    // UPDATE — editar una línea
    public function editar() {
        $sql = "UPDATE pedidos
                SET Cantidad = ?, Total = ?, Fecha = ?
                WHERE IdPedido = ? AND IdProducto = ?";
        return $this->updateRow($sql, array(
            $this->Cantidad,
            $this->Total,
            $this->Fecha,
            $this->IdPedido,
            $this->IdProducto,
        ));
    }

    // DELETE — eliminar una línea
    public function eliminar() {
        $sql = "DELETE FROM pedidos WHERE IdPedido = ? AND IdProducto = ?";
        return $this->updateRow($sql, array($this->IdPedido, $this->IdProducto));
    }
}
