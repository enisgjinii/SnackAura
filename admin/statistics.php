<?php
ob_start();
require_once 'includes/db_connect.php';
require_once 'includes/header.php';
require '../vendor/autoload.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super-admin') {
    header('HTTP/1.1 403 Forbidden');
    echo "<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>";
    require_once 'includes/footer.php';
    exit();
}

function sanitize($data)
{
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

$filters = [
    'start_date' => $_GET['start_date'] ?? '',
    'end_date' => $_GET['end_date'] ?? '',
    'filter_day' => $_GET['filter_day'] ?? '',
    'status' => $_GET['status'] ?? '',
    'store_id' => $_GET['store_id'] ?? '',
    'payment_method' => $_GET['payment_method'] ?? '',
    'waiter_id' => $_GET['waiter_id'] ?? '',
    'delivery_person_id' => $_GET['delivery_person_id'] ?? '',
    'coupon_usage' => $_GET['coupon_usage'] ?? '',
    'tip_min' => $_GET['tip_min'] ?? '',
    'tip_max' => $_GET['tip_max'] ?? ''
];

// Fetch distinct payment methods
$payment_methods_stmt = $pdo->prepare("SELECT DISTINCT payment_method FROM orders WHERE deleted_at IS NULL");
$payment_methods_stmt->execute();
$payment_methods = $payment_methods_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch stores
$stores_stmt = $pdo->prepare("SELECT id, name FROM stores");
$stores_stmt->execute();
$stores = $stores_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch waiters
$waiters_stmt = $pdo->prepare("SELECT id, username FROM users WHERE role = 'waiter'");
$waiters_stmt->execute();
$waiters = $waiters_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch delivery persons
$delivery_persons_stmt = $pdo->prepare("SELECT id, username FROM users WHERE role = 'delivery'");
$delivery_persons_stmt->execute();
$delivery_persons = $delivery_persons_stmt->fetchAll(PDO::FETCH_ASSOC);

// Define possible order statuses
$statuses = ['New Order', 'Kitchen', 'On the Way', 'Delivered', 'Canceled'];

// Function to apply filters to SQL queries
function applyFilters($sql, $filters, &$params)
{
    if ($filters['start_date'] && $filters['end_date']) {
        $sql .= " AND DATE(o.created_at) BETWEEN ? AND ?";
        $params[] = $filters['start_date'];
        $params[] = $filters['end_date'];
    }

    if ($filters['filter_day']) {
        $sql .= " AND DAYNAME(o.created_at) = ?";
        $params[] = $filters['filter_day'];
    }

    if ($filters['status']) {
        $sql .= " AND o.status = ?";
        $params[] = $filters['status'];
    }

    if ($filters['store_id']) {
        $sql .= " AND o.store_id = ?";
        $params[] = $filters['store_id'];
    }

    if ($filters['payment_method']) {
        $sql .= " AND o.payment_method = ?";
        $params[] = $filters['payment_method'];
    }

    if ($filters['waiter_id']) {
        $sql .= " AND o.user_id = ?";
        $params[] = $filters['waiter_id'];
    }

    if ($filters['delivery_person_id']) {
        $sql .= " AND o.delivery_user_id = ?";
        $params[] = $filters['delivery_person_id'];
    }

    if ($filters['coupon_usage'] === 'with') {
        $sql .= " AND o.coupon_code IS NOT NULL AND o.coupon_code != ''";
    } elseif ($filters['coupon_usage'] === 'without') {
        $sql .= " AND (o.coupon_code IS NULL OR o.coupon_code = '')";
    }

    if ($filters['tip_min'] !== '' && is_numeric($filters['tip_min'])) {
        $sql .= " AND o.tip_amount >= ?";
        $params[] = $filters['tip_min'];
    }
    if ($filters['tip_max'] !== '' && is_numeric($filters['tip_max'])) {
        $sql .= " AND o.tip_amount <= ?";
        $params[] = $filters['tip_max'];
    }

    return $sql;
}

// Function to fetch data based on SQL query and filters
function fetchData($pdo, $baseSql, $filters, $limit = null)
{
    $params = [];
    $sql = applyFilters($baseSql, $filters, $params);
    if ($limit) {
        $sql .= " LIMIT $limit";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get summary statistics
function getSummaryStatistics($pdo, $filters)
{
    $sql = "SELECT COUNT(o.id) AS total_orders, 
                   SUM(o.total_amount) AS total_sales, 
                   AVG(o.total_amount) AS average_order_value 
            FROM orders o 
            WHERE o.deleted_at IS NULL";
    $params = [];
    $sql = applyFilters($sql, $filters, $params);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get total tips
function getTotalTips($pdo, $filters)
{
    $sql = "SELECT SUM(o.tip_amount) AS total_tips 
            FROM orders o 
            WHERE o.deleted_at IS NULL";
    $params = [];
    $sql = applyFilters($sql, $filters, $params);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total_tips'] ?? 0;
}

// Function to get VAT statistics
function getVATStatistics($pdo, $filters, $rate)
{
    $sql = "SELECT SUM(o.total_amount) * ? AS vat 
            FROM orders o 
            WHERE o.deleted_at IS NULL";
    $params = [$rate];
    $sql = applyFilters($sql, $filters, $params);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['vat'] ?? 0;
}

$summary_stats = getSummaryStatistics($pdo, $filters);
$total_tips = getTotalTips($pdo, $filters);
$vat1 = getVATStatistics($pdo, $filters, 0.01);
$vat2 = getVATStatistics($pdo, $filters, 0.02);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Sales Statistics Dashboard</title>
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

</head>

<body class="p-3">
    <div class="container-fluid">
        <h1 class="mb-4">Sales Statistics Dashboard</h1>
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-primary h-100">
                    <div class="card-body">
                        <h5 class="card-title" data-bs-toggle="tooltip" data-bs-placement="top" title="Total revenue generated from all orders.">Total Sales (€)</h5>
                        <p class="card-text fs-4"><?= number_format($summary_stats['total_sales'] ?? 0, 2) ?></p>
                        <span class="text-muted small">Aggregate sales excluding tips and discounts.</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-success h-100">
                    <div class="card-body">
                        <h5 class="card-title" data-bs-toggle="tooltip" data-bs-placement="top" title="Total number of orders placed.">Total Orders</h5>
                        <p class="card-text fs-4"><?= sanitize($summary_stats['total_orders'] ?? 0) ?></p>
                        <span class="text-muted small">Count of all active orders.</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-warning h-100">
                    <div class="card-body">
                        <h5 class="card-title" data-bs-toggle="tooltip" data-bs-placement="top" title="Average value per order.">Average Order Value (€)</h5>
                        <p class="card-text fs-4"><?= number_format($summary_stats['average_order_value'] ?? 0, 2) ?></p>
                        <span class="text-muted small">Average revenue per order.</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-info h-100">
                    <div class="card-body">
                        <h5 class="card-title" data-bs-toggle="tooltip" data-bs-placement="top" title="Total amount of tips received from all orders.">Total Tips (€)</h5>
                        <p class="card-text fs-4"><?= number_format($total_tips, 2) ?></p>
                        <span class="text-muted small">Aggregate tips excluding sales.</span>
                    </div>
                </div>
            </div>
        </div>
        <!-- VAT Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card text-white bg-secondary h-100">
                    <div class="card-body">
                        <h5 class="card-title" data-bs-toggle="tooltip" data-bs-placement="top" title="VAT at 1% rate based on total sales.">VAT (Tvsh 1) (€)</h5>
                        <p class="card-text fs-4"><?= number_format($vat1, 2) ?></p>
                        <span class="text-muted small">Calculated as 1% of total sales.</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card text-white bg-secondary h-100">
                    <div class="card-body">
                        <h5 class="card-title" data-bs-toggle="tooltip" data-bs-placement="top" title="VAT at 2% rate based on total sales.">VAT (Tvsh 2) (€)</h5>
                        <p class="card-text fs-4"><?= number_format($vat2, 2) ?></p>
                        <span class="text-muted small">Calculated as 2% of total sales.</span>
                    </div>
                </div>
            </div>
        </div>
        <!-- Custom Filters -->
        <div class="card mb-4">
            <div class="card-header"><strong>Custom Filters</strong></div>
            <div class="card-body">
                <form method="GET" action="statistics.php" class="row g-3">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="text" class="form-control" id="start_date" name="start_date" placeholder="YYYY-MM-DD" value="<?= sanitize($filters['start_date']) ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Select the beginning date for the sales data range.">
                        <span class="text-muted small">Format: YYYY-MM-DD</span>
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="text" class="form-control" id="end_date" name="end_date" placeholder="YYYY-MM-DD" value="<?= sanitize($filters['end_date']) ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Select the ending date for the sales data range.">
                        <span class="text-muted small">Format: YYYY-MM-DD</span>
                    </div>
                    <div class="col-md-3">
                        <label for="filter_day" class="form-label">Day of Week</label>
                        <select class="form-select" id="filter_day" name="filter_day" data-bs-toggle="tooltip" data-bs-placement="top" title="Filter sales data for a specific day of the week.">
                            <option value="">-- Select Day --</option>
                            <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?>
                                <option value="<?= $day ?>" <?= $filters['filter_day'] === $day ? 'selected' : '' ?>><?= $day ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="text-muted small">Optional filter by day.</span>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Order Status</label>
                        <select class="form-select" id="status" name="status" data-bs-toggle="tooltip" data-bs-placement="top" title="Filter sales data based on order status.">
                            <option value="">-- Select Status --</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= $status ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="text-muted small">Optional filter by status.</span>
                    </div>
                    <div class="col-md-3">
                        <label for="store_id" class="form-label">Store</label>
                        <select class="form-select" id="store_id" name="store_id" data-bs-toggle="tooltip" data-bs-placement="top" title="Filter sales data for a specific store.">
                            <option value="">-- Select Store --</option>
                            <?php foreach ($stores as $store): ?>
                                <option value="<?= $store['id'] ?>" <?= $filters['store_id'] == $store['id'] ? 'selected' : '' ?>><?= sanitize($store['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="text-muted small">Select a store to view its sales.</span>
                    </div>
                    <div class="col-md-3">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select class="form-select" id="payment_method" name="payment_method" data-bs-toggle="tooltip" data-bs-placement="top" title="Filter sales data based on payment method.">
                            <option value="">-- Select Payment Method --</option>
                            <?php foreach (['Card', 'PayPal', 'Stripe', 'Cash'] as $method): ?>
                                <option value="<?= sanitize($method) ?>" <?= $filters['payment_method'] === $method ? 'selected' : '' ?>><?= sanitize($method) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="text-muted small">Choose a payment type.</span>
                    </div>
                    <div class="col-md-3">
                        <label for="waiter_id" class="form-label">Waiter</label>
                        <select class="form-select" id="waiter_id" name="waiter_id" data-bs-toggle="tooltip" data-bs-placement="top" title="Filter sales data for a specific waiter.">
                            <option value="">-- Select Waiter --</option>
                            <?php foreach ($waiters as $waiter): ?>
                                <option value="<?= $waiter['id'] ?>" <?= $filters['waiter_id'] == $waiter['id'] ? 'selected' : '' ?>><?= sanitize($waiter['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="text-muted small">Optional filter by waiter.</span>
                    </div>
                    <div class="col-md-3">
                        <label for="delivery_person_id" class="form-label">Delivery Person</label>
                        <select class="form-select" id="delivery_person_id" name="delivery_person_id" data-bs-toggle="tooltip" data-bs-placement="top" title="Filter sales data for a specific delivery person.">
                            <option value="">-- Select Delivery Person --</option>
                            <?php foreach ($delivery_persons as $dp): ?>
                                <option value="<?= $dp['id'] ?>" <?= $filters['delivery_person_id'] == $dp['id'] ? 'selected' : '' ?>><?= sanitize($dp['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="text-muted small">Optional filter by delivery personnel.</span>
                    </div>
                    <div class="col-md-3">
                        <label for="coupon_usage" class="form-label">Coupon Usage</label>
                        <select class="form-select" id="coupon_usage" name="coupon_usage" data-bs-toggle="tooltip" data-bs-placement="top" title="Filter orders based on coupon usage.">
                            <option value="">-- Select Coupon Usage --</option>
                            <option value="with" <?= $filters['coupon_usage'] === 'with' ? 'selected' : '' ?>>With Coupon</option>
                            <option value="without" <?= $filters['coupon_usage'] === 'without' ? 'selected' : '' ?>>Without Coupon</option>
                        </select>
                        <span class="text-muted small">Filter orders that used or did not use a coupon.</span>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tip Amount Range (€)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form-control" name="tip_min" placeholder="Min" value="<?= sanitize($filters['tip_min']) ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Enter the minimum tip amount to filter orders.">
                            <span class="input-group-text">-</span>
                            <input type="number" step="0.01" class="form-control" name="tip_max" placeholder="Max" value="<?= sanitize($filters['tip_max']) ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Enter the maximum tip amount to filter orders.">
                        </div>
                        <span class="text-muted small">Specify tip range for filtering.</span>
                    </div>
                    <div class="col-md-12 align-self-end">
                        <button type="submit" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Apply the selected filters to update the statistics.">Apply Filters</button>
                        <a href="statistics.php" class="btn btn-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="Reset all filters to view complete data.">Reset</a>
                    </div>
                </form>
            </div>
        </div>
        <!-- Data Tables -->
        <div class="row">
            <?php
            $sections = [
                [
                    'title' => '1. Sales Statistics per Product',
                    'headerClass' => 'bg-success',
                    'query' => "SELECT p.name AS product_name, 
                                      SUM(oi.quantity) AS total_quantity_sold, 
                                      SUM(oi.price * oi.quantity) AS total_sales 
                               FROM order_items oi 
                               JOIN products p ON oi.product_id = p.id 
                               JOIN orders o ON oi.order_id = o.id 
                               WHERE o.deleted_at IS NULL",
                    'groupBy' => "p.name",
                    'orderBy' => "total_sales DESC",
                    'limit' => 10,
                    'tableId' => 'productSalesTable',
                    'columns' => ['Product Name', 'Total Quantity Sold', 'Total Sales (€)'],
                    'dataMapping' => function ($row) {
                        return [
                            sanitize($row['product_name']),
                            sanitize($row['total_quantity_sold']),
                            number_format($row['total_sales'], 2)
                        ];
                    }
                ],
                [
                    'title' => '2. Daily Sales Statistics',
                    'headerClass' => 'bg-info',
                    'query' => "SELECT DATE(o.created_at) AS sale_date, 
                                      COUNT(o.id) AS total_orders, 
                                      SUM(o.total_amount) AS total_sales 
                               FROM orders o 
                               WHERE o.deleted_at IS NULL",
                    'groupBy' => "DATE(o.created_at)",
                    'orderBy' => "sale_date DESC",
                    'limit' => null,
                    'tableId' => 'dailySalesTable',
                    'columns' => ['Sale Date', 'Total Orders', 'Total Sales (€)'],
                    'dataMapping' => function ($row) {
                        return [
                            sanitize($row['sale_date']),
                            sanitize($row['total_orders']),
                            number_format($row['total_sales'], 2)
                        ];
                    }
                ],
                [
                    'title' => '3. Weekly Sales Statistics',
                    'headerClass' => 'bg-warning',
                    'query' => "SELECT YEAR(o.created_at) AS sale_year, 
                                      WEEK(o.created_at, 1) AS sale_week, 
                                      COUNT(o.id) AS total_orders, 
                                      SUM(o.total_amount) AS total_sales 
                               FROM orders o 
                               WHERE o.deleted_at IS NULL",
                    'groupBy' => "sale_year, sale_week",
                    'orderBy' => "sale_year DESC, sale_week DESC",
                    'limit' => 10,
                    'tableId' => 'weeklySalesTable',
                    'columns' => ['Year', 'Week Number', 'Total Orders', 'Total Sales (€)'],
                    'dataMapping' => function ($row) {
                        return [
                            sanitize($row['sale_year']),
                            sanitize($row['sale_week']),
                            sanitize($row['total_orders']),
                            number_format($row['total_sales'], 2)
                        ];
                    }
                ],
                [
                    'title' => '4. Monthly Sales Statistics',
                    'headerClass' => 'bg-danger',
                    'query' => "SELECT YEAR(o.created_at) AS sale_year, 
                                      MONTH(o.created_at) AS sale_month, 
                                      COUNT(o.id) AS total_orders, 
                                      SUM(o.total_amount) AS total_sales 
                               FROM orders o 
                               WHERE o.deleted_at IS NULL",
                    'groupBy' => "sale_year, sale_month",
                    'orderBy' => "sale_year DESC, sale_month DESC",
                    'limit' => 12,
                    'tableId' => 'monthlySalesTable',
                    'columns' => ['Year', 'Month', 'Total Orders', 'Total Sales (€)'],
                    'dataMapping' => function ($row) {
                        return [
                            sanitize($row['sale_year']),
                            date("F", mktime(0, 0, 0, $row['sale_month'], 10)),
                            sanitize($row['total_orders']),
                            number_format($row['total_sales'], 2)
                        ];
                    }
                ],
                [
                    'title' => '5. Custom Calendar-Based Sales Statistics',
                    'headerClass' => 'bg-secondary',
                    'query' => "SELECT o.id, 
                                      o.customer_name, 
                                      o.total_amount, 
                                      o.tip_amount, 
                                      o.created_at 
                               FROM orders o 
                               WHERE o.deleted_at IS NULL",
                    'groupBy' => null,
                    'orderBy' => "o.created_at DESC",
                    'limit' => null,
                    'tableId' => 'customSalesTable',
                    'columns' => ['Customer Name', 'Total Amount (€)', 'Tip (€)', 'Order Date & Time'],
                    'dataMapping' => function ($row) {
                        return [
                            // sanitize($row['id']),
                            sanitize($row['customer_name']),
                            number_format($row['total_amount'], 2),
                            number_format($row['tip_amount'], 2),
                            sanitize($row['created_at'])
                        ];
                    }
                ],
                [
                    'title' => '6. Sales Statistics per Waiter',
                    'headerClass' => 'bg-primary',
                    'query' => "SELECT u.username AS waiter_name, 
                                      COUNT(o.id) AS total_orders, 
                                      SUM(o.total_amount) AS total_sales, 
                                      SUM(o.tip_amount) AS total_tips 
                               FROM orders o 
                               JOIN users u ON o.user_id = u.id 
                               WHERE o.deleted_at IS NULL AND u.role = 'waiter'",
                    'groupBy' => "u.username",
                    'orderBy' => "total_sales DESC",
                    'limit' => 10,
                    'tableId' => 'waiterSalesTable',
                    'columns' => ['Waiter Name', 'Total Orders', 'Total Sales (€)', 'Total Tips (€)'],
                    'dataMapping' => function ($row) {
                        return [
                            sanitize($row['waiter_name']),
                            sanitize($row['total_orders']),
                            number_format($row['total_sales'], 2),
                            number_format($row['total_tips'], 2)
                        ];
                    }
                ],
                [
                    'title' => '7. Sales Statistics per Delivery Person',
                    'headerClass' => 'bg-dark',
                    'query' => "SELECT u.username AS delivery_person, 
                                      COUNT(o.id) AS total_orders, 
                                      SUM(o.total_amount) AS total_sales, 
                                      SUM(o.tip_amount) AS total_tips 
                               FROM orders o 
                               JOIN users u ON o.delivery_user_id = u.id 
                               WHERE o.deleted_at IS NULL AND u.role = 'delivery'",
                    'groupBy' => "u.username",
                    'orderBy' => "total_sales DESC",
                    'limit' => 10,
                    'tableId' => 'deliverySalesTable',
                    'columns' => ['Delivery Person', 'Total Orders', 'Total Sales (€)', 'Total Tips (€)'],
                    'dataMapping' => function ($row) {
                        return [
                            sanitize($row['delivery_person']),
                            sanitize($row['total_orders']),
                            number_format($row['total_sales'], 2),
                            number_format($row['total_tips'], 2)
                        ];
                    }
                ],
                [
                    'title' => '8. Sales Statistics by Payment Method',
                    'headerClass' => 'bg-info',
                    'query' => "SELECT o.payment_method, 
                                      COUNT(o.id) AS total_orders, 
                                      SUM(o.total_amount) AS total_sales 
                               FROM orders o 
                               WHERE o.deleted_at IS NULL",
                    'groupBy' => "o.payment_method",
                    'orderBy' => "total_sales DESC",
                    'limit' => null,
                    'tableId' => 'paymentMethodSalesTable',
                    'columns' => ['Payment Method', 'Total Orders', 'Total Sales (€)'],
                    'dataMapping' => function ($row) {
                        return [
                            sanitize($row['payment_method']),
                            sanitize($row['total_orders']),
                            number_format($row['total_sales'], 2)
                        ];
                    }
                ],
                [
                    'title' => '9. VAT Statistics (Tvsh 1)',
                    'headerClass' => 'bg-secondary',
                    'query' => "SELECT 
                                (SUM(o.total_amount) * 0.01) AS vat1 
                               FROM orders o 
                               WHERE o.deleted_at IS NULL",
                    'groupBy' => null,
                    'orderBy' => null,
                    'limit' => null,
                    'tableId' => 'vat1Table',
                    'columns' => ['VAT (Tvsh 1) (€)'],
                    'dataMapping' => function ($row) {
                        return [
                            number_format($row['vat1'], 2)
                        ];
                    }
                ],
                [
                    'title' => '10. VAT Statistics (Tvsh 2)',
                    'headerClass' => 'bg-secondary',
                    'query' => "SELECT 
                                (SUM(o.total_amount) * 0.02) AS vat2 
                               FROM orders o 
                               WHERE o.deleted_at IS NULL",
                    'groupBy' => null,
                    'orderBy' => null,
                    'limit' => null,
                    'tableId' => 'vat2Table',
                    'columns' => ['VAT (Tvsh 2) (€)'],
                    'dataMapping' => function ($row) {
                        return [
                            number_format($row['vat2'], 2)
                        ];
                    }
                ],
                [
                    'title' => '11. Total Sales by Store and Payment Type',
                    'headerClass' => 'bg-dark',
                    'query' => "SELECT s.name AS store_name, 
                                      o.payment_method, 
                                      COUNT(o.id) AS total_orders, 
                                      SUM(o.total_amount) AS total_sales 
                               FROM orders o 
                               JOIN stores s ON o.store_id = s.id 
                               WHERE o.deleted_at IS NULL",
                    'groupBy' => "s.name, o.payment_method",
                    'orderBy' => "s.name ASC, total_sales DESC",
                    'limit' => null,
                    'tableId' => 'storePaymentSalesTable',
                    'columns' => ['Store Name', 'Payment Method', 'Total Orders', 'Total Sales (€)'],
                    'dataMapping' => function ($row) {
                        return [
                            sanitize($row['store_name']),
                            sanitize($row['payment_method']),
                            sanitize($row['total_orders']),
                            number_format($row['total_sales'], 2)
                        ];
                    }
                ],
            ];

            foreach ($sections as $section):
                if ($section['limit']) {
                    $data = fetchData($pdo, $section['query'], $filters, $section['limit']);
                } else {
                    $data = fetchData($pdo, $section['query'], $filters);
                }
            ?>
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header <?= $section['headerClass'] ?> text-white d-flex justify-content-between align-items-center">
                            <strong><?= $section['title'] ?></strong>
                        </div>
                        <div class="card-body">
                            <table id="<?= $section['tableId'] ?>" class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <?php foreach ($section['columns'] as $col): ?>
                                            <th><?= $col ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data as $row): ?>
                                        <tr>
                                            <?php foreach ($section['dataMapping']($row) as $cell): ?>
                                                <td><?= $cell ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <span class="text-muted small">Use the export buttons above the table to download data.</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>
        $(document).ready(function() {
            // Initialize Flatpickr for date inputs
            $("#start_date, #end_date").flatpickr({
                dateFormat: "Y-m-d",
                allowInput: true
            });

            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });

            // Initialize DataTables with export buttons
            function initDataTable(id) {
                $('#' + id).DataTable({
                    dom: 'Bfrtip',
                    buttons: [{
                            extend: 'csvHtml5',
                            text: '<i class="bi bi-file-earmark-excel"></i> CSV',
                            className: 'btn btn-success btn-sm'
                        },
                        {
                            extend: 'pdfHtml5',
                            text: '<i class="bi bi-file-earmark-pdf"></i> PDF',
                            className: 'btn btn-danger btn-sm',
                            orientation: 'landscape',
                            pageSize: 'A4'
                        },
                        {
                            extend: 'print',
                            text: '<i class="bi bi-printer"></i> Print',
                            className: 'btn btn-secondary btn-sm'
                        }
                    ],
                    order: [
                        [2, "desc"]
                    ],
                    pageLength: 5,
                    lengthChange: false,
                    searching: true,
                    responsive: true,
                    language: {
                        emptyTable: "No data available",
                        search: "Search orders:"
                    }
                });
            }

            const tableIds = [
                'productSalesTable',
                'dailySalesTable',
                'weeklySalesTable',
                'monthlySalesTable',
                'customSalesTable',
                'waiterSalesTable',
                'deliverySalesTable',
                'paymentMethodSalesTable',
                'vat1Table',
                'vat2Table',
                'storePaymentSalesTable'
            ];

            tableIds.forEach(initDataTable);
        });
    </script>
</body>

</html>