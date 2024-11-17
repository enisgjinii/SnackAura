<?php
// Enable error reporting for development (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering and session
ob_start();

// Include necessary files
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Define action and product ID
$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

// Helper Functions
function getCategories($pdo)
{
    try {
        return $pdo->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getExtras($pdo)
{
    try {
        return $pdo->query('SELECT * FROM extras ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getSauces($pdo)
{
    try {
        return $pdo->query('SELECT * FROM sauces ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getTop10Products($pdo, $completed_status_id = 3)
{
    try {
        $query = '
            SELECT p.*, SUM(oi.quantity) AS total_sold
            FROM products p
            JOIN order_items oi ON p.id = oi.product_id
            JOIN orders o ON oi.order_id = o.id
            WHERE o.status_id = ?
            GROUP BY p.id
            ORDER BY total_sold DESC
            LIMIT 10
        ';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$completed_status_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getMixedProducts($pdo, $exclude_product_id = null)
{
    try {
        $query = 'SELECT * FROM products ORDER BY name ASC';
        if ($exclude_product_id) {
            $query .= ' WHERE id != ?';
            $stmt = $pdo->prepare($query);
            $stmt->execute([$exclude_product_id]);
        } else {
            $stmt = $pdo->query($query);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function sanitizeInput($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Fetch Categories, Extras, Sauces
$categories = getCategories($pdo);
$extras = getExtras($pdo);
$sauces = getSauces($pdo);

// Handle Actions
switch ($action) {
    case 'add':
    case 'edit':
        // Initialize variables
        $is_edit = ($action === 'edit');
        if ($is_edit) {
            // Fetch existing product data
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                $message = '<div class="alert alert-danger">Product not found.</div>';
                break;
            }

            // Fetch associated extras, sauces, mixes
            $stmt_extras = $pdo->prepare('SELECT extra_id FROM product_extras WHERE product_id = ?');
            $stmt_extras->execute([$id]);
            $selected_extras = $stmt_extras->fetchAll(PDO::FETCH_COLUMN);

            $stmt_sauces = $pdo->prepare('SELECT sauce_id FROM product_sauces WHERE product_id = ?');
            $stmt_sauces->execute([$id]);
            $selected_sauces = $stmt_sauces->fetchAll(PDO::FETCH_COLUMN);

            $stmt_mixes = $pdo->prepare('SELECT mixed_product_id FROM product_mixes WHERE main_product_id = ?');
            $stmt_mixes->execute([$id]);
            $selected_mixes = $stmt_mixes->fetchAll(PDO::FETCH_COLUMN);
        } else {
            // Initialize empty selections for adding new product
            $selected_extras = [];
            $selected_sauces = [];
            $selected_mixes = [];
            $product = [];
        }

        // Handle Form Submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Sanitize and validate inputs
            $product_code = sanitizeInput($_POST['product_code'] ?? '');
            $name = sanitizeInput($_POST['name'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $description = sanitizeInput($_POST['description'] ?? '');
            $allergies = sanitizeInput($_POST['allergies'] ?? '');
            $category_id = (int)($_POST['category_id'] ?? 0);
            $is_new = isset($_POST['is_new']) ? 1 : 0;
            $is_offer = isset($_POST['is_offer']) ? 1 : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $image_source = $_POST['image_source'] ?? 'upload';
            $selected_extras = $_POST['extras'] ?? [];
            $selected_sauces = $_POST['sauces'] ?? [];
            $selected_mixes = $_POST['mixes'] ?? [];

            // Initialize image variables
            $image_url = '';
            $upload_success = false;
            $url_success = false;

            // Handle Image Source
            if ($image_source === 'upload') {
                if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp_path = $_FILES['image_file']['tmp_name'];
                    $file_name = basename($_FILES['image_file']['name']);
                    $file_size = $_FILES['image_file']['size'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    if (in_array($file_ext, $allowed_extensions)) {
                        if ($file_size <= 2 * 1024 * 1024) { // 2MB limit
                            $new_file_name = uniqid('product_', true) . '.' . $file_ext;
                            $upload_dir = 'uploads/';
                            if (!is_dir($upload_dir)) {
                                mkdir($upload_dir, 0755, true);
                            }
                            $dest_path = $upload_dir . $new_file_name;
                            if (move_uploaded_file($file_tmp_path, $dest_path)) {
                                $image_url = $dest_path;
                                $upload_success = true;
                            } else {
                                $message = '<div class="alert alert-danger">Error uploading image.</div>';
                            }
                        } else {
                            $message = '<div class="alert alert-danger">Image size cannot exceed 2MB.</div>';
                        }
                    } else {
                        $message = '<div class="alert alert-danger">Only JPG, JPEG, PNG, and GIF files are allowed.</div>';
                    }
                } elseif ($is_edit) {
                    // Retain existing image if no new upload
                    $image_url = $product['image_url'];
                } else {
                    $message = '<div class="alert alert-danger">Please upload a product image.</div>';
                }
            } elseif ($image_source === 'url') {
                $image_url_input = filter_var(trim($_POST['image_url'] ?? ''), FILTER_SANITIZE_URL);
                if (filter_var($image_url_input, FILTER_VALIDATE_URL)) {
                    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $headers = @get_headers($image_url_input, 1);
                    if ($headers && isset($headers['Content-Type'])) {
                        $content_type = is_array($headers['Content-Type']) ? end($headers['Content-Type']) : $headers['Content-Type'];
                        if (in_array(strtolower($content_type), $allowed_mime_types)) {
                            $image_url = $image_url_input;
                            $url_success = true;
                        } else {
                            $message = '<div class="alert alert-danger">Unsupported image type. Please provide a JPG, PNG, or GIF image URL.</div>';
                        }
                    } else {
                        $message = '<div class="alert alert-danger">Unable to verify image URL.</div>';
                    }
                } elseif ($is_edit) {
                    // Retain existing image if no new URL provided
                    $image_url = $product['image_url'];
                } else {
                    $message = '<div class="alert alert-danger">Please enter a valid image URL.</div>';
                }
            }

            // Proceed if image handling was successful or retained
            if (($image_source === 'upload' && $upload_success) || ($image_source === 'url' && $url_success) || $is_edit) {
                // Validate required fields
                if (empty($product_code) || empty($name) || $price <= 0 || $category_id === 0) {
                    $message = '<div class="alert alert-danger">Product code, name, price, and category are required.</div>';
                } else {
                    try {
                        // Check for unique product code
                        $sql_check = 'SELECT COUNT(*) FROM products WHERE product_code = ?' . ($is_edit ? ' AND id != ?' : '');
                        $stmt_check = $pdo->prepare($sql_check);
                        $params_check = $is_edit ? [$product_code, $id] : [$product_code];
                        $stmt_check->execute($params_check);
                        if ($stmt_check->fetchColumn() > 0) {
                            $message = '<div class="alert alert-danger">Product code already exists. Please choose another.</div>';
                        } else {
                            // Insert or Update product
                            if ($is_edit) {
                                $sql = 'UPDATE products SET product_code = ?, category_id = ?, name = ?, price = ?, description = ?, allergies = ?, image_url = ?, is_new = ?, is_offer = ?, is_active = ? WHERE id = ?';
                                $params = [$product_code, $category_id, $name, $price, $description, $allergies, $image_url, $is_new, $is_offer, $is_active, $id];
                            } else {
                                $sql = 'INSERT INTO products (product_code, category_id, name, price, description, allergies, image_url, is_new, is_offer, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                                $params = [$product_code, $category_id, $name, $price, $description, $allergies, $image_url, $is_new, $is_offer, $is_active];
                            }

                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($params);

                            // Get product ID
                            $product_id = $is_edit ? $id : $pdo->lastInsertId();

                            // Handle image replacement (delete old image if uploaded new one)
                            if ($is_edit && $image_source === 'upload' && $upload_success && strpos($product['image_url'], 'uploads/') === 0) {
                                if (file_exists($product['image_url'])) {
                                    unlink($product['image_url']);
                                }
                            }

                            // Update Extras
                            $pdo->prepare('DELETE FROM product_extras WHERE product_id = ?')->execute([$product_id]);
                            if (!empty($selected_extras)) {
                                $stmt_extras = $pdo->prepare('INSERT INTO product_extras (product_id, extra_id) VALUES (?, ?)');
                                foreach ($selected_extras as $extra_id) {
                                    $stmt_extras->execute([$product_id, (int)$extra_id]);
                                }
                            }

                            // Update Sauces
                            $pdo->prepare('DELETE FROM product_sauces WHERE product_id = ?')->execute([$product_id]);
                            if (!empty($selected_sauces)) {
                                $stmt_sauces = $pdo->prepare('INSERT INTO product_sauces (product_id, sauce_id) VALUES (?, ?)');
                                foreach ($selected_sauces as $sauce_id) {
                                    $stmt_sauces->execute([$product_id, (int)$sauce_id]);
                                }
                            }

                            // Update Mixes
                            $pdo->prepare('DELETE FROM product_mixes WHERE main_product_id = ?')->execute([$product_id]);
                            if (!empty($selected_mixes)) {
                                $stmt_mixes = $pdo->prepare('INSERT INTO product_mixes (main_product_id, mixed_product_id) VALUES (?, ?)');
                                foreach ($selected_mixes as $mixed_product_id) {
                                    $stmt_mixes->execute([$product_id, (int)$mixed_product_id]);
                                }
                            }

                            // Redirect to product list with success message
                            $_SESSION['message'] = '<div class="alert alert-success">Product ' . ($is_edit ? 'updated' : 'added') . ' successfully.</div>';
                            header('Location: products.php');
                            exit();
                        }
                    } catch (PDOException $e) {
                        $message = '<div class="alert alert-danger">Database error: ' . sanitizeInput($e->getMessage()) . '</div>';
                    }
                }
            }
        }

        // Prepare Mix Options excluding current product if editing
        $mixes = getMixedProducts($pdo, $is_edit ? $id : null);
?>
        <h2 class="mb-4"><?= $action === 'add' ? 'Add Product' : 'Edit Product' ?></h2>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <form method="POST" action="products.php?action=<?= $action ?><?= $is_edit ? '&id=' . $id : '' ?>" enctype="multipart/form-data">
            <div class="row g-3">
                <!-- Product Code -->
                <div class="col-md-6">
                    <label for="product_code" class="form-label">Product Code <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text" id="product_code-addon" data-bs-toggle="tooltip" title="Enter a unique product code">
                            <i class="fas fa-id-badge"></i>
                        </span>
                        <input type="text" class="form-control" id="product_code" name="product_code" required value="<?= sanitizeInput($is_edit ? $product['product_code'] : ($_POST['product_code'] ?? '')) ?>" aria-describedby="product_code-addon">
                    </div>
                </div>
                <!-- Product Name -->
                <div class="col-md-6">
                    <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text" id="name-addon" data-bs-toggle="tooltip" title="Enter the product name">
                            <i class="fas fa-heading"></i>
                        </span>
                        <input type="text" class="form-control" id="name" name="name" required value="<?= sanitizeInput($is_edit ? $product['name'] : ($_POST['name'] ?? '')) ?>" aria-describedby="name-addon">
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-3">
                <!-- Category -->
                <div class="col-md-6">
                    <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text" id="category-addon" data-bs-toggle="tooltip" title="Select a category for the product">
                            <i class="fas fa-list"></i>
                        </span>
                        <select class="form-select" id="category_id" name="category_id" required aria-describedby="category-addon">
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>" <?= (
                                                                            ($is_edit && $product['category_id'] == $category['id']) ||
                                                                            (isset($_POST['category_id']) && $_POST['category_id'] == $category['id'])
                                                                        ) ? 'selected' : '' ?>>
                                    <?= sanitizeInput($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <!-- Price -->
                <div class="col-md-6">
                    <label for="price" class="form-label">Price ($) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text" id="price-addon" data-bs-toggle="tooltip" title="Enter the product price">
                            <i class="fas fa-dollar-sign"></i>
                        </span>
                        <input type="number" step="0.01" class="form-control" id="price" name="price" required value="<?= sanitizeInput($is_edit ? $product['price'] : ($_POST['price'] ?? '')) ?>" aria-describedby="price-addon">
                    </div>
                </div>
            </div>

            <!-- Image Source Selection -->
            <div class="row g-3 mt-3">
                <div class="col-md-12">
                    <label class="form-label">Image Source <span class="text-danger">*</span></label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="image_source" id="image_source_upload" value="upload" <?= (
                                                                                                                                        ($is_edit && !empty($product['image_url']) && strpos($product['image_url'], 'uploads/') === 0) ||
                                                                                                                                        (!isset($_POST['image_source']) || $_POST['image_source'] === 'upload')
                                                                                                                                    ) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="image_source_upload">Upload Image</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="image_source" id="image_source_url" value="url" <?= (
                                                                                                                                ($is_edit && !empty($product['image_url']) && strpos($product['image_url'], 'uploads/') !== 0) ||
                                                                                                                                (isset($_POST['image_source']) && $_POST['image_source'] === 'url')
                                                                                                                            ) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="image_source_url">Image URL</label>
                    </div>
                </div>
            </div>

            <!-- Image Upload Field -->
            <div class="row g-3 mt-3" id="image_upload_field" style="display: <?= (
                                                                                    ($is_edit && !empty($product['image_url']) && strpos($product['image_url'], 'uploads/') === 0) ||
                                                                                    (!isset($_POST['image_source']) || $_POST['image_source'] === 'upload')
                                                                                ) ? 'block' : 'none' ?>;">
                <div class="col-md-6">
                    <label for="image_file" class="form-label">Upload Image</label>
                    <div class="input-group">
                        <span class="input-group-text" id="image_file-addon" data-bs-toggle="tooltip" title="Upload a product image">
                            <i class="fas fa-upload"></i>
                        </span>
                        <input type="file" class="form-control" id="image_file" name="image_file" accept="image/*" aria-describedby="image_file-addon">
                    </div>
                    <?php if ($is_edit && !empty($product['image_url']) && strpos($product['image_url'], 'uploads/') === 0): ?>
                        <div class="mt-2">
                            <img src="<?= sanitizeInput($product['image_url']) ?>" alt="Product Image" style="max-width: 200px;">
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Image URL Field -->
            <div class="row g-3 mt-3" id="image_url_field" style="display: <?= (
                                                                                ($is_edit && !empty($product['image_url']) && strpos($product['image_url'], 'uploads/') !== 0) ||
                                                                                (isset($_POST['image_source']) && $_POST['image_source'] === 'url')
                                                                            ) ? 'block' : 'none' ?>;">
                <div class="col-md-6">
                    <label for="image_url" class="form-label">Image URL</label>
                    <div class="input-group">
                        <span class="input-group-text" id="image_url-addon" data-bs-toggle="tooltip" title="Enter the product image URL">
                            <i class="fas fa-image"></i>
                        </span>
                        <input type="url" class="form-control" id="image_url" name="image_url" placeholder="https://example.com/image.jpg" value="<?= sanitizeInput($is_edit ? $product['image_url'] : ($_POST['image_url'] ?? '')) ?>" aria-describedby="image_url-addon">
                    </div>
                    <?php if ($is_edit && !empty($product['image_url']) && strpos($product['image_url'], 'uploads/') !== 0): ?>
                        <div class="mt-2">
                            <img src="<?= sanitizeInput($product['image_url']) ?>" alt="Product Image" style="max-width: 200px;">
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-3 mt-3">
                <!-- Allergies -->
                <div class="col-md-6">
                    <label for="allergies" class="form-label">Allergies</label>
                    <div class="input-group">
                        <span class="input-group-text" id="allergies-addon" data-bs-toggle="tooltip" title="List product allergies">
                            <i class="fas fa-exclamation-triangle"></i>
                        </span>
                        <input type="text" class="form-control" id="allergies" name="allergies" placeholder="e.g., Nuts, Milk" value="<?= sanitizeInput($is_edit ? $product['allergies'] : ($_POST['allergies'] ?? '')) ?>" aria-describedby="allergies-addon">
                    </div>
                    <div class="form-text">Separate allergies with commas.</div>
                </div>
                <!-- Description -->
                <div class="col-md-6">
                    <label for="description" class="form-label">Description</label>
                    <div class="input-group">
                        <span class="input-group-text" id="description-addon" data-bs-toggle="tooltip" title="Describe the product">
                            <i class="fas fa-align-left"></i>
                        </span>
                        <textarea class="form-control" id="description" name="description" aria-describedby="description-addon"><?= sanitizeInput($is_edit ? $product['description'] : ($_POST['description'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-3">
                <!-- Extras -->
                <div class="col-md-4">
                    <label for="extras" class="form-label">Extras</label>
                    <select class="form-select" id="extras" name="extras[]" multiple>
                        <?php foreach ($extras as $extra): ?>
                            <option value="<?= $extra['id'] ?>" <?= (
                                                                    (isset($_POST['extras']) && in_array($extra['id'], $_POST['extras'])) ||
                                                                    ($is_edit && in_array($extra['id'], $selected_extras))
                                                                ) ? 'selected' : '' ?>>
                                <?= sanitizeInput($extra['name']) ?> - $<?= number_format($extra['price'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Select extras for this product.</div>
                </div>
                <!-- Sauces -->
                <div class="col-md-4">
                    <label for="sauces" class="form-label">Sauces</label>
                    <select class="form-select" id="sauces" name="sauces[]" multiple>
                        <?php foreach ($sauces as $sauce): ?>
                            <option value="<?= $sauce['id'] ?>" <?= (
                                                                    (isset($_POST['sauces']) && in_array($sauce['id'], $_POST['sauces'])) ||
                                                                    ($is_edit && in_array($sauce['id'], $selected_sauces))
                                                                ) ? 'selected' : '' ?>>
                                <?= sanitizeInput($sauce['name']) ?> - $<?= number_format($sauce['price'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Select sauces for this product.</div>
                </div>
                <!-- Mix Options -->
                <div class="col-md-4">
                    <label for="mixes" class="form-label">Mix Options</label>
                    <select class="form-select" id="mixes" name="mixes[]" multiple>
                        <?php foreach ($mixes as $mix_product): ?>
                            <option value="<?= $mix_product['id'] ?>" <?= (
                                                                            (isset($_POST['mixes']) && in_array($mix_product['id'], $_POST['mixes'])) ||
                                                                            ($is_edit && in_array($mix_product['id'], $selected_mixes))
                                                                        ) ? 'selected' : '' ?>>
                                <?= sanitizeInput($mix_product['name']) ?> - $<?= number_format($mix_product['price'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Select products to mix with this product.</div>
                </div>
            </div>

            <div class="row g-3 mt-3">
                <!-- New Product Checkbox -->
                <div class="col-md-4">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="is_new" name="is_new" <?= (
                                                                                                        ($is_edit && $product['is_new']) ||
                                                                                                        (isset($_POST['is_new']) && $_POST['is_new'])
                                                                                                    ) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_new" data-bs-toggle="tooltip" title="Mark this product as new">
                            <i class="fas fa-star"></i> New Product
                        </label>
                    </div>
                </div>
                <!-- On Offer Checkbox -->
                <div class="col-md-4">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="is_offer" name="is_offer" <?= (
                                                                                                            ($is_edit && $product['is_offer']) ||
                                                                                                            (isset($_POST['is_offer']) && $_POST['is_offer'])
                                                                                                        ) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_offer" data-bs-toggle="tooltip" title="Mark this product as on offer">
                            <i class="fas fa-tags"></i> On Offer
                        </label>
                    </div>
                </div>
                <!-- Active Status Checkbox -->
                <div class="col-md-4">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?= (
                                                                                                            ($is_edit && $product['is_active']) ||
                                                                                                            (!$is_edit && !isset($_POST['is_active'])) || // Default to active on add
                                                                                                            (isset($_POST['is_active']) && $_POST['is_active'])
                                                                                                        ) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active" data-bs-toggle="tooltip" title="Mark this product as active">
                            <i class="fas fa-toggle-on"></i> Active
                        </label>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-4">
                <button type="submit" class="btn btn-success me-2" data-bs-toggle="tooltip" title="<?= $action === 'add' ? 'Add new product' : 'Update product' ?>">
                    <i class="fas fa-save"></i> <?= $action === 'add' ? 'Add' : 'Update' ?> Product
                </button>
                <a href="products.php" class="btn btn-secondary" data-bs-toggle="tooltip" title="Cancel and return to product list">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>

        <!-- Success and Error Messages via Session -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="mt-3">
                <?= $_SESSION['message'] ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <!-- Scripts -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
        <script>
            $(document).ready(function() {
                // Initialize Select2 for multiple selects
                $('#extras, #sauces, #mixes').select2({
                    placeholder: "Select options",
                    allowClear: true
                });

                // Handle image source toggle
                $('input[name="image_source"]').change(function() {
                    if ($(this).val() === 'upload') {
                        $('#image_upload_field').show();
                        $('#image_url_field').hide();
                        $('#image_url').prop('required', false).prop('disabled', true);
                        $('#image_file').prop('required', true).prop('disabled', false);
                    } else {
                        $('#image_upload_field').hide();
                        $('#image_url_field').show();
                        $('#image_file').prop('required', false).prop('disabled', true);
                        $('#image_url').prop('required', true).prop('disabled', false);
                    }
                });

                // Trigger change event on page load to set the correct fields
                $('input[name="image_source"]:checked').trigger('change');

                // Initialize tooltips
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            });
        </script>
    <?php
        break;

    case 'delete':
        if ($id > 0) {
            try {
                // Begin transaction
                $pdo->beginTransaction();

                // Fetch image URL
                $stmt_image = $pdo->prepare('SELECT image_url FROM products WHERE id = ?');
                $stmt_image->execute([$id]);
                $image_url = $stmt_image->fetchColumn();

                // Delete associated records
                $pdo->prepare('DELETE FROM product_extras WHERE product_id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM product_sauces WHERE product_id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM product_mixes WHERE main_product_id = ?')->execute([$id]);

                // Delete product
                $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);

                // Delete image file if stored locally
                if ($image_url && strpos($image_url, 'uploads/') === 0 && file_exists($image_url)) {
                    unlink($image_url);
                }

                // Commit transaction and redirect
                $pdo->commit();
                $_SESSION['message'] = '<div class="alert alert-success">Product deleted successfully.</div>';
                header('Location: products.php');
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = '<div class="alert alert-danger">Error deleting product: ' . sanitizeInput($e->getMessage()) . '</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Invalid product ID.</div>';
        }
    ?>
        <h2 class="mb-4">Delete Product</h2>
        <?= $message ?>
        <a href="products.php" class="btn btn-secondary">Return to Products</a>
    <?php
        break;

    case 'top10':
        $topProducts = getTop10Products($pdo);
    ?>
        <h2 class="mb-4">Top 10 Best-Selling Products</h2>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>

        <?php if (!empty($topProducts)): ?>
            <!-- Chart -->
            <canvas id="topProductsChart" width="400" height="200" class="mb-4"></canvas>

            <!-- Top 10 Table -->
            <div class="table-responsive">
                <table id="top10Table" class="table table-striped table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Product Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Total Sold</th>
                            <th>Price ($)</th>
                            <th>Image</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProducts as $product): ?>
                            <tr>
                                <td><?= sanitizeInput($product['id']) ?></td>
                                <td><?= sanitizeInput($product['product_code']) ?></td>
                                <td><?= sanitizeInput($product['name']) ?></td>
                                <td>
                                    <?php
                                    foreach ($categories as $category) {
                                        if ($category['id'] == $product['category_id']) {
                                            echo sanitizeInput($category['name']);
                                            break;
                                        }
                                    }
                                    ?>
                                </td>
                                <td><?= sanitizeInput($product['total_sold']) ?></td>
                                <td><?= number_format($product['price'], 2) ?></td>
                                <td>
                                    <?php if (!empty($product['image_url'])): ?>
                                        <a href="<?= sanitizeInput($product['image_url']) ?>" target="_blank" data-bs-toggle="tooltip" title="View Image">
                                            <i class="fas fa-image text-primary"></i>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Chart.js -->
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                $(document).ready(function() {
                    // Initialize DataTable
                    $('#top10Table').DataTable({
                        responsive: true,
                        order: [
                            [4, 'desc']
                        ],
                        language: {
                            "emptyTable": "No data available",
                            "info": "Showing _START_ to _END_ of _TOTAL_ products",
                            "infoEmpty": "Showing 0 to 0 of 0 products",
                            "lengthMenu": "Show _MENU_ products",
                            "paginate": {
                                "first": "First",
                                "last": "Last",
                                "next": "Next",
                                "previous": "Previous"
                            },
                            "search": "Search:"
                        }
                    });

                    // Prepare data for Chart.js
                    var labels = [
                        <?php foreach ($topProducts as $product): ?> '<?= addslashes(sanitizeInput($product['name'])) ?>',
                        <?php endforeach; ?>
                    ];
                    var data = [
                        <?php foreach ($topProducts as $product): ?>
                            <?= sanitizeInput($product['total_sold']) ?>,
                        <?php endforeach; ?>
                    ];

                    // Create Chart
                    var ctx = document.getElementById('topProductsChart').getContext('2d');
                    var topProductsChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Total Sold',
                                data: data,
                                backgroundColor: 'rgba(75, 192, 192, 0.6)',
                                borderColor: 'rgba(75, 192, 192, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    precision: 0
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    enabled: true
                                }
                            }
                        }
                    });

                    // Initialize tooltips
                    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                });
            </script>
        <?php else: ?>
            <div class="alert alert-info">No sales data available.</div>
            <a href="products.php" class="btn btn-secondary">Return to Products</a>
        <?php endif; ?>

        <a href="products.php" class="btn btn-secondary mt-3">Return to Products</a>
    <?php
        break;

    case 'view':
    default:
        try {
            $query = '
                SELECT p.*, c.name AS category_name
                FROM products p
                JOIN categories c ON p.category_id = c.id
                ORDER BY p.created_at DESC
            ';
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $products = [];
            $message = '<div class="alert alert-danger">Error fetching products: ' . sanitizeInput($e->getMessage()) . '</div>';
        }
    ?>
        <h2 class="mb-4">Manage Products</h2>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="mb-3">
            <a href="products.php?action=add" class="btn btn-success me-2" data-bs-toggle="tooltip" title="Add a new product">
                <i class="fas fa-plus"></i> Add Product
            </a>
            <a href="products.php?action=top10" class="btn btn-primary" data-bs-toggle="tooltip" title="View Top 10 Best-Selling Products">
                <i class="fas fa-chart-line"></i> Top 10 Products
            </a>
        </div>

        <!-- Products Table -->
        <div class="table-responsive">
            <table id="productsTable" class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Product Code</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Extras</th>
                        <th>Sauces</th>
                        <th>Mix Options</th>
                        <th>Allergies</th>
                        <th>Description</th>
                        <th>Price ($)</th>
                        <th>Image</th>
                        <th>New Product</th>
                        <th>Offer</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?= sanitizeInput($product['id']) ?></td>
                                <td><?= sanitizeInput($product['product_code']) ?></td>
                                <td><?= sanitizeInput($product['name']) ?></td>
                                <td><?= sanitizeInput($product['category_name']) ?></td>
                                <td>
                                    <?php
                                    try {
                                        $stmt_extras = $pdo->prepare('SELECT e.name FROM extras e JOIN product_extras pe ON e.id = pe.extra_id WHERE pe.product_id = ?');
                                        $stmt_extras->execute([$product['id']]);
                                        $product_extras = $stmt_extras->fetchAll(PDO::FETCH_COLUMN);
                                        echo !empty($product_extras) ? sanitizeInput(implode(', ', $product_extras)) : '-';
                                    } catch (PDOException $e) {
                                        echo '<span class="text-danger">Error</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    try {
                                        $stmt_sauces = $pdo->prepare('SELECT s.name FROM sauces s JOIN product_sauces ps ON s.id = ps.sauce_id WHERE ps.product_id = ?');
                                        $stmt_sauces->execute([$product['id']]);
                                        $product_sauces = $stmt_sauces->fetchAll(PDO::FETCH_COLUMN);
                                        echo !empty($product_sauces) ? sanitizeInput(implode(', ', $product_sauces)) : '-';
                                    } catch (PDOException $e) {
                                        echo '<span class="text-danger">Error</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    try {
                                        $stmt_mixes = $pdo->prepare('SELECT p2.name FROM product_mixes pm JOIN products p2 ON pm.mixed_product_id = p2.id WHERE pm.main_product_id = ?');
                                        $stmt_mixes->execute([$product['id']]);
                                        $mixed_products = $stmt_mixes->fetchAll(PDO::FETCH_COLUMN);
                                        echo !empty($mixed_products) ? sanitizeInput(implode(', ', $mixed_products)) : '-';
                                    } catch (PDOException $e) {
                                        echo '<span class="text-danger">Error</span>';
                                    }
                                    ?>
                                </td>
                                <td><?= sanitizeInput($product['allergies']) ?: '-' ?></td>
                                <td><?= sanitizeInput($product['description']) ?: '-' ?></td>
                                <td><?= number_format($product['price'], 2) ?></td>
                                <td>
                                    <?php if (!empty($product['image_url'])): ?>
                                        <img src="<?= sanitizeInput($product['image_url']) ?>" alt="Product Image" style="max-width: 100px;">
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $product['is_new'] ? '<span class="badge bg-success" data-bs-toggle="tooltip" title="New Product"><i class="fas fa-star"></i></span>' : '-' ?>
                                </td>
                                <td>
                                    <?= $product['is_offer'] ? '<span class="badge bg-warning text-dark" data-bs-toggle="tooltip" title="On Offer"><i class="fas fa-tags"></i></span>' : '-' ?>
                                </td>
                                <td>
                                    <?= $product['is_active']
                                        ? '<span class="badge bg-primary" data-bs-toggle="tooltip" title="Active"><i class="fas fa-check-circle"></i></span>'
                                        : '<span class="badge bg-secondary" data-bs-toggle="tooltip" title="Inactive"><i class="fas fa-times-circle"></i></span>'
                                    ?>
                                </td>
                                <td><?= sanitizeInput($product['created_at']) ?></td>
                                <td>
                                    <a href="products.php?action=edit&id=<?= $product['id'] ?>" class="btn btn-sm btn-warning me-1" data-bs-toggle="tooltip" title="Edit Product">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-sm btn-danger" onclick="showDeleteModal(<?= $product['id'] ?>)" data-bs-toggle="tooltip" title="Delete Product">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form method="GET" action="products.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteProductId">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" data-bs-toggle="tooltip" title="Close"></button>
                        </div>
                        <div class="modal-body">
                            Are you sure you want to delete this product? This action cannot be undone.
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-danger" data-bs-toggle="tooltip" title="Delete">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-bs-toggle="tooltip" title="Cancel">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Scripts -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
        <script>
            $(document).ready(function() {
                // Initialize DataTable for products
                $('#productsTable').DataTable({
                    responsive: true,
                    order: [
                        [0, 'desc']
                    ],
                    language: {
                        "emptyTable": "No products available",
                        "info": "Showing _START_ to _END_ of _TOTAL_ products",
                        "infoEmpty": "Showing 0 to 0 of 0 products",
                        "lengthMenu": "Show _MENU_ products",
                        "paginate": {
                            "first": "First",
                            "last": "Last",
                            "next": "Next",
                            "previous": "Previous"
                        },
                        "search": "Filter records:"
                    }
                });

                // Initialize Select2 for multiple selects (if any)
                $('#extras, #sauces, #mixes').select2({
                    placeholder: "Select options",
                    allowClear: true
                });

                // Handle image source toggle
                $('input[name="image_source"]').change(function() {
                    if ($(this).val() === 'upload') {
                        $('#image_upload_field').show();
                        $('#image_url_field').hide();
                        $('#image_url').prop('required', false).prop('disabled', true);
                        $('#image_file').prop('required', true).prop('disabled', false);
                    } else {
                        $('#image_upload_field').hide();
                        $('#image_url_field').show();
                        $('#image_file').prop('required', false).prop('disabled', true);
                        $('#image_url').prop('required', true).prop('disabled', false);
                    }
                });

                // Trigger change event on page load to set the correct fields
                $('input[name="image_source"]:checked').trigger('change');

                // Initialize tooltips
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            });

            // Function to show delete modal with product ID
            function showDeleteModal(id) {
                $('#deleteProductId').val(id);
                var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                deleteModal.show();
            }
        </script>
<?php
        break;
}

// Include Footer
require_once 'includes/footer.php';
?>