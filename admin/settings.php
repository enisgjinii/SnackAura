<?php
// admin/settings.php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

$message = '';

// Function to fetch settings as key-value pairs
function getSettings($pdo)
{
    $stmt = $pdo->query('SELECT `key`, `value` FROM `settings`');
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Fetch current settings
$settings = getSettings($pdo);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start a transaction for atomicity
    $pdo->beginTransaction();
    try {
        foreach ($_POST as $key => $value) {
            // Validate the key to prevent unauthorized updates
            // Optional: Implement a whitelist of allowed keys
            if (array_key_exists($key, $settings)) {
                // Update each setting
                $update_stmt = $pdo->prepare('UPDATE `settings` SET `value` = ? WHERE `key` = ?');
                $update_stmt->execute([trim($value), $key]);
            }
        }
        // Commit the transaction
        $pdo->commit();
        $message = '<div class="alert alert-success">Settings updated successfully.</div>';
        // Refresh settings
        $settings = getSettings($pdo);
    } catch (PDOException $e) {
        // Rollback the transaction on error
        $pdo->rollBack();
        $message = '<div class="alert alert-danger">Error updating settings: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
?>

<h2>Settings</h2>
<?php if ($message): ?>
    <?= $message ?>
<?php endif; ?>
<form method="POST" action="settings.php">
    <div class="mb-3">
        <label for="minimum_order" class="form-label">Minimum Order (€)</label>
        <input type="number" step="0.01" min="0" class="form-control" id="minimum_order" name="minimum_order" required value="<?= htmlspecialchars($settings['minimum_order'] ?? '5.00') ?>">
    </div>
    <!-- Add more settings fields as needed -->
    <button type="submit" class="btn btn-success">Update Settings</button>
</form>

<?php
require_once 'includes/footer.php';
?>