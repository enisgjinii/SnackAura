<?php
// get_extras.php

require 'db.php';

try {
    $stmt = $pdo->prepare("SELECT id, name, price, category FROM extras ORDER BY name ASC");
    $stmt->execute();
    $extras = $stmt->fetchAll();

    // Ensure all extras have numeric prices
    foreach ($extras as &$extra) {
        $extra['price'] = floatval($extra['price']);
        if ($extra['price'] < 0) {
            $extra['price'] = 0.00;
        }
    }

    echo json_encode(['extras' => $extras]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch extras']);
}
