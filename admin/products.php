<?php
// admin/products.php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Initialize variables
$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$message = '';

// Fetch categories, sizes, and extras for forms
$categories = $pdo->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
$sizes = $pdo->query('SELECT * FROM sizes ORDER BY name ASC')->fetchAll();
$extras = $pdo->query('SELECT * FROM extras ORDER BY name ASC')->fetchAll();

// Handle different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        // Add Product
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $category_id = (int) $_POST['category_id'];
        $is_new = isset($_POST['is_new']) ? 1 : 0;
        $is_offer = isset($_POST['is_offer']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $image_url = trim($_POST['image_url']); // Alternatively, handle file uploads

        // Validate inputs
        if ($name === '' || $category_id === 0) {
            $message = '<div class="alert alert-danger">Name and Category are required.</div>';
        } else {
            // Insert product
            $stmt = $pdo->prepare('INSERT INTO products (category_id, name, description, image_url, is_new, is_offer, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)');
            try {
                $stmt->execute([$category_id, $name, $description, $image_url, $is_new, $is_offer, $is_active]);
                $product_id = $pdo->lastInsertId();

                // Associate sizes with prices
                foreach ($sizes as $size) {
                    if (isset($_POST['price_' . $size['id']]) && is_numeric($_POST['price_' . $size['id']])) {
                        $price = floatval($_POST['price_' . $size['id']]);
                        if ($price > 0) {
                            $pdo->prepare('INSERT INTO product_sizes (product_id, size_id, price) VALUES (?, ?, ?)')
                                ->execute([$product_id, $size['id'], $price]);
                        }
                    }
                }

                // Associate extras
                if (isset($_POST['extras']) && is_array($_POST['extras'])) {
                    foreach ($_POST['extras'] as $extra_id) {
                        $pdo->prepare('INSERT INTO product_extras (product_id, extra_id) VALUES (?, ?)')
                            ->execute([$product_id, $extra_id]);
                    }
                }

                $message = '<div class="alert alert-success">Product added successfully.</div>';
                // Redirect to view
                header('Location: products.php');
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') { // Integrity constraint violation
                    $message = '<div class="alert alert-danger">Product name already exists.</div>';
                } else {
                    $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    } elseif ($action === 'edit' && $id > 0) {
        // Edit Product
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $category_id = (int) $_POST['category_id'];
        $is_new = isset($_POST['is_new']) ? 1 : 0;
        $is_offer = isset($_POST['is_offer']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $image_url = trim($_POST['image_url']);

        // Validate inputs
        if ($name === '' || $category_id === 0) {
            $message = '<div class="alert alert-danger">Name and Category are required.</div>';
        } else {
            // Update product
            $stmt = $pdo->prepare('UPDATE products SET category_id = ?, name = ?, description = ?, image_url = ?, is_new = ?, is_offer = ?, is_active = ? WHERE id = ?');
            try {
                $stmt->execute([$category_id, $name, $description, $image_url, $is_new, $is_offer, $is_active, $id]);

                // Update sizes
                // First, delete existing associations
                $pdo->prepare('DELETE FROM product_sizes WHERE product_id = ?')->execute([$id]);

                // Then, insert new associations
                foreach ($sizes as $size) {
                    if (isset($_POST['price_' . $size['id']]) && is_numeric($_POST['price_' . $size['id']])) {
                        $price = floatval($_POST['price_' . $size['id']]);
                        if ($price > 0) {
                            $pdo->prepare('INSERT INTO product_sizes (product_id, size_id, price) VALUES (?, ?, ?)')
                                ->execute([$id, $size['id'], $price]);
                        }
                    }
                }

                // Update extras
                // First, delete existing associations
                $pdo->prepare('DELETE FROM product_extras WHERE product_id = ?')->execute([$id]);

                // Then, insert new associations
                if (isset($_POST['extras']) && is_array($_POST['extras'])) {
                    foreach ($_POST['extras'] as $extra_id) {
                        $pdo->prepare('INSERT INTO product_extras (product_id, extra_id) VALUES (?, ?)')
                            ->execute([$id, $extra_id]);
                    }
                }

                $message = '<div class="alert alert-success">Product updated successfully.</div>';
                // Redirect to view
                header('Location: products.php');
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') { // Integrity constraint violation
                    $message = '<div class="alert alert-danger">Product name already exists.</div>';
                } else {
                    $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    }
} elseif ($action === 'delete' && $id > 0) {
    // Delete Product
    $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $message = '<div class="alert alert-success">Product deleted successfully.</div>';
    // Redirect to view
    header('Location: products.php');
    exit();
}

// Fetch all products for viewing
if ($action === 'view') {
    $stmt = $pdo->query('
        SELECT products.*, categories.name AS category_name 
        FROM products 
        JOIN categories ON products.category_id = categories.id 
        ORDER BY products.created_at DESC
    ');
    $products = $stmt->fetchAll();
}
?>

<?php if ($action === 'view'): ?>
    <h2>Products</h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <a href="products.php?action=add" class="btn btn-primary mb-3">Add Product</a>
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Category</th>
                <th>New</th>
                <th>Offer</th>
                <th>Active</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?= htmlspecialchars($product['id']) ?></td>
                    <td><?= htmlspecialchars($product['name']) ?></td>
                    <td><?= htmlspecialchars($product['category_name']) ?></td>
                    <td><?= $product['is_new'] ? '<span class="badge bg-success">New</span>' : '-' ?></td>
                    <td><?= $product['is_offer'] ? '<span class="badge bg-warning text-dark">Offer</span>' : '-' ?></td>
                    <td><?= $product['is_active'] ? '<span class="badge bg-primary">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td><?= htmlspecialchars($product['created_at']) ?></td>
                    <td>
                        <a href="products.php?action=edit&id=<?= $product['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a href="products.php?action=delete&id=<?= $product['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this product?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<?php elseif ($action === 'add'): ?>
    <h2>Add Product</h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <form method="POST" action="products.php?action=add">
        <div class="mb-3">
            <label for="name" class="form-label">Product Name *</label>
            <input type="text" class="form-control" id="name" name="name" required value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
        </div>
        <div class="mb-3">
            <label for="category_id" class="form-label">Category *</label>
            <select class="form-select" id="category_id" name="category_id" required>
                <option value="">Select a category</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= $category['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description"><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
        </div>
        <div class="mb-3">
            <label for="image_url" class="form-label">Image URL</label>
            <input type="url" class="form-control" id="image_url" name="image_url" value="<?= isset($_POST['image_url']) ? htmlspecialchars($_POST['image_url']) : '' ?>">
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_new" name="is_new" <?= isset($_POST['is_new']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_new">New Product</label>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_offer" name="is_offer" <?= isset($_POST['is_offer']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_offer">Offer</label>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?= isset($_POST['is_active']) ? 'checked' : 'checked' ?>>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
        <hr>
        <h4>Sizes and Prices</h4>
        <?php foreach ($sizes as $size): ?>
            <div class="mb-3">
                <label for="price_<?= $size['id'] ?>" class="form-label"><?= htmlspecialchars($size['name']) ?> Price (€)</label>
                <input type="number" step="0.01" min="0" class="form-control" id="price_<?= $size['id'] ?>" name="price_<?= $size['id'] ?>" value="<?= isset($_POST['price_' . $size['id']]) ? htmlspecialchars($_POST['price_' . $size['id']]) : '' ?>">
            </div>
        <?php endforeach; ?>
        <hr>
        <h4>Extras</h4>
        <?php foreach ($extras as $extra): ?>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="<?= $extra['id'] ?>" id="extra_<?= $extra['id'] ?>" name="extras[]"
                    <?= (isset($_POST['extras']) && in_array($extra['id'], $_POST['extras'])) ? 'checked' : '' ?>>
                <label class="form-check-label" for="extra_<?= $extra['id'] ?>">
                    <?= htmlspecialchars($extra['name']) ?> (+<?= number_format($extra['price'], 2) ?>€)
                </label>
            </div>
        <?php endforeach; ?>
        <hr>
        <button type="submit" class="btn btn-success">Add Product</button>
        <a href="products.php" class="btn btn-secondary">Cancel</a>
    </form>

<?php elseif ($action === 'edit' && $id > 0): ?>
    <?php
    // Fetch product details
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if (!$product) {
        echo '<div class="alert alert-danger">Product not found.</div>';
        require_once 'includes/footer.php';
        exit();
    }

    // Fetch associated sizes and prices
    $sizes_stmt = $pdo->prepare('SELECT size_id, price FROM product_sizes WHERE product_id = ?');
    $sizes_stmt->execute([$id]);
    $product_sizes = $sizes_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Fetch associated extras
    $extras_stmt = $pdo->prepare('SELECT extra_id FROM product_extras WHERE product_id = ?');
    $extras_stmt->execute([$id]);
    $product_extras = $extras_stmt->fetchAll(PDO::FETCH_COLUMN);
    ?>
    <h2>Edit Product</h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <form method="POST" action="products.php?action=edit&id=<?= $id ?>">
        <div class="mb-3">
            <label for="name" class="form-label">Product Name *</label>
            <input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($product['name']) ?>">
        </div>
        <div class="mb-3">
            <label for="category_id" class="form-label">Category *</label>
            <select class="form-select" id="category_id" name="category_id" required>
                <option value="">Select a category</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= $category['id'] ?>" <?= ($product['category_id'] == $category['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description"><?= htmlspecialchars($product['description']) ?></textarea>
        </div>
        <div class="mb-3">
            <label for="image_url" class="form-label">Image URL</label>
            <input type="url" class="form-control" id="image_url" name="image_url" value="<?= htmlspecialchars($product['image_url']) ?>">
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_new" name="is_new" <?= $product['is_new'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_new">New Product</label>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_offer" name="is_offer" <?= $product['is_offer'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_offer">Offer</label>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?= $product['is_active'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
        <hr>
        <h4>Sizes and Prices</h4>
        <?php foreach ($sizes as $size): ?>
            <div class="mb-3">
                <label for="price_<?= $size['id'] ?>" class="form-label"><?= htmlspecialchars($size['name']) ?> Price (€)</label>
                <input type="number" step="0.01" min="0" class="form-control" id="price_<?= $size['id'] ?>" name="price_<?= $size['id'] ?>" value="<?= isset($product_sizes[$size['id']]) ? htmlspecialchars($product_sizes[$size['id']]) : '' ?>">
            </div>
        <?php endforeach; ?>
        <hr>
        <h4>Extras</h4>
        <?php foreach ($extras as $extra): ?>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="<?= $extra['id'] ?>" id="extra_<?= $extra['id'] ?>" name="extras[]"
                    <?= in_array($extra['id'], $product_extras) ? 'checked' : '' ?>>
                <label class="form-check-label" for="extra_<?= $extra['id'] ?>">
                    <?= htmlspecialchars($extra['name']) ?> (+<?= number_format($extra['price'], 2) ?>€)
                </label>
            </div>
        <?php endforeach; ?>
        <hr>
        <button type="submit" class="btn btn-success">Update Product</button>
        <a href="products.php" class="btn btn-secondary">Cancel</a>
    </form>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>