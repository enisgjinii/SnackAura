<?php
// admin/dashboard.php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Check if the user is logged in
if (!isset($_SESSION['role']) || !isset($_SESSION['user_id'])) {
    // Redirect to login page or show an error
    header('Location: login.php');
    exit();
}

// Function to fetch count of records from a table with optional WHERE clause
function getCount($pdo, $table, $where = '', $params = [])
{
    $query = "SELECT COUNT(*) FROM `$table`";
    if ($where) {
        $query .= " WHERE $where";
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

// Determine user role and ID
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Initialize variables
$total_categories = 0;
$total_products = 0;
$total_sizes = 0;
$total_extras = 0;
$total_orders = 0;
$recent_orders = [];

if ($role === 'admin') {
    // Fetch metrics for admin
    $total_categories = getCount($pdo, 'categories');
    $total_products = getCount($pdo, 'products');
    $total_sizes = getCount($pdo, 'sizes');
    $total_extras = getCount($pdo, 'extras');
    $total_orders = getCount($pdo, 'orders');

    // Fetch recent orders (latest 5)
    $stmt = $pdo->prepare('
        SELECT orders.id, orders.customer_name, orders.total_amount, order_statuses.status, orders.created_at 
        FROM orders 
        JOIN order_statuses ON orders.status_id = order_statuses.id 
        WHERE orders.deleted_at IS NULL
        ORDER BY orders.created_at DESC 
        LIMIT 5
    ');
    $stmt->execute();
    $recent_orders = $stmt->fetchAll();
} elseif ($role === 'delivery') {
    // Fetch metrics for delivery role
    $total_orders = getCount($pdo, 'orders', 'delivery_user_id = ? AND deleted_at IS NULL', [$user_id]);

    // Fetch recent orders assigned to the delivery person (latest 5)
    $stmt = $pdo->prepare('
        SELECT orders.id, orders.customer_name, orders.total_amount, order_statuses.status, orders.created_at 
        FROM orders 
        JOIN order_statuses ON orders.status_id = order_statuses.id 
        WHERE orders.delivery_user_id = ? AND orders.deleted_at IS NULL
        ORDER BY orders.created_at DESC 
        LIMIT 5
    ');
    $stmt->execute([$user_id]);
    $recent_orders = $stmt->fetchAll();
} else {
    // For other roles (e.g., waiter), implement as needed
    echo "<p>Unauthorized access.</p>";
    require_once 'includes/footer.php';
    exit();
}
?>
<h1>Hello, <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($role); ?>)</h1>
<h4>Dashboard</h4>

<?php if ($role === 'admin'): ?>
    <div class="row">
        <!-- Categories Card -->
        <div class="col-md-4">
            <div class="card text-white bg-primary mb-3">
                <div class="card-header">Categories</div>
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($total_categories) ?></h5>
                    <p class="card-text">Total Categories</p>
                    <a href="categories.php" class="btn btn-light">Manage Categories</a>
                </div>
            </div>
        </div>
        <!-- Products Card -->
        <div class="col-md-4">
            <div class="card text-white bg-success mb-3">
                <div class="card-header">Products</div>
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($total_products) ?></h5>
                    <p class="card-text">Total Products</p>
                    <a href="products.php" class="btn btn-light">Manage Products</a>
                </div>
            </div>
        </div>
        <!-- Sizes Card -->
        <div class="col-md-4">
            <div class="card text-white bg-warning mb-3">
                <div class="card-header">Sizes</div>
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($total_sizes) ?></h5>
                    <p class="card-text">Total Sizes</p>
                    <a href="sizes.php" class="btn btn-light">Manage Sizes</a>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <!-- Extras Card -->
        <div class="col-md-6">
            <div class="card text-white bg-info mb-3">
                <div class="card-header">Extras</div>
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($total_extras) ?></h5>
                    <p class="card-text">Total Extras</p>
                    <a href="extras.php" class="btn btn-light">Manage Extras</a>
                </div>
            </div>
        </div>
        <!-- Orders Card -->
        <div class="col-md-6">
            <div class="card text-white bg-secondary mb-3">
                <div class="card-header">Orders</div>
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($total_orders) ?></h5>
                    <p class="card-text">Total Orders</p>
                    <a href="orders.php" class="btn btn-light">Manage Orders</a>
                </div>
            </div>
        </div>
    </div>
<?php elseif ($role === 'delivery'): ?>
    <div class="row">
        <!-- My Orders Card for Delivery Role -->
        <div class="col-md-12">
            <div class="card text-white bg-secondary mb-3">
                <div class="card-header">My Orders</div>
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($total_orders) ?></h5>
                    <p class="card-text">Total Assigned Orders</p>
                    <a href="orders.php" class="btn btn-light">View My Orders</a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<h3>Recent Orders</h3>
<?php if ($recent_orders): ?>
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Order ID</th>
                <th>Customer Name</th>
                <th>Total (€)</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recent_orders as $order): ?>
                <tr>
                    <td><?= htmlspecialchars($order['id']) ?></td>
                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                    <td><?= number_format($order['total_amount'], 2) ?>€</td>
                    <td><?= htmlspecialchars($order['status']) ?></td>
                    <td><?= htmlspecialchars($order['created_at']) ?></td>
                    <td>
                        <a href="orders.php?action=view_details&id=<?= htmlspecialchars($order['id']) ?>" class="btn btn-sm btn-info">View</a>
                        <?php if ($role === 'admin'): ?>
                            <a href="orders.php?action=update_status&id=<?= htmlspecialchars($order['id']) ?>" class="btn btn-sm btn-warning">Update Status</a>
                        <?php elseif ($role === 'delivery'): ?>
                            <a href="orders.php?action=mark_delivered&id=<?= htmlspecialchars($order['id']) ?>" class="btn btn-sm btn-success">Mark as Delivered</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <a href="orders.php" class="btn btn-primary">View All Orders</a>
<?php else: ?>
    <p>No orders found.</p>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>