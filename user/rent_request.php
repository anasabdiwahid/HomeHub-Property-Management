<?php
/**
 * Submit Rental Request Handler
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('user');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    set_flash('danger', 'Invalid security token or request.');
    header('Location: ' . BASE_URL . 'user/index.php');
    exit;
}

$userId = (int)current_user_id();
$houseId = (int)($_POST['house_id'] ?? 0);
$moveInDate = trim($_POST['move_in_date'] ?? date('Y-m-d'));
$requestNote = trim($_POST['request_note'] ?? '');

if ($houseId <= 0) {
    set_flash('danger', 'Invalid property selection.');
    header('Location: ' . BASE_URL . 'user/index.php');
    exit;
}

// Verify house exists
$stmt = $pdo->prepare("SELECT house_name, vacant_apartments FROM houses WHERE id = ? LIMIT 1");
$stmt->execute([$houseId]);
$house = $stmt->fetch();

if (!$house) {
    set_flash('danger', 'The selected property does not exist.');
    header('Location: ' . BASE_URL . 'user/index.php');
    exit;
}

try {
    $insert = $pdo->prepare("
        INSERT INTO rental_requests (user_id, house_id, status, request_note, move_in_date)
        VALUES (?, ?, 'pending', ?, ?)
    ");
    $insert->execute([$userId, $houseId, $requestNote, $moveInDate]);

    set_flash('success', 'Your rental request for "' . $house['house_name'] . '" has been submitted successfully! The property manager will review it.');
    header('Location: ' . BASE_URL . 'user/my_requests.php');
    exit;
} catch (Exception $e) {
    set_flash('danger', 'Error submitting rental request: ' . $e->getMessage());
    header('Location: ' . BASE_URL . 'user/house_details.php?id=' . $houseId);
    exit;
}
