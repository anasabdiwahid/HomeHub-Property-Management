<?php
/**
 * Server-Side CSV Export for Admin
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$type = trim($_GET['type'] ?? 'houses');
$filename = 'homehub_' . $type . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
// Output BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

if ($type === 'houses') {
    fputcsv($output, ['ID', 'House Name', 'House Code', 'Category', 'City', 'Address', 'Rent Price', 'Total Apts', 'Occupied', 'Vacant', 'Status', 'Manager', 'Created At']);
    $stmt = $pdo->query("
        SELECT h.id, h.house_name, h.house_code, c.category_name, h.city, h.address, 
               h.rent_price, h.total_apartments, h.occupied_apartments, h.vacant_apartments,
               h.status, mgr.name as manager_name, h.created_at
        FROM houses h
        JOIN categories c ON h.category_id = c.id
        LEFT JOIN users mgr ON h.manager_id = mgr.id
        ORDER BY h.id ASC
    ");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        fputcsv($output, $row);
    }
} elseif ($type === 'payments') {
    fputcsv($output, ['ID', 'Ref No', 'House Name', 'House Code', 'Tenant', 'Amount', 'Date', 'Method', 'Notes']);
    $stmt = $pdo->query("
        SELECT p.id, p.reference_no, h.house_name, h.house_code, u.name as tenant_name,
               p.amount, p.payment_date, p.payment_method, p.notes
        FROM payments p
        JOIN houses h ON p.house_id = h.id
        LEFT JOIN users u ON p.user_id = u.id
        ORDER BY p.payment_date DESC
    ");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        fputcsv($output, $row);
    }
} elseif ($type === 'requests') {
    fputcsv($output, ['ID', 'Tenant Name', 'Tenant Phone', 'Tenant Email', 'House Name', 'House Code', 'City', 'Rent Price', 'Status', 'Move In Date', 'Applied Date']);
    $stmt = $pdo->query("
        SELECT r.id, u.name, u.phone, u.email, h.house_name, h.house_code, h.city, h.rent_price,
               r.status, r.move_in_date, r.created_at
        FROM rental_requests r
        JOIN users u ON r.user_id = u.id
        JOIN houses h ON r.house_id = h.id
        ORDER BY r.id DESC
    ");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        fputcsv($output, $row);
    }
} else {
    fputcsv($output, ['Invalid export type']);
}

fclose($output);
exit;
