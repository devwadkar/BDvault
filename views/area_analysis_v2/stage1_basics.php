<?php $pageTitle = 'Area Analysis v2 · Project Basics'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/_shell.php'; ?>
<?php
// Decoded $statement, $proposal, $draft, $s1, $stages, $currentStage all come in from the controller.
$v = function($k, $src = null, $default = '') use ($s1, $statement) {
    $pool = $src === 'stmt' ? $statement : $s1;
    return htmlspecialchars((string)($pool[$k] ?? $default), ENT_QUOTES);
};
$sel = function($field, $opt, $src = 'stmt') use ($s1, $statement) {
    $pool = $src === 'stmt' ? $statement : $s1;
    return (($pool[$field] ?? '') === $opt) ? 'selected' : '';
};
$chk = function($field) use ($s1) { return !empty($s1[$field]) ? 'checked' : ''; };
$hasExisting = !empty($s1['has_existing_construction']);
$showSurveyArea = !empty($s1['survey_area_sqm']);
?>

<form method="POST" action="index.php?page=area_analysis_v2_save_stage1" id="s1Form" novalidate>
    <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
    <input type="hidden" name="direction" id="dirInput" value="next">

    <div class="v2-card">
        <h5><i class="bi bi-pin-map-fill me-2" style="color:var(--v2-primary);"></i>Project basics</h5>
        <p class="lead">Universal details that apply to every DCPR regulation. Takes about a minute.
            On the next step we'll suggest which regulation fits your project best.</p>

        <!-- ─── Section A: Identification ─── -->
        <div class="v2-section">
            <div class="v2-section-title"><span class="bullet">A</span>Identification</div>
            <div class="v2-grid">
                <div class="v2-field colspan-2">
                    <label>Project name <span class="req">*</span></label>
                    <input type="text" name="project_name" required value="<?= $v('project_name','stmt') ?>"
                           placeholder="e.g. Ghatkopar Parksite Redevelopment">
                    <div class="hint">A short name for this analysis. Carried over from the source proposal — edit if needed.</div>
                </div>

                <div class="v2-field">
                    <label>Source proposal</label>
                    <div style="padding:9px 12px;border:1px solid var(--v2-border);border-radius:8px;background:var(--v2-surface);font-size:0.85rem;">
                        <a href="index.php?page=proposal_detail&id=<?= (int)$proposal['id'] ?>" target="_blank"
                           style="color:var(--v2-primary);font-weight:600;text-decoration:none;">
                            <?= e($proposal['proposal_name']) ?>
                            <i class="bi bi-box-arrow-up-right" style="font-size:0.75rem;margin-left:4px;"></i>
                        </a>
                    </div>
                    <div class="hint">Opens the original proposal in a new tab.</div>
                </div>

                <div class="v2-field">
                    <label>CTS / Survey number <span class="req">*</span>
                        <span class="hint-icon" title="City Tenancy Survey (CTS) or Revenue Survey number. Found on your 7/12 extract or Property Card.">?</span>
                    </label>
                    <input type="text" name="cts_number" required value="<?= $v('cts_number','stmt') ?>"
                           placeholder="e.g. CTS 123/A pt">
                </div>
            </div>

            <div class="v2-field">
                <label>Property-card plot area <span class="req">*</span>
                    <span class="hint-icon" title="The area recorded on your Property Card (PR Card), measured in square metres.">?</span>
                </label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="number" step="any" min="0" name="property_card_area_sqm" required
                           value="<?= $v('property_card_area_sqm') ?>" style="max-width:220px;" placeholder="0.00">
                    <span style="color:var(--v2-muted);font-size:0.82rem;">sqm</span>
                    <span style="color:var(--v2-faint);font-size:0.75rem;">
                        (<span id="sqftConv">—</span>)
                    </span>
                </div>
                <div class="hint">
                    <a href="javascript:void(0)" onclick="toggleSurveyArea()" id="surveyToggle"
                       style="color:var(--v2-primary);">
                        <?= $showSurveyArea ? 'Hide survey area' : '+ Different from survey area?' ?>
                    </a>
                </div>
                <div id="surveyAreaWrap" style="margin-top:10px;<?= $showSurveyArea ? '' : 'display:none;' ?>">
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="number" step="any" min="0" name="survey_area_sqm"
                               value="<?= $v('survey_area_sqm') ?>" style="max-width:220px;" placeholder="Survey area">
                        <span style="color:var(--v2-muted);font-size:0.82rem;">sqm (as per survey)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Section B: Location & jurisdiction ─── -->
        <div class="v2-section">
            <div class="v2-section-title"><span class="bullet">B</span>Location &amp; jurisdiction</div>

            <div class="v2-field">
                <label>Plot address <span class="req">*</span></label>
                <textarea name="plot_address" required placeholder="Full address including locality, pin code"><?= $v('plot_address','stmt') ?></textarea>
            </div>

            <div class="v2-grid-3">
                <div class="v2-field">
                    <label>City / region <span class="req">*</span></label>
                    <input type="text" name="project_location" required value="<?= $v('project_location','stmt') ?>"
                           placeholder="e.g. Mumbai, Ghatkopar West">
                </div>

                <div class="v2-field">
                    <label>Ward <span class="req">*</span>
                        <span class="hint-icon" title="MCGM ward code (A, B, C, D, E, F/N, F/S, G/N, G/S, H/E, H/W, K/E, K/W, L, M/E, M/W, N, P/N, P/S, R/C, R/N, R/S, S, T).">?</span>
                    </label>
                    <select name="ward" required>
                        <option value="">Select…</option>
                        <?php foreach (['A','B','C','D','E','F/N','F/S','G/N','G/S','H/E','H/W','K/E','K/W','L',
                                        'M/E','M/W','N','P/N','P/S','R/C','R/N','R/S','S','T','N/A'] as $w): ?>
                            <option value="<?= $w ?>" <?= $sel('ward', $w, 'stmt') ?>><?= $w ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="v2-field">
                    <label>Zone (DP)
                        <span class="hint-icon" title="Zoning as per Development Plan — Residential, Commercial, etc.">?</span>
                    </label>
                    <select name="zone_dp">
                        <option value="">Select…</option>
                        <?php foreach (['Residential','Commercial','Industrial','Mixed','No-development'] as $z): ?>
                            <option value="<?= $z ?>" <?= $sel('zone_dp', $z, 's1') ?>><?= $z ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="v2-grid">
                <div class="v2-field">
                    <label>Planning authority <span class="req">*</span></label>
                    <select name="planning_authority" required>
                        <option value="">Select…</option>
                        <?php foreach (['MCGM','SRA','MHADA','MMRDA','Collector','Other'] as $opt): ?>
                            <option value="<?= $opt ?>" <?= $sel('planning_authority', $opt, 'stmt') ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint">The authority that will sanction this project.</div>
                </div>

                <div class="v2-field">
                    <label>Is this an Island City plot?
                        <span class="hint-icon" title="Island City = south of Mahim–Sion. Matters for 33(7) cessed-building eligibility.">?</span>
                    </label>
                    <div class="v2-toggle">
                        <input type="radio" name="is_island_city" id="isl_no" value="0"
                               <?= empty($s1['is_island_city']) ? 'checked' : '' ?>>
                        <label for="isl_no">No</label>
                        <input type="radio" name="is_island_city" id="isl_yes" value="1"
                               <?= !empty($s1['is_island_city']) ? 'checked' : '' ?>>
                        <label for="isl_yes">Yes</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Section C: Project category ─── -->
        <div class="v2-section">
            <div class="v2-section-title"><span class="bullet">C</span>Project category</div>

            <div class="v2-grid">
                <div class="v2-field">
                    <label>Development category <span class="req">*</span></label>
                    <select name="development_type" required>
                        <option value="">Select…</option>
                        <?php
                        $devOpts = [
                            'redevelopment'         => 'Redevelopment (society)',
                            'sra'                   => 'SRA · Slum Redevelopment',
                            'mhada'                 => 'MHADA layout',
                            'society_redevelopment' => 'Co-op society redevelopment',
                            'land_development'      => 'Land development · Vacant plot',
                            'cluster'               => 'Cluster / Urban renewal',
                            'other'                 => 'Other',
                        ];
                        foreach ($devOpts as $k => $l): ?>
                            <option value="<?= $k ?>" <?= $sel('development_type', $k, 'stmt') ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="v2-field">
                    <label>Primary access road width <span class="req">*</span>
                        <span class="hint-icon" title="Width of the main road adjoining the plot. Determines FSI eligibility (e.g. 4.0 FSI for roads ≥ 18m).">?</span>
                    </label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="number" step="any" min="0" name="primary_road_width_m" required
                               value="<?= $v('primary_road_width_m') ?>" style="max-width:160px;" placeholder="0.00">
                        <span style="color:var(--v2-muted);font-size:0.82rem;">m</span>
                    </div>
                </div>
            </div>

            <div class="v2-field">
                <label>Is there existing construction on the plot?</label>
                <div class="v2-toggle">
                    <input type="radio" name="has_existing_construction" id="hec_no" value="0"
                           <?= !$hasExisting ? 'checked' : '' ?> onchange="toggleExisting()">
                    <label for="hec_no">No · Vacant plot</label>
                    <input type="radio" name="has_existing_construction" id="hec_yes" value="1"
                           <?= $hasExisting ? 'checked' : '' ?> onchange="toggleExisting()">
                    <label for="hec_yes">Yes · Buildings on site</label>
                </div>
                <div class="hint">
                    If No, Section D below stays hidden. Detailed per-building data comes later in Stage 3.
                </div>
            </div>
        </div>

        <!-- ─── Section D: Existing occupancy (conditional) ─── -->
        <div class="v2-section" id="secD" style="<?= $hasExisting ? '' : 'display:none;' ?>">
            <div class="v2-section-title"><span class="bullet">D</span>Existing occupancy <span style="font-weight:400;letter-spacing:0;text-transform:none;color:var(--v2-faint);margin-left:4px;">(approximate — refine in Stage 3)</span></div>
            <p class="hint" style="margin-top:-6px;margin-bottom:12px;">
                Rough counts help us recommend the right regulation.
            </p>
            <div class="v2-grid-3">
                <div class="v2-field">
                    <label>Residential units (approx)</label>
                    <input type="number" min="0" step="1" name="approx_residential_units"
                           value="<?= $v('approx_residential_units') ?>">
                </div>
                <div class="v2-field">
                    <label>Commercial units (approx)</label>
                    <input type="number" min="0" step="1" name="approx_commercial_units"
                           value="<?= $v('approx_commercial_units') ?>">
                </div>
                <div class="v2-field">
                    <label>Tenant / member count (approx)
                        <span class="hint-icon" title="Required for SRA (33(10)) and MHADA (33(5)) eligibility.">?</span>
                    </label>
                    <input type="number" min="0" step="1" name="approx_tenant_count"
                           value="<?= $v('approx_tenant_count') ?>">
                </div>
            </div>
        </div>

        <!-- ─── Nav bar ─── -->
        <div class="v2-nav">
            <div class="saved">
                <?php if ($lastSaved): ?>
                    <i class="bi bi-check-circle-fill" style="color:var(--v2-success);"></i>
                    Saved <?= e(date('d M, H:i', strtotime($lastSaved))) ?>
                <?php else: ?>
                    <i class="bi bi-dash-circle" style="color:var(--v2-faint);"></i>
                    Not saved yet
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="index.php?page=area_analysis_v2" class="v2-btn ghost">
                    <i class="bi bi-arrow-left"></i> Back to list
                </a>
                <button type="submit" class="v2-btn ghost" onclick="document.getElementById('dirInput').value='save'">
                    <i class="bi bi-cloud-arrow-up"></i> Save draft
                </button>
                <button type="submit" class="v2-btn primary" onclick="document.getElementById('dirInput').value='next'">
                    Continue to recommendation
                    <i class="bi bi-arrow-right"></i>
                </button>
            </div>
        </div>
    </div>
</form>

<?php _v2_shell_close($statement, $s1, $lastSaved); ?>

<script>
// ─── Toggle Section D visibility based on existing-construction radio ───
function toggleExisting() {
    const yes = document.getElementById('hec_yes').checked;
    document.getElementById('secD').style.display = yes ? '' : 'none';
}

// ─── Toggle the "survey area different from property-card area" field ───
function toggleSurveyArea() {
    const wrap = document.getElementById('surveyAreaWrap');
    const link = document.getElementById('surveyToggle');
    const shown = wrap.style.display !== 'none';
    wrap.style.display = shown ? 'none' : '';
    link.textContent = shown ? '+ Different from survey area?' : 'Hide survey area';
    if (shown) wrap.querySelector('input').value = '';
}

// ─── Live sqm → sq ft display next to property-card area ───
(function() {
    const fld = document.querySelector('input[name=property_card_area_sqm]');
    const out = document.getElementById('sqftConv');
    if (!fld || !out) return;
    const update = () => {
        const sqm = parseFloat(fld.value);
        out.textContent = isNaN(sqm) || sqm <= 0 ? '—' : (sqm * 10.7639).toLocaleString('en-IN', {maximumFractionDigits: 0}) + ' sq ft';
    };
    fld.addEventListener('input', update);
    update();
})();

// ─── Inline required-field validation on blur ───
document.querySelectorAll('#s1Form [required]').forEach(el => {
    el.addEventListener('blur', () => {
        const wrap = el.closest('.v2-field');
        if (!wrap) return;
        const bad = !el.value || (el.type === 'number' && parseFloat(el.value) <= 0);
        wrap.classList.toggle('has-err', bad);
        // Add an .err note if missing
        if (bad && !wrap.querySelector('.err')) {
            const hint = document.createElement('div');
            hint.className = 'err';
            hint.textContent = 'Required';
            wrap.appendChild(hint);
        }
    });
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
