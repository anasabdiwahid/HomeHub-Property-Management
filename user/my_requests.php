<?php
/**
 * User My Rental Requests
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
$targetWhatsApp = get_setting($pdo, 'whatsapp_number', '+252 616256534');
$cleanWhatsApp = preg_replace('/[^0-9]/', '', $targetWhatsApp) ?: '252616256534';

// Handle Cancel Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid security token.');
    } else {
        $reqId = (int)($_POST['request_id'] ?? 0);
        if ($reqId > 0) {
            $stmt = $pdo->prepare("DELETE FROM rental_requests WHERE id = ? AND user_id = ? AND status = 'pending'");
            $stmt->execute([$reqId, $userId]);
            set_flash('success', 'Rental application cancelled.');
        }
    }
    header('Location: ' . BASE_URL . 'user/my_requests.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT r.*, h.house_name, h.house_code, h.city, h.address, h.rent_price, h.image,
           c.category_name, mgr.name as manager_name, mgr.phone as manager_phone
    FROM rental_requests r
    JOIN houses h ON r.house_id = h.id
    JOIN categories c ON h.category_id = c.id
    LEFT JOIN users mgr ON h.manager_id = mgr.id
    WHERE r.user_id = ?
    ORDER BY r.id DESC
");
$stmt->execute([$userId]);
$requests = $stmt->fetchAll();

$pageTitle = 'My Rental Requests - HomeHub';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- User Top Navigation (No Sidebar) -->
<?php require_once __DIR__ . '/../includes/user_navbar.php'; ?>

<main class="content-wrapper container py-4">
    <?= display_flash(); ?>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
        <div>
            <h3 class="fw-bold mb-1">My Rental Applications</h3>
            <p class="text-muted small mb-0">Track the status of your submitted property rental inquiries</p>
        </div>
        <a href="<?= BASE_URL; ?>user/index.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-search me-1"></i>Browse More Houses
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <span class="card-header-icon bg-primary text-white"><i class="bi bi-file-earmark-text"></i></span>
                <div>
                    <h6 class="mb-0 fw-bold">Application History</h6>
                    <small class="text-muted"><?= count($requests); ?> rental applications logged</small>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Property</th>
                            <th>Category & District</th>
                            <th>Apartment Unit</th>
                            <th>Monthly Rent</th>
                            <th>Move-In Date</th>
                            <th>Status</th>
                            <th>Supervisor Response</th>
                            <th>Applied Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="bi bi-file-earmark-x"></i>
                                        </div>
                                        <h6 class="empty-state-title">No Rental Applications Yet</h6>
                                        <p class="empty-state-text">You have not submitted any rental applications yet. Explore available properties and apply in 1-click.</p>
                                        <a href="<?= BASE_URL; ?>user/index.php" class="btn btn-primary btn-sm mt-3">
                                            <i class="bi bi-search me-1"></i>Browse Available Houses
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $r): ?>
                                <?php 
                                    $imgUrl = !empty($r['image']) && file_exists(UPLOAD_DIR . 'houses/' . $r['image'])
                                        ? UPLOAD_URL . 'houses/' . e($r['image'])
                                        : 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=120&q=80';
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?= $imgUrl; ?>" alt="House" class="rounded-2 object-fit-cover shadow-sm" style="width: 50px; height: 40px;">
                                            <div>
                                                <a href="<?= BASE_URL; ?>user/house_details.php?id=<?= (int)$r['house_id']; ?>" class="fw-bold text-main text-decoration-none">
                                                    <?= e($r['house_name']); ?>
                                                </a>
                                                <div class="small text-muted font-monospace"><?= e($r['house_code']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><span class="badge bg-primary-subtle text-primary"><?= e($r['category_name']); ?></span></div>
                                        <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($r['city']); ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($r['assigned_apartment'])): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-2 py-1">
                                                <i class="bi bi-door-closed-fill me-1"></i><?= e($r['assigned_apartment']); ?>
                                            </span>
                                        <?php elseif (!empty($r['apartment_id'])): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-2 py-1">
                                                <i class="bi bi-door-closed-fill me-1"></i>Unit #<?= (int)$r['apartment_id']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border px-2 py-1">
                                                <i class="bi bi-clock me-1"></i>Pending Unit
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold text-primary">
                                        <?= format_currency($r['rent_price'], $currency); ?>
                                    </td>
                                    <td>
                                        <?= !empty($r['move_in_date']) ? format_date($r['move_in_date']) : '<span class="text-muted small">Immediate</span>'; ?>
                                    </td>
                                    <td>
                                        <?= status_badge($r['status']); ?>
                                    </td>
                                    <td class="small text-muted" style="max-width: 220px;">
                                        <?php if (!empty($r['admin_notes'])): ?>
                                            <span class="text-dark fw-semibold"><?= e($r['admin_notes']); ?></span>
                                        <?php else: ?>
                                            <span class="fst-italic">Under review by property manager</span>
                                        <?php endif; ?>
                                        <?php if (!empty($r['manager_phone']) && $r['status'] === 'approved'): ?>
                                            <div class="mt-1">
                                                <a href="tel:<?= e($r['manager_phone']); ?>" class="badge bg-success-subtle text-success text-decoration-none">
                                                    <i class="bi bi-telephone me-1"></i>Call Manager: <?= e($r['manager_phone']); ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= format_date($r['created_at']); ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-action-group justify-content-end align-items-center">
                                            <?php
                                                $rMsg = "🏠 *CODSIGA KIREYSIGA GURI (HomeHub)*\n\n";
                                                $rMsg .= "👤 *Full Name:* " . ($currentUser['name'] ?? 'Tenant') . "\n";
                                                $rMsg .= "📧 *Email:* " . ($currentUser['email'] ?? 'N/A') . "\n";
                                                if (!empty($currentUser['phone'])) {
                                                    $rMsg .= "📞 *Phone:* " . $currentUser['phone'] . "\n";
                                                }
                                                $rMsg .= "🏡 *Guri Name:* " . $r['house_name'] . "\n";
                                                $rMsg .= "🏢 *Guriga Oo Doortay:* " . $r['house_name'] . " (" . $r['house_code'] . " - " . ($r['category_name'] ?? '') . ")\n";
                                                $rMsg .= "📍 *Location:* " . $r['address'] . ", " . $r['city'] . "\n";
                                                $rMsg .= "💰 *Qiimaha:* " . format_currency($r['rent_price'], $currency) . " / bishii\n";
                                                $rMsg .= "📅 *Move-In Date:* " . (!empty($r['move_in_date']) ? $r['move_in_date'] : 'Immediate') . "\n\n";
                                                $rMsg .= "📝 *Description:* \n" . (!empty($r['request_note']) ? $r['request_note'] : 'N/A') . "\n\n";
                                                $rMsg .= "------------------------------------\n";
                                                $rMsg .= "Waxaan rabaa inaan kireysto gurigan, fadlan iisoo xaqiiji.";
                                                $rWaUrl = 'https://api.whatsapp.com/send?phone=' . $cleanWhatsApp . '&text=' . rawurlencode($rMsg);
                                            ?>
                                            <a href="<?= e($rWaUrl); ?>" target="_blank" class="btn btn-sm btn-outline-success text-nowrap d-inline-flex align-items-center gap-1" title="Ku dir WhatsApp (+252 616256534)">
                                                <i class="bi bi-whatsapp"></i> <span>WhatsApp</span>
                                            </a>
                                            <?php if ($r['status'] === 'pending'): ?>
                                                <form method="POST" action="<?= BASE_URL; ?>user/my_requests.php" class="d-inline" data-confirm="Ma hubtaa inaad joojiso (cancel) codsigan kireysiga ah?">
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="request_id" value="<?= (int)$r['id']; ?>">
                                                    <button type="submit" class="btn-action btn-action-delete" title="Cancel Application">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                </form>
                                            <?php elseif ($r['status'] === 'approved'): ?>
                                                <a href="<?= BASE_URL; ?>user/approved_rentals.php" class="btn btn-sm btn-success text-nowrap">
                                                    <i class="bi bi-patch-check me-1"></i>View Lease
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= BASE_URL; ?>user/house_details.php?id=<?= (int)$r['house_id']; ?>" class="btn-action btn-action-view" title="Re-apply or View Details">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </a>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
