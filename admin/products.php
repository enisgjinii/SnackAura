<?php
ob_start(); // Fillon buferimin e output-it
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Initialize variables
$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

// Funksione për të marrë të dhënat e nevojshme
function getCategories($pdo)
{
    try {
        return $pdo->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getExtras($pdo)
{
    try {
        return $pdo->query('SELECT * FROM extras ORDER BY name ASC')->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getSauces($pdo)
{
    try {
        return $pdo->query('SELECT * FROM sauces ORDER BY name ASC')->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getTop10Products($pdo, $completed_status_id = 3)
{
    try {
        $query = '
            SELECT p.*, SUM(oi.quantity) AS total_sold
            FROM products p
            JOIN order_items oi ON p.id = oi.product_id
            JOIN orders o ON oi.order_id = o.id
            WHERE o.status_id = ?
            GROUP BY p.id
            ORDER BY total_sold DESC
            LIMIT 10
        ';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$completed_status_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getMixedProducts($pdo, $exclude_product_id = null)
{
    try {
        $query = 'SELECT * FROM products ORDER BY name ASC';
        if ($exclude_product_id) {
            $query .= ' WHERE id != ?';
            $stmt = $pdo->prepare($query);
            $stmt->execute([$exclude_product_id]);
        } else {
            $stmt = $pdo->query($query);
        }
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Merrni të dhënat e kategorive, extras, salcave dhe opsioneve të përzierjes
$categories = getCategories($pdo);
$extras = getExtras($pdo);
$sauces = getSauces($pdo);

// Menaxhojmë veprimet
switch ($action) {
    case 'add':
    case 'edit':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Përgatitja e të dhënave
            $product_code = trim($_POST['product_code']);
            $name = trim($_POST['name']);
            $price = trim($_POST['price']);
            $description = trim($_POST['description']);
            $allergies = trim($_POST['allergies']);
            $category_id = (int)$_POST['category_id'];
            $is_new = isset($_POST['is_new']) ? 1 : 0;
            $is_offer = isset($_POST['is_offer']) ? 1 : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $image_url = trim($_POST['image_url']);
            $selected_extras = $_POST['extras'] ?? [];
            $selected_sauces = $_POST['sauces'] ?? [];
            $selected_mixes = $_POST['mixes'] ?? [];

            // Validimet
            if ($product_code === '' || $name === '' || $price === '' || $category_id === 0) {
                $message = '<div class="alert alert-danger">Kodi i produktit, Emri, Çmimi, dhe Kategoria janë të nevojshme.</div>';
            } elseif (!is_numeric($price) || $price < 0) {
                $message = '<div class="alert alert-danger">Ju lutem, futni një çmim të vlefshëm pozitiv.</div>';
            } else {
                // Kontrollo unikalitetin e kodi të produktit
                $stmt_check = $pdo->prepare('SELECT COUNT(*) FROM products WHERE product_code = ?' . ($action === 'edit' ? ' AND id != ?' : ''));
                $params = [$product_code];
                if ($action === 'edit') {
                    $params[] = $id;
                }
                $stmt_check->execute($params);
                if ($stmt_check->fetchColumn() > 0) {
                    $message = '<div class="alert alert-danger">Kodi i produktit tashmë ekziston. Ju lutem, zgjidhni një kod tjetër.</div>';
                } else {
                    // Përgatitni SQL-in për shtim ose editim
                    if ($action === 'add') {
                        $sql = 'INSERT INTO products (product_code, category_id, name, price, description, allergies, image_url, is_new, is_offer, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                        $params = [$product_code, $category_id, $name, $price, $description, $allergies, $image_url, $is_new, $is_offer, $is_active];
                    } else {
                        $sql = 'UPDATE products SET product_code = ?, category_id = ?, name = ?, price = ?, description = ?, allergies = ?, image_url = ?, is_new = ?, is_offer = ?, is_active = ? WHERE id = ?';
                        $params = [$product_code, $category_id, $name, $price, $description, $allergies, $image_url, $is_new, $is_offer, $is_active, $id];
                    }
                    // Ekzekuto query-n
                    $stmt = $pdo->prepare($sql);
                    try {
                        $stmt->execute($params);
                        if ($action === 'add') {
                            $product_id = $pdo->lastInsertId();
                        } else {
                            $product_id = $id;
                        }
                        // Menaxho extras
                        $pdo->prepare('DELETE FROM product_extras WHERE product_id = ?')->execute([$product_id]);
                        if (!empty($selected_extras)) {
                            $stmt_extras = $pdo->prepare('INSERT INTO product_extras (product_id, extra_id) VALUES (?, ?)');
                            foreach ($selected_extras as $extra_id) {
                                $stmt_extras->execute([$product_id, $extra_id]);
                            }
                        }
                        // Menaxho salcave
                        $pdo->prepare('DELETE FROM product_sauces WHERE product_id = ?')->execute([$product_id]);
                        if (!empty($selected_sauces)) {
                            $stmt_sauces = $pdo->prepare('INSERT INTO product_sauces (product_id, sauce_id) VALUES (?, ?)');
                            foreach ($selected_sauces as $sauce_id) {
                                $stmt_sauces->execute([$product_id, $sauce_id]);
                            }
                        }
                        // Menaxho opsionet e përzierjes
                        $pdo->prepare('DELETE FROM product_mixes WHERE main_product_id = ?')->execute([$product_id]);
                        if (!empty($selected_mixes)) {
                            $stmt_mixes = $pdo->prepare('INSERT INTO product_mixes (main_product_id, mixed_product_id) VALUES (?, ?)');
                            foreach ($selected_mixes as $mixed_product_id) {
                                $stmt_mixes->execute([$product_id, $mixed_product_id]);
                            }
                        }
                        // Redirect përpara
                        header('Location: products.php');
                        exit();
                    } catch (PDOException $e) {
                        $message = '<div class="alert alert-danger">Gabim gjatë përpunimit të të dhënave: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                }
            }
        }

        // Nëse është editim, marr detajet e produktit
        if ($action === 'edit') {
            try {
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $product = $stmt->fetch();
                if (!$product) {
                    echo '<div class="alert alert-danger">Produkti nuk u gjet.</div>';
                    require_once 'includes/footer.php';
                    exit();
                }
                // Marr extras të lidhura
                $stmt_extras = $pdo->prepare('SELECT extra_id FROM product_extras WHERE product_id = ?');
                $stmt_extras->execute([$id]);
                $selected_extras = $stmt_extras->fetchAll(PDO::FETCH_COLUMN);
                // Marr salcave të lidhura
                $stmt_sauces = $pdo->prepare('SELECT sauce_id FROM product_sauces WHERE product_id = ?');
                $stmt_sauces->execute([$id]);
                $selected_sauces = $stmt_sauces->fetchAll(PDO::FETCH_COLUMN);
                // Marr opsionet e përzierjes të lidhura
                $stmt_mixes = $pdo->prepare('SELECT mixed_product_id FROM product_mixes WHERE main_product_id = ?');
                $stmt_mixes->execute([$id]);
                $selected_mixes = $stmt_mixes->fetchAll(PDO::FETCH_COLUMN);
            } catch (PDOException $e) {
                echo '<div class="alert alert-danger">Gabim gjatë marrjes së të dhënave të produktit: ' . htmlspecialchars($e->getMessage()) . '</div>';
                require_once 'includes/footer.php';
                exit();
            }
        }

        // Merr opsionet e përzierjes për formën
        $mixes = getMixedProducts($pdo, $action === 'edit' ? $id : null);

        // Shfaq formularin për shtim ose editim
?>
        <h2 class="mb-4"><?= $action === 'add' ? 'Shto Produkt' : 'Edito Produkt' ?></h2>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <form method="POST" action="products.php?action=<?= $action ?><?= $action === 'edit' ? '&id=' . $id : '' ?>">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?= $id ?>">
            <?php endif; ?>
            <div class="row g-3">
                <!-- Kodi i Produktit -->
                <div class="col-md-6">
                    <label for="product_code" class="form-label">Kodi i Produktit <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text" id="product_code-addon" data-bs-toggle="tooltip" title="Futni një kod unik të produktit">
                            <i class="fas fa-id-badge"></i>
                        </span>
                        <input type="text" class="form-control" id="product_code" name="product_code" required value="<?= htmlspecialchars($action === 'edit' ? $product['product_code'] : ($_POST['product_code'] ?? '')) ?>" aria-describedby="product_code-addon">
                    </div>
                </div>
                <!-- Emri i Produktit -->
                <div class="col-md-6">
                    <label for="name" class="form-label">Emri i Produktit <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text" id="name-addon" data-bs-toggle="tooltip" title="Futni emrin e produktit">
                            <i class="fas fa-heading"></i>
                        </span>
                        <input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($action === 'edit' ? $product['name'] : ($_POST['name'] ?? '')) ?>" aria-describedby="name-addon">
                    </div>
                </div>
            </div>
            <div class="row g-3 mt-3">
                <!-- Kategoria -->
                <div class="col-md-6">
                    <label for="category_id" class="form-label">Kategoria <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text" id="category-addon" data-bs-toggle="tooltip" title="Zgjidhni kategorinë për produktin">
                            <i class="fas fa-list"></i>
                        </span>
                        <select class="form-select" id="category_id" name="category_id" required aria-describedby="category-addon">
                            <option value="">Zgjidhni një kategori</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>" <?= (
                                                                            ($action === 'edit' && $product['category_id'] == $category['id']) ||
                                                                            (isset($_POST['category_id']) && $_POST['category_id'] == $category['id'])
                                                                        ) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <!-- Çmimi -->
                <div class="col-md-6">
                    <label for="price" class="form-label">Çmimi ($) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text" id="price-addon" data-bs-toggle="tooltip" title="Futni çmimin e produktit">
                            <i class="fas fa-dollar-sign"></i>
                        </span>
                        <input type="number" step="0.01" class="form-control" id="price" name="price" required value="<?= htmlspecialchars($action === 'edit' ? $product['price'] : ($_POST['price'] ?? '')) ?>" aria-describedby="price-addon">
                    </div>
                </div>
            </div>
            <div class="row g-3 mt-3">
                <!-- URL e Imazhit -->
                <div class="col-md-6">
                    <label for="image_url" class="form-label">URL e Imazhit</label>
                    <div class="input-group">
                        <span class="input-group-text" id="image-addon" data-bs-toggle="tooltip" title="Futni URL-në e imazhit të produktit">
                            <i class="fas fa-image"></i>
                        </span>
                        <input type="url" class="form-control" id="image_url" name="image_url" placeholder="https://example.com/image.jpg" value="<?= htmlspecialchars($action === 'edit' ? $product['image_url'] : ($_POST['image_url'] ?? '')) ?>" aria-describedby="image-addon">
                    </div>
                </div>
                <!-- Alergjitë -->
                <div class="col-md-6">
                    <label for="allergies" class="form-label">Alergjitë</label>
                    <div class="input-group">
                        <span class="input-group-text" id="allergies-addon" data-bs-toggle="tooltip" title="Listoni alergjitë e produktit">
                            <i class="fas fa-exclamation-triangle"></i>
                        </span>
                        <input type="text" class="form-control" id="allergies" name="allergies" placeholder="e.g., Nuez, Qumësht" value="<?= htmlspecialchars($action === 'edit' ? $product['allergies'] : ($_POST['allergies'] ?? '')) ?>" aria-describedby="allergies-addon">
                    </div>
                    <div class="form-text">Ndani alergjitë me presje.</div>
                </div>
            </div>
            <div class="row g-3 mt-3">
                <!-- Përshkrimi -->
                <div class="col-md-12">
                    <label for="description" class="form-label">Përshkrimi</label>
                    <div class="input-group">
                        <span class="input-group-text" id="description-addon" data-bs-toggle="tooltip" title="Përshkruani produktin">
                            <i class="fas fa-align-left"></i>
                        </span>
                        <textarea class="form-control" id="description" name="description" aria-describedby="description-addon"><?= htmlspecialchars($action === 'edit' ? $product['description'] : ($_POST['description'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>
            <div class="row g-3 mt-3">
                <!-- Extras -->
                <div class="col-md-4">
                    <label for="extras" class="form-label">Extras</label>
                    <select class="form-select" id="extras" name="extras[]" multiple>
                        <?php foreach ($extras as $extra): ?>
                            <option value="<?= $extra['id'] ?>" <?= (
                                                                    (isset($_POST['extras']) && in_array($extra['id'], $_POST['extras'])) ||
                                                                    ($action === 'edit' && in_array($extra['id'], $selected_extras))
                                                                ) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($extra['name']) ?> - $<?= number_format($extra['price'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Zgjidhni extras për këtë produkt.</div>
                </div>
                <!-- Salcave -->
                <div class="col-md-4">
                    <label for="sauces" class="form-label">Salcave</label>
                    <select class="form-select" id="sauces" name="sauces[]" multiple>
                        <?php foreach ($sauces as $sauce): ?>
                            <option value="<?= $sauce['id'] ?>" <?= (
                                                                    (isset($_POST['sauces']) && in_array($sauce['id'], $_POST['sauces'])) ||
                                                                    ($action === 'edit' && in_array($sauce['id'], $selected_sauces))
                                                                ) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sauce['name']) ?> - $<?= number_format($sauce['price'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Zgjidhni salca për këtë produkt.</div>
                </div>
                <!-- Opsionet e Përzierjes -->
                <div class="col-md-4">
                    <label for="mixes" class="form-label">Opsionet e Përzierjes</label>
                    <select class="form-select" id="mixes" name="mixes[]" multiple>
                        <?php foreach ($mixes as $mix_product): ?>
                            <option value="<?= $mix_product['id'] ?>" <?= (
                                                                            (isset($_POST['mixes']) && in_array($mix_product['id'], $_POST['mixes'])) ||
                                                                            ($action === 'edit' && in_array($mix_product['id'], $selected_mixes))
                                                                        ) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mix_product['name']) ?> - $<?= number_format($mix_product['price'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Zgjidhni produkte që mund të përzihen me këtë produkt.</div>
                </div>
            </div>
            <div class="row g-3 mt-3">
                <!-- Opsione Shtesë -->
                <div class="col-md-4">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="is_new" name="is_new" <?= (
                                                                                                        ($action === 'edit' && $product['is_new']) ||
                                                                                                        (isset($_POST['is_new']) && $_POST['is_new'])
                                                                                                    ) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_new" data-bs-toggle="tooltip" title="Shënoni këtë produkt si të ri">
                            <i class="fas fa-star"></i> Produkt i Ri
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="is_offer" name="is_offer" <?= (
                                                                                                            ($action === 'edit' && $product['is_offer']) ||
                                                                                                            (isset($_POST['is_offer']) && $_POST['is_offer'])
                                                                                                        ) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_offer" data-bs-toggle="tooltip" title="Shënoni këtë produkt si ofertë">
                            <i class="fas fa-tags"></i> Oferta
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?= (
                                                                                                            ($action === 'edit' && $product['is_active']) ||
                                                                                                            (!isset($_POST['is_active']) && $action === 'add') ||
                                                                                                            (isset($_POST['is_active']) && $_POST['is_active'])
                                                                                                        ) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active" data-bs-toggle="tooltip" title="Shënoni këtë produkt si aktiv">
                            <i class="fas fa-toggle-on"></i> Aktiv
                        </label>
                    </div>
                </div>
            </div>
            <!-- Butonat -->
            <div class="mt-4">
                <button type="submit" class="btn btn-success me-2" data-bs-toggle="tooltip" title="<?= $action === 'add' ? 'Shto produkt të ri' : 'Rifresko produktin' ?>">
                    <i class="fas fa-save"></i> <?= $action === 'add' ? 'Shto' : 'Rifresko' ?> Produkt
                </button>
                <a href="products.php" class="btn btn-secondary" data-bs-toggle="tooltip" title="Anulo dhe kthehu te lista e produkteve">
                    <i class="fas fa-times"></i> Anulo
                </a>
            </div>
        </form>
        <?php if (isset($_SESSION['rating_success'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: '<?= htmlspecialchars($_SESSION['rating_success']) ?>',
                        timer: 3000,
                        showConfirmButton: false
                    });
                });
            </script>
            <?php unset($_SESSION['rating_success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['rating_error'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: '<?= htmlspecialchars($_SESSION['rating_error']) ?>',
                        timer: 5000,
                        showConfirmButton: true
                    });
                });
            </script>
            <?php unset($_SESSION['rating_error']); ?>
        <?php endif; ?>
        <!-- Skriptet -->
        <!-- jQuery -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <!-- Bootstrap Bundle -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Font Awesome -->
        <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
        <!-- Select2 -->
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <!-- DataTables (nëse përdoret) -->
        <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
        <script>
            $(document).ready(function() {
                // Initialize Select2
                $('#extras').select2({
                    placeholder: "Zgjidhni extras",
                    allowClear: true
                });
                $('#sauces').select2({
                    placeholder: "Zgjidhni salca",
                    allowClear: true
                });
                $('#mixes').select2({
                    placeholder: "Zgjidhni opsionet e përzierjes",
                    allowClear: true
                });
                // Initialize Tooltips
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
                var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl)
                })
            });
        </script>
    <?php
        break;
    case 'delete':
        if ($id > 0) {
            try {
                // Fshi produktin dhe lidhjet e tij
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM product_extras WHERE product_id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM product_sauces WHERE product_id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM product_mixes WHERE main_product_id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
                $pdo->commit();
                // Redirect pas fshirjes
                header('Location: products.php');
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = '<div class="alert alert-danger">Gabim gjatë fshirjes së produktit: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">ID e produktit është e pavlefshme.</div>';
        }
        // Shfaqimi i mesazhit dhe redirect
    ?>
        <h2 class="mb-4">Fshi Produkt</h2>
        <?= $message ?>
        <a href="products.php" class="btn btn-secondary">Kthehu te Produktet</a>
    <?php
        break;
    case 'top10':
        // Merr Top 10 Produktet më të Shitura
        $topProducts = getTop10Products($pdo);
    ?>
        <h2 class="mb-4">Top 10 Produktet më të Shitura</h2>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <?php if (!empty($topProducts)): ?>
            <!-- Grafiku me Chart.js -->
            <canvas id="topProductsChart" width="400" height="200" class="mb-4"></canvas>
            <!-- Tabela e Top 10 Produkteve -->
            <div class="table-responsive">
                <table id="top10Table" class="table table-striped table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Kodi i Produktit</th>
                            <th>Emri</th>
                            <th>Kategoria</th>
                            <th>Sasia e Shitur</th>
                            <th>Çmimi ($)</th>
                            <th>Imazhi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProducts as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['id']) ?></td>
                                <td><?= htmlspecialchars($product['product_code']) ?></td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td>
                                    <?php
                                    foreach ($categories as $category) {
                                        if ($category['id'] == $product['category_id']) {
                                            echo htmlspecialchars($category['name']);
                                            break;
                                        }
                                    }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($product['total_sold']) ?></td>
                                <td><?= number_format($product['price'], 2) ?></td>
                                <td>
                                    <?php if (!empty($product['image_url'])): ?>
                                        <a href="<?= htmlspecialchars($product['image_url']) ?>" target="_blank" data-bs-toggle="tooltip" title="Shiko Imazhin">
                                            <i class="fas fa-image text-primary"></i>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Skripti për Chart.js dhe DataTables -->
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
            <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
            <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
            <script>
                $(document).ready(function() {
                    // Initialize DataTables për Top 10 Tabelën
                    $('#top10Table').DataTable({
                        responsive: true,
                        order: [
                            [4, 'desc']
                        ], // Rendit sipas sasive të shitura
                        language: {
                            "emptyTable": "Nuk ka të dhëna",
                            "info": "Shfaqur _START_ deri _END_ nga _TOTAL_ produkte",
                            "infoEmpty": "Shfaqur 0 deri 0 nga 0 produkte",
                            "lengthMenu": "Shfaq _MENU_ produkte",
                            "paginate": {
                                "first": "Fillimi",
                                "last": "Fund",
                                "next": "Tjetra",
                                "previous": "Mbrapa"
                            },
                            "search": "Kërko:"
                        }
                    });
                    // Gjenero të dhënat për Grafikun
                    var labels = [
                        <?php foreach ($topProducts as $product): ?> '<?= addslashes(htmlspecialchars($product['name'])) ?>',
                        <?php endforeach; ?>
                    ];
                    var data = [
                        <?php foreach ($topProducts as $product): ?>
                            <?= htmlspecialchars($product['total_sold']) ?>,
                        <?php endforeach; ?>
                    ];
                    // Inicializo Chart.js
                    var ctx = document.getElementById('topProductsChart').getContext('2d');
                    var topProductsChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Sasia e Shitur',
                                data: data,
                                backgroundColor: 'rgba(75, 192, 192, 0.6)',
                                borderColor: 'rgba(75, 192, 192, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    precision: 0
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    enabled: true
                                }
                            }
                        }
                    });
                    // Initialize Tooltips
                    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
                    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl)
                    })
                });
            </script>
        <?php else: ?>
            <div class="alert alert-info">Nuk ka të dhëna për shitje.</div>
            <a href="products.php" class="btn btn-secondary">Kthehu te Produktet</a>
        <?php endif; ?>
        <a href="products.php" class="btn btn-secondary mt-3">Kthehu te Produktet</a>
    <?php
        break;
    case 'view':
    default:
        // Merr të gjitha produktet për shfaqje
        try {
            $query = '
                SELECT p.*, c.name AS category_name
                FROM products p
                JOIN categories c ON p.category_id = c.id
                ORDER BY p.created_at DESC
            ';
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $products = $stmt->fetchAll();
        } catch (PDOException $e) {
            $products = [];
            $message = '<div class="alert alert-danger">Gabim gjatë marrjes së produkteve: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    ?>
        <h2 class="mb-4">Menaxho Produktet</h2>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <!-- Butonat për Menaxhim -->
        <div class="mb-3">
            <a href="products.php?action=add" class="btn btn-success me-2" data-bs-toggle="tooltip" title="Shto një produkt të ri">
                <i class="fas fa-plus"></i> Shto Produkt
            </a>
            <a href="products.php?action=top10" class="btn btn-primary" data-bs-toggle="tooltip" title="Shiko 10 produktet më të shitura">
                <i class="fas fa-chart-line"></i> Top 10 Produktet
            </a>
        </div>
        <!-- Tabela e Produkteve -->
        <div class="table-responsive">
            <table id="productsTable" class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Kodi i Produktit</th>
                        <th>Emri</th>
                        <th>Kategoria</th>
                        <th>Extras</th>
                        <th>Salcave</th>
                        <th>Opsionet e Përzierjes</th>
                        <th>Alergjitë</th>
                        <th>Përshkrimi</th>
                        <th>Çmimi ($)</th>
                        <th>Imazhi</th>
                        <th>Produkt i Ri</th>
                        <th>Oferta</th>
                        <th>Statusi</th>
                        <th>Datë e Krijimit</th>
                        <th>Veprimet</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['id']) ?></td>
                                <td><?= htmlspecialchars($product['product_code']) ?></td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td><?= htmlspecialchars($product['category_name']) ?></td>
                                <td>
                                    <?php
                                    try {
                                        $stmt_extras = $pdo->prepare('SELECT e.name FROM extras e JOIN product_extras pe ON e.id = pe.extra_id WHERE pe.product_id = ?');
                                        $stmt_extras->execute([$product['id']]);
                                        $product_extras = $stmt_extras->fetchAll(PDO::FETCH_COLUMN);
                                        echo !empty($product_extras) ? htmlspecialchars(implode(', ', $product_extras)) : '-';
                                    } catch (PDOException $e) {
                                        echo '<span class="text-danger">Gabim</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    try {
                                        $stmt_sauces = $pdo->prepare('SELECT s.name FROM sauces s JOIN product_sauces ps ON s.id = ps.sauce_id WHERE ps.product_id = ?');
                                        $stmt_sauces->execute([$product['id']]);
                                        $product_sauces = $stmt_sauces->fetchAll(PDO::FETCH_COLUMN);
                                        echo !empty($product_sauces) ? htmlspecialchars(implode(', ', $product_sauces)) : '-';
                                    } catch (PDOException $e) {
                                        echo '<span class="text-danger">Gabim</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    try {
                                        $stmt_mixes = $pdo->prepare('SELECT p2.name FROM product_mixes pm JOIN products p2 ON pm.mixed_product_id = p2.id WHERE pm.main_product_id = ?');
                                        $stmt_mixes->execute([$product['id']]);
                                        $mixed_products = $stmt_mixes->fetchAll(PDO::FETCH_COLUMN);
                                        echo !empty($mixed_products) ? htmlspecialchars(implode(', ', $mixed_products)) : '-';
                                    } catch (PDOException $e) {
                                        echo '<span class="text-danger">Gabim</span>';
                                    }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($product['allergies']) ?: '-' ?></td>
                                <td><?= htmlspecialchars($product['description']) ?: '-' ?></td>
                                <td><?= number_format($product['price'], 2) ?></td>
                                <td>
                                    <?php if (!empty($product['image_url'])): ?>
                                        <a href="<?= htmlspecialchars($product['image_url']) ?>" target="_blank" data-bs-toggle="tooltip" title="Shiko Imazhin">
                                            <i class="fas fa-image text-primary"></i>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $product['is_new'] ? '<span class="badge bg-success" data-bs-toggle="tooltip" title="Produkt i Ri"><i class="fas fa-star"></i></span>' : '-' ?>
                                </td>
                                <td>
                                    <?= $product['is_offer'] ? '<span class="badge bg-warning text-dark" data-bs-toggle="tooltip" title="Oferta"><i class="fas fa-tags"></i></span>' : '-' ?>
                                </td>
                                <td>
                                    <?= $product['is_active']
                                        ? '<span class="badge bg-primary" data-bs-toggle="tooltip" title="Aktiv"><i class="fas fa-check-circle"></i></span>'
                                        : '<span class="badge bg-secondary" data-bs-toggle="tooltip" title="Jo Aktiv"><i class="fas fa-times-circle"></i></span>'
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($product['created_at']) ?></td>
                                <td>
                                    <a href="products.php?action=edit&id=<?= $product['id'] ?>" class="btn btn-sm btn-warning me-1" data-bs-toggle="tooltip" title="Edito Produktin">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-sm btn-danger" onclick="showDeleteModal(<?= $product['id'] ?>)" data-bs-toggle="tooltip" title="Fshi Produktin">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="16" class="text-center">Nuk ka produkte të gjetura.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Modal për Fshirjen -->
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form method="GET" action="products.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteProductId">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteModalLabel">Konfirmo Fshirjen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Mbyll" data-bs-toggle="tooltip" title="Mbyll"></button>
                        </div>
                        <div class="modal-body">
                            A jeni të sigurt që dëshironi të fshini këtë produkt? Kjo veprim nuk mund të rikthehet.
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-danger" data-bs-toggle="tooltip" title="Fshi">
                                <i class="fas fa-trash-alt"></i> Fshi
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-bs-toggle="tooltip" title="Anulo">
                                <i class="fas fa-times"></i> Anulo
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- Skriptet -->
        <!-- jQuery -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <!-- Bootstrap Bundle -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Font Awesome -->
        <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
        <!-- Select2 -->
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <!-- DataTables -->
        <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
        <script>
            $(document).ready(function() {
                // Initialize DataTables për tabelën e produkteve
                $('#productsTable').DataTable({
                    responsive: true,
                    order: [
                        [0, 'desc']
                    ], // Rendit sipas ID-së në mënyrë zbritëse
                    language: {
                        "emptyTable": "Nuk ka produkte të disponueshme",
                        "info": "Shfaqur _START_ deri _END_ nga _TOTAL_ produkte",
                        "infoEmpty": "Shfaqur 0 deri 0 nga 0 produkte",
                        "lengthMenu": "Shfaq _MENU_ produkte",
                        "paginate": {
                            "first": "Fillimi",
                            "last": "Fund",
                            "next": "Tjetra",
                            "previous": "Mbrapa"
                        },
                        "search": "Filtro rekordet:"
                    }
                });
                // Initialize Select2 për multi-select
                $('#extras').select2({
                    placeholder: "Zgjidhni extras",
                    allowClear: true
                });
                $('#sauces').select2({
                    placeholder: "Zgjidhni salca",
                    allowClear: true
                });
                $('#mixes').select2({
                    placeholder: "Zgjidhni opsionet e përzierjes",
                    allowClear: true
                });
                // Initialize Tooltips
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
                var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl)
                })
            });
            // Funksioni për të treguar modalin e fshirjes
            function showDeleteModal(id) {
                $('#deleteProductId').val(id);
                var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                deleteModal.show();
            }
        </script>
<?php
        break;
}
?>
<?php
require_once 'includes/footer.php';
?>