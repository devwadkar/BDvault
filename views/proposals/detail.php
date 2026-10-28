<?php $pageTitle = e($proposal['proposal_name']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<?php
$colours  = CATEGORY_COLOURS;
$s1       = $proposal['is_eligible'];
$cat      = $proposal['category'] ?? '';
$score    = $proposal['total_score'] ?? null;
$prob     = $proposal['probability_pct'] ?? null;
?>

<!-- Header -->
<div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <a href="index.php?page=proposals" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i>Back to list
        </a>
        <h4 class="fw-bold mb-0 mt-1"><?= e($proposal['proposal_name']) ?></h4>
        <div class="text-muted small"><?= e($proposal['proposal_type'] ?? '') ?> &bull; <?= e($proposal['region'] ?? '') ?>, <?= e($proposal['city'] ?? '') ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php?page=proposal_report&id=<?= $proposal['id'] ?>"
           class="btn btn-sm btn-primary">
            <i class="bi bi-file-earmark-pdf me-1"></i>Download Report
        </a>
        <a href="index.php?page=documents&proposal_id=<?= $proposal['id'] ?>"
           class="btn btn-sm btn-outline-warning">
            <i class="bi bi-file-earmark-text me-1"></i>Documents &amp; DA Extraction
        </a>
        <?php if (can('reclassify_proposals')): ?>
        <a href="index.php?page=reclassify&id=<?= $proposal['id'] ?>"
           class="btn btn-sm btn-outline-warning">
            <i class="bi bi-arrow-repeat me-1"></i>Re-classify
        </a>
        <?php endif; ?>
        <?php if (can('edit_proposals')): ?>
        <a href="index.php?page=proposal_edit&id=<?= $proposal['id'] ?>"
           class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <?php endif; ?>
        <?php if (can('delete_proposals')): ?>
        <a href="index.php?page=proposal_delete&id=<?= $proposal['id'] ?>"
           class="btn btn-sm btn-outline-danger"
           onclick="return confirm('Delete this proposal?')">
            <i class="bi bi-trash me-1"></i>Delete
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Review / Decision Control Panel -->
<?php
$status = $proposal['status'] ?? 'Ready for Review';
if (empty($status) || $status === '0') {
    $status = 'Ready for Review';
}
?>
<div class="card border-0 shadow-sm mb-4 border-start border-4 border-<?= $status === 'Accepted' ? 'success' : ($status === 'Rejected' ? 'danger' : 'warning') ?>">
    <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <?php if ($status === 'Accepted'): ?>
                <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="bi bi-check-lg fs-5"></i>
                </div>
                <div>
                    <h6 class="mb-0 fw-bold text-success">Proposal Accepted</h6>
                    <small class="text-muted">Ready for post-proposal planning. Convert to a formal Area Statement below.</small>
                </div>
                <?php if (can('edit_proposals')): ?>
                <form method="POST" action="index.php?page=area_analysis_start" class="ms-auto d-inline">
                    <input type="hidden" name="proposal_id" value="<?= (int)$proposal['id'] ?>">
                    <button type="submit" class="btn btn-primary fw-semibold btn-sm px-3 shadow-sm" style="background:linear-gradient(135deg,#4f46e5,#6366f1);border:0;">
                        <i class="bi bi-diagram-3-fill me-1"></i>Start Area Analysis
                    </button>
                </form>
                <?php endif; ?>
            <?php elseif ($status === 'Rejected'): ?>
                <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="bi bi-x-lg fs-5"></i>
                </div>
                <div>
                    <h6 class="mb-0 fw-bold text-danger">Proposal Rejected</h6>
                    <small class="text-muted">This proposal was marked as rejected by the admin/user.</small>
                </div>
            <?php else: ?>
                <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="bi bi-clock-history fs-5"></i>
                </div>
                <div>
                    <h6 class="mb-0 fw-bold text-dark">Ready for Review</h6>
                    <small class="text-muted">Automated filtering & feasibility scoring are ready. Make a final decision below.</small>
                </div>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
            <?php if (can('decide_proposals')): ?>
            <?php if ($status === 'Ready for Review'): ?>
                <a href="index.php?page=proposal_decision&id=<?= $proposal['id'] ?>&decision=Accepted" 
                   class="btn btn-success fw-semibold btn-sm px-3 shadow-sm">
                    <i class="bi bi-check-circle me-1"></i>Accept Proposal
                </a>
                <a href="index.php?page=proposal_decision&id=<?= $proposal['id'] ?>&decision=Rejected" 
                   class="btn btn-outline-danger fw-semibold btn-sm px-3">
                    <i class="bi bi-x-circle me-1"></i>Reject Proposal
                </a>
            <?php else: ?>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle fw-semibold px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-pencil-square me-1"></i>Change Decision
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                        <li>
                            <a class="dropdown-item py-2 d-flex align-items-center gap-2 <?= $status === 'Ready for Review' ? 'active' : '' ?>" 
                               href="index.php?page=proposal_decision&id=<?= $proposal['id'] ?>&decision=Ready for Review">
                                <i class="bi bi-clock-history text-warning"></i> Reset to Ready for Review
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 d-flex align-items-center gap-2 <?= $status === 'Accepted' ? 'active' : '' ?>" 
                               href="index.php?page=proposal_decision&id=<?= $proposal['id'] ?>&decision=Accepted">
                                <i class="bi bi-check-circle-fill text-success"></i> Mark as Accepted
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 d-flex align-items-center gap-2 <?= $status === 'Rejected' ? 'active' : '' ?>" 
                               href="index.php?page=proposal_decision&id=<?= $proposal['id'] ?>&decision=Rejected">
                                <i class="bi bi-x-circle-fill text-danger"></i> Mark as Rejected
                            </a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
            <?php endif; // can('decide_proposals') ?>
        </div>
    </div>
</div>

<!-- Score hero -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 text-center p-3">
            <?php if ($s1 === null): ?>
                <div class="text-muted fs-1"><i class="bi bi-hourglass"></i></div>
                <div class="fw-semibold text-muted mt-2">Not yet classified</div>
            <?php elseif (!$s1): ?>
                <div class="text-danger fs-1"><i class="bi bi-x-octagon-fill"></i></div>
                <div class="fw-bold text-danger fs-5 mt-2">Stage 1 Rejected</div>
                <div class="text-muted small">Did not pass hard filters</div>
            <?php else: ?>
                <div class="text-success fs-1"><i class="bi bi-check-circle-fill"></i></div>
                <div class="fw-bold text-success fs-5 mt-2">Stage 1 Passed</div>
                <div class="text-muted small">Eligible for scoring</div>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($score !== null): ?>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 text-center p-3">
            <div class="small text-muted mb-1">Feasibility Score</div>
            <div class="display-4 fw-bold text-primary"><?= number_format($score, 1) ?></div>
            <div class="text-muted small mb-2">out of 100</div>
            <div class="progress mx-auto" style="height:8px;max-width:120px">
                <div class="progress-bar bg-primary" style="width:<?= $score ?>%"></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 text-center p-3">
            <div class="small text-muted mb-1">Probability of Success</div>
            <div class="display-4 fw-bold text-success"><?= $prob ?>%</div>
            <div class="mt-2">
                <span class="badge fs-6 bg-<?= $colours[$cat] ?? 'secondary' ?>-subtle text-<?= $colours[$cat] ?? 'secondary' ?>">
                    <?= e($cat) ?>
                </span>
            </div>
        </div>
    </div>
    <?php elseif ($s1): ?>
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100 p-4 text-center text-muted">
            <i class="bi bi-calculator fs-1 mb-2"></i>
            <p>Scoring data is missing. Ensure the proposal has <strong>location_rating</strong>,
            <strong>approval_complexity</strong>, and <strong>market_demand</strong> filled in,
            then <a href="index.php?page=reclassify&id=<?= $proposal['id'] ?>">re-classify</a>.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row g-3">

    <!-- Stage 1 details -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold border-0 pt-3">
                <i class="bi bi-funnel me-1"></i>Stage 1 – Hard Filter Results
            </div>
            <div class="card-body">
            <?php if ($s1 === null): ?>
                <p class="text-muted">Not yet evaluated.</p>
            <?php else:
                $filters_map = [
                    'filter_sale_rate'        => ['Carpet Sale Rate ≥ ₹60,000/sqft',   $proposal['eval_sale_rate'],          'formatINR'],
                    'filter_fsi'              => ['Existing FSI Used ≤ 1',              $proposal['eval_fsi_used'],           null],
                    'filter_free_sale_area'   => ['Free Sale Area ≥ 60%',               $proposal['eval_free_sale_area_pct'], 'formatPct'],
                    'filter_density'          => ['Density Ratio ≥ 2.5× (prop/exist)', $proposal['eval_density_ratio'],      null],
                    'filter_timeline'         => ['Completion ≤ 5 years',               $proposal['eval_timeline_yrs'],       null],
                    'filter_cost_feasibility' => ['Profit Margin ≥ 20%',               $proposal['eval_profit_margin_pct'],  'formatPct'],
                ];
                foreach ($filters_map as $key => [$label, $val, $fmt]):
                    $result = $proposal[$key] ?? null;
                    if ($result === null) $badge = '<span class="badge bg-secondary-subtle text-secondary">N/A</span>';
                    elseif ($result)      $badge = '<span class="badge bg-success-subtle text-success"><i class="bi bi-check me-1"></i>Pass</span>';
                    else                  $badge = '<span class="badge bg-danger-subtle text-danger"><i class="bi bi-x me-1"></i>Fail</span>';
                    $display = $val !== null ? ($fmt ? $fmt((float)$val) : number_format((float)$val, 2)) : '—';
            ?>
                <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                    <div>
                        <div class="fw-semibold small"><?= $label ?></div>
                        <div class="text-muted small">Actual: <strong><?= $display ?></strong></div>
                    </div>
                    <?= $badge ?>
                </div>
            <?php endforeach; ?>

            <?php if ($reasons): ?>
                <div class="mt-3">
                    <div class="fw-semibold small mb-1 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Rejection Reasons</div>
                    <?php foreach ($reasons as $r): ?>
                    <div class="alert alert-danger py-1 px-2 small mb-1"><?= e($r) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stage 2 breakdown -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold border-0 pt-3">
                <i class="bi bi-graph-up me-1"></i>Stage 2 – Score Breakdown
            </div>
            <div class="card-body">
            <?php if (empty($breakdown)): ?>
                <p class="text-muted"><?= $s1 ? 'Score not yet computed.' : 'Not applicable — proposal did not pass Stage 1.' ?></p>
            <?php else:
                $dimCols = [
                    'profit_margin'   => ['score_profit_margin',   'weight_profit_margin'],
                    'location'        => ['score_location',         'weight_location'],
                    'risk'            => ['score_risk',             'weight_risk'],
                    'cost_efficiency' => ['score_cost_efficiency',  'weight_cost_efficiency'],
                    'sale_velocity'   => ['score_sale_velocity',    'weight_sale_velocity'],
                ];
                $weightsUsed = $proposal['weights_used'] ? json_decode($proposal['weights_used'], true) : [];
                foreach ($breakdown as $key => $dim):
                    $s = (float)($proposal['score_' . $key] ?? $dim['score'] ?? 0);
                    $w = (float)($weightsUsed[$key] ?? $dim['weight'] ?? 0);
                    $barColour = $s >= 70 ? 'success' : ($s >= 40 ? 'warning' : 'danger');
            ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-semibold"><?= e($dim['label']) ?></span>
                        <span class="text-muted">
                            <strong class="text-<?= $barColour ?>"><?= number_format($s, 1) ?>/100</strong>
                            &nbsp;&times;&nbsp;<?= $w ?>% weight
                        </span>
                    </div>
                    <div class="progress mb-1" style="height:8px">
                        <div class="progress-bar bg-<?= $barColour ?>" style="width:<?= $s ?>%"></div>
                    </div>
                    <div class="text-muted" style="font-size:0.75rem">
                        <?= e($dim['explanation'] ?? '') ?>
                        <?php if (!empty($dim['raw_value'])): ?>
                        &nbsp;&bull;&nbsp;<strong><?= e($dim['raw_value']) ?></strong>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

                <!-- Radar chart -->
                <canvas id="radarChart" style="max-height:220px;margin-top:1rem"></canvas>
            <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- AI Market Analysis -->
<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-transparent fw-semibold border-0 pt-3 d-flex justify-content-between align-items-center">
        <div><i class="bi bi-robot me-1 text-primary"></i>AI Market Analysis</div>
        <div class="d-flex gap-2">
            <?php if ($marketAnalysis): ?>
                <button id="btnFullAnalysis" class="btn btn-sm btn-outline-primary" onclick="viewFullAnalysis(<?= $proposal['id'] ?>)">
                    <i class="bi bi-file-text me-1"></i>Detailed Analysis
                </button>
            <?php endif; ?>
            <?php if (!$marketAnalysis): ?>
                <button id="btnGenerateAnalysis" class="btn btn-sm btn-primary" onclick="generateMarketAnalysis(<?= $proposal['id'] ?>)">
                    <i class="bi bi-magic me-1"></i>Generate Analysis
                </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body" id="marketAnalysisContainer">
        <?php if ($marketAnalysis): ?>
            <div class="row g-4">
                <div class="col-md-4 text-center border-end">
                    <h1 class="display-3 text-primary fw-bold mb-0"><?= number_format($marketAnalysis['total_score'], 1) ?></h1>
                    <div class="text-muted small">Market Desirability Score / 100</div>
                    <div class="mt-3 small text-start px-2">
                        <?= nl2br(e($marketAnalysis['analysis_details'] ?? '')) ?>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="row g-3">
                        <?php 
                        $rawAnalysis = $marketAnalysis['raw_json_response'] ? json_decode($marketAnalysis['raw_json_response'], true) : [];
                        $maParams = [
                            'Connectivity' => ['connectivity_score', 20, 'connectivity'],
                            'Infrastructure' => ['infrastructure_score', 15, 'infrastructure'],
                            'Demand' => ['demand_score', 20, 'demand'],
                            'Income Levels' => ['income_score', 10, 'income'],
                            'Future Dev' => ['future_dev_score', 15, 'future_dev'],
                            'Competition' => ['competition_score', 10, 'competition'],
                            'Regulatory Ease' => ['regulatory_score', 10, 'regulatory'],
                        ];
                        foreach ($maParams as $label => [$key, $weight, $reasonKey]):
                            $s = (float)($marketAnalysis[$key] ?? 0);
                            $pct = ($s / $weight) * 100;
                            $bg = $pct >= 80 ? 'success' : ($pct >= 50 ? 'warning' : 'danger');
                            $reasonText = $rawAnalysis['reasons'][$reasonKey] ?? '';
                        ?>
                        <div class="col-md-6 mb-2">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="fw-semibold"><?= $label ?></span>
                                <span class="text-muted"><strong><?= number_format($s, 1) ?></strong> / <?= $weight ?></span>
                            </div>
                            <div class="progress mb-1" style="height: 6px;">
                                <div class="progress-bar bg-<?= $bg ?>" style="width: <?= $pct ?>%"></div>
                            </div>
                            <?php if ($reasonText): ?>
                            <div class="text-muted mt-1" style="font-size: 0.8rem; line-height: 1.3;">
                                <i><?= e($reasonText) ?></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center text-muted py-4" id="maPlaceholder">
                <p class="mb-0">Run an AI-powered market analysis for this location based on 7 key desirability factors.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function generateMarketAnalysis(proposalId) {
    const btn = document.getElementById('btnGenerateAnalysis');
    const container = document.getElementById('marketAnalysisContainer');
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Analyzing...';
    
    fetch('index.php?page=api_market_analysis_generate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ proposal_id: proposalId })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            window.location.reload();
        } else {
            alert('Error: ' + data.error);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-magic me-1"></i>Generate Analysis';
        }
    })
    .catch(err => {
        alert('Network Error: ' + err);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-magic me-1"></i>Generate Analysis';
    });
}
</script>

<!-- Proposal raw data -->
<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-transparent fw-semibold border-0 pt-3">
        <i class="bi bi-database me-1"></i>Proposal Data
    </div>
    <div class="card-body">
        <div class="row g-2 small">
            <?php
            $fields = [
                'Plot Area (sqm)'         => number_format((float)$proposal['plot_area_sqm'], 2) . ' sqm',
                'Avg Sale Rate'           => $proposal['avg_sale_price_sqft'] ? formatINR((float)$proposal['avg_sale_price_sqft']) . '/sqft' : '—',
                'Entry Cost'              => $proposal['entry_cost_per_sqft'] ? formatINR((float)$proposal['entry_cost_per_sqft']) . '/sqft' : '—',
                'Existing FSI Used'       => $proposal['existing_fsi_used'] ?? '—',
                'Free Sale Area %'        => $proposal['free_sale_area_pct'] ? formatPct((float)$proposal['free_sale_area_pct']) : '—',
                'Society Allocation %'    => $proposal['society_allocation_pct'] ? formatPct((float)$proposal['society_allocation_pct']) : '—',
                'Existing Units'          => $proposal['existing_units'] ?? '—',
                'Proposed Units'          => $proposal['proposed_units'] ?? '—',
                'Timeline'                => $proposal['completion_timeline_yrs'] ? $proposal['completion_timeline_yrs'] . ' yrs' : '—',
                'Location Rating'         => ($proposal['location_rating'] ?? '—') . '/10',
                'Approval Complexity'     => ($proposal['approval_complexity'] ?? '—') . '/10',
                'Market Demand'           => ($proposal['market_demand'] ?? '—') . '/10',
                'Reference / Owner'       => $proposal['reference_owner'] ?? '—',
                'Consultant Name & Number'=> $proposal['consultant_name_number'] ?? '—',
                'Status'                  => $proposal['status'] ?? '—',
                'Cluster'                 => $proposal['cluster'] ?? '—',
                'Remarks'                 => $proposal['remarks'] ?? '—',
            ];
            foreach ($fields as $label => $val):
            ?>
            <div class="col-6 col-md-3">
                <div class="text-muted"><?= $label ?></div>
                <div class="fw-semibold"><?= e((string)$val) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if (!empty($breakdown)): ?>
<script>
new Chart(document.getElementById('radarChart'), {
    type: 'radar',
    data: {
        labels: ['Profit Margin','Location','Risk','Cost Efficiency','Sale Velocity'],
        datasets: [{
            label: 'Score',
            data: [
                <?= $proposal['score_profit_margin'] ?? 0 ?>,
                <?= $proposal['score_location'] ?? 0 ?>,
                <?= $proposal['score_risk'] ?? 0 ?>,
                <?= $proposal['score_cost_efficiency'] ?? 0 ?>,
                <?= $proposal['score_sale_velocity'] ?? 0 ?>
            ],
            backgroundColor: 'rgba(13,110,253,0.15)',
            borderColor: '#0d6efd',
            pointBackgroundColor: '#0d6efd',
        }]
    },
    options: {
        scales: { r: { min: 0, max: 100, ticks: { stepSize: 20 } } },
        plugins: { legend: { display: false } }
    }
});
</script>
<?php endif; ?>

<!-- Detailed Market Analysis Modal -->
<div class="modal fade" id="fullAnalysisModal" tabindex="-1" aria-labelledby="fullAnalysisModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title fw-bold text-dark" id="fullAnalysisModalLabel">
                    <i class="bi bi-file-earmark-bar-graph text-primary me-2"></i>Detailed Market Analysis & Case Study
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="fullAnalysisContent">
                <!-- Loaded dynamically -->
                <div class="text-center py-5 text-muted">
                    <span class="spinner-border spinner-border-sm me-2 text-primary" role="status"></span>
                    Generating full report...
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="printFullAnalysis()">
                    <i class="bi bi-printer me-1"></i>Print / Save as PDF
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let fullReportHTML = "";

function viewFullAnalysis(proposalId) {
    const modalEl = document.getElementById('fullAnalysisModal');
    const modal = new bootstrap.Modal(modalEl);
    const content = document.getElementById('fullAnalysisContent');
    const btn = document.getElementById('btnFullAnalysis');
    
    // Reset modal content
    content.innerHTML = `
        <div class="text-center py-5 text-muted">
            <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
            <p class="mb-0 fw-semibold">Generating full analysis report & case study...</p>
            <small class="text-muted">This may take a moment as our AI processes micro-market data.</small>
        </div>
    `;
    modal.show();

    fetch('index.php?page=api_market_analysis_full', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ proposal_id: proposalId })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            fullReportHTML = data.full_analysis_text;
            content.innerHTML = `
                <div class="report-header d-flex justify-content-between align-items-start border-bottom pb-3 mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <img src="assets/logo.png" alt="Hiranandani Logo" style="height: 60px; object-fit: contain;">
                        <div>
                            <h4 class="fw-bold mb-0 text-dark">${<?= json_encode($proposal['proposal_name']) ?>}</h4>
                            <div class="text-muted small">${<?= json_encode(($proposal['region'] ?? '') . ', ' . ($proposal['city'] ?? '')) ?>}</div>
                            <h6 class="text-primary fw-bold mt-1 mb-0" style="font-size: 0.85rem; letter-spacing: 0.5px;">MARKET ANALYSIS & CASE STUDY</h6>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-secondary-subtle text-secondary px-3 py-2 fw-semibold border" style="border-radius: 4px; font-size: 0.9rem;">BDvault</span>
                    </div>
                </div>
                <div class="report-body">
                    ${fullReportHTML}
                </div>
            `;
        } else {
            content.innerHTML = `
                <div class="alert alert-danger m-3" role="alert">
                    <h6 class="alert-heading fw-bold"><i class="bi bi-exclamation-octagon me-2"></i>Error Generating Report</h6>
                    <p class="mb-0">${data.error}</p>
                </div>
            `;
        }
    })
    .catch(err => {
        content.innerHTML = `
            <div class="alert alert-danger m-3" role="alert">
                <h6 class="alert-heading fw-bold"><i class="bi bi-wifi-off me-2"></i>Network Error</h6>
                <p class="mb-0">Failed to connect to the server. Please try again. (${err})</p>
            </div>
        `;
    });
}

function printFullAnalysis() {
    if (!fullReportHTML) {
        alert("Report content is not loaded yet.");
        return;
    }
    
    const printWindow = window.open('', '_blank', 'width=900,height=800');
    printWindow.document.write(`
        <html>
        <head>
            <title>Market Analysis & Case Study - <?= e($proposal['proposal_name']) ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body {
                    font-family: 'Inter', system-ui, -apple-system, sans-serif;
                    color: #333;
                    padding: 40px;
                    line-height: 1.6;
                }
                .report-header {
                    border-bottom: 2px solid #0d6efd;
                    padding-bottom: 20px;
                    margin-bottom: 30px;
                }
                h1, h2, h3, h4, h5, h6 {
                    color: #111;
                    font-weight: 700;
                    margin-top: 1.5rem;
                    margin-bottom: 1rem;
                }
                h1 { font-size: 2.2rem; }
                h2 { font-size: 1.8rem; border-bottom: 1px solid #eee; padding-bottom: 8px; }
                h3 { font-size: 1.4rem; }
                table {
                    margin-top: 20px;
                    margin-bottom: 20px;
                    font-size: 0.95rem;
                }
                th {
                    background-color: #f8f9fa !important;
                    font-weight: 600;
                }
                @media print {
                    body {
                        padding: 0;
                    }
                    .no-print {
                        display: none;
                    }
                    a {
                        text-decoration: none;
                        color: #000;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="report-header d-flex justify-content-between align-items-start pb-3 mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <img src="assets/logo.png" alt="Hiranandani Logo" style="height: 60px; object-fit: contain;">
                        <div>
                            <h4 class="fw-bold mb-0 text-dark">${<?= json_encode($proposal['proposal_name']) ?>}</h4>
                            <div class="text-muted small">${<?= json_encode(($proposal['region'] ?? '') . ', ' . ($proposal['city'] ?? '')) ?>}</div>
                            <h6 class="text-primary fw-bold mt-1 mb-0" style="font-size: 0.85rem; letter-spacing: 0.5px;">MARKET ANALYSIS & CASE STUDY</h6>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-secondary-subtle text-secondary px-3 py-2 fw-semibold border" style="border-radius: 4px; font-size: 0.9rem; display: inline-block;">BDvault</span>
                        <div class="mt-2 no-print">
                            <button class="btn btn-sm btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print Report</button>
                        </div>
                    </div>
                </div>
                <div class="report-content">
                    ${fullReportHTML}
                </div>
                <div class="mt-5 pt-3 border-top text-center text-muted small">
                    Generated automatically via Property Management System (PMS) AI Core on ${new Date().toLocaleDateString()}
                </div>
            </div>
            <script>
                // Auto trigger print when loaded
                window.onload = function() {
                    // Give a small delay to make sure styles are processed
                    setTimeout(() => { window.print(); }, 500);
                };
            <\/script>
        </body>
        </html>
    `);
}
</script>

<!-- Floating deals analyst chat button -->
<button id="btnDealsAnalyst" class="btn btn-primary rounded-circle shadow-lg d-flex align-items-center justify-content-center" style="position: fixed; bottom: 24px; right: 24px; width: 60px; height: 60px; z-index: 1050; transition: all 0.3s ease;">
    <i class="bi bi-robot fs-3"></i>
</button>

<!-- Slide-out chat drawer -->
<div id="dealsAnalystDrawer" class="bg-white shadow-lg border-start d-flex flex-column" style="position: fixed; top: 0; right: -420px; width: 420px; height: 100vh; z-index: 1060; transition: right 0.3s cubic-bezier(0.85, 0, 0.15, 1);">
    <!-- Header -->
    <div class="p-3 border-bottom bg-light d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <div class="bg-primary-subtle text-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="bi bi-robot fs-5"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-0 text-dark">Deals Underwriter</h6>
                <small class="text-success d-flex align-items-center gap-1" style="font-size: 0.75rem;">
                    <span class="d-inline-block bg-success rounded-circle" style="width: 6px; height: 6px;"></span>
                    Online &bull; Analyst
                </small>
            </div>
        </div>
        <button id="btnCloseDealsDrawer" class="btn-close" aria-label="Close"></button>
    </div>

    <!-- Message container -->
    <div id="underwriteChatBody" class="flex-grow-1 p-3 overflow-y-auto" style="background-color: #fcfcfc;">
        <!-- Welcome message -->
        <div class="d-flex align-items-start gap-2 mb-3">
            <div class="bg-light border text-muted rounded-circle p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px;">
                <i class="bi bi-robot"></i>
            </div>
            <div class="p-3 rounded bg-light border text-dark small" style="max-width: 82%;">
                Hello! I am your <strong>BDvault Underwriting Assistant</strong>. I have loaded all variables, financial ratios, FSI structures, and market scores for <strong><?= e($proposal['proposal_name']) ?></strong>.
                <br><br>
                Ask me anything! For example:
                <ul class="ps-3 mb-0 mt-1">
                    <li>What are the critical risks/weaknesses of this proposal?</li>
                    <li>How can we structure negotiations with the society's PMC?</li>
                    <li>What is our margin safety if construction costs rise by 15%?</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Input footer -->
    <div class="p-3 border-top bg-light">
        <form id="underwriteChatForm" class="d-flex gap-2">
            <input type="text" id="underwriteInput" class="form-control form-control-sm border-secondary-subtle" placeholder="Ask about this deal..." required autocomplete="off">
            <button type="submit" class="btn btn-sm btn-primary px-3">
                <i class="bi bi-send-fill"></i>
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fab = document.getElementById('btnDealsAnalyst');
    const drawer = document.getElementById('dealsAnalystDrawer');
    const closeBtn = document.getElementById('btnCloseDealsDrawer');
    const chatForm = document.getElementById('underwriteChatForm');
    const chatInput = document.getElementById('underwriteInput');
    const chatBody = document.getElementById('underwriteChatBody');
    
    let chatHistory = [];
    const proposalId = <?= (int)$proposal['id'] ?>;

    // Toggle drawer
    fab.addEventListener('click', function() {
        drawer.style.right = '0';
        fab.style.transform = 'scale(0)';
    });

    closeBtn.addEventListener('click', function() {
        drawer.style.right = '-420px';
        fab.style.transform = 'scale(1)';
    });

    // Handle submit
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const text = chatInput.value.trim();
        if(!text) return;

        chatInput.value = '';

        // Append user bubble
        appendBubble('user', text);
        chatHistory.push({ role: 'user', text: text });

        // Append loading bubble
        const loadingId = 'loading-' + Date.now();
        appendLoadingBubble(loadingId);

        // Fetch
        fetch('index.php?page=api_ai_underwrite', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                proposal_id: proposalId,
                question: text,
                history: chatHistory
            })
        })
        .then(res => res.json())
        .then(data => {
            removeBubble(loadingId);
            if(data.success) {
                appendBubble('assistant', data.answer);
                chatHistory.push({ role: 'assistant', text: data.answer });
            } else {
                appendBubble('assistant', '<span class="text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Error: ' + data.error + '</span>');
            }
        })
        .catch(err => {
            removeBubble(loadingId);
            appendBubble('assistant', '<span class="text-danger"><i class="bi bi-wifi-off me-1"></i>Network connection failed.</span>');
        });
    });

    function appendBubble(role, content) {
        const isUser = role === 'user';
        const bubble = document.createElement('div');
        bubble.className = `d-flex align-items-start gap-2 mb-3 ${isUser ? 'justify-content-end' : ''}`;
        
        const avatar = isUser ? '' : `
            <div class="bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px;">
                <i class="bi bi-robot"></i>
            </div>
        `;

        bubble.innerHTML = `
            ${avatar}
            <div class="p-3 rounded shadow-sm small border ${isUser ? 'bg-primary text-white border-primary' : 'bg-white text-dark'}" style="max-width: 82%; white-space: pre-wrap; line-height: 1.45;">
                ${content}
            </div>
        `;
        chatBody.appendChild(bubble);
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function appendLoadingBubble(id) {
        const bubble = document.createElement('div');
        bubble.className = 'd-flex align-items-start gap-2 mb-3';
        bubble.id = id;
        bubble.innerHTML = `
            <div class="bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px;">
                <i class="bi bi-robot animate-pulse"></i>
            </div>
            <div class="p-3 rounded bg-white text-muted small border" style="max-width: 82%;">
                <span class="spinner-grow spinner-grow-sm me-1" role="status" aria-hidden="true"></span>
                Underwriting metrics...
            </div>
        `;
        chatBody.appendChild(bubble);
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function removeBubble(id) {
        const el = document.getElementById(id);
        if(el) el.remove();
    }
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
