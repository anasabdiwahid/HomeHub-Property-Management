<?php
/**
 * About Us Page
 * HomeHub Property Management System - Mogadishu
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'About Us - HomeHub Mogadishu';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Public Navigation Bar -->
<nav class="navbar navbar-expand-lg public-navbar sticky-top border-bottom">
    <div class="container">
        <!-- Logo & Brand -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL; ?>">
            <img src="<?= BASE_URL; ?>assets/images/logo-dark.png" alt="HomeHub Logo" class="brand-logo-img logo-theme-dark" style="height: 44px; width: auto; object-fit: contain;">
            <img src="<?= BASE_URL; ?>assets/images/logo-light.png" alt="HomeHub Logo" class="brand-logo-img logo-theme-light" style="height: 44px; width: auto; object-fit: contain;">
        </a>

        <!-- Mobile Toggles Container -->
        <div class="d-flex align-items-center gap-2 d-lg-none">
            <button class="theme-toggle-btn btn btn-outline-secondary btn-sm p-1 px-2" type="button" aria-label="Toggle theme">
                <i class="bi bi-moon-stars-fill theme-icon-dark"></i>
                <i class="bi bi-sun-fill theme-icon-light d-none"></i>
            </button>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#aboutNavbarContent" aria-controls="aboutNavbarContent" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list fs-2 text-gold"></i>
            </button>
        </div>

        <!-- Center Nav Items -->
        <div class="collapse navbar-collapse" id="aboutNavbarContent">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-1 gap-lg-2">
                <li class="nav-item">
                    <a class="nav-link public-nav-link" href="<?= BASE_URL; ?>">
                        <i class="bi bi-house-door me-1"></i> Home
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
                    <a class="nav-link public-nav-link active" href="<?= BASE_URL; ?>about.php">
                        <i class="bi bi-info-circle me-1 text-gold"></i> About Us
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link public-nav-link" href="<?= BASE_URL; ?>#contact-section">
                        <i class="bi bi-telephone me-1"></i> Contact Us
                    </a>
                </li>
            </ul>

            <!-- Right Actions -->
            <div class="d-flex align-items-center gap-2 pt-2 pt-lg-0">
                <button class="theme-toggle-btn btn btn-outline-secondary btn-sm rounded-pill px-2.5 py-1.5 d-none d-lg-inline-flex align-items-center" type="button" aria-label="Toggle theme">
                    <i class="bi bi-moon-stars-fill theme-icon-dark"></i>
                    <i class="bi bi-sun-fill theme-icon-light d-none"></i>
                </button>

                <?php if (is_logged_in()): ?>
                    <a href="<?= BASE_URL . current_user_role(); ?>/index.php" class="btn btn-primary btn-sm px-3 rounded-pill fw-semibold">
                        <i class="bi bi-speedometer2 me-1 text-gold"></i> Dashboard
                    </a>
                    <a href="<?= BASE_URL; ?>logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-semibold">
                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="<?= BASE_URL; ?>login.php" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold">
                        <i class="bi bi-box-arrow-in-right me-1 text-gold"></i> Sign In
                    </a>
                    <a href="<?= BASE_URL; ?>register.php" class="btn btn-gold btn-sm rounded-pill px-3 fw-semibold">
                        <i class="bi bi-person-plus me-1"></i> Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- About Hero Section -->
<section class="py-5 position-relative border-bottom bg-body-tertiary">
    <div class="container py-4 text-center">
        <span class="badge badge-gold px-3 py-2 rounded-pill mb-3">
            <i class="bi bi-building-check me-1"></i> Trusted Real Estate Leadership
        </span>
        <h1 class="display-5 fw-bolder mb-3 text-main">
            About HomeHub Property Management
        </h1>
        <p class="lead text-muted max-w-700 mx-auto mb-4">
            Transforming rental workflows, tenant leasing, and property governance across Mogadishu with modern technology, transparency, and local payment integration.
        </p>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb justify-content-center mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>" class="text-gold text-decoration-none"><i class="bi bi-house me-1"></i>Home</a></li>
                <li class="breadcrumb-item active text-muted" aria-current="page">About Us</li>
            </ol>
        </nav>
    </div>
</section>

<!-- Our Story & Mission Section -->
<section class="py-5 bg-body">
    <div class="container py-3">
        <div class="row align-items-center g-5 mb-5">
            <div class="col-lg-6">
                <span class="badge badge-gold mb-2">Our Origins</span>
                <h2 class="display-6 fw-bold mb-3 text-main">
                    Bridging Somali Real Estate with Digital Innovation
                </h2>
                <p class="text-muted mb-3">
                    HomeHub was established to solve the complex hurdles confronting landlords, property managers, and tenants across the burgeoning real estate sector in Mogadishu, Somalia. Traditional handwritten records, verbal rent receipts, and scattered property records frequently led to reconciliation discrepancies and vacancy losses.
                </p>
                <p class="text-muted mb-4">
                    By combining automated occupancy tracking, tenant background registration, digital rental applications, and native mobile payment auditing (EVC Plus, Zaad, Sahal), HomeHub delivers an enterprise-grade ecosystem where every rental exchange is verified, auditable, and instant.
                </p>

                <div class="d-flex flex-wrap gap-3">
                    <a href="https://wa.me/252615554321?text=Hello%20HomeHub,%20I%20would%20like%20to%20inquire%20about%20your%20services" target="_blank" rel="noopener noreferrer" class="btn btn-success px-4 py-2" style="background-color: #25D366; border-color: #25D366;">
                        <i class="bi bi-whatsapp me-2"></i>Speak on WhatsApp
                    </a>
                    <a href="<?= BASE_URL; ?>#browse-section" class="btn btn-outline-primary px-4 py-2">
                        <i class="bi bi-buildings me-2 text-gold"></i>Explore Properties
                    </a>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="position-relative">
                    <div class="card border-0 shadow-lg overflow-hidden rounded-4">
                        <img src="<?= BASE_URL; ?>assets/images/architectural-clay-model.jpg" alt="HomeHub 3D Architecture" class="img-fluid" style="height: 380px; width: 100%; object-fit: cover;">
                        <div class="card-body p-4 bg-body-tertiary border-top">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="fw-bold mb-1">Mogadishu Urban Master Model</h5>
                                    <p class="text-muted small mb-0">Covering residential villas, apartments & commercial plazas</p>
                                </div>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                                    <i class="bi bi-shield-check me-1"></i> Verified
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mission & Vision Pillars -->
        <div class="row g-4 mt-2">
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm p-4 rounded-4 border-top border-4" style="border-top-color: var(--hh-gold) !important;">
                    <div class="card-body">
                        <div class="rounded-3 icon-gold d-inline-flex p-3 fs-3 mb-3">
                            <i class="bi bi-compass-fill"></i>
                        </div>
                        <h4 class="fw-bold mb-3">Our Mission</h4>
                        <p class="text-muted mb-0">
                            To empower Somali property owners and tenants through a unified digital leasing network that eliminates manual paperwork, ensures secure mobile transactions, and guarantees transparent real estate governance.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm p-4 rounded-4 border-top border-4" style="border-top-color: var(--hh-primary) !important;">
                    <div class="card-body">
                        <div class="rounded-3 icon-primary d-inline-flex p-3 fs-3 mb-3">
                            <i class="bi bi-lightbulb-fill"></i>
                        </div>
                        <h4 class="fw-bold mb-3">Our Vision</h4>
                        <p class="text-muted mb-0">
                            To be the leading and most trusted property management infrastructure across the Horn of Africa, recognized for unmatched reliability, tenant satisfaction, and technological excellence.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Mogadishu 17 Districts Coverage Section -->
<section class="py-5 bg-body-tertiary border-top border-bottom">
    <div class="container py-3">
        <div class="text-center max-w-700 mx-auto mb-4">
            <span class="badge badge-gold mb-2">Urban Footprint</span>
            <h2 class="fw-bold">Active in All 17 Districts of Mogadishu</h2>
            <p class="text-muted">HomeHub supports landlords and tenants across every municipality of Banaadir Region.</p>
        </div>

        <div class="row g-2 justify-content-center text-center">
            <?php 
            $allDistricts = [
                'Hodan', 'Wadajir', 'Waaberi', 'Shangaani', 'Xamar Weyne', 
                'Cabdicasiis', 'Kaaraan', 'Yaaqshiid', 'Wardhiigley', 'Howlwadaag', 
                'Heliwaa', 'Dharkenley', 'Dayniile', 'Shibis', 'Kaxda', 
                'Darusalaam', 'Garasbaaley'
            ];
            foreach ($allDistricts as $dst): 
            ?>
                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                    <a href="<?= BASE_URL; ?>?city=<?= urlencode($dst); ?>#browse-section" class="card p-2 text-decoration-none card-hover border bg-body rounded-3">
                        <small class="fw-bold text-main"><i class="bi bi-geo-alt-fill text-gold me-1"></i><?= e($dst); ?></small>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Direct WhatsApp Contact CTA -->
<section class="py-5 bg-body">
    <div class="container py-3">
        <div class="card border-0 shadow-lg p-4 p-md-5 rounded-4 text-center position-relative overflow-hidden" style="background: linear-gradient(135deg, rgba(7, 18, 30, 0.95) 0%, rgba(13, 30, 50, 0.90) 100%);">
            <div class="position-relative z-1 text-white">
                <span class="badge badge-gold px-3 py-2 rounded-pill mb-3">
                    <i class="bi bi-chat-dots-fill me-1"></i> Direct Support Channel
                </span>
                <h2 class="display-6 fw-bold mb-3 text-white">Have a Property Inquiry in Mogadishu?</h2>
                <p class="lead text-light opacity-75 max-w-700 mx-auto mb-4">
                    Our team of experienced property managers is ready to assist you. Chat with us on WhatsApp or visit our central Mogadishu office.
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="https://wa.me/252615554321?text=Hello%20HomeHub,%20I%20have%20an%20inquiry%20regarding%20your%20property%20management%20services" target="_blank" rel="noopener noreferrer" class="btn btn-success btn-lg px-4 shadow" style="background-color: #25D366; border-color: #25D366;">
                        <i class="bi bi-whatsapp me-2"></i>Start WhatsApp Chat (+252 61 555 4321)
                    </a>
                    <a href="<?= BASE_URL; ?>#contact-section" class="btn btn-gold btn-lg px-4 shadow">
                        <i class="bi bi-envelope me-2"></i>View Contact Info
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Public Footer with Luxury Background -->
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
                    <li><a href="<?= BASE_URL; ?>about.php" class="text-muted">About Us</a></li>
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
                <span>Developed by <span class="text-gold fw-semibold">Anas Abdiwahid</span></span>
            </div>
        </div>
    </div>
</footer>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
