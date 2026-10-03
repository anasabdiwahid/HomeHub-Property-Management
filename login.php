<?php
/**
 * Single Login Page
 * Roles: Admin, Manager, User
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect directly to their dashboard
if (is_logged_in()) {
    redirect_by_role();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $error = 'Please fill in both email and password.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active' LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    login_user($user);
                    set_flash('success', 'Welcome back, ' . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . '!');
                    redirect_by_role($user['role']);
                } else {
                    $error = 'Invalid email or password. Please try again.';
                }
            } catch (Exception $e) {
                $error = 'An error occurred during authentication. Please try again.';
            }
        }
    }
}

$pageTitle = 'Sign In - HomeHub';
require_once __DIR__ . '/includes/header.php';
?>

<div class="min-vh-100 d-flex flex-column justify-content-center align-items-center py-5 px-3" style="background: radial-gradient(circle at 50% 20%, rgba(229, 169, 59, 0.12) 0%, rgba(16, 42, 69, 0.05) 50%, transparent 80%);">
    <div style="position: absolute; top: 20px; right: 20px;">
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" onclick="toggleTheme()" title="Toggle Theme" aria-label="Toggle Theme">
            <i class="bi bi-moon-stars-fill theme-toggle-icon fs-5"></i>
        </button>
    </div>

    <div class="card shadow-lg border-0" style="max-width: 460px; width: 100%; border-radius: 18px; border-top: 4px solid var(--hh-gold) !important;">
        <div class="card-body p-4 p-md-5">
            <!-- Brand Logo -->
            <div class="text-center mb-4">
                <a href="<?= BASE_URL; ?>" class="d-inline-block mb-2 text-decoration-none">
                    <img src="<?= BASE_URL; ?>assets/images/logo_clean.png" alt="HomeHub Logo" style="height: 75px; width: auto; object-fit: contain;">
                </a>
                <p class="text-muted small mb-0">Property Management Portal &bull; Somalia</p>
                <div class="badge badge-gold mt-2 px-3 py-1">Unified Multi-Role Login</div>
            </div>

            <?= display_flash(); ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
                    <div class="flex-grow-1 small"><?= e($error); ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL; ?>login.php" class="needs-validation">
                <?= csrf_field(); ?>

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold small">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent text-muted"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($_POST['email'] ?? ''); ?>" placeholder="name@homehub.so" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="password" class="form-label fw-semibold small mb-0">Password</label>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent text-muted"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fs-6 fw-bold shadow-sm mb-3">
                    <i class="bi bi-box-arrow-in-right me-2 text-gold"></i>Sign In to Portal
                </button>

                <div class="text-center small text-muted">
                    Looking for a home? <a href="<?= BASE_URL; ?>register.php" class="fw-semibold text-primary">Create Tenant Account</a>
                </div>
            </form>

            <hr class="my-4 text-muted opacity-25">

            <!-- Quick Demo Credentials Box -->
            <div class="p-3 rounded-3 border bg-body-tertiary">
                <div class="d-flex align-items-center gap-1 mb-2 text-muted fw-bold" style="font-size: 0.75rem;">
                    <i class="bi bi-key-fill text-gold"></i> DEMO CREDENTIALS (CLICK TO AUTO-FILL)
                </div>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary text-start d-flex justify-content-between align-items-center" onclick="fillCreds('admin@homehub.so', 'admin123')">
                        <span><strong class="badge bg-navy me-1" style="border: 1px solid var(--hh-gold);">Admin</strong> admin@homehub.so</span>
                        <span class="small text-muted font-monospace">admin123</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success text-start d-flex justify-content-between align-items-center" onclick="fillCreds('manager@homehub.so', 'manager123')">
                        <span><strong class="badge bg-success me-1">Manager</strong> manager@homehub.so</span>
                        <span class="small text-muted font-monospace">manager123</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning text-dark text-start d-flex justify-content-between align-items-center" onclick="fillCreds('user@homehub.so', 'user123')">
                        <span><strong class="badge bg-gold text-dark me-1">Tenant</strong> user@homehub.so</span>
                        <span class="small text-muted font-monospace">user123</span>
                    </button>
                </div>
            </div>

            <div class="text-center mt-3">
                <a href="<?= BASE_URL; ?>" class="small text-muted text-decoration-none">
                    <i class="bi bi-arrow-left me-1"></i>Back to Homepage
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function fillCreds(email, password) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = password;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
