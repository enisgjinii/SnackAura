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

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

$metrics = [];
$recent_orders = [];

if ($role === 'admin' || $role === 'super-admin') {
    $metrics = [
        ['title' => 'Categories', 'count' => getCount($pdo, 'categories'), 'color' => 'primary', 'icon' => 'fa-list', 'link' => 'categories.php'],
        ['title' => 'Products', 'count' => getCount($pdo, 'products'), 'color' => 'success', 'icon' => 'fa-box', 'link' => 'products.php'],
        ['title' => 'Sizes', 'count' => getCount($pdo, 'sizes'), 'color' => 'warning', 'icon' => 'fa-ruler-combined', 'link' => 'sizes.php'],
        ['title' => 'Extras', 'count' => getCount($pdo, 'extras'), 'color' => 'info', 'icon' => 'fa-concierge-bell', 'link' => 'extras.php'],
        ['title' => 'Orders', 'count' => getCount($pdo, 'orders'), 'color' => 'secondary', 'icon' => 'fa-shopping-cart', 'link' => 'orders.php']
    ];

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
    $metrics[] = [
        'title' => 'My Orders',
        'count' => getCount($pdo, 'orders', 'delivery_user_id = ? AND deleted_at IS NULL', [$user_id]),
        'color' => 'secondary',
        'icon' => 'fa-shipping-fast',
        'link' => 'orders.php'
    ];

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
    </div>

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
                                <a href="orders.php?action=view_details&id=<?= htmlspecialchars($order['id']) ?>" class="btn btn-sm btn-info">View</a>
                                <?php if ($role === 'admin' || $role === 'super-admin'): ?>
                                    <a href="orders.php?action=update_status&id=<?= htmlspecialchars($order['id']) ?>" class="btn btn-sm btn-warning">Update Status</a>
                                <?php elseif ($role === 'delivery'): ?>
                                    <a href="orders.php?action=mark_delivered&id=<?= htmlspecialchars($order['id']) ?>" class="btn btn-sm btn-success">Mark as Delivered</a>
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