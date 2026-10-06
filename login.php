<?php
/**
 * Single Unified Login Page
 * Split-Layout Modern Aesthetic (0.75 Scale Compact View)
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

$bgImgPath = __DIR__ . '/assets/images/auth-bg.jpg';
$bgImgVer = file_exists($bgImgPath) ? filemtime($bgImgPath) : time();

$pageTitle = 'Sign In - HomeHub';
require_once __DIR__ . '/includes/header.php';
?>

<style>
/* Embedded Auth Split Styles - Scaled to 0.75 Compact Ratio */
.auth-split-wrapper {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.25rem;
    position: relative;
    background: radial-gradient(circle at 10% 10%, rgba(229, 169, 59, 0.08) 0%, transparent 45%),
                radial-gradient(circle at 90% 90%, rgba(30, 62, 43, 0.09) 0%, transparent 45%),
                var(--hh-body-bg, #f8fafc);
}

.auth-split-card {
    width: 100%;
    max-width: 890px;
    border-radius: 22px !important;
    background-color: var(--hh-card-bg, #ffffff) !important;
    box-shadow: 0 20px 50px -12px rgba(16, 42, 69, 0.12), 0 8px 20px -8px rgba(0, 0, 0, 0.04) !important;
    overflow: hidden;
    border: 1px solid var(--hh-border, #e2e8f0) !important;
}

[data-bs-theme="dark"] .auth-split-card {
    background-color: #0d1e32 !important;
    border-color: #1a3658 !important;
    box-shadow: 0 20px 50px -12px rgba(0, 0, 0, 0.6) !important;
}

.auth-brand-badge {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #1e3e2b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 3px 8px rgba(30, 62, 43, 0.25);
    flex-shrink: 0;
}

.auth-brand-badge i {
    color: #e5a93b;
    font-size: 1.15rem;
}

.auth-brand-title {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--hh-text-main, #0f1f33);
    letter-spacing: -0.03em;
    line-height: 1.1;
}

.auth-title {
    font-size: 1.55rem;
    font-weight: 700;
    color: var(--hh-text-main, #0f1f33);
    letter-spacing: -0.025em;
    margin-bottom: 0.25rem;
}

.auth-subtitle {
    font-size: 0.825rem;
    color: var(--hh-text-muted, #5f748d);
}

.auth-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--hh-text-main, #0f1f33);
    margin-bottom: 0.35rem;
    display: block;
}

.auth-input {
    height: 42px;
    border-radius: 10px !important;
    border: 1.5px solid var(--hh-border, #e2e8f0) !important;
    padding: 0.5rem 0.95rem;
    font-size: 0.875rem;
    color: var(--hh-text-main, #0f1f33) !important;
    background-color: var(--hh-card-bg, #ffffff) !important;
    transition: all 0.2s ease;
}

.auth-input:focus {
    border-color: #1e3e2b !important;
    box-shadow: 0 0 0 3px rgba(30, 62, 43, 0.12) !important;
}

[data-bs-theme="dark"] .auth-input {
    background-color: #071524 !important;
    border-color: #1a3658 !important;
    color: #f1f6fc !important;
}

[data-bs-theme="dark"] .auth-input:focus {
    border-color: #e5a93b !important;
    box-shadow: 0 0 0 3px rgba(229, 169, 59, 0.18) !important;
}

.auth-eye-btn {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    color: #94a3b8;
    padding: 4px 8px;
    cursor: pointer;
    z-index: 4;
    font-size: 0.9rem;
    transition: color 0.2s ease;
}

.auth-eye-btn:hover {
    color: var(--hh-text-main, #0f1f33);
}

.auth-submit-btn {
    height: 44px;
    border-radius: 9999px !important;
    background-color: var(--hh-primary, #01a799) !important;
    border: none !important;
    color: #ffffff !important;
    font-weight: 600;
    font-size: 0.925rem;
    letter-spacing: 0.01em;
    transition: all 0.25s ease;
    box-shadow: 0 4px 14px rgba(1, 167, 153, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
}

.auth-submit-btn:hover {
    background-color: var(--hh-primary-hover, #008f83) !important;
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(1, 167, 153, 0.4);
    color: #ffffff !important;
}

.auth-hero-container {
    height: 100%;
    min-height: 480px;
    border-radius: 18px;
    position: relative;
    overflow: hidden;
    background-size: cover;
    background-position: center 30%;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 16px;
}

.auth-hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(10, 16, 26, 0.88) 0%, rgba(10, 16, 26, 0.35) 55%, rgba(10, 16, 26, 0.08) 100%);
    pointer-events: none;
    z-index: 1;
}

.auth-quote-card {
    position: relative;
    z-index: 2;
    background: rgba(15, 23, 42, 0.68) !important;
    backdrop-filter: blur(16px) !important;
    -webkit-backdrop-filter: blur(16px) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    border-radius: 16px;
    padding: 16px 18px;
    color: #ffffff !important;
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.35) !important;
}

.auth-quote-text {
    font-size: 0.825rem;
    line-height: 1.5;
    color: #ffffff !important;
    min-height: 48px;
    margin-bottom: 0.65rem;
    font-style: normal;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
    transition: opacity 0.2s ease;
}

.auth-quote-author {
    font-weight: 700;
    font-size: 0.85rem;
    color: #ffffff !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
}

.auth-quote-role {
    font-size: 0.725rem;
    color: rgba(255, 255, 255, 0.75) !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
}

.auth-slider-bar {
    height: 3.5px;
    border-radius: 3.5px;
    background: rgba(255, 255, 255, 0.3);
    flex: 1;
    cursor: pointer;
    transition: all 0.35s ease;
}

.auth-slider-bar.active {
    background: #01a799 !important;
    box-shadow: 0 0 8px rgba(1, 167, 153, 0.6);
}
</style>

<div class="auth-split-wrapper">
    <!-- Theme Toggle Floating Button -->
    <div class="position-absolute top-0 end-0 m-3 z-3">
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 36px; height: 36px; background: var(--hh-card-bg);" onclick="toggleTheme()" title="Toggle Theme" aria-label="Toggle Theme">
            <i class="bi bi-sun-fill theme-toggle-icon" style="font-size: 0.95rem;"></i>
        </button>
    </div>

    <!-- Main Auth Split Card (0.75 Scale) -->
    <div class="card auth-split-card border-0">
        <div class="row g-0 align-items-stretch">
            <!-- Left Column: Form Section -->
            <div class="col-lg-6 p-4 p-md-4 p-xl-4 d-flex flex-column justify-content-between">
                <div>
                    <!-- Brand Lockup -->
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <img src="<?= BASE_URL; ?>assets/images/logo-dark.png" alt="HomeHub" class="logo-theme-dark" style="height: 42px; width: auto; object-fit: contain;">
                        <img src="<?= BASE_URL; ?>assets/images/logo-light.png" alt="HomeHub" class="logo-theme-light" style="height: 42px; width: auto; object-fit: contain;">
                    </div>

                    <!-- Heading & Subtitle -->
                    <h1 class="auth-title">Welcome Back</h1>
                    <p class="auth-subtitle mb-3">Please enter your account credentials to access your portal.</p>

                    <?= display_flash(); ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-3 py-2 px-3 rounded-3" role="alert">
                            <i class="bi bi-exclamation-octagon-fill me-2 fs-6 flex-shrink-0"></i>
                            <div class="flex-grow-1 small"><?= e($error); ?></div>
                            <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= BASE_URL; ?>login.php" class="needs-validation">
                        <?= csrf_field(); ?>

                        <!-- Email Input -->
                        <div class="mb-2.5 mb-2">
                            <label for="email" class="auth-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control auth-input" id="email" name="email" value="<?= e($_POST['email'] ?? ''); ?>" placeholder="name@example.com" required autofocus>
                        </div>

                        <!-- Password Input with Visibility Toggle -->
                        <div class="mb-2.5 mb-2">
                            <label for="password" class="auth-label">Password <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="password" class="form-control auth-input pe-5" id="password" name="password" placeholder="Enter your password" required>
                                <button type="button" class="auth-eye-btn" onclick="togglePasswordVisibility('password', this)" title="Show or hide password" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye-slash" id="pwdEyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Options Row: Remember Me & Forgot Password -->
                        <div class="d-flex align-items-center justify-content-between mb-3 pt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="rememberMe" style="cursor: pointer;">
                                <label class="form-check-label text-muted" for="rememberMe" style="cursor: pointer; font-size: 0.8rem;">
                                    Remember me
                                </label>
                            </div>
                            <a href="javascript:void(0)" onclick="alert('To reset your credentials, please contact the system administrator: ayman@gmail.com');" class="text-muted text-decoration-none" style="font-size: 0.8rem;">
                                Forgot password?
                            </a>
                        </div>

                        <!-- Submit Button (Pill shaped, reference dark green) -->
                        <button type="submit" class="btn auth-submit-btn w-100 mb-2">
                            Sign In
                        </button>
                    </form>
                </div>

                <!-- Footer Navigation -->
                <div class="pt-3 border-top mt-2 text-center">
                    <p class="text-muted mb-1" style="font-size: 0.8rem;">
                        Looking for a home? 
                        <a href="<?= BASE_URL; ?>register.php" class="fw-semibold text-decoration-none" style="color: #1e3e2b;">Create Tenant Account</a>
                    </p>
                    <a href="<?= BASE_URL; ?>" class="text-muted text-decoration-none" style="font-size: 0.8rem;">
                        <i class="bi bi-arrow-left me-1"></i>Back to Homepage
                    </a>
                </div>
            </div>

            <!-- Right Column: Uploaded Luxury Villa Visual -->
            <div class="col-lg-6 p-2 p-md-3 d-none d-lg-block">
                <div class="auth-hero-container" style="background-image: url('<?= BASE_URL; ?>assets/images/auth-bg.jpg?v=<?= $bgImgVer; ?>');">
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password Visibility Toggle Function
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (!input || !icon) return;

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
