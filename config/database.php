<?php
/**
 * Clase base de conexión a la base de datos MySQL.
  
 * NOTA: En producción, las credenciales NO deben ir hardcodeadas.
 * Usar variables de entorno con getenv() o un archivo .env.
 */

abstract class Conexion {

    public $isConnected;
    protected $datab;

    // Credenciales por defecto de XAMPP / WAMP / MAMP
    private $username = "uwcbga61svye8fmp";
    private $password = "7ephn2WKrJgep9L0Hk4r";
    private $host     = "bgyiixxak63zuijixe8s-mysql.services.clever-cloud.com";
    private $dbname   = "bgyiixxak63zuijixe8s";

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
