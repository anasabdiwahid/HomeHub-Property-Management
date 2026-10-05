<?php
/**
 * Admin Houses Management
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$currency = get_setting($pdo, 'currency', '$');

// Handle status toggle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid security token.');
        header('Location: ' . BASE_URL . 'admin/houses.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'toggle_status') {
        $houseId = (int)($_POST['house_id'] ?? 0);
        if ($houseId > 0) {
            $stmt = $pdo->prepare("UPDATE houses SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
            $stmt->execute([$houseId]);
            set_flash('success', 'Property status updated successfully.');
        }
    }

    header('Location: ' . BASE_URL . 'admin/houses.php');
    exit;
}

// Filter parameters
$search = trim($_GET['search'] ?? '');
$filterCategory = !empty($_GET['category']) ? (int)$_GET['category'] : 0;
$filterCity = trim($_GET['city'] ?? '');
$filterManager = !empty($_GET['manager']) ? (int)$_GET['manager'] : 0;
$filterStatus = trim($_GET['status'] ?? '');

// Query
$sql = "SELECT h.*, c.category_name, u.name as manager_name 
        FROM houses h 
        JOIN categories c ON h.category_id = c.id 
        LEFT JOIN users u ON h.manager_id = u.id 
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (h.house_name LIKE ? OR h.house_code LIKE ? OR h.city LIKE ? OR h.address LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}
if ($filterCategory > 0) {
    $sql .= " AND h.category_id = ?";
    $params[] = $filterCategory;
}
if (!empty($filterCity)) {
    $sql .= " AND h.city = ?";
    $params[] = $filterCity;
}
if ($filterManager > 0) {
    $sql .= " AND h.manager_id = ?";
    $params[] = $filterManager;
}
if (!empty($filterStatus)) {
    $sql .= " AND h.status = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY h.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$houses = $stmt->fetchAll();

// Metrics
$totalHousesCount    = (int)$pdo->query("SELECT COUNT(*) FROM houses")->fetchColumn();
$activeHousesCount   = (int)$pdo->query("SELECT COUNT(*) FROM houses WHERE status = 'active'")->fetchColumn();
$inactiveHousesCount = (int)$pdo->query("SELECT COUNT(*) FROM houses WHERE status = 'inactive'")->fetchColumn();

// Dropdowns data
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();
$districts = mogadishu_districts();
$managers = $pdo->query("SELECT id, name FROM users WHERE role = 'manager' AND status = 'active' ORDER BY name ASC")->fetchAll();

$pageTitle = 'Houses Management';
$pageHeading = 'Houses Management';
$pageSubtitle = 'Manage property portfolios across Mogadishu districts, units, and manager assignments';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <main class="content-wrapper">
            <?= display_flash(); ?>

            <!-- Property Stats Row -->
            <div class="row g-3 mb-4">
                <div class="col-sm-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary-subtle text-primary">
                            <i class="bi bi-houses-fill"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= $totalHousesCount; ?></div>
                            <div class="stat-label">Total Properties</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-success-subtle text-success">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= $activeHousesCount; ?></div>
                            <div class="stat-label">Active Properties</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-danger-subtle text-danger">
                            <i class="bi bi-x-circle-fill"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= $inactiveHousesCount; ?></div>
                            <div class="stat-label">Deactive Properties</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Bar -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="<?= BASE_URL; ?>admin/houses.php" class="row g-2 align-items-center" id="houseFilterForm">
                        <div class="col-md-3">
                            <div class="input-group">
                                <span class="input-group-text bg-transparent text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" id="houseLiveSearch" class="form-control" placeholder="Search name, code, address..." value="<?= e($search); ?>" autocomplete="off" autofocus>
                                <button class="btn btn-outline-secondary d-none" type="button" id="clearHouseSearch" title="Clear search">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-sm-6 col-md-2">
                            <select name="category" id="filterCategory" class="form-select">
                                <option value="0">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['id']; ?>" <?= $filterCategory === (int)$cat['id'] ? 'selected' : ''; ?>>
                                        <?= e($cat['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-sm-6 col-md-2">
                            <select name="city" id="filterCity" class="form-select">
                                <option value="">All Districts</option>
                                <?php foreach ($districts as $d): ?>
                                    <option value="<?= e($d); ?>" <?= $filterCity === $d ? 'selected' : ''; ?>>
                                        <?= e($d); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-sm-6 col-md-2">
                            <select name="manager" id="filterManager" class="form-select">
                                <option value="0">All Managers</option>
                                <?php foreach ($managers as $mgr): ?>
                                    <option value="<?= (int)$mgr['id']; ?>" <?= $filterManager === (int)$mgr['id'] ? 'selected' : ''; ?>>
                                        <?= e($mgr['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-sm-6 col-md-1">
                            <select name="status" id="filterStatus" class="form-select px-2" title="Filter by Status">
                                <option value="">Status</option>
                                <option value="active" <?= ($filterStatus === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?= ($filterStatus === 'inactive') ? 'selected' : ''; ?>>Deactive</option>
                            </select>
                        </div>

                        <div class="col-sm-6 col-md-2 d-flex gap-2 justify-content-md-end">
                            <button type="submit" class="btn btn-outline-primary btn-sm px-3">
                                <i class="bi bi-funnel me-1"></i>Filter
                            </button>
                            <?php if (!empty($search) || $filterCategory > 0 || !empty($filterCity) || $filterManager > 0 || !empty($filterStatus)): ?>
                                <a href="<?= BASE_URL; ?>admin/houses.php" class="btn btn-outline-secondary btn-sm" title="Clear Filters">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                            <?php endif; ?>
                            <a href="<?= BASE_URL; ?>admin/house_add.php" class="btn btn-primary btn-sm text-nowrap">
                                <i class="bi bi-plus-lg me-1"></i>Add
                            </a>
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
                            <h6 class="mb-0 fw-bold">Properties Directory</h6>
                            <small class="text-muted" id="propertyCountSummary"><?= count($houses); ?> properties registered</small>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('housesTable', 'houses_list.csv')">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i>Export CSV
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="housesTable">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>House Name & Code</th>
                                    <th>Category</th>
                                    <th>Location</th>
                                    <th>Assigned Manager</th>
                                    <th>Monthly Rent</th>
                                    <th>Apartments (Occ / Vac / Tot)</th>
                                    <th>Status</th>
                                    <th class="text-end no-export">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="housesTableBody">
                                <?php if (empty($houses)): ?>
                                    <tr id="emptyHousesRow">
                                        <td colspan="9">
                                            <div class="empty-state">
                                                <div class="empty-state-icon">
                                                    <i class="bi bi-houses"></i>
                                                </div>
                                                <h6 class="empty-state-title">No Properties Listed Yet</h6>
                                                <p class="empty-state-text">Your property portfolio is currently empty. Click "Add" above to list your first Mogadishu property.</p>
                                                <a href="<?= BASE_URL; ?>admin/house_add.php" class="btn btn-primary btn-sm mt-3">
                                                    <i class="bi bi-plus-lg me-1"></i>Add New House
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($houses as $h): ?>
                                        <?php 
                                             $imgUrl = !empty($h['image']) && file_exists(UPLOAD_DIR . 'houses/' . $h['image'])
                                                ? UPLOAD_URL . 'houses/' . e($h['image'])
                                                : 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=120&q=80';
                                        ?>
                                        <tr class="house-row"
                                            data-name="<?= e(strtolower($h['house_name'])); ?>"
                                            data-code="<?= e(strtolower($h['house_code'])); ?>"
                                            data-city="<?= e(strtolower($h['city'])); ?>"
                                            data-address="<?= e(strtolower($h['address'])); ?>"
                                            data-category="<?= (int)$h['category_id']; ?>"
                                            data-category-name="<?= e(strtolower($h['category_name'])); ?>"
                                            data-manager="<?= (int)($h['manager_id'] ?? 0); ?>"
                                            data-manager-name="<?= e(strtolower($h['manager_name'] ?? '')); ?>"
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
                                                <small class="text-muted text-truncate d-inline-block" style="max-width: 180px;"><?= e($h['address']); ?></small>
                                            </td>
                                            <td>
                                                <?php if (!empty($h['manager_name'])): ?>
                                                    <span class="badge bg-info-subtle text-info border border-info-subtle">
                                                        <i class="bi bi-person-badge me-1"></i><?= e($h['manager_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold text-primary">
                                                <?= format_currency($h['rent_price'], $currency); ?>
                                            </td>
                                            <td>
                                                <a href="<?= BASE_URL; ?>admin/house_apartments.php?id=<?= (int)$h['id']; ?>" class="text-decoration-none" title="Manage individual apartments">
                                                    <div class="d-flex align-items-center gap-1 small mb-1">
                                                        <span class="badge bg-success-subtle text-success"><?= (int)$h['occupied_apartments']; ?> Occ</span>
                                                        <span class="badge bg-warning-subtle text-warning"><?= (int)$h['vacant_apartments']; ?> Vac</span>
                                                        <span class="badge bg-secondary-subtle"><?= (int)$h['total_apartments']; ?> Tot</span>
                                                    </div>
                                                    <?php 
                                                        $tot = (int)$h['total_apartments'];
                                                        $occ = (int)$h['occupied_apartments'];
                                                        $pct = $tot > 0 ? min(100, round(($occ / $tot) * 100)) : 0;
                                                    ?>
                                                    <div class="progress" style="height: 5px;">
                                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $pct; ?>%" aria-valuenow="<?= $pct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                </a>
                                            </td>
                                            <td>
                                                <form method="POST" action="<?= BASE_URL; ?>admin/houses.php" class="d-inline">
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="house_id" value="<?= (int)$h['id']; ?>">
                                                    <button type="submit" class="btn btn-sm p-0 border-0" title="Click to toggle Active / Deactive">
                                                        <?php if (($h['status'] ?? 'active') === 'active'): ?>
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                                <i class="bi bi-check-circle me-1"></i>Active
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                                <i class="bi bi-x-circle me-1"></i>Deactive
                                                            </span>
                                                        <?php endif; ?>
                                                    </button>
                                                </form>
                                            </td>
                                            <td class="text-end no-export">
                                                <div class="btn-action-group justify-content-end">
                                                    <a href="<?= BASE_URL; ?>admin/house_apartments.php?id=<?= (int)$h['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-2 d-inline-flex align-items-center" title="Manage Apartments">
                                                        <i class="bi bi-door-open me-1"></i>Units
                                                    </a>
                                                    <a href="<?= BASE_URL; ?>admin/house_edit.php?id=<?= (int)$h['id']; ?>" class="btn-action btn-action-edit" title="Edit Property">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>
                                                    <form method="POST" action="<?= BASE_URL; ?>admin/house_delete.php" class="d-inline" data-confirm="Are you sure you want to delete property '<?= e(addslashes($h['house_name'])); ?>'? All associated apartment units and rental history will be permanently deleted.">
                                                        <?= csrf_field(); ?>
                                                        <input type="hidden" name="id" value="<?= (int)$h['id']; ?>">
                                                        <button type="submit" class="btn-action btn-action-delete" title="Delete Property">
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
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const catSelect = document.getElementById('filterCategory');
    const citySelect = document.getElementById('filterCity');
    const mgrSelect = document.getElementById('filterManager');
    const statusSelect = document.getElementById('filterStatus');

    if (window.initTableLiveFilter) {
        const liveFilter = window.initTableLiveFilter({
            input: '#houseLiveSearch',
            clearBtn: '#clearHouseSearch',
            tableBody: '#housesTableBody',
            rowSelector: 'tr.house-row',
            countDisplay: '#propertyCountSummary',
            itemLabel: 'properties',
            columnsCount: 9,
            hasActiveDropdowns: () => {
                return (catSelect && catSelect.value !== '0') ||
                       (citySelect && citySelect.value !== '') ||
                       (mgrSelect && mgrSelect.value !== '0') ||
                       (statusSelect && statusSelect.value !== '');
            },
            customFilter: (row) => {
                if (catSelect && catSelect.value !== '0' && row.dataset.category !== catSelect.value) return false;
                if (citySelect && citySelect.value && row.dataset.city.toLowerCase() !== citySelect.value.toLowerCase()) return false;
                if (mgrSelect && mgrSelect.value !== '0' && row.dataset.manager !== mgrSelect.value) return false;
                if (statusSelect && statusSelect.value && row.dataset.status.toLowerCase() !== statusSelect.value.toLowerCase()) return false;
                return true;
            }
        });

        if (liveFilter) {
            [catSelect, citySelect, mgrSelect, statusSelect].forEach(sel => {
                if (sel) sel.addEventListener('change', liveFilter.runFilter);
            });
        }
    }
});
</script>

