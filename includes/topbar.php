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
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;" onclick="toggleTheme()" title="Toggle Theme" aria-label="Toggle Theme">
            <i class="bi bi-sun-fill theme-toggle-icon"></i>
        </button>

        <!-- Quick Logout Button -->
        <a href="<?= BASE_URL; ?>logout.php" class="btn btn-outline-danger btn-sm rounded-pill d-flex align-items-center gap-1.5 px-2.5 py-1 text-decoration-none shadow-sm" title="Sign Out / Logout">
            <i class="bi bi-box-arrow-right"></i>
            <span class="small fw-semibold">Logout</span>
        </a>

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
