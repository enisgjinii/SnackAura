<?php
ob_start();
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `role` ENUM('super-admin','admin','waiter','delivery') NOT NULL DEFAULT 'waiter',
            `code` VARCHAR(10) DEFAULT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Tabelle konnte nicht erstellt werden: ' . htmlspecialchars($e->getMessage())];
    header('Location: users.php?action=list');
    exit();
}

$action = $_REQUEST['action'] ?? 'list';
$id     = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;

function sanitizeInput($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function generateWaiterCode($pdo)
{
    $stmt = $pdo->query("SELECT code FROM users WHERE role = 'waiter' ORDER BY code DESC LIMIT 1");
    $lastCode = $stmt->fetchColumn();
    $number = $lastCode ? ((int)substr($lastCode, 2)) + 1 : 1;
    return 'w-' . str_pad($number, 3, '0', STR_PAD_LEFT);
}

function validateUser($pdo, $data, $id = 0)
{
    $errors = [];
    $username = sanitizeInput($data['username'] ?? '');
    $email = sanitizeInput($data['email'] ?? '');
    $role = sanitizeInput($data['role'] ?? '');
    if (empty($username) || empty($email) || empty($role)) {
        $errors[] = 'Benutzername, E-Mail und Rolle sind erforderlich.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Ungültige E-Mail-Adresse.';
    }
    if (!in_array($role, ['super-admin', 'admin', 'waiter', 'delivery'])) {
        $errors[] = 'Ungültige Rolle.';
    }
    $sql = "SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?)";
    $params = [$username, $email];
    if ($id > 0) {
        $sql .= " AND id != ?";
        $params[] = $id;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'Benutzername oder E-Mail existiert bereits.';
    }
    return [$errors, compact('username', 'email', 'role')];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        list($errors, $data) = validateUser($pdo, $_POST);
        $password = $_POST['password'] ?? '';
        if (empty($password)) {
            $errors[] = 'Passwort ist erforderlich.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Passwort muss mindestens 6 Zeichen lang sein.';
        }
        if ($errors) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => implode('<br>', $errors)];
            header("Location: users.php?action=list");
            exit;
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $code = $data['role'] === 'waiter' ? generateWaiterCode($pdo) : null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $stmt = $pdo->prepare('INSERT INTO users (username, password, email, role, code, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
            try {
                $stmt->execute([$data['username'], $hashed_password, $data['email'], $data['role'], $code, $is_active]);
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Benutzer erfolgreich erstellt.'];
                header("Location: users.php?action=list");
                exit();
            } catch (PDOException $e) {
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler beim Erstellen des Benutzers.'];
                header("Location: users.php?action=list");
                exit();
            }
        }
    } elseif ($action === 'edit' && $id > 0) {
        list($errors, $data) = validateUser($pdo, $_POST, $id);
        $password = $_POST['password'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        if ($errors) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => implode('<br>', $errors)];
            header("Location: users.php?action=list");
            exit;
        } else {
            $stmt = $pdo->prepare("SELECT code FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $existingCode = $stmt->fetchColumn();
            $params = [$data['username'], $data['email'], $data['role'], $is_active];
            $sql = 'UPDATE users SET username = ?, email = ?, role = ?, is_active = ?, updated_at = NOW()';
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $sql .= ', password = ?';
                $params[] = $hashed_password;
            }
            if ($data['role'] === 'waiter') {
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
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Benutzer erfolgreich aktualisiert.'];
                header("Location: users.php?action=list");
                exit();
            } catch (PDOException $e) {
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler beim Aktualisieren des Benutzers.'];
                header("Location: users.php?action=list");
                exit();
            }
        }
    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        try {
            $stmt->execute([$id]);
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Benutzer erfolgreich gelöscht.'];
            header("Location: users.php?action=list");
            exit();
        } catch (PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler beim Löschen des Benutzers.'];
            header("Location: users.php?action=list");
            exit();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'toggle_status' && $id > 0) {
    $stmt = $pdo->prepare('SELECT is_active FROM users WHERE id = ?');
    $stmt->execute([$id]);
    if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $new_status = $user['is_active'] ? 0 : 1;
        $stmt = $pdo->prepare('UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?');
        try {
            $stmt->execute([$new_status, $id]);
            $status_text = $new_status ? 'aktiviert' : 'deaktiviert';
            $_SESSION['toast'] = ['type' => 'success', 'message' => "Benutzer wurde {$status_text}."];
            header("Location: users.php?action=list");
            exit();
        } catch (PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler beim Ändern des Benutzerstatus.'];
            header("Location: users.php?action=list");
            exit();
        }
    } else {
        $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Benutzer nicht gefunden.'];
        header("Location: users.php?action=list");
        exit();
    }
}

if ($action === 'list') {
    try {
        $stmt = $pdo->query('SELECT id, username, email, role, is_active, created_at, code FROM users ORDER BY created_at DESC');
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler beim Abrufen der Benutzer.'];
        $users = [];
    }
}
?>

<style>
/* Users Page Styles */
.users-container {
    padding: 2rem;
    background: #f8fafc;
    min-height: calc(100vh - 80px);
}

.page-header {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
}

.page-title {
    font-size: 1.875rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.page-subtitle {
    color: #64748b;
    margin: 0.5rem 0 0 0;
    font-size: 0.875rem;
}

.add-user-btn {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border: none;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 6px rgba(16, 185, 129, 0.25);
}

.add-user-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 12px rgba(16, 185, 129, 0.3);
    color: white;
}

.users-table-section {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
}

.table-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e2e8f0;
}

.table-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.user-count {
    background: #f1f5f9;
    color: #64748b;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
}

.users-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.users-table th {
    background: #f8fafc;
    color: #475569;
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 1rem;
    text-align: left;
    border-bottom: 2px solid #e2e8f0;
}

.users-table td {
    padding: 1rem;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    font-size: 0.875rem;
}

.users-table tr:hover {
    background: #f8fafc;
}

.user-id {
    font-weight: 600;
    color: #64748b;
    font-family: 'Monaco', 'Menlo', monospace;
}

.user-code {
    font-weight: 700;
    color: #1e293b;
    font-family: 'Monaco', 'Menlo', monospace;
    background: #f1f5f9;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
}

.user-username {
    font-weight: 600;
    color: #1e293b;
}

.user-email {
    color: #64748b;
    font-size: 0.875rem;
}

.user-role {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}

.user-role.super-admin {
    background: #fef3c7;
    color: #92400e;
}

.user-role.admin {
    background: #dbeafe;
    color: #1e40af;
}

.user-role.waiter {
    background: #dcfce7;
    color: #166534;
}

.user-role.delivery {
    background: #fef3c7;
    color: #92400e;
}

.user-status {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}

.user-status.active {
    background: #dcfce7;
    color: #166534;
}

.user-status.inactive {
    background: #f3f4f6;
    color: #6b7280;
}

.user-date {
    color: #64748b;
    font-size: 0.75rem;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-edit-user {
    background: #f59e0b;
    border: none;
    color: white;
    padding: 0.5rem;
    border-radius: 6px;
    font-size: 0.75rem;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
}

.btn-edit-user:hover {
    background: #d97706;
    color: white;
    transform: translateY(-1px);
}

.btn-delete-user {
    background: #ef4444;
    border: none;
    color: white;
    padding: 0.5rem;
    border-radius: 6px;
    font-size: 0.75rem;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
}

.btn-delete-user:hover {
    background: #dc2626;
    color: white;
    transform: translateY(-1px);
}

.btn-toggle-status {
    background: #3b82f6;
    border: none;
    color: white;
    padding: 0.5rem;
    border-radius: 6px;
    font-size: 0.75rem;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
}

.btn-toggle-status:hover {
    background: #2563eb;
    color: white;
    transform: translateY(-1px);
}

/* Offcanvas Styles */
.offcanvas {
    border-radius: 12px 0 0 12px;
    box-shadow: -4px 0 12px rgba(0, 0, 0, 0.1);
}

.offcanvas-header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 1.5rem;
}

.offcanvas-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.offcanvas-body {
    padding: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
    display: block;
    font-size: 0.875rem;
}

.required {
    color: #ef4444;
}

.form-control, .form-select {
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 0.75rem;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    background: white;
}

.form-control:focus, .form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
}

.form-check {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}

.form-check-input {
    width: 1.25rem;
    height: 1.25rem;
    border: 2px solid #d1d5db;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.form-check-input:checked {
    background-color: #10b981;
    border-color: #10b981;
}

.form-check-label {
    font-weight: 600;
    color: #374151;
    font-size: 0.875rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e5e7eb;
}

.btn-save {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border: none;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-save:hover {
    transform: translateY(-1px);
    color: white;
}

.btn-cancel {
    background: #6b7280;
    border: none;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-cancel:hover {
    background: #4b5563;
    color: white;
    text-decoration: none;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #64748b;
}

.empty-state-icon {
    font-size: 3rem;
    color: #cbd5e1;
    margin-bottom: 1rem;
}

.empty-state-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 0.5rem;
}

.empty-state-text {
    color: #64748b;
    margin-bottom: 2rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .users-container {
        padding: 1rem;
    }
    
    .page-header {
        padding: 1.5rem;
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .users-table-section {
        padding: 1rem;
    }
    
    .users-table {
        font-size: 0.75rem;
    }
    
    .users-table th,
    .users-table td {
        padding: 0.75rem 0.5rem;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 0.25rem;
    }
}
</style>

<?php if ($action === 'list'): ?>
    <div class="users-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-users"></i>
                        Users Management
                    </h1>
                    <p class="page-subtitle">Manage system users and their roles</p>
                </div>
                <button class="add-user-btn" data-bs-toggle="offcanvas" data-bs-target="#createUserOffcanvas">
                    <i class="fas fa-user-plus"></i>
                    Add New User
                </button>
            </div>
        </div>

        <!-- Users Table Section -->
        <div class="users-table-section">
            <div class="table-header">
                <div class="d-flex align-items-center gap-3">
                    <h3 class="table-title">All Users</h3>
                    <span class="user-count"><?= count($users ?? []) ?> users</span>
                </div>
            </div>
            
            <?php if (!empty($users)): ?>
                <div class="table-responsive">
                    <table id="usersTable" class="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Code</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td class="user-id">#<?= $u['id'] ?></td>
                                    <td class="user-code">
                                        <?= ($u['role'] === 'waiter' && $u['code']) ? sanitizeInput($u['code']) : '-' ?>
                                    </td>
                                    <td class="user-username"><?= sanitizeInput($u['username']) ?></td>
                                    <td class="user-email"><?= sanitizeInput($u['email']) ?></td>
                                    <td>
                                        <span class="user-role <?= str_replace('-', '-', $u['role']) ?>">
                                            <i class="fas fa-<?= $u['role'] === 'super-admin' ? 'crown' : ($u['role'] === 'admin' ? 'user-shield' : ($u['role'] === 'waiter' ? 'user-tie' : 'truck')) ?>"></i>
                                            <?= ucfirst(str_replace('-', ' ', $u['role'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="user-status <?= ($u['is_active'] ? 'active' : 'inactive') ?>">
                                            <i class="fas fa-<?= ($u['is_active'] ? 'check-circle' : 'times-circle') ?>"></i>
                                            <?= ($u['is_active'] ? 'Active' : 'Inactive') ?>
                                        </span>
                                    </td>
                                    <td class="user-date">
                                        <?= date('M j, Y', strtotime($u['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-edit-user edit-user-btn"
                                                data-id="<?= $u['id'] ?>"
                                                data-username="<?= sanitizeInput($u['username']) ?>"
                                                data-email="<?= sanitizeInput($u['email']) ?>"
                                                data-role="<?= sanitizeInput($u['role']) ?>"
                                                data-active="<?= $u['is_active'] ?>"
                                                data-code="<?= sanitizeInput($u['code']) ?>"
                                                data-bs-toggle="offcanvas"
                                                data-bs-target="#editUserOffcanvas"
                                                title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-delete-user delete-user-btn"
                                                data-id="<?= $u['id'] ?>"
                                                data-username="<?= sanitizeInput($u['username']) ?>"
                                                title="Delete User">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <button class="btn-toggle-status toggle-status-btn"
                                                data-id="<?= $u['id'] ?>"
                                                data-username="<?= sanitizeInput($u['username']) ?>"
                                                data-status="<?= $u['is_active'] ? 'deaktivieren' : 'aktivieren' ?>"
                                                title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> User">
                                                <i class="fas <?= $u['is_active'] ? 'fa-toggle-off' : 'fa-toggle-on' ?>"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="empty-state-title">No Users Found</h3>
                    <p class="empty-state-text">Start by creating your first system user.</p>
                    <button class="add-user-btn" data-bs-toggle="offcanvas" data-bs-target="#createUserOffcanvas">
                        <i class="fas fa-user-plus"></i>
                        Add First User
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create User Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="createUserOffcanvas" aria-labelledby="createUserOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="createUserOffcanvasLabel">
                <i class="fas fa-user-plus me-2"></i>
                Add New User
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="users.php?action=create">
                <div class="form-group">
                    <label for="create_username" class="form-label">
                        Username <span class="required">*</span>
                    </label>
                    <input type="text" name="username" id="create_username" class="form-control" 
                           placeholder="Enter username" required maxlength="50" 
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="create_password" class="form-label">
                        Password <span class="required">*</span>
                    </label>
                    <input type="password" name="password" id="create_password" class="form-control" 
                           placeholder="Enter password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="create_email" class="form-label">
                        Email <span class="required">*</span>
                    </label>
                    <input type="email" name="email" id="create_email" class="form-control" 
                           placeholder="Enter email address" required maxlength="100" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="create_role" class="form-label">
                        Role <span class="required">*</span>
                    </label>
                    <select name="role" id="create_role" class="form-select" required>
                        <option value="">Select role</option>
                        <option value="super-admin">Super Admin</option>
                        <option value="admin">Admin</option>
                        <option value="waiter">Waiter</option>
                        <option value="delivery">Delivery</option>
                    </select>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="is_active" id="create_is_active" class="form-check-input" value="1" checked>
                    <label class="form-check-label" for="create_is_active">Active</label>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i>
                        Save User
                    </button>
                    <button type="button" class="btn-cancel" data-bs-dismiss="offcanvas">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="editUserOffcanvas" aria-labelledby="editUserOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="editUserOffcanvasLabel">
                <i class="fas fa-edit me-2"></i>
                Edit User
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" id="editForm" action="users.php?action=edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label for="edit_username" class="form-label">
                        Username <span class="required">*</span>
                    </label>
                    <input type="text" name="username" id="edit_username" class="form-control" 
                           placeholder="Enter username" required maxlength="50">
                </div>
                <div class="form-group">
                    <label for="edit_password" class="form-label">
                        Password (Leave empty to keep current)
                    </label>
                    <input type="password" name="password" id="edit_password" class="form-control" 
                           placeholder="Enter new password" minlength="6">
                </div>
                <div class="form-group">
                    <label for="edit_email" class="form-label">
                        Email <span class="required">*</span>
                    </label>
                    <input type="email" name="email" id="edit_email" class="form-control" 
                           placeholder="Enter email address" required maxlength="100">
                </div>
                <div class="form-group">
                    <label for="edit_role" class="form-label">
                        Role <span class="required">*</span>
                    </label>
                    <select name="role" id="edit_role" class="form-select" required>
                        <option value="">Select role</option>
                        <option value="super-admin">Super Admin</option>
                        <option value="admin">Admin</option>
                        <option value="waiter">Waiter</option>
                        <option value="delivery">Delivery</option>
                    </select>
                </div>
                <div class="form-group" id="waiter_code_section" style="display: none;">
                    <label for="edit_code" class="form-label">Waiter Code</label>
                    <input type="text" id="edit_code" class="form-control" readonly>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="is_active" id="edit_is_active" class="form-check-input">
                    <label class="form-check-label" for="edit_is_active">Active</label>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i>
                        Update User
                    </button>
                    <button type="button" class="btn-cancel" data-bs-dismiss="offcanvas">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

<?php endif; ?>

<?php
require_once 'includes/footer.php';
ob_end_flush();
?>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#usersTable').DataTable({
        "paging": true,
        "searching": true,
        "info": true,
        "order": [[6, "desc"]],
        "pageLength": 25,
        "dom": '<"row mb-3"' +
            '<"col-12 d-flex justify-content-between align-items-center"lBf>' +
            '>' +
            'rt' +
            '<"row mt-3"' +
            '<"col-sm-12 col-md-6 d-flex justify-content-start"i>' +
            '<"col-sm-12 col-md-6 d-flex justify-content-end"p>' +
            '>',
        "buttons": [
            {
                text: '<i class="fas fa-user-plus"></i> Add User',
                className: 'btn btn-success btn-sm',
                action: function() {
                    $('#createUserOffcanvas').offcanvas('show');
                }
            },
            {
                extend: 'csv',
                text: '<i class="fas fa-file-csv"></i> Export CSV',
                className: 'btn btn-primary btn-sm'
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> Export PDF',
                className: 'btn btn-primary btn-sm'
            },
            {
                extend: 'colvis',
                text: '<i class="fas fa-columns"></i> Columns',
                className: 'btn btn-primary btn-sm'
            }
        ],
        "language": {
            url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/de-DE.json'
        }
    });

    // Edit user functionality
    $('.edit-user-btn').on('click', function() {
        var id = $(this).data('id');
        var username = $(this).data('username');
        var email = $(this).data('email');
        var role = $(this).data('role');
        var is_active = $(this).data('active');
        var code = $(this).data('code');
        
        $('#edit_id').val(id);
        $('#edit_username').val(username);
        $('#edit_email').val(email);
        $('#edit_role').val(role);
        $('#edit_is_active').prop('checked', is_active == 1);
        
        if (role === 'waiter') {
            $('#edit_code').val(code);
            $('#waiter_code_section').show();
        } else {
            $('#edit_code').val('');
            $('#waiter_code_section').hide();
        }
        
        $('#editForm').attr('action', 'users.php?action=edit&id=' + id);
    });

    // Delete user functionality
    $('.delete-user-btn').on('click', function() {
        var id = $(this).data('id');
        var username = $(this).data('username');
        
        Swal.fire({
            title: 'Are you sure?',
            text: `Do you want to delete user "${username}"? This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                var form = $('<form>', {
                    method: 'POST',
                    action: 'users.php?action=delete&id=' + id
                });
                $('body').append(form);
                form.submit();
            }
        });
    });

    // Toggle status functionality
    $('.toggle-status-btn').on('click', function() {
        var id = $(this).data('id');
        var username = $(this).data('username');
        var status = $(this).data('status');
        
        Swal.fire({
            title: 'Are you sure?',
            text: `Do you want to ${status} user "${username}"?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, do it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'users.php?action=toggle_status&id=' + id;
            }
        });
    });

    // Role change in edit form to show/hide waiter code
    $('#edit_role').on('change', function() {
        var selectedRole = $(this).val();
        if (selectedRole === 'waiter') {
            $('#waiter_code_section').show();
        } else {
            $('#waiter_code_section').hide();
            $('#edit_code').val('');
        }
    });

    // Show toast notifications
    <?php if (isset($_SESSION['toast'])): ?>
        var toastHtml = `
            <div class="toast align-items-center text-white bg-<?= $_SESSION['toast']['type'] === 'success' ? 'success' : 'danger' ?> border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-<?= $_SESSION['toast']['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
                        <?= $_SESSION['toast']['message'] ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        
        // Create toast container if it doesn't exist
        if ($('#toast-container').length === 0) {
            $('body').append('<div id="toast-container" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100;"></div>');
        }
        
        $('#toast-container').html(toastHtml);
        $('.toast').toast({
            delay: 5000,
            autohide: true
        }).toast('show');
        
        <?php unset($_SESSION['toast']); ?>
    <?php endif; ?>

    // Form validation
    $('form').on('submit', function() {
        var username = $(this).find('input[name="username"]').val().trim();
        var email = $(this).find('input[name="email"]').val().trim();
        var role = $(this).find('select[name="role"]').val();
        var password = $(this).find('input[name="password"]').val();
        
        if (!username) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please enter a username.'
            });
            return false;
        }
        
        if (!email) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please enter an email address.'
            });
            return false;
        }
        
        if (!role) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please select a role.'
            });
            return false;
        }
        
        // Check password only for create form
        if ($(this).attr('action').includes('action=create') && !password) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please enter a password.'
            });
            return false;
        }
        
        if (password && password.length < 6) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Password must be at least 6 characters long.'
            });
            return false;
        }
    });
});
</script>