<?php
/**
 * Modelo Productos
 * 
 * Adaptado del modelo/productos.php del proyecto original.
 * Cambios:
 *   - Extiende de Conexion (nuevo nombre, antes era 'conexion' minúscula)
 *   - Se removieron los métodos abstractos sin uso (buscar, selectAll)
 *   - insertar() devuelve el ID autogenerado (antes no devolvía nada)
 *   - Se agregó toArray() para serializar a JSON fácilmente
 * 
 * Tabla `productos` en la DB:
 *   IdProducto INT PRIMARY KEY AUTO_INCREMENT
 *   Producto   VARCHAR(100)
 *   Precio     DECIMAL(10,2)
 */

require_once __DIR__ . '/../config/database.php';

class Productos extends Conexion {

    public $IdProducto;
    public $Producto;
    public $Precio;

    public function __construct($data = array()) {
        parent::__construct();
        if (!empty($data)) {
            $this->IdProducto = $data['IdProducto'] ?? null;
            $this->Producto   = $data['Producto']   ?? null;
            $this->Precio     = $data['Precio']     ?? null;
        }
    }

    public function __destruct() {
        $this->Disconnect();
    }

    // CREATE
    // Acepta tanto IdProducto manual (como el original) como autoincrement.
    // Si se provee IdProducto lo usa; si no, deja que MySQL lo genere.
    public function insertar() {
        if ($this->IdProducto !== null && $this->IdProducto !== '') {
            $sql = "INSERT INTO productos (IdProducto, Producto, Precio) VALUES (?, ?, ?)";
            $this->insertRow($sql, array(
                $this->IdProducto,
                $this->Producto,
                $this->Precio,
            ));
        } else {
            $sql = "INSERT INTO productos (Producto, Precio) VALUES (?, ?)";
            $this->IdProducto = $this->insertRow($sql, array(
                $this->Producto,
                $this->Precio,
            ));
        }
        return $this->IdProducto;
    }

    // READ — todos
    public static function getAll() {
        $tmp  = new Productos();
        $rows = $tmp->getRows("SELECT * FROM productos ORDER BY IdProducto ASC");
        $tmp->Disconnect();
        return $rows;
    }

    // READ — uno por ID
    public static function getById($id) {
        $tmp  = new Productos();
        $row  = $tmp->getRow("SELECT * FROM productos WHERE IdProducto = ?", array($id));
        $tmp->Disconnect();
        return $row ?: null;
    }

    // UPDATE
    public function editar() {
        $sql = "UPDATE productos SET Producto = ?, Precio = ? WHERE IdProducto = ?";
        return $this->updateRow($sql, array(
            $this->Producto,
            $this->Precio,
            $this->IdProducto,
        ));
    }

    // DELETE
    public function eliminar() {
        $sql = "DELETE FROM productos WHERE IdProducto = ?";
        return $this->updateRow($sql, array($this->IdProducto));
    }

    // Helper: serialización a array plano (útil para json_encode)
    public function toArray() {
        return [
            'IdProducto' => $this->IdProducto,
            'Producto'   => $this->Producto,
            'Precio'     => $this->Precio,
        ];
    }
}
