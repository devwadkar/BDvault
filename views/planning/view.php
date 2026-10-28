<?php $pageTitle = 'Plan — ' . e($plan['plan_name']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<style>
    .plan-hero { background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff; border-radius: 14px; padding: 22px 26px; margin-bottom: 20px; }
    .plan-hero .badge-reg { background: rgba(99,102,241,0.25); color: #c7d2fe; font-weight: 700; padding: 3px 12px; border-radius: 999px; font-size: 0.72rem; }
    .plan-hero h5 { margin: 8px 0 0; font-weight: 700; }
    .plan-hero p  { margin: 4px 0 0; color: #cbd5e1; font-size: 0.8rem; }
    .section {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
        padding: 18px 22px; margin-bottom: 18px;
    }
    .section h6 {
        font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
        font-size: 0.72rem; color: #0f172a; margin: 0 0 12px;
    }
    .section h6 .section-icon { color: #6366f1; margin-right: 6px; }
    table.calc-table  { width: 100%; font-size: 0.82rem; }
    table.calc-table thead th {
        background: #f8fafc; color: #64748b; font-weight: 600; text-transform: uppercase;
        font-size: 0.68rem; letter-spacing: .04em; padding: 8px 10px; border-bottom: 1px solid #e5e7eb;
    }
    table.calc-table td {
        padding: 8px 10px; vertical-align: top; border-bottom: 1px solid #f1f5f9;
    }
    .calc-label { font-weight: 600; color: #0f172a; }
    .calc-value { text-align: right; font-family: 'JetBrains Mono', 'Consolas', monospace; color: #0f172a; }
    .calc-unit  { color: #64748b; font-size: 0.72rem; }
    .calc-formula { color: #64748b; font-size: 0.72rem; font-family: 'JetBrains Mono', 'Consolas', monospace; margin-top: 2px; }
    .calc-regref  { color: #6366f1; font-size: 0.68rem; text-transform: uppercase; letter-spacing: .04em; margin-top: 2px; }
    .summary-strip {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px; margin-bottom: 18px;
    }
    .summary-card {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px 16px;
    }
    .summary-label { color: #64748b; font-size: 0.72rem; text-transform: uppercase; letter-spacing: .05em; }
    .summary-value { color: #0f172a; font-weight: 700; font-size: 1.05rem; margin-top: 4px; }
    .no-data { color: #94a3b8; padding: 12px 0; }
    .banner-verify {
        background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46;
        padding:10px 14px; border-radius:10px; font-size:0.8rem; margin-bottom:14px;
    }

    /* Validation panel */
    .val-panel {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
        padding: 16px 20px; margin-bottom: 18px;
    }
    .val-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 4px; }
    .val-title  { font-weight: 700; text-transform: uppercase; letter-spacing: .06em; font-size: 0.75rem; color: #0f172a; }
    .val-counters { display: flex; gap: 8px; }
    .val-pill {
        display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px;
        font-size: 0.75rem; font-weight: 700;
    }
    .val-pill.red    { background: #fee2e2; color: #991b1b; }
    .val-pill.yellow { background: #fef3c7; color: #92400e; }
    .val-pill.green  { background: #dcfce7; color: #166534; }
    .val-pill.total  { background: #f1f5f9; color: #475569; }
    .val-list        { border-top: 1px solid #f1f5f9; margin-top: 12px; padding-top: 8px; }
    .val-row {
        display: grid;
        grid-template-columns: 32px 1fr auto;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px dashed #f1f5f9;
    }
    .val-row:last-child { border-bottom: 0; }
    .val-icon {
        width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: 0.75rem;
    }
    .val-icon.red    { background: #dc2626; }
    .val-icon.yellow { background: #f59e0b; }
    .val-icon.green  { background: #16a34a; }
    .val-label { font-weight: 600; font-size: 0.86rem; color: #0f172a; }
    .val-meta  { font-size: 0.72rem; color: #64748b; margin-top: 2px; }
    .val-meta .val-cite { color: #6366f1; text-transform: uppercase; letter-spacing: .04em; font-size: 0.66rem; margin-right: 8px; }
    .val-numbers { font-family: 'JetBrains Mono', 'Consolas', monospace; font-size: 0.78rem; color: #334155; text-align: right; min-width: 160px;}
    .val-action { grid-column: 2 / span 2; margin-top: 4px; font-size: 0.78rem; color: #475569; background: #fffbeb; border-left: 3px solid #f59e0b; padding: 6px 10px; border-radius: 4px; }
    .val-action.red { background: #fef2f2; border-left-color: #dc2626; }
    .val-toggle { background: none; border: 1px solid #e5e7eb; border-radius: 8px; padding: 4px 12px; font-size: 0.72rem; color: #64748b; cursor: pointer; }
    .val-toggle:hover { background: #f8fafc; }
    .val-list.collapsed .val-row.green { display: none; }

    /* AI Regulatory Assistant chat */
    .ai-fab {
        position: fixed; bottom: 22px; right: 22px; z-index: 1050;
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        color: #fff; border: 0; border-radius: 999px;
        padding: 12px 20px; font-weight: 700; font-size: 0.82rem;
        display: inline-flex; align-items: center; gap: 8px;
        box-shadow: 0 8px 24px rgba(79,70,229,0.35); cursor: pointer;
    }
    .ai-fab:hover { transform: translateY(-1px); box-shadow: 0 12px 30px rgba(79,70,229,0.45); }
    .ai-fab i { font-size: 1rem; }

    .ai-panel {
        position: fixed; bottom: 22px; right: 22px; width: 440px; max-width: calc(100vw - 44px);
        max-height: calc(100vh - 100px); z-index: 1050;
        background: #fff; border: 1px solid #e5e7eb; border-radius: 16px;
        box-shadow: 0 20px 50px rgba(15,23,42,0.18);
        display: none; flex-direction: column;
    }
    .ai-panel.open { display: flex; }
    .ai-head {
        background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;
        padding: 14px 18px; border-radius: 16px 16px 0 0;
        display: flex; justify-content: space-between; align-items: center;
    }
    .ai-head h6 { margin: 0; font-weight: 700; font-size: 0.9rem; letter-spacing: -0.01em; }
    .ai-head .ai-sub { font-size: 0.7rem; color: #cbd5e1; margin-top: 2px; }
    .ai-close {
        background: rgba(255,255,255,0.12); color: #fff; border: 0; border-radius: 8px;
        padding: 4px 8px; cursor: pointer;
    }
    .ai-body {
        flex: 1; overflow-y: auto; padding: 14px 18px; background: #f8fafc;
        display: flex; flex-direction: column; gap: 10px; min-height: 240px;
    }
    .ai-msg { max-width: 90%; padding: 10px 14px; border-radius: 12px; font-size: 0.85rem; line-height: 1.55; }
    .ai-msg.user { align-self: flex-end; background: #4f46e5; color: #fff; border-bottom-right-radius: 4px; }
    .ai-msg.assistant { align-self: flex-start; background: #fff; color: #0f172a; border: 1px solid #e5e7eb; border-bottom-left-radius: 4px; }
    .ai-msg.assistant .cite {
        display: inline-block; background: #eef2ff; color: #4338ca;
        padding: 1px 6px; border-radius: 999px; font-size: 0.72rem; font-weight: 600;
    }
    .ai-msg.assistant .cite-block {
        margin-top: 8px; padding-top: 8px; border-top: 1px dashed #e5e7eb;
        font-size: 0.7rem; color: #64748b;
    }
    .ai-warn {
        background: #fffbeb; color: #92400e; border: 1px solid #fde68a; padding: 6px 10px;
        border-radius: 8px; font-size: 0.72rem; margin-top: 6px;
    }
    .ai-suggest {
        display: flex; gap: 6px; flex-wrap: wrap; padding: 8px 18px; border-top: 1px solid #e5e7eb; background: #fff;
    }
    .ai-suggest button {
        background: #f1f5f9; color: #334155; border: 0; border-radius: 999px;
        padding: 4px 12px; font-size: 0.72rem; cursor: pointer;
    }
    .ai-suggest button:hover { background: #e2e8f0; }
    .ai-input {
        display: flex; padding: 12px 14px; border-top: 1px solid #e5e7eb; gap: 8px;
        border-radius: 0 0 16px 16px;
    }
    .ai-input textarea {
        flex: 1; border: 1px solid #e5e7eb; border-radius: 10px; padding: 8px 12px;
        font-size: 0.85rem; resize: none; height: 40px; font-family: inherit;
    }
    .ai-input textarea:focus { outline: none; border-color: #6366f1; }
    .ai-send {
        background: #4f46e5; color: #fff; border: 0; border-radius: 10px;
        padding: 0 16px; font-weight: 700; cursor: pointer;
    }
    .ai-send:disabled { opacity: 0.6; cursor: wait; }
    .ai-empty {
        text-align: center; color: #94a3b8; font-size: 0.82rem; margin: 30px 0;
    }
    .ai-empty i { font-size: 1.8rem; color: #cbd5e1; display: block; margin-bottom: 8px; }

    /* Visualization block */
    .viz-block {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
        padding: 18px 22px; margin-bottom: 18px;
    }
    .viz-block h6 {
        font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
        font-size: 0.72rem; color: #0f172a; margin: 0 0 14px;
    }
    .viz-block h6 .section-icon { color: #10b981; margin-right: 6px; }
    .viz-grid {
        display: grid; grid-template-columns: 1fr; gap: 18px;
    }
    @media (min-width: 992px) {
        .viz-grid.two-col   { grid-template-columns: 1fr 1fr; }
        .viz-grid.donut-col { grid-template-columns: 340px 1fr; }
    }
    .viz-card {
        background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;
        padding: 14px 16px;
        display: flex; flex-direction: column;
    }
    .viz-card h6 {
        font-size: 0.72rem; color: #475569; letter-spacing: .04em;
        margin: 0 0 10px; text-transform: uppercase; font-weight: 600;
    }
    .viz-card canvas { max-width: 100%; }
    /* Fixed uniform height for chart canvases so side-by-side cards align */
    .chart-wrap {
        position: relative; height: 260px; width: 100%;
    }
    .chart-wrap canvas {
        position: absolute; inset: 0; width: 100% !important; height: 100% !important;
    }
    /* Center the legend vertically in its card when the donut card is taller */
    .legend-wrap { display: flex; flex-direction: column; justify-content: center; height: 100%; min-height: 260px; }
    .land-legend {
        display: grid; grid-template-columns: 1fr auto auto; gap: 6px 12px;
        font-size: 0.78rem; align-items: center;
    }
    .land-legend .l-lbl { display: flex; align-items: center; gap: 8px; color: #334155; }
    .land-legend .l-dot { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
    .land-legend .l-val { font-family: 'JetBrains Mono', 'Consolas', monospace; color: #0f172a; text-align: right; }
    .land-legend .l-pct { font-family: 'JetBrains Mono', 'Consolas', monospace; color: #64748b; text-align: right; }
    .land-legend .l-hd  { color: #94a3b8; font-size: 0.66rem; text-transform: uppercase; letter-spacing: .06em; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
</style>

<a href="index.php?page=planning" class="text-muted small text-decoration-none">
    <i class="bi bi-arrow-left me-1"></i>Back to planning
</a>

<div class="plan-hero mt-2 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <span class="badge-reg">DCPR <?= e($plan['regulation_key']) ?></span>
        <h5><?= e($plan['plan_name']) ?></h5>
        <p>Status: <b><?= e(ucfirst($plan['status'])) ?></b>
           <?php if ($plan['calculated_at']): ?> · Calculated <?= e($plan['calculated_at']) ?><?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="index.php?page=planning_form&plan_id=<?= (int)$plan['id'] ?>" class="btn btn-sm btn-outline-light">
            <i class="bi bi-pencil me-1"></i>Edit / Recalculate
        </a>
        <a href="index.php?page=planning_report&plan_id=<?= (int)$plan['id'] ?>" target="_blank" class="btn btn-sm btn-light text-dark">
            <i class="bi bi-file-earmark-pdf-fill me-1"></i>Report
        </a>
        <a href="index.php?page=planning_financials_form&plan_id=<?= (int)$plan['id'] ?>" class="btn btn-sm btn-success">
            <i class="bi bi-cash-stack me-1"></i>Financial Model
        </a>
        <button type="button" class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#cloneModal">
            <i class="bi bi-diagram-2 me-1"></i>Duplicate as Scenario
        </button>
        <?php if (can('edit_proposals')):
            $scenChildRes = getDB()->query("SELECT COUNT(*) AS n FROM development_plans WHERE parent_plan_id = " . (int)$plan['id']);
            $scenChildCount = $scenChildRes ? (int)($scenChildRes->fetch_assoc()['n'] ?? 0) : 0;
            $confirmMsg = $scenChildCount > 0
                ? "Delete this plan?\\n\\nWARNING: this plan has {$scenChildCount} scenario(s) that will be deleted with it. All inputs, calculations, validations, and financial models will be permanently removed."
                : "Delete this plan?\\n\\nAll inputs, calculations, validations, and financial models will be permanently removed.";
        ?>
        <form method="POST" action="index.php?page=planning_delete" class="d-inline"
              onsubmit="return confirm('<?= $confirmMsg ?>');">
            <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-trash me-1"></i>Delete<?= $scenChildCount > 0 ? " + {$scenChildCount} scen." : '' ?>
            </button>
        </form>
        <?php endif; ?>
        <?php
        $siblingSql = "SELECT id, plan_name, scenario_label, status FROM development_plans
                       WHERE proposal_id = " . (int)$plan['proposal_id'] . "
                         AND id <> " . (int)$plan['id'] . "
                       ORDER BY created_at DESC";
        $siblings = getDB()->query($siblingSql);
        $siblings = $siblings ? $siblings->fetch_all(MYSQLI_ASSOC) : [];
        ?>
        <?php if (!empty($siblings)): ?>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-arrow-left-right me-1"></i>Compare with…
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                    <?php foreach ($siblings as $sib): ?>
                        <li>
                            <a class="dropdown-item small py-2" href="index.php?page=planning_compare&base_id=<?= (int)$plan['id'] ?>&scenario_id=<?= (int)$sib['id'] ?>">
                                <?= e($sib['scenario_label'] ?: $sib['plan_name']) ?>
                                <small class="text-muted d-block"><?= e($sib['status']) ?></small>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Clone-as-Scenario Modal -->
<div class="modal fade" id="cloneModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form method="POST" action="index.php?page=planning_clone">
            <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Duplicate as Scenario</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label small fw-semibold">Scenario Label</label>
                    <input type="text" name="scenario_label" required maxlength="80" class="form-control" placeholder='e.g. "Road 24m" or "Fungible 25%"'>
                    <div class="form-text small">This scenario will start as an exact copy of the current plan. You can then edit any inputs — including the ones that make the "what-if" different.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-diagram-2 me-1"></i>Create Scenario</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
// Helper — get a calc by key from the flat list
function calc_by_key(array $calcs, string $key): ?array {
    foreach ($calcs as $c) if ($c['calc_key'] === $key) return $c;
    return null;
}
$grossM = calc_by_key($calcs, 'gross_plot_area_sqm')['calc_value']       ?? null;
$netM   = calc_by_key($calcs, 'net_plot_area_sqm')['calc_value']         ?? null;
$rehab  = calc_by_key($calcs, 'rehab_tenement_bua_sqm')['calc_value']    ?? null;
$sale   = calc_by_key($calcs, 'sale_component_sqm')['calc_value']        ?? null;
$total  = calc_by_key($calcs, 'total_bua_approved_sqm')['calc_value']    ?? null;
$fsiS   = calc_by_key($calcs, 'fsi_sanctioned')['calc_value']            ?? null;
$fsiC   = calc_by_key($calcs, 'fsi_consumed_in_situ')['calc_value']      ?? null;
$grossSale = calc_by_key($calcs, 'gross_sale_bua_permissible_sqm')['calc_value'] ?? null;

// ─── Build chart data from calcs ─────────────────────────────────────
$land_gross_val = (float)($grossM ?? 0);
$land_ros_val   = (float)(calc_by_key($calcs, 'ros_physical_set_aside_sqm')['calc_value'] ?? 0);

$land_segments = [
    ['label' => 'DP Road (Primary)',   'value' => (float)(calc_by_key($calcs, 'dp_road_setback_primary_sqm')['calc_value']   ?? 0), 'color' => '#ef4444'],
    ['label' => 'DP Road (Secondary)', 'value' => (float)(calc_by_key($calcs, 'dp_road_setback_secondary_sqm')['calc_value'] ?? 0), 'color' => '#f97316'],
    ['label' => 'Nalla Setback',       'value' => (float)(calc_by_key($calcs, 'nalla_setback_sqm')['calc_value']              ?? 0), 'color' => '#f59e0b'],
    ['label' => 'DP Reservation',      'value' => (float)(calc_by_key($calcs, 'reservation_area_other_sqm')['calc_value']    ?? 0), 'color' => '#a855f7'],
    ['label' => 'Amenity Plot',        'value' => (float)(calc_by_key($calcs, 'amenity_plot_sqm')['calc_value']              ?? 0), 'color' => '#ec4899'],
    ['label' => 'Other Deductions',    'value' => (float)(calc_by_key($calcs, 'other_deduction_sqm')['calc_value']           ?? 0), 'color' => '#64748b'],
    ['label' => 'ROS (not deducted)',  'value' => $land_ros_val,                                                                    'color' => '#22c55e'],
    ['label' => 'Net Plot (for FSI)',  'value' => (float)($netM ?? 0),                                                              'color' => '#0ea5e9'],
];
$land_segments = array_values(array_filter($land_segments, fn($s) => $s['value'] > 0));
foreach ($land_segments as &$s) {
    $s['pct'] = $land_gross_val > 0 ? round($s['value'] / $land_gross_val * 100, 2) : 0;
}
unset($s);

$rehabAmenity = (float)(calc_by_key($calcs, 'rehab_amenity_block_sqm')['calc_value']   ?? 0);
$saleFungible = (float)(calc_by_key($calcs, 'sale_fungible_sqm')['calc_value']         ?? 0);
$rehabFungible = (float)(calc_by_key($calcs, 'rehab_fungible_sqm')['calc_value']       ?? 0);
$bua_composition = [
    ['label' => 'Rehab BUA (tenements)', 'value' => (float)($rehab ?? 0), 'color' => '#6366f1'],
    ['label' => 'Rehab Fungible (free)', 'value' => $rehabFungible,       'color' => '#818cf8'],
    ['label' => 'Amenity Block',         'value' => $rehabAmenity,        'color' => '#a78bfa'],
    ['label' => 'Sale Component',        'value' => (float)($sale ?? 0),  'color' => '#10b981'],
    ['label' => 'Sale Fungible',         'value' => $saleFungible,        'color' => '#34d399'],
];
$bua_composition = array_values(array_filter($bua_composition, fn($s) => $s['value'] > 0));

// FSI Stack: sanctioned vs consumed vs balance
$fsi_sanctioned_val = (float)($fsiS ?? 0);
$fsi_consumed_val   = (float)($fsiC ?? 0);
$fsi_balance_val    = max(0, $fsi_sanctioned_val - $fsi_consumed_val);

$overallStatus = 'COMPLIANT';
$overallColor  = 'green';
if (($validationCounts['red'] ?? 0) > 0) {
    $overallStatus = 'NON-COMPLIANT'; $overallColor = 'red';
} elseif (($validationCounts['yellow'] ?? 0) > 0) {
    $overallStatus = 'REVIEW REQUIRED'; $overallColor = 'yellow';
}
?>

<?php if (empty($calcs)): ?>
    <div class="alert alert-warning">
        No calculations yet — <a href="index.php?page=planning_form&plan_id=<?= (int)$plan['id'] ?>">fill in the inputs</a> and hit Calculate.
    </div>
<?php else: ?>

<div class="summary-strip">
    <div class="summary-card"><div class="summary-label">Gross Plot</div><div class="summary-value"><?= $grossM !== null ? number_format($grossM, 2) . ' sqm' : '—' ?></div></div>
    <div class="summary-card"><div class="summary-label">Net Plot (for FSI)</div><div class="summary-value"><?= $netM !== null ? number_format($netM, 2) . ' sqm' : '—' ?></div></div>
    <div class="summary-card"><div class="summary-label">Rehab BUA</div><div class="summary-value"><?= $rehab !== null ? number_format($rehab, 2) . ' sqm' : '—' ?></div></div>
    <div class="summary-card"><div class="summary-label">Sale Component</div><div class="summary-value"><?= $sale !== null ? number_format($sale, 2) . ' sqm' : '—' ?></div></div>
    <div class="summary-card"><div class="summary-label">Total BUA Approved</div><div class="summary-value"><?= $total !== null ? number_format($total, 2) . ' sqm' : '—' ?></div></div>
    <div class="summary-card"><div class="summary-label">FSI Sanctioned</div><div class="summary-value"><?= $fsiS !== null ? number_format($fsiS, 3) : '—' ?></div></div>
    <div class="summary-card"><div class="summary-label">FSI Consumed</div><div class="summary-value"><?= $fsiC !== null ? number_format($fsiC, 3) : '—' ?></div></div>
    <div class="summary-card"><div class="summary-label">Gross Sale Permissible</div><div class="summary-value"><?= $grossSale !== null ? number_format($grossSale, 2) . ' sqm' : '—' ?></div></div>
</div>

<?php if (!empty($land_segments)): ?>
<div class="viz-block">
    <h6><i class="section-icon bi bi-pie-chart-fill"></i>Land &amp; Development Visualization</h6>

    <div class="viz-grid donut-col">
        <div class="viz-card">
            <h6>Land Composition</h6>
            <div class="chart-wrap"><canvas id="landDonut"></canvas></div>
        </div>
        <div class="viz-card">
            <h6>Breakdown ( % of Gross Plot <?= number_format($land_gross_val, 2) ?> sqm )</h6>
            <div class="legend-wrap"><div class="land-legend">
                <div class="l-hd">Segment</div>
                <div class="l-hd l-val">sqm</div>
                <div class="l-hd l-pct">% of gross</div>
                <?php foreach ($land_segments as $s): ?>
                    <div class="l-lbl"><span class="l-dot" style="background:<?= e($s['color']) ?>"></span><?= e($s['label']) ?></div>
                    <div class="l-val"><?= number_format($s['value'], 2) ?></div>
                    <div class="l-pct"><?= number_format($s['pct'], 2) ?>%</div>
                <?php endforeach; ?>
            </div></div>
        </div>
    </div>

    <?php if (!empty($bua_composition)): ?>
    <div class="viz-grid two-col mt-3">
        <div class="viz-card">
            <h6>BUA Composition ( sqm )</h6>
            <div class="chart-wrap"><canvas id="buaBar"></canvas></div>
        </div>
        <div class="viz-card">
            <h6>FSI Stack — Sanctioned vs Consumed vs Balance</h6>
            <div class="chart-wrap"><canvas id="fsiBar"></canvas></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const landSegments   = <?= json_encode($land_segments) ?>;
    const buaComposition = <?= json_encode($bua_composition) ?>;
    const fsiSanctioned  = <?= json_encode($fsi_sanctioned_val) ?>;
    const fsiConsumed    = <?= json_encode($fsi_consumed_val) ?>;
    const fsiBalance     = <?= json_encode($fsi_balance_val) ?>;

    const commonOpts = {
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function (ctx) {
                        const v = ctx.raw;
                        return ctx.label + ': ' + (v).toLocaleString(undefined, {maximumFractionDigits: 2});
                    }
                }
            }
        }
    };

    // Land Composition Donut
    if (landSegments.length && document.getElementById('landDonut')) {
        new Chart(document.getElementById('landDonut'), {
            type: 'doughnut',
            data: {
                labels: landSegments.map(s => s.label),
                datasets: [{
                    data: landSegments.map(s => s.value),
                    backgroundColor: landSegments.map(s => s.color),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: Object.assign({}, commonOpts, {
                cutout: '58%',
                plugins: Object.assign({}, commonOpts.plugins, {
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = total > 0 ? (ctx.raw / total * 100) : 0;
                                return ctx.label + ': ' + ctx.raw.toLocaleString(undefined, {maximumFractionDigits: 2}) + ' sqm  (' + pct.toFixed(2) + '%)';
                            }
                        }
                    }
                })
            })
        });
    }

    // BUA Composition Bar
    if (buaComposition.length && document.getElementById('buaBar')) {
        new Chart(document.getElementById('buaBar'), {
            type: 'bar',
            data: {
                labels: buaComposition.map(s => s.label),
                datasets: [{
                    data: buaComposition.map(s => s.value),
                    backgroundColor: buaComposition.map(s => s.color),
                    borderRadius: 6,
                    borderWidth: 0
                }]
            },
            options: Object.assign({}, commonOpts, {
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { callback: v => v.toLocaleString() }
                    },
                    y: { grid: { display: false } }
                }
            })
        });
    }

    // FSI Stack — grouped horizontal bar
    if (document.getElementById('fsiBar') && (fsiSanctioned > 0 || fsiConsumed > 0)) {
        new Chart(document.getElementById('fsiBar'), {
            type: 'bar',
            data: {
                labels: ['Sanctioned', 'Consumed (in-situ)', 'Balance → TDR'],
                datasets: [{
                    data: [fsiSanctioned, fsiConsumed, fsiBalance],
                    backgroundColor: ['#4f46e5', '#0ea5e9', '#f59e0b'],
                    borderRadius: 6,
                    borderWidth: 0
                }]
            },
            options: Object.assign({}, commonOpts, {
                indexAxis: 'y',
                scales: {
                    x: { beginAtZero: true, suggestedMax: Math.max(4.5, fsiSanctioned * 1.1) },
                    y: { grid: { display: false } }
                },
                plugins: Object.assign({}, commonOpts.plugins, {
                    tooltip: {
                        callbacks: {
                            label: ctx => ctx.label + ': ' + ctx.raw.toFixed(3) + ' FSI'
                        }
                    }
                })
            })
        });
    }
})();
</script>
<?php endif; ?>

<?php if (!empty($validations)): ?>
<div class="val-panel">
    <div class="val-header">
        <div>
            <div class="val-title">Regulatory Validation — Overall: <span class="val-pill <?= e($overallColor) ?>"><?= e($overallStatus) ?></span></div>
        </div>
        <div class="val-counters">
            <span class="val-pill red">🔴 <?= (int)$validationCounts['red'] ?> blocker<?= $validationCounts['red'] === 1 ? '' : 's' ?></span>
            <span class="val-pill yellow">🟡 <?= (int)$validationCounts['yellow'] ?> warning<?= $validationCounts['yellow'] === 1 ? '' : 's' ?></span>
            <span class="val-pill green">🟢 <?= (int)$validationCounts['green'] ?> passed</span>
            <span class="val-pill total"><?= (int)count($validations) ?> total</span>
            <button type="button" class="val-toggle" onclick="document.getElementById('val-list').classList.toggle('collapsed')">Toggle passed</button>
        </div>
    </div>
    <div class="val-list <?= ($validationCounts['red'] === 0 && $validationCounts['yellow'] === 0) ? '' : 'collapsed' ?>" id="val-list">
        <?php foreach ($validations as $v):
            $isFail = $v['status'] !== 'green';
            $icon = $v['status'] === 'red' ? '✕' : ($v['status'] === 'yellow' ? '!' : '✓');
        ?>
            <div class="val-row <?= e($v['status']) ?>">
                <div class="val-icon <?= e($v['status']) ?>"><?= e($icon) ?></div>
                <div>
                    <div class="val-label"><?= e($v['check_label']) ?></div>
                    <div class="val-meta">
                        <?php if (!empty($v['regulation_ref'])): ?>
                            <span class="val-cite"><?= e($v['regulation_ref']) ?></span>
                        <?php endif; ?>
                        <?= e($v['message'] ?? '') ?>
                    </div>
                    <?php if ($isFail && !empty($v['recommended_action'])): ?>
                        <div class="val-action <?= e($v['status']) ?>">
                            <strong>Recommended:</strong> <?= e($v['recommended_action']) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="val-numbers">
                    <?php if ($v['entered_value'] !== null && $v['entered_value'] !== ''): ?>
                        Entered: <b><?= e($v['entered_value']) ?></b><br>
                    <?php endif; ?>
                    <?php if ($v['required_value'] !== null && $v['required_value'] !== ''): ?>
                        Required: <b><?= e($v['required_value']) ?></b><br>
                    <?php endif; ?>
                    <?php if ($v['delta'] !== null && $v['delta'] !== ''): ?>
                        Δ <span style="color:<?= $v['status']==='red' ? '#dc2626' : ($v['status']==='yellow' ? '#f59e0b' : '#16a34a') ?>"><?= e($v['delta']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php
$groupTitles = [
    'land'     => ['title' => '1. Land Breakdown',            'icon' => 'bi-geo-alt-fill'],
    'rehab'    => ['title' => '2. Rehabilitation Component',  'icon' => 'bi-house-heart-fill'],
    'sale'     => ['title' => '3. Sale Component',            'icon' => 'bi-cash-stack'],
    'fsi'      => ['title' => '4. FSI (Sanctioned / In-Situ)', 'icon' => 'bi-graph-up'],
    'totals'   => ['title' => '5. Totals',                    'icon' => 'bi-check2-all'],
    'fungible' => ['title' => '6. Fungible',                  'icon' => 'bi-plus-square'],
    'tdr'      => ['title' => '7. TDR',                       'icon' => 'bi-arrow-left-right'],
];
?>

<?php foreach ($groupTitles as $groupKey => $meta): ?>
    <?php if (empty($grouped[$groupKey])) continue; ?>
    <div class="section">
        <h6><i class="section-icon <?= e($meta['icon']) ?>"></i><?= e($meta['title']) ?></h6>
        <table class="calc-table">
            <thead>
                <tr>
                    <th style="width: 45%">Parameter</th>
                    <th style="width: 25%; text-align:right">Value</th>
                    <th style="width: 30%">Basis</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grouped[$groupKey] as $c): ?>
                    <tr>
                        <td>
                            <div class="calc-label"><?= e($c['calc_label']) ?></div>
                            <?php if (!empty($c['formula'])): ?>
                                <div class="calc-formula"><?= e($c['formula']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="calc-value">
                            <?= is_numeric($c['calc_value']) ? number_format((float)$c['calc_value'], ($c['unit'] === 'ratio' || $c['unit'] === 'per ha') ? 3 : 2) : e($c['calc_value']) ?>
                            <span class="calc-unit"><?= e($c['unit']) ?></span>
                        </td>
                        <td>
                            <?php if (!empty($c['regulation_ref'])): ?>
                                <div class="calc-regref"><?= e($c['regulation_ref']) ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endforeach; ?>

<?php endif; ?>

<!-- ─── AI Regulatory Assistant ─────────────────────────────────────── -->
<button class="ai-fab" id="aiFab" onclick="document.getElementById('aiPanel').classList.add('open'); document.getElementById('aiFab').style.display='none'; document.getElementById('aiInput').focus();">
    <i class="bi bi-stars"></i> Ask the DCPR Advisor
</button>

<div class="ai-panel" id="aiPanel">
    <div class="ai-head">
        <div>
            <h6>DCPR Regulatory Advisor</h6>
            <div class="ai-sub">Grounded on this plan + DCPR 2034 rule base</div>
        </div>
        <button class="ai-close" onclick="document.getElementById('aiPanel').classList.remove('open'); document.getElementById('aiFab').style.display='inline-flex';">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="ai-body" id="aiBody">
        <div class="ai-empty">
            <i class="bi bi-chat-square-dots"></i>
            Ask about deductions, FSI, rehab BUA, sale ratio, TDR, or "what-if" scenarios.<br>
            Every answer cites the applicable DCPR clause.
        </div>
    </div>
    <div class="ai-suggest">
        <button type="button" onclick="aiSend(this.textContent)">Why is the sale component 1.15× rehab?</button>
        <button type="button" onclick="aiSend(this.textContent)">How was the rehab BUA calculated?</button>
        <button type="button" onclick="aiSend(this.textContent)">Can we consume more than 4.0 FSI in-situ?</button>
        <button type="button" onclick="aiSend(this.textContent)">What does the FSI Sanctioned > 4.0 warning mean?</button>
    </div>
    <div class="ai-input">
        <textarea id="aiInput" placeholder="Ask a question about this plan…" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();document.getElementById('aiSend').click();}"></textarea>
        <button class="ai-send" id="aiSend" onclick="aiSend(document.getElementById('aiInput').value)">Send</button>
    </div>
</div>

<script>
(function () {
    const PLAN_ID = <?= (int)$plan['id'] ?>;
    const history = [];

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
    }

    function highlightCitations(text) {
        return escapeHtml(text).replace(
            /\b(Reg\s*\d+(?:\([A-Za-z0-9]+\))*|\d+\([A-Za-z0-9]+\)(?:\([A-Za-z0-9]+\))*)/g,
            '<span class="cite">$1</span>'
        );
    }

    function appendMsg(role, text, opts) {
        const body = document.getElementById('aiBody');
        // Remove empty state placeholder on first message
        const empty = body.querySelector('.ai-empty');
        if (empty) empty.remove();

        const msg = document.createElement('div');
        msg.className = 'ai-msg ' + role;

        if (role === 'assistant') {
            msg.innerHTML = highlightCitations(text).replace(/\n/g, '<br>');
            if (opts && opts.citations && opts.citations.length) {
                const cb = document.createElement('div');
                cb.className = 'cite-block';
                cb.innerHTML = 'Cited: ' + opts.citations.map(c => '<span class="cite">' + escapeHtml(c) + '</span>').join(' ');
                msg.appendChild(cb);
            }
            if (opts && opts.warnings && opts.warnings.length) {
                opts.warnings.forEach(w => {
                    const warn = document.createElement('div');
                    warn.className = 'ai-warn';
                    warn.textContent = '⚠ ' + w;
                    msg.appendChild(warn);
                });
            }
        } else {
            msg.textContent = text;
        }
        body.appendChild(msg);
        body.scrollTop = body.scrollHeight;
    }

    window.aiSend = async function (raw) {
        const text = (raw || '').trim();
        if (!text) return;
        const btn = document.getElementById('aiSend');
        const input = document.getElementById('aiInput');
        appendMsg('user', text);
        history.push({role: 'user', text: text});
        input.value = '';
        btn.disabled = true; btn.textContent = 'Thinking…';

        const loading = document.createElement('div');
        loading.className = 'ai-msg assistant';
        loading.id = 'aiLoading';
        loading.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Consulting DCPR rule base…';
        document.getElementById('aiBody').appendChild(loading);

        try {
            const res = await fetch('index.php?page=api_planning_assistant', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({plan_id: PLAN_ID, question: text, history: history})
            });
            const data = await res.json();
            document.getElementById('aiLoading')?.remove();

            if (data.success && data.answer) {
                appendMsg('assistant', data.answer, {citations: data.citations, warnings: data.warnings});
                history.push({role: 'assistant', text: data.answer});
            } else {
                appendMsg('assistant', 'Error: ' + (data.error || 'unknown error'));
            }
        } catch (err) {
            document.getElementById('aiLoading')?.remove();
            appendMsg('assistant', 'Network error: ' + err.message);
        } finally {
            btn.disabled = false; btn.textContent = 'Send';
            input.focus();
        }
    };
})();
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
