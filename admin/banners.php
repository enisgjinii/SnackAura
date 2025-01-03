<?php
ob_start();
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Hilfsfunktionen
function redirectWithMessage($action, $message)
{
    header("Location: banners.php?action={$action}&message=" . urlencode($message));
    exit();
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
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
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

// Initialisierung
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = $_GET['message'] ?? '';
$perPage = 10;

// POST-Anfragen verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'create':
            $title = trim($_POST['title'] ?? '');
            $link = trim($_POST['link'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $image = $_FILES['image'] ?? null;
            if (empty($title) || empty($image)) {
                $message = 'Alle mit * gekennzeichneten Felder sind erforderlich.';
            } else {
                $uploadResult = handleImageUpload($image);
                if (isset($uploadResult['error'])) {
                    $message = $uploadResult['error'];
                } else {
                    try {
                        $stmt = $pdo->prepare('INSERT INTO banners (title, image, link, is_active, created_at) VALUES (?, ?, ?, ?, NOW())');
                        $stmt->execute([$title, $uploadResult['filename'], $link, $is_active]);
                        redirectWithMessage('list', "Banner erfolgreich erstellt.");
                    } catch (PDOException $e) {
                        error_log("Fehler beim Erstellen des Banners: " . $e->getMessage());
                        $message = 'Banner konnte nicht erstellt werden. Bitte versuchen Sie es später erneut.';
                    }
                }
            }
            break;
        case 'edit':
            if ($id > 0) {
                $title = trim($_POST['title'] ?? '');
                $link = trim($_POST['link'] ?? '');
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                $image = $_FILES['image'] ?? null;
                if (empty($title)) {
                    $message = 'Der Titel ist erforderlich.';
                } else {
                    $banner = fetchBanner($pdo, $id);
                    if (!$banner) {
                        redirectWithMessage('list', "Banner nicht gefunden.");
                    }
                    if ($image && $image['error'] === UPLOAD_ERR_OK) {
                        $uploadResult = handleImageUpload($image);
                        if (isset($uploadResult['error'])) {
                            $message = $uploadResult['error'];
                        } else {
                            // Altes Bild löschen
                            if ($banner['image'] && file_exists('uploads/banners/' . $banner['image'])) {
                                unlink('uploads/banners/' . $banner['image']);
                            }
                            $imageFilename = $uploadResult['filename'];
                        }
                    } else {
                        $imageFilename = $banner['image'];
                    }
                    if (empty($message)) {
                        try {
                            $stmt = $pdo->prepare('UPDATE banners SET title = ?, image = ?, link = ?, is_active = ?, updated_at = NOW() WHERE id = ?');
                            $stmt->execute([$title, $imageFilename, $link, $is_active, $id]);
                            redirectWithMessage('list', "Banner erfolgreich aktualisiert.");
                        } catch (PDOException $e) {
                            error_log("Fehler beim Aktualisieren des Banners (ID: $id): " . $e->getMessage());
                            $message = 'Banner konnte nicht aktualisiert werden. Bitte versuchen Sie es später erneut.';
                        }
                    }
                }
            }
            break;
        case 'delete':
            if ($id > 0) {
                $banner = fetchBanner($pdo, $id);
                if ($banner) {
                    try {
                        $stmt = $pdo->prepare('DELETE FROM banners WHERE id = ?');
                        $stmt->execute([$id]);
                        // Bilddatei löschen
                        if ($banner['image'] && file_exists('uploads/banners/' . $banner['image'])) {
                            unlink('uploads/banners/' . $banner['image']);
                        }
                        redirectWithMessage('list', "Banner erfolgreich gelöscht.");
                    } catch (PDOException $e) {
                        error_log("Fehler beim Löschen des Banners (ID: $id): " . $e->getMessage());
                        redirectWithMessage('list', "Banner konnte nicht gelöscht werden. Bitte versuchen Sie es später erneut.");
                    }
                } else {
                    redirectWithMessage('list', "Banner nicht gefunden.");
                }
            }
            break;
        case 'export':
            try {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment;filename=banners_' . date('Ymd') . '.csv');
                $output = fopen('php://output', 'w');
                fputcsv($output, ['ID', 'Titel', 'Bild', 'Link', 'Status', 'Erstellt', 'Aktualisiert']);
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
                $message = 'Banner konnten nicht exportiert werden. Bitte versuchen Sie es später erneut.';
            }
            break;
        default:
            // Weitere POST-Aktionen bei Bedarf hinzufügen
            break;
    }
}

// GET-Anfragen verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'toggle_status' && $id > 0) {
        $banner = fetchBanner($pdo, $id);
        if ($banner) {
            $new_status = $banner['is_active'] ? 0 : 1;
            try {
                $stmt = $pdo->prepare('UPDATE banners SET is_active = ? WHERE id = ?');
                $stmt->execute([$new_status, $id]);
                $status_text = $new_status ? 'aktiviert' : 'deaktiviert';
                redirectWithMessage('list', "Banner erfolgreich {$status_text}.");
            } catch (PDOException $e) {
                error_log("Fehler beim Umschalten des Bannerstatus (ID: $id): " . $e->getMessage());
                redirectWithMessage('list', "Bannerstatus konnte nicht geändert werden. Bitte versuchen Sie es später erneut.");
            }
        } else {
            redirectWithMessage('list', "Banner nicht gefunden.");
        }
    }
}

// Daten für verschiedene Aktionen abrufen
if ($action === 'list') {
    try {
        $stmt = $pdo->query('SELECT * FROM banners ORDER BY created_at DESC');
        $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fehler beim Abrufen der Banner: " . $e->getMessage());
        $banners = [];
        $message = 'Banner konnten nicht abgerufen werden. Bitte versuchen Sie es später erneut.';
    }
} elseif (in_array($action, ['edit', 'view']) && $id > 0) {
    $banner = fetchBanner($pdo, $id);
    if (!$banner) {
        redirectWithMessage('list', "Banner nicht gefunden.");
    }
}
?>

<?php if ($action === 'list'): ?>
    <div class="container mt-4">
        <h2 class="mb-3">Banner</h2>
        <?php if ($message): ?>
            <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <div class="table-responsive">
            <table id="bannersTable" class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Bild</th>
                        <th>Titel</th>
                        <th>Link</th>
                        <th>Status</th>
                        <th>Erstellt</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($banners)): ?>
                        <tr>
                            <td colspan="6" class="text-center">Keine Banner vorhanden.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($banners as $banner): ?>
                            <tr>
                                <td>
                                    <?php if ($banner['image'] && file_exists('uploads/banners/' . $banner['image'])): ?>
                                        <img src="uploads/banners/<?= htmlspecialchars($banner['image']) ?>" alt="<?= htmlspecialchars($banner['title']) ?>" width="80">
                                    <?php else: ?>
                                        <span>N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($banner['title']) ?></td>
                                <td><?= htmlspecialchars($banner['link']) ?></td>
                                <td>
                                    <span class="badge <?= $banner['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $banner['is_active'] ? 'Aktiv' : 'Inaktiv' ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($banner['created_at']) ?></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a href="banners.php?action=view&id=<?= $banner['id'] ?>" class="btn btn-sm btn-info" title="Ansehen">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="banners.php?action=edit&id=<?= $banner['id'] ?>" class="btn btn-sm btn-warning" title="Bearbeiten">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="banners.php?action=delete&id=<?= $banner['id'] ?>" class="btn btn-sm btn-danger" title="Löschen" onclick="return confirm('Sind Sie sicher, dass Sie dieses Banner löschen möchten?');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                        <a href="banners.php?action=toggle_status&id=<?= $banner['id'] ?>" class="btn btn-sm <?= $banner['is_active'] ? 'btn-secondary' : 'btn-success' ?>" title="<?= $banner['is_active'] ? 'Deaktivieren' : 'Aktivieren' ?>">
                                            <i class="fas <?= $banner['is_active'] ? 'fa-toggle-off' : 'fa-toggle-on' ?>"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php elseif ($action === 'create'): ?>
    <div class="container mt-4">
        <h2 class="mb-3"><i class="fas fa-plus"></i> Neues Banner erstellen</h2>
        <?php if ($message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <form method="POST" action="banners.php?action=create" enctype="multipart/form-data">
            <div class="row g-2">
                <div class="col-md-4">
                    <label for="title" class="form-label">Titel<span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title" required maxlength="255" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="link" class="form-label">Link</label>
                    <input type="url" class="form-control" id="link" name="link" maxlength="255" value="<?= htmlspecialchars($_POST['link'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="is_active" class="form-label">Status</label>
                    <select class="form-select" id="is_active" name="is_active">
                        <option value="1" <?= isset($_POST['is_active']) ? 'selected' : 'selected' ?>>Aktiv</option>
                        <option value="0" <?= isset($_POST['is_active']) && $_POST['is_active'] == '0' ? 'selected' : '' ?>>Inaktiv</option>
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <label for="image" class="form-label">Bild<span class="text-danger">*</span></label>
                <input type="file" class="form-control" id="image" name="image" required accept="image/*">
            </div>
            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Banner erstellen
                </button>
                <a href="banners.php?action=list" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Abbrechen
                </a>
            </div>
        </form>
    </div>
<?php elseif ($action === 'edit' && isset($banner)): ?>
    <div class="container mt-4">
        <h2 class="mb-3"><i class="fas fa-edit"></i> Banner bearbeiten</h2>
        <?php if ($message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <form method="POST" action="banners.php?action=edit&id=<?= $banner['id'] ?>" enctype="multipart/form-data">
            <div class="row g-2">
                <div class="col-md-4">
                    <label for="title" class="form-label">Titel<span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title" required maxlength="255" value="<?= htmlspecialchars($_POST['title'] ?? $banner['title']) ?>">
                </div>
                <div class="col-md-4">
                    <label for="link" class="form-label">Link</label>
                    <input type="url" class="form-control" id="link" name="link" maxlength="255" value="<?= htmlspecialchars($_POST['link'] ?? $banner['link']) ?>">
                </div>
                <div class="col-md-4">
                    <label for="is_active" class="form-label">Status</label>
                    <select class="form-select" id="is_active" name="is_active">
                        <option value="1" <?= ($banner['is_active'] || (isset($_POST['is_active']) && $_POST['is_active'])) ? 'selected' : '' ?>>Aktiv</option>
                        <option value="0" <?= (!$banner['is_active'] && !(isset($_POST['is_active']) && $_POST['is_active'])) ? 'selected' : '' ?>>Inaktiv</option>
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <label for="image" class="form-label">Bild</label>
                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                <?php if ($banner['image'] && file_exists('uploads/banners/' . $banner['image'])): ?>
                    <img src="uploads/banners/<?= htmlspecialchars($banner['image']) ?>" alt="<?= htmlspecialchars($banner['title']) ?>" width="120" class="mt-2">
                <?php endif; ?>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Änderungen speichern
                </button>
                <a href="banners.php?action=list" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Abbrechen
                </a>
            </div>
        </form>
    </div>
<?php elseif ($action === 'delete' && $id > 0): ?>
    <div class="container mt-4">
        <h2 class="mb-3"><i class="fas fa-trash-alt"></i> Banner löschen</h2>
        <?php
        $banner = fetchBanner($pdo, $id);
        if (!$banner):
        ?>
            <div class="alert alert-danger">Banner nicht gefunden.</div>
            <a href="banners.php?action=list" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Zurück zur Liste
            </a>
        <?php else: ?>
            <div class="alert alert-warning">
                Sind Sie sicher, dass Sie das Banner <strong><?= htmlspecialchars($banner['title']) ?></strong> löschen möchten?
            </div>
            <form method="POST" action="banners.php?action=delete&id=<?= $id ?>">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-check"></i> Ja, löschen
                    </button>
                    <a href="banners.php?action=list" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Nein, abbrechen
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
<?php elseif ($action === 'view' && isset($banner)): ?>
    <div class="container mt-4">
        <h2 class="mb-3"><i class="fas fa-eye"></i> Banner-Details</h2>
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <tr>
                    <th>ID</th>
                    <td><?= htmlspecialchars($banner['id']) ?></td>
                </tr>
                <tr>
                    <th>Titel</th>
                    <td><?= htmlspecialchars($banner['title']) ?></td>
                </tr>
                <tr>
                    <th>Bild</th>
                    <td>
                        <?php if ($banner['image'] && file_exists('uploads/banners/' . $banner['image'])): ?>
                            <img src="uploads/banners/<?= htmlspecialchars($banner['image']) ?>" alt="<?= htmlspecialchars($banner['title']) ?>" width="200">
                        <?php else: ?>
                            <span>N/A</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Link</th>
                    <td><?= htmlspecialchars($banner['link']) ?></td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>
                        <span class="badge <?= $banner['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $banner['is_active'] ? 'Aktiv' : 'Inaktiv' ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Erstellt</th>
                    <td><?= htmlspecialchars($banner['created_at']) ?></td>
                </tr>
                <tr>
                    <th>Aktualisiert</th>
                    <td><?= htmlspecialchars($banner['updated_at'] ?? 'N/A') ?></td>
                </tr>
            </table>
        </div>
        <a href="banners.php?action=list" class="btn btn-secondary mt-3">
            <i class="fas fa-arrow-left"></i> Zurück zur Liste
        </a>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

<!-- DataTable Initialisierung -->
<script>
    $(document).ready(function() {
        $('#bannersTable').DataTable({
            "paging": true,
            "searching": true,
            "info": true,
            "order": [
                [4, "desc"]
            ],
            "dom": '<"row mb-3"<"col-12 d-flex justify-content-between align-items-center"lBf>>rt<"row mt-3"<"col-sm-12 col-md-6 d-flex justify-content-start"i><"col-sm-12 col-md-6 d-flex justify-content-end"p>>',
            "buttons": [{
                    text: '<i class="fas fa-plus"></i> Neues Banner',
                    action: function(e, dt, node, config) {
                        window.location.href = 'banners.php?action=create';
                    },
                    className: 'btn btn-success btn-sm'
                },
                {
                    extend: 'csv',
                    text: '<i class="fas fa-file-csv"></i> CSV Export',
                    className: 'btn btn-primary btn-sm'
                },
                {
                    extend: 'pdf',
                    text: '<i class="fas fa-file-pdf"></i> PDF Export',
                    className: 'btn btn-primary btn-sm'
                },
                {
                    extend: 'colvis',
                    text: '<i class="fas fa-columns"></i> Spalten',
                    className: 'btn btn-primary btn-sm',
                },
                {
                    extend: 'copy',
                    text: '<i class="fas fa-copy"></i> Kopieren',
                    className: 'btn btn-primary btn-sm',
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
    });
</script>

<?php
require_once 'includes/footer.php';
ob_end_flush();
?>