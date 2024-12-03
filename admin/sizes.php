<?php
// admin/sizes.php

// Enable error reporting for development (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include necessary files
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
        $sauce_price_increase = isset($_POST['sauce_price_increase']) ? floatval($_POST['sauce_price_increase']) : 0.00;
        $extra_price_increase = isset($_POST['extra_price_increase']) ? floatval($_POST['extra_price_increase']) : 0.00;

        if ($name === '') {
            $message = '<div class="alert alert-danger">Size name is required.</div>';
        } else {
            $stmt = $pdo->prepare('INSERT INTO sizes (name, description, sauce_price_increase, extra_price_increase) VALUES (?, ?, ?, ?)');
            try {
                $stmt->execute([$name, $description, $sauce_price_increase, $extra_price_increase]);
                // Redirect to view with success message
                $_SESSION['message'] = '<div class="alert alert-success">Size added successfully.</div>';
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
        $sauce_price_increase = isset($_POST['sauce_price_increase']) ? floatval($_POST['sauce_price_increase']) : 0.00;
        $extra_price_increase = isset($_POST['extra_price_increase']) ? floatval($_POST['extra_price_increase']) : 0.00;

        if ($name === '') {
            $message = '<div class="alert alert-danger">Size name is required.</div>';
        } else {
            $stmt = $pdo->prepare('UPDATE sizes SET name = ?, description = ?, sauce_price_increase = ?, extra_price_increase = ? WHERE id = ?');
            try {
                $stmt->execute([$name, $description, $sauce_price_increase, $extra_price_increase, $id]);
                // Redirect to view with success message
                $_SESSION['message'] = '<div class="alert alert-success">Size updated successfully.</div>';
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
    try {
        // Begin transaction
        $pdo->beginTransaction();

        // Check if any products are associated with this size
        $stmt_check = $pdo->prepare('SELECT COUNT(*) FROM products WHERE size_id = ?');
        $stmt_check->execute([$id]);
        $count = $stmt_check->fetchColumn();

        if ($count > 0) {
            // Cannot delete size as it's associated with products
            $pdo->rollBack();
            $message = '<div class="alert alert-danger">Cannot delete size because it is associated with existing products.</div>';
        } else {
            // Proceed with deletion
            $stmt = $pdo->prepare('DELETE FROM sizes WHERE id = ?');
            $stmt->execute([$id]);
            $pdo->commit();
            // Redirect to view with success message
            $_SESSION['message'] = '<div class="alert alert-success">Size deleted successfully.</div>';
            header('Location: sizes.php');
            exit();
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = '<div class="alert alert-danger">Error deleting size: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Fetch all sizes for viewing
if ($action === 'view') {
    try {
        $stmt = $pdo->query('SELECT * FROM sizes ORDER BY name ASC');
        $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $sizes = [];
        $message = '<div class="alert alert-danger">Error fetching sizes: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
?>

<?php
// Display any session messages
if (isset($_SESSION['message'])):
?>
    <?= $_SESSION['message'] ?>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>

<?php if ($action === 'view'): ?>
    <h2>Sizes</h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <a href="sizes.php?action=add" class="btn btn-primary mb-3">Add Size</a>
    <table class="table table-bordered table-hover" id="sizesTable">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Description</th>
                <th>Sauce Price Increase ($)</th>
                <th>Extra Price Increase ($)</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($sizes)): ?>
                <?php foreach ($sizes as $size): ?>
                    <tr>
                        <td><?= htmlspecialchars($size['id']) ?></td>
                        <td><?= htmlspecialchars($size['name']) ?></td>
                        <td><?= htmlspecialchars($size['description']) ?></td>
                        <td><?= number_format($size['sauce_price_increase'], 2) ?></td>
                        <td><?= number_format($size['extra_price_increase'], 2) ?></td>
                        <td>
                            <a href="sizes.php?action=edit&id=<?= $size['id'] ?>" class="btn btn-sm btn-warning me-1" data-bs-toggle="tooltip" title="Edit Size">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="sizes.php?action=delete&id=<?= $size['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this size?')" data-bs-toggle="tooltip" title="Delete Size">
                                <i class="fas fa-trash-alt"></i> Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>

            <?php endif; ?>
        </tbody>
    </table>
<?php elseif ($action === 'add'): ?>
    <h2>Add Size</h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <form method="POST" action="sizes.php?action=add">
        <div class="mb-3">
            <label for="name" class="form-label">Size Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="name" name="name" required value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description"><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
        </div>
        <div class="mb-3">
            <label for="sauce_price_increase" class="form-label">Sauce Price Increase ($)</label>
            <input type="number" step="0.01" class="form-control" id="sauce_price_increase" name="sauce_price_increase" value="<?= isset($_POST['sauce_price_increase']) ? htmlspecialchars($_POST['sauce_price_increase']) : '0.00' ?>">
            <div class="form-text">Enter the amount to increase the price of sauces when this size is selected.</div>
        </div>
        <div class="mb-3">
            <label for="extra_price_increase" class="form-label">Extra Price Increase ($)</label>
            <input type="number" step="0.01" class="form-control" id="extra_price_increase" name="extra_price_increase" value="<?= isset($_POST['extra_price_increase']) ? htmlspecialchars($_POST['extra_price_increase']) : '0.00' ?>">
            <div class="form-text">Enter the amount to increase the price of extras when this size is selected.</div>
        </div>
        <button type="submit" class="btn btn-success">Add Size</button>
        <a href="sizes.php" class="btn btn-secondary">Cancel</a>
    </form>
<?php elseif ($action === 'edit' && $id > 0): ?>
    <?php
    // Fetch size details
    $stmt = $pdo->prepare('SELECT * FROM sizes WHERE id = ?');
    $stmt->execute([$id]);
    $size = $stmt->fetch(PDO::FETCH_ASSOC);
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
            <label for="name" class="form-label">Size Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($size['name']) ?>">
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description"><?= htmlspecialchars($size['description']) ?></textarea>
        </div>
        <div class="mb-3">
            <label for="sauce_price_increase" class="form-label">Sauce Price Increase ($)</label>
            <input type="number" step="0.01" class="form-control" id="sauce_price_increase" name="sauce_price_increase" value="<?= htmlspecialchars($size['sauce_price_increase']) ?>">
            <div class="form-text">Enter the amount to increase the price of sauces when this size is selected.</div>
        </div>
        <div class="mb-3">
            <label for="extra_price_increase" class="form-label">Extra Price Increase ($)</label>
            <input type="number" step="0.01" class="form-control" id="extra_price_increase" name="extra_price_increase" value="<?= htmlspecialchars($size['extra_price_increase']) ?>">
            <div class="form-text">Enter the amount to increase the price of extras when this size is selected.</div>
        </div>
        <button type="submit" class="btn btn-success">Update Size</button>
        <a href="sizes.php" class="btn btn-secondary">Cancel</a>
    </form>
<?php endif; ?>

<?php
// Include Footer
require_once 'includes/footer.php';
?>

<!-- Include necessary scripts for DataTables and Bootstrap Tooltips -->
<?php if ($action === 'view'): ?>
    <!-- Scripts for DataTables and tooltips -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#sizesTable').DataTable({
                responsive: true,
                order: [
                    [0, 'asc']
                ],
                language: {
                    "emptyTable": "No sizes available.",
                    "info": "Showing _START_ to _END_ of _TOTAL_ sizes",
                    "infoEmpty": "Showing 0 to 0 of 0 sizes",
                    "lengthMenu": "Show _MENU_ sizes",
                    "paginate": {
                        "first": "First",
                        "last": "Last",
                        "next": "Next",
                        "previous": "Previous"
                    },
                    "search": "Search Sizes:"
                }
            });

            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
<?php endif; ?>