<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Initialize variables
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$message = '';

// Handle different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        // Handle user creation
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'waiter';
        // Input validation
        if (empty($username) || empty($password) || empty($email)) {
            $message = '<div class="alert alert-danger">All fields are required.</div>';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '<div class="alert alert-danger">Invalid email format.</div>';
        } elseif (!in_array($role, ['admin', 'waiter', 'delivery'])) {
            $message = '<div class="alert alert-danger">Invalid role selected.</div>';
        } else {
            // Check for existing username or email
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn() > 0) {
                $message = '<div class="alert alert-danger">Username or email already exists.</div>';
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                // Insert the new user
                $stmt = $pdo->prepare('INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)');
                try {
                    $stmt->execute([$username, $hashed_password, $email, $role]);
                    header('Location: users.php?action=list&message=' . urlencode("User created successfully."));
                    exit();
                } catch (PDOException $e) {
                    error_log("Error creating user: " . $e->getMessage());
                    $message = '<div class="alert alert-danger">Failed to create user. Please try again later.</div>';
                }
            }
        }
    } elseif ($action === 'edit' && $id > 0) {
        // Handle user update
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'waiter';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $password = $_POST['password'] ?? '';

        // Input validation
        if (empty($username) || empty($email)) {
            $message = '<div class="alert alert-danger">Username and Email are required.</div>';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '<div class="alert alert-danger">Invalid email format.</div>';
        } elseif (!in_array($role, ['admin', 'waiter', 'delivery'])) {
            $message = '<div class="alert alert-danger">Invalid role selected.</div>';
        } else {
            // Check for existing username or email excluding current user
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?');
            $stmt->execute([$username, $email, $id]);
            if ($stmt->fetchColumn() > 0) {
                $message = '<div class="alert alert-danger">Username or email already exists.</div>';
            } else {
                // Prepare SQL and parameters
                if (!empty($password)) {
                    // If password is provided, update it
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $sql = 'UPDATE users SET username = ?, email = ?, role = ?, is_active = ?, password = ? WHERE id = ?';
                    $params = [$username, $email, $role, $is_active, $hashed_password, $id];
                } else {
                    // If password is not provided, don't update it
                    $sql = 'UPDATE users SET username = ?, email = ?, role = ?, is_active = ? WHERE id = ?';
                    $params = [$username, $email, $role, $is_active, $id];
                }

                // Update the user
                $stmt = $pdo->prepare($sql);
                try {
                    $stmt->execute($params);
                    header('Location: users.php?action=list&message=' . urlencode("User updated successfully."));
                    exit();
                } catch (PDOException $e) {
                    error_log("Error updating user (User ID: $id): " . $e->getMessage());
                    $message = '<div class="alert alert-danger">Failed to update user. Please try again later.</div>';
                }
            }
        }
    } elseif ($action === 'delete' && $id > 0) {
        // Handle user deletion
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        try {
            $stmt->execute([$id]);
            header('Location: users.php?action=list&message=' . urlencode("User deleted successfully."));
            exit();
        } catch (PDOException $e) {
            error_log("Error deleting user (User ID: $id): " . $e->getMessage());
            header('Location: users.php?action=list&message=' . urlencode("Failed to delete user. Please try again later."));
            exit();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'toggle_status' && $id > 0) {
        // Handle user activation/deactivation
        // Fetch current status
        $stmt = $pdo->prepare('SELECT is_active FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $new_status = $user['is_active'] ? 0 : 1;
            // Update status
            $stmt = $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
            try {
                $stmt->execute([$new_status, $id]);
                $status_text = $new_status ? 'activated' : 'deactivated';
                header('Location: users.php?action=list&message=' . urlencode("User has been {$status_text} successfully."));
                exit();
            } catch (PDOException $e) {
                error_log("Error toggling user status (User ID: $id): " . $e->getMessage());
                header('Location: users.php?action=list&message=' . urlencode("Failed to toggle user status. Please try again later."));
                exit();
            }
        } else {
            header('Location: users.php?action=list&message=' . urlencode("User not found."));
            exit();
        }
    }
}

// Fetch data based on the action
if ($action === 'list') {
    // Fetch all users
    try {
        $stmt = $pdo->query('SELECT id, username, email, role, is_active, created_at FROM users ORDER BY created_at DESC');
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching users: " . $e->getMessage());
        $users = [];
        $message = '<div class="alert alert-danger">Failed to fetch users. Please try again later.</div>';
    }
} elseif ($action === 'create') {
    // Display user creation form
    // The form will be displayed in the HTML section below
} elseif ($action === 'edit' && $id > 0) {
    // Fetch user data for editing
    $stmt = $pdo->prepare('SELECT id, username, email, role, is_active FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        header('Location: users.php?action=list&message=' . urlencode("User not found."));
        exit();
    }
} elseif ($action === 'delete' && $id > 0) {
    // Confirmation for deletion will be handled in the HTML section
    // No additional PHP logic needed here
}
?>
<?php if ($action === 'list'): ?>
    <h2>Manage Users</h2>
    <?php if (isset($_GET['message'])): ?>
        <div class="alert alert-info"><?= htmlspecialchars($_GET['message']) ?></div>
    <?php elseif ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <a href="users.php?action=create" class="btn btn-primary mb-3">Create New User</a>
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
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
                    <td colspan="7" class="text-center">No users found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['id']) ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars(ucfirst($user['role'])) ?></td>
                        <td>
                            <?php if ($user['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
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
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
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
                <option value="waiter" <?= (($_POST['role'] ?? '') === 'waiter') ? 'selected' : '' ?>>Kamarierë (Waiter)</option>
                <option value="delivery" <?= (($_POST['role'] ?? '') === 'delivery') ? 'selected' : '' ?>>Delivery</option>
                <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Adminë (Admin)</option>
            </select>
        </div>
        <button type="submit" class="btn btn-success">Create User</button>
        <a href="users.php?action=list" class="btn btn-secondary">Cancel</a>
    </form>
<?php elseif ($action === 'edit' && isset($user)): ?>
    <h2>Edit User</h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
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
                <option value="waiter" <?= ((($_POST['role'] ?? $user['role']) === 'waiter') ? 'selected' : '') ?>>Kamarierë (Waiter)</option>
                <option value="delivery" <?= ((($_POST['role'] ?? $user['role']) === 'delivery') ? 'selected' : '') ?>>Delivery</option>
                <option value="admin" <?= ((($_POST['role'] ?? $user['role']) === 'admin') ? 'selected' : '') ?>>Adminë (Admin)</option>
            </select>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" <?= (($user['is_active'] || (isset($_POST['is_active']) && $_POST['is_active'])) ? 'checked' : '') ?>>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
        <button type="submit" class="btn btn-primary">Update User</button>
        <a href="users.php?action=list" class="btn btn-secondary">Cancel</a>
    </form>
<?php elseif ($action === 'delete' && $id > 0): ?>
    <h2>Delete User</h2>
    <?php
    // Fetch user data for confirmation
    $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user):
    ?>
        <div class="alert alert-danger">User not found.</div>
        <a href="users.php?action=list" class="btn btn-secondary">Back to List</a>
    <?php else: ?>
        <div class="alert alert-warning">
            Are you sure you want to delete the user <strong><?= htmlspecialchars($user['username']) ?></strong> (<?= htmlspecialchars($user['email']) ?>)?
        </div>
        <form method="POST" action="users.php?action=delete&id=<?= $user['id'] ?>">
            <button type="submit" class="btn btn-danger">Yes, Delete</button>
            <a href="users.php?action=list" class="btn btn-secondary">Cancel</a>
        </form>
    <?php endif; ?>
<?php endif; ?>
<!-- Include Bootstrap JS and dependencies (if not already included in 'includes/header.php') -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php
require_once 'includes/footer.php';
?>