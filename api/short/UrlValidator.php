<?php

declare(strict_types=1);

/**
 * Valida URLs antes de acortarlas.
 *
 * Verificaciones realizadas:
 *  1. Formato válido (filter_var FILTER_VALIDATE_URL)
 *  2. Esquema permitido (solo http y https)
 *  3. No es un bucle (no apunta al propio servidor del acortador)
 *  4. No contiene patrones de URLs maliciosas conocidas
 */
class UrlValidator
{
    // Esquemas permitidos
    private const ALLOWED_SCHEMES = ['http', 'https'];

    // Dominios/IPs que nunca deben acortarse (localhost, rangos privados)
    private const BLOCKED_HOSTS = [
        'localhost',
        '127.0.0.1',
        '::1',
        '0.0.0.0',
    ];

    // Patrones de hosts maliciosos conocidos (extensible)
    private const MALICIOUS_PATTERNS = [
        '/phishing/i',
        '/malware/i',
    ];

    /**
     * Valida una URL. Lanza InvalidArgumentException si no es válida.
     *
     * @param string $url     URL a validar.
     * @param string $ownHost Hostname del propio servidor (para evitar bucles).
     *
     * @throws InvalidArgumentException
     */
    public function validate(string $url, string $ownHost = ''): void
    {
        if (trim($url) === '') {
            throw new InvalidArgumentException("La URL no puede estar vacía.");
        }

        if (strlen($url) > 2048) {
            throw new InvalidArgumentException("La URL excede la longitud máxima permitida (2048 caracteres).");
        }

        // 1. Formato general
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("La URL '{$url}' no tiene un formato válido.");
        }

        $parts  = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host   = strtolower($parts['host']   ?? '');

        // 2. Esquema permitido
        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new InvalidArgumentException(
                "Solo se permiten URLs con esquema http o https. Recibido: '{$scheme}'."
            );
        }

        // 3. Hosts bloqueados (localhost / rangos privados)
        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            throw new InvalidArgumentException(
                "No se pueden acortar URLs que apunten a direcciones locales o reservadas."
            );
        }

        // Bloquear rangos de IP privados
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException(
                    "No se pueden acortar URLs que apunten a rangos de IP privados o reservados."
                );
            }
        }

        // 4. Evitar bucles: la URL no debe apuntar al propio servidor
        if ($ownHost !== '' && $host === strtolower($ownHost)) {
            throw new InvalidArgumentException(
                "No se puede acortar una URL que apunte al propio servidor (evitar bucles)."
            );
        }

        // 5. Patrones maliciosos
        foreach (self::MALICIOUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $url)) {
                throw new InvalidArgumentException(
                    "La URL fue rechazada por razones de seguridad."
                );
            }
        }
    }
}
