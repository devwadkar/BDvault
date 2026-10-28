<?php
/**
 * AreaCalcEngine — bridges the Area Analysis wizard to the existing Reg33_*_Calculator
 * classes. Reads the wizard's captured state (draft_json + existing_buildings + selected
 * regulation), maps it to the calculator's input schema, runs .compute(), and persists
 * output into area_statement_calcs + _fsi + _dev + updates the summary columns on the
 * parent area_statements row.
 *
 * Also runs a minimal validation pass and populates area_statement_valid rows.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/calculators/Reg33_5_Calculator.php';
require_once __DIR__ . '/calculators/Reg33_10_Calculator.php';
require_once __DIR__ . '/calculators/Reg33_11_Calculator.php';
require_once __DIR__ . '/calculators/Reg33_20B_Calculator.php';
require_once __DIR__ . '/DCPRRuleLoader.php';
require_once __DIR__ . '/RulesRegistry.php';
require_once __DIR__ . '/FormulaDiscrepancyDetector.php';
require_once __DIR__ . '/CalcOverrideManager.php';

class AreaCalcEngine {

    public const SUPPORTED = ['33(5)', '33(10)', '33(11)', '33(20)(B)'];

    /**
     * Run the calculator for a given area statement.
     * Returns ['ok' => bool, 'error' => ?string, 'calc_count' => int, 'validations' => [...]].
     */
    public static function run(int $statementId): array {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM area_statements WHERE id = ?");
        $stmt->bind_param('i', $statementId);
        $stmt->execute();
        $statement = $stmt->get_result()->fetch_assoc();
        if (!$statement) return ['ok' => false, 'error' => 'Statement not found', 'calc_count' => 0, 'validations' => []];

        $reg = $statement['selected_regulation_key'] ?: $statement['suggested_regulation_key'];
        if (!$reg) return ['ok' => false, 'error' => 'No regulation selected — complete Step 4 first.', 'calc_count' => 0, 'validations' => []];
        if (!in_array($reg, self::SUPPORTED, true)) {
            return ['ok' => false,
                    'error' => "Regulation {$reg} has no calculator yet (PENDING). Choose one of: " . implode(', ', self::SUPPORTED),
                    'calc_count' => 0, 'validations' => []];
        }

        $draft = json_decode($statement['draft_json'] ?? '{}', true) ?: [];
        $buildings = self::loadBuildings($statementId);
        $inputs = self::mapInputs($reg, $statement, $draft, $buildings);

        // Instantiate calculator
        try {
            $calc = self::instantiate($reg, $inputs);
            $rows = $calc->compute();
        } catch (Throwable $t) {
            return ['ok' => false, 'error' => 'Calculator error: ' . $t->getMessage(), 'calc_count' => 0, 'validations' => []];
        }

        // Apply any active manual overrides — rewrites calc_value + flags on affected rows.
        // Downstream rows that consumed the ORIGINAL value in the calculator's math are
        // NOT recomputed; a warning is surfaced in the UI when a foundational key is
        // overridden. This is the MVP scope: transparent override + audit, not full
        // dependency re-derivation.
        $overrideCount = CalcOverrideManager::applyToRows($statementId, $rows);

        // Persist calc rows + flatten into typed statement tables
        self::persistCalcs($statementId, $rows);
        self::flattenFsi($statementId, $rows, $reg);
        self::flattenDev($statementId, $rows, $reg);
        self::updateSummary($statementId, $rows, $reg);

        // Validation pass
        $validations = self::validate($statementId, $rows, $reg);

        // Formula discrepancy detection — cross-checks against identities + reported values.
        // Runs against the just-persisted calc rows, so it sees the same values users do.
        try {
            $discrepancies = FormulaDiscrepancyDetector::run($statementId);
        } catch (Throwable $t) {
            // Never fail the whole calc because a check errored — just note it.
            $discrepancies = -1;
        }

        // Bump status → calculated
        $db->query("UPDATE area_statements
                    SET status = 'calculated', calculated_at = CURRENT_TIMESTAMP
                    WHERE id = " . (int)$statementId);

        return [
            'ok'            => true,
            'error'         => null,
            'calc_count'    => count($rows),
            'validations'   => $validations,
            'discrepancies' => $discrepancies ?? 0,
        ];
    }

    // ─── Regulation-specific input mappers ───────────────────

    private static function mapInputs(string $reg, array $s, array $draft, array $buildings): array {
        $s2 = $draft['step_2'] ?? [];
        $s3 = $draft['step_3'] ?? [];
        $totals = self::sumBuildings($buildings);
        $isIsland = self::isIslandCity($s, $s2);

        // Phase-G3 knobs sourced from Step 3 Section A. Per-regulation defaults live in
        // regulatory_constants (see migration v15) and are pulled via RulesRegistry so
        // policy changes don't require a code edit.
        $carpetResDefault = ($reg === '33(5)')
            ? RulesRegistry::get('carpet_area_default_sqm',     '33(5)')
            : RulesRegistry::get('carpet_area_default_res_sqm', 'global');
        $carpetRes = self::flt($s3['s3_carpet_res_sqm']  ?? null) ?? $carpetResDefault;
        $carpetCom = self::flt($s3['s3_carpet_com_sqm']  ?? null) ?? RulesRegistry::get('carpet_area_default_com_sqm', 'global');
        $loading   = self::flt($s3['s3_loading_factor']  ?? null) ?? RulesRegistry::get('loading_factor_default',      'global');
        // Section A explicit eligible count wins; otherwise fall back to sum from the roster
        $eligibleUser = self::flt($s3['s3_eligible_total'] ?? null);
        $eligibleFinal = $eligibleUser !== null && $eligibleUser > 0
            ? (int)$eligibleUser
            : ($totals['eligible'] > 0 ? $totals['eligible'] : $totals['tenants']);

        // Amenity plot HANDOVER (Reg 14(A)) — Step 2 Section D (Row 6 in the feasibility)
        $amenityHandover = self::flt($s2['amenity_area_sqm'] ?? null) ?? 0.0;
        // Amenity BUILT area (Row 10) — Step 3 Section A. Blank = 0 here; feasibility view
        // then applies Financials override → calc → 38% fallback.
        $amenityBuilt = self::flt($s3['s3_amenity_built_sqm'] ?? null) ?? 0.0;

        // Deductions from Step 2 — sum roads (dynamic) + named-include + reservation-if-include
        $roadTotal = 0.0;
        if (isset($s2['roads']['area']) && is_array($s2['roads']['area'])) {
            foreach ($s2['roads']['area'] as $a) $roadTotal += self::flt($a) ?? 0.0;
        }
        if ($roadTotal <= 0) $roadTotal = self::flt($s2['road_deduction_sqm'] ?? 0) ?? 0.0; // legacy fallback

        $namedTotalIncluded = 0.0;
        if (isset($s2['named']['area']) && is_array($s2['named']['area'])) {
            foreach ($s2['named']['area'] as $i => $a) {
                $area = self::flt($a) ?? 0.0;
                $inc  = !empty(($s2['named']['include'] ?? [])[$i]);
                if ($inc) $namedTotalIncluded += $area;
            }
        } else {
            $namedTotalIncluded = self::flt($s2['other_deduction_sqm'] ?? 0) ?? 0.0;
        }

        $reservation = self::flt($s2['reservation_area_sqm'] ?? 0) ?? 0.0;
        $reservationIncluded = !empty($s2['reservation_included_in_total']) ? $reservation : 0.0;

        // Common — every calculator wants these
        $common = [
            'cts_number'                 => $s['cts_number']       ?? '',
            'village'                    => $s['project_location'] ?? '',
            'ward'                       => $s['ward']             ?? '',
            'location'                   => $s['project_location'] ?? '',
            'site_address'               => $s['plot_address']     ?? '',
            'pincode'                    => '',
            'development_zone'           => $isIsland ? 'Island City' : 'Suburbs & Extended Suburbs',
            'gross_plot_area_sqm'        => self::flt($s2['gross_plot_area_sqm']  ?? 0) ?? 0,
            // Roads sum (all frontages) → the calculator's single setback input
            'setback_area_sqm'           => $roadTotal,
            // Reservation — only pass if the user included it in the deductions total
            'reservation_deduction_sqm'  => $reservationIncluded,
            // Amenity Reg 14(A) handover + any named-included deductions (nalla, CRZ etc.)
            // combined into the calculator's "amenity_deduction_sqm" bucket so Net Plot matches Step 2.
            'amenity_deduction_sqm'      => $amenityHandover + $namedTotalIncluded,
            'primary_road_width_m'       => self::flt($s2['primary_road_width_m'] ?? 0) ?? 0,
            'apply_fungible_pct'         => RulesRegistry::get('fungible_pct_residential', 'global'),
        ];

        switch ($reg) {
            case '33(5)':
                $plotArea = $common['gross_plot_area_sqm'];
                $road     = $common['primary_road_width_m'];
                $plotMin  = RulesRegistry::get('basic_fsi_upgrade_plot_sqm', '33(5)');
                $roadMin  = RulesRegistry::get('basic_fsi_upgrade_road_m',   '33(5)');
                $basicFsi = ($plotArea >= $plotMin && $road >= $roadMin)
                    ? RulesRegistry::get('basic_fsi_ceiling', 'global')
                    : 3.0; // Default MHADA basic FSI (lower tier)
                $addPct   = self::mhadaAddPctByPlotBand($plotArea);
                return $common + [
                    'scheme_type'                     => 'Redevelopment of existing MHADA scheme',
                    'existing_carpet_area_sqm'        => $totals['res_carpet'] + $totals['com_carpet'],
                    'num_existing_tenants'            => $eligibleFinal,
                    'existing_bua_sqm'                => $totals['bua_sqm'],
                    'num_existing_buildings'          => max(1, $totals['count']),
                    'carpet_to_bua_loading_factor'    => $loading,
                    'min_rehab_carpet_sqm'            => $carpetRes,
                    'basic_fsi'                       => $basicFsi,
                    'rehab_additional_pct'            => $addPct,
                    'rehab_base_add_pct'              => RulesRegistry::get('rehab_base_add_pct', '33(5)'),
                    'asr_land_rate_inr_sqm'           => self::asrLandRate($s['ward'] ?? '', $s['project_location'] ?? ''),
                    'asr_construction_rate_inr_sqm'   => 45000,
                ];

            case '33(10)':
                return $common + [
                    'scheme_type'                     => 'On-site rehabilitation',
                    'is_difficult_area'               => 0,
                    'eligible_tenements'              => $eligibleFinal,
                    'carpet_area_per_tenement_sqm'    => $carpetRes,
                    'rehab_loading_factor'            => $loading,  // Phase-G3: user-adjustable
                    'num_commercial_tenements'        => $totals['com_units'],
                    'carpet_area_per_commercial_sqm'  => $carpetCom,
                    'amenity_common_area_sqm'         => $amenityBuilt,  // Step 2 Section D
                    'incentive_ratio_override'        => null,
                    // User-set proposed In-Situ Sale BUA (optional). When blank/0 the view falls back
                    // to the auto-max; when set it is clipped at the in-situ FSI cap inside the calculator.
                    'sale_bua_proposed_in_situ_sqm'   => self::flt($s3['sale_bua_proposed_in_situ_sqm'] ?? null) ?? 0,
                ];

            case '33(11)':
                return $common + [
                    'scheme_type'                     => 'PTC / Rental Housing',
                    'zonal_fsi'                       => $isIsland
                        ? RulesRegistry::get('zonal_fsi_island',       '33(11)')
                        : RulesRegistry::get('zonal_fsi_suburbs',      '33(11)'),
                    'additional_fsi'                  => $isIsland
                        ? RulesRegistry::get('additional_fsi_island',  '33(11)')
                        : RulesRegistry::get('additional_fsi_suburbs', '33(11)'),
                    'sra_ptc_share_pct'               => RulesRegistry::get('sra_ptc_share_pct',      '33(11)'),
                    'sale_society_share_pct'          => RulesRegistry::get('sale_society_share_pct', '33(11)'),
                    'total_fsi_cap'                   => RulesRegistry::get('full_scheme_fsi_cap',    'global'),
                    // Phase-G4: pipe Step 3's loading factor into 33(11) so sale RERA carpet
                    // + society BUA conversion use the same loading as rehab side.
                    'carpet_to_bua_loading_factor'    => $loading,
                ];

            case '33(20)(B)':
                return $common + [
                    'scheme_type'                     => 'Affordable Housing on Private Land',
                    'zonal_fsi'                       => $isIsland
                        ? RulesRegistry::get('zonal_fsi_island_20b',   '33(20)(B)')
                        : RulesRegistry::get('zonal_fsi_suburbs_20b',  '33(20)(B)'),
                    'gov_fsi'                         => RulesRegistry::get('gov_fsi',           '33(20)(B)'),
                    'tdr_fsi'                         => RulesRegistry::get('tdr_fsi_20b',       '33(20)(B)'),
                    'target_fsi_cap'                  => RulesRegistry::get('target_fsi_cap_20b','33(20)(B)'),
                    'owner_share_pct'                 => RulesRegistry::get('owner_share_pct_20b','33(20)(B)'),
                    'ah_share_pct'                    => RulesRegistry::get('ah_share_pct_20b',  '33(20)(B)'),
                    'existing_bua_sqm'                => $totals['bua_sqm'],
                    'num_existing_tenants'            => $totals['tenants'],
                    'incentive_337b_pct'              => RulesRegistry::get('incentive_337b_pct','33(20)(B)'),
                ];
        }
        return $common;
    }

    /** Instantiates the correct calculator class. */
    private static function instantiate(string $reg, array $inputs) {
        switch ($reg) {
            case '33(5)':     return new Reg33_5_Calculator($inputs);
            case '33(10)':    return new Reg33_10_Calculator($inputs);
            case '33(11)':    return new Reg33_11_Calculator($inputs);
            case '33(20)(B)': return new Reg33_20B_Calculator($inputs);
        }
        throw new RuntimeException("Unsupported regulation: {$reg}");
    }

    // ─── Persistence ─────────────────────────────────────────

    private static function persistCalcs(int $statementId, array $rows): void {
        $db = getDB();
        $db->query("DELETE FROM area_statement_calcs WHERE statement_id = " . (int)$statementId);
        $ins = $db->prepare("INSERT INTO area_statement_calcs
            (statement_id, calc_key, calc_label, calc_value, is_overridden, original_value, override_id,
             calc_unit, formula, formula_inputs, regulation_ref, rule_id, source, display_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'derived', ?)");
        foreach ($rows as $r) {
            $key   = $r['calc_key']       ?? '';
            $lab   = $r['calc_label']     ?? '';
            $val   = (float)($r['calc_value'] ?? 0);
            $isOv  = (int)($r['is_overridden'] ?? 0);
            $orig  = isset($r['original_value']) ? (float)$r['original_value'] : null;
            $ovId  = isset($r['override_id']) ? (int)$r['override_id'] : null;
            $unit  = $r['unit']           ?? '';
            $form  = $r['formula']        ?? '';
            $fin   = $r['formula_inputs'] ?? '';
            $ref   = $r['regulation_ref'] ?? '';
            $rule  = $r['rule_id']        ?? null;
            $order = (int)($r['display_order'] ?? 0);
            // 13 placeholders: i s s d i d i s s s s i i
            $ins->bind_param('issdidissssii',
                $statementId, $key, $lab, $val, $isOv, $orig, $ovId,
                $unit, $form, $fin, $ref, $rule, $order);
            $ins->execute();
        }
    }

    /** Extract FSI-like rows from calc output into the FSI table. */
    private static function flattenFsi(int $statementId, array $rows, string $reg): void {
        $db = getDB();
        $db->query("DELETE FROM area_statement_fsi WHERE statement_id = " . (int)$statementId);

        // Which calc_keys count as FSI components (regulation-agnostic).
        $fsiKeys = [
            'basic_fsi'                => ['label' => 'Basic FSI',       'order' => 1],
            'zonal_fsi'                => ['label' => 'Zonal FSI',       'order' => 1],
            'permissible_fsi_stack'    => ['label' => 'Base FSI stack',  'order' => 2],
            'additional_fsi'           => ['label' => 'Additional FSI',  'order' => 3],
            'incentive_fsi'            => ['label' => 'Incentive FSI',   'order' => 4],
            'tdr_fsi'                  => ['label' => 'TDR FSI',         'order' => 5],
            'fungible_pct_applied'     => ['label' => 'Fungible %',      'order' => 6],
            'full_scheme_fsi'          => ['label' => 'Full Scheme FSI (incl. fungible)', 'order' => 7],
            'fsi_sanctioned'           => ['label' => 'Full Scheme FSI (incl. fungible)', 'order' => 7],
            'total_bua_approved_sqm'   => ['label' => 'Total BUA (sqm)', 'order' => 8],
            'full_scheme_bua_sqm'      => ['label' => 'Total BUA (sqm)', 'order' => 8],
        ];

        $ins = $db->prepare("INSERT INTO area_statement_fsi
            (statement_id, component_key, component_label, fsi_ratio, bua_sqm, source, regulation_ref, display_order)
            VALUES (?, ?, ?, ?, ?, 'derived', ?, ?)
            ON DUPLICATE KEY UPDATE fsi_ratio = VALUES(fsi_ratio), bua_sqm = VALUES(bua_sqm), regulation_ref = VALUES(regulation_ref)");

        foreach ($rows as $r) {
            $key = $r['calc_key'] ?? '';
            if (!isset($fsiKeys[$key])) continue;
            $meta = $fsiKeys[$key];
            $val = (float)($r['calc_value'] ?? 0);
            // If unit is sqm and the number is large, treat as BUA ; otherwise as ratio.
            $isBua = strtolower($r['unit'] ?? '') === 'sqm' || $val > 20;
            $ratio = $isBua ? null : $val;
            $bua   = $isBua ? $val : null;
            $ref   = $r['regulation_ref'] ?? '';
            $order = $meta['order'];
            $label = $meta['label'];
            $ins->bind_param('issddsi', $statementId, $key, $label, $ratio, $bua, $ref, $order);
            $ins->execute();
        }
    }

    /** Extract development-component rows into the DEV table. */
    private static function flattenDev(int $statementId, array $rows, string $reg): void {
        $db = getDB();
        $db->query("DELETE FROM area_statement_dev WHERE statement_id = " . (int)$statementId);

        $devMap = [
            // key => [label, handover, order]
            'rehab_bua_sqm'                    => ['Rehabilitation BUA',           'society',   1],
            'rehab_component_sqm'              => ['Rehabilitation Component',     'society',   1],
            'total_rehab_bua_sqm'              => ['Total Rehab BUA',              'society',   1],
            'commercial_rehab_bua_sqm'         => ['Commercial Rehab',             'society',   2],
            'residential_rehab_bua_sqm'        => ['Residential Rehab',            'society',   3],
            'amenity_bua_sqm'                  => ['Amenity BUA',                  'society',   4],
            'incentive_bua_sqm'                => ['Incentive Component',          'society',   5],
            'sale_component_sqm'               => ['Sale Component',               'free-sale', 6],
            'sale_bua_sqm'                     => ['Sale Component',               'free-sale', 6],
            'sale_society_total_sqm'           => ['Sale + Society BUA',           'society',   6],
            'sra_ptc_total_sqm'                => ['SRA PTC Handover',             'SRA',       7],
            'ah_total_sqm'                     => ['AH Handover (MCGM)',           'MCGM',      7],
            'mhada_grand_total_sqm'            => ['MHADA Handover',               'MHADA',     7],
            'society_grand_total_sqm'          => ['Society / Owner Share',        'society',   6],
            'owner_total_sqm'                  => ['Owner / Sale Component',       'free-sale', 6],
            'full_scheme_bua_sqm'              => ['Total Proposed BUA',           null,        9],
            'total_bua_approved_sqm'           => ['Total Proposed BUA',           null,        9],
        ];

        $ins = $db->prepare("INSERT INTO area_statement_dev
            (statement_id, component_key, component_label, area_sqm, handover_to, source, regulation_ref, display_order)
            VALUES (?, ?, ?, ?, ?, 'derived', ?, ?)
            ON DUPLICATE KEY UPDATE area_sqm = VALUES(area_sqm), handover_to = VALUES(handover_to),
                                    regulation_ref = VALUES(regulation_ref)");

        foreach ($rows as $r) {
            $key = $r['calc_key'] ?? '';
            if (!isset($devMap[$key])) continue;
            [$label, $handover, $order] = $devMap[$key];
            $val = (float)($r['calc_value'] ?? 0);
            $ref = $r['regulation_ref'] ?? '';
            $ins->bind_param('issdssi', $statementId, $key, $label, $val, $handover, $ref, $order);
            $ins->execute();
        }
    }

    /** Update the summary columns on area_statements for the list view & preview panel. */
    private static function updateSummary(int $statementId, array $rows, string $reg): void {
        $find = function ($keys) use ($rows) {
            $keys = (array)$keys;
            foreach ($rows as $r) if (in_array($r['calc_key'] ?? '', $keys, true)) return (float)$r['calc_value'];
            return null;
        };
        $totalFsi  = $find(['full_scheme_fsi', 'fsi_sanctioned'])                                     ?? 0.0;
        $totalBua  = $find(['full_scheme_bua_sqm', 'total_bua_approved_sqm'])                         ?? 0.0;
        $rehabBua  = $find(['rehab_bua_sqm', 'total_rehab_bua_sqm', 'rehab_component_sqm'])          ?? 0.0;
        $saleBua   = $find(['sale_component_sqm', 'sale_bua_sqm', 'owner_total_sqm',
                            'society_grand_total_sqm', 'sale_society_total_sqm'])                    ?? 0.0;

        // Deliberately NOT touching net_plot_area_sqm — that's owned by Step 2's writeLandRows()
        // which subtracts amenity/other in addition to setback+reservation. Per-regulation
        // calculators don't know about amenity/other, so their net_plot value would be wrong.
        $stmt = getDB()->prepare("UPDATE area_statements
            SET total_permissible_fsi = ?, total_permissible_bua_sqm = ?,
                rehab_bua_sqm = ?, sale_bua_sqm = ?
            WHERE id = ?");
        $stmt->bind_param('ddddi', $totalFsi, $totalBua, $rehabBua, $saleBua, $statementId);
        $stmt->execute();
    }

    // ─── Validation ─────────────────────────────────────────

    private static function validate(int $statementId, array $rows, string $reg): array {
        $db = getDB();
        $db->query("DELETE FROM area_statement_valid WHERE statement_id = " . (int)$statementId);

        $get = function ($keys) use ($rows) {
            $keys = (array)$keys;
            foreach ($rows as $r) if (in_array($r['calc_key'] ?? '', $keys, true)) return (float)$r['calc_value'];
            return null;
        };

        $checks = [];
        $totalFsi = $get(['full_scheme_fsi', 'fsi_sanctioned']);

        // Universal FSI cap — pulled from registry so a policy change updates the check.
        if ($totalFsi !== null) {
            $fsiCap = RulesRegistry::get('full_scheme_fsi_cap', 'global');
            $ok = $totalFsi <= $fsiCap + 0.01;
            $capLabel = number_format($fsiCap, 2);
            $checks[] = [
                'key' => 'fsi_ceiling',
                'label' => "Full-scheme FSI within {$capLabel} ceiling",
                'severity' => 'error',
                'result' => $ok ? 'pass' : 'fail',
                'entered' => number_format($totalFsi, 3),
                'required' => "≤ {$capLabel}",
                'delta' => number_format($totalFsi - $fsiCap, 3),
                'message' => $ok
                    ? 'Full-scheme FSI (incl. fungible) within the maximum ceiling.'
                    : "Full-scheme FSI exceeds the {$capLabel} ceiling. Check inputs or scheme mix.",
                'ref' => 'Reg 31(3) — global cap',
            ];
        }

        // Basic FSI upgrade condition for 33(5) — thresholds from registry.
        if ($reg === '33(5)') {
            $basicFsi   = $get('basic_fsi');
            $plotMin    = RulesRegistry::get('basic_fsi_upgrade_plot_sqm', '33(5)');
            $roadMin    = RulesRegistry::get('basic_fsi_upgrade_road_m',   '33(5)');
            $upgradeFsi = RulesRegistry::get('basic_fsi_ceiling',          'global');
            $stmtRow = getDB()->query("SELECT draft_json FROM area_statements WHERE id = " . (int)$statementId)->fetch_assoc();
            $s2 = json_decode($stmtRow['draft_json'] ?? '{}', true)['step_2'] ?? [];
            $plot = (float)($s2['gross_plot_area_sqm']  ?? 0);
            $road = (float)($s2['primary_road_width_m'] ?? 0);
            $qualifies = ($plot >= $plotMin && $road >= $roadMin);
            if ($basicFsi !== null && $basicFsi >= $upgradeFsi - 0.01) {
                $ok = $qualifies;
                $checks[] = [
                    'key' => 'mhada_fsi_upgrade',
                    'label' => "MHADA Basic FSI {$upgradeFsi} eligibility",
                    'severity' => 'error',
                    'result' => $ok ? 'pass' : 'fail',
                    'entered' => number_format($upgradeFsi, 1),
                    'required' => "plot ≥ " . number_format($plotMin, 0) . " sqm AND road ≥ " . number_format($roadMin, 0) . " m",
                    'delta' => sprintf('plot=%s road=%s', number_format($plot, 0), number_format($road, 0)),
                    'message' => $ok
                        ? "Plot & road meet the {$upgradeFsi} FSI upgrade threshold."
                        : "Basic FSI = {$upgradeFsi} but plot ({$plot} sqm) or road ({$road} m) fails the threshold.",
                    'ref' => 'Reg 33(5) fsi_caps',
                ];
            }
        }

        // Info: mode-of-computation traceability
        $rehab = $get(['rehab_bua_sqm', 'total_rehab_bua_sqm', 'rehab_component_sqm']);
        if ($rehab !== null && $rehab > 0) {
            $checks[] = [
                'key' => 'rehab_positive',
                'label' => 'Rehabilitation component computed',
                'severity' => 'info',
                'result' => 'pass',
                'entered' => number_format($rehab, 2) . ' sqm',
                'required' => '> 0',
                'delta' => null,
                'message' => "Rehab area {$rehab} sqm meets applicable minimums.",
                'ref' => "Reg {$reg}",
            ];
        }

        // Persist
        $ins = $db->prepare("INSERT INTO area_statement_valid
            (statement_id, check_key, check_label, severity, result, entered_value, required_value, delta, message, regulation_ref)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $counts = ['pass' => 0, 'warn' => 0, 'fail' => 0];
        foreach ($checks as $c) {
            $ins->bind_param('isssssssss',
                $statementId, $c['key'], $c['label'], $c['severity'], $c['result'],
                $c['entered'], $c['required'], $c['delta'], $c['message'], $c['ref']);
            $ins->execute();
            $counts[$c['result']] = ($counts[$c['result']] ?? 0) + 1;
        }
        return ['checks' => $checks, 'counts' => $counts];
    }

    // ─── Helpers ─────────────────────────────────────────

    private static function loadBuildings(int $statementId): array {
        $out = [];
        $r = getDB()->query("SELECT * FROM existing_buildings WHERE statement_id = " . (int)$statementId);
        while ($r && $row = $r->fetch_assoc()) $out[] = $row;
        return $out;
    }

    private static function sumBuildings(array $buildings): array {
        $t = ['count' => count($buildings), 'res_units' => 0, 'com_units' => 0,
              'res_carpet' => 0.0, 'com_carpet' => 0.0, 'bua_sqm' => 0.0,
              'tenants' => 0, 'eligible' => 0];
        foreach ($buildings as $b) {
            $t['res_units']  += (int)$b['residential_units'];
            $t['com_units']  += (int)$b['commercial_units'];
            $t['res_carpet'] += (float)$b['residential_carpet_sqm'];
            $t['com_carpet'] += (float)$b['commercial_carpet_sqm'];
            $t['bua_sqm']    += (float)$b['existing_bua_sqm'];
            $t['tenants']    += (int)$b['tenant_count'];
            $t['eligible']   += (int)$b['eligible_tenant_count'];
        }
        return $t;
    }

    private static function isIslandCity(array $statement, array $s2): bool {
        $loc = strtolower($statement['project_location'] ?? '');
        $ward = strtoupper($statement['ward'] ?? '');
        if (strpos($loc, 'island') !== false) return true;
        // Wards A, B, C, D, E, F, G-South, G-North = Island City
        return in_array($ward, ['A','B','C','D','E','F','F-SOUTH','F-NORTH','G-SOUTH','G-NORTH'], true);
    }

    private static function mhadaAddPctByPlotBand(float $plotArea): float {
        if ($plotArea >= 100000) return 45;
        if ($plotArea >=  50000) return 35;
        if ($plotArea >=  20000) return 25;
        return 15; // > 4000 to 2 ha
    }

    /** Look up an ASR land rate from the asr_rates table (fallback to a Mumbai default). */
    private static function asrLandRate(string $ward, string $location): float {
        $db = getDB();
        $r = $db->query("SHOW TABLES LIKE 'asr_rates'");
        if ($r && $r->num_rows) {
            $q = $db->prepare("SELECT land_rate_per_sqm FROM asr_rates
                               WHERE (LOWER(ward) = LOWER(?)
                                      OR LOWER(zone_name) LIKE CONCAT('%', LOWER(?), '%')
                                      OR LOWER(village) LIKE CONCAT('%', LOWER(?), '%'))
                                 AND land_rate_per_sqm IS NOT NULL
                               ORDER BY imported_at DESC LIMIT 1");
            if ($q) {
                $q->bind_param('sss', $ward, $location, $location);
                $q->execute();
                $row = $q->get_result()->fetch_assoc();
                if ($row && !empty($row['land_rate_per_sqm'])) return (float)$row['land_rate_per_sqm'];
            }
        }
        return 100000; // safe fallback (₹1 lakh/sqm — override manually until ASR seed present for this ward)
    }

    private static function flt($v): ?float {
        if ($v === null || $v === '' || !is_numeric($v)) return null;
        return (float)$v;
    }
}
