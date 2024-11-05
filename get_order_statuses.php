<?php
// get_order_statuses.php

require 'db.php';

try {
    $stmt = $pdo->prepare("SELECT id, status FROM order_statuses ORDER BY id ASC");
    $stmt->execute();
    $statuses = $stmt->fetchAll();

    echo json_encode(['order_statuses' => $statuses]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch order statuses']);
}
