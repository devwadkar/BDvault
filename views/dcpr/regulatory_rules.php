<?php $pageTitle = 'Regulatory Constants'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php $canEdit = function_exists('hasPermission') ? hasPermission('manage_weights') : false; ?>

<style>
    :root {
        --rr-ink: #0f172a; --rr-muted: #64748b; --rr-border: #e2e8f0;
        --rr-primary: #4f46e5; --rr-surface: #f8fafc;
    }
    .rr-hero {
        background: linear-gradient(135deg, #0f172a, #1e293b);
        color: #fff; border-radius: 14px; padding: 22px 26px; margin-bottom: 20px;
        display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;
    }
    .rr-hero h4 { font-weight: 800; margin: 0; letter-spacing: -0.02em; }
    .rr-hero .sub { color: #cbd5e1; font-size: 0.85rem; margin-top: 4px; }

    .rr-card {
        background: #fff; border: 1px solid var(--rr-border); border-radius: 14px;
        margin-bottom: 20px; overflow: hidden;
    }
    .rr-card-hd {
        padding: 16px 22px; border-bottom: 1px solid var(--rr-border);
        display: flex; justify-content: space-between; align-items: center;
        background: var(--rr-surface);
    }
    .rr-card-hd h5 { margin: 0; font-weight: 700; color: var(--rr-ink); font-size: 1.02rem; letter-spacing: -0.01em; }
    .rr-card-hd .badge-scope {
        background: #eef2ff; color: #4338ca; padding: 3px 12px; border-radius: 999px;
        font-family: 'JetBrains Mono', Consolas, monospace; font-weight: 700; font-size: 0.75rem;
    }
    .rr-card-hd .count { color: var(--rr-muted); font-size: 0.75rem; }

    .rr-tbl { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
    .rr-tbl th, .rr-tbl td { padding: 12px 16px; border-bottom: 1px solid var(--rr-border); text-align: left; vertical-align: middle; }
    .rr-tbl thead th { background: #fafbfd; color: var(--rr-muted); font-weight: 700; text-transform: uppercase; font-size: 0.68rem; letter-spacing: 0.06em; }
    .rr-tbl tbody tr:hover { background: #fafbfd; }
    .rr-tbl .rk { font-family: 'JetBrains Mono', Consolas, monospace; color: var(--rr-primary); font-size: 0.82rem; font-weight: 600; }
    .rr-tbl .lbl { color: var(--rr-ink); font-weight: 500; }
    .rr-tbl .val { font-family: 'JetBrains Mono', Consolas, monospace; font-weight: 700; text-align: right; }
    .rr-tbl .unit { color: var(--rr-muted); font-size: 0.78rem; }
    .rr-tbl .ref  { color: #059669; font-size: 0.78rem; font-family: 'JetBrains Mono', Consolas, monospace; }
    .rr-tbl .desc { color: var(--rr-muted); font-size: 0.78rem; }

    .rr-actions a { color: var(--rr-primary); text-decoration: none; font-weight: 600; font-size: 0.78rem; margin-right: 12px; }
    .rr-actions a:hover { text-decoration: underline; }

    .rr-edit-form { display: none; padding: 16px 22px; background: #fffbeb; border-top: 1px solid #fde68a; }
    .rr-edit-form.active { display: block; }
    .rr-edit-form .form-row { display: grid; grid-template-columns: 1fr 2fr 120px; gap: 12px; align-items: end; }
    .rr-edit-form label { color: var(--rr-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; margin-bottom: 4px; }
    .rr-edit-form input[type=number],
    .rr-edit-form input[type=text] {
        width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 8px;
        font-family: 'JetBrains Mono', Consolas, monospace;
    }
    .rr-edit-form button {
        background: var(--rr-primary); color: #fff; border: 0; padding: 10px 18px;
        border-radius: 8px; font-weight: 700; font-size: 0.82rem;
    }
    .rr-edit-form .cancel-link {
        color: var(--rr-muted); font-size: 0.78rem; text-decoration: none; margin-left: 12px;
    }

    .rr-summary {
        background: linear-gradient(135deg, #ecfeff, #cffafe);
        border: 1px solid #67e8f9; border-radius: 10px; padding: 12px 16px;
        margin-bottom: 16px; color: #164e63; font-size: 0.85rem;
    }
    .rr-summary strong { color: #0e7490; }
</style>

<div class="rr-hero">
    <div>
        <h4><i class="bi bi-shield-lock me-2"></i>Regulatory Constants</h4>
        <div class="sub">Every DCPR value that used to be hardcoded in calculators now lives here. Changes take effect the moment they're saved and are captured in an audit trail.</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="index.php?page=dcpr" class="btn btn-sm btn-outline-light">
            <i class="bi bi-book me-1"></i>Rule Base
        </a>
        <?php if ($canEdit): ?>
            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Edit access</span>
        <?php else: ?>
            <span class="badge bg-secondary"><i class="bi bi-eye me-1"></i>Read-only</span>
        <?php endif; ?>
    </div>
</div>

<?php if ($flash = flashGet()): ?>
    <div class="alert alert-<?= e($flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'danger' : 'info')) ?>">
        <?= e($flash['message']) ?>
    </div>
<?php endif; ?>

<div class="rr-summary">
    <strong><?= count($rows) ?></strong> constants across <strong><?= count($byScope) ?></strong> scopes.
    Refactored calculators (Reg33_10 · Reg33_11 · Reg33_5 · Reg33_20B · AreaCalcEngine · FinancialsBridge) all read from this table.
</div>

<?php foreach ($byScope as $scopeName => $scopeRows): ?>
    <div class="rr-card">
        <div class="rr-card-hd">
            <div class="d-flex align-items-center gap-3">
                <span class="badge-scope"><?= e($scopeName) ?></span>
                <h5><?= $scopeName === 'global' ? 'Cross-scheme constants' : "Regulation {$scopeName} constants" ?></h5>
            </div>
            <span class="count"><?= count($scopeRows) ?> row<?= count($scopeRows) === 1 ? '' : 's' ?></span>
        </div>
        <div class="table-responsive">
            <table class="rr-tbl">
                <thead>
                    <tr>
                        <th>Rule key</th>
                        <th>Label</th>
                        <th style="text-align:right;">Value</th>
                        <th>Unit</th>
                        <th>DCPR ref</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($scopeRows as $r): ?>
                    <tr>
                        <td class="rk"><?= e($r['rule_key']) ?></td>
                        <td class="lbl">
                            <?= e($r['label']) ?>
                            <?php if (!empty($r['description'])): ?>
                                <div class="desc"><?= e($r['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="val"><?= $r['numeric_value'] !== null ? rtrim(rtrim(number_format((float)$r['numeric_value'], 4), '0'), '.') : '—' ?></td>
                        <td class="unit"><?= e($r['unit'] ?? '') ?></td>
                        <td class="ref"><?= e($r['regulation_ref']) ?></td>
                        <td class="rr-actions" style="white-space: nowrap;">
                            <?php if ($canEdit): ?>
                                <a href="#" onclick="toggleEdit(<?= (int)$r['id'] ?>); return false;">Edit</a>
                            <?php endif; ?>
                            <a href="index.php?page=regulatory_rule_audit&id=<?= (int)$r['id'] ?>">History</a>
                        </td>
                    </tr>
                    <?php if ($canEdit): ?>
                    <tr id="edit-row-<?= (int)$r['id'] ?>" style="display: none;">
                        <td colspan="6" style="padding: 0;">
                            <form method="POST" action="index.php?page=regulatory_rule_update" class="rr-edit-form active">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <div class="form-row">
                                    <div>
                                        <label>New value</label>
                                        <input type="number" step="any" name="new_value" value="<?= (float)$r['numeric_value'] ?>" required autofocus>
                                    </div>
                                    <div>
                                        <label>Change reason (audit trail)</label>
                                        <input type="text" name="change_reason" placeholder="e.g. DCPR notification dated 2026-04-01 raised fungible to 40%">
                                    </div>
                                    <div>
                                        <button type="submit">Save</button>
                                        <a href="#" class="cancel-link" onclick="toggleEdit(<?= (int)$r['id'] ?>); return false;">Cancel</a>
                                    </div>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<script>
function toggleEdit(id) {
    const row = document.getElementById('edit-row-' + id);
    if (row) row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
