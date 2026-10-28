<?php
/**
 * FormulaDiscrepancyDetector — cross-checks calculator outputs against independent
 * derivations of the same quantity. Fires whenever a stored calc row diverges from
 * an identity or an independently computed value.
 *
 * Wired into AreaCalcEngine::run() — every recompute rewrites this statement's
 * discrepancy rows. Users resolve rows through the UI; unresolved rows appear on
 * the Feasibility view + Area Statement report.
 *
 * Adding a new check: register it in self::checks() with:
 *   'key'       => 'unique_check_id',
 *   'label'     => 'Human-readable name',
 *   'severity'  => 'info' | 'warning' | 'error',
 *   'tol_pct'   => 0.10,                 // 0.10% ≈ float noise
 *   'ref'       => 'DCPR clause',
 *   'run'       => fn(array $calcMap, array $ctx): ?array
 *                    // returns null on pass, or ['expected', 'actual', 'formula', 'inputs', 'desc']
 */

require_once __DIR__ . '/../config/database.php';

class FormulaDiscrepancyDetector {

    /**
     * Run every registered check against a statement's calc rows. Deletes old
     * discrepancy rows for this statement and writes fresh ones. Returns the
     * count of triggered discrepancies.
     */
    public static function run(int $statementId): int {
        $db = getDB();

        // Load calc rows into a key→value map for fast lookup
        $res = $db->query("SELECT calc_key, calc_value FROM area_statement_calcs
                           WHERE statement_id = " . (int)$statementId);
        $calcMap = [];
        while ($r = $res->fetch_assoc()) $calcMap[$r['calc_key']] = (float)$r['calc_value'];
        if (empty($calcMap)) return 0;

        // Load statement + draft for scheme-specific context
        $stmt = $db->prepare("SELECT selected_regulation_key, suggested_regulation_key, draft_json
                              FROM area_statements WHERE id = ?");
        $stmt->bind_param('i', $statementId);
        $stmt->execute();
        $stRow = $stmt->get_result()->fetch_assoc();
        $reg = $stRow['selected_regulation_key'] ?: $stRow['suggested_regulation_key'];
        $draft = json_decode($stRow['draft_json'] ?? '{}', true) ?: [];

        $ctx = [
            'reg'   => $reg,
            'draft' => $draft,
        ];

        // Preserve resolved rows across re-runs: capture keys of prior resolved rows
        $resolved = [];
        $rs = $db->query("SELECT check_key, resolved_by, resolved_at, resolution_notes
                          FROM formula_discrepancies
                          WHERE statement_id = " . (int)$statementId . " AND resolved = 1");
        while ($r = $rs->fetch_assoc()) $resolved[$r['check_key']] = $r;

        // Wipe and reseed
        $db->query("DELETE FROM formula_discrepancies WHERE statement_id = " . (int)$statementId);

        $written = 0;
        foreach (self::checks() as $check) {
            // Some checks are scheme-scoped — skip those that don't apply
            if (!empty($check['scope']) && !in_array($reg, (array)$check['scope'], true)) continue;

            $result = ($check['run'])($calcMap, $ctx);
            if ($result === null) continue;   // check passed

            $expected = (float)$result['expected'];
            $actual   = (float)$result['actual'];
            $absDiff  = abs($expected - $actual);
            $den      = max(abs($expected), 1.0);
            $pctDiff  = 100.0 * $absDiff / $den;
            $tolPct   = (float)($check['tol_pct'] ?? 0.10);

            if ($pctDiff <= $tolPct) continue; // within tolerance → not a discrepancy

            // Restore resolution state if the same check was resolved before
            $priorResolved = $resolved[$check['key']] ?? null;

            $ins = $db->prepare("INSERT INTO formula_discrepancies
                (statement_id, check_key, check_label, category, severity,
                 expected_value, actual_value, variance_abs, variance_pct, tolerance_pct,
                 formula, inputs_json, regulation_ref, description,
                 resolved, resolved_by, resolved_at, resolution_notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $cat      = $check['category'] ?? 'cross_check';
            $sev      = $check['severity'] ?? 'warning';
            $inputsJs = json_encode($result['inputs'] ?? [], JSON_UNESCAPED_SLASHES);
            $formula  = $result['formula'] ?? '';
            $ref      = $check['ref'] ?? '';
            $desc     = $result['desc'] ?? '';
            $isRes    = $priorResolved ? 1 : 0;
            $resBy    = $priorResolved['resolved_by'] ?? null;
            $resAt    = $priorResolved['resolved_at'] ?? null;
            $resNote  = $priorResolved['resolution_notes'] ?? null;

            $ins->bind_param('issssdddddssssiiss',
                $statementId, $check['key'], $check['label'], $cat, $sev,
                $expected, $actual, $absDiff, $pctDiff, $tolPct,
                $formula, $inputsJs, $ref, $desc,
                $isRes, $resBy, $resAt, $resNote);
            $ins->execute();
            $written++;
        }
        return $written;
    }

    /**
     * Load current discrepancies for a statement (default: unresolved first).
     */
    public static function load(int $statementId, bool $includeResolved = true): array {
        $db = getDB();
        $sql = "SELECT * FROM formula_discrepancies WHERE statement_id = ?";
        if (!$includeResolved) $sql .= " AND resolved = 0";
        $sql .= " ORDER BY resolved ASC,
                           FIELD(severity,'error','warning','info'),
                           variance_pct DESC";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $statementId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        return $rows;
    }

    /**
     * Mark one discrepancy resolved with an audit note.
     */
    public static function resolve(int $discrepancyId, ?int $userId, string $notes = ''): bool {
        $db = getDB();
        $stmt = $db->prepare("UPDATE formula_discrepancies
                              SET resolved = 1, resolved_by = ?, resolved_at = CURRENT_TIMESTAMP,
                                  resolution_notes = ?
                              WHERE id = ?");
        $stmt->bind_param('isi', $userId, $notes, $discrepancyId);
        return (bool)$stmt->execute();
    }

    // ─── Registered checks ───────────────────────────────────────────────

    private static function checks(): array {
        $g = fn(array $m, string $k): ?float => isset($m[$k]) ? (float)$m[$k] : null;

        return [
            // (1) Rehab Component identity: rehab_tenement_bua + rehab_amenity_block
            [
                'key'      => 'rehab_component_identity',
                'label'    => 'Rehab Component = Tenement BUA + Amenity Block',
                'severity' => 'error',
                'tol_pct'  => 0.10,
                'ref'      => '33(10) VIII 3.2',
                'scope'    => ['33(10)'],
                'run'      => function (array $m, array $ctx) use ($g): ?array {
                    $comp   = $g($m, 'rehab_component_sqm');
                    $tenBua = $g($m, 'rehab_tenement_bua_sqm');
                    $amen   = $g($m, 'rehab_amenity_block_sqm');
                    if ($comp === null || $tenBua === null || $amen === null) return null;
                    $expected = $tenBua + $amen;
                    return [
                        'expected' => $expected,
                        'actual'   => $comp,
                        'formula'  => 'rehab_component_sqm ?= rehab_tenement_bua_sqm + rehab_amenity_block_sqm',
                        'inputs'   => ['tenement_bua' => $tenBua, 'amenity_block' => $amen, 'component' => $comp],
                        'desc'     => 'The persisted rehab component should equal the sum of its two parts.',
                    ];
                },
            ],

            // (2) Sale Component identity: rehab_component × sale_ratio_effective
            [
                'key'      => 'sale_component_identity',
                'label'    => 'Sale Component = Rehab Component × Effective Sale Ratio',
                'severity' => 'error',
                'tol_pct'  => 0.20,
                'ref'      => '33(10) VIII 3.3–3.6',
                'scope'    => ['33(10)'],
                'run'      => function (array $m, array $ctx) use ($g): ?array {
                    $sale     = $g($m, 'sale_component_base_sqm') ?? $g($m, 'sale_component_sqm');
                    $comp     = $g($m, 'rehab_component_sqm');
                    $ratio    = $g($m, 'sale_ratio_effective');
                    if ($sale === null || $comp === null || $ratio === null) return null;
                    $expected = $comp * $ratio;
                    return [
                        'expected' => $expected,
                        'actual'   => $sale,
                        'formula'  => 'sale_component_base_sqm ?= rehab_component_sqm × sale_ratio_effective',
                        'inputs'   => ['rehab_comp' => $comp, 'ratio' => $ratio, 'sale' => $sale],
                        'desc'     => 'The base sale component (before reservation-incentive add-on) is the rehab component multiplied by the effective ratio.',
                    ];
                },
            ],

            // (3) Total BUA identity: rehab_tenement_bua + sale_component
            [
                'key'      => 'total_bua_identity',
                'label'    => 'Total BUA Approved = Rehab Tenement BUA + Sale Component',
                'severity' => 'error',
                'tol_pct'  => 0.20,
                'ref'      => 'Reg 31(1) + 33(10)',
                'scope'    => ['33(10)'],
                'run'      => function (array $m, array $ctx) use ($g): ?array {
                    $total = $g($m, 'total_bua_approved_sqm');
                    $reh   = $g($m, 'rehab_tenement_bua_sqm');
                    $sale  = $g($m, 'sale_component_sqm');
                    if ($total === null || $reh === null || $sale === null) return null;
                    return [
                        'expected' => $reh + $sale,
                        'actual'   => $total,
                        'formula'  => 'total_bua_approved_sqm ?= rehab_tenement_bua_sqm + sale_component_sqm',
                        'inputs'   => ['rehab_bua' => $reh, 'sale_comp' => $sale, 'total' => $total],
                        'desc'     => 'Amenity block is Reg-31(1)-exempt so it is not included in the FSI-countable total.',
                    ];
                },
            ],

            // (4) FSI identity: total_bua / plot_area_for_fsi
            [
                'key'      => 'fsi_bua_ratio',
                'label'    => 'FSI Sanctioned = Total BUA / Plot Area for FSI',
                'severity' => 'error',
                'tol_pct'  => 0.20,
                'ref'      => '33(10) VIII 3.7',
                'scope'    => ['33(10)'],
                'run'      => function (array $m, array $ctx) use ($g): ?array {
                    $fsi   = $g($m, 'fsi_sanctioned');
                    $total = $g($m, 'total_bua_approved_sqm');
                    $plot  = $g($m, 'plot_area_for_fsi_sqm');
                    if ($fsi === null || $total === null || $plot === null || $plot <= 0) return null;
                    return [
                        'expected' => $total / $plot,
                        'actual'   => $fsi,
                        'formula'  => 'fsi_sanctioned ?= total_bua_approved_sqm / plot_area_for_fsi_sqm',
                        'inputs'   => ['total_bua' => $total, 'plot_for_fsi' => $plot, 'fsi' => $fsi],
                        'desc'     => 'The FSI ratio must equal the sanctioned BUA divided by the plot-for-FSI base.',
                    ];
                },
            ],

            // (5) FSI Ceiling — informational when close to 5.40 cap
            [
                'key'      => 'fsi_ceiling_hit',
                'label'    => 'FSI approaching statutory ceiling',
                'severity' => 'warning',
                'category' => 'cross_check',
                'tol_pct'  => 0,     // any excess triggers
                'ref'      => 'Reg 31(3) — 5.40 cap',
                'run'      => function (array $m, array $ctx) use ($g): ?array {
                    // Load cap dynamically from registry so a policy change updates the check.
                    require_once __DIR__ . '/RulesRegistry.php';
                    $cap = RulesRegistry::get('full_scheme_fsi_cap', 'global');
                    $fsi = $g($m, 'fsi_sanctioned') ?? $g($m, 'full_scheme_fsi');
                    if ($fsi === null) return null;
                    // Only flag when actually AT or above cap; use tol_pct=0 → always fires when expected!=actual
                    if ($fsi < $cap * 0.95) return null; // < 95% of cap → not worth flagging
                    return [
                        'expected' => $cap,
                        'actual'   => $fsi,
                        'formula'  => "fsi_sanctioned ({$fsi}) approaches or exceeds the {$cap} ceiling",
                        'inputs'   => ['fsi' => $fsi, 'cap' => $cap],
                        'desc'     => $fsi > $cap
                            ? 'FSI exceeds the statutory ceiling — surplus is only consumable as TDR off-site.'
                            : "FSI is within 5% of the {$cap} ceiling — plan for TDR utilisation.",
                    ];
                },
            ],

            // (6) Sale Fungible = 35% of sale
            [
                'key'      => 'sale_fungible_pct',
                'label'    => 'Sale Fungible = Sale Component × Fungible %',
                'severity' => 'error',
                'tol_pct'  => 0.10,
                'ref'      => 'Reg 31(3)',
                'run'      => function (array $m, array $ctx) use ($g): ?array {
                    $sf   = $g($m, 'sale_fungible_sqm');
                    $sale = $g($m, 'sale_component_sqm');
                    if ($sf === null || $sale === null) return null;
                    require_once __DIR__ . '/RulesRegistry.php';
                    $pct = RulesRegistry::get('fungible_pct_residential', 'global');
                    return [
                        'expected' => $sale * $pct / 100.0,
                        'actual'   => $sf,
                        'formula'  => "sale_fungible_sqm ?= sale_component_sqm × {$pct}%",
                        'inputs'   => ['sale' => $sale, 'pct' => $pct, 'fungible' => $sf],
                        'desc'     => "Residential fungible cap is {$pct}% per Reg 31(3).",
                    ];
                },
            ],

            // (7) Rehab BUA derivation: tenements × carpet × loading
            [
                'key'      => 'rehab_bua_derivation',
                'label'    => 'Rehab BUA = Tenements × Carpet × Loading',
                'severity' => 'warning',
                'tol_pct'  => 0.20,
                'ref'      => '33(10) VIII 3.2',
                'scope'    => ['33(10)'],
                'run'      => function (array $m, array $ctx) use ($g): ?array {
                    $reh    = $g($m, 'rehab_tenement_bua_sqm');
                    $carpet = $g($m, 'rehab_carpet_total_sqm');
                    if ($reh === null || $carpet === null || $carpet <= 0) return null;
                    // Implied loading factor
                    $impliedLoading = $reh / $carpet;
                    require_once __DIR__ . '/RulesRegistry.php';
                    $registryLoading = RulesRegistry::get('rehab_loading_factor', '33(10)');
                    // Not an error if user overrode; just informational
                    if (abs($impliedLoading - $registryLoading) < 0.005) return null;
                    return [
                        'expected' => $carpet * $registryLoading,
                        'actual'   => $reh,
                        'formula'  => "rehab_tenement_bua_sqm ?= rehab_carpet_total_sqm × registry loading ({$registryLoading})",
                        'inputs'   => ['carpet' => $carpet, 'implied_loading' => $impliedLoading, 'registry_loading' => $registryLoading, 'rehab_bua' => $reh],
                        'desc'     => "Implied loading is " . number_format($impliedLoading, 4) . " vs registry default " . number_format($registryLoading, 4) . " — Step 3 override in effect.",
                    ];
                },
            ],

            // (8) Net Plot = Gross - Total Deductions
            [
                'key'      => 'net_plot_identity',
                'label'    => 'Net Plot = Gross - Total Deductions',
                'severity' => 'error',
                'tol_pct'  => 0.10,
                'ref'      => 'Reg 30(A)(2)',
                'scope'    => ['33(10)'],
                'run'      => function (array $m, array $ctx) use ($g): ?array {
                    $net   = $g($m, 'net_plot_area_sqm');
                    $gross = $g($m, 'gross_plot_area_sqm');
                    $ded   = $g($m, 'total_deductions_sqm');
                    if ($net === null || $gross === null || $ded === null) return null;
                    return [
                        'expected' => $gross - $ded,
                        'actual'   => $net,
                        'formula'  => 'net_plot_area_sqm ?= gross_plot_area_sqm − total_deductions_sqm',
                        'inputs'   => ['gross' => $gross, 'deductions' => $ded, 'net' => $net],
                        'desc'     => 'Net plot must equal gross minus every counted deduction (ROS excluded per Reg 30(A) Note 1).',
                    ];
                },
            ],
        ];
    }
}
