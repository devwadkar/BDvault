<?php $pageTitle = 'Area Analysis v2 · Review'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/_shell.php'; ?>
<?php
// Comes from controller: $statement, $proposal, $draft, $s1, $calcSummary, $validations,
//                        $discrepancies, $landRows, $buildings, $fails, $warns, $stages, $currentStage
$reg = $statement['selected_regulation_key'];
$regTitle = (RegulationSuggester::REGULATIONS[$reg]['title'] ?? '—');
$isLocked = ($statement['status'] === 'locked');
$step2 = $draft['step_2'] ?? [];
$step3 = $draft['step_3'] ?? [];
$roadRows   = array_filter($landRows, fn($r) => str_starts_with($r['component_key'], 'road_'));
$namedRows  = array_filter($landRows, fn($r) => str_starts_with($r['component_key'], 'named_'));
$resvRow    = array_values(array_filter($landRows, fn($r) => $r['component_key'] === 'reservation'))[0] ?? null;
$amenRow    = array_values(array_filter($landRows, fn($r) => $r['component_key'] === 'amenity'))[0] ?? null;
$netRow     = array_values(array_filter($landRows, fn($r) => $r['component_key'] === 'net_plot'))[0] ?? null;
$grossRow   = array_values(array_filter($landRows, fn($r) => $r['component_key'] === 'gross_plot'))[0] ?? null;
$fmtN  = fn($v, $d = 2) => $v === null || $v === '' ? '—' : number_format((float)$v, $d);
?>

<style>
/* ─── Review-section card ─── */
.rv-card {
    background: #fff; border: 1px solid var(--v2-border); border-radius: 12px;
    padding: 20px 24px; margin-bottom: 14px;
}
.rv-head {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;
}
.rv-head .t {
    font-weight: 800; color: var(--v2-ink); font-size: 1.02rem; letter-spacing: -0.01em;
    display: flex; align-items: center; gap: 8px;
}
.rv-head .t .pin {
    width: 26px; height: 26px; border-radius: 7px; background: #eef2ff;
    display: inline-flex; align-items: center; justify-content: center;
    color: var(--v2-primary); font-size: 0.82rem;
}
.rv-head .edit {
    background: #fff; color: var(--v2-primary); border: 1px solid #c7d2fe;
    padding: 6px 14px; border-radius: 8px; font-size: 0.76rem; font-weight: 700;
    text-decoration: none; display: inline-flex; align-items: center; gap: 5px;
    transition: background .1s;
}
.rv-head .edit:hover { background: #eef2ff; color: var(--v2-primary-d); }
.rv-head .edit.locked { opacity: 0.4; pointer-events: none; }

/* ─── Facts grid ─── */
.rv-grid { display: grid; grid-template-columns: 180px 1fr; gap: 10px 18px; font-size: 0.84rem; }
.rv-grid .k { color: var(--v2-muted); font-weight: 500; align-self: center; }
.rv-grid .v { color: var(--v2-ink); font-weight: 600; align-self: center; }
.rv-grid .v.num { font-family: 'JetBrains Mono', Consolas, monospace; }
.rv-grid .v.unset { color: var(--v2-faint); font-weight: 400; font-style: italic; }

/* ─── Mini table (deductions, buildings) ─── */
.rv-tbl { width: 100%; font-size: 0.82rem; border-collapse: collapse; margin-top: 8px; }
.rv-tbl th {
    background: var(--v2-surface); color: var(--v2-muted); text-align: left;
    padding: 8px 10px; font-weight: 700; font-size: 0.68rem; text-transform: uppercase;
    letter-spacing: 0.04em; border-bottom: 1px solid var(--v2-border);
}
.rv-tbl td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.rv-tbl td.num { text-align: right; font-family: 'JetBrains Mono', Consolas, monospace; }
.rv-tbl tr.total-row td { background: #ecfdf5; color: #065f46; font-weight: 700; }
.rv-tbl tr.total-row { border-top: 2px solid #86efac; }
.rv-tbl .not-included { color: var(--v2-faint); font-style: italic; }

.pill-sm { padding: 2px 8px; border-radius: 999px; font-size: 0.65rem; font-weight: 700; letter-spacing: 0.02em; }
.pill-sm.yes { background: #d1fae5; color: #065f46; }
.pill-sm.no  { background: #f1f5f9; color: var(--v2-muted); }

/* ─── Calc preview tiles ─── */
.rv-metrics {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px; margin-top: 8px;
}
.rv-metric {
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    border: 1px solid #86efac; border-radius: 10px; padding: 12px 14px;
}
.rv-metric .k {
    font-size: 0.62rem; color: #047857; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700;
}
.rv-metric .v {
    font-family: 'JetBrains Mono', Consolas, monospace; font-weight: 800;
    font-size: 1.05rem; color: #065f46; margin-top: 2px;
}
.rv-metric .u { font-size: 0.64rem; color: #047857; margin-left: 3px; }

/* ─── Validations + discrepancies ─── */
.rv-check-list { display: flex; flex-direction: column; gap: 6px; }
.rv-check {
    display: grid; grid-template-columns: auto 1fr auto; gap: 10px;
    padding: 10px 12px; border-radius: 8px; align-items: center; font-size: 0.82rem;
}
.rv-check.pass { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
.rv-check.warn { background: #fef3c7; border: 1px solid #fcd34d; color: #92400e; }
.rv-check.fail { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
.rv-check .dot {
    width: 18px; height: 18px; border-radius: 50%; background: currentColor;
    display: inline-flex; align-items: center; justify-content: center;
    color: #fff; font-size: 0.68rem; font-weight: 800;
}
.rv-check .lab { font-weight: 700; }
.rv-check .msg { font-size: 0.74rem; opacity: 0.9; margin-top: 2px; }
.rv-check .side { font-family: 'JetBrains Mono', Consolas, monospace; font-size: 0.72rem; text-align: right; white-space: nowrap; }

/* ─── Disclaimer banner (always shown pre-lock) ─── */
.estimate-banner {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 1px solid #f59e0b; color: #78350f;
    border-radius: 12px; padding: 14px 20px; margin-bottom: 16px;
    display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap;
}
.estimate-banner.locked {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0); border-color: #10b981; color: #064e3b;
}
.estimate-banner .ico { font-size: 1.4rem; }
.estimate-banner .lead { font-weight: 700; font-size: 0.9rem; margin-bottom: 2px; }
.estimate-banner .sub  { font-size: 0.78rem; opacity: 0.88; }

/* ─── Generate CTA ─── */
.gen-wrap {
    background: #fff; border: 1px solid var(--v2-border); border-radius: 14px;
    padding: 22px 26px; margin-top: 14px;
    display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap;
}
.gen-wrap .msg .t { font-weight: 800; color: var(--v2-ink); font-size: 1rem; }
.gen-wrap .msg .d { color: var(--v2-muted); font-size: 0.82rem; margin-top: 3px; }
.gen-btn {
    background: linear-gradient(135deg, #059669, #047857); color: #fff;
    padding: 14px 28px; border-radius: 10px; font-weight: 800; font-size: 0.95rem;
    border: 0; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
    box-shadow: 0 4px 14px rgba(5, 150, 105, 0.3);
}
.gen-btn:hover { background: linear-gradient(135deg, #047857, #065f46); transform: translateY(-1px); }
.gen-btn:disabled {
    background: #e2e8f0; color: var(--v2-muted); cursor: not-allowed; box-shadow: none; transform: none;
}
</style>

<!-- ─── Disclaimer banner ─── -->
<?php if ($isLocked): ?>
    <div class="estimate-banner locked">
        <span class="ico">✓</span>
        <div style="flex:1;">
            <div class="lead">Final Area Statement — generated and locked</div>
            <div class="sub">Locked <?= e(date('d M Y, H:i', strtotime($statement['locked_at']))) ?>. To edit, create a new version.</div>
        </div>
        <a href="index.php?page=area_analysis_v2_stage5&statement_id=<?= (int)$statement['id'] ?>" class="v2-btn primary">
            View statement <i class="bi bi-arrow-right"></i>
        </a>
    </div>
<?php else: ?>
    <div class="estimate-banner">
        <span class="ico">⚠️</span>
        <div style="flex:1;">
            <div class="lead">Calculated estimate — not yet a formally verified area statement</div>
            <div class="sub">Review every section below. Nothing here is legally approved until you Generate and the statement is locked.</div>
        </div>
    </div>
<?php endif; ?>

<!-- ─── Section 1: Project basics (edit → Stage 1) ─── -->
<div class="rv-card">
    <div class="rv-head">
        <div class="t"><span class="pin">1</span> Project basics</div>
        <a href="index.php?page=area_analysis_v2_stage1&statement_id=<?= (int)$statement['id'] ?>"
           class="edit <?= $isLocked ? 'locked' : '' ?>"><i class="bi bi-pencil-square"></i> Edit</a>
    </div>
    <div class="rv-grid">
        <div class="k">Project name</div>       <div class="v"><?= e($statement['project_name']) ?></div>
        <div class="k">CTS / Survey number</div><div class="v"><?= e($statement['cts_number'] ?: '—') ?></div>
        <div class="k">Plot address</div>       <div class="v"><?= e($statement['plot_address']) ?></div>
        <div class="k">City · Region</div>      <div class="v"><?= e($statement['project_location']) ?></div>
        <div class="k">Ward</div>               <div class="v"><?= e($statement['ward']) ?></div>
        <div class="k">Zone (DP)</div>          <div class="v"><?= e($s1['zone_dp'] ?? '—') ?></div>
        <div class="k">Planning authority</div> <div class="v"><?= e($statement['planning_authority']) ?></div>
        <div class="k">Development category</div><div class="v"><?= e(ucfirst(str_replace('_',' ', $statement['development_type']))) ?></div>
        <div class="k">Island City plot?</div>  <div class="v">
            <span class="pill-sm <?= !empty($s1['is_island_city']) ? 'yes' : 'no' ?>">
                <?= !empty($s1['is_island_city']) ? 'Yes' : 'No' ?>
            </span>
        </div>
    </div>
</div>

<!-- ─── Section 2: Regulation (edit → Stage 2) ─── -->
<div class="rv-card">
    <div class="rv-head">
        <div class="t"><span class="pin">2</span> Regulation</div>
        <a href="index.php?page=area_analysis_v2_stage2&statement_id=<?= (int)$statement['id'] ?>"
           class="edit <?= $isLocked ? 'locked' : '' ?>"><i class="bi bi-pencil-square"></i> Change</a>
    </div>
    <div class="rv-grid">
        <div class="k">Selected</div>
        <div class="v"><b>DCPR <?= e($reg) ?></b> — <?= e($regTitle) ?></div>
        <?php if ($statement['suggested_regulation_key']): ?>
            <div class="k">Suggested</div>
            <div class="v">DCPR <?= e($statement['suggested_regulation_key']) ?>
                <span class="pill-sm" style="background:#eef2ff;color:var(--v2-primary-d);">
                    <?= (int)$statement['suggested_confidence'] ?>% confidence
                </span>
                <?php if ($statement['suggested_regulation_key'] !== $reg): ?>
                    <span style="color:var(--v2-warning);font-size:0.76rem;margin-left:6px;">· manually overridden</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ─── Section 3: Regulation-specific inputs (edit → Stage 3) ─── -->
<div class="rv-card">
    <div class="rv-head">
        <div class="t"><span class="pin">3</span> Regulation-specific inputs</div>
        <a href="index.php?page=area_analysis_v2_stage3&statement_id=<?= (int)$statement['id'] ?>"
           class="edit <?= $isLocked ? 'locked' : '' ?>"><i class="bi bi-pencil-square"></i> Edit</a>
    </div>

    <!-- Plot & deductions mini-table -->
    <div style="font-weight:700;color:var(--v2-ink);font-size:0.84rem;margin:4px 0 4px;">Plot &amp; deductions</div>
    <table class="rv-tbl">
        <thead>
            <tr>
                <th>Component</th>
                <th>Label</th>
                <th style="text-align:right;">Area (sqm)</th>
                <th style="text-align:right;">Area (sqft)</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sqmSqft = fn($v) => number_format(((float)$v) * 10.7639, 2);
            $renderLandRow = function($r, $isTotal = false) use ($fmtN, $sqmSqft) {
                $cls = $isTotal ? ' class="total-row"' : '';
                $isIncluded = ($r['is_deduction'] && !$r['include_in_total_deductions']);
                ?>
                <tr<?= $cls ?>>
                    <td><?= e($r['component_key']) ?></td>
                    <td><?= e($r['component_label']) ?>
                        <?php if (!empty($r['road_width_m'])): ?>
                            <span style="color:var(--v2-faint);font-size:0.74rem;">· width <?= $fmtN($r['road_width_m']) ?> m</span>
                        <?php endif; ?>
                    </td>
                    <td class="num"><?= $fmtN($r['area_sqm']) ?></td>
                    <td class="num"><?= $sqmSqft($r['area_sqm']) ?></td>
                    <td><?php if ($isIncluded): ?>
                            <span class="not-included">retains FSI</span>
                        <?php elseif (!$r['is_deduction']): ?>
                            <span class="pill-sm" style="background:#eef2ff;color:var(--v2-primary-d);">computed</span>
                        <?php else: ?>
                            <span class="pill-sm yes">deducted</span>
                        <?php endif; ?></td>
                </tr>
                <?php
            };
            if ($grossRow) $renderLandRow($grossRow);
            foreach ($roadRows as $r) $renderLandRow($r);
            foreach ($namedRows as $r) $renderLandRow($r);
            if ($resvRow) $renderLandRow($resvRow);
            if ($amenRow) $renderLandRow($amenRow);
            if ($netRow)  $renderLandRow($netRow, true);
            ?>
        </tbody>
    </table>

    <?php if (!empty($buildings)): ?>
    <div style="font-weight:700;color:var(--v2-ink);font-size:0.84rem;margin:18px 0 4px;">Existing buildings roster</div>
    <table class="rv-tbl">
        <thead>
            <tr>
                <th>Label</th>
                <th style="text-align:right;">Res units</th>
                <th style="text-align:right;">Com units</th>
                <th style="text-align:right;">Res carpet (sqm)</th>
                <th style="text-align:right;">BUA (sqm)</th>
                <th style="text-align:right;">Tenants</th>
                <th style="text-align:right;">Eligible</th>
            </tr>
        </thead>
        <tbody>
            <?php $totTen = 0; $totElig = 0; $totBUA = 0; foreach ($buildings as $b):
                $totTen += (int)$b['tenant_count'];
                $totElig += (int)$b['eligible_tenant_count'];
                $totBUA += (float)$b['existing_bua_sqm'];
            ?>
                <tr>
                    <td><?= e($b['building_label']) ?></td>
                    <td class="num"><?= (int)$b['residential_units'] ?></td>
                    <td class="num"><?= (int)$b['commercial_units'] ?></td>
                    <td class="num"><?= $fmtN($b['residential_carpet_sqm']) ?></td>
                    <td class="num"><?= $fmtN($b['existing_bua_sqm']) ?></td>
                    <td class="num"><?= (int)$b['tenant_count'] ?></td>
                    <td class="num"><?= (int)$b['eligible_tenant_count'] ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="4">Totals</td>
                <td class="num"><?= $fmtN($totBUA) ?></td>
                <td class="num"><?= $totTen ?></td>
                <td class="num"><?= $totElig ?></td>
            </tr>
        </tbody>
    </table>
    <?php endif; ?>

    <?php
    // Rehab drivers (33(5) and 33(10) only)
    $hasRehabDrivers = isset($step3['s3_carpet_res_sqm']) || isset($step3['s3_loading_factor']) || isset($step3['s3_eligible_total']);
    if ($hasRehabDrivers): ?>
    <div style="font-weight:700;color:var(--v2-ink);font-size:0.84rem;margin:18px 0 4px;">Rehab drivers</div>
    <div class="rv-grid">
        <?php if (!empty($step3['s3_eligible_total'])): ?>
            <div class="k">Eligible tenements</div>
            <div class="v num"><?= $fmtN($step3['s3_eligible_total'], 0) ?></div>
        <?php endif; ?>
        <?php if (!empty($step3['s3_carpet_res_sqm'])): ?>
            <div class="k">Residential carpet</div>
            <div class="v num"><?= $fmtN($step3['s3_carpet_res_sqm']) ?> sqm</div>
        <?php endif; ?>
        <?php if (!empty($step3['s3_carpet_com_sqm'])): ?>
            <div class="k">Commercial carpet</div>
            <div class="v num"><?= $fmtN($step3['s3_carpet_com_sqm']) ?> sqm</div>
        <?php endif; ?>
        <?php if (!empty($step3['s3_loading_factor'])): ?>
            <div class="k">Loading factor</div>
            <div class="v num"><?= $fmtN($step3['s3_loading_factor'], 3) ?></div>
        <?php endif; ?>
        <?php if (!empty($step3['s3_amenity_built_sqm'])): ?>
            <div class="k">Amenity built BUA</div>
            <div class="v num"><?= $fmtN($step3['s3_amenity_built_sqm']) ?> sqm</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ─── Section 4: Computed values ─── -->
<div class="rv-card">
    <div class="rv-head">
        <div class="t"><span class="pin">4</span> Computed area statement
            <span style="color:var(--v2-faint);font-weight:400;font-size:0.76rem;letter-spacing:0;text-transform:none;">(from DCPR <?= e($reg) ?> calculator)</span>
        </div>
    </div>
    <div class="rv-metrics">
        <div class="rv-metric">
            <div class="k">Net plot</div>
            <div class="v"><?= $fmtN($calcSummary['net_plot']) ?><span class="u"> sqm</span></div>
        </div>
        <div class="rv-metric">
            <div class="k">Total FSI</div>
            <div class="v"><?= $fmtN($calcSummary['total_fsi'], 4) ?></div>
        </div>
        <div class="rv-metric">
            <div class="k">Total BUA</div>
            <div class="v"><?= $fmtN($calcSummary['total_bua']) ?><span class="u"> sqm</span></div>
        </div>
        <div class="rv-metric">
            <div class="k">Rehab BUA</div>
            <div class="v"><?= $fmtN($calcSummary['rehab_bua']) ?><span class="u"> sqm</span></div>
        </div>
        <div class="rv-metric">
            <div class="k">Sale BUA</div>
            <div class="v"><?= $fmtN($calcSummary['sale_bua']) ?><span class="u"> sqm</span></div>
        </div>
    </div>
    <div style="margin-top:10px;font-size:0.76rem;color:var(--v2-muted);">
        Computed <?= e(date('d M Y, H:i', strtotime($calcSummary['calculated_at']))) ?>.
        Open the <a href="index.php?page=area_analysis_feasibility&statement_id=<?= (int)$statement['id'] ?>" style="color:var(--v2-primary);">21-row Feasibility Statement</a>
        for the full DCPR breakdown.
    </div>

    <?php
    // In-situ confirmation panel — matches the resolution logic in feasibility_statement.php
    $saleInSituFin  = isset($financials['sale_in_situ_sqm']) ? (float)$financials['sale_in_situ_sqm'] : null;
    $saleInSituWiz  = isset($step3['sale_bua_proposed_in_situ_sqm']) && $step3['sale_bua_proposed_in_situ_sqm'] !== ''
                      ? (float)$step3['sale_bua_proposed_in_situ_sqm'] : null;
    $isAutoMax = ($saleInSituFin === null || $saleInSituFin <= 0) && ($saleInSituWiz === null || $saleInSituWiz <= 0);
    if ($isAutoMax):
    ?>
    <div style="margin-top:14px;background:#fef3c7;border:1px solid #fcd34d;border-radius:10px;padding:12px 16px;color:#92400e;font-size:0.82rem;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div>
            <b>Sale BUA In-Situ is auto-computed.</b>
            Row 15 shows the maximum allowable value, not what you plan to actually build.
            Set an explicit value in Stage 3 (In-situ sale section) or on the Financials form, or lock knowing it's a theoretical max.
        </div>
        <a href="index.php?page=area_analysis_v2_stage3&statement_id=<?= (int)$statement['id'] ?>#insitu"
           style="background:#92400e;color:#fff;padding:7px 14px;border-radius:8px;font-weight:700;text-decoration:none;font-size:0.78rem;white-space:nowrap;">
            Set in Stage 3
        </a>
    </div>
    <?php else: ?>
    <div style="margin-top:14px;background:#d1fae5;border:1px solid #86efac;border-radius:10px;padding:10px 16px;color:#065f46;font-size:0.82rem;">
        <i class="bi bi-check-circle-fill"></i>
        <b>Sale BUA In-Situ confirmed.</b>
        Source: <?= $saleInSituFin ? 'Financials form' : 'Stage 3 wizard' ?>.
    </div>
    <?php endif; ?>
</div>

<!-- ─── Section 5: Validations ─── -->
<div class="rv-card">
    <div class="rv-head">
        <div class="t"><span class="pin">5</span> Validation checks
            <span style="color:var(--v2-faint);font-weight:400;font-size:0.76rem;letter-spacing:0;text-transform:none;">(<?= $calcSummary['validations']['pass'] ?> pass<?= $warns ? ', ' . $warns . ' warn' : '' ?><?= $fails ? ', ' . $fails . ' fail' : '' ?>)</span>
        </div>
    </div>
    <?php if (empty($validations)): ?>
        <div style="color:var(--v2-faint);font-size:0.84rem;padding:14px 0;">No validations ran.</div>
    <?php else: ?>
        <div class="rv-check-list">
            <?php foreach ($validations as $v):
                $icon = $v['result'] === 'pass' ? '✓' : ($v['result'] === 'warn' ? '!' : '✗'); ?>
                <div class="rv-check <?= e($v['result']) ?>">
                    <span class="dot"><?= $icon ?></span>
                    <div>
                        <div class="lab"><?= e($v['check_label']) ?></div>
                        <?php if ($v['message']): ?><div class="msg"><?= e($v['message']) ?></div><?php endif; ?>
                    </div>
                    <div class="side">
                        <?php if ($v['entered_value'] !== null): ?>
                            got: <?= e($v['entered_value']) ?>
                            <?php if ($v['required_value'] !== null): ?><br>need: <?= e($v['required_value']) ?><?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ─── Section 6: Formula discrepancies ─── -->
<?php if (!empty($discrepancies)): ?>
<div class="rv-card">
    <div class="rv-head">
        <div class="t"><span class="pin">6</span> Formula cross-checks
            <span style="color:var(--v2-faint);font-weight:400;font-size:0.76rem;letter-spacing:0;text-transform:none;">(<?= count($discrepancies) ?> flagged — reconcile before locking)</span>
        </div>
        <a href="index.php?page=area_analysis_discrepancies&statement_id=<?= (int)$statement['id'] ?>" class="edit">
            <i class="bi bi-arrows-angle-expand"></i> Open full detail
        </a>
    </div>
    <div class="rv-check-list">
        <?php foreach ($discrepancies as $d): ?>
            <div class="rv-check <?= $d['severity'] === 'error' ? 'fail' : ($d['severity'] === 'warning' ? 'warn' : 'pass') ?>">
                <span class="dot">!</span>
                <div>
                    <div class="lab"><?= e($d['check_label']) ?></div>
                    <?php if ($d['formula']): ?><div class="msg" style="font-family:'JetBrains Mono',Consolas,monospace;"><?= e($d['formula']) ?></div><?php endif; ?>
                </div>
                <div class="side">
                    expected: <?= $fmtN($d['expected_value']) ?><br>
                    actual: <?= $fmtN($d['actual_value']) ?><br>
                    Δ: <?= $fmtN($d['variance_pct']) ?>%
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ─── Generate CTA ─── -->
<div class="gen-wrap">
    <div class="msg">
        <?php if ($isLocked): ?>
            <div class="t">✓ Already generated and locked.</div>
            <div class="d">Open the final statement, or create a new version to make further edits.</div>
        <?php elseif ($fails > 0): ?>
            <div class="t">Can't generate yet — <?= $fails ?> validation error<?= $fails !== 1 ? 's' : '' ?> to fix.</div>
            <div class="d">Return to Stage 3 and adjust inputs so every red check turns green or amber.</div>
        <?php else: ?>
            <div class="t">Ready to generate.</div>
            <div class="d">Generating locks this version — treat it as the formally produced Area Statement. Make a new version to edit later.
                <?php if ($warns > 0): ?><br><b><?= $warns ?> warning<?= $warns !== 1 ? 's' : '' ?></b> acknowledged — generation can still proceed.<?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <?php if (!$isLocked): ?>
            <a href="index.php?page=area_analysis_v2_stage3&statement_id=<?= (int)$statement['id'] ?>" class="v2-btn ghost">
                <i class="bi bi-arrow-left"></i> Back to inputs
            </a>
            <form method="POST" action="index.php?page=area_analysis_v2_generate" style="margin:0;">
                <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
                <button type="submit" class="gen-btn" <?= $fails > 0 ? 'disabled' : '' ?>
                        onclick="return confirm('Generate and lock this Area Statement? You can create a new version later to make changes.')">
                    <i class="bi bi-file-earmark-check"></i> Generate &amp; lock
                </button>
            </form>
        <?php else: ?>
            <a href="index.php?page=area_analysis_v2_stage5&statement_id=<?= (int)$statement['id'] ?>" class="gen-btn" style="text-decoration:none;">
                <i class="bi bi-file-earmark-check"></i> Open final statement
            </a>
        <?php endif; ?>
    </div>
</div>

<?php _v2_shell_close($statement, $s1, $draft['_meta']['stage3_saved_at'] ?? ($draft['_meta']['stage1_saved_at'] ?? null)); ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
