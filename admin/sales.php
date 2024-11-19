<?php
// admin/sales.php

// Enable detailed error reporting for development (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Autoload Composer dependencies using absolute path for reliability
require 'vendor/autoload.php'; // Adjust the path as needed

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Dompdf\Dompdf;

// Initialize Stripe with your Secret Key (consider using environment variables)
$stripeSecretKey = 'sk_test_51QByfJE4KNNCb6nuElXbMZUUan5s9fkJ1N2Ce3fMunhTipH5LGonlnO3bcq6eaxXINmWDuMzfw7RFTNTOb1jDsEm00IzfwoFx2'; // Replace with your actual key or use getenv('STRIPE_SECRET_KEY')
if (!$stripeSecretKey) {
    die("Stripe Secret Key not set.");
}
Stripe::setApiKey($stripeSecretKey);

// Function to log errors
function log_error($message, $context = '')
{
    $log_file = __DIR__ . '/error_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] $message";
    if ($context) {
        $entry .= " | Context: $context";
    }
    $entry .= PHP_EOL;
    file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);
}

// Function to sanitize output
function sanitize($data)
{
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Function to map status_id to status names
function get_status_name($status_id)
{
    $status_names = [
        1 => 'Pending',
        2 => 'Processing',
        3 => 'Ready for Pickup',
        4 => 'Pending Cash Payment',
        5 => 'Paid',
        6 => 'Payment Failed',
        // Add other status IDs and names as needed
    ];
    return $status_names[$status_id] ?? 'Unknown';
}

// Function to generate invoice PDF
function generate_invoice_pdf($payment, $order)
{
    // Initialize Dompdf
    $dompdf = new Dompdf();

    // Build HTML content for the invoice
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Invoice - <?= htmlspecialchars($order['id']) ?></title>
        <style>
            body {
                font-family: DejaVu Sans, sans-serif;
                margin: 20px;
            }
            .header {
                text-align: center;
            }
            .header h1 {
                margin-bottom: 0;
            }
            .header p {
                margin-top: 5px;
            }
            .details {
                margin-top: 20px;
            }
            .details table {
                width: 100%;
                border-collapse: collapse;
            }
            .details th,
            .details td {
                border: 1px solid #000;
                padding: 8px;
                text-align: left;
            }
            .total {
                margin-top: 20px;
                text-align: right;
            }
            .footer {
                margin-top: 30px;
                text-align: center;
                font-size: 0.9em;
                color: #555;
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
                <tr>
                    <th>Invoice Number</th>
                    <td><?= htmlspecialchars($order['id']) ?></td>
                </tr>
                <tr>
                    <th>Payment ID</th>
                    <td><?= htmlspecialchars($payment->id) ?></td>
                </tr>
                <tr>
                    <th>Customer Name</th>
                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                </tr>
                <tr>
                    <th>Customer Email</th>
                    <td><?= htmlspecialchars($order['customer_email']) ?></td>
                </tr>
                <tr>
                    <th>Customer Phone</th>
                    <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                </tr>
                <tr>
                    <th>Delivery Address</th>
                    <td><?= nl2br(htmlspecialchars($order['delivery_address'])) ?></td>
                </tr>
                <tr>
                    <th>Scheduled Date & Time</th>
                    <td><?= htmlspecialchars($order['scheduled_date']) ?> <?= htmlspecialchars($order['scheduled_time']) ?></td>
                </tr>
                <tr>
                    <th>Payment Method</th>
                    <td><?= htmlspecialchars(ucfirst($order['payment_method'])) ?></td>
                </tr>
                <tr>
                    <th>Amount</th>
                    <td>€<?= number_format($payment->amount / 100, 2) ?></td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td><?= htmlspecialchars(ucfirst($payment->status)) ?></td>
                </tr>
                <tr>
                    <th>Created At</th>
                    <td><?= htmlspecialchars((new DateTime('@' . $payment->created))->format('Y-m-d H:i:s')) ?></td>
                </tr>
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
    $html = ob_get_clean();

    // Load HTML to Dompdf
    $dompdf->loadHtml($html);

    // (Optional) Setup the paper size and orientation
    $dompdf->setPaper('A4', 'portrait');

    // Render the HTML as PDF
    $dompdf->render();

    // Output the generated PDF to Browser (force download)
    $dompdf->stream("invoice_{$order['id']}.pdf", ["Attachment" => true]);
    exit;
}

// Handle Invoice Generation Request before any output
if (isset($_GET['generate_invoice'])) {
    // Include database connection before any output
    include __DIR__ . '/includes/db_connect.php'; // Adjust the path if necessary

    $payment_id = $_GET['payment_id'] ?? '';
    // Validate payment ID format
    if (!preg_match('/^pi_\w+$/', $payment_id)) {
        die("Invalid Payment ID.");
    }
    // Retrieve the payment from Stripe
    try {
        $payment = PaymentIntent::retrieve($payment_id);
    } catch (Exception $e) {
        log_error("Error retrieving payment $payment_id: " . $e->getMessage(), "Invoice Generation");
        die("Error retrieving payment.");
    }
    // Extract order_id from payment metadata
    $order_id = $payment->metadata->order_id ?? null;
    if (!$order_id) {
        die("Order ID not found in payment metadata.");
    }
    // Fetch the corresponding order from the database
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            die("Order not found for this payment.");
        }
    } catch (PDOException $e) {
        log_error("Database error while fetching order ID $order_id: " . $e->getMessage(), "Invoice Generation");
        die("Database error.");
    }
    // Generate and serve the invoice PDF
    generate_invoice_pdf($payment, $order);
    // No further processing
}

// Include header after handling invoice generation
include __DIR__ . '/includes/header.php'; // Adjust the path if necessary
include __DIR__ . '/includes/db_connect.php'; // Adjust the path if necessary

// Fetch all PaymentIntents from Stripe
$payments = [];
$has_more = true;
$starting_after = null;
try {
    while ($has_more) {
        $params = [
            'limit' => 100, // Maximum allowed by Stripe
        ];
        if ($starting_after) {
            $params['starting_after'] = $starting_after;
        }
        $response = PaymentIntent::all($params);
        foreach ($response->data as $payment) {
            $payments[] = $payment;
        }
        $has_more = $response->has_more;
        if ($has_more) {
            $starting_after = end($response->data)->id;
        }
    }
} catch (Exception $e) {
    log_error("Error fetching payments from Stripe: " . $e->getMessage(), "Stripe API");
    die("Error fetching payments.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Sales Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Optional: Include any additional CSS here -->
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Sales Dashboard</h1>
        <!-- Filters and Search -->
        <div class="row mb-4">
            <div class="col-md-3">
                <input type="text" id="search" class="form-control" placeholder="Search by Customer Email">
            </div>
            <div class="col-md-3">
                <select id="status_filter" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="succeeded">Succeeded</option>
                    <option value="requires_payment_method">Failed</option>
                    <option value="requires_action">Requires Action</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="date" id="start_date" class="form-control" placeholder="Start Date">
            </div>
            <div class="col-md-3">
                <input type="date" id="end_date" class="form-control" placeholder="End Date">
            </div>
        </div>
        <!-- Sales Table -->
        <table class="table table-striped table-bordered" id="sales_table">
            <thead class="table-dark">
                <tr>
                    <th>Payment ID</th>
                    <th>Amount (€)</th>
                    <th>Currency</th>
                    <th>Status</th>
                    <th>Payment Method</th>
                    <th>Receipt URL</th>
                    <th>Customer Email</th>
                    <th>Customer Name</th>
                    <th>Created At</th>
                    <th>Order Details</th>
                    <th>Invoice</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="11" class="text-center">No payments found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payments as $payment): ?>
                        <?php
                        // Extract order_id from payment metadata
                        $order_id = $payment->metadata->order_id ?? null;
                        // Fetch the corresponding order
                        $order = null;
                        if ($order_id) {
                            try {
                                $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
                                $stmt->execute([$order_id]);
                                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                            } catch (PDOException $e) {
                                log_error("Failed to fetch order ID $order_id: " . $e->getMessage(), "Sales Dashboard");
                            }
                        }
                        ?>
                        <tr>
                            <td><?= sanitize($payment->id) ?></td>
                            <td><?= number_format($payment->amount / 100, 2) ?></td>
                            <td><?= strtoupper(sanitize($payment->currency)) ?></td>
                            <td>
                                <?php
                                switch ($payment->status) {
                                    case 'succeeded':
                                        echo '<span class="badge bg-success">Succeeded</span>';
                                        break;
                                    case 'requires_payment_method':
                                        echo '<span class="badge bg-danger">Failed</span>';
                                        break;
                                    case 'requires_action':
                                        echo '<span class="badge bg-warning text-dark">Requires Action</span>';
                                        break;
                                    default:
                                        echo '<span class="badge bg-secondary">' . sanitize($payment->status) . '</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if (isset($payment->payment_method_details->card)) {
                                    $card = $payment->payment_method_details->card;
                                    echo sanitize(ucfirst($payment->payment_method_details->type)) . ' ending with ' . sanitize($card->last4);
                                } else {
                                    echo sanitize(ucfirst($payment->payment_method_details->type));
                                }
                                ?>
                            </td>
                            <td>
                                <?php if (isset($payment->receipt_url)): ?>
                                    <a href="<?= sanitize($payment->receipt_url) ?>" target="_blank">View Receipt</a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td><?= sanitize($payment->receipt_email ?? 'N/A') ?></td>
                            <td>
                                <?php
                                if ($order) {
                                    echo sanitize($order['customer_name']);
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td><?= sanitize((new DateTime('@' . $payment->created))->format('Y-m-d H:i:s')) ?></td>
                            <td>
                                <?php
                                if ($order) {
                                    echo 'Order ID: ' . sanitize($order['id']) . '<br>';
                                    echo 'Total: €' . number_format($order['total_amount'], 2) . '<br>';
                                    echo 'Status: ' . sanitize(get_status_name($order['status_id']));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($order): ?>
                                    <a href="sales.php?generate_invoice=1&payment_id=<?= urlencode($payment->id) ?>" class="btn btn-sm btn-primary">Download Invoice</a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <!-- Pagination Controls (Optional) -->
        <!-- Implement as needed -->
    </div>
    <!-- Bootstrap JS (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Optional: Include any additional JS here -->
    <!-- For example, you can add JavaScript for handling filters and search -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            const statusFilter = document.getElementById('status_filter');
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            const salesTable = document.getElementById('sales_table').getElementsByTagName('tbody')[0];
            const rows = salesTable.getElementsByTagName('tr');

            function filterTable() {
                const searchTerm = searchInput.value.toLowerCase();
                const statusTerm = statusFilter.value;
                const start = startDate.value ? new Date(startDate.value) : null;
                const end = endDate.value ? new Date(endDate.value) : null;

                for (let i = 0; i < rows.length; i++) {
                    const cells = rows[i].getElementsByTagName('td');
                    let email = cells[6].textContent.toLowerCase();
                    let status = cells[3].textContent.toLowerCase();
                    let createdAt = new Date(cells[8].textContent);
                    let matchesSearch = email.includes(searchTerm);
                    let matchesStatus = statusTerm === '' || status.includes(statusTerm);
                    let matchesDate = true;

                    if (start) {
                        matchesDate = createdAt >= start;
                    }
                    if (end && matchesDate) {
                        matchesDate = createdAt <= end;
                    }

                    if (matchesSearch && matchesStatus && matchesDate) {
                        rows[i].style.display = '';
                    } else {
                        rows[i].style.display = 'none';
                    }
                }
            }

            searchInput.addEventListener('input', filterTable);
            statusFilter.addEventListener('change', filterTable);
            startDate.addEventListener('change', filterTable);
            endDate.addEventListener('change', filterTable);
        });
    </script>
</body>
</html>
