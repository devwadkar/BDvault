<?php
/**
 * ApprovalCostCalculator — applies master cost components to project inputs.
 *
 * For every project_approval row that has a master attached, we:
 *   1. Pull every approval_master_cost_component row linked to that master.
 *   2. Compute each component against the project's facts
 *      (BUA / plot / sqft / tree count / etc.).
 *   3. Replace the project_approval_cost rows for that approval (idempotent).
 *
 * Behavior per method:
 *   fixed         → estimated = rate_numeric
 *   range         → estimated = HIGH bound (conservative); low+high stored in inputs_json
 *   per_sqm_plot  → estimated = rate × gross_plot_area_sqm
 *   per_sqm_bua   → estimated = rate × total_permissible_bua_sqm
 *   per_sqft_plot → estimated = rate × gross_plot_area_sqm × 10.7639
 *   per_sqft_bua  → estimated = rate × total_permissible_bua_sqm × 10.7639
 *   per_tree      → estimated = rate × tree_count (user input via project_approval_input)
 *                   If tree_count unknown → estimated = NULL, confidence = pending_verification
 *   percent_of    → unknown reference → NULL, pending_verification
 *   deposit       → marked is_refundable=1, computed per the fallback rate rule above
 *   unknown       → always NULL, pending_verification
 *
 * Confidence carries through from the master. If the computed estimate ended up
 * NULL (missing input), confidence is downgraded to pending_verification.
 *
 * NEVER touches user-set actual_amount values. NEVER touches rows whose
 * `locked_at` is non-null (future-use lock column from migration v20).
 */

require_once __DIR__ . '/../config/database.php';

class ApprovalCostCalculator {

    private const SQM_TO_SQFT = 10.7639;

    /** Build the inputs the formulas reference. Pulls from area_statements + v2 draft + project_approval_input. */
    public static function projectInputs(int $statementId): array {
        $db = getDB();
        $r = $db->query("SELECT * FROM area_statements WHERE id=" . (int)$statementId);
        $s = $r ? $r->fetch_assoc() : null;
        if (!$s) return [];
        $draft = json_decode($s['draft_json'] ?? '{}', true) ?: [];
        $v2 = $draft['v2_stage1'] ?? [];
        $s2 = $draft['step_2'] ?? [];

        // Net plot fallback (summary column often NULL)
        $netPlot = (float)($s['net_plot_area_sqm'] ?? 0);
        if ($netPlot <= 0) {
            $lr = $db->query("SELECT area_sqm FROM area_statement_land
                              WHERE statement_id=" . (int)$statementId . " AND component_key='net_plot'");
            if ($lr && $row = $lr->fetch_assoc()) $netPlot = (float)$row['area_sqm'];
        }

        $gross = (float)($s2['gross_plot_area_sqm'] ?? ($v2['property_card_area_sqm'] ?? 0));
        $totalBua = (float)($s['total_permissible_bua_sqm'] ?? 0);

        // User-supplied per-project inputs
        $extras = [];
        $xr = $db->query("SELECT input_key, input_value FROM project_approval_input
                          WHERE statement_id=" . (int)$statementId);
        while ($xr && $row = $xr->fetch_assoc()) {
            $extras[$row['input_key']] = $row['input_value'] === null ? null : (float)$row['input_value'];
        }

        return [
            'gross_plot_sqm' => $gross,
            'net_plot_sqm'   => $netPlot,
            'total_bua_sqm'  => $totalBua,
            'rehab_bua_sqm'  => (float)($s['rehab_bua_sqm'] ?? 0),
            'sale_bua_sqm'   => (float)($s['sale_bua_sqm'] ?? 0),
            'gross_plot_sqft'=> $gross > 0    ? $gross    * self::SQM_TO_SQFT : 0,
            'total_bua_sqft' => $totalBua > 0 ? $totalBua * self::SQM_TO_SQFT : 0,
            'extras'         => $extras,
        ];
    }

    /** Recalculate costs for every registered approval on a statement. Returns summary. */
    public static function calculateForStatement(int $statementId): array {
        $db = getDB();
        $inputs = self::projectInputs($statementId);

        $r = $db->query("SELECT id FROM project_approval
                         WHERE statement_id=" . (int)$statementId);
        $touched = 0; $rowsWritten = 0;
        while ($r && $row = $r->fetch_assoc()) {
            $touched++;
            $rowsWritten += self::calculateForApproval((int)$row['id'], $inputs);
        }

        // Roll up totals into area_statements.approval_summary_json
        $rollup = self::rollupForStatement($statementId);

        return [
            'approvals_touched'  => $touched,
            'cost_rows_written'  => $rowsWritten,
            'rollup'             => $rollup,
        ];
    }

    /** Recalculate costs for a single project_approval. Returns rows written. */
    public static function calculateForApproval(int $projectApprovalId, ?array $inputs = null): int {
        $db = getDB();
        $pa = $db->query("SELECT * FROM project_approval WHERE id=" . (int)$projectApprovalId);
        $pa = $pa ? $pa->fetch_assoc() : null;
        if (!$pa) return 0;

        if ($inputs === null) $inputs = self::projectInputs((int)$pa['statement_id']);

        // Clear existing non-locked cost rows for this approval (preserve actual_amount
        // by caching before delete, then reapplying by component label if present).
        $existing = [];
        $er = $db->query("SELECT id, master_cost_component_id, label, actual_amount
                          FROM project_approval_cost
                          WHERE project_approval_id=" . (int)$projectApprovalId
                          . " AND locked_at IS NULL");
        while ($er && $row = $er->fetch_assoc()) {
            $key = $row['master_cost_component_id']
                   ? 'mcc_' . $row['master_cost_component_id']
                   : 'lbl_' . strtolower($row['label']);
            $existing[$key] = $row;
        }
        $db->query("DELETE FROM project_approval_cost
                    WHERE project_approval_id=" . (int)$projectApprovalId
                    . " AND locked_at IS NULL");

        if (empty($pa['master_id'])) return 0;  // custom requirements: no auto-components
        if ($pa['applicability'] === 'not_applicable') return 0; // skip N/A

        $components = [];
        $cr = $db->query("SELECT * FROM approval_master_cost_component
                          WHERE master_id=" . (int)$pa['master_id']);
        while ($cr && $row = $cr->fetch_assoc()) $components[] = $row;

        $written = 0;
        $ins = $db->prepare("INSERT INTO project_approval_cost
            (project_approval_id, master_cost_component_id, label, method,
             inputs_json, rate_used, estimated_amount, actual_amount,
             confidence, is_refundable, source_ref)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($components as $c) {
            $calc = self::computeComponent($c, $inputs);

            // Preserve actual_amount from the deleted row (if any)
            $key = 'mcc_' . (int)$c['id'];
            $actual = isset($existing[$key]) ? $existing[$key]['actual_amount'] : null;

            $mccId = (int)$c['id'];
            $label = $c['label'];
            $method = $c['method'];
            $inputsJson = json_encode($calc['inputs'], JSON_UNESCAPED_UNICODE);
            $rateUsed = $calc['rate_used'];
            $est = $calc['estimated'];
            $conf = $calc['confidence'];
            $refund = (int)$c['is_refundable'];
            $src = $c['source_ref'];

            $ins->bind_param('iissssddsis',
                $projectApprovalId, $mccId, $label, $method,
                $inputsJson, $rateUsed, $est, $actual,
                $conf, $refund, $src);
            $ins->execute();
            $written++;
        }

        return $written;
    }

    /** Compute a single cost component. Returns {estimated, inputs, rate_used, confidence}. */
    private static function computeComponent(array $comp, array $inputs): array {
        $method = $comp['method'];
        $rateN  = $comp['rate_numeric']    !== null ? (float)$comp['rate_numeric']    : null;
        $rateL  = $comp['rate_range_low']  !== null ? (float)$comp['rate_range_low']  : null;
        $rateH  = $comp['rate_range_high'] !== null ? (float)$comp['rate_range_high'] : null;
        $conf   = $comp['confidence'];

        $gross = (float)($inputs['gross_plot_sqm']  ?? 0);
        $bua   = (float)($inputs['total_bua_sqm']   ?? 0);
        $grossSqft = (float)($inputs['gross_plot_sqft'] ?? 0);
        $buaSqft   = (float)($inputs['total_bua_sqft']  ?? 0);
        $trees = isset($inputs['extras']['tree_count']) ? (float)$inputs['extras']['tree_count'] : null;

        $out = [
            'estimated' => null,
            'inputs'    => [],
            'rate_used' => $rateN,
            'confidence'=> $conf,
        ];

        switch ($method) {

            case 'fixed':
                // Same per-unit detection as range: if the unit says "/sqm BUA" or similar,
                // treat the fixed rate as a per-unit rate and multiply by the matching project input.
                $unit = strtolower((string)($comp['unit'] ?? ''));
                $multiplier = 1.0; $mulLabel = null; $mulVal = null;
                if (strpos($unit, 'sqm bua') !== false || strpos($unit, '/sqm bua') !== false) {
                    $multiplier = $bua;   $mulLabel = 'total_bua_sqm';  $mulVal = $bua;
                } elseif (strpos($unit, 'sqft bua') !== false) {
                    $multiplier = $buaSqft; $mulLabel = 'total_bua_sqft'; $mulVal = $buaSqft;
                } elseif (strpos($unit, '/sqm') !== false || strpos($unit, 'inr/sqm') !== false) {
                    $multiplier = $bua ?: $gross; $mulLabel = $bua ? 'total_bua_sqm' : 'gross_plot_sqm'; $mulVal = $multiplier;
                } elseif (strpos($unit, '/sqft') !== false || strpos($unit, 'inr/sqft') !== false) {
                    $multiplier = $buaSqft ?: $grossSqft; $mulLabel = $buaSqft ? 'total_bua_sqft' : 'gross_plot_sqft'; $mulVal = $multiplier;
                }
                $out['estimated'] = $rateN !== null ? $rateN * $multiplier : null;
                $out['inputs']    = array_filter([
                    'rate'             => $rateN,
                    'multiplier_key'   => $mulLabel,
                    'multiplier_value' => $mulVal,
                ], fn($v) => $v !== null);
                break;

            case 'range':
                // Store both bounds; use HIGH as the conservative estimate (same philosophy
                // as the timeline critical-path decision in Stage 3). If the unit tells us
                // the range is per-unit (per sqm BUA / per sqft plot / per tree / etc.) we
                // multiply by the matching project input so the estimate is final-₹, not rate.
                $unit = strtolower((string)($comp['unit'] ?? ''));
                $multiplier = 1.0; $mulLabel = null; $mulVal = null;
                if (strpos($unit, 'sqm bua') !== false || strpos($unit, '/sqm bua') !== false) {
                    $multiplier = $bua;   $mulLabel = 'total_bua_sqm';  $mulVal = $bua;
                } elseif (strpos($unit, 'sqft bua') !== false) {
                    $multiplier = $buaSqft; $mulLabel = 'total_bua_sqft'; $mulVal = $buaSqft;
                } elseif (strpos($unit, 'sqm') !== false) {
                    $multiplier = $bua ?: $gross; $mulLabel = $bua ? 'total_bua_sqm' : 'gross_plot_sqm'; $mulVal = $multiplier;
                } elseif (strpos($unit, 'sqft') !== false) {
                    $multiplier = $buaSqft ?: $grossSqft; $mulLabel = $buaSqft ? 'total_bua_sqft' : 'gross_plot_sqft'; $mulVal = $multiplier;
                } elseif (strpos($unit, 'tree') !== false) {
                    if ($trees !== null && $trees > 0) {
                        $multiplier = $trees; $mulLabel = 'tree_count'; $mulVal = $trees;
                    } else {
                        $out['confidence'] = 'pending_verification';
                        $out['inputs']     = ['low' => $rateL, 'high' => $rateH, 'tree_count' => null,
                                              'note' => 'Tree count not set — supply via project inputs.'];
                        break;
                    }
                }
                $amountLow  = ($rateL !== null) ? $rateL * $multiplier : null;
                $amountHigh = ($rateH !== null) ? $rateH * $multiplier : null;
                $out['estimated'] = $amountHigh;
                $out['rate_used'] = $rateH;
                $out['inputs']    = array_filter([
                    'rate_low'   => $rateL,
                    'rate_high'  => $rateH,
                    'amount_low' => $amountLow,
                    'amount_high'=> $amountHigh,
                    'multiplier_key'   => $mulLabel,
                    'multiplier_value' => $mulVal,
                ], fn($v) => $v !== null);
                break;

            case 'per_sqm_plot':
                if ($rateN !== null && $gross > 0) {
                    $out['estimated'] = $rateN * $gross;
                    $out['inputs']    = ['rate' => $rateN, 'gross_plot_sqm' => $gross];
                } else {
                    $out['confidence'] = 'pending_verification';
                }
                break;

            case 'per_sqm_bua':
                if ($rateN !== null && $bua > 0) {
                    $out['estimated'] = $rateN * $bua;
                    $out['inputs']    = ['rate' => $rateN, 'total_bua_sqm' => $bua];
                } else {
                    $out['confidence'] = 'pending_verification';
                }
                break;

            case 'per_sqft_plot':
                if ($rateN !== null && $grossSqft > 0) {
                    $out['estimated'] = $rateN * $grossSqft;
                    $out['inputs']    = ['rate' => $rateN, 'gross_plot_sqft' => $grossSqft];
                } else {
                    $out['confidence'] = 'pending_verification';
                }
                break;

            case 'per_sqft_bua':
                if ($rateN !== null && $buaSqft > 0) {
                    $out['estimated'] = $rateN * $buaSqft;
                    $out['inputs']    = ['rate' => $rateN, 'total_bua_sqft' => $buaSqft];
                } else {
                    $out['confidence'] = 'pending_verification';
                }
                break;

            case 'per_tree':
                if ($rateN !== null && $trees !== null && $trees > 0) {
                    $out['estimated'] = $rateN * $trees;
                    $out['inputs']    = ['rate' => $rateN, 'tree_count' => $trees];
                } else {
                    // Tree count not supplied — flag for user to fill in
                    $out['confidence'] = 'pending_verification';
                    $out['inputs']    = ['rate' => $rateN, 'tree_count' => null,
                                         'note' => 'Tree count not set — supply via project inputs.'];
                }
                break;

            case 'percent_of':
            case 'unknown':
            default:
                $out['confidence'] = 'pending_verification';
                $out['inputs']     = ['note' => 'Formula not auto-computable yet; needs manual estimate.'];
                break;
        }
        return $out;
    }

    /** Compute totals by phase / category / confidence / refundability. Caches to area_statements.approval_summary_json. */
    public static function rollupForStatement(int $statementId): array {
        $db = getDB();
        $rows = $db->query("
            SELECT pa.id AS pa_id, pa.applicability, pa.project_phase,
                   m.category_key, m.record_type,
                   c.estimated_amount, c.actual_amount, c.confidence, c.is_refundable
            FROM project_approval pa
            LEFT JOIN approval_master m    ON m.id = pa.master_id
            LEFT JOIN project_approval_cost c ON c.project_approval_id = pa.id
            WHERE pa.statement_id = " . (int)$statementId);

        $sum = [
            'non_refundable_est_inr'    => 0.0,
            'refundable_est_inr'        => 0.0,
            'cash_requirement_est_inr'  => 0.0,  // non_ref + refundable
            'actual_total_inr'          => 0.0,
            'unverified_count'          => 0,    // components where confidence != verified_official
            'computed_components'       => 0,    // non-null estimates
            'pending_components'        => 0,    // null estimate + pending_verification
            'by_phase'                  => ['land_acquisition'=>0,'pre_construction'=>0,'during_construction'=>0,'post_construction'=>0],
            'by_category'               => [],
            'by_confidence'             => ['verified_official'=>0,'source_reported'=>0,'consultant_estimate'=>0,'historical_estimate'=>0,'manual_estimate'=>0,'pending_verification'=>0],
            'calculated_at'             => date('Y-m-d H:i:s'),
        ];

        while ($rows && $r = $rows->fetch_assoc()) {
            // Skip N/A applicability from the cost rollup entirely
            if ($r['applicability'] === 'not_applicable') continue;
            $est = $r['estimated_amount'] === null ? null : (float)$r['estimated_amount'];
            $act = $r['actual_amount']    === null ? null : (float)$r['actual_amount'];

            if ($est === null && $act === null) {
                if ($r['confidence'] !== null) $sum['pending_components']++;
                continue;
            }
            $sum['computed_components']++;

            $amount = $est !== null ? $est : $act;
            $isRef  = (int)$r['is_refundable'] === 1;
            if ($isRef) $sum['refundable_est_inr'] += $amount;
            else        $sum['non_refundable_est_inr'] += $amount;

            if ($act !== null) $sum['actual_total_inr'] += $act;

            if ($r['confidence'] !== 'verified_official') $sum['unverified_count']++;

            // Phase bucket
            $phase = $r['project_phase'] ?? 'pre_construction';
            if (isset($sum['by_phase'][$phase])) $sum['by_phase'][$phase] += $amount;

            // Category bucket
            $cat = $r['category_key'] ?? 'custom';
            if (!isset($sum['by_category'][$cat])) $sum['by_category'][$cat] = 0.0;
            $sum['by_category'][$cat] += $amount;

            // Confidence bucket
            $conf = $r['confidence'] ?? 'pending_verification';
            if (!isset($sum['by_confidence'][$conf])) $sum['by_confidence'][$conf] = 0.0;
            $sum['by_confidence'][$conf] += $amount;
        }

        $sum['cash_requirement_est_inr'] = $sum['non_refundable_est_inr'] + $sum['refundable_est_inr'];

        // Cache onto area_statements
        $json = json_encode($sum, JSON_UNESCAPED_UNICODE);
        $u = $db->prepare("UPDATE area_statements SET approval_summary_json=?,
                           approval_phase_started_at = IFNULL(approval_phase_started_at, CURRENT_TIMESTAMP)
                           WHERE id=?");
        $u->bind_param('si', $json, $statementId);
        $u->execute();

        return $sum;
    }

    /** Load (or default) the cached rollup for a statement. */
    public static function loadRollup(int $statementId): ?array {
        $db = getDB();
        $r = $db->query("SELECT approval_summary_json FROM area_statements WHERE id=" . (int)$statementId);
        $row = $r ? $r->fetch_assoc() : null;
        if (!$row || !$row['approval_summary_json']) return null;
        return json_decode($row['approval_summary_json'], true) ?: null;
    }

    /** Format a rupee amount for display (₹1.23 Cr / ₹12.34 L / ₹1,234). */
    public static function formatInr(?float $amount): string {
        if ($amount === null) return '—';
        $a = (float)$amount;
        if ($a >= 10000000) return '₹' . number_format($a / 10000000, 2) . ' Cr';
        if ($a >= 100000)   return '₹' . number_format($a / 100000,   2) . ' L';
        return '₹' . number_format($a, 0);
    }
}
