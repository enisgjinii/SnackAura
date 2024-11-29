<?php
ob_start();
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

if (session_status() == PHP_SESSION_NONE) session_start();

$action = $_REQUEST['action'] ?? 'view';
$id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;

function sanitizeInput($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

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
            if ($file_size <= 2 * 1024 * 1024) {
                $new_file_name = uniqid('cat_', true) . '.' . $file_ext;
                $upload_dir = 'uploads/categories/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $dest_path = $upload_dir . $new_file_name;
                if (move_uploaded_file($file_tmp_path, $dest_path)) {
                    if (!empty($existingImage) && file_exists($existingImage)) unlink($existingImage);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Invalid CSRF token.'];
        header('Location: categories.php');
        exit();
    }

    if ($action === 'add') {
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');

        try {
            $image_url = handleImageUpload($_FILES['image_file'] ?? null);

            if ($name === '') throw new Exception('Category name is required.');

            // Automatically assign the next position
            $stmt = $pdo->query('SELECT MAX(position) AS max_position FROM categories');
            $result = $stmt->fetch();
            $position = $result['max_position'] !== null ? $result['max_position'] + 1 : 1;

            $stmt = $pdo->prepare('INSERT INTO categories (name, description, image_url, position) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $description, $image_url, $position]);

            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Category added successfully.'];
            header('Location: categories.php');
            exit();
        } catch (Exception $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => sanitizeInput($e->getMessage())];
            header('Location: categories.php');
            exit();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Category name already exists.'];
            } else {
                error_log('Database Error: ' . $e->getMessage());
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'An unexpected error occurred. Please try again later.'];
            }
            header('Location: categories.php');
            exit();
        }
    } elseif ($action === 'edit' && $id > 0) {
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');

        try {
            $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
            $stmt->execute([$id]);
            $category = $stmt->fetch();
            if (!$category) throw new Exception('Category not found.');

            $image_url = handleImageUpload($_FILES['image_file'] ?? null, $category['image_url']);

            if ($name === '') throw new Exception('Category name is required.');

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
            if ($e->getCode() === '23000') {
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Category name already exists.'];
            } else {
                error_log('Database Error: ' . $e->getMessage());
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'An unexpected error occurred. Please try again later.'];
            }
            header('Location: categories.php');
            exit();
        }
    } elseif ($action === 'delete' && $id > 0) {
        try {
            $stmt = $pdo->prepare('SELECT image_url FROM categories WHERE id = ?');
            $stmt->execute([$id]);
            $category = $stmt->fetch();
            if ($category) {
                if (!empty($category['image_url']) && file_exists($category['image_url'])) unlink($category['image_url']);
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
            error_log('Database Error: ' . $e->getMessage());
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'An unexpected error occurred. Please try again later.'];
            header('Location: categories.php');
            exit();
        }
    }
}

if ($action === 'view') {
    $stmt = $pdo->prepare('SELECT * FROM categories ORDER BY position ASC');
    $stmt->execute();
    $categories = $stmt->fetchAll();
}
?>
<?php if ($action === 'view'): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Categories</h2>
        <button class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#addCategoryOffcanvas"><i class="fas fa-plus"></i> Add Category</button>
    </div>
    <div class="table-responsive">
        <table id="categoriesTable" class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Move</th>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="sortable">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $category): ?>
                        <tr data-id="<?= sanitizeInput($category['id']) ?>">
                            <td class="text-center">
                                <i class="fas fa-bars handle" style="cursor: move;"></i>
                            </td>
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
                                    data-bs-target="#editCategoryOffcanvas"><i class="fas fa-edit"></i> Edit</button>
                                <button class="btn btn-sm btn-danger delete-category-btn"
                                    data-id="<?= $category['id'] ?>"
                                    data-name="<?= sanitizeInput($category['name']) ?>"><i class="fas fa-trash-alt"></i> Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">No categories found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Category Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="addCategoryOffcanvas" aria-labelledby="addCategoryOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Add New Category</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
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
            <h5 class="offcanvas-title">Edit Category</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="categories.php?action=edit" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">
                <input type="hidden" name="id" id="edit-id" value="">
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
                    <div><img src="" alt="Current Image" id="current-image" class="img-thumbnail" style="width: 100px; height: 100px; object-fit: cover;"></div>
                </div>
                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update Category</button>
            </form>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
        <div id="toast-container"></div>
    </div>
<?php endif; ?>
<?php
require_once 'includes/footer.php';
?>

<!-- External Libraries -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.css" rel="stylesheet">

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>
<script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#categoriesTable').DataTable({
            responsive: true,
            columnDefs: [{
                    orderable: false,
                    targets: [0, 2, 6]
                } // Disable ordering on Move, Image, and Actions columns
            ],
            order: [
                [1, 'asc']
            ], // Initially order by ID
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

        // Initialize Sortable
        $("#sortable").sortable({
            handle: ".handle",
            placeholder: "sortable-placeholder",
            update: function(event, ui) {
                var order = [];
                $('#sortable tr').each(function(index) {
                    order.push({
                        id: $(this).data('id'),
                        position: index + 1
                    });
                });
                $.ajax({
                    url: 'update_position.php',
                    method: 'POST',
                    data: {
                        csrf_token: '<?= generateCsrfToken(); ?>',
                        order: JSON.stringify(order)
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire('Success', response.message, 'success');
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'An error occurred while updating positions.', 'error');
                    }
                });
            }
        }).disableSelection();

        // Handle Edit Button Click
        $('.edit-category-btn').on('click', function() {
            var id = $(this).data('id');
            var name = $(this).data('name');
            var description = $(this).data('description');
            var image = $(this).data('image');

            $('#edit-id').val(id);
            $('#edit-name').val(name);
            $('#edit-description').val(description);
            $('#current-image').attr('src', image ? image : 'assets/images/placeholder.png');
        });

        // Handle Delete Button Click
        $('.delete-category-btn').on('click', function() {
            var id = $(this).data('id');
            var name = $(this).data('name');
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
                    var form = $('<form>', {
                        method: 'POST',
                        action: 'categories.php?action=delete'
                    });
                    form.append($('<input>', {
                        type: 'hidden',
                        name: 'csrf_token',
                        value: '<?= generateCsrfToken(); ?>'
                    }));
                    form.append($('<input>', {
                        type: 'hidden',
                        name: 'id',
                        value: id
                    }));
                    $('body').append(form);
                    form.submit();
                }
            });
        });

        // Display Toast Notifications
        <?php if (isset($_SESSION['toast'])): ?>
            var toastHtml = `
                <div class="toast align-items-center text-white bg-<?= $_SESSION['toast']['type'] === 'success' ? 'success' : 'danger' ?> border-0 mb-2" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            <?= $_SESSION['toast']['message'] ?>
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            $('#toast-container').html(toastHtml);
            $('.toast').toast({
                delay: 5000
            }).toast('show');
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>
    });
</script>

<!-- Custom Styles for Sortable Placeholder -->
<style>
    .sortable-placeholder {
        background: #f0f0f0;
        border: 2px dashed #ccc;
        height: 60px;
    }
</style>