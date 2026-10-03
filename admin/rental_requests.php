<?php
/**
 * Rental Requests Management
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$currency = get_setting($pdo, 'currency', '$');

// Handle Status Updates (Approve, Reject, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid security token.');
        header('Location: ' . BASE_URL . 'admin/rental_requests.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $requestId = (int)($_POST['request_id'] ?? 0);
    $notes = trim($_POST['admin_notes'] ?? '');

    if ($requestId > 0) {
        // Fetch request info
        $stmt = $pdo->prepare("SELECT r.*, h.total_apartments, h.occupied_apartments, h.vacant_apartments FROM rental_requests r JOIN houses h ON r.house_id = h.id WHERE r.id = ? LIMIT 1");
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();

        if ($req) {
            if ($action === 'approve') {
                $pdo->beginTransaction();
                try {
                    // Update request status
                    $upd = $pdo->prepare("UPDATE rental_requests SET status = 'approved', admin_notes = ? WHERE id = ?");
                    $upd->execute([$notes, $requestId]);

                    // If vacant apartments exist and old status was pending/rejected, update house counts
                    if ($req['status'] !== 'approved' && (int)$req['vacant_apartments'] > 0) {
                        $updHouse = $pdo->prepare("UPDATE houses SET occupied_apartments = occupied_apartments + 1, vacant_apartments = GREATEST(0, vacant_apartments - 1) WHERE id = ?");
                        $updHouse->execute([$req['house_id']]);
                    }

                    $pdo->commit();
                    set_flash('success', 'Rental request approved successfully!');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    set_flash('danger', 'Error approving request: ' . $e->getMessage());
                }
            } elseif ($action === 'reject') {
                $pdo->beginTransaction();
                try {
                    $upd = $pdo->prepare("UPDATE rental_requests SET status = 'rejected', admin_notes = ? WHERE id = ?");
                    $upd->execute([$notes, $requestId]);

                    // If it was previously approved, restore vacant unit
                    if ($req['status'] === 'approved' && (int)$req['occupied_apartments'] > 0) {
                        $updHouse = $pdo->prepare("UPDATE houses SET occupied_apartments = GREATEST(0, occupied_apartments - 1), vacant_apartments = LEAST(total_apartments, vacant_apartments + 1) WHERE id = ?");
                        $updHouse->execute([$req['house_id']]);
                    }

                    $pdo->commit();
                    set_flash('warning', 'Rental request rejected.');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    set_flash('danger', 'Error rejecting request: ' . $e->getMessage());
                }
            } elseif ($action === 'delete') {
                $del = $pdo->prepare("DELETE FROM rental_requests WHERE id = ?");
                $del->execute([$requestId]);
                set_flash('success', 'Rental request removed.');
            }
        }
    }

    header('Location: ' . BASE_URL . 'admin/rental_requests.php');
    exit;
}

// Filter parameters
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = "SELECT r.*, u.name as user_name, u.email as user_email, u.phone as user_phone,
               h.house_name, h.house_code, h.city, h.rent_price, h.vacant_apartments,
               mgr.name as manager_name
        FROM rental_requests r
        JOIN users u ON r.user_id = u.id
        JOIN houses h ON r.house_id = h.id
        LEFT JOIN users mgr ON h.manager_id = mgr.id
        WHERE 1=1";
$params = [];

if (!empty($statusFilter) && in_array($statusFilter, ['pending', 'approved', 'rejected'])) {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR u.phone LIKE ? OR h.house_name LIKE ? OR h.house_code LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

$sql .= " ORDER BY r.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Counts for status pills
$countPending  = (int)$pdo->query("SELECT COUNT(*) FROM rental_requests WHERE status = 'pending'")->fetchColumn();
$countApproved = (int)$pdo->query("SELECT COUNT(*) FROM rental_requests WHERE status = 'approved'")->fetchColumn();
$countRejected = (int)$pdo->query("SELECT COUNT(*) FROM rental_requests WHERE status = 'rejected'")->fetchColumn();
$countAll      = (int)$pdo->query("SELECT COUNT(*) FROM rental_requests")->fetchColumn();

$pageTitle = 'Rental Requests';
$pageHeading = 'Rental Requests';
$pageSubtitle = 'Review, approve, or reject tenant leasing inquiries';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <main class="content-wrapper">
            <?= display_flash(); ?>

            <!-- Status Filter Tabs -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <div class="btn-group" role="group">
                    <a href="<?= BASE_URL; ?>admin/rental_requests.php" class="btn btn-sm <?= empty($statusFilter) ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                        All (<?= $countAll; ?>)
                    </a>
                    <a href="<?= BASE_URL; ?>admin/rental_requests.php?status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-warning text-dark' : 'btn-outline-secondary'; ?>">
                        Pending (<?= $countPending; ?>)
                    </a>
                    <a href="<?= BASE_URL; ?>admin/rental_requests.php?status=approved" class="btn btn-sm <?= $statusFilter === 'approved' ? 'btn-success' : 'btn-outline-secondary'; ?>">
                        Approved (<?= $countApproved; ?>)
                    </a>
                    <a href="<?= BASE_URL; ?>admin/rental_requests.php?status=rejected" class="btn btn-sm <?= $statusFilter === 'rejected' ? 'btn-danger' : 'btn-outline-secondary'; ?>">
                        Rejected (<?= $countRejected; ?>)
                    </a>
                </div>

                <form method="GET" action="<?= BASE_URL; ?>admin/rental_requests.php" class="d-flex gap-2">
                    <?php if (!empty($statusFilter)): ?>
                        <input type="hidden" name="status" value="<?= e($statusFilter); ?>">
                    <?php endif; ?>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search tenant or house..." value="<?= e($search); ?>" style="width: 220px;">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
                    <?php if (!empty($search)): ?>
                        <a href="<?= BASE_URL; ?>admin/rental_requests.php<?= !empty($statusFilter) ? '?status=' . e($statusFilter) : ''; ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Requests Table -->
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">Rental Applications (<?= count($requests); ?>)</h6>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('requestsTable', 'rental_requests.csv')">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i>Export CSV
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="requestsTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Tenant Details</th>
                                    <th>Requested Property</th>
                                    <th>Rent Price</th>
                                    <th>Move-In Date</th>
                                    <th>Status</th>
                                    <th>Date Applied</th>
                                    <th class="text-end no-export">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($requests)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">No rental requests found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($requests as $r): ?>
                                        <tr>
                                            <td class="font-monospace text-muted small">#<?= (int)$r['id']; ?></td>
                                            <td>
                                                <div class="fw-bold text-main"><?= e($r['user_name']); ?></div>
                                                <small class="text-muted d-block"><i class="bi bi-telephone me-1"></i><?= e($r['user_phone'] ?? 'No phone'); ?></small>
                                                <small class="text-muted d-block"><i class="bi bi-envelope me-1"></i><?= e($r['user_email']); ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= e($r['house_name']); ?></div>
                                                <span class="badge bg-secondary-subtle text-secondary font-monospace"><?= e($r['house_code']); ?></span>
                                                <small class="text-muted d-block"><i class="bi bi-geo-alt me-1"></i><?= e($r['city']); ?></small>
                                            </td>
                                            <td class="fw-bold text-primary">
                                                <?= format_currency($r['rent_price'], $currency); ?>
                                                <small class="text-muted d-block">per month</small>
                                            </td>
                                            <td>
                                                <?= !empty($r['move_in_date']) ? format_date($r['move_in_date']) : '<span class="text-muted small">Immediate</span>'; ?>
                                            </td>
                                            <td>
                                                <?= status_badge($r['status']); ?>
                                                <?php if (!empty($r['admin_notes'])): ?>
                                                    <small class="text-muted d-block text-truncate mt-1" style="max-width: 150px;" title="<?= e($r['admin_notes']); ?>">
                                                        <i class="bi bi-chat-left-text me-1"></i><?= e($r['admin_notes']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted">
                                                <?= format_date($r['created_at']); ?><br>
                                                <small><?= time_ago($r['created_at']); ?></small>
                                            </td>
                                            <td class="text-end no-export">
                                                <div class="dropdown d-inline-block">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        Manage
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                        <?php if ($r['status'] !== 'approved'): ?>
                                                            <li>
                                                                <button type="button" class="dropdown-item text-success" onclick="openActionModal('approve', <?= (int)$r['id']; ?>, '<?= e(addslashes($r['user_name'])); ?>', '<?= e(addslashes($r['house_name'])); ?>')">
                                                                    <i class="bi bi-check-circle me-2"></i>Approve Application
                                                                </button>
                                                            </li>
                                                        <?php endif; ?>
                                                        <?php if ($r['status'] !== 'rejected'): ?>
                                                            <li>
                                                                <button type="button" class="dropdown-item text-warning" onclick="openActionModal('reject', <?= (int)$r['id']; ?>, '<?= e(addslashes($r['user_name'])); ?>', '<?= e(addslashes($r['house_name'])); ?>')">
                                                                    <i class="bi bi-x-circle me-2"></i>Reject Application
                                                                </button>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <form method="POST" action="<?= BASE_URL; ?>admin/rental_requests.php" onsubmit="return confirm('Delete this request permanently?');">
                                                                <?= csrf_field(); ?>
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="request_id" value="<?= (int)$r['id']; ?>">
                                                                <button type="submit" class="dropdown-item text-danger">
                                                                    <i class="bi bi-trash me-2"></i>Delete Request
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
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

<!-- Approve / Reject Modal -->
<div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= BASE_URL; ?>admin/rental_requests.php" class="modal-content">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" id="modal_action" value="approve">
            <input type="hidden" name="request_id" id="modal_request_id">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modal_title">Review Rental Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="modal_desc" class="mb-3"></p>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Notes / Reason (Sent to Tenant)</label>
                    <textarea name="admin_notes" id="modal_notes" rows="3" class="form-control" placeholder="Optional comments regarding verification, deposit, lease terms, or rejection reason..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="modal_submit_btn">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
function openActionModal(action, id, userName, houseName) {
    document.getElementById('modal_action').value = action;
    document.getElementById('modal_request_id').value = id;
    const title = document.getElementById('modal_title');
    const desc = document.getElementById('modal_desc');
    const btn = document.getElementById('modal_submit_btn');

    if (action === 'approve') {
        title.innerText = 'Approve Rental Application';
        desc.innerHTML = `You are approving tenant <strong>${userName}</strong> for <strong>${houseName}</strong>. This will allocate 1 vacant unit to occupied status.`;
        btn.innerText = 'Approve Application';
        btn.className = 'btn btn-success btn-sm';
    } else {
        title.innerText = 'Reject Rental Application';
        desc.innerHTML = `You are rejecting the rental request from <strong>${userName}</strong> for <strong>${houseName}</strong>.`;
        btn.innerText = 'Reject Application';
        btn.className = 'btn btn-danger btn-sm';
    }
    new bootstrap.Modal(document.getElementById('actionModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
