<?php
/**
 * Reg33_20B_Calculator — DCPR 33(20)(B) Affordable Housing on private land.
 *
 * Verified against:
 *   - Malad Dindoshi CTS 82B (20,234 sqm, 300 tenants, 18.30m road, RR ₹49,020/sqm)
 *   - Sea Pebbles CHS Bandra (1,218 sqm, 13 flats, 13.40m road, RR ₹2,82,910/sqm)
 *   - JAI GIRNAR (cross-scheme reference)
 *
 * Model — composite:
 *   Base (Reg 30(A)) = Zonal + Gov FSI (0.5) + TDR (0.9 split slum/general)
 *   + 33(7)(B) incentive: MAX(existing_bua × 15%, tenants × 10 sqm) — reduces general TDR purchase
 *   + Road setback advantage (once)
 *   + 33(20)(B) additional: (target_cap − base_fsi) split owner/AH 50/50
 *   + 35% fungible on top
 *   Cap: 4.00 base + 35% fungible = 5.40 total FSI
 */

require_once __DIR__ . '/../DCPRRuleLoader.php';
require_once __DIR__ . '/../RulesRegistry.php';

class Reg33_20B_Calculator {

    private const SQM_TO_SQFT = 10.7639;
    private const SCOPE = '33(20)(B)';

    private array $inputs;
    private array $rule;
    private ?int $ruleId;
    private array $calcs = [];
    private int $displayOrderSeq = 0;

    public function __construct(array $inputs) {
        $this->inputs = $inputs;
        $ruleRow = DCPRRuleLoader::fetchByKey('33(20)(B)');
        if (!$ruleRow) throw new RuntimeException('DCPR rule 33(20)(B) not loaded. Run /?page=dcpr_reload first.');
        $this->rule = $ruleRow['content'] ?? [];
        $db = getDB();
        $key = '33(20)(B)';
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
        $this->computeBaseStack();
        $this->computeIncentives();
        $this->computeAdditional33_20B();
        $this->computeFungibleAndTotals();
        return $this->calcs;
    }

    private function computeLand(): void {
        $gross    = $this->num('gross_plot_area_sqm');
        $setback  = $this->num('setback_area_sqm', 0);
        $aosDed   = $this->num('aos_deduction_sqm', 0);
        $netPlot  = max(0, $gross - $setback - $aosDed);

        $this->emit('land', 'gross_plot_area_sqm', 'Gross Plot Area', $gross, 'sqm',
            'User input — plot per PRC',
            ['gross_plot_area_sqm' => $gross], 'Reg 30(A)(1)');

        if ($setback > 0) $this->emit('land', 'setback_area_sqm', 'Setback Deduction', $setback, 'sqm',
            'User input — DP road / RL setback', ['setback_area_sqm' => $setback], 'Reg 30(A)(2)(a)');

        if ($aosDed > 0) $this->emit('land', 'aos_deduction_sqm', 'Proportionate AOS Deduction',
            $aosDed, 'sqm', 'User input — Reg 14(A) for plots > 4000 sqm', ['aos_deduction_sqm' => $aosDed], 'Reg 14(A)');

        $this->emit('land', 'net_plot_area_sqm', 'Net Plot Area (for FSI Computation)',
            $netPlot, 'sqm',
            'gross_plot_area_sqm − setback − aos_deduction',
            ['gross_plot_area_sqm' => $gross, 'setback_area_sqm' => $setback, 'aos_deduction_sqm' => $aosDed],
            'Reg 30(A)(2)');
    }

    private function computeBaseStack(): void {
        $netPlot = $this->getCalc('net_plot_area_sqm')['calc_value'] ?? 0;
        $zonalFsi = $this->num('zonal_fsi',                RulesRegistry::get('zonal_fsi_suburbs_20b', self::SCOPE));
        $govFsi   = $this->num('govt_fsi_on_premium',      RulesRegistry::get('gov_fsi',               self::SCOPE));
        $tdrFsi   = $this->num('tdr_permissible_fsi',      RulesRegistry::get('tdr_fsi_20b',           self::SCOPE));
        $slumPct  = $this->num('slum_tdr_share_of_tdr_pct', 20);

        $zonalBua = $netPlot * $zonalFsi;
        $govBua   = $netPlot * $govFsi;
        $tdrBua   = $netPlot * $tdrFsi;
        $slumTdrBua = $tdrBua * ($slumPct / 100.0);
        $generalTdrBua = $tdrBua - $slumTdrBua;

        $this->emit('base', 'zonal_bua_sqm', 'Zonal BUA (Reg 30)',
            $zonalBua, 'sqm',
            'net_plot_area_sqm × zonal_fsi',
            ['net_plot_area_sqm' => $netPlot, 'zonal_fsi' => $zonalFsi],
            'Reg 30 Table 12');

        $this->emit('base', 'gov_fsi_bua_sqm', "Additional Gov FSI BUA @ {$govFsi} (on premium)",
            $govBua, 'sqm',
            'net_plot_area_sqm × govt_fsi_on_premium',
            ['net_plot_area_sqm' => $netPlot, 'govt_fsi_on_premium' => $govFsi],
            'Reg 30(A)(1)(A) — 60% ASR premium');

        $this->emit('base', 'tdr_bua_sqm', "Additional TDR BUA @ {$tdrFsi}",
            $tdrBua, 'sqm',
            'net_plot_area_sqm × tdr_permissible_fsi',
            ['net_plot_area_sqm' => $netPlot, 'tdr_permissible_fsi' => $tdrFsi],
            'Reg 30 Table 12 + Reg 32');

        $this->emit('base', 'slum_tdr_bua_sqm', "Slum TDR @ {$slumPct}% of Total TDR",
            $slumTdrBua, 'sqm',
            'tdr_bua_sqm × slum_tdr_share_of_tdr_pct / 100',
            ['tdr_bua_sqm' => $tdrBua, 'slum_tdr_share_of_tdr_pct' => $slumPct],
            'Reg 30(A)(6) proviso');

        $this->emit('base', 'general_tdr_bua_sqm', 'General TDR',
            $generalTdrBua, 'sqm',
            'tdr_bua_sqm − slum_tdr_bua_sqm',
            ['tdr_bua_sqm' => $tdrBua, 'slum_tdr_bua_sqm' => $slumTdrBua],
            'Reg 32');

        $baseTotalBua = $zonalBua + $govBua + $tdrBua;
        $baseFsi = $netPlot > 0 ? ($baseTotalBua / $netPlot) : 0;

        $this->emit('base', 'base_stack_bua_sqm', 'Total Base BUA under Reg 30(A)',
            $baseTotalBua, 'sqm',
            'zonal_bua_sqm + gov_fsi_bua_sqm + tdr_bua_sqm',
            ['zonal_bua_sqm' => $zonalBua, 'gov_fsi_bua_sqm' => $govBua, 'tdr_bua_sqm' => $tdrBua],
            'Reg 30(A)');

        $this->emit('base', 'base_stack_fsi', 'Total Base FSI under Reg 30(A)',
            $baseFsi, 'ratio',
            'zonal_fsi + govt_fsi_on_premium + tdr_permissible_fsi',
            ['zonal_fsi' => $zonalFsi, 'govt_fsi_on_premium' => $govFsi, 'tdr_permissible_fsi' => $tdrFsi],
            'Reg 30(A)');
    }

    private function computeIncentives(): void {
        $existingBua = $this->num('existing_bua_sqm', 0);
        $numTenants  = $this->num('num_existing_tenants', 0);
        $apply       = strtolower(trim((string)($this->inputs['apply_33_7B_incentive'] ?? 'yes'))) === 'yes';
        $setback     = $this->num('road_setback_advantage_sqm', 0);

        // 33(7)(B) incentive = MAX(existing × 15%, tenants × 10)
        $incentive15pct = $existingBua * 0.15;
        $incentive10sqm = $numTenants * 10.0;
        $incentive      = $apply ? max($incentive15pct, $incentive10sqm) : 0;

        $this->emit('incentives', 'incentive_15pct_existing_bua_sqm',
            "33(7)(B) — 15% × existing BUA",
            $incentive15pct, 'sqm',
            'existing_bua_sqm × 15%',
            ['existing_bua_sqm' => $existingBua],
            'Reg 33(7)(B)');

        $this->emit('incentives', 'incentive_10sqm_per_tenant_sqm',
            "33(7)(B) — 10 sqm × tenants",
            $incentive10sqm, 'sqm',
            'num_existing_tenants × 10 sqm',
            ['num_existing_tenants' => $numTenants],
            'Reg 33(7)(B)');

        $this->emit('incentives', 'incentive_33_7B_sqm',
            $apply ? '33(7)(B) Incentive (MAX rule)' : '33(7)(B) Incentive (not applied)',
            $incentive, 'sqm',
            $apply
                ? 'MAX(incentive_15pct_existing_bua_sqm, incentive_10sqm_per_tenant_sqm)'
                : 'User opted out',
            ['apply_33_7B_incentive' => $apply, 'incentive_15pct_existing_bua_sqm' => $incentive15pct, 'incentive_10sqm_per_tenant_sqm' => $incentive10sqm],
            'Reg 33(7)(B)');

        if ($setback > 0) {
            $this->emit('incentives', 'road_setback_advantage_sqm', 'Road Setback Advantage (once, merged with DP road)',
                $setback, 'sqm',
                'User input',
                ['road_setback_advantage_sqm' => $setback],
                'Reg 33(20)(B) proviso');
        }
    }

    private function computeAdditional33_20B(): void {
        $netPlot   = $this->getCalc('net_plot_area_sqm')['calc_value'] ?? 0;
        $baseFsi   = $this->getCalc('base_stack_fsi')['calc_value'] ?? 0;
        $targetCap = $this->num('target_total_fsi_cap', RulesRegistry::get('target_fsi_cap_20b',  self::SCOPE));
        $ownerPct  = $this->num('owner_ah_split_pct',    RulesRegistry::get('owner_share_pct_20b', self::SCOPE));

        $addFsi = max(0, $targetCap - $baseFsi);
        $addBua = $netPlot * $addFsi;

        $this->emit('additional', 'additional_33_20b_fsi', "33(20)(B) Additional FSI (Cap {$targetCap} − Base)",
            $addFsi, 'ratio',
            'target_total_fsi_cap − base_stack_fsi',
            ['target_total_fsi_cap' => $targetCap, 'base_stack_fsi' => $baseFsi],
            'Reg 33(20)(B)');

        $this->emit('additional', 'additional_33_20b_bua_sqm', '33(20)(B) Additional BUA',
            $addBua, 'sqm',
            'net_plot_area_sqm × additional_33_20b_fsi',
            ['net_plot_area_sqm' => $netPlot, 'additional_33_20b_fsi' => $addFsi],
            'Reg 33(20)(B)');

        $ownerBua = $addBua * ($ownerPct / 100.0);
        $ahBua    = $addBua - $ownerBua;

        $this->emit('additional', 'owner_share_bua_sqm', "Owner (Sale) Share @ {$ownerPct}%",
            $ownerBua, 'sqm',
            'additional_33_20b_bua_sqm × owner_ah_split_pct / 100',
            ['additional_33_20b_bua_sqm' => $addBua, 'owner_ah_split_pct' => $ownerPct],
            'Reg 33(20)(B) — 50/50 split');

        $this->emit('additional', 'ah_share_bua_sqm', 'AH Share (handed over to MCGM)',
            $ahBua, 'sqm',
            'additional_33_20b_bua_sqm − owner_share_bua_sqm',
            ['additional_33_20b_bua_sqm' => $addBua, 'owner_share_bua_sqm' => $ownerBua],
            'Reg 33(20)(B) — AH handover to MCGM');
    }

    private function computeFungibleAndTotals(): void {
        $netPlot   = $this->getCalc('net_plot_area_sqm')['calc_value'] ?? 0;
        $baseBua   = $this->getCalc('base_stack_bua_sqm')['calc_value'] ?? 0;
        $addBua    = $this->getCalc('additional_33_20b_bua_sqm')['calc_value'] ?? 0;
        $ownerBua  = $this->getCalc('owner_share_bua_sqm')['calc_value'] ?? 0;
        $ahBua     = $this->getCalc('ah_share_bua_sqm')['calc_value'] ?? 0;
        $funPct    = $this->num('apply_fungible_pct', RulesRegistry::get('fungible_pct_residential', 'global'));

        // Total base FSI BUA (owner side gets Base + Owner Additional; AH is separate)
        $ownerTotalBaseBua = $baseBua + $ownerBua;
        $ownerFungible = $ownerTotalBaseBua * ($funPct / 100.0);
        $ownerGrandTotal = $ownerTotalBaseBua + $ownerFungible;

        $ahFungible = $ahBua * ($funPct / 100.0);
        $ahGrandTotal = $ahBua + $ahFungible;

        $this->emit('totals', 'owner_base_bua_sqm', 'Owner (Sale) Base BUA (Reg 30(A) + 33(20)(B) owner share)',
            $ownerTotalBaseBua, 'sqm',
            'base_stack_bua_sqm + owner_share_bua_sqm',
            ['base_stack_bua_sqm' => $baseBua, 'owner_share_bua_sqm' => $ownerBua],
            '30(A) + 33(20)(B)');

        $this->emit('totals', 'owner_fungible_sqm', "Owner Fungible @ {$funPct}%",
            $ownerFungible, 'sqm',
            'owner_base_bua_sqm × apply_fungible_pct / 100',
            ['owner_base_bua_sqm' => $ownerTotalBaseBua, 'apply_fungible_pct' => $funPct],
            'Reg 31(3)');

        $this->emit('totals', 'owner_total_sqm', 'Owner Total BUA (incl. fungible)',
            $ownerGrandTotal, 'sqm',
            'owner_base_bua_sqm + owner_fungible_sqm',
            ['owner_base_bua_sqm' => $ownerTotalBaseBua, 'owner_fungible_sqm' => $ownerFungible],
            '30(A) + 33(20)(B) + Reg 31(3)');

        $this->emit('totals', 'ah_fungible_sqm', "AH Fungible @ {$funPct}%",
            $ahFungible, 'sqm',
            'ah_share_bua_sqm × apply_fungible_pct / 100',
            ['ah_share_bua_sqm' => $ahBua, 'apply_fungible_pct' => $funPct],
            'Reg 31(3)');

        $this->emit('totals', 'ah_total_sqm', 'AH Total BUA (incl. fungible, handed over to MCGM)',
            $ahGrandTotal, 'sqm',
            'ah_share_bua_sqm + ah_fungible_sqm',
            ['ah_share_bua_sqm' => $ahBua, 'ah_fungible_sqm' => $ahFungible],
            'Reg 33(20)(B) + Reg 31(3)');

        $fullBua = $ownerGrandTotal + $ahGrandTotal;
        $this->emit('totals', 'full_scheme_bua_sqm', 'Full Scheme BUA (Owner + AH, incl. fungible)',
            $fullBua, 'sqm',
            'owner_total_sqm + ah_total_sqm',
            ['owner_total_sqm' => $ownerGrandTotal, 'ah_total_sqm' => $ahGrandTotal],
            '30(A) + 33(20)(B) + Reg 31(3)');

        $fullFsi = $netPlot > 0 ? ($fullBua / $netPlot) : 0;
        $this->emit('totals', 'full_scheme_fsi', 'Full Scheme FSI (cap 5.40 = 4.0 + 35% fungible)',
            $fullFsi, 'ratio',
            'full_scheme_bua_sqm / net_plot_area_sqm',
            ['full_scheme_bua_sqm' => $fullBua, 'net_plot_area_sqm' => $netPlot],
            '33(20)(B) + Reg 31(3) cap 5.40');
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
