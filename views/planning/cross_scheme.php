<?php $pageTitle = 'Cross-Scheme Comparison — ' . e($proposal['proposal_name']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<style>
    .cs-hero {
        background: linear-gradient(135deg, #0f172a, #1e293b);
        color: #fff; border-radius: 14px; padding: 22px 26px; margin-bottom: 20px;
    }
    .cs-hero h4 { font-weight: 800; margin: 0 0 4px; letter-spacing: -0.02em; }
    .cs-hero p  { margin: 0; color: #cbd5e1; font-size: 0.85rem; }
    .cs-hero .k { display:inline-block; background: rgba(16,185,129,0.25); color:#a7f3d0; font-weight:700; padding:3px 12px; border-radius:999px; font-size:0.72rem;}
    .cs-table {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;
    }
    .cs-table table { margin: 0; width: 100%; border-collapse: collapse; }
    .cs-table th, .cs-table td { padding: 10px 14px; vertical-align: top; }
    .cs-table thead th {
        background: #f8fafc; border-bottom: 2px solid #e5e7eb; color: #0f172a;
        font-size: 0.75rem; text-transform: uppercase; letter-spacing: .06em; font-weight: 700;
    }
    .cs-table thead th.best { background: #d1fae5; color: #065f46; }
    .cs-table thead th .reg-badge {
        display: inline-block; background: #4f46e5; color:#fff; padding: 2px 10px; border-radius: 999px;
        font-size: 0.7rem; font-weight: 700; margin-bottom: 4px;
    }
    .cs-table thead th.best .reg-badge { background: #10b981; }
    .cs-table thead th .plan-name { display:block; color:#334155; font-size:0.8rem; margin-top: 2px; text-transform: none; letter-spacing: 0; }
    .cs-table thead th .status-pill {
        display:inline-block; font-size:0.65rem; padding:1px 8px; border-radius:999px;
        background:#dbeafe; color:#1e40af; font-weight:600; margin-top: 2px;
    }
    .cs-table tbody tr:nth-child(even) { background: #f8fafc; }
    .cs-table tbody tr:hover { background: #eef2ff; }
    .cs-table td.metric-label {
        font-weight: 600; color: #334155; font-size: 0.82rem; border-right: 1px solid #f1f5f9;
        min-width: 240px;
    }
    .cs-table td.value {
        font-family: 'JetBrains Mono', 'Consolas', monospace;
        font-size: 0.85rem; color: #0f172a; text-align: right;
    }
    .cs-table td.value.text { font-family: inherit; }
    .cs-table td.value.best { background: rgba(16,185,129,0.10); font-weight: 700; }
    .cs-note {
        color: #64748b; font-size: 0.78rem; margin: 12px 0 4px;
        border-left: 3px solid #10b981; padding: 4px 0 4px 12px; background: #f0fdf4;
    }
    .best-banner {
        background: linear-gradient(135deg, #10b981, #059669); color: #fff;
        border-radius: 12px; padding: 14px 20px; margin-bottom: 18px;
        display: flex; align-items: center; gap: 12px;
    }
    .best-banner i { font-size: 1.5rem; }
    .best-banner .lbl { text-transform: uppercase; letter-spacing: .06em; font-size: 0.7rem; }
    .best-banner .val { font-weight: 800; font-size: 1.1rem; }
    .disclaimer {
        color: #92400e; background: #fffbeb; border: 1px solid #fde68a;
        padding: 10px 14px; border-radius: 8px; font-size: 0.78rem; margin-top: 20px;
    }
</style>

<a href="index.php?page=planning" class="text-muted small text-decoration-none">
    <i class="bi bi-arrow-left me-1"></i>Back to planning
</a>

<div class="cs-hero mt-2">
    <span class="k">CROSS-SCHEME COMPARISON</span>
    <h4><?= e($proposal['proposal_name']) ?></h4>
    <p><i class="bi bi-building me-1"></i><?= e($proposal['region'] ?? '') ?><?= $proposal['city'] ? ', ' . e($proposal['city']) : '' ?>
       · <?= number_format((float)$proposal['plot_area_sqm'], 2) ?> sqm plot
       · <?= count($columns) ?> calculated plan<?= count($columns) === 1 ? '' : 's' ?>
    </p>
</div>

<?php
$bestPlan = $columns[$bestIdx] ?? null;
if ($bestPlan && count($columns) > 1):
    $bestBua = $bestPlan['metrics']['full_scheme_bua_sqm']['value'] ?? 0;
    $bestFsi = $bestPlan['metrics']['full_scheme_fsi']['value'] ?? 0;
?>
    <div class="best-banner">
        <i class="bi bi-trophy-fill"></i>
        <div>
            <div class="lbl">Best BUA opportunity</div>
            <div class="val">
                DCPR <?= e($bestPlan['plan']['regulation_key']) ?>
                &nbsp;·&nbsp; <?= number_format($bestBua, 2) ?> sqm total
                &nbsp;·&nbsp; FSI <?= number_format($bestFsi, 2) ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="cs-table">
    <table>
        <thead>
            <tr>
                <th style="min-width:240px;">Metric</th>
                <?php foreach ($columns as $i => $col):
                    $isBest = ($i === $bestIdx && count($columns) > 1);
                ?>
                    <th class="<?= $isBest ? 'best' : '' ?>" style="text-align:right;">
                        <span class="reg-badge">DCPR <?= e($col['plan']['regulation_key']) ?></span>
                        <span class="plan-name"><?= e($col['plan']['plan_name']) ?></span>
                        <span class="status-pill"><?= e(ucfirst($col['plan']['status'])) ?></span>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php
            // Iterate over metric keys in first column (all columns share the same shape)
            $metricKeys = array_keys($columns[0]['metrics']);
            foreach ($metricKeys as $mk):
                $anyText = isset($columns[0]['metrics'][$mk]['value_text']);
                // Find best value across columns for numeric metrics (highest owner_sale_bua or full_scheme_bua)
                $highlightBest = in_array($mk, ['owner_sale_bua_sqm', 'full_scheme_bua_sqm', 'full_scheme_fsi'], true);
                $bestVal = -INF; $bestValIdx = -1;
                if ($highlightBest) {
                    foreach ($columns as $i => $c) {
                        $v = $c['metrics'][$mk]['value'] ?? null;
                        if ($v !== null && $v > $bestVal) { $bestVal = $v; $bestValIdx = $i; }
                    }
                }
            ?>
                <tr>
                    <td class="metric-label"><?= e($columns[0]['metrics'][$mk]['label']) ?></td>
                    <?php foreach ($columns as $i => $col):
                        $m = $col['metrics'][$mk] ?? [];
                        $isBestCell = ($highlightBest && $i === $bestValIdx);
                        if ($anyText || isset($m['value_text'])) {
                            $display = $m['value_text'] ?? '—';
                            $cls = 'value text';
                        } else {
                            $v = $m['value'] ?? null;
                            $unit = $m['unit'] ?? '';
                            if ($v === null) { $display = '—'; }
                            elseif ($unit === 'ratio') { $display = number_format($v, 3); }
                            elseif ($unit === 'percent') { $display = number_format($v, 1) . '%'; }
                            else { $display = number_format($v, 2) . ($unit ? ' ' . $unit : ''); }
                            $cls = 'value';
                        }
                    ?>
                        <td class="<?= $cls ?><?= $isBestCell ? ' best' : '' ?>"><?= e($display) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="cs-note">
    <b>How to read:</b> Rows are normalized metrics; columns are calculated plans (one per scheme).
    Cells highlighted green mark the best plan on that metric (max BUA / max Sale / max FSI).
    Overall "Best BUA Opportunity" banner picks the plan with the highest Full Scheme BUA — that's usually the highest-value scheme,
    but confirm with cost/risk factors before final selection.
</div>

<div class="disclaimer">
    <b>Caveat:</b> Each scheme has different eligibility gates (MHADA lessee status, cessed-building certification, slum notification, road-width thresholds, etc.).
    The comparison shows theoretical BUA potential — the plan that fits YOUR proposal's actual conditions may not be the one with the largest BUA.
    Always cross-check the DCPR eligibility clauses (see the AI Regulatory Advisor on each plan) before committing.
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
