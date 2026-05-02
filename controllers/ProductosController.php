<?php
/**
 * ProductosController — API REST de productos
 * 

 *   - Respuestas en JSON
 *   - Verbos HTTP reales en vez de ?action=crear
 *   - Endpoints admin protegidos con JWT (antes era por $_SESSION)
 * 
 * Endpoints:
 *   GET    /api/productos       → lista todos (público)
 *   GET    /api/productos/{id}  → uno por ID (público)
 *   POST   /api/productos       → crea (requiere admin)
 *   PUT    /api/productos/{id}  → edita (requiere admin)
 *   DELETE /api/productos/{id}  → elimina (requiere admin)
 */

require_once __DIR__ . '/../models/Productos.php';

class ProductosController {

    /**
     * Punto de entrada desde el router.
     * El router pasa el método HTTP y el ID opcional.
     */
    public static function manejar($method, $id = null) {
        switch ($method) {
            case 'GET':
                if ($id !== null) {
                    self::mostrarUno($id);
                } else {
                    self::listar();
                }
                break;

            case 'POST':
                self::crear();
                break;

            case 'PUT':
                if ($id === null) {
                    self::responderError(400, "Se requiere el ID del producto");
                    return;
                }
                self::editar($id);
                break;

            case 'DELETE':
                if ($id === null) {
                    self::responderError(400, "Se requiere el ID del producto");
                    return;
                }
                self::eliminar($id);
                break;

            default:
                self::responderError(405, "Método $method no permitido");
        }
    }

    // ------- Endpoints públicos -------

    private static function listar() {
        $productos = Productos::getAll();
        echo json_encode($productos);
    }

    private static function mostrarUno($id) {
        $producto = Productos::getById($id);
        if (!$producto) {
            self::responderError(404, "Producto no encontrado");
            return;
        }
        echo json_encode($producto);
    }

    // ------- Endpoints de admin (requieren JWT con id_rol=1) -------

    private static function crear() {
        // JWT middleware: si no es admin, corta aquí con 401/403
        JWT::requerirAutenticacion(1);

        $input = $GLOBALS['input'];

        // Validación mínima
        if (empty($input['Producto']) || !isset($input['Precio'])) {
            self::responderError(400, "Faltan campos requeridos: Producto, Precio");
            return;
        }

        $producto = new Productos([
            'IdProducto' => $input['IdProducto'] ?? null, // opcional — si no viene, auto-generado
            'Producto'   => $input['Producto'],
            'Precio'     => $input['Precio'],
        ]);
        $nuevoId = $producto->insertar();

        http_response_code(201); // 201 Created
        echo json_encode([
            'mensaje'    => 'Producto creado',
            'IdProducto' => $nuevoId,
            'Producto'   => $input['Producto'],
            'Precio'     => $input['Precio'],
        ]);
    }

    private static function editar($id) {
        JWT::requerirAutenticacion(1);

        $input = $GLOBALS['input'];

        // Verificar que el producto existe antes de intentar actualizar
        $existente = Productos::getById($id);
        if (!$existente) {
            self::responderError(404, "Producto no encontrado");
            return;
        }

        $producto = new Productos([
            'IdProducto' => $id,
            'Producto'   => $input['Producto'] ?? $existente['Producto'],
            'Precio'     => $input['Precio']   ?? $existente['Precio'],
        ]);
        $producto->editar();

        echo json_encode([
            'mensaje'    => 'Producto actualizado',
            'IdProducto' => (int)$id,
            'Producto'   => $producto->Producto,
            'Precio'     => $producto->Precio,
        ]);
    }

    private static function eliminar($id) {
        JWT::requerirAutenticacion(1);

        $existente = Productos::getById($id);
        if (!$existente) {
            self::responderError(404, "Producto no encontrado");
            return;
        }

        $producto = new Productos(['IdProducto' => $id]);
        $producto->eliminar();

        echo json_encode(['mensaje' => 'Producto eliminado', 'IdProducto' => (int)$id]);
    }

    // ------- Helper -------

    private static function responderError($codigo, $mensaje) {
        http_response_code($codigo);
        echo json_encode(['error' => $mensaje]);
    }
}
