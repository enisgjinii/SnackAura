<?php
// categories.php

ob_start();
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Create categories table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `categories` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL UNIQUE,
            `description` TEXT,
            `image_url` VARCHAR(255),
            `position` INT DEFAULT 0,
            `is_active` BOOLEAN DEFAULT TRUE,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Tabelle konnte nicht erstellt werden: ' . htmlspecialchars($e->getMessage())];
    header('Location: categories.php?action=list');
    exit();
}

$action = $_REQUEST['action'] ?? 'list';
$id     = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$message = '';

// Function to sanitize inputs
function sanitizeInput($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Function to validate category data
function validateCategory($pdo, $data, $id = 0)
{
    $errors = [];
    $name = sanitizeInput($data['name'] ?? '');
    $description = sanitizeInput($data['description'] ?? '');
    $is_active = isset($data['is_active']) ? 1 : 0;

    // Required fields
    if (empty($name)) {
        $errors[] = 'Der Kategoriename ist erforderlich.';
    }

    // Name uniqueness
    if (!empty($name)) {
        $sql = "SELECT COUNT(*) FROM categories WHERE name = ?";
        $params = [$name];
        if ($id > 0) {
            $sql .= " AND id != ?";
            $params[] = $id;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Der Kategoriename existiert bereits.';
        }
    }

    return [$errors, compact('name', 'description', 'is_active')];
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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'Unbekannter Fehler'];

    if ($_POST['ajax_action'] === 'update_status') {
        $id = (int)($_POST['id'] ?? 0);
        $is_active = (int)($_POST['is_active'] ?? 0);

        if ($id > 0) {
            try {
                $pdo->beginTransaction();
                $current_time = date('Y-m-d H:i:s');
                $status_text = $is_active ? 'aktiviert' : 'deaktiviert';
                $stmt = $pdo->prepare('UPDATE categories SET is_active = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$is_active, $id]);
                $pdo->commit();
                $response = ['status' => 'success', 'message' => "Kategorie erfolgreich $status_text."];
            } catch (PDOException $e) {
                $pdo->rollBack();
                $response = ['status' => 'error', 'message' => 'Datenbankfehler: ' . sanitizeInput($e->getMessage())];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'Ungültige Kategorie-ID'];
        }
    } elseif ($_POST['ajax_action'] === 'update_position') {
        $positions = $_POST['positions'] ?? [];
        
        if (!empty($positions)) {
            try {
                $pdo->beginTransaction();
                foreach ($positions as $position => $category_id) {
                    $stmt = $pdo->prepare('UPDATE categories SET position = ? WHERE id = ?');
                    $stmt->execute([$position + 1, $category_id]);
                }
                $pdo->commit();
                $response = ['status' => 'success', 'message' => 'Reihenfolge erfolgreich aktualisiert.'];
            } catch (PDOException $e) {
                $pdo->rollBack();
                $response = ['status' => 'error', 'message' => 'Datenbankfehler: ' . sanitizeInput($e->getMessage())];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'Keine Positionen übermittelt'];
        }
    }

    echo json_encode($response);
    exit;
}

// Handle Add/Edit form submissions
if (($action === 'add' || $action === 'edit') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize form inputs
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validate inputs
    list($errors, $validatedData) = validateCategory($pdo, $_POST, $id);

    if ($errors) {
        $_SESSION['toast'] = ['type' => 'danger', 'message' => implode('<br>', $errors)];
        header("Location: categories.php?action=" . ($action === 'add' ? 'add' : 'edit') . ($action === 'edit' ? "&id=$id" : ''));
        exit();
    } else {
        try {
            // Handle image upload
            $image_url = handleImageUpload($_FILES['image_file'] ?? null, $action === 'edit' ? $_POST['current_image'] ?? '' : '');

            // Prepare SQL and parameters based on action
            if ($action === 'add') {
                // Get the next position
                $stmt = $pdo->query('SELECT MAX(position) AS max_position FROM categories');
                $result = $stmt->fetch();
                $position = $result['max_position'] !== null ? $result['max_position'] + 1 : 1;
                
                $sql = 'INSERT INTO categories (name, description, image_url, is_active, position) VALUES (?, ?, ?, ?, ?)';
                $params = [
                    $validatedData['name'],
                    $validatedData['description'],
                    $image_url,
                    $validatedData['is_active'],
                    $position
                ];
            } else { // Edit
                $sql = 'UPDATE categories SET name = ?, description = ?, image_url = ?, is_active = ?, updated_at = NOW() WHERE id = ?';
                $params = [
                    $validatedData['name'],
                    $validatedData['description'],
                    $image_url,
                    $validatedData['is_active'],
                    $id
                ];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Kategorie erfolgreich ' . ($action === 'add' ? 'hinzugefügt.' : 'aktualisiert.')];
            header("Location: categories.php?action=list");
            exit();
        } catch (PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler beim Verarbeiten der Daten: ' . htmlspecialchars($e->getMessage())];
            header("Location: categories.php?action=" . ($action === 'add' ? 'add' : 'edit') . ($action === 'edit' ? "&id=$id" : ''));
            exit();
        }
    }
}

// Handle category deletion
if ($action === 'delete' && $id > 0) {
    try {
        // Check if category has products
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Kategorie kann nicht gelöscht werden, da sie Produkte enthält.'];
            header("Location: categories.php?action=list");
            exit();
        }

        // Get image path before deletion
        $stmt = $pdo->prepare('SELECT image_url FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        $category = $stmt->fetch();

        // Delete category
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$id]);

        // Delete image file if exists
        if ($category && !empty($category['image_url']) && file_exists($category['image_url'])) {
            unlink($category['image_url']);
        }

        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Kategorie erfolgreich gelöscht.'];
        header("Location: categories.php?action=list");
        exit();
    } catch (PDOException $e) {
        $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler beim Löschen der Kategorie: ' . htmlspecialchars($e->getMessage())];
        header("Location: categories.php?action=list");
        exit();
    }
}

// Fetch categories for listing with optional filters
function getCategories($pdo, $filters = [])
{
    try {
        $query = 'SELECT * FROM categories';
        $conditions = [];
        $params = [];

        if (isset($filters['status']) && $filters['status'] !== '') {
            $conditions[] = 'is_active = ?';
            $params[] = (int)$filters['status'];
        }

        if ($conditions) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $query .= ' ORDER BY position ASC, name ASC';
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Fetch categories for listing
if ($action === 'list') {
    $filters = [];
    if (isset($_GET['filter_status'])) {
        $filters['status'] = sanitizeInput($_GET['filter_status']);
    }

    $categories = getCategories($pdo, $filters);
}

// For edit action, fetch category data
if ($action === 'edit' && $id > 0) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$category) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Category not found.'];
            header("Location: categories.php?action=list");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Error: ' . sanitizeInput($e->getMessage())];
        header("Location: categories.php?action=list");
        exit();
    }
}
?>

<style>
    /* Categories Page - Match Products UI */
    .categories-content {
        padding: 2rem;
        background: var(--content-bg, #f8fafc);
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

    .header-text {
        flex: 1;
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
        padding: 1rem;
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

    .category-name {
        font-weight: 600;
        color: #0f172a;
    }

    .category-description {
        color: #64748b;
        font-size: 0.875rem;
    }

    /* Badges */
    .badge {
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 500;
        margin-left: 0.5rem;
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

    .btn-toggle {
        background: #10b981;
        color: white;
        border: none;
        padding: 0.5rem;
        border-radius: 6px;
        transition: all 0.15s ease;
    }

    .btn-toggle:hover {
        background: #059669;
        color: white;
    }

    .btn-toggle.inactive {
        background: #6b7280;
    }

    .btn-toggle.inactive:hover {
        background: #4b5563;
    }

    .sort-handle {
        cursor: move;
        color: #9ca3af;
        text-align: center;
    }

    .sort-handle:hover {
        color: #6b7280;
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

    /* Image Preview */
    .image-preview {
        margin-top: 1rem;
    }

    .image-preview img {
        max-width: 200px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
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

    /* Buttons */
    .btn {
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-primary {
        background: #3b82f6;
        color: white;
    }

    .btn-primary:hover {
        background: #2563eb;
        color: white;
    }

    .btn-secondary {
        background: #6b7280;
        color: white;
    }

    .btn-secondary:hover {
        background: #4b5563;
        color: white;
    }

    .btn-success {
        background: #10b981;
        color: white;
    }

    .btn-success:hover {
        background: #059669;
        color: white;
    }

    .btn-danger {
        background: #ef4444;
        color: white;
    }

    .btn-danger:hover {
        background: #dc2626;
        color: white;
    }

    .btn-sm {
        padding: 0.5rem;
        font-size: 0.75rem;
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

<!-- Categories Content -->
<div class="categories-content">
    <?php if ($action === 'list'): ?>
        <!-- Categories List View -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-text">
                    <h1 class="page-title">Categories</h1>
                    <p class="page-subtitle">Manage your product categories and organize your inventory</p>
                </div>
                <div class="header-actions">
                    <a href="categories.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Category
                    </a>
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
                            <th width="80">Image</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th width="150">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sortable">
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $category): ?>
                                <tr data-id="<?= sanitizeInput($category['id']) ?>">
                                    <td class="sort-handle">
                                        <i class="fas fa-grip-vertical"></i>
                                    </td>
                                    <td class="image-cell">
                                        <?php if (!empty($category['image_url']) && file_exists($category['image_url'])): ?>
                                            <img src="<?= sanitizeInput($category['image_url']) ?>" alt="Category Image" class="category-image">
                                        <?php else: ?>
                                            <div class="placeholder-image">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="category-name"><?= sanitizeInput($category['name']) ?></span>
                                    </td>
                                    <td>
                                        <span class="category-description"><?= sanitizeInput($category['description']) ?></span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $category['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $category['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($category['created_at'])) ?></td>
                                    <td class="actions-cell">
                                        <div class="action-buttons">
                                            <a href="categories.php?action=edit&id=<?= $category['id'] ?>" class="btn btn-sm btn-edit" title="Edit Category">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-toggle <?= $category['is_active'] ? '' : 'inactive' ?>" 
                                                    title="<?= $category['is_active'] ? 'Deactivate' : 'Activate' ?> Category" 
                                                    data-id="<?= $category['id'] ?>" 
                                                    data-status="<?= $category['is_active'] ?>">
                                                <i class="fas fa-<?= $category['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                            </button>
                                            <button class="btn btn-sm btn-delete delete-category-btn" 
                                                    title="Delete Category" 
                                                    data-id="<?= $category['id'] ?>" 
                                                    data-name="<?= sanitizeInput($category['name']) ?>">
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
                                        <a href="categories.php?action=add" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Add Category
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <!-- Add/Edit Category Form -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-text">
                    <h1 class="page-title"><?= $action === 'edit' ? 'Edit Category' : 'Add New Category' ?></h1>
                    <p class="page-subtitle"><?= $action === 'edit' ? 'Update category information' : 'Create a new category for your products' ?></p>
                </div>
                <div class="header-actions">
                    <a href="categories.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Categories
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
            <form method="POST" enctype="multipart/form-data" action="categories.php?action=<?= $action ?><?= $action === 'edit' ? '&id=' . $id : '' ?>" class="category-form">
                <div class="form-grid">
                    <!-- Basic Information -->
                    <div class="form-card">
                        <div class="card-header">
                            <h3>Basic Information</h3>
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="card-content">
                            <div class="form-group">
                                <label for="name" class="form-label">Category Name <span class="required">*</span></label>
                                <input type="text" id="name" name="name" class="form-control" required 
                                       value="<?= $action === 'edit' ? sanitizeInput($category['name']) : '' ?>" 
                                       placeholder="e.g., Main Dishes">
                            </div>
                            
                            <div class="form-group">
                                <label for="description" class="form-label">Description</label>
                                <textarea id="description" name="description" class="form-control" rows="3" 
                                          placeholder="Category description..."><?= $action === 'edit' ? sanitizeInput($category['description']) : '' ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <input type="checkbox" name="is_active" value="1" <?= ($action === 'edit' && $category['is_active']) ? 'checked' : 'checked' ?>>
                                    Active
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Category Image -->
                    <div class="form-card">
                        <div class="card-header">
                            <h3>Category Image</h3>
                            <i class="fas fa-image"></i>
                        </div>
                        <div class="card-content">
                            <div class="form-group">
                                <label for="image_file" class="form-label">Upload Image</label>
                                <input type="file" id="image_file" name="image_file" class="form-control" accept="image/*">
                                <small class="form-text">Max size: 2MB. Supported formats: JPG, PNG, GIF</small>
                            </div>
                            
                            <?php if ($action === 'edit' && !empty($category['image_url'])): ?>
                                <div class="form-group">
                                    <label class="form-label">Current Image</label>
                                    <div class="image-preview">
                                        <img src="<?= sanitizeInput($category['image_url']) ?>" alt="Current Image">
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> <?= $action === 'edit' ? 'Update Category' : 'Save Category' ?>
                    </button>
                    <a href="categories.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    <?php if ($action === 'list'): ?>
        // Initialize DataTable
        $('#categoriesTable').DataTable({
            responsive: true,
            order: [[2, 'asc']], // Sort by name by default
            pageLength: 25,
            language: {
                search: "Search categories:",
                lengthMenu: "Show _MENU_ categories per page",
                info: "Showing _START_ to _END_ of _TOTAL_ categories",
                emptyTable: "No categories found"
            }
        });

        // Handle Status Toggle
        $(document).on('click', '.btn-toggle', function() {
            let id = $(this).data('id');
            let currentStatus = $(this).data('status');
            let newStatus = currentStatus ? 0 : 1;
            
            $.post('categories.php', {
                ajax_action: 'update_status',
                id: id,
                is_active: newStatus
            }, function(response) {
                if (response.status === 'success') {
                    showToast(response.message, 'success');
                    location.reload();
                } else {
                    showToast(response.message, 'danger');
                }
            }, 'json').fail(() => {
                showToast('Error updating status.', 'danger');
            });
        });

        // Handle Delete Category
        $(document).on('click', '.delete-category-btn', function() {
            let id = $(this).data('id');
            let name = $(this).data('name');
            
            if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
                window.location.href = 'categories.php?action=delete&id=' + id;
            }
        });

        // Sortable functionality
        $("#sortable").sortable({
            handle: ".sort-handle",
            update: function(event, ui) {
                let positions = [];
                $("#sortable tr").each(function(index) {
                    positions.push($(this).data('id'));
                });
                
                $.post('categories.php', {
                    ajax_action: 'update_position',
                    positions: positions
                }, function(response) {
                    if (response.status === 'success') {
                        showToast(response.message, 'success');
                    } else {
                        showToast(response.message, 'danger');
                        location.reload();
                    }
                }, 'json').fail(() => {
                    showToast('Error updating positions.', 'danger');
                    location.reload();
                });
            }
        });

        // Function to show toast notifications
        function showToast(message, type = 'primary') {
            let toastHtml = `
                <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            
            // Create toast container if it doesn't exist
            if ($('#toast-container').length === 0) {
                $('body').append('<div id="toast-container" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100;"></div>');
            }
            
            $('#toast-container').html(toastHtml);
            $('.toast').toast({
                delay: 5000,
                autohide: true
            }).toast('show');
        }

        // Show toast from PHP session
        <?php if (isset($_SESSION['toast'])): ?>
            showToast(`<?= $_SESSION['toast']['message'] ?>`, '<?= $_SESSION['toast']['type'] === 'success' ? 'success' : 'danger' ?>');
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>
    <?php endif; ?>
});
</script>

<?php
require_once 'includes/footer.php';
ob_end_flush();
?>
