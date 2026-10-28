<?php $pageTitle = 'Area Analysis'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php
// Flatten every statement to a single list, sort by most-recently-touched.
$allStatements = [];
foreach ($byProposal as $pid => $group) {
    foreach ($group['items'] as $s) {
        $s['proposal_title'] = $group['title'];
        $s['proposal_id']    = $pid;
        $s['sort_ts'] = strtotime($s['locked_at'] ?: ($s['calculated_at'] ?: $s['updated_at']));
        $allStatements[] = $s;
    }
}
usort($allStatements, fn($a, $b) => $b['sort_ts'] <=> $a['sort_ts']);

// Split active (open drafts / calculated) vs archived (locked / superseded)
$activeStatements = array_values(array_filter($allStatements, fn($s) => !in_array($s['status'], ['locked','superseded'], true)));
$doneStatements   = array_values(array_filter($allStatements, fn($s) =>  in_array($s['status'], ['locked','superseded'], true)));

// Recent = top 5 by touched-at, mixed
$recent = array_slice($allStatements, 0, 5);

$totalLegacy = 0;
foreach (($legacyPlansByProposal ?? []) as $arr) $totalLegacy += count($arr);
?>

<style>
/* ─── Workflow breadcrumb (shared across steps 1/2/3) ─── */
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
.wf-step:hover { background: #f8fafc; color: #4f46e5; }
.wf-step.now { background: #eef2ff; color: #3730a3; font-weight: 700; }
.wf-step.now .num { background: #4f46e5; color: #fff; }
.wf-arrow { color: #cbd5e1; }
:root {
    --aa-ink: #0f172a; --aa-muted: #64748b; --aa-faint: #94a3b8;
    --aa-border: #e2e8f0; --aa-surface: #f8fafc;
    --aa-primary: #4f46e5; --aa-primary-d: #3730a3;
    --aa-success: #059669; --aa-success-l: #d1fae5;
    --aa-warning: #d97706; --aa-danger: #dc2626;
}

/* ─── Top bar (title + search + new) ─────────────────────────── */
.aa-bar {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    padding: 16px 20px; margin-bottom: 16px; background: #fff;
    border: 1px solid var(--aa-border); border-radius: 12px;
}
.aa-bar h4 { margin: 0; font-weight: 700; color: var(--aa-ink); font-size: 1.1rem; letter-spacing: -0.01em; }
.aa-bar h4 .count { color: var(--aa-faint); font-weight: 500; font-size: 0.82rem; margin-left: 6px; }
.aa-search { flex: 1; min-width: 200px; max-width: 380px; position: relative; }
.aa-search input {
    width: 100%; padding: 8px 12px 8px 34px; border: 1px solid var(--aa-border);
    border-radius: 8px; font-size: 0.86rem; background: var(--aa-surface);
}
.aa-search input:focus { outline: none; border-color: var(--aa-primary); background: #fff; box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
.aa-search .icon { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--aa-faint); pointer-events: none; }
.aa-new-wrap { position: relative; }
.aa-new-btn {
    background: var(--aa-primary); color: #fff; padding: 8px 16px; border-radius: 8px;
    font-size: 0.85rem; font-weight: 700; border: 0; cursor: pointer;
    display: inline-flex; align-items: center; gap: 6px;
}
.aa-new-btn:hover { background: var(--aa-primary-d); }
.aa-new-pop {
    position: absolute; top: calc(100% + 6px); right: 0; z-index: 100;
    width: 340px; max-height: 420px; overflow-y: auto;
    background: #fff; border: 1px solid var(--aa-border); border-radius: 10px;
    box-shadow: 0 12px 32px rgba(15,23,42,0.15); display: none;
}
.aa-new-pop.open { display: block; }
.aa-new-pop .hd { padding: 10px 14px; border-bottom: 1px solid var(--aa-border);
                  color: var(--aa-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
.aa-new-pop .item {
    padding: 10px 14px; border-bottom: 1px solid #f1f5f9; cursor: pointer;
    display: flex; justify-content: space-between; align-items: center; gap: 8px;
    text-decoration: none; color: var(--aa-ink);
}
.aa-new-pop .item:last-child { border-bottom: 0; }
.aa-new-pop .item:hover { background: var(--aa-surface); }
.aa-new-pop .item .n { font-weight: 600; font-size: 0.85rem; }
.aa-new-pop .item .m { color: var(--aa-muted); font-size: 0.72rem; margin-top: 2px; }
.aa-new-pop .item i { color: var(--aa-primary); font-size: 1rem; }

/* ─── Card + row layout ─────────────────────────────────────── */
.aa-card { background: #fff; border: 1px solid var(--aa-border); border-radius: 12px; padding: 4px 0; margin-bottom: 16px; overflow: hidden; }
.aa-card-hd {
    padding: 12px 20px 8px; display: flex; justify-content: space-between; align-items: center;
    color: var(--aa-muted); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700;
}
.aa-card-hd .toggle { color: var(--aa-primary); cursor: pointer; text-transform: none; letter-spacing: 0; font-weight: 600; font-size: 0.75rem; text-decoration: none; }

.aa-row {
    display: grid; grid-template-columns: 1fr auto auto auto;
    gap: 14px; align-items: center; padding: 10px 20px;
    border-top: 1px solid #f1f5f9; text-decoration: none; color: inherit;
    transition: background 0.1s;
}
.aa-row:first-of-type { border-top: 0; }
.aa-row:hover { background: var(--aa-surface); color: inherit; text-decoration: none; }
.aa-row .name { color: var(--aa-ink); font-weight: 600; font-size: 0.9rem; }
.aa-row .name .prop { color: var(--aa-muted); font-weight: 500; }
.aa-row .meta { color: var(--aa-muted); font-size: 0.72rem; margin-top: 2px; }
.aa-row .badges { display: flex; gap: 5px; align-items: center; }
.aa-row .when { color: var(--aa-faint); font-size: 0.72rem; font-family: 'JetBrains Mono', Consolas, monospace; text-align: right; }
.aa-row .actions { display: flex; gap: 4px; opacity: 0; transition: opacity 0.12s; }
.aa-row:hover .actions { opacity: 1; }

.pill { padding: 2px 8px; border-radius: 999px; font-size: 0.65rem; font-weight: 700; letter-spacing: 0.02em; }
.pill.ver { background: #eef2ff; color: var(--aa-primary-d); }
.pill.st-draft      { background: #fef3c7; color: #92400e; }
.pill.st-calculated { background: #dbeafe; color: #1e40af; }
.pill.st-validated  { background: var(--aa-success-l); color: var(--aa-success); }
.pill.st-locked     { background: var(--aa-success-l); color: var(--aa-success); }
.pill.st-superseded { background: #f1f5f9; color: var(--aa-muted); }

.btn-i {
    background: transparent; border: 1px solid var(--aa-border); color: var(--aa-muted);
    width: 28px; height: 28px; border-radius: 6px; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.82rem; text-decoration: none;
}
.btn-i:hover { border-color: var(--aa-primary); color: var(--aa-primary); background: var(--aa-surface); }
.btn-i.danger:hover { border-color: var(--aa-danger); color: var(--aa-danger); background: #fee2e2; }

.aa-empty { padding: 32px; text-align: center; color: var(--aa-faint); font-size: 0.88rem; }

.aa-footer-link {
    display: block; text-align: center; padding: 12px; color: var(--aa-muted);
    text-decoration: none; font-size: 0.78rem; border: 1px dashed var(--aa-border);
    border-radius: 8px; margin-top: 12px;
}
.aa-footer-link:hover { border-color: var(--aa-primary); color: var(--aa-primary); background: var(--aa-surface); }

/* Hidden rows for JS filter + toggle */
.aa-row.hidden { display: none !important; }
.aa-card.collapsed .aa-row:not(.aa-row-keep) { display: none; }
</style>

<!-- v2 opt-in banner -->
<div style="background:linear-gradient(135deg,#eef2ff,#e0e7ff);border:1px solid #a5b4fc;color:#312e81;padding:12px 18px;border-radius:12px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;font-size:0.84rem;">
    <span>
        <i class="bi bi-stars me-1"></i>
        <b>Try the new simplified Area Analysis</b> — guided 5-stage flow, regulation-specific forms, less clutter.
        <span style="color:#4338ca;font-size:0.75rem;">Stage 1 live · Stages 2-5 rolling out.</span>
    </span>
    <a href="index.php?page=area_analysis_v2" style="background:#4f46e5;color:#fff;padding:7px 16px;border-radius:8px;font-weight:700;text-decoration:none;font-size:0.8rem;white-space:nowrap;">
        Try v2 preview <i class="bi bi-arrow-right"></i>
    </a>
</div>

<!-- Workflow breadcrumb -->
<div class="wf-crumb">
    <span class="wf-step now"><span class="num">1</span>Area Analysis</span>
    <span class="wf-arrow"><i class="bi bi-arrow-right"></i></span>
    <a href="index.php?page=area_analysis_financials_list" class="wf-step"><span class="num">2</span>Financial Analysis</a>
    <span class="wf-arrow"><i class="bi bi-arrow-right"></i></span>
    <a href="index.php?page=area_analysis_best_outcome" class="wf-step"><span class="num">3</span>Best Outcome</a>
    <span style="flex:1;"></span>
    <span style="color:#94a3b8;font-size:0.72rem;">Pick a proposal → area statement → financial model → decide.</span>
</div>

<!-- Top bar -->
<div class="aa-bar">
    <h4><i class="bi bi-diagram-3-fill me-1" style="color: var(--aa-primary);"></i>Area Analysis
        <span class="count"><?= count($allStatements) ?> statement<?= count($allStatements) !== 1 ? 's' : '' ?></span>
    </h4>
    <div class="aa-search">
        <i class="bi bi-search icon"></i>
        <input type="text" id="aaSearch" placeholder="Search by proposal or statement name…" oninput="aaFilter(this.value)">
    </div>
    <div class="aa-new-wrap">
        <button class="aa-new-btn" onclick="aaToggleNew(event)">
            <i class="bi bi-plus-lg"></i> New statement
        </button>
        <div class="aa-new-pop" id="aaNewPop" onclick="event.stopPropagation()">
            <div class="hd">Pick an accepted proposal</div>
            <?php if (empty($acceptedProposals)): ?>
                <div class="aa-empty" style="padding: 20px;">No accepted proposals yet.</div>
            <?php else: foreach ($acceptedProposals as $p): ?>
                <a class="item" href="index.php?page=area_analysis_dashboard&proposal_id=<?= (int)$p['id'] ?>">
                    <div>
                        <div class="n"><?= e($p['proposal_name']) ?></div>
                        <div class="m">
                            <?= e($p['proposal_type'] ?? '') ?><?php if ($p['region']): ?> · <?= e($p['region']) ?><?php endif; ?><?php if ($p['plot_area_sqm']): ?> · <?= number_format((float)$p['plot_area_sqm'], 0) ?> sqm<?php endif; ?>
                        </div>
                    </div>
                    <i class="bi bi-arrow-right"></i>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<?php if (empty($allStatements)): ?>
    <div class="aa-card">
        <div class="aa-empty">
            <i class="bi bi-diagram-3" style="font-size: 2rem; color: #cbd5e1;"></i>
            <div class="mt-2">No Area Statements yet.</div>
            <div style="font-size: 0.78rem; margin-top: 4px;">Click <b>New statement</b> above to start one from an accepted proposal.</div>
        </div>
    </div>
<?php else: ?>

<!-- Recent (top 5, mixed) -->
<div class="aa-card">
    <div class="aa-card-hd">
        <span>Recent activity</span>
        <span style="text-transform:none;letter-spacing:0;font-weight:500;color:var(--aa-faint);">last <?= count($recent) ?> touched</span>
    </div>
    <?php foreach ($recent as $s): ?>
        <?php $stepUrl = "index.php?page=area_analysis_step&statement_id={$s['id']}&step=" . max(1, (int)$s['current_step']); ?>
        <a class="aa-row aa-searchable" data-search="<?= e(mb_strtolower($s['proposal_title'] . ' ' . $s['statement_name'])) ?>" href="<?= $s['status'] === 'locked' ? "index.php?page=area_analysis_report&statement_id={$s['id']}" : $stepUrl ?>">
            <div>
                <div class="name"><?= e($s['statement_name']) ?></div>
                <div class="meta">
                    <?= e($s['proposal_title']) ?>
                    <?php if ($s['selected_regulation_key']): ?> · DCPR <?= e($s['selected_regulation_key']) ?><?php endif; ?>
                    <?php if ($s['status'] !== 'locked'): ?> · Step <?= (int)$s['current_step'] ?>/6<?php endif; ?>
                </div>
            </div>
            <div class="badges">
                <span class="pill ver">v<?= (int)$s['version_major'] ?>.<?= (int)$s['version_minor'] ?></span>
                <span class="pill st-<?= e($s['status']) ?>"><?= e(ucfirst($s['status'])) ?></span>
            </div>
            <div class="when"><?= e(date('d M', $s['sort_ts'])) ?></div>
            <div class="actions" onclick="event.stopPropagation();">
                <?php if (in_array($s['status'], ['locked','calculated','validated','superseded'], true)): ?>
                    <a href="index.php?page=area_analysis_pdf_report&statement_id=<?= (int)$s['id'] ?>" class="btn-i" title="20-Section PDF" onclick="event.stopPropagation()"><i class="bi bi-file-earmark-pdf"></i></a>
                <?php endif; ?>
                <?php if ($s['status'] !== 'locked'): ?>
                    <form method="POST" action="index.php?page=area_analysis_delete" style="display:inline;" onclick="event.stopPropagation()" onsubmit="event.stopPropagation();return confirm('Delete this draft? All data will be lost.');">
                        <input type="hidden" name="statement_id" value="<?= (int)$s['id'] ?>">
                        <button type="submit" class="btn-i danger" title="Delete draft"><i class="bi bi-trash"></i></button>
                    </form>
                <?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<!-- Active (drafts / calculated) -->
<?php if (!empty($activeStatements) && count($activeStatements) > count(array_filter($recent, fn($s) => !in_array($s['status'], ['locked','superseded'], true)))): ?>
<div class="aa-card">
    <div class="aa-card-hd">
        <span>Active drafts &amp; in-progress</span>
        <span style="text-transform:none;letter-spacing:0;font-weight:500;color:var(--aa-faint);"><?= count($activeStatements) ?> total</span>
    </div>
    <?php foreach ($activeStatements as $s): ?>
        <?php $stepUrl = "index.php?page=area_analysis_step&statement_id={$s['id']}&step=" . max(1, (int)$s['current_step']); ?>
        <a class="aa-row aa-searchable" data-search="<?= e(mb_strtolower($s['proposal_title'] . ' ' . $s['statement_name'])) ?>" href="<?= $stepUrl ?>">
            <div>
                <div class="name"><?= e($s['statement_name']) ?> <span class="prop">· <?= e($s['proposal_title']) ?></span></div>
                <div class="meta">
                    <?php if ($s['selected_regulation_key']): ?>DCPR <?= e($s['selected_regulation_key']) ?> · <?php endif; ?>
                    Step <?= (int)$s['current_step'] ?>/6
                </div>
            </div>
            <div class="badges">
                <span class="pill ver">v<?= (int)$s['version_major'] ?>.<?= (int)$s['version_minor'] ?></span>
                <span class="pill st-<?= e($s['status']) ?>"><?= e(ucfirst($s['status'])) ?></span>
            </div>
            <div class="when"><?= e(date('d M', $s['sort_ts'])) ?></div>
            <div class="actions" onclick="event.stopPropagation();">
                <form method="POST" action="index.php?page=area_analysis_delete" style="display:inline;" onclick="event.stopPropagation()" onsubmit="event.stopPropagation();return confirm('Delete this draft?');">
                    <input type="hidden" name="statement_id" value="<?= (int)$s['id'] ?>">
                    <button type="submit" class="btn-i danger" title="Delete"><i class="bi bi-trash"></i></button>
                </form>
            </div>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Locked / superseded — collapsed by default -->
<?php if (!empty($doneStatements)): ?>
<div class="aa-card collapsed" id="doneCard">
    <div class="aa-card-hd">
        <span>Locked &amp; archived · <?= count($doneStatements) ?></span>
        <a class="toggle" href="#" onclick="event.preventDefault();document.getElementById('doneCard').classList.toggle('collapsed');this.textContent = document.getElementById('doneCard').classList.contains('collapsed') ? 'Show all →' : '← Collapse'">Show all →</a>
    </div>
    <?php foreach ($doneStatements as $i => $s):
        // First 3 always visible if any hits appear via search; hide the rest until toggle
        $keep = $i < 0; // never keep any pre-toggle; but marking searchable ensures search works
    ?>
        <a class="aa-row aa-searchable <?= $keep ? 'aa-row-keep' : '' ?>" data-search="<?= e(mb_strtolower($s['proposal_title'] . ' ' . $s['statement_name'])) ?>" href="index.php?page=area_analysis_report&statement_id=<?= (int)$s['id'] ?>">
            <div>
                <div class="name"><?= e($s['statement_name']) ?> <span class="prop">· <?= e($s['proposal_title']) ?></span></div>
                <div class="meta">
                    <?php if ($s['selected_regulation_key']): ?>DCPR <?= e($s['selected_regulation_key']) ?> · <?php endif; ?>
                    <?= $s['locked_at'] ? 'Locked ' . e(date('d M Y', strtotime($s['locked_at']))) : 'Calc ' . e(date('d M Y', strtotime($s['calculated_at'] ?: $s['updated_at']))) ?>
                </div>
            </div>
            <div class="badges">
                <span class="pill ver">v<?= (int)$s['version_major'] ?>.<?= (int)$s['version_minor'] ?></span>
                <span class="pill st-<?= e($s['status']) ?>"><?= e(ucfirst($s['status'])) ?></span>
            </div>
            <div class="when"><?= e(date('d M', $s['sort_ts'])) ?></div>
            <div class="actions" onclick="event.stopPropagation();">
                <a href="index.php?page=area_analysis_pdf_report&statement_id=<?= (int)$s['id'] ?>" class="btn-i" title="20-Section PDF" onclick="event.stopPropagation()"><i class="bi bi-file-earmark-pdf"></i></a>
                <a href="index.php?page=area_analysis_financials_view&statement_id=<?= (int)$s['id'] ?>" class="btn-i" title="Financials" onclick="event.stopPropagation()"><i class="bi bi-cash-stack"></i></a>
            </div>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($totalLegacy > 0): ?>
    <a class="aa-footer-link" href="index.php?page=planning">
        <i class="bi bi-archive me-1"></i>
        <?= $totalLegacy ?> legacy plan<?= $totalLegacy !== 1 ? 's' : '' ?> — view in Planning (legacy)
    </a>
<?php endif; ?>

<?php endif; ?>

<script>
function aaToggleNew(e) {
    e?.stopPropagation();
    document.getElementById('aaNewPop').classList.toggle('open');
}
document.addEventListener('click', () => document.getElementById('aaNewPop')?.classList.remove('open'));

function aaFilter(q) {
    q = q.trim().toLowerCase();
    const rows = document.querySelectorAll('.aa-searchable');
    // Expand the collapsed archived card while a search is active so results show
    const doneCard = document.getElementById('doneCard');
    if (doneCard) doneCard.classList.toggle('collapsed', !q);

    let anyHit = false;
    rows.forEach(r => {
        if (!q) { r.classList.remove('hidden'); return; }
        const hay = r.dataset.search || '';
        const hit = hay.includes(q);
        r.classList.toggle('hidden', !hit);
        if (hit) anyHit = true;
    });
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
