<?php require __DIR__ . '/_wizard_shell.php'; ?>
<?php
$s3 = $draft['step_3'] ?? [];
$val = function($k, $default = '') use ($s3) { return htmlspecialchars((string)($s3[$k] ?? $default), ENT_QUOTES); };

$buildings = $extra['buildings'] ?? [];
if (empty($buildings)) {
    $buildings = [[
        'building_label' => '', 'residential_units' => '', 'commercial_units' => '',
        'residential_carpet_sqm' => '', 'commercial_carpet_sqm' => '',
        'existing_bua_sqm' => '', 'existing_fsi_consumed' => '',
        'tenant_count' => '', 'eligible_tenant_count' => '',
    ]];
}
$isRedev = in_array($statement['development_type'] ?? '', ['redevelopment','sra','mhada','society_redevelopment','cluster'], true);
$reg = $statement['selected_regulation_key'] ?? '';
$defCarpetRes = match ($reg) { '33(5)' => 35.00, default => 27.88 };
$defCarpetCom = 20.90;
$defLoading   = 1.15;

$sumTenants = 0; $sumEligible = 0; $sumBua = 0.0;
foreach ($buildings as $b) {
    $sumTenants  += (int)($b['tenant_count'] ?? 0);
    $sumEligible += (int)($b['eligible_tenant_count'] ?? 0);
    $sumBua      += (float)($b['existing_bua_sqm'] ?? 0);
}
?>
<?php _aa_shell_open(); ?>

<style>
    .bldg-tbl { width: 100%; font-size: 0.85rem; border-collapse: separate; border-spacing: 0; }
    .bldg-tbl th { color: var(--aa-muted); font-weight: 600; text-transform: uppercase; font-size: 0.66rem; letter-spacing: 0.04em; padding: 8px 6px; text-align: left; border-bottom: 1px solid var(--aa-border); }
    .bldg-tbl td { padding: 4px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .bldg-tbl input { width: 100%; padding: 6px 8px; border: 1px solid var(--aa-border); border-radius: 6px; font-size: 0.82rem; font-family: inherit; }
    .bldg-tbl input[type=number] { font-family: 'JetBrains Mono', Consolas, monospace; }
    .bldg-tbl input:focus { outline: none; border-color: var(--aa-primary); box-shadow: 0 0 0 2px rgba(79,70,229,0.1); }
    .add-row-btn { background: transparent; color: var(--aa-primary); border: 1px dashed #c7d2fe; padding: 8px 16px; border-radius: 8px; font-size: 0.8rem; font-weight: 600; cursor: pointer; }
    .add-row-btn:hover { background: #eef2ff; border-color: var(--aa-primary); border-style: solid; }
    .rm-btn { color: var(--aa-danger); background: transparent; border: 0; padding: 6px 10px; cursor: pointer; border-radius: 6px; }
    .rm-btn:hover { background: #fee2e2; }
    .totals-row { background: #ecfdf5; }
    .totals-row td { color: #065f46; font-weight: 700; font-family: 'JetBrains Mono', Consolas, monospace; padding: 10px 6px; }
    .totals-row td:first-child { font-family: inherit; text-transform: uppercase; font-size: 0.68rem; letter-spacing: 0.04em; }
    .roster-toggle { background: transparent; border: 0; color: var(--aa-primary); font-weight: 600; font-size: 0.85rem; cursor: pointer; padding: 8px 0; }
    .roster-toggle:hover { text-decoration: underline; }
    .roster-body { display: none; margin-top: 8px; }
    .roster-body.open { display: block; }
</style>

<div class="aa-card">
    <h5 class="aa-card-title">Existing development</h5>
    <p class="aa-card-lead">
        <?php if ($isRedev): ?>
            Enter tenement count + carpet + loading factor. That drives the Rehab BUA. Per-building detail is optional (roster below).
        <?php else: ?>
            Skip this step for a vacant plot, or fill the roster if there's any existing structure.
        <?php endif; ?>
    </p>

    <form method="POST" action="index.php?page=area_analysis_save_step" id="s3Form">
        <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
        <input type="hidden" name="step" value="3">

        <div class="aa-group-title">Rehab drivers</div>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Eligible tenements <span class="req">*</span></label>
                <input type="number" name="s3_eligible_total" id="fld_eligible" class="form-control s3-recalc" value="<?= $val('s3_eligible_total', (string)$sumEligible) ?>" min="0" required>
                <div class="form-hint">Roster sum: <span id="sumFromRoster"><?= (int)$sumEligible ?></span></div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Carpet · residential</label>
                <div class="input-group">
                    <input type="number" step="any" name="s3_carpet_res_sqm" id="fld_carpetRes" class="form-control s3-recalc" value="<?= $val('s3_carpet_res_sqm', (string)$defCarpetRes) ?>">
                    <span class="input-group-text">sqm</span>
                </div>
                <div class="form-hint">SRA floor: 27.88 · MHADA: 35.00</div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Carpet · commercial</label>
                <div class="input-group">
                    <input type="number" step="any" name="s3_carpet_com_sqm" id="fld_carpetCom" class="form-control s3-recalc" value="<?= $val('s3_carpet_com_sqm', (string)$defCarpetCom) ?>">
                    <span class="input-group-text">sqm</span>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Loading factor</label>
                <input type="number" step="0.01" name="s3_loading_factor" id="fld_loading" class="form-control s3-recalc" value="<?= $val('s3_loading_factor', (string)$defLoading) ?>">
                <div class="form-hint">1.15 older · 1.28 modern · 1.32 luxury</div>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-6">
                <label class="form-label">Amenity area (Balwadi · W-C · Office · Passage)</label>
                <div class="input-group">
                    <input type="number" step="any" name="s3_amenity_built_sqm" id="fld_amenityBuilt" class="form-control s3-recalc" value="<?= $val('s3_amenity_built_sqm', '') ?>" placeholder="Auto = 38% of Rehab BUA">
                    <span class="input-group-text">sqm</span>
                </div>
                <div class="form-hint">Row 10 of the feasibility. Blank = 38% of Rehab BUA (SRA default).</div>
            </div>
        </div>

        <div class="aa-derived" id="rehabPreview">
            <div>
                <div class="lbl">Rehab BUA (Row 9)</div>
                <div class="val" id="prevBua">— sqm</div>
                <div class="sub" id="prevBuaFormula">tenements × carpet × loading</div>
            </div>
            <div style="text-align: center;">
                <div class="lbl">+ Amenity (Row 10)</div>
                <div class="val val-sec" id="prevAmenity" style="color: #065f46; font-size: 1.05rem;">— sqm</div>
                <div class="sub" id="prevAmenityFormula">38% of rehab (default)</div>
            </div>
            <div style="text-align: right;">
                <div class="lbl">= Rehab Component (Row 11)</div>
                <div class="val" id="prevRehabComp">— sqm</div>
                <div class="sub" id="prevExistBua" style="color: #92400e;">Existing BUA (roster): <?= number_format($sumBua, 2) ?> sqm</div>
            </div>
        </div>

        <div class="aa-group-title">Building roster</div>
        <button type="button" class="roster-toggle" onclick="document.getElementById('rosterBody').classList.toggle('open'); const i = this.querySelector('i'); i.classList.toggle('bi-chevron-right'); i.classList.toggle('bi-chevron-down');">
            <i class="bi bi-chevron-right"></i> Expand roster (<?= count($buildings) ?> row<?= count($buildings) !== 1 ? 's' : '' ?>)
        </button>
        <div class="roster-body" id="rosterBody">
            <p class="form-hint mb-3">Optional per-building detail for the audit trail — display only, no math impact.</p>
            <div style="overflow-x: auto;">
                <table class="bldg-tbl" id="bldgTbl">
                    <thead>
                        <tr>
                            <th style="width: 15%">Building</th>
                            <th style="width: 10%">Res Units</th>
                            <th style="width: 10%">Com Units</th>
                            <th style="width: 14%">Res Carpet</th>
                            <th style="width: 14%">Com Carpet</th>
                            <th style="width: 14%">Existing BUA</th>
                            <th style="width: 8%">Tenants</th>
                            <th style="width: 10%">Eligible</th>
                            <th style="width: 5%"></th>
                        </tr>
                    </thead>
                    <tbody id="bldgBody">
                        <?php foreach ($buildings as $i => $b): ?>
                            <tr class="bldg-row">
                                <td><input type="text" name="buildings[label][]" value="<?= e($b['building_label']) ?>" placeholder="Bldg <?= $i+1 ?>"></td>
                                <td><input type="number" name="buildings[res_units][]" class="b-num b-ru" value="<?= e($b['residential_units']) ?>" min="0"></td>
                                <td><input type="number" name="buildings[com_units][]" class="b-num b-cu" value="<?= e($b['commercial_units']) ?>" min="0"></td>
                                <td><input type="number" step="any" name="buildings[res_carpet][]" value="<?= e($b['residential_carpet_sqm']) ?>"></td>
                                <td><input type="number" step="any" name="buildings[com_carpet][]" value="<?= e($b['commercial_carpet_sqm']) ?>"></td>
                                <td><input type="number" step="any" name="buildings[bua][]" class="b-num b-b" value="<?= e($b['existing_bua_sqm']) ?>"></td>
                                <td><input type="number" name="buildings[tenants][]" class="b-num b-t" value="<?= e($b['tenant_count']) ?>" min="0"></td>
                                <td><input type="number" name="buildings[eligible][]" class="b-num b-e" value="<?= e($b['eligible_tenant_count']) ?>" min="0"></td>
                                <td><button type="button" class="rm-btn" onclick="rmRow(this)"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="totals-row">
                            <td>Totals</td>
                            <td id="tot_ru">0</td><td id="tot_cu">0</td>
                            <td>—</td><td>—</td>
                            <td id="tot_b">0.00</td>
                            <td id="tot_t">0</td><td id="tot_e">0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <button type="button" class="add-row-btn mt-2" onclick="addRow()"><i class="bi bi-plus-lg me-1"></i>Add building</button>

            <div class="form-check mt-3">
                <input type="checkbox" name="s3_roster_autofills_eligible" id="fld_rosterAuto" class="form-check-input" value="1" <?= !empty($s3['s3_roster_autofills_eligible']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="fld_rosterAuto" style="font-size: 0.85rem;">Auto-copy roster total → Eligible tenements on save</label>
            </div>
        </div>

        <div class="aa-group-title">Source &amp; audit</div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Tenement count source</label>
                <select name="s3_tenement_source" class="form-select">
                    <?php $src = $s3['s3_tenement_source'] ?? ''; foreach ([
                        '' => 'Select…',
                        'photo_pass_register' => 'Photo Pass Register',
                        'annexure_ii' => 'Annexure II to DA',
                        'sra_enumeration' => 'SRA Enumeration Survey',
                        'developer_estimate' => 'Developer Estimate',
                        'other' => 'Other',
                    ] as $k => $l): ?>
                        <option value="<?= e($k) ?>" <?= $src === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Source reference</label>
                <input type="text" name="s3_source_ref" class="form-control" value="<?= $val('s3_source_ref') ?>" placeholder="e.g. PPR issued 15-Mar-2024 by SRA-AC Ghatkopar">
            </div>
            <div class="col-md-3">
                <label class="form-label">Cut-off date</label>
                <input type="date" name="s3_cutoff_date" class="form-control" value="<?= $val('s3_cutoff_date', '2000-01-01') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Ineligible tenements</label>
                <input type="number" name="s3_ineligible_count" class="form-control" value="<?= $val('s3_ineligible_count', '0') ?>" min="0">
                <div class="form-hint">Rehab burden without incentive FSI.</div>
            </div>
        </div>

        <?php _aa_nav_bar($statement, $step); ?>
    </form>
</div>

<script>
(function () {
    function num(el) { const v = parseFloat(el?.value || 0); return isNaN(v) ? 0 : v; }
    function fmt(n, d = 2) { return n.toLocaleString('en-IN', {maximumFractionDigits: d, minimumFractionDigits: d}); }

    window.recalcTotals = function () {
        let ru = 0, cu = 0, b = 0, t = 0, e = 0;
        document.querySelectorAll('.bldg-row').forEach(r => {
            ru += num(r.querySelector('.b-ru')); cu += num(r.querySelector('.b-cu'));
            b  += num(r.querySelector('.b-b'));  t  += num(r.querySelector('.b-t'));
            e  += num(r.querySelector('.b-e'));
        });
        document.getElementById('tot_ru').textContent = fmt(ru, 0);
        document.getElementById('tot_cu').textContent = fmt(cu, 0);
        document.getElementById('tot_b').textContent  = fmt(b, 2);
        document.getElementById('tot_t').textContent  = fmt(t, 0);
        document.getElementById('tot_e').textContent  = fmt(e, 0);
        document.getElementById('sumFromRoster').textContent = fmt(e, 0);
        document.getElementById('prevExistBua').textContent = 'Existing BUA (roster): ' + fmt(b, 2) + ' sqm';
        if (document.getElementById('fld_rosterAuto')?.checked) document.getElementById('fld_eligible').value = e;
        recalcRehabPreview();
    };
    window.recalcRehabPreview = function () {
        const tenants = num(document.getElementById('fld_eligible'));
        const carpet  = num(document.getElementById('fld_carpetRes'));
        const loading = num(document.getElementById('fld_loading'));
        const totalBua = tenants * carpet * loading;

        // Amenity: user-supplied wins; else 38% of rehab BUA
        const amenityEl = document.getElementById('fld_amenityBuilt');
        const userAmen  = parseFloat(amenityEl?.value);
        const amenity   = (isFinite(userAmen) && userAmen > 0) ? userAmen : totalBua * 0.38;
        const rehabComp = totalBua + amenity;

        document.getElementById('prevBua').textContent = fmt(totalBua, 2) + ' sqm';
        document.getElementById('prevBuaFormula').textContent =
            fmt(tenants, 0) + ' × ' + fmt(carpet, 2) + ' × ' + fmt(loading, 3) + ' = ' + fmt(totalBua, 2);

        document.getElementById('prevAmenity').textContent = fmt(amenity, 2) + ' sqm';
        document.getElementById('prevAmenityFormula').textContent =
            (isFinite(userAmen) && userAmen > 0)
                ? 'entered'
                : fmt(totalBua, 2) + ' × 38% = ' + fmt(amenity, 2);

        document.getElementById('prevRehabComp').textContent = fmt(rehabComp, 2) + ' sqm';
    };
    window.addRow = function () {
        const body = document.getElementById('bldgBody');
        const idx = body.children.length + 1;
        const tr = document.createElement('tr'); tr.className = 'bldg-row';
        tr.innerHTML = `
            <td><input type="text" name="buildings[label][]" placeholder="Bldg ${idx}"></td>
            <td><input type="number" name="buildings[res_units][]" class="b-num b-ru" min="0"></td>
            <td><input type="number" name="buildings[com_units][]" class="b-num b-cu" min="0"></td>
            <td><input type="number" step="any" name="buildings[res_carpet][]"></td>
            <td><input type="number" step="any" name="buildings[com_carpet][]"></td>
            <td><input type="number" step="any" name="buildings[bua][]" class="b-num b-b"></td>
            <td><input type="number" name="buildings[tenants][]" class="b-num b-t" min="0"></td>
            <td><input type="number" name="buildings[eligible][]" class="b-num b-e" min="0"></td>
            <td><button type="button" class="rm-btn" onclick="rmRow(this)"><i class="bi bi-trash"></i></button></td>`;
        body.appendChild(tr); wireRow(tr);
    };
    window.rmRow = function (btn) {
        const rows = document.querySelectorAll('.bldg-row');
        if (rows.length <= 1) btn.closest('tr').querySelectorAll('input').forEach(i => i.value = '');
        else btn.closest('tr').remove();
        recalcTotals();
    };
    function wireRow(tr) { tr.querySelectorAll('input.b-num').forEach(i => i.addEventListener('input', recalcTotals)); }
    document.querySelectorAll('.s3-recalc').forEach(el => el.addEventListener('input', recalcRehabPreview));
    document.getElementById('fld_rosterAuto')?.addEventListener('change', recalcTotals);
    document.querySelectorAll('.bldg-row').forEach(wireRow);
    recalcTotals();
})();
</script>

<?php _aa_shell_close(); ?>
