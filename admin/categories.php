<?php
ob_start();
// admin/categories.php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize variables
$action = $_REQUEST['action'] ?? 'view';
$id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;

// Function to sanitize input data
function sanitizeInput($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Function to generate CSRF token
function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Function to validate CSRF token
function validateCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Function to handle image upload
function handleImageUpload($file, $existingImage = null)
{
    $image_url = $existingImage ?? '';

    if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $file['tmp_name'];
        $file_name = basename($file['name']);
        $file_size = $file['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_tmp_path);
        finfo_close($finfo);

        if (in_array($file_ext, $allowed_extensions) && in_array($mime_type, $allowed_mime_types)) {
            if ($file_size <= 2 * 1024 * 1024) { // 2MB limit
                $new_file_name = uniqid('cat_', true) . '.' . $file_ext;
                $upload_dir = 'uploads/categories/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $dest_path = $upload_dir . $new_file_name;
                if (move_uploaded_file($file_tmp_path, $dest_path)) {
                    // Delete old image if exists
                    if (!empty($existingImage) && file_exists($existingImage)) {
                        unlink($existingImage);
                    }
                    $image_url = $dest_path;
                } else {
                    throw new Exception('Error uploading image.');
                }
            } else {
                throw new Exception('Image size cannot exceed 2MB.');
            }
        } else {
            throw new Exception('Only JPG, JPEG, PNG, and GIF files are allowed.');
        }
    }

    return $image_url;
}

// Handle different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Invalid CSRF token.'];
        header('Location: categories.php');
        exit();
    }

    if ($action === 'add') {
        // Add Category
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');

        try {
            $image_url = handleImageUpload($_FILES['image_file'] ?? null);

            if ($name === '') {
                throw new Exception('Category name is required.');
            }

            $stmt = $pdo->prepare('INSERT INTO categories (name, description, image_url) VALUES (?, ?, ?)');
            $stmt->execute([$name, $description, $image_url]);

            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Category added successfully.'];
            header('Location: categories.php');
            exit();
        } catch (Exception $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => sanitizeInput($e->getMessage())];
            header('Location: categories.php');
            exit();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { // Integrity constraint violation
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Category name already exists.'];
            } else {
                // Log the error message to a file or monitoring system
                error_log('Database Error: ' . $e->getMessage());
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'An unexpected error occurred. Please try again later.'];
            }
            header('Location: categories.php');
            exit();
        }
    } elseif ($action === 'edit' && $id > 0) {
        // Edit Category
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');

        try {
            // Fetch existing category
            $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
            $stmt->execute([$id]);
            $category = $stmt->fetch();

            if (!$category) {
                throw new Exception('Category not found.');
            }

            // Handle image upload
            $image_url = handleImageUpload($_FILES['image_file'] ?? null, $category['image_url']);

            if ($name === '') {
                throw new Exception('Category name is required.');
            }

            $stmt = $pdo->prepare('UPDATE categories SET name = ?, description = ?, image_url = ? WHERE id = ?');
            $stmt->execute([$name, $description, $image_url, $id]);

            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Category updated successfully.'];
            header('Location: categories.php');
            exit();
        } catch (Exception $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => sanitizeInput($e->getMessage())];
            header('Location: categories.php');
            exit();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { // Integrity constraint violation
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Category name already exists.'];
            } else {
                // Log the error message to a file or monitoring system
                error_log('Database Error: ' . $e->getMessage());
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'An unexpected error occurred. Please try again later.'];
            }
            header('Location: categories.php');
            exit();
        }
    } elseif ($action === 'delete' && $id > 0) {
        // Delete Category
        try {
            // Fetch category to get image URL
            $stmt = $pdo->prepare('SELECT image_url FROM categories WHERE id = ?');
            $stmt->execute([$id]);
            $category = $stmt->fetch();

            if ($category) {
                // Delete image file if exists
                if (!empty($category['image_url']) && file_exists($category['image_url'])) {
                    unlink($category['image_url']);
                }

                // Delete category
                $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
                $stmt->execute([$id]);

                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Category deleted successfully.'];
                header('Location: categories.php');
                exit();
            } else {
                throw new Exception('Category not found.');
            }
        } catch (Exception $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => sanitizeInput($e->getMessage())];
            header('Location: categories.php');
            exit();
        } catch (PDOException $e) {
            // Log the error message to a file or monitoring system
            error_log('Database Error: ' . $e->getMessage());
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'An unexpected error occurred. Please try again later.'];
            header('Location: categories.php');
            exit();
        }
    }
}

// Fetch all categories for viewing
if ($action === 'view') {
    $stmt = $pdo->prepare('SELECT * FROM categories ORDER BY created_at DESC');
    $stmt->execute();
    $categories = $stmt->fetchAll();
}
?>

<?php if ($action === 'view'): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Categories</h2>
        <button class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#addCategoryOffcanvas" aria-controls="addCategoryOffcanvas">
            <i class="fas fa-plus"></i> Add Category
        </button>
    </div>

    <!-- Categories Table -->
    <div class="table-responsive">
        <table id="categoriesTable" class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?= sanitizeInput($category['id']) ?></td>
                            <td>
                                <?php if (!empty($category['image_url']) && file_exists($category['image_url'])): ?>
                                    <img src="<?= sanitizeInput($category['image_url']) ?>" alt="Category Image" class="img-thumbnail" style="width: 60px; height: 60px; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-image fa-2x text-muted"></i>
                                <?php endif; ?>
                            </td>
                            <td><?= sanitizeInput($category['name']) ?></td>
                            <td><?= sanitizeInput($category['description']) ?></td>
                            <td><?= sanitizeInput($category['created_at']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning me-1 edit-category-btn"
                                    data-id="<?= $category['id'] ?>"
                                    data-name="<?= sanitizeInput($category['name']) ?>"
                                    data-description="<?= sanitizeInput($category['description']) ?>"
                                    data-image="<?= sanitizeInput($category['image_url']) ?>"
                                    data-bs-toggle="offcanvas"
                                    data-bs-target="#editCategoryOffcanvas">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-danger delete-category-btn"
                                    data-id="<?= $category['id'] ?>"
                                    data-name="<?= sanitizeInput($category['name']) ?>">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">No categories found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Category Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="addCategoryOffcanvas" aria-labelledby="addCategoryOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="addCategoryOffcanvasLabel">Add New Category</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close" data-bs-toggle="tooltip" title="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="categories.php?action=add" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">
                <div class="mb-3">
                    <label for="add-name" class="form-label">Category Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="add-name" name="name" required>
                </div>
                <div class="mb-3">
                    <label for="add-description" class="form-label">Description</label>
                    <textarea class="form-control" id="add-description" name="description" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label for="add-image" class="form-label">Category Image</label>
                    <input type="file" class="form-control" id="add-image" name="image_file" accept="image/*">
                </div>
                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Category</button>
            </form>
        </div>
    </div>

    <!-- Edit Category Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="editCategoryOffcanvas" aria-labelledby="editCategoryOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="editCategoryOffcanvasLabel">Edit Category</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close" data-bs-toggle="tooltip" title="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="categories.php?action=edit" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">
                <input type="hidden" name="id" id="edit-id">
                <div class="mb-3">
                    <label for="edit-name" class="form-label">Category Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="edit-name" name="name" required>
                </div>
                <div class="mb-3">
                    <label for="edit-description" class="form-label">Description</label>
                    <textarea class="form-control" id="edit-description" name="description" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label for="edit-image" class="form-label">Change Image</label>
                    <input type="file" class="form-control" id="edit-image" name="image_file" accept="image/*">
                </div>
                <div class="mb-3">
                    <label class="form-label">Current Image</label>
                    <div>
                        <img src="" alt="Current Image" id="current-image" class="img-thumbnail" style="width: 100px; height: 100px; object-fit: cover;">
                    </div>
                </div>
                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update Category</button>
            </form>
        </div>
    </div>

    <!-- Bootstrap Toasts Container -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
        <div id="toast-container">
            <!-- Toasts will be injected here -->
        </div>
    </div>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>

<!-- Include jQuery, SweetAlert2, and DataTables via CDN -->
<!-- jQuery (required for DataTables) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- SweetAlert2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">

<!-- DataTables CSS -->
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTables
        $('#categoriesTable').DataTable({
            responsive: true,
            order: [
                [0, 'desc']
            ],
            language: {
                "emptyTable": "No categories available.",
                "info": "Showing _START_ to _END_ of _TOTAL_ categories",
                "infoEmpty": "Showing 0 to 0 of 0 categories",
                "lengthMenu": "Show _MENU_ categories",
                "paginate": {
                    "first": "First",
                    "last": "Last",
                    "next": "Next",
                    "previous": "Previous"
                },
                "search": "Search:"
            }
        });

        // Initialize Bootstrap Tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Handle Edit Category Button Click
        var editButtons = document.querySelectorAll('.edit-category-btn');
        editButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                var name = this.getAttribute('data-name');
                var description = this.getAttribute('data-description');
                var image = this.getAttribute('data-image');

                // Populate the edit form
                document.getElementById('edit-id').value = id;
                document.getElementById('edit-name').value = name;
                document.getElementById('edit-description').value = description;
                if (image) {
                    document.getElementById('current-image').src = image;
                } else {
                    document.getElementById('current-image').src = 'assets/images/placeholder.png'; // Path to placeholder image
                }
            });
        });

        // Handle Delete Category Button Click
        var deleteButtons = document.querySelectorAll('.delete-category-btn');
        deleteButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                var name = this.getAttribute('data-name');

                // Use SweetAlert2 for confirmation
                Swal.fire({
                    title: 'Are you sure?',
                    text: `Do you really want to delete the category "${name}"? This action cannot be undone.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Create a form to submit the deletion via POST
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'categories.php';

                        var actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        actionInput.value = 'delete';
                        form.appendChild(actionInput);

                        var idInput = document.createElement('input');
                        idInput.type = 'hidden';
                        idInput.name = 'id';
                        idInput.value = id;
                        form.appendChild(idInput);

                        var csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = 'csrf_token';
                        csrfInput.value = '<?= generateCsrfToken(); ?>';
                        form.appendChild(csrfInput);

                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });

        // Handle Bootstrap Toasts
        <?php if (isset($_SESSION['toast'])): ?>
            var toastContainer = document.getElementById('toast-container');
            var toastHtml = `
                <div class="toast align-items-center text-white bg-<?= $_SESSION['toast']['type'] === 'success' ? 'success' : 'danger' ?> border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <?= $_SESSION['toast']['message'] ?>
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            toastContainer.innerHTML = toastHtml;
            var toastEl = toastContainer.querySelector('.toast');
            var toast = new bootstrap.Toast(toastEl, {
                delay: 5000
            });
            toast.show();
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>
    });
</script>