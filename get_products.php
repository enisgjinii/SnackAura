<?php
// get_products.php

require 'db.php';

// Get category_id from GET parameters
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

try {
    if ($category_id > 0) {
        // Fetch products for a specific category
        $stmt = $pdo->prepare("SELECT id, category_id, name, description, image_url, is_new, is_offer, is_active, created_at FROM products WHERE is_active = 1 AND category_id = ? ORDER BY name ASC");
        $stmt->execute([$category_id]);
    } else {
        // Fetch all active products
        $stmt = $pdo->prepare("SELECT id, category_id, name, description, image_url, is_new, is_offer, is_active, created_at FROM products WHERE is_active = 1 ORDER BY name ASC");
        $stmt->execute();
    }

    $products = $stmt->fetchAll();

    // Add price to each product by fetching the default size price
    foreach ($products as &$product) {
        // Fetch the default size price (assuming the first size is default)
        $size_stmt = $pdo->prepare("SELECT ps.id, s.name AS size_name, ps.price FROM product_sizes ps JOIN sizes s ON ps.size_id = s.id WHERE ps.product_id = ? ORDER BY ps.price ASC LIMIT 1");
        $size_stmt->execute([$product['id']]);
        $size = $size_stmt->fetch();

        if ($size) {
            // Ensure price is a valid number
            $price = floatval($size['price']);
            if ($price < 0) {
                // Handle negative prices if necessary
                $price = 0.00;
            }
            $product['price'] = $price;
            $product['size_id'] = intval($size['id']);
            $product['size_name'] = $size['size_name'];
        } else {
            // If no size found, set price to 0 and provide a default size name
            $product['price'] = 0.00;
            $product['size_id'] = null;
            $product['size_name'] = 'Keine Größe';
        }
    }

    echo json_encode(['products' => $products]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch products']);
}
