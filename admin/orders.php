<?php
ob_start();
require_once 'includes/db_connect.php';
require_once 'includes/header.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define('ERROR_LOG_FILE', __DIR__ . '/errors.md');

function log_error($message, $context = '')
{
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(
        ERROR_LOG_FILE,
        "### [$timestamp] Error\n\n**Message:** " . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "\n" .
            ($context ? "**Context:** " . htmlspecialchars($context, ENT_QUOTES, 'UTF-8') . "\n" : "") .
            "---\n\n",
        FILE_APPEND | LOCK_EX
    );
}

function sendEmail($to, $toName, $subject, $body)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'egjini17@gmail.com';
        $mail->Password = 'axnjsldfudhohipv';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
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

function sendStatusUpdateEmail($email, $name, $order_id, $status, $scheduled_date = null, $scheduled_time = null)
{
    $sub = "Update Status of Your Order #$order_id";
    $info = ($scheduled_date && $scheduled_time)
        ? "<p><strong>Scheduled Delivery:</strong> $scheduled_date at $scheduled_time</p>"
        : '';
    $body = "<html><body><h2>Hello, $name</h2><p>Your order #$order_id status is now <strong>$status</strong>.</p>$info<p>Thank you for choosing Yumiis!</p></body></html>";
    sendEmail($email, $name, $sub, $body);
}

function sendDelayNotificationEmail($email, $name, $order_id, $additional_time)
{
    $sub = "Delay Notification for Order #$order_id";
    $body = "<html><body><h2>Hello, $name</h2>
             <p>Your order #$order_id may be delayed by an additional $additional_time hour(s).</p>
             <p>Please let us know if this is acceptable.</p></body></html>";
    sendEmail($email, $name, $sub, $body);
}

function notifyDeliveryPerson($email, $name, $order_id, $status)
{
    $sub = "New Order Assigned - Order #$order_id";
    $body = "<html><body><h2>Hello, $name</h2>
             <p>You have been assigned to order #$order_id with status $status.</p>
             <p>Please proceed as required.</p></body></html>";
    sendEmail($email, $name, $sub, $body);
}

set_exception_handler(function ($e) {
    log_error("Uncaught Exception: " . $e->getMessage(), "File: {$e->getFile()} Line: {$e->getLine()}");
    header("Location: orders.php?action=view&message=unknown_error");
    exit;
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function getDeliveryUsers($pdo)
{
    $s = $pdo->prepare("SELECT id,username,email FROM users WHERE role='delivery' AND is_active=1");
    $s->execute();
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

$user_role = $_SESSION['role'] ?? 'admin';
$user_id = $_SESSION['user_id'] ?? 1;
$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

$allowed_actions = [
    'admin' => ['view', 'view_details', 'send_notification', 'send_delay_notification', 'delete', 'assign_delivery', 'update_status_form', 'update_status', 'view_trash', 'restore', 'permanent_delete', 'top_products'],
    'delivery' => ['view', 'view_details', 'send_notification', 'send_delay_notification', 'update_status_form', 'update_status']
];

if (!isset($allowed_actions[$user_role]) || !in_array($action, $allowed_actions[$user_role])) {
    header('HTTP/1.1 403 Forbidden');
    echo "<h1>403 Forbidden</h1><p>You do not have permission. Your role: " . htmlspecialchars($user_role) . "</p>";
    require_once 'includes/footer.php';
    exit;
}

$statuses = ['New Order', 'Kitchen', 'On the Way', 'Delivered', 'Canceled'];

/** Retrieve all orders that are not deleted **/
function getAllOrders($pdo)
{
    $s = $pdo->prepare("
    SELECT 
      o.id,o.customer_name,o.customer_email,o.customer_phone,o.delivery_address,o.total_amount,o.tip_id,o.tip_amount,
      o.scheduled_date,o.scheduled_time,o.payment_method,o.store_id,o.order_details,o.created_at,o.updated_at,
      o.delivery_user_id,o.status,o.is_deleted,
      t.name AS tip_name, t.percentage AS tip_percentage, t.amount AS tip_fixed_amount
    FROM orders o
    LEFT JOIN tips t ON o.tip_id=t.id
    WHERE o.is_deleted=0
    ORDER BY o.created_at DESC
    ");
    $s->execute();
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

/** Retrieve single order by ID **/
function getOrderById($pdo, $id)
{
    $s = $pdo->prepare("
    SELECT 
      o.id,o.customer_name,o.customer_email,o.customer_phone,o.delivery_address,o.total_amount,o.tip_id,o.tip_amount,
      o.scheduled_date,o.scheduled_time,o.payment_method,o.store_id,o.order_details,o.created_at,o.updated_at,
      o.delivery_user_id,o.status,o.is_deleted,
      t.name AS tip_name, t.percentage AS tip_percentage, t.amount AS tip_fixed_amount
    FROM orders o
    LEFT JOIN tips t ON o.tip_id=t.id
    WHERE o.id=? AND o.is_deleted=0
    LIMIT 1");
    $s->execute([$id]);
    return $s->fetch(PDO::FETCH_ASSOC);
}

/** Used for Top 10 Products page **/
function getTopProducts($pdo)
{
    $s = $pdo->prepare("SELECT order_details FROM orders WHERE is_deleted=0");
    $s->execute();
    $orders = $s->fetchAll(PDO::FETCH_ASSOC);

    $counts = [];
    foreach ($orders as $o) {
        $d = json_decode($o['order_details'], true);
        if ($d && isset($d['items']) && is_array($d['items'])) {
            foreach ($d['items'] as $item) {
                $name = $item['name'] ?? 'Unknown';
                $q = intval($item['quantity'] ?? 0);
                if ($q > 0) {
                    $counts[$name] = ($counts[$name] ?? 0) + $q;
                }
            }
        }
    }

    arsort($counts);
    return array_slice($counts, 0, 10, true);
}

switch ($action) {
    case 'view_details':
        if ($id > 0) {
            try {
                $order = getOrderById($pdo, $id);
                if (!$order) {
                    header("Location: orders.php?action=view&message=" . urlencode("Order not found."));
                    exit;
                }
            } catch (PDOException $e) {
                log_error("Failed to retrieve order details: " . $e->getMessage());
                header("Location: orders.php?action=view&message=" . urlencode("Failed to retrieve order details."));
                exit;
            }
        } else {
            header("Location: orders.php?action=view&message=" . urlencode("Invalid order ID."));
            exit;
        }
        break;

    case 'update_status_form':
        if ($id > 0) {
            try {
                $order = getOrderById($pdo, $id);
                if (!$order) {
                    header("Location: orders.php?action=view&message=" . urlencode("Order not found."));
                    exit;
                }
            } catch (PDOException $e) {
                log_error("Failed to retrieve order for status update: " . $e->getMessage());
                header("Location: orders.php?action=view&message=" . urlencode("Failed to retrieve order for status update."));
                exit;
            }
        } else {
            header("Location: orders.php?action=view&message=" . urlencode("Invalid order ID."));
            exit;
        }
        break;

    case 'update_status':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
            $newStatus = $_POST['status'] ?? '';
            if (!empty($newStatus) && in_array($newStatus, $statuses)) {
                try {
                    $order = getOrderById($pdo, $id);
                    if (!$order) {
                        header("Location: orders.php?action=view&message=" . urlencode("Order not found."));
                        exit;
                    }
                    $s = $pdo->prepare("UPDATE orders SET status=?,updated_at=NOW() WHERE id=?");
                    if ($s->execute([$newStatus, $id])) {
                        sendStatusUpdateEmail($order['customer_email'], $order['customer_name'], $id, $newStatus, $order['scheduled_date'], $order['scheduled_time']);
                        header("Location: orders.php?action=view&message=" . urlencode("Order status updated successfully."));
                        exit;
                    } else {
                        header("Location: orders.php?action=view&message=" . urlencode("Failed to update status."));
                        exit;
                    }
                } catch (PDOException $e) {
                    log_error("Failed to update order status: " . $e->getMessage());
                    header("Location: orders.php?action=view&message=" . urlencode("Failed to update status."));
                    exit;
                }
            } else {
                header("Location: orders.php?action=view&message=" . urlencode("Invalid status selected."));
                exit;
            }
        } else {
            header("Location: orders.php?action=view&message=" . urlencode("Invalid request."));
            exit;
        }
        break;

    case 'delete':
        if ($id > 0) {
            try {
                $s = $pdo->prepare("UPDATE orders SET is_deleted=1,updated_at=NOW() WHERE id=?");
                if ($s->execute([$id])) {
                    header("Location: orders.php?action=view&message=" . urlencode("Order moved to Trash successfully."));
                    exit;
                } else {
                    header("Location: orders.php?action=view&message=" . urlencode("Failed to move order to Trash."));
                    exit;
                }
            } catch (PDOException $e) {
                log_error("Failed to move order to Trash: " . $e->getMessage());
                header("Location: orders.php?action=view&message=" . urlencode("Failed to move order to Trash."));
                exit;
            }
        } else {
            header("Location: orders.php?action=view&message=" . urlencode("Invalid order ID."));
            exit;
        }
        break;

    case 'view_trash':
        if ($user_role !== 'admin') {
            header('HTTP/1.1 403 Forbidden');
            echo "<h1>403 Forbidden</h1><p>You do not have permission to view the Trash.</p>";
            require_once 'includes/footer.php';
            exit;
        }
        try {
            $s = $pdo->prepare("
            SELECT 
              o.id,o.customer_name,o.customer_email,o.customer_phone,o.delivery_address,o.total_amount,o.tip_id,o.tip_amount,
              o.scheduled_date,o.scheduled_time,o.payment_method,o.store_id,o.order_details,o.created_at,o.updated_at,
              o.delivery_user_id,o.status,o.is_deleted,
              t.name AS tip_name,t.percentage AS tip_percentage,t.amount AS tip_fixed_amount
            FROM orders o
            LEFT JOIN tips t ON o.tip_id=t.id
            WHERE o.is_deleted=1
            ORDER BY o.updated_at DESC
            ");
            $s->execute();
            $trash_orders = $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            log_error("Failed to retrieve trashed orders: " . $e->getMessage());
            $trash_orders = [];
            $message = '<div class="alert alert-danger">Failed to retrieve trashed orders.</div>';
        }
        break;

    case 'restore':
        if ($user_role !== 'admin') {
            header('HTTP/1.1 403 Forbidden');
            echo "<h1>403 Forbidden</h1><p>You do not have permission to restore orders.</p>";
            require_once 'includes/footer.php';
            exit;
        }
        if ($id > 0) {
            try {
                $s = $pdo->prepare("UPDATE orders SET is_deleted=0,updated_at=NOW() WHERE id=?");
                if ($s->execute([$id])) {
                    header("Location: orders.php?action=view_trash&message=" . urlencode("Order restored successfully."));
                    exit;
                } else {
                    header("Location: orders.php?action=view_trash&message=" . urlencode("Failed to restore order."));
                    exit;
                }
            } catch (PDOException $e) {
                log_error("Failed to restore order: " . $e->getMessage());
                header("Location: orders.php?action=view_trash&message=" . urlencode("Failed to restore order."));
                exit;
            }
        } else {
            header("Location: orders.php?action=view_trash&message=" . urlencode("Invalid order ID."));
            exit;
        }
        break;

    case 'permanent_delete':
        if ($user_role !== 'admin') {
            header('HTTP/1.1 403 Forbidden');
            echo "<h1>403 Forbidden</h1><p>You do not have permission to permanently delete orders.</p>";
            require_once 'includes/footer.php';
            exit;
        }
        if ($id > 0) {
            try {
                $s = $pdo->prepare("DELETE FROM orders WHERE id=?");
                if ($s->execute([$id])) {
                    header("Location: orders.php?action=view_trash&message=" . urlencode("Order permanently deleted."));
                    exit;
                } else {
                    header("Location: orders.php?action=view_trash&message=" . urlencode("Failed to permanently delete order."));
                    exit;
                }
            } catch (PDOException $e) {
                log_error("Failed to permanently delete order: " . $e->getMessage());
                header("Location: orders.php?action=view_trash&message=" . urlencode("Failed to permanently delete order."));
                exit;
            }
        } else {
            header("Location: orders.php?action=view_trash&message=" . urlencode("Invalid order ID."));
            exit;
        }
        break;

    case 'send_delay_notification':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
            $t = intval($_POST['additional_time'] ?? 0);
            if ($t > 0) {
                try {
                    $s = $pdo->prepare("SELECT * FROM orders WHERE id=? AND is_deleted=0");
                    $s->execute([$id]);
                    $o = $s->fetch(PDO::FETCH_ASSOC);
                    if (!$o) {
                        header("Location: orders.php?action=view&message=" . urlencode("Order not found."));
                        exit;
                    }
                    sendDelayNotificationEmail($o['customer_email'], $o['customer_name'], $id, $t);
                    header("Location: orders.php?action=view&message=" . urlencode("Delay notification sent successfully."));
                    exit;
                } catch (PDOException $e) {
                    log_error("Failed to send delay notification: " . $e->getMessage());
                    header("Location: orders.php?action=view&message=" . urlencode("Failed to send delay notification."));
                    exit;
                }
            } else {
                header("Location: orders.php?action=view&message=" . urlencode("Invalid additional time."));
                exit;
            }
        } else {
            header("Location: orders.php?action=view&message=" . urlencode("Invalid request."));
            exit;
        }
        break;

    case 'assign_delivery':
        if ($user_role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
            $du = (int)($_POST['delivery_user_id'] ?? 0);
            if ($du > 0) {
                try {
                    $s = $pdo->prepare("SELECT email,username FROM users WHERE id=? AND role='delivery' AND is_active=1 LIMIT 1");
                    $s->execute([$du]);
                    $u = $s->fetch(PDO::FETCH_ASSOC);
                    if ($u) {
                        $upd = $pdo->prepare("UPDATE orders SET delivery_user_id=? WHERE id=?");
                        if ($upd->execute([$du, $id])) {
                            notifyDeliveryPerson($u['email'], $u['username'], $id, "Assigned");
                            header("Location: orders.php?action=view&message=" . urlencode('Delivery person assigned successfully.'));
                            exit;
                        } else {
                            header("Location: orders.php?action=view&message=" . urlencode('Failed to assign delivery person.'));
                            exit;
                        }
                    } else {
                        header("Location: orders.php?action=view&message=" . urlencode('Invalid delivery person selected.'));
                        exit;
                    }
                } catch (PDOException $e) {
                    log_error("Failed to assign delivery person: " . $e->getMessage());
                    header("Location: orders.php?action=view&message=" . urlencode('Failed to assign delivery person.'));
                    exit;
                }
            } else {
                header("Location: orders.php?action=view&message=" . urlencode('No delivery person selected.'));
                exit;
            }
        } else {
            header("Location: orders.php?action=view&message=" . urlencode('Unauthorized action.'));
            exit;
        }
        break;

    case 'top_products':
        if ($user_role !== 'admin') {
            header('HTTP/1.1 403 Forbidden');
            echo "<h1>403 Forbidden</h1><p>You do not have permission to view Top Products.</p>";
            require_once 'includes/footer.php';
            exit;
        }
        try {
            $top_products = getTopProducts($pdo);
        } catch (Exception $e) {
            log_error("Failed to retrieve top products: " . $e->getMessage());
            $top_products = [];
            $message = '<div class="alert alert-danger">Failed to retrieve top products.</div>';
        }
        break;

    case 'view':
    default:
        try {
            $all_orders = getAllOrders($pdo);
            $status_orders = array_fill_keys($statuses, []);
            foreach ($all_orders as $o) {
                if (in_array($o['status'], $statuses)) {
                    $status_orders[$o['status']][] = $o;
                } else {
                    $status_orders['New Order'][] = $o;
                }
            }
            $delivery_users = ($user_role === 'admin') ? getDeliveryUsers($pdo) : [];
        } catch (PDOException $e) {
            log_error("Failed to retrieve orders: " . $e->getMessage());
            $status_orders = [];
            $message = '<div class="alert alert-danger">Failed to retrieve orders.</div>';
        }
        break;
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
    <style>
        body {
            background: #f8f9fa;
            font-family: Arial, sans-serif
        }

        .table thead th {
            white-space: nowrap
        }

        .status-section {
            margin-bottom: 30px
        }

        .assign-form {
            display: flex;
            align-items: center
        }

        .assign-form select {
            margin-right: 5px
        }
    </style>
</head>

<body class="p-3">
    <div class="container-fluid">
        <nav class="mb-4">
            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <a class="nav-link<?= ($action !== 'view_trash' && $action !== 'top_products') ? ' active' : '' ?>"
                        href="orders.php?action=view">View Orders</a>
                </li>
                <?php if ($user_role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= ($action === 'view_trash') ? ' active' : '' ?>"
                            href="orders.php?action=view_trash">Trash</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= ($action === 'top_products') ? ' active' : '' ?>"
                            href="orders.php?action=top_products">Top 10 Products</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

        <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_GET['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif (!empty($message)): ?>
            <?= $message ?>
        <?php endif; ?>

        <h2 class="mb-4">Orders Management (Role: <?= htmlspecialchars($user_role) ?>)</h2>

        <?php
        switch ($action) {
            case 'view_details': ?>
                <div class="card">
                    <div class="card-header">
                        <h4>Order #<?= htmlspecialchars($order['id']) ?> Details</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Customer Name:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
                                <p><strong>Email:</strong> <?= htmlspecialchars($order['customer_email']) ?></p>
                                <p><strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone']) ?></p>
                                <p><strong>Address:</strong> <?= htmlspecialchars($order['delivery_address']) ?></p>
                                <p><strong>Total Amount:</strong> <?= number_format($order['total_amount'], 2) ?>€</p>
                                <p><strong>Tip:</strong>
                                    <?php
                                    if (!empty($order['tip_name'])) {
                                        $tip_display = $order['tip_percentage'] !== null
                                            ? htmlspecialchars($order['tip_percentage']) . '%'
                                            : number_format($order['tip_fixed_amount'], 2) . '€';
                                        echo "<span class='badge bg-info' data-bs-toggle='tooltip' title='Selected Tip: $tip_display'>"
                                            . htmlspecialchars($order['tip_name']) . "</span>";
                                    } else {
                                        echo "N/A";
                                    }
                                    ?>
                                </p>
                                <p><strong>Tip Amount:</strong> <?= number_format($order['tip_amount'], 2) ?>€</p>
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
                                        $delivery_user = $st->fetch(PDO::FETCH_ASSOC);
                                        echo $delivery_user ? htmlspecialchars($delivery_user['username']) : "Unassigned";
                                    } else {
                                        echo "Unassigned";
                                    }
                                    ?>
                                </p>
                                <p><strong>Created At:</strong> <?= htmlspecialchars($order['created_at']) ?></p>
                                <p><strong>Updated At:</strong> <?= htmlspecialchars($order['updated_at'] ?? 'N/A') ?></p>
                            </div>

                            <div class="col-md-6">
                                <h5>Order Items</h5>
                                <?php
                                $d = json_decode($order['order_details'], true);
                                if ($d && isset($d['items']) && is_array($d['items'])):
                                    foreach ($d['items'] as $item): ?>
                                        <div class="card mb-3">
                                            <div class="card-header">
                                                <strong><?= htmlspecialchars($item['name']) ?></strong>
                                                (Quantity: <?= htmlspecialchars($item['quantity']) ?>)
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <?php if (!empty($item['image_url'])): ?>
                                                            <img src="<?= htmlspecialchars($item['image_url']) ?>"
                                                                alt="<?= htmlspecialchars($item['name']) ?>"
                                                                class="img-fluid rounded">
                                                        <?php else: ?>
                                                            <img src="default-image.png"
                                                                alt="No Image"
                                                                class="img-fluid rounded">
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="col-md-8">
                                                        <p><strong>Description:</strong> <?= htmlspecialchars($item['description'] ?? 'N/A') ?></p>
                                                        <p><strong>Size:</strong> <?= htmlspecialchars($item['size']) ?> (<?= number_format($item['size_price'], 2) ?>€)</p>

                                                        <!-- CHANGED: Display quantity for extras -->
                                                        <?php if (!empty($item['extras'])): ?>
                                                            <p><strong>Extras:</strong></p>
                                                            <ul>
                                                                <?php foreach ($item['extras'] as $extra): ?>
                                                                    <li>
                                                                        <?= htmlspecialchars($extra['name']) ?>
                                                                        x<?= (int)($extra['quantity'] ?? 1) ?>
                                                                        (<?= number_format($extra['price'], 2) ?>€ each)
                                                                        = <?= number_format(($extra['price'] * ($extra['quantity'] ?? 1)), 2) ?>€
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php endif; ?>

                                                        <!-- CHANGED: Display quantity for sauces -->
                                                        <?php if (!empty($item['sauces'])): ?>
                                                            <p><strong>Sauces:</strong></p>
                                                            <ul>
                                                                <?php foreach ($item['sauces'] as $sauce): ?>
                                                                    <li>
                                                                        <?= htmlspecialchars($sauce['name']) ?>
                                                                        x<?= (int)($sauce['quantity'] ?? 1) ?>
                                                                        (<?= number_format($sauce['price'], 2) ?>€ each)
                                                                        = <?= number_format(($sauce['price'] * ($sauce['quantity'] ?? 1)), 2) ?>€
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php endif; ?>

                                                        <!-- CHANGED: Display quantity for dresses -->
                                                        <?php if (!empty($item['dresses'])): ?>
                                                            <p><strong>Dresses:</strong></p>
                                                            <ul>
                                                                <?php foreach ($item['dresses'] as $dress): ?>
                                                                    <li>
                                                                        <?= htmlspecialchars($dress['name']) ?>
                                                                        x<?= (int)($dress['quantity'] ?? 1) ?>
                                                                        (<?= number_format($dress['price'], 2) ?>€ each)
                                                                        = <?= number_format(($dress['price'] * ($dress['quantity'] ?? 1)), 2) ?>€
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php endif; ?>

                                                        <?php if (!empty($item['drink'])): ?>
                                                            <p><strong>Drink:</strong>
                                                                <?= htmlspecialchars($item['drink']['name']) ?>
                                                                (<?= number_format($item['drink']['price'], 2) ?>€)
                                                            </p>
                                                        <?php endif; ?>

                                                        <?php if (!empty($item['special_instructions'])): ?>
                                                            <p><strong>Special Instructions:</strong>
                                                                <?= htmlspecialchars($item['special_instructions']) ?>
                                                            </p>
                                                        <?php endif; ?>

                                                        <p><strong>Unit Price:</strong>
                                                            <?= number_format($item['unit_price'], 2) ?>€</p>
                                                        <p><strong>Total Price:</strong>
                                                            <?= number_format($item['total_price'], 2) ?>€</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach;
                                else: ?>
                                    <p>No items found in this order.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="orders.php?action=view" class="btn btn-secondary mt-3">Back to Orders</a>
                    </div>
                </div>
            <?php
                break;

            case 'update_status_form': ?>
                <div class="card">
                    <div class="card-header">
                        <h4>Update Status for Order #<?= htmlspecialchars($order['id']) ?></h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="orders.php?action=update_status&id=<?= htmlspecialchars($order['id']) ?>">
                            <div class="mb-3">
                                <label for="status" class="form-label">Select New Status</label>
                                <select name="status" id="status" class="form-select" required>
                                    <option value="">-- Select Status --</option>
                                    <?php foreach ($statuses as $so): ?>
                                        <option value="<?= htmlspecialchars($so) ?>"
                                            <?= ($order['status'] === $so) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($so) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Status</button>
                            <a href="orders.php?action=view" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            <?php
                break;

            case 'view_trash': ?>
                <div class="card">
                    <div class="card-header">
                        <h4>Trash</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($trash_orders)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover table-bordered align-middle data-table">
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
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($trash_orders as $o): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($o['id']) ?></td>
                                                <td><?= htmlspecialchars($o['customer_name']) ?></td>
                                                <td><?= htmlspecialchars($o['customer_email']) ?></td>
                                                <td><?= htmlspecialchars($o['customer_phone']) ?></td>
                                                <td><?= htmlspecialchars($o['delivery_address']) ?></td>
                                                <td><?= number_format($o['total_amount'], 2) ?>€</td>
                                                <td>
                                                    <?php
                                                    if (!empty($o['tip_name'])) {
                                                        $d = $o['tip_percentage'] !== null
                                                            ? htmlspecialchars($o['tip_percentage']) . '%'
                                                            : number_format($o['tip_fixed_amount'], 2) . '€';
                                                        echo "<span class='badge bg-info' data-bs-toggle='tooltip' title='Selected Tip: $d'>"
                                                            . htmlspecialchars($o['tip_name']) . "</span>";
                                                    } else {
                                                        echo "N/A";
                                                    }
                                                    ?>
                                                </td>
                                                <td><?= number_format($o['tip_amount'], 2) ?>€</td>
                                                <td><?= htmlspecialchars($o['scheduled_date'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($o['scheduled_time'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($o['created_at']) ?></td>
                                                <td><?= htmlspecialchars($o['status']) ?></td>
                                                <td>
                                                    <a href="orders.php?action=restore&id=<?= $o['id'] ?>"
                                                        class="btn btn-sm btn-success me-1"
                                                        data-bs-toggle="tooltip" title="Restore Order">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </a>
                                                    <a href="orders.php?action=permanent_delete&id=<?= $o['id'] ?>"
                                                        class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Are you sure you want to permanently delete this order? This action cannot be undone.');"
                                                        data-bs-toggle="tooltip" title="Permanently Delete Order">
                                                        <i class="bi bi-trash3-fill"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>No orders in Trash.</p>
                        <?php endif; ?>
                        <a href="orders.php?action=view" class="btn btn-secondary mt-3">Back to Orders</a>
                    </div>
                </div>
            <?php
                break;

            case 'top_products': ?>
                <div class="card">
                    <div class="card-header">
                        <h4>Top 10 Products</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($top_products)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover table-bordered align-middle data-table">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Rank</th>
                                            <th>Product Name</th>
                                            <th>Quantity Sold</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $r = 1;
                                        foreach ($top_products as $p => $q): ?>
                                            <tr>
                                                <td><?= $r ?></td>
                                                <td><?= htmlspecialchars($p) ?></td>
                                                <td><?= $q ?></td>
                                            </tr>
                                        <?php $r++;
                                        endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>No products found.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                break;

            case 'view':
            default:
                foreach ($statuses as $st): ?>
                    <div class="status-section">
                        <h4><?= htmlspecialchars($st) ?> Orders</h4>
                        <?php if (!empty($status_orders[$st])): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover table-bordered align-middle data-table">
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
                                            <th>Status</th>
                                            <?php if ($user_role === 'admin'): ?>
                                                <th>Assign Delivery Person</th>
                                            <?php endif; ?>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($status_orders[$st] as $o) {
                                            $ti = 'N/A';
                                            if (!empty($o['tip_name'])) {
                                                $d = $o['tip_percentage'] !== null
                                                    ? htmlspecialchars($o['tip_percentage']) . '%'
                                                    : number_format($o['tip_fixed_amount'], 2) . '€';
                                                $ti = "<span class='badge bg-info' data-bs-toggle='tooltip' title='Selected Tip: $d'>"
                                                    . htmlspecialchars($o['tip_name']) . "</span>";
                                            }
                                            echo "<tr>
                                        <td>" . htmlspecialchars($o['id']) . "</td>
                                        <td>" . htmlspecialchars($o['customer_name']) . "</td>
                                        <td>" . htmlspecialchars($o['customer_email']) . "</td>
                                        <td>" . htmlspecialchars($o['customer_phone']) . "</td>
                                        <td>" . htmlspecialchars($o['delivery_address']) . "</td>
                                        <td>" . number_format($o['total_amount'], 2) . "€</td>
                                        <td>$ti</td>
                                        <td>" . number_format($o['tip_amount'], 2) . "€</td>
                                        <td>" . htmlspecialchars($o['scheduled_date'] ?? 'N/A') . "</td>
                                        <td>" . htmlspecialchars($o['scheduled_time'] ?? 'N/A') . "</td>
                                        <td>" . htmlspecialchars($o['created_at']) . "</td>
                                        <td>" . htmlspecialchars($o['status']) . "</td>";

                                            // Delivery assignment if admin
                                            if ($user_role === 'admin') {
                                                echo "<td>
                                                <form method='POST' action='orders.php?action=assign_delivery&id=" . $o['id'] . "' class='assign-form'>
                                                    <select name='delivery_user_id' class='form-select form-select-sm me-2' required>
                                                        <option value=''>Assign</option>";
                                                foreach ($delivery_users as $du) {
                                                    echo "<option value='" . htmlspecialchars($du['id']) . "'"
                                                        . ($o['delivery_user_id'] == $du['id'] ? ' selected' : '') . ">"
                                                        . htmlspecialchars($du['username']) . "</option>";
                                                }
                                                echo "</select>
                                                  <button type='submit' class='btn btn-sm btn-primary'>Assign</button>
                                                </form>
                                            </td>";
                                            }

                                            echo "<td>
                                            <a href='orders.php?action=view_details&id=" . $o['id'] . "' 
                                               class='btn btn-sm btn-info me-1' 
                                               data-bs-toggle='tooltip' title='View Details'>
                                               <i class='bi bi-eye'></i>
                                            </a>
                                            <a href='orders.php?action=update_status_form&id=" . $o['id'] . "' 
                                               class='btn btn-sm btn-warning me-1' 
                                               data-bs-toggle='tooltip' title='Update Status'>
                                               <i class='bi bi-pencil'></i>
                                            </a>";
                                            if ($user_role === 'admin') {
                                                echo "<a href='orders.php?action=delete&id=" . $o['id'] . "' 
                                                   class='btn btn-sm btn-danger me-1' 
                                                   onclick='return confirm(\"Are you sure you want to move this order to Trash?\");' 
                                                   data-bs-toggle='tooltip' 
                                                   title='Move to Trash'>
                                                   <i class='bi bi-trash'></i>
                                                </a>
                                                <button type='button' 
                                                        class='btn btn-sm btn-secondary' 
                                                        data-bs-toggle='modal' 
                                                        data-bs-target='#delayModal" . $o['id'] . "' 
                                                        title='Send Delay Notification'>
                                                        <i class='bi bi-clock'></i>
                                                </button>";
                                            }
                                            echo "</td></tr>";

                                            // Delay modal if admin
                                            if ($user_role === 'admin') {
                                                echo "<div class='modal fade' id='delayModal" . $o['id'] . "' tabindex='-1' aria-labelledby='delayModalLabel" . $o['id'] . "' aria-hidden='true'>
                                                <div class='modal-dialog modal-dialog-centered'>
                                                    <div class='modal-content'>
                                                        <div class='modal-header bg-secondary text-white'>
                                                            <h5 class='modal-title' id='delayModalLabel" . $o['id'] . "'>
                                                                Send Delay Notification for Order #" . htmlspecialchars($o['id']) . "
                                                            </h5>
                                                            <button type='button' class='btn-close btn-close-white' data-bs-dismiss='modal'></button>
                                                        </div>
                                                        <form method='POST' action='orders.php?action=send_delay_notification&id=" . $o['id'] . "'>
                                                            <div class='modal-body'>
                                                                <div class='mb-3'>
                                                                    <label for='additional_time_" . $o['id'] . "' class='form-label'>
                                                                        Additional Time (hours)
                                                                    </label>
                                                                    <input type='number' class='form-control' 
                                                                           id='additional_time_" . $o['id'] . "' 
                                                                           name='additional_time' min='1' required>
                                                                </div>
                                                                <p>Send delay notification?</p>
                                                            </div>
                                                            <div class='modal-footer'>
                                                                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>No</button>
                                                                <button type='submit' class='btn btn-warning'>Yes, Send</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>";
                                            }
                                        } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>No <?= htmlspecialchars($st) ?> orders found.</p>
                        <?php endif; ?>
                    </div>
        <?php endforeach;
                break;
        }
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.data-table').DataTable({
                "paging": true,
                "searching": true,
                "ordering": true,
                "info": false,
                "responsive": true,
                "language": {
                    "emptyTable": "No data available in table",
                    "search": "Search orders:"
                }
            });

            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(el) {
                return new bootstrap.Tooltip(el);
            });

            let lastOrderId = <?= !empty($all_orders) ? max(array_column($all_orders, 'id')) : 0 ?>;

            function fetchNewOrders() {
                $.ajax({
                    url: 'get_new_orders.php',
                    method: 'GET',
                    data: {
                        last_order_id: lastOrderId
                    },
                    dataType: 'json',
                    success: function(r) {
                        if (r.status === 'success') {
                            const newOrders = r.new_orders;
                            if (newOrders.length > 0) {
                                newOrders.forEach(o => {
                                    if (o.id > lastOrderId) lastOrderId = o.id;
                                    const body = $('table:contains("New Order")').find('tbody');
                                    const row = `
                                    <tr>
                                        <td>${o.id}</td>
                                        <td>${escapeHtml(o.customer_name)}</td>
                                        <td>${escapeHtml(o.customer_email)}</td>
                                        <td>${escapeHtml(o.customer_phone)}</td>
                                        <td>${escapeHtml(o.delivery_address)}</td>
                                        <td>${parseFloat(o.total_amount).toFixed(2)}€</td>
                                        <td>${o.tip_id?'Tip Badge or Info':'N/A'}</td>
                                        <td>${parseFloat(o.tip_amount).toFixed(2)}€</td>
                                        <td>${escapeHtml(o.scheduled_date||'N/A')}</td>
                                        <td>${escapeHtml(o.scheduled_time||'N/A')}</td>
                                        <td>${escapeHtml(o.created_at)}</td>
                                        <td>${escapeHtml(o.status)}</td>
                                    </tr>`;
                                    body.prepend(row);
                                });
                            }
                        } else {
                            console.error('Error fetching new orders:', r.message);
                        }
                    },
                    error: function(x, s, e) {
                        console.error('Error fetching new orders:', e);
                    }
                });
            }

            function escapeHtml(txt) {
                return $('<div>').text(txt).html();
            }

            setInterval(fetchNewOrders, 5000);
        });
    </script>
    <?php ob_end_flush(); ?>
</body>

</html>