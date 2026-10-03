<?php
/**
 * User Approved Rentals & Active Leases
 * HomeHub Property Management System
 * Top Navigation Only (No Sidebar)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('user');

$currentUser = current_user();
$userId = (int)$currentUser['id'];
$currency = get_setting($pdo, 'currency', '$');

// Fetch approved rental requests for this user
$stmt = $pdo->prepare("
    SELECT r.*, h.id as house_id, h.house_name, h.house_code, h.city, h.address, h.rent_price, h.image,
           c.category_name, mgr.name as manager_name, mgr.phone as manager_phone, mgr.email as manager_email
    FROM rental_requests r
    JOIN houses h ON r.house_id = h.id
    JOIN categories c ON h.category_id = c.id
    LEFT JOIN users mgr ON h.manager_id = mgr.id
    WHERE r.user_id = ? AND r.status = 'approved'
    ORDER BY r.updated_at DESC, r.id DESC
");
$stmt->execute([$userId]);
$approvedRentals = $stmt->fetchAll();

// Fetch payments made by this tenant
$stmtPay = $pdo->prepare("
    SELECT p.*, h.house_name, h.house_code
    FROM payments p
    JOIN houses h ON p.house_id = h.id
    WHERE p.user_id = ?
    ORDER BY p.payment_date DESC
");
$stmtPay->execute([$userId]);
$myPayments = $stmtPay->fetchAll();

$pageTitle = 'Approved Rentals - HomeHub';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- User Top Navigation (No Sidebar) -->
<?php require_once __DIR__ . '/../includes/user_navbar.php'; ?>

<main class="content-wrapper container py-4">
    <?= display_flash(); ?>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
        <div>
            <h3 class="fw-bold mb-1">Approved Property Leases</h3>
            <p class="text-muted small mb-0">Houses and apartments where your tenancy application has been approved</p>
        </div>
        <a href="<?= BASE_URL; ?>user/index.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-houses me-1"></i>Find More Properties
        </a>
    </div>

    <?php if (empty($approvedRentals)): ?>
        <div class="card text-center py-5 shadow-sm border-0">
            <div class="card-body">
                <i class="bi bi-patch-question text-muted" style="font-size: 3.5rem;"></i>
                <h5 class="fw-bold mt-3">No Approved Rentals Yet</h5>
                <p class="text-muted small">Once the property supervisor verifies and approves your rental request, it will appear here.</p>
                <div class="d-flex justify-content-center gap-2 mt-3">
                    <a href="<?= BASE_URL; ?>user/my_requests.php" class="btn btn-outline-secondary btn-sm">View Pending Applications</a>
                    <a href="<?= BASE_URL; ?>user/index.php" class="btn btn-primary btn-sm">Browse Available Properties</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4 mb-5">
            <?php foreach ($approvedRentals as $r): ?>
                <?php 
                    $imgUrl = !empty($r['image']) && file_exists(UPLOAD_DIR . 'houses/' . $r['image'])
                        ? UPLOAD_URL . 'houses/' . e($r['image'])
                        : 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=600&q=80';
                ?>
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 overflow-hidden h-100">
                        <div class="row g-0 h-100">
                            <div class="col-md-5 position-relative">
                                <img src="<?= $imgUrl; ?>" alt="<?= e($r['house_name']); ?>" class="w-100 h-100 object-fit-cover" style="min-height: 220px;">
                                <span class="badge bg-success position-absolute top-0 start-0 m-3 px-3 py-2 shadow-sm">
                                    <i class="bi bi-check2-circle me-1"></i>Approved Lease
                                </span>
                            </div>
                            <div class="col-md-7 d-flex flex-column p-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-primary-subtle text-primary"><?= e($r['category_name']); ?></span>
                                    <span class="font-monospace text-muted small"><?= e($r['house_code']); ?></span>
                                </div>

                                <h5 class="fw-bold text-main mb-1"><?= e($r['house_name']); ?></h5>
                                <p class="text-muted small mb-2"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($r['address']); ?>, <?= e($r['city']); ?> District, Mogadishu</p>

                                <div class="p-2 bg-body-tertiary rounded-3 mb-3 border">
                                    <div class="d-flex justify-content-between small">
                                        <span class="text-muted">Monthly Rent:</span>
                                        <strong class="text-primary"><?= format_currency($r['rent_price'], $currency); ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between small mt-1">
                                        <span class="text-muted">Move-in Date:</span>
                                        <strong><?= !empty($r['move_in_date']) ? format_date($r['move_in_date']) : 'Immediate'; ?></strong>
                                    </div>
                                </div>

                                <?php if (!empty($r['admin_notes'])): ?>
                                    <div class="small mb-3 text-muted">
                                        <strong>Instructions:</strong> <?= e($r['admin_notes']); ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Manager Contact & Actions -->
                                <div class="mt-auto border-top pt-3 d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">SUPERVISOR</small>
                                        <div class="fw-bold small"><?= e($r['manager_name'] ?? 'HomeHub Admin'); ?></div>
                                    </div>
                                    <div>
                                        <?php if (!empty($r['manager_phone'])): ?>
                                            <a href="tel:<?= e($r['manager_phone']); ?>" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-telephone-fill me-1"></i>Call
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= BASE_URL; ?>user/house_details.php?id=<?= (int)$r['house_id']; ?>" class="btn btn-sm btn-outline-primary ms-1">
                                            Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Rent Payment Receipts Table for this Tenant -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-receipt text-success me-2"></i>My Rent Payment Receipts (<?= count($myPayments); ?>)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Receipt / Ref #</th>
                                <th>Property</th>
                                <th>Amount Paid</th>
                                <th>Method</th>
                                <th>Date</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($myPayments)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No rent payment receipts recorded yet under your account.</td></tr>
                            <?php else: ?>
                                <?php foreach ($myPayments as $p): ?>
                                    <tr>
                                        <td class="font-monospace fw-bold text-muted small"><?= !empty($p['reference_no']) ? e($p['reference_no']) : '#' . (int)$p['id']; ?></td>
                                        <td>
                                            <div class="fw-bold"><?= e($p['house_name']); ?></div>
                                            <small class="text-muted font-monospace"><?= e($p['house_code']); ?></small>
                                        </td>
                                        <td class="fw-bold text-success fs-6"><?= format_currency($p['amount'], $currency); ?></td>
                                        <td><span class="badge bg-primary-subtle text-primary"><?= e($p['payment_method']); ?></span></td>
                                        <td class="small"><?= format_date($p['payment_date']); ?></td>
                                        <td class="small text-muted"><?= e($p['notes'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
