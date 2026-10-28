<?php $pageTitle = 'Financial Model — ' . e($plan['plan_name']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<style>
    .fm-hero { background: linear-gradient(135deg, #064e3b, #065f46); color: #fff; border-radius: 14px; padding: 22px 26px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 12px; }
    .fm-hero h5 { margin: 6px 0 0; font-weight: 700; }
    .fm-hero p  { margin: 4px 0 0; color: #d1fae5; font-size: 0.8rem; }
    .fm-hero .badge-reg { background: rgba(16,185,129,0.35); color: #ecfdf5; font-weight: 700; padding: 3px 12px; border-radius: 999px; font-size: 0.72rem; }
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 18px; }
    .kpi-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px 16px; }
    .kpi-card .lbl { color: #64748b; text-transform: uppercase; letter-spacing: .06em; font-size: 0.66rem; font-weight: 600; }
    .kpi-card .val { color: #0f172a; font-family: 'JetBrains Mono', 'Consolas', monospace; font-size: 1.1rem; font-weight: 700; margin-top: 4px; }
    .kpi-card.hi   { background: #ecfdf5; border-color: #a7f3d0; }
    .kpi-card.hi .val { color: #065f46; }
    .kpi-card.warn { background: #fef3c7; border-color: #fde68a; }
    .kpi-card.warn .val { color: #92400e; }
    .fm-section { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px 20px; margin-bottom: 16px; }
    .fm-section h6 { font-weight: 700; text-transform: uppercase; letter-spacing: .06em; font-size: 0.72rem; color: #0f172a; margin: 0 0 12px; }
    table.yy { font-size: 0.75rem; }
    table.yy th, table.yy td { padding: 6px 8px; text-align: right; font-family: 'JetBrains Mono', 'Consolas', monospace; }
    table.yy th:first-child, table.yy td:first-child { text-align: left; font-family: inherit; font-weight: 600; color: #334155; }
    table.yy tbody tr:hover { background: #f8fafc; }
    table.yy tfoot td { font-weight: 700; background: #f1f5f9; color: #0f172a; border-top: 2px solid #cbd5e1; }
    .fm-neg { color: #dc2626; }
    .fm-pos { color: #059669; }
    .fm-warn-strip { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 10px 14px; border-radius: 8px; font-size: 0.78rem; margin-bottom: 14px; }
    .loan-row.mor  { background: #fffbeb; }
    .loan-row.rep  { background: #eff6ff; }
</style>

<?php
$sum   = $summary;
$rev   = $sum['total_revenue_inr'] ?? 0;
$cost  = $sum['total_cost_inr'] ?? 0;
$npB   = $sum['net_profit_before_interest_inr'] ?? 0;
$npA   = $sum['net_profit_after_interest_inr'] ?? 0;
$irr   = $sum['project_irr_pct'];
$eqIrr = $sum['equity_irr_pct'];
$fmt   = fn($n) => '₹' . number_format(($n ?? 0)/1e7, 2) . ' Cr';
?>

<div class="fm-hero">
    <div>
        <span class="badge-reg">FINANCIAL MODEL</span>
        <h5><?= e($plan['plan_name']) ?><?= $fin['scenario_label'] ? ' — ' . e($fin['scenario_label']) : '' ?></h5>
        <p>Calculated <?= e($fin['calculated_at']) ?> · <?= (int)$fin['project_duration_years'] ?>-year model · <?= (float)$fin['annual_escalation_pct'] ?>% escalation</p>
    </div>
    <div>
        <a href="index.php?page=planning_financials_form&plan_id=<?= (int)$plan['id'] ?>" class="btn btn-sm btn-outline-light">
            <i class="bi bi-pencil me-1"></i>Edit &amp; Recalculate
        </a>
    </div>
</div>

<?php if (!empty($warnings)): ?>
    <div class="fm-warn-strip">
        <b>Model warnings:</b>
        <ul class="mb-0 mt-1">
            <?php foreach ($warnings as $w): ?><li><?= e($w) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="kpi-grid">
    <div class="kpi-card"><div class="lbl">Total Revenue (booked)</div><div class="val"><?= $fmt($rev) ?></div></div>
    <div class="kpi-card"><div class="lbl">Total Cost</div><div class="val"><?= $fmt($cost) ?></div></div>
    <div class="kpi-card hi"><div class="lbl">NP before Interest</div><div class="val"><?= $fmt($npB) ?></div></div>
    <div class="kpi-card"><div class="lbl">Interest Cost</div><div class="val"><?= $fmt($sum['total_interest_inr'] ?? 0) ?></div></div>
    <div class="kpi-card hi"><div class="lbl">NP after Interest</div><div class="val"><?= $fmt($npA) ?></div></div>
    <div class="kpi-card hi"><div class="lbl">Project IRR</div><div class="val"><?= $irr !== null ? number_format($irr, 2) . '%' : '—' ?></div></div>
    <div class="kpi-card hi"><div class="lbl">Equity IRR</div><div class="val"><?= $eqIrr !== null ? number_format($eqIrr, 2) . '%' : '—' ?></div></div>
    <div class="kpi-card"><div class="lbl">NP Margin</div><div class="val"><?= number_format($sum['net_profit_margin_pct'] ?? 0, 2) ?>%</div></div>
    <div class="kpi-card"><div class="lbl">Breakeven Price</div><div class="val">₹<?= number_format($sum['breakeven_price_per_rera_sqft'] ?? 0, 0) ?>/sqft</div></div>
    <div class="kpi-card"><div class="lbl">Partner Surplus Share</div><div class="val"><?= $fmt($sum['partner_share_inr'] ?? 0) ?></div></div>
</div>

<div class="fm-section">
    <h6>Yearly Cash Flow (₹ Cr)</h6>
    <div class="table-responsive">
        <table class="table yy align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Item</th>
                    <?php foreach ($yearly as $r): ?><th>Y<?= $r['year'] ?></th><?php endforeach; ?>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $rows = [
                    'Sale Rate (₹/sqft)'  => ['sale_rate', false, false],
                    'Booking %'           => ['booking_pct', true, false],
                    'Collection %'        => ['collection_pct', true, false],
                    'Revenue Booked'      => ['revenue_booked', false, true],
                    'Cash Inflow'         => ['cash_inflow', false, true],
                    'Rehab Cost'          => ['rehab_cost', false, true],
                    'Sale Cost'           => ['sale_cost', false, true],
                    'Premium'             => ['premium', false, true],
                    'Society Cost'        => ['society_cost', false, true],
                    'Marketing'           => ['marketing', false, true],
                    'Admin'                => ['admin', false, true],
                    'Contingency'          => ['contingency', false, true],
                    'Other Overhead'       => ['other_overhead', false, true],
                    'Total Cost'           => ['total_cost', false, true],
                    'Net Cash (before int)'=> ['net_cash_before_interest', false, true],
                    'Interest'             => ['interest', false, true],
                    'Net Cash (after int)' => ['net_cash_after_interest', false, true],
                ];
                foreach ($rows as $label => [$key, $isPct, $isCr]):
                    $sumRow = 0;
                ?>
                    <tr>
                        <td><?= e($label) ?></td>
                        <?php foreach ($yearly as $r):
                            $v = $r[$key] ?? 0;
                            $sumRow += $isPct ? 0 : $v;
                            if ($isPct) {
                                $disp = number_format($v * 100, 1) . '%';
                            } elseif ($isCr) {
                                $disp = number_format($v / 1e7, 2);
                            } else {
                                $disp = number_format($v, 0);
                            }
                            $cls = ($isCr && $v < 0) ? 'fm-neg' : '';
                        ?>
                            <td class="<?= $cls ?>"><?= $disp ?></td>
                        <?php endforeach; ?>
                        <td>
                            <?php if ($isCr) echo number_format($sumRow / 1e7, 2); elseif (!$isPct) echo number_format($sumRow, 0); else echo '—'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="text-muted small mt-2">All ₹ values shown in Crores. Cash-flow uses <b>collections</b> (not bookings) as inflows.</div>
</div>

<div class="fm-section">
    <h6>Loan Amortization Schedule</h6>
    <div class="table-responsive">
        <table class="table yy align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Year</th>
                    <th>Opening Balance</th>
                    <th>Interest</th>
                    <th>Principal</th>
                    <th>Repayment Total</th>
                    <th>Closing Balance</th>
                    <th>Flag</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($loan as $l):
                    $cls = $l['flag'] === 'Moratorium' ? 'loan-row mor' : ($l['flag'] === 'Repayment' ? 'loan-row rep' : '');
                ?>
                    <tr class="<?= $cls ?>">
                        <td>Y<?= $l['year'] ?></td>
                        <td><?= number_format($l['opening_balance']/1e7, 2) ?> Cr</td>
                        <td><?= number_format($l['interest']/1e7, 2) ?> Cr</td>
                        <td><?= number_format($l['principal_repayment']/1e7, 2) ?> Cr</td>
                        <td><?= number_format($l['repayment_total']/1e7, 2) ?> Cr</td>
                        <td><?= number_format($l['closing_balance']/1e7, 2) ?> Cr</td>
                        <td><?= e($l['flag']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="fm-section">
    <h6>Snapshot Used at Run-Time</h6>
    <div class="row g-3 small">
        <div class="col-md-3"><b>Sale BUA (gross):</b> <?= number_format($fin['snapshot_sale_bua_gross_sqft'] ?? 0, 0) ?> sqft</div>
        <div class="col-md-3"><b>RERA Carpet:</b> <?= number_format($fin['snapshot_rera_carpet_sqft'] ?? 0, 0) ?> sqft</div>
        <div class="col-md-3"><b>Rehab Construction:</b> <?= number_format($fin['snapshot_rehab_construction_sqft'] ?? 0, 0) ?> sqft</div>
        <div class="col-md-3"><b>Premium Total:</b> <?= $fmt($fin['snapshot_premium_total_inr'] ?? 0) ?></div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
