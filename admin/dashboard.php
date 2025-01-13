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

function getSum(PDO $pdo, string $column, string $table, string $where = '', array $params = []): float
{
    $query = "SELECT SUM($column) FROM `$table`" . ($where ? " WHERE $where" : '');
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return (float)$stmt->fetchColumn();
}

$role    = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Basic metrics & recent orders (existing)
$metrics       = [];
$recent_orders = [];

// Additional arrays for new features
$order_status_distribution = [];
$low_stock_products        = [];
$revenue_summary           = [];

// Admin/super-admin features
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
            'title' => 'Customers',
            'count' => getCount($pdo, 'users', 'role = ?', ['customer']),
            'color' => 'info',
            'icon'  => 'fa-users',
            'link'  => 'customers.php'
        ],
        [
            'title' => 'Orders',
            'count' => getCount($pdo, 'orders'),
            'color' => 'secondary',
            'icon'  => 'fa-shopping-cart',
            'link'  => 'orders.php'
        ],
    ];

    // Revenue Summary (7 days + current month)
    $revenue_summary = [
        'last_7_days' => getSum(
            $pdo,
            'total_amount',
            'orders',
            'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND deleted_at IS NULL'
        ),
        'current_month' => getSum(
            $pdo,
            'total_amount',
            'orders',
            'MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) AND deleted_at IS NULL'
        ),
    ];

    // Order status distribution for Pie Chart
    try {
        $stmt = $pdo->query("
            SELECT os.status, COUNT(*) AS count
            FROM orders o
            JOIN order_statuses os ON o.status_id = os.id
            WHERE o.deleted_at IS NULL
            GROUP BY os.status
        ");
        $order_status_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Handle error
    }

    // Low Stock Products
    try {
        $stmtLowStock = $pdo->query("
            SELECT name, stock
            FROM products
            WHERE stock <= 10
            ORDER BY stock ASC
            LIMIT 5
        ");
        $low_stock_products = $stmtLowStock->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Handle error
    }

    // Recent Orders
    $stmt = $pdo->prepare("
        SELECT o.id, o.customer_name, o.total_amount, os.status, o.created_at
        FROM orders AS o
        JOIN order_statuses AS os ON o.status_id = os.id
        WHERE o.deleted_at IS NULL
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
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
        <?php if ($role === 'admin' || $role === 'super-admin'): ?>
            <div>
                <a href="users.php" class="btn btn-outline-dark ms-2">Manage Users</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Metrics -->
    <?php if ($metrics): ?>
        <div class="row">
            <?php foreach ($metrics as $metric): ?>
                <div class="col-md-3 mb-4">
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

    <!-- Revenue Summary -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="m-0">Revenue Summary</h5>
                </div>
                <div class="card-body">
                    <p><strong>Last 7 Days:</strong> €<?= number_format($revenue_summary['last_7_days'] ?? 0, 2) ?></p>
                    <p><strong>Current Month:</strong> €<?= number_format($revenue_summary['current_month'] ?? 0, 2) ?></p>
                </div>
            </div>
        </div>

        <!-- Order Status Distribution -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="m-0">Order Status Distribution</h5>
                </div>
                <div class="card-body">
                    <?php if ($order_status_distribution): ?>
                        <canvas id="orderStatusChart"></canvas>
                        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                        <script>
                            const ctx = document.getElementById('orderStatusChart').getContext('2d');
                            const orderStatusChart = new Chart(ctx, {
                                type: 'pie',
                                data: {
                                    labels: <?= json_encode(array_column($order_status_distribution, 'status')) ?>,
                                    datasets: [{
                                        data: <?= json_encode(array_column($order_status_distribution, 'count')) ?>,
                                        backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545'],
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    plugins: {
                                        legend: {
                                            position: 'bottom'
                                        }
                                    }
                                }
                            });
                        </script>
                    <?php else: ?>
                        <p class="text-muted">No order data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock Products -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="m-0">Low Stock Products</h5>
        </div>
        <div class="card-body">
            <?php if ($low_stock_products): ?>
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($low_stock_products as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td><?= htmlspecialchars($product['stock']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">No low-stock products.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Orders -->
    <h3 class="mt-5">Recent Orders</h3>
    <?php if ($recent_orders): ?>
        <div class="table-responsive">
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
                            <td>€<?= number_format($order['total_amount'], 2) ?></td>
                            <td><?= htmlspecialchars($order['status']) ?></td>
                            <td><?= htmlspecialchars($order['created_at']) ?></td>
                            <td>
                                <a href="orders.php?action=view_details&id=<?= htmlspecialchars($order['id']) ?>" class="btn btn-sm btn-info">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <a href="orders.php" class="btn btn-primary">View All Orders</a>
    <?php else: ?>
        <p>No recent orders found.</p>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>