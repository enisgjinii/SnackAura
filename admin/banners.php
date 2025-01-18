<?php
ob_start();
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

function sanitizeInput($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function handleImageUpload($image, $targetDir = 'uploads/banners/')
{
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

    if (!$image || $image['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Fehler beim Hochladen des Bildes.'];
    }

    if (!in_array($image['type'], $allowedTypes)) {
        return ['error' => 'Ungültiges Bildformat. Erlaubt sind JPEG, PNG, GIF.'];
    }

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
        return ['error' => "Verzeichnis '{$targetDir}' konnte nicht erstellt werden."];
    }

    $filename = uniqid() . '_' . basename($image['name']);
    $targetFile = $targetDir . $filename;

    if (!move_uploaded_file($image['tmp_name'], $targetFile)) {
        return ['error' => 'Bild konnte nicht hochgeladen werden.'];
    }

    return ['filename' => $filename];
}

function fetchBanner($pdo, $id)
{
    $stmt = $pdo->prepare('SELECT * FROM banners WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/***************************************
 * Create banners table if not exists
 ***************************************/
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `banners` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `image` VARCHAR(255) DEFAULT NULL,
            `link` VARCHAR(255) DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    $_SESSION['toast'] = [
        'type' => 'danger',
        'message' => 'Tabelle konnte nicht erstellt werden: ' . htmlspecialchars($e->getMessage())
    ];
    header('Location: banners.php');
    exit();
}

/***************************************
 * Determine action
 ***************************************/
$action = $_REQUEST['action'] ?? 'list';
$id     = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;

/***************************************
 * Handle POST requests (Create, Edit, Delete)
 ***************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        // Create banner
        $title     = trim($_POST['title'] ?? '');
        $link      = trim($_POST['link'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $image     = $_FILES['image'] ?? null;

        if (empty($title) || !$image) {
            $_SESSION['toast'] = [
                'type' => 'danger',
                'message' => 'Titel und Bild sind erforderlich.'
            ];
            header('Location: banners.php?action=list');
            exit();
        }

        // Upload image
        $uploadResult = handleImageUpload($image);
        if (isset($uploadResult['error'])) {
            $_SESSION['toast'] = [
                'type' => 'danger',
                'message' => $uploadResult['error']
            ];
            header('Location: banners.php?action=list');
            exit();
        }

        // Insert into DB
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO banners (title, image, link, is_active, created_at)
                 VALUES (?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$title, $uploadResult['filename'], $link, $is_active]);

            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Banner erfolgreich erstellt.'
            ];
            header('Location: banners.php?action=list');
            exit();
        } catch (PDOException $e) {
            error_log("Fehler beim Erstellen des Banners: " . $e->getMessage());
            $_SESSION['toast'] = [
                'type' => 'danger',
                'message' => 'Banner konnte nicht erstellt werden. Bitte versuchen Sie es später erneut.'
            ];
            header('Location: banners.php?action=list');
            exit();
        }
    } elseif ($action === 'edit' && $id > 0) {
        // Edit banner
        $title     = trim($_POST['title'] ?? '');
        $link      = trim($_POST['link'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $image     = $_FILES['image'] ?? null;

        if (empty($title)) {
            $_SESSION['toast'] = [
                'type' => 'danger',
                'message' => 'Der Titel ist erforderlich.'
            ];
            header('Location: banners.php?action=list');
            exit();
        }

        // Fetch existing banner
        $banner = fetchBanner($pdo, $id);
        if (!$banner) {
            $_SESSION['toast'] = [
                'type' => 'danger',
                'message' => 'Banner nicht gefunden.'
            ];
            header('Location: banners.php?action=list');
            exit();
        }

        // Optional image upload
        $imageFilename = $banner['image']; // Keep old image by default
        if ($image && $image['error'] === UPLOAD_ERR_OK) {
            // Upload new image
            $uploadResult = handleImageUpload($image);
            if (isset($uploadResult['error'])) {
                $_SESSION['toast'] = [
                    'type' => 'danger',
                    'message' => $uploadResult['error']
                ];
                header('Location: banners.php?action=list');
                exit();
            }
            $imageFilename = $uploadResult['filename'];
        }

        // Update DB
        try {
            $stmt = $pdo->prepare(
                'UPDATE banners
                 SET title = ?, image = ?, link = ?, is_active = ?, updated_at = NOW()
                 WHERE id = ?'
            );
            $stmt->execute([$title, $imageFilename, $link, $is_active, $id]);

            // Delete old image if a new one was uploaded
            if (($image && $image['error'] === UPLOAD_ERR_OK) && $banner['image']) {
                $oldPath = 'uploads/banners/' . $banner['image'];
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Banner erfolgreich aktualisiert.'
            ];
            header('Location: banners.php?action=list');
            exit();
        } catch (PDOException $e) {
            error_log("Fehler beim Aktualisieren des Banners (ID: $id): " . $e->getMessage());
            $_SESSION['toast'] = [
                'type' => 'danger',
                'message' => 'Banner konnte nicht aktualisiert werden. Bitte versuchen Sie es später erneut.'
            ];
            header('Location: banners.php?action=list');
            exit();
        }
    } elseif ($action === 'delete' && $id > 0) {
        // Delete banner
        $banner = fetchBanner($pdo, $id);
        if ($banner) {
            try {
                $stmt = $pdo->prepare('DELETE FROM banners WHERE id = ?');
                $stmt->execute([$id]);

                // Delete banner image
                if ($banner['image']) {
                    $path = 'uploads/banners/' . $banner['image'];
                    if (file_exists($path)) {
                        @unlink($path);
                    }
                }

                $_SESSION['toast'] = [
                    'type' => 'success',
                    'message' => 'Banner erfolgreich gelöscht.'
                ];
                header('Location: banners.php?action=list');
                exit();
            } catch (PDOException $e) {
                error_log("Fehler beim Löschen des Banners (ID: $id): " . $e->getMessage());
                $_SESSION['toast'] = [
                    'type' => 'danger',
                    'message' => 'Banner konnte nicht gelöscht werden. Bitte versuchen Sie es später erneut.'
                ];
                header('Location: banners.php?action=list');
                exit();
            }
        } else {
            $_SESSION['toast'] = [
                'type' => 'danger',
                'message' => 'Banner nicht gefunden.'
            ];
            header('Location: banners.php?action=list');
            exit();
        }
    } elseif ($action === 'export') {
        // CSV Export
        try {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment;filename=banners_' . date('Ymd') . '.csv');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Titel', 'Bild', 'Link', 'Status', 'Erstellt am', 'Aktualisiert am']);

            $stmt = $pdo->query('SELECT * FROM banners ORDER BY created_at DESC');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['id'],
                    $row['title'],
                    $row['image'],
                    $row['link'],
                    $row['is_active'] ? 'Aktiv' : 'Inaktiv',
                    $row['created_at'],
                    $row['updated_at'] ?? 'N/A'
                ]);
            }
            fclose($output);
            exit();
        } catch (PDOException $e) {
            error_log("Fehler beim Exportieren der Banner: " . $e->getMessage());
            $_SESSION['toast'] = [
                'type' => 'danger',
                'message' => 'Banner konnten nicht exportiert werden. Bitte versuchen Sie es später erneut.'
            ];
            header('Location: banners.php?action=list');
            exit();
        }
    }
}

/***************************************
 * Handle GET requests for toggling status
 ***************************************/
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'toggle_status' && $id > 0) {
    $banner = fetchBanner($pdo, $id);
    if ($banner) {
        $new_status = $banner['is_active'] ? 0 : 1;
        try {
            $stmt = $pdo->prepare('UPDATE banners SET is_active = ? WHERE id = ?');
            $stmt->execute([$new_status, $id]);
            $status_text = $new_status ? 'aktiviert' : 'deaktiviert';
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => "Banner erfolgreich {$status_text}."
            ];
            header('Location: banners.php?action=list');
            exit();
        } catch (PDOException $e) {
            error_log("Fehler beim Umschalten des Bannerstatus (ID: $id): " . $e->getMessage());
            $_SESSION['toast'] = [
                'type' => 'danger',
                'message' => 'Bannerstatus konnte nicht geändert werden. Bitte versuchen Sie es später erneut.'
            ];
            header('Location: banners.php?action=list');
            exit();
        }
    } else {
        $_SESSION['toast'] = [
            'type' => 'danger',
            'message' => 'Banner nicht gefunden.'
        ];
        header('Location: banners.php?action=list');
        exit();
    }
}

/***************************************
 * Fetch data for "list" view
 ***************************************/
if ($action === 'list') {
    try {
        $stmt = $pdo->query('SELECT * FROM banners ORDER BY created_at DESC');
        $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fehler beim Abrufen der Banner: " . $e->getMessage());
        $banners = [];
        $_SESSION['toast'] = [
            'type' => 'danger',
            'message' => 'Banner konnten nicht abgerufen werden. Bitte versuchen Sie es später erneut.'
        ];
    }
}

/***************************************
 * HTML / UI Rendering
 ***************************************/
?>
<?php if ($action === 'list'): ?>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2>Banner verwalten</h2>
        <button class="btn btn-success btn-sm" data-bs-toggle="offcanvas" data-bs-target="#createBannerOffcanvas">
            <i class="fas fa-plus"></i> Neues Banner
        </button>
    </div>
    <hr>
    <div class="table-responsive">
        <table id="bannersTable" class="table table-striped table-bordered align-middle small">
            <thead class="table-dark">
                <tr>
                    <th>Bild</th>
                    <th>Titel</th>
                    <th>Link</th>
                    <th>Status</th>
                    <th>Erstellt am</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($banners)): ?>
                    <?php foreach ($banners as $b): ?>
                        <tr>
                            <td>
                                <?php if ($b['image'] && file_exists('uploads/banners/' . $b['image'])): ?>
                                    <img src="uploads/banners/<?= sanitizeInput($b['image']) ?>" alt="<?= sanitizeInput($b['title']) ?>" width="80">
                                <?php else: ?>
                                    <span>N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?= sanitizeInput($b['title']) ?></td>
                            <td><?= sanitizeInput($b['link']) ?></td>
                            <td>
                                <span class="badge <?= ($b['is_active'] ? 'bg-success' : 'bg-secondary') ?>">
                                    <?= ($b['is_active'] ? 'Aktiv' : 'Inaktiv') ?>
                                </span>
                            </td>
                            <td><?= sanitizeInput($b['created_at']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning me-1 edit-banner-btn"
                                    data-id="<?= $b['id'] ?>"
                                    data-title="<?= sanitizeInput($b['title']) ?>"
                                    data-link="<?= sanitizeInput($b['link']) ?>"
                                    data-active="<?= $b['is_active'] ?>"
                                    data-image="<?= sanitizeInput($b['image']) ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm <?= $b['is_active'] ? 'btn-secondary' : 'btn-success' ?> toggle-status-btn"
                                    data-id="<?= $b['id'] ?>"
                                    data-title="<?= sanitizeInput($b['title']) ?>"
                                    data-status="<?= ($b['is_active'] ? 'deaktivieren' : 'aktivieren') ?>">
                                    <i class="fas <?= ($b['is_active'] ? 'fa-toggle-off' : 'fa-toggle-on') ?>"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-banner-btn"
                                    data-id="<?= $b['id'] ?>"
                                    data-title="<?= sanitizeInput($b['title']) ?>">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">Keine Banner gefunden.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Offcanvas: Create Banner -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="createBannerOffcanvas" aria-labelledby="createBannerOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="createBannerOffcanvasLabel">Neues Banner erstellen</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Schließen"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="banners.php?action=create" enctype="multipart/form-data">
                <div class="mb-2">
                    <label for="create_title" class="form-label">Titel <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="create_title" class="form-control form-control-sm" required>
                </div>
                <div class="mb-2">
                    <label for="create_link" class="form-label">Link</label>
                    <input type="url" name="link" id="create_link" class="form-control form-control-sm">
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" name="is_active" id="create_is_active" class="form-check-input" value="1" checked>
                    <label class="form-check-label" for="create_is_active">Aktiv</label>
                </div>
                <div class="mb-2">
                    <label for="create_image" class="form-label">Bild <span class="text-danger">*</span></label>
                    <input type="file" name="image" id="create_image" class="form-control form-control-sm" accept="image/*" required>
                </div>
                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save"></i> Speichern</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="offcanvas">Abbrechen</button>
            </form>
        </div>
    </div>

    <!-- Offcanvas: Edit Banner -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="editBannerOffcanvas" aria-labelledby="editBannerOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="editBannerOffcanvasLabel">Banner bearbeiten</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Schließen"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" id="editForm" action="banners.php?action=edit" enctype="multipart/form-data">
                <input type="hidden" name="id" id="edit_id">
                <div class="mb-2">
                    <label for="edit_title" class="form-label">Titel <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="edit_title" class="form-control form-control-sm" required>
                </div>
                <div class="mb-2">
                    <label for="edit_link" class="form-label">Link</label>
                    <input type="url" name="link" id="edit_link" class="form-control form-control-sm">
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" name="is_active" id="edit_is_active" class="form-check-input" value="1">
                    <label class="form-check-label" for="edit_is_active">Aktiv</label>
                </div>
                <div class="mb-2">
                    <label for="edit_image" class="form-label">Neues Bild hochladen</label>
                    <input type="file" name="image" id="edit_image" class="form-control form-control-sm" accept="image/*">
                </div>
                <!-- Preview of current image -->
                <div id="currentImagePreview" class="mb-2" style="display: none;">
                    <img id="edit_current_image" src="#" alt="Aktuelles Bild" width="120" class="mt-2">
                </div>
                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save"></i> Aktualisieren</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="offcanvas">Abbrechen</button>
            </form>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
        <div id="toast-container"></div>
    </div>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
ob_end_flush();
?>

<!-- JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 5 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.colVis.min.js"></script>
<!-- Font Awesome -->
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        <?php if ($action === 'list'): ?>
            // Initialize DataTable
            $('#bannersTable').DataTable({
                paging: true,
                searching: true,
                info: true,
                order: [
                    [4, 'desc']
                ],
                dom: '<"row mb-3"' +
                    '<"col-12 d-flex justify-content-between align-items-center"lBf>' +
                    '>' +
                    'rt' +
                    '<"row mt-3"' +
                    '<"col-sm-12 col-md-6 d-flex justify-content-start"i>' +
                    '<"col-sm-12 col-md-6 d-flex justify-content-end"p>' +
                    '>',
                buttons: [{
                        text: '<i class="fas fa-plus"></i> Neues Banner',
                        className: 'btn btn-success btn-sm',
                        action: function() {
                            $('#createBannerOffcanvas').offcanvas('show');
                        }
                    },
                    {
                        extend: 'csv',
                        text: '<i class="fas fa-file-csv"></i> CSV exportieren',
                        className: 'btn btn-primary btn-sm',
                        action: function() {
                            window.location.href = 'banners.php?action=export';
                        }
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf"></i> PDF exportieren',
                        className: 'btn btn-primary btn-sm'
                        // For PDF export, you may need a server-side approach or the pdfmake library
                    },
                    {
                        extend: 'colvis',
                        text: '<i class="fas fa-columns"></i> Spalten',
                        className: 'btn btn-primary btn-sm'
                    },
                    {
                        extend: 'copy',
                        text: '<i class="fas fa-copy"></i> Kopieren',
                        className: 'btn btn-primary btn-sm'
                    },
                ],
                initComplete: function() {
                    var buttons = this.api().buttons();
                    buttons.container().addClass('d-flex flex-wrap gap-2');
                },
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/de-DE.json'
                }
            });

            // Show Toast from PHP Session
            <?php if (isset($_SESSION['toast'])): ?>
                showToast(
                    '<?= $_SESSION['toast']['message'] ?>',
                    '<?= ($_SESSION['toast']['type'] === 'success') ? 'success' : 'danger' ?>'
                );
                <?php unset($_SESSION['toast']); ?>
            <?php endif; ?>

            // Edit Banner Button
            $('.edit-banner-btn').on('click', function() {
                let id = $(this).data('id');
                let title = $(this).data('title');
                let link = $(this).data('link');
                let active = $(this).data('active');
                let image = $(this).data('image');

                // Fill edit offcanvas form
                $('#edit_id').val(id);
                $('#edit_title').val(title);
                $('#edit_link').val(link);
                $('#edit_is_active').prop('checked', (active == 1));
                if (image && image !== '') {
                    let previewPath = 'uploads/banners/' + image;
                    $('#edit_current_image').attr('src', previewPath);
                    $('#currentImagePreview').show();
                } else {
                    $('#edit_current_image').attr('src', '#');
                    $('#currentImagePreview').hide();
                }

                // Update form action
                $('#editForm').attr('action', 'banners.php?action=edit&id=' + id);

                // Show offcanvas
                let offcanvas = new bootstrap.Offcanvas($('#editBannerOffcanvas'));
                offcanvas.show();
            });

            // Toggle Status Button
            $('.toggle-status-btn').on('click', function() {
                let id = $(this).data('id');
                let bannerTitle = $(this).data('title');
                let newStatus = $(this).data('status');

                Swal.fire({
                    title: 'Sind Sie sicher?',
                    text: `Möchten Sie das Banner "${bannerTitle}" wirklich ${newStatus}?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ja, machen!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'banners.php?action=toggle_status&id=' + id;
                    }
                });
            });

            // Delete Banner Button
            $('.delete-banner-btn').on('click', function() {
                let id = $(this).data('id');
                let bannerTitle = $(this).data('title');

                Swal.fire({
                    title: 'Sind Sie sicher?',
                    text: `Möchten Sie das Banner "${bannerTitle}" wirklich löschen?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ja, löschen!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // We can either redirect or submit a hidden form
                        // Here we do a redirect for simplicity
                        window.location.href = 'banners.php?action=delete&id=' + id;
                    }
                });
            });

            // Function to show toast messages dynamically
            function showToast(message, type = 'primary') {
                // Build HTML for a single toast
                let toastHtml = `
                    <div class="toast align-items-center text-white bg-${type} border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">${message}</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Schließen"></button>
                        </div>
                    </div>
                `;

                $('#toast-container').append(toastHtml);
                let newToast = $('#toast-container .toast').last();
                let bsToast = new bootstrap.Toast(newToast, {
                    delay: 5000
                });
                bsToast.show();

                newToast.on('hidden.bs.toast', function() {
                    $(this).remove();
                });
            }
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