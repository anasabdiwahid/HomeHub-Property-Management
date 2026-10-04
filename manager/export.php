<?php
/**
 * Server-Side CSV Export for Manager
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('manager');

$managerId = (int)current_user_id();
$type = trim($_GET['type'] ?? 'houses');
$filename = 'my_' . $type . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
// Output BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

if ($type === 'houses') {
    fputcsv($output, ['ID', 'House Name', 'House Code', 'Category', 'City', 'Address', 'Rent Price', 'Total Apts', 'Occupied', 'Vacant', 'Status']);
    $stmt = $pdo->prepare("
        SELECT h.id, h.house_name, h.house_code, c.category_name, h.city, h.address, 
               h.rent_price, h.total_apartments, h.occupied_apartments, h.vacant_apartments, h.status
        FROM houses h
        JOIN categories c ON h.category_id = c.id
        WHERE h.manager_id = ?
        ORDER BY h.id ASC
    ");
    $stmt->execute([$managerId]);
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        fputcsv($output, $row);
    }
} elseif ($type === 'payments') {
    fputcsv($output, ['ID', 'Ref No', 'House Name', 'House Code', 'Tenant', 'Amount', 'Date', 'Method', 'Notes']);
    $stmt = $pdo->prepare("
        SELECT p.id, p.reference_no, h.house_name, h.house_code, u.name as tenant_name,
               p.amount, p.payment_date, p.payment_method, p.notes
        FROM payments p
        JOIN houses h ON p.house_id = h.id
        LEFT JOIN users u ON p.user_id = u.id
        WHERE h.manager_id = ?
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$managerId]);
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        fputcsv($output, $row);
    }
} else {
    fputcsv($output, ['Invalid export type']);
}

fclose($output);
exit;
