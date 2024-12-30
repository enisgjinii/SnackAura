<?php
ob_start();
require 'vendor/autoload.php';

use Stripe\Stripe;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\{Amount, Payer, Payment, RedirectUrls, Transaction, PaymentExecution};

Stripe::setApiKey("sk_test_XXX");
$paypal = new ApiContext(new OAuthTokenCredential(
    'AfbPMlmPT4z37DRzH886cPd1AggGZjz-L_LnVJxx_Odv7AB82AQ9CIz8P_s-5cjgLf-Ndgpng0NLAiWr',
    'EKbX-h3EwnMlRoAyyGBCFi2370doQi06hO6iOiQJsQ1gDnpvTrwYQIyTG2MxG6H1vVuWpz_Or76JTThi'
));
$paypal->setConfig(['mode' => 'sandbox']);

session_start();
require 'includes/db_connect.php';

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
define('ERROR_LOG_FILE', __DIR__ . '/errors.md');

function log_error_markdown($msg, $ctx = '')
{
    $t = date('Y-m-d H:i:s');
    $m = "### [$t] Error\n\n**Message:** "
       . htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n\n";
    if ($ctx) {
        $m .= "**Context:** " . htmlspecialchars($ctx, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n\n";
    }
    $m .= "---\n\n";
    file_put_contents(ERROR_LOG_FILE, $m, FILE_APPEND | LOCK_EX);
}

set_exception_handler(function ($e) {
    log_error_markdown("Uncaught Exception: " . $e->getMessage(),
        "File: {$e->getFile()} Line: {$e->getLine()}");
    header("Location: index.php?error=unknown_error");
    exit;
});

set_error_handler(function ($s, $m, $f, $l) {
    if (!(error_reporting() & $s)) return;
    throw new ErrorException($m, 0, $s, $f, $l);
});

include 'settings_fetch.php';

$_SESSION['cart'] ??= [];

$is_closed = false;
$notification = [];

/** 
 * If user has selected a store, load that store's schedule.
 * Otherwise, we won't parse schedule until they pick a store.
 */
$selected_store_id = $_SESSION['selected_store'] ?? null;
$main_store = null;
if ($selected_store_id) {
    $stmt = $pdo->prepare("SELECT * FROM stores WHERE id = ? AND is_active = 1");
    $stmt->execute([$selected_store_id]);
    $main_store = $stmt->fetch(PDO::FETCH_ASSOC);
}

/** 
 * Parse the store's schedule only if we actually have a selected store.
 */
if ($main_store) {
    $decoded = @json_decode($main_store['work_schedule'] ?? '', true);
    if (!is_array($decoded)) {
        $decoded = [];
    }
    $todayStr = date('Y-m-d');
    $todayName = date('l');
    $nowTime = date('H:i');

    $holidays = $decoded['holidays'] ?? [];
    foreach ($holidays as $h) {
        if (!empty($h['date']) && $h['date'] === $todayStr) {
            $is_closed = true;
            $notification = [
                'title' => 'Store Closed',
                'message' => !empty($h['desc']) ? $h['desc'] : "Closed for holiday."
            ];
            break;
        }
    }

    if (!$is_closed) {
        $days = $decoded['days'] ?? [];
        if (isset($days[$todayName])) {
            $start = $days[$todayName]['start'] ?? '';
            $end   = $days[$todayName]['end']   ?? '';
            if (empty($start) || empty($end)) {
                $is_closed = true;
                $notification = [
                    'title' => 'Store Closed',
                    'message' => "Closed today ($todayName)."
                ];
            } else {
                if ($nowTime < $start || $nowTime > $end) {
                    $is_closed = true;
                    $notification = [
                        'title' => 'Store Closed',
                        'message' => "Operating hours today: $start - $end"
                    ];
                }
            }
        } else {
            $is_closed = true;
            $notification = [
                'title' => 'Store Closed',
                'message' => "No schedule defined for $todayName."
            ];
        }
    }
} else {
    // If user has not selected a store yet, we won't parse. 
    // We'll show them the store selection modal.
    // We can also do a fallback:
    $is_closed = true;
    $notification = [
        'title' => 'No Store Selected',
        'message' => 'Please select a store before ordering.'
    ];
}

try {
    $tip_options = $pdo->query(
        "SELECT * FROM tips WHERE is_active=1 ORDER BY percentage ASC,amount ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_error_markdown(
        "Failed to fetch tips: " . $e->getMessage(),
        "Fetching Tips"
    );
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
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id=:p AND is_active=1");
    $stmt->execute(['p' => $pid]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) return null;
    $props = json_decode($product['properties'] ?? '{}', true) ?: [];
    return [
        'sizes'       => $props['sizes']   ?? [],
        'extras'      => $props['extras']  ?? [],
        'sauces'      => $props['sauces']  ?? [],
        'dresses'     => $props['dresses'] ?? [],
        'base_price'  => (float)($props['base_price'] ?? $product['base_price']),
        'name'        => $product['name'],
        'description' => $product['description'],
        'image_url'   => $product['image_url']
    ];
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
            return !empty($t['percentage'])
                ? $cart_total * ((float)$t['percentage'] / 100)
                : (float)$t['amount'];
        }
    }
    return 0.0;
}

function computeShipping($settings, $userLat, $userLng)
{
    if (empty($userLat) || empty($userLng) 
        || $settings['shipping_calculation_mode'] === 'pickup'
    ) return 0.0;

    $mode    = $settings['shipping_calculation_mode'] ?? 'radius';
    $storeLat = (float)($settings['store_lat'] ?? 0);
    $storeLng = (float)($settings['store_lng'] ?? 0);
    $baseFee  = (float)($settings['shipping_fee_base'] ?? 0);
    $feeKm    = (float)($settings['shipping_fee_per_km'] ?? 0);
    $radius   = (float)($settings['shipping_distance_radius'] ?? 0);
    $thresh   = (float)($settings['shipping_free_threshold'] ?? 9999);

    $postalZonesRaw = $settings['postal_code_zones'] ?? '';
    $postalZones    = [];
    @($postalZones = json_decode($postalZonesRaw, true));

    $dist = 0.0;
    $shippingFee = 0.0;

    if ($mode === 'postal' || $mode === 'both') {
        if (isset($postalZones[$_SESSION['delivery_address'] ?? ''])) {
            $shippingFee = (float)$postalZones[$_SESSION['delivery_address']];
            $dist = 0;
        } elseif ($mode === 'both') {
            $dist = haversineDist($storeLat, $storeLng, (float)$userLat, (float)$userLng);
            $shippingFee = $baseFee + ($dist * $feeKm);
        } else {
            return 0.0;
        }
    } else {
        $dist = haversineDist($storeLat, $storeLng, (float)$userLat, (float)$userLng);
        $shippingFee = $baseFee + ($dist * $feeKm);
    }

    if ($dist > $radius && $mode !== 'postal') return 0.0;
    return $shippingFee < 0 ? 0 : $shippingFee;
}

function haversineDist($lat1, $lon1, $lat2, $lon2)
{
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2)
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
       * sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}

/** 
 * Handling of POST requests
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['store_id'])) {
        $sid = (int)$_POST['store_id'];
        $st = $pdo->prepare("SELECT name, address FROM stores WHERE id=? AND is_active=1");
        $st->execute([$sid]);
        if ($s = $st->fetch(PDO::FETCH_ASSOC)) {
            $da = trim($_POST['delivery_address'] ?? '');
            $lat= trim($_POST['latitude'] ?? '');
            $lon= trim($_POST['longitude'] ?? '');
            if (empty($da) || empty($lat) || empty($lon)
                || !is_numeric($lat) || !is_numeric($lon)
            ) {
                $error_message = "Please provide a valid delivery address & location.";
            } else {
                $_SESSION['selected_store']   = $sid;
                $_SESSION['store_name']       = $s['name'];
                $_SESSION['delivery_address'] = $da;
                $_SESSION['latitude']         = $lat;
                $_SESSION['longitude']        = $lon;
                header("Location: index.php");
                exit;
            }
        } else {
            $error_message = "Selected store not available.";
        }
    }

    if (isset($_POST['add_to_cart'])) {
        $pid    = (int)$_POST['product_id'];
        $qty    = max(1, (int)$_POST['quantity']);
        $sz     = $_POST['size']   ?? null;
        $pEx    = $_POST['extras'] ?? [];
        $pSa    = $_POST['sauces'] ?? [];
        $pDr    = $_POST['dresses']?? [];
        $drink_id = isset($_POST['drink']) ? (int)$_POST['drink'] : null;
        $spec   = trim($_POST['special_instructions'] ?? '');
        $product= fetchProduct($pdo, $pid);

        if (!$product) {
            header("Location: index.php?error=invalid_product");
            exit;
        }

        $sel_size = null;
        if ($sz) {
            foreach ($product['sizes'] as $size) {
                if ($size['size'] === $sz) {
                    $sel_size = $size;
                    break;
                }
            }
            if (!$sel_size) {
                header("Location: index.php?error=invalid_size");
                exit;
            }
        }

        $base_price = $product['base_price'] + ($sel_size['price'] ?? 0);
        $extras_total = $sauces_total = $dresses_total = $drink_total = 0.0;
        $selected_extras = $selected_sauces = $selected_dresses = [];
        $selected_drink  = null;

        foreach (($sel_size ? $sel_size['extras'] : $product['extras']) as $e) {
            $qx = (int)($pEx[$e['name']] ?? 0);
            if ($qx > 0) {
                $extras_total += $e['price'] * $qx;
                $selected_extras[] = [
                    'name'     => $e['name'],
                    'price'    => $e['price'],
                    'quantity' => $qx
                ];
            }
        }
        foreach (($sel_size ? $sel_size['sauces'] : $product['sauces']) as $sa) {
            $qx = (int)($pSa[$sa['name']] ?? 0);
            if ($qx > 0) {
                $sauces_total += $sa['price'] * $qx;
                $selected_sauces[] = [
                    'name'     => $sa['name'],
                    'price'    => $sa['price'],
                    'quantity' => $qx
                ];
            }
        }
        foreach ($product['dresses'] as $d) {
            $qx = (int)($pDr[$d['name']] ?? 0);
            if ($qx > 0) {
                $dresses_total += $d['price'] * $qx;
                $selected_dresses[] = [
                    'name'     => $d['name'],
                    'price'    => $d['price'],
                    'quantity' => $qx
                ];
            }
        }
        if ($drink_id) {
            $stmt = $pdo->prepare("SELECT * FROM drinks WHERE id=?");
            $stmt->execute([$drink_id]);
            if ($dk = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $drink_total = (float)$dk['price'];
                $selected_drink = $dk;
            }
        }

        $unit_price = $base_price + $extras_total
                     + $sauces_total + $dresses_total
                     + $drink_total;
        $total_price= $unit_price * $qty;

        $cart_item = [
            'product_id' => $pid,
            'name'       => $product['name'],
            'description'=> $product['description'],
            'image_url'  => $product['image_url'],
            'size'       => $sz,
            'size_price' => $sel_size['price'] ?? 0.0,
            'extras'     => $selected_extras,
            'sauces'     => $selected_sauces,
            'dresses'    => $selected_dresses,
            'drink'      => $selected_drink,
            'special_instructions' => $spec,
            'quantity'   => $qty,
            'unit_price' => $unit_price,
            'total_price'=> $total_price
        ];

        foreach ($_SESSION['cart'] as $index => $item) {
            $sameDrink = (($item['drink']['id']??null) === ($cart_item['drink']['id']??null));
            if ($item['product_id'] === $cart_item['product_id']
                && $item['size'] === $cart_item['size']
                && $sameDrink
                && $item['special_instructions'] === $cart_item['special_instructions']
                && $item['extras'] == $cart_item['extras']
                && $item['sauces'] == $cart_item['sauces']
                && $item['dresses']== $cart_item['dresses']
            ) {
                $_SESSION['cart'][$index]['quantity']   += $qty;
                $_SESSION['cart'][$index]['total_price']+= $total_price;
                header("Location: index.php?added=1");
                exit;
            }
        }

        $_SESSION['cart'][]= $cart_item;
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
        $sz  = $_POST['size'] ?? null;
        $pEx = $_POST['extras'] ?? [];
        $pSa = $_POST['sauces'] ?? [];
        $pDr = $_POST['dresses']?? [];
        $drink_id= isset($_POST['drink'])?(int)$_POST['drink']:null;
        $spec    = trim($_POST['special_instructions'] ?? '');
        $pid     = $_SESSION['cart'][$ii]['product_id'];
        $pr      = fetchProduct($pdo, $pid);

        if (!$pr) {
            log_error_markdown("Product not found or inactive ID:$pid", "Updating Cart");
            header("Location: index.php?error=invalid_update");
            exit;
        }

        $props = json_decode($pr['properties'], true) ?? [];
        $base  = (float)($props['base_price'] ?? $pr['base_price']);
        $xopt  = $props['extras'] ?? $pr['extras'];
        $sopt  = $props['sauces'] ?? $pr['sauces'];
        $dopt  = $props['dresses']?? $pr['dresses'];
        $zopt  = $props['sizes']  ?? $pr['sizes'];
        $sel_size = null;
        $szprice  = 0.0;

        if ($sz) {
            foreach ($zopt as $z) {
                if (($z['size'] ?? '') === $sz) {
                    $sel_size = $z;
                    $szprice  = (float)($z['price'] ?? 0.0);
                    $xopt     = $z['extras'] ?? $xopt;
                    $sopt     = $z['sauces'] ?? $sopt;
                    $dopt     = $z['dresses']?? $dopt;
                    break;
                }
            }
            if (!$sel_size) {
                log_error_markdown("Invalid size: $sz for Product $pid","Updating Cart");
                header("Location: index.php?error=invalid_size");
                exit;
            }
        }

        $sel_ex=$sel_sa=$sel_dr=[];
        $exTot=$saTot=$drTot=$drinkTot=0.0;

        foreach ($xopt as $xo) {
            $nm    = $xo['name'];
            $price = (float)$xo['price'];
            if (isset($pEx[$nm])) {
                $qx = (int)$pEx[$nm];
                if ($qx>0) {
                    $exTot += $price*$qx;
                    $sel_ex[]= [
                        'name'=>$nm, 'price'=>$price,
                        'quantity'=>$qx
                    ];
                }
            }
        }
        foreach ($sopt as $sx) {
            $nm    = $sx['name'];
            $price = (float)$sx['price'];
            if (isset($pSa[$nm])) {
                $qx=(int)$pSa[$nm];
                if ($qx>0) {
                    $saTot+=$price*$qx;
                    $sel_sa[]= [
                        'name'=>$nm, 'price'=>$price,
                        'quantity'=>$qx
                    ];
                }
            }
        }
        foreach ($dopt as $dx) {
            $nm    = $dx['name'];
            $price = (float)$dx['price'];
            if (isset($pDr[$nm])) {
                $qx=(int)$pDr[$nm];
                if ($qx>0) {
                    $drTot+=$price*$qx;
                    $sel_dr[]= [
                        'name'=>$nm, 'price'=>$price,
                        'quantity'=>$qx
                    ];
                }
            }
        }
        if ($drink_id) {
            $stmt=$pdo->prepare("SELECT * FROM drinks WHERE id=?");
            $stmt->execute([$drink_id]);
            if ($dk=$stmt->fetch(PDO::FETCH_ASSOC)) {
                $drinkTot=(float)($dk['price'] ?? 0.0);
                $drink_details=$dk;
            }
        }

        $unit_price = $base + $szprice + $exTot + $saTot + $drTot + $drinkTot;
        $total_price= $unit_price*$qty;

        $_SESSION['cart'][$ii]= [
            'product_id'   => $pr['id'],
            'name'         => $pr['name'],
            'description'  => $pr['description'],
            'image_url'    => $pr['image_url'],
            'size'         => $sel_size['size']??null,
            'size_price'   => $szprice,
            'extras'       => $sel_ex,
            'sauces'       => $sel_sa,
            'dresses'      => $sel_dr,
            'drink'        => $drink_details??null,
            'special_instructions'=> $spec,
            'quantity'     => $qty,
            'unit_price'   => $unit_price,
            'total_price'  => $total_price
        ];
        header("Location: index.php?updated=1");
        exit;
    }

    if (isset($_POST['checkout'])) {
        if (empty($_SESSION['cart'])) {
            header("Location: index.php?error=empty_cart");
            exit;
        }
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            header("Location: index.php?error=invalid_csrf_token");
            exit;
        }
        $store_id = $_SESSION['selected_store'] ?? null;
        $cn   = trim($_POST['customer_name']  ?? '');
        $ce   = trim($_POST['customer_email'] ?? '');
        $cp   = trim($_POST['customer_phone'] ?? '');
        $da   = trim($_POST['delivery_address']??'');
        $stid = $_POST['selected_tip'] ?? null;
        $ev   = isset($_POST['is_event']) && $_POST['is_event']=='1';
        $sd   = $_POST['scheduled_date'] ?? null;
        $st_time=$_POST['scheduled_time']??null;

        if ($ev) {
            $sdt = DateTime::createFromFormat('Y-m-d H:i', "$sd $st_time");
            if (!$sdt || $sdt<new DateTime()) {
                header("Location: index.php?error=invalid_scheduled_datetime");
                exit;
            }
        }
        $pm = $_POST['payment_method'] ?? '';
        if (!in_array($pm, ['stripe','paypal','pickup','cash'])) {
            header("Location: index.php?error=invalid_payment_method");
            exit;
        }
        if (!$cn||!$ce||!$cp||!$da) {
            header("Location: index.php?error=invalid_order_details");
            exit;
        }

        $cart_total = calculateCartTotal($_SESSION['cart']);
        $tip_amount = applyTip($cart_total, $tip_options, $stid);

        $shipping=0.0;
        if ($pm!=='pickup') {
            require_once 'settings_fetch.php';
            $shipping= computeShipping($current_settings,
                $_SESSION['latitude']  ?? 0,
                $_SESSION['longitude'] ?? 0
            );
        }
        if ($cart_total>=(float)($current_settings['shipping_free_threshold'] ?? 9999)) {
            $shipping=0.0;
        }

        $total= $cart_total + $tip_amount + $shipping;
        $order_details = json_encode([
            'items'         => $_SESSION['cart'],
            'latitude'      => $_SESSION['latitude']  ?? null,
            'longitude'     => $_SESSION['longitude'] ?? null,
            'tip_id'        => $stid,
            'tip_amount'    => $tip_amount,
            'store_id'      => $store_id,
            'is_event'      => $ev,
            'scheduled_date'=> $sd,
            'scheduled_time'=> $st_time,
            'shipping_fee'  => $shipping
        ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);

        try {
            $pdo->beginTransaction();
            $stmt=$pdo->prepare("
                INSERT INTO orders(user_id,customer_name,customer_email,customer_phone,delivery_address,total_amount,status_id,tip_id,tip_amount,scheduled_date,scheduled_time,payment_method,store_id,order_details)
                VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                null, $cn, $ce, $cp, $da,
                $total, 2, $stid, $tip_amount,
                $sd, $st_time, $pm, $store_id,
                $order_details
            ]);
            $oid= $pdo->lastInsertId();

            if (in_array($pm,['stripe','paypal'])) {
                if ($pm==='stripe') {
                    $spm=$_POST['stripe_payment_method']??'';
                    if (!$spm) throw new Exception("Stripe payment method ID missing.");
                    $amtC= intval(round($total*100));
                    $ret = "http://".$_SERVER['HTTP_HOST']."/index.php?order=success&scheduled_date="
                         . urlencode($sd) ."&scheduled_time=". urlencode($st_time);

                    $pi=\Stripe\PaymentIntent::create([
                        'amount'               => $amtC,
                        'currency'             => 'eur',
                        'payment_method'       => $spm,
                        'confirmation_method'  => 'manual',
                        'confirm'              => true,
                        'description'          => "Order ID:$oid",
                        'metadata'             => ['order_id'=>$oid],
                        'return_url'           => $ret
                    ]);
                    if ($pi->status==='requires_action'
                        && $pi->next_action->type==='use_stripe_sdk'
                    ) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'requires_action'=>true,
                            'payment_intent_client_secret'=>$pi->client_secret
                        ]);
                        exit;
                    } elseif ($pi->status==='succeeded') {
                        $pdo->prepare(
                            "UPDATE orders SET status_id=? WHERE id=?"
                        )->execute([5, $oid]);
                        $pdo->commit();
                        $_SESSION['cart'] = [];
                        $_SESSION['selected_tip'] = null;
                        echo json_encode([
                            'success'=>true,
                            'redirect_url'=>$ret
                        ]);
                        exit;
                    } else {
                        throw new Exception("Invalid PaymentIntent status: " . $pi->status);
                    }
                } elseif ($pm==='paypal') {
                    $payer=new Payer();
                    $payer->setPaymentMethod('paypal');

                    $amount=new Amount();
                    $amount->setTotal(number_format($total,2,'.',''))
                           ->setCurrency('EUR');

                    $transaction=new Transaction();
                    $transaction->setAmount($amount)
                                ->setDescription("Order ID: $oid");

                    $redirectUrls=new RedirectUrls();
                    $redirectUrls->setReturnUrl(
                        "http://".$_SERVER['HTTP_HOST']."/index.php?payment=paypal&success=true&order_id=$oid"
                    )->setCancelUrl(
                        "http://".$_SERVER['HTTP_HOST']."/index.php?payment=paypal&success=false&order_id=$oid"
                    );

                    $payment=new Payment();
                    $payment->setIntent('sale')
                            ->setPayer($payer)
                            ->setTransactions([$transaction])
                            ->setRedirectUrls($redirectUrls);
                    $payment->create($paypal);
                    $_SESSION['paypal_payment_id']=$payment->getId();

                    foreach($payment->getLinks()as$link) {
                        if($link->getRel()==='approval_url'){
                            header("Location: ".$link->getHref());
                            exit;
                        }
                    }
                    throw new Exception("No approval URL found for PayPal.");
                }
            } else {
                $status_map=['pickup'=>3,'cash'=>4];
                $pdo->prepare("UPDATE orders SET status_id=? WHERE id=?")
                    ->execute([$status_map[$pm],$oid]);
                $pdo->commit();
                $_SESSION['cart']=[];
                $_SESSION['selected_tip']=null;
                header("Location: index.php?order=success&scheduled_date=$sd&scheduled_time=$st_time");
                exit;
            }
        } catch (Exception $e) {
            if($pdo->inTransaction())$pdo->rollBack();
            log_error_markdown(
                "Order Placement Failed: " . $e->getMessage(),
                "Checkout"
            );
            header("Location: index.php?error=order_failed");
            exit;
        }
    }
}

if (isset($_GET['payment']) && $_GET['payment']==='paypal') {
    $success = $_GET['success']==='true';
    $order_id= (int)($_GET['order_id']??0);
    if ($order_id<=0) {
        log_error_markdown("Invalid PayPal order ID.","PayPal Callback");
        header("Location: index.php?error=invalid_order");
        exit;
    }
    if ($success) {
        $paymentId=$_SESSION['paypal_payment_id']??'';
        $payerId  =$_GET['PayerID']??'';
        if (!$paymentId||!$payerId) {
            log_error_markdown("Missing PayPal payment details.","PayPal Callback");
            header("Location: index.php?error=paypal_failed");
            exit;
        }
        try {
            $payment=Payment::get($paymentId,$paypal);
            $execution=new PaymentExecution();
            $execution->setPayerId($payerId);
            $result=$payment->execute($execution,$paypal);
            if ($result->getState()==='approved') {
                $pdo->prepare("UPDATE orders SET status_id=? WHERE id=?")
                    ->execute([5,$order_id]);
                $pdo->commit();
                $_SESSION['cart']=[];
                $_SESSION['selected_tip']=null;
                header("Location: index.php?order=success&scheduled_date="
                    .urlencode($_SESSION['scheduled_date']??'')
                    ."&scheduled_time="
                    .urlencode($_SESSION['scheduled_time']??''));
                exit;
            } else {
                throw new Exception("PayPal not approved.");
            }
        } catch (Exception $e) {
            log_error_markdown(
                "PayPal Failed: " . $e->getMessage(),
                "PayPal Callback"
            );
            header("Location: index.php?error=paypal_failed");
            exit;
        }
    } else {
        header("Location: index.php?error=paypal_canceled");
        exit;
    }
}

try {
    $categories=$pdo->prepare("SELECT * FROM categories ORDER BY position ASC,name ASC");
    $categories->execute();
    $categories=$categories->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_error_markdown("Failed to fetch categories: ".$e->getMessage(),"Categories");
    $categories=[];
}

$selC = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$q = "SELECT p.id as product_id,p.product_code,p.name as product_name,p.category,p.description,p.allergies,p.image_url,p.is_new,p.is_offer,p.is_active,p.properties,p.base_price,p.created_at,p.updated_at,p.category_id 
      FROM products p 
      WHERE p.is_active=1"
      .($selC>0 ? " AND p.category_id=:c":"")
      ." ORDER BY p.created_at DESC";
$stmt=$pdo->prepare($q);
if($selC>0)$stmt->bindParam(':c',$selC,PDO::PARAM_INT);
$stmt->execute();
$raw=$stmt->fetchAll(PDO::FETCH_ASSOC);

$products=array_map(function($r){
    $pp = json_decode($r['properties'], true)??[];
    $sz = $pp['sizes']??[];
    $dbp= (float)($pp['base_price']?? $r['base_price']);
    $dp = $dbp+ (count($sz)
         ? min(array_map(fn($s)=>(float)($s['price']??0.0), $sz))
         : 0.0);
    return [
        'id'         => $r['product_id'],
        'product_code'=>$r['product_code'],
        'name'       => $r['product_name'],
        'category'   => $r['category'],
        'description'=> $r['description'],
        'allergies'  => $r['allergies'],
        'image_url'  => $r['image_url'],
        'is_new'     => $r['is_new'],
        'is_offer'   => $r['is_offer'],
        'is_active'  => $r['is_active'],
        'base_price' => $dbp,
        'created_at' => $r['created_at'],
        'updated_at' => $r['updated_at'],
        'category_id'=> $r['category_id'],
        'extras'     => $pp['extras'] ?? [],
        'sauces'     => $pp['sauces'] ?? [],
        'sizes'      => $sz,
        'dresses'    => $pp['dresses']??[],
        'display_price'=> $dp
    ];
}, $raw);

try {
    $drinks = $pdo->query("SELECT * FROM drinks ORDER BY name ASC")
                  ->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e){
    log_error_markdown("Failed to fetch drinks: ".$e->getMessage(),"Drinks");
    $drinks=[];
}

$cart_total       = calculateCartTotal($_SESSION['cart']);
$selT             = $_SESSION['selected_tip'] ?? null;
$tip_amount       = applyTip($cart_total, $tip_options, $selT);
$cart_total_with_tip= $cart_total+$tip_amount;

try {
    $banners=$pdo->prepare("
        SELECT * FROM banners 
        WHERE is_active=1 
        ORDER BY created_at DESC
    ");
    $banners->execute();
    $banners=$banners->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e){
    log_error_markdown("Failed to fetch banners: ".$e->getMessage(),"Banners");
    $banners=[];
}

try {
    $d0=date('Y-m-d');
    $active_offers=$pdo->prepare("
        SELECT * FROM offers 
        WHERE is_active=1 
          AND (start_date IS NULL OR start_date<=?) 
          AND (end_date IS NULL OR end_date>=?)
        ORDER BY created_at DESC
    ");
    $active_offers->execute([$d0,$d0]);
    $active_offers=$active_offers->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e){
    log_error_markdown("Failed to fetch offers: ".$e->getMessage(),"Offers");
    $active_offers=[];
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
    <?php if (!empty($cart_logo) && file_exists($cart_logo)): ?>
        <link rel="icon" type="image/png" href="<?= htmlspecialchars($cart_logo); ?>">
    <?php endif; ?>
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
    <script src="https://js.stripe.com/v3/"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v7.3.0/ol.css" type="text/css">
    <script src="https://cdn.jsdelivr.net/npm/ol@v7.3.0/dist/ol.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
</head>

<body>
    <?php if ($is_closed): ?>
        <div class="alert alert-danger text-center m-0" role="alert">
            <strong><?= htmlspecialchars($notification['title'] ?? 'Closed') ?></strong>: <?= htmlspecialchars($notification['message'] ?? 'We are currently closed.') ?>
        </div>
    <?php endif; ?>
    <div class="loading-overlay" id="loading-overlay">
        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
    </div>
    <?php if (!isset($_SESSION['selected_store'])): ?>
        <div class="modal fade" id="storeModal" tabindex="-1" aria-labelledby="storeModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <form method="POST" id="storeSelectionForm">
                        <div class="modal-header">
                            <img src="<?= htmlspecialchars($cart_logo) ?>" alt="Cart Logo" style="width:100%;height:80px;object-fit:cover" onerror="this.src='https://via.placeholder.com/150?text=Logo';">
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <?php if (isset($error_message)): ?><div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Choose a Store</label>
                                <div class="row" id="storeCardsContainer">
                                    <?php $stores = $pdo->query("SELECT id,name,address FROM stores WHERE is_active=1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($stores as $st): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card store-card h-100" data-store-id="<?= htmlspecialchars($st['id']) ?>">
                                                <div class="card-body d-flex flex-column">
                                                    <h5 class="card-title"><?= htmlspecialchars($st['name']) ?></h5>
                                                    <p class="card-text"><?= htmlspecialchars($st['address'] ?? 'No address provided') ?></p>
                                                    <div class="mt-auto">
                                                        <input type="radio" name="store_id" value="<?= htmlspecialchars($st['id']) ?>" class="form-check-input visually-hidden">
                                                        <button type="button" class="btn btn-outline-primary select-store-btn">Select Store</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="delivery_address" class="form-label">Delivery Address</label>
                                <input type="text" class="form-control" id="delivery_address" name="delivery_address" required>
                            </div>
                            <div class="mb-3"><label class="form-label">Select Location on Map</label>
                                <div id="map" style="height:300px;width:100%"></div>
                            </div>
                            <input type="hidden" id="latitude" name="latitude"><input type="hidden" id="longitude" name="longitude">
                        </div>
                        <div class="modal-footer"><button type="submit" class="btn btn-primary">Confirm</button></div>
                    </form>
                </div>
            </div>
        </div>
        <style>
            .store-card.selected {
                border: 2px solid #0d6efd;
                background-color: #e7f1ff
            }

            .store-card .select-store-btn {
                width: 100%
            }
        </style>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-sA+ePHpVCRq+bN6Acf3XkQ/qnn+qsqMyIAGtFe6lgT0=" crossorigin="">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-o9N1jzUyhDtw0/hR8Z4k5IV8ab/IyX1h8+8G+8aK4Cg=" crossorigin=""></script>
        <script>
            $(function() {
                $('#storeModal').modal({
                    backdrop: 'static',
                    keyboard: false
                });
                $('#storeModal').modal('show');
                $('.select-store-btn').on('click', function() {
                    $('.store-card').removeClass('selected');
                    $('.store-card input[name="store_id"]').prop('checked', false);
                    let c = $(this).closest('.store-card');
                    c.addClass('selected');
                    c.find('input[name="store_id"]').prop('checked', true);
                });
                let map = L.map('map').setView([51.505, -0.09], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(map);
                let marker = null;
                map.on('click', function(e) {
                    if (marker) map.removeLayer(marker);
                    marker = L.marker(e.latlng).addTo(map);
                    $('#latitude').val(e.latlng.lat);
                    $('#longitude').val(e.latlng.lng);
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${e.latlng.lat}&lon=${e.latlng.lng}`).then(r => r.json()).then(d => {
                        if (d.display_name) $('#delivery_address').val(d.display_name);
                    });
                });
                $('#delivery_address').on('change', function() {
                    let a = $(this).val();
                    if (a.length > 5) {
                        fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(a)}`).then(r => r.json()).then(d => {
                            if (d && d.length > 0) {
                                if (marker) map.removeLayer(marker);
                                let lat = parseFloat(d[0].lat),
                                    lon = parseFloat(d[0].lon);
                                marker = L.marker([lat, lon]).addTo(map);
                                map.setView([lat, lon], 13);
                                $('#latitude').val(lat);
                                $('#longitude').val(lon);
                            }
                        });
                    }
                });
                $('#storeSelectionForm').on('submit', function(e) {
                    if (!$('input[name="store_id"]:checked').val()) {
                        e.preventDefault();
                        alert('Please select a store.');
                        return;
                    }
                    if (!$('#delivery_address').val().trim() || !$('#latitude').val() || !$('#longitude').val()) {
                        e.preventDefault();
                        alert('Please provide address & map location.');
                    }
                });
            });
        </script>
    <?php endif;
    $inc = ['edit_cart.php', 'header.php', 'reservation.php', 'promotional_banners.php', 'special_offers.php', 'checkout.php', 'order_success.php', 'cart_modal.php', 'toast_notifications.php', 'agb_modal.php', 'impressum_modal.php', 'datenschutz_modal.php', 'ratings.php', 'store_close.php'];
    foreach ($inc as $f) include $f; ?>
    <div class="modal fade" id="checkoutModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="index.php" id="checkoutForm">
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="customer_name" required></div>
                        <div class="mb-3"><label class="form-label">E-Mail <span class="text-danger">*</span></label><input type="email" class="form-control" name="customer_email" required></div>
                        <div class="mb-3"><label class="form-label">Telefon <span class="text-danger">*</span></label><input type="tel" class="form-control" name="customer_phone" required></div>
                        <div class="mb-3"><label class="form-label">Lieferadresse <span class="text-danger">*</span></label><textarea class="form-control" name="delivery_address" rows="2" required><?= htmlspecialchars($_SESSION['delivery_address'] ?? '') ?></textarea></div>
                        <div class="mb-3"><label><input class="form-check-input" type="checkbox" name="is_event" value="1" id="event_checkbox"> Veranstaltung</label></div>
                        <div id="event_details" style="display:none">
                            <div class="mb-3"><label class="form-label">Bevorzugtes Lieferdatum <span class="text-danger">*</span></label><input type="date" class="form-control" name="scheduled_date" min="<?= date('Y-m-d') ?>"></div>
                            <div class="mb-3"><label class="form-label">Bevorzugte Lieferzeit <span class="text-danger">*</span></label><input type="time" class="form-control" name="scheduled_time"></div>
                        </div>
                        <script>
                            document.getElementById('event_checkbox').addEventListener('change', function() {
                                let ed = document.getElementById('event_details'),
                                    sd = document.querySelector('[name="scheduled_date"]'),
                                    st = document.querySelector('[name="scheduled_time"]');
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
                            <div class="form-check"><input class="form-check-input" type="radio" name="payment_method" value="stripe" required id="paymentStripe"><label class="form-check-label" for="paymentStripe">Stripe</label></div>
                            <div class="form-check"><input class="form-check-input" type="radio" name="payment_method" value="paypal" required id="paymentPayPal"><label class="form-check-label" for="paymentPayPal">PayPal</label></div>
                            <div class="form-check"><input class="form-check-input" type="radio" name="payment_method" value="pickup" required id="paymentPickup"><label class="form-check-label" for="paymentPickup">Abholung</label></div>
                            <div class="form-check"><input class="form-check-input" type="radio" name="payment_method" value="cash" required id="paymentCash"><label class="form-check-label" for="paymentCash">Nachnahme</label></div>
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
                                    <option value="<?= htmlspecialchars($t['id']) ?>" <?= ($selT == $t['id']) ? ' selected' : '' ?>>
                                        <?= htmlspecialchars($t['name']) ?><?= (empty($t['percentage']) ? '' : " ({$t['percentage']}%)") ?><?= (empty($t['amount']) ? '' : "(+ " . number_format($t['amount'], 2) . "€)") ?>
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
                                            <div class="fw-bold"><?= htmlspecialchars($it['name']) ?> x<?= htmlspecialchars($it['quantity']) ?><?= isset($it['size']) ? " (Größe: {$it['size']})" : '' ?></div>
                                            <?php if (!empty($it['extras']) || !empty($it['sauces']) || !empty($it['drink']) || !empty($it['special_instructions'])): ?>
                                                <ul class="mb-1">
                                                    <?php if (!empty($it['extras'])): ?>
                                                        <li><strong>Extras:</strong>
                                                            <ul>
                                                                <?php foreach ($it['extras'] as $e): ?>
                                                                    <li><?= htmlspecialchars($e['name']) ?> x<?= htmlspecialchars($e['quantity']) ?> (<?= number_format($e['price'], 2) ?>€)=<?= number_format($e['price'] * $e['quantity'], 2) ?>€</li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </li>
                                                    <?php endif;
                                                    if (!empty($it['sauces'])): ?>
                                                        <li><strong>Saucen:</strong>
                                                            <ul>
                                                                <?php foreach ($it['sauces'] as $s): ?>
                                                                    <li><?= htmlspecialchars($s['name']) ?> x<?= htmlspecialchars($s['quantity']) ?> (<?= number_format($s['price'], 2) ?>€)=<?= number_format($s['price'] * $s['quantity'], 2) ?>€</li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </li>
                                                    <?php endif;
                                                    if (!empty($it['drink'])): ?>
                                                        <li><strong>Getränk:</strong> <?= htmlspecialchars($it['drink']['name']) ?> (<?= number_format($it['drink']['price'], 2) ?>€)</li>
                                                    <?php endif;
                                                    if (!empty($it['special_instructions'])): ?>
                                                        <li><strong>Besondere Anweisungen:</strong> <?= htmlspecialchars($it['special_instructions']) ?></li>
                                                    <?php endif; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge bg-primary rounded-pill"><?= number_format($it['total_price'], 2) ?>€</span>
                                    </div>
                                </li>
                            <?php endforeach;
                            if ($tip_amount > 0): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center"><strong>Trinkgeld</strong><span><?= number_format($tip_amount, 2) ?>€</span></li>
                            <?php endif; ?>
                        </ul>
                        <h4>Gesamtsumme: <?= number_format($cart_total_with_tip, 2) ?>€</h4>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="checkout" value="1">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary">Bestellung aufgeben</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <ul class="nav nav-tabs justify-content-center my-4">
        <li class="nav-item"><a class="nav-link <?= ($selC === 0) ? 'active' : '' ?>" href="index.php">All</a></li>
        <?php foreach ($categories as $c): ?>
            <li class="nav-item"><a class="nav-link <?= ($selC === $c['id']) ? 'active' : '' ?>" href="index.php?category_id=<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></a></li>
        <?php endforeach; ?>
    </ul>
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
                                            <span class="badge <?= ($pd['is_new'] ? 'bg-success' : 'bg-warning text-dark') ?> position-absolute <?= ($pd['is_offer'] ? 'top-40' : 'top-0') ?> end-0 m-2"><?= ($pd['is_new'] ? 'New' : 'Offer') ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title"><?= htmlspecialchars($pd['name']) ?></h5>
                                        <p class="card-text"><?= htmlspecialchars($pd['description']) ?></p>
                                        <?php if ($pd['allergies']): ?>
                                            <p class="card-text text-danger"><strong>Allergies:</strong> <?= htmlspecialchars($pd['allergies']) ?></p>
                                        <?php endif; ?>
                                        <div class="mt-auto">
                                            <strong><?= !empty($pd['sizes']) ? "From " . number_format($pd['display_price'], 2) . "€" : number_format($pd['display_price'], 2) . "€" ?></strong>
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addToCartModal<?= $pd['id'] ?>" <?= ($is_closed ? 'disabled title="Store is closed"' : '') ?>><i class="bi bi-cart-plus me-1"></i>Add to Cart</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal fade" id="addToCartModal<?= $pd['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-xl modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" action="index.php" class="add-to-cart-form" data-baseprice="<?= number_format($pd['base_price'], 2, '.', '') ?>" data-productid="<?= $pd['id'] ?>">
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-4"><img src="<?= htmlspecialchars($pd['image_url']) ?>" class="img-fluid rounded shadow" alt="<?= htmlspecialchars($pd['name']) ?>" onerror="this.src='https://via.placeholder.com/600x400?text=Product+Image';"></div>
                                                    <div class="col-8">
                                                        <h2 class="text-uppercase"><b><?= htmlspecialchars($pd['name']) ?></b></h2>
                                                        <hr style="width:10%;border:2px solid black;border-radius:5px;">
                                                        <?php if (empty($pd['sizes'])): ?>
                                                            <h5>Base Price: <span id="base-price-<?= $pd['id'] ?>"><?= number_format($pd['base_price'], 2) ?>€</span></h5><input type="hidden" name="size" value="">
                                                        <?php else: ?>
                                                            <div class="mb-3">
                                                                <label class="form-label">Select Size:</label>
                                                                <select class="form-select size-selector" name="size" data-productid="<?= $pd['id'] ?>">
                                                                    <option value="">Choose a size</option>
                                                                    <?php foreach ($pd['sizes'] as $sz): ?>
                                                                        <option value="<?= htmlspecialchars($sz['size']) ?>" data-sizeprice="<?= number_format($sz['price'], 2, '.', '') ?>" data-sizes-extras='<?= json_encode($sz['extras'] ?? []) ?>' data-sizes-sauces='<?= json_encode($sz['sauces'] ?? []) ?>'><?= htmlspecialchars($sz['size']) ?> (<?= number_format($pd['base_price'] + $sz['price'], 2) ?>€)</option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="mb-3">
                                                            <label class="form-label">Extras</label>
                                                            <div class="extras-container">
                                                                <?php foreach ($pd['extras'] as $extra): ?>
                                                                    <div class="mb-2 d-flex align-items-center">
                                                                        <input type="number" class="form-control me-2 item-quantity" style="width:80px" name="extras[<?= htmlspecialchars($extra['name']) ?>]" data-price="<?= number_format($extra['price'], 2, '.', '') ?>" data-productid="<?= $pd['id'] ?>" value="0" min="0" step="1">
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
                                                                        <input type="number" class="form-control me-2 item-quantity" style="width:80px" name="sauces[<?= htmlspecialchars($sauce['name']) ?>]" data-price="<?= number_format($sauce['price'], 2, '.', '') ?>" data-productid="<?= $pd['id'] ?>" value="0" min="0" step="1">
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
                                                                        <input type="number" class="form-control me-2 item-quantity" style="width:80px" name="dresses[<?= htmlspecialchars($dress['name']) ?>]" data-price="<?= number_format($dress['price'], 2, '.', '') ?>" data-productid="<?= $pd['id'] ?>" value="0" min="0" step="1">
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
                                                                        <option value="<?= htmlspecialchars($dk['id']) ?>" data-drinkprice="<?= number_format($dk['price'], 2, '.', '') ?>"><?= htmlspecialchars($dk['name']) ?> (+<?= number_format($dk['price'], 2) ?>€)</option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="mb-3">
                                                            <label class="form-label">Quantity</label><input type="number" class="form-control quantity-selector" name="quantity" data-productid="<?= $pd['id'] ?>" value="1" min="1" max="99" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Special Instructions</label><textarea class="form-control" name="special_instructions" rows="2"></textarea>
                                                        </div>
                                                        <div class="bg-light p-2 mb-2 rounded"><strong>Estimated Price: </strong><span id="estimated-price-<?= $pd['id'] ?>" style="font-size:1.25rem;color:#d3b213"><?= number_format($pd['base_price'], 2) ?>€</span></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <input type="hidden" name="product_id" value="<?= $pd['id'] ?>">
                                                <input type="hidden" name="add_to_cart" value="1">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary" <?= ($is_closed ? 'disabled' : '') ?>>Add to Cart</button>
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
                                                <h5 class="modal-title">Allergies for <?= htmlspecialchars($pd['name']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <ul class="list-group">
                                                    <?php foreach (array_map('trim', explode(',', $pd['allergies'])) as $alg): ?>
                                                        <li class="list-group-item"><?= htmlspecialchars($alg) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
                                        </div>
                                    </div>
                                </div>
                        <?php endif;
                        endforeach;
                    else: ?>
                        <p class="text-center">No products available.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="order-summary">
                    <h2 class="order-title">YOUR ORDER</h2>
                    <?php if (!empty($cart_logo) && file_exists($cart_logo)): ?>
                        <div class="mb-3 text-center"><img src="<?= htmlspecialchars($cart_logo) ?>" alt="Cart Logo" class="img-fluid" onerror="this.src='https://via.placeholder.com/150?text=Logo';"></div>
                    <?php endif;
                    if (!empty($cart_description)): ?>
                        <div class="mb-4" id="cart-description">
                            <p><?= nl2br(htmlspecialchars($cart_description)) ?></p><?= htmlspecialchars($_SESSION['store_name'] ?? '') ?>
                        </div>
                    <?php endif; ?>
                    <p class="card-text"><strong>Working hours:</strong> <?= !empty($notification['message']) ? htmlspecialchars($notification['message']) : "10:00 - 21:45" ?><br><strong>Minimum order:</strong> <?= htmlspecialchars($minimum_order) ?> €</p>
                    <?php if (!empty($_SESSION['delivery_address'])): ?>
                        <div class="mb-3">
                            <h5>Delivery Address</h5>
                            <p><?= htmlspecialchars($_SESSION['delivery_address']) ?></p>
                            <a href="https://www.openstreetmap.org/?mlat=<?= htmlspecialchars($_SESSION['latitude']) ?>&mlon=<?= htmlspecialchars($_SESSION['longitude']) ?>#map=18/<?= htmlspecialchars($_SESSION['latitude']) ?>/<?= htmlspecialchars($_SESSION['longitude']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">View on Map</a>
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
                                                                    <li><?= htmlspecialchars($ex['name']) ?> x<?= htmlspecialchars($ex['quantity']) ?> @ <?= number_format($ex['price'], 2) ?>€=<?= number_format($ex['quantity'] * $ex['price'], 2) ?>€</li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </li>
                                                    <?php endif;
                                                    if (!empty($it['sauces'])): ?>
                                                        <li><strong>Saucen:</strong>
                                                            <ul>
                                                                <?php foreach ($it['sauces'] as $sx): ?>
                                                                    <li><?= htmlspecialchars($sx['name']) ?> x<?= htmlspecialchars($sx['quantity']) ?> @ <?= number_format($sx['price'], 2) ?>€=<?= number_format($sx['quantity'] * $sx['price'], 2) ?>€</li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </li>
                                                    <?php endif;
                                                    if (!empty($it['drink'])): ?>
                                                        <li><strong>Getränk:</strong> <?= htmlspecialchars($it['drink']['name']) ?> (+<?= number_format($it['drink']['price'], 2) ?>€)</li>
                                                    <?php endif;
                                                    if (!empty($it['special_instructions'])): ?>
                                                        <li><strong>Instructions:</strong> <?= htmlspecialchars($it['special_instructions']) ?></li>
                                                    <?php endif; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong><?= number_format($it['total_price'], 2) ?>€</strong><br>
                                            <form action="index.php" method="POST" style="display:inline;">
                                                <input type="hidden" name="remove" value="<?= $i ?>">
                                                <button type="submit" class="btn btn-sm btn-danger mt-2"><i class="bi bi-trash"></i> Remove</button>
                                            </form>
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
                            <button class="btn btn-success w-100 mt-3" data-bs-toggle="modal" data-bs-target="#checkoutModal" <?= ($is_closed ? 'disabled title="Store is closed"' : '') ?>>Proceed to Checkout</button>
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
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark"><img src="https://images.unsplash.com/photo-1550547660-d9450f859349?crop=entropy&cs=tinysrgb&fit=crop&fm=jpg&h=40&w=40" alt="Logo" width="40" height="40" class="me-2">Restaurant</h5>
                    <p>Experience the finest dining with us.</p>
                </div>
                <div class="col-md-2">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-reset text-decoration-none">Home</a></li>
                        <li><a href="#menu" class="text-reset text-decoration-none">Menu</a></li>
                        <li><a href="#about" class="text-reset text-decoration-none">About Us</a></li>
                        <li><a href="#contact" class="text-reset text-decoration-none">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Legal</h5>
                    <ul class="list-unstyled">
                        <li><button type="button" class="btn btn-link text-reset p-0" data-bs-toggle="modal" data-bs-target="#agbModal">AGB</button></li>
                        <li><button type="button" class="btn btn-link text-reset p-0" data-bs-toggle="modal" data-bs-target="#impressumModal">Impressum</button></li>
                        <li><button type="button" class="btn btn-link text-reset p-0" data-bs-toggle="modal" data-bs-target="#datenschutzModal">Datenschutz</button></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Contact Us</h5>
                    <p><i class="bi bi-geo-alt-fill me-2"></i>123 Main Street</p>
                    <p><i class="bi bi-envelope-fill me-2"></i>info@restaurant.com</p>
                    <p><i class="bi bi-telephone-fill me-2"></i>+1 234 567 890</p>
                    <p><i class="bi bi-clock-fill me-2"></i>Mon - Sun: 10:00 - 22:00</p>
                </div>
            </div>
            <hr class="mb-4">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <p>© <?= date('Y') ?> Restaurant. All rights reserved.</p>
                </div>
                <div class="col-md-5 text-end">
                    <div class="social-media">
                        <?php foreach (['facebook_link', 'twitter_link', 'instagram_link', 'linkedin_link', 'youtube_link'] as $social): if (!empty($social_links[$social])): ?>
                                <a href="<?= htmlspecialchars($social_links[$social]) ?>" target="_blank"><i class="bi bi-<?= explode('_', $social)[0] ?>"></i></a>
                        <?php endif;
                        endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(function() {
            $('#loading-overlay').fadeOut('slow');
            $('.toast').toast('show');
            $('#reservationForm').submit(function(e) {
                e.preventDefault();
                let$f = $(this), $b = $f.find('button[type="submit"]').prop('disabled', true);
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
                    text: 'An error occurred. Try again later.'
                })).always(() => $b.prop('disabled', false));
            });
            <?php if (isset($_GET['order']) && $_GET['order'] === 'success'): ?>setTimeout(() => {
                $('.modal').modal('hide');
                window.location.href = 'index.php';
            }, 5000);
        <?php endif; ?>
        const updateStoreStatus = () => {
            $.getJSON('check_store_status.php', d => {
                let$c = $('.btn-add-to-cart,.btn-checkout');
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
        const stripe = Stripe('pk_test_51QByfJE4KNNCb6nuSnWLZP9JXlW84zG9DnOrQDTHQJvus9D8A8vOA85S4DfRlyWgN0rxa2hHzjppchnrmhyZGflx00B2kKlxym');
        const card = stripe.elements().create('card');
        card.mount('#card-element');
        $('input[name="payment_method"]').change(() => {
            $('#stripe-payment-section').toggle($('#paymentStripe').is(':checked'));
        });
        $('#checkoutForm').submit(async function(e) {
            if ($('#paymentStripe').is(':checked')) {
                e.preventDefault();
                let$btn = $(this).find('button[type="submit"]').prop('disabled', true);
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
                        if (ce) $('#card-errors').text(ce.message);
                        else if (pi.status === 'succeeded') window.location.href = res.redirect_url;
                    } else if (res.success) window.location.href = res.redirect_url;
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function updateEstimatedPrice(form) {
                let base = parseFloat(form.dataset.baseprice || "0");
                let productId = form.dataset.productid;
                let estSpan = document.getElementById("estimated-price-" + productId);
                let quantity = parseFloat(form.querySelector('.quantity-selector').value || "1");
                if (quantity < 1) quantity = 1;
                let sizeSelector = form.querySelector('.size-selector');
                let sizeExtraPrice = sizeSelector && sizeSelector.value ? parseFloat(sizeSelector.selectedOptions[0].dataset.sizeprice || "0") : 0;
                let itemsTotal = 0;
                form.querySelectorAll('.item-quantity').forEach(iq => {
                    let itemPrice = parseFloat(iq.dataset.price || "0");
                    let itemQty = parseFloat(iq.value || "0");
                    if (itemQty > 0) itemsTotal += itemPrice * itemQty;
                });
                let drinkSelector = form.querySelector('.drink-selector');
                let drinkPrice = drinkSelector && drinkSelector.value ? parseFloat(drinkSelector.selectedOptions[0].dataset.drinkprice || "0") : 0;
                let finalPrice = (base + sizeExtraPrice + itemsTotal + drinkPrice) * quantity;
                if (estSpan) estSpan.textContent = finalPrice.toFixed(2) + "€";
            }

            function updateSizeSpecificOptions(form, sizeData) {
                let sizeExtras = [],
                    sizeSauces = [];
                if (sizeData.sizesExtras) {
                    try {
                        sizeExtras = JSON.parse(sizeData.sizesExtras);
                    } catch (e) {}
                }
                if (sizeData.sizesSauces) {
                    try {
                        sizeSauces = JSON.parse(sizeData.sizesSauces);
                    } catch (e) {}
                }
                let extrasContainer = form.querySelector('.extras-container');
                if (extrasContainer) {
                    extrasContainer.innerHTML = '';
                    if (sizeExtras.length > 0) {
                        sizeExtras.forEach(extra => {
                            extrasContainer.innerHTML += `
<div class="mb-2 d-flex align-items-center">
<input type="number" class="form-control me-2 item-quantity" style="width:80px" name="extras[${extra.name}]" data-price="${parseFloat(extra.price).toFixed(2)}" data-productid="${form.dataset.productid}" value="0" min="0" step="1">
<span>${extra.name} (+${parseFloat(extra.price).toFixed(2)}€)</span>
</div>`;
                        });
                    } else {
                        <?php $global_extras_js = json_encode($pd['extras']); ?>
                        let globalExtras = <?= $global_extras_js ?>;
                        globalExtras.forEach(e => {
                            extrasContainer.innerHTML += `
<div class="mb-2 d-flex align-items-center">
<input type="number" class="form-control me-2 item-quantity" style="width:80px" name="extras[${e['name']}]" data-price="${parseFloat(e['price']).toFixed(2)}" data-productid="${form.dataset.productid}" value="0" min="0" step="1">
<span>${e['name']} (+${parseFloat(e['price']).toFixed(2)}€)</span>
</div>`;
                        });
                    }
                }
                let saucesContainer = form.querySelector('.sauces-container');
                if (saucesContainer) {
                    saucesContainer.innerHTML = '';
                    if (sizeSauces.length > 0) {
                        sizeSauces.forEach(sauce => {
                            saucesContainer.innerHTML += `
<div class="mb-2 d-flex align-items-center">
<input type="number" class="form-control me-2 item-quantity" style="width:80px" name="sauces[${sauce.name}]" data-price="${parseFloat(sauce.price).toFixed(2)}" data-productid="${form.dataset.productid}" value="0" min="0" step="1">
<span>${sauce.name} (+${parseFloat(sauce.price).toFixed(2)}€)</span>
</div>`;
                        });
                    } else {
                        <?php $global_sauces_js = json_encode($pd['sauces']); ?>
                        let globalSauces = <?= $global_sauces_js ?>;
                        globalSauces.forEach(s => {
                            saucesContainer.innerHTML += `
<div class="mb-2 d-flex align-items-center">
<input type="number" class="form-control me-2 item-quantity" style="width:80px" name="sauces[${s['name']}]" data-price="${parseFloat(s['price']).toFixed(2)}" data-productid="${form.dataset.productid}" value="0" min="0" step="1">
<span>${s['name']} (+${parseFloat(s['price']).toFixed(2)}€)</span>
</div>`;
                        });
                    }
                }
                let dressesContainer = form.querySelector('.dresses-container');
                if (dressesContainer) {
                    dressesContainer.innerHTML = '';
                    <?php $global_dresses_js = json_encode($pd['dresses']); ?>
                    let globalDresses = <?= $global_dresses_js ?>;
                    globalDresses.forEach(d => {
                        dressesContainer.innerHTML += `
<div class="mb-2 d-flex align-items-center">
<input type="number" class="form-control me-2 item-quantity" style="width:80px" name="dresses[${d['name']}]" data-price="${parseFloat(d['price']).toFixed(2)}" data-productid="${form.dataset.productid}" value="0" min="0" step="1">
<span>${d['name']} (+${parseFloat(d['price']).toFixed(2)}€)</span>
</div>`;
                    });
                }
                initializeEventListeners(form);
            }

            function initializeEventListeners(form) {
                form.querySelectorAll('.item-quantity').forEach(iq => {
                    iq.addEventListener('change', () => updateEstimatedPrice(form));
                });
                let sizeSelector = form.querySelector('.size-selector');
                if (sizeSelector) {
                    sizeSelector.addEventListener('change', function() {
                        let sizeData = {
                            sizesExtras: this.selectedOptions[0].dataset.sizesExtras || '[]',
                            sizesSauces: this.selectedOptions[0].dataset.sizesSauces || '[]'
                        };
                        updateSizeSpecificOptions(form, sizeData);
                        updateEstimatedPrice(form);
                    });
                }
                let drinkSelector = form.querySelector('.drink-selector');
                if (drinkSelector) {
                    drinkSelector.addEventListener('change', () => updateEstimatedPrice(form));
                }
                let qtySelector = form.querySelector('.quantity-selector');
                if (qtySelector) {
                    qtySelector.addEventListener('change', () => updateEstimatedPrice(form));
                }
                updateEstimatedPrice(form);
            }
            document.querySelectorAll('.add-to-cart-form').forEach(form => {
                initializeEventListeners(form);
            });
            let observer = new MutationObserver(mutations => {
                for (let mutation of mutations) {
                    if (mutation.type === 'childList') {
                        mutation.addedNodes.forEach(node => {
                            if (node.nodeType === Node.ELEMENT_NODE) {
                                node.querySelectorAll('.add-to-cart-form').forEach(f => initializeEventListeners(f));
                            }
                        });
                    }
                }
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