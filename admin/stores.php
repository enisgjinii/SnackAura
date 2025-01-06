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
        if ($v === '') {
            $errors[] = "Field '{$f}' is required, but was empty.";
        } else {
            $sanitized[$f] = $v;
        }
    }
    if (!empty($sanitized['email']) && !filter_var($sanitized['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format: '{$sanitized['email']}'.";
    }
    $sanitized['manager_id'] = (isset($data['manager_id']) && ctype_digit($data['manager_id'])) ? (int)$data['manager_id'] : null;
    $soc = ['facebook_link', 'twitter_link', 'instagram_link', 'linkedin_link', 'youtube_link'];
    foreach ($soc as $l) {
        $u = trim($data[$l] ?? '');
        if ($u) {
            if (!filter_var($u, FILTER_VALIDATE_URL)) {
                $errors[] = "Invalid URL for '{$l}': '{$u}'.";
            } else {
                $sanitized[$l] = $u;
            }
        } else {
            $sanitized[$l] = null;
        }
    }
    $nums = [
        'minimum_order',
        'shipping_distance_radius',
        'shipping_fee_base',
        'shipping_fee_per_km',
        'shipping_free_threshold',
        'shipping_weekend_surcharge',
        'shipping_holiday_surcharge',
        'shipping_handling_fee',
        'shipping_vat_percentage'
    ];
    foreach ($nums as $f) {
        $val = $data[$f] ?? null;
        if ($val === '' || $val === null) {
            $errors[] = "Required numeric field '{$f}' was empty.";
        } elseif (!is_numeric($val)) {
            $errors[] = "Field '{$f}' must be numeric, got: '{$val}'.";
        } elseif (floatval($val) < 0) {
            $errors[] = "Field '{$f}' cannot be negative, got: '{$val}'.";
        } else {
            $sanitized[$f] = $val;
        }
    }
    $pcz = trim($data['postal_code_zones'] ?? '');
    if ($pcz) {
        json_decode($pcz);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = "Invalid JSON in 'postal_code_zones': '{$pcz}'";
        } else {
            $sanitized['postal_code_zones'] = $pcz;
        }
    } else {
        $sanitized['postal_code_zones'] = null;
    }
    $sql = "SELECT COUNT(*) FROM stores WHERE (name=:name OR email=:email)";
    if ($id > 0) {
        $sql .= " AND id!=:id";
    }
    $stmt = $pdo->prepare($sql);
    $params = [
        ':name' => $sanitized['name'] ?? '',
        ':email' => $sanitized['email'] ?? ''
    ];
    if ($id > 0) {
        $params[':id'] = $id;
    }
    try {
        $stmt->execute($params);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "A store with this name or email already exists.";
        }
    } catch (PDOException $e) {
        error_log("validateStore DB Error: " . $e->getMessage());
        $errors[] = "DB Error in validateStore: " . $e->getMessage();
    }
    return [$errors, $sanitized];
}

function handleFileUpload(array &$errors, string $fileKey = 'logo'): ?string
{
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Upload error code {$_FILES[$fileKey]['error']} for '{$fileKey}'.";
        return null;
    }
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES[$fileKey]['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed)) {
        $errors[] = "Invalid file type for '{$fileKey}' = '{$mime}'. Allowed: " . implode(', ', $allowed);
        return null;
    }
    $dir = __DIR__ . '/uploads/logos/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        $errors[] = "Failed to create directory '{$dir}' for '{$fileKey}'. Check permissions.";
        return null;
    }
    $orig = basename($_FILES[$fileKey]['name']);
    $ext = pathinfo($orig, PATHINFO_EXTENSION);
    $newName = $fileKey . '_' . time() . '.' . $ext;
    $dest = $dir . $newName;
    if (!move_uploaded_file($_FILES[$fileKey]['tmp_name'], $dest)) {
        $errors[] = "Failed to move uploaded file to '{$dest}' for '{$fileKey}'.";
        return null;
    }
    return "uploads/logos/$newName";
}

function buildWorkScheduleJSON(array $data): string
{
    global $daysOfWeek;
    $s = ['days' => [], 'holidays' => []];
    foreach ($daysOfWeek as $d) {
        $sk = strtolower($d) . "_start";
        $ek = strtolower($d) . "_end";
        $sv = trim($data[$sk] ?? '');
        $ev = trim($data[$ek] ?? '');
        if ($sv && !preg_match('/^(?:2[0-3]|[01]\d):[0-5]\d$/', $sv)) {
            $sv = '';
        }
        if ($ev && !preg_match('/^(?:2[0-3]|[01]\d):[0-5]\d$/', $ev)) {
            $ev = '';
        }
        $s['days'][$d] = ['start' => $sv, 'end' => $ev];
    }
    $hol = trim($data['holidays'] ?? '');
    if ($hol) {
        foreach (explode("\n", $hol) as $l) {
            $l = trim($l);
            if (!$l) continue;
            $p = explode(',', $l, 2);
            $date = trim($p[0] ?? '');
            $desc = trim($p[1] ?? 'Holiday');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $s['holidays'][] = ['date' => $date, 'desc' => $desc];
            }
        }
    }
    return json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function buildSettings($post, $data): array
{
    return [
        'minimum_order' => $data['minimum_order'] ?? '5.00',
        'agb' => trim($post['agb'] ?? ''),
        'impressum' => trim($post['impressum'] ?? ''),
        'datenschutzerklaerung' => trim($post['datenschutzerklaerung'] ?? ''),
        'facebook_link' => $data['facebook_link'] ?? null,
        'twitter_link' => $data['twitter_link'] ?? null,
        'instagram_link' => $data['instagram_link'] ?? null,
        'linkedin_link' => $data['linkedin_link'] ?? null,
        'youtube_link' => $data['youtube_link'] ?? null,
        'cart_description' => trim($post['cart_description'] ?? ''),
        'store_lat' => trim($post['store_lat'] ?? '41.327500'),
        'store_lng' => trim($post['store_lng'] ?? '19.818900'),
        'shipping_calculation_mode' => trim($post['shipping_calculation_mode'] ?? 'radius'),
        'shipping_distance_radius' => trim($data['shipping_distance_radius'] ?? '10'),
        'shipping_fee_base' => trim($data['shipping_fee_base'] ?? '0.00'),
        'shipping_fee_per_km' => trim($data['shipping_fee_per_km'] ?? '0.50'),
        'shipping_free_threshold' => trim($data['shipping_free_threshold'] ?? '50.00'),
        'google_maps_api_key' => trim($post['google_maps_api_key'] ?? ''),
        'postal_code_zones' => $data['postal_code_zones'] ?? null,
        'shipping_enable_google_distance_matrix' => isset($post['shipping_enable_google_distance_matrix']) ? 1 : 0,
        'shipping_matrix_region' => trim($post['shipping_matrix_region'] ?? ''),
        'shipping_matrix_units' => trim($post['shipping_matrix_units'] ?? 'metric'),
        'shipping_weekend_surcharge' => trim($data['shipping_weekend_surcharge'] ?? '0.00'),
        'shipping_holiday_surcharge' => trim($data['shipping_holiday_surcharge'] ?? '0.00'),
        'shipping_handling_fee' => trim($data['shipping_handling_fee'] ?? '0.00'),
        'shipping_vat_percentage' => trim($data['shipping_vat_percentage'] ?? '20.00')
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        list($errors, $sanitized) = validateStore($_POST, $pdo);
        $logo = handleFileUpload($errors, 'logo');
        $cartLogo = handleFileUpload($errors, 'cart_logo');
        $ws = buildWorkScheduleJSON($_POST);
        $settings = buildSettings($_POST, $sanitized);
        if (!empty($errors)) {
            $d = implode('<br>', array_map('htmlspecialchars', $errors));
            $message = '<div class="alert alert-danger">Errors occurred:<br>' . $d . '</div>';
        } else {
            $sql = "INSERT INTO stores(name,address,phone,email,manager_id,is_active,logo,cart_logo,work_schedule,minimum_order,agb,impressum,datenschutzerklaerung,facebook_link,twitter_link,instagram_link,linkedin_link,youtube_link,cart_description,store_lat,store_lng,shipping_calculation_mode,shipping_distance_radius,shipping_fee_base,shipping_fee_per_km,shipping_free_threshold,google_maps_api_key,postal_code_zones,shipping_enable_google_distance_matrix,shipping_matrix_region,shipping_matrix_units,shipping_weekend_surcharge,shipping_holiday_surcharge,shipping_handling_fee,shipping_vat_percentage,created_at)VALUES(:n,:ad,:ph,:em,:m,1,:lg,:cl,:ws,:mn,:agb,:imp,:dse,:fb,:tw,:ig,:li,:yt,:cd,:lat,:lng,:scm,:sdr,:sfb,:sfkm,:sft,:gapi,:pcz,:sedm,:smr,:smu,:swe,:sho,:shf,:svat,NOW())";
            $stmt = $pdo->prepare($sql);
            $params = [
                ':n' => $sanitized['name'],
                ':ad' => $sanitized['address'],
                ':ph' => $sanitized['phone'],
                ':em' => $sanitized['email'],
                ':m' => $sanitized['manager_id'],
                ':lg' => $logo,
                ':cl' => $cartLogo,
                ':ws' => $ws,
                ':mn' => $settings['minimum_order'],
                ':agb' => $settings['agb'],
                ':imp' => $settings['impressum'],
                ':dse' => $settings['datenschutzerklaerung'],
                ':fb' => $settings['facebook_link'],
                ':tw' => $settings['twitter_link'],
                ':ig' => $settings['instagram_link'],
                ':li' => $settings['linkedin_link'],
                ':yt' => $settings['youtube_link'],
                ':cd' => $settings['cart_description'],
                ':lat' => $settings['store_lat'],
                ':lng' => $settings['store_lng'],
                ':scm' => $settings['shipping_calculation_mode'],
                ':sdr' => $settings['shipping_distance_radius'],
                ':sfb' => $settings['shipping_fee_base'],
                ':sfkm' => $settings['shipping_fee_per_km'],
                ':sft' => $settings['shipping_free_threshold'],
                ':gapi' => $settings['google_maps_api_key'],
                ':pcz' => $settings['postal_code_zones'],
                ':sedm' => $settings['shipping_enable_google_distance_matrix'],
                ':smr' => $settings['shipping_matrix_region'],
                ':smu' => $settings['shipping_matrix_units'],
                ':swe' => $settings['shipping_weekend_surcharge'],
                ':sho' => $settings['shipping_holiday_surcharge'],
                ':shf' => $settings['shipping_handling_fee'],
                ':svat' => $settings['shipping_vat_percentage']
            ];
            try {
                $stmt->execute($params);
                header('Location: stores.php?action=list&message=' . urlencode("Store created successfully."));
                exit;
            } catch (PDOException $e) {
                error_log("Error creating store: " . $e->getMessage());
                $message = '<div class="alert alert-danger">Unable to create store. Error detail: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    } elseif ($action === 'edit' && $id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM stores WHERE id=:id");
            $stmt->execute([':id' => $id]);
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DB error fetching store: " . $e->getMessage());
            $store = false;
        }
        if (!$store) {
            header('Location: stores.php?action=list&message=' . urlencode("Store not found."));
            exit;
        }
        list($errors, $sanitized) = validateStore($_POST, $pdo, $id);
        $logo = handleFileUpload($errors, 'logo');
        $cartLogo = handleFileUpload($errors, 'cart_logo');
        $ws = buildWorkScheduleJSON($_POST);
        $settings = buildSettings($_POST, $sanitized);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        if (!empty($errors)) {
            $d = implode('<br>', array_map('htmlspecialchars', $errors));
            $message = '<div class="alert alert-danger">Errors occurred:<br>' . $d . '</div>';
        } else {
            $f = [
                'name' => ':n',
                'address' => ':ad',
                'phone' => ':ph',
                'email' => ':em',
                'manager_id' => ':m',
                'is_active' => ':ia',
                'work_schedule' => ':ws',
                'minimum_order' => ':min',
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
                'shipping_calculation_mode' => ':scm',
                'shipping_distance_radius' => ':sdr',
                'shipping_fee_base' => ':sfb',
                'shipping_fee_per_km' => ':sfkm',
                'shipping_free_threshold' => ':sft',
                'google_maps_api_key' => ':gapi',
                'postal_code_zones' => ':pcz',
                'shipping_enable_google_distance_matrix' => ':sedm',
                'shipping_matrix_region' => ':smr',
                'shipping_matrix_units' => ':smu',
                'shipping_weekend_surcharge' => ':swe',
                'shipping_holiday_surcharge' => ':sho',
                'shipping_handling_fee' => ':shf',
                'shipping_vat_percentage' => ':svat'
            ];
            $p = [
                ':n' => $sanitized['name'],
                ':ad' => $sanitized['address'],
                ':ph' => $sanitized['phone'],
                ':em' => $sanitized['email'],
                ':m' => $sanitized['manager_id'],
                ':ia' => $is_active,
                ':ws' => $ws,
                ':min' => $settings['minimum_order'],
                ':agb' => $settings['agb'],
                ':imp' => $settings['impressum'],
                ':dse' => $settings['datenschutzerklaerung'],
                ':fb' => $settings['facebook_link'],
                ':tw' => $settings['twitter_link'],
                ':ig' => $settings['instagram_link'],
                ':li' => $settings['linkedin_link'],
                ':yt' => $settings['youtube_link'],
                ':cd' => $settings['cart_description'],
                ':lat' => $settings['store_lat'],
                ':lng' => $settings['store_lng'],
                ':scm' => $settings['shipping_calculation_mode'],
                ':sdr' => $settings['shipping_distance_radius'],
                ':sfb' => $settings['shipping_fee_base'],
                ':sfkm' => $settings['shipping_fee_per_km'],
                ':sft' => $settings['shipping_free_threshold'],
                ':gapi' => $settings['google_maps_api_key'],
                ':pcz' => $settings['postal_code_zones'],
                ':sedm' => $settings['shipping_enable_google_distance_matrix'],
                ':smr' => $settings['shipping_matrix_region'],
                ':smu' => $settings['shipping_matrix_units'],
                ':swe' => $settings['shipping_weekend_surcharge'],
                ':sho' => $settings['shipping_holiday_surcharge'],
                ':shf' => $settings['shipping_handling_fee'],
                ':svat' => $settings['shipping_vat_percentage']
            ];
            if ($logo) {
                $f['logo'] = ':lg';
                $p[':lg'] = $logo;
            }
            if ($cartLogo) {
                $f['cart_logo'] = ':cl';
                $p[':cl'] = $cartLogo;
            }
            $set = [];
            foreach ($f as $k => $v) {
                $set[] = "$k=$v";
            }
            $sql = "UPDATE stores SET " . implode(', ', $set) . ",updated_at=NOW() WHERE id=:id";
            $p[':id'] = $id;
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($p);
                if ($logo && !empty($store['logo']) && file_exists(__DIR__ . '/' . $store['logo'])) {
                    unlink(__DIR__ . '/' . $store['logo']);
                }
                if ($cartLogo && !empty($store['cart_logo']) && file_exists(__DIR__ . '/' . $store['cart_logo'])) {
                    unlink(__DIR__ . '/' . $store['cart_logo']);
                }
                header('Location: stores.php?action=list&message=' . urlencode("Store updated successfully."));
                exit;
            } catch (PDOException $e) {
                error_log("Error updating store: " . $e->getMessage());
                $message = '<div class="alert alert-danger">Unable to update store. Detailed error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    } elseif ($action === 'delete' && $id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT logo,cart_logo FROM stores WHERE id=:id");
            $stmt->execute([':id' => $id]);
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $store = false;
        }
        if ($store) {
            try {
                $pdo->prepare("DELETE FROM stores WHERE id=:id")->execute([':id' => $id]);
                if (!empty($store['logo']) && file_exists(__DIR__ . '/' . $store['logo'])) {
                    unlink(__DIR__ . '/' . $store['logo']);
                }
                if (!empty($store['cart_logo']) && file_exists(__DIR__ . '/' . $store['cart_logo'])) {
                    unlink(__DIR__ . '/' . $store['cart_logo']);
                }
                header('Location: stores.php?action=list&message=' . urlencode("Store deleted successfully."));
                exit;
            } catch (PDOException $e) {
                error_log($e->getMessage());
                header('Location: stores.php?action=list&message=' . urlencode("Unable to delete store. " . $e->getMessage()));
                exit;
            }
        } else {
            header('Location: stores.php?action=list&message=' . urlencode("Store not found."));
            exit;
        }
    } elseif ($action === 'assign_admin' && $id > 0) {
        $admin_id = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : null;
        $createNew = isset($_POST['create_new_admin']) && $_POST['create_new_admin'] == '1';
        if ($createNew) {
            $newUser = trim($_POST['new_admin_username'] ?? '');
            $newPass = trim($_POST['new_admin_password'] ?? '');
            if ($newUser && $newPass) {
                $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=:u");
                $check->execute([':u' => $newUser]);
                if ($check->fetchColumn() > 0) {
                    $message = '<div class="alert alert-danger">Username already exists. Choose another one.</div>';
                } else {
                    $hp = password_hash($newPass, PASSWORD_BCRYPT);
                    try {
                        $pdo->beginTransaction();
                        $ins = $pdo->prepare("INSERT INTO users(username,password,role,is_active)VALUES(:u,:p,'admin',1)");
                        $ins->execute([':u' => $newUser, ':p' => $hp]);
                        $newAdminId = $pdo->lastInsertId();
                        $pdo->prepare("UPDATE stores SET manager_id=:m WHERE id=:id")->execute([':m' => $newAdminId, ':id' => $id]);
                        $pdo->commit();
                        header('Location: stores.php?action=list&message=' . urlencode("New admin created and assigned."));
                        exit;
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        error_log($e->getMessage());
                        $message = '<div class="alert alert-danger">Unable to create admin. ' . $e->getMessage() . '</div>';
                    }
                }
            } else {
                $message = '<div class="alert alert-danger">Username and password are required to create a new admin.</div>';
            }
        } else {
            if ($admin_id) {
                try {
                    $pdo->prepare("UPDATE stores SET manager_id=:m WHERE id=:id")->execute([':m' => $admin_id, ':id' => $id]);
                    header('Location: stores.php?action=list&message=' . urlencode("Admin assigned successfully."));
                    exit;
                } catch (PDOException $e) {
                    error_log($e->getMessage());
                    $message = '<div class="alert alert-danger">Unable to assign admin. ' . $e->getMessage() . '</div>';
                }
            } else {
                $message = '<div class="alert alert-danger">No admin selected.</div>';
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'toggle_status' && $id > 0) {
        $stmt = $pdo->prepare("SELECT is_active FROM stores WHERE id=:id");
        $stmt->execute([':id' => $id]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($store) {
            $newSt = $store['is_active'] ? 0 : 1;
            $u = $pdo->prepare("UPDATE stores SET is_active=:s WHERE id=:id");
            try {
                $u->execute([':s' => $newSt, ':id' => $id]);
                $txt = $newSt ? 'activated' : 'deactivated';
                header('Location: stores.php?action=list&message=' . urlencode("Store $txt successfully."));
                exit;
            } catch (PDOException $e) {
                error_log($e->getMessage());
                header('Location: stores.php?action=list&message=' . urlencode("Unable to toggle status. " . $e->getMessage()));
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
        $stmt = $pdo->prepare("SELECT s.id,s.name,s.address,s.phone,s.email,s.logo,s.cart_logo,u.username AS manager,s.is_active,s.created_at FROM stores s LEFT JOIN users u ON s.manager_id=u.id ORDER BY s.created_at DESC");
        $stmt->execute();
        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $message = '<div class="alert alert-danger">Unable to fetch stores. ' . $e->getMessage() . '</div>';
    }
} elseif (in_array($action, ['edit', 'view', 'assign_admin']) && $id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM stores WHERE id=:id");
        $stmt->execute([':id' => $id]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $store = false;
    }
    if (!$store) {
        header('Location: stores.php?action=list&message=' . urlencode("Store not found."));
        exit;
    }
    if ($action === 'view') {
        try {
            $v = $pdo->prepare("SELECT s.*,u.username AS manager FROM stores s LEFT JOIN users u ON s.manager_id=u.id WHERE s.id=:id");
            $v->execute([':id' => $id]);
            $store = $v->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $store = false;
        }
        if (!$store) {
            header('Location: stores.php?action=list&message=' . urlencode("Store not found."));
            exit;
        }
    } elseif ($action === 'assign_admin') {
        try {
            $adm = $pdo->prepare('SELECT id,username FROM users WHERE role="admin" AND is_active=1');
            $adm->execute();
            $admins = $adm->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $admins = [];
            $message = '<div class="alert alert-danger">Unable to fetch admins. ' . $e->getMessage() . '</div>';
        }
    }
}
?>
<?php if ($action === 'list'): ?>
    <div class="container mt-4">
        <h2 class="mb-4">Store Management</h2>
        <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-info"><?= htmlspecialchars($_GET['message']) ?></div>
        <?php elseif ($message): ?>
            <?= $message ?>
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
                    <?php else: ?>
                        <?php foreach ($stores as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['name']) ?></td>
                                <td><?= htmlspecialchars($s['address']) ?></td>
                                <td><?= htmlspecialchars($s['phone']) ?></td>
                                <td><?= htmlspecialchars($s['email']) ?></td>
                                <td>
                                    <?php if (!empty($s['logo'])): ?>
                                        <img src="<?= htmlspecialchars($s['logo']) ?>" alt="Logo" style="max-height:40px;">
                                    <?php else: ?>
                                        <span class="text-muted">No Logo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($s['cart_logo'])): ?>
                                        <img src="<?= htmlspecialchars($s['cart_logo']) ?>" alt="Cart Logo" style="max-height:40px;">
                                    <?php else: ?>
                                        <span class="text-muted">No Cart Logo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($s['manager'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge <?= $s['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($s['created_at']) ?></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a href="stores.php?action=view&id=<?= $s['id'] ?>" class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="stores.php?action=edit&id=<?= $s['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="stores.php?action=delete&id=<?= $s['id'] ?>" class="btn btn-sm btn-danger" title="Delete"
                                            onclick="return confirm('Are you sure you want to delete this store?');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                        <a href="stores.php?action=toggle_status&id=<?= $s['id'] ?>"
                                            class="btn btn-sm <?= $s['is_active'] ? 'btn-secondary' : 'btn-success' ?>"
                                            title="<?= $s['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                            <i class="fas <?= $s['is_active'] ? 'fa-toggle-off' : 'fa-toggle-on' ?>"></i>
                                        </a>
                                        <a href="stores.php?action=assign_admin&id=<?= $s['id'] ?>" class="btn btn-sm btn-primary" title="Assign Admin">
                                            <i class="fas fa-user-cog"></i>
                                        </a>
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
        <h2 class="mb-4"><i class="fas fa-plus"></i> New Store</h2>
        <?php if ($message) echo $message; ?>
        <form method="POST" action="stores.php?action=create" enctype="multipart/form-data" class="shadow p-4 bg-light rounded needs-validation" novalidate>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="name" name="name" required maxlength="100"
                        value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    <div class="invalid-feedback">Please provide a store name.</div>
                </div>
                <div class="col-md-6">
                    <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="address" name="address" required maxlength="255"
                        value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                    <div class="invalid-feedback">Please provide an address.</div>
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="phone" name="phone" required maxlength="20"
                        value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    <div class="invalid-feedback">Please provide a phone number.</div>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control form-control-sm" id="email" name="email" required maxlength="100"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <div class="invalid-feedback">Please provide a valid email.</div>
                </div>
                <div class="col-md-6">
                    <label for="manager_id" class="form-label">Manager</label>
                    <select class="form-select form-select-sm" id="manager_id" name="manager_id">
                        <option value="">Select Manager</option>
                        <?php
                        try {
                            $admin_stmt = $pdo->prepare('SELECT id,username FROM users WHERE role="admin" AND is_active=1');
                            $admin_stmt->execute();
                            $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($admins as $adm) {
                                $sel = (isset($_POST['manager_id']) && $_POST['manager_id'] == $adm['id']) ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($adm['id']) . "\" $sel>" . htmlspecialchars($adm['username']) . "</option>";
                            }
                        } catch (PDOException $e) {
                            error_log($e->getMessage());
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="logo" class="form-label">Logo</label>
                    <input type="file" class="form-control form-control-sm" id="logo" name="logo" accept="image/*">
                </div>
                <div class="col-md-6">
                    <label for="cart_logo" class="form-label">Cart Logo</label>
                    <input type="file" class="form-control form-control-sm" id="cart_logo" name="cart_logo" accept="image/*">
                </div>
                <div class="col-md-6">
                    <label for="minimum_order" class="form-label">Minimum Order (€)</label>
                    <input type="number" step="0.01" class="form-control form-control-sm" id="minimum_order" name="minimum_order"
                        required value="<?= htmlspecialchars($_POST['minimum_order'] ?? '5.00') ?>">
                    <div class="invalid-feedback">Please provide a valid minimum order.</div>
                </div>
                <div class="col-md-6">
                    <label for="agb" class="form-label">AGB</label>
                    <textarea class="form-control form-control-sm wysiwyg" id="agb" name="agb" rows="3"><?= htmlspecialchars($_POST['agb'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label for="impressum" class="form-label">Impressum</label>
                    <textarea class="form-control form-control-sm wysiwyg" id="impressum" name="impressum" rows="4"><?= htmlspecialchars($_POST['impressum'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label for="datenschutzerklaerung" class="form-label">Datenschutzerklärung</label>
                    <textarea class="form-control form-control-sm wysiwyg" id="datenschutzerklaerung" name="datenschutzerklaerung" rows="4"><?= htmlspecialchars($_POST['datenschutzerklaerung'] ?? '') ?></textarea>
                </div>
            </div>
            <hr>
            <h5>Social Links</h5>
            <div class="row g-3">
                <?php
                $soc = [
                    'facebook_link' => 'Facebook Link',
                    'twitter_link' => 'Twitter Link',
                    'instagram_link' => 'Instagram Link',
                    'linkedin_link' => 'LinkedIn Link',
                    'youtube_link' => 'YouTube Link'
                ];
                foreach ($soc as $k => $l): ?>
                    <div class="col-md-6">
                        <label for="<?= $k ?>" class="form-label"><?= $l ?></label>
                        <input type="url" class="form-control form-control-sm" id="<?= $k ?>" name="<?= $k ?>" value="<?= htmlspecialchars($_POST[$k] ?? '') ?>">
                    </div>
                <?php endforeach; ?>
            </div>
            <hr>
            <h5>Cart Settings</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="cart_description" class="form-label">Cart Description</label>
                    <textarea class="form-control form-control-sm wysiwyg" id="cart_description" name="cart_description" rows="3"><?= htmlspecialchars($_POST['cart_description'] ?? '') ?></textarea>
                </div>
            </div>
            <hr>
            <h5>Store Location</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="store_lat" class="form-label">Latitude</label>
                    <input type="text" class="form-control form-control-sm" id="store_lat" name="store_lat"
                        value="<?= htmlspecialchars($_POST['store_lat'] ?? '41.327500') ?>">
                </div>
                <div class="col-md-6">
                    <label for="store_lng" class="form-label">Longitude</label>
                    <input type="text" class="form-control form-control-sm" id="store_lng" name="store_lng"
                        value="<?= htmlspecialchars($_POST['store_lng'] ?? '19.818900') ?>">
                </div>
                <div class="col-12">
                    <div id="shippingMap" style="height:400px;margin-top:20px;"></div>
                </div>
            </div>
            <hr>
            <h5>Shipping Settings</h5>
            <div class="row g-3">
                <?php
                $fields = [
                    ['name' => 'shipping_calculation_mode', 'label' => 'Shipping Calculation Mode', 'type' => 'select', 'options' => ['radius' => 'Radius (km)', 'postal' => 'Postal Code', 'both' => 'Both']],
                    ['name' => 'shipping_distance_radius', 'label' => 'Max Distance Radius (km)', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'shipping_fee_base', 'label' => 'Base Shipping Fee (€)', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'shipping_fee_per_km', 'label' => 'Fee per Km (€)', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'shipping_free_threshold', 'label' => 'Free Shipping Above (€)', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'google_maps_api_key', 'label' => 'Google Maps API Key', 'type' => 'text'],
                    ['name' => 'postal_code_zones', 'label' => 'Postal Code Zones (JSON)', 'type' => 'textarea', 'rows' => 3],
                    ['name' => 'shipping_enable_google_distance_matrix', 'label' => 'Enable Google Distance Matrix?', 'type' => 'select', 'options' => ['0' => 'No', '1' => 'Yes']],
                    ['name' => 'shipping_matrix_region', 'label' => 'Distance Matrix Region', 'type' => 'text'],
                    ['name' => 'shipping_matrix_units', 'label' => 'Distance Matrix Units', 'type' => 'select', 'options' => ['metric' => 'Metric (km)', 'imperial' => 'Imperial (miles)']],
                    ['name' => 'shipping_weekend_surcharge', 'label' => 'Weekend Surcharge (€)', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'shipping_holiday_surcharge', 'label' => 'Holiday Surcharge (€)', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'shipping_handling_fee', 'label' => 'Handling Fee (€)', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'shipping_vat_percentage', 'label' => 'VAT on Shipping (%)', 'type' => 'number', 'step' => '0.01']
                ];
                foreach ($fields as $f): ?>
                    <div class="col-md-6">
                        <label for="<?= $f['name'] ?>" class="form-label"><?= $f['label'] ?></label>
                        <?php if ($f['type'] === 'select'): ?>
                            <select class="form-select form-select-sm" id="<?= $f['name'] ?>" name="<?= $f['name'] ?>">
                                <?php foreach ($f['options'] as $val => $txt) {
                                    $sel = (isset($_POST[$f['name']]) && $_POST[$f['name']] == $val) ? 'selected' : '';
                                    echo "<option value=\"" . htmlspecialchars($val) . "\" $sel>" . htmlspecialchars($txt) . "</option>";
                                } ?>
                            </select>
                        <?php elseif ($f['type'] === 'textarea'): ?>
                            <textarea class="form-control form-control-sm" id="<?= $f['name'] ?>" name="<?= $f['name'] ?>" rows="<?= $f['rows'] ?? 3 ?>"><?= htmlspecialchars($_POST[$f['name']] ?? '') ?></textarea>
                        <?php else: ?>
                            <input type="<?= $f['type'] ?>" step="<?= $f['step'] ?? '' ?>" class="form-control form-control-sm" id="<?= $f['name'] ?>" name="<?= $f['name'] ?>" value="<?= htmlspecialchars($_POST[$f['name']] ?? '') ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <hr>
            <h5>Weekly Schedule</h5>
            <?php foreach ($daysOfWeek as $d):
                $sf = strtolower($d) . "_start";
                $ef = strtolower($d) . "_end"; ?>
                <div class="row g-2 mb-2">
                    <div class="col-md-2 d-flex align-items-center fw-bold"><?= $d ?></div>
                    <div class="col-md-5">
                        <label class="form-label mb-0 small">Start Time</label>
                        <input type="time" class="form-control form-control-sm" name="<?= $sf ?>" value="<?= htmlspecialchars($_POST[$sf] ?? '') ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label mb-0 small">End Time</label>
                        <input type="time" class="form-control form-control-sm" name="<?= $ef ?>" value="<?= htmlspecialchars($_POST[$ef] ?? '') ?>">
                    </div>
                </div>
            <?php endforeach; ?>
            <hr>
            <h5>Holidays</h5>
            <p class="small text-muted">Enter one holiday per line in the format: <code>YYYY-MM-DD,Description</code></p>
            <textarea class="form-control form-control-sm" name="holidays" rows="4"><?= htmlspecialchars($_POST['holidays'] ?? '') ?></textarea>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save"></i> Create Store</button>
                <a href="stores.php?action=list" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Cancel</a>
            </div>
        </form>
    </div>
<?php elseif ($action === 'edit' && isset($store)): ?>
    <?php
    $decoded = @json_decode($store['work_schedule'] ?? '', true);
    if (!is_array($decoded)) $decoded = ["days" => [], "holidays" => []];
    ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-edit"></i> Edit Store</h2>
        <?php if ($message) echo $message; ?>
        <form method="POST" action="stores.php?action=edit&id=<?= htmlspecialchars($store['id']) ?>" enctype="multipart/form-data"
            class="shadow p-4 bg-light rounded needs-validation" novalidate>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="name" name="name" required maxlength="100"
                        value="<?= htmlspecialchars($_POST['name'] ?? $store['name']) ?>">
                    <div class="invalid-feedback">Please provide a store name.</div>
                </div>
                <div class="col-md-6">
                    <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="address" name="address" required maxlength="255"
                        value="<?= htmlspecialchars($_POST['address'] ?? $store['address']) ?>">
                    <div class="invalid-feedback">Please provide an address.</div>
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="phone" name="phone" required maxlength="20"
                        value="<?= htmlspecialchars($_POST['phone'] ?? $store['phone']) ?>">
                    <div class="invalid-feedback">Please provide a phone number.</div>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control form-control-sm" id="email" name="email" required maxlength="100"
                        value="<?= htmlspecialchars($_POST['email'] ?? $store['email']) ?>">
                    <div class="invalid-feedback">Please provide a valid email.</div>
                </div>
                <div class="col-md-6">
                    <label for="manager_id" class="form-label">Manager</label>
                    <select class="form-select form-select-sm" id="manager_id" name="manager_id">
                        <option value="">Select Manager</option>
                        <?php
                        try {
                            $adm = $pdo->prepare('SELECT id,username FROM users WHERE role="admin" AND is_active=1');
                            $adm->execute();
                            $admins = $adm->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($admins as $a) {
                                $sel = ((isset($_POST['manager_id']) && $_POST['manager_id'] == $a['id']) || (!isset($_POST['manager_id']) && $store['manager_id'] == $a['id'])) ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($a['id']) . "\" $sel>" . htmlspecialchars($a['username']) . "</option>";
                            }
                        } catch (PDOException $e) {
                            error_log($e->getMessage());
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="logo" class="form-label">Logo</label>
                    <input type="file" class="form-control form-control-sm" id="logo" name="logo" accept="image/*">
                    <?php if (!empty($store['logo'])): ?>
                        <div class="mt-2">
                            <img src="<?= htmlspecialchars($store['logo']) ?>" alt="Current Logo" style="max-height:80px;">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label for="cart_logo" class="form-label">Cart Logo</label>
                    <input type="file" class="form-control form-control-sm" id="cart_logo" name="cart_logo" accept="image/*">
                    <?php if (!empty($store['cart_logo'])): ?>
                        <div class="mt-2">
                            <img src="<?= htmlspecialchars($store['cart_logo']) ?>" alt="Current Cart Logo" style="max-height:80px;">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label for="minimum_order" class="form-label">Minimum Order (€)</label>
                    <input type="number" step="0.01" class="form-control form-control-sm" id="minimum_order" name="minimum_order"
                        required value="<?= htmlspecialchars($_POST['minimum_order'] ?? $store['minimum_order']) ?>">
                    <div class="invalid-feedback">Please provide a valid minimum order.</div>
                </div>
                <div class="col-md-6">
                    <label for="agb" class="form-label">AGB</label>
                    <textarea class="form-control form-control-sm wysiwyg" id="agb" name="agb" rows="3"><?= htmlspecialchars($_POST['agb'] ?? $store['agb']) ?></textarea>
                </div>
                <div class="col-md-6">
                    <label for="impressum" class="form-label">Impressum</label>
                    <textarea class="form-control form-control-sm wysiwyg" id="impressum" name="impressum" rows="4"><?= htmlspecialchars($_POST['impressum'] ?? $store['impressum']) ?></textarea>
                </div>
                <div class="col-md-6">
                    <label for="datenschutzerklaerung" class="form-label">Datenschutzerklärung</label>
                    <textarea class="form-control form-control-sm wysiwyg" id="datenschutzerklaerung" name="datenschutzerklaerung" rows="4"><?= htmlspecialchars($_POST['datenschutzerklaerung'] ?? $store['datenschutzerklaerung']) ?></textarea>
                </div>
            </div>
            <hr>
            <h5>Social Links</h5>
            <div class="row g-3">
                <?php
                $soc = ['facebook_link' => 'Facebook Link', 'twitter_link' => 'Twitter Link', 'instagram_link' => 'Instagram Link', 'linkedin_link' => 'LinkedIn Link', 'youtube_link' => 'YouTube Link'];
                foreach ($soc as $k => $l):
                    $val = htmlspecialchars($_POST[$k] ?? $store[$k] ?? '');
                ?>
                    <div class="col-md-6">
                        <label for="<?= $k ?>" class="form-label"><?= $l ?></label>
                        <input type="url" class="form-control form-control-sm" id="<?= $k ?>" name="<?= $k ?>" value="<?= $val ?>">
                    </div>
                <?php endforeach; ?>
            </div>
            <hr>
            <h5>Cart Settings</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="cart_description" class="form-label">Cart Description</label>
                    <textarea class="form-control form-control-sm wysiwyg" id="cart_description" name="cart_description" rows="3"><?= htmlspecialchars($_POST['cart_description'] ?? $store['cart_description']) ?></textarea>
                </div>
            </div>
            <hr>
            <h5>Store Location</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="store_lat" class="form-label">Latitude</label>
                    <input type="text" class="form-control form-control-sm" id="store_lat" name="store_lat"
                        value="<?= htmlspecialchars($_POST['store_lat'] ?? $store['store_lat']) ?>">
                </div>
                <div class="col-md-6">
                    <label for="store_lng" class="form-label">Longitude</label>
                    <input type="text" class="form-control form-control-sm" id="store_lng" name="store_lng"
                        value="<?= htmlspecialchars($_POST['store_lng'] ?? $store['store_lng']) ?>">
                </div>
                <div class="col-12">
                    <div id="shippingMap" style="height:400px;margin-top:20px;"></div>
                </div>
            </div>
            <hr>
            <h5>Shipping Settings</h5>
            <div class="row g-3">
                <?php
                $shipFields = [
                    ['name' => 'shipping_calculation_mode', 'label' => 'Shipping Calculation Mode', 'type' => 'select', 'options' => ['radius' => 'Radius (km)', 'postal' => 'Postal Code', 'both' => 'Both']],
                    ['name' => 'shipping_distance_radius', 'label' => 'Max Distance Radius (km)', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'shipping_fee_base', 'label' => 'Base Shipping Fee (€)', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'shipping_fee_per_km', 'label' => 'Fee per Km (€)', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'shipping_free_threshold', 'label' => 'Free Shipping Above (€)', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'google_maps_api_key', 'label' => 'Google Maps API Key', 'type' => 'text'],
                    ['name' => 'postal_code_zones', 'label' => 'Postal Code Zones (JSON)', 'type' => 'textarea', 'rows' => 3],
                    ['name' => 'shipping_enable_google_distance_matrix', 'label' => 'Enable Google Distance Matrix?', 'type' => 'select', 'options' => ['0' => 'No', '1' => 'Yes']],
                    ['name' => 'shipping_matrix_region', 'label' => 'Distance Matrix Region', 'type' => 'text'],
                    ['name' => 'shipping_matrix_units', 'label' => 'Distance Matrix Units', 'type' => 'select', 'options' => ['metric' => 'Metric (km)', 'imperial' => 'Imperial (miles)']],
                    ['name' => 'shipping_weekend_surcharge', 'label' => 'Weekend Surcharge (€)', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'shipping_holiday_surcharge', 'label' => 'Holiday Surcharge (€)', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'shipping_handling_fee', 'label' => 'Handling Fee (€)', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'shipping_vat_percentage', 'label' => 'VAT on Shipping (%)', 'type' => 'number', 'step' => '0.01']
                ];
                foreach ($shipFields as $sf): ?>
                    <div class="col-md-6">
                        <label for="<?= $sf['name'] ?>" class="form-label"><?= $sf['label'] ?></label>
                        <?php if ($sf['type'] === 'select'): ?>
                            <select class="form-select form-select-sm" id="<?= $sf['name'] ?>" name="<?= $sf['name'] ?>">
                                <?php
                                foreach ($sf['options'] as $val => $txt) {
                                    if (isset($_POST[$sf['name']])) {
                                        $selected = ($_POST[$sf['name']] == $val) ? 'selected' : '';
                                    } else {
                                        $selected = ($store[$sf['name']] == $val) ? 'selected' : '';
                                    }
                                    echo "<option value=\"" . htmlspecialchars($val) . "\" $selected>" . htmlspecialchars($txt) . "</option>";
                                }
                                ?>
                            </select>
                        <?php elseif ($sf['type'] === 'textarea'): ?>
                            <textarea class="form-control form-control-sm" id="<?= $sf['name'] ?>" name="<?= $sf['name'] ?>" rows="<?= $sf['rows'] ?? 3 ?>"><?= htmlspecialchars($_POST[$sf['name']] ?? $store[$sf['name']] ?? '') ?></textarea>
                        <?php else: ?>
                            <input type="<?= $sf['type'] ?>" step="<?= $sf['step'] ?? '' ?>" class="form-control form-control-sm" id="<?= $sf['name'] ?>" name="<?= $sf['name'] ?>" value="<?= htmlspecialchars($_POST[$sf['name']] ?? $store[$sf['name']] ?? '') ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <hr>
            <h5>Weekly Schedule</h5>
            <?php
            foreach ($daysOfWeek as $d) {
                $sf = strtolower($d) . "_start";
                $ef = strtolower($d) . "_end";
                $savedS = $_POST[$sf] ?? $decoded['days'][$d]['start'] ?? '';
                $savedE = $_POST[$ef] ?? $decoded['days'][$d]['end'] ?? '';
            ?>
                <div class="row g-2 mb-2">
                    <div class="col-md-2 d-flex align-items-center fw-bold"><?= $d ?></div>
                    <div class="col-md-5">
                        <label class="form-label mb-0 small">Start Time</label>
                        <input type="time" class="form-control form-control-sm" name="<?= $sf ?>" value="<?= htmlspecialchars($savedS) ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label mb-0 small">End Time</label>
                        <input type="time" class="form-control form-control-sm" name="<?= $ef ?>" value="<?= htmlspecialchars($savedE) ?>">
                    </div>
                </div>
            <?php } ?>
            <hr>
            <h5>Holidays</h5>
            <p class="small text-muted">One holiday per line: <code>YYYY-MM-DD,Description</code></p>
            <?php
            $holTxt = "";
            if (!empty($decoded['holidays']) && is_array($decoded['holidays'])) {
                foreach ($decoded['holidays'] as $h) {
                    $holTxt .= $h['date'] . "," . ($h['desc'] ?? "Holiday") . "\n";
                }
            }
            ?>
            <textarea class="form-control form-control-sm" name="holidays" rows="4"><?= htmlspecialchars($_POST['holidays'] ?? $holTxt) ?></textarea>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save Changes</button>
                <a href="stores.php?action=list" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Cancel</a>
            </div>
        </form>
    </div>
<?php elseif ($action === 'view' && isset($store)): ?>
    <?php
    $decoded = @json_decode($store['work_schedule'] ?? '', true);
    if (!is_array($decoded)) $decoded = ["days" => [], "holidays" => []];
    ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-eye"></i> Store Details</h2>
        <ul class="nav nav-tabs" id="storeTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab">Details</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">Settings</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="calendar-tab" data-bs-toggle="tab" data-bs-target="#calendarTab" type="button" role="tab">Calendar</button>
            </li>
        </ul>
        <div class="tab-content border p-3" id="storeTabsContent">
            <div class="tab-pane fade show active" id="details" role="tabpanel">
                <div class="table-responsive">
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
                            <td>
                                <?php if (!empty($store['logo'])): ?>
                                    <img src="<?= htmlspecialchars($store['logo']) ?>" alt="Store Logo" style="max-height:80px;">
                                <?php else: ?>
                                    <span class="text-muted">No Logo</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Cart Logo</th>
                            <td>
                                <?php if (!empty($store['cart_logo'])): ?>
                                    <img src="<?= htmlspecialchars($store['cart_logo']) ?>" alt="Cart Logo" style="max-height:80px;">
                                <?php else: ?>
                                    <span class="text-muted">No Cart Logo</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Manager</th>
                            <td><?= htmlspecialchars($store['manager'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td><?= $store['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                        </tr>
                        <tr>
                            <th>Created At</th>
                            <td><?= htmlspecialchars($store['created_at']) ?></td>
                        </tr>
                        <tr>
                            <th>Updated At</th>
                            <td><?= htmlspecialchars($store['updated_at'] ?? 'N/A') ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="settings" role="tabpanel">
                <h5>General Settings</h5>
                <table class="table table-bordered table-sm">
                    <tr>
                        <th>Minimum Order (€)</th>
                        <td><?= htmlspecialchars($store['minimum_order']) ?></td>
                    </tr>
                    <tr>
                        <th>AGB</th>
                        <td><?= $store['agb'] ? htmlspecialchars($store['agb']) : '<em>Not set</em>' ?></td>
                    </tr>
                    <tr>
                        <th>Impressum</th>
                        <td><?= $store['impressum'] ? htmlspecialchars($store['impressum']) : '<em>Not set</em>' ?></td>
                    </tr>
                    <tr>
                        <th>Datenschutzerklärung</th>
                        <td><?= $store['datenschutzerklaerung'] ? htmlspecialchars($store['datenschutzerklaerung']) : '<em>Not set</em>' ?></td>
                    </tr>
                </table>
                <h5>Social Links</h5>
                <table class="table table-bordered table-sm">
                    <tr>
                        <th>Facebook</th>
                        <td><?= $store['facebook_link'] ? '<a href="' . htmlspecialchars($store['facebook_link']) . '" target="_blank">View</a>' : '<em>Not set</em>' ?></td>
                    </tr>
                    <tr>
                        <th>Twitter</th>
                        <td><?= $store['twitter_link'] ? '<a href="' . htmlspecialchars($store['twitter_link']) . '" target="_blank">View</a>' : '<em>Not set</em>' ?></td>
                    </tr>
                    <tr>
                        <th>Instagram</th>
                        <td><?= $store['instagram_link'] ? '<a href="' . htmlspecialchars($store['instagram_link']) . '" target="_blank">View</a>' : '<em>Not set</em>' ?></td>
                    </tr>
                    <tr>
                        <th>LinkedIn</th>
                        <td><?= $store['linkedin_link'] ? '<a href="' . htmlspecialchars($store['linkedin_link']) . '" target="_blank">View</a>' : '<em>Not set</em>' ?></td>
                    </tr>
                    <tr>
                        <th>YouTube</th>
                        <td><?= $store['youtube_link'] ? '<a href="' . htmlspecialchars($store['youtube_link']) . '" target="_blank">View</a>' : '<em>Not set</em>' ?></td>
                    </tr>
                </table>
                <h5>Cart Settings</h5>
                <table class="table table-bordered table-sm">
                    <tr>
                        <th>Cart Description</th>
                        <td><?= $store['cart_description'] ? htmlspecialchars($store['cart_description']) : '<em>Not set</em>' ?></td>
                    </tr>
                </table>
                <h5>Shipping Settings</h5>
                <table class="table table-bordered table-sm">
                    <tr>
                        <th>Shipping Calculation Mode</th>
                        <td><?= htmlspecialchars(ucfirst($store['shipping_calculation_mode'])) ?></td>
                    </tr>
                    <tr>
                        <th>Max Distance Radius (km)</th>
                        <td><?= htmlspecialchars($store['shipping_distance_radius']) ?></td>
                    </tr>
                    <tr>
                        <th>Base Shipping Fee (€)</th>
                        <td><?= htmlspecialchars($store['shipping_fee_base']) ?></td>
                    </tr>
                    <tr>
                        <th>Fee per Km (€)</th>
                        <td><?= htmlspecialchars($store['shipping_fee_per_km']) ?></td>
                    </tr>
                    <tr>
                        <th>Free Shipping Above (€)</th>
                        <td><?= htmlspecialchars($store['shipping_free_threshold']) ?></td>
                    </tr>
                    <tr>
                        <th>Google Maps API Key</th>
                        <td><?= $store['google_maps_api_key'] ? htmlspecialchars($store['google_maps_api_key']) : '<em>Not set</em>' ?></td>
                    </tr>
                    <tr>
                        <th>Postal Code Zones (JSON)</th>
                        <td><?= $store['postal_code_zones'] ? htmlspecialchars($store['postal_code_zones']) : '<em>Not set</em>' ?></td>
                    </tr>
                    <tr>
                        <th>Enable Google Distance Matrix?</th>
                        <td><?= $store['shipping_enable_google_distance_matrix'] ? 'Yes' : 'No' ?></td>
                    </tr>
                    <tr>
                        <th>Distance Matrix Region</th>
                        <td><?= $store['shipping_matrix_region'] ? htmlspecialchars($store['shipping_matrix_region']) : '<em>Not set</em>' ?></td>
                    </tr>
                    <tr>
                        <th>Distance Matrix Units</th>
                        <td><?= htmlspecialchars(ucfirst($store['shipping_matrix_units'])) ?></td>
                    </tr>
                    <tr>
                        <th>Weekend Surcharge (€)</th>
                        <td><?= htmlspecialchars($store['shipping_weekend_surcharge']) ?></td>
                    </tr>
                    <tr>
                        <th>Holiday Surcharge (€)</th>
                        <td><?= htmlspecialchars($store['shipping_holiday_surcharge']) ?></td>
                    </tr>
                    <tr>
                        <th>Handling Fee (€)</th>
                        <td><?= htmlspecialchars($store['shipping_handling_fee']) ?></td>
                    </tr>
                    <tr>
                        <th>VAT on Shipping (%)</th>
                        <td><?= htmlspecialchars($store['shipping_vat_percentage']) ?></td>
                    </tr>
                </table>
            </div>
            <div class="tab-pane fade" id="calendarTab" role="tabpanel">
                <div id="storeCalendar" style="width:100%;max-width:100%;margin:0 auto;"></div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            tinymce.init({
                selector: 'textarea.wysiwyg',
                height: 200,
                menubar: false,
                plugins: 'advlist autolink lists link charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table paste code help wordcount',
                toolbar: 'undo redo | bold italic underline | alignleft aligncenter alignright | bullist numlist | removeformat | help',
                branding: false
            });
            setTimeout(() => {
                const lat = parseFloat(document.getElementById('store_lat')?.value) || 41.3275;
                const lng = parseFloat(document.getElementById('store_lng')?.value) || 19.8189;
                const map = L.map('shippingMap').setView([lat, lng], 12);
                L.tileLayer('https://{s}.tile.osm.org/{z}/{x}/{y}.png', {
                    maxZoom: 19
                }).addTo(map);
                L.control.zoom({
                    position: 'bottomright'
                }).addTo(map);
                const marker = L.marker([lat, lng], {
                    draggable: true
                }).addTo(map);
                marker.on('dragend', e => {
                    const c = e.target.getLatLng();
                    document.getElementById('store_lat').value = c.lat.toFixed(6);
                    document.getElementById('store_lng').value = c.lng.toFixed(6);
                });
            }, 200);
            document.querySelectorAll('form').forEach(f => {
                f.addEventListener('submit', e => {
                    if (!f.checkValidity()) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    f.classList.add('was-validated');
                }, false);
            });
        });
    </script>
<?php elseif ($action === 'assign_admin' && isset($store)): ?>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-user-cog"></i> Assign Administrator</h2>
        <?php if ($message) echo $message; ?>
        <form method="POST" action="stores.php?action=assign_admin&id=<?= htmlspecialchars($store['id']) ?>" class="shadow p-4 bg-light rounded needs-validation" novalidate>
            <div class="mb-3">
                <label for="admin_id" class="form-label">Select an Admin</label>
                <select class="form-select form-select-sm" id="admin_id" name="admin_id" required>
                    <option value="">Select Administrator</option>
                    <?php if (!empty($admins)): foreach ($admins as $adm): ?>
                            <option value="<?= htmlspecialchars($adm['id']) ?>" <?= ($store['manager_id'] == $adm['id']) ? 'selected' : '' ?>><?= htmlspecialchars($adm['username']) ?></option>
                        <?php endforeach;
                    else: ?>
                        <option value="" disabled>No active admins available.</option>
                    <?php endif; ?>
                </select>
                <div class="invalid-feedback">Please select an administrator.</div>
            </div>
            <div class="border-top pt-3 mt-3">
                <h5>Create a New Admin</h5>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" value="1" id="create_new_admin" name="create_new_admin">
                    <label class="form-check-label" for="create_new_admin">Create new admin user</label>
                </div>
                <div class="mb-2">
                    <label for="new_admin_username" class="form-label">Username</label>
                    <input type="text" class="form-control form-control-sm" id="new_admin_username" name="new_admin_username" disabled>
                </div>
                <div class="mb-3">
                    <label for="new_admin_password" class="form-label">Password</label>
                    <input type="password" class="form-control form-control-sm" id="new_admin_password" name="new_admin_password" disabled>
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save</button>
                <a href="stores.php?action=list" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Cancel</a>
            </div>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chk = document.getElementById('create_new_admin');
            const u = document.getElementById('new_admin_username');
            const p = document.getElementById('new_admin_password');
            chk.addEventListener('change', function() {
                if (this.checked) {
                    u.disabled = false;
                    p.disabled = false;
                } else {
                    u.disabled = true;
                    p.disabled = true;
                }
            });
            document.querySelectorAll('form').forEach(f => {
                f.addEventListener('submit', e => {
                    if (chk.checked) {
                        if (!u.value.trim() || !p.value.trim()) {
                            e.preventDefault();
                            e.stopPropagation();
                            alert('Please provide both username and password for the new admin.');
                            return;
                        }
                    }
                    if (!f.checkValidity()) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    f.classList.add('was-validated');
                }, false);
            });
        });
    </script>
<?php endif; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.6/index.global.min.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-o9N1jzYkY6kZ6kZGP2MQH/PAVc4aNG4R1mR9VuAKT0U=" crossorigin="" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.6/index.global.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-o9N1jzYkY6kZ6kZGP2MQH/PAVc4aNG4R1mR9VuAKT0U=" crossorigin=""></script>
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.6/index.global.min.js"></script>
<script>
    $(function() {
        $('.form-select').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });
        $('#storesTable').DataTable({
            language: {
                emptyTable: "No stores found."
            }
        });
    });
</script>
<?php require_once 'includes/footer.php'; ?>