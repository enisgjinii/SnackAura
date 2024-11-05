<?php
// api/get_extras.php
header('Content-Type: application/json');

require_once '../admin/includes/db_connect.php';

try {
    $stmt = $pdo->query('SELECT id, name, price FROM extras ORDER BY name ASC');
    $extras = $stmt->fetchAll();
    echo json_encode(['success' => true, 'extras' => $extras]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch extras']);
}
