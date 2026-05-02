<?php
/**
 * Modelo Usuarios

 * 
 * Tabla usuarios:
 *   id              VARCHAR/INT PRIMARY KEY  (cédula, ingresada manualmente)
 *   nombre          VARCHAR
 *   apellido        VARCHAR
 *   telefono        VARCHAR(10)
 *   email           VARCHAR UNIQUE
 *   password        VARCHAR  (texto plano ⚠️)
 *   confirmpassword VARCHAR  (se guarda pero no se usa)
 *   pregunta        VARCHAR
 *   respuesta       VARCHAR
 *   id_rol          INT      (1=Admin, 2=Cliente)
 */

require_once __DIR__ . '/../config/database.php';

class Usuarios extends Conexion {

    public $id;
    public $nombre;
    public $apellido;
    public $telefono;
    public $email;
    public $password;
    public $confirmpassword;
    public $pregunta;
    public $respuesta;
    public $id_rol;

    public function __construct($data = array()) {
        parent::__construct();
        if (!empty($data)) {
            $this->id              = $data['id']              ?? null;
            $this->nombre          = $data['nombre']          ?? null;
            $this->apellido        = $data['apellido']        ?? null;
            $this->telefono        = $data['telefono']        ?? null;
            $this->email           = $data['email']           ?? null;
            $this->password        = $data['password']        ?? null;
            $this->confirmpassword = $data['confirmpassword'] ?? null;
            $this->pregunta        = $data['pregunta']        ?? null;
            $this->respuesta       = $data['respuesta']       ?? null;
            $this->id_rol          = $data['id_rol']          ?? null;
        }
    }

    public function __destruct() {
        $this->Disconnect();
    }

    // CREATE — autoregistro de cliente (id_rol=2 fijo)
    public function insertar() {
        $sql = "INSERT INTO usuarios(id, nombre, apellido, telefono, email, password, confirmpassword, pregunta, respuesta, id_rol)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        return $this->insertRow($sql, array(
            $this->id,
            $this->nombre,
            $this->apellido,
            $this->telefono,
            $this->email,
            $this->password,
            $this->confirmpassword,
            $this->pregunta,
            $this->respuesta,
            2, // cliente por defecto
        ));
    }

    // READ — lista completa (sin password en el resultado)
    public static function getAll() {
        $tmp  = new Usuarios();
        $rows = $tmp->getRows("SELECT id, nombre, apellido, telefono, email, id_rol FROM usuarios ORDER BY id");
        $tmp->Disconnect();
        return $rows;
    }

    // READ — uno por ID (sin password). Incluye pregunta/respuesta para que
    // la página "Mi Perfil" pueda precargar el formulario.
    public static function getById($id) {
        $tmp = new Usuarios();
        $row = $tmp->getRow(
            "SELECT id, nombre, apellido, telefono, email, pregunta, respuesta, id_rol
             FROM usuarios WHERE id = ?",
            array($id)
        );
        $tmp->Disconnect();
        return $row ?: null;
    }

    // READ — buscar por email (para validar duplicados al registrar)
    public static function existeEmail($email) {
        $tmp = new Usuarios();
        $row = $tmp->getRow("SELECT id FROM usuarios WHERE email = ?", array($email));
        $tmp->Disconnect();
        return $row !== false && $row !== null;
    }

    // READ — verifica si un email ya lo usa OTRO usuario distinto al actual.
    // Necesario para autoedición: el usuario puede "guardar" sin cambiar su email.
    public static function emailTomadoPorOtro($email, $idActual) {
        $tmp = new Usuarios();
        $row = $tmp->getRow(
            "SELECT id FROM usuarios WHERE email = ? AND id != ?",
            array($email, $idActual)
        );
        $tmp->Disconnect();
        return $row !== false && $row !== null;
    }

    // UPDATE — edición por admin (incluye id_rol, no toca password)
    public function editarPorAdmin() {
        $sql = "UPDATE usuarios
                SET nombre = ?, apellido = ?, telefono = ?, email = ?, id_rol = ?
                WHERE id = ?";
        return $this->updateRow($sql, array(
            $this->nombre,
            $this->apellido,
            $this->telefono,
            $this->email,
            $this->id_rol,
            $this->id,
        ));
    }

    // UPDATE — autoedición del perfil (NO toca id, nombre, apellido ni id_rol).
    // Si $cambiarPassword es true, también actualiza la contraseña.
    public function editarPerfilPropio($cambiarPassword = false) {
        if ($cambiarPassword) {
            $sql = "UPDATE usuarios
                    SET telefono = ?, email = ?, pregunta = ?, respuesta = ?,
                        password = ?, confirmpassword = ?
                    WHERE id = ?";
            return $this->updateRow($sql, array(
                $this->telefono,
                $this->email,
                $this->pregunta,
                $this->respuesta,
                $this->password,
                $this->password,  // confirmpassword = password
                $this->id,
            ));
        }
        $sql = "UPDATE usuarios
                SET telefono = ?, email = ?, pregunta = ?, respuesta = ?
                WHERE id = ?";
        return $this->updateRow($sql, array(
            $this->telefono,
            $this->email,
            $this->pregunta,
            $this->respuesta,
            $this->id,
        ));
    }

    // DELETE
    public function eliminar() {
        $sql = "DELETE FROM usuarios WHERE id = ?";
        return $this->updateRow($sql, array($this->id));
    }

    /**
     * Valida credenciales de login.
     * Devuelve el usuario (sin password) si es válido, null si no.
     */
    public static function validarCredenciales($email, $password) {
        $tmp = new Usuarios();
        // comparación directa en texto plano — ver advertencia al inicio del archivo
        $row = $tmp->getRow(
            "SELECT id, nombre, apellido, email, id_rol FROM usuarios WHERE email = ? AND password = ?",
            array($email, $password)
        );
        $tmp->Disconnect();
        return $row ?: null;
    }
}