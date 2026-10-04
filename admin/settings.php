<?php
/**
 * System Settings
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid security token.');
        header('Location: ' . BASE_URL . 'admin/settings.php');
        exit;
    }

    $settingsToUpdate = [
        'system_name'     => trim($_POST['system_name'] ?? 'HomeHub Property Management'),
        'currency'        => trim($_POST['currency'] ?? '$'),
        'company_email'   => trim($_POST['company_email'] ?? 'contact@homehub.so'),
        'company_phone'   => trim($_POST['company_phone'] ?? '+252 61 555 4321'),
        'whatsapp_number' => trim($_POST['whatsapp_number'] ?? '+252 616256534'),
        'company_address' => trim($_POST['company_address'] ?? 'Maka Al Mukarama Road, Hodan, Mogadishu, Somalia'),
    ];

    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        foreach ($settingsToUpdate as $key => $val) {
            $stmt->execute([$key, $val]);
        }
        set_flash('success', 'System settings saved successfully!');
    } catch (Exception $e) {
        set_flash('danger', 'Error updating settings: ' . $e->getMessage());
    }

    header('Location: ' . BASE_URL . 'admin/settings.php');
    exit;
}

$systemName     = get_setting($pdo, 'system_name', 'HomeHub Property Management');
$currency       = get_setting($pdo, 'currency', '$');
$companyEmail   = get_setting($pdo, 'company_email', 'contact@homehub.so');
$companyPhone   = get_setting($pdo, 'company_phone', '+252 61 555 4321');
$whatsappNumber = get_setting($pdo, 'whatsapp_number', '+252 616256534');
$companyAddress = get_setting($pdo, 'company_address', 'Maka Al Mukarama Road, Hodan, Mogadishu, Somalia');

$pageTitle = 'System Settings';
$pageHeading = 'System Settings';
$pageSubtitle = 'Configure global business information, currency and branding';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <main class="content-wrapper">
            <?= display_flash(); ?>

            <div class="card shadow-sm" style="max-width: 800px;">
                <div class="card-header">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-gear-wide-connected text-primary me-2"></i>Global Property Management Configuration</h6>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="<?= BASE_URL; ?>admin/settings.php">
                        <?= csrf_field(); ?>

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold small">Application Name</label>
                                <input type="text" name="system_name" class="form-control" value="<?= e($systemName); ?>" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">Currency Symbol</label>
                                <input type="text" name="currency" class="form-control font-monospace" value="<?= e($currency); ?>" placeholder="$ or USD" required>
                                <div class="form-text small">e.g. $, USD, SLSH</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Support Email</label>
                                <input type="email" name="company_email" class="form-control" value="<?= e($companyEmail); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Support Phone (Somalia)</label>
                                <input type="text" name="company_phone" class="form-control" value="<?= e($companyPhone); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">WhatsApp Rental Inquiries Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-success text-white"><i class="bi bi-whatsapp"></i></span>
                                    <input type="text" name="whatsapp_number" class="form-control font-monospace" value="<?= e($whatsappNumber); ?>" placeholder="+252 616256534" required>
                                </div>
                                <div class="form-text small">Number where user rental requests and descriptions are forwarded (+252 616256534).</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold small">Physical Head Office Address</label>
                                <textarea name="company_address" rows="2" class="form-control" required><?= e($companyAddress); ?></textarea>
                            </div>

                            <div class="col-12 mt-4 pt-3 border-top">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="bi bi-save me-1"></i>Save Configuration
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
