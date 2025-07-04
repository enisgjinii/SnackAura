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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $name = sanitizeInput($_POST['name'] ?? '');
        $price = sanitizeInput($_POST['price'] ?? '');

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
                $stmt = $pdo->prepare("INSERT INTO `drinks` (`name`, `price`) VALUES (?, ?)");
                $stmt->execute([$name, $price]);
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
                $stmt = $pdo->prepare("UPDATE `drinks` SET `name` = ?, `price` = ? WHERE `id` = ?");
                $stmt->execute([$name, $price, $id]);
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
        $stmt = $pdo->query("SELECT * FROM `drinks` ORDER BY `created_at` DESC");
        $drinks = $stmt->fetchAll();
    } catch (PDOException $e) {
        $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler beim Abrufen der Getränke: ' . sanitizeInput($e->getMessage())];
    }
}
?>

<style>
/* Drinks Page Styles */
.drinks-container {
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

.add-drink-btn {
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

.add-drink-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 12px rgba(16, 185, 129, 0.3);
    color: white;
}

.drinks-table-section {
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

.drink-count {
    background: #f1f5f9;
    color: #64748b;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
}

.drinks-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.drinks-table th {
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

.drinks-table td {
    padding: 1rem;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    font-size: 0.875rem;
}

.drinks-table tr:hover {
    background: #f8fafc;
}

.drink-id {
    font-weight: 600;
    color: #64748b;
    font-family: 'Monaco', 'Menlo', monospace;
}

.drink-name {
    font-weight: 600;
    color: #1e293b;
}

.drink-price {
    font-weight: 600;
    color: #059669;
    font-family: 'Monaco', 'Menlo', monospace;
}

.drink-date {
    color: #64748b;
    font-size: 0.75rem;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-edit-drink {
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

.btn-edit-drink:hover {
    background: #d97706;
    color: white;
    transform: translateY(-1px);
}

.btn-delete-drink {
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

.btn-delete-drink:hover {
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

.form-control {
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 0.75rem;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    background: white;
}

.form-control:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
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
    .drinks-container {
        padding: 1rem;
    }
    
    .page-header {
        padding: 1.5rem;
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .drinks-table-section {
        padding: 1rem;
    }
    
    .drinks-table {
        font-size: 0.75rem;
    }
    
    .drinks-table th,
    .drinks-table td {
        padding: 0.75rem 0.5rem;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 0.25rem;
    }
}
</style>

<?php if ($action === 'view'): ?>
    <div class="drinks-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-wine-glass-alt"></i>
                        Drinks Management
                    </h1>
                    <p class="page-subtitle">Manage your beverage inventory and pricing</p>
                </div>
                <button class="add-drink-btn" data-bs-toggle="offcanvas" data-bs-target="#addDrinkOffcanvas">
                    <i class="fas fa-plus"></i>
                    Add New Drink
                </button>
            </div>
        </div>

        <!-- Drinks Table Section -->
        <div class="drinks-table-section">
            <div class="table-header">
                <div class="d-flex align-items-center gap-3">
                    <h3 class="table-title">All Drinks</h3>
                    <span class="drink-count"><?= count($drinks ?? []) ?> drinks</span>
                </div>
            </div>
            
            <?php if (!empty($drinks)): ?>
                <div class="table-responsive">
                    <table id="drinksTable" class="drinks-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Price (€)</th>
                                <th>Created</th>
                                <th width="100">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($drinks as $drink): ?>
                                <tr>
                                    <td class="drink-id">#<?= $drink['id'] ?></td>
                                    <td class="drink-name"><?= sanitizeInput($drink['name']) ?></td>
                                    <td class="drink-price">€<?= number_format($drink['price'], 2) ?></td>
                                    <td class="drink-date"><?= date('M j, Y', strtotime($drink['created_at'])) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-edit-drink edit-drink-btn"
                                                data-id="<?= $drink['id'] ?>"
                                                data-name="<?= sanitizeInput($drink['name']) ?>"
                                                data-price="<?= sanitizeInput($drink['price']) ?>"
                                                data-bs-toggle="offcanvas"
                                                data-bs-target="#editDrinkOffcanvas"
                                                title="Edit Drink">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-delete-drink delete-drink-btn"
                                                data-id="<?= $drink['id'] ?>"
                                                data-name="<?= sanitizeInput($drink['name']) ?>"
                                                title="Delete Drink">
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
                        <i class="fas fa-wine-glass-alt"></i>
                    </div>
                    <h3 class="empty-state-title">No Drinks Found</h3>
                    <p class="empty-state-text">Start by adding your first drink to the menu.</p>
                    <button class="add-drink-btn" data-bs-toggle="offcanvas" data-bs-target="#addDrinkOffcanvas">
                        <i class="fas fa-plus"></i>
                        Add First Drink
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Drink Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="addDrinkOffcanvas" aria-labelledby="addDrinkOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="addDrinkOffcanvasLabel">
                <i class="fas fa-plus me-2"></i>
                Add New Drink
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="drinks.php?action=add">
                <div class="form-group">
                    <label for="add-name" class="form-label">
                        Drink Name <span class="required">*</span>
                    </label>
                    <input type="text" class="form-control" id="add-name" name="name" 
                           placeholder="Enter drink name" required>
                </div>
                <div class="form-group">
                    <label for="add-price" class="form-label">
                        Price (€) <span class="required">*</span>
                    </label>
                    <input type="number" step="0.01" min="0" class="form-control" id="add-price" 
                           name="price" placeholder="0.00" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i>
                        Save Drink
                    </button>
                    <button type="button" class="btn-cancel" data-bs-dismiss="offcanvas">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Drink Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="editDrinkOffcanvas" aria-labelledby="editDrinkOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="editDrinkOffcanvasLabel">
                <i class="fas fa-edit me-2"></i>
                Edit Drink
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="drinks.php?action=edit">
                <input type="hidden" name="id" id="edit-drink-id">
                <div class="form-group">
                    <label for="edit-name" class="form-label">
                        Drink Name <span class="required">*</span>
                    </label>
                    <input type="text" class="form-control" id="edit-name" name="name" 
                           placeholder="Enter drink name" required>
                </div>
                <div class="form-group">
                    <label for="edit-price" class="form-label">
                        Price (€) <span class="required">*</span>
                    </label>
                    <input type="number" step="0.01" min="0" class="form-control" id="edit-price" 
                           name="price" placeholder="0.00" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i>
                        Update Drink
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

<?php if ($action === 'add' || ($action === 'edit' && $id > 0)): ?>
    <?php
    if ($action === 'edit') {
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
<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#drinksTable').DataTable({
        "paging": true,
        "searching": true,
        "info": true,
        "order": [[3, "desc"]],
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
                text: '<i class="fas fa-plus"></i> Add Drink',
                className: 'btn btn-success btn-sm',
                action: function() {
                    $('#addDrinkOffcanvas').offcanvas('show');
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

    // Edit drink functionality
    $('.edit-drink-btn').on('click', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var price = $(this).data('price');
        
        $('#edit-drink-id').val(id);
        $('#edit-name').val(name);
        $('#edit-price').val(price);
    });

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
});
</script>