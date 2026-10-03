<?php
/**
 * Manager House Update & Apartment Status Management
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('manager');

$managerId = (int)current_user_id();
$houseId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;

if ($houseId <= 0) {
    set_flash('danger', 'Invalid property ID.');
    header('Location: ' . BASE_URL . 'manager/houses.php');
    exit;
}

// Ensure the house belongs to this manager
$stmt = $pdo->prepare("SELECT h.*, c.category_name FROM houses h JOIN categories c ON h.category_id = c.id WHERE h.id = ? AND h.manager_id = ? LIMIT 1");
$stmt->execute([$houseId, $managerId]);
$house = $stmt->fetch();

if (!$house) {
    set_flash('danger', 'Property not found or you are not authorized to manage it.');
    header('Location: ' . BASE_URL . 'manager/houses.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Invalid security token.';
    } else {
        $address      = trim($_POST['address'] ?? '');
        $city         = trim($_POST['city'] ?? '');
        $rentPrice    = (float)($_POST['rent_price'] ?? 0);
        $totalApts    = (int)($_POST['total_apartments'] ?? 1);
        $occupiedApts = (int)($_POST['occupied_apartments'] ?? 0);
        $vacantApts   = max(0, $totalApts - $occupiedApts);
        $description  = trim($_POST['description'] ?? '');

        if (empty($address)) $errors[] = 'Address cannot be empty.';
        if (empty($city))    $errors[] = 'City cannot be empty.';
        if ($rentPrice <= 0) $errors[] = 'Rent Price must be greater than zero.';
        if ($totalApts <= 0) $errors[] = 'Total apartments must be at least 1.';
        if ($occupiedApts > $totalApts) $errors[] = 'Occupied apartments cannot exceed total apartments.';

        // Image upload handling
        $imageFilename = $house['image'];
        if (empty($errors) && !empty($_FILES['image']['name'])) {
            $uploadRes = upload_image($_FILES['image'], 'houses/');
            if (!$uploadRes['success']) {
                $errors[] = $uploadRes['error'];
            } else {
                if (!empty($house['image'])) {
                    delete_uploaded_image($house['image'], 'houses/');
                }
                $imageFilename = $uploadRes['filename'];
            }
        }

        if (empty($errors)) {
            try {
                $upd = $pdo->prepare("
                    UPDATE houses SET
                    address = ?, city = ?, rent_price = ?, total_apartments = ?,
                    occupied_apartments = ?, vacant_apartments = ?, description = ?, image = ?
                    WHERE id = ? AND manager_id = ?
                ");
                $upd->execute([
                    $address,
                    $city,
                    $rentPrice,
                    $totalApts,
                    $occupiedApts,
                    $vacantApts,
                    $description,
                    $imageFilename,
                    $houseId,
                    $managerId
                ]);

                set_flash('success', 'Property status and details updated successfully!');
                header('Location: ' . BASE_URL . 'manager/houses.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Update Property - ' . e($house['house_name']);
$pageHeading = 'Update Property & Apartment Status';
$pageSubtitle = e($house['house_name']) . ' (' . e($house['house_code']) . ')';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once __DIR__ . '/../includes/manager_sidebar.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <main class="content-wrapper">
            <?= display_flash(); ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-0"><?= e($house['house_name']); ?></h4>
                    <span class="badge bg-secondary-subtle text-secondary font-monospace"><?= e($house['house_code']); ?></span>
                    <span class="badge bg-primary-subtle text-primary"><?= e($house['category_name']); ?></span>
                </div>
                <a href="<?= BASE_URL; ?>manager/houses.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back to My Houses
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Please resolve the following issues:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="<?= BASE_URL; ?>manager/house_edit.php?id=<?= $houseId; ?>" enctype="multipart/form-data">
                        <?= csrf_field(); ?>

                        <!-- APARTMENT STATUS SECTION -->
                        <div class="p-3 bg-body-tertiary rounded-3 mb-4 border">
                            <h6 class="fw-bold text-primary mb-3">
                                <i class="bi bi-door-open-fill me-1"></i> Apartment Occupancy Status Management
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Total Units / Apartments <span class="text-danger">*</span></label>
                                    <input type="number" min="1" id="total_apartments" name="total_apartments" class="form-control" value="<?= e($_POST['total_apartments'] ?? $house['total_apartments']); ?>" required oninput="calcVacant()">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Occupied Apartments <span class="text-danger">*</span></label>
                                    <input type="number" min="0" id="occupied_apartments" name="occupied_apartments" class="form-control" value="<?= e($_POST['occupied_apartments'] ?? $house['occupied_apartments']); ?>" required oninput="calcVacant()">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Vacant Apartments (Available for Rent)</label>
                                    <input type="number" id="vacant_apartments" class="form-control bg-body fw-bold" value="<?= (int)$house['vacant_apartments']; ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <!-- HOUSE INFORMATION SECTION -->
                        <h6 class="fw-bold text-main mb-3">
                            <i class="bi bi-pencil-square me-1"></i> Property Information & Pricing
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Degmada Muqdisho (District) <span class="text-danger">*</span></label>
                                <select name="city" class="form-select" required>
                                    <option value="">-- Dooro Degmada Muqdisho --</option>
                                    <?php 
                                        $currentDistrict = $_POST['city'] ?? $house['city'];
                                        foreach (mogadishu_districts() as $dist): 
                                    ?>
                                        <option value="<?= e($dist); ?>" <?= $currentDistrict === $dist ? 'selected' : ''; ?>>
                                            <?= e($dist); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Address / Street <span class="text-danger">*</span></label>
                                <input type="text" name="address" class="form-control" value="<?= e($_POST['address'] ?? $house['address']); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Monthly Rent Price ($) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" name="rent_price" class="form-control" value="<?= e($_POST['rent_price'] ?? $house['rent_price']); ?>" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Update Photo</label>
                                <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                            </div>

                            <?php if (!empty($house['image'])): ?>
                                <div class="col-12">
                                    <img src="<?= UPLOAD_URL . 'houses/' . e($house['image']); ?>" alt="Property Image" class="rounded-3 shadow-sm" style="max-height: 140px; object-fit: cover;">
                                </div>
                            <?php endif; ?>

                            <div class="col-12">
                                <label class="form-label fw-semibold small">Description, Amenities & Notes</label>
                                <textarea name="description" rows="4" class="form-control"><?= e($_POST['description'] ?? $house['description']); ?></textarea>
                            </div>

                            <div class="col-12 mt-4 pt-3 border-top d-flex gap-2">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="bi bi-check-circle me-1"></i>Save Changes
                                </button>
                                <a href="<?= BASE_URL; ?>manager/houses.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function calcVacant() {
    const tot = parseInt(document.getElementById('total_apartments').value) || 0;
    const occ = parseInt(document.getElementById('occupied_apartments').value) || 0;
    document.getElementById('vacant_apartments').value = Math.max(0, tot - occ);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
