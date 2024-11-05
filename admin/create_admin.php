<?php
// admin/create_admin.php
require_once 'includes/db_connect.php';

$username = 'admin';
$password = 'admin123'; // Change this to a secure password
$email = 'admin@example.com';

if ($username === '' || $password === '' || $email === '') {
    die("Username, password, and email are required.");
}

// Hash the password
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

// Insert into users table
$stmt = $pdo->prepare('INSERT INTO users (username, password, email) VALUES (?, ?, ?)');
try {
    $stmt->execute([$username, $hashed_password, $email]);
    echo "Admin user created successfully.";
} catch (PDOException $e) {
    if ($e->getCode() === '23000') { // Integrity constraint violation
        echo "Admin user already exists.";
    } else {
        echo "Error: " . htmlspecialchars($e->getMessage());
    }
}
