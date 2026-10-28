<?php require __DIR__ . '/_wizard_shell.php'; ?>
<?php
$failCount = 0; $warnCount = 0; $passCount = 0;
if (!empty($extra['valids'] ?? null)) {
    foreach ($extra['valids'] as $v) {
        if ($v['result'] === 'fail') $failCount++;
        elseif ($v['result'] === 'warn') $warnCount++;
        else $passCount++;
    }
}
// If Step 5 hasn't been visited, we won't have $extra['valids'] — pull them
if (empty($extra['valids'] ?? null)) {
    $r = getDB()->query("SELECT result FROM area_statement_valid WHERE statement_id = " . (int)$statement['id']);
    while ($r && $row = $r->fetch_assoc()) {
        if ($row['result'] === 'fail') $failCount++;
        elseif ($row['result'] === 'warn') $warnCount++;
        else $passCount++;
    }
}
$isLocked = $statement['status'] === 'locked';
$calcDone = !empty($statement['calculated_at']);
$fmtSqm = fn($v) => ($v === null || $v === '' ? '—' : number_format((float)$v, 2) . ' sqm');
$fmtRatio = fn($v) => ($v === null || $v === '' ? '—' : number_format((float)$v, 3));
?>
<?php _aa_shell_open(); ?>

<style>
    .review-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin: 14px 0; }
    .review-metric { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px 18px; }
    .review-metric .lbl { color: #64748b; font-size: 0.7rem; text-transform: uppercase; letter-spacing: .06em; font-weight: 700; }
    .review-metric .val { color: #0f172a; font-weight: 800; font-size: 1.1rem; margin-top: 4px; font-family: 'JetBrains Mono', Consolas, monospace; }
    .review-metric .sub { color: #94a3b8; font-size: 0.72rem; }

    .checklist { display: flex; flex-direction: column; gap: 8px; margin: 14px 0; }
    .checklist-item { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-radius: 8px; font-size: 0.85rem;
                      border: 1px solid #e5e7eb; background: #fff; }
    .checklist-item .dot { font-size: 1.1rem; }
    .checklist-item.done  { background: #f0fdf4; border-color: #86efac; color: #065f46; }
    .checklist-item.miss  { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }

    .lock-cta {
        background: linear-gradient(135deg, #10b981, #059669); color: #fff;
        padding: 14px 26px; border-radius: 12px; font-size: 1rem; font-weight: 800;
        border: 0; cursor: pointer; margin-top: 16px;
    }
    .lock-cta:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(16,185,129,0.35); }
    .lock-cta:disabled { background: #cbd5e1; cursor: not-allowed; transform: none; box-shadow: none; }

    .success-banner {
        background: linear-gradient(135deg, #dcfce7, #bbf7d0);
        border: 1px solid #86efac; border-radius: 12px; padding: 18px 22px; margin: 14px 0;
    }
    .success-banner h6 { color: #065f46; font-weight: 800; margin: 0 0 4px; }
    .success-banner p { color: #047857; margin: 0; font-size: 0.85rem; }
</style>

<div class="aa-card">
    <h5 class="aa-card-title">Review &amp; generate</h5>
    <p class="aa-card-lead">Final check before locking. Once locked, this version is immutable and Financial Analysis unlocks. Create a new version for further edits.</p>

    <?php if ($isLocked): ?>
        <div class="success-banner">
            <h6><i class="bi bi-lock-fill me-1"></i>Locked as v<?= (int)$statement['version_major'] ?>.<?= (int)$statement['version_minor'] ?></h6>
            <p>Sealed <?= e($statement['locked_at']) ?>. Financial Analysis is now unlocked.</p>
        </div>
    <?php endif; ?>

    <div class="review-grid">
        <div class="review-metric">
            <div class="lbl">Project</div>
            <div class="val" style="font-size: 0.9rem; word-break: break-word;"><?= e($statement['project_name'] ?? '—') ?></div>
            <div class="sub"><?= e($statement['planning_authority'] ?? '—') ?> · <?= e(str_replace('_',' ', $statement['development_type'] ?? '—')) ?></div>
        </div>
        <div class="review-metric">
            <div class="lbl">DCPR Regulation</div>
            <div class="val"><?= e($statement['selected_regulation_key'] ?? '—') ?></div>
            <div class="sub">Suggested <?= (int)$statement['suggested_confidence'] ?>%</div>
        </div>
        <div class="review-metric">
            <div class="lbl">Net Plot</div>
            <div class="val" style="color:#059669;"><?= $fmtSqm($statement['net_plot_area_sqm']) ?></div>
        </div>
        <div class="review-metric">
            <div class="lbl">Total FSI</div>
            <div class="val"><?= $fmtRatio($statement['total_permissible_fsi']) ?></div>
            <div class="sub">Incl. fungible</div>
        </div>
        <div class="review-metric">
            <div class="lbl">Total BUA</div>
            <div class="val"><?= $fmtSqm($statement['total_permissible_bua_sqm']) ?></div>
        </div>
        <div class="review-metric">
            <div class="lbl">Rehab / Sale Split</div>
            <div class="val" style="font-size: 0.9rem;">
                <?= $fmtSqm($statement['rehab_bua_sqm']) ?> / <?= $fmtSqm($statement['sale_bua_sqm']) ?>
            </div>
        </div>
    </div>

    <div class="checklist">
        <div class="checklist-item <?= $statement['selected_regulation_key'] ? 'done' : 'miss' ?>">
            <span class="dot"><?= $statement['selected_regulation_key'] ? '✓' : '✗' ?></span>
            <div>Regulation confirmed (Step 4) — <?= $statement['selected_regulation_key'] ? 'DCPR ' . e($statement['selected_regulation_key']) : 'not yet selected' ?></div>
        </div>
        <div class="checklist-item <?= $calcDone ? 'done' : 'miss' ?>">
            <span class="dot"><?= $calcDone ? '✓' : '✗' ?></span>
            <div>Calculator run (Step 5) — <?= $calcDone ? 'calculated ' . e($statement['calculated_at']) : 'not yet run' ?></div>
        </div>
        <div class="checklist-item <?= $failCount === 0 ? 'done' : 'miss' ?>">
            <span class="dot"><?= $failCount === 0 ? '✓' : '✗' ?></span>
            <div>Validation — <b><?= $passCount ?></b> pass · <b><?= $warnCount ?></b> warn · <b class="<?= $failCount ? 'text-danger' : '' ?>"><?= $failCount ?></b> fail
                <?= $failCount > 0 ? '<span class="text-danger">(must resolve fails before lock)</span>' : '' ?>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap mt-3">
        <a href="index.php?page=area_analysis_report&statement_id=<?= (int)$statement['id'] ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-file-earmark-text me-1"></i>View Full Area Statement Report
        </a>
        <a href="index.php?page=area_analysis_export&statement_id=<?= (int)$statement['id'] ?>" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
        </a>
        <?php if ($isLocked): ?>
            <a href="index.php?page=area_analysis_financials_form&statement_id=<?= (int)$statement['id'] ?>" class="btn btn-success btn-sm">
                <i class="bi bi-cash-stack me-1"></i>Proceed to Financial Analysis →
            </a>
        <?php elseif ($calcDone && $failCount === 0): ?>
            <span class="badge bg-light text-secondary small align-self-center">Lock this version to unlock Financial Analysis</span>
        <?php endif; ?>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-4 pt-3" style="border-top: 1px solid #e5e7eb;">
        <form method="POST" action="index.php?page=area_analysis_save_step" class="d-inline">
            <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
            <input type="hidden" name="step" value="6">
            <button type="submit" name="direction" value="prev" class="aa-btn prev">
                <i class="bi bi-arrow-left me-1"></i>Previous
            </button>
        </form>
        <?php if ($isLocked): ?>
            <form method="POST" action="index.php?page=area_analysis_new_version" class="d-inline"
                  onsubmit="return confirm('Create a new draft version from this locked one?')">
                <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
                <input type="hidden" name="bump_level" value="minor">
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-plus-square me-1"></i>Create v<?= (int)$statement['version_major'] ?>.<?= (int)$statement['version_minor']+1 ?> (edit)
                </button>
            </form>
        <?php else: ?>
            <form method="POST" action="index.php?page=area_analysis_generate" class="d-inline"
                  onsubmit="return confirm('Lock this Area Statement as v<?= (int)$statement['version_major'] ?>.<?= (int)$statement['version_minor'] ?>?\n\nOnce locked, no further edits allowed on this version.');">
                <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
                <button type="submit" class="lock-cta" <?= ($failCount > 0 || !$calcDone) ? 'disabled' : '' ?>>
                    <i class="bi bi-lock-fill me-1"></i>
                    <?php if ($failCount > 0): ?>
                        Resolve validation fails to lock
                    <?php elseif (!$calcDone): ?>
                        Run Step 5 first
                    <?php else: ?>
                        Generate &amp; Lock v<?= (int)$statement['version_major'] ?>.<?= (int)$statement['version_minor'] ?>
                    <?php endif; ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php _aa_shell_close(); ?>
