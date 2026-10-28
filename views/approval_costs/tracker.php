<?php $pageTitle = 'Critical Tracker — ' . e($statement['statement_name']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php
// Comes from controller: $statement, $proposal, $alerts, $counts
require_once __DIR__ . '/../../lib/ApprovalCostCalculator.php';
$fmtPhase = fn($p) => ucfirst(str_replace('_', ' ', (string)$p));
$fmtInr   = fn($v) => ApprovalCostCalculator::formatInr($v === null ? null : (float)$v);
$fmtDate  = fn($d) => $d ? date('d M Y', strtotime($d)) : '—';
$daysFromNow = function($d) {
    if (!$d) return null;
    return (int)((strtotime($d) - strtotime(date('Y-m-d'))) / 86400);
};
?>

<style>
:root {
    --ac-ink: #0f172a; --ac-muted: #64748b; --ac-faint: #94a3b8;
    --ac-border: #e2e8f0; --ac-surface: #f8fafc;
    --ac-primary: #0ea5e9; --ac-primary-d: #0369a1;
    --ac-success: #059669; --ac-warning: #d97706; --ac-danger: #dc2626;
}
.d-crumb { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; font-size: 0.84rem; color: var(--ac-muted); }
.d-crumb a { color: var(--ac-primary-d); text-decoration: none; font-weight: 600; }
.d-crumb .sep { color: var(--ac-faint); }

.tk-hero {
    background: linear-gradient(135deg, #991b1b 0%, #dc2626 100%); color: #fff;
    border-radius: 14px; padding: 20px 26px; margin-bottom: 14px;
    display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap;
}
.tk-hero h4 { font-weight: 800; margin: 0 0 3px; letter-spacing: -0.02em; }
.tk-hero p  { color: #fecaca; font-size: 0.84rem; margin: 0; max-width: 580px; }

.tk-counts { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 14px; }
.tk-count { background: #fff; border: 1px solid var(--ac-border); border-radius: 10px; padding: 12px 14px; cursor: pointer; transition: border-color .12s; text-decoration: none; color: inherit; display: block; }
.tk-count:hover { border-color: var(--ac-primary); color: inherit; text-decoration: none; }
.tk-count .k { font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--ac-muted); font-weight: 700; }
.tk-count .v { font-family: 'JetBrains Mono', Consolas, monospace; font-size: 1.4rem; font-weight: 800; margin-top: 3px; }
.tk-count.crit .v    { color: var(--ac-danger); }
.tk-count.over .v    { color: var(--ac-warning); }
.tk-count.blk .v     { color: var(--ac-danger); }
.tk-count.docs .v    { color: var(--ac-warning); }
.tk-count.exp .v     { color: var(--ac-warning); }
.tk-count.foll .v    { color: var(--ac-primary-d); }
.tk-count.cost .v    { color: var(--ac-danger); }

.tk-section { margin-bottom: 18px; }
.tk-head {
    display: flex; align-items: center; gap: 10px; margin-bottom: 8px;
    font-weight: 800; color: var(--ac-ink); font-size: 1rem;
}
.tk-head .chip {
    display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px;
    border-radius: 50%; font-family: 'JetBrains Mono', Consolas, monospace; font-size: 0.78rem; font-weight: 800;
}
.tk-head.crit .chip { background: #fee2e2; color: #991b1b; }
.tk-head.over .chip { background: #ffedd5; color: #9a3412; }
.tk-head.blk .chip  { background: #fee2e2; color: #991b1b; }
.tk-head.docs .chip { background: #fef3c7; color: #92400e; }
.tk-head.exp .chip  { background: #fef3c7; color: #92400e; }
.tk-head.foll .chip { background: #e0f2fe; color: #075985; }
.tk-head.cost .chip { background: #fee2e2; color: #991b1b; }

.tk-list { background: #fff; border: 1px solid var(--ac-border); border-radius: 12px; overflow: hidden; }
.tk-row {
    display: grid; grid-template-columns: 1fr auto auto auto; gap: 14px; align-items: center;
    padding: 12px 18px; border-bottom: 1px solid #f1f5f9;
    text-decoration: none; color: inherit;
}
.tk-row:last-child { border-bottom: 0; }
.tk-row:hover { background: var(--ac-surface); color: inherit; text-decoration: none; }
.tk-row .n { font-weight: 700; color: var(--ac-ink); font-size: 0.9rem; }
.tk-row .m { color: var(--ac-muted); font-size: 0.72rem; margin-top: 2px; }
.tk-row .num { font-family: 'JetBrains Mono', Consolas, monospace; font-size: 0.78rem; color: var(--ac-ink); text-align: right; white-space: nowrap; }
.tk-row .pill { padding: 2px 9px; border-radius: 999px; font-size: 0.65rem; font-weight: 700; letter-spacing: 0.02em; }
.tk-row .pill.danger   { background: #fee2e2; color: #991b1b; }
.tk-row .pill.warn     { background: #fef3c7; color: #92400e; }
.tk-row .pill.primary  { background: #e0f2fe; color: #075985; }

.phase-chip { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 0.62rem; font-weight: 700; letter-spacing: 0.02em; background: #f1f5f9; color: #475569; }

.tk-empty { padding: 20px; text-align: center; color: var(--ac-muted); font-size: 0.86rem; display: flex; align-items: center; justify-content: center; gap: 6px; }
.tk-empty i { color: var(--ac-success); }

.tk-all-clear {
    background: linear-gradient(135deg, #065f46, #059669); color: #fff;
    border-radius: 14px; padding: 22px 26px; text-align: center;
}
.tk-all-clear h5 { margin: 0 0 4px; font-weight: 800; }
.tk-all-clear p { margin: 0; color: #a7f3d0; font-size: 0.86rem; }
</style>

<div class="d-crumb">
    <a href="index.php?page=approval_costs"><i class="bi bi-shield-check me-1"></i>Approval Costs</a>
    <span class="sep">›</span>
    <a href="index.php?page=approval_costs_register&statement_id=<?= (int)$statement['id'] ?>"><?= e($proposal['proposal_name']) ?></a>
    <span class="sep">›</span>
    <span>Critical Tracker</span>
</div>

<?php $flash = flashGet(); if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> py-2 px-3 mb-3" style="font-size:0.84rem;border-radius:8px;">
        <?= e($flash['msg']) ?>
    </div>
<?php endif; ?>

<!-- Hero -->
<div class="tk-hero">
    <div>
        <h4><i class="bi bi-exclamation-triangle-fill me-2"></i>Critical Approval Tracker</h4>
        <p>Everything that could stall the project — surfaced in one place. Click any count or row to jump to the item.</p>
    </div>
    <div style="color:#fecaca;font-size:0.8rem;text-align:right;">
        <?= (int)$counts['critical_total'] ?> critical requirement<?= $counts['critical_total'] !== 1 ? 's' : '' ?> on the register
    </div>
</div>

<!-- Counts strip -->
<div class="tk-counts">
    <a class="tk-count crit" href="#critical_not_initiated"><div class="k">Critical not started</div><div class="v"><?= $counts['critical_not_initiated'] ?></div></a>
    <a class="tk-count over" href="#overdue"><div class="k">Overdue</div><div class="v"><?= $counts['overdue'] ?></div></a>
    <a class="tk-count blk"  href="#blocked"><div class="k">Blocked</div><div class="v"><?= $counts['blocked'] ?></div></a>
    <a class="tk-count docs" href="#docs_pending"><div class="k">Docs pending</div><div class="v"><?= $counts['docs_pending'] ?></div></a>
    <a class="tk-count exp"  href="#expiring"><div class="k">Expiring (60d)</div><div class="v"><?= $counts['expiring'] ?></div></a>
    <a class="tk-count foll" href="#followup_due"><div class="k">Follow-up due</div><div class="v"><?= $counts['followup_due'] ?></div></a>
    <a class="tk-count cost" href="#high_cost_low_conf"><div class="k">High-cost unverified</div><div class="v"><?= $counts['high_cost_low_conf'] ?></div></a>
</div>

<?php
$totalAlerts = $counts['critical_not_initiated'] + $counts['overdue'] + $counts['blocked']
             + $counts['docs_pending'] + $counts['expiring'] + $counts['followup_due']
             + $counts['high_cost_low_conf'];
if ($totalAlerts === 0): ?>
    <div class="tk-all-clear">
        <h5><i class="bi bi-check-circle-fill me-1"></i>All clear</h5>
        <p>No overdue, blocked, expiring or critical-not-initiated items. Keep the follow-ups moving.</p>
    </div>
<?php endif; ?>

<?php
$renderSection = function(string $id, string $css, string $title, array $rows, string $emptyMsg) use ($fmtInr, $fmtDate, $daysFromNow, $fmtPhase) {
    ?>
    <div class="tk-section" id="<?= $id ?>">
        <div class="tk-head <?= $css ?>">
            <span class="chip"><?= count($rows) ?></span>
            <?= e($title) ?>
        </div>
        <?php if (empty($rows)): ?>
            <div class="tk-list">
                <div class="tk-empty"><i class="bi bi-check-circle-fill"></i> <?= e($emptyMsg) ?></div>
            </div>
        <?php else: ?>
            <div class="tk-list">
                <?php foreach ($rows as $r):
                    $displayName = $r['master_name'] ?: $r['custom_name'];
                    $cost = (float)($r['cost_total'] ?? 0);
                    $daysToTarget   = $daysFromNow($r['target_decision_at'] ?? null);
                    $daysToExpiry   = $daysFromNow($r['expiry_at'] ?? null);
                    $daysToFollowup = $daysFromNow($r['followup_next_at'] ?? null);
                ?>
                    <a class="tk-row" href="index.php?page=approval_costs_detail&id=<?= (int)$r['id'] ?>">
                        <div>
                            <div class="n"><?= e($displayName) ?></div>
                            <div class="m">
                                <span class="phase-chip"><?= e($fmtPhase($r['project_phase'])) ?></span>
                                · <?= e(ucfirst($r['criticality'])) ?>
                                · <?= e($fmtPhase($r['status'])) ?>
                                <?php if (!empty($r['authority_hint'])): ?> · <?= e($r['authority_hint']) ?><?php endif; ?>
                            </div>
                        </div>
                        <div style="min-width: 110px;">
                            <?php if ($id === 'overdue' && $daysToTarget !== null): ?>
                                <span class="pill danger"><?= abs($daysToTarget) ?>d overdue</span>
                            <?php elseif ($id === 'expiring' && $daysToExpiry !== null): ?>
                                <span class="pill warn">expires in <?= $daysToExpiry ?>d</span>
                            <?php elseif ($id === 'followup_due' && $daysToFollowup !== null): ?>
                                <span class="pill primary"><?= $daysToFollowup < 0 ? abs($daysToFollowup).'d overdue' : ($daysToFollowup === 0 ? 'today' : 'in '.$daysToFollowup.'d') ?></span>
                            <?php elseif ($id === 'blocked'): ?>
                                <span class="pill danger"><?= e($fmtPhase($r['status'])) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="num">
                            <?php if ($cost > 0): ?><?= $fmtInr($cost) ?><?php else: ?><span style="color:var(--ac-faint);">—</span><?php endif; ?>
                            <?php if ($r['target_decision_at']): ?>
                                <div style="font-size:0.68rem;color:var(--ac-faint);">target <?= $fmtDate($r['target_decision_at']) ?></div>
                            <?php endif; ?>
                        </div>
                        <i class="bi bi-arrow-right" style="color:var(--ac-primary);"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
};

$renderSection('critical_not_initiated', 'crit', 'Critical / high-criticality not started yet', $alerts['critical_not_initiated'],
    'No critical items waiting to be started.');
$renderSection('overdue', 'over', 'Overdue — target decision date passed, no actual decision yet', $alerts['overdue'],
    'Nothing overdue right now.');
$renderSection('blocked', 'blk', 'Blocked — query raised, on hold, or rejected', $alerts['blocked'],
    'No blockers.');
$renderSection('docs_pending', 'docs', 'Documentation pending', $alerts['docs_pending'],
    'No docs-pending items.');
$renderSection('expiring', 'exp', 'Expiring within 60 days', $alerts['expiring'],
    'Nothing expiring in the next 60 days.');
$renderSection('followup_due', 'foll', 'Follow-up due in the next 7 days', $alerts['followup_due'],
    'No scheduled follow-ups coming up.');
$renderSection('high_cost_low_conf', 'cost', 'High-cost items (≥ ₹10 L) with low-confidence estimates', $alerts['high_cost_low_conf'],
    'Every high-cost estimate has a reasonable confidence tag.');
?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
