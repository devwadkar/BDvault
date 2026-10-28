<?php
/**
 * Minimal .env loader — no dependencies, no autoloaders.
 *
 * Reads KEY=value lines from `<project_root>/.env` and populates them via
 * `putenv()` + $_ENV + $_SERVER so that `getenv()` picks them up.
 *
 * Rules:
 *   • Existing environment variables are NEVER overwritten — real OS env
 *     (SetEnv in Apache, Docker -e, systemd EnvironmentFile) always wins.
 *   • Lines starting with `#` are comments. Blank lines are skipped.
 *   • Values may be bare, single-quoted ('…'), or double-quoted ("…").
 *   • No variable interpolation (`${OTHER}` is NOT expanded). Keep it simple.
 *   • Silently returns if `.env` doesn't exist — this is the local-XAMPP
 *     case where defaults in config/database.php are used instead.
 *
 * `.env` MUST be in `.gitignore`. See `.env.example` for the required keys.
 */

(static function (): void {
    $root = dirname(__DIR__);
    $envFile = $root . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($envFile) || !is_readable($envFile)) return;

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $eq = strpos($line, '=');
        if ($eq === false) continue;

        $key = trim(substr($line, 0, $eq));
        $val = trim(substr($line, $eq + 1));

        // Reject anything that doesn't look like a sane env var name.
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) continue;

        // Strip paired surrounding quotes (one layer only).
        $len = strlen($val);
        if ($len >= 2
            && (($val[0] === '"' && $val[$len - 1] === '"')
             || ($val[0] === "'" && $val[$len - 1] === "'"))) {
            $val = substr($val, 1, $len - 2);
        }

        // Don't override anything that's already set in the real environment.
        if (getenv($key) !== false) continue;

        putenv("{$key}={$val}");
        $_ENV[$key]    = $val;
        $_SERVER[$key] = $val;
    }
})();
