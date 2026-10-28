<?php $pageTitle = 'Approval master library'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php $fmtPhase = fn($p) => ucfirst(str_replace('_', ' ', (string)$p)); ?>

<style>
:root {
    --ac-ink: #0f172a; --ac-muted: #64748b; --ac-faint: #94a3b8;
    --ac-border: #e2e8f0; --ac-surface: #f8fafc;
    --ac-primary: #0ea5e9; --ac-primary-d: #0369a1;
    --ac-warning: #d97706; --ac-success: #059669;
}
.d-crumb { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; font-size: 0.84rem; color: var(--ac-muted); }
.d-crumb a { color: var(--ac-primary-d); text-decoration: none; font-weight: 600; }
.d-crumb .sep { color: var(--ac-faint); }

.lib-hero {
    background: linear-gradient(135deg, #075985 0%, #0ea5e9 100%); color: #fff;
    border-radius: 14px; padding: 20px 26px; margin-bottom: 14px;
    display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap;
}
.lib-hero h4 { margin: 0 0 3px; font-weight: 800; }
.lib-hero p { margin: 0; color: #bae6fd; font-size: 0.82rem; }
.lib-hero .tiles { display: flex; gap: 18px; }
.lib-hero .tile .k { font-size: 0.62rem; color: #bae6fd; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
.lib-hero .tile .v { font-family: 'JetBrains Mono', Consolas, monospace; font-size: 1.3rem; font-weight: 800; }

.lib-filters {
    background: #fff; border: 1px solid var(--ac-border); border-radius: 12px;
    padding: 12px 14px; margin-bottom: 10px;
    display: grid; grid-template-columns: 2fr repeat(4, 1fr) auto; gap: 10px; align-items: center;
}
.lib-filters input, .lib-filters select {
    padding: 7px 10px; border: 1px solid var(--ac-border); border-radius: 8px; font-size: 0.82rem;
}
.lib-filters label.cbx { display: flex; align-items: center; gap: 6px; font-size: 0.82rem; color: var(--ac-ink); }
.lib-filters .btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px; font-size: 0.82rem; font-weight: 700; background: var(--ac-primary); color: #fff; border: 0; cursor: pointer; text-decoration: none; }
.lib-filters .clr { color: var(--ac-muted); font-size: 0.78rem; text-decoration: none; padding: 6px 10px; }

.lib-tbl { width: 100%; font-size: 0.84rem; border-collapse: collapse; background: #fff; }
.lib-tbl thead th {
    background: var(--ac-surface); color: var(--ac-muted); text-align: left;
    padding: 10px 12px; font-weight: 700; font-size: 0.66rem; text-transform: uppercase;
    letter-spacing: 0.04em; border-bottom: 1px solid var(--ac-border);
}
.lib-tbl tbody td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
.lib-tbl tbody tr:hover { background: var(--ac-surface); }
.lib-tbl .name { font-weight: 700; color: var(--ac-ink); }
.lib-tbl .code { font-family: 'JetBrains Mono', Consolas, monospace; color: var(--ac-faint); font-size: 0.7rem; margin-top: 2px; }
.lib-tbl .num { font-family: 'JetBrains Mono', Consolas, monospace; text-align: center; }

.pill-sm { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.62rem; font-weight: 700; letter-spacing: 0.02em; }
.pill-sm.STATUTORY    { background: #dbeafe; color: #1e40af; }
.pill-sm.NOC          { background: #cffafe; color: #155e75; }
.pill-sm.LAND_RECORD  { background: #fef3c7; color: #92400e; }
.pill-sm.TECH_REPORT  { background: #e0e7ff; color: #3730a3; }
.pill-sm.MILESTONE    { background: #d1fae5; color: #065f46; }
.pill-sm.REGISTRATION { background: #ede9fe; color: #6d28d9; }
.pill-sm.UTILITY      { background: #ffedd5; color: #9a3412; }
.pill-sm.OTHER        { background: #f1f5f9; color: #64748b; }
.pill-sm.flagged      { background: #fef3c7; color: #92400e; }
</style>

<div class="d-crumb">
    <a href="index.php?page=approval_costs"><i class="bi bi-shield-check me-1"></i>Approval Costs</a>
    <span class="sep">›</span>
    <span>Master library</span>
</div>

<div class="lib-hero">
    <div>
        <h4><i class="bi bi-journal-bookmark me-2"></i>Master library</h4>
        <p>Reconciled from the two intake workbooks. Every original source entry is preserved as an alias with full provenance.</p>
    </div>
    <div class="tiles">
        <div class="tile"><div class="k">Masters</div><div class="v"><?= (int)$totals['masters'] ?></div></div>
        <div class="tile"><div class="k">Aliases</div><div class="v"><?= (int)$totals['aliases'] ?></div></div>
        <div class="tile"><div class="k">Costs</div><div class="v"><?= (int)$totals['costs'] ?></div></div>
        <div class="tile"><div class="k">Durations</div><div class="v"><?= (int)$totals['durations'] ?></div></div>
        <div class="tile"><div class="k">Flagged</div><div class="v" style="color:#fde68a;"><?= (int)$totals['flagged'] ?></div></div>
    </div>
</div>

<form method="GET" action="index.php" class="lib-filters">
    <input type="hidden" name="page" value="approval_costs_master_library">
    <input type="text" name="q" value="<?= e($filters['filterSearch']) ?>" placeholder="Search name, code or alias…">
    <select name="type">
        <option value="">All types</option>
        <?php foreach (['STATUTORY','NOC','LAND_RECORD','TECH_REPORT','MILESTONE','REGISTRATION','UTILITY','OTHER'] as $t): ?>
            <option value="<?= $t ?>" <?= $filters['filterType'] === $t ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
    </select>
    <select name="category">
        <option value="">All categories</option>
        <?php foreach (['land_title','planning_dp','municipal','environmental','fire','location_noc','demolition','construction_inspect','completion_occupancy','registrations','technical_reports','utilities','requires_classification'] as $c): ?>
            <option value="<?= $c ?>" <?= $filters['filterCategory'] === $c ? 'selected' : '' ?>><?= $fmtPhase($c) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="phase">
        <option value="">All phases</option>
        <?php foreach (['land_acquisition','pre_construction','during_construction','post_construction'] as $p): ?>
            <option value="<?= $p ?>" <?= $filters['filterPhase'] === $p ? 'selected' : '' ?>><?= $fmtPhase($p) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="cbx"><input type="checkbox" name="flagged" value="1" <?= $filters['filterFlagged'] ? 'checked' : '' ?>> Flagged only</label>
    <div style="display:flex;gap:6px;">
        <button type="submit" class="btn"><i class="bi bi-funnel"></i></button>
        <a class="clr" href="index.php?page=approval_costs_master_library">Clear</a>
    </div>
</form>

<div style="background:#fff;border:1px solid var(--ac-border);border-radius:12px;overflow:hidden;">
    <table class="lib-tbl">
        <thead>
            <tr>
                <th>Standardized name</th>
                <th>Type</th>
                <th>Category</th>
                <th>Phase</th>
                <th>Authority</th>
                <th class="num">Aliases</th>
                <th class="num">Costs</th>
                <th class="num">Durations</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($masters as $m): ?>
                <tr>
                    <td>
                        <div class="name"><?= e($m['standardized_name']) ?>
                            <?php if ($m['requires_classification']): ?>
                                <span class="pill-sm flagged">needs classification</span>
                            <?php endif; ?>
                        </div>
                        <div class="code"><?= e($m['code']) ?></div>
                    </td>
                    <td><span class="pill-sm <?= e($m['record_type']) ?>"><?= e($m['record_type']) ?></span></td>
                    <td style="font-size:0.78rem;"><?= e($fmtPhase($m['category_key'])) ?></td>
                    <td style="font-size:0.78rem;"><?= e($fmtPhase($m['typical_phase'])) ?></td>
                    <td style="font-size:0.78rem;color:<?= $m['authority_hint'] ? 'var(--ac-ink)' : 'var(--ac-faint)' ?>;"><?= e($m['authority_hint'] ?: '—') ?></td>
                    <td class="num"><?= (int)$m['alias_count'] ?></td>
                    <td class="num"><?= (int)$m['cost_count'] ?: '—' ?></td>
                    <td class="num"><?= (int)$m['duration_count'] ?: '—' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($masters)): ?>
                <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--ac-faint);">No matching records.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
