<?php
/**
 * Admin Profile Management
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$currentUser = current_user();
$adminId = (int)$currentUser['id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$adminId]);
$user = $stmt->fetch() ?: $currentUser;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $pwd      = trim($_POST['password'] ?? '');
        $conf     = trim($_POST['password_confirmation'] ?? '');

        if (empty($name)) {
            $errors[] = 'Full name is required.';
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        } else {
            // Check email uniqueness against other users
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $stmtCheck->execute([$email, $adminId]);
            if ($stmtCheck->fetch()) {
                $errors[] = 'This email address is already in use by another account.';
            }
        }

        if (!empty($pwd)) {
            if (strlen($pwd) < 6) {
                $errors[] = 'Password must be at least 6 characters.';
            } elseif ($pwd !== $conf) {
                $errors[] = 'Password confirmation does not match.';
            }
        }

        if (empty($errors)) {
            try {
                if (!empty($pwd)) {
                    $hash = password_hash($pwd, PASSWORD_BCRYPT);
                    $upd = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, password = ? WHERE id = ?");
                    $upd->execute([$name, $email, $phone, $hash, $adminId]);
                } else {
                    $upd = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
                    $upd->execute([$name, $email, $phone, $adminId]);
                }

                $_SESSION['user']['name']  = $name;
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['phone'] = $phone;

                set_flash('success', 'Admin profile updated successfully!');
                header('Location: ' . BASE_URL . 'admin/profile.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// System stats for Admin Profile Card
$totalHouses   = (int)$pdo->query("SELECT COUNT(*) FROM houses")->fetchColumn();
$totalManagers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'manager'")->fetchColumn();
$totalUsers    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();

$pageTitle = 'Admin Profile - HomeHub';
$pageHeading = 'Admin Profile';
$pageSubtitle = 'Manage your administrator credentials, email, and personal information';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <main class="content-wrapper">
            <?= display_flash(); ?>

            <div class="row g-4">
                <!-- Profile Overview Column -->
                <div class="col-lg-4">
                    <div class="card shadow-sm text-center p-4">
                        <div class="rounded-circle bg-navy text-white d-flex align-items-center justify-content-center fw-bold mx-auto mb-3 shadow" style="width: 84px; height: 84px; font-size: 2.2rem; border: 3px solid var(--hh-gold);">
                            <?= strtoupper(substr($user['name'] ?? 'Admin', 0, 1)); ?>
                        </div>
                        <h5 class="fw-bold mb-1"><?= e($user['name']); ?></h5>
                        <div class="badge bg-gold px-3 py-1 mb-3 text-dark fw-bold text-uppercase">
                            <i class="bi bi-shield-fill-check me-1"></i>Super Administrator
                        </div>
                        <div class="text-muted small mb-2"><i class="bi bi-envelope me-1"></i><?= e($user['email']); ?></div>
                        <div class="text-muted small mb-3"><i class="bi bi-telephone me-1"></i><?= e($user['phone'] ?? 'No phone added'); ?></div>
                        
                        <hr>
                        <div class="row text-center g-2">
                            <div class="col-4">
                                <h5 class="fw-bold text-primary mb-0"><?= $totalHouses; ?></h5>
                                <small class="text-muted text-uppercase" style="font-size: 0.68rem;">Houses</small>
                            </div>
                            <div class="col-4">
                                <h5 class="fw-bold text-info mb-0"><?= $totalManagers; ?></h5>
                                <small class="text-muted text-uppercase" style="font-size: 0.68rem;">Managers</small>
                            </div>
                            <div class="col-4">
                                <h5 class="fw-bold text-success mb-0"><?= $totalUsers; ?></h5>
                                <small class="text-muted text-uppercase" style="font-size: 0.68rem;">Tenants</small>
                            </div>
                        </div>

                        <div class="mt-3 pt-3 border-top small text-muted text-start">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Role:</span>
                                <strong class="text-capitalize text-main">System Admin</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Account Status:</span>
                                <span class="badge bg-success-subtle text-success">Active</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Created At:</span>
                                <span class="text-muted"><?= !empty($user['created_at']) ? format_date($user['created_at'], 'd M Y') : 'System Initialized'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Profile Form Column -->
                <div class="col-lg-8">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <ul class="mb-0 mt-1">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= e($err); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <span class="card-header-icon bg-primary text-white"><i class="bi bi-person-gear"></i></span>
                                <div>
                                    <h6 class="mb-0 fw-bold">Edit Administrator Profile</h6>
                                    <small class="text-muted">Update your login credentials and personal info</small>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="<?= BASE_URL; ?>admin/profile.php">
                                <?= csrf_field(); ?>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                                        <div class="input-icon-group">
                                            <i class="bi bi-person input-icon-prefix"></i>
                                            <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? $user['name']); ?>" required>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                                        <div class="input-icon-group">
                                            <i class="bi bi-envelope input-icon-prefix"></i>
                                            <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? $user['email']); ?>" required>
                                        </div>
                                        <div class="form-text small">Used to sign in to your administrator dashboard.</div>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-semibold small">Phone Number (EVC Plus / Telesom)</label>
                                        <div class="input-icon-group">
                                            <i class="bi bi-telephone input-icon-prefix"></i>
                                            <input type="text" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? $user['phone'] ?? ''); ?>" placeholder="+252 61 XXXXXXX">
                                        </div>
                                    </div>

                                    <div class="col-12"><hr class="my-2"></div>

                                    <div class="col-12">
                                        <h6 class="fw-bold mb-1"><i class="bi bi-key-fill text-gold me-2"></i>Change Admin Password</h6>
                                        <p class="text-muted small mb-3">Leave blank if you do not want to change your current password.</p>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">New Password</label>
                                        <div class="input-icon-group">
                                            <i class="bi bi-lock input-icon-prefix"></i>
                                            <input type="password" name="password" id="admin_new_pwd" class="form-control" placeholder="Minimum 6 characters">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">Confirm New Password</label>
                                        <div class="input-icon-group">
                                            <i class="bi bi-shield-lock input-icon-prefix"></i>
                                            <input type="password" name="password_confirmation" id="admin_conf_pwd" class="form-control" placeholder="Re-type new password">
                                        </div>
                                    </div>

                                    <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-between align-items-center">
                                        <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>All profile changes take effect immediately.</span>
                                        <button type="submit" class="btn btn-primary px-4">
                                            <i class="bi bi-check-circle me-1"></i>Save Profile Changes
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
