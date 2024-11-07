<?php
// index.php
session_start();
require 'db.php';

// Initialize Cart in Session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle Add to Cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = max(1, (int)$_POST['quantity']);
    $size_id = isset($_POST['size']) ? (int)$_POST['size'] : null;
    $extras = isset($_POST['extras']) ? $_POST['extras'] : [];
    $drink_id = isset($_POST['drink']) ? (int)$_POST['drink'] : null;
    $special_instructions = trim($_POST['special_instructions']);

    // Fetch Product Details
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if ($product) {
        // Fetch Size Details
        if ($size_id) {
            $stmt = $pdo->prepare("SELECT ps.price, s.name FROM product_sizes ps JOIN sizes s ON ps.size_id = s.id WHERE ps.product_id = ? AND ps.size_id = ?");
            $stmt->execute([$product_id, $size_id]);
            $size = $stmt->fetch();
            if (!$size) {
                $size_id = null; // Invalid size
            }
        }

        // Fetch Extras Details
        $extras_details = [];
        $extras_total = 0.00;
        if (!empty($extras)) {
            // Prepare placeholders
            $placeholders = implode(',', array_fill(0, count($extras), '?'));
            $stmt = $pdo->prepare("SELECT * FROM extras WHERE id IN ($placeholders) AND category = 'addon'");
            $stmt->execute($extras);
            $extras_details = $stmt->fetchAll();
            $extras_total = array_reduce($extras_details, function ($carry, $extra) {
                return $carry + $extra['price'];
            }, 0.00);
        }

        // Fetch Drink Details
        $drink_details = null;
        $drink_total = 0.00;
        if ($drink_id) {
            $stmt = $pdo->prepare("SELECT * FROM drinks WHERE id = ?");
            $stmt->execute([$drink_id]);
            $drink_details = $stmt->fetch();
            if ($drink_details) {
                $drink_total = $drink_details['price'];
            } else {
                $drink_id = null; // Invalid drink
            }
        }

        // Calculate Total Price
        $size_price = isset($size['price']) ? $size['price'] : 0.00;
        $unit_price = $size_price + $extras_total + $drink_total;
        $total_price = $unit_price * $quantity;

        // Create Cart Item Array
        $cart_item = [
            'product_id' => $product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'image_url' => $product['image_url'],
            'size' => isset($size['name']) ? $size['name'] : null,
            'size_id' => $size_id,
            'extras' => $extras_details,
            'drink' => $drink_details,
            'special_instructions' => $special_instructions,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'total_price' => $total_price
        ];

        // Check for Duplicate Items
        $duplicate_index = null;
        foreach ($_SESSION['cart'] as $index => $item) {
            if (
                $item['product_id'] === $cart_item['product_id'] &&
                $item['size_id'] === $cart_item['size_id'] &&
                ($item['drink']['id'] ?? null) === ($cart_item['drink']['id'] ?? null) &&
                $item['special_instructions'] === $cart_item['special_instructions'] &&
                count(array_intersect(array_column($item['extras'], 'id'), array_column($cart_item['extras'], 'id'))) === count($cart_item['extras'])
            ) {
                $duplicate_index = $index;
                break;
            }
        }

        if ($duplicate_index !== null) {
            // Update Existing Item
            $_SESSION['cart'][$duplicate_index]['quantity'] += $quantity;
            $_SESSION['cart'][$duplicate_index]['total_price'] += $total_price;
        } else {
            // Add New Item
            $_SESSION['cart'][] = $cart_item;
        }

        // Redirect to prevent form resubmission
        header("Location: index.php?added=1");
        exit;
    } else {
        // Invalid product
        header("Location: index.php?error=invalid_product");
        exit;
    }
}

// Handle Remove from Cart
if (isset($_GET['remove'])) {
    $remove_index = (int)$_GET['remove'];
    if (isset($_SESSION['cart'][$remove_index])) {
        unset($_SESSION['cart'][$remove_index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']); // Reindex array
    }
    header("Location: index.php?removed=1");
    exit;
}

// Handle Update Cart Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $item_index = (int)$_POST['item_index'];
    if (isset($_SESSION['cart'][$item_index])) {
        $quantity = max(1, (int)$_POST['quantity']);
        $size_id = isset($_POST['size']) ? (int)$_POST['size'] : null;
        $extras = isset($_POST['extras']) ? $_POST['extras'] : [];
        $drink_id = isset($_POST['drink']) ? (int)$_POST['drink'] : null;
        $special_instructions = trim($_POST['special_instructions']);

        // Fetch Product Details
        $product_id = $_SESSION['cart'][$item_index]['product_id'];
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        if ($product) {
            // Fetch Size Details
            if ($size_id) {
                $stmt = $pdo->prepare("SELECT ps.price, s.name FROM product_sizes ps JOIN sizes s ON ps.size_id = s.id WHERE ps.product_id = ? AND ps.size_id = ?");
                $stmt->execute([$product_id, $size_id]);
                $size = $stmt->fetch();
                if (!$size) {
                    $size_id = null; // Invalid size
                }
            }

            // Fetch Extras Details
            $extras_details = [];
            $extras_total = 0.00;
            if (!empty($extras)) {
                // Prepare placeholders
                $placeholders = implode(',', array_fill(0, count($extras), '?'));
                $stmt = $pdo->prepare("SELECT * FROM extras WHERE id IN ($placeholders) AND category = 'addon'");
                $stmt->execute($extras);
                $extras_details = $stmt->fetchAll();
                $extras_total = array_reduce($extras_details, function ($carry, $extra) {
                    return $carry + $extra['price'];
                }, 0.00);
            }

            // Fetch Drink Details
            $drink_details = null;
            $drink_total = 0.00;
            if ($drink_id) {
                $stmt = $pdo->prepare("SELECT * FROM drinks WHERE id = ?");
                $stmt->execute([$drink_id]);
                $drink_details = $stmt->fetch();
                if ($drink_details) {
                    $drink_total = $drink_details['price'];
                } else {
                    $drink_id = null; // Invalid drink
                }
            }

            // Calculate Total Price
            $size_price = isset($size['price']) ? $size['price'] : 0.00;
            $unit_price = $size_price + $extras_total + $drink_total;
            $total_price = $unit_price * $quantity;

            // Update Cart Item
            $_SESSION['cart'][$item_index] = [
                'product_id' => $product['id'],
                'name' => $product['name'],
                'description' => $product['description'],
                'image_url' => $product['image_url'],
                'size' => isset($size['name']) ? $size['name'] : null,
                'size_id' => $size_id,
                'extras' => $extras_details,
                'drink' => $drink_details,
                'special_instructions' => $special_instructions,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'total_price' => $total_price
            ];
        }

        header("Location: index.php?updated=1");
        exit;
    } else {
        header("Location: index.php?error=invalid_update");
        exit;
    }
}

// Handle Checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    // Ensure cart is not empty
    if (empty($_SESSION['cart'])) {
        header("Location: index.php?error=empty_cart");
        exit;
    }

    // Fetch Order Details from POST
    $customer_name = trim($_POST['customer_name']);
    $customer_email = trim($_POST['customer_email']);
    $customer_phone = trim($_POST['customer_phone']);
    $delivery_address = trim($_POST['delivery_address']);

    // Basic Validation
    if (empty($customer_name) || empty($customer_email) || empty($customer_phone) || empty($delivery_address)) {
        header("Location: index.php?error=invalid_order_details");
        exit;
    }

    // Calculate Total Amount
    $total_amount = array_reduce($_SESSION['cart'], function ($carry, $item) {
        return $carry + $item['total_price'];
    }, 0.00);

    try {
        // Start Transaction
        $pdo->beginTransaction();

        // Insert into orders table
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, customer_name, customer_email, customer_phone, delivery_address, total_amount, status_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        // Assuming user_id is null for guest orders
        $stmt->execute([null, $customer_name, $customer_email, $customer_phone, $delivery_address, $total_amount, 1]);
        $order_id = $pdo->lastInsertId();

        // Insert into order_items, order_extras, order_drinks
        $order_items = $_SESSION['cart'];
        $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, product_id, size_id, quantity, price, extras, special_instructions) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_extras = $pdo->prepare("INSERT INTO order_extras (order_item_id, extra_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
        $stmt_drinks = $pdo->prepare("INSERT INTO order_drinks (order_item_id, drink_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");

        foreach ($order_items as $item) {
            // Serialize extras IDs as comma-separated values
            $extras_ids = !empty($item['extras']) ? implode(',', array_column($item['extras'], 'id')) : null;

            // Insert into order_items
            $stmt_item->execute([
                $order_id,
                $item['product_id'],
                $item['size_id'],
                $item['quantity'],
                $item['unit_price'],
                $extras_ids,
                $item['special_instructions']
            ]);
            $order_item_id = $pdo->lastInsertId();

            // Insert into order_extras
            foreach ($item['extras'] as $extra) {
                $stmt_extras->execute([
                    $order_item_id,
                    $extra['id'],
                    1, // Assuming quantity 1 for each extra
                    $extra['price'],
                    $extra['price']
                ]);
            }

            // Insert into order_drinks
            if ($item['drink']) {
                $stmt_drinks->execute([
                    $order_item_id,
                    $item['drink']['id'],
                    1, // Assuming quantity 1 for each drink
                    $item['drink']['price'],
                    $item['drink']['price']
                ]);
            }
        }

        // Commit Transaction
        $pdo->commit();

        // Clear Cart
        $_SESSION['cart'] = [];

        // Redirect to Confirmation
        header("Location: index.php?order=success");
        exit;
    } catch (Exception $e) {
        // Rollback Transaction
        $pdo->rollBack();

        // Log the error (in real applications, use proper logging)
        error_log("Order Placement Failed: " . $e->getMessage());

        header("Location: index.php?error=order_failed");
        exit;
    }
}

// Fetch Categories
$stmt = $pdo->prepare("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC");
$stmt->execute();
$categories = $stmt->fetchAll();

// Fetch Products with Their Associated Extras
$stmt = $pdo->prepare("
    SELECT 
        p.*, 
        GROUP_CONCAT(e.id SEPARATOR ',') AS extra_ids,
        GROUP_CONCAT(e.name SEPARATOR ',') AS extra_names,
        GROUP_CONCAT(e.price SEPARATOR ',') AS extra_prices
    FROM 
        products p
    LEFT JOIN 
        product_extras pe ON p.id = pe.product_id
    LEFT JOIN 
        extras e ON pe.extra_id = e.id
    WHERE 
        p.is_active = 1
    GROUP BY 
        p.id
    ORDER BY 
        p.name ASC
");
$stmt->execute();
$products = $stmt->fetchAll();

// Process extras for each product
foreach ($products as &$product) {
    // Initialize extras array
    $product['extras'] = [];

    if (!empty($product['extra_ids'])) {
        $extra_ids = explode(',', $product['extra_ids']);
        $extra_names = explode(',', $product['extra_names']);
        $extra_prices = explode(',', $product['extra_prices']);

        foreach ($extra_ids as $index => $extra_id) {
            $product['extras'][] = [
                'id' => $extra_id,
                'name' => $extra_names[$index],
                'price' => $extra_prices[$index]
            ];
        }
    }
}
unset($product); // Break the reference

// Fetch Drinks
$stmt = $pdo->prepare("SELECT * FROM drinks ORDER BY name ASC");
$stmt->execute();
$drinks = $stmt->fetchAll();

// Fetch Sizes
$stmt = $pdo->prepare("SELECT * FROM sizes ORDER BY name ASC");
$stmt->execute();
$sizes = $stmt->fetchAll();

// Fetch Sizes for Each Product
$product_sizes = [];
$stmt = $pdo->prepare("SELECT ps.size_id, s.name, ps.price FROM product_sizes ps JOIN sizes s ON ps.size_id = s.id WHERE ps.product_id = ? ORDER BY s.name ASC");
foreach ($products as $product) {
    $stmt->execute([$product['id']]);
    $product_sizes[$product['id']] = $stmt->fetchAll();
}

// Calculate Cart Total
$cart_total = array_reduce($_SESSION['cart'], function ($carry, $item) {
    return $carry + $item['total_price'];
}, 0.00);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Delivery</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Saira:wght@100..900&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Flag Icons CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/6.6.6/css/flag-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        /* Root Variables */
        :root {
            --primary-color: #9a2a18;
            --secondary-color: #FFFFFF;
            --accent-color: #d7b96f;
            --neutral-light: #F5F5F5;
            --neutral-dark: #333333;
            --font-family: 'Saira', sans-serif;
            --border-radius: 8px;
            --transition-speed: 0.3s;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--neutral-light);
            color: var(--neutral-dark);
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* Navbar */
        .navbar {
            background-color: var(--secondary-color);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .navbar-brand img {
            border-radius: 50%;
            border: 2px solid #e0e0e0;
            transition: transform var(--transition-speed);
        }

        .navbar-brand img:hover {
            transform: scale(1.05);
        }

        .restaurant-name {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-left: 0.5rem;
        }

        /* Language Switcher */
        .language-switcher {
            position: absolute;
            top: 1rem;
            right: 2rem;
            z-index: 1000;
        }

        .language-switcher .dropdown-toggle {
            display: flex;
            align-items: center;
            background: none;
            border: none;
            color: var(--neutral-dark);
            font-weight: 600;
        }

        .language-switcher .flag-icon {
            margin-right: 8px;
        }

        /* Promo Banner */
        .promo-banner {
            background-color: var(--secondary-color);
            padding: 4rem 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .promo-banner h1 {
            font-size: 3.5rem;
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .promo-banner h2 {
            font-size: 2.5rem;
            color: var(--accent-color);
            font-weight: 600;
            margin-top: 1rem;
        }

        .promo-banner p {
            font-size: 1.25rem;
            color: #555555;
            margin-top: 1rem;
            line-height: 1.6;
        }

        .promo-banner img {
            width: 100%;
            height: auto;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform var(--transition-speed);
        }

        .promo-banner img:hover {
            transform: scale(1.02);
        }

        /* Navigation Tabs */
        .nav-tabs {
            justify-content: center;
            border-bottom: none;
            margin-top: -1rem;
        }

        .nav-tabs .nav-link {
            color: var(--secondary-color);
            font-weight: 600;
            border: none;
            transition: color var(--transition-speed), background-color var(--transition-speed);
            padding: 0.75rem 1.5rem;
            background-color: #fcd008;
            margin-left: 15px;
            border-radius: 5px;
        }

        .nav-tabs .nav-link.active {
            color: var(--secondary-color);
            background-color: #9a2a18;
            border-radius: 5px;
        }

        /* Menu Items */
        .menu-item img {
            width: 100%;
            height: auto;
            object-fit: cover;
            transition: transform var(--transition-speed);
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .menu-item img:hover {
            /* Optional: Uncomment to add hover effect */
            /* transform: scale(1.05); */
        }

        .menu-item .card-body {
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
        }

        .menu-item .card-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .menu-item .card-text {
            flex-grow: 1;
            color: #777777;
            margin-bottom: 1rem;
        }

        .menu-item .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            transition: background-color var(--transition-speed), border-color var(--transition-speed);
            padding: 0.5rem 1rem;
            font-size: 1rem;
        }

        .menu-item .btn-primary:hover {
            background-color: #cc0000;
            border-color: #cc0000;
        }

        /* Order Summary */
        .order-summary {
            max-height: 70vh;
            overflow-y: auto;
            padding: 1.5rem;
            background-color: var(--secondary-color);
            border-radius: var(--border-radius);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 2rem;
        }

        .order-summary .order-title {
            font-size: 2rem;
            color: var(--accent-color);
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .order-summary .card-text {
            font-size: 1.1rem;
            color: #555555;
        }

        .order-summary .btn-primary {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            transition: background-color var(--transition-speed), border-color var(--transition-speed);
            padding: 0.75rem 1.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
        }

        .order-summary .btn-primary:hover {
            background-color: #115293;
            border-color: #115293;
        }

        /* Footer */
        footer {
            background-color: var(--secondary-color);
            border-top: 1px solid #e0e0e0;
            padding: 2rem 0;
        }

        footer img {
            border-radius: var(--border-radius);
            transition: transform var(--transition-speed);
        }

        footer img:hover {
            transform: scale(1.05);
        }

        /* Image Container */
        .image-container {
            position: relative;
        }

        .image-container img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: transform var(--transition-speed);
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        /* Badge Styling */
        .badge-new {
            top: 10px;
            left: 10px;
            background-color: #28a745;
            /* Green */
        }

        .badge-offer {
            top: 10px;
            right: 10px;
            background-color: #ffc107;
            /* Yellow */
        }

        .badge-allergies {
            bottom: 10px;
            left: 10px;
            background-color: #dc3545;
            /* Red */
        }

        /* Ensure all badges are positioned absolutely */
        .badge-new,
        .badge-offer,
        .badge-allergies {
            position: absolute;
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            border-radius: var(--border-radius);
        }

        footer a {
            color: var(--neutral-dark);
            margin-right: 1.5rem;
            transition: color var(--transition-speed);
            font-size: 1.5rem;
        }

        footer a:hover {
            color: var(--primary-color);
        }

        /* Modals */
        .modal-content {
            border-radius: var(--border-radius);
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.2);
        }

        .modal-header,
        .modal-footer {
            border: none;
            padding: 1.5rem 2rem;
        }

        .modal-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent-color);
        }

        /* Toasts */
        .toast-container {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1100;
        }

        /* Skeleton Loading */
        .skeleton {
            background-color: #e0e0e0;
            border-radius: var(--border-radius);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.4;
            }

            100% {
                opacity: 1;
            }
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            display: none;
        }

        .loading-overlay.active {
            display: flex;
        }

        /* Button Animations */
        .btn {
            transition: transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        /* Accessibility Focus Styles */
        .btn:focus,
        .nav-link:focus,
        .dropdown-item:focus,
        input:focus,
        select:focus,
        textarea:focus {
            outline: 2px solid var(--accent-color);
            outline-offset: 2px;
        }

        /* Responsive Adjustments */
        @media (max-width: 1200px) {
            .promo-banner h1 {
                font-size: 3rem;
            }

            .promo-banner h2 {
                font-size: 2.2rem;
            }
        }

        @media (max-width: 992px) {
            .navbar {
                padding: 0.75rem 1.5rem;
            }

            .restaurant-name {
                font-size: 1.25rem;
            }

            .promo-banner {
                padding: 3rem 0;
            }

            .promo-banner h1 {
                font-size: 2.5rem;
            }

            .promo-banner h2 {
                font-size: 1.8rem;
            }

            .promo-banner p {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 576px) {
            .language-switcher {
                position: static;
                margin-top: 1rem;
                text-align: center;
            }

            .promo-banner {
                padding: 2rem 1rem;
            }

            .promo-banner h1 {
                font-size: 2rem;
            }

            .promo-banner h2 {
                font-size: 1.5rem;
            }

            .promo-banner p {
                font-size: 1rem;
            }

            .order-summary {
                position: static;
                max-height: none;
                margin-top: 2rem;
            }

            .order-summary .order-title {
                font-size: 1.5rem;
            }

            .menu-item .card-body {
                padding: 1rem;
            }

            .menu-item .card-title {
                font-size: 1.25rem;
            }

            .menu-item .card-text {
                font-size: 0.95rem;
            }

            .order-summary .btn-primary {
                padding: 0.5rem 1rem;
                font-size: 1rem;
            }

            .nav-tabs .nav-link {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }

            footer a {
                margin-right: 1rem;
                font-size: 1.2rem;
            }
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay" aria-hidden="true">
        <div class="spinner-border text-primary" role="status" aria-label="Loading">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Cart Edit Modal Template -->
    <?php foreach ($_SESSION['cart'] as $index => $item): ?>
        <div class="modal fade" id="editCartModal<?= $index ?>" tabindex="-1" aria-labelledby="editCartModalLabel<?= $index ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="index.php">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editCartModalLabel<?= $index ?>">Edit <?= htmlspecialchars($item['name']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <!-- Quantity Selection -->
                            <div class="mb-3">
                                <label for="quantity<?= $index ?>" class="form-label">Quantity</label>
                                <input type="number" class="form-control" id="quantity<?= $index ?>" name="quantity" value="<?= htmlspecialchars($item['quantity']) ?>" min="1" max="99" required>
                            </div>
                            <!-- Size Selection -->
                            <div class="mb-3">
                                <label for="size<?= $index ?>" class="form-label">Size</label>
                                <select class="form-select" id="size<?= $index ?>" name="size" required>
                                    <option value="">Choose a size</option>
                                    <?php foreach ($product_sizes[$item['product_id']] as $size): ?>
                                        <option value="<?= htmlspecialchars($size['size_id']) ?>" <?= ($item['size_id'] === (int)$size['size_id']) ? 'selected' : '' ?>><?= htmlspecialchars($size['name']) ?> (+<?= number_format($size['price'], 2) ?>€)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Extras Selection as Checkboxes -->
                            <div class="mb-3">
                                <label class="form-label">Extras</label>
                                <div>
                                    <?php
                                    // Fetch available extras for the product
                                    $available_extras = $product_sizes[$item['product_id']][0]['size_id'] ?? null;
                                    ?>
                                    <?php foreach ($item['extras'] as $extra): ?>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" id="edit_extra<?= $index ?>_<?= $extra['id'] ?>" name="extras[]" value="<?= htmlspecialchars($extra['id']) ?>" <?= in_array($extra['id'], array_column($item['extras'], 'id')) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="edit_extra<?= $index ?>_<?= $extra['id'] ?>"><?= htmlspecialchars($extra['name']) ?> (+<?= number_format($extra['price'], 2) ?>€)</label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <!-- Drinks Selection -->
                            <div class="mb-3">
                                <label class="form-label">Drinks</label>
                                <div>
                                    <?php foreach ($drinks as $drink): ?>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" id="edit_drink<?= $index ?>_<?= $drink['id'] ?>" name="drink" value="<?= htmlspecialchars($drink['id']) ?>" <?= ($item['drink']['id'] ?? null) === $drink['id'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="edit_drink<?= $index ?>_<?= $drink['id'] ?>"><?= htmlspecialchars($drink['name']) ?> (+<?= number_format($drink['price'], 2) ?>€)</label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <!-- Special Instructions -->
                            <div class="mb-3">
                                <label for="special_instructions<?= $index ?>" class="form-label">Special Instructions</label>
                                <textarea class="form-control" id="special_instructions<?= $index ?>" name="special_instructions" rows="2" placeholder="Any special requests?"><?= htmlspecialchars($item['special_instructions']) ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <input type="hidden" name="item_index" value="<?= $index ?>">
                            <input type="hidden" name="update_cart" value="1">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Cart</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Header with Navbar and Language Switcher -->
    <header class="position-relative">
        <nav class="navbar navbar-expand-lg">
            <div class="container d-flex justify-content-between align-items-center">
                <a class="navbar-brand d-flex align-items-center" href="index.php">
                    <img src="https://images.unsplash.com/photo-1550547660-d9450f859349?crop=entropy&cs=tinysrgb&fit=crop&fm=jpg&h=40&w=40" alt="Restaurant Logo" width="40" height="40" onerror="this.src='https://via.placeholder.com/40';">
                    <span class="restaurant-name">Restaurant</span>
                </a>
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-primary position-relative me-3" type="button" id="cart-button" aria-label="View Cart" data-bs-toggle="modal" data-bs-target="#cartModal">
                        <i class="bi bi-cart fs-4"></i>
                        <?php if (count($_SESSION['cart']) > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cart-count">
                                <?= count($_SESSION['cart']) ?>
                                <span class="visually-hidden">items in cart</span>
                            </span>
                        <?php endif; ?>
                    </button>
                    <span class="fw-bold fs-5" id="cart-total"><?= number_format($cart_total, 2) ?>€</span>
                </div>
            </div>
        </nav>
        <!-- Language Switcher -->
        <div class="language-switcher">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-primary dropdown-toggle d-flex align-items-center" type="button" id="languageDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="flag-icon flag-icon-de"></span> DE
                </button>
                <ul class="dropdown-menu" aria-labelledby="languageDropdown">
                    <li><a class="dropdown-item language-option" href="#" data-lang="de"><span class="flag-icon flag-icon-de"></span> Deutsch (DE)</a></li>
                    <li><a class="dropdown-item language-option" href="#" data-lang="sq"><span class="flag-icon flag-icon-al"></span> Shqip (SQ)</a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Promo Banner -->
    <section class="promo-banner">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <small class="text-uppercase text-muted">Offer</small>
                    <h1>BASKET MIX</h1>
                    <p class="mt-3">10X Chicken Tenders<br>10X Spicy Wings</p>
                    <h2 class="mt-3">11.99€</h2>
                </div>
                <div class="col-lg-6 d-none d-lg-block">
                    <img src="https://images.unsplash.com/photo-1600891964599-f61ba0e24092?crop=entropy&cs=tinysrgb&fit=crop&fm=jpg&h=400&w=600" alt="Basket Mix" class="img-fluid rounded shadow" onerror="this.src='https://via.placeholder.com/600x400';">
                </div>
            </div>
        </div>
    </section>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= (!isset($_GET['category_id']) || $_GET['category_id'] == 0) ? 'active' : '' ?>" href="index.php" role="tab">All</a>
        </li>
        <?php foreach ($categories as $category): ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= (isset($_GET['category_id']) && $_GET['category_id'] == $category['id']) ? 'active' : '' ?>" href="index.php?category_id=<?= htmlspecialchars($category['id']) ?>" role="tab"><?= htmlspecialchars($category['name']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Main Content -->
    <main class="container my-5">
        <div class="row">
            <!-- Menu Items Section -->
            <div class="col-lg-9">
                <div class="row g-4">
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as $product): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card menu-item h-100 shadow-sm">
                                    <!-- Image Container with Badges -->
                                    <div class="image-container position-relative">
                                        <!-- Product Image -->
                                        <img src="<?= htmlspecialchars($product['image_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($product['name']) ?>" onerror="this.src='https://via.placeholder.com/600x400';">
                                        <!-- New Badge -->
                                        <?php if ($product['is_new']): ?>
                                            <span class="badge badge-new text-white position-absolute">New</span>
                                        <?php endif; ?>
                                        <!-- Offer Badge -->
                                        <?php if ($product['is_offer']): ?>
                                            <span class="badge badge-offer text-dark position-absolute">Offer</span>
                                        <?php endif; ?>
                                        <!-- Allergies Badge -->
                                        <?php if (!empty($product['allergies'])): ?>
                                            <button type="button" class="btn btn-sm btn-danger badge-allergies position-absolute" data-bs-toggle="modal" data-bs-target="#allergiesModal<?= $product['id'] ?>">
                                                <i class="bi bi-exclamation-triangle-fill me-1"></i> Allergies
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title"><?= htmlspecialchars($product['name']) ?></h5>
                                        <p class="card-text"><?= htmlspecialchars($product['description']) ?></p>
                                        <div class="mt-auto d-flex justify-content-between align-items-center">
                                            <strong>
                                                <?php
                                                // Display base price (assuming first size as default)
                                                if (isset($product_sizes[$product['id']]) && count($product_sizes[$product['id']]) > 0) {
                                                    echo number_format($product_sizes[$product['id']][0]['price'], 2) . '€';
                                                } else {
                                                    echo '0.00€';
                                                }
                                                ?>
                                            </strong>
                                            <!-- Button to trigger add to cart modal -->
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addToCartModal<?= $product['id'] ?>">
                                                <i class="bi bi-cart-plus me-1"></i> Add to Cart
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Add to Cart Modal for Each Product -->
                            <div class="modal fade" id="addToCartModal<?= $product['id'] ?>" tabindex="-1" aria-labelledby="addToCartModalLabel<?= $product['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" action="index.php">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="addToCartModalLabel<?= $product['id'] ?>">Add <?= htmlspecialchars($product['name']) ?> to Cart</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-4">
                                                        <!-- Product Image -->
                                                        <img src="<?= htmlspecialchars($product['image_url']) ?>" class="img-fluid rounded shadow" alt="<?= htmlspecialchars($product['name']) ?>" onerror="this.src='https://via.placeholder.com/600x400';">
                                                    </div>
                                                    <div class="col-8">
                                                        <!-- Size Selection -->
                                                        <?php if (isset($product_sizes[$product['id']]) && count($product_sizes[$product['id']]) > 0): ?>
                                                            <div class="mb-3">
                                                                <label for="size<?= $product['id'] ?>" class="form-label">Size</label>
                                                                <select class="form-select" id="size<?= $product['id'] ?>" name="size" required>
                                                                    <option value="">Choose a size</option>
                                                                    <?php foreach ($product_sizes[$product['id']] as $size): ?>
                                                                        <option value="<?= htmlspecialchars($size['size_id']) ?>"><?= htmlspecialchars($size['name']) ?> (+<?= number_format($size['price'], 2) ?>€)</option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        <?php endif; ?>
                                                        <!-- Extras Selection as Checkboxes -->
                                                        <?php if (count($product['extras']) > 0): ?>
                                                            <div class="mb-3">
                                                                <label class="form-label">Extras</label>
                                                                <div>
                                                                    <?php foreach ($product['extras'] as $extra): ?>
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" type="checkbox" id="extra<?= $product['id'] ?>_<?= $extra['id'] ?>" name="extras[]" value="<?= htmlspecialchars($extra['id']) ?>">
                                                                            <label class="form-check-label" for="extra<?= $product['id'] ?>_<?= $extra['id'] ?>">
                                                                                <?= htmlspecialchars($extra['name']) ?> (+<?= number_format($extra['price'], 2) ?>€)
                                                                            </label>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        <!-- Drinks Selection -->
                                                        <?php if (count($drinks) > 0): ?>
                                                            <div class="mb-3">
                                                                <label class="form-label">Drinks</label>
                                                                <select class="form-select" name="drink">
                                                                    <option value="">Choose a drink</option>
                                                                    <?php foreach ($drinks as $drink): ?>
                                                                        <option value="<?= htmlspecialchars($drink['id']) ?>"><?= htmlspecialchars($drink['name']) ?> (+<?= number_format($drink['price'], 2) ?>€)</option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        <?php endif; ?>
                                                        <!-- Quantity Selection -->
                                                        <div class="mb-3">
                                                            <label for="quantity<?= $product['id'] ?>" class="form-label">Quantity</label>
                                                            <input type="number" class="form-control" id="quantity<?= $product['id'] ?>" name="quantity" value="1" min="1" max="99" required>
                                                        </div>
                                                        <!-- Special Instructions -->
                                                        <div class="mb-3">
                                                            <label for="special_instructions<?= $product['id'] ?>" class="form-label">Special Instructions</label>
                                                            <textarea class="form-control" id="special_instructions<?= $product['id'] ?>" name="special_instructions" rows="2" placeholder="Any special requests?"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id']) ?>">
                                                <input type="hidden" name="add_to_cart" value="1">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Add to Cart</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Allergies Modal for Each Product -->
                            <?php if (!empty($product['allergies'])): ?>
                                <div class="modal fade" id="allergiesModal<?= $product['id'] ?>" tabindex="-1" aria-labelledby="allergiesModalLabel<?= $product['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="allergiesModalLabel<?= $product['id'] ?>">Allergies for <?= htmlspecialchars($product['name']) ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <ul class="list-group">
                                                    <?php
                                                    $allergies = array_map('trim', explode(',', $product['allergies']));
                                                    foreach ($allergies as $allergy): ?>
                                                        <li class="list-group-item"><?= htmlspecialchars($allergy) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center">No products available in this category.</p>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Order Summary Section -->
            <div class="col-lg-3">
                <div class="order-summary">
                    <h2 class="order-title">YOUR ORDER</h2>
                    <p class="card-text">
                        <strong>Working hours:</strong> 10:00 - 21:45<br>
                        <strong>Minimum order:</strong> 5.00€
                    </p>
                    <div id="cart-items">
                        <?php if (count($_SESSION['cart']) > 0): ?>
                            <ul class="list-group mb-3">
                                <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6><?= htmlspecialchars($item['name']) ?> (<?= htmlspecialchars($item['size'] ?? 'N/A') ?>) x<?= htmlspecialchars($item['quantity']) ?></h6>
                                            <?php if (!empty($item['extras'])): ?>
                                                <ul>
                                                    <?php foreach ($item['extras'] as $extra): ?>
                                                        <li><?= htmlspecialchars($extra['name']) ?> (+<?= number_format($extra['price'], 2) ?>€)</li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                            <?php if ($item['drink']): ?>
                                                <p>Drink: <?= htmlspecialchars($item['drink']['name']) ?> (+<?= number_format($item['drink']['price'], 2) ?>€)</p>
                                            <?php endif; ?>
                                            <?php if (!empty($item['special_instructions'])): ?>
                                                <p><em>Instructions:</em> <?= htmlspecialchars($item['special_instructions']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong><?= number_format($item['total_price'], 2) ?>€</strong>
                                            <br>
                                            <!-- Remove Button -->
                                            <a href="index.php?remove=<?= $index ?>" class="btn btn-sm btn-danger mt-2"><i class="bi bi-trash"></i> Remove</a>
                                            <!-- Edit Button -->
                                            <button type="button" class="btn btn-sm btn-secondary mt-2" data-bs-toggle="modal" data-bs-target="#editCartModal<?= $index ?>"><i class="bi bi-pencil-square"></i> Edit</button>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <h4>Total: <?= number_format($cart_total, 2) ?>€</h4>
                            <button class="btn btn-success w-100 mt-3" data-bs-toggle="modal" data-bs-target="#checkoutModal">Proceed to Checkout</button>
                        <?php else: ?>
                            <p>Your cart is empty.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Checkout Modal -->
    <div class="modal fade" id="checkoutModal" tabindex="-1" aria-labelledby="checkoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="index.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="checkoutModalLabel">Checkout</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Customer Details -->
                        <div class="mb-3">
                            <label for="customer_name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="customer_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="customer_email" name="customer_email" required>
                        </div>
                        <div class="mb-3">
                            <label for="customer_phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="customer_phone" name="customer_phone" required>
                        </div>
                        <div class="mb-3">
                            <label for="delivery_address" class="form-label">Delivery Address</label>
                            <textarea class="form-control" id="delivery_address" name="delivery_address" rows="2" required></textarea>
                        </div>
                        <!-- Cart Summary -->
                        <h5>Order Summary</h5>
                        <ul class="list-group mb-3">
                            <?php foreach ($_SESSION['cart'] as $item): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <?= htmlspecialchars($item['name']) ?> x<?= htmlspecialchars($item['quantity']) ?>
                                        <?php if ($item['size']): ?>
                                            (<?= htmlspecialchars($item['size']) ?>)
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?= number_format($item['total_price'], 2) ?>€
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <h5>Total: <?= number_format($cart_total, 2) ?>€</h5>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="checkout">Place Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Order Confirmation Modal -->
    <?php if (isset($_GET['order']) && $_GET['order'] === 'success'): ?>
        <div class="modal fade show" tabindex="-1" style="display: block;" aria-modal="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Thank You for Your Order!</h5>
                        <a href="index.php" class="btn-close" aria-label="Close"></a>
                    </div>
                    <div class="modal-body">
                        <p>Your order has been placed successfully. It will be delivered in approximately 40-60 minutes.</p>
                    </div>
                    <div class="modal-footer">
                        <a href="index.php" class="btn btn-primary">Close</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Success/Error Messages -->
    <?php if (isset($_GET['added']) && $_GET['added'] == 1): ?>
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div class="toast align-items-center text-bg-success border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        Product added to cart successfully!
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['removed']) && $_GET['removed'] == 1): ?>
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div class="toast align-items-center text-bg-warning border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        Product removed from cart.
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div class="toast align-items-center text-bg-info border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        Cart updated successfully!
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['order']) && $_GET['order'] === 'success'): ?>
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div class="toast align-items-center text-bg-success border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        Your order has been placed successfully!
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div class="toast align-items-center text-bg-danger border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <?php
                        switch ($_GET['error']) {
                            case 'invalid_product':
                                echo "Invalid product selected.";
                                break;
                            case 'empty_cart':
                                echo "Your cart is empty.";
                                break;
                            case 'invalid_order_details':
                                echo "Please fill in all required order details.";
                                break;
                            case 'order_failed':
                                echo "Failed to place your order. Please try again.";
                                break;
                            case 'invalid_update':
                                echo "Invalid cart update request.";
                                break;
                            default:
                                echo "An unknown error occurred.";
                        }
                        ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Cart Modal -->
    <div class="modal fade" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Your Cart</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (count($_SESSION['cart']) > 0): ?>
                        <ul class="list-group mb-3">
                            <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6><?= htmlspecialchars($item['name']) ?> (<?= htmlspecialchars($item['size'] ?? 'N/A') ?>) x<?= htmlspecialchars($item['quantity']) ?></h6>
                                        <?php if (!empty($item['extras'])): ?>
                                            <ul>
                                                <?php foreach ($item['extras'] as $extra): ?>
                                                    <li><?= htmlspecialchars($extra['name']) ?> (+<?= number_format($extra['price'], 2) ?>€)</li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        <?php if ($item['drink']): ?>
                                            <p>Drink: <?= htmlspecialchars($item['drink']['name']) ?> (+<?= number_format($item['drink']['price'], 2) ?>€)</p>
                                        <?php endif; ?>
                                        <?php if (!empty($item['special_instructions'])): ?>
                                            <p><em>Instructions:</em> <?= htmlspecialchars($item['special_instructions']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong><?= number_format($item['total_price'], 2) ?>€</strong>
                                        <br>
                                        <!-- Remove Button -->
                                        <a href="index.php?remove=<?= $index ?>" class="btn btn-sm btn-danger mt-2"><i class="bi bi-trash"></i> Remove</a>
                                        <!-- Edit Button -->
                                        <button type="button" class="btn btn-sm btn-secondary mt-2" data-bs-toggle="modal" data-bs-target="#editCartModal<?= $index ?>"><i class="bi bi-pencil-square"></i> Edit</button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <h4>Total: <?= number_format($cart_total, 2) ?>€</h4>
                        <button class="btn btn-success w-100 mt-3" data-bs-toggle="modal" data-bs-target="#checkoutModal">Proceed to Checkout</button>
                    <?php else: ?>
                        <p>Your cart is empty.</p>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <footer class="d-flex flex-wrap justify-content-between align-items-center py-3 my-4 border-top">
            <div class="col-md-4 d-flex align-items-center">
                <a href="/" class="mb-3 me-2 mb-md-0 text-muted text-decoration-none lh-1">
                    <svg class="bi" width="30" height="24">
                        <use xlink:href="#bootstrap"></use>
                    </svg>
                </a>
                <span class="text-muted">© 2021 Company, Inc</span>
            </div>

            <ul class="nav col-md-4 justify-content-end list-unstyled d-flex">
                <li class="ms-3"><a class="text-muted" href="#"><svg class="bi" width="24" height="24">
                            <use xlink:href="#twitter"></use>
                        </svg></a></li>
                <li class="ms-3"><a class="text-muted" href="#"><svg class="bi" width="24" height="24">
                            <use xlink:href="#instagram"></use>
                        </svg></a></li>
                <li class="ms-3"><a class="text-muted" href="#"><svg class="bi" width="24" height="24">
                            <use xlink:href="#facebook"></use>
                        </svg></a></li>
            </ul>
        </footer>
    </div>

    <!-- Bootstrap Bundle with Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery (Optional, for additional functionalities) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Font Awesome for Social Icons (Add this if not already included) -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <!-- Custom Scripts -->
    <script>
        // Auto-hide the order confirmation modal after a few seconds
        <?php if (isset($_GET['order']) && $_GET['order'] === 'success'): ?>
            setTimeout(() => {
                $('.modal').modal('hide');
                window.location.href = 'index.php';
            }, 5000);
        <?php endif; ?>

        // Initialize all toasts
        $(document).ready(function() {
            $('.toast').toast('show');
        });
    </script>
</body>

</html>
