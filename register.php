<?php
/**
 * User Registration Page
 * Roles: Tenant / User
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect_by_role();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm  = trim($_POST['password_confirmation'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Name, email, and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirm) {
            $error = 'Password confirmation does not match.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'An account with this email address already exists.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'user', 'active')");
                    $stmt->execute([$name, $email, $phone, $hash]);

                    $userId = (int)$pdo->lastInsertId();
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                    $stmt->execute([$userId]);
                    $newUser = $stmt->fetch();

                    login_user($newUser);
                    set_flash('success', 'Registration successful! Welcome to HomeHub.');
                    header('Location: ' . BASE_URL . 'user/index.php');
                    exit;
                }
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Create Tenant Account - HomeHub';
require_once __DIR__ . '/includes/header.php';
?>

<div class="min-vh-100 d-flex flex-column justify-content-center align-items-center py-5 px-3" style="background: radial-gradient(circle at 50% 20%, rgba(229, 169, 59, 0.12) 0%, rgba(16, 42, 69, 0.05) 50%, transparent 80%);">
    <div style="position: absolute; top: 20px; right: 20px;">
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" onclick="toggleTheme()" title="Toggle Theme" aria-label="Toggle Theme">
            <i class="bi bi-sun-fill theme-toggle-icon fs-5"></i>
        </button>
    </div>

    <div class="card shadow-lg border-0" style="max-width: 500px; width: 100%; border-radius: 18px; border-top: 4px solid var(--hh-gold) !important;">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <a href="<?= BASE_URL; ?>" class="d-inline-block mb-2 text-decoration-none">
                    <img src="<?= BASE_URL; ?>assets/images/logo-dark.png" alt="HomeHub Logo" class="logo-theme-dark" style="height: 55px; width: auto; object-fit: contain;">
                    <img src="<?= BASE_URL; ?>assets/images/logo-light.png" alt="HomeHub Logo" class="logo-theme-light" style="height: 55px; width: auto; object-fit: contain;">
                </a>
                <h5 class="fw-bold mb-1">Create Tenant Account</h5>
                <p class="text-muted small">Find and lease verified houses and apartments in Somalia</p>
            </div>

            <?= display_flash(); ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
                    <div class="flex-grow-1 small"><?= e($error); ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL; ?>register.php">
                <?= csrf_field(); ?>

                <div class="mb-3">
                    <label for="name" class="form-label fw-semibold small">Full Name</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent text-muted"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="name" name="name" value="<?= e($_POST['name'] ?? ''); ?>" placeholder="e.g. Hassan Omar" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold small">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent text-muted"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($_POST['email'] ?? ''); ?>" placeholder="hassan@example.com" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label fw-semibold small">Phone Number (EVC Plus / Telesom)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent text-muted"><i class="bi bi-telephone"></i></span>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?= e($_POST['phone'] ?? ''); ?>" placeholder="+252 61 XXXXXXX">
                    </div>
                </div>

                <div class="row g-2 mb-4">
                    <div class="col-sm-6">
                        <label for="password" class="form-label fw-semibold small">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent text-muted"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="At least 6 chars" required>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <label for="password_confirmation" class="form-label fw-semibold small">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent text-muted"><i class="bi bi-shield-check"></i></span>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" placeholder="Repeat password" required>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fs-6 fw-bold shadow-sm mb-3">
                    <i class="bi bi-person-check me-2 text-gold"></i>Create Account
                </button>

                <div class="text-center small text-muted">
                    Already have an account? <a href="<?= BASE_URL; ?>login.php" class="fw-semibold text-primary">Sign In</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
