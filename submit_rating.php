<?php
session_start();
require 'db.php'; // Ensure this file contains your PDO connection as $pdo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
define('ERROR_LOG_FILE', __DIR__ . '/errors.md');

/**
 * Logs errors in Markdown format to a specified file.
 *
 * @param string $error_message The error message to log.
 * @param string $context Additional context about where the error occurred.
 */
function log_error_markdown($error_message, $context = '')
{
    $timestamp = date('Y-m-d H:i:s');
    $safe_error_message = htmlspecialchars($error_message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $formatted_message = "### [$timestamp] Error\n\n**Message:** $safe_error_message\n\n";
    if ($context) {
        $safe_context = htmlspecialchars($context, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $formatted_message .= "**Context:** $safe_context\n\n";
    }
    $formatted_message .= "---\n\n";
    file_put_contents(ERROR_LOG_FILE, $formatted_message, FILE_APPEND | LOCK_EX);
}

// Custom exception handler
set_exception_handler(function ($exception) {
    log_error_markdown("Uncaught Exception: " . $exception->getMessage(), "File: " . $exception->getFile() . " Line: " . $exception->getLine());
    header("Location: index.php?error=unknown_error");
    exit;
});

// Custom error handler to convert errors to exceptions
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    // Retrieve and sanitize POST data
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $anonymous = isset($_POST['anonymous']) ? 1 : 0;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comments = trim($_POST['comments'] ?? '');

    // Basic validation
    if (empty($full_name) || empty($email) || $rating < 1 || $rating > 5) {
        $_SESSION['rating_error'] = "Please fill in all required fields correctly.";
        header("Location: index.php#ratingsModal");
        exit;
    }

    // Optional: Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['rating_error'] = "Please enter a valid email address.";
        header("Location: index.php#ratingsModal");
        exit;
    }

    try {
        // Prepare and execute the INSERT statement
        $stmt = $pdo->prepare("INSERT INTO ratings (full_name, email, phone, anonymous, rating, comments) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$full_name, $email, $phone ?: null, $anonymous, $rating, $comments ?: null]);

        $_SESSION['rating_success'] = "Thank you for your feedback!";
        header("Location: index.php#ratingsModal");
        exit;
    } catch (PDOException $e) {
        log_error_markdown("Failed to submit rating: " . $e->getMessage(), "Ratings Submission");
        $_SESSION['rating_error'] = "An error occurred while submitting your rating. Please try again later.";
        header("Location: index.php#ratingsModal");
        exit;
    }
} else {
    // Invalid access
    header("Location: index.php");
    exit;
}
