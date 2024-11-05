<?php
// place_order.php

// Error and Exception handling
set_error_handler('errorHandler');
set_exception_handler('exceptionHandler');

function errorHandler($errno, $errstr, $errfile, $errline)
{
    $message = "Error: [$errno] $errstr - $errfile:$errline";
    logErrorToFile($message);
    // Prevent the PHP internal error handler from running
    return true;
}

function exceptionHandler($exception)
{
    $message = "Uncaught Exception: " . $exception->getMessage();
    $trace = $exception->getTraceAsString();
    logErrorToFile($message . "\n" . $trace);
}

function logErrorToFile($message)
{
    $date = date('Y-m-d H:i:s');
    $markdownMessage = "## [$date] Error\n\n$message\n\n";
    file_put_contents('error_log.md', $markdownMessage, FILE_APPEND);
}

// Disable error display in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require 'db.php';

// Ensure PDO throws exceptions
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Set the response header to JSON
header('Content-Type: application/json');

// Get the raw POST data
$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);

// Validate JSON data
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

// Define required fields
$required_fields = ['customer_name', 'customer_email', 'customer_phone', 'delivery_address', 'cart'];

// Validate required fields
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing or empty field: $field"]);
        exit;
    }
}

// Extract and sanitize data
$customer_name = htmlspecialchars(trim($data['customer_name']), ENT_QUOTES, 'UTF-8');
$customer_email = filter_var(trim($data['customer_email']), FILTER_VALIDATE_EMAIL);
$customer_phone = htmlspecialchars(trim($data['customer_phone']), ENT_QUOTES, 'UTF-8');
$delivery_address = htmlspecialchars(trim($data['delivery_address']), ENT_QUOTES, 'UTF-8');
$cart = isset($data['cart']) ? $data['cart'] : null;

// Additional validation
if (!$customer_email) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email format']);
    exit;
}

if (!is_array($cart) || empty($cart)) {
    http_response_code(400);
    echo json_encode(['error' => 'Cart is empty or invalid']);
    exit;
}

try {
    // Begin Transaction
    $pdo->beginTransaction();

    // Calculate total_amount
    $total_amount = 0.00;
    foreach ($cart as $index => $item) {
        // Validate each cart item
        if (
            !isset($item['id'], $item['quantity'], $item['totalPrice']) ||
            !is_numeric($item['id']) ||
            !is_numeric($item['quantity']) ||
            !is_numeric($item['totalPrice'])
        ) {
            throw new Exception("Invalid cart item at index $index");
        }

        $product_id = intval($item['id']);
        $quantity = intval($item['quantity']);
        $unit_price = floatval($item['totalPrice']);

        if ($unit_price < 0 || $quantity < 1) {
            throw new Exception("Invalid price or quantity for cart item at index $index");
        }

        // Check if product exists
        $stmt_product = $pdo->prepare('SELECT COUNT(*) FROM products WHERE id = ?');
        $stmt_product->execute([$product_id]);
        if ($stmt_product->fetchColumn() == 0) {
            throw new Exception("Product with ID $product_id does not exist.");
        }

        // Validate size_id
        $size_id = isset($item['size']['id']) ? intval($item['size']['id']) : null;
        if ($size_id !== null) {
            $stmt_size = $pdo->prepare('SELECT COUNT(*) FROM sizes WHERE id = ?');
            $stmt_size->execute([$size_id]);
            if ($stmt_size->fetchColumn() == 0) {
                throw new Exception("Size with ID $size_id does not exist.");
            }
        } else {
            throw new Exception("Size ID is required for product ID $product_id");
        }

        $item_total = $unit_price * $quantity;
        $total_amount += $item_total;
    }

    // Insert into orders table
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            user_id, 
            customer_name, 
            customer_email, 
            customer_phone, 
            delivery_address, 
            total_amount, 
            status_id
        ) VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    // Assuming user_id is null for guest orders. Modify if user authentication is implemented.
    $stmt->execute([null, $customer_name, $customer_email, $customer_phone, $delivery_address, $total_amount]);
    $order_id = $pdo->lastInsertId();

    // Prepare statements for efficiency
    $insert_order_item = $pdo->prepare("
        INSERT INTO order_items (
            order_id, 
            product_id, 
            size_id, 
            quantity, 
            price, 
            special_instructions
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");

    $insert_order_extra = $pdo->prepare("
        INSERT INTO order_extras (
            order_item_id, 
            extra_id, 
            quantity, 
            unit_price, 
            total_price
        ) VALUES (?, ?, ?, ?, ?)
    ");

    $insert_order_drink = $pdo->prepare("
        INSERT INTO order_drinks (
            order_item_id, 
            drink_id, 
            quantity, 
            unit_price, 
            total_price
        ) VALUES (?, ?, ?, ?, ?)
    ");

    // Insert each cart item
    foreach ($cart as $item) {
        $product_id = intval($item['id']);
        $size_id = isset($item['size']['id']) ? intval($item['size']['id']) : null;
        $quantity = intval($item['quantity']);
        $unit_price = floatval($item['totalPrice']); // Using 'totalPrice' as unit price
        $special_instructions = isset($item['specialInstructions']) ? htmlspecialchars(trim($item['specialInstructions']), ENT_QUOTES, 'UTF-8') : null;

        // Insert into order_items
        $insert_order_item->execute([
            $order_id,
            $product_id,
            $size_id,
            $quantity,
            $unit_price,
            $special_instructions
        ]);
        $order_item_id = $pdo->lastInsertId();

        // Insert extras if any
        if (isset($item['extras']) && is_array($item['extras'])) {
            foreach ($item['extras'] as $extra) {
                if (
                    !isset($extra['id'], $extra['price']) ||
                    !is_numeric($extra['id']) ||
                    !is_numeric($extra['price'])
                ) {
                    throw new Exception("Invalid extra in cart item ID $product_id");
                }

                $extra_id = intval($extra['id']);
                $extra_quantity = 1; // Assuming quantity 1 for each extra. Modify as needed.
                $extra_price = floatval($extra['price']);
                $extra_total_price = $extra_price * $extra_quantity;

                // Check if extra exists
                $stmt_extra = $pdo->prepare('SELECT COUNT(*) FROM extras WHERE id = ?');
                $stmt_extra->execute([$extra_id]);
                if ($stmt_extra->fetchColumn() == 0) {
                    throw new Exception("Extra with ID $extra_id does not exist.");
                }

                $insert_order_extra->execute([
                    $order_item_id,
                    $extra_id,
                    $extra_quantity,
                    $extra_price,
                    $extra_total_price
                ]);
            }
        }

        // Insert drink if any
        if (isset($item['drink']) && is_array($item['drink'])) {
            if (
                !isset($item['drink']['id'], $item['drink']['price']) ||
                !is_numeric($item['drink']['id']) ||
                !is_numeric($item['drink']['price'])
            ) {
                throw new Exception("Invalid drink in cart item ID $product_id");
            }

            $drink_id = intval($item['drink']['id']);
            $drink_quantity = 1; // Assuming quantity 1 for each drink. Modify as needed.
            $drink_price = floatval($item['drink']['price']);
            $drink_total_price = $drink_price * $drink_quantity;

            // Check if drink exists
            $stmt_drink = $pdo->prepare('SELECT COUNT(*) FROM drinks WHERE id = ?');
            $stmt_drink->execute([$drink_id]);
            if ($stmt_drink->fetchColumn() == 0) {
                throw new Exception("Drink with ID $drink_id does not exist.");
            }

            $insert_order_drink->execute([
                $order_item_id,
                $drink_id,
                $drink_quantity,
                $drink_price,
                $drink_total_price
            ]);
        }
    }

    // Commit Transaction
    $pdo->commit();

    // Respond with success
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Rollback Transaction if active
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log the error
    logErrorToFile("Order Placement Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());

    // Respond with a generic error message
    http_response_code(500);
    echo json_encode(['error' => 'Failed to place order. Please try again.']);
}
