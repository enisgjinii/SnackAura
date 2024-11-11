<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

// Function to send status update emails
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

// Initialize variables
$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

// Define error log file
define('ERROR_LOG_FILE', __DIR__ . '/errors.md');

// Handle different actions using switch statement
switch ($action) {
    case 'update_status':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
            $status_id = $_POST['status_id'] ?? 0;
            if ($status_id && is_numeric($status_id)) {
                // Fetch new status
                $stmt = $pdo->prepare('SELECT status FROM order_statuses WHERE id = ?');
                $stmt->execute([$status_id]);
                $new_status = $stmt->fetchColumn();

                if ($new_status) {
                    // Fetch customer details
                    $stmt = $pdo->prepare('SELECT customer_email, customer_name FROM orders WHERE id = ?');
                    $stmt->execute([$id]);
                    $order = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($order) {
                        // Update order status
                        $update = $pdo->prepare('UPDATE orders SET status_id = ? WHERE id = ?');
                        if ($update->execute([$status_id, $id])) {
                            // Send email notification
                            sendStatusUpdateEmail($order['customer_email'], $order['customer_name'], $id, $new_status);
                            header('Location: orders.php?action=view&message=' . urlencode('Order status updated successfully.'));
                            exit();
                        }
                    }
                }
            }
            header('Location: orders.php?action=update_status_form&id=' . $id . '&message=' . urlencode('Failed to update status.'));
            exit();
        }
        break;

    case 'delete':
        if ($id > 0) {
            // Delete order
            $delete = $pdo->prepare('DELETE FROM orders WHERE id = ?');
            $success = $delete->execute([$id]);
            $msg = $success ? 'Order deleted successfully.' : 'Failed to delete order.';
            header('Location: orders.php?action=view&message=' . urlencode($msg));
            exit();
        }
        break;

    case 'view_details':
        if ($id > 0) {
            try {
                // Fetch order details
                $stmt = $pdo->prepare('SELECT o.*, os.status FROM orders o JOIN order_statuses os ON o.status_id = os.id WHERE o.id = ?');
                $stmt->execute([$id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($order) {
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
                }
            } catch (PDOException $e) {
                echo '<div class="alert alert-danger">Failed to fetch order details.</div>';
                require_once 'includes/footer.php';
                exit();
            }
        }
        break;

    case 'update_status_form':
        if ($id > 0) {
            try {
                // Fetch order details
                $stmt = $pdo->prepare('SELECT o.*, os.status FROM orders o JOIN order_statuses os ON o.status_id = os.id WHERE o.id = ?');
                $stmt->execute([$id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);

                // Fetch all statuses
                $statuses = $pdo->query('SELECT * FROM order_statuses ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

                // Fetch any messages
                $message = $_GET['message'] ?? '';
            } catch (PDOException $e) {
                echo '<div class="alert alert-danger">Failed to fetch data.</div>';
                require_once 'includes/footer.php';
                exit();
            }
        }
        break;

    case 'customer_counts':
        try {
            // Fetch customer order counts
            $customer_counts = $pdo->query('
                SELECT customer_name, customer_email, customer_phone, COUNT(*) AS order_count 
                FROM orders 
                GROUP BY customer_email, customer_phone 
                ORDER BY order_count DESC
            ')->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $customer_counts = [];
            $message = '<div class="alert alert-danger">Failed to fetch customer counts.</div>';
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
            $message = '<div class="alert alert-danger">Failed to fetch ratings.</div>';
        }
        break;

    case 'delete_rating':
        if ($id > 0) {
            // Delete rating
            $delete = $pdo->prepare('DELETE FROM ratings WHERE id = ?');
            $success = $delete->execute([$id]);
            $msg = $success ? 'Rating deleted successfully.' : 'Failed to delete rating.';
            header('Location: orders.php?action=manage_ratings&message=' . urlencode($msg));
            exit();
        }
        break;

    case 'view':
    default:
        try {
            // Fetch all orders with order_count using subquery and COALESCE to handle NULLs
            $stmt = $pdo->prepare('
                SELECT o.*, os.status, c.order_count
                FROM orders o
                JOIN order_statuses os ON o.status_id = os.id
                JOIN (
                    SELECT 
                        COALESCE(customer_email, "") AS customer_email, 
                        COALESCE(customer_phone, "") AS customer_phone, 
                        COUNT(*) AS order_count
                    FROM orders
                    GROUP BY customer_email, customer_phone
                ) c 
                ON (o.customer_email = c.customer_email OR o.customer_phone = c.customer_phone)
                ORDER BY os.id ASC, o.created_at DESC
            ');
            $stmt->execute();
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch all statuses
            $statuses = $pdo->query('SELECT * FROM order_statuses ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

            // Group orders by status using array_map
            $grouped_orders = [];
            foreach ($statuses as $status) {
                $grouped_orders[$status['status']] = [];
            }
            foreach ($orders as $order) {
                $grouped_orders[$order['status']][] = $order;
            }
        } catch (PDOException $e) {
            $grouped_orders = [];
            $message = '<div class="alert alert-danger">Failed to fetch orders.</div>';
        }
        break;
}

// Fetch Ratings if Managing Ratings
if ($action === 'manage_ratings') {
    // Ratings have been fetched in the 'manage_ratings' case
}
?>
<?php if ($action === 'view'): ?>
    <!-- Existing Orders Management Code -->
    <h2>Orders</h2>
    <a href="orders.php?action=customer_counts" class="btn btn-primary mb-3">View Customer Order Counts</a>
    <a href="orders.php?action=manage_ratings" class="btn btn-warning mb-3 ms-2">Manage Ratings</a>
    <?= isset($_GET['message']) ? '<div class="alert alert-success">' . htmlspecialchars($_GET['message']) . '</div>' : ($message ?? '') ?>
    <?php foreach ($grouped_orders as $status => $orders): ?>
        <h4><?= htmlspecialchars($status) ?> Orders</h4>
        <?php if (count($orders) > 0): ?>
            <table class="table table-bordered table-hover mb-4">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Total (€)</th>
                        <th>Created At</th>
                        <th>Order Count</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="orders-table-body-<?= strtolower(str_replace(' ', '_', $status)) ?>">
                    <?php foreach ($orders as $order): ?>
                        <?php $order_count = $order['order_count'] ?? 1; ?>
                        <tr id="order-row-<?= htmlspecialchars($order['id']) ?>">
                            <td><?= htmlspecialchars($order['id']) ?></td>
                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                            <td><?= htmlspecialchars($order['customer_email']) ?></td>
                            <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                            <td><?= htmlspecialchars($order['delivery_address']) ?></td>
                            <td><?= number_format($order['total_amount'], 2) ?>€</td>
                            <td><?= htmlspecialchars($order['created_at']) ?></td>
                            <td><?= htmlspecialchars($order_count) ?> <?= $order_count > 1 ? '<span class="badge bg-primary">Repeat</span>' : '' ?></td>
                            <td>
                                <a href="orders.php?action=view_details&id=<?= $order['id'] ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View">View</a>
                                <a href="orders.php?action=update_status_form&id=<?= $order['id'] ?>" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Update Status">Update Status</a>
                                <a href="orders.php?action=delete&id=<?= $order['id'] ?>" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="Delete" onclick="return confirm('Are you sure you want to delete this order?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No orders in this category.</p>
        <?php endif; ?>
    <?php endforeach; ?>
<?php elseif ($action === 'manage_ratings'): ?>
    <h2>Manage Customer Ratings</h2>
    <?= isset($_GET['message']) ? '<div class="alert alert-success">' . htmlspecialchars($_GET['message']) . '</div>' : ($message ?? '') ?>
    <?php if ($ratings): ?>
        <table class="table table-bordered table-hover">
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
                    <tr id="rating-row-<?= htmlspecialchars($rating['id']) ?>">
                        <td><?= htmlspecialchars($rating['id']) ?></td>
                        <td><?= $rating['anonymous'] ? 'Anonymous' : htmlspecialchars($rating['full_name']) ?></td>
                        <td><?= $rating['anonymous'] ? 'N/A' : htmlspecialchars($rating['email']) ?></td>
                        <td><?= $rating['anonymous'] ? 'N/A' : htmlspecialchars($rating['phone'] ?? 'N/A') ?></td>
                        <td><?= $rating['anonymous'] ? '<span class="badge bg-secondary">Yes</span>' : '<span class="badge bg-success">No</span>' ?></td>
                        <td><?= str_repeat('⭐', $rating['rating']) ?></td>
                        <td><?= htmlspecialchars($rating['comments'] ?? 'No comments') ?></td>
                        <td><?= htmlspecialchars($rating['created_at']) ?></td>
                        <td>
                            <a href="orders.php?action=delete_rating&id=<?= $rating['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this rating?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No ratings submitted yet.</p>
    <?php endif; ?>
    <a href="orders.php?action=view" class="btn btn-secondary">Back to Orders</a>
<?php elseif ($action === 'view_details' && $id > 0): ?>
    <!-- Existing Order Details Code -->
    <h2>Order Details - ID: <?= htmlspecialchars($order['id']) ?></h2>
    <div class="mb-3">
        <strong>Customer Name:</strong> <?= htmlspecialchars($order['customer_name']) ?><br>
        <strong>Email:</strong> <?= htmlspecialchars($order['customer_email']) ?><br>
        <strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone']) ?><br>
        <strong>Address:</strong> <?= nl2br(htmlspecialchars($order['delivery_address'])) ?><br>
        <strong>Total Amount:</strong> <?= number_format($order['total_amount'], 2) ?>€<br>
        <strong>Status:</strong> <?= htmlspecialchars($order['status']) ?><br>
        <strong>Created At:</strong> <?= htmlspecialchars($order['created_at']) ?>
    </div>
    <h4>Items:</h4>
    <table class="table table-bordered">
        <thead>
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
                        <?= !empty($item['extras'])
                            ? implode('<br>', array_map(fn($e) => htmlspecialchars($e['name']) . ' x' . htmlspecialchars($e['quantity']) . ' (+' . number_format($e['unit_price'], 2) . '€)', $item['extras']))
                            : '-' ?>
                    </td>
                    <td>
                        <?= !empty($item['drinks'])
                            ? implode('<br>', array_map(fn($d) => htmlspecialchars($d['name']) . ' x' . htmlspecialchars($d['quantity']) . ' (+' . number_format($d['unit_price'], 2) . '€)', $item['drinks']))
                            : '-' ?>
                    </td>
                    <td><?= htmlspecialchars($item['special_instructions']) ?: '-' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <a href="orders.php?action=view" class="btn btn-secondary">Back to Orders</a>
<?php elseif ($action === 'update_status_form' && $id > 0): ?>
    <!-- Existing Update Status Form Code -->
    <h2>Update Order Status - ID: <?= htmlspecialchars($order['id']) ?></h2>
    <?= $message ? '<div class="alert alert-danger">' . htmlspecialchars($message) . '</div>' : '' ?>
    <form method="POST" action="orders.php?action=update_status&id=<?= $id ?>">
        <div class="mb-3">
            <label for="status_id" class="form-label">Select New Status</label>
            <select class="form-select" id="status_id" name="status_id" required>
                <?php foreach ($statuses as $status_option): ?>
                    <option value="<?= htmlspecialchars($status_option['id']) ?>" <?= ($order['status_id'] == $status_option['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($status_option['status']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-success">Update Status</button>
        <a href="orders.php?action=view" class="btn btn-secondary">Cancel</a>
    </form>
<?php elseif ($action === 'customer_counts'): ?>
    <!-- Existing Customer Counts Code -->
    <h2>Customer Order Counts</h2>
    <?= isset($_GET['message']) ? '<div class="alert alert-success">' . htmlspecialchars($_GET['message']) . '</div>' : ($message ?? '') ?>
    <table class="table table-bordered table-hover">
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
    <a href="orders.php?action=view" class="btn btn-secondary">Back to Orders</a>
<?php elseif ($action === 'manage_ratings'): ?>
    <!-- Ratings Management Section -->
    <h2>Manage Customer Ratings</h2>
    <?= isset($_GET['message']) ? '<div class="alert alert-success">' . htmlspecialchars($_GET['message']) . '</div>' : ($message ?? '') ?>
    <?php if ($ratings): ?>
        <table class="table table-bordered table-hover">
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
                    <tr id="rating-row-<?= htmlspecialchars($rating['id']) ?>">
                        <td><?= htmlspecialchars($rating['id']) ?></td>
                        <td><?= $rating['anonymous'] ? 'Anonymous' : htmlspecialchars($rating['full_name']) ?></td>
                        <td><?= $rating['anonymous'] ? 'N/A' : htmlspecialchars($rating['email']) ?></td>
                        <td><?= $rating['anonymous'] ? 'N/A' : htmlspecialchars($rating['phone'] ?? 'N/A') ?></td>
                        <td><?= $rating['anonymous'] ? '<span class="badge bg-secondary">Yes</span>' : '<span class="badge bg-success">No</span>' ?></td>
                        <td><?= str_repeat('⭐', $rating['rating']) ?></td>
                        <td><?= htmlspecialchars($rating['comments'] ?? 'No comments') ?></td>
                        <td><?= htmlspecialchars($rating['created_at']) ?></td>
                        <td>
                            <a href="orders.php?action=delete_rating&id=<?= $rating['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this rating?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No ratings submitted yet.</p>
    <?php endif; ?>
    <a href="orders.php?action=view" class="btn btn-secondary">Back to Orders</a>
<?php endif; ?>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<?php require_once 'includes/footer.php'; ?>