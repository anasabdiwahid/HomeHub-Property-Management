<?php
/**
 * Application Configuration & Initialization
 * HomeHub Property Management System
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define Root Directory
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

// Dynamically determine BASE_URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

// Find the project base root relative to web directory
$parts = explode('/', trim($scriptDir, '/'));
$baseParts = [];
foreach ($parts as $p) {
    $baseParts[] = $p;
    if (strtolower($p) === 'home hub' || strtolower($p) === 'homehub') {
        break;
    }
}
$basePath = !empty($baseParts) ? '/' . implode('/', $baseParts) : '/Home hub';
// Fallback check if scriptDir directly contains admin/manager/user
if (preg_match('#^(.*?)/(admin|manager|user|assets|includes|config)#i', $scriptDir, $matches)) {
    $basePath = $matches[1];
}

define('BASE_URL', rtrim($protocol . $host . $basePath, '/') . '/');
define('UPLOAD_DIR', ROOT_PATH . 'uploads' . DIRECTORY_SEPARATOR);
define('UPLOAD_URL', BASE_URL . 'uploads/');

// Database inclusion
require_once ROOT_PATH . 'config/database.php';

// Generate CSRF Token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Function to fetch system settings
function get_setting(PDO $pdo, string $key, string $default = ''): string {
    static $settingsCache = [];
    if (isset($settingsCache[$key])) {
        return $settingsCache[$key];
    }
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        $settingsCache[$key] = $val !== false ? (string)$val : $default;
        return $settingsCache[$key];
    } catch (Exception $e) {
        return $default;
    }
}
