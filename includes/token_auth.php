<?php
/**
 * TokenAuth — Stateless cookie-based auth backed by database.
 * Replaces PHP sessions entirely to bypass Varnish/NFS session failures on Cloudways.
 *
 * How it works:
 *  1. On login: generate a secure random token, store in DB, set as a plain cookie.
 *  2. On each request: read the cookie, look up the token in DB, load the user.
 *  3. All existing $_SESSION reads are emulated via a static $data array.
 *  4. On logout: delete token from DB and expire the cookie.
 */
class TokenAuth {

    private static string $cookieName   = 'PHPSESSID';
    private static int    $lifetime     = 86400 * 7; // 7 days
    private static array  $data         = [];
    private static bool   $initialized  = false;
    private static ?string $activeToken = null;

    // ───────────────────────────────────────────────────────────────────────────
    // Bootstrap: call once at the top of index.php instead of session_start()
    // ───────────────────────────────────────────────────────────────────────────
    public static function boot(): void {
        if (self::$initialized) return;
        self::$initialized = true;

        // Anti-Varnish/proxy cache headers — must be sent before any output
        if (!headers_sent()) {
            header("Cache-Control: private, no-store, no-cache, must-revalidate");
            header("Pragma: no-cache");
            header("Surrogate-Control: no-store");
            header("X-Accel-Expires: 0");
        }

        self::ensureTable();

        $token = $_COOKIE[self::$cookieName] ?? '';
        if ($token !== '') {
            self::$activeToken = $token;
            self::loadFromToken($token);
        }
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Session-like read/write (emulates $_SESSION for backward compatibility)
    // ───────────────────────────────────────────────────────────────────────────
    public static function get(string $key, mixed $default = null): mixed {
        return self::$data[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void {
        self::$data[$key] = $value;
        if (self::$activeToken) {
            self::persistData();
        }
    }

    public static function has(string $key): bool {
        return isset(self::$data[$key]);
    }

    public static function all(): array {
        return self::$data;
    }

    // Flash messages
    public static function flash(string $key, string $value): void {
        $flashes = self::get('_flash', []);
        $flashes[$key] = $value;
        self::set('_flash', $flashes);
    }

    public static function getFlash(string $key): ?string {
        $flashes = self::get('_flash', []);
        if (isset($flashes[$key])) {
            $val = $flashes[$key];
            unset($flashes[$key]);
            self::set('_flash', $flashes);
            return $val;
        }
        return null;
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Login: create token, set cookie, store user data
    // ───────────────────────────────────────────────────────────────────────────
    public static function login(array $user): void {
        $token = bin2hex(random_bytes(32)); // 64-char secure token
        $expires = time() + self::$lifetime;
        $payload = json_encode([
            'user_id'   => $user['id'],
            'username'  => $user['username'],
            'full_name' => $user['full_name'],
            'role'      => $user['role'] ?? 'user',
        ]);

        $db = getDB();
        $stmt = $db->prepare(
            "INSERT INTO auth_tokens (token, user_id, payload, expires_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE payload = VALUES(payload), expires_at = VALUES(expires_at)"
        );
        $stmt->bind_param('sisi', $token, $user['id'], $payload, $expires);
        $stmt->execute();

        self::$activeToken = $token;
        self::$data = json_decode($payload, true);

        self::setCookie($token, $expires);
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Logout: delete token, expire cookie
    // ───────────────────────────────────────────────────────────────────────────
    public static function logout(): void {
        if (self::$activeToken) {
            $db = getDB();
            $stmt = $db->prepare("DELETE FROM auth_tokens WHERE token = ?");
            $stmt->bind_param('s', self::$activeToken);
            $stmt->execute();
        }
        self::$data = [];
        self::$activeToken = null;
        self::setCookie('', time() - 3600); // Expire the cookie
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Helpers
    // ───────────────────────────────────────────────────────────────────────────
    public static function isLoggedIn(): bool {
        return !empty(self::$data['user_id']);
    }

    public static function userId(): ?int {
        return self::$data['user_id'] ?? null;
    }

    public static function username(): ?string {
        return self::$data['username'] ?? null;
    }

    public static function fullName(): ?string {
        return self::$data['full_name'] ?? null;
    }

    public static function role(): string {
        return self::$data['role'] ?? 'user';
    }

    public static function isAdmin(): bool {
        return self::role() === 'admin';
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Internal
    // ───────────────────────────────────────────────────────────────────────────
    private static function loadFromToken(string $token): void {
        $db = getDB();
        $now = time();
        $stmt = $db->prepare(
            "SELECT payload FROM auth_tokens WHERE token = ? AND expires_at > ?"
        );
        $stmt->bind_param('si', $token, $now);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            self::$data = json_decode($row['payload'], true) ?? [];
        } else {
            // Token is invalid/expired — clear the cookie
            self::setCookie('', time() - 3600);
        }
    }

    private static function persistData(): void {
        if (!self::$activeToken) return;
        $db = getDB();
        $payload = json_encode(self::$data);
        $stmt = $db->prepare("UPDATE auth_tokens SET payload = ? WHERE token = ?");
        $stmt->bind_param('ss', $payload, self::$activeToken);
        $stmt->execute();
    }

    private static function setCookie(string $value, int $expires): void {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                   || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443
                   || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        // Use setcookie() with options array (PHP 7.3+) for SameSite support
        setcookie(self::$cookieName, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function ensureTable(): void {
        $db = getDB();
        $db->query("
            CREATE TABLE IF NOT EXISTS `auth_tokens` (
                `token`      VARCHAR(64)   NOT NULL,
                `user_id`    INT           NOT NULL,
                `payload`    TEXT          NOT NULL,
                `expires_at` INT UNSIGNED  NOT NULL,
                PRIMARY KEY (`token`),
                KEY `idx_user_id`  (`user_id`),
                KEY `idx_expires`  (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
