<?php
/**
 * ApprovalApplicabilityEngine — deterministic, rule-based applicability resolver.
 *
 * Same philosophy as RegulationSuggester:
 *   • No LLM, no AI, no `eval`. Pure PHP.
 *   • Rules live in approval_master_applicability_rule and can be edited without
 *     a code change.
 *   • Each rule has a JSON condition evaluated recursively.
 *   • For every master, rules are tried in descending priority. The FIRST match
 *     wins. If no rule matches, the master defaults to `requires_verification`.
 *
 * Rule condition shape:
 *   { "all":  [c1, c2, ...] }        → every child must be true
 *   { "any":  [c1, c2, ...] }        → at least one child true
 *   { "not":  <condition> }          → inverse
 *   { "field": "zone_dp", "op": "in", "value": ["Coastal"] }   → leaf
 *
 * Supported leaf operators: eq, neq, in, not_in, gt, gte, lt, lte, exists, truthy, falsy
 *
 * `rationale_template` is a plain string. Tokens like {field} aren't expanded for
 * now — if you want context inside the rationale, embed it when authoring the rule.
 */

require_once __DIR__ . '/../config/database.php';

class ApprovalApplicabilityEngine {

    /**
     * Build the project context passed to rule evaluation.
     * Returns a flat associative array of facts known about the project.
     */
    public static function buildContext(int $statementId): array {
        $db = getDB();
        $r = $db->query("SELECT * FROM area_statements WHERE id=" . (int)$statementId);
        $s = $r ? $r->fetch_assoc() : null;
        if (!$s) return [];

        $draft = json_decode($s['draft_json'] ?? '{}', true) ?: [];
        $v2    = $draft['v2_stage1'] ?? [];
        $s2    = $draft['step_2']    ?? [];

        // Net plot — summary column is often NULL; fall back to land table
        $netPlot = (float)($s['net_plot_area_sqm'] ?? 0);
        if ($netPlot <= 0) {
            $lr = $db->query("SELECT area_sqm FROM area_statement_land
                              WHERE statement_id=" . (int)$statementId . " AND component_key='net_plot'");
            if ($lr && $row = $lr->fetch_assoc()) $netPlot = (float)$row['area_sqm'];
        }

        $gross = (float)($s2['gross_plot_area_sqm'] ?? ($v2['property_card_area_sqm'] ?? 0));
        $roadW = (float)($v2['primary_road_width_m'] ?? ($s2['primary_road_width_m'] ?? 0));
        $totalBua = (float)($s['total_permissible_bua_sqm'] ?? 0);
        $fsiRatio = $gross > 0 ? ($totalBua / $gross) : 0;

        $wardCode = strtoupper(trim((string)($s['ward'] ?? '')));
        // Pre-canonicalise airport-ish wards (near Mumbai airport: K, H, L, N some overlap)
        $airportWards = ['K/E','K/W','H/E','H/W','L','N','P/N','P/S'];

        return [
            // Core project
            'statement_id'          => (int)$s['id'],
            'proposal_id'           => (int)$s['proposal_id'],
            'planning_authority'    => (string)($s['planning_authority']  ?? ''),
            'development_type'      => (string)($s['development_type']    ?? ''),
            'selected_regulation_key' => (string)($s['selected_regulation_key'] ?? ''),
            'ward'                  => $wardCode,
            'ward_near_airport'     => in_array($wardCode, $airportWards, true),

            // Geometry
            'gross_plot_area_sqm'   => $gross,
            'net_plot_area_sqm'     => $netPlot,
            'primary_road_width_m'  => $roadW,
            'total_bua_sqm'         => $totalBua,
            'rehab_bua_sqm'         => (float)($s['rehab_bua_sqm'] ?? 0),
            'sale_bua_sqm'          => (float)($s['sale_bua_sqm'] ?? 0),
            'fsi_ratio'             => $fsiRatio,  // derived — proxy for high-rise

            // v2 Stage 1 extras
            'is_island_city'        => !empty($v2['is_island_city']),
            'zone_dp'               => (string)($v2['zone_dp'] ?? ''),
            'has_existing_construction' => !empty($v2['has_existing_construction']),
            'approx_tenant_count'   => (int)($v2['approx_tenant_count'] ?? 0),
            'approx_residential_units' => (int)($v2['approx_residential_units'] ?? 0),
            'approx_commercial_units'  => (int)($v2['approx_commercial_units']  ?? 0),

            // Common booleans the rules read
            'is_sra'                => in_array($s['development_type'] ?? '', ['sra'], true)
                                       || in_array($s['selected_regulation_key'] ?? '', ['33(10)','33(11)'], true),
            'is_mhada'              => in_array($s['development_type'] ?? '', ['mhada'], true)
                                       || ($s['planning_authority'] ?? '') === 'MHADA'
                                       || ($s['selected_regulation_key'] ?? '') === '33(5)',
            'is_cluster'            => ($s['development_type'] ?? '') === 'cluster',
            'is_land_development'   => ($s['development_type'] ?? '') === 'land_development',
            'is_private_ah'         => ($s['selected_regulation_key'] ?? '') === '33(20)(B)',
        ];
    }

    /**
     * Evaluate every active master against the context.
     * Returns map: master_id => [
     *   'applicability', 'rationale', 'rule_id', 'rule_priority',
     *   'already_registered'   (bool, from project_approval),
     *   'current_registered'   (string|null, what the register currently says),
     *   'has_changed'          (bool, whether engine answer differs from register)
     * ]
     */
    public static function evaluate(int $statementId): array {
        $db = getDB();
        $ctx = self::buildContext($statementId);

        // Fetch all active masters
        $masters = [];
        $mr = $db->query("SELECT id, code, standardized_name, record_type, category_key, typical_phase, requires_classification
                          FROM approval_master WHERE is_active=1 ORDER BY standardized_name");
        while ($mr && $row = $mr->fetch_assoc()) $masters[(int)$row['id']] = $row;

        // Fetch all active rules grouped by master
        $rulesByMaster = [];
        $rr = $db->query("SELECT * FROM approval_master_applicability_rule
                          WHERE is_active=1 ORDER BY master_id, priority DESC, id ASC");
        while ($rr && $row = $rr->fetch_assoc()) {
            $rulesByMaster[(int)$row['master_id']][] = $row;
        }

        // Current register contents (so we can mark already-added + detect diffs)
        $registered = [];
        $xr = $db->query("SELECT master_id, applicability FROM project_approval
                          WHERE statement_id=" . (int)$statementId . " AND master_id IS NOT NULL");
        while ($xr && $row = $xr->fetch_assoc()) {
            $registered[(int)$row['master_id']] = $row['applicability'];
        }

        $out = [];
        foreach ($masters as $mid => $m) {
            $applicability = 'requires_verification';
            $rationale     = null;
            $ruleId        = null;
            $rulePri       = null;

            // Flagged-for-classification masters never auto-promote beyond requires_verification
            if (!empty($m['requires_classification'])) {
                $applicability = 'requires_verification';
                $rationale     = 'Flagged — needs classification before any rule can promote it.';
            } else {
                foreach ($rulesByMaster[$mid] ?? [] as $rule) {
                    $cond = json_decode($rule['condition_json'] ?? '{}', true);
                    if (!is_array($cond)) continue;
                    if (self::evalCondition($cond, $ctx)) {
                        $applicability = $rule['rule_type'];
                        $rationale     = $rule['rationale_template'];
                        $ruleId        = (int)$rule['id'];
                        $rulePri       = (int)$rule['priority'];
                        break;
                    }
                }
                if ($rationale === null) {
                    $rationale = 'No rule matched — defaulted to requires_verification.';
                }
            }

            $current = $registered[$mid] ?? null;
            $out[$mid] = [
                'master'              => $m,
                'applicability'       => $applicability,
                'rationale'           => $rationale,
                'rule_id'             => $ruleId,
                'rule_priority'       => $rulePri,
                'already_registered'  => isset($registered[$mid]),
                'current_registered'  => $current,
                'has_changed'         => $current !== null && $current !== $applicability,
            ];
        }
        return ['context' => $ctx, 'results' => $out];
    }

    /** Group evaluation results by applicability bucket for the UI. */
    public static function bucket(array $results): array {
        $buckets = [
            'mandatory'            => [],
            'conditional'          => [],
            'requires_verification'=> [],
            'not_applicable'       => [],
        ];
        foreach ($results as $mid => $r) {
            $buckets[$r['applicability']][] = $r + ['master_id' => $mid];
        }
        return $buckets;
    }

    // ─── Condition evaluator (recursive) ──────────────────────────────────

    private static function evalCondition(array $cond, array $ctx): bool {
        // Compound node?
        if (isset($cond['all']) && is_array($cond['all'])) {
            foreach ($cond['all'] as $c) {
                if (!is_array($c) || !self::evalCondition($c, $ctx)) return false;
            }
            return true;
        }
        if (isset($cond['any']) && is_array($cond['any'])) {
            foreach ($cond['any'] as $c) {
                if (is_array($c) && self::evalCondition($c, $ctx)) return true;
            }
            return false;
        }
        if (isset($cond['not']) && is_array($cond['not'])) {
            return !self::evalCondition($cond['not'], $ctx);
        }
        // Leaf node: { field, op, value }
        if (!isset($cond['field']) || !isset($cond['op'])) return false;
        $field = $cond['field'];
        $op    = strtolower($cond['op']);
        $value = $cond['value'] ?? null;
        $cur   = $ctx[$field] ?? null;

        switch ($op) {
            case 'eq':      return (string)$cur === (string)$value || $cur == $value;
            case 'neq':     return (string)$cur !== (string)$value;
            case 'in':      return is_array($value) && in_array($cur, $value, false);
            case 'not_in':  return is_array($value) && !in_array($cur, $value, false);
            case 'gt':      return is_numeric($cur) && is_numeric($value) && ((float)$cur >  (float)$value);
            case 'gte':     return is_numeric($cur) && is_numeric($value) && ((float)$cur >= (float)$value);
            case 'lt':      return is_numeric($cur) && is_numeric($value) && ((float)$cur <  (float)$value);
            case 'lte':     return is_numeric($cur) && is_numeric($value) && ((float)$cur <= (float)$value);
            case 'exists':  return array_key_exists($field, $ctx) && $cur !== null && $cur !== '';
            case 'truthy':  return !empty($cur);
            case 'falsy':   return empty($cur);
        }
        return false;
    }
}
