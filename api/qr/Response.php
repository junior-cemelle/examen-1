<?php

declare(strict_types=1);

/**
 * Helper estático para respuestas JSON y de imagen uniformes.
 *
 * Éxito JSON : { "success": true,  "data": { ... } }
 * Error JSON : { "success": false, "error": { "code": 4xx, "message": "..." } }
 * Imagen     : binario PNG o SVG con headers apropiados
 */
class Response
{
    // -----------------------------------------------------------------------
    // Respuestas JSON
    // -----------------------------------------------------------------------

    public static function success(mixed $data, int $status = 200): void
    {
        self::jsonHeaders($status);
        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function error(string $message, int $status = 400): void
    {
        self::jsonHeaders($status);
        echo json_encode([
            'success' => false,
            'error'   => ['code' => $status, 'message' => $message],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -----------------------------------------------------------------------
    // Respuesta de imagen (QR directo)
    // -----------------------------------------------------------------------

    /**
     * Sirve la imagen QR directamente al cliente (para descarga o visualización).
     *
     * @param string $data     Binario de la imagen.
     * @param string $mimeType image/png | image/svg+xml
     * @param string $format   png | svg  (usado en Content-Disposition)
     */
    public static function image(string $data, string $mimeType, string $format = 'png'): void
    {
        $filename = 'qr-' . date('YmdHis') . '.' . $format;

        http_response_code(200);
        header('Content-Type: '        . $mimeType);
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: '      . strlen($data));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $data;
        exit;
    }

    // -----------------------------------------------------------------------
    // CORS
    // -----------------------------------------------------------------------

    public static function cors(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Accept');

        if (strtoupper($_SERVER['REQUEST_METHOD']) === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    // -----------------------------------------------------------------------
    // Privado
    // -----------------------------------------------------------------------

    private static function jsonHeaders(int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
}
