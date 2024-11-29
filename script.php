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
$stmt = $pdo->prepare("SELECT * FROM categories ORDER BY position ASC, name ASC");
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
