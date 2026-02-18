<?php

/**
 * Helper estático para emitir respuestas JSON uniformes.
 * Toda respuesta sigue la misma estructura:
 *
 * Éxito:  { "success": true,  "data": { ... } }
 * Error:  { "success": false, "error": { "code": 400, "message": "..." } }
 */ 
class Response
{
    /**
     * Envía una respuesta exitosa.
     *
     * @param mixed $data    Datos a serializar.
     * @param int   $status  Código HTTP (default 200).
     */
    public static function success(mixed $data, int $status = 200): void
    {
        self::send(['success' => true, 'data' => $data], $status);
    }

    /**
     * Envía una respuesta de error.
     *
     * @param string $message Mensaje legible.
     * @param int    $status  Código HTTP (default 400).
     */
    public static function error(string $message, int $status = 400): void
    {
        self::send([
            'success' => false,
            'error'   => [
                'code'    => $status,
                'message' => $message,
            ],
        ], $status);
    }

    /**
     * Envía headers CORS permisivos (útil para desarrollo y pruebas).
     * Llama esto antes de cualquier output.
     */
    public static function cors(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Accept');

        // Preflight OPTIONS — responder vacío y terminar
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    // -----------------------------------------------------------------------
    // Privado
    // -----------------------------------------------------------------------

    private static function send(array $body, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
