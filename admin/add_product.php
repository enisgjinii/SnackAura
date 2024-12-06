<?php
// add_product.php

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Include necessary files
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// Fetch Categories, Extras, Sauces, and Mixed Products
$categories = fetchAll($pdo, 'SELECT * FROM categories ORDER BY name ASC');
$extras = fetchAll($pdo, 'SELECT * FROM extras ORDER BY name ASC');
$sauces = fetchAll($pdo, 'SELECT * FROM sauces ORDER BY name ASC');
$mixes = fetchAll($pdo, 'SELECT * FROM products ORDER BY name ASC');

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $product_code = sanitizeInput($_POST['product_code'] ?? '');
    $name = sanitizeInput($_POST['name'] ?? '');
    $price = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
    $description = sanitizeInput($_POST['description'] ?? '');
    $allergies = sanitizeInput($_POST['allergies'] ?? '');
    $category_id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
    $is_new = isset($_POST['is_new']) ? 1 : 0;
    $is_offer = isset($_POST['is_offer']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $image_source = $_POST['image_source'] ?? 'upload';
    $selected_extras = array_map('intval', $_POST['extras'] ?? []);
    $selected_sauces = array_map('intval', $_POST['sauces'] ?? []);
    $selected_mixes = array_map('intval', $_POST['mixes'] ?? []);
    $image_url = '';

    // Determine if category is Pizza
    $selected_category = fetchAll($pdo, 'SELECT name FROM categories WHERE id = ?', [$category_id]);
    $is_pizza = false;
    if ($selected_category) {
        $is_pizza = strtolower($selected_category[0]['name']) === 'pizza';
    }

    // Handle sizes and prices if category is Pizza
    $sizes = [];
    $prices = [];
    if ($is_pizza) {
        $sizes = $_POST['sizes'] ?? [];
        $prices = $_POST['prices'] ?? [];
        // Validate that sizes and prices arrays are of the same length
        if (count($sizes) !== count($prices)) {
            $message = '<div class="alert alert-danger">Sizes and prices do not match.</div>';
        }
        // Further validation can be added here (e.g., non-empty, valid numbers)
    }

    // Validate required fields
    if (empty($product_code) || empty($name) || $price <= 0 || $category_id === 0) {
        $message = '<div class="alert alert-danger">Product code, name, price, and category are required.</div>';
    } else {
        // Check for unique product code
        $sql_check = 'SELECT COUNT(*) FROM products WHERE product_code = ?';
        $params_check = [$product_code];
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute($params_check);
        if ($stmt_check->fetchColumn() > 0) {
            $message = '<div class="alert alert-danger">Product code already exists.</div>';
        } else {
            // Handle image based on source
            if ($image_source === 'upload') {
                if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $upload = handleImageUpload($_FILES['image_file'], false);
                    if ($upload['success']) {
                        $image_url = $upload['url'];
                    } else {
                        $message = '<div class="alert alert-danger">' . $upload['error'] . '</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger">Please upload a product image.</div>';
                }
            } elseif ($image_source === 'url') {
                $validate = validateImageUrl($_POST['image_url'] ?? '');
                if ($validate['valid']) {
                    $image_url = $validate['url'];
                } else {
                    $message = '<div class="alert alert-danger">' . $validate['error'] . '</div>';
                }
            }

            // If image is handled successfully
            if (
                ($image_source === 'upload' && isset($upload) && $upload['success']) ||
                ($image_source === 'url' && isset($validate) && $validate['valid'])
            ) {
                // Proceed with database operations
                try {
                    $pdo->beginTransaction();

                    // Insert new product
                    $sql = 'INSERT INTO products 
                            (product_code, category_id, name, price, description, 
                            allergies, image_url, is_new, is_offer, is_active) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                    $params = [
                        $product_code,
                        $category_id,
                        $name,
                        $price,
                        $description,
                        $allergies,
                        $image_url,
                        $is_new,
                        $is_offer,
                        $is_active
                    ];
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $product_id = $pdo->lastInsertId();

                    // Manage product_extras
                    if (!empty($selected_extras)) {
                        $stmt_extras = $pdo->prepare('INSERT INTO product_extras (product_id, extra_id) VALUES (?, ?)');
                        foreach ($selected_extras as $extra_id) {
                            $stmt_extras->execute([$product_id, $extra_id]);
                        }
                    }

                    // Manage product_sauces
                    if (!empty($selected_sauces)) {
                        $stmt_sauces = $pdo->prepare('INSERT INTO product_sauces (product_id, sauce_id) VALUES (?, ?)');
                        foreach ($selected_sauces as $sauce_id) {
                            $stmt_sauces->execute([$product_id, $sauce_id]);
                        }
                    }

                    // Manage product_mixes
                    if (!empty($selected_mixes)) {
                        $stmt_mixes = $pdo->prepare('INSERT INTO product_mixes (main_product_id, mixed_product_id) VALUES (?, ?)');
                        foreach ($selected_mixes as $mixed_product_id) {
                            $stmt_mixes->execute([$product_id, $mixed_product_id]);
                        }
                    }

                    // Handle sizes and prices for Pizza category
                    if ($is_pizza) {
                        if (!empty($sizes) && !empty($prices)) {
                            $stmt_size = $pdo->prepare('INSERT INTO product_sizes (product_id, size, price) VALUES (?, ?, ?)');
                            foreach ($sizes as $index => $size) {
                                $size = sanitizeInput($size);
                                $price_size = filter_var($prices[$index], FILTER_VALIDATE_FLOAT);
                                if (!empty($size) && $price_size !== false && $price_size >= 0) {
                                    $stmt_size->execute([$product_id, $size, $price_size]);
                                }
                            }
                        }
                    }

                    $pdo->commit();
                    $_SESSION['message'] = '<div class="alert alert-success">Product added successfully.</div>';
                    header('Location: manage_products.php');
                    exit();
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $message = '<div class="alert alert-danger">Database error: ' . sanitizeInput($e->getMessage()) . '</div>';
                }
            }
        }
    }
}
?>

<h2 class="mb-4">Add New Product</h2>
<?= $message ?>

<form method="POST" action="add_product.php" enctype="multipart/form-data">
    <div class="row g-3">
        <!-- Product Code -->
        <div class="col-md-6">
            <label for="product_code" class="form-label">Product Code <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text" data-bs-toggle="tooltip" title="Enter a unique product code"><i class="fas fa-id-badge"></i></span>
                <input type="text" class="form-control" id="product_code" name="product_code" required value="<?= sanitizeInput($_POST['product_code'] ?? '') ?>">
            </div>
        </div>
        <!-- Product Name -->
        <div class="col-md-6">
            <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text" data-bs-toggle="tooltip" title="Enter the product name"><i class="fas fa-heading"></i></span>
                <input type="text" class="form-control" id="name" name="name" required value="<?= sanitizeInput($_POST['name'] ?? '') ?>">
            </div>
        </div>
    </div>

    <div class="row g-3 mt-3">
        <!-- Category -->
        <div class="col-md-6">
            <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text" data-bs-toggle="tooltip" title="Select a category for the product"><i class="fas fa-list"></i></span>
                <select class="form-select" id="category_id" name="category_id" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : '' ?>>
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
                <span class="input-group-text" data-bs-toggle="tooltip" title="Enter the product price"><i class="fas fa-dollar-sign"></i></span>
                <input type="number" step="0.01" class="form-control" id="price" name="price" required value="<?= sanitizeInput($_POST['price'] ?? '') ?>">
            </div>
        </div>
    </div>

    <!-- Sizes and Prices Section (only for Pizza) -->
    <div class="row g-3 mt-3" id="sizes_prices_section" style="display: none;">
        <div class="col-md-12">
            <label class="form-label">Sizes and Prices <span class="text-danger">*</span></label>
            <table class="table table-bordered" id="sizesPricesTable">
                <thead>
                    <tr>
                        <th>Size</th>
                        <th>Price ($)</th>
                        <th>
                            <button type="button" class="btn btn-success btn-sm" id="addSizePriceRow" data-bs-toggle="tooltip" title="Add Size and Price"><i class="fas fa-plus"></i></button>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // If there are POSTed sizes (e.g., after validation error), populate them
                    if (isset($_POST['sizes']) && is_array($_POST['sizes'])) {
                        foreach ($_POST['sizes'] as $index => $size) {
                            $price = isset($_POST['prices'][$index]) ? sanitizeInput($_POST['prices'][$index]) : '';
                            echo '<tr>
                                    <td><input type="text" name="sizes[]" class="form-control" required value="' . sanitizeInput($size) . '"></td>
                                    <td><input type="number" step="0.01" name="prices[]" class="form-control" required value="' . sanitizeInput($price) . '"></td>
                                    <td><button type="button" class="btn btn-danger btn-sm removeRow" data-bs-toggle="tooltip" title="Remove Row"><i class="fas fa-trash-alt"></i></button></td>
                                  </tr>';
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Image Source Selection -->
    <div class="row g-3 mt-3">
        <div class="col-md-12">
            <label class="form-label">Image Source <span class="text-danger">*</span></label>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="image_source" id="image_source_upload" value="upload" <?= (!isset($_POST['image_source']) || $_POST['image_source'] === 'upload') ? 'checked' : '' ?>>
                <label class="form-check-label" for="image_source_upload">Upload Image</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="image_source" id="image_source_url" value="url" <?= (isset($_POST['image_source']) && $_POST['image_source'] === 'url') ? 'checked' : '' ?>>
                <label class="form-check-label" for="image_source_url">Image URL</label>
            </div>
        </div>
    </div>

    <!-- Image Upload Field -->
    <div class="row g-3 mt-3" id="image_upload_field" style="display: <?= (!isset($_POST['image_source']) || $_POST['image_source'] === 'upload') ? 'block' : 'none' ?>;">
        <div class="col-md-6">
            <label for="image_file" class="form-label">Upload Image</label>
            <div class="input-group">
                <span class="input-group-text" data-bs-toggle="tooltip" title="Upload a product image"><i class="fas fa-upload"></i></span>
                <input type="file" class="form-control" id="image_file" name="image_file" accept="image/*">
            </div>
            <!-- No existing image to show when adding -->
        </div>
    </div>

    <!-- Image URL Field -->
    <div class="row g-3 mt-3" id="image_url_field" style="display: <?= (isset($_POST['image_source']) && $_POST['image_source'] === 'url') ? 'block' : 'none' ?>;">
        <div class="col-md-6">
            <label for="image_url" class="form-label">Image URL</label>
            <div class="input-group">
                <span class="input-group-text" data-bs-toggle="tooltip" title="Enter the product image URL"><i class="fas fa-image"></i></span>
                <input type="url" class="form-control" id="image_url" name="image_url" placeholder="https://example.com/image.jpg" value="<?= sanitizeInput($_POST['image_url'] ?? '') ?>">
            </div>
        </div>
    </div>

    <!-- Allergies and Description -->
    <div class="row g-3 mt-3">
        <!-- Allergies -->
        <div class="col-md-6">
            <label for="allergies" class="form-label">Allergies</label>
            <div class="input-group">
                <span class="input-group-text" data-bs-toggle="tooltip" title="List product allergies"><i class="fas fa-exclamation-triangle"></i></span>
                <input type="text" class="form-control" id="allergies" name="allergies" placeholder="e.g., Nuts, Milk" value="<?= sanitizeInput($_POST['allergies'] ?? '') ?>">
            </div>
            <div class="form-text">Separate allergies with commas.</div>
        </div>
        <!-- Description -->
        <div class="col-md-6">
            <label for="description" class="form-label">Description</label>
            <div class="input-group">
                <span class="input-group-text" data-bs-toggle="tooltip" title="Describe the product"><i class="fas fa-align-left"></i></span>
                <textarea class="form-control" id="description" name="description"><?= sanitizeInput($_POST['description'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Extras, Sauces, Mix Options -->
    <div class="row g-3 mt-3">
        <!-- Extras -->
        <div class="col-md-4">
            <label for="extras" class="form-label">Extras</label>
            <select class="form-select" id="extras" name="extras[]" multiple>
                <?php foreach ($extras as $extra): ?>
                    <option value="<?= $extra['id'] ?>" <?= (isset($_POST['extras']) && in_array($extra['id'], $_POST['extras'])) ? 'selected' : '' ?>>
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
                    <option value="<?= $sauce['id'] ?>" <?= (isset($_POST['sauces']) && in_array($sauce['id'], $_POST['sauces'])) ? 'selected' : '' ?>>
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
                    <option value="<?= $mix_product['id'] ?>" <?= (isset($_POST['mixes']) && in_array($mix_product['id'], $_POST['mixes'])) ? 'selected' : '' ?>>
                        <?= sanitizeInput($mix_product['name']) ?> - $<?= number_format($mix_product['price'], 2) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Select products to mix with this product.</div>
        </div>
    </div>

    <!-- Product Flags -->
    <div class="row g-3 mt-3">
        <!-- New Product -->
        <div class="col-md-4">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="is_new" name="is_new" <?= (isset($_POST['is_new']) && $_POST['is_new']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_new" data-bs-toggle="tooltip" title="Mark this product as new"><i class="fas fa-star"></i> New Product</label>
            </div>
        </div>
        <!-- On Offer -->
        <div class="col-md-4">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="is_offer" name="is_offer" <?= (isset($_POST['is_offer']) && $_POST['is_offer']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_offer" data-bs-toggle="tooltip" title="Mark this product as on offer"><i class="fas fa-tags"></i> On Offer</label>
            </div>
        </div>
        <!-- Active Status -->
        <div class="col-md-4">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?= (isset($_POST['is_active']) && $_POST['is_active']) || (!isset($_POST['is_active']) && $product['is_active']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active" data-bs-toggle="tooltip" title="Mark this product as active"><i class="fas fa-toggle-on"></i> Active</label>
            </div>
        </div>
    </div>

    <!-- Form Buttons -->
    <div class="mt-4">
        <button type="submit" class="btn btn-success me-2" data-bs-toggle="tooltip" title="Add new product">
            <i class="fas fa-save"></i> Add Product
        </button>
        <a href="manage_products.php" class="btn btn-secondary" data-bs-toggle="tooltip" title="Cancel and return to product list">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>

<!-- Session Message -->
<?php if (isset($_SESSION['message'])): ?>
    <div class="mt-3"><?= $_SESSION['message'] ?></div>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>

<!-- JavaScript Dependencies and Custom Scripts -->
<script>
    $(document).ready(function() {
        // Initialize Select2
        $('#extras, #sauces, #mixes').select2({
            placeholder: "Select options",
            allowClear: true,
            width: '100%'
        });

        // Toggle Image Fields
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
        }).trigger('change');

        // Toggle Sizes and Prices Section
        $('#category_id').change(function() {
            var selectedCategory = $(this).find('option:selected').text().toLowerCase();
            if (selectedCategory === 'pizza') { // Ensure 'pizza' matches exactly or use the category ID
                $('#sizes_prices_section').show();
            } else {
                $('#sizes_prices_section').hide();
                // Optionally, clear existing sizes and prices
                $('#sizesPricesTable tbody').empty();
            }
        }).trigger('change'); // Trigger on page load in case of edit

        // Add Size and Price Row
        $('#addSizePriceRow').click(function() {
            var newRow = '<tr>' +
                '<td><input type="text" name="sizes[]" class="form-control" required></td>' +
                '<td><input type="number" step="0.01" name="prices[]" class="form-control" required></td>' +
                '<td><button type="button" class="btn btn-danger btn-sm removeRow" data-bs-toggle="tooltip" title="Remove Row"><i class="fas fa-trash-alt"></i></button></td>' +
                '</tr>';
            $('#sizesPricesTable tbody').append(newRow);
        });

        // Remove Size and Price Row
        $('#sizesPricesTable').on('click', '.removeRow', function() {
            $(this).closest('tr').remove();
        });

        // Initialize Tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>