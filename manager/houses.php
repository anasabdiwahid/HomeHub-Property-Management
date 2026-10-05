<?php
/**
 * Manager Assigned Houses Management
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('manager');

$currentUser = current_user();
$managerId = (int)$currentUser['id'];
$currency = get_setting($pdo, 'currency', '$');

$search = trim($_GET['search'] ?? '');

$sql = "SELECT h.*, c.category_name 
        FROM houses h 
        JOIN categories c ON h.category_id = c.id 
        WHERE h.manager_id = ?";
$params = [$managerId];

if (!empty($search)) {
    $sql .= " AND (h.house_name LIKE ? OR h.house_code LIKE ? OR h.city LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term]);
}

$sql .= " ORDER BY h.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$myHouses = $stmt->fetchAll();

$pageTitle = 'My Assigned Houses';
$pageHeading = 'My Assigned Properties';
$pageSubtitle = 'Supervise property conditions, rental rates, and apartment occupancy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once __DIR__ . '/../includes/manager_sidebar.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <main class="content-wrapper">
            <?= display_flash(); ?>

            <!-- Search and Action Bar -->
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <form method="GET" action="<?= BASE_URL; ?>manager/houses.php" class="row g-2 align-items-center" id="managerHouseFilterForm">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-transparent text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" id="managerHouseSearch" class="form-control" placeholder="Search by name, code or district (Hodan, Waaberi...)" value="<?= e($search); ?>" autocomplete="off" autofocus>
                                <button class="btn btn-outline-secondary d-none" type="button" id="clearManagerHouseSearch" title="Clear search">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-outline-primary btn-sm px-3">
                                <i class="bi bi-search me-1"></i>Search
                            </button>
                            <?php if (!empty($search)): ?>
                                <a href="<?= BASE_URL; ?>manager/houses.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <span class="text-muted small" id="managerPropertyCountSummary"><?= count($myHouses); ?> properties assigned</span>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Houses Table -->
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <span class="card-header-icon bg-primary text-white"><i class="bi bi-houses"></i></span>
                        <div>
                            <h6 class="mb-0 fw-bold">Assigned House Portfolio</h6>
                            <small class="text-muted"><?= count($myHouses); ?> properties under your supervision</small>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('managerHousesTable', 'my_assigned_houses.csv')">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i>Export CSV
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="managerHousesTable">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>House Name & Code</th>
                                    <th>Category</th>
                                    <th>Location</th>
                                    <th>Monthly Rent</th>
                                    <th>Occupancy Status</th>
                                    <th>Status</th>
                                    <th class="text-end no-export">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="managerHousesTableBody">
                                <?php if (empty($myHouses)): ?>
                                    <tr id="emptyManagerHousesRow">
                                        <td colspan="8">
                                            <div class="empty-state">
                                                <div class="empty-state-icon">
                                                    <i class="bi bi-house-slash"></i>
                                                </div>
                                                <h6 class="empty-state-title">No Assigned Properties Found</h6>
                                                <p class="empty-state-text">No properties assigned to your account match your search filter.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($myHouses as $h): ?>
                                        <?php 
                                            $imgUrl = !empty($h['image']) && file_exists(UPLOAD_DIR . 'houses/' . $h['image'])
                                                ? UPLOAD_URL . 'houses/' . e($h['image'])
                                                : 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=120&q=80';
                                            $tot = (int)$h['total_apartments'];
                                            $occ = (int)$h['occupied_apartments'];
                                            $pct = $tot > 0 ? min(100, round(($occ / $tot) * 100)) : 0;
                                        ?>
                                        <tr class="manager-house-row"
                                            data-name="<?= e(strtolower($h['house_name'])); ?>"
                                            data-code="<?= e(strtolower($h['house_code'])); ?>"
                                            data-city="<?= e(strtolower($h['city'])); ?>"
                                            data-address="<?= e(strtolower($h['address'])); ?>"
                                            data-category-name="<?= e(strtolower($h['category_name'])); ?>"
                                            data-status="<?= e(strtolower($h['status'] ?? 'active')); ?>">
                                            <td style="width: 70px;">
                                                <img src="<?= $imgUrl; ?>" alt="<?= e($h['house_name']); ?>" class="rounded-3 object-fit-cover shadow-sm" style="width: 60px; height: 45px;">
                                            </td>
                                            <td>
                                                <div class="fw-bold text-main"><?= e($h['house_name']); ?></div>
                                                <span class="badge bg-secondary-subtle text-secondary font-monospace"><?= e($h['house_code']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary"><?= e($h['category_name']); ?></span>
                                            </td>
                                            <td>
                                                <div><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($h['city']); ?></div>
                                                <small class="text-muted"><?= e($h['address']); ?></small>
                                            </td>
                                            <td class="fw-bold text-primary">
                                                <?= format_currency($h['rent_price'], $currency); ?>
                                                <small class="text-muted d-block">per unit/mo</small>
                                            </td>
                                            <td>
                                                <a href="<?= BASE_URL; ?>manager/house_apartments.php?id=<?= (int)$h['id']; ?>" class="text-decoration-none" title="Manage individual apartments">
                                                    <div class="d-flex align-items-center gap-1 small mb-1">
                                                        <span class="badge bg-success-subtle text-success"><?= $occ; ?> Occ</span>
                                                        <span class="badge bg-warning-subtle text-warning"><?= (int)$h['vacant_apartments']; ?> Vac</span>
                                                        <span class="badge bg-secondary-subtle"><?= $tot; ?> Tot</span>
                                                    </div>
                                                    <div class="progress" style="height: 5px;">
                                                        <div class="progress-bar bg-success" style="width: <?= $pct; ?>%"></div>
                                                    </div>
                                                </a>
                                            </td>
                                            <td>
                                                <?php if (($h['status'] ?? 'active') === 'active'): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-x-circle me-1"></i>Deactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end no-export">
                                                <div class="btn-action-group justify-content-end">
                                                    <a href="<?= BASE_URL; ?>manager/house_apartments.php?id=<?= (int)$h['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-2 d-inline-flex align-items-center" title="Manage Apartments">
                                                        <i class="bi bi-door-open me-1"></i>Units
                                                    </a>
                                                    <a href="<?= BASE_URL; ?>manager/house_edit.php?id=<?= (int)$h['id']; ?>" class="btn-action btn-action-edit" title="Update Units & Status">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>
                                                    <a href="<?= BASE_URL; ?>user/house_details.php?id=<?= (int)$h['id']; ?>" target="_blank" class="btn-action btn-action-view" title="Preview Public Listing">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.initTableLiveFilter) {
        window.initTableLiveFilter({
            input: '#managerHouseSearch',
            clearBtn: '#clearManagerHouseSearch',
            tableBody: '#managerHousesTableBody',
            rowSelector: 'tr.manager-house-row',
            countDisplay: '#managerPropertyCountSummary',
            itemLabel: 'properties',
            columnsCount: 8
        });
    }
});
</script>
