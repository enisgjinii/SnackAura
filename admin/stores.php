<?php
ob_start();
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$daysOfWeek = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

function validateStore($data, PDO $pdo, int $id = 0): array
{
    $errors = [];
    $sanitized = [];
    $required = ['name', 'address', 'phone', 'email'];
    foreach ($required as $f) {
        $v = trim($data[$f] ?? '');
        if ($v === '') $errors[] = "Field '$f' is required.";
        else $sanitized[$f] = $v;
    }
    if (!empty($sanitized['email']) && !filter_var($sanitized['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    $sanitized['manager_id'] = (isset($data['manager_id']) && ctype_digit($data['manager_id'])) ? (int)$data['manager_id'] : null;
    $socials = ['facebook_link', 'twitter_link', 'instagram_link', 'linkedin_link', 'youtube_link'];
    foreach ($socials as $s) {
        $link = trim($data[$s] ?? '');
        if ($link) {
            if (!filter_var($link, FILTER_VALIDATE_URL)) $errors[] = "Invalid URL for '$s'.";
            else $sanitized[$s] = $link;
        } else $sanitized[$s] = null;
    }
    $minOrder = trim($data['minimum_order'] ?? '');
    if ($minOrder === '' || !is_numeric($minOrder) || floatval($minOrder) < 0) $errors[] = "Minimum order must be non-negative.";
    else $sanitized['minimum_order'] = $minOrder;
    $lat = trim($data['store_lat'] ?? '');
    $lng = trim($data['store_lng'] ?? '');
    if (!is_numeric($lat) || !is_numeric($lng)) $errors[] = "Invalid latitude or longitude.";
    else {
        $sanitized['store_lat'] = $lat;
        $sanitized['store_lng'] = $lng;
    }
    $sanitized['agb'] = trim($data['agb'] ?? '');
    $sanitized['impressum'] = trim($data['impressum'] ?? '');
    $sanitized['datenschutzerklaerung'] = trim($data['datenschutzerklaerung'] ?? '');
    $sanitized['cart_description'] = trim($data['cart_description'] ?? '');
    $dz = trim($data['delivery_zones'] ?? '');
    if ($dz) {
        json_decode($dz);
        if (json_last_error() !== JSON_ERROR_NONE) $errors[] = "Invalid JSON in 'delivery_zones'.";
        else $sanitized['delivery_zones'] = $dz;
    } else $sanitized['delivery_zones'] = null;
    $sql = "SELECT COUNT(*) FROM stores WHERE (name=:n OR email=:e)";
    if ($id > 0) $sql .= " AND id!=:id";
    $st = $pdo->prepare($sql);
    $args = [':n' => $sanitized['name'] ?? '', ':e' => $sanitized['email'] ?? ''];
    if ($id > 0) $args[':id'] = $id;
    try {
        $st->execute($args);
        if ($st->fetchColumn() > 0) $errors[] = "A store with this name or email already exists.";
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        $errors[] = "Validation DB error.";
    }
    return [$errors, $sanitized];
}

function handleFileUpload(array &$errors, string $fileKey = 'logo'): ?string
{
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Upload error for '$fileKey' code: " . $_FILES[$fileKey]['error'];
        return null;
    }
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($fi, $_FILES[$fileKey]['tmp_name']);
    finfo_close($fi);
    if (!in_array($mime, $allowed)) {
        $errors[] = "Invalid file type ($mime) for '$fileKey'.";
        return null;
    }
    $dir = __DIR__ . '/uploads/logos/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        $errors[] = "Cannot create directory '$dir'. Check permissions.";
        return null;
    }
    $orig = basename($_FILES[$fileKey]['name']);
    $ext = pathinfo($orig, PATHINFO_EXTENSION);
    $fn = $fileKey . '_' . time() . '.' . $ext;
    $dest = $dir . $fn;
    if (!move_uploaded_file($_FILES[$fileKey]['tmp_name'], $dest)) {
        $errors[] = "Cannot move uploaded file for '$fileKey'.";
        return null;
    }
    return 'uploads/logos/' . $fn;
}

function buildWorkScheduleJSON(array $data, array $daysOfWeek): string
{
    $d = ['days' => [], 'holidays' => []];
    foreach ($daysOfWeek as $day) {
        $sf = strtolower($day) . '_start';
        $ef = strtolower($day) . '_end';
        $sv = trim($data[$sf] ?? '');
        $ev = trim($data[$ef] ?? '');
        if ($sv && !preg_match('/^(?:2[0-3]|[01]\d):[0-5]\d$/', $sv)) $sv = '';
        if ($ev && !preg_match('/^(?:2[0-3]|[01]\d):[0-5]\d$/', $ev)) $ev = '';
        $d['days'][$day] = ['start' => $sv, 'end' => $ev];
    }
    $hol = trim($data['holidays'] ?? '');
    if ($hol) {
        foreach (explode("\n", $hol) as $line) {
            $line = trim($line);
            if (!$line) continue;
            $p = explode(',', $line, 2);
            $dt = trim($p[0] ?? '');
            $desc = trim($p[1] ?? 'Holiday');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt)) $d['holidays'][] = ['date' => $dt, 'desc' => $desc];
        }
    }
    return json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        list($errors, $clean) = validateStore($_POST, $pdo);
        $logo = handleFileUpload($errors, 'logo');
        $cart = handleFileUpload($errors, 'cart_logo');
        $ws = buildWorkScheduleJSON($_POST, $daysOfWeek);
        if ($errors) $message = json_encode($errors);
        else {
            $sql = "INSERT INTO stores(name,address,phone,email,manager_id,is_active,logo,cart_logo,work_schedule,minimum_order,agb,impressum,datenschutzerklaerung,facebook_link,twitter_link,instagram_link,linkedin_link,youtube_link,cart_description,store_lat,store_lng,delivery_zones,created_at)
                  VALUES(:nm,:ad,:ph,:em,:mg,1,:lg,:cl,:ws,:mn,:agb,:imp,:dse,:fb,:tw,:ig,:li,:yt,:cd,:lat,:lng,:dz,NOW())";
            $st = $pdo->prepare($sql);
            $pr = [
                ':nm' => $clean['name'],
                ':ad' => $clean['address'],
                ':ph' => $clean['phone'],
                ':em' => $clean['email'],
                ':mg' => $clean['manager_id'],
                ':lg' => $logo,
                ':cl' => $cart,
                ':ws' => $ws,
                ':mn' => $clean['minimum_order'],
                ':agb' => $clean['agb'],
                ':imp' => $clean['impressum'],
                ':dse' => $clean['datenschutzerklaerung'],
                ':fb' => $clean['facebook_link'],
                ':tw' => $clean['twitter_link'],
                ':ig' => $clean['instagram_link'],
                ':li' => $clean['linkedin_link'],
                ':yt' => $clean['youtube_link'],
                ':cd' => $clean['cart_description'],
                ':lat' => $clean['store_lat'],
                ':lng' => $clean['store_lng'],
                ':dz' => $clean['delivery_zones']
            ];
            try {
                $st->execute($pr);
                header('Location: stores.php?action=list&message=' . urlencode("Store created successfully."));
                exit;
            } catch (PDOException $ex) {
                error_log($ex->getMessage());
                $message = json_encode(["Unable to create store: " . $ex->getMessage()]);
            }
        }
    } elseif ($action === 'edit' && $id > 0) {
        $chk = $pdo->prepare("SELECT * FROM stores WHERE id=:i");
        $chk->execute([':i' => $id]);
        $store = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$store) {
            header('Location: stores.php?action=list&message=' . urlencode("Store not found."));
            exit;
        }
        list($errors, $clean) = validateStore($_POST, $pdo, $id);
        $lg = handleFileUpload($errors, 'logo');
        $cg = handleFileUpload($errors, 'cart_logo');
        $ws = buildWorkScheduleJSON($_POST, $daysOfWeek);
        $isA = isset($_POST['is_active']) ? 1 : 0;
        if ($errors) $message = json_encode($errors);
        else {
            $fields = [
                'name' => ':n',
                'address' => ':ad',
                'phone' => ':ph',
                'email' => ':em',
                'manager_id' => ':mg',
                'is_active' => ':ia',
                'work_schedule' => ':ws',
                'minimum_order' => ':mn',
                'agb' => ':agb',
                'impressum' => ':imp',
                'datenschutzerklaerung' => ':dse',
                'facebook_link' => ':fb',
                'twitter_link' => ':tw',
                'instagram_link' => ':ig',
                'linkedin_link' => ':li',
                'youtube_link' => ':yt',
                'cart_description' => ':cd',
                'store_lat' => ':lat',
                'store_lng' => ':lng',
                'delivery_zones' => ':dz'
            ];
            $pa = [
                ':n' => $clean['name'],
                ':ad' => $clean['address'],
                ':ph' => $clean['phone'],
                ':em' => $clean['email'],
                ':mg' => $clean['manager_id'],
                ':ia' => $isA,
                ':ws' => $ws,
                ':mn' => $clean['minimum_order'],
                ':agb' => $clean['agb'],
                ':imp' => $clean['impressum'],
                ':dse' => $clean['datenschutzerklaerung'],
                ':fb' => $clean['facebook_link'],
                ':tw' => $clean['twitter_link'],
                ':ig' => $clean['instagram_link'],
                ':li' => $clean['linkedin_link'],
                ':yt' => $clean['youtube_link'],
                ':cd' => $clean['cart_description'],
                ':lat' => $clean['store_lat'],
                ':lng' => $clean['store_lng'],
                ':dz' => $clean['delivery_zones']
            ];
            if ($lg) {
                $fields['logo'] = ':lg';
                $pa[':lg'] = $lg;
            }
            if ($cg) {
                $fields['cart_logo'] = ':cg';
                $pa[':cg'] = $cg;
            }
            $setClauses = [];
            foreach ($fields as $col => $b) {
                if ($b !== null) $setClauses[] = "$col=$b";
            }
            $sql = "UPDATE stores SET " . implode(', ', $setClauses) . ", updated_at=NOW() WHERE id=:id";
            $pa[':id'] = $id;
            try {
                $ed = $pdo->prepare($sql);
                $ed->execute($pa);
                if ($lg && !empty($store['logo']) && file_exists(__DIR__ . '/' . $store['logo'])) unlink(__DIR__ . '/' . $store['logo']);
                if ($cg && !empty($store['cart_logo']) && file_exists(__DIR__ . '/' . $store['cart_logo'])) unlink(__DIR__ . '/' . $store['cart_logo']);
                header('Location: stores.php?action=list&message=' . urlencode("Store updated successfully."));
                exit;
            } catch (PDOException $ex) {
                error_log($ex->getMessage());
                $message = json_encode(["Unable to update store: " . $ex->getMessage()]);
            }
        }
    } elseif ($action === 'delete' && $id > 0) {
        $g = $pdo->prepare("SELECT logo,cart_logo FROM stores WHERE id=:i");
        $g->execute([':i' => $id]);
        $store = $g->fetch(PDO::FETCH_ASSOC);
        if ($store) {
            try {
                $pdo->prepare("DELETE FROM stores WHERE id=:i")->execute([':i' => $id]);
                if (!empty($store['logo']) && file_exists(__DIR__ . '/' . $store['logo'])) unlink(__DIR__ . '/' . $store['logo']);
                if (!empty($store['cart_logo']) && file_exists(__DIR__ . '/' . $store['cart_logo'])) unlink(__DIR__ . '/' . $store['cart_logo']);
                header('Location: stores.php?action=list&message=' . urlencode("Store deleted successfully."));
                exit;
            } catch (PDOException $ex) {
                error_log($ex->getMessage());
                header('Location: stores.php?action=list&message=' . urlencode("Unable to delete store."));
                exit;
            }
        } else {
            header('Location: stores.php?action=list&message=' . urlencode("Store not found."));
            exit;
        }
    } elseif ($action === 'toggle_status' && $id > 0) {
        $s = $pdo->prepare("SELECT is_active FROM stores WHERE id=:i");
        $s->execute([':i' => $id]);
        $store = $s->fetch(PDO::FETCH_ASSOC);
        if ($store) {
            $nSt = $store['is_active'] ? 0 : 1;
            $u = $pdo->prepare("UPDATE stores SET is_active=:a WHERE id=:i");
            try {
                $u->execute([':a' => $nSt, ':i' => $id]);
                header('Location: stores.php?action=list&message=' . urlencode("Store " . ($nSt ? "activated" : "deactivated") . " successfully."));
                exit;
            } catch (PDOException $ex) {
                header('Location: stores.php?action=list&message=' . urlencode("Unable to toggle status."));
                exit;
            }
        } else {
            header('Location: stores.php?action=list&message=' . urlencode("Store not found."));
            exit;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'toggle_status' && $id > 0) {
        $s = $pdo->prepare("SELECT is_active FROM stores WHERE id=:i");
        $s->execute([':i' => $id]);
        $store = $s->fetch(PDO::FETCH_ASSOC);
        if ($store) {
            $nSt = $store['is_active'] ? 0 : 1;
            $u = $pdo->prepare("UPDATE stores SET is_active=:a WHERE id=:i");
            try {
                $u->execute([':a' => $nSt, ':i' => $id]);
                header('Location: stores.php?action=list&message=' . urlencode("Store " . ($nSt ? "activated" : "deactivated") . " successfully."));
                exit;
            } catch (PDOException $ex) {
                header('Location: stores.php?action=list&message=' . urlencode("Unable to toggle status."));
                exit;
            }
        } else {
            header('Location: stores.php?action=list&message=' . urlencode("Store not found."));
            exit;
        }
    }
}

if ($action === 'list') {
    $message = $_GET['message'] ?? '';
    $stores = [];
    try {
        $q = $pdo->query("SELECT s.id,s.name,s.address,s.phone,s.email,s.logo,s.cart_logo,s.is_active,s.created_at,u.username AS manager FROM stores s LEFT JOIN users u ON s.manager_id=u.id ORDER BY s.created_at DESC");
        $stores = $q->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        $message = json_encode(["Unable to fetch stores."]);
    }
} elseif (in_array($action, ['view', 'edit']) && $id > 0) {
    try {
        $q = $pdo->prepare("SELECT * FROM stores WHERE id=:i");
        $q->execute([':i' => $id]);
        $store = $q->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        $store = false;
    }
    if (!$store) {
        header('Location: stores.php?action=list&message=' . urlencode("Store not found."));
        exit;
    }
}
?>
<?php if ($action === 'list'): ?>
    <div class="container mt-4">
        <h2>Store Management</h2>
        <?php if ($message): ?>
            <script>
                (function() {
                    let msg = "<?= htmlspecialchars($message) ?>";
                    try {
                        let p = JSON.parse(msg);
                        if (Array.isArray(p)) {
                            p.forEach(e => Toastify({
                                text: e,
                                duration: 5000,
                                gravity: "top",
                                position: "right",
                                backgroundColor: "#dc3545"
                            }).showToast());
                        } else {
                            Toastify({
                                text: p,
                                duration: 5000,
                                gravity: "top",
                                position: "right",
                                backgroundColor: "#28a745"
                            }).showToast();
                        }
                    } catch (_) {
                        Toastify({
                            text: msg,
                            duration: 5000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "#28a745"
                        }).showToast();
                    }
                })();
            </script>
        <?php endif; ?>
        <div class="d-flex justify-content-between mb-3">
            <a href="stores.php?action=create" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Store</a>
        </div>
        <div class="table-responsive shadow-sm">
            <table id="storesTable" class="table table-striped table-hover table-sm align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Logo</th>
                        <th>Cart Logo</th>
                        <th>Manager</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stores)): ?>
                        <tr>
                            <td colspan="10" class="text-center">No stores found.</td>
                        </tr>
                        <?php else: foreach ($stores as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['name']) ?></td>
                                <td><?= htmlspecialchars($s['address']) ?></td>
                                <td><?= htmlspecialchars($s['phone']) ?></td>
                                <td><?= htmlspecialchars($s['email']) ?></td>
                                <td><?php if ($s['logo']): ?><img src="<?= htmlspecialchars($s['logo']) ?>" style="max-height:40px;"><?php else: ?>No Logo<?php endif; ?></td>
                                <td><?php if ($s['cart_logo']): ?><img src="<?= htmlspecialchars($s['cart_logo']) ?>" style="max-height:40px;"><?php else: ?>No Cart Logo<?php endif; ?></td>
                                <td><?= htmlspecialchars($s['manager'] ?? 'N/A') ?></td>
                                <td><span class="badge <?= $s['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $s['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                <td><?= htmlspecialchars($s['created_at']) ?></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a href="stores.php?action=view&id=<?= $s['id'] ?>" class="btn btn-sm btn-info" title="View Store"><i class="fas fa-eye"></i></a>
                                        <a href="stores.php?action=edit&id=<?= $s['id'] ?>" class="btn btn-sm btn-warning" title="Edit Store"><i class="fas fa-edit"></i></a>
                                        <a href="stores.php?action=delete&id=<?= $s['id'] ?>" class="btn btn-sm btn-danger" title="Delete Store" onclick="return confirm('Are you sure you want to delete this store?');"><i class="fas fa-trash-alt"></i></a>
                                        <a href="stores.php?action=toggle_status&id=<?= $s['id'] ?>" class="btn btn-sm <?= $s['is_active'] ? 'btn-secondary' : 'btn-success' ?>" title="<?= $s['is_active'] ? 'Deactivate' : 'Activate' ?>"><i class="fas <?= $s['is_active'] ? 'fa-toggle-off' : 'fa-toggle-on' ?>"></i></a>
                                    </div>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php elseif ($action === 'create'): ?>
    <div class="container mt-4">
        <h2><i class="fas fa-plus"></i> New Store</h2>
        <?php if ($message): ?>
            <script>
                (function() {
                    let m = "<?= htmlspecialchars($message) ?>";
                    try {
                        let js = JSON.parse(m);
                        if (Array.isArray(js)) {
                            js.forEach(e => Toastify({
                                text: e,
                                duration: 5000,
                                gravity: "top",
                                position: "right",
                                backgroundColor: "#dc3545"
                            }).showToast());
                        } else {
                            Toastify({
                                text: js,
                                duration: 5000,
                                gravity: "top",
                                position: "right",
                                backgroundColor: "#28a745"
                            }).showToast();
                        }
                    } catch (_) {
                        Toastify({
                            text: m,
                            duration: 5000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "#28a745"
                        }).showToast();
                    }
                })();
            </script>
        <?php endif; ?>
        <form method="POST" action="stores.php?action=create" enctype="multipart/form-data" class="shadow p-4 bg-light rounded needs-validation" novalidate>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Store Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="name" required placeholder="Store Name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Address <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="address" required placeholder="Street 123, City" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="phone" required placeholder="Phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control form-control-sm" name="email" required placeholder="store@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Manager</label>
                    <select class="form-select form-select-sm" name="manager_id">
                        <option value="">-- No Manager Selected --</option>
                        <?php
                        try {
                            $adm = $pdo->query("SELECT id,username FROM users WHERE role='admin' AND is_active=1");
                            while ($r = $adm->fetch(PDO::FETCH_ASSOC)) {
                                $sel = (isset($_POST['manager_id']) && $_POST['manager_id'] == $r['id']) ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($r['id']) . "\" $sel>" . htmlspecialchars($r['username']) . "</option>";
                            }
                        } catch (PDOException $ex) {
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Logo</label>
                    <input type="file" name="logo" class="form-control form-control-sm" accept="image/*">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cart Logo</label>
                    <input type="file" name="cart_logo" class="form-control form-control-sm" accept="image/*">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Minimum Order (€)</label>
                    <input type="number" step="0.01" name="minimum_order" required class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['minimum_order'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">AGB</label>
                    <textarea class="form-control form-control-sm wysiwyg" name="agb" rows="3"><?= htmlspecialchars($_POST['agb'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Impressum</label>
                    <textarea class="form-control form-control-sm wysiwyg" name="impressum" rows="3"><?= htmlspecialchars($_POST['impressum'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Datenschutzerklärung</label>
                    <textarea class="form-control form-control-sm wysiwyg" name="datenschutzerklaerung" rows="3"><?= htmlspecialchars($_POST['datenschutzerklaerung'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cart Description</label>
                    <textarea class="form-control form-control-sm wysiwyg" name="cart_description" rows="2"><?= htmlspecialchars($_POST['cart_description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <div class="form-check mt-4 pt-2">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" <?= isset($_POST['is_active']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active?</label>
                    </div>
                </div>
            </div>
            <hr>
            <h5>Social Links</h5>
            <div class="row g-3">
                <?php
                $socMap = ['facebook_link' => 'Facebook', 'twitter_link' => 'Twitter', 'instagram_link' => 'Instagram', 'linkedin_link' => 'LinkedIn', 'youtube_link' => 'YouTube'];
                foreach ($socMap as $k => $v) {
                    $val = htmlspecialchars($_POST[$k] ?? ''); ?>
                    <div class="col-md-6">
                        <label class="form-label"><?= $v ?> Link</label>
                        <input type="url" class="form-control form-control-sm" name="<?= $k ?>" value="<?= $val ?>">
                    </div>
                <?php } ?>
            </div>
            <hr>
            <h5>Store Location</h5>
            <p class="small text-muted">Use the map to set latitude and longitude, or enter manually. Right-click on the map to add delivery zones. Drag the main marker to adjust the store's coordinates.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Latitude</label>
                    <input type="text" class="form-control form-control-sm" name="store_lat" id="store_lat" value="<?= htmlspecialchars($_POST['store_lat'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Longitude</label>
                    <input type="text" class="form-control form-control-sm" name="store_lng" id="store_lng" value="<?= htmlspecialchars($_POST['store_lng'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <div id="storeMap" style="height:400px; border:2px solid #aaa;"></div>
                </div>
            </div>
            <hr>
            <h5>Delivery Zones</h5>
            <textarea class="form-control form-control-sm" rows="3" name="delivery_zones" id="delivery_zones" readonly><?= htmlspecialchars($_POST['delivery_zones'] ?? '') ?></textarea>
            <p class="small text-muted">Right-click the map to open the modal for zone label, radius, and price.</p>
            <hr>
            <h5>Weekly Schedule</h5>
            <?php
            $decodedWS = @json_decode('', true);
            foreach ($daysOfWeek as $d) {
                $sf = strtolower($d) . '_start';
                $ef = strtolower($d) . '_end';
                $vStart = htmlspecialchars($_POST[$sf] ?? '');
                $vEnd = htmlspecialchars($_POST[$ef] ?? ''); ?>
                <div class="row g-2 mb-2">
                    <div class="col-md-2 fw-bold"><?= $d ?></div>
                    <div class="col-md-5">
                        <label class="form-label small mb-1">Start</label>
                        <input type="time" class="form-control form-control-sm" name="<?= $sf ?>" value="<?= $vStart ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small mb-1">End</label>
                        <input type="time" class="form-control form-control-sm" name="<?= $ef ?>" value="<?= $vEnd ?>">
                    </div>
                </div>
            <?php } ?>
            <hr>
            <h5>Holidays</h5>
            <?php
            $holStr = "";
            if (isset($_POST['holidays'])) $holStr = htmlspecialchars($_POST['holidays']);
            ?>
            <textarea name="holidays" rows="3" class="form-control form-control-sm"><?= $holStr ?></textarea>
            <div class="mt-4">
                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save"></i> Create Store</button>
                <a href="stores.php?action=list" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Cancel</a>
            </div>
        </form>
    </div>
    <div class="modal fade" id="zoneModal" tabindex="-1" aria-labelledby="zoneModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Delivery Zone</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label for="zoneLabel" class="form-label">Label</label>
                    <input type="text" id="zoneLabel" class="form-control form-control-sm" placeholder="e.g. Prishtina Zone">
                    <label for="zoneRadius" class="form-label mt-2">Radius (km)</label>
                    <input type="number" step="0.1" id="zoneRadius" class="form-control form-control-sm" placeholder="e.g. 10">
                    <label for="zonePrice" class="form-label mt-2">Price (€)</label>
                    <input type="number" step="0.01" id="zonePrice" class="form-control form-control-sm" placeholder="e.g. 5.00">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnAddZone">Add Zone</button>
                </div>
            </div>
        </div>
    </div>
<?php elseif ($action === 'edit' && isset($store)): ?>
    <?php
    $decodedWS = @json_decode($store['work_schedule'] ?? '', true);
    if (!is_array($decodedWS)) $decodedWS = ['days' => [], 'holidays' => []];
    ?>
    <div class="container mt-4">
        <h2><i class="fas fa-edit"></i> Edit Store</h2>
        <?php if ($message): ?>
            <script>
                (function() {
                    let m = "<?= htmlspecialchars($message) ?>";
                    try {
                        let js = JSON.parse(m);
                        if (Array.isArray(js)) {
                            js.forEach(e => Toastify({
                                text: e,
                                duration: 5000,
                                gravity: "top",
                                position: "right",
                                backgroundColor: "#dc3545"
                            }).showToast());
                        } else {
                            Toastify({
                                text: js,
                                duration: 5000,
                                gravity: "top",
                                position: "right",
                                backgroundColor: "#28a745"
                            }).showToast();
                        }
                    } catch (_) {
                        Toastify({
                            text: m,
                            duration: 5000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "#28a745"
                        }).showToast();
                    }
                })();
            </script>
        <?php endif; ?>
        <form method="POST" action="stores.php?action=edit&id=<?= htmlspecialchars($store['id']) ?>" enctype="multipart/form-data" class="shadow p-4 bg-light rounded needs-validation" novalidate>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Store Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="name" required placeholder="Store Name" value="<?= htmlspecialchars($_POST['name'] ?? $store['name']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Address <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="address" required placeholder="Street 123, City" value="<?= htmlspecialchars($_POST['address'] ?? $store['address']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="phone" required placeholder="Phone" value="<?= htmlspecialchars($_POST['phone'] ?? $store['phone']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control form-control-sm" name="email" required placeholder="store@example.com" value="<?= htmlspecialchars($_POST['email'] ?? $store['email']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Manager</label>
                    <select class="form-select form-select-sm" name="manager_id">
                        <option value="">-- No Manager Selected --</option>
                        <?php
                        try {
                            $adm = $pdo->query("SELECT id,username FROM users WHERE role='admin' AND is_active=1");
                            while ($r = $adm->fetch(PDO::FETCH_ASSOC)) {
                                $sel = '';
                                if (isset($_POST['manager_id'])) $sel = ($_POST['manager_id'] == $r['id']) ? 'selected' : '';
                                else $sel = ($store['manager_id'] == $r['id']) ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($r['id']) . "\" $sel>" . htmlspecialchars($r['username']) . "</option>";
                            }
                        } catch (PDOException $ex) {
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Logo</label>
                    <input type="file" name="logo" class="form-control form-control-sm" accept="image/*">
                    <?php if (!empty($store['logo'])): ?>
                        <div class="mt-2">
                            <img src="<?= htmlspecialchars($store['logo']) ?>" alt="Current Logo" style="max-height:80px;">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cart Logo</label>
                    <input type="file" name="cart_logo" class="form-control form-control-sm" accept="image/*">
                    <?php if (!empty($store['cart_logo'])): ?>
                        <div class="mt-2">
                            <img src="<?= htmlspecialchars($store['cart_logo']) ?>" alt="Current Cart Logo" style="max-height:80px;">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Minimum Order (€)</label>
                    <input type="number" step="0.01" name="minimum_order" required class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['minimum_order'] ?? $store['minimum_order']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">AGB</label>
                    <textarea class="form-control form-control-sm wysiwyg" name="agb" rows="3"><?= htmlspecialchars($_POST['agb'] ?? $store['agb']) ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Impressum</label>
                    <textarea class="form-control form-control-sm wysiwyg" name="impressum" rows="3"><?= htmlspecialchars($_POST['impressum'] ?? $store['impressum']) ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Datenschutzerklärung</label>
                    <textarea class="form-control form-control-sm wysiwyg" name="datenschutzerklaerung" rows="3"><?= htmlspecialchars($_POST['datenschutzerklaerung'] ?? $store['datenschutzerklaerung']) ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cart Description</label>
                    <textarea class="form-control form-control-sm wysiwyg" name="cart_description" rows="2"><?= htmlspecialchars($_POST['cart_description'] ?? $store['cart_description']) ?></textarea>
                </div>
                <div class="col-md-6">
                    <div class="form-check mt-4 pt-2">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" <?= (!empty($store['is_active']) ? 'checked' : '') ?>>
                        <label class="form-check-label" for="is_active">Active?</label>
                    </div>
                </div>
            </div>
            <hr>
            <h5>Social Links</h5>
            <div class="row g-3">
                <?php
                $socMap = ['facebook_link' => 'Facebook', 'twitter_link' => 'Twitter', 'instagram_link' => 'Instagram', 'linkedin_link' => 'LinkedIn', 'youtube_link' => 'YouTube'];
                foreach ($socMap as $k => $v) {
                    $val = htmlspecialchars($_POST[$k] ?? $store[$k] ?? ''); ?>
                    <div class="col-md-6">
                        <label class="form-label"><?= $v ?> Link</label>
                        <input type="url" class="form-control form-control-sm" name="<?= $k ?>" value="<?= $val ?>">
                    </div>
                <?php } ?>
            </div>
            <hr>
            <h5>Store Location</h5>
            <p class="small text-muted">Use the map to set latitude and longitude, or enter manually. Right-click on the map to add delivery zones. Drag the main marker to adjust the store's coordinates.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Latitude</label>
                    <input type="text" class="form-control form-control-sm" name="store_lat" id="store_lat" value="<?= htmlspecialchars($_POST['store_lat'] ?? $store['store_lat']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Longitude</label>
                    <input type="text" class="form-control form-control-sm" name="store_lng" id="store_lng" value="<?= htmlspecialchars($_POST['store_lng'] ?? $store['store_lng']) ?>">
                </div>
                <div class="col-12">
                    <div id="storeMap" style="height:400px; border:2px solid #aaa;"></div>
                </div>
            </div>
            <hr>
            <h5>Delivery Zones</h5>
            <textarea class="form-control form-control-sm" rows="3" name="delivery_zones" id="delivery_zones" readonly><?= htmlspecialchars($_POST['delivery_zones'] ?? $store['delivery_zones'] ?? '') ?></textarea>
            <p class="small text-muted">Right-click the map to open the modal for zone label, radius, and price.</p>
            <hr>
            <h5>Weekly Schedule</h5>
            <?php
            foreach ($daysOfWeek as $d) {
                $sf = strtolower($d) . '_start';
                $ef = strtolower($d) . '_end';
                $vStart = htmlspecialchars($_POST[$sf] ?? ($decodedWS['days'][$d]['start'] ?? ''));
                $vEnd = htmlspecialchars($_POST[$ef] ?? ($decodedWS['days'][$d]['end'] ?? '')); ?>
                <div class="row g-2 mb-2">
                    <div class="col-md-2 fw-bold"><?= $d ?></div>
                    <div class="col-md-5">
                        <label class="form-label small mb-1">Start</label>
                        <input type="time" class="form-control form-control-sm" name="<?= $sf ?>" value="<?= $vStart ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small mb-1">End</label>
                        <input type="time" class="form-control form-control-sm" name="<?= $ef ?>" value="<?= $vEnd ?>">
                    </div>
                </div>
            <?php } ?>
            <hr>
            <h5>Holidays</h5>
            <?php
            $holStr = "";
            if (isset($decodedWS['holidays']) && is_array($decodedWS['holidays'])) {
                foreach ($decodedWS['holidays'] as $h) {
                    $holStr .= $h['date'] . "," . ($h['desc'] ?? 'Holiday') . "\n";
                }
            }
            $holVal = htmlspecialchars($_POST['holidays'] ?? $holStr);
            ?>
            <textarea name="holidays" rows="3" class="form-control form-control-sm"><?= $holVal ?></textarea>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save Changes</button>
                <a href="stores.php?action=list" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Cancel</a>
            </div>
        </form>
    </div>
    <div class="modal fade" id="zoneModal" tabindex="-1" aria-labelledby="zoneModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Delivery Zone</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label for="zoneLabel" class="form-label">Label</label>
                    <input type="text" id="zoneLabel" class="form-control form-control-sm" placeholder="e.g. Prishtina Zone">
                    <label for="zoneRadius" class="form-label mt-2">Radius (km)</label>
                    <input type="number" step="0.1" id="zoneRadius" class="form-control form-control-sm" placeholder="e.g. 10">
                    <label for="zonePrice" class="form-label mt-2">Price (€)</label>
                    <input type="number" step="0.01" id="zonePrice" class="form-control form-control-sm" placeholder="e.g. 5.00">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnAddZone">Add Zone</button>
                </div>
            </div>
        </div>
    </div>
<?php elseif ($action === 'view' && isset($store)): ?>
    <div class="container mt-4">
        <h2><i class="fas fa-eye"></i> Store Details</h2>
        <table class="table table-bordered table-sm">
            <tr>
                <th>ID</th>
                <td><?= htmlspecialchars($store['id']) ?></td>
            </tr>
            <tr>
                <th>Name</th>
                <td><?= htmlspecialchars($store['name']) ?></td>
            </tr>
            <tr>
                <th>Address</th>
                <td><?= htmlspecialchars($store['address']) ?></td>
            </tr>
            <tr>
                <th>Phone</th>
                <td><?= htmlspecialchars($store['phone']) ?></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><?= htmlspecialchars($store['email']) ?></td>
            </tr>
            <tr>
                <th>Logo</th>
                <td><?php if ($store['logo']): ?><img src="<?= htmlspecialchars($store['logo']) ?>" style="max-height:80px;"><?php else: ?><em>No logo</em><?php endif; ?></td>
            </tr>
            <tr>
                <th>Cart Logo</th>
                <td><?php if ($store['cart_logo']): ?><img src="<?= htmlspecialchars($store['cart_logo']) ?>" style="max-height:80px;"><?php else: ?><em>No cart logo</em><?php endif; ?></td>
            </tr>
            <tr>
                <th>Manager</th>
                <td><?= htmlspecialchars($store['manager_id'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td><?= $store['is_active'] ? 'Active' : 'Inactive' ?></td>
            </tr>
            <tr>
                <th>Created</th>
                <td><?= htmlspecialchars($store['created_at']) ?></td>
            </tr>
            <tr>
                <th>Updated</th>
                <td><?= htmlspecialchars($store['updated_at'] ?? '') ?></td>
            </tr>
            <tr>
                <th>Minimum Order (€)</th>
                <td><?= htmlspecialchars($store['minimum_order']) ?></td>
            </tr>
            <tr>
                <th>AGB</th>
                <td><?= nl2br(htmlspecialchars($store['agb'] ?? '')) ?></td>
            </tr>
            <tr>
                <th>Impressum</th>
                <td><?= nl2br(htmlspecialchars($store['impressum'] ?? '')) ?></td>
            </tr>
            <tr>
                <th>Datenschutzerklärung</th>
                <td><?= nl2br(htmlspecialchars($store['datenschutzerklaerung'] ?? '')) ?></td>
            </tr>
            <tr>
                <th>Cart Description</th>
                <td><?= nl2br(htmlspecialchars($store['cart_description'] ?? '')) ?></td>
            </tr>
            <tr>
                <th>Latitude</th>
                <td><?= htmlspecialchars($store['store_lat']) ?></td>
            </tr>
            <tr>
                <th>Longitude</th>
                <td><?= htmlspecialchars($store['store_lng']) ?></td>
            </tr>
            <tr>
                <th>Delivery Zones</th>
                <td>
                    <?php if ($store['delivery_zones']): ?>
                        <?php
                        $deliveryZones = json_decode($store['delivery_zones'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($deliveryZones)): ?>
                            <ul>
                                <?php foreach ($deliveryZones as $dz): ?>
                                    <li>
                                        <strong><?= htmlspecialchars($dz['label'] ?? 'Unnamed Zone') ?></strong>:
                                        Radius <?= htmlspecialchars($dz['radius']) ?> km -
                                        Price: €<?= htmlspecialchars($dz['price']) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <em>Invalid delivery zones data.</em>
                        <?php endif; ?>
                    <?php else: ?>
                        <em>No delivery zones</em>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
<?php endif; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" integrity="sha256-sA+ePqR5HvTUFAv3xWVPU2cY1pXkI3A7qpr3kKZx3GM=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js" integrity="sha256-o9N1jsk8N6SwWmDxc5Uo3zIxR4o4hURwYykMZ1r0QQ0=" crossorigin=""></script>
<script>
    $(function() {
        $('#storesTable').DataTable({
            language: {
                emptyTable: "No stores found."
            }
        });
        $('.form-select').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });
        tinymce.init({
            selector: 'textarea.wysiwyg',
            height: 200,
            menubar: false,
            plugins: 'advlist autolink lists link charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table paste code help wordcount',
            toolbar: 'undo redo | bold italic underline | alignleft aligncenter alignright | bullist numlist | removeformat | help',
            branding: false
        });
        $('form.needs-validation').on('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                Toastify({
                    text: "Please fill all required fields properly.",
                    duration: 5000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#dc3545"
                }).showToast();
            }
            this.classList.add('was-validated');
        });
        const mapEl = document.getElementById('storeMap');
        if (mapEl) {
            if (typeof L === 'undefined') {
                Toastify({
                    text: "Leaflet not loaded. Check your script tags.",
                    duration: 5000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#dc3545"
                }).showToast();
                return;
            }
            try {
                let lat = parseFloat($('#store_lat').val()) || 41.3275;
                let lng = parseFloat($('#store_lng').val()) || 19.8189;
                const map = L.map(mapEl).setView([lat, lng], 12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);
                let mainMarker = L.marker([lat, lng], {
                    draggable: true
                }).addTo(map);
                mainMarker.on('dragend', e => {
                    let c = e.target.getLatLng();
                    $('#store_lat').val(c.lat.toFixed(6));
                    $('#store_lng').val(c.lng.toFixed(6));
                });
                let deliveryZones = [];
                const $dz = $('#delivery_zones');
                try {
                    deliveryZones = JSON.parse($dz.val() || '[]');
                } catch (_) {
                    deliveryZones = [];
                }
                const dzLayer = L.layerGroup().addTo(map);
                let currentLatLng = null;

                function refreshDZ() {
                    dzLayer.clearLayers();
                    deliveryZones.forEach((z, i) => {
                        let circle = L.circle([z.lat, z.lng], {
                            radius: z.radius * 1000,
                            color: '#3388ff',
                            fillColor: '#3388ff',
                            fillOpacity: 0.2
                        }).addTo(dzLayer).bindPopup(`
<div><strong>${z.label||'Unnamed Zone'}</strong></div>
<div>Radius: ${z.radius} km</div>
<div>Price: €${z.price}</div>
<hr style="margin:4px 0;">
<button class="btn btn-sm btn-danger" onclick="removeZone(${i})">Remove</button>
`);
                    });
                }

                function updateDZ() {
                    $dz.val(JSON.stringify(deliveryZones, null, 2));
                }
                refreshDZ();
                map.on('contextmenu', e => {
                    currentLatLng = e.latlng;
                    const zoneModal = new bootstrap.Modal(document.getElementById('zoneModal'));
                    zoneModal.show();
                });
                $('#btnAddZone').on('click', function() {
                    let labelVal = $('#zoneLabel').val().trim();
                    let radiusVal = parseFloat($('#zoneRadius').val().trim());
                    let priceVal = parseFloat($('#zonePrice').val().trim());
                    if (!labelVal || isNaN(radiusVal) || isNaN(priceVal) || radiusVal <= 0 || priceVal < 0) {
                        Toastify({
                            text: "Please enter a valid label, positive radius, and non-negative price.",
                            duration: 4000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "#dc3545"
                        }).showToast();
                        return;
                    }
                    if (!currentLatLng) {
                        Toastify({
                            text: "Map coordinates are missing or invalid.",
                            duration: 4000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "#dc3545"
                        }).showToast();
                        return;
                    }
                    deliveryZones.push({
                        lat: currentLatLng.lat,
                        lng: currentLatLng.lng,
                        radius: radiusVal,
                        label: labelVal,
                        price: priceVal
                    });
                    updateDZ();
                    refreshDZ();
                    $('#zoneModal').modal('hide');
                    $('#zoneLabel').val('');
                    $('#zoneRadius').val('');
                    $('#zonePrice').val('');
                });
                window.removeZone = i => {
                    if (i >= 0 && i < deliveryZones.length) {
                        deliveryZones.splice(i, 1);
                        updateDZ();
                        refreshDZ();
                    }
                };
            } catch (err) {
                console.error(err);
                Toastify({
                    text: "Map initialization error.",
                    duration: 5000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#dc3545"
                }).showToast();
            }
        }
    });
</script>
<?php require_once 'includes/footer.php'; ?>