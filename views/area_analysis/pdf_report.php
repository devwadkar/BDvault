<?php
$pageTitle = 'Feasibility Report — ' . e($statement['statement_name']);
require __DIR__ . '/../layout/header.php';

// ─── Formatters ──────────────────────────────────────────────
$fmt   = fn($v, $d = 2) => ($v === null || $v === '') ? '—' : number_format((float)$v, $d);
$fmtR  = fn($v) => ($v === null || $v === '') ? '—' : number_format((float)$v, 3);
$fmtCr = fn($v) => ($v === null || $v === '') ? '—' : '₹' . number_format(((float)$v) / 1e7, 2) . ' Cr';
$fmtPct = fn($v) => $v === null ? '—' : number_format((float)$v, 2) . '%';
$sqft  = fn($sqm) => $sqm > 0 ? $sqm * 10.7639 : 0;
$getCalc = function($key) use ($calcs) {
    foreach ($calcs as $c) if ($c['calc_key'] === $key) return (float)$c['calc_value'];
    return null;
};
$getCalcRow = function($key) use ($calcs) {
    foreach ($calcs as $c) if ($c['calc_key'] === $key) return $c;
    return null;
};

// Key numbers used in exec summary + cover
$gross     = $getCalc('gross_plot_area_sqm') ?? (float)($draft['step_2']['gross_plot_area_sqm'] ?? 0);
$netPlot   = $getCalc('net_plot_area_sqm') ?? (float)($statement['net_plot_area_sqm'] ?? 0);
$rehabBua  = $getCalc('rehab_component_sqm') ?? $getCalc('rehab_tenement_bua_sqm') ?? 0;
$saleBua   = $getCalc('sale_component_sqm') ?? $getCalc('sale_bua_sqm') ?? $getCalc('sale_society_total_sqm') ?? 0;
$totalBua  = $getCalc('total_bua_approved_sqm') ?? $getCalc('full_scheme_bua_sqm') ?? 0;
$fsiSanct  = $getCalc('fsi_sanctioned') ?? $getCalc('full_scheme_fsi') ?? 0;
$reg       = $statement['selected_regulation_key'] ?: $statement['suggested_regulation_key'];
$fr        = $financials_result['financials'] ?? [];
$np        = $fr['net_profit_after_interest_inr'] ?? null;
$margin    = $fr['net_profit_margin_pct'] ?? null;
$irr       = $fr['project_irr_pct'] ?? null;
$revenue   = $fr['total_revenue_inr'] ?? null;
$costTotal = $fr['total_cost_inr'] ?? null;

$genDate = date('d M Y');
$ver     = 'v' . (int)$statement['version_major'] . '.' . (int)$statement['version_minor'];
?>

<style>
:root {
    --pr-ink: #0f172a; --pr-muted: #64748b; --pr-border: #e2e8f0;
    --pr-primary: #4f46e5; --pr-success: #059669; --pr-warning: #d97706; --pr-danger: #dc2626;
}

/* ─── Screen (read + navigate) ─────────────────────────────── */
body { background: #cbd5e1; }
.pr-toolbar {
    position: sticky; top: 0; z-index: 100; background: #0f172a; color: #fff;
    padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;
}
.pr-toolbar h6 { margin: 0; font-weight: 700; font-size: 0.9rem; }
.pr-toolbar .sub { color: #94a3b8; font-size: 0.75rem; margin-left: 6px; }
.pr-toolbar .actions { display: flex; gap: 8px; flex-wrap: wrap; }
.pr-toolbar .btn { padding: 7px 14px; border-radius: 8px; font-size: 0.78rem; font-weight: 700; text-decoration: none; border: 0; cursor: pointer; }
.pr-toolbar .btn-print { background: #4f46e5; color: #fff; }
.pr-toolbar .btn-back  { background: #334155; color: #e2e8f0; }
.pr-toolbar .btn-toc   { background: #0284c7; color: #fff; }

.pr-doc {
    max-width: 820px; margin: 20px auto 40px; background: #fff;
    padding: 0; box-shadow: 0 12px 40px rgba(15,23,42,0.15);
    color: var(--pr-ink); font-family: 'Lexend', system-ui, sans-serif;
    font-size: 10.5pt; line-height: 1.5;
}

/* ─── Cover page ──────────────────────────────────────────── */
.pr-cover {
    min-height: 1080px; background: linear-gradient(160deg, #0f172a 0%, #1e293b 60%, #4338ca 100%);
    color: #fff; padding: 80px 60px 60px; display: flex; flex-direction: column; justify-content: space-between;
    page-break-after: always;
}
.pr-cover-brand { font-size: 0.85rem; letter-spacing: 0.2em; text-transform: uppercase; color: #a5b4fc; font-weight: 700; }
.pr-cover-title { font-size: 2.6rem; font-weight: 800; line-height: 1.15; letter-spacing: -0.02em; margin-top: 60px; }
.pr-cover-sub   { font-size: 1.15rem; color: #cbd5e1; margin-top: 12px; font-weight: 400; }
.pr-cover-badges { display: flex; gap: 10px; margin-top: 24px; flex-wrap: wrap; }
.pr-cover-badges span { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); padding: 6px 14px; border-radius: 999px; font-size: 0.78rem; font-weight: 600; }
.pr-cover-numbers {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 60px;
    padding: 24px; background: rgba(0,0,0,0.25); border-radius: 14px; backdrop-filter: blur(10px);
}
.pr-cover-numbers .n { }
.pr-cover-numbers .k { color: #a5b4fc; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
.pr-cover-numbers .v { font-size: 1.4rem; font-weight: 800; margin-top: 6px; font-family: 'JetBrains Mono', Consolas, monospace; }
.pr-cover-numbers .u { color: #94a3b8; font-size: 0.72rem; font-weight: 500; }
.pr-cover-foot { display: flex; justify-content: space-between; align-items: flex-end; color: #94a3b8; font-size: 0.78rem; margin-top: 60px; }
.pr-cover-foot .prep { font-size: 0.82rem; color: #cbd5e1; }
.pr-cover-foot .prep b { display: block; color: #fff; font-size: 1rem; margin-bottom: 2px; }

/* ─── TOC ─────────────────────────────────────────────────── */
.pr-toc { padding: 60px 60px 80px; page-break-after: always; }
.pr-toc h2 { font-size: 1.6rem; font-weight: 800; letter-spacing: -0.02em; margin: 0 0 8px; }
.pr-toc .sub { color: var(--pr-muted); font-size: 0.9rem; margin-bottom: 30px; }
.pr-toc ol { list-style: none; padding: 0; margin: 0; counter-reset: sec; }
.pr-toc li { counter-increment: sec; padding: 12px 0; border-bottom: 1px dotted #cbd5e1; display: flex; align-items: baseline; gap: 12px; }
.pr-toc li::before {
    content: counter(sec, decimal-leading-zero); font-family: 'JetBrains Mono', Consolas, monospace;
    font-weight: 800; color: var(--pr-primary); font-size: 0.95rem; min-width: 32px;
}
.pr-toc li a { flex: 1; color: var(--pr-ink); text-decoration: none; font-weight: 600; font-size: 0.98rem; }
.pr-toc li a:hover { color: var(--pr-primary); }
.pr-toc li .desc { color: var(--pr-muted); font-size: 0.78rem; font-weight: 400; display: block; margin-top: 2px; }

/* ─── Sections ─────────────────────────────────────────────── */
.pr-section { padding: 50px 60px; page-break-before: always; page-break-inside: avoid; }
.pr-section:first-of-type { page-break-before: auto; }
.pr-section-num {
    display: inline-block; font-family: 'JetBrains Mono', Consolas, monospace;
    background: var(--pr-primary); color: #fff; padding: 4px 12px; border-radius: 6px;
    font-size: 0.75rem; font-weight: 800; letter-spacing: 0.04em; margin-bottom: 12px;
}
.pr-section h2 { font-size: 1.5rem; font-weight: 800; letter-spacing: -0.02em; margin: 0 0 6px; }
.pr-section .subtitle { color: var(--pr-muted); font-size: 0.88rem; margin-bottom: 24px; }
.pr-h3 { font-size: 0.95rem; font-weight: 800; color: var(--pr-ink); text-transform: uppercase; letter-spacing: 0.04em; margin: 24px 0 10px; border-bottom: 2px solid #eef2ff; padding-bottom: 6px; }

/* Table styles */
.pr-tbl { width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-bottom: 16px; }
.pr-tbl th, .pr-tbl td { padding: 9px 12px; text-align: left; border-bottom: 1px solid var(--pr-border); vertical-align: top; }
.pr-tbl thead th { background: #f8fafc; font-weight: 700; color: var(--pr-muted); text-transform: uppercase; font-size: 0.68rem; letter-spacing: 0.06em; }
.pr-tbl .num { text-align: right; font-family: 'JetBrains Mono', Consolas, monospace; font-weight: 700; white-space: nowrap; }
.pr-tbl .lbl { font-weight: 500; color: var(--pr-ink); }
.pr-tbl .ref { font-family: 'JetBrains Mono', Consolas, monospace; font-size: 0.75rem; color: var(--pr-success); }
.pr-tbl .desc { color: var(--pr-muted); font-size: 0.78rem; }
.pr-tbl tfoot td { border-top: 2px solid var(--pr-ink); font-weight: 800; }
.pr-tbl tr.total td { background: #ecfdf5; font-weight: 700; color: #065f46; border-top: 2px solid #86efac; }

/* Metric tiles */
.pr-tiles { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
.pr-tile { background: #f8fafc; border: 1px solid var(--pr-border); border-radius: 10px; padding: 14px 16px; }
.pr-tile .k { color: var(--pr-muted); font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
.pr-tile .v { font-family: 'JetBrains Mono', Consolas, monospace; font-weight: 800; font-size: 1.15rem; margin-top: 4px; color: var(--pr-ink); }
.pr-tile .u { color: var(--pr-muted); font-size: 0.72rem; margin-top: 2px; }
.pr-tile.pos .v { color: var(--pr-success); }
.pr-tile.neg .v { color: var(--pr-danger); }

/* Two-column info grid */
.pr-kv { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px; margin-bottom: 16px; font-size: 0.88rem; }
.pr-kv > div { display: flex; gap: 12px; padding: 6px 0; border-bottom: 1px dotted #e2e8f0; }
.pr-kv .k { color: var(--pr-muted); min-width: 140px; font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
.pr-kv .v { color: var(--pr-ink); font-weight: 600; font-family: 'JetBrains Mono', Consolas, monospace; }

/* Callouts */
.pr-callout { border-left: 4px solid var(--pr-primary); background: #eef2ff; padding: 12px 16px; border-radius: 6px; font-size: 0.85rem; margin: 12px 0 20px; color: #1e1b4b; }
.pr-callout.warn { border-color: var(--pr-warning); background: #fef3c7; color: #78350f; }
.pr-callout.danger { border-color: var(--pr-danger); background: #fee2e2; color: #7f1d1d; }
.pr-callout.success { border-color: var(--pr-success); background: #d1fae5; color: #064e3b; }
.pr-callout strong { display: block; margin-bottom: 4px; font-size: 0.88rem; }

/* Severity chip */
.chip { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
.chip-error { background: #fee2e2; color: #991b1b; }
.chip-warn  { background: #fef3c7; color: #92400e; }
.chip-info  { background: #dbeafe; color: #1e40af; }
.chip-pass  { background: #d1fae5; color: #065f46; }

/* ─── Print styles ─────────────────────────────────────────── */
@media print {
    body { background: #fff !important; }
    .pr-toolbar { display: none !important; }
    .premium-navbar, .sidebar-container, nav, header, aside, footer,
    #chatbot, .fab, .navbar { display: none !important; }
    .pr-doc { box-shadow: none !important; margin: 0 !important; max-width: 100% !important; }
    .pr-section { padding: 30px 40px !important; }
    .pr-cover { padding: 50px 40px !important; min-height: 90vh; }
    .main-content, .container, .container-fluid { padding: 0 !important; margin: 0 !important; }
    @page { size: A4; margin: 12mm; }
    body > *:not(.pr-doc):not(script) { display: none !important; }
}
</style>

<!-- Toolbar (screen only) -->
<div class="pr-toolbar">
    <div>
        <h6><i class="bi bi-file-earmark-pdf-fill me-1"></i>20-Section Feasibility Report</h6>
        <span class="sub"><?= e($statement['statement_name']) ?> · <?= $ver ?> · <?= $genDate ?></span>
    </div>
    <div class="actions">
        <a href="index.php?page=area_analysis_report&statement_id=<?= (int)$statement['id'] ?>" class="btn btn-back">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <button class="btn btn-toc" onclick="document.querySelector('.pr-toc').scrollIntoView({behavior:'smooth'});">
            <i class="bi bi-list-ol me-1"></i>Contents
        </button>
        <button class="btn btn-print" onclick="window.print();">
            <i class="bi bi-printer me-1"></i>Print / Save as PDF
        </button>
    </div>
</div>

<div class="pr-doc">

<!-- ═══════════ COVER PAGE ═══════════ -->
<div class="pr-cover">
    <div>
        <div class="pr-cover-brand">BDVault · Feasibility Report</div>
        <h1 class="pr-cover-title"><?= e($proposal['proposal_name'] ?? $statement['statement_name']) ?></h1>
        <div class="pr-cover-sub">
            <?= e($statement['project_location'] ?: ($proposal['location'] ?? '—')) ?>
            <?php if (!empty($statement['cts_number'])): ?> · CTS <?= e($statement['cts_number']) ?><?php endif; ?>
            <?php if (!empty($statement['ward'])): ?> · Ward <?= e($statement['ward']) ?><?php endif; ?>
        </div>
        <div class="pr-cover-badges">
            <span><i class="bi bi-file-earmark-ruled me-1"></i>DCPR <?= e($reg ?: '—') ?></span>
            <span><i class="bi bi-tag me-1"></i><?= $ver ?></span>
            <span><i class="bi bi-check-circle me-1"></i>Status: <?= e(ucfirst($statement['status'])) ?></span>
        </div>
    </div>

    <div class="pr-cover-numbers">
        <div class="n">
            <div class="k">Net Plot</div>
            <div class="v"><?= $fmt($netPlot, 0) ?></div>
            <div class="u">sqm</div>
        </div>
        <div class="n">
            <div class="k">FSI Sanctioned</div>
            <div class="v"><?= $fmtR($fsiSanct) ?></div>
            <div class="u">ratio</div>
        </div>
        <div class="n">
            <div class="k">Total BUA</div>
            <div class="v"><?= $fmt($totalBua, 0) ?></div>
            <div class="u">sqm</div>
        </div>
        <div class="n">
            <div class="k">NP After Interest</div>
            <div class="v" style="color: <?= ($np ?? 0) >= 0 ? '#86efac' : '#fca5a5' ?>;">
                <?= $np !== null ? $fmtCr($np) : '—' ?>
            </div>
            <div class="u">Margin <?= $fmtPct($margin) ?></div>
        </div>
    </div>

    <div class="pr-cover-foot">
        <div class="prep">
            <b>Prepared for internal circulation</b>
            Generated <?= $genDate ?> · Confidential · Not for external distribution
        </div>
        <div>BDVault v2 · Regulatory-aware Feasibility Engine</div>
    </div>
</div>

<!-- ═══════════ TABLE OF CONTENTS ═══════════ -->
<div class="pr-toc">
    <h2>Contents</h2>
    <div class="sub">Twenty sections covering the full feasibility case — from regulation through profitability, sensitivity and audit.</div>
    <ol>
        <li><a href="#s1">Executive Summary</a><span class="desc">Key numbers, verdict, headline chart</span></li>
        <li><a href="#s2">Project Overview</a><span class="desc">Location, plot, existing use, DA reference</span></li>
        <li><a href="#s3">Regulatory Framework</a><span class="desc">Chosen regulation with DCPR citations + rationale</span></li>
        <li><a href="#s4">Plot &amp; Land Statement</a><span class="desc">Gross, deductions, net plot with FSI base</span></li>
        <li><a href="#s5">Existing Development</a><span class="desc">Building roster + tenement drivers</span></li>
        <li><a href="#s6">Rehabilitation Component</a><span class="desc">Tenement BUA + amenity block + total rehab</span></li>
        <li><a href="#s7">Sale Component</a><span class="desc">Ratio derivation, sale BUA, in-situ vs TDR</span></li>
        <li><a href="#s8">Full Development Statement</a><span class="desc">FSI stack + total BUA + handover splits</span></li>
        <li><a href="#s9">Site Plan</a><span class="desc">Scaled schematic with deductions coloured</span></li>
        <li><a href="#s10">Formula Audit Trail</a><span class="desc">Every derived value with formula + inputs + citation</span></li>
        <li><a href="#s11">Revenue Model</a><span class="desc">Line items + escalation + total inflow</span></li>
        <li><a href="#s12">Cost Model</a><span class="desc">Construction + professional + marketing + finance</span></li>
        <li><a href="#s13">Premium &amp; Statutory Charges</a><span class="desc">Itemised premiums with ready reckoner base</span></li>
        <li><a href="#s14">Society / Rehab Costs</a><span class="desc">Corpus, hardship, temp accommodation</span></li>
        <li><a href="#s15">Profitability Summary</a><span class="desc">NP, margin, profit-on-cost, IRR, ROE</span></li>
        <li><a href="#s16">Scenario Analysis</a><span class="desc">Conservative / Base / Optimistic comparison</span></li>
        <li><a href="#s17">Sensitivity &amp; Break-Even</a><span class="desc">Sale-rate + cost-rate tables + target-return matrix</span></li>
        <li><a href="#s18">Regulatory Validation &amp; Discrepancies</a><span class="desc">Traffic-light checks + open discrepancies</span></li>
        <li><a href="#s19">Assumptions &amp; Regulatory Constants</a><span class="desc">Every constant that shaped the numbers, sourced</span></li>
        <li><a href="#s20">Sources &amp; Audit Trail</a><span class="desc">Data provenance, overrides, version history</span></li>
    </ol>
</div>

<!-- ═══════════ 1. EXECUTIVE SUMMARY ═══════════ -->
<section class="pr-section" id="s1">
    <div class="pr-section-num">SECTION 01</div>
    <h2>Executive Summary</h2>
    <div class="subtitle">The complete case in one page — for the decision-maker who reads only this.</div>

    <div class="pr-tiles">
        <div class="pr-tile">
            <div class="k">Gross Plot</div><div class="v"><?= $fmt($gross, 0) ?></div><div class="u">sqm</div>
        </div>
        <div class="pr-tile">
            <div class="k">Net Plot (FSI base)</div><div class="v"><?= $fmt($netPlot, 0) ?></div><div class="u">sqm</div>
        </div>
        <div class="pr-tile">
            <div class="k">Total BUA</div><div class="v"><?= $fmt($totalBua, 0) ?></div><div class="u">sqm · FSI <?= $fmtR($fsiSanct) ?></div>
        </div>
        <div class="pr-tile">
            <div class="k">Sale Component</div><div class="v"><?= $fmt($saleBua, 0) ?></div><div class="u">sqm</div>
        </div>
        <?php if ($fr): ?>
        <div class="pr-tile <?= ($np ?? 0) >= 0 ? 'pos' : 'neg' ?>">
            <div class="k">Net Profit (after int.)</div><div class="v"><?= $fmtCr($np) ?></div><div class="u">₹ Cr</div>
        </div>
        <div class="pr-tile">
            <div class="k">Margin</div><div class="v"><?= $fmtPct($margin) ?></div><div class="u">% of revenue</div>
        </div>
        <div class="pr-tile">
            <div class="k">Project IRR</div><div class="v"><?= $irr !== null ? number_format($irr, 2) . '%' : '—' ?></div><div class="u">annualised</div>
        </div>
        <div class="pr-tile">
            <div class="k">Total Revenue</div><div class="v"><?= $fmtCr($revenue) ?></div><div class="u">gross of tax</div>
        </div>
        <?php endif; ?>
    </div>

    <?php
    $verdict = 'success'; $verdictText = 'Financially feasible — meets minimum profit-on-cost thresholds.';
    if ($np === null) { $verdict = 'warn'; $verdictText = 'Financial model not yet computed — set Financials to complete this section.'; }
    elseif ($np < 0) { $verdict = 'danger'; $verdictText = 'Not feasible at current inputs — net profit after interest is negative. See Section 17 (Sensitivity) for break-even path.'; }
    elseif ($margin !== null && $margin < 15) { $verdict = 'warn'; $verdictText = 'Marginally feasible — margin below 15% is fragile to cost overruns. See Section 16 (Scenarios) for downside risk.'; }
    ?>
    <div class="pr-callout <?= $verdict ?>">
        <strong>Verdict</strong>
        <?= e($verdictText) ?>
    </div>

    <div class="pr-callout">
        <strong>How to read this document</strong>
        Sections 1–10 cover the Area Statement (regulatory + physical). Sections 11–15 cover Financials. Sections 16–17 stress-test the base model. Sections 18–20 provide audit traceability. Every derived number cites its DCPR clause.
    </div>
</section>

<!-- ═══════════ 2. PROJECT OVERVIEW ═══════════ -->
<section class="pr-section" id="s2">
    <div class="pr-section-num">SECTION 02</div>
    <h2>Project Overview</h2>
    <div class="subtitle">Site facts, ownership, current use.</div>

    <div class="pr-kv">
        <div><span class="k">Project name</span><span class="v"><?= e($proposal['proposal_name'] ?? $statement['statement_name']) ?></span></div>
        <div><span class="k">Statement version</span><span class="v"><?= $ver ?></span></div>
        <div><span class="k">Location</span><span class="v"><?= e($statement['project_location'] ?? '—') ?></span></div>
        <div><span class="k">CTS number</span><span class="v"><?= e($statement['cts_number'] ?? '—') ?></span></div>
        <div><span class="k">Ward</span><span class="v"><?= e($statement['ward'] ?? '—') ?></span></div>
        <div><span class="k">Planning authority</span><span class="v"><?= e($statement['planning_authority'] ?? '—') ?></span></div>
        <div><span class="k">Development type</span><span class="v"><?= e($statement['development_type'] ?? '—') ?></span></div>
        <div><span class="k">Status</span><span class="v"><?= e(ucfirst($statement['status'])) ?></span></div>
    </div>

    <?php if (!empty($statement['plot_address'])): ?>
        <div class="pr-h3">Plot address</div>
        <div style="color: var(--pr-ink); font-size: 0.9rem;"><?= nl2br(e($statement['plot_address'])) ?></div>
    <?php endif; ?>

    <?php if (!empty($proposal['description'])): ?>
        <div class="pr-h3">Deal notes</div>
        <div style="color: var(--pr-muted); font-size: 0.88rem;"><?= nl2br(e($proposal['description'])) ?></div>
    <?php endif; ?>
</section>

<!-- ═══════════ 3. REGULATORY FRAMEWORK ═══════════ -->
<section class="pr-section" id="s3">
    <div class="pr-section-num">SECTION 03</div>
    <h2>Regulatory Framework</h2>
    <div class="subtitle">Chosen DCPR regulation with rationale + citations.</div>

    <div class="pr-callout">
        <strong>Selected regulation</strong>
        <span style="font-family: 'JetBrains Mono', Consolas, monospace; font-weight: 800; color: var(--pr-primary);">DCPR <?= e($reg ?: 'Not selected') ?></span>
        <?php if (!empty($statement['suggested_rationale'])): ?>
            <div style="margin-top: 8px;"><?= nl2br(e($statement['suggested_rationale'])) ?></div>
        <?php endif; ?>
    </div>

    <div class="pr-kv">
        <div><span class="k">Suggested by engine</span><span class="v"><?= e($statement['suggested_regulation_key'] ?? '—') ?></span></div>
        <div><span class="k">Engine confidence</span><span class="v"><?= $statement['suggested_confidence'] !== null ? $statement['suggested_confidence'] . '%' : '—' ?></span></div>
        <div><span class="k">User confirmed</span><span class="v"><?= e($statement['selected_regulation_key'] ?? '—') ?></span></div>
        <div><span class="k">Override applied</span><span class="v"><?= (!empty($statement['selected_regulation_key']) && $statement['selected_regulation_key'] !== $statement['suggested_regulation_key']) ? 'Yes' : 'No' ?></span></div>
    </div>

    <div class="pr-h3">Applicable DCPR clauses</div>
    <table class="pr-tbl">
        <thead><tr><th>Clause</th><th>Applies to</th></tr></thead>
        <tbody>
            <?php
            $refs = [];
            foreach ($calcs as $c) {
                $r = trim($c['regulation_ref'] ?? '');
                if ($r && !isset($refs[$r])) $refs[$r] = [];
                if ($r) $refs[$r][] = $c['calc_label'] ?? $c['calc_key'];
            }
            $shown = 0;
            foreach ($refs as $r => $labels): if (++$shown > 20) break; ?>
                <tr>
                    <td class="ref"><?= e($r) ?></td>
                    <td class="desc"><?= e(implode(' · ', array_slice(array_unique($labels), 0, 3))) ?><?= count($labels) > 3 ? ' + ' . (count($labels) - 3) . ' more' : '' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<!-- ═══════════ 4. PLOT & LAND STATEMENT ═══════════ -->
<section class="pr-section" id="s4">
    <div class="pr-section-num">SECTION 04</div>
    <h2>Plot &amp; Land Statement</h2>
    <div class="subtitle">Gross plot minus every counted deduction gives the FSI base.</div>

    <table class="pr-tbl">
        <thead>
            <tr><th>Component</th><th class="num">Area (sqm)</th><th class="num">Area (sqft)</th><th>DCPR ref</th></tr>
        </thead>
        <tbody>
            <?php foreach ($land as $l): ?>
                <tr>
                    <td class="lbl"><?= e($l['component_label']) ?></td>
                    <td class="num"><?= $fmt($l['area_sqm']) ?></td>
                    <td class="num"><?= $fmt($sqft((float)$l['area_sqm'])) ?></td>
                    <td class="ref"><?= e($l['regulation_ref'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($gross > 0 && $netPlot > 0): ?>
    <div class="pr-callout">
        <strong>Net plot summary</strong>
        Gross plot <b><?= $fmt($gross, 2) ?> sqm</b> less total deductions of <b><?= $fmt($gross - $netPlot, 2) ?> sqm</b>
        (<b><?= $fmt(($gross - $netPlot) / $gross * 100, 2) ?>%</b> of gross) yields
        a net FSI base of <b><?= $fmt($netPlot, 2) ?> sqm</b> = <b><?= $fmt($sqft($netPlot), 0) ?> sqft</b>.
    </div>
    <?php endif; ?>
</section>

<!-- ═══════════ 5. EXISTING DEVELOPMENT ═══════════ -->
<section class="pr-section" id="s5">
    <div class="pr-section-num">SECTION 05</div>
    <h2>Existing Development</h2>
    <div class="subtitle">Per-building tenement + BUA roster driving the rehab component.</div>

    <?php if (!empty($buildings)): ?>
        <table class="pr-tbl">
            <thead>
                <tr>
                    <th>Building</th>
                    <th class="num">Res units</th>
                    <th class="num">Com units</th>
                    <th class="num">Res carpet</th>
                    <th class="num">Com carpet</th>
                    <th class="num">Existing BUA</th>
                    <th class="num">Tenants</th>
                    <th class="num">Eligible</th>
                </tr>
            </thead>
            <tbody>
                <?php $tt=[0,0,0,0,0,0,0]; foreach ($buildings as $b): ?>
                    <tr>
                        <td class="lbl"><?= e($b['building_label'] ?: '—') ?></td>
                        <td class="num"><?= (int)($b['residential_units'] ?? 0) ?></td>
                        <td class="num"><?= (int)($b['commercial_units'] ?? 0) ?></td>
                        <td class="num"><?= $fmt($b['residential_carpet_sqm'] ?? 0) ?></td>
                        <td class="num"><?= $fmt($b['commercial_carpet_sqm'] ?? 0) ?></td>
                        <td class="num"><?= $fmt($b['existing_bua_sqm'] ?? 0) ?></td>
                        <td class="num"><?= (int)($b['tenant_count'] ?? 0) ?></td>
                        <td class="num"><?= (int)($b['eligible_tenant_count'] ?? 0) ?></td>
                    </tr>
                    <?php
                    $tt[0] += (int)($b['residential_units'] ?? 0);
                    $tt[1] += (int)($b['commercial_units'] ?? 0);
                    $tt[2] += (float)($b['residential_carpet_sqm'] ?? 0);
                    $tt[3] += (float)($b['commercial_carpet_sqm'] ?? 0);
                    $tt[4] += (float)($b['existing_bua_sqm'] ?? 0);
                    $tt[5] += (int)($b['tenant_count'] ?? 0);
                    $tt[6] += (int)($b['eligible_tenant_count'] ?? 0);
                    ?>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="total">
                    <td>Totals</td>
                    <td class="num"><?= $tt[0] ?></td><td class="num"><?= $tt[1] ?></td>
                    <td class="num"><?= $fmt($tt[2]) ?></td><td class="num"><?= $fmt($tt[3]) ?></td>
                    <td class="num"><?= $fmt($tt[4]) ?></td>
                    <td class="num"><?= $tt[5] ?></td><td class="num"><?= $tt[6] ?></td>
                </tr>
            </tfoot>
        </table>
    <?php else: ?>
        <div class="pr-callout warn">No existing buildings on record. Rehab component defaults to zero if this is a vacant plot; verify.</div>
    <?php endif; ?>

    <?php $s3 = $draft['step_3'] ?? []; ?>
    <div class="pr-h3">Rehab drivers (Step 3 Section A)</div>
    <div class="pr-kv">
        <div><span class="k">Eligible tenements</span><span class="v"><?= $fmt($s3['s3_eligible_total'] ?? 0, 0) ?></span></div>
        <div><span class="k">Carpet per residential</span><span class="v"><?= $fmt($s3['s3_carpet_res_sqm'] ?? 0) ?> sqm</span></div>
        <div><span class="k">Carpet per commercial</span><span class="v"><?= $fmt($s3['s3_carpet_com_sqm'] ?? 0) ?> sqm</span></div>
        <div><span class="k">Loading factor</span><span class="v"><?= $fmtR($s3['s3_loading_factor'] ?? 0) ?></span></div>
        <div><span class="k">Amenity area (explicit)</span><span class="v"><?= isset($s3['s3_amenity_built_sqm']) && $s3['s3_amenity_built_sqm'] !== '' ? $fmt($s3['s3_amenity_built_sqm']) . ' sqm' : '(blank — using registry default)' ?></span></div>
        <div><span class="k">Tenement source</span><span class="v"><?= e($s3['s3_tenement_source'] ?? '—') ?></span></div>
    </div>
</section>

<!-- ═══════════ 6. REHABILITATION COMPONENT ═══════════ -->
<section class="pr-section" id="s6">
    <div class="pr-section-num">SECTION 06</div>
    <h2>Rehabilitation Component</h2>
    <div class="subtitle">Rehab tenement BUA + amenity block. The denominator for the sale ratio.</div>

    <?php
    $rows_rehab = ['total_rehab_tenements','rehab_carpet_total_sqm','rehab_tenement_bua_sqm','rehab_amenity_block_sqm','rehab_component_sqm'];
    ?>
    <table class="pr-tbl">
        <thead>
            <tr><th>Line</th><th class="num">Value</th><th>Formula</th><th>DCPR ref</th></tr>
        </thead>
        <tbody>
            <?php foreach ($rows_rehab as $k): $r = $getCalcRow($k); if (!$r) continue; ?>
                <tr>
                    <td class="lbl"><?= e($r['calc_label'] ?? $k) ?></td>
                    <td class="num"><?= $fmt($r['calc_value']) ?><span style="color:var(--pr-muted);font-weight:400;font-family:inherit;"> <?= e($r['calc_unit'] ?? '') ?></span></td>
                    <td class="desc" style="font-family:'JetBrains Mono',monospace;font-size:0.75rem;"><?= e($r['formula'] ?? '') ?></td>
                    <td class="ref"><?= e($r['regulation_ref'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<!-- ═══════════ 7. SALE COMPONENT ═══════════ -->
<section class="pr-section" id="s7">
    <div class="pr-section-num">SECTION 07</div>
    <h2>Sale Component</h2>
    <div class="subtitle">Rehab component × effective ratio → sale BUA. In-situ vs TDR split.</div>

    <?php $rows_sale = ['sale_ratio_base','sale_ratio_incentive_pct','sale_ratio_effective','sale_component_base_sqm','buildable_reservation_sale_incentive_sqm','sale_component_sqm','in_situ_bua_cap_sqm','sale_bua_in_situ_max_sqm','sale_bua_proposed_in_situ_sqm','balance_sale_bua_tdr_sqm','sale_fungible_sqm','gross_sale_bua_permissible_sqm','sale_society_total_sqm','sale_bua_sqm','sra_ptc_total_sqm']; ?>
    <table class="pr-tbl">
        <thead>
            <tr><th>Line</th><th class="num">Value</th><th>Formula</th><th>DCPR ref</th></tr>
        </thead>
        <tbody>
            <?php foreach ($rows_sale as $k): $r = $getCalcRow($k); if (!$r) continue; ?>
                <tr>
                    <td class="lbl"><?= e($r['calc_label'] ?? $k) ?></td>
                    <td class="num"><?= $fmt($r['calc_value']) ?><span style="color:var(--pr-muted);font-weight:400;font-family:inherit;"> <?= e($r['calc_unit'] ?? '') ?></span></td>
                    <td class="desc" style="font-family:'JetBrains Mono',monospace;font-size:0.75rem;"><?= e($r['formula'] ?? '') ?></td>
                    <td class="ref"><?= e($r['regulation_ref'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<!-- ═══════════ 8. FULL DEVELOPMENT STATEMENT ═══════════ -->
<section class="pr-section" id="s8">
    <div class="pr-section-num">SECTION 08</div>
    <h2>Full Development Statement</h2>
    <div class="subtitle">Every FSI + BUA row that the scheme sanctions.</div>

    <?php if (!empty($fsi)): ?>
    <div class="pr-h3">FSI stack</div>
    <table class="pr-tbl">
        <thead><tr><th>Component</th><th class="num">Ratio</th><th class="num">Effective BUA (sqm)</th><th>DCPR ref</th></tr></thead>
        <tbody>
            <?php foreach ($fsi as $f): ?>
                <tr>
                    <td class="lbl"><?= e($f['component_label']) ?></td>
                    <td class="num"><?= $fmtR($f['fsi_ratio'] ?? null) ?></td>
                    <td class="num"><?= $fmt($f['bua_sqm'] ?? null) ?></td>
                    <td class="ref"><?= e($f['regulation_ref'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if (!empty($dev)): ?>
    <div class="pr-h3">BUA + handover breakdown</div>
    <table class="pr-tbl">
        <thead><tr><th>Component</th><th class="num">Area (sqm)</th><th class="num">Area (sqft)</th><th>Handover</th><th>DCPR ref</th></tr></thead>
        <tbody>
            <?php foreach ($dev as $d): ?>
                <tr>
                    <td class="lbl"><?= e($d['component_label']) ?></td>
                    <td class="num"><?= $fmt($d['area_sqm']) ?></td>
                    <td class="num"><?= $fmt($sqft((float)$d['area_sqm'])) ?></td>
                    <td class="desc"><?= e($d['handover_to'] ?? '—') ?></td>
                    <td class="ref"><?= e($d['regulation_ref'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<!-- ═══════════ 9. SITE PLAN ═══════════ -->
<section class="pr-section" id="s9">
    <div class="pr-section-num">SECTION 09</div>
    <h2>Site Plan</h2>
    <div class="subtitle">Scaled schematic — deductions, reservations and amenity all colour-coded.</div>
    <div style="background: #f8fafc; border: 1px solid var(--pr-border); border-radius: 10px; padding: 16px; text-align: center;">
        <?php require_once __DIR__ . '/../../lib/SitePlanRenderer.php';
              echo SitePlanRenderer::render(['statement' => $statement, 'land' => $land, 'dev' => $dev], 'full'); ?>
    </div>
    <div class="pr-callout">
        <strong>How to read the plan</strong>
        The outer boundary is the gross plot. Deductions (roads, nalla, reservations, amenity) are cut from the perimeter. The remaining interior is the net plot that generates FSI.
    </div>
</section>

<!-- ═══════════ 10. FORMULA AUDIT TRAIL ═══════════ -->
<section class="pr-section" id="s10">
    <div class="pr-section-num">SECTION 10</div>
    <h2>Formula Audit Trail</h2>
    <div class="subtitle">Every derived value with its formula, inputs, and regulation citation. Grouped by section.</div>

    <?php
    // Group by calc_key prefix (rehab_/sale_/fsi_/... etc)
    $byGroup = [];
    foreach ($calcs as $c) {
        $key = $c['calc_key'] ?? '';
        $g = 'other';
        foreach (['land'=>['gross_','net_','plot_','dp_','nalla_','setback_','reservation_','amenity_','other_deduction','ros_','total_deductions'],
                  'rehab'=>['rehab_','total_rehab'],
                  'sale'=>['sale_','buildable_'],
                  'fsi'=>['fsi_','zonal_','additional_fsi','basic_fsi','incentive_','tdr_','full_scheme_fsi','total_base_fsi'],
                  'totals'=>['total_bua','full_scheme_bua','total_base_bua'],
                  'fungible'=>['fungible','_fungible_'],
                  'sra_ptc'=>['sra_','ptc_','num_ptc'],
                  'society'=>['society_','sale_society'],
                  'ah'=>['ah_','owner_','gov_fsi','incentive_337b','existing_bua'],
                  'existing'=>['existing_'],
                  'other'=>['sqm_to_sqft','conversion'],
                 ] as $gname => $prefixes) {
            foreach ($prefixes as $p) if (strpos($key, $p) === 0 || strpos($key, $p) !== false) { $g = $gname; break 2; }
        }
        $byGroup[$g][] = $c;
    }
    $groupLabels = ['land'=>'Land','rehab'=>'Rehab','sale'=>'Sale','fsi'=>'FSI','totals'=>'Totals','tdr'=>'TDR','fungible'=>'Fungible','sra_ptc'=>'SRA PTC','society'=>'Society','ah'=>'AH','existing'=>'Existing','other'=>'Other'];
    $order = ['land','existing','rehab','sale','fsi','fungible','sra_ptc','society','ah','totals','other'];
    foreach ($order as $g): if (empty($byGroup[$g])) continue; $rows = $byGroup[$g]; ?>
        <div class="pr-h3"><?= e($groupLabels[$g] ?? ucfirst($g)) ?> (<?= count($rows) ?>)</div>
        <table class="pr-tbl">
            <thead><tr><th>Row</th><th class="num">Value</th><th>Formula</th><th>DCPR ref</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="lbl"><?= e($r['calc_label'] ?? $r['calc_key']) ?><br><span class="desc" style="font-family:'JetBrains Mono',monospace;"><?= e($r['calc_key']) ?></span></td>
                        <td class="num"><?= $fmt($r['calc_value'], 4) ?><br><span class="desc"><?= e($r['calc_unit'] ?? '') ?></span></td>
                        <td class="desc" style="font-family:'JetBrains Mono',monospace;font-size:0.72rem;"><?= e($r['formula'] ?? '') ?></td>
                        <td class="ref"><?= e($r['regulation_ref'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
</section>

<?php require __DIR__ . '/_pdf_report_financial.php'; ?>

</div>  <!-- .pr-doc -->

<?php $__aaChatSid = (int)$statement['id']; require __DIR__ . '/_ai_chat.php'; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
