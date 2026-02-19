<?php

declare(strict_types=1);

/**
 * Helper estático para respuestas JSON y redirecciones uniformes.
 *
 * Éxito:     { "success": true,  "data": { ... } }
 * Error:     { "success": false, "error": { "code": 4xx, "message": "..." } }
 * Redirect:  HTTP 301 con header Location
 */
class Response
{
    public static function success(mixed $data, int $status = 200): void
    {
        self::jsonHeaders($status);
        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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

    /**
     * Redirección 301 a la URL original.
     * 301 = permanente (el enunciado lo solicita explícitamente).
     */
    public static function redirect(string $url): void
    {
        http_response_code(301);
        header('Location: ' . $url);
        exit;
    }

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

    private static function jsonHeaders(int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
}
