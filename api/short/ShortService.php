<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/UrlValidator.php';
require_once __DIR__ . '/ShortCodeGenerator.php';
require_once __DIR__ . '/RateLimiter.php';

/**
 * Capa de servicio principal del acortador.
 *
 * Métodos públicos:
 *  - shorten()   : acorta una URL y la persiste
 *  - resolve()   : busca la URL original por código y registra la visita
 *  - stats()     : devuelve estadísticas de uso de un código
 */
class ShortService
{
    private PDO               $db;
    private UrlValidator      $validator;
    private ShortCodeGenerator $codeGen;
    private RateLimiter       $rateLimiter;

    public function __construct()
    {
        $this->db          = Database::getInstance();
        $this->validator   = new UrlValidator();
        $this->codeGen     = new ShortCodeGenerator();
        $this->rateLimiter = new RateLimiter();
    }

    // -----------------------------------------------------------------------
    // 1. Acortar URL
    // -----------------------------------------------------------------------

    /**
     * Acorta una URL y la almacena.
     *
     * Parámetros aceptados:
     *   url        string   URL original (requerida)
     *   expiresAt  string   Fecha ISO 8601 o "YYYY-MM-DD" (opcional)
     *   maxUses    int      Máximo de redirecciones (opcional, >= 1)
     *   codeLength int      Longitud del código (mínimo 5, default 6)
     *
     * @return array Datos del enlace creado.
     * @throws InvalidArgumentException | RuntimeException
     */
    public function shorten(array $params, string $creatorIp): array
    {
        // Rate limiting
        $this->rateLimiter->check($creatorIp);

        // Validar URL
        $url = $params['url'] ?? '';
        if (!is_string($url)) {
            throw new InvalidArgumentException("El campo 'url' es requerido.");
        }

        $ownHost = $_SERVER['HTTP_HOST'] ?? '';
        $this->validator->validate($url, $ownHost);

        // Parámetros opcionales
        $expiresAt = $this->parseExpiry($params['expiresAt'] ?? null);
        $maxUses   = $this->parseMaxUses($params['maxUses']  ?? null);
        $codeLen   = isset($params['codeLength']) ? (int) $params['codeLength'] : 6;

        // Generar código único
        $code = $this->codeGen->generate(
            fn(string $c) => $this->codeExists($c),
            $codeLen
        );

        $now = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare("
            INSERT INTO urls (code, original_url, created_at, creator_ip, expires_at, max_uses)
            VALUES (:code, :url, :created_at, :creator_ip, :expires_at, :max_uses)
        ");
        $stmt->execute([
            ':code'       => $code,
            ':url'        => $url,
            ':created_at' => $now,
            ':creator_ip' => $creatorIp,
            ':expires_at' => $expiresAt,
            ':max_uses'   => $maxUses,
        ]);

        // Registrar en rate limiter
        $this->rateLimiter->record($creatorIp);

        $baseUrl = $this->buildBaseUrl();

        return [
            'code'      => $code,
            'shortUrl'  => "{$baseUrl}/api/short/{$code}",
            'originalUrl' => $url,
            'createdAt' => $now,
            'expiresAt' => $expiresAt,
            'maxUses'   => $maxUses,
        ];
    }

    // -----------------------------------------------------------------------
    // 2. Resolver código → URL original
    // -----------------------------------------------------------------------

    /**
     * Busca la URL original y registra la visita.
     *
     * @return string URL original a la que redirigir.
     * @throws RuntimeException con código 404 o 410.
     */
    public function resolve(string $code, string $visitorIp, string $userAgent): string
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM urls WHERE code = :code AND is_active = 1'
        );
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException("El código '{$code}' no existe.", 404);
        }

        // Verificar expiración
        if ($row['expires_at'] !== null && $row['expires_at'] < date('Y-m-d H:i:s')) {
            throw new RuntimeException(
                "El enlace corto ha expirado.",
                410
            );
        }

        // Verificar límite de usos
        if ($row['max_uses'] !== null && $row['visit_count'] >= (int) $row['max_uses']) {
            throw new RuntimeException(
                "El enlace corto ha alcanzado su límite de usos.",
                410
            );
        }

        // Registrar visita e incrementar contador (transacción)
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'UPDATE urls SET visit_count = visit_count + 1 WHERE id = :id'
            )->execute([':id' => $row['id']]);

            $this->db->prepare(
                'INSERT INTO visits (url_id, visited_at, visitor_ip, user_agent)
                 VALUES (:url_id, :visited_at, :visitor_ip, :user_agent)'
            )->execute([
                ':url_id'     => $row['id'],
                ':visited_at' => date('Y-m-d H:i:s'),
                ':visitor_ip' => $visitorIp,
                ':user_agent' => mb_substr($userAgent, 0, 512),
            ]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw new RuntimeException('Error al registrar la visita.', 500, $e);
        }

        return $row['original_url'];
    }

    // -----------------------------------------------------------------------
    // 3. Estadísticas
    // -----------------------------------------------------------------------

    /**
     * Devuelve estadísticas de uso de un código corto.
     *
     * @return array Estadísticas completas.
     * @throws RuntimeException con código 404.
     */
    public function stats(string $code): array
    {
        $stmt = $this->db->prepare('SELECT * FROM urls WHERE code = :code');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException("El código '{$code}' no existe.", 404);
        }

        // Visitas por día (últimos 30 días)
        $visitsByDay = $this->visitsByDay($row['id']);

        // Últimas 10 visitas
        $lastVisits = $this->lastVisits($row['id'], 10);

        // Visitantes únicos por IP
        $uniqueVisitors = $this->uniqueVisitors($row['id']);

        $baseUrl = $this->buildBaseUrl();

        return [
            'code'           => $row['code'],
            'shortUrl'       => "{$baseUrl}/api/short/{$row['code']}",
            'originalUrl'    => $row['original_url'],
            'createdAt'      => $row['created_at'],
            'expiresAt'      => $row['expires_at'],
            'maxUses'        => $row['max_uses'],
            'isActive'       => (bool) $row['is_active'],
            'totalVisits'    => (int) $row['visit_count'],
            'uniqueVisitors' => $uniqueVisitors,
            'visitsByDay'    => $visitsByDay,
            'lastVisits'     => $lastVisits,
        ];
    }

    // -----------------------------------------------------------------------
    // Helpers privados
    // -----------------------------------------------------------------------

    private function codeExists(string $code): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM urls WHERE code = :code');
        $stmt->execute([':code' => $code]);
        return $stmt->fetchColumn() !== false;
    }

    private function visitsByDay(int $urlId): array
    {
        $stmt = $this->db->prepare("
            SELECT DATE(visited_at) AS day, COUNT(*) AS visits
            FROM visits
            WHERE url_id = :url_id
              AND visited_at >= DATE('now', '-30 days')
            GROUP BY DATE(visited_at)
            ORDER BY day ASC
        ");
        $stmt->execute([':url_id' => $urlId]);
        return $stmt->fetchAll();
    }

    private function lastVisits(int $urlId, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT visited_at, visitor_ip, user_agent
            FROM visits
            WHERE url_id = :url_id
            ORDER BY visited_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':url_id', $urlId, PDO::PARAM_INT);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function uniqueVisitors(int $urlId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(DISTINCT visitor_ip) FROM visits WHERE url_id = :url_id'
        );
        $stmt->execute([':url_id' => $urlId]);
        return (int) $stmt->fetchColumn();
    }

    private function parseExpiry(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;

        if (!is_string($value)) {
            throw new InvalidArgumentException("'expiresAt' debe ser una cadena de fecha (YYYY-MM-DD o ISO 8601).");
        }

        $ts = strtotime($value);
        if ($ts === false) {
            throw new InvalidArgumentException("Formato de fecha inválido en 'expiresAt': '{$value}'.");
        }
        if ($ts <= time()) {
            throw new InvalidArgumentException("La fecha de expiración debe ser futura.");
        }

        return date('Y-m-d H:i:s', $ts);
    }

    private function parseMaxUses(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;

        $int = filter_var($value, FILTER_VALIDATE_INT);
        if ($int === false || $int < 1) {
            throw new InvalidArgumentException("'maxUses' debe ser un entero mayor o igual a 1.");
        }

        return $int;
    }

    private function buildBaseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Obtener el path base hasta /api/short (sin el script)
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $base   = rtrim(dirname(dirname(dirname($script))), '/');
        return "{$scheme}://{$host}{$base}";
    }
}
