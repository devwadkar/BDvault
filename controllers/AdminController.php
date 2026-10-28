<?php
/**
 * AdminController – User Management (Admin-only)
 */

class AdminController {

    // ─── User List ───────────────────────────────────────────────────────────

    public static function users(): void {
        requireAdmin();

        $db   = getDB();
        $users = $db->query("SELECT id, username, full_name, role, created_at FROM users ORDER BY id ASC")
                    ->fetch_all(MYSQLI_ASSOC);

        $pageTitle = 'User Management';
        require __DIR__ . '/../views/admin/users.php';
    }

    // ─── Create User Form ────────────────────────────────────────────────────

    public static function createUser(): void {
        requireAdmin();

        $pageTitle  = 'Create User';
        $editUser   = null; // indicates create mode
        $errors     = [];

        require __DIR__ . '/../views/admin/user_form.php';
    }

    // ─── Create User Process ─────────────────────────────────────────────────

    public static function createUserProcess(): void {
        requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('index.php?page=admin_users');
        }

        $username  = trim($_POST['username'] ?? '');
        $fullName  = trim($_POST['full_name'] ?? '');
        $role      = in_array($_POST['role'] ?? '', ['admin', 'manager', 'viewer'], true) ? $_POST['role'] : 'viewer';
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';

        $errors = [];
        if (empty($username))      $errors[] = 'Username is required.';
        if (empty($password))      $errors[] = 'Password is required.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

        if (empty($errors)) {
            $db   = getDB();
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = "Username '{$username}' is already taken.";
            }
        }

        if (!empty($errors)) {
            $pageTitle = 'Create User';
            $editUser  = null;
            require __DIR__ . '/../views/admin/user_form.php';
            return;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db   = getDB();
        $stmt = $db->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $username, $hash, $fullName, $role);

        if ($stmt->execute()) {
            flashSet('success', "User '{$username}' created successfully.");
        } else {
            flashSet('danger', 'Failed to create user. Please try again.');
        }
        redirect('index.php?page=admin_users');
    }

    // ─── Edit User Form ───────────────────────────────────────────────────────

    public static function editUser(): void {
        requireAdmin();

        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { redirect('index.php?page=admin_users'); }

        $db   = getDB();
        $stmt = $db->prepare("SELECT id, username, full_name, role FROM users WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $editUser = $stmt->get_result()->fetch_assoc();
        if (!$editUser) { redirect('index.php?page=admin_users'); }

        $errors    = [];
        $pageTitle = 'Edit User';
        require __DIR__ . '/../views/admin/user_form.php';
    }

    // ─── Edit User Process ────────────────────────────────────────────────────

    public static function editUserProcess(): void {
        requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('index.php?page=admin_users');
        }

        $id       = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $role     = in_array($_POST['role'] ?? '', ['admin', 'manager', 'viewer'], true) ? $_POST['role'] : 'viewer';
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        $errors = [];
        if (empty($username)) $errors[] = 'Username is required.';
        if (!empty($password)) {
            if ($password !== $confirm)  $errors[] = 'Passwords do not match.';
            if (strlen($password) < 8)   $errors[] = 'Password must be at least 8 characters.';
        }

        // Prevent admin from demoting themselves
        $currentUser = currentUser();
        if ($id === (int)$currentUser['id'] && $role !== 'admin') {
            $errors[] = 'You cannot remove your own admin role.';
        }

        if (!empty($errors)) {
            $db   = getDB();
            $stmt = $db->prepare("SELECT id, username, full_name, role FROM users WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $editUser  = $stmt->get_result()->fetch_assoc();
            $pageTitle = 'Edit User';
            require __DIR__ . '/../views/admin/user_form.php';
            return;
        }

        $db = getDB();
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET username=?, full_name=?, role=?, password=? WHERE id=?");
            $stmt->bind_param('ssssi', $username, $fullName, $role, $hash, $id);
        } else {
            $stmt = $db->prepare("UPDATE users SET username=?, full_name=?, role=? WHERE id=?");
            $stmt->bind_param('sssi', $username, $fullName, $role, $id);
        }

        if ($stmt->execute()) {
            flashSet('success', "User '{$username}' updated successfully.");
        } else {
            flashSet('danger', 'Failed to update user.');
        }
        redirect('index.php?page=admin_users');
    }

    // ─── Delete User ──────────────────────────────────────────────────────────

    public static function deleteUser(): void {
        requireAdmin();

        $id          = (int)($_GET['id'] ?? 0);
        $currentUser = currentUser();

        if ($id === (int)$currentUser['id']) {
            flashSet('danger', 'You cannot delete your own account.');
            redirect('index.php?page=admin_users');
        }

        if ($id) {
            $db   = getDB();
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            flashSet('success', 'User deleted successfully.');
        }
        redirect('index.php?page=admin_users');
    }
}
