<?php
/**
 * Configuración CORS (Cross-Origin Resource Sharing)
 * 
 * Permite que el frontend React (corriendo en http://localhost:5173 por defecto en Vite)
 * pueda consumir esta API PHP (corriendo en http://localhost o http://localhost:8000).
 * 
 * Para producción, reemplazar el "*" en Access-Control-Allow-Origin por el dominio real.
 */

// Orígenes permitidos. En desarrollo dejamos varios comunes; en producción se limita.
$origenesPermitidos = [
    'http://localhost:5173',   // Vite dev server (default)
    'http://localhost:3000',   // Alternativa común
    'http://127.0.0.1:5173',
];

$origen = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origen, $origenesPermitidos)) {
    header("Access-Control-Allow-Origin: $origen");
} else {
    // Fallback permisivo para desarrollo académico. 
    // ELIMINAR esta línea en producción y dejar solo la whitelist.
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 86400"); // Cachea el preflight 24h

// La API siempre responde JSON
header("Content-Type: application/json; charset=UTF-8");

// Preflight: el navegador manda un OPTIONS antes de requests con Authorization header.
// Respondemos 200 y cortamos la ejecución — no hace falta lógica de negocio aquí.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
