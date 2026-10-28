<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * Stage 1 – Rule-Based Hard Filter
 *
 * Evaluates each business rule independently, records pass/fail and
 * the actual values used, then persists the result.
 */
class Stage1Filter {

    // ─── Evaluate a single proposal ─────────────────────────────────────────

    /**
     * @param array $proposal  Full row from the proposals table
     * @param array $cfg       Active weights_config row
     * @return array           Result array (also written to stage1_results)
     */
    public static function evaluate(array $proposal, array $cfg): array {
        $reasons = [];
        $filters = [];

        // ── 1. Carpet Sale Rate ────────────────────────────────────────────
        $saleRate = (float)($proposal['avg_sale_price_sqft'] ?? 0);
        $filters['filter_sale_rate'] = ($saleRate >= (float)$cfg['min_sale_rate']) ? 1 : 0;
        if (!$filters['filter_sale_rate']) {
            $reasons[] = sprintf(
                'Sale rate ₹%s < minimum ₹%s/sqft required',
                number_format($saleRate), number_format($cfg['min_sale_rate'])
            );
        }

        // ── 2. Existing FSI Used ──────────────────────────────────────────
        $fsi = $proposal['existing_fsi_used'] !== null ? (float)$proposal['existing_fsi_used'] : null;
        if ($fsi === null) {
            $filters['filter_fsi'] = null; // cannot evaluate
            $reasons[] = 'Existing FSI data not provided — filter skipped';
        } else {
            $filters['filter_fsi'] = ($fsi <= (float)$cfg['max_fsi_used']) ? 1 : 0;
            if (!$filters['filter_fsi']) {
                $reasons[] = sprintf('Existing FSI %.2f exceeds maximum of %.1f', $fsi, $cfg['max_fsi_used']);
            }
        }

        // ── 3. Free Sale Area ─────────────────────────────────────────────
        $freeSalePct = $proposal['free_sale_area_pct'] !== null ? (float)$proposal['free_sale_area_pct'] : null;
        if ($freeSalePct === null) {
            $filters['filter_free_sale_area'] = null;
            $reasons[] = 'Free sale area % not provided — filter skipped';
        } else {
            $filters['filter_free_sale_area'] = ($freeSalePct >= (float)$cfg['min_free_sale_pct']) ? 1 : 0;
            if (!$filters['filter_free_sale_area']) {
                $reasons[] = sprintf('Free sale area %.1f%% < minimum %.0f%% required', $freeSalePct, $cfg['min_free_sale_pct']);
            }
        }

        // ── 4. Density (Proposed/Existing unit ratio) ─────────────────────
        $existing  = ($proposal['existing_units'] ?? 0);
        $proposed  = ($proposal['proposed_units'] ?? 0);
        $densityRatio = null;
        if ($existing > 0 && $proposed > 0) {
            $densityRatio = round($proposed / $existing, 2);
            $filters['filter_density'] = ($densityRatio >= (float)$cfg['min_density_ratio']) ? 1 : 0;
            if (!$filters['filter_density']) {
                $reasons[] = sprintf(
                    'Density ratio %.2f (proposed/existing) < minimum %.2f',
                    $densityRatio, $cfg['min_density_ratio']
                );
            }
        } else {
            $filters['filter_density'] = null;
            $reasons[] = 'Unit counts not provided — density filter skipped';
        }

        // ── 5. Project Timeline ───────────────────────────────────────────
        $timeline = $proposal['completion_timeline_yrs'] !== null ? (float)$proposal['completion_timeline_yrs'] : null;
        if ($timeline === null) {
            $filters['filter_timeline'] = null;
            $reasons[] = 'Completion timeline not provided — filter skipped';
        } else {
            $filters['filter_timeline'] = ($timeline <= (float)$cfg['max_timeline_years']) ? 1 : 0;
            if (!$filters['filter_timeline']) {
                $reasons[] = sprintf('Timeline %.1f years exceeds maximum of %.0f years', $timeline, $cfg['max_timeline_years']);
            }
        }

        // ── 6. Cost vs Sale Feasibility ───────────────────────────────────
        $entryCost = $proposal['entry_cost_per_sqft'] !== null ? (float)$proposal['entry_cost_per_sqft'] : null;
        $profitMarginPct = null;
        if ($entryCost !== null && $saleRate > 0) {
            $profitMarginPct = round(($saleRate - $entryCost) / $saleRate * 100, 2);
            $filters['filter_cost_feasibility'] = ($profitMarginPct >= (float)$cfg['min_profit_margin_pct']) ? 1 : 0;
            if (!$filters['filter_cost_feasibility']) {
                $reasons[] = sprintf(
                    'Profit margin %.1f%% (sale ₹%s – entry cost ₹%s) < minimum %.0f%%',
                    $profitMarginPct,
                    number_format($saleRate),
                    number_format($entryCost),
                    $cfg['min_profit_margin_pct']
                );
            }
        } else {
            $filters['filter_cost_feasibility'] = null;
            $reasons[] = 'Entry cost not provided — cost feasibility filter skipped';
        }

        // ── Overall eligibility ───────────────────────────────────────────
        // Eligible if: no filter that was evaluated returned 0
        $isEligible = 1;
        foreach ($filters as $f) {
            if ($f === 0) { $isEligible = 0; break; }
        }

        $result = array_merge($filters, [
            'proposal_id'            => $proposal['id'],
            'is_eligible'            => $isEligible,
            'eval_sale_rate'         => $saleRate,
            'eval_fsi_used'          => $fsi,
            'eval_free_sale_area_pct'=> $freeSalePct,
            'eval_density_ratio'     => $densityRatio,
            'eval_timeline_yrs'      => $timeline,
            'eval_profit_margin_pct' => $profitMarginPct,
            'rejection_reasons'      => json_encode($reasons),
            'config_snapshot'        => json_encode($cfg),
        ]);

        self::persist($result);
        return $result;
    }

    // ─── Batch evaluate all proposals without a Stage 1 result ───────────────

    public static function evaluateAll(array $cfg): array {
        $db   = getDB();
        $rows = $db->query("
            SELECT p.*
            FROM proposals p
            LEFT JOIN stage1_results s ON s.proposal_id = p.id
            WHERE s.id IS NULL
        ")->fetch_all(MYSQLI_ASSOC);

        $results = [];
        foreach ($rows as $proposal) {
            $results[] = self::evaluate($proposal, $cfg);
        }
        return $results;
    }

    // ─── Re-evaluate a single proposal (delete old result first) ─────────────

    public static function reEvaluate(array $proposal, array $cfg): array {
        $db   = getDB();
        $stmt = $db->prepare("DELETE FROM stage1_results WHERE proposal_id = ?");
        $stmt->bind_param('i', $proposal['id']);
        $stmt->execute();
        return self::evaluate($proposal, $cfg);
    }

    // ─── Persist ─────────────────────────────────────────────────────────────

    private static function persist(array $r): void {
        $db   = getDB();
        $stmt = $db->prepare("
            INSERT INTO stage1_results
                (proposal_id, is_eligible,
                 filter_sale_rate, filter_fsi, filter_free_sale_area,
                 filter_density, filter_timeline, filter_cost_feasibility,
                 eval_sale_rate, eval_fsi_used, eval_free_sale_area_pct,
                 eval_density_ratio, eval_timeline_yrs, eval_profit_margin_pct,
                 rejection_reasons, config_snapshot)
            VALUES (?,?, ?,?,?, ?,?,?, ?,?,?, ?,?,?, ?,?)
            ON DUPLICATE KEY UPDATE
                is_eligible             = VALUES(is_eligible),
                filter_sale_rate        = VALUES(filter_sale_rate),
                filter_fsi              = VALUES(filter_fsi),
                filter_free_sale_area   = VALUES(filter_free_sale_area),
                filter_density          = VALUES(filter_density),
                filter_timeline         = VALUES(filter_timeline),
                filter_cost_feasibility = VALUES(filter_cost_feasibility),
                eval_sale_rate          = VALUES(eval_sale_rate),
                eval_fsi_used           = VALUES(eval_fsi_used),
                eval_free_sale_area_pct = VALUES(eval_free_sale_area_pct),
                eval_density_ratio      = VALUES(eval_density_ratio),
                eval_timeline_yrs       = VALUES(eval_timeline_yrs),
                eval_profit_margin_pct  = VALUES(eval_profit_margin_pct),
                rejection_reasons       = VALUES(rejection_reasons),
                config_snapshot         = VALUES(config_snapshot),
                processed_at            = CURRENT_TIMESTAMP
        ");
        // 16 params: 8×i (proposal_id,is_eligible,6 filters) + 6×d (eval values) + 2×s (JSON)
        $stmt->bind_param(
            'iiiiiiiiddddddss',
            $r['proposal_id'], $r['is_eligible'],
            $r['filter_sale_rate'], $r['filter_fsi'], $r['filter_free_sale_area'],
            $r['filter_density'], $r['filter_timeline'], $r['filter_cost_feasibility'],
            $r['eval_sale_rate'], $r['eval_fsi_used'], $r['eval_free_sale_area_pct'],
            $r['eval_density_ratio'], $r['eval_timeline_yrs'], $r['eval_profit_margin_pct'],
            $r['rejection_reasons'], $r['config_snapshot']
        );
        $stmt->execute();
    }
}
