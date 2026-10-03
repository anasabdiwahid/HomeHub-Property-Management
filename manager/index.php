<?php
/**
 * Manager Dashboard
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

// 1. My Houses
$stmtHouses = $pdo->prepare("SELECT COUNT(*) FROM houses WHERE manager_id = ?");
$stmtHouses->execute([$managerId]);
$myHousesCount = (int)$stmtHouses->fetchColumn();

// 2. Occupied Apartments
$stmtOcc = $pdo->prepare("SELECT COALESCE(SUM(occupied_apartments), 0) FROM houses WHERE manager_id = ?");
$stmtOcc->execute([$managerId]);
$myOccupied = (int)$stmtOcc->fetchColumn();

// 3. Vacant Apartments
$stmtVac = $pdo->prepare("SELECT COALESCE(SUM(vacant_apartments), 0) FROM houses WHERE manager_id = ?");
$stmtVac->execute([$managerId]);
$myVacant = (int)$stmtVac->fetchColumn();

// Total Apartments
$myTotalApts = $myOccupied + $myVacant;
$myOccupancyRate = $myTotalApts > 0 ? round(($myOccupied / $myTotalApts) * 100, 1) : 0;

// 4. Monthly Revenue
$currentMonth = date('Y-m');
$stmtRev = $pdo->prepare("
    SELECT COALESCE(SUM(p.amount), 0) 
    FROM payments p 
    JOIN houses h ON p.house_id = h.id 
    WHERE h.manager_id = ? AND DATE_FORMAT(p.payment_date, '%Y-%m') = ?
");
$stmtRev->execute([$managerId, $currentMonth]);
$myMonthlyRevenue = (float)$stmtRev->fetchColumn();

// If current month is 0, fall back to all-time revenue for assigned houses
$stmtTotalRev = $pdo->prepare("
    SELECT COALESCE(SUM(p.amount), 0) 
    FROM payments p 
    JOIN houses h ON p.house_id = h.id 
    WHERE h.manager_id = ?
");
$stmtTotalRev->execute([$managerId]);
$myAllTimeRevenue = (float)$stmtTotalRev->fetchColumn();

// Recent Rental Requests for My Houses
$stmtReq = $pdo->prepare("
    SELECT r.*, u.name as user_name, u.email as user_email, u.phone as user_phone,
           h.house_name, h.house_code, h.city, h.rent_price
    FROM rental_requests r
    JOIN users u ON r.user_id = u.id
    JOIN houses h ON r.house_id = h.id
    WHERE h.manager_id = ?
    ORDER BY r.id DESC LIMIT 5
");
$stmtReq->execute([$managerId]);
$recentRequests = $stmtReq->fetchAll();

// Recent Payments for My Houses
$stmtPay = $pdo->prepare("
    SELECT p.*, h.house_name, h.house_code, u.name as tenant_name
    FROM payments p
    JOIN houses h ON p.house_id = h.id
    LEFT JOIN users u ON p.user_id = u.id
    WHERE h.manager_id = ?
    ORDER BY p.payment_date DESC, p.id DESC LIMIT 5
");
$stmtPay->execute([$managerId]);
$recentPayments = $stmtPay->fetchAll();

// My Houses List
$stmtMyHouses = $pdo->prepare("
    SELECT h.*, c.category_name 
    FROM houses h 
    JOIN categories c ON h.category_id = c.id 
    WHERE h.manager_id = ? 
    ORDER BY h.id DESC LIMIT 4
");
$stmtMyHouses->execute([$managerId]);
$myHouses = $stmtMyHouses->fetchAll();

$pageTitle = 'Manager Dashboard';
$pageHeading = 'Manager Dashboard';
$pageSubtitle = 'Supervision Portal • ' . e($currentUser['name'] ?? 'Manager');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once __DIR__ . '/../includes/manager_sidebar.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <main class="content-wrapper">
            <?= display_flash(); ?>

            <!-- Header Banner -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div>
                    <h3 class="fw-bold mb-1">Portfolio Summary</h3>
                    <p class="text-muted small mb-0">Overview of properties assigned to your management in Mogadishu</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL; ?>manager/houses.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-houses me-1"></i>Manage My Houses
                    </a>
                    <a href="<?= BASE_URL; ?>manager/payments.php" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-plus-circle me-1"></i>Record Rent
                    </a>
                </div>
            </div>

            <!-- 4 Required Statistics Cards -->
            <div class="row g-3 mb-4">
                <!-- 1. My Houses -->
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="stat-label">My Houses</div>
                                <div class="stat-value text-primary"><?= $myHousesCount; ?></div>
                                <div class="stat-trend text-primary">
                                    <i class="bi bi-building me-1"></i> Assigned Properties
                                </div>
                            </div>
                            <div class="stat-icon icon-primary">
                                <i class="bi bi-houses"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Occupied Apartments -->
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="stat-label">Occupied Apartments</div>
                                <div class="stat-value text-success"><?= $myOccupied; ?></div>
                                <div class="stat-trend text-success">
                                    <i class="bi bi-person-check-fill me-1"></i> <?= $myOccupancyRate; ?>% occupancy
                                </div>
                            </div>
                            <div class="stat-icon icon-success">
                                <i class="bi bi-door-closed"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Vacant Apartments -->
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="stat-label">Vacant Apartments</div>
                                <div class="stat-value text-warning"><?= $myVacant; ?></div>
                                <div class="stat-trend text-warning">
                                    <i class="bi bi-door-open-fill me-1"></i> Ready for lease
                                </div>
                            </div>
                            <div class="stat-icon icon-warning">
                                <i class="bi bi-key"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Monthly Revenue -->
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="stat-label">Monthly Revenue</div>
                                <div class="stat-value text-success">
                                    <?= format_currency($myMonthlyRevenue > 0 ? $myMonthlyRevenue : $myAllTimeRevenue, $currency); ?>
                                </div>
                                <div class="stat-trend text-muted">
                                    <i class="bi bi-graph-up-arrow me-1"></i> Rent collections
                                </div>
                            </div>
                            <div class="stat-icon icon-indigo">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assigned Houses Quick Cards -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-building-check text-primary me-2"></i>My Assigned Properties</h6>
                    <a href="<?= BASE_URL; ?>manager/houses.php" class="btn btn-sm btn-link text-decoration-none">View All Assigned</a>
                </div>
                <div class="card-body">
                    <?php if (empty($myHouses)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-house-slash fs-1 d-block mb-2"></i>
                            You currently have no properties assigned by the administrator.
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($myHouses as $h): ?>
                                <div class="col-md-6 col-lg-3">
                                    <div class="border rounded-3 p-3 h-100 bg-body-tertiary">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="badge bg-secondary-subtle text-secondary font-monospace"><?= e($h['house_code']); ?></span>
                                            <span class="badge bg-primary-subtle text-primary"><?= e($h['category_name']); ?></span>
                                        </div>
                                        <h6 class="fw-bold mb-1 text-truncate"><?= e($h['house_name']); ?></h6>
                                        <p class="text-muted small mb-2"><i class="bi bi-geo-alt me-1 text-danger"></i><?= e($h['city']); ?></p>
                                        <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2">
                                            <div class="fw-bold text-primary"><?= format_currency($h['rent_price'], $currency); ?>/mo</div>
                                            <a href="<?= BASE_URL; ?>manager/house_edit.php?id=<?= (int)$h['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                                                Update
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tables: Recent Requests & Recent Payments -->
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card shadow-sm h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-file-earmark-check text-warning me-2"></i>Recent Rental Requests</h6>
                            <a href="<?= BASE_URL; ?>manager/rental_requests.php" class="btn btn-sm btn-link text-decoration-none">Manage Requests</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Tenant</th>
                                            <th>Property</th>
                                            <th>Status</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentRequests)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-4 text-muted">
                                                    <i class="bi bi-inbox opacity-50 fs-3 d-block mb-1"></i>
                                                    <span class="small">No rental requests for your houses.</span>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recentRequests as $req): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold"><?= e($req['user_name']); ?></div>
                                                        <small class="text-muted"><?= e($req['user_phone'] ?? $req['user_email']); ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold"><?= e($req['house_name']); ?></div>
                                                        <small class="text-muted"><?= e($req['city']); ?></small>
                                                    </td>
                                                    <td><?= status_badge($req['status']); ?></td>
                                                    <td class="text-end">
                                                        <a href="<?= BASE_URL; ?>manager/rental_requests.php" class="btn-action btn-action-view" title="Review Application">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card shadow-sm h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-receipt text-success me-2"></i>Recent Collections</h6>
                            <a href="<?= BASE_URL; ?>manager/payments.php" class="btn btn-sm btn-link text-decoration-none">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>House</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentPayments)): ?>
                                            <tr><td colspan="3" class="text-center py-4 text-muted">No collections recorded yet.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($recentPayments as $pay): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold text-truncate" style="max-width: 140px;"><?= e($pay['house_name']); ?></div>
                                                        <small class="badge bg-light text-dark border"><?= e($pay['payment_method']); ?></small>
                                                    </td>
                                                    <td class="fw-bold text-success"><?= format_currency($pay['amount'], $currency); ?></td>
                                                    <td class="small text-muted"><?= format_date($pay['payment_date'], 'd M'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
