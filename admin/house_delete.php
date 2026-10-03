<?php
/**
 * Delete House Handler
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    set_flash('danger', 'Invalid security token or request.');
    header('Location: ' . BASE_URL . 'admin/houses.php');
    exit;
}

$houseId = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
if ($houseId <= 0) {
    set_flash('danger', 'Invalid house ID.');
    header('Location: ' . BASE_URL . 'admin/houses.php');
    exit;
}

try {
    // Fetch house details to get image
    $stmt = $pdo->prepare("SELECT house_name, image FROM houses WHERE id = ? LIMIT 1");
    $stmt->execute([$houseId]);
    $house = $stmt->fetch();

    if ($house) {
        // Delete image file if exists
        if (!empty($house['image'])) {
            delete_uploaded_image($house['image'], 'houses/');
        }

        // Delete from database (foreign keys will handle cascades if configured)
        $delStmt = $pdo->prepare("DELETE FROM houses WHERE id = ?");
        $delStmt->execute([$houseId]);

        set_flash('success', 'House "' . $house['house_name'] . '" deleted successfully.');
    } else {
        set_flash('warning', 'House not found or already deleted.');
    }
} catch (Exception $e) {
    set_flash('danger', 'Error deleting house: ' . $e->getMessage());
}

header('Location: ' . BASE_URL . 'admin/houses.php');
exit;
