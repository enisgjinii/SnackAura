<?php
// admin/extras.php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Initialize variables
$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$message = '';

// Handle different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        // Add Extra
        $name = trim($_POST['name']);
        $price = trim($_POST['price']);

        // Validate inputs
        if ($name === '' || $price === '') {
            $message = '<div class="alert alert-danger">Name and Price are required.</div>';
        } elseif (!is_numeric($price) || $price < 0) {
            $message = '<div class="alert alert-danger">Price must be a non-negative number.</div>';
        } else {
            // Insert into database
            $stmt = $pdo->prepare('INSERT INTO extras (name, price) VALUES (?, ?)');
            try {
                $stmt->execute([$name, $price]);
                $message = '<div class="alert alert-success">Extra added successfully.</div>';
                // Redirect to view
                header('Location: extras.php');
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') { // Integrity constraint violation
                    $message = '<div class="alert alert-danger">Extra name already exists.</div>';
                } else {
                    $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    } elseif ($action === 'edit' && $id > 0) {
        // Edit Extra
        $name = trim($_POST['name']);
        $price = trim($_POST['price']);

        // Validate inputs
        if ($name === '' || $price === '') {
            $message = '<div class="alert alert-danger">Name and Price are required.</div>';
        } elseif (!is_numeric($price) || $price < 0) {
            $message = '<div class="alert alert-danger">Price must be a non-negative number.</div>';
        } else {
            // Update the database
            $stmt = $pdo->prepare('UPDATE extras SET name = ?, price = ? WHERE id = ?');
            try {
                $stmt->execute([$name, $price, $id]);
                $message = '<div class="alert alert-success">Extra updated successfully.</div>';
                // Redirect to view
                header('Location: extras.php');
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') { // Integrity constraint violation
                    $message = '<div class="alert alert-danger">Extra name already exists.</div>';
                } else {
                    $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    }
} elseif ($action === 'delete' && $id > 0) {
    // Delete Extra
    $stmt = $pdo->prepare('DELETE FROM extras WHERE id = ?');
    try {
        $stmt->execute([$id]);
        $message = '<div class="alert alert-success">Extra deleted successfully.</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Error deleting extra: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    // Redirect to view after deletion
    header('Location: extras.php');
    exit();
}

// Fetch all extras for viewing
if ($action === 'view') {
    $stmt = $pdo->query('SELECT * FROM extras ORDER BY created_at DESC');
    $extras = $stmt->fetchAll();
}

// Fetch extra details for editing
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM extras WHERE id = ?');
    $stmt->execute([$id]);
    $extra = $stmt->fetch();
    if (!$extra) {
        echo '<div class="alert alert-danger">Extra not found.</div>';
        require_once 'includes/footer.php';
        exit();
    }
}
?>

<?php if ($action === 'view'): ?>
    <h2>Extras</h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <a href="extras.php?action=add" class="btn btn-primary mb-3">Add Extra</a>
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Price (€)</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($extras as $extra): ?>
                <tr>
                    <td><?= htmlspecialchars($extra['id']) ?></td>
                    <td><?= htmlspecialchars($extra['name']) ?></td>
                    <td><?= number_format($extra['price'], 2) ?></td>
                    <td><?= htmlspecialchars($extra['created_at']) ?></td>
                    <td>
                        <a href="extras.php?action=edit&id=<?= $extra['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a href="extras.php?action=delete&id=<?= $extra['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this extra?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<?php elseif ($action === 'add'): ?>
    <h2>Add Extra</h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <form method="POST" action="extras.php?action=add">
        <div class="mb-3">
            <label for="name" class="form-label">Extra Name *</label>
            <input type="text" class="form-control" id="name" name="name" required value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
        </div>
        <div class="mb-3">
            <label for="price" class="form-label">Price (€) *</label>
            <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" required value="<?= isset($_POST['price']) ? htmlspecialchars($_POST['price']) : '' ?>">
        </div>
        <button type="submit" class="btn btn-success">Add Extra</button>
        <a href="extras.php" class="btn btn-secondary">Cancel</a>
    </form>

<?php elseif ($action === 'edit' && $id > 0): ?>
    <h2>Edit Extra</h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <form method="POST" action="extras.php?action=edit&id=<?= $id ?>">
        <div class="mb-3">
            <label for="name" class="form-label">Extra Name *</label>
            <input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($extra['name']) ?>">
        </div>
        <div class="mb-3">
            <label for="price" class="form-label">Price (€) *</label>
            <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" required value="<?= htmlspecialchars($extra['price']) ?>">
        </div>
        <button type="submit" class="btn btn-success">Update Extra</button>
        <a href="extras.php" class="btn btn-secondary">Cancel</a>
    </form>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>