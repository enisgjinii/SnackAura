<?php
// Start output buffering to prevent accidental output
ob_start();

// Include database connection
require_once 'includes/db_connect.php';

// Initialize variables
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

// Function to generate unique waiter code
function generateWaiterCode($pdo)
{
    $stmt = $pdo->query("SELECT code FROM users WHERE role = 'waiter' ORDER BY code DESC LIMIT 1");
    $lastCode = $stmt->fetchColumn();
    $number = $lastCode ? ((int)substr($lastCode, 2)) + 1 : 1;
    return 'w-' . str_pad($number, 3, '0', STR_PAD_LEFT);
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        // Create user logic
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'waiter';

        if (
            empty($username) || empty($password) || empty($email) ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            !in_array($role, ['super-admin', 'admin', 'waiter', 'delivery'])
        ) {
            $message = "All fields are required and must be valid.";
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn() > 0) {
                $message = "Username or email already exists.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $code = $role === 'waiter' ? generateWaiterCode($pdo) : null;
                $stmt = $pdo->prepare('INSERT INTO users (username, password, email, role, code) VALUES (?, ?, ?, ?, ?)');
                try {
                    $stmt->execute([$username, $hashed_password, $email, $role, $code]);
                    header('Location: users.php?action=list&message=' . urlencode("User created successfully."));
                    exit();
                } catch (PDOException $e) {
                    error_log("Error creating user: " . $e->getMessage());
                    $message = "Failed to create user.";
                }
            }
        }
    } elseif ($action === 'edit' && $id > 0) {
        // Edit user logic
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'waiter';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $password = $_POST['password'] ?? '';

        if (
            empty($username) || empty($email) ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            !in_array($role, ['super-admin', 'admin', 'waiter', 'delivery'])
        ) {
            $message = "Username and Email are required and must be valid.";
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?');
            $stmt->execute([$username, $email, $id]);
            if ($stmt->fetchColumn() > 0) {
                $message = "Username or email already exists.";
            } else {
                $params = [$username, $email, $role, $is_active];
                $sql = 'UPDATE users SET username = ?, email = ?, role = ?, is_active = ?';

                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $sql .= ', password = ?';
                    $params[] = $hashed_password;
                }

                if ($role === 'waiter') {
                    $stmt = $pdo->prepare('SELECT code FROM users WHERE id = ?');
                    $stmt->execute([$id]);
                    $existingCode = $stmt->fetchColumn();
                    if (!$existingCode) {
                        $sql .= ', code = ?';
                        $params[] = generateWaiterCode($pdo);
                    }
                } else {
                    $sql .= ', code = NULL';
                }

                $sql .= ' WHERE id = ?';
                $params[] = $id;

                $stmt = $pdo->prepare($sql);
                try {
                    $stmt->execute($params);
                    header('Location: users.php?action=list&message=' . urlencode("User updated successfully."));
                    exit();
                } catch (PDOException $e) {
                    error_log("Error updating user (ID: $id): " . $e->getMessage());
                    $message = "Failed to update user.";
                }
            }
        }
    } elseif ($action === 'delete' && $id > 0) {
        // Delete user logic
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        try {
            $stmt->execute([$id]);
            header('Location: users.php?action=list&message=' . urlencode("User deleted successfully."));
            exit();
        } catch (PDOException $e) {
            error_log("Error deleting user (ID: $id): " . $e->getMessage());
            header('Location: users.php?action=list&message=' . urlencode("Failed to delete user."));
            exit();
        }
    }
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'toggle_status' && $id > 0) {
    $stmt = $pdo->prepare('SELECT is_active FROM users WHERE id = ?');
    $stmt->execute([$id]);
    if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $new_status = $user['is_active'] ? 0 : 1;
        $stmt = $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        try {
            $stmt->execute([$new_status, $id]);
            $status_text = $new_status ? 'activated' : 'deactivated';
            header('Location: users.php?action=list&message=' . urlencode("User has been {$status_text}."));
            exit();
        } catch (PDOException $e) {
            error_log("Error toggling status (ID: $id): " . $e->getMessage());
            header('Location: users.php?action=list&message=' . urlencode("Failed to toggle user status."));
            exit();
        }
    } else {
        header('Location: users.php?action=list&message=' . urlencode("User not found."));
        exit();
    }
}

// Fetch data based on the action
if ($action === 'list') {
    try {
        $stmt = $pdo->query('SELECT id, username, email, role, is_active, created_at, code FROM users ORDER BY created_at DESC');
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching users: " . $e->getMessage());
        $users = [];
        $message = "Failed to fetch users.";
    }
} elseif ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare('SELECT id, username, email, role, is_active, code FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        header('Location: users.php?action=list&message=' . urlencode("User not found."));
        exit();
    }
} elseif ($action === 'delete' && $id > 0) {
    $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Now include the header (which outputs content)
require_once 'includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <h2>Manage Users</h2>
    <?php if (isset($_GET['message']) || $message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($_GET['message'] ?? '') . htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <a href="users.php?action=create" class="btn btn-primary mb-3">Create New User</a>
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Code</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="8" class="text-center">No users found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['id']) ?></td>
                        <td><?= $user['role'] === 'waiter' && $user['code'] ? htmlspecialchars($user['code']) : '-' ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars(ucfirst($user['role'])) ?></td>
                        <td><span class="badge bg-<?= $user['is_active'] ? 'success' : 'secondary' ?>"><?= $user['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td><?= htmlspecialchars($user['created_at']) ?></td>
                        <td>
                            <a href="users.php?action=edit&id=<?= $user['id'] ?>" class="btn btn-sm btn-info">Edit</a>
                            <a href="users.php?action=delete&id=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                            <a href="users.php?action=toggle_status&id=<?= $user['id'] ?>" class="btn btn-sm <?= $user['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                                <?= $user['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
<?php elseif ($action === 'create'): ?>
    <h2>Create New User</h2>
    <?= $message ? '<div class="alert alert-danger">' . htmlspecialchars($message) . '</div>' : '' ?>
    <form method="POST" action="users.php?action=create">
        <div class="mb-3">
            <label for="username" class="form-label">Username<span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="username" name="username" required maxlength="50" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password<span class="text-danger">*</span></label>
            <input type="password" class="form-control" id="password" name="password" required minlength="6">
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email<span class="text-danger">*</span></label>
            <input type="email" class="form-control" id="email" name="email" required maxlength="100" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="role" class="form-label">Role<span class="text-danger">*</span></label>
            <select class="form-select" id="role" name="role" required>
                <option value="super-admin" <?= (($_POST['role'] ?? '') === 'super-admin') ? 'selected' : '' ?>>Super Admin</option>
                <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                <option value="waiter" <?= (($_POST['role'] ?? '') === 'waiter') ? 'selected' : '' ?>>Waiter</option>
                <option value="delivery" <?= (($_POST['role'] ?? '') === 'delivery') ? 'selected' : '' ?>>Delivery</option>
            </select>
        </div>
        <button type="submit" class="btn btn-success">Create User</button>
        <a href="users.php?action=list" class="btn btn-secondary">Cancel</a>
    </form>
<?php elseif ($action === 'edit' && isset($user)): ?>
    <h2>Edit User</h2>
    <?= $message ? '<div class="alert alert-danger">' . htmlspecialchars($message) . '</div>' : '' ?>
    <form method="POST" action="users.php?action=edit&id=<?= $user['id'] ?>">
        <div class="mb-3">
            <label for="username" class="form-label">Username<span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="username" name="username" required maxlength="50" value="<?= htmlspecialchars($_POST['username'] ?? $user['username']) ?>">
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password (Leave blank to keep current)</label>
            <input type="password" class="form-control" id="password" name="password" minlength="6">
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email<span class="text-danger">*</span></label>
            <input type="email" class="form-control" id="email" name="email" required maxlength="100" value="<?= htmlspecialchars($_POST['email'] ?? $user['email']) ?>">
        </div>
        <div class="mb-3">
            <label for="role" class="form-label">Role<span class="text-danger">*</span></label>
            <select class="form-select" id="role" name="role" required>
                <option value="super-admin" <?= (($_POST['role'] ?? $user['role']) === 'super-admin') ? 'selected' : '' ?>>Super Admin</option>
                <option value="admin" <?= (($_POST['role'] ?? $user['role']) === 'admin') ? 'selected' : '' ?>>Admin</option>
                <option value="waiter" <?= (($_POST['role'] ?? $user['role']) === 'waiter') ? 'selected' : '' ?>>Waiter</option>
                <option value="delivery" <?= (($_POST['role'] ?? $user['role']) === 'delivery') ? 'selected' : '' ?>>Delivery</option>
            </select>
        </div>
        <?php if ($user['role'] === 'waiter' && $user['code']): ?>
            <div class="mb-3">
                <label class="form-label">Waiter Code</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($user['code']) ?>" readonly>
            </div>
        <?php endif; ?>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" <?= ($user['is_active'] || isset($_POST['is_active'])) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
        <button type="submit" class="btn btn-primary">Update User</button>
        <a href="users.php?action=list" class="btn btn-secondary">Cancel</a>
    </form>
<?php elseif ($action === 'delete' && isset($user)): ?>
    <h2>Delete User</h2>
    <div class="alert alert-warning">
        Are you sure you want to delete the user <strong><?= htmlspecialchars($user['username']) ?></strong> (<?= htmlspecialchars($user['email']) ?>)?
    </div>
    <form method="POST" action="users.php?action=delete&id=<?= $user['id'] ?>">
        <button type="submit" class="btn btn-danger">Yes, Delete</button>
        <a href="users.php?action=list" class="btn btn-secondary">Cancel</a>
    </form>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php
require_once 'includes/footer.php';
// Flush the output buffer
ob_end_flush();
?>