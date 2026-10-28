<?php $pageTitle = 'Compare Plans'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<style>
    .cmp-hero { background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff; border-radius: 14px; padding: 22px 26px; margin-bottom: 20px; }
    .cmp-hero h5 { font-weight: 700; margin: 0 0 4px; }
    .cmp-hero p  { margin: 0; color: #cbd5e1; font-size: 0.82rem; }

    .cmp-cols { display: grid; grid-template-columns: 1fr 40px 1fr; gap: 0; margin-bottom: 20px; }
    .cmp-col-head {
        background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 12px;
        padding: 14px 18px; text-align: center;
    }
    .cmp-col-head.base     { border-color: #cbd5e1; }
    .cmp-col-head.scenario { border-color: #a78bfa; background: #faf5ff; }
    .cmp-col-head .lbl     { color: #64748b; font-size: 0.68rem; letter-spacing: .1em; text-transform: uppercase; }
    .cmp-col-head .val     { color: #0f172a; font-weight: 700; margin-top: 4px; font-size: 0.92rem; }
    .cmp-col-head .status  {
        display: inline-block; margin-top: 4px; padding: 2px 8px; border-radius: 999px;
        font-size: 0.7rem; font-weight: 600;
    }
    .cmp-arrow {
        display: flex; align-items: center; justify-content: center;
        color: #94a3b8; font-size: 1.2rem;
    }

    .section {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
        padding: 16px 20px; margin-bottom: 18px;
    }
    .section h6 {
        font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
        font-size: 0.72rem; color: #0f172a; margin: 0 0 12px;
    }

    table.cmp { width: 100%; font-size: 0.82rem; border-collapse: collapse; }
    table.cmp th {
        background: #f8fafc; color: #64748b; font-weight: 600; text-transform: uppercase;
        font-size: 0.66rem; letter-spacing: .04em; padding: 8px 10px; text-align: right;
        border-bottom: 1px solid #e5e7eb;
    }
    table.cmp th:first-child { text-align: left; }
    table.cmp td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    table.cmp td.k { color: #0f172a; font-weight: 600; }
    table.cmp td.v {
        text-align: right; font-family: 'JetBrains Mono', 'Consolas', monospace; color: #0f172a;
    }
    table.cmp td.ref { color: #6366f1; font-size: 0.66rem; text-transform: uppercase; letter-spacing: .04em; }
    table.cmp .delta-pos { color: #059669; font-weight: 700; }
    table.cmp .delta-neg { color: #dc2626; font-weight: 700; }
    table.cmp .delta-zero { color: #94a3b8; }
    table.cmp tr.changed td.k { color: #4338ca; }
    table.cmp tr.changed { background: #faf5ff; }

    .val-flip {
        display: grid; grid-template-columns: 1fr 90px 90px auto;
        gap: 10px; padding: 8px 0; border-bottom: 1px dashed #f1f5f9; font-size: 0.82rem;
    }
    .val-flip .lb { color: #0f172a; font-weight: 600; }
    .val-flip .cite { color: #6366f1; font-size: 0.68rem; text-transform: uppercase; letter-spacing: .04em; margin-left: 6px; }
    .status-badge { padding: 2px 8px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; text-align: center; }
    .status-badge.red    { background: #fee2e2; color: #991b1b; }
    .status-badge.yellow { background: #fef3c7; color: #92400e; }
    .status-badge.green  { background: #dcfce7; color: #166534; }
    .status-badge.gone   { background: #f1f5f9; color: #64748b; }

    .status-plan-validated  { background: #dcfce7; color: #166534; }
    .status-plan-calculated { background: #dbeafe; color: #1e40af; }
    .status-plan-draft      { background: #fef3c7; color: #92400e; }
</style>

<a href="index.php?page=planning" class="text-muted small text-decoration-none">
    <i class="bi bi-arrow-left me-1"></i>Back to planning
</a>

<div class="cmp-hero mt-2">
    <h5><i class="bi bi-arrow-left-right me-2"></i>What-If Scenario Comparison</h5>
    <p>Proposal: <b><?= e($proposal['proposal_name']) ?></b> · DCPR <?= e($basePlan['regulation_key']) ?></p>
</div>

<div class="cmp-cols">
    <div class="cmp-col-head base">
        <div class="lbl">Base Plan</div>
        <div class="val"><?= e($basePlan['plan_name']) ?></div>
        <span class="status status-plan-<?= e($basePlan['status']) ?>"><?= e(ucfirst($basePlan['status'])) ?></span>
    </div>
    <div class="cmp-arrow"><i class="bi bi-arrow-right"></i></div>
    <div class="cmp-col-head scenario">
        <div class="lbl">Scenario<?= $scenPlan['scenario_label'] ? ' — ' . e($scenPlan['scenario_label']) : '' ?></div>
        <div class="val"><?= e($scenPlan['plan_name']) ?></div>
        <span class="status status-plan-<?= e($scenPlan['status']) ?>"><?= e(ucfirst($scenPlan['status'])) ?></span>
    </div>
</div>

<?php
function fmt_num($v, int $decimals = 2): string {
    if ($v === null || $v === '') return '—';
    if (!is_numeric($v)) return htmlspecialchars((string)$v);
    return number_format((float)$v, $decimals);
}
function delta_html($a, $b, int $decimals = 2, string $unit = ''): string {
    if (!is_numeric($a) || !is_numeric($b)) return '<span class="delta-zero">—</span>';
    $d = (float)$b - (float)$a;
    if (abs($d) < 0.0001) return '<span class="delta-zero">·</span>';
    $sign = $d > 0 ? '+' : '';
    $class = $d > 0 ? 'delta-pos' : 'delta-neg';
    $pct = ((float)$a != 0) ? sprintf(' (%s%.1f%%)', $sign, ($d / (float)$a) * 100) : '';
    return "<span class=\"{$class}\">{$sign}" . number_format($d, $decimals) . ($unit ? ' ' . $unit : '') . $pct . '</span>';
}
$inputKeys = array_unique(array_merge(array_keys($baseInputs), array_keys($scenInputs)));
sort($inputKeys);
$changedInputs = array_filter($inputKeys, fn($k) => ($baseInputs[$k] ?? '') !== ($scenInputs[$k] ?? ''));
?>

<div class="section">
    <h6><i class="bi bi-sliders me-1"></i>Inputs — Changed<?= empty($changedInputs) ? ' (none — inputs are identical)' : ' (' . count($changedInputs) . ')' ?></h6>
    <?php if (!empty($changedInputs)): ?>
        <table class="cmp">
            <thead><tr><th>Input</th><th>Base</th><th>Scenario</th><th>Delta</th></tr></thead>
            <tbody>
                <?php foreach ($changedInputs as $k): ?>
                    <tr class="changed">
                        <td class="k"><?= e($k) ?></td>
                        <td class="v"><?= e($baseInputs[$k] ?? '(unset)') ?></td>
                        <td class="v"><?= e($scenInputs[$k] ?? '(unset)') ?></td>
                        <td class="v"><?= delta_html($baseInputs[$k] ?? null, $scenInputs[$k] ?? null) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
// Compare calcs. Show changed rows first, then unchanged (collapsed group).
$calcKeys = array_unique(array_merge(array_keys($baseCalcs), array_keys($scenCalcs)));
$grouped = [];
foreach ($calcKeys as $k) {
    $b = $baseCalcs[$k]['calc_value'] ?? null;
    $s = $scenCalcs[$k]['calc_value'] ?? null;
    $row = $baseCalcs[$k] ?? $scenCalcs[$k]; // for label/unit/ref
    $group = $row['calc_group'] ?? 'other';
    $grouped[$group][] = [
        'key'   => $k,
        'label' => $row['calc_label'] ?? $k,
        'unit'  => $row['unit'] ?? '',
        'ref'   => $row['regulation_ref'] ?? '',
        'base'  => $b,
        'scen'  => $s,
        'changed' => is_numeric($b) && is_numeric($s) ? abs((float)$b - (float)$s) > 0.0001 : ($b !== $s),
    ];
}
$groupTitles = [
    'land'     => 'Land Breakdown',
    'rehab'    => 'Rehabilitation Component',
    'sale'     => 'Sale Component',
    'fsi'      => 'FSI',
    'totals'   => 'Totals',
    'fungible' => 'Fungible',
    'tdr'      => 'TDR',
];
?>

<?php foreach ($groupTitles as $g => $title): ?>
    <?php if (empty($grouped[$g])) continue; ?>
    <div class="section">
        <h6><i class="bi bi-graph-up me-1"></i><?= e($title) ?></h6>
        <table class="cmp">
            <thead>
                <tr><th>Parameter</th><th>Base</th><th>Scenario</th><th>Δ (abs · %)</th><th>Regulation</th></tr>
            </thead>
            <tbody>
                <?php
                usort($grouped[$g], fn($a, $b) => (int)$b['changed'] - (int)$a['changed']);
                foreach ($grouped[$g] as $r):
                    $isRatio = in_array($r['unit'], ['ratio', 'per ha'], true);
                    $dec = $isRatio ? 3 : 2;
                ?>
                    <tr class="<?= $r['changed'] ? 'changed' : '' ?>">
                        <td class="k"><?= e($r['label']) ?></td>
                        <td class="v"><?= is_numeric($r['base']) ? fmt_num($r['base'], $dec) : '—' ?> <?= e($r['unit']) ?></td>
                        <td class="v"><?= is_numeric($r['scen']) ? fmt_num($r['scen'], $dec) : '—' ?> <?= e($r['unit']) ?></td>
                        <td class="v"><?= delta_html($r['base'], $r['scen'], $dec, $r['unit']) ?></td>
                        <td class="ref"><?= e($r['ref']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endforeach; ?>

<?php
// Validation flips: checks where base status differs from scenario status
$checkKeys = array_unique(array_merge(array_keys($baseVals), array_keys($scenVals)));
$flips = [];
foreach ($checkKeys as $k) {
    $b = $baseVals[$k]['status'] ?? 'gone';
    $s = $scenVals[$k]['status'] ?? 'gone';
    if ($b !== $s) {
        $flips[] = [
            'key'    => $k,
            'label'  => $baseVals[$k]['check_label'] ?? $scenVals[$k]['check_label'] ?? $k,
            'ref'    => $baseVals[$k]['regulation_ref'] ?? $scenVals[$k]['regulation_ref'] ?? '',
            'from'   => $b,
            'to'     => $s,
        ];
    }
}
?>

<?php if (!empty($flips)): ?>
<div class="section">
    <h6><i class="bi bi-shuffle me-1"></i>Validation Flips — checks that changed status (<?= count($flips) ?>)</h6>
    <?php foreach ($flips as $f): ?>
        <div class="val-flip">
            <div>
                <span class="lb"><?= e($f['label']) ?></span>
                <?php if ($f['ref']): ?><span class="cite"><?= e($f['ref']) ?></span><?php endif; ?>
            </div>
            <div class="status-badge <?= e($f['from']) ?>"><?= e(ucfirst($f['from'])) ?></div>
            <div class="status-badge <?= e($f['to']) ?>"><?= e(ucfirst($f['to'])) ?></div>
            <div style="color:#64748b;font-size:0.72rem">
                <?php if ($f['from'] === 'green' && $f['to'] !== 'green'): ?>Regressed
                <?php elseif ($f['from'] !== 'green' && $f['to'] === 'green'): ?>Improved
                <?php else: ?>Changed<?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="d-flex gap-2 mb-4">
    <a href="index.php?page=planning_view&plan_id=<?= (int)$basePlan['id'] ?>" class="btn btn-outline-secondary btn-sm">View Base Plan</a>
    <a href="index.php?page=planning_view&plan_id=<?= (int)$scenPlan['id'] ?>" class="btn btn-outline-primary btn-sm">View Scenario</a>
    <a href="index.php?page=planning_form&plan_id=<?= (int)$scenPlan['id'] ?>" class="btn btn-outline-primary btn-sm">Edit Scenario Inputs</a>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
