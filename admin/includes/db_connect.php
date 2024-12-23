<?php
// config.php

// Load environment variables (you can use libraries like vlucas/phpdotenv)
$stripe_secret_key = getenv('STRIPE_SECRET_KEY'); // Ensure this is set in your environment
$stripe_publishable_key = getenv('STRIPE_PUBLISHABLE_KEY'); // Ensure this is set in your environment

$env = 'local'; // Default to 'local' if not set

if ($env === 'local') {
     $host = 'localhost';
     $db   = 'yumiis_e';
     $user = 'root';
     $pass = '';
     $port = 3306; // Change this to 3306 if using the default MySQL port
} else {
     $host = '127.0.0.1';
     $db   = 'yumiis_e';
     $user = 'ab';
     $pass = 'bUd!d1107';
     $port = 3306; // Production MySQL default port
}

$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset;port=$port";
$options = [
     PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     // echo "Database connection established successfully.";
} catch (\PDOException $e) {
     // Log the error and display a generic message for security reasons
     error_log($e->getMessage());
     die("Database connection failed. Please try again later.");
}
