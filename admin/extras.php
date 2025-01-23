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

<div class="container my-5">
    <h2 class="mb-4">Manage Extras, Sauces & Dressings</h2>
    <?= $message ?>

    <ul class="nav nav-pills mb-4">
        <li class="nav-item">
            <a class="nav-link <?= ($category === 'Extras') ? 'active' : '' ?>"
                href="extras.php?category=Extras">Extras</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($category === 'Sauces') ? 'active' : '' ?>"
                href="extras.php?category=Sauces">Sauces</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($category === 'Dressing') ? 'active' : '' ?>"
                href="extras.php?category=Dressing">Dressing</a>
        </li>
    </ul>

    <?php if ($action === 'edit' && !empty($item)): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">Edit <?= s($item['category']) ?> (ID: <?= (int)$item['id'] ?>)</div>
            <div class="card-body">
                <form method="POST" action="extras.php?action=edit&id=<?= (int)$item['id'] ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required value="<?= s($item['name']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Price (€)</label>
                        <input type="number" name="price" step="0.01" class="form-control" value="<?= s($item['price']) ?>">
                    </div>
                    <button type="submit" class="btn btn-success">Save Changes</button>
                    <a href="extras.php?category=<?= s($item['category']) ?>" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    <?php else: ?>
        <?php if ($action === 'add'): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">Add New to <?= s($category) ?></div>
                <div class="card-body">
                    <form method="POST" action="extras.php?action=add&category=<?= s($category) ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Cheese">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Price (€)</label>
                            <input type="number" name="price" step="0.01" class="form-control" placeholder="e.g. 1.50">
                        </div>
                        <button type="submit" class="btn btn-primary">Add</button>
                        <a href="extras.php?category=<?= s($category) ?>" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="mb-3">
                <a href="extras.php?action=add&category=<?= s($category) ?>" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Add New to <?= s($category) ?>
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <span class="fw-bold"><?= s($category) ?></span>
            <span class="text-muted ms-2">Total: <?= count($items) ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($items)): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-striped align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price (€)</th>
                                <th class="text-center" style="width: 150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $row): ?>
                                <tr>
                                    <td><?= (int)$row['id'] ?></td>
                                    <td><?= s($row['name']) ?></td>
                                    <td><?= s($row['category']) ?></td>
                                    <td>€<?= number_format($row['price'], 2) ?></td>
                                    <td class="text-center">
                                        <a href="extras.php?action=edit&id=<?= (int)$row['id'] ?>"
                                            class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="extras.php?action=delete&id=<?= (int)$row['id'] ?>"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('Are you sure you want to delete this item?');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-3">No items found in this category.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>