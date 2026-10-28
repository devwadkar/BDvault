<?php $pageTitle = 'Import Excel'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="mb-4">
    <h4 class="fw-bold mb-0">Import Proposals from Excel</h4>
    <p class="text-muted small">Supports .xlsx and .csv files. Both 23-24 and 25 data formats are auto-detected.</p>
</div>

<div class="row g-3">
    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" action="index.php?page=upload_process" enctype="multipart/form-data">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Excel / CSV File <span class="text-danger">*</span></label>
                        <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls,.csv" required>
                        <div class="form-text">Max <?= MAX_UPLOAD_MB ?>MB. Accepts .xlsx (recommended) or .csv.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Source Label</label>
                        <input type="text" name="source_label" class="form-control" value="excel_import" placeholder="e.g. excel_23_24">
                        <div class="form-text">A tag to identify the data source in the database.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload me-1"></i>Upload & Import
                    </button>
                    <a href="index.php?page=proposals" class="btn btn-outline-secondary ms-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold border-0 pt-3">
                <i class="bi bi-table me-1"></i>Expected Columns
            </div>
            <div class="card-body small">
                <p class="text-muted">The system auto-maps these columns from your Excel file:</p>
                <table class="table table-sm mb-0">
                    <thead><tr><th>Column Name</th><th>Stage</th></tr></thead>
                    <tbody>
                        <tr><td>Proposal name</td><td>—</td></tr>
                        <tr><td>Proposal type</td><td>—</td></tr>
                        <tr><td>Region / City / Site Address</td><td>—</td></tr>
                        <tr><td>Plot Area (sq.m.)</td><td>S1</td></tr>
                        <tr><td class="text-danger fw-semibold">Avg Sale Price(per sq.ft)</td><td>S1</td></tr>
                        <tr><td class="text-danger fw-semibold">Entry Cost (per sq.ft)</td><td>S1</td></tr>
                        <tr><td class="text-danger fw-semibold">Existing FSI Used</td><td>S1</td></tr>
                        <tr><td class="text-danger fw-semibold">Free Sale Area %</td><td>S1</td></tr>
                        <tr><td class="text-danger fw-semibold">Existing Units / Proposed Units</td><td>S1</td></tr>
                        <tr><td class="text-danger fw-semibold">Timeline</td><td>S1</td></tr>
                        <tr><td>Location Rating (1-10)</td><td>S2</td></tr>
                        <tr><td>Approval Complexity (1-10)</td><td>S2</td></tr>
                        <tr><td>Market Demand (1-10)</td><td>S2</td></tr>
                        <tr><td>Status / Remarks / Cluster</td><td>—</td></tr>
                    </tbody>
                </table>
                <div class="mt-2 text-muted">
                    <span class="text-danger">Red</span> = required for Stage 1 filter. Missing values cause that filter to be skipped (not failed).
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-transparent fw-semibold border-0 pt-3">
                <i class="bi bi-clock-history me-1"></i>Recent Imports
            </div>
            <div class="card-body p-0">
                <?php
                $db   = getDB();
                $logs = $db->query("SELECT * FROM import_logs ORDER BY imported_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
                if (empty($logs)):
                ?>
                <p class="text-muted small p-3 mb-0">No imports yet.</p>
                <?php else: ?>
                <table class="table table-sm small mb-0">
                    <thead><tr><th class="ps-3">File</th><th>Imported</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="ps-3"><?= e($log['filename']) ?></td>
                            <td><?= $log['imported_rows'] ?>/<?= $log['total_rows'] ?></td>
                            <td class="text-muted"><?= date('d M y', strtotime($log['imported_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
