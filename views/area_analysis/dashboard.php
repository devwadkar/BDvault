<?php $pageTitle = 'Planning · ' . e($proposal['proposal_name']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php
$cr = fn($v) => $v ? '₹' . number_format($v / 1e7, 2) . ' Cr' : '—';
$sqm = fn($v) => $v ? number_format((float)$v, 2) . ' sqm' : '—';
$pct = fn($v) => $v === null ? '—' : number_format($v, 2) . '%';
?>

<style>
    :root {
        --db-ink: #0f172a; --db-muted: #64748b; --db-faint: #94a3b8;
        --db-border: #e2e8f0; --db-surface: #f8fafc;
        --db-primary: #4f46e5; --db-success: #059669; --db-warning: #d97706; --db-danger: #dc2626;
    }
    .db-hero {
        display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;
        padding: 24px 28px; margin-bottom: 22px; background: #fff;
        border: 1px solid var(--db-border); border-radius: 14px;
    }
    .db-hero h4 { font-weight: 700; margin: 0; color: var(--db-ink); letter-spacing: -0.01em; }
    .db-hero .sub { color: var(--db-muted); font-size: 0.85rem; margin-top: 4px; }
    .db-back { padding: 7px 14px; border-radius: 8px; background: #f1f5f9; color: #475569;
               font-size: 0.78rem; font-weight: 700; text-decoration: none; }

    .db-card { background: #fff; border: 1px solid var(--db-border); border-radius: 14px;
               padding: 24px 26px; margin-bottom: 18px; }
    .db-card h5 { font-weight: 700; margin: 0 0 16px; font-size: 1.05rem; color: var(--db-ink); letter-spacing: -0.01em; }
    .db-card .lead { color: var(--db-muted); font-size: 0.88rem; margin: -12px 0 18px; }

    /* Progress stages */
    .db-progress { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
    @media (max-width: 900px) { .db-progress { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 520px) { .db-progress { grid-template-columns: 1fr; } }
    .db-stage {
        background: #fff; border: 1px solid var(--db-border); border-radius: 12px;
        padding: 18px 20px; position: relative; text-decoration: none;
        display: block; color: inherit; transition: border-color .15s, transform .15s;
    }
    .db-stage:hover:not(.locked):not(.pending) { border-color: var(--db-primary); transform: translateY(-2px); color: inherit; }
    .db-stage .st-badge {
        position: absolute; top: 12px; right: 12px;
        width: 28px; height: 28px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 0.85rem; font-weight: 800;
    }
    .db-stage.done       .st-badge { background: var(--db-success); color: #fff; }
    .db-stage.in_progress .st-badge { background: var(--db-warning); color: #fff; }
    .db-stage.ready      .st-badge { background: var(--db-primary); color: #fff; }
    .db-stage.pending    .st-badge { background: #e2e8f0; color: var(--db-muted); }
    .db-stage.locked     .st-badge { background: #f1f5f9; color: var(--db-faint); }

    .db-stage .st-num { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--db-faint); font-weight: 700; }
    .db-stage .st-title { font-weight: 700; font-size: 1rem; color: var(--db-ink); margin: 6px 0 4px; letter-spacing: -0.01em; }
    .db-stage .st-meta { color: var(--db-muted); font-size: 0.78rem; }
    .db-stage.done .st-title, .db-stage.in_progress .st-title, .db-stage.ready .st-title { color: var(--db-ink); }
    .db-stage.locked .st-title, .db-stage.pending .st-title { color: var(--db-muted); }

    /* Key metrics */
    .db-metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
    .db-metric {
        background: var(--db-surface); border: 1px solid var(--db-border); border-radius: 10px;
        padding: 14px 16px;
    }
    .db-metric .lbl { color: var(--db-muted); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
    .db-metric .val { color: var(--db-ink); font-weight: 800; font-size: 1.15rem; margin-top: 4px; font-family: 'JetBrains Mono', Consolas, monospace; }
    .db-metric.hi   { background: linear-gradient(135deg, #ecfdf5, #d1fae5); border-color: #86efac; }
    .db-metric.hi .val { color: #064e3b; }
    .db-metric.warn { background: linear-gradient(135deg, #fef3c7, #fde68a); border-color: #fbbf24; }
    .db-metric.warn .val { color: #78350f; }

    /* Version list */
    .db-ver-row { display: grid; grid-template-columns: 1fr auto auto auto; gap: 10px;
                  padding: 12px 14px; border: 1px solid var(--db-border); border-radius: 8px;
                  align-items: center; margin-bottom: 6px; }
    .db-ver-row .v-name { font-weight: 600; color: var(--db-ink); font-size: 0.88rem; }
    .db-ver-row .v-meta { color: var(--db-muted); font-size: 0.75rem; margin-top: 2px; }
    .pill { padding: 3px 10px; border-radius: 999px; font-size: 0.7rem; font-weight: 700; }
    .pill.st-draft { background: #fef3c7; color: #92400e; }
    .pill.st-calculated { background: #dbeafe; color: #1e40af; }
    .pill.st-locked { background: #d1fae5; color: #065f46; }
    .pill.st-superseded { background: #f1f5f9; color: #64748b; }
    .pill.ver { background: #eef2ff; color: #3730a3; }

    .btn-cta { background: var(--db-primary); color: #fff; padding: 10px 20px; border-radius: 8px;
               font-weight: 700; text-decoration: none; font-size: 0.85rem; }
    .btn-cta:hover { background: #3730a3; color: #fff; }
    .btn-cta-outline { background: #fff; color: var(--db-primary); padding: 8px 16px; border: 1px solid var(--db-primary);
                       border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 0.82rem; }
</style>

<div class="db-hero">
    <div>
        <h4><i class="bi bi-building me-2" style="color: var(--db-primary);"></i><?= e($proposal['proposal_name']) ?></h4>
        <div class="sub">
            <?php if ($proposal['city']): ?><?= e($proposal['city']) ?><?php endif; ?>
            <?php if ($proposal['region']): ?> · <?= e($proposal['region']) ?><?php endif; ?>
            <?php if ($proposal['plot_area_sqm']): ?> · Plot <?= number_format((float)$proposal['plot_area_sqm'], 2) ?> sqm<?php endif; ?>
            <?php if ($active && $active['selected_regulation_key']): ?> · DCPR <?= e($active['selected_regulation_key']) ?><?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="index.php?page=area_analysis" class="db-back"><i class="bi bi-arrow-left me-1"></i>All projects</a>
        <?php if (!$active): ?>
            <form method="POST" action="index.php?page=area_analysis_start" class="d-inline">
                <input type="hidden" name="proposal_id" value="<?= (int)$proposal['id'] ?>">
                <button type="submit" class="btn-cta"><i class="bi bi-play-fill me-1"></i>Start planning</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="db-card">
    <h5>Post-Proposal Planning</h5>
    <p class="lead">Progress from Area Analysis through Feasibility Summary. Each stage unlocks the next.</p>

    <div class="db-progress">
        <?php $i = 0; foreach ($progress as $key => $stage): $i++; $st = $stage['status']; $clickable = $st !== 'locked' && $st !== 'pending' && $stage['link'] !== '#'; ?>
            <?= $clickable ? '<a href="' . e($stage['link']) . '"' : '<div' ?> class="db-stage <?= $st ?>">
                <div class="st-badge">
                    <?php if ($st === 'done') echo '✓';
                          elseif ($st === 'in_progress') echo '⋯';
                          elseif ($st === 'ready') echo '→';
                          elseif ($st === 'locked') echo '🔒';
                          else echo $i; ?>
                </div>
                <div class="st-num">Step <?= $i ?></div>
                <div class="st-title"><?= e($stage['title']) ?></div>
                <div class="st-meta"><?= e($stage['meta']) ?></div>
            <?= $clickable ? '</a>' : '</div>' ?>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($active && in_array($active['status'], ['locked','calculated','validated','superseded'], true)): ?>
<div class="db-card">
    <h5>Key numbers <span class="pill ver ms-2">v<?= (int)$active['version_major'] ?>.<?= (int)$active['version_minor'] ?></span></h5>
    <div class="db-metrics">
        <div class="db-metric"><div class="lbl">Net Plot</div>
            <div class="val" data-calc-key="net_plot_area_sqm" data-statement-id="<?= (int)$active['id'] ?>"><?= $sqm($active['net_plot_area_sqm']) ?></div></div>
        <div class="db-metric hi"><div class="lbl">Total FSI</div>
            <div class="val" data-calc-key="full_scheme_fsi" data-statement-id="<?= (int)$active['id'] ?>"><?= $active['total_permissible_fsi'] ? number_format((float)$active['total_permissible_fsi'], 3) : '—' ?></div></div>
        <div class="db-metric hi"><div class="lbl">Total BUA</div>
            <div class="val" data-calc-key="total_bua_approved_sqm" data-statement-id="<?= (int)$active['id'] ?>"><?= $sqm($active['total_permissible_bua_sqm']) ?></div></div>
        <div class="db-metric"><div class="lbl">Rehab</div>
            <div class="val" data-calc-key="rehab_tenement_bua_sqm" data-statement-id="<?= (int)$active['id'] ?>"><?= $sqm($active['rehab_bua_sqm']) ?></div></div>
        <div class="db-metric"><div class="lbl">Sale</div>
            <div class="val" data-calc-key="sale_component_sqm" data-statement-id="<?= (int)$active['id'] ?>"><?= $sqm($active['sale_bua_sqm']) ?></div></div>

        <?php if (!empty($finSummary)): ?>
            <div class="db-metric hi"><div class="lbl">Revenue</div><div class="val"><?= $cr($finSummary['total_revenue_inr'] ?? 0) ?></div></div>
            <div class="db-metric"><div class="lbl">Total Cost</div><div class="val"><?= $cr($finSummary['total_cost_inr'] ?? 0) ?></div></div>
            <?php $np = $finSummary['net_profit_after_interest_inr'] ?? 0; ?>
            <div class="db-metric <?= $np < 0 ? 'warn' : 'hi' ?>"><div class="lbl">NP after Int</div><div class="val"><?= $cr($np) ?></div></div>
            <div class="db-metric"><div class="lbl">Project IRR</div><div class="val"><?= isset($finSummary['project_irr_pct']) && $finSummary['project_irr_pct'] !== null ? number_format($finSummary['project_irr_pct'], 2) . '%' : '—' ?></div></div>
        <?php endif; ?>
    </div>

    <div class="mt-3 d-flex gap-2 flex-wrap">
        <a href="index.php?page=area_analysis_pdf_report&statement_id=<?= (int)$active['id'] ?>" class="btn-cta-outline" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border-color:#dc2626;">
            <i class="bi bi-file-earmark-pdf-fill me-1"></i>20-Section PDF Report
        </a>
        <a href="index.php?page=area_analysis_report&statement_id=<?= (int)$active['id'] ?>" class="btn-cta-outline">
            <i class="bi bi-file-earmark-text me-1"></i>Area Statement Report
        </a>
        <a href="index.php?page=area_analysis_feasibility&statement_id=<?= (int)$active['id'] ?>" class="btn-cta-outline">
            <i class="bi bi-file-earmark-ruled me-1"></i>Feasibility Statement
        </a>
        <?php if (!empty($finSummary)): ?>
            <a href="index.php?page=area_analysis_scenarios&statement_id=<?= (int)$active['id'] ?>" class="btn-cta-outline">
                <i class="bi bi-arrow-left-right me-1"></i>Scenarios
            </a>
            <a href="index.php?page=area_analysis_sensitivity&statement_id=<?= (int)$active['id'] ?>" class="btn-cta-outline">
                <i class="bi bi-sliders me-1"></i>Sensitivity
            </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($statements)): ?>
<div class="db-card">
    <h5>All versions</h5>
    <?php foreach ($statements as $s): ?>
        <div class="db-ver-row">
            <div>
                <div class="v-name"><?= e($s['statement_name']) ?></div>
                <div class="v-meta">
                    Step <?= (int)$s['current_step'] ?>/6
                    <?php if ($s['selected_regulation_key']): ?> · DCPR <?= e($s['selected_regulation_key']) ?><?php endif; ?>
                    <?php if ($s['locked_at']): ?> · Locked <?= e(date('d M Y', strtotime($s['locked_at']))) ?>
                    <?php else: ?> · Updated <?= e(date('d M Y', strtotime($s['updated_at']))) ?><?php endif; ?>
                </div>
            </div>
            <span class="pill ver">v<?= (int)$s['version_major'] ?>.<?= (int)$s['version_minor'] ?></span>
            <span class="pill st-<?= e($s['status']) ?>"><?= e(ucfirst($s['status'])) ?></span>
            <a href="index.php?page=area_analysis_step&statement_id=<?= (int)$s['id'] ?>&step=<?= max(1, (int)$s['current_step']) ?>" class="btn-cta-outline">
                <?= $s['status'] === 'locked' ? 'View' : 'Resume' ?>
            </a>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/_calc_explorer.php'; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
