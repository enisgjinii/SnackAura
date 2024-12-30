<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$message = '';
// All allowed setting keys
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
    // Store & Shipping
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
    // Additional advanced shipping
    'shipping_weekend_surcharge',
    'shipping_holiday_surcharge',
    'shipping_vat_percentage',
    'shipping_handling_fee'
];
// Fetch settings
function getSettings($pdo, $allowed_keys)
{
    $in_placeholders = rtrim(str_repeat('?,', count($allowed_keys)), ',');
    $stmt = $pdo->prepare("SELECT `key`, `value` FROM `settings` WHERE `key` IN ($in_placeholders)");
    $stmt->execute($allowed_keys);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}
// Update settings
function updateSettings($pdo, $settings, $allowed_keys)
{
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE `settings` SET `value` = ? WHERE `key` = ?');
        foreach ($settings as $key => $value) {
            if (!in_array($key, $allowed_keys)) continue;
            $check = $pdo->prepare('SELECT COUNT(*) FROM `settings` WHERE `key` = ?');
            $check->execute([$key]);
            if ($check->fetchColumn()) {
                $update->execute([trim($value), $key]);
            } else {
                $insert = $pdo->prepare('INSERT INTO `settings`(`key`, `value`) VALUES (?,?)');
                $insert->execute([$key, trim($value)]);
            }
        }
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        return $e->getMessage();
    }
}
// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Fetch current settings
$current_settings = getSettings($pdo, $allowed_keys);
// Handle post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = '<div class="alert alert-danger">Invalid CSRF token.</div>';
    } else {
        $submitted_settings = [];
        foreach ($allowed_keys as $key) {
            if (isset($_POST[$key])) {
                $value = trim($_POST[$key]);
                // Validation examples
                if ($key === 'minimum_order') {
                    if (!is_numeric($value) || $value < 0) {
                        $message = '<div class="alert alert-danger">Minimum Order must be a non-negative number.</div>';
                        break;
                    }
                    $value = number_format((float)$value, 2, '.', '');
                }
                if (in_array($key, ['facebook_link', 'twitter_link', 'instagram_link', 'linkedin_link', 'youtube_link'])) {
                    if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                        $message = '<div class="alert alert-danger">Invalid URL for ' . htmlspecialchars($key) . '</div>';
                        break;
                    }
                }
                if ($key === 'cart_description') {
                    $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }
                // Additional advanced shipping numeric validations
                if (in_array($key, [
                    'shipping_fee_base',
                    'shipping_fee_per_km',
                    'shipping_free_threshold',
                    'shipping_weekend_surcharge',
                    'shipping_holiday_surcharge',
                    'shipping_handling_fee',
                    'shipping_vat_percentage'
                ])) {
                    if (!is_numeric($value)) $value = 0; // fallback
                }
                $submitted_settings[$key] = $value;
            }
        }
        // Handle logo upload
        if (isset($_FILES['cart_logo']) && $_FILES['cart_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['cart_logo']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $_FILES['cart_logo']['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime_type, $allowed_types)) {
                    $message = '<div class="alert alert-danger">Invalid file type for Cart Logo.</div>';
                } else {
                    $upload_dir = '../uploads/logos/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $ext = pathinfo($_FILES['cart_logo']['name'], PATHINFO_EXTENSION);
                    $new_file = 'cart_logo_' . time() . '.' . $ext;
                    $dest = $upload_dir . $new_file;
                    if (move_uploaded_file($_FILES['cart_logo']['tmp_name'], $dest)) {
                        if (!empty($current_settings['cart_logo']) && file_exists($current_settings['cart_logo'])) {
                            unlink($current_settings['cart_logo']);
                        }
                        $submitted_settings['cart_logo'] = $dest;
                    } else {
                        $message = '<div class="alert alert-danger">Failed to upload Cart Logo.</div>';
                    }
                }
            } else {
                $message = '<div class="alert alert-danger">Error uploading Cart Logo.</div>';
            }
        }
        // If no validation errors yet
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
}
$current_settings = getSettings($pdo, $allowed_keys);
?>
<div class="container-fluid mt-4">
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <form method="POST" action="settings.php" enctype="multipart/form-data" novalidate>
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                    <i class="fas fa-sliders-h"></i> General
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#social" type="button" role="tab">
                    <i class="fas fa-share-alt"></i> Social
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#legal" type="button" role="tab">
                    <i class="fas fa-balance-scale"></i> Legal
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#cart" type="button" role="tab">
                    <i class="fas fa-shopping-cart"></i> Cart
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#shipping" type="button" role="tab">
                    <i class="fas fa-truck"></i> Shipping
                </button>
            </li>
        </ul>
        <div class="tab-content p-4 border border-top-0">
            <!-- GENERAL -->
            <div class="tab-pane fade show active" id="general" role="tabpanel">
                <div class="mb-3">
                    <label class="form-label">Minimum Order (€)</label>
                    <input type="number" step="0.01" class="form-control" name="minimum_order" required
                        value="<?= htmlspecialchars($current_settings['minimum_order'] ?? '5.00') ?>">
                </div>
            </div>
            <!-- SOCIAL -->
            <div class="tab-pane fade" id="social" role="tabpanel">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Facebook</label>
                        <input type="url" class="form-control" name="facebook_link"
                            value="<?= htmlspecialchars($current_settings['facebook_link'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Twitter</label>
                        <input type="url" class="form-control" name="twitter_link"
                            value="<?= htmlspecialchars($current_settings['twitter_link'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Instagram</label>
                        <input type="url" class="form-control" name="instagram_link"
                            value="<?= htmlspecialchars($current_settings['instagram_link'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>LinkedIn</label>
                        <input type="url" class="form-control" name="linkedin_link"
                            value="<?= htmlspecialchars($current_settings['linkedin_link'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>YouTube</label>
                        <input type="url" class="form-control" name="youtube_link"
                            value="<?= htmlspecialchars($current_settings['youtube_link'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <!-- LEGAL -->
            <div class="tab-pane fade" id="legal" role="tabpanel">
                <div class="mb-3">
                    <label class="form-label">AGB</label>
                    <textarea class="form-control wysiwyg" name="agb" rows="6"><?= htmlspecialchars($current_settings['agb'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Impressum</label>
                    <textarea class="form-control wysiwyg" name="impressum" rows="4"><?= htmlspecialchars($current_settings['impressum'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Datenschutzerklärung</label>
                    <textarea class="form-control wysiwyg" name="datenschutzerklaerung" rows="6"><?= htmlspecialchars($current_settings['datenschutzerklaerung'] ?? '') ?></textarea>
                </div>
            </div>
            <!-- CART -->
            <div class="tab-pane fade" id="cart" role="tabpanel">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Cart Logo</label>
                        <input type="file" class="form-control" name="cart_logo" accept="image/*">
                        <?php if (!empty($current_settings['cart_logo']) && file_exists($current_settings['cart_logo'])): ?>
                            <img src="<?= htmlspecialchars($current_settings['cart_logo']) ?>" alt="Cart Logo"
                                class="img-thumbnail mt-2" style="max-width:200px;">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Cart Description</label>
                        <textarea class="form-control wysiwyg" rows="4" name="cart_description"><?= htmlspecialchars($current_settings['cart_description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <!-- SHIPPING -->
            <div class="tab-pane fade" id="shipping" role="tabpanel">
                <h5 class="mb-3">Advanced Shipping Options</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Shipping Calculation Mode</label>
                        <select class="form-select" name="shipping_calculation_mode">
                            <option value="radius" <?= ($current_settings['shipping_calculation_mode'] ?? '') === 'radius' ? 'selected' : '' ?>>Radius (km)</option>
                            <option value="postal" <?= ($current_settings['shipping_calculation_mode'] ?? '') === 'postal' ? 'selected' : '' ?>>Postal Code</option>
                            <option value="both" <?= ($current_settings['shipping_calculation_mode'] ?? '') === 'both' ? 'selected' : '' ?>>Both</option>
                        </select>
                        <div class="form-text">Use “radius” for store-based distance, “postal” for zones, or “both”.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Max Distance Radius (km)</label>
                        <input type="number" class="form-control" name="shipping_distance_radius"
                            value="<?= htmlspecialchars($current_settings['shipping_distance_radius'] ?? '10') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Base Shipping Fee (€)</label>
                        <input type="number" step="0.01" class="form-control" name="shipping_fee_base"
                            value="<?= htmlspecialchars($current_settings['shipping_fee_base'] ?? '0.00') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Fee per Km (€)</label>
                        <input type="number" step="0.01" class="form-control" name="shipping_fee_per_km"
                            value="<?= htmlspecialchars($current_settings['shipping_fee_per_km'] ?? '0.50') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Free Shipping Above (€)</label>
                        <input type="number" step="0.01" class="form-control" name="shipping_free_threshold"
                            value="<?= htmlspecialchars($current_settings['shipping_free_threshold'] ?? '50.00') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Store Latitude</label>
                        <input type="text" class="form-control" id="store_lat" name="store_lat"
                            value="<?= htmlspecialchars($current_settings['store_lat'] ?? '41.3275') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Store Longitude</label>
                        <input type="text" class="form-control" id="store_lng" name="store_lng"
                            value="<?= htmlspecialchars($current_settings['store_lng'] ?? '19.8189') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Enable Google Distance Matrix?</label>
                        <select class="form-select" name="shipping_enable_google_distance_matrix">
                            <option value="0" <?= ($current_settings['shipping_enable_google_distance_matrix'] ?? '') == '0' ? 'selected' : '' ?>>No</option>
                            <option value="1" <?= ($current_settings['shipping_enable_google_distance_matrix'] ?? '') == '1' ? 'selected' : '' ?>>Yes</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Google Maps API Key</label>
                        <input type="text" class="form-control" name="google_maps_api_key"
                            value="<?= htmlspecialchars($current_settings['google_maps_api_key'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Distance Matrix Region (e.g. DE, US)</label>
                        <input type="text" class="form-control" name="shipping_matrix_region"
                            value="<?= htmlspecialchars($current_settings['shipping_matrix_region'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Distance Matrix Units</label>
                        <select class="form-select" name="shipping_matrix_units">
                            <option value="metric" <?= ($current_settings['shipping_matrix_units'] ?? '') === 'metric' ? 'selected' : '' ?>>Metric (km)</option>
                            <option value="imperial" <?= ($current_settings['shipping_matrix_units'] ?? '') === 'imperial' ? 'selected' : '' ?>>Imperial (miles)</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Weekend Surcharge (€)</label>
                        <input type="number" step="0.01" class="form-control" name="shipping_weekend_surcharge"
                            value="<?= htmlspecialchars($current_settings['shipping_weekend_surcharge'] ?? '0.00') ?>">
                        <div class="form-text">Extra charge for weekend deliveries.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Holiday Surcharge (€)</label>
                        <input type="number" step="0.01" class="form-control" name="shipping_holiday_surcharge"
                            value="<?= htmlspecialchars($current_settings['shipping_holiday_surcharge'] ?? '0.00') ?>">
                        <div class="form-text">Extra charge on public holidays.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Handling Fee (€)</label>
                        <input type="number" step="0.01" class="form-control" name="shipping_handling_fee"
                            value="<?= htmlspecialchars($current_settings['shipping_handling_fee'] ?? '0.00') ?>">
                        <div class="form-text">For packaging, handling, etc.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>VAT on Shipping (%)</label>
                        <input type="number" step="0.01" class="form-control" name="shipping_vat_percentage"
                            value="<?= htmlspecialchars($current_settings['shipping_vat_percentage'] ?? '20.00') ?>">
                        <div class="form-text">Add VAT on shipping cost. Use 0 for none.</div>
                    </div>
                    <div class="col-12 mb-3">
                        <label>Postal Code Zones (JSON)</label>
                        <textarea class="form-control" name="postal_code_zones" rows="3" style="font-family:monospace" placeholder='{"1000":5,"1001":7,"1010":0}'><?= htmlspecialchars($current_settings['postal_code_zones'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label d-block">Store Location</label>
                        <div style="height:400px" id="shippingMap"></div>
                    </div>
                </div>
            </div>
        </div>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="mt-3 d-flex justify-content-end">
            <button type="submit" class="btn btn-success me-2">
                <i class="fas fa-save"></i> Save
            </button>
            <button type="reset" class="btn btn-secondary">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>
<!-- TinyMCE -->
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/7.5.1/tinymce.min.js"
    integrity="sha512-8+JNyduy8cg+AUuQiuxKD2W7277rkqjlmEE/Po60jKpCXzc+EYwyVB8o3CnlTGf98+ElVPaOBWyme/8jJqseMA=="
    crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<!-- Leaflet CSS / JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script defer src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script defer>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize TinyMCE
        tinymce.init({
            selector: 'textarea.wysiwyg',
            height: 200,
            menubar: false,
            plugins: [
                'advlist autolink lists link charmap preview anchor',
                'searchreplace visualblocks code fullscreen',
                'insertdatetime media table paste code help wordcount'
            ],
            toolbar: 'undo redo | bold italic underline | alignleft aligncenter alignright | bullist numlist | removeformat | help',
            branding: false
        });
        // Initialize Leaflet after short timeout, to improve user perception
        setTimeout(function() {
            let lat = parseFloat(document.getElementById('store_lat').value) || 41.3275;
            let lng = parseFloat(document.getElementById('store_lng').value) || 19.8189;
            let map = L.map('shippingMap', {
                zoomControl: false
            }).setView([lat, lng], 12);
            // Use a relatively fast tile server
            L.tileLayer('https://{s}.tile.osm.org/{z}/{x}/{y}.png', {
                maxZoom: 19
            }).addTo(map);
            L.control.zoom({
                position: 'bottomright'
            }).addTo(map);
            let marker = L.marker([lat, lng], {
                draggable: true
            }).addTo(map);
            marker.on('dragend', function(e) {
                let coords = e.target.getLatLng();
                document.getElementById('store_lat').value = coords.lat.toFixed(6);
                document.getElementById('store_lng').value = coords.lng.toFixed(6);
            });
        }, 200);
        // Basic validation
        const forms = document.querySelectorAll('form');
        Array.prototype.slice.call(forms).forEach(function(form) {
            form.addEventListener('submit', function(e) {
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