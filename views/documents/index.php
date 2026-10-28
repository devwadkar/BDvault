<?php $pageTitle = 'Documents — ' . e($proposal['proposal_name']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<style>
    .doc-hero { background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff; border-radius: 14px; padding: 22px 26px; margin-bottom: 20px; }
    .doc-hero h5 { margin: 0; font-weight: 700; }
    .doc-hero p  { margin: 4px 0 0; color: #cbd5e1; font-size: 0.82rem; }

    .card-block { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px 22px; margin-bottom: 18px; }
    .card-block h6 { font-weight: 700; text-transform: uppercase; letter-spacing: .06em; font-size: 0.72rem; color: #0f172a; margin: 0 0 12px; }

    .upload-drop {
        border: 2px dashed #cbd5e1; border-radius: 12px; padding: 24px; text-align: center;
        background: #f8fafc; transition: border-color .15s, background .15s;
    }
    .upload-drop:hover, .upload-drop.dragover { border-color: #6366f1; background: #eef2ff; }
    .upload-drop input[type=file] { display: none; }
    .upload-drop label { cursor: pointer; color: #4f46e5; font-weight: 600; }
    .upload-drop small { color: #64748b; font-size: 0.75rem; display: block; margin-top: 6px; }

    table.docs { width: 100%; font-size: 0.82rem; border-collapse: collapse; }
    table.docs th { background: #f8fafc; color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 0.68rem; letter-spacing: .04em; padding: 8px 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
    table.docs td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .doc-type-badge { padding: 2px 10px; border-radius: 999px; font-size: 0.7rem; font-weight: 600; background: #eef2ff; color: #4338ca; }
    .ext-status-badge { padding: 2px 10px; border-radius: 999px; font-size: 0.7rem; font-weight: 600; }
    .ext-status-badge.ok { background: #dcfce7; color: #166534; }
    .ext-status-badge.no { background: #fef3c7; color: #92400e; }

    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 14px; }
    .stat-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 14px; }
    .stat-card .lbl { color: #64748b; font-size: 0.68rem; text-transform: uppercase; letter-spacing: .06em; }
    .stat-card .val { color: #0f172a; font-weight: 700; font-size: 1rem; margin-top: 2px; }

    .ex-cat { margin-bottom: 18px; }
    .ex-cat-title { font-weight: 700; text-transform: uppercase; letter-spacing: .06em; font-size: 0.7rem; color: #4338ca; margin: 8px 0 6px; }
    table.ex { width: 100%; font-size: 0.82rem; border-collapse: collapse; }
    table.ex th { background: #f8fafc; color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 0.66rem; letter-spacing: .04em; padding: 8px 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
    table.ex td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    .ex-key { font-weight: 600; color: #0f172a; font-size: 0.82rem; }
    .ex-src { color: #64748b; font-size: 0.7rem; margin-top: 2px; }
    .ex-src .cite { color: #6366f1; }
    .ex-val { font-family: 'JetBrains Mono', 'Consolas', monospace; font-size: 0.82rem; color: #0f172a; }
    .ex-val small { color: #64748b; }
    .conf-badge { padding: 2px 8px; border-radius: 999px; font-size: 0.7rem; font-weight: 600; }
    .conf-badge.high   { background: #dcfce7; color: #166534; }
    .conf-badge.med    { background: #fef3c7; color: #92400e; }
    .conf-badge.low    { background: #fee2e2; color: #991b1b; }
    .st-badge { padding: 2px 10px; border-radius: 999px; font-size: 0.7rem; font-weight: 600; }
    .st-badge.auto         { background: #f1f5f9; color: #475569; }
    .st-badge.needs_review { background: #fef3c7; color: #92400e; }
    .st-badge.verified     { background: #dcfce7; color: #166534; }
    .st-badge.edited       { background: #dbeafe; color: #1e40af; }
    .st-badge.rejected     { background: #fee2e2; color: #991b1b; }

    .action-btn { border: 1px solid #e5e7eb; background: #fff; padding: 3px 8px; border-radius: 6px; font-size: 0.7rem; cursor: pointer; margin-right: 4px; }
    .action-btn:hover { background: #f1f5f9; }
    .action-btn.accept { border-color: #16a34a; color: #16a34a; }
    .action-btn.edit   { border-color: #2563eb; color: #2563eb; }
    .action-btn.reject { border-color: #dc2626; color: #dc2626; }
    .action-btn.source { border-color: #6366f1; color: #6366f1; }

    /* Source-view modal */
    .src-body { max-height: 60vh; overflow-y: auto; }
    .src-snippet { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 14px; font-family: 'JetBrains Mono', 'Consolas', monospace; font-size: 0.78rem; white-space: pre-wrap; word-break: break-word; }
    .src-snippet mark { background: #fef08a; color: #713f12; padding: 1px 2px; }

    /* DA Explainer chat */
    .ai-fab {
        position: fixed; bottom: 22px; right: 22px; z-index: 1050;
        background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; border: 0;
        border-radius: 999px; padding: 12px 20px; font-weight: 700; font-size: 0.82rem;
        display: inline-flex; align-items: center; gap: 8px;
        box-shadow: 0 8px 24px rgba(245,158,11,0.35); cursor: pointer;
    }
    .ai-fab:hover { transform: translateY(-1px); }
    .ai-panel {
        position: fixed; bottom: 22px; right: 22px; width: 440px; max-width: calc(100vw - 44px);
        max-height: calc(100vh - 100px); z-index: 1050;
        background: #fff; border: 1px solid #e5e7eb; border-radius: 16px;
        box-shadow: 0 20px 50px rgba(15,23,42,0.18);
        display: none; flex-direction: column;
    }
    .ai-panel.open { display: flex; }
    .ai-head { background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff; padding: 14px 18px; border-radius: 16px 16px 0 0; display: flex; justify-content: space-between; align-items: center; }
    .ai-head h6 { margin: 0; font-weight: 700; font-size: 0.9rem; }
    .ai-head .ai-sub { font-size: 0.7rem; color: #cbd5e1; margin-top: 2px; }
    .ai-close { background: rgba(255,255,255,0.12); color: #fff; border: 0; border-radius: 8px; padding: 4px 8px; cursor: pointer; }
    .ai-body { flex: 1; overflow-y: auto; padding: 14px 18px; background: #f8fafc; display: flex; flex-direction: column; gap: 10px; min-height: 240px; }
    .ai-msg { max-width: 90%; padding: 10px 14px; border-radius: 12px; font-size: 0.85rem; line-height: 1.55; }
    .ai-msg.user { align-self: flex-end; background: #d97706; color: #fff; border-bottom-right-radius: 4px; }
    .ai-msg.assistant { align-self: flex-start; background: #fff; color: #0f172a; border: 1px solid #e5e7eb; border-bottom-left-radius: 4px; }
    .ai-msg.assistant .cite { display: inline-block; background: #fef3c7; color: #92400e; padding: 1px 6px; border-radius: 999px; font-size: 0.72rem; font-weight: 600; }
    .ai-msg.assistant .cite-block { margin-top: 8px; padding-top: 8px; border-top: 1px dashed #e5e7eb; font-size: 0.7rem; color: #64748b; }
    .ai-warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; padding: 6px 10px; border-radius: 8px; font-size: 0.72rem; margin-top: 6px; }
    .ai-suggest { display: flex; gap: 6px; flex-wrap: wrap; padding: 8px 18px; border-top: 1px solid #e5e7eb; background: #fff; }
    .ai-suggest button { background: #fef3c7; color: #92400e; border: 0; border-radius: 999px; padding: 4px 12px; font-size: 0.72rem; cursor: pointer; }
    .ai-input { display: flex; padding: 12px 14px; border-top: 1px solid #e5e7eb; gap: 8px; border-radius: 0 0 16px 16px; }
    .ai-input textarea { flex: 1; border: 1px solid #e5e7eb; border-radius: 10px; padding: 8px 12px; font-size: 0.85rem; resize: none; height: 40px; font-family: inherit; }
    .ai-input textarea:focus { outline: none; border-color: #f59e0b; }
    .ai-send { background: #d97706; color: #fff; border: 0; border-radius: 10px; padding: 0 16px; font-weight: 700; cursor: pointer; }
    .ai-send:disabled { opacity: 0.6; cursor: wait; }
    .ai-empty { text-align: center; color: #94a3b8; font-size: 0.82rem; margin: 30px 0; }
    .ai-empty i { font-size: 1.8rem; color: #cbd5e1; display: block; margin-bottom: 8px; }
</style>

<a href="index.php?page=proposal_detail&id=<?= (int)$proposal['id'] ?>" class="text-muted small text-decoration-none">
    <i class="bi bi-arrow-left me-1"></i>Back to proposal
</a>

<div class="doc-hero mt-2 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h5><i class="bi bi-file-earmark-text me-2"></i>Documents &amp; DA Extraction</h5>
        <p>Proposal: <b><?= e($proposal['proposal_name']) ?></b></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="index.php?page=documents_extract&proposal_id=<?= (int)$proposal['id'] ?>" class="btn btn-sm btn-warning fw-semibold"
           onclick="return confirm('Run AI extraction on all uploaded documents? This may take 20-60 seconds.')">
            <i class="bi bi-stars me-1"></i>Extract Project Data with AI
        </a>
        <?php
        $exCountRes = getDB()->query("SELECT COUNT(*) AS c FROM da_extractions WHERE proposal_id = " . (int)$proposal['id']);
        $exCount = $exCountRes ? (int)($exCountRes->fetch_assoc()['c'] ?? 0) : 0;
        ?>
        <?php if ($exCount > 0): ?>
        <a href="index.php?page=documents_reconcile&proposal_id=<?= (int)$proposal['id'] ?>" class="btn btn-sm btn-light text-dark fw-semibold">
            <i class="bi bi-shield-check me-1"></i>DA vs DCPR Reconciliation
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Upload -->
<div class="card-block">
    <h6><i class="bi bi-cloud-upload me-1"></i>Upload Documents</h6>
    <form method="POST" action="index.php?page=documents_upload" enctype="multipart/form-data" id="upForm">
        <input type="hidden" name="proposal_id" value="<?= (int)$proposal['id'] ?>">
        <div class="row g-2 align-items-end mb-2">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Document Type</label>
                <select name="doc_type" class="form-select form-select-sm">
                    <option value="DA">Development Agreement (DA)</option>
                    <option value="supplemental">Supplemental / Amendment</option>
                    <option value="annexure">Annexure / Schedule</option>
                    <option value="property_card">Property Card</option>
                    <option value="cts">CTS Document</option>
                    <option value="survey">Survey Plan</option>
                    <option value="feasibility">Existing Feasibility Report</option>
                    <option value="other">Other</option>
                </select>
            </div>
        </div>
        <div class="upload-drop" id="upDrop">
            <input type="file" name="files[]" id="upInput" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.tif,.tiff">
            <label for="upInput"><i class="bi bi-plus-circle me-1"></i>Choose files or drag them here</label>
            <small>PDFs recommended · Max 40 MB per file · DOC/DOCX/images accepted (OCR arrives in Phase 3c)</small>
            <div id="upSelected" class="mt-2 small text-muted"></div>
        </div>
        <div class="mt-2 text-end">
            <button type="submit" class="btn btn-primary btn-sm" id="upBtn" disabled>
                <i class="bi bi-cloud-arrow-up me-1"></i>Upload
            </button>
        </div>
    </form>
</div>

<!-- Document list -->
<div class="card-block">
    <h6><i class="bi bi-files me-1"></i>Uploaded Documents (<?= count($docs) ?>)</h6>
    <?php if (empty($docs)): ?>
        <div class="text-muted small py-3">No documents uploaded yet.</div>
    <?php else: ?>
        <table class="docs">
            <thead><tr><th>Filename</th><th>Type</th><th style="text-align:right">Pages</th><th style="text-align:right">Size</th><th>Extraction</th><th>Uploaded</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($docs as $d): ?>
                    <tr>
                        <td><i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i><?= e($d['filename']) ?></td>
                        <td><span class="doc-type-badge"><?= e($d['doc_type']) ?></span></td>
                        <td style="text-align:right"><?= $d['page_count'] ? (int)$d['page_count'] : '—' ?></td>
                        <td style="text-align:right"><?= number_format($d['size_bytes'] / 1024, 0) ?> KB</td>
                        <td>
                            <?php if ($d['text_extracted']): ?>
                                <span class="ext-status-badge ok">Text OK</span>
                            <?php else: ?>
                                <span class="ext-status-badge no" title="<?= e($d['extraction_note'] ?? '') ?>">Pending / N/A</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= e($d['uploaded_at']) ?></td>
                        <td class="text-end">
                            <a href="index.php?page=documents_download&doc_id=<?= (int)$d['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Extractions -->
<div class="card-block">
    <h6><i class="bi bi-braces-asterisk me-1"></i>Extracted Project Data (<?= array_sum($counts) ?> fields)</h6>

    <div class="stats-row">
        <div class="stat-card"><div class="lbl">Auto-accepted (≥ 90%)</div><div class="val text-success"><?= (int)$counts['auto'] ?></div></div>
        <div class="stat-card"><div class="lbl">Needs Review</div><div class="val text-warning"><?= (int)$counts['needs_review'] ?></div></div>
        <div class="stat-card"><div class="lbl">Verified</div><div class="val text-success"><?= (int)$counts['verified'] ?></div></div>
        <div class="stat-card"><div class="lbl">Edited</div><div class="val text-primary"><?= (int)$counts['edited'] ?></div></div>
        <div class="stat-card"><div class="lbl">Rejected</div><div class="val text-danger"><?= (int)$counts['rejected'] ?></div></div>
    </div>

    <?php if (empty($exsByCat)): ?>
        <div class="text-muted small py-3">No extractions yet. Upload a DA and click <b>Extract Project Data with AI</b>.</div>
    <?php else:
        $catTitles = [
            'identification' => 'A · Project Identification',
            'land'           => 'B · Land Details',
            'road'           => 'C · Road Information',
            'existing'       => 'D · Existing Development',
            'rehab'          => 'E · Rehabilitation',
            'rights'         => 'F · DA-Stated Development Rights (not DCPR-verified)',
            'reservations'   => 'G · Reservations & Restrictions',
        ];
    ?>
        <?php foreach ($catTitles as $catKey => $title): ?>
            <?php if (empty($exsByCat[$catKey])) continue; ?>
            <div class="ex-cat">
                <div class="ex-cat-title"><?= e($title) ?></div>
                <table class="ex">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th style="width: 200px">Value</th>
                            <th style="width: 90px">Confidence</th>
                            <th style="width: 110px">Status</th>
                            <th style="width: 210px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exsByCat[$catKey] as $r):
                            $conf = (int)$r['confidence'];
                            $confClass = $conf >= 90 ? 'high' : ($conf >= 70 ? 'med' : 'low');
                        ?>
                            <tr>
                                <td>
                                    <div class="ex-key"><?= e($r['field_label']) ?></div>
                                    <div class="ex-src">
                                        <?php if ($r['clause_ref']): ?><span class="cite"><?= e($r['clause_ref']) ?></span> · <?php endif; ?>
                                        <?php if ($r['page_no']): ?>Page <?= (int)$r['page_no'] ?><?php endif; ?>
                                    </div>
                                </td>
                                <td class="ex-val">
                                    <span data-val-cell="<?= (int)$r['id'] ?>"><?= e($r['extracted_value']) ?></span>
                                    <?php if ($r['unit']): ?><small><?= e($r['unit']) ?></small><?php endif; ?>
                                    <?php if ($r['normalized_value'] && $r['normalized_value'] !== $r['extracted_value']): ?>
                                        <div><small>→ <?= e($r['normalized_value']) ?> <?= e($r['unit']) ?></small></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="conf-badge <?= $confClass ?>"><?= $conf ?>%</span></td>
                                <td><span class="st-badge <?= e($r['status']) ?>" data-st-cell="<?= (int)$r['id'] ?>"><?= e($r['status']) ?></span></td>
                                <td>
                                    <button type="button" class="action-btn source" onclick="showSource(<?= (int)$r['id'] ?>)"><i class="bi bi-quote"></i> Source</button>
                                    <button type="button" class="action-btn accept" onclick="verifyRow(<?= (int)$r['id'] ?>, 'accept')"><i class="bi bi-check"></i></button>
                                    <button type="button" class="action-btn edit"   onclick="editRow(<?= (int)$r['id'] ?>)"><i class="bi bi-pencil"></i></button>
                                    <button type="button" class="action-btn reject" onclick="verifyRow(<?= (int)$r['id'] ?>, 'reject')"><i class="bi bi-x"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Source-view modal -->
<div class="modal fade" id="srcModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold" id="srcTitle">Source</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body src-body">
                <div id="srcMeta" class="small text-muted mb-2"></div>
                <div id="srcOriginal" class="mb-2"><b>Original text:</b> <span></span></div>
                <div><b>Page snippet:</b></div>
                <div class="src-snippet" id="srcSnippet"></div>
                <div class="mt-3"><b>AI notes:</b> <span id="srcNotes" class="text-muted small"></span></div>
            </div>
            <div class="modal-footer">
                <a href="#" id="srcOpenDoc" target="_blank" class="btn btn-sm btn-outline-secondary">Open full document</a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // Upload UX
    const drop = document.getElementById('upDrop');
    const input = document.getElementById('upInput');
    const sel   = document.getElementById('upSelected');
    const btn   = document.getElementById('upBtn');
    input?.addEventListener('change', () => {
        const files = Array.from(input.files);
        sel.textContent = files.length ? files.map(f => f.name).join(', ') : '';
        btn.disabled = files.length === 0;
    });
    ['dragover'].forEach(ev => drop?.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('dragover'); }));
    ['dragleave', 'drop'].forEach(ev => drop?.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('dragover'); }));
    drop?.addEventListener('drop', e => {
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            input.dispatchEvent(new Event('change'));
        }
    });
})();

async function verifyRow(id, action, value) {
    const fd = new URLSearchParams();
    fd.append('id', id);
    fd.append('action', action);
    if (value !== undefined) fd.append('value', value);
    const res = await fetch('index.php?page=documents_verify', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: fd.toString()});
    const data = await res.json();
    if (!data.success) { alert(data.error || 'Failed'); return; }
    // Update the status pill inline
    const cell = document.querySelector(`[data-st-cell="${id}"]`);
    if (cell) { cell.textContent = data.status; cell.className = 'st-badge ' + data.status; }
    if (action === 'edit') {
        const vCell = document.querySelector(`[data-val-cell="${id}"]`);
        if (vCell) vCell.textContent = data.normalized;
    }
}

function editRow(id) {
    const cur = document.querySelector(`[data-val-cell="${id}"]`)?.textContent?.trim() || '';
    const val = prompt('Enter corrected value:', cur);
    if (val === null || val === '') return;
    verifyRow(id, 'edit', val);
}

async function showSource(id) {
    const res = await fetch('index.php?page=documents_source&extraction_id=' + id);
    const data = await res.json();
    if (!data.success) { alert(data.error || 'Failed'); return; }

    document.getElementById('srcTitle').textContent = data.field_label + ' — Source';
    document.getElementById('srcMeta').innerHTML =
        `<b>File:</b> ${escapeHtml(data.filename || '—')} · <b>Page:</b> ${data.page_no || '—'}` +
        ` · <b>Clause:</b> ${escapeHtml(data.clause_ref || '—')} · <b>Confidence:</b> ${data.confidence}%`;
    document.querySelector('#srcOriginal span').textContent = data.original_text || '(no original text)';
    document.getElementById('srcNotes').textContent = data.ai_notes || '—';

    let snippet = escapeHtml(data.page_snippet || '');
    snippet = snippet.replace(/&lt;&lt;HIT&gt;&gt;/g, '<mark>').replace(/&lt;&lt;\/HIT&gt;&gt;/g, '</mark>');
    document.getElementById('srcSnippet').innerHTML = snippet;

    document.getElementById('srcOpenDoc').href = 'index.php?page=documents_download&doc_id=' + (data.document_id || 0);

    new bootstrap.Modal(document.getElementById('srcModal')).show();
}

function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
}
</script>

<!-- ─── DA Explainer Chat ─────────────────────────────────────── -->
<?php if (!empty($exs)): ?>
<button class="ai-fab" id="daFab" onclick="document.getElementById('daPanel').classList.add('open'); document.getElementById('daFab').style.display='none'; document.getElementById('daInput').focus();">
    <i class="bi bi-quote"></i> Ask about extractions
</button>
<div class="ai-panel" id="daPanel">
    <div class="ai-head">
        <div>
            <h6>DA Extraction Explainer</h6>
            <div class="ai-sub">Grounded on this proposal's extracted data</div>
        </div>
        <button class="ai-close" onclick="document.getElementById('daPanel').classList.remove('open'); document.getElementById('daFab').style.display='inline-flex';">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="ai-body" id="daBody">
        <div class="ai-empty">
            <i class="bi bi-file-earmark-text"></i>
            Ask where a value came from, why it was flagged, or what's missing.<br>
            Every answer cites the extraction id + page.
        </div>
    </div>
    <div class="ai-suggest">
        <button type="button" onclick="daAsk(this.textContent)">Where did you get the gross plot area?</button>
        <button type="button" onclick="daAsk(this.textContent)">Which fields were flagged for review and why?</button>
        <button type="button" onclick="daAsk(this.textContent)">What are the DA-stated development rights?</button>
        <button type="button" onclick="daAsk(this.textContent)">What information is missing?</button>
    </div>
    <div class="ai-input">
        <textarea id="daInput" placeholder="Ask a question about the extractions…" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();document.getElementById('daSend').click();}"></textarea>
        <button class="ai-send" id="daSend" onclick="daAsk(document.getElementById('daInput').value)">Send</button>
    </div>
</div>
<script>
(function () {
    const PROPOSAL_ID = <?= (int)$proposal['id'] ?>;
    const history = [];
    function escapeHtml(s) { return String(s).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch])); }
    function highlightCites(t) {
        return escapeHtml(t).replace(/(Extraction\s*#\d+|E\d+|Doc\s+\d+|page\s+\d+|p\.\s*\d+)/gi, '<span class="cite">$1</span>');
    }
    function appendMsg(role, text, opts) {
        const body = document.getElementById('daBody');
        body.querySelector('.ai-empty')?.remove();
        const m = document.createElement('div');
        m.className = 'ai-msg ' + role;
        if (role === 'assistant') {
            m.innerHTML = highlightCites(text).replace(/\n/g, '<br>');
            if (opts && opts.citations && opts.citations.length) {
                const cb = document.createElement('div');
                cb.className = 'cite-block';
                cb.innerHTML = 'Cited: ' + opts.citations.map(c => '<span class="cite">' + escapeHtml(c) + '</span>').join(' ');
                m.appendChild(cb);
            }
            if (opts && opts.warnings) opts.warnings.forEach(w => { const el = document.createElement('div'); el.className='ai-warn'; el.textContent='⚠ '+w; m.appendChild(el); });
        } else {
            m.textContent = text;
        }
        body.appendChild(m);
        body.scrollTop = body.scrollHeight;
    }
    window.daAsk = async function (raw) {
        const text = (raw||'').trim(); if (!text) return;
        const btn = document.getElementById('daSend');
        const inp = document.getElementById('daInput');
        appendMsg('user', text);
        history.push({role:'user', text});
        inp.value = ''; btn.disabled = true; btn.textContent = '…';
        const loading = document.createElement('div');
        loading.className = 'ai-msg assistant'; loading.id = 'daLoading';
        loading.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Consulting extractions…';
        document.getElementById('daBody').appendChild(loading);
        try {
            const res = await fetch('index.php?page=api_documents_assistant', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({proposal_id: PROPOSAL_ID, question: text, history: history})
            });
            const data = await res.json();
            document.getElementById('daLoading')?.remove();
            if (data.success && data.answer) {
                appendMsg('assistant', data.answer, {citations: data.citations, warnings: data.warnings});
                history.push({role:'assistant', text: data.answer});
            } else {
                appendMsg('assistant', 'Error: ' + (data.error || 'unknown'));
            }
        } catch (err) {
            document.getElementById('daLoading')?.remove();
            appendMsg('assistant', 'Network error: ' + err.message);
        } finally { btn.disabled = false; btn.textContent = 'Send'; inp.focus(); }
    };
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
