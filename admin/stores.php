<?php
ob_start();
require_once 'includes/db_connect.php';
require_once 'includes/header.php';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

function validateStore($data, $pdo, $id = 0)
{
    $errors = [];
    $name = trim($data['name'] ?? '');
    $address = trim($data['address'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $email = trim($data['email'] ?? '');
    $manager_id = isset($data['manager_id']) ? (int)$data['manager_id'] : null;
    if (empty($name) || empty($address) || empty($phone) || empty($email)) {
        $errors[] = "All fields are required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
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
        $errors[] = "Store name or email already exists.";
    }
    return [$errors, compact('name', 'address', 'phone', 'email', 'manager_id')];
}

function handleFileUpload(&$errors, $fileKey = 'logo')
{
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $uploadDir = __DIR__ . '/uploads/logos/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            $errors[] = "Could not create upload directory for logos.";
            return null;
        }
    }
    $tmpPath = $_FILES[$fileKey]['tmp_name'];
    $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES[$fileKey]['name']);
    $destPath = $uploadDir . $fileName;
    if (!move_uploaded_file($tmpPath, $destPath)) {
        $errors[] = "File upload failed.";
        return null;
    }
    return $fileName;
}

function buildWorkScheduleJSON()
{
    $days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
    $schedule = ['days' => [], 'holidays' => []];
    foreach ($days as $day) {
        $startKey = strtolower($day) . '_start';
        $endKey = strtolower($day) . '_end';
        $startVal = trim($_POST[$startKey] ?? '');
        $endVal = trim($_POST[$endKey] ?? '');
        $schedule['days'][$day] = ['start' => $startVal, 'end' => $endVal];
    }
    $holidaysRaw = trim($_POST['holidays'] ?? '');
    if (!empty($holidaysRaw)) {
        $lines = explode("\n", $holidaysRaw);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line) continue;
            $parts = explode(',', $line, 2);
            $date = trim($parts[0]);
            $desc = isset($parts[1]) ? trim($parts[1]) : 'Holiday';
            $schedule['holidays'][] = ['date' => $date, 'desc' => $desc];
        }
    }
    return json_encode($schedule, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        list($errors, $data) = validateStore($_POST, $pdo);
        $uploadedLogo = handleFileUpload($errors, 'logo');
        $workScheduleJSON = buildWorkScheduleJSON();
        if (!empty($errors)) {
            $message = '<div class="alert alert-danger">' . implode('<br>', $errors) . '</div>';
        } else {
            $stmt = $pdo->prepare('INSERT INTO stores (name, address, phone, email, manager_id, is_active, logo, work_schedule, created_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?, NOW())');
            try {
                $stmt->execute([$data['name'], $data['address'], $data['phone'], $data['email'], $data['manager_id'], $uploadedLogo, $workScheduleJSON]);
                header('Location: stores.php?action=list&message=' . urlencode("Store created successfully."));
                exit();
            } catch (PDOException $e) {
                error_log("Error creating store: " . $e->getMessage());
                $message = '<div class="alert alert-danger">Unable to create store. Please try again later.</div>';
            }
        }
    } elseif ($action === 'edit' && $id > 0) {
        list($errors, $data) = validateStore($_POST, $pdo, $id);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $uploadedLogo = handleFileUpload($errors, 'logo');
        $workScheduleJSON = buildWorkScheduleJSON();
        if (!empty($errors)) {
            $message = '<div class="alert alert-danger">' . implode('<br>', $errors) . '</div>';
        } else {
            $extraLogo = $uploadedLogo ? ', logo = ?' : '';
            $stmt = $pdo->prepare("UPDATE stores SET name = ?, address = ?, phone = ?, email = ?, manager_id = ?, is_active = ?, work_schedule = ? $extraLogo, updated_at = NOW() WHERE id = ?");
            $params = [$data['name'], $data['address'], $data['phone'], $data['email'], $data['manager_id'], $is_active, $workScheduleJSON];
            if ($uploadedLogo) {
                $params[] = $uploadedLogo;
            }
            $params[] = $id;
            try {
                $stmt->execute($params);
                header('Location: stores.php?action=list&message=' . urlencode("Store updated successfully."));
                exit();
            } catch (PDOException $e) {
                error_log("Error updating store (ID: $id): " . $e->getMessage());
                $message = '<div class="alert alert-danger">Unable to update store. Please try again later.</div>';
            }
        }
    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $pdo->prepare('DELETE FROM stores WHERE id = ?');
        try {
            $stmt->execute([$id]);
            header('Location: stores.php?action=list&message=' . urlencode("Store deleted successfully."));
            exit();
        } catch (PDOException $e) {
            error_log("Error deleting store (ID: $id): " . $e->getMessage());
            header('Location: stores.php?action=list&message=' . urlencode("Unable to delete store. Please try again later."));
            exit();
        }
    } elseif ($action === 'assign_admin' && $id > 0) {
        $admin_id = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : null;
        $createNewAdmin = isset($_POST['create_new_admin']) && $_POST['create_new_admin'] == '1';
        if ($createNewAdmin) {
            $newUser = trim($_POST['new_admin_username'] ?? '');
            $newPass = trim($_POST['new_admin_password'] ?? '');
            if ($newUser && $newPass) {
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $checkStmt->execute([$newUser]);
                if ($checkStmt->fetchColumn() > 0) {
                    $message = '<div class="alert alert-danger">Username already exists. Choose another one.</div>';
                } else {
                    $hashedPass = password_hash($newPass, PASSWORD_BCRYPT);
                    try {
                        $insStmt = $pdo->prepare("INSERT INTO users (username, password, role, is_active) VALUES (?, ?, 'admin', 1)");
                        $insStmt->execute([$newUser, $hashedPass]);
                        $newAdminId = $pdo->lastInsertId();
                        $stmt = $pdo->prepare('UPDATE stores SET manager_id = ? WHERE id = ?');
                        $stmt->execute([$newAdminId, $id]);
                        header('Location: stores.php?action=list&message=' . urlencode("New admin created and assigned."));
                        exit();
                    } catch (PDOException $e) {
                        error_log("Error creating new admin user: " . $e->getMessage());
                        $message = '<div class="alert alert-danger">Unable to create admin. Please try again later.</div>';
                    }
                }
            } else {
                $message = '<div class="alert alert-danger">Username and password are required to create a new admin.</div>';
            }
        } else {
            if ($admin_id) {
                $stmt = $pdo->prepare('UPDATE stores SET manager_id = ? WHERE id = ?');
                try {
                    $stmt->execute([$admin_id, $id]);
                    header('Location: stores.php?action=list&message=' . urlencode("Admin assigned successfully."));
                    exit();
                } catch (PDOException $e) {
                    error_log("Error assigning admin to store (ID: $id): " . $e->getMessage());
                    $message = '<div class="alert alert-danger">Unable to assign admin. Please try again later.</div>';
                }
            } else {
                $message = '<div class="alert alert-danger">No admin selected.</div>';
            }
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
                $txt = $new_status ? 'activated' : 'deactivated';
                header('Location: stores.php?action=list&message=' . urlencode("Store $txt successfully."));
                exit();
            } catch (PDOException $e) {
                error_log("Error toggling store status (ID: $id): " . $e->getMessage());
                header('Location: stores.php?action=list&message=' . urlencode("Unable to toggle status. Please try again later."));
                exit();
            }
        } else {
            header('Location: stores.php?action=list&message=' . urlencode("Store not found."));
            exit();
        }
    }
}

if ($action === 'list') {
    $message = $_GET['message'] ?? '';
    $stores = [];
    try {
        $stmt = $pdo->prepare("SELECT s.id, s.name, s.address, s.phone, s.email, s.logo, u.username AS manager, s.is_active, s.created_at FROM stores s LEFT JOIN users u ON s.manager_id = u.id ORDER BY s.created_at DESC");
        $stmt->execute();
        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching stores: " . $e->getMessage());
        $message = '<div class="alert alert-danger">Unable to fetch stores. Please try again later.</div>';
    }
} elseif (in_array($action, ['edit', 'view', 'assign_admin']) && $id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM stores WHERE id = ?');
    $stmt->execute([$id]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$store) {
        header('Location: stores.php?action=list&message=' . urlencode("Store not found."));
        exit();
    }
    if ($action === 'view') {
        $stmt = $pdo->prepare('SELECT s.*, u.username AS manager FROM stores s LEFT JOIN users u ON s.manager_id = u.id WHERE s.id = ?');
        $stmt->execute([$id]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$store) {
            header('Location: stores.php?action=list&message=' . urlencode("Store not found."));
            exit();
        }
    } elseif ($action === 'assign_admin') {
        try {
            $admin_stmt = $pdo->prepare('SELECT id, username FROM users WHERE role = "admin" AND is_active = 1');
            $admin_stmt->execute();
            $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching admins: " . $e->getMessage());
            $admins = [];
            $message = '<div class="alert alert-danger">Unable to fetch admins. Please try again later.</div>';
        }
    }
}
?>
<?php if ($action === 'list'): ?>
    <div class="container mt-4">
        <h2 class="mb-4">Store Management</h2>
        <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-info"><?= htmlspecialchars($_GET['message']) ?></div>
        <?php elseif ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <div class="d-flex justify-content-between mb-3">
            <a href="stores.php?action=create" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Store</a>
        </div>
        <div class="table-responsive shadow-sm">
            <table id="storesTable" class="table table-striped table-hover table-sm align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Logo</th>
                        <th>Manager</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stores)): ?>
                        <tr>
                            <td colspan="9" class="text-center">No stores found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($stores as $store): ?>
                            <tr>
                                <td><?= htmlspecialchars($store['name']) ?></td>
                                <td><?= htmlspecialchars($store['address']) ?></td>
                                <td><?= htmlspecialchars($store['phone']) ?></td>
                                <td><?= htmlspecialchars($store['email']) ?></td>
                                <td><?php if (!empty($store['logo'])): ?><img src="uploads/logos/<?= htmlspecialchars($store['logo']) ?>" alt="Logo" style="max-height:40px;"><?php else: ?><span class="text-muted">No Logo</span><?php endif; ?></td>
                                <td><?= htmlspecialchars($store['manager'] ?? 'N/A') ?></td>
                                <td><span class="badge <?= $store['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $store['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                <td><?= htmlspecialchars($store['created_at']) ?></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a href="stores.php?action=view&id=<?= $store['id'] ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                                        <a href="stores.php?action=edit&id=<?= $store['id'] ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="stores.php?action=delete&id=<?= $store['id'] ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this store?');"><i class="fas fa-trash-alt"></i></a>
                                        <a href="stores.php?action=toggle_status&id=<?= $store['id'] ?>" class="btn btn-sm <?= $store['is_active'] ? 'btn-secondary' : 'btn-success' ?>" title="<?= $store['is_active'] ? 'Deactivate' : 'Activate' ?>"><i class="fas <?= $store['is_active'] ? 'fa-toggle-off' : 'fa-toggle-on' ?>"></i></a>
                                        <a href="stores.php?action=assign_admin&id=<?= $store['id'] ?>" class="btn btn-sm btn-primary" title="Assign Admin"><i class="fas fa-user-cog"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php elseif ($action === 'create'): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-plus"></i> New Store</h2>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <form method="POST" action="stores.php?action=create" enctype="multipart/form-data" class="shadow p-4 bg-light rounded">
            <div class="row g-3">
                <div class="col-md-6"><label for="name" class="form-label">Name <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" id="name" name="name" required maxlength="100" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"></div>
                <div class="col-md-6"><label for="address" class="form-label">Address <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" id="address" name="address" required maxlength="255" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"></div>
                <div class="col-md-6"><label for="phone" class="form-label">Phone <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" id="phone" name="phone" required maxlength="20" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"></div>
                <div class="col-md-6"><label for="email" class="form-label">Email <span class="text-danger">*</span></label><input type="email" class="form-control form-control-sm" id="email" name="email" required maxlength="100" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></div>
                <div class="col-md-6"><label for="manager_id" class="form-label">Manager</label>
                    <select class="form-select form-select-sm" id="manager_id" name="manager_id">
                        <option value="">Select Manager</option>
                        <?php
                        try {
                            $admin_stmt = $pdo->prepare('SELECT id, username FROM users WHERE role = "admin" AND is_active = 1');
                            $admin_stmt->execute();
                            $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($admins as $admin) {
                                $sel = (isset($_POST['manager_id']) && $_POST['manager_id'] == $admin['id']) ? 'selected' : '';
                                echo "<option value=\"{$admin['id']}\" $sel>" . htmlspecialchars($admin['username']) . "</option>";
                            }
                        } catch (PDOException $e) {
                            error_log("Error fetching admins: " . $e->getMessage());
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6"><label for="logo" class="form-label">Logo</label><input type="file" class="form-control form-control-sm" id="logo" name="logo" accept="image/*"></div>
            </div>
            <hr>
            <h5>Weekly Schedule</h5>
            <?php
            $daysOfWeek = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
            foreach ($daysOfWeek as $d):
                $startField = strtolower($d) . "_start";
                $endField = strtolower($d) . "_end";
            ?>
                <div class="row g-2 mb-2">
                    <div class="col-md-2 d-flex align-items-center fw-bold"><?= $d ?></div>
                    <div class="col-md-5"><label class="form-label mb-0 small">Start Time</label><input type="time" class="form-control form-control-sm" name="<?= $startField ?>" value="<?= htmlspecialchars($_POST[$startField] ?? '') ?>"></div>
                    <div class="col-md-5"><label class="form-label mb-0 small">End Time</label><input type="time" class="form-control form-control-sm" name="<?= $endField ?>" value="<?= htmlspecialchars($_POST[$endField] ?? '') ?>"></div>
                </div>
            <?php endforeach; ?>
            <hr>
            <h5>Holidays</h5>
            <p class="small text-muted">Enter one holiday per line in the format: <code>YYYY-MM-DD,Description</code><br>For example: <code>2024-12-25,Christmas Day</code></p>
            <textarea class="form-control form-control-sm" name="holidays" rows="4"><?= htmlspecialchars($_POST['holidays'] ?? '') ?></textarea>
            <div class="d-flex gap-2 mt-4"><button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save"></i> Create Store</button><a href="stores.php?action=list" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Cancel</a></div>
        </form>
    </div>
<?php elseif ($action === 'edit' && isset($store)): ?>
    <?php
    $daysOfWeek = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
    $decoded = ["days" => [], "holidays" => []];
    if (!empty($store['work_schedule'])) {
        $temp = @json_decode($store['work_schedule'], true);
        if (is_array($temp)) {
            $decoded = $temp;
        }
    }
    ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-edit"></i> Edit Store</h2>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <form method="POST" action="stores.php?action=edit&id=<?= $store['id'] ?>" enctype="multipart/form-data" class="shadow p-4 bg-light rounded">
            <div class="row g-3">
                <div class="col-md-6"><label for="name" class="form-label">Name <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" id="name" name="name" required maxlength="100" value="<?= htmlspecialchars($_POST['name'] ?? $store['name']) ?>"></div>
                <div class="col-md-6"><label for="address" class="form-label">Address <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" id="address" name="address" required maxlength="255" value="<?= htmlspecialchars($_POST['address'] ?? $store['address']) ?>"></div>
                <div class="col-md-6"><label for="phone" class="form-label">Phone <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" id="phone" name="phone" required maxlength="20" value="<?= htmlspecialchars($_POST['phone'] ?? $store['phone']) ?>"></div>
                <div class="col-md-6"><label for="email" class="form-label">Email <span class="text-danger">*</span></label><input type="email" class="form-control form-control-sm" id="email" name="email" required maxlength="100" value="<?= htmlspecialchars($_POST['email'] ?? $store['email']) ?>"></div>
                <div class="col-md-6"><label for="manager_id" class="form-label">Manager</label>
                    <select class="form-select form-select-sm" id="manager_id" name="manager_id">
                        <option value="">Select Manager</option>
                        <?php
                        try {
                            $admin_stmt = $pdo->prepare('SELECT id, username FROM users WHERE role = "admin" AND is_active = 1');
                            $admin_stmt->execute();
                            $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($admins as $admin) {
                                $sel = ((isset($_POST['manager_id']) && $_POST['manager_id'] == $admin['id']) || (!isset($_POST['manager_id']) && $store['manager_id'] == $admin['id'])) ? 'selected' : '';
                                echo "<option value=\"{$admin['id']}\" $sel>" . htmlspecialchars($admin['username']) . "</option>";
                            }
                        } catch (PDOException $e) {
                            error_log("Error fetching admins: " . $e->getMessage());
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6"><label for="logo" class="form-label">Logo</label><input type="file" class="form-control form-control-sm" id="logo" name="logo" accept="image/*"><?php if (!empty($store['logo'])): ?><div class="mt-2"><img src="uploads/logos/<?= htmlspecialchars($store['logo']) ?>" alt="Current Logo" style="max-height:80px;"></div><?php endif; ?></div>
                <div class="col-md-6 align-self-end">
                    <div class="form-check"><input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" <?= ($store['is_active'] || (isset($_POST['is_active']) && $_POST['is_active'])) ? 'checked' : '' ?>><label class="form-check-label" for="is_active">Active</label></div>
                </div>
            </div>
            <hr>
            <h5>Weekly Schedule</h5>
            <?php
            foreach ($daysOfWeek as $d):
                $startField = strtolower($d) . "_start";
                $endField = strtolower($d) . "_end";
                $savedStart = $decoded['days'][$d]['start'] ?? '';
                $savedEnd = $decoded['days'][$d]['end'] ?? '';
            ?>
                <div class="row g-2 mb-2">
                    <div class="col-md-2 d-flex align-items-center fw-bold"><?= $d ?></div>
                    <div class="col-md-5"><label class="form-label mb-0 small">Start Time</label><input type="time" class="form-control form-control-sm" name="<?= $startField ?>" value="<?= htmlspecialchars($_POST[$startField] ?? $savedStart) ?>"></div>
                    <div class="col-md-5"><label class="form-label mb-0 small">End Time</label><input type="time" class="form-control form-control-sm" name="<?= $endField ?>" value="<?= htmlspecialchars($_POST[$endField] ?? $savedEnd) ?>"></div>
                </div>
            <?php endforeach; ?>
            <hr>
            <h5>Holidays</h5>
            <p class="small text-muted">One holiday per line: <code>YYYY-MM-DD,Description</code></p>
            <?php
            $holidaysText = "";
            if (!empty($decoded['holidays']) && is_array($decoded['holidays'])) {
                foreach ($decoded['holidays'] as $h) {
                    $holidaysText .= $h['date'] . "," . ($h['desc'] ?? "Holiday") . "\n";
                }
            }
            ?>
            <textarea class="form-control form-control-sm" name="holidays" rows="4"><?= htmlspecialchars($_POST['holidays'] ?? $holidaysText) ?></textarea>
            <div class="d-flex gap-2 mt-4"><button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save Changes</button><a href="stores.php?action=list" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Cancel</a></div>
        </form>
    </div>
<?php elseif ($action === 'delete' && $id > 0): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-trash-alt"></i> Delete Store</h2>
        <?php
        $stmt = $pdo->prepare('SELECT name, email FROM stores WHERE id = ?');
        $stmt->execute([$id]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$store):
        ?>
            <div class="alert alert-danger">Store not found.</div>
            <a href="stores.php?action=list" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to List</a>
        <?php else: ?>
            <div class="alert alert-warning">Are you sure you want to delete <strong><?= htmlspecialchars($store['name']) ?></strong> (<?= htmlspecialchars($store['email']) ?>)?</div>
            <form method="POST" action="stores.php?action=delete&id=<?= $id ?>">
                <div class="d-flex gap-2"><button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-check"></i> Yes, delete</button><a href="stores.php?action=list" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> No, cancel</a></div>
            </form>
        <?php endif; ?>
    </div>
<?php elseif ($action === 'view' && isset($store)): ?>
    <?php
    $decoded = @json_decode($store['work_schedule'] ?? '', true);
    if (!is_array($decoded)) {
        $decoded = ["days" => [], "holidays" => []];
    }
    ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-eye"></i> Store Details</h2>
        <ul class="nav nav-tabs" id="storeTabs" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab" aria-controls="details" aria-selected="true">Details</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" id="calendar-tab" data-bs-toggle="tab" data-bs-target="#calendarTab" type="button" role="tab" aria-controls="calendarTab" aria-selected="false">Calendar</button></li>
        </ul>
        <div class="tab-content border p-3" id="storeTabsContent">
            <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
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
                            <th>Address</th>
                            <td><?= htmlspecialchars($store['address']) ?></td>
                        </tr>
                        <tr>
                            <th>Phone</th>
                            <td><?= htmlspecialchars($store['phone']) ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?= htmlspecialchars($store['email']) ?></td>
                        </tr>
                        <tr>
                            <th>Logo</th>
                            <td><?php if (!empty($store['logo'])): ?><img src="uploads/logos/<?= htmlspecialchars($store['logo']) ?>" alt="Store Logo" style="max-height:80px;"><?php else: ?><span class="text-muted">No Logo</span><?php endif; ?></td>
                        </tr>
                        <tr>
                            <th>Manager</th>
                            <td><?= htmlspecialchars($store['manager'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td><?= $store['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                        </tr>
                        <tr>
                            <th>Weekly Schedule</th>
                            <td><?php if (!empty($decoded['days'])) {
                                    echo "<table class='table table-bordered table-sm mb-0'><tr class='table-secondary'><th>Day</th><th>Start</th><th>End</th></tr>";
                                    foreach ($decoded['days'] as $dayName => $info) {
                                        $start = htmlspecialchars($info['start'] ?? '');
                                        $end = htmlspecialchars($info['end'] ?? '');
                                        echo "<tr><td>{$dayName}</td><td>{$start}</td><td>{$end}</td></tr>";
                                    }
                                    echo "</table>";
                                } else {
                                    echo "<em>No schedule defined.</em>";
                                } ?></td>
                        </tr>
                        <tr>
                            <th>Holidays</th>
                            <td><?php if (!empty($decoded['holidays'])) {
                                    echo "<ul class='mb-0'>";
                                    foreach ($decoded['holidays'] as $h) {
                                        echo "<li><strong>" . htmlspecialchars($h['date']) . "</strong> - " . htmlspecialchars($h['desc']) . "</li>";
                                    }
                                    echo "</ul>";
                                } else {
                                    echo "<em>No holidays defined.</em>";
                                } ?></td>
                        </tr>
                        <tr>
                            <th>Created At</th>
                            <td><?= htmlspecialchars($store['created_at']) ?></td>
                        </tr>
                        <tr>
                            <th>Updated At</th>
                            <td><?= htmlspecialchars($store['updated_at'] ?? 'N/A') ?></td>
                        </tr>
                    </table>
                </div>
                <a href="stores.php?action=list" class="btn btn-secondary btn-sm mt-3"><i class="fas fa-arrow-left"></i> Back to List</a>
            </div>
            <div class="tab-pane fade" id="calendarTab" role="tabpanel" aria-labelledby="calendar-tab">
                <div id="storeCalendar" style="width:100%; max-width:100%; margin:0 auto;"></div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('storeCalendar');
            if (calendarEl) {
                var schedule = <?= json_encode($decoded) ?>;
                var events = [];
                if (schedule.days) {
                    var dayMap = {
                        "Sunday": 0,
                        "Monday": 1,
                        "Tuesday": 2,
                        "Wednesday": 3,
                        "Thursday": 4,
                        "Friday": 5,
                        "Saturday": 6
                    };
                    for (var d in schedule.days) {
                        if (!schedule.days[d]) continue;
                        var dow = (dayMap[d] !== undefined) ? [dayMap[d]] : [];
                        var startTime = schedule.days[d].start || '';
                        var endTime = schedule.days[d].end || '';
                        if (startTime && endTime && dow.length) {
                            events.push({
                                title: 'Open',
                                daysOfWeek: dow,
                                startTime: startTime,
                                endTime: endTime
                            });
                        }
                    }
                }
                if (Array.isArray(schedule.holidays)) {
                    schedule.holidays.forEach(function(h) {
                        events.push({
                            title: h.desc || 'Holiday',
                            start: h.date,
                            allDay: true,
                            color: '#dc3545'
                        });
                    });
                }
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    height: 600,
                    headerToolbar: {
                        start: 'prev,next today',
                        center: 'title',
                        end: 'dayGridMonth,timeGridWeek,listWeek'
                    },
                    events: events
                });
                calendar.render();
            }
        });
    </script>
<?php elseif ($action === 'assign_admin' && isset($store)): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-user-cog"></i> Assign Administrator</h2>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <form method="POST" action="stores.php?action=assign_admin&id=<?= $store['id'] ?>" class="shadow p-4 bg-light rounded">
            <div class="mb-3"><label for="admin_id" class="form-label">Select an Admin</label>
                <select class="form-select form-select-sm" id="admin_id" name="admin_id">
                    <option value="">Select Administrator</option>
                    <?php if (!empty($admins)): foreach ($admins as $admin): ?>
                            <option value="<?= $admin['id'] ?>" <?= ($store['manager_id'] == $admin['id']) ? 'selected' : '' ?>><?= htmlspecialchars($admin['username']) ?></option>
                    <?php endforeach;
                    endif; ?>
                </select>
            </div>
            <div class="border-top pt-3 mt-3">
                <h5>Create a New Admin</h5>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" value="1" id="create_new_admin" name="create_new_admin"><label class="form-check-label" for="create_new_admin">Create new admin user</label></div>
                <div class="mb-2"><label for="new_admin_username" class="form-label">Username</label><input type="text" class="form-control form-control-sm" id="new_admin_username" name="new_admin_username"></div>
                <div class="mb-3"><label for="new_admin_password" class="form-label">Password</label><input type="password" class="form-control form-control-sm" id="new_admin_password" name="new_admin_password"></div>
            </div>
            <div class="d-flex gap-2 mt-3"><button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save</button><a href="stores.php?action=list" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Cancel</a></div>
        </form>
    </div>
<?php endif; ?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.6/index.global.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.6/index.global.min.js"></script>
<script>
    $(function() {
        $('.form-select').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });
        $('#storesTable').DataTable({
            language: {
                emptyTable: "No stores found."
            }
        });
    });
</script>
<?php require_once 'includes/footer.php'; ?>