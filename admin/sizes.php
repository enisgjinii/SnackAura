<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/db_connect.php';
require_once 'includes/header.php';

$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

function handleSubmission($pdo, $action, $id = 0)
{
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $sauce_price = floatval($_POST['sauce_price_increase'] ?? 0);
    $extra_price = floatval($_POST['extra_price_increase'] ?? 0);

    if ($name === '') {
        return '<div class="alert alert-danger">Size name is required.</div>';
    }

    try {
        if ($action === 'add') {
            $stmt = $pdo->prepare('INSERT INTO sizes (name, description, sauce_price_increase, extra_price_increase) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $description, $sauce_price, $extra_price]);
            $_SESSION['message'] = '<div class="alert alert-success">Size added successfully.</div>';
        } elseif ($action === 'edit' && $id > 0) {
            $stmt = $pdo->prepare('UPDATE sizes SET name = ?, description = ?, sauce_price_increase = ?, extra_price_increase = ? WHERE id = ?');
            $stmt->execute([$name, $description, $sauce_price, $extra_price, $id]);
            $_SESSION['message'] = '<div class="alert alert-success">Size updated successfully.</div>';
        }
        header('Location: sizes.php');
        exit();
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return '<div class="alert alert-danger">Size name already exists.</div>';
        }
        return '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = handleSubmission($pdo, $action, $id);
} elseif ($action === 'delete' && $id > 0) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE size_id = ?');
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            $message = '<div class="alert alert-danger">Cannot delete size because it is associated with existing products.</div>';
        } else {
            $pdo->prepare('DELETE FROM sizes WHERE id = ?')->execute([$id]);
            $pdo->commit();
            $_SESSION['message'] = '<div class="alert alert-success">Size deleted successfully.</div>';
            header('Location: sizes.php');
            exit();
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = '<div class="alert alert-danger">Error deleting size: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

if ($action === 'view') {
    try {
        $sizes = $pdo->query('SELECT * FROM sizes ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $sizes = [];
        $message = '<div class="alert alert-danger">Error fetching sizes: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
?>
<?= $_SESSION['message'] ?? '' ?>
<?php unset($_SESSION['message']); ?>
<?php if ($action === 'view'): ?>
    <h2>Sizes</h2>
    <?= $message ?>
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
            <?php if ($sizes): foreach ($sizes as $size): ?>
                    <tr>
                        <td><?= htmlspecialchars($size['id']) ?></td>
                        <td><?= htmlspecialchars($size['name']) ?></td>
                        <td><?= htmlspecialchars($size['description']) ?></td>
                        <td><?= number_format($size['sauce_price_increase'], 2) ?></td>
                        <td><?= number_format($size['extra_price_increase'], 2) ?></td>
                        <td>
                            <a href="sizes.php?action=edit&id=<?= $size['id'] ?>" class="btn btn-sm btn-warning me-1" data-bs-toggle="tooltip" title="Edit Size"><i class="fas fa-edit"></i> Edit</a>
                            <a href="sizes.php?action=delete&id=<?= $size['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this size?')" data-bs-toggle="tooltip" title="Delete Size"><i class="fas fa-trash-alt"></i> Delete</a>
                        </td>
                    </tr>
            <?php endforeach;
            endif; ?>
        </tbody>
    </table>
<?php elseif (in_array($action, ['add', 'edit']) && ($action !== 'edit' || $id > 0)): ?>
    <?php
    if ($action === 'edit') {
        $stmt = $pdo->prepare('SELECT * FROM sizes WHERE id = ?');
        $stmt->execute([$id]);
        $size = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$size) {
            echo '<div class="alert alert-danger">Size not found.</div>';
            require_once 'includes/footer.php';
            exit();
        }
    }
    ?>
    <h2><?= ucfirst($action) ?> Size</h2>
    <?= $message ?>
    <form method="POST" action="sizes.php?action=<?= $action ?><?= $action === 'edit' ? '&id=' . $id : '' ?>">
        <div class="mb-3">
            <label class="form-label">Size Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? ($action === 'edit' ? $size['name'] : '')) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description"><?= htmlspecialchars($_POST['description'] ?? ($action === 'edit' ? $size['description'] : '')) ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Sauce Price Increase ($)</label>
            <input type="number" step="0.01" class="form-control" name="sauce_price_increase" value="<?= htmlspecialchars($_POST['sauce_price_increase'] ?? ($action === 'edit' ? $size['sauce_price_increase'] : '0.00')) ?>">
            <div class="form-text">Enter the amount to increase the price of sauces when this size is selected.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Extra Price Increase ($)</label>
            <input type="number" step="0.01" class="form-control" name="extra_price_increase" value="<?= htmlspecialchars($_POST['extra_price_increase'] ?? ($action === 'edit' ? $size['extra_price_increase'] : '0.00')) ?>">
            <div class="form-text">Enter the amount to increase the price of extras when this size is selected.</div>
        </div>
        <button type="submit" class="btn btn-success"><?= ucfirst($action) ?> Size</button>
        <a href="sizes.php" class="btn btn-secondary">Cancel</a>
    </form>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
<?php if ($action === 'view'): ?>
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(function() {
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
            $('[data-bs-toggle="tooltip"]').tooltip();
        });
    </script>
<?php endif; ?>