<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

$action = $_REQUEST['action'] ?? 'view';
$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$message = '';

function sanitizeInput($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'Unbekannter Fehler'];

    if ($_POST['ajax_action'] === 'update_status') {
        $id = (int)($_POST['id'] ?? 0);
        $is_active = (int)($_POST['is_active'] ?? 0);

        if ($id > 0) {
            try {
                $pdo->beginTransaction();
                $current_time = date('Y-m-d H:i:s');
                $status_text = $is_active ? 'aktiviert' : 'deaktiviert';
                $stmt = $pdo->prepare('UPDATE drinks SET is_active = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$is_active, $id]);
                $pdo->commit();
                $response = ['status' => 'success', 'message' => "Getränk erfolgreich $status_text."];
            } catch (PDOException $e) {
                $pdo->rollBack();
                $response = ['status' => 'error', 'message' => 'Datenbankfehler: ' . sanitizeInput($e->getMessage())];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'Ungültige Getränk-ID'];
        }
    } elseif ($_POST['ajax_action'] === 'update_position') {
        $positions = $_POST['positions'] ?? [];
        
        if (!empty($positions)) {
            try {
                $pdo->beginTransaction();
                foreach ($positions as $position => $drink_id) {
                    $stmt = $pdo->prepare('UPDATE drinks SET position = ? WHERE id = ?');
                    $stmt->execute([$position + 1, $drink_id]);
                }
                $pdo->commit();
                $response = ['status' => 'success', 'message' => 'Reihenfolge erfolgreich aktualisiert.'];
            } catch (PDOException $e) {
                $pdo->rollBack();
                $response = ['status' => 'error', 'message' => 'Datenbankfehler: ' . sanitizeInput($e->getMessage())];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'Keine Positionen übermittelt'];
        }
    }

    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $name = sanitizeInput($_POST['name'] ?? '');
        $price = sanitizeInput($_POST['price'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '' || $price === '') {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Name und Preis sind erforderlich.'];
            header("Location: drinks.php");
            exit();
        } elseif (!is_numeric($price) || floatval($price) < 0) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Der Preis muss eine gültige nicht-negative Zahl sein.'];
            header("Location: drinks.php");
            exit();
        } else {
            try {
                // Get the next position
                $stmt = $pdo->query('SELECT MAX(position) AS max_position FROM drinks');
                $result = $stmt->fetch();
                $position = $result['max_position'] !== null ? $result['max_position'] + 1 : 1;
                
                $stmt = $pdo->prepare("INSERT INTO `drinks` (`name`, `price`, `is_active`, `position`) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $price, $is_active, $position]);
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Getränk erfolgreich hinzugefügt.'];
                header("Location: drinks.php");
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Der Getränkename existiert bereits.'];
                } else {
                    $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler: ' . sanitizeInput($e->getMessage())];
                }
                header("Location: drinks.php");
                exit();
            }
        }
    } elseif ($action === 'edit' && $id > 0) {
        $name = sanitizeInput($_POST['name'] ?? '');
        $price = sanitizeInput($_POST['price'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '' || $price === '') {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Name und Preis sind erforderlich.'];
            header("Location: drinks.php");
            exit();
        } elseif (!is_numeric($price) || floatval($price) < 0) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Der Preis muss eine gültige nicht-negative Zahl sein.'];
            header("Location: drinks.php");
            exit();
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE `drinks` SET `name` = ?, `price` = ?, `is_active` = ?, `updated_at` = NOW() WHERE `id` = ?");
                $stmt->execute([$name, $price, $is_active, $id]);
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Getränk erfolgreich aktualisiert.'];
                header("Location: drinks.php");
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Der Getränkename existiert bereits.'];
                } else {
                    $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler: ' . sanitizeInput($e->getMessage())];
                }
                header("Location: drinks.php");
                exit();
            }
        }
    }
} elseif ($action === 'delete' && $id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM `drinks` WHERE `id` = ?");
        $stmt->execute([$id]);
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Getränk erfolgreich gelöscht.'];
        header("Location: drinks.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler: ' . sanitizeInput($e->getMessage())];
        header("Location: drinks.php");
        exit();
    }
}

if ($action === 'view') {
    try {
        $stmt = $pdo->query("SELECT * FROM `drinks` ORDER BY `position` ASC, `created_at` DESC");
        $drinks = $stmt->fetchAll();
    } catch (PDOException $e) {
        $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler beim Abrufen der Getränke: ' . sanitizeInput($e->getMessage())];
    }
}

// Get drink data for editing
if ($action === 'edit' && $id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM `drinks` WHERE `id` = ?");
        $stmt->execute([$id]);
        $drink = $stmt->fetch();
        if (!$drink) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Drink not found.'];
            header("Location: drinks.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Error: ' . sanitizeInput($e->getMessage())];
        header("Location: drinks.php");
        exit();
    }
}
?>

<style>
    /* CSS Variables for consistent theming */
    :root {
        --primary-color: #3b82f6;
        --primary-hover: #2563eb;
        --success-color: #10b981;
        --success-hover: #059669;
        --danger-color: #ef4444;
        --danger-hover: #dc2626;
        --warning-color: #f59e0b;
        --warning-hover: #d97706;
        --border-color: #e5e7eb;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }

    /* Main Content */
    .drinks-content {
        padding: 2rem;
        background: #f8fafc;
        min-height: calc(100vh - 80px);
    }

    /* Page Header */
    .page-header {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        margin-bottom: 2rem;
        overflow: hidden;
    }

    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 2rem;
    }

    .header-text {
        flex: 1;
    }

    .page-title {
        font-size: 1.875rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 0.5rem 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .page-subtitle {
        color: #64748b;
        margin: 0;
        font-size: 0.875rem;
    }

    .header-actions {
        display: flex;
        gap: 1rem;
    }

    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.875rem;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: all 0.15s ease;
        box-shadow: var(--shadow-sm);
    }

    .btn-primary {
        background: var(--primary-color);
        color: white;
    }

    .btn-primary:hover {
        background: var(--primary-hover);
        color: white;
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-secondary {
        background: #6b7280;
        color: white;
    }

    .btn-secondary:hover {
        background: #4b5563;
        color: white;
        text-decoration: none;
    }

    .btn-success {
        background: var(--success-color);
        color: white;
    }

    .btn-success:hover {
        background: var(--success-hover);
        color: white;
    }

    .btn-danger {
        background: var(--danger-color);
        color: white;
    }

    .btn-danger:hover {
        background: var(--danger-hover);
        color: white;
    }

    .btn-sm {
        padding: 0.5rem;
        font-size: 0.75rem;
    }

    /* Alert Container */
    .alert-container {
        margin-bottom: 2rem;
    }

    .alert {
        padding: 1rem 1.5rem;
        border-radius: 8px;
        border: 1px solid;
        margin-bottom: 1rem;
    }

    .alert-success {
        background: #d1fae5;
        border-color: var(--success-color);
        color: #065f46;
    }

    .alert-danger {
        background: #fee2e2;
        border-color: var(--danger-color);
        color: #991b1b;
    }

    /* Table Section */
    .table-section {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        overflow: hidden;
    }

    .table-container {
        overflow-x: auto;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th {
        background: #f8fafc;
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: #374151;
        border-bottom: 1px solid var(--border-color);
        font-size: 0.875rem;
    }

    .data-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--border-color);
        color: #374151;
        vertical-align: middle;
    }

    .data-table tbody tr:hover {
        background: #f8fafc;
    }

    /* Table Cells */
    .drink-id {
        font-family: 'Courier New', monospace;
        font-weight: 600;
        color: #64748b;
    }

    .drink-name {
        font-weight: 600;
        color: #0f172a;
    }

    .drink-price {
        font-weight: 600;
        color: var(--success-color);
        font-family: 'Courier New', monospace;
    }

    .drink-date {
        color: #64748b;
        font-size: 0.875rem;
    }

    /* Status Badges */
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    .status-active {
        background: #d1fae5;
        color: #065f46;
    }

    .status-inactive {
        background: #fee2e2;
        color: #991b1b;
    }

    /* Action Buttons */
    .actions-cell {
        text-align: center;
    }

    .action-buttons {
        display: flex;
        gap: 0.5rem;
        justify-content: center;
    }

    .btn-edit {
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 0.5rem;
        border-radius: 6px;
        transition: all 0.15s ease;
    }

    .btn-edit:hover {
        background: var(--primary-hover);
        color: white;
    }

    .btn-delete {
        background: var(--danger-color);
        color: white;
        border: none;
        padding: 0.5rem;
        border-radius: 6px;
        transition: all 0.15s ease;
    }

    .btn-delete:hover {
        background: var(--danger-hover);
        color: white;
    }

    .btn-toggle {
        background: var(--success-color);
        color: white;
        border: none;
        padding: 0.5rem;
        border-radius: 6px;
        transition: all 0.15s ease;
    }

    .btn-toggle:hover {
        background: var(--success-hover);
        color: white;
    }

    .btn-toggle.inactive {
        background: #6b7280;
    }

    .btn-toggle.inactive:hover {
        background: #4b5563;
    }

    .sort-handle {
        cursor: move;
        color: #9ca3af;
        text-align: center;
    }

    .sort-handle:hover {
        color: #6b7280;
    }

    /* Empty State */
    .no-data {
        text-align: center;
        padding: 3rem 1rem;
    }

    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
    }

    .empty-state i {
        font-size: 3rem;
        color: #9ca3af;
    }

    .empty-state h3 {
        color: #374151;
        margin: 0;
        font-size: 1.25rem;
    }

    .empty-state p {
        color: #64748b;
        margin: 0;
    }

    /* Form Section */
    .form-section {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    /* Form Cards */
    .form-card {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        overflow: hidden;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        background: #f8fafc;
    }

    .card-header h3 {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 600;
        color: #0f172a;
    }

    .card-header i {
        color: #64748b;
        font-size: 1.25rem;
    }

    .card-content {
        padding: 1.5rem;
    }

    /* Form Groups */
    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group:last-child {
        margin-bottom: 0;
    }

    .form-label {
        display: block;
        font-weight: 500;
        color: #374151;
        font-size: 0.875rem;
        margin-bottom: 0.5rem;
    }

    .required {
        color: var(--danger-color);
    }

    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        font-size: 0.875rem;
        transition: all 0.15s ease;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-text {
        font-size: 0.75rem;
        color: #64748b;
        margin-top: 0.25rem;
    }

    /* Checkbox */
    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
    }

    .checkbox-item input {
        margin: 0;
    }

    .checkbox-label {
        font-size: 0.875rem;
        color: #374151;
    }

    /* Form Actions */
    .form-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        padding: 1.5rem;
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .drinks-content {
            padding: 1rem;
        }

        .header-content {
            flex-direction: column;
            gap: 1rem;
            text-align: center;
        }

        .page-title {
            font-size: 1.5rem;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .action-buttons {
            flex-direction: column;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem 0.5rem;
        }
    }
</style>

<!-- Drinks Content -->
<div class="drinks-content">
    <?php if ($action === 'view'): ?>
        <!-- Drinks List View -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-text">
                    <h1 class="page-title">
                        <i class="fas fa-wine-glass-alt"></i>
                        Drinks Management
                    </h1>
                    <p class="page-subtitle">Manage your beverage inventory and pricing</p>
                </div>
                <div class="header-actions">
                    <a href="drinks.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Drink
                    </a>
                </div>
            </div>
        </div>

        <!-- Drinks Table -->
        <div class="table-section">
            <div class="table-container">
                <table id="drinksTable" class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Price (€)</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th width="150">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($drinks)): ?>
                            <?php foreach ($drinks as $drink): ?>
                                <tr data-id="<?= sanitizeInput($drink['id']) ?>">
                                    <td>
                                        <span class="drink-id">#<?= $drink['id'] ?></span>
                                    </td>
                                    <td>
                                        <span class="drink-name"><?= sanitizeInput($drink['name']) ?></span>
                                    </td>
                                    <td>
                                        <span class="drink-price">€<?= number_format($drink['price'], 2) ?></span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= ($drink['is_active'] ?? 1) ? 'active' : 'inactive' ?>">
                                            <?= ($drink['is_active'] ?? 1) ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="drink-date"><?= date('M j, Y', strtotime($drink['created_at'])) ?></span>
                                    </td>
                                    <td class="actions-cell">
                                        <div class="action-buttons">
                                            <a href="drinks.php?action=edit&id=<?= $drink['id'] ?>" class="btn btn-sm btn-edit" title="Edit Drink">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-toggle <?= ($drink['is_active'] ?? 1) ? '' : 'inactive' ?>" 
                                                    title="<?= ($drink['is_active'] ?? 1) ? 'Deactivate' : 'Activate' ?> Drink" 
                                                    data-id="<?= $drink['id'] ?>" 
                                                    data-status="<?= ($drink['is_active'] ?? 1) ?>">
                                                <i class="fas fa-<?= ($drink['is_active'] ?? 1) ? 'eye-slash' : 'eye' ?>"></i>
                                            </button>
                                            <button class="btn btn-sm btn-delete delete-drink-btn" 
                                                    title="Delete Drink" 
                                                    data-id="<?= $drink['id'] ?>" 
                                                    data-name="<?= sanitizeInput($drink['name']) ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="no-data">
                                    <div class="empty-state">
                                        <i class="fas fa-wine-glass-alt"></i>
                                        <h3>No Drinks Found</h3>
                                        <p>Start by adding your first drink to the menu.</p>
                                        <a href="drinks.php?action=add" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Add Drink
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <!-- Add/Edit Drink Form -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-text">
                    <h1 class="page-title">
                        <i class="fas fa-<?= $action === 'edit' ? 'edit' : 'plus' ?>"></i>
                        <?= $action === 'edit' ? 'Edit Drink' : 'Add New Drink' ?>
                    </h1>
                    <p class="page-subtitle">
                        <?= $action === 'edit' ? 'Update drink information' : 'Create a new drink for your menu' ?>
                    </p>
                </div>
                <div class="header-actions">
                    <a href="drinks.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Drinks
                    </a>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert-container">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="form-section">
            <form method="POST" action="drinks.php?action=<?= $action ?><?= $action === 'edit' ? '&id=' . $id : '' ?>" class="drink-form">
                <div class="form-grid">
                    <!-- Basic Information -->
                    <div class="form-card">
                        <div class="card-header">
                            <h3>Basic Information</h3>
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="card-content">
                            <div class="form-group">
                                <label for="name" class="form-label">
                                    Drink Name <span class="required">*</span>
                                </label>
                                <input type="text" id="name" name="name" class="form-control" 
                                       value="<?= $action === 'edit' ? sanitizeInput($drink['name']) : '' ?>" 
                                       placeholder="Enter drink name" required>
                                <div class="form-text">Enter the name of the drink (e.g., Coca Cola, Sprite)</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="price" class="form-label">
                                    Price (€) <span class="required">*</span>
                                </label>
                                <input type="number" step="0.01" min="0" id="price" name="price" class="form-control" 
                                       value="<?= $action === 'edit' ? sanitizeInput($drink['price']) : '' ?>" 
                                       placeholder="0.00" required>
                                <div class="form-text">Enter the price in euros (e.g., 2.50)</div>
                            </div>

                            <div class="form-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" id="is_active" name="is_active" 
                                           <?= ($action === 'edit' ? ($drink['is_active'] ?? 1) : 1) ? 'checked' : '' ?>>
                                    <label for="is_active" class="checkbox-label">Active</label>
                                </div>
                                <div class="form-text">Enable or disable this drink in the menu</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i>
                        <?= $action === 'edit' ? 'Update Drink' : 'Save Drink' ?>
                    </button>
                    <a href="drinks.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
?>

<script>
$(document).ready(function() {
    // Initialize DataTable with simplified configuration
    if ($('#drinksTable').length) {
        var table = $('#drinksTable').DataTable({
            "paging": true,
            "searching": true,
            "info": true,
            "order": [[4, "desc"]], // Sort by Created column (index 4)
            "pageLength": 25,
            "columnDefs": [
                { "orderable": false, "targets": [5] }, // Disable sorting for Actions column
                { "searchable": false, "targets": [5] }  // Disable searching for Actions column
            ],
            "language": {
                url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/de-DE.json'
            }
        });
    }

    // Status toggle functionality
    $('.btn-toggle').on('click', function() {
        var button = $(this);
        var id = button.data('id');
        var currentStatus = button.data('status');
        var newStatus = currentStatus ? 0 : 1;
        var statusText = newStatus ? 'activate' : 'deactivate';

        $.ajax({
            url: 'drinks.php',
            type: 'POST',
            data: {
                ajax_action: 'update_status',
                id: id,
                is_active: newStatus
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Update button appearance
                    button.data('status', newStatus);
                    if (newStatus) {
                        button.removeClass('inactive').addClass('btn-toggle');
                        button.find('i').removeClass('fa-eye').addClass('fa-eye-slash');
                        button.attr('title', 'Deactivate Drink');
                        button.closest('tr').find('.status-badge')
                            .removeClass('status-inactive')
                            .addClass('status-active')
                            .text('Active');
                    } else {
                        button.addClass('inactive').removeClass('btn-toggle');
                        button.find('i').removeClass('fa-eye-slash').addClass('fa-eye');
                        button.attr('title', 'Activate Drink');
                        button.closest('tr').find('.status-badge')
                            .removeClass('status-active')
                            .addClass('status-inactive')
                            .text('Inactive');
                    }

                    // Show success toast
                    showToast('success', response.message);
                } else {
                    showToast('danger', response.message);
                }
            },
            error: function() {
                showToast('danger', 'An error occurred while updating the status.');
            }
        });
    });

    // Drag and drop sorting (disabled for now to avoid conflicts with DataTables)
    // $("#sortable").sortable({
    //     handle: ".sort-handle",
    //     axis: "y",
    //     opacity: 0.8,
    //     helper: function(e, tr) {
    //         var $originals = tr.children();
    //         var $helper = tr.clone();
    //         $helper.children().each(function(index) {
    //             $(this).width($originals.eq(index).width());
    //         });
    //         return $helper;
    //     },
    //     update: function(event, ui) {
    //         var positions = [];
    //         $("#sortable tr").each(function(index) {
    //                 positions.push($(this).data('id'));
    //             });
    // 
    //         $.ajax({
    //             url: 'drinks.php',
    //             type: 'POST',
    //             data: {
    //                 ajax_action: 'update_position',
    //                 positions: positions
    //             },
    //             dataType: 'json',
    //             success: function(response) {
    //                 if (response.status === 'success') {
    //                     showToast('success', response.message);
    //                 } else {
    //                     showToast('danger', response.message);
    //                 }
    //             },
    //             error: function() {
    //                 showToast('danger', 'An error occurred while updating the order.');
    //             }
    //         });
    //     }
    // });

    // Delete drink functionality
    $('.delete-drink-btn').on('click', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        
        Swal.fire({
            title: 'Are you sure?',
            text: `Do you want to delete "${name}"? This action cannot be undone.`,
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
                    action: 'drinks.php?action=delete'
                });
                form.append($('<input>', {
                    type: 'hidden',
                    name: 'id',
                    value: id
                }));
                $('body').append(form);
                form.submit();
            }
        });
    });

    // Show toast notifications
    <?php if (isset($_SESSION['toast'])): ?>
        showToast('<?= $_SESSION['toast']['type'] ?>', '<?= $_SESSION['toast']['message'] ?>');
        <?php unset($_SESSION['toast']); ?>
    <?php endif; ?>

    // Form validation
    $('.drink-form').on('submit', function() {
        var name = $(this).find('input[name="name"]').val().trim();
        var price = $(this).find('input[name="price"]').val();
        
        if (!name) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please enter a drink name.'
            });
            return false;
        }
        
        if (!price || isNaN(price) || parseFloat(price) < 0) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please enter a valid price (0 or greater).'
            });
            return false;
        }
    });

    // Toast notification function
    function showToast(type, message) {
        var toastHtml = `
            <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                        ${message}
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
    }
});
</script>