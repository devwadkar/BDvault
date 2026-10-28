<?php $pageTitle = 'Sensitivity · ' . e($statement['statement_name']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php
$cr  = fn($v) => $v === null ? '—' : '₹' . number_format(($v ?? 0) / 1e7, 2) . ' Cr';
$pct = fn($v) => $v === null ? '—' : number_format($v, 2) . '%';
$num = fn($v, $d = 2) => $v === null ? '—' : number_format($v, $d);
$cellClass = function ($np) {
    if ($np === null) return '';
    if ($np < 0)          return 'cell-neg';
    if ($np < 300 * 1e7)  return 'cell-low';
    if ($np < 1000 * 1e7) return 'cell-mid';
    return 'cell-hi';
};
?>

<style>
    :root { --sn-ink: #0f172a; --sn-muted: #64748b; --sn-border: #e2e8f0;
            --sn-primary: #4f46e5; --sn-success: #059669; --sn-warning: #d97706; --sn-danger: #dc2626; }
    .sn-hero {
        display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;
        padding: 22px 26px; margin-bottom: 20px; background: #fff;
        border: 1px solid var(--sn-border); border-radius: 14px;
    }
    .sn-hero h4 { font-weight: 700; margin: 0; color: var(--sn-ink); letter-spacing: -0.01em; }
    .sn-hero .sub { color: var(--sn-muted); font-size: 0.85rem; margin-top: 2px; }
    .sn-back { padding: 7px 14px; border-radius: 8px; background: #f1f5f9; color: #475569;
               font-size: 0.78rem; font-weight: 700; text-decoration: none; }

    .sn-card { background: #fff; border: 1px solid var(--sn-border); border-radius: 14px; padding: 24px 26px; margin-bottom: 20px; }
    .sn-card h5 { font-weight: 700; margin: 0 0 4px; font-size: 1.05rem; color: var(--sn-ink); letter-spacing: -0.01em; }
    .sn-card .lead { color: var(--sn-muted); font-size: 0.85rem; margin: 0 0 20px; }

    /* Break-even card */
    .be-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; }
    .be-cell {
        background: linear-gradient(135deg, #ecfdf5, #d1fae5); border: 1px solid #86efac;
        border-radius: 10px; padding: 14px 16px; text-align: center;
    }
    .be-cell.zero  { background: linear-gradient(135deg, #fee2e2, #fecaca); border-color: #fca5a5; }
    .be-cell .lbl { color: #065f46; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
    .be-cell.zero .lbl { color: #991b1b; }
    .be-cell .val { color: #064e3b; font-weight: 800; font-size: 1.2rem; font-family: 'JetBrains Mono', Consolas, monospace; margin-top: 4px; }
    .be-cell.zero .val { color: #7f1d1d; }
    .be-cell .sub { color: #047857; font-size: 0.72rem; margin-top: 4px; }
    .be-cell.zero .sub { color: #b91c1c; }

    /* Sensitivity tables */
    .sn-tbl { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .sn-tbl th, .sn-tbl td { padding: 10px 12px; text-align: right; border-bottom: 1px solid var(--sn-border); }
    .sn-tbl th:first-child, .sn-tbl td:first-child { text-align: left; }
    .sn-tbl thead th { background: #f8fafc; font-weight: 700; color: var(--sn-ink); text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.04em; }
    .sn-tbl tbody tr:hover { background: #f8fafc; }
    .sn-tbl .num { font-family: 'JetBrains Mono', Consolas, monospace; }
    .sn-tbl .row-base { background: #eef2ff; font-weight: 700; }
    .sn-tbl .row-base:hover { background: #e0e7ff; }
    .sn-tbl .delta-pill {
        display: inline-block; padding: 3px 10px; border-radius: 999px;
        font-size: 0.72rem; font-weight: 700; font-family: 'JetBrains Mono', Consolas, monospace;
    }
    .sn-tbl .delta-pill.neg { background: #fee2e2; color: #991b1b; }
    .sn-tbl .delta-pill.pos { background: #dcfce7; color: #065f46; }
    .sn-tbl .delta-pill.zero { background: #eef2ff; color: #3730a3; }

    /* NP cell coloring */
    td.cell-neg { color: #991b1b; font-weight: 700; }
    td.cell-low { color: #d97706; font-weight: 700; }
    td.cell-mid { color: #059669; font-weight: 700; }
    td.cell-hi  { color: #065f46; font-weight: 800; }
</style>

<div class="sn-hero">
    <div>
        <h4><i class="bi bi-sliders me-2" style="color: var(--sn-primary);"></i>Sensitivity &amp; Break-even</h4>
        <div class="sub"><?= e($statement['statement_name']) ?>
            <?php if ($proposal['proposal_name'] ?? null): ?> · <?= e($proposal['proposal_name']) ?><?php endif; ?>
        </div>
    </div>
    <a href="index.php?page=area_analysis_financials_view&statement_id=<?= (int)$statement['id'] ?>" class="sn-back">
        <i class="bi bi-arrow-left me-1"></i>Back to Financials
    </a>
</div>

<div class="sn-card">
    <h5>Break-even &amp; target returns</h5>
    <p class="lead">Minimum sale rate needed to hit each profit-on-cost target. Closed-form: (Total Cost × (1 + T)) ÷ RERA Carpet.</p>

    <?php if (empty($breakevenTargets)): ?>
        <div class="text-muted">Missing RERA carpet or cost data — save financials with valid inputs first.</div>
    <?php else: ?>
        <div class="be-grid">
            <?php foreach ($breakevenTargets as $bt): ?>
                <div class="be-cell <?= $bt['target_pct'] === 0 ? 'zero' : '' ?>">
                    <div class="lbl">
                        <?= $bt['target_pct'] === 0 ? 'Break-even' : $bt['target_pct'] . '% profit-on-cost' ?>
                    </div>
                    <div class="val">₹<?= number_format($bt['required_rate'], 0) ?></div>
                    <div class="sub">/RERA sqft carpet</div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="text-muted small mt-3" style="font-size: 0.75rem;">
            <i class="bi bi-info-circle me-1"></i>Simple linear estimate. Assumes cost stays flat as sale rate changes; ignores marketing-% effect. Use the sensitivity tables below for the full model.
        </p>
    <?php endif; ?>
</div>

<div class="sn-card">
    <h5>Sale-rate sensitivity</h5>
    <p class="lead">Full model re-run for each ±% variation in Y1 sale rate. Base row highlighted.</p>

    <div class="table-responsive">
        <table class="sn-tbl">
            <thead>
                <tr>
                    <th>Δ Rate</th>
                    <th>Sale rate (₹/sqft)</th>
                    <th>Revenue</th>
                    <th>Total cost</th>
                    <th>NP after int</th>
                    <th>Margin</th>
                    <th>Profit on cost</th>
                    <th>IRR</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($saleRateSensitivity as $r): $isBase = $r['delta_pct'] === 0; ?>
                    <tr class="<?= $isBase ? 'row-base' : '' ?>">
                        <td>
                            <span class="delta-pill <?= $r['delta_pct'] < 0 ? 'neg' : ($r['delta_pct'] > 0 ? 'pos' : 'zero') ?>">
                                <?= $r['delta_pct'] > 0 ? '+' : '' ?><?= $r['delta_pct'] ?>%
                            </span>
                        </td>
                        <td class="num">₹<?= number_format($r['sale_rate'], 0) ?></td>
                        <td class="num"><?= $cr($r['revenue'] ?? null) ?></td>
                        <td class="num"><?= $cr($r['cost'] ?? null) ?></td>
                        <td class="num <?= $cellClass($r['np'] ?? null) ?>"><?= $cr($r['np'] ?? null) ?></td>
                        <td class="num"><?= $pct($r['margin'] ?? null) ?></td>
                        <td class="num"><?= $pct($r['profit_on_cost'] ?? null) ?></td>
                        <td class="num"><?= isset($r['irr']) && $r['irr'] !== null ? number_format($r['irr'], 2) . '%' : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="sn-card">
    <h5>Construction-cost sensitivity</h5>
    <p class="lead">Full model re-run for each ±% variation in Y1 rehab &amp; sale construction rates (both move together).</p>

    <div class="table-responsive">
        <table class="sn-tbl">
            <thead>
                <tr>
                    <th>Δ Cost</th>
                    <th>Sale-cost rate</th>
                    <th>Revenue</th>
                    <th>Total cost</th>
                    <th>NP after int</th>
                    <th>Margin</th>
                    <th>Profit on cost</th>
                    <th>IRR</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($costSensitivity as $r): $isBase = $r['delta_pct'] === 0; ?>
                    <tr class="<?= $isBase ? 'row-base' : '' ?>">
                        <td>
                            <span class="delta-pill <?= $r['delta_pct'] < 0 ? 'pos' : ($r['delta_pct'] > 0 ? 'neg' : 'zero') ?>">
                                <?= $r['delta_pct'] > 0 ? '+' : '' ?><?= $r['delta_pct'] ?>%
                            </span>
                        </td>
                        <td class="num">₹<?= number_format($r['sale_cost_rate'], 0) ?></td>
                        <td class="num"><?= $cr($r['revenue'] ?? null) ?></td>
                        <td class="num"><?= $cr($r['cost'] ?? null) ?></td>
                        <td class="num <?= $cellClass($r['np'] ?? null) ?>"><?= $cr($r['np'] ?? null) ?></td>
                        <td class="num"><?= $pct($r['margin'] ?? null) ?></td>
                        <td class="num"><?= $pct($r['profit_on_cost'] ?? null) ?></td>
                        <td class="num"><?= isset($r['irr']) && $r['irr'] !== null ? number_format($r['irr'], 2) . '%' : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
