<?php
/**
 * PedidosController — REST de pedidos
 * 
 
 *  * Endpoints:
 *   POST   /api/pedidos                          → crear compra (usuario autenticado)
 *                                                  Body: { items: [{ IdProducto, Cantidad, Precio }, ...] }
 *   GET    /api/pedidos/mios                     → mis compras (usuario autenticado)
 *   GET    /api/pedidos                          → todos (admin)
 *   GET    /api/pedidos/{idPedido}/{idProducto}  → uno específico (admin)
 *   PUT    /api/pedidos/{idPedido}/{idProducto}  → editar línea (admin)
 *   DELETE /api/pedidos/{idPedido}/{idProducto}  → eliminar línea (admin)
 */

require_once __DIR__ . '/../models/Pedidos.php';

class PedidosController {

    /**
     * El router nos pasa $method, $id (primer segmento post-recurso) y $subAccion
     * (segundo segmento). Para pedidos usamos los 2 porque la clave es compuesta.
     */
    public static function manejar($method, $idPedido = null, $idProducto = null) {
        // Casos especiales: /pedidos/mios (GET con "mios" donde iría un id)
        if ($method === 'GET' && $idPedido === 'mios') {
            self::misCompras();
            return;
        }

        switch ($method) {
            case 'POST':
                self::crearCompra();
                break;

            case 'GET':
                JWT::requerirAutenticacion(1); // listar / ver es admin
                if ($idPedido !== null && $idProducto !== null) {
                    self::mostrarUno($idPedido, $idProducto);
                } else {
                    self::listarTodos();
                }
                break;

            case 'PUT':
                JWT::requerirAutenticacion(1);
                if ($idPedido === null || $idProducto === null) {
                    self::responderError(400, "Se requieren IdPedido e IdProducto en la URL");
                    return;
                }
                self::editar($idPedido, $idProducto);
                break;

            case 'DELETE':
                JWT::requerirAutenticacion(1);
                if ($idPedido === null || $idProducto === null) {
                    self::responderError(400, "Se requieren IdPedido e IdProducto en la URL");
                    return;
                }
                self::eliminar($idPedido, $idProducto);
                break;

            default:
                self::responderError(405, "Método $method no permitido");
        }
    }

    /**
     * POST /pedidos — crear compra.
     * Body: { items: [{ IdProducto, Cantidad, Precio }, ...] }
     * Requiere usuario autenticado (cualquier rol).
     */
    private static function crearCompra() {
        $usuario = JWT::requerirAutenticacion();

        $input = $GLOBALS['input'];
        $items = $input['items'] ?? [];

        if (empty($items) || !is_array($items)) {
            self::responderError(400, "El carrito está vacío o tiene un formato inválido");
            return;
        }

        // Validación básica de cada item
        foreach ($items as $item) {
            if (!isset($item['IdProducto']) || !isset($item['Cantidad']) || !isset($item['Precio'])) {
                self::responderError(400, "Cada item debe tener IdProducto, Cantidad y Precio");
                return;
            }
            if ((int)$item['Cantidad'] < 1) {
                self::responderError(400, "La cantidad debe ser al menos 1");
                return;
            }
        }

        try {
            $idPedido = Pedidos::crearCompra($items, $usuario['id']);

            // Calcular total para la respuesta
            $total = 0;
            foreach ($items as $item) {
                $total += (float)$item['Precio'] * (int)$item['Cantidad'];
            }

            http_response_code(201);
            echo json_encode([
                'mensaje'   => '¡Pedido registrado con éxito en Coffee Luda!',
                'IdPedido'  => $idPedido,
                'total'     => $total,
                'cantidadItems' => count($items),
            ]);
        } catch (Exception $e) {
            self::responderError(500, 'Error al registrar el pedido: ' . $e->getMessage());
        }
    }

    /**
     * GET /pedidos/mios — historial del usuario autenticado.
     */
    private static function misCompras() {
        $usuario = JWT::requerirAutenticacion();
        $pedidos = Pedidos::buscarPorUsuario($usuario['id']);
        echo json_encode($pedidos);
    }

    private static function listarTodos() {
        $pedidos = Pedidos::getAll();
        echo json_encode($pedidos);
    }

    private static function mostrarUno($idPedido, $idProducto) {
        $pedido = Pedidos::getByClave($idPedido, $idProducto);
        if (!$pedido) {
            self::responderError(404, "Pedido no encontrado");
            return;
        }
        echo json_encode($pedido);
    }

    private static function editar($idPedido, $idProducto) {
        $input = $GLOBALS['input'];

        $existente = Pedidos::getByClave($idPedido, $idProducto);
        if (!$existente) {
            self::responderError(404, "Pedido no encontrado");
            return;
        }

        $pedido = new Pedidos([
            'IdPedido'   => $idPedido,
            'IdProducto' => $idProducto,
            'Cantidad'   => $input['Cantidad'] ?? $existente['Cantidad'],
            'Total'      => $input['Total']    ?? $existente['Total'],
            'Fecha'      => $input['Fecha']    ?? $existente['Fecha'],
        ]);
        $pedido->editar();

        echo json_encode([
            'mensaje' => 'Pedido actualizado',
            'pedido'  => Pedidos::getByClave($idPedido, $idProducto),
        ]);
    }

    private static function eliminar($idPedido, $idProducto) {
        $existente = Pedidos::getByClave($idPedido, $idProducto);
        if (!$existente) {
            self::responderError(404, "Pedido no encontrado");
            return;
        }

        $pedido = new Pedidos([
            'IdPedido'   => $idPedido,
            'IdProducto' => $idProducto,
        ]);
        $pedido->eliminar();

        echo json_encode(['mensaje' => 'Pedido eliminado']);
    }

    private static function responderError($codigo, $mensaje) {
        http_response_code($codigo);
        echo json_encode(['error' => $mensaje]);
    }
}
