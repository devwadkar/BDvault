<?php
/**
 * Reg33_5_Calculator — DCPR 33(5) MHADA Housing Scheme Redevelopment.
 *
 * Verified structurally against Worli MHADA feasibility
 *   (Shivneri CHS 1&2 + Shree Ganesh 3&4, 4381.35 sqm plot, 192 residential tenants,
 *    SDRR ₹1,78,070/sqm, Basic FSI 3.0 → 13,144.05 sqm BUA; Full scheme 5.4 FSI → 23,659.29 sqm).
 *
 * Model:
 *   1. Basic FSI (3.0 or 4.0 conditional) × net_plot = Basic BUA
 *   2. Rehabilitation Area = (existing_carpet + 35% base + N% plot-size-additional) × loading, min 35 sqm/tenement
 *   3. Basic Ratio = LR / RC → Incentive % (Table B: 70/60/50/40) & Sharing (Table C: 45/40/35/30 society vs MHADA)
 *   4. Incentive FSI = Rehab area × Incentive % (developer/society keeps)
 *   5. Balance BUA = Basic BUA − Rehab area − Incentive → shared between Society & MHADA per ratio
 *   6. Fungible 35% on top (Reg 31(3); free on rehab component per 33(5) clause 5(b))
 *   Cap: 4.0 base + 35% fungible = 5.4 total FSI
 */

require_once __DIR__ . '/../DCPRRuleLoader.php';
require_once __DIR__ . '/../RulesRegistry.php';

class Reg33_5_Calculator {

    private const SQM_TO_SQFT = 10.7639;
    private const SCOPE = '33(5)';

    private array $inputs;
    private array $rule;
    private ?int $ruleId;
    private array $calcs = [];
    private int $displayOrderSeq = 0;

    public function __construct(array $inputs) {
        $this->inputs = $inputs;
        $ruleRow = DCPRRuleLoader::fetchByKey('33(5)');
        if (!$ruleRow) throw new RuntimeException('DCPR rule 33(5) not loaded. Run /?page=dcpr_reload first.');
        $this->rule = $ruleRow['content'] ?? [];
        $db = getDB();
        $key = '33(5)';
        $stmt = $db->prepare("SELECT id FROM dcpr_rules WHERE regulation_key = ? ORDER BY version DESC LIMIT 1");
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $this->ruleId = $res ? (int)$res['id'] : null;
    }

    public function compute(): array {
        $this->calcs = [];
        $this->displayOrderSeq = 0;
        $this->computeLand();
        $this->computeBasicFSI();
        $this->computeRehabEntitlement();
        $this->computeBasicRatioAndTiers();
        $this->computeIncentiveAndBalance();
        $this->computeFungibleAndTotals();
        return $this->calcs;
    }

    private function computeLand(): void {
        $gross    = $this->num('gross_plot_area_sqm');
        $setback  = $this->num('setback_area_sqm', 0);
        $reserv   = $this->num('reservation_deduction_sqm', 0);
        $netPlot  = max(0, $gross - $setback - $reserv);

        $this->emit('land', 'gross_plot_area_sqm', 'Gross Plot Area', $gross, 'sqm',
            'User input — MHADA layout plot area',
            ['gross_plot_area_sqm' => $gross], 'Reg 30(A)(1)');
        if ($setback > 0) $this->emit('land', 'setback_area_sqm', 'DP Road / Setback Deduction',
            $setback, 'sqm', 'User input', ['setback_area_sqm' => $setback], 'Reg 30(A)(2)(a)');
        if ($reserv > 0) $this->emit('land', 'reservation_deduction_sqm', 'Reservation of Public Amenities',
            $reserv, 'sqm', 'User input — excluded from FSI per 33(5)(3)',
            ['reservation_deduction_sqm' => $reserv], '33(5) clause 3');

        $this->emit('land', 'net_plot_area_sqm', 'Net Plot Area (for FSI)',
            $netPlot, 'sqm',
            'gross_plot_area_sqm − setback − reservation',
            ['gross_plot_area_sqm' => $gross, 'setback_area_sqm' => $setback, 'reservation_deduction_sqm' => $reserv],
            'Reg 30(A)(2)');
    }

    private function computeBasicFSI(): void {
        $netPlot   = $this->getCalc('net_plot_area_sqm')['calc_value'] ?? 0;
        $basicFsi  = $this->num('basic_fsi', 3.0);
        $roadWidth = $this->num('primary_road_width_m', 0);

        // Compliance check on 4.0 upgrade condition
        $eligible40 = ($netPlot >= 4000 && $roadWidth >= 18.0);
        $note = $basicFsi >= 4.0 && !$eligible40
            ? 'WARNING: user chose Basic FSI 4.0 but plot < 4000 sqm OR road < 18m — verify 33(5) fsi_caps'
            : ($basicFsi >= 4.0 && $eligible40 ? 'Plot ≥ 4000 sqm AND road ≥ 18m — 4.0 eligible' : 'Standard 3.0 FSI applied');

        $basicBua = $netPlot * $basicFsi;
        $this->emit('fsi', 'basic_bua_sqm', "Basic BUA @ FSI {$basicFsi}",
            $basicBua, 'sqm',
            "net_plot_area_sqm × basic_fsi ({$note})",
            ['net_plot_area_sqm' => $netPlot, 'basic_fsi' => $basicFsi, 'road_width_m' => $roadWidth],
            '33(5) fsi_caps');
    }

    private function computeRehabEntitlement(): void {
        $existingCarpet    = $this->num('existing_carpet_area_sqm');
        $numTenants        = $this->num('num_existing_tenants');
        $baseAddPct        = $this->num('rehab_base_add_pct',           RulesRegistry::get('rehab_base_add_pct',           self::SCOPE));
        $plotSizeAddPct    = $this->num('rehab_additional_pct',         RulesRegistry::get('rehab_additional_pct',         self::SCOPE));
        $minCarpetPer      = $this->num('min_rehab_carpet_sqm',         RulesRegistry::get('min_rehab_carpet_sqm',         self::SCOPE));
        $loading           = $this->num('carpet_to_bua_loading_factor', RulesRegistry::get('carpet_to_bua_loading_mofa_33_5', self::SCOPE));

        // Rehab entitlement per tenement
        $avgExistingPer = $numTenants > 0 ? $existingCarpet / $numTenants : 0;
        $rehabPerBase = $avgExistingPer * (1 + $baseAddPct / 100.0);
        $rehabPerWithMin = max($rehabPerBase, $minCarpetPer);
        $rehabPerWithAdd = $rehabPerWithMin * (1 + $plotSizeAddPct / 100.0);

        $this->emit('rehab', 'avg_existing_carpet_per_tenement_sqm', 'Avg Existing Carpet / Tenement',
            $avgExistingPer, 'sqm',
            'existing_carpet_area_sqm / num_existing_tenants',
            ['existing_carpet_area_sqm' => $existingCarpet, 'num_existing_tenants' => $numTenants],
            '33(5) A(a) — baseline');

        $this->emit('rehab', 'rehab_carpet_per_tenement_sqm',
            "Rehab Carpet / Tenement (base +{$baseAddPct}% + plot-tier +{$plotSizeAddPct}%, floor {$minCarpetPer} sqm)",
            $rehabPerWithAdd, 'sqm',
            'MAX(avg_existing × (1 + base_add%), min_rehab_carpet) × (1 + plot_size_add%)',
            ['avg_existing' => $avgExistingPer, 'base_add_pct' => $baseAddPct, 'plot_size_add_pct' => $plotSizeAddPct, 'min_carpet' => $minCarpetPer],
            '33(5) A(a) + Table A');

        $totalRehabCarpet = $rehabPerWithAdd * $numTenants;
        $this->emit('rehab', 'total_rehab_carpet_sqm', 'Total Rehab Carpet Area',
            $totalRehabCarpet, 'sqm',
            'rehab_carpet_per_tenement × num_existing_tenants',
            ['rehab_carpet_per_tenement' => $rehabPerWithAdd, 'num_existing_tenants' => $numTenants],
            '33(5) A');

        $rehabBua = $totalRehabCarpet * $loading;
        $this->emit('rehab', 'rehab_bua_sqm', 'Rehabilitation BUA',
            $rehabBua, 'sqm',
            'total_rehab_carpet_sqm × loading_factor',
            ['total_rehab_carpet_sqm' => $totalRehabCarpet, 'loading_factor' => $loading],
            '33(5) A');
    }

    private function computeBasicRatioAndTiers(): void {
        $lr = $this->num('asr_land_rate_inr_sqm');
        $rc = $this->num('asr_construction_rate_inr_sqm');
        $basicRatio = $rc > 0 ? $lr / $rc : 0;

        $this->emit('basic_ratio', 'basic_ratio', 'Basic Ratio (LR / RC)',
            $basicRatio, 'ratio',
            'asr_land_rate_inr_sqm / asr_construction_rate_inr_sqm',
            ['asr_land_rate_inr_sqm' => $lr, 'asr_construction_rate_inr_sqm' => $rc],
            '33(5) B — Basic Ratio');

        // Look up incentive tier
        $tiers = $this->rule['operational_formulas_verified']['incentive_fsi_by_basic_ratio_tiers'] ?? [];
        $incentivePct = 40;
        $incentiveTierLabel = 'unknown';
        foreach ($tiers as $t) {
            $min = (float)$t['basic_ratio_min'];
            $max = $t['basic_ratio_max'] === null ? INF : (float)$t['basic_ratio_max'];
            if ($basicRatio >= $min && $basicRatio < $max) {
                $incentivePct = (float)$t['incentive_pct_of_rehab_area'];
                $incentiveTierLabel = "{$min} – " . ($t['basic_ratio_max'] === null ? '∞' : $t['basic_ratio_max']);
                break;
            }
        }
        $this->emit('basic_ratio', 'incentive_pct_of_rehab', "Incentive % of Rehab Area (Tier {$incentiveTierLabel})",
            $incentivePct, 'percent',
            'Lookup Table B: 70% (<2), 60% (2-4), 50% (4-6), 40% (>6)',
            ['basic_ratio' => $basicRatio],
            '33(5) B Table B');

        // Look up sharing tier
        $shareTiers = $this->rule['operational_formulas_verified']['sharing_of_balance_fsi_tiers'] ?? [];
        $societyShare = 30;
        $mhadaShare = 70;
        foreach ($shareTiers as $t) {
            $min = (float)$t['basic_ratio_min'];
            $max = $t['basic_ratio_max'] === null ? INF : (float)$t['basic_ratio_max'];
            if ($basicRatio >= $min && $basicRatio < $max) {
                $societyShare = (float)$t['society_share_pct'];
                $mhadaShare   = (float)$t['mhada_share_pct'];
                break;
            }
        }
        $this->emit('basic_ratio', 'society_share_pct', 'Society Share of Balance FSI',
            $societyShare, 'percent',
            'Lookup Table C by Basic Ratio: 45/40/35/30%',
            ['basic_ratio' => $basicRatio],
            '33(5) C Table C');
        $this->emit('basic_ratio', 'mhada_share_pct', 'MHADA Share of Balance FSI',
            $mhadaShare, 'percent',
            '100 - society_share_pct',
            ['society_share_pct' => $societyShare],
            '33(5) C Table C');
    }

    private function computeIncentiveAndBalance(): void {
        $basicBua      = $this->getCalc('basic_bua_sqm')['calc_value'] ?? 0;
        $rehabBua      = $this->getCalc('rehab_bua_sqm')['calc_value'] ?? 0;
        $incentivePct  = $this->getCalc('incentive_pct_of_rehab')['calc_value'] ?? 0;
        $societyPct    = $this->getCalc('society_share_pct')['calc_value'] ?? 0;
        $mhadaPct      = $this->getCalc('mhada_share_pct')['calc_value'] ?? 0;

        $incentiveBua = $rehabBua * ($incentivePct / 100.0);
        $this->emit('shares', 'incentive_bua_sqm', "Incentive FSI ({$incentivePct}% of Rehab)",
            $incentiveBua, 'sqm',
            'rehab_bua_sqm × incentive_pct_of_rehab / 100 — free BUA for developer/society',
            ['rehab_bua_sqm' => $rehabBua, 'incentive_pct' => $incentivePct],
            '33(5) B');

        $consumedByRehabPlusIncentive = $rehabBua + $incentiveBua;
        $balanceBua = max(0, $basicBua - $consumedByRehabPlusIncentive);

        $this->emit('shares', 'balance_fsi_bua_sqm', 'Balance BUA (Basic − Rehab − Incentive)',
            $balanceBua, 'sqm',
            'basic_bua_sqm − rehab_bua_sqm − incentive_bua_sqm',
            ['basic_bua_sqm' => $basicBua, 'rehab_bua_sqm' => $rehabBua, 'incentive_bua_sqm' => $incentiveBua],
            '33(5) C');

        $societyBalanceBua = $balanceBua * ($societyPct / 100.0);
        $mhadaBalanceBua   = $balanceBua * ($mhadaPct   / 100.0);

        $this->emit('shares', 'society_balance_bua_sqm', "Society Share of Balance ({$societyPct}%)",
            $societyBalanceBua, 'sqm',
            'balance_fsi_bua_sqm × society_share_pct / 100',
            ['balance_fsi_bua_sqm' => $balanceBua, 'society_share_pct' => $societyPct],
            '33(5) C');

        $this->emit('shares', 'mhada_balance_bua_sqm', "MHADA Share of Balance ({$mhadaPct}% — handed over)",
            $mhadaBalanceBua, 'sqm',
            'balance_fsi_bua_sqm × mhada_share_pct / 100',
            ['balance_fsi_bua_sqm' => $balanceBua, 'mhada_share_pct' => $mhadaPct],
            '33(5) C');

        // Society total (before fungible) = Rehab + Incentive + Society balance share
        $societyTotalBua = $rehabBua + $incentiveBua + $societyBalanceBua;
        $this->emit('shares', 'society_total_bua_sqm', 'Society Total BUA (Rehab + Incentive + Balance share)',
            $societyTotalBua, 'sqm',
            'rehab_bua_sqm + incentive_bua_sqm + society_balance_bua_sqm',
            ['rehab_bua_sqm' => $rehabBua, 'incentive_bua_sqm' => $incentiveBua, 'society_balance_bua_sqm' => $societyBalanceBua],
            '33(5)');
    }

    private function computeFungibleAndTotals(): void {
        $netPlot    = $this->getCalc('net_plot_area_sqm')['calc_value'] ?? 0;
        $basicBua   = $this->getCalc('basic_bua_sqm')['calc_value'] ?? 0;
        $rehabBua   = $this->getCalc('rehab_bua_sqm')['calc_value'] ?? 0;
        $societyTot = $this->getCalc('society_total_bua_sqm')['calc_value'] ?? 0;
        $mhadaBal   = $this->getCalc('mhada_balance_bua_sqm')['calc_value'] ?? 0;
        $funPct     = $this->num('apply_fungible_pct', 35);

        // Rehab fungible is FREE (33(5) 5(b)); balance/incentive fungible chargeable
        $rehabFungible = $rehabBua * ($funPct / 100.0);
        $chargeable    = $societyTot - $rehabBua;  // society side minus free rehab
        $chargeableFungible = $chargeable * ($funPct / 100.0);
        $mhadaFungible = $mhadaBal * ($funPct / 100.0);

        $this->emit('fungible', 'rehab_fungible_free_sqm', "Rehab Fungible @ {$funPct}% (FREE — 33(5) 5(b))",
            $rehabFungible, 'sqm',
            'rehab_bua_sqm × apply_fungible_pct / 100 — no premium under 33(5) 5(b) / Reg 31(3)',
            ['rehab_bua_sqm' => $rehabBua, 'apply_fungible_pct' => $funPct],
            '33(5) 5(b) + Reg 31(3)');

        $this->emit('fungible', 'chargeable_fungible_sqm', "Society Non-Rehab Fungible (Incentive + Balance @ {$funPct}%)",
            $chargeableFungible, 'sqm',
            '(society_total_bua_sqm − rehab_bua_sqm) × apply_fungible_pct / 100 — premium 60% ASR per Reg 31(3)',
            ['chargeable_bua' => $chargeable, 'apply_fungible_pct' => $funPct],
            'Reg 31(3)');

        $this->emit('fungible', 'mhada_fungible_sqm', "MHADA-Share Fungible @ {$funPct}%",
            $mhadaFungible, 'sqm',
            'mhada_balance_bua_sqm × apply_fungible_pct / 100 — no premium on MHADA share',
            ['mhada_balance_bua' => $mhadaBal, 'apply_fungible_pct' => $funPct],
            '33(5) note + Reg 31(3)');

        // Grand totals
        $societyGrandTotal = $societyTot + $rehabFungible + $chargeableFungible;
        $mhadaGrandTotal   = $mhadaBal + $mhadaFungible;
        $fullBua = $societyGrandTotal + $mhadaGrandTotal;
        $fullFsi = $netPlot > 0 ? $fullBua / $netPlot : 0;

        $this->emit('totals', 'society_grand_total_sqm', 'Society Grand Total (incl. all fungible)',
            $societyGrandTotal, 'sqm',
            'society_total_bua_sqm + rehab_fungible + chargeable_fungible',
            ['society_total' => $societyTot, 'rehab_fungible' => $rehabFungible, 'chargeable_fungible' => $chargeableFungible],
            '33(5) + Reg 31(3)');

        $this->emit('totals', 'mhada_grand_total_sqm', 'MHADA Grand Total (incl. fungible)',
            $mhadaGrandTotal, 'sqm',
            'mhada_balance_bua_sqm + mhada_fungible',
            ['mhada_balance' => $mhadaBal, 'mhada_fungible' => $mhadaFungible],
            '33(5) + Reg 31(3)');

        $this->emit('totals', 'full_scheme_bua_sqm', 'Full Scheme BUA (Society + MHADA, incl. fungible)',
            $fullBua, 'sqm',
            'society_grand_total_sqm + mhada_grand_total_sqm',
            ['society_grand_total' => $societyGrandTotal, 'mhada_grand_total' => $mhadaGrandTotal],
            '33(5) + Reg 31(3)');

        $this->emit('totals', 'full_scheme_fsi', 'Full Scheme FSI (cap ~5.40)',
            $fullFsi, 'ratio',
            'full_scheme_bua_sqm / net_plot_area_sqm',
            ['full_scheme_bua_sqm' => $fullBua, 'net_plot_area_sqm' => $netPlot],
            '33(5) fsi_caps + Reg 31(3) cap 5.40');
    }

    private function num(string $key, ?float $default = null): float {
        if (!array_key_exists($key, $this->inputs) || $this->inputs[$key] === '' || $this->inputs[$key] === null) {
            if ($default === null) throw new RuntimeException("Required input missing: $key");
            return $default;
        }
        return (float)$this->inputs[$key];
    }

    private function emit(string $group, string $key, string $label, float $value, string $unit,
                          string $formula, array $inputs, string $regRef): void {
        $this->calcs[] = [
            'calc_group' => $group,
            'calc_key' => $key,
            'calc_label' => $label,
            'calc_value' => round($value, 4),
            'unit' => $unit,
            'formula' => $formula,
            'formula_inputs' => json_encode($inputs, JSON_UNESCAPED_SLASHES),
            'regulation_ref' => $regRef,
            'rule_id' => $this->ruleId,
            'display_order' => ++$this->displayOrderSeq,
        ];
    }

    private function getCalc(string $key): ?array {
        foreach ($this->calcs as $c) if ($c['calc_key'] === $key) return $c;
        return null;
    }
}
