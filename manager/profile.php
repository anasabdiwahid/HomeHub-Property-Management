<?php
/**
 * Manager Profile Management
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('manager');

$currentUser = current_user();
$managerId = (int)$currentUser['id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$managerId]);
$user = $stmt->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Invalid security token.';
    } else {
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $pwd   = trim($_POST['password'] ?? '');
        $conf  = trim($_POST['password_confirmation'] ?? '');

        if (empty($name)) {
            $errors[] = 'Name cannot be empty.';
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
                    $upd = $pdo->prepare("UPDATE users SET name = ?, phone = ?, password = ? WHERE id = ?");
                    $upd->execute([$name, $phone, $hash, $managerId]);
                } else {
                    $upd = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
                    $upd->execute([$name, $phone, $managerId]);
                }

                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['phone'] = $phone;

                set_flash('success', 'Profile updated successfully!');
                header('Location: ' . BASE_URL . 'manager/profile.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Portfolio stat
$assignedHousesCount = (int)$pdo->query("SELECT COUNT(*) FROM houses WHERE manager_id = {$managerId}")->fetchColumn();

$pageTitle = 'Manager Profile - HomeHub';
$pageHeading = 'My Profile';
$pageSubtitle = 'Manage your account credentials and personal information';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once __DIR__ . '/../includes/manager_sidebar.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <main class="content-wrapper">
            <?= display_flash(); ?>

            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card shadow-sm text-center p-4">
                        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center fw-bold mx-auto mb-3 shadow" style="width: 80px; height: 80px; font-size: 2rem;">
                            <?= strtoupper(substr($user['name'], 0, 1)); ?>
                        </div>
                        <h5 class="fw-bold mb-1"><?= e($user['name']); ?></h5>
                        <div class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 mb-3 text-uppercase">
                            Property Manager
                        </div>
                        <div class="text-muted small mb-2"><i class="bi bi-envelope me-1"></i><?= e($user['email']); ?></div>
                        <div class="text-muted small mb-3"><i class="bi bi-telephone me-1"></i><?= e($user['phone'] ?? 'No phone added'); ?></div>
                        
                        <hr>
                        <div class="d-flex justify-content-around text-center">
                            <div>
                                <h4 class="fw-bold text-primary mb-0"><?= $assignedHousesCount; ?></h4>
                                <small class="text-muted text-uppercase" style="font-size: 0.72rem;">Assigned Houses</small>
                            </div>
                            <div>
                                <h4 class="fw-bold text-success mb-0">Active</h4>
                                <small class="text-muted text-uppercase" style="font-size: 0.72rem;">Account Status</small>
                            </div>
                        </div>
                    </div>
                </div>

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
                        <div class="card-header">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-person-gear text-primary me-2"></i>Edit Profile Details</h6>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="<?= BASE_URL; ?>manager/profile.php">
                                <?= csrf_field(); ?>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? $user['name']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Email Address</label>
                                    <input type="email" class="form-control bg-light" value="<?= e($user['email']); ?>" readonly disabled>
                                    <div class="form-text small">Email cannot be changed directly. Contact administrator for email updates.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Phone Number (EVC Plus / Telesom)</label>
                                    <input type="text" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? $user['phone']); ?>" placeholder="+252 61 XXXXXXX">
                                </div>

                                <hr class="my-4">
                                <h6 class="fw-bold mb-3"><i class="bi bi-shield-lock text-primary me-1"></i>Change Password (Optional)</h6>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">New Password</label>
                                        <input type="password" name="password" class="form-control" placeholder="Leave empty to keep current">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">Confirm New Password</label>
                                        <input type="password" name="password_confirmation" class="form-control" placeholder="Repeat new password">
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="bi bi-save me-1"></i>Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
