<?php

/**
 * Router principal del servicio de contraseñas.
 *
 * Rutas manejadas:
 *   GET  /api/password            → genera 1 contraseña (parámetros por query string)
 *   POST /api/password            → genera 1 contraseña (parámetros por body JSON)
 *   POST /api/passwords           → genera N contraseñas (parámetros por body JSON)
 *
 * La ruta /api/password/validate se maneja en validate.php
 * (configurado en .htaccess / nginx.conf)
 */

declare(strict_types=1);

require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/PasswordService.php';

// -- CORS (útil para pruebas locales y desde otros orígenes) ----------------
Response::cors();

// -- Detectar ruta y método -------------------------------------------------
$method = strtoupper($_SERVER['REQUEST_METHOD']);

// REQUEST_URI puede tener query string; la limpiamos para comparar rutas
$uri    = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$uri    = rtrim($uri, '/');

// -- Parsear body JSON si lo hay --------------------------------------------
$bodyRaw  = file_get_contents('php://input');
$bodyJson = [];

if (!empty($bodyRaw)) {
    $decoded = json_decode($bodyRaw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        Response::error('El cuerpo de la petición no es JSON válido.', 400);
    }
    $bodyJson = $decoded ?? [];
}

// Merged params: body tiene prioridad sobre query string
$params = array_merge($_GET, $bodyJson);

// -- Servicio ---------------------------------------------------------------
try {
    $service = new PasswordService();

    // POST /api/passwords  →  múltiples contraseñas
    if ($method === 'POST' && str_ends_with($uri, '/passwords')) {
        $result = $service->generateMany($params);
        Response::success($result, 200);
    }

    // GET o POST /api/password  →  una sola contraseña
    if (in_array($method, ['GET', 'POST'], true) && str_ends_with($uri, '/password')) {
        $result = $service->generateOne($params);
        Response::success($result, 200);
    }

    // Ruta no reconocida
    Response::error('Ruta no encontrada.', 404);

} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (Throwable $e) {
    // En producción evitar exponer detalles internos
    Response::error('Error interno del servidor.', 500);
}
