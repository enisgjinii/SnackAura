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

<style>
    /* Reservations Page - Match Products UI */
    .reservations-content {
        padding: 2rem;
        background: var(--content-bg, #f8fafc);
        min-height: 100vh;
    }
    .page-header {
        margin-bottom: 2rem;
    }
    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        background: white;
        border-radius: 12px;
        box-shadow: 0 1px 6px 0 rgba(16,30,54,0.06);
        border: 1px solid #e5e7eb;
    }
    .page-title {
        font-size: 1.875rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 0.5rem 0;
    }
    .page-subtitle {
        color: #64748b;
        margin: 0;
        font-size: 1rem;
    }
    .header-actions {
        display: flex;
        gap: 1rem;
    }
    .table-section {
        background: white;
        border-radius: 12px;
        box-shadow: 0 1px 6px 0 rgba(16,30,54,0.06);
        border: 1px solid #e5e7eb;
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
        border-bottom: 1px solid #e5e7eb;
        font-size: 0.875rem;
    }
    .data-table td {
        padding: 1rem;
        border-bottom: 1px solid #e5e7eb;
        color: #374151;
        vertical-align: middle;
    }
    .data-table tbody tr:hover {
        background: #f8fafc;
    }
    .badge {
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 500;
        margin-left: 0.5rem;
    }
    .badge-pending { background: #fef3c7; color: #92400e; }
    .badge-confirmed { background: #dcfce7; color: #166534; }
    .badge-cancelled { background: #fee2e2; color: #991b1b; }
    .badge-completed { background: #dbeafe; color: #1e40af; }
    .badge-no-show { background: #f3f4f6; color: #6b7280; }
    .actions-cell { text-align: center; }
    .action-buttons { display: flex; gap: 0.5rem; justify-content: center; }
    .btn-edit { background: #3b82f6; color: white; border: none; padding: 0.5rem; border-radius: 6px; transition: all 0.15s; }
    .btn-edit:hover { background: #2563eb; }
    .btn-delete { background: #ef4444; color: white; border: none; padding: 0.5rem; border-radius: 6px; transition: all 0.15s; }
    .btn-delete:hover { background: #dc2626; }
    .btn-notes { background: #64748b; color: white; border: none; padding: 0.5rem; border-radius: 6px; transition: all 0.15s; }
    .btn-notes:hover { background: #334155; }
    .no-data { text-align: center; padding: 3rem 1rem; }
    .empty-state { display: flex; flex-direction: column; align-items: center; gap: 1rem; }
    .empty-state i { font-size: 3rem; color: #9ca3af; }
    .empty-state h3 { color: #374151; margin: 0; font-size: 1.25rem; }
    .empty-state p { color: #64748b; margin: 0; }
</style>

<?php if ($action === 'list'): ?>
    <div class="reservations-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-calendar-check"></i>
                        Reservations Management
                    </h1>
                    <p class="page-subtitle">Manage table reservations and bookings</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-success" data-bs-toggle="offcanvas" data-bs-target="#createReservationOffcanvas">
                        <i class="fas fa-plus"></i>
                        Add New Reservation
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="form-section" style="margin-bottom:2rem;">
            <div class="form-card">
                <div class="card-header">
                    <h3>Filter Reservations</h3>
                    <i class="fas fa-filter"></i>
                </div>
                <div class="card-content">
                    <form method="GET" action="reservations.php" class="filter-form">
                        <div class="form-group" style="max-width:200px;display:inline-block;margin-right:1rem;">
                            <label for="filter_status" class="form-label">Status</label>
                            <select name="filter_status" id="filter_status" class="form-control">
                                <option value="">All Status</option>
                                <?php foreach (['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'] as $stat): ?>
                                    <option value="<?= $stat ?>" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] === $stat) ? 'selected' : '' ?>><?= $stat ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="max-width:200px;display:inline-block;margin-right:1rem;">
                            <label for="filter_source" class="form-label">Source</label>
                            <select name="filter_source" id="filter_source" class="form-control">
                                <option value="">All Sources</option>
                                <?php foreach (['Online', 'Phone', 'In-person'] as $source): ?>
                                    <option value="<?= $source ?>" <?= (isset($_GET['filter_source']) && $_GET['filter_source'] === $source) ? 'selected' : '' ?>><?= $source ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-actions" style="display:inline-block;vertical-align:bottom;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filters</button>
                            <a href="reservations.php?action=list" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Reservations Table Section -->
        <div class="table-section">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>People</th>
                            <th>Status</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($reservations)): ?>
                            <?php foreach ($reservations as $res): ?>
                                <tr>
                                    <td><?= sanitizeInput($res['id']) ?></td>
                                    <td><?= sanitizeInput($res['client_name']) ?></td>
                                    <td><?= sanitizeInput($res['client_email']) ?></td>
                                    <td><?= date('M j, Y', strtotime($res['reservation_date'])) ?></td>
                                    <td><?= date('g:i A', strtotime($res['reservation_time'])) ?></td>
                                    <td><?= sanitizeInput($res['number_of_people']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= strtolower($res['status']) ?>">
                                            <?= ucfirst($res['status']) ?>
                                        </span>
                                    </td>
                                    <td class="actions-cell">
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-edit" title="Edit Reservation" data-id="<?= $res['id'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-delete" title="Delete Reservation" data-id="<?= $res['id'] ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <button class="btn btn-sm btn-notes" title="View Notes" data-id="<?= $res['id'] ?>">
                                                <i class="fas fa-sticky-note"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="no-data">
                                    <div class="empty-state">
                                        <i class="fas fa-calendar-times"></i>
                                        <h3>No Reservations Found</h3>
                                        <p>Start by adding your first reservation.</p>
                                        <button class="btn btn-success" data-bs-toggle="offcanvas" data-bs-target="#createReservationOffcanvas">
                                            <i class="fas fa-plus"></i> Add Reservation
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create Reservation Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="createReservationOffcanvas" aria-labelledby="createReservationOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="createReservationOffcanvasLabel">
                <i class="fas fa-plus me-2"></i>
                Add New Reservation
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="form-section">
                <form method="POST" action="reservations.php?action=add" class="reservation-form">
                    <div class="form-card">
                        <div class="card-header">
                            <h3>Reservation Details</h3>
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <div class="card-content">
                            <div class="form-group">
                                <label for="create_client_name" class="form-label">Name <span class="required">*</span></label>
                                <input type="text" name="client_name" id="create_client_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="create_client_email" class="form-label">Email <span class="required">*</span></label>
                                <input type="email" name="client_email" id="create_client_email" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="create_phone_number" class="form-label">Phone Number <span class="required">*</span></label>
                                <input type="tel" name="phone_number" id="create_phone_number" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="create_reservation_source" class="form-label">Source <span class="required">*</span></label>
                                <select name="reservation_source" id="create_reservation_source" class="form-control" required>
                                    <option value="">Select Source</option>
                                    <option value="Online">Online</option>
                                    <option value="Phone">Phone</option>
                                    <option value="In-person">In-person</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="create_reservation_date" class="form-label">Date <span class="required">*</span></label>
                                <input type="date" name="reservation_date" id="create_reservation_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="form-group">
                                <label for="create_reservation_time" class="form-label">Time <span class="required">*</span></label>
                                <input type="time" name="reservation_time" id="create_reservation_time" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="create_number_of_people" class="form-label">People <span class="required">*</span></label>
                                <input type="number" name="number_of_people" id="create_number_of_people" class="form-control" required min="1">
                            </div>
                            <div class="form-group">
                                <label for="create_confirmation_number" class="form-label">Confirmation Number</label>
                                <input type="text" name="confirmation_number" id="create_confirmation_number" class="form-control" placeholder="Auto-generated">
                            </div>
                            <div class="form-group">
                                <label for="create_status" class="form-label">Status <span class="required">*</span></label>
                                <select name="status" id="create_status" class="form-control" required>
                                    <option value="">Select Status</option>
                                    <?php foreach (['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'] as $stat): ?>
                                        <option value="<?= $stat ?>"><?= $stat ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="create_assigned_to" class="form-label">Assign To</label>
                                <select name="assigned_to" id="create_assigned_to" class="form-control">
                                    <option value="">Not Assigned</option>
                                    <?php foreach ($staff as $emp): ?>
                                        <option value="<?= $emp['id'] ?>"><?= sanitizeInput($emp['username']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="create_notes" class="form-label">Notes</label>
                                <textarea name="notes" id="create_notes" class="form-control" rows="2" placeholder="Special notes..."></textarea>
                            </div>
                            <div class="form-group">
                                <label for="create_message" class="form-label">Message</label>
                                <input type="text" name="message" id="create_message" class="form-control" placeholder="Add message...">
                            </div>
                        </div>
                    </div>
                    <div class="form-actions" style="margin-top:1.5rem;display:flex;gap:1rem;justify-content:flex-end;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="offcanvas">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Reservation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Reservation Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="editReservationOffcanvas" aria-labelledby="editReservationOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="editReservationOffcanvasLabel">
                <i class="fas fa-edit me-2"></i>
                Edit Reservation
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" id="editForm" action="reservations.php?action=edit&id=0" class="row g-3">
                <input type="hidden" name="id" id="edit_id">
                <div class="col-md-6">
                    <label for="edit_client_name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="client_name" id="edit_client_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label for="edit_client_email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="client_email" id="edit_client_email" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label for="edit_phone_number" class="form-label">Phone Number <span class="text-danger">*</span></label>
                    <input type="tel" name="phone_number" id="edit_phone_number" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label for="edit_reservation_source" class="form-label">Source <span class="text-danger">*</span></label>
                    <select name="reservation_source" id="edit_reservation_source" class="form-select" required>
                        <option value="">Select Source</option>
                        <?php foreach (['Online', 'Phone', 'In-person'] as $source): ?>
                            <option value="<?= $source ?>"><?= $source ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="edit_reservation_date" class="form-label">Date <span class="text-danger">*</span></label>
                    <input type="date" name="reservation_date" id="edit_reservation_date" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label for="edit_reservation_time" class="form-label">Time <span class="text-danger">*</span></label>
                    <input type="time" name="reservation_time" id="edit_reservation_time" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label for="edit_number_of_people" class="form-label">People <span class="text-danger">*</span></label>
                    <input type="number" name="number_of_people" id="edit_number_of_people" class="form-control" required min="1">
                </div>
                <div class="col-md-3">
                    <label for="edit_confirmation_number" class="form-label">Confirmation Number</label>
                    <input type="text" name="confirmation_number" id="edit_confirmation_number" class="form-control">
                </div>
                <div class="col-md-6">
                    <label for="edit_status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" id="edit_status" class="form-select" required>
                        <option value="">Select Status</option>
                        <?php foreach (['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'] as $stat): ?>
                            <option value="<?= $stat ?>"><?= $stat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="edit_assigned_to" class="form-label">Assign To</label>
                    <select name="assigned_to" id="edit_assigned_to" class="form-select">
                        <option value="">Not Assigned</option>
                        <?php foreach ($staff as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= sanitizeInput($emp['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="edit_notes" class="form-label">Notes</label>
                    <textarea name="notes" id="edit_notes" class="form-control" rows="2" placeholder="Special notes..."></textarea>
                </div>
                <div class="col-md-6">
                    <label for="edit_message" class="form-label">Message</label>
                    <input type="text" name="message" id="edit_message" class="form-control" placeholder="Add message...">
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Update
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="offcanvas">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Notes Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="viewNotesOffcanvas" aria-labelledby="viewNotesOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="viewNotesOffcanvasLabel">
                <i class="fas fa-sticky-note me-2"></i>
                Reservation Notes
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="list-group" id="notesList">
                <li class="list-group-item">Loading notes...</li>
            </ul>
        </div>
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
                $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Reservation not found.'];
                header("Location: reservations.php?action=list");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'message' => 'Error: ' . sanitizeInput($e->getMessage())];
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

<script>
$(document).ready(function() {
    <?php if ($action === 'list'): ?>
        // Initialize DataTable
        $('#reservationsTable').DataTable({
            "paging": true,
            "searching": true,
            "info": true,
            "order": [[0, "desc"]],
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
                    text: '<i class="fas fa-plus"></i> Add Reservation',
                    className: 'btn btn-success btn-sm',
                    action: function() {
                        $('#createReservationOffcanvas').offcanvas('show');
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
                        location.reload();
                    }
                }, 'json').fail(() => {
                    showToast('Error updating status.', 'danger');
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
                    location.reload();
                }
            }, 'json').fail(() => {
                showToast('Error assigning staff.', 'danger');
                location.reload();
            });
        });

        // Handle View Notes
        $(document).on('click', '.view-notes-btn', function() {
            let id = $(this).data('id');
            $('#notesList').html('<li class="list-group-item">Loading notes...</li>');
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
                        $('#notesList').html('<li class="list-group-item">No notes available.</li>');
                    }
                } else {
                    $('#notesList').html(`<li class="list-group-item text-danger">${response.message}</li>`);
                }
            }, 'json').fail(() => {
                $('#notesList').html('<li class="list-group-item text-danger">Error loading notes.</li>');
            });
        });

        // Handle Delete Reservation with SweetAlert2
        $(document).on('click', '.delete-reservation-btn', function() {
            let id = $(this).data('id');
            let client = $(this).data('client');
            Swal.fire({
                title: 'Are you sure?',
                text: `Do you want to delete the reservation for "${client}"? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'reservations.php?action=delete&id=' + id;
                }
            });
        });

        // Function to show toast notifications
        function showToast(message, type = 'primary') {
            let toastHtml = `
                <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
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

        // Show toast from PHP session
        <?php if (isset($_SESSION['toast'])): ?>
            showToast(`<?= $_SESSION['toast']['message'] ?>`, '<?= $_SESSION['toast']['type'] === 'success' ? 'success' : 'danger' ?>');
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>
    <?php endif; ?>
});
</script>