<?php
/**
 * AreaStatementFinancialsBridge — runs the existing Reg33_10_FinancialsCalculator
 * against a locked Area Statement's outputs.
 *
 * FinancialsCalculator was built to read from a plan_calculations table keyed by calc_key.
 * The Area Analysis flow stores its calculator output in area_statement_calcs with the same
 * calc_key convention, but the per-regulation calculators name their totals differently.
 * This bridge normalises the AS calc rows into the four keys the FinancialsCalculator wants:
 *
 *   rehab_tenement_bua_sqm         — the on-site rehab component
 *   gross_sale_bua_permissible_sqm — the sale / owner / society-market share
 *   total_bua_proposed_sqm         — the full scheme BUA (incl. fungible)
 *   sale_bua_proposed_in_situ_sqm  — optional; falls back to gross_sale when absent
 *   premium_total_inr              — optional; 0 when the AS calc didn't emit premiums
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/calculators/Reg33_10_FinancialsCalculator.php';
require_once __DIR__ . '/RulesRegistry.php';

class AreaStatementFinancialsBridge {

    /**
     * Load or seed the financials row for a statement (never insert on demand from view code).
     */
    public static function loadRow(int $statementId): ?array {
        $stmt = getDB()->prepare("SELECT * FROM area_statement_financials WHERE statement_id = ?");
        $stmt->bind_param('i', $statementId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /**
     * Insert an empty row with defaults, primed with a rate suggestion derived from the AS.
     * No-op if the row already exists.
     */
    public static function ensureRow(int $statementId): void {
        $existing = self::loadRow($statementId);
        if ($existing) return;
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO area_statement_financials (statement_id) VALUES (?)");
        $stmt->bind_param('i', $statementId);
        $stmt->execute();
    }

    /**
     * Extract the normalised AS calc rows keyed by the AS calc_key.
     */
    public static function loadStatementCalcsByKey(int $statementId): array {
        $out = [];
        $r = getDB()->query("SELECT calc_key, calc_value, calc_label, calc_unit
                             FROM area_statement_calcs WHERE statement_id = " . (int)$statementId);
        while ($r && $row = $r->fetch_assoc()) $out[$row['calc_key']] = $row;
        return $out;
    }

    /**
     * Map AS calc keys to the calc keys the FinancialsCalculator wants.
     * Returns an array shaped like plan_calcs (keyed by calc_key, each ['calc_value' => x]).
     */
    public static function bridgeCalcsForFinancials(array $asCalcsByKey, array $statement): array {
        // Preferred sources per financial input, in fallback order
        $pick = function (array $keys) use ($asCalcsByKey) {
            foreach ($keys as $k) if (isset($asCalcsByKey[$k])) return (float)$asCalcsByKey[$k]['calc_value'];
            return null;
        };

        $totalBua = $pick(['full_scheme_bua_sqm', 'total_bua_approved_sqm'])
                    ?? (float)($statement['total_permissible_bua_sqm'] ?? 0);

        $rehabBua = $pick(['rehab_bua_sqm', 'total_rehab_bua_sqm', 'rehab_component_sqm'])
                    ?? (float)($statement['rehab_bua_sqm'] ?? 0);

        $saleBua = $pick([
            'sale_component_sqm', 'sale_bua_sqm',
            'owner_total_sqm', 'sale_society_total_sqm', 'society_grand_total_sqm',
        ]) ?? (float)($statement['sale_bua_sqm'] ?? 0);

        // Premium synthesis by regulation. Each scheme has its own premium logic;
        // when the AS calc rows don't emit a premium_total_inr row, apply the
        // scheme's typical rate against the fungible / additional BUA.
        $premium = self::synthesizePremium($asCalcsByKey, $statement, $totalBua, $rehabBua);

        return [
            'rehab_tenement_bua_sqm'         => ['calc_value' => $rehabBua],
            'gross_sale_bua_permissible_sqm' => ['calc_value' => $saleBua],
            'total_bua_proposed_sqm'         => ['calc_value' => $totalBua],
            'sale_bua_proposed_in_situ_sqm'  => ['calc_value' => $saleBua],
            'premium_total_inr'              => ['calc_value' => $premium],
        ];
    }

    /**
     * Synthesize premium_total_inr for schemes whose AS calc rows didn't emit it.
     * Uses ASR land rate × premium % of fungible/additional BUA (the industry heuristic).
     *
     * If the AS emitted an explicit `premium_total_inr` calc row, use that as-is.
     * If ASR data is missing, returns 0 (financial model still runs, just with no
     * premium bucket — the user can override manually later).
     */
    private static function synthesizePremium(array $asCalcsByKey, array $statement, float $totalBua, float $rehabBua): float {
        if (isset($asCalcsByKey['premium_total_inr'])) {
            return (float)$asCalcsByKey['premium_total_inr']['calc_value'];
        }
        $reg = $statement['selected_regulation_key'] ?? '';
        // Rehab and MHADA-handover BUA are premium-exempt (Reg 31(1)/31(3) + 33(5) exemption).
        // Premium applies to the sale/owner BUA beyond zonal FSI.
        $premiumApplicableBua = max(0, $totalBua - $rehabBua);

        // Scheme-specific premium rate as % of ASR land rate — all four schemes now live
        // in regulatory_constants (rule_key = 'premium_rate_factor'). Missing scope → 0.
        $pctOfAsr = RulesRegistry::getOrNull('premium_rate_factor', $reg) ?? 0.0;
        if ($pctOfAsr <= 0 || $premiumApplicableBua <= 0) return 0.0;

        $asrRate = self::asrLandRate($statement);
        if ($asrRate <= 0) return 0.0;

        // ₹ = BUA (sqm) × ASR rate (₹/sqm) × premium %
        return $premiumApplicableBua * $asrRate * $pctOfAsr;
    }

    /** Look up an ASR land rate for the statement's ward (fallback 0 if not seeded). */
    private static function asrLandRate(array $statement): float {
        $db = getDB();
        $r = $db->query("SHOW TABLES LIKE 'asr_rates'");
        if (!$r || !$r->num_rows) return 0.0;
        $ward = $statement['ward'] ?? '';
        $loc  = $statement['project_location'] ?? '';
        $q = $db->prepare("SELECT land_rate_per_sqm FROM asr_rates
                           WHERE land_rate_per_sqm IS NOT NULL
                             AND (LOWER(ward) = LOWER(?)
                                  OR LOWER(zone_name) LIKE CONCAT('%', LOWER(?), '%')
                                  OR LOWER(village)   LIKE CONCAT('%', LOWER(?), '%'))
                           ORDER BY imported_at DESC LIMIT 1");
        if (!$q) return 0.0;
        $q->bind_param('sss', $ward, $loc, $loc);
        $q->execute();
        $row = $q->get_result()->fetch_assoc();
        return $row ? (float)$row['land_rate_per_sqm'] : 0.0;
    }

    /**
     * Run the model + persist yearly / summary / loan output onto the row.
     * When line-item categories exist (revenue/premium/society), their totals
     * override the calculator's synthesized versions.
     */
    public static function run(int $statementId): array {
        $financials = self::loadRow($statementId);
        if (!$financials) return ['ok' => false, 'error' => 'No financial row — save the form first.'];
        $statement = getDB()->query("SELECT * FROM area_statements WHERE id = " . (int)$statementId)->fetch_assoc();
        if (!$statement) return ['ok' => false, 'error' => 'Statement not found.'];
        $asCalcs = self::loadStatementCalcsByKey($statementId);
        if (empty($asCalcs)) return ['ok' => false, 'error' => 'Area Statement not calculated yet (run Step 5).'];

        $planCalcs = self::bridgeCalcsForFinancials($asCalcs, $statement);

        // Sum line items by category — override calculator inputs when the user has itemised
        $lineItemTotals = self::sumLineItemsByCategory($statementId);
        if (($lineItemTotals['premium'] ?? 0) > 0) {
            $planCalcs['premium_total_inr'] = ['calc_value' => (float)$lineItemTotals['premium']];
        }

        try {
            $calc = new Reg33_10_FinancialsCalculator($financials, $planCalcs);
            $out = $calc->run();
        } catch (Throwable $t) {
            return ['ok' => false, 'error' => 'Calculation error: ' . $t->getMessage()];
        }

        // Post-process: if user itemised revenue, override total_revenue_inr with the line-item total
        // (the calculator's model uses sale_rate × area × collection curves; when user itemises,
        // we trust the totals they entered).
        if (($lineItemTotals['revenue'] ?? 0) > 0) {
            $itemisedRevenue = (float)$lineItemTotals['revenue'];
            $out['summary']['total_revenue_inr']  = $itemisedRevenue;
            $out['summary']['total_cash_inflow_inr'] = $itemisedRevenue;
            // Recompute NP margin against the itemised revenue
            $cost = $out['summary']['total_cost_inr'] ?? 0;
            $out['summary']['net_profit_before_interest_inr'] = $itemisedRevenue - $cost;
            $intCost = $out['summary']['total_interest_inr'] ?? 0;
            $out['summary']['net_profit_after_interest_inr']  = $itemisedRevenue - $cost - $intCost;
            $out['summary']['net_profit_margin_pct'] = $itemisedRevenue > 0
                ? $out['summary']['net_profit_after_interest_inr'] / $itemisedRevenue * 100 : 0;
        }
        // If user itemised society costs, add to total cost
        if (($lineItemTotals['society'] ?? 0) > 0) {
            $societyExtra = (float)$lineItemTotals['society'];
            // The calculator already has a society cost bucket; we treat the itemised value as override
            $out['summary']['society_cost_itemised_inr'] = $societyExtra;
        }
        $out['summary']['line_item_totals'] = $lineItemTotals;

        // Persist
        $yearly    = json_encode($out['yearly']    ?? [], JSON_UNESCAPED_SLASHES);
        $loan      = json_encode($out['loan']      ?? [], JSON_UNESCAPED_SLASHES);
        $summary   = json_encode($out['summary']   ?? [], JSON_UNESCAPED_SLASHES);
        $warnings  = json_encode($out['warnings']  ?? [], JSON_UNESCAPED_SLASHES);

        $upd = getDB()->prepare("UPDATE area_statement_financials
            SET yearly_output_json = ?, loan_schedule_json = ?, summary_output_json = ?, warnings_json = ?,
                calculated_at = CURRENT_TIMESTAMP
            WHERE statement_id = ?");
        $upd->bind_param('ssssi', $yearly, $loan, $summary, $warnings, $statementId);
        $upd->execute();

        return ['ok' => true, 'summary' => $out['summary'] ?? [], 'warnings' => $out['warnings'] ?? []];
    }

    /** Sum line items by category. */
    public static function sumLineItemsByCategory(int $statementId): array {
        $out = [];
        $r = getDB()->query("SELECT category, SUM(amount_inr) AS total FROM area_statement_line_items
                             WHERE statement_id = " . (int)$statementId . " GROUP BY category");
        while ($r && $row = $r->fetch_assoc()) $out[$row['category']] = (float)$row['total'];
        return $out;
    }
}
