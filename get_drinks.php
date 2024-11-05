<?php
header('Content-Type: application/json');
require_once 'db.php'; // Ensure this file establishes a PDO connection as $pdo

try {
    $stmt = $pdo->prepare("SELECT `id`, `name`, `price` FROM `drinks` WHERE `is_active` = 1");
    $stmt->execute();
    $drinks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['drinks' => $drinks]);
} catch (Exception $e) {
    error_log("Error fetching drinks: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to fetch drinks.']);
}
