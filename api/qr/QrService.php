<?php

declare(strict_types=1);

require_once __DIR__ . '/QrContentBuilder.php';
require_once __DIR__ . '/QrGenerator.php';

/**
 * Capa de servicio: orquesta QrContentBuilder y QrGenerator.
 * Recibe los parámetros HTTP crudos, los valida, construye el
 * payload correcto según el tipo y delega la generación de imagen.
 *
 * Nota: phpqrcode solo genera PNG, por lo que el parámetro "format"
 * no aplica en esta versión.
 */
class QrService
{
    private QrGenerator $generator;

    public function __construct()
    {
        $this->generator = new QrGenerator();
    }

    // -----------------------------------------------------------------------
    // Punto de entrada genérico
    // -----------------------------------------------------------------------

    /**
     * Enruta la petición al método correcto según el campo "type".
     * Tipos soportados: text | url | wifi | geo
     */
    public function generate(array $params): array
    {
        $type = strtolower(trim($params['type'] ?? 'text'));

        return match ($type) {
            'text' => $this->text($params),
            'url'  => $this->url($params),
            'wifi' => $this->wifi($params),
            'geo'  => $this->geo($params),
            default => throw new InvalidArgumentException(
                "Tipo de QR no válido: '{$type}'. Tipos soportados: text, url, wifi, geo."
            ),
        };
    }

    // -----------------------------------------------------------------------
    // Métodos por tipo de QR
    // -----------------------------------------------------------------------

    /** QR de texto plano. Param requerido: text */
    public function text(array $params): array
    {
        $payload = QrContentBuilder::text($this->requireString($params, 'text'));
        return $this->buildQr($payload, $params);
    }

    /** QR de URL. Param requerido: url */
    public function url(array $params): array
    {
        $payload = QrContentBuilder::url($this->requireString($params, 'url'));
        return $this->buildQr($payload, $params);
    }

    /**
     * QR de red WiFi.
     * Params requeridos : ssid
     * Params opcionales : password, encryption (WPA|WEP|nopass)
     */
    public function wifi(array $params): array
    {
        $ssid       = $this->requireString($params, 'ssid');
        $password   = isset($params['password'])   ? (string) $params['password']   : '';
        $encryption = isset($params['encryption']) ? (string) $params['encryption'] : 'WPA';

        $payload = QrContentBuilder::wifi($ssid, $password, $encryption);
        return $this->buildQr($payload, $params);
    }

    /**
     * QR de geolocalización.
     * Params requeridos: lat, lng
     */
    public function geo(array $params): array
    {
        if (!isset($params['lat'], $params['lng'])) {
            throw new InvalidArgumentException("Los campos 'lat' y 'lng' son requeridos.");
        }

        $lat = filter_var($params['lat'], FILTER_VALIDATE_FLOAT);
        $lng = filter_var($params['lng'], FILTER_VALIDATE_FLOAT);

        if ($lat === false || $lng === false) {
            throw new InvalidArgumentException("'lat' y 'lng' deben ser números decimales válidos.");
        }

        $payload = QrContentBuilder::geo((float) $lat, (float) $lng);
        return $this->buildQr($payload, $params);
    }

    // -----------------------------------------------------------------------
    // Helpers privados
    // -----------------------------------------------------------------------

    /**
     * Extrae los parámetros comunes de presentación y llama al generador.
     */
    private function buildQr(string $payload, array $params): array
    {
        $size            = isset($params['size'])            ? (int) $params['size']            : 300;
        $errorCorrection = isset($params['errorCorrection']) ? (string) $params['errorCorrection'] : 'M';
        $margin          = isset($params['margin'])          ? (int) $params['margin']          : 1;

        return $this->generator->generate($payload, $size, $errorCorrection, $margin);
    }

    private function requireString(array $params, string $field): string
    {
        if (!isset($params[$field]) || !is_string($params[$field]) || trim($params[$field]) === '') {
            throw new InvalidArgumentException(
                "El campo '{$field}' es requerido y no puede estar vacío."
            );
        }
        return $params[$field];
    }
}
