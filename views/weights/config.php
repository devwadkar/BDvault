<?php $pageTitle = 'Scoring Weights'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<<!-- Premium Weights View Styling -->
<style>
.weights-heading {
    color: #1e293b;
    font-size: 1.55rem;
    font-weight: 800;
    letter-spacing: -0.02em;
}
.weights-subheading {
    color: #64748b;
    font-size: 0.82rem;
    font-weight: 500;
}

/* Cards customization */
.premium-card {
    background: #ffffff;
    border: 1px solid #f0f2f5;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.02);
    margin-bottom: 24px;
}
.premium-card-header {
    padding: 20px 24px 0 24px;
    font-size: 0.95rem;
    font-weight: 800;
    color: #1e293b;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.premium-card-body {
    padding: 20px 24px 24px 24px;
}

/* Custom premium input fields */
.weights-input-lbl {
    font-size: 0.76rem;
    font-weight: 700;
    color: #475569;
    margin-bottom: 6px;
    display: block;
}
.weights-input-field {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 6px 12px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #334155;
    outline: none;
    width: 100%;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.weights-input-field:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
}

/* Action button configurations */
.btn-weights-active {
    background: linear-gradient(135deg, #3b82f6, #4f46e5);
    color: #ffffff !important;
    border: none;
    border-radius: 10px;
    padding: 10px 20px;
    font-weight: 700;
    font-size: 0.8rem;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
    transition: transform 0.2s, box-shadow 0.2s;
}
.btn-weights-active:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.35);
}

.btn-weights-rerun {
    background: #f59e0b;
    color: #ffffff !important;
    border: none;
    border-radius: 10px;
    padding: 10px 20px;
    font-weight: 700;
    font-size: 0.8rem;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
    transition: transform 0.2s, box-shadow 0.2s;
}
.btn-weights-rerun:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(245, 158, 11, 0.3);
}

.btn-weights-outline {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 20px;
    font-weight: 700;
    font-size: 0.8rem;
    color: #475569;
    transition: background 0.2s;
}
.btn-weights-outline:hover { background: #f8fafc; }

/* Config listing item */
.cfg-list-tile {
    padding: 12px 20px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.cfg-list-tile:last-child { border-bottom: none; }
</style>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="weights-heading mb-0">Scoring Configuration</h4>
        <p class="weights-subheading mb-0">Adjust Stage 1 thresholds and Stage 2 weights. Activating re-runs all classifications.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Active config editor -->
    <div class="col-lg-8">
        <form method="POST" action="index.php?page=weights_save" id="weightsForm">
            <input type="hidden" name="id" value="<?= (int)$active['id'] ?>">

            <!-- Stage 1 thresholds -->
            <div class="card border-0 premium-card">
                <div class="premium-card-header">
                    <span><i class="bi bi-funnel text-danger me-2"></i>Stage 1 – Hard Filter Thresholds</span>
                </div>
                <div class="premium-card-body">
                    <div class="row g-3">
                        <?php
                        $s1fields = [
                            'min_sale_rate'         => ['Min Sale Rate (₹/sqft)', 'number', 1, '₹', ''],
                            'max_fsi_used'          => ['Max FSI Used',           'number', 0.01, '', '≤ this passes'],
                            'min_free_sale_pct'     => ['Min Free Sale Area (%)', 'number', 0.1,  '', '%'],
                            'min_density_ratio'     => ['Min Density Ratio',      'number', 0.1,  '', 'proposed/existing ratio'],
                            'max_timeline_years'    => ['Max Timeline (years)',   'number', 0.5,  '', '≤ this passes'],
                            'min_profit_margin_pct' => ['Min Profit Margin (%)', 'number', 0.1,  '', '%'],
                        ];
                        foreach ($s1fields as $fname => [$flabel, $ftype, $fstep, $prepend, $hint]):
                        ?>
                        <div class="col-md-4">
                            <label class="weights-input-lbl"><?= $flabel ?></label>
                            <div class="input-group input-group-sm">
                                <?php if ($prepend): ?><span class="input-group-text bg-light text-muted" style="border-radius:10px 0 0 10px; border-right:none; font-weight:700;"><?= $prepend ?></span><?php endif; ?>
                                <input type="<?= $ftype ?>" step="<?= $fstep ?>" name="<?= $fname ?>"
                                       class="weights-input-field" style="<?= $prepend?'border-radius:0 10px 10px 0;':'' ?>" value="<?= e($active[$fname]) ?>">
                            </div>
                            <?php if ($hint): ?><div class="text-muted mt-1 smaller" style="font-size:0.7rem"><?= $hint ?></div><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Stage 2 weights -->
            <div class="card border-0 premium-card">
                <div class="premium-card-header">
                    <span><i class="bi bi-sliders text-primary me-2"></i>Stage 2 – Scoring Weights</span>
                    <span class="badge bg-primary-subtle text-primary px-3 py-1.5" id="weightTotal" style="font-size:0.75rem; font-weight:700;">
                        Total: <span id="wSum">100</span>%
                    </span>
                </div>
                <div class="premium-card-body">
                    <div class="alert alert-info py-2 small border-0 bg-light-subtle rounded-3 mb-4" style="border-left:4px solid #0dcaf0 !important;">
                        <i class="bi bi-info-circle me-1"></i>
                        Weights must sum to exactly <strong>100</strong>. Use the sliders or type values.
                    </div>
                    <?php
                    $w2fields = [
                        'weight_profit_margin'  => ['Profit Margin Potential', 25],
                        'weight_location'       => ['Location Desirability',   20],
                        'weight_risk'           => ['Risk Assessment',         20],
                        'weight_cost_efficiency'=> ['Cost Efficiency',         20],
                        'weight_sale_velocity'  => ['Sale Velocity / Market Demand', 15],
                    ];
                    foreach ($w2fields as $fname => [$flabel, $default]):
                        $curVal = (float)($active[$fname] ?? $default);
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="weights-input-lbl mb-0" style="font-size: 0.8rem; font-weight: 600;"><?= $flabel ?></label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="range" class="form-range weight-slider" name="<?= $fname ?>"
                                       min="0" max="60" step="1" value="<?= $curVal ?>"
                                       style="width:140px"
                                       oninput="syncWeight(this)">
                                <input type="number" class="weights-input-field text-center"
                                       style="width:65px; padding: 4px;" min="0" max="60"
                                       value="<?= $curVal ?>"
                                       oninput="syncWeight(this)" data-pair="<?= $fname ?>">
                                <span class="text-muted small">%</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <hr class="my-4">
                    <!-- Category thresholds -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="weights-input-lbl">High Potential threshold (score ≥)</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="threshold_high" class="weights-input-field" style="border-radius: 10px 0 0 10px;" step="1"
                                       value="<?= e($active['threshold_high']) ?>">
                                <span class="input-group-text bg-light text-muted" style="border-radius:0 10px 10px 0; border-left:none;">/100</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="weights-input-lbl">Moderate threshold (score ≥)</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="threshold_moderate" class="weights-input-field" style="border-radius: 10px 0 0 10px;" step="1"
                                       value="<?= e($active['threshold_moderate']) ?>">
                                <span class="input-group-text bg-light text-muted" style="border-radius:0 10px 10px 0; border-left:none;">/100</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Config name description -->
            <div class="card border-0 premium-card">
                <div class="premium-card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="weights-input-lbl">Config Name</label>
                            <input type="text" name="config_name" class="weights-input-field" placeholder="Enter configuration name" value="<?= e($active['config_name']) ?>">
                        </div>
                        <div class="col-md-7">
                            <label class="weights-input-lbl">Description</label>
                            <input type="text" name="description" class="weights-input-field" placeholder="Enter description details" value="<?= e($active['description'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions row -->
            <div class="d-flex gap-2 flex-wrap mb-4">
                <button type="submit" name="set_active" value="1" class="btn-weights-active">
                    <i class="bi bi-save me-1"></i>Save & Activate
                </button>
                <button type="submit" name="set_active" value="1"
                        onclick="document.querySelector('[name=rerun_all]').value='1'"
                        class="btn-weights-rerun">
                    <i class="bi bi-arrow-repeat me-1"></i>Save, Activate & Re-run All
                </button>
                <button type="submit" class="btn-weights-outline">
                    <i class="bi bi-save me-1"></i>Save as New Config
                </button>
                <input type="hidden" name="rerun_all" value="0">
            </div>

        </form>
    </div>

    <!-- Config list -->
    <div class="col-lg-4">
        <div class="card border-0 premium-card">
            <div class="premium-card-header" style="padding-bottom:12px; border-bottom:1px solid #f1f5f9;">
                <span><i class="bi bi-archive me-2"></i>Saved Configurations</span>
            </div>
            <div class="premium-card-body p-0">
                <?php foreach ($configs as $cfg): ?>
                <div class="cfg-list-tile">
                    <div>
                        <div class="fw-bold text-dark" style="font-size:0.8rem;"><?= e($cfg['config_name']) ?></div>
                        <div class="text-muted smaller" style="font-size:0.7rem;"><?= e($cfg['description'] ?? '—') ?></div>
                    </div>
                    <div class="d-flex gap-1">
                        <?php if ($cfg['is_active']): ?>
                        <span class="badge bg-success-subtle text-success px-2 py-1" style="font-size:0.65rem; font-weight:700;">Active</span>
                        <?php else: ?>
                        <a href="index.php?page=weights_activate&id=<?= $cfg['id'] ?>&rerun=1"
                           class="btn btn-outline-success btn-xs py-0.5 px-2" style="font-size:0.65rem; font-weight:700;"
                           onclick="return confirm('Activate this config and re-run all classifications?')">
                            Activate
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($configs)): ?>
                <p class="text-muted small p-3 mb-0">No configs saved yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Scoring explanation card -->
        <div class="card border-0 premium-card" style="background: #fafcff; border: 1.5px dashed #dbeafe !important;">
            <div class="premium-card-header">
                <span><i class="bi bi-question-circle text-primary me-2"></i>How Scores Work</span>
            </div>
            <div class="premium-card-body small text-muted" style="line-height: 1.5; font-size: 0.76rem;">
                <p class="mb-2"><strong>Stage 1</strong> applies hard filters. Failing any one filter rejects the proposal.</p>
                <p class="mb-2"><strong>Stage 2</strong> scores each dimension 0–100, then computes a weighted average.</p>
                <p class="mb-3"><strong>Probability</strong> uses a logistic function:<br>
                   <code class="bg-white p-1 rounded border d-block text-center my-1.5" style="font-size:0.72rem;">P = 100 / (1 + e<sup>-0.1×(score−50)</sup>)</code>
                   Score 50 → ~50%, Score 70 → ~88%, Score 80 → ~95%.
                </p>
                <p class="mb-0"><strong>Categories:</strong><br>
                   Score ≥ <?= $active['threshold_high'] ?> → High Potential<br>
                   Score ≥ <?= $active['threshold_moderate'] ?> → Moderate<br>
                   Score &lt; <?= $active['threshold_moderate'] ?> → Low
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function syncWeight(el) {
    const val = parseFloat(el.value) || 0;
    const row = el.closest('.mb-3');
    const slider = row.querySelector('input[type=range]');
    const number = row.querySelector('input[type=number]');
    if (slider) slider.value = val;
    if (number) number.value = val;
    updateTotal();
}

function updateTotal() {
    const sliders = document.querySelectorAll('.weight-slider');
    let sum = 0;
    sliders.forEach(s => sum += parseFloat(s.value) || 0);
    const el = document.getElementById('wSum');
    el.textContent = sum.toFixed(0);
    const badge = document.getElementById('weightTotal');
    badge.className = Math.abs(sum - 100) < 0.5
        ? 'badge bg-success-subtle text-success px-3 py-1.5'
        : 'badge bg-danger-subtle text-danger px-3 py-1.5';
}

document.addEventListener('DOMContentLoaded', updateTotal);
</script>DOMContentLoaded', updateTotal);
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
