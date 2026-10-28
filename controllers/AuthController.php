<?php
/**
 * AuthController – Handles Authentication Flows (Login, Register, Logout)
 */

class AuthController {

    /**
     * Render the login portal view
     */
    public static function login(): void {
        if (isLoggedIn()) {
            redirect('index.php?page=dashboard');
        }
        
        $pageTitle = 'Log In';
        require_once __DIR__ . '/../views/auth/login.php';
    }

    /**
     * Registration is disabled – only admins can create accounts.
     */
    public static function register(): void {
        flashSet('danger', 'Self-registration is disabled. Contact your administrator.');
        redirect('index.php?page=login');
    }

    /**
     * Process user login request
     */
    public static function loginProcess(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('index.php?page=login');
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            flashSet('danger', 'Please enter both username and password.');
            redirect('index.php?page=login');
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res  = $stmt->get_result();
        $user = $res->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            // Create a database-backed auth token (bypasses Varnish/NFS session issues)
            TokenAuth::login($user);

            flashSet('success', "Welcome back, " . ($user['full_name'] ?: $user['username']) . "!");
            redirect('index.php?page=dashboard');
        } else {
            flashSet('danger', 'Invalid username or password.');
            redirect('index.php?page=login');
        }
    }

    /**
     * Register is admin-only. This endpoint is disabled.
     */
    public static function registerProcess(): void {
        flashSet('danger', 'Self-registration is disabled.');
        redirect('index.php?page=login');
    }

    /**
     * Process secure user logout
     */
    public static function logout(): void {
        TokenAuth::logout();

        flashSet('success', 'Logged out successfully.');
        redirect('index.php?page=login');
    }
}
