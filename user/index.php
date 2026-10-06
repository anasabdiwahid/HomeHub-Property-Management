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
$totalAvailableHouses = (int)$pdo->query("SELECT COUNT(*) FROM houses WHERE status = 'active' AND vacant_apartments > 0")->fetchColumn();

// Filter parameters
$search = trim($_GET['search'] ?? '');
$filterCity = trim($_GET['city'] ?? '');
$filterCategory = !empty($_GET['category']) ? (int)$_GET['category'] : 0;
$filterMaxPrice = !empty($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$filterVacantOnly = isset($_GET['vacant_only']) ? (int)$_GET['vacant_only'] : 0;

$query = "SELECT h.*, c.category_name, u.name as manager_name, u.phone as manager_phone
          FROM houses h
          JOIN categories c ON h.category_id = c.id
          LEFT JOIN users u ON h.manager_id = u.id
          WHERE h.status = 'active'";
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

// Ensure all houses have their apartments populated & sync occupancy
foreach ($houses as $h) {
    ensure_house_apartments($pdo, (int)$h['id']);
}

// Fetch vacant apartments grouped by house for quick rent modal
$vacantApartmentsByHouse = [];
$vacStmt = $pdo->query("SELECT id, house_id, apartment_number, rent_price, floor FROM apartments WHERE status = 'vacant' ORDER BY id ASC");
while ($vRow = $vacStmt->fetch()) {
    $vacantApartmentsByHouse[(int)$vRow['house_id']][] = $vRow;
}

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
                <h3 class="fw-bold mb-1">Welcome, <?= e($currentUser['name'] ?? 'Tenant'); ?>!</h3>
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
        <div class="row g-2 g-md-3 g-lg-4">
            <?php foreach ($houses as $h): ?>
                <div class="col-6 col-md-6 col-lg-4">
                    <div class="card h-100 card-hover overflow-hidden border">
                        <div class="property-card-img-wrapper position-relative">
                            <?php 
                                $imgUrl = !empty($h['image']) && file_exists(UPLOAD_DIR . 'houses/' . $h['image'])
                                    ? UPLOAD_URL . 'houses/' . e($h['image'])
                                    : 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=600&q=80';
                            ?>
                            <img src="<?= $imgUrl; ?>" class="property-card-img" alt="<?= e($h['house_name']); ?>">
                            <span class="property-badge-city"><i class="bi bi-geo-alt me-0.5"></i><?= e($h['city']); ?></span>
                            <span class="property-badge-category"><?= e($h['category_name']); ?></span>
                        </div>

                        <div class="card-body d-flex flex-column p-2 p-sm-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted font-monospace" style="font-size: 0.7rem;"><?= e($h['house_code']); ?></small>
                                <span class="badge <?= (int)$h['vacant_apartments'] > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger text-white'; ?>" style="font-size: 0.68rem; padding: 0.2rem 0.4rem;">
                                    <?= (int)$h['vacant_apartments'] > 0 ? (int)$h['vacant_apartments'] . ' Vac' : 'Full'; ?>
                                </span>
                            </div>

                            <h6 class="card-title fw-bold text-truncate mb-1 fs-sm-5"><?= e($h['house_name']); ?></h6>
                            <small class="text-muted mb-2 text-truncate" style="font-size: 0.72rem;"><i class="bi bi-geo me-0.5"></i><?= e($h['address']); ?></small>

                            <!-- Apartments Occupancy Breakdown -->
                            <div class="bg-body-tertiary p-1.5 p-sm-2 rounded-2 border mb-2" style="font-size: 0.72rem;">
                                <div class="d-flex justify-content-between align-items-center mb-0.5">
                                    <span class="text-muted"><i class="bi bi-buildings text-primary me-0.5"></i>Tot: <strong><?= (int)$h['total_apartments']; ?></strong></span>
                                    <?php if ((int)$h['vacant_apartments'] > 0): ?>
                                        <span class="text-success fw-bold"><i class="bi bi-door-open me-0.5"></i><?= (int)$h['vacant_apartments']; ?> Vac</span>
                                    <?php else: ?>
                                        <span class="text-danger fw-bold"><i class="bi bi-x-circle me-0.5"></i>Full</span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex justify-content-between text-muted" style="font-size: 0.68rem;">
                                    <span><i class="bi bi-person-fill text-danger me-0.5"></i>Occ: <strong class="text-danger"><?= (int)$h['occupied_apartments']; ?></strong></span>
                                    <span><i class="bi bi-check2-circle text-success me-0.5"></i>Avail: <strong class="text-success"><?= (int)$h['vacant_apartments']; ?></strong></span>
                                </div>
                            </div>

                            <p class="text-muted small mb-2 flex-grow-1 d-none d-sm--webkit-box" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 0.74rem;">
                                <?= e($h['description'] ?? 'No description provided.'); ?>
                            </p>

                            <div class="border-top pt-2 pt-sm-3 d-flex flex-column gap-1.5 mt-auto">
                                <div class="d-flex justify-content-between align-items-baseline">
                                    <div>
                                        <span class="property-price fs-6 fs-sm-5"><?= format_currency($h['rent_price'], $currency); ?></span>
                                        <small class="text-muted d-none d-sm-inline">/ mo</small>
                                    </div>
                                    <small class="text-muted text-truncate" style="font-size: 0.7rem; max-width: 60px;">
                                        <i class="bi bi-geo-alt-fill text-danger me-0.5"></i><?= e($h['city']); ?>
                                    </small>
                                </div>
                                <div class="row g-1 g-sm-2">
                                    <div class="col-6">
                                        <a href="<?= BASE_URL; ?>user/house_details.php?id=<?= (int)$h['id']; ?>" class="btn btn-outline-primary btn-sm w-100 py-1 px-1 fw-semibold text-center" style="font-size: 0.74rem;">
                                            Details
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <?php if ((int)$h['vacant_apartments'] > 0): ?>
                                            <button type="button" class="btn btn-primary btn-sm w-100 py-1 px-1 fw-semibold text-center" style="font-size: 0.74rem;" onclick="openRentModal(<?= (int)$h['id']; ?>, '<?= e(addslashes($h['house_name'])); ?>', '<?= format_currency($h['rent_price'], $currency); ?>')">
                                                <span>Rent</span> <i class="bi bi-arrow-right"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-danger btn-sm w-100 py-1 px-1 fw-semibold text-center opacity-75" style="font-size: 0.74rem;" onclick="showOccupiedNotice('<?= e(addslashes($h['house_name'])); ?>', <?= (int)$h['total_apartments']; ?>)">
                                                Full
                                            </button>
                                        <?php endif; ?>
                                    </div>
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
                    <label class="form-label fw-semibold small">Dooro Apartment-ka aad rabto (Select Unit) <span class="text-danger">*</span></label>
                    <select name="apartment_id" id="modal_apartment_select" class="form-select" required>
                        <option value="">-- Dooro Apartment Number (e.g. Apartment 13) --</option>
                    </select>
                    <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">
                        Qolka aad doorato waxaa lagu dari doonaa codsigaaga iyo farriinta WhatsApp.
                    </small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Preferred Move-In Date <span class="text-danger">*</span></label>
                    <input type="date" name="move_in_date" class="form-control" value="<?= date('Y-m-d', strtotime('+3 days')); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Description / Faahfaahinta Codsiga</label>
                    <textarea name="request_note" rows="3" class="form-control" placeholder="Qor faahfaahintaada / e.g. 2nd floor preferred, moving with small family, inquiry about parking space..."></textarea>
                </div>

                <div class="p-2 rounded-3 bg-success-subtle border border-success-subtle d-flex align-items-center gap-2 small text-success-emphasis mb-2">
                    <i class="bi bi-whatsapp fs-5 text-success flex-shrink-0"></i>
                    <div>
                        Marka aad riixdo <strong>Confirm</strong>, waxaa toos laguu geynayaa <strong>WhatsApp (+252 616256534)</strong> fariintana waa diyaar si aad u dirto.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success btn-sm fw-bold shadow-sm">
                    <i class="bi bi-whatsapp me-1"></i> Confirm & Send via WhatsApp
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Fully Occupied Notice Modal -->
<div class="modal fade" id="occupiedNoticeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-x-octagon-fill me-2"></i>Gurigan Wuu Buuxaa!</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="mb-3">
                    <span class="rounded-circle bg-danger-subtle text-danger p-3 d-inline-flex" style="width: 70px; height: 70px; align-items: center; justify-content: center;">
                        <i class="bi bi-houses-fill fs-2"></i>
                    </span>
                </div>
                <h5 class="fw-bold mb-1" id="occupiedHouseName"></h5>
                <div class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 mb-3">
                    Dhammaan waa la wada deggen yahay
                </div>
                <div class="alert alert-danger text-start p-3 rounded-3 small mb-0">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    Gurigan wuxuu ka kooban yahay <strong id="occupiedHouseTotal">15</strong> Apartments, dhammaantoodna waa la wada qabsaday oo la wada deggen yahay. Hadda ma jiro qol bannaan oo la kireysan karo.
                </div>
            </div>
            <div class="modal-footer justify-content-center bg-body-tertiary">
                <button type="button" class="btn btn-secondary px-4 btn-sm" data-bs-dismiss="modal">Waan Fahmay (Close)</button>
            </div>
        </div>
    </div>
</div>

<script>
const vacantApartmentsData = <?= json_encode($vacantApartmentsByHouse, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

function openRentModal(houseId, houseTitle, rentPrice) {
    document.getElementById('modal_house_id').value = houseId;
    document.getElementById('modal_house_title').innerText = houseTitle;
    document.getElementById('modal_house_price').innerText = rentPrice + ' / month';

    const aptSelect = document.getElementById('modal_apartment_select');
    if (aptSelect) {
        aptSelect.innerHTML = '<option value="">-- Dooro Apartment Number (e.g. Apartment 13) --</option>';
        const apts = vacantApartmentsData[houseId] || [];
        apts.forEach(apt => {
            const opt = document.createElement('option');
            opt.value = apt.id;
            opt.textContent = `${apt.apartment_number} (${apt.floor || 'Ground'}) - $${parseFloat(apt.rent_price).toFixed(2)}/mo`;
            aptSelect.appendChild(opt);
        });
    }

    new bootstrap.Modal(document.getElementById('rentModal')).show();
}

function showOccupiedNotice(houseTitle, totalApts) {
    document.getElementById('occupiedHouseName').innerText = houseTitle;
    document.getElementById('occupiedHouseTotal').innerText = totalApts;
    new bootstrap.Modal(document.getElementById('occupiedNoticeModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
