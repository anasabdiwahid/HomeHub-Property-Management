<?php
/**
 * Global Header Include
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$pageTitle = isset($pageTitle) ? $pageTitle . ' - HomeHub' : 'HomeHub - Somalia Property Management';
$requestScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$isDashboardPage = (strpos($requestScript, '/admin/') !== false) || (strpos($requestScript, '/manager/') !== false);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light" class="<?= $isDashboardPage ? 'app-dashboard' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?></title>
    
    <!-- Favicons generated from Official Logo -->
    <link rel="icon" type="image/png" sizes="64x64" href="<?= BASE_URL; ?>assets/images/favicon.png">
    <link rel="shortcut icon" href="<?= BASE_URL; ?>favicon.ico">
    <link rel="apple-touch-icon" href="<?= BASE_URL; ?>assets/images/favicon.png">

    <!-- Prevent dark mode and zoom flash -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('homehub-theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);

            // Persistent dashboard scale (default 0.68 / ~65% compact)
            const savedZoom = localStorage.getItem('homehub-dashboard-zoom') || '0.68';
            document.documentElement.style.setProperty('--dashboard-zoom', savedZoom);
        })();
    </script>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom Styles (Logo Color Harmonized) -->
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/style.css?v=<?= file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time(); ?>">
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/dark-mode.css?v=<?= file_exists(__DIR__ . '/../assets/css/dark-mode.css') ? filemtime(__DIR__ . '/../assets/css/dark-mode.css') : time(); ?>">
</head>
<body class="<?= $isDashboardPage ? 'app-dashboard' : ''; ?>">
