<?php $pageTitle = 'Applicability review — ' . e($statement['statement_name']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php
// Comes from controller: $statement, $proposal, $context, $results, $buckets, $counts
$fmtPhase = fn($p) => ucfirst(str_replace('_', ' ', (string)$p));
$fmtN = fn($v, $d = 2) => $v === null || $v === '' ? '—' : number_format((float)$v, $d);
?>

<style>
:root {
    --ac-ink: #0f172a; --ac-muted: #64748b; --ac-faint: #94a3b8;
    --ac-border: #e2e8f0; --ac-surface: #f8fafc;
    --ac-primary: #0ea5e9; --ac-primary-d: #0369a1;
    --ac-success: #059669; --ac-warning: #d97706; --ac-danger: #dc2626;
}
.d-crumb { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; font-size: 0.84rem; color: var(--ac-muted); }
.d-crumb a { color: var(--ac-primary-d); text-decoration: none; font-weight: 600; }
.d-crumb .sep { color: var(--ac-faint); }

.ap-hero {
    background: linear-gradient(135deg, #4338ca 0%, #6366f1 100%); color: #fff;
    border-radius: 14px; padding: 20px 26px; margin-bottom: 14px;
    display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap;
}
.ap-hero h4 { font-weight: 800; margin: 0 0 3px; letter-spacing: -0.02em; }
.ap-hero p  { color: #c7d2fe; font-size: 0.84rem; margin: 0; max-width: 600px; }

.ap-counts { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 12px; }
.ap-count { background: #fff; border: 1px solid var(--ac-border); border-radius: 10px; padding: 12px 14px; }
.ap-count .k { font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--ac-muted); font-weight: 700; }
.ap-count .v { font-family: 'JetBrains Mono', Consolas, monospace; font-size: 1.4rem; font-weight: 800; margin-top: 3px; }
.ap-count.mand .v   { color: #1e40af; }
.ap-count.cond .v   { color: #6d28d9; }
.ap-count.verify .v { color: var(--ac-warning); }
.ap-count.na .v     { color: var(--ac-muted); }
.ap-count.chg .v    { color: var(--ac-danger); }

/* Context panel */
.ctx-panel { background: #fff; border: 1px solid var(--ac-border); border-radius: 12px; padding: 14px 18px; margin-bottom: 14px; }
.ctx-panel h6 {
    font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.06em;
    color: var(--ac-muted); font-weight: 800; margin: 0 0 10px;
}
.ctx-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 8px 18px; font-size: 0.78rem; }
.ctx-grid .k { color: var(--ac-muted); font-size: 0.72rem; }
.ctx-grid .v { color: var(--ac-ink); font-weight: 700; font-family: 'JetBrains Mono', Consolas, monospace; }
.ctx-grid .v.text { font-family: inherit; }

/* Bulk-action strip */
.ap-actions {
    background: #fff; border: 1px solid var(--ac-border); border-radius: 12px;
    padding: 14px 18px; margin-bottom: 14px;
    display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap;
}
.ap-actions .msg { font-size: 0.84rem; color: var(--ac-ink); }
.ap-actions .msg b { color: var(--ac-primary-d); }
.btn-ap {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 8px; font-size: 0.82rem; font-weight: 700;
    text-decoration: none; cursor: pointer; border: 1px solid transparent;
}
.btn-ap.primary { background: var(--ac-primary); color: #fff; border-color: var(--ac-primary); }
.btn-ap.primary:hover { background: var(--ac-primary-d); color: #fff; }
.btn-ap.ghost { background: #fff; color: var(--ac-muted); border-color: var(--ac-border); }
.btn-ap.ghost:hover { background: var(--ac-surface); color: var(--ac-ink); }

/* Buckets */
.bk-section { margin-bottom: 18px; }
.bk-head {
    display: flex; align-items: center; gap: 10px; margin-bottom: 8px;
    font-weight: 800; color: var(--ac-ink); font-size: 1rem;
}
.bk-head .chip {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 50%; font-family: 'JetBrains Mono', Consolas, monospace;
    font-size: 0.78rem; font-weight: 800;
}
.bk-head.mandatory .chip   { background: #dbeafe; color: #1e40af; }
.bk-head.conditional .chip { background: #ede9fe; color: #6d28d9; }
.bk-head.requires_verification .chip { background: #fef3c7; color: #92400e; }
.bk-head.not_applicable .chip { background: #f1f5f9; color: var(--ac-muted); }

.bk-list { background: #fff; border: 1px solid var(--ac-border); border-radius: 12px; overflow: hidden; }
.bk-row {
    display: grid; grid-template-columns: 24px 1fr auto; gap: 14px; align-items: center;
    padding: 12px 18px; border-bottom: 1px solid #f1f5f9;
}
.bk-row:last-child { border-bottom: 0; }
.bk-row:hover { background: var(--ac-surface); }
.bk-row.registered { background: #ecfdf5; }
.bk-row.registered:hover { background: #d1fae5; }
.bk-row.changed { background: #fff7ed; }
.bk-row.changed:hover { background: #ffedd5; }

.bk-row input[type=checkbox] { transform: scale(1.2); accent-color: var(--ac-primary); }
.bk-row .n { font-weight: 700; color: var(--ac-ink); font-size: 0.88rem; }
.bk-row .m { color: var(--ac-muted); font-size: 0.74rem; margin-top: 2px; line-height: 1.4; }
.bk-row .rt { color: var(--ac-faint); font-size: 0.68rem; font-weight: 600; margin-top: 2px; }

.pill-sm { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 0.62rem; font-weight: 700; letter-spacing: 0.02em; }
.pill-sm.added     { background: #d1fae5; color: #065f46; }
.pill-sm.changed   { background: #ffedd5; color: #9a3412; }
.pill-sm.flagged   { background: #fef3c7; color: #92400e; }

.bk-empty { padding: 18px; text-align: center; color: var(--ac-faint); font-size: 0.84rem; }

/* "Changed since last review" diff banner */
.diff-row {
    display: flex; gap: 6px; align-items: center;
    font-size: 0.72rem; margin-top: 4px;
}
.diff-row .was { color: var(--ac-muted); text-decoration: line-through; }
.diff-row .now { color: var(--ac-primary-d); font-weight: 700; }
</style>

<!-- Breadcrumb -->
<div class="d-crumb">
    <a href="index.php?page=approval_costs"><i class="bi bi-shield-check me-1"></i>Approval Costs</a>
    <span class="sep">›</span>
    <a href="index.php?page=approval_costs_register&statement_id=<?= (int)$statement['id'] ?>"><?= e($proposal['proposal_name']) ?></a>
    <span class="sep">›</span>
    <span>Applicability review</span>
</div>

<?php $flash = flashGet(); if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> py-2 px-3 mb-3" style="font-size:0.84rem;border-radius:8px;">
        <?= e($flash['msg']) ?>
    </div>
<?php endif; ?>

<!-- Hero -->
<div class="ap-hero">
    <div>
        <h4><i class="bi bi-funnel me-2"></i>Applicability review</h4>
        <p>Deterministic rule engine evaluates every master requirement against your project context.
            Nothing is auto-added — you confirm what belongs on the register.</p>
    </div>
    <a href="index.php?page=approval_costs_applicability&statement_id=<?= (int)$statement['id'] ?>" class="btn-ap ghost" style="background:#fff;">
        <i class="bi bi-arrow-clockwise"></i> Re-scan
    </a>
</div>

<!-- Context -->
<div class="ctx-panel">
    <h6>What the engine saw (from Area Analysis)</h6>
    <div class="ctx-grid">
        <div class="k">Planning authority</div>
        <div class="v text"><?= e($context['planning_authority'] ?: 'Not set') ?></div>

        <div class="k">Development type</div>
        <div class="v text"><?= e($fmtPhase($context['development_type'] ?: 'Not set')) ?></div>

        <div class="k">Regulation</div>
        <div class="v text"><?= e($context['selected_regulation_key'] ? 'DCPR ' . $context['selected_regulation_key'] : 'Not set') ?></div>

        <div class="k">Ward</div>
        <div class="v text"><?= e($context['ward'] ?: '—') ?><?php if ($context['ward_near_airport']): ?> <span style="color:var(--ac-warning);">(near airport)</span><?php endif; ?></div>

        <div class="k">Zone (DP)</div>
        <div class="v text"><?= e($context['zone_dp'] ?: '—') ?></div>

        <div class="k">Island City</div>
        <div class="v text"><?= $context['is_island_city'] ? 'Yes' : 'No' ?></div>

        <div class="k">Gross plot</div>
        <div class="v"><?= $fmtN($context['gross_plot_area_sqm'], 2) ?> sqm</div>

        <div class="k">Net plot</div>
        <div class="v"><?= $fmtN($context['net_plot_area_sqm'], 2) ?> sqm</div>

        <div class="k">Road width</div>
        <div class="v"><?= $fmtN($context['primary_road_width_m'], 2) ?> m</div>

        <div class="k">Total BUA</div>
        <div class="v"><?= $fmtN($context['total_bua_sqm'], 2) ?> sqm</div>

        <div class="k">FSI ratio (proxy)</div>
        <div class="v"><?= $fmtN($context['fsi_ratio'], 3) ?></div>

        <div class="k">Approx tenants</div>
        <div class="v"><?= (int)$context['approx_tenant_count'] ?></div>
    </div>
</div>

<!-- Bulk action strip -->
<div class="ap-actions">
    <div class="msg">
        <b><?= $counts['to_add_mandatory'] ?></b> mandatory requirement<?= $counts['to_add_mandatory'] !== 1 ? 's' : '' ?> not yet on the register
        <?php if ($counts['changed']): ?> · <b><?= $counts['changed'] ?></b> existing items drifted from the engine's current answer<?php endif; ?>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if ($counts['to_add_mandatory'] > 0): ?>
            <form method="POST" action="index.php?page=approval_costs_apply_suggestions" style="margin:0;">
                <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
                <input type="hidden" name="scope" value="mandatory">
                <button type="submit" class="btn-ap primary">
                    <i class="bi bi-check-all"></i> Add all mandatory (<?= $counts['to_add_mandatory'] ?>)
                </button>
            </form>
        <?php endif; ?>
        <?php if ($counts['changed'] > 0): ?>
            <form method="POST" action="index.php?page=approval_costs_apply_suggestions" style="margin:0;"
                  onsubmit="return confirm('Sync <?= $counts['changed'] ?> drifted items to the engine current answer? (overridden items are not touched)');">
                <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
                <input type="hidden" name="scope" value="drifted">
                <input type="hidden" name="sync_existing" value="1">
                <button type="submit" class="btn-ap ghost">
                    <i class="bi bi-arrow-left-right"></i> Sync drifted (<?= $counts['changed'] ?>)
                </button>
            </form>
        <?php endif; ?>
        <a href="index.php?page=approval_costs_register&statement_id=<?= (int)$statement['id'] ?>" class="btn-ap ghost">
            <i class="bi bi-arrow-right"></i> Register
        </a>
    </div>
</div>

<form method="POST" action="index.php?page=approval_costs_apply_suggestions" id="apForm">
    <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
    <input type="hidden" name="scope" value="selected">

    <!-- Counts -->
    <div class="ap-counts">
        <div class="ap-count mand"><div class="k">Mandatory</div><div class="v"><?= $counts['mandatory'] ?></div></div>
        <div class="ap-count cond"><div class="k">Conditional</div><div class="v"><?= $counts['conditional'] ?></div></div>
        <div class="ap-count verify"><div class="k">Verify</div><div class="v"><?= $counts['requires_verification'] ?></div></div>
        <div class="ap-count na"><div class="k">Not applicable</div><div class="v"><?= $counts['not_applicable'] ?></div></div>
        <?php if ($counts['changed']): ?>
            <div class="ap-count chg"><div class="k">Drifted</div><div class="v"><?= $counts['changed'] ?></div></div>
        <?php endif; ?>
    </div>

    <?php
    $renderBucket = function(string $bucketKey, string $title, array $rows) use ($fmtPhase) {
        if (empty($rows)) return;
        ?>
        <div class="bk-section">
            <div class="bk-head <?= $bucketKey ?>">
                <span class="chip"><?= count($rows) ?></span>
                <?= e($title) ?>
            </div>
            <div class="bk-list">
                <?php foreach ($rows as $r):
                    $m = $r['master'];
                    $cls = [];
                    if ($r['already_registered']) $cls[] = 'registered';
                    if ($r['has_changed'])        $cls[] = 'changed';
                ?>
                    <label class="bk-row <?= implode(' ', $cls) ?>">
                        <input type="checkbox" name="master_ids[]" value="<?= (int)$r['master_id'] ?>"
                               <?= $r['already_registered'] ? 'disabled' : '' ?>>
                        <div>
                            <div class="n"><?= e($m['standardized_name']) ?>
                                <?php if ($r['already_registered']): ?>
                                    <span class="pill-sm added"><i class="bi bi-check-circle-fill"></i> on register</span>
                                <?php endif; ?>
                                <?php if ($r['has_changed']): ?>
                                    <span class="pill-sm changed">drifted</span>
                                <?php endif; ?>
                                <?php if (!empty($m['requires_classification'])): ?>
                                    <span class="pill-sm flagged">needs classification</span>
                                <?php endif; ?>
                            </div>
                            <div class="rt"><?= e($m['record_type']) ?> · <?= e($fmtPhase($m['category_key'])) ?> · <?= e($fmtPhase($m['typical_phase'])) ?></div>
                            <div class="m"><i class="bi bi-signpost-split me-1"></i><?= e($r['rationale']) ?></div>
                            <?php if ($r['has_changed']): ?>
                                <div class="diff-row">
                                    <span class="was">was: <?= e($fmtPhase($r['current_registered'])) ?></span>
                                    <i class="bi bi-arrow-right" style="color:var(--ac-muted);"></i>
                                    <span class="now">now: <?= e($fmtPhase($r['applicability'])) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if (!$r['already_registered']): ?>
                                <span class="pill-sm" style="background:#eef2ff;color:#3730a3;">add</span>
                            <?php endif; ?>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    };

    $renderBucket('mandatory',             'Mandatory',             $buckets['mandatory']);
    $renderBucket('conditional',           'Conditional',           $buckets['conditional']);
    $renderBucket('requires_verification', 'Requires verification', $buckets['requires_verification']);
    $renderBucket('not_applicable',        'Not applicable',        $buckets['not_applicable']);
    ?>

    <div class="ap-actions" style="margin-top: 10px;">
        <div class="msg"><span id="selCount">0</span> selected</div>
        <button type="submit" class="btn-ap primary">
            <i class="bi bi-plus-lg"></i> Add selected to register
        </button>
    </div>
</form>

<script>
document.querySelectorAll('#apForm input[type=checkbox]').forEach(cb => {
    cb.addEventListener('change', () => {
        const n = document.querySelectorAll('#apForm input[type=checkbox]:checked').length;
        document.getElementById('selCount').textContent = n;
    });
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
