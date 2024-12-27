<?php
// reservations.php

// Include the database connection
include 'includes/db_connect.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'Unknown error'];

    if ($_POST['ajax_action'] === 'update_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $valid_statuses = ['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'];

        if ($id > 0 && in_array($status, $valid_statuses)) {
            try {
                $pdo->beginTransaction();
                $current_time = date('Y-m-d H:i:s');
                $stmt = $pdo->prepare('UPDATE reservations SET status = ?, updated_at = NOW(), notes = CONCAT(IFNULL(notes, ""), ?, ". ") WHERE id = ?');
                $note = "[$current_time] Status updated to '$status'";
                $stmt->execute([$status, $note, $id]);
                $pdo->commit();
                $response = ['status' => 'success', 'message' => 'Status updated successfully'];
            } catch (PDOException $e) {
                $pdo->rollBack();
                $response = ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'Invalid ID or status'];
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
                    if (!$user) throw new Exception('Employee not found or inactive');
                    $assignment = "[$current_time] Assigned to '{$user['username']}'";
                } else {
                    $assignment = "[$current_time] Assignment removed";
                }

                $stmt = $pdo->prepare('UPDATE reservations SET assigned_to = ?, updated_at = NOW(), notes = CONCAT(IFNULL(notes, ""), ?, ". ") WHERE id = ?');
                $stmt->execute([$assigned_to, $assignment, $id]);
                $pdo->commit();
                $response = ['status' => 'success', 'message' => 'Assignment updated successfully'];
            } catch (Exception $e) {
                $pdo->rollBack();
                $response = ['status' => 'error', 'message' => 'Error: ' . $e->getMessage()];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'Invalid reservation ID'];
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
                    $response = ['status' => 'error', 'message' => 'Reservation not found'];
                }
            } catch (PDOException $e) {
                $response = ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'Invalid reservation ID'];
        }
    }

    echo json_encode($response);
    exit;
}

// Include the header after handling AJAX
include 'includes/header.php';

// Determine the current action
$action = $_GET['action'] ?? 'view';
$id = (int)($_GET['id'] ?? 0);
$message = '';

// Handle Add/Edit forms submission
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

    // Validate required fields
    if (empty($client_name) || empty($client_email) || empty($phone_number) || empty($reservation_date) || empty($reservation_time) || $number_of_people <= 0 || empty($reservation_source) || empty($status)) {
        $message = '<div class="alert alert-danger">All required fields must be filled.</div>';
    } elseif (!filter_var($client_email, FILTER_VALIDATE_EMAIL)) {
        $message = '<div class="alert alert-danger">Invalid email address.</div>';
    } elseif (!in_array($status, ['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'])) {
        $message = '<div class="alert alert-danger">Invalid status.</div>';
    } else {
        // Generate a confirmation number if not provided
        if (empty($confirmation_number)) {
            $confirmation_number = strtoupper(substr(md5(uniqid(rand(), true)), 0, 10));
        }

        // Prepare SQL and parameters based on action
        if ($action === 'add') {
            $sql = 'INSERT INTO reservations (client_name, client_email, phone_number, reservation_date, reservation_time, number_of_people, reservation_source, confirmation_number, status, assigned_to, notes, message) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $params = [$client_name, $client_email, $phone_number, $reservation_date, $reservation_time, $number_of_people, $reservation_source, $confirmation_number, $status, $assigned_to, $notes, $message_field];
        } else { // Edit
            $sql = 'UPDATE reservations SET client_name = ?, client_email = ?, phone_number = ?, reservation_date = ?, reservation_time = ?, number_of_people = ?, reservation_source = ?, confirmation_number = ?, status = ?, assigned_to = ?, notes = ?, message = ?, updated_at = NOW() WHERE id = ?';
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

// Fetch reservation details for editing
if ($action === 'edit') {
    $stmt = $pdo->prepare('SELECT * FROM reservations WHERE id = ?');
    $stmt->execute([$id]);
    $reservation = $stmt->fetch();
    if (!$reservation) {
        $message = '<div class="alert alert-danger">Reservation not found.</div>';
        $action = 'view';
    }
}

// Handle reservation deletion
if ($action === 'delete' && $id > 0) {
    try {
        $stmt = $pdo->prepare('DELETE FROM reservations WHERE id = ?');
        $stmt->execute([$id]);
        header('Location: reservations.php?action=view&message=deleted');
        exit();
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Error deleting reservation: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Function to fetch reservations with optional filters
function getReservations($pdo, $filters = [])
{
    try {
        $query = 'SELECT r.*, u.username AS assigned_username FROM reservations r LEFT JOIN users u ON r.assigned_to = u.id';
        $conditions = [];
        $params = [];

        if (!empty($filters['status'])) {
            $conditions[] = 'r.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['source'])) {
            $conditions[] = 'r.reservation_source = ?';
            $params[] = $filters['source'];
        }

        if ($conditions) {
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

// Fetch active staff members to populate the Assign dropdown
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE role IN ('admin', 'waiter', 'delivery') AND is_active = 1");
$stmt->execute();
$staff = $stmt->fetchAll();
?>

<body>
    <div class="container mt-4">
        <?php if ($action === 'add' || $action === 'edit'): ?>
            <h2 class="mb-4"><?= $action === 'add' ? 'Add Reservation' : 'Edit Reservation' ?></h2>
            <?= $message ?>
            <form method="POST" action="reservations.php?action=<?= $action ?><?= $action === 'edit' ? '&id=' . $id : '' ?>" class="row g-2">
                <!-- Row 1: Name and Email -->
                <div class="col-md-6">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" name="client_name" required value="<?= htmlspecialchars($action === 'edit' ? $reservation['client_name'] : ($_POST['client_name'] ?? '')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" class="form-control" name="client_email" required value="<?= htmlspecialchars($action === 'edit' ? $reservation['client_email'] : ($_POST['client_email'] ?? '')) ?>">
                    </div>
                </div>
                <!-- Row 2: Phone and Source -->
                <div class="col-md-6">
                    <label class="form-label">Phone <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                        <input type="tel" class="form-control" name="phone_number" required value="<?= htmlspecialchars($action === 'edit' ? $reservation['phone_number'] : ($_POST['phone_number'] ?? '')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Source <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                        <select class="form-select" name="reservation_source" required>
                            <option value="">Select Source</option>
                            <?php foreach (['Online', 'Phone', 'In-person'] as $source): ?>
                                <option value="<?= $source ?>" <?= ($action === 'edit' && $reservation['reservation_source'] === $source) || (($_POST['reservation_source'] ?? '') === $source) ? 'selected' : '' ?>><?= $source ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <!-- Row 3: Date, Time, People, Confirmation Number -->
                <div class="col-md-3">
                    <label class="form-label">Date <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                        <input type="date" class="form-control" name="reservation_date" required min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($action === 'edit' ? $reservation['reservation_date'] : ($_POST['reservation_date'] ?? '')) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Time <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fas fa-clock"></i></span>
                        <input type="time" class="form-control" name="reservation_time" required value="<?= htmlspecialchars($action === 'edit' ? $reservation['reservation_time'] : ($_POST['reservation_time'] ?? '')) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">People <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fas fa-users"></i></span>
                        <input type="number" class="form-control" name="number_of_people" min="1" required value="<?= htmlspecialchars($action === 'edit' ? $reservation['number_of_people'] : ($_POST['number_of_people'] ?? '1')) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Confirmation No.</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                        <input type="text" class="form-control" name="confirmation_number" value="<?= htmlspecialchars($action === 'edit' ? $reservation['confirmation_number'] : ($_POST['confirmation_number'] ?? '')) ?>">
                    </div>
                </div>
                <!-- Row 4: Status and Assign To -->
                <div class="col-md-6">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fas fa-info-circle"></i></span>
                        <select class="form-select" name="status" required>
                            <option value="">Select Status</option>
                            <?php foreach (['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'] as $stat): ?>
                                <option value="<?= $stat ?>" <?= ($action === 'edit' && $reservation['status'] === $stat) || (($_POST['status'] ?? '') === $stat) ? 'selected' : '' ?>><?= $stat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Assign To</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                        <select class="form-select" name="assigned_to">
                            <option value="">Assign Employee</option>
                            <?php foreach ($staff as $employee): ?>
                                <option value="<?= $employee['id'] ?>" <?= ($action === 'edit' && $reservation['assigned_to'] == $employee['id']) || (($_POST['assigned_to'] ?? '') == $employee['id']) ? 'selected' : '' ?>><?= htmlspecialchars($employee['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <!-- Row 5: Notes and Message -->
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control form-control-sm" name="notes" rows="2" placeholder="Add any special notes..."><?= htmlspecialchars($action === 'edit' ? $reservation['notes'] : ($_POST['notes'] ?? '')) ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Message</label>
                    <input type="text" class="form-control form-control-sm" name="message" placeholder="Enter a message..." value="<?= htmlspecialchars($action === 'edit' ? $reservation['message'] : ($_POST['message'] ?? '')) ?>">
                </div>
                <!-- Form Buttons -->
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-success btn-sm me-2"><i class="fas fa-save"></i> <?= $action === 'add' ? 'Add' : 'Update' ?> Reservation</button>
                    <a href="reservations.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </form>
        <?php else: ?>
            <h2 class="mb-4">Manage Reservations</h2>
            <?= $message ?>
            <!-- Filter Form -->
            <form method="GET" action="reservations.php" class="row g-2 mb-3">
                <input type="hidden" name="action" value="view">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select form-select-sm" name="filter_status">
                        <option value="">All Statuses</option>
                        <?php foreach (['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'] as $stat): ?>
                            <option value="<?= $stat ?>" <?= (($_GET['filter_status'] ?? '') === $stat) ? 'selected' : '' ?>><?= $stat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Source</label>
                    <select class="form-select form-select-sm" name="filter_source">
                        <option value="">All Sources</option>
                        <?php foreach (['Online', 'Phone', 'In-person'] as $source): ?>
                            <option value="<?= $source ?>" <?= (($_GET['filter_source'] ?? '') === $source) ? 'selected' : '' ?>><?= $source ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 align-self-end">
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    <a href="reservations.php?action=view" class="btn btn-secondary btn-sm">Clear</a>
                </div>
            </form>
            <!-- Add Reservation Button -->
            <div class="mb-3">
                <a href="reservations.php?action=add" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Add Reservation</a>
            </div>
            <!-- Reservations Table -->
            <div class="table-responsive">
                <table id="reservationsTable" class="table table-striped table-hover table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>People</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $filters = [];
                        if (!empty($_GET['filter_status']) && in_array($_GET['filter_status'], ['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'])) {
                            $filters['status'] = $_GET['filter_status'];
                        }
                        if (!empty($_GET['filter_source']) && in_array($_GET['filter_source'], ['Online', 'Phone', 'In-person'])) {
                            $filters['source'] = $_GET['filter_source'];
                        }
                        $reservations = getReservations($pdo, $filters);
                        if ($reservations):
                            foreach ($reservations as $res): ?>
                                <tr>
                                    <td><?= $res['id'] ?></td>
                                    <td><?= htmlspecialchars($res['client_name']) ?></td>
                                    <td><?= htmlspecialchars($res['reservation_date']) ?></td>
                                    <td><?= htmlspecialchars($res['reservation_time']) ?></td>
                                    <td><?= $res['number_of_people'] ?></td>
                                    <td>
                                        <select class="form-select form-select-sm status-dropdown" data-id="<?= $res['id'] ?>">
                                            <option value="">Select</option>
                                            <?php foreach (['Pending', 'Confirmed', 'Cancelled', 'Completed', 'No-show'] as $stat): ?>
                                                <option value="<?= $stat ?>" <?= ($res['status'] === $stat) ? 'selected' : '' ?>><?= $stat ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm assign-dropdown" data-id="<?= $res['id'] ?>">
                                            <option value="">Assign</option>
                                            <?php foreach ($staff as $emp): ?>
                                                <option value="<?= $emp['id'] ?>" <?= ($res['assigned_to'] == $emp['id']) ? 'selected' : '' ?>><?= htmlspecialchars($emp['username']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <a href="reservations.php?action=edit&id=<?= $res['id'] ?>" class="btn btn-warning btn-sm me-1" title="Edit"><i class="fas fa-edit"></i></a>
                                        <button type="button" class="btn btn-info btn-sm me-1 view-notes-btn" data-id="<?= $res['id'] ?>" title="View Notes"><i class="fas fa-sticky-note"></i></button>
                                        <a href="reservations.php?action=delete&id=<?= $res['id'] ?>" class="btn btn-danger btn-sm" title="Delete" onclick="return confirm('Delete this reservation?')"><i class="fas fa-trash-alt"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach;
                        else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No reservations found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Off-Canvas for Viewing Notes -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="notesOffcanvas">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Reservation Notes</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="list-group" id="notesList">
                <li class="list-group-item">Loading notes...</li>
            </ul>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="liveToast" class="toast align-items-center text-bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastBody">
                    Hello, world! This is a toast message.
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Font Awesome JS -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#reservationsTable').DataTable({
                responsive: true,
                order: [
                    [0, 'desc']
                ],
                columnDefs: [{
                    orderable: false,
                    targets: [5, 6, 7]
                }],
                language: {
                    "emptyTable": "No reservations found",
                    "info": "Showing _START_ to _END_ of _TOTAL_ reservations",
                    "lengthMenu": "Show _MENU_ reservations",
                    "paginate": {
                        "next": "Next",
                        "previous": "Previous"
                    }
                }
            });

            // Initialize Toast
            var toastEl = document.getElementById('liveToast');
            var toast = new bootstrap.Toast(toastEl);

            // Function to show toast
            function showToast(message, type = 'primary') {
                $('#liveToast').removeClass('text-bg-primary text-bg-danger text-bg-success');
                $('#toastBody').text(message);
                $('#liveToast').addClass(`text-bg-${type}`);
                toast.show();
            }

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
                        }
                    }, 'json').fail(() => {
                        showToast('Failed to update status.', 'danger');
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
                    }
                }, 'json').fail(() => {
                    showToast('Failed to assign staff.', 'danger');
                });
            });

            // Handle View Notes
            $(document).on('click', '.view-notes-btn', function() {
                let id = $(this).data('id');
                $('#notesList').html('<li class="list-group-item">Loading notes...</li>');
                let notesOffcanvas = new bootstrap.Offcanvas(document.getElementById('notesOffcanvas'));
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
                    $('#notesList').html('<li class="list-group-item text-danger">Failed to load notes.</li>');
                });
            });
        });
    </script>
</body>

</html>