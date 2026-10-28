<?php $pageTitle = 'Best Outcome'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php
$cr  = fn($v) => $v === null ? '—' : '₹' . number_format(((float)$v) / 1e7, 2) . ' Cr';
$pct = fn($v) => $v === null ? '—' : number_format((float)$v, 2) . '%';

$verdictMap = [
    'strong_go'  => ['label' => 'STRONG GO',  'bg' => '#059669', 'fg' => '#fff', 'sub' => '#065f46'],
    'go'         => ['label' => 'GO',         'bg' => '#22c55e', 'fg' => '#fff', 'sub' => '#166534'],
    'caution'    => ['label' => 'CAUTION',    'bg' => '#f59e0b', 'fg' => '#fff', 'sub' => '#92400e'],
    'no_go'      => ['label' => 'NO-GO',      'bg' => '#dc2626', 'fg' => '#fff', 'sub' => '#991b1b'],
    'incomplete' => ['label' => 'INCOMPLETE', 'bg' => '#94a3b8', 'fg' => '#fff', 'sub' => '#64748b'],
];

$counts = ['strong_go'=>0, 'go'=>0, 'caution'=>0, 'no_go'=>0, 'incomplete'=>0];
$totalRows = 0;
foreach ($byProposal as $g) foreach ($g['items'] as $r) { $counts[$r['verdict']]++; $totalRows++; }
?>

<style>
:root {
    --bo-ink: #0f172a; --bo-muted: #64748b; --bo-faint: #94a3b8;
    --bo-border: #e2e8f0; --bo-surface: #f8fafc;
    --bo-primary: #d97706; --bo-primary-d: #b45309;
}

/* Workflow breadcrumb */
.wf-crumb {
    display: flex; align-items: center; gap: 12px; margin-bottom: 18px;
    padding: 14px 20px; background: #fff; border: 1px solid var(--bo-border); border-radius: 12px;
    font-size: 0.85rem;
}
.wf-step { display: flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 8px; text-decoration: none; color: var(--bo-muted); }
.wf-step .num {
    display: inline-flex; align-items: center; justify-content: center;
    width: 24px; height: 24px; border-radius: 50%; background: #e2e8f0; color: #64748b;
    font-weight: 800; font-size: 0.72rem; font-family: 'JetBrains Mono', Consolas, monospace;
}
.wf-step.done { color: #059669; }
.wf-step.done .num { background: #d1fae5; color: #065f46; }
.wf-step.now  { background: #fef3c7; color: #78350f; font-weight: 700; }
.wf-step.now .num { background: #d97706; color: #fff; }
.wf-arrow { color: var(--bo-faint); }

/* Hero */
.bo-hero {
    background: linear-gradient(135deg, #78350f, #d97706); color: #fff;
    border-radius: 14px; padding: 22px 26px; margin-bottom: 18px;
    display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;
}
.bo-hero h4 { font-weight: 800; margin: 0 0 4px; letter-spacing: -0.02em; }
.bo-hero p  { margin: 0; color: #fef3c7; font-size: 0.85rem; }

.bo-summary { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 20px; }
.bo-tile {
    background: #fff; border: 1px solid var(--bo-border); border-radius: 10px;
    padding: 14px 16px; text-align: center;
}
.bo-tile .n { font-family: 'JetBrains Mono', Consolas, monospace; font-weight: 800; font-size: 1.5rem; }
.bo-tile .l { color: var(--bo-muted); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 700; margin-top: 2px; }

/* Proposal block + statement cards */
.prop-hdr { color: var(--bo-muted); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; margin: 18px 0 8px; }

.st-card {
    background: #fff; border: 1px solid var(--bo-border); border-radius: 12px;
    padding: 18px 22px; margin-bottom: 12px;
    display: grid; grid-template-columns: 200px 1fr auto; gap: 22px; align-items: center;
}

.verdict-block { text-align: center; }
.verdict-pill {
    display: inline-block; padding: 8px 18px; border-radius: 8px;
    font-weight: 800; font-size: 0.88rem; letter-spacing: 0.04em;
}
.verdict-note { color: var(--bo-muted); font-size: 0.72rem; margin-top: 8px; line-height: 1.35; }

.st-body h6 { margin: 0 0 4px; font-weight: 700; color: var(--bo-ink); font-size: 0.98rem; }
.st-meta { color: var(--bo-muted); font-size: 0.78rem; margin-bottom: 12px; }
.st-metrics { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; }
.metric { }
.metric .l { color: var(--bo-muted); font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
.metric .v { font-family: 'JetBrains Mono', Consolas, monospace; font-weight: 800; font-size: 1rem; margin-top: 2px; }
.metric.pos .v { color: #059669; }
.metric.neg .v { color: #dc2626; }

.st-actions { display: flex; flex-direction: column; gap: 6px; }
.btn-plan {
    background: var(--bo-primary); color: #fff; border: 0; padding: 8px 16px;
    border-radius: 8px; font-weight: 700; font-size: 0.78rem; cursor: pointer;
    text-decoration: none; display: inline-flex; align-items: center; gap: 4px; justify-content: center; white-space: nowrap;
}
.btn-plan:hover { background: var(--bo-primary-d); color: #fff; }
.btn-plan-o {
    background: #fff; color: var(--bo-primary); border: 1px solid var(--bo-primary);
    padding: 8px 16px; border-radius: 8px; font-weight: 700; font-size: 0.78rem;
    text-decoration: none; display: inline-flex; align-items: center; gap: 4px; justify-content: center; white-space: nowrap;
}
.btn-plan-o:hover { background: #fef3c7; }

.needs-fin {
    display: inline-block; background: #fef3c7; color: #92400e;
    padding: 3px 10px; border-radius: 999px; font-size: 0.7rem; font-weight: 700;
    letter-spacing: 0.02em; margin-top: 6px;
}

.empty { background: #fff; border: 1px dashed var(--bo-border); border-radius: 12px;
         text-align: center; padding: 40px; color: var(--bo-faint); }
</style>

<div class="wf-crumb">
    <a href="index.php?page=area_analysis" class="wf-step done"><span class="num">1</span>Area Analysis <i class="bi bi-check-circle-fill"></i></a>
    <span class="wf-arrow"><i class="bi bi-arrow-right"></i></span>
    <a href="index.php?page=area_analysis_financials_list" class="wf-step done"><span class="num">2</span>Financial Analysis <i class="bi bi-check-circle-fill"></i></a>
    <span class="wf-arrow"><i class="bi bi-arrow-right"></i></span>
    <span class="wf-step now"><span class="num">3</span>Best Outcome</span>
</div>

<div class="bo-hero">
    <div>
        <h4><i class="bi bi-trophy-fill me-2"></i>Best Outcome</h4>
        <p>Every statement's GO / NO-GO verdict at a glance — with scenarios and sensitivity one click away.</p>
    </div>
</div>

<div class="bo-summary">
    <div class="bo-tile"><div class="n" style="color:#059669;"><?= $counts['strong_go'] ?></div><div class="l">Strong Go</div></div>
    <div class="bo-tile"><div class="n" style="color:#22c55e;"><?= $counts['go'] ?></div><div class="l">Go</div></div>
    <div class="bo-tile"><div class="n" style="color:#f59e0b;"><?= $counts['caution'] ?></div><div class="l">Caution</div></div>
    <div class="bo-tile"><div class="n" style="color:#dc2626;"><?= $counts['no_go'] ?></div><div class="l">No-Go</div></div>
    <div class="bo-tile"><div class="n" style="color:#94a3b8;"><?= $counts['incomplete'] ?></div><div class="l">Incomplete</div></div>
</div>

<?php if ($totalRows === 0): ?>
    <div class="empty">
        <i class="bi bi-hourglass-split" style="font-size:2rem;color:#cbd5e1;"></i>
        <h5 class="mt-2" style="color:#0f172a;">No statements ready for a verdict yet</h5>
        <p>Complete <a href="index.php?page=area_analysis">Step 1 · Area Analysis</a> to calculate a statement, then
           <a href="index.php?page=area_analysis_financials_list">Step 2 · Financial Analysis</a> to set up its financial model.</p>
    </div>
<?php else: ?>
    <?php foreach ($byProposal as $pid => $group): ?>
        <div class="prop-hdr"><i class="bi bi-building me-1"></i><?= e($group['title']) ?></div>
        <?php foreach ($group['items'] as $r):
            $v = $verdictMap[$r['verdict']] ?? $verdictMap['incomplete'];
        ?>
            <div class="st-card">
                <div class="verdict-block">
                    <div class="verdict-pill" style="background: <?= $v['bg'] ?>; color: <?= $v['fg'] ?>;">
                        <?= e($v['label']) ?>
                    </div>
                    <div class="verdict-note" style="color: <?= $v['sub'] ?>;"><?= e($r['verdict_note']) ?></div>
                </div>

                <div class="st-body">
                    <h6><?= e($r['statement_name']) ?>
                        <span style="color:var(--bo-muted);font-weight:500;font-size:0.82rem;">v<?= (int)$r['version_major'] ?>.<?= (int)$r['version_minor'] ?></span>
                    </h6>
                    <div class="st-meta">
                        <?php if ($r['selected_regulation_key']): ?>DCPR <?= e($r['selected_regulation_key']) ?> · <?php endif; ?>
                        Total BUA <?= number_format((float)($r['total_permissible_bua_sqm'] ?? 0), 0) ?> sqm ·
                        FSI <?= number_format((float)($r['total_permissible_fsi'] ?? 0), 3) ?>
                        <?php if (!$r['ready']): ?><br><span class="needs-fin">Financial model not set up</span><?php endif; ?>
                    </div>
                    <?php if ($r['ready']): ?>
                    <div class="st-metrics">
                        <div class="metric <?= ($r['np_after_int'] ?? 0) >= 0 ? 'pos' : 'neg' ?>">
                            <div class="l">NP After Int</div>
                            <div class="v"><?= $cr($r['np_after_int']) ?></div>
                        </div>
                        <div class="metric <?= ($r['margin_pct'] ?? 0) >= 15 ? 'pos' : (($r['margin_pct'] ?? 0) < 0 ? 'neg' : '') ?>">
                            <div class="l">Margin</div>
                            <div class="v"><?= $pct($r['margin_pct']) ?></div>
                        </div>
                        <div class="metric <?= ($r['irr_pct'] ?? 0) >= 20 ? 'pos' : (($r['irr_pct'] ?? 0) < 0 ? 'neg' : '') ?>">
                            <div class="l">IRR</div>
                            <div class="v"><?= $pct($r['irr_pct']) ?></div>
                        </div>
                        <div class="metric">
                            <div class="l">Revenue</div>
                            <div class="v" style="color:var(--bo-ink);"><?= $cr($r['revenue_inr']) ?></div>
                        </div>
                        <div class="metric">
                            <div class="l">Total Cost</div>
                            <div class="v" style="color:var(--bo-ink);"><?= $cr($r['cost_inr']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="st-actions">
                    <?php if ($r['ready']): ?>
                        <a href="index.php?page=area_analysis_scenarios&statement_id=<?= (int)$r['id'] ?>" class="btn-plan">
                            <i class="bi bi-arrow-left-right"></i> Scenarios
                        </a>
                        <a href="index.php?page=area_analysis_sensitivity&statement_id=<?= (int)$r['id'] ?>" class="btn-plan-o">
                            <i class="bi bi-sliders"></i> Sensitivity
                        </a>
                        <a href="index.php?page=area_analysis_pdf_report&statement_id=<?= (int)$r['id'] ?>" class="btn-plan-o" style="border-color:#dc2626;color:#dc2626;">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                        </a>
                    <?php else: ?>
                        <a href="index.php?page=area_analysis_financials_form&statement_id=<?= (int)$r['id'] ?>" class="btn-plan">
                            <i class="bi bi-plus-circle"></i> Set up financials
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
