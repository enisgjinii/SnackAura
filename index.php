<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require 'includes/db_connect.php';
$_SESSION['applied_coupon'] = $_SESSION['applied_coupon'] ?? null;
$_SESSION['cart'] = $_SESSION['cart'] ?? [];
$_SESSION['customer_name'] = $_SESSION['customer_name'] ?? '';
$_SESSION['customer_email'] = $_SESSION['customer_email'] ?? '';
$_SESSION['customer_phone'] = $_SESSION['customer_phone'] ?? '';
$_SESSION['postal_code'] = $_SESSION['postal_code'] ?? '';
$_SESSION['payment_method'] = $_SESSION['payment_method'] ?? '';
$_SESSION['scheduled_date'] = $_SESSION['scheduled_date'] ?? '';
$_SESSION['scheduled_time'] = $_SESSION['scheduled_time'] ?? '';
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
define('ERROR_LOG_FILE', __DIR__ . '/errors.md');
function log_error_markdown($m, $c = '')
{
    file_put_contents(
        ERROR_LOG_FILE,
        "### [" . date('Y-m-d H:i:s') . "] Error\n\n**Message:** " . htmlspecialchars($m, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . "\n\n" . ($c ? "**Context:** " . htmlspecialchars($c, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n\n" : '')
            . "---\n\n",
        FILE_APPEND | LOCK_EX
    );
}
set_exception_handler(function ($e) {
    log_error_markdown("Uncaught Exception: " . $e->getMessage(), "File: {$e->getFile()} Line: {$e->getLine()}");
    header("Location: index.php?error=unknown_error");
    exit;
});
set_error_handler(function ($sev, $msg, $fl, $ln) {
    if (!(error_reporting() & $sev)) return;
    throw new ErrorException($msg, 0, $sev, $fl, $ln);
});
include 'settings_fetch.php';
$selected_store_id = $_SESSION['selected_store'] ?? null;
$main_store = null;
$is_closed = false;
$notification = [];
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
            $end = $days[$todayName]['end'] ?? '';
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
        header("Location:index.php");
        exit;
    }
    header("Location:index.php?error=invalid_tip");
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
        'sizes' => $props['sizes'] ?? [],
        'extras' => $props['extras'] ?? [],
        'sauces' => $props['sauces'] ?? [],
        'dresses' => $props['dresses'] ?? [],
        'base_price' => (float)($props['base_price'] ?? $p['base_price']),
        'name' => $p['name'],
        'description' => $p['description'],
        'image_url' => $p['image_url'],
        'properties' => $p['properties'] ?? '',
        'id' => $p['id']
    ];
}
function calculateCartTotal($cart)
{
    return array_reduce($cart, function ($a, $i) {
        return $a + ($i['total_price'] ?? 0);
    }, 0);
}
function applyTip($cartTotal, $tips, $selectedTipId)
{
    if (!$selectedTipId) return 0;
    foreach ($tips as $t) {
        if ($t['id'] == $selectedTipId) {
            if (!empty($t['percentage'])) return $cartTotal * ((float)$t['percentage'] / 100);
            else return (float)($t['amount'] ?? 0);
        }
    }
    return 0;
}
function haversineDist($lat1, $lon1, $lat2, $lon2)
{
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    return $R * 2 * asin(sqrt((sin($dLat / 2)) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * (sin($dLon / 2)) ** 2));
}
function getDeliveryZonePrice($store, $lat, $lng)
{
    if (empty($store['delivery_zones']) || !$lat || !$lng) return 0;
    $zones = json_decode($store['delivery_zones'], true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($zones)) return 0;
    foreach ($zones as $z) {
        $dist = haversineDist($lat, $lng, $z['lat'], $z['lng']);
        if ($dist <= $z['radius']) {
            return (float)$z['price'];
        }
    }
    return 0;
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
    $dv = ($c['discount_type'] === 'percentage')
        ? ($cartTotal * ((float)$c['discount_value'] / 100))
        : (float)$c['discount_value'];
    $r['ok'] = true;
    $r['coupon'] = ['code' => $c['code'], 'discount_type' => $c['discount_type'], 'discount_value' => $dv];
    return $r;
}
function logdb($m)
{
    file_put_contents('errors.md', $m . "\n", FILE_APPEND);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['checkout'])) {
        if (empty($_SESSION['cart'])) {
            header("Location:index.php?error=empty_cart");
            exit;
        }
        $store_id = $_SESSION['selected_store'] ?? null;
        $cn = trim($_POST['customer_name'] ?? '');
        $ce = trim($_POST['customer_email'] ?? '');
        $cp = trim($_POST['customer_phone'] ?? '');
        $da = $_SESSION['delivery_address'] ?? '';
        $postal_code = trim($_POST['postal_code'] ?? '');
        $stid = $_POST['selected_tip'] ?? null;
        $ev = (isset($_POST['is_event']) && $_POST['is_event'] == '1');
        $sd = $_POST['scheduled_date'] ?? null;
        $st_time = $_POST['scheduled_time'] ?? null;
        $pm = $_POST['payment_method'] ?? '';
        if ($ev) {
            $sdt = DateTime::createFromFormat('Y-m-d H:i', "$sd $st_time");
            if (!$sdt || $sdt < new DateTime()) {
                header("Location:index.php?error=invalid_scheduled_datetime");
                exit;
            }
        }
        if (!in_array($pm, ['paypal', 'pickup', 'cash'])) {
            header("Location:index.php?error=invalid_payment_method");
            exit;
        }
        if (!$cn || !$ce || !$cp || !$da || !$postal_code) {
            header("Location:index.php?error=invalid_order_details");
            exit;
        }
        if ($pm !== 'pickup' && $main_store) {
            $delivery_zone_price_test = getDeliveryZonePrice($main_store, $_SESSION['latitude'] ?? 0, $_SESSION['longitude'] ?? 0);
            if ($delivery_zone_price_test == 0) {
                header("Location:index.php?error=out_of_zone");
                exit;
            }
        }
        $cart_total = calculateCartTotal($_SESSION['cart']);
        $tip_amount = applyTip($cart_total, $tip_options, $stid);
        $delivery_zone_price = 0;
        if ($pm !== 'pickup' && $main_store) {
            $delivery_zone_price = getDeliveryZonePrice($main_store, $_SESSION['latitude'] ?? 0, $_SESSION['longitude'] ?? 0);
        }
        $coupon_discount = 0;
        if (!empty($_SESSION['applied_coupon'])) {
            $coupon_discount = $_SESSION['applied_coupon']['discount_value'];
            if ($coupon_discount > $cart_total) {
                $coupon_discount = $cart_total;
            }
        }
        $total = max($cart_total + $tip_amount + $delivery_zone_price - $coupon_discount, 0);
        $order_details = json_encode([
            'items' => $_SESSION['cart'],
            'latitude' => $_SESSION['latitude'] ?? null,
            'longitude' => $_SESSION['longitude'] ?? null,
            'tip_id' => $stid,
            'tip_amount' => $tip_amount,
            'store_id' => $store_id,
            'is_event' => $ev,
            'scheduled_date' => $sd,
            'scheduled_time' => $st_time,
            'delivery_zone_price' => $delivery_zone_price,
            'coupon_code' => $_SESSION['applied_coupon']['code'] ?? null,
            'coupon_discount' => $coupon_discount
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO orders(user_id, customer_name, customer_email, customer_phone, delivery_address, postal_code, total_amount, status_id, tip_id, tip_amount, scheduled_date, scheduled_time, payment_method, store_id, order_details, coupon_code, coupon_discount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                null,
                $cn,
                $ce,
                $cp,
                $da,
                $postal_code,
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
            if ($pm === 'paypal') {
                $pdo->commit();
                $_SESSION['cart'] = [];
                $_SESSION['selected_tip'] = null;
                $_SESSION['applied_coupon'] = null;
                header("Location:index.php?pending_paypal_order_id=$oid");
                exit;
            } elseif (in_array($pm, ['pickup', 'cash'])) {
                $statusMap = ['pickup' => 3, 'cash' => 4];
                $pdo->prepare("UPDATE orders SET status_id=? WHERE id=?")->execute([$statusMap[$pm], $oid]);
                $pdo->commit();
                $_SESSION['cart'] = [];
                $_SESSION['selected_tip'] = null;
                $_SESSION['applied_coupon'] = null;
                header("Location:index.php?order=success&scheduled_date=$sd&scheduled_time=$st_time");
                exit;
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            log_error_markdown("Order Placement Failed: " . $e->getMessage(), "Checkout");
            header("Location:index.php?error=order_failed");
            exit;
        }
    } elseif (isset($_POST['apply_coupon'])) {
        $cc = trim($_POST['coupon_code'] ?? '');
        $st = calculateCartTotal($_SESSION['cart']);
        $res = applyCoupon($pdo, $cc, $st);
        if ($res['ok']) {
            $_SESSION['applied_coupon'] = $res['coupon'];
            header("Location:index.php?coupon=applied");
            exit;
        }
        header("Location:index.php?coupon_error=" . urlencode($res['error']));
        exit;
    } elseif (isset($_POST['store_id'])) {
        $sid = (int)$_POST['store_id'];
        $st = $pdo->prepare("SELECT * FROM stores WHERE id=? AND is_active=1");
        $st->execute([$sid]);
        if ($store = $st->fetch(PDO::FETCH_ASSOC)) {
            $da = trim($_POST['delivery_address'] ?? '');
            $la = trim($_POST['latitude'] ?? '');
            $lo = trim($_POST['longitude'] ?? '');
            $postal_code = trim($_POST['postal_code'] ?? '');
            if (!$da || !$la || !$lo || !$postal_code) {
                $error_message = "Please provide a valid delivery address, postal code & location.";
            } else {
                $delivery_zone_test = getDeliveryZonePrice($store, $la, $lo);
                if ($delivery_zone_test == 0) {
                    $error_message = "Your location is out of this store's delivery zones. Please pick another store or location.";
                } else {
                    $_SESSION['selected_store'] = $sid;
                    $_SESSION['store_name'] = $store['name'];
                    $_SESSION['store_lat'] = $store['store_lat'];
                    $_SESSION['store_lng'] = $store['store_lng'];
                    $_SESSION['delivery_address'] = $da;
                    $_SESSION['postal_code'] = $postal_code;
                    $_SESSION['latitude'] = $la;
                    $_SESSION['longitude'] = $lo;
                    $_SESSION['minimum_order'] = $store['minimum_order'];
                    header("Location:index.php");
                    exit;
                }
            }
        } else {
            $error_message = "Selected store not available.";
        }
    } elseif (isset($_POST['add_to_cart'])) {
        $pid = (int)$_POST['product_id'];
        $qty = max(1, (int)$_POST['quantity']);
        $sz = $_POST['size'] ?? null;
        $pEx = $_POST['extras'] ?? [];
        $pSa = $_POST['sauces'] ?? [];
        $pDr = $_POST['dresses'] ?? [];
        $drink_id = isset($_POST['drink']) ? (int)$_POST['drink'] : null;
        $spec = trim($_POST['special_instructions'] ?? '');
        $pr = fetchProduct($pdo, $pid);
        if (!$pr) {
            header("Location:index.php?error=invalid_product");
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
                header("Location:index.php?error=invalid_size");
                exit;
            }
        }
        $bp = $pr['base_price'] + ($sel_size['price'] ?? 0);
        $et = 0;
        $exs = [];
        $theseExtras = $sel_size ? ($sel_size['extras'] ?? []) : ($pr['extras'] ?? []);
        foreach ($theseExtras as $e) {
            $qx = (int)($pEx[$e['name']] ?? 0);
            if ($qx > 0) {
                $et += $e['price'] * $qx;
                $exs[] = ['name' => $e['name'], 'price' => $e['price'], 'quantity' => $qx];
            }
        }
        $st = 0;
        $sas = [];
        $theseSauces = $sel_size ? ($sel_size['sauces'] ?? []) : ($pr['sauces'] ?? []);
        foreach ($theseSauces as $sa) {
            $qx = (int)($pSa[$sa['name']] ?? 0);
            if ($qx > 0) {
                $st += $sa['price'] * $qx;
                $sas[] = ['name' => $sa['name'], 'price' => $sa['price'], 'quantity' => $qx];
            }
        }
        $dt = 0;
        $drs = [];
        foreach ($pr['dresses'] as $d) {
            $qx = (int)($pDr[$d['name']] ?? 0);
            if ($qx > 0) {
                $dt += $d['price'] * $qx;
                $drs[] = ['name' => $d['name'], 'price' => $d['price'], 'quantity' => $qx];
            }
        }
        $drt = 0;
        $drnk = null;
        if ($drink_id) {
            $stD = $pdo->prepare("SELECT * FROM drinks WHERE id=?");
            $stD->execute([$drink_id]);
            if ($dk = $stD->fetch(PDO::FETCH_ASSOC)) {
                $drt = (float)($dk['price']);
                $drnk = $dk;
            }
        }
        $unit = $bp + $et + $st + $dt + $drt;
        $total = $unit * $qty;
        $cart_item = [
            'product_id' => $pid,
            'name' => $pr['name'],
            'description' => $pr['description'],
            'image_url' => $pr['image_url'],
            'size' => $sz,
            'size_price' => $sel_size['price'] ?? 0,
            'extras' => $exs,
            'sauces' => $sas,
            'dresses' => $drs,
            'drink' => $drnk,
            'special_instructions' => $spec,
            'quantity' => $qty,
            'unit_price' => $unit,
            'total_price' => $total
        ];
        foreach ($_SESSION['cart'] as $i => $exI) {
            $sameDrink = (($exI['drink']['id'] ?? null) == ($cart_item['drink']['id'] ?? null));
            if (
                $exI['product_id'] == $cart_item['product_id'] &&
                $exI['size'] == $cart_item['size'] &&
                $sameDrink &&
                $exI['special_instructions'] == $cart_item['special_instructions'] &&
                $exI['extras'] == $cart_item['extras'] &&
                $exI['sauces'] == $cart_item['sauces'] &&
                $exI['dresses'] == $cart_item['dresses']
            ) {
                $_SESSION['cart'][$i]['quantity'] += $qty;
                $_SESSION['cart'][$i]['total_price'] += $total;
                header("Location:index.php?added=1");
                exit;
            }
        }
        $_SESSION['cart'][] = $cart_item;
        header("Location:index.php?added=1");
        exit;
    } elseif (isset($_POST['remove'])) {
        $ri = (int)$_POST['remove'];
        if (isset($_SESSION['cart'][$ri])) {
            unset($_SESSION['cart'][$ri]);
            $_SESSION['cart'] = array_values($_SESSION['cart']);
            header("Location:index.php?removed=1");
            exit;
        }
        log_error_markdown("Attempted remove non-existent cart item:$ri", "Remove from Cart");
        header("Location:index.php?error=invalid_remove");
        exit;
    } elseif (isset($_POST['update_cart'])) {
        $ii = (int)$_POST['item_index'];
        if (!isset($_SESSION['cart'][$ii])) {
            log_error_markdown("Invalid cart update request:$ii", "Updating Cart");
            header("Location:index.php?error=invalid_update");
            exit;
        }
        $qty = max(1, (int)$_POST['quantity']);
        $sz = $_POST['size'] ?? null;
        $pEx = $_POST['extras'] ?? [];
        $pSa = $_POST['sauces'] ?? [];
        $pDr = $_POST['dresses'] ?? [];
        $drink_id = isset($_POST['drink']) ? (int)$_POST['drink'] : null;
        $spec = trim($_POST['special_instructions'] ?? '');
        $pid = $_SESSION['cart'][$ii]['product_id'];
        $pr = fetchProduct($pdo, $pid);
        if (!$pr) {
            log_error_markdown("Product not found or inactive ID:$pid", "Updating Cart");
            header("Location:index.php?error=invalid_update");
            exit;
        }
        $props = json_decode($pr['properties'] ?? '{}', true) ?? [];
        $base = (float)($props['base_price'] ?? $pr['base_price']);
        $zopt = $props['sizes'] ?? $pr['sizes'];
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
                    $xopt = $z['extras'] ?? $xopt;
                    $sopt = $z['sauces'] ?? $sopt;
                    $dopt = $z['dresses'] ?? $dopt;
                    break;
                }
            }
            if (!$sel_size) {
                log_error_markdown("Invalid size:$sz for Product $pid", "Updating Cart");
                header("Location:index.php?error=invalid_size");
                exit;
            }
        }
        $exTot = 0;
        $sel_ex = [];
        foreach ($xopt as $xo) {
            $nm = $xo['name'];
            $prc = (float)$xo['price'];
            if (isset($pEx[$nm])) {
                $qx = (int)$pEx[$nm];
                if ($qx > 0) {
                    $exTot += $prc * $qx;
                    $sel_ex[] = ['name' => $nm, 'price' => $prc, 'quantity' => $qx];
                }
            }
        }
        $saTot = 0;
        $sel_sa = [];
        foreach ($sopt as $sx) {
            $nm = $sx['name'];
            $prc = (float)$sx['price'];
            if (isset($pSa[$nm])) {
                $qx = (int)$pSa[$nm];
                if ($qx > 0) {
                    $saTot += $sx['price'] * $qx;
                    $sel_sa[] = ['name' => $nm, 'price' => $sx['price'], 'quantity' => $qx];
                }
            }
        }
        $drTot = 0;
        $sel_dr = [];
        foreach ($dopt as $dx) {
            $nm = $dx['name'];
            $prc = (float)$dx['price'];
            if (isset($pDr[$nm])) {
                $qx = (int)$pDr[$nm];
                if ($qx > 0) {
                    $drTot += $dx['price'] * $qx;
                    $sel_dr[] = ['name' => $nm, 'price' => $prc, 'quantity' => $qx];
                }
            }
        }
        $drinkTot = 0;
        $drink_details = null;
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
            'product_id' => $pr['id'],
            'name' => $pr['name'],
            'description' => $pr['description'],
            'image_url' => $pr['image_url'],
            'size' => $sel_size['size'] ?? null,
            'size_price' => $szp,
            'extras' => $sel_ex,
            'sauces' => $sel_sa,
            'dresses' => $sel_dr,
            'drink' => $drink_details,
            'special_instructions' => $spec,
            'quantity' => $qty,
            'unit_price' => $u,
            'total_price' => $tp
        ];
        header("Location:index.php?updated=1");
        exit;
    } elseif (isset($_POST['paypal_success'])) {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data && isset($data['orderId']) && isset($data['paypalOrderId']) && isset($data['paypalCaptureId'])) {
            $orderId = (int)$data['orderId'];
            $paypalOrderId = $data['paypalOrderId'];
            $paypalCaptureId = $data['paypalCaptureId'];
            try {
                $stmt = $pdo->prepare("UPDATE orders SET status_id=?, payment_info=? WHERE id=?");
                $stmt->execute([5, json_encode(['paypalOrderId' => $paypalOrderId, 'paypalCaptureId' => $paypalCaptureId]), $orderId]);
                echo json_encode(['status' => 'ok']);
                exit;
            } catch (Exception $e) {
                log_error_markdown("Failed to update order status: " . $e->getMessage(), "PayPal Success");
                echo json_encode(['status' => 'error']);
                exit;
            }
        }
        echo json_encode(['status' => 'invalid']);
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
$q = "SELECT p.id AS product_id, p.product_code, p.name AS product_name, p.category, p.description, p.allergies, p.image_url, p.is_new, p.is_offer, p.is_active, p.properties, p.base_price, p.created_at, p.updated_at, p.category_id FROM products p WHERE p.is_active=1";
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
        'base_price' => $baseP,
        'created_at' => $r['created_at'],
        'updated_at' => $r['updated_at'],
        'category_id' => $r['category_id'],
        'extras' => $pp['extras'] ?? [],
        'sauces' => $pp['sauces'] ?? [],
        'sizes' => $sz,
        'dresses' => $pp['dresses'] ?? [],
        'display_price' => $dp,
        'max_extras_base' => (int)($pp['max_extras_base'] ?? 0),
        'max_sauces_base' => (int)($pp['max_sauces_base'] ?? 0),
        'max_dresses_base' => (int)($pp['max_dresses_base'] ?? 0)
    ];
}, $rawProducts);
try {
    $drinks = $pdo->query("SELECT * FROM drinks ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_error_markdown("Failed to fetch drinks: " . $e->getMessage(), "Drinks");
    $drinks = [];
}
$cart_total = calculateCartTotal($_SESSION['cart']);
$selT = $_SESSION['selected_tip'] ?? null;
$tip_amount = applyTip($cart_total, $tip_options, $selT);
$coupon_discount = 0;
if (!empty($_SESSION['applied_coupon'])) {
    $coupon_discount = $_SESSION['applied_coupon']['discount_value'];
    if ($coupon_discount > $cart_total) $coupon_discount = $cart_total;
}
$cart_total_with_tip = $cart_total + $tip_amount - $coupon_discount;
$delivery_zone_price = 0;
if (!empty($main_store) && (!isset($_GET['payment_method']) || $_GET['payment_method'] !== 'pickup')) {
    $delivery_zone_price = getDeliveryZonePrice($main_store, $_SESSION['latitude'] ?? 0, $_SESSION['longitude'] ?? 0);
}
$total_with_zone_price = $cart_total_with_tip + $delivery_zone_price;
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
    <?php if (!empty($main_store['cart_logo'])): ?>
        <link rel="icon" type="image/png" href="admin/<?= htmlspecialchars($main_store['cart_logo']) ?>">
    <?php endif; ?>
    <style>
        * {
            font-family: 'Inter', sans-serif;
            font-weight: 400
        }

        body {
            font-size: 0.9rem;
        }

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
            height: 300px;
            object-fit: cover
        }

        .offers-section .card-img-top {
            height: 170px;
            object-fit: cover
        }

        @media(max-width:768px) {
            .promo-banner .carousel-item img {
                height: 200px
            }

            .offers-section .card-img-top {
                height: 130px
            }
        }

        .btn.disabled,
        .btn:disabled {
            opacity: .65;
            cursor: not-allowed
        }

        .order-summary {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            position: sticky;
            top: 20px
        }

        .order-title {
            margin-bottom: 10px;
            font-size: 1rem
        }

        .store-card.selected {
            border: 2px solid #0d6efd;
            background-color: #e7f1ff
        }

        .store-card .select-store-btn {
            width: 100%
        }

        .store-card {
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 6px;
            transition: box-shadow .3s;
            height: 100%
        }

        .store-card:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, .1)
        }

        .store-card img {
            height: 50px;
            object-fit: contain;
            margin-bottom: 5px
        }

        .store-card .card-title {
            font-size: 1rem;
            margin-bottom: 3px
        }

        .store-card .card-text {
            font-size: .8rem;
            color: #555
        }

        .select-store-btn {
            margin-top: auto;
            padding: 6px 0;
            font-size: .8rem
        }
    </style>
    <script src="https://js.stripe.com/v3/"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
</head>

<body>
    <div class="loading-overlay" id="loading-overlay">
        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
    </div>
    <?php if ($is_closed && isset($_SESSION['selected_store'])): ?>
        <div class="modal fade" id="storeClosedModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?= htmlspecialchars($notification['title']) ?></h5>
                    </div>
                    <div class="modal-body"><?= htmlspecialchars($notification['message']) ?></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <a href="?action=change_address" class="btn btn-primary">Change Store</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if (!isset($_SESSION['selected_store']) || (isset($_GET['action']) && $_GET['action'] === 'change_address')): ?>
        <div class="modal fade show" id="storeModal" tabindex="-1" style="display:block;background:rgba(0,0,0,0.5)">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" id="storeSelectionForm">
                        <div class="modal-header">
                            <h5 class="modal-title">Select Your Store</h5>
                            <?php if (!empty($main_store['cart_logo'])): ?>
                                <img src="admin/<?= htmlspecialchars($main_store['cart_logo']) ?>" alt="Cart Logo" style="width:50px;height:50px;object-fit:cover;margin-left:10px">
                            <?php endif; ?>
                        </div>
                        <div class="modal-body">
                            <?php if (isset($error_message)): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                            <?php endif; ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold" style="font-size:.95rem">Choose a Store</label>
                                <div class="row" id="storeCardsContainer">
                                    <?php
                                    try {
                                        $storesQuery = $pdo->query("SELECT * FROM stores WHERE is_active=1 ORDER BY name ASC");
                                        $storesAll = $storesQuery->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($storesAll as $st): ?>
                                            <div class="col-md-4 col-sm-6 mb-3">
                                                <div class="store-card d-flex flex-column" data-store-id="<?= htmlspecialchars($st['id']) ?>">
                                                    <img src="admin/<?= htmlspecialchars($st['cart_logo'] ?? '') ?>" alt="Store Logo" onerror="this.src='https://coffective.com/wp-content/uploads/2018/06/default-featured-image.png.jpg'">
                                                    <h5 class="card-title"><?= htmlspecialchars($st['name']) ?></h5>
                                                    <p class="card-text"><?= htmlspecialchars($st['address'] ?? 'No address') ?></p>
                                                    <p><?= htmlspecialchars($st['store_lat'] ?? '') ?> , <?= htmlspecialchars($st['store_lng'] ?? '') ?></p>
                                                    <input type="radio" name="store_id" value="<?= htmlspecialchars($st['id']) ?>" class="form-check-input visually-hidden">
                                                    <button type="button" class="btn btn-outline-primary select-store-btn"><i class="bi bi-pin-map"></i> Select</button>
                                                </div>
                                            </div>
                                    <?php endforeach;
                                    } catch (PDOException $e) {
                                        log_error_markdown("Failed to fetch stores: " . $e->getMessage(), "Fetch Stores");
                                        echo '<div class="col-12"><div class="alert alert-danger">Unable to load stores at this time.</div></div>';
                                    } ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="delivery_address" class="form-label">Delivery Address</label>
                                <input type="text" class="form-control" id="delivery_address" name="delivery_address" required value="<?= htmlspecialchars($_SESSION['delivery_address'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label for="postal_code" class="form-label">Postal Code</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" required value="<?= htmlspecialchars($_SESSION['postal_code'] ?? '') ?>">
                            </div>
                            <input type="hidden" id="latitude" name="latitude" value="<?= htmlspecialchars($_SESSION['latitude'] ?? '') ?>">
                            <input type="hidden" id="longitude" name="longitude" value="<?= htmlspecialchars($_SESSION['longitude'] ?? '') ?>">
                            <div id="map" style="width:100%;height:300px" hidden></div>
                            <p><?= htmlspecialchars($_SESSION['latitude'] ?? '') ?> , <?= htmlspecialchars($_SESSION['longitude'] ?? '') ?></p>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i> Confirm</button>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
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
                let slt = parseFloat(document.getElementById('latitude').value) || null;
                let sln = parseFloat(document.getElementById('longitude').value) || null;
                if (slt && sln) {
                    marker = L.marker([slt, sln]).addTo(map);
                    map.setView([slt, sln], 13);
                }
                map.on('click', function(e) {
                    if (marker) map.removeLayer(marker);
                    marker = L.marker(e.latlng).addTo(map);
                    document.getElementById('latitude').value = e.latlng.lat;
                    document.getElementById('longitude').value = e.latlng.lng;
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${e.latlng.lat}&lon=${e.latlng.lng}`)
                        .then(r => r.json())
                        .then(d => {
                            if (d.display_name) document.getElementById('delivery_address').value = d.display_name;
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
                        showBootstrapAlert('No store selected.', 'warning');
                        return;
                    }
                    if (!document.getElementById('delivery_address').value.trim() || !document.getElementById('latitude').value || !document.getElementById('longitude').value) {
                        e.preventDefault();
                        showBootstrapAlert('Please provide a complete address and location.', 'warning');
                    }
                });

                function showBootstrapAlert(m, t = 'info') {
                    let a = document.getElementById('alert_placeholder');
                    if (!a) {
                        let c = document.createElement('div');
                        c.id = 'alert_placeholder';
                        document.body.prepend(c);
                        a = c;
                    }
                    let w = document.createElement('div');
                    w.innerHTML = `<div class="alert alert-${t} alert-dismissible fade show" role="alert">${m}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>`;
                    a.append(w);
                }
            })();
        </script>
    <?php endif; ?>
    <?php if ($is_closed && isset($_SESSION['selected_store'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                new bootstrap.Modal(document.getElementById('storeClosedModal')).show();
            });
        </script>
    <?php endif; ?>
    <?php
    $includes = ['edit_cart.php', 'header.php', 'reservation.php', 'promotional_banners.php', 'special_offers.php', 'checkout.php', 'ratings_modal.php', 'cart_modal.php', 'toast_notifications.php'];
    foreach ($includes as $f) {
        if (file_exists($f)) include $f;
    }
    ?>
    <div class="modal fade" id="checkoutModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="index.php" id="checkoutForm">
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="customer_name" required value="<?= htmlspecialchars($_SESSION['customer_name'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Contact Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="customer_phone" required value="<?= htmlspecialchars($_SESSION['customer_phone'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">E-Mail <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="customer_email" required value="<?= htmlspecialchars($_SESSION['customer_email'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Delivery Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="delivery_address" rows="2" required readonly><?= htmlspecialchars($_SESSION['delivery_address'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Postal Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="postal_code" required value="<?= htmlspecialchars($_SESSION['postal_code'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label><input class="form-check-input" type="checkbox" name="is_event" value="1" id="event_checkbox" <?= ($_SESSION['is_event'] ?? '') == '1' ? 'checked' : '' ?>> Event</label>
                        </div>
                        <div id="event_details" style="display:<?= (($_SESSION['is_event'] ?? '') == '1' ? 'block' : 'none') ?>">
                            <div class="mb-2">
                                <label class="form-label">Preferred Delivery Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="scheduled_date" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_SESSION['scheduled_date'] ?? '') ?>">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Preferred Delivery Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="scheduled_time" value="<?= htmlspecialchars($_SESSION['scheduled_time'] ?? '') ?>">
                            </div>
                        </div>
                        <script>
                            document.getElementById('event_checkbox').addEventListener('change', function() {
                                let e = document.getElementById('event_details'),
                                    sd = document.querySelector('[name="scheduled_date"]'),
                                    st = document.querySelector('[name="scheduled_time"]');
                                if (this.checked) {
                                    e.style.display = 'block';
                                    sd.required = true;
                                    st.required = true;
                                } else {
                                    e.style.display = 'none';
                                    sd.required = false;
                                    st.required = false;
                                }
                            });
                        </script>
                        <div class="mb-2">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" value="paypal" required id="paymentPayPal" <?= ($_SESSION['payment_method'] ?? '') === 'paypal' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="paymentPayPal"><i class="bi bi-paypal"></i> PayPal</label>
                            </div>
                            <div id="paypal-button-container"></div>
                            <?php if (isset($_GET['pending_paypal_order_id'])): ?>
                                <h6 class="mt-3">Complete PayPal Payment</h6>
                                <div id="paypal-button-container-pending"></div>
                                <script>
                                    paypal.Buttons({
                                        createOrder: function(data, actions) {
                                            const urlParams = new URLSearchParams(window.location.search);
                                            const pId = urlParams.get('pending_paypal_order_id');
                                            let t = <?= json_encode(number_format($total_with_zone_price, 2, '.', '')) ?>;
                                            return actions.order.create({
                                                purchase_units: [{
                                                    description: "Order #" + pId,
                                                    amount: {
                                                        currency_code: "EUR",
                                                        value: t
                                                    }
                                                }]
                                            });
                                        },
                                        onApprove: function(data, actions) {
                                            return actions.order.capture().then(function(details) {
                                                fetch('index.php', {
                                                    method: 'POST',
                                                    headers: {
                                                        'Content-Type': 'application/json'
                                                    },
                                                    body: JSON.stringify({
                                                        paypal_success: true,
                                                        orderId: <?= json_encode((int)($_GET['pending_paypal_order_id'] ?? 0)) ?>,
                                                        paypalOrderId: data.orderID,
                                                        paypalCaptureId: details.purchase_units[0].payments.captures[0].id
                                                    })
                                                }).then(response => response.json()).then(res => {
                                                    if (res.status === 'ok') {
                                                        alert("Payment successful!");
                                                        window.location.href = 'index.php?order=success';
                                                    } else {
                                                        alert("Payment captured, but server error occurred.");
                                                    }
                                                }).catch(err => {
                                                    console.error("Error updating order:", err);
                                                    alert("Server error after capture.");
                                                });
                                            });
                                        },
                                        onCancel: function(data) {
                                            alert("Payment was canceled.");
                                        },
                                        onError: function(err) {
                                            console.error("PayPal onError:", err);
                                            alert("Error in PayPal process.");
                                        }
                                    }).render('#paypal-button-container-pending');
                                </script>
                            <?php endif; ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" value="pickup" required id="paymentPickup" <?= ($_SESSION['payment_method'] ?? '') === 'pickup' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="paymentPickup"><i class="bi bi-bag"></i> Pickup</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" value="cash" required id="paymentCash" <?= ($_SESSION['payment_method'] ?? '') === 'cash' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="paymentCash"><i class="bi bi-cash"></i> Cash on Delivery</label>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Select Tip</label>
                            <select class="form-select" name="selected_tip" id="tip_selection">
                                <option value="">No Tip</option>
                                <?php foreach ($tip_options as $t): ?>
                                    <option value="<?= htmlspecialchars($t['id']) ?>" <?= ($selT == $t['id'] ? 'selected' : '') ?>>
                                        <?= htmlspecialchars($t['name']) ?>
                                        <?php if (!empty($t['percentage'])) echo " (" . $t['percentage'] . "%)";
                                        elseif (!empty($t['amount'])) echo " (+" . number_format($t['amount'], 2) . "€)"; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <h6>Order Summary</h6>
                        <ul class="list-group mb-2">
                            <?php foreach ($_SESSION['cart'] as $i => $it): ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold"><?= htmlspecialchars($it['name']) ?> x<?= htmlspecialchars($it['quantity']) ?><?php if (isset($it['size'])) echo " (" . htmlspecialchars($it['size']) . ")"; ?></div>
                                            <?php if (!empty($it['extras']) || !empty($it['sauces']) || !empty($it['drink']) || !empty($it['special_instructions'])): ?>
                                                <ul class="mb-1">
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
                                                        <li><strong>Sauces:</strong>
                                                            <ul>
                                                                <?php foreach ($it['sauces'] as $sx): ?>
                                                                    <li><?= htmlspecialchars($sx['name']) ?> x<?= htmlspecialchars($sx['quantity']) ?> @ <?= number_format($sx['price'], 2) ?>€=<?= number_format($sx['price'] * $sx['quantity'], 2) ?>€</li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($it['drink'])): ?>
                                                        <li><strong>Drink:</strong> <?= htmlspecialchars($it['drink']['name']) ?> (+<?= number_format($it['drink']['price'], 2) ?>€)</li>
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
                                                <button type="submit" class="btn btn-sm btn-danger mt-1" title="Remove"><i class="bi bi-trash"></i></button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-secondary mt-1" data-bs-toggle="modal" data-bs-target="#editCartModal<?= $i ?>" title="Edit"><i class="bi bi-pencil-square"></i></button>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                            <?php if ($tip_amount > 0): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center"><strong>Tip</strong><span><?= number_format($tip_amount, 2) ?>€</span></li>
                            <?php endif; ?>
                            <?php if (!empty($_SESSION['applied_coupon']) && $coupon_discount > 0): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center"><strong>Coupon (<?= htmlspecialchars($_SESSION['applied_coupon']['code']) ?>)</strong><span>-<?= number_format($coupon_discount, 2) ?>€</span></li>
                            <?php endif; ?>
                        </ul>
                        <p><strong>Subtotal:</strong> <?= number_format($cart_total_with_tip, 2) ?> €<br><strong>Delivery Zone Price:</strong> <?= number_format($delivery_zone_price, 2) ?> €</p>
                        <hr>
                        <h6>Total: <?= number_format($total_with_zone_price, 2) ?> €</h6>
                        <button type="submit" name="checkout" value="1" class="btn btn-success w-100 mt-2" <?= ($is_closed ? 'disabled' : '') ?>><i class="bi bi-bag-check-fill"></i> Place Order</button>
                    </div>
                    <div class="modal-footer"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"></div>
                </form>
            </div>
        </div>
    </div>
    <ul class="nav nav-tabs justify-content-center my-3">
        <li class="nav-item"><a class="nav-link <?= ($selC === 0 ? 'active' : '') ?>" href="index.php">All</a></li>
        <?php foreach ($categories as $cItem): ?>
            <li class="nav-item"><a class="nav-link <?= ($selC === $cItem['id'] ? 'active' : '') ?>" href="index.php?category_id=<?= $cItem['id'] ?>"><?= htmlspecialchars($cItem['name']) ?></a></li>
        <?php endforeach; ?>
    </ul>
    <main class="container my-3">
        <div class="row">
            <div class="col-lg-9">
                <div class="row g-3">
                    <?php if ($products) foreach ($products as $pd): ?>
                        <div class="col-md-4">
                            <div class="card h-100 shadow-sm">
                                <div class="position-relative">
                                    <img src="admin/<?= htmlspecialchars($pd['image_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($pd['name']) ?>" onerror="this.src='https://coffective.com/wp-content/uploads/2018/06/default-featured-image.png.jpg';">
                                    <?php if ($pd['is_new'] || $pd['is_offer']): ?>
                                        <span class="badge <?= ($pd['is_new'] ? 'bg-success' : 'bg-warning text-dark') ?> position-absolute <?= ($pd['is_offer'] ? 'top-40' : 'top-0') ?> end-0 m-2"><?= ($pd['is_new'] ? 'New' : 'Offer') ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body d-flex flex-column" style="gap:4px">
                                    <h6 class="card-title" style="font-size:.95rem"><?= htmlspecialchars($pd['name']) ?></h6>
                                    <p class="card-text" style="font-size:.8rem;line-height:1.2"><?= htmlspecialchars($pd['description']) ?></p>
                                    <?php if ($pd['allergies']): ?>
                                        <p class="card-text text-danger" style="font-size:.8rem"><strong>Allergies:</strong> <?= htmlspecialchars($pd['allergies']) ?></p>
                                    <?php endif; ?>
                                    <div class="mt-auto">
                                        <small><strong><?= (!empty($pd['sizes']) ? "From " . number_format($pd['display_price'], 2) . "€" : number_format($pd['display_price'], 2) . "€") ?></strong></small>
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addToCartModal<?= $pd['id'] ?>" <?= ($is_closed ? 'disabled' : '') ?> style="font-size:.8rem"><i class="bi bi-cart-plus"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal fade" id="addToCartModal<?= $pd['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-xl modal-dialog-centered">
                                <div class="modal-content">
                                    <form method="POST" action="index.php" class="add-to-cart-form" data-baseprice="<?= number_format($pd['base_price'], 2, '.', '') ?>" data-productid="<?= $pd['id'] ?>" data-basemaxextras="<?= $pd['max_extras_base'] ?>" data-basemaxsauces="<?= $pd['max_sauces_base'] ?>" data-basemaxdresses="<?= $pd['max_dresses_base'] ?>">
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-4">
                                                    <img src="admin/<?= htmlspecialchars($pd['image_url']) ?>" class="img-fluid rounded shadow" alt="<?= htmlspecialchars($pd['name']) ?>" onerror="this.src='https://coffective.com/wp-content/uploads/2018/06/default-featured-image.png.jpg';">
                                                </div>
                                                <div class="col-8">
                                                    <h6 style="font-size:1rem"><b><?= htmlspecialchars($pd['name']) ?></b></h6>
                                                    <hr style="width:10%;border:2px solid black;border-radius:5px;">
                                                    <?php $thereAreSizes = !empty($pd['sizes']);
                                                    if (!$thereAreSizes): ?>
                                                        <p style="font-size:.9rem">Base Price: <span id="base-price-<?= $pd['id'] ?>"><?= number_format($pd['base_price'], 2) ?>€</span></p>
                                                        <input type="hidden" name="size" value="">
                                                    <?php else: ?>
                                                        <div class="mb-2">
                                                            <label class="form-label">Select Size:</label>
                                                            <select class="form-select size-selector" name="size" data-productid="<?= $pd['id'] ?>">
                                                                <option value="">Choose a size</option>
                                                                <?php foreach ($pd['sizes'] as $sz):
                                                                    $mxEx = (int)($sz['max_extras'] ?? 0);
                                                                    $mxSa = (int)($sz['max_sauces'] ?? 0);
                                                                    $mxDr = (int)($sz['max_dresses'] ?? 0); ?>
                                                                    <option value="<?= htmlspecialchars($sz['size']) ?>"
                                                                        data-sizeprice="<?= number_format($sz['price'], 2, '.', '') ?>"
                                                                        data-sizes-extras='<?= json_encode($sz['extras'] ?? []) ?>'
                                                                        data-sizes-sauces='<?= json_encode($sz['sauces'] ?? []) ?>'
                                                                        data-sizes-dresses='<?= json_encode($sz['dresses'] ?? []) ?>'
                                                                        data-maxextras="<?= $mxEx ?>"
                                                                        data-maxsauces="<?= $mxSa ?>"
                                                                        data-maxdresses="<?= $mxDr ?>">
                                                                        <?= htmlspecialchars($sz['size']) ?> (<?= number_format($pd['base_price'] + $sz['price'], 2) ?>€)
                                                                        <?php if ($mxEx > 0) echo " - Max $mxEx extras";
                                                                        if ($mxSa > 0) echo " - Max $mxSa sauces";
                                                                        if ($mxDr > 0) echo " - Max $mxDr dresses"; ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="mb-2">
                                                        <label class="form-label">Extras</label>
                                                        <div class="extras-container">
                                                            <?php foreach ($pd['extras'] as $extra): ?>
                                                                <div class="row align-items-center mb-2">
                                                                    <div class="col-auto">
                                                                        <input type="checkbox" class="form-check-input extra-checkbox" id="check-extra-<?= htmlspecialchars($extra['name']) ?>" data-qtyid="qty-extra-<?= htmlspecialchars($extra['name']) ?>">
                                                                    </div>
                                                                    <div class="col">
                                                                        <label class="form-check-label" for="check-extra-<?= htmlspecialchars($extra['name']) ?>"><?= htmlspecialchars($extra['name']) ?> (+<?= number_format($extra['price'], 2) ?>€)</label>
                                                                    </div>
                                                                    <div class="col-auto">
                                                                        <div class="input-group input-group-sm" style="width:100px;">
                                                                            <button type="button" class="btn btn-outline-secondary minus-btn" data-target="qty-extra-<?= htmlspecialchars($extra['name']) ?>">-</button>
                                                                            <input type="number" class="form-control text-center extra-quantity" name="extras[<?= htmlspecialchars($extra['name']) ?>]" id="qty-extra-<?= htmlspecialchars($extra['name']) ?>" data-price="<?= number_format($extra['price'], 2, '.', '') ?>" value="0" min="0" step="1" disabled>
                                                                            <button type="button" class="btn btn-outline-secondary plus-btn" data-target="qty-extra-<?= htmlspecialchars($extra['name']) ?>">+</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label">Sauces</label>
                                                        <div class="sauces-container">
                                                            <?php foreach ($pd['sauces'] as $sauce): ?>
                                                                <div class="row align-items-center mb-2">
                                                                    <div class="col-auto">
                                                                        <input type="checkbox" class="form-check-input extra-checkbox" id="check-sauce-<?= htmlspecialchars($sauce['name']) ?>" data-qtyid="qty-sauce-<?= htmlspecialchars($sauce['name']) ?>">
                                                                    </div>
                                                                    <div class="col">
                                                                        <label class="form-check-label" for="check-sauce-<?= htmlspecialchars($sauce['name']) ?>"><?= htmlspecialchars($sauce['name']) ?> (+<?= number_format($sauce['price'], 2) ?>€)</label>
                                                                    </div>
                                                                    <div class="col-auto">
                                                                        <div class="input-group input-group-sm" style="width:100px;">
                                                                            <button type="button" class="btn btn-outline-secondary minus-btn" data-target="qty-sauce-<?= htmlspecialchars($sauce['name']) ?>">-</button>
                                                                            <input type="number" class="form-control text-center extra-quantity sauce-quantity" name="sauces[<?= htmlspecialchars($sauce['name']) ?>]" id="qty-sauce-<?= htmlspecialchars($sauce['name']) ?>" data-price="<?= number_format($sauce['price'], 2, '.', '') ?>" value="0" min="0" step="1" disabled>
                                                                            <button type="button" class="btn btn-outline-secondary plus-btn" data-target="qty-sauce-<?= htmlspecialchars($sauce['name']) ?>">+</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label">Dresses</label>
                                                        <div class="dresses-container">
                                                            <?php foreach ($pd['dresses'] as $dress): ?>
                                                                <div class="row align-items-center mb-2">
                                                                    <div class="col-auto">
                                                                        <input type="checkbox" class="form-check-input extra-checkbox" id="check-dress-<?= htmlspecialchars($dress['name']) ?>" data-qtyid="qty-dress-<?= htmlspecialchars($dress['name']) ?>">
                                                                    </div>
                                                                    <div class="col">
                                                                        <label class="form-check-label" for="check-dress-<?= htmlspecialchars($dress['name']) ?>"><?= htmlspecialchars($dress['name']) ?> (+<?= number_format($dress['price'], 2) ?>€)</label>
                                                                    </div>
                                                                    <div class="col-auto">
                                                                        <div class="input-group input-group-sm" style="width:100px;">
                                                                            <button type="button" class="btn btn-outline-secondary minus-btn" data-target="qty-dress-<?= htmlspecialchars($dress['name']) ?>">-</button>
                                                                            <input type="number" class="form-control text-center extra-quantity" name="dresses[<?= htmlspecialchars($dress['name']) ?>]" id="qty-dress-<?= htmlspecialchars($dress['name']) ?>" data-price="<?= number_format($dress['price'], 2, '.', '') ?>" value="0" min="0" step="1" disabled>
                                                                            <button type="button" class="btn btn-outline-secondary plus-btn" data-target="qty-dress-<?= htmlspecialchars($dress['name']) ?>">+</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <?php if (!empty($drinks)): ?>
                                                        <div class="mb-2">
                                                            <label class="form-label">Drinks</label>
                                                            <select class="form-select drink-selector" name="drink" data-productid="<?= $pd['id'] ?>">
                                                                <option value="">Choose a drink</option>
                                                                <?php foreach ($drinks as $dk): ?>
                                                                    <option value="<?= htmlspecialchars($dk['id']) ?>" data-drinkprice="<?= number_format($dk['price'], 2, '.', '') ?>"><?= htmlspecialchars($dk['name']) ?> (+<?= number_format($dk['price'], 2) ?>€)</option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="mb-2">
                                                        <label class="form-label">Quantity</label>
                                                        <input type="number" class="form-control quantity-selector" name="quantity" data-productid="<?= $pd['id'] ?>" value="1" min="1" max="99" required>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label">Special Instructions</label>
                                                        <textarea class="form-control" name="special_instructions" rows="2" style="font-size:.8rem"></textarea>
                                                    </div>
                                                    <div class="bg-light p-2 mb-2 rounded">
                                                        <small><strong>Estimated Price: </strong></small>
                                                        <span id="estimated-price-<?= $pd['id'] ?>" style="font-size:1rem;color:#d3b213"><?= number_format($pd['base_price'], 2) ?>€</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <input type="hidden" name="product_id" value="<?= $pd['id'] ?>">
                                            <input type="hidden" name="add_to_cart" value="1">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i></button>
                                            <button type="submit" class="btn btn-primary" <?= ($is_closed ? 'disabled' : '') ?>><i class="bi bi-cart-plus"></i></button>
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
                                                <?php $algArr = array_map('trim', explode(',', $pd['allergies']));
                                                foreach ($algArr as $alg): ?>
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
                <div class="order-summary card shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-4">
                            <h5 class="card-title mb-0 flex-grow-1">Your Order</h5>
                            <?php if (!empty($main_store['logo'])): ?>
                                <img src="admin/<?= htmlspecialchars($main_store['logo']) ?>" alt="Store Logo" width="50" height="50" class="img-fluid rounded-circle" onerror="this.src='https://coffective.com/wp-content/uploads/2018/06/default-featured-image.png.jpg';">
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($main_store['cart_description'])): ?>
                            <div class="mb-3">
                                <p class="text-muted small mb-1"><?= nl2br(htmlspecialchars($main_store['cart_description'])) ?></p>
                                <p class="text-muted small mb-3"><?= htmlspecialchars($_SESSION['store_name'] ?? '') ?></p>
                                <p><?= htmlspecialchars($_SESSION['store_lat'] ?? '') ?> , <?= htmlspecialchars($_SESSION['store_lng'] ?? '') ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($_SESSION['delivery_address'])): ?>
                            <div class="mb-4">
                                <h6 class="fw-bold">Delivery Address</h6>
                                <p class="mb-1"><?= htmlspecialchars($_SESSION['delivery_address']) ?></p>
                                <?php if (isset($_SESSION['latitude'], $_SESSION['longitude'])): ?>
                                    <p class="mb-1"><?= htmlspecialchars($_SESSION['latitude']) ?> , <?= htmlspecialchars($_SESSION['longitude']) ?></p>
                                <?php else: ?>
                                    <p class="mb-1">Location not available</p>
                                <?php endif; ?>
                                <div class="d-flex gap-2">
                                    <a href="?action=change_address" class="btn btn-sm btn-outline-primary" title="Change Address" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Change Address"><i class="bi bi-geo-alt"></i></a>
                                    <a href="https://www.openstreetmap.org/?mlat=<?= htmlspecialchars($_SESSION['latitude']) ?>&mlon=<?= htmlspecialchars($_SESSION['longitude']) ?>#map=18/<?= htmlspecialchars($_SESSION['latitude']) ?>/<?= htmlspecialchars($_SESSION['longitude']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="View on Map" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="View on Map"><i class="bi bi-map"></i></a>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div id="cart-items">
                            <?php if (!empty($_SESSION['cart'])): ?>
                                <ul class="list-group mb-4">
                                    <?php foreach ($_SESSION['cart'] as $i => $it): ?>
                                        <li class="list-group-item p-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1" style="font-size:0.95rem;"><?= htmlspecialchars($it['name']) ?><?php if (isset($it['size'])) echo " (" . htmlspecialchars($it['size']) . ")"; ?> x<?= htmlspecialchars($it['quantity']) ?></h6>
                                                    <?php if (!empty($it['extras']) || !empty($it['sauces']) || !empty($it['drink']) || !empty($it['special_instructions'])): ?>
                                                        <ul class="list-unstyled ms-3" style="font-size:0.8rem;">
                                                            <?php if (!empty($it['extras'])): ?>
                                                                <li><strong>Extras:</strong>
                                                                    <ul class="list-unstyled ms-3">
                                                                        <?php foreach ($it['extras'] as $ex): ?>
                                                                            <li><?= htmlspecialchars($ex['name']) ?> x<?= htmlspecialchars($ex['quantity']) ?> @ <?= number_format($ex['price'], 2) ?>€=<?= number_format($ex['price'] * $ex['quantity'], 2) ?>€</li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                </li>
                                                            <?php endif; ?>
                                                            <?php if (!empty($it['sauces'])): ?>
                                                                <li><strong>Sauces:</strong>
                                                                    <ul class="list-unstyled ms-3">
                                                                        <?php foreach ($it['sauces'] as $sx): ?>
                                                                            <li><?= htmlspecialchars($sx['name']) ?> x<?= htmlspecialchars($sx['quantity']) ?> @ <?= number_format($sx['price'], 2) ?>€=<?= number_format($sx['price'] * $sx['quantity'], 2) ?>€</li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                </li>
                                                            <?php endif; ?>
                                                            <?php if (!empty($it['drink'])): ?>
                                                                <li><strong>Drink:</strong> <?= htmlspecialchars($it['drink']['name']) ?> (+<?= number_format($it['drink']['price'], 2) ?>€)</li>
                                                            <?php endif; ?>
                                                            <?php if (!empty($it['special_instructions'])): ?>
                                                                <li><strong>Instructions:</strong> <?= htmlspecialchars($it['special_instructions']) ?></li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-end">
                                                    <strong><?= number_format($it['total_price'], 2) ?>€</strong>
                                                    <div class="mt-2 d-flex flex-column gap-1">
                                                        <form action="index.php" method="POST">
                                                            <input type="hidden" name="remove" value="<?= $i ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" title="Remove Item" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Remove Item"><i class="bi bi-trash"></i></button>
                                                        </form>
                                                        <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#editCartModal<?= $i ?>" title="Edit Item" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Edit Item"><i class="bi bi-pencil-square"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if (!empty($_SESSION['applied_coupon']) && $coupon_discount > 0): ?>
                                    <div class="alert alert-info d-flex justify-content-between align-items-center mb-4 p-2" role="alert">
                                        <span>Coupon (<?= htmlspecialchars($_SESSION['applied_coupon']['code']) ?>)</span><span>-<?= number_format($coupon_discount, 2) ?>€</span>
                                    </div>
                                <?php endif; ?>
                                <div class="mb-4">
                                    <p class="mb-1"><strong>Subtotal:</strong> <?= number_format($cart_total_with_tip, 2) ?> €</p>
                                    <hr class="my-2">
                                    <p class="mb-1"><strong>Delivery Zone Price:</strong> <?= number_format($delivery_zone_price, 2) ?> €</p>
                                    <hr class="my-2">
                                    <p class="mb-1"><strong>Total:</strong> <?= number_format($total_with_zone_price, 2) ?> €</p>
                                </div>
                                <form method="POST" action="index.php" class="mb-4">
                                    <div class="input-group">
                                        <input type="text" name="coupon_code" class="form-control form-control-sm" placeholder="Enter coupon code">
                                        <button type="submit" name="apply_coupon" class="btn btn-outline-primary btn-sm">Apply</button>
                                    </div>
                                    <?php if (isset($_GET['coupon_error'])): ?>
                                        <div class="alert alert-danger mt-2 p-2 text-center small" role="alert"><?= htmlspecialchars($_GET['coupon_error']) ?></div>
                                    <?php elseif (isset($_GET['coupon']) && $_GET['coupon'] === 'applied'): ?>
                                        <div class="alert alert-success mt-2 p-2 text-center small" role="alert">Coupon applied!</div>
                                    <?php endif; ?>
                                </form>
                                <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#checkoutModal" <?= ($is_closed ? 'disabled' : '') ?>><i class="bi bi-bag-check-fill me-2"></i> Checkout</button>
                            <?php else: ?>
                                <p class="text-center text-muted">Your cart is empty.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php
    include 'footer.php';
    include 'rules.php';
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://www.paypal.com/sdk/js?client-id=AfbPMlmPT4z37DRzH886cPd1AggGZjz-L_LnVJxx_Odv7AB82AQ9CIz8P_s-5cjgLf-NDgpng0NLAiWr&currency=EUR"></script>
    <script>
        function showBootstrapAlert(m, t = 'info') {
            let a = document.getElementById('alert_placeholder');
            if (!a) {
                let c = document.createElement('div');
                c.id = 'alert_placeholder';
                document.body.prepend(c);
                a = c;
            }
            let w = document.createElement('div');
            w.innerHTML = `<div class="alert alert-${t} alert-dismissible fade show" role="alert">${m}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>`;
            a.append(w);
        }
        $(function() {
            $('#loading-overlay').fadeOut('slow');
            <?php if (isset($_GET['added']) && $_GET['added'] == 1): ?>showBootstrapAlert("Item added to cart.", "success");
        <?php endif; ?>
        <?php if (isset($_GET['removed']) && $_GET['removed'] == 1): ?>showBootstrapAlert("Item removed from cart.", "warning");
        <?php endif; ?>
        <?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>showBootstrapAlert("Cart updated.", "info");
        <?php endif; ?>
        <?php if (isset($_GET['order']) && $_GET['order'] === 'success'): ?>showBootstrapAlert("Order placed successfully.", "success");
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>showBootstrapAlert("Error: <?= htmlspecialchars($_GET['error']) ?>", "danger");
        <?php endif; ?>
        <?php if (isset($_GET['empty_cart']) && $_GET['empty_cart'] == 1): ?>showBootstrapAlert("Cart is empty.", "warning");
        <?php endif; ?>
        <?php if (isset($_GET['out_of_zone']) && $_GET['out_of_zone'] == 1): ?>showBootstrapAlert("Your location is out of the delivery zones.", "danger");
        <?php endif; ?>
        });
        document.addEventListener('DOMContentLoaded', function() {
            window.lastChangedInput = {
                extras: null,
                sauces: null,
                dresses: null
            };

            function limitItemQuantities(form, type) {
                let sizeSel = form.querySelector('.size-selector');
                let baseMax = parseInt(form.dataset['basemax' + type] || "0", 10);
                let maxVal = 0;
                if (sizeSel && sizeSel.value) {
                    let sOpt = sizeSel.selectedOptions[0];
                    maxVal = parseInt(sOpt.dataset['max' + type] || "0", 10);
                } else {
                    maxVal = baseMax;
                }
                if (!maxVal || maxVal < 1) return;
                let cls = type === "sauces" ? ".sauce-quantity" : ".extra-quantity";
                if (type === "extras") cls = ".extra-quantity:not(.sauce-quantity)";
                if (type === "dresses") cls = ".dresses-container .extra-quantity";
                let inputs = form.querySelectorAll(cls);
                let total = 0;
                inputs.forEach(inp => {
                    total += parseInt(inp.value || '0', 10);
                });
                if (total > maxVal) {
                    alert("Max " + maxVal + " " + type + " allowed!");
                    if (window.lastChangedInput[type]) {
                        let cval = parseInt(window.lastChangedInput[type].value || '0', 10),
                            diff = total - maxVal;
                        let revertVal = Math.max(0, cval - diff);
                        window.lastChangedInput[type].value = revertVal;
                    }
                }
            }

            function updateEstimatedPrice(form) {
                let base = parseFloat(form.dataset.baseprice || "0"),
                    pid = form.dataset.productid;
                let es = document.getElementById("estimated-price-" + pid);
                let q = parseFloat(form.querySelector('.quantity-selector').value || "1");
                if (q < 1) q = 1;
                let sz = form.querySelector('.size-selector');
                let sp = (sz && sz.value) ? parseFloat(sz.selectedOptions[0].dataset.sizeprice || "0") : 0;
                let totalExtras = 0;
                form.querySelectorAll('.extra-quantity').forEach(iq => {
                    let ip = parseFloat(iq.dataset.price || "0"),
                        iv = parseFloat(iq.value || "0");
                    if (iv > 0) totalExtras += (ip * iv);
                });
                let dr = form.querySelector('.drink-selector');
                let dp = (dr && dr.value) ? parseFloat(dr.selectedOptions[0].dataset.drinkprice || "0") : 0;
                let partial = (base + sp + totalExtras + dp) * q;
                if (partial < 0) partial = 0;
                if (es) es.textContent = partial.toFixed(2) + "€";
            }

            function updateSizeSpecificOptions(form, sd) {
                let se = [],
                    ss = [],
                    dd = [];
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
                if (sd.sizesDresses) {
                    try {
                        dd = JSON.parse(sd.sizesDresses);
                    } catch (e) {}
                }
                let ec = form.querySelector('.extras-container');
                if (ec) {
                    ec.innerHTML = '';
                    if (se.length > 0) {
                        se.forEach(e => {
                            ec.innerHTML += `
<div class="row align-items-center mb-2">
 <div class="col-auto">
  <input type="checkbox" class="form-check-input extra-checkbox" id="check-extra-${e.name}" data-qtyid="qty-extra-${e.name}">
 </div>
 <div class="col">
  <label class="form-check-label" for="check-extra-${e.name}">${e.name} (+${parseFloat(e.price).toFixed(2)}€)</label>
 </div>
 <div class="col-auto">
  <div class="input-group input-group-sm" style="width:100px;">
   <button type="button" class="btn btn-outline-secondary minus-btn" data-target="qty-extra-${e.name}">-</button>
   <input type="number" class="form-control text-center extra-quantity" name="extras[${e.name}]" id="qty-extra-${e.name}" data-price="${parseFloat(e.price).toFixed(2)}" value="0" min="0" step="1" disabled>
   <button type="button" class="btn btn-outline-secondary plus-btn" data-target="qty-extra-${e.name}">+</button>
  </div>
 </div>
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
<div class="row align-items-center mb-2">
 <div class="col-auto">
  <input type="checkbox" class="form-check-input extra-checkbox" id="check-sauce-${e.name}" data-qtyid="qty-sauce-${e.name}">
 </div>
 <div class="col">
  <label class="form-check-label" for="check-sauce-${e.name}">${e.name} (+${parseFloat(e.price).toFixed(2)}€)</label>
 </div>
 <div class="col-auto">
  <div class="input-group input-group-sm" style="width:100px;">
   <button type="button" class="btn btn-outline-secondary minus-btn" data-target="qty-sauce-${e.name}">-</button>
   <input type="number" class="form-control text-center extra-quantity sauce-quantity" name="sauces[${e.name}]" id="qty-sauce-${e.name}" data-price="${parseFloat(e.price).toFixed(2)}" value="0" min="0" step="1" disabled>
   <button type="button" class="btn btn-outline-secondary plus-btn" data-target="qty-sauce-${e.name}">+</button>
  </div>
 </div>
</div>`;
                        });
                    }
                }
                let dc = form.querySelector('.dresses-container');
                if (dc) {
                    dc.innerHTML = '';
                    if (dd.length > 0) {
                        dd.forEach(e => {
                            dc.innerHTML += `
<div class="row align-items-center mb-2">
 <div class="col-auto">
  <input type="checkbox" class="form-check-input extra-checkbox" id="check-dress-${e.name}" data-qtyid="qty-dress-${e.name}">
 </div>
 <div class="col">
  <label class="form-check-label" for="check-dress-${e.name}">${e.name} (+${parseFloat(e.price).toFixed(2)}€)</label>
 </div>
 <div class="col-auto">
  <div class="input-group input-group-sm" style="width:100px;">
   <button type="button" class="btn btn-outline-secondary minus-btn" data-target="qty-dress-${e.name}">-</button>
   <input type="number" class="form-control text-center extra-quantity" name="dresses[${e.name}]" id="qty-dress-${e.name}" data-price="${parseFloat(e.price).toFixed(2)}" value="0" min="0" step="1" disabled>
   <button type="button" class="btn btn-outline-secondary plus-btn" data-target="qty-dress-${e.name}">+</button>
  </div>
 </div>
</div>`;
                        });
                    }
                }
            }

            function initializeEventListeners(form) {
                form.querySelectorAll('.extra-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const qtyInputId = checkbox.dataset.qtyid;
                        const qtyInput = document.getElementById(qtyInputId);
                        if (checkbox.checked) {
                            qtyInput.disabled = false;
                            if (parseInt(qtyInput.value) === 0) qtyInput.value = 1;
                        } else {
                            qtyInput.value = 0;
                            qtyInput.disabled = true;
                        }
                        updateEstimatedPrice(form);
                    });
                });
                form.querySelectorAll('.plus-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const qtyInputId = btn.dataset.target;
                        const qtyInput = document.getElementById(qtyInputId);
                        let currentValue = parseInt(qtyInput.value) || 0;
                        qtyInput.value = currentValue + 1;
                        if (qtyInput.value > 0) {
                            const checkboxes = form.querySelectorAll('.extra-checkbox');
                            checkboxes.forEach(chk => {
                                if (chk.dataset.qtyid === qtyInputId) chk.checked = true;
                            });
                            qtyInput.disabled = false;
                        }
                        updateEstimatedPrice(form);
                        if (qtyInput.classList.contains('sauce-quantity')) {
                            window.lastChangedInput['sauces'] = qtyInput;
                            limitItemQuantities(form, 'sauces');
                        }
                        if (qtyInputId.includes('extra-')) {
                            window.lastChangedInput['extras'] = qtyInput;
                            limitItemQuantities(form, 'extras');
                        }
                        if (qtyInputId.includes('dress-')) {
                            window.lastChangedInput['dresses'] = qtyInput;
                            limitItemQuantities(form, 'dresses');
                        }
                    });
                });
                form.querySelectorAll('.minus-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const qtyInputId = btn.dataset.target;
                        const qtyInput = document.getElementById(qtyInputId);
                        let currentValue = parseInt(qtyInput.value) || 0;
                        if (currentValue > 0) qtyInput.value = currentValue - 1;
                        if (parseInt($qtyInput.value) === 0) {
                            const checkboxes = form.querySelectorAll('.extra-checkbox');
                            checkboxes.forEach(chk => {
                                if (chk.dataset.qtyid === qtyInputId) chk.checked = false;
                            });
                            qtyInput.disabled = true;
                        }
                        updateEstimatedPrice(form);
                        if (qtyInput.classList.contains('sauce-quantity')) {
                            window.lastChangedInput['sauces'] = qtyInput;
                            limitItemQuantities(form, 'sauces');
                        }
                        if (qtyInputId.includes('extra-')) {
                            window.lastChangedInput['extras'] = qtyInput;
                            limitItemQuantities(form, 'extras');
                        }
                        if (qtyInputId.includes('dress-')) {
                            window.lastChangedInput['dresses'] = qtyInput;
                            limitItemQuantities(form, 'dresses');
                        }
                    });
                });
                form.querySelectorAll('.extra-quantity').forEach(inp => {
                    inp.addEventListener('input', function() {
                        const val = parseInt(inp.value) || 0;
                        if (val > 0) {
                            const checkboxes = form.querySelectorAll('.extra-checkbox');
                            checkboxes.forEach(chk => {
                                if (chk.dataset.qtyid === inp.id) chk.checked = true;
                            });
                            inp.disabled = false;
                        } else {
                            const checkboxes = form.querySelectorAll('.extra-checkbox');
                            checkboxes.forEach(chk => {
                                if (chk.dataset.qtyid === inp.id) chk.checked = false;
                            });
                            inp.disabled = true;
                        }
                        updateEstimatedPrice(form);
                        if (inp.classList.contains('sauce-quantity')) {
                            window.lastChangedInput['sauces'] = inp;
                            limitItemQuantities(form, 'sauces');
                        }
                        if (inp.id.includes('extra-')) {
                            window.lastChangedInput['extras'] = inp;
                            limitItemQuantities(form, 'extras');
                        }
                        if (inp.id.includes('dress-')) {
                            window.lastChangedInput['dresses'] = inp;
                            limitItemQuantities(form, 'dresses');
                        }
                    });
                });
                let sz = form.querySelector('.size-selector');
                if (sz) {
                    sz.addEventListener('change', function() {
                        let sd = {
                            sizesExtras: this.selectedOptions[0].dataset.sizesExtras || '[]',
                            sizesSauces: this.selectedOptions[0].dataset.sizesSauces || '[]',
                            sizesDresses: this.selectedOptions[0].dataset.sizesDresses || '[]'
                        };
                        updateSizeSpecificOptions(form, sd);
                        form.querySelectorAll('.extra-checkbox').forEach(cb => {
                            cb.checked = false;
                            let qiId = cb.dataset.qtyid;
                            if (qiId) {
                                let iq = document.getElementById(qiId);
                                iq.value = 0;
                                iq.disabled = true;
                            }
                        });
                        initializeEventListeners(form);
                        updateEstimatedPrice(form);
                    });
                }
                let dr = form.querySelector('.drink-selector');
                if (dr) {
                    dr.addEventListener('change', () => updateEstimatedPrice(form));
                }
                let qty = form.querySelector('.quantity-selector');
                if (qty) {
                    qty.addEventListener('change', () => updateEstimatedPrice(form));
                }
                updateEstimatedPrice(form);
            }
            document.querySelectorAll('.add-to-cart-form').forEach(f => {
                initializeEventListeners(f);
            });
            let observer = new MutationObserver(muts => {
                muts.forEach(mu => {
                    if (mu.type === 'childList') {
                        mu.addedNodes.forEach(n => {
                            if (n.nodeType === Node.ELEMENT_NODE) {
                                n.querySelectorAll('.add-to-cart-form').forEach(ff => {
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
            paypal.Buttons({
                createOrder: function(data, actions) {
                    let t = <?= json_encode(number_format($total_with_zone_price, 2, '.', '')) ?>;
                    return actions.order.create({
                        purchase_units: [{
                            description: "Order (no pending ID)",
                            amount: {
                                currency_code: "EUR",
                                value: t
                            }
                        }]
                    });
                },
                onApprove: function(data, actions) {
                    return actions.order.capture().then(function(details) {
                        alert("Payment success (no pending order ID). Server needs to handle capture info.");
                    });
                },
                onCancel: function(data) {
                    alert("Payment was canceled.");
                },
                onError: function(err) {
                    console.error("PayPal error:", err);
                    alert("PayPal error occurred.");
                }
            }).render('#paypal-button-container');
        });
    </script>
</body>

</html>
<?php ob_end_flush(); ?>