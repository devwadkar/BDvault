<?php
$isEdit    = !empty($proposal);
$pageTitle = $isEdit ? 'Edit Proposal' : 'Add Proposal';
$v         = $proposal ?? [];
?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<!-- Premium Form Styling -->
<style>
.form-header-wrap {
    margin-bottom: 28px;
}
.back-btn-circle {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    transition: background 0.2s;
    text-decoration: none;
}
.back-btn-circle:hover { background: #f8fafc; color: #1e293b; }

.form-title {
    font-size: 1.45rem;
    font-weight: 800;
    color: #1e293b;
    letter-spacing: -0.02em;
}
.form-subtitle {
    font-size: 0.8rem;
    color: #64748b;
    font-weight: 500;
}

/* Header completion bar */
.top-completion-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
}
.top-completion-bar {
    width: 140px;
    height: 6px;
    background: #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}
.top-completion-fill {
    height: 100%;
    background: #2563eb;
    border-radius: 10px;
    transition: width 0.4s ease;
}

/* Action button gradients */
.btn-premium-save {
    background: linear-gradient(135deg, #3b82f6, #4f46e5);
    color: #ffffff !important;
    border: none;
    border-radius: 10px;
    padding: 8px 20px;
    font-weight: 700;
    font-size: 0.82rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
    transition: transform 0.2s, box-shadow 0.2s;
}
.btn-premium-save:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.35);
}

.btn-premium-outline {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 8px 18px;
    font-weight: 700;
    font-size: 0.82rem;
    color: #475569;
    transition: background 0.2s;
}
.btn-premium-outline:hover { background: #f8fafc; }

/* Left steps layout */
.step-card {
    background: #ffffff;
    border: 1px solid #f0f2f5;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.02);
    margin-bottom: 20px;
    overflow: hidden;
}
.step-header {
    padding: 20px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    background: #ffffff;
}
.step-header-left {
    display: flex;
    align-items: center;
    gap: 16px;
}
.step-badge {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    font-weight: 800;
}
.step-badge.blue { background: #eff6ff; color: #2563eb; }
.step-badge.green { background: #f0fdf4; color: #16a34a; }
.step-badge.orange { background: #fff7ed; color: #ea580c; }
.step-badge.purple { background: #faf5ff; color: #9333ea; }

.step-title {
    font-size: 0.92rem;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 2px;
}
.step-desc {
    font-size: 0.72rem;
    color: #94a3b8;
    font-weight: 500;
}
.step-body {
    padding: 0 24px 24px 24px;
    border-top: 1px solid #f8fafc;
}

/* Custom Input Styling */
.form-premium-label {
    font-size: 0.76rem;
    font-weight: 700;
    color: #475569;
    margin-bottom: 6px;
    display: block;
}
.form-premium-input {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 8px 14px;
    font-size: 0.82rem;
    font-weight: 600;
    color: #334155;
    outline: none;
    width: 100%;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.form-premium-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
}
.form-premium-input::placeholder { color: #94a3b8; }

/* Status pill radios */
.status-radio-group {
    display: flex;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    background: #f8fafc;
}
.status-radio-lbl {
    flex: 1;
    text-align: center;
    padding: 8px 12px;
    font-size: 0.78rem;
    font-weight: 700;
    color: #64748b;
    cursor: pointer;
    transition: background 0.2s, color 0.2s;
    border-right: 1px solid #e2e8f0;
}
.status-radio-lbl:last-child { border-right: none; }
.status-radio-input { display: none; }
.status-radio-input:checked + .status-radio-lbl {
    background: #2563eb;
    color: #ffffff;
}

/* Custom Toggles */
.flag-toggle-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 10px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
}
.flag-toggle-circle {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}
.custom-switch {
    width: 36px;
    height: 20px;
    background: #cbd5e1;
    border-radius: 10px;
    position: relative;
    cursor: pointer;
    transition: background 0.2s;
}
.custom-switch::after {
    content: '';
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #ffffff;
    position: absolute;
    top: 3px;
    left: 3px;
    transition: transform 0.2s;
}
input[type="checkbox"]:checked + .custom-switch { background: #2563eb; }
input[type="checkbox"]:checked + .custom-switch::after { transform: translateX(16px); }

/* Right Assistant Sidebar */
.assistant-sidebar {
    position: sticky;
    top: 96px;
}
.assistant-card {
    background: #ffffff;
    border: 1px solid #f0f2f5;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    padding: 24px;
}
.assistant-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}
.assistant-spark-icon {
    width: 38px;
    height: 38px;
    background: #eff6ff;
    color: #2563eb;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
}

/* Sidebar progress card */
.sidebar-progress-card {
    background: #fafcff;
    border: 1.5px dashed #dbeafe;
    border-radius: 16px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
}
.progress-ring-label {
    position: absolute;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.missing-info-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 12px;
}
.missing-info-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.78rem;
    font-weight: 600;
    color: #475569;
}

/* Bottom Bar */
.bottom-dock-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 72px;
    background: #ffffff;
    border-top: 1.5px solid #e2e8f0;
    padding: 0 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    z-index: 999;
}
</style>

<!-- Main Form view container -->
<div class="container-fluid pb-5 mb-5">
    
    <!-- Header Row -->
    <div class="row align-items-center justify-content-between form-header-wrap">
        <div class="col-auto d-flex align-items-center gap-3">
            <a href="index.php?page=proposals" class="back-btn-circle">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h4 class="form-title mb-0"><?= $isEdit ? 'Edit Proposal' : 'Add New Proposal' ?></h4>
                <p class="form-subtitle mb-0">Enter the details to add a new proposal</p>
            </div>
        </div>
        
        <!-- Completion status & Fast buttons -->
        <div class="col-auto d-flex align-items-center gap-4">
            
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn-premium-outline" id="btn_top_draft" onclick="document.getElementById('status_draft').checked = true; document.getElementById('main_proposal_form').submit();">Save Draft</button>
                <button type="button" class="btn-premium-outline text-purple border-purple-subtle" style="background:#faf5ff;" id="btn_top_ai_fill"><i class="bi bi-stars"></i> AI Auto Fill</button>
                <button type="button" class="btn-premium-save" onclick="document.getElementById('main_proposal_form').submit();">Save Proposal</button>
            </div>
        </div>
    </div>

    <!-- Smart AI text input tab block (collapsible helper panel) -->
    <div class="card border-0 shadow-sm bg-light-subtle mb-4 rounded-4" style="border: 1.5px dashed #cbd5e1 !important;">
        <div class="card-body py-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="fw-bold text-dark small"><i class="bi bi-magic me-1"></i>AI Smart Fill Console</span>
                <span class="text-muted smaller">Paste text notes or load a PDF document</span>
            </div>
            <div class="row g-2">
                <div class="col-md-9">
                    <textarea id="ai_raw_text" class="form-control form-control-sm" rows="1" placeholder="e.g. Society redevelopment proposal in Bandra West, expected sale ₹65000/sqft, plot area 2500 sqm..."></textarea>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary btn-sm flex-grow-1" type="button" id="btn_ai_fill">
                        <span id="ai_btn_text"><i class="bi bi-stars"></i> Auto-Fill</span>
                        <span id="ai_btn_spinner" class="spinner-border spinner-border-sm d-none"></span>
                    </button>
                    <!-- PDF input wrapper -->
                    <label class="btn btn-outline-secondary btn-sm mb-0 d-inline-flex align-items-center justify-content-center cursor-pointer" style="cursor:pointer;">
                        <input type="file" id="ai_pdf_file" accept=".pdf" style="display:none;">
                        <i class="bi bi-file-earmark-pdf"></i>
                    </label>
                </div>
            </div>
            <div id="ai_feedback" class="small mt-1 d-none"></div>
        </div>
    </div>

    <!-- Main Layout Grid -->
    <form method="POST" action="index.php?page=proposal_save" id="main_proposal_form" novalidate>
        <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
        <?php endif; ?>

        <div class="row g-4">
            
            <!-- Full Width: Collapsible Form Steps -->
            <div class="col-12">
                
                <!-- STEP 1: Basic Information -->
                <div class="step-card">
                    <div class="step-header">
                        <div class="step-header-left">
                            <div class="step-badge blue"><i class="bi bi-file-earmark-text"></i></div>
                            <div>
                                <div class="step-title">1. Basic Information</div>
                                <div class="step-desc">Primary details about the proposal</div>
                            </div>
                        </div>
                        <i class="bi bi-chevron-up text-muted"></i>
                    </div>
                    <div class="step-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-premium-label">Proposal Name *</label>
                                <div class="position-relative">
                                    <input type="text" name="proposal_name" class="form-premium-input" placeholder="Enter proposal name" value="<?= e($v['proposal_name'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-premium-label">Proposal Type *</label>
                                <select name="proposal_type" class="form-premium-input">
                                    <option value="">-- Select proposal type --</option>
                                    <?php foreach (PROPOSAL_TYPES as $t): ?>
                                    <option value="<?= e($t) ?>" <?= ($v['proposal_type'] ?? '')===$t?'selected':'' ?>><?= e($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-premium-label">Date / Month *</label>
                                <input type="date" name="date_month" class="form-premium-input" value="<?= e($v['date_month'] ?? date('Y-m-d')) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Location Details -->
                <div class="step-card">
                    <div class="step-header">
                        <div class="step-header-left">
                            <div class="step-badge blue" style="background:#f0f9ff; color:#0369a1;"><i class="bi bi-geo-alt"></i></div>
                            <div>
                                <div class="step-title">2. Location Details</div>
                                <div class="step-desc">Where is this proposal located?</div>
                            </div>
                        </div>
                        <i class="bi bi-chevron-up text-muted"></i>
                    </div>
                    <div class="step-body">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-premium-label">Region / Micro-market *</label>
                                <input type="text" name="region" class="form-premium-input" placeholder="Select region / micro-market" value="<?= e($v['region'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-premium-label">City *</label>
                                <input type="text" name="city" class="form-premium-input" placeholder="Select city" value="<?= e($v['city'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-premium-label">Pincode</label>
                                <input type="text" name="pincode" class="form-premium-input" placeholder="Enter pincode" value="<?= e($v['pincode'] ?? '') ?>">
                            </div>
                            <div class="col-md-12">
                                <label class="form-premium-label">Site Address *</label>
                                <div class="input-group">
                                    <input type="text" name="site_address" class="form-premium-input" placeholder="Enter complete site address" value="<?= e($v['site_address'] ?? '') ?>">
                                    <button type="button" class="btn btn-outline-secondary btn-sm px-3 d-flex align-items-center gap-1" style="border-radius:0 10px 10px 0;"><i class="bi bi-map"></i> Pick on Map</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: Contacts & References -->
                <div class="step-card">
                    <div class="step-header">
                        <div class="step-header-left">
                            <div class="step-badge green"><i class="bi bi-people"></i></div>
                            <div>
                                <div class="step-title">3. Contacts & References</div>
                                <div class="step-desc">Add owners, references and consultant details</div>
                            </div>
                        </div>
                        <i class="bi bi-chevron-up text-muted"></i>
                    </div>
                    <div class="step-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-premium-label">Reference / Owner (Multiple names allowed)</label>
                                <div class="tags-input-wrapper border rounded-3 p-2 bg-white d-flex flex-wrap align-items-center gap-1" id="tags-wrapper" style="min-height: 38px; cursor: text;">
                                    <div id="tags-list" class="d-flex flex-wrap gap-1"></div>
                                    <input type="text" id="tag-input-field" class="border-0 p-0 flex-grow-1 bg-transparent" placeholder="Type a name & press Enter..." style="outline: none; font-size:0.8rem;">
                                </div>
                                <input type="hidden" name="reference_owner" id="reference_owner_hidden" value="<?= e($v['reference_owner'] ?? '') ?>">

                                <!-- Inline suggestions tags -->
                                <div class="mt-2" style="font-size:0.75rem;">
                                    <span class="text-muted block-title mb-1 d-block">Suggested Contacts</span>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php
                                        $suggestions = ['JLL', 'Colliers', 'Knight Frank', 'Cushman & Wakefield', 'Zapkey', 'Internal Ref- NLH Sir', 'Internal Ref- Neha Bhide', 'Internal Ref- Niranjan Sir', 'Direct Contact', 'Society Member', 'Society Secretary'];
                                        foreach ($suggestions as $sug):
                                        ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill" style="font-size: 0.72rem; border-color:#e2e8f0; color:#475569;" onclick="window.addTag('<?= e(addslashes($sug)) ?>')"><i class="bi bi-arrow-right-short"></i> <?= e($sug) ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-premium-label">Consultant Name & Number</label>
                                <input type="text" name="consultant_name_number" class="form-premium-input" placeholder="Enter consultant name and number" value="<?= e($v['consultant_name_number'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 4: Deal Information -->
                <div class="step-card">
                    <div class="step-header">
                        <div class="step-header-left">
                            <div class="step-badge orange"><i class="bi bi-briefcase"></i></div>
                            <div>
                                <div class="step-title">4. Deal Information</div>
                                <div class="step-desc">Status, cluster and other deal related flags</div>
                            </div>
                        </div>
                        <i class="bi bi-chevron-up text-muted"></i>
                    </div>
                    <div class="step-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-premium-label">Cluster</label>
                                <input type="text" name="cluster" class="form-premium-input" placeholder="Select cluster" value="<?= e($v['cluster'] ?? '') ?>">
                            </div>
                            <div class="col-md-8">
                                <label class="form-premium-label">Status / Decision *</label>
                                <?php
                                $curStatus = $v['status'] ?? 'Ready for Review';
                                if (empty($curStatus) || $curStatus === '0') {
                                    $curStatus = 'Ready for Review';
                                }
                                ?>
                                <div class="status-radio-group">
                                    <input type="radio" name="status" id="status_draft" value="Draft" class="status-radio-input" <?= $curStatus==='Draft'?'checked':'' ?>>
                                    <label for="status_draft" class="status-radio-lbl">Draft</label>

                                    <input type="radio" name="status" id="status_review" value="Ready for Review" class="status-radio-input" <?= $curStatus==='Ready for Review'?'checked':'' ?>>
                                    <label for="status_review" class="status-radio-lbl">Ready for Review</label>

                                    <input type="radio" name="status" id="status_approved" value="Accepted" class="status-radio-input" <?= $curStatus==='Accepted'?'checked':'' ?>>
                                    <label for="status_approved" class="status-radio-lbl">Approved</label>

                                    <input type="radio" name="status" id="status_rejected" value="Rejected" class="status-radio-input" <?= $curStatus==='Rejected'?'checked':'' ?>>
                                    <label for="status_rejected" class="status-radio-lbl">Rejected</label>
                                </div>
                            </div>

                            <!-- Deal Flags toggles -->
                            <div class="col-12">
                                <label class="form-premium-label mb-2">Deal Flags</label>
                                <div class="row g-2">
                                    <?php
                                    $flags = [
                                        'is_sra'      => ['SRA', '#3b82f6'],
                                        'is_outright' => ['Outright', '#22c55e'],
                                        'is_mhada'    => ['MHADA', '#a855f7'],
                                        'is_stressed' => ['Stressed', '#ef4444']
                                    ];
                                    foreach ($flags as $fld => [$lbl, $clr]):
                                    ?>
                                    <div class="col-sm-3">
                                        <div class="flag-toggle-card" onclick="document.getElementById('<?= $fld ?>').click()">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="flag-toggle-circle" style="background:<?= $clr ?>"></span>
                                                <span class="small fw-bold text-dark"><?= $lbl ?></span>
                                            </div>
                                            <div class="form-check form-switch p-0 m-0">
                                                <input type="checkbox" class="d-none" name="<?= $fld ?>" id="<?= $fld ?>" <?= !empty($v[$fld]) ? 'checked' : '' ?>>
                                                <div class="custom-switch"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 5: Remarks -->
                <div class="step-card">
                    <div class="step-header">
                        <div class="step-header-left">
                            <div class="step-badge purple"><i class="bi bi-chat-left-dots"></i></div>
                            <div>
                                <div class="step-title">5. Remarks</div>
                                <div class="step-desc">Additional notes and remarks</div>
                            </div>
                        </div>
                        <i class="bi bi-chevron-up text-muted"></i>
                    </div>
                    <div class="step-body">
                        <div>
                            <label class="form-premium-label">Remarks</label>
                            <textarea name="remarks" id="remarks_area" class="form-premium-input" rows="4" placeholder="Enter remarks, notes or any additional information..." maxlength="1000" oninput="document.getElementById('remarks_counter').textContent = this.value.length + ' / 1000'"><?= e($v['remarks'] ?? '') ?></textarea>
                            <div class="text-end text-muted smaller mt-1" id="remarks_counter">0 / 1000</div>
                        </div>
                    </div>
                </div>

                <!-- STAGE 1 Hard Filter Panel -->
                <div class="step-card">
                    <div class="step-header" style="border-left: 4px solid #ef4444;">
                        <div class="step-header-left">
                            <div class="step-badge orange" style="background:#fee2e2; color:#ef4444;"><i class="bi bi-funnel"></i></div>
                            <div>
                                <div class="step-title">Stage 1 – Hard Filter Inputs</div>
                                <div class="step-desc text-danger">Required fields for AI qualification analysis</div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-xs btn-outline-danger" id="btn_ai_stage1" style="font-size:0.7rem; font-weight:700;">
                            <span id="ai_stage1_btn_text"><i class="bi bi-magic"></i> Auto-Estimate</span>
                            <span id="ai_stage1_btn_spinner" class="spinner-border spinner-border-sm d-none"></span>
                        </button>
                    </div>
                    <div class="step-body">
                        <div id="ai_stage1_feedback" class="small px-2 pb-2"></div>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-premium-label">Plot Area (sq.m.)</label>
                                <input type="number" step="0.01" name="plot_area_sqm" class="form-premium-input" placeholder="e.g. 2500" value="<?= e($v['plot_area_sqm'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-premium-label">Avg Sale Rate (₹/sqft)</label>
                                <input type="number" step="1" name="avg_sale_price_sqft" class="form-premium-input" placeholder="e.g. 65000" value="<?= e($v['avg_sale_price_sqft'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-premium-label">Entry Cost (₹/sqft)</label>
                                <input type="number" step="1" name="entry_cost_per_sqft" class="form-premium-input" placeholder="e.g. 18000" value="<?= e($v['entry_cost_per_sqft'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-premium-label">Existing FSI Used</label>
                                <input type="number" step="0.01" name="existing_fsi_used" class="form-premium-input" placeholder="e.g. 0.75" value="<?= e($v['existing_fsi_used'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-premium-label">Free Sale Area %</label>
                                <input type="number" step="0.1" name="free_sale_area_pct" class="form-premium-input" placeholder="e.g. 60" value="<?= e($v['free_sale_area_pct'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-premium-label">Society Allocation %</label>
                                <input type="number" step="0.1" name="society_allocation_pct" class="form-premium-input" placeholder="e.g. 40" value="<?= e($v['society_allocation_pct'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-premium-label">Existing Units</label>
                                <input type="number" name="existing_units" class="form-premium-input" placeholder="e.g. 40" value="<?= e($v['existing_units'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-premium-label">Proposed Units</label>
                                <input type="number" name="proposed_units" class="form-premium-input" placeholder="e.g. 120" value="<?= e($v['proposed_units'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-premium-label">Timeline (years)</label>
                                <input type="number" step="0.5" name="completion_timeline_yrs" class="form-premium-input" placeholder="e.g. 4" value="<?= e($v['completion_timeline_yrs'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STAGE 2 Scoring Panel -->
                <div class="step-card">
                    <div class="step-header" style="border-left: 4px solid #3b82f6;">
                        <div class="step-header-left">
                            <div class="step-badge blue" style="background:#eff6ff; color:#3b82f6;"><i class="bi bi-bar-chart"></i></div>
                            <div>
                                <div class="step-title">Stage 2 – Scoring Inputs</div>
                                <div class="step-desc text-primary">Location rating and complexity parameters</div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-xs btn-outline-primary" id="btn_ai_stage2" style="font-size:0.7rem; font-weight:700;">
                            <span id="ai_stage2_btn_text"><i class="bi bi-magic"></i> Auto-Score</span>
                            <span id="ai_stage2_btn_spinner" class="spinner-border spinner-border-sm d-none"></span>
                        </button>
                    </div>
                    <div class="step-body">
                        <div id="ai_stage2_feedback" class="small px-2 pb-2"></div>
                        <div class="row g-3">
                            <?php
                            $ratingFields = [
                                'location_rating'     => ['Location Desirability Rating', '1 = poor location, 10 = premium market'],
                                'approval_complexity' => ['Approval Complexity',          '1 = simple approvals, 10 = extremely complex'],
                                'market_demand'       => ['Market Demand / Sale Velocity', '1 = low demand, 10 = very high absorption'],
                            ];
                            foreach ($ratingFields as $fname => [$flabel, $fhint]):
                                $curVal = (int)($v[$fname] ?? 5);
                            ?>
                            <div class="col-md-4">
                                <label class="form-premium-label d-flex justify-content-between">
                                    <span><?= $flabel ?></span>
                                    <span class="badge bg-primary" id="badge_<?= $fname ?>"><?= $curVal ?>/10</span>
                                </label>
                                <input type="range" class="form-range" name="<?= $fname ?>"
                                       min="1" max="10" step="1" value="<?= $curVal ?>"
                                       oninput="document.getElementById('badge_<?= $fname ?>').textContent=this.value+'/10'">
                                <div class="text-muted smaller"><?= $fhint ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </form>
</div>

<!-- Bottom Fixed Dock Bar -->
<div class="bottom-dock-bar">
    <a href="index.php?page=proposals" class="btn-premium-outline"><i class="bi bi-x-lg"></i> Cancel</a>
    <div class="d-flex align-items-center gap-1 text-muted smaller">
        <i class="bi bi-check-circle-fill text-success"></i> All changes are auto-saved Just now
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn-premium-outline" onclick="document.getElementById('status_draft').checked = true; document.getElementById('main_proposal_form').submit();">Save Draft</button>
        <button type="button" class="btn-premium-save" onclick="document.getElementById('main_proposal_form').submit();">Save Proposal</button>
    </div>
</div>

<!-- PDF.js CDN for client-side PDF parsing -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize PDF.js worker
    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
    }

    // AI Smart Fill Logic
    const btnAiFill = document.getElementById('btn_ai_fill');
    if (btnAiFill) {
        btnAiFill.addEventListener('click', async function() {
            const rawText = document.getElementById('ai_raw_text').value.trim();
            const btnText = document.getElementById('ai_btn_text');
            const btnSpinner = document.getElementById('ai_btn_spinner');
            const feedback = document.getElementById('ai_feedback');

            if (!rawText) {
                alert('Please paste some text first.');
                return;
            }

            this.disabled = true;
            if(btnText) btnText.classList.add('d-none');
            if(btnSpinner) btnSpinner.classList.remove('d-none');
            feedback.className = 'small mt-2 text-primary d-block';
            feedback.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>AI is analyzing the text...';

            try {
                const response = await fetch('index.php?page=api_ai_extract', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ text: rawText })
                });
                
                const result = await response.json();

                if (result.success && result.data) {
                    const data = result.data;
                    let filledCount = 0;

                    const fill = (name, val) => {
                        if (val === null || val === '') return;
                        const el = document.querySelector(`[name="${name}"]`);
                        if (el) {
                            el.value = val;
                            el.dispatchEvent(new Event('input')); 
                            filledCount++;
                        }
                    };

                    fill('proposal_name', data.proposal_name);
                    fill('proposal_type', data.proposal_type);
                    fill('region', data.region);
                    fill('city', data.city);
                    fill('site_address', data.site_address);
                    fill('pincode', data.pincode);
                    fill('reference_owner', data.reference_owner);
                    fill('consultant_name_number', data.consultant_name_number);
                    fill('plot_area_sqm', data.plot_area_sqm);
                    fill('avg_sale_price_sqft', data.avg_sale_price_sqft);
                    fill('entry_cost_per_sqft', data.entry_cost_per_sqft);
                    fill('existing_fsi_used', data.existing_fsi_used);
                    fill('free_sale_area_pct', data.free_sale_area_pct);
                    fill('society_allocation_pct', data.society_allocation_pct);
                    fill('existing_units', data.existing_units);
                    fill('proposed_units', data.proposed_units);
                    fill('completion_timeline_yrs', data.completion_timeline_yrs);
                    fill('remarks', data.remarks);

                    feedback.className = 'small mt-2 text-success fw-bold d-block';
                    feedback.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i>Filled ${filledCount} fields!`;
                } else {
                    throw new Error(result.error || 'Unknown error occurred.');
                }
            } catch (err) {
                feedback.className = 'small mt-2 text-danger fw-bold d-block';
                feedback.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i>Error: ${err.message}`;
            } finally {
                this.disabled = false;
                if(btnText) btnText.classList.remove('d-none');
                if(btnSpinner) btnSpinner.classList.add('d-none');
            }
        });
    }

    // AI Stage 1 Auto-Estimate Logic
    const btnAiStage1 = document.getElementById('btn_ai_stage1');
    if (btnAiStage1) {
        btnAiStage1.addEventListener('click', async function() {
            const s1BtnText = document.getElementById('ai_stage1_btn_text');
            const s1BtnSpinner = document.getElementById('ai_stage1_btn_spinner');
            const s1Feedback = document.getElementById('ai_stage1_feedback');

            const locData = {
                proposal_name: document.querySelector('[name="proposal_name"]').value.trim(),
                proposal_type: document.querySelector('[name="proposal_type"]').value.trim(),
                region: document.querySelector('[name="region"]').value.trim(),
                city: document.querySelector('[name="city"]').value.trim(),
                site_address: document.querySelector('[name="site_address"]').value.trim(),
                pincode: document.querySelector('[name="pincode"]').value.trim()
            };

            if (!locData.region && !locData.city && !locData.site_address) {
                alert('Please fill in at least the Region, City, or Site Address in Basic Information before using AI estimation.');
                return;
            }

            this.disabled = true;
            s1BtnText.classList.add('d-none');
            s1BtnSpinner.classList.remove('d-none');
            s1Feedback.className = 'small mt-1 px-3 pb-2 text-primary d-block';
            s1Feedback.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>AI is estimating Stage 1 parameters...';

            try {
                const response = await fetch('index.php?page=api_ai_stage1', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(locData)
                });
                const result = await response.json();

                if (result.success && result.data) {
                    const data = result.data;
                    let filledCount = 0;

                    const fillIfEmpty = (name, val) => {
                        if (val === null || val === '' || val === undefined) return;
                        const el = document.querySelector(`[name="${name}"]`);
                        if (el) {
                            if (!el.value || el.value === '0' || el.value === '') {
                                el.value = val;
                                el.dispatchEvent(new Event('input'));
                                filledCount++;
                            }
                        }
                    };

                    fillIfEmpty('avg_sale_price_sqft', data.avg_sale_price_sqft);
                    fillIfEmpty('entry_cost_per_sqft', data.entry_cost_per_sqft);
                    fillIfEmpty('existing_fsi_used', data.existing_fsi_used);
                    fillIfEmpty('free_sale_area_pct', data.free_sale_area_pct);
                    fillIfEmpty('society_allocation_pct', data.society_allocation_pct);
                    fillIfEmpty('completion_timeline_yrs', data.completion_timeline_yrs);

                    s1Feedback.className = 'small mt-1 px-3 pb-2 text-success fw-bold d-block';
                    s1Feedback.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i>AI estimated ${filledCount} fields!`;
                } else {
                    throw new Error(result.error || 'Unknown error.');
                }
            } catch (err) {
                s1Feedback.className = 'small mt-1 px-3 pb-2 text-danger fw-bold d-block';
                s1Feedback.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i>Error: ${err.message}`;
            } finally {
                this.disabled = false;
                s1BtnText.classList.remove('d-none');
                s1BtnSpinner.classList.add('d-none');
            }
        });
    }

    // AI Stage 2 Scoring Logic
    const btnAiStage2 = document.getElementById('btn_ai_stage2');
    if (btnAiStage2) {
        btnAiStage2.addEventListener('click', async function() {
            const s2BtnText = document.getElementById('ai_stage2_btn_text');
            const s2BtnSpinner = document.getElementById('ai_stage2_btn_spinner');
            const s2Feedback = document.getElementById('ai_stage2_feedback');

            const reqData = {
                proposal_name: document.querySelector('[name="proposal_name"]').value.trim(),
                region: document.querySelector('[name="region"]').value.trim(),
                city: document.querySelector('[name="city"]').value.trim(),
                site_address: document.querySelector('[name="site_address"]').value.trim(),
                remarks: document.querySelector('[name="remarks"]').value.trim()
            };

            this.disabled = true;
            s2BtnText.classList.add('d-none');
            s2BtnSpinner.classList.remove('d-none');
            s2Feedback.className = 'small mt-2 px-3 pb-2 text-primary d-block';
            s2Feedback.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>AI is scoring proposal...';

            try {
                const response = await fetch('index.php?page=api_ai_stage2', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(reqData)
                });
                const result = await response.json();

                if (result.success && result.data) {
                    const data = result.data;
                    const updateSlider = (name, val) => {
                        if (val === null || val === '' || val === undefined) return;
                        const el = document.querySelector(`[name="${name}"]`);
                        if (el) {
                            el.value = val;
                            el.dispatchEvent(new Event('input'));
                        }
                    };

                    updateSlider('location_rating', data.location_rating);
                    updateSlider('approval_complexity', data.approval_complexity);
                    updateSlider('market_demand', data.market_demand);

                    s2Feedback.className = 'small mt-2 px-3 pb-2 text-success fw-bold d-block';
                    s2Feedback.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i>AI scored successfully!`;
                } else {
                    throw new Error(result.error || 'Unknown error.');
                }
            } catch (err) {
                s2Feedback.className = 'small mt-2 px-3 pb-2 text-danger fw-bold d-block';
                s2Feedback.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i>Error: ${err.message}`;
            } finally {
                this.disabled = false;
                s2BtnText.classList.remove('d-none');
                s2BtnSpinner.classList.add('d-none');
            }
        });
    }

    // --- Reference / Owner Tags Input Logic ---
    const tagsWrapper = document.getElementById('tags-wrapper');
    const hiddenInput = document.getElementById('reference_owner_hidden');
    const tagsList = document.getElementById('tags-list');
    const tagInputField = document.getElementById('tag-input-field');

    let tags = [];

    function loadTags() {
        const val = hiddenInput ? hiddenInput.value.trim() : '';
        if (val) {
            tags = val.split(/[,;\n]|\s+\b(?:and|&)\b\s+/gi)
                      .map(t => t.trim())
                      .filter(t => t.length > 0);
        } else {
            tags = [];
        }
        renderTags();
    }

    function renderTags() {
        if (!tagsList || !tagInputField) return;
        tagsList.innerHTML = '';
        tags.forEach((tag, index) => {
            const badge = document.createElement('span');
            badge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle d-inline-flex align-items-center gap-1 py-1 px-2';
            badge.style.fontSize = '0.75rem';
            
            const spanText = document.createElement('span');
            spanText.textContent = tag;
            badge.appendChild(spanText);
            
            const xIcon = document.createElement('i');
            xIcon.className = 'bi bi-x text-primary ms-1 cursor-pointer';
            xIcon.style.cursor = 'pointer';
            xIcon.addEventListener('click', function(e) {
                e.stopPropagation();
                removeTag(index);
            });
            badge.appendChild(xIcon);
            
            tagsList.appendChild(badge);
        });
        
        if (tags.length > 0) {
            tagInputField.placeholder = 'Add another...';
        } else {
            tagInputField.placeholder = 'Type a name & press Enter...';
        }
    }

    function addTag(name) {
        name = name.trim();
        if (!name) return;
        if (!tags.includes(name)) {
            tags.push(name);
            updateHiddenValue();
        }
        if (tagInputField) tagInputField.value = '';
        renderTags();
    }

    function removeTag(index) {
        tags.splice(index, 1);
        updateHiddenValue();
        renderTags();
    }

    function updateHiddenValue() {
        if (hiddenInput) hiddenInput.value = tags.join(', ');
    }

    window.addTag = addTag;
    window.removeTag = removeTag;

    if (tagInputField) {
        tagInputField.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const val = this.value.trim();
                if (val) addTag(val);
            } else if (e.key === ',') {
                e.preventDefault();
                const val = this.value.trim();
                if (val) addTag(val);
            } else if (e.key === 'Backspace' && !this.value) {
                if (tags.length > 0) removeTag(tags.length - 1);
            }
        });
    }

    if (tagsWrapper && tagInputField) {
        tagsWrapper.addEventListener('click', () => {
            tagInputField.focus();
        });
    }

    if (hiddenInput) {
        hiddenInput.addEventListener('input', function() {
            loadTags();
        });
    }

    loadTags();
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
