<?php
$pageTitle = 'Compare · v' . (int)$a['statement']['version_major'] . '.' . (int)$a['statement']['version_minor']
           . ' ↔ v' . (int)$b['statement']['version_major'] . '.' . (int)$b['statement']['version_minor'];
?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php
$fmtSqm = fn($v) => ($v === null || $v === '' ? '—' : number_format((float)$v, 2));
$fmtRatio = fn($v) => ($v === null || $v === '' ? '—' : number_format((float)$v, 3));

function delta_class($a, $b) {
    if ($a === null || $b === null) return '';
    $d = (float)$b - (float)$a;
    if (abs($d) < 0.01) return 'delta-eq';
    return $d > 0 ? 'delta-up' : 'delta-down';
}
function delta_val($a, $b, $format = 'num') {
    if ($a === null || $b === null) return '—';
    $d = (float)$b - (float)$a;
    if (abs($d) < 0.01) return '±0';
    $sign = $d > 0 ? '+' : '−';
    $abs = abs($d);
    return $sign . number_format($abs, $format === 'ratio' ? 3 : 2);
}

// Merge FSI keys from both statements
$fsiKeys = [];
foreach (array_merge($a['fsi'], $b['fsi']) as $r) $fsiKeys[$r['component_key']] = $r['component_label'];
$fsiAByKey = []; foreach ($a['fsi'] as $r) $fsiAByKey[$r['component_key']] = $r;
$fsiBByKey = []; foreach ($b['fsi'] as $r) $fsiBByKey[$r['component_key']] = $r;

$devKeys = [];
foreach (array_merge($a['dev'], $b['dev']) as $r) $devKeys[$r['component_key']] = $r['component_label'];
$devAByKey = []; foreach ($a['dev'] as $r) $devAByKey[$r['component_key']] = $r;
$devBByKey = []; foreach ($b['dev'] as $r) $devBByKey[$r['component_key']] = $r;
?>

<style>
    .cmp-hero {
        background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;
        border-radius: 14px; padding: 22px 26px; margin-bottom: 20px;
    }
    .cmp-hero h4 { font-weight: 800; margin: 0; }
    .cmp-hero p { color: #cbd5e1; margin: 4px 0 0; font-size: 0.82rem; }

    .cmp-cols { display: grid; grid-template-columns: 1fr 130px 1fr 120px; gap: 0; align-items: stretch; margin-bottom: 24px; }
    .cmp-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px 20px; margin-bottom: 16px; }
    .cmp-card h5 { font-weight: 700; margin: 0 0 12px; font-size: 0.9rem; color: #4338ca; text-transform: uppercase; letter-spacing:.05em; }

    .cmp-tbl { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
    .cmp-tbl th { background: #f8fafc; text-transform: uppercase; letter-spacing: .04em; font-size: 0.68rem;
                  color: #64748b; padding: 8px; border-bottom: 1px solid #e5e7eb; text-align: right; }
    .cmp-tbl th.left { text-align: left; }
    .cmp-tbl td { padding: 7px 8px; border-bottom: 1px solid #f1f5f9; }
    .cmp-tbl td.num { text-align: right; font-family: 'JetBrains Mono', Consolas, monospace; }
    .cmp-tbl td.delta { text-align: right; font-family: 'JetBrains Mono', Consolas, monospace; font-weight: 700; }
    .delta-up   { color: #059669; }
    .delta-down { color: #dc2626; }
    .delta-eq   { color: #94a3b8; }

    .ver-pill { display: inline-block; background: #eef2ff; color: #4338ca; padding: 3px 12px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
    .status-pill { padding: 2px 10px; border-radius: 999px; font-size: 0.68rem; font-weight: 700; }
    .status-pill.locked { background: #dcfce7; color: #065f46; }
    .status-pill.draft  { background: #fef3c7; color: #92400e; }
    .status-pill.calculated { background: #dbeafe; color: #1e40af; }
    .status-pill.superseded { background: #f1f5f9; color: #64748b; }
</style>

<a href="index.php?page=area_analysis" class="text-muted small text-decoration-none">
    <i class="bi bi-arrow-left me-1"></i>Back to Area Analysis
</a>

<div class="cmp-hero mt-2">
    <h4><i class="bi bi-arrow-left-right me-2"></i>Version Comparison</h4>
    <p><?= e($a['proposal']['proposal_name'] ?? '') ?> · Comparing two Area Statement versions</p>
</div>

<div class="cmp-card">
    <h5>Versions Under Review</h5>
    <table class="cmp-tbl">
        <thead><tr><th class="left">Version</th><th class="left">Status</th><th class="left">Regulation</th><th>Net Plot</th><th>Total FSI</th><th>Total BUA</th><th>Rehab</th><th>Sale</th></tr></thead>
        <tbody>
            <?php foreach ([['A', $a], ['B', $b]] as $pair): [$tag, $x] = $pair; $s = $x['statement']; ?>
                <tr>
                    <td><span class="ver-pill"><?= $tag ?> · v<?= (int)$s['version_major'] ?>.<?= (int)$s['version_minor'] ?></span></td>
                    <td><span class="status-pill <?= e($s['status']) ?>"><?= strtoupper(e($s['status'])) ?></span></td>
                    <td><b>DCPR <?= e($s['selected_regulation_key'] ?? '—') ?></b></td>
                    <td class="num"><?= $fmtSqm($s['net_plot_area_sqm']) ?> sqm</td>
                    <td class="num"><?= $fmtRatio($s['total_permissible_fsi']) ?></td>
                    <td class="num"><?= $fmtSqm($s['total_permissible_bua_sqm']) ?> sqm</td>
                    <td class="num"><?= $fmtSqm($s['rehab_bua_sqm']) ?> sqm</td>
                    <td class="num"><?= $fmtSqm($s['sale_bua_sqm']) ?> sqm</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- FSI comparison -->
<?php if (!empty($fsiKeys)): ?>
<div class="cmp-card">
    <h5>FSI Statement — deltas (B − A)</h5>
    <table class="cmp-tbl">
        <thead><tr><th class="left">Component</th><th>A · FSI</th><th>A · BUA</th><th>B · FSI</th><th>B · BUA</th><th>Δ FSI</th><th>Δ BUA</th></tr></thead>
        <tbody>
            <?php foreach ($fsiKeys as $key => $label): $ra = $fsiAByKey[$key] ?? null; $rb = $fsiBByKey[$key] ?? null;
                $af = $ra['fsi_ratio'] ?? null; $bf = $rb['fsi_ratio'] ?? null;
                $ab = $ra['bua_sqm']   ?? null; $bb = $rb['bua_sqm']   ?? null;
            ?>
                <tr>
                    <td><?= e($label) ?></td>
                    <td class="num"><?= $fmtRatio($af) ?></td>
                    <td class="num"><?= $fmtSqm($ab) ?></td>
                    <td class="num"><?= $fmtRatio($bf) ?></td>
                    <td class="num"><?= $fmtSqm($bb) ?></td>
                    <td class="delta <?= delta_class($af, $bf) ?>"><?= delta_val($af, $bf, 'ratio') ?></td>
                    <td class="delta <?= delta_class($ab, $bb) ?>"><?= delta_val($ab, $bb) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Dev comparison -->
<?php if (!empty($devKeys)): ?>
<div class="cmp-card">
    <h5>Development Statement — deltas (B − A)</h5>
    <table class="cmp-tbl">
        <thead><tr><th class="left">Component</th><th>A · Area</th><th>B · Area</th><th>Δ Area</th><th class="left">Handover</th></tr></thead>
        <tbody>
            <?php foreach ($devKeys as $key => $label): $ra = $devAByKey[$key] ?? null; $rb = $devBByKey[$key] ?? null;
                $aa = $ra['area_sqm'] ?? null; $ba = $rb['area_sqm'] ?? null;
            ?>
                <tr>
                    <td><?= e($label) ?></td>
                    <td class="num"><?= $fmtSqm($aa) ?></td>
                    <td class="num"><?= $fmtSqm($ba) ?></td>
                    <td class="delta <?= delta_class($aa, $ba) ?>"><?= delta_val($aa, $ba) ?></td>
                    <td><?= e($rb['handover_to'] ?? $ra['handover_to'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
