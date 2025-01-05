<?php


ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require 'vendor/autoload.php';

use Stripe\Stripe;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Amount;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Api\PaymentExecution;

Stripe::setApiKey("sk_test_XXX");
$paypal = new ApiContext(new OAuthTokenCredential(
    'AfbPMlmPT4z37DRzH886cPd1AggGZjz-L_LnVJxx_Odv7AB82AQ9CIz8P_s-5cjgLf-Ndgpng0NLAiWr',
    'EKbX-h3EwnMlRoAyyGBCFi2370doQi06hO6iOiQJsQ1gDnpvTrwYQIyTG2MxG6H1vVuWpz_Or76JTThi'
));
$paypal->setConfig(['mode' => 'sandbox']);
session_start();
require 'includes/db_connect.php';
$_SESSION['applied_coupon'] = $_SESSION['applied_coupon'] ?? null;
$showChangeAddressModal = isset($_GET['action']) && $_GET['action'] === 'change_address';
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
define('ERROR_LOG_FILE', __DIR__ . '/errors.md');
function log_error_markdown($message, $context = '')
{
    $t = date('Y-m-d H:i:s');
    $entry = "### [$t] Error\n\n**Message:** " . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n\n";
    if ($context) {
        $entry .= "**Context:** " . htmlspecialchars($context, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n\n";
    }
    $entry .= "---\n\n";
    file_put_contents(ERROR_LOG_FILE, $entry, FILE_APPEND | LOCK_EX);
}
set_exception_handler(function ($e) {
    log_error_markdown("Uncaught Exception: " . $e->getMessage(), "File: {$e->getFile()} Line: {$e->getLine()}");
    header("Location: index.php?error=unknown_error");
    exit;
});
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    throw new ErrorException($message, 0, $severity, $file, $line);
});
include 'settings_fetch.php';
$_SESSION['cart'] = $_SESSION['cart'] ?? [];
$is_closed = false;
$notification = [];
$selected_store_id = $_SESSION['selected_store'] ?? null;
$main_store = null;
if ($selected_store_id) {
    $st = $pdo->prepare("SELECT * FROM stores WHERE id=? AND is_active=1");
    $st->execute([$selected_store_id]);
    $main_store = $st->fetch(PDO::FETCH_ASSOC);
}
if ($main_store) {
    $d = @json_decode($main_store['work_schedule'] ?? '', true);
    if (!is_array($d)) $d = [];
    $todayStr = date('Y-m-d');
    $todayName = date('l');
    $nowTime = date('H:i');
    $holidays = $d['holidays'] ?? [];
    foreach ($holidays as $h) {
        if (!empty($h['date']) && $h['date'] === $todayStr) {
            $is_closed = true;
            $notification = ['title' => 'Store Closed', 'message' => $h['desc'] ?? "Closed for holiday."];
            break;
        }
    }
    if (!$is_closed) {
        $days = $d['days'] ?? [];
        if (isset($days[$todayName])) {
            $start = $days[$todayName]['start'] ?? '';
            $end   = $days[$todayName]['end']   ?? '';
            if (!$start || !$end) {
                $is_closed = true;
                $notification = ['title' => 'Store Closed', 'message' => "Closed today ($todayName)."];
            } else {
                if ($nowTime < $start || $nowTime > $end) {
                    $is_closed = true;
                    $notification = ['title' => 'Store Closed', 'message' => "Operating hours today: $start - $end"];
                }
            }
        } else {
            $is_closed = true;
            $notification = ['title' => 'Store Closed', 'message' => "No schedule defined for $todayName."];
        }
    }
} else {
    $is_closed = true;
    $notification = ['title' => 'No Store Selected', 'message' => 'Please select a store before ordering.'];
}
try {
    $tip_options = $pdo->query("SELECT * FROM tips WHERE is_active=1 ORDER BY percentage ASC, amount ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_error_markdown("Failed to fetch tips: " . $e->getMessage(), "Fetching Tips");
    $tip_options = [];
}
if (isset($_GET['select_tip'])) {
    $tid = (int)$_GET['select_tip'];
    $valid = in_array($tid, array_column($tip_options, 'id'), true) || $tid === 0;
    if ($valid) {
        $_SESSION['selected_tip'] = $tid === 0 ? null : $tid;
        header("Location: index.php");
        exit;
    }
    header("Location: index.php?error=invalid_tip");
    exit;
}
function fetchProduct($pdo, $pid)
{
    $s = $pdo->prepare("SELECT * FROM products WHERE id=:p AND is_active=1");
    $s->execute(['p' => $pid]);
    $p = $s->fetch(PDO::FETCH_ASSOC);
    if (!$p) return null;
    $props = json_decode($p['properties'] ?? '{}', true) ?: [];
    return [
        'sizes'      => $props['sizes']      ?? [],
        'extras'     => $props['extras']     ?? [],
        'sauces'     => $props['sauces']     ?? [],
        'dresses'    => $props['dresses']    ?? [],
        'base_price' => (float)($props['base_price'] ?? $p['base_price']),
        'name'       => $p['name'],
        'description' => $p['description'],
        'image_url'  => $p['image_url'],
        'properties' => $p['properties'] ?? ''
    ];
}
function calculateCartTotal($cart)
{
    return array_reduce($cart, function ($acc, $item) {
        return $acc + ($item['total_price'] ?? 0);
    }, 0);
}
function applyTip($cartTotal, $tips, $selectedTipId)
{
    if (!$selectedTipId) return 0;
    foreach ($tips as $tip) {
        if ($tip['id'] == $selectedTipId) {
            if (!empty($tip['percentage'])) {
                return $cartTotal * ((float)$tip['percentage'] / 100);
            } else {
                return (float)($tip['amount'] ?? 0);
            }
        }
    }
    return 0;
}
function haversineDist($lat1, $lon1, $lat2, $lon2)
{
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    return $R * 2 * asin(
        sqrt(
            sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2
        )
    );
}
function computeShipping($store, $lat, $lon)
{
    if (empty($lat) || empty($lon) || ($store['shipping_calculation_mode'] === 'pickup')) return 0;
    $mode     = $store['shipping_calculation_mode'] ?? 'radius';
    $baseFee  = (float)($store['shipping_fee_base']    ?? 0);
    $perKmFee = (float)($store['shipping_fee_per_km']  ?? 0);
    $radius   = (float)($store['shipping_distance_radius'] ?? 0);
    $postalZones = json_decode($store['postal_code_zones'] ?? '{}', true) ?: [];
    if (in_array($mode, ['postal', 'both'])) {
        $address = $_SESSION['delivery_address'] ?? '';
        if (isset($postalZones[$address])) {
            return max((float)$postalZones[$address], 0);
        }
    }
    $distance = haversineDist(
        (float)$store['store_lat'],
        (float)$store['store_lng'],
        (float)$lat,
        (float)$lon
    );
    if ($distance > $radius && $mode !== 'postal') return 0;
    return max($baseFee + ($distance * $perKmFee), 0);
}
function applyCoupon($pdo, $code, $cartTotal)
{
    $r = ['ok' => false, 'error' => '', 'coupon' => null];
    $s = $pdo->prepare("SELECT * FROM coupons WHERE code=? AND is_active=1 LIMIT 1");
    $s->execute([$code]);
    $c = $s->fetch(PDO::FETCH_ASSOC);
    if (!$c) {
        $r['error'] = "Invalid or inactive coupon code.";
        return $r;
    }
    $today = date('Y-m-d');
    if (!empty($c['start_date']) && $today < $c['start_date']) {
        $r['error'] = "This coupon is not valid yet.";
        return $r;
    }
    if (!empty($c['end_date']) && $today > $c['end_date']) {
        $r['error'] = "This coupon has expired.";
        return $r;
    }
    $discountValue = ($c['discount_type'] === 'percentage')
        ? $cartTotal * ($c['discount_value'] / 100)
        : $c['discount_value'];
    $r['ok'] = true;
    $r['coupon'] = [
        'code'          => $c['code'],
        'discount_type' => $c['discount_type'],
        'discount_value' => $discountValue
    ];
    return $r;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['apply_coupon'])) {
        $cc = trim($_POST['coupon_code'] ?? '');
        $st = calculateCartTotal($_SESSION['cart']);
        $res = applyCoupon($pdo, $cc, $st);
        if ($res['ok']) {
            $_SESSION['applied_coupon'] = $res['coupon'];
            header("Location: index.php?coupon=applied");
            exit;
        }
        header("Location: index.php?coupon_error=" . urlencode($res['error']));
        exit;
    }
    if (isset($_POST['store_id'])) {
        $sid = (int)$_POST['store_id'];
        $st  = $pdo->prepare("SELECT * FROM stores WHERE id=? AND is_active=1");
        $st->execute([$sid]);
        if ($store = $st->fetch(PDO::FETCH_ASSOC)) {
            $da = trim($_POST['delivery_address'] ?? '');
            $la = trim($_POST['latitude']         ?? '');
            $lo = trim($_POST['longitude']        ?? '');
            if (!$da || !$la || !$lo || !is_numeric($la) || !is_numeric($lo)) {
                $error_message = "Please provide a valid delivery address & location.";
            } else {
                $_SESSION['selected_store'] = $sid;
                $_SESSION['store_name']     = $store['name'];
                $_SESSION['delivery_address'] = $da;
                $_SESSION['latitude']       = $la;
                $_SESSION['longitude']      = $lo;
                $_SESSION['minimum_order']  = $store['minimum_order'];
                header("Location: index.php");
                exit;
            }
        } else {
            $error_message = "Selected store not available.";
        }
    }
    if (isset($_POST['add_to_cart'])) {
        $pid = (int)$_POST['product_id'];
        $qty = max(1, (int)$_POST['quantity']);
        $sz  = $_POST['size']   ?? null;
        $pEx = $_POST['extras'] ?? [];
        $pSa = $_POST['sauces'] ?? [];
        $pDr = $_POST['dresses'] ?? [];
        $drink_id = isset($_POST['drink']) ? (int)$_POST['drink'] : null;
        $spec = trim($_POST['special_instructions'] ?? '');
        $pr   = fetchProduct($pdo, $pid);
        if (!$pr) {
            header("Location: index.php?error=invalid_product");
            exit;
        }
        $sel_size = null;
        if ($sz) {
            foreach ($pr['sizes'] as $sdef) {
                if ($sdef['size'] === $sz) {
                    $sel_size = $sdef;
                    break;
                }
            }
            if (!$sel_size) {
                header("Location: index.php?error=invalid_size");
                exit;
            }
        }
        $bp = $pr['base_price'] + ($sel_size['price'] ?? 0);
        $et = 0;
        $st = 0;
        $dt = 0;
        $drt = 0;
        $exs = [];
        $sas = [];
        $drs = [];
        $drnk = null;
        $theseExtras = $sel_size ? ($sel_size['extras'] ?? []) : ($pr['extras'] ?? []);
        $theseSauces = $sel_size ? ($sel_size['sauces'] ?? []) : ($pr['sauces'] ?? []);
        foreach ($theseExtras as $e) {
            $qx = (int)($pEx[$e['name']] ?? 0);
            if ($qx > 0) {
                $et += $e['price'] * $qx;
                $exs[] = ['name' => $e['name'], 'price' => $e['price'], 'quantity' => $qx];
            }
        }
        foreach ($theseSauces as $sa) {
            $qx = (int)($pSa[$sa['name']] ?? 0);
            if ($qx > 0) {
                $st += $sa['price'] * $qx;
                $sas[] = ['name' => $sa['name'], 'price' => $sa['price'], 'quantity' => $qx];
            }
        }
        foreach ($pr['dresses'] as $d) {
            $qx = (int)($pDr[$d['name']] ?? 0);
            if ($qx > 0) {
                $dt += $d['price'] * $qx;
                $drs[] = ['name' => $d['name'], 'price' => $d['price'], 'quantity' => $qx];
            }
        }
        if ($drink_id) {
            $stD = $pdo->prepare("SELECT * FROM drinks WHERE id=?");
            $stD->execute([$drink_id]);
            if ($dk = $stD->fetch(PDO::FETCH_ASSOC)) {
                $drt  = (float)($dk['price']);
                $drnk = $dk;
            }
        }
        $unit = $bp + $et + $st + $dt + $drt;
        $total = $unit * $qty;
        $cart_item = [
            'product_id'          => $pid,
            'name'                => $pr['name'],
            'description'         => $pr['description'],
            'image_url'           => $pr['image_url'],
            'size'                => $sz,
            'size_price'          => $sel_size['price'] ?? 0,
            'extras'              => $exs,
            'sauces'              => $sas,
            'dresses'             => $drs,
            'drink'               => $drnk,
            'special_instructions' => $spec,
            'quantity'            => $qty,
            'unit_price'          => $unit,
            'total_price'         => $total
        ];
        foreach ($_SESSION['cart'] as $i => $existingItem) {
            $sameDrink = (($existingItem['drink']['id'] ?? null) == ($cart_item['drink']['id'] ?? null));
            if (
                $existingItem['product_id'] == $cart_item['product_id'] &&
                $existingItem['size']       == $cart_item['size']       &&
                $sameDrink &&
                $existingItem['special_instructions'] == $cart_item['special_instructions'] &&
                $existingItem['extras'] == $cart_item['extras'] &&
                $existingItem['sauces'] == $cart_item['sauces'] &&
                $existingItem['dresses'] == $cart_item['dresses']
            ) {
                $_SESSION['cart'][$i]['quantity']    += $qty;
                $_SESSION['cart'][$i]['total_price'] += $total;
                header("Location: index.php?added=1");
                exit;
            }
        }
        $_SESSION['cart'][] = $cart_item;
        header("Location: index.php?added=1");
        exit;
    }
    if (isset($_POST['remove'])) {
        $ri = (int)$_POST['remove'];
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
    if (isset($_POST['update_cart'])) {
        $ii = (int)$_POST['item_index'];
        if (!isset($_SESSION['cart'][$ii])) {
            log_error_markdown("Invalid cart update request: $ii", "Updating Cart");
            header("Location: index.php?error=invalid_update");
            exit;
        }
        $qty = max(1, (int)$_POST['quantity']);
        $sz  = $_POST['size']   ?? null;
        $pEx = $_POST['extras'] ?? [];
        $pSa = $_POST['sauces'] ?? [];
        $pDr = $_POST['dresses'] ?? [];
        $drink_id = isset($_POST['drink']) ? (int)$_POST['drink'] : null;
        $spec = trim($_POST['special_instructions'] ?? '');
        $pid  = $_SESSION['cart'][$ii]['product_id'];
        $pr   = fetchProduct($pdo, $pid);
        if (!$pr) {
            log_error_markdown("Product not found or inactive ID:$pid", "Updating Cart");
            header("Location: index.php?error=invalid_update");
            exit;
        }
        $props = json_decode($pr['properties'] ?? '{}', true) ?? [];
        $base = (float)($props['base_price'] ?? $pr['base_price']);
        $zopt = $props['sizes']  ?? $pr['sizes'];
        $xopt = $props['extras'] ?? $pr['extras'];
        $sopt = $props['sauces'] ?? $pr['sauces'];
        $dopt = $props['dresses'] ?? $pr['dresses'];
        $sel_size = null;
        $szp = 0;
        if ($sz) {
            foreach ($zopt as $z) {
                if (($z['size'] ?? '') === $sz) {
                    $sel_size = $z;
                    $szp = (float)($z['price'] ?? 0);
                    $xopt = $z['extras']  ?? $xopt;
                    $sopt = $z['sauces']  ?? $sopt;
                    $dopt = $z['dresses'] ?? $dopt;
                    break;
                }
            }
            if (!$sel_size) {
                log_error_markdown("Invalid size: $sz for Product $pid", "Updating Cart");
                header("Location: index.php?error=invalid_size");
                exit;
            }
        }
        $exTot = 0;
        $saTot = 0;
        $drTot = 0;
        $drinkTot = 0;
        $sel_ex = [];
        $sel_sa = [];
        $sel_dr = [];
        foreach ($xopt as $xo) {
            $nm  = $xo['name'];
            $prc = (float)$xo['price'];
            if (isset($pEx[$nm])) {
                $qx = (int)$pEx[$nm];
                if ($qx > 0) {
                    $exTot += $prc * $qx;
                    $sel_ex[] = ['name' => $nm, 'price' => $prc, 'quantity' => $qx];
                }
            }
        }
        foreach ($sopt as $sx) {
            $nm  = $sx['name'];
            $prc = (float)$sx['price'];
            if (isset($pSa[$nm])) {
                $qx = (int)$pSa[$nm];
                if ($qx > 0) {
                    $saTot += $prc * $qx;
                    $sel_sa[] = ['name' => $nm, 'price' => $prc, 'quantity' => $qx];
                }
            }
        }
        foreach ($dopt as $dx) {
            $nm = $dx['name'];
            $prc = (float)$dx['price'];
            if (isset($pDr[$nm])) {
                $qx = (int)$pDr[$nm];
                if ($qx > 0) {
                    $drTot += $prc * $qx;
                    $sel_dr[] = ['name' => $nm, 'price' => $prc, 'quantity' => $qx];
                }
            }
        }
        if ($drink_id) {
            $s = $pdo->prepare("SELECT * FROM drinks WHERE id=?");
            $s->execute([$drink_id]);
            if ($dk = $s->fetch(PDO::FETCH_ASSOC)) {
                $drinkTot = (float)($dk['price'] ?? 0);
                $drink_details = $dk;
            }
        }
        $u = $base + $szp + $exTot + $saTot + $drTot + $drinkTot;
        $tp = $u * $qty;
        $_SESSION['cart'][$ii] = [
            'product_id'          => $pr['id'],
            'name'                => $pr['name'],
            'description'         => $pr['description'],
            'image_url'           => $pr['image_url'],
            'size'                => $sel_size['size'] ?? null,
            'size_price'          => $szp,
            'extras'              => $sel_ex,
            'sauces'              => $sel_sa,
            'dresses'             => $sel_dr,
            'drink'               => $drink_details ?? null,
            'special_instructions' => $spec,
            'quantity'            => $qty,
            'unit_price'          => $u,
            'total_price'         => $tp
        ];
        header("Location: index.php?updated=1");
        exit;
    }
    if (isset($_POST['checkout'])) {
        if (empty($_SESSION['cart'])) {
            header("Location: index.php?error=empty_cart");
            exit;
        }
        // if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        //     header("Location: index.php?error=invalid_csrf_token");
        //     exit;
        // }
        $store_id = $_SESSION['selected_store'] ?? null;
        $cn = trim($_POST['customer_name']  ?? '');
        $ce = trim($_POST['customer_email'] ?? '');
        $cp = trim($_POST['customer_phone'] ?? '');
        $da = trim($_POST['delivery_address'] ?? '');
        $stid = $_POST['selected_tip'] ?? null;
        $ev = (isset($_POST['is_event']) && $_POST['is_event'] == '1');
        $sd = $_POST['scheduled_date'] ?? null;
        $st_time = $_POST['scheduled_time'] ?? null;
        if ($ev) {
            $sdt = DateTime::createFromFormat('Y-m-d H:i', "$sd $st_time");
            if (!$sdt || $sdt < new DateTime()) {
                header("Location: index.php?error=invalid_scheduled_datetime");
                exit;
            }
        }
        $pm = $_POST['payment_method'] ?? '';
        if (!in_array($pm, ['stripe', 'paypal', 'pickup', 'cash'])) {
            header("Location: index.php?error=invalid_payment_method");
            exit;
        }
        if (!$cn || !$ce || !$cp || !$da) {
            header("Location: index.php?error=invalid_order_details");
            exit;
        }
        $cart_total = calculateCartTotal($_SESSION['cart']);
        $tip_amount = applyTip($cart_total, $tip_options, $stid);
        $shipping = 0;
        if ($pm !== 'pickup' && $main_store) {
            $shipping = computeShipping($main_store, $_SESSION['latitude'] ?? 0, $_SESSION['longitude'] ?? 0);
        }
        $free_threshold = (float)($main_store['shipping_free_threshold'] ?? 9999);
        if ($cart_total >= $free_threshold) $shipping = 0;
        $coupon_discount = 0;
        if (!empty($_SESSION['applied_coupon'])) {
            $coupon_discount = $_SESSION['applied_coupon']['discount_value'];
            if ($coupon_discount > $cart_total) $coupon_discount = $cart_total;
        }
        $total = max($cart_total + $tip_amount + $shipping - $coupon_discount, 0);
        $order_details = json_encode([
            'items'         => $_SESSION['cart'],
            'latitude'      => $_SESSION['latitude']  ?? null,
            'longitude'     => $_SESSION['longitude'] ?? null,
            'tip_id'        => $stid,
            'tip_amount'    => $tip_amount,
            'store_id'      => $store_id,
            'is_event'      => $ev,
            'scheduled_date' => $sd,
            'scheduled_time' => $st_time,
            'shipping_fee'  => $shipping,
            'coupon_code'   => $_SESSION['applied_coupon']['code'] ?? null,
            'coupon_discount' => $coupon_discount
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO orders(user_id,customer_name,customer_email,customer_phone,delivery_address,total_amount,status_id,tip_id,tip_amount,scheduled_date,scheduled_time,payment_method,store_id,order_details,coupon_code,coupon_discount) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                null,
                $cn,
                $ce,
                $cp,
                $da,
                $total,
                2,
                $stid,
                $tip_amount,
                $sd,
                $st_time,
                $pm,
                $store_id,
                $order_details,
                $_SESSION['applied_coupon']['code'] ?? null,
                $coupon_discount
            ]);
            $oid = $pdo->lastInsertId();
            if (in_array($pm, ['stripe', 'paypal'])) {
                if ($pm === 'stripe') {
                    $spm = $_POST['stripe_payment_method'] ?? '';
                    if (!$spm) throw new Exception("Stripe payment method ID missing.");
                    $amtCents = intval(round($total * 100));
                    $returnUrl = "http://" . $_SERVER['HTTP_HOST'] . "/index.php?order=success&scheduled_date=" . urlencode($sd) . "&scheduled_time=" . urlencode($st_time);
                    $pi = \Stripe\PaymentIntent::create([
                        'amount'               => $amtCents,
                        'currency'             => 'eur',
                        'payment_method'       => $spm,
                        'confirmation_method'  => 'manual',
                        'confirm'              => true,
                        'description'          => "Order ID:$oid",
                        'metadata'             => ['order_id' => $oid],
                        'return_url'           => $returnUrl
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
                        $_SESSION['applied_coupon'] = null;
                        echo json_encode(['success' => true, 'redirect_url' => $returnUrl]);
                        exit;
                    } else {
                        throw new Exception("Invalid PaymentIntent status: " . $pi->status);
                    }
                } elseif ($pm === 'paypal') {
                    $payer      = (new Payer())->setPaymentMethod('paypal');
                    $amount     = (new Amount())->setTotal(number_format($total, 2, '.', ''))->setCurrency('EUR');
                    $transaction = (new Transaction())->setAmount($amount)->setDescription("Order ID: $oid");
                    $redirectUrls = (new RedirectUrls())
                        ->setReturnUrl("http://" . $_SERVER['HTTP_HOST'] . "/index.php?payment=paypal&success=true&order_id=$oid")
                        ->setCancelUrl("http://" . $_SERVER['HTTP_HOST'] . "/index.php?payment=paypal&success=false&order_id=$oid");
                    $payment    = (new Payment())->setIntent('sale')->setPayer($payer)->setTransactions([$transaction])->setRedirectUrls($redirectUrls);
                    $payment->create($paypal);
                    $_SESSION['paypal_payment_id'] = $payment->getId();
                    foreach ($payment->getLinks() as $lnk) {
                        if ($lnk->getRel() === 'approval_url') {
                            header("Location: " . $lnk->getHref());
                            exit;
                        }
                    }
                    throw new Exception("No approval URL found for PayPal.");
                }
            } else {
                $statusMap = ['pickup' => 3, 'cash' => 4];
                $pdo->prepare("UPDATE orders SET status_id=? WHERE id=?")->execute([$statusMap[$pm], $oid]);
                $pdo->commit();
                $_SESSION['cart'] = [];
                $_SESSION['selected_tip'] = null;
                $_SESSION['applied_coupon'] = null;
                header("Location: index.php?order=success&scheduled_date=$sd&scheduled_time=$st_time");
                exit;
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            log_error_markdown("Order Placement Failed: " . $e->getMessage(), "Checkout");
            header("Location: index.php?error=order_failed");
            exit;
        }
    }
}
if (isset($_GET['payment']) && $_GET['payment'] === 'paypal') {
    $success = ($_GET['success'] === 'true');
    $oid = (int)($_GET['order_id'] ?? 0);
    if ($oid <= 0) {
        log_error_markdown("Invalid PayPal order ID.", "PayPal Callback");
        header("Location: index.php?error=invalid_order");
        exit;
    }
    if ($success) {
        $payId  = $_SESSION['paypal_payment_id'] ?? '';
        $payerId = $_GET['PayerID'] ?? '';
        if (!$payId || !$payerId) {
            log_error_markdown("Missing PayPal payment details.", "PayPal Callback");
            header("Location: index.php?error=paypal_failed");
            exit;
        }
        try {
            $paymentObj = Payment::get($payId, $paypal);
            $exec       = new PaymentExecution();
            $exec->setPayerId($payerId);
            $pdo->beginTransaction();
            $response = $paymentObj->execute($exec, $paypal);
            if ($response->getState() === 'approved') {
                $pdo->prepare("UPDATE orders SET status_id=? WHERE id=?")->execute([5, $oid]);
                $pdo->commit();
                $_SESSION['cart'] = [];
                $_SESSION['selected_tip'] = null;
                $_SESSION['applied_coupon'] = null;
                header("Location: index.php?order=success&scheduled_date=" . urlencode($_SESSION['scheduled_date'] ?? '') . "&scheduled_time=" . urlencode($_SESSION['scheduled_time'] ?? ''));
                exit;
            } else {
                throw new Exception("PayPal not approved.");
            }
        } catch (Exception $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            log_error_markdown("PayPal Failed: " . $ex->getMessage(), "PayPal Callback");
            header("Location: index.php?error=paypal_failed");
            exit;
        }
    } else {
        header("Location: index.php?error=paypal_canceled");
        exit;
    }
}
try {
    $catStmt = $pdo->prepare("SELECT * FROM categories ORDER BY position ASC, name ASC");
    $catStmt->execute();
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_error_markdown("Failed to fetch categories: " . $e->getMessage(), "Categories");
    $categories = [];
}
$selC = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$q = "SELECT p.id AS product_id,p.product_code,p.name AS product_name,p.category,p.description,p.allergies,p.image_url,p.is_new,p.is_offer,p.is_active,p.properties,p.base_price,p.created_at,p.updated_at,p.category_id FROM products p WHERE p.is_active=1";
if ($selC > 0) $q .= " AND p.category_id=:c";
$q .= " ORDER BY p.created_at DESC";
$st = $pdo->prepare($q);
if ($selC > 0) $st->bindParam(':c', $selC, PDO::PARAM_INT);
$st->execute();
$rawProducts = $st->fetchAll(PDO::FETCH_ASSOC);
$products = array_map(function ($r) {
    $pp = json_decode($r['properties'], true) ?? [];
    $sz = $pp['sizes'] ?? [];
    $baseP = (float)($pp['base_price'] ?? $r['base_price']);
    $dp = $baseP + (count($sz) ? min(array_map(fn($s) => (float)($s['price'] ?? 0), $sz)) : 0);
    return [
        'id'           => $r['product_id'],
        'product_code' => $r['product_code'],
        'name'         => $r['product_name'],
        'category'     => $r['category'],
        'description'  => $r['description'],
        'allergies'    => $r['allergies'],
        'image_url'    => $r['image_url'],
        'is_new'       => $r['is_new'],
        'is_offer'     => $r['is_offer'],
        'is_active'    => $r['is_active'],
        'base_price'   => $baseP,
        'created_at'   => $r['created_at'],
        'updated_at'   => $r['updated_at'],
        'category_id'  => $r['category_id'],
        'extras'       => $pp['extras'] ?? [],
        'sauces'       => $pp['sauces'] ?? [],
        'sizes'        => $sz,
        'dresses'      => $pp['dresses'] ?? [],
        'display_price' => $dp
    ];
}, $rawProducts);
try {
    $drinks = $pdo->query("SELECT * FROM drinks ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_error_markdown("Failed to fetch drinks: " . $e->getMessage(), "Drinks");
    $drinks = [];
}
$cart_total = calculateCartTotal($_SESSION['cart']);
$selT      = $_SESSION['selected_tip'] ?? null;
$tip_amount = applyTip($cart_total, $tip_options, $selT);
$coupon_discount = 0;
if (!empty($_SESSION['applied_coupon'])) {
    $coupon_discount = $_SESSION['applied_coupon']['discount_value'];
    if ($coupon_discount > $cart_total) $coupon_discount = $cart_total;
}
$cart_total_with_tip = $cart_total + $tip_amount - $coupon_discount;
try {
    $bannersStmt = $pdo->prepare("SELECT * FROM banners WHERE is_active=1 ORDER BY created_at DESC");
    $bannersStmt->execute();
    $banners = $bannersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_error_markdown("Failed to fetch banners: " . $e->getMessage(), "Banners");
    $banners = [];
}
try {
    $d0 = date('Y-m-d');
    $offersStmt = $pdo->prepare("SELECT * FROM offers WHERE is_active=1 AND (start_date IS NULL OR start_date<=?) AND (end_date IS NULL OR end_date>=?) ORDER BY created_at DESC");
    $offersStmt->execute([$d0, $d0]);
    $active_offers = $offersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_error_markdown("Failed to fetch offers: " . $e->getMessage(), "Offers");
    $active_offers = [];
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Restaurant Delivery</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Saira:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/6.6.6/css/flag-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <?php if (!empty($main_store['cart_logo'])): ?>
        <link rel="icon" type="image/png" href="admin/<?= htmlspecialchars($main_store['cart_logo']) ?>">
    <?php endif; ?>
    <style>
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, .8);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .promo-banner .carousel-item img {
            height: 400px;
            object-fit: cover;
        }

        .offers-section .card-img-top {
            height: 200px;
            object-fit: cover;
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
            cursor: not-allowed;
        }

        .language-switcher {
            position: absolute;
            top: 10px;
            right: 10px;
        }

        .order-summary {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            position: sticky;
            top: 20px;
        }

        .order-title {
            margin-bottom: 15px;
        }

        .store-card.selected {
            border: 2px solid #0d6efd;
            background-color: #e7f1ff;
        }

        .store-card .select-store-btn {
            width: 100%
        }
    </style>
    <script src="https://js.stripe.com/v3/"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
</head>

<body>
    <?php if ($is_closed): ?>
        <div class="alert alert-danger text-center m-0" role="alert">
            <strong><?= htmlspecialchars($notification['title'] ?? 'Closed') ?></strong>:
            <?= htmlspecialchars($notification['message'] ?? 'We are currently closed.') ?>
        </div>
    <?php endif; ?>
    <div class="loading-overlay" id="loading-overlay">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    <?php if (!isset($_SESSION['selected_store']) || $showChangeAddressModal): ?>
        <div class="modal fade" id="storeModal" tabindex="-1" aria-labelledby="storeModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <form method="POST" id="storeSelectionForm">
                        <div class="modal-header">
                            <img src="<?= htmlspecialchars($main_store['cart_logo'] ?? '') ?>"
                                alt="Cart Logo"
                                style="width:100%;height:80px;object-fit:cover"
                                onerror="this.src='https://via.placeholder.com/150?text=Logo';">
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <?php if (isset($error_message)): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                            <?php endif; ?>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Choose a Store</label>
                                <div class="row" id="storeCardsContainer">
                                    <?php
                                    try {
                                        $storesQuery = $pdo->query("SELECT * FROM stores WHERE is_active=1 ORDER BY name ASC");
                                        $storesAll   = $storesQuery->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($storesAll as $st):
                                    ?>
                                            <div class="col-md-4 mb-3">
                                                <div class="card store-card h-100" data-store-id="<?= htmlspecialchars($st['id']) ?>">
                                                    <div class="card-body d-flex flex-column">
                                                        <h5 class="card-title"><?= htmlspecialchars($st['name']) ?></h5>
                                                        <p class="card-text"><?= htmlspecialchars($st['address'] ?? 'No address') ?></p>
                                                        <img src="admin/<?= htmlspecialchars($st['cart_logo'] ?? '') ?>"
                                                            alt="Store Logo"
                                                            style="width:100%;height:80px;object-fit:cover"
                                                            onerror="this.src='https://via.placeholder.com/150?text=Logo';">
                                                        <div class="mt-auto">
                                                            <input type="radio"
                                                                name="store_id"
                                                                value="<?= htmlspecialchars($st['id']) ?>"
                                                                class="form-check-input visually-hidden">
                                                            <button type="button" class="btn btn-outline-primary select-store-btn">
                                                                <i class="bi bi-pin-map"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                    <?php
                                        endforeach;
                                    } catch (PDOException $e) {
                                        log_error_markdown("Failed to fetch stores: " . $e->getMessage(), "Fetch Stores");
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="delivery_address" class="form-label">Delivery Address</label>
                                <input type="text"
                                    class="form-control"
                                    id="delivery_address"
                                    name="delivery_address"
                                    required
                                    value="<?= htmlspecialchars($_SESSION['delivery_address'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Select Location on Map</label>
                                <div id="map" style="height:300px;width:100%"></div>
                            </div>
                            <input type="hidden" id="latitude" name="latitude" value="<?= htmlspecialchars($_SESSION['latitude']  ?? '') ?>">
                            <input type="hidden" id="longitude" name="longitude" value="<?= htmlspecialchars($_SESSION['longitude'] ?? '') ?>">
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i> Confirm
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            (function() {
                let storeModal = new bootstrap.Modal('#storeModal', {
                    backdrop: 'static',
                    keyboard: false
                });
                storeModal.show();
                document.querySelectorAll('.select-store-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        document.querySelectorAll('.store-card').forEach(c => {
                            c.classList.remove('selected');
                            c.querySelector('input[name="store_id"]').checked = false;
                        });
                        let card = this.closest('.store-card');
                        card.classList.add('selected');
                        card.querySelector('input[name="store_id"]').checked = true;
                    });
                });
                let map = L.map('map').setView([51.505, -0.09], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(map);
                let marker = null;
                let storedLat = parseFloat(document.getElementById('latitude').value) || null;
                let storedLng = parseFloat(document.getElementById('longitude').value) || null;
                if (storedLat && storedLng) {
                    marker = L.marker([storedLat, storedLng]).addTo(map);
                    map.setView([storedLat, storedLng], 13);
                }
                map.on('click', function(e) {
                    if (marker) map.removeLayer(marker);
                    marker = L.marker(e.latlng).addTo(map);
                    document.getElementById('latitude').value = e.latlng.lat;
                    document.getElementById('longitude').value = e.latlng.lng;
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${e.latlng.lat}&lon=${e.latlng.lng}`)
                        .then(r => r.json())
                        .then(d => {
                            if (d.display_name) {
                                document.getElementById('delivery_address').value = d.display_name;
                            }
                        });
                });
                document.getElementById('delivery_address').addEventListener('change', function() {
                    let a = this.value.trim();
                    if (a.length > 5) {
                        fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(a)}`)
                            .then(r => r.json())
                            .then(d => {
                                if (d && d.length > 0) {
                                    if (marker) map.removeLayer(marker);
                                    let lat = parseFloat(d[0].lat),
                                        lon = parseFloat(d[0].lon);
                                    marker = L.marker([lat, lon]).addTo(map);
                                    map.setView([lat, lon], 13);
                                    document.getElementById('latitude').value = lat;
                                    document.getElementById('longitude').value = lon;
                                }
                            });
                    }
                });
                document.getElementById('storeSelectionForm').addEventListener('submit', function(e) {
                    if (!document.querySelector('input[name="store_id"]:checked')) {
                        e.preventDefault();
                        alert('Please select a store.');
                        return;
                    }
                    if (!document.getElementById('delivery_address').value.trim() ||
                        !document.getElementById('latitude').value ||
                        !document.getElementById('longitude').value) {
                        e.preventDefault();
                        alert('Please provide address & map location.');
                    }
                });
            })();
        </script>
    <?php endif; ?>
    <?php
    // Optionally include partials, if they exist:
    $includes = [
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
    foreach ($includes as $f) {
        if (file_exists($f)) include $f;
    }
    ?>
    <!-- Checkout modal -->
    <div class="modal fade" id="checkoutModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="index.php" id="checkoutForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="customer_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">E-Mail <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="customer_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Telefon <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="customer_phone" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Lieferadresse <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="delivery_address" rows="2" required><?= htmlspecialchars($_SESSION['delivery_address'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label>
                                <input class="form-check-input" type="checkbox" name="is_event" value="1" id="event_checkbox"> Veranstaltung
                            </label>
                        </div>
                        <div id="event_details" style="display:none">
                            <div class="mb-3">
                                <label class="form-label">Bevorzugtes Lieferdatum <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="scheduled_date" min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Bevorzugte Lieferzeit <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="scheduled_time">
                            </div>
                        </div>
                        <script>
                            document.getElementById('event_checkbox').addEventListener('change', function() {
                                let ed = document.getElementById('event_details');
                                let sd = document.querySelector('[name="scheduled_date"]');
                                let st = document.querySelector('[name="scheduled_time"]');
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
                        <div class="mb-3">
                            <label class="form-label">Zahlungsmethode <span class="text-danger">*</span></label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" value="stripe" required id="paymentStripe">
                                <label class="form-check-label" for="paymentStripe"><i class="bi bi-credit-card"></i> Stripe</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" value="paypal" required id="paymentPayPal">
                                <label class="form-check-label" for="paymentPayPal"><i class="bi bi-paypal"></i> PayPal</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" value="pickup" required id="paymentPickup">
                                <label class="form-check-label" for="paymentPickup"><i class="bi bi-bag"></i> Abholung</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" value="cash" required id="paymentCash">
                                <label class="form-check-label" for="paymentCash"><i class="bi bi-cash"></i> Nachnahme</label>
                            </div>
                        </div>
                        <div id="stripe-payment-section" class="mb-3" style="display:none">
                            <label class="form-label">Karte</label>
                            <div id="card-element"></div>
                            <div id="card-errors" class="text-danger mt-2"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Trinkgeld auswählen</label>
                            <select class="form-select" name="selected_tip" id="tip_selection">
                                <option value="">Kein Trinkgeld</option>
                                <?php foreach ($tip_options as $t): ?>
                                    <option value="<?= htmlspecialchars($t['id']) ?>" <?= ($selT == $t['id'] ? 'selected' : '') ?>>
                                        <?= htmlspecialchars($t['name']) ?>
                                        <?php
                                        if (!empty($t['percentage'])) {
                                            echo " ({$t['percentage']}%)";
                                        } elseif (!empty($t['amount'])) {
                                            echo " (+" . number_format($t['amount'], 2) . "€)";
                                        }
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <h5>Bestellübersicht</h5>
                        <ul class="list-group mb-3">
                            <?php foreach ($_SESSION['cart'] as $i => $it): ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold">
                                                <?= htmlspecialchars($it['name']) ?> x<?= htmlspecialchars($it['quantity']) ?>
                                                <?= isset($it['size']) ? " (Größe: " . htmlspecialchars($it['size']) . ")" : '' ?>
                                            </div>
                                            <?php if (!empty($it['extras']) || !empty($it['sauces']) || !empty($it['drink']) || !empty($it['special_instructions'])): ?>
                                                <ul class="mb-1">
                                                    <?php if (!empty($it['extras'])): ?>
                                                        <li><strong>Extras:</strong>
                                                            <ul>
                                                                <?php foreach ($it['extras'] as $e): ?>
                                                                    <li><?= htmlspecialchars($e['name']) ?> x<?= htmlspecialchars($e['quantity']) ?> @ <?= number_format($e['price'], 2) ?>€=<?= number_format($e['price'] * $e['quantity'], 2) ?>€</li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($it['sauces'])): ?>
                                                        <li><strong>Saucen:</strong>
                                                            <ul>
                                                                <?php foreach ($it['sauces'] as $sx): ?>
                                                                    <li><?= htmlspecialchars($sx['name']) ?> x<?= htmlspecialchars($sx['quantity']) ?> @ <?= number_format($sx['price'], 2) ?>€=<?= number_format($sx['price'] * $sx['quantity'], 2) ?>€</li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($it['drink'])): ?>
                                                        <li><strong>Getränk:</strong> <?= htmlspecialchars($it['drink']['name']) ?> (+<?= number_format($it['drink']['price'], 2) ?>€)</li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($it['special_instructions'])): ?>
                                                        <li><strong>Instructions:</strong> <?= htmlspecialchars($it['special_instructions']) ?></li>
                                                    <?php endif; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge bg-primary rounded-pill"><?= number_format($it['total_price'], 2) ?>€</span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                            <?php if ($tip_amount > 0): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <strong>Trinkgeld</strong><span><?= number_format($tip_amount, 2) ?>€</span>
                                </li>
                            <?php endif; ?>
                            <?php if (!empty($_SESSION['applied_coupon']) && $coupon_discount > 0): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <strong>Coupon (<?= htmlspecialchars($_SESSION['applied_coupon']['code']) ?>)</strong>
                                    <span>-<?= number_format($coupon_discount, 2) ?>€</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                        <h4>Gesamtsumme: <?= number_format($cart_total_with_tip, 2) ?>€</h4>
                        <form method="POST" action="index.php" class="mb-3 d-flex">
                            <input type="text" name="coupon_code" class="form-control" placeholder="Enter coupon code">
                            <button type="submit" name="apply_coupon" class="btn btn-outline-primary ms-2">Apply</button>
                        </form>
                        <?php if (isset($_GET['coupon_error'])): ?>
                            <div class="alert alert-danger p-1 text-center"><?= htmlspecialchars($_GET['coupon_error']) ?></div>
                        <?php elseif (isset($_GET['coupon']) && $_GET['coupon'] === 'applied'): ?>
                            <div class="alert alert-success p-1 text-center">Coupon applied!</div>
                        <?php endif; ?>
                        <button type="submit"
                            name="checkout"
                            value="1"
                            class="btn btn-success w-100 mt-3"
                            <?= ($is_closed ? 'disabled' : '') ?>>
                            <i class="bi bi-bag-check-fill"></i>
                            Bestellung abschicken
                        </button>

                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Category tabs -->
    <ul class="nav nav-tabs justify-content-center my-4">
        <li class="nav-item">
            <a class="nav-link <?= ($selC === 0 ? 'active' : '') ?>" href="index.php">All</a>
        </li>
        <?php foreach ($categories as $cItem): ?>
            <li class="nav-item">
                <a class="nav-link <?= ($selC === $cItem['id'] ? 'active' : '') ?>" href="index.php?category_id=<?= $cItem['id'] ?>">
                    <?= htmlspecialchars($cItem['name']) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <main class="container my-5">
        <div class="row">
            <div class="col-lg-9">
                <div class="row g-4">
                    <?php if ($products) foreach ($products as $pd): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100 shadow-sm">
                                <div class="position-relative">
                                    <img src="admin/<?= htmlspecialchars($pd['image_url']) ?>" class="card-img-top"
                                        alt="<?= htmlspecialchars($pd['name']) ?>"
                                        onerror="this.src='https://via.placeholder.com/600x400?text=Product+Image';">
                                    <?php if ($pd['is_new'] || $pd['is_offer']): ?>
                                        <span class="badge <?= ($pd['is_new'] ? 'bg-success' : 'bg-warning text-dark') ?> position-absolute <?= ($pd['is_offer'] ? 'top-40' : 'top-0') ?> end-0 m-2">
                                            <?= ($pd['is_new'] ? 'New' : 'Offer') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?= htmlspecialchars($pd['name']) ?></h5>
                                    <p class="card-text"><?= htmlspecialchars($pd['description']) ?></p>
                                    <?php if ($pd['allergies']): ?>
                                        <p class="card-text text-danger">
                                            <strong>Allergies:</strong> <?= htmlspecialchars($pd['allergies']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <div class="mt-auto">
                                        <strong>
                                            <?= !empty($pd['sizes'])
                                                ? "From " . number_format($pd['display_price'], 2) . "€"
                                                : number_format($pd['display_price'], 2) . "€" ?>
                                        </strong>
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                            data-bs-target="#addToCartModal<?= $pd['id'] ?>"
                                            <?= ($is_closed ? 'disabled' : '') ?> title="<?= ($is_closed ? 'Store is closed' : '') ?>">
                                            <i class="bi bi-cart-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Add to cart modal for product -->
                        <div class="modal fade" id="addToCartModal<?= $pd['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-xl modal-dialog-centered">
                                <div class="modal-content">
                                    <form method="POST" action="index.php"
                                        class="add-to-cart-form"
                                        data-baseprice="<?= number_format($pd['base_price'], 2, '.', '') ?>"
                                        data-productid="<?= $pd['id'] ?>">
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-4">
                                                    <img src="<?= htmlspecialchars($pd['image_url']) ?>"
                                                        class="img-fluid rounded shadow"
                                                        alt="<?= htmlspecialchars($pd['name']) ?>"
                                                        onerror="this.src='https://via.placeholder.com/600x400?text=Product+Image';">
                                                </div>
                                                <div class="col-8">
                                                    <h2 class="text-uppercase"><b><?= htmlspecialchars($pd['name']) ?></b></h2>
                                                    <hr style="width:10%;border:2px solid black;border-radius:5px;">
                                                    <?php if (empty($pd['sizes'])): ?>
                                                        <h5>Base Price:
                                                            <span id="base-price-<?= $pd['id'] ?>">
                                                                <?= number_format($pd['base_price'], 2) ?>€
                                                            </span>
                                                        </h5>
                                                        <input type="hidden" name="size" value="">
                                                    <?php else: ?>
                                                        <div class="mb-3">
                                                            <label class="form-label">Select Size:</label>
                                                            <select class="form-select size-selector" name="size" data-productid="<?= $pd['id'] ?>">
                                                                <option value="">Choose a size</option>
                                                                <?php foreach ($pd['sizes'] as $sz):
                                                                    $maxS = isset($sz['max_sauces']) ? (int)$sz['max_sauces'] : 0;
                                                                ?>
                                                                    <option value="<?= htmlspecialchars($sz['size']) ?>"
                                                                        data-sizeprice="<?= number_format($sz['price'], 2, '.', '') ?>"
                                                                        data-sizes-extras='<?= json_encode($sz['extras'] ?? []) ?>'
                                                                        data-sizes-sauces='<?= json_encode($sz['sauces'] ?? []) ?>'
                                                                        data-maxsauces="<?= $maxS ?>">
                                                                        <?= htmlspecialchars($sz['size']) ?> (<?= number_format($pd['base_price'] + $sz['price'], 2) ?>€)
                                                                        <?php if ($maxS > 0): ?> - Max <?= $maxS ?> sauces<?php endif; ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="mb-3">
                                                        <label class="form-label">Extras</label>
                                                        <div class="extras-container">
                                                            <?php foreach ($pd['extras'] as $extra): ?>
                                                                <div class="mb-2 d-flex align-items-center">
                                                                    <input type="number" class="form-control me-2 item-quantity"
                                                                        style="width:80px"
                                                                        name="extras[<?= htmlspecialchars($extra['name']) ?>]"
                                                                        data-price="<?= number_format($extra['price'], 2, '.', '') ?>"
                                                                        data-productid="<?= $pd['id'] ?>"
                                                                        value="0" min="0" step="1">
                                                                    <span><?= htmlspecialchars($extra['name']) ?> (+<?= number_format($extra['price'], 2) ?>€)</span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Sauces</label>
                                                        <div class="sauces-container">
                                                            <?php foreach ($pd['sauces'] as $sauce): ?>
                                                                <div class="mb-2 d-flex align-items-center">
                                                                    <input type="number" class="form-control me-2 item-quantity sauce-quantity"
                                                                        style="width:80px"
                                                                        name="sauces[<?= htmlspecialchars($sauce['name']) ?>]"
                                                                        data-price="<?= number_format($sauce['price'], 2, '.', '') ?>"
                                                                        data-productid="<?= $pd['id'] ?>"
                                                                        value="0" min="0" step="1">
                                                                    <span><?= htmlspecialchars($sauce['name']) ?> (+<?= number_format($sauce['price'], 2) ?>€)</span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Dresses</label>
                                                        <div class="dresses-container">
                                                            <?php foreach ($pd['dresses'] as $dress): ?>
                                                                <div class="mb-2 d-flex align-items-center">
                                                                    <input type="number" class="form-control me-2 item-quantity"
                                                                        style="width:80px"
                                                                        name="dresses[<?= htmlspecialchars($dress['name']) ?>]"
                                                                        data-price="<?= number_format($dress['price'], 2, '.', '') ?>"
                                                                        data-productid="<?= $pd['id'] ?>"
                                                                        value="0" min="0" step="1">
                                                                    <span><?= htmlspecialchars($dress['name']) ?> (+<?= number_format($dress['price'], 2) ?>€)</span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <?php if (!empty($drinks)): ?>
                                                        <div class="mb-3">
                                                            <label class="form-label">Drinks</label>
                                                            <select class="form-select drink-selector" name="drink" data-productid="<?= $pd['id'] ?>">
                                                                <option value="">Choose a drink</option>
                                                                <?php foreach ($drinks as $dk): ?>
                                                                    <option value="<?= htmlspecialchars($dk['id']) ?>"
                                                                        data-drinkprice="<?= number_format($dk['price'], 2, '.', '') ?>">
                                                                        <?= htmlspecialchars($dk['name']) ?> (+<?= number_format($dk['price'], 2) ?>€)
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="mb-3">
                                                        <label class="form-label">Quantity</label>
                                                        <input type="number" class="form-control quantity-selector" name="quantity"
                                                            data-productid="<?= $pd['id'] ?>" value="1" min="1" max="99" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Special Instructions</label>
                                                        <textarea class="form-control" name="special_instructions" rows="2"></textarea>
                                                    </div>
                                                    <div class="bg-light p-2 mb-2 rounded">
                                                        <strong>Estimated Price: </strong>
                                                        <span id="estimated-price-<?= $pd['id'] ?>" style="font-size:1.25rem;color:#d3b213">
                                                            <?= number_format($pd['base_price'], 2) ?>€
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <input type="hidden" name="product_id" value="<?= $pd['id'] ?>">
                                            <input type="hidden" name="add_to_cart" value="1">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                            <button type="submit" class="btn btn-primary" <?= ($is_closed ? 'disabled' : '') ?>>
                                                <i class="bi bi-cart-plus"></i>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
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
                                                <?php
                                                $algArr = array_map('trim', explode(',', $pd['allergies']));
                                                foreach ($algArr as $alg):
                                                ?>
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
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="order-summary">
                    <h2 class="order-title">YOUR ORDER</h2>
                    <?php if (!empty($main_store['logo'])): ?>
                        <div class="mb-3 text-center">
                            <img src="admin/<?= htmlspecialchars($main_store['logo']) ?>" alt="Cart Logo" class="img-fluid"
                                onerror="this.src='https://via.placeholder.com/150?text=Logo';">
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($main_store['cart_description'])): ?>
                        <div class="mb-4" id="cart-description">
                            <p><?= nl2br(htmlspecialchars($main_store['cart_description'])) ?></p>
                            <?= htmlspecialchars($_SESSION['store_name'] ?? '') ?>
                        </div>
                    <?php endif; ?>
                    <p class="card-text">
                        <strong>Minimum order:</strong> <?= htmlspecialchars($_SESSION['minimum_order'] ?? '5.00') ?> €<br>
                        <strong>Shipping base fee:</strong> <?= htmlspecialchars($main_store['shipping_fee_base'] ?? '0.00') ?> €<br>
                        <strong>Fee per km:</strong> <?= htmlspecialchars($main_store['shipping_fee_per_km'] ?? '0.50') ?> €<br>
                    </p>
                    <?php
                    if ($main_store) {
                        $wd = @json_decode($main_store['work_schedule'] ?? '', true);
                        if (isset($wd['days'])) {
                            echo "<p><strong>Store Schedule:</strong><br>";
                            foreach ($wd['days'] as $day => $hours) {
                                $stt = $hours['start'] ?? '--';
                                $enn = $hours['end']  ?? '--';
                                echo htmlspecialchars("$day: $stt - $enn") . "<br>";
                            }
                            echo "</p>";
                        }
                    }
                    ?>
                    <?php if (!empty($_SESSION['delivery_address'])): ?>
                        <div class="mb-3">
                            <h5>Delivery Address</h5>
                            <p><?= htmlspecialchars($_SESSION['delivery_address']) ?></p>
                            <a href="?action=change_address" class="btn btn-sm btn-outline-primary" title="Change Address">
                                <i class="bi bi-geo-alt"></i>
                            </a>
                            <a href="https://www.openstreetmap.org/?mlat=<?= htmlspecialchars($_SESSION['latitude']) ?>&mlon=<?= htmlspecialchars($_SESSION['longitude']) ?>#map=18/<?= htmlspecialchars($_SESSION['latitude']) ?>/<?= htmlspecialchars($_SESSION['longitude']) ?>"
                                target="_blank" class="btn btn-sm btn-outline-secondary" title="View on Map">
                                <i class="bi bi-map"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                    <div id="cart-items">
                        <?php if (!empty($_SESSION['cart'])): ?>
                            <ul class="list-group mb-3">
                                <?php foreach ($_SESSION['cart'] as $i => $it): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6><?= htmlspecialchars($it['name']) ?><?= isset($it['size']) ? " ({$it['size']})" : '' ?> x<?= htmlspecialchars($it['quantity']) ?></h6>
                                            <?php if (!empty($it['extras']) || !empty($it['sauces']) || !empty($it['drink']) || !empty($it['special_instructions'])): ?>
                                                <ul>
                                                    <?php if (!empty($it['extras'])): ?>
                                                        <li><strong>Extras:</strong>
                                                            <ul>
                                                                <?php foreach ($it['extras'] as $ex): ?>
                                                                    <li><?= htmlspecialchars($ex['name']) ?> x<?= htmlspecialchars($ex['quantity']) ?> @ <?= number_format($ex['price'], 2) ?>€=<?= number_format($ex['price'] * $ex['quantity'], 2) ?>€</li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($it['sauces'])): ?>
                                                        <li><strong>Saucen:</strong>
                                                            <ul>
                                                                <?php foreach ($it['sauces'] as $sx): ?>
                                                                    <li><?= htmlspecialchars($sx['name']) ?> x<?= htmlspecialchars($sx['quantity']) ?> @ <?= number_format($sx['price'], 2) ?>€=<?= number_format($sx['price'] * $sx['quantity'], 2) ?>€</li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($it['drink'])): ?>
                                                        <li><strong>Getränk:</strong> <?= htmlspecialchars($it['drink']['name']) ?> (+<?= number_format($it['drink']['price'], 2) ?>€)</li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($it['special_instructions'])): ?>
                                                        <li><strong>Instructions:</strong> <?= htmlspecialchars($it['special_instructions']) ?></li>
                                                    <?php endif; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <strong><?= number_format($it['total_price'], 2) ?>€</strong><br>
                                            <form action="index.php" method="POST" style="display:inline;">
                                                <input type="hidden" name="remove" value="<?= $i ?>">
                                                <button type="submit" class="btn btn-sm btn-danger mt-2" title="Remove">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-secondary mt-2"
                                                data-bs-toggle="modal" data-bs-target="#editCartModal<?= $i ?>"
                                                title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if (!empty($_SESSION['applied_coupon']) && $coupon_discount > 0): ?>
                                <ul class="list-group mb-3">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>Coupon (<?= htmlspecialchars($_SESSION['applied_coupon']['code']) ?>)</div>
                                        <div>-<?= number_format($coupon_discount, 2) ?>€</div>
                                    </li>
                                </ul>
                            <?php endif; ?>
                            <h4>Total: <?= number_format($cart_total_with_tip, 2) ?>€</h4>
                            <form method="POST" action="index.php" class="mb-3 d-flex">
                                <input type="text" name="coupon_code" class="form-control" placeholder="Enter coupon code" />
                                <button type="submit" name="apply_coupon" class="btn btn-outline-primary ms-2">Apply</button>
                            </form>
                            <?php if (isset($_GET['coupon_error'])): ?>
                                <div class="alert alert-danger p-1 text-center">
                                    <?= htmlspecialchars($_GET['coupon_error']) ?>
                                </div>
                            <?php elseif (isset($_GET['coupon']) && $_GET['coupon'] === 'applied'): ?>
                                <div class="alert alert-success p-1 text-center">
                                    Coupon applied!
                                </div>
                            <?php endif; ?>
                            <button class="btn btn-success w-100 mt-3" data-bs-toggle="modal" data-bs-target="#checkoutModal" <?= ($is_closed ? 'disabled' : '') ?>>
                                <i class="bi bi-bag-check-fill"></i> Bestellung abschicken
                            </button>
                        <?php else: ?>
                            <p>Your cart is empty.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <footer class="bg-light text-muted pt-5 pb-4">
        <div class="container">
            <div class="row">
                <div class="col-md-3">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">
                        <?php if (!empty($main_store['logo'])): ?>
                            <img src="<?= htmlspecialchars($main_store['logo']) ?>" alt="Logo" width="40" height="40" class="me-2">
                        <?php endif; ?>
                        <?= htmlspecialchars($main_store['name'] ?? 'Restaurant') ?>
                    </h5>
                    <p>Experience the finest dining with us.</p>
                </div>
                <div class="col-md-3">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">AGB</h5>
                    <button type="button" class="btn btn-link p-0 text-reset" data-bs-toggle="modal" data-bs-target="#agbModal">Read AGB</button>
                </div>
                <div class="col-md-3">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Impressum</h5>
                    <button type="button" class="btn btn-link p-0 text-reset" data-bs-toggle="modal" data-bs-target="#impressumModal">View Impressum</button>
                </div>
                <div class="col-md-3">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Datenschutz</h5>
                    <button type="button" class="btn btn-link p-0 text-reset" data-bs-toggle="modal" data-bs-target="#datenschutzModal">Privacy Policy</button>
                </div>
            </div>
            <hr class="mb-4">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <p>© <?= date('Y') ?> <?= htmlspecialchars($main_store['name'] ?? 'Restaurant') ?>. All rights reserved.</p>
                </div>
                <div class="col-md-5 text-end">
                    <div class="social-media">
                        <?php
                        foreach (['facebook_link', 'twitter_link', 'instagram_link', 'linkedin_link', 'youtube_link'] as $social) {
                            if (!empty($main_store[$social])): ?>
                                <a href="<?= htmlspecialchars($main_store[$social]) ?>" target="_blank">
                                    <i class="bi bi-<?= explode('_', $social)[0] ?>"></i>
                                </a>
                        <?php endif;
                        } ?>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    <!-- AGB Modal -->
    <div class="modal fade" id="agbModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">AGB</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= !empty($main_store['agb']) ? nl2br(htmlspecialchars($main_store['agb'])) : '<em>No AGB provided.</em>' ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Impressum Modal -->
    <div class="modal fade" id="impressumModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Impressum</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= !empty($main_store['impressum']) ? nl2br(htmlspecialchars($main_store['impressum'])) : '<em>No Impressum provided.</em>' ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Datenschutz Modal -->
    <div class="modal fade" id="datenschutzModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Datenschutz</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= !empty($main_store['datenschutzerklaerung']) ? nl2br(htmlspecialchars($main_store['datenschutzerklaerung'])) : '<em>No Datenschutzerklärung provided.</em>' ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(function() {
            $('#loading-overlay').fadeOut('slow');
            <?php if (isset($_GET['added']) && $_GET['added'] == 1): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Added to Cart',
                    text: 'Item has been added to your cart.',
                    timer: 3000,
                    showConfirmButton: false
                });
            <?php endif; ?>
            <?php if (isset($_GET['removed']) && $_GET['removed'] == 1): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Removed',
                    text: 'Item removed from cart.',
                    timer: 3000,
                    showConfirmButton: false
                });
            <?php endif; ?>
            <?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Updated',
                    text: 'Cart updated.',
                    timer: 3000,
                    showConfirmButton: false
                });
            <?php endif; ?>
            <?php if (isset($_GET['order']) && $_GET['order'] === 'success'): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Order Successful',
                    text: 'Your order has been placed successfully.',
                    timer: 5000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = 'index.php';
                });
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '<?= htmlspecialchars($_GET['error']) ?>'
                });
            <?php endif; ?>
        });
    </script>
    <script>
        const stripe = Stripe('pk_test_XXXXXXXXXXXXXXXXXXXXXXXX');
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
                            name: $('[name="customer_name"]').val(),
                            email: $('[name="customer_email"]').val(),
                            phone: $('[name="customer_phone"]').val(),
                            address: {
                                line1: $('[name="delivery_address"]').val()
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
                        if (ce) {
                            $('#card-errors').text(ce.message);
                        } else if (pi.status === 'succeeded') {
                            window.location.href = res.redirect_url;
                        }
                    } else if (res.success) {
                        window.location.href = res.redirect_url;
                    }
                } catch (err) {
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
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            window.lastChangedSauceInput = null;

            function updateEstimatedPrice(form) {
                let base = parseFloat(form.dataset.baseprice || "0");
                let pid = form.dataset.productid;
                let es = document.getElementById("estimated-price-" + pid);
                let q = parseFloat(form.querySelector('.quantity-selector').value || "1");
                if (q < 1) q = 1;
                let sz = form.querySelector('.size-selector');
                let sp = (sz && sz.value) ? parseFloat(sz.selectedOptions[0].dataset.sizeprice || "0") : 0;
                let totalExtras = 0;
                form.querySelectorAll('.item-quantity').forEach(iq => {
                    let ip = parseFloat(iq.dataset.price || "0");
                    let iv = parseFloat(iq.value || "0");
                    if (iv > 0) totalExtras += ip * iv;
                });
                let dr = form.querySelector('.drink-selector');
                let dp = (dr && dr.value) ? parseFloat(dr.selectedOptions[0].dataset.drinkprice || "0") : 0;
                let final = (base + sp + totalExtras + dp) * q;
                if (es) es.textContent = final.toFixed(2) + "€";
            }

            function limitSauceQuantities(form) {
                let sz = form.querySelector('.size-selector');
                if (!sz || !sz.value) return;
                let maxS = parseInt(sz.selectedOptions[0].dataset.maxsauces || "0", 10);
                if (!maxS || maxS < 1) return;
                let sauceInputs = form.querySelectorAll('.sauce-quantity');
                let total = 0;
                sauceInputs.forEach(inp => {
                    total += parseInt(inp.value || '0', 10);
                });
                if (total > maxS) {
                    alert("Max " + maxS + " sauce(s) allowed for this size!");
                    if (window.lastChangedSauceInput) {
                        let cval = parseInt(window.lastChangedSauceInput.value || '0', 10);
                        let diff = total - maxS;
                        let revertVal = Math.max(0, cval - diff);
                        window.lastChangedSauceInput.value = revertVal;
                    }
                }
            }

            function updateSizeSpecificOptions(form, sd) {
                let se = [],
                    ss = [];
                if (sd.sizesExtras) {
                    try {
                        se = JSON.parse(sd.sizesExtras);
                    } catch (e) {}
                }
                if (sd.sizesSauces) {
                    try {
                        ss = JSON.parse(sd.sizesSauces);
                    } catch (e) {}
                }
                let ec = form.querySelector('.extras-container');
                if (ec) {
                    ec.innerHTML = '';
                    if (se.length > 0) {
                        se.forEach(e => {
                            ec.innerHTML += `
                                    <div class="mb-2 d-flex align-items-center">
                                        <input type="number" class="form-control me-2 item-quantity" style="width:80px"
                                               name="extras[${e.name}]"
                                               data-price="${parseFloat(e.price).toFixed(2)}"
                                               data-productid="${form.dataset.productid}"
                                               value="0" min="0" step="1">
                                        <span>${e.name} (+${parseFloat(e.price).toFixed(2)}€)</span>
                                    </div>`;
                        });
                    }
                }
                let sc = form.querySelector('.sauces-container');
                if (sc) {
                    sc.innerHTML = '';
                    if (ss.length > 0) {
                        ss.forEach(e => {
                            sc.innerHTML += `
                                    <div class="mb-2 d-flex align-items-center">
                                        <input type="number" class="form-control me-2 item-quantity sauce-quantity" style="width:80px"
                                               name="sauces[${e.name}]"
                                               data-price="${parseFloat(e.price).toFixed(2)}"
                                               data-productid="${form.dataset.productid}"
                                               value="0" min="0" step="1">
                                        <span>${e.name} (+${parseFloat(e.price).toFixed(2)}€)</span>
                                    </div>`;
                        });
                    }
                }
                initializeEventListeners(form);
            }

            function initializeEventListeners(form) {
                form.querySelectorAll('.item-quantity').forEach(iq => {
                    iq.addEventListener('change', () => {
                        updateEstimatedPrice(form);
                        if (iq.classList.contains('sauce-quantity')) {
                            window.lastChangedSauceInput = iq;
                            limitSauceQuantities(form);
                        }
                    });
                });
                let sz = form.querySelector('.size-selector');
                if (sz) {
                    sz.addEventListener('change', function() {
                        let sd = {
                            sizesExtras: this.selectedOptions[0].dataset.sizesExtras || '[]',
                            sizesSauces: this.selectedOptions[0].dataset.sizesSauces || '[]'
                        };
                        updateSizeSpecificOptions(form, sd);
                        limitSauceQuantities(form);
                        updateEstimatedPrice(form);
                    });
                }
                let dr = form.querySelector('.drink-selector');
                if (dr) dr.addEventListener('change', () => updateEstimatedPrice(form));
                let qty = form.querySelector('.quantity-selector');
                if (qty) qty.addEventListener('change', () => updateEstimatedPrice(form));
                updateEstimatedPrice(form);
            }
            document.querySelectorAll('.add-to-cart-form').forEach(f => {
                initializeEventListeners(f);
            });
            let observer = new MutationObserver(mutations => {
                mutations.forEach(mu => {
                    if (mu.type === 'childList') {
                        mu.addedNodes.forEach(node => {
                            if (node.nodeType === Node.ELEMENT_NODE) {
                                node.querySelectorAll('.add-to-cart-form').forEach(ff => {
                                    initializeEventListeners(ff);
                                });
                            }
                        });
                    }
                });
            });
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        });
    </script>
</body>

</html>
<?php ob_end_flush(); ?>