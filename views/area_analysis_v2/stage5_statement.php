<?php $pageTitle = 'Area Analysis v2 · Final Area Statement'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/_shell.php'; ?>
<?php
// Comes from controller: $statement, $proposal, $draft, $s1, $calcSummary, $isLocked, $stages, $currentStage
$reg = $statement['selected_regulation_key'];
$regTitle = (RegulationSuggester::REGULATIONS[$reg]['title'] ?? '—');
$fmt = fn($v, $d = 2) => $v === null ? '—' : number_format((float)$v, $d);
?>

<style>
.final-hero {
    border-radius: 14px; padding: 24px 28px; margin-bottom: 18px;
    display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;
    color: #fff;
}
.final-hero.locked   { background: linear-gradient(135deg, #065f46 0%, #059669 100%); }
.final-hero.draft    { background: linear-gradient(135deg, #92400e 0%, #d97706 100%); }
.final-hero .eyebrow {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 800; opacity: 0.9;
}
.final-hero .title { font-weight: 800; font-size: 1.5rem; letter-spacing: -0.02em; margin: 4px 0 0; }
.final-hero .sub { opacity: 0.85; font-size: 0.86rem; margin-top: 4px; }
.final-hero .act { display: flex; gap: 8px; flex-wrap: wrap; }
.final-hero .btn-light {
    background: #fff; padding: 10px 18px; border-radius: 10px; font-weight: 700;
    font-size: 0.86rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
    border: 0; cursor: pointer;
}
.final-hero.locked .btn-light { color: #065f46; }
.final-hero.locked .btn-light:hover { background: #d1fae5; color: #065f46; }
.final-hero.draft  .btn-light { color: #92400e; }
.final-hero.draft  .btn-light:hover { background: #fef3c7; color: #92400e; }

.metrics-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 12px; margin-bottom: 16px;
}
.metric-cell {
    background: #fff; border: 1px solid var(--v2-border); border-radius: 12px;
    padding: 16px 18px;
}
.metric-cell .k {
    color: var(--v2-muted); font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700;
}
.metric-cell .v {
    font-family: 'JetBrains Mono', Consolas, monospace;
    font-weight: 800; font-size: 1.3rem; color: var(--v2-ink); margin-top: 4px;
}
.metric-cell .u { font-size: 0.68rem; color: var(--v2-muted); margin-left: 4px; }
.metric-cell.featured {
    background: linear-gradient(135deg, #eef2ff, #e0e7ff);
    border-color: #a5b4fc;
}
.metric-cell.featured .k { color: var(--v2-primary-d); }
.metric-cell.featured .v { color: var(--v2-primary-d); }

.handoff-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 10px;
}
.handoff {
    background: #fff; border: 1px solid var(--v2-border); border-radius: 10px;
    padding: 14px 18px; text-decoration: none; color: inherit;
    display: flex; align-items: center; gap: 12px;
    transition: border-color .12s, box-shadow .12s;
}
.handoff:hover { border-color: var(--v2-primary); box-shadow: 0 4px 14px rgba(79,70,229,0.08); color: inherit; }
.handoff .ico {
    width: 42px; height: 42px; border-radius: 10px; background: #eef2ff;
    color: var(--v2-primary); display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.2rem; flex-shrink: 0;
}
.handoff .t { font-weight: 700; color: var(--v2-ink); font-size: 0.9rem; }
.handoff .d { color: var(--v2-muted); font-size: 0.72rem; margin-top: 2px; }
.handoff .arrow { color: var(--v2-faint); margin-left: auto; }

.verdict-pill {
    display: inline-block; padding: 3px 10px; border-radius: 999px;
    font-size: 0.68rem; font-weight: 800; letter-spacing: 0.03em;
}
.verdict-pill.locked { background: #d1fae5; color: #065f46; }
.verdict-pill.draft  { background: #fef3c7; color: #92400e; }
</style>

<!-- ─── Hero ─── -->
<?php if ($isLocked): ?>
    <div class="final-hero locked">
        <div>
            <div class="eyebrow"><i class="bi bi-shield-check"></i>Final Area Statement</div>
            <div class="title">v<?= (int)$statement['version_major'] ?>.<?= (int)$statement['version_minor'] ?> · DCPR <?= e($reg) ?></div>
            <div class="sub">
                Locked on <?= e(date('d M Y, H:i', strtotime($statement['locked_at']))) ?>.
                <?= e($regTitle) ?>.
            </div>
        </div>
        <div class="act">
            <a href="index.php?page=area_analysis_feasibility&statement_id=<?= (int)$statement['id'] ?>" class="btn-light">
                <i class="bi bi-table"></i> 21-row Feasibility
            </a>
            <a href="index.php?page=area_analysis_pdf_report&statement_id=<?= (int)$statement['id'] ?>" class="btn-light">
                <i class="bi bi-file-earmark-pdf"></i> 20-section PDF
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="final-hero draft">
        <div>
            <div class="eyebrow"><i class="bi bi-exclamation-triangle"></i>Draft — not yet generated</div>
            <div class="title">Preview · DCPR <?= e($reg) ?></div>
            <div class="sub">This is a calculated estimate. Return to Review (Stage 4) to Generate &amp; lock the version.</div>
        </div>
        <div class="act">
            <a href="index.php?page=area_analysis_v2_stage4&statement_id=<?= (int)$statement['id'] ?>" class="btn-light">
                <i class="bi bi-arrow-left"></i> Back to Review
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- ─── Headline metrics ─── -->
<?php if ($calcSummary): ?>
<div class="metrics-grid">
    <div class="metric-cell featured">
        <div class="k">Total BUA Permissible</div>
        <div class="v"><?= $fmt($calcSummary['total_bua']) ?><span class="u"> sqm</span></div>
    </div>
    <div class="metric-cell">
        <div class="k">Net Plot</div>
        <div class="v"><?= $fmt($calcSummary['net_plot']) ?><span class="u"> sqm</span></div>
    </div>
    <div class="metric-cell">
        <div class="k">Total FSI</div>
        <div class="v"><?= $fmt($calcSummary['total_fsi'], 4) ?></div>
    </div>
    <div class="metric-cell">
        <div class="k">Rehab BUA</div>
        <div class="v"><?= $fmt($calcSummary['rehab_bua']) ?><span class="u"> sqm</span></div>
    </div>
    <div class="metric-cell">
        <div class="k">Sale BUA</div>
        <div class="v"><?= $fmt($calcSummary['sale_bua']) ?><span class="u"> sqm</span></div>
    </div>
</div>
<?php endif; ?>

<!-- ─── Full statement handoffs ─── -->
<div class="v2-card">
    <h5 style="margin:0 0 4px;font-weight:800;color:var(--v2-ink);">Full Area Statement outputs</h5>
    <p style="color:var(--v2-muted);font-size:0.84rem;margin:0 0 14px;">
        The computed inputs are now wired into every DCPR output. Open any of these to see or share the full breakdown.
    </p>
    <div class="handoff-grid">
        <a class="handoff" href="index.php?page=area_analysis_feasibility&statement_id=<?= (int)$statement['id'] ?>">
            <span class="ico"><i class="bi bi-table"></i></span>
            <div>
                <div class="t">21-row Feasibility Statement</div>
                <div class="d">Classic DCPR 2034 feasibility table</div>
            </div>
            <i class="bi bi-arrow-right arrow"></i>
        </a>
        <a class="handoff" href="index.php?page=area_analysis_pdf_report&statement_id=<?= (int)$statement['id'] ?>">
            <span class="ico"><i class="bi bi-file-earmark-pdf"></i></span>
            <div>
                <div class="t">20-section PDF report</div>
                <div class="d">Master deliverable — cover, TOC, 20 sections</div>
            </div>
            <i class="bi bi-arrow-right arrow"></i>
        </a>
        <a class="handoff" href="index.php?page=area_analysis_report&statement_id=<?= (int)$statement['id'] ?>">
            <span class="ico"><i class="bi bi-journal-text"></i></span>
            <div>
                <div class="t">Full Area Statement report</div>
                <div class="d">All sections rendered inline</div>
            </div>
            <i class="bi bi-arrow-right arrow"></i>
        </a>
        <a class="handoff" href="index.php?page=area_analysis_feasibility_excel&statement_id=<?= (int)$statement['id'] ?>">
            <span class="ico"><i class="bi bi-file-earmark-excel"></i></span>
            <div>
                <div class="t">Excel export</div>
                <div class="d">21-row feasibility · spreadsheet</div>
            </div>
            <i class="bi bi-arrow-right arrow"></i>
        </a>
        <a class="handoff" href="index.php?page=area_analysis_discrepancies&statement_id=<?= (int)$statement['id'] ?>">
            <span class="ico" style="background:#fef3c7;color:#92400e;"><i class="bi bi-exclamation-diamond"></i></span>
            <div>
                <div class="t">Formula discrepancies</div>
                <div class="d">Identity-based cross-checks</div>
            </div>
            <i class="bi bi-arrow-right arrow"></i>
        </a>
        <a class="handoff" href="index.php?page=area_analysis_overrides&statement_id=<?= (int)$statement['id'] ?>">
            <span class="ico" style="background:#f1f5f9;color:var(--v2-muted);"><i class="bi bi-pencil-square"></i></span>
            <div>
                <div class="t">Overrides &amp; audit</div>
                <div class="d">Apply · deactivate · audit trail</div>
            </div>
            <i class="bi bi-arrow-right arrow"></i>
        </a>
    </div>
</div>

<!-- ─── Next steps ─── -->
<div class="v2-card">
    <h5 style="margin:0 0 10px;font-weight:800;color:var(--v2-ink);">Next</h5>
    <div class="handoff-grid">
        <a class="handoff" href="index.php?page=approval_costs_register&statement_id=<?= (int)$statement['id'] ?>">
            <span class="ico" style="background:#e0f2fe;color:#0ea5e9;"><i class="bi bi-shield-check"></i></span>
            <div>
                <div class="t">Build Approval Register</div>
                <div class="d">Pick applicable NOCs · track approvals · estimate fees</div>
            </div>
            <i class="bi bi-arrow-right arrow"></i>
        </a>
        <a class="handoff" href="index.php?page=area_analysis_financials_form&statement_id=<?= (int)$statement['id'] ?>">
            <span class="ico" style="background:#d1fae5;color:#059669;"><i class="bi bi-cash-stack"></i></span>
            <div>
                <div class="t">Set up Financial Analysis</div>
                <div class="d">Revenue · cost · IRR · margin</div>
            </div>
            <i class="bi bi-arrow-right arrow"></i>
        </a>
        <a class="handoff" href="index.php?page=area_analysis_best_outcome">
            <span class="ico" style="background:#fef3c7;color:#d97706;"><i class="bi bi-trophy"></i></span>
            <div>
                <div class="t">See the GO / NO-GO verdict</div>
                <div class="d">After financials, run the Best Outcome check</div>
            </div>
            <i class="bi bi-arrow-right arrow"></i>
        </a>
        <?php if ($isLocked): ?>
            <a class="handoff" href="index.php?page=area_analysis_new_version&statement_id=<?= (int)$statement['id'] ?>">
                <span class="ico" style="background:#eef2ff;color:var(--v2-primary);"><i class="bi bi-arrow-repeat"></i></span>
                <div>
                    <div class="t">Create a new version to edit</div>
                    <div class="d">v<?= (int)$statement['version_major'] ?>.<?= (int)$statement['version_minor'] + 1 ?> keeps this one locked</div>
                </div>
                <i class="bi bi-arrow-right arrow"></i>
            </a>
        <?php endif; ?>
        <a class="handoff" href="index.php?page=area_analysis_v2">
            <span class="ico" style="background:#f1f5f9;color:var(--v2-muted);"><i class="bi bi-house"></i></span>
            <div>
                <div class="t">Back to v2 landing</div>
                <div class="d">Start another analysis or view your drafts</div>
            </div>
            <i class="bi bi-arrow-right arrow"></i>
        </a>
    </div>
</div>

<?php _v2_shell_close($statement, $s1, $statement['locked_at'] ?: ($draft['_meta']['stage3_saved_at'] ?? null)); ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
