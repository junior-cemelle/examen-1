<?php

/**
 * Valida la fortaleza de contraseñas existentes y verifica
 * requisitos específicos enviados por el cliente.
 */

class PasswordValidator
{
    // Umbrales de puntuación para nivel de fortaleza
    private const SCORE_WEAK   = 2;
    private const SCORE_MEDIUM = 4;

    // -----------------------------------------------------------------------
    // API pública
    // -----------------------------------------------------------------------

    /**
     * Valida una contraseña contra un conjunto de requisitos.
     *
     * @param string $password     Contraseña a evaluar.
     * @param array  $requirements Requisitos:
     *   - minLength       (int)  Longitud mínima             [default: 8]
     *   - maxLength       (int)  Longitud máxima             [default: 128]
     *   - requireUppercase(bool) Requiere al menos 1 mayúscula
     *   - requireLowercase(bool) Requiere al menos 1 minúscula
     *   - requireNumbers  (bool) Requiere al menos 1 número
     *   - requireSymbols  (bool) Requiere al menos 1 símbolo
     *
     * @return array {
     *   valid: bool,
     *   strength: string,   // 'weak' | 'medium' | 'strong'
     *   score: int,         // 0-6
     *   checks: array,      // resultado de cada verificación
     *   suggestions: array  // sugerencias de mejora
     * }
     */
    public function validate(string $password, array $requirements = []): array
    {
        $requirements = array_merge([
            'minLength'        => 8,
            'maxLength'        => 128,
            'requireUppercase' => false,
            'requireLowercase' => false,
            'requireNumbers'   => false,
            'requireSymbols'   => false,
        ], $requirements);

        $checks      = $this->runChecks($password, $requirements);
        $score       = $this->calculateScore($password);
        $strength    = $this->strengthLabel($score);
        $suggestions = $this->buildSuggestions($password, $checks);

        // La contraseña es válida si pasa TODOS los checks requeridos
        $valid = !in_array(false, array_values($checks), true);

        return [
            'valid'       => $valid,
            'strength'    => $strength,
            'score'       => $score,
            'checks'      => $checks,
            'suggestions' => $suggestions,
        ];
    }

    // -----------------------------------------------------------------------
    // Lógica interna
    // -----------------------------------------------------------------------

    private function runChecks(string $password, array $req): array
    {
        $len    = mb_strlen($password);
        $checks = [];

        $checks['minLength'] = $len >= $req['minLength'];
        $checks['maxLength'] = $len <= $req['maxLength'];

        if ($req['requireUppercase']) {
            $checks['hasUppercase'] = (bool) preg_match('/[A-Z]/', $password);
        }
        if ($req['requireLowercase']) {
            $checks['hasLowercase'] = (bool) preg_match('/[a-z]/', $password);
        }
        if ($req['requireNumbers']) {
            $checks['hasNumbers'] = (bool) preg_match('/[0-9]/', $password);
        }
        if ($req['requireSymbols']) {
            $checks['hasSymbols'] = (bool) preg_match('/[^A-Za-z0-9]/', $password);
        }

        return $checks;
    }

    /**
     * Puntuación independiente de los requisitos del cliente (0–6).
     * Sirve para el campo "strength".
     */
    private function calculateScore(string $password): int
    {
        $score = 0;
        $len   = mb_strlen($password);

        if ($len >= 8)  $score++;
        if ($len >= 12) $score++;
        if ($len >= 16) $score++;

        if (preg_match('/[A-Z]/', $password))      $score++;
        if (preg_match('/[a-z]/', $password))      $score++;
        if (preg_match('/[0-9]/', $password))      $score++;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score++;

        return min($score, 6);
    }

    private function strengthLabel(int $score): string
    {
        if ($score <= self::SCORE_WEAK)   return 'weak';
        if ($score <= self::SCORE_MEDIUM) return 'medium';
        return 'strong';
    }

    private function buildSuggestions(string $password, array $checks): array
    {
        $suggestions = [];
        $len         = mb_strlen($password);

        if ($len < 12) {
            $suggestions[] = 'Aumenta la longitud a al menos 12 caracteres.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $suggestions[] = 'Agrega letras mayúsculas.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $suggestions[] = 'Agrega letras minúsculas.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $suggestions[] = 'Agrega números.';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $suggestions[] = 'Agrega símbolos especiales (!@#$...).';
        }

        // Verificar si algún check requerido falló
        foreach ($checks as $check => $passed) {
            if (!$passed) {
                $suggestions[] = match ($check) {
                    'minLength'    => "La contraseña no alcanza la longitud mínima requerida.",
                    'hasUppercase' => "Se requiere al menos una letra mayúscula.",
                    'hasLowercase' => "Se requiere al menos una letra minúscula.",
                    'hasNumbers'   => "Se requiere al menos un número.",
                    'hasSymbols'   => "Se requiere al menos un símbolo.",
                    default        => "Requisito '{$check}' no cumplido.",
                };
            }
        }

        return array_values(array_unique($suggestions));
    }
}
