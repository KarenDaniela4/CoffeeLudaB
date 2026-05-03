<?php
/**

 *  * FLUJO:
 *   1. Usuario hace login → servidor genera JWT y lo devuelve al cliente
 *   2. Cliente guarda el JWT en localStorage
 *   3. En cada request, cliente envía: Authorization: Bearer <token>
 *   4. Servidor verifica la firma y el expiración antes de responder
 */

class JWT {

    // CAMBIAR ESTA CLAVE en un proyecto real. Debe ir en variable de entorno.
    private static $secretKey = "coffeeluda_secret_key_cambiar_en_produccion_2024";

    // Duración del token: 24 horas
    private static $expiracionSegundos = 86400;

    /**
     * Codifica a base64url (variante de base64 segura para URLs: sin +, /, =)
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decodifica base64url
     */
    private static function base64UrlDecode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Genera un JWT para un usuario autenticado.
     * 
     * @param array $payloadData Datos a incluir (id, email, id_rol, etc.)
     * @return string JWT firmado
     */
    public static function generar($payloadData) {
        $header = json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256'
        ]);

        // Agregamos claims estándar: iat (issued at) y exp (expiration)
        $payloadData['iat'] = time();
        $payloadData['exp'] = time() + self::$expiracionSegundos;
        $payload = json_encode($payloadData);

        $headerEncoded  = self::base64UrlEncode($header);
        $payloadEncoded = self::base64UrlEncode($payload);

        $signature = hash_hmac(
            'sha256',
            $headerEncoded . '.' . $payloadEncoded,
            self::$secretKey,
            true
        );
        $signatureEncoded = self::base64UrlEncode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * Verifica un JWT. Devuelve el payload decodificado si es válido, null si no.
     * 
     * @param string $token
     * @return array|null
     */
    public static function verificar($token) {
        $partes = explode('.', $token);
        if (count($partes) !== 3) {
            return null;
        }

        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $partes;

        // Recalculamos la firma y comparamos. Si no coincide, el token fue alterado.
        $signatureEsperada = hash_hmac(
            'sha256',
            $headerEncoded . '.' . $payloadEncoded,
            self::$secretKey,
            true
        );
        $signatureEsperadaEncoded = self::base64UrlEncode($signatureEsperada);

        if (!hash_equals($signatureEsperadaEncoded, $signatureEncoded)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);

        // Verificar expiración
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Extrae el token del header Authorization: Bearer <token>
     * Devuelve el payload del usuario autenticado, o null si no hay token válido.
     */
    public static function obtenerUsuarioActual() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
            return null;
        }

        return self::verificar($matches[1]);
    }

    /**
     * Middleware: exige que haya un usuario autenticado. 
     * Si no, responde 401 y corta la ejecución.
     * Si se pasa $rolRequerido, verifica que el usuario tenga ese rol.
     */
    public static function requerirAutenticacion($rolRequerido = null) {
        $usuario = self::obtenerUsuarioActual();

        if (!$usuario) {
            http_response_code(401);
            echo json_encode(['error' => 'No autenticado']);
            exit();
        }

        if ($rolRequerido !== null && ($usuario['id_rol'] ?? null) != $rolRequerido) {
            http_response_code(403);
            echo json_encode(['error' => 'No autorizado']);
            exit();
        }

        return $usuario;
    }
}
