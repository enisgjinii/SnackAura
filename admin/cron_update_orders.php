<?php
// admin/cron_update_orders.php

// Ensure this script is only accessible via command line
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden";
    exit();
}

require_once 'includes/db_connect.php';

// Example Cron Task: Update orders from 'Pending' to 'Processing' after 30 minutes
try {
    // Fetch status IDs for 'Pending' and 'Processing'
    $stmt = $pdo->prepare('SELECT id, status FROM order_statuses WHERE status IN (?, ?)');
    $stmt->execute(['Pending', 'Processing']);
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $status_ids = [];
    foreach ($statuses as $status) {
        $status_ids[$status['status']] = $status['id'];
    }

    if (!isset($status_ids['Pending']) || !isset($status_ids['Processing'])) {
        throw new Exception("Required statuses not found in 'order_statuses' table.");
    }

    // Update orders
    $stmt = $pdo->prepare('
        UPDATE orders 
        SET status_id = :processing_id 
        WHERE status_id = :pending_id 
        AND created_at <= (NOW() - INTERVAL 30 MINUTE)
    ');
    $stmt->bindParam(':processing_id', $status_ids['Processing'], PDO::PARAM_INT);
    $stmt->bindParam(':pending_id', $status_ids['Pending'], PDO::PARAM_INT);
    $stmt->execute();

    $updated_rows = $stmt->rowCount();
    echo "Cron Task Completed: Updated $updated_rows orders from 'Pending' to 'Processing'.\n";
} catch (Exception $e) {
    error_log("Cron Task Error: " . $e->getMessage());
    echo "Cron Task Failed: " . $e->getMessage() . "\n";
}
