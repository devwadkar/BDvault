<?php
// Sections 11-20 of the PDF report — pulls from $financials, $financials_result, $line_items,
// $scenarios, $sensitivity, $discrepancies, $regulatory_rules, $statement (from parent scope).

// $line_items is already grouped by loadLineItems() as [category => [rows]].
// Guard against any category being absent (older data may miss keys).
$byCat = is_array($line_items ?? null) ? $line_items : [];
$fin   = $financials ?? null;
$frr   = $financials_result['financials'] ?? null;
$hasFin = !empty($fin);
// Filter to non-empty categories so section headers only show when there's content
$byCat = array_filter($byCat, fn($rows) => is_array($rows) && count($rows) > 0);
?>

<!-- ═══════════ 11. REVENUE MODEL ═══════════ -->
<section class="pr-section" id="s11">
    <div class="pr-section-num">SECTION 11</div>
    <h2>Revenue Model</h2>
    <div class="subtitle">Sale receipts, itemised revenue, escalation and total inflow.</div>

    <?php if (!$hasFin): ?>
        <div class="pr-callout warn">Financial model not yet configured. Set base rates on the Financials form to populate this section.</div>
    <?php else: ?>
        <div class="pr-h3">Base rates</div>
        <div class="pr-kv">
            <div><span class="k">Y1 sale rate</span><span class="v">₹<?= $fmt($fin['sale_rate_inr_sqft_y1'] ?? 0, 0) ?> / sqft carpet</span></div>
            <div><span class="k">Sale escalation</span><span class="v"><?= $fmtPct($fin['sale_price_escalation_pct'] ?? 0) ?> p.a.</span></div>
            <div><span class="k">Booking start month</span><span class="v"><?= (int)($fin['booking_start_month'] ?? 0) ?></span></div>
            <div><span class="k">Booking duration</span><span class="v"><?= (int)($fin['booking_duration_months'] ?? 0) ?> months</span></div>
        </div>

        <?php if (!empty($byCat['revenue'])): ?>
            <div class="pr-h3">Revenue line items</div>
            <table class="pr-tbl">
                <thead><tr><th>Item</th><th class="num">Area/Qty</th><th class="num">Rate</th><th class="num">Amount</th></tr></thead>
                <tbody>
                    <?php $totRev = 0; foreach ($byCat['revenue'] as $li): $totRev += (float)$li['amount_inr']; ?>
                        <tr>
                            <td class="lbl"><?= e($li['label']) ?><br><span class="desc"><?= e($li['formula_type']) ?><?= $li['regulation_ref'] ? ' · ' . e($li['regulation_ref']) : '' ?></span></td>
                            <td class="num"><?= $li['area_sqft'] ? $fmt($li['area_sqft'], 0) . ' sqft' : ($li['quantity'] ? $fmt($li['quantity'], 0) . ' units' : '—') ?></td>
                            <td class="num"><?= $li['rate_inr'] ? '₹' . $fmt($li['rate_inr'], 0) . '/' . e($li['rate_unit'] ?? 'sqft') : ($li['pct'] ? $fmt($li['pct']) . '%' : '—') ?></td>
                            <td class="num"><?= $fmtCr($li['amount_inr']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="total"><td colspan="3">Revenue subtotal</td><td class="num"><?= $fmtCr($totRev) ?></td></tr></tfoot>
            </table>
        <?php endif; ?>

        <?php if ($frr): ?>
            <div class="pr-h3">Cash inflow (from model)</div>
            <div class="pr-tiles">
                <div class="pr-tile"><div class="k">Total Revenue</div><div class="v"><?= $fmtCr($frr['total_revenue_inr']) ?></div><div class="u">gross</div></div>
                <div class="pr-tile"><div class="k">Cash Inflow</div><div class="v"><?= $fmtCr($frr['total_cash_inflow_inr']) ?></div><div class="u">net of tax</div></div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<!-- ═══════════ 12. COST MODEL ═══════════ -->
<section class="pr-section" id="s12">
    <div class="pr-section-num">SECTION 12</div>
    <h2>Cost Model</h2>
    <div class="subtitle">Construction + professional + marketing + finance. Every rupee out of pocket.</div>

    <?php if (!$hasFin): ?>
        <div class="pr-callout warn">Financial model not yet configured.</div>
    <?php else: ?>
        <div class="pr-h3">Construction rates</div>
        <div class="pr-kv">
            <div><span class="k">Rehab cost Y1</span><span class="v">₹<?= $fmt($fin['rehab_cost_rate_inr_sqm_y1'] ?? 0, 0) ?> / sqm</span></div>
            <div><span class="k">Sale cost Y1</span><span class="v">₹<?= $fmt($fin['sale_cost_rate_inr_sqm_y1'] ?? 0, 0) ?> / sqm</span></div>
            <div><span class="k">Cost escalation</span><span class="v"><?= $fmtPct($fin['cost_escalation_pct'] ?? 0) ?> p.a.</span></div>
            <div><span class="k">Construction duration</span><span class="v"><?= (int)($fin['construction_duration_months'] ?? 0) ?> months</span></div>
        </div>

        <?php if (!empty($byCat['construction'])): ?>
            <div class="pr-h3">Construction line items</div>
            <table class="pr-tbl">
                <thead><tr><th>Item</th><th class="num">Area</th><th class="num">Rate</th><th class="num">Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($byCat['construction'] as $li): ?>
                        <tr>
                            <td class="lbl"><?= e($li['label']) ?></td>
                            <td class="num"><?= $li['area_sqm'] ? $fmt($li['area_sqm']) . ' sqm' : '—' ?></td>
                            <td class="num"><?= $li['rate_inr'] ? '₹' . $fmt($li['rate_inr'], 0) : '—' ?></td>
                            <td class="num"><?= $fmtCr($li['amount_inr']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($frr): ?>
            <div class="pr-h3">Cost summary</div>
            <div class="pr-tiles">
                <div class="pr-tile"><div class="k">Construction</div><div class="v"><?= $fmtCr($frr['construction_cost_total_inr']) ?></div></div>
                <div class="pr-tile"><div class="k">Professional</div><div class="v"><?= $fmtCr($frr['professional_fees_inr']) ?></div></div>
                <div class="pr-tile"><div class="k">Marketing</div><div class="v"><?= $fmtCr($frr['marketing_cost_inr']) ?></div></div>
                <div class="pr-tile"><div class="k">GST + Tax</div><div class="v"><?= $fmtCr($frr['gst_total_inr']) ?></div></div>
                <div class="pr-tile"><div class="k">Interest</div><div class="v"><?= $fmtCr($frr['interest_expense_inr']) ?></div></div>
                <div class="pr-tile"><div class="k">Other</div><div class="v"><?= $fmtCr($frr['other_cost_inr'] ?? 0) ?></div></div>
                <div class="pr-tile"><div class="k">Total Cost</div><div class="v"><?= $fmtCr($frr['total_cost_inr']) ?></div><div class="u">before interest</div></div>
                <div class="pr-tile"><div class="k">Total + Interest</div><div class="v"><?= $fmtCr(($frr['total_cost_inr'] ?? 0) + ($frr['interest_expense_inr'] ?? 0)) ?></div></div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<!-- ═══════════ 13. PREMIUM & STATUTORY CHARGES ═══════════ -->
<section class="pr-section" id="s13">
    <div class="pr-section-num">SECTION 13</div>
    <h2>Premium &amp; Statutory Charges</h2>
    <div class="subtitle">Itemised premiums with the ready-reckoner base + regulation cited.</div>

    <?php if (empty($byCat['premium'])): ?>
        <div class="pr-callout">No explicit premium line items configured. The model uses the auto-synthesised premium from the ready reckoner rate for the scheme (see Section 19).</div>
    <?php else: ?>
        <table class="pr-tbl">
            <thead><tr><th>Premium</th><th class="num">Base</th><th class="num">Rate</th><th class="num">Amount</th><th>Regulation</th></tr></thead>
            <tbody>
                <?php $totPrem = 0; foreach ($byCat['premium'] as $li): $totPrem += (float)$li['amount_inr']; ?>
                    <tr>
                        <td class="lbl"><?= e($li['label']) ?></td>
                        <td class="num"><?= $li['area_sqm'] ? $fmt($li['area_sqm']) . ' sqm' : ($li['base_ref'] ?: '—') ?></td>
                        <td class="num"><?= $li['rate_inr'] ? '₹' . $fmt($li['rate_inr'], 0) : ($li['pct'] ? $fmt($li['pct']) . '%' : '—') ?></td>
                        <td class="num"><?= $fmtCr($li['amount_inr']) ?></td>
                        <td class="ref"><?= e($li['regulation_ref']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot><tr class="total"><td colspan="3">Premium subtotal</td><td class="num"><?= $fmtCr($totPrem) ?></td><td></td></tr></tfoot>
        </table>
    <?php endif; ?>

    <?php if ($frr && !empty($frr['premium_total_inr'])): ?>
        <div class="pr-callout">
            <strong>Total premium payable</strong>
            <?= $fmtCr($frr['premium_total_inr']) ?> — feeds into the cost model above.
        </div>
    <?php endif; ?>
</section>

<!-- ═══════════ 14. SOCIETY / REHAB COSTS ═══════════ -->
<section class="pr-section" id="s14">
    <div class="pr-section-num">SECTION 14</div>
    <h2>Society / Rehab Costs</h2>
    <div class="subtitle">Corpus, hardship, temporary accommodation, brokerage — all obligations to existing tenants.</div>

    <?php if (empty($byCat['society'])): ?>
        <div class="pr-callout">
            No explicit society line items configured. Rehab construction cost is included in Section 12.
            Corpus + hardship charges are treated as external obligations.
        </div>
    <?php else: ?>
        <table class="pr-tbl">
            <thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Amount</th></tr></thead>
            <tbody>
                <?php $totSoc = 0; foreach ($byCat['society'] as $li): $totSoc += (float)$li['amount_inr']; ?>
                    <tr>
                        <td class="lbl"><?= e($li['label']) ?><?= $li['notes'] ? '<br><span class="desc">' . e($li['notes']) . '</span>' : '' ?></td>
                        <td class="num"><?= $li['quantity'] ? $fmt($li['quantity'], 0) : '—' ?></td>
                        <td class="num"><?= $li['rate_inr'] ? '₹' . $fmt($li['rate_inr'], 0) . ' / ' . e($li['rate_unit'] ?? '') : '—' ?></td>
                        <td class="num"><?= $fmtCr($li['amount_inr']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot><tr class="total"><td colspan="3">Society costs subtotal</td><td class="num"><?= $fmtCr($totSoc) ?></td></tr></tfoot>
        </table>
    <?php endif; ?>
</section>

<!-- ═══════════ 15. PROFITABILITY SUMMARY ═══════════ -->
<section class="pr-section" id="s15">
    <div class="pr-section-num">SECTION 15</div>
    <h2>Profitability Summary</h2>
    <div class="subtitle">Net profit, margin, profit-on-cost, IRR — every metric a bank or PE will ask for.</div>

    <?php if (!$frr): ?>
        <div class="pr-callout warn">Financial model has not been computed yet.</div>
    <?php else:
        $poc = ($frr['total_cost_inr'] ?? 0) > 0 ? (100.0 * ($frr['net_profit_after_interest_inr'] ?? 0) / (float)$frr['total_cost_inr']) : null;
        $roe = $poc; // simple proxy for closed-book projects
    ?>
        <div class="pr-tiles">
            <div class="pr-tile <?= ($frr['net_profit_after_interest_inr'] ?? 0) >= 0 ? 'pos' : 'neg' ?>">
                <div class="k">NP After Interest</div>
                <div class="v"><?= $fmtCr($frr['net_profit_after_interest_inr']) ?></div>
                <div class="u">the take-home</div>
            </div>
            <div class="pr-tile">
                <div class="k">NP Before Interest</div>
                <div class="v"><?= $fmtCr($frr['net_profit_before_interest_inr']) ?></div>
                <div class="u">EBITDA-like</div>
            </div>
            <div class="pr-tile <?= ($frr['net_profit_margin_pct'] ?? 0) >= 15 ? 'pos' : (($frr['net_profit_margin_pct'] ?? 0) < 0 ? 'neg' : '') ?>">
                <div class="k">Net Profit Margin</div>
                <div class="v"><?= $fmtPct($frr['net_profit_margin_pct']) ?></div>
                <div class="u">% of revenue</div>
            </div>
            <div class="pr-tile <?= ($poc ?? 0) >= 20 ? 'pos' : (($poc ?? 0) < 0 ? 'neg' : '') ?>">
                <div class="k">Profit on Cost</div>
                <div class="v"><?= $fmtPct($poc) ?></div>
                <div class="u">NP ÷ Total Cost</div>
            </div>
            <div class="pr-tile <?= ($frr['project_irr_pct'] ?? 0) >= 20 ? 'pos' : (($frr['project_irr_pct'] ?? 0) < 0 ? 'neg' : '') ?>">
                <div class="k">Project IRR</div>
                <div class="v"><?= isset($frr['project_irr_pct']) && $frr['project_irr_pct'] !== null ? number_format($frr['project_irr_pct'], 2) . '%' : '—' ?></div>
                <div class="u">annualised</div>
            </div>
            <div class="pr-tile">
                <div class="k">Revenue Multiple</div>
                <div class="v"><?= ($frr['total_cost_inr'] ?? 0) > 0 ? number_format(($frr['total_revenue_inr'] ?? 0) / $frr['total_cost_inr'], 2) . '×' : '—' ?></div>
                <div class="u">Revenue ÷ Cost</div>
            </div>
        </div>
    <?php endif; ?>
</section>

<!-- ═══════════ 16. SCENARIO ANALYSIS ═══════════ -->
<section class="pr-section" id="s16">
    <div class="pr-section-num">SECTION 16</div>
    <h2>Scenario Analysis</h2>
    <div class="subtitle">Conservative (−30% sale / +10% cost) vs Base vs Optimistic (+10% sale / −10% cost).</div>

    <?php if (empty($scenarios)): ?>
        <div class="pr-callout warn">Scenarios not available (financial model not configured).</div>
    <?php else: ?>
        <table class="pr-tbl">
            <thead>
                <tr>
                    <th>Metric</th>
                    <?php foreach ($scenarios as $key => $s): ?>
                        <th class="num" style="color: <?= e($s['preset']['color']) ?>;"><?= e($s['preset']['label']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $srows = [
                    ['Sale rate (₹/sqft)', 'sale_rate', fn($v) => $v === null ? '—' : '₹' . number_format($v, 0)],
                    ['Cost rate (₹/sqft)', 'cost_rate', fn($v) => $v === null ? '—' : '₹' . number_format($v, 0)],
                    ['Total revenue', 'revenue', $fmtCr],
                    ['Total cost', 'cost', $fmtCr],
                    ['NP after interest', 'np_after_int', $fmtCr],
                    ['Margin', 'margin', $fmtPct],
                    ['Profit on cost', 'profit_on_cost', $fmtPct],
                    ['IRR', 'irr', fn($v) => $v === null ? '—' : number_format($v, 2) . '%'],
                    ['Breakeven price', 'breakeven', fn($v) => $v === null ? '—' : '₹' . number_format($v, 0)],
                ];
                foreach ($srows as $sr): [$lbl, $k, $f] = $sr; ?>
                    <tr>
                        <td class="lbl"><?= e($lbl) ?></td>
                        <?php foreach ($scenarios as $s): ?>
                            <td class="num"><?= $f($s[$k] ?? null) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<!-- ═══════════ 17. SENSITIVITY & BREAK-EVEN ═══════════ -->
<section class="pr-section" id="s17">
    <div class="pr-section-num">SECTION 17</div>
    <h2>Sensitivity &amp; Break-Even</h2>
    <div class="subtitle">Sale-rate movements + target-return matrix. Which lever moves the needle?</div>

    <?php if (empty($sensitivity)): ?>
        <div class="pr-callout warn">Sensitivity not available (financial model not configured).</div>
    <?php else: ?>
        <?php if (!empty($sensitivity['breakeven'])): ?>
            <div class="pr-h3">Break-even &amp; target returns (closed-form)</div>
            <table class="pr-tbl">
                <thead><tr><th>Target profit-on-cost</th><th class="num">Sale rate required (₹/RERA sqft)</th></tr></thead>
                <tbody>
                    <?php foreach ($sensitivity['breakeven'] as $b): ?>
                        <tr>
                            <td class="lbl"><?= $b['target_pct'] === 0 ? 'Break-even (0%)' : $b['target_pct'] . '% profit on cost' ?></td>
                            <td class="num">₹<?= $fmt($b['required_rate'], 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($sensitivity['sale'])): ?>
            <div class="pr-h3">Sale-rate sensitivity (±30%)</div>
            <table class="pr-tbl">
                <thead><tr><th class="num">Δ</th><th class="num">Sale rate</th><th class="num">Revenue</th><th class="num">NP after int</th><th class="num">Margin</th><th class="num">IRR</th></tr></thead>
                <tbody>
                    <?php foreach ($sensitivity['sale'] as $r): $isBase = $r['delta_pct'] === 0; ?>
                        <tr <?= $isBase ? 'style="background:#eef2ff;font-weight:700;"' : '' ?>>
                            <td class="num"><?= ($r['delta_pct'] > 0 ? '+' : '') . $r['delta_pct'] ?>%</td>
                            <td class="num">₹<?= $fmt($r['sale_rate'], 0) ?></td>
                            <td class="num"><?= $fmtCr($r['revenue'] ?? null) ?></td>
                            <td class="num" style="color:<?= ($r['np'] ?? 0) < 0 ? '#dc2626' : '#059669' ?>;font-weight:700;"><?= $fmtCr($r['np'] ?? null) ?></td>
                            <td class="num"><?= $fmtPct($r['margin'] ?? null) ?></td>
                            <td class="num"><?= isset($r['irr']) && $r['irr'] !== null ? number_format($r['irr'], 2) . '%' : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</section>

<!-- ═══════════ 18. REGULATORY VALIDATION & DISCREPANCIES ═══════════ -->
<section class="pr-section" id="s18">
    <div class="pr-section-num">SECTION 18</div>
    <h2>Regulatory Validation &amp; Discrepancies</h2>
    <div class="subtitle">Traffic-light regulatory checks + all cross-formula discrepancies flagged by the auditor.</div>

    <?php if (!empty($valids)): ?>
        <div class="pr-h3">Regulatory validation</div>
        <table class="pr-tbl">
            <thead><tr><th></th><th>Check</th><th class="num">Entered</th><th class="num">Required</th><th>Ref</th></tr></thead>
            <tbody>
                <?php foreach ($valids as $v): $chip = $v['result']==='pass'?'chip-pass':($v['result']==='warn'?'chip-warn':'chip-error'); ?>
                    <tr>
                        <td><span class="chip <?= $chip ?>"><?= strtoupper(e($v['result'])) ?></span></td>
                        <td class="lbl"><?= e($v['check_label']) ?><br><span class="desc"><?= e($v['message']) ?></span></td>
                        <td class="num"><?= e($v['entered_value'] ?? '—') ?></td>
                        <td class="num"><?= e($v['required_value'] ?? '—') ?></td>
                        <td class="ref"><?= e($v['regulation_ref'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php
    $open = array_filter($discrepancies ?? [], fn($d) => !$d['resolved']);
    $done = array_filter($discrepancies ?? [], fn($d) =>  $d['resolved']);
    ?>
    <div class="pr-h3">Formula discrepancies (<?= count($open) ?> open · <?= count($done) ?> resolved)</div>
    <?php if (empty($discrepancies)): ?>
        <div class="pr-callout success">All identities hold within tolerance — no cross-check discrepancies detected.</div>
    <?php else: ?>
        <table class="pr-tbl">
            <thead><tr><th></th><th>Check</th><th class="num">Expected</th><th class="num">Actual</th><th class="num">Δ %</th></tr></thead>
            <tbody>
                <?php foreach ($discrepancies as $d): $chip = $d['resolved'] ? 'chip-pass' : ($d['severity']==='error'?'chip-error':($d['severity']==='warning'?'chip-warn':'chip-info')); ?>
                    <tr <?= $d['resolved'] ? 'style="opacity:0.6;"' : '' ?>>
                        <td><span class="chip <?= $chip ?>"><?= $d['resolved'] ? 'RESOLVED' : strtoupper(e($d['severity'])) ?></span></td>
                        <td class="lbl"><?= e($d['check_label']) ?><br><span class="desc"><?= e($d['description'] ?? '') ?></span><?= $d['resolution_notes'] ? '<br><span class="desc" style="color:#059669;">✓ ' . e($d['resolution_notes']) . '</span>' : '' ?></td>
                        <td class="num"><?= $fmt($d['expected_value']) ?></td>
                        <td class="num"><?= $fmt($d['actual_value']) ?></td>
                        <td class="num"><?= $fmt($d['variance_pct'], 3) ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<!-- ═══════════ 19. ASSUMPTIONS & REGULATORY CONSTANTS ═══════════ -->
<section class="pr-section" id="s19">
    <div class="pr-section-num">SECTION 19</div>
    <h2>Assumptions &amp; Regulatory Constants</h2>
    <div class="subtitle">Every DCPR constant that shaped the numbers. Sourced from <code>regulatory_constants</code>.</div>

    <table class="pr-tbl">
        <thead><tr><th>Constant</th><th>Scope</th><th class="num">Value</th><th>Unit</th><th>DCPR ref</th></tr></thead>
        <tbody>
            <?php foreach ($regulatory_rules ?? [] as $r): ?>
                <tr>
                    <td class="lbl"><?= e($r['label']) ?><br><span class="desc" style="font-family:'JetBrains Mono',monospace;"><?= e($r['rule_key']) ?></span></td>
                    <td class="desc"><?= e($r['scope']) ?></td>
                    <td class="num"><?= rtrim(rtrim(number_format((float)$r['numeric_value'], 4), '0'), '.') ?></td>
                    <td class="desc"><?= e($r['unit']) ?></td>
                    <td class="ref"><?= e($r['regulation_ref']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<!-- ═══════════ 20. SOURCES & AUDIT TRAIL ═══════════ -->
<section class="pr-section" id="s20">
    <div class="pr-section-num">SECTION 20</div>
    <h2>Sources &amp; Audit Trail</h2>
    <div class="subtitle">Data provenance, version history, and everything a reviewer needs to reproduce these numbers.</div>

    <div class="pr-h3">Statement metadata</div>
    <div class="pr-kv">
        <div><span class="k">Statement ID</span><span class="v">#<?= (int)$statement['id'] ?></span></div>
        <div><span class="k">Version</span><span class="v"><?= $ver ?></span></div>
        <div><span class="k">Parent statement</span><span class="v"><?= $statement['parent_statement_id'] ? '#' . (int)$statement['parent_statement_id'] : '(none)' ?></span></div>
        <div><span class="k">Created</span><span class="v"><?= e($statement['created_at']) ?></span></div>
        <div><span class="k">Calculated</span><span class="v"><?= e($statement['calculated_at'] ?? '(never)') ?></span></div>
        <div><span class="k">Locked</span><span class="v"><?= e($statement['locked_at'] ?? '(not locked)') ?></span></div>
        <div><span class="k">Status</span><span class="v"><?= e($statement['status']) ?></span></div>
        <div><span class="k">Rows persisted</span><span class="v"><?= count($calcs) ?> calc + <?= count($land) ?> land + <?= count($dev) ?> dev</span></div>
    </div>

    <?php $s3 = $draft['step_3'] ?? []; ?>
    <div class="pr-h3">Input provenance</div>
    <div class="pr-kv">
        <div><span class="k">Tenement source</span><span class="v"><?= e($s3['s3_tenement_source'] ?? '—') ?></span></div>
        <div><span class="k">Source reference</span><span class="v"><?= e($s3['s3_source_ref'] ?? '—') ?></span></div>
        <div><span class="k">Cut-off date</span><span class="v"><?= e($s3['s3_cutoff_date'] ?? '—') ?></span></div>
        <div><span class="k">Ineligible count</span><span class="v"><?= e($s3['s3_ineligible_count'] ?? '0') ?></span></div>
    </div>

    <div class="pr-h3">Financial model provenance</div>
    <?php if ($fin): ?>
        <div class="pr-kv">
            <div><span class="k">Financials created</span><span class="v"><?= e($fin['created_at'] ?? '—') ?></span></div>
            <div><span class="k">Financials updated</span><span class="v"><?= e($fin['updated_at'] ?? '—') ?></span></div>
            <div><span class="k">Line items configured</span><span class="v"><?= count($line_items ?? []) ?> across <?= count($byCat) ?> categories</span></div>
            <div><span class="k">Loan interest rate</span><span class="v"><?= $fmtPct($fin['loan_interest_rate_pct'] ?? 0) ?></span></div>
        </div>
    <?php else: ?>
        <div class="pr-callout">Financial model not configured. Complete the Financials form to populate this section.</div>
    <?php endif; ?>

    <div class="pr-h3">Report generation</div>
    <div class="pr-kv">
        <div><span class="k">Generated on</span><span class="v"><?= date('d M Y H:i:s') ?></span></div>
        <div><span class="k">Report version</span><span class="v">BDVault Feasibility Report v1.0</span></div>
        <div><span class="k">Engine</span><span class="v">AreaCalcEngine + Reg33_<?= e($reg ?: '?') ?>_Calculator + FinancialsCalculator</span></div>
        <div><span class="k">Rules registry</span><span class="v"><?= count($regulatory_rules ?? []) ?> constants loaded</span></div>
    </div>

    <div class="pr-callout">
        <strong>Reproducibility</strong>
        Every number in this report can be traced back to (a) a user-entered input in Steps 1–3, (b) a regulatory constant in <code>regulatory_constants</code> with a DCPR clause reference, or (c) a formula in Section 10 with all its inputs cited. There are no unsourced numbers.
    </div>
</section>
