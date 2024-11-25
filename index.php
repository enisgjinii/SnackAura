<?php
ob_start(); // Start output buffering
require 'vendor/autoload.php'; // Ensure Composer's autoload is included
\Stripe\Stripe::setApiKey("sk_test_51QByfJE4KNNCb6nuElXbMZUUan5s9fkJ1N2Ce3fMunhTipH5LGonlnO3bcq6eaxXINmWDuMzfw7RFTNTOb1jDsEm00IzfwoFx2"); // Securely set your Stripe secret key using environment variables
session_start();
require 'db.php'; // Initialize CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
define('ERROR_LOG_FILE', __DIR__ . '/errors.md');
// Function to log errors in Markdown format
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
// Custom error handler
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    throw new ErrorException($message, 0, $severity, $file, $line);
});
include 'settings_fetch.php'; // This is the code we just corrected
// Initialize cart in session
$_SESSION['cart'] = $_SESSION['cart'] ?? [];
// Handle Operational Hours
$is_closed = false;
$notification = [];
$current_datetime = new DateTime();
$current_date = $current_datetime->format('Y-m-d');
$current_day = $current_datetime->format('l');
$current_time = $current_datetime->format('H:i:s');
// Fetch operational hours
$query = "
    SELECT type, is_closed, title, description, date, open_time, close_time, day_of_week
    FROM operational_hours
    WHERE (type = 'holiday' AND date = :current_date)
       OR (type = 'regular' AND day_of_week = :current_day)
    LIMIT 1
";
$stmt = $pdo->prepare($query);
$stmt->execute(['current_date' => $current_date, 'current_day' => $current_day]);
$op_hours = $stmt->fetch(PDO::FETCH_ASSOC);
if ($op_hours) {
    if ($op_hours['type'] === 'holiday' && $op_hours['is_closed']) {
        $is_closed = true;
        $notification = [
            'title' => $op_hours['title'] ?? 'Store Closed',
            'message' => $op_hours['description'] ?? 'The store is currently closed for a holiday.',
            'end_datetime' => (new DateTime($op_hours['date'] . ' ' . $op_hours['close_time']))->format('c')
        ];
    } elseif ($op_hours['type'] === 'regular') {
        if ($op_hours['is_closed'] || $current_time < $op_hours['open_time'] || $current_time > $op_hours['close_time']) {
            $is_closed = true;
            $notification = [
                'title' => 'Store Closed',
                'message' => $op_hours['is_closed'] ? 'The store is currently closed.' : 'The store is currently closed. Our working hours are from ' . date('H:i', strtotime($op_hours['open_time'])) . ' to ' . date('H:i', strtotime($op_hours['close_time'])) . '.',
                'end_datetime' => $op_hours['is_closed'] ? (new DateTime())->modify('+1 day')->format('c') : (new DateTime($current_date . ' ' . $op_hours['close_time']))->format('c')
            ];
        }
    }
}
// Fetch active tips
try {
    $stmt = $pdo->prepare("SELECT * FROM tips WHERE is_active = 1 ORDER BY percentage ASC, amount ASC");
    $stmt->execute();
    $tip_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_error_markdown("Failed to fetch tips: " . $e->getMessage(), "Fetching Tips");
    $tip_options = [];
}
// Handle Tip Selection
if (isset($_GET['select_tip'])) {
    $selected_tip_id = (int)$_GET['select_tip'];
    $tip_exists = false;
    foreach ($tip_options as $tip) {
        if ($tip['id'] === $selected_tip_id) {
            $tip_exists = true;
            break;
        }
    }
    if ($tip_exists || $selected_tip_id === 0) {
        $_SESSION['selected_tip'] = $selected_tip_id === 0 ? null : $selected_tip_id;
    }
    header("Location: index.php");
    exit;
}
// Handle Adding to Cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = max(1, (int)$_POST['quantity']);
    $size_id = isset($_POST['size']) ? (int)$_POST['size'] : null;
    $extras = $_POST['extras'] ?? [];
    $drink_id = isset($_POST['drink']) ? (int)$_POST['drink'] : null;
    $special_instructions = trim($_POST['special_instructions']);
    $selected_sauces = $_POST['sauces'] ?? [];
    // Fetch product details
    $product_query = "
        SELECT p.*, 
               ps.size_id, s.name AS size_name, ps.price AS size_price,
               e.id AS extra_id, e.name AS extra_name, e.price AS extra_price,
               psu.sauce_id
        FROM products p
        LEFT JOIN product_sizes ps ON p.id = ps.product_id
        LEFT JOIN sizes s ON ps.size_id = s.id
        LEFT JOIN product_extras pe ON p.id = pe.product_id
        LEFT JOIN extras e ON pe.extra_id = e.id
        LEFT JOIN product_sauces psu ON p.id = psu.product_id
        WHERE p.id = :product_id AND p.is_active = 1
    ";
    $stmt = $pdo->prepare($product_query);
    $stmt->execute(['product_id' => $product_id]);
    $product_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($product_rows) {
        // Initialize product array
        $product = [
            'id' => $product_rows[0]['id'],
            'name' => $product_rows[0]['name'],
            'description' => $product_rows[0]['description'],
            'image_url' => $product_rows[0]['image_url'],
            'is_new' => $product_rows[0]['is_new'],
            'is_offer' => $product_rows[0]['is_offer'],
            'allergies' => $product_rows[0]['allergies'],
            'base_price' => (float)$product_rows[0]['price'],
            'sizes' => [],
            'extras' => [],
            'sauces' => []
        ];
        // Populate sizes, extras, and sauces
        foreach ($product_rows as $row) {
            if ($row['size_id'] && !isset($product['sizes'][$row['size_id']])) {
                $product['sizes'][$row['size_id']] = [
                    'id' => $row['size_id'],
                    'name' => $row['size_name'],
                    'price' => (float)$row['size_price']
                ];
            }
            if ($row['extra_id'] && !isset($product['extras'][$row['extra_id']])) {
                $product['extras'][$row['extra_id']] = [
                    'id' => $row['extra_id'],
                    'name' => $row['extra_name'],
                    'price' => (float)$row['extra_price']
                ];
            }
            if ($row['sauce_id'] && !in_array($row['sauce_id'], $product['sauces'])) {
                $product['sauces'][] = $row['sauce_id'];
            }
        }
        // Calculate size price
        $size_price = $size_id && isset($product['sizes'][$size_id]) ? $product['sizes'][$size_id]['price'] : 0.00;
        // Calculate extras
        $extras_total = 0.00;
        $extras_details = [];
        if ($extras) {
            foreach ($extras as $extra_id) {
                if (isset($product['extras'][$extra_id])) {
                    $extras_details[] = $product['extras'][$extra_id];
                    $extras_total += $product['extras'][$extra_id]['price'];
                }
            }
        }
        // Fetch drink details
        $drink_details = null;
        $drink_total = 0.00;
        if ($drink_id) {
            $stmt = $pdo->prepare("SELECT * FROM drinks WHERE id = ?");
            $stmt->execute([$drink_id]);
            $drink = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($drink) {
                $drink_details = $drink;
                $drink_total = (float)$drink['price'];
            }
        }
        // Fetch sauces details
        $sauces_details = [];
        $sauces_total = 0.00;
        if ($selected_sauces) {
            $placeholders = implode(',', array_fill(0, count($selected_sauces), '?'));
            $stmt = $pdo->prepare("SELECT * FROM sauces WHERE id IN ($placeholders)");
            $stmt->execute($selected_sauces);
            $sauces = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($sauces as $sauce) {
                $sauces_details[] = $sauce;
                $sauces_total += (float)$sauce['price'];
            }
        }
        // Calculate unit and total price
        $unit_price = $product['base_price'] + $size_price + $extras_total + $drink_total + $sauces_total;
        $total_price = $unit_price * $quantity;
        // Create cart item
        $cart_item = [
            'product_id' => $product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'image_url' => $product['image_url'],
            'size' => $size_id ? $product['sizes'][$size_id]['name'] : null,
            'size_id' => $size_id,
            'extras' => $extras_details,
            'drink' => $drink_details,
            'sauces' => $sauces_details,
            'special_instructions' => $special_instructions,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'total_price' => $total_price,
            'base_price' => $product['base_price']
        ];
        // Check for duplicate items in cart
        $duplicate_index = array_search(true, array_map(function ($item) use ($cart_item) {
            return (
                $item['product_id'] === $cart_item['product_id'] &&
                $item['size_id'] === $cart_item['size_id'] &&
                ($item['drink']['id'] ?? null) === ($cart_item['drink']['id'] ?? null) &&
                $item['special_instructions'] === $cart_item['special_instructions'] &&
                count(array_intersect(array_column($item['extras'], 'id'), array_column($cart_item['extras'], 'id'))) === count($cart_item['extras']) &&
                count(array_intersect(array_column($item['sauces'], 'id'), array_column($cart_item['sauces'], 'id'))) === count($cart_item['sauces'])
            );
        }, $_SESSION['cart']), true);
        if ($duplicate_index !== false) {
            $_SESSION['cart'][$duplicate_index]['quantity'] += $quantity;
            $_SESSION['cart'][$duplicate_index]['total_price'] += $total_price;
        } else {
            $_SESSION['cart'][] = $cart_item;
        }
        header("Location: index.php?added=1");
        exit;
    } else {
        log_error_markdown("Product not found or inactive. Product ID: $product_id", "Adding to Cart");
        header("Location: index.php?error=invalid_product");
        exit;
    }
}
// Handle Removing from Cart
if (isset($_GET['remove'])) {
    $remove_index = (int)$_GET['remove'];
    if (isset($_SESSION['cart'][$remove_index])) {
        unset($_SESSION['cart'][$remove_index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
    } else {
        log_error_markdown("Attempted to remove non-existent cart item at index: $remove_index", "Remove from Cart");
    }
    header("Location: index.php?removed=1");
    exit;
}
// Handle Updating Cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $item_index = (int)$_POST['item_index'];
    if (isset($_SESSION['cart'][$item_index])) {
        $quantity = max(1, (int)$_POST['quantity']);
        $size_id = isset($_POST['size']) ? (int)$_POST['size'] : null;
        $extras = $_POST['extras'] ?? [];
        $drink_id = isset($_POST['drink']) ? (int)$_POST['drink'] : null;
        $special_instructions = trim($_POST['special_instructions']);
        $selected_sauces = $_POST['sauces'] ?? [];
        $product_id = $_SESSION['cart'][$item_index]['product_id'];
        // Fetch product details
        $product_query = "
            SELECT p.*, 
                   ps.size_id, s.name AS size_name, ps.price AS size_price,
                   e.id AS extra_id, e.name AS extra_name, e.price AS extra_price,
                   psu.sauce_id
            FROM products p
            LEFT JOIN product_sizes ps ON p.id = ps.product_id
            LEFT JOIN sizes s ON ps.size_id = s.id
            LEFT JOIN product_extras pe ON p.id = pe.product_id
            LEFT JOIN extras e ON pe.extra_id = e.id
            LEFT JOIN product_sauces psu ON p.id = psu.product_id
            WHERE p.id = :product_id AND p.is_active = 1
        ";
        $stmt = $pdo->prepare($product_query);
        $stmt->execute(['product_id' => $product_id]);
        $product_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($product_rows) {
            // Initialize product array
            $product = [
                'id' => $product_rows[0]['id'],
                'name' => $product_rows[0]['name'],
                'description' => $product_rows[0]['description'],
                'image_url' => $product_rows[0]['image_url'],
                'is_new' => $product_rows[0]['is_new'],
                'is_offer' => $product_rows[0]['is_offer'],
                'allergies' => $product_rows[0]['allergies'],
                'base_price' => (float)$product_rows[0]['price'],
                'sizes' => [],
                'extras' => [],
                'sauces' => []
            ];
            // Populate sizes, extras, and sauces
            foreach ($product_rows as $row) {
                if ($row['size_id'] && !isset($product['sizes'][$row['size_id']])) {
                    $product['sizes'][$row['size_id']] = [
                        'id' => $row['size_id'],
                        'name' => $row['size_name'],
                        'price' => (float)$row['size_price']
                    ];
                }
                if ($row['extra_id'] && !isset($product['extras'][$row['extra_id']])) {
                    $product['extras'][$row['extra_id']] = [
                        'id' => $row['extra_id'],
                        'name' => $row['extra_name'],
                        'price' => (float)$row['extra_price']
                    ];
                }
                if ($row['sauce_id'] && !in_array($row['sauce_id'], $product['sauces'])) {
                    $product['sauces'][] = $row['sauce_id'];
                }
            }
            // Calculate size price
            $size_price = $size_id && isset($product['sizes'][$size_id]) ? $product['sizes'][$size_id]['price'] : 0.00;
            // Calculate extras
            $extras_total = 0.00;
            $extras_details = [];
            if ($extras) {
                foreach ($extras as $extra_id) {
                    if (isset($product['extras'][$extra_id])) {
                        $extras_details[] = $product['extras'][$extra_id];
                        $extras_total += $product['extras'][$extra_id]['price'];
                    }
                }
            }
            // Fetch drink details
            $drink_details = null;
            $drink_total = 0.00;
            if ($drink_id) {
                $stmt = $pdo->prepare("SELECT * FROM drinks WHERE id = ?");
                $stmt->execute([$drink_id]);
                $drink = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($drink) {
                    $drink_details = $drink;
                    $drink_total = (float)$drink['price'];
                }
            }
            // Fetch sauces details
            $sauces_details = [];
            $sauces_total = 0.00;
            if ($selected_sauces) {
                $placeholders = implode(',', array_fill(0, count($selected_sauces), '?'));
                $stmt = $pdo->prepare("SELECT * FROM sauces WHERE id IN ($placeholders)");
                $stmt->execute($selected_sauces);
                $sauces = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($sauces as $sauce) {
                    $sauces_details[] = $sauce;
                    $sauces_total += (float)$sauce['price'];
                }
            }
            // Calculate unit and total price
            $unit_price = $product['base_price'] + $size_price + $extras_total + $drink_total + $sauces_total;
            $total_price = $unit_price * $quantity;
            // Update cart item
            $_SESSION['cart'][$item_index] = [
                'product_id' => $product['id'],
                'name' => $product['name'],
                'description' => $product['description'],
                'image_url' => $product['image_url'],
                'size' => $size_id ? $product['sizes'][$size_id]['name'] : null,
                'size_id' => $size_id,
                'extras' => $extras_details,
                'drink' => $drink_details,
                'sauces' => $sauces_details,
                'special_instructions' => $special_instructions,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'total_price' => $total_price,
                'base_price' => $product['base_price']
            ];
            header("Location: index.php?updated=1");
            exit;
        } else {
            log_error_markdown("Product not found or inactive during cart update. Product ID: $product_id", "Updating Cart");
            header("Location: index.php?error=invalid_update");
            exit;
        }
    } else {
        log_error_markdown("Invalid cart update request. Item index: $item_index", "Updating Cart");
        header("Location: index.php?error=invalid_update");
        exit;
    }
}
// Handle Checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    if (empty($_SESSION['cart'])) {
        header("Location: index.php?error=empty_cart");
        exit;
    }
    // CSRF Token Validation
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        header("Location: index.php?error=invalid_csrf_token");
        exit;
    }
    // Fetch and validate customer details
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $selected_tip_id = isset($_POST['selected_tip']) ? (int)$_POST['selected_tip'] : null;
    // Check if 'is_event' checkbox was selected
    $is_event = isset($_POST['is_event']) && $_POST['is_event'] == '1';
    // Retrieve and validate scheduled date and time
    $scheduled_date = $_POST['scheduled_date'] ?? '';
    $scheduled_time = $_POST['scheduled_time'] ?? '';
    if ($is_event) {
        if (empty($scheduled_date) || empty($scheduled_time)) {
            header("Location: index.php?error=missing_scheduled_time");
            exit;
        }
        // Validate that scheduled_date and scheduled_time are in the future
        $scheduled_datetime = DateTime::createFromFormat('Y-m-d H:i', "$scheduled_date $scheduled_time");
        if (!$scheduled_datetime) {
            header("Location: index.php?error=invalid_scheduled_datetime");
            exit;
        }
        $current_datetime = new DateTime();
        if ($scheduled_datetime < $current_datetime) {
            header("Location: index.php?error=invalid_scheduled_time");
            exit;
        }
    } else {
        // If not an event, set scheduled_date and scheduled_time to null or defaults
        $scheduled_date = null;
        $scheduled_time = null;
    }
    // Validate payment method
    $payment_method = $_POST['payment_method'] ?? '';
    $allowed_payment_methods = ['stripe', 'pickup', 'cash'];
    if (!in_array($payment_method, $allowed_payment_methods)) {
        header("Location: index.php?error=invalid_payment_method");
        exit;
    }
    // Validate order details
    if (empty($customer_name) || empty($customer_email) || empty($customer_phone) || empty($delivery_address)) {
        header("Location: index.php?error=invalid_order_details");
        exit;
    }
    // Calculate cart total
    $cart_total = array_reduce($_SESSION['cart'], fn($carry, $item) => $carry + $item['total_price'], 0.00);
    // Calculate tip amount
    $tip_amount = 0.00;
    if ($selected_tip_id) {
        foreach ($tip_options as $tip) {
            if ($tip['id'] == $selected_tip_id) {
                if ($tip['percentage']) {
                    $tip_amount = $cart_total * ($tip['percentage'] / 100);
                } elseif ($tip['amount']) {
                    $tip_amount = $tip['amount'];
                }
                break;
            }
        }
    }
    // Calculate total amount
    $total_amount = $cart_total + $tip_amount;
    try {
        // Begin transaction
        $pdo->beginTransaction();
        // Insert into orders table
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, customer_name, customer_email, customer_phone, delivery_address, total_amount, status_id, tip_id, tip_amount, scheduled_date, scheduled_time, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([null, $customer_name, $customer_email, $customer_phone, $delivery_address, $total_amount, 2, $selected_tip_id, $tip_amount, $scheduled_date, $scheduled_time, $payment_method]);
        $order_id = $pdo->lastInsertId();
        // Prepare statements for order items, extras, and drinks
        $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, product_id, size_id, quantity, price, extras, special_instructions) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_extras = $pdo->prepare("INSERT INTO order_extras (order_item_id, extra_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
        $stmt_drinks = $pdo->prepare("INSERT INTO order_drinks (order_item_id, drink_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
        foreach ($_SESSION['cart'] as $item) {
            // Serialize extras as comma-separated IDs
            $extras_ids = $item['extras'] ? implode(',', array_column($item['extras'], 'id')) : null;
            // Serialize sauces as comma-separated names
            $sauces_names = $item['sauces'] ? implode(', ', array_column($item['sauces'], 'name')) : null;
            // Combine sauces and special instructions
            $combined_instructions = $sauces_names ? ($sauces_names . ($item['special_instructions'] ? "; " . $item['special_instructions'] : "")) : $item['special_instructions'];
            // Insert into order_items
            $stmt_item->execute([
                $order_id,
                $item['product_id'],
                $item['size_id'],
                $item['quantity'],
                $item['unit_price'],
                $extras_ids,
                $combined_instructions
            ]);
            $order_item_id = $pdo->lastInsertId();
            // Insert into order_extras
            foreach ($item['extras'] as $extra) {
                $stmt_extras->execute([
                    $order_item_id,
                    $extra['id'],
                    1,
                    $extra['price'],
                    $extra['price']
                ]);
            }
            // Insert into order_drinks
            if ($item['drink']) {
                $stmt_drinks->execute([
                    $order_item_id,
                    $item['drink']['id'],
                    1,
                    $item['drink']['price'],
                    $item['drink']['price']
                ]);
            }
        }
        // Process payment based on payment method
        if ($payment_method === 'stripe') {
            // Retrieve the Payment Method ID from POST data
            $stripe_payment_method = $_POST['stripe_payment_method'] ?? '';
            if (empty($stripe_payment_method)) {
                throw new Exception("Stripe payment method ID is missing.");
            }
            // Calculate the amount in cents
            $amount_cents = intval(round($total_amount * 100));
            // Define your return_url dynamically based on scheduled_date and scheduled_time
            $return_url = 'http://' . $_SERVER['HTTP_HOST'] . '/index.php?order=success&scheduled_date=' . urlencode($scheduled_date) . '&scheduled_time=' . urlencode($scheduled_time);
            // Create a PaymentIntent with Stripe, including return_url
            $payment_intent = \Stripe\PaymentIntent::create([
                'amount' => $amount_cents,
                'currency' => 'eur', // Change to your currency
                'payment_method' => $stripe_payment_method,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'description' => "Order ID: $order_id",
                'metadata' => [
                    'order_id' => $order_id
                ],
                'return_url' => $return_url, // Added return_url
            ]);
            // Handle Payment Intent status
            if ($payment_intent->status == 'requires_action' && $payment_intent->next_action->type == 'use_stripe_sdk') {
                // Tell the client to handle the action
                header('Content-Type: application/json');
                echo json_encode([
                    'requires_action' => true,
                    'payment_intent_client_secret' => $payment_intent->client_secret
                ]);
                exit;
            } elseif ($payment_intent->status == 'succeeded') {
                // The payment is complete, update order status to 'Paid'
                $stmt = $pdo->prepare("UPDATE orders SET status_id = ? WHERE id = ?");
                $stmt->execute([2, $order_id]); // Assuming status_id 5 is 'Paid'
                // Commit transaction before sending response
                $pdo->commit();
                // Clear cart and selected tip
                $_SESSION['cart'] = [];
                $_SESSION['selected_tip'] = null;
                // Send JSON response indicating success and redirect URL
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'redirect_url' => $return_url
                ]);
                exit;
            } else {
                // Invalid status
                throw new Exception("Invalid PaymentIntent status: " . $payment_intent->status);
            }
        } elseif ($payment_method === 'pickup') {
            // Set order status to 'Ready for Pickup'
            $new_status_id = 2; // Example: 3 represents 'Ready for Pickup'
            $stmt = $pdo->prepare("UPDATE orders SET status_id = ? WHERE id = ?");
            $stmt->execute([$new_status_id, $order_id]);
        } elseif ($payment_method === 'cash') {
            // Set order status to 'Pending Cash Payment'
            $new_status_id = 2; // Example: 4 represents 'Pending Cash Payment'
            $stmt = $pdo->prepare("UPDATE orders SET status_id = ? WHERE id = ?");
            $stmt->execute([$new_status_id, $order_id]);
        }
        // Commit transaction after successful payment and status update
        if ($payment_method !== 'stripe') {
            $pdo->commit();
            // Clear cart and selected tip
            $_SESSION['cart'] = [];
            $_SESSION['selected_tip'] = null;
            // Redirect to success page
            header("Location: index.php?order=success&scheduled_date=$scheduled_date&scheduled_time=$scheduled_time");
            exit;
        }
    } catch (Exception $e) {
        // Check if a transaction is active before attempting to rollback
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        log_error_markdown("Order Placement Failed: " . $e->getMessage(), "Checkout Process");
        header("Location: index.php?error=order_failed");
        exit;
    }
}
// Fetch categories
$stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name ASC");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Determine selected category
$selected_category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
// Fetch products based on category
$product_query = "
    SELECT 
        p.id AS product_id, p.name AS product_name, p.description, p.image_url, p.is_new, p.is_offer, p.allergies, p.price AS base_price,
        ps.size_id, s.name AS size_name, ps.price AS size_price,
        e.id AS extra_id, e.name AS extra_name, e.price AS extra_price,
        psu.sauce_id
    FROM products p
    LEFT JOIN product_sizes ps ON p.id = ps.product_id
    LEFT JOIN sizes s ON ps.size_id = s.id
    LEFT JOIN product_extras pe ON p.id = pe.product_id
    LEFT JOIN extras e ON pe.extra_id = e.id
    LEFT JOIN product_sauces psu ON p.id = psu.product_id
    WHERE p.is_active = 1" . ($selected_category_id > 0 ? " AND p.category_id = :category_id" : "") . " 
    ORDER BY p.name ASC, s.name ASC, e.name ASC
";
$stmt = $pdo->prepare($product_query);
if ($selected_category_id > 0) {
    $stmt->bindParam(':category_id', $selected_category_id, PDO::PARAM_INT);
}
$stmt->execute();
$raw_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Organize products with sizes, extras, and sauces
$products = [];
foreach ($raw_products as $row) {
    $pid = $row['product_id'];
    if (!isset($products[$pid])) {
        $products[$pid] = [
            'id' => $pid,
            'name' => $row['product_name'],
            'description' => $row['description'],
            'image_url' => $row['image_url'],
            'is_new' => $row['is_new'],
            'is_offer' => $row['is_offer'],
            'allergies' => $row['allergies'],
            'base_price' => (float)$row['base_price'],
            'sauces' => [],
            'sizes' => [],
            'extras' => []
        ];
    }
    if ($row['size_id'] && !isset($products[$pid]['sizes'][$row['size_id']])) {
        $products[$pid]['sizes'][$row['size_id']] = [
            'id' => $row['size_id'],
            'name' => $row['size_name'],
            'price' => (float)$row['size_price']
        ];
    }
    if ($row['extra_id'] && !isset($products[$pid]['extras'][$row['extra_id']])) {
        $products[$pid]['extras'][$row['extra_id']] = [
            'id' => $row['extra_id'],
            'name' => $row['extra_name'],
            'price' => (float)$row['extra_price']
        ];
    }
    if ($row['sauce_id'] && !in_array($row['sauce_id'], $products[$pid]['sauces'])) {
        $products[$pid]['sauces'][] = $row['sauce_id'];
    }
}
foreach ($products as &$product) {
    $product['sizes'] = array_values($product['sizes']);
    $product['extras'] = array_values($product['extras']);
}
unset($product);
// Fetch sauce details
$sauce_details = [];
$all_sauce_ids = array_unique(array_merge(...array_map(fn($p) => $p['sauces'], $products)));
if ($all_sauce_ids) {
    $placeholders = implode(',', array_fill(0, count($all_sauce_ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM sauces WHERE id IN ($placeholders)");
    $stmt->execute($all_sauce_ids);
    $sauce_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sauce_rows as $sauce_row) {
        $sauce_details[$sauce_row['id']] = $sauce_row;
    }
}
// Fetch drinks
$stmt = $pdo->prepare("SELECT * FROM drinks ORDER BY name ASC");
$stmt->execute();
$drinks = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Calculate cart total with selected tip
$cart_total = array_reduce($_SESSION['cart'], fn($carry, $item) => $carry + $item['total_price'], 0.00);
$selected_tip = $_SESSION['selected_tip'] ?? null;
$tip_amount = 0.00;
if ($selected_tip) {
    foreach ($tip_options as $tip) {
        if ($tip['id'] == $selected_tip) {
            if ($tip['percentage']) {
                $tip_amount = $cart_total * ($tip['percentage'] / 100);
            } elseif ($tip['amount']) {
                $tip_amount = $tip['amount'];
            }
            break;
        }
    }
}
$cart_total_with_tip = $cart_total + $tip_amount;
// Fetch banners
try {
    $stmt = $pdo->prepare("SELECT * FROM banners WHERE is_active = 1 ORDER BY created_at DESC");
    $stmt->execute();
    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_error_markdown("Failed to fetch banners: " . $e->getMessage(), "Fetching Banners");
    $banners = [];
}
// Fetch active offers
try {
    $current_date = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT * FROM offers WHERE is_active = 1 AND (start_date IS NULL OR start_date <= ?) AND (end_date IS NULL OR end_date >= ?) ORDER BY created_at DESC");
    $stmt->execute([$current_date, $current_date]);
    $active_offers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_error_markdown("Failed to fetch offers: " . $e->getMessage(), "Fetching Offers");
    $active_offers = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Delivery</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Saira:wght@100..900&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Flag Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/6.6.6/css/flag-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .promo-banner .carousel-item img {
            height: 400px;
            object-fit: cover;
        }

        .offers-section .card-img-top {
            height: 200px;
            object-fit: cover;
        }

        .offers-section .card-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .offers-section .card-text {
            font-size: 0.95rem;
            color: #555;
        }

        @media (max-width: 768px) {
            .promo-banner .carousel-item img {
                height: 250px;
            }

            .offers-section .card-img-top {
                height: 150px;
            }
        }

        .btn.disabled,
        .btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        .language-switcher {
            position: absolute;
            top: 10px;
            right: 10px;
        }

        .order-summary {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            position: sticky;
            top: 20px;
        }

        .order-title {
            margin-bottom: 15px;
        }
    </style>
    <script src="https://js.stripe.com/v3/"></script>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay" aria-hidden="true">
        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
    </div>
    <!-- Store Closed Modal -->
    <div class="modal fade" id="storeClosedModal" tabindex="-1" aria-labelledby="storeClosedModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="storeClosedModalLabel">Store Closed</h5>
                </div>
                <div class="modal-body">
                    <p id="storeClosedMessage">The store is currently closed. Please wait until we reopen.</p>
                    <p id="storeClosedEndTime" class="text-muted"></p>
                </div>
            </div>
        </div>
    </div>
    <!-- Edit Cart Modals -->
    <?php foreach ($_SESSION['cart'] as $index => $item): ?>
        <div class="modal fade" id="editCartModal<?= $index ?>" tabindex="-1" aria-labelledby="editCartModalLabel<?= $index ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="index.php">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editCartModalLabel<?= $index ?>">Edit <?= htmlspecialchars($item['name']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="quantity<?= $index ?>" class="form-label">Quantity</label>
                                <input type="number" class="form-control" id="quantity<?= $index ?>" name="quantity" value="<?= htmlspecialchars($item['quantity']) ?>" min="1" max="99" required>
                            </div>
                            <?php if (!empty($products[$item['product_id']]['sizes'])): ?>
                                <div class="mb-3">
                                    <label for="size<?= $index ?>" class="form-label">Size</label>
                                    <select class="form-select" id="size<?= $index ?>" name="size" required>
                                        <option value="">Choose a size</option>
                                        <?php foreach ($products[$item['product_id']]['sizes'] as $size): ?>
                                            <option value="<?= htmlspecialchars($size['id']) ?>" <?= ($item['size_id'] === (int)$size['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($size['name']) ?> (+<?= number_format($size['price'], 2) ?>€)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <?php if ($products[$item['product_id']]['extras']): ?>
                                <div class="mb-3">
                                    <label class="form-label">Extras</label>
                                    <div>
                                        <?php foreach ($products[$item['product_id']]['extras'] as $extra): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="edit_extra<?= $index ?>_<?= $extra['id'] ?>" name="extras[]" value="<?= htmlspecialchars($extra['id']) ?>" <?= in_array($extra['id'], array_column($item['extras'], 'id')) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="edit_extra<?= $index ?>_<?= $extra['id'] ?>"><?= htmlspecialchars($extra['name']) ?> (+<?= number_format($extra['price'], 2) ?>€)</label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($products[$item['product_id']]['sauces']): ?>
                                <div class="mb-3">
                                    <label class="form-label">Sauces</label>
                                    <div>
                                        <?php foreach ($products[$item['product_id']]['sauces'] as $sauce_id): ?>
                                            <?php if (isset($sauce_details[$sauce_id])): ?>
                                                <?php $sauce = $sauce_details[$sauce_id]; ?>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="edit_sauce<?= $index ?>_<?= $sauce['id'] ?>" name="sauces[]" value="<?= htmlspecialchars($sauce['id']) ?>" <?= in_array($sauce['id'], array_column($item['sauces'], 'id')) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="edit_sauce<?= $index ?>_<?= $sauce['id'] ?>"><?= htmlspecialchars($sauce['name']) ?> (+<?= number_format($sauce['price'], 2) ?>€)</label>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($drinks): ?>
                                <div class="mb-3">
                                    <label class="form-label">Drinks</label>
                                    <select class="form-select" name="drink">
                                        <option value="">Choose a drink</option>
                                        <?php foreach ($drinks as $drink): ?>
                                            <option value="<?= htmlspecialchars($drink['id']) ?>" <?= ($item['drink']['id'] ?? null) === $drink['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($drink['name']) ?> (+<?= number_format($drink['price'], 2) ?>€)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div class="mb-3">
                                <label for="special_instructions<?= $index ?>" class="form-label">Special Instructions</label>
                                <textarea class="form-control" id="special_instructions<?= $index ?>" name="special_instructions" rows="2" placeholder="Any special requests?"><?= htmlspecialchars($item['special_instructions']) ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <input type="hidden" name="item_index" value="<?= $index ?>">
                            <input type="hidden" name="update_cart" value="1">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Cart</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <!-- Header with Navbar -->
    <header class="position-relative">
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container d-flex justify-content-between align-items-center">
                <a class="d-flex align-items-center" href="index.php">
                    <!-- Display Cart Logo -->
                    <?php if (!empty($cart_logo) && file_exists($cart_logo)): ?>
                        <div class="mb-3 text-center">
                            <img src="<?= htmlspecialchars($cart_logo) ?>" alt="Cart Logo" style="width: 100%; height: 80px;object-fit: cover" id="cart-logo" loading="lazy" onerror="this.src='https://via.placeholder.com/150?text=Logo';">
                        </div>
                    <?php endif; ?>
                    <!-- <span class="restaurant-name ms-2">Restaurant</span> -->
                </a>

                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-primary position-relative me-3 btn-add-to-cart" type="button" id="cart-button" aria-label="View Cart" data-bs-toggle="modal" data-bs-target="#cartModal">
                        <i class="bi bi-cart fs-4"></i>
                        <?php if (count($_SESSION['cart']) > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cart-count">
                                <?= count($_SESSION['cart']) ?>
                                <span class="visually-hidden">items in cart</span>
                            </span>
                        <?php endif; ?>
                    </button>
                    <button type="button" class="btn btn-outline-success me-3" data-bs-toggle="modal" data-bs-target="#reservationModal">
                        <i class="bi bi-calendar-plus fs-4"></i> Rezervo
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ratingsModal">
                        <i class="bi bi-star fs-4"></i>
                    </button>
                    <span class="fw-bold fs-5 ms-3" id="cart-total"><?= number_format($cart_total_with_tip, 2) ?>€</span>
                </div>
            </div>
        </nav>
        <div class="language-switcher">
            <a href="#" class="me-2"><span class="flag-icon flag-icon-us"></span></a>
            <a href="#"><span class="flag-icon flag-icon-al"></span></a>
        </div>
    </header>
    <!-- Reservation Modal -->
    <div class="modal fade" id="reservationModal" tabindex="-1" aria-labelledby="reservationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="reservationForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="reservationModalLabel">Bëj Rezervim</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Mbyll"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="reservation_name" class="form-label">Emri <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="reservation_name" name="client_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="reservation_email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="reservation_email" name="client_email" required>
                        </div>
                        <div class="mb-3">
                            <label for="reservation_date" class="form-label">Data e Rezervimit <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="reservation_date" name="reservation_date" required min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="mb-3">
                            <label for="reservation_time" class="form-label">Ora e Rezervimit <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="reservation_time" name="reservation_time" required>
                        </div>
                        <div class="mb-3">
                            <label for="number_of_people" class="form-label">Numri i Njerëzve <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="number_of_people" name="number_of_people" required min="1">
                        </div>
                        <div class="mb-3">
                            <label for="reservation_message" class="form-label">Mesazh (Opsionale)</label>
                            <textarea class="form-control" id="reservation_message" name="reservation_message" rows="3" placeholder="Shkruani ndonjë kërkesë të veçantë..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anulo</button>
                        <button type="submit" class="btn btn-success">Bëj Rezervim</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Promotional Banners Carousel -->
    <?php if (!empty($banners)): ?>
        <section class="promo-banner">
            <div id="bannersCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-indicators">
                    <?php foreach ($banners as $index => $banner): ?>
                        <button type="button" data-bs-target="#bannersCarousel" data-bs-slide-to="<?= $index ?>" class="<?= $index === 0 ? 'active' : '' ?>" aria-current="<?= $index === 0 ? 'true' : 'false' ?>" aria-label="Slide <?= $index + 1 ?>"></button>
                    <?php endforeach; ?>
                </div>
                <div class="carousel-inner">
                    <?php foreach ($banners as $index => $banner): ?>
                        <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                            <a href="<?= htmlspecialchars($banner['link'] ?? '#') ?>">
                                <img src="./admin/uploads/banners/<?= htmlspecialchars($banner['image']) ?>" class="d-block w-100" alt="<?= htmlspecialchars($banner['title']) ?>" loading="lazy" onerror="this.src='https://via.placeholder.com/1200x400?text=Banner+Image';">
                            </a>
                            <div class="carousel-caption d-none d-md-block">
                                <h5><?= htmlspecialchars($banner['title']) ?></h5>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($banners) > 1): ?>
                    <button class="carousel-control-prev" type="button" data-bs-target="#bannersCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#bannersCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
    <!-- Special Offers Section -->
    <?php if (!empty($active_offers)): ?>
        <section class="offers-section py-5">
            <div class="container">
                <h2 class="mb-4 text-center">Special Offers</h2>
                <div class="row g-4">
                    <?php foreach ($active_offers as $offer): ?>
                        <div class="col-md-4">
                            <div class="card h-100 shadow-sm position-relative">
                                <?php if ($offer['image'] && file_exists($offer['image'])): ?>
                                    <a href="offers.php?action=view&id=<?= htmlspecialchars($offer['id']) ?>">
                                        <img src="<?= htmlspecialchars($offer['image']) ?>" class="card-img-top" alt="<?= htmlspecialchars($offer['title']) ?>" loading="lazy" onerror="this.src='https://via.placeholder.com/600x400?text=Offer+Image';">
                                    </a>
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/600x400?text=No+Image" class="card-img-top" alt="No Image Available" loading="lazy">
                                <?php endif; ?>
                                <?php if ($offer['is_active']): ?>
                                    <span class="badge bg-success position-absolute top-0 end-0 m-2">Active</span>
                                <?php endif; ?>
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?= htmlspecialchars($offer['title']) ?></h5>
                                    <p class="card-text"><?= nl2br(htmlspecialchars($offer['description'])) ?></p>
                                    <div class="mt-auto">
                                        <a href="offers.php?action=view&id=<?= htmlspecialchars($offer['id']) ?>" class="btn btn-primary w-100">
                                            View Offer
                                        </a>
                                    </div>
                                </div>
                                <div class="card-footer text-muted">
                                    <?= htmlspecialchars($offer['start_date'] ?? 'N/A') ?> - <?= htmlspecialchars($offer['end_date'] ?? 'N/A') ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
    <!-- Category Tabs -->
    <ul class="nav nav-tabs justify-content-center my-4" id="categoryTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= ($selected_category_id === 0) ? 'active' : '' ?>" href="index.php" role="tab">All</a>
        </li>
        <?php foreach ($categories as $category): ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= ($selected_category_id === (int)$category['id']) ? 'active' : '' ?>" href="index.php?category_id=<?= htmlspecialchars($category['id']) ?>" role="tab"><?= htmlspecialchars($category['name']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <!-- Main Content -->
    <main class="container my-5">
        <div class="row">
            <!-- Products Section -->
            <div class="col-lg-9">
                <div class="row g-4">
                    <?php if ($products): ?>
                        <?php foreach ($products as $product): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card menu-item h-100 shadow-sm">
                                    <div class="image-container position-relative">
                                        <img src="<?= htmlspecialchars($product['image_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($product['name']) ?>" onerror="this.src='https://via.placeholder.com/600x400?text=Product+Image';">
                                        <?php if ($product['is_new']): ?>
                                            <span class="badge bg-success position-absolute top-0 end-0 m-2">New</span>
                                        <?php endif; ?>
                                        <?php if ($product['is_offer']): ?>
                                            <span class="badge bg-warning text-dark position-absolute top-40 end-0 m-2">Offer</span>
                                        <?php endif; ?>
                                        <?php if ($product['allergies']): ?>
                                            <button type="button" class="btn btn-sm btn-danger position-absolute" style="top: 10px; left: 10px;" data-bs-toggle="modal" data-bs-target="#allergiesModal<?= $product['id'] ?>">
                                                <i class="bi bi-exclamation-triangle-fill"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title"><?= htmlspecialchars($product['name']) ?></h5>
                                        <p class="card-text"><?= htmlspecialchars($product['description']) ?></p>
                                        <div class="mt-auto d-flex justify-content-between align-items-center">
                                            <strong><?= !empty($product['sizes']) ? number_format($product['sizes'][0]['price'], 2) . '€' : number_format($product['base_price'], 2) . '€' ?></strong>
                                            <button class="btn btn-sm btn-primary btn-add-to-cart" data-bs-toggle="modal" data-bs-target="#addToCartModal<?= $product['id'] ?>"><i class="bi bi-cart-plus me-1"></i> Add to Cart</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Add to Cart Modal -->
                            <div class="modal fade" id="addToCartModal<?= $product['id'] ?>" tabindex="-1" aria-labelledby="addToCartModalLabel<?= $product['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-xl modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" action="index.php">
                                            <!-- <div class="modal-header">
                                                <h5 class="modal-title" id="addToCartModalLabel<?= $product['id'] ?>">Add <?= htmlspecialchars($product['name']) ?> to Cart</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div> -->
                                            <div class="modal-body">
                                                <!-- Product Details -->
                                                <div class="row">
                                                    <div class="col-4">
                                                        <img src="<?= htmlspecialchars($product['image_url']) ?>" class="img-fluid rounded shadow" alt="<?= htmlspecialchars($product['name']) ?>" onerror="this.src='https://via.placeholder.com/600x400?text=Product+Image';">
                                                    </div>
                                                    <div class="col-8">
                                                        <!-- All Uppercase -->
                                                        <h2 class="text-uppercase"><b><?= htmlspecialchars($product['name']) ?></b></h2>
                                                        <hr style="width: 10%;border: 2px solid black;  border-radius: 5px;">
                                                        <h3 style="color:#d3b213"><b><?= !empty($product['sizes']) ? number_format($product['sizes'][0]['price'], 2) . '€' : number_format($product['base_price'], 2) . '€' ?></b></h3>
                                                        <?php if (!empty($product['sizes'])): ?>
                                                            <div class="mb-3">
                                                                <label class="form-label">Size</label>
                                                                <select class="form-select" name="size" required>
                                                                    <option value="">Choose a size</option>
                                                                    <?php foreach ($product['sizes'] as $size): ?>
                                                                        <option value="<?= htmlspecialchars($size['id']) ?>"><?= htmlspecialchars($size['name']) ?> (+<?= number_format($size['price'], 2) ?>€)</option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ($product['extras']): ?>
                                                            <div class="mb-3">
                                                                <label class="form-label">Extras</label>
                                                                <div>
                                                                    <?php foreach ($product['extras'] as $extra): ?>
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" type="checkbox" id="extra<?= $product['id'] ?>_<?= $extra['id'] ?>" name="extras[]" value="<?= htmlspecialchars($extra['id']) ?>">
                                                                            <label class="form-check-label" for="extra<?= $product['id'] ?>_<?= $extra['id'] ?>"><?= htmlspecialchars($extra['name']) ?> (+<?= number_format($extra['price'], 2) ?>€)</label>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ($product['sauces']): ?>
                                                            <div class="mb-3">
                                                                <label class="form-label">Sauces</label>
                                                                <div>
                                                                    <?php foreach ($product['sauces'] as $sauce_id): ?>
                                                                        <?php if (isset($sauce_details[$sauce_id])): ?>
                                                                            <?php $sauce = $sauce_details[$sauce_id]; ?>
                                                                            <div class="form-check">
                                                                                <input class="form-check-input" type="checkbox" id="sauce<?= $product['id'] ?>_<?= $sauce['id'] ?>" name="sauces[]" value="<?= htmlspecialchars($sauce['id']) ?>">
                                                                                <label class="form-check-label" for="sauce<?= $product['id'] ?>_<?= $sauce['id'] ?>"><?= htmlspecialchars($sauce['name']) ?> (+<?= number_format($sauce['price'], 2) ?>€)</label>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ($drinks): ?>
                                                            <div class="mb-3">
                                                                <label class="form-label">Drinks</label>
                                                                <select class="form-select" name="drink">
                                                                    <option value="">Choose a drink</option>
                                                                    <?php foreach ($drinks as $drink): ?>
                                                                        <option value="<?= htmlspecialchars($drink['id']) ?>"><?= htmlspecialchars($drink['name']) ?> (+<?= number_format($drink['price'], 2) ?>€)</option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="mb-3">
                                                            <label class="form-label">Quantity</label>
                                                            <input type="number" class="form-control" name="quantity" value="1" min="1" max="99" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Special Instructions</label>
                                                            <textarea class="form-control" name="special_instructions" rows="2" placeholder="Any special requests?"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id']) ?>">
                                                <input type="hidden" name="add_to_cart" value="1">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Add to Cart</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <!-- Allergies Modal -->
                            <?php if ($product['allergies']): ?>
                                <div class="modal fade" id="allergiesModal<?= $product['id'] ?>" tabindex="-1" aria-labelledby="allergiesModalLabel<?= $product['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="allergiesModalLabel<?= $product['id'] ?>">Allergies for <?= htmlspecialchars($product['name']) ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <ul class="list-group">
                                                    <?php foreach (array_map('trim', explode(',', $product['allergies'])) as $allergy): ?>
                                                        <li class="list-group-item"><?= htmlspecialchars($allergy) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center">No products available in this category.</p>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Order Summary Sidebar -->
            <div class="col-lg-3">
                <div class="order-summary">
                    <h2 class="order-title">YOUR ORDER</h2>
                    <!-- Display Cart Logo -->
                    <?php if (!empty($cart_logo) && file_exists($cart_logo)): ?>
                        <div class="mb-3 text-center">
                            <img src="<?= htmlspecialchars($cart_logo) ?>" alt="Cart Logo" class="img-fluid cart-logo" id="cart-logo" loading="lazy" onerror="this.src='https://via.placeholder.com/150?text=Logo';">
                        </div>
                    <?php endif; ?>
                    <!-- Display Cart Description -->
                    <?php if (!empty($cart_description)): ?>
                        <div class="mb-4 cart-description" id="cart-description">
                            <p><?= nl2br(htmlspecialchars($cart_description)) ?></p>
                        </div>
                    <?php endif; ?>
                    <!-- Existing Order Details -->
                    <p class="card-text">
                        <strong>Working hours:</strong>
                        <?php if (!empty($notification['message'])): ?>
                            <?= htmlspecialchars($notification['message']) ?>
                        <?php else: ?>
                            <?= "10:00 - 21:45" ?>
                        <?php endif; ?>
                        <br>
                        <strong>Minimum order:</strong> 5.00€
                    </p>
                    <div id="cart-items">
                        <?php if ($_SESSION['cart']): ?>
                            <ul class="list-group mb-3">
                                <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6><?= htmlspecialchars($item['name']) ?> (<?= htmlspecialchars($item['size'] ?? 'N/A') ?>) x<?= htmlspecialchars($item['quantity']) ?></h6>
                                            <?php if ($item['extras']): ?>
                                                <ul>
                                                    <?php foreach ($item['extras'] as $extra): ?>
                                                        <li><?= htmlspecialchars($extra['name']) ?> (+<?= number_format($extra['price'], 2) ?>€)</li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                            <?php if ($item['drink']): ?>
                                                <p>Drink: <?= htmlspecialchars($item['drink']['name']) ?> (+<?= number_format($item['drink']['price'], 2) ?>€)</p>
                                            <?php endif; ?>
                                            <?php if ($item['sauces']): ?>
                                                <p>Sauces: <?= htmlspecialchars(implode(', ', array_column($item['sauces'], 'name'))) ?> (+<?= number_format(array_reduce($item['sauces'], fn($carry, $sauce) => $carry + $sauce['price'], 0.00), 2) ?>€)</p>
                                            <?php endif; ?>
                                            <?php if ($item['special_instructions']): ?>
                                                <p><em>Instructions:</em> <?= htmlspecialchars($item['special_instructions']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong><?= number_format($item['total_price'], 2) ?>€</strong><br>
                                            <a href="index.php?remove=<?= $index ?>" class="btn btn-sm btn-danger mt-2"><i class="bi bi-trash"></i> Remove</a>
                                            <button type="button" class="btn btn-sm btn-secondary mt-2" data-bs-toggle="modal" data-bs-target="#editCartModal<?= $index ?>"><i class="bi bi-pencil-square"></i> Edit</button>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if ($tip_amount > 0): ?>
                                <ul class="list-group mb-3">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>Tip</div>
                                        <div><?= number_format($tip_amount, 2) ?>€</div>
                                    </li>
                                </ul>
                            <?php endif; ?>
                            <h4>Total: <?= number_format($cart_total_with_tip, 2) ?>€</h4>
                            <button class="btn btn-success w-100 mt-3 btn-checkout" data-bs-toggle="modal" data-bs-target="#checkoutModal">Proceed to Checkout</button>
                        <?php else: ?>
                            <p>Your cart is empty.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <!-- Checkout Modal -->
    <div class="modal fade" id="checkoutModal" tabindex="-1" aria-labelledby="checkoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="index.php" id="checkoutForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="checkoutModalLabel">Checkout</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Customer Details Fields -->
                        <div class="mb-3">
                            <label for="customer_name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="customer_email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="customer_email" name="customer_email" required>
                        </div>
                        <div class="mb-3">
                            <label for="customer_phone" class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="customer_phone" name="customer_phone" required>
                        </div>
                        <div class="mb-3">
                            <label for="delivery_address" class="form-label">Delivery Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="delivery_address" name="delivery_address" rows="2" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label>
                                <input class="form-check-input" type="radio" id="event_checkbox"> Event
                            </label>
                        </div>
                        <div id="event_details" style="display: none;">
                            <div class="mb-3">
                                <label for="scheduled_date" class="form-label">Preferred Delivery Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="scheduled_date" name="scheduled_date" min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="mb-3">
                                <label for="scheduled_time" class="form-label">Preferred Delivery Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="scheduled_time" name="scheduled_time">
                            </div>
                        </div>
                        <script>
                            document.getElementById('event_checkbox').addEventListener('change', function() {
                                const eventDetails = document.getElementById('event_details');
                                const scheduledDate = document.getElementById('scheduled_date');
                                const scheduledTime = document.getElementById('scheduled_time');
                                if (this.checked) {
                                    eventDetails.style.display = 'block';
                                    // Set required attribute for inputs when checkbox is checked
                                    scheduledDate.setAttribute('required', 'required');
                                    scheduledTime.setAttribute('required', 'required');
                                } else {
                                    eventDetails.style.display = 'none';
                                    // Remove required attribute for inputs when checkbox is unchecked
                                    scheduledDate.removeAttribute('required');
                                    scheduledTime.removeAttribute('required');
                                }
                            });
                        </script>
                        <!-- Payment Method Selection -->
                        <div class="mb-3">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="paymentStripe" value="stripe" required>
                                    <label class="form-check-label" for="paymentStripe">
                                        Stripe (Online Payment)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="paymentPickup" value="pickup" required>
                                    <label class="form-check-label" for="paymentPickup">
                                        Pick-Up (Pay on Collection)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="paymentCash" value="cash" required>
                                    <label class="form-check-label" for="paymentCash">
                                        Cash on Delivery
                                    </label>
                                </div>
                            </div>
                        </div>
                        <!-- Stripe Payment Elements (Hidden by Default) -->
                        <div id="stripe-payment-section" class="mb-3" style="display: none;">
                            <label class="form-label">Credit or Debit Card</label>
                            <div id="card-element"><!-- A Stripe Element will be inserted here. --></div>
                            <div id="card-errors" role="alert" class="text-danger mt-2"></div>
                        </div>
                        <!-- Tip Selection -->
                        <div class="mb-3">
                            <label for="tip_selection" class="form-label">Select Tip</label>
                            <select class="form-select" id="tip_selection" name="selected_tip">
                                <?php foreach ($tip_options as $tip): ?>
                                    <option value="<?= htmlspecialchars($tip['id']) ?>" <?= ($selected_tip == $tip['id']) ? 'selected' : '' ?>>
                                        <?php
                                        if ($tip['percentage']) {
                                            echo htmlspecialchars($tip['name']) . " (" . htmlspecialchars($tip['percentage']) . "%)";
                                        } elseif ($tip['amount']) {
                                            echo htmlspecialchars($tip['name']) . " (+" . number_format($tip['amount'], 2) . "€)";
                                        }
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Order Summary -->
                        <h5>Order Summary</h5>
                        <ul class="list-group mb-3">
                            <?php foreach ($_SESSION['cart'] as $item): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div><?= htmlspecialchars($item['name']) ?> x<?= htmlspecialchars($item['quantity']) ?><?= $item['size'] ? " ({$item['size']})" : '' ?></div>
                                    <div><?= number_format($item['total_price'], 2) ?>€</div>
                                </li>
                            <?php endforeach; ?>
                            <?php if ($tip_amount > 0): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>Tip</div>
                                    <div><?= number_format($tip_amount, 2) ?>€</div>
                                </li>
                            <?php endif; ?>
                        </ul>
                        <h5>Total: <?= number_format($cart_total_with_tip, 2) ?>€</h5>
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="checkout" value="1">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Place Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Order Success Modal -->
    <?php if (isset($_GET['order']) && $_GET['order'] === 'success'): ?>
        <?php
        // Fetch the last order to get payment method
        $stmt = $pdo->prepare("SELECT payment_method FROM orders WHERE id = (SELECT MAX(id) FROM orders)");
        $stmt->execute();
        $last_order = $stmt->fetch(PDO::FETCH_ASSOC);
        $payment_method = $last_order['payment_method'] ?? 'cash';
        ?>
        <div class="modal fade show" tabindex="-1" style="display: block;" aria-modal="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Thank You for Your Order!</h5>
                        <a href="index.php" class="btn-close" aria-label="Close"></a>
                    </div>
                    <div class="modal-body">
                        <p>Your order has been placed successfully.</p>
                        <p>
                            <?php if ($payment_method === 'stripe'): ?>
                                <strong>Payment Method:</strong> Online Payment via Stripe.
                            <?php elseif ($payment_method === 'pickup'): ?>
                                <strong>Payment Method:</strong> Pay on Collection.
                            <?php elseif ($payment_method === 'cash'): ?>
                                <strong>Payment Method:</strong> Cash on Delivery.
                            <?php endif; ?>
                        </p>
                        <p>It will be delivered on <strong><?= htmlspecialchars($_GET['scheduled_date']) ?></strong> at <strong><?= htmlspecialchars($_GET['scheduled_time']) ?></strong>.</p>
                    </div>
                    <div class="modal-footer">
                        <a href="index.php" class="btn btn-primary">Close</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <!-- Toast Notifications -->
    <?php if (isset($_GET['added']) && $_GET['added'] == 1): ?>
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div class="toast align-items-center text-bg-success border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">Product added to cart successfully!</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['removed']) && $_GET['removed'] == 1): ?>
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div class="toast align-items-center text-bg-warning border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">Product removed from cart.</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div class="toast align-items-center text-bg-info border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">Cart updated successfully!</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <!-- Cart Modal -->
    <div class="modal fade" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Your Cart</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($_SESSION['cart']): ?>
                        <ul class="list-group mb-3">
                            <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6><?= htmlspecialchars($item['name']) ?> (<?= htmlspecialchars($item['size'] ?? 'N/A') ?>) x<?= htmlspecialchars($item['quantity']) ?></h6>
                                        <?php if ($item['extras']): ?>
                                            <ul>
                                                <?php foreach ($item['extras'] as $extra): ?>
                                                    <li><?= htmlspecialchars($extra['name']) ?> (+<?= number_format($extra['price'], 2) ?>€)</li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        <?php if ($item['drink']): ?>
                                            <p>Drink: <?= htmlspecialchars($item['drink']['name']) ?> (+<?= number_format($item['drink']['price'], 2) ?>€)</p>
                                        <?php endif; ?>
                                        <?php if ($item['sauces']): ?>
                                            <p>Sauces: <?= htmlspecialchars(implode(', ', array_column($item['sauces'], 'name'))) ?> (+<?= number_format(array_reduce($item['sauces'], fn($carry, $sauce) => $carry + $sauce['price'], 0.00), 2) ?>€)</p>
                                        <?php endif; ?>
                                        <?php if ($item['special_instructions']): ?>
                                            <p><em>Instructions:</em> <?= htmlspecialchars($item['special_instructions']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong><?= number_format($item['total_price'], 2) ?>€</strong><br>
                                        <a href="index.php?remove=<?= $index ?>" class="btn btn-sm btn-danger mt-2"><i class="bi bi-trash"></i> Remove</a>
                                        <button type="button" class="btn btn-sm btn-secondary mt-2" data-bs-toggle="modal" data-bs-target="#editCartModal<?= $index ?>"><i class="bi bi-pencil-square"></i> Edit</button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($tip_amount > 0): ?>
                            <ul class="list-group mb-3">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>Tip</div>
                                    <div><?= number_format($tip_amount, 2) ?>€</div>
                                </li>
                            </ul>
                        <?php endif; ?>
                        <h4>Total: <?= number_format($cart_total_with_tip, 2) ?>€</h4>
                        <button class="btn btn-success w-100 mt-3 btn-checkout" data-bs-toggle="modal" data-bs-target="#checkoutModal">Proceed to Checkout</button>
                    <?php else: ?>
                        <p>Your cart is empty.</p>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <footer class="bg-light text-muted pt-5 pb-4">
        <div class="container text-md-left">
            <div class="row text-md-left">
                <!-- Company Info -->
                <div class="col-md-3 col-lg-3 col-xl-3 mx-auto mt-3">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">
                        <img src="https://images.unsplash.com/photo-1550547660-d9450f859349?crop=entropy&cs=tinysrgb&fit=crop&fm=jpg&h=40&w=40" alt="Restaurant Logo" width="40" height="40" class="me-2">
                        Restaurant
                    </h5>
                    <p>
                        Experience the finest dining with us. We offer a variety of dishes crafted from the freshest ingredients to delight your palate.
                    </p>
                </div>
                <!-- Navigation Links -->
                <div class="col-md-2 col-lg-2 col-xl-2 mx-auto mt-3">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="index.php" class="text-reset text-decoration-none">Home</a>
                        </li>
                        <li class="mb-2">
                            <a href="#menu" class="text-reset text-decoration-none">Menu</a>
                        </li>
                        <li class="mb-2">
                            <a href="#about" class="text-reset text-decoration-none">About Us</a>
                        </li>
                        <li class="mb-2">
                            <a href="#contact" class="text-reset text-decoration-none">Contact</a>
                        </li>
                    </ul>
                </div>
                <!-- Legal Links -->
                <div class="col-md-3 col-lg-2 col-xl-2 mx-auto mt-3">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Legal</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <button type="button" class="btn btn-link text-reset text-decoration-none p-0" data-bs-toggle="modal" data-bs-target="#agbModal">
                                <i class="bi bi-file-earmark-text-fill me-2"></i> AGB
                            </button>
                        </li>
                        <li class="mb-2">
                            <button type="button" class="btn btn-link text-reset text-decoration-none p-0" data-bs-toggle="modal" data-bs-target="#impressumModal">
                                <i class="bi bi-file-earmark-text-fill me-2"></i> Impressum
                            </button>
                        </li>
                        <li class="mb-2">
                            <button type="button" class="btn btn-link text-reset text-decoration-none p-0" data-bs-toggle="modal" data-bs-target="#datenschutzModal">
                                <i class="bi bi-file-earmark-text-fill me-2"></i> Datenschutzerklärung
                            </button>
                        </li>
                    </ul>
                </div>
                <!-- Contact Information -->
                <div class="col-md-4 col-lg-3 col-xl-3 mx-auto mt-3">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Contact Us</h5>
                    <p><i class="bi bi-geo-alt-fill me-2"></i> 123 Main Street, City, Country</p>
                    <p><i class="bi bi-envelope-fill me-2"></i> info@restaurant.com</p>
                    <p><i class="bi bi-telephone-fill me-2"></i> +1 234 567 890</p>
                    <p><i class="bi bi-clock-fill me-2"></i> Mon - Sun: 10:00 AM - 10:00 PM</p>
                </div>
            </div>
            <hr class="mb-4">
            <div class="row align-items-center">
                <!-- Social Media Icons -->
                <div class="col-md-7 col-lg-8">
                    <p>
                        © <?= date('Y') ?> <strong>Restaurant</strong>. All rights reserved.
                    </p>
                </div>
                <div class="col-md-5 col-lg-4">
                    <div class="text-center text-md-end">
                        <div class="social-media">
                            <?php if (!empty($social_links['facebook_link'])): ?>
                                <a href="<?= htmlspecialchars($social_links['facebook_link']) ?>" target="_blank" rel="noopener noreferrer">
                                    <i class="bi bi-facebook"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($social_links['twitter_link'])): ?>
                                <a href="<?= htmlspecialchars($social_links['twitter_link']) ?>" target="_blank" rel="noopener noreferrer">
                                    <i class="bi bi-twitter"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($social_links['instagram_link'])): ?>
                                <a href="<?= htmlspecialchars($social_links['instagram_link']) ?>" target="_blank" rel="noopener noreferrer">
                                    <i class="bi bi-instagram"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($social_links['linkedin_link'])): ?>
                                <a href="<?= htmlspecialchars($social_links['linkedin_link']) ?>" target="_blank" rel="noopener noreferrer">
                                    <i class="bi bi-linkedin"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($social_links['youtube_link'])): ?>
                                <a href="<?= htmlspecialchars($social_links['youtube_link']) ?>" target="_blank" rel="noopener noreferrer">
                                    <i class="bi bi-youtube"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    <!-- Legal Modals -->
    <!-- AGB Modal -->
    <div class="modal fade" id="agbModal" tabindex="-1" aria-labelledby="agbModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">AGB (Terms and Conditions)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?= nl2br(htmlspecialchars($agb)) ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Impressum Modal -->
    <div class="modal fade" id="impressumModal" tabindex="-1" aria-labelledby="impressumModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Impressum (Imprint)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?= nl2br(htmlspecialchars($impressum)) ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Datenschutzerklärung Modal -->
    <div class="modal fade" id="datenschutzModal" tabindex="-1" aria-labelledby="datenschutzModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Datenschutzerklärung (Privacy Policy)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?= nl2br(htmlspecialchars($datenschutzerklaerung)) ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Ratings Modal -->
    <div class="modal fade" id="ratingsModal" tabindex="-1" aria-labelledby="ratingsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="submit_rating.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ratingsModalLabel">Submit Your Rating</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone (Optional)</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="1" id="anonymous" name="anonymous">
                            <label class="form-check-label" for="anonymous">
                                Submit as Anonymous
                            </label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rating <span class="text-danger">*</span></label>
                            <div>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="rating" id="rating<?= $i ?>" value="<?= $i ?>" required>
                                        <label class="form-check-label" for="rating<?= $i ?>"><?= $i ?> Star<?= $i > 1 ? 's' : '' ?></label>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="comments" class="form-label">Comments</label>
                            <textarea class="form-control" id="comments" name="comments" rows="3" placeholder="Your feedback..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="submit_rating" value="1">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Rating</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Bootstrap Bundle JS (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Fade out loading overlay
            $('#loading-overlay').fadeOut('slow');
            // Initialize toasts
            $('.toast').toast('show');
            // Handle reservation form submission via AJAX
            $('#reservationForm').on('submit', function(e) {
                e.preventDefault();
                const formData = $(this).serialize();
                const submitButton = $(this).find('button[type="submit"]');
                submitButton.prop('disabled', true);
                $.ajax({
                    url: 'submit_reservation.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        $('#reservationModal').modal('hide');
                        $('#reservationForm')[0].reset();
                        Swal.fire({
                            icon: response.status === 'success' ? 'success' : 'error',
                            title: response.status === 'success' ? 'Rezervim i Suksesshëm' : 'Gabim',
                            text: response.message,
                            timer: response.status === 'success' ? 3000 : undefined,
                            showConfirmButton: response.status !== 'success'
                        });
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gabim',
                            text: 'Ka ndodhur një gabim gjatë dërgimit të rezervimit. Ju lutem provoni më vonë.'
                        });
                    },
                    complete: function() {
                        submitButton.prop('disabled', false);
                    }
                });
            });
            // Redirect after order success
            <?php if (isset($_GET['order']) && $_GET['order'] === 'success'): ?>
                setTimeout(() => {
                    $('.modal').modal('hide');
                    window.location.href = 'index.php';
                }, 5000);
            <?php endif; ?>
            // Handle Store Closed Status
            let storeClosedModalInstance = null;

            function checkStoreStatus() {
                $.getJSON('check_store_status.php', function(data) {
                    if (data.is_closed) {
                        if (!storeClosedModalInstance) {
                            $('#storeClosedModalLabel').text(data.notification.title);
                            $('#storeClosedMessage').text(data.notification.message);
                            const endTime = new Date(data.notification.end_datetime);
                            $('#storeClosedEndTime').text(`We will reopen on ${endTime.toLocaleString()}.`);
                            storeClosedModalInstance = new bootstrap.Modal('#storeClosedModal', {
                                backdrop: 'static',
                                keyboard: false
                            });
                            storeClosedModalInstance.show();
                            $('.btn-add-to-cart').prop('disabled', true).toggleClass('disabled', true).attr('title', 'Store is currently closed');
                            $('.btn-checkout').prop('disabled', true).toggleClass('disabled', true).attr('title', 'Store is currently closed');
                        }
                    } else {
                        if (storeClosedModalInstance) {
                            storeClosedModalInstance.hide();
                            storeClosedModalInstance = null;
                            $('.btn-add-to-cart').prop('disabled', false).toggleClass('disabled', false).attr('title', '');
                            $('.btn-checkout').prop('disabled', false).toggleClass('disabled', false).attr('title', '');
                        }
                    }
                }).fail(function(error) {
                    console.error('Error fetching store status:', error);
                });
            }
            // Initial check and set interval
            checkStoreStatus();
            setInterval(checkStoreStatus, 60000); // Check every 60 seconds
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Stripe
            const stripe = Stripe('pk_test_51QByfJE4KNNCb6nuSnWLZP9JXlW84zG9DnOrQDTHQJvus9D8A8vOA85S4DfRlyWgN0rxa2hHzjppchnrmhyZGflx00B2kKlxym'); // Replace with your actual Stripe publishable key
            const elements = stripe.elements();
            const cardElement = elements.create('card');
            cardElement.mount('#card-element');
            // Handle payment method change
            const paymentMethods = document.getElementsByName('payment_method');
            paymentMethods.forEach(function(method) {
                method.addEventListener('change', function() {
                    if (document.getElementById('paymentStripe').checked) {
                        document.getElementById('stripe-payment-section').style.display = 'block';
                    } else {
                        document.getElementById('stripe-payment-section').style.display = 'none';
                    }
                });
            });
            // Handle form submission
            const checkoutForm = document.getElementById('checkoutForm');
            const submitButton = checkoutForm.querySelector('button[type="submit"]');
            checkoutForm.addEventListener('submit', function(e) {
                if (document.getElementById('paymentStripe').checked) {
                    e.preventDefault();
                    submitButton.disabled = true;
                    stripe.createPaymentMethod({
                        type: 'card',
                        card: cardElement,
                        billing_details: {
                            name: document.getElementById('customer_name').value,
                            email: document.getElementById('customer_email').value,
                            phone: document.getElementById('customer_phone').value,
                            address: {
                                line1: document.getElementById('delivery_address').value
                            }
                        }
                    }).then(function(result) {
                        if (result.error) {
                            // Display error in #card-errors
                            document.getElementById('card-errors').textContent = result.error.message;
                            submitButton.disabled = false;
                        } else {
                            // Append payment_method ID to the form and submit via AJAX
                            const hiddenInput = document.createElement('input');
                            hiddenInput.setAttribute('type', 'hidden');
                            hiddenInput.setAttribute('name', 'stripe_payment_method');
                            hiddenInput.setAttribute('value', result.paymentMethod.id);
                            checkoutForm.appendChild(hiddenInput);
                            // Submit the form via AJAX using Fetch API
                            fetch('index.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded'
                                    },
                                    body: new URLSearchParams(new FormData(checkoutForm))
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.requires_action) {
                                        // Handle required action (e.g., 3D Secure)
                                        stripe.confirmCardPayment(data.payment_intent_client_secret).then(function(confirmResult) {
                                            if (confirmResult.error) {
                                                // Show error to the customer
                                                document.getElementById('card-errors').textContent = confirmResult.error.message;
                                                submitButton.disabled = false;
                                            } else {
                                                if (confirmResult.paymentIntent.status === 'succeeded') {
                                                    // Redirect to success page
                                                    window.location.href = data.redirect_url;
                                                }
                                            }
                                        });
                                    } else if (data.success) {
                                        // Payment succeeded, redirect to success page
                                        window.location.href = data.redirect_url;
                                    }
                                })
                                .catch(() => {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: 'There was an issue processing your payment. Please try again.',
                                    });
                                    submitButton.disabled = false;
                                });
                        }
                    });
                }
            });
        });
    </script>
</body>

</html>
<?php ob_end_flush();
?>