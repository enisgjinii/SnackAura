<?php
// admin/offers.php

require_once 'includes/db_connect.php';
require_once 'includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$perPage = 10;

// Function to sanitize input
function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        // Handle creating a new offer
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $link = sanitize($_POST['link'] ?? '');
        $start_date = $_POST['start_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $products = $_POST['products'] ?? [];

        // Handle image upload
        $image = $_FILES['image'] ?? null;
        $imageName = null;

        // Validate required fields
        if (empty($title)) {
            $message = '<div class="alert alert-danger">Titulli është i detyrueshëm.</div>';
        } elseif ($image && $image['error'] !== UPLOAD_ERR_NO_FILE) {
            // If image is uploaded, validate it
            if ($image['error'] !== UPLOAD_ERR_OK) {
                $message = '<div class="alert alert-danger">Gabim gjatë ngarkimit të imazhit.</div>';
            } else {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($image['type'], $allowedTypes)) {
                    $message = '<div class="alert alert-danger">Format i imazhit i pavlefshëm. Lejohen JPEG, PNG, GIF.</div>';
                } elseif ($image['size'] > 5 * 1024 * 1024) { // 5MB limit
                    $message = '<div class="alert alert-danger">Imazhi nuk mund të jetë më i madh se 5MB.</div>';
                } else {
                    $targetDir = 'uploads/offers/';
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }
                    $imageName = uniqid() . '_' . basename($image['name']);
                    $targetFile = $targetDir . $imageName;

                    if (!move_uploaded_file($image['tmp_name'], $targetFile)) {
                        $message = '<div class="alert alert-danger">Deshtoi ngarkimi i imazhit.</div>';
                    }
                }
            }
        }

        if (empty($message)) {
            // Insert offer into database
            $stmt = $pdo->prepare('INSERT INTO offers (title, description, image, link, start_date, end_date, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
            try {
                $stmt->execute([$title, $description, $imageName, $link, $start_date, $end_date, $is_active]);
                $offer_id = $pdo->lastInsertId();

                // Insert into offer_products
                if (!empty($products)) {
                    $stmt_op = $pdo->prepare('INSERT INTO offer_products (offer_id, product_id) VALUES (?, ?)');
                    foreach ($products as $product_id) {
                        $stmt_op->execute([$offer_id, $product_id]);
                    }
                }

                header('Location: offers.php?action=list&message=' . urlencode("Oferta u krijua me sukses."));
                exit();
            } catch (PDOException $e) {
                error_log("Error creating offer: " . $e->getMessage());
                $message = '<div class="alert alert-danger">Deshtoi krijimi i ofertës. Ju lutem provoni më vonë.</div>';
            }
        }
    } elseif ($action === 'edit' && $id > 0) {
        // Handle editing an existing offer
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $link = sanitize($_POST['link'] ?? '');
        $start_date = $_POST['start_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $products = $_POST['products'] ?? [];

        // Handle image upload
        $image = $_FILES['image'] ?? null;
        $imageName = null;

        // Validate required fields
        if (empty($title)) {
            $message = '<div class="alert alert-danger">Titulli është i detyrueshëm.</div>';
        } elseif ($image && $image['error'] !== UPLOAD_ERR_NO_FILE) {
            // If image is uploaded, validate it
            if ($image['error'] !== UPLOAD_ERR_OK) {
                $message = '<div class="alert alert-danger">Gabim gjatë ngarkimit të imazhit.</div>';
            } else {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($image['type'], $allowedTypes)) {
                    $message = '<div class="alert alert-danger">Format i imazhit i pavlefshëm. Lejohen JPEG, PNG, GIF.</div>';
                } elseif ($image['size'] > 5 * 1024 * 1024) { // 5MB limit
                    $message = '<div class="alert alert-danger">Imazhi nuk mund të jetë më i madh se 5MB.</div>';
                } else {
                    $targetDir = 'uploads/offers/';
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }
                    $imageName = uniqid() . '_' . basename($image['name']);
                    $targetFile = $targetDir . $imageName;

                    if (!move_uploaded_file($image['tmp_name'], $targetFile)) {
                        $message = '<div class="alert alert-danger">Deshtoi ngarkimi i imazhit.</div>';
                    }
                }
            }
        }

        if (empty($message)) {
            try {
                if ($imageName) {
                    // Fetch existing offer to delete old image
                    $stmt_old = $pdo->prepare('SELECT image FROM offers WHERE id = ?');
                    $stmt_old->execute([$id]);
                    $existingOffer = $stmt_old->fetch(PDO::FETCH_ASSOC);
                    if ($existingOffer && $existingOffer['image'] && file_exists('uploads/offers/' . $existingOffer['image'])) {
                        unlink('uploads/offers/' . $existingOffer['image']);
                    }

                    // Update offer with new image
                    $stmt = $pdo->prepare('UPDATE offers SET title = ?, description = ?, image = ?, link = ?, start_date = ?, end_date = ?, is_active = ?, updated_at = NOW() WHERE id = ?');
                    $stmt->execute([$title, $description, $imageName, $link, $start_date, $end_date, $is_active, $id]);
                } else {
                    // Update offer without changing image
                    $stmt = $pdo->prepare('UPDATE offers SET title = ?, description = ?, link = ?, start_date = ?, end_date = ?, is_active = ?, updated_at = NOW() WHERE id = ?');
                    $stmt->execute([$title, $description, $link, $start_date, $end_date, $is_active, $id]);
                }

                // Update offer_products
                // First, delete existing associations
                $stmt_del = $pdo->prepare('DELETE FROM offer_products WHERE offer_id = ?');
                $stmt_del->execute([$id]);

                // Then, insert new associations
                if (!empty($products)) {
                    $stmt_op = $pdo->prepare('INSERT INTO offer_products (offer_id, product_id) VALUES (?, ?)');
                    foreach ($products as $product_id) {
                        $stmt_op->execute([$id, $product_id]);
                    }
                }

                header('Location: offers.php?action=list&message=' . urlencode("Oferta u azhurnua me sukses."));
                exit();
            } catch (PDOException $e) {
                error_log("Error editing offer (ID: $id): " . $e->getMessage());
                $message = '<div class="alert alert-danger">Deshtoi azhurnimi i ofertës. Ju lutem provoni më vonë.</div>';
            }
        }
    } elseif ($action === 'delete' && $id > 0) {
        // Handle deleting an offer
        // Fetch offer to delete image
        $stmt = $pdo->prepare('SELECT image FROM offers WHERE id = ?');
        $stmt->execute([$id]);
        $offer = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($offer) {
            try {
                // Delete offer (due to foreign key constraints, offer_products will be deleted automatically)
                $stmt_del = $pdo->prepare('DELETE FROM offers WHERE id = ?');
                $stmt_del->execute([$id]);

                // Delete image file
                if ($offer['image'] && file_exists('uploads/offers/' . $offer['image'])) {
                    unlink('uploads/offers/' . $offer['image']);
                }

                header('Location: offers.php?action=list&message=' . urlencode("Oferta u fshi me sukses."));
                exit();
            } catch (PDOException $e) {
                error_log("Error deleting offer (ID: $id): " . $e->getMessage());
                header('Location: offers.php?action=list&message=' . urlencode("Deshtoi fshirja e ofertës. Ju lutem provoni më vonë."));
                exit();
            }
        } else {
            header('Location: offers.php?action=list&message=' . urlencode("Oferta nuk u gjet."));
            exit();
        }
    } elseif ($action === 'toggle_status' && $id > 0) {
        // Handle toggling offer status
        $stmt = $pdo->prepare('SELECT is_active FROM offers WHERE id = ?');
        $stmt->execute([$id]);
        $offer = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($offer) {
            $new_status = $offer['is_active'] ? 0 : 1;
            try {
                $stmt_update = $pdo->prepare('UPDATE offers SET is_active = ?, updated_at = NOW() WHERE id = ?');
                $stmt_update->execute([$new_status, $id]);
                $status_text = $new_status ? 'aktivizuar' : 'deaktivizuar';
                header('Location: offers.php?action=list&message=' . urlencode("Oferta është {$status_text} me sukses."));
                exit();
            } catch (PDOException $e) {
                error_log("Error toggling offer status (ID: $id): " . $e->getMessage());
                header('Location: offers.php?action=list&message=' . urlencode("Deshtoi ndryshimi i statusit të ofertës. Ju lutem provoni më vonë."));
                exit();
            }
        } else {
            header('Location: offers.php?action=list&message=' . urlencode("Oferta nuk u gjet."));
            exit();
        }
    } elseif ($action === 'export') {
        // Handle exporting offers to CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=offers_' . date('Ymd') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Titulli', 'Përshkrimi', 'Imazhi', 'Lidhja', 'Data Fillestare', 'Data Përfundimtare', 'Statusi', 'Krijuar', 'Azhurnuar']);

        try {
            $stmt = $pdo->prepare('SELECT * FROM offers ORDER BY created_at DESC');
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['id'],
                    $row['title'],
                    $row['description'],
                    $row['image'],
                    $row['link'],
                    $row['start_date'],
                    $row['end_date'],
                    $row['is_active'] ? 'Aktiv' : 'Inaktiv',
                    $row['created_at'],
                    $row['updated_at']
                ]);
            }
            fclose($output);
            exit();
        } catch (PDOException $e) {
            error_log("Error exporting offers: " . $e->getMessage());
            $message = '<div class="alert alert-danger">Deshtoi eksportimi i ofertave. Ju lutem provoni më vonë.</div>';
        }
    }
}

// Handle GET requests for fetching data
if ($action === 'edit' && $id > 0) {
    // Fetch offer data for editing
    $stmt = $pdo->prepare('SELECT * FROM offers WHERE id = ?');
    $stmt->execute([$id]);
    $offer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$offer) {
        header('Location: offers.php?action=list&message=' . urlencode("Oferta nuk u gjet."));
        exit();
    }

    // Fetch associated products
    $stmt_p = $pdo->prepare('SELECT product_id FROM offer_products WHERE offer_id = ?');
    $stmt_p->execute([$id]);
    $associated_products = $stmt_p->fetchAll(PDO::FETCH_COLUMN);
} elseif ($action === 'view' && $id > 0) {
    // Fetch offer data for viewing
    $stmt = $pdo->prepare('SELECT * FROM offers WHERE id = ?');
    $stmt->execute([$id]);
    $offer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$offer) {
        header('Location: offers.php?action=list&message=' . urlencode("Oferta nuk u gjet."));
        exit();
    }

    // Fetch associated products
    $stmt_p = $pdo->prepare('SELECT p.name FROM offer_products op JOIN products p ON op.product_id = p.id WHERE op.offer_id = ?');
    $stmt_p->execute([$id]);
    $associated_products = $stmt_p->fetchAll(PDO::FETCH_COLUMN);
}

// Fetch all active products for selection
try {
    $stmt_products = $pdo->prepare('SELECT id, name FROM products WHERE is_active = 1 ORDER BY name ASC');
    $stmt_products->execute();
    $all_products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching products: " . $e->getMessage());
    $all_products = [];
    $message = '<div class="alert alert-danger">Deshtoi marrja e produkteve. Ju lutem provoni më vonë.</div>';
}
?>

<?php if ($action === 'list'): ?>
    <div class="container mt-4">
        <h2 class="mb-4">Menaxhimi i Ofertave</h2>
        <?php
        if (isset($_GET['message'])) {
            echo '<div class="alert alert-info">' . htmlspecialchars($_GET['message']) . '</div>';
        } elseif ($message) {
            echo $message;
        }
        ?>
        <div class="d-flex justify-content-between mb-3">
            <div>
                <a href="offers.php?action=create" class="btn btn-primary me-2">
                    <i class="fas fa-plus"></i> Krijo Oferta të Reja
                </a>
                <a href="offers.php?action=export" class="btn btn-secondary">
                    <i class="fas fa-file-export"></i> Eksporto CSV
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <table id="offersTable" class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Foto</th>
                        <th>Titulli</th>
                        <th>Lidhja</th>
                        <th>Data Fillestare</th>
                        <th>Data Përfundimtare</th>
                        <th>Statusi</th>
                        <th>Krijuar</th>
                        <th>Veprime</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_products)): ?>
                        <tr>
                            <td colspan="8" class="text-center">Nuk u gjetën produkte aktive për t'u lidhur me ofertat.</td>
                        </tr>
                    <?php endif; ?>
                    <?php
                    // Fetch offers with pagination
                    try {
                        // Calculate total offers
                        $stmt_count = $pdo->prepare('SELECT COUNT(*) FROM offers');
                        $stmt_count->execute();
                        $total_offers = $stmt_count->fetchColumn();

                        // Calculate total pages
                        $totalPages = ceil($total_offers / $perPage);
                        if ($totalPages == 0) $totalPages = 1;

                        // Calculate offset
                        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                        $offset = ($page - 1) * $perPage;

                        // Fetch offers
                        $stmt_offers = $pdo->prepare('SELECT * FROM offers ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
                        $stmt_offers->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
                        $stmt_offers->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
                        $stmt_offers->execute();
                        $offers = $stmt_offers->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        error_log("Error fetching offers: " . $e->getMessage());
                        $offers = [];
                        echo '<tr><td colspan="8" class="text-center">Deshtoi marrja e ofertave. Ju lutem provoni më vonë.</td></tr>';
                    }

                    foreach ($offers as $offer):
                        // Fetch associated products count
                        $stmt_p = $pdo->prepare('SELECT COUNT(*) FROM offer_products WHERE offer_id = ?');
                        $stmt_p->execute([$offer['id']]);
                        $products_count = $stmt_p->fetchColumn();
                    ?>
                        <tr>
                            <td>
                                <?php if ($offer['image'] && file_exists('uploads/offers/' . $offer['image'])): ?>
                                    <img src="uploads/offers/<?= htmlspecialchars($offer['image']) ?>" alt="<?= htmlspecialchars($offer['title']) ?>" width="100">
                                <?php else: ?>
                                    <i class="fas fa-image fa-2x text-secondary"></i>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($offer['title']) ?></td>
                            <td>
                                <?php if ($offer['link']): ?>
                                    <a href="<?= htmlspecialchars($offer['link']) ?>" target="_blank"><?= htmlspecialchars($offer['link']) ?></a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($offer['start_date']) ?></td>
                            <td><?= htmlspecialchars($offer['end_date']) ?></td>
                            <td>
                                <span class="badge <?= $offer['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $offer['is_active'] ? 'Aktiv' : 'Inaktiv' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($offer['created_at']) ?></td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <a href="offers.php?action=view&id=<?= $offer['id'] ?>" class="btn btn-sm btn-info" title="Shiko">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="offers.php?action=edit&id=<?= $offer['id'] ?>" class="btn btn-sm btn-warning" title="Ndrysho">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="offers.php?action=delete&id=<?= $offer['id'] ?>" class="btn btn-sm btn-danger" title="Fshij" onclick="return confirm('A jeni i sigurtë që dëshironi të fshini këtë ofertë?');">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                    <a href="offers.php?action=toggle_status&id=<?= $offer['id'] ?>" class="btn btn-sm <?= $offer['is_active'] ? 'btn-secondary' : 'btn-success' ?>" title="<?= $offer['is_active'] ? 'Deaktivizo' : 'Aktivizo' ?>">
                                        <i class="fas <?= $offer['is_active'] ? 'fa-toggle-off' : 'fa-toggle-on' ?>"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?action=list&page=<?= $p ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
<?php elseif ($action === 'create'): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-plus"></i> Krijo Oferta të Reja</h2>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <form method="POST" action="offers.php?action=create" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="title" class="form-label">Titulli<span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="title" name="title" required maxlength="255" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Përshkrimi</label>
                <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
                <label for="image" class="form-label">Imazhi</label>
                <input type="file" class="form-control" id="image" name="image" accept="image/*">
            </div>
            <div class="mb-3">
                <label for="link" class="form-label">Lidhja</label>
                <input type="url" class="form-control" id="link" name="link" maxlength="255" value="<?= htmlspecialchars($_POST['link'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="start_date" class="form-label">Data Fillestare</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="end_date" class="form-label">Data Përfundimtare</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="products" class="form-label">Produkte të Lidhura</label>
                <select class="form-select" id="products" name="products[]" multiple>
                    <?php foreach ($all_products as $product): ?>
                        <option value="<?= $product['id'] ?>" <?= (isset($_POST['products']) && in_array($product['id'], $_POST['products'])) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($product['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" <?= (isset($_POST['is_active']) && $_POST['is_active']) ? 'checked' : 'checked' ?>>
                <label class="form-check-label" for="is_active">Aktiv</label>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Krijo Oferta
                </button>
                <a href="offers.php?action=list" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Anulo
                </a>
            </div>
        </form>
    </div>
<?php elseif ($action === 'edit' && isset($offer)): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-edit"></i> Ndrysho Oferta</h2>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <form method="POST" action="offers.php?action=edit&id=<?= $offer['id'] ?>" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="title" class="form-label">Titulli<span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="title" name="title" required maxlength="255" value="<?= htmlspecialchars($_POST['title'] ?? $offer['title']) ?>">
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Përshkrimi</label>
                <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($_POST['description'] ?? $offer['description']) ?></textarea>
            </div>
            <div class="mb-3">
                <label for="image" class="form-label">Imazhi</label>
                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                <?php if ($offer['image'] && file_exists('uploads/offers/' . $offer['image'])): ?>
                    <img src="uploads/offers/<?= htmlspecialchars($offer['image']) ?>" alt="<?= htmlspecialchars($offer['title']) ?>" width="150" class="mt-2">
                <?php endif; ?>
            </div>
            <div class="mb-3">
                <label for="link" class="form-label">Lidhja</label>
                <input type="url" class="form-control" id="link" name="link" maxlength="255" value="<?= htmlspecialchars($_POST['link'] ?? $offer['link']) ?>">
            </div>
            <div class="mb-3">
                <label for="start_date" class="form-label">Data Fillestare</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($_POST['start_date'] ?? $offer['start_date']) ?>">
            </div>
            <div class="mb-3">
                <label for="end_date" class="form-label">Data Përfundimtare</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($_POST['end_date'] ?? $offer['end_date']) ?>">
            </div>
            <div class="mb-3">
                <label for="products" class="form-label">Produkte të Lidhura</label>
                <select class="form-select" id="products" name="products[]" multiple>
                    <?php foreach ($all_products as $product): ?>
                        <option value="<?= $product['id'] ?>" <?= (isset($associated_products) && in_array($product['id'], $associated_products)) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($product['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" <?= ($offer['is_active'] || (isset($_POST['is_active']) && $_POST['is_active'])) ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">Aktiv</label>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Ruaj Ndryshimet
                </button>
                <a href="offers.php?action=list" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Anulo
                </a>
            </div>
        </form>
    </div>
<?php elseif ($action === 'delete' && $id > 0): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-trash-alt"></i> Fshij Oferta</h2>
        <?php
        $stmt = $pdo->prepare('SELECT title, image FROM offers WHERE id = ?');
        $stmt->execute([$id]);
        $offer = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$offer):
        ?>
            <div class="alert alert-danger">Oferta nuk u gjet.</div>
            <a href="offers.php?action=list" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kthehu te Lista
            </a>
        <?php else: ?>
            <div class="alert alert-warning">
                A jeni i sigurtë që dëshironi të fshini ofertën <strong><?= htmlspecialchars($offer['title']) ?></strong>?
            </div>
            <form method="POST" action="offers.php?action=delete&id=<?= $id ?>">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-check"></i> Po, Fshij
                    </button>
                    <a href="offers.php?action=list" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Jo, Anulo
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
<?php elseif ($action === 'view' && isset($offer)): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-eye"></i> Detajet e Ofertës</h2>
        <div class="table-responsive">
            <table class="table table-bordered">
                <tr>
                    <th>ID</th>
                    <td><?= htmlspecialchars($offer['id']) ?></td>
                </tr>
                <tr>
                    <th>Titulli</th>
                    <td><?= htmlspecialchars($offer['title']) ?></td>
                </tr>
                <tr>
                    <th>Përshkrimi</th>
                    <td><?= nl2br(htmlspecialchars($offer['description'])) ?></td>
                </tr>
                <tr>
                    <th>Foto</th>
                    <td>
                        <?php if ($offer['image'] && file_exists('uploads/offers/' . $offer['image'])): ?>
                            <img src="uploads/offers/<?= htmlspecialchars($offer['image']) ?>" alt="<?= htmlspecialchars($offer['title']) ?>" width="200">
                        <?php else: ?>
                            <i class="fas fa-image fa-3x text-secondary"></i>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Lidhja</th>
                    <td>
                        <?php if ($offer['link']): ?>
                            <a href="<?= htmlspecialchars($offer['link']) ?>" target="_blank"><?= htmlspecialchars($offer['link']) ?></a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Data Fillestare</th>
                    <td><?= htmlspecialchars($offer['start_date']) ?></td>
                </tr>
                <tr>
                    <th>Data Përfundimtare</th>
                    <td><?= htmlspecialchars($offer['end_date']) ?></td>
                </tr>
                <tr>
                    <th>Statusi</th>
                    <td>
                        <span class="badge <?= $offer['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $offer['is_active'] ? 'Aktiv' : 'Inaktiv' ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Krijuar</th>
                    <td><?= htmlspecialchars($offer['created_at']) ?></td>
                </tr>
                <tr>
                    <th>Azhurnuar</th>
                    <td><?= htmlspecialchars($offer['updated_at']) ?></td>
                </tr>
                <tr>
                    <th>Produkte të Lidhura</th>
                    <td>
                        <?php if (!empty($associated_products)): ?>
                            <ul>
                                <?php foreach ($associated_products as $product_name): ?>
                                    <li><?= htmlspecialchars($product_name) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        <a href="offers.php?action=list" class="btn btn-secondary">
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
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymou