<?php
/**
 * Managers Management
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

// Handle Actions (Add, Edit, Toggle, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid security token.');
        header('Location: ' . BASE_URL . 'admin/managers.php');
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
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                set_flash('danger', 'An account with this email already exists.');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'manager', 'active')");
                $stmt->execute([$name, $email, $phone, $hash]);
                set_flash('success', 'Manager "' . $name . '" created successfully!');
            }
        }
    } elseif ($action === 'edit') {
        $id    = (int)($_POST['manager_id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $pwd   = trim($_POST['password'] ?? '');

        if ($id <= 0 || empty($name) || empty($email)) {
            set_flash('danger', 'Name and email are required.');
        } else {
            // Check email uniqueness
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                set_flash('danger', 'This email is already in use by another user.');
            } else {
                if (!empty($pwd)) {
                    $hash = password_hash($pwd, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, password = ? WHERE id = ? AND role = 'manager'");
                    $stmt->execute([$name, $email, $phone, $hash, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ? AND role = 'manager'");
                    $stmt->execute([$name, $email, $phone, $id]);
                }
                set_flash('success', 'Manager updated successfully!');
            }
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)($_POST['manager_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ? AND role = 'manager'");
            $stmt->execute([$id]);
            set_flash('success', 'Manager status updated successfully!');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['manager_id'] ?? 0);
        if ($id > 0) {
            // Unassign houses first
            $pdo->prepare("UPDATE houses SET manager_id = NULL WHERE manager_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'manager'")->execute([$id]);
            set_flash('success', 'Manager deleted successfully (assigned houses set to unassigned).');
        }
    }

    header('Location: ' . BASE_URL . 'admin/managers.php');
    exit;
}

// Fetch managers with assigned houses list
$managers = $pdo->query("
    SELECT u.*, COUNT(h.id) as assigned_houses,
           GROUP_CONCAT(h.house_name SEPARATOR ', ') as house_names
    FROM users u 
    LEFT JOIN houses h ON u.id = h.manager_id 
    WHERE u.role = 'manager'
    GROUP BY u.id 
    ORDER BY u.id DESC
")->fetchAll();

$pageTitle = 'Manage Managers';
$pageHeading = 'Property Managers';
$pageSubtitle = 'Create and oversee property managers and their assigned house portfolios';
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
                        <h5 class="fw-bold mb-1">Registered Managers (<?= count($managers); ?>)</h5>
                        <p class="text-muted small mb-0">Managers can oversee assigned properties, update apartment statuses, and track rent</p>
                    </div>
                    <div>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addManagerModal">
                            <i class="bi bi-person-plus-fill me-1"></i>Add New Manager
                        </button>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="managersTable">
                            <thead>
                                <tr>
                                    <th>Manager Name</th>
                                    <th>Contact Info</th>
                                    <th>Assigned Houses</th>
                                    <th>Status</th>
                                    <th>Joined Date</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($managers)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">No managers found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($managers as $mgr): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px;">
                                                        <?= strtoupper(substr($mgr['name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-main"><?= e($mgr['name']); ?></div>
                                                        <small class="text-muted font-monospace">ID: #<?= (int)$mgr['id']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div><i class="bi bi-envelope text-muted me-1"></i><?= e($mgr['email']); ?></div>
                                                <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= e($mgr['phone'] ?? 'No phone'); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary mb-1">
                                                    <?= (int)$mgr['assigned_houses']; ?> House(s)
                                                </span>
                                                <?php if (!empty($mgr['house_names'])): ?>
                                                    <small class="text-muted d-block text-truncate" style="max-width: 200px;" title="<?= e($mgr['house_names']); ?>">
                                                        <?= e($mgr['house_names']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" action="<?= BASE_URL; ?>admin/managers.php" class="d-inline">
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="manager_id" value="<?= (int)$mgr['id']; ?>">
                                                    <button type="submit" class="btn btn-sm p-0 border-0" title="Click to toggle status">
                                                        <?php if ($mgr['status'] === 'active'): ?>
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="bi bi-dash-circle me-1"></i>Inactive</span>
                                                        <?php endif; ?>
                                                    </button>
                                                </form>
                                            </td>
                                            <td class="small text-muted">
                                                <?= format_date($mgr['created_at'], 'd M Y'); ?>
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-secondary me-1"
                                                        onclick="openEditManagerModal(<?= (int)$mgr['id']; ?>, '<?= e(addslashes($mgr['name'])); ?>', '<?= e(addslashes($mgr['email'])); ?>', '<?= e(addslashes($mgr['phone'] ?? '')); ?>')">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" action="<?= BASE_URL; ?>admin/managers.php" class="d-inline" onsubmit="return confirm('Are you sure you want to remove manager \'<?= e($mgr['name']); ?>\'?');">
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="manager_id" value="<?= (int)$mgr['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
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

<!-- Add Manager Modal -->
<div class="modal fade" id="addManagerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= BASE_URL; ?>admin/managers.php" class="modal-content">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="add">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill text-primary me-2"></i>Create Property Manager</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Ahmed Nur" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="ahmed@homehub.so" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Phone (EVC Plus / Telesom)</label>
                    <input type="text" name="phone" class="form-control" placeholder="+252 61 XXXXXXX">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Login Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Create Manager Account</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Manager Modal -->
<div class="modal fade" id="editManagerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= BASE_URL; ?>admin/managers.php" class="modal-content">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="manager_id" id="edit_mgr_id">

            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Manager Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="edit_mgr_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="edit_mgr_email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Phone Number</label>
                    <input type="text" name="phone" id="edit_mgr_phone" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">New Password (leave empty to keep current)</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditManagerModal(id, name, email, phone) {
    document.getElementById('edit_mgr_id').value = id;
    document.getElementById('edit_mgr_name').value = name;
    document.getElementById('edit_mgr_email').value = email;
    document.getElementById('edit_mgr_phone').value = phone;
    new bootstrap.Modal(document.getElementById('editManagerModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
