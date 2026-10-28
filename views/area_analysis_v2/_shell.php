<?php
/**
 * Shared chrome for the v2 Area Analysis wizard — stage progress bar + project
 * summary panel + save indicator. Expects these vars in scope:
 *   $statement (row from area_statements)
 *   $proposal  (row from proposals)
 *   $draft     (decoded draft_json)
 *   $stages    (AreaAnalysisV2Controller::STAGES)
 *   $currentStage (int 1..5)
 */
if (!isset($statement, $proposal, $stages, $currentStage)) {
    throw new RuntimeException('_shell.php needs $statement, $proposal, $stages, $currentStage.');
}
$draft = $draft ?? [];
$s1    = $draft['v2_stage1'] ?? [];
$lastSaved = $draft['_meta']['stage1_saved_at'] ?? null;
?>
<style>
:root {
    --v2-ink: #0f172a; --v2-muted: #64748b; --v2-faint: #94a3b8;
    --v2-border: #e2e8f0; --v2-surface: #f8fafc;
    --v2-primary: #4f46e5; --v2-primary-d: #3730a3;
    --v2-success: #059669; --v2-success-l: #d1fae5;
    --v2-warning: #d97706; --v2-danger: #dc2626;
    --v2-amber-l: #fef3c7;
}

/* ─── Beta banner (opt-in experience notice) ─── */
.v2-beta-banner {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 1px solid #f59e0b; color: #78350f;
    padding: 10px 16px; border-radius: 10px; font-size: 0.78rem;
    margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; gap: 12px;
}
.v2-beta-banner a { color: #78350f; font-weight: 700; text-decoration: underline; white-space: nowrap; }

/* ─── Stage progress bar ─── */
.v2-progress {
    display: flex; gap: 0; background: #fff; border: 1px solid var(--v2-border);
    border-radius: 12px; padding: 10px 6px; margin-bottom: 16px; overflow: hidden;
}
.v2-step {
    flex: 1; display: flex; align-items: center; gap: 10px; padding: 10px 12px; min-width: 0;
    border-right: 1px solid #f1f5f9; text-decoration: none; color: var(--v2-muted);
    transition: background .15s;
}
.v2-step:last-child { border-right: 0; }
.v2-step .num {
    width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
    display: inline-flex; align-items: center; justify-content: center;
    background: #e2e8f0; color: var(--v2-muted); font-family: 'JetBrains Mono', Consolas, monospace;
    font-size: 0.78rem; font-weight: 800;
}
.v2-step .lab { line-height: 1.2; min-width: 0; }
.v2-step .lab .t { font-weight: 700; font-size: 0.82rem; color: var(--v2-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.v2-step .lab .b { font-size: 0.68rem; color: var(--v2-faint); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.v2-step.done .num     { background: var(--v2-success-l); color: var(--v2-success); }
.v2-step.done .lab .t  { color: var(--v2-success); }
.v2-step.now           { background: #eef2ff; }
.v2-step.now .num      { background: var(--v2-primary); color: #fff; }
.v2-step.now .lab .t   { color: var(--v2-primary-d); }
.v2-step.locked        { cursor: not-allowed; opacity: 0.55; }
.v2-step.locked:hover  { background: #fff; }

/* ─── Two-column layout: form (wide) + summary panel (narrow) ─── */
.v2-wrap { display: grid; grid-template-columns: 1fr 300px; gap: 18px; align-items: start; }
@media (max-width: 980px) { .v2-wrap { grid-template-columns: 1fr; } }

.v2-main { min-width: 0; }

/* ─── Cards + sections ─── */
.v2-card { background: #fff; border: 1px solid var(--v2-border); border-radius: 12px; padding: 22px 26px; margin-bottom: 14px; }
.v2-card h5 { font-weight: 800; color: var(--v2-ink); font-size: 1.08rem; letter-spacing: -0.01em; margin: 0 0 4px; }
.v2-card .lead { color: var(--v2-muted); font-size: 0.85rem; margin: 0 0 18px; }

.v2-section { padding-top: 18px; margin-top: 18px; border-top: 1px solid #f1f5f9; }
.v2-section:first-of-type { padding-top: 0; margin-top: 0; border-top: 0; }
.v2-section-title {
    display: flex; align-items: center; gap: 8px;
    color: var(--v2-primary-d); font-weight: 700;
    font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em;
    margin: 0 0 14px;
}
.v2-section-title .bullet {
    width: 20px; height: 20px; border-radius: 50%; background: #eef2ff;
    display: inline-flex; align-items: center; justify-content: center; color: var(--v2-primary);
    font-family: 'JetBrains Mono', Consolas, monospace; font-size: 0.72rem; font-weight: 800;
}

/* ─── Form controls ─── */
.v2-field { margin-bottom: 14px; }
.v2-field label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--v2-ink); margin-bottom: 5px; }
.v2-field label .req { color: var(--v2-danger); margin-left: 2px; }
.v2-field label .hint-icon {
    display: inline-block; width: 14px; height: 14px; border-radius: 50%;
    background: #e2e8f0; color: var(--v2-muted); font-size: 0.62rem;
    text-align: center; line-height: 14px; margin-left: 4px; cursor: help;
}
.v2-field .hint { font-size: 0.7rem; color: var(--v2-faint); margin-top: 4px; }
.v2-field .err  { font-size: 0.7rem; color: var(--v2-danger); margin-top: 4px; display: none; }
.v2-field.has-err .err { display: block; }
.v2-field.has-err input, .v2-field.has-err select, .v2-field.has-err textarea { border-color: var(--v2-danger); background: #fef2f2; }

.v2-field input, .v2-field select, .v2-field textarea {
    width: 100%; padding: 9px 12px; border: 1px solid var(--v2-border); border-radius: 8px;
    font-size: 0.88rem; font-family: inherit; background: #fff; color: var(--v2-ink);
    transition: border-color .12s, box-shadow .12s;
}
.v2-field input:focus, .v2-field select:focus, .v2-field textarea:focus {
    outline: none; border-color: var(--v2-primary); box-shadow: 0 0 0 3px rgba(79,70,229,0.08);
}
.v2-field input[type=number] { font-family: 'JetBrains Mono', Consolas, monospace; }
.v2-field textarea { min-height: 60px; resize: vertical; }

.v2-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
.v2-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.v2-grid .colspan-2, .v2-grid-3 .colspan-2 { grid-column: span 2; }
@media (max-width: 640px) { .v2-grid, .v2-grid-3 { grid-template-columns: 1fr; } }

/* ─── Toggle (yes/no) ─── */
.v2-toggle {
    display: inline-flex; gap: 0; border: 1px solid var(--v2-border); border-radius: 8px; overflow: hidden;
    background: var(--v2-surface);
}
.v2-toggle label {
    padding: 8px 16px; cursor: pointer; font-size: 0.82rem; font-weight: 600; color: var(--v2-muted);
    user-select: none; margin: 0; border-right: 1px solid var(--v2-border);
}
.v2-toggle label:last-child { border-right: 0; }
.v2-toggle input { display: none; }
.v2-toggle input:checked + label { background: var(--v2-primary); color: #fff; }

/* ─── Nav bar at form bottom ─── */
.v2-nav { display: flex; justify-content: space-between; align-items: center; gap: 10px;
          padding: 16px 0 0; margin-top: 18px; border-top: 1px solid #f1f5f9; flex-wrap: wrap; }
.v2-nav .saved { color: var(--v2-faint); font-size: 0.72rem; }
.v2-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 20px; border-radius: 8px; font-size: 0.85rem; font-weight: 700;
    text-decoration: none; cursor: pointer; border: 1px solid transparent;
}
.v2-btn.primary { background: var(--v2-primary); color: #fff; border-color: var(--v2-primary); }
.v2-btn.primary:hover { background: var(--v2-primary-d); color: #fff; }
.v2-btn.ghost { background: #fff; color: var(--v2-muted); border-color: var(--v2-border); }
.v2-btn.ghost:hover { background: var(--v2-surface); color: var(--v2-ink); }

/* ─── Summary panel ─── */
.v2-summary {
    background: #fff; border: 1px solid var(--v2-border); border-radius: 12px;
    padding: 18px 20px; position: sticky; top: 20px;
}
.v2-summary h6 { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em;
                 color: var(--v2-muted); font-weight: 700; margin: 0 0 12px; }
.v2-summary .fact { font-size: 0.78rem; padding: 8px 0; border-top: 1px solid #f1f5f9;
                    display: flex; justify-content: space-between; align-items: baseline; gap: 10px; }
.v2-summary .fact:first-of-type { border-top: 0; padding-top: 0; }
.v2-summary .fact .k { color: var(--v2-muted); flex-shrink: 0; }
.v2-summary .fact .v { color: var(--v2-ink); font-weight: 700; text-align: right;
                       font-family: 'JetBrains Mono', Consolas, monospace; font-size: 0.78rem;
                       overflow-wrap: anywhere; }
.v2-summary .fact .v.text { font-family: inherit; }
.v2-summary .fact .v.unset { color: var(--v2-faint); font-weight: 400; font-style: italic; }
.v2-summary .ver {
    display: inline-block; background: #eef2ff; color: var(--v2-primary-d);
    padding: 2px 8px; border-radius: 999px; font-size: 0.65rem; font-weight: 700;
    margin-bottom: 10px;
}
</style>

<?php
// ─── Beta banner ─────────────────────────────────────────────────────────
?>
<div class="v2-beta-banner">
    <span>
        <i class="bi bi-info-circle-fill me-1"></i>
        <b>Area Analysis v2 (preview).</b>
        You're using the new simplified workflow. The original wizard is still available if you need it.
    </span>
    <a href="index.php?page=area_analysis_step&statement_id=<?= (int)$statement['id'] ?>&step=1">Switch to old wizard →</a>
</div>

<?php
// ─── Stage progress bar ──────────────────────────────────────────────────
?>
<div class="v2-progress">
    <?php
    // All 5 stages are now implemented.
    $builtStages = [1, 2, 3, 4, 5];
    foreach ($stages as $n => $meta):
        $isDone = $n <  $currentStage;
        $isNow  = $n == $currentStage;
        $isReachable = in_array($n, $builtStages, true) && ($n <= $currentStage);
        $cls = $isNow ? 'now' : ($isDone ? 'done' : ($isReachable ? '' : 'locked'));
        $href = '';
        if ($isReachable && !$isNow) {
            $hrefMap = [
                1 => "index.php?page=area_analysis_v2_stage1&statement_id=" . (int)$statement['id'],
                2 => "index.php?page=area_analysis_v2_stage2&statement_id=" . (int)$statement['id'],
                3 => "index.php?page=area_analysis_v2_stage3&statement_id=" . (int)$statement['id'],
                4 => "index.php?page=area_analysis_v2_stage4&statement_id=" . (int)$statement['id'],
                5 => "index.php?page=area_analysis_v2_stage5&statement_id=" . (int)$statement['id'],
            ];
            $href = $hrefMap[$n] ?? '';
        }
        $tag = $href ? 'a' : 'div';
        $hrefAttr = $href ? "href=\"{$href}\"" : '';
    ?>
        <<?= $tag ?> class="v2-step <?= $cls ?>" <?= $hrefAttr ?>>
            <span class="num"><?= $n ?></span>
            <span class="lab"><div class="t"><?= e($meta['title']) ?></div><div class="b"><?= e($meta['blurb']) ?></div></span>
        </<?= $tag ?>>
    <?php endforeach; ?>
</div>

<div class="v2-wrap">
    <div class="v2-main">
        <?php $flash = flashGet(); if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> py-2 px-3 mb-3" style="font-size:0.84rem;border-radius:8px;">
                <?= e($flash['msg']) ?>
            </div>
        <?php endif; ?>
        <?php /* Main form slot — stage view renders here */ ?>
<?php
// ─── Helper called by the stage view at the very end ─────────────────────
if (!function_exists('_v2_shell_close')) {
    function _v2_shell_close(array $statement, array $s1, ?string $lastSaved): void {
?>
    </div>
    <aside>
        <div class="v2-summary">
            <span class="ver">v<?= (int)$statement['version_major'] ?>.<?= (int)$statement['version_minor'] ?> · <?= e($statement['status']) ?></span>
            <h6>Project summary</h6>
            <div class="fact"><span class="k">Project</span>
                <span class="v text<?= empty($statement['project_name']) ? ' unset' : '' ?>">
                    <?= e($statement['project_name'] ?: '—') ?>
                </span></div>
            <div class="fact"><span class="k">City · Region</span>
                <span class="v text<?= empty($statement['project_location']) ? ' unset' : '' ?>">
                    <?= e($statement['project_location'] ?: 'Not set') ?>
                </span></div>
            <div class="fact"><span class="k">CTS / Survey</span>
                <span class="v text<?= empty($statement['cts_number']) ? ' unset' : '' ?>">
                    <?= e($statement['cts_number'] ?: 'Not set') ?>
                </span></div>
            <div class="fact"><span class="k">Plot area</span>
                <span class="v<?= empty($s1['property_card_area_sqm']) ? ' unset' : '' ?>">
                    <?= !empty($s1['property_card_area_sqm'])
                        ? number_format((float)$s1['property_card_area_sqm'], 2) . ' sqm'
                        : 'Not set' ?>
                </span></div>
            <div class="fact"><span class="k">Road width</span>
                <span class="v<?= empty($s1['primary_road_width_m']) ? ' unset' : '' ?>">
                    <?= !empty($s1['primary_road_width_m'])
                        ? number_format((float)$s1['primary_road_width_m'], 2) . ' m'
                        : 'Not set' ?>
                </span></div>
            <div class="fact"><span class="k">Authority</span>
                <span class="v text<?= empty($statement['planning_authority']) ? ' unset' : '' ?>">
                    <?= e($statement['planning_authority'] ?: 'Not set') ?>
                </span></div>
            <div class="fact"><span class="k">Category</span>
                <span class="v text<?= empty($statement['development_type']) ? ' unset' : '' ?>">
                    <?= e($statement['development_type'] ? ucfirst(str_replace('_',' ',$statement['development_type'])) : 'Not set') ?>
                </span></div>
            <div class="fact"><span class="k">Regulation</span>
                <span class="v text<?= empty($statement['selected_regulation_key']) ? ' unset' : '' ?>" style="<?= !empty($statement['selected_regulation_key']) ? 'color:var(--v2-primary-d);' : '' ?>">
                    <?= !empty($statement['selected_regulation_key'])
                        ? 'DCPR ' . e($statement['selected_regulation_key'])
                        : 'Not picked' ?>
                </span></div>
            <?php if ($lastSaved): ?>
                <div class="fact" style="margin-top:8px;"><span class="k">Saved</span>
                    <span class="v text" style="color:var(--v2-success);">
                        <i class="bi bi-check-circle-fill"></i> <?= e(date('d M, H:i', strtotime($lastSaved))) ?>
                    </span></div>
            <?php endif; ?>
        </div>
    </aside>
</div>
<?php
    }
}
?>
