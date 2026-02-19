<?php

declare(strict_types=1);

/**
 * Gestiona la conexión SQLite y garantiza que el esquema exista.
 *
 * El archivo .db se crea automáticamente en data/ la primera vez.
 * No requiere configuración manual ni permisos especiales más allá
 * de que PHP pueda escribir en el directorio data/.
 *
 * Esquema:
 *
 *  urls
 *  ----
 *  id            INTEGER PK AUTOINCREMENT
 *  code          TEXT UNIQUE        → código corto (ej: aB3xZ)
 *  original_url  TEXT               → URL original completa
 *  created_at    TEXT               → ISO 8601
 *  creator_ip    TEXT               → IP de quien creó el enlace
 *  expires_at    TEXT NULL          → fecha de expiración (ISO 8601) o NULL
 *  max_uses      INTEGER NULL       → límite de usos o NULL
 *  visit_count   INTEGER DEFAULT 0  → contador total de visitas
 *  is_active     INTEGER DEFAULT 1  → 1 activo / 0 desactivado
 *
 *  visits
 *  ------
 *  id         INTEGER PK AUTOINCREMENT
 *  url_id     INTEGER FK → urls.id
 *  visited_at TEXT       → ISO 8601
 *  visitor_ip TEXT
 *  user_agent TEXT
 */
class Database
{
    private static ?PDO $instance = null;

    private const DB_PATH = __DIR__ . '/data/short.db';

    // -----------------------------------------------------------------------
    // Singleton
    // -----------------------------------------------------------------------

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::connect();
        }
        return self::$instance;
    }

    private static function connect(): PDO
    {
        // Verificar que el directorio data/ sea escribible
        $dir = dirname(self::DB_PATH);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException("No se pudo crear el directorio de datos: {$dir}");
        }
        if (!is_writable($dir)) {
            throw new RuntimeException("El directorio de datos no tiene permisos de escritura: {$dir}");
        }

        $pdo = new PDO('sqlite:' . self::DB_PATH);

        // Configuración de PDO
        $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Habilitar WAL para mejor concurrencia (múltiples lecturas simultáneas)
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');

        self::createSchema($pdo);

        return $pdo;
    }

    private static function createSchema(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS urls (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                code        TEXT    NOT NULL UNIQUE,
                original_url TEXT   NOT NULL,
                created_at  TEXT    NOT NULL,
                creator_ip  TEXT    NOT NULL,
                expires_at  TEXT    NULL,
                max_uses    INTEGER NULL,
                visit_count INTEGER NOT NULL DEFAULT 0,
                is_active   INTEGER NOT NULL DEFAULT 1
            );

            CREATE TABLE IF NOT EXISTS visits (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                url_id     INTEGER NOT NULL REFERENCES urls(id) ON DELETE CASCADE,
                visited_at TEXT    NOT NULL,
                visitor_ip TEXT    NOT NULL,
                user_agent TEXT    NOT NULL DEFAULT ''
            );

            CREATE INDEX IF NOT EXISTS idx_urls_code      ON urls(code);
            CREATE INDEX IF NOT EXISTS idx_visits_url_id  ON visits(url_id);
            CREATE INDEX IF NOT EXISTS idx_visits_visited ON visits(visited_at);
        ");
    }
}
