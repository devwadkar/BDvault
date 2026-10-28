<?php require __DIR__ . '/_wizard_shell.php'; ?>
<?php
$sug = $extra['suggestion'] ?? ['regulation_key'=>'—','confidence'=>0,'rationale'=>'','alternates'=>[],'status'=>'pending','title'=>'—'];
$selected = $statement['selected_regulation_key'] ?: $sug['regulation_key'];
$confClass = $sug['confidence'] >= 85 ? 'high' : ($sug['confidence'] >= 60 ? 'med' : 'low');
?>
<?php _aa_shell_open(); ?>

<style>
    .sug-card {
        background: linear-gradient(135deg, #eef2ff, #e0e7ff);
        border: 1px solid #a5b4fc; border-radius: 14px;
        padding: 26px 30px; margin-bottom: 20px;
    }
    .sug-head { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; }
    .sug-eyebrow { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em; color: #6366f1; font-weight: 700; }
    .sug-title { font-weight: 800; color: #312e81; font-size: 1.4rem; letter-spacing: -0.02em; margin-top: 2px; }
    .sug-subtitle { color: #4338ca; font-size: 0.85rem; }
    .conf-pill { padding: 5px 14px; border-radius: 999px; font-size: 0.72rem; font-weight: 800; letter-spacing: 0.03em; }
    .conf-pill.high { background: #d1fae5; color: #065f46; }
    .conf-pill.med  { background: #fef3c7; color: #92400e; }
    .conf-pill.low  { background: #fee2e2; color: #991b1b; }
    .status-pill { padding: 3px 10px; border-radius: 999px; font-size: 0.65rem; font-weight: 700; letter-spacing: 0.03em; margin-top: 6px; display: inline-block; }
    .status-pill.calculator { background: #dcfce7; color: #166534; }
    .status-pill.pending    { background: #fef3c7; color: #92400e; }
    .rationale-box {
        background: rgba(255,255,255,0.7); border: 1px solid #c7d2fe;
        border-radius: 10px; padding: 14px 18px; margin-top: 16px;
        color: #1e1b4b; font-size: 0.88rem; line-height: 1.6;
    }
    .accept-btn {
        background: var(--aa-primary); color: #fff; padding: 12px 24px; border-radius: 10px;
        font-weight: 700; font-size: 0.9rem; border: 0; cursor: pointer; margin-top: 18px;
    }
    .accept-btn:hover { background: var(--aa-primary-d); }
    .accept-btn.accepted { background: var(--aa-success); cursor: default; }

    .alt-toggle { background: transparent; border: 0; color: var(--aa-primary); font-weight: 600; font-size: 0.85rem; cursor: pointer; padding: 8px 0; }
    .alt-toggle:hover { text-decoration: underline; }
    .alt-panel { display: none; margin-top: 12px; }
    .alt-panel.open { display: block; }
    .alt-row {
        display: grid; grid-template-columns: auto 1fr auto; gap: 14px; align-items: center;
        padding: 12px 16px; border: 1px solid var(--aa-border); border-radius: 10px;
        background: #fff; margin-bottom: 8px; cursor: pointer;
    }
    .alt-row:hover { border-color: var(--aa-primary); }
    .alt-row input[type=radio] { transform: scale(1.2); accent-color: var(--aa-primary); }
    .alt-row .a-title { font-weight: 700; color: var(--aa-ink); font-size: 0.9rem; }
    .alt-row .a-why { color: var(--aa-muted); font-size: 0.78rem; margin-top: 3px; }
    .alt-row .a-conf { font-family: 'JetBrains Mono', Consolas, monospace; font-size: 0.78rem; color: var(--aa-primary-d); font-weight: 700; }
</style>

<div class="aa-card">
    <h5 class="aa-card-title">Regulation</h5>
    <p class="aa-card-lead">Based on the plot &amp; scheme characteristics, we've suggested the applicable DCPR regulation. Confirm or pick a different one.</p>

    <form method="POST" action="index.php?page=area_analysis_save_step" id="s4Form">
        <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
        <input type="hidden" name="step" value="4">
        <input type="hidden" name="selected_regulation_key" id="selRegHidden" value="<?= e($selected) ?>">

        <div class="sug-card">
            <div class="sug-head">
                <div>
                    <div class="sug-eyebrow">🎯 Suggested</div>
                    <div class="sug-title">DCPR <?= e($sug['regulation_key']) ?></div>
                    <div class="sug-subtitle"><?= e($sug['title']) ?></div>
                    <span class="status-pill <?= e($sug['status']) ?>">
                        <?= $sug['status'] === 'calculator' ? '✓ Calculator ready' : '⏳ Calculator pending' ?>
                    </span>
                </div>
                <div class="conf-pill <?= $confClass ?>"><?= (int)$sug['confidence'] ?>% confidence</div>
            </div>

            <div class="rationale-box"><?= e($sug['rationale']) ?></div>

            <?php if ($selected === $sug['regulation_key']): ?>
                <button type="button" class="accept-btn accepted" disabled>
                    <i class="bi bi-check-circle-fill me-2"></i>Selected · DCPR <?= e($selected) ?>
                </button>
            <?php else: ?>
                <button type="button" class="accept-btn" onclick="selectReg('<?= e($sug['regulation_key']) ?>')">
                    <i class="bi bi-check-lg me-2"></i>Accept &amp; use DCPR <?= e($sug['regulation_key']) ?>
                </button>
            <?php endif; ?>
        </div>

        <?php if (!empty($sug['alternates'])): ?>
            <button type="button" class="alt-toggle" onclick="document.getElementById('altPanel').classList.toggle('open')">
                <i class="bi bi-list-ul me-1"></i>Show other applicable regulations (<?= count($sug['alternates']) ?>)
            </button>
            <div class="alt-panel <?= ($selected !== $sug['regulation_key']) ? 'open' : '' ?>" id="altPanel">
                <?php foreach ($sug['alternates'] as $alt): ?>
                    <label class="alt-row">
                        <input type="radio" name="_alt" value="<?= e($alt['key']) ?>"
                               <?= $selected === $alt['key'] ? 'checked' : '' ?>
                               onchange="selectReg('<?= e($alt['key']) ?>')">
                        <div>
                            <div class="a-title">DCPR <?= e($alt['key']) ?> · <?= e($alt['title']) ?>
                                <span class="status-pill <?= e($alt['status']) ?>" style="margin-left: 8px;">
                                    <?= $alt['status'] === 'calculator' ? 'Ready' : 'Pending' ?>
                                </span>
                            </div>
                            <div class="a-why"><?= e($alt['why']) ?></div>
                        </div>
                        <div class="a-conf"><?= (int)$alt['confidence'] ?>%</div>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php _aa_nav_bar($statement, $step); ?>
    </form>
</div>

<script>
function selectReg(key) {
    document.getElementById('selRegHidden').value = key;
    document.querySelectorAll('input[name="_alt"]').forEach(r => r.checked = (r.value === key));
    fetch('index.php?page=area_analysis_save_step', {
        method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            statement_id: <?= (int)$statement['id'] ?>, step: 4, direction: 'save', selected_regulation_key: key
        }).toString()
    }).then(() => location.reload());
}
</script>

<?php _aa_shell_close(); ?>
