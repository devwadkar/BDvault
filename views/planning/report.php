<?php
// Print-optimized feasibility report — bypasses the app chrome (no sidebar/navbar).
// User hits "Print / Save as PDF" → browser handles the export.

function calc_by_key_r(array $calcs, string $key): ?array {
    foreach ($calcs as $c) if ($c['calc_key'] === $key) return $c;
    return null;
}
function calc_v_r(array $calcs, string $key, int $decimals = 2, string $unit = ''): string {
    $c = calc_by_key_r($calcs, $key);
    if (!$c) return '—';
    $v = (float)$c['calc_value'];
    $s = number_format($v, $decimals);
    return $unit ? ($s . ' ' . $unit) : $s;
}

$reportGenerated = date('d M Y · H:i');
$appliedRegulation = 'DCPR ' . $plan['regulation_key'] . ' — ' . ($plan['regulation_key'] === '33(10)' ? 'Slum Rehabilitation (SRA)' : '');

$overallStatus = 'COMPLIANT';
$overallColor  = '#16a34a';
if (($validationCounts['red'] ?? 0) > 0)    { $overallStatus = 'NON-COMPLIANT';   $overallColor = '#dc2626'; }
elseif (($validationCounts['yellow'] ?? 0) > 0) { $overallStatus = 'REVIEW REQUIRED'; $overallColor = '#f59e0b'; }

// Chart data (re-computed here for the report)
$land_gross_val = (float)(calc_by_key_r($calcs, 'gross_plot_area_sqm')['calc_value'] ?? 0);
$netM   = (float)(calc_by_key_r($calcs, 'net_plot_area_sqm')['calc_value'] ?? 0);
$land_ros = (float)(calc_by_key_r($calcs, 'ros_physical_set_aside_sqm')['calc_value'] ?? 0);

$land_segments = [
    ['label' => 'DP Road (Primary)',   'value' => (float)(calc_by_key_r($calcs, 'dp_road_setback_primary_sqm')['calc_value']   ?? 0), 'color' => '#ef4444'],
    ['label' => 'DP Road (Secondary)', 'value' => (float)(calc_by_key_r($calcs, 'dp_road_setback_secondary_sqm')['calc_value'] ?? 0), 'color' => '#f97316'],
    ['label' => 'Nalla Setback',       'value' => (float)(calc_by_key_r($calcs, 'nalla_setback_sqm')['calc_value']              ?? 0), 'color' => '#f59e0b'],
    ['label' => 'DP Reservation',      'value' => (float)(calc_by_key_r($calcs, 'reservation_area_other_sqm')['calc_value']    ?? 0), 'color' => '#a855f7'],
    ['label' => 'Amenity Plot',        'value' => (float)(calc_by_key_r($calcs, 'amenity_plot_sqm')['calc_value']              ?? 0), 'color' => '#ec4899'],
    ['label' => 'ROS (not deducted)',  'value' => $land_ros,                                                                          'color' => '#22c55e'],
    ['label' => 'Net Plot (for FSI)',  'value' => $netM,                                                                              'color' => '#0ea5e9'],
];
$land_segments = array_values(array_filter($land_segments, fn($s) => $s['value'] > 0));
foreach ($land_segments as &$s) {
    $s['pct'] = $land_gross_val > 0 ? round($s['value'] / $land_gross_val * 100, 2) : 0;
}
unset($s);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Feasibility Report — <?= e($plan['plan_name']) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<style>
    * { box-sizing: border-box; font-family: 'Lexend', system-ui, -apple-system, sans-serif; }
    html, body { margin: 0; padding: 0; background: #eef2f7; color: #0f172a; font-size: 12px; }
    .action-bar {
        position: sticky; top: 0; z-index: 100;
        background: #0f172a; color: #fff; padding: 12px 24px;
        display: flex; align-items: center; justify-content: space-between;
    }
    .action-bar .lft { display: flex; align-items: center; gap: 12px; }
    .action-bar .lft b { font-weight: 700; }
    .action-bar a, .action-bar button {
        background: #4f46e5; color: #fff; border: 0; padding: 8px 18px; border-radius: 8px;
        font-size: 0.82rem; font-weight: 600; text-decoration: none; cursor: pointer;
    }
    .action-bar a.back { background: rgba(255,255,255,0.12); }
    .action-bar a:hover, .action-bar button:hover { background: #4338ca; }

    .page {
        max-width: 900px; margin: 20px auto; background: #fff;
        padding: 40px 48px; border-radius: 6px; box-shadow: 0 6px 24px rgba(15,23,42,0.08);
    }

    /* Cover header */
    .cover {
        display: flex; align-items: flex-start; justify-content: space-between; gap: 20px;
        padding-bottom: 20px; border-bottom: 3px solid #0f172a;
    }
    .cover .brand { font-weight: 800; font-size: 1.5rem; letter-spacing: -0.02em; color: #0f172a; }
    .cover .brand span { color: #4f46e5; }
    .cover .sub   { color: #64748b; font-size: 0.78rem; letter-spacing: .04em; text-transform: uppercase; margin-top: 2px; }
    .cover .meta  { text-align: right; font-size: 0.72rem; color: #64748b; }
    .cover .meta b { display: block; color: #0f172a; font-size: 0.86rem; margin-bottom: 2px; }

    h1 { font-size: 1.6rem; font-weight: 800; margin: 24px 0 4px; letter-spacing: -0.02em; }
    h2 {
        font-size: 0.85rem; text-transform: uppercase; letter-spacing: .1em;
        color: #0f172a; font-weight: 700; margin: 22px 0 10px; padding-bottom: 6px;
        border-bottom: 2px solid #4f46e5;
    }
    h2 .num { color: #4f46e5; margin-right: 8px; }
    p.lead { color: #64748b; font-size: 0.86rem; margin: 0 0 20px; }

    /* Overall compliance banner */
    .banner {
        display: flex; align-items: center; justify-content: space-between; gap: 16px;
        padding: 14px 20px; border-radius: 10px; margin: 20px 0;
        color: #fff; font-weight: 700;
    }
    .banner .label { font-size: 0.75rem; letter-spacing: .08em; opacity: 0.8; text-transform: uppercase; }
    .banner .status { font-size: 1.4rem; letter-spacing: -0.01em; }
    .banner .counts { display: flex; gap: 8px; }
    .banner .pill {
        background: rgba(255,255,255,0.22); padding: 4px 12px; border-radius: 999px;
        font-size: 0.78rem;
    }

    /* Tables */
    table.kv {
        width: 100%; border-collapse: collapse; font-size: 0.82rem;
    }
    table.kv td { padding: 6px 8px; vertical-align: top; border-bottom: 1px solid #f1f5f9; }
    table.kv td.k { color: #64748b; width: 42%; }
    table.kv td.v { color: #0f172a; font-weight: 600; }

    table.calc { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
    table.calc th {
        background: #f8fafc; color: #64748b; font-weight: 600; text-align: left;
        padding: 8px 10px; text-transform: uppercase; font-size: 0.66rem; letter-spacing: .04em;
        border-bottom: 1px solid #e5e7eb;
    }
    table.calc td { padding: 6px 10px; vertical-align: top; border-bottom: 1px solid #f1f5f9; }
    table.calc td.v { text-align: right; font-family: 'JetBrains Mono', 'Consolas', monospace; }
    table.calc td.ref { color: #6366f1; font-size: 0.66rem; text-transform: uppercase; letter-spacing: .04em; }
    table.calc td .formula { font-family: 'JetBrains Mono', 'Consolas', monospace; font-size: 0.68rem; color: #94a3b8; margin-top: 2px; }

    /* Summary grid on final page */
    .summary-grid {
        display: grid; grid-template-columns: repeat(2, 1fr); gap: 0;
        border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden;
    }
    .summary-grid .cell {
        padding: 14px 18px; border-right: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9;
    }
    .summary-grid .cell:nth-child(2n) { border-right: 0; }
    .summary-grid .cell .lbl { color: #64748b; font-size: 0.72rem; text-transform: uppercase; letter-spacing: .05em; }
    .summary-grid .cell .val { color: #0f172a; font-weight: 700; font-size: 1.05rem; margin-top: 4px; }

    /* Validation list */
    .v-row {
        display: grid; grid-template-columns: 28px 1fr auto; gap: 10px; padding: 8px 0;
        border-bottom: 1px dashed #f1f5f9;
    }
    .v-icon { width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 0.7rem; }
    .v-icon.red    { background: #dc2626; }
    .v-icon.yellow { background: #f59e0b; }
    .v-icon.green  { background: #16a34a; }
    .v-label { font-weight: 600; font-size: 0.82rem; }
    .v-meta  { color: #64748b; font-size: 0.72rem; margin-top: 2px; }
    .v-cite  { color: #6366f1; font-size: 0.66rem; text-transform: uppercase; letter-spacing: .04em; margin-right: 6px; }
    .v-nums  { text-align: right; font-family: 'JetBrains Mono', 'Consolas', monospace; font-size: 0.72rem; color: #334155; min-width: 130px; }
    .v-action { grid-column: 2 / span 2; font-size: 0.72rem; color: #475569; background: #fffbeb; border-left: 3px solid #f59e0b; padding: 4px 8px; border-radius: 3px; margin-top: 4px; }

    /* Charts */
    .charts-row { display: grid; grid-template-columns: 300px 1fr; gap: 20px; align-items: stretch; margin: 12px 0; }
    .chart-wrap { position: relative; height: 240px; }
    .chart-wrap canvas { position: absolute; inset: 0; width: 100% !important; height: 100% !important; }
    .legend {
        display: grid; grid-template-columns: 1fr auto auto; gap: 4px 12px;
        font-size: 0.74rem; align-content: start;
    }
    .legend .lb { display: flex; align-items: center; gap: 6px; color: #334155; }
    .legend .dt { width: 9px; height: 9px; border-radius: 3px; }
    .legend .vl { font-family: 'JetBrains Mono', 'Consolas', monospace; color: #0f172a; text-align: right; }
    .legend .pc { font-family: 'JetBrains Mono', 'Consolas', monospace; color: #64748b; text-align: right; }
    .legend .hd { color: #94a3b8; font-size: 0.62rem; text-transform: uppercase; letter-spacing: .06em; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; }

    footer.report-foot {
        margin-top: 32px; padding-top: 14px; border-top: 1px solid #e5e7eb;
        display: flex; justify-content: space-between; font-size: 0.7rem; color: #94a3b8;
    }

    /* Print rules */
    @page { size: A4; margin: 12mm 12mm; }
    @media print {
        html, body { background: #fff; }
        .action-bar { display: none !important; }
        .page {
            margin: 0; box-shadow: none; border-radius: 0; padding: 0;
            max-width: 100%;
        }
        h2 { page-break-after: avoid; }
        table, .v-row, .charts-row, .summary-grid { page-break-inside: avoid; }
        .pb { page-break-before: always; }
    }
</style>
</head>
<body>

<div class="action-bar">
    <div class="lft">
        <b>Feasibility Report</b>
        <span style="color:#cbd5e1"><?= e($plan['plan_name']) ?></span>
    </div>
    <div>
        <a class="back" href="index.php?page=planning_view&plan_id=<?= (int)$plan['id'] ?>">
            <i class="bi bi-arrow-left"></i> Back to Plan
        </a>
        <button onclick="window.print()">
            <i class="bi bi-printer-fill"></i> Print / Save as PDF
        </button>
    </div>
</div>

<div class="page">

    <!-- COVER -->
    <div class="cover">
        <div>
            <div class="brand">BD<span>Vault</span></div>
            <div class="sub">Post-Proposal Development Planning Report</div>
        </div>
        <div class="meta">
            <b>Feasibility Report</b>
            Generated: <?= e($reportGenerated) ?><br>
            Plan v<?= (int)$plan['version'] ?> · Status: <?= e(ucfirst($plan['status'])) ?><br>
            Prepared by: <?= e($currentUser['full_name'] ?? '—') ?>
        </div>
    </div>

    <h1><?= e($proposal['proposal_name']) ?></h1>
    <p class="lead"><?= e($appliedRegulation) ?></p>

    <!-- Overall banner -->
    <div class="banner" style="background: <?= e($overallColor) ?>;">
        <div>
            <div class="label">Overall Regulatory Status</div>
            <div class="status"><?= e($overallStatus) ?></div>
        </div>
        <div class="counts">
            <span class="pill">🔴 <?= (int)$validationCounts['red'] ?> blockers</span>
            <span class="pill">🟡 <?= (int)$validationCounts['yellow'] ?> warnings</span>
            <span class="pill">🟢 <?= (int)$validationCounts['green'] ?> passed</span>
        </div>
    </div>

    <!-- 1. Project Information -->
    <h2><span class="num">1.</span>Project Information</h2>
    <table class="kv">
        <tr><td class="k">Proposal Name</td>            <td class="v"><?= e($proposal['proposal_name']) ?></td></tr>
        <tr><td class="k">CTS Number</td>               <td class="v"><?= e($inputs['cts_number'] ?? '—') ?></td></tr>
        <tr><td class="k">Village</td>                  <td class="v"><?= e($inputs['village']    ?? '—') ?></td></tr>
        <tr><td class="k">Ward</td>                     <td class="v"><?= e($inputs['ward']       ?? '—') ?></td></tr>
        <tr><td class="k">Location</td>                 <td class="v"><?= e($inputs['location']   ?? '—') ?></td></tr>
        <tr><td class="k">Site Address</td>             <td class="v"><?= e($inputs['site_address'] ?? '—') ?></td></tr>
        <tr><td class="k">Pincode</td>                  <td class="v"><?= e($inputs['pincode']    ?? '—') ?></td></tr>
        <tr><td class="k">Development Zone</td>         <td class="v"><?= e($inputs['development_zone'] ?? '—') ?></td></tr>
        <tr><td class="k">Applied Regulation</td>       <td class="v">DCPR <?= e($plan['regulation_key']) ?></td></tr>
        <tr><td class="k">Plan Prepared</td>            <td class="v"><?= e($plan['calculated_at'] ?? $plan['created_at']) ?></td></tr>
    </table>

    <!-- 2. Site Parameters -->
    <h2><span class="num">2.</span>Site Parameters</h2>
    <table class="kv">
        <tr><td class="k">Gross Plot Area</td>          <td class="v"><?= number_format($land_gross_val, 2) ?> sqm  &middot;  <?= number_format($land_gross_val * 10.7639, 2) ?> sqft</td></tr>
        <tr><td class="k">Primary Road Width</td>       <td class="v"><?= e($inputs['primary_road_width_m']    ?? '—') ?> m</td></tr>
        <tr><td class="k">Secondary Road Width</td>     <td class="v"><?= e($inputs['secondary_road_width_m']  ?? '—') ?> m</td></tr>
        <tr><td class="k">Existing DP Road Width</td>   <td class="v"><?= e($inputs['existing_dp_road_width_m']?? '—') ?> m</td></tr>
        <tr><td class="k">Proposed DP Road Width</td>   <td class="v"><?= e($inputs['proposed_dp_road_width_m']?? '—') ?> m</td></tr>
        <tr><td class="k">Existing Land Use</td>        <td class="v"><?= e($inputs['existing_land_use']       ?? '—') ?></td></tr>
        <tr><td class="k">Proposed Land Use</td>        <td class="v"><?= e($inputs['proposed_land_use']       ?? '—') ?></td></tr>
        <tr><td class="k">Existing Buildings</td>       <td class="v"><?= e($inputs['number_of_buildings_existing'] ?? '—') ?></td></tr>
    </table>

    <!-- 3. Land Deduction Statement -->
    <h2><span class="num">3.</span>Land Deduction Statement</h2>
    <div class="charts-row">
        <div class="chart-wrap"><canvas id="rptLandDonut"></canvas></div>
        <div class="legend">
            <div class="hd">Segment</div>
            <div class="hd vl">sqm</div>
            <div class="hd pc">% of gross</div>
            <?php foreach ($land_segments as $s): ?>
                <div class="lb"><span class="dt" style="background:<?= e($s['color']) ?>"></span><?= e($s['label']) ?></div>
                <div class="vl"><?= number_format($s['value'], 2) ?></div>
                <div class="pc"><?= number_format($s['pct'], 2) ?>%</div>
            <?php endforeach; ?>
        </div>
    </div>

    <table class="calc">
        <thead><tr><th>Component</th><th style="text-align:right">Area (sqm)</th><th style="text-align:right">Area (sqft)</th><th style="text-align:right">% of Gross</th><th>Regulation</th></tr></thead>
        <tbody>
            <?php
            $rows = [
                ['Gross Plot Area',                     $land_gross_val,                                                                       'Reg 30(A)(1)'],
                ['Primary DP Road Setback Deduction',    (float)(calc_by_key_r($calcs, 'dp_road_setback_primary_sqm')['calc_value']   ?? 0), 'Reg 30(A)(2)(a)'],
                ['Secondary DP Road Setback Deduction',  (float)(calc_by_key_r($calcs, 'dp_road_setback_secondary_sqm')['calc_value'] ?? 0), 'Reg 30(A)(2)(a)'],
                ['Nalla / Storm Water Setback',          (float)(calc_by_key_r($calcs, 'nalla_setback_sqm')['calc_value']              ?? 0), 'Reg 30(A)(2)(a)'],
                ['DP Reservation (non-ROS)',             (float)(calc_by_key_r($calcs, 'reservation_area_other_sqm')['calc_value']    ?? 0), 'Reg 30(A)(2)(a)'],
                ['Amenity Plot',                         (float)(calc_by_key_r($calcs, 'amenity_plot_sqm')['calc_value']              ?? 0), 'Reg 14(A)'],
                ['Other Deductions',                     (float)(calc_by_key_r($calcs, 'other_deduction_sqm')['calc_value']           ?? 0), 'Reg 30(A)(2)'],
                ['ROS / Green Belt (not deducted)',      $land_ros,                                                                             'Reg 30(A) Note 1'],
                ['Total Deductions',                     (float)(calc_by_key_r($calcs, 'total_deductions_sqm')['calc_value']          ?? 0), 'Reg 30(A)(2)'],
                ['Net Plot Area (for FSI)',              $netM,                                                                                 'Reg 30(A)(2)'],
            ];
            foreach ($rows as $r):
                $areaSqm = (float)$r[1];
                $pct = $land_gross_val > 0 ? ($areaSqm / $land_gross_val) * 100 : 0;
            ?>
                <tr>
                    <td><?= e($r[0]) ?></td>
                    <td class="v"><?= number_format($areaSqm, 2) ?></td>
                    <td class="v"><?= number_format($areaSqm * 10.7639, 2) ?></td>
                    <td class="v"><?= number_format($pct, 2) ?>%</td>
                    <td class="ref"><?= e($r[2]) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- 4. Development Potential -->
    <h2><span class="num">4.</span>Development Potential (FSI &amp; BUA)</h2>
    <table class="calc">
        <thead><tr><th>Parameter</th><th style="text-align:right">Value</th><th>Formula</th><th>Regulation</th></tr></thead>
        <tbody>
            <?php
            $devKeys = [
                'fsi_sanctioned', 'fsi_consumed_in_situ', 'in_situ_bua_cap_sqm',
                'total_bua_approved_sqm', 'total_bua_proposed_sqm',
            ];
            foreach ($devKeys as $k):
                $c = calc_by_key_r($calcs, $k);
                if (!$c) continue;
                $isRatio = in_array($c['unit'], ['ratio', 'per ha'], true);
            ?>
                <tr>
                    <td><?= e($c['calc_label']) ?></td>
                    <td class="v"><?= number_format((float)$c['calc_value'], $isRatio ? 3 : 2) ?> <?= e($c['unit']) ?></td>
                    <td><span class="formula"><?= e($c['formula']) ?></span></td>
                    <td class="ref"><?= e($c['regulation_ref']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- 5. Rehabilitation Component -->
    <h2><span class="num">5.</span>Rehabilitation Component</h2>
    <table class="calc">
        <thead><tr><th>Parameter</th><th style="text-align:right">Value</th><th>Formula</th><th>Regulation</th></tr></thead>
        <tbody>
            <?php foreach ($grouped['rehab'] ?? [] as $c): $isRatio = in_array($c['unit'], ['ratio'], true); ?>
                <tr>
                    <td><?= e($c['calc_label']) ?></td>
                    <td class="v"><?= number_format((float)$c['calc_value'], $isRatio ? 3 : 2) ?> <?= e($c['unit']) ?></td>
                    <td><span class="formula"><?= e($c['formula']) ?></span></td>
                    <td class="ref"><?= e($c['regulation_ref']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- 6. Sale Component -->
    <h2><span class="num">6.</span>Sale Component &amp; Fungible</h2>
    <table class="calc">
        <thead><tr><th>Parameter</th><th style="text-align:right">Value</th><th>Formula</th><th>Regulation</th></tr></thead>
        <tbody>
            <?php foreach (array_merge($grouped['sale'] ?? [], $grouped['fungible'] ?? [], $grouped['tdr'] ?? []) as $c):
                $isRatio = in_array($c['unit'], ['ratio', 'percent'], true); ?>
                <tr>
                    <td><?= e($c['calc_label']) ?></td>
                    <td class="v"><?= number_format((float)$c['calc_value'], $isRatio ? 3 : 2) ?> <?= e($c['unit']) ?></td>
                    <td><span class="formula"><?= e($c['formula']) ?></span></td>
                    <td class="ref"><?= e($c['regulation_ref']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- 7. Compliance Report -->
    <h2 class="pb"><span class="num">7.</span>Regulatory Compliance</h2>
    <?php if (empty($validations)): ?>
        <p class="lead">No validations run yet.</p>
    <?php else: ?>
        <?php foreach ($validations as $v):
            $icon = $v['status'] === 'red' ? '✕' : ($v['status'] === 'yellow' ? '!' : '✓');
            $isFail = $v['status'] !== 'green';
        ?>
            <div class="v-row">
                <div class="v-icon <?= e($v['status']) ?>"><?= e($icon) ?></div>
                <div>
                    <div class="v-label"><?= e($v['check_label']) ?></div>
                    <div class="v-meta">
                        <?php if (!empty($v['regulation_ref'])): ?>
                            <span class="v-cite"><?= e($v['regulation_ref']) ?></span>
                        <?php endif; ?>
                        <?= e($v['message']) ?>
                    </div>
                    <?php if ($isFail && !empty($v['recommended_action'])): ?>
                        <div class="v-action"><strong>Recommended:</strong> <?= e($v['recommended_action']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="v-nums">
                    <?php if ($v['entered_value']): ?>Entered: <b><?= e($v['entered_value']) ?></b><br><?php endif; ?>
                    <?php if ($v['required_value']): ?>Required: <b><?= e($v['required_value']) ?></b><br><?php endif; ?>
                    <?php if ($v['delta']): ?>Δ <?= e($v['delta']) ?><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- 8. Final Summary -->
    <h2 class="pb"><span class="num">8.</span>Executive Summary</h2>
    <div class="summary-grid">
        <div class="cell"><div class="lbl">Gross Plot</div><div class="val"><?= calc_v_r($calcs, 'gross_plot_area_sqm', 2, 'sqm') ?></div></div>
        <div class="cell"><div class="lbl">Net Plot for FSI</div><div class="val"><?= calc_v_r($calcs, 'net_plot_area_sqm', 2, 'sqm') ?></div></div>
        <div class="cell"><div class="lbl">Rehabilitation BUA</div><div class="val"><?= calc_v_r($calcs, 'rehab_tenement_bua_sqm', 2, 'sqm') ?></div></div>
        <div class="cell"><div class="lbl">Sale BUA (base)</div><div class="val"><?= calc_v_r($calcs, 'sale_component_sqm', 2, 'sqm') ?></div></div>
        <div class="cell"><div class="lbl">Sale Fungible</div><div class="val"><?= calc_v_r($calcs, 'sale_fungible_sqm', 2, 'sqm') ?></div></div>
        <div class="cell"><div class="lbl">Gross Sale Permissible</div><div class="val"><?= calc_v_r($calcs, 'gross_sale_bua_permissible_sqm', 2, 'sqm') ?></div></div>
        <div class="cell"><div class="lbl">Total BUA Approved</div><div class="val"><?= calc_v_r($calcs, 'total_bua_approved_sqm', 2, 'sqm') ?></div></div>
        <div class="cell"><div class="lbl">Balance Sale (as TDR)</div><div class="val"><?= calc_v_r($calcs, 'balance_sale_bua_tdr_sqm', 2, 'sqm') ?></div></div>
        <div class="cell"><div class="lbl">FSI Sanctioned</div><div class="val"><?= calc_v_r($calcs, 'fsi_sanctioned', 3) ?></div></div>
        <div class="cell"><div class="lbl">FSI Consumed (in-situ)</div><div class="val"><?= calc_v_r($calcs, 'fsi_consumed_in_situ', 3) ?></div></div>
        <div class="cell" style="grid-column: 1 / -1;">
            <div class="lbl">Overall Regulatory Status</div>
            <div class="val" style="color: <?= e($overallColor) ?>;"><?= e($overallStatus) ?></div>
        </div>
    </div>

    <?php if (!empty($hasFinancials)):
        $inrCr = fn($v) => number_format(((float)$v) / 1e7, 2);
        $fmt   = fn($v, $d=2) => number_format((float)$v, $d);
        $pct   = fn($v) => ($v === null ? '—' : number_format((float)$v, 2) . '%');
    ?>

    <!-- 9. Financial Summary -->
    <h2 class="pb"><span class="num">9.</span>Financial Model Summary
        <?php if (!empty($financialLabel)): ?>
            <span style="font-size: 0.65rem; color:#64748b; margin-left:8px;">Scenario: <?= e($financialLabel) ?></span>
        <?php endif; ?>
    </h2>
    <div class="summary-grid">
        <div class="cell"><div class="lbl">Total Revenue</div><div class="val">₹<?= $inrCr($financialSummary['total_revenue_inr'] ?? 0) ?> Cr</div></div>
        <div class="cell"><div class="lbl">Total Cost</div><div class="val">₹<?= $inrCr($financialSummary['total_cost_inr'] ?? 0) ?> Cr</div></div>
        <div class="cell"><div class="lbl">NP before Interest</div><div class="val">₹<?= $inrCr($financialSummary['net_profit_before_interest_inr'] ?? 0) ?> Cr</div></div>
        <div class="cell"><div class="lbl">Interest Cost</div><div class="val">₹<?= $inrCr($financialSummary['total_interest_inr'] ?? 0) ?> Cr</div></div>
        <div class="cell"><div class="lbl">NP after Interest</div><div class="val">₹<?= $inrCr($financialSummary['net_profit_after_interest_inr'] ?? 0) ?> Cr</div></div>
        <div class="cell"><div class="lbl">NP Margin</div><div class="val"><?= $pct($financialSummary['net_profit_margin_pct'] ?? null) ?></div></div>
        <div class="cell"><div class="lbl">Project IRR</div><div class="val" style="color:#059669;"><?= $pct($financialSummary['project_irr_pct'] ?? null) ?></div></div>
        <div class="cell"><div class="lbl">Equity IRR</div><div class="val" style="color:#059669;"><?= $pct($financialSummary['equity_irr_pct'] ?? null) ?></div></div>
        <div class="cell"><div class="lbl">Breakeven Price</div><div class="val">₹<?= $fmt($financialSummary['breakeven_price_per_rera_sqft'] ?? 0, 0) ?>/RERA sqft</div></div>
        <div class="cell"><div class="lbl">Partner Surplus Share (<?= $fmt($financialSummary['partner_share_pct'] ?? 0, 1) ?>%)</div><div class="val">₹<?= $inrCr($financialSummary['partner_share_inr'] ?? 0) ?> Cr</div></div>
    </div>

    <?php if (!empty($financialYearly)): ?>
        <!-- 10. Year-by-Year Cash Flow -->
        <h2 class="pb"><span class="num">10.</span>Year-by-Year Cash Flow</h2>
        <table class="calc-tbl" style="font-size: 0.62rem;">
            <thead>
                <tr>
                    <th>Year</th>
                    <th>Sale Rate (₹/sqft)</th>
                    <th>Booking %</th>
                    <th>Revenue Booked (₹ Cr)</th>
                    <th>Cash Inflow (₹ Cr)</th>
                    <th>Rehab Cost (₹ Cr)</th>
                    <th>Sale Cost (₹ Cr)</th>
                    <th>Premium (₹ Cr)</th>
                    <th>Society (₹ Cr)</th>
                    <th>Overheads (₹ Cr)</th>
                    <th>Total Cost (₹ Cr)</th>
                    <th>Interest (₹ Cr)</th>
                    <th>Net Cash After Int. (₹ Cr)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($financialYearly as $y):
                    $overhead = ($y['marketing'] ?? 0) + ($y['admin'] ?? 0) + ($y['contingency'] ?? 0) + ($y['other_overhead'] ?? 0);
                ?>
                    <tr>
                        <td><b>Y<?= (int)$y['year'] ?></b></td>
                        <td><?= $fmt($y['sale_rate'] ?? 0, 0) ?></td>
                        <td><?= $fmt(($y['booking_pct'] ?? 0) * 100, 1) ?>%</td>
                        <td><?= $inrCr($y['revenue_booked'] ?? 0) ?></td>
                        <td><?= $inrCr($y['cash_inflow'] ?? 0) ?></td>
                        <td><?= $inrCr($y['rehab_cost'] ?? 0) ?></td>
                        <td><?= $inrCr($y['sale_cost'] ?? 0) ?></td>
                        <td><?= $inrCr($y['premium'] ?? 0) ?></td>
                        <td><?= $inrCr($y['society_cost'] ?? 0) ?></td>
                        <td><?= $inrCr($overhead) ?></td>
                        <td><?= $inrCr($y['total_cost'] ?? 0) ?></td>
                        <td><?= $inrCr($y['interest'] ?? 0) ?></td>
                        <td style="color: <?= ($y['net_cash_after_interest'] ?? 0) < 0 ? '#dc2626' : '#059669' ?>; font-weight: 600;">
                            <?= $inrCr($y['net_cash_after_interest'] ?? 0) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php
                    $totals = [
                        'rev' => array_sum(array_column($financialYearly, 'revenue_booked')),
                        'cin' => array_sum(array_column($financialYearly, 'cash_inflow')),
                        'rc'  => array_sum(array_column($financialYearly, 'rehab_cost')),
                        'sc'  => array_sum(array_column($financialYearly, 'sale_cost')),
                        'pr'  => array_sum(array_column($financialYearly, 'premium')),
                        'so'  => array_sum(array_column($financialYearly, 'society_cost')),
                        'oh'  => array_sum(array_map(fn($y) => ($y['marketing']??0)+($y['admin']??0)+($y['contingency']??0)+($y['other_overhead']??0), $financialYearly)),
                        'tc'  => array_sum(array_column($financialYearly, 'total_cost')),
                        'int' => array_sum(array_column($financialYearly, 'interest')),
                        'ncai'=> array_sum(array_column($financialYearly, 'net_cash_after_interest')),
                    ];
                ?>
                <tr style="background:#f1f5f9; font-weight:700;">
                    <td>Total</td><td>—</td><td>—</td>
                    <td><?= $inrCr($totals['rev']) ?></td>
                    <td><?= $inrCr($totals['cin']) ?></td>
                    <td><?= $inrCr($totals['rc']) ?></td>
                    <td><?= $inrCr($totals['sc']) ?></td>
                    <td><?= $inrCr($totals['pr']) ?></td>
                    <td><?= $inrCr($totals['so']) ?></td>
                    <td><?= $inrCr($totals['oh']) ?></td>
                    <td><?= $inrCr($totals['tc']) ?></td>
                    <td><?= $inrCr($totals['int']) ?></td>
                    <td style="color:#059669;"><?= $inrCr($totals['ncai']) ?></td>
                </tr>
            </tbody>
        </table>
        <p style="font-size: 0.7rem; color: #64748b; margin-top: 6px;">
            All amounts in ₹ Crores unless stated. Overheads = Marketing + Admin + Contingency + Other. Sale rate escalates annually per project assumption.
        </p>
    <?php endif; ?>

    <?php if (!empty($financialLoan)): ?>
        <!-- 11. Loan Schedule -->
        <h2 class="pb"><span class="num">11.</span>Loan Schedule</h2>
        <table class="calc-tbl" style="font-size: 0.7rem;">
            <thead>
                <tr>
                    <th>Year</th>
                    <th>Opening Balance (₹ Cr)</th>
                    <th>Interest (₹ Cr)</th>
                    <th>Principal Repayment (₹ Cr)</th>
                    <th>Total Repayment (₹ Cr)</th>
                    <th>Closing Balance (₹ Cr)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($financialLoan as $l): ?>
                    <tr>
                        <td><b>Y<?= (int)$l['year'] ?></b></td>
                        <td><?= $inrCr($l['opening_balance'] ?? 0) ?></td>
                        <td><?= $inrCr($l['interest'] ?? 0) ?></td>
                        <td><?= $inrCr($l['principal_repayment'] ?? 0) ?></td>
                        <td><?= $inrCr($l['repayment_total'] ?? 0) ?></td>
                        <td><?= $inrCr($l['closing_balance'] ?? 0) ?></td>
                        <td><?= e($l['flag'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <?php endif; ?>

    <footer class="report-foot">
        <div>BDVault — Post-Proposal Development Planning · <?= e($appliedRegulation) ?></div>
        <div>Generated <?= e($reportGenerated) ?> · Plan ID <?= (int)$plan['id'] ?></div>
    </footer>
</div>

<script>
(function () {
    const seg = <?= json_encode($land_segments) ?>;
    if (!seg.length) return;
    new Chart(document.getElementById('rptLandDonut'), {
        type: 'doughnut',
        data: {
            labels: seg.map(s => s.label),
            datasets: [{
                data: seg.map(s => s.value),
                backgroundColor: seg.map(s => s.color),
                borderWidth: 2, borderColor: '#fff'
            }]
        },
        options: {
            maintainAspectRatio: false,
            cutout: '58%',
            animation: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: {
                    label: ctx => {
                        const total = ctx.dataset.data.reduce((a,b) => a+b, 0);
                        const pct = total ? (ctx.raw / total * 100).toFixed(2) : 0;
                        return `${ctx.label}: ${ctx.raw.toLocaleString(undefined,{maximumFractionDigits:2})} sqm (${pct}%)`;
                    }
                }}
            }
        }
    });
})();
</script>
</body>
</html>
