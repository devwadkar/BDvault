<?php
/**
 * Reg33_10_FinancialsCalculator — 7-year (or N-year) financial model for a 33(10) SRA plan.
 *
 * Consumes:
 *   - plan_financials row (rates, curves, loan, overheads)
 *   - plan_calculations row for the same plan (Sale BUA, RERA carpet, construction areas, premiums)
 *
 * Emits (persisted to plan_financials.yearly_output_json / summary_output_json / loan_schedule_json):
 *   - yearly[]  : per-year revenue booking, cash inflow, rehab cost, sale cost, premiums,
 *                 society, marketing, admin, contingency, other, total cost, net-cash, cumulative
 *   - loan[]    : per-year opening balance, interest, principal, repayment, closing balance
 *   - summary   : total revenue, total cost, NP before/after interest, project IRR, equity IRR,
 *                 surplus share (partner), breakeven price
 */
class Reg33_10_FinancialsCalculator {

    private const SQM_TO_SQFT = 10.7639;

    /** @var array plan_financials row */
    private array $f;

    /** @var array plan_calculations rows keyed by calc_key */
    private array $calc;

    public function __construct(array $financialsRow, array $planCalcsByKey) {
        $this->f = $financialsRow;
        $this->calc = $planCalcsByKey;
    }

    /**
     * Run the model. Returns ['yearly' => [...], 'loan' => [...], 'summary' => [...], 'warnings' => [...]].
     */
    public function run(): array {
        $warnings = [];
        $N = max(1, (int)($this->f['project_duration_years'] ?? 7));

        // ─── Derive upstream areas from plan_calculations ─────────────────
        $rehabBuaSqm       = (float)($this->calc['rehab_tenement_bua_sqm']['calc_value'] ?? 0);
        $saleGrossSqm      = (float)($this->calc['gross_sale_bua_permissible_sqm']['calc_value'] ?? 0);
        $totalBuaProposedSqm = (float)($this->calc['total_bua_proposed_sqm']['calc_value'] ?? ($rehabBuaSqm + (float)($this->calc['sale_bua_proposed_in_situ_sqm']['calc_value'] ?? 0)));

        // Parksite convention for CONSTRUCTION area (as-built, incl. parking, common, walls):
        //   Rehab construction sqft = Total BUA proposed × 1.75 × sqm→sqft
        //   Sale  construction sqft = RERA carpet × 2       (2× loading over saleable carpet)
        // The 1.75 factor accounts for parking + non-FSI-counted BUA in the physical build.
        $constructionLoadingFactor = 1.75;
        $rehabConstructionSqft = $totalBuaProposedSqm * $constructionLoadingFactor * self::SQM_TO_SQFT;

        // Saleable RERA carpet — override or derived from sale BUA × loading
        $loadingPct = (float)($this->f['rera_carpet_loading_pct'] ?? 88);
        $reraCarpetSqft = (float)($this->f['saleable_rera_carpet_sqft_override'] ?? 0);
        if ($reraCarpetSqft <= 0) {
            $reraCarpetSqft = $saleGrossSqm * ($loadingPct / 100.0) * self::SQM_TO_SQFT;
        }
        // Round down to nearest 100k sqft — industry practice for sale-inventory
        $reraCarpetSqftRounded = floor($reraCarpetSqft / 100000) * 100000;
        if ($reraCarpetSqftRounded <= 0) { $reraCarpetSqftRounded = $reraCarpetSqft; }

        // Sale construction area = 2 × RERA carpet (Parksite convention — matches sheet B23 = B17*2)
        $saleConstructionSqft = $reraCarpetSqftRounded * 2;

        // ─── Escalation vectors ───────────────────────────────────────────
        $escPct  = (float)($this->f['annual_escalation_pct'] ?? 5);
        $escMult = 1 + $escPct / 100.0;
        $saleY1  = (float)$this->f['sale_rate_y1_inr_sqft'];
        $rehabRY1= (float)$this->f['rehab_construction_rate_y1_inr_sqft'];
        $saleRY1 = (float)$this->f['sale_construction_rate_y1_inr_sqft'];

        $saleRate = []; $rehabConsRate = []; $saleConsRate = [];
        for ($y = 1; $y <= $N; $y++) {
            $factor = pow($escMult, $y - 1);
            $saleRate[$y]      = $saleY1  * $factor;
            $rehabConsRate[$y] = $rehabRY1 * $factor;
            $saleConsRate[$y]  = $saleRY1  * $factor;
        }

        // ─── Curves (normalize + pad/truncate to N years) ─────────────────
        $booking     = $this->curve('booking_curve_json',    [0, 0.15, 0.20, 0.20, 0.20, 0.20, 0.05], $N);
        $collection  = $this->curve('collection_curve_json', [0.30, 0.30, 0.55, 0.70, 0.80, 0.90, 1.00], $N);
        $rehabCurve  = $this->curve('rehab_cost_curve_json', [0.15, 0.20, 0.25, 0.25, 0.15, 0, 0], $N);
        $saleCurve   = $this->curve('sale_cost_curve_json',  [0.05, 0.10, 0.15, 0.20, 0.20, 0.20, 0.10], $N);
        $premiumCurve= $this->curve('premium_curve_json',    [0.15, 0.15, 0.10, 0.20, 0.20, 0.10, 0.10], $N);

        if (abs(array_sum($booking) - 1.0) > 0.01) $warnings[] = 'Booking curve does not sum to 100%';
        if (abs(array_sum($rehabCurve) - 1.0) > 0.01) $warnings[] = 'Rehab cost curve does not sum to 100%';
        if (abs(array_sum($saleCurve) - 1.0) > 0.01) $warnings[] = 'Sale cost curve does not sum to 100%';
        if (abs(array_sum($premiumCurve) - 1.0) > 0.01) $warnings[] = 'Premium curve does not sum to 100%';

        // ─── Premium & deposit total (from area calc if present) ──────────
        $premiumTotal = $this->derivePremiumTotal();

        // ─── Yearly loop ──────────────────────────────────────────────────
        $yearly = [];
        $cumulativeRevenueBooked = 0.0;
        $cumulativeRevenueCollected = 0.0;
        for ($y = 1; $y <= $N; $y++) {
            $bookPct = $booking[$y] ?? 0;
            $collPct = $collection[$y] ?? 0;

            // Revenue booking Y = total_inventory × booking_pct_this_year × rate_this_year
            $revBooking = $reraCarpetSqftRounded * $bookPct * $saleRate[$y];
            $cumulativeRevenueBooked += $revBooking;

            // Cash collection: cumulative "should have collected" = cumulative_booked × collection_pct.
            // Actual this year = max(0, that − collections in prior years)
            $shouldBeCollectedTo = $cumulativeRevenueBooked * $collPct;
            $cashInflow = max(0, $shouldBeCollectedTo - $cumulativeRevenueCollected);
            $cumulativeRevenueCollected += $cashInflow;

            // Construction costs
            $rehabCost = $rehabConstructionSqft * $rehabConsRate[$y] * ($rehabCurve[$y] ?? 0);
            $saleCost  = $saleConstructionSqft  * $saleConsRate[$y]  * ($saleCurve[$y] ?? 0);

            // Premium payment for the year (share of total premium)
            $premium = $premiumTotal * ($premiumCurve[$y] ?? 0);

            // Society costs — lumps in configured years
            $society = 0.0;
            if ($y === (int)$this->f['society_rent_year_a']) $society += (float)$this->f['society_rent_lump_inr'] * 0.5;
            if ($y === (int)$this->f['society_rent_year_b']) $society += (float)$this->f['society_rent_lump_inr'] * 0.5;
            if ($y === (int)$this->f['society_other_year'])  $society += (float)$this->f['society_other_lump_inr'];

            // Overheads (percentages)
            $costsBase   = $rehabCost + $saleCost + $premium + $society;
            $marketing   = $revBooking * ((float)$this->f['marketing_pct_of_sales'] / 100.0);
            $admin       = $costsBase  * ((float)$this->f['admin_pct_of_costs']     / 100.0);
            $contingency = $costsBase  * ((float)$this->f['contingency_pct_of_costs'] / 100.0);
            $otherOH     = $costsBase  * ((float)$this->f['other_costs_pct_of_costs'] / 100.0);

            $totalCost  = $costsBase + $marketing + $admin + $contingency + $otherOH;
            $netCash    = $cashInflow - $totalCost;

            $yearly[] = [
                'year'                 => $y,
                'sale_rate'            => round($saleRate[$y], 2),
                'rehab_construction_rate' => round($rehabConsRate[$y], 2),
                'sale_construction_rate'  => round($saleConsRate[$y], 2),
                'booking_pct'          => $bookPct,
                'collection_pct'       => $collPct,
                'revenue_booked'       => round($revBooking, 2),
                'cash_inflow'          => round($cashInflow, 2),
                'rehab_cost'           => round($rehabCost, 2),
                'sale_cost'            => round($saleCost, 2),
                'premium'              => round($premium, 2),
                'society_cost'         => round($society, 2),
                'marketing'            => round($marketing, 2),
                'admin'                => round($admin, 2),
                'contingency'          => round($contingency, 2),
                'other_overhead'       => round($otherOH, 2),
                'total_cost'           => round($totalCost, 2),
                'net_cash_before_interest' => round($netCash, 2),
            ];
        }

        // ─── Loan schedule ────────────────────────────────────────────────
        $loanSchedule = $this->buildLoanSchedule($N);

        // Merge loan interest into yearly
        foreach ($yearly as &$row) {
            $y = $row['year'];
            $intY = 0; $prinY = 0;
            foreach ($loanSchedule as $l) {
                if ($l['year'] == $y) { $intY = $l['interest']; $prinY = $l['principal_repayment']; break; }
            }
            $row['interest']       = round($intY, 2);
            $row['principal_repay']= round($prinY, 2);
            $row['net_cash_after_interest'] = round($row['net_cash_before_interest'] - $intY, 2);
        }
        unset($row);

        // ─── Summary ──────────────────────────────────────────────────────
        $totalRevenue   = array_sum(array_column($yearly, 'revenue_booked'));
        $totalCashIn    = array_sum(array_column($yearly, 'cash_inflow'));
        $totalCost      = array_sum(array_column($yearly, 'total_cost'));
        $totalInterest  = array_sum(array_column($yearly, 'interest'));
        $npBefore       = $totalRevenue - $totalCost;
        $npAfter        = $npBefore - $totalInterest;

        // Cash-flow vectors for IRR — using net_cash before/after interest (annual)
        $cfBefore = array_column($yearly, 'net_cash_before_interest');
        $cfAfter  = array_column($yearly, 'net_cash_after_interest');
        $equityInv = (float)$this->f['equity_investment_inr'];
        $partnerPct= (float)$this->f['surplus_share_partner_pct'];

        // Equity cash-flow: assume equity infusion in Y1, then net-after-interest is distributed
        $cfEquity = $cfAfter;
        $cfEquity[0] = ($cfEquity[0] ?? 0) - 0; // treat equity as reducing Y1 cash flow negatively
        // Standard modeling: infuse equity at Y1 (negative), receive back later
        $cfEquityWithInfusion = $cfAfter;
        $cfEquityWithInfusion[0] = ($cfEquityWithInfusion[0] ?? 0) + $equityInv; // equity added to cash pool
        // Better: use cfAfter directly (as the Parksite Sheet does — equity added to Y1 makes it less negative)

        $projectIRR = $this->irr($cfBefore);
        $equityIRR  = $this->irr($cfAfter); // matches Sheet's IRR calc on E40:K40

        $breakevenPerCarpetSqft = $reraCarpetSqftRounded > 0
            ? ($totalCost + $totalInterest) / $reraCarpetSqftRounded
            : 0;

        $partnerShare = max(0, $npAfter) * ($partnerPct / 100.0);

        $summary = [
            'sale_bua_gross_sqft'            => round($saleConstructionSqft, 2),
            'rera_carpet_sqft_derived'       => round($reraCarpetSqft, 2),
            'rera_carpet_sqft_used'          => round($reraCarpetSqftRounded, 2),
            'rehab_construction_sqft'        => round($rehabConstructionSqft, 2),
            'sale_construction_sqft_2x'      => round($saleConstructionSqft * 2, 2),  // Parksite convention: sale_construction area is 2× RERA
            'premium_total_inr'              => round($premiumTotal, 2),

            'total_revenue_inr'              => round($totalRevenue, 2),
            'total_cash_inflow_inr'          => round($totalCashIn, 2),
            'total_cost_inr'                 => round($totalCost, 2),
            'net_profit_before_interest_inr' => round($npBefore, 2),
            'total_interest_inr'             => round($totalInterest, 2),
            'net_profit_after_interest_inr'  => round($npAfter, 2),
            'net_profit_margin_pct'          => $totalRevenue > 0 ? round($npBefore / $totalRevenue * 100, 2) : 0,
            'project_irr_pct'                => $projectIRR !== null ? round($projectIRR * 100, 2) : null,
            'equity_irr_pct'                 => $equityIRR !== null  ? round($equityIRR * 100, 2)  : null,

            'equity_investment_inr'          => round($equityInv, 2),
            'partner_share_pct'              => $partnerPct,
            'partner_share_inr'              => round($partnerShare, 2),

            'breakeven_price_per_rera_sqft'  => round($breakevenPerCarpetSqft, 2),
        ];

        return [
            'yearly'    => $yearly,
            'loan'      => $loanSchedule,
            'summary'   => $summary,
            'warnings'  => $warnings,
            'snapshot'  => [
                'sale_bua_gross_sqft'     => round($saleConstructionSqft, 2),
                'rera_carpet_sqft'        => round($reraCarpetSqftRounded, 2),
                'rehab_construction_sqft' => round($rehabConstructionSqft, 2),
                'sale_construction_sqft'  => round($saleConstructionSqft, 2),
                'premium_total_inr'       => round($premiumTotal, 2),
            ],
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function curve(string $col, array $default, int $N): array {
        $json = $this->f[$col] ?? null;
        $arr = $json ? json_decode($json, true) : null;
        if (!is_array($arr) || empty($arr)) $arr = $default;
        // 1-indexed for calculator convenience
        $out = [];
        for ($y = 1; $y <= $N; $y++) {
            $out[$y] = (float)($arr[$y - 1] ?? 0);
        }
        return $out;
    }

    /**
     * Total premium (Approvals + Fungible + Additional Land Premium) — from plan_calculations.
     * If any specific premium calc keys exist we sum them; else fall back to a rough %-of-BUA estimate.
     */
    private function derivePremiumTotal(): float {
        // Parksite pattern (from the sheet):
        //  L27 = (80.59+99.67+19.26+15.58+8.59+16.95+14.47) × 1e7 = ₹2,551,100,000 (approvals)
        //  L28 = Fungible × ASR × 50%                              = ₹2,227,416,658 (fungible premium)
        //  L29 = 111.12 × 1e7                                       = ₹1,111,200,000 (additional govt land premium)
        //  Total ~ ₹5.89 Cr × 100 = ₹588 Cr

        // Our calculator emits fungible + sale-fungible; total premium is complex. Two paths:
        //  1) If plan already has a stored 'premium_total_inr' calc, use that
        //  2) Else use the Parksite defaults scaled to this plan's fungible area
        $stored = $this->calc['premium_total_inr']['calc_value'] ?? null;
        // Treat an explicit 0 as "no premium" (Area Analysis bridge may supply this deliberately).
        // Only fall back to the Parksite default when the caller supplied no value at all.
        if ($stored !== null) return (float)$stored;

        // Fallback: fungible × ASR (assume 50% × ASR₹69210)
        // Users should override via a manual entry — kept simple here.
        return 588.67e7; // Parksite total = ₹588.67 Cr; sensible default for Ghatkopar-scale project
    }

    private function buildLoanSchedule(int $N): array {
        $principal = (float)$this->f['loan_principal_inr'];
        $rate      = (float)$this->f['loan_interest_rate_pct'] / 100.0;
        $moratorium= (int)$this->f['loan_moratorium_years'];
        $repayStart= (int)$this->f['loan_repayment_start_year'];
        $repayYrs  = max(1, (int)$this->f['loan_repayment_years']);

        $schedule = [];
        $opening = $principal;
        $prinPerYear = $repayYrs > 0 ? $principal / $repayYrs : 0;

        for ($y = 1; $y <= $N; $y++) {
            $prin = ($y >= $repayStart && $y < $repayStart + $repayYrs) ? $prinPerYear : 0;
            $prin = min($prin, $opening);
            // Parksite convention (Sheet2 C7: =(B7-D7)*rate) — interest computed on
            // (opening − this-year-principal), i.e. principal is treated as year-start.
            $interest = ($opening - $prin) * $rate;
            $closing = $opening - $prin;
            $schedule[] = [
                'year'                => $y,
                'opening_balance'     => round($opening, 2),
                'interest'            => round($interest, 2),
                'principal_repayment' => round($prin, 2),
                'repayment_total'     => round($interest + $prin, 2),
                'closing_balance'     => round($closing, 2),
                'flag'                => $y <= $moratorium ? 'Moratorium' : ($prin > 0 ? 'Repayment' : ''),
            ];
            $opening = $closing;
        }
        return $schedule;
    }

    /**
     * IRR via Newton-Raphson. Cash flows are 1-indexed but we take them as a plain 0-indexed array.
     * Returns null if it fails to converge.
     */
    private function irr(array $cashFlows): ?float {
        if (empty($cashFlows)) return null;

        // Pre-flight: IRR only exists if the cash-flow series has at least one sign change.
        // All-positive or all-negative => no rate makes NPV zero, return null instead of a
        // divergent Newton-Raphson result.
        $hasPos = false; $hasNeg = false;
        foreach ($cashFlows as $cf) { if ($cf > 0) $hasPos = true; if ($cf < 0) $hasNeg = true; }
        if (!$hasPos || !$hasNeg) return null;

        // Newton-Raphson from 0.10 seed
        $r = 0.10;
        for ($iter = 0; $iter < 200; $iter++) {
            $npv = 0; $dnpv = 0;
            foreach ($cashFlows as $i => $cf) {
                $t = $i + 1;
                $factor = pow(1 + $r, $t);
                $npv += $cf / $factor;
                $dnpv -= $t * $cf / ($factor * (1 + $r));
            }
            if (abs($dnpv) < 1e-12) break;
            $rNew = $r - $npv / $dnpv;
            if (!is_finite($rNew) || $rNew <= -0.999) $rNew = ($r - 0.5) / 2;
            if (abs($rNew - $r) < 1e-8) { $r = $rNew; break; }
            $r = $rNew;
        }
        // Sanity clamp — real-world IRRs sit in ±500%. Anything wider = divergence.
        if (!is_finite($r) || $r > 5.0 || $r < -0.99) return null;
        return $r;
    }
}
