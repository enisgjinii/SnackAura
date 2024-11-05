<?php
// admin/fetch_new_orders.php

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
    // Fetch new orders with id > 'last_order_id'
    $stmt = $pdo->prepare('
        SELECT orders.*, order_statuses.status 
        FROM orders 
        JOIN order_statuses ON orders.status_id = order_statuses.id 
        WHERE orders.id > ? 
        ORDER BY orders.created_at ASC
    ');
    $stmt->execute([$last_order_id]);
    $new_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Optionally, you can fetch associated order items, extras, and drinks here
    // For simplicity, only order details are fetched

    // Populate response
    $response['new_orders'] = $new_orders;
} catch (PDOException $e) {
    // Log the error and respond with an error message
    error_log("Error in fetch_new_orders.php: " . $e->getMessage());
    $response['error'] = 'Failed to fetch new orders.';
}

// Return the JSON response
echo json_encode($response);
