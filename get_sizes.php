<?php
header('Content-Type: application/json');
require_once 'db.php'; // Include your database connection

if (!isset($_GET['product_id'])) {
    echo json_encode(['error' => 'Product ID is required']);
    exit;
}

$product_id = intval($_GET['product_id']);

// Fetch sizes available for the product
$stmt = $pdo->prepare("
    SELECT s.id, s.name, ps.price
    FROM sizes s
    INNER JOIN product_sizes ps ON s.id = ps.size_id
    WHERE ps.product_id = :product_id
");
$stmt->execute(['product_id' => $product_id]);
$sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['sizes' => $sizes]);
