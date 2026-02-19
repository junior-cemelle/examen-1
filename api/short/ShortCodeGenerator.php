<?php

declare(strict_types=1);

/**
 * Genera códigos cortos alfanuméricos únicos.
 *
 * - Caracteres: A-Z a-z 0-9 (62 posibles por posición)
 * - Longitud mínima: 5 caracteres
 * - Usa random_int() para entropía criptográfica
 * - Maneja colisiones: si el código ya existe, regenera hasta MAX_ATTEMPTS
 */
class ShortCodeGenerator
{
    private const CHARSET      = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    private const MIN_LENGTH   = 5;
    private const DEFAULT_LENGTH = 6;
    private const MAX_ATTEMPTS = 10;

    // -----------------------------------------------------------------------
    // API pública
    // -----------------------------------------------------------------------

    /**
     * Genera un código único verificando contra los existentes.
     *
     * @param callable $existsFn  Función que recibe un código y devuelve bool
     *                            (true = ya existe, hay colisión).
     * @param int      $length    Longitud del código (mínimo 5).
     *
     * @return string Código único listo para usar.
     * @throws RuntimeException Si no logra generar un código único.
     */
    public function generate(callable $existsFn, int $length = self::DEFAULT_LENGTH): string
    {
        $length = max(self::MIN_LENGTH, $length);

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $code = $this->randomCode($length);
            if (!$existsFn($code)) {
                return $code;
            }
        }

        // Si hay colisión persistente, intentar con un código más largo
        $code = $this->randomCode($length + 2);
        if (!$existsFn($code)) {
            return $code;
        }

        throw new RuntimeException(
            'No se pudo generar un código único. Intenta de nuevo.',
            500
        );
    }

    // -----------------------------------------------------------------------
    // Privado
    // -----------------------------------------------------------------------

    private function randomCode(int $length): string
    {
        $charset = self::CHARSET;
        $max     = strlen($charset) - 1;
        $code    = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $charset[random_int(0, $max)];
        }

        return $code;
    }
}
