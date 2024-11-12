<?php
ob_start(); // Fillon buferimin e output-it
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Initialize variables
$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

// Funksione për të marrë të dhënat e nevojshme
function getMainProducts($pdo)
{
    try {
        return $pdo->query('SELECT id, name FROM products ORDER BY name ASC')->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getMixedProducts($pdo, $exclude_product_id = null)
{
    try {
        $query = 'SELECT id, name, price FROM products ORDER BY name ASC';
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

function getProductMixes($pdo, $product_id)
{
    try {
        $stmt = $pdo->prepare('SELECT mixed_product_id FROM product_mixes WHERE main_product_id = ?');
        $stmt->execute([$product_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

function getAllProductMixes($pdo)
{
    try {
        $query = '
            SELECT pm.id, p_main.name AS main_product, p_mix.name AS mixed_product
            FROM product_mixes pm
            JOIN products p_main ON pm.main_product_id = p_main.id
            JOIN products p_mix ON pm.mixed_product_id = p_mix.id
            ORDER BY p_main.name ASC, p_mix.name ASC
        ';
        return $pdo->query($query)->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Menaxhojmë veprimet
switch ($action) {
    case 'add':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Përgatitja e të dhënave
            $main_product_id = (int)$_POST['main_product_id'];
            $selected_mixes = $_POST['mixes'] ?? [];

            // Validimet
            if ($main_product_id === 0) {
                $message = '<div class="alert alert-danger">Zgjidhni një produkt kryesor.</div>';
            } else {
                // Kontrollo nëse marrëdhënia ekziston tashmë
                try {
                    $stmt_check = $pdo->prepare('SELECT COUNT(*) FROM product_mixes WHERE main_product_id = ? AND mixed_product_id = ?');
                    $duplicate = false;
                    foreach ($selected_mixes as $mixed_product_id) {
                        $stmt_check->execute([$main_product_id, $mixed_product_id]);
                        if ($stmt_check->fetchColumn() > 0) {
                            $duplicate = true;
                            break;
                        }
                    }
                    if ($duplicate) {
                        $message = '<div class="alert alert-danger">Disa lidhje tashmë ekzistojnë. Ju lutem, zgjidhni lidhje të tjera.</div>';
                    } else {
                        // Shtimi i lidhjeve
                        $stmt_insert = $pdo->prepare('INSERT INTO product_mixes (main_product_id, mixed_product_id) VALUES (?, ?)');
                        foreach ($selected_mixes as $mixed_product_id) {
                            $stmt_insert->execute([$main_product_id, $mixed_product_id]);
                        }
                        $_SESSION['success'] = 'Opsionet e përzierjes u shtuan me sukses.';
                        header('Location: products_mixes.php');
                        exit();
                    }
                } catch (PDOException $e) {
                    $message = '<div class="alert alert-danger">Gabim gjatë përpunimit të të dhënave: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }

        // Merr produktet kryesore dhe të përzierjes për formularin
        $main_products = getMainProducts($pdo);
        $mixed_products = getMixedProducts($pdo);

        // Shfaq formularin për shtimin e lidhjes së re
?>
        <h2 class="mb-4">Shto Opsion për Përzierje</h2>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <form method="POST" action="products_mixes.php?action=add">
            <div class="row g-3">
                <!-- Produkti Kryesor -->
                <div class="col-md-6">
                    <label for="main_product_id" class="form-label">Produkti Kryesor <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text" id="main_product-addon" data-bs-toggle="tooltip" title="Zgjidhni produktin kryesor">
                            <i class="fas fa-box-open"></i>
                        </span>
                        <select class="form-select" id="main_product_id" name="main_product_id" required aria-describedby="main_product-addon">
                            <option value="">Zgjidhni një produkt kryesor</option>
                            <?php foreach ($main_products as $product): ?>
                                <option value="<?= $product['id'] ?>" <?= (isset($_POST['main_product_id']) && $_POST['main_product_id'] == $product['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($product['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <!-- Opsionet e Përzierjes -->
                <div class="col-md-6">
                    <label for="mixes" class="form-label">Opsionet e Përzierjes</label>
                    <select class="form-select" id="mixes" name="mixes[]" multiple>
                        <?php foreach ($mixed_products as $mix_product): ?>
                            <option value="<?= $mix_product['id'] ?>" <?= (isset($_POST['mixes']) && in_array($mix_product['id'], $_POST['mixes'])) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mix_product['name']) ?> - $<?= number_format($mix_product['price'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Zgjidhni produkte që mund të përzihen me produktin kryesor.</div>
                </div>
            </div>
            <!-- Butonat -->
            <div class="mt-4">
                <button type="submit" class="btn btn-success me-2" data-bs-toggle="tooltip" title="Shto opsion për përzierje">
                    <i class="fas fa-save"></i> Shto Opsion
                </button>
                <a href="products_mixes.php" class="btn btn-secondary" data-bs-toggle="tooltip" title="Anulo dhe kthehu te lista e opsioneve">
                    <i class="fas fa-times"></i> Anulo
                </a>
            </div>
        </form>
    <?php
        break;

    case 'edit':
        if ($id <= 0) {
            $message = '<div class="alert alert-danger">ID e opsionit të përzierjes është e pavlefshme.</div>';
            break;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Përgatitja e të dhënave
            $main_product_id = (int)$_POST['main_product_id'];
            $selected_mixes = $_POST['mixes'] ?? [];

            // Validimet
            if ($main_product_id === 0) {
                $message = '<div class="alert alert-danger">Zgjidhni një produkt kryesor.</div>';
            } else {
                try {
                    // Përditësimi i lidhjeve
                    // Fshi lidhjet ekzistuese
                    $pdo->prepare('DELETE FROM product_mixes WHERE main_product_id = ?')->execute([$main_product_id]);

                    // Shtimi i lidhjeve të reja
                    if (!empty($selected_mixes)) {
                        $stmt_insert = $pdo->prepare('INSERT INTO product_mixes (main_product_id, mixed_product_id) VALUES (?, ?)');
                        foreach ($selected_mixes as $mixed_product_id) {
                            $stmt_insert->execute([$main_product_id, $mixed_product_id]);
                        }
                    }

                    $_SESSION['success'] = 'Opsionet e përzierjes u përditësuan me sukses.';
                    header('Location: products_mixes.php');
                    exit();
                } catch (PDOException $e) {
                    $message = '<div class="alert alert-danger">Gabim gjatë përpunimit të të dhënave: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }

        // Merr të dhënat ekzistuese për opsionin e përzierjes
        try {
            $stmt = $pdo->prepare('SELECT main_product_id FROM product_mixes WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $existing_mix = $stmt->fetch();

            if (!$existing_mix) {
                $message = '<div class="alert alert-danger">Opsioni i përzierjes nuk u gjet.</div>';
                break;
            }

            $main_product_id = $existing_mix['main_product_id'];
            $selected_mixes = getProductMixes($pdo, $main_product_id);
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Gabim gjatë marrjes së të dhënave: ' . htmlspecialchars($e->getMessage()) . '</div>';
            break;
        }

        // Merr produktet kryesore dhe të përzierjes për formularin
        $main_products = getMainProducts($pdo);
        $mixed_products = getMixedProducts($pdo, $main_product_id);

        // Shfaq formularin për editimin e lidhjes
    ?>
        <h2 class="mb-4">Edito Opsion për Përzierje</h2>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <form method="POST" action="products_mixes.php?action=edit&id=<?= $id ?>">
            <div class="row g-3">
                <!-- Produkti Kryesor -->
                <div class="col-md-6">
                    <label for="main_product_id" class="form-label">Produkti Kryesor <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text" id="main_product-addon" data-bs-toggle="tooltip" title="Zgjidhni produktin kryesor">
                            <i class="fas fa-box-open"></i>
                        </span>
                        <select class="form-select" id="main_product_id" name="main_product_id" required aria-describedby="main_product-addon">
                            <option value="">Zgjidhni një produkt kryesor</option>
                            <?php foreach ($main_products as $product): ?>
                                <option value="<?= $product['id'] ?>" <?= ($product['id'] == $main_product_id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($product['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <!-- Opsionet e Përzierjes -->
                <div class="col-md-6">
                    <label for="mixes" class="form-label">Opsionet e Përzierjes</label>
                    <select class="form-select" id="mixes" name="mixes[]" multiple>
                        <?php foreach ($mixed_products as $mix_product): ?>
                            <option value="<?= $mix_product['id'] ?>" <?= (in_array($mix_product['id'], $selected_mixes)) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mix_product['name']) ?> - $<?= number_format($mix_product['price'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Zgjidhni produkte që mund të përzihen me produktin kryesor.</div>
                </div>
            </div>
            <!-- Butonat -->
            <div class="mt-4">
                <button type="submit" class="btn btn-primary me-2" data-bs-toggle="tooltip" title="Përditëso opsionet e përzierjes">
                    <i class="fas fa-save"></i> Përditëso
                </button>
                <a href="products_mixes.php" class="btn btn-secondary" data-bs-toggle="tooltip" title="Anulo dhe kthehu te lista e opsioneve">
                    <i class="fas fa-times"></i> Anulo
                </a>
            </div>
        </form>
    <?php
        break;

    case 'delete':
        if ($id <= 0) {
            $message = '<div class="alert alert-danger">ID e opsionit të përzierjes është e pavlefshme.</div>';
            break;
        }

        try {
            // Merrni të dhënat për opsionin e përzierjes përpara se ta fshini
            $stmt = $pdo->prepare('SELECT main_product_id, mixed_product_id FROM product_mixes WHERE id = ?');
            $stmt->execute([$id]);
            $mix = $stmt->fetch();

            if (!$mix) {
                $message = '<div class="alert alert-danger">Opsioni i përzierjes nuk u gjet.</div>';
                break;
            }

            // Fshi opsionin e përzierjes
            $pdo->prepare('DELETE FROM product_mixes WHERE id = ?')->execute([$id]);
            $_SESSION['success'] = 'Opsioni i përzierjes u fshi me sukses.';
            header('Location: products_mixes.php');
            exit();
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Gabim gjatë fshirjes së opsionit të përzierjes: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        // Shfaq mesazhin e gabimit dhe lidhjen për t'u kthyer
    ?>
        <h2 class="mb-4">Fshi Opsion për Përzierje</h2>
        <?= $message ?>
        <a href="products_mixes.php" class="btn btn-secondary">Kthehu te Opsionet e Përzierjes</a>
    <?php
        break;

    case 'view':
    default:
        // Merr të gjitha opsionet e përzierjes për shfaqje
        $all_mixes = getAllProductMixes($pdo);
    ?>
        <h2 class="mb-4">Menaxho Opsionet e Përzierjes së Produkteve</h2>
        <?php
        // Shfaq mesazhet e suksesit ose gabimit
        if (isset($_SESSION['success'])):
        ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php
        endif;
        if ($message):
            echo $message;
        endif;
        ?>
        <!-- Butonat për Menaxhim -->
        <div class="mb-3">
            <a href="products_mixes.php?action=add" class="btn btn-success me-2" data-bs-toggle="tooltip" title="Shto një opsion të ri për përzierje">
                <i class="fas fa-plus"></i> Shto Opsion
            </a>
        </div>
        <!-- Tabela e Opsioneve të Përzierjes -->
        <div class="table-responsive">
            <table id="mixesTable" class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Produkti Kryesor</th>
                        <th>Produkti i Përzier</th>
                        <th>Veprimet</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($all_mixes)): ?>
                        <?php foreach ($all_mixes as $mix): ?>
                            <tr>
                                <td><?= htmlspecialchars($mix['id']) ?></td>
                                <td><?= htmlspecialchars($mix['main_product']) ?></td>
                                <td><?= htmlspecialchars($mix['mixed_product']) ?></td>
                                <td>
                                    <a href="products_mixes.php?action=edit&id=<?= $mix['id'] ?>" class="btn btn-sm btn-warning me-1" data-bs-toggle="tooltip" title="Edito Opsionin">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-sm btn-danger" onclick="showDeleteModal(<?= $mix['id'] ?>)" data-bs-toggle="tooltip" title="Fshi Opsionin">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">Nuk ka opsione të përzierjes të gjetura.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Modal për Fshirjen -->
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form method="GET" action="products_mixes.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteMixId">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteModalLabel">Konfirmo Fshirjen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Mbyll" data-bs-toggle="tooltip" title="Mbyll"></button>
                        </div>
                        <div class="modal-body">
                            A jeni të sigurt që dëshironi të fshini këtë opsion të përzierjes? Kjo veprim nuk mund të rikthehet.
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
                // Initialize DataTables për tabelën e opsioneve të përzierjes
                $('#mixesTable').DataTable({
                    responsive: true,
                    order: [
                        [0, 'desc']
                    ], // Rendit sipas ID-së në mënyrë zbritëse
                    language: {
                        "emptyTable": "Nuk ka opsione të përzierjes të disponueshme",
                        "info": "Shfaqur _START_ deri _END_ nga _TOTAL_ opsione",
                        "infoEmpty": "Shfaqur 0 deri 0 nga 0 opsione",
                        "lengthMenu": "Shfaq _MENU_ opsione",
                        "paginate": {
                            "first": "Fillimi",
                            "last": "Fund",
                            "next": "Tjetra",
                            "previous": "Mbrapa"
                        },
                        "search": "Filtro:"
                    }
                });

                // Initialize Select2 për fushat multi-select
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
                $('#deleteMixId').val(id);
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