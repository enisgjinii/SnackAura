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

<!-- Dashboard Content -->
<div class="dashboard-content">
    <!-- Welcome Section -->
    <div class="welcome-section">
        <div class="welcome-text">
            <h1 class="welcome-title">Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!</h1>
            <p class="welcome-subtitle">Here's what's happening with your store today.</p>
        </div>
        <?php if ($role === 'admin' || $role === 'super-admin'): ?>
            <div class="welcome-actions">
                <a href="users.php" class="btn btn-primary">Manage Users</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Metrics Cards -->
    <?php if ($metrics): ?>
        <div class="metrics-grid">
            <?php foreach ($metrics as $metric): ?>
                <div class="metric-card">
                    <div class="metric-icon">
                        <i class="fas <?= $metric['icon'] ?>"></i>
                    </div>
                    <div class="metric-content">
                        <h3 class="metric-number"><?= htmlspecialchars($metric['count']) ?></h3>
                        <p class="metric-title"><?= $metric['title'] ?></p>
                        <a href="<?= $metric['link'] ?>" class="metric-link">Manage <?= $metric['title'] ?></a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Revenue and Charts Section -->
    <div class="charts-section">
        <!-- Revenue Summary -->
        <div class="chart-card">
            <div class="chart-header">
                <h3>Revenue Summary</h3>
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="chart-content">
                <div class="revenue-item">
                    <span class="revenue-label">Last 7 Days</span>
                    <span class="revenue-amount">€<?= number_format($revenue_summary['last_7_days'] ?? 0, 2) ?></span>
                </div>
                <div class="revenue-item">
                    <span class="revenue-label">Current Month</span>
                    <span class="revenue-amount">€<?= number_format($revenue_summary['current_month'] ?? 0, 2) ?></span>
                </div>
            </div>
        </div>

        <!-- Order Status Distribution -->
        <div class="chart-card">
            <div class="chart-header">
                <h3>Order Status Distribution</h3>
                <i class="fas fa-chart-pie"></i>
            </div>
            <div class="chart-content">
                <?php if ($order_status_distribution): ?>
                    <canvas id="orderStatusChart"></canvas>
                <?php else: ?>
                    <p class="no-data">No order data available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Low Stock Products -->
    <div class="section-card">
        <div class="section-header">
            <h3>Low Stock Products</h3>
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="section-content">
            <?php if ($low_stock_products): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($low_stock_products as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><?= htmlspecialchars($product['stock']) ?></td>
                                    <td>
                                        <span class="status-badge status-warning">Low Stock</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="no-data">No low-stock products.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="section-card">
        <div class="section-header">
            <h3>Recent Orders</h3>
            <a href="orders.php" class="view-all-link">View All Orders</a>
        </div>
        <div class="section-content">
            <?php if ($recent_orders): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($order['id']) ?></td>
                                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                    <td>€<?= number_format($order['total_amount'], 2) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($order['status']) ?>">
                                            <?= htmlspecialchars($order['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                                    <td>
                                        <a href="orders.php?action=view_details&id=<?= htmlspecialchars($order['id']) ?>" class="btn btn-sm btn-primary">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="no-data">No recent orders found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js Script -->
<?php if ($order_status_distribution): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('orderStatusChart').getContext('2d');
        const orderStatusChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($order_status_distribution, 'status')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($order_status_distribution, 'count')) ?>,
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });
    </script>
<?php endif; ?>

<style>
    /* Dashboard Styles */
    .dashboard-content {
        padding: 2rem;
        background: var(--content-bg);
        min-height: 100vh;
    }

    /* Welcome Section */
    .welcome-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding: 1.5rem;
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
    }

    .welcome-title {
        font-size: 1.875rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 0.5rem 0;
    }

    .welcome-subtitle {
        color: #64748b;
        margin: 0;
        font-size: 1rem;
    }

    .welcome-actions {
        display: flex;
        gap: 1rem;
    }

    /* Metrics Grid */
    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .metric-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        transition: all 0.2s ease;
    }

    .metric-card:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    .metric-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        font-size: 1.25rem;
        color: white;
    }

    .metric-card:nth-child(1) .metric-icon { background: #3b82f6; }
    .metric-card:nth-child(2) .metric-icon { background: #10b981; }
    .metric-card:nth-child(3) .metric-icon { background: #06b6d4; }
    .metric-card:nth-child(4) .metric-icon { background: #8b5cf6; }

    .metric-number {
        font-size: 2rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 0.5rem 0;
    }

    .metric-title {
        color: #64748b;
        margin: 0 0 1rem 0;
        font-weight: 500;
    }

    .metric-link {
        color: #3b82f6;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.875rem;
    }

    .metric-link:hover {
        text-decoration: underline;
    }

    /* Charts Section */
    .charts-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .chart-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .chart-header h3 {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 600;
        color: #0f172a;
    }

    .chart-header i {
        color: #64748b;
        font-size: 1.25rem;
    }

    .chart-content {
        min-height: 200px;
    }

    /* Revenue Items */
    .revenue-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 0;
        border-bottom: 1px solid var(--border-color);
    }

    .revenue-item:last-child {
        border-bottom: none;
    }

    .revenue-label {
        color: #64748b;
        font-weight: 500;
    }

    .revenue-amount {
        font-size: 1.25rem;
        font-weight: 700;
        color: #0f172a;
    }

    /* Section Cards */
    .section-card {
        background: white;
        border-radius: 12px;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        overflow: hidden;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        background: #f8fafc;
    }

    .section-header h3 {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 600;
        color: #0f172a;
    }

    .section-header i {
        color: #f59e0b;
        font-size: 1.25rem;
    }

    .view-all-link {
        color: #3b82f6;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.875rem;
    }

    .view-all-link:hover {
        text-decoration: underline;
    }

    .section-content {
        padding: 1.5rem;
    }

    /* Tables */
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
    }

    .data-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--border-color);
        color: #374151;
    }

    .data-table tbody tr:hover {
        background: #f8fafc;
    }

    /* Status Badges */
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    .status-warning {
        background: #fef3c7;
        color: #92400e;
    }

    .status-pending {
        background: #dbeafe;
        color: #1e40af;
    }

    .status-completed {
        background: #d1fae5;
        color: #065f46;
    }

    .status-cancelled {
        background: #fee2e2;
        color: #991b1b;
    }

    /* No Data */
    .no-data {
        color: #64748b;
        text-align: center;
        padding: 2rem;
        font-style: italic;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .dashboard-content {
            padding: 1rem;
        }

        .welcome-section {
            flex-direction: column;
            gap: 1rem;
            text-align: center;
        }

        .metrics-grid {
            grid-template-columns: 1fr;
        }

        .charts-section {
            grid-template-columns: 1fr;
        }

        .welcome-title {
            font-size: 1.5rem;
        }
    }
</style>

<?php require_once 'includes/footer.php'; ?>