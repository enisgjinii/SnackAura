<?php
// admin/api/submit_order.php
require_once '../includes/db_connect.php';

// Set response header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Extract data
$customer_name = trim($data['customer_name'] ?? '');
$customer_email = trim($data['customer_email'] ?? '');
$customer_phone = trim($data['customer_phone'] ?? '');
$delivery_address = trim($data['delivery_address'] ?? '');
$cart_items = $data['cart_items'] ?? [];
$minimum_order = 5.00; // Fetch from settings if needed

// Validate inputs
if ($customer_name === '' || $customer_email === '' || $customer_phone === '' || $delivery_address === '' || empty($cart_items)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit();
}

// Calculate total amount
$total_amount = 0;
foreach ($cart_items as $item) {
    $total_amount += $item['price'] * $item['quantity'];
}

// Enforce minimum order
if ($total_amount < $minimum_order) {
    echo json_encode(['success' => false, 'message' => 'Minimum order amount is ' . number_format($minimum_order, 2) . '€.']);
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Insert into orders table
    $stmt = $pdo->prepare('INSERT INTO orders (customer_name, customer_email, customer_phone, delivery_address, total_amount) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$customer_name, $customer_email, $customer_phone, $delivery_address, $total_amount]);
    $order_id = $pdo->lastInsertId();

    // Insert into order_items table
    $item_stmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, size_id, quantity, price, extras, special_instructions) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($cart_items as $item) {
        $extras = isset($item['extras']) ? implode(',', $item['extras']) : null;
        $special_instructions = trim($item['special_instructions'] ?? '');
        $item_stmt->execute([
            $order_id,
            $item['product_id'],
            $item['size_id'],
            $item['quantity'],
            $item['price'],
            $extras,
            $special_instructions
        ]);
    }

    // Commit transaction
    $pdo->commit();

    // Respond with success
    echo json_encode(['success' => true, 'message' => 'Order placed successfully!']);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error placing order: ' . htmlspecialchars($e->getMessage())]);
}
