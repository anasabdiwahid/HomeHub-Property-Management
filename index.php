<?php
/**
 * Public Landing Page
 * HomeHub Property Management System - Somalia
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Fetch categories for search filter
$categoriesStmt = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC");
$categories = $categoriesStmt->fetchAll();

// Fetch Mogadishu districts
$districts = mogadishu_districts();

// Search & Filter parameters
$search = trim($_GET['search'] ?? '');
$filterCity = trim($_GET['city'] ?? '');
$filterCategory = !empty($_GET['category']) ? (int)$_GET['category'] : 0;

$query = "SELECT h.*, c.category_name, u.name as manager_name 
          FROM houses h 
          JOIN categories c ON h.category_id = c.id 
          LEFT JOIN users u ON h.manager_id = u.id 
          WHERE 1=1";
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

$query .= " ORDER BY h.id DESC LIMIT 9";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$featuredHouses = $stmt->fetchAll();

// Aggregate stats
$stats = [
    'houses'     => $pdo->query("SELECT COUNT(*) FROM houses")->fetchColumn(),
    'units'      => $pdo->query("SELECT COALESCE(SUM(total_apartments), 0) FROM houses")->fetchColumn(),
    'vacant'     => $pdo->query("SELECT COALESCE(SUM(vacant_apartments), 0) FROM houses")->fetchColumn(),
    'districts'  => $pdo->query("SELECT COUNT(DISTINCT city) FROM houses")->fetchColumn(),
];

$pageTitle = 'HomeHub - Mogadishu Property Management & Rentals';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Public Top Navigation -->
<nav class="navbar navbar-expand-lg bg-body border-bottom sticky-top py-2 shadow-sm">
    <div class="container">
        <!-- Official Logo -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL; ?>">
            <img src="<?= BASE_URL; ?>assets/images/logo_clean.png" alt="HomeHub Logo" style="height: 48px; width: auto; object-fit: contain;">
        </a>

        <div class="d-flex align-items-center gap-2 order-lg-last">
            <!-- Dark mode toggle -->
            <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" onclick="toggleTheme()" title="Toggle Theme" aria-label="Toggle Theme">
                <i class="bi bi-moon-stars-fill theme-toggle-icon"></i>
            </button>

            <?php if (is_logged_in()): ?>
                <?php $user = current_user(); ?>
                <div class="dropdown">
                    <button class="btn btn-primary btn-sm rounded-pill px-3 py-2 dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle text-gold"></i>
                        <span><?= e($user['name']); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><span class="dropdown-header text-uppercase">Role: <?= e($user['role']); ?></span></li>
                        <?php if ($user['role'] === 'admin'): ?>
                            <li><a class="dropdown-item" href="<?= BASE_URL; ?>admin/index.php"><i class="bi bi-speedometer2 me-2 text-primary"></i>Admin Dashboard</a></li>
                        <?php elseif ($user['role'] === 'manager'): ?>
                            <li><a class="dropdown-item" href="<?= BASE_URL; ?>manager/index.php"><i class="bi bi-speedometer2 me-2 text-primary"></i>Manager Dashboard</a></li>
                        <?php else: ?>
                            <li><a class="dropdown-item" href="<?= BASE_URL; ?>user/index.php"><i class="bi bi-speedometer2 me-2 text-primary"></i>User Dashboard</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= BASE_URL; ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            <?php else: ?>
                <a href="<?= BASE_URL; ?>login.php" class="btn btn-outline-primary btn-sm px-3">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
                </a>
                <a href="<?= BASE_URL; ?>register.php" class="btn btn-gold btn-sm px-3 d-none d-sm-inline-block">
                    <i class="bi bi-person-plus me-1"></i>Register
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="py-5" style="background: radial-gradient(circle at 50% 10%, rgba(229, 169, 59, 0.14) 0%, rgba(16, 42, 69, 0.04) 50%, transparent 80%);">
    <div class="container py-4">
        <div class="row align-items-center justify-content-between g-5">
            <div class="col-lg-6">
                <div class="badge badge-gold px-3 py-2 rounded-pill mb-3">
                    <i class="bi bi-geo-alt-fill me-1 text-gold"></i> Mogadishu Only &bull; Degmooyinka Muqdisho
                </div>
                <h1 class="display-4 fw-bolder mb-3 text-main">
                    Modern Property Management & Rentals in Mogadishu
                </h1>
                <p class="lead text-muted mb-4">
                    Guryaha iyo dabaqyada kireysan ee ugu casrisan guud ahaan degmooyinka gobolka Banaadir (Muqdisho). Hel guri banaan, gudbi codsi, kireyso adigoo isticmaalaya EVC Plus ama xawaalad.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="#browse-section" class="btn btn-gold btn-lg px-4 shadow-sm">
                        <i class="bi bi-search me-2"></i>Daawo Guryaha Muqdisho
                    </a>
                    <a href="<?= BASE_URL; ?>login.php" class="btn btn-primary btn-lg px-4 shadow-sm">
                        <i class="bi bi-shield-lock me-2 text-gold"></i>Staff Portal
                    </a>
                </div>
            </div>

            <div class="col-lg-5">
                <!-- Search Card -->
                <div class="card shadow-lg border-0 p-3 p-md-4" style="border-top: 4px solid var(--hh-gold) !important;">
                    <div class="card-body">
                        <h4 class="card-title fw-bold mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-funnel-fill text-gold"></i> Raadi Guri Muqdisho ah
                        </h4>
                        <form method="GET" action="<?= BASE_URL; ?>#browse-section">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Magaca Guriga ama Aagga</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-body-tertiary"><i class="bi bi-search"></i></span>
                                    <input type="text" name="search" class="form-control" placeholder="e.g. Wadajir, KM4, Villa, Dabaq..." value="<?= e($search); ?>">
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold text-muted">Degmada (District)</label>
                                    <select name="city" class="form-select">
                                        <option value="">Dhammaan Degmooyinka</option>
                                        <?php foreach ($districts as $d): ?>
                                            <option value="<?= e($d); ?>" <?= $filterCity === $d ? 'selected' : ''; ?>><?= e($d); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold text-muted">Nooca (Category)</label>
                                    <select name="category" class="form-select">
                                        <option value="0">Dhammaan Noocyada</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= (int)$cat['id']; ?>" <?= $filterCategory === (int)$cat['id'] ? 'selected' : ''; ?>><?= e($cat['category_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                                <i class="bi bi-arrow-right-circle me-2 text-gold"></i>Raadi Guryaha
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Counter Bar -->
<section class="py-4 border-top border-bottom bg-body">
    <div class="container">
        <div class="row g-4 text-center">
            <div class="col-6 col-md-3">
                <div class="p-2">
                    <div class="fs-2 fw-bolder text-primary"><?= (int)$stats['houses']; ?>+</div>
                    <div class="text-muted small fw-semibold text-uppercase">Guryaha Muqdisho</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2">
                    <div class="fs-2 fw-bolder text-success"><?= (int)$stats['units']; ?></div>
                    <div class="text-muted small fw-semibold text-uppercase">Wadarta Qeybaha (Units)</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2">
                    <div class="fs-2 fw-bolder text-gold"><?= (int)$stats['vacant']; ?></div>
                    <div class="text-muted small fw-semibold text-uppercase">Kireysi Banaan</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2">
                    <div class="fs-2 fw-bolder text-info">17</div>
                    <div class="text-muted small fw-semibold text-uppercase">Degmooyinka Muqdisho</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured / Available Properties Section -->
<section id="browse-section" class="py-5">
    <div class="container py-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4 gap-2">
            <div>
                <span class="badge badge-gold mb-2">Verified Real Estate</span>
                <h2 class="fw-bold mb-1">Featured Available Properties</h2>
                <p class="text-muted mb-0">Browse modern apartments, commercial plazas, and standalone villas</p>
            </div>
            <?php if (!empty($search) || !empty($filterCity) || $filterCategory > 0): ?>
                <a href="<?= BASE_URL; ?>#browse-section" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle me-1"></i>Clear Filters
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($featuredHouses)): ?>
            <div class="text-center py-5 card border-dashed">
                <div class="card-body">
                    <i class="bi bi-house-slash text-muted" style="font-size: 3.5rem;"></i>
                    <h5 class="mt-3 fw-bold">No properties match your search criteria</h5>
                    <p class="text-muted">Try adjusting your filters or search keywords.</p>
                    <a href="<?= BASE_URL; ?>#browse-section" class="btn btn-outline-primary btn-sm">Reset Search</a>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($featuredHouses as $h): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 card-hover overflow-hidden">
                            <div class="property-card-img-wrapper">
                                <?php 
                                    $imgUrl = !empty($h['image']) && file_exists(UPLOAD_DIR . 'houses/' . $h['image'])
                                        ? UPLOAD_URL . 'houses/' . e($h['image'])
                                        : 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=700&q=80';
                                ?>
                                <img src="<?= $imgUrl; ?>" class="property-card-img" alt="<?= e($h['house_name']); ?>">
                                <span class="property-badge-city"><i class="bi bi-geo-alt me-1"></i><?= e($h['city']); ?></span>
                                <span class="property-badge-category"><?= e($h['category_name']); ?></span>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="text-muted font-monospace"><?= e($h['house_code']); ?></small>
                                    <span class="badge <?= (int)$h['vacant_apartments'] > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle'; ?>">
                                        <?= (int)$h['vacant_apartments'] > 0 ? (int)$h['vacant_apartments'] . ' Units Vacant' : 'Fully Occupied'; ?>
                                    </span>
                                </div>
                                <h5 class="card-title fw-bold text-truncate mb-2"><?= e($h['house_name']); ?></h5>
                                <p class="text-muted small mb-3 flex-grow-1" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?= e($h['description'] ?? 'No description available.'); ?>
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-auto">
                                    <div>
                                        <div class="property-price"><?= format_currency($h['rent_price']); ?></div>
                                        <small class="text-muted">per month</small>
                                    </div>
                                    <div>
                                        <?php if (is_logged_in() && current_user_role() === 'user'): ?>
                                            <a href="<?= BASE_URL; ?>user/house_details.php?id=<?= (int)$h['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                View & Rent <i class="bi bi-arrow-right ms-1"></i>
                                            </a>
                                        <?php elseif (is_logged_in()): ?>
                                            <a href="<?= BASE_URL . current_user_role(); ?>/index.php" class="btn btn-outline-secondary btn-sm">
                                                Dashboard <i class="bi bi-speedometer2 ms-1"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= BASE_URL; ?>login.php" class="btn btn-primary btn-sm">
                                                Rent Now <i class="bi bi-box-arrow-in-right ms-1 text-gold"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Features Section -->
<section class="py-5 bg-body-tertiary">
    <div class="container py-4">
        <div class="text-center max-w-700 mx-auto mb-5">
            <span class="badge badge-gold mb-2">System Advantages</span>
            <h2 class="fw-bold">Why Property Owners & Tenants Choose HomeHub</h2>
            <p class="text-muted">Built specifically for the real estate landscape across the districts of Mogadishu (Banaadir).</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm p-3 border-top border-4" style="border-top-color: var(--hh-gold) !important;">
                    <div class="card-body">
                        <div class="rounded-3 icon-gold d-inline-flex p-3 fs-3 mb-3">
                            <i class="bi bi-phone"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Somali Mobile Payments</h5>
                        <p class="text-muted small">Seamless rent tracking supporting EVC Plus, Zaad Service, Sahal, and local bank transfers with reference validation.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm p-3 border-top border-4" style="border-top-color: var(--hh-primary) !important;">
                    <div class="card-body">
                        <div class="rounded-3 icon-primary d-inline-flex p-3 fs-3 mb-3">
                            <i class="bi bi-pie-chart"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Automated Occupancy Tracking</h5>
                        <p class="text-muted small">Real-time statistics on occupied vs. vacant apartments, tenant turnover, and monthly collection ratios for property managers.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm p-3 border-top border-4" style="border-top-color: var(--hh-gold) !important;">
                    <div class="card-body">
                        <div class="rounded-3 icon-gold d-inline-flex p-3 fs-3 mb-3">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Role-Based Security</h5>
                        <p class="text-muted small">Independent portals for Administrators, Assigned Property Managers, and Tenants with complete RBAC and data privacy.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="bg-body border-top py-5">
    <div class="container">
        <div class="row g-4 justify-content-between mb-4">
            <div class="col-lg-4">
                <div class="mb-3">
                    <img src="<?= BASE_URL; ?>assets/images/logo_clean.png" alt="HomeHub Logo" style="height: 50px; width: auto; object-fit: contain;">
                </div>
                <p class="text-muted small mb-3">
                    The complete, modern property management solution in Somalia. Empowering owners, managers, and tenants with transparent rental workflows.
                </p>
                <div class="text-muted small">
                    <i class="bi bi-geo-alt me-1 text-gold"></i> Maka Al Mukarama Road, Hodan, Mogadishu, Somalia<br>
                    <i class="bi bi-telephone me-1 text-gold"></i> +252 61 555 4321<br>
                    <i class="bi bi-envelope me-1 text-gold"></i> contact@homehub.so
                </div>
            </div>

            <div class="col-6 col-md-3 col-lg-2">
                <h6 class="fw-bold mb-3 text-uppercase small text-muted">Quick Links</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                    <li><a href="<?= BASE_URL; ?>" class="text-muted">Home</a></li>
                    <li><a href="<?= BASE_URL; ?>#browse-section" class="text-muted">Available Houses</a></li>
                    <li><a href="<?= BASE_URL; ?>login.php" class="text-muted">Portal Login</a></li>
                    <li><a href="<?= BASE_URL; ?>register.php" class="text-muted">Register Tenant</a></li>
                </ul>
            </div>

            <div class="col-6 col-md-3 col-lg-2">
                <h6 class="fw-bold mb-3 text-uppercase small text-muted">Degmooyinka Muqdisho</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                    <li><a href="<?= BASE_URL; ?>?city=Hodan#browse-section" class="text-muted">Hodan</a></li>
                    <li><a href="<?= BASE_URL; ?>?city=Wadajir#browse-section" class="text-muted">Wadajir</a></li>
                    <li><a href="<?= BASE_URL; ?>?city=Waaberi#browse-section" class="text-muted">Waaberi</a></li>
                    <li><a href="<?= BASE_URL; ?>?city=Cabdicasiis#browse-section" class="text-muted">Cabdicasiis</a></li>
                    <li><a href="<?= BASE_URL; ?>?city=Darusalaam#browse-section" class="text-muted">Darusalaam</a></li>
                    <li><a href="<?= BASE_URL; ?>?city=Xamar+Weyne#browse-section" class="text-muted">Xamar Weyne</a></li>
                </ul>
            </div>

            <div class="col-lg-3">
                <h6 class="fw-bold mb-3 text-uppercase small text-muted">System Portals</h6>
                <div class="d-flex flex-column gap-2">
                    <a href="<?= BASE_URL; ?>login.php" class="btn btn-outline-primary btn-sm text-start">
                        <i class="bi bi-shield-lock me-1"></i> Admin & Manager Sign In
                    </a>
                    <a href="<?= BASE_URL; ?>register.php" class="btn btn-gold btn-sm text-start">
                        <i class="bi bi-person-plus me-1"></i> Tenant Sign Up
                    </a>
                </div>
            </div>
        </div>

        <div class="border-top pt-3 d-flex flex-column flex-sm-row justify-content-between align-items-center text-muted small">
            <div>&copy; <?= date('Y'); ?> HomeHub Somalia Property Management. All rights reserved.</div>
            <div class="mt-2 mt-sm-0">
                <span>Designed with HTML5, CSS3, Bootstrap 5, PHP 8 & MySQL</span>
            </div>
        </div>
    </div>
</footer>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
