<?php
// api/get_categories.php
header('Content-Type: application/json');

require_once '../admin/includes/db_connect.php';

try {
    $stmt = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC');
    $categories = $stmt->fetchAll();
    echo json_encode(['success' => true, 'categories' => $categories]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch categories']);
}
