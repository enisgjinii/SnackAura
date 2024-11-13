<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$perPage = 10;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $link = trim($_POST['link'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $image = $_FILES['image'] ?? null;

        if (empty($title) || empty($image)) {
            $message = '<div class="alert alert-danger">Të gjitha fushat me yll janë të detyrueshme.</div>';
        } elseif ($image && $image['error'] !== UPLOAD_ERR_OK) {
            $message = '<div class="alert alert-danger">Gabim gjatë ngarkimit të imazhit.</div>';
        } else {
            // Validate image
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($image['type'], $allowedTypes)) {
                $message = '<div class="alert alert-danger">Format i imazhit i pavlefshëm. Lejohen JPEG, PNG, GIF.</div>';
            } else {
                $targetDir = 'uploads/banners/';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                $filename = uniqid() . '_' . basename($image['name']);
                $targetFile = $targetDir . $filename;

                if (move_uploaded_file($image['tmp_name'], $targetFile)) {
                    $stmt = $pdo->prepare('INSERT INTO banners (title, image, link, is_active, created_at) VALUES (?, ?, ?, ?, NOW())');
                    try {
                        $stmt->execute([$title, $filename, $link, $is_active]);
                        header('Location: banners.php?action=list&message=' . urlencode("Banner u krijua me sukses."));
                        exit();
                    } catch (PDOException $e) {
                        error_log("Error creating banner: " . $e->getMessage());
                        $message = '<div class="alert alert-danger">Deshtoi krijimi i bannerit. Ju lutem provoni më vonë.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger">Deshtoi ngarkimi i imazhit.</div>';
                }
            }
        }
    } elseif ($action === 'edit' && $id > 0) {
        $title = trim($_POST['title'] ?? '');
        $link = trim($_POST['link'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $image = $_FILES['image'] ?? null;

        if (empty($title)) {
            $message = '<div class="alert alert-danger">Titulli është i detyrueshëm.</div>';
        } else {
            if ($image && $image['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($image['type'], $allowedTypes)) {
                    $message = '<div class="alert alert-danger">Format i imazhit i pavlefshëm. Lejohen JPEG, PNG, GIF.</div>';
                } else {
                    $targetDir = 'uploads/banners/';
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }
                    $filename = uniqid() . '_' . basename($image['name']);
                    $targetFile = $targetDir . $filename;

                    if (move_uploaded_file($image['tmp_name'], $targetFile)) {
                        // Fetch existing banner to delete old image
                        $stmt = $pdo->prepare('SELECT image FROM banners WHERE id = ?');
                        $stmt->execute([$id]);
                        $existingBanner = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($existingBanner && file_exists($targetDir . $existingBanner['image'])) {
                            unlink($targetDir . $existingBanner['image']);
                        }

                        $stmt = $pdo->prepare('UPDATE banners SET title = ?, image = ?, link = ?, is_active = ?, updated_at = NOW() WHERE id = ?');
                        $params = [$title, $filename, $link, $is_active, $id];
                    } else {
                        $message = '<div class="alert alert-danger">Deshtoi ngarkimi i imazhit.</div>';
                        $params = [$title, $link, $is_active, $id];
                    }
                }
            } else {
                $stmt = $pdo->prepare('UPDATE banners SET title = ?, link = ?, is_active = ?, updated_at = NOW() WHERE id = ?');
                $params = [$title, $link, $is_active, $id];
            }

            if (empty($message)) {
                try {
                    $stmt->execute($params);
                    header('Location: banners.php?action=list&message=' . urlencode("Banner u azhurnua me sukses."));
                    exit();
                } catch (PDOException $e) {
                    error_log("Error updating banner (Banner ID: $id): " . $e->getMessage());
                    $message = '<div class="alert alert-danger">Deshtoi azhurnimi i bannerit. Ju lutem provoni më vonë.</div>';
                }
            }
        }
    } elseif ($action === 'delete' && $id > 0) {
        // Fetch banner to delete image
        $stmt = $pdo->prepare('SELECT image FROM banners WHERE id = ?');
        $stmt->execute([$id]);
        $banner = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($banner) {
            $stmt = $pdo->prepare('DELETE FROM banners WHERE id = ?');
            try {
                $stmt->execute([$id]);
                // Delete image file
                $imagePath = 'uploads/banners/' . $banner['image'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
                header('Location: banners.php?action=list&message=' . urlencode("Banner u fshi me sukses."));
                exit();
            } catch (PDOException $e) {
                error_log("Error deleting banner (Banner ID: $id): " . $e->getMessage());
                header('Location: banners.php?action=list&message=' . urlencode("Deshtoi fshirja e bannerit. Ju lutem provoni më vonë."));
                exit();
            }
        } else {
            header('Location: banners.php?action=list&message=' . urlencode("Banner nuk u gjet."));
            exit();
        }
    } elseif ($action === 'assign_admin' && $id > 0) {
        // If banners are associated with admins, implement here
        // For simplicity, this action is omitted unless specified
    } elseif ($action === 'export') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=banners_' . date('Ymd') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Titulli', 'Imazhi', 'Lidhja', 'Statusi', 'Krijuar', 'Azhurnuar']);
        try {
            $stmt = $pdo->query('SELECT * FROM banners ORDER BY created_at DESC');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['id'],
                    $row['title'],
                    $row['image'],
                    $row['link'],
                    $row['is_active'] ? 'Aktiv' : 'Inaktiv',
                    $row['created_at'],
                    $row['updated_at']
                ]);
            }
            fclose($output);
            exit();
        } catch (PDOException $e) {
            error_log("Error exporting banners: " . $e->getMessage());
            $message = '<div class="alert alert-danger">Deshtoi eksportimi i bannerave. Ju lutem provoni më vonë.</div>';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'toggle_status' && $id > 0) {
        $stmt = $pdo->prepare('SELECT is_active FROM banners WHERE id = ?');
        $stmt->execute([$id]);
        $banner = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($banner) {
            $new_status = $banner['is_active'] ? 0 : 1;
            $stmt = $pdo->prepare('UPDATE banners SET is_active = ? WHERE id = ?');
            try {
                $stmt->execute([$new_status, $id]);
                $status_text = $new_status ? 'aktivizuar' : 'deaktivizuar';
                header('Location: banners.php?action=list&message=' . urlencode("Banneri është {$status_text} me sukses."));
                exit();
            } catch (PDOException $e) {
                error_log("Error toggling banner status (Banner ID: $id): " . $e->getMessage());
                header('Location: banners.php?action=list&message=' . urlencode("Deshtoi ndryshimi i statusit të bannerit. Ju lutem provoni më vonë."));
                exit();
            }
        } else {
            header('Location: banners.php?action=list&message=' . urlencode("Banner nuk u gjet."));
            exit();
        }
    }
}

if ($action === 'list') {
    $search = trim($_GET['search'] ?? '');
    $search_query = '';
    $params = [];
    if ($search) {
        $search_query = ' WHERE b.title LIKE :search OR b.link LIKE :search ';
        $params[':search'] = '%' . $search . '%';
    }

    $sort = $_GET['sort'] ?? 'created_at';
    $allowed_sort = ['title', 'created_at', 'is_active'];
    if (!in_array($sort, $allowed_sort)) {
        $sort = 'created_at';
    }
    $order = $_GET['order'] ?? 'DESC';
    $order = ($order === 'ASC') ? 'ASC' : 'DESC';

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $perPage;

    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM banners b {$search_query}");
        $count_stmt->execute($params);
        $total = $count_stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error counting banners: " . $e->getMessage());
        $total = 0;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM banners b {$search_query} ORDER BY b.{$sort} {$order} LIMIT :limit OFFSET :offset");
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching banners: " . $e->getMessage());
        $banners = [];
        $message = '<div class="alert alert-danger">Deshtoi marrja e bannerave. Ju lutem provoni më vonë.</div>';
    }

    $totalPages = ceil($total / $perPage);
} elseif ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM banners WHERE id = ?');
    $stmt->execute([$id]);
    $banner = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$banner) {
        header('Location: banners.php?action=list&message=' . urlencode("Banner nuk u gjet."));
        exit();
    }
} elseif ($action === 'view' && $id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM banners WHERE id = ?');
    $stmt->execute([$id]);
    $banner = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$banner) {
        header('Location: banners.php?action=list&message=' . urlencode("Banner nuk u gjet."));
        exit();
    }
} elseif ($action === 'assign_admin' && $id > 0) {
    // If banners are associated with admins, implement here
    // For simplicity, this action is omitted unless specified
}
?>
<?php if ($action === 'list'): ?>
    <div class="container mt-4">
        <h2 class="mb-4">Menaxhimi i Bënarave</h2>
        <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-info"><?= htmlspecialchars($_GET['message']) ?></div>
        <?php elseif ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <div class="d-flex justify-content-between mb-3">
            <div>
                <a href="banners.php?action=create" class="btn btn-primary me-2">
                    <i class="fas fa-plus"></i> Krijo Banner të Ri
                </a>
                <a href="banners.php?action=export" class="btn btn-secondary">
                    <i class="fas fa-file-export"></i> Eksporto CSV
                </a>
            </div>
        </div>
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
                        <tr>
                            <td colspan="6" class="text-center">Nuk u gjetën bannerë.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($banners as $banner): ?>
                            <tr>
                                <td>
                                    <img src="uploads/banners/<?= htmlspecialchars($banner['image']) ?>" alt="<?= htmlspecialchars($banner['title']) ?>" width="100">
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
        <!-- Pagination (Optional if using DataTables) -->
        <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?action=list&page=<?= $p ?>&sort=<?= $sort ?>&order=<?= $order ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
<?php elseif ($action === 'create'): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-plus"></i> Krijo Banner të Ri</h2>
        <?php if ($message): ?>
            <?= $message ?>
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
                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" <?= (isset($_POST['is_active']) && $_POST['is_active']) ? 'checked' : 'checked' ?>>
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
    </div>
<?php elseif ($action === 'edit' && isset($banner)): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-edit"></i> Ndrysho Banner</h2>
        <?php if ($message): ?>
            <?= $message ?>
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
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-trash-alt"></i> Fshij Banner</h2>
        <?php
        $stmt = $pdo->prepare('SELECT title, image FROM banners WHERE id = ?');
        $stmt->execute([$id]);
        $banner = $stmt->fetch(PDO::FETCH_ASSOC);
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
    </div>
<?php elseif ($action === 'view' && isset($banner)): ?>
    <div class="container mt-4">
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
                        <img src="uploads/banners/<?= htmlspecialchars($banner['image']) ?>" alt="<?= htmlspecialchars($banner['title']) ?>" width="200">
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
    </div>
<?php endif; ?>
<!-- Scripts and Styles -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        $('.form-select').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });

        $('#bannersTable').DataTable({
            "paging": false,
            "searching": true,
            "info": false,
            "order": [],
            "language": {
                "search": "Kërko:",
                "emptyTable": "Nuk u gjetën bannerë."
            }
        });
    });
</script>

<?php
require_once 'includes/footer.php';
?>