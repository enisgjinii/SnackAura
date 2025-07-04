<?php
ob_start();
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `coupons` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(50) NOT NULL,
            `discount_type` ENUM('percentage','fixed') NOT NULL,
            `discount_value` DECIMAL(10,2) NOT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `start_date` DATE NULL,
            `end_date` DATE NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Tabelle konnte nicht erstellt werden: ' . htmlspecialchars($e->getMessage())];
    header('Location: cupons.php?action=list');
    exit();
}

$action = $_REQUEST['action'] ?? 'list';
$id     = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$message = '';

function sanitizeInput($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validateCoupon($pdo, $data, $id = 0)
{
    $errors = [];
    $code = sanitizeInput($data['code'] ?? '');
    $type = sanitizeInput($data['discount_type'] ?? '');
    $val  = (float)($data['discount_value'] ?? 0);
    if (!$code || !$type || !$val) {
        $errors[] = 'Code, Typ und Wert sind erforderlich.';
    }
    $sql = "SELECT COUNT(*) FROM coupons WHERE code=?";
    $params = [$code];
    if ($id > 0) {
        $sql .= " AND id!=?";
        $params[] = $id;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'Der Coupon-Code existiert bereits.';
    }
    return [$errors, compact('code', 'type', 'val')];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        list($errors, $data) = validateCoupon($pdo, $_POST);
        $act = isset($_POST['is_active']) ? 1 : 0;
        $start = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end  = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        if ($errors) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => implode('<br>', $errors)];
            header("Location: cupons.php?action=list");
            exit;
        } else {
            $sql = "INSERT INTO coupons (code, discount_type, discount_value, is_active, start_date, end_date, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            try {
                $stmt->execute([
                    $data['code'],
                    $data['type'],
                    $data['val'],
                    $act,
                    $start,
                    $end
                ]);
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Coupon erfolgreich erstellt.'];
                header("Location: cupons.php?action=list");
                exit;
            } catch (PDOException $e) {
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler beim Erstellen des Coupons.'];
                header("Location: cupons.php?action=list");
                exit;
            }
        }
    } elseif ($action === 'edit' && $id > 0) {
        list($errors, $data) = validateCoupon($pdo, $_POST, $id);
        $act = isset($_POST['is_active']) ? 1 : 0;
        $start = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end  = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        if ($errors) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => implode('<br>', $errors)];
            header("Location: cupons.php?action=list");
            exit;
        } else {
            $sql = "UPDATE coupons SET code=?, discount_type=?, discount_value=?, is_active=?, start_date=?, end_date=?, updated_at=NOW() WHERE id=?";
            $stmt = $pdo->prepare($sql);
            try {
                $stmt->execute([
                    $data['code'],
                    $data['type'],
                    $data['val'],
                    $act,
                    $start,
                    $end,
                    $id
                ]);
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Coupon erfolgreich aktualisiert.'];
                header("Location: cupons.php?action=list");
                exit;
            } catch (PDOException $e) {
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler beim Aktualisieren des Coupons.'];
                header("Location: cupons.php?action=list");
                exit;
            }
        }
    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $pdo->prepare("DELETE FROM coupons WHERE id=?");
        try {
            $stmt->execute([$id]);
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Coupon erfolgreich gelöscht.'];
            header("Location: cupons.php?action=list");
            exit;
        } catch (PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler beim Löschen des Coupons.'];
            header("Location: cupons.php?action=list");
            exit;
        }
    }
}

if ($action === 'list') {
    $msg = $_GET['message'] ?? '';
    try {
        $stmt = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC");
        $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler beim Abrufen der Coupons.'];
        $coupons = [];
    }
}
?>

<style>
/* Coupons Page Styles */
.coupons-container {
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

.add-coupon-btn {
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

.add-coupon-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 12px rgba(16, 185, 129, 0.3);
    color: white;
}

.coupons-table-section {
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

.coupon-count {
    background: #f1f5f9;
    color: #64748b;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
}

.coupons-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.coupons-table th {
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

.coupons-table td {
    padding: 1rem;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    font-size: 0.875rem;
}

.coupons-table tr:hover {
    background: #f8fafc;
}

.coupon-id {
    font-weight: 600;
    color: #64748b;
    font-family: 'Monaco', 'Menlo', monospace;
}

.coupon-code {
    font-weight: 700;
    color: #1e293b;
    font-family: 'Monaco', 'Menlo', monospace;
    background: #f1f5f9;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
}

.coupon-type {
    font-weight: 600;
    color: #059669;
}

.coupon-value {
    font-weight: 600;
    color: #dc2626;
    font-family: 'Monaco', 'Menlo', monospace;
}

.coupon-status {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}

.coupon-status.active {
    background: #dcfce7;
    color: #166534;
}

.coupon-status.inactive {
    background: #f3f4f6;
    color: #6b7280;
}

.coupon-date {
    color: #64748b;
    font-size: 0.75rem;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-edit-coupon {
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

.btn-edit-coupon:hover {
    background: #d97706;
    color: white;
    transform: translateY(-1px);
}

.btn-delete-coupon {
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

.btn-delete-coupon:hover {
    background: #dc2626;
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
    .coupons-container {
        padding: 1rem;
    }
    
    .page-header {
        padding: 1.5rem;
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .coupons-table-section {
        padding: 1rem;
    }
    
    .coupons-table {
        font-size: 0.75rem;
    }
    
    .coupons-table th,
    .coupons-table td {
        padding: 0.75rem 0.5rem;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 0.25rem;
    }
}
</style>

<?php if ($action === 'list'): ?>
    <div class="coupons-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-ticket-alt"></i>
                        Coupons Management
                    </h1>
                    <p class="page-subtitle">Manage discount codes and promotional offers</p>
                </div>
                <button class="add-coupon-btn" data-bs-toggle="offcanvas" data-bs-target="#createCouponOffcanvas">
                    <i class="fas fa-plus"></i>
                    Add New Coupon
                </button>
            </div>
        </div>

        <!-- Coupons Table Section -->
        <div class="coupons-table-section">
            <div class="table-header">
                <div class="d-flex align-items-center gap-3">
                    <h3 class="table-title">All Coupons</h3>
                    <span class="coupon-count"><?= count($coupons ?? []) ?> coupons</span>
                </div>
            </div>
            
            <?php if (!empty($coupons)): ?>
                <div class="table-responsive">
                    <table id="couponsTable" class="coupons-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Value</th>
                                <th>Status</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Created</th>
                                <th width="100">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coupons as $c): ?>
                                <tr>
                                    <td class="coupon-id">#<?= $c['id'] ?></td>
                                    <td class="coupon-code"><?= sanitizeInput($c['code']) ?></td>
                                    <td class="coupon-type">
                                        <?= sanitizeInput($c['discount_type'] === 'percentage' ? 'Percentage' : 'Fixed') ?>
                                    </td>
                                    <td class="coupon-value">
                                        <?= $c['discount_type'] === 'percentage' ? sanitizeInput($c['discount_value']) . '%' : '€' . sanitizeInput($c['discount_value']) ?>
                                    </td>
                                    <td>
                                        <span class="coupon-status <?= ($c['is_active'] ? 'active' : 'inactive') ?>">
                                            <i class="fas fa-<?= ($c['is_active'] ? 'check-circle' : 'times-circle') ?>"></i>
                                            <?= ($c['is_active'] ? 'Active' : 'Inactive') ?>
                                        </span>
                                    </td>
                                    <td class="coupon-date">
                                        <?= $c['start_date'] ? date('M j, Y', strtotime($c['start_date'])) : 'Not set' ?>
                                    </td>
                                    <td class="coupon-date">
                                        <?= $c['end_date'] ? date('M j, Y', strtotime($c['end_date'])) : 'Not set' ?>
                                    </td>
                                    <td class="coupon-date">
                                        <?= date('M j, Y', strtotime($c['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-edit-coupon edit-coupon-btn"
                                                data-id="<?= $c['id'] ?>"
                                                data-code="<?= sanitizeInput($c['code']) ?>"
                                                data-type="<?= sanitizeInput($c['discount_type']) ?>"
                                                data-value="<?= sanitizeInput($c['discount_value']) ?>"
                                                data-active="<?= $c['is_active'] ?>"
                                                data-start="<?= sanitizeInput($c['start_date']) ?>"
                                                data-end="<?= sanitizeInput($c['end_date']) ?>"
                                                data-bs-toggle="offcanvas"
                                                data-bs-target="#editCouponOffcanvas"
                                                title="Edit Coupon">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-delete-coupon delete-coupon-btn"
                                                data-id="<?= $c['id'] ?>"
                                                data-code="<?= sanitizeInput($c['code']) ?>"
                                                title="Delete Coupon">
                                                <i class="fas fa-trash"></i>
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
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <h3 class="empty-state-title">No Coupons Found</h3>
                    <p class="empty-state-text">Start by creating your first discount coupon.</p>
                    <button class="add-coupon-btn" data-bs-toggle="offcanvas" data-bs-target="#createCouponOffcanvas">
                        <i class="fas fa-plus"></i>
                        Add First Coupon
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Coupon Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="createCouponOffcanvas" aria-labelledby="createCouponOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="createCouponOffcanvasLabel">
                <i class="fas fa-plus me-2"></i>
                Add New Coupon
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="cupons.php?action=create">
                <div class="form-group">
                    <label for="create_code" class="form-label">
                        Coupon Code <span class="required">*</span>
                    </label>
                    <input type="text" name="code" id="create_code" class="form-control" 
                           placeholder="Enter coupon code" required>
                </div>
                <div class="form-group">
                    <label for="create_discount_type" class="form-label">
                        Discount Type <span class="required">*</span>
                    </label>
                    <select name="discount_type" id="create_discount_type" class="form-select" required>
                        <option value="">Select type</option>
                        <option value="percentage">Percentage</option>
                        <option value="fixed">Fixed Amount</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="create_discount_value" class="form-label">
                        Discount Value <span class="required">*</span>
                    </label>
                    <input type="number" step="0.01" name="discount_value" id="create_discount_value" 
                           class="form-control" placeholder="0.00" required>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="is_active" id="create_is_active" class="form-check-input" value="1" checked>
                    <label class="form-check-label" for="create_is_active">Active</label>
                </div>
                <div class="form-group">
                    <label for="create_start_date" class="form-label">Start Date</label>
                    <input type="date" name="start_date" id="create_start_date" class="form-control">
                </div>
                <div class="form-group">
                    <label for="create_end_date" class="form-label">End Date</label>
                    <input type="date" name="end_date" id="create_end_date" class="form-control">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i>
                        Save Coupon
                    </button>
                    <button type="button" class="btn-cancel" data-bs-dismiss="offcanvas">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Coupon Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="editCouponOffcanvas" aria-labelledby="editCouponOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="editCouponOffcanvasLabel">
                <i class="fas fa-edit me-2"></i>
                Edit Coupon
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" id="editForm" action="cupons.php?action=edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label for="edit_code" class="form-label">
                        Coupon Code <span class="required">*</span>
                    </label>
                    <input type="text" name="code" id="edit_code" class="form-control" 
                           placeholder="Enter coupon code" required>
                </div>
                <div class="form-group">
                    <label for="edit_discount_type" class="form-label">
                        Discount Type <span class="required">*</span>
                    </label>
                    <select name="discount_type" id="edit_discount_type" class="form-select" required>
                        <option value="">Select type</option>
                        <option value="percentage">Percentage</option>
                        <option value="fixed">Fixed Amount</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_discount_value" class="form-label">
                        Discount Value <span class="required">*</span>
                    </label>
                    <input type="number" step="0.01" name="discount_value" id="edit_discount_value" 
                           class="form-control" placeholder="0.00" required>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="is_active" id="edit_is_active" class="form-check-input" value="1">
                    <label class="form-check-label" for="edit_is_active">Active</label>
                </div>
                <div class="form-group">
                    <label for="edit_start_date" class="form-label">Start Date</label>
                    <input type="date" name="start_date" id="edit_start_date" class="form-control">
                </div>
                <div class="form-group">
                    <label for="edit_end_date" class="form-label">End Date</label>
                    <input type="date" name="end_date" id="edit_end_date" class="form-control">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i>
                        Update Coupon
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

<?php if ($action === 'create' || ($action === 'edit' && $id > 0)): ?>
    <?php
    if ($action === 'edit') {
        try {
            $stmt = $pdo->prepare("SELECT * FROM `coupons` WHERE `id` = ?");
            $stmt->execute([$id]);
            $coupon = $stmt->fetch();
            if (!$coupon) {
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Coupon not found.'];
                header("Location: cupons.php?action=list");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Error: ' . sanitizeInput($e->getMessage())];
            header("Location: cupons.php?action=list");
            exit();
        }
    }
    ?>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
ob_end_flush();
?>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#couponsTable').DataTable({
        "paging": true,
        "searching": true,
        "info": true,
        "order": [[7, "desc"]],
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
                text: '<i class="fas fa-plus"></i> Add Coupon',
                className: 'btn btn-success btn-sm',
                action: function() {
                    $('#createCouponOffcanvas').offcanvas('show');
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

    // Edit coupon functionality
    $('.edit-coupon-btn').on('click', function() {
        var id = $(this).data('id');
        var code = $(this).data('code');
        var type = $(this).data('type');
        var value = $(this).data('value');
        var active = $(this).data('active');
        var start = $(this).data('start');
        var end = $(this).data('end');
        
        $('#edit_id').val(id);
        $('#edit_code').val(code);
        $('#edit_discount_type').val(type);
        $('#edit_discount_value').val(value);
        $('#edit_is_active').prop('checked', active == 1);
        $('#edit_start_date').val(start);
        $('#edit_end_date').val(end);
        $('#editForm').attr('action', 'cupons.php?action=edit&id=' + id);
    });

    // Delete coupon functionality
    $('.delete-coupon-btn').on('click', function() {
        var id = $(this).data('id');
        var code = $(this).data('code');
        
        Swal.fire({
            title: 'Are you sure?',
            text: `Do you want to delete coupon "${code}"? This action cannot be undone.`,
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
                    action: 'cupons.php?action=delete&id=' + id
                });
                $('body').append(form);
                form.submit();
            }
        });
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
        var code = $(this).find('input[name="code"]').val().trim();
        var type = $(this).find('select[name="discount_type"]').val();
        var value = $(this).find('input[name="discount_value"]').val();
        
        if (!code) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please enter a coupon code.'
            });
            return false;
        }
        
        if (!type) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please select a discount type.'
            });
            return false;
        }
        
        if (!value || isNaN(value) || parseFloat(value) < 0) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please enter a valid discount value (0 or greater).'
            });
            return false;
        }
    });
});
</script>