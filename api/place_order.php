<?php
// api/place_order.php
header('Content-Type: application/json');

require_once '../admin/includes/db_connect.php';

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate input
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit();
}

// Required fields
$required_fields = ['customer_name', 'customer_email', 'customer_phone', 'location', 'items'];

foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Missing field: $field"]);
        exit();
    }
}

$customer_name = trim($data['customer_name']);
$customer_email = trim($data['customer_email']);
$customer_phone = trim($data['customer_phone']);
$location = trim($data['location']);
$items = $data['items']; // Array of items

// Basic validation
if ($customer_name === '' || $customer_email === '' || $customer_phone === '' || $location === '' || !is_array($items) || count($items) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input data']);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Insert into orders table
    $stmt = $pdo->prepare('INSERT INTO orders (customer_name, customer_email, customer_phone, location, total_amount, created_at) VALUES (?, ?, ?, ?, ?, NOW())');

    // Calculate total amount
    $total_amount = 0;
    foreach ($items as $item) {
        $total_amount += $item['total_price'];
    }

    $stmt->execute([$customer_name, $customer_email, $customer_phone, $location, $total_amount]);
    $order_id = $pdo->lastInsertId();

    // Insert into order_items table
    $stmt_item = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, extras, drink, special_instructions, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)');

    foreach ($items as $item) {
        $product_id = (int) $item['id'];
        $quantity = (int) $item['quantity'];
        $extras = isset($item['extras']) ? json_encode($item['extras']) : null; // Store as JSON
        $drink = isset($item['drink']) ? (int) $item['drink']['id'] : null;
        $special_instructions = isset($item['special_instructions']) ? trim($item['special_instructions']) : null;
        $total_price = (float) $item['total_price'];

        $stmt_item->execute([$order_id, $product_id, $quantity, $extras, $drink, $special_instructions, $total_price]);
    }

    // Commit transaction
    $pdo->commit();

    // Respond with success
    echo json_encode(['success' => true, 'order_id' => $order_id]);
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to place order']);
}
