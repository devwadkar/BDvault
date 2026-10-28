<?php $pageTitle = 'Audit · ' . e($constant['rule_key']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<style>
    .aud-hero {
        background: linear-gradient(135deg, #0f172a, #1e293b);
        color: #fff; border-radius: 14px; padding: 22px 26px; margin-bottom: 20px;
    }
    .aud-hero h4 { margin: 0; font-weight: 800; letter-spacing: -0.02em; }
    .aud-hero .sub { color: #cbd5e1; font-size: 0.85rem; margin-top: 6px; font-family: 'JetBrains Mono', Consolas, monospace; }
    .aud-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; margin-bottom: 20px; }
    .aud-card h5 { margin: 0 0 14px; font-weight: 700; color: #0f172a; }
    .aud-meta { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 4px; }
    .aud-meta .k { color: #64748b; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; margin-bottom: 4px; }
    .aud-meta .v { font-family: 'JetBrains Mono', Consolas, monospace; color: #0f172a; font-weight: 700; }
    .aud-tbl { width: 100%; border-collapse: collapse; font-size: 0.87rem; }
    .aud-tbl th, .aud-tbl td { padding: 12px 14px; border-bottom: 1px solid #e2e8f0; text-align: left; }
    .aud-tbl thead th { color: #64748b; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; }
    .aud-tbl .val { font-family: 'JetBrains Mono', Consolas, monospace; font-weight: 700; }
    .aud-tbl .neg { color: #dc2626; }
    .aud-tbl .pos { color: #059669; }
    .aud-tbl .reason { color: #475569; font-style: italic; }
    .aud-empty { padding: 40px; text-align: center; color: #94a3b8; }
    .aud-empty i { font-size: 2.5rem; }
    .back-link { color: #4f46e5; text-decoration: none; font-weight: 600; font-size: 0.85rem; margin-bottom: 12px; display: inline-block; }
</style>

<a href="index.php?page=regulatory_rules" class="back-link">
    <i class="bi bi-arrow-left me-1"></i>Back to Regulatory Constants
</a>

<div class="aud-hero">
    <h4><?= e($constant['label']) ?></h4>
    <div class="sub"><?= e($constant['scope']) ?> / <?= e($constant['rule_key']) ?></div>
</div>

<div class="aud-card">
    <h5>Current state</h5>
    <div class="aud-meta">
        <div>
            <div class="k">Current value</div>
            <div class="v"><?= rtrim(rtrim(number_format((float)$constant['numeric_value'], 4), '0'), '.') ?> <span style="color:#64748b;font-size:0.8rem;font-weight:500;"><?= e($constant['unit']) ?></span></div>
        </div>
        <div>
            <div class="k">Unit</div>
            <div class="v" style="color: #64748b;"><?= e($constant['unit']) ?></div>
        </div>
        <div>
            <div class="k">DCPR reference</div>
            <div class="v" style="color: #059669;"><?= e($constant['regulation_ref']) ?></div>
        </div>
        <div>
            <div class="k">Last updated</div>
            <div class="v" style="color: #64748b; font-size: 0.85rem;"><?= e($constant['updated_at']) ?></div>
        </div>
    </div>
    <?php if (!empty($constant['description'])): ?>
        <p style="margin-top: 16px; color: #64748b; font-size: 0.87rem;"><?= e($constant['description']) ?></p>
    <?php endif; ?>
</div>

<div class="aud-card">
    <h5>Change history (<?= count($history) ?>)</h5>
    <?php if (empty($history)): ?>
        <div class="aud-empty">
            <i class="bi bi-clock-history"></i>
            <div class="mt-2">No changes yet — this constant is at its seeded value.</div>
        </div>
    <?php else: ?>
        <table class="aud-tbl">
            <thead>
                <tr>
                    <th>When</th>
                    <th style="text-align:right;">Old</th>
                    <th style="text-align:right;">New</th>
                    <th style="text-align:right;">Δ</th>
                    <th>Reason</th>
                    <th>By user</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h):
                    $old = $h['old_value'] !== null ? (float)$h['old_value'] : null;
                    $new = $h['new_value'] !== null ? (float)$h['new_value'] : null;
                    $delta = ($old !== null && $new !== null) ? $new - $old : null;
                    $dcls  = ($delta === null) ? '' : ($delta < 0 ? 'neg' : 'pos');
                ?>
                <tr>
                    <td><?= e($h['changed_at']) ?></td>
                    <td class="val" style="text-align:right;"><?= $old !== null ? rtrim(rtrim(number_format($old, 4), '0'), '.') : '—' ?></td>
                    <td class="val" style="text-align:right;"><?= $new !== null ? rtrim(rtrim(number_format($new, 4), '0'), '.') : '—' ?></td>
                    <td class="val <?= $dcls ?>" style="text-align:right;"><?= $delta !== null ? (($delta >= 0 ? '+' : '') . rtrim(rtrim(number_format($delta, 4), '0'), '.')) : '—' ?></td>
                    <td class="reason"><?= e($h['change_reason'] ?? '(no reason given)') ?></td>
                    <td><?= $h['changed_by'] !== null ? '#' . (int)$h['changed_by'] : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
