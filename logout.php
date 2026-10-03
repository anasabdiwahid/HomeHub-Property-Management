<?php
/**
 * User Logout Script
 * HomeHub Property Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

logout_user();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
set_flash('info', 'You have been signed out safely. Come back soon!');
header('Location: ' . BASE_URL . 'login.php');
exit;
