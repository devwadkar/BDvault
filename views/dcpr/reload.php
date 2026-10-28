<?php $pageTitle = 'DCPR Rules — Reload'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<a href="index.php?page=dcpr" class="text-muted small text-decoration-none">
    <i class="bi bi-arrow-left me-1"></i>Back to rule list
</a>

<div class="card border-0 shadow-sm mt-3">
    <div class="card-body">
        <h5 class="fw-bold mb-3">
            <i class="bi bi-arrow-repeat me-2"></i>Rule Base Reload Results
        </h5>
        <?php if (empty($results)): ?>
            <div class="alert alert-warning mb-0">No result returned.</div>
        <?php else: ?>
            <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>File</th>
                        <th>Regulation</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $r): ?>
                        <tr>
                            <td><code><?= e($r['file']) ?></code></td>
                            <td><?= isset($r['reg']) ? '<b>' . e($r['reg']) . '</b>' : '—' ?></td>
                            <td><?= e($r['title'] ?? '') ?></td>
                            <td>
                                <?php if ($r['status'] === 'ok'): ?>
                                    <span class="badge bg-success">OK</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Error</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= e($r['message'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
