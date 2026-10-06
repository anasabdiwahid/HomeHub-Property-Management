<?php
/**
 * User Top Navigation Bar (NO SIDEBAR AS SPECIFIED)
 * HomeHub Property Management System
 */

declare(strict_types=1);
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$currentUser = current_user();
?>
<nav class="navbar navbar-expand-lg user-navbar">
    <div class="container">
        <!-- Brand Logo -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL; ?>user/index.php">
            <img src="<?= BASE_URL; ?>assets/images/logo_clean.png" alt="HomeHub Logo" style="height: 42px; width: auto; object-fit: contain;">
        </a>

        <!-- Mobile Toggle Button -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#userNavbarContent" aria-controls="userNavbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="userNavbarContent">
            <!-- Navigation Links -->
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-1 gap-lg-2">
                <li class="nav-item">
                    <a class="nav-link <?= ($currentScript === 'index.php' || $currentScript === 'house_details.php') ? 'active' : ''; ?>" href="<?= BASE_URL; ?>user/index.php">
                        <i class="bi bi-houses"></i>
                        <span>Available Houses</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentScript === 'my_requests.php') ? 'active' : ''; ?>" href="<?= BASE_URL; ?>user/my_requests.php">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>My Rental Requests</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentScript === 'approved_rentals.php') ? 'active' : ''; ?>" href="<?= BASE_URL; ?>user/approved_rentals.php">
                        <i class="bi bi-patch-check"></i>
                        <span>Approved Rentals</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentScript === 'profile.php') ? 'active' : ''; ?>" href="<?= BASE_URL; ?>user/profile.php">
                        <i class="bi bi-person"></i>
                        <span>Profile</span>
                    </a>
                </li>
            </ul>

            <!-- Right Controls: Dark Mode & User Dropdown -->
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" onclick="toggleTheme()" title="Toggle Theme" aria-label="Toggle Theme">
                    <i class="bi bi-sun-fill theme-toggle-icon"></i>
                </button>

                <div class="dropdown">
                    <button class="btn btn-light btn-sm d-flex align-items-center gap-2 border px-3 py-2 dropdown-toggle rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="rounded-circle bg-navy text-white d-flex align-items-center justify-content-center fw-bold" style="width: 28px; height: 28px; font-size: 0.8rem; border: 2px solid var(--hh-gold);">
                            <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)); ?>
                        </div>
                        <span class="fw-semibold small d-none d-sm-inline"><?= e($currentUser['name'] ?? 'User'); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li class="px-3 py-2 border-bottom">
                            <p class="mb-0 fw-semibold text-truncate"><?= e($currentUser['name'] ?? ''); ?></p>
                            <small class="text-muted text-truncate d-block"><?= e($currentUser['email'] ?? ''); ?></small>
                        </li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL; ?>user/profile.php"><i class="bi bi-person me-2 text-primary"></i>My Profile</a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL; ?>user/my_requests.php"><i class="bi bi-list-check me-2 text-primary"></i>Rental Requests</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2 text-danger" href="<?= BASE_URL; ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>
