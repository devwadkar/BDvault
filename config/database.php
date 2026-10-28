<?php
/**
 * Database configuration.
 *
 * Local-dev (XAMPP on localhost / 127.0.0.1 / 192.168.*) uses the standard
 * XAMPP root/empty-password defaults — no env setup needed.
 *
 * Production reads from environment variables. Set them in your host's panel
 * (shared hosting cPanel, Apache SetEnv, systemd EnvironmentFile, Docker env,
 * .htaccess SetEnv, or an .env loader before this file is included).
 *
 * Required env vars in production:
 *   BDVAULT_DB_HOST, BDVAULT_DB_USER, BDVAULT_DB_PASS, BDVAULT_DB_NAME
 *
 * NEVER hard-code production credentials in this file. See .env.example.
 */

$isLocal = (php_sapi_name() === 'cli')
        || (isset($_SERVER['HTTP_HOST']) && (
               $_SERVER['HTTP_HOST'] === 'localhost'
            || $_SERVER['HTTP_HOST'] === '127.0.0.1'
            || strpos($_SERVER['HTTP_HOST'], '192.168.') !== false
           ));

if ($isLocal) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'pms_db');
} else {
    $host = getenv('BDVAULT_DB_HOST');
    $user = getenv('BDVAULT_DB_USER');
    $pass = getenv('BDVAULT_DB_PASS');
    $name = getenv('BDVAULT_DB_NAME');
    if ($host === false || $user === false || $pass === false || $name === false) {
        http_response_code(500);
        die('Database configuration missing. Set BDVAULT_DB_HOST / _USER / _PASS / _NAME env vars.');
    }
    define('DB_HOST', $host);
    define('DB_USER', $user);
    define('DB_PASS', $pass);
    define('DB_NAME', $name);
}
define('DB_CHARSET', 'utf8mb4');

function getDB(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
        }
        $conn->set_charset(DB_CHARSET);
    }
    return $conn;
}
