<?php
session_start();
require_once 'includes/db_connect.php'; // same DB connect as used in orders.php

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$last_order_id = isset($_GET['last_order_id']) ? (int)$_GET['last_order_id'] : 0;

try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id > ? ORDER BY id ASC");
    $stmt->execute([$last_order_id]);
    $new_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'new_orders' => $new_orders]);
} catch (PDOException $e) {
    // You might have a function like log_error in a separate file
    // log_error("Failed to fetch new orders: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch new orders.']);
}
