<?php
ob_start();
require_once 'includes/db_connect.php';
require_once 'includes/header.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Define error log file
define('ERROR_LOG_FILE', __DIR__ . '/errors.md');

// Function to log errors
function log_error($message, $context = '')
{
    $timestamp = date('Y-m-d H:i:s');
    $safe_message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $formatted = "### [$timestamp] Error\n\n**Message:** $safe_message\n";
    if ($context) $formatted .= "**Context:** " . htmlspecialchars($context, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
    $formatted .= "---\n\n";
    file_put_contents(ERROR_LOG_FILE, $formatted, FILE_APPEND | LOCK_EX);
}

// Function to send emails using PHPMailer
function sendEmail($to, $toName, $subject, $body)
{
    $mail = new PHPMailer(true);
    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'egjini17@gmail.com';
        $mail->Password = 'axnjsldfudhohipv'; // Consider using environment variables for security
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Email settings
        $mail->setFrom('egjini17@gmail.com', 'Yumiis');
        $mail->addAddress($to, $toName);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
    } catch (Exception $e) {
        log_error("Mail Error: " . $mail->ErrorInfo, "Sending Email to: $to");
    }
}

// Function to send status update emails
function sendStatusUpdateEmail($email, $name, $order_id, $status, $scheduled_date = null, $scheduled_time = null)
{
    $subject = "Update Status of Your Order #{$order_id}";
    $scheduled_info = ($scheduled_date && $scheduled_time) ? "<p><strong>Scheduled Delivery:</strong> {$scheduled_date} at {$scheduled_time}</p>" : '';
    $body = <<<EOD
    <html>
    <head><style>
        body { font-family: Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 0; }
        .container { padding: 20px; background-color: #ffffff; margin: 20px auto; max-width: 600px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { background-color: #4CAF50; color: white; padding: 10px 0; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; }
        .footer { text-align: center; padding: 10px 0; color: #777777; font-size: 12px; }
        .button { background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
    </style></head>
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
            <div class='footer'>&copy; " . date('Y') . " Yumiis. Të gjitha të drejtat e mbrojtura.</div>
        </div>
    </body>
    </html>
    EOD;
    sendEmail($email, $name, $subject, $body);
}

// Function to send delay notification emails
function sendDelayNotificationEmail($email, $name, $order_id, $additional_time)
{
    $subject = "Njoftim për Vonese në Porosinë #{$order_id}";
    $body = <<<EOD
    <html>
    <head><style>
        body { font-family: Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 0; }
        .container { padding: 20px; background-color: #ffffff; margin: 20px auto; max-width: 600px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { background-color: #ff9800; color: white; padding: 10px 0; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; }
        .footer { text-align: center; padding: 10px 0; color: #777777; font-size: 12px; }
        .button { background-color: #ff9800; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
    </style></head>
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
            <div class='footer'>&copy; " . date('Y') . " Yumiis. Të gjitha të drejtat e mbrojtura.</div>
        </div>
    </body>
    </html>
    EOD;
    sendEmail($email, $name, $subject, $body);
}

// Function to notify the delivery person
function notifyDeliveryPerson($email, $name, $order_id, $status)
{
    $subject = "New Order Assigned - Order #{$order_id}";
    $body = <<<EOD
    <html>
    <head><style>
        body { font-family: Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 0; }
        .container { padding: 20px; background-color: #ffffff; margin: 20px auto; max-width: 600px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { background-color: #2196F3; color: white; padding: 10px 0; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; }
        .footer { text-align: center; padding: 10px 0; color: #777777; font-size: 12px; }
        .button { background-color: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
    </style></head>
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
            <div class='footer'>&copy; " . date('Y') . " Yumiis. Të gjitha të drejtat e mbrojtura.</div>
        </div>
    </body>
    </html>
    EOD;
    sendEmail($email, $name, $subject, $body);
}

// Exception and Error Handlers
set_exception_handler(function ($e) {
    log_error("Uncaught Exception: " . $e->getMessage(), "File: {$e->getFile()} Line: {$e->getLine()}");
    header("Location: orders.php?action=view&message=unknown_error");
    exit;
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Function to get delivery users
function getDeliveryUsers($pdo)
{
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE role = 'delivery' AND is_active = 1");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get order status history
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

// Function to geocode address
function geocodeAddress($address)
{
    $encoded = urlencode($address);
    $url = "https://nominatim.openstreetmap.org/search?format=json&limit=1&q={$encoded}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'YumiisAdmin/1.0');
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        log_error("cURL Error: " . curl_error($ch), "Geocoding Address: $address");
        curl_close($ch);
        return [null, null];
    }
    curl_close($ch);
    $data = json_decode($response, true);
    return isset($data[0]) ? [$data[0]['lat'], $data[0]['lon']] : [null, null];
}

// Function to update order coordinates
function updateOrderCoordinates($pdo, $order_id, $address)
{
    list($lat, $lon) = geocodeAddress($address);
    if ($lat && $lon) {
        $stmt = $pdo->prepare("UPDATE orders SET latitude = ?, longitude = ? WHERE id = ?");
        $stmt->execute([$lat, $lon, $order_id]);
    }
}

// Function to get all statuses
function getAllStatuses($pdo)
{
    return $pdo->query('SELECT * FROM order_statuses ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get active orders based on role
function getActiveOrders($pdo, $role, $user_id = null)
{
    $common_fields = 'o.*, os.status, t.name AS tip_name, t.percentage AS tip_percentage, t.amount AS tip_fixed_amount, c.order_count, u.username AS delivery_username';
    $common_query = '
        SELECT ' . $common_fields . ' 
        FROM orders o 
        JOIN order_statuses os ON o.status_id = os.id 
        LEFT JOIN tips t ON o.tip_id = t.id 
        JOIN (
            SELECT COALESCE(customer_email, "") AS customer_email, 
                   COALESCE(customer_phone, "") AS customer_phone, 
                   COUNT(*) AS order_count 
            FROM orders 
            WHERE deleted_at IS NULL 
            GROUP BY customer_email, customer_phone
        ) c ON (o.customer_email = c.customer_email OR o.customer_phone = c.customer_phone) 
        LEFT JOIN users u ON o.delivery_user_id = u.id 
        WHERE o.deleted_at IS NULL ';
    if ($role === 'admin') {
        $query = $common_query . ' ORDER BY os.id ASC, o.created_at DESC';
        $stmt = $pdo->prepare($query);
        $stmt->execute();
    } elseif ($role === 'delivery') {
        $query = $common_query . ' AND o.delivery_user_id = ? ORDER BY os.id ASC, o.created_at DESC';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
    } else {
        return [];
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Retrieve user role and ID from session
$user_role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Retrieve action and ID from GET parameters
$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

// Define allowed actions based on role
$allowed_actions = [
    'admin' => ['view', 'update_status', 'delete', 'permanent_delete', 'restore', 'view_trash', 'view_details', 'update_status_form', 'customer_counts', 'manage_ratings', 'delete_rating', 'send_delay_notification'],
    'delivery' => ['view', 'view_details', 'update_status', 'send_delay_notification']
];

// Check if the action is allowed for the user role
if (!in_array($action, $allowed_actions[$user_role] ?? [])) {
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
            date_default_timezone_set('Europe/Tirane');

            // Validate scheduled date and time
            if ($scheduled_date || $scheduled_time) {
                if ($scheduled_date && $scheduled_time) {
                    $scheduled_datetime = DateTime::createFromFormat('Y-m-d H:i', "$scheduled_date $scheduled_time");
                    $errors = DateTime::getLastErrors();
                    if ($errors['warning_count'] || $errors['error_count']) {
                        log_error("Invalid scheduled date and time format: $scheduled_date $scheduled_time", "Order ID: $id");
                        header("Location: orders.php?action=update_status_form&id=$id&message=" . urlencode('Invalid scheduled date and time format.'));
                        exit();
                    }
                    if ($scheduled_datetime < new DateTime()) {
                        log_error("Scheduled datetime is in the past: " . $scheduled_datetime->format('Y-m-d H:i'), "Order ID: $id");
                        header("Location: orders.php?action=update_status_form&id=$id&message=" . urlencode('Scheduled date and time cannot be in the past.'));
                        exit();
                    }
                } else {
                    header("Location: orders.php?action=update_status_form&id=$id&message=" . urlencode('Both scheduled date and time must be provided.'));
                    exit();
                }
            }

            if ($status_id && is_numeric($status_id)) {
                $stmt = $pdo->prepare('SELECT status FROM order_statuses WHERE id = ?');
                $stmt->execute([$status_id]);
                $new_status = $stmt->fetchColumn();
                if ($new_status) {
                    $stmt = $pdo->prepare('SELECT customer_email, customer_name, delivery_address, delivery_user_id, latitude, longitude FROM orders WHERE id = ? AND deleted_at IS NULL');
                    $stmt->execute([$id]);
                    $order = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($order) {
                        if ($user_role === 'delivery') {
                            $allowed_delivery_statuses = ['Delivered'];
                            if (!in_array($new_status, $allowed_delivery_statuses)) {
                                header("Location: orders.php?action=view&message=" . urlencode('You do not have permission to set this status.'));
                                exit();
                            }
                            if ($order['delivery_user_id'] != $user_id) {
                                header("Location: orders.php?action=view&message=" . urlencode('You are not assigned to this order.'));
                                exit();
                            }
                        }

                        // Update coordinates if not set
                        if (is_null($order['latitude']) || is_null($order['longitude'])) {
                            updateOrderCoordinates($pdo, $id, $order['delivery_address']);
                            $stmt->execute([$id]);
                            $order = $stmt->fetch(PDO::FETCH_ASSOC);
                        }

                        // Update order status
                        $update = $pdo->prepare('UPDATE orders SET status_id = ?, delivery_user_id = ?, scheduled_date = ?, scheduled_time = ? WHERE id = ?');
                        if ($update->execute([$status_id, $delivery_user_id ?: null, $scheduled_date ?: null, $scheduled_time ?: null, $id])) {
                            // Insert into history
                            $history = $pdo->prepare('INSERT INTO order_status_history (order_id, status_id, delivery_user_id) VALUES (?, ?, ?)');
                            $history->execute([$id, $status_id, $delivery_user_id ?: null]);

                            // Send status update email
                            sendStatusUpdateEmail($order['customer_email'], $order['customer_name'], $id, $new_status, $scheduled_date, $scheduled_time);

                            // Notify delivery person if assigned by admin
                            if ($delivery_user_id && $user_role === 'admin') {
                                $stmt = $pdo->prepare('SELECT email, username FROM users WHERE id = ?');
                                $stmt->execute([$delivery_user_id]);
                                $delivery_user = $stmt->fetch(PDO::FETCH_ASSOC);
                                if ($delivery_user) notifyDeliveryPerson($delivery_user['email'], $delivery_user['username'], $id, $new_status);
                            }

                            header("Location: orders.php?action=view&message=" . urlencode('Order status updated successfully.'));
                            exit();
                        }
                    }
                }
            }
            header("Location: orders.php?action=update_status_form&id=$id&message=" . urlencode('Failed to update status.'));
            exit();
        }
        break;

    case 'delete':
        if ($user_role === 'admin' && $id > 0) {
            $stmt = $pdo->prepare('UPDATE orders SET deleted_at = NOW() WHERE id = ?');
            $success = $stmt->execute([$id]);
            $msg = $success ? 'Order has been moved to Trash.' : 'Failed to move the order to Trash.';
            header("Location: orders.php?action=view&message=" . urlencode($msg));
            exit();
        }
        break;

    case 'permanent_delete':
        if ($user_role === 'admin' && $id > 0) {
            $stmt = $pdo->prepare('DELETE FROM orders WHERE id = ?');
            $success = $stmt->execute([$id]);
            $msg = $success ? 'Order has been permanently deleted.' : 'Failed to permanently delete the order.';
            header("Location: orders.php?action=view_trash&message=" . urlencode($msg));
            exit();
        }
        break;

    case 'restore':
        if ($user_role === 'admin' && $id > 0) {
            $stmt = $pdo->prepare('UPDATE orders SET deleted_at = NULL WHERE id = ?');
            $success = $stmt->execute([$id]);
            $msg = $success ? 'Order has been restored.' : 'Failed to restore the order.';
            header("Location: orders.php?action=view_trash&message=" . urlencode($msg));
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
                log_error("Failed to retrieve orders from Trash: " . $e->getMessage());
                $trash_orders = [];
                $message = '<div class="alert alert-danger">Failed to retrieve orders from Trash.</div>';
            }
        }
        break;

    case 'view_details':
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare('
                    SELECT o.*, os.status, t.name AS tip_name, t.percentage AS tip_percentage, t.amount AS tip_fixed_amount 
                    FROM orders o 
                    JOIN order_statuses os ON o.status_id = os.id 
                    LEFT JOIN tips t ON o.tip_id = t.id 
                    WHERE o.id = ? AND o.deleted_at IS NULL
                ');
                $stmt->execute([$id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($order) {
                    // Update coordinates if not set
                    if (is_null($order['latitude']) || is_null($order['longitude'])) {
                        updateOrderCoordinates($pdo, $id, $order['delivery_address']);
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
                        $stmt = $pdo->prepare('
                            SELECT e.name, oe.quantity, oe.unit_price, oe.total_price 
                            FROM order_extras oe 
                            JOIN extras e ON oe.extra_id = e.id 
                            WHERE oe.order_item_id = ?
                        ');
                        $stmt->execute([$item['id']]);
                        $item['extras'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        // Fetch drinks
                        $stmt = $pdo->prepare('
                            SELECT d.name, od.quantity, od.unit_price, od.total_price 
                            FROM order_drinks od 
                            JOIN drinks d ON od.drink_id = d.id 
                            WHERE od.order_item_id = ?
                        ');
                        $stmt->execute([$item['id']]);
                        $item['drinks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }

                    // Fetch status history
                    $status_history = getOrderStatusHistory($pdo, $id);
                } else {
                    $message = '<div class="alert alert-warning">The order does not exist or is in Trash.</div>';
                    require_once 'includes/footer.php';
                    exit();
                }
            } catch (PDOException $e) {
                log_error("Failed to retrieve order details: " . $e->getMessage());
                $message = '<div class="alert alert-danger">Failed to retrieve order details.</div>';
                require_once 'includes/footer.php';
                exit();
            }
        }
        break;

    case 'update_status_form':
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare('
                    SELECT o.*, os.status, t.name AS tip_name, t.percentage AS tip_percentage, t.amount AS tip_fixed_amount 
                    FROM orders o 
                    JOIN order_statuses os ON o.status_id = os.id 
                    LEFT JOIN tips t ON o.tip_id = t.id 
                    WHERE o.id = ? AND o.deleted_at IS NULL
                ');
                $stmt->execute([$id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($order) {
                    $statuses = getAllStatuses($pdo);
                    if ($user_role === 'admin') $delivery_users = getDeliveryUsers($pdo);
                    $message = $_GET['message'] ?? '';
                } else {
                    $message = '<div class="alert alert-warning">The order does not exist or is in Trash.</div>';
                    require_once 'includes/footer.php';
                    exit();
                }
            } catch (PDOException $e) {
                log_error("Failed to retrieve data for update_status_form: " . $e->getMessage());
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
            log_error("Failed to retrieve customer order counts: " . $e->getMessage());
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
                log_error("Failed to retrieve ratings: " . $e->getMessage());
                $ratings = [];
                $message = '<div class="alert alert-danger">Failed to retrieve ratings.</div>';
            }
        }
        break;

    case 'delete_rating':
        if ($user_role === 'admin' && $id > 0) {
            try {
                $stmt = $pdo->prepare('DELETE FROM ratings WHERE id = ?');
                $success = $stmt->execute([$id]);
                $msg = $success ? 'Rating has been successfully deleted.' : 'Failed to delete the rating.';
                header("Location: orders.php?action=manage_ratings&message=" . urlencode($msg));
                exit();
            } catch (PDOException $e) {
                log_error("Failed to delete rating ID $id: " . $e->getMessage());
                $msg = 'Failed to delete the rating.';
                header("Location: orders.php?action=manage_ratings&message=" . urlencode($msg));
                exit();
            }
        }
        break;

    case 'send_delay_notification':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
            $additional_time = (int)($_POST['additional_time'] ?? 0);
            if ($additional_time > 0) {
                try {
                    $stmt = $pdo->prepare('SELECT customer_email, customer_name FROM orders WHERE id = ? AND deleted_at IS NULL');
                    $stmt->execute([$id]);
                    $order = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($order) {
                        sendDelayNotificationEmail($order['customer_email'], $order['customer_name'], $id, $additional_time);
                        header("Location: orders.php?action=view&message=" . urlencode('Delay notification has been sent successfully.'));
                        exit();
                    } else {
                        header("Location: orders.php?action=view&message=" . urlencode('The order does not exist or is in Trash.'));
                        exit();
                    }
                } catch (PDOException $e) {
                    log_error("Failed to send delay notification for order ID $id: " . $e->getMessage());
                    header("Location: orders.php?action=view&message=" . urlencode('Failed to send delay notification.'));
                    exit();
                }
            } else {
                header("Location: orders.php?action=view&message=" . urlencode('Please enter a valid additional time.'));
                exit();
            }
        }
        break;

    case 'check_new_orders':
        // Ensure the user is authenticated
        if (!isset($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit();
        }
        // Get the last known order ID from the client
        $last_order_id = isset($_GET['last_order_id']) ? (int)$_GET['last_order_id'] : 0;
        try {
            // Fetch the latest order ID in the database
            $stmt = $pdo->prepare('SELECT MAX(id) AS max_id FROM orders WHERE deleted_at IS NULL');
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $current_max_id = $result['max_id'] ?? 0;
            if ($current_max_id > $last_order_id) {
                // New orders have arrived
                echo json_encode([
                    'status' => 'success',
                    'new_orders' => $current_max_id - $last_order_id,
                    'latest_order_id' => $current_max_id
                ]);
            } else {
                // No new orders
                echo json_encode(['status' => 'no_new_orders']);
            }
        } catch (PDOException $e) {
            log_error("Failed to check for new orders: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Server error']);
        }
        exit();

    case 'view':
    default:
        try {
            $orders = getActiveOrders($pdo, $user_role, $user_id);
            $statuses = getAllStatuses($pdo);
            $grouped_orders = [];
            foreach ($statuses as $status) $grouped_orders[$status['status']] = [];
            foreach ($orders as $order) $grouped_orders[$order['status']][] = $order;
        } catch (PDOException $e) {
            log_error("Failed to retrieve orders: " . $e->getMessage());
            $grouped_orders = [];
            $message = '<div class="alert alert-danger">Failed to retrieve orders.</div>';
        }
        break;
}

// Function to get Delivered Status ID
function getDeliveredStatusId($pdo)
{
    $stmt = $pdo->prepare("SELECT id FROM order_statuses WHERE status = 'Delivered' LIMIT 1");
    $stmt->execute();
    return $stmt->fetchColumn();
}
?>
<!-- Custom CSS for Compactness -->
<style>
    .table th,
    .table td {
        padding: 0.3rem;
        font-size: 0.9rem;
    }

    .card-header,
    .modal-header {
        padding: 0.5rem;
    }

    .card-body,
    .modal-body,
    .modal-footer {
        padding: 0.5rem;
    }

    .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }

    .badge {
        font-size: 0.75rem;
    }
</style>

<div class="container-fluid mt-4 px-3">
    <?php if (isset($_GET['message'])): ?>
        <div class="alert alert-<?= (strpos($_GET['message'], 'successfully') !== false || strpos($_GET['message'], 'sukses') !== false || strpos($_GET['message'], 'suksesshëm') !== false) ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_GET['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif (isset($message)): ?>
        <?= $message ?>
    <?php endif; ?>

    <?php if ($action === 'view'): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4">Orders</h2>
            <?php if ($user_role === 'admin'): ?>
                <div class="btn-group" role="group" aria-label="Admin Actions">
                    <a href="orders.php?action=customer_counts" class="btn btn-sm btn-primary"><i class="fas fa-chart-bar"></i> Customer Order Counts</a>
                    <a href="orders.php?action=manage_ratings" class="btn btn-sm btn-warning"><i class="fas fa-star"></i> Manage Ratings</a>
                    <a href="orders.php?action=view_trash" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Trash</a>
                </div>
            <?php endif; ?>
        </div>
        <?php foreach ($grouped_orders as $status => $orders): ?>
            <div class="card mb-3 shadow-sm">
                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center p-2">
                    <span><i class="fas fa-tag"></i> <?= htmlspecialchars($status) ?> Orders</span>
                    <span class="badge bg-primary"><?= count($orders) ?></span>
                </div>
                <div class="card-body p-2">
                    <?php if (count($orders) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-bordered table-sm mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Address</th>
                                        <th>Total (€)</th>
                                        <th>Tip</th>
                                        <th>Tip (€)</th>
                                        <th>Scheduled</th>
                                        <th>Created At</th>
                                        <th>Order Count</th>
                                        <th>Delivery</th>
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
                                            <td><?= $order['tip_name'] ? "<span class='badge bg-info' data-bs-toggle='tooltip' title='Selected Tip: " . ($order['tip_percentage'] ? htmlspecialchars($order['tip_percentage']) . '%' : htmlspecialchars(number_format($order['tip_fixed_amount'], 2)) . '€') . "'>" . htmlspecialchars($order['tip_name']) . "</span>" : 'N/A' ?></td>
                                            <td><?= $order['tip_amount'] > 0 ? "<span class='badge bg-warning'>" . number_format($order['tip_amount'], 2) . "€</span>" : '0.00€' ?></td>
                                            <td><?= htmlspecialchars($order['scheduled_date'] ?? 'N/A') ?> <?= htmlspecialchars($order['scheduled_time'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($order['created_at']) ?></td>
                                            <td><?= htmlspecialchars($order['order_count']) ?><?= $order['order_count'] > 1 ? "<span class='badge bg-primary ms-1'>Repeat</span>" : '' ?></td>
                                            <td><?= $order['delivery_username'] ? htmlspecialchars($order['delivery_username']) : 'N/A' ?></td>
                                            <td class="text-nowrap">
                                                <a href="orders.php?action=view_details&id=<?= $order['id'] ?>" class="btn btn-sm btn-info me-1" data-bs-toggle="tooltip" title="View"><i class="fas fa-eye"></i></a>
                                                <?php if ($user_role === 'admin' || ($user_role === 'delivery' && in_array($order['status'], ['Assigned', 'Processing']))): ?>
                                                    <a href="orders.php?action=update_status_form&id=<?= $order['id'] ?>" class="btn btn-sm btn-warning me-1" data-bs-toggle="tooltip" title="Update Status"><i class="fas fa-edit"></i></a>
                                                <?php endif; ?>
                                                <?php if ($user_role === 'admin'): ?>
                                                    <button type="button" class="btn btn-sm btn-danger me-1" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $order['id'] ?>" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#delayNotificationModal<?= $order['id'] ?>" title="Delay Notification"><i class="fas fa-clock"></i></button>
                                                <?php elseif ($user_role === 'delivery' && $order['status'] !== 'Delivered'): ?>
                                                    <a href="orders.php?action=update_status&id=<?= $order['id'] ?>&status_id=<?= getDeliveredStatusId($pdo) ?>" class="btn btn-sm btn-success me-1" data-bs-toggle="tooltip" title="Mark as Delivered"><i class="fas fa-check-circle"></i></a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                        <?php if ($user_role === 'admin'): ?>
                                            <!-- Delete Modal -->
                                            <div class="modal fade" id="deleteModal<?= $order['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $order['id'] ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-danger text-white p-2">
                                                            <h5 class="modal-title" id="deleteModalLabel<?= $order['id'] ?>">Delete Order</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body p-2">Are you sure you want to delete this order?</div>
                                                        <div class="modal-footer p-2">
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">No</button>
                                                            <a href="orders.php?action=delete&id=<?= $order['id'] ?>" class="btn btn-danger btn-sm">Yes, Delete</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Delay Notification Modal -->
                                            <div class="modal fade" id="delayNotificationModal<?= $order['id'] ?>" tabindex="-1" aria-labelledby="delayNotificationModalLabel<?= $order['id'] ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-warning text-dark p-2">
                                                            <h5 class="modal-title" id="delayNotificationModalLabel<?= $order['id'] ?>">Send Delay Notification - Order #<?= $order['id'] ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <form method="POST" action="orders.php?action=send_delay_notification&id=<?= $order['id'] ?>">
                                                            <div class="modal-body p-2">
                                                                <div class="mb-3">
                                                                    <label for="additional_time_<?= $order['id'] ?>" class="form-label">Additional Time (in hours)</label>
                                                                    <input type="number" class="form-control form-control-sm" id="additional_time_<?= $order['id'] ?>" name="additional_time" min="1" required>
                                                                </div>
                                                                <p>Are you sure you want to send a delay notification for this order?</p>
                                                            </div>
                                                            <div class="modal-footer p-2">
                                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">No</button>
                                                                <button type="submit" class="btn btn-warning btn-sm">Yes, Send</button>
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
                        <p class="mb-0">No orders in this category.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

    <?php elseif ($action === 'manage_ratings' && $user_role === 'admin'): ?>
        <!-- Manage Ratings Section -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4">Manage Customer Ratings</h2>
            <a href="orders.php?action=view" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Back to Orders</a>
        </div>
        <?php if (!empty($ratings)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered table-sm mb-0">
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
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteRatingModal<?= $rating['id'] ?>" title="Delete"><i class="fas fa-trash-alt"></i></button>

                                    <!-- Delete Rating Modal -->
                                    <div class="modal fade" id="deleteRatingModal<?= $rating['id'] ?>" tabindex="-1" aria-labelledby="deleteRatingModalLabel<?= $rating['id'] ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white p-2">
                                                    <h5 class="modal-title" id="deleteRatingModalLabel<?= $rating['id'] ?>">Delete Rating</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body p-2">Are you sure you want to delete this rating?</div>
                                                <div class="modal-footer p-2">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">No</button>
                                                    <a href="orders.php?action=delete_rating&id=<?= $rating['id'] ?>" class="btn btn-danger btn-sm">Yes, Delete</a>
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
            <p class="mb-0">No ratings submitted yet.</p>
        <?php endif; ?>

    <?php elseif ($action === 'view_details' && $id > 0): ?>
        <!-- Order Details Section -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4">Order Details - ID: <?= htmlspecialchars($order['id']) ?></h2>
            <a href="orders.php?action=view" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Back to Orders</a>
        </div>

        <!-- Order Information Card -->
        <div class="card mb-3 shadow-sm">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center p-2">
                <span><i class="fas fa-info-circle"></i> Order Information</span>
                <span class="badge bg-primary"><?= htmlspecialchars($order['status']) ?></span>
            </div>
            <div class="card-body p-2">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <p><strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($order['customer_email']) ?></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone']) ?></p>
                        <p><strong>Address:</strong> <?= nl2br(htmlspecialchars($order['delivery_address'])) ?></p>
                    </div>
                    <div class="col-md-6 mb-2">
                        <p><strong>Total Amount:</strong> <?= number_format($order['total_amount'], 2) ?>€</p>
                        <p><strong>Status:</strong> <?= htmlspecialchars($order['status']) ?></p>
                        <p><strong>Tip:</strong> <?= $order['tip_name'] ? "<span class='badge bg-info' data-bs-toggle='tooltip' title='Selected Tip: " . ($order['tip_percentage'] ? htmlspecialchars($order['tip_percentage']) . '%' : htmlspecialchars(number_format($order['tip_fixed_amount'], 2)) . '€') . "'>" . htmlspecialchars($order['tip_name']) . "</span>" : 'N/A' ?></p>
                        <p><strong>Tip Amount:</strong> <?= $order['tip_amount'] > 0 ? "<span class='badge bg-warning'>" . number_format($order['tip_amount'], 2) . "€</span>" : '0.00€' ?></p>
                        <p><strong>Created At:</strong> <?= htmlspecialchars($order['created_at']) ?></p>
                        <?php if ($order['delivery_user_id']): ?>
                            <?php
                            $stmt = $pdo->prepare('SELECT username, email FROM users WHERE id = ?');
                            $stmt->execute([$order['delivery_user_id']]);
                            $delivery_user = $stmt->fetch(PDO::FETCH_ASSOC);
                            ?>
                            <p><strong>Delivery Person:</strong> <?= htmlspecialchars($delivery_user['username']) ?> (<?= htmlspecialchars($delivery_user['email']) ?>)</p>
                        <?php endif; ?>
                        <?php if ($order['scheduled_date'] || $order['scheduled_time']): ?>
                            <p><strong>Scheduled Delivery:</strong> <?= htmlspecialchars($order['scheduled_date'] ?? 'N/A') ?> <?= htmlspecialchars($order['scheduled_time'] ?? '') ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Items Card -->
        <div class="card mb-3 shadow-sm">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center p-2">
                <span><i class="fas fa-boxes"></i> Order Items</span>
                <span class="badge bg-primary"><?= count($items) ?> Item<?= count($items) > 1 ? 's' : '' ?></span>
            </div>
            <div class="card-body p-2">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered table-sm mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Product</th>
                                <th>Size</th>
                                <th>Qty</th>
                                <th>Price (€)</th>
                                <th>Extras</th>
                                <th>Drinks</th>
                                <th>Instructions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                                    <td><?= htmlspecialchars($item['size_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($item['quantity']) ?></td>
                                    <td><?= number_format($item['price'], 2) ?></td>
                                    <td><?= !empty($item['extras']) ? implode('<br>', array_map(function ($e) {
                                            return "<span class='badge bg-primary'>" . htmlspecialchars($e['name']) . " x" . htmlspecialchars($e['quantity']) . " (+" . number_format($e['unit_price'], 2) . "€)</span>";
                                        }, $item['extras'])) : '-' ?></td>
                                    <td><?= !empty($item['drinks']) ? implode('<br>', array_map(function ($d) {
                                            return "<span class='badge bg-info'>" . htmlspecialchars($d['name']) . " x" . htmlspecialchars($d['quantity']) . " (+" . number_format($d['unit_price'], 2) . "€)</span>";
                                        }, $item['drinks'])) : '-' ?></td>
                                    <td><?= htmlspecialchars($item['special_instructions']) ?: '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-2">
                    <h5 class="h6">Selected Tip</h5>
                    <p class="mb-0"><strong>Tip:</strong> <?= $order['tip_name'] ? "<span class='badge bg-info' data-bs-toggle='tooltip' title='Selected Tip: " . ($order['tip_percentage'] ? htmlspecialchars($order['tip_percentage']) . '%' : htmlspecialchars(number_format($order['tip_fixed_amount'], 2)) . '€') . "'>" . htmlspecialchars($order['tip_name']) . "</span>" : 'N/A' ?><br>
                        <strong>Tip Amount:</strong> <?= $order['tip_amount'] > 0 ? "<span class='badge bg-warning'>" . number_format($order['tip_amount'], 2) . "€</span>" : '0.00€' ?>
                    </p>
                </div>
            </div>
        </div>

        <?php if (!empty($order['latitude']) && !empty($order['longitude'])): ?>
            <!-- Delivery Address Map Card -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center p-2">
                    <span><i class="fas fa-map-marker-alt"></i> Delivery Address</span>
                </div>
                <div class="card-body p-2">
                    <div id="map" style="height: 300px; width: 100%;"></div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning p-2 mb-3">The delivery address is not set or could not be geocoded.</div>
        <?php endif; ?>

        <!-- Order Status History Card -->
        <div class="card mb-3 shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center p-2">
                <span><i class="fas fa-history"></i> Order Status History</span>
                <span class="badge bg-primary"><?= count($status_history) ?> Changes</span>
            </div>
            <div class="card-body p-2">
                <?php if (!empty($status_history)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-bordered table-sm mb-0">
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
                                        <td><?= $history['delivery_username'] ? htmlspecialchars($history['delivery_username']) . ' (' . htmlspecialchars($history['delivery_email']) . ')' : 'N/A' ?></td>
                                        <td><?= htmlspecialchars($history['changed_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="mb-0">No status history recorded.</p>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($action === 'update_status_form' && $id > 0): ?>
        <!-- Update Status Form -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4">Update Order Status - ID: <?= htmlspecialchars($order['id']) ?></h2>
            <a href="orders.php?action=view" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Back to Orders</a>
        </div>
        <?php if (!empty($message)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <div class="card shadow-sm">
            <div class="card-body p-2">
                <form method="POST" action="orders.php?action=update_status&id=<?= $id ?>" class="row g-3">
                    <div class="col-md-6">
                        <label for="status_id" class="form-label">Select New Status</label>
                        <select class="form-select form-select-sm" id="status_id" name="status_id" required>
                            <?php foreach ($statuses as $status_option): ?>
                                <?php if ($user_role === 'delivery' && !in_array($status_option['status'], ['Delivered'])) continue; ?>
                                <option value="<?= htmlspecialchars($status_option['id']) ?>" <?= ($order['status_id'] == $status_option['id']) ? 'selected' : '' ?>><?= htmlspecialchars($status_option['status']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($user_role === 'admin'): ?>
                        <div class="col-md-6">
                            <label for="delivery_user_id" class="form-label">Assign Delivery Person</label>
                            <select class="form-select form-select-sm" id="delivery_user_id" name="delivery_user_id">
                                <option value="">-- Select Delivery Person --</option>
                                <?php foreach ($delivery_users as $user): ?>
                                    <option value="<?= htmlspecialchars($user['id']) ?>" <?= ($order['delivery_user_id'] == $user['id']) ? 'selected' : '' ?>><?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['email']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Optional for certain statuses.</div>
                        </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="scheduleDeliveryCheck">
                            <label class="form-check-label" for="scheduleDeliveryCheck">Schedule Delivery</label>
                        </div>
                    </div>
                    <div class="col-md-6" id="scheduledDateContainer" style="display: none;">
                        <label for="scheduled_date" class="form-label">Scheduled Delivery Date</label>
                        <input type="date" class="form-control form-control-sm" id="scheduled_date" name="scheduled_date" value="<?= htmlspecialchars($order['scheduled_date'] ?? '') ?>" min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6" id="scheduledTimeContainer" style="display: none;">
                        <label for="scheduled_time" class="form-label">Scheduled Delivery Time</label>
                        <input type="time" class="form-control form-control-sm" id="scheduled_time" name="scheduled_time" value="<?= htmlspecialchars($order['scheduled_time'] ?? '') ?>">
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-sm btn-success me-2"><i class="fas fa-check-circle"></i> Update Status</button>
                        <a href="orders.php?action=view" class="btn btn-sm btn-secondary"><i class="fas fa-times-circle"></i> Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <script>
            document.getElementById('scheduleDeliveryCheck').addEventListener('change', function() {
                var dateContainer = document.getElementById('scheduledDateContainer');
                var timeContainer = document.getElementById('scheduledTimeContainer');
                if (this.checked) {
                    dateContainer.style.display = 'block';
                    timeContainer.style.display = 'block';
                } else {
                    dateContainer.style.display = 'none';
                    timeContainer.style.display = 'none';
                    document.getElementById('scheduled_date').value = '';
                    document.getElementById('scheduled_time').value = '';
                }
            });
        </script>

    <?php elseif ($action === 'customer_counts' && $user_role === 'admin'): ?>
        <!-- Customer Order Counts Section -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4">Customer Order Counts</h2>
            <a href="orders.php?action=view" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Back to Orders</a>
        </div>
        <?php if (!empty($customer_counts)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered table-sm mb-0">
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
            <p class="mb-0">No orders recorded for customers.</p>
        <?php endif; ?>

    <?php elseif ($action === 'view_trash' && $user_role === 'admin'): ?>
        <!-- Trash - Deleted Orders Section -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4">Trash - Deleted Orders</h2>
            <a href="orders.php?action=view" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Back to Orders</a>
        </div>
        <?php if (!empty($trash_orders)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered table-sm mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Total (€)</th>
                            <th>Deleted At</th>
                            <th>Status</th>
                            <th>Delivery</th>
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
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#restoreModal<?= $order['id'] ?>" title="Restore"><i class="fas fa-undo"></i></button>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#permanentDeleteModal<?= $order['id'] ?>" title="Permanently Delete"><i class="fas fa-trash-alt"></i></button>
                                </td>
                            </tr>

                            <!-- Restore Modal -->
                            <div class="modal fade" id="restoreModal<?= $order['id'] ?>" tabindex="-1" aria-labelledby="restoreModalLabel<?= $order['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header bg-success text-white p-2">
                                            <h5 class="modal-title" id="restoreModalLabel<?= $order['id'] ?>">Restore Order</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-2">Are you sure you want to restore this order?</div>
                                        <div class="modal-footer p-2">
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">No</button>
                                            <a href="orders.php?action=restore&id=<?= $order['id'] ?>" class="btn btn-success btn-sm">Yes, Restore</a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Permanent Delete Modal -->
                            <div class="modal fade" id="permanentDeleteModal<?= $order['id'] ?>" tabindex="-1" aria-labelledby="permanentDeleteModalLabel<?= $order['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header bg-danger text-white p-2">
                                            <h5 class="modal-title" id="permanentDeleteModalLabel<?= $order['id'] ?>">Permanently Delete Order</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-2">Are you sure you want to permanently delete this order? This action cannot be undone.</div>
                                        <div class="modal-footer p-2">
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">No</button>
                                            <a href="orders.php?action=permanent_delete&id=<?= $order['id'] ?>" class="btn btn-danger btn-sm">Yes, Delete</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="mb-0">Trash is empty.</p>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php if ($action === 'view_details' && $id > 0 && !empty($order['latitude']) && !empty($order['longitude'])): ?>
    <!-- Leaflet Map Initialization -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var map = L.map('map').setView([<?= htmlspecialchars($order['latitude']) ?>, <?= htmlspecialchars($order['longitude']) ?>], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap'
            }).addTo(map);
            L.marker([<?= htmlspecialchars($order['latitude']) ?>, <?= htmlspecialchars($order['longitude']) ?>])
                .addTo(map)
                .bindPopup("<b>Delivery Address</b><br><?= htmlspecialchars(addslashes($order['delivery_address'])) ?>")
                .openPopup();
        });
    </script>
<?php endif; ?>

<!-- Include Leaflet CSS and JS if not already included in header.php -->
<!-- If already included in header.php, you can remove these lines -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" integrity="sha512-Zcn6bjR/8RZbLEpLIeOwNtzREBAJnUKESxces60Mpoj+2okopSAcSUIUOseddDm0cxnGQzxIR7vG1NpYfqg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js" integrity="sha512-BwHfrr4c9kmRkLw6iXFdzcdWV/PGkVgiIyIWLLlTSXzWQzxuSg4DiQUCpauz/EWjgk5TYQqX/kvn9pG1NpYfqg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<!-- DataTables and Bootstrap JS -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Initialize DataTables and Tooltips -->
<script>
    $(document).ready(function() {
        $('.table').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/English.json"
            },
            "paging": true,
            "searching": true,
            "ordering": true,
            "responsive": true,
            "autoWidth": false,
            "columnDefs": [{
                    "orderable": false,
                    "targets": -1
                } // Disable ordering on the last column (Actions)
            ]
        });
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>

<?php
require_once 'includes/footer.php';
?>