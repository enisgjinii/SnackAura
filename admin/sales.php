<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Autoload dependencies
require 'vendor/autoload.php';

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Dompdf\Dompdf;

// Configuration
$stripeSecretKey = 'sk_test_51QByfJE4KNNCb6nuElXbMZUUan5s9fkJ1N2Ce3fMunhTipH5LGonlnO3bcq6eaxXINmWDuMzfw7RFTNTOb1jDsEm00IzfwoFx2';
if (!$stripeSecretKey) die("Stripe Secret Key not set.");
Stripe::setApiKey($stripeSecretKey);

// Constants
define('LOG_FILE', __DIR__ . '/error_log.txt');
define('PER_PAGE', 20);

// Utility Functions
function log_error($message, $context = '')
{
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] $message" . ($context ? " | Context: $context" : "") . PHP_EOL;
    file_put_contents(LOG_FILE, $entry, FILE_APPEND | LOCK_EX);
}

function sanitize($data)
{
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function get_status_name($status_id)
{
    $status_names = [
        1 => 'Pending',
        2 => 'Processing',
        3 => 'Ready for Pickup',
        4 => 'Pending Cash Payment',
        5 => 'Paid',
        6 => 'Payment Failed'
    ];
    return $status_names[$status_id] ?? 'Unknown';
}

function generate_invoice_pdf($payment, $order)
{
    $dompdf = new Dompdf();
    ob_start();
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Invoice - <?= sanitize($order['id']) ?></title>
        <style>
            body {
                font-family: DejaVu Sans, sans-serif;
                margin: 20px;
            }

            .header,
            .footer {
                text-align: center;
            }

            .details {
                margin-top: 20px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            th,
            td {
                border: 1px solid #000;
                padding: 8px;
                text-align: left;
            }

            .total {
                margin-top: 20px;
                text-align: right;
            }
        </style>
    </head>

    <body>
        <div class="header">
            <h1>Your Company Name</h1>
            <p>Address Line 1, Address Line 2, City, Country</p>
            <p>Email: contact@yourcompany.com | Phone: +1234567890</p>
        </div>
        <div class="details">
            <h2>Invoice</h2>
            <table>
                <?php foreach (
                    [
                        'Invoice Number' => $order['id'],
                        'Payment ID' => $payment->id,
                        'Customer Name' => $order['customer_name'],
                        'Customer Email' => $order['customer_email'],
                        'Customer Phone' => $order['customer_phone'],
                        'Delivery Address' => nl2br(sanitize($order['delivery_address'])),
                        'Scheduled Date & Time' => sanitize($order['scheduled_date']) . ' ' . sanitize($order['scheduled_time']),
                        'Payment Method' => sanitize(ucfirst($order['payment_method'])),
                        'Amount' => '€' . number_format($payment->amount / 100, 2),
                        'Status' => sanitize(ucfirst($payment->status)),
                        'Created At' => sanitize((new DateTime('@' . $payment->created))->format('Y-m-d H:i:s'))
                    ] as $key => $value
                ): ?>
                    <tr>
                        <th><?= sanitize($key) ?></th>
                        <td><?= $value ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <div class="total">
            <h3>Total Amount: €<?= number_format($payment->amount / 100, 2) ?></h3>
        </div>
        <div class="footer">
            <p>Thank you for your business!</p>
        </div>
    </body>

    </html>
<?php
    $dompdf->loadHtml(ob_get_clean());
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream("invoice_{$order['id']}.pdf", ["Attachment" => true]);
    exit;
}

function fetch_payments($limit = 100, $starting_after = null)
{
    $params = ['limit' => $limit];
    if ($starting_after) $params['starting_after'] = $starting_after;
    return PaymentIntent::all($params);
}

function fetch_order($pdo, $order_id)
{
    if (!$order_id) return null;
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        log_error("Database error while fetching order ID $order_id: " . $e->getMessage(), "Order Fetch");
        return null;
    }
}

// Handle Invoice Generation
if (isset($_GET['generate_invoice'])) {
    include __DIR__ . '/includes/db_connect.php';
    $payment_id = $_GET['payment_id'] ?? '';
    if (!preg_match('/^pi_\w+$/', $payment_id)) die("Invalid Payment ID.");
    try {
        $payment = PaymentIntent::retrieve($payment_id);
    } catch (Exception $e) {
        log_error("Error retrieving payment $payment_id: " . $e->getMessage(), "Invoice Generation");
        die("Error retrieving payment.");
    }
    $order_id = $payment->metadata->order_id ?? null;
    if (!$order_id) die("Order ID not found in payment metadata.");
    $order = fetch_order($pdo, $order_id);
    if (!$order) die("Order not found for this payment.");
    generate_invoice_pdf($payment, $order);
}

// Handle CSV Export
if (isset($_GET['export_csv'])) {
    include __DIR__ . '/includes/db_connect.php';
    // Get selected payment IDs from POST
    $selected_payments = $_POST['selected_payments'] ?? [];
    if (empty($selected_payments)) {
        die("No payments selected for export.");
    }
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="selected_payments.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Payment ID', 'Amount (€)', 'Currency', 'Status', 'Payment Method', 'Customer Email', 'Customer Name', 'Created At', 'Order ID', 'Order Total', 'Order Status']);

    foreach ($selected_payments as $payment_id) {
        if (!preg_match('/^pi_\w+$/', $payment_id)) continue; // Skip invalid IDs
        try {
            $payment = PaymentIntent::retrieve($payment_id, ['expand' => ['latest_charge']]);
            $order = fetch_order($pdo, $payment->metadata->order_id ?? null);
            fputcsv($output, [
                $payment->id,
                number_format($payment->amount / 100, 2),
                strtoupper(sanitize($payment->currency)),
                ucfirst($payment->status),
                sanitize(ucfirst($order['payment_method'] ?? 'N/A')),
                sanitize($order['customer_email'] ?? 'N/A'),
                sanitize($order['customer_name'] ?? 'N/A'),
                sanitize((new DateTime('@' . $payment->created))->format('Y-m-d H:i:s')),
                $order['id'] ?? 'N/A',
                number_format($order['total_amount'] ?? 0, 2),
                get_status_name($order['status_id'] ?? 0)
            ]);
        } catch (Exception $e) {
            log_error("Error retrieving payment $payment_id for CSV export: " . $e->getMessage(), "CSV Export");
            continue;
        }
    }
    fclose($output);
    exit;
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/db_connect.php';

// Fetch all payments
$payments = [];
$has_more = true;
$starting_after = null;
try {
    while ($has_more) {
        $response = fetch_payments(100, $starting_after);
        foreach ($response->data as $payment_summary) {
            $payment = PaymentIntent::retrieve($payment_summary->id, ['expand' => ['latest_charge']]);
            $payments[] = $payment;
        }
        $has_more = $response->has_more;
        if ($has_more) $starting_after = end($response->data)->id;
    }
} catch (Exception $e) {
    log_error("Error fetching payments from Stripe: " . $e->getMessage(), "Stripe API");
    die("Error fetching payments.");
}

// Calculate Dashboard Metrics
$total_sales = 0;
$total_transactions = count($payments);
$successful_payments = 0;
$failed_payments = 0;
$payment_dates = []; // For chart data

foreach ($payments as $payment) {
    if ($payment->status === 'succeeded') {
        $total_sales += $payment->amount;
        $successful_payments++;
    } elseif ($payment->status === 'requires_payment_method' || $payment->status === 'requires_action') {
        $failed_payments++;
    }

    // Prepare data for sales over time
    $date = (new DateTime('@' . $payment->created))->format('Y-m');
    if (!isset($payment_dates[$date])) {
        $payment_dates[$date] = 0;
    }
    $payment_dates[$date] += $payment->amount / 100;
}

$average_transaction = $total_transactions > 0 ? ($total_sales / $total_transactions) / 100 : 0;

// Sort payment_dates by date
ksort($payment_dates);
$chart_labels = array_keys($payment_dates);
$chart_data = array_values($payment_dates);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Sales Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Include DataTables CSS for enhanced table features -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <!-- Include Chart.js CSS (optional, as it's a JS library) -->
    <style>
        /* Custom styles for dashboard metrics */
        .metric {
            padding: 20px;
            color: #fff;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .metric h3 {
            margin: 0;
            font-size: 1.5rem;
        }

        .metric p {
            margin: 0;
            font-size: 1rem;
        }

        .metric.total-sales {
            background-color: #28a745;
        }

        .metric.total-transactions {
            background-color: #17a2b8;
        }

        .metric.average-transaction {
            background-color: #ffc107;
        }

        .metric.successful-payments {
            background-color: #007bff;
        }

        .metric.failed-payments {
            background-color: #dc3545;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <h1 class="mb-4">Sales Dashboard</h1>

        <!-- Dashboard Metrics -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="metric total-sales">
                    <h3>€<?= number_format($total_sales / 100, 2) ?></h3>
                    <p>Total Sales</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric total-transactions">
                    <h3><?= $total_transactions ?></h3>
                    <p>Transactions</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric average-transaction">
                    <h3>€<?= number_format($average_transaction, 2) ?></h3>
                    <p>Avg. Transaction</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric successful-payments">
                    <h3><?= $successful_payments ?></h3>
                    <p>Successful Payments</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric failed-payments">
                    <h3><?= $failed_payments ?></h3>
                    <p>Failed Payments</p>
                </div>
            </div>
        </div>

        <!-- Graphical Reports -->
        <div class="row mb-5">
            <div class="col-md-12">
                <canvas id="salesChart"></canvas>
            </div>
        </div>

        <!-- Bulk Actions and Export -->
        <div class="mb-3">
            <form method="POST" action="sales.php?export_csv=1" id="bulk_export_form">
                <button type="submit" class="btn btn-success" id="export_selected">Export Selected to CSV</button>
            </form>
            <!-- Real-time Notifications Placeholder -->
            <!-- Implement real-time notifications using WebSockets or services like Pusher here -->
        </div>

        <!-- Payments Table -->
        <form method="POST" action="sales.php?export_csv=1" id="payments_form">
            <table class="table table-striped table-bordered" id="sales_table">
                <thead class="table-dark">
                    <tr>
                        <th><input type="checkbox" id="select_all"></th>
                        <th>Payment ID</th>
                        <th>Amount (€)</th>
                        <th>Currency</th>
                        <th>Status</th>
                        <th>Payment Method</th>
                        <th>Customer Email</th>
                        <th>Customer Name</th>
                        <th>Created At</th>
                        <th>Order Details</th>
                        <th>Invoice</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="12" class="text-center">No payments found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $payment):
                            $order_id = $payment->metadata->order_id ?? null;
                            $order = $order_id ? fetch_order($pdo, $order_id) : null;
                            if (!$order && $order_id) {
                                log_error("Order ID $order_id not found for Payment ID {$payment->id}.", "Sales Dashboard");
                            }
                        ?>
                            <tr>
                                <td><input type="checkbox" name="selected_payments[]" value="<?= sanitize($payment->id) ?>"></td>
                                <td><?= sanitize($payment->id) ?></td>
                                <td><?= number_format($payment->amount / 100, 2) ?></td>
                                <td><?= strtoupper(sanitize($payment->currency)) ?></td>
                                <td>
                                    <?php
                                    $status_badges = [
                                        'succeeded' => 'success',
                                        'requires_payment_method' => 'danger',
                                        'requires_action' => 'warning'
                                    ];
                                    $badge = $status_badges[$payment->status] ?? 'secondary';
                                    echo '<span class="badge bg-' . $badge . '">' . sanitize(ucfirst($payment->status)) . '</span>';
                                    ?>
                                </td>
                                <td><?= sanitize(ucfirst($order['payment_method'] ?? 'N/A')) ?></td>
                                <td><?= sanitize($order['customer_email'] ?? 'N/A') ?></td>
                                <td><?= sanitize($order['customer_name'] ?? 'N/A') ?></td>
                                <td><?= sanitize((new DateTime('@' . $payment->created))->format('Y-m-d H:i:s')) ?></td>
                                <td>
                                    <?php if ($order): ?>
                                        Order ID: <?= sanitize($order['id']) ?><br>
                                        Total: €<?= number_format($order['total_amount'], 2) ?><br>
                                        Status: <?= sanitize(get_status_name($order['status_id'])) ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order): ?>
                                        <a href="sales.php?generate_invoice=1&payment_id=<?= urlencode($payment->id) ?>" class="btn btn-sm btn-primary">Download Invoice</a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order): ?>
                                        <button type="button" class="btn btn-sm btn-info view-details" data-payment='<?= json_encode([
                                                                                                                            'payment_id' => sanitize($payment->id),
                                                                                                                            'amount' => number_format($payment->amount / 100, 2),
                                                                                                                            'currency' => strtoupper(sanitize($payment->currency)),
                                                                                                                            'status' => ucfirst($payment->status),
                                                                                                                            'customer_email' => sanitize($order['customer_email'] ?? 'N/A'),
                                                                                                                            'customer_name' => sanitize($order['customer_name'] ?? 'N/A'),
                                                                                                                            'created_at' => sanitize((new DateTime('@' . $payment->created))->format('Y-m-d H:i:s')),
                                                                                                                            'order_id' => sanitize($order['id'] ?? 'N/A'),
                                                                                                                            'order_total' => number_format($order['total_amount'] ?? 0, 2),
                                                                                                                            'order_status' => sanitize(get_status_name($order['status_id'] ?? 0)),
                                                                                                                            'delivery_address' => sanitize(nl2br($order['delivery_address'] ?? 'N/A'))
                                                                                                                        ]) ?>'>View</button>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </form>
    </div>

    <!-- Payment Details Modal -->
    <div class="modal fade" id="paymentDetailsModal" tabindex="-1" aria-labelledby="paymentDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentDetailsModalLabel">Payment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Payment details will be populated here via JavaScript -->
                    <table class="table table-bordered">
                        <tbody id="payment-details-table">
                            <!-- Dynamic Content -->
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Include jQuery and DataTables JS for enhanced table features -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <!-- Include Chart.js for graphical reports -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable with advanced features
            var table = $('#sales_table').DataTable({
                "paging": true,
                "pageLength": <?= PER_PAGE ?>,
                "lengthChange": false,
                "ordering": true,
                "order": [
                    [8, "desc"]
                ], // Default sort by Created At descending
                "searching": true,
                "columnDefs": [{
                        "orderable": false,
                        "targets": [0, 10, 11]
                    } // Disable ordering on checkbox, Invoice, and Details columns
                ]
            });

            // Select/Deselect all checkboxes
            $('#select_all').on('click', function() {
                var rows = table.rows({
                    'search': 'applied'
                }).nodes();
                $('input[type="checkbox"]', rows).prop('checked', this.checked);
            });

            // If any checkbox is unchecked, uncheck the select all checkbox
            $('#sales_table tbody').on('change', 'input[type="checkbox"]', function() {
                if (!this.checked) {
                    var el = $('#select_all').get(0);
                    if (el && el.checked && ('indeterminate' in el)) {
                        el.indeterminate = true;
                    }
                }
            });

            // Handle form submission for bulk export
            $('#bulk_export_form').on('submit', function(e) {
                e.preventDefault();
                var selected = [];
                $('input[name="selected_payments[]"]:checked').each(function() {
                    selected.push($(this).val());
                });
                if (selected.length === 0) {
                    alert('No payments selected for export.');
                    return;
                }
                // Create a form and submit
                var form = $('<form method="POST" action="sales.php?export_csv=1"></form>');
                selected.forEach(function(payment_id) {
                    form.append('<input type="hidden" name="selected_payments[]" value="' + payment_id + '">');
                });
                $('body').append(form);
                form.submit();
            });

            // Initialize Chart.js for sales over time
            var ctx = document.getElementById('salesChart').getContext('2d');
            var salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_labels) ?>,
                    datasets: [{
                        label: 'Sales (€)',
                        data: <?= json_encode($chart_data) ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        fill: true,
                        tension: 0.1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Handle view details button click
            $('.view-details').on('click', function() {
                var paymentData = $(this).data('payment');
                var tableBody = $('#payment-details-table');
                tableBody.empty();
                $.each(paymentData, function(key, value) {
                    tableBody.append('<tr><th>' + key.replace(/_/g, ' ').toUpperCase() + '</th><td>' + value + '</td></tr>');
                });
                $('#paymentDetailsModal').modal('show');
            });
        });
    </script>
</body>

</html>