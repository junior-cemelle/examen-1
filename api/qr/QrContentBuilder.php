<?php

declare(strict_types=1);

/**
 * Construye el string de contenido (payload) para cada tipo de QR.
 *
 * Cada tipo sigue el estándar de facto reconocido por los lectores móviles:
 *  - text  → texto plano
 *  - url   → URI directa
 *  - wifi  → WIFI:T:<type>;S:<ssid>;P:<pass>;;
 *  - geo   → geo:<lat>,<lng>
 */
class QrContentBuilder
{
    // Capacidad aproximada en bytes del QR versión 40, nivel L (máximo práctico)
    private const MAX_CONTENT_BYTES = 2953;

    // -----------------------------------------------------------------------
    // Constructores de payload por tipo
    // -----------------------------------------------------------------------

    /**
     * QR de texto plano.
     *
     * @param string $text Texto a codificar.
     */
    public static function text(string $text): string
    {
        self::assertNotEmpty($text, 'text');
        self::assertLength($text);
        return $text;
    }

    /**
     * QR de URL.
     *
     * @param string $url URL completa (http/https/ftp…).
     */
    public static function url(string $url): string
    {
        self::assertNotEmpty($url, 'url');

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("La URL '{$url}' no es válida.");
        }

        self::assertLength($url);
        return $url;
    }

    /**
     * QR de red WiFi.
     *
     * @param string $ssid       Nombre de la red.
     * @param string $password   Contraseña (puede ser vacía si es abierta).
     * @param string $encryption WPA | WEP | nopass
     */
    public static function wifi(string $ssid, string $password = '', string $encryption = 'WPA'): string
    {
        self::assertNotEmpty($ssid, 'ssid');

        $encryption = strtoupper(trim($encryption));
        $allowed    = ['WPA', 'WEP', 'NOPASS'];

        if (!in_array($encryption, $allowed, true)) {
            throw new InvalidArgumentException(
                "El tipo de encriptación '{$encryption}' no es válido. Usa: WPA, WEP o nopass."
            );
        }

        if ($encryption === 'NOPASS') {
            $password = '';
        }

        // Escapar caracteres especiales del estándar WiFi QR
        $ssidEsc = self::wifiEscape($ssid);
        $passEsc = self::wifiEscape($password);

        $payload = "WIFI:T:{$encryption};S:{$ssidEsc};P:{$passEsc};;";
        self::assertLength($payload);

        return $payload;
    }

    /**
     * QR de geolocalización.
     *
     * @param float $lat Latitud  (-90  a  90).
     * @param float $lng Longitud (-180 a 180).
     */
    public static function geo(float $lat, float $lng): string
    {
        if ($lat < -90 || $lat > 90) {
            throw new InvalidArgumentException(
                "La latitud {$lat} está fuera del rango válido (-90 a 90)."
            );
        }

        if ($lng < -180 || $lng > 180) {
            throw new InvalidArgumentException(
                "La longitud {$lng} está fuera del rango válido (-180 a 180)."
            );
        }

        $payload = "geo:{$lat},{$lng}";
        self::assertLength($payload);

        return $payload;
    }

    // -----------------------------------------------------------------------
    // Helpers privados
    // -----------------------------------------------------------------------

    private static function assertNotEmpty(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException("El campo '{$field}' no puede estar vacío.");
        }
    }

    private static function assertLength(string $content): void
    {
        $bytes = strlen($content);
        if ($bytes > self::MAX_CONTENT_BYTES) {
            throw new OverflowException(
                "El contenido excede la capacidad máxima del QR ({$bytes} bytes de "
                . self::MAX_CONTENT_BYTES . " permitidos)."
            );
        }
    }

    /**
     * Escapa los caracteres reservados en el formato WiFi QR:
     *  \ " ; , :
     */
    private static function wifiEscape(string $value): string
    {
        return str_replace(
            ['\\', '"', ';', ',', ':'],
            ['\\\\', '\\"', '\\;', '\\,', '\\:'],
            $value
        );
    }
}
