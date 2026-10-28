<?php $pageTitle = 'Feasibility Statement — ' . e($statement['statement_name']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php
$flt = function ($v): ?float {
    if ($v === null || $v === '' || !is_numeric($v)) return null;
    return (float)$v;
};

$s          = $statement;
$reg        = $s['selected_regulation_key'] ?? '';
$projectAdr = $s['plot_address'] ?: ($s['project_name'] . ($s['ward'] ? ', Ward ' . $s['ward'] : ''));
$ctsNo      = $s['cts_number'] ?: '—';
$roadWidth  = (float)($draft['step_2']['primary_road_width_m'] ?? 0);
$financials = $financials ?? [];
$rrLand     = $flt($financials['snapshot_rr_land_rate']    ?? null) ?? (float)($ctx['asr']['land_rate_per_sqm']         ?? 0);
$rrConstr   = $flt($financials['snapshot_rr_construction'] ?? null) ?? (float)($ctx['asr']['construction_rate_per_sqm'] ?? 0);

// Section A — derive from the ordered land rows (Phase G2: dynamic)
$get = fn($k) => (float)($land[$k]['area_sqm'] ?? 0);
$gross = $get('gross_plot');
$netPlot = $get('net_plot');

// Walk the ordered deduction rows for display
$deductionRows = [];         // each: [label_for_display, area, sqft_area, include_flag]
$totalDed      = 0.0;
foreach ($land as $k => $r) {
    if ((int)$r['is_deduction'] !== 1) continue;
    $labelParts = [$r['component_label']];
    if (!empty($r['label_extra']))    $labelParts[] = '(' . $r['label_extra'] . ')';
    $label = implode(' ', array_filter($labelParts));
    $area  = (float)$r['area_sqm'];
    $inc   = (int)$r['include_in_total_deductions'] === 1;
    $deductionRows[] = ['label' => $label, 'area' => $area, 'include' => $inc, 'reg_ref' => $r['regulation_ref']];
    if ($inc) $totalDed += $area;
}
$netPlotDerived = max(0, $gross - $totalDed);

// Pull calculator's outputs for the BUA rows
$calcVal = function ($keys) use ($calcs) {
    foreach ($calcs as $c) if (in_array($c['calc_key'], (array)$keys, true)) return (float)$c['calc_value'];
    return null;
};
// Regulation-aware key resolution. The classic 21-row feasibility layout was designed
// against 33(10) SRA; 33(11)/33(5)/33(20)(B) emit different key names. Each row prefers
// the calc-emitted value that best matches its intent for the current regulation.
$reg = $s['selected_regulation_key'] ?: $s['suggested_regulation_key'];

// Row 9 Rehab BUA — 33(10): rehab_tenement_bua_sqm · 33(11): society_bua_sqm + sra_ptc handover
// · 33(5): total_rehab_bua_sqm · 33(20)(B): rehab_bua_sqm
$rehabBldg = $flt($financials['rehab_bua_override_sqm'] ?? null)
             ?? $calcVal(['rehab_tenement_bua_sqm', 'total_rehab_bua_sqm', 'rehab_bua_sqm',
                          'society_bua_sqm', 'sra_ptc_share_bua_sqm'])
             ?? (float)($s['rehab_bua_sqm'] ?? 0);

// Row 10 Amenity — 33(10) has explicit amenity block; other schemes don't.
$amenityInternal = $flt($financials['amenity_bua_override_sqm'] ?? null)
                   ?? $calcVal(['rehab_amenity_block_sqm', 'amenity_common_area_sqm', 'amenity_bua_sqm'])
                   ?? 0.0;

// Row 11 Rehab Component — for 33(10) it's tenement + amenity; for 33(11) it's the
// handover total; for 33(5) it's the rehab area total.
$rehabComponent   = $calcVal(['rehab_component_sqm']) ?? ($rehabBldg + $amenityInternal);

// Effective sale ratio — 33(10) emits sale_ratio_effective; other schemes have implicit
// ratios embedded in their FSI split.
$saleRatio = $calcVal(['sale_ratio_effective', 'incentive_ratio', 'incentive_ratio_used', 'sale_incentive_ratio']) ?? 1.15;

// Row 12 Sale Component — prefer the calc's own sale total; fall back to rehab × ratio.
$saleComponent    = $calcVal(['sale_component_sqm', 'sale_bua_sqm', 'sale_society_total_sqm',
                              'owner_total_sqm'])
                    ?? ($rehabComponent * $saleRatio);

// Row 13 Total BUA — prefer full_scheme_bua_sqm or total_bua_approved_sqm.
$totalBuaApproved = $calcVal(['total_bua_approved_sqm', 'full_scheme_bua_sqm'])
                    ?? ($rehabBldg + $saleComponent);

// Row 14 FSI Sanctioned — prefer the calc's own emission; else derive.
$fsiSanctioned    = $calcVal(['fsi_sanctioned', 'full_scheme_fsi'])
                    ?? ($netPlotDerived > 0 ? $totalBuaApproved / $netPlotDerived : 0);

// Row 20 Sale Fungible + Row 21 Gross Sale Permissible — prefer the calc row if present.
$saleFungible     = $calcVal(['sale_fungible_sqm', 'sale_society_fungible_sqm', 'sra_ptc_fungible_sqm'])
                    ?? ($saleComponent * 0.35);
$netSalePerm      = $saleComponent;
$grossSalePerm    = $calcVal(['gross_sale_bua_permissible_sqm']) ?? ($netSalePerm + $saleFungible);

// In-situ BUA + TDR balance.
// Resolution order for Sale In-Situ (Row 15):
//   1. User-set on the Financials form (sale_in_situ_sqm)
//   2. User-set via Stage 3 input (sale_bua_proposed_in_situ_sqm) → emitted by the calculator
//   3. Auto-fallback = max allowable = min(sale component, FSI-cap headroom after rehab)
// $saleInSituSource = 'user_fin' | 'user_wiz' | 'auto_max' — drives the badge next to Row 15.
require_once __DIR__ . '/../../lib/RulesRegistry.php';
$fsiCap            = (float)(RulesRegistry::getOrNull('in_situ_fsi_cap', $reg) ?? 4.0);
$saleInSituMax     = max(0, min($saleComponent, ($gross * $fsiCap) - $rehabBldg));

$saleInSituFin  = $flt($financials['sale_in_situ_sqm'] ?? null);
$saleInSituWiz  = $flt(($draft['step_3']['sale_bua_proposed_in_situ_sqm'] ?? null));
$saleInSituCalc = $calcVal(['sale_bua_proposed_in_situ_sqm']);

if ($saleInSituFin !== null && $saleInSituFin > 0) {
    $saleInSitu = $saleInSituFin;
    $saleInSituSource = 'user_fin';
} elseif ($saleInSituWiz !== null && $saleInSituWiz > 0) {
    $saleInSitu = $saleInSituWiz;
    $saleInSituSource = 'user_wiz';
} elseif ($saleInSituCalc !== null && $saleInSituCalc > 0) {
    $saleInSitu = $saleInSituCalc;
    $saleInSituSource = 'user_wiz';
} else {
    $saleInSitu = $saleInSituMax;
    $saleInSituSource = 'auto_max';
}

$totalBuaProposed  = $rehabBldg + $saleInSitu;                                  // Row 16 = Row 9 + Row 15
$fsiConsumedInSitu = $gross > 0 ? $totalBuaProposed / $gross : 0;               // Row 17 = Row 16 / Gross
$balanceSaleTDR    = max(0, $saleComponent - $saleInSitu);                      // Row 18 = Row 12 − Row 15

$sqft = fn($v) => $v > 0 ? $v * 10.7639 : 0;
$fmt  = fn($v) => $v > 0 ? number_format($v, 2) : '—';
$fmtF = fn($v) => $v > 0 ? number_format($v, 3) : '—';

// Sequential row-number counter — reset at start of Section A
$srNo = 0;
?>

<style>
    .fs-actions { display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
    .fs-btn { padding: 7px 14px; border-radius: 8px; font-size: 0.78rem; font-weight: 700; text-decoration: none; border: 0; cursor: pointer; }
    .fs-back    { background: #f1f5f9; color: #475569; }
    .fs-print   { background: #4f46e5; color: #fff; }
    .fs-preview { background: #0ea5e9; color: #fff; }
    .fs-excel   { background: #10b981; color: #fff; }
    .fs-edit    { background: #f59e0b; color: #fff; }

    /* Print-preview overlay — mirrors what @media print will render. */
    body.fs-preview-mode { background: #cbd5e1; overflow-x: hidden; }
    body.fs-preview-mode .premium-navbar,
    body.fs-preview-mode .sidebar-container,
    body.fs-preview-mode nav,
    body.fs-preview-mode header,
    body.fs-preview-mode aside,
    body.fs-preview-mode footer,
    body.fs-preview-mode .fs-actions { display: none !important; }
    body.fs-preview-mode > .main-content,
    body.fs-preview-mode > div[class*="content"],
    body.fs-preview-mode > main {
        margin: 0 !important; padding: 20px !important; width: 100% !important;
    }
    body.fs-preview-mode .fs-wrap {
        margin: 40px auto !important; padding: 48px 60px !important;
        box-shadow: 0 12px 40px rgba(15,23,42,0.25) !important;
        max-width: 820px !important;  /* ≈ A4 width at 96dpi */
        min-height: 1080px;           /* ≈ A4 height */
        border: 0 !important;
    }
    .fs-preview-bar {
        position: fixed; top: 0; left: 0; right: 0; z-index: 10000;
        background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;
        padding: 10px 24px; display: none;
        justify-content: space-between; align-items: center; gap: 10px;
        box-shadow: 0 4px 16px rgba(15,23,42,0.4); font-family: 'Lexend', system-ui, sans-serif;
    }
    body.fs-preview-mode .fs-preview-bar { display: flex; }
    .fs-preview-bar .lbl { font-size: 0.82rem; font-weight: 700; letter-spacing: 0.02em; }
    .fs-preview-bar .lbl .dim { color: #94a3b8; font-weight: 400; margin-left: 8px; font-size: 0.72rem; }
    .fs-preview-bar .actions { display: flex; gap: 8px; }
    .fs-preview-bar button {
        background: rgba(255,255,255,0.12); color: #fff; border: 1px solid rgba(255,255,255,0.2);
        padding: 6px 14px; border-radius: 6px; font-size: 0.78rem; font-weight: 700;
        cursor: pointer; font-family: inherit;
    }
    .fs-preview-bar button:hover { background: rgba(255,255,255,0.22); }
    .fs-preview-bar button.primary { background: #10b981; border-color: #10b981; }
    .fs-preview-bar button.primary:hover { background: #059669; }
    body.fs-preview-mode { padding-top: 56px !important; /* space for the fixed bar */ }

    .fs-wrap { max-width: 1000px; margin: 0 auto; background: #fff; border: 1px solid #cbd5e1; padding: 26px 34px;
               font-family: 'Times New Roman', 'Liberation Serif', serif; color: #0f172a; box-shadow: 0 6px 20px rgba(15,23,42,0.05); }
    .fs-title { text-align: center; font-weight: 700; font-size: 1rem; line-height: 1.5;
                border: 1px solid #0f172a; padding: 10px 16px; margin: 0 0 16px;
                text-transform: uppercase; letter-spacing: 0.03em; }
    .fs-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .fs-table th, .fs-table td { border: 1px solid #0f172a; padding: 6px 10px; vertical-align: top; }
    .fs-table thead th { background: #f1f5f9; font-weight: 700; text-align: center; text-transform: uppercase; font-size: 0.78rem; letter-spacing: 0.02em; }
    .fs-table td.n  { text-align: center; width: 42px; font-weight: 600; }
    .fs-table td.p  { min-width: 260px; }
    .fs-table td.a  { text-align: right; font-family: 'Consolas', 'Liberation Mono', monospace; white-space: nowrap; min-width: 110px; }
    .fs-section-hdr td { background: #cbd5e1; font-weight: 800; text-transform: uppercase; font-size: 0.78rem; letter-spacing: 0.06em; text-align: left; }
    .fs-total     td { background: #fef3c7; font-weight: 700; }
    .fs-total-big td { background: #dbeafe; font-weight: 800; font-size: 0.9rem; }
    .fs-fsi       td { background: #dcfce7; font-weight: 700; }
    .fs-grand     td { background: #6366f1; color: #fff; font-weight: 800; font-size: 0.95rem; }
    .fs-excluded  td { background: #fafafa; color: #64748b; font-style: italic; }
    .fs-note { font-size: 0.75rem; color: #64748b; margin-top: 14px; padding-top: 10px; border-top: 1px dashed #cbd5e1; font-family: 'Segoe UI', system-ui, sans-serif; }
    .fs-note code { background: #f1f5f9; padding: 1px 6px; border-radius: 4px; font-family: 'Consolas', monospace; }
    @media print {
        body { background: #fff; }
        .fs-actions, .navbar, aside, header, footer, .premium-navbar, nav, .sidebar-container { display: none !important; }
        .fs-wrap { margin: 0; padding: 0; max-width: none; box-shadow: none; border: 0; }
    }
</style>

<div class="fs-actions">
    <a href="index.php?page=area_analysis_report&statement_id=<?= (int)$s['id'] ?>" class="fs-btn fs-back">
        <i class="bi bi-arrow-left me-1"></i>Back to Report
    </a>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($s['status'] !== 'locked'): ?>
            <a href="index.php?page=area_analysis_step&statement_id=<?= (int)$s['id'] ?>&step=2" class="fs-btn fs-edit">
                <i class="bi bi-pencil me-1"></i>Edit Land Inputs
            </a>
        <?php endif; ?>
        <?php
        // Open-discrepancy count for the badge
        $__dcCount = 0;
        try {
            $__dcRes = getDB()->query("SELECT COUNT(*) c FROM formula_discrepancies WHERE statement_id = " . (int)$s['id'] . " AND resolved = 0");
            if ($__dcRes) { $__row = $__dcRes->fetch_assoc(); $__dcCount = (int)$__row['c']; }
        } catch (Throwable $__t) {}
        $__dcBg = $__dcCount === 0 ? '#10b981' : ($__dcCount >= 3 ? '#dc2626' : '#f59e0b');
        ?>
        <form method="POST" action="index.php?page=area_analysis_refresh_calc" style="display:inline;">
            <input type="hidden" name="statement_id" value="<?= (int)$s['id'] ?>">
            <input type="hidden" name="redirect_to" value="index.php?page=area_analysis_feasibility&statement_id=<?= (int)$s['id'] ?>">
            <button type="submit" class="fs-btn" style="background:#0284c7;color:#fff;" title="Re-run the calc engine — picks up amenity + registry + regulation changes">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh calc
            </button>
        </form>
        <?php
        // Active-overrides count for badge
        $__ovCount = 0;
        try {
            $__ovRes = getDB()->query("SELECT COUNT(*) c FROM area_statement_calc_overrides WHERE statement_id = " . (int)$s['id'] . " AND active = 1");
            if ($__ovRes) { $__ovRow = $__ovRes->fetch_assoc(); $__ovCount = (int)$__ovRow['c']; }
        } catch (Throwable $__t) {}
        ?>
        <a href="index.php?page=area_analysis_overrides&statement_id=<?= (int)$s['id'] ?>" class="fs-btn fs-back"
           style="background:<?= $__ovCount > 0 ? '#d97706' : '#78716c' ?>;color:#fff;" title="Manual value overrides with audit trail">
            <i class="bi bi-pencil-square me-1"></i>Overrides
            <?php if ($__ovCount > 0): ?><span style="background:rgba(255,255,255,0.35);padding:1px 8px;border-radius:999px;margin-left:4px;font-weight:800;"><?= $__ovCount ?></span><?php endif; ?>
        </a>
        <a href="index.php?page=area_analysis_discrepancies&statement_id=<?= (int)$s['id'] ?>" class="fs-btn fs-back"
           style="background: <?= $__dcBg ?>; color: #fff;" title="Formula discrepancies detected by cross-check identities">
            <i class="bi bi-diagram-3 me-1"></i>Discrepancies
            <?php if ($__dcCount > 0): ?><span style="background:rgba(255,255,255,0.35);padding:1px 8px;border-radius:999px;margin-left:4px;font-weight:800;"><?= $__dcCount ?></span><?php endif; ?>
        </a>
        <a href="index.php?page=area_analysis_pdf_report&statement_id=<?= (int)$s['id'] ?>" class="fs-btn" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;">
            <i class="bi bi-file-earmark-pdf-fill me-1"></i>20-Section PDF
        </a>
        <a href="index.php?page=area_analysis_feasibility_excel&statement_id=<?= (int)$s['id'] ?>" class="fs-btn fs-excel">
            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
        </a>
        <button class="fs-btn fs-preview" type="button" onclick="fsEnterPreview()">
            <i class="bi bi-eye me-1"></i>Print Preview
        </button>
        <button class="fs-btn fs-print" type="button" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>
</div>

<!-- Print-preview toolbar (visible only when preview mode is on) -->
<div class="fs-preview-bar" id="fsPreviewBar">
    <div class="lbl">
        <i class="bi bi-eye-fill me-1"></i>Print Preview
        <span class="dim">— what your printed / PDF version will look like</span>
    </div>
    <div class="actions">
        <button type="button" onclick="window.print()" class="primary">
            <i class="bi bi-printer me-1"></i>Print Now
        </button>
        <button type="button" onclick="fsExitPreview()">
            <i class="bi bi-x-lg me-1"></i>Exit Preview
        </button>
    </div>
</div>

<script>
function fsEnterPreview() {
    document.body.classList.add('fs-preview-mode');
    window.scrollTo({ top: 0, behavior: 'instant' });
    // Escape key exits preview
    document.addEventListener('keydown', fsHandleEsc);
}
function fsExitPreview() {
    document.body.classList.remove('fs-preview-mode');
    document.removeEventListener('keydown', fsHandleEsc);
}
function fsHandleEsc(e) { if (e.key === 'Escape') fsExitPreview(); }
</script>

<?php
// Stale-calc detection: for 33(10), if rehab_component_sqm exists but
// rehab_amenity_block_sqm is 0 (or missing), the calc predates the amenity fix.
$__stale = false;
if (($reg ?? '') === '33(10)') {
    $__rehabComp = $calcVal(['rehab_component_sqm']);
    $__amenBlock = $calcVal(['rehab_amenity_block_sqm']);
    if ($__rehabComp !== null && $__rehabComp > 0 && (!$__amenBlock || $__amenBlock < 0.01)) {
        $__stale = true;
    }
}
// Cross-scheme mapping note — the 21-row layout was designed for 33(10) SRA. For
// 33(11) PTC and other schemes some rows map to different calc keys; a few (like
// Row 10 Amenity Area) genuinely don't exist and stay blank.
$__schemeNote = null;
if (in_array($reg, ['33(11)', '33(5)', '33(20)(B)'], true)) {
    $__schemeMap = [
        '33(11)' => 'Row 9 (Rehab BUA) shows Society Rehab BUA · Row 10 (Amenity) is not applicable · Row 11 (Rehab Component) uses rehab_component_sqm if present · Row 12 (Sale) shows sale_society_total_sqm · Row 13 (Total BUA) shows full_scheme_bua_sqm.',
        '33(5)'  => 'Rows 9–13 pull from total_rehab_bua_sqm / rehab_component_sqm / full_scheme_bua_sqm as emitted by the MHADA calculator. Amenity block is embedded in the rehab total.',
        '33(20)(B)' => 'Row 9 shows rehab_bua_sqm · Row 12 shows owner_total_sqm · Total BUA is the full scheme.',
    ];
    $__schemeNote = $__schemeMap[$reg] ?? null;
}
?>
<?php if ($__schemeNote): ?>
    <div style="background: linear-gradient(135deg,#eef2ff,#c7d2fe); border:1px solid #6366f1; border-radius:10px;
                padding:12px 16px; margin-bottom:12px; color:#312e81; font-size:0.82rem;">
        <i class="bi bi-info-circle-fill me-1"></i>
        <strong>This scheme uses <?= e($reg) ?> keys.</strong>
        The 21-row layout was designed around 33(10) SRA — <?= $__schemeNote ?>
    </div>
<?php endif; ?>

<?php if ($__stale): ?>
    <div style="background: linear-gradient(135deg,#fef3c7,#fde68a); border:1px solid #f59e0b; border-radius:10px;
                padding:14px 18px; margin-bottom:16px; color:#78350f; display:flex; gap:14px; align-items:center;">
        <i class="bi bi-exclamation-triangle-fill" style="font-size:1.4rem;"></i>
        <div style="flex:1;">
            <strong>Numbers on this page are pre-amenity-fix.</strong>
            The calc was persisted before Rehab Component included the amenity block.
            Click <strong>Refresh calc</strong> above to bring Rehab Component, Sale Component, Total BUA and FSI up to date.
        </div>
        <form method="POST" action="index.php?page=area_analysis_refresh_calc" style="display:inline;">
            <input type="hidden" name="statement_id" value="<?= (int)$s['id'] ?>">
            <input type="hidden" name="redirect_to" value="index.php?page=area_analysis_feasibility&statement_id=<?= (int)$s['id'] ?>">
            <button type="submit" style="background:#d97706; color:#fff; border:0; padding:8px 16px; border-radius:8px; font-weight:700; font-size:0.85rem; cursor:pointer;">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh now
            </button>
        </form>
    </div>
<?php endif; ?>

<div class="fs-wrap">
    <div class="fs-title">
        Tentative Feasibility under D.C. Regulation <?= e($reg ?: '33(10)') ?> of D.C.P.R. 2034<br>
        for land bearing C.T.S. No. <?= e($ctsNo) ?> of <?= e($projectAdr ?: 'the subject plot') ?>
    </div>

    <table class="fs-table">
        <thead>
            <tr>
                <th style="width: 42px;">Sr. No.</th>
                <th>Particular</th>
                <th style="width: 130px;">Area in Sq.Mt.</th>
                <th style="width: 130px;">Area in Sq.Ft.</th>
            </tr>
        </thead>
        <tbody>
            <!-- Preamble -->
            <tr>
                <td class="n">1</td>
                <td class="p">Road Width <?= $roadWidth > 0 ? number_format($roadWidth, 2) . ' M' : '—' ?></td>
                <td class="a">Reg. <?= e($reg ?: '33(10)') ?></td>
                <td class="a">&nbsp;</td>
            </tr>
            <tr>
                <td class="n">2</td>
                <td class="p">RR Land Rate</td>
                <td class="a"><?= $rrLand > 0 ? number_format($rrLand, 2) : '<span style="color:#dc2626;">— set in financials —</span>' ?></td>
                <td class="a">&nbsp;</td>
            </tr>
            <tr>
                <td class="n">3</td>
                <td class="p">RR for Construction</td>
                <td class="a"><?= $rrConstr > 0 ? number_format($rrConstr, 2) : '<span style="color:#dc2626;">— set in financials —</span>' ?></td>
                <td class="a">&nbsp;</td>
            </tr>

            <!-- Section A -->
            <tr class="fs-section-hdr">
                <td class="n">A</td>
                <td colspan="3">Revised Scheme Parameters</td>
            </tr>

            <!-- Row 1 · Gross Plot -->
            <?php $srNo = 1; ?>
            <tr>
                <td class="n"><?= $srNo ?></td>
                <td class="p">Gross Plot Area</td>
                <td class="a"><?= $fmt($gross) ?></td>
                <td class="a"><?= $fmt($sqft($gross)) ?></td>
            </tr>

            <!-- Rows 2..N — deductions (dynamic) -->
            <?php foreach ($deductionRows as $r): $srNo++; ?>
                <tr class="<?= !$r['include'] ? 'fs-excluded' : '' ?>">
                    <td class="n"><?= $srNo ?></td>
                    <td class="p">
                        <?= e($r['label']) ?>
                        <?php if (!$r['include']): ?> <small style="color:#dc2626;">— not deducted (retains FSI)</small><?php endif; ?>
                    </td>
                    <td class="a"><?= $fmt($r['area']) ?></td>
                    <td class="a"><?= $fmt($sqft($r['area'])) ?></td>
                </tr>
            <?php endforeach; ?>

            <!-- Total Deductions -->
            <?php $srNo++; ?>
            <tr class="fs-total">
                <td class="n"><?= $srNo ?></td>
                <td class="p">Total Deductions</td>
                <td class="a"><?= $fmt($totalDed) ?></td>
                <td class="a"><?= $fmt($sqft($totalDed)) ?></td>
            </tr>

            <!-- Net Plot -->
            <?php $srNo++; ?>
            <tr class="fs-total-big">
                <td class="n"><?= $srNo ?></td>
                <td class="p">Net Plot Area of S.R. Scheme for FSI Computation</td>
                <td class="a"><?= $fmt($netPlotDerived) ?></td>
                <td class="a"><?= $fmt($sqft($netPlotDerived)) ?></td>
            </tr>

            <!-- Rehab BUA -->
            <?php $srNo++; $sid = (int)$s['id']; ?>
            <tr>
                <td class="n"><?= $srNo ?></td>
                <td class="p">Rehabilitation BUA (for Bldg. No. 1 to <?= max(1, count($buildings)) ?>)</td>
                <td class="a"><span data-calc-key="rehab_tenement_bua_sqm" data-statement-id="<?= $sid ?>"><?= $fmt($rehabBldg) ?></span></td>
                <td class="a"><?= $fmt($sqft($rehabBldg)) ?></td>
            </tr>
            <?php $srNo++; ?>
            <tr>
                <td class="n"><?= $srNo ?></td>
                <td class="p">Amenity Area (Balwadi, W/C and Society Office &amp; Common Passage)</td>
                <td class="a"><span data-calc-key="rehab_amenity_block_sqm" data-statement-id="<?= $sid ?>"><?= $fmt($amenityInternal) ?></span></td>
                <td class="a"><?= $fmt($sqft($amenityInternal)) ?></td>
            </tr>
            <?php $srNo++; ?>
            <tr>
                <td class="n"><?= $srNo ?></td>
                <td class="p">Rehabilitation Component (for Bldg. No. 1 to <?= max(1, count($buildings)) ?>)</td>
                <td class="a"><span data-calc-key="rehab_component_sqm" data-statement-id="<?= $sid ?>"><?= $fmt($rehabComponent) ?></span></td>
                <td class="a"><?= $fmt($sqft($rehabComponent)) ?></td>
            </tr>
            <?php $srNo++; ?>
            <tr>
                <td class="n"><?= $srNo ?></td>
                <td class="p">Sale Component (Rehab to Sale Ratio <?= number_format($saleRatio, 2) ?> of Rehab BUA)</td>
                <td class="a"><span data-calc-key="sale_component_sqm" data-statement-id="<?= $sid ?>"><?= $fmt($saleComponent) ?></span></td>
                <td class="a"><?= $fmt($sqft($saleComponent)) ?></td>
            </tr>
            <?php $srNo++; ?>
            <tr class="fs-total-big">
                <td class="n"><?= $srNo ?></td>
                <td class="p">Total BUA Approved for the Scheme</td>
                <td class="a"><span data-calc-key="total_bua_approved_sqm" data-statement-id="<?= $sid ?>"><?= $fmt($totalBuaApproved) ?></span></td>
                <td class="a"><?= $fmt($sqft($totalBuaApproved)) ?></td>
            </tr>
            <?php $srNo++; ?>
            <tr class="fs-fsi">
                <td class="n"><?= $srNo ?></td>
                <td class="p">FSI Sanctioned for the Scheme</td>
                <td class="a"><span data-calc-key="full_scheme_fsi" data-statement-id="<?= $sid ?>"><?= $fmtF($fsiSanctioned) ?></span></td>
                <td class="a">FSI</td>
            </tr>
            <?php $srNo++; ?>
            <tr>
                <td class="n"><?= $srNo ?></td>
                <td class="p">Sale BUA Proposed In Situ
                    <?php if ($saleInSituSource === 'auto_max'): ?>
                        <span title="Auto-computed maximum = min(sale component, gross × <?= number_format($fsiCap, 2) ?> FSI − rehab). Not confirmed by the user." style="margin-left:6px;padding:1px 7px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:0.65rem;font-weight:700;letter-spacing:0.02em;text-transform:uppercase;">
                            auto-max · unconfirmed
                        </span>
                    <?php elseif ($saleInSituSource === 'user_fin'): ?>
                        <span title="Set in the Financial Analysis form." style="margin-left:6px;padding:1px 7px;border-radius:999px;background:#d1fae5;color:#065f46;font-size:0.65rem;font-weight:700;letter-spacing:0.02em;text-transform:uppercase;">
                            user-set · financials
                        </span>
                    <?php else: ?>
                        <span title="Set in Stage 3 of the Area Analysis wizard." style="margin-left:6px;padding:1px 7px;border-radius:999px;background:#dbeafe;color:#1e40af;font-size:0.65rem;font-weight:700;letter-spacing:0.02em;text-transform:uppercase;">
                            user-set · wizard
                        </span>
                    <?php endif; ?>
                </td>
                <td class="a"><?= $fmt($saleInSitu) ?></td>
                <td class="a"><?= $fmt($sqft($saleInSitu)) ?></td>
            </tr>
            <?php $srNo++; ?>
            <tr>
                <td class="n"><?= $srNo ?></td>
                <td class="p">Total BUA Proposed for Scheme (Rehab + Sale)</td>
                <td class="a"><?= $fmt($totalBuaProposed) ?></td>
                <td class="a"><?= $fmt($sqft($totalBuaProposed)) ?></td>
            </tr>
            <?php $srNo++; ?>
            <tr class="fs-fsi">
                <td class="n"><?= $srNo ?></td>
                <td class="p">FSI Consumed In Situ</td>
                <td class="a"><?= $fmtF($fsiConsumedInSitu) ?></td>
                <td class="a">FSI</td>
            </tr>
            <?php $srNo++; ?>
            <tr>
                <td class="n"><?= $srNo ?></td>
                <td class="p">Balance Sale BUA / TDR</td>
                <td class="a"><span data-calc-key="balance_sale_bua_tdr_sqm" data-statement-id="<?= $sid ?>"><?= $fmt($balanceSaleTDR) ?></span></td>
                <td class="a"><?= $fmt($sqft($balanceSaleTDR)) ?></td>
            </tr>
            <?php $srNo++; ?>
            <tr>
                <td class="n"><?= $srNo ?></td>
                <td class="p">Net Sale Permissible</td>
                <td class="a"><span data-calc-key="sale_component_sqm" data-statement-id="<?= $sid ?>"><?= $fmt($netSalePerm) ?></span></td>
                <td class="a"><?= $fmt($sqft($netSalePerm)) ?></td>
            </tr>
            <?php $srNo++; ?>
            <tr>
                <td class="n"><?= $srNo ?></td>
                <td class="p">Sale Fungible @ 35%</td>
                <td class="a"><span data-calc-key="sale_fungible_sqm" data-statement-id="<?= $sid ?>"><?= $fmt($saleFungible) ?></span></td>
                <td class="a"><?= $fmt($sqft($saleFungible)) ?></td>
            </tr>
            <?php $srNo++; ?>
            <tr class="fs-grand">
                <td class="n"><?= $srNo ?></td>
                <td class="p">Gross Sale BUA Permissible</td>
                <td class="a"><span data-calc-key="gross_sale_bua_permissible_sqm" data-statement-id="<?= $sid ?>" style="color:#fff;"><?= $fmt($grossSalePerm) ?></span></td>
                <td class="a"><?= $fmt($sqft($grossSalePerm)) ?></td>
            </tr>
        </tbody>
    </table>

    <div class="fs-note">
        <b>Notes.</b>
        Total Deductions sums only rows marked <i>Include in Total?</i>. Reservation is typically excluded per SRA convention (Green Belt/ROS retains developer's FSI).
        Rehab-to-Sale ratio <?= number_format($saleRatio, 2) ?> per Reg <?= e($reg) ?>.
        FSI Sanctioned = Gross Sale Permissible ÷ Net Plot; FSI Consumed In Situ = Total Proposed ÷ Gross Plot (industry convention).
        Sale In Situ defaults to what fits under <?= number_format($fsiCap, 1) ?>&nbsp;FSI on the Gross plot after Rehab Component is placed — override in the financials form.
    </div>
</div>

<?php require __DIR__ . '/_calc_explorer.php'; ?>
<?php $__aaChatSid = (int)$s['id']; require __DIR__ . '/_ai_chat.php'; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
