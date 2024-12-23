<?php
ob_start();
require 'vendor/autoload.php';
\Stripe\Stripe::setApiKey("sk_test_XXX"); // Replace with your Stripe test key
session_start();
require 'includes/db_connect.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['selected_store'])) {
    $stmt = $pdo->query('SELECT id, name FROM stores WHERE is_active = 1 ORDER BY name ASC');
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['store_id'])) {
    $selected_store_id = (int)$_POST['store_id'];
    $stmt = $pdo->prepare('SELECT name FROM stores WHERE id = ? AND is_active = 1');
    $stmt->execute([$selected_store_id]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($store) {
        $delivery_address = trim($_POST['delivery_address'] ?? '');
        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');
        if (empty($delivery_address) || empty($latitude) || empty($longitude)) {
            $error_message = "Please provide a valid delivery address and select your location on the map.";
        } else {
            if (!is_numeric($latitude) || !is_numeric($longitude)) {
                $error_message = "Invalid location coordinates.";
            } else {
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
        $error_message = "Selected store is not available.";
    }
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
define('ERROR_LOG_FILE', __DIR__ . '/errors.md');

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

set_exception_handler(function ($exception) {
    log_error_markdown("Uncaught Exception: " . $exception->getMessage(), "File: " . $exception->getFile() . " Line: " . $exception->getLine());
    header("Location: index.php?error=unknown_error");
    exit;
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

include 'settings_fetch.php';
$_SESSION['cart'] = $_SESSION['cart'] ?? [];

$is_closed = false;
$notification = [];
$current_datetime = new DateTime();
$current_date = $current_datetime->format('Y-m-d');
$current_day = $current_datetime->format('l');
$current_time = $current_datetime->format('H:i:s');

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
        if ($op_hours['is_closed'] || $current_time < ($op_hours['open_time'] ?? '00:00:00') || $current_time > ($op_hours['close_time'] ?? '23:59:59')) {
            $is_closed = true;
            $notification = [
                'title' => 'Store Closed',
                'message' => $op_hours['is_closed'] ? 'The store is currently closed.' : 'The store is currently closed. Our working hours are from ' . date('H:i', strtotime($op_hours['open_time'])) . ' to ' . date('H:i', strtotime($op_hours['close_time'])) . '.',
                'end_datetime' => $op_hours['is_closed'] ? (new DateTime())->modify('+1 day')->format('c') : (new DateTime($current_date . ' ' . $op_hours['close_time']))->format('c')
            ];
        }
    }
}

try {
    $stmt = $pdo->prepare("SELECT * FROM tips WHERE is_active = 1 ORDER BY percentage ASC, amount ASC");
    $stmt->execute();
    $tip_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_error_markdown("Failed to fetch tips: " . $e->getMessage(), "Fetching Tips");
    $tip_options = [];
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = max(1, (int)$_POST['quantity']);
    $size = $_POST['size'] ?? null;
    $extras = $_POST['extras'] ?? [];
    $sauces = $_POST['sauces'] ?? [];
    $dresses = $_POST['dresses'] ?? [];
    $drink_id = isset($_POST['drink']) ? (int)$_POST['drink'] : null;
    $special_instructions = trim($_POST['special_instructions']);
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :product_id AND is_active = 1");
    $stmt->execute(['product_id' => $product_id]);
    $product_row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($product_row) {
        $properties = json_decode($product_row['properties'], true) ?? [];
        $extras_options = $properties['extras'] ?? [];
        $sauces_options = $properties['sauces'] ?? [];
        $sizes_options = $properties['sizes'] ?? [];
        $dresses_options = $properties['dresses'] ?? [];
        $base_price = (float)($product_row['price'] ?? 0.0);
        $selected_size = null;
        $size_price = 0.00;
        if ($size) {
            foreach ($sizes_options as $sz) {
                if (isset($sz['size']) && $sz['size'] === $size) {
                    $selected_size = $sz;
                    $size_price = (float)($sz['price'] ?? 0.0);
                    break;
                }
            }
            if (!$selected_size) {
                log_error_markdown("Invalid size selected: $size for Product ID: $product_id", "Adding to Cart");
                header("Location: index.php?error=invalid_size");
                exit;
            }
        }
        $selected_extras = [];
        $extras_total = 0.00;
        foreach ($extras as $extra_name) {
            foreach ($extras_options as $extra) {
                if (isset($extra['name']) && $extra['name'] === $extra_name) {
                    $selected_extras[] = $extra;
                    $extras_total += (float)($extra['price'] ?? 0.0);
                    break;
                }
            }
        }
        $selected_sauces = [];
        $sauces_total = 0.00;
        foreach ($sauces as $sauce_name) {
            foreach ($sauces_options as $sauce) {
                if (isset($sauce['name']) && $sauce['name'] === $sauce_name) {
                    $selected_sauces[] = $sauce;
                    $sauces_total += (float)($sauce['price'] ?? 0.0);
                    break;
                }
            }
        }
        $selected_dresses = [];
        $dresses_total = 0.00;
        foreach ($dresses as $dress_name) {
            foreach ($dresses_options as $dress) {
                if (isset($dress['name']) && $dress['name'] === $dress_name) {
                    $selected_dresses[] = $dress;
                    $dresses_total += (float)($dress['price'] ?? 0.0);
                    break;
                }
            }
        }
        $drink_details = null;
        $drink_total = 0.00;
        if ($drink_id) {
            $stmt = $pdo->prepare("SELECT * FROM drinks WHERE id = ?");
            $stmt->execute([$drink_id]);
            $drink = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($drink) {
                $drink_details = $drink;
                $drink_total = (float)($drink['price'] ?? 0.0);
            }
        }
        $unit_price = $base_price + $size_price + $extras_total + $sauces_total + $dresses_total + $drink_total;
        $total_price = $unit_price * $quantity;
        $cart_item = [
            'product_id' => $product_row['id'],
            'name' => $product_row['name'],
            'description' => $product_row['description'],
            'image_url' => $product_row['image_url'],
            'size' => $selected_size['size'] ?? null,
            'size_price' => $size_price,
            'extras' => $selected_extras,
            'sauces' => $selected_sauces,
            'dresses' => $selected_dresses,
            'drink' => $drink_details,
            'special_instructions' => $special_instructions,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'total_price' => $total_price
        ];
        $duplicate_index = array_search(true, array_map(function ($item) use ($cart_item) {
            return (
                $item['product_id'] === $cart_item['product_id'] &&
                $item['size'] === $cart_item['size'] &&
                ((isset($item['drink']['id']) && isset($cart_item['drink']['id']) && $item['drink']['id'] === $cart_item['drink']['id']) || (!isset($item['drink']) && !isset($cart_item['drink']))) &&
                $item['special_instructions'] === $cart_item['special_instructions'] &&
                count(array_intersect(array_column($item['extras'], 'name'), array_column($cart_item['extras'], 'name'))) === count($cart_item['extras']) &&
                count(array_intersect(array_column($item['sauces'], 'name'), array_column($cart_item['sauces'], 'name'))) === count($cart_item['sauces']) &&
                count(array_intersect(array_column($item['dresses'], 'name'), array_column($cart_item['dresses'], 'name'))) === count($cart_item['dresses'])
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


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $item_index = (int)$_POST['item_index'];
    if (isset($_SESSION['cart'][$item_index])) {
        $quantity = max(1, (int)$_POST['quantity']);
        $size = $_POST['size'] ?? null;
        $extras = $_POST['extras'] ?? [];
        $sauces = $_POST['sauces'] ?? [];
        $dresses = $_POST['dresses'] ?? [];
        $drink_id = isset($_POST['drink']) ? (int)$_POST['drink'] : null;
        $special_instructions = trim($_POST['special_instructions']);
        $product_id = $_SESSION['cart'][$item_index]['product_id'];
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :product_id AND is_active = 1");
        $stmt->execute(['product_id' => $product_id]);
        $product_row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($product_row) {
            $properties = json_decode($product_row['properties'], true) ?? [];
            $extras_options = $properties['extras'] ?? [];
            $sauces_options = $properties['sauces'] ?? [];
            $sizes_options = $properties['sizes'] ?? [];
            $dresses_options = $properties['dresses'] ?? [];
            $base_price = (float)($product_row['price'] ?? 0.0);
            $selected_size = null;
            $size_price = 0.00;
            if ($size) {
                foreach ($sizes_options as $sz) {
                    if (isset($sz['size']) && $sz['size'] === $size) {
                        $selected_size = $sz;
                        $size_price = (float)($sz['price'] ?? 0.0);
                        break;
                    }
                }
                if (!$selected_size) {
                    log_error_markdown("Invalid size selected: $size for Product ID: $product_id", "Updating Cart");
                    header("Location: index.php?error=invalid_size");
                    exit;
                }
            }
            $selected_extras = [];
            $extras_total = 0.00;
            foreach ($extras as $extra_name) {
                foreach ($extras_options as $extra) {
                    if (isset($extra['name']) && $extra['name'] === $extra_name) {
                        $selected_extras[] = $extra;
                        $extras_total += (float)($extra['price'] ?? 0.0);
                        break;
                    }
                }
            }
            $selected_sauces = [];
            $sauces_total = 0.00;
            foreach ($sauces as $sauce_name) {
                foreach ($sauces_options as $sauce) {
                    if (isset($sauce['name']) && $sauce['name'] === $sauce_name) {
                        $selected_sauces[] = $sauce;
                        $sauces_total += (float)($sauce['price'] ?? 0.0);
                        break;
                    }
                }
            }
            $selected_dresses = [];
            $dresses_total = 0.00;
            foreach ($dresses as $dress_name) {
                foreach ($dresses_options as $dress) {
                    if (isset($dress['name']) && $dress['name'] === $dress_name) {
                        $selected_dresses[] = $dress;
                        $dresses_total += (float)($dress['price'] ?? 0.0);
                        break;
                    }
                }
            }
            $drink_details = null;
            $drink_total = 0.00;
            if ($drink_id) {
                $stmt = $pdo->prepare("SELECT * FROM drinks WHERE id = ?");
                $stmt->execute([$drink_id]);
                $drink = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($drink) {
                    $drink_details = $drink;
                    $drink_total = (float)($drink['price'] ?? 0.0);
                }
            }
            $unit_price = $base_price + $size_price + $extras_total + $sauces_total + $dresses_total + $drink_total;
            $total_price = $unit_price * $quantity;
            $_SESSION['cart'][$item_index] = [
                'product_id' => $product_row['id'],
                'name' => $product_row['name'],
                'description' => $product_row['description'],
                'image_url' => $product_row['image_url'],
                'size' => $selected_size['size'] ?? null,
                'size_price' => $size_price,
                'extras' => $selected_extras,
                'sauces' => $selected_sauces,
                'dresses' => $selected_dresses,
                'drink' => $drink_details,
                'special_instructions' => $special_instructions,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'total_price' => $total_price
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    if (empty($_SESSION['cart'])) {
        header("Location: index.php?error=empty_cart");
        exit;
    }
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        header("Location: index.php?error=invalid_csrf_token");
        exit;
    }

    $store_id = $_SESSION['selected_store'] ?? null;
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $selected_tip_id = isset($_POST['selected_tip']) ? (int)$_POST['selected_tip'] : null;
    $is_event = isset($_POST['is_event']) && $_POST['is_event'] == '1';
    $scheduled_date = $_POST['scheduled_date'] ?? '';
    $scheduled_time = $_POST['scheduled_time'] ?? '';
    if ($is_event) {
        if (empty($scheduled_date) || empty($scheduled_time)) {
            header("Location: index.php?error=missing_scheduled_time");
            exit;
        }
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
        $scheduled_date = null;
        $scheduled_time = null;
    }

    $payment_method = $_POST['payment_method'] ?? '';
    $allowed_payment_methods = ['stripe', 'pickup', 'cash'];
    if (!in_array($payment_method, $allowed_payment_methods)) {
        header("Location: index.php?error=invalid_payment_method");
        exit;
    }
    if (empty($customer_name) || empty($customer_email) || empty($customer_phone) || empty($delivery_address)) {
        header("Location: index.php?error=invalid_order_details");
        exit;
    }

    $cart_total = array_reduce($_SESSION['cart'], fn($carry, $item) => $carry + ($item['total_price'] ?? 0.0), 0.00);
    $tip_amount = 0.00;
    if ($selected_tip_id) {
        foreach ($tip_options as $tip) {
            if ($tip['id'] == $selected_tip_id) {
                if (isset($tip['percentage']) && $tip['percentage']) {
                    $tip_amount = $cart_total * ((float)$tip['percentage'] / 100);
                } elseif (isset($tip['amount']) && $tip['amount']) {
                    $tip_amount = (float)$tip['amount'];
                }
                break;
            }
        }
    }
    $total_amount = $cart_total + $tip_amount;

    $order_data = [
        'items' => $_SESSION['cart'],
        'latitude' => $_SESSION['latitude'] ?? null,
        'longitude' => $_SESSION['longitude'] ?? null,
        'tip_id' => $selected_tip_id,
        'tip_amount' => $tip_amount,
        'store_id' => $store_id,
        'is_event' => $is_event
    ];
    $order_details_json = json_encode($order_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, customer_name, customer_email, customer_phone, delivery_address, total_amount, status_id, tip_id, tip_amount, scheduled_date, scheduled_time, payment_method, store_id, order_details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([null, $customer_name, $customer_email, $customer_phone, $delivery_address, $total_amount, 2, $selected_tip_id, $tip_amount, $scheduled_date, $scheduled_time, $payment_method, $store_id, $order_details_json]);
        $order_id = $pdo->lastInsertId();

        if ($payment_method === 'stripe') {
            $stripe_payment_method = $_POST['stripe_payment_method'] ?? '';
            if (empty($stripe_payment_method)) {
                throw new Exception("Stripe payment method ID is missing.");
            }
            $amount_cents = intval(round($total_amount * 100));
            $return_url = 'http://' . $_SERVER['HTTP_HOST'] . '/index.php?order=success&scheduled_date=' . urlencode($scheduled_date) . '&scheduled_time=' . urlencode($scheduled_time);
            $payment_intent = \Stripe\PaymentIntent::create([
                'amount' => $amount_cents,
                'currency' => 'eur',
                'payment_method' => $stripe_payment_method,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'description' => "Order ID: $order_id",
                'metadata' => ['order_id' => $order_id],
                'return_url' => $return_url,
            ]);
            if ($payment_intent->status == 'requires_action' && $payment_intent->next_action->type == 'use_stripe_sdk') {
                header('Content-Type: application/json');
                echo json_encode(['requires_action' => true, 'payment_intent_client_secret' => $payment_intent->client_secret]);
                exit;
            } elseif ($payment_intent->status == 'succeeded') {
                $stmt = $pdo->prepare("UPDATE orders SET status_id = ? WHERE id = ?");
                $stmt->execute([5, $order_id]);
                $pdo->commit();
                $_SESSION['cart'] = [];
                $_SESSION['selected_tip'] = null;
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'redirect_url' => $return_url]);
                exit;
            } else {
                throw new Exception("Invalid PaymentIntent status: " . $payment_intent->status);
            }
        } elseif ($payment_method === 'pickup') {
            $new_status_id = 3;
            $stmt = $pdo->prepare("UPDATE orders SET status_id = ? WHERE id = ?");
            $stmt->execute([$new_status_id, $order_id]);
        } elseif ($payment_method === 'cash') {
            $new_status_id = 4;
            $stmt = $pdo->prepare("UPDATE orders SET status_id = ? WHERE id = ?");
            $stmt->execute([$new_status_id, $order_id]);
        }

        if ($payment_method !== 'stripe') {
            $pdo->commit();
            $_SESSION['cart'] = [];
            $_SESSION['selected_tip'] = null;
            header("Location: index.php?order=success&scheduled_date=$scheduled_date&scheduled_time=$scheduled_time");
            exit;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        log_error_markdown("Order Placement Failed: " . $e->getMessage(), "Checkout Process");
        header("Location: index.php?error=order_failed");
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM categories ORDER BY position ASC, name ASC");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
$selected_category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

$product_query = "
    SELECT
        p.id AS product_id,
        p.product_code,
        p.name AS product_name,
        p.category,
        p.description,
        p.allergies,
        p.image_url,
        p.is_new,
        p.is_offer,
        p.is_active,
        p.properties,
        p.base_price,
        p.created_at,
        p.updated_at,
        p.category_id
    FROM products p
    WHERE p.is_active = 1" . ($selected_category_id > 0 ? " AND p.category_id = :category_id" : "") . "
    ORDER BY p.created_at DESC
";
$stmt = $pdo->prepare($product_query);
if ($selected_category_id > 0) {
    $stmt->bindParam(':category_id', $selected_category_id, PDO::PARAM_INT);
}
$stmt->execute();
$raw_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$products = [];
foreach ($raw_products as $row) {
    $properties = json_decode($row['properties'], true) ?? [];
    $sizes = $properties['sizes'] ?? [];
    $base_price = (float)($row['base_price'] ?? 0.0);
    $display_price = $base_price;
    if (!empty($sizes)) {
        $min_size_price = min(array_map(fn($s) => (float)($s['price'] ?? 0.0), $sizes));
        $display_price = $base_price + $min_size_price;
    }
    $products[] = [
        'id' => $row['product_id'],
        'product_code' => $row['product_code'],
        'name' => $row['product_name'],
        'category' => $row['category'],
        'description' => $row['description'],
        'allergies' => $row['allergies'],
        'image_url' => $row['image_url'],
        'is_new' => $row['is_new'],
        'is_offer' => $row['is_offer'],
        'is_active' => $row['is_active'],
        'base_price' => $base_price,
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
        'category_id' => $row['category_id'],
        'extras' => $properties['extras'] ?? [],
        'sauces' => $properties['sauces'] ?? [],
        'sizes' => $sizes,
        'dresses' => $properties['dresses'] ?? [],
        'display_price' => $display_price
    ];
}

$stmt = $pdo->prepare("SELECT * FROM drinks ORDER BY name ASC");
$stmt->execute();
$drinks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cart_total = array_reduce($_SESSION['cart'], fn($carry, $item) => $carry + ($item['total_price'] ?? 0.0), 0.00);
$selected_tip = $_SESSION['selected_tip'] ?? null;
$tip_amount = 0.00;
if ($selected_tip) {
    foreach ($tip_options as $tip) {
        if ($tip['id'] == $selected_tip) {
            if (isset($tip['percentage']) && $tip['percentage']) {
                $tip_amount = $cart_total * ((float)$tip['percentage'] / 100);
            } elseif (isset($tip['amount']) && $tip['amount']) {
                $tip_amount = (float)$tip['amount'];
            }
            break;
        }
    }
}
$cart_total_with_tip = $cart_total + $tip_amount;

try {
    $stmt = $pdo->prepare("SELECT * FROM banners WHERE is_active = 1 ORDER BY created_at DESC");
    $stmt->execute();
    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_error_markdown("Failed to fetch banners: " . $e->getMessage(), "Fetching Banners");
    $banners = [];
}

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Saira:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/6.6.6/css/flag-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <?php if (!empty($cart_logo) && file_exists($cart_logo)): ?>
        <link rel="icon" type="image/png" href="<?php echo $cart_logo; ?>">
    <?php endif; ?>
    <style>
        .loading-overlay {
            position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.8); z-index:9999; display:flex; justify-content:center; align-items:center;
        }
        .promo-banner .carousel-item img { height:400px; object-fit:cover; }
        .offers-section .card-img-top { height:200px; object-fit:cover; }
        .offers-section .card-title { font-size:1.25rem; font-weight:600; }
        .offers-section .card-text { font-size:0.95rem; color:#555; }
        @media (max-width:768px) {
            .promo-banner .carousel-item img { height:250px; }
            .offers-section .card-img-top { height:150px; }
        }
        .btn.disabled, .btn:disabled { opacity:0.65; cursor:not-allowed; }
        .language-switcher { position:absolute; top:10px; right:10px; }
        .order-summary { background-color:#f8f9fa; padding:20px; border-radius:5px; position:sticky; top:20px; }
        .order-title { margin-bottom:15px; }
    </style>
    <script src="https://js.stripe.com/v3/"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v7.3.0/ol.css" type="text/css">
    <script src="https://cdn.jsdelivr.net/npm/ol@v7.3.0/dist/ol.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
</head>
<body>
    <div class="loading-overlay" id="loading-overlay" aria-hidden="true">
        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
    </div>
    <?php if (!isset($_SESSION['selected_store'])): ?>
        <div class="modal fade" id="storeModal" tabindex="-1" aria-labelledby="storeModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" id="storeSelectionForm">
                        <div class="modal-header">
                            <img src="<?= htmlspecialchars($cart_logo) ?>" alt="Cart Logo" style="width: 100%; height: 80px; object-fit: cover" id="cart-logo" loading="lazy" onerror="this.src='https://via.placeholder.com/150?text=Logo';">
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
                                        <option value="<?= htmlspecialchars($store['id']) ?>"><?= htmlspecialchars($store['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="delivery_address" class="form-label">Delivery Address</label>
                                <input type="text" class="form-control" id="delivery_address" name="delivery_address" placeholder="Enter your address" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Select Location on Map</label>
                                <div id="map" class="map-container" style="height: 300px; width: 100%;"></div>
                            </div>
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
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            $(document).ready(function() {
                $('#storeModal').modal('show');
                var map = L.map('map').setView([51.505, -0.09], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);
                var marker;
                function onMapClick(e) {
                    if (marker) {
                        map.removeLayer(marker);
                    }
                    marker = L.marker(e.latlng).addTo(map);
                    $('#latitude').val(e.latlng.lat);
                    $('#longitude').val(e.latlng.lng);
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${e.latlng.lat}&lon=${e.latlng.lng}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.display_name) {
                                $('#delivery_address').val(data.display_name);
                            } else {
                                alert('Unable to fetch address. Please enter manually.');
                            }
                        })
                        .catch(error => console.error('Error fetching address:', error));
                }
                map.on('click', onMapClick);
                $('#delivery_address').on('change', function() {
                    var address = $(this).val();
                    if (address.length > 5) {
                        fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(address)}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data && data.length > 0) {
                                    var lat = data[0].lat;
                                    var lon = data[0].lon;
                                    if (marker) {
                                        map.removeLayer(marker);
                                    }
                                    marker = L.marker([lat, lon]).addTo(map);
                                    map.setView([lat, lon], 13);
                                    $('#latitude').val(lat);
                                    $('#longitude').val(lon);
                                } else {
                                    alert('Address not found. Please select manually on the map.');
                                }
                            })
                            .catch(error => console.error('Error geocoding address:', error));
                    }
                });
                $('#storeSelectionForm').on('submit', function(e) {
                    var address = $('#delivery_address').val().trim();
                    var lat = $('#latitude').val();
                    var lon = $('#longitude').val();
                    if (address === '' || lat === '' || lon === '') {
                        e.preventDefault();
                        alert('Please provide a valid address and select your location on the map.');
                    }
                });
            });
        </script>
    <?php else: ?>
        <div class="container mt-4">
            <p>You have selected store ID: <?= htmlspecialchars($_SESSION['selected_store']) ?></p>
            <p>Name of the store: <?= htmlspecialchars($_SESSION['store_name']) ?></p>
        </div>
    <?php endif; ?>
    <?php
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
    foreach ($includes as $file) {
        include $file;
    }
    ?>
    <div class="modal fade" id="checkoutModal" tabindex="-1" aria-labelledby="checkoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="index.php" id="checkoutForm">
                    <div class="modal-body">
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
                            <label><input class="form-check-input" type="checkbox" id="event_checkbox"> Event</label>
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
                                    scheduledDate.setAttribute('required', 'required');
                                    scheduledTime.setAttribute('required', 'required');
                                } else {
                                    eventDetails.style.display = 'none';
                                    scheduledDate.removeAttribute('required');
                                    scheduledTime.removeAttribute('required');
                                }
                            });
                        </script>
                        <div class="mb-3">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="paymentStripe" value="stripe" required>
                                    <label class="form-check-label" for="paymentStripe">Stripe (Online Payment)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="paymentPickup" value="pickup" required>
                                    <label class="form-check-label" for="paymentPickup">Pick-Up (Pay on Collection)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="paymentCash" value="cash" required>
                                    <label class="form-check-label" for="paymentCash">Cash on Delivery</label>
                                </div>
                            </div>
                        </div>
                        <div id="stripe-payment-section" class="mb-3" style="display: none;">
                            <label class="form-label">Credit or Debit Card</label>
                            <div id="card-element"></div>
                            <div id="card-errors" role="alert" class="text-danger mt-2"></div>
                        </div>
                        <div class="mb-3">
                            <label for="tip_selection" class="form-label">Select Tip</label>
                            <select class="form-select" id="tip_selection" name="selected_tip">
                                <?php foreach ($tip_options as $tip): ?>
                                    <option value="<?= htmlspecialchars($tip['id']) ?>" <?= ($selected_tip == $tip['id']) ? 'selected' : '' ?>>
                                        <?php
                                        if (isset($tip['percentage']) && $tip['percentage']) {
                                            echo htmlspecialchars($tip['name']) . " (" . htmlspecialchars($tip['percentage']) . "%)";
                                        } elseif (isset($tip['amount']) && $tip['amount']) {
                                            echo htmlspecialchars($tip['name']) . " (+" . number_format($tip['amount'], 2) . "€)";
                                        }
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <h5>Order Summary</h5>
                        <ul class="list-group mb-3">
                            <?php foreach ($_SESSION['cart'] as $item): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div><?= htmlspecialchars($item['name']) ?> x<?= htmlspecialchars($item['quantity']) ?><?= isset($item['size']) ? " ({$item['size']})" : '' ?></div>
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
                        <h4>Total: <?= number_format($cart_total_with_tip, 2) ?>€</h4>
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
    <main class="container my-5">
        <div class="row">
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
                                    </div>
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title"><?= htmlspecialchars($product['name']) ?></h5>
                                        <p class="card-text"><?= htmlspecialchars($product['description']) ?></p>
                                        <?php if (!empty($product['allergies'])): ?>
                                            <p class="card-text text-danger"><strong>Allergies:</strong> <?= htmlspecialchars($product['allergies']) ?></p>
                                        <?php endif; ?>
                                        <div class="mt-auto">
                                            <?php if (!empty($product['sizes'])): ?>
                                                <strong>From <?= number_format($product['display_price'], 2) ?>€</strong>
                                            <?php else: ?>
                                                <strong><?= number_format($product['base_price'], 2) ?>€</strong>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-primary btn-add-to-cart" data-bs-toggle="modal" data-bs-target="#addToCartModal<?= $product['id'] ?>">
                                                <i class="bi bi-cart-plus me-1"></i> Add to Cart
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
                                                        <?php if (empty($product['sizes'])): ?>
                                                            <h3 style="color:#d3b213"><b><?= number_format($product['base_price'], 2) ?>€</b></h3>
                                                        <?php else: ?>
                                                            <h5>Select a size to see final price</h5>
                                                            <div class="mb-3">
                                                                <label class="form-label">Select Size:</label>
                                                                <select class="form-select" name="size" required>
                                                                    <option value="">Choose a size</option>
                                                                    <?php foreach ($product['sizes'] as $size): 
                                                                        $sz_price = (float)($size['price'] ?? 0.0);
                                                                        $final_sz_price = $product['base_price'] + $sz_price;
                                                                    ?>
                                                                        <option value="<?= htmlspecialchars($size['size']) ?>"><?= htmlspecialchars($size['size']) ?> (<?= number_format($final_sz_price, 2) ?>€)</option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($product['extras'])): ?>
                                                            <div class="mb-3">
                                                                <label class="form-label">Extras</label>
                                                                <div>
                                                                    <?php foreach ($product['extras'] as $extra): ?>
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" type="checkbox" id="extra<?= $product['id'] ?>_<?= htmlspecialchars($extra['name']) ?>" name="extras[]" value="<?= htmlspecialchars($extra['name']) ?>">
                                                                            <label class="form-check-label" for="extra<?= $product['id'] ?>_<?= htmlspecialchars($extra['name']) ?>">
                                                                                <?= htmlspecialchars($extra['name']) ?> (+<?= number_format((float)($extra['price'] ?? 0.0), 2) ?>€)
                                                                            </label>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($product['sauces'])): ?>
                                                            <div class="mb-3">
                                                                <label class="form-label">Sauces</label>
                                                                <div>
                                                                    <?php foreach ($product['sauces'] as $sauce): ?>
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" type="checkbox" id="sauce<?= $product['id'] ?>_<?= htmlspecialchars($sauce['name']) ?>" name="sauces[]" value="<?= htmlspecialchars($sauce['name']) ?>">
                                                                            <label class="form-check-label" for="sauce<?= $product['id'] ?>_<?= htmlspecialchars($sauce['name']) ?>">
                                                                                <?= htmlspecialchars($sauce['name']) ?> (+<?= number_format((float)($sauce['price'] ?? 0.0), 2) ?>€)
                                                                            </label>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($product['dresses'])): ?>
                                                            <div class="mb-3">
                                                                <label class="form-label">Dresses</label>
                                                                <div>
                                                                    <?php foreach ($product['dresses'] as $dress): ?>
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" type="checkbox" id="dress<?= $product['id'] ?>_<?= htmlspecialchars($dress['name']) ?>" name="dresses[]" value="<?= htmlspecialchars($dress['name']) ?>">
                                                                            <label class="form-check-label" for="dress<?= $product['id'] ?>_<?= htmlspecialchars($dress['name']) ?>">
                                                                                <?= htmlspecialchars($dress['name']) ?> (+<?= number_format((float)($dress['price'] ?? 0.0), 2) ?>€)
                                                                            </label>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($drinks)): ?>
                                                            <div class="mb-3">
                                                                <label class="form-label">Drinks</label>
                                                                <select class="form-select" name="drink">
                                                                    <option value="">Choose a drink</option>
                                                                    <?php foreach ($drinks as $drink): ?>
                                                                        <option value="<?= htmlspecialchars($drink['id']) ?>">
                                                                            <?= htmlspecialchars($drink['name']) ?> (+<?= number_format((float)($drink['price'] ?? 0.0), 2) ?>€)
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
            <div class="col-lg-3">
                <div class="order-summary">
                    <h2 class="order-title">YOUR ORDER</h2>
                    <?php if (!empty($cart_logo) && file_exists($cart_logo)): ?>
                        <div class="mb-3 text-center">
                            <img src="<?= htmlspecialchars($cart_logo) ?>" alt="Cart Logo" class="img-fluid cart-logo" id="cart-logo" loading="lazy" onerror="this.src='https://via.placeholder.com/150?text=Logo';">
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($cart_description)): ?>
                        <div class="mb-4 cart-description" id="cart-description">
                            <p><?= nl2br(htmlspecialchars($cart_description)) ?></p>
                        </div>
                    <?php endif; ?>
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
                                            <h6><?= htmlspecialchars($item['name']) ?><?= isset($item['size']) ? " ({$item['size']})" : '' ?> x<?= htmlspecialchars($item['quantity']) ?></h6>
                                            <?php if (!empty($item['extras'])): ?>
                                                <ul>
                                                    <?php foreach ($item['extras'] as $extra): ?>
                                                        <li><?= htmlspecialchars($extra['name'] ?? '') ?> (+<?= number_format((float)($extra['price'] ?? 0.0), 2) ?>€)</li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                            <?php if (!empty($item['drink'])): ?>
                                                <p>Drink: <?= htmlspecialchars($item['drink']['name'] ?? '') ?> (+<?= number_format((float)($item['drink']['price'] ?? 0.0), 2) ?>€)</p>
                                            <?php endif; ?>
                                            <?php if (!empty($item['sauces'])): ?>
                                                <p>Sauces: <?= htmlspecialchars(implode(', ', array_column($item['sauces'], 'name'))) ?> (+<?= number_format(array_reduce($item['sauces'], fn($carry, $s) => $carry+(float)($s['price']??0.0),0.0), 2) ?>€)</p>
                                            <?php endif; ?>
                                            <?php if (!empty($item['dresses'])): ?>
                                                <p>Dresses: <?= htmlspecialchars(implode(', ', array_column($item['dresses'], 'name'))) ?> (+<?= number_format(array_reduce($item['dresses'], fn($carry, $d) => $carry+(float)($d['price']??0.0),0.0), 2) ?>€)</p>
                                            <?php endif; ?>
                                            <?php if (!empty($item['special_instructions'])): ?>
                                                <p><em>Instructions:</em> <?= htmlspecialchars($item['special_instructions']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong><?= number_format($item['total_price'], 2) ?>€</strong><br>
                                            <a href="index.php?remove=<?= $index ?>" class="btn btn-sm btn-danger mt-2"><i class="bi bi-trash"></i> Remove</a>
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
                <div class="col-md-3 col-lg-3 col-xl-3 mx-auto mt-3">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">
                        <img src="https://images.unsplash.com/photo-1550547660-d9450f859349?crop=entropy&cs=tinysrgb&fit=crop&fm=jpg&h=40&w=40" alt="Restaurant Logo" width="40" height="40" class="me-2">
                        Restaurant
                    </h5>
                    <p>Experience the finest dining with us. We offer a variety of dishes crafted from the freshest ingredients.</p>
                </div>
                <div class="col-md-2 col-lg-2 col-xl-2 mx-auto mt-3">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php" class="text-reset text-decoration-none">Home</a></li>
                        <li class="mb-2"><a href="#menu" class="text-reset text-decoration-none">Menu</a></li>
                        <li class="mb-2"><a href="#about" class="text-reset text-decoration-none">About Us</a></li>
                        <li class="mb-2"><a href="#contact" class="text-reset text-decoration-none">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-3 col-lg-2 col-xl-2 mx-auto mt-3">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Legal</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><button type="button" class="btn btn-link text-reset text-decoration-none p-0" data-bs-toggle="modal" data-bs-target="#agbModal"><i class="bi bi-file-earmark-text-fill me-2"></i> AGB</button></li>
                        <li class="mb-2"><button type="button" class="btn btn-link text-reset text-decoration-none p-0" data-bs-toggle="modal" data-bs-target="#impressumModal"><i class="bi bi-file-earmark-text-fill me-2"></i> Impressum</button></li>
                        <li class="mb-2"><button type="button" class="btn btn-link text-reset text-decoration-none p-0" data-bs-toggle="modal" data-bs-target="#datenschutzModal"><i class="bi bi-file-earmark-text-fill me-2"></i> Datenschutzerklärung</button></li>
                    </ul>
                </div>
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
                <div class="col-md-7 col-lg-8">
                    <p>© <?= date('Y') ?> <strong>Restaurant</strong>. All rights reserved.</p>
                </div>
                <div class="col-md-5 col-lg-4">
                    <div class="text-center text-md-end">
                        <div class="social-media">
                            <?php if (!empty($social_links['facebook_link'])): ?><a href="<?= htmlspecialchars($social_links['facebook_link']) ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-facebook"></i></a><?php endif; ?>
                            <?php if (!empty($social_links['twitter_link'])): ?><a href="<?= htmlspecialchars($social_links['twitter_link']) ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-twitter"></i></a><?php endif; ?>
                            <?php if (!empty($social_links['instagram_link'])): ?><a href="<?= htmlspecialchars($social_links['instagram_link']) ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-instagram"></i></a><?php endif; ?>
                            <?php if (!empty($social_links['linkedin_link'])): ?><a href="<?= htmlspecialchars($social_links['linkedin_link']) ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-linkedin"></i></a><?php endif; ?>
                            <?php if (!empty($social_links['youtube_link'])): ?><a href="<?= htmlspecialchars($social_links['youtube_link']) ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-youtube"></i></a><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(() => {
            $('#loading-overlay').fadeOut('slow');
            $('.toast').toast('show');
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
                .fail(() => Swal.fire({icon:'error', title:'Gabim', text:'Ka ndodhur një gabim. Provoni më vonë.'}))
                .always(() => $submitBtn.prop('disabled', false));
            });
            <?php if (isset($_GET['order']) && $_GET['order'] === 'success'): ?>
                setTimeout(() => {
                    $('.modal').modal('hide');
                    window.location.href = 'index.php';
                }, 5000);
            <?php endif; ?>
            const updateStoreStatus = () => {
                $.getJSON('check_store_status.php', (data) => {
                    const $controls = $('.btn-add-to-cart, .btn-checkout');
                    if (data.is_closed) {
                        $('#storeClosedModalLabel').text(data.notification.title);
                        $('#storeClosedMessage').text(data.notification.message);
                        $('#storeClosedEndTime').text(`We will reopen on ${new Date(data.notification.end_datetime).toLocaleString()}.`);
                        new bootstrap.Modal('#storeClosedModal',{backdrop:'static',keyboard:false}).show();
                        $controls.prop('disabled', true).addClass('disabled').attr('title','Store is currently closed');
                    } else {
                        const modal = bootstrap.Modal.getInstance($('#storeClosedModal')[0]);
                        if (modal) modal.hide();
                        $controls.prop('disabled', false).removeClass('disabled').attr('title','');
                    }
                }).fail(err => console.error('Error fetching store status:', err));
            };
            updateStoreStatus();
            setInterval(updateStoreStatus, 60000);
            const stripe = Stripe('pk_test_51QByfJE4KNNCb6nuSnWLZP9JXlW84zG9DnOrQDTHQJvus9D8A8vOA85S4DfRlyWgN0rxa2hHzjppchnrmhyZGflx00B2kKlxym');
            const card = stripe.elements().create('card').mount('#card-element');
            $('input[name="payment_method"]').change(() => $('#stripe-payment-section').toggle($('#paymentStripe').is(':checked')));
            $('#checkoutForm').submit(async function(e) {
                if ($('#paymentStripe').is(':checked')) {
                    e.preventDefault();
                    const $submitBtn = $(this).find('button[type="submit"]').prop('disabled', true);
                    try {
                        const {paymentMethod, error} = await stripe.createPaymentMethod({type:'card',card:card,billing_details:{name:$('#customer_name').val(),email:$('#customer_email').val(),phone:$('#customer_phone').val(),address:{line1:$('#delivery_address').val()}}});
                        if (error) { $('#card-errors').text(error.message); $submitBtn.prop('disabled', false); return; }
                        $(this).append($('<input>', {type:'hidden', name:'stripe_payment_method',value:paymentMethod.id}));
                        const formData = new URLSearchParams(new FormData(this)).toString();
                        const response = await fetch('index.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:formData}).then(res=>res.json());
                        if (response.requires_action) {
                            const {error:confirmError,paymentIntent} = await stripe.confirmCardPayment(response.payment_intent_client_secret);
                            if (confirmError) { $('#card-errors').text(confirmError.message); }
                            else if (paymentIntent.status === 'succeeded') { window.location.href = response.redirect_url; }
                        } else if (response.success) { window.location.href = response.redirect_url; }
                    } catch {
                        Swal.fire({icon:'error', title:'Error', text:'Issue processing your payment. Try again.'});
                    } finally {
                        $(this).find('button[type="submit"]').prop('disabled', false);
                    }
                }
            });
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
