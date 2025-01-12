<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

if (!isset($_SESSION['role'], $_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

function getCount(PDO $pdo, string $table, string $where = '', array $params = []): int
{
    $query = "SELECT COUNT(*) FROM `$table`" . ($where ? " WHERE $where" : '');
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

$role    = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Basic metrics & recent orders (existing)
$metrics       = [];
$recent_orders = [];

// Additional arrays for new features
$top_products      = [];
$daily_orders_data = []; // For Chart.js

// Only admin/super-admin can see advanced analytics
if ($role === 'admin' || $role === 'super-admin') {
    $metrics = [
        [
            'title' => 'Categories',
            'count' => getCount($pdo, 'categories'),
            'color' => 'primary',
            'icon'  => 'fa-list',
            'link'  => 'categories.php'
        ],
        [
            'title' => 'Products',
            'count' => getCount($pdo, 'products'),
            'color' => 'success',
            'icon'  => 'fa-box',
            'link'  => 'products.php'
        ],
        [
            'title' => 'Sizes',
            'count' => getCount($pdo, 'sizes'),
            'color' => 'warning',
            'icon'  => 'fa-ruler-combined',
            'link'  => 'sizes.php'
        ],
        [
            'title' => 'Extras',
            'count' => getCount($pdo, 'extras'),
            'color' => 'info',
            'icon'  => 'fa-concierge-bell',
            'link'  => 'extras.php'
        ],
        [
            'title' => 'Orders',
            'count' => getCount($pdo, 'orders'),
            'color' => 'secondary',
            'icon'  => 'fa-shopping-cart',
            'link'  => 'orders.php'
        ],
    ];

    // Recent orders (existing)
    $stmt = $pdo->prepare('
        SELECT o.id, o.customer_name, o.total_amount, os.status, o.created_at
        FROM orders AS o
        JOIN order_statuses AS os ON o.status_id = os.id
        WHERE o.deleted_at IS NULL
        ORDER BY o.created_at DESC
        LIMIT 5
    ');
    $stmt->execute();
    $recent_orders = $stmt->fetchAll();

    // Extra: Top 3 best-selling products (by the number of order_items referencing them)
    try {
        $stmtTop = $pdo->query("
            SELECT p.name AS product_name, COUNT(oi.id) AS num_sold
            FROM order_items AS oi
            JOIN products AS p ON oi.product_id = p.id
            GROUP BY p.name
            ORDER BY num_sold DESC
            LIMIT 3
        ");
        $top_products = $stmtTop->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Handle error or log it
    }

    // Extra: Orders per day for the last 7 days (for Chart.js)
    try {
        $stmtChart = $pdo->query("
            SELECT DATE(created_at) AS order_date, COUNT(*) AS total
            FROM orders
            WHERE deleted_at IS NULL
            GROUP BY DATE(created_at)
            ORDER BY order_date DESC
            LIMIT 7
        ");
        $rows = $stmtChart->fetchAll(PDO::FETCH_ASSOC);

        // Reverse them so oldest is first in the chart
        $rows = array_reverse($rows);

        // Prepare labels & data for the chart
        foreach ($rows as $r) {
            $daily_orders_data['labels'][] = $r['order_date'];
            $daily_orders_data['data'][]   = (int)$r['total'];
        }
    } catch (PDOException $e) {
        // Handle error or log it
    }
} elseif ($role === 'delivery') {
    // Basic metric for the Delivery role
    $metrics[] = [
        'title' => 'My Orders',
        'count' => getCount($pdo, 'orders', 'delivery_user_id = ? AND deleted_at IS NULL', [$user_id]),
        'color' => 'secondary',
        'icon'  => 'fa-shipping-fast',
        'link'  => 'orders.php'
    ];

    // Recent orders for this delivery user
    $stmt = $pdo->prepare('
        SELECT o.id, o.customer_name, o.total_amount, os.status, o.created_at 
        FROM orders AS o
        JOIN order_statuses AS os ON o.status_id = os.id
        WHERE o.delivery_user_id = ? AND o.deleted_at IS NULL
        ORDER BY o.created_at DESC
        LIMIT 5
    ');
    $stmt->execute([$user_id]);
    $recent_orders = $stmt->fetchAll();
} else {
    echo "<p>Unauthorized access.</p>";
    require_once 'includes/footer.php';
    exit();
}
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1>Hello, <?= htmlspecialchars($_SESSION['username']) ?> (<?= htmlspecialchars($role) ?>)</h1>
            <h4>Dashboard</h4>
        </div>
        <!-- Optional quick links (especially for admin/super-admin) -->
        <?php if ($role === 'admin' || $role === 'super-admin'): ?>
            <div>
                <a href="settings.php" class="btn btn-outline-dark">System Settings</a>
                <a href="users.php" class="btn btn-outline-dark ms-2">User Management</a>
            </div>
        <?php else: ?>
            <div>
                <a href="profile.php" class="btn btn-outline-dark">My Profile</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Metrics / summary cards -->
    <?php if ($metrics): ?>
        <div class="row">
            <?php foreach ($metrics as $metric): ?>
                <div class="col-md-<?= $role === 'admin' || $role === 'super-admin' ? '4' : '12' ?> mb-4">
                    <div class="card text-white bg-<?= $metric['color'] ?> h-100">
                        <div class="card-header d-flex align-items-center">
                            <i class="fas <?= $metric['icon'] ?> me-2"></i><?= $metric['title'] ?>
                        </div>
                        <div class="card-body d-flex flex-column justify-content-center">
                            <h5 class="card-title display-4"><?= htmlspecialchars($metric['count']) ?></h5>
                            <a href="<?= $metric['link'] ?>" class="btn btn-light mt-3">Manage <?= $metric['title'] ?></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Extra insights only for admin/super-admin -->
    <?php if (($role === 'admin' || $role === 'super-admin')): ?>
        <div class="row">
            <!-- Chart column -->
            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header">
                        <h5 class="m-0">Orders in the Last 7 Days</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($daily_orders_data)): ?>
                            <canvas id="ordersChart" style="width:100%; height:300px;"></canvas>
                            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                            <script>
                                const ctx = document.getElementById('ordersChart').getContext('2d');
                                const ordersChart = new Chart(ctx, {
                                    type: 'line',
                                    data: {
                                        labels: <?= json_encode($daily_orders_data['labels'] ?? []) ?>,
                                        datasets: [{
                                            label: 'Orders',
                                            data: <?= json_encode($daily_orders_data['data'] ?? []) ?>,
                                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                            borderColor: 'rgba(54, 162, 235, 1)',
                                            borderWidth: 2,
                                            fill: true,
                                            tension: 0.3
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        scales: {
                                            y: {
                                                beginAtZero: true
                                            }
                                        }
                                    }
                                });
                            </script>
                        <?php else: ?>
                            <p class="text-muted">No recent orders to display.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Top products column -->
            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header">
                        <h5 class="m-0">Top 3 Best-Selling Products</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($top_products)): ?>
                            <table class="table table-sm table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>Sold (Units)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_products as $p): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($p['product_name']) ?></td>
                                            <td><?= (int)$p['num_sold'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="text-muted">No data for best-selling products.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recent Orders -->
    <h3 class="mt-5">Recent Orders</h3>
    <?php if ($recent_orders): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover mt-3">
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
                                <a href="orders.php?action=view_details&id=<?= htmlspecialchars($order['id']) ?>"
                                    class="btn btn-sm btn-info"
                                    title="View Order #<?= htmlspecialchars($order['id']) ?>">
                                    View
                                </a>
                                <?php if ($role === 'admin' || $role === 'super-admin'): ?>
                                    <a href="orders.php?action=update_status&id=<?= htmlspecialchars($order['id']) ?>"
                                        class="btn btn-sm btn-warning"
                                        title="Update Status for Order #<?= htmlspecialchars($order['id']) ?>">
                                        Update Status
                                    </a>
                                <?php elseif ($role === 'delivery'): ?>
                                    <a href="orders.php?action=mark_delivered&id=<?= htmlspecialchars($order['id']) ?>"
                                        class="btn btn-sm btn-success"
                                        title="Mark Delivered for Order #<?= htmlspecialchars($order['id']) ?>">
                                        Mark as Delivered
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <a href="orders.php" class="btn btn-primary">View All Orders</a>
    <?php else: ?>
        <p>No orders found.</p>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>