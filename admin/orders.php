<?php
ob_start();
require_once 'includes/db_connect.php';
require_once 'includes/header.php';
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
define('ERROR_LOG_FILE', __DIR__ . '/errors.md');
function log_error($message, $context = '', $level = 'ERROR')
{
    $time = date('Y-m-d H:i:s');
    $entry = "### [$time] [$level]\n\n**Message:** " . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "\n";
    if ($context) {
        $entry .= "**Context:** " . htmlspecialchars($context, ENT_QUOTES, 'UTF-8') . "\n";
    }
    $entry .= "---\n\n";
    file_put_contents(ERROR_LOG_FILE, $entry, FILE_APPEND | LOCK_EX);
}
function redirect_with_message($code, $action = 'view', $extra = '')
{
    $extraParam = $extra ? '&err=' . urlencode($extra) : '';
    header("Location: orders.php?action={$action}&message=" . urlencode($code) . $extraParam);
    exit;
}
function sendEmail($to, $toName, $subject, $body)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USERNAME');
        $mail->Password   = getenv('SMTP_PASSWORD');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->setFrom('example@gmail.com', 'Yumiis');
        $mail->addAddress($to, $toName);
        $mail->isHTML(true);
        $mail->CharSet    = 'UTF-8';
        $mail->Subject    = $subject;
        $mail->Body       = $body;
        $mail->send();
    } catch (Exception $e) {
        log_error("Mail Error: " . $mail->ErrorInfo, "Sending to: $to", 'MAIL');
    }
}
function sendStatusUpdateEmail($e, $n, $oid, $st, $sd = null, $stm = null)
{
    $sb = "Order #$oid Status Updated";
    $x  = ($sd && $stm) ? "<p><strong>Scheduled Delivery:</strong> $sd at $stm</p>" : '';
    $b  = "<html><body><h2>Hello, $n</h2><p>Your order #$oid status is now <strong>$st</strong>.</p>$x<p>Thank you!</p></body></html>";
    sendEmail($e, $n, $sb, $b);
}
function sendDelayNotificationEmail($e, $n, $oid, $t)
{
    $sb = "Delay Notification for Order #$oid";
    $b  = "<html><body><h2>Hello, $n</h2><p>Your order #$oid may be delayed by $t hour(s).</p></body></html>";
    sendEmail($e, $n, $sb, $b);
}
function notifyDeliveryPerson($e, $n, $oid, $st)
{
    $sb = "New Order Assigned - Order #$oid";
    $b  = "<html><body><h2>Hello, $n</h2><p>You have been assigned to order #$oid with status $st.</p></body></html>";
    sendEmail($e, $n, $sb, $b);
}
$error_messages = [
    'unknown_error'          => 'An unexpected error occurred.',
    'invalid_order_id'       => 'Invalid Order ID.',
    'order_not_found'        => 'Order not found.',
    'status_update_failed'   => 'Failed to update order status.',
    'assign_delivery_failed' => 'Failed to assign delivery user.',
    'delivery_user_invalid'  => 'Invalid delivery user.',
    'invalid_request'        => 'Invalid request.',
    'invalid_time'           => 'Invalid time provided.',
    'delete_failed'          => 'Failed to delete.',
    'restore_failed'         => 'Failed to restore.',
    'database_error'         => 'A database error occurred.',
    'invalid_status'         => 'Invalid status selected.'
];
$success_messages = [
    'status_updated'            => 'Order status updated.',
    'delivery_user_assigned'    => 'Delivery user assigned.',
    'order_restored'            => 'Order restored.',
    'order_deleted'             => 'Order moved to Trash.',
    'delay_notification_sent'   => 'Delay notification sent.',
    'order_permanently_deleted' => 'Order permanently deleted.'
];
set_exception_handler(function ($e) use ($error_messages) {
    $code = 'unknown_error';
    if ($e instanceof PDOException) {
        $code = 'database_error';
    }
    log_error($e->getMessage(), "File: {$e->getFile()} Line: {$e->getLine()}", 'EXCEPTION');
    redirect_with_message($code, 'view', $e->getMessage());
});
set_error_handler(function ($sev, $msg, $file, $line) {
    if (!(error_reporting() & $sev)) return;
    throw new ErrorException($msg, 0, $sev, $file, $line);
});
function getDeliveryUsers($pdo)
{
    $s = $pdo->prepare("SELECT id,username,email FROM users WHERE role='delivery' AND is_active=1");
    $s->execute();
    return $s->fetchAll(PDO::FETCH_ASSOC);
}
function getAllOrders($pdo)
{
    $s = $pdo->prepare("SELECT o.*,t.name AS tip_name,t.percentage AS tip_percentage,t.amount AS tip_fixed_amount FROM orders o LEFT JOIN tips t ON o.tip_id=t.id WHERE o.is_deleted=0 ORDER BY o.created_at DESC");
    $s->execute();
    return $s->fetchAll(PDO::FETCH_ASSOC);
}
function getOrderById($pdo, $id)
{
    $s = $pdo->prepare("SELECT o.*,t.name AS tip_name,t.percentage AS tip_percentage,t.amount AS tip_fixed_amount FROM orders o LEFT JOIN tips t ON o.tip_id=t.id WHERE o.id=? AND o.is_deleted=0 LIMIT 1");
    $s->execute([$id]);
    return $s->fetch(PDO::FETCH_ASSOC);
}
function getTopProducts($pdo)
{
    $s = $pdo->prepare("SELECT order_details FROM orders WHERE is_deleted=0");
    $s->execute();
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    $c = [];
    foreach ($rows as $r) {
        $d = json_decode($r['order_details'], true);
        if (!empty($d['items']) && is_array($d['items'])) {
            foreach ($d['items'] as $it) {
                $n = $it['name'] ?? 'Unknown';
                $q = (int)($it['quantity'] ?? 0);
                if ($q > 0) $c[$n] = ($c[$n] ?? 0) + $q;
            }
        }
    }
    arsort($c);
    return array_slice($c, 0, 10, true);
}
$user_role = $_SESSION['role'] ?? 'admin';
$user_id   = $_SESSION['user_id'] ?? 1;
$action    = $_GET['action'] ?? 'view';
$id        = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message   = '';
$allowed_actions = [
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
$delivery_users = getDeliveryUsers($pdo);
$all_orders = [];
$status_orders = [];
try {
    switch ($action) {
        case 'view_details':
            if ($id <= 0) {
                redirect_with_message('invalid_order_id');
            }
            $order = getOrderById($pdo, $id);
            if (!$order) {
                redirect_with_message('order_not_found');
            }
            break;
        case 'update_status_form':
            if ($id <= 0) {
                redirect_with_message('invalid_order_id');
            }
            $order = getOrderById($pdo, $id);
            if (!$order) {
                redirect_with_message('order_not_found');
            }
            break;
        case 'update_status':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
                $newStatus = $_POST['status'] ?? '';
                if (!empty($newStatus) && in_array($newStatus, $statuses)) {
                    $order = getOrderById($pdo, $id);
                    if (!$order) {
                        redirect_with_message('order_not_found');
                    }
                    $s = $pdo->prepare("UPDATE orders SET status=?, updated_at=NOW() WHERE id=?");
                    if ($s->execute([$newStatus, $id])) {
                        sendStatusUpdateEmail($order['customer_email'], $order['customer_name'], $id, $newStatus, $order['scheduled_date'], $order['scheduled_time']);
                        redirect_with_message('status_updated');
                    } else {
                        redirect_with_message('status_update_failed');
                    }
                }
                redirect_with_message('invalid_status');
            }
            redirect_with_message('invalid_request');
            break;
        case 'delete':
            if ($id <= 0) {
                redirect_with_message('invalid_order_id');
            }
            $s = $pdo->prepare("UPDATE orders SET is_deleted=1, updated_at=NOW() WHERE id=?");
            if ($s->execute([$id])) {
                redirect_with_message('order_deleted');
            } else {
                redirect_with_message('delete_failed');
            }
            break;
        case 'view_trash':
            if ($user_role !== 'admin') {
                header('HTTP/1.1 403 Forbidden');
                exit;
            }
            $t = $pdo->prepare("SELECT o.*,t.name AS tip_name,t.percentage AS tip_percentage,t.amount AS tip_fixed_amount FROM orders o LEFT JOIN tips t ON o.tip_id=t.id WHERE o.is_deleted=1 ORDER BY o.updated_at DESC");
            $t->execute();
            $trash_orders = $t->fetchAll(PDO::FETCH_ASSOC);
            break;
        case 'restore':
            if ($user_role !== 'admin') {
                header('HTTP/1.1 403 Forbidden');
                exit;
            }
            if ($id <= 0) {
                redirect_with_message('invalid_order_id', 'view_trash');
            }
            $r = $pdo->prepare("UPDATE orders SET is_deleted=0, updated_at=NOW() WHERE id=?");
            if ($r->execute([$id])) {
                redirect_with_message('order_restored', 'view_trash');
            } else {
                redirect_with_message('restore_failed', 'view_trash');
            }
            break;
        case 'permanent_delete':
            if ($user_role !== 'admin') {
                header('HTTP/1.1 403 Forbidden');
                exit;
            }
            if ($id <= 0) {
                redirect_with_message('invalid_order_id', 'view_trash');
            }
            $pd = $pdo->prepare("DELETE FROM orders WHERE id=?");
            if ($pd->execute([$id])) {
                redirect_with_message('order_permanently_deleted', 'view_trash');
            } else {
                redirect_with_message('delete_failed', 'view_trash');
            }
            break;
        case 'send_delay_notification':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
                $time = (int)($_POST['additional_time'] ?? 0);
                if ($time <= 0) {
                    redirect_with_message('invalid_time');
                }
                $q = $pdo->prepare("SELECT * FROM orders WHERE id=? AND is_deleted=0");
                $q->execute([$id]);
                $ord = $q->fetch(PDO::FETCH_ASSOC);
                if (!$ord) {
                    redirect_with_message('order_not_found');
                }
                sendDelayNotificationEmail($ord['customer_email'], $ord['customer_name'], $id, $time);
                redirect_with_message('delay_notification_sent');
            }
            redirect_with_message('invalid_request');
            break;
        case 'assign_delivery':
            if ($user_role !== 'admin') {
                header('HTTP/1.1 403 Forbidden');
                exit;
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
                $du = (int)($_POST['delivery_user_id'] ?? 0);
                if ($du <= 0) {
                    redirect_with_message('delivery_user_invalid');
                }
                $s = $pdo->prepare("SELECT email,username FROM users WHERE id=? AND role='delivery' AND is_active=1");
                $s->execute([$du]);
                $usr = $s->fetch(PDO::FETCH_ASSOC);
                if (!$usr) {
                    redirect_with_message('delivery_user_invalid');
                }
                $upd = $pdo->prepare("UPDATE orders SET delivery_user_id=? WHERE id=?");
                if ($upd->execute([$du, $id])) {
                    notifyDeliveryPerson($usr['email'], $usr['username'], $id, 'Assigned');
                    redirect_with_message('delivery_user_assigned');
                }
                redirect_with_message('assign_delivery_failed');
            }
            redirect_with_message('invalid_request');
            break;
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
    }
} catch (Exception $e) {
    log_error($e->getMessage(), "Action:$action,ID:$id", 'EXCEPTION');
    redirect_with_message('unknown_error', 'view', $e->getMessage());
}
function generate_order_row_data($order, $delivery_users, $user_role)
{
    $tip = 'N/A';
    if (!empty($order['tip_name'])) {
        $val = ($order['tip_percentage'] !== null)
            ? ($order['tip_percentage'] . '%')
            : (number_format($order['tip_fixed_amount'], 2) . '€');
        $tip = "<span class='badge bg-info'>" . htmlspecialchars($order['tip_name']) . "</span> ($val)";
    }
    $cp = $order['coupon_code'] ?? 'N/A';
    if (!empty($order['coupon_discount'])) {
        $cp .= " (" . number_format($order['coupon_discount'], 2) . "€)";
    }
    $ac = "<a href='orders.php?action=view_details&id={$order['id']}' class='btn btn-sm btn-info me-1'><i class='bi bi-eye'></i></a>"
        . "<a href='orders.php?action=update_status_form&id={$order['id']}' class='btn btn-sm btn-warning me-1'><i class='bi bi-pencil'></i></a>";
    if ($user_role === 'admin') {
        $ac .= "<a href='orders.php?action=delete&id={$order['id']}' class='btn btn-sm btn-danger me-1' onclick='return confirm(\"Move to trash?\");'><i class='bi bi-trash'></i></a>"
            . "<button type='button' class='btn btn-sm btn-secondary me-1' data-bs-toggle='modal' data-bs-target='#delayModal{$order['id']}'><i class='bi bi-clock'></i></button>";
    }
    $ass = '';
    if ($user_role === 'admin') {
        $ass .= "<form method='POST' action='orders.php?action=assign_delivery&id={$order['id']}' class='d-flex mb-0'>"
            . "<select name='delivery_user_id' class='form-select form-select-sm me-1' required>"
            . "<option value=''>Assign</option>";
        foreach ($delivery_users as $d) {
            $sel = ($order['delivery_user_id'] == $d['id']) ? 'selected' : '';
            $ass .= "<option value='" . htmlspecialchars($d['id']) . "' $sel>" . htmlspecialchars($d['username']) . "</option>";
        }
        $ass .= "</select>"
            . "<button type='submit' class='btn btn-sm btn-primary'><i class='bi bi-person-check'></i></button>"
            . "</form>";
    }
    $p = [
        htmlspecialchars($order['id']),
        htmlspecialchars($order['customer_name']),
        htmlspecialchars($order['customer_email']) . '<br>' . htmlspecialchars($order['customer_phone']),
        htmlspecialchars($order['delivery_address']),
        number_format($order['total_amount'], 2) . '€',
        $tip,
        $cp,
        htmlspecialchars($order['status'])
    ];
    if ($user_role === 'admin') {
        $p[] = $ass;
    }
    $p[] = $ac;
    ob_start();
    $details = json_decode($order['order_details'], true);
    $items   = $details['items'] ?? [];
?>
    <div style="font-size:0.85rem;">
        <p><strong>Payment:</strong> <?= htmlspecialchars($order['payment_method'] ?? 'N/A') ?></p>
        <p><strong>Scheduled:</strong> <?= htmlspecialchars($order['scheduled_date'] ?? 'N/A') ?> <?= htmlspecialchars($order['scheduled_time'] ?? '') ?></p>
        <p><strong>Tip Amount:</strong> <?= number_format($order['tip_amount'], 2) ?>€</p>
        <p><strong>Created:</strong> <?= htmlspecialchars($order['created_at']) ?></p>
        <p><strong>Updated:</strong> <?= htmlspecialchars($order['updated_at'] ?? 'N/A') ?></p>
        <?php if ($items) { ?>
            <hr>
            <strong>Items:</strong>
            <ul class="mb-0">
                <?php foreach ($items as $it) {
                    $nm = htmlspecialchars($it['name'] ?? 'Item');
                    $qt = htmlspecialchars($it['quantity'] ?? '1');
                    $pr = number_format($it['total_price'] ?? 0, 2);
                    echo "<li>{$nm} (Qty: {$qt}) - {$pr}€</li>";
                } ?>
            </ul>
        <?php } ?>
    </div>
<?php
    $c = ob_get_clean();
    return ['parent' => $p, 'child' => $c];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Orders Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">
    <style>
        .child-row {
            background-color: #f0f0f0
        }
    </style>
</head>
<body>
    <div class="container-fluid my-3">
        <div class="btn-group mb-3">
            <button class="btn btn-primary me-2" onclick="window.location='orders.php?action=view'"><i class="bi bi-box-seam"></i> View Orders</button>
            <?php if ($user_role === 'admin') { ?>
                <button class="btn btn-dark me-2" onclick="window.location='orders.php?action=view_trash'"><i class="bi bi-trash"></i> Trash</button>
                <button class="btn btn-info" onclick="window.location='orders.php?action=top_products'"><i class="bi bi-bar-chart-fill"></i> Top 10 Products</button>
            <?php } ?>
        </div>
        <?php
        $msg_code = $_GET['message'] ?? '';
        $err_extra = $_GET['err'] ?? '';
        if ($msg_code !== '') {
            $is_err   = array_key_exists($msg_code, $error_messages);
            $is_succ  = array_key_exists($msg_code, $success_messages);
            if ($is_err) {
                $dm = $error_messages[$msg_code];
                $at = 'danger';
                $ic = 'exclamation-triangle-fill';
                if ($msg_code === 'unknown_error' && $err_extra) {
                    $dm .= ' (Details: ' . htmlspecialchars($err_extra) . ')';
                }
            } elseif ($is_succ) {
                $dm = $success_messages[$msg_code];
                $at = 'success';
                $ic = 'check-circle-fill';
            } else {
                $dm = $error_messages['unknown_error'];
                $at = 'danger';
                $ic = 'exclamation-triangle-fill';
            }
        ?>
            <div class="alert alert-<?= $at ?> d-flex align-items-center alert-dismissible fade show" role="alert">
                <i class="bi bi-<?= $ic ?> me-2"></i>
                <div><?= htmlspecialchars($dm) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php
        }
        ?>
        <h4 class="mb-3">Orders Management <small class="text-muted">(Role: <?= htmlspecialchars($user_role) ?>)</small></h4>
        <?php
        if ($action === 'view_details' && !empty($order)) { ?>
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">Order #<?= htmlspecialchars($order['id']) ?> Details</div>
                <div class="card-body">
                    <?php
                    $storeData = null;
                    if (!empty($order['store_id'])) {
                        $ss = $pdo->prepare("SELECT*FROM stores WHERE id=? LIMIT 1");
                        $ss->execute([$order['store_id']]);
                        $storeData = $ss->fetch(PDO::FETCH_ASSOC);
                    }
                    ?>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($order['customer_email']) ?></p>
                            <p><strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone']) ?></p>
                            <p><strong>Address:</strong> <?= htmlspecialchars($order['delivery_address']) ?></p>
                            <p><strong>Total:</strong> <?= number_format($order['total_amount'], 2) ?>€</p>
                            <p><strong>Tip:</strong>
                                <?php
                                if (!empty($order['tip_name'])) {
                                    $ti = ($order['tip_percentage'] !== null) ? ($order['tip_percentage'] . '%') : (number_format($order['tip_fixed_amount'], 2) . '€');
                                    echo "<span class='badge bg-info'>" . htmlspecialchars($order['tip_name']) . "</span> ($ti)";
                                } else {
                                    echo "N/A";
                                }
                                ?>
                            </p>
                            <p><strong>Tip Amount:</strong> <?= number_format($order['tip_amount'], 2) ?>€</p>
                            <p><strong>Coupon Code:</strong> <?= htmlspecialchars($order['coupon_code'] ?? 'N/A') ?></p>
                            <p><strong>Coupon Discount:</strong> <?= number_format(($order['coupon_discount'] ?? 0), 2) ?>€</p>
                            <p><strong>Scheduled:</strong> <?= htmlspecialchars($order['scheduled_date'] ?? 'N/A') ?> <?= htmlspecialchars($order['scheduled_time'] ?? 'N/A') ?></p>
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
                            <p><strong>Created:</strong> <?= htmlspecialchars($order['created_at']) ?></p>
                            <p><strong>Updated:</strong> <?= htmlspecialchars($order['updated_at'] ?? 'N/A') ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Order Items</h6>
                            <?php
                            $details = json_decode($order['order_details'], true);
                            $items = $details['items'] ?? [];
                            if ($items) {
                                foreach ($items as $it) { ?>
                                    <div class="card mb-2">
                                        <div class="card-header" style="font-size:.9rem;">
                                            <?= htmlspecialchars($it['name']) ?> (Qty: <?= htmlspecialchars($it['quantity']) ?>)
                                        </div>
                                        <div class="card-body" style="font-size:.8rem;">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <?php if (!empty($it['image_url'])) { ?>
                                                        <img src="<?= htmlspecialchars($it['image_url']) ?>" alt="Item" class="img-fluid rounded">
                                                    <?php } else { ?>
                                                        <img src="default-image.png" alt="No Image" class="img-fluid rounded">
                                                    <?php } ?>
                                                </div>
                                                <div class="col-md-8">
                                                    <p><strong>Description:</strong> <?= htmlspecialchars($it['description'] ?? 'N/A') ?></p>
                                                    <p><strong>Size:</strong> <?= htmlspecialchars($it['size']) ?> (<?= number_format($it['size_price'], 2) ?>€)</p>
                                                    <?php
                                                    if (!empty($it['extras'])) {
                                                        echo "<p><strong>Extras:</strong></p><ul>";
                                                        foreach ($it['extras'] as $ex) {
                                                            echo "<li>" . htmlspecialchars($ex['name']) . " x" . intval($ex['quantity'] ?? 1) . " (" . number_format($ex['price'], 2) . "€)=" . number_format($ex['price'] * ($ex['quantity'] ?? 1), 2) . "€</li>";
                                                        }
                                                        echo "</ul>";
                                                    }
                                                    if (!empty($it['sauces'])) {
                                                        echo "<p><strong>Sauces:</strong></p><ul>";
                                                        foreach ($it['sauces'] as $sx) {
                                                            echo "<li>" . htmlspecialchars($sx['name']) . " x" . intval($sx['quantity'] ?? 1) . " (" . number_format($sx['price'], 2) . "€)=" . number_format($sx['price'] * ($sx['quantity'] ?? 1), 2) . "€</li>";
                                                        }
                                                        echo "</ul>";
                                                    }
                                                    if (!empty($it['dresses'])) {
                                                        echo "<p><strong>Dresses:</strong></p><ul>";
                                                        foreach ($it['dresses'] as $dr) {
                                                            echo "<li>" . htmlspecialchars($dr['name']) . " x" . intval($dr['quantity'] ?? 1) . " (" . number_format($dr['price'], 2) . "€)=" . number_format($dr['price'] * ($dr['quantity'] ?? 1), 2) . "€</li>";
                                                        }
                                                        echo "</ul>";
                                                    }
                                                    if (!empty($it['drink'])) {
                                                        echo "<p><strong>Drink:</strong> " . htmlspecialchars($it['drink']['name']) . " (" . number_format($it['drink']['price'], 2) . "€)</p>";
                                                    }
                                                    if (!empty($it['special_instructions'])) {
                                                        echo "<p><strong>Instructions:</strong> " . htmlspecialchars($it['special_instructions']) . "</p>";
                                                    }
                                                    ?>
                                                    <p><strong>Unit Price:</strong> <?= number_format($it['unit_price'], 2) ?>€</p>
                                                    <p><strong>Total Price:</strong> <?= number_format($it['total_price'], 2) ?>€</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                            <?php }
                            } else {
                                echo "<p>No items.</p>";
                            } ?>
                        </div>
                    </div>
                    <button type="button" class="btn btn-dark mt-2" data-bs-toggle="modal" data-bs-target="#invoiceModal" style="font-size:.8rem;"><i class="bi bi-file-earmark-text"></i> Invoice</button>
                    <a href="orders.php?action=view" class="btn btn-secondary mt-2" style="font-size:.8rem;">Back</a>
                </div>
            </div>
            <div class="modal fade" id="invoiceModal">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-dark text-white" style="font-size:.9rem;">
                            Invoice #<?= htmlspecialchars($order['id']) ?>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" style="font-size:.8rem;">
                            <?php
                            $d = json_decode($order['order_details'], true);
                            $items = $d['items'] ?? [];
                            $shipping = (float)($d['shipping_fee'] ?? 0);
                            $invTip = (float)($order['tip_amount'] ?? 0);
                            $invDisc = (float)($order['coupon_discount'] ?? 0);
                            $sub = 0;
                            foreach ($items as $i) {
                                $sub += (float)($i['total_price'] ?? 0);
                            }
                            $taxRate = 0.1;
                            $tax = ($sub + $shipping - $invDisc) * $taxRate;
                            $calc = $sub + $shipping + $invTip - $invDisc + $tax;
                            $storeData = $storeData ?? null;
                            ?>
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <?php
                                    if ($storeData && !empty($storeData['cart_logo'])) {
                                        echo "<img src='../admin/" . htmlspecialchars($storeData['cart_logo']) . "' alt='StoreLogo' style='max-height:60px;'>";
                                    }
                                    ?>
                                    <h3>INVOICE</h3>
                                </div>
                                <?php
                                if ($storeData) {
                                    echo "<div class='mb-2'><strong>" . htmlspecialchars($storeData['name']) . "</strong><br>" . htmlspecialchars($storeData['address']) . "<br>";
                                    if (!empty($storeData['phone'])) echo "Tel: " . htmlspecialchars($storeData['phone']) . "<br>";
                                    if (!empty($storeData['email'])) echo htmlspecialchars($storeData['email']) . "<br>";
                                    if (!empty($storeData['tax_id'])) echo "VAT/TaxID: " . htmlspecialchars($storeData['tax_id']) . "<br>";
                                    echo "</div>";
                                } else {
                                    echo "<div class='mb-2'><strong>Yumiis</strong><br>123 Main<br>City<br></div>";
                                }
                                ?>
                                <div class="mb-2">
                                    <p><strong>Bill To:</strong> <?= htmlspecialchars($order['customer_name']) ?><br><?= htmlspecialchars($order['delivery_address']) ?><br><?= htmlspecialchars($order['customer_email']) ?></p>
                                    <p><strong>Order Date:</strong> <?= htmlspecialchars($order['created_at']) ?></p>
                                </div>
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
                                            <?php
                                            if ($items) {
                                                foreach ($items as $x) {
                                                    $nm = htmlspecialchars($x['name'] ?? 'Item');
                                                    $q = (int)($x['quantity'] ?? 1);
                                                    $u = (float)($x['unit_price'] ?? 0);
                                                    $tp = (float)($x['total_price'] ?? 0);
                                                    echo "<tr><td>$nm</td><td>$q</td><td>" . number_format($u, 2) . "</td><td>" . number_format($tp, 2) . "</td></tr>";
                                                }
                                            } else {
                                                echo "<tr><td colspan='4'>No items.</td></tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                <p><strong>Items Subtotal:</strong> <?= number_format($sub, 2) ?>€</p>
                                <p><strong>Shipping Fee:</strong> <?= number_format($shipping, 2) ?>€</p>
                                <p><strong>Tax(<?= ($taxRate * 100) ?>%):</strong> <?= number_format($tax, 2) ?>€</p>
                                <p><strong>Tip:</strong> <?= number_format($invTip, 2) ?>€</p>
                                <p><strong>Coupon Discount:</strong> <?= number_format($invDisc, 2) ?>€</p>
                                <hr>
                                <p><strong>Total:</strong> <?= number_format($calc, 2) ?>€</p>
                                <p class="text-center mt-2" style="font-size:.7rem;color:#666;">Thank you.</p>
                            </div>
                        </div>
                        <div class="modal-footer" style="font-size:.8rem;">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" onclick="window.print();">Print</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        } elseif ($action === 'update_status_form' && !empty($order)) { ?>
            <div class="card">
                <div class="card-header bg-warning">Update Status #<?= htmlspecialchars($order['id']) ?></div>
                <div class="card-body" style="font-size:.9rem;">
                    <form method="POST" action="orders.php?action=update_status&id=<?= htmlspecialchars($order['id']) ?>">
                        <div class="mb-2">
                            <label for="status">Status</label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($statuses as $s) { ?>
                                    <option value="<?= htmlspecialchars($s) ?>" <?= ($order['status'] === $s ? 'selected' : '') ?>><?= htmlspecialchars($s) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <button class="btn btn-primary">Update</button>
                        <a href="orders.php?action=view" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        <?php
        } elseif ($action === 'view_trash' && isset($trash_orders)) { ?>
            <div class="card">
                <div class="card-header bg-secondary text-white">Trashed Orders</div>
                <div class="card-body" style="font-size:.9rem;">
                    <?php if ($trash_orders) { ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-bordered align-middle data-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Address</th>
                                        <th>Total(€)</th>
                                        <th>Tip</th>
                                        <th>Coupon</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trash_orders as $t) { ?>
                                        <tr>
                                            <td><?= htmlspecialchars($t['id']) ?></td>
                                            <td><?= htmlspecialchars($t['customer_name']) ?></td>
                                            <td><?= htmlspecialchars($t['customer_email']) ?><br><?= htmlspecialchars($t['customer_phone']) ?></td>
                                            <td><?= htmlspecialchars($t['delivery_address']) ?></td>
                                            <td><?= number_format($t['total_amount'], 2) ?></td>
                                            <td><?php
                                                if (!empty($t['tip_name'])) {
                                                    $xx = ($t['tip_percentage'] !== null) ? ($t['tip_percentage'] . '%') : (number_format($t['tip_fixed_amount'], 2) . '€');
                                                    echo "<span class='badge bg-info'>" . htmlspecialchars($t['tip_name']) . "</span> ($xx)";
                                                } else echo 'N/A';
                                                ?></td>
                                            <td><?php
                                                $cc = $t['coupon_code'] ?? 'N/A';
                                                if (!empty($t['coupon_discount'])) $cc .= " (" . number_format($t['coupon_discount'], 2) . "€)";
                                                echo htmlspecialchars($cc);
                                                ?></td>
                                            <td><?= htmlspecialchars($t['status']) ?></td>
                                            <td>
                                                <a href="orders.php?action=restore&id=<?= $t['id'] ?>" class="btn btn-sm btn-success me-1"><i class="bi bi-arrow-counterclockwise"></i></a>
                                                <a href="orders.php?action=permanent_delete&id=<?= $t['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Permanently delete?');"><i class="bi bi-trash3-fill"></i></a>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } else {
                        echo "<p>No trashed orders.</p>";
                    } ?>
                    <a href="orders.php?action=view" class="btn btn-secondary mt-2" style="font-size:.8rem;">Back</a>
                </div>
            </div>
        <?php
        } elseif ($action === 'top_products' && isset($top_products)) { ?>
            <div class="card">
                <div class="card-header bg-info text-white">Top 10 Products</div>
                <div class="card-body" style="font-size:.9rem;">
                    <?php if ($top_products) { ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-bordered align-middle data-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Rank</th>
                                        <th>Product</th>
                                        <th>Qty Sold</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $pos = 1;
                                    foreach ($top_products as $p => $q) { ?>
                                        <tr>
                                            <td><?= $pos ?></td>
                                            <td><?= htmlspecialchars($p) ?></td>
                                            <td><?= $q ?></td>
                                        </tr>
                                    <?php $pos++;
                                    } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } else {
                        echo "<p>No top products found.</p>";
                    } ?>
                </div>
            </div>
            <?php
        } else {
            $all_orders = getAllOrders($pdo);
            $status_orders = array_fill_keys($statuses, []);
            foreach ($all_orders as $a) {
                $st = in_array($a['status'], $statuses) ? $a['status'] : 'New Order';
                $status_orders[$st][] = $a;
            }
            foreach ($statuses as $st) {
                $group = $status_orders[$st] ?? [];
                $tid = 'table-' . strtolower(str_replace(' ', '_', $st)); ?>
                <div class="card mb-4">
                    <div class="card-header bg-light"><?= $st ?> Orders <span class="badge bg-secondary"><?= count($group) ?></span></div>
                    <div class="card-body" style="font-size:.9rem;">
                        <?php if (!empty($group)) { ?>
                            <table id="<?= $tid ?>" class="table table-striped table-hover table-bordered align-middle dt-with-details" style="width:100%;">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Address</th>
                                        <th>Total(€)</th>
                                        <th>Tip</th>
                                        <th>Coupon</th>
                                        <th>Status</th>
                                        <?php if ($user_role === 'admin') { ?><th>Assign</th><?php } ?>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($group as $o) {
                                        $rd = generate_order_row_data($o, $delivery_users, $user_role);
                                        $p = $rd['parent'];
                                        $c = $rd['child'];
                                        echo "<tr data-child-content='" . htmlspecialchars($c, ENT_QUOTES) . "'>";
                                        foreach ($p as $td) {
                                            echo "<td>$td</td>";
                                        }
                                        echo "</tr>";
                                    } ?>
                                </tbody>
                            </table>
                        <?php } else {
                            echo "<p>No $st orders found.</p>";
                        } ?>
                    </div>
                </div>
        <?php }
        } ?>
    </div>
    <?php if ($user_role === 'admin' && isset($all_orders) && ($action === 'view' || $action === 'view_trash')) {
        foreach ($all_orders as $o) {
            if (!in_array($o['status'], ['Canceled', 'Delivered'])) { ?>
                <div class="modal fade" id="delayModal<?= $o['id'] ?>">
                    <div class="modal-dialog modal-dialog-centered" style="font-size:.9rem;">
                        <div class="modal-content">
                            <div class="modal-header bg-secondary text-white">Delay Notification #<?= htmlspecialchars($o['id']) ?>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" action="orders.php?action=send_delay_notification&id=<?= $o['id'] ?>">
                                <div class="modal-body">
                                    <label class="form-label">Additional Time (hours)</label>
                                    <input type="number" name="additional_time" class="form-control mb-3" min="1" required>
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
    <?php }
        }
    } ?>
    <audio id="orderSound" src="alert.mp3" preload="auto"></audio>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
    <script>
        $(function() {
            $('table.dt-with-details').each(function() {
                const $t = $(this);
                const dT = $t.DataTable({
                    responsive: false,
                    autoWidth: false,
                    pageLength: 5,
                    lengthMenu: [5, 10, 25, 50, 100],
                    language: {
                        emptyTable: "No data available",
                        search: "Search:"
                    },
                    columnDefs: [{
                            orderable: false,
                            targets: -1
                        }
                        <?php if ($user_role === 'admin') { ?>, {
                                orderable: false,
                                targets: -2
                            }
                        <?php } ?>
                    ]
                });
                $t.find('tbody').on('click', 'tr', function(e) {
                    const tg = $(e.target);
                    if (tg.is('button') || tg.is('a') || tg.closest('form').length) return;
                    const row = dT.row(this);
                    if (row.child.isShown()) {
                        row.child.hide();
                        $(this).removeClass('child-row');
                    } else {
                        const cc = $(this).attr('data-child-content') || '';
                        row.child(cc).show();
                        $(this).addClass('child-row');
                    }
                });
            });
            let lastOrderId = 0;
            const newOrderTableId = 'table-new_order';
            const $newOrderTable = $('#' + newOrderTableId);
            if ($newOrderTable.length) {
                $newOrderTable.find('tbody tr').each(function() {
                    const cid = parseInt($(this).find('td').eq(0).text(), 10);
                    if (cid > lastOrderId) lastOrderId = cid;
                });
                setInterval(() => {
                    $.ajax({
                        url: 'fetch_new_orders.php',
                        method: 'GET',
                        data: {
                            last_id: lastOrderId
                        },
                        dataType: 'json',
                        success: function(r) {
                            if (r.status === 'success') {
                                const n = r.newOrders;
                                if (n.length > 0) {
                                    const dt = $newOrderTable.DataTable();
                                    n.forEach(o => {
                                        let tipBadge = 'N/A';
                                        if (o.tip_name) {
                                            const tv = (o.tip_percentage !== null) ? (o.tip_percentage + '%') : (parseFloat(o.tip_fixed_amount).toFixed(2) + '€');
                                            tipBadge = `<span class='badge bg-info'>${o.tip_name}</span> (${tv})`;
                                        }
                                        let cp = o.coupon_code || 'N/A';
                                        if (o.coupon_discount) {
                                            cp += ` (${parseFloat(o.coupon_discount).toFixed(2)}€)`;
                                        }
                                        let assign = '';
                                        <?php if ($user_role === 'admin') { ?>
                                            assign = `<form method='POST' action='orders.php?action=assign_delivery&id=${o.id}' class='d-flex mb-0'><select name='delivery_user_id' class='form-select form-select-sm me-1' required><option value=''>Assign</option><?php foreach ($delivery_users as $d) { ?> <option value='<?= $d['id'] ?>'><?= htmlspecialchars($d['username']) ?></option><?php } ?></select><button type='submit' class='btn btn-sm btn-primary'><i class='bi bi-person-check'></i></button></form>`;
                                        <?php } ?>
                                        let act = `<a href='orders.php?action=view_details&id=${o.id}' class='btn btn-sm btn-info me-1'><i class='bi bi-eye'></i></a><a href='orders.php?action=update_status_form&id=${o.id}' class='btn btn-sm btn-warning me-1'><i class='bi bi-pencil'></i></a>`;
                                        <?php if ($user_role === 'admin') { ?>
                                            act += `<a href='orders.php?action=delete&id=${o.id}' class='btn btn-sm btn-danger me-1' onclick='return confirm("Move to trash?");'><i class='bi bi-trash'></i></a><button type='button' class='btn btn-sm btn-secondary me-1' data-bs-toggle='modal' data-bs-target='#delayModal${o.id}'><i class='bi bi-clock'></i></button>`;
                                        <?php } ?>
                                        const parentRow = [
                                            o.id,
                                            o.customer_name,
                                            `${o.customer_email}<br>${o.customer_phone}`,
                                            o.delivery_address,
                                            parseFloat(o.total_amount).toFixed(2) + '€',
                                            tipBadge,
                                            cp,
                                            o.status
                                            <?php if ($user_role === 'admin') { ?>, assign<?php } ?>,
                                                act
                                            ];
                                        const childHtml = `<div style="font-size:0.85rem;"><p>Auto new order details here</p></div>`;
                                        const newRow = dt.row.add(parentRow).draw(false).node();
                                        $(newRow).attr('data-child-content', childHtml);
                                        if (o.id > lastOrderId) lastOrderId = o.id;
                                        <?php if ($user_role === 'admin') { ?>
                                            const dm = `
<div class="modal fade" id="delayModal${o.id}">
<div class="modal-dialog modal-dialog-centered" style="font-size:.9rem;">
<div class="modal-content">
<div class="modal-header bg-secondary text-white">Delay Notification #${o.id}
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form method="POST" action="orders.php?action=send_delay_notification&id=${o.id}">
<div class="modal-body">
<label class="form-label">Additional Time (hours)</label>
<input type="number" name="additional_time" class="form-control mb-3" min="1" required>
<p>Send Delay Notification?</p>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
<button type="submit" class="btn btn-warning">Send</button>
</div>
</form>
</div>
</div>
</div>`;
                                            $('body').append(dm);
                                        <?php } ?>
                                    });
                                    document.getElementById('orderSound').play();
                                }
                            } else {
                                console.error('Fetch Error:', r.message);
                            }
                        },
                        error: function(a, b, c) {
                            console.error('AJAX Error:', b, c);
                        }
                    });
                }, 5000);
            }
        });
    </script>
    <?php ob_end_flush();
    include 'includes/footer.php'; ?>
</body>
</html>