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
<html lang="en" data-bs-theme="dark" class="<?= $isDashboardPage ? 'app-dashboard' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?></title>
    
    <!-- Favicons generated from Official Logo with cache-busting -->
    <?php 
    $favVer = file_exists(__DIR__ . '/../assets/images/favicon.png') ? filemtime(__DIR__ . '/../assets/images/favicon.png') : time();
    ?>
    <link rel="icon" type="image/png" sizes="128x128" href="<?= BASE_URL; ?>assets/images/favicon-128.png?v=<?= $favVer; ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="<?= BASE_URL; ?>assets/images/favicon-64.png?v=<?= $favVer; ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL; ?>assets/images/favicon-32.png?v=<?= $favVer; ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL; ?>assets/images/favicon-16.png?v=<?= $favVer; ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?= BASE_URL; ?>favicon.ico?v=<?= $favVer; ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= BASE_URL; ?>assets/images/apple-touch-icon.png?v=<?= $favVer; ?>">

    <!-- Prevent dark mode flash & Set Dark Mode by Default -->
    <script>
        (function() {
            let savedTheme = localStorage.getItem('homehub-theme');
            // Ensure open browser defaults to dark mode
            if (!savedTheme || localStorage.getItem('homehub-force-dark') !== '1') {
                savedTheme = 'dark';
                localStorage.setItem('homehub-theme', 'dark');
                localStorage.setItem('homehub-force-dark', '1');
            }
            document.documentElement.setAttribute('data-bs-theme', savedTheme);

            // Clean up any previous zoom override to restore 100% normal crisp layout
            localStorage.removeItem('homehub-dashboard-zoom');
            document.documentElement.style.removeProperty('--dashboard-zoom');
        })();
    </script>

    <!-- Google Fonts: Geometric Branding Typography (Syne & Outfit) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Syne:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom Styles (Logo Color Harmonized) -->
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/style.css?v=<?= file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time(); ?>">
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/dark-mode.css?v=<?= file_exists(__DIR__ . '/../assets/css/dark-mode.css') ? filemtime(__DIR__ . '/../assets/css/dark-mode.css') : time(); ?>">
</head>
<body class="<?= $isDashboardPage ? 'app-dashboard' : ''; ?>">
