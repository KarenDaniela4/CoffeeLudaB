<?php
/**
 * AuthController — endpoints de autenticación
 * 
 * Reemplaza las acciones `validacion`, `registrarNuevo` y `cerrars` del 
 * controller_usuarios.php original.
 * 
 * Endpoints:
 *   POST /api/auth/login     → valida email+password, devuelve JWT
 *   POST /api/auth/registro  → autoregistro de cliente (id_rol=2)
 *   GET  /api/auth/yo        → datos del usuario autenticado (verifica token)
 * 
 * Nota: no hay endpoint de logout. Con JWT stateless, el logout es del lado
 * cliente (borrar el token de localStorage). Eso lo hace AuthContext.logout().
 */

require_once __DIR__ . '/../models/Usuarios.php';

class AuthController {

    public static function manejar($method, $subAccion = null) {
        switch ("$method:$subAccion") {
            case 'POST:login':
                self::login();
                break;
            case 'POST:registro':
                self::registro();
                break;
            case 'GET:yo':
                self::yo();
                break;
            default:
                http_response_code(404);
                echo json_encode(['error' => "Ruta /auth/$subAccion no encontrada para método $method"]);
        }
    }

    /**
     * POST /auth/login
     * Body: { email, password }
     * Respuesta exitosa: { token, usuario: { id, nombre, email, id_rol } }
     */
    private static function login() {
        $input = $GLOBALS['input'];

        $email    = trim($input['email']    ?? '');
        $password = $input['password'] ?? '';

        if ($email === '' || $password === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Email y contraseña son obligatorios']);
            return;
        }

        $usuario = Usuarios::validarCredenciales($email, $password);

        if (!$usuario) {
            // Nota: no distinguimos entre "email no existe" y "password incorrecta"
            // para no ayudar a atacantes a enumerar cuentas.
            http_response_code(401);
            echo json_encode(['error' => 'Correo o contraseña incorrectos']);
            return;
        }

        // Generar JWT con la info mínima necesaria
        $token = JWT::generar([
            'id'     => $usuario['id'],
            'email'  => $usuario['email'],
            'id_rol' => (int)$usuario['id_rol'],
        ]);

        echo json_encode([
            'token'   => $token,
            'usuario' => [
                'id'       => $usuario['id'],
                'nombre'   => $usuario['nombre'],
                'apellido' => $usuario['apellido'],
                'email'    => $usuario['email'],
                'id_rol'   => (int)$usuario['id_rol'],
            ],
        ]);
    }

    /**
     * POST /auth/registro
     * Body: { id, nombre, apellido, telefono, email, password, confirmpassword, pregunta, respuesta }
     * Crea un usuario con id_rol=2 (cliente).
     */
    private static function registro() {
        $input = $GLOBALS['input'];

        // Validaciones — mismas reglas que el registro.js original
        $requeridos = ['id', 'nombre', 'apellido', 'telefono', 'email', 'password', 'confirmpassword', 'pregunta', 'respuesta'];
        foreach ($requeridos as $campo) {
            if (empty($input[$campo])) {
                http_response_code(400);
                echo json_encode(['error' => "El campo '$campo' es obligatorio"]);
                return;
            }
        }

        // Validación de teléfono (10 dígitos exactos)
        if (strlen($input['telefono']) !== 10 || !ctype_digit($input['telefono'])) {
            http_response_code(400);
            echo json_encode(['error' => 'El teléfono debe tener exactamente 10 dígitos numéricos']);
            return;
        }

        // Validación de concordancia de contraseñas
        if ($input['password'] !== $input['confirmpassword']) {
            http_response_code(400);
            echo json_encode(['error' => 'Las contraseñas no coinciden']);
            return;
        }

        // Validación de email
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'El correo electrónico no es válido']);
            return;
        }

        // Email no duplicado
        if (Usuarios::existeEmail($input['email'])) {
            http_response_code(409); // 409 Conflict
            echo json_encode(['error' => 'Ya existe una cuenta con ese correo electrónico']);
            return;
        }

        // Insertar
        $usuario = new Usuarios([
            'id'              => $input['id'],
            'nombre'          => $input['nombre'],
            'apellido'        => $input['apellido'],
            'telefono'        => $input['telefono'],
            'email'           => $input['email'],
            'password'        => $input['password'],
            'confirmpassword' => $input['confirmpassword'],
            'pregunta'        => $input['pregunta'],
            'respuesta'       => $input['respuesta'],
        ]);
        $usuario->insertar();

        http_response_code(201);
        echo json_encode([
            'mensaje' => 'Cuenta creada exitosamente',
            'id'      => $input['id'],
        ]);
    }

    /**
     * GET /auth/yo
     * Devuelve los datos del usuario actualmente logueado (según el JWT).
     * Útil para refrescar la info del usuario al recargar la app.
     */
    private static function yo() {
        $payload = JWT::requerirAutenticacion();

        // Traemos datos frescos de la DB por si cambiaron
        $usuario = Usuarios::getById($payload['id']);
        if (!$usuario) {
            http_response_code(404);
            echo json_encode(['error' => 'Usuario no encontrado']);
            return;
        }

        echo json_encode($usuario);
    }
}
