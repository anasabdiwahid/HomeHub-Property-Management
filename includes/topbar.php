<?php
/**
 * Admin & Manager Topbar Navigation
 * HomeHub Property Management System
 */

declare(strict_types=1);
$currentUser = current_user();
?>
<header class="topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" onclick="toggleSidebar()" aria-label="Toggle Sidebar">
            <i class="bi bi-list fs-5"></i>
        </button>
        <div>
            <h5 class="mb-0 fw-bold"><?= e($pageHeading ?? 'Dashboard'); ?></h5>
            <?php if (!empty($pageSubtitle)): ?>
                <small class="text-muted"><?= e($pageSubtitle); ?></small>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 gap-md-3">
        <!-- Dark Mode Toggle Button -->
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" onclick="toggleTheme()" title="Toggle Theme" aria-label="Toggle Theme">
            <i class="bi bi-moon-stars-fill theme-toggle-icon"></i>
        </button>

        <!-- Dashboard Zoom Scale Dropdown -->
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1 rounded-pill px-2.5 py-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Dashboard Zoom Scale">
                <i class="bi bi-aspect-ratio"></i>
                <span id="zoomLevelDisplay" class="fw-bold" style="font-size: 0.78rem;">68%</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm py-1" style="min-width: 170px; font-size: 0.85rem;">
                <li class="dropdown-header text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.04em;">Dashboard Scale</li>
                <li>
                    <button type="button" class="dropdown-item py-1.5 zoom-option-item d-flex align-items-center justify-content-between" data-zoom="0.68" onclick="setDashboardZoom('0.68')">
                        <span>68% (Compact)</span>
                        <span class="badge bg-success-subtle text-success small">Default</span>
                    </button>
                </li>
                <li>
                    <button type="button" class="dropdown-item py-1.5 zoom-option-item d-flex align-items-center justify-content-between" data-zoom="0.65" onclick="setDashboardZoom('0.65')">
                        <span>65% (Mini)</span>
                    </button>
                </li>
                <li>
                    <button type="button" class="dropdown-item py-1.5 zoom-option-item" data-zoom="0.75" onclick="setDashboardZoom('0.75')">
                        75%
                    </button>
                </li>
                <li>
                    <button type="button" class="dropdown-item py-1.5 zoom-option-item" data-zoom="0.85" onclick="setDashboardZoom('0.85')">
                        85%
                    </button>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <button type="button" class="dropdown-item py-1.5 zoom-option-item" data-zoom="1.0" onclick="setDashboardZoom('1.0')">
                        100% (Standard)
                    </button>
                </li>
            </ul>
        </div>

        <!-- User Dropdown -->
        <div class="dropdown">
            <button class="btn btn-light btn-sm d-flex align-items-center gap-2 border px-2 py-1 dropdown-toggle rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="rounded-circle bg-navy text-white d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.85rem; border: 2px solid var(--hh-gold);">
                    <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)); ?>
                </div>
                <div class="text-start d-none d-md-block me-1">
                    <div class="fw-semibold lh-1" style="font-size: 0.88rem;"><?= e($currentUser['name'] ?? 'User'); ?></div>
                    <small class="text-gold text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.05em;"><?= e($currentUser['role'] ?? ''); ?></small>
                </div>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li class="px-3 py-2 border-bottom">
                    <p class="mb-0 fw-semibold text-truncate"><?= e($currentUser['name'] ?? ''); ?></p>
                    <small class="text-muted text-truncate d-block"><?= e($currentUser['email'] ?? ''); ?></small>
                </li>
                <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                    <li><a class="dropdown-item py-2" href="<?= BASE_URL; ?>admin/profile.php"><i class="bi bi-person me-2 text-primary"></i>My Profile</a></li>
                    <li><a class="dropdown-item py-2" href="<?= BASE_URL; ?>admin/admins.php"><i class="bi bi-shield-lock me-2 text-primary"></i>Manage Admins</a></li>
                    <li><a class="dropdown-item py-2" href="<?= BASE_URL; ?>admin/settings.php"><i class="bi bi-gear me-2 text-primary"></i>Settings</a></li>
                <?php else: ?>
                    <li><a class="dropdown-item py-2" href="<?= BASE_URL; ?>manager/profile.php"><i class="bi bi-person me-2 text-primary"></i>My Profile</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item py-2 text-danger" href="<?= BASE_URL; ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
            </ul>
        </div>
    </div>
</header>
