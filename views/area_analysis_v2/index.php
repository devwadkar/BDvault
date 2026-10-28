<?php $pageTitle = 'Area Analysis v2 · Preview'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<style>
/* Mirrors v1 landing style tokens but scoped to v2 */
:root {
    --v2-ink: #0f172a; --v2-muted: #64748b; --v2-faint: #94a3b8;
    --v2-border: #e2e8f0; --v2-surface: #f8fafc;
    --v2-primary: #4f46e5; --v2-primary-d: #3730a3;
    --v2-success: #059669; --v2-success-l: #d1fae5;
}
.v2-hero {
    background: linear-gradient(135deg, #4f46e5, #6366f1); color: #fff;
    border-radius: 14px; padding: 24px 28px; margin-bottom: 18px;
    display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap;
}
.v2-hero h4 { font-weight: 800; margin: 0 0 4px; letter-spacing: -0.02em; }
.v2-hero p  { margin: 0; color: #c7d2fe; font-size: 0.86rem; max-width: 580px; }
.v2-hero .cta {
    background: #fff; color: var(--v2-primary); padding: 10px 20px; border-radius: 10px;
    font-weight: 700; font-size: 0.88rem; text-decoration: none; cursor: pointer; border: 0;
    display: inline-flex; align-items: center; gap: 6px;
}
.v2-hero .cta:hover { background: #e0e7ff; color: var(--v2-primary); }
.v2-hero .link { color: #c7d2fe; font-size: 0.82rem; text-decoration: underline; }
.v2-hero .link:hover { color: #fff; }

.v2-card { background: #fff; border: 1px solid var(--v2-border); border-radius: 12px; padding: 18px 22px; margin-bottom: 14px; }
.v2-card h5 { font-weight: 700; color: var(--v2-ink); font-size: 0.95rem; margin: 0 0 12px; }

.v2-pop { position: fixed; inset: 0; background: rgba(15,23,42,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
.v2-pop.open { display: flex; }
.v2-pop-box { background: #fff; border-radius: 14px; width: 90%; max-width: 520px; max-height: 80vh; overflow-y: auto;
              box-shadow: 0 20px 60px rgba(15,23,42,0.3); }
.v2-pop-hd { padding: 16px 22px; border-bottom: 1px solid var(--v2-border);
             display: flex; justify-content: space-between; align-items: center; }
.v2-pop-hd h5 { margin: 0; font-weight: 700; color: var(--v2-ink); }
.v2-pop-hd .x { background: transparent; border: 0; color: var(--v2-muted); font-size: 1.3rem; cursor: pointer; }
.v2-pop-body { padding: 10px 22px 20px; }
.v2-pop-body .item {
    display: flex; justify-content: space-between; align-items: center; gap: 10px;
    padding: 12px 14px; margin: 6px 0; border: 1px solid var(--v2-border); border-radius: 10px;
    text-decoration: none; color: var(--v2-ink); cursor: pointer;
}
.v2-pop-body .item:hover { background: var(--v2-surface); border-color: var(--v2-primary); }
.v2-pop-body .item .n { font-weight: 700; font-size: 0.9rem; }
.v2-pop-body .item .m { color: var(--v2-muted); font-size: 0.74rem; margin-top: 2px; }

.v2-row {
    display: grid; grid-template-columns: 1fr auto auto;
    gap: 14px; align-items: center; padding: 12px 16px;
    border: 1px solid var(--v2-border); border-radius: 10px; margin-bottom: 8px;
    text-decoration: none; color: inherit; background: #fff; transition: border-color .1s;
}
.v2-row:hover { border-color: var(--v2-primary); background: var(--v2-surface); color: inherit; text-decoration: none; }
.v2-row .n   { font-weight: 700; color: var(--v2-ink); font-size: 0.9rem; }
.v2-row .m   { color: var(--v2-muted); font-size: 0.74rem; margin-top: 2px; }
.v2-row .pill { padding: 2px 10px; border-radius: 999px; font-size: 0.65rem; font-weight: 700; letter-spacing: 0.02em; }
.v2-row .pill.ver { background: #eef2ff; color: var(--v2-primary-d); }
.v2-row .pill.draft { background: #fef3c7; color: #92400e; }
.v2-row .pill.calculated { background: #dbeafe; color: #1e40af; }
.v2-row .pill.locked { background: var(--v2-success-l); color: var(--v2-success); }
.v2-row .when { color: var(--v2-faint); font-size: 0.72rem; font-family: 'JetBrains Mono', Consolas, monospace; text-align: right; }

.v2-empty { padding: 36px; text-align: center; color: var(--v2-faint); font-size: 0.88rem; }
.v2-empty i { font-size: 2rem; color: #cbd5e1; display: block; margin-bottom: 8px; }
</style>

<!-- Workflow breadcrumb (consistent with v1) -->
<div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;padding:12px 18px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;font-size:0.82rem;flex-wrap:wrap;">
    <span style="display:flex;align-items:center;gap:8px;padding:5px 10px;border-radius:8px;background:#eef2ff;color:#3730a3;font-weight:700;">
        <span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:#4f46e5;color:#fff;font-weight:800;font-size:0.7rem;font-family:'JetBrains Mono',Consolas,monospace;">1</span>Area Analysis
    </span>
    <span style="color:#cbd5e1;"><i class="bi bi-arrow-right"></i></span>
    <a href="index.php?page=area_analysis_financials_list" style="display:flex;align-items:center;gap:8px;padding:5px 10px;border-radius:8px;color:#64748b;text-decoration:none;">
        <span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:#e2e8f0;color:#64748b;font-weight:800;font-size:0.7rem;font-family:'JetBrains Mono',Consolas,monospace;">2</span>Financial Analysis
    </a>
    <span style="color:#cbd5e1;"><i class="bi bi-arrow-right"></i></span>
    <a href="index.php?page=area_analysis_best_outcome" style="display:flex;align-items:center;gap:8px;padding:5px 10px;border-radius:8px;color:#64748b;text-decoration:none;">
        <span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:#e2e8f0;color:#64748b;font-weight:800;font-size:0.7rem;font-family:'JetBrains Mono',Consolas,monospace;">3</span>Best Outcome
    </a>
    <span style="flex:1;"></span>
    <a href="index.php?page=area_analysis" style="color:#64748b;font-size:0.76rem;text-decoration:none;">
        Switch to classic wizard →
    </a>
</div>

<?php $flash = flashGet(); if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> py-2 px-3 mb-3" style="font-size:0.84rem;border-radius:8px;">
        <?= e($flash['msg']) ?>
    </div>
<?php endif; ?>

<div class="v2-hero">
    <div>
        <h4><i class="bi bi-stars me-2"></i>Area Analysis — Preview</h4>
        <p>A simplified, guided workflow. Capture the basics, we recommend the right DCPR regulation,
            then you only answer the questions that regulation needs. Stage 1 is live now — Stages 2-5 ship as they're verified.</p>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
        <button class="cta" onclick="openStart()">
            <i class="bi bi-plus-lg"></i> Start new analysis
        </button>
        <a href="index.php?page=area_analysis" class="link">or use the classic wizard</a>
    </div>
</div>

<div class="v2-card">
    <h5>Your v2 drafts</h5>
    <?php if (empty($v2Statements)): ?>
        <div class="v2-empty">
            <i class="bi bi-folder2-open"></i>
            No v2 drafts yet.
            <div style="font-size:0.78rem;margin-top:6px;">Click <b>Start new analysis</b> to begin — pick an accepted proposal and we'll pre-fill what we can.</div>
        </div>
    <?php else: ?>
        <?php foreach ($v2Statements as $s): ?>
            <a class="v2-row" href="index.php?page=area_analysis_v2_stage1&statement_id=<?= (int)$s['id'] ?>">
                <div>
                    <div class="n"><?= e($s['proposal_name']) ?></div>
                    <div class="m"><?= e($s['statement_name']) ?></div>
                </div>
                <div style="display:flex;gap:6px;align-items:center;">
                    <span class="pill ver">v<?= (int)$s['version_major'] ?>.<?= (int)$s['version_minor'] ?></span>
                    <span class="pill <?= e($s['status']) ?>"><?= e(ucfirst($s['status'])) ?></span>
                </div>
                <div class="when"><?= e(date('d M', strtotime($s['updated_at']))) ?></div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- "Pick a proposal" modal -->
<div class="v2-pop" id="startPop" onclick="if(event.target===this)closeStart()">
    <div class="v2-pop-box">
        <div class="v2-pop-hd">
            <h5><i class="bi bi-building me-1"></i>Pick an accepted proposal</h5>
            <button class="x" onclick="closeStart()">&times;</button>
        </div>
        <div class="v2-pop-body">
            <?php if (empty($acceptedProposals)): ?>
                <div class="v2-empty">No accepted proposals yet. Mark a proposal as Accepted first.</div>
            <?php else: foreach ($acceptedProposals as $p): ?>
                <form method="POST" action="index.php?page=area_analysis_v2_start" style="margin:0;">
                    <input type="hidden" name="proposal_id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="item" style="width:100%;background:transparent;text-align:left;">
                        <div>
                            <div class="n"><?= e($p['proposal_name']) ?></div>
                            <div class="m">
                                <?= e($p['proposal_type'] ?? '') ?>
                                <?php if ($p['region']): ?> · <?= e($p['region']) ?><?php endif; ?>
                                <?php if ($p['plot_area_sqm']): ?> · <?= number_format((float)$p['plot_area_sqm'], 0) ?> sqm<?php endif; ?>
                            </div>
                        </div>
                        <i class="bi bi-arrow-right" style="color:var(--v2-primary);"></i>
                    </button>
                </form>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<script>
function openStart() { document.getElementById('startPop').classList.add('open'); }
function closeStart() { document.getElementById('startPop').classList.remove('open'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeStart(); });
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
