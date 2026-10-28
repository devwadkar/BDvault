<?php
/**
 * RulesRegistry — single point of read/write for regulatory_constants.
 *
 * Every hardcoded DCPR value (5.40 FSI cap, 1.15 loading factor, 35% fungible, …)
 * lives in the regulatory_constants table. Calculators call:
 *
 *     RulesRegistry::get('fungible_pct_residential', 'global')       // 35.0
 *     RulesRegistry::get('rehab_loading_factor',    '33(10)')        // 1.283
 *     RulesRegistry::get('missing_key',             '33(11)', 4.0)   // 4.0 fallback
 *
 * Reads are memoised per request (single SELECT * on first hit; O(1) after).
 * Updates go through update() which writes an audit row automatically.
 */

require_once __DIR__ . '/../config/database.php';

class RulesRegistry {

    /** @var array<string,array>|null  scope|key => row (null until first read) */
    private static ?array $cache = null;

    private static function load(): void {
        if (self::$cache !== null) return;
        self::$cache = [];
        $db = getDB();
        $res = $db->query("SELECT * FROM regulatory_constants WHERE is_active = 1");
        if (!$res) return;
        while ($r = $res->fetch_assoc()) {
            self::$cache[self::mapKey($r['scope'], $r['rule_key'])] = $r;
        }
    }

    private static function mapKey(string $scope, string $ruleKey): string {
        return $scope . '|' . $ruleKey;
    }

    /**
     * Numeric value for (rule_key, scope). Falls back to $default when the row
     * is missing OR its numeric_value is null. Throws if no default was provided.
     */
    public static function get(string $ruleKey, string $scope = 'global', ?float $default = null): float {
        self::load();
        $row = self::$cache[self::mapKey($scope, $ruleKey)] ?? null;
        if ($row === null || $row['numeric_value'] === null) {
            if ($default === null) {
                throw new RuntimeException("Regulatory constant missing: $scope / $ruleKey (no default provided)");
            }
            return (float)$default;
        }
        return (float)$row['numeric_value'];
    }

    /**
     * Same as get() but never throws — returns $default (may be null) if the row is missing.
     * Use when the caller genuinely wants "did we have this configured or not?".
     */
    public static function getOrNull(string $ruleKey, string $scope = 'global'): ?float {
        self::load();
        $row = self::$cache[self::mapKey($scope, $ruleKey)] ?? null;
        return ($row && $row['numeric_value'] !== null) ? (float)$row['numeric_value'] : null;
    }

    /**
     * Full row (label, unit, regulation_ref, description, updated_at) — useful for the
     * calc-explorer traceability panel and the admin view.
     */
    public static function getRow(string $ruleKey, string $scope = 'global'): ?array {
        self::load();
        return self::$cache[self::mapKey($scope, $ruleKey)] ?? null;
    }

    /**
     * Every row (optionally filtered by scope), sorted by (scope, rule_key).
     */
    public static function getAll(?string $scope = null): array {
        self::load();
        $rows = array_values(self::$cache);
        if ($scope !== null) {
            $rows = array_values(array_filter($rows, fn($r) => $r['scope'] === $scope));
        }
        usort($rows, fn($a, $b) => [$a['scope'], $a['rule_key']] <=> [$b['scope'], $b['rule_key']]);
        return $rows;
    }

    /**
     * List of distinct scopes present in the table.
     */
    public static function scopes(): array {
        self::load();
        $scopes = array_unique(array_map(fn($r) => $r['scope'], self::$cache));
        sort($scopes);
        return $scopes;
    }

    /**
     * Update a constant. Writes an audit row with old→new + reason + user.
     * Returns true on success. Cache is invalidated so subsequent get() calls see the new value.
     */
    public static function update(int $id, float $newValue, ?string $reason = null, ?int $userId = null): bool {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, rule_key, scope, numeric_value FROM regulatory_constants WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        if (!$existing) return false;

        $oldVal = $existing['numeric_value'];
        if ((float)$oldVal === $newValue) return true;

        $upd = $db->prepare("UPDATE regulatory_constants
                             SET numeric_value = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
                             WHERE id = ?");
        $upd->bind_param('dii', $newValue, $userId, $id);
        if (!$upd->execute()) return false;

        $oldStr = (string)$oldVal;
        $newStr = (string)$newValue;
        $aud = $db->prepare("INSERT INTO regulatory_constants_audit
                              (constant_id, rule_key, scope, old_value, new_value, change_reason, changed_by)
                             VALUES (?, ?, ?, ?, ?, ?, ?)");
        $aud->bind_param('isssssi',
            $id, $existing['rule_key'], $existing['scope'], $oldStr, $newStr, $reason, $userId);
        $aud->execute();

        self::$cache = null;   // invalidate memoised cache
        return true;
    }

    /**
     * Audit history for a constant (newest first).
     */
    public static function audit(int $constantId, int $limit = 50): array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM regulatory_constants_audit
                              WHERE constant_id = ?
                              ORDER BY changed_at DESC LIMIT ?");
        $stmt->bind_param('ii', $constantId, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        return $rows;
    }
}
