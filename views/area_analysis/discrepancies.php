<?php $pageTitle = 'Discrepancies · ' . e($statement['statement_name']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php
$num = fn($v, $d = 2) => $v === null ? '—' : number_format((float)$v, $d);
$sev = [
    'error'   => ['bg' => '#fee2e2', 'fg' => '#991b1b', 'label' => 'ERROR'],
    'warning' => ['bg' => '#fef3c7', 'fg' => '#92400e', 'label' => 'WARNING'],
    'info'    => ['bg' => '#dbeafe', 'fg' => '#1e40af', 'label' => 'INFO'],
];
$open = array_filter($discrepancies, fn($d) => !$d['resolved']);
$done = array_filter($discrepancies, fn($d) =>  $d['resolved']);
?>

<style>
    :root {
        --dc-ink: #0f172a; --dc-muted: #64748b; --dc-border: #e2e8f0;
        --dc-primary: #4f46e5;
    }
    .dc-hero {
        display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;
        padding: 22px 26px; margin-bottom: 20px; background: #fff;
        border: 1px solid var(--dc-border); border-radius: 14px;
    }
    .dc-hero h4 { margin: 0; font-weight: 700; color: var(--dc-ink); letter-spacing: -0.01em; }
    .dc-hero .sub { color: var(--dc-muted); font-size: 0.85rem; margin-top: 4px; }
    .dc-back { padding: 7px 14px; border-radius: 8px; background: #f1f5f9; color: #475569;
               font-size: 0.78rem; font-weight: 700; text-decoration: none; }
    .dc-rescan { background: #4f46e5; color: #fff; border: 0; padding: 7px 14px; border-radius: 8px;
                 font-size: 0.78rem; font-weight: 700; cursor: pointer; }

    .dc-summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
    .dc-summary .card {
        background: #fff; border: 1px solid var(--dc-border); border-radius: 12px;
        padding: 16px 20px;
    }
    .dc-summary .card .k { color: var(--dc-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
    .dc-summary .card .v { font-size: 1.8rem; font-weight: 800; margin-top: 4px; font-family: 'JetBrains Mono', Consolas, monospace; }

    .dc-card { background: #fff; border: 1px solid var(--dc-border); border-radius: 14px; margin-bottom: 20px; overflow: hidden; }
    .dc-card-hd { padding: 16px 24px; border-bottom: 1px solid var(--dc-border); background: #f8fafc; display: flex; justify-content: space-between; align-items: center; }
    .dc-card-hd h5 { margin: 0; font-weight: 700; color: var(--dc-ink); font-size: 1rem; letter-spacing: -0.01em; }
    .dc-card-hd .count { color: var(--dc-muted); font-size: 0.78rem; }

    .dc-row { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; }
    .dc-row:last-child { border-bottom: 0; }
    .dc-row.resolved { opacity: 0.62; }
    .dc-row-hd { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
    .dc-sev-pill { display: inline-block; padding: 3px 10px; border-radius: 999px; font-weight: 700;
                   font-size: 0.68rem; letter-spacing: 0.04em; text-transform: uppercase; }
    .dc-title { font-weight: 700; color: var(--dc-ink); font-size: 0.96rem; }
    .dc-key { color: var(--dc-muted); font-family: 'JetBrains Mono', Consolas, monospace; font-size: 0.75rem; }
    .dc-ref { margin-left: auto; color: #059669; font-family: 'JetBrains Mono', Consolas, monospace; font-size: 0.75rem; }
    .dc-desc { color: var(--dc-muted); font-size: 0.85rem; margin-bottom: 12px; }

    .dc-numbers {
        display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 16px;
        background: #fafbfd; border: 1px solid var(--dc-border); border-radius: 10px;
        padding: 12px 16px; margin-bottom: 12px;
    }
    .dc-numbers .lbl { color: var(--dc-muted); font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
    .dc-numbers .val { font-family: 'JetBrains Mono', Consolas, monospace; font-weight: 700; font-size: 1.02rem; }
    .dc-numbers .exp { color: #059669; }
    .dc-numbers .act { color: #dc2626; }
    .dc-numbers .var { color: #d97706; }
    .dc-numbers .tol { color: #64748b; font-family: 'JetBrains Mono', Consolas, monospace; font-size: 0.82rem; }

    .dc-formula {
        font-family: 'JetBrains Mono', Consolas, monospace; font-size: 0.82rem;
        background: #f1f5f9; padding: 8px 12px; border-radius: 6px; color: #334155; margin-bottom: 10px;
    }
    .dc-resolve { display: none; margin-top: 12px; padding: 14px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; }
    .dc-resolve.active { display: block; }
    .dc-resolve label { color: #92400e; font-size: 0.75rem; font-weight: 700; margin-bottom: 6px; display: block; }
    .dc-resolve input[type=text] { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; margin-bottom: 8px; }
    .dc-resolve .btn-resolve { background: #059669; color: #fff; border: 0; padding: 8px 16px; border-radius: 6px; font-weight: 700; font-size: 0.8rem; }
    .dc-toggle-btn { background: transparent; border: 1px solid var(--dc-border); color: var(--dc-primary); padding: 6px 14px; border-radius: 8px; font-size: 0.78rem; font-weight: 600; cursor: pointer; }
    .dc-toggle-btn:hover { background: #eef2ff; }

    .dc-resolved-badge { background: #d1fae5; color: #065f46; padding: 3px 10px; border-radius: 999px; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.04em; }
    .dc-resolution { margin-top: 10px; padding: 10px 14px; background: #ecfdf5; border-left: 3px solid #10b981; border-radius: 4px; font-size: 0.84rem; color: #065f46; }

    .dc-empty { padding: 60px; text-align: center; color: var(--dc-muted); }
    .dc-empty i { font-size: 3rem; color: #10b981; }
</style>

<div class="dc-hero">
    <div>
        <h4><i class="bi bi-diagram-3 me-2" style="color: var(--dc-primary);"></i>Formula Discrepancies</h4>
        <div class="sub"><?= e($statement['statement_name']) ?>
            <?php if ($proposal['proposal_name'] ?? null): ?> · <?= e($proposal['proposal_name']) ?><?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <form method="POST" action="index.php?page=area_analysis_discrepancy_rescan" style="display: inline;">
            <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
            <button type="submit" class="dc-rescan">
                <i class="bi bi-arrow-clockwise me-1"></i>Rescan
            </button>
        </form>
        <a href="index.php?page=area_analysis_feasibility&statement_id=<?= (int)$statement['id'] ?>" class="dc-back">
            <i class="bi bi-arrow-left me-1"></i>Back to Feasibility
        </a>
    </div>
</div>

<?php if ($flash = flashGet()): ?>
    <div class="alert alert-<?= e($flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'danger' : 'info')) ?>">
        <?= e($flash['message']) ?>
    </div>
<?php endif; ?>

<?php
$counts = ['error' => 0, 'warning' => 0, 'info' => 0];
foreach ($open as $o) $counts[$o['severity']]++;
?>
<div class="dc-summary">
    <div class="card">
        <div class="k">Open (unresolved)</div>
        <div class="v" style="color: <?= count($open) > 0 ? '#dc2626' : '#059669' ?>;"><?= count($open) ?></div>
    </div>
    <div class="card">
        <div class="k">Errors</div>
        <div class="v" style="color: <?= $counts['error'] > 0 ? '#dc2626' : '#94a3b8' ?>;"><?= $counts['error'] ?></div>
    </div>
    <div class="card">
        <div class="k">Warnings</div>
        <div class="v" style="color: <?= $counts['warning'] > 0 ? '#d97706' : '#94a3b8' ?>;"><?= $counts['warning'] ?></div>
    </div>
    <div class="card">
        <div class="k">Resolved</div>
        <div class="v" style="color: #059669;"><?= count($done) ?></div>
    </div>
</div>

<?php if (empty($discrepancies)): ?>
    <div class="dc-card">
        <div class="dc-empty">
            <i class="bi bi-check-circle"></i>
            <h5 class="mt-3">All formulas verified</h5>
            <p>Every cross-check identity holds within tolerance. Run the calculator again if inputs change.</p>
        </div>
    </div>
<?php else: ?>
    <?php if (!empty($open)): ?>
        <div class="dc-card">
            <div class="dc-card-hd">
                <h5>Open discrepancies</h5>
                <span class="count"><?= count($open) ?></span>
            </div>
            <?php foreach ($open as $d):
                $s = $sev[$d['severity']] ?? $sev['info'];
                $inputs = json_decode($d['inputs_json'] ?? '[]', true) ?: [];
            ?>
                <div class="dc-row">
                    <div class="dc-row-hd">
                        <span class="dc-sev-pill" style="background: <?= $s['bg'] ?>; color: <?= $s['fg'] ?>;"><?= $s['label'] ?></span>
                        <span class="dc-title"><?= e($d['check_label']) ?></span>
                        <span class="dc-key"><?= e($d['check_key']) ?></span>
                        <?php if ($d['regulation_ref']): ?>
                            <span class="dc-ref"><?= e($d['regulation_ref']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($d['description']): ?>
                        <div class="dc-desc"><?= e($d['description']) ?></div>
                    <?php endif; ?>
                    <?php if ($d['formula']): ?>
                        <div class="dc-formula"><?= e($d['formula']) ?></div>
                    <?php endif; ?>
                    <div class="dc-numbers">
                        <div>
                            <div class="lbl">Expected</div>
                            <div class="val exp"><?= $num($d['expected_value'], 2) ?></div>
                        </div>
                        <div>
                            <div class="lbl">Actual</div>
                            <div class="val act"><?= $num($d['actual_value'], 2) ?></div>
                        </div>
                        <div>
                            <div class="lbl">Δ</div>
                            <div class="val var">
                                <?= $num($d['variance_abs'], 2) ?>
                                <span class="tol">(<?= $num($d['variance_pct'], 3) ?>%)</span>
                            </div>
                        </div>
                        <div>
                            <div class="lbl">Tol</div>
                            <div class="val tol">≤ <?= $num($d['tolerance_pct'], 2) ?>%</div>
                        </div>
                    </div>
                    <button class="dc-toggle-btn" onclick="toggleResolve(<?= (int)$d['id'] ?>); return false;">Resolve…</button>

                    <form method="POST" action="index.php?page=area_analysis_discrepancy_resolve" class="dc-resolve" id="resolve-<?= (int)$d['id'] ?>">
                        <input type="hidden" name="discrepancy_id" value="<?= (int)$d['id'] ?>">
                        <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
                        <label>Resolution note (audit trail)</label>
                        <input type="text" name="resolution_notes" placeholder="e.g. Accepted — user overrode default loading factor for luxury tier">
                        <button type="submit" class="btn-resolve">Mark resolved</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($done)): ?>
        <div class="dc-card">
            <div class="dc-card-hd">
                <h5>Resolved discrepancies</h5>
                <span class="count"><?= count($done) ?></span>
            </div>
            <?php foreach ($done as $d):
                $s = $sev[$d['severity']] ?? $sev['info'];
            ?>
                <div class="dc-row resolved">
                    <div class="dc-row-hd">
                        <span class="dc-sev-pill" style="background: <?= $s['bg'] ?>; color: <?= $s['fg'] ?>;"><?= $s['label'] ?></span>
                        <span class="dc-title"><?= e($d['check_label']) ?></span>
                        <span class="dc-resolved-badge">RESOLVED</span>
                        <?php if ($d['resolved_at']): ?>
                            <span class="dc-key" style="margin-left: 12px;"><?= e($d['resolved_at']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($d['resolution_notes']): ?>
                        <div class="dc-resolution"><i class="bi bi-check2-square me-1"></i><?= e($d['resolution_notes']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
function toggleResolve(id) {
    const el = document.getElementById('resolve-' + id);
    if (el) el.classList.toggle('active');
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
