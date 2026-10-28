<?php
/**
 * Classic Feasibility Statement — Excel export (dynamic rows).
 * Headers set by AreaAnalysisController::feasibilityExcel().
 */
$s          = $statement;
$reg        = $s['selected_regulation_key'] ?? '';
$projectAdr = $s['plot_address'] ?: ($s['project_name'] . ($s['ward'] ? ', Ward ' . $s['ward'] : ''));
$ctsNo      = $s['cts_number'] ?: '—';
$roadWidth  = (float)($draft['step_2']['primary_road_width_m'] ?? 0);
$financials = $financials ?? [];
$flt = fn($v) => (is_numeric($v) ? (float)$v : null);
$rrLand     = $flt($financials['snapshot_rr_land_rate']    ?? null) ?? 0;
$rrConstr   = $flt($financials['snapshot_rr_construction'] ?? null) ?? 0;

$get = fn($k) => (float)($land[$k]['area_sqm'] ?? 0);
$gross = $get('gross_plot');

// Walk deduction rows
$deductionRows = [];
$totalDed = 0.0;
foreach ($land as $k => $r) {
    if ((int)$r['is_deduction'] !== 1) continue;
    $labelParts = [$r['component_label']];
    if (!empty($r['label_extra'])) $labelParts[] = '(' . $r['label_extra'] . ')';
    $label = implode(' ', array_filter($labelParts));
    $area  = (float)$r['area_sqm'];
    $inc   = (int)$r['include_in_total_deductions'] === 1;
    $deductionRows[] = ['label' => $label, 'area' => $area, 'include' => $inc];
    if ($inc) $totalDed += $area;
}
$netPlotDerived = max(0, $gross - $totalDed);

$calcVal = function ($keys) use ($calcs) {
    foreach ($calcs as $c) if (in_array($c['calc_key'], (array)$keys, true)) return (float)$c['calc_value'];
    return null;
};
$rehabBldg = $flt($financials['rehab_bua_override_sqm'] ?? null)
             ?? $calcVal(['rehab_tenement_bua_sqm','rehab_bua_sqm','total_rehab_bua_sqm'])
             ?? (float)($s['rehab_bua_sqm'] ?? 0);
$amenityInternal = $flt($financials['amenity_bua_override_sqm'] ?? null)
                   ?? $calcVal(['amenity_common_area_sqm','amenity_bua_sqm']);
if (!$amenityInternal || $amenityInternal < 0.10 * $rehabBldg) {
    $amenityInternal = $rehabBldg * 0.38;
}
$saleRatio  = $calcVal(['incentive_ratio','incentive_ratio_used','sale_incentive_ratio']) ?? 1.15;
$rehabComponent   = $rehabBldg + $amenityInternal;
$saleComponent    = $rehabComponent * $saleRatio;
// Total BUA Approved = Rehab BUA + Sale Component (Parksite convention — see PHP view).
$totalBuaApproved = $rehabBldg + $saleComponent;
$saleFungible     = $saleComponent * 0.35;
$netSalePerm      = $saleComponent;
$grossSalePerm    = $netSalePerm + $saleFungible;
$fsiSanctioned    = $netPlotDerived > 0 ? $grossSalePerm / $netPlotDerived : 0;
$fsiCap = 4.0;
$saleInSituDefault = max(0, min($saleComponent, ($gross * $fsiCap) - $rehabComponent));
$saleInSitu        = $flt($financials['sale_in_situ_sqm'] ?? null) ?? $saleInSituDefault;
$totalBuaProposed  = $rehabBldg + $saleInSitu;
$fsiConsumedInSitu = $gross > 0 ? $totalBuaProposed / $gross : 0;
$balanceSaleTDR    = max(0, $saleComponent - $saleInSitu);

$sqft = fn($v) => $v > 0 ? $v * 10.7639 : 0;
$fmt  = fn($v) => $v > 0 ? number_format($v, 2) : '';
$fmtF = fn($v) => $v > 0 ? number_format($v, 3) : '';
$srNo = 0;
?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Feasibility Statement — <?= e($s['statement_name']) ?></title>
    <style>
        table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11px; }
        td, th { border: 1px solid #0f172a; padding: 6px 10px; vertical-align: top; }
        th { background: #cbd5e1; font-weight: bold; text-align: center; text-transform: uppercase; font-size: 10px; }
        .title { background: #f1f5f9; font-weight: bold; text-align: center; font-size: 13px; padding: 12px; text-transform: uppercase; }
        .sec-hdr { background: #cbd5e1; font-weight: bold; text-transform: uppercase; font-size: 11px; }
        .total { background: #fef3c7; font-weight: bold; }
        .total-big { background: #dbeafe; font-weight: bold; font-size: 12px; }
        .fsi { background: #dcfce7; font-weight: bold; }
        .grand { background: #6366f1; color: #fff; font-weight: bold; font-size: 13px; }
        .excluded { background: #fafafa; color: #64748b; font-style: italic; }
        .n { text-align: center; width: 40px; font-weight: bold; }
        .a { text-align: right; font-family: Consolas, monospace; }
    </style>
</head>
<body>

<table>
    <tr><td class="title" colspan="4">
        Tentative Feasibility under D.C. Regulation <?= e($reg) ?> of D.C.P.R. 2034<br>
        for land bearing C.T.S. No. <?= e($ctsNo) ?> of <?= e($projectAdr) ?>
    </td></tr>
    <tr>
        <th>Sr. No.</th><th>Particular</th><th>Area in Sq.Mt.</th><th>Area in Sq.Ft.</th>
    </tr>
    <tr><td class="n">1</td><td>Road Width <?= $roadWidth > 0 ? number_format($roadWidth, 2) . ' M' : '' ?></td><td class="a">Reg. <?= e($reg) ?></td><td></td></tr>
    <tr><td class="n">2</td><td>RR Land Rate</td><td class="a"><?= $rrLand > 0 ? number_format($rrLand, 2) : '' ?></td><td></td></tr>
    <tr><td class="n">3</td><td>RR for Construction</td><td class="a"><?= $rrConstr > 0 ? number_format($rrConstr, 2) : '' ?></td><td></td></tr>

    <tr class="sec-hdr"><td class="n">A</td><td colspan="3">Revised Scheme Parameters</td></tr>

    <?php $srNo = 1; ?>
    <tr><td class="n"><?= $srNo ?></td><td>Gross Plot Area</td><td class="a"><?= $fmt($gross) ?></td><td class="a"><?= $fmt($sqft($gross)) ?></td></tr>

    <?php foreach ($deductionRows as $r): $srNo++; ?>
        <tr class="<?= !$r['include'] ? 'excluded' : '' ?>">
            <td class="n"><?= $srNo ?></td>
            <td><?= e($r['label']) ?><?= !$r['include'] ? ' (not deducted)' : '' ?></td>
            <td class="a"><?= $fmt($r['area']) ?></td>
            <td class="a"><?= $fmt($sqft($r['area'])) ?></td>
        </tr>
    <?php endforeach; ?>

    <?php $srNo++; ?>
    <tr class="total"><td class="n"><?= $srNo ?></td><td>Total Deductions</td><td class="a"><?= $fmt($totalDed) ?></td><td class="a"><?= $fmt($sqft($totalDed)) ?></td></tr>
    <?php $srNo++; ?>
    <tr class="total-big"><td class="n"><?= $srNo ?></td><td>Net Plot Area of S.R. Scheme for FSI Computation</td><td class="a"><?= $fmt($netPlotDerived) ?></td><td class="a"><?= $fmt($sqft($netPlotDerived)) ?></td></tr>

    <?php $srNo++; ?>
    <tr><td class="n"><?= $srNo ?></td><td>Rehabilitation BUA (for Bldg. No. 1 to <?= max(1, count($buildings)) ?>)</td><td class="a"><?= $fmt($rehabBldg) ?></td><td class="a"><?= $fmt($sqft($rehabBldg)) ?></td></tr>
    <?php $srNo++; ?>
    <tr><td class="n"><?= $srNo ?></td><td>Amenity Area (Balwadi, W/C and Society Office &amp; Common Passage)</td><td class="a"><?= $fmt($amenityInternal) ?></td><td class="a"><?= $fmt($sqft($amenityInternal)) ?></td></tr>
    <?php $srNo++; ?>
    <tr><td class="n"><?= $srNo ?></td><td>Rehabilitation Component (for Bldg. No. 1 to <?= max(1, count($buildings)) ?>)</td><td class="a"><?= $fmt($rehabComponent) ?></td><td class="a"><?= $fmt($sqft($rehabComponent)) ?></td></tr>
    <?php $srNo++; ?>
    <tr><td class="n"><?= $srNo ?></td><td>Sale Component (Rehab to Sale Ratio <?= number_format($saleRatio, 2) ?> of Rehab BUA)</td><td class="a"><?= $fmt($saleComponent) ?></td><td class="a"><?= $fmt($sqft($saleComponent)) ?></td></tr>
    <?php $srNo++; ?>
    <tr class="total-big"><td class="n"><?= $srNo ?></td><td>Total BUA Approved for the Scheme</td><td class="a"><?= $fmt($totalBuaApproved) ?></td><td class="a"><?= $fmt($sqft($totalBuaApproved)) ?></td></tr>
    <?php $srNo++; ?>
    <tr class="fsi"><td class="n"><?= $srNo ?></td><td>FSI Sanctioned for the Scheme</td><td class="a"><?= $fmtF($fsiSanctioned) ?></td><td class="a">FSI</td></tr>

    <?php $srNo++; ?>
    <tr><td class="n"><?= $srNo ?></td><td>Sale BUA Proposed In Situ</td><td class="a"><?= $fmt($saleInSitu) ?></td><td class="a"><?= $fmt($sqft($saleInSitu)) ?></td></tr>
    <?php $srNo++; ?>
    <tr><td class="n"><?= $srNo ?></td><td>Total BUA Proposed for Scheme (Rehab + Sale)</td><td class="a"><?= $fmt($totalBuaProposed) ?></td><td class="a"><?= $fmt($sqft($totalBuaProposed)) ?></td></tr>
    <?php $srNo++; ?>
    <tr class="fsi"><td class="n"><?= $srNo ?></td><td>FSI Consumed In Situ</td><td class="a"><?= $fmtF($fsiConsumedInSitu) ?></td><td class="a">FSI</td></tr>
    <?php $srNo++; ?>
    <tr><td class="n"><?= $srNo ?></td><td>Balance Sale BUA / TDR</td><td class="a"><?= $fmt($balanceSaleTDR) ?></td><td class="a"><?= $fmt($sqft($balanceSaleTDR)) ?></td></tr>
    <?php $srNo++; ?>
    <tr><td class="n"><?= $srNo ?></td><td>Net Sale Permissible</td><td class="a"><?= $fmt($netSalePerm) ?></td><td class="a"><?= $fmt($sqft($netSalePerm)) ?></td></tr>
    <?php $srNo++; ?>
    <tr><td class="n"><?= $srNo ?></td><td>Sale Fungible @ 35%</td><td class="a"><?= $fmt($saleFungible) ?></td><td class="a"><?= $fmt($sqft($saleFungible)) ?></td></tr>
    <?php $srNo++; ?>
    <tr class="grand"><td class="n"><?= $srNo ?></td><td>Gross Sale BUA Permissible</td><td class="a"><?= $fmt($grossSalePerm) ?></td><td class="a"><?= $fmt($sqft($grossSalePerm)) ?></td></tr>
</table>

</body>
</html>
