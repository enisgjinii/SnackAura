<?php
// get_categories.php

require 'db.php';

try {
    $stmt = $pdo->prepare("SELECT id, name, description FROM categories WHERE is_active = 1 ORDER BY name ASC");
    $stmt->execute();
    $categories = $stmt->fetchAll();

    echo json_encode(['categories' => $categories]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch categories']);
}
