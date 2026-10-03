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
                    <div class="row g-3 py-3 border-bottom mb-3 text-center">
                        <div class="col-4 border-end">
                            <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Monthly Rent</small>
                            <h4 class="fw-bolder text-primary mb-0"><?= format_currency($house['rent_price'], $currency); ?></h4>
                        </div>
                        <div class="col-4 border-end">
                            <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Available Vacant</small>
                            <h4 class="fw-bolder <?= (int)$house['vacant_apartments'] > 0 ? 'text-success' : 'text-danger'; ?> mb-0">
                                <?= (int)$house['vacant_apartments']; ?> Units
                            </h4>
                        </div>
                        <div class="col-4">
                            <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Total Apartments</small>
                            <h4 class="fw-bolder text-main mb-0"><?= (int)$house['total_apartments']; ?> Units</h4>
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

                    <?php if ((int)$house['vacant_apartments'] <= 0): ?>
                        <div class="alert alert-warning py-3" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Fully Occupied:</strong> All units in this building are currently leased.
                        </div>
                    <?php else: ?>
                        <form method="POST" action="<?= BASE_URL; ?>user/rent_request.php">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="house_id" value="<?= (int)$house['id']; ?>">

                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Preferred Move-In Date <span class="text-danger">*</span></label>
                                <input type="date" name="move_in_date" class="form-control" value="<?= date('Y-m-d', strtotime('+3 days')); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Your Message / Requirements</label>
                                <textarea name="request_note" rows="3" class="form-control" placeholder="Specify unit floor preference, family size, duration of stay..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                                <i class="bi bi-send me-1"></i><?= $existingRequest ? 'Submit Another Request' : 'Submit Rental Request'; ?>
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
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
