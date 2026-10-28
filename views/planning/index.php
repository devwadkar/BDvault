<?php $pageTitle = 'Development Planning'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<style>
    .planning-hero { background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff; border-radius: 14px; padding: 22px 26px; margin-bottom: 20px; }
    .planning-hero h4 { font-weight: 800; margin: 0 0 4px; letter-spacing: -0.02em; }
    .planning-hero p  { margin: 0; color: #cbd5e1; font-size: 0.85rem; }
    .card-section { border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px 20px; background: #fff; margin-bottom: 20px; }
    .card-section h5 { font-weight: 700; margin: 0 0 12px; font-size: 1rem; color: #0f172a; }
    .prop-row { display:flex; align-items:center; justify-content:space-between; padding:12px 0; border-bottom:1px solid #f1f5f9;}
    .prop-row:last-child { border-bottom: 0; }
    .prop-title { font-weight: 600; color: #0f172a; font-size: 0.9rem; }
    .prop-meta  { color: #64748b; font-size: 0.75rem; margin-top: 2px; }
    .status-badge { font-size: 0.7rem; padding: 3px 10px; border-radius: 999px; font-weight: 600; }
    .status-draft      { background: #fef3c7; color: #92400e; }
    .status-calculated { background: #dbeafe; color: #1e40af; }
    .status-validated  { background: #dcfce7; color: #166534; }
    .status-finalized  { background: #cffafe; color: #155e75; }
    .btn-start { background: #4f46e5; color:#fff; padding:6px 14px; border-radius:8px; font-size:0.78rem; font-weight:600; text-decoration:none; }
    .btn-start:hover { background:#4338ca; color:#fff; }
    .empty { color: #94a3b8; text-align: center; padding: 28px 0; font-size: 0.85rem; }
</style>

<div class="planning-hero" style="background: linear-gradient(135deg, #475569, #64748b);">
    <h4><i class="bi bi-archive me-2"></i>Development Planning (Legacy)</h4>
    <p>This module has been superseded by <b>Area Analysis</b>. Existing plans below remain fully viewable and editable. New plans should be created through the new wizard.</p>
</div>

<div style="background: linear-gradient(135deg, #eef2ff, #e0e7ff); border: 1px solid #a5b4fc; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
    <div>
        <div style="font-weight: 800; color: #312e81; font-size: 0.95rem;">
            <i class="bi bi-arrow-right-circle-fill me-1"></i>Primary path: Area Analysis
        </div>
        <div style="color: #4338ca; font-size: 0.8rem; margin-top: 4px;">
            6-step wizard · auto-suggested regulation · versioned Area Statement · unlocks Financial Analysis.
        </div>
    </div>
    <a href="index.php?page=area_analysis" style="background:#4f46e5;color:#fff;padding:8px 18px;border-radius:10px;text-decoration:none;font-weight:700;font-size:0.82rem;">
        Open Area Analysis →
    </a>
</div>

<style>
    .plan-tree { display: flex; flex-direction: column; gap: 6px; }
    .plan-item {
        display: grid; grid-template-columns: 1fr auto auto auto auto; gap: 12px;
        align-items: center; padding: 10px 14px; border: 1px solid #e5e7eb;
        border-radius: 8px; background: #fff;
    }
    .plan-item .btn-delete {
        color: #dc2626; border: 1px solid #fecaca; background: #fff;
        padding: 4px 8px; border-radius: 6px; font-size: 0.72rem;
    }
    .plan-item .btn-delete:hover { background: #fee2e2; }
    .plan-item form.d-inline { display: inline; }
    .plan-item.base { border-color: #cbd5e1; }
    .plan-item.scen { border-color: #ddd6fe; background: #faf5ff; margin-left: 28px; position: relative; }
    .plan-item.scen::before {
        content: ''; position: absolute; left: -20px; top: 50%; width: 16px; height: 1px;
        background: #a78bfa;
    }
    .plan-item .name { font-weight: 600; color: #0f172a; font-size: 0.86rem; }
    .plan-item .meta { color: #64748b; font-size: 0.72rem; margin-top: 2px; }
    .plan-item .scen-badge { background: #ede9fe; color: #6d28d9; padding: 2px 8px; border-radius: 999px; font-size: 0.68rem; font-weight: 700; margin-left: 6px; }
    .proposal-block { margin-bottom: 20px; }
    .proposal-block .p-title {
        font-size: 0.78rem; color: #64748b; margin-bottom: 6px; letter-spacing: .04em;
        text-transform: uppercase; font-weight: 600;
    }
</style>

<div class="card-section">
    <h5><i class="bi bi-file-earmark-ruled me-1 text-primary"></i>Existing Development Plans</h5>

    <?php
    // Group legacy planTree by proposal
    $byProposal = [];
    foreach ($planTree as $trunk) {
        if (!$trunk['base']) continue;
        $pid = $trunk['base']['proposal_id'];
        $byProposal[$pid]['title'] = $trunk['base']['proposal_name'];
        $byProposal[$pid]['trunks'][] = $trunk;
    }
    // Also seed proposals that ONLY have Area Statements (no legacy plans) so
    // the list stays in sync with Area Analysis.
    foreach (($areaStatementsByProposal ?? []) as $pid => $items) {
        if (isset($byProposal[$pid])) continue;
        // Look up proposal name from the first AS row (via a small JOIN was done in the controller)
        $pname = null;
        $rs = getDB()->query("SELECT proposal_name FROM proposals WHERE id = " . (int)$pid);
        if ($rs && $row = $rs->fetch_assoc()) $pname = $row['proposal_name'];
        $byProposal[$pid] = ['title' => $pname ?? 'Proposal #' . $pid, 'trunks' => []];
    }
    ?>
    <?php if (empty($byProposal)): ?>
        <div class="empty">No development plans or area statements yet.</div>
    <?php else: ?>
        <?php foreach ($byProposal as $pid => $group):
            $totalPlansOnProposal = 0;
            foreach ($group['trunks'] as $t) $totalPlansOnProposal += 1 + count($t['scenarios']);
        ?>
            <div class="proposal-block">
                <div class="p-title" style="display:flex;align-items:center;justify-content:space-between;">
                    <span><i class="bi bi-building me-1"></i><?= e($group['title']) ?></span>
                    <?php if ($totalPlansOnProposal >= 2): ?>
                        <a href="index.php?page=planning_cross_scheme&proposal_id=<?= (int)$pid ?>"
                           style="background:#10b981;color:#fff;padding:4px 10px;border-radius:6px;font-size:0.7rem;font-weight:700;text-decoration:none;letter-spacing:0.02em;text-transform:none;">
                            <i class="bi bi-columns-gap"></i> Cross-Scheme Comparison
                        </a>
                    <?php endif; ?>
                </div>
                <?php
                // Cross-flow: Area Statements for this proposal (rendered above legacy plans)
                $asItems = $areaStatementsByProposal[$pid] ?? [];
                ?>
                <?php if (!empty($asItems)): ?>
                    <div style="margin-bottom: 10px;">
                        <div style="font-size:0.65rem;color:#059669;text-transform:uppercase;letter-spacing:.06em;font-weight:800;margin-bottom:6px;display:flex;align-items:center;gap:6px;">
                            <i class="bi bi-diagram-3-fill"></i>
                            Area Analysis · <?= count($asItems) ?> version<?= count($asItems) !== 1 ? 's' : '' ?>
                            <span style="color:#94a3b8;font-weight:500;text-transform:none;letter-spacing:0;font-size:0.68rem;">— primary flow</span>
                        </div>
                        <?php foreach ($asItems as $as): ?>
                            <div class="plan-item base" style="border-color:#a7f3d0;background:#f0fdf4;">
                                <div>
                                    <div class="name">
                                        <a href="index.php?page=area_analysis_step&statement_id=<?= (int)$as['id'] ?>&step=<?= max(1, (int)$as['current_step']) ?>"
                                           class="text-decoration-none text-dark"><?= e($as['statement_name']) ?></a>
                                        <span style="background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:999px;font-size:0.65rem;font-weight:700;margin-left:6px;letter-spacing:.02em;">v<?= (int)$as['version_major'] ?>.<?= (int)$as['version_minor'] ?></span>
                                    </div>
                                    <div class="meta">
                                        <?php if ($as['selected_regulation_key']): ?>DCPR <?= e($as['selected_regulation_key']) ?> · <?php endif; ?>
                                        Step <?= (int)$as['current_step'] ?>/6 ·
                                        <?= $as['locked_at'] ? 'Locked ' . e(date('d M Y', strtotime($as['locked_at']))) : ($as['calculated_at'] ? 'Calc ' . e(date('d M Y', strtotime($as['calculated_at']))) : 'Draft') ?>
                                    </div>
                                </div>
                                <span class="status-badge status-<?= e($as['status']) ?>"><?= e(ucfirst($as['status'])) ?></span>
                                <a href="index.php?page=area_analysis_step&statement_id=<?= (int)$as['id'] ?>&step=<?= max(1, (int)$as['current_step']) ?>"
                                   class="btn btn-sm btn-outline-success py-1 px-2">
                                    <i class="bi bi-arrow-right"></i> Open
                                </a>
                                <?php if (in_array($as['status'], ['locked','calculated','superseded'], true)): ?>
                                    <a href="index.php?page=area_analysis_report&statement_id=<?= (int)$as['id'] ?>" class="btn btn-sm btn-outline-primary py-1 px-2">Report</a>
                                <?php else: ?><span></span><?php endif; ?>
                                <span></span><span></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="font-size:0.65rem;color:#64748b;text-transform:uppercase;letter-spacing:.06em;font-weight:800;margin:12px 0 6px;">
                        <i class="bi bi-archive me-1"></i>Legacy Plans · <?= $totalPlansOnProposal ?>
                    </div>
                <?php endif; ?>
                <div class="plan-tree">
                    <?php foreach ($group['trunks'] as $trunk): $b = $trunk['base']; ?>
                        <?php $scenCount = count($trunk['scenarios']); ?>
                        <div class="plan-item base">
                            <div>
                                <div class="name">
                                    <a href="index.php?page=planning_view&plan_id=<?= (int)$b['id'] ?>" class="text-decoration-none text-dark"><?= e($b['plan_name']) ?></a>
                                </div>
                                <div class="meta">DCPR <?= e($b['regulation_key']) ?> · <?= $b['calculated_at'] ? 'Calculated ' . e($b['calculated_at']) : 'Not calculated yet' ?></div>
                            </div>
                            <span class="status-badge status-<?= e($b['status']) ?>"><?= e(ucfirst($b['status'])) ?></span>
                            <a href="index.php?page=planning_form&plan_id=<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline-secondary py-1 px-2">Edit</a>
                            <a href="index.php?page=planning_view&plan_id=<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline-primary py-1 px-2">View</a>
                            <?php if (can('edit_proposals')): ?>
                            <form method="POST" action="index.php?page=planning_delete" class="d-inline"
                                  onsubmit="return confirm(<?= $scenCount > 0
                                        ? "'Delete this base plan?\\n\\nWARNING: it also has {$scenCount} scenario(s) that will be deleted with it.\\n\\nAll inputs, calculations, validations, and financial models will be permanently removed.'"
                                        : "'Delete this plan?\\n\\nAll inputs, calculations, validations, and financial models will be permanently removed.'" ?>);">
                                <input type="hidden" name="plan_id" value="<?= (int)$b['id'] ?>">
                                <button type="submit" class="btn-delete" title="Delete plan<?= $scenCount > 0 ? ' + ' . $scenCount . ' scenario(s)' : '' ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php foreach ($trunk['scenarios'] as $s): ?>
                            <div class="plan-item scen">
                                <div>
                                    <div class="name">
                                        <a href="index.php?page=planning_view&plan_id=<?= (int)$s['id'] ?>" class="text-decoration-none text-dark"><?= e($s['plan_name']) ?></a>
                                        <?php if ($s['scenario_label']): ?><span class="scen-badge">SCENARIO — <?= e($s['scenario_label']) ?></span><?php endif; ?>
                                    </div>
                                    <div class="meta">DCPR <?= e($s['regulation_key']) ?> · <?= $s['calculated_at'] ? 'Calculated ' . e($s['calculated_at']) : 'Not calculated yet' ?></div>
                                </div>
                                <span class="status-badge status-<?= e($s['status']) ?>"><?= e(ucfirst($s['status'])) ?></span>
                                <a href="index.php?page=planning_compare&base_id=<?= (int)$b['id'] ?>&scenario_id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-warning py-1 px-2"><i class="bi bi-arrow-left-right"></i> Compare</a>
                                <a href="index.php?page=planning_view&plan_id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-primary py-1 px-2">View</a>
                                <?php if (can('edit_proposals')): ?>
                                <form method="POST" action="index.php?page=planning_delete" class="d-inline"
                                      onsubmit="return confirm('Delete this scenario?\n\nAll inputs, calculations, validations, and financial models will be permanently removed.');">
                                    <input type="hidden" name="plan_id" value="<?= (int)$s['id'] ?>">
                                    <button type="submit" class="btn-delete" title="Delete scenario">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
