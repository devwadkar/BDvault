<?php $pageTitle = 'Area Analysis v2 · Regulation'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/_shell.php'; ?>
<?php
// Comes from controller: $statement, $proposal, $draft, $s1, $suggestion, $selected, $stages, $currentStage
$confClass = $suggestion['confidence'] >= 85 ? 'high' : ($suggestion['confidence'] >= 60 ? 'med' : 'low');
$regMeta = RegulationSuggester::REGULATIONS;
$isSuggested = ($selected === $suggestion['regulation_key']);
// Split why-lines by sentence boundary for a checklist look
$splitWhy = function(string $txt): array {
    $parts = preg_split('/(?<=\.)\s+/', trim($txt));
    return array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));
};
$primaryWhy = $splitWhy($suggestion['rationale']);

// Build the inputs-summary pills for "what drove this recommendation"
$facts = [];
if ($statement['development_type']) {
    $facts[] = ['label' => 'Development', 'value' => ucfirst(str_replace('_',' ', $statement['development_type']))];
}
if ($statement['planning_authority']) {
    $facts[] = ['label' => 'Authority', 'value' => $statement['planning_authority']];
}
if (!empty($s1['property_card_area_sqm'])) {
    $facts[] = ['label' => 'Plot', 'value' => number_format((float)$s1['property_card_area_sqm'], 0) . ' sqm'];
}
if (!empty($s1['primary_road_width_m'])) {
    $facts[] = ['label' => 'Road', 'value' => number_format((float)$s1['primary_road_width_m'], 1) . ' m'];
}
if (!empty($s1['approx_tenant_count'])) {
    $facts[] = ['label' => 'Tenants', 'value' => (int)$s1['approx_tenant_count']];
}
if (!empty($s1['is_island_city'])) {
    $facts[] = ['label' => 'Area', 'value' => 'Island City'];
}
?>

<style>
/* ─── Primary recommendation card ─── */
.reco-primary {
    background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
    border: 1px solid #a5b4fc; border-radius: 14px;
    padding: 26px 28px; margin-bottom: 18px; position: relative;
}
.reco-primary .eyebrow {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 800;
    color: #4338ca;
}
.reco-primary .title { font-size: 1.65rem; font-weight: 800; color: #1e1b4b; letter-spacing: -0.02em; margin: 4px 0 2px; }
.reco-primary .subtitle { color: #4338ca; font-size: 0.9rem; font-weight: 500; }
.reco-primary .head {
    display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;
    margin-bottom: 18px;
}

.reco-conf {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 999px; font-size: 0.75rem; font-weight: 800;
    letter-spacing: 0.03em; white-space: nowrap;
}
.reco-conf.high { background: #d1fae5; color: #065f46; }
.reco-conf.med  { background: #fef3c7; color: #92400e; }
.reco-conf.low  { background: #fee2e2; color: #991b1b; }
.reco-conf .meter {
    width: 36px; height: 6px; border-radius: 3px; background: rgba(0,0,0,0.1); overflow: hidden;
}
.reco-conf .meter .bar { height: 100%; background: currentColor; }

.reco-status {
    display: inline-block; margin-top: 6px;
    padding: 3px 10px; border-radius: 6px;
    font-size: 0.68rem; font-weight: 700; letter-spacing: 0.03em;
}
.reco-status.ok      { background: #dcfce7; color: #166534; }
.reco-status.pending { background: #fef3c7; color: #92400e; }

/* Why checklist */
.reco-why {
    background: rgba(255,255,255,0.75); border: 1px solid #c7d2fe;
    border-radius: 10px; padding: 14px 18px;
}
.reco-why-head {
    font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.08em; color: #6366f1;
    font-weight: 800; margin-bottom: 10px;
}
.reco-why ul { list-style: none; padding: 0; margin: 0; }
.reco-why li {
    position: relative; padding: 5px 0 5px 24px; color: #1e1b4b; font-size: 0.86rem; line-height: 1.5;
}
.reco-why li::before {
    content: '✓'; position: absolute; left: 0; top: 6px;
    width: 16px; height: 16px; border-radius: 50%;
    background: #a7f3d0; color: #065f46; display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.68rem; font-weight: 800;
}

/* Inputs summary pill row */
.reco-inputs {
    display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px;
    padding: 10px 12px; background: rgba(255,255,255,0.5); border-radius: 8px;
    font-size: 0.78rem; color: #4338ca;
}
.reco-inputs .label { color: #6366f1; font-weight: 600; font-size: 0.72rem; margin-right: 10px; }
.reco-inputs .pill {
    background: #fff; border: 1px solid #c7d2fe; padding: 3px 10px; border-radius: 999px;
    font-size: 0.75rem; color: #312e81; font-weight: 600;
}
.reco-inputs .pill .k { color: #6366f1; margin-right: 4px; font-weight: 500; }

/* Accept-this + buttons */
.reco-accept-row {
    display: flex; align-items: center; gap: 10px; margin-top: 16px; flex-wrap: wrap;
}
.reco-accept {
    background: var(--v2-primary); color: #fff;
    padding: 11px 22px; border-radius: 10px; font-weight: 700; font-size: 0.9rem;
    border: 0; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
}
.reco-accept:hover { background: var(--v2-primary-d); }
.reco-accept.accepted { background: var(--v2-success); cursor: default; }
.reco-accept.accepted:hover { background: var(--v2-success); }

/* Alternates */
.alts-head {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 10px; padding: 0 2px;
}
.alts-head .t { font-weight: 700; color: var(--v2-ink); font-size: 0.92rem; }
.alts-head .sub { color: var(--v2-muted); font-size: 0.76rem; }

.alt-card {
    background: #fff; border: 1px solid var(--v2-border); border-radius: 10px;
    padding: 14px 18px; margin-bottom: 8px; display: grid;
    grid-template-columns: 1fr auto; gap: 14px; align-items: center;
    transition: border-color .12s, box-shadow .12s;
}
.alt-card:hover { border-color: #a5b4fc; box-shadow: 0 4px 14px rgba(79,70,229,0.08); }
.alt-card.selected { border-color: var(--v2-primary); background: #eef2ff; }
.alt-card .reg {
    font-weight: 800; color: var(--v2-ink); font-size: 1rem; letter-spacing: -0.01em;
    display: flex; align-items: center; gap: 8px;
}
.alt-card .reg .name { color: var(--v2-muted); font-weight: 500; font-size: 0.82rem; }
.alt-card .why { color: var(--v2-muted); font-size: 0.78rem; margin-top: 4px; line-height: 1.5; }
.alt-card .conf-chip {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--v2-surface); border: 1px solid var(--v2-border);
    padding: 4px 10px; border-radius: 999px; font-size: 0.72rem;
    color: var(--v2-muted); font-weight: 700; font-family: 'JetBrains Mono', Consolas, monospace;
}
.alt-card .st-chip {
    display: inline-block; padding: 2px 8px; border-radius: 6px;
    font-size: 0.65rem; font-weight: 700; letter-spacing: 0.03em; margin-left: 6px;
}
.alt-card .st-chip.ok      { background: #dcfce7; color: #166534; }
.alt-card .st-chip.pending { background: #fef3c7; color: #92400e; }
.alt-card .actions { display: flex; align-items: center; gap: 6px; }
.alt-card .pick-btn {
    background: #fff; color: var(--v2-primary); border: 1px solid var(--v2-primary);
    padding: 7px 14px; border-radius: 8px; font-size: 0.78rem; font-weight: 700; cursor: pointer;
    white-space: nowrap;
}
.alt-card .pick-btn:hover { background: var(--v2-primary); color: #fff; }
.alt-card .pick-btn:disabled { opacity: 0.4; cursor: not-allowed; background: #fff; color: var(--v2-muted); border-color: var(--v2-border); }

.alts-toggle {
    display: inline-flex; align-items: center; gap: 6px;
    background: transparent; border: 1px dashed var(--v2-border); color: var(--v2-muted);
    padding: 8px 14px; border-radius: 8px; font-size: 0.78rem; font-weight: 600; cursor: pointer;
    margin-top: 6px;
}
.alts-toggle:hover { border-color: var(--v2-primary); color: var(--v2-primary); border-style: solid; }
.alts-body { display: none; margin-top: 12px; }
.alts-body.open { display: block; }

/* Manual picker (fallback) */
.manual-picker {
    background: var(--v2-surface); border: 1px dashed var(--v2-border); border-radius: 10px;
    padding: 14px 18px; margin-top: 14px;
    display: grid; grid-template-columns: 1fr auto; gap: 14px; align-items: center;
}
.manual-picker label { font-size: 0.78rem; color: var(--v2-muted); font-weight: 600; margin: 0 0 6px; display: block; }
.manual-picker select { width: 100%; padding: 8px 10px; border: 1px solid var(--v2-border); border-radius: 8px;
                        font-size: 0.85rem; background: #fff; }

/* Pending-regulation nudge */
.pending-nudge {
    background: #fef3c7; border: 1px solid #fcd34d; color: #92400e;
    border-radius: 10px; padding: 12px 16px; margin-top: 12px; font-size: 0.82rem;
    display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;
}
.pending-nudge a { color: #92400e; font-weight: 700; text-decoration: underline; }
</style>

<form method="POST" action="index.php?page=area_analysis_v2_save_stage2" id="s2Form">
    <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
    <input type="hidden" name="selected_regulation_key" id="selRegKey" value="<?= e($selected) ?>">

    <!-- ─── Primary recommendation card ─── -->
    <div class="reco-primary">
        <div class="head">
            <div>
                <div class="eyebrow"><i class="bi bi-stars"></i>Suggested regulation</div>
                <div class="title">DCPR <?= e($suggestion['regulation_key']) ?></div>
                <div class="subtitle"><?= e($suggestion['title']) ?></div>
                <?php if (($suggestion['status'] ?? '') === 'calculator'): ?>
                    <span class="reco-status ok">✓ Calculator ready</span>
                <?php else: ?>
                    <span class="reco-status pending">⏳ Review-only — calculator pending</span>
                <?php endif; ?>
            </div>
            <div class="reco-conf <?= $confClass ?>">
                <?= (int)$suggestion['confidence'] ?>% confidence
                <div class="meter"><div class="bar" style="width: <?= (int)$suggestion['confidence'] ?>%;"></div></div>
            </div>
        </div>

        <div class="reco-why">
            <div class="reco-why-head">Why this fits your project</div>
            <ul>
                <?php foreach ($primaryWhy as $line): ?>
                    <li><?= e($line) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if (!empty($facts)): ?>
        <div class="reco-inputs">
            <span class="label">BASED ON</span>
            <?php foreach ($facts as $f): ?>
                <span class="pill"><span class="k"><?= e($f['label']) ?>:</span><?= e((string)$f['value']) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="reco-accept-row">
            <?php if (($suggestion['status'] ?? '') === 'calculator'): ?>
                <?php if ($isSuggested && $statement['selected_regulation_key']): ?>
                    <button type="submit" class="reco-accept accepted" disabled>
                        <i class="bi bi-check-circle-fill"></i> Selected · DCPR <?= e($selected) ?>
                    </button>
                    <span style="color:#4338ca;font-size:0.8rem;">You can change this below.</span>
                <?php else: ?>
                    <button type="submit" class="reco-accept" onclick="setKey('<?= e($suggestion['regulation_key']) ?>')">
                        <i class="bi bi-check-circle"></i> Accept &amp; continue
                    </button>
                    <span style="color:#4338ca;font-size:0.8rem;">Or pick a different regulation below.</span>
                <?php endif; ?>
            <?php else: ?>
                <div class="pending-nudge" style="margin:0;flex:1;">
                    <span>
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        This regulation's calculator isn't ready yet. Pick one of the <b>Calculator-ready</b> alternatives below, or use the classic wizard for review-only use.
                    </span>
                    <a href="index.php?page=area_analysis_step&statement_id=<?= (int)$statement['id'] ?>&step=4">Open classic wizard →</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ─── Alternatives ─── -->
    <div class="v2-card">
        <div class="alts-head">
            <div>
                <div class="t"><i class="bi bi-list-stars me-1"></i>Other regulations we scored</div>
                <div class="sub">Ranked by confidence. Click <b>Pick this</b> to swap the recommendation.</div>
            </div>
        </div>

        <?php if (empty($suggestion['alternates'])): ?>
            <div style="padding:20px;text-align:center;color:var(--v2-faint);font-size:0.85rem;">
                No alternatives scored. The primary recommendation is the only match for your inputs.
            </div>
        <?php else: ?>
            <?php
            // Show top 2 by default, rest behind "show more"
            $top = array_slice($suggestion['alternates'], 0, 2);
            $rest = array_slice($suggestion['alternates'], 2);
            $renderAlt = function($alt) use ($selected, $regMeta) {
                $isOk = (($regMeta[$alt['key']]['status'] ?? '') === 'calculator');
                $sel  = ($selected === $alt['key']);
                $whyLines = preg_split('/(?<=\.)\s+/', trim($alt['why']));
                ?>
                <div class="alt-card <?= $sel ? 'selected' : '' ?>">
                    <div>
                        <div class="reg">
                            DCPR <?= e($alt['key']) ?>
                            <span class="name"><?= e($alt['title']) ?></span>
                            <?php if ($isOk): ?>
                                <span class="st-chip ok">✓ Ready</span>
                            <?php else: ?>
                                <span class="st-chip pending">⏳ Pending</span>
                            <?php endif; ?>
                        </div>
                        <div class="why">
                            <?= e(implode(' ', (array)$whyLines)) ?>
                        </div>
                    </div>
                    <div class="actions">
                        <span class="conf-chip"><?= (int)$alt['confidence'] ?>%</span>
                        <?php if ($isOk): ?>
                            <button type="button" class="pick-btn" onclick="pickAlt('<?= e($alt['key']) ?>')">
                                <?= $sel ? 'Selected ✓' : 'Pick this' ?>
                            </button>
                        <?php else: ?>
                            <button type="button" class="pick-btn" disabled title="No calculator yet">Pending</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            };
            foreach ($top as $alt) $renderAlt($alt);
            if (!empty($rest)): ?>
                <button type="button" class="alts-toggle" onclick="toggleMore(this)">
                    <i class="bi bi-chevron-down"></i>
                    Show <?= count($rest) ?> more
                </button>
                <div class="alts-body">
                    <?php foreach ($rest as $alt) $renderAlt($alt); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Manual picker fallback -->
        <div class="manual-picker" style="margin-top:14px;">
            <div>
                <label>Still don't see the one you need?</label>
                <select id="manualPick">
                    <option value="">Pick manually…</option>
                    <?php foreach ($regMeta as $k => $m): ?>
                        <option value="<?= e($k) ?>" <?= $selected === $k ? 'selected' : '' ?>>
                            DCPR <?= e($k) ?> — <?= e($m['title']) ?>
                            <?= $m['status'] === 'calculator' ? '· ✓ Ready' : '· ⏳ Pending' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="pick-btn" onclick="pickManual()">Pick this</button>
        </div>
    </div>

    <!-- ─── Nav bar ─── -->
    <div class="v2-nav">
        <div class="saved">
            <?php if ($statement['selected_regulation_key']): ?>
                <i class="bi bi-check-circle-fill" style="color:var(--v2-success);"></i>
                Currently selected: <b>DCPR <?= e($statement['selected_regulation_key']) ?></b>
            <?php else: ?>
                <i class="bi bi-dash-circle" style="color:var(--v2-faint);"></i>
                No regulation chosen yet
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="index.php?page=area_analysis_v2_stage1&statement_id=<?= (int)$statement['id'] ?>" class="v2-btn ghost">
                <i class="bi bi-arrow-left"></i> Back to basics
            </a>
            <button type="submit" class="v2-btn primary">
                Continue with DCPR <span id="selDisplay"><?= e($selected) ?></span>
                <i class="bi bi-arrow-right"></i>
            </button>
        </div>
    </div>
</form>

<?php _v2_shell_close($statement, $s1, $draft['_meta']['stage1_saved_at'] ?? null); ?>

<script>
function setKey(k) {
    document.getElementById('selRegKey').value = k;
    document.getElementById('selDisplay').textContent = k;
}
function pickAlt(k) {
    setKey(k);
    // Visually highlight the newly-picked card
    document.querySelectorAll('.alt-card').forEach(c => c.classList.remove('selected'));
    event.target.closest('.alt-card').classList.add('selected');
    event.target.textContent = 'Selected ✓';
    // Submit immediately so the next stage sees the pick
    document.getElementById('s2Form').submit();
}
function pickManual() {
    const sel = document.getElementById('manualPick');
    if (!sel.value) { alert('Pick a regulation first.'); return; }
    setKey(sel.value);
    document.getElementById('s2Form').submit();
}
function toggleMore(btn) {
    const body = btn.nextElementSibling;
    const open = body.classList.toggle('open');
    btn.innerHTML = open
        ? '<i class="bi bi-chevron-up"></i> Show less'
        : '<i class="bi bi-chevron-down"></i> Show ' + body.children.length + ' more';
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
