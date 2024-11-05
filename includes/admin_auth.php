<?php
// includes/admin_auth.php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check if the user has the 'Admin' role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    echo '<div class="alert alert-danger">You do not have permission to access this page.</div>';
    echo '<a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>';
    exit();
}
