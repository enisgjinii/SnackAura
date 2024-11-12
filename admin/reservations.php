<?php
// session_start();
include 'includes/header.php';
$host = '127.0.0.1';
$db   = 'dbfood';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    $response = ['status' => 'error', 'message' => 'Unknown error'];
    if ($_POST['ajax_action'] === 'update_status') {
        if (isset($_POST['id']) && isset($_POST['status'])) {
            $id = (int)$_POST['id'];
            $status = $_POST['status'];
            $valid_statuses = ['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'];
            if (in_array($status, $valid_statuses)) {
                try {
                    $stmt = $pdo->prepare('UPDATE reservations SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                    $stmt->execute([$status, $id]);
                    $note = "Status updated to '$status'. ";
                    $stmt = $pdo->prepare('UPDATE reservations SET notes = CONCAT(notes, ?) WHERE id = ?');
                    $stmt->execute([$note, $id]);
                    $response = ['status' => 'success', 'message' => 'Status updated successfully'];
                } catch (PDOException $e) {
                    $response = ['status' => 'error', 'message' => $e->getMessage()];
                }
            } else {
                $response = ['status' => 'error', 'message' => 'Invalid status'];
            }
        }
    } elseif ($_POST['ajax_action'] === 'assign_staff') {
        if (isset($_POST['id']) && isset($_POST['assigned_to'])) {
            $id = (int)$_POST['id'];
            $assigned_to = $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : NULL;
            try {
                if ($assigned_to !== NULL) {
                    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
                    $stmt->execute([$assigned_to]);
                    $user = $stmt->fetch();
                    if (!$user) {
                        $response = ['status' => 'error', 'message' => 'Employee not found or inactive'];
                        echo json_encode($response);
                        exit;
                    }
                }
                $stmt = $pdo->prepare('UPDATE reservations SET assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute([$assigned_to, $id]);
                if ($assigned_to !== NULL) {
                    $assignment = "Assigned to '{$user['username']}'. ";
                } else {
                    $assignment = "Assignment removed. ";
                }
                $stmt = $pdo->prepare('UPDATE reservations SET notes = CONCAT(notes, ?) WHERE id = ?');
                $stmt->execute([$assignment, $id]);
                $response = ['status' => 'success', 'message' => 'Assignment updated successfully'];
            } catch (PDOException $e) {
                $response = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }
    }
    echo json_encode($response);
    exit;
}
$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
if (($action === 'add' || $action === 'edit') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name = trim($_POST['client_name']);
    $client_email = trim($_POST['client_email']);
    $phone_number = trim($_POST['phone_number']);
    $reservation_date = trim($_POST['reservation_date']);
    $reservation_time = trim($_POST['reservation_time']);
    $number_of_people = (int)$_POST['number_of_people'];
    $reservation_source = $_POST['reservation_source'];
    $confirmation_number = trim($_POST['confirmation_number']);
    $status = $_POST['status'];
    $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : NULL;
    $notes = trim($_POST['notes']);
    $message_field = trim($_POST['message']);
    if ($client_name === '' || $client_email === '' || $phone_number === '' || $reservation_date === '' || $reservation_time === '' || $number_of_people <= 0 || $reservation_source === '' || $status === '') {
        $message = '<div class="alert alert-danger">All required fields must be filled.</div>';
    } elseif (!filter_var($client_email, FILTER_VALIDATE_EMAIL)) {
        $message = '<div class="alert alert-danger">Invalid email address.</div>';
    } elseif (!in_array($status, ['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'])) {
        $message = '<div class="alert alert-danger">Invalid status.</div>';
    } else {
        if (empty($confirmation_number)) {
            $confirmation_number = strtoupper(substr(md5(uniqid(rand(), true)), 0, 10));
        }
        if ($action === 'add') {
            $sql = 'INSERT INTO reservations (client_name, client_email, phone_number, reservation_date, reservation_time, number_of_people, reservation_source, confirmation_number, status, assigned_to, notes, message) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $params = [$client_name, $client_email, $phone_number, $reservation_date, $reservation_time, $number_of_people, $reservation_source, $confirmation_number, $status, $assigned_to, $notes, $message_field];
        } else {
            $sql = 'UPDATE reservations SET client_name = ?, client_email = ?, phone_number = ?, reservation_date = ?, reservation_time = ?, number_of_people = ?, reservation_source = ?, confirmation_number = ?, status = ?, assigned_to = ?, notes = ?, message = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?';
            $params = [$client_name, $client_email, $phone_number, $reservation_date, $reservation_time, $number_of_people, $reservation_source, $confirmation_number, $status, $assigned_to, $notes, $message_field, $id];
        }
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            header('Location: reservations.php?action=view&message=success');
            exit();
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Error processing data: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
if ($action === 'edit') {
    $stmt = $pdo->prepare('SELECT * FROM reservations WHERE id = ?');
    $stmt->execute([$id]);
    $reservation = $stmt->fetch();
    if (!$reservation) {
        echo '<div class="alert alert-danger">Reservation not found.</div>';
        exit;
    }
}
if ($action === 'delete') {
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM reservations WHERE id = ?');
            $stmt->execute([$id]);
            header('Location: reservations.php?action=view&message=deleted');
            exit();
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Error deleting reservation: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Invalid reservation ID.</div>';
    }
}
function getReservations($pdo, $filters = [])
{
    try {
        $query = 'SELECT r.*, u.username AS assigned_username FROM reservations r LEFT JOIN users u ON r.assigned_to = u.id';
        $conditions = [];
        $params = [];
        if (!empty($filters['status'])) {
            $conditions[] = 'r.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['source'])) {
            $conditions[] = 'r.reservation_source = :source';
            $params[':source'] = $filters['source'];
        }
        if (!empty($conditions)) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $query .= ' ORDER BY r.reservation_date DESC, r.reservation_time DESC';
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Reservations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4@5/bootstrap-4.min.css" rel="stylesheet">
    <style>
        .select2-container--bootstrap5 .select2-selection--single {
            height: 38px;
            padding: 6px 12px;
        }
    </style>
</head>

<body>
    <div class="container mt-4">
        <?php if ($action === 'add' || $action === 'edit'): ?>
            <h2 class="mb-4"><?= $action === 'add' ? 'Add Reservation' : 'Edit Reservation' ?></h2>
            <?php if ($message): ?>
                <?= $message ?>
            <?php endif; ?>
            <form method="POST" action="reservations.php?action=<?= $action ?><?= $action === 'edit' ? '&id=' . $id : '' ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="client_name" class="form-label">Name <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="client_name" name="client_name" required value="<?= htmlspecialchars($action === 'edit' ? $reservation['client_name'] : ($_POST['client_name'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="client_email" class="form-label">Email <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="client_email" name="client_email" required value="<?= htmlspecialchars($action === 'edit' ? $reservation['client_email'] : ($_POST['client_email'] ?? '')) ?>">
                        </div>
                    </div>
                </div>
                <div class="row g-3 mt-3">
                    <div class="col-md-6">
                        <label for="phone_number" class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="tel" class="form-control" id="phone_number" name="phone_number" required value="<?= htmlspecialchars($action === 'edit' ? $reservation['phone_number'] : ($_POST['phone_number'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="reservation_source" class="form-label">Reservation Source <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                            <select class="form-select" id="reservation_source" name="reservation_source" required>
                                <option value="">Select Source</option>
                                <option value="Online" <?= ($action === 'edit' && $reservation['reservation_source'] === 'Online') ? 'selected' : (($_POST['reservation_source'] ?? '') === 'Online' ? 'selected' : '') ?>>Online</option>
                                <option value="Phone" <?= ($action === 'edit' && $reservation['reservation_source'] === 'Phone') ? 'selected' : (($_POST['reservation_source'] ?? '') === 'Phone' ? 'selected' : '') ?>>Phone</option>
                                <option value="In-person" <?= ($action === 'edit' && $reservation['reservation_source'] === 'In-person') ? 'selected' : (($_POST['reservation_source'] ?? '') === 'In-person' ? 'selected' : '') ?>>In-person</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mt-3">
                    <div class="col-md-6">
                        <label for="reservation_date" class="form-label">Reservation Date <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            <input type="date" class="form-control" id="reservation_date" name="reservation_date" required min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($action === 'edit' ? $reservation['reservation_date'] : ($_POST['reservation_date'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="reservation_time" class="form-label">Reservation Time <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-clock"></i></span>
                            <input type="time" class="form-control" id="reservation_time" name="reservation_time" required value="<?= htmlspecialchars($action === 'edit' ? $reservation['reservation_time'] : ($_POST['reservation_time'] ?? '')) ?>">
                        </div>
                    </div>
                </div>
                <div class="row g-3 mt-3">
                    <div class="col-md-6">
                        <label for="number_of_people" class="form-label">Number of People <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-users"></i></span>
                            <input type="number" class="form-control" id="number_of_people" name="number_of_people" min="1" required value="<?= htmlspecialchars($action === 'edit' ? $reservation['number_of_people'] : ($_POST['number_of_people'] ?? '1')) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="confirmation_number" class="form-label">Confirmation Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                            <input type="text" class="form-control" id="confirmation_number" name="confirmation_number" value="<?= htmlspecialchars($action === 'edit' ? $reservation['confirmation_number'] : ($_POST['confirmation_number'] ?? '')) ?>">
                        </div>
                    </div>
                </div>
                <div class="row g-3 mt-3">
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-info-circle"></i></span>
                            <select class="form-select" id="status" name="status" required>
                                <option value="">Select Status</option>
                                <?php foreach (['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'] as $stat): ?>
                                    <option value="<?= htmlspecialchars($stat) ?>" <?= ($action === 'edit' && $reservation['status'] === $stat) ? 'selected' : (($_POST['status'] ?? '') === $stat ? 'selected' : '') ?>><?= htmlspecialchars($stat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="assigned_to" class="form-label">Assign to</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                            <select class="form-select" id="assigned_to" name="assigned_to">
                                <option value="">Assign Employee</option>
                                <?php foreach ($staff as $employee): ?>
                                    <option value="<?= htmlspecialchars($employee['id']) ?>" <?= ($action === 'edit' && $reservation['assigned_to'] == $employee['id']) ? 'selected' : (($_POST['assigned_to'] ?? '') == $employee['id'] ? 'selected' : '') ?>><?= htmlspecialchars($employee['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mt-3">
                    <div class="col-md-6">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Add any special notes..."><?= htmlspecialchars($action === 'edit' ? $reservation['notes'] : ($_POST['notes'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="message" class="form-label">Message</label>
                        <input type="text" class="form-control" id="message" name="message" placeholder="Enter a message..." value="<?= htmlspecialchars($action === 'edit' ? $reservation['message'] : ($_POST['message'] ?? '')) ?>">
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-success me-2"><i class="fas fa-save"></i> <?= $action === 'add' ? 'Add' : 'Update' ?> Reservation</button>
                    <a href="reservations.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </form>
        <?php elseif ($action === 'view'): ?>
            <h2 class="mb-4">Manage Reservations</h2>
            <?php if ($message): ?>
                <?= $message ?>
            <?php endif; ?>
            <form method="GET" action="reservations.php" class="row g-3 mb-4">
                <input type="hidden" name="action" value="view">
                <div class="col-md-3">
                    <label for="filter_status" class="form-label">Status</label>
                    <select class="form-select" id="filter_status" name="filter_status">
                        <option value="">All Statuses</option>
                        <?php foreach (['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'] as $stat): ?>
                            <option value="<?= htmlspecialchars($stat) ?>" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] === $stat) ? 'selected' : '' ?>><?= htmlspecialchars($stat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filter_source" class="form-label">Reservation Source</label>
                    <select class="form-select" id="filter_source" name="filter_source">
                        <option value="">All Sources</option>
                        <option value="Online" <?= (isset($_GET['filter_source']) && $_GET['filter_source'] === 'Online') ? 'selected' : '' ?>>Online</option>
                        <option value="Phone" <?= (isset($_GET['filter_source']) && $_GET['filter_source'] === 'Phone') ? 'selected' : '' ?>>Phone</option>
                        <option value="In-person" <?= (isset($_GET['filter_source']) && $_GET['filter_source'] === 'In-person') ? 'selected' : '' ?>>In-person</option>
                    </select>
                </div>
                <div class="col-md-3 align-self-end">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="reservations.php?action=view" class="btn btn-secondary">Clear Filters</a>
                </div>
            </form>
            <div class="mb-3">
                <a href="reservations.php?action=add" class="btn btn-success"><i class="fas fa-plus"></i> Add Reservation</a>
            </div>
            <div class="table-responsive">
                <table id="reservationsTable" class="table table-striped table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Client Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>People</th>
                            <th>Source</th>
                            <th>Confirmation No.</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Notes</th>
                            <th>Message</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $filters = [];
                        if (isset($_GET['filter_status']) && in_array($_GET['filter_status'], ['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'])) {
                            $filters['status'] = $_GET['filter_status'];
                        }
                        if (isset($_GET['filter_source']) && in_array($_GET['filter_source'], ['Online', 'Phone', 'In-person'])) {
                            $filters['source'] = $_GET['filter_source'];
                        }
                        $reservations = getReservations($pdo, $filters);
                        if (!empty($reservations)):
                            foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td><?= htmlspecialchars($reservation['id']) ?></td>
                                    <td><?= htmlspecialchars($reservation['client_name']) ?></td>
                                    <td><?= htmlspecialchars($reservation['client_email']) ?></td>
                                    <td><?= htmlspecialchars($reservation['phone_number']) ?></td>
                                    <td><?= htmlspecialchars($reservation['reservation_date']) ?></td>
                                    <td><?= htmlspecialchars($reservation['reservation_time']) ?></td>
                                    <td><?= htmlspecialchars($reservation['number_of_people']) ?></td>
                                    <td><?= htmlspecialchars($reservation['reservation_source']) ?></td>
                                    <td><?= htmlspecialchars($reservation['confirmation_number']) ?></td>
                                    <td>
                                        <select class="form-select form-select-sm status-dropdown" data-id="<?= $reservation['id'] ?>">
                                            <option value="">Select Status</option>
                                            <?php foreach (['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'] as $stat): ?>
                                                <option value="<?= htmlspecialchars($stat) ?>" <?= ($reservation['status'] === $stat) ? 'selected' : '' ?>><?= htmlspecialchars($stat) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm assign-dropdown" data-id="<?= $reservation['id'] ?>">
                                            <option value="">Assign</option>
                                            <?php
                                            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE role IN ('admin', 'waiter', 'delivery') AND is_active = 1");
                                            $stmt->execute();
                                            $staff = $stmt->fetchAll();
                                            foreach ($staff as $employee): ?>
                                                <option value="<?= htmlspecialchars($employee['id']) ?>" <?= ($reservation['assigned_to'] == $employee['id']) ? 'selected' : '' ?>><?= htmlspecialchars($employee['username']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><?= htmlspecialchars($reservation['notes']) ?></td>
                                    <td><?= htmlspecialchars($reservation['message']) ?></td>
                                    <td><?= htmlspecialchars($reservation['created_at']) ?></td>
                                    <td>
                                        <a href="reservations.php?action=edit&id=<?= $reservation['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                        <a href="reservations.php?action=delete&id=<?= $reservation['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this reservation?')"><i class="fas fa-trash-alt"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach;
                        else: ?>
                            <tr>
                                <td colspan="15" class="text-center">No reservations found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            $('#reservationsTable').DataTable({
                responsive: true,
                order: [
                    [0, 'desc']
                ],
                columnDefs: [{
                    orderable: false,
                    targets: [9, 10, 11, 12, 14]
                }],
                language: {
                    "emptyTable": "No reservations found",
                    "info": "Showing _START_ to _END_ of _TOTAL_ reservations",
                    "infoEmpty": "Showing 0 to 0 of 0 reservations",
                    "lengthMenu": "Show _MENU_ reservations",
                    "paginate": {
                        "first": "First",
                        "last": "Last",
                        "next": "Next",
                        "previous": "Previous"
                    },
                    "search": "Search:"
                }
            });
            $('.status-dropdown').select2({
                theme: 'bootstrap5',
                minimumResultsForSearch: -1
            });
            $('.assign-dropdown').select2({
                theme: 'bootstrap5',
                placeholder: 'Assign Employee',
                allowClear: true
            });
            $('.status-dropdown').on('change', function() {
                var id = $(this).data('id');
                var status = $(this).val();
                $.ajax({
                    url: 'reservations.php',
                    type: 'POST',
                    data: {
                        ajax_action: 'update_status',
                        id: id,
                        status: status
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Status updated',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred while updating status.'
                        });
                    }
                });
            });
            $('.assign-dropdown').on('change', function() {
                var id = $(this).data('id');
                var assigned_to = $(this).val();
                $.ajax({
                    url: 'reservations.php',
                    type: 'POST',
                    data: {
                        ajax_action: 'assign_staff',
                        id: id,
                        assigned_to: assigned_to
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Assignment updated',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred while assigning staff.'
                        });
                    }
                });
            });
        });
    </script>
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4@5/bootstrap-4.min.css" rel="stylesheet">
</body>

</html>