<?php
ob_start();
require_once 'includes/db_connect.php';
require_once 'includes/header.php';
require 'vendor/autoload.php';
define('ROLE_ADMIN', 'admin');
define('ROLE_WAITER', 'waiter');
define('ROLE_DELIVERY', 'delivery');
// Check if the user is logged in and has the admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_ADMIN) {
    header('HTTP/1.1 403 Forbidden');
    echo "<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>";
    require_once 'includes/footer.php';
    exit();
}
// Function to sanitize output
function sanitize($data)
{
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}
// Handle custom date filtering
$filter_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$filter_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$filter_day = isset($_GET['filter_day']) ? $_GET['filter_day'] : '';
// Function to calculate summary statistics
function getSummaryStatistics($pdo, $start_date, $end_date, $filter_day)
{
    $sql = "
        SELECT 
            COUNT(o.id) AS total_orders,
            SUM(o.total_amount) AS total_sales,
            AVG(o.total_amount) AS average_order_value
        FROM orders o
        WHERE o.deleted_at IS NULL
    ";
    $params = [];
    if ($start_date && $end_date) {
        $sql .= " AND DATE(o.created_at) BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    } elseif ($filter_day) {
        $sql .= " AND DAYNAME(o.created_at) = ?";
        $params[] = $filter_day;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
$summary_stats = getSummaryStatistics($pdo, $filter_start_date, $filter_end_date, $filter_day);
?>
<div class="container-fluid mt-4">
    <h1 class="mb-4">Sales Statistics</h1>
    <!-- Summary Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card text-white bg-primary summary-card">
                <div class="card-body">
                    <h5 class="card-title">Total Sales (€)</h5>
                    <p class="card-text fs-4"><?= isset($summary_stats['total_sales']) ? number_format($summary_stats['total_sales'], 2) : '0.00' ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card text-white bg-success summary-card">
                <div class="card-body">
                    <h5 class="card-title">Total Orders</h5>
                    <p class="card-text fs-4"><?= isset($summary_stats['total_orders']) ? sanitize($summary_stats['total_orders']) : '0' ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card text-white bg-warning summary-card">
                <div class="card-body">
                    <h5 class="card-title">Average Order Value (€)</h5>
                    <p class="card-text fs-4"><?= isset($summary_stats['average_order_value']) ? number_format($summary_stats['average_order_value'], 2) : '0.00' ?></p>
                </div>
            </div>
        </div>
    </div>
    <!-- Custom Date Filtering Section -->
    <div class="card mb-4">
        <div class="card-header">
            <strong>Custom Date/Day Filtering</strong>
        </div>
        <div class="card-body">
            <form method="GET" action="statistics.php" class="row g-3">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="text" class="form-control" id="start_date" name="start_date" placeholder="Start Date" value="<?= sanitize($filter_start_date) ?>">
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="text" class="form-control" id="end_date" name="end_date" placeholder="End Date" value="<?= sanitize($filter_end_date) ?>">
                </div>
                <div class="col-md-4">
                    <label for="filter_day" class="form-label">Select Day of Week</label>
                    <select class="form-select" id="filter_day" name="filter_day">
                        <option value="">-- Select Day --</option>
                        <?php
                        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                        foreach ($days as $day) {
                            $selected = ($filter_day === $day) ? 'selected' : '';
                            echo "<option value=\"{$day}\" {$selected}>{$day}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-12 align-self-end">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="statistics.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    <!-- Statistics Dashboard -->
    <div class="row">
        <!-- 1. Sales Statistics per Product -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <strong>1. Sales Statistics per Product</strong>
                </div>
                <div class="card-body">
                    <?php
                    // SQL Query for Sales per Product
                    $sql_product_sales = "
                            SELECT 
                                p.name AS product_name,
                                SUM(oi.quantity) AS total_quantity_sold,
                                SUM(oi.price * oi.quantity) AS total_sales
                            FROM order_items oi
                            JOIN products p ON oi.product_id = p.id
                            JOIN orders o ON oi.order_id = o.id
                            WHERE o.deleted_at IS NULL
                        ";
                    // Apply custom date filters if any
                    $params = [];
                    if ($filter_start_date && $filter_end_date) {
                        $sql_product_sales .= " AND DATE(o.created_at) BETWEEN ? AND ?";
                        $params[] = $filter_start_date;
                        $params[] = $filter_end_date;
                    } elseif ($filter_day) {
                        $sql_product_sales .= " AND DAYNAME(o.created_at) = ?";
                        $params[] = $filter_day;
                    }
                    $sql_product_sales .= "
                            GROUP BY p.name
                            ORDER BY total_sales DESC
                            LIMIT 10
                        ";
                    $stmt = $pdo->prepare($sql_product_sales);
                    $stmt->execute($params);
                    $product_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div class="chart-container">
                        <div id="productSalesChart"></div>
                    </div>
                    <table id="productSalesTable" class="table table-striped table-bordered mt-3">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Total Quantity Sold</th>
                                <th>Total Sales (€)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($product_sales as $row): ?>
                                <tr>
                                    <td><?= sanitize($row['product_name']) ?></td>
                                    <td><?= sanitize($row['total_quantity_sold']) ?></td>
                                    <td><?= number_format($row['total_sales'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- 2. Daily Sales Statistics -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <strong>2. Daily Sales Statistics</strong>
                </div>
                <div class="card-body">
                    <?php
                    // SQL Query for Daily Sales
                    $sql_daily_sales = "
                            SELECT 
                                DATE(o.created_at) AS sale_date,
                                COUNT(o.id) AS total_orders,
                                SUM(o.total_amount) AS total_sales
                            FROM orders o
                            WHERE o.deleted_at IS NULL
                        ";
                    // Apply custom date filters if any
                    if ($filter_start_date && $filter_end_date) {
                        $sql_daily_sales .= " AND DATE(o.created_at) BETWEEN ? AND ?";
                        $params_daily = [$filter_start_date, $filter_end_date];
                    } elseif ($filter_day) {
                        $sql_daily_sales .= " AND DAYNAME(o.created_at) = ?";
                        $params_daily = [$filter_day];
                    } else {
                        $params_daily = [];
                    }
                    $sql_daily_sales .= "
                            GROUP BY DATE(o.created_at)
                            ORDER BY sale_date DESC
                        ";
                    $stmt_daily = $pdo->prepare($sql_daily_sales);
                    $stmt_daily->execute($params_daily);
                    $daily_sales = $stmt_daily->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div class="chart-container">
                        <div id="dailySalesChart"></div>
                    </div>
                    <table id="dailySalesTable" class="table table-striped table-bordered mt-3">
                        <thead>
                            <tr>
                                <th>Sale Date</th>
                                <th>Total Orders</th>
                                <th>Total Sales (€)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daily_sales as $row): ?>
                                <tr>
                                    <td><?= sanitize($row['sale_date']) ?></td>
                                    <td><?= sanitize($row['total_orders']) ?></td>
                                    <td><?= number_format($row['total_sales'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <!-- 3. Weekly Sales Statistics -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-warning text-white">
                    <strong>3. Weekly Sales Statistics</strong>
                </div>
                <div class="card-body">
                    <?php
                    // SQL Query for Weekly Sales
                    $sql_weekly_sales = "
                            SELECT 
                                YEAR(o.created_at) AS sale_year,
                                WEEK(o.created_at, 1) AS sale_week,
                                COUNT(o.id) AS total_orders,
                                SUM(o.total_amount) AS total_sales
                            FROM orders o
                            WHERE o.deleted_at IS NULL
                        ";
                    // Apply custom date filters if any
                    if ($filter_start_date && $filter_end_date) {
                        $sql_weekly_sales .= " AND DATE(o.created_at) BETWEEN ? AND ?";
                        $params_weekly = [$filter_start_date, $filter_end_date];
                    } elseif ($filter_day) {
                        $sql_weekly_sales .= " AND DAYNAME(o.created_at) = ?";
                        $params_weekly = [$filter_day];
                    } else {
                        $params_weekly = [];
                    }
                    $sql_weekly_sales .= "
                            GROUP BY sale_year, sale_week
                            ORDER BY sale_year DESC, sale_week DESC
                            LIMIT 10
                        ";
                    $stmt_weekly = $pdo->prepare($sql_weekly_sales);
                    $stmt_weekly->execute($params_weekly);
                    $weekly_sales = $stmt_weekly->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div class="chart-container">
                        <div id="weeklySalesChart"></div>
                    </div>
                    <table id="weeklySalesTable" class="table table-striped table-bordered mt-3">
                        <thead>
                            <tr>
                                <th>Year</th>
                                <th>Week Number</th>
                                <th>Total Orders</th>
                                <th>Total Sales (€)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($weekly_sales as $row): ?>
                                <tr>
                                    <td><?= sanitize($row['sale_year']) ?></td>
                                    <td><?= sanitize($row['sale_week']) ?></td>
                                    <td><?= sanitize($row['total_orders']) ?></td>
                                    <td><?= number_format($row['total_sales'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- 4. Monthly Sales Statistics -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-danger text-white">
                    <strong>4. Monthly Sales Statistics</strong>
                </div>
                <div class="card-body">
                    <?php
                    // SQL Query for Monthly Sales
                    $sql_monthly_sales = "
                            SELECT 
                                YEAR(o.created_at) AS sale_year,
                                MONTH(o.created_at) AS sale_month,
                                COUNT(o.id) AS total_orders,
                                SUM(o.total_amount) AS total_sales
                            FROM orders o
                            WHERE o.deleted_at IS NULL
                        ";
                    // Apply custom date filters if any
                    if ($filter_start_date && $filter_end_date) {
                        $sql_monthly_sales .= " AND DATE(o.created_at) BETWEEN ? AND ?";
                        $params_monthly = [$filter_start_date, $filter_end_date];
                    } elseif ($filter_day) {
                        $sql_monthly_sales .= " AND DAYNAME(o.created_at) = ?";
                        $params_monthly = [$filter_day];
                    } else {
                        $params_monthly = [];
                    }
                    $sql_monthly_sales .= "
                            GROUP BY sale_year, sale_month
                            ORDER BY sale_year DESC, sale_month DESC
                            LIMIT 12
                        ";
                    $stmt_monthly = $pdo->prepare($sql_monthly_sales);
                    $stmt_monthly->execute($params_monthly);
                    $monthly_sales = $stmt_monthly->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div class="chart-container">
                        <div id="monthlySalesChart"></div>
                    </div>
                    <table id="monthlySalesTable" class="table table-striped table-bordered mt-3">
                        <thead>
                            <tr>
                                <th>Year</th>
                                <th>Month</th>
                                <th>Total Orders</th>
                                <th>Total Sales (€)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthly_sales as $row): ?>
                                <tr>
                                    <td><?= sanitize($row['sale_year']) ?></td>
                                    <td><?= date("F", mktime(0, 0, 0, $row['sale_month'], 10)) ?></td>
                                    <td><?= sanitize($row['total_orders']) ?></td>
                                    <td><?= number_format($row['total_sales'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <!-- 5. Custom Calendar-Based Sales Statistics -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-secondary text-white">
                    <strong>5. Custom Calendar-Based Sales Statistics</strong>
                </div>
                <div class="card-body">
                    <?php
                    // SQL Query for Custom Calendar-Based Sales
                    $sql_custom_sales = "
                            SELECT 
                                o.id,
                                o.customer_name,
                                o.total_amount,
                                o.created_at
                            FROM orders o
                            WHERE o.deleted_at IS NULL
                        ";
                    $params_custom = [];
                    if ($filter_start_date && $filter_end_date) {
                        $sql_custom_sales .= " AND DATE(o.created_at) BETWEEN ? AND ?";
                        $params_custom = [$filter_start_date, $filter_end_date];
                    }
                    if ($filter_day) {
                        $sql_custom_sales .= " AND DAYNAME(o.created_at) = ?";
                        $params_custom[] = $filter_day;
                    }
                    $sql_custom_sales .= "
                            ORDER BY o.created_at DESC
                        ";
                    $stmt_custom = $pdo->prepare($sql_custom_sales);
                    $stmt_custom->execute($params_custom);
                    $custom_sales = $stmt_custom->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div class="chart-container">
                        <div id="customSalesChart"></div>
                    </div>
                    <table id="customSalesTable" class="table table-striped table-bordered mt-3">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer Name</th>
                                <th>Total Amount (€)</th>
                                <th>Order Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($custom_sales as $row): ?>
                                <tr>
                                    <td><?= sanitize($row['id']) ?></td>
                                    <td><?= sanitize($row['customer_name']) ?></td>
                                    <td><?= number_format($row['total_amount'], 2) ?></td>
                                    <td><?= sanitize($row['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- 6. Sales Statistics per Waiter -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <strong>6. Sales Statistics per Waiter</strong>
                </div>
                <div class="card-body">
                    <?php
                    // SQL Query for Sales per Waiter
                    $sql_waiter_sales = "
                            SELECT 
                                u.username AS waiter_name,
                                COUNT(o.id) AS total_orders,
                                SUM(o.total_amount) AS total_sales
                            FROM orders o
                            JOIN users u ON o.user_id = u.id
                            WHERE o.deleted_at IS NULL AND u.role = 'waiter'
                        ";
                    // Apply custom date filters if any
                    if ($filter_start_date && $filter_end_date) {
                        $sql_waiter_sales .= " AND DATE(o.created_at) BETWEEN ? AND ?";
                        $params_waiter = [$filter_start_date, $filter_end_date];
                    } elseif ($filter_day) {
                        $sql_waiter_sales .= " AND DAYNAME(o.created_at) = ?";
                        $params_waiter = [$filter_day];
                    } else {
                        $params_waiter = [];
                    }
                    $sql_waiter_sales .= "
                            GROUP BY u.username
                            ORDER BY total_sales DESC
                            LIMIT 10
                        ";
                    $stmt_waiter = $pdo->prepare($sql_waiter_sales);
                    $stmt_waiter->execute($params_waiter);
                    $waiter_sales = $stmt_waiter->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div class="chart-container">
                        <div id="waiterSalesChart"></div>
                    </div>
                    <table id="waiterSalesTable" class="table table-striped table-bordered mt-3">
                        <thead>
                            <tr>
                                <th>Waiter Name</th>
                                <th>Total Orders</th>
                                <th>Total Sales (€)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($waiter_sales as $row): ?>
                                <tr>
                                    <td><?= sanitize($row['waiter_name']) ?></td>
                                    <td><?= sanitize($row['total_orders']) ?></td>
                                    <td><?= number_format($row['total_sales'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <!-- 7. Sales Statistics per Delivery Person -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-dark text-white">
                    <strong>7. Sales Statistics per Delivery Person</strong>
                </div>
                <div class="card-body">
                    <?php
                    // SQL Query for Sales per Delivery Person
                    $sql_delivery_sales = "
                            SELECT 
                                u.username AS delivery_person,
                                COUNT(o.id) AS total_orders,
                                SUM(o.total_amount) AS total_sales
                            FROM orders o
                            JOIN users u ON o.delivery_user_id = u.id
                            WHERE o.deleted_at IS NULL AND u.role = 'delivery'
                        ";
                    // Apply custom date filters if any
                    if ($filter_start_date && $filter_end_date) {
                        $sql_delivery_sales .= " AND DATE(o.created_at) BETWEEN ? AND ?";
                        $params_delivery = [$filter_start_date, $filter_end_date];
                    } elseif ($filter_day) {
                        $sql_delivery_sales .= " AND DAYNAME(o.created_at) = ?";
                        $params_delivery = [$filter_day];
                    } else {
                        $params_delivery = [];
                    }
                    $sql_delivery_sales .= "
                            GROUP BY u.username
                            ORDER BY total_sales DESC
                            LIMIT 10
                        ";
                    $stmt_delivery = $pdo->prepare($sql_delivery_sales);
                    $stmt_delivery->execute($params_delivery);
                    $delivery_sales = $stmt_delivery->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div class="chart-container">
                        <div id="deliverySalesChart"></div>
                    </div>
                    <table id="deliverySalesTable" class="table table-striped table-bordered mt-3">
                        <thead>
                            <tr>
                                <th>Delivery Person</th>
                                <th>Total Orders</th>
                                <th>Total Sales (€)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($delivery_sales as $row): ?>
                                <tr>
                                    <td><?= sanitize($row['delivery_person']) ?></td>
                                    <td><?= sanitize($row['total_orders']) ?></td>
                                    <td><?= number_format($row['total_sales'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- 8. Top 10 Products Chart (Additional Feature) -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-secondary text-white">
                    <strong>8. Top 10 Products</strong>
                </div>
                <div class="card-body">
                    <?php
                    // SQL Query for Top 10 Products
                    $sql_top_products = "
                            SELECT 
                                p.name AS product_name,
                                SUM(oi.quantity) AS total_quantity_sold,
                                SUM(oi.price * oi.quantity) AS total_sales
                            FROM order_items oi
                            JOIN products p ON oi.product_id = p.id
                            JOIN orders o ON oi.order_id = o.id
                            WHERE o.deleted_at IS NULL
                        ";
                    // Apply custom date filters if any
                    $params_top = [];
                    if ($filter_start_date && $filter_end_date) {
                        $sql_top_products .= " AND DATE(o.created_at) BETWEEN ? AND ?";
                        $params_top[] = $filter_start_date;
                        $params_top[] = $filter_end_date;
                    } elseif ($filter_day) {
                        $sql_top_products .= " AND DAYNAME(o.created_at) = ?";
                        $params_top[] = $filter_day;
                    }
                    $sql_top_products .= "
                            GROUP BY p.name
                            ORDER BY total_sales DESC
                            LIMIT 10
                        ";
                    $stmt_top = $pdo->prepare($sql_top_products);
                    $stmt_top->execute($params_top);
                    $top_products = $stmt_top->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div class="chart-container">
                        <div id="topProductsChart"></div>
                    </div>
                    <table id="topProductsTable" class="table table-striped table-bordered mt-3">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Total Quantity Sold</th>
                                <th>Total Sales (€)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_products as $row): ?>
                                <tr>
                                    <td><?= sanitize($row['product_name']) ?></td>
                                    <td><?= sanitize($row['total_quantity_sold']) ?></td>
                                    <td><?= number_format($row['total_sales'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    <!-- jQuery, Bootstrap JS, Flatpickr JS, DataTables JS, DataTables Buttons JS, ApexCharts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <!-- DataTables Buttons JS for Export -->
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        $(document).ready(function() {
            // Initialize Flatpickr
            $("#start_date, #end_date").flatpickr({
                dateFormat: "Y-m-d"
            });
            // Initialize DataTables with Export Buttons
            function initializeDataTable(tableId) {
                $('#' + tableId).DataTable({
                    dom: 'Bfrtip',
                    buttons: [{
                            extend: 'csvHtml5',
                            text: 'CSV',
                            className: 'btn btn-success btn-sm'
                        },
                        {
                            extend: 'pdfHtml5',
                            text: 'PDF',
                            className: 'btn btn-danger btn-sm',
                            orientation: 'landscape',
                            pageSize: 'A4'
                        },
                        {
                            extend: 'print',
                            text: 'Print',
                            className: 'btn btn-secondary btn-sm'
                        }
                    ],
                    "order": [
                        [2, "desc"]
                    ],
                    "pageLength": 5,
                    "lengthChange": false,
                    "searching": false,
                    "paging": true,
                    "responsive": true
                });
            }
            // Initialize all DataTables with export buttons
            initializeDataTable('productSalesTable');
            initializeDataTable('dailySalesTable');
            initializeDataTable('weeklySalesTable');
            initializeDataTable('monthlySalesTable');
            initializeDataTable('customSalesTable');
            initializeDataTable('waiterSalesTable');
            initializeDataTable('deliverySalesTable');
            initializeDataTable('topProductsTable');
            // Initialize ApexCharts
            // 1. Product Sales Chart
            var optionsProduct = {
                chart: {
                    type: 'bar',
                    height: 300
                },
                series: [{
                    name: 'Total Sales (€)',
                    data: <?= json_encode(array_column($product_sales, 'total_sales')) ?>
                }],
                xaxis: {
                    categories: <?= json_encode(array_column($product_sales, 'product_name')) ?>,
                    labels: {
                        rotate: -45
                    }
                },
                yaxis: {
                    title: {
                        text: 'Sales (€)'
                    }
                },
                title: {
                    text: 'Sales per Product',
                    align: 'center'
                },
                tooltip: {
                    y: {
                        formatter: function(val) {
                            return '€ ' + val.toFixed(2);
                        }
                    }
                }
            };
            var chartProduct = new ApexCharts(document.querySelector("#productSalesChart"), optionsProduct);
            chartProduct.render();
            // 2. Daily Sales Chart
            var optionsDaily = {
                chart: {
                    type: 'line',
                    height: 300
                },
                series: [{
                    name: 'Daily Sales (€)',
                    data: <?= json_encode(array_column($daily_sales, 'total_sales')) ?>
                }],
                xaxis: {
                    categories: <?= json_encode(array_column($daily_sales, 'sale_date')) ?>,
                    title: {
                        text: 'Date'
                    }
                },
                yaxis: {
                    title: {
                        text: 'Sales (€)'
                    }
                },
                title: {
                    text: 'Daily Sales Trends',
                    align: 'center'
                },
                tooltip: {
                    y: {
                        formatter: function(val) {
                            return '€ ' + val.toFixed(2);
                        }
                    }
                }
            };
            var chartDaily = new ApexCharts(document.querySelector("#dailySalesChart"), optionsDaily);
            chartDaily.render();
            // 3. Weekly Sales Chart
            var optionsWeekly = {
                chart: {
                    type: 'bar',
                    height: 300
                },
                series: [{
                    name: 'Weekly Sales (€)',
                    data: <?= json_encode(array_column($weekly_sales, 'total_sales')) ?>
                }],
                xaxis: {
                    categories: <?= json_encode(array_map(function ($row) {
                                    return "Year {$row['sale_year']} - Week {$row['sale_week']}";
                                }, $weekly_sales)) ?>,
                    labels: {
                        rotate: -45
                    }
                },
                yaxis: {
                    title: {
                        text: 'Sales (€)'
                    }
                },
                title: {
                    text: 'Weekly Sales',
                    align: 'center'
                },
                tooltip: {
                    y: {
                        formatter: function(val) {
                            return '€ ' + val.toFixed(2);
                        }
                    }
                }
            };
            var chartWeekly = new ApexCharts(document.querySelector("#weeklySalesChart"), optionsWeekly);
            chartWeekly.render();
            // 4. Monthly Sales Chart
            var optionsMonthly = {
                chart: {
                    type: 'line',
                    height: 300
                },
                series: [{
                    name: 'Monthly Sales (€)',
                    data: <?= json_encode(array_column($monthly_sales, 'total_sales')) ?>
                }],
                xaxis: {
                    categories: <?= json_encode(array_map(function ($row) {
                                    return "{$row['sale_year']} - " . date("F", mktime(0, 0, 0, $row['sale_month'], 10));
                                }, $monthly_sales)) ?>,
                    title: {
                        text: 'Month'
                    }
                },
                yaxis: {
                    title: {
                        text: 'Sales (€)'
                    }
                },
                title: {
                    text: 'Monthly Sales Trends',
                    align: 'center'
                },
                tooltip: {
                    y: {
                        formatter: function(val) {
                            return '€ ' + val.toFixed(2);
                        }
                    }
                }
            };
            var chartMonthly = new ApexCharts(document.querySelector("#monthlySalesChart"), optionsMonthly);
            chartMonthly.render();
            // 5. Custom Sales Chart
            var customDates = <?= json_encode(array_column($custom_sales, 'created_at')) ?>;
            var customTotals = <?= json_encode(array_column($custom_sales, 'total_amount')) ?>;
            var optionsCustom = {
                chart: {
                    type: 'scatter',
                    height: 300
                },
                series: [{
                    name: 'Custom Sales',
                    data: customDates.map(function(date, index) {
                        return [new Date(date).getTime(), customTotals[index]];
                    })
                }],
                xaxis: {
                    type: 'datetime',
                    title: {
                        text: 'Date & Time'
                    }
                },
                yaxis: {
                    title: {
                        text: 'Sales (€)'
                    }
                },
                title: {
                    text: 'Custom Calendar-Based Sales',
                    align: 'center'
                },
                tooltip: {
                    x: {
                        format: 'dd MMM yyyy HH:mm'
                    },
                    y: {
                        formatter: function(val) {
                            return '€ ' + val.toFixed(2);
                        }
                    }
                }
            };
            var chartCustom = new ApexCharts(document.querySelector("#customSalesChart"), optionsCustom);
            chartCustom.render();
            // 6. Waiter Sales Chart
            var optionsWaiter = {
                chart: {
                    type: 'pie',
                    height: 300
                },
                series: <?= json_encode(array_column($waiter_sales, 'total_sales')) ?>,
                labels: <?= json_encode(array_column($waiter_sales, 'waiter_name')) ?>,
                colors: ['#17a2b8', '#28a745', '#ffc107', '#dc3545', '#6c757d', '#9900cc', '#ff9933', '#4bc0c0', '#ffce56', '#36a2eb'],
                title: {
                    text: 'Sales Distribution Among Waiters',
                    align: 'center'
                },
                tooltip: {
                    y: {
                        formatter: function(val) {
                            return '€ ' + val.toFixed(2);
                        }
                    }
                }
            };
            var chartWaiter = new ApexCharts(document.querySelector("#waiterSalesChart"), optionsWaiter);
            chartWaiter.render();
            // 7. Delivery Person Sales Chart
            var optionsDelivery = {
                chart: {
                    type: 'donut',
                    height: 300
                },
                series: <?= json_encode(array_column($delivery_sales, 'total_sales')) ?>,
                labels: <?= json_encode(array_column($delivery_sales, 'delivery_person')) ?>,
                colors: ['#28a745', '#17a2b8', '#ffc107', '#dc3545', '#6c757d', '#9900cc', '#ff9933', '#4bc0c0', '#ffce56', '#36a2eb'],
                title: {
                    text: 'Sales Distribution Among Delivery Persons',
                    align: 'center'
                },
                tooltip: {
                    y: {
                        formatter: function(val) {
                            return '€ ' + val.toFixed(2);
                        }
                    }
                }
            };
            var chartDelivery = new ApexCharts(document.querySelector("#deliverySalesChart"), optionsDelivery);
            chartDelivery.render();
            // 8. Top 10 Products Chart (Additional Feature)
            var optionsTopProducts = {
                chart: {
                    type: 'bar',
                    height: 300
                },
                series: [{
                    name: 'Total Sales (€)',
                    data: <?= json_encode(array_column($top_products, 'total_sales')) ?>
                }],
                plotOptions: {
                    bar: {
                        horizontal: true,
                    }
                },
                xaxis: {
                    categories: <?= json_encode(array_column($top_products, 'product_name')) ?>,
                    title: {
                        text: 'Sales (€)'
                    }
                },
                yaxis: {
                    title: {
                        text: 'Product Name'
                    }
                },
                title: {
                    text: 'Top 10 Products',
                    align: 'center'
                },
                tooltip: {
                    y: {
                        formatter: function(val) {
                            return '€ ' + val.toFixed(2);
                        }
                    }
                }
            };
            var chartTopProducts = new ApexCharts(document.querySelector("#topProductsChart"), optionsTopProducts);
            chartTopProducts.render();
        });
    </script>