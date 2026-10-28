<?php $pageTitle = 'DA vs DCPR Reconciliation — ' . e($proposal['proposal_name']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<style>
    .recon-hero { background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff; border-radius: 14px; padding: 22px 26px; margin-bottom: 20px; }
    .recon-hero h5 { margin: 0; font-weight: 700; }
    .recon-hero p  { margin: 4px 0 0; color: #cbd5e1; font-size: 0.82rem; }

    .card-block { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px 22px; margin-bottom: 18px; }
    .card-block h6 { font-weight: 700; text-transform: uppercase; letter-spacing: .06em; font-size: 0.72rem; color: #0f172a; margin: 0 0 12px; }

    .coverage-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; }
    .cov-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 16px; }
    .cov-card .lbl { color: #64748b; font-size: 0.68rem; text-transform: uppercase; letter-spacing: .06em; }
    .cov-card .val { color: #0f172a; font-weight: 700; font-size: 1.25rem; margin-top: 4px; }
    .cov-card.covered .val   { color: #16a34a; }
    .cov-card.missing .val   { color: #f59e0b; }
    .cov-card.conflict .val  { color: #dc2626; }

    .conflict-banner {
        background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b;
        padding: 12px 16px; border-radius: 10px; margin-bottom: 18px; font-size: 0.85rem;
    }
    .conflict-banner b { color: #7f1d1d; }

    /* Reconciliation table */
    table.recon { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
    table.recon th {
        background: #f8fafc; color: #64748b; font-weight: 600; text-transform: uppercase;
        font-size: 0.66rem; letter-spacing: .04em; padding: 8px 10px; text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }
    table.recon th.n { text-align: right; }
    table.recon td { padding: 10px; vertical-align: top; border-bottom: 1px solid #f1f5f9; }
    table.recon td.n { text-align: right; font-family: 'JetBrains Mono', 'Consolas', monospace; }
    .cell-label { font-weight: 600; color: #0f172a; }
    .cell-hint  { color: #64748b; font-size: 0.7rem; margin-top: 2px; }
    .cell-hint .cite { color: #6366f1; }
    .right-marker { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #ede9fe; color: #6d28d9; font-size: 0.66rem; font-weight: 700; margin-left: 4px; }

    .flag-pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 0.7rem; font-weight: 700; }
    .flag-pill.match    { background: #dcfce7; color: #166534; }
    .flag-pill.minor    { background: #fef3c7; color: #92400e; }
    .flag-pill.conflict { background: #fee2e2; color: #991b1b; }
    .flag-pill.da_only  { background: #ede9fe; color: #6d28d9; }
    .flag-pill.dcpr_only{ background: #dbeafe; color: #1e40af; }

    .missing-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 8px 16px; }
    .missing-list .m { color: #92400e; font-size: 0.82rem; padding: 6px 10px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; }
    .missing-list .m .cite { color: #64748b; font-size: 0.7rem; display: block; margin-top: 2px; }

    .principle-strip {
        background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 16px;
        font-size: 0.78rem; color: #475569; margin-bottom: 18px;
    }
    .principle-strip b { color: #0f172a; }
</style>

<a href="index.php?page=documents&proposal_id=<?= (int)$proposal['id'] ?>" class="text-muted small text-decoration-none">
    <i class="bi bi-arrow-left me-1"></i>Back to Documents
</a>

<div class="recon-hero mt-2">
    <h5><i class="bi bi-shield-check me-2"></i>DA vs DCPR Reconciliation</h5>
    <p>Proposal: <b><?= e($proposal['proposal_name']) ?></b>
       <?php if ($plan): ?> · Comparing against plan <b><?= e($plan['plan_name']) ?></b> (DCPR <?= e($plan['regulation_key']) ?>)<?php else: ?> · <span style="color:#fbbf24">No calculated plan yet — comparisons disabled</span><?php endif; ?>
    </p>
</div>

<div class="principle-strip">
    <b>Core principle:</b> AI extracts information · Rules determine legality · The calculation engine determines feasibility · The user approves uncertain information.
    A DA statement is <b>never</b> automatically treated as regulatory truth.
</div>

<?php if (!empty($conflicts)): ?>
<div class="conflict-banner">
    🔴 <b>Regulatory Review Required</b> — <?= count($conflicts) ?> DA-stated development right(s) diverge materially (&gt;5%) from the DCPR calculation.
    Legal/planning verification recommended before proceeding.
</div>
<?php endif; ?>

<!-- Coverage strip -->
<div class="coverage-strip mb-3">
    <div class="cov-card"><div class="lbl">Required Inputs (Reg <?= e($plan['regulation_key'] ?? '33(10)') ?>)</div><div class="val"><?= (int)$coverage['required_total'] ?></div></div>
    <div class="cov-card covered"><div class="lbl">Covered by DA</div><div class="val"><?= (int)$coverage['covered'] ?></div></div>
    <div class="cov-card missing"><div class="lbl">Missing</div><div class="val"><?= (int)$coverage['missing'] ?></div></div>
    <div class="cov-card conflict"><div class="lbl">Conflicts</div><div class="val"><?= (int)$coverage['conflicts'] ?></div></div>
    <div class="cov-card"><div class="lbl">Coverage %</div><div class="val"><?= $coverage['required_total'] ? number_format($coverage['covered'] / $coverage['required_total'] * 100, 0) : 0 ?>%</div></div>
</div>

<!-- Missing Information -->
<?php if (!empty($missing)): ?>
<div class="card-block">
    <h6><i class="bi bi-exclamation-triangle me-1 text-warning"></i>Missing Information (<?= count($missing) ?>)</h6>
    <div class="missing-list">
        <?php foreach ($missing as $m): ?>
            <div class="m">
                <span><?= e($m['label']) ?></span>
                <span class="cite">Required for Reg <?= e($plan['regulation_key'] ?? '33(10)') ?> — not present in DA or not yet verified</span>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="mt-3 small text-muted">
        Enter these values manually in the planning form or upload the missing document(s).
        <?php if ($plan): ?>
            <a href="index.php?page=planning_form&plan_id=<?= (int)$plan['id'] ?>" class="btn btn-sm btn-outline-primary ms-2">Go to Planning Form</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Reconciliation table -->
<div class="card-block">
    <h6><i class="bi bi-arrow-left-right me-1"></i>DA vs DCPR — Side by Side</h6>

    <?php if (empty($reconciliations)): ?>
        <div class="text-muted small py-3">No comparable pairs yet. Extract DA data and calculate a plan to enable reconciliation.</div>
    <?php else: ?>
    <table class="recon">
        <thead>
            <tr>
                <th style="width: 30%">Parameter</th>
                <th class="n">DA Stated</th>
                <th class="n">DCPR Calculated</th>
                <th class="n">Δ (abs · %)</th>
                <th style="width: 12%">Flag</th>
                <th style="width: 18%">Regulation</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Sort: conflicts first, then minor, then match, then singletons
            $rank = ['conflict' => 0, 'minor' => 1, 'da_only' => 2, 'dcpr_only' => 2, 'match' => 3];
            usort($reconciliations, fn($a, $b) => ($rank[$a['flag']] ?? 9) - ($rank[$b['flag']] ?? 9));
            foreach ($reconciliations as $r):
                $flagLabel = ['match' => 'Match', 'minor' => 'Minor diff', 'conflict' => 'Conflict', 'da_only' => 'DA only', 'dcpr_only' => 'DCPR only'][$r['flag']] ?? $r['flag'];
                $dec = ($r['kind'] === 'ratio') ? 3 : 2;
            ?>
                <tr>
                    <td>
                        <div class="cell-label"><?= e($r['label']) ?><?php if ($r['is_right']): ?><span class="right-marker">DA-STATED</span><?php endif; ?></div>
                        <?php if ($r['da_source']): ?>
                            <div class="cell-hint"><span class="cite">DA:</span> <?= e($r['da_source']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="n">
                        <?php if ($r['da_value'] !== null): ?>
                            <?= number_format($r['da_value'], $dec) ?> <?= e($r['da_unit']) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td class="n">
                        <?php if ($r['dcpr_value'] !== null): ?>
                            <?= number_format($r['dcpr_value'], $dec) ?> <?= e($r['da_unit']) ?>
                        <?php else: ?>
                            <span class="text-muted">— (no DCPR calc)</span>
                        <?php endif; ?>
                    </td>
                    <td class="n">
                        <?php if ($r['delta_abs'] !== null): ?>
                            <?php
                            $col = $r['flag'] === 'conflict' ? '#dc2626' : ($r['flag'] === 'minor' ? '#f59e0b' : '#16a34a');
                            $sign = $r['delta_abs'] >= 0 ? '+' : '';
                            ?>
                            <span style="color: <?= $col ?>; font-weight: 700;">
                                <?= $sign . number_format($r['delta_abs'], $dec) ?>
                                <br><small>(<?= $sign . number_format($r['delta_pct'], 2) ?>%)</small>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">n/a</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="flag-pill <?= e($r['flag']) ?>"><?= e($flagLabel) ?></span></td>
                    <td class="cell-hint"><span class="cite"><?= e($r['dcpr_ref']) ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="mt-3 small text-muted">
        <b>Match</b> = within 1% · <b>Minor</b> = 1–5% · <b>Conflict</b> = &gt;5% on a DA-stated development right.<br>
        Neutral facts (plot area, road width, tenement counts) at 5%+ delta are shown as <b>Minor</b> — they usually reflect measurement basis differences, not regulatory contradictions.
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
