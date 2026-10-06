<?php
/**
 * Manager Sidebar Navigation
 * HomeHub Property Management System
 */

declare(strict_types=1);
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<aside class="sidebar">
    <div class="brand d-flex align-items-center justify-content-between">
        <a href="<?= BASE_URL; ?>manager/index.php" class="d-flex align-items-center gap-2 text-decoration-none">
            <img src="<?= BASE_URL; ?>assets/images/logo-icon-dark.png" alt="HomeHub" class="brand-logo-img">
            <div>
                <h6 class="brand-title">HomeHub</h6>
                <p class="brand-tagline">Manager Panel</p>
            </div>
        </a>
    </div>

    <div class="sidebar-nav">
        <div class="nav-section-title">Navigation</div>
        
        <a href="<?= BASE_URL; ?>manager/index.php" class="nav-link <?= ($currentScript === 'index.php') ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>

        <a href="<?= BASE_URL; ?>manager/houses.php" class="nav-link <?= in_array($currentScript, ['houses.php', 'house_edit.php']) ? 'active' : ''; ?>">
            <i class="bi bi-houses"></i>
            <span>My Houses</span>
        </a>

        <a href="<?= BASE_URL; ?>manager/rental_requests.php" class="nav-link <?= ($currentScript === 'rental_requests.php') ? 'active' : ''; ?>">
            <i class="bi bi-file-earmark-check"></i>
            <span>Rental Requests</span>
        </a>

        <a href="<?= BASE_URL; ?>manager/payments.php" class="nav-link <?= ($currentScript === 'payments.php') ? 'active' : ''; ?>">
            <i class="bi bi-cash-stack"></i>
            <span>Rent Tracking</span>
        </a>

        <a href="<?= BASE_URL; ?>manager/reports.php" class="nav-link <?= ($currentScript === 'reports.php') ? 'active' : ''; ?>">
            <i class="bi bi-bar-chart-line"></i>
            <span>Reports</span>
        </a>

        <div class="nav-section-title">Account</div>

        <a href="<?= BASE_URL; ?>manager/profile.php" class="nav-link <?= ($currentScript === 'profile.php') ? 'active' : ''; ?>">
            <i class="bi bi-person-circle"></i>
            <span>Profile</span>
        </a>

        <a href="<?= BASE_URL; ?>logout.php" class="nav-link text-danger mt-1">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>

        <!-- View Public Website Button -->
        <div class="pt-2 px-1">
            <a href="<?= BASE_URL; ?>index.php" target="_blank" class="btn-view-website" title="Open Public Website">
                <i class="bi bi-globe2"></i>
                <span>View Website</span>
                <i class="bi bi-arrow-up-right small ms-auto opacity-75"></i>
            </a>
        </div>
    </div>

    <div class="sidebar-footer">
        <div class="d-flex align-items-center justify-content-between text-muted small">
            <span>Manager Portal</span>
            <span class="badge bg-gold">Active</span>
        </div>
    </div>
</aside>
