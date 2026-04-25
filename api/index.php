<?php
/**
 * Router principal de la API REST.
 * 
 * Todas las peticiones al backend entran por aquí (gracias al .htaccess que
 * reescribe /api/cualquier/ruta → /api/index.php?route=cualquier/ruta).
 * 
 * Formato de rutas:
 *   GET    /api/productos           → listar todos
 *   GET    /api/productos/{id}      → uno por id
 *   POST   /api/productos           → crear (admin)
 *   PUT    /api/productos/{id}      → editar (admin)
 *   DELETE /api/productos/{id}      → eliminar (admin)
 * 
 *   POST   /api/auth/login          → login (devuelve JWT)
 *   POST   /api/auth/registro       → autoregistro de cliente
 *   GET    /api/auth/yo             → datos del usuario autenticado (verifica JWT)
 * 
 *   GET    /api/usuarios            → listar (admin)
 *   ... etc
 * 
 *   GET    /api/pedidos             → listar (admin)
 *   GET    /api/pedidos/mios        → historial del usuario logueado
 *   POST   /api/pedidos             → crear (usuario autenticado)
 *   ... etc
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';

// Obtener la ruta solicitada. Viene por ?route=... gracias al .htaccess.
$route  = $_GET['route'] ?? '';
$route  = trim($route, '/');
$method = $_SERVER['REQUEST_METHOD'];

// Partir la ruta en segmentos: "productos/5" → ["productos", "5"]
$segments = $route === '' ? [] : explode('/', $route);
$recurso  = $segments[0] ?? '';
$id       = $segments[1] ?? null;
$subAccion = $segments[1] ?? null; // Para rutas como /auth/login o /pedidos/mios

// Input JSON: los requests POST/PUT traen JSON en el body, no form-data.
// Esto lo parseamos una sola vez y lo dejamos accesible globalmente.
$inputRaw = file_get_contents('php://input');
$input    = json_decode($inputRaw, true) ?? [];
$GLOBALS['input'] = $input;

// Dispatcher: según el recurso, cargamos el controller correspondiente.
// Cada controller expone una función `manejar($method, $id, $subAccion)`.
try {
    switch ($recurso) {

        case 'auth':
            require_once __DIR__ . '/../controllers/AuthController.php';
            AuthController::manejar($method, $subAccion);
            break;

        case 'productos':
            require_once __DIR__ . '/../controllers/ProductosController.php';
            ProductosController::manejar($method, $id);
            break;

        case 'usuarios':
            require_once __DIR__ . '/../controllers/UsuariosController.php';
            UsuariosController::manejar($method, $id);
            break;

        case 'pedidos':
            require_once __DIR__ . '/../controllers/PedidosController.php';
            // Pedidos usa clave compuesta (IdPedido, IdProducto):
            // segments[1] = IdPedido (o "mios"), segments[2] = IdProducto
            $idPedido   = $segments[1] ?? null;
            $idProducto = $segments[2] ?? null;
            PedidosController::manejar($method, $idPedido, $idProducto);
            break;

        case '':
            // Endpoint raíz: útil para verificar que la API está viva
            echo json_encode([
                'nombre'  => 'CoffeeLuda API',
                'version' => '1.0',
                'estado'  => 'activa'
            ]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => "Recurso '$recurso' no encontrado"]);
    }
} catch (Throwable $e) {
    // Throwable captura tanto Exception como Error (PHP 7+).
    // Esto incluye errores fatales como "driver PDO no disponible",
    // que antes hacían que PHP devolviera una respuesta vacía con status 500.
    http_response_code(500);
    echo json_encode([
        'error'   => 'Error interno del servidor',
        'detalle' => $e->getMessage() // En producción quitar esto para no filtrar info
    ]);
}
