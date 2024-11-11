<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_entry') {
        $type = $_POST['type'];
        $day_of_week = $type === 'regular' ? $_POST['day_of_week'] : NULL;
        $date = $type === 'holiday' ? $_POST['date'] : NULL;
        $title = $type === 'holiday' ? trim($_POST['title']) : NULL;
        $description = $type === 'holiday' ? trim($_POST['description']) : NULL;
        $is_closed = isset($_POST['is_closed']) ? 1 : 0;
        $open_time = ($type === 'regular' || ($type === 'holiday' && !$is_closed)) ? $_POST['open_time'] : NULL;
        $close_time = ($type === 'regular' || ($type === 'holiday' && !$is_closed)) ? $_POST['close_time'] : NULL;
        $errors = [];
        if ($type === 'holiday') {
            if (empty($date)) $errors[] = "Date is required.";
            if (empty($title)) $errors[] = "Title is required.";
        } elseif ($type === 'regular') {
            if (empty($day_of_week)) $errors[] = "Day of week is required.";
        } else {
            $errors[] = "Invalid entry type.";
        }
        if (!empty($errors)) {
            $error_message = implode(' ', $errors);
        } else {
            $stmt = $pdo->prepare("INSERT INTO operational_hours (type, day_of_week, date, title, description, open_time, close_time, is_closed) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            try {
                $stmt->execute([$type, $day_of_week, $date, $title, $description, $open_time, $close_time, $is_closed]);
                $success_message = ucfirst($type) . " entry added successfully.";
            } catch (Exception $e) {
                $error_message = "Failed to add entry: " . $e->getMessage();
            }
        }
    }
    if ($action === 'edit_entry') {
        $id = $_POST['entry_id'];
        $type = $_POST['type'];
        $day_of_week = $type === 'regular' ? $_POST['day_of_week'] : NULL;
        $date = $type === 'holiday' ? $_POST['date'] : NULL;
        $title = $type === 'holiday' ? trim($_POST['title']) : NULL;
        $description = $type === 'holiday' ? trim($_POST['description']) : NULL;
        $is_closed = isset($_POST['is_closed']) ? 1 : 0;
        $open_time = ($type === 'regular' || ($type === 'holiday' && !$is_closed)) ? $_POST['open_time'] : NULL;
        $close_time = ($type === 'regular' || ($type === 'holiday' && !$is_closed)) ? $_POST['close_time'] : NULL;
        $errors = [];
        if ($type === 'holiday') {
            if (empty($date)) $errors[] = "Date is required.";
            if (empty($title)) $errors[] = "Title is required.";
        } elseif ($type === 'regular') {
            if (empty($day_of_week)) $errors[] = "Day of week is required.";
        } else {
            $errors[] = "Invalid entry type.";
        }
        if (!empty($errors)) {
            $error_message = implode(' ', $errors);
        } else {
            $stmt = $pdo->prepare("UPDATE operational_hours SET type = ?, day_of_week = ?, date = ?, title = ?, description = ?, open_time = ?, close_time = ?, is_closed = ? WHERE id = ?");
            try {
                $stmt->execute([$type, $day_of_week, $date, $title, $description, $open_time, $close_time, $is_closed, $id]);
                $success_message = ucfirst($type) . " entry updated successfully.";
            } catch (Exception $e) {
                $error_message = "Failed to update entry: " . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['delete_entry'])) {
    $delete_id = (int)$_GET['delete_entry'];
    $stmt = $pdo->prepare("DELETE FROM operational_hours WHERE id = ?");
    try {
        $stmt->execute([$delete_id]);
        $success_message = "Entry deleted successfully.";
    } catch (Exception $e) {
        $error_message = "Failed to delete entry: " . $e->getMessage();
    }
}

$stmt = $pdo->prepare("SELECT * FROM operational_hours ORDER BY type, date DESC, day_of_week ASC");
$stmt->execute();
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
$events = [];
$day_map = [
    'Sunday' => 0,
    'Monday' => 1,
    'Tuesday' => 2,
    'Wednesday' => 3,
    'Thursday' => 4,
    'Friday' => 5,
    'Saturday' => 6,
];
foreach ($entries as $entry) {
    if ($entry['type'] === 'regular') {
        if (!$entry['is_closed']) {
            $events[] = [
                'title' => 'Open',
                'daysOfWeek' => [$day_map[$entry['day_of_week']]],
                'startTime' => substr($entry['open_time'], 0, 5),
                'endTime' => substr($entry['close_time'], 0, 5),
                'display' => 'background',
                'color' => '#d4edda',
            ];
        } else {
            $events[] = [
                'title' => 'Closed',
                'daysOfWeek' => [$day_map[$entry['day_of_week']]],
                'allDay' => true,
                'display' => 'background',
                'color' => '#f8d7da',
            ];
        }
    } elseif ($entry['type'] === 'holiday') {
        if ($entry['is_closed']) {
            $events[] = [
                'title' => $entry['title'],
                'start' => $entry['date'],
                'allDay' => true,
                'display' => 'background',
                'color' => '#f5c6cb',
                'description' => $entry['description'],
            ];
        } else {
            $events[] = [
                'title' => $entry['title'],
                'start' => $entry['date'] . 'T' . $entry['open_time'],
                'end' => $entry['date'] . 'T' . $entry['close_time'],
                'display' => 'background',
                'color' => '#ffeeba',
                'description' => $entry['description'],
            ];
        }
    }
}
$events_json = json_encode($events);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Operational Hours</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        #calendar {
            max-width: 900px;
            margin: 40px auto;
        }

        .fc-event-title {
            color: #000 !important;
        }

        .tooltip-inner {
            max-width: 200px;
            text-align: left;
        }
    </style>
</head>

<body>
    <div class="container my-5">
        <h1 class="mb-4">Manage Operational Hours</h1>
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2>All Entries</h2>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addEntryModal">Add Entry</button>
            </div>
            <div class="card-body">
                <?php if ($entries): ?>
                    <table class="table table-bordered table-responsive">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Day/Date</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Open Time</th>
                                <th>Close Time</th>
                                <th>Closed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $entry): ?>
                                <tr>
                                    <td><?= ucfirst(htmlspecialchars($entry['type'])) ?></td>
                                    <td>
                                        <?= $entry['type'] === 'regular' ? htmlspecialchars($entry['day_of_week']) : htmlspecialchars($entry['date']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($entry['title'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($entry['description'] ?? '-') ?></td>
                                    <td><?= $entry['is_closed'] ? '-' : htmlspecialchars($entry['open_time']) ?></td>
                                    <td><?= $entry['is_closed'] ? '-' : htmlspecialchars($entry['close_time']) ?></td>
                                    <td><?= $entry['is_closed'] ? 'Yes' : 'No' ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editEntryModal<?= $entry['id'] ?>">Edit</button>
                                        <button type="button" class="btn btn-sm btn-danger btn-delete" data-id="<?= htmlspecialchars($entry['id']) ?>">Delete</button>
                                    </td>
                                </tr>
                                <div class="modal fade" id="editEntryModal<?= $entry['id'] ?>" tabindex="-1" aria-labelledby="editEntryModalLabel<?= $entry['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content">
                                            <form method="POST" action="informations.php">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="editEntryModalLabel<?= $entry['id'] ?>">Edit <?= ucfirst(htmlspecialchars($entry['type'])) ?> Entry</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="edit_entry">
                                                    <input type="hidden" name="entry_id" value="<?= htmlspecialchars($entry['id']) ?>">
                                                    <div class="mb-3">
                                                        <label for="type<?= $entry['id'] ?>" class="form-label">Type</label>
                                                        <select class="form-select" id="type<?= $entry['id'] ?>" name="type" onchange="toggleFormFields(this, <?= $entry['id'] ?>)" required>
                                                            <option value="regular" <?= $entry['type'] === 'regular' ? 'selected' : '' ?>>Regular</option>
                                                            <option value="holiday" <?= $entry['type'] === 'holiday' ? 'selected' : '' ?>>Holiday</option>
                                                        </select>
                                                    </div>
                                                    <div id="regularFields<?= $entry['id'] ?>" style="display: <?= $entry['type'] === 'regular' ? 'block' : 'none' ?>;">
                                                        <div class="mb-3">
                                                            <label for="day_of_week<?= $entry['id'] ?>" class="form-label">Day of Week</label>
                                                            <select class="form-select" id="day_of_week<?= $entry['id'] ?>" name="day_of_week">
                                                                <?php foreach ($day_map as $day => $index): ?>
                                                                    <option value="<?= htmlspecialchars($day) ?>" <?= $entry['day_of_week'] === $day ? 'selected' : '' ?>><?= htmlspecialchars($day) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div id="holidayFields<?= $entry['id'] ?>" style="display: <?= $entry['type'] === 'holiday' ? 'block' : 'none' ?>;">
                                                        <div class="mb-3">
                                                            <label for="date<?= $entry['id'] ?>" class="form-label">Date</label>
                                                            <input type="date" class="form-control" id="date<?= $entry['id'] ?>" name="date" value="<?= htmlspecialchars($entry['date']) ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="title<?= $entry['id'] ?>" class="form-label">Title</label>
                                                            <input type="text" class="form-control" id="title<?= $entry['id'] ?>" name="title" value="<?= htmlspecialchars($entry['title']) ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="description<?= $entry['id'] ?>" class="form-label">Description</label>
                                                            <textarea class="form-control" id="description<?= $entry['id'] ?>" name="description" rows="3"><?= htmlspecialchars($entry['description']) ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3 form-check">
                                                        <input type="checkbox" class="form-check-input" id="is_closed<?= $entry['id'] ?>" name="is_closed" value="1" <?= $entry['is_closed'] ? 'checked' : '' ?> onclick="toggleEntryTimeFields(this, <?= $entry['id'] ?>)">
                                                        <label class="form-check-label" for="is_closed<?= $entry['id'] ?>">Restaurant is Closed</label>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label for="open_time<?= $entry['id'] ?>" class="form-label">Open Time</label>
                                                            <input type="time" class="form-control" id="open_time<?= $entry['id'] ?>" name="open_time" value="<?= htmlspecialchars($entry['open_time']) ?>" <?= $entry['is_closed'] ? 'disabled' : '' ?>>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="close_time<?= $entry['id'] ?>" class="form-label">Close Time</label>
                                                            <input type="time" class="form-control" id="close_time<?= $entry['id'] ?>" name="close_time" value="<?= htmlspecialchars($entry['close_time']) ?>" <?= $entry['is_closed'] ? 'disabled' : '' ?>>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Update Entry</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No entries found.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="modal fade" id="addEntryModal" tabindex="-1" aria-labelledby="addEntryModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="informations.php">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addEntryModalLabel">Add New Entry</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add_entry">
                            <div class="mb-3">
                                <label for="type" class="form-label">Type</label>
                                <select class="form-select" id="type" name="type" onchange="toggleAddFormFields(this)" required>
                                    <option value="" selected disabled>Choose type</option>
                                    <option value="regular">Regular</option>
                                    <option value="holiday">Holiday</option>
                                </select>
                            </div>
                            <div id="addRegularFields" style="display: none;">
                                <div class="mb-3">
                                    <label for="day_of_week" class="form-label">Day of Week</label>
                                    <select class="form-select" id="day_of_week" name="day_of_week">
                                        <option value="" selected disabled>Choose a day</option>
                                        <?php foreach ($day_map as $day => $index): ?>
                                            <option value="<?= htmlspecialchars($day) ?>"><?= htmlspecialchars($day) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div id="addHolidayFields" style="display: none;">
                                <div class="mb-3">
                                    <label for="date" class="form-label">Date</label>
                                    <input type="date" class="form-control" id="date" name="date">
                                </div>
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title</label>
                                    <input type="text" class="form-control" id="title" name="title" placeholder="e.g., Christmas Day">
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" placeholder="Details about the holiday"></textarea>
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_closed" name="is_closed" value="1" onclick="toggleAddEntryTimeFields(this)">
                                <label class="form-check-label" for="is_closed">Restaurant is Closed</label>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="open_time" class="form-label">Open Time</label>
                                    <input type="time" class="form-control" id="open_time" name="open_time">
                                </div>
                                <div class="col-md-6">
                                    <label for="close_time" class="form-label">Close Time</label>
                                    <input type="time" class="form-control" id="close_time" name="close_time">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Entry</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div id="calendar"></div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function toggleAddFormFields(select) {
            const type = select.value;
            const regularFields = document.getElementById('addRegularFields');
            const holidayFields = document.getElementById('addHolidayFields');
            if (type === 'regular') {
                regularFields.style.display = 'block';
                holidayFields.style.display = 'none';
            } else if (type === 'holiday') {
                regularFields.style.display = 'none';
                holidayFields.style.display = 'block';
            } else {
                regularFields.style.display = 'none';
                holidayFields.style.display = 'none';
            }
        }

        function toggleAddEntryTimeFields(checkbox) {
            const openTime = document.getElementById('open_time');
            const closeTime = document.getElementById('close_time');
            if (checkbox.checked) {
                openTime.disabled = true;
                closeTime.disabled = true;
                openTime.value = '';
                closeTime.value = '';
            } else {
                openTime.disabled = false;
                closeTime.disabled = false;
            }
        }

        function toggleEntryTimeFields(checkbox, id) {
            const openTime = document.getElementById('open_time' + id);
            const closeTime = document.getElementById('close_time' + id);
            if (checkbox.checked) {
                openTime.disabled = true;
                closeTime.disabled = true;
                openTime.value = '';
                closeTime.value = '';
            } else {
                openTime.disabled = false;
                closeTime.disabled = false;
            }
        }

        function toggleFormFields(select, id) {
            const type = select.value;
            const regularFields = document.getElementById('regularFields' + id);
            const holidayFields = document.getElementById('holidayFields' + id);
            if (type === 'regular') {
                regularFields.style.display = 'block';
                holidayFields.style.display = 'none';
            } else if (type === 'holiday') {
                regularFields.style.display = 'none';
                holidayFields.style.display = 'block';
            } else {
                regularFields.style.display = 'none';
                holidayFields.style.display = 'none';
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var events = <?= $events_json ?>;
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                events: events,
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                eventDidMount: function(info) {
                    if (info.event.extendedProps.description) {
                        var tooltip = new bootstrap.Tooltip(info.el, {
                            title: info.event.extendedProps.description,
                            placement: 'top',
                            trigger: 'hover',
                            container: 'body'
                        });
                    }
                }
            });
            calendar.render();

            const deleteButtons = document.querySelectorAll('.btn-delete');
            deleteButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const entryId = this.getAttribute('data-id');
                    Swal.fire({
                        title: 'Are you sure?',
                        text: "You won't be able to revert this!",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, delete it!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = `informations.php?delete_entry=${entryId}`;
                        }
                    });
                });
            });
        });
    </script>
</body>

</html>