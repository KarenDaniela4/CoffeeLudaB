<?php
/**
 * Clase base de conexión a la base de datos MySQL.
 * 
 * Adaptada del original modelo/conexion.php del proyecto PHP puro.
 * Cambios principales:
 *   - Se removieron los métodos abstractos `buscar`, `selectAll`, etc. que no se usaban
 *     realmente en los modelos hijos (solo estaban como stubs vacíos).
 *   - Se mantiene la interfaz pública: getRow, getRows, insertRow, updateRow, deleteRow.
 *   - Los modelos hijos (usuarios, productos, pedidos) se mantienen casi idénticos.
 * 
 * NOTA: En producción, las credenciales NO deben ir hardcodeadas.
 * Usar variables de entorno con getenv() o un archivo .env.
 */

abstract class Conexion {

    public $isConnected;
    protected $datab;

    // Credenciales por defecto de XAMPP / WAMP / MAMP
    private $username = "root";
    private $password = "";
    private $host     = "localhost";
    private $dbname   = "coffee";

    public function __construct() {
        $this->isConnected = true;
        try {
            $this->datab = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8",
                $this->username,
                $this->password,
                array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8')
            );
            $this->datab->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->datab->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->isConnected = false;
            throw new Exception($e->getMessage());
        }
    }

    public function Disconnect() {
        $this->datab = null;
        $this->isConnected = false;
    }

    public function getRow($query, $params = array()) {
        try {
            $stmt = $this->datab->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function getRows($query, $params = array()) {
        try {
            $stmt = $this->datab->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function insertRow($query, $params) {
        try {
            $stmt = $this->datab->prepare($query);
            $stmt->execute($params);
            return $this->datab->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function updateRow($query, $params) {
        try {
            $stmt = $this->datab->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function deleteRow($query, $params) {
        return $this->updateRow($query, $params);
    }
}
