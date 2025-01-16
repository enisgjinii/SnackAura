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

<?php if ($action === 'list'): ?>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2>Coupons verwalten</h2>
        <button class="btn btn-success btn-sm" data-bs-toggle="offcanvas" data-bs-target="#createCouponOffcanvas">
            <i class="fas fa-plus"></i> Neuer Coupon
        </button>
    </div>
    <hr>
    <div class="table-responsive">
        <table id="couponsTable" class="table table-striped table-bordered align-middle small">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Code</th>
                    <th>Typ</th>
                    <th>Wert</th>
                    <th>Aktiv</th>
                    <th>Startdatum</th>
                    <th>Enddatum</th>
                    <th>Erstellt am</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($coupons)): ?>
                    <?php foreach ($coupons as $c): ?>
                        <tr>
                            <td><?= sanitizeInput($c['id']) ?></td>
                            <td><?= sanitizeInput($c['code']) ?></td>
                            <td><?= sanitizeInput($c['discount_type'] === 'percentage' ? 'Prozentual' : 'Fest') ?></td>
                            <td><?= sanitizeInput($c['discount_value']) ?></td>
                            <td>
                                <span class="badge <?= ($c['is_active'] ? 'bg-success' : 'bg-secondary') ?>">
                                    <?= ($c['is_active'] ? 'Ja' : 'Nein') ?>
                                </span>
                            </td>
                            <td><?= sanitizeInput($c['start_date'] ?? 'Nicht gesetzt') ?></td>
                            <td><?= sanitizeInput($c['end_date'] ?? 'Nicht gesetzt') ?></td>
                            <td><?= sanitizeInput($c['created_at']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning me-1 edit-coupon-btn"
                                    data-id="<?= $c['id'] ?>"
                                    data-code="<?= sanitizeInput($c['code']) ?>"
                                    data-type="<?= sanitizeInput($c['discount_type']) ?>"
                                    data-value="<?= sanitizeInput($c['discount_value']) ?>"
                                    data-active="<?= $c['is_active'] ?>"
                                    data-start="<?= sanitizeInput($c['start_date']) ?>"
                                    data-end="<?= sanitizeInput($c['end_date']) ?>"
                                    data-bs-toggle="offcanvas"
                                    data-bs-target="#editCouponOffcanvas">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-coupon-btn"
                                    data-id="<?= $c['id'] ?>"
                                    data-code="<?= sanitizeInput($c['code']) ?>">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center">Keine Coupons gefunden.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Create Coupon Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="createCouponOffcanvas" aria-labelledby="createCouponOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="createCouponOffcanvasLabel">Neuen Coupon hinzufügen</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Schließen"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="cupons.php?action=create">
                <div class="mb-2">
                    <label for="create_code" class="form-label">Code <span class="text-danger">*</span></label>
                    <input type="text" name="code" id="create_code" class="form-control form-control-sm" required>
                </div>
                <div class="mb-2">
                    <label for="create_discount_type" class="form-label">Typ <span class="text-danger">*</span></label>
                    <select name="discount_type" id="create_discount_type" class="form-select form-select-sm" required>
                        <option value="percentage">Prozentual</option>
                        <option value="fixed">Fest</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label for="create_discount_value" class="form-label">Wert <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="discount_value" id="create_discount_value" class="form-control form-control-sm" required>
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" name="is_active" id="create_is_active" class="form-check-input" value="1" checked>
                    <label class="form-check-label" for="create_is_active">Aktiv</label>
                </div>
                <div class="mb-2">
                    <label for="create_start_date" class="form-label">Startdatum</label>
                    <input type="date" name="start_date" id="create_start_date" class="form-control form-control-sm">
                </div>
                <div class="mb-2">
                    <label for="create_end_date" class="form-label">Enddatum</label>
                    <input type="date" name="end_date" id="create_end_date" class="form-control form-control-sm">
                </div>
                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save"></i> Speichern</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="offcanvas">Abbrechen</button>
            </form>
        </div>
    </div>

    <!-- Edit Coupon Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="editCouponOffcanvas" aria-labelledby="editCouponOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="editCouponOffcanvasLabel">Coupon bearbeiten</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Schließen"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" id="editForm" action="cupons.php?action=edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="mb-2">
                    <label for="edit_code" class="form-label">Code <span class="text-danger">*</span></label>
                    <input type="text" name="code" id="edit_code" class="form-control form-control-sm" required>
                </div>
                <div class="mb-2">
                    <label for="edit_discount_type" class="form-label">Typ <span class="text-danger">*</span></label>
                    <select name="discount_type" id="edit_discount_type" class="form-select form-select-sm" required>
                        <option value="percentage">Prozentual</option>
                        <option value="fixed">Fest</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label for="edit_discount_value" class="form-label">Wert <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="discount_value" id="edit_discount_value" class="form-control form-control-sm" required>
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" name="is_active" id="edit_is_active" class="form-check-input" value="1">
                    <label class="form-check-label" for="edit_is_active">Aktiv</label>
                </div>
                <div class="mb-2">
                    <label for="edit_start_date" class="form-label">Startdatum</label>
                    <input type="date" name="start_date" id="edit_start_date" class="form-control form-control-sm">
                </div>
                <div class="mb-2">
                    <label for="edit_end_date" class="form-label">Enddatum</label>
                    <input type="date" name="end_date" id="edit_end_date" class="form-control form-control-sm">
                </div>
                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save"></i> Aktualisieren</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="offcanvas">Abbrechen</button>
            </form>
        </div>
    </div>

    <!-- Toast-Benachrichtigungen -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
        <div id="toast-container"></div>
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
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Coupon nicht gefunden.'];
                header("Location: cupons.php?action=list");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler: ' . sanitizeInput($e->getMessage())];
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
        $('#couponsTable').DataTable({
            "paging": true,
            "searching": true,
            "info": true,
            "order": [
                [7, "desc"]
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
                    text: '<i class="fas fa-plus"></i> Neuer Coupon',
                    className: 'btn btn-success btn-sm rounded-2',
                    action: function() {
                        $('#createCouponOffcanvas').offcanvas('show');
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
            $('#editCouponOffcanvas').offcanvas('show');
        });

        $('.delete-coupon-btn').on('click', function() {
            var id = $(this).data('id');
            var code = $(this).data('code');
            Swal.fire({
                title: 'Sind Sie sicher?',
                text: `Möchten Sie den Coupon "${code}" wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ja, löschen!'
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
<style>
    .sortable-placeholder {
        background: #f0f0f0;
        border: 2px dashed #ccc;
        height: 40px;
    }
</style>