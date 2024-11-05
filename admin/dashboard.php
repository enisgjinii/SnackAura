<?php
// admin/dashboard.php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Function to fetch count of records from a table
function getCount($pdo, $table)
{
    $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
    return $stmt->fetchColumn();
}

// Fetch metrics
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
    ORDER BY orders.created_at DESC 
    LIMIT 5
');
$stmt->execute();
$recent_orders = $stmt->fetchAll();
?>

<h2>Dashboard</h2>

<div class="row">
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
                        <a href="orders.php?action=view_details&id=<?= $order['id'] ?>" class="btn btn-sm btn-info">View</a>
                        <a href="orders.php?action=update_status&id=<?= $order['id'] ?>" class="btn btn-sm btn-warning">Update Status</a>
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