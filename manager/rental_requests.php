<?php
/**
 * Manager Rental Requests Management
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('manager');

$managerId = (int)current_user_id();
$currency = get_setting($pdo, 'currency', '$');

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid security token.');
        header('Location: ' . BASE_URL . 'manager/rental_requests.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $requestId = (int)($_POST['request_id'] ?? 0);
    $notes = trim($_POST['admin_notes'] ?? '');

    if ($requestId > 0) {
        // Verify this request belongs to one of this manager's houses
        $stmt = $pdo->prepare("
            SELECT r.*, h.id as house_id, h.total_apartments, h.occupied_apartments, h.vacant_apartments 
            FROM rental_requests r 
            JOIN houses h ON r.house_id = h.id 
            WHERE r.id = ? AND h.manager_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$requestId, $managerId]);
        $req = $stmt->fetch();

        if ($req) {
            if ($action === 'approve') {
                $pdo->beginTransaction();
                try {
                    $upd = $pdo->prepare("UPDATE rental_requests SET status = 'approved', admin_notes = ? WHERE id = ?");
                    $upd->execute([$notes, $requestId]);

                    if ($req['status'] !== 'approved' && (int)$req['vacant_apartments'] > 0) {
                        $updHouse = $pdo->prepare("UPDATE houses SET occupied_apartments = occupied_apartments + 1, vacant_apartments = GREATEST(0, vacant_apartments - 1) WHERE id = ?");
                        $updHouse->execute([$req['house_id']]);
                    }
                    $pdo->commit();
                    set_flash('success', 'Rental application approved successfully!');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    set_flash('danger', 'Error: ' . $e->getMessage());
                }
            } elseif ($action === 'reject') {
                $pdo->beginTransaction();
                try {
                    $upd = $pdo->prepare("UPDATE rental_requests SET status = 'rejected', admin_notes = ? WHERE id = ?");
                    $upd->execute([$notes, $requestId]);

                    if ($req['status'] === 'approved' && (int)$req['occupied_apartments'] > 0) {
                        $updHouse = $pdo->prepare("UPDATE houses SET occupied_apartments = GREATEST(0, occupied_apartments - 1), vacant_apartments = LEAST(total_apartments, vacant_apartments + 1) WHERE id = ?");
                        $updHouse->execute([$req['house_id']]);
                    }
                    $pdo->commit();
                    set_flash('warning', 'Rental application rejected.');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    set_flash('danger', 'Error: ' . $e->getMessage());
                }
            }
        } else {
            set_flash('danger', 'Unauthorized request or record not found.');
        }
    }

    header('Location: ' . BASE_URL . 'manager/rental_requests.php');
    exit;
}

$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = "SELECT r.*, u.name as user_name, u.email as user_email, u.phone as user_phone,
               h.house_name, h.house_code, h.city, h.rent_price, h.vacant_apartments
        FROM rental_requests r
        JOIN users u ON r.user_id = u.id
        JOIN houses h ON r.house_id = h.id
        WHERE h.manager_id = ?";
$params = [$managerId];

if (!empty($statusFilter) && in_array($statusFilter, ['pending', 'approved', 'rejected'])) {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR u.phone LIKE ? OR h.house_name LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term]);
}

$sql .= " ORDER BY r.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Status counts
$stmtCounts = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM rental_requests r
    JOIN houses h ON r.house_id = h.id
    WHERE h.manager_id = ?
");
$stmtCounts->execute([$managerId]);
$counts = $stmtCounts->fetch();

$pageTitle = 'Rental Requests';
$pageHeading = 'Rental Requests';
$pageSubtitle = 'Review tenant applications for your assigned properties';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once __DIR__ . '/../includes/manager_sidebar.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <main class="content-wrapper">
            <?= display_flash(); ?>

            <!-- Tabs and Search -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <div class="btn-group" role="group">
                    <a href="<?= BASE_URL; ?>manager/rental_requests.php" class="btn btn-sm <?= empty($statusFilter) ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                        All (<?= (int)($counts['total'] ?? 0); ?>)
                    </a>
                    <a href="<?= BASE_URL; ?>manager/rental_requests.php?status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-warning text-dark' : 'btn-outline-secondary'; ?>">
                        Pending (<?= (int)($counts['pending'] ?? 0); ?>)
                    </a>
                    <a href="<?= BASE_URL; ?>manager/rental_requests.php?status=approved" class="btn btn-sm <?= $statusFilter === 'approved' ? 'btn-success' : 'btn-outline-secondary'; ?>">
                        Approved (<?= (int)($counts['approved'] ?? 0); ?>)
                    </a>
                    <a href="<?= BASE_URL; ?>manager/rental_requests.php?status=rejected" class="btn btn-sm <?= $statusFilter === 'rejected' ? 'btn-danger' : 'btn-outline-secondary'; ?>">
                        Rejected (<?= (int)($counts['rejected'] ?? 0); ?>)
                    </a>
                </div>

                <form method="GET" action="<?= BASE_URL; ?>manager/rental_requests.php" class="d-flex gap-2">
                    <?php if (!empty($statusFilter)): ?>
                        <input type="hidden" name="status" value="<?= e($statusFilter); ?>">
                    <?php endif; ?>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search applicant..." value="<?= e($search); ?>" style="width: 200px;">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
                </form>
            </div>

            <!-- Table -->
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">Applications (<?= count($requests); ?>)</h6>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('managerRequestsTable', 'my_rental_requests.csv')">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i>Export CSV
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="managerRequestsTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Tenant Details</th>
                                    <th>House Requested</th>
                                    <th>Monthly Rent</th>
                                    <th>Move-In Date</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                    <th class="text-end no-export">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($requests)): ?>
                                    <tr><td colspan="8" class="text-center py-5 text-muted">No rental requests found for your properties.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($requests as $r): ?>
                                        <tr>
                                            <td class="font-monospace text-muted small">#<?= (int)$r['id']; ?></td>
                                            <td>
                                                <div class="fw-bold text-main"><?= e($r['user_name']); ?></div>
                                                <small class="text-muted d-block"><i class="bi bi-telephone me-1"></i><?= e($r['user_phone'] ?? 'No phone'); ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= e($r['house_name']); ?></div>
                                                <small class="text-muted"><?= e($r['city']); ?> (<?= e($r['house_code']); ?>)</small>
                                            </td>
                                            <td class="fw-bold text-primary"><?= format_currency($r['rent_price'], $currency); ?></td>
                                            <td><?= !empty($r['move_in_date']) ? format_date($r['move_in_date']) : '<span class="text-muted small">Immediate</span>'; ?></td>
                                            <td><?= status_badge($r['status']); ?></td>
                                            <td class="small text-muted" style="max-width: 180px;">
                                                <?= e($r['request_note'] ?? $r['admin_notes'] ?? '-'); ?>
                                            </td>
                                            <td class="text-end no-export">
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($r['status'] !== 'approved'): ?>
                                                        <button type="button" class="btn btn-outline-success" onclick="openActionModal('approve', <?= (int)$r['id']; ?>, '<?= e(addslashes($r['user_name'])); ?>', '<?= e(addslashes($r['house_name'])); ?>')" title="Approve">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($r['status'] !== 'rejected'): ?>
                                                        <button type="button" class="btn btn-outline-danger" onclick="openActionModal('reject', <?= (int)$r['id']; ?>, '<?= e(addslashes($r['user_name'])); ?>', '<?= e(addslashes($r['house_name'])); ?>')" title="Reject">
                                                            <i class="bi bi-x-lg"></i>
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

<!-- Approve / Reject Modal -->
<div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= BASE_URL; ?>manager/rental_requests.php" class="modal-content">
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
                    <label class="form-label fw-semibold small">Notes / Instructions</label>
                    <textarea name="admin_notes" id="modal_notes" rows="3" class="form-control" placeholder="Comments, key collection instructions, deposit clearance..."></textarea>
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
        desc.innerHTML = `You are approving <strong>${userName}</strong> for <strong>${houseName}</strong>. 1 apartment unit will be marked occupied.`;
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
