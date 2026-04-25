<?php
/**
 * UsuariosController — CRUD de usuarios (solo admin)
 * 
 * Reemplaza las acciones admin del controller_usuarios.php original.
 * 
 * Endpoints (todos requieren JWT con id_rol=1):
 *   GET    /api/usuarios        → listar todos
 *   GET    /api/usuarios/{id}   → uno por ID
 *   PUT    /api/usuarios/{id}   → editar
 *   DELETE /api/usuarios/{id}   → eliminar (no permite auto-eliminación)
 */

require_once __DIR__ . '/../models/Usuarios.php';

class UsuariosController {

    public static function manejar($method, $id = null) {
        // Toda operación sobre usuarios requiere admin
        $adminActual = JWT::requerirAutenticacion(1);

        switch ($method) {
            case 'GET':
                if ($id !== null) {
                    self::mostrarUno($id);
                } else {
                    self::listar();
                }
                break;

            case 'PUT':
                if ($id === null) {
                    self::responderError(400, "Se requiere el ID del usuario");
                    return;
                }
                self::editar($id);
                break;

            case 'DELETE':
                if ($id === null) {
                    self::responderError(400, "Se requiere el ID del usuario");
                    return;
                }
                self::eliminar($id, $adminActual);
                break;

            default:
                self::responderError(405, "Método $method no permitido");
        }
    }

    private static function listar() {
        $usuarios = Usuarios::getAll();
        echo json_encode($usuarios);
    }

    private static function mostrarUno($id) {
        $usuario = Usuarios::getById($id);
        if (!$usuario) {
            self::responderError(404, "Usuario no encontrado");
            return;
        }
        echo json_encode($usuario);
    }

    private static function editar($id) {
        $input = $GLOBALS['input'];

        $existente = Usuarios::getById($id);
        if (!$existente) {
            self::responderError(404, "Usuario no encontrado");
            return;
        }

        // Permitimos edición parcial: si no viene un campo, se mantiene el existente
        $usuario = new Usuarios([
            'id'       => $id,
            'nombre'   => $input['nombre']   ?? $existente['nombre'],
            'apellido' => $input['apellido'] ?? $existente['apellido'],
            'telefono' => $input['telefono'] ?? $existente['telefono'],
            'email'    => $input['email']    ?? $existente['email'],
            'id_rol'   => (int)($input['id_rol'] ?? $existente['id_rol']),
        ]);
        $usuario->editarPorAdmin();

        echo json_encode([
            'mensaje' => 'Usuario actualizado',
            'usuario' => Usuarios::getById($id), // devolvemos el estado final
        ]);
    }

    private static function eliminar($id, $adminActual) {
        // Regla del original: el admin no puede auto-eliminarse
        if ($adminActual['id'] == $id) {
            self::responderError(400, "No puedes eliminar tu propia cuenta de administrador");
            return;
        }

        $existente = Usuarios::getById($id);
        if (!$existente) {
            self::responderError(404, "Usuario no encontrado");
            return;
        }

        $usuario = new Usuarios(['id' => $id]);
        $usuario->eliminar();

        echo json_encode(['mensaje' => 'Usuario eliminado', 'id' => $id]);
    }

    private static function responderError($codigo, $mensaje) {
        http_response_code($codigo);
        echo json_encode(['error' => $mensaje]);
    }
}
