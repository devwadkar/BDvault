<?php
/**
 * CalcOverrideManager — manual overrides on any persisted calc_key, with audit.
 *
 * A manager who needs to force a specific value (approved by SRA, negotiated with
 * planning authority, revised by the architect) can override any calc row without
 * touching the calculator. The override is stored, tagged onto the calc row, and
 * every activate/edit/deactivate is captured in the audit table.
 *
 * Propagation: this MVP applies overrides at PERSIST time — the persisted row's
 * calc_value is the override, its original_value is preserved. Downstream calcs
 * that consumed the ORIGINAL value in the calculator's math are NOT recomputed;
 * the views show a stale-downstream warning when a foundational key is overridden.
 *
 * Whitelist of "foundational" keys whose override implies downstream may be stale
 * lives in FOUNDATIONAL_KEYS below — the UI uses this to decide the warning.
 */

require_once __DIR__ . '/../config/database.php';

class CalcOverrideManager {

    /**
     * Overriding any of these means one or more downstream rows probably need
     * re-derivation the engine doesn't do automatically.
     */
    public const FOUNDATIONAL_KEYS = [
        'gross_plot_area_sqm', 'net_plot_area_sqm', 'plot_area_for_fsi_sqm',
        'rehab_tenement_bua_sqm', 'rehab_amenity_block_sqm', 'rehab_component_sqm',
        'sale_ratio_effective', 'sale_component_sqm',
        'total_bua_approved_sqm', 'full_scheme_bua_sqm',
        'zonal_bua_sqm', 'additional_bua_sqm',
    ];

    /**
     * Apply a new override (or replace the existing one for this key). Writes
     * an audit row for either 'applied' (first time) or 'edited' (subsequent).
     * Returns the override row id.
     */
    public static function apply(int $statementId, string $calcKey, float $overrideValue,
                                  string $reason, ?int $userId = null): int {
        $db = getDB();
        $reason = trim($reason);
        if ($reason === '') throw new InvalidArgumentException('Override reason is required for the audit trail.');

        // Look up the calculator's current value for original_value
        $original = null;
        $stmt = $db->prepare("SELECT calc_value, is_overridden, original_value
                              FROM area_statement_calcs
                              WHERE statement_id = ? AND calc_key = ?");
        $stmt->bind_param('is', $statementId, $calcKey);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            // Prefer original_value if already overridden (don't overwrite the true baseline)
            $original = ($row['is_overridden'] && $row['original_value'] !== null)
                ? (float)$row['original_value']
                : (float)$row['calc_value'];
        }

        // Deactivate any existing active override for this (statement, key)
        $existingStmt = $db->prepare("SELECT id, override_value FROM area_statement_calc_overrides
                                      WHERE statement_id = ? AND calc_key = ? AND active = 1
                                      ORDER BY id DESC LIMIT 1");
        $existingStmt->bind_param('is', $statementId, $calcKey);
        $existingStmt->execute();
        $existing = $existingStmt->get_result()->fetch_assoc();
        $isEdit = (bool)$existing;
        $oldValue = $existing ? (float)$existing['override_value'] : null;

        if ($existing) {
            $db->query("UPDATE area_statement_calc_overrides SET active = 0
                        WHERE id = " . (int)$existing['id']);
        }

        // Insert the new active override
        $ins = $db->prepare("INSERT INTO area_statement_calc_overrides
            (statement_id, calc_key, override_value, original_value, reason, active, created_by)
            VALUES (?, ?, ?, ?, ?, 1, ?)");
        $ins->bind_param('isddsi', $statementId, $calcKey, $overrideValue, $original, $reason, $userId);
        $ins->execute();
        $overrideId = $db->insert_id;

        // Write audit
        self::writeAudit($overrideId, $statementId, $calcKey,
            $isEdit ? 'edited' : 'applied', $oldValue, $overrideValue, $reason, $userId);

        // Apply to the persisted calc row so it renders immediately
        self::syncCalcRow($statementId, $calcKey, $overrideValue, $original, $overrideId);

        return $overrideId;
    }

    /**
     * Deactivate an override — restores the calculator's original value on the calc row.
     */
    public static function deactivate(int $overrideId, ?int $userId, string $reason = ''): bool {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM area_statement_calc_overrides WHERE id = ?");
        $stmt->bind_param('i', $overrideId);
        $stmt->execute();
        $ov = $stmt->get_result()->fetch_assoc();
        if (!$ov) return false;

        $db->query("UPDATE area_statement_calc_overrides SET active = 0
                    WHERE id = " . (int)$overrideId);

        self::writeAudit((int)$overrideId, (int)$ov['statement_id'], $ov['calc_key'],
            'deactivated', (float)$ov['override_value'], null, $reason, $userId);

        // Restore the calc row to original_value (if we captured it)
        $original = $ov['original_value'] !== null ? (float)$ov['original_value'] : null;
        if ($original !== null) {
            self::syncCalcRow((int)$ov['statement_id'], $ov['calc_key'], $original, null, null);
        } else {
            // No original recorded — just clear the flag; recalc will fully restore
            $db->query("UPDATE area_statement_calcs
                        SET is_overridden = 0, original_value = NULL, override_id = NULL
                        WHERE statement_id = " . (int)$ov['statement_id']
                        . " AND calc_key = '" . $db->real_escape_string($ov['calc_key']) . "'");
        }
        return true;
    }

    /**
     * Load active overrides for a statement as [calc_key => row].
     */
    public static function loadActiveMap(int $statementId): array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM area_statement_calc_overrides
                              WHERE statement_id = ? AND active = 1");
        $stmt->bind_param('i', $statementId);
        $stmt->execute();
        $res = $stmt->get_result();
        $map = [];
        while ($r = $res->fetch_assoc()) $map[$r['calc_key']] = $r;
        return $map;
    }

    /**
     * List every override for a statement (active first).
     */
    public static function listAll(int $statementId): array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM area_statement_calc_overrides
                              WHERE statement_id = ?
                              ORDER BY active DESC, created_at DESC");
        $stmt->bind_param('i', $statementId);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        while ($r = $res->fetch_assoc()) $out[] = $r;
        return $out;
    }

    /**
     * Full audit trail for a statement (or for a single calc_key).
     */
    public static function audit(int $statementId, ?string $calcKey = null, int $limit = 200): array {
        $db = getDB();
        if ($calcKey) {
            $stmt = $db->prepare("SELECT * FROM area_statement_calc_overrides_audit
                                  WHERE statement_id = ? AND calc_key = ?
                                  ORDER BY changed_at DESC LIMIT ?");
            $stmt->bind_param('isi', $statementId, $calcKey, $limit);
        } else {
            $stmt = $db->prepare("SELECT * FROM area_statement_calc_overrides_audit
                                  WHERE statement_id = ?
                                  ORDER BY changed_at DESC LIMIT ?");
            $stmt->bind_param('ii', $statementId, $limit);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        return $rows;
    }

    /**
     * Called by AreaCalcEngine::run() after compute(), before persistCalcs.
     * Rewrites the $rows array in-place: any row whose calc_key has an active
     * override gets its value replaced and picks up the is_overridden flag +
     * original_value + override_id. Returns count of overrides applied.
     */
    public static function applyToRows(int $statementId, array &$rows): int {
        $map = self::loadActiveMap($statementId);
        if (empty($map)) return 0;

        $applied = 0;
        $now = date('Y-m-d H:i:s');

        foreach ($rows as &$r) {
            $k = $r['calc_key'] ?? '';
            if (!isset($map[$k])) continue;
            $ov = $map[$k];
            $original = (float)$r['calc_value'];
            $r['original_value'] = $original;
            $r['calc_value']     = (float)$ov['override_value'];
            $r['is_overridden']  = 1;
            $r['override_id']    = (int)$ov['id'];

            // Keep the override table's original_value in sync with what the
            // calculator emitted THIS pass (in case the calc changed since the
            // override was first applied). This preserves audit accuracy.
            $db = getDB();
            $upd = $db->prepare("UPDATE area_statement_calc_overrides
                                 SET original_value = ?, updated_at = ?
                                 WHERE id = ?");
            $upd->bind_param('dsi', $original, $now, $ov['id']);
            $upd->execute();

            $applied++;
        }
        unset($r);
        return $applied;
    }

    /**
     * Whether overriding this key implies downstream rows are stale.
     */
    public static function isFoundational(string $calcKey): bool {
        return in_array($calcKey, self::FOUNDATIONAL_KEYS, true);
    }

    // ─── Helpers ────────────────────────────────────────────────────

    private static function writeAudit(?int $overrideId, int $statementId, string $calcKey,
                                        string $action, ?float $oldValue, ?float $newValue,
                                        ?string $reason, ?int $userId): void {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO area_statement_calc_overrides_audit
            (override_id, statement_id, calc_key, action, old_value, new_value, reason, changed_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iissddsi', $overrideId, $statementId, $calcKey, $action,
            $oldValue, $newValue, $reason, $userId);
        $stmt->execute();
    }

    /**
     * Directly rewrite the calc row for immediate rendering (no need to re-run engine).
     * Used by apply()/deactivate() so overrides are visible without a recalc.
     */
    private static function syncCalcRow(int $statementId, string $calcKey,
                                         float $newValue, ?float $originalValue,
                                         ?int $overrideId): void {
        $db = getDB();
        if ($overrideId === null) {
            $stmt = $db->prepare("UPDATE area_statement_calcs
                                  SET calc_value = ?, is_overridden = 0,
                                      original_value = NULL, override_id = NULL
                                  WHERE statement_id = ? AND calc_key = ?");
            $stmt->bind_param('dis', $newValue, $statementId, $calcKey);
        } else {
            $stmt = $db->prepare("UPDATE area_statement_calcs
                                  SET calc_value = ?, is_overridden = 1,
                                      original_value = ?, override_id = ?
                                  WHERE statement_id = ? AND calc_key = ?");
            $stmt->bind_param('ddiis', $newValue, $originalValue, $overrideId,
                              $statementId, $calcKey);
        }
        $stmt->execute();
    }
}
