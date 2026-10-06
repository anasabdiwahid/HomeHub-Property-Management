<?php
/**
 * Utility Functions & Helpers
 * HomeHub Property Management System
 */

declare(strict_types=1);

/**
 * Returns the list of official Mogadishu districts
 */
function mogadishu_districts(): array {
    return [
        'Hodan',
        'Wadajir',
        'Waaberi',
        'Cabdicasiis',
        'Howlwadaag',
        'Xamar Weyne',
        'Xamar Jajab',
        'Darusalaam',
        'Dharkenley',
        'Deyniile',
        'Boondheere',
        'Shibis',
        'Shangaani',
        'Yaaqshiid',
        'Kaaraan',
        'Warta Nabadda',
        'Kaxda',
        'Huriwaa',
        'Garasbaaley'
    ];
}

/**
 * Escapes HTML characters to prevent XSS
 */
function e(string|int|float|null $string): string {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Renders a hidden CSRF token input
 */
function csrf_field(): string {
    $token = $_SESSION['csrf_token'] ?? '';
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Verifies the CSRF token on POST requests
 */
function verify_csrf(): bool {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }
    }
    return true;
}

/**
 * Sets a flash message to display on the next page
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type'    => $type, // success, danger, warning, info
        'message' => $message
    ];
}

/**
 * Renders and clears flash message HTML if present
 */
function display_flash(): string {
    if (!empty($_SESSION['flash'])) {
        $type = e($_SESSION['flash']['type']);
        $msg = e($_SESSION['flash']['message']);
        unset($_SESSION['flash']);

        $icon = match($type) {
            'success' => 'bi-check-circle-fill',
            'danger'  => 'bi-exclamation-octagon-fill',
            'warning' => 'bi-exclamation-triangle-fill',
            default   => 'bi-info-circle-fill'
        };

        return '<div class="alert alert-' . $type . ' alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                    <i class="bi ' . $icon . ' me-2 fs-5"></i>
                    <div class="flex-grow-1">' . $msg . '</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
    return '';
}

/**
 * Formats a currency amount
 */
function format_currency(float|int|string $amount, string $currency = '$'): string {
    $num = (float)$amount;
    return $currency . ' ' . number_format($num, 2);
}

/**
 * Formats date string
 */
function format_date(?string $date, string $format = 'd M Y'): string {
    if (!$date) return '-';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : '-';
}

/**
 * Returns human-friendly relative time (e.g. "2 days ago")
 */
function time_ago(?string $datetime): string {
    if (!$datetime) return '-';
    $time = strtotime($datetime);
    if (!$time) return '-';
    $diff = time() - $time;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hrs ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
    return date('d M Y', $time);
}

/**
 * Returns HTML badge for rental request status
 */
function status_badge(string $status): string {
    $status = strtolower($status);
    return match($status) {
        'approved' => '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-circle me-1"></i>Approved</span>',
        'pending'  => '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1"><i class="bi bi-clock-history me-1"></i>Pending</span>',
        'rejected' => '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="bi bi-x-circle me-1"></i>Rejected</span>',
        default    => '<span class="badge bg-secondary px-2 py-1">' . e(ucfirst($status)) . '</span>'
    };
}

/**
 * Handle secure image upload
 */
function upload_image(array $file, string $subfolder = 'houses/'): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Invalid file parameters.'];
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'filename' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error code: ' . $file['error']];
    }

    // Limit to 5MB
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Image size exceeds maximum limit of 5MB.'];
    }

    // Check MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];

    if (!array_key_exists($mime, $allowed)) {
        return ['success' => false, 'error' => 'Invalid image format. Allowed: JPG, PNG, WEBP, GIF.'];
    }

    $ext = $allowed[$mime];
    $filename = 'house_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetDir = UPLOAD_DIR . trim($subfolder, '/\\') . DIRECTORY_SEPARATOR;

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $targetPath = $targetDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Failed to save uploaded image.'];
    }

    return ['success' => true, 'filename' => $filename];
}

/**
 * Delete uploaded image
 */
function delete_uploaded_image(?string $filename, string $subfolder = 'houses/'): bool {
    if (empty($filename)) return false;
    $filePath = UPLOAD_DIR . trim($subfolder, '/\\') . DIRECTORY_SEPARATOR . basename($filename);
    if (file_exists($filePath) && is_file($filePath)) {
        return @unlink($filePath);
    }
    return false;
}

/**
 * Returns icon and styling metadata for property category
 */
function category_icon_meta(?string $categoryName): array {
    $name = strtolower(trim((string)$categoryName));
    if (str_contains($name, 'apartment')) {
        return ['icon' => 'bi-building', 'class' => 'cat-icon-apartment', 'color' => '#0284c7'];
    } elseif (str_contains($name, 'villa')) {
        return ['icon' => 'bi-gem', 'class' => 'cat-icon-villa', 'color' => '#d97706'];
    } elseif (str_contains($name, 'office') || str_contains($name, 'commercial')) {
        return ['icon' => 'bi-briefcase', 'class' => 'cat-icon-office', 'color' => '#7c3aed'];
    } elseif (str_contains($name, 'townhouse')) {
        return ['icon' => 'bi-houses', 'class' => 'cat-icon-townhouse', 'color' => '#059669'];
    } elseif (str_contains($name, 'studio')) {
        return ['icon' => 'bi-lamp', 'class' => 'cat-icon-studio', 'color' => '#ea580c'];
    }
    return ['icon' => 'bi-tag', 'class' => 'cat-icon-default', 'color' => '#102a45'];
}

/**
 * Ensures all individual apartments exist for a house based on total_apartments count.
 * Returns array of apartments with tenant information if occupied.
 */
function ensure_house_apartments(PDO $pdo, int $houseId): array {
    $houseStmt = $pdo->prepare("SELECT id, house_name, total_apartments, rent_price FROM houses WHERE id = ? LIMIT 1");
    $houseStmt->execute([$houseId]);
    $house = $houseStmt->fetch();

    if (!$house) {
        return [];
    }

    $totalApts = max(1, (int)$house['total_apartments']);
    $basePrice = (float)$house['rent_price'];

    // Check existing apartments
    $aptStmt = $pdo->prepare("SELECT * FROM apartments WHERE house_id = ? ORDER BY id ASC");
    $aptStmt->execute([$houseId]);
    $existing = $aptStmt->fetchAll();
    $existingCount = count($existing);

    // Create any missing apartments
    if ($existingCount < $totalApts) {
        $insertStmt = $pdo->prepare("
            INSERT INTO apartments (house_id, apartment_number, rent_price, floor, status)
            VALUES (?, ?, ?, ?, 'vacant')
        ");
        for ($i = $existingCount + 1; $i <= $totalApts; $i++) {
            $aptNum = 'Apartment ' . $i;
            $floorNum = 'Floor ' . ceil($i / 4);
            $insertStmt->execute([$houseId, $aptNum, $basePrice, $floorNum]);
        }
    }

    // Synchronize approved rental requests with apartments if any are unassigned
    $approvedRequestsStmt = $pdo->prepare("
        SELECT id, user_id FROM rental_requests 
        WHERE house_id = ? AND status = 'approved' AND (apartment_id IS NULL OR apartment_id = 0)
        ORDER BY id ASC
    ");
    $approvedRequestsStmt->execute([$houseId]);
    $unassignedApproved = $approvedRequestsStmt->fetchAll();

    if (!empty($unassignedApproved)) {
        // Find vacant apartments to assign
        $vacantAptsStmt = $pdo->prepare("
            SELECT id, apartment_number FROM apartments 
            WHERE house_id = ? AND status = 'vacant' 
            ORDER BY id ASC LIMIT ?
        ");
        $vacantAptsStmt->bindValue(1, $houseId, PDO::PARAM_INT);
        $vacantAptsStmt->bindValue(2, count($unassignedApproved), PDO::PARAM_INT);
        $vacantAptsStmt->execute();
        $vacants = $vacantAptsStmt->fetchAll();

        foreach ($unassignedApproved as $idx => $req) {
            if (isset($vacants[$idx])) {
                $apt = $vacants[$idx];
                // Update rental_requests
                $updReq = $pdo->prepare("UPDATE rental_requests SET apartment_id = ?, assigned_apartment = ? WHERE id = ?");
                $updReq->execute([$apt['id'], $apt['apartment_number'], $req['id']]);

                // Update apartment
                $updApt = $pdo->prepare("UPDATE apartments SET status = 'occupied', current_tenant_id = ?, rental_request_id = ? WHERE id = ?");
                $updApt->execute([$req['user_id'], $req['id'], $apt['id']]);
            }
        }
    }

    // Synchronize house occupancy counters
    sync_house_occupancy_counts($pdo, $houseId);

    // Return all apartments with tenant details
    $fullStmt = $pdo->prepare("
        SELECT a.*, u.name as tenant_name, u.email as tenant_email, u.phone as tenant_phone,
               r.move_in_date, r.status as request_status
        FROM apartments a
        LEFT JOIN users u ON a.current_tenant_id = u.id
        LEFT JOIN rental_requests r ON a.rental_request_id = r.id
        WHERE a.house_id = ?
        ORDER BY a.id ASC
    ");
    $fullStmt->execute([$houseId]);
    return $fullStmt->fetchAll();
}

/**
 * Synchronizes the house summary counters (total, occupied, vacant)
 * with the actual rows in the apartments table.
 */
function sync_house_occupancy_counts(PDO $pdo, int $houseId): void {
    if ($houseId <= 0) {
        return;
    }
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_apts,
            SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occ_apts,
            SUM(CASE WHEN status = 'vacant' THEN 1 ELSE 0 END) as vac_apts
        FROM apartments 
        WHERE house_id = ?
    ");
    $stmt->execute([$houseId]);
    $counts = $stmt->fetch();
    
    if ($counts && (int)$counts['total_apts'] > 0) {
        $tot = (int)$counts['total_apts'];
        $occ = (int)$counts['occ_apts'];
        $vac = (int)$counts['vac_apts'];
        $upd = $pdo->prepare("UPDATE houses SET total_apartments = ?, occupied_apartments = ?, vacant_apartments = ? WHERE id = ?");
        $upd->execute([$tot, $occ, $vac, $houseId]);
    }
}

/**
 * Returns all currently vacant apartments for a house.
 */
function get_vacant_apartments(PDO $pdo, int $houseId): array {
    if ($houseId <= 0) {
        return [];
    }
    // Ensure apartments exist
    ensure_house_apartments($pdo, $houseId);
    $stmt = $pdo->prepare("
        SELECT id, apartment_number, rent_price, floor, status, notes
        FROM apartments 
        WHERE house_id = ? AND status = 'vacant'
        ORDER BY id ASC
    ");
    $stmt->execute([$houseId]);
    return $stmt->fetchAll();
}
