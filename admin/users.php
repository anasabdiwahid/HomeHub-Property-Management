<?php
/**
 * Users / Tenants Management
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid security token.');
        header('Location: ' . BASE_URL . 'admin/users.php');
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
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                set_flash('danger', 'A user with this email already exists.');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'user', 'active')");
                $stmt->execute([$name, $email, $phone, $hash]);
                set_flash('success', 'User "' . $name . '" registered successfully!');
            }
        }
    } elseif ($action === 'edit') {
        $id    = (int)($_POST['user_id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $pwd   = trim($_POST['password'] ?? '');

        if ($id <= 0 || empty($name) || empty($email)) {
            set_flash('danger', 'Name and email are required.');
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                set_flash('danger', 'This email is already in use.');
            } else {
                if (!empty($pwd)) {
                    $hash = password_hash($pwd, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, password = ? WHERE id = ? AND role = 'user'");
                    $stmt->execute([$name, $email, $phone, $hash, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ? AND role = 'user'");
                    $stmt->execute([$name, $email, $phone, $id]);
                }
                set_flash('success', 'User updated successfully!');
            }
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ? AND role = 'user'");
            $stmt->execute([$id]);
            set_flash('success', 'User status updated successfully!');
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
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'user'");
            $stmt->execute([$hash, $id]);
            set_flash('success', 'Password reset successfully for user!');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'user'")->execute([$id]);
            set_flash('success', 'User deleted successfully.');
        }
    }

    header('Location: ' . BASE_URL . 'admin/users.php');
    exit;
}

// Fetch users with rental requests count
$users = $pdo->query("
    SELECT u.*, COUNT(r.id) as request_count,
           SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved_count
    FROM users u 
    LEFT JOIN rental_requests r ON u.id = r.user_id 
    WHERE u.role = 'user'
    GROUP BY u.id 
    ORDER BY u.id DESC
")->fetchAll();

$pageTitle = 'Manage Users';
$pageHeading = 'Users (Tenants)';
$pageSubtitle = 'Manage registered prospective and current tenants';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <main class="content-wrapper">
            <?= display_flash(); ?>

            <div class="card mb-4">
                <div class="card-body d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                    <div>
                        <h5 class="fw-bold mb-1">Registered Tenants (<?= count($users); ?>)</h5>
                        <p class="text-muted small mb-0">Tenants can browse verified properties, submit rental requests, and track status</p>
                    </div>
                    <div>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="bi bi-person-plus-fill me-1"></i>Add New Tenant
                        </button>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="usersTable">
                            <thead>
                                <tr>
                                    <th>Tenant</th>
                                    <th>Contact Info</th>
                                    <th>Rental Activity</th>
                                    <th>Status</th>
                                    <th>Registration Date</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty-state">
                                                <div class="empty-state-icon">
                                                    <i class="bi bi-people"></i>
                                                </div>
                                                <h6 class="empty-state-title">No Registered Tenants Found</h6>
                                                <p class="empty-state-text">No tenants are registered in the system yet. Tenants can register via public portal or you can manually add them.</p>
                                                <button type="button" class="btn btn-primary btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                                    <i class="bi bi-person-plus-fill me-1"></i>Add New Tenant
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px;">
                                                        <?= strtoupper(substr($u['name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-main"><?= e($u['name']); ?></div>
                                                        <small class="text-muted font-monospace">ID: #<?= (int)$u['id']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div><i class="bi bi-envelope text-muted me-1"></i><?= e($u['email']); ?></div>
                                                <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= e($u['phone'] ?? 'No phone'); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary-subtle text-secondary me-1">
                                                    <?= (int)$u['request_count']; ?> Requests
                                                </span>
                                                <?php if ((int)$u['approved_count'] > 0): ?>
                                                    <span class="badge bg-success-subtle text-success">
                                                        <i class="bi bi-check-circle me-1"></i><?= (int)$u['approved_count']; ?> Approved
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" action="<?= BASE_URL; ?>admin/users.php" class="d-inline">
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="user_id" value="<?= (int)$u['id']; ?>">
                                                    <button type="submit" class="btn btn-sm p-0 border-0" title="Click to toggle status">
                                                        <?php if ($u['status'] === 'active'): ?>
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="bi bi-dash-circle me-1"></i>Inactive</span>
                                                        <?php endif; ?>
                                                    </button>
                                                </form>
                                            </td>
                                            <td class="small text-muted">
                                                <?= format_date($u['created_at'], 'd M Y'); ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-action-group justify-content-end">
                                                    <button type="button" class="btn-action btn-action-view" title="Set / Reset Password"
                                                            onclick="openResetPasswordModal(<?= (int)$u['id']; ?>, '<?= e(addslashes($u['name'])); ?>', '<?= e(addslashes($u['email'])); ?>')">
                                                        <i class="bi bi-key-fill"></i>
                                                    </button>
                                                    <button type="button" class="btn-action btn-action-edit" title="Edit Tenant"
                                                            onclick="openEditUserModal(<?= (int)$u['id']; ?>, '<?= e(addslashes($u['name'])); ?>', '<?= e(addslashes($u['email'])); ?>', '<?= e(addslashes($u['phone'] ?? '')); ?>')">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <form method="POST" action="<?= BASE_URL; ?>admin/users.php" class="d-inline" onsubmit="return confirm('Are you sure you want to remove user \'<?= e($u['name']); ?>\'?');">
                                                        <?= csrf_field(); ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="user_id" value="<?= (int)$u['id']; ?>">
                                                        <button type="submit" class="btn-action btn-action-delete" title="Delete Tenant">
                                                            <i class="bi bi-trash3"></i>
                                                        </button>
                                                    </form>
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= BASE_URL; ?>admin/users.php" class="modal-content">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="add">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill text-primary me-2"></i>Register New Tenant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Jama Hassan" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="jama@example.com" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="+252 61 XXXXXXX">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" name="password" id="new_usr_password" class="form-control" placeholder="Minimum 6 characters" required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('new_usr_password', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-primary" type="button" onclick="generateRandomPassword('new_usr_password')" title="Generate Strong Password">
                            <i class="bi bi-magic"></i> Generate
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Create Tenant</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= BASE_URL; ?>admin/users.php" class="modal-content">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" id="edit_usr_id">

            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Tenant Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="edit_usr_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="edit_usr_email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Phone Number</label>
                    <input type="text" name="phone" id="edit_usr_phone" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">New Password (leave empty to keep current)</label>
                    <div class="input-group">
                        <input type="password" name="password" id="edit_usr_password" class="form-control" placeholder="••••••••">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('edit_usr_password', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-primary" type="button" onclick="generateRandomPassword('edit_usr_password')">
                            <i class="bi bi-magic"></i> Generate
                        </button>
                    </div>
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
        <form method="POST" action="<?= BASE_URL; ?>admin/users.php" class="modal-content">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="reset_user_id">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-key-fill text-warning me-2"></i>Set / Reset Tenant Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3">
                    <i class="bi bi-info-circle me-1"></i> Setting new password for tenant: <strong id="reset_user_label">Tenant</strong>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">New Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" name="password" id="reset_usr_password" class="form-control" placeholder="Enter new password" required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('reset_usr_password', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-primary" type="button" onclick="generateRandomPassword('reset_usr_password')" title="Generate Strong Password">
                            <i class="bi bi-magic"></i> Generate
                        </button>
                    </div>
                    <small class="text-muted">Minimum 6 characters.</small>
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
function openEditUserModal(id, name, email, phone) {
    document.getElementById('edit_usr_id').value = id;
    document.getElementById('edit_usr_name').value = name;
    document.getElementById('edit_usr_email').value = email;
    document.getElementById('edit_usr_phone').value = phone;
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

function openResetPasswordModal(id, name, email) {
    document.getElementById('reset_user_id').value = id;
    document.getElementById('reset_user_label').innerText = name + ' (' + email + ')';
    document.getElementById('reset_usr_password').value = '';
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
    if (navigator.clipboard) {
        navigator.clipboard.writeText(pwd);
        alert('Generated Password: ' + pwd + '\n(Copied to clipboard!)');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
