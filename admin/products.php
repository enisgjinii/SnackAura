<?php
// admin/products.php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Initialize variables
$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category_filter'] ?? '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Fetch categories for forms and filters
try {
    $categories = $pdo->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
} catch (PDOException $e) {
    $categories = [];
    $message = '<div class="alert alert-danger">Error fetching categories: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Fetch extras for multi-select
try {
    $extras = $pdo->query('SELECT * FROM extras ORDER BY name ASC')->fetchAll();
} catch (PDOException $e) {
    $extras = [];
    $message .= '<div class="alert alert-danger">Error fetching extras: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Fetch sauces for selection
try {
    $available_sauces = $pdo->query('SELECT * FROM sauces ORDER BY name ASC')->fetchAll();
} catch (PDOException $e) {
    $available_sauces = [];
    $message .= '<div class="alert alert-danger">Error fetching sauces: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Handle different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        // Add Product
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $allergies = trim($_POST['allergies']);
        $category_id = (int)$_POST['category_id'];
        $is_new = isset($_POST['is_new']) ? 1 : 0;
        $is_offer = isset($_POST['is_offer']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $image_url = trim($_POST['image_url']); // Alternatively, handle file uploads
        $selected_extras = $_POST['extras'] ?? [];
        $selected_sauces = $_POST['sauces'] ?? []; // Array of sauce names

        // Convert sauces array to comma-separated string
        $sauces_string = !empty($selected_sauces) ? implode(', ', array_map('trim', $selected_sauces)) : null;

        // Validate inputs
        if ($name === '' || $category_id === 0) {
            $message = '<div class="alert alert-danger">Name and Category are required.</div>';
        } else {
            // Insert product
            $stmt = $pdo->prepare('INSERT INTO products (category_id, name, description, allergies, sauces, image_url, is_new, is_offer, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            try {
                $stmt->execute([$category_id, $name, $description, $allergies, $sauces_string, $image_url, $is_new, $is_offer, $is_active]);

                // Insert selected extras
                if (!empty($selected_extras)) {
                    $stmt_extras = $pdo->prepare('INSERT INTO product_extras (product_id, extra_id) VALUES (?, ?)');
                    $product_id = $pdo->lastInsertId();
                    foreach ($selected_extras as $extra_id) {
                        $stmt_extras->execute([$product_id, $extra_id]);
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
        $allergies = trim($_POST['allergies']);
        $category_id = (int)$_POST['category_id'];
        $is_new = isset($_POST['is_new']) ? 1 : 0;
        $is_offer = isset($_POST['is_offer']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $image_url = trim($_POST['image_url']); // Alternatively, handle file uploads
        $selected_extras = $_POST['extras'] ?? [];
        $selected_sauces = $_POST['sauces'] ?? []; // Array of sauce names

        // Convert sauces array to comma-separated string
        $sauces_string = !empty($selected_sauces) ? implode(', ', array_map('trim', $selected_sauces)) : null;

        // Validate inputs
        if ($name === '' || $category_id === 0) {
            $message = '<div class="alert alert-danger">Name and Category are required.</div>';
        } else {
            // Update product
            $stmt = $pdo->prepare('UPDATE products SET category_id = ?, name = ?, description = ?, allergies = ?, sauces = ?, image_url = ?, is_new = ?, is_offer = ?, is_active = ? WHERE id = ?');
            try {
                $stmt->execute([$category_id, $name, $description, $allergies, $sauces_string, $image_url, $is_new, $is_offer, $is_active, $id]);

                // Update extras
                // First, delete existing extras
                $stmt_delete_extras = $pdo->prepare('DELETE FROM product_extras WHERE product_id = ?');
                $stmt_delete_extras->execute([$id]);

                // Then, insert new extras
                if (!empty($selected_extras)) {
                    $stmt_extras = $pdo->prepare('INSERT INTO product_extras (product_id, extra_id) VALUES (?, ?)');
                    foreach ($selected_extras as $extra_id) {
                        $stmt_extras->execute([$id, $extra_id]);
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
    try {
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $message = '<div class="alert alert-success">Product deleted successfully.</div>';
        // Redirect to view
        header('Location: products.php');
        exit();
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Fetch products based on search and filter
if ($action === 'view') {
    try {
        $query = '
            SELECT products.*, categories.name AS category_name
            FROM products
            JOIN categories ON products.category_id = categories.id
            WHERE 1
        ';
        $params = [];

        if ($search !== '') {
            $query .= ' AND products.name LIKE ?';
            $params[] = '%' . $search . '%';
        }

        if ($category_filter !== '') {
            $query .= ' AND products.category_id = ?';
            $params[] = $category_filter;
        }

        $query .= ' ORDER BY products.created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        // Fetch total count for pagination
        $count_query = '
            SELECT COUNT(*) FROM products
            WHERE 1
        ';
        $count_params = [];

        if ($search !== '') {
            $count_query .= ' AND name LIKE ?';
            $count_params[] = '%' . $search . '%';
        }

        if ($category_filter !== '') {
            $count_query .= ' AND category_id = ?';
            $count_params[] = $category_filter;
        }

        $count_stmt = $pdo->prepare($count_query);
        $count_stmt->execute($count_params);
        $total = $count_stmt->fetchColumn();
        $total_pages = ceil($total / $limit);
    } catch (PDOException $e) {
        $products = [];
        $message = '<div class="alert alert-danger">Error fetching products: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
?>

<?php if ($action === 'view'): ?>
    <h2 class="mb-4">Manage Products</h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>

    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs mb-3" id="productTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'drinks.php' ? 'active' : '' ?>" href="drinks.php" data-bs-toggle="tooltip" data-bs-placement="top" title="View Drinks">
                <i class="fas fa-glass-martini-alt"></i> Drinks
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'extras.php' ? 'active' : '' ?>" href="extras.php" data-bs-toggle="tooltip" data-bs-placement="top" title="View Extras">
                <i class="fas fa-plus-circle"></i> Extras
            </a>
        </li>
    </ul>

    <!-- Search and Filter Form -->
    <form method="GET" action="products.php" class="row g-2 mb-3">
        <input type="hidden" name="type" value="product"> <!-- Assuming 'product' type for products.php -->
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text" id="search-addon" data-bs-toggle="tooltip" data-bs-placement="top" title="Search products by name">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" class="form-control" name="search" placeholder="Search by name" value="<?= htmlspecialchars($search) ?>" aria-label="Search" aria-describedby="search-addon">
            </div>
        </div>
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text" id="category-addon" data-bs-toggle="tooltip" data-bs-placement="top" title="Filter products by category">
                    <i class="fas fa-filter"></i>
                </span>
                <select class="form-select" name="category_filter" aria-label="Category Filter" aria-describedby="category-addon">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" <?= ($category_filter == $category['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-4 d-flex align-items-center">
            <button type="submit" class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="Click to search">
                <i class="fas fa-search"></i> Search
            </button>
            <a href="products.php" class="btn btn-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="Reset search and filters">
                <i class="fas fa-undo"></i> Reset
            </a>
        </div>
    </form>

    <!-- Add Product Button -->
    <div class="mb-3">
        <a href="products.php?action=add" class="btn btn-success" data-bs-toggle="tooltip" data-bs-placement="top" title="Add a new product">
            <i class="fas fa-plus"></i> Add Product
        </a>
    </div>

    <!-- Products Table -->
    <div class="table-responsive">
        <table id="productsTable" class="table table-striped table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th scope="col">ID <i class="fas fa-sort"></i></th>
                    <th scope="col">Name <i class="fas fa-sort"></i></th>
                    <th scope="col">Category <i class="fas fa-sort"></i></th>
                    <th scope="col">Extras</th>
                    <th scope="col">Sauces</th>
                    <th scope="col">Allergies</th>
                    <th scope="col">Description</th>
                    <th scope="col">Image</th>
                    <th scope="col">New</th>
                    <th scope="col">Offer</th>
                    <th scope="col">Status</th>
                    <th scope="col">Created At <i class="fas fa-sort"></i></th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= htmlspecialchars($product['id']) ?></td>
                            <td><?= htmlspecialchars($product['name']) ?></td>
                            <td><?= htmlspecialchars($product['category_name']) ?></td>
                            <td><?= !empty($product['extras']) ? htmlspecialchars($product['allergies']) : '-' ?></td>
                            <td><?= !empty($product['sauces']) ? htmlspecialchars($product['sauces']) : '-' ?></td>
                            <td><?= htmlspecialchars($product['allergies']) ? htmlspecialchars($product['allergies']) : '-' ?></td>
                            <td><?= htmlspecialchars($product['description']) ?></td>
                            <td>
                                <?php if (!empty($product['image_url'])): ?>
                                    <a href="<?= htmlspecialchars($product['image_url']) ?>" target="_blank" data-bs-toggle="tooltip" data-bs-placement="top" title="View Image">
                                        <i class="fas fa-image text-primary"></i>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $product['is_new'] ? '<span class="badge bg-success" data-bs-toggle="tooltip" data-bs-placement="top" title="New Product"><i class="fas fa-star"></i></span>' : '-' ?>
                            </td>
                            <td>
                                <?= $product['is_offer'] ? '<span class="badge bg-warning text-dark" data-bs-toggle="tooltip" data-bs-placement="top" title="Special Offer"><i class="fas fa-tags"></i></span>' : '-' ?>
                            </td>
                            <td>
                                <?= $product['is_active'] 
                                    ? '<span class="badge bg-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Active"><i class="fas fa-check-circle"></i></span>' 
                                    : '<span class="badge bg-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="Inactive"><i class="fas fa-times-circle"></i></span>' 
                                ?>
                            </td>
                            <td><?= htmlspecialchars($product['created_at']) ?></td>
                            <td>
                                <a href="products.php?action=edit&id=<?= $product['id'] ?>" class="btn btn-sm btn-warning me-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit Product">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button class="btn btn-sm btn-danger" onclick="showDeleteModal(<?= $product['id'] ?>)" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete Product">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="13" class="text-center">No products found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Links -->
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="products.php?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category_filter=<?= $category_filter ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                            <span class="visually-hidden">Previous</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                        <a class="page-link" href="products.php?page=<?= $p ?>&search=<?= urlencode($search) ?>&category_filter=<?= $category_filter ?>">
                            <?= $p ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="products.php?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category_filter=<?= $category_filter ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                            <span class="visually-hidden">Next</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="GET" action="products.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteProductId">
                <input type="hidden" name="type" value="product"> <!-- Assuming 'product' type -->
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" data-bs-toggle="tooltip" data-bs-placement="top" title="Close"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete this product?
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete">
                            <i class="fas fa-trash-alt"></i> Delete
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-bs-toggle="tooltip" data-bs-placement="top" title="Cancel">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Include Necessary Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Font Awesome -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <!-- Select2 CSS and JS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Initialize Select2 and Tooltips -->
    <script>
        $(document).ready(function() {
            // Initialize Select2 on extras and sauces multi-select
            $('#extras').select2({
                placeholder: "Select extras",
                allowClear: true
            });

            $('#sauces').select2({
                placeholder: "Select sauces",
                allowClear: true
            });

            // Initialize Tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
        });

        function showDeleteModal(id) {
            document.getElementById('deleteProductId').value = id;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>

<?php elseif ($action === 'add' || ($action === 'edit' && $id > 0)): ?>
    <?php
    if ($action === 'edit') {
        // Fetch existing product details
        try {
            $stmt = $pdo->prepare("SELECT * FROM `products` WHERE `id` = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            if (!$product) {
                echo '<div class="alert alert-danger">Product not found.</div>';
                require_once 'includes/footer.php';
                exit();
            }

            // Fetch associated extras
            try {
                $stmt_extras = $pdo->prepare('SELECT extra_id FROM product_extras WHERE product_id = ?');
                $stmt_extras->execute([$id]);
                $selected_extras = $stmt_extras->fetchAll(PDO::FETCH_COLUMN);
            } catch (PDOException $e) {
                $selected_extras = [];
                $message .= '<div class="alert alert-danger">Error fetching product extras: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }

            // Convert existing sauces string to array
            $selected_sauces_array = !empty($product['sauces']) ? array_map('trim', explode(',', $product['sauces'])) : [];
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger">Error fetching product details: ' . htmlspecialchars($e->getMessage()) . '</div>';
            require_once 'includes/footer.php';
            exit();
        }
    }
    ?>

    <h2 class="mb-4"><?= $action === 'add' ? 'Add Product' : 'Edit Product' ?></h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <form method="POST" action="products.php?action=<?= $action ?><?= $action === 'edit' ? '&id=' . $id : '' ?>">
        <?php if ($action === 'edit'): ?>
            <!-- Hidden input for ID -->
            <input type="hidden" name="id" value="<?= $id ?>">
        <?php endif; ?>
        <div class="row g-3">
            <!-- Product Name -->
            <div class="col-md-6">
                <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text" id="name-addon" data-bs-toggle="tooltip" data-bs-placement="top" title="Enter the name of the product">
                        <i class="fas fa-heading"></i>
                    </span>
                    <input type="text" class="form-control" id="name" name="name" required value="<?= $action === 'edit' ? htmlspecialchars($product['name']) : (isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '') ?>" aria-describedby="name-addon">
                </div>
            </div>
            <!-- Category -->
            <div class="col-md-6">
                <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text" id="category-addon" data-bs-toggle="tooltip" data-bs-placement="top" title="Select the category for the product">
                        <i class="fas fa-list"></i>
                    </span>
                    <select class="form-select" id="category_id" name="category_id" required aria-describedby="category-addon">
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" <?= ($action === 'edit' && $product['category_id'] == $category['id']) || (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Allergies and Image URL -->
        <div class="row g-3 mt-3">
            <!-- Allergies -->
            <div class="col-md-6">
                <label for="allergies" class="form-label">Allergies</label>
                <div class="input-group">
                    <span class="input-group-text" id="allergies-addon" data-bs-toggle="tooltip" data-bs-placement="top" title="List any allergies associated with the product">
                        <i class="fas fa-exclamation-triangle"></i>
                    </span>
                    <input type="text" class="form-control" id="allergies" name="allergies" placeholder="e.g., Nuts, Dairy" value="<?= $action === 'edit' ? htmlspecialchars($product['allergies']) : (isset($_POST['allergies']) ? htmlspecialchars($_POST['allergies']) : '') ?>" aria-describedby="allergies-addon">
                </div>
                <div class="form-text">Separate multiple allergies with commas.</div>
            </div>
            <!-- Image URL -->
            <div class="col-md-6">
                <label for="image_url" class="form-label">Image URL</label>
                <div class="input-group">
                    <span class="input-group-text" id="image-addon" data-bs-toggle="tooltip" data-bs-placement="top" title="Enter the URL of the product image">
                        <i class="fas fa-image"></i>
                    </span>
                    <input type="url" class="form-control" id="image_url" name="image_url" placeholder="https://example.com/image.jpg" value="<?= $action === 'edit' ? htmlspecialchars($product['image_url']) : (isset($_POST['image_url']) ? htmlspecialchars($_POST['image_url']) : '') ?>" aria-describedby="image-addon">
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="row g-3 mt-3">
            <div class="col-md-12">
                <label for="description" class="form-label">Description</label>
                <div class="input-group">
                    <span class="input-group-text" id="description-addon" data-bs-toggle="tooltip" data-bs-placement="top" title="Provide a description for the product">
                        <i class="fas fa-align-left"></i>
                    </span>
                    <textarea class="form-control" id="description" name="description" aria-describedby="description-addon"><?= $action === 'edit' ? htmlspecialchars($product['description']) : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Extras Multi-Select -->
        <div class="row g-3 mt-3">
            <div class="col-md-12">
                <label for="extras" class="form-label">Extras</label>
                <select class="form-select" id="extras" name="extras[]" multiple aria-describedby="extras-addon">
                    <?php foreach ($extras as $extra): ?>
                        <option value="<?= $extra['id'] ?>" 
                            <?= (isset($_POST['extras']) && in_array($extra['id'], $_POST['extras'])) ||
                               ($action === 'edit' && isset($selected_extras) && in_array($extra['id'], $selected_extras)) 
                               ? 'selected' : '' ?>>
                            <?= htmlspecialchars($extra['name']) ?> - $<?= htmlspecialchars($extra['price']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Select extras for this product.</div>
            </div>
        </div>

        <!-- Sauces Multi-Select -->
        <div class="row g-3 mt-3">
            <div class="col-md-12">
                <label for="sauces" class="form-label">Sauces</label>
                <select class="form-select" id="sauces" name="sauces[]" multiple aria-describedby="sauces-addon">
                    <?php foreach ($available_sauces as $sauce): ?>
                        <option value="<?= htmlspecialchars($sauce['name']) ?>" 
                            <?= (isset($_POST['sauces']) && in_array($sauce['name'], $_POST['sauces'])) ||
                               ($action === 'edit' && isset($selected_sauces_array) && in_array($sauce['name'], $selected_sauces_array)) 
                               ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sauce['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Select sauces for this product.</div>
            </div>
        </div>

        <!-- Additional Options: New, Offer, Active -->
        <div class="row g-3 mt-3">
            <!-- New Product -->
            <div class="col-md-4">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="is_new" name="is_new" <?= $action === 'edit' ? ($product['is_new'] ? 'checked' : '') : (isset($_POST['is_new']) ? 'checked' : '') ?>>
                    <label class="form-check-label" for="is_new" data-bs-toggle="tooltip" data-bs-placement="top" title="Mark as a new product">
                        <i class="fas fa-star"></i> New Product
                    </label>
                </div>
            </div>
            <!-- Offer -->
            <div class="col-md-4">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="is_offer" name="is_offer" <?= $action === 'edit' ? ($product['is_offer'] ? 'checked' : '') : (isset($_POST['is_offer']) ? 'checked' : '') ?>>
                    <label class="form-check-label" for="is_offer" data-bs-toggle="tooltip" data-bs-placement="top" title="Mark as an offer product">
                        <i class="fas fa-tags"></i> Offer
                    </label>
                </div>
            </div>
            <!-- Active Status -->
            <div class="col-md-4">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?= $action === 'edit' ? ($product['is_active'] ? 'checked' : '') : (isset($_POST['is_active']) ? 'checked' : 'checked') ?>>
                    <label class="form-check-label" for="is_active" data-bs-toggle="tooltip" data-bs-placement="top" title="Set the product as active">
                        <i class="fas fa-toggle-on"></i> Active
                    </label>
                </div>
            </div>
        </div>

        <!-- Submit and Cancel Buttons -->
        <div class="mt-4">
            <button type="submit" class="btn btn-success me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $action === 'add' ? 'Add new product' : 'Update product details' ?>">
                <i class="fas fa-save"></i> <?= $action === 'add' ? 'Add' : 'Update' ?> Product
            </button>
            <a href="products.php" class="btn btn-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="Cancel and return to product list">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>

    <!-- Include Necessary Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Font Awesome -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <!-- Select2 CSS and JS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Initialize Select2 and Tooltips -->
    <script>
        $(document).ready(function() {
            // Initialize Select2 on extras and sauces multi-select
            $('#extras').select2({
                placeholder: "Select extras",
                allowClear: true
            });

            $('#sauces').select2({
                placeholder: "Select sauces",
                allowClear: true
            });

            // Initialize Tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
        });

        function showDeleteModal(id) {
            document.getElementById('deleteProductId').value = id;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>
