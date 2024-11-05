<?php
// api/get_products.php
header('Content-Type: application/json');

require_once '../admin/includes/db_connect.php';

$category_id = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;

try {
    if ($category_id > 0) {
        $stmt = $pdo->prepare('SELECT id, name, price, description, image FROM products WHERE category_id = ? ORDER BY name ASC');
        $stmt->execute([$category_id]);
    } else {
        $stmt = $pdo->query('SELECT id, name, price, description, image FROM products ORDER BY name ASC');
    }
    $products = $stmt->fetchAll();
    echo json_encode(['success' => true, 'products' => $products]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch products']);
}
