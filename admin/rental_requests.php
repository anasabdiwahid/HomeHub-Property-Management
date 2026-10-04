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
    $apartmentId = (int)($_POST['apartment_id'] ?? 0);
    $notes = trim($_POST['admin_notes'] ?? '');

    if ($requestId > 0) {
        // Fetch request info
        $stmt = $pdo->prepare("SELECT r.*, h.total_apartments, h.occupied_apartments, h.vacant_apartments FROM rental_requests r JOIN houses h ON r.house_id = h.id WHERE r.id = ? LIMIT 1");
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();

        if ($req) {
            $houseId = (int)$req['house_id'];

            if ($action === 'approve') {
                if ($req['status'] !== 'approved' && (int)$req['vacant_apartments'] <= 0) {
                    set_flash('danger', 'Lama aqbali karo codsigan: Gurigan wuu buuxaa, dhammaan waa la wada deggenyahay (0 qol oo bannaan ayaa haray)!');
                    header('Location: ' . BASE_URL . 'admin/rental_requests.php');
                    exit;
                }

                // Ensure individual apartment units exist
                ensure_house_apartments($pdo, $houseId);

                // Fetch chosen apartment or first available vacant unit if none selected
                $assignedAptName = '';
                if ($apartmentId > 0) {
                    $aptStmt = $pdo->prepare("SELECT id, apartment_number FROM apartments WHERE id = ? AND house_id = ? LIMIT 1");
                    $aptStmt->execute([$apartmentId, $houseId]);
                    $chosenApt = $aptStmt->fetch();
                    if ($chosenApt) {
                        $assignedAptName = $chosenApt['apartment_number'];
                    }
                }

                if (empty($assignedAptName)) {
                    // Pick first vacant apartment
                    $vacStmt = $pdo->prepare("SELECT id, apartment_number FROM apartments WHERE house_id = ? AND status = 'vacant' ORDER BY id ASC LIMIT 1");
                    $vacStmt->execute([$houseId]);
                    $chosenApt = $vacStmt->fetch();
                    if ($chosenApt) {
                        $apartmentId = (int)$chosenApt['id'];
                        $assignedAptName = $chosenApt['apartment_number'];
                    }
                }

                if ($apartmentId <= 0) {
                    set_flash('danger', 'Ma jiro qol bannaan oo loo xilsaari karo codsigan.');
                    header('Location: ' . BASE_URL . 'admin/rental_requests.php');
                    exit;
                }

                $pdo->beginTransaction();
                try {
                    // If request was previously assigned to a different apartment, release it
                    if (!empty($req['apartment_id']) && (int)$req['apartment_id'] !== $apartmentId) {
                        $relPrev = $pdo->prepare("UPDATE apartments SET status = 'vacant', current_tenant_id = NULL, rental_request_id = NULL WHERE id = ?");
                        $relPrev->execute([(int)$req['apartment_id']]);
                    }

                    // Update request
                    $upd = $pdo->prepare("UPDATE rental_requests SET status = 'approved', apartment_id = ?, assigned_apartment = ?, admin_notes = ? WHERE id = ?");
                    $upd->execute([$apartmentId, $assignedAptName, $notes, $requestId]);

                    // Update apartment
                    $updApt = $pdo->prepare("UPDATE apartments SET status = 'occupied', current_tenant_id = ?, rental_request_id = ? WHERE id = ?");
                    $updApt->execute([$req['user_id'], $requestId, $apartmentId]);

                    // Sync occupancy counters
                    sync_house_occupancy_counts($pdo, $houseId);

                    $pdo->commit();
                    set_flash('success', "Rental request approved successfully! Assigned unit: {$assignedAptName}");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    set_flash('danger', 'Error approving request: ' . $e->getMessage());
                }
            } elseif ($action === 'reject') {
                $pdo->beginTransaction();
                try {
                    $upd = $pdo->prepare("UPDATE rental_requests SET status = 'rejected', admin_notes = ? WHERE id = ?");
                    $upd->execute([$notes, $requestId]);

                    // If previously approved or had apartment assigned, release it
                    if (!empty($req['apartment_id'])) {
                        $relApt = $pdo->prepare("UPDATE apartments SET status = 'vacant', current_tenant_id = NULL, rental_request_id = NULL WHERE id = ?");
                        $relApt->execute([(int)$req['apartment_id']]);
                    }

                    sync_house_occupancy_counts($pdo, $houseId);
                    $pdo->commit();
                    set_flash('warning', 'Rental request rejected and assigned apartment unit released.');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    set_flash('danger', 'Error rejecting request: ' . $e->getMessage());
                }
            } elseif ($action === 'delete') {
                if (!empty($req['apartment_id'])) {
                    $relApt = $pdo->prepare("UPDATE apartments SET status = 'vacant', current_tenant_id = NULL, rental_request_id = NULL WHERE id = ?");
                    $relApt->execute([(int)$req['apartment_id']]);
                    sync_house_occupancy_counts($pdo, $houseId);
                }
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

// Fetch all apartments grouped by house for assignment modal
$houseApartmentsData = [];
$allAptsStmt = $pdo->query("SELECT id, house_id, apartment_number, rent_price, floor, status FROM apartments ORDER BY id ASC");
while ($aRow = $allAptsStmt->fetch()) {
    $houseApartmentsData[(int)$aRow['house_id']][] = $aRow;
}

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
                        <i class="bi bi-collection me-1"></i>All (<?= $countAll; ?>)
                    </a>
                    <a href="<?= BASE_URL; ?>admin/rental_requests.php?status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-warning text-dark' : 'btn-outline-secondary'; ?>">
                        <i class="bi bi-clock-history me-1"></i>Pending (<?= $countPending; ?>)
                    </a>
                    <a href="<?= BASE_URL; ?>admin/rental_requests.php?status=approved" class="btn btn-sm <?= $statusFilter === 'approved' ? 'btn-success' : 'btn-outline-secondary'; ?>">
                        <i class="bi bi-check-circle me-1"></i>Approved (<?= $countApproved; ?>)
                    </a>
                    <a href="<?= BASE_URL; ?>admin/rental_requests.php?status=rejected" class="btn btn-sm <?= $statusFilter === 'rejected' ? 'btn-danger' : 'btn-outline-secondary'; ?>">
                        <i class="bi bi-x-circle me-1"></i>Rejected (<?= $countRejected; ?>)
                    </a>
                </div>

                <form method="GET" action="<?= BASE_URL; ?>admin/rental_requests.php" class="d-flex gap-2">
                    <?php if (!empty($statusFilter)): ?>
                        <input type="hidden" name="status" value="<?= e($statusFilter); ?>">
                    <?php endif; ?>
                    <div class="input-icon-group">
                        <i class="bi bi-search input-icon-prefix"></i>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search applicant..." value="<?= e($search); ?>" style="width: 240px;">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-arrow-right"></i></button>
                    <?php if (!empty($search)): ?>
                        <a href="<?= BASE_URL; ?>admin/rental_requests.php<?= !empty($statusFilter) ? '?status=' . e($statusFilter) : ''; ?>" class="btn btn-outline-secondary btn-sm" title="Clear Search"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Requests Table -->
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <span class="card-header-icon bg-primary text-white"><i class="bi bi-file-earmark-text"></i></span>
                        <div>
                            <h6 class="mb-0 fw-bold">Rental Applications Ledger</h6>
                            <small class="text-muted"><?= count($requests); ?> records retrieved</small>
                        </div>
                    </div>
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
                                    <th>Assigned Unit</th>
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
                                        <td colspan="9">
                                            <div class="empty-state">
                                                <div class="empty-state-icon">
                                                    <i class="bi bi-inbox"></i>
                                                </div>
                                                <h6 class="empty-state-title">No Rental Applications Found</h6>
                                                <p class="empty-state-text">There are currently no tenant rental applications matching your selected criteria.</p>
                                            </div>
                                        </td>
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
                                            <td>
                                                <?php if (!empty($r['assigned_apartment'])): ?>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-2 py-1">
                                                        <i class="bi bi-door-closed me-1"></i><?= e($r['assigned_apartment']); ?>
                                                    </span>
                                                <?php elseif (!empty($r['apartment_id'])): ?>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-2 py-1">
                                                        <i class="bi bi-door-closed me-1"></i>Unit #<?= (int)$r['apartment_id']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border px-2 py-1">
                                                        <i class="bi bi-clock me-1"></i>Unassigned
                                                    </span>
                                                <?php endif; ?>
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
                                                <div class="btn-action-group justify-content-end">
                                                    <?php if ($r['status'] !== 'approved'): ?>
                                                        <button type="button" class="btn-action btn-action-approve" onclick="openActionModal('approve', <?= (int)$r['id']; ?>, '<?= e(addslashes($r['user_name'])); ?>', '<?= e(addslashes($r['house_name'])); ?>', <?= (int)$r['house_id']; ?>, <?= (int)($r['apartment_id'] ?? 0); ?>, '<?= e(addslashes($r['assigned_apartment'] ?? '')); ?>')" title="Approve Application & Assign Unit">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($r['status'] !== 'rejected'): ?>
                                                        <button type="button" class="btn-action btn-action-reject" onclick="openActionModal('reject', <?= (int)$r['id']; ?>, '<?= e(addslashes($r['user_name'])); ?>', '<?= e(addslashes($r['house_name'])); ?>', <?= (int)$r['house_id']; ?>, <?= (int)($r['apartment_id'] ?? 0); ?>, '<?= e(addslashes($r['assigned_apartment'] ?? '')); ?>')" title="Reject Application">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <form method="POST" action="<?= BASE_URL; ?>admin/rental_requests.php" class="d-inline" data-confirm="Ma hubtaa inaad tirtirto codsigan kireysiga ah? Tallaabadan dib looma noqon karo.">
                                                        <?= csrf_field(); ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="request_id" value="<?= (int)$r['id']; ?>">
                                                        <button type="submit" class="btn-action btn-action-delete" title="Delete Request">
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

                <!-- Apartment Unit Assignment Selector -->
                <div id="apartment_assign_group" class="mb-3">
                    <label class="form-label fw-semibold small">Assign Apartment Unit (Dooro Qolka la siinayo) <span class="text-danger">*</span></label>
                    <select name="apartment_id" id="modal_apartment_id" class="form-select">
                        <option value="">-- Dooro Apartment Unit (e.g. Apartment 13) --</option>
                    </select>
                    <small class="text-muted d-block mt-1" id="modal_apt_hint">
                        Dooro qolka loo xilsaarayo qofkan (tusaale <strong>Apartment 13</strong>).
                    </small>
                </div>

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
const houseApartmentsData = <?= json_encode($houseApartmentsData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

function openActionModal(action, id, userName, houseName, houseId, currentAptId, assignedAptName) {
    document.getElementById('modal_action').value = action;
    document.getElementById('modal_request_id').value = id;
    const title = document.getElementById('modal_title');
    const desc = document.getElementById('modal_desc');
    const btn = document.getElementById('modal_submit_btn');
    const aptGroup = document.getElementById('apartment_assign_group');
    const aptSelect = document.getElementById('modal_apartment_id');

    if (action === 'approve') {
        title.innerText = 'Approve Application & Assign Apartment';
        desc.innerHTML = `You are approving tenant <strong>${userName}</strong> for <strong>${houseName}</strong>. Dooro apartment number-ka aad siinayso (tusaale <strong>Apartment 13</strong>):`;
        btn.innerText = 'Approve & Assign Apartment';
        btn.className = 'btn btn-success btn-sm';
        aptGroup.style.display = 'block';
        aptSelect.required = true;

        aptSelect.innerHTML = '<option value="">-- Dooro Apartment Unit (e.g. Apartment 13) --</option>';
        const apts = houseApartmentsData[houseId] || [];
        let hasOptions = false;
        apts.forEach(apt => {
            if (apt.status === 'vacant' || apt.id == currentAptId) {
                hasOptions = true;
                const opt = document.createElement('option');
                opt.value = apt.id;
                const isCur = (apt.id == currentAptId);
                opt.textContent = `${apt.apartment_number} (${apt.floor || 'Ground'} - $${parseFloat(apt.rent_price).toFixed(2)})${isCur ? ' [Current]' : ''}`;
                if (isCur) {
                    opt.selected = true;
                }
                aptSelect.appendChild(opt);
            }
        });

        if (!hasOptions) {
            const opt = document.createElement('option');
            opt.value = "";
            opt.textContent = "Ma jiro qol bannaan gurigan!";
            aptSelect.appendChild(opt);
        }
    } else {
        title.innerText = 'Reject Rental Application';
        desc.innerHTML = `You are rejecting the rental request from <strong>${userName}</strong> for <strong>${houseName}</strong>. Haddii qol loo xilsaaray, waa la bannayn doonaa.`;
        btn.innerText = 'Reject Application';
        btn.className = 'btn btn-danger btn-sm';
        aptGroup.style.display = 'none';
        aptSelect.required = false;
    }
    new bootstrap.Modal(document.getElementById('actionModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
