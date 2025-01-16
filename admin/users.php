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
            `role` ENUM('super-admin', 'admin', 'waiter', 'delivery') NOT NULL DEFAULT 'waiter',
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
            header("Location: users.php?action=create");
            exit;
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$data['username'], $data['email']]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Benutzername oder E-Mail existiert bereits.'];
                header("Location: users.php?action=create");
                exit();
            }
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $code = $data['role'] === 'waiter' ? generateWaiterCode($pdo) : null;
            $stmt = $pdo->prepare('INSERT INTO users (username, password, email, role, code) VALUES (?, ?, ?, ?, ?)');
            try {
                $stmt->execute([$data['username'], $hashed_password, $data['email'], $data['role'], $code]);
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Benutzer erfolgreich erstellt.'];
                header("Location: users.php?action=list");
                exit();
            } catch (PDOException $e) {
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler beim Erstellen des Benutzers.'];
                header("Location: users.php?action=create");
                exit();
            }
        }
    } elseif ($action === 'edit' && $id > 0) {
        list($errors, $data) = validateUser($pdo, $_POST, $id);
        $password = $_POST['password'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        if ($errors) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => implode('<br>', $errors)];
            header("Location: users.php?action=edit&id=$id");
            exit();
        } else {
            $stmt = $pdo->prepare("SELECT code FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $existingCode = $stmt->fetchColumn();
            $params = [$data['username'], $data['email'], $data['role'], $is_active];
            $sql = 'UPDATE users SET username = ?, email = ?, role = ?, is_active = ?';
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
                header("Location: users.php?action=edit&id=$id");
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
        $stmt = $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
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
} elseif ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare('SELECT id, username, email, role, is_active, code FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Benutzer nicht gefunden.'];
        header('Location: users.php?action=list');
        exit();
    }
} elseif ($action === 'delete' && $id > 0) {
    $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<?php if ($action === 'list'): ?>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2>Benutzer verwalten</h2>
        <button class="btn btn-success btn-sm" data-bs-toggle="offcanvas" data-bs-target="#createUserOffcanvas" data-bs-toggle="tooltip" data-bs-placement="top" title="Neuen Benutzer hinzufügen">
            <i class="fas fa-user-plus"></i>
        </button>
    </div>
    <hr>
    <div class="table-responsive">
        <table id="usersTable" class="table table-striped table-bordered align-middle small">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Code</th>
                    <th>Benutzername</th>
                    <th>E-Mail</th>
                    <th>Rolle</th>
                    <th>Status</th>
                    <th>Erstellt am</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= sanitizeInput($u['id']) ?></td>
                            <td><?= ($u['role'] === 'waiter' && $u['code']) ? sanitizeInput($u['code']) : '-' ?></td>
                            <td><?= sanitizeInput($u['username']) ?></td>
                            <td><?= sanitizeInput($u['email']) ?></td>
                            <td><?= ucfirst(sanitizeInput($u['role'])) ?></td>
                            <td>
                                <span class="badge bg-<?= $u['is_active'] ? 'success' : 'secondary' ?>">
                                    <?= $u['is_active'] ? 'Aktiv' : 'Inaktiv' ?>
                                </span>
                            </td>
                            <td><?= sanitizeInput($u['created_at']) ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-warning me-1 edit-user-btn" data-id="<?= $u['id'] ?>" data-username="<?= sanitizeInput($u['username']) ?>" data-email="<?= sanitizeInput($u['email']) ?>" data-role="<?= sanitizeInput($u['role']) ?>" data-active="<?= $u['is_active'] ?>" data-code="<?= sanitizeInput($u['code']) ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Bearbeiten">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-user-btn" data-id="<?= $u['id'] ?>" data-username="<?= sanitizeInput($u['username']) ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Löschen">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <button class="btn btn-sm <?= $u['is_active'] ? 'btn-warning' : 'btn-success' ?> toggle-status-btn" data-id="<?= $u['id'] ?>" data-username="<?= sanitizeInput($u['username']) ?>" data-status="<?= $u['is_active'] ? 'deaktivieren' : 'aktivieren' ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $u['is_active'] ? 'Deaktivieren' : 'Aktivieren' ?>">
                                    <i class="fas <?= $u['is_active'] ? 'fa-toggle-off' : 'fa-toggle-on' ?>"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">Keine Benutzer gefunden.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Create User Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="createUserOffcanvas" aria-labelledby="createUserOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="createUserOffcanvasLabel">Neuen Benutzer hinzufügen</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Schließen"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="users.php?action=create">
                <div class="mb-2">
                    <label for="create_username" class="form-label">Benutzername <span class="text-danger">*</span></label>
                    <input type="text" name="username" id="create_username" class="form-control form-control-sm" required maxlength="50" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="mb-2">
                    <label for="create_password" class="form-label">Passwort <span class="text-danger">*</span></label>
                    <input type="password" name="password" id="create_password" class="form-control form-control-sm" required minlength="6">
                </div>
                <div class="mb-2">
                    <label for="create_email" class="form-label">E-Mail <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="create_email" class="form-control form-control-sm" required maxlength="100" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="mb-2">
                    <label for="create_role" class="form-label">Rolle <span class="text-danger">*</span></label>
                    <select name="role" id="create_role" class="form-select form-select-sm" required>
                        <option value="super-admin">Super Admin</option>
                        <option value="admin">Admin</option>
                        <option value="waiter">Kellner</option>
                        <option value="delivery">Lieferung</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save"></i></button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="offcanvas"><i class="fas fa-times"></i></button>
            </form>
        </div>
    </div>

    <!-- Edit User Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="editUserOffcanvas" aria-labelledby="editUserOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="editUserOffcanvasLabel">Benutzer bearbeiten</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Schließen"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" id="editForm" action="users.php?action=edit&id=0">
                <input type="hidden" name="id" id="edit_id">
                <div class="mb-2">
                    <label for="edit_username" class="form-label">Benutzername <span class="text-danger">*</span></label>
                    <input type="text" name="username" id="edit_username" class="form-control form-control-sm" required maxlength="50">
                </div>
                <div class="mb-2">
                    <label for="edit_password" class="form-label">Passwort (Leer lassen, um aktuell zu lassen)</label>
                    <input type="password" name="password" id="edit_password" class="form-control form-control-sm" minlength="6">
                </div>
                <div class="mb-2">
                    <label for="edit_email" class="form-label">E-Mail <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="edit_email" class="form-control form-control-sm" required maxlength="100">
                </div>
                <div class="mb-2">
                    <label for="edit_role" class="form-label">Rolle <span class="text-danger">*</span></label>
                    <select name="role" id="edit_role" class="form-select form-select-sm" required>
                        <option value="super-admin">Super Admin</option>
                        <option value="admin">Admin</option>
                        <option value="waiter">Kellner</option>
                        <option value="delivery">Lieferung</option>
                    </select>
                </div>
                <div class="mb-2" id="waiter_code_section" style="display: none;">
                    <label for="edit_code" class="form-label">Kellner-Code</label>
                    <input type="text" id="edit_code" class="form-control form-control-sm" readonly>
                </div>
                <div class="mb-2 form-check">
                    <input type="checkbox" name="is_active" id="edit_is_active" class="form-check-input">
                    <label class="form-check-label" for="edit_is_active">Aktiv</label>
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i></button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="offcanvas"><i class="fas fa-times"></i></button>
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
            $stmt = $pdo->prepare("SELECT id, username, email, role, is_active, code FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Benutzer nicht gefunden.'];
                header("Location: users.php?action=list");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler: ' . sanitizeInput($e->getMessage())];
            header("Location: users.php?action=list");
            exit();
        }
    }
    ?>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
ob_end_flush();
?>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.colVis.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        // Initialisiere DataTable
        $('#usersTable').DataTable({
            "paging": true,
            "searching": true,
            "info": true,
            "order": [
                [6, "desc"]
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
                    text: '<i class="fas fa-user-plus"></i>',
                    className: 'btn btn-success btn-sm rounded-2',
                    action: function() {
                        $('#createUserOffcanvas').offcanvas('show');
                    },
                    titleAttr: 'Neuen Benutzer hinzufügen'
                },
                {
                    extend: 'csv',
                    text: '<i class="fas fa-file-csv"></i>',
                    className: 'btn btn-primary btn-sm rounded-2',
                    titleAttr: 'CSV exportieren'
                },
                {
                    extend: 'pdf',
                    text: '<i class="fas fa-file-pdf"></i>',
                    className: 'btn btn-primary btn-sm rounded-2',
                    titleAttr: 'PDF exportieren'
                },
                {
                    extend: 'colvis',
                    text: '<i class="fas fa-columns"></i>',
                    className: 'btn btn-primary btn-sm rounded-2',
                    titleAttr: 'Spalten anzeigen'
                },
                {
                    extend: 'copy',
                    text: '<i class="fas fa-copy"></i>',
                    className: 'btn btn-primary btn-sm rounded-2',
                    titleAttr: 'Kopieren'
                },
            ],
            initComplete: function() {
                var buttons = this.api().buttons();
                buttons.container().addClass('d-flex flex-wrap gap-2');
            },
            "language": {
                url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/de-DE.json'
            }
        });

        // Tooltips initialisieren
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })

        // Bearbeiten-Button
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
            $('#editUserOffcanvas').offcanvas('show');
        });

        // Löschen-Button
        $('.delete-user-btn').on('click', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var username = $(this).data('username');
            Swal.fire({
                title: 'Sind Sie sicher?',
                text: `Möchten Sie den Benutzer "${username}" wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ja, löschen!'
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

        // Status-Button
        $('.toggle-status-btn').on('click', function() {
            var id = $(this).data('id');
            var username = $(this).data('username');
            var status = $(this).data('status');
            Swal.fire({
                title: 'Sind Sie sicher?',
                text: `Möchten Sie den Benutzer "${username}" wirklich ${status}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ja, machen!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'users.php?action=toggle_status&id=' + id;
                }
            });
        });

        // Formular-Übertragung für Bearbeiten (optional, wenn Offcanvas genutzt wird)
        // Kann entfernt werden, da die Daten bereits durch das Offcanvas-Formular gesetzt werden

        // Toast-Benachrichtigungen
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
    .table td,
    .table th {
        vertical-align: middle;
        text-align: center;
        padding: 0.5rem;
    }

    .offcanvas-body form .form-label {
        font-size: 0.875rem;
    }

    .offcanvas-body form .form-control,
    .offcanvas-body form .form-select {
        font-size: 0.875rem;
    }

    .btn {
        padding: 0.25rem 0.4rem;
        font-size: 0.875rem;
    }
</style>