<?php
// reservations.php

ob_start();
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Create reservations table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `reservations` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `client_name` VARCHAR(100) NOT NULL,
            `client_email` VARCHAR(100) NOT NULL,
            `phone_number` VARCHAR(20) NOT NULL,
            `reservation_date` DATE NOT NULL,
            `reservation_time` TIME NOT NULL,
            `number_of_people` INT NOT NULL,
            `reservation_source` ENUM('Online', 'Phone', 'In-person') NOT NULL,
            `confirmation_number` VARCHAR(20) NOT NULL UNIQUE,
            `status` ENUM('Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show') NOT NULL DEFAULT 'Pending',
            `assigned_to` INT DEFAULT NULL,
            `notes` TEXT,
            `message` VARCHAR(255),
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Tabelle konnte nicht erstellt werden: ' . htmlspecialchars($e->getMessage())];
    header('Location: reservations.php?action=list');
    exit();
}

$action = $_REQUEST['action'] ?? 'list';
$id     = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$message = '';

// Function to sanitize inputs
function sanitizeInput($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Function to validate reservation data
function validateReservation($pdo, $data, $id = 0)
{
    $errors = [];
    $client_name = sanitizeInput($data['client_name'] ?? '');
    $client_email = sanitizeInput($data['client_email'] ?? '');
    $phone_number = sanitizeInput($data['phone_number'] ?? '');
    $reservation_date = sanitizeInput($data['reservation_date'] ?? '');
    $reservation_time = sanitizeInput($data['reservation_time'] ?? '');
    $number_of_people = (int)($data['number_of_people'] ?? 0);
    $reservation_source = sanitizeInput($data['reservation_source'] ?? '');
    $confirmation_number = sanitizeInput($data['confirmation_number'] ?? '');
    $status = sanitizeInput($data['status'] ?? '');

    // Required fields
    if (empty($client_name) || empty($client_email) || empty($phone_number) || empty($reservation_date) || empty($reservation_time) || $number_of_people <= 0 || empty($reservation_source) || empty($status)) {
        $errors[] = 'Alle Pflichtfelder müssen ausgefüllt sein.';
    }

    // Email validation
    if (!filter_var($client_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Ungültige E-Mail-Adresse.';
    }

    // Status validation
    $valid_statuses = ['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'];
    if (!in_array($status, $valid_statuses)) {
        $errors[] = 'Ungültiger Status.';
    }

    // Confirmation number uniqueness
    if (!empty($confirmation_number)) {
        $sql = "SELECT COUNT(*) FROM reservations WHERE confirmation_number = ?";
        $params = [$confirmation_number];
        if ($id > 0) {
            $sql .= " AND id != ?";
            $params[] = $id;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Bestätigungsnummer existiert bereits.';
        }
    }

    return [$errors, compact('client_name', 'client_email', 'phone_number', 'reservation_date', 'reservation_time', 'number_of_people', 'reservation_source', 'confirmation_number', 'status')];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'Unbekannter Fehler'];

    if ($_POST['ajax_action'] === 'update_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = sanitizeInput($_POST['status'] ?? '');
        $valid_statuses = ['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'];

        if ($id > 0 && in_array($status, $valid_statuses)) {
            try {
                $pdo->beginTransaction();
                $current_time = date('Y-m-d H:i:s');
                $stmt = $pdo->prepare('UPDATE reservations SET status = ?, updated_at = NOW(), notes = CONCAT(IFNULL(notes, ""), ?, ". ") WHERE id = ?');
                $note = "[$current_time] Status aktualisiert auf '$status'";
                $stmt->execute([$status, $note, $id]);
                $pdo->commit();
                $response = ['status' => 'success', 'message' => 'Status erfolgreich aktualisiert.'];
            } catch (PDOException $e) {
                $pdo->rollBack();
                $response = ['status' => 'error', 'message' => 'Datenbankfehler: ' . sanitizeInput($e->getMessage())];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'Ungültige ID oder Status'];
        }
    } elseif ($_POST['ajax_action'] === 'assign_staff') {
        $id = (int)($_POST['id'] ?? 0);
        $assigned_to = ($_POST['assigned_to'] !== '') ? (int)$_POST['assigned_to'] : NULL;

        if ($id > 0) {
            try {
                $pdo->beginTransaction();
                $current_time = date('Y-m-d H:i:s');
                if ($assigned_to !== NULL) {
                    $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ? AND is_active = 1');
                    $stmt->execute([$assigned_to]);
                    $user = $stmt->fetch();
                    if (!$user) throw new Exception('Mitarbeiter nicht gefunden oder inaktiv.');
                    $assignment = "[$current_time] Zuweisung an '{$user['username']}'";
                } else {
                    $assignment = "[$current_time] Zuweisung entfernt.";
                }

                $stmt = $pdo->prepare('UPDATE reservations SET assigned_to = ?, updated_at = NOW(), notes = CONCAT(IFNULL(notes, ""), ?, ". ") WHERE id = ?');
                $stmt->execute([$assigned_to, $assignment, $id]);
                $pdo->commit();
                $response = ['status' => 'success', 'message' => 'Zuweisung erfolgreich aktualisiert.'];
            } catch (Exception $e) {
                $pdo->rollBack();
                $response = ['status' => 'error', 'message' => 'Fehler: ' . sanitizeInput($e->getMessage())];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'Ungültige Reservierungs-ID'];
        }
    } elseif ($_POST['ajax_action'] === 'get_notes') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare('SELECT notes FROM reservations WHERE id = ?');
                $stmt->execute([$id]);
                $reservation = $stmt->fetch();
                if ($reservation) {
                    $response = ['status' => 'success', 'notes' => $reservation['notes']];
                } else {
                    $response = ['status' => 'error', 'message' => 'Reservierung nicht gefunden.'];
                }
            } catch (PDOException $e) {
                $response = ['status' => 'error', 'message' => 'Datenbankfehler: ' . sanitizeInput($e->getMessage())];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'Ungültige Reservierungs-ID'];
        }
    }

    echo json_encode($response);
    exit;
}

// Handle Add/Edit form submissions
if (($action === 'add' || $action === 'edit') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize form inputs
    $client_name = trim($_POST['client_name'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $reservation_date = trim($_POST['reservation_date'] ?? '');
    $reservation_time = trim($_POST['reservation_time'] ?? '');
    $number_of_people = (int)($_POST['number_of_people'] ?? 0);
    $reservation_source = $_POST['reservation_source'] ?? '';
    $confirmation_number = trim($_POST['confirmation_number'] ?? '');
    $status = $_POST['status'] ?? '';
    $assigned_to = ($_POST['assigned_to'] !== '') ? (int)$_POST['assigned_to'] : NULL;
    $notes = trim($_POST['notes'] ?? '');
    $message_field = trim($_POST['message'] ?? '');

    // Validate inputs
    list($errors, $validatedData) = validateReservation($pdo, $_POST, $id);

    if ($errors) {
        $_SESSION['toast'] = ['type' => 'danger', 'message' => implode('<br>', $errors)];
        header("Location: reservations.php?action=" . ($action === 'add' ? 'add' : 'edit') . ($action === 'edit' ? "&id=$id" : ''));
        exit();
    } else {
        // Generate a confirmation number if not provided
        if (empty($confirmation_number)) {
            $confirmation_number = strtoupper(substr(md5(uniqid(rand(), true)), 0, 10));
        }

        // Prepare SQL and parameters based on action
        if ($action === 'add') {
            $sql = 'INSERT INTO reservations (client_name, client_email, phone_number, reservation_date, reservation_time, number_of_people, reservation_source, confirmation_number, status, assigned_to, notes, message) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $params = [
                $validatedData['client_name'],
                $validatedData['client_email'],
                $validatedData['phone_number'],
                $validatedData['reservation_date'],
                $validatedData['reservation_time'],
                $validatedData['number_of_people'],
                $validatedData['reservation_source'],
                $confirmation_number,
                $validatedData['status'],
                $assigned_to,
                $notes,
                $message_field
            ];
        } else { // Edit
            $sql = 'UPDATE reservations SET client_name = ?, client_email = ?, phone_number = ?, reservation_date = ?, reservation_time = ?, number_of_people = ?, reservation_source = ?, confirmation_number = ?, status = ?, assigned_to = ?, notes = ?, message = ?, updated_at = NOW() WHERE id = ?';
            $params = [
                $validatedData['client_name'],
                $validatedData['client_email'],
                $validatedData['phone_number'],
                $validatedData['reservation_date'],
                $validatedData['reservation_time'],
                $validatedData['number_of_people'],
                $validatedData['reservation_source'],
                $confirmation_number,
                $validatedData['status'],
                $assigned_to,
                $notes,
                $message_field,
                $id
            ];
        }

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Reservierung erfolgreich ' . ($action === 'add' ? 'hinzugefügt.' : 'aktualisiert.')];
            header("Location: reservations.php?action=list");
            exit();
        } catch (PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler beim Verarbeiten der Daten: ' . htmlspecialchars($e->getMessage())];
            header("Location: reservations.php?action=" . ($action === 'add' ? 'add' : 'edit') . ($action === 'edit' ? "&id=$id" : ''));
            exit();
        }
    }
}

// Handle reservation deletion
if ($action === 'delete' && $id > 0) {
    try {
        $stmt = $pdo->prepare('DELETE FROM reservations WHERE id = ?');
        $stmt->execute([$id]);
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Reservierung erfolgreich gelöscht.'];
        header("Location: reservations.php?action=list");
        exit();
    } catch (PDOException $e) {
        $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler beim Löschen der Reservierung: ' . htmlspecialchars($e->getMessage())];
        header("Location: reservations.php?action=list");
        exit();
    }
}

// Fetch reservations for listing with optional filters
function getReservations($pdo, $filters = [])
{
    try {
        $query = 'SELECT r.*, u.username AS assigned_username FROM reservations r LEFT JOIN users u ON r.assigned_to = u.id';
        $conditions = [];
        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], ['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'])) {
            $conditions[] = 'r.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['source']) && in_array($filters['source'], ['Online', 'Phone', 'In-person'])) {
            $conditions[] = 'r.reservation_source = ?';
            $params[] = $filters['source'];
        }

        if ($conditions) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $query .= ' ORDER BY r.reservation_date DESC, r.reservation_time DESC';
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Fetch reservations for listing
if ($action === 'list') {
    $filters = [];
    if (isset($_GET['filter_status'])) {
        $filters['status'] = sanitizeInput($_GET['filter_status']);
    }
    if (isset($_GET['filter_source'])) {
        $filters['source'] = sanitizeInput($_GET['filter_source']);
    }

    $reservations = getReservations($pdo, $filters);

    // Fetch active staff members to populate Assign dropdown
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE role IN ('admin', 'waiter', 'delivery') AND is_active = 1");
    $stmt->execute();
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php if ($action === 'list'): ?>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2>Reservierungen verwalten</h2>
        <button class="btn btn-success btn-sm" data-bs-toggle="offcanvas" data-bs-target="#createReservationOffcanvas">
            <i class="fas fa-plus"></i> Neue Reservierung
        </button>
    </div>
    <hr>
    <!-- Filters -->
    <form method="GET" action="reservations.php" class="row g-3 mb-4">
        <input type="hidden" name="action" value="list">
        <div class="col-md-3">
            <label for="filter_status" class="form-label">Status</label>
            <select name="filter_status" id="filter_status" class="form-select form-select-sm">
                <option value="">Alle Status</option>
                <?php foreach (['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'] as $stat): ?>
                    <option value="<?= $stat ?>" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] === $stat) ? 'selected' : '' ?>><?= $stat ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="filter_source" class="form-label">Quelle</label>
            <select name="filter_source" id="filter_source" class="form-select form-select-sm">
                <option value="">Alle Quellen</option>
                <?php foreach (['Online', 'Phone', 'In-person'] as $source): ?>
                    <option value="<?= $source ?>" <?= (isset($_GET['filter_source']) && $_GET['filter_source'] === $source) ? 'selected' : '' ?>><?= $source ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <button type="submit" class="btn btn-primary btn-sm me-2"><i class="fas fa-filter"></i> Filtern</button>
            <a href="reservations.php?action=list" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Zurücksetzen</a>
        </div>
    </form>
    <!-- Reservations Table -->
    <div class="table-responsive">
        <table id="reservationsTable" class="table table-striped table-bordered align-middle small">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Bestätigungsnummer</th>
                    <th>Client</th>
                    <th>Datum</th>
                    <th>Uhrzeit</th>
                    <th>Personen</th>
                    <th>Status</th>
                    <th>Zugewiesen an</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($reservations)): ?>
                    <?php foreach ($reservations as $res): ?>
                        <tr>
                            <td><?= sanitizeInput($res['id']) ?></td>
                            <td><?= sanitizeInput($res['confirmation_number']) ?></td>
                            <td><?= sanitizeInput($res['client_name']) ?></td>
                            <td><?= sanitizeInput($res['reservation_date']) ?></td>
                            <td><?= sanitizeInput($res['reservation_time']) ?></td>
                            <td><?= sanitizeInput($res['number_of_people']) ?></td>
                            <td>
                                <select class="form-select form-select-sm status-dropdown" data-id="<?= $res['id'] ?>">
                                    <option value="">Auswählen</option>
                                    <?php foreach (['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'] as $stat): ?>
                                        <option value="<?= $stat ?>" <?= ($res['status'] === $stat) ? 'selected' : '' ?>><?= $stat ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm assign-dropdown" data-id="<?= $res['id'] ?>">
                                    <option value="">Zuweisen</option>
                                    <?php foreach ($staff as $emp): ?>
                                        <option value="<?= $emp['id'] ?>" <?= ($res['assigned_to'] == $emp['id']) ? 'selected' : '' ?>><?= sanitizeInput($emp['username']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info me-1 view-notes-btn" data-id="<?= $res['id'] ?>" title="Notizen anzeigen">
                                    <i class="fas fa-sticky-note"></i>
                                </button>
                                <a href="reservations.php?action=edit&id=<?= $res['id'] ?>" class="btn btn-sm btn-warning me-1" title="Bearbeiten">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button class="btn btn-sm btn-danger delete-reservation-btn" data-id="<?= $res['id'] ?>" data-client="<?= sanitizeInput($res['client_name']) ?>" title="Löschen">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center">Keine Reservierungen gefunden.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Create Reservation Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="createReservationOffcanvas" aria-labelledby="createReservationOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="createReservationOffcanvasLabel">Neue Reservierung hinzufügen</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Schließen"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="reservations.php?action=add" class="row g-2">
                <div class="col-md-6">
                    <label for="create_client_name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="client_name" id="create_client_name" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-6">
                    <label for="create_client_email" class="form-label">E-Mail <span class="text-danger">*</span></label>
                    <input type="email" name="client_email" id="create_client_email" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-6">
                    <label for="create_phone_number" class="form-label">Telefonnummer <span class="text-danger">*</span></label>
                    <input type="tel" name="phone_number" id="create_phone_number" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-6">
                    <label for="create_reservation_source" class="form-label">Quelle <span class="text-danger">*</span></label>
                    <select name="reservation_source" id="create_reservation_source" class="form-select form-select-sm" required>
                        <option value="">Auswählen</option>
                        <option value="Online">Online</option>
                        <option value="Phone">Telefon</option>
                        <option value="In-person">Vor Ort</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="create_reservation_date" class="form-label">Datum <span class="text-danger">*</span></label>
                    <input type="date" name="reservation_date" id="create_reservation_date" class="form-control form-control-sm" required min="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3">
                    <label for="create_reservation_time" class="form-label">Uhrzeit <span class="text-danger">*</span></label>
                    <input type="time" name="reservation_time" id="create_reservation_time" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-3">
                    <label for="create_number_of_people" class="form-label">Personen <span class="text-danger">*</span></label>
                    <input type="number" name="number_of_people" id="create_number_of_people" class="form-control form-control-sm" required min="1">
                </div>
                <div class="col-md-3">
                    <label for="create_confirmation_number" class="form-label">Bestätigungsnummer</label>
                    <input type="text" name="confirmation_number" id="create_confirmation_number" class="form-control form-control-sm" placeholder="Automatisch generiert">
                </div>
                <div class="col-md-6">
                    <label for="create_status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" id="create_status" class="form-select form-select-sm" required>
                        <option value="">Auswählen</option>
                        <?php foreach (['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'] as $stat): ?>
                            <option value="<?= $stat ?>"><?= $stat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="create_assigned_to" class="form-label">Zuweisen an</label>
                    <select name="assigned_to" id="create_assigned_to" class="form-select form-select-sm">
                        <option value="">Nicht zugewiesen</option>
                        <?php foreach ($staff as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= sanitizeInput($emp['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="create_notes" class="form-label">Notizen</label>
                    <textarea name="notes" id="create_notes" class="form-control form-control-sm" rows="2" placeholder="Besondere Anmerkungen..."></textarea>
                </div>
                <div class="col-md-6">
                    <label for="create_message" class="form-label">Nachricht</label>
                    <input type="text" name="message" id="create_message" class="form-control form-control-sm" placeholder="Nachricht hinzufügen...">
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-success btn-sm me-2"><i class="fas fa-save"></i> Speichern</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="offcanvas">Abbrechen</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Reservation Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="editReservationOffcanvas" aria-labelledby="editReservationOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="editReservationOffcanvasLabel">Reservierung bearbeiten</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Schließen"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" id="editForm" action="reservations.php?action=edit&id=0" class="row g-2">
                <input type="hidden" name="id" id="edit_id">
                <div class="col-md-6">
                    <label for="edit_client_name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="client_name" id="edit_client_name" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-6">
                    <label for="edit_client_email" class="form-label">E-Mail <span class="text-danger">*</span></label>
                    <input type="email" name="client_email" id="edit_client_email" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-6">
                    <label for="edit_phone_number" class="form-label">Telefonnummer <span class="text-danger">*</span></label>
                    <input type="tel" name="phone_number" id="edit_phone_number" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-6">
                    <label for="edit_reservation_source" class="form-label">Quelle <span class="text-danger">*</span></label>
                    <select name="reservation_source" id="edit_reservation_source" class="form-select form-select-sm" required>
                        <option value="">Auswählen</option>
                        <?php foreach (['Online', 'Phone', 'In-person'] as $source): ?>
                            <option value="<?= $source ?>"><?= $source ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="edit_reservation_date" class="form-label">Datum <span class="text-danger">*</span></label>
                    <input type="date" name="reservation_date" id="edit_reservation_date" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-3">
                    <label for="edit_reservation_time" class="form-label">Uhrzeit <span class="text-danger">*</span></label>
                    <input type="time" name="reservation_time" id="edit_reservation_time" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-3">
                    <label for="edit_number_of_people" class="form-label">Personen <span class="text-danger">*</span></label>
                    <input type="number" name="number_of_people" id="edit_number_of_people" class="form-control form-control-sm" required min="1">
                </div>
                <div class="col-md-3">
                    <label for="edit_confirmation_number" class="form-label">Bestätigungsnummer</label>
                    <input type="text" name="confirmation_number" id="edit_confirmation_number" class="form-control form-control-sm">
                </div>
                <div class="col-md-6">
                    <label for="edit_status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" id="edit_status" class="form-select form-select-sm" required>
                        <option value="">Auswählen</option>
                        <?php foreach (['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'] as $stat): ?>
                            <option value="<?= $stat ?>"><?= $stat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="edit_assigned_to" class="form-label">Zuweisen an</label>
                    <select name="assigned_to" id="edit_assigned_to" class="form-select form-select-sm">
                        <option value="">Nicht zugewiesen</option>
                        <?php foreach ($staff as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= sanitizeInput($emp['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="edit_notes" class="form-label">Notizen</label>
                    <textarea name="notes" id="edit_notes" class="form-control form-control-sm" rows="2" placeholder="Besondere Anmerkungen..."></textarea>
                </div>
                <div class="col-md-6">
                    <label for="edit_message" class="form-label">Nachricht</label>
                    <input type="text" name="message" id="edit_message" class="form-control form-control-sm" placeholder="Nachricht hinzufügen...">
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-success btn-sm me-2"><i class="fas fa-save"></i> Aktualisieren</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="offcanvas">Abbrechen</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Notes Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="viewNotesOffcanvas" aria-labelledby="viewNotesOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="viewNotesOffcanvasLabel">Reservierungsnotizen</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Schließen"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="list-group" id="notesList">
                <li class="list-group-item">Lade Notizen...</li>
            </ul>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
        <div id="toast-container"></div>
    </div>
<?php endif; ?>

<?php if ($action === 'add' || ($action === 'edit' && $id > 0)): ?>
    <?php
    if ($action === 'edit') {
        try {
            $stmt = $pdo->prepare('SELECT * FROM reservations WHERE id = ?');
            $stmt->execute([$id]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$reservation) {
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Reservierung nicht gefunden.'];
                header("Location: reservations.php?action=list");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Fehler: ' . sanitizeInput($e->getMessage())];
            header("Location: reservations.php?action=list");
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
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<!-- Font Awesome -->
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        <?php if ($action === 'list'): ?>
            // Initialize DataTable
            $('#reservationsTable').DataTable({
                "paging": true,
                "searching": true,
                "info": true,
                "order": [
                    [0, "desc"]
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
                        text: '<i class="fas fa-plus"></i> Neue Reservierung',
                        className: 'btn btn-success btn-sm rounded-2',
                        action: function() {
                            $('#createReservationOffcanvas').offcanvas('show');
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
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/de-DE.json'
                }
            });

            // Initialize Tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Handle Status Change
            $(document).on('change', '.status-dropdown', function() {
                let id = $(this).data('id');
                let status = $(this).val();
                if (status) {
                    $.post('reservations.php', {
                        ajax_action: 'update_status',
                        id: id,
                        status: status
                    }, function(response) {
                        if (response.status === 'success') {
                            showToast(response.message, 'success');
                        } else {
                            showToast(response.message, 'danger');
                            // Revert the select value
                            location.reload();
                        }
                    }, 'json').fail(() => {
                        showToast('Fehler beim Aktualisieren des Status.', 'danger');
                        location.reload();
                    });
                }
            });

            // Handle Assign Change
            $(document).on('change', '.assign-dropdown', function() {
                let id = $(this).data('id');
                let assigned_to = $(this).val();
                $.post('reservations.php', {
                    ajax_action: 'assign_staff',
                    id: id,
                    assigned_to: assigned_to
                }, function(response) {
                    if (response.status === 'success') {
                        showToast(response.message, 'success');
                    } else {
                        showToast(response.message, 'danger');
                        // Revert the select value
                        location.reload();
                    }
                }, 'json').fail(() => {
                    showToast('Fehler bei der Zuweisung des Mitarbeiters.', 'danger');
                    location.reload();
                });
            });

            // Handle View Notes
            $(document).on('click', '.view-notes-btn', function() {
                let id = $(this).data('id');
                $('#notesList').html('<li class="list-group-item">Lade Notizen...</li>');
                let notesOffcanvas = new bootstrap.Offcanvas(document.getElementById('viewNotesOffcanvas'));
                notesOffcanvas.show();
                $.post('reservations.php', {
                    ajax_action: 'get_notes',
                    id: id
                }, function(response) {
                    if (response.status === 'success') {
                        let notes = response.notes.split('. ').filter(note => note.trim() !== '');
                        if (notes.length) {
                            let listItems = notes.map(note => `<li class="list-group-item">${note}.</li>`).join('');
                            $('#notesList').html(listItems);
                        } else {
                            $('#notesList').html('<li class="list-group-item">Keine Notizen verfügbar.</li>');
                        }
                    } else {
                        $('#notesList').html(`<li class="list-group-item text-danger">${response.message}</li>`);
                    }
                }, 'json').fail(() => {
                    $('#notesList').html('<li class="list-group-item text-danger">Fehler beim Laden der Notizen.</li>');
                });
            });

            // Handle Delete Reservation with SweetAlert2
            $(document).on('click', '.delete-reservation-btn', function() {
                let id = $(this).data('id');
                let client = $(this).data('client');
                Swal.fire({
                    title: 'Sind Sie sicher?',
                    text: `Möchten Sie die Reservierung für "${client}" wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ja, löschen!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'reservations.php?action=delete&id=' + id;
                    }
                });
            });

            // Handle Edit Reservation Button Click
            $('.edit-reservation-btn').on('click', function() {
                let id = $(this).data('id');
                // Fetch reservation data via AJAX or embed data attributes
                // For simplicity, we'll reload the page with action=edit&id=ID
                window.location.href = 'reservations.php?action=edit&id=' + id;
            });

            // Function to show toast notifications
            function showToast(message, type = 'primary') {
                let toastHtml = `
                    <div class="toast align-items-center text-white bg-${type} border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                ${message}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Schließen"></button>
                        </div>
                    </div>
                `;
                $('#toast-container').append(toastHtml);
                $('.toast').toast({
                    delay: 5000
                }).toast('show');

                // Remove the toast after it hides
                $('.toast').on('hidden.bs.toast', function() {
                    $(this).remove();
                });
            }

            // Show toast from PHP session
            <?php if (isset($_SESSION['toast'])): ?>
                showToast(`<?= $_SESSION['toast']['message'] ?>`, '<?= $_SESSION['toast']['type'] === 'success' ? 'success' : 'danger' ?>');
                <?php unset($_SESSION['toast']); ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($action === 'add' || ($action === 'edit' && $id > 0)): ?>
            // Automatically open the respective offcanvas if there's a validation error
            <?php if (!empty($_SESSION['toast'])): ?>
                $('#<?= $action === 'add' ? 'createReservationOffcanvas' : 'editReservationOffcanvas' ?>').offcanvas('show');
            <?php endif; ?>
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

    .toast-container .toast {
        min-width: 250px;
    }
</style>