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

<?php if ($action === 'view'): ?>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2>Getränke verwalten</h2>
        <button class="btn btn-success btn-sm" data-bs-toggle="offcanvas" data-bs-target="#addDrinkOffcanvas">
            <i class="fas fa-plus"></i> Neues Getränk
        </button>
    </div>
    <hr>
    <div class="table-responsive">
        <table id="drinksTable" class="table table-striped table-bordered align-middle small">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Preis (€)</th>
                    <th>Erstellt am</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($drinks)): ?>
                    <?php foreach ($drinks as $drink): ?>
                        <tr>
                            <td><?= sanitizeInput($drink['id']) ?></td>
                            <td><?= sanitizeInput($drink['name']) ?></td>
                            <td><?= number_format($drink['price'], 2) ?></td>
                            <td><?= sanitizeInput($drink['created_at']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning me-1 edit-drink-btn"
                                    data-id="<?= $drink['id'] ?>"
                                    data-name="<?= sanitizeInput($drink['name']) ?>"
                                    data-price="<?= sanitizeInput($drink['price']) ?>"
                                    data-bs-toggle="offcanvas"
                                    data-bs-target="#editDrinkOffcanvas">
                                    <i class="fas fa-edit"></i> 
                                </button>
                                <button class="btn btn-sm btn-danger delete-drink-btn"
                                    data-id="<?= $drink['id'] ?>"
                                    data-name="<?= sanitizeInput($drink['name']) ?>">
                                    <i class="fas fa-trash-alt"></i> 
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">Keine Getränke gefunden.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Drink Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="addDrinkOffcanvas" aria-labelledby="addDrinkOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="addDrinkOffcanvasLabel">Neues Getränk hinzufügen</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Schließen"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="drinks.php?action=add">
                <div class="mb-2">
                    <label for="add-name" class="form-label">Getränkename <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="add-name" name="name" required>
                </div>
                <div class="mb-2">
                    <label for="add-price" class="form-label">Preis (€) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="add-price" name="price" required>
                </div>
                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save"></i> Speichern</button>
            </form>
        </div>
    </div>

    <!-- Edit Drink Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="editDrinkOffcanvas" aria-labelledby="editDrinkOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="editDrinkOffcanvasLabel">Getränk </h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Schließen"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="drinks.php?action=edit">
                <input type="hidden" name="id" id="edit-drink-id">
                <div class="mb-2">
                    <label for="edit-name" class="form-label">Getränkename <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="edit-name" name="name" required>
                </div>
                <div class="mb-2">
                    <label for="edit-price" class="form-label">Preis (€) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="edit-price" name="price" required>
                </div>
                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save"></i> Aktualisieren</button>
            </form>
        </div>
    </div>

    <!-- Toast-Benachrichtigungen -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
        <div id="toast-container"></div>
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
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Getränk nicht gefunden.'];
                header("Location: drinks.php");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler: ' . sanitizeInput($e->getMessage())];
            header("Location: drinks.php");
            exit();
        }
    }
    ?>
    <!-- Edit Drink Offcanvas (redundant but kept for consistency) -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="editDrinkOffcanvas" aria-labelledby="editDrinkOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="editDrinkOffcanvasLabel">Getränk </h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Schließen"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="drinks.php?action=edit">
                <input type="hidden" name="id" id="edit-drink-id" value="<?= $id ?>">
                <div class="mb-2">
                    <label for="edit-name" class="form-label">Getränkename <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="edit-name" name="name" required value="<?= sanitizeInput($drink['name']) ?>">
                </div>
                <div class="mb-2">
                    <label for="edit-price" class="form-label">Preis (€) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="edit-price" name="price" required value="<?= sanitizeInput($drink['price']) ?>">
                </div>
                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save"></i> Aktualisieren</button>
                <a href="drinks.php" class="btn btn-secondary btn-sm">Abbrechen</a>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>
<script>
    $(document).ready(function() {
        $('#drinksTable').DataTable({
            "paging": true,
            "searching": true,
            "info": true,
            "order": [
                [3, "desc"]
            ],
            "dom": '<"row mb-3"' +
                '<"col-12 d-flex justify-content-between align-items-center"lBf>' +
                '>' +
                'rt' +
                '<"row mt-3"' +
                '<"col-sm-12 col-md-6 d-flex justify-content-start"i>' +
                '<"col-sm-12 col-md-6 d-flex justify-content-end"p>' +
                '>',
            "buttons": [{
                    text: '<i class="fas fa-plus"></i> Neues Getränk',
                    className: 'btn btn-success btn-sm rounded-2',
                    action: function() {
                        $('#addDrinkOffcanvas').offcanvas('show');
                    }
                },
                {
                    extend: 'csv',
                    text: '<i class="fas fa-file-csv"></i> CSV exportieren',
                    className: 'btn btn-primary btn-sm rounded-2'
                },
                {
                    extend: 'pdf',
                    text: '<i class="fas fa-file-pdf"></i> PDF exportieren',
                    className: 'btn btn-primary btn-sm rounded-2'
                },
                {
                    extend: 'colvis',
                    text: '<i class="fas fa-columns"></i> Spalten',
                    className: 'btn btn-primary btn-sm rounded-2',
                },
                {
                    extend: 'copy',
                    text: '<i class="fas fa-copy"></i> Kopieren',
                    className: 'btn btn-primary btn-sm rounded-2',
                },
            ],
            initComplete: function() {
                var buttons = this.api().buttons();
                buttons.container().addClass('d-flex flex-wrap gap-2');
            },
            "language": {
                url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/de-DE.json'
            }
        });

        $('.edit-drink-btn').on('click', function() {
            var id = $(this).data('id');
            var name = $(this).data('name');
            var price = $(this).data('price');
            $('#edit-drink-id').val(id);
            $('#edit-name').val(name);
            $('#edit-price').val(price);
            $('#editDrinkOffcanvas').offcanvas('show');
        });

        $('.delete-drink-btn').on('click', function() {
            var id = $(this).data('id');
            var name = $(this).data('name');
            Swal.fire({
                title: 'Sind Sie sicher?',
                text: `Möchten Sie das Getränk "${name}" wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ja, löschen!'
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

        <?php if (isset($_SESSION['toast'])): ?>
            var toastHtml = `
                <div class="toast align-items-center text-white bg-<?= $_SESSION['toast']['type'] === 'success' ? 'success' : 'danger' ?> border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <?= $_SESSION['toast']['message'] ?>
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Schließen"></button>
                    </div>
                </div>
            `;
            $('#toast-container').html(toastHtml);
            $('.toast').toast({
                delay: 5000
            }).toast('show');
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>
    });
</script>
<!-- Benutzerdefinierte Styles für Sortable Placeholder (falls benötigt) -->
<style>
    .sortable-placeholder {
        background: #f0f0f0;
        border: 2px dashed #ccc;
        height: 40px;
    }
</style>