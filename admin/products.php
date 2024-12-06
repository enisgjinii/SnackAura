<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ob_start();
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('COMPLETED_STATUS_ID', 3);

function fetchAll($pdo, $query, $params = [])
{
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}
function sanitizeInput($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
function handleImageUpload($file, $is_edit = false, $current_image = '')
{
    $response = ['success' => false, 'url' => '', 'error' => ''];
    if ($file['error'] !== UPLOAD_ERR_OK) return ['error' => 'Error uploading image.'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, ALLOWED_EXTENSIONS)) return ['error' => 'Invalid image format. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS) . '.'];
    if ($file['size'] > MAX_FILE_SIZE) return ['error' => 'Image exceeds 2MB.'];
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) return ['error' => 'Failed to create upload directory.'];
    $new_file = uniqid('product_', true) . '.' . $file_ext;
    $dest = UPLOAD_DIR . $new_file;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $response = ['success' => true, 'url' => $dest];
        if ($is_edit && strpos($current_image, UPLOAD_DIR) === 0 && file_exists($current_image)) unlink($current_image);
    } else {
        $response['error'] = 'Failed to move uploaded file.';
    }
    return $response;
}
function validateImageUrl($url)
{
    $url = filter_var(trim($url), FILTER_SANITIZE_URL);
    if (!filter_var($url, FILTER_VALIDATE_URL)) return ['valid' => false, 'error' => 'Invalid URL.'];
    $headers = @get_headers($url, 1);
    if ($headers && isset($headers['Content-Type'])) {
        $ct = is_array($headers['Content-Type']) ? end($headers['Content-Type']) : $headers['Content-Type'];
        if (in_array(strtolower($ct), ALLOWED_MIME_TYPES)) return ['valid' => true, 'url' => $url];
        return ['valid' => false, 'error' => 'Unsupported image MIME type.'];
    }
    return ['valid' => false, 'error' => 'Unable to verify image URL.'];
}
function getTop10Products($pdo, $status_id = COMPLETED_STATUS_ID)
{
    $q = 'SELECT p.*,SUM(oi.quantity) AS total_sold FROM products p JOIN order_items oi ON p.id=oi.product_id JOIN orders o ON oi.order_id=o.id WHERE o.status_id=? GROUP BY p.id ORDER BY total_sold DESC LIMIT 10';
    return fetchAll($pdo, $q, [$status_id]);
}

$categories = fetchAll($pdo, 'SELECT * FROM categories ORDER BY name ASC');
$extras = fetchAll($pdo, 'SELECT * FROM extras ORDER BY name ASC');
$sauces = fetchAll($pdo, 'SELECT * FROM sauces ORDER BY name ASC');
$sizes = fetchAll($pdo, 'SELECT * FROM sizes ORDER BY name ASC');
$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

switch ($action) {
    case 'add':
    case 'edit':
        $is_edit = ($action === 'edit');
        $product = [];
        $selected_extras = $selected_sauces = $selected_mixes = $selected_sizes = [];
        if ($is_edit) {
            $product = fetchAll($pdo, 'SELECT * FROM products WHERE id=?', [$id])[0] ?? [];
            if (!$product) {
                $message = '<div class="alert alert-danger">Product not found.</div>';
                break;
            }
            $selected_extras = array_column(fetchAll($pdo, 'SELECT extra_id FROM product_extras WHERE product_id=?', [$id]), 'extra_id');
            $selected_sauces = array_column(fetchAll($pdo, 'SELECT sauce_id FROM product_sauces WHERE product_id=?', [$id]), 'sauce_id');
            $selected_mixes = array_column(fetchAll($pdo, 'SELECT mixed_product_id FROM product_mixes WHERE main_product_id=?', [$id]), 'mixed_product_id');
            $selected_sizes = array_column(fetchAll($pdo, 'SELECT size_id FROM product_sizes WHERE product_id=?', [$id]), 'size_id');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $selected_sizes = array_map('intval', $_POST['sizes'] ?? []);
            $image_url = '';
            if (empty($product_code) || empty($name) || $price <= 0 || $category_id === 0) {
                $message = '<div class="alert alert-danger">Code, name, price, category required.</div>';
            } else {
                $sql_check = 'SELECT COUNT(*) FROM products WHERE product_code=?' . ($is_edit ? ' AND id!=?' : '');
                $params_check = $is_edit ? [$product_code, $id] : [$product_code];
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_check->execute($params_check);
                if ($stmt_check->fetchColumn() > 0) {
                    $message = '<div class="alert alert-danger">Product code exists.</div>';
                } else {
                    if ($image_source === 'upload') {
                        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                            $upload = handleImageUpload($_FILES['image_file'], $is_edit, $product['image_url'] ?? '');
                            if ($upload['success']) $image_url = $upload['url'];
                            else {
                                $message = '<div class="alert alert-danger">' . $upload['error'] . '</div>';
                            }
                        } elseif ($is_edit) {
                            $image_url = $product['image_url'];
                        } else {
                            $message = '<div class="alert alert-danger">Upload an image.</div>';
                        }
                    } elseif ($image_source === 'url') {
                        $validate = validateImageUrl($_POST['image_url'] ?? '');
                        if ($validate['valid']) $image_url = $validate['url'];
                        elseif ($is_edit) $image_url = $product['image_url'];
                        else {
                            $message = '<div class="alert alert-danger">' . $validate['error'] . '</div>';
                        }
                    }
                    if (($image_source === 'upload' && ($is_edit || (isset($upload) && $upload['success']))) || ($image_source === 'url' && ($is_edit || (isset($validate) && $validate['valid'])))) {
                        try {
                            $pdo->beginTransaction();
                            if ($is_edit) {
                                $sql = 'UPDATE products SET product_code=?,category_id=?,name=?,price=?,description=?,allergies=?,image_url=?,is_new=?,is_offer=?,is_active=? WHERE id=?';
                                $params = [$product_code, $category_id, $name, $price, $description, $allergies, $image_url, $is_new, $is_offer, $is_active, $id];
                            } else {
                                $sql = 'INSERT INTO products(product_code,category_id,name,price,description,allergies,image_url,is_new,is_offer,is_active) VALUES(?,?,?,?,?,?,?,?,?,?)';
                                $params = [$product_code, $category_id, $name, $price, $description, $allergies, $image_url, $is_new, $is_offer, $is_active];
                            }
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($params);
                            $product_id = $is_edit ? $id : $pdo->lastInsertId();
                            $pdo->prepare('DELETE FROM product_extras WHERE product_id=?')->execute([$product_id]);
                            if (!empty($selected_extras)) {
                                $stmt_extras = $pdo->prepare('INSERT INTO product_extras(product_id,extra_id) VALUES(?,?)');
                                foreach ($selected_extras as $extra_id) $stmt_extras->execute([$product_id, $extra_id]);
                            }
                            $pdo->prepare('DELETE FROM product_sauces WHERE product_id=?')->execute([$product_id]);
                            if (!empty($selected_sauces)) {
                                $stmt_sauces = $pdo->prepare('INSERT INTO product_sauces(product_id,sauce_id) VALUES(?,?)');
                                foreach ($selected_sauces as $sauce_id) $stmt_sauces->execute([$product_id, $sauce_id]);
                            }
                            $pdo->prepare('DELETE FROM product_mixes WHERE main_product_id=?')->execute([$product_id]);
                            if (!empty($selected_mixes)) {
                                $stmt_mixes = $pdo->prepare('INSERT INTO product_mixes(main_product_id,mixed_product_id) VALUES(?,?)');
                                foreach ($selected_mixes as $mixed_product_id) $stmt_mixes->execute([$product_id, $mixed_product_id]);
                            }
                            $pdo->prepare('DELETE FROM product_sizes WHERE product_id=?')->execute([$product_id]);
                            if (!empty($selected_sizes)) {
                                $stmt_sizes = $pdo->prepare('INSERT INTO product_sizes(product_id,size_id) VALUES(?,?)');
                                foreach ($selected_sizes as $size_id) $stmt_sizes->execute([$product_id, $size_id]);
                            }
                            $pdo->commit();
                            $_SESSION['message'] = '<div class="alert alert-success">Product ' . ($is_edit ? 'updated' : 'added') . ' successfully.</div>';
                            header('Location: products.php');
                            exit();
                        } catch (PDOException $e) {
                            $pdo->rollBack();
                            $message = '<div class="alert alert-danger">DB error: ' . sanitizeInput($e->getMessage()) . '</div>';
                        }
                    }
                }
            }
        }
        $mixes = fetchAll($pdo, 'SELECT * FROM products WHERE id!=? ORDER BY name ASC', [$id ?? 0]);
?>
        <div class="container mt-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0"><?= $action === 'add' ? 'Add New Product' : 'Edit Product' ?></h3>
                </div>
                <div class="card-body">
                    <?= $message ?>
                    <form method="POST" action="products.php?action=<?= $action ?><?= $is_edit ? '&id=' . $id : '' ?>" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Product Code <span class="text-danger">*</span></label>
                                <div class="input-group"><span class="input-group-text"><i class="fas fa-id-badge"></i></span>
                                    <input type="text" class="form-control" name="product_code" required value="<?= sanitizeInput($is_edit ? $product['product_code'] : ($_POST['product_code'] ?? '')) ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                <div class="input-group"><span class="input-group-text"><i class="fas fa-heading"></i></span>
                                    <input type="text" class="form-control" name="name" required value="<?= sanitizeInput($is_edit ? $product['name'] : ($_POST['name'] ?? '')) ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mt-3">
                            <div class="col-md-6">
                                <label class="form-label">Category <span class="text-danger">*</span></label>
                                <div class="input-group"><span class="input-group-text"><i class="fas fa-list"></i></span>
                                    <select class="form-select" name="category_id" required>
                                        <option value="">Select a category</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat['id'] ?>" <?= (($is_edit && $product['category_id'] == $cat['id']) || (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id'])) ? 'selected' : '' ?>>
                                                <?= sanitizeInput($cat['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Price ($) <span class="text-danger">*</span></label>
                                <div class="input-group"><span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                                    <input type="number" step="0.01" class="form-control" name="price" required value="<?= sanitizeInput($is_edit ? $product['price'] : ($_POST['price'] ?? '')) ?>">
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <h5>Image Source</h5>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="image_source" value="upload" <?= (($is_edit && !empty($product['image_url']) && strpos($product['image_url'], UPLOAD_DIR) === 0) || (!isset($_POST['image_source']) || $_POST['image_source'] === 'upload')) ? 'checked' : '' ?>>
                                <label class="form-check-label"><i class="fas fa-upload me-1"></i> Upload Image</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="image_source" value="url" <?= (($is_edit && !empty($product['image_url']) && strpos($product['image_url'], UPLOAD_DIR) !== 0) || (isset($_POST['image_source']) && $_POST['image_source'] === 'url')) ? 'checked' : '' ?>>
                                <label class="form-check-label"><i class="fas fa-link me-1"></i> Image URL</label>
                            </div>
                        </div>
                        <div class="row g-3 mt-3" id="image_upload_field" style="display: <?= (($is_edit && !empty($product['image_url']) && strpos($product['image_url'], UPLOAD_DIR) === 0) || (!isset($_POST['image_source']) || $_POST['image_source'] === 'upload')) ? 'block' : 'none' ?>;">
                            <div class="col-md-6">
                                <label class="form-label">Upload Image</label>
                                <div class="input-group"><span class="input-group-text"><i class="fas fa-image"></i></span>
                                    <input type="file" class="form-control" name="image_file" accept="image/*">
                                </div>
                                <?php if ($is_edit && !empty($product['image_url']) && strpos($product['image_url'], UPLOAD_DIR) === 0): ?>
                                    <div class="mt-2"><img src="<?= sanitizeInput($product['image_url']) ?>" class="img-thumbnail" style="max-width:200px;"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row g-3 mt-3" id="image_url_field" style="display: <?= (($is_edit && !empty($product['image_url']) && strpos($product['image_url'], UPLOAD_DIR) !== 0) || (isset($_POST['image_source']) && $_POST['image_source'] === 'url')) ? 'block' : 'none' ?>;">
                            <div class="col-md-6">
                                <label class="form-label">Image URL</label>
                                <div class="input-group"><span class="input-group-text"><i class="fas fa-link"></i></span>
                                    <input type="url" class="form-control" name="image_url" placeholder="https://example.com/image.jpg" value="<?= sanitizeInput($is_edit ? $product['image_url'] : ($_POST['image_url'] ?? '')) ?>">
                                </div>
                                <?php if ($is_edit && !empty($product['image_url']) && strpos($product['image_url'], UPLOAD_DIR) !== 0): ?>
                                    <div class="mt-2"><img src="<?= sanitizeInput($product['image_url']) ?>" class="img-thumbnail" style="max-width:200px;"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row g-3 mt-4">
                            <div class="col-md-6">
                                <label class="form-label">Allergies</label>
                                <div class="input-group"><span class="input-group-text"><i class="fas fa-exclamation-triangle"></i></span>
                                    <input type="text" class="form-control" name="allergies" placeholder="e.g., Nuts, Milk" value="<?= sanitizeInput($is_edit ? $product['allergies'] : ($_POST['allergies'] ?? '')) ?>">
                                </div>
                                <div class="form-text">Separate allergies with commas.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Description</label>
                                <div class="input-group"><span class="input-group-text"><i class="fas fa-align-left"></i></span>
                                    <textarea class="form-control" name="description" rows="3"><?= sanitizeInput($is_edit ? $product['description'] : ($_POST['description'] ?? '')) ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mt-4">
                            <div class="col-md-4">
                                <label class="form-label">Extras</label>
                                <select class="form-select" id="extras" name="extras[]" multiple>
                                    <?php foreach ($extras as $extra): ?>
                                        <option value="<?= $extra['id'] ?>" <?= ((isset($_POST['extras']) && in_array($extra['id'], $_POST['extras'])) || ($is_edit && in_array($extra['id'], $selected_extras))) ? 'selected' : '' ?>>
                                            <?= sanitizeInput($extra['name']) ?> - $<?= number_format($extra['price'], 2) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sauces</label>
                                <select class="form-select" id="sauces" name="sauces[]" multiple>
                                    <?php foreach ($sauces as $sauce): ?>
                                        <option value="<?= $sauce['id'] ?>" <?= ((isset($_POST['sauces']) && in_array($sauce['id'], $_POST['sauces'])) || ($is_edit && in_array($sauce['id'], $selected_sauces))) ? 'selected' : '' ?>>
                                            <?= sanitizeInput($sauce['name']) ?> - $<?= number_format($sauce['price'], 2) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mix Options</label>
                                <select class="form-select" id="mixes" name="mixes[]" multiple>
                                    <?php foreach ($mixes as $mp): ?>
                                        <option value="<?= $mp['id'] ?>" <?= ((isset($_POST['mixes']) && in_array($mp['id'], $_POST['mixes'])) || ($is_edit && in_array($mp['id'], $selected_mixes))) ? 'selected' : '' ?>>
                                            <?= sanitizeInput($mp['name']) ?> - $<?= number_format($mp['price'], 2) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sizes</label>
                                <select class="form-select" id="sizes" name="sizes[]" multiple>
                                    <?php foreach ($sizes as $size): ?>
                                        <option value="<?= $size['id'] ?>" <?= ((isset($_POST['sizes']) && in_array($size['id'], $_POST['sizes'])) || ($is_edit && in_array($size['id'], $selected_sizes))) ? 'selected' : '' ?>>
                                            <?= sanitizeInput($size['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mt-4">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="is_new" name="is_new" <?= (($is_edit && $product['is_new']) || (isset($_POST['is_new']) && $_POST['is_new'])) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_new"><i class="fas fa-star text-warning me-1"></i>New</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="is_offer" name="is_offer" <?= (($is_edit && $product['is_offer']) || (isset($_POST['is_offer']) && $_POST['is_offer'])) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_offer"><i class="fas fa-tags text-success me-1"></i>On Offer</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?= (($is_edit && $product['is_active']) || (!$is_edit && !isset($_POST['is_active'])) || (isset($_POST['is_active']) && $_POST['is_active'])) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active"><i class="fas fa-toggle-on text-primary me-1"></i>Active</label>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 d-flex justify-content-end">
                            <button type="submit" class="btn btn-success me-2"><i class="fas fa-save me-1"></i><?= $action === 'add' ? 'Add' : 'Update' ?> Product</button>
                            <a href="products.php" class="btn btn-secondary"><i class="fas fa-times me-1"></i> Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php if (isset($_SESSION['message'])): ?>
            <div class="container mt-3"><?= $_SESSION['message'] ?><?php unset($_SESSION['message']); ?></div>
        <?php endif; ?>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script>
            $(function() {
                $('#extras,#sauces,#mixes,#sizes').select2({
                    placeholder: "Select options",
                    allowClear: true,
                    width: '100%'
                });
                $('input[name="image_source"]').change(function() {
                    if ($(this).val() === 'upload') {
                        $('#image_upload_field').slideDown();
                        $('#image_url_field').slideUp();
                        $('#image_url').prop('required', false).prop('disabled', true);
                        $('#image_file').prop('required', true).prop('disabled', false);
                    } else {
                        $('#image_upload_field').slideUp();
                        $('#image_url_field').slideDown();
                        $('#image_file').prop('required', false).prop('disabled', true);
                        $('#image_url').prop('required', true).prop('disabled', false);
                    }
                }).trigger('change');
                var tt = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tt.map(function(el) {
                    return new bootstrap.Tooltip(el);
                });
            });
        </script>
    <?php break;
    case 'delete':
        if ($id > 0) {
            try {
                $pdo->beginTransaction();
                $stmt_image = $pdo->prepare('SELECT image_url FROM products WHERE id=?');
                $stmt_image->execute([$id]);
                $image_url = $stmt_image->fetchColumn();
                $pdo->prepare('DELETE FROM product_extras WHERE product_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM product_sauces WHERE product_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM product_mixes WHERE main_product_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM product_sizes WHERE product_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
                if ($image_url && strpos($image_url, UPLOAD_DIR) === 0 && file_exists($image_url)) unlink($image_url);
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
        <div class="container mt-4">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h3 class="mb-0">Delete Product</h3>
                </div>
                <div class="card-body">
                    <?= $message ?>
                    <a href="products.php" class="btn btn-secondary mt-3"><i class="fas fa-arrow-left me-1"></i> Return</a>
                </div>
            </div>
        </div>
    <?php break;
    case 'top10':
        $topProducts = getTop10Products($pdo);
    ?>
        <div class="container mt-4">
            <div class="card">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Top 10 Best-Selling Products</h3>
                    <a href="products.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</a>
                </div>
                <div class="card-body">
                    <?= $message ?>
                    <?php if (!empty($topProducts)): ?>
                        <canvas id="topProductsChart" width="400" height="200" class="mb-4"></canvas>
                        <div class="table-responsive">
                            <table id="top10Table" class="table table-striped table-hover align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Product Code</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Sizes</th>
                                        <th>Total Sold</th>
                                        <th>Price ($)</th>
                                        <th>Image</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topProducts as $p): ?>
                                        <tr>
                                            <td><?= sanitizeInput($p['id']) ?></td>
                                            <td><?= sanitizeInput($p['product_code']) ?></td>
                                            <td><?= sanitizeInput($p['name']) ?></td>
                                            <td><?php foreach ($categories as $c) {
                                                    if ($c['id'] == $p['category_id']) {
                                                        echo sanitizeInput($c['name']);
                                                        break;
                                                    }
                                                } ?></td>
                                            <td>
                                                <?php
                                                $ps = fetchAll($pdo, 'SELECT s.name FROM sizes s JOIN product_sizes ps ON s.id=ps.size_id WHERE ps.product_id=?', [$p['id']]);
                                                echo !empty($ps) ? sanitizeInput(implode(', ', array_column($ps, 'name'))) : '-';
                                                ?>
                                            </td>
                                            <td><?= sanitizeInput($p['total_sold']) ?></td>
                                            <td><?= number_format($p['price'], 2) ?></td>
                                            <td><?php if (!empty($p['image_url'])): ?>
                                                    <a href="<?= sanitizeInput($p['image_url']) ?>" target="_blank" class="text-primary" data-bs-toggle="tooltip" title="View Image"><i class="fas fa-image"></i></a>
                                                    <?php else: ?>- <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                        <script>
                            $(function() {
                                $('#top10Table').DataTable({
                                    responsive: true,
                                    order: [
                                        [5, 'desc']
                                    ],
                                    language: {
                                        emptyTable: "No data",
                                        info: "Showing _START_ to _END_ of _TOTAL_",
                                        infoEmpty: "0 to 0 of 0",
                                        lengthMenu: "Show _MENU_",
                                        paginate: {
                                            first: "First",
                                            last: "Last",
                                            next: "Next",
                                            previous: "Previous"
                                        },
                                        search: "Search:"
                                    }
                                });
                                var labels = [<?php foreach ($topProducts as $tp): ?> '<?= addslashes(sanitizeInput($tp['name'])) ?>', <?php endforeach; ?>];
                                var data = [<?php foreach ($topProducts as $tp): ?><?= sanitizeInput($tp['total_sold']) ?>, <?php endforeach; ?>];
                                var ctx = document.getElementById('topProductsChart').getContext('2d');
                                new Chart(ctx, {
                                    type: 'bar',
                                    data: {
                                        labels: labels,
                                        datasets: [{
                                            label: 'Total Sold',
                                            data: data,
                                            backgroundColor: 'rgba(54,162,235,0.6)',
                                            borderColor: 'rgba(54,162,235,1)',
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
                                var tts = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                                tts.map(function(el) {
                                    return new bootstrap.Tooltip(el);
                                });
                            });
                        </script>
                    <?php else: ?>
                        <div class="alert alert-info">No sales data available.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php break;
    case 'view':
    default:
        $products = fetchAll($pdo, 'SELECT p.*,c.name AS category_name FROM products p JOIN categories c ON p.category_id=c.id ORDER BY p.created_at DESC');
    ?>
        <div class="container mt-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Manage Products</h3>
                <a href="products.php?action=add" class="btn btn-success"><i class="fas fa-plus me-1"></i> Add New Product</a>
            </div>
            <div class="mb-3">
                <a href="products.php?action=top10" class="btn btn-primary"><i class="fas fa-chart-line me-1"></i> Top 10 Products</a>
            </div>
            <?= $message ?>
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="productsTable" class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Product Code</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Extras</th>
                                    <th>Sauces</th>
                                    <th>Mix Options</th>
                                    <th>Sizes</th>
                                    <th>Allergies</th>
                                    <th>Description</th>
                                    <th>Price ($)</th>
                                    <th>Image</th>
                                    <th>New</th>
                                    <th>Offer</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($products)): foreach ($products as $pr): ?>
                                        <tr>
                                            <td><?= sanitizeInput($pr['id']) ?></td>
                                            <td><?= sanitizeInput($pr['product_code']) ?></td>
                                            <td><?= sanitizeInput($pr['name']) ?></td>
                                            <td><?= sanitizeInput($pr['category_name']) ?></td>
                                            <td><?php $pe = fetchAll($pdo, 'SELECT e.name FROM extras e JOIN product_extras pe ON e.id=pe.extra_id WHERE pe.product_id=?', [$pr['id']]);
                                                echo !empty($pe) ? sanitizeInput(implode(', ', array_column($pe, 'name'))) : '-'; ?></td>
                                            <td><?php $ps = fetchAll($pdo, 'SELECT s.name FROM sauces s JOIN product_sauces ps ON s.id=ps.sauce_id WHERE ps.product_id=?', [$pr['id']]);
                                                echo !empty($ps) ? sanitizeInput(implode(', ', array_column($ps, 'name'))) : '-'; ?></td>
                                            <td><?php $pm = fetchAll($pdo, 'SELECT p2.name FROM product_mixes pm JOIN products p2 ON pm.mixed_product_id=p2.id WHERE pm.main_product_id=?', [$pr['id']]);
                                                echo !empty($pm) ? sanitizeInput(implode(', ', array_column($pm, 'name'))) : '-'; ?></td>
                                            <td><?php $pz = fetchAll($pdo, 'SELECT s.name FROM sizes s JOIN product_sizes psz ON s.id=psz.size_id WHERE psz.product_id=?', [$pr['id']]);
                                                echo !empty($pz) ? sanitizeInput(implode(', ', array_column($pz, 'name'))) : '-'; ?></td>
                                            <td><?= sanitizeInput($pr['allergies']) ?: '-' ?></td>
                                            <td><?= sanitizeInput($pr['description']) ?: '-' ?></td>
                                            <td><?= number_format($pr['price'], 2) ?></td>
                                            <td><?php if (!empty($pr['image_url'])): ?><img src="<?= sanitizeInput($pr['image_url']) ?>" class="img-thumbnail" style="max-width:100px;"><?php else: ?>-<?php endif; ?></td>
                                            <td><?= $pr['is_new'] ? '<span class="badge bg-warning text-dark"><i class="fas fa-star"></i></span>' : '-' ?></td>
                                            <td><?= $pr['is_offer'] ? '<span class="badge bg-success"><i class="fas fa-tags"></i></span>' : '-' ?></td>
                                            <td><?= $pr['is_active'] ? '<span class="badge bg-primary"><i class="fas fa-check-circle"></i></span>' : '<span class="badge bg-secondary"><i class="fas fa-times-circle"></i></span>' ?></td>
                                            <td><?= sanitizeInput($pr['created_at']) ?></td>
                                            <td>
                                                <a href="products.php?action=edit&id=<?= $pr['id'] ?>" class="btn btn-sm btn-warning me-1" data-bs-toggle="tooltip" title="Edit"><i class="fas fa-edit"></i></a>
                                                <button class="btn btn-sm btn-danger" onclick="showDeleteModal(<?= $pr['id'] ?>)" data-bs-toggle="tooltip" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach;
                                else: ?>
                                    <tr>
                                        <td colspan="17" class="text-center">No products available.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="GET" action="products.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteProductId">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">Confirm Deletion</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">Are you sure? This cannot be undone.</div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt me-1"></i> Delete</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> Cancel</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php if (isset($_SESSION['message'])): ?>
            <div class="container mt-3"><?= $_SESSION['message'] ?><?php unset($_SESSION['message']); ?></div>
        <?php endif; ?>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
        <link href="https://cdn.datatables.net/1.13.5/css/jquery.dataTables.min.css" rel="stylesheet" />
        <script>
            $(function() {
                $('#extras,#sauces,#mixes,#sizes').select2({
                    placeholder: "Select options",
                    allowClear: true,
                    width: '100%'
                });
                $('input[name="image_source"]').change(function() {
                    if ($(this).val() === 'upload') {
                        $('#image_upload_field').slideDown();
                        $('#image_url_field').slideUp();
                        $('#image_url').prop('required', false).prop('disabled', true);
                        $('#image_file').prop('required', true).prop('disabled', false);
                    } else {
                        $('#image_upload_field').slideUp();
                        $('#image_url_field').slideDown();
                        $('#image_file').prop('required', false).prop('disabled', true);
                        $('#image_url').prop('required', true).prop('disabled', false);
                    }
                }).trigger('change');
                $('#productsTable').DataTable({
                    paging: true,
                    searching: true,
                    info: true,
                    responsive: true,
                    order: [
                        [0, 'desc']
                    ],
                    language: {
                        emptyTable: "No products.",
                        info: "Showing _START_ to _END_ of _TOTAL_",
                        infoEmpty: "0 to 0 of 0",
                        lengthMenu: "Show _MENU_",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Previous"
                        },
                        search: "Search:"
                    }
                });
                var tt = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tt.map(function(el) {
                    return new bootstrap.Tooltip(el);
                });
            });

            function showDeleteModal(id) {
                $('#deleteProductId').val(id);
                new bootstrap.Modal(document.getElementById('deleteModal')).show();
            }
        </script>
        <?php include 'includes/footer.php'; ?>
<?php break;
} ?>