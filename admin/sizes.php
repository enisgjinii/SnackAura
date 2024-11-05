<?php
// admin/sizes.php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Initialize variables
$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$message = '';

// Handle different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        // Add Size
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);

        if ($name === '') {
            $message = '<div class="alert alert-danger">Size name is required.</div>';
        } else {
            $stmt = $pdo->prepare('INSERT INTO sizes (name, description) VALUES (?, ?)');
            try {
                $stmt->execute([$name, $description]);
                $message = '<div class="alert alert-success">Size added successfully.</div>';
                // Redirect to view
                header('Location: sizes.php');
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') { // Integrity constraint violation
                    $message = '<div class="alert alert-danger">Size name already exists.</div>';
                } else {
                    $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    } elseif ($action === 'edit' && $id > 0) {
        // Edit Size
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);

        if ($name === '') {
            $message = '<div class="alert alert-danger">Size name is required.</div>';
        } else {
            $stmt = $pdo->prepare('UPDATE sizes SET name = ?, description = ? WHERE id = ?');
            try {
                $stmt->execute([$name, $description, $id]);
                $message = '<div class="alert alert-success">Size updated successfully.</div>';
                // Redirect to view
                header('Location: sizes.php');
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') { // Integrity constraint violation
                    $message = '<div class="alert alert-danger">Size name already exists.</div>';
                } else {
                    $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    }
} elseif ($action === 'delete' && $id > 0) {
    // Delete Size
    $stmt = $pdo->prepare('DELETE FROM sizes WHERE id = ?');
    $stmt->execute([$id]);
    $message = '<div class="alert alert-success">Size deleted successfully.</div>';
    // Redirect to view
    header('Location: sizes.php');
    exit();
}

// Fetch all sizes for viewing
if ($action === 'view') {
    $stmt = $pdo->query('SELECT * FROM sizes ORDER BY name ASC');
    $sizes = $stmt->fetchAll();
}
?>

<?php if ($action === 'view'): ?>
    <h2>Sizes</h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <a href="sizes.php?action=add" class="btn btn-primary mb-3">Add Size</a>
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sizes as $size): ?>
                <tr>
                    <td><?= htmlspecialchars($size['id']) ?></td>
                    <td><?= htmlspecialchars($size['name']) ?></td>
                    <td><?= htmlspecialchars($size['description']) ?></td>
                    <td>
                        <a href="sizes.php?action=edit&id=<?= $size['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a href="sizes.php?action=delete&id=<?= $size['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this size?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<?php elseif ($action === 'add'): ?>
    <h2>Add Size</h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <form method="POST" action="sizes.php?action=add">
        <div class="mb-3">
            <label for="name" class="form-label">Size Name *</label>
            <input type="text" class="form-control" id="name" name="name" required value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description"><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
        </div>
        <button type="submit" class="btn btn-success">Add Size</button>
        <a href="sizes.php" class="btn btn-secondary">Cancel</a>
    </form>

<?php elseif ($action === 'edit' && $id > 0): ?>
    <?php
    // Fetch size details
    $stmt = $pdo->prepare('SELECT * FROM sizes WHERE id = ?');
    $stmt->execute([$id]);
    $size = $stmt->fetch();

    if (!$size) {
        echo '<div class="alert alert-danger">Size not found.</div>';
        require_once 'includes/footer.php';
        exit();
    }
    ?>
    <h2>Edit Size</h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <form method="POST" action="sizes.php?action=edit&id=<?= $id ?>">
        <div class="mb-3">
            <label for="name" class="form-label">Size Name *</label>
            <input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($size['name']) ?>">
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description"><?= htmlspecialchars($size['description']) ?></textarea>
        </div>
        <button type="submit" class="btn btn-success">Update Size</button>
        <a href="sizes.php" class="btn btn-secondary">Cancel</a>
    </form>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>