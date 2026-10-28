<?php $pageTitle = 'User Management'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-people-fill me-2 text-primary"></i>User Management</h4>
        <p class="text-muted small mb-0"><?= count($users) ?> registered user<?= count($users) !== 1 ? 's' : '' ?></p>
    </div>
    <a href="index.php?page=admin_user_create" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus-fill me-1"></i> New User
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">No users found.</td>
                    </tr>
                <?php else: foreach ($users as $u):
                    $isSelf = ((int)$u['id'] === (int)TokenAuth::userId());
                ?>
                    <tr>
                        <td class="ps-4 text-muted small">#<?= $u['id'] ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                     style="width:34px;height:34px;font-size:13px;flex-shrink:0">
                                    <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                </div>
                                <span class="fw-semibold"><?= e($u['username']) ?></span>
                                <?php if ($isSelf): ?>
                                    <span class="badge bg-info-subtle text-info">You</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= e($u['full_name'] ?: '—') ?></td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge bg-danger-subtle text-danger"><i class="bi bi-shield-fill-check me-1"></i>Admin</span>
                            <?php elseif ($u['role'] === 'manager'): ?>
                                <span class="badge bg-warning-subtle text-warning"><i class="bi bi-person-fill-gear me-1"></i>Manager</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-person me-1"></i>Viewer</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td class="text-end pe-4">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="index.php?page=admin_user_edit&id=<?= $u['id'] ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if (!$isSelf): ?>
                                <a href="index.php?page=admin_user_delete&id=<?= $u['id'] ?>"
                                   class="btn btn-sm btn-outline-danger" title="Delete"
                                   onclick="return confirm('Delete user <?= e($u['username']) ?>? This cannot be undone.')">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php else: ?>
                                <button class="btn btn-sm btn-outline-danger" disabled title="Cannot delete your own account">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
