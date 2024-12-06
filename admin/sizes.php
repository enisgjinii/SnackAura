<?php
// admin/sizes.php

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include necessary files
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Determine the action and ID from GET parameters
$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

// Handle POST requests for adding or editing sizes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        // Sanitize and validate input
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $base_price = isset($_POST['base_price']) ? floatval($_POST['base_price']) : 0.00;

        if ($name === '') {
            $message = '<div class="alert alert-danger">Size name is required.</div>';
        } else {
            // Prepare and execute the insert statement
            $stmt = $pdo->prepare('INSERT INTO sizes (name, description, base_price) VALUES (?, ?, ?)');
            try {
                $stmt->execute([$name, $description, $base_price]);
                $_SESSION['message'] = '<div class="alert alert-success">Size added successfully.</div>';
                header('Location: sizes.php');
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $message = '<div class="alert alert-danger">Size name already exists.</div>';
                } else {
                    $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    } elseif ($action === 'edit' && $id > 0) {
        // Sanitize and validate input
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $base_price = isset($_POST['base_price']) ? floatval($_POST['base_price']) : 0.00;

        if ($name === '') {
            $message = '<div class="alert alert-danger">Size name is required.</div>';
        } else {
            // Prepare and execute the update statement
            $stmt = $pdo->prepare('UPDATE sizes SET name = ?, description = ?, base_price = ? WHERE id = ?');
            try {
                $stmt->execute([$name, $description, $base_price, $id]);
                $_SESSION['message'] = '<div class="alert alert-success">Size updated successfully.</div>';
                header('Location: sizes.php');
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $message = '<div class="alert alert-danger">Size name already exists.</div>';
                } else {
                    $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    }
} elseif ($action === 'delete' && $id > 0) {
    // Handle deletion via POST to enhance security
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $pdo->beginTransaction();

            // Check if any products are associated with this size
            $stmt_check = $pdo->prepare('SELECT COUNT(*) FROM product_sizes WHERE size_id = ?');
            $stmt_check->execute([$id]);
            $count = $stmt_check->fetchColumn();

            if ($count > 0) {
                $pdo->rollBack();
                $_SESSION['message'] = '<div class="alert alert-danger">Cannot delete size because it is associated with existing products.</div>';
            } else {
                // Proceed to delete the size
                $stmt = $pdo->prepare('DELETE FROM sizes WHERE id = ?');
                $stmt->execute([$id]);
                $pdo->commit();
                $_SESSION['message'] = '<div class="alert alert-success">Size deleted successfully.</div>';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['message'] = '<div class="alert alert-danger">Error deleting size: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        header('Location: sizes.php');
        exit();
    } else {
        // If deletion is requested via GET, show a confirmation modal or redirect
        $_SESSION['message'] = '<div class="alert alert-warning">Invalid request method for deletion.</div>';
        header('Location: sizes.php');
        exit();
    }
}

// Fetch sizes for viewing
if ($action === 'view') {
    try {
        $stmt = $pdo->query('SELECT * FROM sizes ORDER BY name ASC');
        $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $sizes = [];
        $message = '<div class="alert alert-danger">Error fetching sizes: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Display session messages if any
if (isset($_SESSION['message'])) {
    echo $_SESSION['message'];
    unset($_SESSION['message']);
}

// Display messages from current actions
if ($message) {
    echo $message;
}

// Handle different actions
if ($action === 'view'): ?>
    <h2 class="mb-4">Manage Sizes</h2>
    <a href="sizes.php?action=add" class="btn btn-primary mb-3">
        <i class="fas fa-plus"></i> Add Size
    </a>
    <div class="table-responsive">
        <table class="table table-bordered table-hover" id="sizesTable">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Base Price (€)</th>
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
                            <td><?= number_format($size['base_price'], 2) ?></td>
                            <td>
                                <a href="sizes.php?action=edit&id=<?= $size['id'] ?>" class="btn btn-sm btn-warning me-1" data-bs-toggle="tooltip" title="Edit Size">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <!-- Delete button triggers a modal for confirmation -->
                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $size['id'] ?>">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>

                                <!-- Delete Confirmation Modal -->
                                <div class="modal fade" id="deleteModal<?= $size['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $size['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="sizes.php?action=delete&id=<?= $size['id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="deleteModalLabel<?= $size['id'] ?>">Confirm Deletion</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    Are you sure you want to delete the size "<strong><?= htmlspecialchars($size['name']) ?></strong>"?
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger">Delete</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <!-- End of Modal -->
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">No sizes found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </div>

<?php elseif ($action === 'add'): ?>
    <div class="container mt-4">
        <h2 class="mb-4">Add New Size</h2>
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
                <label for="base_price" class="form-label">Base Price (€)</label>
                <input type="number" step="0.01" class="form-control" id="base_price" name="base_price" value="<?= isset($_POST['base_price']) ? htmlspecialchars($_POST['base_price']) : '0.00' ?>">
            </div>
            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Add Size</button>
            <a href="sizes.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
        </form>
    </div>

    <?php elseif ($action === 'edit' && $id > 0):
    // Fetch the size to edit
    $stmt = $pdo->prepare('SELECT * FROM sizes WHERE id = ?');
    $stmt->execute([$id]);
    $size = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$size):
    ?>
        <div class="container mt-4">
            <div class="alert alert-danger">Size not found.</div>
            <a href="sizes.php" class="btn btn-secondary">Back to Sizes</a>
        </div>
    <?php
        require_once 'includes/footer.php';
        exit();
    endif;
    ?>
    <div class="container mt-4">
        <h2 class="mb-4">Edit Size</h2>
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
                <label for="base_price" class="form-label">Base Price (€)</label>
                <input type="number" step="0.01" class="form-control" id="base_price" name="base_price" value="<?= htmlspecialchars($size['base_price']) ?>">
            </div>
            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update Size</button>
            <a href="sizes.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
        </form>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>

<!-- DataTables and Bootstrap 5 Integration -->
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        // Initialize DataTables with enhanced features
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
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>