<?php
/**
 * Manager House Apartments Management
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('manager');

$managerId = (int)current_user_id();
$currency = get_setting($pdo, 'currency', '$');
$houseId = (int)($_GET['id'] ?? 0);

if ($houseId <= 0) {
    set_flash('danger', 'Invalid property ID.');
    header('Location: ' . BASE_URL . 'manager/houses.php');
    exit;
}

// Fetch house details and verify this manager is assigned to this house
$houseStmt = $pdo->prepare("
    SELECT h.*, c.category_name
    FROM houses h
    LEFT JOIN categories c ON h.category_id = c.id
    WHERE h.id = ? AND h.manager_id = ? LIMIT 1
");
$houseStmt->execute([$houseId, $managerId]);
$house = $houseStmt->fetch();

if (!$house) {
    set_flash('danger', 'Property listing not found or not assigned to your supervisor account.');
    header('Location: ' . BASE_URL . 'manager/houses.php');
    exit;
}

// Handle POST actions: Edit unit, Assign unit, Vacate unit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid security token.');
        header('Location: ' . BASE_URL . 'manager/house_apartments.php?id=' . $houseId);
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. Edit Apartment details (rent price, floor, notes)
    if ($action === 'edit_apartment') {
        $aptId = (int)($_POST['apartment_id'] ?? 0);
        $aptNumber = trim($_POST['apartment_number'] ?? '');
        $rentPrice = (float)($_POST['rent_price'] ?? 0);
        $floor = trim($_POST['floor'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['vacant', 'occupied', 'maintenance']) ? $_POST['status'] : 'vacant';
        $notes = trim($_POST['notes'] ?? '');

        if ($aptId > 0 && !empty($aptNumber) && $rentPrice > 0) {
            $upd = $pdo->prepare("
                UPDATE apartments 
                SET apartment_number = ?, rent_price = ?, floor = ?, status = ?, notes = ?
                WHERE id = ? AND house_id = ?
            ");
            $upd->execute([$aptNumber, $rentPrice, $floor, $status, $notes, $aptId, $houseId]);
            sync_house_occupancy_counts($pdo, $houseId);
            set_flash('success', "Apartment '{$aptNumber}' updated successfully!");
        } else {
            set_flash('danger', 'Please provide a valid apartment number and rent price.');
        }
    }

    // 2. Assign Tenant directly to Apartment
    elseif ($action === 'assign_tenant') {
        $aptId = (int)($_POST['apartment_id'] ?? 0);
        $tenantId = (int)($_POST['tenant_id'] ?? 0);

        if ($aptId > 0 && $tenantId > 0) {
            $aptStmt = $pdo->prepare("SELECT apartment_number FROM apartments WHERE id = ? AND house_id = ? LIMIT 1");
            $aptStmt->execute([$aptId, $houseId]);
            $apt = $aptStmt->fetch();

            if ($apt) {
                $updApt = $pdo->prepare("
                    UPDATE apartments 
                    SET status = 'occupied', current_tenant_id = ? 
                    WHERE id = ? AND house_id = ?
                ");
                $updApt->execute([$tenantId, $aptId, $houseId]);

                sync_house_occupancy_counts($pdo, $houseId);
                set_flash('success', "Successfully assigned tenant to {$apt['apartment_number']}!");
            }
        } else {
            set_flash('danger', 'Please select both an apartment and a tenant.');
        }
    }

    // 3. Vacate / Release Apartment
    elseif ($action === 'vacate_apartment') {
        $aptId = (int)($_POST['apartment_id'] ?? 0);

        if ($aptId > 0) {
            $aptStmt = $pdo->prepare("SELECT apartment_number FROM apartments WHERE id = ? AND house_id = ? LIMIT 1");
            $aptStmt->execute([$aptId, $houseId]);
            $apt = $aptStmt->fetch();

            if ($apt) {
                $updApt = $pdo->prepare("
                    UPDATE apartments 
                    SET status = 'vacant', current_tenant_id = NULL, rental_request_id = NULL 
                    WHERE id = ? AND house_id = ?
                ");
                $updApt->execute([$aptId, $houseId]);

                sync_house_occupancy_counts($pdo, $houseId);
                set_flash('success', "{$apt['apartment_number']} is now vacant and available for rent!");
            }
        }
    }

    header('Location: ' . BASE_URL . 'manager/house_apartments.php?id=' . $houseId);
    exit;
}

// Fetch all individual apartments for this house
$apartments = ensure_house_apartments($pdo, $houseId);

// Re-read house counts after sync
$houseStmt->execute([$houseId, $managerId]);
$house = $houseStmt->fetch();

// All tenants for assign modal dropdown
$tenantsStmt = $pdo->query("SELECT id, name, email, phone FROM users WHERE role = 'user' ORDER BY name ASC");
$allTenants = $tenantsStmt->fetchAll();

// Calculations for metrics
$totalUnits = count($apartments);
$occupiedUnits = 0;
$vacantUnits = 0;
$activeRevenue = 0.0;
$potentialRevenue = 0.0;

foreach ($apartments as $a) {
    $price = (float)$a['rent_price'];
    $potentialRevenue += $price;
    if ($a['status'] === 'occupied') {
        $occupiedUnits++;
        $activeRevenue += $price;
    } elseif ($a['status'] === 'vacant') {
        $vacantUnits++;
    }
}

$occupancyRate = $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100) : 0;

$filterStatus = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$filteredApartments = array_filter($apartments, function ($a) use ($filterStatus, $search) {
    if (!empty($filterStatus) && $a['status'] !== $filterStatus) {
        return false;
    }
    if (!empty($search)) {
        $term = strtolower($search);
        $numMatch = str_contains(strtolower($a['apartment_number']), $term);
        $floorMatch = str_contains(strtolower($a['floor'] ?? ''), $term);
        $tenantMatch = !empty($a['tenant_name']) && str_contains(strtolower($a['tenant_name']), $term);
        $phoneMatch = !empty($a['tenant_phone']) && str_contains(strtolower($a['tenant_phone']), $term);
        if (!$numMatch && !$floorMatch && !$tenantMatch && !$phoneMatch) {
            return false;
        }
    }
    return true;
});

$pageTitle = 'Apartments - ' . $house['house_name'];
$pageHeading = 'Apartments Management';
$pageSubtitle = 'Individual units, rent pricing, occupancy, and tenant assignment';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once __DIR__ . '/../includes/manager_sidebar.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <main class="content-wrapper">
            <?= display_flash(); ?>

            <!-- Navigation Breadcrumb & Actions -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= BASE_URL; ?>manager/houses.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Back to Houses
                    </a>
                    <span class="text-muted">/</span>
                    <h5 class="fw-bold mb-0 text-main">
                        <?= e($house['house_name']); ?> 
                        <span class="badge bg-secondary-subtle text-secondary font-monospace ms-1"><?= e($house['house_code']); ?></span>
                    </h5>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignTenantModal">
                        <i class="bi bi-person-plus me-1"></i>Assign Tenant
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('apartmentsTable', 'apartments_<?= e($house['house_code']); ?>.csv')">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </button>
                </div>
            </div>

            <!-- Property Overview & Key Metrics -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="rounded-3 bg-primary-subtle text-primary p-3 fs-3">
                                <i class="bi bi-buildings"></i>
                            </div>
                            <div>
                                <small class="text-muted fw-semibold text-uppercase">Total Capacity</small>
                                <h3 class="fw-bolder mb-0 text-main"><?= $totalUnits; ?> <small class="fs-6 fw-normal text-muted">Units</small></h3>
                                <small class="text-muted">Base rate: <?= format_currency($house['rent_price'], $currency); ?>/mo</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="rounded-3 bg-danger-subtle text-danger p-3 fs-3">
                                <i class="bi bi-person-fill-check"></i>
                            </div>
                            <div>
                                <small class="text-muted fw-semibold text-uppercase">Occupied Units</small>
                                <h3 class="fw-bolder mb-0 text-danger"><?= $occupiedUnits; ?> <small class="fs-6 fw-normal text-muted">(<?= $occupancyRate; ?>%)</small></h3>
                                <div class="progress mt-1" style="height: 6px; width: 120px;">
                                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?= $occupancyRate; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="rounded-3 bg-success-subtle text-success p-3 fs-3">
                                <i class="bi bi-door-open-fill"></i>
                            </div>
                            <div>
                                <small class="text-muted fw-semibold text-uppercase">Vacant Available</small>
                                <h3 class="fw-bolder mb-0 text-success"><?= $vacantUnits; ?> <small class="fs-6 fw-normal text-muted">Units</small></h3>
                                <small class="text-success fw-semibold">Ready for leasing</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="rounded-3 bg-info-subtle text-info p-3 fs-3">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                            <div>
                                <small class="text-muted fw-semibold text-uppercase">Active Monthly Rent</small>
                                <h3 class="fw-bolder mb-0 text-primary"><?= format_currency($activeRevenue, $currency); ?></h3>
                                <small class="text-muted">Potential: <?= format_currency($potentialRevenue, $currency); ?>/mo</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters & Search Toolbar -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="btn-group" role="group">
                            <a href="<?= BASE_URL; ?>manager/house_apartments.php?id=<?= $houseId; ?>" class="btn btn-sm <?= empty($filterStatus) ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                                All Units (<?= $totalUnits; ?>)
                            </a>
                            <a href="<?= BASE_URL; ?>manager/house_apartments.php?id=<?= $houseId; ?>&status=vacant" class="btn btn-sm <?= $filterStatus === 'vacant' ? 'btn-success' : 'btn-outline-secondary'; ?>">
                                <i class="bi bi-door-open me-1"></i>Vacant (<?= $vacantUnits; ?>)
                            </a>
                            <a href="<?= BASE_URL; ?>manager/house_apartments.php?id=<?= $houseId; ?>&status=occupied" class="btn btn-sm <?= $filterStatus === 'occupied' ? 'btn-danger' : 'btn-outline-secondary'; ?>">
                                <i class="bi bi-person-fill me-1"></i>Occupied (<?= $occupiedUnits; ?>)
                            </a>
                        </div>

                        <form method="GET" action="<?= BASE_URL; ?>manager/house_apartments.php" class="d-flex gap-2">
                            <input type="hidden" name="id" value="<?= $houseId; ?>">
                            <?php if (!empty($filterStatus)): ?>
                                <input type="hidden" name="status" value="<?= e($filterStatus); ?>">
                            <?php endif; ?>
                            <div class="input-icon-group">
                                <i class="bi bi-search input-icon-prefix"></i>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search unit #, floor, tenant..." value="<?= e($search); ?>" style="width: 250px;">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-arrow-right"></i></button>
                            <?php if (!empty($search)): ?>
                                <a href="<?= BASE_URL; ?>manager/house_apartments.php?id=<?= $houseId; ?><?= !empty($filterStatus) ? '&status=' . e($filterStatus) : ''; ?>" class="btn btn-outline-secondary btn-sm" title="Clear Search"><i class="bi bi-x-lg"></i></a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Apartments Ledger Table -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <span class="card-header-icon bg-primary text-white"><i class="bi bi-grid-3x3-gap"></i></span>
                        <div>
                            <h6 class="mb-0 fw-bold">Apartments Ledger (<?= count($filteredApartments); ?> Units)</h6>
                            <small class="text-muted">Listing individual units, rent per unit, and active leaseholders</small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="apartmentsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 80px;">Unit #</th>
                                    <th>Apartment Name</th>
                                    <th>Floor</th>
                                    <th>Monthly Rent</th>
                                    <th>Status</th>
                                    <th>Current Tenant</th>
                                    <th>Tenant Contact</th>
                                    <th class="text-end no-export">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($filteredApartments)): ?>
                                    <tr>
                                        <td colspan="8">
                                            <div class="empty-state py-5 text-center">
                                                <i class="bi bi-door-closed text-muted fs-1 mb-2 d-block"></i>
                                                <h6 class="empty-state-title">No Apartments Found</h6>
                                                <p class="empty-state-text text-muted">No apartment units match the selected criteria.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($filteredApartments as $apt): ?>
                                        <tr>
                                            <td class="font-monospace text-muted small">#<?= (int)$apt['id']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded-circle <?= $apt['status'] === 'occupied' ? 'bg-danger text-white' : 'bg-success text-white'; ?> d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                                        <i class="bi <?= $apt['status'] === 'occupied' ? 'bi-door-closed-fill' : 'bi-door-open-fill'; ?>"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-main fs-6"><?= e($apt['apartment_number']); ?></div>
                                                        <?php if (!empty($apt['notes'])): ?>
                                                            <small class="text-muted"><i class="bi bi-info-circle me-1"></i><?= e($apt['notes']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border"><?= e($apt['floor'] ?? 'Ground'); ?></span>
                                            </td>
                                            <td class="fw-bold text-primary">
                                                <?= format_currency($apt['rent_price'], $currency); ?>
                                                <small class="text-muted d-block" style="font-size: 0.75rem;">/ bishii</small>
                                            </td>
                                            <td>
                                                <?php if ($apt['status'] === 'occupied'): ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                                        <i class="bi bi-person-fill-check me-1"></i>Occupied (La deggen)
                                                    </span>
                                                <?php elseif ($apt['status'] === 'vacant'): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                        <i class="bi bi-check-circle me-1"></i>Vacant (Bannaan)
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                                        <i class="bi bi-tools me-1"></i>Maintenance
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($apt['tenant_name'])): ?>
                                                    <div class="fw-bold text-main"><?= e($apt['tenant_name']); ?></div>
                                                    <?php if (!empty($apt['move_in_date'])): ?>
                                                        <small class="text-muted d-block">Moved in: <?= format_date($apt['move_in_date']); ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted small fst-italic">No Tenant Assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($apt['tenant_phone']) || !empty($apt['tenant_email'])): ?>
                                                    <?php if (!empty($apt['tenant_phone'])): ?>
                                                        <div class="small"><i class="bi bi-telephone me-1 text-primary"></i><?= e($apt['tenant_phone']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($apt['tenant_email'])): ?>
                                                        <small class="text-muted"><i class="bi bi-envelope me-1"></i><?= e($apt['tenant_email']); ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted small">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end no-export">
                                                <div class="btn-action-group justify-content-end">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openEditModal(<?= htmlspecialchars(json_encode($apt), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit Unit Price & Floor">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>

                                                    <?php if ($apt['status'] === 'vacant'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="openAssignModal(<?= (int)$apt['id']; ?>, '<?= e(addslashes($apt['apartment_number'])); ?>')" title="Assign Tenant to this Unit">
                                                            <i class="bi bi-person-plus"></i> Assign
                                                        </button>
                                                    <?php elseif ($apt['status'] === 'occupied'): ?>
                                                        <form method="POST" action="<?= BASE_URL; ?>manager/house_apartments.php?id=<?= $houseId; ?>" class="d-inline" data-confirm="Are you sure you want to vacate unit '<?= e(addslashes($apt['apartment_number'])); ?>'? The apartment will become vacant immediately.">
                                                            <?= csrf_field(); ?>
                                                            <input type="hidden" name="action" value="vacate_apartment">
                                                            <input type="hidden" name="apartment_id" value="<?= (int)$apt['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Vacate / Release Unit">
                                                                <i class="bi bi-door-open"></i> Vacate
                                                            </button>
                                                        </form>
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

<!-- Edit Apartment Modal -->
<div class="modal fade" id="editApartmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= BASE_URL; ?>manager/house_apartments.php?id=<?= $houseId; ?>" class="modal-content">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="edit_apartment">
            <input type="hidden" name="apartment_id" id="edit_apt_id">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Apartment Unit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Apartment Unit Name / Number <span class="text-danger">*</span></label>
                    <input type="text" name="apartment_number" id="edit_apt_number" class="form-control" placeholder="e.g. Apartment 13" required>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold small">Rent Price (<?= $currency; ?>) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="rent_price" id="edit_rent_price" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold small">Floor / Level</label>
                        <input type="text" name="floor" id="edit_floor" class="form-control" placeholder="e.g. Floor 4">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Unit Status</label>
                    <select name="status" id="edit_status" class="form-select">
                        <option value="vacant">Vacant (Bannaan)</option>
                        <option value="occupied">Occupied (La deggen)</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Notes / Remarks</label>
                    <textarea name="notes" id="edit_notes" rows="2" class="form-control" placeholder="Optional unit features or remarks..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm fw-bold">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Tenant Modal -->
<div class="modal fade" id="assignTenantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= BASE_URL; ?>manager/house_apartments.php?id=<?= $houseId; ?>" class="modal-content">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="assign_tenant">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-check-fill text-success me-2"></i>Assign Tenant to Apartment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Select Apartment Unit <span class="text-danger">*</span></label>
                    <select name="apartment_id" id="assign_apt_select" class="form-select" required>
                        <option value="">-- Choose Vacant Unit --</option>
                        <?php foreach ($apartments as $apt): ?>
                            <?php if ($apt['status'] === 'vacant'): ?>
                                <option value="<?= (int)$apt['id']; ?>">
                                    <?= e($apt['apartment_number']); ?> (<?= e($apt['floor'] ?? 'Ground'); ?>) - <?= format_currency($apt['rent_price'], $currency); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Select Registered Tenant <span class="text-danger">*</span></label>
                    <select name="tenant_id" class="form-select" required>
                        <option value="">-- Choose Tenant --</option>
                        <?php foreach ($allTenants as $t): ?>
                            <option value="<?= (int)$t['id']; ?>">
                                <?= e($t['name']); ?> (<?= e($t['phone'] ?? $t['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success btn-sm fw-bold">Assign Tenant</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(apt) {
    document.getElementById('edit_apt_id').value = apt.id;
    document.getElementById('edit_apt_number').value = apt.apartment_number;
    document.getElementById('edit_rent_price').value = apt.rent_price;
    document.getElementById('edit_floor').value = apt.floor || '';
    document.getElementById('edit_status').value = apt.status;
    document.getElementById('edit_notes').value = apt.notes || '';
    new bootstrap.Modal(document.getElementById('editApartmentModal')).show();
}

function openAssignModal(aptId, aptNumber) {
    const sel = document.getElementById('assign_apt_select');
    if (sel) {
        sel.value = aptId;
    }
    new bootstrap.Modal(document.getElementById('assignTenantModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
