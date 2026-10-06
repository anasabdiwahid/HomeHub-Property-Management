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
          WHERE h.status = 'active'";
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
    'houses'     => $pdo->query("SELECT COUNT(*) FROM houses WHERE status = 'active'")->fetchColumn(),
    'units'      => $pdo->query("SELECT COALESCE(SUM(total_apartments), 0) FROM houses WHERE status = 'active'")->fetchColumn(),
    'vacant'     => $pdo->query("SELECT COALESCE(SUM(vacant_apartments), 0) FROM houses WHERE status = 'active'")->fetchColumn(),
    'districts'  => $pdo->query("SELECT COUNT(DISTINCT city) FROM houses WHERE status = 'active'")->fetchColumn(),
];

$pageTitle = 'Index';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Public Top Navigation -->
<nav class="navbar navbar-expand-lg bg-body border-bottom sticky-top py-2 shadow-sm public-navbar">
    <div class="container">
        <!-- Official Logo -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL; ?>">
            <img src="<?= BASE_URL; ?>assets/images/logo-dark.png" alt="HomeHub Logo" class="logo-theme-dark" style="height: 44px; width: auto; object-fit: contain;">
            <img src="<?= BASE_URL; ?>assets/images/logo-light.png" alt="HomeHub Logo" class="logo-theme-light" style="height: 44px; width: auto; object-fit: contain;">
        </a>

        <!-- Right Controls: Dark Mode, Auth & Mobile Toggler -->
        <div class="d-flex align-items-center gap-2 order-lg-last">
            <!-- Dark mode toggle -->
            <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" onclick="toggleTheme()" title="Toggle Theme" aria-label="Toggle Theme">
                <i class="bi bi-sun-fill theme-toggle-icon"></i>
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

            <!-- Mobile Hamburger Toggle Button -->
            <button class="navbar-toggler border-0 ms-1 p-1" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavbarContent" aria-controls="publicNavbarContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>

        <!-- Center Nav Items -->
        <div class="collapse navbar-collapse" id="publicNavbarContent">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-1 gap-lg-2">
                <li class="nav-item">
                    <a class="nav-link public-nav-link active" href="<?= BASE_URL; ?>">
                        <i class="bi bi-house-door me-1 text-gold"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link public-nav-link" href="<?= BASE_URL; ?>#browse-section">
                        <i class="bi bi-buildings me-1"></i> Available Houses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link public-nav-link" href="<?= BASE_URL; ?>#features-section">
                        <i class="bi bi-shield-check me-1"></i> Features
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link public-nav-link" href="<?= BASE_URL; ?>#about-section">
                        <i class="bi bi-info-circle me-1"></i> About Us
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link public-nav-link" href="<?= BASE_URL; ?>#contact-section">
                        <i class="bi bi-telephone me-1"></i> Contact Us
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero-section py-5 position-relative">
    <div class="container py-4">
        <div class="row align-items-center justify-content-between g-5">
            <div class="col-lg-6">
                <div class="badge badge-gold px-3 py-2 rounded-pill mb-3">
                    <i class="bi bi-house-door-fill me-1 text-gold"></i> HomeHub Index &bull; Mogadishu Property Hub
                </div>
                <h1 class="display-4 fw-bolder mb-3 text-main">
                    Modern Property Management & Rentals in Mogadishu
                </h1>
                <p class="lead text-muted mb-4">
                    The most modern residential and commercial rental properties across all districts of Mogadishu (Banaadir). Find vacant homes, submit rental inquiries, and manage tenancies with mobile payments and verified agreements.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="#browse-section" class="btn btn-gold btn-lg px-4 shadow-sm">
                        <i class="bi bi-search me-2"></i>Browse Mogadishu Properties
                    </a>
                    <a href="<?= BASE_URL; ?>login.php" class="btn btn-primary btn-lg px-4 shadow-sm">
                        <i class="bi bi-shield-lock me-2 text-gold"></i>Staff Portal
                    </a>
                </div>
            </div>

            <div class="col-lg-5">
                <!-- Search Card -->
                <div class="card hero-search-card shadow-lg p-3 p-md-4">
                    <div class="card-body">
                        <h4 class="card-title fw-bold mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-funnel-fill text-gold"></i> Find Properties in Mogadishu
                        </h4>
                        <form method="GET" action="<?= BASE_URL; ?>#browse-section">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Property Name or Area</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-body-tertiary"><i class="bi bi-search"></i></span>
                                    <input type="text" name="search" class="form-control" placeholder="e.g. Wadajir, KM4, Villa, Apartment..." value="<?= e($search); ?>">
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold text-muted">District</label>
                                    <select name="city" class="form-select">
                                        <option value="">All Districts</option>
                                        <?php foreach ($districts as $d): ?>
                                            <option value="<?= e($d); ?>" <?= $filterCity === $d ? 'selected' : ''; ?>><?= e($d); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold text-muted">Category</label>
                                    <select name="category" class="form-select">
                                        <option value="0">All Categories</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= (int)$cat['id']; ?>" <?= $filterCategory === (int)$cat['id'] ? 'selected' : ''; ?>><?= e($cat['category_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                                <i class="bi bi-arrow-right-circle me-2 text-gold"></i>Search Properties
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
                    <div class="text-muted small fw-semibold text-uppercase">Mogadishu Properties</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2">
                    <div class="fs-2 fw-bolder text-success"><?= (int)$stats['units']; ?></div>
                    <div class="text-muted small fw-semibold text-uppercase">Total Units</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2">
                    <div class="fs-2 fw-bolder text-gold"><?= (int)$stats['vacant']; ?></div>
                    <div class="text-muted small fw-semibold text-uppercase">Vacant Units</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2">
                    <div class="fs-2 fw-bolder text-info">17</div>
                    <div class="text-muted small fw-semibold text-uppercase">Mogadishu Districts</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured / Available Properties Section (Real Estate / Houses Background) -->
<section id="browse-section" class="properties-section py-5 position-relative">
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
            <div class="text-center py-5 card border-dashed shadow-sm">
                <div class="card-body py-4">
                    <i class="bi bi-houses text-muted opacity-50" style="font-size: 3.5rem;"></i>
                    <h5 class="mt-3 fw-bold">No Properties Listed Yet</h5>
                    <p class="text-muted small mb-3">All dummy records have been cleared. Sign in to your Admin portal to begin adding real properties.</p>
                    <a href="<?= BASE_URL; ?>login.php" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-box-arrow-in-right me-1 text-gold"></i>Staff Sign In
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-2 g-md-3 g-lg-4">
                <?php foreach ($featuredHouses as $h): ?>
                    <?php 
                        $imgUrl = !empty($h['image']) && file_exists(UPLOAD_DIR . 'houses/' . $h['image'])
                            ? UPLOAD_URL . 'houses/' . e($h['image'])
                            : 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=700&q=80';

                        $hDataJson = htmlspecialchars(json_encode([
                            'id'       => (int)$h['id'],
                            'name'     => $h['house_name'],
                            'code'     => $h['house_code'],
                            'category' => $h['category_name'],
                            'city'     => $h['city'],
                            'address'  => $h['address'],
                            'price'    => format_currency($h['rent_price']),
                            'total'    => (int)$h['total_apartments'],
                            'occupied' => (int)$h['occupied_apartments'],
                            'vacant'   => (int)$h['vacant_apartments'],
                            'desc'     => $h['description'] ?? '',
                            'image'    => $imgUrl,
                            'isUser'   => (is_logged_in() && current_user_role() === 'user'),
                            'rentUrl'  => BASE_URL . 'user/house_details.php?id=' . (int)$h['id'],
                            'loginUrl' => BASE_URL . 'login.php'
                        ]), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="col-6 col-md-6 col-lg-4">
                        <div class="card h-100 card-hover overflow-hidden shadow-sm border" style="cursor: pointer;" onclick="showHomeHouseModal(JSON.parse(this.dataset.hdata))" data-hdata="<?= $hDataJson; ?>">
                            <div class="property-card-img-wrapper position-relative">
                                <img src="<?= $imgUrl; ?>" class="property-card-img" alt="<?= e($h['house_name']); ?>">
                                <span class="property-badge-city"><i class="bi bi-geo-alt me-0.5"></i><?= e($h['city']); ?></span>
                                <span class="property-badge-category"><?= e($h['category_name']); ?></span>
                            </div>
                            <div class="card-body d-flex flex-column p-2 p-sm-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="text-muted font-monospace" style="font-size: 0.7rem;"><?= e($h['house_code']); ?></small>
                                    <span class="badge <?= (int)$h['vacant_apartments'] > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle'; ?>" style="font-size: 0.68rem; padding: 0.2rem 0.4rem;">
                                        <?= (int)$h['vacant_apartments'] > 0 ? (int)$h['vacant_apartments'] . ' Vac' : 'Full'; ?>
                                    </span>
                                </div>
                                <h6 class="card-title fw-bold text-truncate mb-1 fs-sm-5" title="<?= e($h['house_name']); ?>"><?= e($h['house_name']); ?></h6>

                                <!-- Apartments Occupancy Breakdown Box -->
                                <div class="bg-body-tertiary p-1.5 p-sm-2 rounded-3 border mb-2" onclick="event.stopPropagation();" style="font-size: 0.72rem;">
                                    <div class="d-flex justify-content-between align-items-center mb-0.5">
                                        <span class="text-muted"><i class="bi bi-buildings text-primary me-0.5"></i>Tot: <strong><?= (int)$h['total_apartments']; ?></strong></span>
                                        <?php if ((int)$h['vacant_apartments'] > 0): ?>
                                            <span class="text-success fw-semibold"><i class="bi bi-door-open me-0.5"></i><?= (int)$h['vacant_apartments']; ?> Vac</span>
                                        <?php else: ?>
                                            <span class="text-danger fw-semibold"><i class="bi bi-x-circle me-0.5"></i>Full</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex justify-content-between text-muted" style="font-size: 0.68rem;">
                                        <span><i class="bi bi-person-fill text-danger me-0.5"></i>Occ: <strong class="text-danger"><?= (int)$h['occupied_apartments']; ?></strong></span>
                                        <span><i class="bi bi-check2-circle text-success me-0.5"></i>Avail: <strong class="text-success"><?= (int)$h['vacant_apartments']; ?></strong></span>
                                    </div>
                                </div>

                                <p class="text-muted small mb-2 flex-grow-1 d-none d-sm--webkit-box" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 0.74rem;">
                                    <?= e($h['description'] ?? 'Modern property located in Mogadishu with full residential and commercial amenities.'); ?>
                                </p>
                                
                                <!-- Clean Price & Actions Section (Zero Overlap Guaranteed) -->
                                <div class="pt-2 pt-sm-3 border-top mt-auto" onclick="event.stopPropagation();">
                                    <div class="d-flex justify-content-between align-items-baseline mb-1.5 mb-sm-2">
                                        <div>
                                            <span class="property-price"><?= format_currency($h['rent_price']); ?></span>
                                            <small class="text-muted d-none d-sm-inline">/ mo</small>
                                        </div>
                                        <small class="text-muted text-truncate" style="font-size: 0.7rem; max-width: 60px;">
                                            <i class="bi bi-geo-alt-fill text-danger me-0.5"></i><?= e($h['city']); ?>
                                        </small>
                                    </div>
                                    <div class="row g-1 g-sm-2">
                                        <div class="col-6">
                                            <button type="button" class="btn btn-outline-secondary btn-sm w-100 py-1 px-1 fw-semibold d-flex align-items-center justify-content-center gap-1" style="font-size: 0.74rem;" onclick="showHomeHouseModal(JSON.parse(this.closest('.card').dataset.hdata))">
                                                <i class="bi bi-eye"></i> <span>Details</span>
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <?php if ((int)$h['vacant_apartments'] > 0): ?>
                                                <?php if (is_logged_in() && current_user_role() === 'user'): ?>
                                                    <a href="<?= BASE_URL; ?>user/house_details.php?id=<?= (int)$h['id']; ?>" class="btn btn-primary btn-sm w-100 py-1 px-1 fw-semibold d-flex align-items-center justify-content-center gap-1" style="font-size: 0.74rem;">
                                                        <span>Rent</span> <i class="bi bi-arrow-right"></i>
                                                    </a>
                                                <?php elseif (is_logged_in()): ?>
                                                    <a href="<?= BASE_URL . current_user_role(); ?>/index.php" class="btn btn-outline-primary btn-sm w-100 py-1 px-1 fw-semibold d-flex align-items-center justify-content-center" style="font-size: 0.74rem;">
                                                        Dash
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?= BASE_URL; ?>login.php" class="btn btn-primary btn-sm w-100 py-1 px-1 fw-semibold d-flex align-items-center justify-content-center gap-1" style="font-size: 0.74rem;">
                                                        <span>Rent</span> <i class="bi bi-arrow-right"></i>
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-danger btn-sm w-100 py-1 px-1 fw-semibold d-flex align-items-center justify-content-center gap-1 opacity-75" style="font-size: 0.74rem;" onclick="showHomeHouseModal(JSON.parse(this.closest('.card').dataset.hdata))">
                                                    <i class="bi bi-x-circle"></i> <span>Full</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
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

<!-- About Us Section -->
<section id="about-section" class="py-5 position-relative bg-body">
    <div class="container py-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="badge badge-gold px-3 py-2 rounded-pill mb-3">
                    <i class="bi bi-info-circle-fill me-1 text-gold"></i> About HomeHub
                </span>
                <h2 class="display-6 fw-bold mb-3 text-main">
                    Mogadishu’s Most Trusted Digital Property Platform
                </h2>
                <p class="lead text-muted mb-4">
                    HomeHub was engineered to modernize, simplify, and secure property leasing and estate management across all 17 districts of Mogadishu (Banaadir).
                </p>
                <p class="text-muted mb-4">
                    Whether you are an ambitious property owner seeking transparent occupancy tracking, an assigned manager supervising rental units, or a tenant searching for a modern verified home, HomeHub bridges every step with automated rent invoicing, seamless Somali mobile money records (EVC Plus, Zaad, Sahal), and instant digital agreements.
                </p>

                <!-- Value Highlights Grid (2 Columns Side-by-Side on Mobile & Desktop) -->
                <div class="row g-2 g-sm-3 mb-4">
                    <div class="col-6">
                        <div class="d-flex flex-column flex-sm-row align-items-start gap-2 gap-sm-3 p-2.5 p-sm-3 rounded-3 bg-body-tertiary border h-100">
                            <div class="rounded-3 icon-gold d-inline-flex p-2 fs-5 fs-sm-4 flex-shrink-0">
                                <i class="bi bi-patch-check-fill text-gold"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1 small fs-sm-6">100% Verified</h6>
                                <p class="text-muted mb-0" style="font-size: 0.74rem; line-height: 1.35;">Every house, unit, and lease agreement is legally documented.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex flex-column flex-sm-row align-items-start gap-2 gap-sm-3 p-2.5 p-sm-3 rounded-3 bg-body-tertiary border h-100">
                            <div class="rounded-3 icon-primary d-inline-flex p-2 fs-5 fs-sm-4 flex-shrink-0">
                                <i class="bi bi-wallet2 text-primary"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1 small fs-sm-6">Local Payments</h6>
                                <p class="text-muted mb-0" style="font-size: 0.74rem; line-height: 1.35;">Direct support for Somali mobile money & bank transfer references.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex flex-column flex-sm-row align-items-start gap-2 gap-sm-3 p-2.5 p-sm-3 rounded-3 bg-body-tertiary border h-100">
                            <div class="rounded-3 icon-success d-inline-flex p-2 fs-5 fs-sm-4 flex-shrink-0">
                                <i class="bi bi-speedometer2 text-success"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1 small fs-sm-6">Real-Time Data</h6>
                                <p class="text-muted mb-0" style="font-size: 0.74rem; line-height: 1.35;">Live occupancy tracking, automated invoices, and receipt verification.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex flex-column flex-sm-row align-items-start gap-2 gap-sm-3 p-2.5 p-sm-3 rounded-3 bg-body-tertiary border h-100">
                            <div class="rounded-3 icon-purple d-inline-flex p-2 fs-5 fs-sm-4 flex-shrink-0">
                                <i class="bi bi-shield-lock-fill text-purple"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1 small fs-sm-6">Secure Portals</h6>
                                <p class="text-muted mb-0" style="font-size: 0.74rem; line-height: 1.35;">Distinct portals for Administrators, Assigned Managers, and Tenants.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Side-by-Side Action Buttons on Mobile & Desktop -->
                <div class="row g-2">
                    <div class="col-6">
                        <a href="#contact-section" class="btn btn-primary w-100 py-2 d-flex align-items-center justify-content-center text-center">
                            <i class="bi bi-envelope-paper me-1.5 text-gold"></i><span>Get In Touch</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="https://wa.me/252615554321?text=Hello%20HomeHub,%20I%20would%20like%20to%20learn%20more%20about%20your%20services" target="_blank" rel="noopener noreferrer" class="btn btn-success w-100 py-2 d-flex align-items-center justify-content-center text-center" style="background-color: #25D366; border-color: #25D366;">
                            <i class="bi bi-whatsapp me-1.5"></i><span>WhatsApp</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <!-- Visual Showcase Card -->
                <div class="card border-0 shadow-lg overflow-hidden position-relative rounded-4">
                    <img src="<?= BASE_URL; ?>assets/images/architectural-clay-model.jpg" alt="HomeHub Architectural Model" class="card-img-top" style="height: 280px; object-fit: cover;">
                    <div class="card-body p-4 bg-body-tertiary">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="badge badge-gold px-3 py-1.5 rounded-pill">
                                <i class="bi bi-geo-alt-fill me-1"></i> Head Office: Maka Al Mukarama, Hodan
                            </span>
                            <span class="text-muted small fw-semibold">Est. 2024</span>
                        </div>
                        <h4 class="fw-bold mb-2">Our Vision for Somali Urban Living</h4>
                        <p class="text-muted small mb-4">
                            We envision Mogadishu as a modern smart city where discovering a home and leasing an apartment takes minutes, with complete digital clarity and zero disputes between landlords and tenants.
                        </p>
                        <div class="row g-3 text-center border-top pt-3">
                            <div class="col-4">
                                <div class="fs-4 fw-bolder text-primary">17</div>
                                <div class="text-muted small text-uppercase" style="font-size: 0.72rem;">Districts</div>
                            </div>
                            <div class="col-4">
                                <div class="fs-4 fw-bolder text-gold">100%</div>
                                <div class="text-muted small text-uppercase" style="font-size: 0.72rem;">Transparent</div>
                            </div>
                            <div class="col-4">
                                <div class="fs-4 fw-bolder text-success">24/7</div>
                                <div class="text-muted small text-uppercase" style="font-size: 0.72rem;">Support</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section id="features-section" class="py-5 bg-body-tertiary">
    <div class="container py-4">
        <div class="text-center max-w-700 mx-auto mb-5">
            <span class="badge badge-gold mb-2">System Advantages</span>
            <h2 class="fw-bold">Why Property Owners & Tenants Choose HomeHub</h2>
            <p class="text-muted">Built specifically for the real estate landscape across the districts of Mogadishu (Banaadir).</p>
        </div>

        <!-- Features Cards Grid (2 Columns Side-by-Side on Mobile, 4 Columns on Desktop) -->
        <div class="row g-2 g-sm-3 g-lg-4">
            <div class="col-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm p-2.5 p-sm-3 border-top border-4" style="border-top-color: var(--hh-gold) !important;">
                    <div class="card-body p-1 p-sm-2">
                        <div class="rounded-3 icon-gold d-inline-flex p-2 p-sm-2.5 fs-4 fs-sm-3 mb-2 mb-sm-3">
                            <i class="bi bi-phone"></i>
                        </div>
                        <h6 class="fw-bold mb-1 mb-sm-2 fs-sm-5">Somali Mobile Payments</h6>
                        <p class="text-muted mb-0" style="font-size: 0.74rem; line-height: 1.4;">Seamless rent tracking supporting EVC Plus, Zaad Service, Sahal, and local bank transfers with reference validation.</p>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm p-2.5 p-sm-3 border-top border-4" style="border-top-color: var(--hh-primary) !important;">
                    <div class="card-body p-1 p-sm-2">
                        <div class="rounded-3 icon-primary d-inline-flex p-2 p-sm-2.5 fs-4 fs-sm-3 mb-2 mb-sm-3">
                            <i class="bi bi-pie-chart"></i>
                        </div>
                        <h6 class="fw-bold mb-1 mb-sm-2 fs-sm-5">Automated Occupancy Tracking</h6>
                        <p class="text-muted mb-0" style="font-size: 0.74rem; line-height: 1.4;">Real-time statistics on occupied vs. vacant apartments, tenant turnover, and monthly collection ratios for property managers.</p>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm p-2.5 p-sm-3 border-top border-4" style="border-top-color: var(--hh-gold) !important;">
                    <div class="card-body p-1 p-sm-2">
                        <div class="rounded-3 icon-gold d-inline-flex p-2 p-sm-2.5 fs-4 fs-sm-3 mb-2 mb-sm-3">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h6 class="fw-bold mb-1 mb-sm-2 fs-sm-5">Role-Based Security</h6>
                        <p class="text-muted mb-0" style="font-size: 0.74rem; line-height: 1.4;">Independent portals for Administrators, Assigned Property Managers, and Tenants with complete RBAC and data privacy.</p>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm p-2.5 p-sm-3 border-top border-4" style="border-top-color: var(--hh-primary) !important;">
                    <div class="card-body p-1 p-sm-2">
                        <div class="rounded-3 icon-primary d-inline-flex p-2 p-sm-2.5 fs-4 fs-sm-3 mb-2 mb-sm-3">
                            <i class="bi bi-receipt-cutoff"></i>
                        </div>
                        <h6 class="fw-bold mb-1 mb-sm-2 fs-sm-5">Instant Digital Invoicing</h6>
                        <p class="text-muted mb-0" style="font-size: 0.74rem; line-height: 1.4;">Automated monthly rent invoices, verifiable digital payment receipts, and real-time ledger records for all leases.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer with Luxury Architectural Background -->
<footer id="contact-section" class="footer-showcase py-5">
    <div class="container">
        <div class="row g-4 justify-content-between mb-4">
            <div class="col-lg-3">
                <div class="mb-3">
                    <img src="<?= BASE_URL; ?>assets/images/logo-dark.png" alt="HomeHub Logo" style="height: 48px; width: auto; object-fit: contain;">
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
                    <li><a href="<?= BASE_URL; ?>#about-section" class="text-muted">About Us</a></li>
                    <li><a href="<?= BASE_URL; ?>login.php" class="text-muted">Portal Login</a></li>
                    <li><a href="<?= BASE_URL; ?>register.php" class="text-muted">Register Tenant</a></li>
                </ul>
            </div>

            <div class="col-12 col-md-6 col-lg-4">
                <h6 class="fw-bold mb-3 text-uppercase small text-muted">Mogadishu Districts</h6>
                <?php 
                $allDst = mogadishu_districts();
                $halfDst = (int)ceil(count($allDst) / 2);
                $colDst1 = array_slice($allDst, 0, $halfDst);
                $colDst2 = array_slice($allDst, $halfDst);
                ?>
                <div class="row g-2 small">
                    <div class="col-6">
                        <ul class="list-unstyled d-flex flex-column gap-1.5 mb-0">
                            <?php foreach ($colDst1 as $dst): ?>
                                <li>
                                    <a href="<?= BASE_URL; ?>?city=<?= urlencode($dst); ?>#browse-section" class="text-muted d-flex align-items-center gap-1.5 text-truncate">
                                        <i class="bi bi-geo-alt text-gold" style="font-size: 0.75rem;"></i> <?= e($dst); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="col-6">
                        <ul class="list-unstyled d-flex flex-column gap-1.5 mb-0">
                            <?php foreach ($colDst2 as $dst): ?>
                                <li>
                                    <a href="<?= BASE_URL; ?>?city=<?= urlencode($dst); ?>#browse-section" class="text-muted d-flex align-items-center gap-1.5 text-truncate">
                                        <i class="bi bi-geo-alt text-gold" style="font-size: 0.75rem;"></i> <?= e($dst); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 d-flex flex-column align-items-center align-items-lg-end justify-content-center">
                <div class="text-center text-lg-end p-2">
                    <img src="<?= BASE_URL; ?>assets/images/logo-icon-white.png" alt="HomeHub Logo Icon" class="footer-brand-icon" style="height: 105px; width: auto; object-fit: contain; filter: drop-shadow(0 8px 24px rgba(1, 167, 153, 0.35)); transition: transform 0.3s ease;">
                    <div class="mt-2 text-muted small fw-semibold" style="letter-spacing: 0.05em; font-size: 0.75rem;">
                        <span class="text-gold">HomeHub</span> Property Management
                    </div>
                </div>
            </div>
        </div>

        <div class="border-top pt-3 d-flex flex-column flex-lg-row justify-content-between align-items-center gap-2 text-muted small text-center text-lg-start">
            <div class="d-flex flex-column flex-sm-row align-items-center gap-2">
                <div>&copy; <?= date('Y'); ?> HomeHub Somalia Property Management. All rights reserved.</div>
                <span class="d-none d-sm-inline opacity-50">&bull;</span>
                <div class="live-system-clock d-inline-flex flex-wrap align-items-center justify-content-center" title="Current Real-Time Live Clock">
                    <span class="text-gold fw-semibold"><?= date('l'); ?></span>, 
                    <span class="ms-1"><?= date('d F Y'); ?></span> 
                    <span class="opacity-50 mx-1.5">•</span> 
                    <span class="font-monospace text-gold fw-semibold"><?= date('h:i:s A'); ?></span>
                </div>
            </div>
            <div class="mt-1 mt-lg-0">
                <span>Developed by <a href="https://anazabdiwahid.netlify.app/" target="_blank" rel="noopener noreferrer" class="text-gold fw-semibold text-decoration-none">Anas Abdiwahid</a></span>
            </div>
        </div>
    </div>
</footer>

<!-- Home House Details & Occupancy Modal -->
<div class="modal fade" id="homeHouseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="hmTitle">House Details</h5>
                    <small class="text-muted" id="hmSubtitle"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4 align-items-center">
                    <div class="col-md-5">
                        <img id="hmImage" src="" alt="House" class="img-fluid rounded-3 shadow-sm w-100" style="height: 220px; object-fit: cover;">
                        <div class="mt-2 text-center">
                            <span class="fs-4 fw-bolder text-primary" id="hmPrice"></span>
                            <small class="text-muted d-block">per month</small>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <h6 class="fw-bold text-uppercase small text-muted mb-2">
                            <i class="bi bi-buildings-fill text-primary me-1"></i> Apartment Occupancy Status
                        </h6>

                        <!-- 3 Stat Blocks -->
                        <div class="row g-2 text-center mb-3">
                            <div class="col-4">
                                <div class="p-2 rounded-3 bg-body-tertiary border">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">Total</small>
                                    <h5 class="fw-bold mb-0 text-dark" id="hmTotalApts">0</h5>
                                    <small class="text-muted" style="font-size: 0.7rem;">Apartments</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded-3 bg-danger-subtle border border-danger-subtle">
                                    <small class="text-danger d-block fw-semibold" style="font-size: 0.72rem;">Occupied</small>
                                    <h5 class="fw-bold mb-0 text-danger" id="hmOccupiedApts">0</h5>
                                    <small class="text-danger" style="font-size: 0.7rem;">Units</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded-3 bg-success-subtle border border-success-subtle">
                                    <small class="text-success d-block fw-semibold" style="font-size: 0.72rem;">Vacant</small>
                                    <h5 class="fw-bold mb-0 text-success" id="hmVacantApts">0</h5>
                                    <small class="text-success" style="font-size: 0.7rem;">Available</small>
                                </div>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Occupancy Rate:</span>
                                <strong id="hmPercentText" class="text-dark">0%</strong>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" id="hmProgressBar" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>

                        <!-- Message Notice Alert -->
                        <div id="hmAlertBox"></div>
                    </div>
                </div>

                <div class="border-top mt-3 pt-3">
                    <h6 class="fw-bold small mb-1">Property Overview:</h6>
                    <p class="text-muted small mb-0" id="hmDesc" style="white-space: pre-line;"></p>
                </div>
            </div>
            <div class="modal-footer bg-body-tertiary d-flex justify-content-between">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <div id="hmActionBtnContainer"></div>
            </div>
        </div>
    </div>
</div>

<script>
function showHomeHouseModal(data) {
    document.getElementById('hmTitle').innerText = data.name;
    document.getElementById('hmSubtitle').innerText = data.code + ' • ' + data.category + ' • ' + data.city + ' (' + data.address + ')';
    document.getElementById('hmImage').src = data.image;
    document.getElementById('hmPrice').innerText = data.price;
    document.getElementById('hmTotalApts').innerText = data.total;
    document.getElementById('hmOccupiedApts').innerText = data.occupied;
    document.getElementById('hmVacantApts').innerText = data.vacant;
    document.getElementById('hmDesc').innerText = data.desc || 'No additional description provided.';

    const total = Math.max(1, parseInt(data.total) || 1);
    const occupied = parseInt(data.occupied) || 0;
    const vacant = parseInt(data.vacant) || 0;
    const pct = Math.min(100, Math.round((occupied / total) * 100));

    document.getElementById('hmPercentText').innerText = pct + '% Occupied';
    const pb = document.getElementById('hmProgressBar');
    pb.style.width = pct + '%';
    if (vacant <= 0) {
        pb.className = 'progress-bar bg-danger';
    } else if (pct > 70) {
        pb.className = 'progress-bar bg-warning';
    } else {
        pb.className = 'progress-bar bg-success';
    }

    const alertBox = document.getElementById('hmAlertBox');
    const btnBox = document.getElementById('hmActionBtnContainer');

    if (vacant <= 0) {
        alertBox.innerHTML = `
            <div class="alert alert-danger p-2 rounded-3 small mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-x-octagon-fill fs-4 text-danger flex-shrink-0"></i>
                <div>
                    <strong>Fully Occupied:</strong> All apartment units in this property are currently occupied.
                </div>
            </div>
        `;
        btnBox.innerHTML = `
            <button type="button" class="btn btn-secondary btn-sm fw-semibold" disabled>
                <i class="bi bi-x-circle me-1"></i> Fully Occupied
            </button>
        `;
    } else {
        alertBox.innerHTML = `
            <div class="alert alert-success p-2 rounded-3 small mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill fs-4 text-success flex-shrink-0"></i>
                <div>
                    <strong>Available for Rent:</strong> There are <strong>${vacant}</strong> vacant unit(s) ready for move-in!
                </div>
            </div>
        `;
        if (data.isUser) {
            btnBox.innerHTML = `
                <a href="${data.rentUrl}" class="btn btn-success btn-sm fw-semibold">
                    <i class="bi bi-key me-1"></i> Apply to Rent
                </a>
            `;
        } else {
            btnBox.innerHTML = `
                <a href="${data.loginUrl}" class="btn btn-primary btn-sm fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Rent
                </a>
            `;
        }
    }

    new bootstrap.Modal(document.getElementById('homeHouseModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
