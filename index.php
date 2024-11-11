<?php
session_start();
require 'db.php'; // Ensure this file contains your PDO connection as $pdo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
define('ERROR_LOG_FILE', __DIR__ . '/errors.md');

/**
 * Logs errors in Markdown format to a specified file.
 *
 * @param string $error_message The error message to log.
 * @param string $context Additional context about where the error occurred.
 */
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

// Custom error handler to convert errors to exceptions
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Initialize cart if not set
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// Initialize variables for operational status
$is_closed = false;
$notification = [];

// Fetch Operational Hours
$current_datetime = new DateTime();
$current_date = $current_datetime->format('Y-m-d');
$current_day = $current_datetime->format('l'); // Full day name, e.g., 'Monday'
$current_time = $current_datetime->format('H:i:s');

// Check if today is a holiday
$stmt = $pdo->prepare("SELECT * FROM operational_hours WHERE type = 'holiday' AND date = ?");
if ($stmt->execute([$current_date])) {
    $holiday = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($holiday && $holiday['is_closed']) {
        $is_closed = true;
        $notification = [
            'title' => $holiday['title'] ?? 'Store Closed',
            'message' => $holiday['description'] ?? 'The store is currently closed for a holiday.',
            'end_datetime' => (new DateTime($holiday['date'] . ' ' . $holiday['close_time']))->format('c')
        ];
    }
}

if (!$is_closed) {
    // Check regular operational hours for today
    $stmt = $pdo->prepare("SELECT * FROM operational_hours WHERE type = 'regular' AND day_of_week = ?");
    if ($stmt->execute([$current_day])) {
        $regular_hours = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($regular_hours) {
            if ($regular_hours['is_closed']) {
                $is_closed = true;
                $notification = [
                    'title' => 'Store Closed',
                    'message' => 'The store is currently closed.',
                    'end_datetime' => (new DateTime())->modify('+1 day')->format('c') // Next day as reopening time
                ];
            } else {
                // Check if current time is within operational hours
                if ($current_time < $regular_hours['open_time'] || $current_time > $regular_hours['close_time']) {
                    $is_closed = true;
                    $notification = [
                        'title' => 'Store Closed',
                        'message' => 'The store is currently closed. Our working hours are from ' . date('H:i', strtotime($regular_hours['open_time'])) . ' to ' . date('H:i', strtotime($regular_hours['close_time'])) . '.',
                        'end_datetime' => (new DateTime($current_date . ' ' . $regular_hours['close_time']))->format('c')
                    ];
                }
            }
        } else {
            // No regular hours found for today
            $is_closed = true;
            $notification = [
                'title' => 'Store Closed',
                'message' => 'No operational hours found for today.',
                'end_datetime' => (new DateTime())->modify('+1 day')->format('c')
            ];
        }
    } else {
        // Query failed
        $errorInfo = $stmt->errorInfo();
        log_error_markdown("Failed to execute operational_hours query: " . implode(", ", $errorInfo), "Fetching Operational Hours");
    }
}

// Handle Add to Cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    // Retrieve and sanitize POST data
    $product_id = (int)$_POST['product_id'];
    $quantity = max(1, (int)$_POST['quantity']);
    $size_id = isset($_POST['size']) ? (int)$_POST['size'] : null;
    $extras = $_POST['extras'] ?? [];
    $drink_id = isset($_POST['drink']) ? (int)$_POST['drink'] : null;
    $special_instructions = trim($_POST['special_instructions']);
    $selected_sauces = $_POST['sauces'] ?? [];

    // Fetch Product
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
    if (!$stmt->execute([$product_id])) {
        $errorInfo = $stmt->errorInfo();
        log_error_markdown("Failed to execute product details query: " . $errorInfo[2], "Fetching Product ID: $product_id");
        header("Location: index.php?error=invalid_product");
        exit;
    }
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        $base_price = (float)$product['price'];

        // Fetch Size if selected
        if ($size_id) {
            $stmt = $pdo->prepare("SELECT ps.price, s.name FROM product_sizes ps JOIN sizes s ON ps.size_id = s.id WHERE ps.product_id = ? AND ps.size_id = ?");
            if (!$stmt->execute([$product_id, $size_id])) {
                $errorInfo = $stmt->errorInfo();
                log_error_markdown("Failed to execute size details query: " . $errorInfo[2], "Fetching Size ID: $size_id for Product ID: $product_id");
                $size_id = null;
            }
            $size = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$size) $size_id = null;
        }
        $size_price = isset($size['price']) ? (float)$size['price'] : 0.00;

        // Fetch Extras
        $extras_details = [];
        $extras_total = 0.00;
        if ($extras) {
            $placeholders = implode(',', array_fill(0, count($extras), '?'));
            $stmt = $pdo->prepare("SELECT * FROM extras WHERE id IN ($placeholders)");
            if ($stmt->execute($extras)) {
                $extras_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $extras_total = array_reduce($extras_details, fn($carry, $extra) => $carry + (float)$extra['price'], 0.00);
            }
        }

        // Fetch Drink
        $drink_details = null;
        $drink_total = 0.00;
        if ($drink_id) {
            $stmt = $pdo->prepare("SELECT * FROM drinks WHERE id = ?");
            if ($stmt->execute([$drink_id])) {
                $drink_details = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($drink_details) $drink_total = (float)$drink_details['price'];
                else $drink_id = null;
            }
        }

        // Fetch Sauces
        $sauces_details = [];
        $sauces_total = 0.00;
        if ($selected_sauces) {
            $placeholders = implode(',', array_fill(0, count($selected_sauces), '?'));
            $stmt = $pdo->prepare("SELECT * FROM sauces WHERE id IN ($placeholders)");
            if ($stmt->execute($selected_sauces)) {
                $sauces_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $sauces_total = array_reduce($sauces_details, fn($carry, $sauce) => $carry + (float)$sauce['price'], 0.00);
            }
        }

        // Calculate Prices
        $unit_price = $base_price + $size_price + $extras_total + $drink_total + $sauces_total;
        $total_price = $unit_price * $quantity;

        // Prepare Cart Item
        $cart_item = [
            'product_id' => $product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'image_url' => $product['image_url'],
            'size' => $size['name'] ?? null,
            'size_id' => $size_id,
            'extras' => $extras_details,
            'drink' => $drink_details,
            'sauces' => $sauces_details,
            'special_instructions' => $special_instructions,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'total_price' => $total_price,
            'base_price' => $base_price
        ];

        // Check for Duplicates in Cart
        $duplicate_index = null;
        foreach ($_SESSION['cart'] as $index => $item) {
            if (
                $item['product_id'] === $cart_item['product_id'] &&
                $item['size_id'] === $cart_item['size_id'] &&
                ($item['drink']['id'] ?? null) === ($cart_item['drink']['id'] ?? null) &&
                $item['special_instructions'] === $cart_item['special_instructions'] &&
                count(array_intersect(array_column($item['extras'], 'id'), array_column($cart_item['extras'], 'id'))) === count($cart_item['extras']) &&
                count(array_intersect(array_column($item['sauces'], 'id'), array_column($cart_item['sauces'], 'id'))) === count($cart_item['sauces'])
            ) {
                $duplicate_index = $index;
                break;
            }
        }

        if ($duplicate_index !== null) {
            // Update Quantity and Total Price if Duplicate Found
            $_SESSION['cart'][$duplicate_index]['quantity'] += $quantity;
            $_SESSION['cart'][$duplicate_index]['total_price'] += $total_price;
        } else {
            // Add New Item to Cart
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

// Handle Remove from Cart
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

// Handle Update Cart
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

        // Fetch Product
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
        if (!$stmt->execute([$product_id])) {
            $errorInfo = $stmt->errorInfo();
            log_error_markdown("Failed to execute product details query during cart update: " . $errorInfo[2], "Updating Cart - Fetching Product ID: $product_id");
            header("Location: index.php?error=invalid_update");
            exit;
        }
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            $base_price = (float)$product['price'];

            // Fetch Size if selected
            if ($size_id) {
                $stmt = $pdo->prepare("SELECT ps.price, s.name FROM product_sizes ps JOIN sizes s ON ps.size_id = s.id WHERE ps.product_id = ? AND ps.size_id = ?");
                if (!$stmt->execute([$product_id, $size_id])) {
                    $errorInfo = $stmt->errorInfo();
                    log_error_markdown("Failed to execute size details query during cart update: " . $errorInfo[2], "Updating Cart - Fetching Size ID: $size_id for Product ID: $product_id");
                    $size_id = null;
                }
                $size = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$size) $size_id = null;
            }
            $size_price = isset($size['price']) ? (float)$size['price'] : 0.00;

            // Fetch Extras
            $extras_details = [];
            $extras_total = 0.00;
            if ($extras) {
                $placeholders = implode(',', array_fill(0, count($extras), '?'));
                $stmt = $pdo->prepare("SELECT * FROM extras WHERE id IN ($placeholders)");
                if ($stmt->execute($extras)) {
                    $extras_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $extras_total = array_reduce($extras_details, fn($carry, $extra) => $carry + (float)$extra['price'], 0.00);
                }
            }

            // Fetch Drink
            $drink_details = null;
            $drink_total = 0.00;
            if ($drink_id) {
                $stmt = $pdo->prepare("SELECT * FROM drinks WHERE id = ?");
                if ($stmt->execute([$drink_id])) {
                    $drink_details = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($drink_details) $drink_total = (float)$drink_details['price'];
                    else $drink_id = null;
                }
            }

            // Fetch Sauces
            $sauces_details = [];
            $sauces_total = 0.00;
            if ($selected_sauces) {
                $placeholders = implode(',', array_fill(0, count($selected_sauces), '?'));
                $stmt = $pdo->prepare("SELECT * FROM sauces WHERE id IN ($placeholders)");
                if ($stmt->execute($selected_sauces)) {
                    $sauces_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $sauces_total = array_reduce($sauces_details, fn($carry, $sauce) => $carry + (float)$sauce['price'], 0.00);
                }
            }

            // Calculate Prices
            $unit_price = $base_price + $size_price + $extras_total + $drink_total + $sauces_total;
            $total_price = $unit_price * $quantity;

            // Update Cart Item
            $_SESSION['cart'][$item_index] = [
                'product_id' => $product['id'],
                'name' => $product['name'],
                'description' => $product['description'],
                'image_url' => $product['image_url'],
                'size' => $size['name'] ?? null,
                'size_id' => $size_id,
                'extras' => $extras_details,
                'drink' => $drink_details,
                'sauces' => $sauces_details,
                'special_instructions' => $special_instructions,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'total_price' => $total_price,
                'base_price' => $base_price
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
    $customer_name = trim($_POST['customer_name']);
    $customer_email = trim($_POST['customer_email']);
    $customer_phone = trim($_POST['customer_phone']);
    $delivery_address = trim($_POST['delivery_address']);

    // Basic validation
    if (!$customer_name || !$customer_email || !$customer_phone || !$delivery_address) {
        header("Location: index.php?error=invalid_order_details");
        exit;
    }

    // Calculate total amount
    $total_amount = array_reduce($_SESSION['cart'], fn($carry, $item) => $carry + $item['total_price'], 0.00);

    try {
        $pdo->beginTransaction();

        // Insert Order
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, customer_name, customer_email, customer_phone, delivery_address, total_amount, status_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt->execute([null, $customer_name, $customer_email, $customer_phone, $delivery_address, $total_amount, 2])) { // Assuming status_id 1 is 'Pending'
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Failed to insert order: " . $errorInfo[2]);
        }
        $order_id = $pdo->lastInsertId();

        // Prepare Statements for Order Items, Extras, and Drinks
        $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, product_id, size_id, quantity, price, extras, special_instructions) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_extras = $pdo->prepare("INSERT INTO order_extras (order_item_id, extra_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
        $stmt_drinks = $pdo->prepare("INSERT INTO order_drinks (order_item_id, drink_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");

        foreach ($_SESSION['cart'] as $item) {
            // Prepare data for order_item
            $extras_ids = $item['extras'] ? implode(',', array_column($item['extras'], 'id')) : null;
            $sauces_names = $item['sauces'] ? implode(', ', array_column($item['sauces'], 'name')) : null;
            $combined_instructions = $sauces_names ? ($sauces_names . ($item['special_instructions'] ? "; " . $item['special_instructions'] : "")) : $item['special_instructions'];

            // Insert Order Item
            if (!$stmt_item->execute([$order_id, $item['product_id'], $item['size_id'], $item['quantity'], $item['unit_price'], $extras_ids, $combined_instructions])) {
                $errorInfo = $stmt_item->errorInfo();
                throw new Exception("Failed to insert order item: " . $errorInfo[2]);
            }
            $order_item_id = $pdo->lastInsertId();

            // Insert Extras
            foreach ($item['extras'] as $extra) {
                if (!$stmt_extras->execute([$order_item_id, $extra['id'], 1, $extra['price'], $extra['price']])) {
                    $errorInfo = $stmt_extras->errorInfo();
                    throw new Exception("Failed to insert order extra: " . $errorInfo[2]);
                }
            }

            // Insert Drink
            if ($item['drink']) {
                if (!$stmt_drinks->execute([$order_item_id, $item['drink']['id'], 1, $item['drink']['price'], $item['drink']['price']])) {
                    $errorInfo = $stmt_drinks->errorInfo();
                    throw new Exception("Failed to insert order drink: " . $errorInfo[2]);
                }
            }
        }

        $pdo->commit();
        $_SESSION['cart'] = []; // Clear cart after successful checkout
        header("Location: index.php?order=success");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        log_error_markdown("Order Placement Failed: " . $e->getMessage(), "Checkout Process");
        header("Location: index.php?error=order_failed");
        exit;
    }
}

// Fetch Categories
$stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name ASC");
if (!$stmt->execute()) {
    $errorInfo = $stmt->errorInfo();
    log_error_markdown("Failed to execute categories query: " . $errorInfo[2], "Fetching Categories");
    $categories = [];
} else {
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Determine Selected Category
$selected_category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Fetch Products Based on Selected Category
$product_query = "SELECT p.id AS product_id, p.name AS product_name, p.description, p.image_url, p.is_new, p.is_offer, p.allergies, p.price AS base_price, ps.size_id, s.name AS size_name, ps.price AS size_price, e.id AS extra_id, e.name AS extra_name, e.price AS extra_price 
FROM products p 
LEFT JOIN product_sizes ps ON p.id = ps.product_id 
LEFT JOIN sizes s ON ps.size_id = s.id 
LEFT JOIN product_extras pe ON p.id = pe.product_id 
LEFT JOIN extras e ON pe.extra_id = e.id 
WHERE p.is_active = 1" . ($selected_category_id > 0 ? " AND p.category_id = :category_id" : "") . " 
ORDER BY p.name ASC, s.name ASC, e.name ASC";

$stmt = $pdo->prepare($product_query);
if ($selected_category_id > 0) {
    $stmt->bindParam(':category_id', $selected_category_id, PDO::PARAM_INT);
}

if (!$stmt->execute()) {
    $errorInfo = $stmt->errorInfo();
    log_error_markdown("Failed to execute products query: " . $errorInfo[2], "Fetching Products for Category ID: $selected_category_id");
    $raw_products = [];
} else {
    $raw_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Organize Products
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
    if ($row['size_id']) {
        $products[$pid]['sizes'][$row['size_id']] = [
            'id' => $row['size_id'],
            'name' => $row['size_name'],
            'price' => (float)$row['size_price']
        ];
    }
    if ($row['extra_id']) {
        $products[$pid]['extras'][$row['extra_id']] = [
            'id' => $row['extra_id'],
            'name' => $row['extra_name'],
            'price' => (float)$row['extra_price']
        ];
    }
}

foreach ($products as &$product) {
    $product['sizes'] = array_values($product['sizes']);
    $product['extras'] = array_values($product['extras']);
}
unset($product);

// Fetch Sauces Associated with Products
$product_ids = array_keys($products);
$sauce_details = [];
if ($product_ids) {
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $stmt = $pdo->prepare("SELECT product_id, sauce_id FROM product_sauces WHERE product_id IN ($placeholders)");
    if ($stmt->execute($product_ids)) {
        $product_sauces_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $product_sauces = [];
        foreach ($product_sauces_raw as $ps) {
            $pid = $ps['product_id'];
            $sid = $ps['sauce_id'];
            $product_sauces[$pid][] = $sid;
        }
        foreach ($products as $pid => &$product) {
            $product['sauces'] = $product_sauces[$pid] ?? [];
        }
        unset($product);

        $all_sauce_ids = array_unique(array_merge(...array_map(fn($p) => $p['sauces'], $products)));
        if ($all_sauce_ids) {
            $placeholders = implode(',', array_fill(0, count($all_sauce_ids), '?'));
            $stmt = $pdo->prepare("SELECT * FROM sauces WHERE id IN ($placeholders)");
            if ($stmt->execute($all_sauce_ids)) {
                $sauce_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($sauce_rows as $sauce_row) {
                    $sauce_details[$sauce_row['id']] = $sauce_row;
                }
            }
        }
    }
}

// Fetch Drinks
$stmt = $pdo->prepare("SELECT * FROM drinks ORDER BY name ASC");
if (!$stmt->execute()) {
    $errorInfo = $stmt->errorInfo();
    log_error_markdown("Failed to execute drinks query: " . $errorInfo[2], "Fetching Drinks");
    $drinks = [];
} else {
    $drinks = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate Cart Total
$cart_total = array_reduce($_SESSION['cart'], fn($carry, $item) => $carry + $item['total_price'], 0.00);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Delivery</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Saira:wght@100..900&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Flag Icons for Language Switcher -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/6.6.6/css/flag-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        /* Loading Overlay Styles */
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

        /* Calendar Styles (if used) */
        #calendar {
            max-width: 900px;
            margin: 40px auto;
        }

        .fc-event-title {
            color: #000 !important;
        }

        .tooltip-inner {
            max-width: 200px;
            text-align: left;
        }

        /* Disabled Button Styles */
        .btn.disabled,
        .btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        /* Language Switcher Positioning */
        .language-switcher {
            position: absolute;
            top: 10px;
            right: 10px;
        }

        /* Order Summary Styles */
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

        /* Badge Styles */
        .badge-new {
            background-color: #28a745;
            /* Green */
            top: 10px;
            right: 10px;
            padding: 5px 10px;
        }

        .badge-offer {
            background-color: #ffc107;
            /* Yellow */
            top: 40px;
            right: 10px;
            padding: 5px 10px;
        }

        .badge-allergies {
            background-color: rgba(220, 53, 69, 0.9);
            /* Red with opacity */
            top: 70px;
            right: 10px;
            padding: 5px 10px;
        }
    </style>
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

    <!-- Edit Cart Modals for Each Cart Item -->
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
                            <?php if (!empty($products[$item['product_id']]['extras'])): ?>
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
                            <?php if (!empty($products[$item['product_id']]['sauces'])): ?>
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

    <!-- Navbar/Header -->
    <header class="position-relative">
        <nav class="navbar navbar-expand-lg">
            <div class="container d-flex justify-content-between align-items-center">
                <!-- Brand -->
                <a class="navbar-brand d-flex align-items-center" href="index.php">
                    <img src="https://images.unsplash.com/photo-1550547660-d9450f859349?crop=entropy&cs=tinysrgb&fit=crop&fm=jpg&h=40&w=40" alt="Restaurant Logo" width="40" height="40" onerror="this.src='https://via.placeholder.com/40';">
                    <span class="restaurant-name">Restaurant</span>
                </a>
                <!-- Cart and Total -->
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
                    <!-- Button for modal -->
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ratingsModal">
                        <i class="bi bi-star fs-4"></i>
                    </button>
                    <span class="fw-bold fs-5" id="cart-total"><?= number_format($cart_total, 2) ?>€</span>
                </div>
            </div>
        </nav>
        <!-- Language Switcher -->
        <div class="language-switcher">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-primary dropdown-toggle d-flex align-items-center" type="button" id="languageDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="flag-icon flag-icon-de"></span> DE
                </button>
                <ul class="dropdown-menu" aria-labelledby="languageDropdown">
                    <li><a class="dropdown-item language-option" href="#" data-lang="de"><span class="flag-icon flag-icon-de"></span> Deutsch (DE)</a></li>
                    <li><a class="dropdown-item language-option" href="#" data-lang="sq"><span class="flag-icon flag-icon-al"></span> Shqip (SQ)</a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Promotional Banner -->
    <section class="promo-banner">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <small class="text-uppercase text-muted">Offer</small>
                    <h1>BASKET MIX</h1>
                    <p class="mt-3">10X Chicken Tenders<br>10X Spicy Wings</p>
                    <h2 class="mt-3">11.99€</h2>
                </div>
                <div class="col-lg-6 d-none d-lg-block">
                    <img src="https://images.unsplash.com/photo-1600891964599-f61ba0e24092?crop=entropy&cs=tinysrgb&fit=crop&fm=jpg&h=400&w=600" alt="Basket Mix" class="img-fluid rounded shadow" onerror="this.src='https://via.placeholder.com/600x400';">
                </div>
            </div>
        </div>
    </section>

    <!-- Category Tabs -->
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation"><a class="nav-link <?= ($selected_category_id === 0) ? 'active' : '' ?>" href="index.php" role="tab">All</a></li>
        <?php foreach ($categories as $category): ?>
            <li class="nav-item" role="presentation"><a class="nav-link <?= ($selected_category_id === (int)$category['id']) ? 'active' : '' ?>" href="index.php?category_id=<?= htmlspecialchars($category['id']) ?>" role="tab"><?= htmlspecialchars($category['name']) ?></a></li>
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
                                        <img src="<?= htmlspecialchars($product['image_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($product['name']) ?>" onerror="this.src='https://via.placeholder.com/600x400';">
                                        <?php if ($product['is_new']): ?>
                                            <span class="badge badge-new text-white position-absolute">New</span>
                                        <?php endif; ?>
                                        <?php if ($product['is_offer']): ?>
                                            <span class="badge badge-offer text-dark position-absolute">Offer</span>
                                        <?php endif; ?>
                                        <?php if ($product['allergies']): ?>
                                            <button type="button" class="btn btn-sm btn-danger badge-allergies position-absolute" data-bs-toggle="modal" data-bs-target="#allergiesModal<?= $product['id'] ?>"><i class="bi bi-exclamation-triangle-fill me-1"></i> Allergies</button>
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
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" action="index.php">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="addToCartModalLabel<?= $product['id'] ?>">Add <?= htmlspecialchars($product['name']) ?> to Cart</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-4">
                                                        <img src="<?= htmlspecialchars($product['image_url']) ?>" class="img-fluid rounded shadow" alt="<?= htmlspecialchars($product['name']) ?>" onerror="this.src='https://via.placeholder.com/600x400';">
                                                    </div>
                                                    <div class="col-8">
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

            <!-- Order Summary -->
            <div class="col-lg-3">
                <div class="order-summary">
                    <h2 class="order-title">YOUR ORDER</h2>
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
                            <h4>Total: <?= number_format($cart_total, 2) ?>€</h4>
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
                <form method="POST" action="index.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="checkoutModalLabel">Checkout</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Customer Details -->
                        <div class="mb-3">
                            <label for="customer_name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="customer_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="customer_email" name="customer_email" required>
                        </div>
                        <div class="mb-3">
                            <label for="customer_phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="customer_phone" name="customer_phone" required>
                        </div>
                        <div class="mb-3">
                            <label for="delivery_address" class="form-label">Delivery Address</label>
                            <textarea class="form-control" id="delivery_address" name="delivery_address" rows="2" required></textarea>
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
                        </ul>
                        <h5>Total: <?= number_format($cart_total, 2) ?>€</h5>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="checkout">Place Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success Modal (After Order Placement) -->
    <?php if (isset($_GET['order']) && $_GET['order'] === 'success'): ?>
        <div class="modal fade show" tabindex="-1" style="display: block;" aria-modal="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Thank You for Your Order!</h5>
                        <a href="index.php" class="btn-close" aria-label="Close"></a>
                    </div>
                    <div class="modal-body">
                        <p>Your order has been placed successfully. It will be delivered in approximately 40-60 minutes.</p>
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
                        <h4>Total: <?= number_format($cart_total, 2) ?>€</h4>
                        <button class="btn btn-success w-100 mt-3 btn-checkout" data-bs-toggle="modal" data-bs-target="#checkoutModal">Proceed to Checkout</button>
                    <?php else: ?>
                        <p>Your cart is empty.</p>
                    <?php endif; ?>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="container">
        <footer class="d-flex flex-wrap justify-content-between align-items-center py-3 my-4 border-top">
            <div class="col-md-4 d-flex align-items-center">
                <a href="/" class="mb-3 me-2 mb-md-0 text-muted text-decoration-none lh-1">
                    <!-- You can replace the SVG below with your own logo if needed -->
                    <svg class="bi" width="30" height="24">
                        <use xlink:href="#bootstrap"></use>
                    </svg>
                </a>
                <span class="text-muted">© <?= date('Y') ?> Restaurant, Inc</span>
            </div>
            <ul class="nav col-md-4 justify-content-end list-unstyled d-flex">
                <li class="ms-3"><a class="text-muted" href="#"><i class="bi bi-twitter"></i></a></li>
                <li class="ms-3"><a class="text-muted" href="#"><i class="bi bi-instagram"></i></a></li>
                <li class="ms-3"><a class="text-muted" href="#"><i class="bi bi-facebook"></i></a></li>
            </ul>
        </footer>
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
                        <!-- Full Name -->
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <!-- Phone (Optional) -->
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone (Optional)</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                        </div>
                        <!-- Anonymous Option -->
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="1" id="anonymous" name="anonymous">
                            <label class="form-check-label" for="anonymous">
                                Submit as Anonymous
                            </label>
                        </div>
                        <!-- Rating -->
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
                        <!-- Comments -->
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

    <!-- JavaScript Files -->
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery (required for some Bootstrap components) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Hide Loading Overlay once the page is fully loaded
        window.addEventListener('load', function() {
            const loadingOverlay = document.getElementById('loading-overlay');
            if (loadingOverlay) {
                loadingOverlay.style.display = 'none';
            }
        });
    </script>

    <script>
        <?php if (isset($_GET['order']) && $_GET['order'] === 'success'): ?>
            // Automatically hide the success modal after 5 seconds and redirect to homepage
            setTimeout(() => {
                $('.modal').modal('hide');
                window.location.href = 'index.php';
            }, 5000);
        <?php endif; ?>
        $(document).ready(function() {
            // Initialize and show all toasts
            $('.toast').toast('show');
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let storeClosedModalInstance = null;

            function checkStoreStatus() {
                fetch('check_store_status.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.is_closed) {
                            if (!storeClosedModalInstance) {
                                // Set modal title and message
                                document.getElementById('storeClosedModalLabel').innerText = data.notification.title;
                                document.getElementById('storeClosedMessage').innerText = data.notification.message;
                                // Show the end time
                                const endTime = new Date(data.notification.end_datetime);
                                document.getElementById('storeClosedEndTime').innerText = `We will reopen on ${endTime.toLocaleString()}.`;
                                // Show the modal
                                storeClosedModalInstance = new bootstrap.Modal(document.getElementById('storeClosedModal'), {
                                    backdrop: 'static',
                                    keyboard: false
                                });
                                storeClosedModalInstance.show();
                                // Disable ordering functionalities
                                document.querySelectorAll('.btn-add-to-cart').forEach(button => {
                                    button.disabled = true;
                                    button.classList.add('disabled');
                                    button.title = 'Store is currently closed';
                                });
                                document.querySelectorAll('.btn-checkout').forEach(button => {
                                    button.disabled = true;
                                    button.classList.add('disabled');
                                    button.title = 'Store is currently closed';
                                });
                            }
                        } else {
                            if (storeClosedModalInstance) {
                                storeClosedModalInstance.hide();
                                storeClosedModalInstance = null;
                                // Enable ordering functionalities
                                document.querySelectorAll('.btn-add-to-cart').forEach(button => {
                                    button.disabled = false;
                                    button.classList.remove('disabled');
                                    button.title = '';
                                });
                                document.querySelectorAll('.btn-checkout').forEach(button => {
                                    button.disabled = false;
                                    button.classList.remove('disabled');
                                    button.title = '';
                                });
                            }
                        }
                    })
                    .catch(error => console.error('Error fetching store status:', error));
            }

            // Initial check
            checkStoreStatus();
            // Check every minute (60000 milliseconds)
            setInterval(checkStoreStatus, 60000);
        });
    </script>
</body>

</html>