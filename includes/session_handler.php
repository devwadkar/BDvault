<?php
/**
 * DatabaseSessionHandler
 * Stores PHP sessions in MySQL instead of the filesystem.
 * This solves flock() failures on NFS/block storage mounts (e.g. Cloudways).
 */
class DatabaseSessionHandler implements SessionHandlerInterface {

    private mysqli $db;
    private string $table = 'php_sessions';

    public function __construct(mysqli $db) {
        $this->db = $db;
    }

    public function open(string $path, string $name): bool {
        // Ensure the sessions table exists
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `{$this->table}` (
                `session_id`   VARCHAR(128)  NOT NULL,
                `session_data` MEDIUMTEXT    NOT NULL DEFAULT '',
                `expires_at`   INT UNSIGNED  NOT NULL,
                PRIMARY KEY (`session_id`),
                KEY `idx_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        $lifetime = (int) ini_get('session.gc_maxlifetime');
        $stmt = $this->db->prepare(
            "SELECT session_data FROM `{$this->table}` WHERE session_id = ? AND expires_at >= ?"
        );
        $now = time();
        $stmt->bind_param('si', $id, $now);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row['session_data'];
        }
        return '';
    }

    public function write(string $id, string $data): bool {
        $lifetime = (int) ini_get('session.gc_maxlifetime');
        $expires  = time() + $lifetime;
        $stmt = $this->db->prepare(
            "INSERT INTO `{$this->table}` (session_id, session_data, expires_at)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE session_data = VALUES(session_data), expires_at = VALUES(expires_at)"
        );
        $stmt->bind_param('ssi', $id, $data, $expires);
        return $stmt->execute();
    }

    public function destroy(string $id): bool {
        $stmt = $this->db->prepare("DELETE FROM `{$this->table}` WHERE session_id = ?");
        $stmt->bind_param('s', $id);
        return $stmt->execute();
    }

    public function gc(int $max_lifetime): int|false {
        $stmt = $this->db->prepare("DELETE FROM `{$this->table}` WHERE expires_at < ?");
        $now = time();
        $stmt->bind_param('i', $now);
        $stmt->execute();
        return $stmt->affected_rows;
    }
}

/**
 * Bootstrap the database session handler.
 * Call this BEFORE session_start() anywhere in the application.
 */
function startDatabaseSession(): void {
    // Anti-caching headers to prevent proxy/Varnish from caching responses with cookies
    if (!headers_sent()) {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");
    }

    // Secure cookie settings
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
               || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
    }

    // Register the database handler
    $db = getDB();
    $handler = new DatabaseSessionHandler($db);
    session_set_save_handler($handler, true);

    session_start();
}
