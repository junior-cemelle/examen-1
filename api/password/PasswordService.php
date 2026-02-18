<?php

require_once __DIR__ . '/GenPassword.php';
require_once __DIR__ . '/PasswordValidator.php';

/**
 * Capa de servicio: orquesta GenPassword y PasswordValidator.
 * Traduce los parámetros HTTP (camelCase del cliente) a las
 * opciones internas de GenPassword (snake_case).
 */
class PasswordService
{
    private PasswordValidator $validator;

    public function __construct()
    {
        $this->validator = new PasswordValidator();
    }

    // -----------------------------------------------------------------------
    // Generación
    // -----------------------------------------------------------------------

    /**
     * Genera UNA contraseña a partir de parámetros HTTP normalizados.
     *
     * Parámetros aceptados (query string o body JSON):
     *   length           int    Longitud (4–128)       default 16
     *   includeUppercase bool                          default true
     *   includeLowercase bool                          default true
     *   includeNumbers   bool                          default true
     *   includeSymbols   bool                          default true
     *   excludeAmbiguous bool                          default true
     *   exclude          string Caracteres a excluir   default ''
     *
     * @param array $params Parámetros ya parseados (merged query + body).
     * @return array Respuesta lista para JSON.
     * @throws InvalidArgumentException
     */
    public function generateOne(array $params): array
    {
        [$length, $opts] = $this->parseParams($params);

        $password = GenPassword::generate($length, $opts);

        return [
            'password' => $password,
            'length'   => mb_strlen($password),
            'options'  => $this->describeOptions($opts),
        ];
    }

    /**
     * Genera MÚLTIPLES contraseñas.
     *
     * Parámetros extra:
     *   count int Número de contraseñas (1–100) default 5
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     */
    public function generateMany(array $params): array
    {
        $count = isset($params['count']) ? (int) $params['count'] : 5;

        if ($count < 1 || $count > 100) {
            throw new InvalidArgumentException('El campo count debe estar entre 1 y 100.');
        }

        [$length, $opts] = $this->parseParams($params);

        $passwords = GenPassword::generateMany($count, $length, $opts);

        return [
            'passwords' => $passwords,
            'count'     => count($passwords),
            'length'    => $length,
            'options'   => $this->describeOptions($opts),
        ];
    }

    // -----------------------------------------------------------------------
    // Validación
    // -----------------------------------------------------------------------

    /**
     * Valida una contraseña existente.
     *
     * Parámetros:
     *   password     string  Contraseña a evaluar
     *   requirements array   Requisitos (ver PasswordValidator::validate)
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     */
    public function validatePassword(array $params): array
    {
        if (empty($params['password']) || !is_string($params['password'])) {
            throw new InvalidArgumentException("El campo 'password' es requerido y debe ser texto.");
        }

        $password     = $params['password'];
        $requirements = $params['requirements'] ?? [];

        if (!is_array($requirements)) {
            throw new InvalidArgumentException("El campo 'requirements' debe ser un objeto JSON.");
        }

        return $this->validator->validate($password, $requirements);
    }

    // -----------------------------------------------------------------------
    // Helpers privados
    // -----------------------------------------------------------------------

    /**
     * Convierte parámetros HTTP a [length, opts] internos.
     */
    private function parseParams(array $params): array
    {
        $length = isset($params['length']) ? (int) $params['length'] : 16;

        // Límites de seguridad
        if ($length < 4 || $length > 128) {
            throw new InvalidArgumentException(
                'El parámetro length debe estar entre 4 y 128.'
            );
        }

        $opts = [
            'upper'           => $this->parseBool($params, 'includeUppercase', true),
            'lower'           => $this->parseBool($params, 'includeLowercase', true),
            'digits'          => $this->parseBool($params, 'includeNumbers',   true),
            'symbols'         => $this->parseBool($params, 'includeSymbols',   false),
            'avoid_ambiguous' => $this->parseBool($params, 'excludeAmbiguous', true),
            'exclude'         => isset($params['exclude']) ? (string) $params['exclude'] : '',
            'require_each'    => true,
        ];

        return [$length, $opts];
    }

    /**
     * Lee un booleano flexible desde params: acepta true/false, "true"/"false", 1/0.
     */
    private function parseBool(array $params, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $params)) return $default;

        $val = $params[$key];

        if (is_bool($val)) return $val;

        if (is_string($val)) {
            return in_array(strtolower($val), ['true', '1', 'yes'], true);
        }

        return (bool) $val;
    }

    /**
     * Construye un array legible de las opciones usadas (para incluir en respuesta).
     */
    private function describeOptions(array $opts): array
    {
        return [
            'includeUppercase' => $opts['upper'],
            'includeLowercase' => $opts['lower'],
            'includeNumbers'   => $opts['digits'],
            'includeSymbols'   => $opts['symbols'],
            'excludeAmbiguous' => $opts['avoid_ambiguous'],
            'customExclude'    => $opts['exclude'],
        ];
    }
}
