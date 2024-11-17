<?php
// admin/settings.php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Initialize message variable
$message = '';

// Define allowed setting keys to prevent unauthorized updates
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
    'cart_logo',           // New Key
    'cart_description'     // New Key
];

/**
 * Fetch settings as key-value pairs.
 */
function getSettings($pdo, $allowed_keys)
{
    // Prepare placeholders for the IN clause
    $placeholders = rtrim(str_repeat('?,', count($allowed_keys)), ',');
    $stmt = $pdo->prepare("SELECT `key`, `value` FROM `settings` WHERE `key` IN ($placeholders)");
    $stmt->execute($allowed_keys);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

/**
 * Update settings in the database.
 */
function updateSettings($pdo, $settings, $allowed_keys)
{
    // Start a transaction for atomicity
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE `settings` SET `value` = ? WHERE `key` = ?');
        foreach ($settings as $key => $value) {
            if (in_array($key, $allowed_keys)) {
                // Check if the key exists
                $check_stmt = $pdo->prepare('SELECT COUNT(*) FROM `settings` WHERE `key` = ?');
                $check_stmt->execute([$key]);
                $exists = $check_stmt->fetchColumn();
                if ($exists) {
                    // Update existing key
                    $stmt->execute([trim($value), $key]);
                } else {
                    // Insert new key
                    $insert_stmt = $pdo->prepare('INSERT INTO `settings` (`key`, `value`) VALUES (?, ?)');
                    $insert_stmt->execute([$key, trim($value)]);
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

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch current settings
$current_settings = getSettings($pdo, $allowed_keys);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = '<div class="alert alert-danger">Invalid CSRF token.</div>';
    } else {
        // Collect and sanitize POST data
        $submitted_settings = [];
        foreach ($allowed_keys as $key) {
            if (isset($_POST[$key])) {
                // Trim whitespace
                $value = trim($_POST[$key]);
                // Additional validation based on key
                if ($key === 'minimum_order') {
                    // Validate minimum_order as a positive decimal number
                    if (!is_numeric($value) || $value < 0) {
                        $message = '<div class="alert alert-danger">Minimum Order must be a positive number.</div>';
                        break;
                    }
                    // Format to two decimal places
                    $value = number_format((float)$value, 2, '.', '');
                }
                // Validate URLs for social media links
                if (in_array($key, ['facebook_link', 'twitter_link', 'instagram_link', 'linkedin_link', 'youtube_link'])) {
                    if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                        $message = '<div class="alert alert-danger">Invalid URL format for ' . htmlspecialchars(ucfirst(str_replace('_', ' ', $key))) . '.</div>';
                        break;
                    }
                }
                // Handle Cart Description
                if ($key === 'cart_description') {
                    // Optional: Sanitize or limit the description length
                    $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }
                $submitted_settings[$key] = $value;
            }
        }

        // Handle File Upload for Cart Logo
        if (isset($_FILES['cart_logo']) && $_FILES['cart_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['cart_logo']['error'] === UPLOAD_ERR_OK) {
                $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $_FILES['cart_logo']['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mime_type, $allowed_mime_types)) {
                    $message = '<div class="alert alert-danger">Invalid file type for Cart Logo. Allowed types: JPEG, PNG, GIF, WEBP.</div>';
                } else {
                    // Define the upload directory
                    $upload_dir = '../uploads/logos/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    // Generate a unique filename
                    $file_extension = pathinfo($_FILES['cart_logo']['name'], PATHINFO_EXTENSION);
                    $new_filename = 'cart_logo_' . time() . '.' . $file_extension;
                    $destination = $upload_dir . $new_filename;

                    // Move the uploaded file
                    if (move_uploaded_file($_FILES['cart_logo']['tmp_name'], $destination)) {
                        // Optionally, delete the old logo if it exists
                        if (!empty($current_settings['cart_logo']) && file_exists($current_settings['cart_logo'])) {
                            unlink($current_settings['cart_logo']);
                        }
                        // Assign the new logo path to the settings
                        $submitted_settings['cart_logo'] = $destination;
                    } else {
                        $message = '<div class="alert alert-danger">Failed to upload Cart Logo. Please try again.</div>';
                    }
                }
            } else {
                $message = '<div class="alert alert-danger">Error uploading Cart Logo. Please try again.</div>';
            }
        }

        if (empty($message)) {
            // Update settings
            $result = updateSettings($pdo, $submitted_settings, $allowed_keys);
            if ($result === true) {
                $message = '<div class="alert alert-success">Settings updated successfully.</div>';
                // Refresh current settings
                $current_settings = getSettings($pdo, $allowed_keys);
            } else {
                $message = '<div class="alert alert-danger">Error updating settings: ' . htmlspecialchars($result) . '</div>';
            }
        }
    }
}

// Fetch current settings again after possible updates
$current_settings = getSettings($pdo, $allowed_keys);
?>

<div class="container-fluid mt-4">
    <h2 class="mb-4"><i class="fas fa-cogs"></i> Settings</h2>
    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>
    <!-- Tabbed Interface -->
    <form method="POST" action="settings.php" enctype="multipart/form-data">
        <ul class="nav nav-tabs" id="settingsTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
                    <i class="fas fa-sliders-h"></i> General
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="social-media-tab" data-bs-toggle="tab" data-bs-target="#social-media" type="button" role="tab" aria-controls="social-media" aria-selected="false">
                    <i class="fas fa-share-alt"></i> Social Media
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="legal-tab" data-bs-toggle="tab" data-bs-target="#legal" type="button" role="tab" aria-controls="legal" aria-selected="false">
                    <i class="fas fa-balance-scale"></i> Legal
                </button>
            </li>
            <!-- New Cart Settings Tab -->
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="cart-settings-tab" data-bs-toggle="tab" data-bs-target="#cart-settings" type="button" role="tab" aria-controls="cart-settings" aria-selected="false">
                    <i class="fas fa-shopping-cart"></i> Cart Settings
                </button>
            </li>
        </ul>
        <div class="tab-content" id="settingsTabContent">
            <!-- General Settings Tab -->
            <div class="tab-pane fade show active p-4" id="general" role="tabpanel" aria-labelledby="general-tab">
                <!-- Minimum Order Price -->
                <div class="mb-4">
                    <label for="minimum_order" class="form-label">
                        <i class="fas fa-euro-sign"></i> <strong>Minimum Order (€)</strong>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-euro-sign"></i></span>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            class="form-control"
                            id="minimum_order"
                            name="minimum_order"
                            required
                            value="<?= htmlspecialchars($current_settings['minimum_order'] ?? '5.00') ?>">
                        <div class="invalid-feedback">
                            Please enter a valid positive number.
                        </div>
                    </div>
                    <div class="form-text">Set the minimum order price required for customers to place an order.</div>
                </div>
            </div>

            <!-- Social Media Settings Tab -->
            <div class="tab-pane fade p-4" id="social-media" role="tabpanel" aria-labelledby="social-media-tab">
                <h5 class="mb-4"><i class="fas fa-share-alt"></i> Social Media Links</h5>
                <!-- Facebook Link -->
                <div class="mb-3">
                    <label for="facebook_link" class="form-label">
                        <i class="fab fa-facebook-f me-2"></i> Facebook URL
                    </label>
                    <input
                        type="url"
                        class="form-control"
                        id="facebook_link"
                        name="facebook_link"
                        placeholder="https://facebook.com/yourpage"
                        value="<?= htmlspecialchars($current_settings['facebook_link'] ?? '') ?>">
                    <div class="form-text">Enter your Facebook page URL.</div>
                </div>
                <!-- Twitter Link -->
                <div class="mb-3">
                    <label for="twitter_link" class="form-label">
                        <i class="fab fa-twitter me-2"></i> Twitter URL
                    </label>
                    <input
                        type="url"
                        class="form-control"
                        id="twitter_link"
                        name="twitter_link"
                        placeholder="https://twitter.com/yourprofile"
                        value="<?= htmlspecialchars($current_settings['twitter_link'] ?? '') ?>">
                    <div class="form-text">Enter your Twitter profile URL.</div>
                </div>
                <!-- Instagram Link -->
                <div class="mb-3">
                    <label for="instagram_link" class="form-label">
                        <i class="fab fa-instagram me-2"></i> Instagram URL
                    </label>
                    <input
                        type="url"
                        class="form-control"
                        id="instagram_link"
                        name="instagram_link"
                        placeholder="https://instagram.com/yourprofile"
                        value="<?= htmlspecialchars($current_settings['instagram_link'] ?? '') ?>">
                    <div class="form-text">Enter your Instagram profile URL.</div>
                </div>
                <!-- LinkedIn Link -->
                <div class="mb-3">
                    <label for="linkedin_link" class="form-label">
                        <i class="fab fa-linkedin-in me-2"></i> LinkedIn URL
                    </label>
                    <input
                        type="url"
                        class="form-control"
                        id="linkedin_link"
                        name="linkedin_link"
                        placeholder="https://linkedin.com/in/yourprofile"
                        value="<?= htmlspecialchars($current_settings['linkedin_link'] ?? '') ?>">
                    <div class="form-text">Enter your LinkedIn profile URL.</div>
                </div>
                <!-- YouTube Link -->
                <div class="mb-3">
                    <label for="youtube_link" class="form-label">
                        <i class="fab fa-youtube me-2"></i> YouTube URL
                    </label>
                    <input
                        type="url"
                        class="form-control"
                        id="youtube_link"
                        name="youtube_link"
                        placeholder="https://youtube.com/yourchannel"
                        value="<?= htmlspecialchars($current_settings['youtube_link'] ?? '') ?>">
                    <div class="form-text">Enter your YouTube channel URL.</div>
                </div>
            </div>

            <!-- Legal Settings Tab -->
            <div class="tab-pane fade p-4" id="legal" role="tabpanel" aria-labelledby="legal-tab">
                <!-- AGB (Terms and Conditions) -->
                <div class="mb-4">
                    <label for="agb" class="form-label">
                        <i class="fas fa-file-contract me-2"></i> <strong>AGB (Terms and Conditions)</strong>
                    </label>
                    <textarea
                        class="form-control wysiwyg"
                        id="agb"
                        name="agb"
                        rows="10"
                        required><?= htmlspecialchars($current_settings['agb'] ?? '') ?></textarea>
                    <div class="form-text">Enter the Terms and Conditions for your website.</div>
                </div>

                <!-- Impressum (Imprint) -->
                <div class="mb-4">
                    <label for="impressum" class="form-label">
                        <i class="fas fa-building me-2"></i> <strong>Impressum (Imprint)</strong>
                    </label>
                    <textarea
                        class="form-control wysiwyg"
                        id="impressum"
                        name="impressum"
                        rows="5"
                        required><?= htmlspecialchars($current_settings['impressum'] ?? '') ?></textarea>
                    <div class="form-text">Enter the Imprint information required by law.</div>
                </div>

                <!-- Datenschutzerklärung (Privacy Policy) -->
                <div class="mb-4">
                    <label for="datenschutzerklaerung" class="form-label">
                        <i class="fas fa-user-shield me-2"></i> <strong>Datenschutzerklärung (Privacy Policy)</strong>
                    </label>
                    <textarea
                        class="form-control wysiwyg"
                        id="datenschutzerklaerung"
                        name="datenschutzerklaerung"
                        rows="10"
                        required><?= htmlspecialchars($current_settings['datenschutzerklaerung'] ?? '') ?></textarea>
                    <div class="form-text">Enter the Privacy Policy for your website.</div>
                </div>
            </div>

            <!-- Cart Settings Tab -->
            <div class="tab-pane fade p-4" id="cart-settings" role="tabpanel" aria-labelledby="cart-settings-tab">
                <h5 class="mb-4"><i class="fas fa-shopping-cart me-2"></i> Cart Settings</h5>

                <!-- Cart Logo Upload -->
                <div class="mb-4">
                    <label for="cart_logo" class="form-label">
                        <i class="fas fa-image me-2"></i> <strong>Cart Logo</strong>
                    </label>
                    <input
                        class="form-control"
                        type="file"
                        id="cart_logo"
                        name="cart_logo"
                        accept="image/*">
                    <div class="form-text">Upload your company logo to display in the shopping cart.</div>
                    <?php if (!empty($current_settings['cart_logo']) && file_exists($current_settings['cart_logo'])): ?>
                        <div class="mt-2">
                            <img src="<?= htmlspecialchars($current_settings['cart_logo']) ?>" alt="Cart Logo" style="max-width: 200px;">
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Cart Description -->
                <div class="mb-4">
                    <label for="cart_description" class="form-label">
                        <i class="fas fa-align-left me-2"></i> <strong>Cart Description</strong>
                    </label>
                    <textarea
                        class="form-control wysiwyg"
                        id="cart_description"
                        name="cart_description"
                        rows="4"
                        placeholder="Enter a description to display below the logo in the shopping cart."><?= htmlspecialchars($current_settings['cart_description'] ?? '') ?></textarea>
                    <div class="form-text">Provide a brief description or message to accompany your logo in the cart.</div>
                </div>
            </div>
        </div>
        <!-- CSRF Token -->
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <!-- Save All Changes Button -->
        <div class="d-flex justify-content-end my-4">
            <button type="submit" class="btn btn-success me-2">
                <i class="fas fa-save"></i> Save All Changes
            </button>
            <button type="reset" class="btn btn-secondary">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>

<!-- Optional: JavaScript for Real-Time Validation -->
<script>
    // Example: Validate Minimum Order in Real-Time
    document.getElementById('minimum_order').addEventListener('input', function() {
        const value = parseFloat(this.value);
        if (!isNaN(value) && value >= 0) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
        }
    });

    // Example: Validate Social Media URLs in Real-Time
    const socialLinks = ['facebook_link', 'twitter_link', 'instagram_link', 'linkedin_link', 'youtube_link'];
    socialLinks.forEach(function(linkId) {
        const linkInput = document.getElementById(linkId);
        if (linkInput) {
            linkInput.addEventListener('input', function() {
                const value = this.value.trim();
                if (value === '' || isValidURL(value)) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            });
        }
    });

    function isValidURL(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }
</script>

<?php
require_once 'includes/footer.php';
?>