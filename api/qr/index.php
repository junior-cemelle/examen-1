<?php

declare(strict_types=1);

/**
 * Router principal del servicio QR.
 *
 * Rutas (configuradas en el .htaccess raíz):
 *   GET/POST /api/qr           → genera QR genérico (campo "type" en params)
 *   POST     /api/qr/text      → QR de texto plano
 *   POST     /api/qr/url       → QR de URL
 *   POST     /api/qr/wifi      → QR de red WiFi
 *   POST     /api/qr/geo       → QR de geolocalización
 *
 * Respuesta por defecto : imagen PNG directa (Content-Type: image/png)
 * Con ?json=true         : JSON con la imagen codificada en base64
 *
 * Parámetros comunes:
 *   size            int    100–1000 px  (default 300)
 *   errorCorrection string L|M|Q|H     (default M)
 *   margin          int    0–10        (default 1)
 *   json            bool   devolver base64 en JSON (default false)
 */

require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/QrService.php';

Response::cors();

$method = strtoupper($_SERVER['REQUEST_METHOD']);
$uri    = rtrim(strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), '/');

// -- Parsear body JSON si viene en el cuerpo --------------------------------
$bodyJson = [];
$bodyRaw  = file_get_contents('php://input');

if (!empty($bodyRaw)) {
    $decoded = json_decode($bodyRaw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        Response::error('El cuerpo de la petición no es JSON válido.', 400);
    }
    $bodyJson = $decoded ?? [];
}

// Merged: body tiene prioridad sobre query string
$params = array_merge($_GET, $bodyJson);

// ¿El cliente quiere JSON con base64 en lugar de imagen directa?
$wantsJson = filter_var($params['json'] ?? false, FILTER_VALIDATE_BOOLEAN);

// -- Detectar sub-ruta (/text, /url, /wifi, /geo) --------------------------
$segments   = explode('/', trim($uri, '/'));
$lastSeg    = end($segments);
$knownTypes = ['text', 'url', 'wifi', 'geo'];

if (in_array($lastSeg, $knownTypes, true)) {
    $params['type'] = $lastSeg;
}

// -- Ejecutar ---------------------------------------------------------------
try {
    $service = new QrService();
    $result  = $service->generate($params);

    if ($wantsJson) {
        // Devolver JSON con imagen en base64
        Response::success([
            'format'   => $result['format'],     // siempre 'png'
            'mimeType' => $result['mimeType'],   // siempre 'image/png'
            'size'     => $result['size'],
            'image'    => base64_encode($result['data']),
        ]);
    } else {
        // Servir la imagen PNG directamente al navegador/cliente
        Response::image($result['data'], $result['mimeType'], $result['format']);
    }

} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (OverflowException $e) {
    Response::error($e->getMessage(), 413);
} catch (RuntimeException $e) {
    Response::error($e->getMessage(), 500);
} catch (\Throwable $e) {
    Response::error('Error interno del servidor.', 500);
}
