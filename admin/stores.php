<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$perPage = 10;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $manager_id = isset($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
        if (empty($name) || empty($address) || empty($phone) || empty($email)) {
            $message = '<div class="alert alert-danger">All fields are required.</div>';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '<div class="alert alert-danger">Invalid email format.</div>';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM stores WHERE name = ? OR email = ?');
            $stmt->execute([$name, $email]);
            if ($stmt->fetchColumn() > 0) {
                $message = '<div class="alert alert-danger">Store name or email already exists.</div>';
            } else {
                $stmt = $pdo->prepare('INSERT INTO stores (name, address, phone, email, manager_id, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())');
                try {
                    $stmt->execute([$name, $address, $phone, $email, $manager_id]);
                    header('Location: stores.php?action=list&message=' . urlencode("Store created successfully."));
                    exit();
                } catch (PDOException $e) {
                    error_log("Error creating store: " . $e->getMessage());
                    $message = '<div class="alert alert-danger">Failed to create store. Please try again later.</div>';
                }
            }
        }
    } elseif ($action === 'edit' && $id > 0) {
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $manager_id = isset($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        if (empty($name) || empty($address) || empty($phone) || empty($email)) {
            $message = '<div class="alert alert-danger">All fields are required.</div>';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '<div class="alert alert-danger">Invalid email format.</div>';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM stores WHERE (name = ? OR email = ?) AND id != ?');
            $stmt->execute([$name, $email, $id]);
            if ($stmt->fetchColumn() > 0) {
                $message = '<div class="alert alert-danger">Store name or email already exists.</div>';
            } else {
                $stmt = $pdo->prepare('UPDATE stores SET name = ?, address = ?, phone = ?, email = ?, manager_id = ?, is_active = ?, updated_at = NOW() WHERE id = ?');
                try {
                    $stmt->execute([$name, $address, $phone, $email, $manager_id, $is_active, $id]);
                    header('Location: stores.php?action=list&message=' . urlencode("Store updated successfully."));
                    exit();
                } catch (PDOException $e) {
                    error_log("Error updating store (Store ID: $id): " . $e->getMessage());
                    $message = '<div class="alert alert-danger">Failed to update store. Please try again later.</div>';
                }
            }
        }
    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $pdo->prepare('DELETE FROM stores WHERE id = ?');
        try {
            $stmt->execute([$id]);
            header('Location: stores.php?action=list&message=' . urlencode("Store deleted successfully."));
            exit();
        } catch (PDOException $e) {
            error_log("Error deleting store (Store ID: $id): " . $e->getMessage());
            header('Location: stores.php?action=list&message=' . urlencode("Failed to delete store. Please try again later."));
            exit();
        }
    } elseif ($action === 'assign_admin' && $id > 0) {
        $admin_id = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : null;
        if ($admin_id) {
            $stmt = $pdo->prepare('UPDATE stores SET manager_id = ? WHERE id = ?');
            try {
                $stmt->execute([$admin_id, $id]);
                header('Location: stores.php?action=list&message=' . urlencode("Administrator assigned successfully."));
                exit();
            } catch (PDOException $e) {
                error_log("Error assigning admin to store (Store ID: $id): " . $e->getMessage());
                $message = '<div class="alert alert-danger">Failed to assign administrator. Please try again later.</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">No administrator selected.</div>';
        }
    } elseif ($action === 'export') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=stores_' . date('Ymd') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Name', 'Address', 'Phone', 'Email', 'Manager', 'Status', 'Created At']);
        try {
            $stmt = $pdo->query('SELECT s.id, s.name, s.address, s.phone, s.email, u.username AS manager, s.is_active, s.created_at FROM stores s LEFT JOIN users u ON s.manager_id = u.id ORDER BY s.created_at DESC');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['id'],
                    $row['name'],
                    $row['address'],
                    $row['phone'],
                    $row['email'],
                    $row['manager'] ?? 'N/A',
                    $row['is_active'] ? 'Active' : 'Inactive',
                    $row['created_at']
                ]);
            }
            fclose($output);
            exit();
        } catch (PDOException $e) {
            error_log("Error exporting stores: " . $e->getMessage());
            $message = '<div class="alert alert-danger">Failed to export stores. Please try again later.</div>';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'toggle_status' && $id > 0) {
        $stmt = $pdo->prepare('SELECT is_active FROM stores WHERE id = ?');
        $stmt->execute([$id]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($store) {
            $new_status = $store['is_active'] ? 0 : 1;
            $stmt = $pdo->prepare('UPDATE stores SET is_active = ? WHERE id = ?');
            try {
                $stmt->execute([$new_status, $id]);
                $status_text = $new_status ? 'activated' : 'deactivated';
                header('Location: stores.php?action=list&message=' . urlencode("Store has been {$status_text} successfully."));
                exit();
            } catch (PDOException $e) {
                error_log("Error toggling store status (Store ID: $id): " . $e->getMessage());
                header('Location: stores.php?action=list&message=' . urlencode("Failed to toggle store status. Please try again later."));
                exit();
            }
        } else {
            header('Location: stores.php?action=list&message=' . urlencode("Store not found."));
            exit();
        }
    }
}
if ($action === 'list') {
    $search = trim($_GET['search'] ?? '');
    $search_query = '';
    $params = [];
    if ($search) {
        $search_query = ' WHERE s.name LIKE :search OR s.address LIKE :search ';
        $params[':search'] = '%' . $search . '%';
    }
    $sort = $_GET['sort'] ?? 'created_at';
    $allowed_sort = ['name', 'created_at', 'is_active'];
    if (!in_array($sort, $allowed_sort)) {
        $sort = 'created_at';
    }
    $order = $_GET['order'] ?? 'DESC';
    $order = ($order === 'ASC') ? 'ASC' : 'DESC';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $perPage;
    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM stores s {$search_query}");
        $count_stmt->execute($params);
        $total = $count_stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error counting stores: " . $e->getMessage());
        $total = 0;
    }
    try {
        $stmt = $pdo->prepare("SELECT s.id, s.name, s.address, s.phone, s.email, u.username AS manager, s.is_active, s.created_at FROM stores s LEFT JOIN users u ON s.manager_id = u.id {$search_query} ORDER BY s.{$sort} {$order} LIMIT :limit OFFSET :offset");
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching stores: " . $e->getMessage());
        $stores = [];
        $message = '<div class="alert alert-danger">Failed to fetch stores. Please try again later.</div>';
    }
    $totalPages = ceil($total / $perPage);
} elseif ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM stores WHERE id = ?');
    $stmt->execute([$id]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$store) {
        header('Location: stores.php?action=list&message=' . urlencode("Store not found."));
        exit();
    }
} elseif ($action === 'view' && $id > 0) {
    $stmt = $pdo->prepare('SELECT s.*, u.username AS manager FROM stores s LEFT JOIN users u ON s.manager_id = u.id WHERE s.id = ?');
    $stmt->execute([$id]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$store) {
        header('Location: stores.php?action=list&message=' . urlencode("Store not found."));
        exit();
    }
} elseif ($action === 'assign_admin' && $id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM stores WHERE id = ?');
    $stmt->execute([$id]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$store) {
        header('Location: stores.php?action=list&message=' . urlencode("Store not found."));
        exit();
    }
    try {
        $admin_stmt = $pdo->prepare('SELECT id, username FROM users WHERE role = "admin" AND is_active = 1');
        $admin_stmt->execute();
        $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching admins: " . $e->getMessage());
        $admins = [];
        $message = '<div class="alert alert-danger">Failed to fetch administrators. Please try again later.</div>';
    }
}
?>
<?php if ($action === 'list'): ?>
    <h2 class="mb-4">Menaxhimi i Pikave të Dyqanit</h2>
    <?php if (isset($_GET['message'])): ?>
        <div class="alert alert-info"><?= htmlspecialchars($_GET['message']) ?></div>
    <?php elseif ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <div class="d-flex justify-content-between mb-3">
        <div>
            <a href="stores.php?action=create" class="btn btn-primary me-2">
                <i class="fas fa-plus"></i> Krijo Pikë të Re
            </a>
            <a href="stores.php?action=export" class="btn btn-secondary">
                <i class="fas fa-file-export"></i> Eksporto CSV
            </a>
        </div>
    </div>
    <div class="table-responsive">
        <table id="storesTable" class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Emri</th>
                    <th>Adresa</th>
                    <th>Telefoni</th>
                    <th>Email</th>
                    <th>Menaxher</th>
                    <th>Statusi</th>
                    <th>Krijuar</th>
                    <th>Veprime</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($stores)): ?>
                    <tr>
                        <td colspan="8" class="text-center">Nuk u gjetën pika të dyqanit.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($stores as $store): ?>
                        <tr>
                            <td><?= htmlspecialchars($store['name']) ?></td>
                            <td><?= htmlspecialchars($store['address']) ?></td>
                            <td><?= htmlspecialchars($store['phone']) ?></td>
                            <td><?= htmlspecialchars($store['email']) ?></td>
                            <td><?= htmlspecialchars($store['manager'] ?? 'N/A') ?></td>
                            <td>
                                <span class="badge <?= $store['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $store['is_active'] ? 'Aktiv' : 'Inaktiv' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($store['created_at']) ?></td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <a href="stores.php?action=view&id=<?= $store['id'] ?>" class="btn btn-sm btn-info" title="Shiko">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="stores.php?action=edit&id=<?= $store['id'] ?>" class="btn btn-sm btn-warning" title="Ndrysho">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="stores.php?action=delete&id=<?= $store['id'] ?>" class="btn btn-sm btn-danger" title="Fshij" onclick="return confirm('A jeni i sigurtë që dëshironi të fshini këtë pikë të dyqanit?');">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                    <a href="stores.php?action=toggle_status&id=<?= $store['id'] ?>" class="btn btn-sm <?= $store['is_active'] ? 'btn-secondary' : 'btn-success' ?>" title="<?= $store['is_active'] ? 'Deaktivizo' : 'Aktivizo' ?>">
                                        <i class="fas <?= $store['is_active'] ? 'fa-toggle-off' : 'fa-toggle-on' ?>"></i>
                                    </a>
                                    <a href="stores.php?action=assign_admin&id=<?= $store['id'] ?>" class="btn btn-sm btn-primary" title="Cakto Admin">
                                        <i class="fas fa-user-cog"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <!-- Pagination (Optional if using DataTables) -->
        <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?action=list&page=<?= $p ?>&sort=<?= $sort ?>&order=<?= $order ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
<?php elseif ($action === 'create'): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-plus"></i> Krijo Pikë të Re</h2>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <form method="POST" action="stores.php?action=create">
            <div class="mb-3">
                <label for="name" class="form-label">Emri<span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" required maxlength="100" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="address" class="form-label">Adresa<span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="address" name="address" required maxlength="255" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="phone" class="form-label">Telefoni<span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="phone" name="phone" required maxlength="20" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email<span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="email" name="email" required maxlength="100" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="manager_id" class="form-label">Menaxher</label>
                <select class="form-select" id="manager_id" name="manager_id">
                    <option value="">Zgjidh Menaxher</option>
                    <?php
                    try {
                        $admin_stmt = $pdo->prepare('SELECT id, username FROM users WHERE role = "admin" AND is_active = 1');
                        $admin_stmt->execute();
                        $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($admins as $admin) {
                            $selected = (isset($_POST['manager_id']) && $_POST['manager_id'] == $admin['id']) ? 'selected' : '';
                            echo "<option value=\"{$admin['id']}\" {$selected}>" . htmlspecialchars($admin['username']) . "</option>";
                        }
                    } catch (PDOException $e) {
                        error_log("Error fetching admins: " . $e->getMessage());
                    }
                    ?>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Krijo Pikë
                </button>
                <a href="stores.php?action=list" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Anulo
                </a>
            </div>
        </form>
    </div>
<?php elseif ($action === 'edit' && isset($store)): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-edit"></i> Ndrysho Pikë</h2>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <form method="POST" action="stores.php?action=edit&id=<?= $store['id'] ?>">
            <div class="mb-3">
                <label for="name" class="form-label">Emri<span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" required maxlength="100" value="<?= htmlspecialchars($_POST['name'] ?? $store['name']) ?>">
            </div>
            <div class="mb-3">
                <label for="address" class="form-label">Adresa<span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="address" name="address" required maxlength="255" value="<?= htmlspecialchars($_POST['address'] ?? $store['address']) ?>">
            </div>
            <div class="mb-3">
                <label for="phone" class="form-label">Telefoni<span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="phone" name="phone" required maxlength="20" value="<?= htmlspecialchars($_POST['phone'] ?? $store['phone']) ?>">
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email<span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="email" name="email" required maxlength="100" value="<?= htmlspecialchars($_POST['email'] ?? $store['email']) ?>">
            </div>
            <div class="mb-3">
                <label for="manager_id" class="form-label">Menaxher</label>
                <select class="form-select" id="manager_id" name="manager_id">
                    <option value="">Zgjidh Menaxher</option>
                    <?php
                    try {
                        $admin_stmt = $pdo->prepare('SELECT id, username FROM users WHERE role = "admin" AND is_active = 1');
                        $admin_stmt->execute();
                        $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($admins as $admin) {
                            $selected = ((isset($_POST['manager_id']) && $_POST['manager_id'] == $admin['id']) || (!isset($_POST['manager_id']) && $store['manager_id'] == $admin['id'])) ? 'selected' : '';
                            echo "<option value=\"{$admin['id']}\" {$selected}>" . htmlspecialchars($admin['username']) . "</option>";
                        }
                    } catch (PDOException $e) {
                        error_log("Error fetching admins: " . $e->getMessage());
                    }
                    ?>
                </select>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" <?= ($store['is_active'] || (isset($_POST['is_active']) && $_POST['is_active'])) ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">Aktiv</label>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Ruaj Ndryshimet
                </button>
                <a href="stores.php?action=list" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Anulo
                </a>
            </div>
        </form>
    </div>
<?php elseif ($action === 'delete' && $id > 0): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-trash-alt"></i> Fshij Pikë</h2>
        <?php
        $stmt = $pdo->prepare('SELECT name, email FROM stores WHERE id = ?');
        $stmt->execute([$id]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$store):
        ?>
            <div class="alert alert-danger">Pika e Dyqanit nuk u gjet.</div>
            <a href="stores.php?action=list" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kthehu te Lista
            </a>
        <?php else: ?>
            <div class="alert alert-warning">
                A jeni i sigurtë që dëshironi të fshini pikën e dyqanit <strong><?= htmlspecialchars($store['name']) ?></strong> (<?= htmlspecialchars($store['email']) ?>)?
            </div>
            <form method="POST" action="stores.php?action=delete&id=<?= $id ?>">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-check"></i> Po, Fshij
                    </button>
                    <a href="stores.php?action=list" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Jo, Anulo
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
<?php elseif ($action === 'view' && isset($store)): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-eye"></i> Detajet e Pikës së Dyqanit</h2>
        <div class="table-responsive">
            <table class="table table-bordered">
                <tr>
                    <th>ID</th>
                    <td><?= htmlspecialchars($store['id']) ?></td>
                </tr>
                <tr>
                    <th>Emri</th>
                    <td><?= htmlspecialchars($store['name']) ?></td>
                </tr>
                <tr>
                    <th>Adresa</th>
                    <td><?= htmlspecialchars($store['address']) ?></td>
                </tr>
                <tr>
                    <th>Telefoni</th>
                    <td><?= htmlspecialchars($store['phone']) ?></td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td><?= htmlspecialchars($store['email']) ?></td>
                </tr>
                <tr>
                    <th>Menaxher</th>
                    <td><?= htmlspecialchars($store['manager'] ?? 'N/A') ?></td>
                </tr>
                <tr>
                    <th>Statusi</th>
                    <td><?= $store['is_active'] ? '<span class="badge bg-success">Aktiv</span>' : '<span class="badge bg-secondary">Inaktiv</span>' ?></td>
                </tr>
                <tr>
                    <th>Krijuar</th>
                    <td><?= htmlspecialchars($store['created_at']) ?></td>
                </tr>
                <tr>
                    <th>Ndryshuar</th>
                    <td><?= htmlspecialchars($store['updated_at'] ?? 'N/A') ?></td>
                </tr>
            </table>
        </div>
        <a href="stores.php?action=list" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kthehu te Lista
        </a>
    </div>
<?php elseif ($action === 'assign_admin' && isset($store)): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-user-cog"></i> Cakto Administratör për Pikë</h2>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <form method="POST" action="stores.php?action=assign_admin&id=<?= $store['id'] ?>">
            <div class="mb-3">
                <label for="admin_id" class="form-label">Zgjidh Administratörin</label>
                <select class="form-select" id="admin_id" name="admin_id" required>
                    <option value="">Zgjidh Administratör</option>
                    <?php foreach ($admins as $admin): ?>
                        <option value="<?= $admin['id'] ?>" <?= ($store['manager_id'] == $admin['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($admin['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Cakto Administratörin
                </button>
                <a href="stores.php?action=list" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Anulo
                </a>
            </div>
        </form>
    </div>
<?php endif; ?>
<!-- Scripts and Styles -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        $('.form-select').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });
        $('#storesTable').DataTable({
            "paging": false,
            "searching": true,
            "info": false,
            "order": [],
            "language": {
                "search": "Kërko:",
                "emptyTable": "Nuk u gjetën pika të dyqanit."
            }
        });
    });
</script>
<?php
require_once 'includes/footer.php';
?>