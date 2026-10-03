<?php
/**
 * Admin Reports Module
 * Reports: Total Revenue, Occupancy Rate, Monthly Collections, Vacant Houses
 * Export: PDF (Print), Excel (CSV)
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$currency = get_setting($pdo, 'currency', '$');
$activeReport = trim($_GET['type'] ?? 'revenue'); // revenue, occupancy, collections, vacant

// 1. Total Revenue Data (Per House)
$revenueData = $pdo->query("
    SELECT h.id, h.house_name, h.house_code, h.city, c.category_name, h.rent_price,
           COUNT(p.id) as payment_count,
           COALESCE(SUM(p.amount), 0) as total_collected
    FROM houses h
    JOIN categories c ON h.category_id = c.id
    LEFT JOIN payments p ON h.id = p.house_id
    GROUP BY h.id
    ORDER BY total_collected DESC
")->fetchAll();

$overallRevenue = array_sum(array_column($revenueData, 'total_collected'));

// 2. Occupancy Rate Data
$occupancyData = $pdo->query("
    SELECT h.id, h.house_name, h.house_code, h.city, c.category_name,
           h.total_apartments, h.occupied_apartments, h.vacant_apartments,
           mgr.name as manager_name
    FROM houses h
    JOIN categories c ON h.category_id = c.id
    LEFT JOIN users mgr ON h.manager_id = mgr.id
    ORDER BY h.occupied_apartments DESC
")->fetchAll();

$totalAptsAll = (int)array_sum(array_column($occupancyData, 'total_apartments'));
$totalOccAll  = (int)array_sum(array_column($occupancyData, 'occupied_apartments'));
$totalVacAll  = (int)array_sum(array_column($occupancyData, 'vacant_apartments'));
$globalOccupancyRate = $totalAptsAll > 0 ? round(($totalOccAll / $totalAptsAll) * 100, 1) : 0;

// 3. Monthly Collections Data
$monthlyData = $pdo->query("
    SELECT DATE_FORMAT(payment_date, '%Y-%m') as month_period,
           COUNT(id) as total_txns,
           COALESCE(SUM(amount), 0) as month_total,
           COALESCE(SUM(CASE WHEN payment_method = 'EVC Plus' THEN amount ELSE 0 END), 0) as evc_total,
           COALESCE(SUM(CASE WHEN payment_method = 'Zaad' THEN amount ELSE 0 END), 0) as zaad_total,
           COALESCE(SUM(CASE WHEN payment_method = 'Sahal' THEN amount ELSE 0 END), 0) as sahal_total,
           COALESCE(SUM(CASE WHEN payment_method = 'Bank Transfer' THEN amount ELSE 0 END), 0) as bank_total,
           COALESCE(SUM(CASE WHEN payment_method = 'Cash' THEN amount ELSE 0 END), 0) as cash_total
    FROM payments
    GROUP BY month_period
    ORDER BY month_period DESC
")->fetchAll();

// 4. Vacant Houses Data
$vacantData = $pdo->query("
    SELECT h.id, h.house_name, h.house_code, h.city, h.address, c.category_name,
           h.total_apartments, h.vacant_apartments, h.rent_price,
           (h.vacant_apartments * h.rent_price) as monthly_loss,
           mgr.name as manager_name, mgr.phone as manager_phone
    FROM houses h
    JOIN categories c ON h.category_id = c.id
    LEFT JOIN users mgr ON h.manager_id = mgr.id
    WHERE h.vacant_apartments > 0
    ORDER BY h.vacant_apartments DESC
")->fetchAll();

$totalVacantUnits = array_sum(array_column($vacantData, 'vacant_apartments'));
$totalPotentialLoss = array_sum(array_column($vacantData, 'monthly_loss'));

// Log report generation in reports table
try {
    $pdo->prepare("INSERT INTO reports (report_type, generated_by, parameters) VALUES (?, ?, ?)")
        ->execute([$activeReport, current_user_id(), json_encode(['date' => date('Y-m-d H:i:s')])]);
} catch (Exception $e) {
    // Ignore logging error
}

$pageTitle = 'Admin Reports - HomeHub';
$pageHeading = 'Analytical Reports';
$pageSubtitle = 'Revenue collections, occupancy rates, monthly performance and vacancy analytics';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <main class="content-wrapper">
            <?= display_flash(); ?>

            <!-- Print Header Banner (Only visible during printing) -->
            <div class="print-only mb-4 text-center border-bottom pb-3">
                <h2 class="fw-bold">HOMEHUB MOGADISHU PROPERTY MANAGEMENT</h2>
                <h4 class="text-uppercase"><?= e(strtoupper($activeReport)); ?> REPORT</h4>
                <p class="small text-muted mb-0">Generated on: <?= date('d M Y, h:i A'); ?> | Admin: <?= e($currentUser['name'] ?? 'Admin'); ?></p>
            </div>

            <!-- Report Navigation & Export Controls -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 no-print">
                <div class="btn-group" role="group">
                    <a href="<?= BASE_URL; ?>admin/reports.php?type=revenue" class="btn btn-sm <?= $activeReport === 'revenue' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                        <i class="bi bi-cash-coin me-1"></i>Total Revenue
                    </a>
                    <a href="<?= BASE_URL; ?>admin/reports.php?type=occupancy" class="btn btn-sm <?= $activeReport === 'occupancy' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                        <i class="bi bi-pie-chart me-1"></i>Occupancy Rate
                    </a>
                    <a href="<?= BASE_URL; ?>admin/reports.php?type=collections" class="btn btn-sm <?= $activeReport === 'collections' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                        <i class="bi bi-calendar3 me-1"></i>Monthly Collections
                    </a>
                    <a href="<?= BASE_URL; ?>admin/reports.php?type=vacant" class="btn btn-sm <?= $activeReport === 'vacant' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                        <i class="bi bi-door-open me-1"></i>Vacant Houses
                    </a>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i>Export PDF / Print
                    </button>
                    <button type="button" class="btn btn-success btn-sm" onclick="exportTableToCSV('activeReportTable', '<?= $activeReport; ?>_report.csv')">
                        <i class="bi bi-file-earmark-excel me-1"></i>Export Excel (CSV)
                    </button>
                </div>
            </div>

            <!-- REPORT 1: TOTAL REVENUE -->
            <?php if ($activeReport === 'revenue'): ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-6 col-lg-4">
                        <div class="card p-3 shadow-sm border-0 border-start border-primary border-4">
                            <small class="text-muted fw-bold text-uppercase">Total Property Revenue</small>
                            <h3 class="fw-bolder text-primary mb-0 mt-1"><?= format_currency($overallRevenue, $currency); ?></h3>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="card p-3 shadow-sm border-0 border-start border-success border-4">
                            <small class="text-muted fw-bold text-uppercase">Properties with Collections</small>
                            <h3 class="fw-bolder text-success mb-0 mt-1"><?= count(array_filter($revenueData, fn($r) => (float)$r['total_collected'] > 0)); ?> / <?= count($revenueData); ?></h3>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-cash-stack text-primary me-2"></i>Total Revenue by Property</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="activeReportTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>House Name</th>
                                        <th>Code</th>
                                        <th>Category</th>
                                        <th>City</th>
                                        <th>Monthly Rent</th>
                                        <th>Transactions</th>
                                        <th class="text-end">Total Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($revenueData as $i => $r): ?>
                                        <tr>
                                            <td><?= $i + 1; ?></td>
                                            <td class="fw-bold"><?= e($r['house_name']); ?></td>
                                            <td class="font-monospace text-muted"><?= e($r['house_code']); ?></td>
                                            <td><span class="badge bg-secondary-subtle text-secondary"><?= e($r['category_name']); ?></span></td>
                                            <td><?= e($r['city']); ?></td>
                                            <td><?= format_currency($r['rent_price'], $currency); ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= (int)$r['payment_count']; ?> payments</span></td>
                                            <td class="text-end fw-bold fs-6 text-success"><?= format_currency($r['total_collected'], $currency); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold bg-light">
                                        <td colspan="7" class="text-end">OVERALL TOTAL:</td>
                                        <td class="text-end text-success fs-5"><?= format_currency($overallRevenue, $currency); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

            <!-- REPORT 2: OCCUPANCY RATE -->
            <?php elseif ($activeReport === 'occupancy'): ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card p-3 shadow-sm border-0 border-start border-primary border-4">
                            <small class="text-muted fw-bold text-uppercase">Total Portfolio Units</small>
                            <h3 class="fw-bolder text-primary mb-0 mt-1"><?= $totalAptsAll; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3 shadow-sm border-0 border-start border-success border-4">
                            <small class="text-muted fw-bold text-uppercase">Total Occupied</small>
                            <h3 class="fw-bolder text-success mb-0 mt-1"><?= $totalOccAll; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3 shadow-sm border-0 border-start border-warning border-4">
                            <small class="text-muted fw-bold text-uppercase">Total Vacant</small>
                            <h3 class="fw-bolder text-warning mb-0 mt-1"><?= $totalVacAll; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3 shadow-sm border-0 border-start border-info border-4">
                            <small class="text-muted fw-bold text-uppercase">Global Occupancy Rate</small>
                            <h3 class="fw-bolder text-info mb-0 mt-1"><?= $globalOccupancyRate; ?>%</h3>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-pie-chart text-success me-2"></i>Occupancy Status by Property</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="activeReportTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>House Name</th>
                                        <th>City</th>
                                        <th>Manager</th>
                                        <th>Total Units</th>
                                        <th>Occupied</th>
                                        <th>Vacant</th>
                                        <th>Occupancy %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($occupancyData as $i => $o): ?>
                                        <?php 
                                            $tot = (int)$o['total_apartments'];
                                            $occ = (int)$o['occupied_apartments'];
                                            $rate = $tot > 0 ? round(($occ / $tot) * 100, 1) : 0;
                                        ?>
                                        <tr>
                                            <td><?= $i + 1; ?></td>
                                            <td class="fw-bold"><?= e($o['house_name']); ?> <small class="text-muted font-monospace">(<?= e($o['house_code']); ?>)</small></td>
                                            <td><?= e($o['city']); ?></td>
                                            <td><?= e($o['manager_name'] ?? 'Unassigned'); ?></td>
                                            <td class="fw-semibold"><?= $tot; ?></td>
                                            <td><span class="badge bg-success-subtle text-success"><?= $occ; ?> Occupied</span></td>
                                            <td><span class="badge bg-warning-subtle text-warning"><?= (int)$o['vacant_apartments']; ?> Vacant</span></td>
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

            <!-- REPORT 3: MONTHLY COLLECTIONS -->
            <?php elseif ($activeReport === 'collections'): ?>
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-calendar-check text-primary me-2"></i>Monthly Collections Breakdown by Channel</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="activeReportTable">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th>Txns</th>
                                        <th>EVC Plus</th>
                                        <th>Zaad</th>
                                        <th>Sahal</th>
                                        <th>Bank Transfer</th>
                                        <th>Cash</th>
                                        <th class="text-end">Total Month</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($monthlyData)): ?>
                                        <tr><td colspan="8" class="text-center py-4 text-muted">No monthly collection records found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($monthlyData as $m): ?>
                                            <tr>
                                                <td class="fw-bold"><?= date('F Y', strtotime($m['month_period'] . '-01')); ?></td>
                                                <td><span class="badge bg-secondary-subtle text-secondary"><?= (int)$m['total_txns']; ?></span></td>
                                                <td class="text-primary"><?= format_currency($m['evc_total'], $currency); ?></td>
                                                <td class="text-success"><?= format_currency($m['zaad_total'], $currency); ?></td>
                                                <td class="text-info"><?= format_currency($m['sahal_total'], $currency); ?></td>
                                                <td><?= format_currency($m['bank_total'], $currency); ?></td>
                                                <td><?= format_currency($m['cash_total'], $currency); ?></td>
                                                <td class="text-end fw-bold text-success fs-6"><?= format_currency($m['month_total'], $currency); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <!-- REPORT 4: VACANT HOUSES -->
            <?php elseif ($activeReport === 'vacant'): ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-6 col-lg-4">
                        <div class="card p-3 shadow-sm border-0 border-start border-warning border-4">
                            <small class="text-muted fw-bold text-uppercase">Total Available Vacant Units</small>
                            <h3 class="fw-bolder text-warning mb-0 mt-1"><?= $totalVacantUnits; ?> Units</h3>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="card p-3 shadow-sm border-0 border-start border-danger border-4">
                            <small class="text-muted fw-bold text-uppercase">Monthly Potential Unrealized Rent</small>
                            <h3 class="fw-bolder text-danger mb-0 mt-1"><?= format_currency($totalPotentialLoss, $currency); ?></h3>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-door-open-fill text-warning me-2"></i>Properties with Vacant Units</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="activeReportTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Property Name</th>
                                        <th>City & Address</th>
                                        <th>Category</th>
                                        <th>Manager</th>
                                        <th>Vacant / Total</th>
                                        <th>Rent / Unit</th>
                                        <th class="text-end">Unrealized Monthly</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($vacantData)): ?>
                                        <tr><td colspan="8" class="text-center py-4 text-success fw-bold">Awesome! All properties are 100% fully occupied!</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($vacantData as $i => $v): ?>
                                            <tr>
                                                <td><?= $i + 1; ?></td>
                                                <td>
                                                    <div class="fw-bold"><?= e($v['house_name']); ?></div>
                                                    <span class="badge bg-secondary-subtle text-secondary font-monospace"><?= e($v['house_code']); ?></span>
                                                </td>
                                                <td>
                                                    <div><?= e($v['city']); ?></div>
                                                    <small class="text-muted"><?= e($v['address']); ?></small>
                                                </td>
                                                <td><span class="badge bg-primary-subtle text-primary"><?= e($v['category_name']); ?></span></td>
                                                <td>
                                                    <div><?= e($v['manager_name'] ?? 'Unassigned'); ?></div>
                                                    <small class="text-muted"><?= e($v['manager_phone'] ?? ''); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning-subtle text-warning fs-6">
                                                        <?= (int)$v['vacant_apartments']; ?> / <?= (int)$v['total_apartments']; ?> Vacant
                                                    </span>
                                                </td>
                                                <td class="fw-semibold"><?= format_currency($v['rent_price'], $currency); ?></td>
                                                <td class="text-end fw-bold text-danger"><?= format_currency($v['monthly_loss'], $currency); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold bg-light">
                                        <td colspan="7" class="text-end">TOTAL POTENTIAL UNREALIZED REVENUE:</td>
                                        <td class="text-end text-danger fs-5"><?= format_currency($totalPotentialLoss, $currency); ?></td>
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
