<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/db_connect.php';
require_once 'includes/header.php';

function s($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$valid_categories = ['Extras', 'Sauces', 'Dressing'];
$category = $_GET['category'] ?? 'Extras';
if (!in_array($category, $valid_categories)) {
    $category = 'Extras';
}

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

if ($action === 'add') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name  = trim($_POST['name'] ?? '');
        $price = trim($_POST['price'] ?? '0.00');
        if ($name === '') {
            $message = '<div class="alert alert-danger">Name is required.</div>';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO extras_products (name, category, price) VALUES (?, ?, ?)");
                $stmt->execute([$name, $category, $price]);
                $message = '<div class="alert alert-success">Item added successfully.</div>';
            } catch (PDOException $e) {
                $message = '<div class="alert alert-danger">DB Error: ' . s($e->getMessage()) . '</div>';
            }
        }
    }
} elseif ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM extras_products WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        $message = '<div class="alert alert-danger">Item not found.</div>';
        $action = 'list';
    } else {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name  = trim($_POST['name'] ?? '');
            $price = trim($_POST['price'] ?? '0.00');
            if ($name === '') {
                $message = '<div class="alert alert-danger">Name is required.</div>';
            } else {
                try {
                    $stmtUpd = $pdo->prepare("UPDATE extras_products SET name=?, price=? WHERE id=?");
                    $stmtUpd->execute([$name, $price, $id]);
                    $message = '<div class="alert alert-success">Changes saved successfully.</div>';
                    $item['name']  = $name;
                    $item['price'] = $price;
                } catch (PDOException $e) {
                    $message = '<div class="alert alert-danger">DB Error: ' . s($e->getMessage()) . '</div>';
                }
            }
        }
        $category = $item['category'];
    }
} elseif ($action === 'delete' && $id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT category FROM extras_products WHERE id=?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $stmtDel = $pdo->prepare("DELETE FROM extras_products WHERE id = ?");
            $stmtDel->execute([$id]);
            $_SESSION['message'] = '<div class="alert alert-success">Item deleted successfully.</div>';
            header('Location: extras.php?category=' . $existing['category']);
            exit();
        } else {
            $message = '<div class="alert alert-danger">Item not found.</div>';
        }
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">DB Error: ' . s($e->getMessage()) . '</div>';
    }
}

$stmtList = $pdo->prepare("SELECT * FROM extras_products WHERE category=? ORDER BY name ASC");
$stmtList->execute([$category]);
$items = $stmtList->fetchAll(PDO::FETCH_ASSOC);

if (isset($_SESSION['message'])) {
    $message .= $_SESSION['message'];
    unset($_SESSION['message']);
}
?>

<!-- Extras Content -->
<div class="extras-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-text">
                <h1 class="page-title">Extras & Add-ons</h1>
                <p class="page-subtitle">Manage extras, sauces, and dressings for your menu items</p>
            </div>
            <div class="header-actions">
                <a href="extras.php?action=add&category=<?= s($category) ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New
                </a>
            </div>
        </div>
    </div>

    <!-- Alert Container -->
    <?php if ($message): ?>
        <div class="alert-container">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- Category Navigation -->
    <div class="category-nav">
        <div class="nav-tabs">
            <a href="extras.php?category=Extras" class="nav-tab <?= ($category === 'Extras') ? 'active' : '' ?>">
                <i class="fas fa-plus-circle"></i>
                <span>Extras</span>
            </a>
            <a href="extras.php?category=Sauces" class="nav-tab <?= ($category === 'Sauces') ? 'active' : '' ?>">
                <i class="fas fa-tint"></i>
                <span>Sauces</span>
            </a>
            <a href="extras.php?category=Dressing" class="nav-tab <?= ($category === 'Dressing') ? 'active' : '' ?>">
                <i class="fas fa-leaf"></i>
                <span>Dressings</span>
            </a>
        </div>
    </div>

    <?php if ($action === 'edit' && !empty($item)): ?>
        <!-- Edit Form -->
        <div class="form-section">
            <div class="form-card">
                <div class="card-header">
                    <h3>Edit <?= s($item['category']) ?></h3>
                    <i class="fas fa-edit"></i>
                </div>
                <div class="card-content">
                    <form method="POST" action="extras.php?action=edit&id=<?= (int)$item['id'] ?>" class="extras-form">
                        <div class="form-group">
                            <label for="edit_name" class="form-label">Name <span class="required">*</span></label>
                            <input type="text" id="edit_name" name="name" class="form-control" required value="<?= s($item['name']) ?>" placeholder="Enter item name">
                        </div>
                        <div class="form-group">
                            <label for="edit_price" class="form-label">Price ($)</label>
                            <input type="number" id="edit_price" name="price" step="0.01" class="form-control" value="<?= s($item['price']) ?>" placeholder="0.00">
                        </div>
                        <div class="form-actions">
                            <a href="extras.php?category=<?= s($item['category']) ?>" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <?php elseif ($action === 'add'): ?>
        <!-- Add Form -->
        <div class="form-section">
            <div class="form-card">
                <div class="card-header">
                    <h3>Add New <?= s($category) ?></h3>
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="card-content">
                    <form method="POST" action="extras.php?action=add&category=<?= s($category) ?>" class="extras-form">
                        <div class="form-group">
                            <label for="add_name" class="form-label">Name <span class="required">*</span></label>
                            <input type="text" id="add_name" name="name" class="form-control" required placeholder="Enter item name">
                        </div>
                        <div class="form-group">
                            <label for="add_price" class="form-label">Price ($)</label>
                            <input type="number" id="add_price" name="price" step="0.01" class="form-control" placeholder="0.00">
                        </div>
                        <div class="form-actions">
                            <a href="extras.php?category=<?= s($category) ?>" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Item
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Items List -->
        <div class="table-section">
            <div class="table-header">
                <div class="table-info">
                    <h3><?= s($category) ?></h3>
                    <span class="item-count"><?= count($items) ?> items</span>
                </div>
            </div>
            
            <div class="table-container">
                <?php if (!empty($items)): ?>
                    <table id="extrasTable" class="data-table">
                        <thead>
                            <tr>
                                <th width="80">ID</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $row): ?>
                                <tr>
                                    <td class="id-cell">
                                        <span class="item-id">#<?= (int)$row['id'] ?></span>
                                    </td>
                                    <td class="name-cell">
                                        <span class="item-name"><?= s($row['name']) ?></span>
                                    </td>
                                    <td class="category-cell">
                                        <span class="category-badge category-<?= strtolower($row['category']) ?>">
                                            <?= s($row['category']) ?>
                                        </span>
                                    </td>
                                    <td class="price-cell">
                                        <span class="price-value">$<?= number_format($row['price'], 2) ?></span>
                                    </td>
                                    <td class="actions-cell">
                                        <div class="action-buttons">
                                            <a href="extras.php?action=edit&id=<?= (int)$row['id'] ?>" 
                                               class="btn btn-sm btn-edit" title="Edit Item">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-delete delete-item-btn" 
                                                    data-id="<?= (int)$row['id'] ?>" 
                                                    data-name="<?= s($row['name']) ?>"
                                                    data-category="<?= s($row['category']) ?>"
                                                    title="Delete Item">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-content">
                            <i class="fas fa-box-open"></i>
                            <h3>No <?= s($category) ?> Found</h3>
                            <p>Start by adding your first <?= strtolower($category) ?> item.</p>
                            <a href="extras.php?action=add&category=<?= s($category) ?>" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add <?= s($category) ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    /* Extras Page Styles */
    .extras-content {
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

    /* Category Navigation */
    .category-nav {
        margin-bottom: 2rem;
    }

    .nav-tabs {
        display: flex;
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        overflow: hidden;
    }

    .nav-tab {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 1rem 1.5rem;
        text-decoration: none;
        color: #64748b;
        font-weight: 500;
        transition: all 0.15s ease;
        border-right: 1px solid var(--border-color);
    }

    .nav-tab:last-child {
        border-right: none;
    }

    .nav-tab:hover {
        background: #f8fafc;
        color: #374151;
    }

    .nav-tab.active {
        background: #3b82f6;
        color: white;
    }

    .nav-tab i {
        font-size: 1rem;
    }

    /* Form Section */
    .form-section {
        margin-bottom: 2rem;
    }

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

    /* Form Actions */
    .form-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 2rem;
    }

    /* Table Section */
    .table-section {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        overflow: hidden;
    }

    .table-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        background: #f8fafc;
    }

    .table-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .table-info h3 {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 600;
        color: #0f172a;
    }

    .item-count {
        color: #64748b;
        font-size: 0.875rem;
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
    .id-cell {
        text-align: center;
    }

    .item-id {
        font-family: 'Courier New', monospace;
        font-weight: 600;
        color: #64748b;
        font-size: 0.875rem;
    }

    .name-cell {
        font-weight: 600;
        color: #0f172a;
    }

    .category-cell {
        text-align: center;
    }

    .category-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    .category-extras {
        background: #dbeafe;
        color: #1e40af;
    }

    .category-sauces {
        background: #fef3c7;
        color: #92400e;
    }

    .category-dressing {
        background: #d1fae5;
        color: #065f46;
    }

    .price-cell {
        font-weight: 600;
        color: #059669;
    }

    .price-value {
        font-family: 'Courier New', monospace;
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
    .empty-state {
        padding: 3rem 1.5rem;
        text-align: center;
    }

    .empty-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
    }

    .empty-content i {
        font-size: 3rem;
        color: #9ca3af;
    }

    .empty-content h3 {
        color: #374151;
        margin: 0;
        font-size: 1.25rem;
    }

    .empty-content p {
        color: #64748b;
        margin: 0;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .extras-content {
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

        .nav-tabs {
            flex-direction: column;
        }

        .nav-tab {
            border-right: none;
            border-bottom: 1px solid var(--border-color);
        }

        .nav-tab:last-child {
            border-bottom: none;
        }

        .action-buttons {
            flex-direction: column;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem 0.5rem;
        }

        .form-actions {
            flex-direction: column;
        }
    }
</style>

<!-- JavaScript for Extras Page -->
<script>
    $(document).ready(function() {
        // Initialize DataTable for extras list
        if ($('#extrasTable').length) {
            $('#extrasTable').DataTable({
                responsive: true,
                order: [[1, 'asc']], // Sort by name by default
                pageLength: 25,
                language: {
                    search: "Search items:",
                    lengthMenu: "Show _MENU_ items per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ items",
                    emptyTable: "No items found"
                }
            });
        }

        // Delete item functionality
        $('.delete-item-btn').on('click', function() {
            const itemId = $(this).data('id');
            const itemName = $(this).data('name');
            const itemCategory = $(this).data('category');
            
            if (confirm(`Are you sure you want to delete "${itemName}"? This action cannot be undone.`)) {
                window.location.href = `extras.php?action=delete&id=${itemId}`;
            }
        });

        // Form validation
        $('.extras-form').on('submit', function(e) {
            const name = $(this).find('input[name="name"]').val().trim();
            if (!name) {
                e.preventDefault();
                alert('Please enter a name for the item.');
                return false;
            }
        });

        // Auto-focus on name input when adding new item
        if ($('#add_name').length) {
            $('#add_name').focus();
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>