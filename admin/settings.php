<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';
session_status() === PHP_SESSION_NONE && session_start();
$message = '';
$allowed_keys = [
    'minimum_order',
    'agb',
    'impressum',
    'datenschutzerklaerung',
    'facebook_link',
    'twitter_link',
    'instagram_link',
    'linkedin_link',
    'youtube_link',
    'cart_logo',
    'cart_description',
    'store_lat',
    'store_lng',
    'shipping_calculation_mode',
    'shipping_distance_radius',
    'shipping_fee_base',
    'shipping_fee_per_km',
    'shipping_free_threshold',
    'google_maps_api_key',
    'postal_code_zones',
    'shipping_enable_google_distance_matrix',
    'shipping_matrix_region',
    'shipping_matrix_units',
    'shipping_weekend_surcharge',
    'shipping_holiday_surcharge',
    'shipping_vat_percentage',
    'shipping_handling_fee'
];
function getSettings($pdo, $keys)
{
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT `key`, `value` FROM `settings` WHERE `key` IN ($placeholders)");
    $stmt->execute($keys);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}
function updateSettings($pdo, $settings, $keys)
{
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE `settings` SET `value` = ? WHERE `key` = ?');
        foreach ($settings as $k => $v) {
            if (in_array($k, $keys)) {
                $check = $pdo->prepare('SELECT COUNT(*) FROM `settings` WHERE `key` = ?');
                $check->execute([$k]);
                if ($check->fetchColumn()) {
                    $update->execute([trim($v), $k]);
                } else {
                    $pdo->prepare('INSERT INTO `settings`(`key`, `value`) VALUES (?,?)')->execute([$k, trim($v)]);
                }
            }
        }
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        return $e->getMessage();
    }
}
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$current_settings = getSettings($pdo, $allowed_keys);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = '<div class="alert alert-danger">Invalid CSRF token.</div>';
    } else {
        $submitted_settings = [];
        foreach ($allowed_keys as $key) {
            if (isset($_POST[$key])) {
                $value = trim($_POST[$key]);
                switch ($key) {
                    case 'minimum_order':
                        if (!is_numeric($value) || $value < 0) {
                            $message = '<div class="alert alert-danger">Minimum Order must be a non-negative number.</div>';
                            break 2;
                        }
                        $value = number_format((float)$value, 2, '.', '');
                        break;
                    case 'facebook_link':
                    case 'twitter_link':
                    case 'instagram_link':
                    case 'linkedin_link':
                    case 'youtube_link':
                        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                            $message = '<div class="alert alert-danger">Invalid URL for ' . htmlspecialchars($key) . '</div>';
                            break 2;
                        }
                        break;
                    case 'cart_description':
                        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                        break;
                    case 'shipping_fee_base':
                    case 'shipping_fee_per_km':
                    case 'shipping_free_threshold':
                    case 'shipping_weekend_surcharge':
                    case 'shipping_holiday_surcharge':
                    case 'shipping_handling_fee':
                    case 'shipping_vat_percentage':
                        $value = is_numeric($value) ? $value : 0;
                        break;
                }
                $submitted_settings[$key] = $value;
            }
        }
        if (isset($_FILES['cart_logo']) && $_FILES['cart_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['cart_logo']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $mime_type = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $_FILES['cart_logo']['tmp_name']);
                if (!in_array($mime_type, $allowed_types)) {
                    $message = '<div class="alert alert-danger">Invalid file type for Cart Logo.</div>';
                } else {
                    $upload_dir = '../uploads/logos/';
                    is_dir($upload_dir) || mkdir($upload_dir, 0755, true);
                    $ext = pathinfo($_FILES['cart_logo']['name'], PATHINFO_EXTENSION);
                    $new_file = 'cart_logo_' . time() . '.' . $ext;
                    $dest = $upload_dir . $new_file;
                    if (move_uploaded_file($_FILES['cart_logo']['tmp_name'], $dest)) {
                        !empty($current_settings['cart_logo']) && file_exists($current_settings['cart_logo']) && unlink($current_settings['cart_logo']);
                        $submitted_settings['cart_logo'] = $dest;
                    } else {
                        $message = '<div class="alert alert-danger">Failed to upload Cart Logo.</div>';
                    }
                }
            } else {
                $message = '<div class="alert alert-danger">Error uploading Cart Logo.</div>';
            }
        }
        if (empty($message)) {
            $result = updateSettings($pdo, $submitted_settings, $allowed_keys);
            if ($result === true) {
                $message = '<div class="alert alert-success">Settings updated successfully.</div>';
                $current_settings = getSettings($pdo, $allowed_keys);
            } else {
                $message = '<div class="alert alert-danger">Error updating settings: ' . htmlspecialchars($result) . '</div>';
            }
        }
    }
    $current_settings = getSettings($pdo, $allowed_keys);
}
?>
<div class="container-fluid mt-4">
    <?= $message ?>
    <form method="POST" action="settings.php" enctype="multipart/form-data" novalidate>
        <ul class="nav nav-tabs" role="tablist">
            <?php
            $tabs = [
                'general' => ['icon' => 'fas fa-sliders-h', 'label' => 'General'],
                'social' => ['icon' => 'fas fa-share-alt', 'label' => 'Social'],
                'legal' => ['icon' => 'fas fa-balance-scale', 'label' => 'Legal'],
                'cart' => ['icon' => 'fas fa-shopping-cart', 'label' => 'Cart'],
                'shipping' => ['icon' => 'fas fa-truck', 'label' => 'Shipping']
            ];
            foreach ($tabs as $id => $tab) {
                echo '<li class="nav-item"><button class="nav-link' . ($id === 'general' ? ' active' : '') . '" data-bs-toggle="tab" data-bs-target="#' . $id . '" type="button" role="tab"><i class="' . $tab['icon'] . '"></i> ' . $tab['label'] . '</button></li>';
            }
            ?>
        </ul>
        <div class="tab-content p-4 border border-top-0">
            <div class="tab-pane fade show active" id="general" role="tabpanel">
                <div class="mb-3">
                    <label class="form-label">Minimum Order (€)</label>
                    <input type="number" step="0.01" class="form-control" name="minimum_order" required value="<?= htmlspecialchars($current_settings['minimum_order'] ?? '5.00') ?>">
                </div>
            </div>
            <div class="tab-pane fade" id="social" role="tabpanel">
                <div class="row">
                    <?php
                    $social_platforms = ['facebook_link' => 'Facebook', 'twitter_link' => 'Twitter', 'instagram_link' => 'Instagram', 'linkedin_link' => 'LinkedIn', 'youtube_link' => 'YouTube'];
                    foreach ($social_platforms as $key => $label) {
                        echo '<div class="col-md-6 mb-3"><label>' . $label . '</label><input type="url" class="form-control" name="' . $key . '" value="' . htmlspecialchars($current_settings[$key] ?? '') . '"></div>';
                    }
                    ?>
                </div>
            </div>
            <div class="tab-pane fade" id="legal" role="tabpanel">
                <?php
                $legal_fields = [
                    'agb' => 'AGB',
                    'impressum' => 'Impressum',
                    'datenschutzerklaerung' => 'Datenschutzerklärung'
                ];
                foreach ($legal_fields as $key => $label) {
                    echo '<div class="mb-3"><label class="form-label">' . $label . '</label><textarea class="form-control wysiwyg" name="' . $key . '" rows="' . ($key === 'impressum' ? '4' : '6') . '">' . htmlspecialchars($current_settings[$key] ?? '') . '</textarea></div>';
                }
                ?>
            </div>
            <div class="tab-pane fade" id="cart" role="tabpanel">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Cart Logo</label>
                        <input type="file" class="form-control" name="cart_logo" accept="image/*">
                        <?= !empty($current_settings['cart_logo']) && file_exists($current_settings['cart_logo']) ? '<img src="' . htmlspecialchars($current_settings['cart_logo']) . '" alt="Cart Logo" class="img-thumbnail mt-2" style="max-width:200px;">' : '' ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Cart Description</label>
                        <textarea class="form-control wysiwyg" rows="4" name="cart_description"><?= htmlspecialchars($current_settings['cart_description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="shipping" role="tabpanel">
                <h5 class="mb-3">Advanced Shipping Options</h5>
                <div class="row">
                    <?php
                    $shipping_fields = [
                        ['type' => 'select', 'label' => 'Shipping Calculation Mode', 'name' => 'shipping_calculation_mode', 'options' => ['radius' => 'Radius (km)', 'postal' => 'Postal Code', 'both' => 'Both'], 'value' => $current_settings['shipping_calculation_mode'] ?? '', 'text' => 'Use “radius” for store-based distance, “postal” for zones, or “both”.'],
                        ['type' => 'number', 'label' => 'Max Distance Radius (km)', 'name' => 'shipping_distance_radius', 'value' => $current_settings['shipping_distance_radius'] ?? '10'],
                        ['type' => 'number', 'label' => 'Base Shipping Fee (€)', 'name' => 'shipping_fee_base', 'step' => '0.01', 'value' => $current_settings['shipping_fee_base'] ?? '0.00'],
                        ['type' => 'number', 'label' => 'Fee per Km (€)', 'name' => 'shipping_fee_per_km', 'step' => '0.01', 'value' => $current_settings['shipping_fee_per_km'] ?? '0.50'],
                        ['type' => 'number', 'label' => 'Free Shipping Above (€)', 'name' => 'shipping_free_threshold', 'step' => '0.01', 'value' => $current_settings['shipping_free_threshold'] ?? '50.00'],
                        ['type' => 'text', 'label' => 'Store Latitude', 'name' => 'store_lat', 'id' => 'store_lat', 'value' => $current_settings['store_lat'] ?? '41.3275'],
                        ['type' => 'text', 'label' => 'Store Longitude', 'name' => 'store_lng', 'id' => 'store_lng', 'value' => $current_settings['store_lng'] ?? '19.8189'],
                        ['type' => 'select', 'label' => 'Enable Google Distance Matrix?', 'name' => 'shipping_enable_google_distance_matrix', 'options' => ['0' => 'No', '1' => 'Yes'], 'value' => $current_settings['shipping_enable_google_distance_matrix'] ?? '0'],
                        ['type' => 'text', 'label' => 'Google Maps API Key', 'name' => 'google_maps_api_key', 'value' => $current_settings['google_maps_api_key'] ?? ''],
                        ['type' => 'text', 'label' => 'Distance Matrix Region (e.g. DE, US)', 'name' => 'shipping_matrix_region', 'value' => $current_settings['shipping_matrix_region'] ?? ''],
                        ['type' => 'select', 'label' => 'Distance Matrix Units', 'name' => 'shipping_matrix_units', 'options' => ['metric' => 'Metric (km)', 'imperial' => 'Imperial (miles)'], 'value' => $current_settings['shipping_matrix_units'] ?? 'metric'],
                        ['type' => 'number', 'label' => 'Weekend Surcharge (€)', 'name' => 'shipping_weekend_surcharge', 'step' => '0.01', 'value' => $current_settings['shipping_weekend_surcharge'] ?? '0.00', 'text' => 'Extra charge for weekend deliveries.'],
                        ['type' => 'number', 'label' => 'Holiday Surcharge (€)', 'name' => 'shipping_holiday_surcharge', 'step' => '0.01', 'value' => $current_settings['shipping_holiday_surcharge'] ?? '0.00', 'text' => 'Extra charge on public holidays.'],
                        ['type' => 'number', 'label' => 'Handling Fee (€)', 'name' => 'shipping_handling_fee', 'step' => '0.01', 'value' => $current_settings['shipping_handling_fee'] ?? '0.00', 'text' => 'For packaging, handling, etc.'],
                        ['type' => 'number', 'label' => 'VAT on Shipping (%)', 'name' => 'shipping_vat_percentage', 'step' => '0.01', 'value' => $current_settings['shipping_vat_percentage'] ?? '20.00', 'text' => 'Add VAT on shipping cost. Use 0 for none.'],
                        ['type' => 'textarea', 'label' => 'Postal Code Zones (JSON)', 'name' => 'postal_code_zones', 'rows' => 3, 'placeholder' => '{"1000":5,"1001":7,"1010":0}', 'value' => $current_settings['postal_code_zones'] ?? '', 'style' => 'font-family:monospace'],
                        ['type' => 'map', 'label' => 'Store Location', 'id' => 'shippingMap', 'style' => 'height:400px']
                    ];
                    foreach ($shipping_fields as $field) {
                        switch ($field['type']) {
                            case 'select':
                                echo '<div class="col-md-6 mb-3"><label>' . $field['label'] . '</label><select class="form-select" name="' . $field['name'] . '">';
                                foreach ($field['options'] as $val => $text) {
                                    echo '<option value="' . $val . '"' . (($field['value'] === $val) ? ' selected' : '') . '>' . $text . '</option>';
                                }
                                echo '</select>' . (!empty($field['text']) ? '<div class="form-text">' . $field['text'] . '</div>' : '') . '</div>';
                                break;
                            case 'number':
                            case 'text':
                                echo '<div class="col-md-6 mb-3"><label>' . $field['label'] . '</label><input type="' . $field['type'] . '"' .
                                    (isset($field['step']) ? ' step="' . $field['step'] . '"' : '') .
                                    ' class="form-control" name="' . $field['name'] . '"' .
                                    (isset($field['id']) ? ' id="' . $field['id'] . '"' : '') .
                                    ' value="' . htmlspecialchars($field['value']) . '">';
                                echo !empty($field['text']) ? '<div class="form-text">' . $field['text'] . '</div>' : '';
                                echo '</div>';
                                break;
                            case 'textarea':
                                echo '<div class="col-12 mb-3"><label>' . $field['label'] . '</label><textarea class="form-control"' .
                                    (isset($field['rows']) ? ' rows="' . $field['rows'] . '"' : '') .
                                    (isset($field['placeholder']) ? ' placeholder="' . $field['placeholder'] . '"' : '') .
                                    (isset($field['style']) ? ' style="' . $field['style'] . '"' : '') .
                                    ' name="' . $field['name'] . '">' . htmlspecialchars($field['value']) . '</textarea></div>';
                                break;
                            case 'map':
                                echo '<div class="col-12 mb-3"><label class="form-label d-block">' . $field['label'] . '</label><div id="' . $field['id'] . '" style="' . $field['style'] . '"></div></div>';
                                break;
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="mt-3 d-flex justify-content-end">
            <button type="submit" class="btn btn-success me-2"><i class="fas fa-save"></i> Save</button>
            <button type="reset" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</button>
        </div>
    </form>
</div>
<!-- TinyMCE -->
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/7.5.1/tinymce.min.js" integrity="sha512-8+JNyduy8cg+AUuQiuxKD2W7277rkqjlmEE/Po60jKpCXzc+EYwyVB8o3CnlTGf98+ElVPaOBWyme/8jJqseMA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<!-- Leaflet CSS / JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script defer src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script defer>
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
            const lat = parseFloat(document.getElementById('store_lat').value) || 41.3275;
            const lng = parseFloat(document.getElementById('store_lng').value) || 19.8189;
            const map = L.map('shippingMap', {
                zoomControl: false
            }).setView([lat, lng], 12);
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
                const coords = e.target.getLatLng();
                document.getElementById('store_lat').value = coords.lat.toFixed(6);
                document.getElementById('store_lng').value = coords.lng.toFixed(6);
            });
        }, 200);
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', e => {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    });
</script>
<?php require_once 'includes/footer.php'; ?>