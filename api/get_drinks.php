<?php
// api/get_drinks.php
header('Content-Type: application/json');

require_once '../admin/includes/db_connect.php';

try {
    $stmt = $pdo->query('SELECT id, name, price FROM drinks ORDER BY name ASC');
    $drinks = $stmt->fetchAll();
    echo json_encode(['success' => true, 'drinks' => $drinks]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch drinks']);
}
