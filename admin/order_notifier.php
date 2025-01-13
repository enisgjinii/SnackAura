<?php
header('Content-Type: application/json');
require_once 'includes/db_connect.php'; // or adjust path as needed

try {
    // Count how many orders are in "New Order" status and not deleted
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt 
                           FROM orders 
                           WHERE status='New Order' AND is_deleted=0");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $countNew = (int)$row['cnt'];
    echo json_encode(['countNew' => $countNew]);
} catch (PDOException $e) {
    echo json_encode(['countNew' => 0, 'error' => $e->getMessage()]);
}
