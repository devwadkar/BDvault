<?php
/**
 * Excel export — HTML table format opens natively in Excel.
 * Headers are already set by AreaAnalysisController::exportExcel().
 */
$s2 = $draft['step_2'] ?? [];
$ver = 'v' . (int)$statement['version_major'] . '.' . (int)$statement['version_minor'];
$fs = fn($v) => $v === null || $v === '' ? '' : number_format((float)$v, 2);
$fr = fn($v) => $v === null || $v === '' ? '' : number_format((float)$v, 3);
?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Area Statement <?= e($ver) ?></title>
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Area Statement</x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
    <style>
        table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11px; }
        td, th { border: 1px solid #999; padding: 4px 8px; vertical-align: top; }
        th { background: #1e293b; color: #fff; font-weight: bold; }
        .sec-hdr { background: #4f46e5; color: #fff; font-weight: bold; font-size: 12px; text-align: left; }
        .total { background: #dcfce7; font-weight: bold; }
        .num { text-align: right; font-family: Consolas, monospace; }
        .label { font-weight: bold; background: #f8fafc; width: 220px; }
    </style>
</head>
<body>

<table>
    <tr><td colspan="4" class="sec-hdr" style="font-size: 16px;">AREA STATEMENT — <?= e($statement['statement_name']) ?></td></tr>
    <tr><td colspan="4">Version: <?= e($ver) ?> · Status: <?= strtoupper(e($statement['status'])) ?>
        <?= $statement['locked_at'] ? '· Locked ' . e($statement['locked_at']) : ($statement['calculated_at'] ? '· Calculated ' . e($statement['calculated_at']) : '') ?></td></tr>
    <tr><td colspan="4"></td></tr>

    <!-- 1 · Project Info -->
    <tr><td colspan="4" class="sec-hdr">1 · PROJECT INFORMATION</td></tr>
    <tr><td class="label">Project Name</td><td colspan="3"><?= e($statement['project_name']) ?></td></tr>
    <tr><td class="label">Location</td><td colspan="3"><?= e($statement['project_location']) ?></td></tr>
    <tr><td class="label">Ward</td><td colspan="3"><?= e($statement['ward']) ?></td></tr>
    <tr><td class="label">CTS / Survey No.</td><td colspan="3"><?= e($statement['cts_number']) ?></td></tr>
    <tr><td class="label">Plot Address</td><td colspan="3"><?= e($statement['plot_address']) ?></td></tr>
    <tr><td class="label">Planning Authority</td><td colspan="3"><?= e($statement['planning_authority']) ?></td></tr>
    <tr><td class="label">Development Type</td><td colspan="3"><?= e($statement['development_type']) ?></td></tr>
    <tr><td colspan="4"></td></tr>

    <!-- 2 · Plot & Land -->
    <tr><td colspan="4" class="sec-hdr">2 · PLOT &amp; LAND DETAILS</td></tr>
    <tr><td class="label">Gross Plot Area (sqm)</td><td class="num"><?= $fs($s2['gross_plot_area_sqm'] ?? null) ?></td><td colspan="2"></td></tr>
    <tr><td class="label">Property-Card Area (sqm)</td><td class="num"><?= $fs($s2['property_card_area_sqm'] ?? null) ?></td><td colspan="2"></td></tr>
    <tr><td class="label">Primary Road Width (m)</td><td class="num"><?= $fs($s2['primary_road_width_m'] ?? null) ?></td><td colspan="2"></td></tr>
    <tr><td class="label">Amalgamation</td><td colspan="3"><?= e($s2['amalgamation_status'] ?? '') ?></td></tr>
    <tr><td colspan="4"></td></tr>

    <!-- 3 · Land Statement -->
    <tr><td colspan="4" class="sec-hdr">3 · LAND STATEMENT</td></tr>
    <tr><th>Component</th><th>Area (sqm)</th><th>Source</th><th>DCPR Ref</th></tr>
    <?php foreach ($land as $k => $r): ?>
        <tr class="<?= in_array($k, ['net_plot','developable']) ? 'total' : '' ?>">
            <td><?= e($r['component_label']) ?></td>
            <td class="num"><?= $fs($r['area_sqm']) ?></td>
            <td><?= e($r['source']) ?></td>
            <td><?= e($r['regulation_ref']) ?></td>
        </tr>
    <?php endforeach; ?>
    <tr><td colspan="4"></td></tr>

    <!-- 4 · Applicable Regulation -->
    <tr><td colspan="4" class="sec-hdr">4 · APPLICABLE DCPR REGULATION</td></tr>
    <tr><td class="label">Selected</td><td colspan="3"><?= e($statement['selected_regulation_key']) ?></td></tr>
    <tr><td class="label">Suggested</td><td colspan="3"><?= e($statement['suggested_regulation_key']) ?> (<?= (int)$statement['suggested_confidence'] ?>%)</td></tr>
    <tr><td class="label">Rationale</td><td colspan="3"><?= e($statement['suggested_rationale']) ?></td></tr>
    <tr><td colspan="4"></td></tr>

    <!-- 5 · FSI -->
    <tr><td colspan="4" class="sec-hdr">5 · FSI STATEMENT</td></tr>
    <tr><th>Component</th><th>FSI Ratio</th><th>BUA (sqm)</th><th>DCPR Ref</th></tr>
    <?php foreach ($fsi as $r): $t = strpos($r['component_key'], 'full_') === 0 || $r['component_key'] === 'fsi_sanctioned' || $r['component_key'] === 'total_bua_approved_sqm'; ?>
        <tr class="<?= $t ? 'total' : '' ?>">
            <td><?= e($r['component_label']) ?></td>
            <td class="num"><?= $r['fsi_ratio'] !== null ? $fr($r['fsi_ratio']) : '' ?></td>
            <td class="num"><?= $r['bua_sqm']   !== null ? $fs($r['bua_sqm']) : '' ?></td>
            <td><?= e($r['regulation_ref']) ?></td>
        </tr>
    <?php endforeach; ?>
    <tr><td colspan="4"></td></tr>

    <!-- 6 · Existing Buildings -->
    <?php if (!empty($buildings)): ?>
        <tr><td colspan="4" class="sec-hdr">6 · EXISTING BUILDINGS</td></tr>
        <tr><th>Building</th><th>Units (Res+Com)</th><th>BUA (sqm)</th><th>Tenants (Eligible)</th></tr>
        <?php foreach ($buildings as $b): ?>
            <tr>
                <td><?= e($b['building_label']) ?></td>
                <td class="num"><?= (int)$b['residential_units'] ?> + <?= (int)$b['commercial_units'] ?></td>
                <td class="num"><?= $fs($b['existing_bua_sqm']) ?></td>
                <td class="num"><?= (int)$b['tenant_count'] ?> (<?= (int)$b['eligible_tenant_count'] ?>)</td>
            </tr>
        <?php endforeach; ?>
        <tr><td colspan="4"></td></tr>
    <?php endif; ?>

    <!-- 7 · Development Statement -->
    <tr><td colspan="4" class="sec-hdr">7 · DEVELOPMENT STATEMENT</td></tr>
    <tr><th>Component</th><th>Area (sqm)</th><th>Handover</th><th>DCPR Ref</th></tr>
    <?php foreach ($dev as $r): $t = strpos($r['component_key'], 'full_') === 0 || strpos($r['component_key'], 'total_bua') === 0; ?>
        <tr class="<?= $t ? 'total' : '' ?>">
            <td><?= e($r['component_label']) ?></td>
            <td class="num"><?= $fs($r['area_sqm']) ?></td>
            <td><?= e($r['handover_to']) ?></td>
            <td><?= e($r['regulation_ref']) ?></td>
        </tr>
    <?php endforeach; ?>
    <tr><td colspan="4"></td></tr>

    <!-- 8 · Validation -->
    <tr><td colspan="4" class="sec-hdr">8 · VALIDATION</td></tr>
    <tr><th>Result</th><th>Check</th><th>Value</th><th>Required</th></tr>
    <?php foreach ($valids as $v): ?>
        <tr>
            <td><?= strtoupper(e($v['result'])) ?></td>
            <td><?= e($v['check_label']) ?></td>
            <td class="num"><?= e($v['entered_value']) ?></td>
            <td class="num"><?= e($v['required_value']) ?></td>
        </tr>
    <?php endforeach; ?>
    <tr><td colspan="4"></td></tr>

    <!-- 9 · Full Audit Trail -->
    <tr><td colspan="4" class="sec-hdr">9 · CALCULATION AUDIT TRAIL</td></tr>
    <tr><th>Row</th><th>Value</th><th>Unit</th><th>Formula · DCPR Ref</th></tr>
    <?php foreach ($calcs as $c): ?>
        <tr>
            <td><?= e($c['calc_label']) ?></td>
            <td class="num"><?= number_format((float)$c['calc_value'], 4) ?></td>
            <td><?= e($c['calc_unit']) ?></td>
            <td><?= e($c['formula']) ?> · <?= e($c['regulation_ref']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

</body>
</html>
