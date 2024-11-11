<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" integrity="sha512-Zcn6bjR/8RZbLEpLIeOwNtzREBAJnUKESxces60Mpoj+2okopSAcSUIUOseddDm0cxnGQzxIR7vJgsLZbdLE3w==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js" integrity="sha512-BwHfrr4c9kmRkLw6iXFdzcdWV/PGkVgiIyIWLLlTSXzWQzxuSg4DiQUCpauz/EWjgk5TYQqX/kvn9pG1NpYfqg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

// Define error log file
define('ERROR_LOG_FILE', __DIR__ . '/errors.md');

// Function to send status update emails to customers
function sendStatusUpdateEmail($email, $name, $order_id, $status)
{
    $mail = new PHPMailer(true);
    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.example.com'; // Replace with your SMTP host
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your_email@example.com'; // Replace with your SMTP username
        $mail->Password   = 'your_password'; // Replace with your SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587; // Adjust port if necessary

        // Sender and recipient settings
        $mail->setFrom('no-reply@example.com', 'Your Company'); // Replace with your sender email and name
        $mail->addAddress($email, $name);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = "Update Status of Your Order #{$order_id}";
        $mail->Body    = "<h3>Dear {$name},</h3>
                          <p>Your order <strong>#{$order_id}</strong> status has been updated to <strong>{$status}</strong>.</p>
                          <p>Thank you for choosing us!</p>";

        $mail->send();
    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
    }
}

// Function to notify delivery person via email
function notifyDeliveryPerson($email, $name, $order_id, $status)
{
    $mail = new PHPMailer(true);
    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.example.com'; // Replace with your SMTP host
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your_email@example.com'; // Replace with your SMTP username
        $mail->Password   = 'your_password'; // Replace with your SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587; // Adjust port if necessary

        // Sender and recipient settings
        $mail->setFrom('no-reply@example.com', 'Your Company');
        $mail->addAddress($email, $name);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = "New Order Assigned - Order #{$order_id}";
        $mail->Body    = "<h3>Dear {$name},</h3>
                          <p>You have been assigned to handle order <strong>#{$order_id}</strong> with status <strong>{$status}</strong>.</p>
                          <p>Please proceed accordingly.</p>";

        $mail->send();
    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
    }
}

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
    header("Location: orders.php?action=view&message=unknown_error");
    exit;
});

// Custom error handler to convert errors to exceptions
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Function to fetch delivery users
function getDeliveryUsers($pdo)
{
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE role = 'delivery' AND is_active = 1");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to fetch order status history
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

// Function to geocode an address using Nominatim
function geocodeAddress($address)
{
    $encoded_address = urlencode($address);
    $url = "https://nominatim.openstreetmap.org/search?format=json&limit=1&q={$encoded_address}";

    // Initialize cURL
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'YourAppName/1.0'); // Nominatim requires a valid User-Agent

    // Execute cURL request
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

// Function to update order's latitude and longitude
function updateOrderCoordinates($pdo, $order_id, $address)
{
    list($latitude, $longitude) = geocodeAddress($address);

    if ($latitude && $longitude) {
        $stmt = $pdo->prepare("UPDATE orders SET latitude = ?, longitude = ? WHERE id = ?");
        $stmt->execute([$latitude, $longitude, $order_id]);
    }
}

// Initialize variables
$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

// Handle different actions using switch statement
switch ($action) {
    case 'update_status':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
            $status_id = $_POST['status_id'] ?? 0;
            $delivery_user_id = $_POST['delivery_user_id'] ?? null; // New field for delivery person

            if ($status_id && is_numeric($status_id)) {
                // Fetch new status
                $stmt = $pdo->prepare('SELECT status FROM order_statuses WHERE id = ?');
                $stmt->execute([$status_id]);
                $new_status = $stmt->fetchColumn();

                if ($new_status) {
                    // Fetch customer details
                    $stmt = $pdo->prepare('SELECT customer_email, customer_name, delivery_address FROM orders WHERE id = ? AND deleted_at IS NULL');
                    $stmt->execute([$id]);
                    $order = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($order) {
                        // Geocode address if not already done
                        if (is_null($order['latitude']) || is_null($order['longitude'])) {
                            updateOrderCoordinates($pdo, $id, $order['delivery_address']);

                            // Re-fetch the updated order details
                            $stmt->execute([$id]);
                            $order = $stmt->fetch(PDO::FETCH_ASSOC);
                        }

                        // Update order status and assign delivery person
                        $update = $pdo->prepare('UPDATE orders SET status_id = ?, delivery_user_id = ? WHERE id = ?');
                        if ($update->execute([$status_id, $delivery_user_id ?: null, $id])) {
                            // Insert into order_status_history
                            $history = $pdo->prepare('INSERT INTO order_status_history (order_id, status_id, delivery_user_id) VALUES (?, ?, ?)');
                            $history->execute([$id, $status_id, $delivery_user_id ?: null]);

                            // Send email notification to customer
                            sendStatusUpdateEmail($order['customer_email'], $order['customer_name'], $id, $new_status);

                            // Notify delivery person if assigned
                            if ($delivery_user_id) {
                                // Fetch delivery user details
                                $stmt = $pdo->prepare('SELECT email, username FROM users WHERE id = ?');
                                $stmt->execute([$delivery_user_id]);
                                $delivery_user = $stmt->fetch(PDO::FETCH_ASSOC);
                                if ($delivery_user) {
                                    notifyDeliveryPerson($delivery_user['email'], $delivery_user['username'], $id, $new_status);
                                }
                            }

                            $msg = 'Order status updated successfully.';
                            header('Location: orders.php?action=view&message=' . urlencode($msg));
                            exit();
                        }
                    }
                }
            }
            $msg = 'Failed to update status.';
            header('Location: orders.php?action=update_status_form&id=' . $id . '&message=' . urlencode($msg));
            exit();
        }
        break;

    case 'delete':
        if ($id > 0) {
            // Move the order to Trash by updating the deleted_at field
            $delete = $pdo->prepare('UPDATE orders SET deleted_at = NOW() WHERE id = ?');
            $success = $delete->execute([$id]);
            $msg = $success ? 'Porosia është zhvendosur në Trash.' : 'Deshtoi zhvendosja e porosisë në Trash.';
            header('Location: orders.php?action=view&message=' . urlencode($msg));
            exit();
        }
        break;

    case 'permanent_delete':
        if ($id > 0) {
            // Permanently delete the order from the database
            $perm_delete = $pdo->prepare('DELETE FROM orders WHERE id = ?');
            $success = $perm_delete->execute([$id]);
            $msg = $success ? 'Porosia është fshirë përfundimisht.' : 'Deshtoi fshirja përfundimtare e porosisë.';
            header('Location: orders.php?action=view_trash&message=' . urlencode($msg));
            exit();
        }
        break;

    case 'restore':
        if ($id > 0) {
            // Restore the order by setting deleted_at to NULL
            $restore = $pdo->prepare('UPDATE orders SET deleted_at = NULL WHERE id = ?');
            $success = $restore->execute([$id]);
            $msg = $success ? 'Porosia është rikthyer.' : 'Deshtoi rikthimi i porosisë.';
            header('Location: orders.php?action=view_trash&message=' . urlencode($msg));
            exit();
        }
        break;

    case 'view_trash':
        try {
            // Fetch all deleted orders
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
            $message = '<div class="alert alert-danger">Deshtoi marrja e porosive në Trash.</div>';
        }
        break;

    case 'view_details':
        if ($id > 0) {
            try {
                // Fetch order details only if not in Trash
                $stmt = $pdo->prepare('SELECT o.*, os.status FROM orders o JOIN order_statuses os ON o.status_id = os.id WHERE o.id = ? AND o.deleted_at IS NULL');
                $stmt->execute([$id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($order) {
                    // Check if latitude or longitude is missing
                    if (is_null($order['latitude']) || is_null($order['longitude'])) {
                        // Attempt to geocode the address
                        updateOrderCoordinates($pdo, $id, $order['delivery_address']);

                        // Re-fetch the updated order details
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
                    $message = '<div class="alert alert-warning">Porosia nuk ekziston ose është në Trash.</div>';
                    require_once 'includes/footer.php';
                    exit();
                }
            } catch (PDOException $e) {
                $message = '<div class="alert alert-danger">Deshtoi marrja e detajeve të porosisë.</div>';
                require_once 'includes/footer.php';
                exit();
            }
        }
        break;

    case 'update_status_form':
        if ($id > 0) {
            try {
                // Fetch order details
                $stmt = $pdo->prepare('SELECT o.*, os.status FROM orders o JOIN order_statuses os ON o.status_id = os.id WHERE o.id = ? AND o.deleted_at IS NULL');
                $stmt->execute([$id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$order) {
                    $message = '<div class="alert alert-warning">Porosia nuk ekziston ose është në Trash.</div>';
                    require_once 'includes/footer.php';
                    exit();
                }

                // Fetch all statuses
                $statuses = $pdo->query('SELECT * FROM order_statuses ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

                // Fetch delivery users
                $delivery_users = getDeliveryUsers($pdo);

                // Fetch any messages
                $message = $_GET['message'] ?? '';
            } catch (PDOException $e) {
                $message = '<div class="alert alert-danger">Deshtoi marrja e të dhënave.</div>';
                require_once 'includes/footer.php';
                exit();
            }
        }
        break;

    case 'customer_counts':
        try {
            // Fetch customer order counts excluding Trash
            $customer_counts = $pdo->query('
                SELECT customer_name, customer_email, customer_phone, COUNT(*) AS order_count 
                FROM orders 
                WHERE deleted_at IS NULL
                GROUP BY customer_email, customer_phone 
                ORDER BY order_count DESC
            ')->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $customer_counts = [];
            $message = '<div class="alert alert-danger">Deshtoi marrja e numrave të porosive të klientëve.</div>';
        }
        break;

    case 'manage_ratings':
        try {
            // Fetch all ratings
            $stmt = $pdo->prepare('SELECT * FROM ratings ORDER BY created_at DESC');
            $stmt->execute();
            $ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $ratings = [];
            $message = '<div class="alert alert-danger">Deshtoi marrja e vlerësimeve.</div>';
        }
        break;

    case 'delete_rating':
        if ($id > 0) {
            // Delete rating
            $delete = $pdo->prepare('DELETE FROM ratings WHERE id = ?');
            $success = $delete->execute([$id]);
            $msg = $success ? 'Vlerësimi është fshirë me sukses.' : 'Deshtoi fshirja e vlerësimit.';
            header('Location: orders.php?action=manage_ratings&message=' . urlencode($msg));
            exit();
        }
        break;

    case 'view':
    default:
        try {
            // Fetch all active orders with order_count using subquery and COALESCE to handle NULLs
            $stmt = $pdo->prepare('
                SELECT o.*, os.status, c.order_count, u.username AS delivery_username
                FROM orders o
                JOIN order_statuses os ON o.status_id = os.id
                JOIN (
                    SELECT 
                        COALESCE(customer_email, "") AS customer_email, 
                        COALESCE(customer_phone, "") AS customer_phone, 
                        COUNT(*) AS order_count
                    FROM orders
                    WHERE deleted_at IS NULL
                    GROUP BY customer_email, customer_phone
                ) c 
                ON (o.customer_email = c.customer_email OR o.customer_phone = c.customer_phone)
                LEFT JOIN users u ON o.delivery_user_id = u.id
                WHERE o.deleted_at IS NULL
                ORDER BY os.id ASC, o.created_at DESC
            ');
            $stmt->execute();
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch all statuses
            $statuses = $pdo->query('SELECT * FROM order_statuses ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

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
            $message = '<div class="alert alert-danger">Deshtoi marrja e porosive.</div>';
        }
        break;
}

// Fetch Ratings if Managing Ratings
if ($action === 'manage_ratings') {
    // Ratings have been fetched in the 'manage_ratings' case
}
?>

<!-- Main Container -->
<div class="container-fluid">

    <!-- Display Messages -->
    <?php if (isset($_GET['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_GET['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif (isset($message)): ?>
        <?= $message ?>
    <?php endif; ?>

    <?php if ($action === 'view'): ?>
        <!-- Orders Management Section -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Porositë</h2>
            <div>
                <a href="orders.php?action=customer_counts" class="btn btn-primary">
                    <i class="fas fa-chart-bar"></i> Numrat e Porosive të Klientëve
                </a>
                <a href="orders.php?action=manage_ratings" class="btn btn-warning ms-2">
                    <i class="fas fa-star"></i> Menaxho Vlerësimet
                </a>
                <a href="orders.php?action=view_trash" class="btn btn-danger ms-2">
                    <i class="fas fa-trash"></i> Trash
                </a>
            </div>
        </div>

        <?php foreach ($grouped_orders as $status => $orders): ?>
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <i class="fas fa-tag"></i> <?= htmlspecialchars($status) ?> Porositë
                </div>
                <div class="card-body">
                    <?php if (count($orders) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Klienti</th>
                                        <th>Email</th>
                                        <th>Telefoni</th>
                                        <th>Adresë</th>
                                        <th>Totali (€)</th>
                                        <th>Krijuar Më</th>
                                        <th>Numri i Porosive</th>
                                        <th>Personi i Dërgesës</th>
                                        <th>Veprime</th>
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
                                            <td><?= htmlspecialchars($order['created_at']) ?></td>
                                            <td>
                                                <?= htmlspecialchars($order['order_count']) ?>
                                                <?php if ($order['order_count'] > 1): ?>
                                                    <span class="badge bg-primary">Ripërsëritje</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= $order['delivery_username'] ? htmlspecialchars($order['delivery_username']) : 'N/A' ?>
                                            </td>
                                            <td>
                                                <a href="orders.php?action=view_details&id=<?= $order['id'] ?>" class="btn btn-sm btn-info me-1" data-bs-toggle="tooltip" title="Shiko">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="orders.php?action=update_status_form&id=<?= $order['id'] ?>" class="btn btn-sm btn-warning me-1" data-bs-toggle="tooltip" title="Përditëso Statusin">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <!-- Delete Button triggers Modal -->
                                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $order['id'] ?>" data-bs-toggle="tooltip" title="Fshije">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>

                                                <!-- Delete Confirmation Modal -->
                                                <div class="modal fade" id="deleteModal<?= $order['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $order['id'] ?>" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content">
                                                            <div class="modal-header bg-danger text-white">
                                                                <h5 class="modal-title" id="deleteModalLabel<?= $order['id'] ?>">Fshije Porosinë</h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                A jeni të sigurt që dëshironi të fshini këtë porosi?
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Jo</button>
                                                                <a href="orders.php?action=delete&id=<?= $order['id'] ?>" class="btn btn-danger">Po, fshije</a>
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
                        <p>Nuk ka porosi në këtë kategori.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

    <?php elseif ($action === 'manage_ratings'): ?>
        <!-- Manage Ratings Section -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Menaxho Vlerësimet e Klientëve</h2>
            <a href="orders.php?action=view" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kthehu në Porositë
            </a>
        </div>

        <?php if ($ratings): ?>
            <div class="table-responsive">
                <table class="table table-striped table-bordered data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Emri i Plotë</th>
                            <th>Email</th>
                            <th>Telefoni</th>
                            <th>Anonim</th>
                            <th>Vlerësimi</th>
                            <th>Komentet</th>
                            <th>Paraqitur Më</th>
                            <th>Veprime</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ratings as $rating): ?>
                            <tr>
                                <td><?= htmlspecialchars($rating['id']) ?></td>
                                <td><?= $rating['anonymous'] ? 'Anonim' : htmlspecialchars($rating['full_name']) ?></td>
                                <td><?= $rating['anonymous'] ? 'N/A' : htmlspecialchars($rating['email']) ?></td>
                                <td><?= $rating['anonymous'] ? 'N/A' : htmlspecialchars($rating['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <?= $rating['anonymous'] ?
                                        '<span class="badge bg-secondary">Po</span>' :
                                        '<span class="badge bg-success">Jo</span>'
                                    ?>
                                </td>
                                <td><?= str_repeat('⭐', $rating['rating']) ?></td>
                                <td><?= htmlspecialchars($rating['comments'] ?? 'Pa komente') ?></td>
                                <td><?= htmlspecialchars($rating['created_at']) ?></td>
                                <td>
                                    <!-- Delete Rating Button triggers Modal -->
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteRatingModal<?= $rating['id'] ?>" data-bs-toggle="tooltip" title="Fshije">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>

                                    <!-- Delete Rating Confirmation Modal -->
                                    <div class="modal fade" id="deleteRatingModal<?= $rating['id'] ?>" tabindex="-1" aria-labelledby="deleteRatingModalLabel<?= $rating['id'] ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title" id="deleteRatingModalLabel<?= $rating['id'] ?>">Fshije Vlerësimin</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    A jeni të sigurt që dëshironi të fshini këtë vlerësim?
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Jo</button>
                                                    <a href="orders.php?action=delete_rating&id=<?= $rating['id'] ?>" class="btn btn-danger">Po, fshije</a>
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
            <p>Nuk ka vlerësime të paraqitura ende.</p>
        <?php endif; ?>
    <?php elseif ($action === 'view_details' && $id > 0): ?>
        <!-- Order Details Section -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Detajet e Porosisë - ID: <?= htmlspecialchars($order['id']) ?></h2>
            <a href="orders.php?action=view" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kthehu në Porositë
            </a>
        </div>

        <!-- Order Information Card -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <i class="fas fa-info-circle"></i> Informacione të Porosisë
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Left Column -->
                    <div class="col-md-6">
                        <p><strong>Emri i Klientit:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($order['customer_email']) ?></p>
                        <p><strong>Telefoni:</strong> <?= htmlspecialchars($order['customer_phone']) ?></p>
                        <p><strong>Adresa:</strong> <?= nl2br(htmlspecialchars($order['delivery_address'])) ?></p>
                    </div>
                    <!-- Right Column -->
                    <div class="col-md-6">
                        <p><strong>Shuma Totale:</strong> <?= number_format($order['total_amount'], 2) ?>€</p>
                        <p><strong>Statusi:</strong> <?= htmlspecialchars($order['status']) ?></p>
                        <p><strong>Krijuar Më:</strong> <?= htmlspecialchars($order['created_at']) ?></p>
                        <?php if ($order['delivery_user_id']):
                            // Fetch delivery user details
                            $stmt = $pdo->prepare('SELECT username, email FROM users WHERE id = ?');
                            $stmt->execute([$order['delivery_user_id']]);
                            $delivery_user = $stmt->fetch(PDO::FETCH_ASSOC);
                        ?>
                            <p><strong>Personi i Dërgesës:</strong> <?= htmlspecialchars($delivery_user['username']) ?> (<?= htmlspecialchars($delivery_user['email']) ?>)</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Items Section -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <i class="fas fa-boxes"></i> Pajisjet e Porosisë
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Produkti</th>
                                <th>Madhësia</th>
                                <th>Shuma</th>
                                <th>Çmimi (€)</th>
                                <th>Extras</th>
                                <th>Pijes</th>
                                <th>Udhëzime Speciale</th>
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
            </div>
        </div>

        <!-- Map Section -->
        <?php if (isset($order['latitude'], $order['longitude']) && $order['latitude'] && $order['longitude']): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-map-marker-alt"></i> Adresa e Dërgesës
                </div>
                <div class="card-body">
                    <div id="map" style="height: 400px;"></div>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Initialize the map
                    var map = L.map('map').setView([<?= htmlspecialchars($order['latitude']) ?>, <?= htmlspecialchars($order['longitude']) ?>], 15);

                    // Set up the OpenStreetMap tiles
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '© OpenStreetMap'
                    }).addTo(map);

                    // Add a marker for the delivery address
                    L.marker([<?= htmlspecialchars($order['latitude']) ?>, <?= htmlspecialchars($order['longitude']) ?>]).addTo(map)
                        .bindPopup("<b>Adresa e Dërgesës</b><br><?= htmlspecialchars($order['delivery_address']) ?>")
                        .openPopup();
                });
            </script>
        <?php else: ?>
            <div class="alert alert-warning">
                Adresa e dërgesës nuk është përcaktuar ose nuk mund të gjejë koordinatat.
            </div>
        <?php endif; ?>

        <!-- Status History Section -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <i class="fas fa-history"></i> Historia e Statusit të Porosisë
            </div>
            <div class="card-body">
                <?php if ($status_history): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered data-table">
                            <thead>
                                <tr>
                                    <th>Statusi</th>
                                    <th>Personi i Dërgesës</th>
                                    <th>Ndryshuar Më</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($status_history as $history): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($history['status']) ?></td>
                                        <td>
                                            <?= $history['delivery_username'] ?
                                                htmlspecialchars($history['delivery_username']) . ' (' . htmlspecialchars($history['delivery_email']) . ')' :
                                                'N/A'
                                            ?>
                                        </td>
                                        <td><?= htmlspecialchars($history['changed_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>Asnjë histori statusi nuk është regjistruar.</p>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($action === 'update_status_form' && $id > 0): ?>
        <!-- Update Status Form Section -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Update Status Porosisë - ID: <?= htmlspecialchars($order['id']) ?></h2>
            <a href="orders.php?action=view" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kthehu në Porositë
            </a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="orders.php?action=update_status&id=<?= $id ?>">
                    <div class="mb-3">
                        <label for="status_id" class="form-label">Zgjidh Statusin e Ri</label>
                        <select class="form-select" id="status_id" name="status_id" required>
                            <?php foreach ($statuses as $status_option): ?>
                                <option value="<?= htmlspecialchars($status_option['id']) ?>" <?= ($order['status_id'] == $status_option['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($status_option['status']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="delivery_user_id" class="form-label">Zgjidh Personin e Dërgesës</label>
                        <select class="form-select" id="delivery_user_id" name="delivery_user_id">
                            <option value="">-- Zgjidh Personin e Dërgesës --</option>
                            <?php foreach ($delivery_users as $user): ?>
                                <option value="<?= htmlspecialchars($user['id']) ?>" <?= ($order['delivery_user_id'] == $user['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Opsionale për disa statuse.</div>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> Përditëso Statusin
                    </button>
                    <a href="orders.php?action=view" class="btn btn-secondary">
                        <i class="fas fa-times-circle"></i> Anulo
                    </a>
                </form>
            </div>
        </div>

    <?php elseif ($action === 'customer_counts'): ?>
        <!-- Customer Counts Section -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Numrat e Porosive të Klientëve</h2>
            <a href="orders.php?action=view" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kthehu në Porositë
            </a>
        </div>

        <?php if ($customer_counts): ?>
            <div class="table-responsive">
                <table class="table table-striped table-bordered data-table">
                    <thead>
                        <tr>
                            <th>Emri i Klientit</th>
                            <th>Email</th>
                            <th>Telefoni</th>
                            <th>Numri Total i Porosive</th>
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
            <p>Nuk ka porosi të regjistruara për klientët.</p>
        <?php endif; ?>

    <?php elseif ($action === 'view_trash'): ?>
        <!-- Trash Management Section -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Trash - Porositë e Fshira</h2>
            <a href="orders.php?action=view" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kthehu në Porositë
            </a>
        </div>

        <?php if ($trash_orders): ?>
            <div class="table-responsive">
                <table class="table table-striped table-bordered data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Emri i Klientit</th>
                            <th>Email</th>
                            <th>Telefoni</th>
                            <th>Adresë</th>
                            <th>Shuma Totale (€)</th>
                            <th>Fshihet Më</th>
                            <th>Statusi</th>
                            <th>Personi i Dërgesës</th>
                            <th>Veprime</th>
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
                                <td>
                                    <?= $order['delivery_username'] ? htmlspecialchars($order['delivery_username']) : 'N/A' ?>
                                </td>
                                <td>
                                    <!-- Restore Button triggers Modal -->
                                    <button type="button" class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#restoreModal<?= $order['id'] ?>" data-bs-toggle="tooltip" title="Rikthe">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                    <!-- Permanent Delete Button triggers Modal -->
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#permanentDeleteModal<?= $order['id'] ?>" data-bs-toggle="tooltip" title="Fshije Përfundimisht">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>

                                    <!-- Restore Confirmation Modal -->
                                    <div class="modal fade" id="restoreModal<?= $order['id'] ?>" tabindex="-1" aria-labelledby="restoreModalLabel<?= $order['id'] ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header bg-success text-white">
                                                    <h5 class="modal-title" id="restoreModalLabel<?= $order['id'] ?>">Rikthe Porosinë</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    A jeni të sigurt që dëshironi të riktheni këtë porosi?
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Jo</button>
                                                    <a href="orders.php?action=restore&id=<?= $order['id'] ?>" class="btn btn-success">Po, rikthe</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Permanent Delete Confirmation Modal -->
                                    <div class="modal fade" id="permanentDeleteModal<?= $order['id'] ?>" tabindex="-1" aria-labelledby="permanentDeleteModalLabel<?= $order['id'] ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title" id="permanentDeleteModalLabel<?= $order['id'] ?>">Fshije Porosinë Përfundimisht</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    A jeni të sigurt që dëshironi të fshini këtë porosi përfundimisht? Kjo nuk mund të rikthehet.
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Jo</button>
                                                    <a href="orders.php?action=permanent_delete&id=<?= $order['id'] ?>" class="btn btn-danger">Po, fshije</a>
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
            <p>Trash është bosh.</p>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php if ($action === 'view_details' && $id > 0): ?>
    <!-- Order Details Section Continued -->
    <?php if (isset($order['latitude'], $order['longitude']) && $order['latitude'] && $order['longitude']): ?>
        <!-- Map Initialization Script -->
        <!-- Leaflet.js JS is already included in the head -->
    <?php endif; ?>
<?php endif; ?>

<!-- DataTables Initialization and Bootstrap Tooltips -->
<script>
    $(document).ready(function() {
        // Initialize DataTables
        $('.data-table').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/algerian.json" // Adjust language as needed
            },
            "paging": true,
            "searching": true,
            "ordering": true,
            "responsive": true
        });

        // Initialize Bootstrap Tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>