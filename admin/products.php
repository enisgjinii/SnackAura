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

<div class="container my-5">
    <?php
    if ($adding) echo '<h2 class="mb-4 text-primary">Add New Product</h2>';
    elseif ($editing) echo '<h2 class="mb-4 text-primary">Edit Product</h2>';
    echo $message;
    if ($adding || $editing) {
    ?>
        <div class="row">
            <div class="col-lg-8">
                <form method="POST" enctype="multipart/form-data" action="products.php?action=<?= $editing ? 'edit&id=' . $id : 'add' ?>">
                    <div class="card mb-4">
                        <div class="card-header bg-light fw-bold">Basic Information</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Product Code <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-id-badge"></i></span>
                                    <input type="text" class="form-control" name="product_code" required value="<?= s($pc) ?>" placeholder="e.g., P1234">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-heading"></i></span>
                                    <input type="text" class="form-control" name="name" required value="<?= s($pn) ?>" placeholder="e.g., Chicken Nuggets">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Category <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-list"></i></span>
                                    <select class="form-select" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php
                                        foreach ($db_categories as $c) {
                                            echo '<option value="' . $c['id'] . '" ' . ($category_id == $c['id'] ? 'selected' : '') . '>' . s($c['name']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-4">
                        <div class="card-header bg-light fw-bold">Product Options</div>
                        <div class="card-body">
                            <div class="row g-3 align-items-center">
                                <div class="col-auto">
                                    <input class="form-check-input" type="checkbox" name="has_extras_sauces" id="extras_sauces_checkbox" <?= $has_extras_sauces ? 'checked' : '' ?>>
                                    <label class="form-check-label ms-1" for="extras_sauces_checkbox">Has Extras & Sauces</label>
                                </div>
                                <div class="col-auto">
                                    <input class="form-check-input" type="checkbox" name="has_sizes" id="sizes_checkbox" <?= $has_sizes ? 'checked' : '' ?>>
                                    <label class="form-check-label ms-1" for="sizes_checkbox">Has Sizes</label>
                                </div>
                                <div class="col-auto">
                                    <input class="form-check-input" type="checkbox" name="has_dresses" id="dresses_checkbox" <?= $has_dresses ? 'checked' : '' ?>>
                                    <label class="form-check-label ms-1" for="dresses_checkbox">Has Dresses</label>
                                </div>
                            </div>
                            <div id="basePriceField" style="display:<?= $has_sizes ? 'none' : 'block' ?>;" class="mt-4">
                                <label class="form-label fw-semibold">Base Price (USD)</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" class="form-control" name="base_price" value="<?= s($base_price) ?>" placeholder="e.g., 6.99">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-4" id="extrasSaucesFields" style="display:<?= $has_extras_sauces ? 'block' : 'none' ?>;">
                        <div class="card-header bg-light fw-bold">Global Extras & Sauces</div>
                        <div class="card-body">
                            <p class="small text-muted">These apply to all sizes or to the base product if no sizes.</p>
                            <div id="baseLimitsFields" style="display:none;">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Max Sauces (Base)</label>
                                        <input type="number" class="form-control" name="max_sauces_base" min="0"
                                            value="<?= s($props['max_sauces_base'] ?? ($_POST['max_sauces_base'] ?? 0)) ?>" placeholder="e.g., 2" />
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Max Extras (Base)</label>
                                        <input type="number" class="form-control" name="max_extras_base" min="0"
                                            value="<?= s($props['max_extras_base'] ?? ($_POST['max_extras_base'] ?? 0)) ?>" placeholder="e.g., 3" />
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Extras</h6>
                                    <div id="globalExtrasContainer">
                                        <?php
                                        if (($adding || $editing) && isset($props['extras'])) {
                                            foreach ($props['extras'] as $ei => $ev) {
                                                echo '<div class="row g-2 mb-2 global-extra">
                                                <div class="col">
                                                    <input type="text" class="form-control" name="global_extras[' . $ei . '][name]" value="' . s($ev['name']) . '" placeholder="Extra name">
                                                </div>
                                                <div class="col">
                                                    <input type="number" step="0.01" class="form-control" name="global_extras[' . $ei . '][price]" value="' . s($ev['price']) . '" placeholder="Price">
                                                </div>
                                                <div class="col-auto">
                                                    <button type="button" class="btn btn-sm btn-danger remove-global-extra">&times;</button>
                                                </div>
                                            </div>';
                                            }
                                        }
                                        if (($adding || $editing) && !empty($extrasList)) {
                                            echo '<div class="mb-3">
                                            <label class="text-muted">Add from existing Extras:</label>
                                            <select class="form-select existing-extras-select" multiple>';
                                            foreach ($extrasList as $ue) {
                                                echo '<option value="' . s($ue['name']) . '">' . s($ue['name']) . ' ($' . s($ue['price']) . ')</option>';
                                            }
                                            echo '</select>
                                        </div>';
                                        }
                                        ?>
                                    </div>
                                    <button type="button" class="btn btn-primary btn-sm" id="addGlobalExtra">Add Extra</button>
                                </div>
                                <div class="col-md-6">
                                    <h6>Sauces</h6>
                                    <div id="globalSaucesContainer">
                                        <?php
                                        if (($adding || $editing) && isset($props['sauces'])) {
                                            foreach ($props['sauces'] as $si => $sv) {
                                                echo '<div class="row g-2 mb-2 global-sauce">
                                                <div class="col">
                                                    <input type="text" class="form-control" name="global_sauces[' . $si . '][name]" value="' . s($sv['name']) . '" placeholder="Sauce name">
                                                </div>
                                                <div class="col">
                                                    <input type="number" step="0.01" class="form-control" name="global_sauces[' . $si . '][price]" value="' . s($sv['price']) . '" placeholder="Price">
                                                </div>
                                                <div class="col-auto">
                                                    <button type="button" class="btn btn-sm btn-danger remove-global-sauce">&times;</button>
                                                </div>
                                            </div>';
                                            }
                                        }
                                        if (($adding || $editing) && !empty($saucesList)) {
                                            echo '<div class="mb-3">
                                            <label class="text-muted">Add from existing Sauces:</label>
                                            <select class="form-select existing-sauces-select" multiple>';
                                            foreach ($saucesList as $us) {
                                                echo '<option value="' . s($us['name']) . '">' . s($us['name']) . ' ($' . s($us['price']) . ')</option>';
                                            }
                                            echo '</select>
                                        </div>';
                                        }
                                        ?>
                                    </div>
                                    <button type="button" class="btn btn-primary btn-sm" id="addGlobalSauce">Add Sauce</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-4" id="sizesFields" style="display:<?= $has_sizes ? 'block' : 'none' ?>;">
                        <div class="card-header bg-light fw-bold">Sizes</div>
                        <div class="card-body">
                            <p class="small text-muted">Define multiple sizes, their base price, etc.</p>
                            <div id="sizeBlocks">
                                <?php
                                if ($editing && isset($props['sizes']) && is_array($props['sizes'])) {
                                    foreach ($props['sizes'] as $sz_id => $szb) {
                                        $sz_name = s($szb['size']);
                                        $sz_price = (float)$szb['price'];
                                        $sz_maxSauce = isset($szb['max_sauces']) ? (int)$szb['max_sauces'] : 0;
                                        echo '<div class="size-block border p-3 mb-3" data-block-index="' . $sz_id . '">
                                        <div class="row g-3 align-items-end">
                                            <div class="col-md-4">
                                                <label class="form-label">Size</label>
                                                <input type="text" class="form-control" name="sizes_block[' . $sz_id . '][size]" value="' . $sz_name . '" required placeholder="Medium / 10 pieces">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Base Price ($)</label>
                                                <input type="number" step="0.01" class="form-control" name="sizes_block[' . $sz_id . '][price]" value="' . $sz_price . '" required placeholder="9.99">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Max Sauces</label>
                                                <input type="number" class="form-control" name="sizes_block[' . $sz_id . '][max_sauces]" value="' . $sz_maxSauce . '" min="0" placeholder="1">
                                            </div>
                                            <div class="col-md-2 text-end">
                                                <button type="button" class="btn btn-danger remove-size-block"><i class="fas fa-minus-circle"></i></button>
                                            </div>
                                        </div>
                                        <div class="row g-3 mt-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Extras</label>
                                                <select class="form-select extras-select" name="sizes_block[' . $sz_id . '][extras_selected][]" multiple></select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Sauces</label>
                                                <select class="form-select sauces-select" name="sizes_block[' . $sz_id . '][sauces_selected][]" multiple></select>
                                            </div>
                                        </div>
                                        <div class="extras-prices mt-3"></div>
                                        <div class="sauces-prices mt-3"></div>
                                    </div>';
                                    }
                                }
                                ?>
                            </div>
                            <button type="button" class="btn btn-success btn-sm" id="addSizeBlock"><i class="fas fa-plus-circle"></i> Add Size</button>
                        </div>
                    </div>
                    <div class="card mb-4" id="dressesFields" style="display:<?= $has_dresses ? 'block' : 'none' ?>;">
                        <div class="card-header bg-light fw-bold">Dresses</div>
                        <div class="card-body">
                            <p class="small text-muted">Additional dress options.</p>
                            <div id="baseDressLimit" style="display:none;">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Max Dressings (Base)</label>
                                        <input type="number" class="form-control" name="max_dresses_base" min="0"
                                            value="<?= s($props['max_dresses_base'] ?? ($_POST['max_dresses_base'] ?? 0)) ?>" placeholder="e.g., 1" />
                                    </div>
                                </div>
                            </div>
                            <div id="dressBlocks">
                                <?php
                                if ($editing && isset($props['dresses']) && is_array($props['dresses'])) {
                                    foreach ($props['dresses'] as $db_i => $db) {
                                        $dname = s($db['name']);
                                        $dprice = (float)$db['price'];
                                        echo '<div class="dress-block border p-3 mb-3">
                                        <div class="row g-3 align-items-end">
                                            <div class="col-md-6">
                                                <label class="form-label">Dress</label>
                                                <input type="text" class="form-control" name="dresses[' . $db_i . '][dress]" value="' . $dname . '" required placeholder="Italian Dressing">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Price ($)</label>
                                                <input type="number" step="0.01" class="form-control" name="dresses[' . $db_i . '][price]" value="' . $dprice . '" required placeholder="1.50">
                                            </div>
                                            <div class="col-md-2 text-end">
                                                <button type="button" class="btn btn-danger remove-dress-block"><i class="fas fa-minus-circle"></i></button>
                                            </div>
                                        </div>
                                    </div>';
                                    }
                                }
                                ?>
                            </div>
                            <?php if (($adding || $editing) && !empty($dressesList)): ?>
                                <div class="mb-3">
                                    <label class="text-muted">Add from existing Dresses:</label>
                                    <select class="form-select existing-dresses-select" multiple>
                                        <?php foreach ($dressesList as $ud): ?>
                                            <option value="<?= s($ud['name']) ?>">
                                                <?= s($ud['name']) ?> ($<?= s($ud['price']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <button type="button" class="btn btn-success btn-sm" id="addDressBlock"><i class="fas fa-plus-circle"></i> Add Dress</button>
                        </div>
                    </div>
                    <div class="card mb-4">
                        <div class="card-header bg-light fw-bold">Image</div>
                        <div class="card-body">
                            <label class="form-label d-block">Image Source <span class="text-danger">*</span></label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="image_source" value="upload" <?= $image_source === 'upload' ? 'checked' : '' ?>>
                                <label class="form-check-label">Upload</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="image_source" value="url" <?= $image_source === 'url' ? 'checked' : '' ?>>
                                <label class="form-check-label">URL</label>
                            </div>
                            <div class="mt-3" id="image_upload_field" style="display:<?= $img_u ?>;">
                                <label class="form-label">Upload Image</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-upload"></i></span>
                                    <input type="file" class="form-control" name="image_file" accept="image/*">
                                </div>
                                <div id="image_file_preview" class="mt-2">
                                    <?php
                                    if ($editing && !empty($product['image_url']) && strpos($product['image_url'], UPLOAD_DIR) === 0) {
                                        echo '<img src="' . s($product['image_url']) . '" class="img-thumbnail" style="max-width:200px;">';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="mt-3" id="image_url_field" style="display:<?= $img_ul ?>;">
                                <label class="form-label">Image URL</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-image"></i></span>
                                    <input type="url" class="form-control" name="image_url" placeholder="https://example.com/img.jpg" value="<?= s($img_src) ?>">
                                </div>
                                <div id="image_url_preview" class="mt-2">
                                    <?php
                                    if ($editing && !empty($product['image_url']) && strpos($product['image_url'], UPLOAD_DIR) !== 0) {
                                        echo '<img src="' . s($product['image_url']) . '" class="img-thumbnail" style="max-width:200px;">';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-4">
                        <div class="card-header bg-light fw-bold">Allergies & Description</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Allergies</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-exclamation-triangle"></i></span>
                                    <input type="text" class="form-control" name="allergies" value="<?= s($allg) ?>" placeholder="e.g., Contains Nuts">
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Description</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-align-left"></i></span>
                                    <textarea class="form-control" name="description" rows="3"><?= s($desc) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-4">
                        <div class="card-header bg-light fw-bold">Additional Flags</div>
                        <div class="card-body">
                            <div class="form-check mb-2">
                                <input type="checkbox" class="form-check-input" name="is_new" id="is_new_checkbox" <?= $i_new ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_new_checkbox">New</label>
                            </div>
                            <div class="form-check mb-2">
                                <input type="checkbox" class="form-check-input" name="is_offer" id="is_offer_checkbox" <?= $i_off ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_offer_checkbox">Offer</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_active" id="is_active_checkbox" <?= $i_act ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active_checkbox">Active</label>
                            </div>
                        </div>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-success me-2"><?= $editing ? 'Update' : 'Add' ?> Product</button>
                        <a href="products.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
            <div class="col-lg-4">
                <div class="card mb-4" id="previewBox">
                    <div class="card-header bg-primary text-white fw-bold">Live Preview</div>
                    <div class="card-body" id="previewContent">No data yet...</div>
                </div>
            </div>
        </div>
    <?php
    } elseif ($viewing) {
        $products = fetchAll(
            $pdo,
            'SELECT p.*, c.name as category_name
             FROM products p
             JOIN categories c ON p.category_id=c.id
             ORDER BY p.created_at DESC'
        );
        echo '<h2 class="mb-4 text-primary">Manage Products</h2>';
        echo $_SESSION['message'] ?? '';
        unset($_SESSION['message']);
        echo '<div class="mb-3">
                <a href="products.php?action=add" class="btn btn-success"><i class="fas fa-plus-circle"></i> Add New Product</a>
              </div>';
        echo '<div class="table-responsive">
                <table class="table table-striped table-hover table-bordered align-middle">
                  <thead class="table-dark">
                    <tr>
                      <th>ID</th>
                      <th>Code</th>
                      <th>Name</th>
                      <th>Category</th>
                      <th>Details</th>
                      <th>Allergies</th>
                      <th>Description</th>
                      <th>Image</th>
                      <th>New</th>
                      <th>Offer</th>
                      <th>Status</th>
                      <th>Created</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>';
        foreach ($products as $p) {
            $pr = json_decode($p['properties'], true);
            echo '<tr>
                    <td>' . s($p['id']) . '</td>
                    <td>' . s($p['product_code']) . '</td>
                    <td>' . s($p['name']) . '</td>
                    <td>' . s($p['category_name']) . '</td>
                    <td>';
            if (!empty($pr['sizes'])) {
                foreach ($pr['sizes'] as $sz) {
                    echo '<strong>Size:</strong> ' . s($sz['size']) . ' - $' . number_format($sz['price'], 2) . '<br>';
                    if (isset($sz['max_sauces'])) {
                        echo '&nbsp;&nbsp;<em>Max Sauces:</em> ' . (int)$sz['max_sauces'] . '<br>';
                    }
                    if (!empty($sz['extras'])) {
                        echo '&nbsp;&nbsp;<strong>Extras:</strong> ';
                        $exarr = [];
                        foreach ($sz['extras'] as $ex) {
                            $exarr[] = $ex['name'] . ' ($' . number_format($ex['price'], 2) . ')';
                        }
                        echo implode(', ', $exarr) . '<br>';
                    }
                    if (!empty($sz['sauces'])) {
                        echo '&nbsp;&nbsp;<strong>Sauces:</strong> ';
                        $saarr = [];
                        foreach ($sz['sauces'] as $sa) {
                            $saarr[] = $sa['name'] . ' ($' . number_format($sa['price'], 2) . ')';
                        }
                        echo implode(', ', $saarr) . '<br>';
                    }
                }
            } else {
                if (isset($pr['base_price'])) {
                    echo '<strong>Base Price:</strong> $' . number_format($pr['base_price'], 2) . '<br>';
                }
                if (isset($pr['max_sauces_base'])) {
                    echo '<strong>Max Sauces (Base):</strong> ' . (int)$pr['max_sauces_base'] . '<br>';
                }
                if (isset($pr['max_extras_base'])) {
                    echo '<strong>Max Extras (Base):</strong> ' . (int)$pr['max_extras_base'] . '<br>';
                }
                if (isset($pr['max_dresses_base'])) {
                    echo '<strong>Max Dressings (Base):</strong> ' . (int)$pr['max_dresses_base'] . '<br>';
                }
            }
            if (!empty($pr['extras']) && empty($pr['sizes'])) {
                echo '<strong>Extras:</strong> ';
                $exarr = [];
                foreach ($pr['extras'] as $ex) {
                    $exarr[] = $ex['name'] . ' ($' . number_format($ex['price'], 2) . ')';
                }
                echo implode(', ', $exarr) . '<br>';
            }
            if (!empty($pr['sauces']) && empty($pr['sizes'])) {
                echo '<strong>Sauces:</strong> ';
                $saarr = [];
                foreach ($pr['sauces'] as $sa) {
                    $saarr[] = $sa['name'] . ' ($' . number_format($sa['price'], 2) . ')';
                }
                echo implode(', ', $saarr) . '<br>';
            }
            if (!empty($pr['dresses'])) {
                foreach ($pr['dresses'] as $d) {
                    echo '<strong>Dress:</strong> ' . s($d['name']) . ' - $' . number_format($d['price'], 2) . '<br>';
                }
            }
            echo '</td>
                  <td>' . (s($p['allergies']) ?: '-') . '</td>
                  <td>' . (s($p['description']) ?: '-') . '</td>
                  <td>';
            if (!empty($p['image_url'])) {
                echo '<img src="' . s($p['image_url']) . '" class="img-thumbnail" style="max-width:100px;">';
            } else {
                echo '-';
            }
            echo '</td>
                  <td>' . ($p['is_new'] ? '<span class="badge bg-success"><i class="fas fa-star"></i></span>' : '-') . '</td>
                  <td>' . ($p['is_offer'] ? '<span class="badge bg-warning text-dark"><i class="fas fa-tags"></i></span>' : '-') . '</td>
                  <td>' . ($p['is_active'] ? '<span class="badge bg-primary"><i class="fas fa-check-circle"></i></span>' : '<span class="badge bg-secondary"><i class="fas fa-times-circle"></i></span>') . '</td>
                  <td>' . s($p['created_at']) . '</td>
                  <td>
                    <a href="products.php?action=edit&id=' . $p['id'] . '" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i> Edit</a>
                    <a href="products.php?action=delete&id=' . $p['id'] . '" class="btn btn-danger btn-sm"><i class="fas fa-trash-alt"></i> Delete</a>
                  </td>
                </tr>';
        }
        echo '</tbody></table></div>';
    } elseif ($deleting) {
        if ($id > 0) {
            $user_id = $_SESSION['user_id'] ?? null;
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
                $stmt->execute([$id]);
                $old_product = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($old_product) {
                    $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
                    if (!empty($old_product['image_url']) && strpos($old_product['image_url'], UPLOAD_DIR) === 0 && file_exists($old_product['image_url'])) {
                        unlink($old_product['image_url']);
                    }
                    $stmt_audit = $pdo->prepare('INSERT INTO product_audit (product_id, action, changed_by, old_values, new_values) VALUES (?, "delete", ?, ?, NULL)');
                    $stmt_audit->execute([
                        $id,
                        $user_id,
                        json_encode($old_product)
                    ]);
                    $pdo->commit();
                    $_SESSION['message'] = '<div class="alert alert-success">Deleted successfully.</div>';
                    header('Location: products.php');
                    exit();
                } else {
                    throw new Exception('Product not found.');
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = '<div class="alert alert-danger">Error deleting: ' . s($e->getMessage()) . '</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Invalid product ID.</div>';
        }
        echo '<h2 class="mb-4">Delete Product</h2>' . $message . '<a href="products.php" class="btn btn-secondary">Return</a>';
    }
    ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        var sizeBlockCounter = 1000;

        function toggleFields() {
            var hasExtrasSauces = $('#extras_sauces_checkbox').is(':checked');
            var hasSizes = $('#sizes_checkbox').is(':checked');
            var hasDresses = $('#dresses_checkbox').is(':checked');
            $('#extrasSaucesFields').toggle(hasExtrasSauces);
            $('#sizesFields').toggle(hasSizes);
            $('#dressesFields').toggle(hasDresses);
            $('#basePriceField').toggle(!hasSizes);
            if (hasExtrasSauces && !hasSizes) {
                $('#baseLimitsFields').show();
            } else {
                $('#baseLimitsFields').hide();
            }
            if (hasDresses && !hasSizes) {
                $('#baseDressLimit').show();
            } else {
                $('#baseDressLimit').hide();
            }
            updatePreview();
        }
        $('input[name="has_extras_sauces"], input[name="has_sizes"], input[name="has_dresses"]').change(function() {
            toggleFields();
            populateExtrasSaucesSelects();
            refreshAllPrices();
        });

        function getUniqueExtras() {
            var globalExtras = [];
            $('#globalExtrasContainer .global-extra').each(function() {
                var en = $(this).find('input[name*="name"]').val() || '';
                var ep = $(this).find('input[name*="price"]').val() || 0;
                if (en.trim() !== '') {
                    globalExtras.push({
                        name: en,
                        price: ep
                    });
                }
            });
            return globalExtras;
        }

        function getUniqueSauces() {
            var globalSauces = [];
            $('#globalSaucesContainer .global-sauce').each(function() {
                var sn = $(this).find('input[name*="name"]').val() || '';
                var sp = $(this).find('input[name*="price"]').val() || 0;
                if (sn.trim() !== '') {
                    globalSauces.push({
                        name: sn,
                        price: sp
                    });
                }
            });
            return globalSauces;
        }

        function getUniqueDresses() {
            var globalDresses = [];
            $('#dressBlocks .dress-block').each(function() {
                var dn = $(this).find('input[name*="dress"]').val() || '';
                var dp = $(this).find('input[name*="price"]').val() || 0;
                if (dn.trim() !== '') {
                    globalDresses.push({
                        name: dn,
                        price: dp
                    });
                }
            });
            return globalDresses;
        }

        function populateExtrasSaucesSelects() {
            var globalExtras = getUniqueExtras();
            var globalSauces = getUniqueSauces();
            $('#sizeBlocks .size-block').each(function() {
                var $block = $(this);
                var $extrasSelect = $block.find('.extras-select');
                var $saucesSelect = $block.find('.sauces-select');
                var extrasVal = $extrasSelect.val() || [];
                var saucesVal = $saucesSelect.val() || [];
                $extrasSelect.empty();
                $saucesSelect.empty();
                $.each(globalExtras, function(i, ex) {
                    $extrasSelect.append('<option value="' + i + '">' + ex.name + '</option>');
                });
                $.each(globalSauces, function(i, sa) {
                    $saucesSelect.append('<option value="' + i + '">' + sa.name + '</option>');
                });
                $extrasSelect.val(extrasVal).trigger('change');
                $saucesSelect.val(saucesVal).trigger('change');
            });
        }
        $(document).on('change', '.extras-select', function() {
            updateSizeExtrasPrices($(this).closest('.size-block'));
        });
        $(document).on('change', '.sauces-select', function() {
            updateSizeSaucesPrices($(this).closest('.size-block'));
        });

        function getSizeBlockName($block) {
            var firstInput = $block.find('input[name^="sizes_block["]').first();
            return firstInput.attr('name').replace(/\[size\]$/, '');
        }

        function updateSizeExtrasPrices($block) {
            var globalExtras = getUniqueExtras();
            var selectedExtras = $block.find('.extras-select').val() || [];
            var $extrasPrices = $block.find('.extras-prices').empty();
            $.each(selectedExtras, function(_, exIndex) {
                exIndex = parseInt(exIndex, 10);
                if (globalExtras[exIndex]) {
                    var blockName = getSizeBlockName($block);
                    $extrasPrices.append('<div class="row g-2 mb-2"><div class="col-md-6"><label>' + globalExtras[exIndex].name + ' Price ($)</label><input type="number" step="0.01" class="form-control" name="' + blockName + '[extras_prices][' + exIndex + ']" value="' + globalExtras[exIndex].price + '"></div></div>');
                }
            });
            updatePreview();
        }

        function updateSizeSaucesPrices($block) {
            var globalSauces = getUniqueSauces();
            var selectedSauces = $block.find('.sauces-select').val() || [];
            var $saucesPrices = $block.find('.sauces-prices').empty();
            $.each(selectedSauces, function(_, saIndex) {
                saIndex = parseInt(saIndex, 10);
                if (globalSauces[saIndex]) {
                    var blockName = getSizeBlockName($block);
                    $saucesPrices.append('<div class="row g-2 mb-2"><div class="col-md-6"><label>' + globalSauces[saIndex].name + ' Price ($)</label><input type="number" step="0.01" class="form-control" name="' + blockName + '[sauces_prices][' + saIndex + ']" value="' + globalSauces[saIndex].price + '"></div></div>');
                }
            });
            updatePreview();
        }
        $('#addSizeBlock').click(function() {
            var index = sizeBlockCounter++;
            $('#sizeBlocks').append(renderSizeBlock(index));
            $('.extras-select, .sauces-select').select2({
                width: '100%'
            });
            populateExtrasSaucesSelects();
            updatePreview();
        });
        $(document).on('click', '.remove-size-block', function() {
            $(this).closest('.size-block').remove();
            updatePreview();
        });
        $(document).on('click', '.remove-dress-block', function() {
            $(this).closest('.dress-block').remove();
            updatePreview();
        });
        $(document).on('click', '.remove-global-extra', function() {
            $(this).closest('.global-extra').remove();
            populateExtrasSaucesSelects();
            refreshAllPrices();
            updatePreview();
        });
        $(document).on('click', '.remove-global-sauce', function() {
            $(this).closest('.global-sauce').remove();
            populateExtrasSaucesSelects();
            refreshAllPrices();
            updatePreview();
        });
        $('#addDressBlock').click(function() {
            $('#dressBlocks').append(renderDressBlock());
            $('.extras-select, .sauces-select').select2({
                width: '100%'
            });
            populateExtrasSaucesSelects();
            updatePreview();
        });
        $('#addGlobalExtra').click(function() {
            var c = $('#globalExtrasContainer .global-extra').length;
            $('#globalExtrasContainer').append(`
<div class="row g-2 mb-2 global-extra">
  <div class="col">
    <input type="text" class="form-control" name="global_extras[${c}][name]" placeholder="Extra name">
  </div>
  <div class="col">
    <input type="number" step="0.01" class="form-control" name="global_extras[${c}][price]" placeholder="Price">
  </div>
  <div class="col-auto">
    <button type="button" class="btn btn-sm btn-danger remove-global-extra">&times;</button>
  </div>
</div>`);
            populateExtrasSaucesSelects();
            refreshAllPrices();
            updatePreview();
        });
        $('#addGlobalSauce').click(function() {
            var c = $('#globalSaucesContainer .global-sauce').length;
            $('#globalSaucesContainer').append(`
<div class="row g-2 mb-2 global-sauce">
  <div class="col">
    <input type="text" class="form-control" name="global_sauces[${c}][name]" placeholder="Sauce name">
  </div>
  <div class="col">
    <input type="number" step="0.01" class="form-control" name="global_sauces[${c}][price]" placeholder="Price">
  </div>
  <div class="col-auto">
    <button type="button" class="btn btn-sm btn-danger remove-global-sauce">&times;</button>
  </div>
</div>`);
            populateExtrasSaucesSelects();
            refreshAllPrices();
            updatePreview();
        });
        $('.existing-extras-select').change(function() {
            var selectedExtras = $(this).val();
            if (selectedExtras) {
                selectedExtras.forEach(function(extraName) {
                    var exists = false;
                    $('#globalExtrasContainer .global-extra').each(function() {
                        if ($(this).find('input[name*="[name]"]').val() === extraName) {
                            exists = true;
                            return false;
                        }
                    });
                    if (!exists) {
                        var index = $('#globalExtrasContainer .global-extra').length;
                        var optionText = $('.existing-extras-select option[value="' + extraName + '"]').text();
                        var priceMatch = optionText.match(/\$\d+(\.\d{1,2})?/);
                        var price = priceMatch ? priceMatch[0].replace('$', '') : '';
                        $('#globalExtrasContainer').append(`
<div class="row g-2 mb-2 global-extra">
  <div class="col">
    <input type="text" class="form-control" name="global_extras[${index}][name]" value="${extraName}" placeholder="Extra name">
  </div>
  <div class="col">
    <input type="number" step="0.01" class="form-control" name="global_extras[${index}][price]" value="${price}" placeholder="Price">
  </div>
  <div class="col-auto">
    <button type="button" class="btn btn-sm btn-danger remove-global-extra">&times;</button>
  </div>
</div>`);
                    }
                });
                $(this).val(null).trigger('change');
                populateExtrasSaucesSelects();
                refreshAllPrices();
                updatePreview();
            }
        });
        $('.existing-sauces-select').change(function() {
            var selectedSauces = $(this).val();
            if (selectedSauces) {
                selectedSauces.forEach(function(sauceName) {
                    var exists = false;
                    $('#globalSaucesContainer .global-sauce').each(function() {
                        if ($(this).find('input[name*="[name]"]').val() === sauceName) {
                            exists = true;
                            return false;
                        }
                    });
                    if (!exists) {
                        var index = $('#globalSaucesContainer .global-sauce').length;
                        var optionText = $('.existing-sauces-select option[value="' + sauceName + '"]').text();
                        var priceMatch = optionText.match(/\$\d+(\.\d{1,2})?/);
                        var price = priceMatch ? priceMatch[0].replace('$', '') : '';
                        $('#globalSaucesContainer').append(`
<div class="row g-2 mb-2 global-sauce">
  <div class="col">
    <input type="text" class="form-control" name="global_sauces[${index}][name]" value="${sauceName}" placeholder="Sauce name">
  </div>
  <div class="col">
    <input type="number" step="0.01" class="form-control" name="global_sauces[${index}][price]" value="${price}" placeholder="Price">
  </div>
  <div class="col-auto">
    <button type="button" class="btn btn-sm btn-danger remove-global-sauce">&times;</button>
  </div>
</div>`);
                    }
                });
                $(this).val(null).trigger('change');
                populateExtrasSaucesSelects();
                refreshAllPrices();
                updatePreview();
            }
        });
        $('.existing-dresses-select').change(function() {
            var selectedDresses = $(this).val();
            if (selectedDresses) {
                selectedDresses.forEach(function(dressName) {
                    var exists = false;
                    $('#dressBlocks .dress-block').each(function() {
                        if ($(this).find('input[name*="[dress]"]').val() === dressName) {
                            exists = true;
                            return false;
                        }
                    });
                    if (!exists) {
                        var index = $('#dressBlocks .dress-block').length;
                        var optionText = $('.existing-dresses-select option[value="' + dressName + '"]').text();
                        var priceMatch = optionText.match(/\$\d+(\.\d{1,2})?/);
                        var price = priceMatch ? priceMatch[0].replace('$', '') : '';
                        $('#dressBlocks').append(`
<div class="dress-block border p-3 mb-3">
  <div class="row g-3 align-items-end">
    <div class="col-md-6">
      <label class="form-label">Dress</label>
      <input type="text" class="form-control" name="dresses[${index}][dress]" required value="${dressName}" placeholder="Italian Dressing">
    </div>
    <div class="col-md-4">
      <label class="form-label">Price ($)</label>
      <input type="number" step="0.01" class="form-control" name="dresses[${index}][price]" required value="${price}" placeholder="1.50">
    </div>
    <div class="col-md-2 text-end">
      <button type="button" class="btn btn-danger remove-dress-block"><i class="fas fa-minus-circle"></i></button>
    </div>
  </div>
</div>`);
                    }
                });
                $(this).val(null).trigger('change');
                populateExtrasSaucesSelects();
                refreshAllPrices();
                updatePreview();
            }
        });

        function refreshAllPrices() {
            $('#sizeBlocks .size-block').each(function() {
                updateSizeExtrasPrices($(this));
                updateSizeSaucesPrices($(this));
            });
        }
        $('input[name="image_source"]').change(function() {
            var v = $(this).val();
            $('#image_upload_field').toggle(v === 'upload');
            $('#image_url_field').toggle(v === 'url');
            if (v === 'upload') {
                $('input[name="image_url"]').prop('required', false).prop('disabled', true);
                $('input[name="image_file"]').prop('required', true).prop('disabled', false);
            } else {
                $('input[name="image_file"]').prop('required', false).prop('disabled', true);
                $('input[name="image_url"]').prop('required', true).prop('disabled', false);
            }
        }).trigger('change');
        $('.extras-select, .sauces-select').select2({
            placeholder: "Select options",
            width: '100%'
        });
        $('.existing-extras-select, .existing-sauces-select, .existing-dresses-select').select2({
            placeholder: "Select options",
            width: '100%'
        });
        $(document).on('change', 'input[name="image_file"]', function() {
            var input = this;
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#image_file_preview').html('<img src="' + e.target.result + '" class="img-thumbnail" style="max-width:200px;">');
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                $('#image_file_preview').empty();
            }
        });
        $(document).on('input', 'input[name="image_url"]', function() {
            var url = $(this).val().trim();
            if (url !== '') {
                $('#image_url_preview').html(`
<img src="${url}" class="img-thumbnail" style="max-width:200px;" onerror="this.onerror=null; this.remove(); $('#image_url_preview').append('<small class=\'text-danger\'>Invalid image URL</small>');" />
`);
            } else {
                $('#image_url_preview').empty();
            }
        });

        function updatePreview() {
            var extrasSauces = $('#extras_sauces_checkbox').is(':checked');
            var sizes = $('#sizes_checkbox').is(':checked');
            var dresses = $('#dresses_checkbox').is(':checked');
            var productCode = $('input[name="product_code"]').val() || '';
            var productName = $('input[name="name"]').val() || '';
            var categoryName = $('select[name="category_id"] option:selected').text() || '';
            var allergies = $('input[name="allergies"]').val() || '';
            var description = $('textarea[name="description"]').val() || '';
            var isNew = $('#is_new_checkbox').is(':checked');
            var isOffer = $('#is_offer_checkbox').is(':checked');
            var isActive = $('#is_active_checkbox').is(':checked');
            var globalExtras = getUniqueExtras();
            var globalSauces = getUniqueSauces();
            var globalDresses = getUniqueDresses();
            var max_sauces_base = $('input[name="max_sauces_base"]').val() || 0;
            var max_extras_base = $('input[name="max_extras_base"]').val() || 0;
            var max_dresses_base = $('input[name="max_dresses_base"]').val() || 0;
            var preview = '<strong>Product:</strong> ' + productName + ' (Code: ' + productCode + ')<br>' +
                '<strong>Category:</strong> ' + categoryName + '<br>';
            if (allergies.trim() !== '') {
                preview += '<strong>Allergies:</strong> ' + allergies + '<br>';
            }
            if (description.trim() !== '') {
                preview += '<strong>Description:</strong> ' + description + '<br>';
            }
            if (isNew) {
                preview += '<span class="badge bg-success me-1">New</span>';
            }
            if (isOffer) {
                preview += '<span class="badge bg-warning text-dark me-1">Offer</span>';
            }
            if (isActive) {
                preview += '<span class="badge bg-primary">Active</span><br>';
            }
            if (extrasSauces) {
                preview += '<strong>Global Extras:</strong><br>';
                if (globalExtras.length > 0) {
                    $.each(globalExtras, function(i, ex) {
                        preview += '- ' + ex.name + ' ($' + ex.price + ')<br>';
                    });
                } else {
                    preview += '(None)<br>';
                }
                preview += '<strong>Global Sauces:</strong><br>';
                if (globalSauces.length > 0) {
                    $.each(globalSauces, function(i, sa) {
                        preview += '- ' + sa.name + ' ($' + sa.price + ')<br>';
                    });
                } else {
                    preview += '(None)<br>';
                }
            }
            if (sizes) {
                preview += '<strong>Sizes:</strong><br>';
                $('#sizeBlocks .size-block').each(function() {
                    var $thisBlock = $(this);
                    var sz = $thisBlock.find('input[name*="[size]"]').val() || '';
                    var szp = $thisBlock.find('input[name*="[price]"]').val() || 0;
                    var mxs = $thisBlock.find('input[name*="[max_sauces]"]').val() || 0;
                    if (sz.trim() !== '') {
                        preview += sz + ' ($' + szp + ')';
                        if (mxs > 0) {
                            preview += ' - <em>Max Sauces: ' + mxs + '</em>';
                        }
                        preview += '<br>';
                        var extrasSelected = $thisBlock.find('.extras-select').val() || [];
                        var saucesSelected = $thisBlock.find('.sauces-select').val() || [];
                        if (extrasSelected.length > 0) {
                            preview += '&nbsp;&nbsp;<strong>Extras:</strong><br>';
                            $.each(extrasSelected, function(_, exIndex) {
                                exIndex = parseInt(exIndex, 10);
                                if (globalExtras[exIndex]) {
                                    var exPrice = $thisBlock.find('input[name*="[extras_prices][' + exIndex + ']"]').val() || globalExtras[exIndex].price || 0;
                                    preview += '&nbsp;&nbsp;- ' + globalExtras[exIndex].name + ' ($' + exPrice + ')<br>';
                                }
                            });
                        }
                        if (saucesSelected.length > 0) {
                            preview += '&nbsp;&nbsp;<strong>Sauces:</strong><br>';
                            $.each(saucesSelected, function(_, saIndex) {
                                saIndex = parseInt(saIndex, 10);
                                if (globalSauces[saIndex]) {
                                    var saPrice = $thisBlock.find('input[name*="[sauces_prices][' + saIndex + ']"]').val() || globalSauces[saIndex].price || 0;
                                    preview += '&nbsp;&nbsp;- ' + globalSauces[saIndex].name + ' ($' + saPrice + ')<br>';
                                }
                            });
                        }
                    }
                });
            } else {
                var basePrice = $('input[name="base_price"]').val() || 0;
                preview += '<strong>Base Price:</strong> $' + basePrice + '<br>';
                if (extrasSauces) {
                    if (max_sauces_base > 0) {
                        preview += '<strong>Max Sauces (Base):</strong> ' + max_sauces_base + '<br>';
                    }
                    if (max_extras_base > 0) {
                        preview += '<strong>Max Extras (Base):</strong> ' + max_extras_base + '<br>';
                    }
                }
            }
            if (dresses) {
                preview += '<strong>Dresses:</strong><br>';
                $('#dressBlocks .dress-block').each(function() {
                    var dn = $(this).find('input[name*="dress"]').val() || '';
                    var dp = $(this).find('input[name*="price"]').val() || 0;
                    if (dn.trim() !== '') {
                        preview += '- ' + dn + ' ($' + dp + ')<br>';
                    }
                });
                if (!sizes && max_dresses_base > 0) {
                    preview += '<strong>Max Dressings (Base):</strong> ' + max_dresses_base + '<br>';
                }
            }
            $('#previewContent').html(preview);
        }

        function renderSizeBlock(index) {
            return `
<div class="size-block border p-3 mb-3" data-block-index="${index}">
  <div class="row g-3 align-items-end">
    <div class="col-md-4">
      <label class="form-label">Size</label>
      <input type="text" class="form-control" name="sizes_block[${index}][size]" required placeholder="Medium / 10 pieces">
    </div>
    <div class="col-md-3">
      <label class="form-label">Base Price ($)</label>
      <input type="number" step="0.01" class="form-control" name="sizes_block[${index}][price]" required placeholder="9.99">
    </div>
    <div class="col-md-3">
      <label class="form-label">Max Sauces</label>
      <input type="number" class="form-control" name="sizes_block[${index}][max_sauces]" min="0" placeholder="1">
    </div>
    <div class="col-md-2 text-end">
      <button type="button" class="btn btn-danger remove-size-block"><i class="fas fa-minus-circle"></i></button>
    </div>
  </div>
  <div class="row g-3 mt-3">
    <div class="col-md-6">
      <label class="form-label">Extras</label>
      <select class="form-select extras-select" name="sizes_block[${index}][extras_selected][]" multiple></select>
    </div>
    <div class="col-md-6">
      <label class="form-label">Sauces</label>
      <select class="form-select sauces-select" name="sizes_block[${index}][sauces_selected][]" multiple></select>
    </div>
  </div>
  <div class="extras-prices mt-3"></div>
  <div class="sauces-prices mt-3"></div>
</div>`;
        }

        function renderDressBlock() {
            var c = $('#dressBlocks .dress-block').length;
            return `
<div class="dress-block border p-3 mb-3">
  <div class="row g-3 align-items-end">
    <div class="col-md-6">
      <label class="form-label">Dress</label>
      <input type="text" class="form-control" name="dresses[${c}][dress]" required placeholder="Italian Dressing">
    </div>
    <div class="col-md-4">
      <label class="form-label">Price ($)</label>
      <input type="number" step="0.01" class="form-control" name="dresses[${c}][price]" required placeholder="1.50">
    </div>
    <div class="col-md-2 text-end">
      <button type="button" class="btn btn-danger remove-dress-block"><i class="fas fa-minus-circle"></i></button>
    </div>
  </div>
</div>`;
        }
        toggleFields();
        updatePreview();
    });
</script>

<?php require_once 'includes/footer.php'; ?>