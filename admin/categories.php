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
                    throw new Exception('Fehler beim Hochladen des Bildes.');
                }
            } else {
                throw new Exception('Die Bildgröße darf 2MB nicht überschreiten.');
            }
        } else {
            throw new Exception('Nur JPG, JPEG, PNG und GIF Dateien sind erlaubt.');
        }
    }
    return $image_url;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Ungültiges CSRF-Token.'];
        header('Location: categories.php');
        exit();
    }
    if ($action === 'add') {
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        try {
            $image_url = handleImageUpload($_FILES['image_file'] ?? null);
            if ($name === '') throw new Exception('Der Kategoriename ist erforderlich.');
            // Automatisch die nächste Position zuweisen
            $stmt = $pdo->query('SELECT MAX(position) AS max_position FROM categories');
            $result = $stmt->fetch();
            $position = $result['max_position'] !== null ? $result['max_position'] + 1 : 1;
            $stmt = $pdo->prepare('INSERT INTO categories (name, description, image_url, position) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $description, $image_url, $position]);
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Kategorie erfolgreich hinzugefügt.'];
            header('Location: categories.php');
            exit();
        } catch (Exception $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => sanitizeInput($e->getMessage())];
            header('Location: categories.php');
            exit();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Der Kategoriename existiert bereits.'];
            } else {
                error_log('Datenbankfehler: ' . $e->getMessage());
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.'];
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
            if (!$category) throw new Exception('Kategorie nicht gefunden.');
            $image_url = handleImageUpload($_FILES['image_file'] ?? null, $category['image_url']);
            if ($name === '') throw new Exception('Der Kategoriename ist erforderlich.');
            $stmt = $pdo->prepare('UPDATE categories SET name = ?, description = ?, image_url = ? WHERE id = ?');
            $stmt->execute([$name, $description, $image_url, $id]);
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Kategorie erfolgreich aktualisiert.'];
            header('Location: categories.php');
            exit();
        } catch (Exception $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => sanitizeInput($e->getMessage())];
            header('Location: categories.php');
            exit();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Der Kategoriename existiert bereits.'];
            } else {
                error_log('Datenbankfehler: ' . $e->getMessage());
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.'];
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
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Kategorie erfolgreich gelöscht.'];
                header('Location: categories.php');
                exit();
            } else {
                throw new Exception('Kategorie nicht gefunden.');
            }
        } catch (Exception $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => sanitizeInput($e->getMessage())];
            header('Location: categories.php');
            exit();
        } catch (PDOException $e) {
            error_log('Datenbankfehler: ' . $e->getMessage());
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.'];
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

<!-- Categories Content -->
<div class="categories-content">
    <!-- Header Section -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-text">
                <h1 class="page-title">Categories</h1>
                <p class="page-subtitle">Manage your product categories and organize your inventory</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#addCategoryOffcanvas">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>
        </div>
    </div>

    <!-- Categories Table -->
    <div class="table-section">
        <div class="table-container">
            <table id="categoriesTable" class="data-table">
                <thead>
                    <tr>
                        <th width="50">Sort</th>
                        <th width="80">ID</th>
                        <th width="80">Image</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th width="120">Created</th>
                        <th width="120">Actions</th>
                    </tr>
                </thead>
                <tbody id="sortable">
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $category): ?>
                            <tr data-id="<?= sanitizeInput($category['id']) ?>">
                                <td class="sort-handle">
                                    <i class="fas fa-grip-vertical"></i>
                                </td>
                                <td class="text-center"><?= sanitizeInput($category['id']) ?></td>
                                <td class="image-cell">
                                    <?php if (!empty($category['image_url']) && file_exists($category['image_url'])): ?>
                                        <img src="<?= sanitizeInput($category['image_url']) ?>" alt="Category Image" class="category-image">
                                    <?php else: ?>
                                        <div class="placeholder-image">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="name-cell">
                                    <span class="category-name"><?= sanitizeInput($category['name']) ?></span>
                                </td>
                                <td class="description-cell">
                                    <span class="category-description"><?= sanitizeInput($category['description']) ?></span>
                                </td>
                                <td class="date-cell">
                                    <?= date('M d, Y', strtotime($category['created_at'])) ?>
                                </td>
                                <td class="actions-cell">
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-edit edit-category-btn"
                                            data-id="<?= $category['id'] ?>"
                                            data-name="<?= sanitizeInput($category['name']) ?>"
                                            data-description="<?= sanitizeInput($category['description']) ?>"
                                            data-image="<?= sanitizeInput($category['image_url']) ?>"
                                            data-bs-toggle="offcanvas"
                                            data-bs-target="#editCategoryOffcanvas"
                                            title="Edit Category">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-delete delete-category-btn"
                                            data-id="<?= $category['id'] ?>"
                                            data-name="<?= sanitizeInput($category['name']) ?>"
                                            title="Delete Category">
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
                                    <i class="fas fa-folder-open"></i>
                                    <h3>No Categories Found</h3>
                                    <p>Start by adding your first category to organize your products.</p>
                                    <button class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#addCategoryOffcanvas">
                                        <i class="fas fa-plus"></i> Add Category
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Category Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="addCategoryOffcanvas" aria-labelledby="addCategoryOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="addCategoryOffcanvasLabel">Add New Category</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="categories.php?action=add" enctype="multipart/form-data" class="category-form">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">
                
                <div class="form-group">
                    <label for="add-name" class="form-label">Category Name <span class="required">*</span></label>
                    <input type="text" class="form-control" id="add-name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="add-description" class="form-label">Description</label>
                    <textarea class="form-control" id="add-description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="add-image" class="form-label">Category Image</label>
                    <input type="file" class="form-control" id="add-image" name="image_file" accept="image/*">
                    <small class="form-text">Max size: 2MB. Supported formats: JPG, PNG, GIF</small>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="offcanvas">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Category Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="editCategoryOffcanvas" aria-labelledby="editCategoryOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="editCategoryOffcanvasLabel">Edit Category</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="categories.php?action=edit" enctype="multipart/form-data" class="category-form">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">
                <input type="hidden" name="id" id="edit-id" value="">
                
                <div class="form-group">
                    <label for="edit-name" class="form-label">Category Name <span class="required">*</span></label>
                    <input type="text" class="form-control" id="edit-name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit-description" class="form-label">Description</label>
                    <textarea class="form-control" id="edit-description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit-image" class="form-label">Change Image</label>
                    <input type="file" class="form-control" id="edit-image" name="image_file" accept="image/*">
                    <small class="form-text">Max size: 2MB. Supported formats: JPG, PNG, GIF</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Current Image</label>
                    <div class="current-image">
                        <img src="" alt="Current Image" id="current-image" class="preview-image">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="offcanvas">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div class="toast-container">
        <div id="toast-container"></div>
    </div>
</div>

<style>
    /* Categories Page Styles */
    .categories-content {
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

    /* Sort Handle */
    .sort-handle {
        text-align: center;
        color: #9ca3af;
        cursor: move;
    }

    .sort-handle i {
        font-size: 1rem;
    }

    /* Image Cell */
    .image-cell {
        text-align: center;
    }

    .category-image {
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

    /* Name and Description */
    .name-cell .category-name {
        font-weight: 600;
        color: #0f172a;
    }

    .description-cell .category-description {
        color: #64748b;
        font-size: 0.875rem;
        line-height: 1.4;
    }

    .date-cell {
        font-size: 0.875rem;
        color: #64748b;
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

    /* Offcanvas Styles */
    .offcanvas {
        border-left: 1px solid var(--border-color);
    }

    .offcanvas-header {
        border-bottom: 1px solid var(--border-color);
        padding: 1.5rem;
    }

    .offcanvas-title {
        font-weight: 600;
        color: #0f172a;
    }

    .offcanvas-body {
        padding: 1.5rem;
    }

    /* Form Styles */
    .category-form {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-label {
        font-weight: 500;
        color: #374151;
        font-size: 0.875rem;
    }

    .required {
        color: #ef4444;
    }

    .form-control {
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
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        padding-top: 1rem;
        border-top: 1px solid var(--border-color);
    }

    /* Current Image Preview */
    .current-image {
        display: flex;
        justify-content: center;
    }

    .preview-image {
        width: 120px;
        height: 120px;
        border-radius: 8px;
        object-fit: cover;
        border: 1px solid var(--border-color);
    }

    /* Toast Container */
    .toast-container {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        z-index: 1100;
    }

    /* Sortable Placeholder */
    .sortable-placeholder {
        background: #f8fafc;
        border: 2px dashed #cbd5e1;
        height: 80px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .categories-content {
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

        .action-buttons {
            flex-direction: column;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem 0.5rem;
        }
    }
</style>

<script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#categoriesTable').DataTable({
            "paging": true,
            "searching": true,
            "info": true,
            "order": [[5, "desc"]],
            "dom": '<"row mb-3"' +
                '<"col-12 d-flex justify-content-between align-items-center"lBf>' +
                '>' +
                'rt' +
                '<"row mt-3"' +
                '<"col-sm-12 col-md-6 d-flex justify-content-start"i>' +
                '<"col-sm-12 col-md-6 d-flex justify-content-end"p>' +
                '>',
            "buttons": [{
                    text: '<i class="fas fa-plus"></i> Add Category',
                    className: 'btn btn-primary btn-sm',
                    action: function() {
                        $('#addCategoryOffcanvas').offcanvas('show');
                    }
                },
                {
                    extend: 'csv',
                    text: '<i class="fas fa-file-csv"></i> Export CSV',
                    className: 'btn btn-secondary btn-sm'
                },
                {
                    extend: 'pdf',
                    text: '<i class="fas fa-file-pdf"></i> Export PDF',
                    className: 'btn btn-secondary btn-sm'
                }
            ],
            "language": {
                url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/de-DE.json'
            }
        });

        // Initialize Sortable
        $("#sortable").sortable({
            handle: ".sort-handle",
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
                            showToast('Success', response.message, 'success');
                        } else {
                            showToast('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        showToast('Error', 'An error occurred while updating positions.', 'error');
                    }
                });
            }
        }).disableSelection();

        // Edit Category Button
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

        // Delete Category Button
        $('.delete-category-btn').on('click', function() {
            var id = $(this).data('id');
            var name = $(this).data('name');
            
            Swal.fire({
                title: 'Are you sure?',
                text: `Do you want to delete the category "${name}"? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
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

        // Show Toast Function
        function showToast(title, message, type) {
            const toastHtml = `
                <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            $('#toast-container').html(toastHtml);
            $('.toast').toast({
                delay: 5000
            }).toast('show');
        }

        // Show existing toast if available
        <?php if (isset($_SESSION['toast'])): ?>
            showToast('<?= $_SESSION['toast']['type'] === 'success' ? 'Success' : 'Error' ?>', '<?= $_SESSION['toast']['message'] ?>', '<?= $_SESSION['toast']['type'] ?>');
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>
    });
</script>

<?php require_once 'includes/footer.php'; ?>