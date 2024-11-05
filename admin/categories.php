<?php
// admin/categories.php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Initialize variables
$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$message = '';

// Handle different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        // Add Category
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);

        if ($name === '') {
            $message = '<div class="alert alert-danger">Category name is required.</div>';
        } else {
            $stmt = $pdo->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
            try {
                $stmt->execute([$name, $description]);
                $message = '<div class="alert alert-success">Category added successfully.</div>';
                // Redirect to view
                header('Location: categories.php');
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') { // Integrity constraint violation
                    $message = '<div class="alert alert-danger">Category name already exists.</div>';
                } else {
                    $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    } elseif ($action === 'edit' && $id > 0) {
        // Edit Category
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);

        if ($name === '') {
            $message = '<div class="alert alert-danger">Category name is required.</div>';
        } else {
            $stmt = $pdo->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?');
            try {
                $stmt->execute([$name, $description, $id]);
                $message = '<div class="alert alert-success">Category updated successfully.</div>';
                // Redirect to view
                header('Location: categories.php');
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') { // Integrity constraint violation
                    $message = '<div class="alert alert-danger">Category name already exists.</div>';
                } else {
                    $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    }
} elseif ($action === 'delete' && $id > 0) {
    // Delete Category
    $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
    $stmt->execute([$id]);
    $message = '<div class="alert alert-success">Category deleted successfully.</div>';
    // Redirect to view
    header('Location: categories.php');
    exit();
}

// Fetch all categories for viewing
if ($action === 'view') {
    $stmt = $pdo->query('SELECT * FROM categories ORDER BY created_at DESC');
    $categories = $stmt->fetchAll();
}
?>

<?php if ($action === 'view'): ?>
    <h2>Categories</h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <a href="categories.php?action=add" class="btn btn-primary mb-3">Add Category</a>
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Description</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $category): ?>
                <tr>
                    <td><?= htmlspecialchars($category['id']) ?></td>
                    <td><?= htmlspecialchars($category['name']) ?></td>
                    <td><?= htmlspecialchars($category['description']) ?></td>
                    <td><?= htmlspecialchars($category['created_at']) ?></td>
                    <td>
                        <a href="categories.php?action=edit&id=<?= $category['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a href="categories.php?action=delete&id=<?= $category['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this category?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<?php elseif ($action === 'add'): ?>
    <h2>Add Category</h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <form method="POST" action="categories.php?action=add">
        <div class="mb-3">
            <label for="name" class="form-label">Category Name *</label>
            <input type="text" class="form-control" id="name" name="name" required value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description"><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
        </div>
        <button type="submit" class="btn btn-success">Add Category</button>
        <a href="categories.php" class="btn btn-secondary">Cancel</a>
    </form>

<?php elseif ($action === 'edit' && $id > 0): ?>
    <?php
    // Fetch category details
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$id]);
    $category = $stmt->fetch();

    if (!$category) {
        echo '<div class="alert alert-danger">Category not found.</div>';
        require_once 'includes/footer.php';
        exit();
    }
    ?>
    <h2>Edit Category</h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <form method="POST" action="categories.php?action=edit&id=<?= $id ?>">
        <div class="mb-3">
            <label for="name" class="form-label">Category Name *</label>
            <input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($category['name']) ?>">
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description"><?= htmlspecialchars($category['description']) ?></textarea>
        </div>
        <button type="submit" class="btn btn-success">Update Category</button>
        <a href="categories.php" class="btn btn-secondary">Cancel</a>
    </form>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>