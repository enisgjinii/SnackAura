<?php
// fetch_new_orders.php
header('Content-Type: application/json');
require_once 'includes/db_connect.php'; // adjust path if needed

// Get the last known order ID from the GET parameter
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

try {
    // Fetch new orders with status 'New Order' and ID greater than last_id
    $stmt = $pdo->prepare("
        SELECT o.* 
        FROM orders o
        WHERE o.status = 'New Order'
          AND o.is_deleted = 0
          AND o.id > ?
        ORDER BY o.id ASC
    ");
    $stmt->execute([$last_id]);
    $new_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status'    => 'success',
        'newOrders' => $new_orders
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
