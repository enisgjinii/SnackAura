<?php
// admin/check_new_orders.php

require_once 'includes/db_connect.php';

// Set Content-Type to JSON
header('Content-Type: application/json');

// Initialize response array
$response = [];

// Retrieve and validate 'last_order_id' parameter
$last_order_id = isset($_GET['last_order_id']) ? (int) $_GET['last_order_id'] : 0;

// Validate 'last_order_id'
if ($last_order_id < 0) {
    $response['error'] = 'Invalid last_order_id parameter.';
    echo json_encode($response);
    exit();
}

try {
    // Fetch the latest order ID in the database
    $stmt = $pdo->query('SELECT MAX(id) AS latest_order_id FROM orders');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $latest_order_id = isset($result['latest_order_id']) ? (int) $result['latest_order_id'] : 0;

    // Count new orders since 'last_order_id'
    if ($last_order_id > 0) {
        $stmt = $pdo->prepare('SELECT COUNT(*) AS new_orders_count FROM orders WHERE id > ?');
        $stmt->execute([$last_order_id]);
        $count_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $new_orders_count = isset($count_result['new_orders_count']) ? (int) $count_result['new_orders_count'] : 0;
    } else {
        // If no 'last_order_id' provided, assume no new orders
        $new_orders_count = 0;
    }

    // Populate response
    $response['latest_order_id'] = $latest_order_id;
    $response['new_orders_count'] = $new_orders_count;
} catch (PDOException $e) {
    // Log the error and respond with an error message
    error_log("Error in check_new_orders.php: " . $e->getMessage());
    $response['error'] = 'Failed to check new orders.';
}

// Return the JSON response
echo json_encode($response);
