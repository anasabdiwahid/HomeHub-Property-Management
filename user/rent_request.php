<?php
/**
 * Submit Rental Request Handler & WhatsApp Forwarder
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

// Verify house exists and fetch its full property details
$stmt = $pdo->prepare("
    SELECT h.*, c.category_name 
    FROM houses h 
    LEFT JOIN categories c ON h.category_id = c.id 
    WHERE h.id = ? LIMIT 1
");
$stmt->execute([$houseId]);
$house = $stmt->fetch();

if (!$house) {
    set_flash('danger', 'The selected property does not exist.');
    header('Location: ' . BASE_URL . 'user/index.php');
    exit;
}

// Check if property is deactive / inactive
if (($house['status'] ?? 'active') === 'inactive') {
    set_flash('danger', 'This property is currently deactive and unavailable for new rental applications.');
    header('Location: ' . BASE_URL . 'user/index.php');
    exit;
}

// Check if all apartments are fully occupied
if ((int)$house['vacant_apartments'] <= 0) {
    set_flash('danger', 'This property is fully occupied (' . (int)$house['total_apartments'] . ' Units). There are no vacant units available for rent at this time.');
    header('Location: ' . BASE_URL . 'user/house_details.php?id=' . $houseId);
    exit;
}

// Fetch tenant / user details
$userStmt = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE id = ? LIMIT 1");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

$userName = !empty($user['name']) ? $user['name'] : 'Tenant';
$userEmail = !empty($user['email']) ? $user['email'] : 'N/A';
$userPhone = !empty($user['phone']) ? $user['phone'] : '';
$houseName = $house['house_name'];
$houseCode = $house['house_code'] ?? 'N/A';
$categoryName = $house['category_name'] ?? 'Residential';
$location = trim(($house['address'] ?? '') . ', ' . ($house['city'] ?? ''));
$currency = get_setting($pdo, 'currency', '$');
$apartmentPrice = (float)($house['rent_price'] ?? 0);

// Capture requested apartment unit
$apartmentId = (int)($_POST['apartment_id'] ?? 0);
$apartmentName = '';

if ($apartmentId > 0) {
    $aptCheck = $pdo->prepare("SELECT id, apartment_number, rent_price, floor, status FROM apartments WHERE id = ? AND house_id = ? LIMIT 1");
    $aptCheck->execute([$apartmentId, $houseId]);
    $chosenApt = $aptCheck->fetch();
    if ($chosenApt) {
        $apartmentName = $chosenApt['apartment_number'];
        if ((float)$chosenApt['rent_price'] > 0) {
            $apartmentPrice = (float)$chosenApt['rent_price'];
        }
    }
}

// Fallback to first vacant apartment if not specified
if (empty($apartmentName)) {
    $firstVacStmt = $pdo->prepare("SELECT id, apartment_number, rent_price FROM apartments WHERE house_id = ? AND status = 'vacant' ORDER BY id ASC LIMIT 1");
    $firstVacStmt->execute([$houseId]);
    $firstVac = $firstVacStmt->fetch();
    if ($firstVac) {
        $apartmentId = (int)$firstVac['id'];
        $apartmentName = $firstVac['apartment_number'];
        if ((float)$firstVac['rent_price'] > 0) {
            $apartmentPrice = (float)$firstVac['rent_price'];
        }
    }
}

$priceFormatted = $currency . number_format($apartmentPrice, 2);

try {
    // 1. Record rental request in the system database with preferred/assigned apartment
    $insert = $pdo->prepare("
        INSERT INTO rental_requests (user_id, house_id, apartment_id, assigned_apartment, status, request_note, move_in_date)
        VALUES (?, ?, ?, ?, 'pending', ?, ?)
    ");
    $insert->execute([$userId, $houseId, $apartmentId > 0 ? $apartmentId : null, $apartmentName, $requestNote, $moveInDate]);

    // 2. Fetch destination WhatsApp phone (+252 616256534)
    $targetWhatsApp = get_setting($pdo, 'whatsapp_number', '+252 616256534');
    $cleanWhatsApp = preg_replace('/[^0-9]/', '', $targetWhatsApp);
    if (empty($cleanWhatsApp)) {
        $cleanWhatsApp = '252616256534';
    }

    // 3. Compose the WhatsApp message with exact requested details
    $msgLines = [
        "🏠 *CODSIGA KIREYSIGA GURI (HomeHub)*",
        "━━━━━━━━━━━━━━━━━━━━━━",
        "👤 *Full Name:* " . $userName,
        "📧 *Email:* " . $userEmail,
    ];
    if (!empty($userPhone)) {
        $msgLines[] = "📞 *Phone:* " . $userPhone;
    }
    $msgLines[] = "🏡 *Guri Name:* " . $houseName;
    $msgLines[] = "🏢 *Guriga Oo Doortay:* " . $houseName . " (" . $houseCode . " - " . $categoryName . ")";
    if (!empty($apartmentName)) {
        $msgLines[] = "🚪 *Apartment Number:* " . $apartmentName;
    }
    $msgLines[] = "📍 *Location:* " . $location;
    $msgLines[] = "💰 *Qiimaha:* " . $priceFormatted . " / bishii";
    $msgLines[] = "📅 *Move-In Date:* " . $moveInDate;
    $msgLines[] = "━━━━━━━━━━━━━━━━━━━━━━";
    $msgLines[] = "📝 *Description:*";
    $msgLines[] = !empty($requestNote) ? $requestNote : "(Wax faahfaahin ah lama soo gelin)";
    $msgLines[] = "━━━━━━━━━━━━━━━━━━━━━━";
    $msgLines[] = "Waxaan rabaa inaan kireysto gurigan" . (!empty($apartmentName) ? " iyo qolka {$apartmentName}" : "") . ", fadlan iisoo xaqiiji.";

    $whatsappMessage = implode("\n", $msgLines);
    $whatsappUrl = 'https://api.whatsapp.com/send?phone=' . $cleanWhatsApp . '&text=' . rawurlencode($whatsappMessage);

    set_flash('success', 'Codsigagii kireysiga si guul leh ayaa loo diiwaangeliyay! Waxaa laguugu wareejiyay WhatsApp si aad fariinta u dirto.');

    // 4. Redirect to WhatsApp directly
    if (!headers_sent()) {
        header('Location: ' . $whatsappUrl);
    }
?>
<!DOCTYPE html>
<html lang="so">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Waxaa laguu wareejinayaa WhatsApp...</title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 1.5rem;
        }
        .redirect-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            max-width: 480px;
            width: 100%;
            padding: 2.5rem 2rem;
            text-align: center;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .wa-btn {
            background-color: #25D366;
            color: #ffffff;
            font-weight: 700;
            padding: 14px 28px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 1.05rem;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
            transition: all 0.2s ease;
            width: 100%;
        }
        .wa-btn:hover {
            background-color: #20ba5a;
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.5);
        }
    </style>
</head>
<body>
    <div class="redirect-card">
        <div style="width: 75px; height: 75px; background: #e8f5e9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
            <i class="bi bi-whatsapp" style="font-size: 2.75rem; color: #25D366;"></i>
        </div>
        <h3 style="font-weight: 700; color: #1e293b; margin-bottom: 0.5rem;">Codsigagii Waa La Qabtay!</h3>
        <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 1.75rem; line-height: 1.5;">
            Waxaa si toos ah laguugu wareejinayaa <strong>WhatsApp (+<?= htmlspecialchars($cleanWhatsApp, ENT_QUOTES, 'UTF-8'); ?>)</strong> si aad fariinta ugu dirto maamulka.
        </p>
        <a href="<?= htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8'); ?>" class="wa-btn">
            <i class="bi bi-whatsapp" style="font-size: 1.3rem;"></i> Fur WhatsApp & Dir Hadda
        </a>
        <div style="margin-top: 1.75rem; border-top: 1px solid #f1f5f9; padding-top: 1.25rem;">
            <a href="<?= BASE_URL; ?>user/my_requests.php" style="color: #64748b; text-decoration: none; font-size: 0.9rem; font-weight: 500;">
                ← Eeg Codsiyadayda (My Requests)
            </a>
        </div>
    </div>
    <script>
        // Automatic redirect to WhatsApp
        window.location.href = <?= json_encode($whatsappUrl); ?>;
    </script>
</body>
</html>
<?php
    exit;
} catch (Exception $e) {
    set_flash('danger', 'Error submitting rental request: ' . $e->getMessage());
    header('Location: ' . BASE_URL . 'user/house_details.php?id=' . $houseId);
    exit;
}
