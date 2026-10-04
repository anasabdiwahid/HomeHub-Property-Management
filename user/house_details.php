<?php
/**
 * User View House Details & Rental Inquiry
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('user');

$currentUser = current_user();
$userId = (int)$currentUser['id'];
$currency = get_setting($pdo, 'currency', '$');

$houseId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
if ($houseId <= 0) {
    set_flash('danger', 'Property ID is invalid.');
    header('Location: ' . BASE_URL . 'user/index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT h.*, c.category_name, c.description as category_desc,
           mgr.name as manager_name, mgr.phone as manager_phone, mgr.email as manager_email
    FROM houses h
    JOIN categories c ON h.category_id = c.id
    LEFT JOIN users mgr ON h.manager_id = mgr.id
    WHERE h.id = ? LIMIT 1
");
$stmt->execute([$houseId]);
$house = $stmt->fetch();

if (!$house) {
    set_flash('danger', 'Property not found.');
    header('Location: ' . BASE_URL . 'user/index.php');
    exit;
}

// Ensure all individual apartments exist and are synced
$apartments = ensure_house_apartments($pdo, $houseId);
$stmt->execute([$houseId]);
$house = $stmt->fetch();

// Check if user already submitted a request for this house
$stmtExisting = $pdo->prepare("SELECT * FROM rental_requests WHERE user_id = ? AND house_id = ? ORDER BY id DESC LIMIT 1");
$stmtExisting->execute([$userId, $houseId]);
$existingRequest = $stmtExisting->fetch();

$imgUrl = !empty($house['image']) && file_exists(UPLOAD_DIR . 'houses/' . $house['image'])
    ? UPLOAD_URL . 'houses/' . e($house['image'])
    : 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1200&q=80';

$pageTitle = e($house['house_name']) . ' - Details';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- User Top Navigation (No Sidebar) -->
<?php require_once __DIR__ . '/../includes/user_navbar.php'; ?>

<main class="content-wrapper container py-4">
    <?= display_flash(); ?>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>user/index.php">Available Houses</a></li>
            <li class="breadcrumb-item text-muted"><?= e($house['city']); ?></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($house['house_name']); ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Main Details Column -->
        <div class="col-lg-8">
            <div class="card overflow-hidden shadow-sm border-0 mb-4">
                <div style="position: relative; height: 380px;">
                    <img src="<?= $imgUrl; ?>" alt="<?= e($house['house_name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <div style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(to top, rgba(0,0,0,0.85), transparent); padding: 2rem 1.5rem 1rem;">
                        <span class="badge bg-primary px-3 py-1 rounded-pill mb-2"><?= e($house['category_name']); ?></span>
                        <h2 class="text-white fw-bold mb-1"><?= e($house['house_name']); ?></h2>
                        <div class="text-white-50 small">
                            <i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($house['address']); ?>, <?= e($house['city']); ?> District, Mogadishu
                            <span class="ms-3 font-monospace badge bg-dark bg-opacity-75"><?= e($house['house_code']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="row g-2 py-3 border-bottom mb-3 text-center align-items-center">
                        <div class="col-6 col-md-3 border-end">
                            <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem;">Monthly Rent</small>
                            <h4 class="fw-bolder text-primary mb-0"><?= format_currency($house['rent_price'], $currency); ?></h4>
                        </div>
                        <div class="col-6 col-md-3 border-end">
                            <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem;">Wadarta Apartments</small>
                            <h4 class="fw-bolder text-dark mb-0"><?= (int)$house['total_apartments']; ?> Qol</h4>
                        </div>
                        <div class="col-6 col-md-3 border-end">
                            <small class="text-muted text-uppercase fw-semibold text-danger" style="font-size: 0.7rem;">La Deggen Yahay</small>
                            <h4 class="fw-bolder text-danger mb-0">
                                <i class="bi bi-people-fill me-1"></i><?= (int)$house['occupied_apartments']; ?> Qol
                            </h4>
                        </div>
                        <div class="col-6 col-md-3">
                            <small class="text-muted text-uppercase fw-semibold <?= (int)$house['vacant_apartments'] > 0 ? 'text-success' : 'text-danger'; ?>" style="font-size: 0.7rem;">Bannaan (Vacant)</small>
                            <h4 class="fw-bolder <?= (int)$house['vacant_apartments'] > 0 ? 'text-success' : 'text-danger'; ?> mb-0">
                                <i class="bi bi-door-open-fill me-1"></i><?= (int)$house['vacant_apartments']; ?> Qol
                            </h4>
                        </div>
                    </div>

                    <!-- Occupancy Progress Indicator -->
                    <?php 
                        $totalApts = max(1, (int)$house['total_apartments']);
                        $occupiedPct = min(100, round(((int)$house['occupied_apartments'] / $totalApts) * 100));
                        $isFull = (int)$house['vacant_apartments'] <= 0;
                    ?>
                    <div class="mb-4 p-3 rounded-3 <?= $isFull ? 'bg-danger-subtle border border-danger-subtle' : 'bg-body-tertiary border'; ?>">
                        <div class="d-flex justify-content-between align-items-center mb-1 small fw-semibold">
                            <span><i class="bi bi-bar-chart-fill me-1 text-primary"></i>Heerka Deganaanshaha (Occupancy Rate):</span>
                            <span class="<?= $isFull ? 'text-danger fw-bold' : 'text-primary'; ?>"><?= $occupiedPct; ?>% Buuxa</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar <?= $isFull ? 'bg-danger' : ($occupiedPct > 70 ? 'bg-warning' : 'bg-success'); ?>" role="progressbar" style="width: <?= $occupiedPct; ?>%" aria-valuenow="<?= $occupiedPct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between text-muted mt-2" style="font-size: 0.8rem;">
                            <span><i class="bi bi-person-check-fill text-danger me-1"></i><?= (int)$house['occupied_apartments']; ?>/<?= (int)$house['total_apartments']; ?> waa la deggen yahay</span>
                            <span>
                                <?php if ($isFull): ?>
                                    <span class="badge bg-danger text-white"><i class="bi bi-x-circle me-1"></i>Wuu Buuxaa (0 Bannaan)</span>
                                <?php else: ?>
                                    <span class="badge bg-success text-white"><i class="bi bi-check-circle me-1"></i><?= (int)$house['vacant_apartments']; ?> Qol ayaa bannaan</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <h5 class="fw-bold mb-3">Property Description & Highlights</h5>
                    <p class="text-muted leading-relaxed" style="white-space: pre-line; line-height: 1.7;">
                        <?= e($house['description'] ?? 'No additional description provided for this listing.'); ?>
                    </p>

                    <h5 class="fw-bold mb-3 mt-4">Key Features & Amenities</h5>
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <div class="p-2 border rounded-3 d-flex align-items-center gap-2">
                                <i class="bi bi-shield-check text-primary fs-5"></i>
                                <span class="small fw-semibold">24/7 Gated Security & Guard</span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-2 border rounded-3 d-flex align-items-center gap-2">
                                <i class="bi bi-lightning-charge text-warning fs-5"></i>
                                <span class="small fw-semibold">Reliable Electricity & Backup Gen</span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-2 border rounded-3 d-flex align-items-center gap-2">
                                <i class="bi bi-droplet text-info fs-5"></i>
                                <span class="small fw-semibold">Clean Water Storage Tank Supply</span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-2 border rounded-3 d-flex align-items-center gap-2">
                                <i class="bi bi-p-square text-success fs-5"></i>
                                <span class="small fw-semibold">Dedicated Tenant Parking</span>
                            </div>
                        </div>
                    </div>

                    <!-- Individual Apartments Availability Map -->
                    <h5 class="fw-bold mb-3 mt-4"><i class="bi bi-grid-3x3-gap-fill text-primary me-2"></i>Apartments Availability (Qolalka Guriga)</h5>
                    <p class="text-muted small mb-2">Guji qolka bannaan ee aad rabto si toos ah loogu doorto foomka kireysiga:</p>
                    <div class="row g-2 mb-2">
                        <?php foreach ($apartments as $apt): ?>
                            <?php $isVacant = ($apt['status'] === 'vacant'); ?>
                            <div class="col-6 col-sm-4 col-md-3">
                                <div class="p-2 border rounded-3 text-center <?= $isVacant ? 'border-success bg-success-subtle text-success-emphasis' : 'bg-body-secondary text-muted opacity-75'; ?>" 
                                     style="<?= $isVacant ? 'cursor: pointer; transition: transform 0.15s ease;' : 'cursor: not-allowed;'; ?>"
                                     <?= $isVacant ? 'onclick="selectApartment(' . (int)$apt['id'] . ')"' : ''; ?>>
                                    <div class="fw-bold small"><?= e($apt['apartment_number']); ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;"><?= e($apt['floor'] ?? 'Ground'); ?></div>
                                    <span class="badge <?= $isVacant ? 'bg-success' : 'bg-secondary'; ?> mt-1" style="font-size: 0.7rem;">
                                        <?= $isVacant ? '<i class="bi bi-check-circle me-1"></i>Bannaan' : '<i class="bi bi-x-circle me-1"></i>La deggen'; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar / Action Column -->
        <div class="col-lg-4">
            <!-- Rental Request Card -->
            <div class="card shadow-sm border-0 mb-4 sticky-top" style="top: 90px;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-1">Rent This Property</h5>
                    <p class="text-muted small mb-3">Submit your leasing request directly to property management</p>

                    <?php if ($existingRequest): ?>
                        <div class="p-3 rounded-3 mb-3 border <?= $existingRequest['status'] === 'approved' ? 'bg-success-subtle border-success' : ($existingRequest['status'] === 'rejected' ? 'bg-danger-subtle border-danger' : 'bg-warning-subtle border-warning'); ?>">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold small">Current Application</span>
                                <?= status_badge($existingRequest['status']); ?>
                            </div>
                            <small class="d-block text-muted">Applied on: <?= format_date($existingRequest['created_at']); ?></small>
                            <?php if (!empty($existingRequest['assigned_apartment'])): ?>
                                <small class="d-block text-primary fw-bold mt-1">
                                    <i class="bi bi-door-closed me-1"></i>Assigned: <?= e($existingRequest['assigned_apartment']); ?>
                                </small>
                            <?php endif; ?>
                            <?php if (!empty($existingRequest['admin_notes'])): ?>
                                <div class="mt-2 pt-2 border-top small">
                                    <strong>Supervisor Note:</strong> <?= e($existingRequest['admin_notes']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="mt-3">
                                <a href="<?= BASE_URL; ?>user/my_requests.php" class="btn btn-sm btn-outline-dark w-100">
                                    View in My Requests
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (($house['status'] ?? 'active') === 'inactive'): ?>
                        <div class="alert alert-warning p-3 rounded-3 border-warning shadow-sm mb-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-pause-circle-fill text-warning fs-3 flex-shrink-0"></i>
                                <h6 class="fw-bold mb-0 text-warning-emphasis">Property Currently Deactive</h6>
                            </div>
                            <p class="small mb-0 text-muted">
                                This property has been set to deactive by management. New rental applications are currently closed.
                            </p>
                        </div>
                        <button type="button" class="btn btn-secondary w-100 py-2 fw-bold" disabled>
                            <i class="bi bi-pause-circle me-1"></i> Applications Closed (Deactive)
                        </button>
                    <?php elseif ((int)$house['vacant_apartments'] <= 0): ?>
                        <div class="alert alert-danger p-3 rounded-3 border-danger shadow-sm mb-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-x-octagon-fill text-danger fs-3 flex-shrink-0"></i>
                                <h6 class="fw-bold mb-0 text-danger">Property Fully Occupied</h6>
                            </div>
                            <p class="small mb-2 text-danger-emphasis">
                                All <?= (int)$house['total_apartments']; ?> apartment units in this property are currently occupied (<?= (int)$house['occupied_apartments']; ?>/<?= (int)$house['total_apartments']; ?>).
                            </p>
                            <div class="p-2 rounded bg-white text-danger fw-semibold small border border-danger-subtle">
                                <i class="bi bi-info-circle me-1"></i> No vacant units available at this time.
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary w-100 py-2 fw-bold" disabled>
                            <i class="bi bi-x-circle me-1"></i> Fully Occupied
                        </button>
                    <?php else: ?>
                        <form method="POST" action="<?= BASE_URL; ?>user/rent_request.php">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="house_id" value="<?= (int)$house['id']; ?>">

                            <!-- Select Desired Apartment Unit -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Select Desired Apartment Unit <span class="text-danger">*</span></label>
                                <select name="apartment_id" id="house_apartment_select" class="form-select" required>
                                    <option value="">-- Choose an Apartment (e.g. Unit 101) --</option>
                                    <?php foreach ($apartments as $apt): ?>
                                        <?php if ($apt['status'] === 'vacant'): ?>
                                            <option value="<?= (int)$apt['id']; ?>">
                                                <?= e($apt['apartment_number']); ?> (<?= e($apt['floor'] ?? 'Ground'); ?>) - <?= format_currency($apt['rent_price'], $currency); ?>/mo
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">
                                    Xulo qolka aad doonayso inaad degto (tusaale <strong>Apartment 13</strong>).
                                </small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Preferred Move-In Date <span class="text-danger">*</span></label>
                                <input type="date" name="move_in_date" class="form-control" value="<?= date('Y-m-d', strtotime('+3 days')); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Description / Your Message</label>
                                <textarea name="request_note" rows="3" class="form-control" placeholder="Specify any preferences, family size, duration of stay..."></textarea>
                            </div>

                            <div class="p-2 rounded-3 bg-success-subtle border border-success-subtle d-flex align-items-center gap-2 small text-success-emphasis mb-3">
                                <i class="bi bi-whatsapp fs-5 text-success flex-shrink-0"></i>
                                <div>
                                    Marka aad codsato, waxaa toos laguu geynayaa <strong>WhatsApp (+252 616256534)</strong> si aad fariinta ugu dirto maamulka.
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success w-100 py-2 fw-bold shadow-sm">
                                <i class="bi bi-whatsapp me-1"></i><?= $existingRequest ? 'Submit Another Request via WhatsApp' : 'Submit Rental Request via WhatsApp'; ?>
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Property Supervisor Card -->
                    <div class="border-top mt-4 pt-3">
                        <small class="text-muted fw-bold text-uppercase d-block mb-2">Assigned Supervisor</small>
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-secondary-subtle text-secondary d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px;">
                                <i class="bi bi-person-workspace fs-5"></i>
                            </div>
                            <div>
                                <div class="fw-bold small"><?= e($house['manager_name'] ?? 'HomeHub Admin Office'); ?></div>
                                <small class="text-muted d-block"><i class="bi bi-telephone me-1"></i><?= e($house['manager_phone'] ?? '+252 61 555 4321'); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
</main>

<script>
function selectApartment(aptId) {
    const sel = document.getElementById('house_apartment_select');
    if (sel) {
        sel.value = aptId;
        sel.focus();
        sel.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

