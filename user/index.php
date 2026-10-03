<?php
/**
 * User / Tenant Dashboard & Available Houses Browse
 * HomeHub Property Management System
 * Top Navigation Only (No Sidebar as specified)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('user');

$currentUser = current_user();
$userId = (int)$currentUser['id'];
$currency = get_setting($pdo, 'currency', '$');

// User Dashboard Metrics
$myRequestsCount = (int)$pdo->query("SELECT COUNT(*) FROM rental_requests WHERE user_id = {$userId}")->fetchColumn();
$myApprovedCount = (int)$pdo->query("SELECT COUNT(*) FROM rental_requests WHERE user_id = {$userId} AND status = 'approved'")->fetchColumn();
$totalAvailableHouses = (int)$pdo->query("SELECT COUNT(*) FROM houses WHERE vacant_apartments > 0")->fetchColumn();

// Filter parameters
$search = trim($_GET['search'] ?? '');
$filterCity = trim($_GET['city'] ?? '');
$filterCategory = !empty($_GET['category']) ? (int)$_GET['category'] : 0;
$filterMaxPrice = !empty($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$filterVacantOnly = isset($_GET['vacant_only']) ? (int)$_GET['vacant_only'] : 1;

$query = "SELECT h.*, c.category_name, u.name as manager_name, u.phone as manager_phone
          FROM houses h
          JOIN categories c ON h.category_id = c.id
          LEFT JOIN users u ON h.manager_id = u.id
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (h.house_name LIKE ? OR h.house_code LIKE ? OR h.address LIKE ? OR h.description LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}
if (!empty($filterCity)) {
    $query .= " AND h.city = ?";
    $params[] = $filterCity;
}
if ($filterCategory > 0) {
    $query .= " AND h.category_id = ?";
    $params[] = $filterCategory;
}
if ($filterMaxPrice > 0) {
    $query .= " AND h.rent_price <= ?";
    $params[] = $filterMaxPrice;
}
if ($filterVacantOnly === 1) {
    $query .= " AND h.vacant_apartments > 0";
}

$query .= " ORDER BY h.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$houses = $stmt->fetchAll();

// Categories and Mogadishu Districts
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();
$districts = mogadishu_districts();

$pageTitle = 'Available Houses - HomeHub';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- User Top Navigation (No Sidebar) -->
<?php require_once __DIR__ . '/../includes/user_navbar.php'; ?>

<main class="content-wrapper container py-4">
    <?= display_flash(); ?>

    <!-- Welcome & Quick Stats Banner -->
    <div class="card border-0 shadow-sm p-4 mb-4" style="background: linear-gradient(135deg, #102a45 0%, #1c3d61 100%); color: #ffffff; border-radius: 16px; border-top: 4px solid var(--hh-gold) !important;">
        <div class="row align-items-center g-3">
            <div class="col-lg-7">
                <span class="badge bg-gold px-3 py-1 rounded-pill mb-2 fw-bold text-dark">Tenant Portal &bull; Mogadishu</span>
                <h3 class="fw-bold mb-1">Welcome, <?= e($currentUser['name']); ?>!</h3>
                <p class="mb-0 text-white-50">Explore available rental properties across Mogadishu districts, submit lease applications, and track your request statuses.</p>
            </div>
            <div class="col-lg-5">
                <div class="row g-2 text-center">
                    <div class="col-4">
                        <div class="bg-white bg-opacity-10 p-2 rounded-3 border border-white border-opacity-10">
                            <h4 class="fw-bold mb-0 text-gold"><?= $totalAvailableHouses; ?></h4>
                            <small class="text-white-50" style="font-size: 0.72rem;">Vacant Houses</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <a href="<?= BASE_URL; ?>user/my_requests.php" class="text-white text-decoration-none d-block bg-white bg-opacity-10 p-2 rounded-3 border border-white border-opacity-10">
                            <h4 class="fw-bold mb-0"><?= $myRequestsCount; ?></h4>
                            <small class="text-white-50" style="font-size: 0.72rem;">My Requests</small>
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="<?= BASE_URL; ?>user/approved_rentals.php" class="text-white text-decoration-none d-block bg-white bg-opacity-10 p-2 rounded-3 border border-white border-opacity-10">
                            <h4 class="fw-bold mb-0 text-success"><?= $myApprovedCount; ?></h4>
                            <small class="text-white-50" style="font-size: 0.72rem;">Approved</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Card -->
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body p-3 p-md-4">
            <form method="GET" action="<?= BASE_URL; ?>user/index.php" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted">Property Keywords</label>
                    <div class="input-group">
                        <span class="input-group-text bg-body-tertiary"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Name, code, street..." value="<?= e($search); ?>">
                    </div>
                </div>

                <div class="col-sm-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted">District</label>
                    <select name="city" class="form-select">
                        <option value="">All Districts</option>
                        <?php foreach ($districts as $d): ?>
                            <option value="<?= e($d); ?>" <?= $filterCity === $d ? 'selected' : ''; ?>><?= e($d); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-sm-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted">Category</label>
                    <select name="category" class="form-select">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id']; ?>" <?= $filterCategory === (int)$cat['id'] ? 'selected' : ''; ?>><?= e($cat['category_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-sm-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted">Max Monthly Rent</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="50" name="max_price" class="form-control" placeholder="Any" value="<?= $filterMaxPrice > 0 ? e((string)$filterMaxPrice) : ''; ?>">
                    </div>
                </div>

                <div class="col-sm-6 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <?php if (!empty($search) || !empty($filterCity) || $filterCategory > 0 || $filterMaxPrice > 0): ?>
                        <a href="<?= BASE_URL; ?>user/index.php" class="btn btn-outline-secondary" title="Clear Filters">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Available Houses Grid -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0">Available Properties (<?= count($houses); ?>)</h4>
        <span class="text-muted small">Updated in real-time</span>
    </div>

    <?php if (empty($houses)): ?>
        <div class="card text-center py-5 border-dashed shadow-sm">
            <div class="card-body">
                <i class="bi bi-house-slash text-muted" style="font-size: 3.5rem;"></i>
                <h5 class="fw-bold mt-3">No matching properties found</h5>
                <p class="text-muted small">Try broadening your search keywords or removing the rent price cap.</p>
                <a href="<?= BASE_URL; ?>user/index.php" class="btn btn-outline-primary btn-sm">Reset Filters</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($houses as $h): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 card-hover overflow-hidden border">
                        <div class="property-card-img-wrapper">
                            <?php 
                                $imgUrl = !empty($h['image']) && file_exists(UPLOAD_DIR . 'houses/' . $h['image'])
                                    ? UPLOAD_URL . 'houses/' . e($h['image'])
                                    : 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=600&q=80';
                            ?>
                            <img src="<?= $imgUrl; ?>" class="property-card-img" alt="<?= e($h['house_name']); ?>">
                            <span class="property-badge-city"><i class="bi bi-geo-alt me-1"></i><?= e($h['city']); ?></span>
                            <span class="property-badge-category"><?= e($h['category_name']); ?></span>
                        </div>

                        <div class="card-body d-flex flex-column p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted font-monospace"><?= e($h['house_code']); ?></small>
                                <span class="badge <?= (int)$h['vacant_apartments'] > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle'; ?>">
                                    <?= (int)$h['vacant_apartments'] > 0 ? (int)$h['vacant_apartments'] . ' Units Vacant' : 'Occupied'; ?>
                                </span>
                            </div>

                            <h5 class="card-title fw-bold text-truncate mb-1"><?= e($h['house_name']); ?></h5>
                            <small class="text-muted mb-2"><i class="bi bi-geo me-1"></i><?= e($h['address']); ?></small>

                            <p class="text-muted small mb-3 flex-grow-1" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?= e($h['description'] ?? 'No description provided.'); ?>
                            </p>

                            <div class="border-top pt-3 d-flex justify-content-between align-items-center mt-auto">
                                <div>
                                    <div class="property-price"><?= format_currency($h['rent_price'], $currency); ?></div>
                                    <small class="text-muted">per month</small>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="<?= BASE_URL; ?>user/house_details.php?id=<?= (int)$h['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        Details
                                    </a>
                                    <?php if ((int)$h['vacant_apartments'] > 0): ?>
                                        <button type="button" class="btn btn-primary btn-sm" onclick="openRentModal(<?= (int)$h['id']; ?>, '<?= e(addslashes($h['house_name'])); ?>', '<?= format_currency($h['rent_price'], $currency); ?>')">
                                            Rent <i class="bi bi-arrow-right ms-1"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-secondary btn-sm" disabled>Full</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Quick Rent Request Modal -->
<div class="modal fade" id="rentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= BASE_URL; ?>user/rent_request.php" class="modal-content">
            <?= csrf_field(); ?>
            <input type="hidden" name="house_id" id="modal_house_id">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-house-door-fill text-primary me-2"></i>Submit Rental Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="p-3 bg-body-tertiary rounded-3 mb-3 border">
                    <div class="fw-bold fs-6 text-main" id="modal_house_title"></div>
                    <div class="text-primary fw-bold" id="modal_house_price"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Preferred Move-In Date <span class="text-danger">*</span></label>
                    <input type="date" name="move_in_date" class="form-control" value="<?= date('Y-m-d', strtotime('+3 days')); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Notes or Unit Preference</label>
                    <textarea name="request_note" rows="3" class="form-control" placeholder="e.g. 2nd floor preferred, moving with small family, inquiry about parking space..."></textarea>
                </div>

                <div class="small text-muted">
                    <i class="bi bi-shield-check text-success me-1"></i>Your request will be sent to the assigned property supervisor for approval.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Confirm & Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRentModal(houseId, houseTitle, rentPrice) {
    document.getElementById('modal_house_id').value = houseId;
    document.getElementById('modal_house_title').innerText = houseTitle;
    document.getElementById('modal_house_price').innerText = rentPrice + ' / month';
    new bootstrap.Modal(document.getElementById('rentModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
