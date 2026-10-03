<?php
/**
 * Edit House
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$houseId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
if ($houseId <= 0) {
    set_flash('danger', 'Invalid house ID.');
    header('Location: ' . BASE_URL . 'admin/houses.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM houses WHERE id = ? LIMIT 1");
$stmt->execute([$houseId]);
$house = $stmt->fetch();

if (!$house) {
    set_flash('danger', 'House not found.');
    header('Location: ' . BASE_URL . 'admin/houses.php');
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();
$managers = $pdo->query("SELECT id, name FROM users WHERE role = 'manager' AND status = 'active' ORDER BY name ASC")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $houseName      = trim($_POST['house_name'] ?? '');
        $houseCode      = trim($_POST['house_code'] ?? '');
        $categoryId     = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $managerId      = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
        $address        = trim($_POST['address'] ?? '');
        $city           = trim($_POST['city'] ?? '');
        $rentPrice      = (float)($_POST['rent_price'] ?? 0);
        $totalApts      = (int)($_POST['total_apartments'] ?? 1);
        $occupiedApts   = (int)($_POST['occupied_apartments'] ?? 0);
        $vacantApts     = max(0, $totalApts - $occupiedApts);
        $description    = trim($_POST['description'] ?? '');

        if (empty($houseName)) $errors[] = 'House Name is required.';
        if (empty($houseCode)) $errors[] = 'House Code is required.';
        if ($categoryId <= 0)  $errors[] = 'Please select a Category.';
        if (empty($address))   $errors[] = 'Address is required.';
        if (empty($city))      $errors[] = 'City is required.';
        if ($rentPrice <= 0)   $errors[] = 'Rent Price must be greater than zero.';
        if ($totalApts <= 0)   $errors[] = 'Total apartments must be at least 1.';
        if ($occupiedApts > $totalApts) $errors[] = 'Occupied apartments cannot exceed total apartments.';

        // Check unique code excluding this house
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM houses WHERE house_code = ? AND id != ? LIMIT 1");
            $stmt->execute([$houseCode, $houseId]);
            if ($stmt->fetch()) {
                $errors[] = 'A property with this House Code already exists.';
            }
        }

        // Image upload handling
        $imageFilename = $house['image'];
        if (empty($errors) && !empty($_FILES['image']['name'])) {
            $uploadRes = upload_image($_FILES['image'], 'houses/');
            if (!$uploadRes['success']) {
                $errors[] = $uploadRes['error'];
            } else {
                // Delete old image if new one uploaded
                if (!empty($house['image'])) {
                    delete_uploaded_image($house['image'], 'houses/');
                }
                $imageFilename = $uploadRes['filename'];
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE houses SET
                    category_id = ?, manager_id = ?, house_name = ?, house_code = ?, 
                    address = ?, city = ?, rent_price = ?, total_apartments = ?, 
                    occupied_apartments = ?, vacant_apartments = ?, description = ?, image = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $categoryId,
                    $managerId,
                    $houseName,
                    $houseCode,
                    $address,
                    $city,
                    $rentPrice,
                    $totalApts,
                    $occupiedApts,
                    $vacantApts,
                    $description,
                    $imageFilename,
                    $houseId
                ]);

                set_flash('success', 'House "' . $houseName . '" updated successfully!');
                header('Location: ' . BASE_URL . 'admin/houses.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Edit House - ' . e($house['house_name']);
$pageHeading = 'Edit House';
$pageSubtitle = 'Update property details, apartments allocation, and manager';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <main class="content-wrapper">
            <?= display_flash(); ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-0">Edit House: <?= e($house['house_name']); ?></h4>
                    <span class="badge bg-secondary-subtle text-secondary font-monospace"><?= e($house['house_code']); ?></span>
                </div>
                <a href="<?= BASE_URL; ?>admin/houses.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back to Houses
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Please check the following errors:</strong>
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
                    <form method="POST" action="<?= BASE_URL; ?>admin/house_edit.php?id=<?= $houseId; ?>" enctype="multipart/form-data">
                        <?= csrf_field(); ?>

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">House Name <span class="text-danger">*</span></label>
                                <input type="text" name="house_name" class="form-control" value="<?= e($_POST['house_name'] ?? $house['house_name']); ?>" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">House Code <span class="text-danger">*</span></label>
                                <input type="text" name="house_code" class="form-control font-monospace" value="<?= e($_POST['house_code'] ?? $house['house_code']); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">-- Select Category --</option>
                                    <?php 
                                        $currentCat = (int)($_POST['category_id'] ?? $house['category_id']);
                                        foreach ($categories as $cat): 
                                    ?>
                                        <option value="<?= (int)$cat['id']; ?>" <?= $currentCat === (int)$cat['id'] ? 'selected' : ''; ?>>
                                            <?= e($cat['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Assigned Manager</label>
                                <select name="manager_id" class="form-select">
                                    <option value="">-- No Manager Assigned --</option>
                                    <?php 
                                        $currentMgr = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : (!empty($house['manager_id']) ? (int)$house['manager_id'] : 0);
                                        foreach ($managers as $mgr): 
                                    ?>
                                        <option value="<?= (int)$mgr['id']; ?>" <?= $currentMgr === (int)$mgr['id'] ? 'selected' : ''; ?>>
                                            <?= e($mgr['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Degmada (District in Mogadishu) -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Degmada Muqdisho (District) <span class="text-danger">*</span></label>
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
                                <label class="form-label fw-semibold">Address / Street <span class="text-danger">*</span></label>
                                <input type="text" name="address" class="form-control" value="<?= e($_POST['address'] ?? $house['address']); ?>" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Monthly Rent ($) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" name="rent_price" class="form-control" value="<?= e($_POST['rent_price'] ?? $house['rent_price']); ?>" required>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Total Apartments <span class="text-danger">*</span></label>
                                <input type="number" min="1" id="total_apartments" name="total_apartments" class="form-control" value="<?= e($_POST['total_apartments'] ?? $house['total_apartments']); ?>" required oninput="calcVacant()">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Occupied Apartments</label>
                                <input type="number" min="0" id="occupied_apartments" name="occupied_apartments" class="form-control" value="<?= e($_POST['occupied_apartments'] ?? $house['occupied_apartments']); ?>" oninput="calcVacant()">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Vacant Apartments</label>
                                <input type="number" id="vacant_apartments" class="form-control bg-light" value="<?= (int)$house['vacant_apartments']; ?>" readonly>
                            </div>

                            <!-- Image Preview & File Input -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">Property Image</label>
                                <?php if (!empty($house['image'])): ?>
                                    <div class="mb-2">
                                        <img src="<?= UPLOAD_URL . 'houses/' . e($house['image']); ?>" alt="Current image" class="rounded-3 shadow-sm" style="max-height: 120px; object-fit: cover;">
                                        <div class="small text-muted mt-1">Current Image: <?= e($house['image']); ?></div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                                <div class="form-text small">Upload a new image to replace current one (Max 5MB).</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Description & Amenities</label>
                                <textarea name="description" rows="4" class="form-control"><?= e($_POST['description'] ?? $house['description']); ?></textarea>
                            </div>

                            <div class="col-12 mt-4 pt-3 border-top d-flex gap-2">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="bi bi-save me-1"></i>Update House
                                </button>
                                <a href="<?= BASE_URL; ?>admin/houses.php" class="btn btn-outline-secondary">Cancel</a>
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
