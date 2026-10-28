<?php
/**
 * Reg33_10_Calculator — Deterministic engine for DCPR 33(10) SRA feasibility.
 *
 * Reads:
 *   - plan_inputs (user + derived + defaults from schema)
 *   - dcpr_rules row for regulation 33(10) (loaded JSON via DCPRRuleLoader)
 *
 * Emits an array of calculation rows, each carrying:
 *   - calc_group, calc_key, calc_label, calc_value, unit
 *   - formula (human readable), formula_inputs (JSON of what was used)
 *   - regulation_ref (DCPR clause), rule_id (dcpr_rules.id)
 *   - display_order
 *
 * Verified against the Ghatkopar 33(10) feasibility document (CTS 1(PT), Ghatkopar W).
 * All numbers reproduce within 0.1%.
 *
 * Extending to a new scheme (33(5)/33(7)/etc.): copy this class, rewrite the compute()
 * method for that scheme's rule tree. The controller is scheme-agnostic.
 */

require_once __DIR__ . '/../DCPRRuleLoader.php';
require_once __DIR__ . '/../RulesRegistry.php';

class Reg33_10_Calculator {

    private const SQM_TO_SQFT = 10.7639;
    private const SCOPE = '33(10)';

    /** @var array plan input map: input_key => value (typed) */
    private array $inputs;

    /** @var array decoded content of dcpr_rules row for 33(10) */
    private array $rule;

    /** @var int|null id of the 33(10) row in dcpr_rules — cited on every calc */
    private ?int $ruleId;

    /** @var array accumulator of emitted calculations */
    private array $calcs = [];

    private int $displayOrderSeq = 0;

    public function __construct(array $inputs) {
        $this->inputs = $inputs;

        $ruleRow = DCPRRuleLoader::fetchByKey('33(10)');
        if (!$ruleRow) {
            throw new RuntimeException('DCPR rule 33(10) not loaded. Run /?page=dcpr_reload first.');
        }
        $this->rule = $ruleRow['content'] ?? [];

        // fetch the numeric id too — the loader fetchByKey doesn't include it, so pull from db.
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM dcpr_rules WHERE regulation_key = ? ORDER BY version DESC LIMIT 1");
        $stmt->bind_param('s', $key);
        $key = '33(10)';
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $this->ruleId = $res ? (int)$res['id'] : null;
    }

    /**
     * Run the full 33(10) calculation. Returns array of calc rows.
     */
    public function compute(): array {
        $this->calcs = [];
        $this->displayOrderSeq = 0;

        $this->computeLandBreakdown();
        $this->computeRehabComponent();
        $this->computeSaleRatio();
        $this->computeSaleComponent();
        $this->computeTotalsAndFSI();
        $this->computeInSituAndTDR();
        $this->computeFungibleAndGrossSale();

        return $this->calcs;
    }

    // ─── Land Breakdown ────────────────────────────────────────────────────

    private function computeLandBreakdown(): void {
        $gross = $this->num('gross_plot_area_sqm');

        $dpPrimary   = $this->num('dp_road_setback_primary_sqm', 0);
        $dpSecondary = $this->num('dp_road_setback_secondary_sqm', 0);
        $nalla       = $this->num('nalla_setback_sqm', 0);
        $resOther    = $this->num('reservation_area_other_sqm', 0);
        $amenity     = $this->num('amenity_plot_sqm', 0);
        $other       = $this->num('other_deduction_sqm', 0);

        // ROS is emitted for the physical breakdown but is NOT part of the FSI deduction total.
        $rosDisplay  = $this->num('reservation_area_ros_sqm', 0);

        $this->emit('land', 'gross_plot_area_sqm', 'Gross Plot Area', $gross, 'sqm',
            'User input — total plot boundary area',
            ['gross_plot_area_sqm' => $gross],
            'Reg 30(A)(1)');

        $this->emit('land', 'gross_plot_area_sqft', 'Gross Plot Area', $gross * self::SQM_TO_SQFT, 'sqft',
            'gross_plot_area_sqm × 10.7639', ['gross_plot_area_sqm' => $gross], 'Unit conversion');

        $this->emitDeduction('dp_road_setback_primary_sqm', 'Primary DP Road Setback Deduction', $dpPrimary, 'Reg 30(A)(2)(a)', $gross);
        $this->emitDeduction('dp_road_setback_secondary_sqm', 'Secondary DP Road Setback Deduction', $dpSecondary, 'Reg 30(A)(2)(a)', $gross);
        $this->emitDeduction('nalla_setback_sqm', 'Nalla / Storm Water Setback', $nalla, 'Reg 30(A)(2)(a)', $gross);
        $this->emitDeduction('reservation_area_other_sqm', 'DP Reservation Deduction (non-ROS)', $resOther, 'Reg 30(A)(2)(a)', $gross);
        $this->emitDeduction('amenity_plot_sqm', 'Amenity Plot Deduction', $amenity, 'Reg 14(A)', $gross);
        $this->emitDeduction('other_deduction_sqm', 'Other Deductions', $other, 'Reg 30(A)(2)', $gross);

        // ROS emitted as informational only — labeled explicitly
        if ($rosDisplay > 0) {
            $this->emit('land', 'ros_physical_set_aside_sqm', 'ROS / Green Belt (physical set-aside, NOT deducted from FSI base)',
                $rosDisplay, 'sqm',
                'Not deducted per Reg 30(A) Note (1) — ROS under Reg 27 is exempt from FSI/BUA deductions',
                ['reservation_area_ros_sqm' => $rosDisplay],
                'Reg 30(A) Note (1)');
        }

        $totalDeduction = $dpPrimary + $dpSecondary + $nalla + $resOther + $amenity + $other;
        $netPlot = $gross - $totalDeduction;

        $this->emit('land', 'total_deductions_sqm', 'Total Deductions from Gross Plot', $totalDeduction, 'sqm',
            'sum of DP-road + nalla + DP-reservation-other + amenity + other (ROS excluded per Reg 30(A) Note 1)',
            [
                'dp_road_setback_primary_sqm'   => $dpPrimary,
                'dp_road_setback_secondary_sqm' => $dpSecondary,
                'nalla_setback_sqm'             => $nalla,
                'reservation_area_other_sqm'    => $resOther,
                'amenity_plot_sqm'              => $amenity,
                'other_deduction_sqm'           => $other,
            ],
            'Reg 30(A)(2)');

        $this->emit('land', 'net_plot_area_sqm', 'Net Plot Area (for FSI Computation)', $netPlot, 'sqm',
            'gross_plot_area_sqm − total_deductions_sqm',
            ['gross_plot_area_sqm' => $gross, 'total_deductions_sqm' => $totalDeduction],
            'Reg 30(A)(2)');

        $this->emit('land', 'net_plot_area_sqft', 'Net Plot Area', $netPlot * self::SQM_TO_SQFT, 'sqft',
            'net_plot_area_sqm × 10.7639', ['net_plot_area_sqm' => $netPlot], 'Unit conversion');

        $this->emit('land', 'net_plot_area_ha', 'Net Plot Area (hectares — used for large-scheme incentive tier)',
            $netPlot / 10000.0, 'ha',
            'net_plot_area_sqm / 10000',
            ['net_plot_area_sqm' => $netPlot],
            '33(10) VIII 3.6');

        // Plot Area for FSI computation — user override or default to gross.
        // Ghatkopar-style projects leave this blank (all deductions counted-in-FSI implicitly).
        // Malad-E-style projects enter the explicit plot-for-fsi from their feasibility (gross − nalla − amenity + add-backs).
        $userPlotForFsi = $this->num('plot_area_for_fsi_sqm', 0);
        $plotForFsi = $userPlotForFsi > 0 ? $userPlotForFsi : $gross;
        $formula = $userPlotForFsi > 0
            ? 'User input — net plot + reservations counted in FSI (add-backs)'
            : 'Defaults to gross_plot_area_sqm (no explicit plot-for-FSI provided)';
        $this->emit('land', 'plot_area_for_fsi_sqm', 'Plot Area for FSI Computation', $plotForFsi, 'sqm',
            $formula,
            ['plot_area_for_fsi_sqm_input' => $userPlotForFsi, 'gross_plot_area_sqm' => $gross],
            'Reg 30(A)(2)');
    }

    private function emitDeduction(string $key, string $label, float $value, string $ref, float $gross): void {
        if ($value <= 0) return;
        $pct = $gross > 0 ? ($value / $gross) * 100 : 0;
        $this->emit('land', $key, $label, $value, 'sqm',
            "User input — " . number_format($pct, 2) . "% of gross plot",
            [$key => $value, 'gross_plot_area_sqm' => $gross],
            $ref);
    }

    // ─── Rehab Component ───────────────────────────────────────────────────

    private function computeRehabComponent(): void {
        $eligible  = $this->num('eligible_tenements');
        $welfare   = $this->num('welfare_tenements', 0);
        $carpet    = $this->num('carpet_area_per_tenement_sqm', RulesRegistry::get('carpet_area_min_sqm', self::SCOPE));
        $loading   = $this->num('rehab_loading_factor',         RulesRegistry::get('rehab_loading_factor', self::SCOPE));
        $amenityUser      = $this->num('amenity_common_area_sqm', 0);
        $religious        = $this->num('religious_structures_sqm', 0);
        $socialInfra      = $this->num('social_infra_sqm', 0);

        $totalRehabTenements = $eligible + $welfare;

        $this->emit('rehab', 'total_rehab_tenements', 'Total Rehab Tenements (Residential + Welfare/Balwadi/PAP)',
            $totalRehabTenements, 'tenements',
            'eligible_tenements + welfare_tenements',
            ['eligible_tenements' => $eligible, 'welfare_tenements' => $welfare],
            '33(10) VI 1.5');

        $rehabCarpetTotal = $totalRehabTenements * $carpet;
        $this->emit('rehab', 'rehab_carpet_total_sqm', 'Total Rehab Carpet Area',
            $rehabCarpetTotal, 'sqm',
            'total_rehab_tenements × carpet_area_per_tenement_sqm',
            ['total_rehab_tenements' => $totalRehabTenements, 'carpet_area_per_tenement_sqm' => $carpet],
            '33(10) VI 1.1 (25 sqm carpet minimum)');

        $rehabTenementBUA = $rehabCarpetTotal * $loading;
        $this->emit('rehab', 'rehab_tenement_bua_sqm', 'Rehab BUA (Tenement Buildings, excl. amenity block)',
            $rehabTenementBUA, 'sqm',
            'rehab_carpet_total_sqm × rehab_loading_factor  (walls + staircase + lift + common passages)',
            ['rehab_carpet_total_sqm' => $rehabCarpetTotal, 'rehab_loading_factor' => $loading],
            '33(10) VIII 3.2');

        $rehabTenementBuaSqft = $rehabTenementBUA * self::SQM_TO_SQFT;
        $this->emit('rehab', 'rehab_tenement_bua_sqft', 'Rehab BUA', $rehabTenementBuaSqft, 'sqft',
            'rehab_tenement_bua_sqm × 10.7639', ['rehab_tenement_bua_sqm' => $rehabTenementBUA], 'Unit conversion');

        // Amenity block — if the user leaves the explicit amenity input blank, fall back to
        // the regulatory default (currently 38% of Rehab Tenement BUA per registry). This
        // matches the Step 3 preview and the classic feasibility Row 10, and makes sale
        // ratio + total BUA + FSI downstream computations correct without a display kludge.
        $amenityPctDefault = RulesRegistry::get('amenity_pct_default_of_rehab_bua', self::SCOPE);
        $amenityFromDefault = $rehabTenementBUA * ($amenityPctDefault / 100.0);
        $useDefault = ($amenityUser <= 0);
        $amenityCommon = $useDefault ? $amenityFromDefault : $amenityUser;

        $rehabAmenityBlock = $amenityCommon + $religious + $socialInfra;
        $this->emit('rehab', 'rehab_amenity_block_sqm', 'Amenity Block (Balwadi + WC + Society Office + Common Passage + Religious + Social)',
            $rehabAmenityBlock, 'sqm',
            $useDefault
                ? "rehab_tenement_bua_sqm × {$amenityPctDefault}% + religious_structures_sqm + social_infra_sqm  (default when explicit amenity input blank)"
                : 'amenity_common_area_sqm + religious_structures_sqm + social_infra_sqm',
            [
                'amenity_common_area_sqm_input'  => $amenityUser,
                'amenity_common_area_sqm_used'   => $amenityCommon,
                'amenity_default_source'         => $useDefault ? "{$amenityPctDefault}% of rehab_tenement_bua" : 'user_input',
                'religious_structures_sqm'       => $religious,
                'social_infra_sqm'               => $socialInfra,
            ],
            '33(10) VIII 3.2 + 3.10');

        $rehabComponent = $rehabTenementBUA + $rehabAmenityBlock;
        $this->emit('rehab', 'rehab_component_sqm', 'Rehabilitation Component (Denominator for Sale Ratio)',
            $rehabComponent, 'sqm',
            'rehab_tenement_bua_sqm + rehab_amenity_block_sqm',
            ['rehab_tenement_bua_sqm' => $rehabTenementBUA, 'rehab_amenity_block_sqm' => $rehabAmenityBlock],
            '33(10) VIII 3.2');
    }

    // ─── Rehab-to-Sale Ratio (base × large-scheme incentive) ───────────────

    private function computeSaleRatio(): void {
        $zone = strtolower(trim((string)($this->inputs['development_zone'] ?? 'Suburbs & Extended Suburbs')));

        // Base ratio lookup from rule JSON (reg_33_10_sra.json → rehab_to_sale_ratio_base)
        $base = $this->rule['rehab_to_sale_ratio_base'] ?? [];
        $baseRatio = 1.00;
        $baseSource = 'Suburbs & Extended Suburbs';
        $isDifficult = false;
        if (str_contains($zone, 'island')) {
            $baseRatio = $base['island_city']['ratio'] ?? 0.75;
            $baseSource = 'Island City';
        } elseif (str_contains($zone, 'difficult')) {
            // Difficult Area = 1.30 (industry rounding of DCPR 1.333 per Reg 33(10) VIII 3.5)
            $baseRatio = $base['difficult_areas']['industry_rounded_ratio'] ?? ($base['difficult_areas']['ratio'] ?? 1.30);
            $baseSource = 'Difficult Area';
            $isDifficult = true;
        } else {
            $baseRatio = $base['suburbs_and_extended_suburbs']['ratio'] ?? 1.00;
            $baseSource = 'Suburbs & Extended Suburbs';
        }

        $this->emit('sale', 'sale_ratio_base', "Base Rehab-to-Sale Ratio ($baseSource)", $baseRatio, 'ratio',
            "Looked up from Reg 33(10) rule base: rehab_to_sale_ratio_base.$baseSource",
            ['development_zone' => $zone],
            $isDifficult ? '33(10) VIII 3.5 (industry-rounded 1.30)' : '33(10) VIII 3.3–3.5');

        // Incentive tier lookup — SKIPPED for Difficult Area (does not stack per rule note)
        $netHaCalc = $this->getCalc('net_plot_area_ha');
        $schemeAreaHa = $netHaCalc['calc_value'] ?? 0;

        $incentivePct = 0;
        $tierLabel = 'not applicable (Difficult Area — no tier stacking)';
        if (!$isDifficult) {
            $tiers = $this->rule['large_scheme_free_sale_incentive']['tiers'] ?? [];
            $tierLabel = 'below smallest tier';
            foreach ($tiers as $t) {
                $min = (float)($t['area_min_ha'] ?? 0);
                $max = $t['area_max_ha'] === null ? INF : (float)$t['area_max_ha'];
                if ($schemeAreaHa >= $min && $schemeAreaHa < $max) {
                    $incentivePct = (float)($t['additional_free_sale_pct'] ?? 0);
                    $tierLabel = "{$min}–" . ($t['area_max_ha'] === null ? '∞' : $t['area_max_ha']) . " ha";
                    break;
                }
            }
        }
        $this->emit('sale', 'sale_ratio_incentive_pct', "Large-Scheme Incentive (tier: $tierLabel)",
            $incentivePct, 'percent',
            $isDifficult
                ? "Skipped — Difficult Area ratio already accounts for large-scheme bonus (per rule JSON note)"
                : "Scheme area {$schemeAreaHa} ha falls in tier '$tierLabel' → $incentivePct%",
            ['scheme_area_ha' => $schemeAreaHa, 'is_difficult_area' => $isDifficult],
            '33(10) VIII 3.6');

        $effectiveRatio = $baseRatio * (1 + $incentivePct / 100);
        $this->emit('sale', 'sale_ratio_effective', 'Effective Rehab-to-Sale Ratio',
            $effectiveRatio, 'ratio',
            'sale_ratio_base × (1 + sale_ratio_incentive_pct / 100)',
            ['sale_ratio_base' => $baseRatio, 'sale_ratio_incentive_pct' => $incentivePct],
            '33(10) VIII 3.3–3.6');
    }

    // ─── Sale Component ────────────────────────────────────────────────────

    private function computeSaleComponent(): void {
        $rehabComp = $this->getCalc('rehab_component_sqm')['calc_value'] ?? 0;
        $ratio     = $this->getCalc('sale_ratio_effective')['calc_value'] ?? 1.0;

        $saleBase = $rehabComp * $ratio;
        $this->emit('sale', 'sale_component_base_sqm', 'Sale Component (Base = Rehab × Ratio, excl. Reservation Incentive)',
            $saleBase, 'sqm',
            'rehab_component_sqm × sale_ratio_effective',
            ['rehab_component_sqm' => $rehabComp, 'sale_ratio_effective' => $ratio],
            '33(10) VIII 3.3–3.6');

        // Buildable reservation sale incentive (Malad-E-style: municipal school handover)
        $resSaleIncentive = $this->num('buildable_reservation_sale_incentive_sqm', 0);
        if ($resSaleIncentive > 0) {
            $this->emit('sale', 'buildable_reservation_sale_incentive_sqm',
                'Sale Incentive in lieu of Buildable Reservation Handover',
                $resSaleIncentive, 'sqm',
                'User input — additional sale BUA earned for handing over a buildable reservation (e.g. Municipal School RE 1.1). Malad-E reference: 4,230 sqm school × 0.452 factor = 1,912.15 sqm.',
                ['buildable_reservation_sale_incentive_sqm' => $resSaleIncentive],
                '33(10) + Reg 33(7) 7(f) analog');
        }

        $saleComp = $saleBase + $resSaleIncentive;
        $this->emit('sale', 'sale_component_sqm', 'Sale Component (Total, excl. Fungible)',
            $saleComp, 'sqm',
            'sale_component_base_sqm + buildable_reservation_sale_incentive_sqm',
            ['sale_component_base_sqm' => $saleBase, 'buildable_reservation_sale_incentive_sqm' => $resSaleIncentive],
            '33(10) VIII 3.3–3.6');

        $this->emit('sale', 'sale_component_sqft', 'Sale Component (Total)',
            $saleComp * self::SQM_TO_SQFT, 'sqft',
            'sale_component_sqm × 10.7639', ['sale_component_sqm' => $saleComp], 'Unit conversion');
    }

    // ─── Totals & FSI Sanctioned ───────────────────────────────────────────

    private function computeTotalsAndFSI(): void {
        $rehabBua   = $this->getCalc('rehab_tenement_bua_sqm')['calc_value'] ?? 0;
        $saleComp   = $this->getCalc('sale_component_sqm')['calc_value'] ?? 0;
        $plotForFsi = $this->getCalc('plot_area_for_fsi_sqm')['calc_value'] ?? $this->num('gross_plot_area_sqm');

        // Total BUA Approved = rehab tenement BUA + sale component (incl. reservation sale incentive)
        // (Amenity block is used for the sale ratio but is not itself part of FSI-countable BUA per Reg 31(1))
        $totalBuaApproved = $rehabBua + $saleComp;
        $this->emit('totals', 'total_bua_approved_sqm', 'Total BUA Approved for Scheme',
            $totalBuaApproved, 'sqm',
            'rehab_tenement_bua_sqm + sale_component_sqm  (amenity block Reg-31(1)-exempt, not added)',
            ['rehab_tenement_bua_sqm' => $rehabBua, 'sale_component_sqm' => $saleComp],
            '33(10) + Reg 31(1)');

        $this->emit('totals', 'total_bua_approved_sqft', 'Total BUA Approved',
            $totalBuaApproved * self::SQM_TO_SQFT, 'sqft',
            'total_bua_approved_sqm × 10.7639', ['total_bua_approved_sqm' => $totalBuaApproved], 'Unit conversion');

        $fsiSanctioned = $plotForFsi > 0 ? $totalBuaApproved / $plotForFsi : 0;
        $this->emit('fsi', 'fsi_sanctioned', 'FSI Sanctioned for Scheme',
            $fsiSanctioned, 'ratio',
            'total_bua_approved_sqm / plot_area_for_fsi_sqm',
            ['total_bua_approved_sqm' => $totalBuaApproved, 'plot_area_for_fsi_sqm' => $plotForFsi],
            '33(10) VIII 3.7 (may exceed 4.0; excess consumable as TDR)');
    }

    // ─── In-Situ vs TDR Split ──────────────────────────────────────────────

    private function computeInSituAndTDR(): void {
        $rehabBua        = $this->getCalc('rehab_tenement_bua_sqm')['calc_value'] ?? 0;
        $saleComp        = $this->getCalc('sale_component_sqm')['calc_value'] ?? 0;
        $gross           = $this->num('gross_plot_area_sqm');
        $inSituFsiCap    = $this->num('in_situ_fsi_cap', RulesRegistry::get('in_situ_fsi_cap', self::SCOPE));
        $saleInSituInput = $this->num('sale_bua_proposed_in_situ_sqm', 0);

        $inSituBuaCap = $inSituFsiCap * $gross;
        $this->emit('fsi', 'in_situ_bua_cap_sqm', 'Max BUA Consumable In-Situ (@ FSI cap)',
            $inSituBuaCap, 'sqm',
            'in_situ_fsi_cap × gross_plot_area_sqm',
            ['in_situ_fsi_cap' => $inSituFsiCap, 'gross_plot_area_sqm' => $gross],
            '33(10) VIII 3.8');

        $saleInSituMax = max(0, $inSituBuaCap - $rehabBua);
        $this->emit('sale', 'sale_bua_in_situ_max_sqm', 'Max Sale BUA Buildable In-Situ',
            $saleInSituMax, 'sqm',
            'in_situ_bua_cap_sqm − rehab_tenement_bua_sqm',
            ['in_situ_bua_cap_sqm' => $inSituBuaCap, 'rehab_tenement_bua_sqm' => $rehabBua],
            '33(10) VIII 3.8');

        // Actual proposed = min(user input, in-situ max). If user leaves blank (0), we don't clip.
        $saleProposed = $saleInSituInput > 0 ? min($saleInSituInput, $saleInSituMax) : 0;
        $this->emit('sale', 'sale_bua_proposed_in_situ_sqm', 'Sale BUA Proposed In-Situ',
            $saleProposed, 'sqm',
            $saleInSituInput > 0
                ? 'min(user_input, sale_bua_in_situ_max_sqm)'
                : 'user has not proposed an in-situ sale BUA yet',
            ['sale_bua_proposed_in_situ_sqm_input' => $saleInSituInput, 'sale_bua_in_situ_max_sqm' => $saleInSituMax],
            '33(10) VIII 3.8');

        $totalBuaProposed = $rehabBua + $saleProposed;
        $this->emit('totals', 'total_bua_proposed_sqm', 'Total BUA Proposed (Rehab + Sale In-Situ)',
            $totalBuaProposed, 'sqm',
            'rehab_tenement_bua_sqm + sale_bua_proposed_in_situ_sqm',
            ['rehab_tenement_bua_sqm' => $rehabBua, 'sale_bua_proposed_in_situ_sqm' => $saleProposed],
            '33(10)');

        $plotForFsi = $this->getCalc('plot_area_for_fsi_sqm')['calc_value'] ?? $gross;
        $fsiConsumed = $plotForFsi > 0 ? $totalBuaProposed / $plotForFsi : 0;
        $this->emit('fsi', 'fsi_consumed_in_situ', 'FSI Consumed In-Situ',
            $fsiConsumed, 'ratio',
            'total_bua_proposed_sqm / plot_area_for_fsi_sqm',
            ['total_bua_proposed_sqm' => $totalBuaProposed, 'plot_area_for_fsi_sqm' => $plotForFsi],
            '33(10) VIII 3.8');

        $balanceSaleTDR = max(0, $saleComp - $saleProposed);
        $this->emit('tdr', 'balance_sale_bua_tdr_sqm', 'Balance Sale BUA (Available as TDR)',
            $balanceSaleTDR, 'sqm',
            'sale_component_sqm − sale_bua_proposed_in_situ_sqm',
            ['sale_component_sqm' => $saleComp, 'sale_bua_proposed_in_situ_sqm' => $saleProposed],
            '33(10) VIII 3.8 + Reg 32');
    }

    // ─── Fungible & Gross Sale Permissible ─────────────────────────────────

    private function computeFungibleAndGrossSale(): void {
        $saleComp     = $this->getCalc('sale_component_sqm')['calc_value'] ?? 0;
        $rehabBua     = $this->getCalc('rehab_tenement_bua_sqm')['calc_value'] ?? 0;
        $fungiblePct  = $this->num('apply_fungible_pct', RulesRegistry::get('fungible_pct_residential', 'global'));

        $saleFungible = $saleComp * $fungiblePct / 100.0;
        $this->emit('fungible', 'sale_fungible_sqm', "Sale Fungible @ {$fungiblePct}%",
            $saleFungible, 'sqm',
            "sale_component_sqm × apply_fungible_pct / 100  (Residential fungible max 35% per Reg 31(3))",
            ['sale_component_sqm' => $saleComp, 'apply_fungible_pct' => $fungiblePct],
            'Reg 31(3)');

        $rehabFungible = $rehabBua * $fungiblePct / 100.0;
        $this->emit('fungible', 'rehab_fungible_sqm', "Rehab Fungible @ {$fungiblePct}% (free of premium)",
            $rehabFungible, 'sqm',
            'rehab_tenement_bua_sqm × apply_fungible_pct / 100  (Rehab fungible under 33(10) is FREE per Reg 31(3))',
            ['rehab_tenement_bua_sqm' => $rehabBua, 'apply_fungible_pct' => $fungiblePct],
            'Reg 31(3) — 33(10) rehab clause');

        $grossSalePermissible = $saleComp + $saleFungible;
        $this->emit('sale', 'gross_sale_bua_permissible_sqm', 'Gross Sale BUA Permissible (incl. Fungible)',
            $grossSalePermissible, 'sqm',
            'sale_component_sqm + sale_fungible_sqm',
            ['sale_component_sqm' => $saleComp, 'sale_fungible_sqm' => $saleFungible],
            'Reg 31(3) + 33(10)');

        $this->emit('sale', 'gross_sale_bua_permissible_sqft', 'Gross Sale BUA Permissible',
            $grossSalePermissible * self::SQM_TO_SQFT, 'sqft',
            'gross_sale_bua_permissible_sqm × 10.7639',
            ['gross_sale_bua_permissible_sqm' => $grossSalePermissible],
            'Unit conversion');
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    private function num(string $key, ?float $default = null): float {
        if (!array_key_exists($key, $this->inputs) || $this->inputs[$key] === '' || $this->inputs[$key] === null) {
            if ($default === null) {
                throw new RuntimeException("Required input missing: $key");
            }
            return $default;
        }
        return (float)$this->inputs[$key];
    }

    private function emit(string $group, string $key, string $label, float $value, string $unit,
                          string $formula, array $inputs, string $regRef): void {
        $this->calcs[] = [
            'calc_group'     => $group,
            'calc_key'       => $key,
            'calc_label'     => $label,
            'calc_value'     => round($value, 4),
            'unit'           => $unit,
            'formula'        => $formula,
            'formula_inputs' => json_encode($inputs, JSON_UNESCAPED_SLASHES),
            'regulation_ref' => $regRef,
            'rule_id'        => $this->ruleId,
            'display_order'  => ++$this->displayOrderSeq,
        ];
    }

    private function getCalc(string $key): ?array {
        foreach ($this->calcs as $c) {
            if ($c['calc_key'] === $key) return $c;
        }
        return null;
    }
}
