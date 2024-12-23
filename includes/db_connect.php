<?php
// admin/includes/db_connect.php
$env = "dev"; // Set to "prod" for production
if ($env === "dev") {
    // Development environment
    $host = 'localhost';
    $db   = 'yumiis_e';
    $user = 'root';     // Replace with your actual database username
    $pass = '';     // Replace with your actual database password
    $charset = 'utf8mb4';
    $port = 3306;
} else {
    $host = '127.0.0.1';
    $db   = 'yumiis_e';
    $user = 'ab';     // Replace with your actual database username
    $pass = 'bUd!d1107';     // Replace with your actual database password
    $charset = 'utf8mb4';
    $port = 8443;
}
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
