<?php
ob_start();
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$perPage = 10;

// Funktion zur Validierung der Eingaben
function validateStore($data, $pdo, $id = 0)
{
    $errors = [];
    $name = trim($data['name'] ?? '');
    $address = trim($data['address'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $email = trim($data['email'] ?? '');
    $manager_id = isset($data['manager_id']) ? (int)$data['manager_id'] : null;

    if (empty($name) || empty($address) || empty($phone) || empty($email)) {
        $errors[] = "Alle Felder sind erforderlich.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Ungültiges E-Mail-Format.";
    }
    // Überprüfen, ob der Store-Name oder die E-Mail bereits existiert
    $query = 'SELECT COUNT(*) FROM stores WHERE (name = ? OR email = ?)';
    if ($id > 0) {
        $query .= ' AND id != ?';
    }
    $stmt = $pdo->prepare($query);
    $params = [$name, $email];
    if ($id > 0) {
        $params[] = $id;
    }
    $stmt->execute($params);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Store-Name oder E-Mail existiert bereits.";
    }

    return [$errors, compact('name', 'address', 'phone', 'email', 'manager_id')];
}

// Funktion zur Anzeige von Meldungen
function displayMessage($message)
{
    if ($message) {
        echo '<div class="alert alert-info">' . htmlspecialchars($message) . '</div>';
    }
}

// Verarbeitung von POST-Anfragen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        list($errors, $data) = validateStore($_POST, $pdo);
        if (!empty($errors)) {
            $message = '<div class="alert alert-danger">' . implode('<br>', $errors) . '</div>';
        } else {
            $stmt = $pdo->prepare('INSERT INTO stores (name, address, phone, email, manager_id, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())');
            try {
                $stmt->execute([$data['name'], $data['address'], $data['phone'], $data['email'], $data['manager_id']]);
                header('Location: stores.php?action=list&message=' . urlencode("Store erfolgreich erstellt."));
                exit();
            } catch (PDOException $e) {
                error_log("Fehler beim Erstellen des Stores: " . $e->getMessage());
                $message = '<div class="alert alert-danger">Store konnte nicht erstellt werden. Bitte versuchen Sie es später erneut.</div>';
            }
        }
    } elseif ($action === 'edit' && $id > 0) {
        list($errors, $data) = validateStore($_POST, $pdo, $id);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        if (!empty($errors)) {
            $message = '<div class="alert alert-danger">' . implode('<br>', $errors) . '</div>';
        } else {
            $stmt = $pdo->prepare('UPDATE stores SET name = ?, address = ?, phone = ?, email = ?, manager_id = ?, is_active = ?, updated_at = NOW() WHERE id = ?');
            try {
                $stmt->execute([$data['name'], $data['address'], $data['phone'], $data['email'], $data['manager_id'], $is_active, $id]);
                header('Location: stores.php?action=list&message=' . urlencode("Store erfolgreich aktualisiert."));
                exit();
            } catch (PDOException $e) {
                error_log("Fehler beim Aktualisieren des Stores (ID: $id): " . $e->getMessage());
                $message = '<div class="alert alert-danger">Store konnte nicht aktualisiert werden. Bitte versuchen Sie es später erneut.</div>';
            }
        }
    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $pdo->prepare('DELETE FROM stores WHERE id = ?');
        try {
            $stmt->execute([$id]);
            header('Location: stores.php?action=list&message=' . urlencode("Store erfolgreich gelöscht."));
            exit();
        } catch (PDOException $e) {
            error_log("Fehler beim Löschen des Stores (ID: $id): " . $e->getMessage());
            header('Location: stores.php?action=list&message=' . urlencode("Store konnte nicht gelöscht werden. Bitte versuchen Sie es später erneut."));
            exit();
        }
    } elseif ($action === 'assign_admin' && $id > 0) {
        $admin_id = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : null;
        if ($admin_id) {
            $stmt = $pdo->prepare('UPDATE stores SET manager_id = ? WHERE id = ?');
            try {
                $stmt->execute([$admin_id, $id]);
                header('Location: stores.php?action=list&message=' . urlencode("Administrator erfolgreich zugewiesen."));
                exit();
            } catch (PDOException $e) {
                error_log("Fehler beim Zuweisen des Administrators zum Store (ID: $id): " . $e->getMessage());
                $message = '<div class="alert alert-danger">Administrator konnte nicht zugewiesen werden. Bitte versuchen Sie es später erneut.</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Kein Administrator ausgewählt.</div>';
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
                $status_text = $new_status ? 'aktiviert' : 'deaktiviert';
                header('Location: stores.php?action=list&message=' . urlencode("Store wurde erfolgreich {$status_text}."));
                exit();
            } catch (PDOException $e) {
                error_log("Fehler beim Umschalten des Store-Status (ID: $id): " . $e->getMessage());
                header('Location: stores.php?action=list&message=' . urlencode("Store-Status konnte nicht umgeschaltet werden. Bitte versuchen Sie es später erneut."));
                exit();
            }
        } else {
            header('Location: stores.php?action=list&message=' . urlencode("Store nicht gefunden."));
            exit();
        }
    }
}

// Daten für die Listenansicht abrufen
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
        error_log("Fehler beim Zählen der Stores: " . $e->getMessage());
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
        error_log("Fehler beim Abrufen der Stores: " . $e->getMessage());
        $stores = [];
        $message = '<div class="alert alert-danger">Stores konnten nicht abgerufen werden. Bitte versuchen Sie es später erneut.</div>';
    }
    $totalPages = ceil($total / $perPage);
} elseif (in_array($action, ['edit', 'view', 'assign_admin']) && $id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM stores WHERE id = ?');
    $stmt->execute([$id]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$store) {
        header('Location: stores.php?action=list&message=' . urlencode("Store nicht gefunden."));
        exit();
    }
    if ($action === 'view') {
        $stmt = $pdo->prepare('SELECT s.*, u.username AS manager FROM stores s LEFT JOIN users u ON s.manager_id = u.id WHERE s.id = ?');
        $stmt->execute([$id]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$store) {
            header('Location: stores.php?action=list&message=' . urlencode("Store nicht gefunden."));
            exit();
        }
    } elseif ($action === 'assign_admin') {
        try {
            $admin_stmt = $pdo->prepare('SELECT id, username FROM users WHERE role = "admin" AND is_active = 1');
            $admin_stmt->execute();
            $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Fehler beim Abrufen der Administratoren: " . $e->getMessage());
            $admins = [];
            $message = '<div class="alert alert-danger">Administratoren konnten nicht abgerufen werden. Bitte versuchen Sie es später erneut.</div>';
        }
    }
}
?>
<?php if ($action === 'list'): ?>
    <div class="container mt-4">
        <h2 class="mb-4">Store Verwaltung</h2>
        <?php
        if (isset($_GET['message'])) {
            echo '<div class="alert alert-info">' . htmlspecialchars($_GET['message']) . '</div>';
        } elseif ($message) {
            echo $message;
        }
        ?>
        <div class="d-flex justify-content-between mb-3">
            <div>
                <a href="stores.php?action=create" class="btn btn-primary btn-sm me-2">
                    <i class="fas fa-plus"></i> Neuer Store
                </a>
            </div>
            <form class="d-flex" method="GET" action="stores.php">
                <input type="hidden" name="action" value="list">
                <input class="form-control form-control-sm me-2" type="search" name="search" placeholder="Suche" value="<?= htmlspecialchars($search ?? '') ?>">
                <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
        <div class="table-responsive">
            <table id="storesTable" class="table table-striped table-hover table-sm">
                <thead class="table-dark">
                    <tr>
                        <th><a href="?action=list&sort=name&order=<?= $sort === 'name' && $order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white text-decoration-none">Name</a></th>
                        <th><a href="?action=list&sort=address&order=<?= $sort === 'address' && $order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white text-decoration-none">Adresse</a></th>
                        <th>Telefon</th>
                        <th>E-Mail</th>
                        <th>Manager</th>
                        <th>Status</th>
                        <th><a href="?action=list&sort=created_at&order=<?= $sort === 'created_at' && $order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-white text-decoration-none">Erstellt am</a></th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stores)): ?>
                        <tr>
                            <td colspan="8" class="text-center">Keine Stores gefunden.</td>
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
                                        <a href="stores.php?action=view&id=<?= $store['id'] ?>" class="btn btn-sm btn-info" title="Anzeigen">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="stores.php?action=edit&id=<?= $store['id'] ?>" class="btn btn-sm btn-warning" title="Bearbeiten">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="stores.php?action=delete&id=<?= $store['id'] ?>" class="btn btn-sm btn-danger" title="Löschen" onclick="return confirm('Sind Sie sicher, dass Sie diesen Store löschen möchten?');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                        <a href="stores.php?action=toggle_status&id=<?= $store['id'] ?>" class="btn btn-sm <?= $store['is_active'] ? 'btn-secondary' : 'btn-success' ?>" title="<?= $store['is_active'] ? 'Deaktivieren' : 'Aktivieren' ?>">
                                            <i class="fas <?= $store['is_active'] ? 'fa-toggle-off' : 'fa-toggle-on' ?>"></i>
                                        </a>
                                        <a href="stores.php?action=assign_admin&id=<?= $store['id'] ?>" class="btn btn-sm btn-primary" title="Administrator zuweisen">
                                            <i class="fas fa-user-cog"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <!-- Paginierung -->
            <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?action=list&page=<?= $p ?>&sort=<?= $sort ?>&order=<?= $order ?>&search=<?= htmlspecialchars($search) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
<?php elseif ($action === 'create'): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-plus"></i> Neuer Store</h2>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <form method="POST" action="stores.php?action=create">
            <div class="row g-2">
                <div class="col-md-6">
                    <label for="name" class="form-label">Name<span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="name" name="name" required maxlength="100" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="address" class="form-label">Adresse<span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="address" name="address" required maxlength="255" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Telefon<span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="phone" name="phone" required maxlength="20" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">E-Mail<span class="text-danger">*</span></label>
                    <input type="email" class="form-control form-control-sm" id="email" name="email" required maxlength="100" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="manager_id" class="form-label">Manager</label>
                    <select class="form-select form-select-sm" id="manager_id" name="manager_id">
                        <option value="">Manager auswählen</option>
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
                            error_log("Fehler beim Abrufen der Administratoren: " . $e->getMessage());
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="fas fa-save"></i> Store erstellen
                </button>
                <a href="stores.php?action=list" class="btn btn-secondary btn-sm">
                    <i class="fas fa-times"></i> Abbrechen
                </a>
            </div>
        </form>
    </div>
<?php elseif ($action === 'edit' && isset($store)): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-edit"></i> Store bearbeiten</h2>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <form method="POST" action="stores.php?action=edit&id=<?= $store['id'] ?>">
            <div class="row g-2">
                <div class="col-md-6">
                    <label for="name" class="form-label">Name<span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="name" name="name" required maxlength="100" value="<?= htmlspecialchars($_POST['name'] ?? $store['name']) ?>">
                </div>
                <div class="col-md-6">
                    <label for="address" class="form-label">Adresse<span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="address" name="address" required maxlength="255" value="<?= htmlspecialchars($_POST['address'] ?? $store['address']) ?>">
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Telefon<span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="phone" name="phone" required maxlength="20" value="<?= htmlspecialchars($_POST['phone'] ?? $store['phone']) ?>">
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">E-Mail<span class="text-danger">*</span></label>
                    <input type="email" class="form-control form-control-sm" id="email" name="email" required maxlength="100" value="<?= htmlspecialchars($_POST['email'] ?? $store['email']) ?>">
                </div>
                <div class="col-md-6">
                    <label for="manager_id" class="form-label">Manager</label>
                    <select class="form-select form-select-sm" id="manager_id" name="manager_id">
                        <option value="">Manager auswählen</option>
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
                            error_log("Fehler beim Abrufen der Administratoren: " . $e->getMessage());
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6 align-self-end">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" <?= ($store['is_active'] || (isset($_POST['is_active']) && $_POST['is_active'])) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Aktiv</label>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-save"></i> Änderungen speichern
                </button>
                <a href="stores.php?action=list" class="btn btn-secondary btn-sm">
                    <i class="fas fa-times"></i> Abbrechen
                </a>
            </div>
        </form>
    </div>
<?php elseif ($action === 'delete' && $id > 0): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-trash-alt"></i> Store löschen</h2>
        <?php
        $stmt = $pdo->prepare('SELECT name, email FROM stores WHERE id = ?');
        $stmt->execute([$id]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$store):
        ?>
            <div class="alert alert-danger">Store wurde nicht gefunden.</div>
            <a href="stores.php?action=list" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Zurück zur Liste
            </a>
        <?php else: ?>
            <div class="alert alert-warning">
                Sind Sie sicher, dass Sie den Store <strong><?= htmlspecialchars($store['name']) ?></strong> (<?= htmlspecialchars($store['email']) ?>) löschen möchten?
            </div>
            <form method="POST" action="stores.php?action=delete&id=<?= $id ?>">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="fas fa-check"></i> Ja, löschen
                    </button>
                    <a href="stores.php?action=list" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times"></i> Nein, abbrechen
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
<?php elseif ($action === 'view' && isset($store)): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-eye"></i> Store Details</h2>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <tr>
                    <th>ID</th>
                    <td><?= htmlspecialchars($store['id']) ?></td>
                </tr>
                <tr>
                    <th>Name</th>
                    <td><?= htmlspecialchars($store['name']) ?></td>
                </tr>
                <tr>
                    <th>Adresse</th>
                    <td><?= htmlspecialchars($store['address']) ?></td>
                </tr>
                <tr>
                    <th>Telefon</th>
                    <td><?= htmlspecialchars($store['phone']) ?></td>
                </tr>
                <tr>
                    <th>E-Mail</th>
                    <td><?= htmlspecialchars($store['email']) ?></td>
                </tr>
                <tr>
                    <th>Manager</th>
                    <td><?= htmlspecialchars($store['manager'] ?? 'N/A') ?></td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td><?= $store['is_active'] ? '<span class="badge bg-success">Aktiv</span>' : '<span class="badge bg-secondary">Inaktiv</span>' ?></td>
                </tr>
                <tr>
                    <th>Erstellt am</th>
                    <td><?= htmlspecialchars($store['created_at']) ?></td>
                </tr>
                <tr>
                    <th>Aktualisiert am</th>
                    <td><?= htmlspecialchars($store['updated_at'] ?? 'N/A') ?></td>
                </tr>
            </table>
        </div>
        <a href="stores.php?action=list" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Zurück zur Liste
        </a>
    </div>
<?php elseif ($action === 'assign_admin' && isset($store)): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-user-cog"></i> Administrator zuweisen</h2>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <form method="POST" action="stores.php?action=assign_admin&id=<?= $store['id'] ?>">
            <div class="mb-3">
                <label for="admin_id" class="form-label">Administrator auswählen<span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" id="admin_id" name="admin_id" required>
                    <option value="">Administrator auswählen</option>
                    <?php foreach ($admins as $admin): ?>
                        <option value="<?= $admin['id'] ?>" <?= ($store['manager_id'] == $admin['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($admin['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-save"></i> Administrator zuweisen
                </button>
                <a href="stores.php?action=list" class="btn btn-secondary btn-sm">
                    <i class="fas fa-times"></i> Abbrechen
                </a>
            </div>
        </form>
    </div>
<?php endif; ?>
<!-- Scripts und Styles -->
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
            "searching": false,
            "info": false,
            "order": [],
            "language": {
                "emptyTable": "Keine Stores gefunden."
            }
        });
    });
</script>
<?php
require_once 'includes/footer.php';
?>