<?php $pageTitle = 'Area Statement — ' . e($statement['statement_name']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php
$s2 = $draft['step_2'] ?? [];
$fmtSqm  = fn($v) => ($v === null || $v === '' ? '—' : number_format((float)$v, 2) . ' sqm');
$fmtRatio = fn($v) => ($v === null || $v === '' ? '—' : number_format((float)$v, 3));
$fmtNum  = fn($v, $d = 2) => ($v === null || $v === '' ? '—' : number_format((float)$v, $d));
$ver = 'v' . (int)$statement['version_major'] . '.' . (int)$statement['version_minor'];
$vDate = $statement['locked_at'] ?: $statement['calculated_at'] ?: $statement['updated_at'];
?>

<style>
    .rpt-wrap { background: #fff; max-width: 900px; margin: 0 auto; padding: 30px 40px; font-family: 'Lexend', system-ui, sans-serif; color: #0f172a; }
    .rpt-actions { max-width: 900px; margin: 0 auto 14px; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
    .rpt-actions .btn-a { padding: 7px 14px; border-radius: 8px; font-size: 0.78rem; font-weight: 700; text-decoration: none; border: 0; cursor: pointer; }
    .btn-back    { background: #f1f5f9; color: #475569; }
    .btn-print   { background: #4f46e5; color: #fff; }
    .btn-excel   { background: #10b981; color: #fff; }
    .btn-newver  { background: #f59e0b; color: #fff; }
    .btn-compare { background: #06b6d4; color: #fff; }

    .rpt-hero {
        border-bottom: 3px solid #4f46e5; padding-bottom: 18px; margin-bottom: 24px;
        display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;
    }
    .rpt-hero h1 { font-size: 1.4rem; font-weight: 800; margin: 0; letter-spacing: -0.02em; }
    .rpt-hero .sub { color: #64748b; font-size: 0.85rem; margin-top: 4px; }
    .rpt-hero .meta-pill { padding: 3px 12px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
    .meta-pill.locked      { background: #dcfce7; color: #065f46; }
    .meta-pill.calculated  { background: #dbeafe; color: #1e40af; }
    .meta-pill.draft       { background: #fef3c7; color: #92400e; }
    .meta-pill.superseded  { background: #f1f5f9; color: #64748b; }

    .rpt-sec { margin-bottom: 24px; page-break-inside: avoid; }
    .rpt-sec h2 {
        font-size: 0.9rem; font-weight: 800; color: #4338ca;
        text-transform: uppercase; letter-spacing: .06em;
        border-bottom: 2px solid #eef2ff; padding-bottom: 6px; margin: 0 0 12px;
    }
    .rpt-sec h2 .n {
        display: inline-flex; width: 26px; height: 26px; border-radius: 50%;
        background: #4f46e5; color: #fff; text-align: center; align-items: center; justify-content: center;
        margin-right: 10px; font-size: 0.75rem; font-weight: 800;
    }
    .kv { display: grid; grid-template-columns: 180px 1fr; gap: 6px 20px; font-size: 0.85rem; }
    .kv dt { color: #64748b; font-weight: 600; }
    .kv dd { margin: 0; color: #0f172a; }

    .rpt-tbl { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
    .rpt-tbl th { background: #f8fafc; color: #64748b; text-align: left; padding: 8px 10px; text-transform: uppercase; font-size: 0.66rem; letter-spacing: .04em; border-bottom: 1px solid #e5e7eb; }
    .rpt-tbl td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    .rpt-tbl .num { text-align: right; font-family: 'JetBrains Mono', Consolas, monospace; font-weight: 600; white-space: nowrap; }
    .rpt-tbl .ref { color: #6366f1; font-size: 0.7rem; font-weight: 700; }
    .rpt-tbl tr.total td { background: #ecfdf5; font-weight: 800; color: #065f46; border-bottom: 2px solid #86efac; }

    .valid-badge {
        display: inline-block; padding: 2px 10px; border-radius: 999px;
        font-size: 0.7rem; font-weight: 700;
    }
    .valid-badge.pass { background: #dcfce7; color: #065f46; }
    .valid-badge.warn { background: #fef3c7; color: #92400e; }
    .valid-badge.fail { background: #fee2e2; color: #991b1b; }

    .rpt-footer { border-top: 1px solid #e5e7eb; padding-top: 12px; margin-top: 30px; color: #94a3b8; font-size: 0.75rem; display: flex; justify-content: space-between; }

    /* Print polish */
    @media print {
        body { background: #fff; }
        .rpt-actions, .navbar, aside, header, footer, .premium-navbar, nav, .sidebar-container { display: none !important; }
        .rpt-wrap { margin: 0; padding: 0; max-width: none; box-shadow: none; }
        .rpt-sec { page-break-inside: avoid; }
        .rpt-hero { page-break-after: avoid; }
        h2 { page-break-after: avoid; }
        table { page-break-inside: auto; }
    }
</style>

<div class="rpt-actions">
    <a href="index.php?page=area_analysis" class="btn-a btn-back">
        <i class="bi bi-arrow-left me-1"></i>Back to Area Analysis
    </a>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($statement['status'] === 'locked' && $statement['calculated_at']): ?>
            <form method="POST" action="index.php?page=area_analysis_new_version" class="d-inline"
                  onsubmit="return confirm('Create a new version from this one?')">
                <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
                <input type="hidden" name="bump_level" id="bumpLevel" value="minor">
                <button type="submit" class="btn-a btn-newver" onclick="document.getElementById('bumpLevel').value = confirm('OK for MINOR bump (v<?= (int)$statement['version_major'] ?>.<?= (int)$statement['version_minor']+1 ?>). Cancel for MAJOR bump.') ? 'minor' : 'major'; return true;">
                    <i class="bi bi-plus-square me-1"></i>New Version
                </button>
            </form>
        <?php endif; ?>
        <form method="POST" action="index.php?page=area_analysis_refresh_calc" class="d-inline">
            <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
            <input type="hidden" name="redirect_to" value="index.php?page=area_analysis_report&statement_id=<?= (int)$statement['id'] ?>">
            <button type="submit" class="btn-a" style="background:#0284c7;color:#fff;" title="Re-run the calc engine — picks up amenity + registry + regulation changes">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh calc
            </button>
        </form>
        <a href="index.php?page=area_analysis_feasibility&statement_id=<?= (int)$statement['id'] ?>" class="btn-a" style="background:#0f172a;color:#fff;">
            <i class="bi bi-file-earmark-ruled me-1"></i>Feasibility Statement
        </a>
        <a href="index.php?page=area_analysis_pdf_report&statement_id=<?= (int)$statement['id'] ?>" class="btn-a" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;box-shadow:0 2px 8px rgba(220,38,38,0.3);">
            <i class="bi bi-file-earmark-pdf-fill me-1"></i>20-Section PDF Report
        </a>
        <a href="index.php?page=area_analysis_export&statement_id=<?= (int)$statement['id'] ?>" class="btn-a btn-excel">
            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
        </a>
        <button class="btn-a btn-print" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print / Save PDF
        </button>
    </div>
</div>

<div class="rpt-wrap">
    <div class="rpt-hero">
        <div>
            <h1><?= e($statement['statement_name']) ?></h1>
            <div class="sub">
                <?= e($proposal['proposal_name'] ?? '') ?>
                <?php if ($proposal['city']): ?> · <?= e($proposal['city']) ?><?php endif; ?>
                <?php if ($statement['selected_regulation_key']): ?> · <b>DCPR <?= e($statement['selected_regulation_key']) ?></b><?php endif; ?>
            </div>
        </div>
        <div style="text-align: right;">
            <div><span class="meta-pill <?= e($statement['status']) ?>"><?= strtoupper(e($statement['status'])) ?></span></div>
            <div style="font-size: 0.72rem; color: #64748b; margin-top: 4px;">
                <?= $ver ?> · <?= $vDate ? e(date('d M Y H:i', strtotime($vDate))) : '—' ?>
            </div>
        </div>
    </div>

    <!-- Site Plan — schematic visual -->
    <?php if (!empty($land)): ?>
    <section class="rpt-sec" style="page-break-inside: avoid;">
        <h2><span class="n"><i class="bi bi-diagram-2" style="font-style:normal;font-size:0.75rem;"></i></span>Site Plan Overview</h2>
        <div style="background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; text-align: center;">
            <?= SitePlanRenderer::render(compact('statement','land','dev','buildings'), 'full') ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- 1 · Project Information -->
    <section class="rpt-sec">
        <h2><span class="n">1</span>Project Information</h2>
        <dl class="kv">
            <dt>Project Name</dt>   <dd><?= e($statement['project_name'] ?? '—') ?></dd>
            <dt>Location</dt>       <dd><?= e($statement['project_location'] ?? '—') ?></dd>
            <dt>Ward</dt>           <dd><?= e($statement['ward'] ?? '—') ?></dd>
            <dt>CTS / Survey No.</dt><dd><?= e($statement['cts_number'] ?? '—') ?></dd>
            <dt>Plot Address</dt>   <dd><?= e($statement['plot_address'] ?? '—') ?></dd>
            <dt>Planning Authority</dt><dd><?= e($statement['planning_authority'] ?? '—') ?></dd>
            <dt>Development Type</dt><dd><?= e(str_replace('_', ' ', ucwords((string)($statement['development_type'] ?? '—')))) ?></dd>
        </dl>
    </section>

    <!-- 2 · Plot / Land Details -->
    <section class="rpt-sec">
        <h2><span class="n">2</span>Plot &amp; Land Details</h2>
        <dl class="kv">
            <dt>Gross Plot Area</dt>          <dd><?= $fmtSqm($s2['gross_plot_area_sqm'] ?? null) ?></dd>
            <dt>Property-Card Area</dt>       <dd><?= $fmtSqm($s2['property_card_area_sqm'] ?? null) ?></dd>
            <dt>Primary Road Width</dt>       <dd><?= isset($s2['primary_road_width_m']) && $s2['primary_road_width_m'] !== '' ? $fmtNum($s2['primary_road_width_m'], 2) . ' m' : '—' ?></dd>
            <dt>Amalgamation</dt>             <dd><?= e(ucfirst((string)($s2['amalgamation_status'] ?? '—'))) ?></dd>
            <dt>No. of Parcels</dt>           <dd><?= (int)($s2['num_parcels'] ?? 1) ?></dd>
            <dt>Existing DP Road</dt>         <dd><?= e(ucfirst((string)($s2['has_dp_road'] ?? '—'))) ?></dd>
            <dt>Existing Reservation</dt>     <dd><?= e(ucfirst((string)($s2['has_reservation'] ?? '—'))) ?></dd>
        </dl>
    </section>

    <!-- 3 · Land Deduction Statement -->
    <section class="rpt-sec">
        <h2><span class="n">3</span>Land Deduction Statement</h2>
        <table class="rpt-tbl">
            <thead><tr><th>Component</th><th class="num">Area</th><th>Source</th><th>DCPR Ref</th></tr></thead>
            <tbody>
                <?php foreach ($land as $key => $r): if ($key === 'net_plot' || $key === 'developable') continue; ?>
                    <tr>
                        <td><?= e($r['component_label']) ?><?= $r['is_deduction'] ? ' <small class="text-muted">(deduction)</small>' : '' ?></td>
                        <td class="num"><?= $fmtSqm($r['area_sqm']) ?></td>
                        <td class="small text-muted"><?= e($r['source']) ?></td>
                        <td class="ref"><?= e($r['regulation_ref'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <!-- 4 · Net Plot Area Calculation -->
    <section class="rpt-sec">
        <h2><span class="n">4</span>Net Plot Area Calculation</h2>
        <table class="rpt-tbl">
            <thead><tr><th>Component</th><th class="num">Area</th><th>DCPR Ref</th></tr></thead>
            <tbody>
                <?php foreach (['net_plot', 'developable'] as $k): if (!isset($land[$k])) continue; $r = $land[$k]; ?>
                    <tr class="total">
                        <td><?= e($r['component_label']) ?></td>
                        <td class="num"><?= $fmtSqm($r['area_sqm']) ?></td>
                        <td class="ref"><?= e($r['regulation_ref'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="small text-muted mt-2">Formula: Net = Gross − (Road + Reservation + Amenity + Other Statutory Deductions).</p>
    </section>

    <!-- 5 · Applicable DCPR Regulation -->
    <section class="rpt-sec">
        <h2><span class="n">5</span>Applicable DCPR Regulation</h2>
        <dl class="kv">
            <dt>Selected Regulation</dt>
            <dd><b>DCPR <?= e($statement['selected_regulation_key'] ?? '—') ?></b>
                <?php if ($statement['suggested_regulation_key'] === $statement['selected_regulation_key']): ?>
                    <span class="badge bg-success ms-2">System-suggested</span>
                <?php elseif ($statement['selected_regulation_key']): ?>
                    <span class="badge bg-warning ms-2">User-overridden</span>
                <?php endif; ?>
            </dd>
            <dt>System Suggestion</dt>
            <dd>DCPR <?= e($statement['suggested_regulation_key'] ?? '—') ?>
                <?php if ($statement['suggested_confidence']): ?> · <?= (int)$statement['suggested_confidence'] ?>% confidence<?php endif; ?>
            </dd>
            <dt>Rationale</dt>
            <dd><?= e($statement['suggested_rationale'] ?? '—') ?></dd>
        </dl>
    </section>

    <!-- 6 · FSI Calculation -->
    <section class="rpt-sec">
        <h2><span class="n">6</span>FSI Calculation</h2>
        <?php if (empty($fsi)): ?>
            <p class="text-muted small">Run Step 5 to compute FSI.</p>
        <?php else: ?>
            <table class="rpt-tbl">
                <thead><tr><th>Component</th><th class="num">FSI Ratio</th><th class="num">BUA</th><th>DCPR Ref</th></tr></thead>
                <tbody>
                    <?php foreach ($fsi as $r): $isTotal = strpos($r['component_key'], 'full_') === 0 || $r['component_key'] === 'fsi_sanctioned' || $r['component_key'] === 'total_bua_approved_sqm'; ?>
                        <tr class="<?= $isTotal ? 'total' : '' ?>">
                            <td><?= e($r['component_label']) ?></td>
                            <td class="num"><?= $r['fsi_ratio'] !== null ? $fmtRatio($r['fsi_ratio']) : '—' ?></td>
                            <td class="num"><?= $r['bua_sqm']   !== null ? $fmtSqm($r['bua_sqm'])   : '—' ?></td>
                            <td class="ref"><?= e($r['regulation_ref'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <!-- 7 · Rehabilitation Calculation -->
    <section class="rpt-sec">
        <h2><span class="n">7</span>Rehabilitation Calculation</h2>
        <?php if (!empty($buildings)): ?>
            <table class="rpt-tbl" style="margin-bottom: 10px;">
                <thead><tr><th>Existing Building</th><th class="num">Res Units</th><th class="num">Com Units</th><th class="num">Res Carpet</th><th class="num">Com Carpet</th><th class="num">Existing BUA</th><th class="num">Tenants</th></tr></thead>
                <tbody>
                    <?php $tRu=0; $tCu=0; $tRc=0; $tCc=0; $tB=0; $tT=0; foreach ($buildings as $b): $tRu+=$b['residential_units']; $tCu+=$b['commercial_units']; $tRc+=$b['residential_carpet_sqm']; $tCc+=$b['commercial_carpet_sqm']; $tB+=$b['existing_bua_sqm']; $tT+=$b['tenant_count']; ?>
                        <tr>
                            <td><?= e($b['building_label']) ?></td>
                            <td class="num"><?= (int)$b['residential_units'] ?></td>
                            <td class="num"><?= (int)$b['commercial_units'] ?></td>
                            <td class="num"><?= $fmtSqm($b['residential_carpet_sqm']) ?></td>
                            <td class="num"><?= $fmtSqm($b['commercial_carpet_sqm']) ?></td>
                            <td class="num"><?= $fmtSqm($b['existing_bua_sqm']) ?></td>
                            <td class="num"><?= (int)$b['tenant_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total">
                        <td>Totals</td>
                        <td class="num"><?= (int)$tRu ?></td>
                        <td class="num"><?= (int)$tCu ?></td>
                        <td class="num"><?= $fmtSqm($tRc) ?></td>
                        <td class="num"><?= $fmtSqm($tCc) ?></td>
                        <td class="num"><?= $fmtSqm($tB) ?></td>
                        <td class="num"><?= (int)$tT ?></td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
        <?php $rehabRows = array_filter($dev, fn($r) => in_array($r['component_key'], ['rehab_bua_sqm','rehab_component_sqm','total_rehab_bua_sqm','commercial_rehab_bua_sqm','residential_rehab_bua_sqm','amenity_bua_sqm','incentive_bua_sqm'], true)); ?>
        <?php if (!empty($rehabRows)): ?>
            <table class="rpt-tbl">
                <thead><tr><th>Rehab Component</th><th class="num">Area</th><th>Handover</th><th>DCPR Ref</th></tr></thead>
                <tbody>
                    <?php foreach ($rehabRows as $r): ?>
                        <tr>
                            <td><?= e($r['component_label']) ?></td>
                            <td class="num"><?= $fmtSqm($r['area_sqm']) ?></td>
                            <td class="small"><?= e($r['handover_to'] ?? '—') ?></td>
                            <td class="ref"><?= e($r['regulation_ref'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <!-- 8 · Development Potential -->
    <section class="rpt-sec">
        <h2><span class="n">8</span>Development Potential</h2>
        <dl class="kv">
            <dt>Total Permissible FSI</dt>   <dd><b><?= $fmtRatio($statement['total_permissible_fsi']) ?></b></dd>
            <dt>Total Permissible BUA</dt>   <dd><b><?= $fmtSqm($statement['total_permissible_bua_sqm']) ?></b></dd>
            <dt>Rehab Component</dt>         <dd><?= $fmtSqm($statement['rehab_bua_sqm']) ?></dd>
            <dt>Sale / Owner Component</dt>  <dd><?= $fmtSqm($statement['sale_bua_sqm']) ?></dd>
        </dl>
    </section>

    <!-- 9 · Sale / Free-Sale Potential -->
    <section class="rpt-sec">
        <h2><span class="n">9</span>Sale / Free-Sale Potential</h2>
        <?php $saleRows = array_filter($dev, fn($r) => in_array($r['component_key'], ['sale_component_sqm','sale_bua_sqm','sale_society_total_sqm','owner_total_sqm','society_grand_total_sqm'], true)); ?>
        <?php $handoverRows = array_filter($dev, fn($r) => in_array($r['handover_to'] ?? '', ['SRA','MHADA','MCGM'], true)); ?>
        <table class="rpt-tbl">
            <thead><tr><th>Component</th><th class="num">Area</th><th>Handover / Sale</th><th>DCPR Ref</th></tr></thead>
            <tbody>
                <?php foreach ($saleRows as $r): ?>
                    <tr><td><?= e($r['component_label']) ?></td><td class="num"><?= $fmtSqm($r['area_sqm']) ?></td><td class="small">Free-sale / Owner</td><td class="ref"><?= e($r['regulation_ref']) ?></td></tr>
                <?php endforeach; ?>
                <?php foreach ($handoverRows as $r): ?>
                    <tr><td><?= e($r['component_label']) ?></td><td class="num"><?= $fmtSqm($r['area_sqm']) ?></td><td class="small">Handover to <?= e($r['handover_to']) ?></td><td class="ref"><?= e($r['regulation_ref']) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <!-- 10 · Complete Area Statement -->
    <section class="rpt-sec">
        <h2><span class="n">10</span>Complete Area Statement</h2>
        <?php if (empty($dev)): ?>
            <p class="text-muted small">No development rows.</p>
        <?php else: ?>
            <table class="rpt-tbl">
                <thead><tr><th>Component</th><th class="num">Area</th><th>Handover</th><th>DCPR Ref</th></tr></thead>
                <tbody>
                    <?php foreach ($dev as $r): $isTotal = strpos($r['component_key'], 'full_') === 0 || strpos($r['component_key'], 'total_bua') === 0; ?>
                        <tr class="<?= $isTotal ? 'total' : '' ?>">
                            <td><?= e($r['component_label']) ?></td>
                            <td class="num"><?= $fmtSqm($r['area_sqm']) ?></td>
                            <td class="small"><?= e($r['handover_to'] ?? '—') ?></td>
                            <td class="ref"><?= e($r['regulation_ref'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <!-- 11 · Validation / Compliance -->
    <section class="rpt-sec">
        <h2><span class="n">11</span>Validation &amp; Compliance Summary</h2>
        <?php if (empty($valids)): ?>
            <p class="text-muted small">No validation results (run Step 5 to compute).</p>
        <?php else: ?>
            <table class="rpt-tbl">
                <thead><tr><th style="width:80px;">Result</th><th>Check</th><th class="num">Value</th><th class="num">Required</th><th>DCPR Ref</th></tr></thead>
                <tbody>
                    <?php foreach ($valids as $v): ?>
                        <tr>
                            <td><span class="valid-badge <?= e($v['result']) ?>"><?= strtoupper(e($v['result'])) ?></span></td>
                            <td><?= e($v['check_label']) ?><div class="text-muted small"><?= e($v['message']) ?></div></td>
                            <td class="num"><?= e($v['entered_value'] ?? '—') ?></td>
                            <td class="num"><?= e($v['required_value'] ?? '—') ?></td>
                            <td class="ref"><?= e($v['regulation_ref'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <!-- 12 · Calculation Notes & Assumptions -->
    <section class="rpt-sec">
        <h2><span class="n">12</span>Calculation Notes &amp; Assumptions</h2>
        <p class="small text-muted">Every derived value was produced by the calculator for DCPR <?= e($statement['selected_regulation_key'] ?? '—') ?>, using the inputs captured in Steps 2–3 of the wizard. Assumptions applied where inputs were missing:</p>
        <ul class="small" style="line-height: 1.7;">
            <li>Carpet-to-BUA loading factor: <b>1.32</b> (typical Mumbai high-rise).</li>
            <li>Fungible: <b>35 %</b> (Reg 31(3), residential).</li>
            <li>ASR land rate: <?= isset($statement['ward']) ? 'looked up for ward <b>' . e($statement['ward']) . '</b>' : 'generic Mumbai fallback' ?> where the ASR spreadsheet had no seed value.</li>
            <li>Basic-FSI selection follows the scheme's own upgrade condition (e.g. Reg 33(5) 4.0 requires plot ≥ 4000 sqm AND road ≥ 18 m).</li>
        </ul>
        <?php if (!empty($calcs)): ?>
            <details class="mt-3">
                <summary style="cursor: pointer; color: #4338ca; font-weight: 700; font-size: 0.85rem;">
                    Full formula audit trail (<?= count($calcs) ?> rows)
                </summary>
                <table class="rpt-tbl" style="margin-top: 10px;">
                    <thead><tr><th>Row</th><th class="num">Value</th><th>Formula</th><th>DCPR Ref</th></tr></thead>
                    <tbody>
                        <?php foreach ($calcs as $c): ?>
                            <tr>
                                <td><b><?= e($c['calc_label']) ?></b><div class="text-muted small"><?= e($c['calc_key']) ?></div></td>
                                <td class="num"><?= number_format((float)$c['calc_value'], 4) ?> <small class="text-muted"><?= e($c['calc_unit']) ?></small></td>
                                <td class="small" style="max-width: 300px;"><?= e($c['formula'] ?? '—') ?></td>
                                <td class="ref"><?= e($c['regulation_ref']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
        <?php endif; ?>
    </section>

    <div class="rpt-footer">
        <div>BDVault · Area Statement <?= $ver ?></div>
        <div>Generated <?= date('d M Y H:i') ?></div>
    </div>
</div>

<?php $__aaChatSid = (int)$statement['id']; require __DIR__ . '/_ai_chat.php'; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
