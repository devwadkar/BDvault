<?php $pageTitle = 'DCPR Rule Base'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<style>
    .dcpr-hero {
        background: linear-gradient(135deg, #0f172a, #1e293b);
        color: #fff;
        border-radius: 14px;
        padding: 22px 26px;
        margin-bottom: 20px;
    }
    .dcpr-hero h4 { font-weight: 800; margin: 0 0 4px; letter-spacing: -0.02em; }
    .dcpr-hero p  { margin: 0; color: #cbd5e1; font-size: 0.85rem; }
    .rule-card {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 14px 16px;
        transition: transform .12s, box-shadow .12s;
        background: #fff;
        height: 100%;
    }
    .rule-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.06); }
    .rule-key {
        display: inline-block; padding: 2px 10px; border-radius: 999px;
        background: #eef2ff; color: #4338ca; font-weight: 700; font-size: 0.72rem; letter-spacing: .04em;
    }
    .part-badge {
        font-size: 0.65rem; color: #64748b; text-transform: uppercase; letter-spacing: .08em; margin-left: 8px;
    }
    .rule-title  { color: #0f172a; font-weight: 700; margin-top: 8px; font-size: 0.95rem; line-height: 1.35; }
    .rule-meta   { color: #94a3b8; font-size: 0.72rem; margin-top: 6px; }
    .reload-btn  {
        background: #4f46e5; color: #fff; border: 0; padding: 8px 16px; border-radius: 8px;
        font-weight: 600; font-size: 0.8rem;
    }
    .reload-btn:hover { background: #4338ca; color: #fff; }
</style>

<div class="dcpr-hero d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h4><i class="bi bi-buildings me-2"></i>DCPR 2034 Rule Base</h4>
        <p>Structured regulations loaded from <code style="color:#93c5fd">dcpr_rules/*.json</code> — used by the Compliance Checker, FSI Optimizer, and AI Advisory layer.</p>
    </div>
    <?php if (can('manage_weights')): ?>
        <a href="index.php?page=dcpr_reload" class="reload-btn"
           onclick="return confirm('Re-read every JSON file into the dcpr_rules table?')">
            <i class="bi bi-arrow-clockwise me-1"></i>Reload from JSON
        </a>
    <?php endif; ?>
</div>

<?php if (empty($rules)): ?>
    <div class="alert alert-warning">
        No rules loaded yet. Click <b>Reload from JSON</b> above to import the JSON files under <code>dcpr_rules/</code>.
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($rules as $r): ?>
            <div class="col-md-6 col-lg-4">
                <a href="index.php?page=dcpr_rule&key=<?= urlencode($r['regulation_key']) ?>" class="text-decoration-none">
                    <div class="rule-card">
                        <div>
                            <span class="rule-key">Reg <?= e($r['regulation_key']) ?></span>
                            <?php if (!empty($r['part'])): ?>
                                <span class="part-badge">Part <?= e($r['part']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="rule-title"><?= e($r['title']) ?></div>
                        <div class="rule-meta">
                            <?= e($r['source']) ?>
                            <?php if (!empty($r['effective_from'])): ?> · Effective <?= e($r['effective_from']) ?><?php endif; ?>
                            · v<?= e($r['version']) ?>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
