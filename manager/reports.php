<?php
/**
 * Manager Reports Module
 * Reports: Assigned Houses, Occupancy Report, Revenue Report
 * Export: PDF (Print), Excel (CSV)
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
$activeReport = trim($_GET['type'] ?? 'assigned'); // assigned, occupancy, revenue

// 1. Assigned Houses Data
$stmtAssigned = $pdo->prepare("
    SELECT h.*, c.category_name,
           (SELECT COUNT(*) FROM rental_requests r WHERE r.house_id = h.id) as total_requests,
           (SELECT COUNT(*) FROM payments p WHERE p.house_id = h.id) as payment_count,
           (SELECT COALESCE(SUM(amount), 0) FROM payments p WHERE p.house_id = h.id) as total_rev
    FROM houses h
    JOIN categories c ON h.category_id = c.id
    WHERE h.manager_id = ?
    ORDER BY h.id DESC
");
$stmtAssigned->execute([$managerId]);
$assignedHouses = $stmtAssigned->fetchAll();

// 2. Occupancy Data for Manager Houses
$totalApts = array_sum(array_column($assignedHouses, 'total_apartments'));
$totalOcc  = array_sum(array_column($assignedHouses, 'occupied_apartments'));
$totalVac  = array_sum(array_column($assignedHouses, 'vacant_apartments'));
$occupancyPct = $totalApts > 0 ? round(($totalOcc / $totalApts) * 100, 1) : 0;

// 3. Revenue Report for Manager Houses
$stmtRev = $pdo->prepare("
    SELECT p.*, h.house_name, h.house_code, h.city, u.name as tenant_name
    FROM payments p
    JOIN houses h ON p.house_id = h.id
    LEFT JOIN users u ON p.user_id = u.id
    WHERE h.manager_id = ?
    ORDER BY p.payment_date DESC
");
$stmtRev->execute([$managerId]);
$revenueLedger = $stmtRev->fetchAll();
$totalRevenue = array_sum(array_column($revenueLedger, 'amount'));

$pageTitle = 'Manager Reports - HomeHub';
$pageHeading = 'Manager Reports';
$pageSubtitle = 'Supervision analytics for assigned properties, occupancy metrics, and rent collections';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once __DIR__ . '/../includes/manager_sidebar.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <main class="content-wrapper">
            <?= display_flash(); ?>

            <!-- Print Header Banner -->
            <div class="print-only mb-4 text-center border-bottom pb-3">
                <h2 class="fw-bold">HOMEHUB MOGADISHU PROPERTY MANAGEMENT</h2>
                <h4 class="text-uppercase"><?= e(strtoupper($activeReport)); ?> REPORT</h4>
                <p class="small text-muted mb-0">Supervisor: <?= e($currentUser['name'] ?? 'Manager'); ?> | Date: <?= date('d M Y, h:i A'); ?></p>
            </div>

            <!-- Report Navigation & Export Controls -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 no-print">
                <div class="btn-group" role="group">
                    <a href="<?= BASE_URL; ?>manager/reports.php?type=assigned" class="btn btn-sm <?= $activeReport === 'assigned' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                        <i class="bi bi-houses me-1"></i>Assigned Houses
                    </a>
                    <a href="<?= BASE_URL; ?>manager/reports.php?type=occupancy" class="btn btn-sm <?= $activeReport === 'occupancy' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                        <i class="bi bi-pie-chart me-1"></i>Occupancy Report
                    </a>
                    <a href="<?= BASE_URL; ?>manager/reports.php?type=revenue" class="btn btn-sm <?= $activeReport === 'revenue' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                        <i class="bi bi-cash-stack me-1"></i>Revenue Report
                    </a>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i>Export PDF / Print
                    </button>
                    <button type="button" class="btn btn-success btn-sm" onclick="exportTableToCSV('managerReportTable', '<?= $activeReport; ?>_report.csv')">
                        <i class="bi bi-file-earmark-excel me-1"></i>Export Excel (CSV)
                    </button>
                </div>
            </div>

            <!-- REPORT 1: ASSIGNED HOUSES -->
            <?php if ($activeReport === 'assigned'): ?>
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-houses text-primary me-2"></i>Assigned Properties Portfolio Overview</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="managerReportTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>House Name</th>
                                        <th>Code</th>
                                        <th>Category</th>
                                        <th>City & Address</th>
                                        <th>Monthly Rent</th>
                                        <th>Units (Occ / Vac / Tot)</th>
                                        <th class="text-end">Revenue Generated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($assignedHouses)): ?>
                                        <tr><td colspan="8" class="text-center py-4 text-muted">No assigned properties found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($assignedHouses as $i => $h): ?>
                                            <tr>
                                                <td><?= $i + 1; ?></td>
                                                <td class="fw-bold"><?= e($h['house_name']); ?></td>
                                                <td class="font-monospace text-muted"><?= e($h['house_code']); ?></td>
                                                <td><span class="badge bg-secondary-subtle text-secondary"><?= e($h['category_name']); ?></span></td>
                                                <td><?= e($h['city']); ?>, <small class="text-muted"><?= e($h['address']); ?></small></td>
                                                <td class="fw-semibold"><?= format_currency($h['rent_price'], $currency); ?></td>
                                                <td>
                                                    <span class="badge bg-success-subtle text-success"><?= (int)$h['occupied_apartments']; ?> Occ</span>
                                                    <span class="badge bg-warning-subtle text-warning"><?= (int)$h['vacant_apartments']; ?> Vac</span>
                                                    <span class="badge bg-secondary-subtle"><?= (int)$h['total_apartments']; ?> Tot</span>
                                                </td>
                                                <td class="text-end fw-bold text-success"><?= format_currency($h['total_rev'], $currency); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <!-- REPORT 2: OCCUPANCY REPORT -->
            <?php elseif ($activeReport === 'occupancy'): ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card p-3 shadow-sm border-0 border-start border-primary border-4">
                            <small class="text-muted fw-bold text-uppercase">Total Units Managed</small>
                            <h3 class="fw-bolder text-primary mb-0 mt-1"><?= $totalApts; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3 shadow-sm border-0 border-start border-success border-4">
                            <small class="text-muted fw-bold text-uppercase">Occupied Units</small>
                            <h3 class="fw-bolder text-success mb-0 mt-1"><?= $totalOcc; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3 shadow-sm border-0 border-start border-warning border-4">
                            <small class="text-muted fw-bold text-uppercase">Vacant Units</small>
                            <h3 class="fw-bolder text-warning mb-0 mt-1"><?= $totalVac; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3 shadow-sm border-0 border-start border-info border-4">
                            <small class="text-muted fw-bold text-uppercase">Occupancy Rate</small>
                            <h3 class="fw-bolder text-info mb-0 mt-1"><?= $occupancyPct; ?>%</h3>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-pie-chart text-success me-2"></i>Occupancy Breakdown per Property</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="managerReportTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Property Name</th>
                                        <th>City</th>
                                        <th>Total Apartments</th>
                                        <th>Occupied</th>
                                        <th>Vacant</th>
                                        <th>Occupancy Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignedHouses as $i => $h): ?>
                                        <?php 
                                            $tot = (int)$h['total_apartments'];
                                            $occ = (int)$h['occupied_apartments'];
                                            $rate = $tot > 0 ? round(($occ / $tot) * 100, 1) : 0;
                                        ?>
                                        <tr>
                                            <td><?= $i + 1; ?></td>
                                            <td class="fw-bold"><?= e($h['house_name']); ?> (<?= e($h['house_code']); ?>)</td>
                                            <td><?= e($h['city']); ?></td>
                                            <td class="fw-semibold"><?= $tot; ?></td>
                                            <td><span class="badge bg-success-subtle text-success"><?= $occ; ?> Occupied</span></td>
                                            <td><span class="badge bg-warning-subtle text-warning"><?= (int)$h['vacant_apartments']; ?> Vacant</span></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1" style="height: 6px;">
                                                        <div class="progress-bar bg-success" style="width: <?= $rate; ?>%"></div>
                                                    </div>
                                                    <span class="fw-bold small"><?= $rate; ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <!-- REPORT 3: REVENUE REPORT -->
            <?php elseif ($activeReport === 'revenue'): ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-6 col-lg-4">
                        <div class="card p-3 shadow-sm border-0 border-start border-success border-4">
                            <small class="text-muted fw-bold text-uppercase">Total Collections Handled</small>
                            <h3 class="fw-bolder text-success mb-0 mt-1"><?= format_currency($totalRevenue, $currency); ?></h3>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="card p-3 shadow-sm border-0 border-start border-primary border-4">
                            <small class="text-muted fw-bold text-uppercase">Total Transactions</small>
                            <h3 class="fw-bolder text-primary mb-0 mt-1"><?= count($revenueLedger); ?></h3>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-cash-stack text-success me-2"></i>Revenue Collections Detailed Ledger</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="managerReportTable">
                                <thead>
                                    <tr>
                                        <th>Ref #</th>
                                        <th>House</th>
                                        <th>Tenant</th>
                                        <th>Date</th>
                                        <th>Method</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($revenueLedger)): ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">No revenue collected yet for your assigned houses.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($revenueLedger as $r): ?>
                                            <tr>
                                                <td class="font-monospace text-muted small"><?= !empty($r['reference_no']) ? e($r['reference_no']) : '#' . (int)$r['id']; ?></td>
                                                <td class="fw-bold"><?= e($r['house_name']); ?> <small class="text-muted">(<?= e($r['city']); ?>)</small></td>
                                                <td><?= e($r['tenant_name'] ?? 'Direct Tenant'); ?></td>
                                                <td><?= format_date($r['payment_date']); ?></td>
                                                <td><span class="badge bg-primary-subtle text-primary"><?= e($r['payment_method']); ?></span></td>
                                                <td class="text-end fw-bold text-success fs-6"><?= format_currency($r['amount'], $currency); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold bg-light">
                                        <td colspan="5" class="text-end">TOTAL REVENUE:</td>
                                        <td class="text-end text-success fs-5"><?= format_currency($totalRevenue, $currency); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
