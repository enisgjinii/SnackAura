<?php
// orders.php

ob_start();
require_once 'includes/db_connect.php';
require_once 'includes/header.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Error Logging
define('ERROR_LOG_FILE', __DIR__ . '/errors.md');
function log_error($m, $c = '')
{
    $t = date('Y-m-d H:i:s');
    file_put_contents(ERROR_LOG_FILE, "### [$t] Error\n\n**Message:** " . htmlspecialchars($m, ENT_QUOTES, 'UTF-8') . "\n" . ($c ? "**Context:** " . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . "\n" : '') . "---\n\n", FILE_APPEND | LOCK_EX);
}

// Email Functions
function sendEmail($to, $toName, $sub, $body)
{
    $mail = new PHPMailer(true);
    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'egjini17@gmail.com';
        $mail->Password = 'axnjsldfudhohipv'; // **Important:** Store passwords securely!
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Email Headers
        $mail->setFrom('egjini17@gmail.com', 'Yumiis');
        $mail->addAddress($to, $toName);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $sub;
        $mail->Body = $body;

        $mail->send();
    } catch (Exception $e) {
        log_error("Mail Error: " . $mail->ErrorInfo, "Sending Email to: $to");
    }
}

function sendStatusUpdateEmail($e, $n, $oid, $st, $sd = null, $stm = null)
{
    $sub = "Order #$oid Status Updated";
    $i = ($sd && $stm) ? "<p><strong>Scheduled Delivery:</strong> $sd at $stm</p>" : '';
    $b = "<html><body><h2>Hello, $n</h2><p>Your order #$oid status is now <strong>$st</strong>.</p>$i<p>Thank you for choosing Yumiis!</p></body></html>";
    sendEmail($e, $n, $sub, $b);
}

function sendDelayNotificationEmail($e, $n, $oid, $t)
{
    $sub = "Delay Notification for Order #$oid";
    $b = "<html><body><h2>Hello, $n</h2><p>Your order #$oid may be delayed by $t hour(s).</p><p>Please let us know if this is acceptable.</p></body></html>";
    sendEmail($e, $n, $sub, $b);
}

function notifyDeliveryPerson($e, $n, $oid, $st)
{
    $sub = "New Order Assigned - Order #$oid";
    $b = "<html><body><h2>Hello, $n</h2><p>You have been assigned to order #$oid with status $st.</p><p>Please proceed as required.</p></body></html>";
    sendEmail($e, $n, $sub, $b);
}

// Exception and Error Handlers
set_exception_handler(function ($e) {
    log_error("Uncaught Exception: " . $e->getMessage(), "File: {$e->getFile()} Line: {$e->getLine()}");
    header("Location: orders.php?action=view&message=unknown_error");
    exit;
});
set_error_handler(function ($sv, $m, $f, $l) {
    if (!(error_reporting() & $sv)) return;
    throw new ErrorException($m, 0, $sv, $f, $l);
});

// Fetch Delivery Users (for Admin)
function getDeliveryUsers($pdo)
{
    $q = $pdo->prepare("SELECT id,username,email FROM users WHERE role='delivery' AND is_active=1");
    $q->execute();
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

// User and Action Setup
$user_role = $_SESSION['role'] ?? 'admin';
$user_id = $_SESSION['user_id'] ?? 1;
$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

// Define Allowed Actions Based on Role
$allowed_actions = [
    'admin' => ['view', 'view_details', 'send_notification', 'send_delay_notification', 'delete', 'assign_delivery', 'update_status_form', 'update_status', 'view_trash', 'restore', 'permanent_delete', 'top_products'],
    'delivery' => ['view', 'view_details', 'send_notification', 'send_delay_notification', 'update_status_form', 'update_status']
];
if (!isset($allowed_actions[$user_role]) || !in_array($action, $allowed_actions[$user_role])) {
    header('HTTP/1.1 403 Forbidden');
    echo "<h1>403 Forbidden</h1><p>Role: " . htmlspecialchars($user_role) . "</p>";
    require_once 'includes/footer.php';
    exit;
}

// Define Order Statuses
$statuses = ['New Order', 'Kitchen', 'On the Way', 'Delivered', 'Canceled'];

// Fetch All Orders
function getAllOrders($pdo)
{
    $q = $pdo->prepare("SELECT o.*,t.name AS tip_name,t.percentage AS tip_percentage,t.amount AS tip_fixed_amount 
                        FROM orders o 
                        LEFT JOIN tips t ON o.tip_id=t.id 
                        WHERE o.is_deleted=0 
                        ORDER BY o.created_at DESC");
    $q->execute();
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Single Order by ID
function getOrderById($pdo, $id)
{
    $q = $pdo->prepare("SELECT o.*,t.name AS tip_name,t.percentage AS tip_percentage,t.amount AS tip_fixed_amount 
                        FROM orders o 
                        LEFT JOIN tips t ON o.tip_id=t.id 
                        WHERE o.id=? AND o.is_deleted=0 
                        LIMIT 1");
    $q->execute([$id]);
    return $q->fetch(PDO::FETCH_ASSOC);
}

// Fetch Top 10 Products
function getTopProducts($pdo)
{
    $q = $pdo->prepare("SELECT order_details FROM orders WHERE is_deleted=0");
    $q->execute();
    $all = $q->fetchAll(PDO::FETCH_ASSOC);
    $count = [];
    foreach ($all as $row) {
        $detail = json_decode($row['order_details'], true);
        if (!empty($detail['items']) && is_array($detail['items'])) {
            foreach ($detail['items'] as $it) {
                $nm = $it['name'] ?? 'Unknown';
                $qt = (int)($it['quantity'] ?? 0);
                if ($qt > 0) $count[$nm] = ($count[$nm] ?? 0) + $qt;
            }
        }
    }
    arsort($count);
    return array_slice($count, 0, 10, true);
}

// Define a function to generate a safe table ID based on status
function generate_table_id($status)
{
    return 'table-' . strtolower(str_replace(' ', '_', $status));
}
$delivery_users = getDeliveryUsers($pdo);
try {
    switch ($action) {
        case 'view_details':
            if ($id <= 0) {
                header("Location: orders.php?action=view&message=Invalid Order ID");
                exit;
            }
            $order = getOrderById($pdo, $id);
            if (!$order) {
                header("Location: orders.php?action=view&message=Order not found");
                exit;
            }
            break;

        case 'update_status_form':
            if ($id <= 0) {
                header("Location: orders.php?action=view&message=Invalid Order ID");
                exit;
            }
            $order = getOrderById($pdo, $id);
            if (!$order) {
                header("Location: orders.php?action=view&message=Order not found");
                exit;
            }
            break;

        case 'update_status':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
                $newStatus = $_POST['status'] ?? '';
                if (!empty($newStatus) && in_array($newStatus, $statuses)) {
                    $order = getOrderById($pdo, $id);
                    if (!$order) {
                        header("Location: orders.php?action=view&message=Order not found");
                        exit;
                    }
                    $s = $pdo->prepare("UPDATE orders SET status=?, updated_at=NOW() WHERE id=?");
                    if ($s->execute([$newStatus, $id])) {
                        sendStatusUpdateEmail($order['customer_email'], $order['customer_name'], $id, $newStatus, $order['scheduled_date'], $order['scheduled_time']);
                        header("Location: orders.php?action=view&message=Order status updated");
                        exit;
                    } else {
                        header("Location: orders.php?action=view&message=Status update failed");
                        exit;
                    }
                }
                header("Location: orders.php?action=view&message=Invalid status");
                exit;
            }
            header("Location: orders.php?action=view&message=Invalid request");
            exit;

        case 'delete':
            if ($id <= 0) {
                header("Location: orders.php?action=view&message=Invalid ID");
                exit;
            }
            $s = $pdo->prepare("UPDATE orders SET is_deleted=1, updated_at=NOW() WHERE id=?");
            if ($s->execute([$id])) {
                header("Location: orders.php?action=view&message=Order moved to Trash");
                exit;
            } else {
                header("Location: orders.php?action=view&message=Could not move to Trash");
                exit;
            }

        case 'view_trash':
            if ($user_role !== 'admin') {
                header('HTTP/1.1 403 Forbidden');
                exit;
            }
            $t = $pdo->prepare("SELECT o.*,t.name AS tip_name,t.percentage AS tip_percentage,t.amount AS tip_fixed_amount 
                                FROM orders o 
                                LEFT JOIN tips t ON o.tip_id=t.id 
                                WHERE o.is_deleted=1 
                                ORDER BY o.updated_at DESC");
            $t->execute();
            $trash_orders = $t->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'restore':
            if ($user_role !== 'admin') {
                header('HTTP/1.1 403 Forbidden');
                exit;
            }
            if ($id <= 0) {
                header("Location: orders.php?action=view_trash&message=Invalid ID");
                exit;
            }
            $r = $pdo->prepare("UPDATE orders SET is_deleted=0, updated_at=NOW() WHERE id=?");
            if ($r->execute([$id])) {
                header("Location: orders.php?action=view_trash&message=Order restored");
                exit;
            } else {
                header("Location: orders.php?action=view_trash&message=Restore failed");
                exit;
            }

        case 'permanent_delete':
            if ($user_role !== 'admin') {
                header('HTTP/1.1 403 Forbidden');
                exit;
            }
            if ($id <= 0) {
                header("Location: orders.php?action=view_trash&message=Invalid ID");
                exit;
            }
            $pd = $pdo->prepare("DELETE FROM orders WHERE id=?");
            if ($pd->execute([$id])) {
                header("Location: orders.php?action=view_trash&message=Order permanently deleted");
                exit;
            } else {
                header("Location: orders.php?action=view_trash&message=Permanent delete failed");
                exit;
            }

        case 'send_delay_notification':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
                $time = (int)($_POST['additional_time'] ?? 0);
                if ($time <= 0) {
                    header("Location: orders.php?action=view&message=Invalid Time");
                    exit;
                }
                $q = $pdo->prepare("SELECT * FROM orders WHERE id=? AND is_deleted=0");
                $q->execute([$id]);
                $ord = $q->fetch(PDO::FETCH_ASSOC);
                if (!$ord) {
                    header("Location: orders.php?action=view&message=Order not found");
                    exit;
                }
                sendDelayNotificationEmail($ord['customer_email'], $ord['customer_name'], $id, $time);
                header("Location: orders.php?action=view&message=Delay Notification Sent");
                exit;
            }
            header("Location: orders.php?action=view&message=Invalid request");
            exit;

        case 'assign_delivery':
            if ($user_role !== 'admin') {
                header('HTTP/1.1 403 Forbidden');
                exit;
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
                $du = (int)($_POST['delivery_user_id'] ?? 0);
                if ($du <= 0) {
                    header("Location: orders.php?action=view&message=No delivery user chosen");
                    exit;
                }
                $s = $pdo->prepare("SELECT email,username FROM users WHERE id=? AND role='delivery' AND is_active=1");
                $s->execute([$du]);
                $usr = $s->fetch(PDO::FETCH_ASSOC);
                if (!$usr) {
                    header("Location: orders.php?action=view&message=Invalid delivery user");
                    exit;
                }
                $upd = $pdo->prepare("UPDATE orders SET delivery_user_id=? WHERE id=?");
                if ($upd->execute([$du, $id])) {
                    notifyDeliveryPerson($usr['email'], $usr['username'], $id, "Assigned");
                    header("Location: orders.php?action=view&message=Delivery user assigned");
                    exit;
                }
                header("Location: orders.php?action=view&message=Delivery assignment failed");
                exit;
            }
            header("Location: orders.php?action=view&message=Invalid request");
            exit;

        case 'top_products':
            if ($user_role !== 'admin') {
                header('HTTP/1.1 403 Forbidden');
                exit;
            }
            $top_products = getTopProducts($pdo);
            break;

        case 'view':
        default:
            $all_orders = getAllOrders($pdo);
            $status_orders = array_fill_keys($statuses, []);
            foreach ($all_orders as $ord) {
                $st = in_array($ord['status'], $statuses) ? $ord['status'] : 'New Order';
                $status_orders[$st][] = $ord;
            }
            $delivery_users = $user_role === 'admin' ? getDeliveryUsers($pdo) : [];
    }
} catch (Exception $e) {
    log_error("Action switch error: " . $e->getMessage());
    header("Location: orders.php?action=view&message=Unknown error");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Orders Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/datetime/1.4.0/css/dataTables.dateTime.min.css">
    <style>
        body {
            background: #f8f9fa;
            font-family: Arial, sans-serif;
            font-size: .9rem;
        }

        .container-fluid {
            max-width: 95%;
        }

        .btn-group-top {
            margin-bottom: .5rem;
        }

        .btn-group-top .btn {
            padding: .2rem .6rem;
            font-size: .8rem;
        }

        .alert-info {
            padding: .4rem .6rem;
            margin-bottom: .5rem;
        }

        .card .card-header {
            padding: .3rem .6rem;
            font-size: .9rem;
        }

        .card .card-body {
            padding: .6rem;
        }

        .table thead th {
            padding: .3rem;
            font-size: .8rem;
        }

        .table td {
            padding: .3rem;
            font-size: .8rem;
        }

        .badge-status {
            font-size: .65rem;
            padding: .1em .3em;
            border-radius: .25rem;
        }

        .badge-new {
            background-color: #0d6efd;
        }

        .badge-kitchen {
            background-color: #ffc107;
        }

        .badge-on-way {
            background-color: #fd7e14;
        }

        .badge-delivered {
            background-color: #198754;
        }

        .badge-canceled {
            background-color: #dc3545;
        }

        .assign-form {
            display: flex;
            align-items: center;
        }

        .assign-form select {
            margin-right: 5px;
            padding: .1rem .3rem;
            font-size: .8rem;
        }

        .assign-form .btn {
            padding: .2rem .4rem;
            font-size: .8rem;
        }

        .modal-dialog {
            max-width: 80%;
        }

        .modal-content .modal-header {
            padding: .3rem .6rem;
        }

        .modal-content .modal-body {
            padding: .6rem;
        }

        .modal-content .modal-footer {
            padding: .3rem .6rem;
        }

        .invoice-logo {
            max-width: 80px;
            height: auto;
        }

        .invoice-title {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
        }

        .invoice-items th,
        .invoice-items td {
            padding: .2rem;
            border: 1px solid #ddd;
            font-size: .8rem;
        }
    </style>
</head>

<body class="p-2">
    <div class="container-fluid">
        <div class="btn-group-top">
            <button class="btn btn-primary me-2" onclick="window.location='orders.php?action=view'">View Orders</button>
            <?php if ($user_role === 'admin'): ?>
                <button class="btn btn-dark me-2" onclick="window.location='orders.php?action=view_trash'">Trash</button>
                <button class="btn btn-info" onclick="window.location='orders.php?action=top_products'">Top 10 Products</button>
            <?php endif; ?>
        </div>
        <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_GET['message']) ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif (!empty($message)): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <h5 class="mb-3">Orders Management (Role: <?= htmlspecialchars($user_role) ?>)</h5>
        <?php
        if ($action === 'view_details' && !empty($order)):
            // 1) Fetch store data based on store_id
            $storeData = null;
            if (!empty($order['store_id'])) {
                $stmtStore = $pdo->prepare("SELECT * FROM stores WHERE id=? LIMIT 1");
                $stmtStore->execute([$order['store_id']]);
                $storeData = $stmtStore->fetch(PDO::FETCH_ASSOC);
            }
        ?>
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    Order #<?= htmlspecialchars($order['id']) ?> Details
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Left Column: Customer and Order Info -->
                        <div class="col-md-6">
                            <p><strong>Customer Name:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($order['customer_email']) ?></p>
                            <p><strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone']) ?></p>
                            <p><strong>Address:</strong> <?= htmlspecialchars($order['delivery_address']) ?></p>
                            <p><strong>Total Amount:</strong> <?= number_format($order['total_amount'], 2) ?>€</p>
                            <p><strong>Tip:</strong>
                                <?php
                                if (!empty($order['tip_name'])) {
                                    $ti = $order['tip_percentage'] !== null ? htmlspecialchars($order['tip_percentage']) . '%' : number_format($order['tip_fixed_amount'], 2) . '€';
                                    echo "<span class='badge bg-info'>" . htmlspecialchars($order['tip_name']) . "</span> ($ti)";
                                } else {
                                    echo "N/A";
                                }
                                ?>
                            </p>
                            <p><strong>Tip Amount:</strong> <?= number_format($order['tip_amount'], 2) ?>€</p>
                            <p><strong>Coupon Code:</strong> <?= htmlspecialchars($order['coupon_code'] ?? 'N/A') ?></p>
                            <p><strong>Coupon Discount:</strong> <?= number_format(($order['coupon_discount'] ?? 0), 2) ?>€</p>
                            <p><strong>Scheduled Date:</strong> <?= htmlspecialchars($order['scheduled_date'] ?? 'N/A') ?></p>
                            <p><strong>Scheduled Time:</strong> <?= htmlspecialchars($order['scheduled_time'] ?? 'N/A') ?></p>
                            <p><strong>Payment Method:</strong> <?= htmlspecialchars($order['payment_method']) ?></p>
                            <p><strong>Store ID:</strong> <?= htmlspecialchars($order['store_id']) ?></p>
                            <p><strong>Status:</strong> <?= htmlspecialchars($order['status']) ?></p>
                            <p><strong>Delivery User:</strong>
                                <?php
                                if ($order['delivery_user_id'] && $user_role === 'admin') {
                                    $st = $pdo->prepare("SELECT username FROM users WHERE id=? LIMIT 1");
                                    $st->execute([$order['delivery_user_id']]);
                                    $du = $st->fetch(PDO::FETCH_ASSOC);
                                    echo $du ? htmlspecialchars($du['username']) : "Unassigned";
                                } else {
                                    echo "Unassigned";
                                }
                                ?>
                            </p>
                            <p><strong>Created At:</strong> <?= htmlspecialchars($order['created_at']) ?></p>
                            <p><strong>Updated At:</strong> <?= htmlspecialchars($order['updated_at'] ?? 'N/A') ?></p>
                        </div>
                        <!-- Right Column: Order Items -->
                        <div class="col-md-6">
                            <h6>Order Items</h6>
                            <?php
                            $details = json_decode($order['order_details'], true);
                            $items = $details['items'] ?? [];
                            if ($items):
                                foreach ($items as $item):
                            ?>
                                    <div class="card mb-2">
                                        <div class="card-header" style="font-size:.9rem;">
                                            <?= htmlspecialchars($item['name']) ?> (Qty: <?= htmlspecialchars($item['quantity']) ?>)
                                        </div>
                                        <div class="card-body" style="font-size:.8rem;">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <?php if (!empty($item['image_url'])): ?>
                                                        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="Item" class="img-fluid rounded">
                                                    <?php else: ?>
                                                        <img src="default-image.png" alt="No Image" class="img-fluid rounded">
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-8">
                                                    <p><strong>Description:</strong> <?= htmlspecialchars($item['description'] ?? 'N/A') ?></p>
                                                    <p><strong>Size:</strong> <?= htmlspecialchars($item['size']) ?> (<?= number_format($item['size_price'], 2) ?>€)</p>
                                                    <?php if (!empty($item['extras'])): ?>
                                                        <p><strong>Extras:</strong></p>
                                                        <ul>
                                                            <?php foreach ($item['extras'] as $ex): ?>
                                                                <li><?= htmlspecialchars($ex['name']) ?> x<?= intval($ex['quantity'] ?? 1) ?> (<?= number_format($ex['price'], 2) ?>€)=<?= number_format($ex['price'] * ($ex['quantity'] ?? 1), 2) ?>€</li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['sauces'])): ?>
                                                        <p><strong>Sauces:</strong></p>
                                                        <ul>
                                                            <?php foreach ($item['sauces'] as $sx): ?>
                                                                <li><?= htmlspecialchars($sx['name']) ?> x<?= intval($sx['quantity'] ?? 1) ?> (<?= number_format($sx['price'], 2) ?>€)=<?= number_format($sx['price'] * ($sx['quantity'] ?? 1), 2) ?>€</li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['dresses'])): ?>
                                                        <p><strong>Dresses:</strong></p>
                                                        <ul>
                                                            <?php foreach ($item['dresses'] as $dr): ?>
                                                                <li><?= htmlspecialchars($dr['name']) ?> x<?= intval($dr['quantity'] ?? 1) ?> (<?= number_format($dr['price'], 2) ?>€)=<?= number_format($dr['price'] * ($dr['quantity'] ?? 1), 2) ?>€</li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['drink'])): ?>
                                                        <p><strong>Drink:</strong> <?= htmlspecialchars($item['drink']['name']) ?> (<?= number_format($item['drink']['price'], 2) ?>€)</p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['special_instructions'])): ?>
                                                        <p><strong>Instructions:</strong> <?= htmlspecialchars($item['special_instructions']) ?></p>
                                                    <?php endif; ?>
                                                    <p><strong>Unit Price:</strong> <?= number_format($item['unit_price'], 2) ?>€</p>
                                                    <p><strong>Total Price:</strong> <?= number_format($item['total_price'], 2) ?>€</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php
                                endforeach;
                            else:
                                ?>
                                <p>No items in this order.</p>
                            <?php
                            endif;
                            ?>
                        </div>
                    </div>
                    <!-- Invoice Button & Modal Trigger -->
                    <button type="button" class="btn btn-dark mt-2" data-bs-toggle="modal" data-bs-target="#invoiceModal" style="font-size:.8rem;">
                        <i class="bi bi-file-earmark-text"></i> Invoice
                    </button>
                    <a href="orders.php?action=view" class="btn btn-secondary mt-2" style="font-size:.8rem;">Back</a>
                </div>
            </div>
            <!-- Invoice Modal -->
            <div class="modal fade" id="invoiceModal">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-dark text-white" style="font-size:.9rem;">
                            Invoice for Order #<?= htmlspecialchars($order['id']) ?>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" style="font-size:.8rem;">
                            <?php
                            // Decode the JSON details to find shipping_fee, items, etc.
                            $details = json_decode($order['order_details'], true);
                            $items = $details['items'] ?? [];

                            // Shipping Fee from order_details
                            $shipping = isset($details['shipping_fee']) ? (float)$details['shipping_fee'] : 0;

                            // Basic amounts (already in DB columns)
                            $invTip   = isset($order['tip_amount']) ? (float)$order['tip_amount'] : 0;
                            $invDisc  = isset($order['coupon_discount']) ? (float)$order['coupon_discount'] : 0;
                            $totalAmt = isset($order['total_amount']) ? (float)$order['total_amount'] : 0;

                            // Compute Items Subtotal
                            $itemsSubtotal = 0;
                            foreach ($items as $it) {
                                $itemsSubtotal += isset($it['total_price']) ? (float)$it['total_price'] : 0;
                            }

                            // Taxes (if any) - Assuming a tax rate, e.g., 10%
                            $taxRate = 0.10; // 10%
                            $taxAmount = ($itemsSubtotal + $shipping - $invDisc) * $taxRate;

                            // Final Total Calculation
                            $calculatedTotal = $itemsSubtotal + $shipping + $invTip - $invDisc + $taxAmount;
                            ?>
                            <div class="p-1">
                                <!-- Store Info -->
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <?php if ($storeData && !empty($storeData['cart_logo'])): ?>
                                        <img src="../admin/<?= htmlspecialchars($storeData['cart_logo']) ?>" alt="Store Logo" class="invoice-logo">
                                    <?php endif; ?>
                                    <h3 class="invoice-title">INVOICE</h3>
                                </div>

                                <!-- Detailed Store Info -->
                                <?php if ($storeData): ?>
                                    <div class="mb-2">
                                        <strong><?= htmlspecialchars($storeData['name']) ?></strong><br>
                                        <?= htmlspecialchars($storeData['address']) ?><br>
                                        <?php if (!empty($storeData['phone'])): ?>
                                            Tel: <?= htmlspecialchars($storeData['phone']) ?><br>
                                        <?php endif; ?>
                                        <?php if (!empty($storeData['email'])): ?>
                                            <?= htmlspecialchars($storeData['email']) ?><br>
                                        <?php endif; ?>
                                        <?php if (!empty($storeData['tax_id'])): ?>
                                            VAT/Tax ID: <?= htmlspecialchars($storeData['tax_id']) ?><br>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="mb-2">
                                        <strong>Yumiis Restaurant</strong><br>
                                        123 Main St<br>
                                        Some City, 12345<br>
                                        +1 (555) 123-4567<br>
                                        contact@yumiis.com
                                    </div>
                                <?php endif; ?>

                                <!-- Customer Info -->
                                <div class="mb-2">
                                    <p>
                                        <strong>Bill To:</strong> <?= htmlspecialchars($order['customer_name']) ?><br>
                                        <?= htmlspecialchars($order['delivery_address']) ?><br>
                                        <?= htmlspecialchars($order['customer_email']) ?>
                                    </p>
                                    <p><strong>Order Date:</strong> <?= htmlspecialchars($order['created_at']) ?></p>
                                </div>

                                <!-- Itemized List -->
                                <div class="invoice-items mb-2">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Qty</th>
                                                <th>Unit(€)</th>
                                                <th>Total(€)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($items)): ?>
                                                <?php foreach ($items as $it):
                                                    $nm   = htmlspecialchars($it['name'] ?? 'Item');
                                                    $qty  = intval($it['quantity'] ?? 1);
                                                    $unit = isset($it['unit_price']) ? number_format((float)$it['unit_price'], 2) : '0.00';
                                                    $tpr  = isset($it['total_price']) ? number_format((float)$it['total_price'], 2) : '0.00';
                                                ?>
                                                    <tr>
                                                        <td><?= $nm ?></td>
                                                        <td><?= $qty ?></td>
                                                        <td><?= $unit ?></td>
                                                        <td><?= $tpr ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4">No items</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Detailed Breakdown -->
                                <p><strong>Items Subtotal:</strong> <?= number_format($itemsSubtotal, 2) ?> €</p>
                                <p><strong>Shipping Fee:</strong> <?= number_format($shipping, 2) ?> €</p>
                                <p><strong>Tax (<?= ($taxRate * 100) ?>%):</strong> <?= number_format($taxAmount, 2) ?> €</p>
                                <p><strong>Tip:</strong> <?= number_format($invTip, 2) ?> €</p>
                                <p><strong>Coupon Discount:</strong> <?= number_format($invDisc, 2) ?> €</p>
                                <hr>
                                <p><strong>Total Amount:</strong> <?= number_format($calculatedTotal, 2) ?> €</p>
                                <p class="text-center mt-2" style="font-size:.7rem;color:#666;">
                                    Thank you for your order!
                                </p>
                            </div>
                        </div>
                        <div class="modal-footer" style="font-size:.8rem;">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <!-- Optionally, you can add a print button -->
                            <button type="button" class="btn btn-primary" onclick="window.print();">Print Invoice</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        elseif ($action === 'update_status_form' && !empty($order)): ?>
            <div class="card">
                <div class="card-header bg-warning" style="font-size:.9rem;">
                    Update Status #<?= htmlspecialchars($order['id']) ?>
                </div>
                <div class="card-body" style="font-size:.8rem;">
                    <form method="POST" action="orders.php?action=update_status&id=<?= htmlspecialchars($order['id']) ?>">
                        <div class="mb-2">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?= htmlspecialchars($s) ?>" <?= ($order['status'] === $s ? 'selected' : '') ?>><?= htmlspecialchars($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-primary" style="font-size:.8rem;">Update</button>
                        <a href="orders.php?action=view" class="btn btn-secondary" style="font-size:.8rem;">Cancel</a>
                    </form>
                </div>
            </div>
        <?php
        elseif ($action === 'view_trash' && isset($trash_orders)): ?>
            <div class="card">
                <div class="card-header bg-secondary text-white" style="font-size:.9rem;">
                    Trashed Orders
                </div>
                <div class="card-body" style="font-size:.8rem;">
                    <?php if ($trash_orders): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-bordered align-middle data-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Address</th>
                                        <th>Total(€)</th>
                                        <th>Tip</th>
                                        <th>Tip(€)</th>
                                        <th>Sch.Date</th>
                                        <th>Sch.Time</th>
                                        <th>Created</th>
                                        <th>Status</th>
                                        <th>Coupon</th>
                                        <th>C.Discount(€)</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trash_orders as $tr): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($tr['id']) ?></td>
                                            <td><?= htmlspecialchars($tr['customer_name']) ?></td>
                                            <td><?= htmlspecialchars($tr['customer_email']) ?></td>
                                            <td><?= htmlspecialchars($tr['customer_phone']) ?></td>
                                            <td><?= htmlspecialchars($tr['delivery_address']) ?></td>
                                            <td><?= number_format($tr['total_amount'], 2) ?></td>
                                            <td>
                                                <?php
                                                if (!empty($tr['tip_name'])) {
                                                    $xx = $tr['tip_percentage'] !== null ? htmlspecialchars($tr['tip_percentage']) . '%' : number_format($tr['tip_fixed_amount'], 2) . '€';
                                                    echo "<span class='badge bg-info'>" . htmlspecialchars($tr['tip_name']) . "</span> ($xx)";
                                                } else echo 'N/A';
                                                ?>
                                            </td>
                                            <td><?= number_format($tr['tip_amount'], 2) ?></td>
                                            <td><?= htmlspecialchars($tr['scheduled_date'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($tr['scheduled_time'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($tr['created_at']) ?></td>
                                            <td><?= htmlspecialchars($tr['status']) ?></td>
                                            <td><?= htmlspecialchars($tr['coupon_code'] ?? '') ?></td>
                                            <td><?= number_format(($tr['coupon_discount'] ?? 0), 2) ?></td>
                                            <td>
                                                <a href="orders.php?action=restore&id=<?= $tr['id'] ?>" class="btn btn-sm btn-success me-1" style="font-size:.7rem;"><i class="bi bi-arrow-counterclockwise"></i></a>
                                                <a href="orders.php?action=permanent_delete&id=<?= $tr['id'] ?>" class="btn btn-sm btn-danger" style="font-size:.7rem;" onclick="return confirm('Are you sure you want to permanently delete?');"><i class="bi bi-trash3-fill"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p>No trashed orders.</p>
                    <?php endif; ?>
                    <a href="orders.php?action=view" class="btn btn-secondary mt-2" style="font-size:.8rem;">Back</a>
                </div>
            </div>
        <?php
        elseif ($action === 'top_products' && isset($top_products)): ?>
            <div class="card">
                <div class="card-header bg-info text-white" style="font-size:.9rem;">
                    Top 10 Products
                </div>
                <div class="card-body" style="font-size:.8rem;">
                    <?php if ($top_products): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-bordered align-middle data-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Rank</th>
                                        <th>Product</th>
                                        <th>Quantity Sold</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $pos = 1;
                                    foreach ($top_products as $prod => $qnt): ?>
                                        <tr>
                                            <td><?= $pos ?></td>
                                            <td><?= htmlspecialchars($prod) ?></td>
                                            <td><?= $qnt ?></td>
                                        </tr>
                                    <?php $pos++;
                                    endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p>No top products found.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        else:
            foreach ($statuses as $st): ?>
                <div class="mb-3">
                    <h6 style="font-size:.9rem;">
                        <?= htmlspecialchars($st) ?> Orders
                        <?php
                        $badge = match ($st) {
                            'New Order' => 'badge-new',
                            'Kitchen' => 'badge-kitchen',
                            'On the Way' => 'badge-on-way',
                            'Delivered' => 'badge-delivered',
                            'Canceled' => 'badge-canceled',
                            default => 'badge-secondary',
                        }; ?>
                        <span class="badge badge-status <?= $badge ?>" style="font-size:.65rem;"><?= $st ?></span>
                    </h6>
                    <?php if (!empty($status_orders[$st])): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-bordered align-middle data-table" id="<?= generate_table_id($st) ?>">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Address</th>
                                        <th>Total(€)</th>
                                        <th>Tip</th>
                                        <th>Tip(€)</th>
                                        <th>Sch.Date</th>
                                        <th>Sch.Time</th>
                                        <th>Created</th>
                                        <th>Status</th>
                                        <?php if ($user_role === 'admin'): ?>
                                            <th>Assign</th>
                                        <?php endif; ?>
                                        <th>Coupon</th>
                                        <th>C.Discount(€)</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($status_orders[$st] as $o):
                                        $tipBadge = 'N/A';
                                        if (!empty($o['tip_name'])) {
                                            $val = $o['tip_percentage'] !== null ? htmlspecialchars($o['tip_percentage']) . '%' : number_format($o['tip_fixed_amount'], 2) . '€';
                                            $tipBadge = "<span class='badge bg-info'>" . htmlspecialchars($o['tip_name']) . "</span> ($val)";
                                        }
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($o['id']) ?></td>
                                            <td><?= htmlspecialchars($o['customer_name']) ?></td>
                                            <td><?= htmlspecialchars($o['customer_email']) ?></td>
                                            <td><?= htmlspecialchars($o['customer_phone']) ?></td>
                                            <td><?= htmlspecialchars($o['delivery_address']) ?></td>
                                            <td><?= number_format($o['total_amount'], 2) ?></td>
                                            <td><?= $tipBadge ?></td>
                                            <td><?= number_format($o['tip_amount'], 2) ?></td>
                                            <td><?= htmlspecialchars($o['scheduled_date'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($o['scheduled_time'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($o['created_at']) ?></td>
                                            <td><?= htmlspecialchars($o['status']) ?></td>
                                            <?php if ($user_role === 'admin'): ?>
                                                <td>
                                                    <form method="POST" action="orders.php?action=assign_delivery&id=<?= $o['id'] ?>" class="assign-form">
                                                        <select name="delivery_user_id" class="form-select form-select-sm me-1" required>
                                                            <option value="">Choose</option>
                                                            <?php foreach ($delivery_users as $du): ?>
                                                                <option value="<?= htmlspecialchars($du['id']) ?>" <?= ($o['delivery_user_id'] == $du['id'] ? 'selected' : '') ?>>
                                                                    <?= htmlspecialchars($du['username']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-person-check"></i></button>
                                                    </form>
                                                </td>
                                            <?php endif; ?>
                                            <td><?= htmlspecialchars($o['coupon_code'] ?? '') ?></td>
                                            <td><?= number_format(($o['coupon_discount'] ?? 0), 2) ?></td>
                                            <td>
                                                <a href="orders.php?action=view_details&id=<?= $o['id'] ?>" class="btn btn-sm btn-info me-1" style="font-size:.7rem;"><i class="bi bi-eye"></i></a>
                                                <a href="orders.php?action=update_status_form&id=<?= $o['id'] ?>" class="btn btn-sm btn-warning me-1" style="font-size:.7rem;"><i class="bi bi-pencil"></i></a>
                                                <?php if ($user_role === 'admin'): ?>
                                                    <a href="orders.php?action=delete&id=<?= $o['id'] ?>" class="btn btn-sm btn-danger me-1" style="font-size:.7rem;" onclick="return confirm('Move to trash?');"><i class="bi bi-trash"></i></a>
                                                    <button type="button" class="btn btn-sm btn-secondary me-1" style="font-size:.7rem;" data-bs-toggle="modal" data-bs-target="#delayModal<?= $o['id'] ?>"><i class="bi bi-clock"></i></button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php if ($user_role === 'admin'): ?>
                                            <!-- Delay Notification Modal -->
                                            <div class="modal fade" id="delayModal<?= $o['id'] ?>">
                                                <div class="modal-dialog modal-dialog-centered" style="font-size:.8rem;">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-secondary text-white" style="padding:.4rem;">
                                                            Delay Notification #<?= htmlspecialchars($o['id']) ?>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST" action="orders.php?action=send_delay_notification&id=<?= $o['id'] ?>">
                                                            <div class="modal-body">
                                                                <div class="mb-2">
                                                                    <label for="additional_time_<?= $o['id'] ?>" class="form-label">Additional Time (hours)</label>
                                                                    <input type="number" class="form-control" id="additional_time_<?= $o['id'] ?>" name="additional_time" min="1" required>
                                                                </div>
                                                                <p>Send Delay Notification?</p>
                                                            </div>
                                                            <div class="modal-footer" style="padding:.4rem;">
                                                                <button type="button" class="btn btn-secondary" style="font-size:.7rem;" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-warning" style="font-size:.7rem;">Send</button>
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
                        <p>No <?= htmlspecialchars($st) ?> orders found.</p>
                    <?php endif; ?>
                </div>
        <?php endforeach;
        endif; ?>
    </div>

    <!-- Notification Sound (Hidden) -->
    <audio id="orderSound" src="alert.mp3" preload="auto"></audio>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/datetime/1.4.0/js/dataTables.dateTime.min.js"></script>

    <!-- Notification Script -->
    <script>
        $(document).ready(function() {
            // Initialize DataTables for all tables
            $('.data-table').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                info: true,
                responsive: true,
                language: {
                    emptyTable: "No data available",
                    search: "Search:"
                }
            });

            // Get the highest order ID in the 'New Order' table on page load
            let lastOrderId = 0;
            const newOrderTableId = 'table-new_order'; // Adjust if the table ID format is different

            // Function to find the highest order ID in the 'New Order' table
            function initializeLastOrderId() {
                $('#' + newOrderTableId + ' tbody tr').each(function() {
                    const currentId = parseInt($(this).find('td').eq(0).text(), 10);
                    if (currentId > lastOrderId) {
                        lastOrderId = currentId;
                    }
                });
            }

            initializeLastOrderId();

            // Notification Sound Handling
            const orderSound = document.getElementById('orderSound');

            // Polling function to check for new orders every 5 seconds
            setInterval(() => {
                $.ajax({
                    url: 'fetch_new_orders.php',
                    method: 'GET',
                    data: {
                        last_id: lastOrderId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            const newOrders = response.newOrders;
                            if (newOrders.length > 0) {
                                const table = $('#' + newOrderTableId).DataTable();
                                newOrders.forEach(order => {
                                    // Prepare Tip Badge
                                    let tipBadge = 'N/A';
                                    if (order.tip_name) {
                                        const tipValue = order.tip_percentage !== null ? htmlspecialchars(order.tip_percentage) + '%' : parseFloat(order.tip_fixed_amount).toFixed(2) + '€';
                                        tipBadge = `<span class='badge bg-info'>${htmlspecialchars(order.tip_name)}</span> (${tipValue})`;
                                    }

                                    // Prepare Assign Form if Admin
                                    let assignForm = '';
                                    <?php if ($user_role === 'admin'): ?>
                                        assignForm = `<form method="POST" action="orders.php?action=assign_delivery&id=${order.id}" class="assign-form">
                                                        <select name="delivery_user_id" class="form-select form-select-sm me-1" required>
                                                            <option value="">Choose</option>
                                                            <?php foreach ($delivery_users as $du): ?>
                                                                <option value="<?= htmlspecialchars($du['id']) ?>"><?= htmlspecialchars($du['username']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-person-check"></i></button>
                                                    </form>`;
                                    <?php endif; ?>

                                    // Prepare Actions Buttons
                                    let actions = `<a href="orders.php?action=view_details&id=${order.id}" class="btn btn-sm btn-info me-1" style="font-size:.7rem;"><i class="bi bi-eye"></i></a>
                                                   <a href="orders.php?action=update_status_form&id=${order.id}" class="btn btn-sm btn-warning me-1" style="font-size:.7rem;"><i class="bi bi-pencil"></i></a>`;
                                    <?php if ($user_role === 'admin'): ?>
                                        actions += `<a href="orders.php?action=delete&id=${order.id}" class="btn btn-sm btn-danger me-1" style="font-size:.7rem;" onclick="return confirm('Move to trash?');"><i class="bi bi-trash"></i></a>
                                                    <button type="button" class="btn btn-sm btn-secondary me-1" style="font-size:.7rem;" data-bs-toggle="modal" data-bs-target="#delayModal${order.id}"><i class="bi bi-clock"></i></button>`;
                                    <?php endif; ?>

                                    // Append the new order to the 'New Order' table
                                    table.row.add([
                                        order.id,
                                        htmlspecialchars(order.customer_name),
                                        htmlspecialchars(order.customer_email),
                                        htmlspecialchars(order.customer_phone),
                                        htmlspecialchars(order.delivery_address),
                                        parseFloat(order.total_amount).toFixed(2),
                                        tipBadge,
                                        parseFloat(order.tip_amount).toFixed(2),
                                        htmlspecialchars(order.scheduled_date || 'N/A'),
                                        htmlspecialchars(order.scheduled_time || 'N/A'),
                                        htmlspecialchars(order.created_at),
                                        htmlspecialchars(order.status),
                                        <?php if ($user_role === 'admin'): ?>
                                            assignForm,
                                        <?php endif; ?>(order.coupon_code || ''),
                                        parseFloat(order.coupon_discount || 0).toFixed(2),
                                        actions
                                    ]).draw(false);

                                    // Update lastOrderId
                                    if (order.id > lastOrderId) {
                                        lastOrderId = order.id;
                                    }

                                    // Optionally, add Delay Modal for Admin
                                    <?php if ($user_role === 'admin'): ?>
                                        const delayModal = `
                                            <div class="modal fade" id="delayModal${order.id}">
                                                <div class="modal-dialog modal-dialog-centered" style="font-size:.8rem;">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-secondary text-white" style="padding:.4rem;">
                                                            Delay Notification #${order.id}
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST" action="orders.php?action=send_delay_notification&id=${order.id}">
                                                            <div class="modal-body">
                                                                <div class="mb-2">
                                                                    <label for="additional_time_${order.id}" class="form-label">Additional Time (hours)</label>
                                                                    <input type="number" class="form-control" id="additional_time_${order.id}" name="additional_time" min="1" required>
                                                                </div>
                                                                <p>Send Delay Notification?</p>
                                                            </div>
                                                            <div class="modal-footer" style="padding:.4rem;">
                                                                <button type="button" class="btn btn-secondary" style="font-size:.7rem;" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-warning" style="font-size:.7rem;">Send</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        `;
                                        $('body').append(delayModal);
                                    <?php endif; ?>
                                });

                                // Play notification sound
                                orderSound.play();
                            }
                        } else {
                            console.error('Fetch New Orders Error:', response.message);
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('AJAX Error:', textStatus, errorThrown);
                    }
                });
            }, 5000); // Poll every 5 seconds

            // Function to escape HTML to prevent XSS
            function htmlspecialchars(str) {
                if (typeof str !== 'string') {
                    return str;
                }
                return str.replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }
        });
    </script>
    <?php ob_end_flush(); ?>
    <?php include 'includes/footer.php'; ?>
</body>

</html>