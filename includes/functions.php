<?php
// ─── General helpers ────────────────────────────────────────────────────────

function e(?string $val): string {
    return htmlspecialchars($val ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatINR(float $val, int $decimals = 0): string {
    return '₹' . number_format($val, $decimals);
}

function formatPct(float $val, int $decimals = 1): string {
    return number_format($val, $decimals) . '%';
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function flashSet(string $type, string $msg): void {
    TokenAuth::flash('flash_type', $type);
    TokenAuth::flash('flash_msg', $msg);
}

function flashGet(): ?array {
    $type = TokenAuth::getFlash('flash_type');
    $msg  = TokenAuth::getFlash('flash_msg');
    if ($type && $msg) {
        return ['type' => $type, 'msg' => $msg];
    }
    return null;
}

// ─── Logistic probability ───────────────────────────────────────────────────

function scoreToProbability(float $score): float {
    $k   = LOGISTIC_K;
    $mid = LOGISTIC_MIDPOINT;
    return round(100 / (1 + exp(-$k * ($score - $mid))), 1);
}

// ─── Category from score ────────────────────────────────────────────────────

function scoreToCategory(float $score, array $cfg): string {
    if ($score >= $cfg['threshold_high'])     return CATEGORY_HIGH;
    if ($score >= $cfg['threshold_moderate']) return CATEGORY_MODERATE;
    return CATEGORY_LOW;
}

// ─── Sanitise & coerce helpers ──────────────────────────────────────────────

function safeFloat(?string $val): ?float {
    if ($val === null || $val === '') return null;
    // Strip currency symbols, commas, spaces, and other non-numeric chars (keep . and -)
    $clean = preg_replace('/[^\d.\-]/', '', str_replace(',', '.', trim($val)));
    // If multiple dots, keep only the last one (handles "1,234.56" → "1234.56")
    $parts = explode('.', $clean);
    if (count($parts) > 2) {
        $clean = implode('', array_slice($parts, 0, -1)) . '.' . end($parts);
    }
    return $clean !== '' && $clean !== '-' ? (float)$clean : null;
}

function safeInt(?string $val): ?int {
    if ($val === null || $val === '') return null;
    $clean = preg_replace('/[^\d\-]/', '', $val);
    return $clean !== '' ? (int)$clean : null;
}

function safeDate(?string $val): ?string {
    if (empty($val)) return null;
    // Accept DD/MM/YYYY or YYYY-MM-DD
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', trim($val), $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    $ts = strtotime($val);
    return $ts ? date('Y-m-d', $ts) : null;
}

// ─── Pagination helper ──────────────────────────────────────────────────────

function paginate(int $total, int $perPage, int $page): array {
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = max(1, min($page, $pages));
    return [
        'total'   => $total,
        'perPage' => $perPage,
        'page'    => $page,
        'pages'   => $pages,
        'offset'  => ($page - 1) * $perPage,
    ];
}

// ─── Excel column letter → index ────────────────────────────────────────────

function colLetterToIndex(string $col): int {
    $col   = strtoupper($col);
    $index = 0;
    for ($i = 0; $i < strlen($col); $i++) {
        $index = $index * 26 + (ord($col[$i]) - 64);
    }
    return $index - 1; // 0-based
}

// ─── Authentication helpers ──────────────────────────────────────────────────

function isLoggedIn(): bool {
    return TokenAuth::isLoggedIn();
}

function isAdmin(): bool {
    return TokenAuth::role() === 'admin';
}

/**
 * Check if the current user has a given permission key.
 * Delegates to the central Permission engine.
 */
function can(string $permission): bool {
    if (!isLoggedIn()) return false;
    return Permission::can($permission);
}

/**
 * Redirect with error if the current user lacks the given permission.
 */
function requirePermission(string $permission): void {
    requireLogin();
    if (!can($permission)) {
        flashSet('danger', 'Access denied. You do not have permission to perform this action.');
        redirect('index.php?page=dashboard');
    }
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        flashSet('warning', 'Please log in to access the system.');
        redirect('index.php?page=login');
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        flashSet('danger', 'Access denied. Administrator privileges required.');
        redirect('index.php?page=dashboard');
    }
}

function currentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'        => TokenAuth::userId(),
        'username'  => TokenAuth::username(),
        'full_name' => TokenAuth::fullName() ?? 'System User',
        'role'      => TokenAuth::role(),
    ];
}
