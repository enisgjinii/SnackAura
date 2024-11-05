<?php
// admin/orders.php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';
// Initialize variables
$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$message = '';
// Handle different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'update_status' && $id > 0) {
        // Validate status_id
        if (!isset($_POST['status_id']) || !is_numeric($_POST['status_id'])) {
            $message = '<div class="alert alert-danger">Invalid status selected.</div>';
        } else {
            $status_id = (int) $_POST['status_id'];
            // Check if the status_id exists
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM order_statuses WHERE id = ?');
            $stmt->execute([$status_id]);
            if ($stmt->fetchColumn() == 0) {
                $message = '<div class="alert alert-danger">Selected status does not exist.</div>';
            } else {
                // Update the order status
                $stmt = $pdo->prepare('UPDATE orders SET status_id = ? WHERE id = ?');
                try {
                    $stmt->execute([$status_id, $id]);
                    $message = '<div class="alert alert-success">Order status updated successfully.</div>';
                    // Redirect to avoid form resubmission
                    header('Location: orders.php?action=view&message=' . urlencode('Order status updated successfully.'));
                    exit();
                } catch (PDOException $e) {
                    error_log("Error updating order status (Order ID: $id): " . $e->getMessage());
                    $message = '<div class="alert alert-danger">Failed to update order status. Please try again later.</div>';
                }
            }
        }
    }
} elseif ($action === 'delete' && $id > 0) {
    // Delete Order
    $stmt = $pdo->prepare('DELETE FROM orders WHERE id = ?');
    try {
        $stmt->execute([$id]);
        $message = '<div class="alert alert-success">Order deleted successfully.</div>';
        // Redirect to avoid accidental deletion via refresh
        header('Location: orders.php?action=view&message=' . urlencode('Order deleted successfully.'));
        exit();
    } catch (PDOException $e) {
        error_log("Error deleting order (Order ID: $id): " . $e->getMessage());
        $message = '<div class="alert alert-danger">Failed to delete order. Please try again later.</div>';
    }
}
// Fetch all orders for viewing
if ($action === 'view') {
    try {
        $stmt = $pdo->query('
            SELECT orders.*, order_statuses.status 
            FROM orders 
            JOIN order_statuses ON orders.status_id = order_statuses.id 
            ORDER BY orders.created_at DESC
        ');
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching orders: " . $e->getMessage());
        $orders = [];
        $message = '<div class="alert alert-danger">Failed to fetch orders. Please try again later.</div>';
    }
} elseif ($action === 'view_details' && $id > 0) {
    // Fetch order details
    try {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            echo '<div class="alert alert-danger">Order not found.</div>';
            require_once 'includes/footer.php';
            exit();
        }
        // Fetch order status
        $stmt = $pdo->prepare('SELECT status FROM order_statuses WHERE id = ?');
        $stmt->execute([$order['status_id']]);
        $status = $stmt->fetchColumn();
        // Fetch order items with associated sizes, extras, and drinks
        $stmt = $pdo->prepare('
            SELECT 
                order_items.*, 
                products.name AS product_name, 
                sizes.name AS size_name 
            FROM order_items 
            JOIN products ON order_items.product_id = products.id 
            LEFT JOIN sizes ON order_items.size_id = sizes.id 
            WHERE order_items.order_id = ?
        ');
        $stmt->execute([$id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // For each order item, fetch associated extras and drinks
        foreach ($items as &$item) {
            // Fetch extras
            $stmt_extras = $pdo->prepare('
                SELECT extras.name, order_extras.quantity, order_extras.unit_price, order_extras.total_price
                FROM order_extras
                JOIN extras ON order_extras.extra_id = extras.id
                WHERE order_extras.order_item_id = ?
            ');
            $stmt_extras->execute([$item['id']]);
            $item['extras'] = $stmt_extras->fetchAll(PDO::FETCH_ASSOC);
            // Fetch drinks
            $stmt_drinks = $pdo->prepare('
                SELECT drinks.name, order_drinks.quantity, order_drinks.unit_price, order_drinks.total_price
                FROM order_drinks
                JOIN drinks ON order_drinks.drink_id = drinks.id
                WHERE order_drinks.order_item_id = ?
            ');
            $stmt_drinks->execute([$item['id']]);
            $item['drinks'] = $stmt_drinks->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Error fetching order details (Order ID: $id): " . $e->getMessage());
        echo '<div class="alert alert-danger">Failed to fetch order details. Please try again later.</div>';
        require_once 'includes/footer.php';
        exit();
    }
} elseif ($action === 'update_status_form' && $id > 0) {
    // Display Update Status Form
    try {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            echo '<div class="alert alert-danger">Order not found.</div>';
            require_once 'includes/footer.php';
            exit();
        }
        // Fetch all statuses
        $stmt = $pdo->query('SELECT * FROM order_statuses ORDER BY id ASC');
        $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching data for update status form (Order ID: $id): " . $e->getMessage());
        echo '<div class="alert alert-danger">Failed to fetch data. Please try again later.</div>';
        require_once 'includes/footer.php';
        exit();
    }
}
?>
<?php if ($action === 'view'): ?>
    <h2>Orders</h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Address</th>
                <th>Total (€)</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="orders-table-body">
            <?php foreach ($orders as $order): ?>
                <tr id="order-row-<?= htmlspecialchars($order['id']) ?>">
                    <td><?= htmlspecialchars($order['id']) ?></td>
                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                    <td><?= htmlspecialchars($order['customer_email']) ?></td>
                    <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                    <td><?= htmlspecialchars($order['delivery_address']) ?></td>
                    <td><?= number_format($order['total_amount'], 2) ?>€</td>
                    <td><?= htmlspecialchars($order['status']) ?></td>
                    <td><?= htmlspecialchars($order['created_at']) ?></td>
                    <td>
                        <a href="orders.php?action=view_details&id=<?= $order['id'] ?>" class="btn btn-sm btn-info">View</a>
                        <a href="orders.php?action=update_status&id=<?= $order['id'] ?>" class="btn btn-sm btn-warning">Update Status</a>
                        <a href="orders.php?action=delete&id=<?= $order['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this order?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <!-- Notification Sound -->
    <audio id="notification-sound" src="sounds/notification.mp3" preload="auto"></audio>
    <!-- Include Bootstrap Toasts Container -->
    <div aria-live="polite" aria-atomic="true" class="position-relative">
        <div id="toasts-container" class="toast-container position-fixed bottom-0 end-0 p-3"></div>
    </div>
    <!-- Real-Time Notifications Script -->
    <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4=" crossorigin="anonymous"></script>
    <!-- Include Bootstrap Bundle JS (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
    <script>
        $(document).ready(function() {
            // Initialize variables
            let lastOrderId = 0; // Will be updated after the first fetch
            const pollingInterval = 5000; // Poll every 5 seconds
            // Initialize lastOrderId on page load
            function initializeLastOrderId() {
                $.ajax({
                    url: 'check_new_orders.php',
                    method: 'GET',
                    data: {
                        last_order_id: 0
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.latest_order_id) {
                            lastOrderId = parseInt(response.latest_order_id);
                            console.log('Initialized lastOrderId:', lastOrderId);
                        } else {
                            console.warn('latest_order_id not found in response:', response);
                        }
                    },
                    error: function(xhr) {
                        console.error('Failed to initialize lastOrderId:', xhr.responseText);
                    }
                });
            }
            // Call the initialization function
            initializeLastOrderId();
            // Function to check for new orders
            function checkForNewOrders() {
                $.ajax({
                    url: 'check_new_orders.php',
                    method: 'GET',
                    data: {
                        last_order_id: lastOrderId
                    },
                    dataType: 'json',
                    success: function(response) {
                        console.log('check_new_orders.php response:', response);
                        if (response.new_orders_count > 0) {
                            // Play notification sound
                            $('#notification-sound')[0].play();
                            // Display a toast notification
                            showToast(`You have ${response.new_orders_count} new order(s)!`, 'success');
                            // Fetch and append new orders dynamically using the current lastOrderId
                            fetchNewOrders(lastOrderId);
                            // Update the lastOrderId after fetching new orders
                            lastOrderId = parseInt(response.latest_order_id);
                            console.log('Updated lastOrderId:', lastOrderId);
                        } else {
                            console.log('No new orders detected.');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error checking for new orders:', xhr.responseText);
                    }
                });
            }
            // Start polling at regular intervals
            setInterval(checkForNewOrders, pollingInterval);
            // Function to display toast notifications
            function showToast(message, type = 'info') {
                // Create a unique ID for the toast
                const toastId = `toast-${Date.now()}`;
                // Append the toast HTML to the toasts container
                $('#toasts-container').append(`
                    <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                ${message}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                `);
                // Initialize and show the toast using Bootstrap's Toast
                const toastElement = new bootstrap.Toast(`#${toastId}`, {
                    delay: 5000
                });
                toastElement.show();
                // Remove the toast from DOM after it hides
                $(`#${toastId}`).on('hidden.bs.toast', function() {
                    $(this).remove();
                });
            }
            // Function to dynamically fetch and append new orders to the table
            function fetchNewOrders(lastOrderId) {
                $.ajax({
                    url: 'fetch_new_orders.php',
                    method: 'GET',
                    data: {
                        last_order_id: lastOrderId
                    },
                    dataType: 'json',
                    success: function(response) {
                        console.log('fetch_new_orders.php response:', response);
                        if (response.new_orders && response.new_orders.length > 0) {
                            response.new_orders.forEach(order => {
                                console.log('Appending new order:', order);
                                const newRow = `
                                    <tr id="order-row-${escapeHtml(order.id)}">
                                        <td>${escapeHtml(order.id)}</td>
                                        <td>${escapeHtml(order.customer_name)}</td>
                                        <td>${escapeHtml(order.customer_email)}</td>
                                        <td>${escapeHtml(order.customer_phone)}</td>
                                        <td>${escapeHtml(order.delivery_address)}</td>
                                        <td>${parseFloat(order.total_amount).toFixed(2)}€</td>
                                        <td>${escapeHtml(order.status)}</td>
                                        <td>${escapeHtml(order.created_at)}</td>
                                        <td>
                                            <a href="orders.php?action=view_details&id=${order.id}" class="btn btn-sm btn-info">View</a>
                                            <a href="orders.php?action=update_status&id=${order.id}" class="btn btn-sm btn-warning">Update Status</a>
                                            <a href="orders.php?action=delete&id=${order.id}" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this order?')">Delete</a>
                                        </td>
                                    </tr>
                                `;
                                $('#orders-table-body').prepend(newRow);
                            });
                        } else {
                            console.log('No new orders to append.');
                        }
                    },
                    error: function(xhr) {
                        console.error('Failed to fetch new orders:', xhr.responseText);
                    }
                });
            }

            function escapeHtml(text) {
                if (text === null || text === undefined) return '';
                return String(text)
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }
        });
    </script>
<?php elseif ($action === 'view_details' && $id > 0): ?>
    <h2>Order Details - ID: <?= htmlspecialchars($order['id']) ?></h2>
    <div class="mb-3">
        <strong>Customer Name:</strong> <?= htmlspecialchars($order['customer_name']) ?><br>
        <strong>Email:</strong> <?= htmlspecialchars($order['customer_email']) ?><br>
        <strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone']) ?><br>
        <strong>Address:</strong> <?= nl2br(htmlspecialchars($order['delivery_address'])) ?><br>
        <strong>Total Amount:</strong> <?= number_format($order['total_amount'], 2) ?>€<br>
        <strong>Status:</strong> <?= htmlspecialchars($status) ?><br>
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
                        <?php
                        if (!empty($item['extras'])) {
                            foreach ($item['extras'] as $extra) {
                                echo htmlspecialchars($extra['name']) . ' x' . htmlspecialchars($extra['quantity']) . ' (+' . number_format($extra['unit_price'], 2) . '€)<br>';
                            }
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        if (!empty($item['drinks'])) {
                            foreach ($item['drinks'] as $drink) {
                                echo htmlspecialchars($drink['name']) . ' x' . htmlspecialchars($drink['quantity']) . ' (+' . number_format($drink['unit_price'], 2) . '€)<br>';
                            }
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td><?= htmlspecialchars($item['special_instructions']) ?: '-' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <a href="orders.php" class="btn btn-secondary">Back to Orders</a>
<?php elseif ($action === 'update_status' && $id > 0): ?>
    <h2>Update Order Status - ID: <?= htmlspecialchars($order['id']) ?></h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
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
        <a href="orders.php" class="btn btn-secondary">Cancel</a>
    </form>
<?php endif; ?>
<!-- Include Bootstrap CSS (if not already included in 'includes/header.php') -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<?php
require_once 'includes/footer.php';
?>