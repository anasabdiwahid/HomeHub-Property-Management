<?php
/**
 * Add New House
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

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
        $status         = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        // Validation
        if (empty($houseName)) $errors[] = 'House Name is required.';
        if (empty($houseCode)) $errors[] = 'House Code is required.';
        if ($categoryId <= 0)  $errors[] = 'Please select a Category.';
        if (empty($address))   $errors[] = 'Address is required.';
        if (empty($city))      $errors[] = 'City is required.';
        if ($rentPrice <= 0)   $errors[] = 'Rent Price must be greater than zero.';
        if ($totalApts <= 0)   $errors[] = 'Total apartments must be at least 1.';
        if ($occupiedApts > $totalApts) $errors[] = 'Occupied apartments cannot exceed total apartments.';

        // Check unique code
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM houses WHERE house_code = ? LIMIT 1");
            $stmt->execute([$houseCode]);
            if ($stmt->fetch()) {
                $errors[] = 'A property with this House Code already exists.';
            }
        }

        // Image upload handling
        $imageFilename = null;
        if (empty($errors) && !empty($_FILES['image']['name'])) {
            $uploadRes = upload_image($_FILES['image'], 'houses/');
            if (!$uploadRes['success']) {
                $errors[] = $uploadRes['error'];
            } else {
                $imageFilename = $uploadRes['filename'];
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO houses 
                    (category_id, manager_id, house_name, house_code, address, city, rent_price, total_apartments, occupied_apartments, vacant_apartments, description, image, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                    $status
                ]);

                set_flash('success', 'House "' . $houseName . '" added successfully!');
                header('Location: ' . BASE_URL . 'admin/houses.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Auto-generate suggested code
$nextId = (int)$pdo->query("SELECT MAX(id) + 1 FROM houses")->fetchColumn();
if ($nextId <= 0) $nextId = 1;
$suggestedCode = 'HH-SO-' . str_pad((string)$nextId, 3, '0', STR_PAD_LEFT);

$pageTitle = 'Add New House';
$pageHeading = 'Add New House';
$pageSubtitle = 'Create a new property listing with unit configuration and pricing';
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
                    <h4 class="fw-bold mb-0">Add New Property</h4>
                    <small class="text-muted">Enter complete house details, location, and apartment allocation</small>
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
                    <form method="POST" action="<?= BASE_URL; ?>admin/house_add.php" enctype="multipart/form-data">
                        <?= csrf_field(); ?>

                        <div class="row g-3">
                            <!-- House Name -->
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">House Name <span class="text-danger">*</span></label>
                                <input type="text" name="house_name" class="form-control" placeholder="e.g. Wadajir Heights Tower" value="<?= e($_POST['house_name'] ?? ''); ?>" required>
                            </div>

                            <!-- House Code -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">House Code <span class="text-danger">*</span></label>
                                <input type="text" name="house_code" class="form-control font-monospace" placeholder="e.g. HH-MG-001" value="<?= e($_POST['house_code'] ?? $suggestedCode); ?>" required>
                            </div>

                            <!-- Category -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">-- Select Category --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= (int)$cat['id']; ?>" <?= (isset($_POST['category_id']) && (int)$_POST['category_id'] === (int)$cat['id']) ? 'selected' : ''; ?>>
                                            <?= e($cat['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Assigned Manager -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Assigned Manager</label>
                                <select name="manager_id" class="form-select">
                                    <option value="">-- No Manager Assigned --</option>
                                    <?php foreach ($managers as $mgr): ?>
                                        <option value="<?= (int)$mgr['id']; ?>" <?= (isset($_POST['manager_id']) && (int)$_POST['manager_id'] === (int)$mgr['id']) ? 'selected' : ''; ?>>
                                            <?= e($mgr['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- District in Mogadishu -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Mogadishu District <span class="text-danger">*</span></label>
                                <select name="city" class="form-select" required>
                                    <option value="">-- Select Mogadishu District --</option>
                                    <?php 
                                        $selectedDistrict = $_POST['city'] ?? 'Hodan';
                                        foreach (mogadishu_districts() as $dist): 
                                    ?>
                                        <option value="<?= e($dist); ?>" <?= $selectedDistrict === $dist ? 'selected' : ''; ?>>
                                            <?= e($dist); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Address -->
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">Address / Street <span class="text-danger">*</span></label>
                                <input type="text" name="address" class="form-control" placeholder="e.g. Maka Al Mukarama Road, Hodan" value="<?= e($_POST['address'] ?? ''); ?>" required>
                            </div>

                            <!-- Property Status -->
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Property Status <span class="text-danger">*</span></label>
                                <select name="status" class="form-select">
                                    <option value="active" <?= (($_POST['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active (Visible)</option>
                                    <option value="inactive" <?= (($_POST['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Deactive (Hidden)</option>
                                </select>
                            </div>

                            <!-- Monthly Rent Price -->
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Monthly Rent ($) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" name="rent_price" class="form-control" placeholder="500.00" value="<?= e($_POST['rent_price'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <!-- Total Apartments -->
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Total Apartments <span class="text-danger">*</span></label>
                                <input type="number" min="1" id="total_apartments" name="total_apartments" class="form-control" value="<?= e($_POST['total_apartments'] ?? '1'); ?>" required oninput="calcVacant()">
                            </div>

                            <!-- Occupied Apartments -->
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Occupied Apartments</label>
                                <input type="number" min="0" id="occupied_apartments" name="occupied_apartments" class="form-control" value="<?= e($_POST['occupied_apartments'] ?? '0'); ?>" oninput="calcVacant()">
                            </div>

                            <!-- Vacant Apartments -->
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Vacant Apartments</label>
                                <input type="number" id="vacant_apartments" class="form-control bg-body fw-bold text-success" value="1" readonly>
                            </div>

                            <!-- Image Upload -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">Property Image</label>
                                <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                                <div class="form-text small">Accepted formats: JPG, PNG, WEBP (Max 5MB).</div>
                            </div>

                            <!-- Description -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">Description & Amenities</label>
                                <textarea name="description" rows="4" class="form-control" placeholder="Provide details about rooms, facilities, backup power, water, security, parking, etc..."><?= e($_POST['description'] ?? ''); ?></textarea>
                            </div>

                            <!-- Submit Buttons -->
                            <div class="col-12 mt-4 pt-3 border-top d-flex gap-2">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="bi bi-check-circle me-1"></i>Save House
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
calcVacant();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
