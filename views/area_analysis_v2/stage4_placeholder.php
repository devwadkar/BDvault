<?php $pageTitle = 'Area Analysis v2 · Review (Preview)'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/_shell.php'; ?>
<?php
$reg = $statement['selected_regulation_key'];
$regTitle = (RegulationSuggester::REGULATIONS[$reg]['title'] ?? '—');
?>

<style>
.hero-ok {
    background: linear-gradient(135deg, #065f46, #059669); color: #fff;
    border-radius: 14px; padding: 22px 26px; margin-bottom: 18px;
    display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap;
}
.hero-ok h5 { font-weight: 800; margin: 0 0 4px; letter-spacing: -0.02em; }
.hero-ok p { margin: 0; color: #a7f3d0; font-size: 0.86rem; }
.hero-pending {
    background: linear-gradient(135deg, #92400e, #d97706); color: #fff;
}
.hero-pending p { color: #fde68a; }

.pane {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(165px, 1fr));
    gap: 10px; margin-bottom: 16px;
}
.pane .cell {
    background: #fff; border: 1px solid var(--v2-border); border-radius: 10px;
    padding: 14px 16px;
}
.pane .cell .k {
    font-size: 0.65rem; color: var(--v2-muted); text-transform: uppercase;
    letter-spacing: 0.06em; font-weight: 700;
}
.pane .cell .v {
    font-family: 'JetBrains Mono', Consolas, monospace;
    font-size: 1.2rem; font-weight: 800; color: var(--v2-ink); margin-top: 4px;
}
.pane .cell .u { font-size: 0.72rem; color: var(--v2-muted); margin-left: 4px; }
</style>

<?php if ($calcSummary): ?>
    <div class="hero-ok">
        <div>
            <h5><i class="bi bi-check-circle-fill me-2"></i>Area Statement computed</h5>
            <p>Based on your Stage 3 inputs. Verified against DCPR <?= e($reg) ?> rules.
                <?php $v = $calcSummary['validations']; ?>
                <?= $v['pass'] ?> checks passed<?= $v['warn'] > 0 ? ', ' . $v['warn'] . ' warnings' : '' ?><?= $v['fail'] > 0 ? ', ' . $v['fail'] . ' failures' : '' ?>.
            </p>
        </div>
        <a href="index.php?page=area_analysis_feasibility&statement_id=<?= (int)$statement['id'] ?>"
           class="v2-btn" style="background:#fff;color:#059669;">
            <i class="bi bi-table"></i> View 21-row feasibility
        </a>
    </div>

    <div class="v2-card">
        <h5><i class="bi bi-calculator me-2" style="color:var(--v2-primary);"></i>Headline numbers</h5>
        <div class="pane">
            <div class="cell">
                <div class="k">Net plot</div>
                <div class="v"><?= number_format($calcSummary['net_plot'], 2) ?><span class="u"> sqm</span></div>
            </div>
            <div class="cell">
                <div class="k">Total FSI</div>
                <div class="v"><?= number_format($calcSummary['total_fsi'], 4) ?></div>
            </div>
            <div class="cell">
                <div class="k">Total BUA</div>
                <div class="v"><?= number_format($calcSummary['total_bua'], 2) ?><span class="u"> sqm</span></div>
            </div>
            <div class="cell">
                <div class="k">Rehab BUA</div>
                <div class="v"><?= number_format($calcSummary['rehab_bua'], 2) ?><span class="u"> sqm</span></div>
            </div>
            <div class="cell">
                <div class="k">Sale BUA</div>
                <div class="v"><?= number_format($calcSummary['sale_bua'], 2) ?><span class="u"> sqm</span></div>
            </div>
        </div>
    </div>

    <div class="v2-card">
        <h5>Stages 4 (Review) &amp; 5 (Final statement) ship next</h5>
        <p style="color:var(--v2-muted);font-size:0.86rem;margin-bottom:14px;">
            Review-with-edit chrome and the final 20-section PDF are the next pass. Until then,
            every existing v1 output already works for this v2 statement — Feasibility, 20-section
            PDF, Excel export, Formula discrepancies, Overrides, AI chat.
        </p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="index.php?page=area_analysis_feasibility&statement_id=<?= (int)$statement['id'] ?>" class="v2-btn primary">
                <i class="bi bi-table"></i> 21-row Feasibility
            </a>
            <a href="index.php?page=area_analysis_pdf_report&statement_id=<?= (int)$statement['id'] ?>" class="v2-btn ghost">
                <i class="bi bi-file-earmark-pdf"></i> 20-section PDF
            </a>
            <a href="index.php?page=area_analysis_report&statement_id=<?= (int)$statement['id'] ?>" class="v2-btn ghost">
                <i class="bi bi-journal-text"></i> Full Area Statement report
            </a>
            <a href="index.php?page=area_analysis_discrepancies&statement_id=<?= (int)$statement['id'] ?>" class="v2-btn ghost">
                <i class="bi bi-exclamation-diamond"></i> Formula discrepancies
            </a>
            <a href="index.php?page=area_analysis_overrides&statement_id=<?= (int)$statement['id'] ?>" class="v2-btn ghost">
                <i class="bi bi-pencil-square"></i> Overrides &amp; audit
            </a>
            <a href="index.php?page=area_analysis_step&statement_id=<?= (int)$statement['id'] ?>&step=6" class="v2-btn ghost">
                <i class="bi bi-arrow-up-right"></i> Lock version (classic Step 6)
            </a>
        </div>
    </div>

<?php else: ?>
    <div class="hero-ok hero-pending">
        <div>
            <h5><i class="bi bi-exclamation-triangle-fill me-2"></i>Not calculated yet</h5>
            <p>Fill Stage 3 and hit "Save &amp; calculate" to produce the Area Statement.</p>
        </div>
        <a href="index.php?page=area_analysis_v2_stage3&statement_id=<?= (int)$statement['id'] ?>" class="v2-btn" style="background:#fff;color:#92400e;">
            <i class="bi bi-arrow-left"></i> Go to Stage 3
        </a>
    </div>
<?php endif; ?>

<?php _v2_shell_close($statement, $s1, $draft['_meta']['stage3_saved_at'] ?? ($draft['_meta']['stage1_saved_at'] ?? null)); ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
