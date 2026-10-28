<?php $pageTitle = 'Approval Costs'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<style>
:root {
    --ac-ink: #0f172a; --ac-muted: #64748b; --ac-faint: #94a3b8;
    --ac-border: #e2e8f0; --ac-surface: #f8fafc;
    --ac-primary: #0ea5e9; --ac-primary-d: #0369a1;
    --ac-success: #059669; --ac-warning: #d97706; --ac-danger: #dc2626;
}
.ac-crumb {
    display: flex; align-items: center; gap: 8px; margin-bottom: 14px;
    padding: 12px 18px; background: #fff; border: 1px solid var(--ac-border); border-radius: 12px;
    font-size: 0.82rem; flex-wrap: wrap;
}
.ac-crumb a, .ac-crumb span.wf-step { display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 8px; text-decoration: none; color: #64748b; }
.ac-crumb .num {
    display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%;
    background: #e2e8f0; color: #64748b; font-weight: 800; font-size: 0.7rem; font-family: 'JetBrains Mono', Consolas, monospace;
}
.ac-crumb .now { background: #e0f2fe; color: #0369a1; font-weight: 700; }
.ac-crumb .now .num { background: var(--ac-primary); color: #fff; }
.ac-crumb .done { color: #059669; }
.ac-crumb .done .num { background: #d1fae5; color: #065f46; }
.ac-crumb .arrow { color: #cbd5e1; }

.ac-hero {
    background: linear-gradient(135deg, #0369a1 0%, #0ea5e9 100%); color: #fff;
    border-radius: 14px; padding: 24px 28px; margin-bottom: 18px;
    display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap;
}
.ac-hero h4 { font-weight: 800; margin: 0 0 4px; letter-spacing: -0.02em; }
.ac-hero p { margin: 0; color: #bae6fd; font-size: 0.86rem; max-width: 640px; }
.ac-hero .cta {
    background: #fff; color: var(--ac-primary-d); padding: 10px 18px; border-radius: 10px;
    font-weight: 700; font-size: 0.86rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
}

.ac-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 16px; }
.ac-tile {
    background: #fff; border: 1px solid var(--ac-border); border-radius: 12px;
    padding: 16px 18px;
}
.ac-tile .k { color: var(--ac-muted); font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
.ac-tile .v { font-family: 'JetBrains Mono', Consolas, monospace; font-size: 1.3rem; font-weight: 800; color: var(--ac-ink); margin-top: 4px; }
.ac-tile.flagged .v { color: var(--ac-warning); }

.ac-card { background: #fff; border: 1px solid var(--ac-border); border-radius: 12px; padding: 18px 22px; margin-bottom: 14px; }
.ac-card h5 { font-weight: 700; margin: 0 0 12px; font-size: 1rem; color: var(--ac-ink); display: flex; justify-content: space-between; align-items: center; }

.ac-row {
    display: grid; grid-template-columns: 1fr auto auto auto; gap: 14px; align-items: center;
    padding: 12px 16px; border: 1px solid var(--ac-border); border-radius: 10px; margin-bottom: 8px;
    text-decoration: none; color: inherit; background: #fff; transition: border-color .1s;
}
.ac-row:hover { border-color: var(--ac-primary); background: var(--ac-surface); color: inherit; text-decoration: none; }
.ac-row .n { font-weight: 700; color: var(--ac-ink); font-size: 0.9rem; }
.ac-row .m { color: var(--ac-muted); font-size: 0.74rem; margin-top: 2px; }
.ac-row .pill { padding: 2px 9px; border-radius: 999px; font-size: 0.65rem; font-weight: 700; }
.ac-row .pill.draft { background: #fef3c7; color: #92400e; }
.ac-row .pill.calculated { background: #dbeafe; color: #1e40af; }
.ac-row .pill.validated, .ac-row .pill.locked { background: #d1fae5; color: #065f46; }
.ac-row .meta { font-family: 'JetBrains Mono', Consolas, monospace; font-size: 0.72rem; color: var(--ac-muted); text-align: right; }

.ac-empty { padding: 36px; text-align: center; color: var(--ac-faint); font-size: 0.88rem; }
.ac-empty i { font-size: 2rem; color: #cbd5e1; display: block; margin-bottom: 8px; }
</style>

<!-- Workflow breadcrumb (new 4-step order) -->
<div class="ac-crumb">
    <a href="index.php?page=area_analysis" class="wf-step done"><span class="num">1</span>Area Analysis <i class="bi bi-check-circle-fill"></i></a>
    <span class="arrow"><i class="bi bi-arrow-right"></i></span>
    <span class="wf-step now"><span class="num">2</span>Approval Costs</span>
    <span class="arrow"><i class="bi bi-arrow-right"></i></span>
    <a href="index.php?page=area_analysis_financials_list" class="wf-step"><span class="num">3</span>Financial Analysis</a>
    <span class="arrow"><i class="bi bi-arrow-right"></i></span>
    <a href="index.php?page=area_analysis_best_outcome" class="wf-step"><span class="num">4</span>Best Outcome</a>
</div>

<?php $flash = flashGet(); if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> py-2 px-3 mb-3" style="font-size:0.84rem;border-radius:8px;">
        <?= e($flash['msg']) ?>
    </div>
<?php endif; ?>

<div class="ac-hero">
    <div>
        <h4><i class="bi bi-shield-check me-2"></i>Approval Costs</h4>
        <p>Identify every approval, NOC, clearance and supporting document a redevelopment project needs — estimate fees, track status, surface blockers. Powered by a reconciled master library of <?= (int)$masterCount ?> standardized requirements.</p>
    </div>
    <a href="index.php?page=approval_costs_master_library" class="cta">
        <i class="bi bi-journal-bookmark"></i> Browse master library
    </a>
</div>

<div class="ac-tiles">
    <div class="ac-tile">
        <div class="k">Master records</div>
        <div class="v"><?= (int)$masterCount ?></div>
    </div>
    <div class="ac-tile">
        <div class="k">Source aliases preserved</div>
        <div class="v"><?= (int)$aliasCount ?></div>
    </div>
    <div class="ac-tile flagged">
        <div class="k">Requires classification</div>
        <div class="v"><?= (int)$flaggedCount ?></div>
    </div>
    <div class="ac-tile">
        <div class="k">Active statements</div>
        <div class="v"><?= count($statements) ?></div>
    </div>
</div>

<div class="ac-card">
    <h5>
        <span><i class="bi bi-building me-1"></i>Area Statements</span>
        <span style="font-size:0.74rem;color:var(--ac-muted);font-weight:500;">pick one to build its approval register</span>
    </h5>
    <?php if (empty($statements)): ?>
        <div class="ac-empty">
            <i class="bi bi-folder2-open"></i>
            No calculated Area Statements yet. <a href="index.php?page=area_analysis_v2" style="color:var(--ac-primary);">Run Area Analysis first →</a>
        </div>
    <?php else: foreach ($statements as $s):
        $total = (int)($s['approval_count'] ?? 0);
        $apr   = (int)($s['approved_count'] ?? 0);
        $inp   = (int)($s['in_progress_count'] ?? 0);
    ?>
        <a class="ac-row" href="index.php?page=approval_costs_register&statement_id=<?= (int)$s['id'] ?>">
            <div>
                <div class="n"><?= e($s['proposal_name']) ?></div>
                <div class="m"><?= e($s['statement_name']) ?><?php if ($s['selected_regulation_key']): ?> · DCPR <?= e($s['selected_regulation_key']) ?><?php endif; ?></div>
            </div>
            <div><span class="pill <?= e($s['status']) ?>"><?= e(ucfirst($s['status'])) ?></span></div>
            <div class="meta">
                <?php if ($total === 0): ?>
                    <span style="color:var(--ac-faint);">no register yet</span>
                <?php else: ?>
                    <?= $total ?> item<?= $total !== 1 ? 's' : '' ?>
                    <?php if ($apr): ?> · <span style="color:var(--ac-success);"><?= $apr ?> ✓</span><?php endif; ?>
                    <?php if ($inp): ?> · <span style="color:var(--ac-primary-d);"><?= $inp ?> ⟳</span><?php endif; ?>
                <?php endif; ?>
            </div>
            <i class="bi bi-arrow-right" style="color:var(--ac-primary);"></i>
        </a>
    <?php endforeach; endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
