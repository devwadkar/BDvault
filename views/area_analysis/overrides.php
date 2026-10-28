<?php $pageTitle = 'Overrides · ' . e($statement['statement_name']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php
require_once __DIR__ . '/../../lib/CalcOverrideManager.php';
$fmt = fn($v, $d = 2) => $v === null ? '—' : number_format((float)$v, $d);
$active   = array_filter($allOverrides, fn($o) => (int)$o['active'] === 1);
$inactive = array_filter($allOverrides, fn($o) => (int)$o['active'] === 0);
$foundational = CalcOverrideManager::FOUNDATIONAL_KEYS;

// Build a calc_key → row map for the picker
$calcMap = [];
foreach ($calcs as $c) $calcMap[$c['calc_key']] = $c;
ksort($calcMap);
?>

<style>
:root {
    --ov-ink: #0f172a; --ov-muted: #64748b; --ov-border: #e2e8f0;
    --ov-primary: #4f46e5; --ov-warn: #d97706;
}
.ov-hero {
    display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;
    padding: 22px 26px; margin-bottom: 20px; background: #fff;
    border: 1px solid var(--ov-border); border-radius: 14px;
}
.ov-hero h4 { margin: 0; font-weight: 700; color: var(--ov-ink); letter-spacing: -0.01em; }
.ov-hero .sub { color: var(--ov-muted); font-size: 0.85rem; margin-top: 4px; }
.ov-back { padding: 7px 14px; border-radius: 8px; background: #f1f5f9; color: #475569;
           font-size: 0.78rem; font-weight: 700; text-decoration: none; }

.ov-card {
    background: #fff; border: 1px solid var(--ov-border); border-radius: 14px;
    margin-bottom: 20px; overflow: hidden;
}
.ov-card-hd {
    padding: 16px 22px; border-bottom: 1px solid var(--ov-border);
    display: flex; justify-content: space-between; align-items: center; background: #f8fafc;
}
.ov-card-hd h5 { margin: 0; font-weight: 700; color: var(--ov-ink); font-size: 1rem; letter-spacing: -0.01em; }
.ov-card-hd .count { color: var(--ov-muted); font-size: 0.78rem; }

.ov-row {
    padding: 18px 22px; border-bottom: 1px solid #f1f5f9; display: grid;
    grid-template-columns: 220px 130px 130px 1fr 200px; gap: 16px; align-items: center;
}
.ov-row:last-child { border-bottom: 0; }
.ov-row .k { color: var(--ov-primary); font-family: 'JetBrains Mono', Consolas, monospace; font-weight: 700; font-size: 0.82rem; }
.ov-row .k .label { display: block; color: var(--ov-ink); font-family: 'Lexend', sans-serif; font-size: 0.72rem; margin-top: 4px; font-weight: 500; }
.ov-row .v { font-family: 'JetBrains Mono', Consolas, monospace; font-weight: 700; text-align: right; font-size: 1rem; }
.ov-row .v-old { color: var(--ov-muted); text-decoration: line-through; font-size: 0.9rem; }
.ov-row .v-new { color: #d97706; }
.ov-row .reason { color: var(--ov-ink); font-size: 0.86rem; font-style: italic; }
.ov-row .reason .meta { color: var(--ov-muted); font-size: 0.72rem; font-style: normal; margin-top: 4px; }
.ov-row .actions { display: flex; gap: 6px; justify-content: flex-end; }
.chip { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
.chip-warn  { background: #fef3c7; color: #92400e; }
.chip-info  { background: #dbeafe; color: #1e40af; }
.chip-off   { background: #f1f5f9; color: #64748b; }

.btn-x { padding: 6px 12px; border-radius: 6px; font-size: 0.72rem; font-weight: 700; text-decoration: none; border: 0; cursor: pointer; }
.btn-x-deact { background: #f1f5f9; color: #475569; }
.btn-x-deact:hover { background: #fee2e2; color: #991b1b; }

.ov-apply-form { padding: 22px 24px; background: #fffbeb; }
.ov-apply-form label { color: #78350f; font-weight: 700; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px; display: block; }
.ov-apply-form select, .ov-apply-form input {
    width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 8px;
    font-family: inherit; font-size: 0.88rem;
}
.ov-apply-form input[type=number] { font-family: 'JetBrains Mono', Consolas, monospace; }
.ov-apply-form .row-3 { display: grid; grid-template-columns: 2fr 1fr 3fr auto; gap: 14px; align-items: end; }
.ov-apply-form .btn-apply { background: var(--ov-warn); color: #fff; border: 0; padding: 10px 20px; border-radius: 8px; font-weight: 700; font-size: 0.85rem; cursor: pointer; }
.ov-apply-form .baseline-hint { color: #92400e; font-size: 0.7rem; margin-top: 3px; font-family: 'JetBrains Mono', Consolas, monospace; }

.ov-audit { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.ov-audit th, .ov-audit td { padding: 10px 14px; border-bottom: 1px solid var(--ov-border); text-align: left; }
.ov-audit thead th { background: #f8fafc; color: var(--ov-muted); font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
.ov-audit .num { font-family: 'JetBrains Mono', Consolas, monospace; text-align: right; font-weight: 600; }
.ov-audit .action-applied      { color: #059669; font-weight: 700; }
.ov-audit .action-edited       { color: #d97706; font-weight: 700; }
.ov-audit .action-deactivated  { color: #64748b; font-weight: 700; }
.ov-audit .action-reactivated  { color: #059669; font-weight: 700; }

.ov-empty { padding: 40px; text-align: center; color: var(--ov-muted); }
</style>

<div class="ov-hero">
    <div>
        <h4><i class="bi bi-pencil-square me-2" style="color: var(--ov-warn);"></i>Manual Overrides</h4>
        <div class="sub"><?= e($statement['statement_name']) ?>
            <?php if ($proposal['proposal_name'] ?? null): ?> · <?= e($proposal['proposal_name']) ?><?php endif; ?>
        </div>
    </div>
    <a href="index.php?page=area_analysis_feasibility&statement_id=<?= (int)$statement['id'] ?>" class="ov-back">
        <i class="bi bi-arrow-left me-1"></i>Back to Feasibility
    </a>
</div>

<?php if ($flash = flashGet()): ?>
    <div class="alert alert-<?= e($flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'danger' : 'info')) ?>">
        <?= $flash['message'] ?>
    </div>
<?php endif; ?>

<!-- Apply new override -->
<div class="ov-card">
    <div class="ov-card-hd">
        <h5><i class="bi bi-plus-square me-2"></i>Apply new override</h5>
        <span class="count">Requires a reason for the audit trail</span>
    </div>
    <form method="POST" action="index.php?page=area_analysis_apply_override" class="ov-apply-form">
        <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
        <div class="row-3">
            <div>
                <label>Calc row to override</label>
                <select name="calc_key" id="calcKeySelect" required onchange="showBaseline(this)">
                    <option value="">Pick a row…</option>
                    <?php
                    // Order by foundational first, then alphabetical
                    $sortedKeys = array_keys($calcMap);
                    usort($sortedKeys, function($a, $b) use ($foundational) {
                        $af = in_array($a, $foundational, true);
                        $bf = in_array($b, $foundational, true);
                        return $af === $bf ? strcmp($a, $b) : ($af ? -1 : 1);
                    });
                    foreach ($sortedKeys as $k):
                        $c = $calcMap[$k];
                        $isF = in_array($k, $foundational, true);
                    ?>
                        <option value="<?= e($k) ?>"
                                data-current="<?= (float)$c['calc_value'] ?>"
                                data-unit="<?= e($c['calc_unit'] ?? '') ?>"
                                data-label="<?= e($c['calc_label']) ?>">
                            <?= $isF ? '★ ' : '' ?><?= e($c['calc_label']) ?> — <?= e($k) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="baseline-hint" id="baselineHint">★ = foundational (downstream may be affected)</div>
            </div>
            <div>
                <label>New value</label>
                <input type="number" step="any" name="override_value" required>
            </div>
            <div>
                <label>Reason (mandatory for audit)</label>
                <input type="text" name="reason" required
                       placeholder="e.g. Approved by SRA on 15-Apr-2026 per letter SRA/EE/2026/1234">
            </div>
            <div>
                <button type="submit" class="btn-apply">
                    <i class="bi bi-check2 me-1"></i>Apply
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Active overrides -->
<div class="ov-card">
    <div class="ov-card-hd">
        <h5>Active overrides</h5>
        <span class="count"><?= count($active) ?></span>
    </div>
    <?php if (empty($active)): ?>
        <div class="ov-empty">No active overrides — every value below is exactly what the calculator emitted.</div>
    <?php else: foreach ($active as $ov):
        $isFoundational = in_array($ov['calc_key'], $foundational, true);
        $delta = ((float)$ov['override_value']) - ((float)($ov['original_value'] ?? 0));
    ?>
        <div class="ov-row">
            <div class="k">
                <?= $isFoundational ? '★ ' : '' ?><?= e($ov['calc_key']) ?>
                <?php if ($isFoundational): ?>
                    <span class="chip chip-warn" style="margin-left:4px;">FOUNDATIONAL</span>
                <?php endif; ?>
                <span class="label"><?= e($calcMap[$ov['calc_key']]['calc_label'] ?? '') ?></span>
            </div>
            <div class="v v-old"><?= $fmt($ov['original_value']) ?></div>
            <div class="v v-new"><?= $fmt($ov['override_value']) ?><br><span style="color:var(--ov-muted);font-weight:400;font-size:0.7rem;font-family:'Lexend',sans-serif;">Δ <?= ($delta >= 0 ? '+' : '') . $fmt($delta) ?></span></div>
            <div class="reason">
                <?= e($ov['reason']) ?>
                <div class="meta">By user #<?= (int)$ov['created_by'] ?> · <?= e($ov['created_at']) ?></div>
            </div>
            <div class="actions">
                <form method="POST" action="index.php?page=area_analysis_deactivate_override" onsubmit="return confirm('Restore original value?')">
                    <input type="hidden" name="override_id" value="<?= (int)$ov['id'] ?>">
                    <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
                    <button type="submit" class="btn-x btn-x-deact">
                        <i class="bi bi-x-circle me-1"></i>Deactivate
                    </button>
                </form>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>

<!-- Historical (inactive) overrides -->
<?php if (!empty($inactive)): ?>
<div class="ov-card">
    <div class="ov-card-hd">
        <h5>Deactivated overrides (history)</h5>
        <span class="count"><?= count($inactive) ?></span>
    </div>
    <?php foreach ($inactive as $ov): ?>
        <div class="ov-row" style="opacity: 0.5;">
            <div class="k"><?= e($ov['calc_key']) ?>
                <span class="chip chip-off">INACTIVE</span>
                <span class="label"><?= e($calcMap[$ov['calc_key']]['calc_label'] ?? '') ?></span>
            </div>
            <div class="v v-old"><?= $fmt($ov['original_value']) ?></div>
            <div class="v"><?= $fmt($ov['override_value']) ?></div>
            <div class="reason">
                <?= e($ov['reason']) ?>
                <div class="meta"><?= e($ov['created_at']) ?> → <?= e($ov['updated_at']) ?></div>
            </div>
            <div class="actions"></div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Full audit trail -->
<div class="ov-card">
    <div class="ov-card-hd">
        <h5><i class="bi bi-journal-text me-2"></i>Audit trail</h5>
        <span class="count"><?= count($audit) ?> events</span>
    </div>
    <?php if (empty($audit)): ?>
        <div class="ov-empty">No override actions recorded yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="ov-audit">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Action</th>
                        <th>Calc key</th>
                        <th class="num">Old</th>
                        <th class="num">New</th>
                        <th>Reason</th>
                        <th>By user</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($audit as $a): ?>
                        <tr>
                            <td><?= e($a['changed_at']) ?></td>
                            <td class="action-<?= e($a['action']) ?>"><?= strtoupper(e($a['action'])) ?></td>
                            <td style="font-family:'JetBrains Mono',monospace;font-size:0.8rem;"><?= e($a['calc_key']) ?></td>
                            <td class="num"><?= $fmt($a['old_value']) ?></td>
                            <td class="num"><?= $fmt($a['new_value']) ?></td>
                            <td style="font-size:0.85rem;color:#475569;"><?= e($a['reason'] ?? '—') ?></td>
                            <td>#<?= (int)$a['changed_by'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function showBaseline(sel) {
    const opt = sel.options[sel.selectedIndex];
    const cur = opt.dataset.current, unit = opt.dataset.unit;
    if (cur) {
        document.getElementById('baselineHint').innerHTML =
            'Current value: <strong>' + Number(cur).toLocaleString('en-IN', {maximumFractionDigits: 4}) + '</strong> ' + (unit || '');
    }
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
