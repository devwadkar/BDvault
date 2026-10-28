<?php require __DIR__ . '/_wizard_shell.php'; ?>
<?php
$s2 = $draft['step_2'] ?? [];
$val = function($k, $default = '') use ($s2) { return htmlspecialchars((string)($s2[$k] ?? $default), ENT_QUOTES); };

$roads = $s2['roads'] ?? null;
if (!is_array($roads) || empty($roads['area'] ?? [])) {
    $roads = ['width' => [$s2['primary_road_width_m'] ?? ''], 'area' => [$s2['road_deduction_sqm'] ?? '']];
}
$roadCount = max(1, count($roads['area']));

$named = $s2['named'] ?? null;
if (!is_array($named) || empty($named['area'] ?? [])) {
    $legacyOther = (float)($s2['other_deduction_sqm'] ?? 0);
    $named = [
        'label'   => [$legacyOther > 0 ? 'Nalla Setback' : ''],
        'area'    => [$legacyOther > 0 ? (string)$legacyOther : ''],
        'include' => [$legacyOther > 0 ? '1' : ''],
    ];
}
$namedCount = max(1, count($named['area']));
$reservIncluded = !empty($s2['reservation_included_in_total']);
?>
<?php _aa_shell_open(); ?>

<style>
    /* Step 2 dynamic tables */
    .din-tbl { width: 100%; font-size: 0.85rem; border-collapse: separate; border-spacing: 0; }
    .din-tbl th { color: var(--aa-muted); font-weight: 600; text-transform: uppercase; font-size: 0.68rem; letter-spacing: 0.04em; padding: 8px 6px; text-align: left; }
    .din-tbl td { padding: 4px 4px 12px; vertical-align: middle; }
    .din-tbl .form-control, .din-tbl .form-select { font-size: 0.88rem; padding: 6px 10px; }
    .din-tbl .input-group-text { font-size: 0.78rem; padding: 6px 10px; }
    .add-row-btn { background: transparent; color: var(--aa-primary); border: 1px dashed #c7d2fe; padding: 8px 16px; border-radius: 8px; font-size: 0.8rem; font-weight: 600; cursor: pointer; }
    .add-row-btn:hover { background: #eef2ff; border-color: var(--aa-primary); border-style: solid; }
    .rm-btn { color: var(--aa-danger); background: transparent; border: 0; padding: 6px 10px; cursor: pointer; border-radius: 6px; font-size: 0.9rem; }
    .rm-btn:hover { background: #fee2e2; }
    .cbx-cell { text-align: center; vertical-align: middle !important; padding-top: 12px !important; }
</style>

<div class="aa-card">
    <h5 class="aa-card-title">Plot &amp; land</h5>
    <p class="aa-card-lead">Enter the plot area, all road setbacks, any reservation and amenity handover. Net plot updates as you type.</p>

    <form method="POST" action="index.php?page=area_analysis_save_step" id="s2Form">
        <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
        <input type="hidden" name="step" value="2">

        <div class="aa-group-title">Plot</div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Gross plot area <span class="req">*</span></label>
                <div class="input-group">
                    <input type="number" step="any" name="gross_plot_area_sqm" id="fld_gross" class="form-control s2-recalc" value="<?= $val('gross_plot_area_sqm') ?>" required>
                    <span class="input-group-text">sqm</span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Property card area</label>
                <div class="input-group">
                    <input type="number" step="any" name="property_card_area_sqm" class="form-control" value="<?= $val('property_card_area_sqm') ?>">
                    <span class="input-group-text">sqm</span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Primary road width</label>
                <div class="input-group">
                    <input type="number" step="any" name="primary_road_width_m" class="form-control" value="<?= $val('primary_road_width_m') ?>">
                    <span class="input-group-text">m</span>
                </div>
            </div>
        </div>

        <div class="aa-group-title">Road setbacks</div>
        <table class="din-tbl" id="roadsTbl">
            <thead><tr><th style="width: 22%">Width</th><th style="width: 68%">Deduction area</th><th style="width: 10%"></th></tr></thead>
            <tbody id="roadsBody">
                <?php for ($i = 0; $i < $roadCount; $i++): ?>
                    <tr class="road-row">
                        <td>
                            <div class="input-group">
                                <input type="number" step="any" name="roads[width][]" class="form-control s2-recalc" value="<?= htmlspecialchars((string)($roads['width'][$i] ?? ''), ENT_QUOTES) ?>" placeholder="e.g. 27.45">
                                <span class="input-group-text">m</span>
                            </div>
                        </td>
                        <td>
                            <div class="input-group">
                                <input type="number" step="any" name="roads[area][]" class="form-control s2-recalc road-area" value="<?= htmlspecialchars((string)($roads['area'][$i] ?? ''), ENT_QUOTES) ?>" placeholder="0">
                                <span class="input-group-text">sqm</span>
                            </div>
                        </td>
                        <td><button type="button" class="rm-btn" onclick="rmRoad(this)"><i class="bi bi-trash"></i></button></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
        <button type="button" class="add-row-btn" onclick="addRoad()"><i class="bi bi-plus-lg me-1"></i>Add road</button>

        <div class="aa-group-title">Reservation</div>
        <p class="form-hint mb-3">Green Belt / ROS / public amenity reservation. Usually retains FSI (leave the checkbox off).</p>
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label">Sub-label</label>
                <input type="text" name="reservation_sub_label" class="form-control" value="<?= $val('reservation_sub_label') ?>" placeholder="e.g. Green Belt / ROS 2.7">
            </div>
            <div class="col-md-4">
                <label class="form-label">Area</label>
                <div class="input-group">
                    <input type="number" step="any" name="reservation_area_sqm" class="form-control s2-recalc" id="fld_reservation" value="<?= $val('reservation_area_sqm', '0') ?>">
                    <span class="input-group-text">sqm</span>
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check pb-2">
                    <input class="form-check-input s2-recalc" type="checkbox" name="reservation_included_in_total" id="fld_reservIncluded" value="1" <?= $reservIncluded ? 'checked' : '' ?>>
                    <label class="form-check-label" for="fld_reservIncluded" style="font-size: 0.85rem;">Deduct from net plot</label>
                </div>
            </div>
        </div>

        <div class="aa-group-title">Amenity handover · Reg 14(A)</div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Amenity plot area</label>
                <div class="input-group">
                    <input type="number" step="any" name="amenity_area_sqm" class="form-control s2-recalc" id="fld_amenity" value="<?= $val('amenity_area_sqm', '0') ?>">
                    <span class="input-group-text">sqm</span>
                </div>
                <div class="form-hint">Always subtracted from Net Plot.</div>
            </div>
        </div>

        <div class="aa-group-title">Other deductions</div>
        <p class="form-hint mb-3">Nalla setback, CRZ, Forest, Defence, etc. Tick to subtract from Net Plot.</p>
        <table class="din-tbl" id="namedTbl">
            <thead><tr><th style="width: 40%">Label</th><th style="width: 30%">Area</th><th style="width: 20%">Deduct?</th><th style="width: 10%"></th></tr></thead>
            <tbody id="namedBody">
                <?php for ($i = 0; $i < $namedCount; $i++): ?>
                    <tr class="named-row">
                        <td><input type="text" name="named[label][]" class="form-control" value="<?= htmlspecialchars((string)($named['label'][$i] ?? ''), ENT_QUOTES) ?>" placeholder="e.g. Nalla Setback"></td>
                        <td>
                            <div class="input-group">
                                <input type="number" step="any" name="named[area][]" class="form-control s2-recalc named-area" value="<?= htmlspecialchars((string)($named['area'][$i] ?? ''), ENT_QUOTES) ?>" placeholder="0">
                                <span class="input-group-text">sqm</span>
                            </div>
                        </td>
                        <td class="cbx-cell">
                            <input type="checkbox" name="named[include][<?= $i ?>]" class="form-check-input s2-recalc named-inc" value="1" <?= !empty($named['include'][$i]) ? 'checked' : '' ?>>
                        </td>
                        <td><button type="button" class="rm-btn" onclick="rmNamed(this)"><i class="bi bi-trash"></i></button></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
        <button type="button" class="add-row-btn" onclick="addNamed()"><i class="bi bi-plus-lg me-1"></i>Add deduction</button>

        <div class="aa-derived" id="netBox">
            <div>
                <div class="lbl">Net plot area (for FSI)</div>
                <div class="val" id="netVal">— sqm</div>
                <div class="sub" id="netFormula">Gross − (Roads + Amenity + Deducted items)</div>
            </div>
            <div style="text-align: right;">
                <div class="lbl">Total deductions</div>
                <div class="val val-sec" id="dedVal">— sqm</div>
            </div>
        </div>

        <div class="aa-group-title">Plot character</div>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">No. of parcels</label>
                <input type="number" name="num_parcels" class="form-control" value="<?= $val('num_parcels', '1') ?>" min="1">
            </div>
            <div class="col-md-3">
                <label class="form-label">Amalgamation</label>
                <select name="amalgamation_status" class="form-select">
                    <?php $amalg = $s2['amalgamation_status'] ?? 'single'; foreach (['single'=>'Single plot','amalgamated'=>'Amalgamated','subdivided'=>'Sub-divided'] as $k => $l): ?>
                        <option value="<?= $k ?>" <?= $amalg === $k ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">DP road</label>
                <select name="has_dp_road" class="form-select">
                    <?php $dp = $s2['has_dp_road'] ?? 'no'; foreach (['no'=>'None','existing'=>'Existing','proposed'=>'Proposed'] as $k => $l): ?>
                        <option value="<?= $k ?>" <?= $dp === $k ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Existing reservation</label>
                <select name="has_reservation" class="form-select">
                    <?php $rv = $s2['has_reservation'] ?? 'no'; foreach (['no'=>'No','yes'=>'Yes'] as $k => $l): ?>
                        <option value="<?= $k ?>" <?= $rv === $k ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php _aa_nav_bar($statement, $step); ?>
    </form>
</div>

<script>
(function () {
    function num(el) { const v = parseFloat(el?.value || 0); return isNaN(v) ? 0 : v; }
    function fmt(n) { return n.toLocaleString('en-IN', {maximumFractionDigits: 2, minimumFractionDigits: 2}); }

    window.recalcNet = function () {
        const gross = num(document.getElementById('fld_gross'));
        let ded = 0;
        document.querySelectorAll('.road-area').forEach(el => ded += num(el));
        ded += num(document.getElementById('fld_amenity'));
        document.querySelectorAll('.named-row').forEach(row => {
            if (row.querySelector('.named-inc')?.checked) ded += num(row.querySelector('.named-area'));
        });
        if (document.getElementById('fld_reservIncluded')?.checked) ded += num(document.getElementById('fld_reservation'));
        const net = Math.max(0, gross - ded);
        document.getElementById('netVal').textContent = fmt(net) + ' sqm';
        document.getElementById('dedVal').textContent = '− ' + fmt(ded) + ' sqm';
        document.getElementById('netFormula').textContent = fmt(gross) + ' − ' + fmt(ded) + ' = ' + fmt(net);
    };

    function wireRecalc(root = document) {
        root.querySelectorAll('.s2-recalc').forEach(el => {
            el.addEventListener('input', recalcNet);
            el.addEventListener('change', recalcNet);
        });
    }
    window.addRoad = function () {
        const tr = document.createElement('tr');
        tr.className = 'road-row';
        tr.innerHTML = `
            <td><div class="input-group"><input type="number" step="any" name="roads[width][]" class="form-control s2-recalc" placeholder="e.g. 24"><span class="input-group-text">m</span></div></td>
            <td><div class="input-group"><input type="number" step="any" name="roads[area][]" class="form-control s2-recalc road-area" placeholder="0"><span class="input-group-text">sqm</span></div></td>
            <td><button type="button" class="rm-btn" onclick="rmRoad(this)"><i class="bi bi-trash"></i></button></td>`;
        document.getElementById('roadsBody').appendChild(tr); wireRecalc(tr); recalcNet();
    };
    window.rmRoad = function (btn) {
        const rows = document.querySelectorAll('.road-row');
        if (rows.length <= 1) btn.closest('tr').querySelectorAll('input').forEach(i => i.value = '');
        else btn.closest('tr').remove();
        recalcNet();
    };
    window.addNamed = function () {
        const idx = document.getElementById('namedBody').children.length;
        const tr = document.createElement('tr'); tr.className = 'named-row';
        tr.innerHTML = `
            <td><input type="text" name="named[label][]" class="form-control" placeholder="e.g. Nalla Setback"></td>
            <td><div class="input-group"><input type="number" step="any" name="named[area][]" class="form-control s2-recalc named-area" placeholder="0"><span class="input-group-text">sqm</span></div></td>
            <td class="cbx-cell"><input type="checkbox" name="named[include][${idx}]" class="form-check-input s2-recalc named-inc" value="1" checked></td>
            <td><button type="button" class="rm-btn" onclick="rmNamed(this)"><i class="bi bi-trash"></i></button></td>`;
        document.getElementById('namedBody').appendChild(tr); wireRecalc(tr); recalcNet();
    };
    window.rmNamed = function (btn) {
        const rows = document.querySelectorAll('.named-row');
        if (rows.length <= 1) btn.closest('tr').querySelectorAll('input').forEach(i => { if (i.type === 'checkbox') i.checked = false; else i.value = ''; });
        else btn.closest('tr').remove();
        recalcNet();
    };
    wireRecalc(); recalcNet();
})();
</script>

<?php _aa_shell_close(); ?>
