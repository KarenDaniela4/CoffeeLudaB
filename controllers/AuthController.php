<?php
/**
 * AuthController — endpoints de autenticación
 * 

 * 
 * Endpoints:
 *   POST /api/auth/login     → valida email+password, devuelve JWT
 *   POST /api/auth/registro  → autoregistro de cliente (id_rol=2)
 *   GET  /api/auth/yo        → datos del usuario autenticado (verifica token)
 *   PUT  /api/auth/yo        → autoedición del perfil del usuario autenticado
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
            case 'PUT:yo':
                self::actualizarPerfil();
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

    /**
     * PUT /auth/yo
     * Body: { telefono, email, pregunta, respuesta, password?, confirmpassword? }
     *
     * Permite al usuario autenticado editar SU PROPIO perfil.
     * NO permite cambiar id, nombre, apellido ni id_rol — esos campos se ignoran
     * aunque vengan en el body (defensa en profundidad).
     */
    private static function actualizarPerfil() {
        $payload   = JWT::requerirAutenticacion(); // cualquier rol autenticado
        $idUsuario = $payload['id'];
        $input     = $GLOBALS['input'];

        $existente = Usuarios::getById($idUsuario);
        if (!$existente) {
            http_response_code(404);
            echo json_encode(['error' => 'Usuario no encontrado']);
            return;
        }

        // Si no viene un campo, conservamos el valor actual.
        $telefono  = trim($input['telefono']  ?? $existente['telefono']);
        $email     = trim($input['email']     ?? $existente['email']);
        $pregunta  = trim($input['pregunta']  ?? $existente['pregunta']);
        $respuesta = trim($input['respuesta'] ?? $existente['respuesta']);

        // Validaciones (mismas reglas que registro.js)
        if ($email === '') {
            http_response_code(400);
            echo json_encode(['error' => 'El correo es obligatorio']);
            return;
        }
        if ($telefono !== '' && (strlen($telefono) !== 10 || !ctype_digit($telefono))) {
            http_response_code(400);
            echo json_encode(['error' => 'El teléfono debe tener exactamente 10 dígitos']);
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'El correo electrónico no es válido']);
            return;
        }
        if ($email !== $existente['email']
            && Usuarios::emailTomadoPorOtro($email, $idUsuario)) {
            http_response_code(409);
            echo json_encode(['error' => 'Ya existe otra cuenta con ese correo']);
            return;
        }

        // Cambio de contraseña: solo si el usuario llenó el campo.
        $cambiarPassword = !empty($input['password']);
        $password        = null;
        if ($cambiarPassword) {
            if (($input['password'] ?? '') !== ($input['confirmpassword'] ?? '')) {
                http_response_code(400);
                echo json_encode(['error' => 'Las contraseñas no coinciden']);
                return;
            }
            $password = $input['password'];
        }

        $usuario = new Usuarios([
            'id'        => $idUsuario,
            'telefono'  => $telefono,
            'email'     => $email,
            'pregunta'  => $pregunta,
            'respuesta' => $respuesta,
            'password'  => $password,
        ]);
        $usuario->editarPerfilPropio($cambiarPassword);

        echo json_encode([
            'mensaje' => 'Perfil actualizado',
            'usuario' => Usuarios::getById($idUsuario),
        ]);
    }
}