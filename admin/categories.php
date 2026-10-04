<?php
/**
 * Categories Management
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

// Handle Actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid security token.');
        header('Location: ' . BASE_URL . 'admin/categories.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $catName = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($catName)) {
            set_flash('danger', 'Category name is required.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (category_name, description) VALUES (?, ?)");
            $stmt->execute([$catName, $description]);
            set_flash('success', 'Category "' . $catName . '" added successfully!');
        }
    } elseif ($action === 'edit') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $catName = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($catId <= 0 || empty($catName)) {
            set_flash('danger', 'Valid Category ID and name are required.');
        } else {
            $stmt = $pdo->prepare("UPDATE categories SET category_name = ?, description = ? WHERE id = ?");
            $stmt->execute([$catName, $description, $catId]);
            set_flash('success', 'Category updated successfully!');
        }
    } elseif ($action === 'delete') {
        $catId = (int)($_POST['category_id'] ?? 0);
        if ($catId > 0) {
            // Check if houses exist under this category
            $count = (int)$pdo->query("SELECT COUNT(*) FROM houses WHERE category_id = {$catId}")->fetchColumn();
            if ($count > 0) {
                set_flash('danger', 'Cannot delete this category because ' . $count . ' house(s) are assigned to it.');
            } else {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$catId]);
                set_flash('success', 'Category deleted successfully!');
            }
        }
    }

    header('Location: ' . BASE_URL . 'admin/categories.php');
    exit;
}

// Fetch categories with houses count
$categories = $pdo->query("
    SELECT c.*, COUNT(h.id) as house_count 
    FROM categories c 
    LEFT JOIN houses h ON c.id = h.category_id 
    GROUP BY c.id 
    ORDER BY c.id ASC
")->fetchAll();

$pageTitle = 'Categories Management';
$pageHeading = 'Categories';
$pageSubtitle = 'Manage property classifications (Apartments, Villas, Offices, Studios)';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-wrapper">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <main class="content-wrapper">
            <?= display_flash(); ?>

            <div class="row g-4">
                <!-- Add Category Form -->
                <div class="col-lg-4">
                    <div class="card shadow-sm sticky-top" style="top: 90px; border-top: 4px solid var(--hh-gold) !important;">
                        <div class="card-header bg-transparent d-flex align-items-center">
                            <div class="card-header-icon bg-primary-subtle text-primary">
                                <i class="bi bi-tag-fill"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold">Add New Category</h6>
                                <small class="text-muted">Create property classification</small>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="<?= BASE_URL; ?>admin/categories.php">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="add">

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Category Name <span class="text-danger">*</span></label>
                                    <div class="input-icon-group">
                                        <i class="bi bi-card-heading input-icon-prefix"></i>
                                        <input type="text" name="category_name" class="form-control" placeholder="e.g. Commercial Plaza" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Description</label>
                                    <textarea name="description" rows="3" class="form-control" placeholder="Brief summary of amenities, unit structures or layout..."></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 py-2 d-flex align-items-center justify-content-center gap-2 shadow-sm">
                                    <i class="bi bi-plus-circle-fill text-gold"></i>
                                    <span>Create Category</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Categories List -->
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="card-header-icon bg-primary-subtle text-primary">
                                    <i class="bi bi-collection-fill"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold">Property Categories</h6>
                                    <small class="text-muted">Classifications used for filtering & listings</small>
                                </div>
                            </div>
                            <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill font-monospace">
                                <i class="bi bi-tags me-1"></i><?= count($categories); ?> Categories
                            </span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">#</th>
                                            <th>Category</th>
                                            <th>Description</th>
                                            <th style="width: 140px;">Properties</th>
                                            <th class="text-end" style="width: 110px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($categories)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-5 text-muted">
                                                    <i class="bi bi-tags fs-1 d-block mb-2 text-muted opacity-50"></i>
                                                    No categories created yet. Fill the form to add one.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($categories as $index => $cat): ?>
                                                <?php $meta = category_icon_meta($cat['category_name']); ?>
                                                <tr>
                                                    <td class="text-muted small fw-semibold"><?= $index + 1; ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-3">
                                                            <div class="category-avatar <?= $meta['class']; ?>">
                                                                <i class="bi <?= $meta['icon']; ?>"></i>
                                                            </div>
                                                            <div>
                                                                <div class="fw-bold text-main fs-6"><?= e($cat['category_name']); ?></div>
                                                                <small class="text-muted font-monospace" style="font-size: 0.72rem;">ID #<?= (int)$cat['id']; ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-muted small" style="max-width: 280px; line-height: 1.5;">
                                                        <?= e($cat['description'] ?? '-'); ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge-count-pill">
                                                            <i class="bi bi-buildings text-primary"></i>
                                                            <span><strong><?= (int)$cat['house_count']; ?></strong> <?= (int)$cat['house_count'] === 1 ? 'property' : 'properties'; ?></span>
                                                        </span>
                                                    </td>
                                                    <td class="text-end">
                                                        <div class="btn-action-group justify-content-end">
                                                            <button type="button" class="btn-action btn-action-edit" title="Edit Category" 
                                                                    onclick="openEditCategoryModal(<?= (int)$cat['id']; ?>, '<?= e(addslashes($cat['category_name'])); ?>', '<?= e(addslashes($cat['description'] ?? '')); ?>')">
                                                                <i class="bi bi-pencil-square"></i>
                                                            </button>

                                                            <form method="POST" action="<?= BASE_URL; ?>admin/categories.php" class="d-inline" data-confirm="Ma hubtaa inaad tirtirto category-gan '<?= e(addslashes($cat['category_name'])); ?>'?">
                                                                <?= csrf_field(); ?>
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="category_id" value="<?= (int)$cat['id']; ?>">
                                                                <button type="submit" class="btn-action btn-action-delete" title="Delete Category" <?= (int)$cat['house_count'] > 0 ? 'disabled title="Cannot delete category with assigned houses"' : ''; ?>>
                                                                    <i class="bi bi-trash3"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= BASE_URL; ?>admin/categories.php" class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="category_id" id="edit_cat_id">

            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="card-header-icon bg-warning-subtle text-warning mb-0">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0">Edit Category</h5>
                        <small class="text-muted">Update classification details</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Category Name <span class="text-danger">*</span></label>
                    <div class="input-icon-group">
                        <i class="bi bi-card-heading input-icon-prefix"></i>
                        <input type="text" name="category_name" id="edit_cat_name" class="form-control" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Description</label>
                    <textarea name="description" id="edit_cat_desc" rows="3" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer border-top py-3 bg-body-tertiary">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-4">
                    <i class="bi bi-check2-circle me-1 text-gold"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditCategoryModal(id, name, desc) {
    document.getElementById('edit_cat_id').value = id;
    document.getElementById('edit_cat_name').value = name;
    document.getElementById('edit_cat_desc').value = desc;
    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
