<?php
/**
 * Authentication & Role-Based Access Control (RBAC)
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

/**
 * Check if a user is currently logged in
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Get the current logged-in user data
 */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Get current user ID
 */
function current_user_id(): ?int {
    return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
}

/**
 * Get current user role
 */
function current_user_role(): ?string {
    return $_SESSION['user']['role'] ?? null;
}

/**
 * Enforce authentication: redirect to login if guest
 */
function require_login(): void {
    if (!is_logged_in()) {
        set_flash('warning', 'Please sign in to access this page.');
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

/**
 * Enforce specific role or set of roles
 */
function require_role(string|array $allowed_roles): void {
    require_login();

    $allowed = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];
    $user_role = current_user_role();

    if (!in_array($user_role, $allowed, true)) {
        set_flash('danger', 'Access denied. You do not have permission to access that section.');
        redirect_by_role($user_role);
        exit;
    }
}

/**
 * Redirect user to their corresponding role dashboard
 */
function redirect_by_role(?string $role = null): void {
    $role = $role ?? current_user_role();
    
    match($role) {
        'admin'   => header('Location: ' . BASE_URL . 'admin/index.php'),
        'manager' => header('Location: ' . BASE_URL . 'manager/index.php'),
        'user'    => header('Location: ' . BASE_URL . 'user/index.php'),
        default   => header('Location: ' . BASE_URL . 'login.php')
    };
    exit;
}

/**
 * Logs in a user, regenerates session ID for security
 */
function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'         => (int)$user['id'],
        'name'       => $user['name'],
        'email'      => $user['email'],
        'phone'      => $user['phone'] ?? '',
        'role'       => $user['role'],
        'avatar'     => $user['avatar'] ?? null,
        'created_at' => $user['created_at'] ?? date('Y-m-d H:i:s'),
    ];
}

/**
 * Destroys user session completely
 */
function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}
