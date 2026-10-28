<?php $pageTitle = 'Select Regulation'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<style>
    .picker-hero { background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff; border-radius: 14px; padding: 22px 26px; margin-bottom: 20px; }
    .picker-hero h5 { font-weight: 700; margin: 0 0 4px; }
    .picker-hero p  { margin: 0; color: #cbd5e1; font-size: 0.82rem; }
    .reg-option {
        border: 2px solid #e5e7eb; border-radius: 12px; padding: 18px 22px; margin-bottom: 12px;
        transition: border-color .15s, box-shadow .15s; cursor: pointer;
    }
    .reg-option:hover { border-color: #6366f1; box-shadow: 0 4px 12px rgba(99,102,241,0.12); }
    .reg-option.disabled { opacity: 0.5; cursor: not-allowed; }
    .reg-option input[type=radio] { margin-right: 12px; }
    .reg-key   { font-weight: 700; color: #0f172a; }
    .reg-desc  { color: #64748b; font-size: 0.82rem; margin-top: 2px; }
    .disabled-badge { font-size: 0.7rem; padding: 2px 8px; border-radius: 999px; background: #fef3c7; color: #92400e; margin-left: 8px; }
</style>

<a href="index.php?page=planning" class="text-muted small text-decoration-none">
    <i class="bi bi-arrow-left me-1"></i>Back to planning
</a>

<div class="picker-hero mt-2">
    <h5>Select the applicable development regulation</h5>
    <p>Proposal: <b><?= e($proposal['proposal_name']) ?></b>
       <?php if ($proposal['region']): ?> · <?= e($proposal['region']) ?><?php endif; ?>
       <?php if ($proposal['city']): ?>, <?= e($proposal['city']) ?><?php endif; ?>
    </p>
</div>

<form method="POST" action="index.php?page=planning_create">
    <input type="hidden" name="proposal_id" value="<?= (int)$proposal['id'] ?>">

    <?php foreach ($supported as $key => $title): ?>
        <label class="reg-option d-flex align-items-start">
            <input type="radio" name="regulation_key" value="<?= e($key) ?>" required>
            <div>
                <div class="reg-key">DCPR <?= e($key) ?></div>
                <div class="reg-desc"><?= e($title) ?></div>
            </div>
        </label>
    <?php endforeach; ?>

    <div class="mt-3 mb-2 text-uppercase small fw-bold text-muted" style="letter-spacing:.08em">
        <i class="bi bi-hourglass-split me-1"></i>Rule base loaded — calculator pending feasibility validation
    </div>
    <?php foreach ($pending as $key => $title): ?>
        <label class="reg-option disabled d-flex align-items-start">
            <input type="radio" disabled>
            <div>
                <div class="reg-key">DCPR <?= e($key) ?> <span class="disabled-badge">Awaiting feasibility</span></div>
                <div class="reg-desc"><?= e($title) ?> · Regulation text loaded into rule base; AI Advisor can already reference it. Calculator + input schema will be authored once a reference feasibility for this scheme is provided.</div>
            </div>
        </label>
    <?php endforeach; ?>

    <div class="mt-3">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-arrow-right me-1"></i>Continue to Input Form
        </button>
        <a href="index.php?page=planning" class="btn btn-outline-secondary ms-2">Cancel</a>
    </div>
</form>

<?php require __DIR__ . '/../layout/footer.php'; ?>
