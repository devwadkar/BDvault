<?php $pageTitle = 'Plan Inputs — ' . e($plan['plan_name']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<style>
    .plan-hero { background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff; border-radius: 14px; padding: 20px 24px; margin-bottom: 18px; }
    .plan-hero .badge-reg { background: rgba(99,102,241,0.25); color: #c7d2fe; font-weight: 700; padding: 3px 12px; border-radius: 999px; font-size: 0.72rem; }
    .plan-hero h5 { margin: 8px 0 0; font-weight: 700; }
    .plan-hero p  { margin: 4px 0 0; color: #cbd5e1; font-size: 0.8rem; }
    .group-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px 22px; background: #fff; margin-bottom: 16px; }
    .group-card h6 { font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: .06em; font-size: 0.75rem; margin: 0 0 4px; }
    .group-help { color: #64748b; font-size: 0.78rem; margin: 0 0 14px; }
    .field-label { font-weight: 600; color: #334155; font-size: 0.8rem; margin-bottom: 2px; }
    .field-reg { color: #94a3b8; font-size: 0.68rem; text-transform: uppercase; letter-spacing: .04em; margin-left: 6px; }
    .field-help { color: #64748b; font-size: 0.72rem; margin-top: 2px; }
    .required { color: #dc2626; }
</style>

<a href="index.php?page=planning" class="text-muted small text-decoration-none">
    <i class="bi bi-arrow-left me-1"></i>Back to planning
</a>

<div class="plan-hero mt-2">
    <span class="badge-reg">DCPR <?= e($plan['regulation_key']) ?></span>
    <h5><?= e($plan['plan_name']) ?></h5>
    <p>Fill in the inputs below and click <b>Calculate</b>. Every value is stored with its regulation reference for audit.</p>
</div>

<?php
$daCountRes = getDB()->query("SELECT COUNT(*) AS c FROM da_extractions WHERE proposal_id = " . (int)$plan['proposal_id'] . " AND status IN ('auto','verified','edited')");
$daCount = $daCountRes ? (int)($daCountRes->fetch_assoc()['c'] ?? 0) : 0;
?>
<style>
    .autofill-card {
        background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
        border: 1px solid #fde68a; border-radius: 12px;
        padding: 16px 20px; margin-bottom: 16px;
    }
    .autofill-card .title { font-weight: 700; color: #92400e; font-size: 0.85rem; margin-bottom: 4px; }
    .autofill-card .sub   { color: #78350f; font-size: 0.75rem; margin-bottom: 10px; }
    .autofill-drop {
        border: 2px dashed #fbbf24; border-radius: 10px; padding: 14px;
        background: rgba(255,255,255,0.6); text-align: center;
        transition: border-color .15s, background .15s;
    }
    .autofill-drop:hover, .autofill-drop.dragover { border-color: #d97706; background: rgba(255,255,255,0.9); }
    .autofill-drop input[type=file] { display: none; }
    .autofill-drop label { cursor: pointer; color: #b45309; font-weight: 600; font-size: 0.82rem; margin: 0; }
    .autofill-drop small { display: block; color: #92400e; font-size: 0.7rem; margin-top: 4px; }
    .autofill-selected { color: #78350f; font-size: 0.78rem; margin-top: 6px; font-weight: 600; }
    .autofill-loading {
        display: none; align-items: center; gap: 8px; margin-top: 10px;
        color: #78350f; font-size: 0.78rem; font-weight: 600;
    }
    .autofill-loading.on { display: inline-flex; }
</style>
<div class="autofill-card">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div style="flex: 1; min-width: 260px;">
            <div class="title"><i class="bi bi-magic me-1"></i>Auto-fill from Development Agreement / Feasibility Report</div>
            <div class="sub">
                Upload a DA, supplemental, feasibility report, or approval letter. AI will read it, extract the values, and pre-fill this form —
                you only review &amp; correct. Supported: PDF, DOCX, images (OCR).
            </div>

            <form method="POST" action="index.php?page=planning_upload_autofill" enctype="multipart/form-data" id="afForm">
                <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
                <div class="row g-2 align-items-end mb-2">
                    <div class="col-md-5">
                        <label class="small fw-semibold text-warning-emphasis mb-1">Document Type</label>
                        <select name="doc_type" class="form-select form-select-sm">
                            <option value="DA">Development Agreement (DA)</option>
                            <option value="feasibility">Existing Feasibility Report</option>
                            <option value="supplemental">Supplemental / Amendment</option>
                            <option value="annexure">Annexure / Schedule</option>
                            <option value="property_card">Property Card</option>
                            <option value="cts">CTS Document</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="autofill-drop" id="afDrop">
                    <input type="file" name="file" id="afInput" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.tif,.tiff">
                    <label for="afInput"><i class="bi bi-cloud-upload me-1"></i>Choose a file or drag it here</label>
                    <small>One document · Max 40 MB · PDF preferred (DOCX &amp; images also supported)</small>
                    <div id="afSelected" class="autofill-selected" style="display:none;"></div>
                </div>
                <div class="d-flex align-items-center mt-2 gap-2 flex-wrap">
                    <button type="submit" class="btn btn-warning btn-sm fw-semibold" id="afBtn" disabled>
                        <i class="bi bi-stars me-1"></i>Upload &amp; Auto-fill
                    </button>
                    <span class="text-muted small">This may take 20-60 seconds while AI reads the document.</span>
                    <div class="autofill-loading" id="afLoading">
                        <span class="spinner-border spinner-border-sm"></span>
                        Uploading &amp; extracting…
                    </div>
                </div>
            </form>
        </div>

        <?php if ($daCount > 0): ?>
            <div style="min-width: 220px; padding-left: 16px; border-left: 1px dashed #fbbf24;">
                <div class="title" style="font-size: 0.75rem;">Existing extractions</div>
                <div class="sub"><?= $daCount ?> field(s) already vetted from previously uploaded documents.</div>
                <form method="POST" action="index.php?page=planning_prefill_da" onsubmit="return confirm('Overwrite matching inputs with <?= $daCount ?> verified DA extractions?')">
                    <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-warning fw-semibold">
                        <i class="bi bi-arrow-repeat me-1"></i>Re-apply <?= $daCount ?> fields
                    </button>
                </form>
                <div class="mt-2">
                    <a href="index.php?page=documents&proposal_id=<?= (int)$plan['proposal_id'] ?>" class="small text-decoration-none" style="color:#92400e;">
                        <i class="bi bi-folder2-open me-1"></i>Manage all documents →
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const drop  = document.getElementById('afDrop');
    const input = document.getElementById('afInput');
    const sel   = document.getElementById('afSelected');
    const btn   = document.getElementById('afBtn');
    const form  = document.getElementById('afForm');
    const load  = document.getElementById('afLoading');

    input?.addEventListener('change', () => {
        const f = input.files[0];
        if (f) {
            sel.textContent = '📎 ' + f.name + ' (' + Math.round(f.size / 1024) + ' KB)';
            sel.style.display = 'block';
            btn.disabled = false;
        } else {
            sel.style.display = 'none';
            btn.disabled = true;
        }
    });
    ['dragover'].forEach(ev => drop?.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('dragover'); }));
    ['dragleave','drop'].forEach(ev => drop?.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('dragover'); }));
    drop?.addEventListener('drop', e => {
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            input.dispatchEvent(new Event('change'));
        }
    });
    form?.addEventListener('submit', () => {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Working…';
        load.classList.add('on');
    });
})();
</script>

<form method="POST" action="index.php?page=planning_save">
    <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">

    <?php foreach ($schema['groups'] as $group): ?>
        <div class="group-card">
            <h6><?= e($group['title']) ?></h6>
            <?php if (!empty($group['help'])): ?>
                <p class="group-help"><?= e($group['help']) ?></p>
            <?php endif; ?>

            <div class="row g-3">
                <?php foreach ($group['fields'] as $field):
                    $val = $inputs[$field['key']] ?? ($field['default'] ?? '');
                    $required = !empty($field['required']);
                    $unit = $field['unit'] ?? '';
                    $type = $field['type'] ?? 'string';
                ?>
                    <div class="col-md-6">
                        <label class="field-label" for="fld_<?= e($field['key']) ?>">
                            <?= e($field['label']) ?>
                            <?php if ($required): ?><span class="required">*</span><?php endif; ?>
                            <?php if (!empty($field['regulation_ref'])): ?>
                                <span class="field-reg"><?= e($field['regulation_ref']) ?></span>
                            <?php endif; ?>
                        </label>

                        <?php if ($type === 'select'): ?>
                            <select name="<?= e($field['key']) ?>" id="fld_<?= e($field['key']) ?>" class="form-select form-select-sm" <?= $required ? 'required' : '' ?>>
                                <option value="">— Select —</option>
                                <?php foreach (($field['options'] ?? []) as $opt): ?>
                                    <option value="<?= e($opt) ?>" <?= ((string)$val === (string)$opt) ? 'selected' : '' ?>><?= e($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($type === 'textarea'): ?>
                            <textarea name="<?= e($field['key']) ?>" id="fld_<?= e($field['key']) ?>" rows="2" class="form-control form-control-sm" <?= $required ? 'required' : '' ?>><?= e((string)$val) ?></textarea>
                        <?php else: ?>
                            <div class="input-group input-group-sm">
                                <input
                                    type="<?= $type === 'number' ? 'number' : 'text' ?>"
                                    step="any"
                                    name="<?= e($field['key']) ?>"
                                    id="fld_<?= e($field['key']) ?>"
                                    value="<?= e((string)$val) ?>"
                                    class="form-control"
                                    <?= $required ? 'required' : '' ?>>
                                <?php if ($unit): ?>
                                    <span class="input-group-text"><?= e($unit) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($field['help'])): ?>
                            <div class="field-help"><?= e($field['help']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-calculator me-1"></i>Save Inputs &amp; Calculate
        </button>
        <a href="index.php?page=planning" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<?php require __DIR__ . '/../layout/footer.php'; ?>
