<?php
/**
 * Admin Payments & Revenue Management
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$currency = get_setting($pdo, 'currency', '$');

// Handle Record Payment / Delete Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid security token.');
        header('Location: ' . BASE_URL . 'admin/payments.php');
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

        if ($houseId <= 0 || $amount <= 0 || empty($paymentDate)) {
            set_flash('danger', 'Please select a property, valid amount, and date.');
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO payments (house_id, user_id, amount, payment_date, payment_method, reference_no, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$houseId, $userId, $amount, $paymentDate, $paymentMethod, $referenceNo, $notes]);
            set_flash('success', 'Rent payment of ' . format_currency($amount, $currency) . ' recorded successfully!');
        }
    } elseif ($action === 'delete') {
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        if ($paymentId > 0) {
            $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
            $stmt->execute([$paymentId]);
            set_flash('success', 'Payment record deleted successfully.');
        }
    }

    header('Location: ' . BASE_URL . 'admin/payments.php');
    exit;
}

// Filter parameters
$filterHouse = !empty($_GET['house_id']) ? (int)$_GET['house_id'] : 0;
$filterMethod = trim($_GET['method'] ?? '');
$filterMonth = trim($_GET['month'] ?? '');

$sql = "SELECT p.*, h.house_name, h.house_code, h.city, u.name as tenant_name, u.phone as tenant_phone
        FROM payments p
        JOIN houses h ON p.house_id = h.id
        LEFT JOIN users u ON p.user_id = u.id
        WHERE 1=1";
$params = [];

if ($filterHouse > 0) {
    $sql .= " AND p.house_id = ?";
    $params[] = $filterHouse;
}
if (!empty($filterMethod)) {
    $sql .= " AND p.payment_method = ?";
    $params[] = $filterMethod;
}
if (!empty($filterMonth)) {
    $sql .= " AND DATE_FORMAT(p.payment_date, '%Y-%m') = ?";
    $params[] = $filterMonth;
}

$sql .= " ORDER BY p.payment_date DESC, p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Dropdowns data
$housesList = $pdo->query("SELECT id, house_name, house_code, rent_price FROM houses ORDER BY house_name ASC")->fetchAll();
$tenantsList = $pdo->query("SELECT id, name, phone FROM users WHERE role = 'user' AND status = 'active' ORDER BY name ASC")->fetchAll();

// Aggregate stats
$totalCollections = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments")->fetchColumn();
$thisMonthCollections = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = '" . date('Y-m') . "'")->fetchColumn();
$totalTransactions = (int)$pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn();

$pageTitle = 'Payments & Revenue';
$pageHeading = 'Revenue & Rent Tracking';
$pageSubtitle = 'Record and manage rent payments, mobile transactions, and payment receipts';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <main class="content-wrapper">
            <?= display_flash(); ?>

            <!-- Revenue Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card p-3 shadow-sm border-0 border-start border-primary border-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted fw-bold text-uppercase">Total Collections (All-Time)</small>
                                <h3 class="fw-bolder text-primary mb-0 mt-1"><?= format_currency($totalCollections, $currency); ?></h3>
                            </div>
                            <div class="stat-icon icon-primary">
                                <i class="bi bi-wallet2"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card p-3 shadow-sm border-0 border-start border-success border-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted fw-bold text-uppercase">Current Month Collections</small>
                                <h3 class="fw-bolder text-success mb-0 mt-1"><?= format_currency($thisMonthCollections, $currency); ?></h3>
                            </div>
                            <div class="stat-icon icon-success">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card p-3 shadow-sm border-0 border-start border-info border-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted fw-bold text-uppercase">Recorded Transactions</small>
                                <h3 class="fw-bolder text-info mb-0 mt-1"><?= $totalTransactions; ?></h3>
                            </div>
                            <div class="stat-icon icon-indigo">
                                <i class="bi bi-receipt"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action & Filter Bar -->
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <form method="GET" action="<?= BASE_URL; ?>admin/payments.php" class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <select name="house_id" class="form-select">
                                <option value="0">All Properties</option>
                                <?php foreach ($housesList as $hl): ?>
                                    <option value="<?= (int)$hl['id']; ?>" <?= $filterHouse === (int)$hl['id'] ? 'selected' : ''; ?>>
                                        <?= e($hl['house_name']); ?> (<?= e($hl['house_code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-sm-6 col-md-3">
                            <select name="method" class="form-select">
                                <option value="">All Payment Methods</option>
                                <option value="EVC Plus" <?= $filterMethod === 'EVC Plus' ? 'selected' : ''; ?>>EVC Plus</option>
                                <option value="Zaad" <?= $filterMethod === 'Zaad' ? 'selected' : ''; ?>>Zaad Service</option>
                                <option value="Sahal" <?= $filterMethod === 'Sahal' ? 'selected' : ''; ?>>Sahal</option>
                                <option value="Bank Transfer" <?= $filterMethod === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="Cash" <?= $filterMethod === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                            </select>
                        </div>

                        <div class="col-sm-6 col-md-2">
                            <input type="month" name="month" class="form-control" value="<?= e($filterMonth); ?>">
                        </div>

                        <div class="col-md-3 d-flex gap-2 justify-content-md-end">
                            <button type="submit" class="btn btn-outline-primary btn-sm px-3">
                                <i class="bi bi-funnel me-1"></i>Filter
                            </button>
                            <?php if ($filterHouse > 0 || !empty($filterMethod) || !empty($filterMonth)): ?>
                                <a href="<?= BASE_URL; ?>admin/payments.php" class="btn btn-outline-secondary btn-sm" title="Clear">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-primary btn-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
                                <i class="bi bi-plus-lg me-1"></i>Record Rent
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Payments Table -->
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">Payments Ledger (<?= count($payments); ?>)</h6>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('paymentsTable', 'payments_ledger.csv')">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i>Export CSV
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="paymentsTable">
                            <thead>
                                <tr>
                                    <th>Ref #</th>
                                    <th>Property</th>
                                    <th>Tenant / Payer</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Date</th>
                                    <th>Notes</th>
                                    <th class="text-end no-export">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($payments)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">No payment records found.</td>
                                    </tr>
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
                                                <?php if (!empty($p['tenant_name'])): ?>
                                                    <div class="fw-semibold text-main"><?= e($p['tenant_name']); ?></div>
                                                    <small class="text-muted"><?= e($p['tenant_phone'] ?? ''); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted small">Direct Property Collection</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold fs-6 text-success">
                                                <?= format_currency($p['amount'], $currency); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                                    <?= e($p['payment_method']); ?>
                                                </span>
                                            </td>
                                            <td class="small">
                                                <?= format_date($p['payment_date']); ?>
                                            </td>
                                            <td class="small text-muted" style="max-width: 200px;">
                                                <?= e($p['notes'] ?? '-'); ?>
                                            </td>
                                            <td class="text-end no-export">
                                                <form method="POST" action="<?= BASE_URL; ?>admin/payments.php" class="d-inline" onsubmit="return confirm('Delete this payment record permanently?');">
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="payment_id" value="<?= (int)$p['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Payment">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
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

<!-- Record Payment Modal -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= BASE_URL; ?>admin/payments.php" class="modal-content">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="add">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-stack text-success me-2"></i>Record Rent Collection</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Property <span class="text-danger">*</span></label>
                    <select name="house_id" class="form-select" id="pay_house_select" required onchange="updateRentHint()">
                        <option value="">-- Select Property --</option>
                        <?php foreach ($housesList as $hl): ?>
                            <option value="<?= (int)$hl['id']; ?>" data-rent="<?= (float)$hl['rent_price']; ?>">
                                <?= e($hl['house_name']); ?> (<?= e($hl['house_code']); ?>) - Rent: <?= format_currency($hl['rent_price'], $currency); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Tenant (Optional)</label>
                    <select name="user_id" class="form-select">
                        <option value="">-- Unassigned / Direct Tenant --</option>
                        <?php foreach ($tenantsList as $tl): ?>
                            <option value="<?= (int)$tl['id']; ?>">
                                <?= e($tl['name']); ?> (<?= e($tl['phone'] ?? 'No phone'); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold small">Amount Paid ($) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="amount" id="pay_amount" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold small">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold small">Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-select" required>
                            <option value="EVC Plus">EVC Plus (Hormuud)</option>
                            <option value="Zaad">Zaad Service (Telesom)</option>
                            <option value="Sahal">Sahal (Golis)</option>
                            <option value="Bank Transfer">Bank Transfer (Premier / Dahabshiil / IBS)</option>
                            <option value="Cash">Cash In Hand</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold small">Transaction / Ref #</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="e.g. EVC-88231">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Notes / Month Covered</label>
                    <input type="text" name="notes" class="form-control" placeholder="e.g. October 2026 rent payment Unit 201">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success btn-sm">Save Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateRentHint() {
    const sel = document.getElementById('pay_house_select');
    const opt = sel.options[sel.selectedIndex];
    const rent = opt.getAttribute('data-rent');
    if (rent) {
        document.getElementById('pay_amount').value = rent;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
