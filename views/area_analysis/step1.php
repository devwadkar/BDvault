<?php require __DIR__ . '/_wizard_shell.php'; ?>
<?php _aa_shell_open(); ?>

<div class="aa-card">
    <h5 class="aa-card-title">Project details</h5>
    <p class="aa-card-lead">Confirm the details carried over from the source proposal. Anything you change here becomes part of this Area Statement version.</p>

    <form method="POST" action="index.php?page=area_analysis_save_step">
        <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
        <input type="hidden" name="step" value="1">

        <div class="aa-group-title">Identification</div>
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Project name <span class="req">*</span></label>
                <input type="text" name="project_name" class="form-control" value="<?= e($statement['project_name'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">CTS / Survey no.</label>
                <input type="text" name="cts_number" class="form-control" value="<?= e($statement['cts_number'] ?? '') ?>">
            </div>
        </div>

        <div class="aa-group-title">Location</div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">City · region</label>
                <input type="text" name="project_location" class="form-control" value="<?= e($statement['project_location'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Ward</label>
                <input type="text" name="ward" class="form-control" value="<?= e($statement['ward'] ?? '') ?>" placeholder="e.g. K-East">
            </div>
            <div class="col-md-5">
                <label class="form-label">Plot address</label>
                <input type="text" name="plot_address" class="form-control" value="<?= e($statement['plot_address'] ?? '') ?>">
            </div>
        </div>

        <div class="aa-group-title">Development classification</div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Planning authority <span class="req">*</span></label>
                <select name="planning_authority" class="form-select" required>
                    <option value="">Select…</option>
                    <?php foreach (['MCGM','SRA','MHADA','MMRDA','Collector','Other'] as $opt): ?>
                        <option value="<?= $opt ?>" <?= ($statement['planning_authority'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Development type <span class="req">*</span></label>
                <select name="development_type" class="form-select" required>
                    <option value="">Select…</option>
                    <?php
                    $devOpts = [
                        'redevelopment'         => 'Redevelopment (society)',
                        'sra'                   => 'SRA · Slum Redevelopment',
                        'mhada'                 => 'MHADA',
                        'society_redevelopment' => 'Co-op Society Redevelopment',
                        'land_development'      => 'Land Development · Vacant Plot',
                        'cluster'               => 'Cluster / Urban Renewal',
                        'other'                 => 'Other',
                    ];
                    foreach ($devOpts as $k => $l): ?>
                        <option value="<?= $k ?>" <?= ($statement['development_type'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php _aa_nav_bar($statement, $step); ?>
    </form>
</div>

<?php _aa_shell_close(); ?>
