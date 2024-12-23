<?php

// Include your database connection file
require 'db.php'; // Adjust the path as necessary

// Initialize CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch Tip Options from the Database
try {
    $stmt = $pdo->prepare("SELECT * FROM tips WHERE is_active = 1 ORDER BY percentage ASC, amount ASC");
    $stmt->execute();
    $tip_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle the error appropriately
    error_log("Failed to fetch tip options: " . $e->getMessage());
    $tip_options = [];
}

// Initialize $selected_tip from session or set to null
$selected_tip = $_SESSION['selected_tip'] ?? null;

// If the form is submitted, handle the POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    // Validate CSRF Token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        // Invalid CSRF Token
        header("Location: index.php?error=invalid_csrf_token");
        exit();
    }

    // Retrieve and sanitize POST data
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $payment_method = $_POST['payment_method'] ?? '';
    $is_event = isset($_POST['is_event']) && $_POST['is_event'] == '1';
    $scheduled_date = $is_event ? ($_POST['scheduled_date'] ?? '') : null;
    $scheduled_time = $is_event ? ($_POST['scheduled_time'] ?? '') : null;
    $selected_tip = $_POST['selected_tip'] ?? null;

    // Validate Required Fields
    if (empty($customer_name) || empty($customer_email) || empty($customer_phone) || empty($delivery_address)) {
        header("Location: index.php?error=missing_required_fields");
        exit();
    }

    // Validate Payment Method
    $allowed_payment_methods = ['stripe', 'pickup', 'cash'];
    if (!in_array($payment_method, $allowed_payment_methods)) {
        header("Location: index.php?error=invalid_payment_method");
        exit();
    }

    // Validate Event Details if Applicable
    if ($is_event) {
        if (empty($scheduled_date) || empty($scheduled_time)) {
            header("Location: index.php?error=missing_event_details");
            exit();
        }
        $scheduled_datetime = DateTime::createFromFormat('Y-m-d H:i', "$scheduled_date $scheduled_time");
        if (!$scheduled_datetime || $scheduled_datetime < new DateTime()) {
            header("Location: index.php?error=invalid_event_datetime");
            exit();
        }
    }

    // Calculate Order Total and Tip
    $order_total = 0.00;
    foreach ($_SESSION['cart'] as $item) {
        $order_total += $item['total_price'];
    }

    $tip_amount = 0.00;
    if ($selected_tip) {
        foreach ($tip_options as $tip) {
            if ($tip['id'] == $selected_tip) {
                if ($tip['percentage']) {
                    $tip_amount = $order_total * ($tip['percentage'] / 100);
                } elseif ($tip['amount']) {
                    $tip_amount = $tip['amount'];
                }
                break;
            }
        }
    }

    $final_total = $order_total + $tip_amount;

    // TODO: Proceed with order processing, such as saving to the database and handling payments

    // For demonstration, let's just store the selected tip in the session
    $_SESSION['selected_tip'] = $selected_tip;

    // Redirect to a success page or display a success message
    header("Location: index.php?order=success");
    exit();
}

// Calculate $final_total for display in the modal
$order_total = 0.00;
foreach ($_SESSION['cart'] as $item) {
    $order_total += $item['total_price'];
}

$tip_amount = 0.00;
if ($selected_tip) {
    foreach ($tip_options as $tip) {
        if ($tip['id'] == $selected_tip) {
            if ($tip['percentage']) {
                $tip_amount = $order_total * ($tip['percentage'] / 100);
            } elseif ($tip['amount']) {
                $tip_amount = $tip['amount'];
            }
            break;
        }
    }
}

$final_total = $order_total + $tip_amount;

// Optional: Format numbers
$order_total_formatted = number_format($order_total, 2);
$tip_amount_formatted = number_format($tip_amount, 2);
$final_total_formatted = number_format($final_total, 2);
?>
