<?php
// retrieve_payments.php

require '../vendor/autoload.php';
session_start();

// Define constants
define('ERROR_LOG_FILE', __DIR__ . '/errors.md');

// Function to log errors (reuse from your existing code)
function log_error_markdown($message, $context = '')
{
    $t = date('Y-m-d H:i:s');
    $entry = "### [$t] Error\n\n**Message:** " . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n\n";
    if ($context) {
        $entry .= "**Context:** " . htmlspecialchars(print_r($context, true), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n\n";
    }
    $entry .= "---\n\n";
    file_put_contents(ERROR_LOG_FILE, $entry, FILE_APPEND | LOCK_EX);
}

// Set exception and error handlers (reuse from your existing code)
set_exception_handler(function ($e) {
    log_error_markdown("Uncaught Exception: " . $e->getMessage(), $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
    exit;
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    if (!isset($_SESSION['sumup_access_token'])) {
        // Redirect to authorization if not authenticated
        header('Location: start_authorization.php');
        exit;
    }

    $accessToken = $_SESSION['sumup_access_token'];

    // Optionally, handle token refresh if you have a refresh token
    // (Not shown here for brevity)

    // Step 2: Fetch Transactions
    $transactionsUrl = 'https://api.sumup.com/v0.1/me/transactions';

    $ch2 = curl_init($transactionsUrl);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    $result2 = curl_exec($ch2);

    if ($result2 === false) {
        throw new Exception("CURL Error: " . curl_error($ch2));
    }

    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    if ($httpCode2 !== 200) {
        throw new Exception("Failed to fetch transactions. HTTP Status Code: $httpCode2. Response: $result2");
    }

    $transactions = json_decode($result2, true);

    if (!is_array($transactions)) {
        throw new Exception("Invalid transactions response: " . $result2);
    }

    // Step 3: Display or Process Transactions
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>SumUp Transactions</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    </head>
    <body>
        <div class="container mt-5">
            <h1 class="mb-4">SumUp Transactions</h1>
            <?php if (empty($transactions)): ?>
                <p>No transactions found.</p>
            <?php else: ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Currency</th>
                            <th>Date</th>
                            <th>Payment Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?= htmlspecialchars($transaction['transaction_code']) ?></td>
                                <td><?= htmlspecialchars($transaction['status']) ?></td>
                                <td><?= htmlspecialchars($transaction['amount']) ?></td>
                                <td><?= htmlspecialchars($transaction['currency']) ?></td>
                                <td><?= htmlspecialchars($transaction['timestamp']) ?></td>
                                <td><?= htmlspecialchars($transaction['payment_type']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    log_error_markdown("Error in retrieve_payments.php: " . $e->getMessage(), $e->getTraceAsString());
    http_response_code(500);
    echo "<h1>An error occurred</h1>";
    echo "<p>Please try again later.</p>";
    exit;
}
