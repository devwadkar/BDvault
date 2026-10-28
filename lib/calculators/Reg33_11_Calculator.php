<?php
/**
 * Reg33_11_Calculator — DCPR 33(11) Permanent Transit Camp / Rental Housing SRA scheme.
 *
 * Verified against:
 *   - Bhagwan Apartment, Worli Hill Estate (1575 sqm, road 24m, 16 flats)
 *   - Abdul Court + Kachwalla Building, Mahim (1578 + 960 sqm, road 18.4m, 20 flats)
 *   - JAI GIRNAR CHS, Tardeo (3346 sqm, road 36.6m, 74 flats)
 *
 * Model:
 *   Total FSI = Zonal FSI (1.33 Island / 1.00 Suburbs) + Additional FSI (road-width based, cap 2.67/3.00)
 *   SRA-PTC share    = Additional_BUA × 63%  (handed over to Planning Authority)
 *   Sale+Society share = Zonal_BUA + Additional_BUA × 37%
 *   35% fungible on each side (residential)
 *   Total permissible scheme FSI ≤ 5.40 (4.0 + 35% fungible)
 */

require_once __DIR__ . '/../DCPRRuleLoader.php';
require_once __DIR__ . '/../RulesRegistry.php';

class Reg33_11_Calculator {

    private const SQM_TO_SQFT = 10.7639;
    private const SCOPE = '33(11)';

    private array $inputs;
    private array $rule;
    private ?int $ruleId;
    private array $calcs = [];
    private int $displayOrderSeq = 0;

    public function __construct(array $inputs) {
        $this->inputs = $inputs;

        $ruleRow = DCPRRuleLoader::fetchByKey('33(11)');
        if (!$ruleRow) throw new RuntimeException('DCPR rule 33(11) not loaded. Run /?page=dcpr_reload first.');
        $this->rule = $ruleRow['content'] ?? [];

        $db = getDB();
        $key = '33(11)';
        $stmt = $db->prepare("SELECT id FROM dcpr_rules WHERE regulation_key = ? ORDER BY version DESC LIMIT 1");
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $this->ruleId = $res ? (int)$res['id'] : null;
    }

    public function compute(): array {
        $this->calcs = [];
        $this->displayOrderSeq = 0;

        $this->computeLandBreakdown();
        $this->computeFSISplit();
        $this->computeRehabSociety();
        $this->computeSaleComponent();
        $this->computeSRAPTC();
        $this->computeTotalsAndCheck();
        $this->computePtcPurchaseCost();

        return $this->calcs;
    }

    // ─── Land ──────────────────────────────────────────────────────────

    private function computeLandBreakdown(): void {
        $gross    = $this->num('gross_plot_area_sqm');
        $setback  = $this->num('setback_area_sqm', 0);
        $amenity  = $this->num('amenity_deduction_sqm', 0);
        $netPlot  = max(0, $gross - $setback - $amenity);

        $this->emit('land', 'gross_plot_area_sqm', 'Gross Plot Area', $gross, 'sqm',
            'User input — plot area per PRC',
            ['gross_plot_area_sqm' => $gross],
            'Reg 30(A)(1)');

        if ($setback > 0) {
            $this->emit('land', 'setback_area_sqm', 'Setback / Encroachment Deduction',
                $setback, 'sqm', 'User input', ['setback_area_sqm' => $setback], 'Reg 30(A)(2)(a)');
        }
        if ($amenity > 0) {
            $this->emit('land', 'amenity_deduction_sqm', 'Amenity Deduction (Reg 14A)',
                $amenity, 'sqm', 'User input', ['amenity_deduction_sqm' => $amenity], 'Reg 14(A)');
        }

        $this->emit('land', 'net_plot_area_sqm', 'Net Plot Area (for FSI Computation)',
            $netPlot, 'sqm',
            'gross_plot_area_sqm − setback − amenity',
            ['gross_plot_area_sqm' => $gross, 'setback_area_sqm' => $setback, 'amenity_deduction_sqm' => $amenity],
            'Reg 30(A)(2)');

        $this->emit('land', 'net_plot_area_sqft', 'Net Plot Area',
            $netPlot * self::SQM_TO_SQFT, 'sqft',
            'net_plot_area_sqm × 10.7639',
            ['net_plot_area_sqm' => $netPlot], 'Unit conversion');
    }

    // ─── FSI Split (the heart of 33(11)) ────────────────────────────────

    private function computeFSISplit(): void {
        $netPlot  = $this->getCalc('net_plot_area_sqm')['calc_value'] ?? 0;
        // Defaults resolved through RulesRegistry — AreaCalcEngine already passes explicit
        // values based on Island/Suburbs, but if this calculator is invoked outside the
        // engine (unit tests, ad-hoc scripts) the registry still supplies the right numbers.
        $zonalFsi = $this->num('zonal_fsi',                  RulesRegistry::get('zonal_fsi_suburbs',      self::SCOPE));
        $addFsi   = $this->num('additional_fsi',             RulesRegistry::get('additional_fsi_suburbs', self::SCOPE));
        $sraPct   = $this->num('sra_additional_share_pct',   RulesRegistry::get('sra_ptc_share_pct',      self::SCOPE));
        $funPct   = $this->num('apply_fungible_pct',         RulesRegistry::get('fungible_pct_residential', 'global'));

        $zonalBua = $netPlot * $zonalFsi;
        $this->emit('fsi', 'zonal_bua_sqm', 'Zonal BUA',
            $zonalBua, 'sqm',
            'net_plot_area_sqm × zonal_fsi',
            ['net_plot_area_sqm' => $netPlot, 'zonal_fsi' => $zonalFsi],
            '33(11) Table A + Reg 30');

        $addBua = $netPlot * $addFsi;
        $this->emit('fsi', 'additional_bua_sqm', 'Additional BUA (road-width based)',
            $addBua, 'sqm',
            'net_plot_area_sqm × additional_fsi',
            ['net_plot_area_sqm' => $netPlot, 'additional_fsi' => $addFsi],
            '33(11) Table A');

        $totalFsi = $zonalFsi + $addFsi;
        $totalBaseBua = $zonalBua + $addBua;
        $this->emit('fsi', 'total_base_fsi', 'Total Base FSI (excl. fungible)',
            $totalFsi, 'ratio',
            'zonal_fsi + additional_fsi',
            ['zonal_fsi' => $zonalFsi, 'additional_fsi' => $addFsi],
            '33(11) Table A');

        $this->emit('fsi', 'total_base_bua_sqm', 'Total Base BUA (excl. fungible)',
            $totalBaseBua, 'sqm',
            'zonal_bua_sqm + additional_bua_sqm',
            ['zonal_bua_sqm' => $zonalBua, 'additional_bua_sqm' => $addBua],
            '33(11) Table A');

        // Split
        $sraShareBua = $addBua * ($sraPct / 100.0);
        $saleSocietyShareBua = $zonalBua + $addBua * ((100 - $sraPct) / 100.0);

        $this->emit('sra_ptc', 'sra_ptc_share_bua_sqm', "SRA-PTC Share of Additional FSI ({$sraPct}%)",
            $sraShareBua, 'sqm',
            "additional_bua_sqm × ({$sraPct} / 100) — SRA gets 63% of Additional FSI as PTC/Rental (industry 63/37 split)",
            ['additional_bua_sqm' => $addBua, 'sra_additional_share_pct' => $sraPct],
            '33(11) A + industry split');

        $this->emit('sale', 'sale_society_share_bua_sqm', "Sale + Society Share (Zonal + " . (100 - $sraPct) . "% Additional)",
            $saleSocietyShareBua, 'sqm',
            'zonal_bua_sqm + additional_bua_sqm × (37 / 100)',
            ['zonal_bua_sqm' => $zonalBua, 'additional_bua_sqm' => $addBua, 'complement_pct' => (100 - $sraPct)],
            '33(11) A + industry split');

        // Fungible on each
        $sraFungible = $sraShareBua * ($funPct / 100.0);
        $this->emit('sra_ptc', 'sra_ptc_fungible_sqm', "SRA-PTC Fungible @ {$funPct}%",
            $sraFungible, 'sqm',
            'sra_ptc_share_bua_sqm × apply_fungible_pct / 100 (Res fungible on rehab is free per Reg 31(3))',
            ['sra_ptc_share_bua_sqm' => $sraShareBua, 'apply_fungible_pct' => $funPct],
            'Reg 31(3)');

        $saleFungible = $saleSocietyShareBua * ($funPct / 100.0);
        $this->emit('sale', 'sale_society_fungible_sqm', "Sale + Society Fungible @ {$funPct}%",
            $saleFungible, 'sqm',
            'sale_society_share_bua_sqm × apply_fungible_pct / 100',
            ['sale_society_share_bua_sqm' => $saleSocietyShareBua, 'apply_fungible_pct' => $funPct],
            'Reg 31(3)');

        // Totals per component
        $sraTotal = $sraShareBua + $sraFungible;
        $saleTotal = $saleSocietyShareBua + $saleFungible;
        $this->emit('sra_ptc', 'sra_ptc_total_sqm', 'SRA-PTC Total (incl. fungible)',
            $sraTotal, 'sqm',
            'sra_ptc_share_bua_sqm + sra_ptc_fungible_sqm',
            ['sra_ptc_share_bua_sqm' => $sraShareBua, 'sra_ptc_fungible_sqm' => $sraFungible],
            '33(11) + Reg 31(3)');

        $this->emit('sale', 'sale_society_total_sqm', 'Sale + Society Total (incl. fungible)',
            $saleTotal, 'sqm',
            'sale_society_share_bua_sqm + sale_society_fungible_sqm',
            ['sale_society_share_bua_sqm' => $saleSocietyShareBua, 'sale_society_fungible_sqm' => $saleFungible],
            '33(11) + Reg 31(3)');
    }

    // ─── Society Rehab Component (of Sale+Society) ──────────────────────

    private function computeRehabSociety(): void {
        $existingCarpetSqft = $this->num('existing_carpet_area_sqft', 0);
        $addMofaPct         = $this->num('additional_carpet_mofa_pct',    RulesRegistry::get('additional_carpet_mofa_pct', self::SCOPE));
        $addReraPct         = $this->num('additional_carpet_rera_pct',    RulesRegistry::get('additional_carpet_rera_pct', self::SCOPE));
        $loading            = $this->num('carpet_to_bua_loading_factor',  RulesRegistry::get('carpet_to_bua_loading_mofa', self::SCOPE));

        $existingCarpetSqm = $existingCarpetSqft / self::SQM_TO_SQFT;
        $additionalCarpetMofa = $existingCarpetSqft * ($addMofaPct / 100.0);
        $additionalCarpetRera = $existingCarpetSqft * ($addReraPct / 100.0);
        $totalSocietyCarpetMofa = $existingCarpetSqft + $additionalCarpetMofa;
        $totalSocietyCarpetRera = $existingCarpetSqft + $additionalCarpetRera;

        // Convert to BUA (via loading, then sqft→sqm)
        $totalSocietyBuaSqft = $totalSocietyCarpetRera * $loading;
        $totalSocietyBuaSqm  = $totalSocietyBuaSqft / self::SQM_TO_SQFT;

        $this->emit('rehab', 'existing_carpet_sqft', 'Existing MOFA Carpet Area',
            $existingCarpetSqft, 'sqft',
            'User input from society records',
            ['existing_carpet_area_sqft' => $existingCarpetSqft],
            '33(11) A (existing carpet baseline)');

        $this->emit('rehab', 'additional_carpet_mofa_sqft', "Additional Carpet (MOFA) @ {$addMofaPct}%",
            $additionalCarpetMofa, 'sqft',
            'existing_carpet_sqft × additional_carpet_mofa_pct / 100',
            ['existing_carpet_area_sqft' => $existingCarpetSqft, 'additional_carpet_mofa_pct' => $addMofaPct],
            '33(11) rehab entitlement (industry NKPC)');

        $this->emit('rehab', 'total_society_carpet_rera_sqft', "Total Society RERA Carpet (existing + {$addReraPct}%)",
            $totalSocietyCarpetRera, 'sqft',
            'existing_carpet_sqft × (1 + additional_carpet_rera_pct / 100)',
            ['existing_carpet_area_sqft' => $existingCarpetSqft, 'additional_carpet_rera_pct' => $addReraPct],
            '33(11) rehab entitlement');

        $this->emit('rehab', 'society_bua_sqm', 'Society Rehab BUA',
            $totalSocietyBuaSqm, 'sqm',
            'total_society_carpet_rera_sqft × loading_factor / 10.7639',
            ['total_society_carpet_rera_sqft' => $totalSocietyCarpetRera, 'carpet_to_bua_loading_factor' => $loading],
            '33(11) society share');
    }

    // ─── Sale Component (Sale+Society Total minus Society BUA) ──────────

    private function computeSaleComponent(): void {
        $saleSocietyTotal = $this->getCalc('sale_society_total_sqm')['calc_value'] ?? 0;
        $societyBua       = $this->getCalc('society_bua_sqm')['calc_value'] ?? 0;

        $saleBua = max(0, $saleSocietyTotal - $societyBua);
        $this->emit('sale', 'sale_bua_sqm', 'Balance Sale BUA (after Society rehab)',
            $saleBua, 'sqm',
            'sale_society_total_sqm − society_bua_sqm',
            ['sale_society_total_sqm' => $saleSocietyTotal, 'society_bua_sqm' => $societyBua],
            '33(11) A');

        // Also compute RERA carpet on sale
        $reraLoading = $this->num('carpet_to_bua_loading_factor', RulesRegistry::get('carpet_to_bua_loading_mofa', self::SCOPE));
        $saleReraCarpet = ($reraLoading > 0) ? ($saleBua / $reraLoading) : 0;
        $this->emit('sale', 'sale_rera_carpet_sqm', 'Sale RERA Carpet Area (approx)',
            $saleReraCarpet, 'sqm',
            'sale_bua_sqm / carpet_to_bua_loading_factor',
            ['sale_bua_sqm' => $saleBua, 'carpet_to_bua_loading_factor' => $reraLoading],
            'Industry practice');

        $this->emit('sale', 'sale_rera_carpet_sqft', 'Sale RERA Carpet Area',
            $saleReraCarpet * self::SQM_TO_SQFT, 'sqft',
            'sale_rera_carpet_sqm × 10.7639',
            ['sale_rera_carpet_sqm' => $saleReraCarpet],
            'Unit conversion');
    }

    // ─── SRA-PTC Sub-summary ────────────────────────────────────────────

    private function computeSRAPTC(): void {
        // Already emitted sra_ptc_total_sqm — nothing more to compute here for now.
        // Reserved for future: PTC tenement carpet split, welfare/PAP breakdown.
    }

    // ─── Totals & FSI Cap Check ─────────────────────────────────────────

    private function computeTotalsAndCheck(): void {
        $netPlot = $this->getCalc('net_plot_area_sqm')['calc_value'] ?? 0;
        $sraTotal  = $this->getCalc('sra_ptc_total_sqm')['calc_value']  ?? 0;
        $saleTotal = $this->getCalc('sale_society_total_sqm')['calc_value'] ?? 0;

        $fullBua = $sraTotal + $saleTotal;
        $this->emit('totals', 'full_scheme_bua_sqm', 'Full Scheme BUA (SRA-PTC + Sale+Society, incl. fungible)',
            $fullBua, 'sqm',
            'sra_ptc_total_sqm + sale_society_total_sqm',
            ['sra_ptc_total_sqm' => $sraTotal, 'sale_society_total_sqm' => $saleTotal],
            '33(11) + Reg 31(3)');

        $fullFsi = $netPlot > 0 ? ($fullBua / $netPlot) : 0;
        $this->emit('totals', 'full_scheme_fsi', 'Full Scheme FSI (incl. fungible, cap 5.40)',
            $fullFsi, 'ratio',
            'full_scheme_bua_sqm / net_plot_area_sqm',
            ['full_scheme_bua_sqm' => $fullBua, 'net_plot_area_sqm' => $netPlot],
            '33(11) A (max 4.0 + 35% fungible = 5.40)');
    }

    // ─── PTC Purchase Cost (SRA handover obligation) ───────────────────

    private function computePtcPurchaseCost(): void {
        $costPer = $this->num('ptc_tenement_purchase_cost_inr', 0);
        if ($costPer <= 0) return;

        $sraTotal = $this->getCalc('sra_ptc_total_sqm')['calc_value'] ?? 0;
        $userN    = $this->num('num_ptc_tenements_to_purchase', 0);

        // Derive PTC tenement count if not user-provided: BUA per tenement = carpet × loading, both from registry.
        $ptcCarpetSqm      = RulesRegistry::get('carpet_area_min_sqm',        '33(10)'); // shared 25 sqm minimum
        $ptcLoading        = RulesRegistry::get('carpet_to_bua_loading_mofa', self::SCOPE);
        $ptcTenementBuaSqm = $ptcCarpetSqm * $ptcLoading;
        $derivedN = $ptcTenementBuaSqm > 0 ? floor($sraTotal / $ptcTenementBuaSqm) : 0;
        $ptcN = $userN > 0 ? $userN : $derivedN;

        $this->emit('sra_ptc', 'num_ptc_tenements', 'Number of PTC Tenements (to be built)',
            $ptcN, 'count',
            $userN > 0
                ? 'User input'
                : "sra_ptc_total_sqm / ({$ptcCarpetSqm} sqm carpet × {$ptcLoading} loading)  — derived from registry",
            ['sra_ptc_total_sqm' => $sraTotal, 'user_input_n' => $userN, 'derived_n' => $derivedN,
             'ptc_carpet_sqm' => $ptcCarpetSqm, 'ptc_loading' => $ptcLoading],
            '33(11) PTC unit definition');

        $totalCost = $ptcN * $costPer;
        $this->emit('sra_ptc', 'ptc_purchase_cost_inr', 'PTC Purchase Cost (alternative to build)',
            $totalCost, 'INR',
            'num_ptc_tenements × ptc_tenement_purchase_cost_inr',
            ['num_ptc_tenements' => $ptcN, 'ptc_tenement_purchase_cost_inr' => $costPer],
            '33(11) developer option');
    }

    // ─── Helpers ───────────────────────────────────────────────────────

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
