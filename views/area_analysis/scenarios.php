<?php $pageTitle = 'Scenarios · ' . e($statement['statement_name']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php
$cr = fn($v) => '₹' . number_format(($v ?? 0) / 1e7, 2) . ' Cr';
$pct = fn($v) => $v === null ? '—' : number_format($v, 2) . '%';
$num = fn($v, $d = 2) => $v === null ? '—' : number_format($v, $d);
?>

<style>
    :root {
        --sc-ink: #0f172a; --sc-muted: #64748b; --sc-border: #e2e8f0;
        --sc-surface: #f8fafc; --sc-primary: #4f46e5;
    }
    .sc-hero { padding: 22px 26px; margin-bottom: 20px; background: #fff;
               border: 1px solid var(--sc-border); border-radius: 14px;
               display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
    .sc-hero h4 { font-weight: 700; margin: 0; color: var(--sc-ink); letter-spacing: -0.01em; }
    .sc-hero .sub { color: var(--sc-muted); font-size: 0.85rem; margin-top: 2px; }
    .sc-back { padding: 7px 14px; border-radius: 8px; background: #f1f5f9; color: #475569;
               font-size: 0.78rem; font-weight: 700; text-decoration: none; }

    .sc-card { background: #fff; border: 1px solid var(--sc-border); border-radius: 14px; padding: 26px 28px; margin-bottom: 20px; }
    .sc-card h5 { font-weight: 700; margin: 0 0 6px; font-size: 1.05rem; color: var(--sc-ink); }
    .sc-card .lead { color: var(--sc-muted); font-size: 0.88rem; margin: 0 0 22px; }

    .sc-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
    .sc-scenario {
        background: #fff; border: 2px solid var(--sc-border); border-radius: 14px;
        padding: 20px 22px; position: relative;
    }
    .sc-scenario.base { border-color: var(--sc-primary); box-shadow: 0 6px 16px rgba(79,70,229,0.08); }
    .sc-scenario .sc-name { font-weight: 800; font-size: 0.98rem; letter-spacing: -0.01em; }
    .sc-scenario .sc-tag { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; }
    .sc-scenario .sc-headline {
        display: flex; align-items: baseline; gap: 4px; margin: 10px 0 4px;
        font-family: 'JetBrains Mono', Consolas, monospace;
    }
    .sc-scenario .sc-headline .amt { font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; }
    .sc-scenario .sc-headline .lbl { color: var(--sc-muted); font-size: 0.72rem; margin-left: 6px; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; font-family: inherit; }
    .sc-scenario .sc-margin { color: var(--sc-muted); font-size: 0.85rem; }

    .sc-rates {
        display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
        margin-top: 16px; padding-top: 14px; border-top: 1px dashed var(--sc-border);
        font-size: 0.75rem;
    }
    .sc-rates .k { color: var(--sc-muted); }
    .sc-rates .v { color: var(--sc-ink); font-weight: 600; font-family: 'JetBrains Mono', Consolas, monospace; }

    .sc-metric-tbl { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    .sc-metric-tbl th, .sc-metric-tbl td { padding: 12px 14px; text-align: right; border-bottom: 1px solid var(--sc-border); }
    .sc-metric-tbl th:first-child, .sc-metric-tbl td:first-child { text-align: left; }
    .sc-metric-tbl thead th { background: var(--sc-surface); font-weight: 700; color: var(--sc-ink); text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.04em; }
    .sc-metric-tbl tbody td.v { font-family: 'JetBrains Mono', Consolas, monospace; }
    .sc-metric-tbl tbody tr:hover { background: var(--sc-surface); }
    .sc-metric-tbl .row-hi td { background: #eef2ff; font-weight: 700; }
    .sc-metric-tbl .neg { color: #dc2626; }
    .sc-metric-tbl .pos { color: #059669; }
</style>

<div class="sc-hero">
    <div>
        <h4><i class="bi bi-arrow-left-right me-2" style="color: var(--sc-primary);"></i>Scenario Analysis</h4>
        <div class="sub">
            <?= e($statement['statement_name']) ?>
            <?php if ($proposal['proposal_name'] ?? null): ?> · <?= e($proposal['proposal_name']) ?><?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="index.php?page=area_analysis_sensitivity&statement_id=<?= (int)$statement['id'] ?>" class="sc-back" style="background:#0ea5e9;color:#fff;">
            <i class="bi bi-sliders me-1"></i>Sensitivity
        </a>
        <a href="index.php?page=area_analysis_financials_view&statement_id=<?= (int)$statement['id'] ?>" class="sc-back">
            <i class="bi bi-arrow-left me-1"></i>Back to Financials
        </a>
    </div>
</div>

<div class="sc-card">
    <h5>Preset scenarios</h5>
    <p class="lead">Each scenario adjusts sale rate, construction cost and escalation off the Base model. Numbers recompute live.</p>

    <div class="sc-grid">
        <?php foreach ($results as $key => $r): $p = $r['preset']; ?>
            <div class="sc-scenario <?= $key === 'base' ? 'base' : '' ?>" style="border-top: 4px solid <?= $p['color'] ?>;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <div class="sc-tag" style="color: <?= $p['color'] ?>;"><?= $key === 'base' ? '● BASELINE' : $p['label'] ?></div>
                        <div class="sc-name"><?= e($p['label']) ?> scenario</div>
                    </div>
                </div>

                <?php if (isset($r['error'])): ?>
                    <div class="text-danger small mt-3"><?= e($r['error']) ?></div>
                <?php else: ?>
                    <div class="sc-headline">
                        <span class="amt" style="color: <?= ($r['np_after_int'] ?? 0) < 0 ? '#dc2626' : '#059669' ?>;">
                            <?= $cr($r['np_after_int']) ?>
                        </span>
                        <span class="lbl">NP after int</span>
                    </div>
                    <div class="sc-margin">Margin <?= $pct($r['margin']) ?> · IRR
                        <?= $r['irr'] === null ? '—' : number_format($r['irr'], 2) . '%' ?>
                    </div>

                    <div class="sc-rates">
                        <div class="k">Sale rate</div>
                        <div class="v">₹<?= $num($r['sale_rate'], 0) ?>/sqft</div>
                        <div class="k">Cost rate</div>
                        <div class="v">₹<?= $num($r['cost_rate'], 0) ?>/sqft</div>
                        <div class="k">Multipliers</div>
                        <div class="v">×<?= number_format($p['sale_mult'], 2) ?> / ×<?= number_format($p['cost_mult'], 2) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="sc-card">
    <h5>Comparison</h5>
    <p class="lead">Side-by-side metrics across all three scenarios.</p>

    <div class="table-responsive">
        <table class="sc-metric-tbl">
            <thead>
                <tr>
                    <th>Metric</th>
                    <?php foreach ($results as $key => $r): ?>
                        <th style="color: <?= $r['preset']['color'] ?>;"><?= e($r['preset']['label']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $rows = [
                    ['Sale rate (₹/sqft carpet)', 'sale_rate',   fn($v) => '₹' . $num($v, 0),   false],
                    ['Cost rate (₹/sqft build)',  'cost_rate',   fn($v) => '₹' . $num($v, 0),   false],
                    ['Total revenue',             'revenue',     $cr,                            false],
                    ['Total cost',                'cost',        $cr,                            false],
                    ['NP after interest',         'np_after_int',$cr,                            true],
                    ['Profit on cost',            'profit_on_cost', fn($v) => $pct($v),          true],
                    ['Net profit margin',         'margin',      fn($v) => $pct($v),             true],
                    ['Project IRR',               'irr',         fn($v) => $v === null ? '—' : number_format($v, 2) . '%', true],
                    ['Breakeven price / RERA sqft','breakeven',  fn($v) => '₹' . $num($v, 0),   false],
                ];
                foreach ($rows as $rr): [$label, $key, $fmt, $hi] = $rr; ?>
                    <tr class="<?= $hi ? 'row-hi' : '' ?>">
                        <td><?= e($label) ?></td>
                        <?php foreach ($results as $sk => $r):
                            $v = $r[$key] ?? null;
                            $cls = '';
                            if (in_array($key, ['np_after_int', 'profit_on_cost', 'margin'], true)) {
                                if ($v !== null && $v < 0) $cls = 'neg';
                                elseif ($v !== null && $v > 0) $cls = 'pos';
                            }
                        ?>
                            <td class="v <?= $cls ?>"><?= $fmt($v) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="text-muted small mt-3" style="font-size: 0.75rem;">
        <i class="bi bi-info-circle me-1"></i>
        Scenarios are computed on demand from the Base financial model. Adjust base rates in the
        <a href="index.php?page=area_analysis_financials_form&statement_id=<?= (int)$statement['id'] ?>">financials form</a>
        to move all three curves.
    </p>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
