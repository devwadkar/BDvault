<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Proposal Report - <?= e($proposal['proposal_name']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: white; font-size: 14px; }
        .report-header { border-bottom: 2px solid #0d6efd; padding-bottom: 1rem; margin-bottom: 2rem; }
        .score-box { background: #f8f9fa; border-radius: 8px; padding: 1.5rem; text-align: center; }
        .score-value { font-size: 3rem; font-weight: bold; line-height: 1; }
        .metric-label { color: #6c757d; font-size: 0.8rem; text-transform: uppercase; font-weight: bold; }
        .metric-value { font-size: 1.1rem; font-weight: 600; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            .card { border: 1px solid #ddd !important; }
        }
    </style>
</head>
<body class="p-4">

    <div class="container">
        <div class="no-print mb-4 d-flex justify-content-between align-items-center">
            <a href="index.php?page=proposal_detail&id=<?= $proposal['id'] ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Detail
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="bi bi-printer"></i> Print / Save as PDF
            </button>
        </div>

        <header class="report-header d-flex justify-content-between align-items-end">
            <div>
                <h1 class="fw-bold mb-0 text-primary"><?= e($proposal['proposal_name']) ?></h1>
                <p class="text-muted mb-0"><?= e($proposal['region']) ?>, <?= e($proposal['city']) ?> | Ref: #<?= $proposal['id'] ?></p>
            </div>
            <div class="text-end">
                <h4 class="mb-0">BD Proposal Report</h4>
                <p class="text-muted mb-0"><?= date('d M Y') ?></p>
            </div>
        </header>

        <div class="row g-4 mb-4">
            <div class="col-md-8">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title fw-bold border-bottom pb-2 mb-3">Project Overview</h5>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="metric-label">Proposal Type</div>
                                <div class="metric-value"><?= e($proposal['proposal_type']) ?></div>
                            </div>
                            <div class="col-6">
                                <div class="metric-label">Plot Area</div>
                                <div class="metric-value"><?= number_format($proposal['plot_area_sqm']) ?> sq.m.</div>
                            </div>
                            <div class="col-6">
                                <div class="metric-label">Avg Sale Rate</div>
                                <div class="metric-value"><?= formatINR($proposal['avg_sale_price_sqft']) ?> /sq.ft.</div>
                            </div>
                            <div class="col-6">
                                <div class="metric-label">Potential Value</div>
                                <div class="metric-value text-primary">₹<?= number_format(($proposal['plot_area_sqm'] * 10.764 * $proposal['avg_sale_price_sqft']) / 10000000, 2) ?> Cr</div>
                            </div>
                            <div class="col-12">
                                <div class="metric-label">Site Address</div>
                                <div class="metric-value"><?= nl2br(e($proposal['site_address'])) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="score-box h-100 shadow-sm">
                    <div class="metric-label mb-2">Feasibility Score</div>
                    <div class="score-value text-primary"><?= number_format($proposal['total_score'], 1) ?></div>
                    <div class="badge bg-<?= CATEGORY_COLOURS[$proposal['category']] ?? 'secondary' ?> mt-2 fs-6">
                        <?= e($proposal['category']) ?>
                    </div>
                    <hr>
                    <div class="metric-label mb-1">Probability of Success</div>
                    <div class="h3 fw-bold text-success"><?= $proposal['probability_pct'] ?>%</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title fw-bold border-bottom pb-2 mb-3">Scoring Breakdown</h5>
                        <?php foreach ($breakdown as $item): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?= e($item['label']) ?></span>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress" style="width: 100px; height: 6px;">
                                    <div class="progress-bar bg-primary" style="width: <?= $item['score'] ?>%"></div>
                                </div>
                                <span class="fw-bold"><?= number_format($item['score'], 1) ?>/100</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title fw-bold border-bottom pb-2 mb-3">Stage 1 Filter Analysis</h5>
                        <?php if ($proposal['is_eligible']): ?>
                            <div class="alert alert-success d-flex align-items-center gap-2">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>Passed all technical and financial thresholds.</span>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger mb-3">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> Failed Stage 1 Thresholds
                            </div>
                            <ul class="text-danger small">
                                <?php foreach ($reasons as $reason): ?>
                                    <li><?= e($reason) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title fw-bold border-bottom pb-2 mb-3">Additional Details & Remarks</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="metric-label">Consultant / Owner</div>
                        <div class="text-muted"><?= e($proposal['consultant_name_number'] ?: '—') ?> / <?= e($proposal['reference_owner'] ?: '—') ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="metric-label">Completion Timeline</div>
                        <div class="text-muted"><?= $proposal['completion_timeline_yrs'] ?> Years</div>
                    </div>
                    <div class="col-md-4">
                        <div class="metric-label">Data Source</div>
                        <div class="text-muted"><?= e($proposal['data_source']) ?></div>
                    </div>
                    <div class="col-12">
                        <div class="metric-label">Remarks</div>
                        <p class="text-muted"><?= nl2br(e($proposal['remarks'] ?: 'No additional remarks.')) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <footer class="text-center text-muted smaller mt-5 no-print">
            BDvault BD Classifier System | Generated on <?= date('d M Y, H:i') ?>
        </footer>
    </div>

</body>
</html>
