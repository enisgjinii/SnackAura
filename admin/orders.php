<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

// Define error log file
define('ERROR_LOG_FILE', __DIR__ . '/errors.md');

// Function to send status update emails
function sendStatusUpdateEmail($email, $name, $order_id, $status)
{
    $mail = new PHPMailer(true);
    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.example.com'; // Replace with your SMTP host
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your_email@example.com'; // Replace with your SMTP username
        $mail->Password   = 'your_password'; // Replace with your SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587; // Adjust port if necessary

        // Sender and recipient settings
        $mail->setFrom('no-reply@example.com', 'Your Company'); // Replace with your sender email and name
        $mail->addAddress($email, $name);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = "Update Status of Your Order #{$order_id}";
        $mail->Body    = "<h3>Dear {$name},</h3>
                          <p>Your order <strong>#{$order_id}</strong> status has been updated to <strong>{$status}</strong>.</p>
                          <p>Thank you for choosing us!</p>";

        $mail->send();
    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
    }
}

// Function to log errors in Markdown format
function log_error_markdown($error_message, $context = '')
{
    $timestamp = date('Y-m-d H:i:s');
    $safe_error_message = htmlspecialchars($error_message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $formatted_message = "### [$timestamp] Error\n\n**Message:** $safe_error_message\n\n";
    if ($context) {
        $safe_context = htmlspecialchars($context, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $formatted_message .= "**Context:** $safe_context\n\n";
    }
    $formatted_message .= "---\n\n";
    file_put_contents(ERROR_LOG_FILE, $formatted_message, FILE_APPEND | LOCK_EX);
}

// Custom exception handler
set_exception_handler(function ($exception) {
    log_error_markdown("Uncaught Exception: " . $exception->getMessage(), "File: " . $exception->getFile() . " Line: " . $exception->getLine());
    header("Location: orders.php?action=view&message=unknown_error");
    exit;
});

// Custom error handler to convert errors to exceptions
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Initialize variables
$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

// Handle different actions using switch statement
switch ($action) {
    case 'update_status':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
            $status_id = $_POST['status_id'] ?? 0;
            if ($status_id && is_numeric($status_id)) {
                // Fetch new status
                $stmt = $pdo->prepare('SELECT status FROM order_statuses WHERE id = ?');
                $stmt->execute([$status_id]);
                $new_status = $stmt->fetchColumn();

                if ($new_status) {
                    // Fetch customer details
                    $stmt = $pdo->prepare('SELECT customer_email, customer_name FROM orders WHERE id = ? AND deleted_at IS NULL');
                    $stmt->execute([$id]);
                    $order = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($order) {
                        // Update order status
                        $update = $pdo->prepare('UPDATE orders SET status_id = ? WHERE id = ?');
                        if ($update->execute([$status_id, $id])) {
                            // Send email notification
                            sendStatusUpdateEmail($order['customer_email'], $order['customer_name'], $id, $new_status);
                            header('Location: orders.php?action=view&message=' . urlencode('Order status updated successfully.'));
                            exit();
                        }
                    }
                }
            }
            header('Location: orders.php?action=update_status_form&id=' . $id . '&message=' . urlencode('Failed to update status.'));
            exit();
        }
        break;

    case 'delete':
        if ($id > 0) {
            // Zhvendos porosinë në Trash duke përditësuar fushën deleted_at
            $delete = $pdo->prepare('UPDATE orders SET deleted_at = NOW() WHERE id = ?');
            $success = $delete->execute([$id]);
            $msg = $success ? 'Porosia është zhvendosur në Trash.' : 'Deshtoi zhvendosja e porosisë në Trash.';
            header('Location: orders.php?action=view&message=' . urlencode($msg));
            exit();
        }
        break;

    case 'permanent_delete':
        if ($id > 0) {
            // Fshije përfundimisht porosinë nga baza e të dhënave
            $perm_delete = $pdo->prepare('DELETE FROM orders WHERE id = ?');
            $success = $perm_delete->execute([$id]);
            $msg = $success ? 'Porosia është fshirë përfundimisht.' : 'Deshtoi fshirja përfundimtare e porosisë.';
            header('Location: orders.php?action=view_trash&message=' . urlencode($msg));
            exit();
        }
        break;

    case 'restore':
        if ($id > 0) {
            // Rikthe porosinë duke vendosur deleted_at në NULL
            $restore = $pdo->prepare('UPDATE orders SET deleted_at = NULL WHERE id = ?');
            $success = $restore->execute([$id]);
            $msg = $success ? 'Porosia është rikthyer.' : 'Deshtoi rikthimi i porosisë.';
            header('Location: orders.php?action=view_trash&message=' . urlencode($msg));
            exit();
        }
        break;

    case 'view_trash':
        try {
            // Fetch all deleted orders
            $stmt = $pdo->prepare('
                SELECT o.*, os.status
                FROM orders o
                JOIN order_statuses os ON o.status_id = os.id
                WHERE o.deleted_at IS NOT NULL
                ORDER BY o.deleted_at DESC
            ');
            $stmt->execute();
            $trash_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $trash_orders = [];
            $message = '<div class="alert alert-danger">Deshtoi marrja e porosive në Trash.</div>';
        }
        break;

    case 'view_details':
        if ($id > 0) {
            try {
                // Fetch order details only if not in Trash
                $stmt = $pdo->prepare('SELECT o.*, os.status FROM orders o JOIN order_statuses os ON o.status_id = os.id WHERE o.id = ? AND o.deleted_at IS NULL');
                $stmt->execute([$id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($order) {
                    // Fetch order items
                    $stmt = $pdo->prepare('
                        SELECT oi.*, p.name AS product_name, s.name AS size_name 
                        FROM order_items oi 
                        JOIN products p ON oi.product_id = p.id 
                        LEFT JOIN sizes s ON oi.size_id = s.id 
                        WHERE oi.order_id = ?
                    ');
                    $stmt->execute([$id]);
                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Fetch extras and drinks for each item
                    foreach ($items as &$item) {
                        // Fetch extras
                        $stmt_extras = $pdo->prepare('
                            SELECT e.name, oe.quantity, oe.unit_price, oe.total_price 
                            FROM order_extras oe 
                            JOIN extras e ON oe.extra_id = e.id 
                            WHERE oe.order_item_id = ?
                        ');
                        $stmt_extras->execute([$item['id']]);
                        $item['extras'] = $stmt_extras->fetchAll(PDO::FETCH_ASSOC);

                        // Fetch drinks
                        $stmt_drinks = $pdo->prepare('
                            SELECT d.name, od.quantity, od.unit_price, od.total_price 
                            FROM order_drinks od 
                            JOIN drinks d ON od.drink_id = d.id 
                            WHERE od.order_item_id = ?
                        ');
                        $stmt_drinks->execute([$item['id']]);
                        $item['drinks'] = $stmt_drinks->fetchAll(PDO::FETCH_ASSOC);
                    }
                } else {
                    echo '<div class="alert alert-warning">Porosia nuk ekziston ose është në Trash.</div>';
                    require_once 'includes/footer.php';
                    exit();
                }
            } catch (PDOException $e) {
                echo '<div class="alert alert-danger">Deshtoi marrja e detajeve të porosisë.</div>';
                require_once 'includes/footer.php';
                exit();
            }
        }
        break;

    case 'update_status_form':
        if ($id > 0) {
            try {
                // Fetch order details
                $stmt = $pdo->prepare('SELECT o.*, os.status FROM orders o JOIN order_statuses os ON o.status_id = os.id WHERE o.id = ? AND o.deleted_at IS NULL');
                $stmt->execute([$id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$order) {
                    echo '<div class="alert alert-warning">Porosia nuk ekziston ose është në Trash.</div>';
                    require_once 'includes/footer.php';
                    exit();
                }

                // Fetch all statuses
                $statuses = $pdo->query('SELECT * FROM order_statuses ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

                // Fetch any messages
                $message = $_GET['message'] ?? '';
            } catch (PDOException $e) {
                echo '<div class="alert alert-danger">Deshtoi marrja e të dhënave.</div>';
                require_once 'includes/footer.php';
                exit();
            }
        }
        break;

    case 'customer_counts':
        try {
            // Fetch customer order counts excluding Trash
            $customer_counts = $pdo->query('
                SELECT customer_name, customer_email, customer_phone, COUNT(*) AS order_count 
                FROM orders 
                WHERE deleted_at IS NULL
                GROUP BY customer_email, customer_phone 
                ORDER BY order_count DESC
            ')->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $customer_counts = [];
            $message = '<div class="alert alert-danger">Deshtoi marrja e numrave të porosive të klientëve.</div>';
        }
        break;

    case 'manage_ratings':
        try {
            // Fetch all ratings
            $stmt = $pdo->prepare('SELECT * FROM ratings ORDER BY created_at DESC');
            $stmt->execute();
            $ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $ratings = [];
            $message = '<div class="alert alert-danger">Deshtoi marrja e vlerësimeve.</div>';
        }
        break;

    case 'delete_rating':
        if ($id > 0) {
            // Delete rating
            $delete = $pdo->prepare('DELETE FROM ratings WHERE id = ?');
            $success = $delete->execute([$id]);
            $msg = $success ? 'Vlerësimi është fshirë me sukses.' : 'Deshtoi fshirja e vlerësimit.';
            header('Location: orders.php?action=manage_ratings&message=' . urlencode($msg));
            exit();
        }
        break;

    case 'view':
    default:
        try {
            // Fetch all active orders with order_count using subquery and COALESCE to handle NULLs
            $stmt = $pdo->prepare('
                SELECT o.*, os.status, c.order_count
                FROM orders o
                JOIN order_statuses os ON o.status_id = os.id
                JOIN (
                    SELECT 
                        COALESCE(customer_email, "") AS customer_email, 
                        COALESCE(customer_phone, "") AS customer_phone, 
                        COUNT(*) AS order_count
                    FROM orders
                    WHERE deleted_at IS NULL
                    GROUP BY customer_email, customer_phone
                ) c 
                ON (o.customer_email = c.customer_email OR o.customer_phone = c.customer_phone)
                WHERE o.deleted_at IS NULL
                ORDER BY os.id ASC, o.created_at DESC
            ');
            $stmt->execute();
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch all statuses
            $statuses = $pdo->query('SELECT * FROM order_statuses ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

            // Group orders by status
            $grouped_orders = [];
            foreach ($statuses as $status) {
                $grouped_orders[$status['status']] = [];
            }
            foreach ($orders as $order) {
                $grouped_orders[$order['status']][] = $order;
            }
        } catch (PDOException $e) {
            $grouped_orders = [];
            $message = '<div class="alert alert-danger">Deshtoi marrja e porosive.</div>';
        }
        break;
}

// Fetch Ratings if Managing Ratings
if ($action === 'manage_ratings') {
    // Ratings have been fetched in the 'manage_ratings' case
}
?>
<?php if ($action === 'view'): ?>
    <!-- Orders Management Section -->
    <h2>Porositë</h2>
    <a href="orders.php?action=customer_counts" class="btn btn-primary mb-3">Shikoni Numrat e Porosive të Klientëve</a>
    <a href="orders.php?action=manage_ratings" class="btn btn-warning mb-3 ms-2">Menaxhoni Vlerësimet</a>
    <a href="orders.php?action=view_trash" class="btn btn-danger mb-3 ms-2">Trash</a>
    <?= isset($_GET['message']) ? '<div class="alert alert-success">' . htmlspecialchars($_GET['message']) . '</div>' : ($message ?? '') ?>
    <?php foreach ($grouped_orders as $status => $orders): ?>
        <h4><?= htmlspecialchars($status) ?> Porositë</h4>
        <?php if (count($orders) > 0): ?>
            <table class="table table-bordered table-hover mb-4">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Klienti</th>
                        <th>Email</th>
                        <th>Telefoni</th>
                        <th>Adresë</th>
                        <th>Totali (€)</th>
                        <th>Krijuar Më</th>
                        <th>Numri i Porosive</th>
                        <th>Veprime</th>
                    </tr>
                </thead>
                <tbody id="orders-table-body-<?= strtolower(str_replace(' ', '_', $status)) ?>">
                    <?php foreach ($orders as $order): ?>
                        <?php $order_count = $order['order_count'] ?? 1; ?>
                        <tr id="order-row-<?= htmlspecialchars($order['id']) ?>">
                            <td><?= htmlspecialchars($order['id']) ?></td>
                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                            <td><?= htmlspecialchars($order['customer_email']) ?></td>
                            <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                            <td><?= htmlspecialchars($order['delivery_address']) ?></td>
                            <td><?= number_format($order['total_amount'], 2) ?>€</td>
                            <td><?= htmlspecialchars($order['created_at']) ?></td>
                            <td><?= htmlspecialchars($order_count) ?> <?= $order_count > 1 ? '<span class="badge bg-primary">Ripërsëritje</span>' : '' ?></td>
                            <td>
                                <a href="orders.php?action=view_details&id=<?= $order['id'] ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Shiko">Shiko</a>
                                <a href="orders.php?action=update_status_form&id=<?= $order['id'] ?>" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Përditëso Statusin">Përditëso Statusin</a>
                                <a href="orders.php?action=delete&id=<?= $order['id'] ?>" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="Fshije" onclick="return confirm('A jeni i sigurt që dëshironi të fshini këtë porosi?')">Fshije</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Nuk ka porosi në këtë kategori.</p>
        <?php endif; ?>
    <?php endforeach; ?>
<?php elseif ($action === 'manage_ratings'): ?>
    <!-- Manage Ratings Section -->
    <h2>Menaxho Vlerësimet e Klientëve</h2>
    <?= isset($_GET['message']) ? '<div class="alert alert-success">' . htmlspecialchars($_GET['message']) . '</div>' : ($message ?? '') ?>
    <?php if ($ratings): ?>
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Emri i Plotë</th>
                    <th>Email</th>
                    <th>Telefoni</th>
                    <th>Anonim</th>
                    <th>Vlerësimi</th>
                    <th>Komentet</th>
                    <th>Paraqitur Më</th>
                    <th>Veprime</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ratings as $rating): ?>
                    <tr id="rating-row-<?= htmlspecialchars($rating['id']) ?>">
                        <td><?= htmlspecialchars($rating['id']) ?></td>
                        <td><?= $rating['anonymous'] ? 'Anonim' : htmlspecialchars($rating['full_name']) ?></td>
                        <td><?= $rating['anonymous'] ? 'N/A' : htmlspecialchars($rating['email']) ?></td>
                        <td><?= $rating['anonymous'] ? 'N/A' : htmlspecialchars($rating['phone'] ?? 'N/A') ?></td>
                        <td><?= $rating['anonymous'] ? '<span class="badge bg-secondary">Po</span>' : '<span class="badge bg-success">Jo</span>' ?></td>
                        <td><?= str_repeat('⭐', $rating['rating']) ?></td>
                        <td><?= htmlspecialchars($rating['comments'] ?? 'Pa komente') ?></td>
                        <td><?= htmlspecialchars($rating['created_at']) ?></td>
                        <td>
                            <a href="orders.php?action=delete_rating&id=<?= $rating['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('A jeni i sigurt që dëshironi të fshini këtë vlerësim?')">Fshije</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Nuk ka vlerësime të paraqitura ende.</p>
    <?php endif; ?>
    <a href="orders.php?action=view" class="btn btn-secondary">Kthehu në Porositë</a>
<?php elseif ($action === 'view_details' && $id > 0): ?>
    <!-- Order Details Section -->
    <h2>Detajet e Porosisë - ID: <?= htmlspecialchars($order['id']) ?></h2>
    <div class="mb-3">
        <strong>Emri i Klientit:</strong> <?= htmlspecialchars($order['customer_name']) ?><br>
        <strong>Email:</strong> <?= htmlspecialchars($order['customer_email']) ?><br>
        <strong>Telefoni:</strong> <?= htmlspecialchars($order['customer_phone']) ?><br>
        <strong>Adresa:</strong> <?= nl2br(htmlspecialchars($order['delivery_address'])) ?><br>
        <strong>Shuma Totale:</strong> <?= number_format($order['total_amount'], 2) ?>€<br>
        <strong>Statusi:</strong> <?= htmlspecialchars($order['status']) ?><br>
        <strong>Krijuar Më:</strong> <?= htmlspecialchars($order['created_at']) ?>
    </div>
    <h4>Pajisjet:</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Produkti</th>
                <th>Madhësia</th>
                <th>Shuma</th>
                <th>Çmimi (€)</th>
                <th>Extras</th>
                <th>Pijes</th>
                <th>Udhëzime Speciale</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                    <td><?= htmlspecialchars($item['size_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($item['quantity']) ?></td>
                    <td><?= number_format($item['price'], 2) ?></td>
                    <td>
                        <?= !empty($item['extras'])
                            ? implode('<br>', array_map(fn($e) => htmlspecialchars($e['name']) . ' x' . htmlspecialchars($e['quantity']) . ' (+' . number_format($e['unit_price'], 2) . '€)', $item['extras']))
                            : '-' ?>
                    </td>
                    <td>
                        <?= !empty($item['drinks'])
                            ? implode('<br>', array_map(fn($d) => htmlspecialchars($d['name']) . ' x' . htmlspecialchars($d['quantity']) . ' (+' . number_format($d['unit_price'], 2) . '€)', $item['drinks']))
                            : '-' ?>
                    </td>
                    <td><?= htmlspecialchars($item['special_instructions']) ?: '-' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <a href="orders.php?action=view" class="btn btn-secondary">Kthehu në Porositë</a>
<?php elseif ($action === 'update_status_form' && $id > 0): ?>
    <!-- Update Status Form Section -->
    <h2>Update Status porosisë - ID: <?= htmlspecialchars($order['id']) ?></h2>
    <?= $message ? '<div class="alert alert-danger">' . htmlspecialchars($message) . '</div>' : '' ?>
    <form method="POST" action="orders.php?action=update_status&id=<?= $id ?>">
        <div class="mb-3">
            <label for="status_id" class="form-label">Zgjidh Statusin e Ri</label>
            <select class="form-select" id="status_id" name="status_id" required>
                <?php foreach ($statuses as $status_option): ?>
                    <option value="<?= htmlspecialchars($status_option['id']) ?>" <?= ($order['status_id'] == $status_option['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($status_option['status']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-success">Përditëso Statusin</button>
        <a href="orders.php?action=view" class="btn btn-secondary">Anulo</a>
    </form>
<?php elseif ($action === 'customer_counts'): ?>
    <!-- Customer Counts Section -->
    <h2>Numrat e Porosive të Klientëve</h2>
    <?= isset($_GET['message']) ? '<div class="alert alert-success">' . htmlspecialchars($_GET['message']) . '</div>' : ($message ?? '') ?>
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Emri i Klientit</th>
                <th>Email</th>
                <th>Telefoni</th>
                <th>Numri Total i Porosive</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($customer_counts as $customer): ?>
                <tr>
                    <td><?= htmlspecialchars($customer['customer_name']) ?></td>
                    <td><?= htmlspecialchars($customer['customer_email']) ?></td>
                    <td><?= htmlspecialchars($customer['customer_phone']) ?></td>
                    <td><?= htmlspecialchars($customer['order_count']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <a href="orders.php?action=view" class="btn btn-secondary">Kthehu në Porositë</a>
<?php elseif ($action === 'view_trash'): ?>
    <!-- Trash Management Section -->
    <h2>Trash - Porositë e Fshira</h2>
    <?= isset($_GET['message']) ? '<div class="alert alert-success">' . htmlspecialchars($_GET['message']) . '</div>' : ($message ?? '') ?>
    <?php if ($trash_orders): ?>
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Emri i Klientit</th>
                    <th>Email</th>
                    <th>Telefoni</th>
                    <th>Adresë</th>
                    <th>Shuma Totale (€)</th>
                    <th>Fshihet Më</th>
                    <th>Statusi</th>
                    <th>Veprime</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trash_orders as $order): ?>
                    <tr id="trash-order-row-<?= htmlspecialchars($order['id']) ?>">
                        <td><?= htmlspecialchars($order['id']) ?></td>
                        <td><?= htmlspecialchars($order['customer_name']) ?></td>
                        <td><?= htmlspecialchars($order['customer_email']) ?></td>
                        <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                        <td><?= htmlspecialchars($order['delivery_address']) ?></td>
                        <td><?= number_format($order['total_amount'], 2) ?>€</td>
                        <td><?= htmlspecialchars($order['deleted_at']) ?></td>
                        <td><?= htmlspecialchars($order['status']) ?></td>
                        <td>
                            <a href="orders.php?action=restore&id=<?= $order['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('A jeni i sigurt që dëshironi të riktheni këtë porosi?')">Rikthe</a>
                            <a href="orders.php?action=permanent_delete&id=<?= $order['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('A jeni i sigurt që dëshironi të fshini këtë porosi përfundimisht?')">Fshije Përfundimisht</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Trash është bosh.</p>
    <?php endif; ?>
    <a href="orders.php?action=view" class="btn btn-secondary">Kthehu në Porositë</a>
<?php endif; ?>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<?php require_once 'includes/footer.php'; ?>