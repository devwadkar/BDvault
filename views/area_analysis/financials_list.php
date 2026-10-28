<?php $pageTitle = 'Financial Statement'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php
$cr  = fn($v) => $v === null ? '—' : '₹' . number_format(((float)$v) / 1e7, 2) . ' Cr';
$pct = fn($v) => $v === null ? '—' : number_format((float)$v, 2) . '%';
$fmt = fn($v, $d = 2) => $v === null ? '—' : number_format((float)$v, $d);
$totalStatements = 0;
$totalFinancials = 0;
foreach ($byProposal as $g) {
    foreach ($g['items'] as $s) {
        $totalStatements++;
        if ($s['fin_id']) $totalFinancials++;
    }
}
?>

<style>
/* Workflow crumb (shared) */
.wf-crumb {
    display: flex; align-items: center; gap: 8px; margin-bottom: 14px;
    padding: 12px 18px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
    font-size: 0.82rem; flex-wrap: wrap;
}
.wf-step { display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 8px; text-decoration: none; color: #64748b; }
.wf-step .num {
    display: inline-flex; align-items: center; justify-content: center;
    width: 22px; height: 22px; border-radius: 50%; background: #e2e8f0; color: #64748b;
    font-weight: 800; font-size: 0.7rem; font-family: 'JetBrains Mono', Consolas, monospace;
}
.wf-step:hover { background: #f8fafc; color: #059669; }
.wf-step.done { color: #059669; }
.wf-step.done .num { background: #d1fae5; color: #065f46; }
.wf-step.now { background: #ecfdf5; color: #065f46; font-weight: 700; }
.wf-step.now .num { background: #059669; color: #fff; }
.wf-arrow { color: #cbd5e1; }
:root {
    --fl-ink: #0f172a; --fl-muted: #64748b; --fl-border: #e2e8f0;
    --fl-surface: #f8fafc; --fl-primary: #4f46e5; --fl-success: #059669;
    --fl-warning: #d97706; --fl-danger: #dc2626;
}
.fl-hero {
    background: linear-gradient(135deg, #065f46, #059669); color: #fff;
    border-radius: 14px; padding: 22px 26px; margin-bottom: 20px;
    display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;
}
.fl-hero h4 { font-weight: 800; margin: 0 0 4px; letter-spacing: -0.02em; }
.fl-hero p  { margin: 0; color: #a7f3d0; font-size: 0.85rem; }
.fl-hero .stats { display: flex; gap: 18px; }
.fl-hero .stat { text-align: right; }
.fl-hero .stat .k { font-size: 0.68rem; color: #a7f3d0; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
.fl-hero .stat .v { font-family: 'JetBrains Mono', Consolas, monospace; font-size: 1.6rem; font-weight: 800; margin-top: 4px; }

.fl-card { background: #fff; border: 1px solid var(--fl-border); border-radius: 14px; padding: 22px 26px; margin-bottom: 20px; }
.fl-card h5 { font-weight: 700; margin: 0 0 12px; font-size: 1.02rem; color: var(--fl-ink); }

.prop-block { margin-bottom: 22px; }
.prop-title {
    font-size: 0.75rem; color: var(--fl-muted); margin-bottom: 8px;
    letter-spacing: 0.06em; text-transform: uppercase; font-weight: 700;
    display: flex; justify-content: space-between; align-items: center;
}

.fs-row {
    display: grid; grid-template-columns: 2.2fr 90px 1fr 1fr 1fr 1fr auto; gap: 14px;
    align-items: center; padding: 14px 16px; border: 1px solid var(--fl-border);
    border-radius: 10px; margin-bottom: 8px; background: #fff;
}
.fs-row:hover { border-color: var(--fl-primary); box-shadow: 0 4px 12px rgba(15,23,42,0.05); }
.fs-row.no-fin { background: #fafbfd; opacity: 0.85; }
.fs-name { font-weight: 700; color: var(--fl-ink); font-size: 0.92rem; }
.fs-meta { color: var(--fl-muted); font-size: 0.75rem; margin-top: 3px; }

.pill { padding: 3px 12px; border-radius: 999px; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.02em; }
.pill.ver { background: #eef2ff; color: #3730a3; }
.pill.st-locked     { background: #d1fae5; color: #065f46; }
.pill.st-calculated { background: #dbeafe; color: #1e40af; }
.pill.st-validated  { background: #dcfce7; color: #166534; }
.pill.st-superseded { background: #f1f5f9; color: #64748b; }
.pill.st-draft      { background: #fef3c7; color: #92400e; }

.metric { text-align: right; font-family: 'JetBrains Mono', Consolas, monospace; }
.metric .k { color: var(--fl-muted); font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; font-family: 'Lexend', sans-serif; }
.metric .v { font-weight: 800; font-size: 0.95rem; margin-top: 2px; }
.metric.pos .v { color: var(--fl-success); }
.metric.neg .v { color: var(--fl-danger); }

.btn-mini { padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; text-decoration: none;
            font-weight: 700; border: 1px solid transparent; display: inline-flex; align-items: center; gap: 4px; }
.btn-mini.view { background: #fff; color: var(--fl-primary); border-color: #c7d2fe; }
.btn-mini.view:hover { background: #eef2ff; }
.btn-mini.setup { background: var(--fl-warning); color: #fff; }
.btn-mini.setup:hover { background: #b45309; }
.btn-mini.locked { background: var(--fl-success); color: #fff; }
.btn-mini.locked:hover { background: #047857; }

.empty { color: #94a3b8; text-align: center; padding: 40px 0; font-size: 0.9rem; }

.needs-fin {
    display: inline-block; background: #fef3c7; color: #92400e;
    padding: 2px 10px; border-radius: 999px; font-size: 0.65rem; font-weight: 700;
    letter-spacing: 0.02em; text-transform: uppercase; margin-top: 4px;
}
</style>

<div class="wf-crumb">
    <a href="index.php?page=area_analysis" class="wf-step done"><span class="num">1</span>Area Analysis <i class="bi bi-check-circle-fill"></i></a>
    <span class="wf-arrow"><i class="bi bi-arrow-right"></i></span>
    <span class="wf-step now"><span class="num">2</span>Financial Analysis</span>
    <span class="wf-arrow"><i class="bi bi-arrow-right"></i></span>
    <a href="index.php?page=area_analysis_best_outcome" class="wf-step"><span class="num">3</span>Best Outcome</a>
    <span style="flex:1;"></span>
    <a href="index.php?page=area_analysis_best_outcome" style="color:#059669;font-weight:700;font-size:0.78rem;text-decoration:none;">
        Next: See the GO/NO-GO verdict →
    </a>
</div>

<div class="fl-hero">
    <div>
        <h4><i class="bi bi-cash-stack me-2"></i>Financial Statement</h4>
        <p>Every Area Statement's financial model — revenue, cost, IRR, profitability — in one landing.</p>
    </div>
    <div class="stats">
        <div class="stat"><div class="k">Statements</div><div class="v"><?= $totalStatements ?></div></div>
        <div class="stat"><div class="k">With Financials</div><div class="v"><?= $totalFinancials ?></div></div>
    </div>
</div>

<div class="fl-card">
    <h5>Statements &amp; financial models</h5>
    <?php if (empty($byProposal)): ?>
        <div class="empty">No calculated Area Statements yet. <a href="index.php?page=area_analysis">Start one →</a></div>
    <?php else: ?>
        <?php foreach ($byProposal as $pid => $group): ?>
            <div class="prop-block">
                <div class="prop-title">
                    <span><i class="bi bi-building me-1"></i><?= e($group['title']) ?></span>
                    <span><?= count($group['items']) ?> statement<?= count($group['items']) !== 1 ? 's' : '' ?></span>
                </div>

                <?php foreach ($group['items'] as $s):
                    $hasFin = (bool)$s['fin_id'];
                    $np = $s['np_after_int'];
                ?>
                    <div class="fs-row <?= $hasFin ? '' : 'no-fin' ?>">
                        <div>
                            <div class="fs-name">
                                <?= e($s['statement_name']) ?>
                                <?php if (!$hasFin): ?><span class="needs-fin">Financials not set</span><?php endif; ?>
                            </div>
                            <div class="fs-meta">
                                <?php if ($s['selected_regulation_key']): ?>DCPR <?= e($s['selected_regulation_key']) ?> · <?php endif; ?>
                                Total BUA <?= $fmt($s['total_permissible_bua_sqm'], 0) ?> sqm · FSI <?= $fmt($s['total_permissible_fsi'], 3) ?>
                                <?php if ($hasFin): ?> · Fin updated <?= e(date('d M Y', strtotime($s['fin_updated']))) ?><?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <span class="pill ver">v<?= (int)$s['version_major'] ?>.<?= (int)$s['version_minor'] ?></span>
                            <span class="pill st-<?= e($s['status']) ?>"><?= e(ucfirst($s['status'])) ?></span>
                        </div>
                        <div class="metric <?= ($np !== null && $np >= 0) ? 'pos' : ($np !== null && $np < 0 ? 'neg' : '') ?>">
                            <div class="k">NP after int</div>
                            <div class="v"><?= $cr($np) ?></div>
                        </div>
                        <div class="metric <?= ($s['margin_pct'] !== null && $s['margin_pct'] >= 15) ? 'pos' : (($s['margin_pct'] !== null && $s['margin_pct'] < 0) ? 'neg' : '') ?>">
                            <div class="k">Margin</div>
                            <div class="v"><?= $pct($s['margin_pct']) ?></div>
                        </div>
                        <div class="metric <?= ($s['irr_pct'] !== null && $s['irr_pct'] >= 20) ? 'pos' : (($s['irr_pct'] !== null && $s['irr_pct'] < 0) ? 'neg' : '') ?>">
                            <div class="k">IRR</div>
                            <div class="v"><?= $pct($s['irr_pct']) ?></div>
                        </div>
                        <div class="metric">
                            <div class="k">Revenue</div>
                            <div class="v" style="color:#0f172a;"><?= $cr($s['revenue_inr']) ?></div>
                        </div>
                        <div>
                            <?php if ($hasFin): ?>
                                <a href="index.php?page=area_analysis_financials_view&statement_id=<?= (int)$s['id'] ?>" class="btn-mini locked">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            <?php else: ?>
                                <a href="index.php?page=area_analysis_financials_form&statement_id=<?= (int)$s['id'] ?>" class="btn-mini setup">
                                    <i class="bi bi-plus-circle"></i> Set up
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
