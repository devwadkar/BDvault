<?php $pageTitle = 'Area Analysis v2 · Regulation-specific details'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/_shell.php'; ?>
<?php
// Comes from controller: $statement, $proposal, $draft, $s1, $renderer, $calcSummary, $hasCalc, $stages, $currentStage
$reg = $statement['selected_regulation_key'];
$regTitle = (RegulationSuggester::REGULATIONS[$reg]['title'] ?? '—');
?>

<style>
/* Form-hint styling shared with the schema renderer */
.form-hint { font-size: 0.75rem; color: var(--v2-faint); line-height: 1.5; }
.v2-rep-tbl th { border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }
.v2-rep-tbl td { border-bottom: 1px solid #fafbfd; padding: 4px; }
.v2-rep-row input:focus { outline: none; border-color: var(--v2-primary); box-shadow: 0 0 0 2px rgba(79,70,229,0.08); }

/* Preview strip at the bottom of the form */
.calc-preview {
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    border: 1px solid #86efac; border-radius: 12px;
    padding: 18px 22px; margin-bottom: 16px;
}
.calc-preview .hd {
    display: flex; justify-content: space-between; align-items: baseline; gap: 10px; flex-wrap: wrap;
    margin-bottom: 12px;
}
.calc-preview .hd .t { font-weight: 800; color: #065f46; font-size: 0.95rem; }
.calc-preview .hd .sub { font-size: 0.72rem; color: #047857; }
.calc-preview .tiles {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));
    gap: 10px;
}
.calc-preview .tile {
    background: rgba(255,255,255,0.7); border: 1px solid #a7f3d0;
    padding: 10px 12px; border-radius: 8px;
}
.calc-preview .tile .k { font-size: 0.65rem; color: #047857; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
.calc-preview .tile .v {
    font-family: 'JetBrains Mono', Consolas, monospace;
    font-size: 1rem; font-weight: 800; color: #065f46; margin-top: 2px;
}
.calc-preview .tile .u { font-size: 0.65rem; color: #047857; margin-left: 3px; }

.calc-preview.stale {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border-color: #fbbf24;
}
.calc-preview.stale .hd .t,
.calc-preview.stale .tile .k,
.calc-preview.stale .tile .v,
.calc-preview.stale .tile .u,
.calc-preview.stale .hd .sub { color: #92400e; }
.calc-preview.stale .tile { background: rgba(255,255,255,0.55); border-color: #fcd34d; }

.reg-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: #eef2ff; color: var(--v2-primary-d);
    padding: 3px 12px; border-radius: 999px; font-size: 0.72rem; font-weight: 700;
    letter-spacing: 0.02em; margin-top: 6px;
}
</style>

<form method="POST" action="index.php?page=area_analysis_v2_save_stage3" id="s3Form" novalidate>
    <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
    <input type="hidden" name="direction" id="dirInput" value="next">

    <div class="v2-card">
        <h5><i class="bi bi-sliders me-2" style="color:var(--v2-primary);"></i>Regulation-specific details
            <span class="reg-badge"><i class="bi bi-shield-check"></i> DCPR <?= e($reg) ?> · <?= e($regTitle) ?></span>
        </h5>
        <p class="lead">Only the fields DCPR <?= e($reg) ?> actually needs. Save to run the calculator and see the derived FSI + BUA.</p>

        <?php $renderer->renderAll(); ?>

        <!-- ─── Calc preview strip (shown after first calculation) ─── -->
        <?php if ($hasCalc): ?>
            <div class="calc-preview" id="calcPreview">
                <div class="hd">
                    <div>
                        <div class="t"><i class="bi bi-check-circle-fill me-1"></i>Last calculation</div>
                        <div class="sub">Computed <?= e(date('d M, H:i', strtotime($calcSummary['calculated_at']))) ?></div>
                    </div>
                    <?php $v = $calcSummary['validations']; ?>
                    <div style="font-size:0.72rem;color:#047857;">
                        Validations: <b><?= $v['pass'] ?> pass</b>
                        <?= $v['warn'] > 0 ? ' · ' . $v['warn'] . ' warn' : '' ?>
                        <?= $v['fail'] > 0 ? ' · ' . $v['fail'] . ' fail' : '' ?>
                    </div>
                </div>
                <div class="tiles">
                    <div class="tile">
                        <div class="k">Net plot</div>
                        <div class="v"><?= number_format($calcSummary['net_plot'], 2) ?><span class="u"> sqm</span></div>
                    </div>
                    <div class="tile">
                        <div class="k">Total FSI</div>
                        <div class="v"><?= number_format($calcSummary['total_fsi'], 4) ?></div>
                    </div>
                    <div class="tile">
                        <div class="k">Total BUA</div>
                        <div class="v"><?= number_format($calcSummary['total_bua'], 2) ?><span class="u"> sqm</span></div>
                    </div>
                    <div class="tile">
                        <div class="k">Rehab BUA</div>
                        <div class="v"><?= number_format($calcSummary['rehab_bua'], 2) ?><span class="u"> sqm</span></div>
                    </div>
                    <div class="tile">
                        <div class="k">Sale BUA</div>
                        <div class="v"><?= number_format($calcSummary['sale_bua'], 2) ?><span class="u"> sqm</span></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ─── Nav bar ─── -->
        <div class="v2-nav">
            <div class="saved">
                <?php $lastS3 = $draft['_meta']['stage3_saved_at'] ?? null; ?>
                <?php if ($lastS3): ?>
                    <i class="bi bi-check-circle-fill" style="color:var(--v2-success);"></i>
                    Saved <?= e(date('d M, H:i', strtotime($lastS3))) ?>
                <?php else: ?>
                    <i class="bi bi-dash-circle" style="color:var(--v2-faint);"></i>
                    Not saved yet
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="index.php?page=area_analysis_v2_stage2&statement_id=<?= (int)$statement['id'] ?>" class="v2-btn ghost">
                    <i class="bi bi-arrow-left"></i> Change regulation
                </a>
                <button type="submit" class="v2-btn ghost" onclick="document.getElementById('dirInput').value='save'">
                    <i class="bi bi-arrow-clockwise"></i> Save &amp; calculate
                </button>
                <button type="submit" class="v2-btn primary" onclick="document.getElementById('dirInput').value='next'">
                    Save &amp; continue to Review
                    <i class="bi bi-arrow-right"></i>
                </button>
            </div>
        </div>
    </div>
</form>

<?php _v2_shell_close($statement, $s1, $draft['_meta']['stage3_saved_at'] ?? ($draft['_meta']['stage1_saved_at'] ?? null)); ?>

<script>
// ─── Repeat-group add/remove helpers (used by SchemaFormRenderer HTML) ─────
function v2RepAdd(key) {
    const tbody = document.querySelector('#' + key + '_tbl tbody');
    if (!tbody) return;
    const tpl = tbody.querySelector('tr.v2-rep-row');
    if (!tpl) return;
    const row = tpl.cloneNode(true);
    row.querySelectorAll('input').forEach(i => i.value = '');
    tbody.appendChild(row);
}
function v2RepRm(btn) {
    const row = btn.closest('tr.v2-rep-row');
    const tbody = row.parentNode;
    if (tbody.querySelectorAll('tr.v2-rep-row').length <= 1) {
        row.querySelectorAll('input').forEach(i => i.value = '');
        return;
    }
    row.remove();
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
