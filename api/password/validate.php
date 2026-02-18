<?php

/**
 * Endpoint: POST /api/password/validate
 *
 * Evalúa la fortaleza de una contraseña existente y verifica
 * que cumple los requisitos enviados por el cliente.
 *
 * Body JSON esperado:
 * { 
 *   "password": "MiContraseña123!",
 *   "requirements": {
 *     "minLength": 8,
 *     "requireUppercase": true,
 *     "requireLowercase": true,
 *     "requireNumbers": true,
 *     "requireSymbols": true
 *   }
 * }
 */

declare(strict_types=1);

require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/PasswordService.php';

Response::cors();

// Solo aceptar POST
$method = strtoupper($_SERVER['REQUEST_METHOD']);
if ($method !== 'POST') {
    Response::error('Método no permitido. Usa POST.', 405);
}

// Parsear body JSON
$bodyRaw = file_get_contents('php://input');
if (empty($bodyRaw)) {
    Response::error("El cuerpo de la petición está vacío. Se esperaba JSON con 'password'.", 400);
}

$params = json_decode($bodyRaw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    Response::error('El cuerpo de la petición no es JSON válido.', 400);
}

try {
    $service = new PasswordService();
    $result  = $service->validatePassword($params);

    // HTTP 200 si es válida, 422 si no cumple los requisitos
    $httpCode = $result['valid'] ? 200 : 422;

    Response::success($result, $httpCode);

} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (Throwable $e) {
    Response::error('Error interno del servidor.', 500);
}
