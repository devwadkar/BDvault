<?php
$isEdit    = !empty($editUser);
$pageTitle = $isEdit ? 'Edit User' : 'Create User';
?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="index.php?page=admin_users" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h4 class="mb-0 fw-bold"><?= $isEdit ? 'Edit User' : 'Create New User' ?></h4>
        <p class="text-muted small mb-0">
            <?= $isEdit ? 'Update credentials, name, or role for ' . e($editUser['username']) : 'Add a new user account to the system' ?>
        </p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-3">
    <ul class="mb-0 ps-3">
        <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="row justify-content-center">
<div class="col-md-6">
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST"
              action="index.php?page=<?= $isEdit ? 'admin_user_save_edit' : 'admin_user_save_create' ?>"
              autocomplete="off">

            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= (int)$editUser['id'] ?>">
            <?php endif; ?>

            <!-- Username -->
            <div class="mb-3">
                <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                <input type="text" name="username" class="form-control"
                       value="<?= e($isEdit ? $editUser['username'] : ($_POST['username'] ?? '')) ?>"
                       required autocomplete="off">
            </div>

            <!-- Full Name -->
            <div class="mb-3">
                <label class="form-label fw-semibold">Full Name</label>
                <input type="text" name="full_name" class="form-control"
                       value="<?= e($isEdit ? $editUser['full_name'] : ($_POST['full_name'] ?? '')) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                <select name="role" class="form-select">
                    <?php
                    $currentRole = $isEdit ? $editUser['role'] : ($_POST['role'] ?? 'viewer');
                    ?>
                    <option value="viewer"  <?= ($currentRole === 'viewer' || $currentRole === 'user') ? 'selected' : '' ?>>Viewer — View &amp; Add Proposals only</option>
                    <option value="manager" <?= $currentRole === 'manager' ? 'selected' : '' ?>>Manager — Edit, Delete, Decisions, AI Autofill (No User Management)</option>
                    <option value="admin"   <?= $currentRole === 'admin'   ? 'selected' : '' ?>>Admin — Full System Access (incl. User Management)</option>
                </select>
            </div>

            <hr class="my-3">

            <!-- Password -->
            <div class="mb-3">
                <label class="form-label fw-semibold">
                    Password <?= !$isEdit ? '<span class="text-danger">*</span>' : '' ?>
                </label>
                <?php if ($isEdit): ?>
                <p class="text-muted small mb-2">Leave blank to keep existing password.</p>
                <?php endif; ?>
                <input type="password" name="password" class="form-control"
                       <?= !$isEdit ? 'required' : '' ?>
                       autocomplete="new-password" minlength="8"
                       placeholder="<?= $isEdit ? 'Enter new password (optional)' : 'Minimum 8 characters' ?>">
            </div>

            <!-- Confirm Password -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control"
                       autocomplete="new-password"
                       placeholder="Re-enter password">
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-<?= $isEdit ? 'check-lg' : 'person-plus-fill' ?> me-1"></i>
                    <?= $isEdit ? 'Save Changes' : 'Create User' ?>
                </button>
                <a href="index.php?page=admin_users" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
