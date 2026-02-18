<?php

/**
 * Clase que encapsula la lógica de generación de contraseñas seguras.
 * Utiliza random_int() para entropía criptográfica y Fisher-Yates para mezcla.
 */
class GenPassword
{
    // Conjuntos de caracteres base
    private const UPPER   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private const LOWER   = 'abcdefghijklmnopqrstuvwxyz';
    private const DIGITS  = '0123456789';
    private const SYMBOLS = '!@#$%^&*()-_=+[]{}|;:,.<>?';

    // Caracteres considerados ambiguos
    private const AMBIGUOUS = 'Il1O0o';

    // -----------------------------------------------------------------------
    // Métodos privados de utilidad
    // -----------------------------------------------------------------------

    /**
     * Wrapper de random_int para mayor claridad semántica.
     */
    private static function secureRandInt(int $min, int $max): int
    {
        return random_int($min, $max);
    }

    /**
     * Mezcla una cadena usando Fisher-Yates con random_int.
     */
    private static function shuffleSecure(string $str): string
    {
        $arr = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);
        $n   = count($arr);

        for ($i = $n - 1; $i > 0; $i--) {
            $j       = self::secureRandInt(0, $i);
            $tmp     = $arr[$i];
            $arr[$i] = $arr[$j];
            $arr[$j] = $tmp;
        }

        return implode('', $arr);
    }

    // -----------------------------------------------------------------------
    // API pública
    // -----------------------------------------------------------------------

    /**
     * Genera una contraseña segura.
     *
     * @param int   $length Longitud deseada (4–128).
     * @param array $opts   Opciones:
     *   - upper          (bool)   Incluir mayúsculas          [default: true]
     *   - lower          (bool)   Incluir minúsculas          [default: true]
     *   - digits         (bool)   Incluir dígitos             [default: true]
     *   - symbols        (bool)   Incluir símbolos            [default: true]
     *   - avoid_ambiguous(bool)   Evitar Il1O0o               [default: true]
     *   - exclude        (string) Caracteres a excluir        [default: '']
     *   - require_each   (bool)   Al menos 1 de cada categoría[default: true]
     *
     * @return string
     * @throws InvalidArgumentException
     */
    public static function generate(int $length = 16, array $opts = []): string
    {
        self::validateLength($length);

        $opts = array_merge([
            'upper'           => true,
            'lower'           => true,
            'digits'          => true,
            'symbols'         => true,
            'avoid_ambiguous' => true,
            'exclude'         => '',
            'require_each'    => true,
        ], $opts);

        // Construir conjuntos activos
        $sets = [];
        if ($opts['upper'])   $sets['upper']   = self::UPPER;
        if ($opts['lower'])   $sets['lower']   = self::LOWER;
        if ($opts['digits'])  $sets['digits']  = self::DIGITS;
        if ($opts['symbols']) $sets['symbols'] = self::SYMBOLS;

        if (empty($sets)) {
            throw new InvalidArgumentException(
                'Debe activarse al menos una categoría (upper/lower/digits/symbols).'
            );
        }

        // Calcular caracteres a excluir
        $excludeChars = $opts['exclude'];
        if ($opts['avoid_ambiguous']) {
            $excludeChars .= self::AMBIGUOUS;
        }

        $excludeMap = self::buildExcludeMap($excludeChars);

        // Filtrar cada conjunto
        foreach ($sets as $key => $chars) {
            $filtered = self::filterChars($chars, $excludeMap);
            if ($filtered === '') {
                throw new InvalidArgumentException(
                    "Después de aplicar exclusiones, la categoría '{$key}' quedó sin caracteres."
                );
            }
            $sets[$key] = $filtered;
        }

        // Pool total
        $pool = implode('', array_values($sets));

        $passwordChars = [];

        // Garantizar al menos un carácter de cada categoría activa
        if ($opts['require_each']) {
            if ($length < count($sets)) {
                throw new InvalidArgumentException(
                    'La longitud es menor que el número de categorías activas (require_each=true).'
                );
            }
            foreach ($sets as $chars) {
                $idx             = self::secureRandInt(0, strlen($chars) - 1);
                $passwordChars[] = $chars[$idx];
            }
        }

        // Rellenar con caracteres del pool
        $needed = $length - count($passwordChars);
        for ($i = 0; $i < $needed; $i++) {
            $idx             = self::secureRandInt(0, strlen($pool) - 1);
            $passwordChars[] = $pool[$idx];
        }

        // Mezclar de forma segura
        return self::shuffleSecure(implode('', $passwordChars));
    }

    /**
     * Genera múltiples contraseñas.
     *
     * @param int   $count  Cantidad de contraseñas (1–100).
     * @param int   $length Longitud de cada contraseña.
     * @param array $opts   Mismas opciones que generate().
     *
     * @return string[]
     * @throws InvalidArgumentException
     */
    public static function generateMany(int $count = 5, int $length = 16, array $opts = []): array
    {
        if ($count < 1 || $count > 100) {
            throw new InvalidArgumentException('El número de contraseñas debe estar entre 1 y 100.');
        }

        $passwords = [];
        for ($i = 0; $i < $count; $i++) {
            $passwords[] = self::generate($length, $opts);
        }

        return $passwords;
    }

    // -----------------------------------------------------------------------
    // Helpers privados
    // -----------------------------------------------------------------------
 
    private static function validateLength(int $length): void
    {
        if ($length < 4 || $length > 128) {
            throw new InvalidArgumentException(
                'La longitud debe estar entre 4 y 128 caracteres.'
            );
        }
    }

    private static function buildExcludeMap(string $chars): array
    {
        $arr = preg_split('//u', $chars, -1, PREG_SPLIT_NO_EMPTY);
        return array_flip(array_unique($arr));
    }

    private static function filterChars(string $chars, array $excludeMap): string
    {
        $arr      = preg_split('//u', $chars, -1, PREG_SPLIT_NO_EMPTY);
        $filtered = array_filter($arr, fn($c) => !isset($excludeMap[$c]));
        return implode('', array_values($filtered));
    }
}
