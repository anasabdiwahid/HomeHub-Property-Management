<?php
/**
 * Database Connection using PDO
 * HomeHub Property Management System
 */

declare(strict_types=1);

$db_host = 'localhost';
$db_name = 'homehub_db';
$db_user = 'root';
$db_pass = '';
$db_charset = 'utf8mb4';

$dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // Show friendly message without exposing credentials
    die("<div style='font-family:sans-serif;padding:30px;background:#fff1f0;border:1px solid #ffa39e;border-radius:8px;max-width:600px;margin:50px auto;color:#cf1322;'>
            <h3 style='margin-top:0;'>Database Connection Error</h3>
            <p>Could not connect to the database. Please ensure MySQL is running in XAMPP.</p>
            <p><small style='color:#666;'>Error Details: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</small></p>
         </div>");
}
