    <?php
    ob_start();
    require_once 'includes/db_connect.php';
    require_once 'includes/header.php';

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `coupons` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `code` VARCHAR(50) NOT NULL,
                `discount_type` ENUM('percentage','fixed') NOT NULL,
                `discount_value` DECIMAL(10,2) NOT NULL,
                `is_active` TINYINT(1) DEFAULT 1,
                `start_date` DATE NULL,
                `end_date` DATE NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Could not create table: "
            . htmlspecialchars($e->getMessage()) . "</div>";
    }

    $action = $_GET['action'] ?? 'list';
    $id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $message = '';

    function validateCoupon($pdo, $data, $id = 0)
    {
        $errors = [];
        $code = trim($data['code'] ?? '');
        $type = trim($data['discount_type'] ?? '');
        $val  = (float)($data['discount_value'] ?? 0);
        if (!$code || !$type || !$val) {
            $errors[] = 'Code, type, and value are required.';
        }
        $sql = "SELECT COUNT(*) FROM coupons WHERE code=?";
        $p = [$code];
        if ($id > 0) {
            $sql .= " AND id!=?";
            $p[] = $id;
        }
        $s = $pdo->prepare($sql);
        $s->execute($p);
        if ($s->fetchColumn() > 0) {
            $errors[] = 'Coupon code already exists.';
        }
        return [$errors, compact('code', 'type', 'val')];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'create') {
            list($errors, $data) = validateCoupon($pdo, $_POST);
            $act = isset($_POST['is_active']) ? 1 : 0;
            $start = $_POST['start_date'] ?? null;
            $end  = $_POST['end_date'] ?? null;
            if ($errors) {
                $message = implode('<br>', $errors);
            } else {
                $sql = "INSERT INTO coupons (code,discount_type,discount_value,is_active,start_date,end_date,created_at)
                    VALUES(?,?,?,?,?,?,NOW())";
                $stm = $pdo->prepare($sql);
                try {
                    $stm->execute([
                        $data['code'],
                        $data['type'],
                        $data['val'],
                        $act,
                        $start ?: null,
                        $end ?: null
                    ]);
                    header("Location: cupons.php?action=list&message=" . urlencode("Coupon created successfully."));
                    exit;
                } catch (PDOException $e) {
                    $message = "Error creating coupon.";
                }
            }
        } elseif ($action === 'edit' && $id > 0) {
            list($errors, $data) = validateCoupon($pdo, $_POST, $id);
            $act = isset($_POST['is_active']) ? 1 : 0;
            $start = $_POST['start_date'] ?? null;
            $end  = $_POST['end_date'] ?? null;
            if ($errors) {
                $message = implode('<br>', $errors);
            } else {
                $sql = "UPDATE coupons SET code=?, discount_type=?, discount_value=?, 
                    is_active=?, start_date=?, end_date=?, updated_at=NOW() 
                    WHERE id=?";
                $stm = $pdo->prepare($sql);
                try {
                    $stm->execute([
                        $data['code'],
                        $data['type'],
                        $data['val'],
                        $act,
                        $start ?: null,
                        $end ?: null,
                        $id
                    ]);
                    header("Location: cupons.php?action=list&message=" . urlencode("Coupon updated."));
                    exit;
                } catch (PDOException $e) {
                    $message = "Error updating coupon.";
                }
            }
        } elseif ($action === 'delete' && $id > 0) {
            $stm = $pdo->prepare("DELETE FROM coupons WHERE id=?");
            try {
                $stm->execute([$id]);
                header("Location: cupons.php?action=list&message=" . urlencode("Coupon deleted."));
                exit;
            } catch (PDOException $e) {
                $message = "Error deleting coupon.";
            }
        }
    }

    if ($action === 'list') {
        $msg = $_GET['message'] ?? '';
        try {
            $stm = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC");
            $coupons = $stm->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $coupons = [];
            $msg = "Error fetching coupons.";
        }
    ?>
        <div class="container mt-4">
            <h2>Coupons</h2>
            <button class="btn btn-sm btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#createModal">New Coupon</button>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Type</th>
                            <th>Value</th>
                            <th>Active</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$coupons): ?>
                            <tr>
                                <td colspan="9" class="text-center">No coupons found.</td>
                            </tr>
                            <?php else: foreach ($coupons as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['id']) ?></td>
                                    <td><?= htmlspecialchars($c['code']) ?></td>
                                    <td><?= htmlspecialchars($c['discount_type']) ?></td>
                                    <td><?= htmlspecialchars($c['discount_value']) ?></td>
                                    <td><span class="badge <?= ($c['is_active'] ? 'bg-success' : 'bg-secondary') ?>"><?= ($c['is_active'] ? 'Yes' : 'No') ?></span></td>
                                    <td><?= htmlspecialchars($c['start_date'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($c['end_date'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($c['created_at']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning btnEdit"
                                            data-id="<?= $c['id'] ?>"
                                            data-code="<?= htmlspecialchars($c['code']) ?>"
                                            data-type="<?= htmlspecialchars($c['discount_type']) ?>"
                                            data-value="<?= htmlspecialchars($c['discount_value']) ?>"
                                            data-active="<?= $c['is_active'] ?>"
                                            data-start="<?= $c['start_date'] ?? '' ?>"
                                            data-end="<?= $c['end_date'] ?? '' ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editModal">
                                            Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger btnDelete"
                                            data-id="<?= $c['id'] ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteModal">
                                            Del
                                        </button>
                                    </td>
                                </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- CREATE Modal -->
        <div class="modal fade" id="createModal" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" action="cupons.php?action=create" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">New Coupon</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="create_code" class="form-label">Code</label>
                            <input type="text" name="code" id="create_code" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-3">
                            <label for="create_discount_type" class="form-label">Type</label>
                            <select name="discount_type" id="create_discount_type" class="form-select form-select-sm">
                                <option value="percentage">Percentage</option>
                                <option value="fixed">Fixed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="create_discount_value" class="form-label">Value</label>
                            <input type="number" step="0.01" name="discount_value" id="create_discount_value" class="form-control form-control-sm" required>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="is_active" id="create_is_active" class="form-check-input" value="1" checked>
                            <label class="form-check-label" for="create_is_active">Active</label>
                        </div>
                        <div class="mb-3">
                            <label for="create_start_date" class="form-label">Start Date</label>
                            <input type="date" name="start_date" id="create_start_date" class="form-control form-control-sm">
                        </div>
                        <div class="mb-3">
                            <label for="create_end_date" class="form-label">End Date</label>
                            <input type="date" name="end_date" id="create_end_date" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary btn-sm">Create</button>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- EDIT Modal -->
        <div class="modal fade" id="editModal" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" id="editForm" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Coupon</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="edit_id" name="id">
                        <div class="mb-3">
                            <label class="form-label">Code</label>
                            <input type="text" id="edit_code" name="code" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select id="edit_type" name="discount_type" class="form-select form-select-sm" required>
                                <option value="percentage">Percentage</option>
                                <option value="fixed">Fixed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Value</label>
                            <input type="number" step="0.01" id="edit_value" name="discount_value" class="form-control form-control-sm" required>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" id="edit_active" name="is_active" class="form-check-input" value="1">
                            <label for="edit_active" class="form-check-label">Active</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" id="edit_start" name="start_date" class="form-control form-control-sm">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" id="edit_end" name="end_date" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- DELETE Modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" id="deleteForm" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Coupon</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this coupon?</p>
                        <input type="hidden" id="delete_id" name="id">
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-danger btn-sm">Yes, Delete</button>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Toast for messages -->
        <div class="position-fixed top-0 end-0 p-3" style="z-index:9999">
            <div id="toastMessage" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-success text-white">
                    <strong class="me-auto">Info</strong>
                    <button type="button" class="btn-close btn-close-white ms-2 mb-1" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body" id="toastBody"></div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Show toast if there's a message in the URL or server
                var msg = "<?= addslashes($msg ?? ($message ?? '')) ?>";
                if (msg) {
                    var toastBody = document.getElementById('toastBody');
                    toastBody.innerHTML = msg;
                    var toastEl = document.getElementById('toastMessage');
                    var toast = new bootstrap.Toast(toastEl);
                    toast.show();
                }

                // Edit
                var editBtns = document.querySelectorAll('.btnEdit');
                editBtns.forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var id = this.getAttribute('data-id');
                        var code = this.getAttribute('data-code');
                        var type = this.getAttribute('data-type');
                        var val = this.getAttribute('data-value');
                        var act = this.getAttribute('data-active');
                        var start = this.getAttribute('data-start');
                        var end = this.getAttribute('data-end');
                        document.getElementById('edit_id').value = id;
                        document.getElementById('edit_code').value = code;
                        document.getElementById('edit_type').value = type;
                        document.getElementById('edit_value').value = val;
                        document.getElementById('edit_active').checked = (act == '1');
                        document.getElementById('edit_start').value = start;
                        document.getElementById('edit_end').value = end;
                        document.getElementById('editForm').setAttribute('action', 'cupons.php?action=edit&id=' + id);
                    });
                });

                // Delete
                var delBtns = document.querySelectorAll('.btnDelete');
                delBtns.forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var id = this.getAttribute('data-id');
                        document.getElementById('delete_id').value = id;
                        document.getElementById('deleteForm').setAttribute('action', 'cupons.php?action=delete&id=' + id);
                    });
                });
            });
        </script>
    <?php
    } elseif ($action === 'create') {
        // fallback if someone directly goes to action=create
        header('Location: cupons.php?action=list');
        exit;
    } elseif ($action === 'edit' && $id > 0) {
        // fallback if direct
        header('Location: cupons.php?action=list');
        exit;
    } elseif ($action === 'delete' && $id > 0) {
        // fallback if direct
        header('Location: cupons.php?action=list');
        exit;
    }
    require_once 'includes/footer.php';
    ob_end_flush();
