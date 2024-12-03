<?php
// admin/includes/db_connect.php


$env = 'local'; // Default to 'local' if not set
if ($env === 'local') {
    $host = 'localhost';
    $db   = 'yumiis_e';
    $user = 'root';
    $pass = '';
} else {
    $host = '127.0.0.1';
    $db   = 'yumiis_e';
    $user = 'ab';
    $pass = 'bUd!d1107';
}

// Replace with your actual database password
$charset = 'utf8mb4';
$port = 8443;

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Enable exceptions
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Disable emulation
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
