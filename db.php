<?php
// config.php

// Load environment variables (use libraries like vlucas/phpdotenv if needed)
$stripe_secret_key = getenv('sk_test_51QByfJE4KNNCb6nuElXbMZUUan5s9fkJ1N2Ce3fMunhTipH5LGonlnO3bcq6eaxXINmWDuMzfw7RFTNTOb1jDsEm00IzfwoFx2');
$stripe_publishable_key = getenv('pk_test_51QByfJE4KNNCb6nuSnWLZP9JXlW84zG9DnOrQDTHQJvus9D8A8vOA85S4DfRlyWgN0rxa2hHzjppchnrmhyZGflx00B2kKlxym');

// Environment setup
$env = 'local'; // Default to 'local' if not set
if ($env === 'local') {
     $host = '127.0.0.1'; // Use IP instead of 'localhost' for clarity
     $db   = 'yumiis_e';
     $user = 'root';
     $pass = '';
     $port = 3306; // Default MySQL port
} else {
     $host = '127.0.0.1';
     $db   = 'yumiis_e';
     $user = 'ab';
     $pass = 'bUd!d1107';
     $port = 3306;
}

$charset = 'utf8mb4';

// Data Source Name (DSN) for PDO
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
     PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     // echo "Database connection established successfully.";
} catch (\PDOException $e) {
     // Use a generic error message in production
     echo "Database connection failed: " . $e->getMessage();
     error_log("Database connection error: " . $e->getMessage(), 0); // Log error details
}
