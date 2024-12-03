<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';
// Helper Functions
function redirectWithMessage($action, $message)
{
    header("Location: banners.php?action={$action}&message=" . urlencode($message));
    exit();
}
function handleImageUpload($image, $targetDir = 'uploads/banners/')
{
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!$image || $image['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Gabim gjatë ngarkimit të imazhit.'];
    }
    if (!in_array($image['type'], $allowedTypes)) {
        return ['error' => 'Format i imazhit i pavlefshëm. Lejohen JPEG, PNG, GIF.'];
    }
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    $filename = uniqid() . '_' . basename($image['name']);
    $targetFile = $targetDir . $filename;
    if (!move_uploaded_file($image['tmp_name'], $targetFile)) {
        return ['error' => 'Deshtoi ngarkimi i imazhit.'];
    }
    return ['filename' => $filename];
}
function fetchBanner($pdo, $id)
{
    $stmt = $pdo->prepare('SELECT * FROM banners WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
// Initialize Variables
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = $_GET['message'] ?? '';
$perPage = 10;
// Handle POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'create':
            $title = trim($_POST['title'] ?? '');
            $link = trim($_POST['link'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $image = $_FILES['image'] ?? null;
            if (empty($title) || empty($image)) {
                $message = 'Të gjitha fushat me yll janë të detyrueshme.';
            } else {
                $uploadResult = handleImageUpload($image);
                if (isset($uploadResult['error'])) {
                    $message = $uploadResult['error'];
                } else {
                    try {
                        $stmt = $pdo->prepare('INSERT INTO banners (title, image, link, is_active, created_at) VALUES (?, ?, ?, ?, NOW())');
                        $stmt->execute([$title, $uploadResult['filename'], $link, $is_active]);
                        redirectWithMessage('list', "Banner u krijua me sukses.");
                    } catch (PDOException $e) {
                        error_log("Error creating banner: " . $e->getMessage());
                        $message = 'Deshtoi krijimi i bannerit. Ju lutem provoni më vonë.';
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
                    $message = 'Titulli është i detyrueshëm.';
                } else {
                    $banner = fetchBanner($pdo, $id);
                    if (!$banner) {
                        redirectWithMessage('list', "Banner nuk u gjet.");
                    }
                    if ($image && $image['error'] === UPLOAD_ERR_OK) {
                        $uploadResult = handleImageUpload($image);
                        if (isset($uploadResult['error'])) {
                            $message = $uploadResult['error'];
                        } else {
                            // Delete old image
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
                            redirectWithMessage('list', "Banner u azhurnua me sukses.");
                        } catch (PDOException $e) {
                            error_log("Error updating banner (ID: $id): " . $e->getMessage());
                            $message = 'Deshtoi azhurnimi i bannerit. Ju lutem provoni më vonë.';
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
                        // Delete image file
                        if ($banner['image'] && file_exists('uploads/banners/' . $banner['image'])) {
                            unlink('uploads/banners/' . $banner['image']);
                        }
                        redirectWithMessage('list', "Banner u fshi me sukses.");
                    } catch (PDOException $e) {
                        error_log("Error deleting banner (ID: $id): " . $e->getMessage());
                        redirectWithMessage('list', "Deshtoi fshirja e bannerit. Ju lutem provoni më vonë.");
                    }
                } else {
                    redirectWithMessage('list', "Banner nuk u gjet.");
                }
            }
            break;
        case 'export':
            try {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment;filename=banners_' . date('Ymd') . '.csv');
                $output = fopen('php://output', 'w');
                fputcsv($output, ['ID', 'Titulli', 'Imazhi', 'Lidhja', 'Statusi', 'Krijuar', 'Azhurnuar']);
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
                error_log("Error exporting banners: " . $e->getMessage());
                $message = 'Deshtoi eksportimi i bannerave. Ju lutem provoni më vonë.';
            }
            break;
        default:
            // Handle other POST actions if necessary
            break;
    }
}
// Handle GET Requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'toggle_status' && $id > 0) {
        $banner = fetchBanner($pdo, $id);
        if ($banner) {
            $new_status = $banner['is_active'] ? 0 : 1;
            try {
                $stmt = $pdo->prepare('UPDATE banners SET is_active = ? WHERE id = ?');
                $stmt->execute([$new_status, $id]);
                $status_text = $new_status ? 'aktivizuar' : 'deaktivizuar';
                redirectWithMessage('list', "Banneri është {$status_text} me sukses.");
            } catch (PDOException $e) {
                error_log("Error toggling banner status (ID: $id): " . $e->getMessage());
                redirectWithMessage('list', "Deshtoi ndryshimi i statusit të bannerit. Ju lutem provoni më vonë.");
            }
        } else {
            redirectWithMessage('list', "Banner nuk u gjet.");
        }
    }
}
// Fetch Data for Different Actions
if ($action === 'list') {
    try {
        $stmt = $pdo->query('SELECT * FROM banners ORDER BY created_at DESC');
        $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching banners: " . $e->getMessage());
        $banners = [];
        $message = 'Deshtoi marrja e bannerave. Ju lutem provoni më vonë.';
    }
} elseif (in_array($action, ['edit', 'view']) && $id > 0) {
    $banner = fetchBanner($pdo, $id);
    if (!$banner) {
        redirectWithMessage('list', "Banner nuk u gjet.");
    }
}
?>
<?php if ($action === 'list'): ?>
    <h2 class="mb-4">Banners</h2>
    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <hr>
    <div class="table-responsive">
        <table id="bannersTable" class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Foto</th>
                    <th>Titulli</th>
                    <th>Lidhja</th>
                    <th>Statusi</th>
                    <th>Krijuar</th>
                    <th>Veprime</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($banners)): ?>

                <?php else: ?>
                    <?php foreach ($banners as $banner): ?>
                        <tr>
                            <td>
                                <?php if ($banner['image'] && file_exists('uploads/banners/' . $banner['image'])): ?>
                                    <img src="uploads/banners/<?= htmlspecialchars($banner['image']) ?>" alt="<?= htmlspecialchars($banner['title']) ?>" width="100">
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
                                    <a href="banners.php?action=view&id=<?= $banner['id'] ?>" class="btn btn-sm btn-info" title="Shiko">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="banners.php?action=edit&id=<?= $banner['id'] ?>" class="btn btn-sm btn-warning" title="Ndrysho">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="banners.php?action=delete&id=<?= $banner['id'] ?>" class="btn btn-sm btn-danger" title="Fshij" onclick="return confirm('A jeni i sigurtë që dëshironi të fshini këtë banner?');">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                    <a href="banners.php?action=toggle_status&id=<?= $banner['id'] ?>" class="btn btn-sm <?= $banner['is_active'] ? 'btn-secondary' : 'btn-success' ?>" title="<?= $banner['is_active'] ? 'Deaktivizo' : 'Aktivizo' ?>">
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
<?php elseif ($action === 'create'): ?>
    <h2 class="mb-4"><i class="fas fa-plus"></i> Krijo Banner të Ri</h2>
    <?php if ($message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <form method="POST" action="banners.php?action=create" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="title" class="form-label">Titulli<span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="title" name="title" required maxlength="255" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="image" class="form-label">Imazhi<span class="text-danger">*</span></label>
            <input type="file" class="form-control" id="image" name="image" required accept="image/*">
        </div>
        <div class="mb-3">
            <label for="link" class="form-label">Lidhja</label>
            <input type="url" class="form-control" id="link" name="link" maxlength="255" value="<?= htmlspecialchars($_POST['link'] ?? '') ?>">
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" <?= isset($_POST['is_active']) ? 'checked' : 'checked' ?>>
            <label class="form-check-label" for="is_active">Aktiv</label>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Krijo Banner
            </button>
            <a href="banners.php?action=list" class="btn btn-secondary">
                <i class="fas fa-times"></i> Anulo
            </a>
        </div>
    </form>
<?php elseif ($action === 'edit' && isset($banner)): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-edit"></i> Ndrysho Banner</h2>
        <?php if ($message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <form method="POST" action="banners.php?action=edit&id=<?= $banner['id'] ?>" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="title" class="form-label">Titulli<span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="title" name="title" required maxlength="255" value="<?= htmlspecialchars($_POST['title'] ?? $banner['title']) ?>">
            </div>
            <div class="mb-3">
                <label for="image" class="form-label">Imazhi</label>
                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                <?php if ($banner['image'] && file_exists('uploads/banners/' . $banner['image'])): ?>
                    <img src="uploads/banners/<?= htmlspecialchars($banner['image']) ?>" alt="<?= htmlspecialchars($banner['title']) ?>" width="150" class="mt-2">
                <?php endif; ?>
            </div>
            <div class="mb-3">
                <label for="link" class="form-label">Lidhja</label>
                <input type="url" class="form-control" id="link" name="link" maxlength="255" value="<?= htmlspecialchars($_POST['link'] ?? $banner['link']) ?>">
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" <?= ($banner['is_active'] || (isset($_POST['is_active']) && $_POST['is_active'])) ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">Aktiv</label>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Ruaj Ndryshimet
                </button>
                <a href="banners.php?action=list" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Anulo
                </a>
            </div>
        </form>
    </div>
<?php elseif ($action === 'delete' && $id > 0): ?>
    <h2 class="mb-4"><i class="fas fa-trash-alt"></i> Fshij Banner</h2>
    <?php
    $banner = fetchBanner($pdo, $id);
    if (!$banner):
    ?>
        <div class="alert alert-danger">Banner nuk u gjet.</div>
        <a href="banners.php?action=list" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kthehu te Lista
        </a>
    <?php else: ?>
        <div class="alert alert-warning">
            A jeni i sigurtë që dëshironi të fshini bannerin <strong><?= htmlspecialchars($banner['title']) ?></strong>?
        </div>
        <form method="POST" action="banners.php?action=delete&id=<?= $id ?>">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-check"></i> Po, Fshij
                </button>
                <a href="banners.php?action=list" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Jo, Anulo
                </a>
            </div>
        </form>
    <?php endif; ?>
<?php elseif ($action === 'view' && isset($banner)): ?>
    <h2 class="mb-4"><i class="fas fa-eye"></i> Detajet e Bannerit</h2>
    <div class="table-responsive">
        <table class="table table-bordered">
            <tr>
                <th>ID</th>
                <td><?= htmlspecialchars($banner['id']) ?></td>
            </tr>
            <tr>
                <th>Titulli</th>
                <td><?= htmlspecialchars($banner['title']) ?></td>
            </tr>
            <tr>
                <th>Foto</th>
                <td>
                    <?php if ($banner['image'] && file_exists('uploads/banners/' . $banner['image'])): ?>
                        <img src="uploads/banners/<?= htmlspecialchars($banner['image']) ?>" alt="<?= htmlspecialchars($banner['title']) ?>" width="200">
                    <?php else: ?>
                        <span>N/A</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Lidhja</th>
                <td><?= htmlspecialchars($banner['link']) ?></td>
            </tr>
            <tr>
                <th>Statusi</th>
                <td>
                    <span class="badge <?= $banner['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                        <?= $banner['is_active'] ? 'Aktiv' : 'Inaktiv' ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Krijuar</th>
                <td><?= htmlspecialchars($banner['created_at']) ?></td>
            </tr>
            <tr>
                <th>Ndryshuar</th>
                <td><?= htmlspecialchars($banner['updated_at'] ?? 'N/A') ?></td>
            </tr>
        </table>
    </div>
    <a href="banners.php?action=list" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Kthehu te Lista
    </a>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
<!-- DataTable Initialization -->
<script>
    $(document).ready(function() {
        $('#bannersTable').DataTable({
            "paging": true, // Enable pagination
            "searching": true, // Enable searching
            "info": true, // Show table info
            "order": [
                [4, "desc"]
            ], // Default sort by 'Krijuar' column (index 4)
            // Add buttons, search, info, pagination
            "dom": '<"row mb-3"' +
                '<"col-12 d-flex justify-content-between align-items-center"lBf>' +
                '>' +
                'rt' +
                '<"row mt-3"' +
                '<"col-sm-12 col-md-6 d-flex justify-content-start"i>' +
                '<"col-sm-12 col-md-6 d-flex justify-content-end"p>' +
                '>',
            // Add custom buttons for export
            "buttons": [ // Krijo banner te ri
                {
                    text: '<i class="fas fa-plus"></i> Krijo Banner',
                    action: function(e, dt, node, config) {
                        window.location.href = 'banners.php?action=create';
                    },
                    className: 'btn btn-success rounded-2'
                }, {
                    extend: 'csv',
                    text: '<i class="fas fa-file-csv"></i> Eksporto CSV',
                    className: 'btn btn-primary rounded-2'
                },
                {
                    extend: 'pdf',
                    text: '<i class="fas fa-file-pdf"></i> Eksporto PDF',
                    className: 'btn btn-primary rounded-2'
                },
                {
                    extend: 'colvis',
                    text: '<i class="fas fa-columns"></i> Kolonat',
                    className: 'btn btn-primary rounded-2',
                },
                {
                    extend: 'copy',
                    text: '<i class="fas fa-copy"></i> Kopjo',
                    className: 'btn btn-primary rounded-2',
                },
            ],
            initComplete: function() {
                // Change the buttons dont make as a group button
                var buttons = this.api().buttons();
                buttons.container().addClass('d-flex flex-wrap gap-2');
            },
            // Dutch
            "language": {
                // url: 'dataTables.german.json',
                url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/de-DE.json'
            }
        });
    });
</script>
<?php
require_once 'includes/footer.php';
?>