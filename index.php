<?php
ob_start(); // Start output buffering
require 'vendor/autoload.php'; // Ensure Composer's autoload is included
\Stripe\Stripe::setApiKey("sk_test_51QByfJE4KNNCb6nuElXbMZUUan5s9fkJ1N2Ce3fMunhTipH5LGonlnO3bcq6eaxXINmWDuMzfw7RFTNTOb1jDsEm00IzfwoFx2"); // Securely set your Stripe secret key using environment variables
session_start();
require 'db.php'; // Initialize CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Fetch available stores if not already selected
if (!isset($_SESSION['selected_store'])) {
    $stmt = $pdo->query('SELECT id, name FROM stores WHERE is_active = 1 ORDER BY name ASC');
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// Handle Store Selection Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['store_id'])) {
    // Sanitize and validate the store_id
    $selected_store_id = (int)$_POST['store_id'];
    // Fetch the store name from the database
    $stmt = $pdo->prepare('SELECT name FROM stores WHERE id = ? AND is_active = 1');
    $stmt->execute([$selected_store_id]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($store) {
        // Sanitize and validate address inputs
        $delivery_address = trim($_POST['delivery_address'] ?? '');
        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');
        if (empty($delivery_address) || empty($latitude) || empty($longitude)) {
            $error_message = "Please provide a valid delivery address and select your location on the map.";
        } else {
            // Optionally, further validate latitude and longitude
            if (!is_numeric($latitude) || !is_numeric($longitude)) {
                $error_message = "Invalid location coordinates.";
            } else {
                // Store the selected store and address in the session
                $_SESSION['selected_store'] = $selected_store_id;
                $_SESSION['store_name'] = $store['name'];
                $_SESSION['delivery_address'] = $delivery_address;
                $_SESSION['latitude'] = $latitude;
                $_SESSION['longitude'] = $longitude;
                header('Location: index.php');
                exit();
            }
        }
    } else {
        // Handle error: store not found or inactive
        $error_message = "Selected store is not available.";
    }
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
    $store_id = $_SESSION['selected_store'] ?? null;
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
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, customer_name, customer_email, customer_phone, delivery_address, total_amount, status_id, tip_id, tip_amount, scheduled_date, scheduled_time, payment_method,store_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([null, $customer_name, $customer_email, $customer_phone, $delivery_address, $total_amount, 2, $selected_tip_id, $tip_amount, $scheduled_date, $scheduled_time, $payment_method, $store_id]);
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
    $stmt = $pdo->prepare("SELECT * FROM offers WHERE is_active = 1 AND (start_date IS NULL OR start_date <= ?) AND (end_date IS NULL OR end_date>= ?) ORDER BY created_at DESC");
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
    <?php if (!empty($cart_logo) && file_exists($cart_logo)): ?>
        <link rel="icon" type="image/png" href="<?php echo $cart_logo; ?>">
    <?php endif; ?>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js" integrity="sha512-BwHfrr4c9kmRkLw6iXFdzcdWV/PGkVgiIyIWLLlTSXzWQzxuSg4DiQUCpauz/EWjgk5TYQqX/kvn9pG1NpYfqg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" integrity="sha512-Zcn6bjR/8RZbLEpLIeOwNtzREBAJnUKESxces60Mpoj+2okopSAcSUIUOseddDm0cxnGQzxIR7vJgsLZbdLE3w==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay" aria-hidden="true">
        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
    </div>
    <?php if (!isset($_SESSION['selected_store'])): ?>
        <!-- Store Selection Modal -->
        <div class="modal fade" id="storeModal" tabindex="-1" aria-labelledby="storeModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg"> <!-- Increased size for map -->
                <div class="modal-content">
                    <form method="POST" id="storeSelectionForm">
                        <div class="modal-header">
                            <img src="<?= htmlspecialchars($cart_logo) ?>" alt="Cart Logo"
                                style="width: 100%; height: 80px; object-fit: cover" id="cart-logo" loading="lazy"
                                onerror="this.src='https://via.placeholder.com/150?text=Logo';">
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <?php if (isset($error_message)): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                            <?php endif; ?>
                            <div class="mb-3">
                                <label for="store_id" class="form-label">Choose a store</label>
                                <select name="store_id" id="store_id" class="form-select" required>
                                    <option value="" selected>Select Store</option>
                                    <?php foreach ($stores as $store): ?>
                                        <option value="<?= htmlspecialchars($store['id']) ?>">
                                            <?= htmlspecialchars($store['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Address Input -->
                            <div class="mb-3">
                                <label for="delivery_address" class="form-label">Delivery Address</label>
                                <input type="text" class="form-control" id="delivery_address" name="delivery_address" placeholder="Enter your address" required>
                            </div>
                            <!-- Leaflet Map -->
                            <div class="mb-3">
                                <label class="form-label">Select Location on Map</label>
                                <div id="map" style="height: 300px;"></div>
                            </div>
                            <!-- Hidden Inputs for Latitude and Longitude -->
                            <input type="hidden" id="latitude" name="latitude">
                            <input type="hidden" id="longitude" name="longitude">
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Confirm</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Include jQuery and Bootstrap JS if not already included -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            $(document).ready(function() {
                $('#storeModal').modal('show');
                // Initialize Leaflet Map
                var map = L.map('map').setView([51.505, -0.09], 13); // Default to London
                // Add OpenStreetMap tiles
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(map);
                var marker;
                // Function to handle map clicks
                function onMapClick(e) {
                    if (marker) {
                        map.removeLayer(marker);
                    }
                    marker = L.marker(e.latlng).addTo(map);
                    $('#latitude').val(e.latlng.lat);
                    $('#longitude').val(e.latlng.lng);
                    // Optionally, reverse geocode to fill the address input
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${e.latlng.lat}&lon=${e.latlng.lng}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.display_name) {
                                $('#delivery_address').val(data.display_name);
                            }
                        })
                        .catch(error => console.error('Error:', error));
                }
                map.on('click', onMapClick);
                // Optional: Autofill address when address input changes using geocoding
                $('#delivery_address').on('change', function() {
                    var address = $(this).val();
                    if (address.length > 5) { // Basic validation
                        fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(address)}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data && data.length > 0) {
                                    var firstResult = data[0];
                                    var lat = firstResult.lat;
                                    var lon = firstResult.lon;
                                    if (marker) {
                                        map.removeLayer(marker);
                                    }
                                    marker = L.marker([lat, lon]).addTo(map);
                                    map.setView([lat, lon], 13);
                                    $('#latitude').val(lat);
                                    $('#longitude').val(lon);
                                } else {
                                    alert('Address not found. Please try again.');
                                }
                            })
                            .catch(error => console.error('Error:', error));
                    }
                });
                // Form submission validation
                $('#storeSelectionForm').on('submit', function(e) {
                    var address = $('#delivery_address').val().trim();
                    var lat = $('#latitude').val();
                    var lon = $('#longitude').val();
                    if (address === '' || lat === '' || lon === '') {
                        e.preventDefault();
                        alert('Please enter a valid address and select your location on the map.');
                    }
                });
            });
        </script>
    <?php else: ?>
        <div class="container mt-4">
            <!-- <h1>Welcome to the Store Management System</h1> -->
            <p>You have selected store ID: <?= htmlspecialchars($_SESSION['selected_store']) ?></p>
            <p>Name of the store: <?= htmlspecialchars($_SESSION['store_name']) ?></p>
        </div>
    <?php endif; ?>
    <?php
    // Array of files to include
    $includes = [
        'edit_cart.php',
        'header.php',
        'reservation.php',
        'promotional_banners.php',
        'special_offers.php',
        'checkout.php',
        'order_success.php',
        'cart_modal.php',
        'toast_notifications.php',
        'agb_modal.php',
        'impressum_modal.php',
        'datenschutz_modal.php',
        'ratings.php',
        'store_close.php'
    ];
    // Include each file
    foreach ($includes as $file) {
        include $file;
    }
    ?>
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
                                        <img src="admin/<?= htmlspecialchars($product['image_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($product['name']) ?>" onerror="this.src='https://via.placeholder.com/600x400?text=Product+Image';">
                                        <?php if ($product['is_new'] || $product['is_offer']): ?>
                                            <span class="badge <?= $product['is_new'] ? 'bg-success' : 'bg-warning text-dark' ?> position-absolute <?= $product['is_offer'] ? 'top-40' : 'top-0' ?> end-0 m-2">
                                                <?= $product['is_new'] ? 'New' : 'Offer' ?>
                                            </span>
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
                                            <strong>
                                                <?= number_format(!empty($product['sizes']) ? $product['sizes'][0]['price'] : $product['base_price'], 2) . '€' ?>
                                            </strong>
                                            <button class="btn btn-sm btn-primary btn-add-to-cart" data-bs-toggle="modal" data-bs-target="#addToCartModal<?= $product['id'] ?>">
                                                <i class="bi bi-cart-plus me-1"></i> Add to Cart
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Add to Cart Modal -->
                            <div class="modal fade" id="addToCartModal<?= $product['id'] ?>" tabindex="-1" aria-labelledby="addToCartModalLabel<?= $product['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-xl modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" action="index.php">
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-4">
                                                        <img src="<?= htmlspecialchars($product['image_url']) ?>" class="img-fluid rounded shadow" alt="<?= htmlspecialchars($product['name']) ?>" onerror="this.src='https://via.placeholder.com/600x400?text=Product+Image';">
                                                    </div>
                                                    <div class="col-8">
                                                        <h2 class="text-uppercase"><b><?= htmlspecialchars($product['name']) ?></b></h2>
                                                        <hr style="width: 10%; border: 2px solid black; border-radius: 5px;">
                                                        <h3 style="color:#d3b213"><b>
                                                                <?= number_format(!empty($product['sizes']) ? $product['sizes'][0]['price'] : $product['base_price'], 2) . '€' ?>
                                                            </b></h3>
                                                        <?php if (!empty($product['sizes'])): ?>
                                                            <div class="mb-3">
                                                                <label class="form-label">Size</label>
                                                                <select class="form-select" name="size" required>
                                                                    <option value="">Choose a size</option>
                                                                    <?php foreach ($product['sizes'] as $size): ?>
                                                                        <option value="<?= htmlspecialchars($size['id']) ?>">
                                                                            <?= htmlspecialchars($size['name']) ?> (+<?= number_format($size['price'], 2) ?>€)
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php foreach (['extras' => $product['extras'], 'sauces' => $product['sauces']] as $type => $items): ?>
                                                            <?php if ($items): ?>
                                                                <div class="mb-3">
                                                                    <label class="form-label"><?= ucfirst($type) ?></label>
                                                                    <div>
                                                                        <?php foreach ($items as $key => $item): ?>
                                                                            <?php
                                                                            $itemData = ($type === 'sauces') && isset($sauce_details[$item]) ? $sauce_details[$item] : $item;
                                                                            if ($type === 'sauces' && !isset($sauce_details[$item])) continue;
                                                                            ?>
                                                                            <div class="form-check">
                                                                                <input class="form-check-input" type="checkbox" id="<?= $type ?><?= $product['id'] ?>_<?= is_array($itemData) ? $itemData['id'] : $item['id'] ?>" name="<?= $type ?>[]" value="<?= htmlspecialchars(is_array($itemData) ? $itemData['id'] : $item['id']) ?>">
                                                                                <label class="form-check-label" for="<?= $type ?><?= $product['id'] ?>_<?= is_array($itemData) ? $itemData['id'] : $item['id'] ?>">
                                                                                    <?= htmlspecialchars(is_array($itemData) ? $itemData['name'] : $item['name']) ?>
                                                                                    (+<?= number_format(is_array($itemData) ? $itemData['price'] : $item['price'], 2) ?>€)
                                                                                </label>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                        <?php if ($drinks): ?>
                                                            <div class="mb-3">
                                                                <label class="form-label">Drinks</label>
                                                                <select class="form-select" name="drink">
                                                                    <option value="">Choose a drink</option>
                                                                    <?php foreach ($drinks as $drink): ?>
                                                                        <option value="<?= htmlspecialchars($drink['id']) ?>">
                                                                            <?= htmlspecialchars($drink['name']) ?> (+<?= number_format($drink['price'], 2) ?>€)
                                                                        </option>
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
                        <?= !empty($notification['message']) ? htmlspecialchars($notification['message']) : "10:00 - 21:45" ?>
                        <br>
                        <strong>Minimum order:</strong> 5.00€
                    </p>
                    <?php if (!empty($_SESSION['delivery_address'])): ?>
                        <div class="mb-3">
                            <h5>Delivery Address</h5>
                            <p><?= htmlspecialchars($_SESSION['delivery_address']) ?></p>
                            <a href="https://www.openstreetmap.org/?mlat=<?= htmlspecialchars($_SESSION['latitude']) ?>&mlon=<?= htmlspecialchars($_SESSION['longitude']) ?>#map=18/<?= htmlspecialchars($_SESSION['latitude']) ?>/<?= htmlspecialchars($_SESSION['longitude']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                View on Map
                            </a>
                        </div>
                    <?php endif; ?>
                    <div id="cart-items">
                        <?php if (!empty($_SESSION['cart'])): ?>
                            <ul class="list-group mb-3">
                                <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6><?= htmlspecialchars($item['name']) ?> (<?= htmlspecialchars($item['size'] ?? 'N/A') ?>) x<?= htmlspecialchars($item['quantity']) ?></h6>
                                            <?php if (!empty($item['extras'])): ?>
                                                <ul>
                                                    <?php foreach ($item['extras'] as $extra): ?>
                                                        <li><?= htmlspecialchars($extra['name']) ?> (+<?= number_format($extra['price'], 2) ?>€)</li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                            <?php if (!empty($item['drink'])): ?>
                                                <p>Drink: <?= htmlspecialchars($item['drink']['name']) ?> (+<?= number_format($item['drink']['price'], 2) ?>€)</p>
                                            <?php endif; ?>
                                            <?php if (!empty($item['sauces'])): ?>
                                                <p>Sauces: <?= htmlspecialchars(implode(', ', array_column($item['sauces'], 'name'))) ?> (+<?= number_format(array_reduce($item['sauces'], fn($carry, $sauce) => $carry + $sauce['price'], 0.00), 2) ?>€)</p>
                                            <?php endif; ?>
                                            <?php if (!empty($item['special_instructions'])): ?>
                                                <p><em>Instructions:</em> <?= htmlspecialchars($item['special_instructions']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong><?= number_format($item['total_price'], 2) ?>€</strong><br>
                                            <a href="index.php?remove=<?= $index ?>" class="btn btn-sm btn-danger mt-2">
                                                <i class="bi bi-trash"></i> Remove
                                            </a>
                                            <button type="button" class="btn btn-sm btn-secondary mt-2" data-bs-toggle="modal" data-bs-target="#editCartModal<?= $index ?>">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </button>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Bootstrap Bundle JS (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(() => {
            // Fade out loading overlay and show toasts
            $('#loading-overlay').fadeOut('slow');
            $('.toast').toast('show');
            // Handle reservation form submission via AJAX
            $('#reservationForm').submit(function(e) {
                e.preventDefault();
                const $form = $(this);
                const $submitBtn = $form.find('button[type="submit"]').prop('disabled', true);
                $.post('submit_reservation.php', $form.serialize(), (response) => {
                        $('#reservationModal').modal('hide');
                        $form[0].reset();
                        Swal.fire({
                            icon: response.status === 'success' ? 'success' : 'error',
                            title: response.status === 'success' ? 'Rezervim i Suksesshëm' : 'Gabim',
                            text: response.message,
                            timer: response.status === 'success' ? 3000 : null,
                            showConfirmButton: response.status !== 'success'
                        });
                    }, 'json')
                    .fail(() => Swal.fire({
                        icon: 'error',
                        title: 'Gabim',
                        text: 'Ka ndodhur një gabim gjatë dërgimit të rezervimit. Ju lutem provoni më vonë.'
                    }))
                    .always(() => $submitBtn.prop('disabled', false));
            });
            // Redirect after order success
            <?php if (isset($_GET['order']) && $_GET['order'] === 'success'): ?>
                setTimeout(() => {
                    $('.modal').modal('hide');
                    window.location.href = 'index.php';
                }, 5000);
            <?php endif; ?>
            // Handle Store Closed Status
            const updateStoreStatus = () => {
                $.getJSON('check_store_status.php', (data) => {
                    const $controls = $('.btn-add-to-cart, .btn-checkout');
                    if (data.is_closed) {
                        $('#storeClosedModalLabel').text(data.notification.title);
                        $('#storeClosedMessage').text(data.notification.message);
                        $('#storeClosedEndTime').text(`We will reopen on ${new Date(data.notification.end_datetime).toLocaleString()}.`);
                        new bootstrap.Modal('#storeClosedModal', {
                            backdrop: 'static',
                            keyboard: false
                        }).show();
                        $controls.prop('disabled', true).addClass('disabled').attr('title', 'Store is currently closed');
                    } else {
                        const modal = bootstrap.Modal.getInstance($('#storeClosedModal')[0]);
                        modal?.hide();
                        $controls.prop('disabled', false).removeClass('disabled').attr('title', '');
                    }
                }).fail(err => console.error('Error fetching store status:', err));
            };
            updateStoreStatus();
            setInterval(updateStoreStatus, 60000);
            // Initialize Stripe
            const stripe = Stripe('pk_test_51QByfJE4KNNCb6nuSnWLZP9JXlW84zG9DnOrQDTHQJvus9D8A8vOA85S4DfRlyWgN0rxa2hHzjppchnrmhyZGflx00B2kKlxym');
            const card = stripe.elements().create('card').mount('#card-element');
            // Toggle Stripe payment section
            $('input[name="payment_method"]').change(() => $('#stripe-payment-section').toggle($('#paymentStripe').is(':checked')));
            // Handle checkout form submission
            $('#checkoutForm').submit(async function(e) {
                if ($('#paymentStripe').is(':checked')) {
                    e.preventDefault();
                    const $submitBtn = $(this).find('button[type="submit"]').prop('disabled', true);
                    try {
                        const {
                            paymentMethod,
                            error
                        } = await stripe.createPaymentMethod({
                            type: 'card',
                            card: card,
                            billing_details: {
                                name: $('#customer_name').val(),
                                email: $('#customer_email').val(),
                                phone: $('#customer_phone').val(),
                                address: {
                                    line1: $('#delivery_address').val()
                                }
                            }
                        });
                        if (error) {
                            $('#card-errors').text(error.message);
                            $submitBtn.prop('disabled', false);
                            return;
                        }
                        $(this).append($('<input>', {
                            type: 'hidden',
                            name: 'stripe_payment_method',
                            value: paymentMethod.id
                        }));
                        const formData = new URLSearchParams(new FormData(this)).toString();
                        const response = await fetch('index.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: formData
                        }).then(res => res.json());
                        if (response.requires_action) {
                            const {
                                error: confirmError,
                                paymentIntent
                            } = await stripe.confirmCardPayment(response.payment_intent_client_secret);
                            if (confirmError) {
                                $('#card-errors').text(confirmError.message);
                            } else if (paymentIntent.status === 'succeeded') {
                                window.location.href = response.redirect_url;
                            }
                        } else if (response.success) {
                            window.location.href = response.redirect_url;
                        }
                    } catch {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'There was an issue processing your payment. Please try again.'
                        });
                    } finally {
                        $(this).find('button[type="submit"]').prop('disabled', false);
                    }
                }
            });
        });
    </script>
</body>

</html>
<?php ob_end_flush();
?>