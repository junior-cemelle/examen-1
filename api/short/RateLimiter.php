<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

/**
 * Limita el número de peticiones de creación por IP en una ventana de tiempo.
 * Usa la misma base de datos SQLite; crea su propia tabla si no existe.
 *
 * Estrategia: ventana deslizante de 1 hora.
 * Si una IP supera MAX_REQUESTS en la última hora → HTTP 429.
 */
class RateLimiter
{
    private const MAX_REQUESTS  = 30;   // máximo de URLs acortadas por IP por hora
    private const WINDOW_SECONDS = 3600; // ventana de 1 hora

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTable();
    }

    // -----------------------------------------------------------------------
    // API pública
    // -----------------------------------------------------------------------

    /**
     * Verifica si la IP puede realizar otra petición.
     *
     * @throws RuntimeException con código 429 si se supera el límite.
     */
    public function check(string $ip): void
    {
        $this->cleanup();

        $windowStart = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS cnt FROM rate_limit WHERE ip = :ip AND requested_at > :window'
        );
        $stmt->execute([':ip' => $ip, ':window' => $windowStart]);
        $count = (int) $stmt->fetchColumn();

        if ($count >= self::MAX_REQUESTS) {
            throw new RuntimeException(
                'Has superado el límite de ' . self::MAX_REQUESTS
                . ' URLs acortadas por hora. Intenta más tarde.',
                429
            );
        }
    }

    /**
     * Registra una petición exitosa de la IP.
     */
    public function record(string $ip): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO rate_limit (ip, requested_at) VALUES (:ip, :now)'
        );
        $stmt->execute([':ip' => $ip, ':now' => date('Y-m-d H:i:s')]);
    }

    // -----------------------------------------------------------------------
    // Privado
    // -----------------------------------------------------------------------

    private function ensureTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS rate_limit (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                ip           TEXT NOT NULL,
                requested_at TEXT NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_rate_ip ON rate_limit(ip);
        ");
    }

    /** Elimina entradas antiguas fuera de la ventana para no crecer indefinidamente */
    private function cleanup(): void
    {
        $cutoff = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
        $this->db->prepare('DELETE FROM rate_limit WHERE requested_at < :cutoff')
                 ->execute([':cutoff' => $cutoff]);
    }
}
