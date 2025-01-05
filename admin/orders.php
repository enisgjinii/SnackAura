<?php
ob_start();
require_once 'includes/db_connect.php';
require_once 'includes/header.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define('ERROR_LOG_FILE', __DIR__ . '/errors.md');

function log_error($m, $c = '')
{
    $t = date('Y-m-d H:i:s');
    file_put_contents(
        ERROR_LOG_FILE,
        "### [$t] Error\n\n**Message:** " . htmlspecialchars($m, ENT_QUOTES, 'UTF-8') . "\n"
            . ($c ? "**Context:** " . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . "\n" : '')
            . "---\n\n",
        FILE_APPEND | LOCK_EX
    );
}

function sendEmail($to, $toName, $sub, $body)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'egjini17@gmail.com';
        $mail->Password   = 'axnjsldfudhohipv';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->setFrom('egjini17@gmail.com', 'Yumiis');
        $mail->addAddress($to, $toName);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $sub;
        $mail->Body    = $body;
        $mail->send();
    } catch (Exception $e) {
        log_error("Mail Error: " . $mail->ErrorInfo, "Sending Email to: $to");
    }
}

function sendStatusUpdateEmail($e, $n, $oid, $st, $sd = null, $stm = null)
{
    $sub = "Order #$oid Status Updated";
    $i = ($sd && $stm) ? "<p><strong>Scheduled Delivery:</strong> $sd at $stm</p>" : '';
    $b = "<html><body><h2>Hello, $n</h2>
         <p>Your order #$oid status is now <strong>$st</strong>.</p>
         $i
         <p>Thank you for choosing Yumiis!</p></body></html>";
    sendEmail($e, $n, $sub, $b);
}

function sendDelayNotificationEmail($e, $n, $oid, $t)
{
    $sub = "Delay Notification for Order #$oid";
    $b = "<html><body><h2>Hello, $n</h2>
         <p>Your order #$oid may be delayed by $t hour(s).</p>
         <p>Please let us know if this is acceptable.</p>
         </body></html>";
    sendEmail($e, $n, $sub, $b);
}

function notifyDeliveryPerson($e, $n, $oid, $st)
{
    $sub = "New Order Assigned - Order #$oid";
    $b = "<html><body><h2>Hello, $n</h2>
         <p>You have been assigned to order #$oid with status $st.</p>
         <p>Please proceed as required.</p></body></html>";
    sendEmail($e, $n, $sub, $b);
}

set_exception_handler(function ($e) {
    log_error("Uncaught Exception: " . $e->getMessage(), "File: {$e->getFile()} Line: {$e->getLine()}");
    header("Location: orders.php?action=view&message=unknown_error");
    exit;
});

set_error_handler(function ($sv, $m, $f, $l) {
    if (!(error_reporting() & $sv)) return;
    throw new ErrorException($m, 0, $sv, $f, $l);
});

function getDeliveryUsers($pdo)
{
    $q = $pdo->prepare("SELECT id,username,email FROM users WHERE role='delivery' AND is_active=1");
    $q->execute();
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

$user_role = $_SESSION['role'] ?? 'admin' ?? 'super-admin';
$user_id   = $_SESSION['user_id'] ?? 1;
$action    = $_GET['action']     ?? 'view';
$id        = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message   = '';

$allowed_actions = [
    'super-admin' => ['view', 'view_details', 'send_notification', 'send_delay_notification', 'delete', 'assign_delivery', 'update_status_form', 'update_status', 'view_trash', 'restore', 'permanent_delete', 'top_products'],
    'admin'    => ['view', 'view_details', 'send_notification', 'send_delay_notification', 'delete', 'assign_delivery', 'update_status_form', 'update_status', 'view_trash', 'restore', 'permanent_delete', 'top_products'],
    'delivery' => ['view', 'view_details', 'send_notification', 'send_delay_notification', 'update_status_form', 'update_status']
];

if (!isset($allowed_actions[$user_role]) || !in_array($action, $allowed_actions[$user_role])) {
    header('HTTP/1.1 403 Forbidden');
    echo "<h1>403 Forbidden</h1><p>Role: " . htmlspecialchars($user_role) . "</p>";
    require_once 'includes/footer.php';
    exit;
}

$statuses = ['New Order', 'Kitchen', 'On the Way', 'Delivered', 'Canceled'];

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
                    $s = $pdo->prepare("UPDATE orders SET status=?,updated_at=NOW() WHERE id=?");
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
            $s = $pdo->prepare("UPDATE orders SET is_deleted=1,updated_at=NOW() WHERE id=?");
            if ($s->execute([$id])) {
                header("Location: orders.php?action=view&message=Order moved to Trash");
                exit;
            } else {
                header("Location: orders.php?action=view&message=Could not move to Trash");
                exit;
            }
        case 'view_trash':
            if ($user_role !== 'admin' || $user_role !== 'super-admin') {
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
            if ($user_role !== 'admin' || $user_role !== 'super-admin') {
                header('HTTP/1.1 403 Forbidden');
                exit;
            }
            if ($id <= 0) {
                header("Location: orders.php?action=view_trash&message=Invalid ID");
                exit;
            }
            $r = $pdo->prepare("UPDATE orders SET is_deleted=0,updated_at=NOW() WHERE id=?");
            if ($r->execute([$id])) {
                header("Location: orders.php?action=view_trash&message=Order restored");
                exit;
            } else {
                header("Location: orders.php?action=view_trash&message=Restore failed");
                exit;
            }
        case 'permanent_delete':
            if ($user_role !== 'admin' || $user_role !== 'super-admin') {
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
            if ($user_role !== 'admin' || $user_role !== 'super-admin') {
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
            if ($user_role !== 'admin' || $user_role !== 'super-admin') {
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
            $delivery_users = $user_role === 'admin' || $user_role === 'super-admin' ? getDeliveryUsers($pdo) : [];
            break;
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
        }

        .table thead th {
            white-space: nowrap;
        }

        .status-section {
            margin-bottom: 30px;
        }

        .assign-form {
            display: flex;
            align-items: center;
        }

        .assign-form select {
            margin-right: 5px;
        }

        .badge-status {
            font-size: 0.9rem;
            padding: 0.4em 0.6em;
            border-radius: 0.25rem;
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

        .modal-invoice-header {
            background-color: #333;
            color: #fff;
        }

        .modal-invoice-header h5 {
            margin: 0;
            font-size: 1.25rem;
        }

        .invoice-container {
            padding: 1rem;
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .invoice-logo {
            max-width: 120px;
            height: auto;
        }

        .invoice-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
        }

        .store-info {
            line-height: 1.4;
            margin-bottom: 1rem;
        }

        .customer-info {
            margin-bottom: 1rem;
        }

        .invoice-items table {
            width: 100%;
            border-collapse: collapse;
        }

        .invoice-items th,
        .invoice-items td {
            padding: 0.5rem;
            border: 1px solid #ddd;
        }

        .invoice-items th {
            background-color: #f2f2f2;
        }

        .invoice-summary {
            margin-top: 1rem;
            text-align: right;
        }

        .invoice-summary p {
            margin: 0;
        }

        .invoice-footer {
            margin-top: 1rem;
            text-align: center;
            font-size: 0.9rem;
            color: #666;
        }
    </style>
</head>

<body class="p-3">
    <div class="container-fluid">
        <nav class="mb-4">
            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <a class="nav-link <?= ($action !== 'view_trash' && $action !== 'top_products') ? 'active' : '' ?>" href="orders.php?action=view">View Orders</a>
                </li>
                <?php if ($user_role === 'admin' || $user_role === 'super-admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($action === 'view_trash' ? 'active' : '') ?>" href="orders.php?action=view_trash">Trash</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($action === 'top_products' ? 'active' : '') ?>" href="orders.php?action=top_products">Top 10 Products</a>
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
        if ($action === 'view_details' && !empty($order)): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Order #<?= htmlspecialchars($order['id']) ?> Details</h4>
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
                                <?php if (!empty($order['tip_name'])) {
                                    $ti = $order['tip_percentage'] !== null
                                        ? htmlspecialchars($order['tip_percentage']) . '%'
                                        : number_format($order['tip_fixed_amount'], 2) . '€';
                                    echo "<span class='badge bg-info'>" . htmlspecialchars($order['tip_name']) . "</span> ($ti)";
                                } else {
                                    echo "N/A";
                                } ?>
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
                                if ($order['delivery_user_id'] && $user_role === 'admin' || $user_role === 'super-admin') {
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
                        <div class="col-md-6">
                            <h5>Order Items</h5>
                            <?php
                            $details = json_decode($order['order_details'], true);
                            $items   = $details['items'] ?? [];
                            if ($items):
                                foreach ($items as $item): ?>
                                    <div class="card mb-3">
                                        <div class="card-header"><strong><?= htmlspecialchars($item['name']) ?></strong> (Quantity: <?= htmlspecialchars($item['quantity']) ?>)</div>
                                        <div class="card-body">
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
                                <?php endforeach;
                            else: ?>
                                <p>No items in this order.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="button" class="btn btn-dark mt-3" data-bs-toggle="modal" data-bs-target="#invoiceModal">
                        <i class="bi bi-file-earmark-text"></i> View Invoice
                    </button>
                    <a href="orders.php?action=view" class="btn btn-secondary mt-3">Back</a>
                </div>
            </div>
            <div class="modal fade" id="invoiceModal" tabindex="-1">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header modal-invoice-header">
                            <h5 class="modal-title">Invoice for Order #<?= htmlspecialchars($order['id']) ?></h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <?php
                            $invTip  = number_format($order['tip_amount'], 2);
                            $invDisc = number_format($order['coupon_discount'] ?? 0, 2);
                            $subtotal = ($order['total_amount'] + (float)$invDisc - (float)$order['tip_amount']);
                            $invSubtotal = number_format($subtotal, 2);
                            ?>
                            <div class="invoice-container">
                                <div class="invoice-header">
                                    <div>
                                        <img src="store_logo.png" alt="Store Logo" class="invoice-logo">
                                    </div>
                                    <div>
                                        <h3 class="invoice-title">INVOICE</h3>
                                    </div>
                                </div>
                                <div class="store-info">
                                    <strong>Yumiis Restaurant</strong><br>
                                    123 Main St<br>
                                    Some City, 12345<br>
                                    +1 (555) 123-4567<br>
                                    contact@yumiis.com
                                </div>
                                <div class="customer-info">
                                    <p>
                                        <strong>Bill To:</strong> <?= htmlspecialchars($order['customer_name']) ?><br>
                                        <?= htmlspecialchars($order['delivery_address']) ?><br>
                                        <?= htmlspecialchars($order['customer_email']) ?>
                                    </p>
                                    <p><strong>Order Date:</strong> <?= htmlspecialchars($order['created_at']) ?></p>
                                </div>
                                <div class="invoice-items">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Qty</th>
                                                <th>Unit(€)</th>
                                                <th>Total(€)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if (!empty($items)) {
                                                foreach ($items as $it) {
                                                    $nm  = htmlspecialchars($it['name'] ?? 'Item');
                                                    $qty = intval($it['quantity'] ?? 1);
                                                    $uni = number_format($it['unit_price'] ?? 0, 2);
                                                    $tpr = number_format($it['total_price'] ?? 0, 2);
                                                    echo "<tr><td>$nm</td><td>$qty</td><td>$uni</td><td>$tpr</td></tr>";
                                                }
                                            } else {
                                                echo "<tr><td colspan='4'>No items</td></tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="invoice-summary">
                                    <p><strong>Subtotal:</strong> <?= $invSubtotal ?> €</p>
                                    <p><strong>Tip:</strong> <?= $invTip ?> €</p>
                                    <p><strong>Coupon Discount:</strong> <?= $invDisc ?> €</p>
                                    <p><strong>Total Amount:</strong> <?= number_format($order['total_amount'], 2) ?> €</p>
                                </div>
                                <div class="invoice-footer">
                                    <p>Thank you for your order!</p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        elseif ($action === 'update_status_form' && !empty($order)): ?>
            <div class="card">
                <div class="card-header bg-warning">
                    <h4 class="mb-0">Update Status #<?= htmlspecialchars($order['id']) ?></h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="orders.php?action=update_status&id=<?= htmlspecialchars($order['id']) ?>">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?= htmlspecialchars($s) ?>" <?= ($order['status'] === $s ? 'selected' : '') ?>>
                                        <?= htmlspecialchars($s) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-primary">Update</button>
                        <a href="orders.php?action=view" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        <?php
        elseif ($action === 'view_trash' && isset($trash_orders)): ?>
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h4 class="mb-0">Trashed Orders</h4>
                </div>
                <div class="card-body">
                    <?php if ($trash_orders): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-bordered align-middle data-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Address</th>
                                        <th>Total(€)</th>
                                        <th>Tip</th>
                                        <th>Tip(€)</th>
                                        <th>Scheduled Date</th>
                                        <th>Scheduled Time</th>
                                        <th>Created</th>
                                        <th>Status</th>
                                        <th>Coupon Code</th>
                                        <th>Coupon Discount(€)</th>
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
                                            <td><?php
                                                if (!empty($tr['tip_name'])) {
                                                    $xx = $tr['tip_percentage'] !== null ? htmlspecialchars($tr['tip_percentage']) . '%' : number_format($tr['tip_fixed_amount'], 2) . '€';
                                                    echo "<span class='badge bg-info'>" . htmlspecialchars($tr['tip_name']) . "</span> ($xx)";
                                                } else echo 'N/A';
                                                ?></td>
                                            <td><?= number_format($tr['tip_amount'], 2) ?></td>
                                            <td><?= htmlspecialchars($tr['scheduled_date'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($tr['scheduled_time'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($tr['created_at']) ?></td>
                                            <td><?= htmlspecialchars($tr['status']) ?></td>
                                            <td><?= htmlspecialchars($tr['coupon_code'] ?? '') ?></td>
                                            <td><?= number_format(($tr['coupon_discount'] ?? 0), 2) ?></td>
                                            <td>
                                                <a href="orders.php?action=restore&id=<?= $tr['id'] ?>" class="btn btn-sm btn-success me-1"><i class="bi bi-arrow-counterclockwise"></i></a>
                                                <a href="orders.php?action=permanent_delete&id=<?= $tr['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to permanently delete?');"><i class="bi bi-trash3-fill"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p>No trashed orders.</p>
                    <?php endif; ?>
                    <a href="orders.php?action=view" class="btn btn-secondary mt-3">Back</a>
                </div>
            </div>
        <?php
        elseif ($action === 'top_products' && isset($top_products)): ?>
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0">Top 10 Products</h4>
                </div>
                <div class="card-body">
                    <?php if ($top_products): ?>
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
                <div class="status-section">
                    <h4 class="mb-3">
                        <?= htmlspecialchars($st) ?> Orders
                        <?php
                        $badge = match ($st) {
                            'New Order'  => 'badge-new',
                            'Kitchen'    => 'badge-kitchen',
                            'On the Way' => 'badge-on-way',
                            'Delivered'  => 'badge-delivered',
                            'Canceled'   => 'badge-canceled',
                            default      => 'badge-secondary',
                        };
                        ?>
                        <span class="badge badge-status <?= $badge ?>"><?= $st ?></span>
                    </h4>
                    <?php if (!empty($status_orders[$st])): ?>
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
                                        <th>Scheduled Date</th>
                                        <th>Scheduled Time</th>
                                        <th>Created</th>
                                        <th>Status</th>
                                        <?php if ($user_role === 'admin' || $user_role === 'super-admin'): ?>
                                            <th>Assign Delivery</th>
                                        <?php endif; ?>
                                        <th>Coupon Code</th>
                                        <th>Coupon Discount(€)</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($status_orders[$st] as $o):
                                        $tipBadge = 'N/A';
                                        if (!empty($o['tip_name'])) {
                                            $val = $o['tip_percentage'] !== null
                                                ? htmlspecialchars($o['tip_percentage']) . '%'
                                                : number_format($o['tip_fixed_amount'], 2) . '€';
                                            $tipBadge = "<span class='badge bg-info'>" . htmlspecialchars($o['tip_name']) . "</span> ($val)";
                                        } ?>
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
                                            <?php if ($user_role === 'admin' || $user_role === 'super-admin'): ?>
                                                <td>
                                                    <form method="POST" action="orders.php?action=assign_delivery&id=<?= $o['id'] ?>" class="assign-form">
                                                        <select name="delivery_user_id" class="form-select form-select-sm me-2" required>
                                                            <option value="">Choose</option>
                                                            <?php foreach ($delivery_users as $du): ?>
                                                                <option value="<?= htmlspecialchars($du['id']) ?>" <?= ($o['delivery_user_id'] == $du['id'] ? 'selected' : '') ?>><?= htmlspecialchars($du['username']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-person-check"></i></button>
                                                    </form>
                                                </td>
                                            <?php endif; ?>
                                            <td><?= htmlspecialchars($o['coupon_code'] ?? '') ?></td>
                                            <td><?= number_format(($o['coupon_discount'] ?? 0), 2) ?></td>
                                            <td>
                                                <a href="orders.php?action=view_details&id=<?= $o['id'] ?>" class="btn btn-sm btn-info me-1"><i class="bi bi-eye"></i></a>
                                                <a href="orders.php?action=update_status_form&id=<?= $o['id'] ?>" class="btn btn-sm btn-warning me-1"><i class="bi bi-pencil"></i></a>
                                                <?php if ($user_role === 'admin' || $user_role === 'super-admin'): ?>
                                                    <a href="orders.php?action=delete&id=<?= $o['id'] ?>" class="btn btn-sm btn-danger me-1" onclick="return confirm('Move to trash?');"><i class="bi bi-trash"></i></a>
                                                    <button type="button" class="btn btn-sm btn-secondary me-1" data-bs-toggle="modal" data-bs-target="#delayModal<?= $o['id'] ?>"><i class="bi bi-clock"></i></button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php if ($user_role === 'admin' || $user_role === 'super-admin'): ?>
                                            <div class="modal fade" id="delayModal<?= $o['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-secondary text-white">
                                                            <h5 class="modal-title">Delay Notification for Order #<?= htmlspecialchars($o['id']) ?></h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST" action="orders.php?action=send_delay_notification&id=<?= $o['id'] ?>">
                                                            <div class="modal-body">
                                                                <div class="mb-3">
                                                                    <label for="additional_time_<?= $o['id'] ?>" class="form-label">Additional Time (hours)</label>
                                                                    <input type="number" class="form-control" id="additional_time_<?= $o['id'] ?>" name="additional_time" min="1" required>
                                                                </div>
                                                                <p>Send Delay Notification?</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-warning">Send</button>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/datetime/1.4.0/js/dataTables.dateTime.min.js"></script>
    <script>
        $(function() {
            $('.data-table').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                info: true,
                responsive: true,
                language: {
                    emptyTable: "No data available",
                    search: "Search orders:"
                }
            });
            <?php if (!empty($all_orders)): ?>
                let lastId = <?= max(array_column($all_orders, 'id')) ?>;
                setInterval(() => {
                    $.get('get_new_orders.php', {
                        last_order_id: lastId
                    }, r => {
                        if (r.status === 'success' && r.new_orders.length > 0) {
                            r.new_orders.forEach(o => {
                                if (o.id > lastId) lastId = o.id;
                                console.log('New order found:', o);
                            });
                        }
                    }, 'json').fail(e => console.error('Error:', e));
                }, 5000);
            <?php endif; ?>
        });
    </script>
    <?php ob_end_flush(); ?>
</body>

</html>