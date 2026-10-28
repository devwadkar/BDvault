<?php $pageTitle = 'Approval Register — ' . e($statement['statement_name']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php
// Comes from controller: $statement, $proposal, $approvals, $suggestions, $summary, $filters,
//                        $costTotalsByApprovalId, $rollup, $projectInputs
require_once __DIR__ . '/../../lib/ApprovalCostCalculator.php';
$fmtPhase = fn($p) => ucfirst(str_replace('_', ' ', (string)$p));
$fmtInr = fn($v) => ApprovalCostCalculator::formatInr($v === null ? null : (float)$v);
?>

<style>
:root {
    --ac-ink: #0f172a; --ac-muted: #64748b; --ac-faint: #94a3b8;
    --ac-border: #e2e8f0; --ac-surface: #f8fafc;
    --ac-primary: #0ea5e9; --ac-primary-d: #0369a1;
    --ac-success: #059669; --ac-warning: #d97706; --ac-danger: #dc2626;
}
.ac-breadcrumb { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; font-size: 0.84rem; color: var(--ac-muted); }
.ac-breadcrumb a { color: var(--ac-primary-d); text-decoration: none; font-weight: 600; }
.ac-breadcrumb .sep { color: var(--ac-faint); }

/* Top ribbon */
.ac-ribbon {
    background: #fff; border: 1px solid var(--ac-border); border-radius: 12px;
    padding: 14px 18px; margin-bottom: 14px;
    display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap;
}
.ac-ribbon .proj { font-weight: 800; color: var(--ac-ink); font-size: 1rem; }
.ac-ribbon .sub  { color: var(--ac-muted); font-size: 0.78rem; margin-top: 2px; }
.ac-ribbon .act  { display: flex; gap: 8px; flex-wrap: wrap; }
.btn-ac {
    display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px;
    font-size: 0.82rem; font-weight: 700; text-decoration: none; cursor: pointer; border: 1px solid transparent;
}
.btn-ac.primary { background: var(--ac-primary); color: #fff; border-color: var(--ac-primary); }
.btn-ac.primary:hover { background: var(--ac-primary-d); color: #fff; }
.btn-ac.ghost { background: #fff; color: var(--ac-muted); border-color: var(--ac-border); }
.btn-ac.ghost:hover { background: var(--ac-surface); color: var(--ac-ink); }

/* Dashboard tiles */
.ac-dash { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-bottom: 14px; }
.ac-dash .cell { background: #fff; border: 1px solid var(--ac-border); border-radius: 10px; padding: 12px 14px; }
.ac-dash .cell .k { font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--ac-muted); font-weight: 700; }
.ac-dash .cell .v { font-family: 'JetBrains Mono', Consolas, monospace; font-weight: 800; font-size: 1.3rem; color: var(--ac-ink); margin-top: 3px; }
.ac-dash .cell.critical  .v { color: var(--ac-danger); }
.ac-dash .cell.mand      .v { color: var(--ac-primary-d); }
.ac-dash .cell.verify    .v { color: var(--ac-warning); }
.ac-dash .cell.approved  .v { color: var(--ac-success); }

/* Filters */
.ac-filters {
    background: #fff; border: 1px solid var(--ac-border); border-radius: 12px;
    padding: 12px 14px; margin-bottom: 10px;
    display: grid; grid-template-columns: 2fr repeat(4, 1fr) auto; gap: 10px; align-items: center;
}
.ac-filters input, .ac-filters select {
    padding: 7px 10px; border: 1px solid var(--ac-border); border-radius: 8px;
    font-size: 0.82rem; background: #fff;
}
.ac-filters input:focus, .ac-filters select:focus { outline: none; border-color: var(--ac-primary); }
.ac-filters .clr { color: var(--ac-muted); font-size: 0.78rem; text-decoration: none; padding: 6px 10px; }
.ac-filters .clr:hover { color: var(--ac-ink); }

/* Checklist table */
.ac-tbl { width: 100%; font-size: 0.84rem; border-collapse: collapse; background: #fff; }
.ac-tbl thead th {
    background: var(--ac-surface); color: var(--ac-muted); text-align: left;
    padding: 10px 12px; font-weight: 700; font-size: 0.66rem; text-transform: uppercase;
    letter-spacing: 0.04em; border-bottom: 1px solid var(--ac-border);
    position: sticky; top: 0; z-index: 1;
}
.ac-tbl tbody td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
.ac-tbl tbody tr:hover { background: var(--ac-surface); }
.ac-tbl .name { font-weight: 700; color: var(--ac-ink); }
.ac-tbl .meta { color: var(--ac-muted); font-size: 0.72rem; margin-top: 2px; }
.ac-tbl .alias { color: var(--ac-faint); font-style: italic; font-size: 0.72rem; }
.ac-tbl .num { font-family: 'JetBrains Mono', Consolas, monospace; }
.ac-tbl .act-cell { text-align: right; white-space: nowrap; }

/* Pills */
.pill-sm { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.64rem; font-weight: 700; letter-spacing: 0.02em; }
.pill-sm.mandatory             { background: #dbeafe; color: #1e40af; }
.pill-sm.conditional           { background: #ede9fe; color: #6d28d9; }
.pill-sm.not_applicable        { background: #f1f5f9; color: #64748b; }
.pill-sm.requires_verification { background: #fef3c7; color: #92400e; }

.pill-sm.critical { background: #fee2e2; color: #991b1b; }
.pill-sm.high     { background: #ffedd5; color: #9a3412; }
.pill-sm.medium   { background: #e0f2fe; color: #075985; }
.pill-sm.low      { background: #f1f5f9; color: #64748b; }

.pill-sm.not_started     { background: #f1f5f9; color: #64748b; }
.pill-sm.docs_pending    { background: #fef3c7; color: #92400e; }
.pill-sm.ready_to_apply  { background: #e0e7ff; color: #3730a3; }
.pill-sm.applied         { background: #dbeafe; color: #1e40af; }
.pill-sm.under_review    { background: #dbeafe; color: #1e40af; }
.pill-sm.query_raised    { background: #fef3c7; color: #92400e; }
.pill-sm.approved        { background: #d1fae5; color: #065f46; }
.pill-sm.rejected        { background: #fee2e2; color: #991b1b; }
.pill-sm.on_hold         { background: #ffedd5; color: #9a3412; }
.pill-sm.not_applicable  { background: #f1f5f9; color: #64748b; }
.pill-sm.expired         { background: #fecaca; color: #991b1b; }
.pill-sm.flagged         { background: #fef3c7; color: #92400e; }

.phase-chip {
    display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 0.62rem; font-weight: 700; letter-spacing: 0.02em;
    background: #f1f5f9; color: #475569;
}

.ac-empty { padding: 36px; text-align: center; color: var(--ac-faint); font-size: 0.88rem; }
.ac-empty i { font-size: 2rem; color: #cbd5e1; display: block; margin-bottom: 8px; }

/* Add drawer (modal) */
.ac-pop { position: fixed; inset: 0; background: rgba(15,23,42,0.5); display: none; align-items: flex-start; justify-content: center; z-index: 1000; padding: 30px 20px; overflow: auto; }
.ac-pop.open { display: flex; }
.ac-pop-box { background: #fff; border-radius: 14px; width: 100%; max-width: 760px; max-height: 85vh; overflow: hidden; display: flex; flex-direction: column; }
.ac-pop-hd { padding: 14px 20px; border-bottom: 1px solid var(--ac-border); display: flex; justify-content: space-between; align-items: center; }
.ac-pop-hd h5 { margin: 0; font-weight: 700; }
.ac-pop-hd .x { background: transparent; border: 0; font-size: 1.4rem; color: var(--ac-muted); cursor: pointer; }
.ac-pop-filter { padding: 10px 20px; border-bottom: 1px solid var(--ac-border); }
.ac-pop-filter input { width: 100%; padding: 8px 12px; border: 1px solid var(--ac-border); border-radius: 8px; font-size: 0.84rem; }
.ac-pop-body { overflow-y: auto; padding: 0; }
.sug-row { display: grid; grid-template-columns: 32px 1fr auto; gap: 10px; align-items: center; padding: 10px 20px; border-bottom: 1px solid #f1f5f9; }
.sug-row:hover { background: var(--ac-surface); }
.sug-row .n { font-weight: 600; color: var(--ac-ink); font-size: 0.86rem; }
.sug-row .m { color: var(--ac-muted); font-size: 0.72rem; }
.sug-row input[type=checkbox] { transform: scale(1.2); accent-color: var(--ac-primary); }
.ac-pop-foot { padding: 12px 20px; border-top: 1px solid var(--ac-border); display: flex; justify-content: space-between; align-items: center; gap: 10px; }
.ac-pop-foot .sel-count { color: var(--ac-muted); font-size: 0.82rem; }
</style>

<!-- Breadcrumb -->
<div class="ac-breadcrumb">
    <a href="index.php?page=approval_costs"><i class="bi bi-shield-check me-1"></i>Approval Costs</a>
    <span class="sep">›</span>
    <span><?= e($proposal['proposal_name']) ?></span>
    <span class="sep">·</span>
    <span style="font-family:'JetBrains Mono',Consolas,monospace;">v<?= (int)$statement['version_major'] ?>.<?= (int)$statement['version_minor'] ?></span>
</div>

<?php $flash = flashGet(); if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> py-2 px-3 mb-3" style="font-size:0.84rem;border-radius:8px;">
        <?= e($flash['msg']) ?>
    </div>
<?php endif; ?>

<!-- Project ribbon -->
<div class="ac-ribbon">
    <div>
        <div class="proj"><?= e($proposal['proposal_name']) ?></div>
        <div class="sub">
            <?= e($statement['statement_name']) ?>
            <?php if ($statement['selected_regulation_key']): ?> · DCPR <?= e($statement['selected_regulation_key']) ?><?php endif; ?>
            <?php if ($statement['planning_authority']): ?> · <?= e($statement['planning_authority']) ?><?php endif; ?>
            <?php if ($statement['ward']): ?> · Ward <?= e($statement['ward']) ?><?php endif; ?>
        </div>
    </div>
    <div class="act">
        <a href="index.php?page=approval_costs_tracker&statement_id=<?= (int)$statement['id'] ?>" class="btn-ac ghost">
            <i class="bi bi-exclamation-triangle"></i> Critical tracker
        </a>
        <a href="index.php?page=approval_costs_applicability&statement_id=<?= (int)$statement['id'] ?>" class="btn-ac ghost">
            <i class="bi bi-funnel"></i> Review applicability
        </a>
        <button type="button" class="btn-ac ghost" onclick="openCustom()">
            <i class="bi bi-plus-lg"></i> Add custom
        </button>
        <button type="button" class="btn-ac primary" onclick="openSuggest()">
            <i class="bi bi-journal-plus"></i> Add from library (<?= count($suggestions) ?>)
        </button>
    </div>
</div>

<?php
$totalAlerts = ($alertCounts['critical_not_initiated'] ?? 0)
             + ($alertCounts['overdue'] ?? 0)
             + ($alertCounts['blocked'] ?? 0)
             + ($alertCounts['followup_due'] ?? 0)
             + ($alertCounts['expiring'] ?? 0);
if ($totalAlerts > 0): ?>
<a href="index.php?page=approval_costs_tracker&statement_id=<?= (int)$statement['id'] ?>"
   style="display:flex;align-items:center;gap:14px;background:linear-gradient(135deg,#991b1b,#dc2626);color:#fff;border-radius:12px;padding:12px 18px;margin-bottom:14px;text-decoration:none;flex-wrap:wrap;">
    <i class="bi bi-exclamation-triangle-fill" style="font-size:1.2rem;"></i>
    <div style="flex:1;font-size:0.86rem;font-weight:700;">
        <?= $totalAlerts ?> item<?= $totalAlerts !== 1 ? 's' : '' ?> needs attention
        <span style="font-weight:400;color:#fecaca;font-size:0.78rem;">
            <?php
            $parts = [];
            if ($alertCounts['critical_not_initiated']) $parts[] = $alertCounts['critical_not_initiated'] . ' critical not started';
            if ($alertCounts['overdue'])                $parts[] = $alertCounts['overdue'] . ' overdue';
            if ($alertCounts['blocked'])                $parts[] = $alertCounts['blocked'] . ' blocked';
            if ($alertCounts['followup_due'])           $parts[] = $alertCounts['followup_due'] . ' follow-up due';
            if ($alertCounts['expiring'])               $parts[] = $alertCounts['expiring'] . ' expiring';
            echo '· ' . e(implode(' · ', $parts));
            ?>
        </span>
    </div>
    <span style="background:#fff;color:#991b1b;padding:5px 12px;border-radius:8px;font-weight:800;font-size:0.78rem;white-space:nowrap;">
        Open tracker <i class="bi bi-arrow-right"></i>
    </span>
</a>
<?php endif; ?>

<!-- Dashboard -->
<div class="ac-dash">
    <div class="cell"><div class="k">Total</div><div class="v"><?= $summary['total'] ?></div></div>
    <div class="cell mand"><div class="k">Mandatory</div><div class="v"><?= $summary['mandatory'] ?></div></div>
    <div class="cell"><div class="k">Conditional</div><div class="v"><?= $summary['conditional'] ?></div></div>
    <div class="cell verify"><div class="k">Verify</div><div class="v"><?= $summary['requires_verify'] ?></div></div>
    <div class="cell critical"><div class="k">Critical</div><div class="v"><?= $summary['critical'] ?></div></div>
    <div class="cell"><div class="k">In progress</div><div class="v"><?= $summary['in_progress'] ?></div></div>
    <div class="cell approved"><div class="k">Approved</div><div class="v"><?= $summary['approved'] ?></div></div>
    <div class="cell"><div class="k">Blocked</div><div class="v"><?= $summary['blocked'] ?></div></div>
</div>

<!-- Cost rollup strip (Stage 4) + Project inputs -->
<div style="display:grid;grid-template-columns:1fr 320px;gap:12px;margin-bottom:14px;" class="cost-strip-wrap">
    <?php if ($rollup): ?>
    <div style="background:linear-gradient(135deg,#065f46,#059669);color:#fff;border-radius:12px;padding:16px 20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:12px;flex-wrap:wrap;">
            <div style="font-weight:800;font-size:0.98rem;"><i class="bi bi-cash-stack me-1"></i>Estimated approval costs</div>
            <div style="font-size:0.7rem;color:#a7f3d0;">Computed <?= e(date('d M, H:i', strtotime($rollup['calculated_at']))) ?><?php if ($rollup['pending_components']): ?> · <?= $rollup['pending_components'] ?> pending input<?php endif; ?></div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(140px, 1fr));gap:10px;">
            <div><div style="font-size:0.6rem;color:#a7f3d0;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;">Non-refundable</div>
                <div style="font-family:'JetBrains Mono',Consolas,monospace;font-size:1.3rem;font-weight:800;margin-top:2px;"><?= $fmtInr($rollup['non_refundable_est_inr']) ?></div></div>
            <div><div style="font-size:0.6rem;color:#a7f3d0;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;">Refundable deposits</div>
                <div style="font-family:'JetBrains Mono',Consolas,monospace;font-size:1.3rem;font-weight:800;margin-top:2px;"><?= $fmtInr($rollup['refundable_est_inr']) ?></div></div>
            <div><div style="font-size:0.6rem;color:#fde68a;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;">Cash requirement</div>
                <div style="font-family:'JetBrains Mono',Consolas,monospace;font-size:1.4rem;font-weight:800;margin-top:2px;color:#fff;"><?= $fmtInr($rollup['cash_requirement_est_inr']) ?></div></div>
            <div><div style="font-size:0.6rem;color:#a7f3d0;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;">Actual to date</div>
                <div style="font-family:'JetBrains Mono',Consolas,monospace;font-size:1.3rem;font-weight:800;margin-top:2px;"><?= $fmtInr($rollup['actual_total_inr']) ?></div></div>
        </div>
        <?php if ($rollup['unverified_count'] > 0): ?>
            <div style="margin-top:10px;font-size:0.72rem;color:#fde68a;">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                <?= $rollup['unverified_count'] ?> cost component<?= $rollup['unverified_count'] !== 1 ? 's' : '' ?> not from an officially verified source.
            </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div style="background:#fef3c7;border:1px solid #fcd34d;color:#92400e;border-radius:12px;padding:16px 20px;font-size:0.84rem;">
        <b>No costs computed yet.</b> Hit "Recompute costs" (right) to apply master cost components to this project's inputs.
    </div>
    <?php endif; ?>

    <div style="background:#fff;border:1px solid var(--ac-border);border-radius:12px;padding:14px 16px;">
        <div style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--ac-muted);font-weight:800;margin-bottom:8px;">Project inputs</div>

        <form method="POST" action="index.php?page=approval_costs_set_input" style="margin:0 0 8px;display:flex;gap:6px;align-items:center;">
            <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
            <input type="hidden" name="input_key" value="tree_count">
            <label style="font-size:0.78rem;color:var(--ac-ink);min-width:84px;">Tree count</label>
            <input type="number" name="input_value" min="0" step="1" value="<?= isset($projectInputs['tree_count']) ? (int)$projectInputs['tree_count'] : '' ?>"
                   style="width:80px;padding:6px 8px;border:1px solid var(--ac-border);border-radius:6px;font-size:0.84rem;" placeholder="0">
            <button type="submit" class="btn-ac ghost" style="padding:6px 10px;font-size:0.74rem;" title="Save & recompute">
                <i class="bi bi-check-lg"></i>
            </button>
        </form>

        <form method="POST" action="index.php?page=approval_costs_calculate" style="margin:0;">
            <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
            <button type="submit" class="btn-ac primary" style="width:100%;justify-content:center;">
                <i class="bi bi-arrow-clockwise"></i> Recompute costs
            </button>
        </form>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="ac-filters" action="index.php">
    <input type="hidden" name="page" value="approval_costs_register">
    <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
    <input type="text" name="q" value="<?= e($filters['filterSearch']) ?>" placeholder="Search by name, remark, next action…">
    <select name="category">
        <option value="">All categories</option>
        <?php foreach (['land_title','planning_dp','municipal','environmental','fire','location_noc','demolition','construction_inspect','completion_occupancy','registrations','technical_reports','utilities','requires_classification','custom'] as $c): ?>
            <option value="<?= $c ?>" <?= $filters['filterCategory'] === $c ? 'selected' : '' ?>><?= $fmtPhase($c) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="phase">
        <option value="">All phases</option>
        <?php foreach (['land_acquisition','pre_construction','during_construction','post_construction'] as $p): ?>
            <option value="<?= $p ?>" <?= $filters['filterPhase'] === $p ? 'selected' : '' ?>><?= $fmtPhase($p) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="appl">
        <option value="">All applicability</option>
        <?php foreach (['mandatory','conditional','not_applicable','requires_verification'] as $a): ?>
            <option value="<?= $a ?>" <?= $filters['filterAppl'] === $a ? 'selected' : '' ?>><?= $fmtPhase($a) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status">
        <option value="">All statuses</option>
        <?php foreach (['not_started','docs_pending','ready_to_apply','applied','under_review','query_raised','approved','rejected','on_hold','not_applicable','expired'] as $s): ?>
            <option value="<?= $s ?>" <?= $filters['filterStatus'] === $s ? 'selected' : '' ?>><?= $fmtPhase($s) ?></option>
        <?php endforeach; ?>
    </select>
    <div style="display:flex;gap:6px;">
        <button type="submit" class="btn-ac primary"><i class="bi bi-funnel"></i></button>
        <a class="clr" href="index.php?page=approval_costs_register&statement_id=<?= (int)$statement['id'] ?>">Clear</a>
    </div>
</form>

<!-- Main checklist table -->
<div style="background:#fff;border:1px solid var(--ac-border);border-radius:12px;overflow:hidden;">
    <?php if (empty($approvals)): ?>
        <div class="ac-empty">
            <i class="bi bi-journal-plus"></i>
            This register is empty. Click <b>Add from library</b> above to pick requirements from the master list.
        </div>
    <?php else: ?>
    <table class="ac-tbl">
        <thead>
            <tr>
                <th>Approval / Requirement</th>
                <th>Phase</th>
                <th>Applicability</th>
                <th>Status</th>
                <th style="text-align:right;">Est. cost</th>
                <th>Next action</th>
                <th class="act-cell"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($approvals as $a):
                $displayName = $a['master_name'] ?: $a['custom_name'];
                $catLabel = $a['category_key'] ?: 'custom';
                $isCustom = empty($a['master_id']);
                $tot = $costTotalsByApprovalId[(int)$a['id']] ?? null;
                $nonRef = $tot ? (float)$tot['non_ref_est'] : 0;
                $refund = $tot ? (float)$tot['refund_est'] : 0;
                $pending = $tot ? (int)$tot['pending'] : 0;
            ?>
                <tr>
                    <td>
                        <div class="name"><?= e($displayName) ?>
                            <?php if (!empty($a['requires_classification'])): ?>
                                <span class="pill-sm flagged">needs classification</span>
                            <?php endif; ?>
                            <?php if ($isCustom): ?>
                                <span class="pill-sm" style="background:#eef2ff;color:#3730a3;">custom</span>
                            <?php endif; ?>
                        </div>
                        <div class="meta"><?= e($fmtPhase($catLabel)) ?><?php if ($a['record_type']): ?> · <?= e($a['record_type']) ?><?php endif; ?>
                            <?php if ($a['authority_hint'] || $a['master_authority']): ?> · <?= e($a['authority_hint'] ?: $a['master_authority']) ?><?php endif; ?></div>
                    </td>
                    <td><span class="phase-chip"><?= e($fmtPhase($a['project_phase'])) ?></span></td>
                    <td><span class="pill-sm <?= e($a['applicability']) ?>"><?= e($fmtPhase($a['applicability'])) ?></span></td>
                    <td><span class="pill-sm <?= e($a['status']) ?>"><?= e($fmtPhase($a['status'])) ?></span></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <?php if ($nonRef > 0 || $refund > 0): ?>
                            <div class="num" style="font-size:0.82rem;color:var(--ac-ink);font-weight:700;"><?= $fmtInr($nonRef) ?></div>
                            <?php if ($refund > 0): ?>
                                <div class="num" style="font-size:0.68rem;color:var(--ac-muted);"><?= $fmtInr($refund) ?> deposit</div>
                            <?php endif; ?>
                        <?php elseif ($pending > 0): ?>
                            <span class="pill-sm" style="background:#fef3c7;color:#92400e;"><?= $pending ?> pending</span>
                        <?php else: ?>
                            <span style="color:var(--ac-faint);font-size:0.76rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="max-width:220px;">
                        <?php if ($a['next_action']): ?>
                            <span style="font-size:0.78rem;color:var(--ac-ink);"><?= e($a['next_action']) ?></span>
                        <?php else: ?>
                            <span style="color:var(--ac-faint);font-size:0.76rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="act-cell">
                        <a href="index.php?page=approval_costs_detail&id=<?= (int)$a['id'] ?>"
                           class="btn-ac ghost" style="padding:5px 10px;font-size:0.74rem;">
                            Open <i class="bi bi-arrow-right"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Suggestions modal -->
<div class="ac-pop" id="sugPop" onclick="if(event.target===this)closeSuggest()">
    <div class="ac-pop-box">
        <div class="ac-pop-hd">
            <h5><i class="bi bi-journal-plus me-1"></i>Add from master library</h5>
            <button class="x" onclick="closeSuggest()">&times;</button>
        </div>
        <div class="ac-pop-filter">
            <input type="text" id="sugFilter" oninput="filterSug(this.value)" placeholder="Filter requirements…">
        </div>
        <form method="POST" action="index.php?page=approval_costs_add" id="sugForm" style="display:contents;">
            <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
            <div class="ac-pop-body">
                <?php foreach ($suggestions as $sug):
                    $flag = !empty($sug['requires_classification']); ?>
                    <label class="sug-row" data-txt="<?= e(strtolower($sug['standardized_name'] . ' ' . $sug['category_key'] . ' ' . ($sug['authority_hint'] ?? ''))) ?>">
                        <input type="checkbox" name="master_ids[]" value="<?= (int)$sug['id'] ?>">
                        <div>
                            <div class="n"><?= e($sug['standardized_name']) ?>
                                <?php if ($flag): ?><span class="pill-sm flagged">needs classification</span><?php endif; ?>
                            </div>
                            <div class="m">
                                <?= e($fmtPhase($sug['category_key'])) ?> · <?= e($sug['record_type']) ?>
                                · <?= e($fmtPhase($sug['typical_phase'])) ?>
                                <?php if ($sug['authority_hint']): ?> · <?= e($sug['authority_hint']) ?><?php endif; ?>
                            </div>
                        </div>
                        <i class="bi bi-plus-circle" style="color:var(--ac-muted);"></i>
                    </label>
                <?php endforeach; ?>
                <?php if (empty($suggestions)): ?>
                    <div class="ac-empty">Every master record is already on this register.</div>
                <?php endif; ?>
            </div>
            <div class="ac-pop-foot">
                <div class="sel-count"><span id="selCount">0</span> selected</div>
                <div>
                    <button type="button" class="btn-ac ghost" onclick="closeSuggest()">Cancel</button>
                    <button type="submit" class="btn-ac primary"><i class="bi bi-check-lg"></i> Add selected</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Custom add modal -->
<div class="ac-pop" id="customPop" onclick="if(event.target===this)closeCustom()">
    <div class="ac-pop-box" style="max-width:520px;">
        <div class="ac-pop-hd">
            <h5><i class="bi bi-plus-lg me-1"></i>Add custom requirement</h5>
            <button class="x" onclick="closeCustom()">&times;</button>
        </div>
        <form method="POST" action="index.php?page=approval_costs_add_custom" style="padding:18px 20px;">
            <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
            <div style="margin-bottom:12px;">
                <label style="font-size:0.78rem;font-weight:600;color:var(--ac-ink);display:block;margin-bottom:4px;">Name</label>
                <input type="text" name="custom_name" required
                       style="width:100%;padding:8px 12px;border:1px solid var(--ac-border);border-radius:8px;font-size:0.86rem;"
                       placeholder="e.g. Society NOC for redevelopment">
            </div>
            <div style="margin-bottom:14px;">
                <label style="font-size:0.78rem;font-weight:600;color:var(--ac-ink);display:block;margin-bottom:4px;">Project phase</label>
                <select name="project_phase" style="width:100%;padding:8px 12px;border:1px solid var(--ac-border);border-radius:8px;font-size:0.86rem;">
                    <?php foreach (['land_acquisition','pre_construction','during_construction','post_construction'] as $p): ?>
                        <option value="<?= $p ?>" <?= $p === 'pre_construction' ? 'selected' : '' ?>><?= $fmtPhase($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" class="btn-ac ghost" onclick="closeCustom()">Cancel</button>
                <button type="submit" class="btn-ac primary"><i class="bi bi-plus-lg"></i> Add</button>
            </div>
        </form>
    </div>
</div>

<script>
function openSuggest() { document.getElementById('sugPop').classList.add('open'); setTimeout(() => document.getElementById('sugFilter')?.focus(), 50); }
function closeSuggest() { document.getElementById('sugPop').classList.remove('open'); }
function openCustom()  { document.getElementById('customPop').classList.add('open'); }
function closeCustom() { document.getElementById('customPop').classList.remove('open'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeSuggest(); closeCustom(); } });

function filterSug(q) {
    const n = q.trim().toLowerCase();
    document.querySelectorAll('.sug-row').forEach(r => {
        r.style.display = (!n || r.dataset.txt.includes(n)) ? '' : 'none';
    });
}
document.querySelectorAll('#sugForm input[type=checkbox]').forEach(cb => {
    cb.addEventListener('change', () => {
        const n = document.querySelectorAll('#sugForm input[type=checkbox]:checked').length;
        document.getElementById('selCount').textContent = n;
    });
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
