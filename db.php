<?php
// db.php

$host = 'localhost';         // Your database host
$db   = 'dbfood';            // Your database name
$user = 'root';              // Your database username
$pass = '';                  // Your database password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Enable exceptions
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Disable emulation
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Since headers might already be sent, avoid sending headers here
    // Instead, handle this in place_order.php or wherever db.php is included
    // Optionally, log the error and handle it gracefully in the calling script
    error_log("Database Connection Failed: " . $e->getMessage());
    // It's better to let the calling script handle the response
    exit;
}
?>
