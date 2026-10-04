<?php
/**
 * Administrator Management (Super Admin Portal)
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$currentUser = current_user();
$currentAdminId = (int)$currentUser['id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid security token.');
        header('Location: ' . BASE_URL . 'admin/admins.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            set_flash('danger', 'Name, email, and password are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'A valid email address is required.');
        } elseif (strlen($password) < 6) {
            set_flash('danger', 'Password must be at least 6 characters long.');
        } else {
            // Check if email is already taken
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                set_flash('danger', 'An account with this email address already exists.');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'admin', 'active')");
                $stmt->execute([$name, $email, $phone, $hash]);
                set_flash('success', 'Admin account "' . $name . '" created successfully!');
            }
        }
    } elseif ($action === 'edit') {
        $id    = (int)($_POST['admin_id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($id <= 0 || empty($name) || empty($email)) {
            set_flash('danger', 'Admin ID, name, and email are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'A valid email address is required.');
        } else {
            // Check email uniqueness
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                set_flash('danger', 'This email is already in use by another account.');
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ? AND role = 'admin'");
                $stmt->execute([$name, $email, $phone, $id]);

                // If updating logged-in admin, sync session
                if ($id === $currentAdminId) {
                    $_SESSION['user']['name'] = $name;
                    $_SESSION['user']['email'] = $email;
                    $_SESSION['user']['phone'] = $phone;
                }

                set_flash('success', 'Administrator details updated successfully!');
            }
        }
    } elseif ($action === 'reset_password') {
        $id       = (int)($_POST['user_id'] ?? 0);
        $password = trim($_POST['password'] ?? '');

        if ($id <= 0 || empty($password)) {
            set_flash('danger', 'Please provide a valid new password.');
        } elseif (strlen($password) < 6) {
            set_flash('danger', 'Password must be at least 6 characters long.');
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'admin'");
            $stmt->execute([$hash, $id]);
            set_flash('success', 'Password reset successfully for administrator!');
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)($_POST['admin_id'] ?? 0);
        if ($id === $currentAdminId) {
            set_flash('danger', 'You cannot deactivate your own admin account.');
        } elseif ($id > 0) {
            $stmt = $pdo->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ? AND role = 'admin'");
            $stmt->execute([$id]);
            set_flash('success', 'Admin account status updated successfully!');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['admin_id'] ?? 0);
        if ($id === $currentAdminId) {
            set_flash('danger', 'Security Notice: You cannot delete your own admin account while logged in.');
        } elseif ($id > 0) {
            // Count remaining admins
            $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            if ($adminCount <= 1) {
                set_flash('danger', 'Cannot delete the only remaining administrator.');
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'");
                $stmt->execute([$id]);
                set_flash('success', 'Administrator account deleted successfully.');
            }
        }
    }

    header('Location: ' . BASE_URL . 'admin/admins.php');
    exit;
}

// Fetch all admin accounts
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT * FROM users WHERE role = 'admin'";
$params = [];

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if (!empty($statusFilter)) {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$admins = $stmt->fetchAll();

// Metrics
$totalAdmins  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$activeAdmins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn();

$pageTitle = 'Manage Administrators - HomeHub';
$pageHeading = 'Administrator Accounts';
$pageSubtitle = 'Super Admin control panel to create, manage, and reset passwords for system administrators';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <main class="content-wrapper">
            <?= display_flash(); ?>

            <!-- Stats Row -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary-subtle text-primary">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= $totalAdmins; ?></div>
                            <div class="stat-label">Total Administrators</div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-success-subtle text-success">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= $activeAdmins; ?></div>
                            <div class="stat-label">Active Administrators</div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-12 col-xl-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning-subtle text-warning">
                            <i class="bi bi-person-badge-fill"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value small font-monospace text-truncate" style="font-size: 1.1rem;"><?= e($currentUser['email'] ?? 'ayman@gmail.com'); ?></div>
                            <div class="stat-label">Current Super Admin Session</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Administrators Table Card -->
            <div class="card shadow-sm">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center">
                        <span class="card-header-icon bg-primary text-white"><i class="bi bi-shield-fill"></i></span>
                        <div>
                            <h6 class="mb-0 fw-bold">System Administrators</h6>
                            <small class="text-muted">Accounts with full administrative authority across HomeHub</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="<?= BASE_URL; ?>admin/profile.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-person-circle me-1"></i>My Profile
                        </a>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                            <i class="bi bi-person-plus-fill me-1"></i>Add New Admin
                        </button>
                    </div>
                </div>

                <!-- Search & Filters -->
                <div class="card-body border-bottom bg-light py-2">
                    <form method="GET" action="<?= BASE_URL; ?>admin/admins.php" class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Search by name, email, or phone..." value="<?= e($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All Statuses</option>
                                <option value="active" <?= ($statusFilter === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?= ($statusFilter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-secondary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                            <?php if (!empty($search) || !empty($statusFilter)): ?>
                                <a href="<?= BASE_URL; ?>admin/admins.php" class="btn btn-outline-secondary btn-sm" title="Clear Filters"><i class="bi bi-x-circle"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Administrator</th>
                                    <th>Contact Info</th>
                                    <th>Authority</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($admins)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <div class="empty-state">
                                                <div class="empty-state-icon text-muted">
                                                    <i class="bi bi-shield-slash"></i>
                                                </div>
                                                <h6 class="empty-state-title">No Administrators Found</h6>
                                                <p class="empty-state-text">No admin accounts matched your filter criteria.</p>
                                                <button type="button" class="btn btn-primary btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                                                    <i class="bi bi-person-plus-fill me-1"></i>Add New Admin
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($admins as $adm): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded-circle bg-navy text-white d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 38px; height: 38px; border: 2px solid var(--hh-gold);">
                                                        <?= strtoupper(substr($adm['name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-main d-flex align-items-center gap-1">
                                                            <?= e($adm['name']); ?>
                                                            <?php if ((int)$adm['id'] === $currentAdminId): ?>
                                                                <span class="badge bg-gold text-dark" style="font-size: 0.65rem;">You</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <small class="text-muted font-monospace">ID: #<?= (int)$adm['id']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div><i class="bi bi-envelope text-muted me-1"></i><?= e($adm['email']); ?></div>
                                                <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= e($adm['phone'] ?? 'No phone'); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold">
                                                    <i class="bi bi-shield-check me-1"></i>Super Admin
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ((int)$adm['id'] === $currentAdminId): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                        <i class="bi bi-check-circle me-1"></i>Active (Current)
                                                    </span>
                                                <?php else: ?>
                                                    <form method="POST" action="<?= BASE_URL; ?>admin/admins.php" class="d-inline">
                                                        <?= csrf_field(); ?>
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="admin_id" value="<?= (int)$adm['id']; ?>">
                                                        <button type="submit" class="btn btn-sm p-0 border-0" title="Click to toggle status">
                                                            <?php if ($adm['status'] === 'active'): ?>
                                                                <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Active</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="bi bi-dash-circle me-1"></i>Inactive</span>
                                                            <?php endif; ?>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted">
                                                <?= !empty($adm['created_at']) ? format_date($adm['created_at'], 'd M Y') : 'System Initialized'; ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-action-group justify-content-end">
                                                    <!-- Reset Password Button -->
                                                    <button type="button" class="btn-action btn-action-view" title="Set / Reset Password"
                                                            onclick="openResetPasswordModal(<?= (int)$adm['id']; ?>, '<?= e(addslashes($adm['name'])); ?>', '<?= e(addslashes($adm['email'])); ?>')">
                                                        <i class="bi bi-key-fill"></i>
                                                    </button>

                                                    <!-- Edit Button -->
                                                    <button type="button" class="btn-action btn-action-edit" title="Edit Admin"
                                                            onclick="openEditAdminModal(<?= (int)$adm['id']; ?>, '<?= e(addslashes($adm['name'])); ?>', '<?= e(addslashes($adm['email'])); ?>', '<?= e(addslashes($adm['phone'] ?? '')); ?>')">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>

                                                    <!-- Delete Button (Disabled for logged-in admin) -->
                                                    <?php if ((int)$adm['id'] !== $currentAdminId): ?>
                                                        <form method="POST" action="<?= BASE_URL; ?>admin/admins.php" class="d-inline" data-confirm="Ma hubtaa inaad tirtirto admin-ka '<?= e(addslashes($adm['name'])); ?>'? Tallaabadan dib looma noqon karo.">
                                                            <?= csrf_field(); ?>
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="admin_id" value="<?= (int)$adm['id']; ?>">
                                                            <button type="submit" class="btn-action btn-action-delete" title="Delete Admin">
                                                                <i class="bi bi-trash3"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button type="button" class="btn-action btn-action-delete opacity-50" title="Cannot delete current logged-in session" disabled>
                                                            <i class="bi bi-trash3"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= BASE_URL; ?>admin/admins.php" class="modal-content">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="add">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-plus text-primary me-2"></i>Create New Administrator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Ayman Ahmed" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="admin@example.com" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="+252 61 XXXXXXX">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" name="password" id="new_admin_password" class="form-control" placeholder="Minimum 6 characters" required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('new_admin_password', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-primary" type="button" onclick="generateRandomPassword('new_admin_password')" title="Generate Strong Password">
                            <i class="bi bi-magic"></i> Generate
                        </button>
                    </div>
                    <small class="text-muted">Must be at least 6 characters.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Create Admin</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal fade" id="editAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= BASE_URL; ?>admin/admins.php" class="modal-content">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="admin_id" id="edit_adm_id">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Administrator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="edit_adm_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="edit_adm_email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Phone Number</label>
                    <input type="text" name="phone" id="edit_adm_phone" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= BASE_URL; ?>admin/admins.php" class="modal-content">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="reset_user_id">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-key-fill text-warning me-2"></i>Set / Reset Admin Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3">
                    <i class="bi bi-info-circle me-1"></i> Setting new password for: <strong id="reset_user_label">Admin</strong>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">New Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" name="password" id="reset_admin_password" class="form-control" placeholder="Enter new password" required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('reset_admin_password', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-primary" type="button" onclick="generateRandomPassword('reset_admin_password')" title="Generate Strong Password">
                            <i class="bi bi-magic"></i> Generate
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2-circle me-1"></i>Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditAdminModal(id, name, email, phone) {
    document.getElementById('edit_adm_id').value = id;
    document.getElementById('edit_adm_name').value = name;
    document.getElementById('edit_adm_email').value = email;
    document.getElementById('edit_adm_phone').value = phone;
    new bootstrap.Modal(document.getElementById('editAdminModal')).show();
}

function openResetPasswordModal(id, name, email) {
    document.getElementById('reset_user_id').value = id;
    document.getElementById('reset_user_label').innerText = name + ' (' + email + ')';
    document.getElementById('reset_admin_password').value = '';
    new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
}

function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

function generateRandomPassword(inputId) {
    const chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ23456789!@#$%';
    let pwd = '';
    for (let i = 0; i < 10; i++) {
        pwd += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    const input = document.getElementById(inputId);
    input.type = 'text';
    input.value = pwd;
    // Notify
    if (navigator.clipboard) {
        navigator.clipboard.writeText(pwd);
        showPasswordCopied(pwd);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
