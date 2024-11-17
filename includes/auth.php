<?php
// includes/auth.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check if that user_id is deliverer
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'delivery') {
    echo '<div class="alert alert-danger">You do not have permission to access this page.</div>';
    echo '<a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>';
    exit();
}