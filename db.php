<?php
// Prevent direct access
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

// Load configuration
require_once __DIR__ . '/config.php';

// Get database credentials from config
$host = getConfig('DB_HOST', 'localhost');
$dbname = getConfig('DB_NAME');
$username = getConfig('DB_USER');
$password = getConfig('DB_PASS');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // In production, don't expose database connection details
    if (getConfig('ENVIRONMENT') === 'production') {
        die("Database connection failed. Please contact the administrator.");
    } else {
        die("Connection failed: " . $e->getMessage());
    }
}
?>