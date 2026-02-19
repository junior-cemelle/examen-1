<?php

declare(strict_types=1);
ob_start();

/**
 * Router principal del servicio de acortamiento de URLs.
 *
 * Rutas (configuradas en el .htaccess raíz):
 *
 *   POST /api/short              → acorta una URL nueva
 *   GET  /api/short/{code}       → redirige al destino original (301)
 *   GET  /api/short/{code}/stats → devuelve estadísticas del enlace
 *
 * Obtención de la IP real del visitante:
 *   Se respetan X-Forwarded-For y X-Real-IP para entornos detrás de proxy.
 */

require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/ShortService.php';

Response::cors();

$method = strtoupper($_SERVER['REQUEST_METHOD']);
$uri    = rtrim(strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), '/');

// -- Parsear body JSON -------------------------------------------------------
$bodyJson = [];
$bodyRaw  = file_get_contents('php://input');
if (!empty($bodyRaw)) {
    $decoded = json_decode($bodyRaw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        Response::error('El cuerpo de la petición no es JSON válido.', 400);
    }
    $bodyJson = $decoded ?? [];
}

$params = array_merge($_GET, $bodyJson);


// -- IP real del cliente ----------------------------------------------------
function getClientIp(): string
{
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            // X-Forwarded-For puede tener lista de IPs; tomar la primera
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

$clientIp  = getClientIp();
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// -- Extraer segmentos de la ruta -------------------------------------------
// URI esperada: .../api/short  |  .../api/short/{code}  |  .../api/short/{code}/stats
$segments = explode('/', trim($uri, '/'));

// Encontrar el índice donde aparece "short" para extraer lo que sigue
$shortIdx = array_search('short', $segments);
$after    = ($shortIdx !== false) ? array_slice($segments, $shortIdx + 1) : [];

// $after[0] = code (si existe)
// $after[1] = "stats" (si existe)
$code    = $after[0] ?? '';
$subpath = $after[1] ?? '';


// Log para verificar parametros de entrada
error_log("METHOD: $method | CODE: '$code' | BODY: $bodyRaw | PARAMS: " . json_encode($params));

// ============================================================================
// RUTAS
// ============================================================================

try {
    $service = new ShortService();

    // -- POST /api/short  →  acortar URL ------------------------------------
    if ($method === 'POST' && $code === '') {
        if (empty($params['url'])) {
            Response::error("El campo 'url' es requerido.", 400);
        }
        $result = $service->shorten($params, $clientIp);
        Response::success($result, 201);
    }

    // -- GET /api/short/{code}/stats  →  estadísticas -----------------------
    if ($method === 'GET' && $code !== '' && $subpath === 'stats') {
        $result = $service->stats($code);
        Response::success($result);
    }

    // -- GET /api/short/{code}  →  redirección 301 --------------------------
    if ($method === 'GET' && $code !== '' && $subpath === '') {
        $originalUrl = $service->resolve($code, $clientIp, $userAgent);
        Response::redirect($originalUrl);
    }

    // -- Ruta no reconocida -------------------------------------------------
    Response::error('Ruta no encontrada.', 404);

} catch (RuntimeException $e) {
    // El código de la excepción puede ser 404, 410, 429 o 500
    $httpCode = in_array($e->getCode(), [400, 404, 410, 429, 500], true)
        ? $e->getCode()
        : 500;
    Response::error($e->getMessage(), $httpCode);
} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    Response::error('Error interno del servidor.', 500);
}
