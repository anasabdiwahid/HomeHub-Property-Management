<?php
/**
 * Manager Rent Tracking & Collections
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('manager');

$managerId = (int)current_user_id();
$currency = get_setting($pdo, 'currency', '$');

// Handle Record Rent Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid security token.');
        header('Location: ' . BASE_URL . 'manager/payments.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $houseId       = (int)($_POST['house_id'] ?? 0);
        $userId        = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $amount        = (float)($_POST['amount'] ?? 0);
        $paymentDate   = trim($_POST['payment_date'] ?? date('Y-m-d'));
        $paymentMethod = trim($_POST['payment_method'] ?? 'EVC Plus');
        $referenceNo   = trim($_POST['reference_no'] ?? '');
        $notes         = trim($_POST['notes'] ?? '');

        // Verify house belongs to this manager
        $chk = $pdo->prepare("SELECT id FROM houses WHERE id = ? AND manager_id = ? LIMIT 1");
        $chk->execute([$houseId, $managerId]);
        if (!$chk->fetch()) {
            set_flash('danger', 'You can only record payments for your assigned houses.');
        } elseif ($amount <= 0 || empty($paymentDate)) {
            set_flash('danger', 'Please enter a valid payment amount and date.');
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO payments (house_id, user_id, amount, payment_date, payment_method, reference_no, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$houseId, $userId, $amount, $paymentDate, $paymentMethod, $referenceNo, $notes]);
            set_flash('success', 'Rent collection of ' . format_currency($amount, $currency) . ' recorded successfully!');
        }
    }

    header('Location: ' . BASE_URL . 'manager/payments.php');
    exit;
}

// Fetch manager houses for dropdown
$myHouses = $pdo->prepare("SELECT id, house_name, house_code, rent_price FROM houses WHERE manager_id = ? ORDER BY house_name ASC");
$myHouses->execute([$managerId]);
$housesList = $myHouses->fetchAll();

// Fetch tenants
$tenantsList = $pdo->query("SELECT id, name, phone FROM users WHERE role = 'user' AND status = 'active' ORDER BY name ASC")->fetchAll();

// Payments query for manager houses
$sql = "SELECT p.*, h.house_name, h.house_code, h.city, u.name as tenant_name, u.phone as tenant_phone
        FROM payments p
        JOIN houses h ON p.house_id = h.id
        LEFT JOIN users u ON p.user_id = u.id
        WHERE h.manager_id = ?
        ORDER BY p.payment_date DESC, p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$managerId]);
$payments = $stmt->fetchAll();

$totalCollections = array_sum(array_column($payments, 'amount'));

$pageTitle = 'Rent Tracking - HomeHub';
$pageHeading = 'Rent Tracking';
$pageSubtitle = 'Record and monitor rent collections for your supervised properties';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once __DIR__ . '/../includes/manager_sidebar.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <main class="content-wrapper">
            <?= display_flash(); ?>

            <!-- Action & Summary Bar -->
            <div class="card mb-4 shadow-sm">
                <div class="card-body d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                    <div>
                        <small class="text-muted text-uppercase fw-bold">Total Rent Collected</small>
                        <h3 class="fw-bolder text-success mb-0"><?= format_currency($totalCollections, $currency); ?></h3>
                    </div>
                    <div>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#recordRentModal">
                            <i class="bi bi-plus-circle me-1"></i>Record Rent Collection
                        </button>
                    </div>
                </div>
            </div>

            <!-- Ledger Table -->
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">Collections Ledger (<?= count($payments); ?>)</h6>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('managerPaymentsTable', 'my_rent_collections.csv')">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i>Export CSV
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="managerPaymentsTable">
                            <thead>
                                <tr>
                                    <th>Ref #</th>
                                    <th>Property</th>
                                    <th>Tenant</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Date</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($payments)): ?>
                                    <tr><td colspan="7" class="text-center py-5 text-muted">No rent payments recorded yet for your properties.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($payments as $p): ?>
                                        <tr>
                                            <td class="font-monospace fw-bold text-muted small">
                                                <?= !empty($p['reference_no']) ? e($p['reference_no']) : '#' . (int)$p['id']; ?>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= e($p['house_name']); ?></div>
                                                <span class="badge bg-secondary-subtle text-secondary font-monospace"><?= e($p['house_code']); ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?= e($p['tenant_name'] ?? 'Direct Tenant'); ?></div>
                                                <small class="text-muted"><?= e($p['tenant_phone'] ?? ''); ?></small>
                                            </td>
                                            <td class="fw-bold fs-6 text-success">
                                                <?= format_currency($p['amount'], $currency); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                    <?= e($p['payment_method']); ?>
                                                </span>
                                            </td>
                                            <td class="small">
                                                <?= format_date($p['payment_date']); ?>
                                            </td>
                                            <td class="small text-muted" style="max-width: 220px;">
                                                <?= e($p['notes'] ?? '-'); ?>
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

<!-- Record Rent Modal -->
<div class="modal fade" id="recordRentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= BASE_URL; ?>manager/payments.php" class="modal-content">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="add">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-coin text-success me-2"></i>Record Tenant Rent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Property <span class="text-danger">*</span></label>
                    <select name="house_id" class="form-select" id="mgr_house_sel" required onchange="setMgrRentHint()">
                        <option value="">-- Select Your House --</option>
                        <?php foreach ($housesList as $hl): ?>
                            <option value="<?= (int)$hl['id']; ?>" data-rent="<?= (float)$hl['rent_price']; ?>">
                                <?= e($hl['house_name']); ?> (<?= e($hl['house_code']); ?>) - <?= format_currency($hl['rent_price'], $currency); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Tenant (Optional)</label>
                    <select name="user_id" class="form-select">
                        <option value="">-- Direct / Unregistered Tenant --</option>
                        <?php foreach ($tenantsList as $tl): ?>
                            <option value="<?= (int)$tl['id']; ?>">
                                <?= e($tl['name']); ?> (<?= e($tl['phone'] ?? ''); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold small">Amount Paid ($) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="amount" id="mgr_amount" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold small">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold small">Payment Channel</label>
                        <select name="payment_method" class="form-select">
                            <option value="EVC Plus">EVC Plus (Hormuud)</option>
                            <option value="Zaad">Zaad Service (Telesom)</option>
                            <option value="Sahal">Sahal (Golis)</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cash">Cash</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold small">Ref / Transaction #</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="e.g. EVC-99124">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Notes / Month</label>
                    <input type="text" name="notes" class="form-control" placeholder="e.g. October Rent - Apt 102">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success btn-sm">Save Collection</button>
            </div>
        </form>
    </div>
</div>

<script>
function setMgrRentHint() {
    const sel = document.getElementById('mgr_house_sel');
    const opt = sel.options[sel.selectedIndex];
    const rent = opt.getAttribute('data-rent');
    if (rent) {
        document.getElementById('mgr_amount').value = rent;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
