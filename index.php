<?php
ob_start();
require 'vendor/autoload.php';

use Stripe\Stripe;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\{Amount, Payer, Payment, RedirectUrls, Transaction, PaymentExecution};

// Initialize Stripe
Stripe::setApiKey("sk_test_XXX"); // Replace with your Stripe secret key

// Initialize PayPal
$paypal = new ApiContext(
    new OAuthTokenCredential(
        'AfbPMlmPT4z37DRzH886cPd1AggGZjz-L_LnVJxx_Odv7AB82AQ9CIz8P_s-5cjgLf-NDgpng0NLAiWr',     // Replace with your PayPal Client ID
        'EKbX-h3EwnMlRoAyyGBCFi2370doQi06hO6iOiQJsQ1gDnpvTrwYQIyTG2MxG6H1vVuWpz_Or76JTThi'  // Replace with your PayPal Client Secret
    )
);
$paypal->setConfig([
    'mode' => 'sandbox', // Change to 'live' for production
]);

session_start();
require 'includes/db_connect.php';

// CSRF Token Initialization
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Error Logging Function
define('ERROR_LOG_FILE', __DIR__ . '/errors.md');
function log_error_markdown($msg, $ctx = '')
{
    $t = date('Y-m-d H:i:s');
    $m = "### [$t] Error\n\n**Message:** " . htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n\n";
    if ($ctx) {
        $m .= "**Context:** " . htmlspecialchars($ctx, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n\n";
    }
    $m .= "---\n\n";
    file_put_contents(ERROR_LOG_FILE, $m, FILE_APPEND | LOCK_EX);
}

// Exception and Error Handlers
set_exception_handler(function ($e) {
    log_error_markdown("Uncaught Exception: " . $e->getMessage(), "File: " . $e->getFile() . " Line: " . $e->getLine());
    header("Location: index.php?error=unknown_error");
    exit;
});
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Include Settings and Initialize Cart
include 'settings_fetch.php';
$_SESSION['cart'] = $_SESSION['cart'] ?? [];

// Fetch Active Stores if Not Selected
if (!isset($_SESSION['selected_store'])) {
    $stores = $pdo->query("SELECT id, name FROM stores WHERE is_active=1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Operational Hours and Check Store Status
$is_closed = false;
$notification = [];
$cdt = new DateTime();
$curD = $cdt->format('Y-m-d');
$curDay = $cdt->format('l');
$curT = $cdt->format('H:i:s');

$q = "SELECT type, is_closed, title, description, date, open_time, close_time, day_of_week 
      FROM operational_hours 
      WHERE (type='holiday' AND date=:d) OR (type='regular' AND day_of_week=:dw) 
      LIMIT 1";
$stmt = $pdo->prepare($q);
$stmt->execute(['d' => $curD, 'dw' => $curDay]);
$op = $stmt->fetch(PDO::FETCH_ASSOC);

if ($op) {
    if ($op['type'] === 'holiday' && $op['is_closed']) {
        $is_closed = true;
        $notification = [
            'title' => $op['title'] ?? 'Store Closed',
            'message' => $op['description'] ?? 'Closed for a holiday.',
            'end_datetime' => (new DateTime("{$op['date']} {$op['close_time']}"))->format('c')
        ];
    } elseif ($op['type'] === 'regular') {
        if ($op['is_closed'] || $curT < ($op['open_time'] ?? '00:00:00') || $curT > ($op['close_time'] ?? '23:59:59')) {
            $is_closed = true;
            $end_time = $op['is_closed']
                ? (new DateTime())->modify('+1 day')->format('c')
                : (new DateTime("{$curD} {$op['close_time']}"))->format('c');
            $notification = [
                'title' => 'Store Closed',
                'message' => $op['is_closed']
                    ? 'The store is closed.'
                    : "Closed now. Working hours {$op['open_time']} - {$op['close_time']}.",
                'end_datetime' => $end_time
            ];
        }
    }
}

// Fetch Tip Options
try {
    $tip_options = $pdo->query("SELECT * FROM tips WHERE is_active=1 ORDER BY percentage ASC, amount ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_error_markdown("Failed to fetch tips: " . $e->getMessage(), "Fetching Tips");
    $tip_options = [];
}

// Handle Tip Selection
if (isset($_GET['select_tip'])) {
    $tid = (int)$_GET['select_tip'];
    $valid = array_filter($tip_options, fn($t) => $t['id'] === $tid) || $tid === 0;
    if ($valid) {
        $_SESSION['selected_tip'] = $tid === 0 ? null : $tid;
        header("Location: index.php");
        exit;
    }
    header("Location: index.php?error=invalid_tip");
    exit;
}

// Helper Functions
function fetchProduct($pdo, $pid)
{
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id=:p AND is_active=1");
    $stmt->execute(['p' => $pid]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function calculateCartTotal($cart)
{
    return array_reduce($cart, fn($c, $i) => $c + ($i['total_price'] ?? 0.0), 0.0);
}

function applyTip($cart_total, $tip_options, $tip_id)
{
    if (!$tip_id) return 0.0;
    foreach ($tip_options as $t) {
        if ($t['id'] == $tip_id) {
            return !empty($t['percentage']) ? $cart_total * ((float)$t['percentage'] / 100) : (float)$t['amount'];
        }
    }
    return 0.0;
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Store Selection
    if (isset($_POST['store_id'])) {
        $sid = (int)$_POST['store_id'];
        $st = $pdo->prepare("SELECT name FROM stores WHERE id=? AND is_active=1");
        $st->execute([$sid]);
        $s = $st->fetch(PDO::FETCH_ASSOC);
        if ($s) {
            $da = trim($_POST['delivery_address'] ?? '');
            $lat = trim($_POST['latitude'] ?? '');
            $lon = trim($_POST['longitude'] ?? '');
            if (empty($da) || empty($lat) || empty($lon)) {
                $error_message = "Please provide a valid delivery address and select your location on the map.";
            } elseif (!is_numeric($lat) || !is_numeric($lon)) {
                $error_message = "Invalid location coordinates.";
            } else {
                $_SESSION['selected_store'] = $sid;
                $_SESSION['store_name'] = $s['name'];
                $_SESSION['delivery_address'] = $da;
                $_SESSION['latitude'] = $lat;
                $_SESSION['longitude'] = $lon;
                header("Location: index.php");
                exit;
            }
        } else {
            $error_message = "Selected store is not available.";
        }
    }

    // Add to Cart
    if (isset($_POST['add_to_cart'])) {
        $pid = (int)$_POST['product_id'];
        $qty = max(1, (int)$_POST['quantity']);
        $sz = $_POST['size'] ?? null;
        $ex = $_POST['extras'] ?? [];
        $sa = $_POST['sauces'] ?? [];
        $dr = $_POST['dresses'] ?? [];
        $drink_id = isset($_POST['drink']) ? (int)$_POST['drink'] : null;
        $spec = trim($_POST['special_instructions'] ?? '');

        $pr = fetchProduct($pdo, $pid);
        if ($pr) {
            $props = json_decode($pr['properties'], true) ?? [];
            $base = isset($props['base_price']) && (float)$props['base_price'] ? (float)$props['base_price'] : (float)$pr['base_price'];
            $xopt = $props['extras'] ?? [];
            $sopt = $props['sauces'] ?? [];
            $zopt = $props['sizes'] ?? [];
            $dopt = $props['dresses'] ?? [];
            $sel_size = null;
            $szprice = 0.0;

            if ($sz) {
                foreach ($zopt as $z) {
                    if (!empty($z['size']) && $z['size'] === $sz) {
                        $sel_size = $z;
                        $szprice = (float)($z['price'] ?? 0.0);
                        break;
                    }
                }
                if (!$sel_size) {
                    log_error_markdown("Invalid size: $sz for Product $pid", "Add to Cart");
                    header("Location: index.php?error=invalid_size");
                    exit;
                }
                if (!empty($sel_size['extras'])) $xopt = $sel_size['extras'];
                if (!empty($sel_size['sauces'])) $sopt = $sel_size['sauces'];
                if (!empty($sel_size['dresses'])) $dopt = $sel_size['dresses'];
            }

            // Select Extras
            $sel_ex = array_filter($xopt, fn($xo) => in_array($xo['name'], $ex));
            $exTot = array_sum(array_column($sel_ex, 'price'));

            // Select Sauces
            $sel_sa = array_filter($sopt, fn($sx) => in_array($sx['name'], $sa));
            $saTot = array_sum(array_column($sel_sa, 'price'));

            // Select Dresses
            $sel_dr = array_filter($dopt, fn($dx) => in_array($dx['name'], $dr));
            $drTot = array_sum(array_column($sel_dr, 'price'));

            // Select Drink
            $drink_details = null;
            $drinkTot = 0.0;
            if ($drink_id) {
                $st = $pdo->prepare("SELECT * FROM drinks WHERE id=?");
                $st->execute([$drink_id]);
                $dk = $st->fetch(PDO::FETCH_ASSOC);
                if ($dk) {
                    $drink_details = $dk;
                    $drinkTot = (float)($dk['price'] ?? 0.0);
                }
            }

            // Calculate Prices
            $unit_price = $base + $szprice + $exTot + $saTot + $drTot + $drinkTot;
            $total_price = $unit_price * $qty;

            // Prepare Cart Item
            $cart_item = [
                'product_id' => $pr['id'],
                'name' => $pr['name'],
                'description' => $pr['description'],
                'image_url' => $pr['image_url'],
                'size' => $sel_size['size'] ?? null,
                'size_price' => $szprice,
                'extras' => array_values($sel_ex),
                'sauces' => array_values($sel_sa),
                'dresses' => array_values($sel_dr),
                'drink' => $drink_details,
                'special_instructions' => $spec,
                'quantity' => $qty,
                'unit_price' => $unit_price,
                'total_price' => $total_price
            ];

            // Check for Duplicate Items
            $dup_idx = false;
            foreach ($_SESSION['cart'] as $index => $item) {
                if (
                    $item['product_id'] === $cart_item['product_id'] &&
                    $item['size'] === $cart_item['size'] &&
                    (($item['drink']['id'] ?? null) === ($cart_item['drink']['id'] ?? null)) &&
                    $item['special_instructions'] === $cart_item['special_instructions'] &&
                    count(array_intersect(array_column($item['extras'], 'name'), array_column($cart_item['extras'], 'name'))) === count($cart_item['extras']) &&
                    count(array_intersect(array_column($item['sauces'], 'name'), array_column($cart_item['sauces'], 'name'))) === count($cart_item['sauces']) &&
                    count(array_intersect(array_column($item['dresses'], 'name'), array_column($cart_item['dresses'], 'name'))) === count($cart_item['dresses'])
                ) {
                    $dup_idx = $index;
                    break;
                }
            }

            if ($dup_idx !== false) {
                $_SESSION['cart'][$dup_idx]['quantity'] += $qty;
                $_SESSION['cart'][$dup_idx]['total_price'] += $total_price;
            } else {
                $_SESSION['cart'][] = $cart_item;
            }

            header("Location: index.php?added=1");
            exit;
        } else {
            log_error_markdown("Product not found or inactive. ID:$pid", "Adding to Cart");
            header("Location: index.php?error=invalid_product");
            exit;
        }
    }

    // Remove from Cart
    if (isset($_GET['remove'])) {
        $ri = (int)$_GET['remove'];
        if (isset($_SESSION['cart'][$ri])) {
            unset($_SESSION['cart'][$ri]);
            $_SESSION['cart'] = array_values($_SESSION['cart']);
            header("Location: index.php?removed=1");
            exit;
        }
        log_error_markdown("Attempted remove non-existent cart item: $ri", "Remove from Cart");
        header("Location: index.php?error=invalid_remove");
        exit;
    }

    // Update Cart
    if (isset($_POST['update_cart'])) {
        $ii = (int)$_POST['item_index'];
        if (isset($_SESSION['cart'][$ii])) {
            $qty = max(1, (int)$_POST['quantity']);
            $sz = $_POST['size'] ?? null;
            $ex = $_POST['extras'] ?? [];
            $sa = $_POST['sauces'] ?? [];
            $dr = $_POST['dresses'] ?? [];
            $drink_id = isset($_POST['drink']) ? (int)$_POST['drink'] : null;
            $spec = trim($_POST['special_instructions'] ?? '');
            $pid = $_SESSION['cart'][$ii]['product_id'];

            $pr = fetchProduct($pdo, $pid);
            if ($pr) {
                $props = json_decode($pr['properties'], true) ?? [];
                $base = isset($props['base_price']) && (float)$props['base_price'] ? (float)$props['base_price'] : (float)$pr['base_price'];
                $xopt = $props['extras'] ?? [];
                $sopt = $props['sauces'] ?? [];
                $zopt = $props['sizes'] ?? [];
                $dopt = $props['dresses'] ?? [];
                $sel_size = null;
                $szprice = 0.0;

                if ($sz) {
                    foreach ($zopt as $z) {
                        if (!empty($z['size']) && $z['size'] === $sz) {
                            $sel_size = $z;
                            $szprice = (float)($z['price'] ?? 0.0);
                            break;
                        }
                    }
                    if (!$sel_size) {
                        log_error_markdown("Invalid size: $sz for Product $pid", "Updating Cart");
                        header("Location: index.php?error=invalid_size");
                        exit;
                    }
                    if (!empty($sel_size['extras'])) $xopt = $sel_size['extras'];
                    if (!empty($sel_size['sauces'])) $sopt = $sel_size['sauces'];
                    if (!empty($sel_size['dresses'])) $dopt = $sel_size['dresses'];
                }

                // Select Extras
                $sel_ex = array_filter($xopt, fn($xo) => in_array($xo['name'], $ex));
                $exTot = array_sum(array_column($sel_ex, 'price'));

                // Select Sauces
                $sel_sa = array_filter($sopt, fn($sx) => in_array($sx['name'], $sa));
                $saTot = array_sum(array_column($sel_sa, 'price'));

                // Select Dresses
                $sel_dr = array_filter($dopt, fn($dx) => in_array($dx['name'], $dr));
                $drTot = array_sum(array_column($sel_dr, 'price'));

                // Select Drink
                $drink_details = null;
                $drinkTot = 0.0;
                if ($drink_id) {
                    $st = $pdo->prepare("SELECT * FROM drinks WHERE id=?");
                    $st->execute([$drink_id]);
                    $dk = $st->fetch(PDO::FETCH_ASSOC);
                    if ($dk) {
                        $drink_details = $dk;
                        $drinkTot = (float)($dk['price'] ?? 0.0);
                    }
                }

                // Calculate Prices
                $unit_price = $base + $szprice + $exTot + $saTot + $drTot + $drinkTot;
                $total_price = $unit_price * $qty;

                // Update Cart Item
                $_SESSION['cart'][$ii] = [
                    'product_id' => $pr['id'],
                    'name' => $pr['name'],
                    'description' => $pr['description'],
                    'image_url' => $pr['image_url'],
                    'size' => $sel_size['size'] ?? null,
                    'size_price' => $szprice,
                    'extras' => array_values($sel_ex),
                    'sauces' => array_values($sel_sa),
                    'dresses' => array_values($sel_dr),
                    'drink' => $drink_details,
                    'special_instructions' => $spec,
                    'quantity' => $qty,
                    'unit_price' => $unit_price,
                    'total_price' => $total_price
                ];

                header("Location: index.php?updated=1");
                exit;
            } else {
                log_error_markdown("Product not found or inactive ID:$pid", "Updating Cart");
                header("Location: index.php?error=invalid_update");
                exit;
            }
        } else {
            log_error_markdown("Invalid cart update request: $ii", "Updating Cart");
            header("Location: index.php?error=invalid_update");
            exit;
        }
    }

    // Checkout
    if (isset($_POST['checkout'])) {
        // Validate Cart and CSRF Token
        if (empty($_SESSION['cart'])) {
            header("Location: index.php?error=empty_cart");
            exit;
        }
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            header("Location: index.php?error=invalid_csrf_token");
            exit;
        }

        // Extract Order Details
        $store_id = $_SESSION['selected_store'] ?? null;
        $cn = trim($_POST['customer_name'] ?? '');
        $ce = trim($_POST['customer_email'] ?? '');
        $cp = trim($_POST['customer_phone'] ?? '');
        $da = trim($_POST['delivery_address'] ?? '');
        $stid = isset($_POST['selected_tip']) ? (int)$_POST['selected_tip'] : null;
        $ev = isset($_POST['is_event']) && $_POST['is_event'] == '1';
        $sd = $_POST['scheduled_date'] ?? null;
        $st_time = $_POST['scheduled_time'] ?? null;

        // Validate Event Scheduling
        if ($ev) {
            if (empty($sd) || empty($st_time)) {
                header("Location: index.php?error=missing_scheduled_time");
                exit;
            }
            $sdt = DateTime::createFromFormat('Y-m-d H:i', "$sd $st_time");
            if (!$sdt || $sdt < new DateTime()) {
                header("Location: index.php?error=invalid_scheduled_datetime");
                exit;
            }
        }

        // Payment Method Validation
        $pm = $_POST['payment_method'] ?? '';
        if (!in_array($pm, ['stripe', 'paypal', 'pickup', 'cash'])) {
            header("Location: index.php?error=invalid_payment_method");
            exit;
        }

        // Validate Customer Details
        if (empty($cn) || empty($ce) || empty($cp) || empty($da)) {
            header("Location: index.php?error=invalid_order_details");
            exit;
        }

        // Calculate Totals
        $cart_total = calculateCartTotal($_SESSION['cart']);
        $tip_amount = applyTip($cart_total, $tip_options, $stid);
        $total = $cart_total + $tip_amount;

        // Prepare Order Details
        $order_details = json_encode([
            'items' => $_SESSION['cart'],
            'latitude' => $_SESSION['latitude'] ?? null,
            'longitude' => $_SESSION['longitude'] ?? null,
            'tip_id' => $stid,
            'tip_amount' => $tip_amount,
            'store_id' => $store_id,
            'is_event' => $ev,
            'scheduled_date' => $sd,
            'scheduled_time' => $st_time
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        try {
            // Begin Transaction
            $pdo->beginTransaction();

            // Insert Order
            $stmt = $pdo->prepare("INSERT INTO orders(user_id, customer_name, customer_email, customer_phone, delivery_address, total_amount, status_id, tip_id, tip_amount, scheduled_date, scheduled_time, payment_method, store_id, order_details)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([null, $cn, $ce, $cp, $da, $total, 2, $stid, $tip_amount, $sd, $st_time, $pm, $store_id, $order_details]);
            $oid = $pdo->lastInsertId();

            if (in_array($pm, ['stripe', 'paypal'])) {
                if ($pm === 'stripe') {
                    // Handle Stripe Payment
                    $spm = $_POST['stripe_payment_method'] ?? '';
                    if (empty($spm)) throw new Exception("Stripe payment method ID missing.");
                    $amtC = intval(round($total * 100));
                    $ret = "http://" . $_SERVER['HTTP_HOST'] . "/index.php?order=success&scheduled_date=" . urlencode($sd) . "&scheduled_time=" . urlencode($st_time);
                    $pi = \Stripe\PaymentIntent::create([
                        'amount' => $amtC,
                        'currency' => 'eur',
                        'payment_method' => $spm,
                        'confirmation_method' => 'manual',
                        'confirm' => true,
                        'description' => "Order ID:$oid",
                        'metadata' => ['order_id' => $oid],
                        'return_url' => $ret
                    ]);

                    if ($pi->status === 'requires_action' && $pi->next_action->type === 'use_stripe_sdk') {
                        header('Content-Type: application/json');
                        echo json_encode(['requires_action' => true, 'payment_intent_client_secret' => $pi->client_secret]);
                        exit;
                    } elseif ($pi->status === 'succeeded') {
                        $pdo->prepare("UPDATE orders SET status_id=? WHERE id=?")->execute([5, $oid]);
                        $pdo->commit();
                        $_SESSION['cart'] = [];
                        $_SESSION['selected_tip'] = null;
                        echo json_encode(['success' => true, 'redirect_url' => $ret]);
                        exit;
                    } else {
                        throw new Exception("Invalid PaymentIntent status: " . $pi->status);
                    }
                } elseif ($pm === 'paypal') {
                    // Create PayPal Payment
                    $payer = new Payer();
                    $payer->setPaymentMethod('paypal');

                    $amount = new Amount();
                    $amount->setTotal(number_format($total, 2, '.', ''))
                        ->setCurrency('EUR');

                    $transaction = new Transaction();
                    $transaction->setAmount($amount)
                        ->setDescription("Order ID: $oid");

                    $redirectUrls = new RedirectUrls();
                    $redirectUrls->setReturnUrl("http://" . $_SERVER['HTTP_HOST'] . "/index.php?payment=paypal&success=true&order_id=$oid")
                        ->setCancelUrl("http://" . $_SERVER['HTTP_HOST'] . "/index.php?payment=paypal&success=false&order_id=$oid");

                    $payment = new Payment();
                    $payment->setIntent('sale')
                        ->setPayer($payer)
                        ->setTransactions([$transaction])
                        ->setRedirectUrls($redirectUrls);

                    $payment->create($paypal);

                    // Save Payment ID to Session
                    $_SESSION['paypal_payment_id'] = $payment->getId();

                    // Redirect to PayPal for Approval
                    foreach ($payment->getLinks() as $link) {
                        if ($link->getRel() === 'approval_url') {
                            header("Location: " . $link->getHref());
                            exit;
                        }
                    }
                    throw new Exception("No approval URL found for PayPal payment.");
                }
            } else {
                // Handle Non-Online Payments
                $status_map = ['pickup' => 3, 'cash' => 4];
                $pdo->prepare("UPDATE orders SET status_id=? WHERE id=?")->execute([$status_map[$pm], $oid]);
                $pdo->commit();
                $_SESSION['cart'] = [];
                $_SESSION['selected_tip'] = null;
                header("Location: index.php?order=success&scheduled_date=$sd&scheduled_time=$st_time");
                exit;
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            log_error_markdown("Order Placement Failed: " . $e->getMessage(), "Checkout Process");
            header("Location: index.php?error=order_failed");
            exit;
        }
    }
}

// Handle PayPal Payment Approval
if (isset($_GET['payment']) && $_GET['payment'] === 'paypal') {
    $success = $_GET['success'] === 'true';
    $order_id = (int)($_GET['order_id'] ?? 0);
    if ($order_id <= 0) {
        log_error_markdown("Invalid PayPal order ID.", "PayPal Callback");
        header("Location: index.php?error=invalid_order");
        exit;
    }

    if ($success) {
        $paymentId = $_SESSION['paypal_payment_id'] ?? '';
        $payerId = $_GET['PayerID'] ?? '';

        if (empty($paymentId) || empty($payerId)) {
            log_error_markdown("Missing PayPal payment details.", "PayPal Callback");
            header("Location: index.php?error=paypal_failed");
            exit;
        }

        try {
            $payment = Payment::get($paymentId, $paypal);
            $execution = new PaymentExecution();
            $execution->setPayerId($payerId);
            $result = $payment->execute($execution, $paypal);

            if ($result->getState() === 'approved') {
                $pdo->prepare("UPDATE orders SET status_id=? WHERE id=?")->execute([5, $order_id]);
                $pdo->commit();
                $_SESSION['cart'] = [];
                $_SESSION['selected_tip'] = null;
                header("Location: index.php?order=success&scheduled_date=" . urlencode($_SESSION['scheduled_date'] ?? '') . "&scheduled_time=" . urlencode($_SESSION['scheduled_time'] ?? ''));
                exit;
            } else {
                throw new Exception("PayPal payment not approved.");
            }
        } catch (Exception $e) {
            log_error_markdown("PayPal Payment Failed: " . $e->getMessage(), "PayPal Callback");
            header("Location: index.php?error=paypal_failed");
            exit;
        }
    } else {
        // Payment canceled by user
        header("Location: index.php?error=paypal_canceled");
        exit;
    }
}

// Fetch Categories, Products, Drinks, Banners, Offers
try {
    $categories = $pdo->prepare("SELECT * FROM categories ORDER BY position ASC, name ASC");
    $categories->execute();
    $categories = $categories->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_error_markdown("Failed to fetch categories: " . $e->getMessage(), "Categories");
    $categories = [];
}

$selC = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$q = "SELECT p.id as product_id, p.product_code, p.name as product_name, p.category, p.description, 
             p.allergies, p.image_url, p.is_new, p.is_offer, p.is_active, p.properties, 
             p.base_price, p.created_at, p.updated_at, p.category_id 
      FROM products p 
      WHERE p.is_active=1" . ($selC > 0 ? " AND p.category_id=:c" : "") . " 
      ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($q);
if ($selC > 0) $stmt->bindParam(':c', $selC, PDO::PARAM_INT);
$stmt->execute();
$raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$products = array_map(function ($r) {
    $pp = json_decode($r['properties'], true) ?? [];
    $sz = $pp['sizes'] ?? [];
    $dbp = isset($pp['base_price']) && (float)$pp['base_price'] ? (float)$pp['base_price'] : (float)$r['base_price'];
    $dp = $dbp + (!empty($sz) ? min(array_map(fn($s) => (float)($s['price'] ?? 0.0), $sz)) : 0.0);
    return [
        'id' => $r['product_id'],
        'product_code' => $r['product_code'],
        'name' => $r['product_name'],
        'category' => $r['category'],
        'description' => $r['description'],
        'allergies' => $r['allergies'],
        'image_url' => $r['image_url'],
        'is_new' => $r['is_new'],
        'is_offer' => $r['is_offer'],
        'is_active' => $r['is_active'],
        'base_price' => $dbp,
        'created_at' => $r['created_at'],
        'updated_at' => $r['updated_at'],
        'category_id' => $r['category_id'],
        'extras' => $pp['extras'] ?? [],
        'sauces' => $pp['sauces'] ?? [],
        'sizes' => $sz,
        'dresses' => $pp['dresses'] ?? [],
        'display_price' => $dp
    ];
}, $raw);

try {
    $drinks = $pdo->query("SELECT * FROM drinks ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_error_markdown("Failed to fetch drinks: " . $e->getMessage(), "Drinks");
    $drinks = [];
}

$cart_total = calculateCartTotal($_SESSION['cart']);
$selT = $_SESSION['selected_tip'] ?? null;
$tip_amount = applyTip($cart_total, $tip_options, $selT);
$cart_total_with_tip = $cart_total + $tip_amount;

try {
    $banners = $pdo->prepare("SELECT * FROM banners WHERE is_active=1 ORDER BY created_at DESC");
    $banners->execute();
    $banners = $banners->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_error_markdown("Failed to fetch banners: " . $e->getMessage(), "Banners");
    $banners = [];
}

try {
    $d0 = date('Y-m-d');
    $active_offers = $pdo->prepare("SELECT * FROM offers WHERE is_active=1 AND (start_date IS NULL OR start_date<=?) AND (end_date IS NULL OR end_date>=?) ORDER BY created_at DESC");
    $active_offers->execute([$d0, $d0]);
    $active_offers = $active_offers->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_error_markdown("Failed to fetch offers: " . $e->getMessage(), "Offers");
    $active_offers = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Meta and Title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Restaurant Delivery</title>

    <!-- Fonts and CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Saira:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/6.6.6/css/flag-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <?php if (!empty($cart_logo) && file_exists($cart_logo)): ?>
        <link rel="icon" type="image/png" href="<?= htmlspecialchars($cart_logo); ?>">
    <?php endif; ?>

    <!-- Inline Styles -->
    <style>
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center
        }

        .promo-banner .carousel-item img {
            height: 400px;
            object-fit: cover
        }

        .offers-section .card-img-top {
            height: 200px;
            object-fit: cover
        }

        .offers-section .card-title {
            font-size: 1.25rem;
            font-weight: 600
        }

        .offers-section .card-text {
            font-size: .95rem;
            color: #555
        }

        @media(max-width:768px) {
            .promo-banner .carousel-item img {
                height: 250px
            }

            .offers-section .card-img-top {
                height: 150px
            }
        }

        .btn.disabled,
        .btn:disabled {
            opacity: .65;
            cursor: not-allowed
        }

        .language-switcher {
            position: absolute;
            top: 10px;
            right: 10px
        }

        .order-summary {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            position: sticky;
            top: 20px
        }

        .order-title {
            margin-bottom: 15px
        }
    </style>

    <!-- Stripe JS -->
    <script src="https://js.stripe.com/v3/"></script>

    <!-- Map Libraries -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v7.3.0/ol.css" type="text/css">
    <script src="https://cdn.jsdelivr.net/npm/ol@v7.3.0/dist/ol.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
    </div>

    <!-- Store Selection Modal -->
    <?php if (!isset($_SESSION['selected_store'])): ?>
        <div class="modal fade" id="storeModal" tabindex="-1" aria-labelledby="storeModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" id="storeSelectionForm">
                        <div class="modal-header">
                            <img src="<?= htmlspecialchars($cart_logo) ?>" alt="Cart Logo" style="width:100%;height:80px;object-fit:cover" onerror="this.src='https://via.placeholder.com/150?text=Logo';">
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <?php if (isset($error_message)): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                            <?php endif; ?>
                            <div class="mb-3">
                                <label for="store_id" class="form-label">Choose a store</label>
                                <select name="store_id" id="store_id" class="form-select" required>
                                    <option value="" selected>Select Store</option>
                                    <?php foreach ($stores as $st): ?>
                                        <option value="<?= htmlspecialchars($st['id']) ?>"><?= htmlspecialchars($st['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="delivery_address" class="form-label">Delivery Address</label>
                                <input type="text" class="form-control" id="delivery_address" name="delivery_address" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Select Location on Map</label>
                                <div id="map" style="height:300px;width:100%"></div>
                            </div>
                            <input type="hidden" id="latitude" name="latitude">
                            <input type="hidden" id="longitude" name="longitude">
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Confirm</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Include jQuery and Bootstrap JS -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

        <!-- Store Modal Script -->
        <script>
            $(function() {
                $('#storeModal').modal('show');
                let map = L.map('map').setView([51.505, -0.09], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(map);
                let marker = null;
                map.on('click', e => {
                    if (marker) map.removeLayer(marker);
                    marker = L.marker(e.latlng).addTo(map);
                    $('#latitude').val(e.latlng.lat);
                    $('#longitude').val(e.latlng.lng);
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${e.latlng.lat}&lon=${e.latlng.lng}`)
                        .then(r => r.json())
                        .then(d => {
                            if (d.display_name) $('#delivery_address').val(d.display_name);
                        });
                });
                $('#delivery_address').on('change', function() {
                    let a = $(this).val();
                    if (a.length > 5) {
                        fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(a)}`)
                            .then(r => r.json())
                            .then(d => {
                                if (d && d.length > 0) {
                                    if (marker) map.removeLayer(marker);
                                    marker = L.marker([d[0].lat, d[0].lon]).addTo(map);
                                    map.setView([d[0].lat, d[0].lon], 13);
                                    $('#latitude').val(d[0].lat);
                                    $('#longitude').val(d[0].lon);
                                }
                            });
                    }
                });
                $('#storeSelectionForm').on('submit', function(e) {
                    if (!$('#delivery_address').val().trim() || !$('#latitude').val() || !$('#longitude').val()) {
                        e.preventDefault();
                        alert('Please provide a valid address and select location on map.');
                    }
                });
            });
        </script>
    <?php endif; ?>

    <!-- Include Other Components -->
    <?php
    $inc = [
        'edit_cart.php',
        'header.php',
        'reservation.php',
        'promotional_banners.php',
        'special_offers.php',
        'checkout.php',
        'order_success.php',
        'cart_modal.php',
        'toast_notifications.php',
        'agb_modal.php',
        'impressum_modal.php',
        'datenschutz_modal.php',
        'ratings.php',
        'store_close.php'
    ];
    foreach ($inc as $f) include $f;
    ?>

    <!-- Checkout Modal -->
    <div class="modal fade" id="checkoutModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="index.php" id="checkoutForm">
                    <div class="modal-body">
                        <!-- Customer Details -->
                        <div class="mb-3">
                            <label for="customer_name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="customer_email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="customer_email" name="customer_email" required>
                        </div>
                        <div class="mb-3">
                            <label for="customer_phone" class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="customer_phone" name="customer_phone" required>
                        </div>
                        <div class="mb-3">
                            <label for="delivery_address" class="form-label">Delivery Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="delivery_address" name="delivery_address" rows="2" required><?= htmlspecialchars($_SESSION['delivery_address'] ?? '') ?></textarea>
                        </div>

                        <!-- Event Scheduling -->
                        <div class="mb-3">
                            <label><input class="form-check-input" type="checkbox" id="event_checkbox" name="is_event" value="1"> Event</label>
                        </div>
                        <div id="event_details" style="display:none">
                            <div class="mb-3">
                                <label for="scheduled_date" class="form-label">Preferred Delivery Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="scheduled_date" name="scheduled_date" min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="mb-3">
                                <label for="scheduled_time" class="form-label">Preferred Delivery Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="scheduled_time" name="scheduled_time">
                            </div>
                        </div>
                        <script>
                            document.getElementById('event_checkbox').addEventListener('change', function() {
                                let ed = document.getElementById('event_details'),
                                    sd = document.getElementById('scheduled_date'),
                                    st = document.getElementById('scheduled_time');
                                if (this.checked) {
                                    ed.style.display = 'block';
                                    sd.required = true;
                                    st.required = true;
                                } else {
                                    ed.style.display = 'none';
                                    sd.required = false;
                                    st.required = false;
                                }
                            });
                        </script>

                        <!-- Payment Method Selection -->
                        <div class="mb-3">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="paymentStripe" value="stripe" required>
                                <label class="form-check-label" for="paymentStripe">Stripe</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="paymentPayPal" value="paypal" required>
                                <label class="form-check-label" for="paymentPayPal">PayPal</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="paymentPickup" value="pickup" required>
                                <label class="form-check-label" for="paymentPickup">Pick-Up</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="paymentCash" value="cash" required>
                                <label class="form-check-label" for="paymentCash">Cash on Delivery</label>
                            </div>
                        </div>

                        <!-- Stripe Payment Section -->
                        <div id="stripe-payment-section" class="mb-3" style="display:none">
                            <label class="form-label">Card</label>
                            <div id="card-element"></div>
                            <div id="card-errors" class="text-danger mt-2"></div>
                        </div>

                        <!-- Tip Selection -->
                        <div class="mb-3">
                            <label for="tip_selection" class="form-label">Select Tip</label>
                            <select class="form-select" id="tip_selection" name="selected_tip">
                                <option value="">No Tip</option>
                                <?php foreach ($tip_options as $t): ?>
                                    <option value="<?= htmlspecialchars($t['id']) ?>" <?= ($selT == $t['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['name']) ?>
                                        <?= !empty($t['percentage']) ? "({$t['percentage']}%)" : (!empty($t['amount']) ? "(+ " . number_format($t['amount'], 2) . "€)" : '') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Order Summary -->
                        <h5>Order Summary</h5>
                        <ul class="list-group mb-3">
                            <?php foreach ($_SESSION['cart'] as $it): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div><?= htmlspecialchars($it['name']) ?> x<?= htmlspecialchars($it['quantity']) ?><?= isset($it['size']) ? " ({$it['size']})" : '' ?></div>
                                    <div><?= number_format($it['total_price'], 2) ?>€</div>
                                </li>
                            <?php endforeach; ?>
                            <?php if ($tip_amount > 0): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>Tip</div>
                                    <div><?= number_format($tip_amount, 2) ?>€</div>
                                </li>
                            <?php endif; ?>
                        </ul>
                        <h4>Total: <?= number_format($cart_total_with_tip, 2) ?>€</h4>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="checkout" value="1">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Place Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs for Categories -->
    <ul class="nav nav-tabs justify-content-center my-4">
        <li class="nav-item"><a class="nav-link <?= ($selC === 0) ? 'active' : '' ?>" href="index.php">All</a></li>
        <?php foreach ($categories as $c): ?>
            <li class="nav-item"><a class="nav-link <?= ($selC === $c['id']) ? 'active' : '' ?>" href="index.php?category_id=<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></a></li>
        <?php endforeach; ?>
    </ul>

    <!-- Main Content -->
    <main class="container my-5">
        <div class="row">
            <div class="col-lg-9">
                <div class="row g-4">
                    <?php if ($products): foreach ($products as $pd): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="position-relative">
                                        <img src="admin/<?= htmlspecialchars($pd['image_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($pd['name']) ?>" onerror="this.src='https://via.placeholder.com/600x400?text=Product+Image';">
                                        <?php if ($pd['is_new'] || $pd['is_offer']): ?>
                                            <span class="badge <?= $pd['is_new'] ? 'bg-success' : 'bg-warning text-dark' ?> position-absolute <?= $pd['is_offer'] ? 'top-40' : 'top-0' ?> end-0 m-2">
                                                <?= $pd['is_new'] ? 'New' : 'Offer' ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title"><?= htmlspecialchars($pd['name']) ?></h5>
                                        <p class="card-text"><?= htmlspecialchars($pd['description']) ?></p>
                                        <?php if (!empty($pd['allergies'])): ?>
                                            <p class="card-text text-danger"><strong>Allergies:</strong> <?= htmlspecialchars($pd['allergies']) ?></p>
                                        <?php endif; ?>
                                        <div class="mt-auto">
                                            <strong><?= !empty($pd['sizes']) ? "From " . number_format($pd['display_price'], 2) . "€" : number_format($pd['display_price'], 2) . "€" ?></strong>
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addToCartModal<?= $pd['id'] ?>">
                                                <i class="bi bi-cart-plus me-1"></i>Add to Cart
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Add to Cart Modal -->
                            <div class="modal fade" id="addToCartModal<?= $pd['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-xl modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" action="index.php">
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-4">
                                                        <img src="<?= htmlspecialchars($pd['image_url']) ?>" class="img-fluid rounded shadow" alt="<?= htmlspecialchars($pd['name']) ?>" onerror="this.src='https://via.placeholder.com/600x400?text=Product+Image';">
                                                    </div>
                                                    <div class="col-8">
                                                        <h2 class="text-uppercase"><b><?= htmlspecialchars($pd['name']) ?></b></h2>
                                                        <hr style="width:10%;border:2px solid black;border-radius:5px;">
                                                        <?php if (empty($pd['sizes'])): ?>
                                                            <h3 style="color:#d3b213"><b><?= number_format($pd['base_price'], 2) ?>€</b></h3>
                                                        <?php else: ?>
                                                            <h5>Select a size to see final price</h5>
                                                            <div class="mb-3">
                                                                <label class="form-label">Select Size:</label>
                                                                <select class="form-select" name="size" required>
                                                                    <option value="">Choose a size</option>
                                                                    <?php foreach ($pd['sizes'] as $sz): ?>
                                                                        <option value="<?= htmlspecialchars($sz['size']) ?>">
                                                                            <?= htmlspecialchars($sz['size']) ?> (<?= number_format($pd['base_price'] + (float)$sz['price'], 2) ?>€)
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Extras, Sauces, Dresses -->
                                                        <?php foreach (['extras', 'sauces', 'dresses'] as $option): ?>
                                                            <?php if (!empty($pd[$option])): ?>
                                                                <div class="mb-3">
                                                                    <label class="form-label"><?= ucfirst($option) ?></label>
                                                                    <div>
                                                                        <?php foreach ($pd[$option] as $item): ?>
                                                                            <div class="form-check">
                                                                                <input class="form-check-input" type="checkbox" name="<?= $option ?>[]" value="<?= htmlspecialchars($item['name']) ?>" id="<?= $option . $pd['id'] . '_' . htmlspecialchars($item['name']) ?>">
                                                                                <label class="form-check-label" for="<?= $option . $pd['id'] . '_' . htmlspecialchars($item['name']) ?>">
                                                                                    <?= htmlspecialchars($item['name']) ?> (+<?= number_format((float)$item['price'], 2) ?>€)
                                                                                </label>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>

                                                        <!-- Drinks Selection -->
                                                        <?php if (!empty($drinks)): ?>
                                                            <div class="mb-3">
                                                                <label class="form-label">Drinks</label>
                                                                <select class="form-select" name="drink">
                                                                    <option value="">Choose a drink</option>
                                                                    <?php foreach ($drinks as $dk): ?>
                                                                        <option value="<?= htmlspecialchars($dk['id']) ?>">
                                                                            <?= htmlspecialchars($dk['name']) ?> (+<?= number_format((float)$dk['price'], 2) ?>€)
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Quantity and Instructions -->
                                                        <div class="mb-3">
                                                            <label class="form-label">Quantity</label>
                                                            <input type="number" class="form-control" name="quantity" value="1" min="1" max="99" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Special Instructions</label>
                                                            <textarea class="form-control" name="special_instructions" rows="2"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <input type="hidden" name="product_id" value="<?= $pd['id'] ?>">
                                                <input type="hidden" name="add_to_cart" value="1">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Add to Cart</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Allergies Modal -->
                            <?php if ($pd['allergies']): ?>
                                <div class="modal fade" id="allergiesModal<?= $pd['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Allergies for <?= htmlspecialchars($pd['name']) ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <ul class="list-group">
                                                    <?php foreach (array_map('trim', explode(',', $pd['allergies'])) as $alg): ?>
                                                        <li class="list-group-item"><?= htmlspecialchars($alg) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach;
                    else: ?>
                        <p class="text-center">No products available.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Order Summary Sidebar -->
            <div class="col-lg-3">
                <div class="order-summary">
                    <h2 class="order-title">YOUR ORDER</h2>
                    <?php if (!empty($cart_logo) && file_exists($cart_logo)): ?>
                        <div class="mb-3 text-center">
                            <img src="<?= htmlspecialchars($cart_logo) ?>" alt="Cart Logo" class="img-fluid" onerror="this.src='https://via.placeholder.com/150?text=Logo';">
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($cart_description)): ?>
                        <div class="mb-4" id="cart-description">
                            <p><?= nl2br(htmlspecialchars($cart_description)) ?></p>
                            <?= htmlspecialchars($_SESSION['store_name'] ?? '') ?>
                        </div>
                    <?php endif; ?>
                    <p class="card-text">
                        <strong>Working hours:</strong> <?= !empty($notification['message']) ? htmlspecialchars($notification['message']) : "10:00 - 21:45" ?><br>
                        <strong>Minimum order:</strong> <?= htmlspecialchars($minimum_order) ?> €
                    </p>
                    <?php if (!empty($_SESSION['delivery_address'])): ?>
                        <div class="mb-3">
                            <h5>Delivery Address</h5>
                            <p><?= htmlspecialchars($_SESSION['delivery_address']) ?></p>
                            <a href="https://www.openstreetmap.org/?mlat=<?= htmlspecialchars($_SESSION['latitude']) ?>&mlon=<?= htmlspecialchars($_SESSION['longitude']) ?>#map=18/<?= htmlspecialchars($_SESSION['latitude']) ?>/<?= htmlspecialchars($_SESSION['longitude']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">View on Map</a>
                        </div>
                    <?php endif; ?>

                    <!-- Cart Items -->
                    <div id="cart-items">
                        <?php if (!empty($_SESSION['cart'])): ?>
                            <ul class="list-group mb-3">
                                <?php foreach ($_SESSION['cart'] as $i => $it): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6><?= htmlspecialchars($it['name']) ?><?= isset($it['size']) ? " ({$it['size']})" : '' ?> x<?= htmlspecialchars($it['quantity']) ?></h6>
                                            <?php if (!empty($it['extras'])): ?>
                                                <ul>
                                                    <?php foreach ($it['extras'] as $ex): ?>
                                                        <li><?= htmlspecialchars($ex['name'] ?? '') ?> (+<?= number_format((float)($ex['price'] ?? 0.0), 2) ?>€)</li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                            <?php if (!empty($it['drink'])): ?>
                                                <p>Drink: <?= htmlspecialchars($it['drink']['name'] ?? '') ?> (+<?= number_format((float)($it['drink']['price'] ?? 0.0), 2) ?>€)</p>
                                            <?php endif; ?>
                                            <?php if (!empty($it['sauces'])): ?>
                                                <p>Sauces: <?= htmlspecialchars(implode(', ', array_column($it['sauces'], 'name'))) ?> (+<?= number_format(array_reduce($it['sauces'], fn($c, $sx) => $c + (float)($sx['price'] ?? 0.0), 0.0), 2) ?>€)</p>
                                            <?php endif; ?>
                                            <?php if (!empty($it['dresses'])): ?>
                                                <p>Dresses: <?= htmlspecialchars(implode(', ', array_column($it['dresses'], 'name'))) ?> (+<?= number_format(array_reduce($it['dresses'], fn($c, $dx) => $c + (float)($dx['price'] ?? 0.0), 0.0), 2) ?>€)</p>
                                            <?php endif; ?>
                                            <?php if (!empty($it['special_instructions'])): ?>
                                                <p><em>Instructions:</em> <?= htmlspecialchars($it['special_instructions']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong><?= number_format($it['total_price'], 2) ?>€</strong><br>
                                            <a href="index.php?remove=<?= $i ?>" class="btn btn-sm btn-danger mt-2"><i class="bi bi-trash"></i> Remove</a>
                                            <button type="button" class="btn btn-sm btn-secondary mt-2" data-bs-toggle="modal" data-bs-target="#editCartModal<?= $i ?>"><i class="bi bi-pencil-square"></i> Edit</button>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if ($tip_amount > 0): ?>
                                <ul class="list-group mb-3">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>Tip</div>
                                        <div><?= number_format($tip_amount, 2) ?>€</div>
                                    </li>
                                </ul>
                            <?php endif; ?>
                            <h4>Total: <?= number_format($cart_total_with_tip, 2) ?>€</h4>
                            <button class="btn btn-success w-100 mt-3" data-bs-toggle="modal" data-bs-target="#checkoutModal">Proceed to Checkout</button>
                        <?php else: ?>
                            <p>Your cart is empty.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-light text-muted pt-5 pb-4">
        <div class="container">
            <div class="row">
                <!-- Logo and Description -->
                <div class="col-md-3">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">
                        <img src="https://images.unsplash.com/photo-1550547660-d9450f859349?crop=entropy&cs=tinysrgb&fit=crop&fm=jpg&h=40&w=40" alt="Logo" width="40" height="40" class="me-2">Restaurant
                    </h5>
                    <p>Experience the finest dining with us.</p>
                </div>
                <!-- Quick Links -->
                <div class="col-md-2">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-reset text-decoration-none">Home</a></li>
                        <li><a href="#menu" class="text-reset text-decoration-none">Menu</a></li>
                        <li><a href="#about" class="text-reset text-decoration-none">About Us</a></li>
                        <li><a href="#contact" class="text-reset text-decoration-none">Contact</a></li>
                    </ul>
                </div>
                <!-- Legal -->
                <div class="col-md-3">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Legal</h5>
                    <ul class="list-unstyled">
                        <li><button type="button" class="btn btn-link text-reset p-0" data-bs-toggle="modal" data-bs-target="#agbModal">AGB</button></li>
                        <li><button type="button" class="btn btn-link text-reset p-0" data-bs-toggle="modal" data-bs-target="#impressumModal">Impressum</button></li>
                        <li><button type="button" class="btn btn-link text-reset p-0" data-bs-toggle="modal" data-bs-target="#datenschutzModal">Datenschutz</button></li>
                    </ul>
                </div>
                <!-- Contact Information -->
                <div class="col-md-4">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Contact Us</h5>
                    <p><i class="bi bi-geo-alt-fill me-2"></i> 123 Main Street</p>
                    <p><i class="bi bi-envelope-fill me-2"></i> info@restaurant.com</p>
                    <p><i class="bi bi-telephone-fill me-2"></i> +1 234 567 890</p>
                    <p><i class="bi bi-clock-fill me-2"></i> Mon - Sun: 10:00 - 22:00</p>
                </div>
            </div>
            <hr class="mb-4">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <p>© <?= date('Y') ?> Restaurant. All rights reserved.</p>
                </div>
                <div class="col-md-5 text-end">
                    <div class="social-media">
                        <?php foreach (['facebook_link', 'twitter_link', 'instagram_link', 'linkedin_link', 'youtube_link'] as $social): ?>
                            <?php if (!empty($social_links[$social])): ?>
                                <a href="<?= htmlspecialchars($social_links[$social]) ?>" target="_blank"><i class="bi bi-<?= explode('_', $social)[0] ?>"></i></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Main JS -->
    <script>
        $(function() {
            // Hide Loading Overlay
            $('#loading-overlay').fadeOut('slow');

            // Toast Notifications
            $('.toast').toast('show');

            // Reservation Form Submission
            $('#reservationForm').submit(function(e) {
                e.preventDefault();
                let $f = $(this),
                    $b = $f.find('button[type="submit"]').prop('disabled', true);
                $.post('submit_reservation.php', $f.serialize(), r => {
                    $('#reservationModal').modal('hide');
                    $f[0].reset();
                    Swal.fire({
                        icon: r.status === 'success' ? 'success' : 'error',
                        title: r.status === 'success' ? 'Reservation Successful' : 'Error',
                        text: r.message,
                        timer: r.status === 'success' ? 3000 : null,
                        showConfirmButton: r.status !== 'success'
                    });
                }, 'json').fail(() => Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred. Please try again later.'
                })).always(() => {
                    $b.prop('disabled', false);
                });
            });

            // Order Success Redirect
            <?php if (isset($_GET['order']) && $_GET['order'] === 'success'): ?>
                setTimeout(() => {
                    $('.modal').modal('hide');
                    window.location.href = 'index.php';
                }, 5000);
            <?php endif; ?>

            // Store Status Check
            const updateStoreStatus = () => {
                $.getJSON('check_store_status.php', d => {
                    let $c = $('.btn-add-to-cart, .btn-checkout');
                    if (d.is_closed) {
                        $('#storeClosedModalLabel').text(d.notification.title);
                        $('#storeClosedMessage').text(d.notification.message);
                        $('#storeClosedEndTime').text(`We will reopen on ${new Date(d.notification.end_datetime).toLocaleString()}.`);
                        new bootstrap.Modal('#storeClosedModal', {
                            backdrop: 'static',
                            keyboard: false
                        }).show();
                        $c.prop('disabled', true).addClass('disabled').attr('title', 'Store is closed');
                    } else {
                        let m = bootstrap.Modal.getInstance($('#storeClosedModal')[0]);
                        if (m) m.hide();
                        $c.prop('disabled', false).removeClass('disabled').attr('title', '');
                    }
                }).fail(e => console.error('Error store status:', e));
            };
            updateStoreStatus();
            setInterval(updateStoreStatus, 60000);

            // Stripe Integration
            const stripe = Stripe('pk_test_51QByfJE4KNNCb6nuSnWLZP9JXlW84zG9DnOrQDTHQJvus9D8A8vOA85S4DfRlyWgN0rxa2hHzjppchnrmhyZGflx00B2kKlxym'); // Replace with your Stripe Publishable Key
            const card = stripe.elements().create('card');
            card.mount('#card-element');

            $('input[name="payment_method"]').change(() => {
                $('#stripe-payment-section').toggle($('#paymentStripe').is(':checked'));
            });

            $('#checkoutForm').submit(async function(e) {
                if ($('#paymentStripe').is(':checked')) {
                    e.preventDefault();
                    let $btn = $(this).find('button[type="submit"]').prop('disabled', true);
                    try {
                        const {
                            paymentMethod,
                            error
                        } = await stripe.createPaymentMethod({
                            type: 'card',
                            card: card,
                            billing_details: {
                                name: $('#customer_name').val(),
                                email: $('#customer_email').val(),
                                phone: $('#customer_phone').val(),
                                address: {
                                    line1: $('#delivery_address').val()
                                }
                            }
                        });
                        if (error) {
                            $('#card-errors').text(error.message);
                            $btn.prop('disabled', false);
                            return;
                        }
                        $(this).append($('<input>', {
                            type: 'hidden',
                            name: 'stripe_payment_method',
                            value: paymentMethod.id
                        }));
                        const fd = new URLSearchParams(new FormData(this)).toString();
                        const res = await fetch('index.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: fd
                        }).then(x => x.json());
                        if (res.requires_action) {
                            const {
                                error: ce,
                                paymentIntent: pi
                            } = await stripe.confirmCardPayment(res.payment_intent_client_secret);
                            if (ce) $('#card-errors').text(ce.message);
                            else if (pi.status === 'succeeded') window.location.href = res.redirect_url;
                        } else if (res.success) {
                            window.location.href = res.redirect_url;
                        }
                    } catch (e) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Payment failed. Try again.'
                        });
                    } finally {
                        $btn.prop('disabled', false);
                    }
                }
            });
        });
    </script>
</body>

</html>
<?php ob_end_flush(); ?>