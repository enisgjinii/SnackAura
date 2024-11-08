<?php
ob_start(); // Start output buffering

// admin/sauces.php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Initialize variables
$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

// Handle different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        // Add Sauce
        $name = trim($_POST['name']);
        $price = trim($_POST['price']);

        // Validate inputs
        if ($name === '' || $price === '') {
            $message = '<div class="alert alert-danger">Name and Price are required.</div>';
        } elseif (!is_numeric($price) || floatval($price) < 0) {
            $message = '<div class="alert alert-danger">Price must be a valid non-negative number.</div>';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO `sauces` (`name`, `price`) VALUES (?, ?)");
                $stmt->execute([$name, $price]);
                // Redirect to view after successful addition
                header("Location: sauces.php");
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') { // Integrity constraint violation
                    $message = '<div class="alert alert-danger">Sauce name already exists.</div>';
                } else {
                    $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    } elseif ($action === 'edit' && $id > 0) {
        // Edit Sauce
        $name = trim($_POST['name']);
        $price = trim($_POST['price']);

        // Validate inputs
        if ($name === '' || $price === '') {
            $message = '<div class="alert alert-danger">Name and Price are required.</div>';
        } elseif (!is_numeric($price) || floatval($price) < 0) {
            $message = '<div class="alert alert-danger">Price must be a valid non-negative number.</div>';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE `sauces` SET `name` = ?, `price` = ? WHERE `id` = ?");
                $stmt->execute([$name, $price, $id]);
                // Redirect to view after successful update
                header("Location: sauces.php");
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') { // Integrity constraint violation
                    $message = '<div class="alert alert-danger">Sauce name already exists.</div>';
                } else {
                    $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    }
} elseif ($action === 'delete' && $id > 0) {
    // Delete Sauce
    try {
        $stmt = $pdo->prepare("DELETE FROM `sauces` WHERE `id` = ?");
        $stmt->execute([$id]);
        // Redirect to view after successful deletion
        header("Location: sauces.php");
        exit();
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Fetch all sauces for viewing
if ($action === 'view') {
    try {
        $stmt = $pdo->query("SELECT * FROM `sauces` ORDER BY `created_at` DESC");
        $sauces = $stmt->fetchAll();
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Error fetching sauces: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
?>

<?php if ($action === 'view'): ?>
    <h2>Manage Sauces</h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>

    <!-- Add Sauce Button -->
    <a href="sauces.php?action=add" class="btn btn-primary mb-3">Add Sauce</a>

    <!-- Sauces Table -->
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
            <?php if (!empty($sauces)): ?>
                <?php foreach ($sauces as $sauce): ?>
                    <tr>
                        <td><?= htmlspecialchars($sauce['id']) ?></td>
                        <td><?= htmlspecialchars($sauce['name']) ?></td>
                        <td><?= number_format($sauce['price'], 2) ?></td>
                        <td><?= htmlspecialchars($sauce['created_at']) ?></td>
                        <td>
                            <a href="sauces.php?action=edit&id=<?= $sauce['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                            <a href="#" class="btn btn-sm btn-danger" onclick="showDeleteModal(<?= $sauce['id'] ?>)">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center">No sauces found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="GET" action="sauces.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteSauceId">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete this sauce?
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-danger">Delete</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showDeleteModal(id) {
            document.getElementById('deleteSauceId').value = id;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>

<?php elseif ($action === 'add' || ($action === 'edit' && $id > 0)): ?>
    <?php
    if ($action === 'edit') {
        // Fetch existing sauce details
        try {
            $stmt = $pdo->prepare("SELECT * FROM `sauces` WHERE `id` = ?");
            $stmt->execute([$id]);
            $sauce = $stmt->fetch();
            if (!$sauce) {
                echo '<div class="alert alert-danger">Sauce not found.</div>';
                require_once 'includes/footer.php';
                exit();
            }
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger">Error fetching sauce details: ' . htmlspecialchars($e->getMessage()) . '</div>';
            require_once 'includes/footer.php';
            exit();
        }
    }
    ?>

    <h2><?= $action === 'add' ? 'Add Sauce' : 'Edit Sauce' ?></h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <form method="POST" action="sauces.php?action=<?= $action ?><?= $action === 'edit' ? '&id=' . $id : '' ?>">
        <?php if ($action === 'edit'): ?>
            <!-- Hidden input for ID -->
            <input type="hidden" name="id" value="<?= $id ?>">
        <?php endif; ?>
        <div class="mb-3">
            <label for="name" class="form-label">Sauce Name *</label>
            <input type="text" class="form-control" id="name" name="name" required value="<?= $action === 'edit' ? htmlspecialchars($sauce['name']) : (isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '') ?>">
        </div>
        <div class="mb-3">
            <label for="price" class="form-label">Price (€) *</label>
            <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" required value="<?= $action === 'edit' ? htmlspecialchars($sauce['price']) : (isset($_POST['price']) ? htmlspecialchars($_POST['price']) : '') ?>">
        </div>
        <button type="submit" class="btn btn-success"><?= $action === 'add' ? 'Add' : 'Update' ?> Sauce</button>
        <a href="sauces.php" class="btn btn-secondary">Cancel</a>
    </form>

<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>
