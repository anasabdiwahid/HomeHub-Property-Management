<?php
/**
 * Single Unified Login Page
 * Split-Layout Modern Aesthetic matching Reference Design
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

<div class="auth-split-wrapper d-flex align-items-center justify-content-center p-3 p-md-4 p-xl-5 position-relative">
    <!-- Theme Toggle Floating Button -->
    <div class="position-absolute top-0 end-0 m-3 m-md-4 z-3">
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; background: var(--hh-card-bg);" onclick="toggleTheme()" title="Toggle Theme" aria-label="Toggle Theme">
            <i class="bi bi-moon-stars-fill theme-toggle-icon fs-5"></i>
        </button>
    </div>

    <!-- Main Auth Card -->
    <div class="card auth-split-card border-0">
        <div class="row g-0 align-items-stretch">
            <!-- Left Column: Form -->
            <div class="col-lg-6 p-4 p-sm-5 d-flex flex-column justify-content-between">
                <div>
                    <!-- Brand Lockup -->
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="auth-brand-badge">
                            <i class="bi bi-houses-fill"></i>
                        </div>
                        <div class="auth-brand-title">
                            HomeHub<span style="color: #e5a93b;">.</span>
                        </div>
                    </div>

                    <!-- Heading & Subtitle -->
                    <h1 class="auth-title">Welcome Back</h1>
                    <p class="auth-subtitle mb-4">Please enter your account credentials to access your portal.</p>

                    <?= display_flash(); ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4 rounded-3" role="alert">
                            <i class="bi bi-exclamation-octagon-fill me-2 fs-5 flex-shrink-0"></i>
                            <div class="flex-grow-1 small"><?= e($error); ?></div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= BASE_URL; ?>login.php" class="needs-validation">
                        <?= csrf_field(); ?>

                        <!-- Email Input -->
                        <div class="mb-3">
                            <label for="email" class="auth-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control auth-input" id="email" name="email" value="<?= e($_POST['email'] ?? ''); ?>" placeholder="Enter your email" required autofocus>
                        </div>

                        <!-- Password Input with Visibility Toggle -->
                        <div class="mb-3">
                            <label for="password" class="auth-label">Password <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="password" class="form-control auth-input pe-5" id="password" name="password" placeholder="Enter your password" required>
                                <button type="button" class="auth-eye-btn" onclick="togglePasswordVisibility('password', this)" title="Show or hide password" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye-slash" id="pwdEyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Options Row: Remember Me & Forgot Password -->
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="rememberMe" style="cursor: pointer;">
                                <label class="form-check-label small text-muted" for="rememberMe" style="cursor: pointer;">
                                    Remember me
                                </label>
                            </div>
                            <a href="javascript:void(0)" onclick="alert('To reset your credentials, please contact the system administrator: ayman@gmail.com');" class="small text-muted text-decoration-none">
                                Forgot password?
                            </a>
                        </div>

                        <!-- Submit Button (Pill shaped, reference dark green) -->
                        <button type="submit" class="btn auth-submit-btn w-100 mb-3">
                            Sign In
                        </button>
                    </form>
                </div>

                <!-- Footer Navigation -->
                <div class="pt-4 border-top mt-3 text-center">
                    <p class="small text-muted mb-2">
                        Looking for a home? 
                        <a href="<?= BASE_URL; ?>register.php" class="fw-semibold text-decoration-none" style="color: #1e3e2b;">Create Tenant Account</a>
                    </p>
                    <a href="<?= BASE_URL; ?>" class="small text-muted text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i>Back to Homepage
                    </a>
                </div>
            </div>

            <!-- Right Column: Hero Visual with Frosted Glass Testimonial -->
            <div class="col-lg-6 p-3 p-md-4 d-none d-lg-block">
                <div class="auth-hero-container" style="background-image: url('<?= BASE_URL; ?>assets/images/auth-bg.jpg');">
                    <div class="auth-hero-overlay"></div>

                    <!-- Glassmorphism Testimonial Card -->
                    <div class="auth-quote-card">
                        <p class="auth-quote-text" id="quoteText">“Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore.”</p>
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="auth-quote-author" id="quoteAuthor">Ronald Richards</div>
                                <div class="auth-quote-role" id="quoteRole">Office Owner</div>
                            </div>
                        </div>

                        <!-- 4 Slider Progress Bars Matching Reference Layout -->
                        <div class="d-flex gap-2 mt-3 pt-2">
                            <div class="auth-slider-bar" onclick="showQuote(0)" data-index="0" title="Slide 1"></div>
                            <div class="auth-slider-bar" onclick="showQuote(1)" data-index="1" title="Slide 2"></div>
                            <div class="auth-slider-bar" onclick="showQuote(2)" data-index="2" title="Slide 3"></div>
                            <div class="auth-slider-bar active" onclick="showQuote(3)" data-index="3" title="Slide 4"></div>
                        </div>
                    </div>
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

// Testimonials Slider Matching Reference Design
const testimonials = [
    {
        quote: "HomeHub made finding and managing luxury rental properties completely effortless. The smoothest property management experience we have ever had.",
        author: "Sofia Martinez",
        role: "Verified Resident"
    },
    {
        quote: "The automated rent collection, digital agreements, and maintenance workflow saved our agency countless hours every single month.",
        author: "Farhan Ahmed",
        role: "Property Manager"
    },
    {
        quote: "Professional tools, instant tenant notifications, and reliable financial reporting all in one intuitive, beautifully designed platform.",
        author: "Sahra Hassan",
        role: "Real Estate Investor"
    },
    {
        quote: "Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore.",
        author: "Ronald Richards",
        role: "Office Owner"
    }
];

let currentQuoteIndex = 3; // Defaults to index 3 to match the user's reference screenshot
let quoteTimer = null;

function showQuote(index) {
    currentQuoteIndex = index;
    const textEl = document.getElementById('quoteText');
    const authorEl = document.getElementById('quoteAuthor');
    const roleEl = document.getElementById('quoteRole');
    const bars = document.querySelectorAll('.auth-slider-bar');

    if (textEl && authorEl && roleEl) {
        textEl.style.opacity = '0';
        setTimeout(() => {
            textEl.textContent = '“' + testimonials[index].quote.replace(/^“|”$/g, '') + '”';
            authorEl.textContent = testimonials[index].author;
            roleEl.textContent = testimonials[index].role;
            textEl.style.opacity = '1';
        }, 180);
    }

    bars.forEach((bar, i) => {
        if (i === index) {
            bar.classList.add('active');
        } else {
            bar.classList.remove('active');
        }
    });

    // Reset auto-rotation timer
    resetQuoteTimer();
}

function nextQuote() {
    const nextIndex = (currentQuoteIndex + 1) % testimonials.length;
    showQuote(nextIndex);
}

function resetQuoteTimer() {
    if (quoteTimer) clearInterval(quoteTimer);
    quoteTimer = setInterval(nextQuote, 6500);
}

document.addEventListener('DOMContentLoaded', () => {
    resetQuoteTimer();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
