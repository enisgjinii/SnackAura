<?php
// orders.php - Admin and Delivery Orders Management Page

// Include necessary libraries and files
require_once 'includes/db_connect.php';
require_once 'includes/header.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define the error log file
define('ERROR_LOG_FILE', __DIR__ . '/errors.md');

/**
 * Log errors in a Markdown file for better readability.
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

/**
 * Send an email using PHPMailer.
 */
function sendEmail($recipientEmail, $recipientName, $subject, $body)
{
    $mail = new PHPMailer(true);
    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'egjini17@gmail.com'; // Replace with your SMTP username
        $mail->Password = 'axnjsldfudhohipv';   // Replace with your SMTP password or app-specific password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Sender and recipient settings
        $mail->setFrom('egjini17@gmail.com', 'Yumiis');
        $mail->addAddress($recipientEmail, $recipientName);

        // Email content settings
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
    } catch (Exception $e) {
        log_error_markdown("Mail Error: " . $mail->ErrorInfo, "Sending Email to: {$recipientEmail}");
    }
}

/**
 * Send a status update email to the customer.
 */
function sendStatusUpdateEmail($email, $name, $order_id, $status, $scheduled_date = null, $scheduled_time = null)
{
    $subject = "Update Status of Your Order #{$order_id}";
    $scheduled_info = '';
    if ($scheduled_date && $scheduled_time) {
        $scheduled_info = "<p><strong>Scheduled Delivery:</strong> " . htmlspecialchars($scheduled_date) . " at " . htmlspecialchars($scheduled_time) . "</p>";
    }
    $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 0; }
                .container { padding: 20px; background-color: #ffffff; margin: 20px auto; max-width: 600px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
                .header { background-color: #4CAF50; color: white; padding: 10px 0; text-align: center; border-radius: 8px 8px 0 0; }
                .content { padding: 20px; }
                .footer { text-align: center; padding: 10px 0; color: #777777; font-size: 12px; }
                .button { background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Yumiis</h2>
                </div>
                <div class='content'>
                    <p>Pershendetje <strong>{$name}</strong>,</p>
                    <p>Statusi i porosisë tuaj <strong>#{$order_id}</strong> është përditësuar në <strong>{$status}</strong>.</p>
                    {$scheduled_info}
                    <p>Faleminderit që zgjodhët Yumiis!</p>
                    <a href='https://yourwebsite.com/orders/{$order_id}' class='button'>Shiko Detajet</a>
                </div>
                <div class='footer'>
                    &copy; " . date('Y') . " Yumiis. Të gjitha të drejtat e mbrojtura.
                </div>
            </div>
        </body>
        </html>
    ";
    sendEmail($email, $name, $subject, $body);
}

/**
 * Notify the delivery person about the new order assignment.
 */
function notifyDeliveryPerson($email, $name, $order_id, $status)
{
    $subject = "New Order Assigned - Order #{$order_id}";
    $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 0; }
                .container { padding: 20px; background-color: #ffffff; margin: 20px auto; max-width: 600px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
                .header { background-color: #2196F3; color: white; padding: 10px 0; text-align: center; border-radius: 8px 8px 0 0; }
                .content { padding: 20px; }
                .footer { text-align: center; padding: 10px 0; color: #777777; font-size: 12px; }
                .button { background-color: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Yumiis</h2>
                </div>
                <div class='content'>
                    <p>Pershendetje <strong>{$name}</strong>,</p>
                    <p>Ju keni caktuar të trajtoni porosinë <strong>#{$order_id}</strong> me statusin <strong>{$status}</strong>.</p>
                    <p>Ju lutem, procedoni sipas nevojës.</p>
                    <a href='https://yourwebsite.com/orders/{$order_id}' class='button'>Shiko Porosinë</a>
                </div>
                <div class='footer'>
                    &copy; " . date('Y') . " Yumiis. Të gjitha të drejtat e mbrojtura.
                </div>
            </div>
        </body>
        </html>
    ";
    sendEmail($email, $name, $subject, $body);
}

/**
 * Send a delay notification email to the customer.
 */
function sendDelayNotificationEmail($email, $name, $order_id, $additional_time)
{
    $subject = "Njoftim për Vonese në Porosinë #{$order_id}";
    $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 0; }
                .container { padding: 20px; background-color: #ffffff; margin: 20px auto; max-width: 600px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
                .header { background-color: #ff9800; color: white; padding: 10px 0; text-align: center; border-radius: 8px 8px 0 0; }
                .content { padding: 20px; }
                .footer { text-align: center; padding: 10px 0; color: #777777; font-size: 12px; }
                .button { background-color: #ff9800; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Yumiis</h2>
                </div>
                <div class='content'>
                    <p>Pershendetje <strong>{$name}</strong>,</p>
                    <p>Na vjen keq të informojmë se porosia juaj <strong>#{$order_id}</strong> mund të përbëhet nga vonesë për shkak të numrit të madh të porosive.</p>
                    <p>Kemi nevojë për miratimin tuaj për kohën e shtuar të dërgesës prej <strong>{$additional_time}</strong> orësh.</p>
                    <p>Ju lutem, na njoftoni nëse jeni dakord me këtë ndryshim.</p>
                    <a href='https://yourwebsite.com/contact' class='button'>Na Kontaktoni</a>
                    <p>Faleminderit për mirëkuptimin!</p>
                </div>
                <div class='footer'>
                    &copy; " . date('Y') . " Yumiis. Të gjitha të drejtat e mbrojtura.
                </div>
            </div>
        </body>
        </html>
    ";
    sendEmail($email, $name, $order_id, $additional_time);
}

/**
 * Custom exception handler to log uncaught exceptions.
 */
set_exception_handler(function ($exception) {
    log_error_markdown("Uncaught Exception: " . $exception->getMessage(), "File: " . $exception->getFile() . " Line: " . $exception->getLine());
    header("Location: orders.php?action=view&message=unknown_error");
    exit;
});

/**
 * Custom error handler to convert errors to exceptions.
 */
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

/**
 * Fetch active delivery users from the database.
 */
function getDeliveryUsers($pdo)
{
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE role = 'delivery' AND is_active = 1");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch the status history of a specific order.
 */
function getOrderStatusHistory($pdo, $order_id)
{
    $stmt = $pdo->prepare('
        SELECT osh.*, os.status, u.username AS delivery_username, u.email AS delivery_email 
        FROM order_status_history osh 
        JOIN order_statuses os ON osh.status_id = os.id 
        LEFT JOIN users u ON osh.delivery_user_id = u.id 
        WHERE osh.order_id = ? 
        ORDER BY osh.changed_at DESC
    ');
    $stmt->execute([$order_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Geocode an address to obtain latitude and longitude using OpenStreetMap's Nominatim.
 */
function geocodeAddress($address)
{
    $encoded_address = urlencode($address);
    $url = "https://nominatim.openstreetmap.org/search?format=json&limit=1&q={$encoded_address}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'YumiisAdmin/1.0'); // Replace with your application's name and version
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        log_error_markdown("cURL Error: " . curl_error($ch), "Geocoding Address: {$address}");
        curl_close($ch);
        return [null, null];
    }
    curl_close($ch);
    $data = json_decode($response, true);
    if (isset($data[0])) {
        return [$data[0]['lat'], $data[0]['lon']];
    } else {
        log_error_markdown("Geocoding Failed for Address: {$address}", "No results returned.");
        return [null, null];
    }
}

/**
 * Update the latitude and longitude of an order based on its address.
 */
function updateOrderCoordinates($pdo, $order_id, $address)
{
    list($latitude, $longitude) = geocodeAddress($address);
    if ($latitude && $longitude) {
        $stmt = $pdo->prepare("UPDATE orders SET latitude = ?, longitude = ? WHERE id = ?");
        $stmt->execute([$latitude, $longitude, $order_id]);
    }
}

/**
 * Fetch all possible statuses.
 */
function getAllStatuses($pdo)
{
    return $pdo->query('SELECT * FROM order_statuses ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch all active orders with necessary details, including tip information
 */
function getActiveOrders($pdo, $user_role, $user_id)
{
    if ($user_role === 'admin') {
        $stmt = $pdo->prepare('
            SELECT 
                o.*, 
                os.status, 
                t.name AS tip_name, 
                t.percentage AS tip_percentage, 
                t.amount AS tip_fixed_amount, 
                c.order_count, 
                u.username AS delivery_username 
            FROM orders o 
            JOIN order_statuses os ON o.status_id = os.id 
            LEFT JOIN tips t ON o.tip_id = t.id 
            JOIN (
                SELECT 
                    COALESCE(customer_email, "") AS customer_email, 
                    COALESCE(customer_phone, "") AS customer_phone, 
                    COUNT(*) AS order_count 
                FROM orders 
                WHERE deleted_at IS NULL 
                GROUP BY customer_email, customer_phone
            ) c ON (o.customer_email = c.customer_email OR o.customer_phone = c.customer_phone) 
            LEFT JOIN users u ON o.delivery_user_id = u.id 
            WHERE o.deleted_at IS NULL 
            ORDER BY os.id ASC, o.created_at DESC
        ');
        $stmt->execute();
    } elseif ($user_role === 'delivery') {
        // Delivery personnel see only their assigned orders
        $stmt = $pdo->prepare('
            SELECT 
                o.*, 
                os.status, 
                t.name AS tip_name, 
                t.percentage AS tip_percentage, 
                t.amount AS tip_fixed_amount, 
                c.order_count, 
                u.username AS delivery_username 
            FROM orders o 
            JOIN order_statuses os ON o.status_id = os.id 
            LEFT JOIN tips t ON o.tip_id = t.id 
            JOIN (
                SELECT 
                    COALESCE(customer_email, "") AS customer_email, 
                    COALESCE(customer_phone, "") AS customer_phone, 
                    COUNT(*) AS order_count 
                FROM orders 
                WHERE deleted_at IS NULL 
                GROUP BY customer_email, customer_phone
            ) c ON (o.customer_email = c.customer_email OR o.customer_phone = c.customer_phone) 
            LEFT JOIN users u ON o.delivery_user_id = u.id 
            WHERE o.deleted_at IS NULL AND o.delivery_user_id = ? 
            ORDER BY os.id ASC, o.created_at DESC
        ');
        $stmt->execute([$user_id]);
    } else {
        // For other roles like 'waiter', you can define similar queries or restrict access
        return [];
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch all active orders with necessary details for delivery users
 */
function getDeliveryOrders($pdo, $user_id)
{
    $stmt = $pdo->prepare('
        SELECT 
            o.*, 
            os.status, 
            t.name AS tip_name, 
            t.percentage AS tip_percentage, 
            t.amount AS tip_fixed_amount 
        FROM orders o 
        JOIN order_statuses os ON o.status_id = os.id 
        LEFT JOIN tips t ON o.tip_id = t.id 
        WHERE o.deleted_at IS NULL AND o.delivery_user_id = ? 
        ORDER BY o.created_at DESC
    ');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch all active orders with necessary details for admin
 */
function getAdminOrders($pdo)
{
    return getActiveOrders($pdo, 'admin', null);
}

/**
 * Fetch all active orders with necessary details for delivery
 */
function getDeliveryOrdersForUser($pdo, $user_id)
{
    return getActiveOrders($pdo, 'delivery', $user_id);
}

// Determine the user's role and ID
$user_role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Initialize variables
$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

// Enforce role-based access control
$allowed_actions_admin = ['view', 'update_status', 'delete', 'permanent_delete', 'restore', 'view_trash', 'view_details', 'update_status_form', 'customer_counts', 'manage_ratings', 'delete_rating', 'send_delay_notification'];
$allowed_actions_delivery = ['view', 'view_details', 'update_status', 'send_delay_notification'];

if ($user_role === 'admin') {
    $allowed_actions = $allowed_actions_admin;
} elseif ($user_role === 'delivery') {
    $allowed_actions = $allowed_actions_delivery;
} else {
    // If role is not recognized, deny access
    header('HTTP/1.1 403 Forbidden');
    echo "<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>";
    require_once 'includes/footer.php';
    exit();
}

// Check if the requested action is allowed for the user's role
if (!in_array($action, $allowed_actions)) {
    header('HTTP/1.1 403 Forbidden');
    echo "<h1>403 Forbidden</h1><p>You do not have permission to perform this action.</p>";
    require_once 'includes/footer.php';
    exit();
}

// Handle different actions
switch ($action) {
    case 'update_status':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
            $status_id = $_POST['status_id'] ?? 0;
            $delivery_user_id = $_POST['delivery_user_id'] ?? null;
            $scheduled_date = $_POST['scheduled_date'] ?? null;
            $scheduled_time = $_POST['scheduled_time'] ?? null;

            // Validate scheduled date and time
            if ($scheduled_date && $scheduled_time) {
                $scheduled_datetime = DateTime::createFromFormat('Y-m-d H:i', "$scheduled_date $scheduled_time");
                $current_datetime = new DateTime();
                if (!$scheduled_datetime || $scheduled_datetime < $current_datetime) {
                    $msg = 'Invalid scheduled date and time.';
                    header('Location: orders.php?action=update_status_form&id=' . $id . '&message=' . urlencode($msg));
                    exit();
                }
            }

            if ($status_id && is_numeric($status_id)) {
                // Fetch the new status
                $stmt = $pdo->prepare('SELECT status FROM order_statuses WHERE id = ?');
                $stmt->execute([$status_id]);
                $new_status = $stmt->fetchColumn();
                if ($new_status) {
                    // Fetch order details including latitude and longitude
                    $stmt = $pdo->prepare('SELECT customer_email, customer_name, delivery_address, latitude, longitude FROM orders WHERE id = ? AND deleted_at IS NULL');
                    $stmt->execute([$id]);
                    $order = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($order) {
                        // For delivery role, ensure they can only update to specific statuses
                        if ($user_role === 'delivery') {
                            // Define allowed statuses for delivery role
                            $allowed_delivery_statuses = ['Delivered']; // Adjust as per your statuses
                            if (!in_array($new_status, $allowed_delivery_statuses)) {
                                $msg = 'You do not have permission to set this status.';
                                header('Location: orders.php?action=view&message=' . urlencode($msg));
                                exit();
                            }
                            // Ensure the order is assigned to the delivery user
                            if ($order['delivery_address'] && $order['delivery_user_id'] != $user_id) {
                                $msg = 'You are not assigned to this order.';
                                header('Location: orders.php?action=view&message=' . urlencode($msg));
                                exit();
                            }
                        }

                        // Check if latitude or longitude is null
                        if (is_null($order['latitude']) || is_null($order['longitude'])) {
                            // Geocode the address and update coordinates
                            updateOrderCoordinates($pdo, $id, $order['delivery_address']);
                            // Re-fetch the updated order
                            $stmt->execute([$id]);
                            $order = $stmt->fetch(PDO::FETCH_ASSOC);
                        }

                        // Update status, delivery user, scheduled_date, and scheduled_time
                        $update = $pdo->prepare('UPDATE orders SET status_id = ?, delivery_user_id = ?, scheduled_date = ?, scheduled_time = ? WHERE id = ?');
                        if ($update->execute([$status_id, $delivery_user_id ?: null, $scheduled_date, $scheduled_time, $id])) {
                            // Insert into status history
                            $history = $pdo->prepare('INSERT INTO order_status_history (order_id, status_id, delivery_user_id) VALUES (?, ?, ?)');
                            $history->execute([$id, $status_id, $delivery_user_id ?: null]);

                            // Fetch updated order details for email
                            $stmt = $pdo->prepare('SELECT customer_email, customer_name, delivery_address, delivery_user_id FROM orders WHERE id = ?');
                            $stmt->execute([$id]);
                            $order = $stmt->fetch(PDO::FETCH_ASSOC);

                            // Send emails
                            sendStatusUpdateEmail($order['customer_email'], $order['customer_name'], $id, $new_status, $scheduled_date, $scheduled_time);

                            if ($delivery_user_id && $user_role === 'admin') {
                                // Only admin can notify delivery person
                                $stmt = $pdo->prepare('SELECT email, username FROM users WHERE id = ?');
                                $stmt->execute([$delivery_user_id]);
                                $delivery_user = $stmt->fetch(PDO::FETCH_ASSOC);
                                if ($delivery_user) {
                                    notifyDeliveryPerson($delivery_user['email'], $delivery_user['username'], $id, $new_status);
                                }
                            }

                            // Redirect with success message
                            $msg = 'Order status updated successfully.';
                            header('Location: orders.php?action=view&message=' . urlencode($msg));
                            exit();
                        }
                    }
                }
            }

            // Redirect with failure message
            $msg = 'Failed to update status.';
            header('Location: orders.php?action=update_status_form&id=' . $id . '&message=' . urlencode($msg));
            exit();
        }
        break;

    case 'delete':
        if ($user_role === 'admin' && $id > 0) {
            $delete = $pdo->prepare('UPDATE orders SET deleted_at = NOW() WHERE id = ?');
            $success = $delete->execute([$id]);
            $msg = $success ? 'Order has been moved to Trash.' : 'Failed to move the order to Trash.';
            header('Location: orders.php?action=view&message=' . urlencode($msg));
            exit();
        }
        break;

    case 'permanent_delete':
        if ($user_role === 'admin' && $id > 0) {
            $perm_delete = $pdo->prepare('DELETE FROM orders WHERE id = ?');
            $success = $perm_delete->execute([$id]);
            $msg = $success ? 'Order has been permanently deleted.' : 'Failed to permanently delete the order.';
            header('Location: orders.php?action=view_trash&message=' . urlencode($msg));
            exit();
        }
        break;

    case 'restore':
        if ($user_role === 'admin' && $id > 0) {
            $restore = $pdo->prepare('UPDATE orders SET deleted_at = NULL WHERE id = ?');
            $success = $restore->execute([$id]);
            $msg = $success ? 'Order has been restored.' : 'Failed to restore the order.';
            header('Location: orders.php?action=view_trash&message=' . urlencode($msg));
            exit();
        }
        break;

    case 'view_trash':
        if ($user_role === 'admin') {
            try {
                $stmt = $pdo->prepare('
                    SELECT o.*, os.status, u.username AS delivery_username 
                    FROM orders o 
                    JOIN order_statuses os ON o.status_id = os.id 
                    LEFT JOIN users u ON o.delivery_user_id = u.id 
                    WHERE o.deleted_at IS NOT NULL 
                    ORDER BY o.deleted_at DESC
                ');
                $stmt->execute();
                $trash_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $trash_orders = [];
                $message = '<div class="alert alert-danger">Failed to retrieve orders from Trash.</div>';
            }
        }
        break;

    case 'view_details':
        if ($id > 0) {
            try {
                // Fetch order details including latitude, longitude, and tip information
                $stmt = $pdo->prepare('
                    SELECT 
                        o.*, 
                        os.status, 
                        t.name AS tip_name, 
                        t.percentage AS tip_percentage, 
                        t.amount AS tip_fixed_amount 
                    FROM orders o 
                    JOIN order_statuses os ON o.status_id = os.id 
                    LEFT JOIN tips t ON o.tip_id = t.id 
                    WHERE o.id = ? AND o.deleted_at IS NULL
                ');
                $stmt->execute([$id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($order) {
                    // Geocode if necessary
                    if (is_null($order['latitude']) || is_null($order['longitude'])) {
                        updateOrderCoordinates($pdo, $id, $order['delivery_address']);
                        // Re-fetch the updated order
                        $stmt->execute([$id]);
                        $order = $stmt->fetch(PDO::FETCH_ASSOC);
                    }

                    // Fetch order items
                    $stmt = $pdo->prepare('
                        SELECT oi.*, p.name AS product_name, s.name AS size_name 
                        FROM order_items oi 
                        JOIN products p ON oi.product_id = p.id 
                        LEFT JOIN sizes s ON oi.size_id = s.id 
                        WHERE oi.order_id = ?
                    ');
                    $stmt->execute([$id]);
                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Fetch extras and drinks for each item
                    foreach ($items as &$item) {
                        // Fetch extras
                        $stmt_extras = $pdo->prepare('
                            SELECT e.name, oe.quantity, oe.unit_price, oe.total_price 
                            FROM order_extras oe 
                            JOIN extras e ON oe.extra_id = e.id 
                            WHERE oe.order_item_id = ?
                        ');
                        $stmt_extras->execute([$item['id']]);
                        $item['extras'] = $stmt_extras->fetchAll(PDO::FETCH_ASSOC);

                        // Fetch drinks
                        $stmt_drinks = $pdo->prepare('
                            SELECT d.name, od.quantity, od.unit_price, od.total_price 
                            FROM order_drinks od 
                            JOIN drinks d ON od.drink_id = d.id 
                            WHERE od.order_item_id = ?
                        ');
                        $stmt_drinks->execute([$item['id']]);
                        $item['drinks'] = $stmt_drinks->fetchAll(PDO::FETCH_ASSOC);
                    }

                    // Fetch status history
                    $status_history = getOrderStatusHistory($pdo, $id);
                } else {
                    $message = '<div class="alert alert-warning">The order does not exist or is in Trash.</div>';
                    require_once 'includes/footer.php';
                    exit();
                }
            } catch (PDOException $e) {
                $message = '<div class="alert alert-danger">Failed to retrieve order details.</div>';
                require_once 'includes/footer.php';
                exit();
            }
        }
        break;

    case 'update_status_form':
        if ($id > 0) {
            try {
                // Fetch order details including latitude, longitude, and tip information
                $stmt = $pdo->prepare('
                    SELECT 
                        o.*, 
                        os.status, 
                        t.name AS tip_name, 
                        t.percentage AS tip_percentage, 
                        t.amount AS tip_fixed_amount 
                    FROM orders o 
                    JOIN order_statuses os ON o.status_id = os.id 
                    LEFT JOIN tips t ON o.tip_id = t.id 
                    WHERE o.id = ? AND o.deleted_at IS NULL
                ');
                $stmt->execute([$id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($order) {
                    // Fetch all possible statuses
                    $statuses = getAllStatuses($pdo);

                    // Fetch active delivery users (only for admin)
                    if ($user_role === 'admin') {
                        $delivery_users = getDeliveryUsers($pdo);
                    }

                    // Capture any messages passed via GET
                    $message = $_GET['message'] ?? '';
                } else {
                    $message = '<div class="alert alert-warning">The order does not exist or is in Trash.</div>';
                    require_once 'includes/footer.php';
                    exit();
                }
            } catch (PDOException $e) {
                $message = '<div class="alert alert-danger">Failed to retrieve data.</div>';
                require_once 'includes/footer.php';
                exit();
            }
        }
        break;

    case 'customer_counts':
        try {
            $stmt = $pdo->prepare('
                SELECT customer_name, customer_email, customer_phone, COUNT(*) AS order_count 
                FROM orders 
                WHERE deleted_at IS NULL 
                GROUP BY customer_email, customer_phone 
                ORDER BY order_count DESC
            ');
            $stmt->execute();
            $customer_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $customer_counts = [];
            $message = '<div class="alert alert-danger">Failed to retrieve customer order counts.</div>';
        }
        break;

    case 'manage_ratings':
        if ($user_role === 'admin') {
            try {
                $stmt = $pdo->prepare('SELECT * FROM ratings ORDER BY created_at DESC');
                $stmt->execute();
                $ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $ratings = [];
                $message = '<div class="alert alert-danger">Failed to retrieve ratings.</div>';
            }
        }
        break;

    case 'delete_rating':
        if ($user_role === 'admin' && $id > 0) {
            try {
                $delete = $pdo->prepare('DELETE FROM ratings WHERE id = ?');
                $success = $delete->execute([$id]);
                $msg = $success ? 'Rating has been successfully deleted.' : 'Failed to delete the rating.';
                header('Location: orders.php?action=manage_ratings&message=' . urlencode($msg));
                exit();
            } catch (PDOException $e) {
                $msg = 'Failed to delete the rating.';
                header('Location: orders.php?action=manage_ratings&message=' . urlencode($msg));
                exit();
            }
        }
        break;

    case 'send_delay_notification':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
            $additional_time = (int)($_POST['additional_time'] ?? 0);
            if ($additional_time > 0) {
                try {
                    // Fetch order details
                    $stmt = $pdo->prepare('SELECT customer_email, customer_name FROM orders WHERE id = ? AND deleted_at IS NULL');
                    $stmt->execute([$id]);
                    $order = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($order) {
                        // Send delay notification email
                        sendDelayNotificationEmail($order['customer_email'], $order['customer_name'], $id, $additional_time);
                        $msg = 'Delay notification has been sent successfully.';
                        header('Location: orders.php?action=view&message=' . urlencode($msg));
                        exit();
                    } else {
                        $msg = 'The order does not exist or is in Trash.';
                        header('Location: orders.php?action=view&message=' . urlencode($msg));
                        exit();
                    }
                } catch (PDOException $e) {
                    $msg = 'Failed to send delay notification.';
                    header('Location: orders.php?action=view&message=' . urlencode($msg));
                    exit();
                }
            } else {
                $msg = 'Please enter a valid additional time.';
                header('Location: orders.php?action=view&message=' . urlencode($msg));
                exit();
            }
        }
        break;

    case 'view':
    default:
        try {
            // Fetch all active orders based on user role
            if ($user_role === 'admin') {
                $orders = getAdminOrders($pdo);
            } elseif ($user_role === 'delivery') {
                $orders = getDeliveryOrdersForUser($pdo, $user_id);
            } else {
                $orders = [];
            }

            // Fetch all possible statuses
            $statuses = getAllStatuses($pdo);

            // Group orders by status
            $grouped_orders = [];
            foreach ($statuses as $status) {
                $grouped_orders[$status['status']] = [];
            }
            foreach ($orders as $order) {
                $grouped_orders[$order['status']][] = $order;
            }
        } catch (PDOException $e) {
            $grouped_orders = [];
            $message = '<div class="alert alert-danger">Failed to retrieve orders.</div>';
        }
        break;
}

// HTML Content Starts Here
?>
<div class="container-fluid mt-4">
    <!-- Display Success or Error Messages -->
    <?php if (isset($_GET['message'])): ?>
        <div class="alert alert-<?= (strpos($_GET['message'], 'successfully') !== false || strpos($_GET['message'], 'sukses') !== false || strpos($_GET['message'], 'suksesshëm') !== false) ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_GET['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif (isset($message)): ?>
        <?= $message ?>
    <?php endif; ?>

    <!-- Handle Different Actions -->
    <?php if ($action === 'view'): ?>
        <!-- Orders View -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Orders</h2>
            <?php if ($user_role === 'admin'): ?>
                <div>
                    <a href="orders.php?action=customer_counts" class="btn btn-primary me-2">
                        <i class="fas fa-chart-bar"></i> Customer Order Counts
                    </a>
                    <a href="orders.php?action=manage_ratings" class="btn btn-warning me-2">
                        <i class="fas fa-star"></i> Manage Ratings
                    </a>
                    <a href="orders.php?action=view_trash" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Trash
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php foreach ($grouped_orders as $status => $orders): ?>
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-tag"></i> <?= htmlspecialchars($status) ?> Orders</span>
                    <span class="badge bg-primary"><?= count($orders) ?></span>
                </div>
                <div class="card-body">
                    <?php if (count($orders) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-bordered data-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Address</th>
                                        <th>Total (€)</th>
                                        <th>Tip</th>
                                        <th>Tip Amount (€)</th>
                                        <th>Scheduled Date</th>
                                        <th>Scheduled Time</th>
                                        <th>Created At</th>
                                        <th>Order Count</th>
                                        <th>Delivery Person</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($order['id']) ?></td>
                                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                            <td><?= htmlspecialchars($order['customer_email']) ?></td>
                                            <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                                            <td><?= htmlspecialchars($order['delivery_address']) ?></td>
                                            <td><?= number_format($order['total_amount'], 2) ?>€</td>
                                            <td>
                                                <?php
                                                if ($order['tip_name']) {
                                                    $tip_details = $order['tip_percentage'] ? htmlspecialchars($order['tip_percentage']) . '%' : htmlspecialchars(number_format($order['tip_fixed_amount'], 2)) . '€';
                                                    echo "<span class='badge bg-info' data-bs-toggle='tooltip' data-bs-placement='top' title='Selected Tip: {$tip_details}'>" . htmlspecialchars($order['tip_name']) . "</span>";
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                if ($order['tip_amount'] > 0) {
                                                    echo "<span class='badge bg-warning'>" . number_format($order['tip_amount'], 2) . "€</span>";
                                                } else {
                                                    echo '0.00€';
                                                }
                                                ?>
                                            </td>
                                            <td><?= htmlspecialchars($order['scheduled_date'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($order['scheduled_time'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($order['created_at']) ?></td>
                                            <td>
                                                <?= htmlspecialchars($order['order_count']) ?>
                                                <?php if ($order['order_count'] > 1): ?>
                                                    <span class="badge bg-primary">Repeat</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $order['delivery_username'] ? htmlspecialchars($order['delivery_username']) : 'N/A' ?></td>
                                            <td>
                                                <!-- Action Buttons -->
                                                <a href="orders.php?action=view_details&id=<?= $order['id'] ?>" class="btn btn-sm btn-info me-1" data-bs-toggle="tooltip" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>

                                                <?php if ($user_role === 'admin' || ($user_role === 'delivery' && in_array($order['status'], ['Assigned', 'Processing']))) : ?>
                                                    <a href="orders.php?action=update_status_form&id=<?= $order['id'] ?>" class="btn btn-sm btn-warning me-1" data-bs-toggle="tooltip" title="Update Status">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>

                                                <?php if ($user_role === 'admin'): ?>
                                                    <button type="button" class="btn btn-sm btn-danger me-1" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $order['id'] ?>" data-bs-toggle="tooltip" title="Delete">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#delayNotificationModal<?= $order['id'] ?>" data-bs-toggle="tooltip" title="Delay Notification">
                                                        <i class="fas fa-clock"></i>
                                                    </button>
                                                <?php elseif ($user_role === 'delivery'): ?>
                                                    <!-- Delivery users can mark as delivered -->
                                                    <?php if ($order['status'] !== 'Delivered'): ?>
                                                        <a href="orders.php?action=update_status&id=<?= $order['id'] ?>&status_id=<?= getDeliveredStatusId($pdo) ?>" class="btn btn-sm btn-success me-1" data-bs-toggle="tooltip" title="Mark as Delivered">
                                                            <i class="fas fa-check-circle"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                        <!-- Delete Order Modal (Admin Only) -->
                                        <?php if ($user_role === 'admin'): ?>
                                            <div class="modal fade" id="deleteModal<?= $order['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $order['id'] ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-danger text-white">
                                                            <h5 class="modal-title" id="deleteModalLabel<?= $order['id'] ?>">Delete Order</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            Are you sure you want to delete this order?
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                                                            <a href="orders.php?action=delete&id=<?= $order['id'] ?>" class="btn btn-danger">Yes, Delete</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Send Delay Notification Modal (Admin Only) -->
                                            <div class="modal fade" id="delayNotificationModal<?= $order['id'] ?>" tabindex="-1" aria-labelledby="delayNotificationModalLabel<?= $order['id'] ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-warning text-dark">
                                                            <h5 class="modal-title" id="delayNotificationModalLabel<?= $order['id'] ?>">Send Delay Notification - Order #<?= $order['id'] ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <form method="POST" action="orders.php?action=send_delay_notification&id=<?= $order['id'] ?>">
                                                            <div class="modal-body">
                                                                <div class="mb-3">
                                                                    <label for="additional_time_<?= $order['id'] ?>" class="form-label">Additional Time (in hours)</label>
                                                                    <input type="number" class="form-control" id="additional_time_<?= $order['id'] ?>" name="additional_time" min="1" required>
                                                                </div>
                                                                <p>Are you sure you want to send a delay notification for this order?</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                                                                <button type="submit" class="btn btn-warning">Yes, Send</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p>No orders in this category.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

    <?php elseif ($action === 'manage_ratings' && $user_role === 'admin'): ?>
        <!-- Manage Ratings View -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Manage Customer Ratings</h2>
            <a href="orders.php?action=view" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>
        </div>
        <?php if (isset($ratings) && count($ratings) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered data-table">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Anonymous</th>
                            <th>Rating</th>
                            <th>Comments</th>
                            <th>Submitted At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ratings as $rating): ?>
                            <tr>
                                <td><?= htmlspecialchars($rating['id']) ?></td>
                                <td><?= $rating['anonymous'] ? 'Anonymous' : htmlspecialchars($rating['full_name']) ?></td>
                                <td><?= $rating['anonymous'] ? 'N/A' : htmlspecialchars($rating['email']) ?></td>
                                <td><?= $rating['anonymous'] ? 'N/A' : htmlspecialchars($rating['phone'] ?? 'N/A') ?></td>
                                <td><?= $rating['anonymous'] ? '<span class="badge bg-secondary">Yes</span>' : '<span class="badge bg-success">No</span>' ?></td>
                                <td><?= str_repeat('⭐', $rating['rating']) ?></td>
                                <td><?= htmlspecialchars($rating['comments'] ?? 'No comments') ?></td>
                                <td><?= htmlspecialchars($rating['created_at']) ?></td>
                                <td>
                                    <!-- Delete Rating Button -->
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteRatingModal<?= $rating['id'] ?>" data-bs-toggle="tooltip" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                    <!-- Delete Rating Modal -->
                                    <div class="modal fade" id="deleteRatingModal<?= $rating['id'] ?>" tabindex="-1" aria-labelledby="deleteRatingModalLabel<?= $rating['id'] ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title" id="deleteRatingModalLabel<?= $rating['id'] ?>">Delete Rating</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    Are you sure you want to delete this rating?
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                                                    <a href="orders.php?action=delete_rating&id=<?= $rating['id'] ?>" class="btn btn-danger">Yes, Delete</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No ratings submitted yet.</p>
        <?php endif; ?>

    <?php elseif ($action === 'view_details' && $id > 0): ?>
        <!-- View Order Details -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Order Details - ID: <?= htmlspecialchars($order['id']) ?></h2>
            <a href="orders.php?action=view" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>
        </div>
        <!-- Order Information Card -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <span><i class="fas fa-info-circle"></i> Order Information</span>
                <span class="badge bg-primary"><?= htmlspecialchars($order['status']) ?></span>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Customer Information -->
                    <div class="col-md-6">
                        <p><strong>Customer Name:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($order['customer_email']) ?></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone']) ?></p>
                        <p><strong>Address:</strong> <?= nl2br(htmlspecialchars($order['delivery_address'])) ?></p>
                    </div>
                    <!-- Order Details -->
                    <div class="col-md-6">
                        <p><strong>Total Amount:</strong> <?= number_format($order['total_amount'], 2) ?>€</p>
                        <p><strong>Status:</strong> <?= htmlspecialchars($order['status']) ?></p>
                        <p><strong>Tip:</strong>
                            <?php
                            if ($order['tip_name']) {
                                $tip_details = $order['tip_percentage'] ? htmlspecialchars($order['tip_percentage']) . '%' : htmlspecialchars(number_format($order['tip_fixed_amount'], 2)) . '€';
                                echo "<span class='badge bg-info' data-bs-toggle='tooltip' data-bs-placement='top' title='Selected Tip: {$tip_details}'>" . htmlspecialchars($order['tip_name']) . "</span>";
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </p>
                        <p><strong>Tip Amount:</strong>
                            <?php
                            if ($order['tip_amount'] > 0) {
                                echo "<span class='badge bg-warning'>" . number_format($order['tip_amount'], 2) . "€</span>";
                            } else {
                                echo '0.00€';
                            }
                            ?>
                        </p>
                        <p><strong>Created At:</strong> <?= htmlspecialchars($order['created_at']) ?></p>
                        <?php if ($order['delivery_user_id']): ?>
                            <?php
                            $stmt = $pdo->prepare('SELECT username, email FROM users WHERE id = ?');
                            $stmt->execute([$order['delivery_user_id']]);
                            $delivery_user = $stmt->fetch(PDO::FETCH_ASSOC);
                            ?>
                            <p><strong>Delivery Person:</strong> <?= htmlspecialchars($delivery_user['username']) ?> (<?= htmlspecialchars($delivery_user['email']) ?>)</p>
                        <?php endif; ?>
                        <!-- Scheduled Date and Time -->
                        <?php if ($order['scheduled_date'] || $order['scheduled_time']): ?>
                            <p><strong>Scheduled Delivery:</strong>
                                <?= htmlspecialchars($order['scheduled_date'] ?? 'N/A') ?>
                                <?= htmlspecialchars($order['scheduled_time'] ?? '') ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- Order Items Card -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <span><i class="fas fa-boxes"></i> Order Items</span>
                <span class="badge bg-primary"><?= count($items) ?> Item<?= count($items) > 1 ? 's' : '' ?></span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>Product</th>
                                <th>Size</th>
                                <th>Quantity</th>
                                <th>Price (€)</th>
                                <th>Extras</th>
                                <th>Drinks</th>
                                <th>Special Instructions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                                    <td><?= htmlspecialchars($item['size_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($item['quantity']) ?></td>
                                    <td><?= number_format($item['price'], 2) ?></td>
                                    <td>
                                        <?php if (!empty($item['extras'])): ?>
                                            <?php foreach ($item['extras'] as $extra): ?>
                                                <span class="badge bg-primary"><?= htmlspecialchars($extra['name']) ?> x<?= htmlspecialchars($extra['quantity']) ?> (+<?= number_format($extra['unit_price'], 2) ?>€)</span><br>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['drinks'])): ?>
                                            <?php foreach ($item['drinks'] as $drink): ?>
                                                <span class="badge bg-info"><?= htmlspecialchars($drink['name']) ?> x<?= htmlspecialchars($drink['quantity']) ?> (+<?= number_format($drink['unit_price'], 2) ?>€)</span><br>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($item['special_instructions']) ?: '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Tip Summary -->
                <div class="mt-3">
                    <h5>Selected Tip</h5>
                    <p>
                        <strong>Tip:</strong>
                        <?php
                        if ($order['tip_name']) {
                            $tip_details = $order['tip_percentage'] ? htmlspecialchars($order['tip_percentage']) . '%' : htmlspecialchars(number_format($order['tip_fixed_amount'], 2)) . '€';
                            echo "<span class='badge bg-info' data-bs-toggle='tooltip' data-bs-placement='top' title='Selected Tip: {$tip_details}'>" . htmlspecialchars($order['tip_name']) . "</span>";
                        } else {
                            echo 'N/A';
                        }
                        ?>
                        <br>
                        <strong>Tip Amount:</strong>
                        <?php
                        if ($order['tip_amount'] > 0) {
                            echo "<span class='badge bg-warning'>" . number_format($order['tip_amount'], 2) . "€</span>";
                        } else {
                            echo '0.00€';
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <!-- Delivery Address Map Card -->
        <?php if (!empty($order['latitude']) && !empty($order['longitude'])): ?>
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-map-marker-alt"></i> Delivery Address</span>
                </div>
                <div class="card-body">
                    <div id="map" style="height: 400px;"></div>
                </div>
            </div>
            <!-- Leaflet Map Script -->
            <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js" integrity="sha512-BwHfrr4c9kmRkLw6iXFdzcdWV/PGkVgiIyIWLLlTSXzWQzxuSg4DiQUCpauz/EWjgk5TYQqX/kvn9pG1NpYfqg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var map = L.map('map').setView([<?= htmlspecialchars($order['latitude']) ?>, <?= htmlspecialchars($order['longitude']) ?>], 15);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '© OpenStreetMap'
                    }).addTo(map);
                    L.marker([<?= htmlspecialchars($order['latitude']) ?>, <?= htmlspecialchars($order['longitude']) ?>])
                        .addTo(map)
                        .bindPopup("<b>Delivery Address</b><br><?= htmlspecialchars($order['delivery_address']) ?>")
                        .openPopup();
                });
            </script>
        <?php else: ?>
            <div class="alert alert-warning">The delivery address is not set or could not be geocoded.</div>
        <?php endif; ?>
        <!-- Order Status History Card -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span><i class="fas fa-history"></i> Order Status History</span>
                <span class="badge bg-primary"><?= count($status_history) ?> Changes</span>
            </div>
            <div class="card-body">
                <?php if (isset($status_history) && count($status_history) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-bordered data-table">
                            <thead class="table-dark">
                                <tr>
                                    <th>Status</th>
                                    <th>Delivery Person</th>
                                    <th>Changed At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($status_history as $history): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($history['status']) ?></td>
                                        <td>
                                            <?= $history['delivery_username'] ? htmlspecialchars($history['delivery_username']) . ' (' . htmlspecialchars($history['delivery_email']) . ')' : 'N/A' ?>
                                        </td>
                                        <td><?= htmlspecialchars($history['changed_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No status history recorded.</p>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($action === 'update_status_form' && $id > 0): ?>
        <!-- Update Order Status Form -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Update Order Status - ID: <?= htmlspecialchars($order['id']) ?></h2>
            <a href="orders.php?action=view" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>
        </div>
        <?php if (!empty($message)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="orders.php?action=update_status&id=<?= $id ?>">
                    <div class="mb-3">
                        <label for="status_id" class="form-label">Select New Status</label>
                        <select class="form-select" id="status_id" name="status_id" required>
                            <?php foreach ($statuses as $status_option): ?>
                                <?php
                                // For delivery users, restrict available statuses
                                if ($user_role === 'delivery') {
                                    $allowed_statuses_delivery = ['Delivered']; // Adjust based on your status names
                                    if (!in_array($status_option['status'], $allowed_statuses_delivery)) {
                                        continue;
                                    }
                                }
                                ?>
                                <option value="<?= htmlspecialchars($status_option['id']) ?>" <?= ($order['status_id'] == $status_option['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($status_option['status']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($user_role === 'admin'): ?>
                        <div class="mb-3">
                            <label for="delivery_user_id" class="form-label">Assign Delivery Person</label>
                            <select class="form-select" id="delivery_user_id" name="delivery_user_id">
                                <option value="">-- Select Delivery Person --</option>
                                <?php foreach ($delivery_users as $user): ?>
                                    <option value="<?= htmlspecialchars($user['id']) ?>" <?= ($order['delivery_user_id'] == $user['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Optional for certain statuses.</div>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="scheduled_date" class="form-label">Scheduled Delivery Date</label>
                        <input type="date" class="form-control" id="scheduled_date" name="scheduled_date" value="<?= htmlspecialchars($order['scheduled_date'] ?? '') ?>" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="scheduled_time" class="form-label">Scheduled Delivery Time</label>
                        <input type="time" class="form-control" id="scheduled_time" name="scheduled_time" value="<?= htmlspecialchars($order['scheduled_time'] ?? '') ?>" required>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> Update Status
                    </button>
                    <a href="orders.php?action=view" class="btn btn-secondary">
                        <i class="fas fa-times-circle"></i> Cancel
                    </a>
                </form>
            </div>
        </div>

    <?php elseif ($action === 'customer_counts' && $user_role === 'admin'): ?>
        <!-- Customer Order Counts View -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Customer Order Counts</h2>
            <a href="orders.php?action=view" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>
        </div>
        <?php if (isset($customer_counts) && count($customer_counts) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered data-table">
                    <thead class="table-dark">
                        <tr>
                            <th>Customer Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Total Orders</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customer_counts as $customer): ?>
                            <tr>
                                <td><?= htmlspecialchars($customer['customer_name']) ?></td>
                                <td><?= htmlspecialchars($customer['customer_email']) ?></td>
                                <td><?= htmlspecialchars($customer['customer_phone']) ?></td>
                                <td><?= htmlspecialchars($customer['order_count']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No orders recorded for customers.</p>
        <?php endif; ?>

    <?php elseif ($action === 'view_trash' && $user_role === 'admin'): ?>
        <!-- Trash Orders View -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Trash - Deleted Orders</h2>
            <a href="orders.php?action=view" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>
        </div>
        <?php if (isset($trash_orders) && count($trash_orders) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered data-table">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Customer Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Total (€)</th>
                            <th>Deleted At</th>
                            <th>Status</th>
                            <th>Delivery Person</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trash_orders as $order): ?>
                            <tr>
                                <td><?= htmlspecialchars($order['id']) ?></td>
                                <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                <td><?= htmlspecialchars($order['customer_email']) ?></td>
                                <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                                <td><?= htmlspecialchars($order['delivery_address']) ?></td>
                                <td><?= number_format($order['total_amount'], 2) ?>€</td>
                                <td><?= htmlspecialchars($order['deleted_at']) ?></td>
                                <td><?= htmlspecialchars($order['status']) ?></td>
                                <td><?= $order['delivery_username'] ? htmlspecialchars($order['delivery_username']) : 'N/A' ?></td>
                                <td>
                                    <!-- Restore Order Button -->
                                    <button type="button" class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#restoreModal<?= $order['id'] ?>" data-bs-toggle="tooltip" title="Restore">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                    <!-- Permanently Delete Order Button -->
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#permanentDeleteModal<?= $order['id'] ?>" data-bs-toggle="tooltip" title="Permanently Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>

                            <!-- Restore Order Modal -->
                            <div class="modal fade" id="restoreModal<?= $order['id'] ?>" tabindex="-1" aria-labelledby="restoreModalLabel<?= $order['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header bg-success text-white">
                                            <h5 class="modal-title" id="restoreModalLabel<?= $order['id'] ?>">Restore Order</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            Are you sure you want to restore this order?
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                                            <a href="orders.php?action=restore&id=<?= $order['id'] ?>" class="btn btn-success">Yes, Restore</a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Permanently Delete Order Modal -->
                            <div class="modal fade" id="permanentDeleteModal<?= $order['id'] ?>" tabindex="-1" aria-labelledby="permanentDeleteModalLabel<?= $order['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header bg-danger text-white">
                                            <h5 class="modal-title" id="permanentDeleteModalLabel<?= $order['id'] ?>">Permanently Delete Order</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            Are you sure you want to permanently delete this order? This action cannot be undone.
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                                            <a href="orders.php?action=permanent_delete&id=<?= $order['id'] ?>" class="btn btn-danger">Yes, Delete</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>Trash is empty.</p>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php
// Leaflet CSS for map if viewing order details
if ($action === 'view_details' && $id > 0): ?>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" integrity="sha512-Zcn6bjR/8RZbLEpLIeOwNtzREBAJnUKESxces60Mpoj+2okopSAcSUIUOseddDm0cxnGQzxIR7vG1NpYfqg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<?php endif; ?>

<!-- Initialize DataTables and Tooltips -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha384-..." crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        // Initialize DataTables
        $('.data-table').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/English.json" // Adjust language as needed
            },
            "paging": true,
            "searching": true,
            "ordering": true,
            "responsive": true
        });

        // Initialize Bootstrap tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>

<?php
// Function to get the status ID for 'Delivered' status (used by delivery users)
function getDeliveredStatusId($pdo)
{
    $stmt = $pdo->prepare("SELECT id FROM order_statuses WHERE status = 'Delivered' LIMIT 1");
    $stmt->execute();
    return $stmt->fetchColumn();
}

require_once 'includes/footer.php';
?>