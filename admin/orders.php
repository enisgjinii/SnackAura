<?php
ob_start();
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

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

// Simplified email notification (placeholder for future implementation)
function sendStatusUpdateEmail($e, $n, $oid, $st, $sd = null, $stm = null)
{
    // Log the email notification instead of sending
    $message = "Status update email would be sent to: $e for order #$oid with status: $st";
    log_error($message, "Email notification", 'INFO');
}

function sendDelayNotificationEmail($e, $n, $oid, $t)
{
    // Log the delay notification instead of sending
    $message = "Delay notification email would be sent to: $e for order #$oid with delay: $t hours";
    log_error($message, "Delay notification", 'INFO');
}

function notifyDeliveryPerson($e, $n, $oid, $st)
{
    // Log the delivery notification instead of sending
    $message = "Delivery notification email would be sent to: $e for order #$oid with status: $st";
    log_error($message, "Delivery notification", 'INFO');
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
            
        case 'restore':
            if ($id <= 0) {
                redirect_with_message('invalid_order_id');
            }
            $s = $pdo->prepare("UPDATE orders SET is_deleted=0, updated_at=NOW() WHERE id=?");
            if ($s->execute([$id])) {
                redirect_with_message('order_restored');
            } else {
                redirect_with_message('restore_failed');
            }
            break;
            
        case 'permanent_delete':
            if ($id <= 0) {
                redirect_with_message('invalid_order_id');
            }
            $s = $pdo->prepare("DELETE FROM orders WHERE id=?");
            if ($s->execute([$id])) {
                redirect_with_message('order_permanently_deleted');
            } else {
                redirect_with_message('delete_failed');
            }
            break;
            
        case 'assign_delivery':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
                $delivery_user_id = (int)($_POST['delivery_user_id'] ?? 0);
                if ($delivery_user_id > 0) {
                    $s = $pdo->prepare("UPDATE orders SET delivery_user_id=?, updated_at=NOW() WHERE id=?");
                    if ($s->execute([$delivery_user_id, $id])) {
                        $order = getOrderById($pdo, $id);
                        $delivery_user = array_filter($delivery_users, function($u) use ($delivery_user_id) {
                            return $u['id'] == $delivery_user_id;
                        });
                        if (!empty($delivery_user)) {
                            $delivery_user = reset($delivery_user);
                            notifyDeliveryPerson($delivery_user['email'], $delivery_user['username'], $id, $order['status']);
                        }
                        redirect_with_message('delivery_user_assigned');
                    } else {
                        redirect_with_message('assign_delivery_failed');
                    }
                } else {
                    redirect_with_message('delivery_user_invalid');
                }
            }
            redirect_with_message('invalid_request');
            break;
            
        case 'send_delay_notification':
            if ($id <= 0) {
                redirect_with_message('invalid_order_id');
            }
            $order = getOrderById($pdo, $id);
            if (!$order) {
                redirect_with_message('order_not_found');
            }
            $delay_hours = (int)($_POST['delay_hours'] ?? 1);
            if ($delay_hours > 0) {
                sendDelayNotificationEmail($order['customer_email'], $order['customer_name'], $id, $delay_hours);
                redirect_with_message('delay_notification_sent');
            } else {
                redirect_with_message('invalid_time');
            }
            break;
    }
    
    // Get orders for display
            $all_orders = getAllOrders($pdo);
    foreach ($statuses as $status) {
        $status_orders[$status] = array_filter($all_orders, function($order) use ($status) {
            return $order['status'] === $status;
        });
    }
    
} catch (Exception $e) {
    log_error($e->getMessage(), "Orders page error", 'ERROR');
    $message = $error_messages['unknown_error'] ?? 'An error occurred.';
}

// Handle messages
$message_code = $_GET['message'] ?? '';
if ($message_code) {
    if (isset($error_messages[$message_code])) {
        $message = '<div class="alert alert-danger">' . $error_messages[$message_code] . '</div>';
    } elseif (isset($success_messages[$message_code])) {
        $message = '<div class="alert alert-success">' . $success_messages[$message_code] . '</div>';
    }
}

function generate_order_row_data($order, $delivery_users, $user_role)
{
    $order_details = json_decode($order['order_details'], true);
    $items = $order_details['items'] ?? [];
    $total_items = array_sum(array_column($items, 'quantity'));
    
    $status_class = strtolower(str_replace(' ', '-', $order['status']));
    $status_badge = '<span class="status-badge status-' . $status_class . '">' . htmlspecialchars($order['status']) . '</span>';
    
    $delivery_user_name = 'Unassigned';
    if ($order['delivery_user_id']) {
        foreach ($delivery_users as $du) {
            if ($du['id'] == $order['delivery_user_id']) {
                $delivery_user_name = htmlspecialchars($du['username']);
                break;
            }
        }
    }
    
    return [
        'id' => $order['id'],
        'customer_name' => htmlspecialchars($order['customer_name']),
        'customer_email' => htmlspecialchars($order['customer_email']),
        'customer_phone' => htmlspecialchars($order['customer_phone']),
        'status' => $status_badge,
        'total_amount' => '$' . number_format($order['total_amount'], 2),
        'total_items' => $total_items,
        'delivery_user' => $delivery_user_name,
        'created_at' => date('M j, Y g:i A', strtotime($order['created_at'])),
        'scheduled_date' => $order['scheduled_date'] ? date('M j, Y', strtotime($order['scheduled_date'])) : 'ASAP',
        'scheduled_time' => $order['scheduled_time'] ? $order['scheduled_time'] : 'ASAP'
    ];
}
?>

<!-- Orders Content -->
<div class="orders-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-text">
                <h1 class="page-title">Orders Management</h1>
                <p class="page-subtitle">Track and manage customer orders and delivery status</p>
    </div>
            <div class="header-actions">
                <a href="orders.php?action=top_products" class="btn btn-secondary">
                    <i class="fas fa-chart-bar"></i> Top Products
                </a>
        </div>
            </div>
                        </div>

    <!-- Alert Container -->
    <?php if ($message): ?>
        <div class="alert-container">
            <?= $message ?>
                                        </div>
    <?php endif; ?>

    <?php if ($action === 'view_details' && isset($order)): ?>
        <!-- Order Details View -->
        <div class="order-details-section">
            <div class="details-header">
                <div class="header-info">
                    <h2>Order #<?= $order['id'] ?></h2>
                    <span class="order-date"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></span>
                                                </div>
                <div class="header-actions">
                    <a href="orders.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Orders
                    </a>
                                                </div>
                                            </div>

            <div class="details-grid">
                <!-- Customer Information -->
                <div class="details-card">
                    <div class="card-header">
                        <h3>Customer Information</h3>
                        <i class="fas fa-user"></i>
                                        </div>
                    <div class="card-content">
                        <div class="info-row">
                            <span class="label">Name:</span>
                            <span class="value"><?= htmlspecialchars($order['customer_name']) ?></span>
                                    </div>
                        <div class="info-row">
                            <span class="label">Email:</span>
                            <span class="value"><?= htmlspecialchars($order['customer_email']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Phone:</span>
                            <span class="value"><?= htmlspecialchars($order['customer_phone']) ?></span>
                    </div>
                        <div class="info-row">
                            <span class="label">Address:</span>
                            <span class="value"><?= htmlspecialchars($order['delivery_address'] ?? 'N/A') ?></span>
                </div>
            </div>
                        </div>

                <!-- Order Information -->
                <div class="details-card">
                    <div class="card-header">
                        <h3>Order Information</h3>
                        <i class="fas fa-shopping-cart"></i>
                                </div>
                    <div class="card-content">
                        <div class="info-row">
                            <span class="label">Status:</span>
                            <span class="value"><?= generate_order_row_data($order, $delivery_users, $user_role)['status'] ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Total Amount:</span>
                            <span class="value"><?= generate_order_row_data($order, $delivery_users, $user_role)['total_amount'] ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Scheduled Date:</span>
                            <span class="value"><?= generate_order_row_data($order, $delivery_users, $user_role)['scheduled_date'] ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Scheduled Time:</span>
                            <span class="value"><?= generate_order_row_data($order, $delivery_users, $user_role)['scheduled_time'] ?></span>
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="details-card full-width">
                    <div class="card-header">
                        <h3>Order Items</h3>
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="card-content">
                                <?php
                        $order_details = json_decode($order['order_details'], true);
                        $items = $order_details['items'] ?? [];
                        ?>
                        <?php if (!empty($items)): ?>
                            <div class="items-table">
                                <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                            <th>Quantity</th>
                                            <th>Price</th>
                                            <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['name']) ?></td>
                                                <td><?= (int)$item['quantity'] ?></td>
                                                <td>$<?= number_format($item['price'], 2) ?></td>
                                                <td>$<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                        <?php else: ?>
                            <p class="no-items">No items found in this order.</p>
                        <?php endif; ?>
                            </div>
                        </div>
                        </div>
                    </div>

    <?php elseif ($action === 'update_status_form' && isset($order)): ?>
        <!-- Update Status Form -->
        <div class="form-section">
            <div class="form-card">
                <div class="card-header">
                    <h3>Update Order Status</h3>
                    <i class="fas fa-edit"></i>
                </div>
                <div class="card-content">
                    <form method="POST" action="orders.php?action=update_status&id=<?= $order['id'] ?>" class="status-form">
                        <div class="form-group">
                            <label for="status" class="form-label">New Status <span class="required">*</span></label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="">Select Status</option>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?= $status ?>" <?= ($order['status'] === $status ? 'selected' : '') ?>>
                                        <?= $status ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-actions">
                            <a href="orders.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Status
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <?php elseif ($action === 'top_products'): ?>
        <!-- Top Products View -->
        <div class="top-products-section">
            <div class="section-header">
                <h2>Top Products</h2>
                <p>Most ordered items</p>
            </div>
            
            <div class="products-grid">
        <?php
                $top_products = getTopProducts($pdo);
                $rank = 1;
                ?>
                <?php foreach ($top_products as $product_name => $quantity): ?>
                    <div class="product-card">
                        <div class="product-rank">#<?= $rank ?></div>
                        <div class="product-info">
                            <h3><?= htmlspecialchars($product_name) ?></h3>
                            <p class="quantity"><?= $quantity ?> orders</p>
                        </div>
                </div>
                    <?php $rank++; ?>
                <?php endforeach; ?>
            </div>
                        </div>

    <?php else: ?>
        <!-- Orders List View -->
        <div class="orders-overview">
            <!-- Status Summary -->
            <div class="status-summary">
                <?php foreach ($statuses as $status): ?>
                    <div class="status-card">
                        <div class="status-icon status-<?= strtolower(str_replace(' ', '-', $status)) ?>">
                            <i class="fas fa-circle"></i>
                </div>
                        <div class="status-info">
                            <h3><?= $status ?></h3>
                            <p><?= count($status_orders[$status]) ?> orders</p>
            </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Orders Table -->
            <div class="table-section">
                <div class="table-header">
                    <div class="table-info">
                        <h3>All Orders</h3>
                        <span class="order-count"><?= count($all_orders) ?> orders</span>
                    </div>
                </div>
                
                <div class="table-container">
                    <?php if (!empty($all_orders)): ?>
                        <table id="ordersTable" class="data-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                        <th>Status</th>
                                    <th>Total</th>
                                    <th>Items</th>
                                    <th>Delivery</th>
                                    <th>Date</th>
                                    <th width="120">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($all_orders as $order): ?>
                                    <?php $order_data = generate_order_row_data($order, $delivery_users, $user_role); ?>
                                    <tr>
                                        <td class="id-cell">
                                            <span class="order-id">#<?= $order_data['id'] ?></span>
                                        </td>
                                        <td class="customer-cell">
                                            <div class="customer-info">
                                                <span class="customer-name"><?= $order_data['customer_name'] ?></span>
                                                <span class="customer-email"><?= $order_data['customer_email'] ?></span>
                                            </div>
                                        </td>
                                        <td class="status-cell">
                                            <?= $order_data['status'] ?>
                                        </td>
                                        <td class="total-cell">
                                            <span class="total-amount"><?= $order_data['total_amount'] ?></span>
                                        </td>
                                        <td class="items-cell">
                                            <span class="items-count"><?= $order_data['total_items'] ?> items</span>
                                        </td>
                                        <td class="delivery-cell">
                                            <span class="delivery-user"><?= $order_data['delivery_user'] ?></span>
                                        </td>
                                        <td class="date-cell">
                                            <span class="order-date"><?= $order_data['created_at'] ?></span>
                                        </td>
                                        <td class="actions-cell">
                                            <div class="action-buttons">
                                                <a href="orders.php?action=view_details&id=<?= $order['id'] ?>" 
                                                   class="btn btn-sm btn-view" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="orders.php?action=update_status_form&id=<?= $order['id'] ?>" 
                                                   class="btn btn-sm btn-edit" title="Update Status">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button class="btn btn-sm btn-delete delete-order-btn" 
                                                        data-id="<?= $order['id'] ?>" 
                                                        title="Delete Order">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-content">
                                <i class="fas fa-shopping-cart"></i>
                                <h3>No Orders Found</h3>
                                <p>There are no orders to display at the moment.</p>
                    </div>
                </div>
                    <?php endif; ?>
    </div>
                            </div>
                                </div>
    <?php endif; ?>
                                </div>

<style>
    /* Orders Page Styles */
    .orders-content {
        padding: 2rem;
        background: var(--content-bg);
        min-height: 100vh;
    }

    /* Page Header */
    .page-header {
        margin-bottom: 2rem;
    }

    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
    }

    .page-title {
        font-size: 1.875rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 0.5rem 0;
    }

    .page-subtitle {
        color: #64748b;
        margin: 0;
        font-size: 1rem;
    }

    .header-actions {
        display: flex;
        gap: 1rem;
    }

    /* Alert Container */
    .alert-container {
        margin-bottom: 2rem;
    }

    .alert {
        padding: 1rem 1.5rem;
        border-radius: 8px;
        border: 1px solid;
        margin-bottom: 1rem;
    }

    .alert-success {
        background: #d1fae5;
        border-color: #10b981;
        color: #065f46;
    }

    .alert-danger {
        background: #fee2e2;
        border-color: #ef4444;
        color: #991b1b;
    }

    /* Order Details Section */
    .order-details-section {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }

    .details-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
    }

    .header-info h2 {
        margin: 0 0 0.5rem 0;
        color: #0f172a;
        font-size: 1.5rem;
    }

    .order-date {
        color: #64748b;
        font-size: 0.875rem;
    }

    .details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 1.5rem;
    }

    .details-card {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        overflow: hidden;
    }

    .details-card.full-width {
        grid-column: 1 / -1;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        background: #f8fafc;
    }

    .card-header h3 {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 600;
        color: #0f172a;
    }

    .card-header i {
        color: #64748b;
        font-size: 1.25rem;
    }

    .card-content {
        padding: 1.5rem;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .info-row:last-child {
        border-bottom: none;
    }

    .info-row .label {
        font-weight: 500;
        color: #374151;
    }

    .info-row .value {
        color: #0f172a;
        font-weight: 600;
    }

    /* Status Summary */
    .orders-overview {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }

    .status-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .status-card {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.5rem;
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
    }

    .status-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .status-new-order {
        background: #dbeafe;
        color: #1e40af;
    }

    .status-kitchen {
        background: #fef3c7;
        color: #92400e;
    }

    .status-on-the-way {
        background: #d1fae5;
        color: #065f46;
    }

    .status-delivered {
        background: #dcfce7;
        color: #166534;
    }

    .status-canceled {
        background: #fee2e2;
        color: #991b1b;
    }

    .status-info h3 {
        margin: 0 0 0.25rem 0;
        font-size: 1rem;
        font-weight: 600;
        color: #0f172a;
    }

    .status-info p {
        margin: 0;
        color: #64748b;
        font-size: 0.875rem;
    }

    /* Table Section */
    .table-section {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        overflow: hidden;
    }

    .table-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        background: #f8fafc;
    }

    .table-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .table-info h3 {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 600;
        color: #0f172a;
    }

    .order-count {
        color: #64748b;
        font-size: 0.875rem;
    }

    .table-container {
        overflow-x: auto;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th {
        background: #f8fafc;
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: #374151;
        border-bottom: 1px solid var(--border-color);
        font-size: 0.875rem;
    }

    .data-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--border-color);
        color: #374151;
        vertical-align: middle;
    }

    .data-table tbody tr:hover {
        background: #f8fafc;
    }

    /* Table Cells */
    .id-cell {
        text-align: center;
    }

    .order-id {
        font-family: 'Courier New', monospace;
        font-weight: 600;
        color: #0f172a;
    }

    .customer-info {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .customer-name {
        font-weight: 600;
        color: #0f172a;
    }

    .customer-email {
        font-size: 0.875rem;
        color: #64748b;
    }

    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    .status-new-order {
        background: #dbeafe;
        color: #1e40af;
    }

    .status-kitchen {
        background: #fef3c7;
        color: #92400e;
    }

    .status-on-the-way {
        background: #d1fae5;
        color: #065f46;
    }

    .status-delivered {
        background: #dcfce7;
        color: #166534;
    }

    .status-canceled {
        background: #fee2e2;
        color: #991b1b;
    }

    .total-amount {
        font-weight: 600;
        color: #059669;
        font-family: 'Courier New', monospace;
    }

    .items-count {
        color: #64748b;
        font-size: 0.875rem;
    }

    .delivery-user {
        color: #64748b;
        font-size: 0.875rem;
    }

    .order-date {
        color: #64748b;
        font-size: 0.875rem;
    }

    /* Action Buttons */
    .actions-cell {
        text-align: center;
    }

    .action-buttons {
        display: flex;
        gap: 0.5rem;
        justify-content: center;
    }

    .btn-view {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 0.5rem;
        border-radius: 6px;
        transition: all 0.15s ease;
    }

    .btn-view:hover {
        background: #2563eb;
        color: white;
    }

    .btn-edit {
        background: #f59e0b;
        color: white;
        border: none;
        padding: 0.5rem;
        border-radius: 6px;
        transition: all 0.15s ease;
    }

    .btn-edit:hover {
        background: #d97706;
        color: white;
    }

    .btn-delete {
        background: #ef4444;
        color: white;
        border: none;
        padding: 0.5rem;
        border-radius: 6px;
        transition: all 0.15s ease;
    }

    .btn-delete:hover {
        background: #dc2626;
        color: white;
    }

    /* Form Section */
    .form-section {
        margin-bottom: 2rem;
    }

    .form-card {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        overflow: hidden;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        font-weight: 500;
        color: #374151;
        font-size: 0.875rem;
        margin-bottom: 0.5rem;
    }

    .required {
        color: #ef4444;
    }

    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        font-size: 0.875rem;
        transition: all 0.15s ease;
    }

    .form-control:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 2rem;
    }

    /* Top Products Section */
    .top-products-section {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }

    .section-header {
        text-align: center;
        padding: 2rem;
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
    }

    .section-header h2 {
        margin: 0 0 0.5rem 0;
        color: #0f172a;
        font-size: 1.875rem;
    }

    .section-header p {
        margin: 0;
        color: #64748b;
    }

    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .product-card {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.5rem;
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
    }

    .product-rank {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #3b82f6;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 1.125rem;
    }

    .product-info h3 {
        margin: 0 0 0.25rem 0;
        color: #0f172a;
        font-size: 1.125rem;
    }

    .product-info .quantity {
        margin: 0;
        color: #64748b;
        font-size: 0.875rem;
    }

    /* Empty State */
    .empty-state {
        padding: 3rem 1.5rem;
        text-align: center;
    }

    .empty-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
    }

    .empty-content i {
        font-size: 3rem;
        color: #9ca3af;
    }

    .empty-content h3 {
        color: #374151;
        margin: 0;
        font-size: 1.25rem;
    }

    .empty-content p {
        color: #64748b;
        margin: 0;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .orders-content {
            padding: 1rem;
        }

        .header-content {
            flex-direction: column;
            gap: 1rem;
            text-align: center;
        }

        .page-title {
            font-size: 1.5rem;
        }

        .details-grid {
            grid-template-columns: 1fr;
        }

        .status-summary {
            grid-template-columns: repeat(2, 1fr);
        }

        .action-buttons {
            flex-direction: column;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem 0.5rem;
        }

        .form-actions {
            flex-direction: column;
        }

        .products-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- JavaScript for Orders Page -->
<script>
    $(document).ready(function() {
        // Initialize DataTable for orders list
        if ($('#ordersTable').length) {
            $('#ordersTable').DataTable({
                responsive: true,
                order: [[6, 'desc']], // Sort by date by default
                pageLength: 25,
                language: {
                    search: "Search orders:",
                    lengthMenu: "Show _MENU_ orders per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ orders",
                    emptyTable: "No orders found"
                }
            });
        }

        // Delete order functionality
        $('.delete-order-btn').on('click', function() {
            const orderId = $(this).data('id');
            
            if (confirm(`Are you sure you want to delete order #${orderId}? This action cannot be undone.`)) {
                window.location.href = `orders.php?action=delete&id=${orderId}`;
            }
        });

        // Form validation
        $('.status-form').on('submit', function(e) {
            const status = $(this).find('select[name="status"]').val();
            if (!status) {
                e.preventDefault();
                alert('Please select a status.');
                return false;
            }
        });
        });
    </script>

<?php require_once 'includes/footer.php'; ?>