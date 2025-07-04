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

function s($d)
{
    return htmlspecialchars(trim((string)$d), ENT_QUOTES, 'UTF-8');
}

function handleImageUpload($file, $edit = false, $curr = '')
{
    $r = ['success' => false, 'url' => '', 'error' => ''];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $r['error'] = 'Error uploading image.';
        return $r;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        $r['error'] = 'Invalid image format.';
        return $r;
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        $r['error'] = 'Image exceeds 2MB.';
        return $r;
    }
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
        $r['error'] = 'Failed to create upload directory.';
        return $r;
    }
    $new_name = uniqid('product_', true) . '.' . $ext;
    $dest = UPLOAD_DIR . $new_name;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $r['success'] = true;
        $r['url'] = $dest;
        if ($edit && strpos($curr, UPLOAD_DIR) === 0 && file_exists($curr)) {
            unlink($curr);
        }
    } else {
        $r['error'] = 'Failed to move file.';
    }
    return $r;
}

function validateImageUrl($url)
{
    $url = filter_var(trim((string)$url), FILTER_SANITIZE_URL);
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['valid' => false, 'error' => 'Invalid URL.'];
    }
    $h = @get_headers($url, 1);
    if ($h && isset($h['Content-Type'])) {
        $ct = is_array($h['Content-Type']) ? end($h['Content-Type']) : $h['Content-Type'];
        $ct = strtolower($ct);
        if (in_array($ct, ALLOWED_MIME_TYPES)) {
            return ['valid' => true, 'url' => $url];
        }
        return ['valid' => false, 'error' => 'Unsupported image type.'];
    }
    return ['valid' => false, 'error' => 'Cannot verify image URL.'];
}

$db_categories = fetchAll($pdo, "SELECT id,name FROM categories");
$extrasList  = fetchAll($pdo, "SELECT * FROM extras_products WHERE category='Extras' ORDER BY name ASC");
$saucesList  = fetchAll($pdo, "SELECT * FROM extras_products WHERE category='Sauces' ORDER BY name ASC");
$dressesList = fetchAll($pdo, "SELECT * FROM extras_products WHERE category='Dressing' ORDER BY name ASC");

$action = $_GET['action'] ?? 'view';
$id = (int)($_GET['id'] ?? 0);
$message = '';
$editing = ($action === 'edit');
$adding = ($action === 'add');
$viewing = ($action === 'view');
$deleting = ($action === 'delete');
$product = $editing ? fetchAll($pdo, 'SELECT * FROM products WHERE id=?', [$id]) : [];
$product = $editing && !empty($product) ? $product[0] : [];

if ($adding) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    }
}

if ($adding || $editing) {
    $post_image_source = $_POST['image_source'] ?? '';
    $image_source = $editing ? $post_image_source : ($post_image_source ?: 'upload');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $product_code = s($_POST['product_code'] ?? '');
        $name = s($_POST['name'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $description = s($_POST['description'] ?? '');
        $allergies = s($_POST['allergies'] ?? '');
        $is_new = isset($_POST['is_new']) ? 1 : 0;
        $is_offer = isset($_POST['is_offer']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $has_extras_sauces = isset($_POST['has_extras_sauces']) ? 1 : 0;
        $has_sizes = isset($_POST['has_sizes']) ? 1 : 0;
        $has_dresses = isset($_POST['has_dresses']) ? 1 : 0;
        if (empty($product_code) || empty($name) || $category_id === 0) {
            $message = '<div class="alert alert-danger">Code, name, and category are required.</div>';
        } else {
            $sql_check = 'SELECT COUNT(*) FROM products WHERE product_code=?' . ($editing ? ' AND id!=?' : '');
            $params_check = $editing ? [$product_code, $id] : [$product_code];
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute($params_check);
            if ($stmt_check->fetchColumn() > 0) {
                $message = '<div class="alert alert-danger">Product code already exists.</div>';
            }
        }
        if (!$message) {
            $image_url = '';
            if ($image_source === 'upload') {
                if (!empty($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $upload = handleImageUpload($_FILES['image_file'], $editing, $product['image_url'] ?? '');
                    if (!$upload['success']) {
                        $message = '<div class="alert alert-danger">' . $upload['error'] . '</div>';
                    }
                    $image_url = $upload['success'] ? $upload['url'] : '';
                } elseif ($editing) {
                    $image_url = $product['image_url'];
                } else {
                    $message = '<div class="alert alert-danger">Please upload an image.</div>';
                }
            } else {
                $validate = validateImageUrl($_POST['image_url'] ?? '');
                if ($validate['valid']) {
                    $image_url = $validate['url'];
                } elseif ($editing) {
                    $image_url = $product['image_url'];
                } else {
                    $message = '<div class="alert alert-danger">' . $validate['error'] . '</div>';
                }
            }
            if (!$message) {
                $properties = [];
                if (!$has_sizes) {
                    $base_price = (float)($_POST['base_price'] ?? 0);
                    $properties['base_price'] = $base_price;
                }
                if ($has_extras_sauces) {
                    $properties['extras'] = [];
                    if (!empty($_POST['global_extras'])) {
                        foreach ($_POST['global_extras'] as $ge) {
                            $en = s($ge['name'] ?? '');
                            $ep = (float)($ge['price'] ?? 0);
                            if ($en !== '') {
                                $properties['extras'][] = ['name' => $en, 'price' => $ep];
                            }
                        }
                    }
                    $properties['sauces'] = [];
                    if (!empty($_POST['global_sauces'])) {
                        foreach ($_POST['global_sauces'] as $gs) {
                            $sn = s($gs['name'] ?? '');
                            $sp = (float)($gs['price'] ?? 0);
                            if ($sn !== '') {
                                $properties['sauces'][] = ['name' => $sn, 'price' => $sp];
                            }
                        }
                    }
                }
                if ($has_sizes) {
                    $properties['sizes'] = [];
                    if (!empty($_POST['sizes_block'])) {
                        foreach ($_POST['sizes_block'] as $block) {
                            $size = s($block['size'] ?? '');
                            $price = (float)($block['price'] ?? 0);
                            $max_sauces_block = isset($block['max_sauces']) ? (int)$block['max_sauces'] : 0;
                            $exs = [];
                            $sas = [];
                            if ($has_extras_sauces && !empty($block['extras_selected'])) {
                                $global_extras = $properties['extras'] ?? [];
                                foreach ($block['extras_selected'] as $ex_i) {
                                    $ex_i = (int)$ex_i;
                                    $gex = $global_extras[$ex_i] ?? null;
                                    $ex_pr = (float)($block['extras_prices'][$ex_i] ?? 0);
                                    if ($gex) {
                                        $exs[] = ['name' => $gex['name'], 'price' => $ex_pr];
                                    }
                                }
                            }
                            if ($has_extras_sauces && !empty($block['sauces_selected'])) {
                                $global_sauces = $properties['sauces'] ?? [];
                                foreach ($block['sauces_selected'] as $sa_i) {
                                    $sa_i = (int)$sa_i;
                                    $gsa = $global_sauces[$sa_i] ?? null;
                                    $sa_pr = (float)($block['sauces_prices'][$sa_i] ?? 0);
                                    if ($gsa) {
                                        $sas[] = ['name' => $gsa['name'], 'price' => $sa_pr];
                                    }
                                }
                            }
                            if ($size) {
                                $properties['sizes'][] = [
                                    'size' => $size,
                                    'price' => $price,
                                    'extras' => $exs,
                                    'sauces' => $sas,
                                    'max_sauces' => $max_sauces_block
                                ];
                            }
                        }
                    }
                } else {
                    if ($has_extras_sauces) {
                        $max_sauces_base = isset($_POST['max_sauces_base']) ? (int)$_POST['max_sauces_base'] : 0;
                        $max_extras_base = isset($_POST['max_extras_base']) ? (int)$_POST['max_extras_base'] : 0;
                        $properties['max_sauces_base'] = $max_sauces_base;
                        $properties['max_extras_base'] = $max_extras_base;
                    }
                }
                if ($has_dresses) {
                    $properties['dresses'] = [];
                    if (!empty($_POST['dresses'])) {
                        foreach ($_POST['dresses'] as $d) {
                            $dn = s($d['dress'] ?? '');
                            $dp = (float)($d['price'] ?? 0);
                            if ($dn !== '') {
                                $properties['dresses'][] = [
                                    'name' => $dn,
                                    'price' => $dp
                                ];
                            }
                        }
                    }
                    if (!$has_sizes) {
                        $max_dresses_base = isset($_POST['max_dresses_base']) ? (int)$_POST['max_dresses_base'] : 0;
                        $properties['max_dresses_base'] = $max_dresses_base;
                    }
                }
                $props_json = json_encode($properties);
                $user_id = $_SESSION['user_id'] ?? null;
                try {
                    if ($editing) {
                        $stmt_old = $pdo->prepare('SELECT * FROM products WHERE id=?');
                        $stmt_old->execute([$id]);
                        $old_product = $stmt_old->fetch(PDO::FETCH_ASSOC);
                        $sql = 'UPDATE products
                              SET product_code=?,
                                  category_id=?,
                                  name=?,
                                  description=?,
                                  allergies=?,
                                  image_url=?,
                                  is_new=?,
                                  is_offer=?,
                                  is_active=?,
                                  properties=?,
                                  updated_by=?,
                                  updated_at=NOW()
                              WHERE id=?';
                        $params = [
                            $product_code,
                            $category_id,
                            $name,
                            $description,
                            $allergies,
                            $image_url,
                            $is_new,
                            $is_offer,
                            $is_active,
                            $props_json,
                            $user_id,
                            $id
                        ];
                        $stmt_update = $pdo->prepare($sql);
                        $stmt_update->execute($params);
                        $new_product = fetchAll($pdo, 'SELECT * FROM products WHERE id=?', [$id])[0];
                        $stmt_audit = $pdo->prepare('INSERT INTO product_audit (product_id, action, changed_by, old_values, new_values) VALUES (?, "update", ?, ?, ?)');
                        $stmt_audit->execute([
                            $id,
                            $user_id,
                            json_encode($old_product),
                            json_encode($new_product)
                        ]);
                    } else {
                        $sql = 'INSERT INTO products
                              (product_code, category_id, name, description, allergies, image_url,
                               is_new, is_offer, is_active, properties, created_by, created_at)
                              VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())';
                        $params = [
                            $product_code,
                            $category_id,
                            $name,
                            $description,
                            $allergies,
                            $image_url,
                            $is_new,
                            $is_offer,
                            $is_active,
                            $props_json,
                            $user_id
                        ];
                        $stmt_insert = $pdo->prepare($sql);
                        $stmt_insert->execute($params);
                        $new_id = $pdo->lastInsertId();
                        $new_product = fetchAll($pdo, 'SELECT * FROM products WHERE id=?', [$new_id])[0];
                        $stmt_audit = $pdo->prepare('INSERT INTO product_audit (product_id, action, changed_by, old_values, new_values) VALUES (?, "insert", ?, NULL, ?)');
                        $stmt_audit->execute([
                            $new_id,
                            $user_id,
                            json_encode($new_product)
                        ]);
                    }
                    $_SESSION['message'] = '<div class="alert alert-success">' . ($editing ? 'Updated' : 'Added') . ' product successfully.</div>';
                    header('Location: products.php');
                    exit();
                } catch (PDOException $e) {
                    $message = '<div class="alert alert-danger">DB error: ' . s($e->getMessage()) . '</div>';
                }
            }
        }
    }
}

$pc = $editing ? $product['product_code'] : ($_POST['product_code'] ?? '');
$pn = $editing ? $product['name'] : ($_POST['name'] ?? '');
$category_id = $editing ? $product['category_id'] : ($_POST['category_id'] ?? 0);
$desc = $editing ? $product['description'] : ($_POST['description'] ?? '');
$allg = $editing ? $product['allergies'] : ($_POST['allergies'] ?? '');
$i_new = $editing ? $product['is_new'] : (isset($_POST['is_new']) ? 1 : 0);
$i_off = $editing ? $product['is_offer'] : (isset($_POST['is_offer']) ? 1 : 0);
$i_act = $editing ? $product['is_active'] : (isset($_POST['is_active']) ? 1 : 0);
$img_src = $editing ? ($product['image_url'] ?? '') : ($_POST['image_url'] ?? '');
if ($img_src === null) {
    $img_src = '';
}
$props = ($editing && $product['properties']) ? json_decode($product['properties'], true) : [];
$has_extras_sauces = (!empty($props['extras']) || !empty($props['sauces']) || isset($_POST['has_extras_sauces']));
$has_sizes = (!empty($props['sizes']) || isset($_POST['has_sizes']));
$has_dresses = (!empty($props['dresses']) || isset($_POST['has_dresses']));
$base_price = isset($props['base_price']) ? $props['base_price'] : (float)($_POST['base_price'] ?? 0);
$image_source = $editing
    ? (isset($_POST['image_source'])
        ? $_POST['image_source']
        : ((!empty($product['image_url']) && strpos($product['image_url'], UPLOAD_DIR) === false)
            ? 'url'
            : 'upload'))
    : (isset($_POST['image_source'])
        ? $_POST['image_source']
        : 'upload');
$img_u = ($image_source === 'upload') ? 'block' : 'none';
$img_ul = ($image_source === 'url') ? 'block' : 'none';
?>

<!-- Products Content -->
<div class="products-content">
    <?php if ($viewing): ?>
        <!-- Products List View -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-text">
                    <h1 class="page-title">Products</h1>
                    <p class="page-subtitle">Manage your product inventory and menu items</p>
                                </div>
                <div class="header-actions">
                    <a href="products.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Product
                    </a>
                            </div>
                                </div>
                            </div>

        <!-- Products Table -->
        <div class="table-section">
            <div class="table-container">
                <table id="productsTable" class="data-table">
                    <thead>
                        <tr>
                            <th width="80">Image</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                                        <?php
                        $products = fetchAll($pdo, "
                            SELECT p.*, c.name as category_name 
                            FROM products p 
                            LEFT JOIN categories c ON p.category_id = c.id 
                            ORDER BY p.created_at DESC
                        ");
                        ?>
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $prod): ?>
                                <tr>
                                    <td class="image-cell">
                                        <?php if (!empty($prod['image_url'])): ?>
                                            <img src="<?= s($prod['image_url']) ?>" alt="Product Image" class="product-image">
                                        <?php else: ?>
                                            <div class="placeholder-image">
                                                <i class="fas fa-image"></i>
                                </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="code-cell">
                                        <span class="product-code"><?= s($prod['product_code']) ?></span>
                                    </td>
                                    <td class="name-cell">
                                        <span class="product-name"><?= s($prod['name']) ?></span>
                                        <?php if ($prod['is_new']): ?>
                                            <span class="badge badge-new">New</span>
                                        <?php endif; ?>
                                        <?php if ($prod['is_offer']): ?>
                                            <span class="badge badge-offer">Offer</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="category-cell">
                                        <span class="category-name"><?= s($prod['category_name']) ?></span>
                                    </td>
                                    <td class="price-cell">
                                        <?php
                                        $props = json_decode($prod['properties'], true);
                                        if (isset($props['base_price'])) {
                                            echo '$' . number_format($props['base_price'], 2);
                                        } elseif (isset($props['sizes']) && !empty($props['sizes'])) {
                                            $min_price = min(array_column($props['sizes'], 'price'));
                                            $max_price = max(array_column($props['sizes'], 'price'));
                                            if ($min_price === $max_price) {
                                                echo '$' . number_format($min_price, 2);
                                            } else {
                                                echo '$' . number_format($min_price, 2) . ' - $' . number_format($max_price, 2);
                                            }
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td class="status-cell">
                                        <span class="status-badge status-<?= $prod['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $prod['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td class="actions-cell">
                                        <div class="action-buttons">
                                            <a href="products.php?action=edit&id=<?= $prod['id'] ?>" class="btn btn-sm btn-edit" title="Edit Product">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-delete delete-product-btn" 
                                                data-id="<?= $prod['id'] ?>" 
                                                data-name="<?= s($prod['name']) ?>"
                                                title="Delete Product">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                    </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="no-data">
                                    <div class="empty-state">
                                        <i class="fas fa-box-open"></i>
                                        <h3>No Products Found</h3>
                                        <p>Start by adding your first product to your inventory.</p>
                                        <a href="products.php?action=add" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Add Product
                                        </a>
                                </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                                                </div>
                                                </div>

    <?php elseif ($adding || $editing): ?>
        <!-- Add/Edit Product Form -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-text">
                    <h1 class="page-title"><?= $editing ? 'Edit Product' : 'Add New Product' ?></h1>
                    <p class="page-subtitle"><?= $editing ? 'Update product information' : 'Create a new product for your menu' ?></p>
                                                </div>
                <div class="header-actions">
                    <a href="products.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Products
                    </a>
                                    </div>
                                </div>
                            </div>

        <?php if ($message): ?>
            <div class="alert-container">
                <?= $message ?>
                        </div>
        <?php endif; ?>

        <div class="form-section">
            <form method="POST" enctype="multipart/form-data" action="products.php?action=<?= $editing ? 'edit&id=' . $id : 'add' ?>" class="product-form">
                <div class="form-grid">
                    <!-- Basic Information -->
                    <div class="form-card">
                        <div class="card-header">
                            <h3>Basic Information</h3>
                            <i class="fas fa-info-circle"></i>
                    </div>
                        <div class="card-content">
                            <div class="form-group">
                                <label for="product_code" class="form-label">Product Code <span class="required">*</span></label>
                                <input type="text" id="product_code" name="product_code" class="form-control" required value="<?= s($pc) ?>" placeholder="e.g., P1234">
                                            </div>
                            
                            <div class="form-group">
                                <label for="name" class="form-label">Product Name <span class="required">*</span></label>
                                <input type="text" id="name" name="name" class="form-control" required value="<?= s($pn) ?>" placeholder="e.g., Chicken Nuggets">
                                            </div>
                            
                            <div class="form-group">
                                <label for="category_id" class="form-label">Category <span class="required">*</span></label>
                                <select id="category_id" name="category_id" class="form-control" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($db_categories as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= ($category_id == $c['id'] ? 'selected' : '') ?>>
                                            <?= s($c['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                            </div>
                            
                            <div class="form-group">
                                <label for="description" class="form-label">Description</label>
                                <textarea id="description" name="description" class="form-control" rows="3" placeholder="Product description..."><?= s($desc) ?></textarea>
                                            </div>
                            
                            <div class="form-group">
                                <label for="allergies" class="form-label">Allergies</label>
                                <input type="text" id="allergies" name="allergies" class="form-control" value="<?= s($allg) ?>" placeholder="e.g., Contains nuts, dairy">
                                        </div>
                                            </div>
                                            </div>

                    <!-- Image Upload -->
                    <div class="form-card">
                        <div class="card-header">
                            <h3>Product Image</h3>
                            <i class="fas fa-image"></i>
                                        </div>
                        <div class="card-content">
                            <div class="form-group">
                                <label class="form-label">Image Source</label>
                                <div class="radio-group">
                                    <label class="radio-item">
                                        <input type="radio" name="image_source" value="upload" <?= ($image_source === 'upload' ? 'checked' : '') ?>>
                                        <span class="radio-label">Upload Image</span>
                                    </label>
                                    <label class="radio-item">
                                        <input type="radio" name="image_source" value="url" <?= ($image_source === 'url' ? 'checked' : '') ?>>
                                        <span class="radio-label">Image URL</span>
                                    </label>
                            </div>
                        </div>
                            
                            <div id="image_upload_field" class="form-group" style="display: <?= $img_u ?>;">
                                <label for="image_file" class="form-label">Upload Image</label>
                                <input type="file" id="image_file" name="image_file" class="form-control" accept="image/*">
                                <small class="form-text">Max size: 2MB. Supported formats: JPG, PNG, GIF</small>
                                <div id="image_file_preview" class="image-preview"></div>
                    </div>
                            
                            <div id="image_url_field" class="form-group" style="display: <?= $img_ul ?>;">
                                <label for="image_url" class="form-label">Image URL</label>
                                <input type="url" id="image_url" name="image_url" class="form-control" value="<?= s($img_src) ?>" placeholder="https://example.com/image.jpg">
                                <div id="image_url_preview" class="image-preview"></div>
                                    </div>
                                </div>
                            </div>

                    <!-- Product Settings -->
                    <div class="form-card">
                        <div class="card-header">
                            <h3>Product Settings</h3>
                            <i class="fas fa-cog"></i>
                                            </div>
                        <div class="card-content">
                            <div class="checkbox-group">
                                <label class="checkbox-item">
                                    <input type="checkbox" id="is_new_checkbox" name="is_new" <?= $i_new ? 'checked' : '' ?>>
                                    <span class="checkbox-label">Mark as New</span>
                                </label>
                                
                                <label class="checkbox-item">
                                    <input type="checkbox" id="is_offer_checkbox" name="is_offer" <?= $i_off ? 'checked' : '' ?>>
                                    <span class="checkbox-label">Mark as Offer</span>
                                </label>
                                
                                <label class="checkbox-item">
                                    <input type="checkbox" id="is_active_checkbox" name="is_active" <?= $i_act ? 'checked' : '' ?>>
                                    <span class="checkbox-label">Active Product</span>
                                </label>
                                            </div>
                                            </div>
                                        </div>

                    <!-- Pricing & Options -->
                    <div class="form-card">
                        <div class="card-header">
                            <h3>Pricing & Options</h3>
                            <i class="fas fa-tags"></i>
                            </div>
                        <div class="card-content">
                            <div class="checkbox-group">
                                <label class="checkbox-item">
                                    <input type="checkbox" id="extras_sauces_checkbox" name="has_extras_sauces" <?= $has_extras_sauces ? 'checked' : '' ?>>
                                    <span class="checkbox-label">Include Extras & Sauces</span>
                                </label>
                                
                                <label class="checkbox-item">
                                    <input type="checkbox" id="sizes_checkbox" name="has_sizes" <?= $has_sizes ? 'checked' : '' ?>>
                                    <span class="checkbox-label">Multiple Sizes</span>
                                </label>
                                
                                <label class="checkbox-item">
                                    <input type="checkbox" id="dresses_checkbox" name="has_dresses" <?= $has_dresses ? 'checked' : '' ?>>
                                    <span class="checkbox-label">Include Dressings</span>
                                </label>
                                </div>
                            
                            <!-- Base Price (when no sizes) -->
                            <div id="base_price_field" class="form-group" style="display: <?= $has_sizes ? 'none' : 'block' ?>;">
                                <label for="base_price" class="form-label">Base Price ($)</label>
                                <input type="number" id="base_price" name="base_price" class="form-control" step="0.01" value="<?= $base_price ?>" placeholder="9.99">
                        </div>
                    </div>
                            </div>
                            </div>

                <!-- Dynamic Sections -->
                <div id="dynamic_sections">
                    <!-- Extras & Sauces Section -->
                    <div id="extras_sauces_section" class="form-card" style="display: <?= $has_extras_sauces ? 'block' : 'none' ?>;">
                        <div class="card-header">
                            <h3>Extras & Sauces</h3>
                            <i class="fas fa-plus-circle"></i>
                                </div>
                        <div class="card-content">
                            <!-- Global Extras -->
                            <div class="form-group">
                                <label class="form-label">Global Extras</label>
                                <div id="global_extras_container">
                                    <!-- Dynamic extras will be added here -->
                                </div>
                                <button type="button" class="btn btn-secondary btn-sm" id="add_global_extra">
                                    <i class="fas fa-plus"></i> Add Extra
                                </button>
                            </div>
                            
                            <!-- Global Sauces -->
                            <div class="form-group">
                                <label class="form-label">Global Sauces</label>
                                <div id="global_sauces_container">
                                    <!-- Dynamic sauces will be added here -->
                                </div>
                                <button type="button" class="btn btn-secondary btn-sm" id="add_global_sauce">
                                    <i class="fas fa-plus"></i> Add Sauce
                                </button>
                                </div>
                            </div>
                        </div>

                    <!-- Sizes Section -->
                    <div id="sizes_section" class="form-card" style="display: <?= $has_sizes ? 'block' : 'none' ?>;">
                        <div class="card-header">
                            <h3>Product Sizes</h3>
                            <i class="fas fa-ruler"></i>
                    </div>
                        <div class="card-content">
                            <div id="sizeBlocks">
                                <!-- Dynamic size blocks will be added here -->
                                </div>
                            <button type="button" class="btn btn-secondary btn-sm" id="add_size_block">
                                <i class="fas fa-plus"></i> Add Size
                            </button>
                            </div>
                                </div>

                    <!-- Dressings Section -->
                    <div id="dresses_section" class="form-card" style="display: <?= $has_dresses ? 'block' : 'none' ?>;">
                        <div class="card-header">
                            <h3>Dressings</h3>
                            <i class="fas fa-leaf"></i>
                            </div>
                        <div class="card-content">
                            <div id="dressBlocks">
                                <!-- Dynamic dress blocks will be added here -->
                        </div>
                            <button type="button" class="btn btn-secondary btn-sm" id="add_dress_block">
                                <i class="fas fa-plus"></i> Add Dressing
                            </button>
                    </div>
                            </div>
                            </div>

                <!-- Preview Section -->
                <div class="form-card">
                    <div class="card-header">
                        <h3>Product Preview</h3>
                        <i class="fas fa-eye"></i>
                            </div>
                    <div class="card-content">
                        <div id="previewContent" class="preview-content">
                            <!-- Preview will be generated here -->
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                        <a href="products.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= $editing ? 'Update Product' : 'Save Product' ?>
                    </button>
                    </div>
                </form>
            </div>
    <?php endif; ?>
                </div>

<style>
    /* Products Page Styles */
    .products-content {
        padding: 2rem;
        background: var(--content-bg);
        min-height: 100vh;
    }

    /* Page Header */
    .page-header {
        margin-bottom: 2rem;
    }

    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
    }

    .page-title {
        font-size: 1.875rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 0.5rem 0;
    }

    .page-subtitle {
        color: #64748b;
        margin: 0;
        font-size: 1rem;
    }

    .header-actions {
        display: flex;
        gap: 1rem;
    }

    /* Alert Container */
    .alert-container {
        margin-bottom: 2rem;
    }

    .alert {
        padding: 1rem 1.5rem;
        border-radius: 8px;
        border: 1px solid;
        margin-bottom: 1rem;
    }

    .alert-success {
        background: #d1fae5;
        border-color: #10b981;
        color: #065f46;
    }

    .alert-danger {
        background: #fee2e2;
        border-color: #ef4444;
        color: #991b1b;
    }

    /* Table Section */
    .table-section {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        overflow: hidden;
    }

    .table-container {
        overflow-x: auto;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th {
        background: #f8fafc;
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: #374151;
        border-bottom: 1px solid var(--border-color);
        font-size: 0.875rem;
    }

    .data-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--border-color);
        color: #374151;
        vertical-align: middle;
    }

    .data-table tbody tr:hover {
        background: #f8fafc;
    }

    /* Table Cells */
    .image-cell {
        text-align: center;
    }

    .product-image {
        width: 48px;
        height: 48px;
        border-radius: 8px;
        object-fit: cover;
        border: 1px solid var(--border-color);
    }

    .placeholder-image {
        width: 48px;
        height: 48px;
        border-radius: 8px;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
        border: 1px solid var(--border-color);
    }

    .product-code {
        font-family: 'Courier New', monospace;
        font-weight: 600;
        color: #0f172a;
    }

    .product-name {
        font-weight: 600;
        color: #0f172a;
    }

    .category-name {
        color: #64748b;
        font-size: 0.875rem;
    }

    .price-cell {
        font-weight: 600;
        color: #059669;
    }

    /* Badges */
    .badge {
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 500;
        margin-left: 0.5rem;
    }

    .badge-new {
        background: #dbeafe;
        color: #1e40af;
    }

    .badge-offer {
        background: #fef3c7;
        color: #92400e;
    }

    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    .status-active {
        background: #d1fae5;
        color: #065f46;
    }

    .status-inactive {
        background: #fee2e2;
        color: #991b1b;
    }

    /* Action Buttons */
    .actions-cell {
        text-align: center;
    }

    .action-buttons {
        display: flex;
        gap: 0.5rem;
        justify-content: center;
    }

    .btn-edit {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 0.5rem;
        border-radius: 6px;
        transition: all 0.15s ease;
    }

    .btn-edit:hover {
        background: #2563eb;
        color: white;
    }

    .btn-delete {
        background: #ef4444;
        color: white;
        border: none;
        padding: 0.5rem;
        border-radius: 6px;
        transition: all 0.15s ease;
    }

    .btn-delete:hover {
        background: #dc2626;
        color: white;
    }

    /* Empty State */
    .no-data {
        text-align: center;
        padding: 3rem 1rem;
    }

    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
    }

    .empty-state i {
        font-size: 3rem;
        color: #9ca3af;
    }

    .empty-state h3 {
        color: #374151;
        margin: 0;
        font-size: 1.25rem;
    }

    .empty-state p {
        color: #64748b;
        margin: 0;
    }

    /* Form Section */
    .form-section {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    /* Form Cards */
    .form-card {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        overflow: hidden;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        background: #f8fafc;
    }

    .card-header h3 {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 600;
        color: #0f172a;
    }

    .card-header i {
        color: #64748b;
        font-size: 1.25rem;
    }

    .card-content {
        padding: 1.5rem;
    }

    /* Form Groups */
    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group:last-child {
        margin-bottom: 0;
    }

    .form-label {
        display: block;
        font-weight: 500;
        color: #374151;
        font-size: 0.875rem;
        margin-bottom: 0.5rem;
    }

    .required {
        color: #ef4444;
    }

    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        font-size: 0.875rem;
        transition: all 0.15s ease;
    }

    .form-control:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-text {
        font-size: 0.75rem;
        color: #64748b;
        margin-top: 0.25rem;
    }

    /* Radio and Checkbox Groups */
    .radio-group,
    .checkbox-group {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .radio-item,
    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
    }

    .radio-item input,
    .checkbox-item input {
        margin: 0;
    }

    .radio-label,
    .checkbox-label {
        font-size: 0.875rem;
        color: #374151;
    }

    /* Image Preview */
    .image-preview {
        margin-top: 1rem;
    }

    .image-preview img {
        max-width: 200px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
    }

    /* Preview Content */
    .preview-content {
        background: #f8fafc;
        padding: 1.5rem;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        min-height: 200px;
    }

    /* Form Actions */
    .form-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        padding: 1.5rem;
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .products-content {
            padding: 1rem;
        }

        .header-content {
            flex-direction: column;
            gap: 1rem;
            text-align: center;
        }

        .page-title {
            font-size: 1.5rem;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .action-buttons {
            flex-direction: column;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem 0.5rem;
        }
    }
</style>

<!-- JavaScript for Products Page -->
<script>
    $(document).ready(function() {
        // Initialize DataTable for products list
        if ($('#productsTable').length) {
            $('#productsTable').DataTable({
                responsive: true,
                order: [[2, 'asc']], // Sort by name by default
                pageLength: 25,
                language: {
                    search: "Search products:",
                    lengthMenu: "Show _MENU_ products per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ products",
                    emptyTable: "No products found"
                }
            });
        }

        // Delete product functionality
        $('.delete-product-btn').on('click', function() {
            const productId = $(this).data('id');
            const productName = $(this).data('name');
            
            if (confirm(`Are you sure you want to delete "${productName}"? This action cannot be undone.`)) {
                window.location.href = `products.php?action=delete&id=${productId}`;
            }
        });

        // Form functionality for add/edit
        if ($('.product-form').length) {
            // Toggle fields based on checkboxes
            function toggleFields() {
                const extrasSauces = $('#extras_sauces_checkbox').is(':checked');
                const sizes = $('#sizes_checkbox').is(':checked');
                const dresses = $('#dresses_checkbox').is(':checked');
                
                $('#extras_sauces_section').toggle(extrasSauces);
                $('#sizes_section').toggle(sizes);
                $('#dresses_section').toggle(dresses);
                $('#base_price_field').toggle(!sizes);
                
                updatePreview();
            }

            // Checkbox change handlers
            $('#extras_sauces_checkbox, #sizes_checkbox, #dresses_checkbox').on('change', toggleFields);

            // Image source toggle
            $('input[name="image_source"]').on('change', function() {
                const source = $(this).val();
                $('#image_upload_field').toggle(source === 'upload');
                $('#image_url_field').toggle(source === 'url');
                
                if (source === 'upload') {
                    $('input[name="image_url"]').prop('required', false).prop('disabled', true);
                    $('input[name="image_file"]').prop('required', true).prop('disabled', false);
                } else {
                    $('input[name="image_file"]').prop('required', false).prop('disabled', true);
                    $('input[name="image_url"]').prop('required', true).prop('disabled', false);
                }
            }).trigger('change');

            // Image preview functionality
            $('input[name="image_file"]').on('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#image_file_preview').html(`<img src="${e.target.result}" class="img-thumbnail" style="max-width:200px;">`);
                    };
                    reader.readAsDataURL(file);
                } else {
                    $('#image_file_preview').empty();
                }
            });

            $('input[name="image_url"]').on('input', function() {
                const url = $(this).val().trim();
                if (url) {
                    $('#image_url_preview').html(`
                        <img src="${url}" class="img-thumbnail" style="max-width:200px;" 
                             onerror="this.onerror=null; this.remove(); $('#image_url_preview').append('<small class=\'text-danger\'>Invalid image URL</small>');" />
                    `);
                } else {
                    $('#image_url_preview').empty();
                }
            });

            // Dynamic form elements
            let globalExtrasIndex = 0;
            let globalSaucesIndex = 0;
            let sizeBlockIndex = 0;
            let dressBlockIndex = 0;

            // Add global extra
            $('#add_global_extra').on('click', function() {
                const extraHtml = `
                    <div class="extra-item border p-3 mb-2">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="global_extras[${globalExtrasIndex}][name]" placeholder="Extra name" required>
                            </div>
                            <div class="col-md-4">
                                <input type="number" step="0.01" class="form-control" name="global_extras[${globalExtrasIndex}][price]" placeholder="Price" required>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-danger btn-sm remove-extra">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                $('#global_extras_container').append(extraHtml);
                globalExtrasIndex++;
            });

            // Add global sauce
            $('#add_global_sauce').on('click', function() {
                const sauceHtml = `
                    <div class="sauce-item border p-3 mb-2">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="global_sauces[${globalSaucesIndex}][name]" placeholder="Sauce name" required>
                            </div>
                            <div class="col-md-4">
                                <input type="number" step="0.01" class="form-control" name="global_sauces[${globalSaucesIndex}][price]" placeholder="Price" required>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-danger btn-sm remove-sauce">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                $('#global_sauces_container').append(sauceHtml);
                globalSaucesIndex++;
            });

            // Add size block
            $('#add_size_block').on('click', function() {
                const sizeHtml = `
                    <div class="size-block border p-3 mb-3">
  <div class="row g-3 align-items-end">
    <div class="col-md-4">
      <label class="form-label">Size</label>
                                <input type="text" class="form-control" name="sizes_block[${sizeBlockIndex}][size]" required placeholder="Medium / 10 pieces">
    </div>
    <div class="col-md-3">
      <label class="form-label">Base Price ($)</label>
                                <input type="number" step="0.01" class="form-control" name="sizes_block[${sizeBlockIndex}][price]" required placeholder="9.99">
    </div>
    <div class="col-md-3">
      <label class="form-label">Max Sauces</label>
                                <input type="number" class="form-control" name="sizes_block[${sizeBlockIndex}][max_sauces]" min="0" placeholder="1">
    </div>
    <div class="col-md-2 text-end">
                                <button type="button" class="btn btn-danger btn-sm remove-size-block">
                                    <i class="fas fa-minus"></i>
                                </button>
    </div>
  </div>
    </div>
                `;
                $('#sizeBlocks').append(sizeHtml);
                sizeBlockIndex++;
            });

            // Add dress block
            $('#add_dress_block').on('click', function() {
                const dressHtml = `
<div class="dress-block border p-3 mb-3">
  <div class="row g-3 align-items-end">
    <div class="col-md-6">
                                <label class="form-label">Dressing</label>
                                <input type="text" class="form-control" name="dresses[${dressBlockIndex}][dress]" required placeholder="Italian Dressing">
    </div>
    <div class="col-md-4">
      <label class="form-label">Price ($)</label>
                                <input type="number" step="0.01" class="form-control" name="dresses[${dressBlockIndex}][price]" required placeholder="1.50">
    </div>
    <div class="col-md-2 text-end">
                                <button type="button" class="btn btn-danger btn-sm remove-dress-block">
                                    <i class="fas fa-minus"></i>
                                </button>
    </div>
  </div>
                    </div>
                `;
                $('#dressBlocks').append(dressHtml);
                dressBlockIndex++;
            });

            // Remove dynamic elements
            $(document).on('click', '.remove-extra', function() {
                $(this).closest('.extra-item').remove();
            });

            $(document).on('click', '.remove-sauce', function() {
                $(this).closest('.sauce-item').remove();
            });

            $(document).on('click', '.remove-size-block', function() {
                $(this).closest('.size-block').remove();
            });

            $(document).on('click', '.remove-dress-block', function() {
                $(this).closest('.dress-block').remove();
            });

            // Preview functionality
            function updatePreview() {
                const productName = $('input[name="name"]').val() || 'Product Name';
                const productCode = $('input[name="product_code"]').val() || 'CODE';
                const categoryName = $('select[name="category_id"] option:selected').text() || 'Category';
                const description = $('textarea[name="description"]').val() || '';
                const allergies = $('input[name="allergies"]').val() || '';
                const isNew = $('#is_new_checkbox').is(':checked');
                const isOffer = $('#is_offer_checkbox').is(':checked');
                const isActive = $('#is_active_checkbox').is(':checked');
                const hasSizes = $('#sizes_checkbox').is(':checked');
                const basePrice = $('input[name="base_price"]').val() || '0.00';

                let preview = `
                    <div class="preview-item">
                        <h4>${productName}</h4>
                        <p><strong>Code:</strong> ${productCode}</p>
                        <p><strong>Category:</strong> ${categoryName}</p>
                        ${description ? `<p><strong>Description:</strong> ${description}</p>` : ''}
                        ${allergies ? `<p><strong>Allergies:</strong> ${allergies}</p>` : ''}
                        <div class="preview-badges">
                            ${isNew ? '<span class="badge badge-new">New</span>' : ''}
                            ${isOffer ? '<span class="badge badge-offer">Offer</span>' : ''}
                            <span class="status-badge status-${isActive ? 'active' : 'inactive'}">${isActive ? 'Active' : 'Inactive'}</span>
                        </div>
                        ${hasSizes ? '<p><strong>Pricing:</strong> Multiple sizes available</p>' : `<p><strong>Price:</strong> $${basePrice}</p>`}
                    </div>
                `;

                $('#previewContent').html(preview);
            }

            // Update preview on form changes
            $('input, select, textarea').on('input change', updatePreview);
            
            // Initial preview
        updatePreview();
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>