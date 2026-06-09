<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
revibe_require_verified_account($conn, 'verification_required.php');

$user_id = (int)$_SESSION['user_id'];

function load_checkout_items($conn, $user_id) {
    $items = [];
    $coordSelect = "";
    $hasUserLat = db_column_exists($conn, 'users', 'latitude');
    $hasUserLng = db_column_exists($conn, 'users', 'longitude');
    $hasUserAddress = db_column_exists($conn, 'users', 'address');
    if (db_column_exists($conn, 'products', 'seller_latitude')) $coordSelect .= $hasUserLat ? ", COALESCE(p.seller_latitude, u.latitude) AS seller_latitude" : ", p.seller_latitude AS seller_latitude";
    elseif ($hasUserLat) $coordSelect .= ", u.latitude AS seller_latitude";
    else $coordSelect .= ", NULL AS seller_latitude";
    if (db_column_exists($conn, 'products', 'seller_longitude')) $coordSelect .= $hasUserLng ? ", COALESCE(p.seller_longitude, u.longitude) AS seller_longitude" : ", p.seller_longitude AS seller_longitude";
    elseif ($hasUserLng) $coordSelect .= ", u.longitude AS seller_longitude";
    else $coordSelect .= ", NULL AS seller_longitude";
    if (db_column_exists($conn, 'products', 'seller_address_snapshot')) $coordSelect .= $hasUserAddress ? ", COALESCE(p.seller_address_snapshot, u.address) AS seller_address" : ", p.seller_address_snapshot AS seller_address";
    elseif ($hasUserAddress) $coordSelect .= ", u.address AS seller_address";

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_now_product_id'])) {
        verify_csrf();
        $pid = (int)$_POST['buy_now_product_id'];
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        $q = mysqli_query($conn, "SELECT p.*, u.first_name, u.last_name $coordSelect FROM products p LEFT JOIN users u ON p.user_id=u.id WHERE p.id=$pid LIMIT 1");
        if ($q && $p = mysqli_fetch_assoc($q)) {
            if ((int)($p['user_id'] ?? 0) === (int)$user_id) {
                $_SESSION['error'] = 'Produk milik sendiri tidak bisa dibeli untuk menghindari kecurangan transaksi.';
                header('Location: detail.php?id=' . $pid); exit;
            }
            $p['qty'] = min($qty, max(1, (int)$p['stock']));
            $p['image'] = revibe_product_image($conn, $pid);
            $items[] = $p;
        }
    } else {
        $ownCheck = mysqli_query($conn, "SELECT c.id FROM cart c JOIN products p ON c.product_id=p.id WHERE c.user_id=$user_id AND p.user_id=$user_id");
        if ($ownCheck && mysqli_num_rows($ownCheck) > 0) {
            $ids = [];
            while($bad = mysqli_fetch_assoc($ownCheck)) $ids[] = (int)$bad['id'];
            if ($ids) mysqli_query($conn, "DELETE FROM cart WHERE user_id=$user_id AND id IN (" . implode(',', $ids) . ")");
            $_SESSION['error'] = 'Produk milik sendiri otomatis dihapus dari keranjang karena tidak boleh dibeli sendiri.';
            header('Location: cart.php'); exit;
        }
        $q = mysqli_query($conn, "SELECT p.*, c.qty, u.first_name, u.last_name $coordSelect FROM cart c JOIN products p ON c.product_id=p.id LEFT JOIN users u ON p.user_id=u.id WHERE c.user_id=$user_id AND p.user_id<>$user_id ORDER BY c.id DESC");
        if ($q) while($row = mysqli_fetch_assoc($q)) { $row['image'] = revibe_product_image($conn, (int)$row['id']); $items[] = $row; }
    }
    return $items;
}

function checkout_shipping_summary($items, $buyerLat, $buyerLng, $courier) {
    $totalShipping = 0;
    $maxDistance = 0;
    $rows = [];
    foreach($items as $item) {
        $sellerLat = $item['seller_latitude'] ?? null;
        $sellerLng = $item['seller_longitude'] ?? null;
        $distance = revibe_distance_km($sellerLat, $sellerLng, $buyerLat, $buyerLng);
        $weightGram = max(1, (int)($item['weight_gram'] ?? 1000)) * max(1, (int)($item['qty'] ?? 1));
        $cost = revibe_shipping_cost_by_distance($distance, $courier, $weightGram);
        $totalShipping += $cost;
        if ($distance !== null) $maxDistance = max($maxDistance, $distance);
        $rows[(int)$item['id']] = ['distance' => $distance, 'cost' => $cost, 'seller_lat' => $sellerLat, 'seller_lng' => $sellerLng, 'weight_gram' => $weightGram];
    }
    return ['total' => $totalShipping, 'max_distance' => $maxDistance, 'rows' => $rows];
}

$items = load_checkout_items($conn, $user_id);
$buyer = current_user($conn);
$defaultBuyerLat = db_column_exists($conn, 'users', 'latitude') ? ($buyer['latitude'] ?? '') : '';
$defaultBuyerLng = db_column_exists($conn, 'users', 'longitude') ? ($buyer['longitude'] ?? '') : '';
$defaultAddress = revibe_user_full_address($buyer);
$buyerRegion = revibe_user_region($buyer);
$buyerLabel = revibe_address_label($buyer);
$subtotal = 0;
foreach($items as $item) $subtotal += (int)$item['price'] * (int)$item['qty'];
$initialCourier = 'JNE REG';
$initialShipping = checkout_shipping_summary($items, $defaultBuyerLat, $defaultBuyerLng, $initialCourier);
$initialShippingTotal = $initialShipping['total'] ?: 15000;
$initialServiceFee = revibe_calculate_service_fee($subtotal);
$initialSellerCashback = revibe_calculate_seller_cashback($subtotal);
$initialPlatformMargin = revibe_calculate_platform_margin($subtotal);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    verify_csrf();
    if (!revibe_rate_limit('checkout_place_order', 8, 300)) {
        $_SESSION['error'] = 'Terlalu banyak percobaan checkout. Tunggu beberapa menit.';
        header('Location: checkout.php'); exit;
    }
    $items = load_checkout_items($conn, $user_id);
    if (empty($items)) {
        $_SESSION['error'] = 'Checkout gagal. Keranjang kosong.';
        header('Location: cart.php'); exit;
    }

    $address = trim($_POST['shipping_address'] ?? '');
    $courier = trim($_POST['courier'] ?? 'JNE');
    $payment_method = trim($_POST['payment_method'] ?? 'transfer_bank');
    $notes = trim($_POST['notes'] ?? '');

    $buyerForCheckout = current_user($conn);
    $buyerLat = revibe_float_or_null($buyerForCheckout['latitude'] ?? null);
    $buyerLng = revibe_float_or_null($buyerForCheckout['longitude'] ?? null);
    if ($address === '') $address = revibe_user_full_address($buyerForCheckout);

    if ($address === '') {
        $_SESSION['error'] = 'Alamat pengiriman wajib diisi.';
        header('Location: checkout.php'); exit;
    }
    if (!revibe_valid_coordinate($buyerLat, $buyerLng)) {
        $buyerLat = null;
        $buyerLng = null;
    }

    $shippingSummary = checkout_shipping_summary($items, $buyerLat, $buyerLng, $courier);

    mysqli_begin_transaction($conn);
    try {
        foreach ($items as $item) {
            $product_id = (int)$item['id'];
            $requested_qty = max(1, (int)$item['qty']);

            $lockQ = mysqli_query($conn, "SELECT * FROM products WHERE id=$product_id FOR UPDATE");
            $locked = $lockQ ? mysqli_fetch_assoc($lockQ) : null;
            if (!$locked) throw new Exception('Produk tidak ditemukan atau sudah dihapus.');
            $seller_id = (int)$locked['user_id'];
            if ($seller_id === $user_id) throw new Exception('Produk milik sendiri tidak bisa dibeli.');
            if (($locked['product_status'] ?? 'approved') !== 'approved') throw new Exception('Produk ' . ($locked['name'] ?? '') . ' belum disetujui admin.');
            if ((int)$locked['stock'] < $requested_qty) throw new Exception('Stok produk ' . ($locked['name'] ?? '') . ' tidak mencukupi. Sisa stok: ' . (int)$locked['stock']);

            $qty = $requested_qty;
            $price = (int)$locked['price'];
            $total_price = $price * $qty;
            $service_fee = revibe_calculate_service_fee($total_price);
            $seller_cashback_amount = revibe_calculate_seller_cashback($total_price);
            $platform_margin_amount = max(0, $service_fee - $seller_cashback_amount);
            $order_code = generate_order_code();
            $ship = $shippingSummary['rows'][$product_id] ?? ['distance'=>null,'cost'=>15000,'seller_lat'=>null,'seller_lng'=>null];
            $shipping_cost = (int)$ship['cost'];
            $distance_km = $ship['distance'];
            $sellerLat = revibe_float_or_null($ship['seller_lat'] ?? ($locked['seller_latitude'] ?? null));
            $sellerLng = revibe_float_or_null($ship['seller_lng'] ?? ($locked['seller_longitude'] ?? null));
            $deliveryEstimate = revibe_delivery_estimate_text($distance_km, $courier, max(1, (int)($locked['weight_gram'] ?? 1000)) * $qty);
            $orderStatus = ($payment_method === 'cod') ? 'processing' : 'pending_payment';

            $columns = "order_code, buyer_id, seller_id, product_id, qty, total_price, status, shipping_address, courier, shipping_cost, payment_method, notes";
            $values = "?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?";
            $types = 'siiiiisssiss';
            $params = [$order_code, $user_id, $seller_id, $product_id, $qty, $total_price, $orderStatus, $address, $courier, $shipping_cost, $payment_method, $notes];

            if (db_column_exists($conn, 'orders', 'buyer_latitude')) { $columns .= ", buyer_latitude"; $values .= ", ?"; $types .= 'd'; $params[] = $buyerLat; }
            if (db_column_exists($conn, 'orders', 'buyer_longitude')) { $columns .= ", buyer_longitude"; $values .= ", ?"; $types .= 'd'; $params[] = $buyerLng; }
            if (db_column_exists($conn, 'orders', 'seller_latitude')) { $columns .= ", seller_latitude"; $values .= ", ?"; $types .= 'd'; $params[] = $sellerLat; }
            if (db_column_exists($conn, 'orders', 'seller_longitude')) { $columns .= ", seller_longitude"; $values .= ", ?"; $types .= 'd'; $params[] = $sellerLng; }
            if (db_column_exists($conn, 'orders', 'distance_km')) { $columns .= ", distance_km"; $values .= ", ?"; $types .= 'd'; $params[] = $distance_km; }
            if (db_column_exists($conn, 'orders', 'delivery_estimate')) { $columns .= ", delivery_estimate"; $values .= ", ?"; $types .= 's'; $params[] = $deliveryEstimate; }
            if (db_column_exists($conn, 'orders', 'service_fee')) { $columns .= ", service_fee"; $values .= ", ?"; $types .= 'i'; $params[] = $service_fee; }
            if (db_column_exists($conn, 'orders', 'seller_cashback_amount')) { $columns .= ", seller_cashback_amount"; $values .= ", ?"; $types .= 'i'; $params[] = $seller_cashback_amount; }
            if (db_column_exists($conn, 'orders', 'platform_margin_amount')) { $columns .= ", platform_margin_amount"; $values .= ", ?"; $types .= 'i'; $params[] = $platform_margin_amount; }
            if (db_column_exists($conn, 'orders', 'payment_status')) { $columns .= ", payment_status"; $values .= ", ?"; $types .= 's'; $params[] = $payment_method === 'cod' ? 'cod' : 'pending'; }

            $stmt = mysqli_prepare($conn, "INSERT INTO orders ($columns) VALUES ($values)");
            if (!$stmt) throw new Exception('Gagal membuat order. Detail teknis masuk log.');
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $order_id = mysqli_insert_id($conn);
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            if (db_column_exists($conn, 'orders', 'invoice_number')) {
                $invSafe = mysqli_real_escape_string($conn, $invoiceNumber);
                mysqli_query($conn, "UPDATE orders SET invoice_number='$invSafe' WHERE id=$order_id");
            }

            if (db_table_exists($conn, 'order_items')) {
                $stmtItem = mysqli_prepare($conn, "INSERT INTO order_items (order_id, product_id, seller_id, product_name, price, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $product_name = $locked['name'];
                mysqli_stmt_bind_param($stmtItem, 'iiisiii', $order_id, $product_id, $seller_id, $product_name, $price, $qty, $total_price);
                mysqli_stmt_execute($stmtItem);
            }
            $grand = $total_price + $shipping_cost + $service_fee;
            if (db_table_exists($conn, 'payments')) {
                $gateway = (string)revibe_env('PAYMENT_GATEWAY', 'manual');
                if (db_column_exists($conn, 'payments', 'gateway')) {
                    $stmtPay = mysqli_prepare($conn, "INSERT INTO payments (order_id, user_id, method, amount, status, gateway, expired_at) VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
                    $payStatus = ($payment_method === 'cod') ? 'verified' : 'waiting_upload';
                    mysqli_stmt_bind_param($stmtPay, 'iisiss', $order_id, $user_id, $payment_method, $grand, $payStatus, $gateway);
                } else {
                    $stmtPay = mysqli_prepare($conn, "INSERT INTO payments (order_id, user_id, method, amount, status) VALUES (?, ?, ?, ?, ?)");
                    $payStatus = ($payment_method === 'cod') ? 'verified' : 'waiting_upload';
                    mysqli_stmt_bind_param($stmtPay, 'iisis', $order_id, $user_id, $payment_method, $grand, $payStatus);
                }
                mysqli_stmt_execute($stmtPay);
                $payment_id = mysqli_insert_id($conn);
                revibe_payment_status_history($conn, $payment_id, $order_id, null, $payStatus, 'checkout', 'Payment dibuat saat checkout');

                $gateway = strtolower((string)revibe_env('PAYMENT_GATEWAY', 'manual'));
                if ($payment_method !== 'cod' && in_array($gateway, ['midtrans','xendit'], true) && db_column_exists($conn, 'payments', 'payment_url')) {
                    $gatewayPayload = [
                        'id' => $order_id,
                        'order_id' => $order_id,
                        'payment_id' => $payment_id,
                        'order_code' => $order_code,
                        'invoice_number' => $invoiceNumber,
                        'amount' => $grand,
                        'gross_amount' => $grand,
                        'buyer_name' => trim(($buyerForCheckout['first_name'] ?? '') . ' ' . ($buyerForCheckout['last_name'] ?? '')),
                        'buyer_email' => (string)($buyerForCheckout['email'] ?? ''),
                        'buyer_phone' => (string)($buyerForCheckout['phone'] ?? ''),
                    ];
                    $gatewayResult = (new PaymentGatewayService($conn))->createPayment($gatewayPayload, $gateway);
                    $paymentUrl = mysqli_real_escape_string($conn, (string)($gatewayResult['payment_url'] ?? $gatewayResult['invoice_url'] ?? ''));
                    $snapToken = mysqli_real_escape_string($conn, (string)($gatewayResult['snap_token'] ?? ''));
                    $gatewayRef = mysqli_real_escape_string($conn, (string)($gatewayResult['gateway_reference'] ?? $gatewayResult['external_id'] ?? $order_code));
                    $gatewayJson = mysqli_real_escape_string($conn, json_encode($gatewayResult, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                    $setSnap = db_column_exists($conn, 'payments', 'snap_token') ? ", snap_token='$snapToken'" : '';
                    $setPayload = db_column_exists($conn, 'payments', 'gateway_payload') ? ", gateway_payload='$gatewayJson'" : '';
                    $nextStatus = !empty($gatewayResult['success']) ? 'waiting_payment' : 'waiting_upload';
                    mysqli_query($conn, "UPDATE payments SET status='$nextStatus', gateway='$gateway', gateway_reference='$gatewayRef', payment_url='$paymentUrl' $setSnap $setPayload WHERE id=$payment_id");
                    revibe_payment_status_history($conn, $payment_id, $order_id, $payStatus, $nextStatus, $gateway, 'Gateway payment dibuat saat checkout');
                }
            }
            if (db_table_exists($conn, 'invoices')) {
                $due = date('Y-m-d H:i:s', time()+86400);
                $invoiceStatus = ($payment_method === 'cod') ? 'cod' : 'unpaid';
                $stmtInv = mysqli_prepare($conn, "INSERT INTO invoices (invoice_number, order_id, user_id, subtotal, shipping_cost, service_fee, discount_amount, total, status, due_at, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, NOW())");
                if ($stmtInv) { mysqli_stmt_bind_param($stmtInv, 'siiiiiiss', $invoiceNumber, $order_id, $user_id, $total_price, $shipping_cost, $service_fee, $grand, $invoiceStatus, $due); mysqli_stmt_execute($stmtInv); }
            }
            revibe_order_status_history($conn, $order_id, null, $orderStatus, $user_id, 'Order dibuat dari checkout');
            if ($payment_method === 'cod') revibe_create_pending_seller_balance($conn, $order_id);
            if (db_table_exists($conn, 'shipments')) {
                $shipColumns = "order_id, courier, shipping_address, shipping_cost, status";
                $shipValues = "?, ?, ?, ?, ?";
                $shipTypes = "issis";
                $shipStatus = ($payment_method === 'cod') ? 'processing' : 'waiting_payment';
                $shipParams = [$order_id, $courier, $address, $shipping_cost, $shipStatus];
                if (db_column_exists($conn, 'shipments', 'distance_km')) { $shipColumns .= ", distance_km"; $shipValues .= ", ?"; $shipTypes .= 'd'; $shipParams[] = $distance_km; }
                if (db_column_exists($conn, 'shipments', 'delivery_estimate')) { $shipColumns .= ", delivery_estimate"; $shipValues .= ", ?"; $shipTypes .= 's'; $shipParams[] = $deliveryEstimate; }
                $stmtShip = mysqli_prepare($conn, "INSERT INTO shipments ($shipColumns) VALUES ($shipValues)");
                mysqli_stmt_bind_param($stmtShip, $shipTypes, ...$shipParams);
                mysqli_stmt_execute($stmtShip);
            }
            mysqli_query($conn, "UPDATE products SET stock = stock - $qty WHERE id=$product_id AND stock >= $qty");
            if (mysqli_affected_rows($conn) !== 1) throw new Exception('Stok produk berubah saat checkout. Coba ulangi.');
            revibe_notify_user_event($conn, $user_id, 'order_created', 'Pesanan ReVibe Market Berhasil Dibuat', 'Invoice ' . $invoiceNumber . ' berhasil dibuat. Total pembayaran: ' . money($grand) . ' termasuk Biaya Layanan ReVibe ' . revibe_service_fee_percent() . '%. Silakan lanjutkan pembayaran demo.', ['order_id'=>$order_id,'invoice'=>$invoiceNumber]);
            if ($payment_method === 'cod') revibe_notify_user_event($conn, $seller_id, 'order_cod', 'Order COD Baru Masuk', 'Ada pesanan COD untuk produk ' . $locked['name'] . '. Silakan proses barang.', ['order_id'=>$order_id]);
        }

        if (!isset($_POST['buy_now_product_id'])) mysqli_query($conn, "DELETE FROM cart WHERE user_id=$user_id");
        mysqli_commit($conn);
        $_SESSION['success'] = 'Checkout berhasil. Stok dikunci aman dan ongkir dihitung dari koordinat profil pembeli serta penjual.';
        header('Location: buyer_orders.php'); exit;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        revibe_log('error','checkout failed',['user_id'=>$user_id,'error'=>$e->getMessage()]);
        $_SESSION['error'] = revibe_is_debug() ? ('Checkout gagal: ' . $e->getMessage()) : 'Terjadi kendala saat memproses checkout. Silakan coba lagi.';
        header('Location: cart.php'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><title>Checkout - ReVibe Market</title><link rel="stylesheet" href="../assets/css/style.css"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="stylesheet" href="../assets/css/loader.css?v=25">
</head>
<body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>

<div class="navbar"><a href="cart.php" class="btn">← Keranjang</a><a href="../index.php" class="btn">Beranda</a></div>
<div class="page-shell">
    <div class="page-header"><h1>Checkout</h1><p>Alamat dan titik koordinat otomatis diambil dari profil pembeli. Ongkir dihitung dari titik penjual ke titik pembeli.</p></div>
    <?php if(empty($items)): ?>
        <div class="empty-state"><h3>Tidak ada produk untuk checkout.</h3><a href="cart.php" class="btn primary">Kembali ke Keranjang</a></div>
    <?php else: ?>
    <div class="checkout-grid">
        <div class="checkout-card checkout-products-card">
            <h3>Produk</h3>
            <?php foreach($items as $item):
                $itemDistance = $initialShipping['rows'][(int)$item['id']]['distance'] ?? null;
                $itemShipping = $initialShipping['rows'][(int)$item['id']]['cost'] ?? 15000;
                $itemWeightGram = max(1, (int)($item['weight_gram'] ?? 1000)) * max(1, (int)$item['qty']);
                $itemEta = revibe_delivery_estimate_text($itemDistance, $initialCourier, $itemWeightGram);
            ?>
                <div class="checkout-product-row"
                     data-price="<?= (int)$item['price'] ?>"
                     data-qty="<?= (int)$item['qty'] ?>"
                     data-seller-lat="<?= e($item['seller_latitude'] ?? '') ?>"
                     data-seller-lng="<?= e($item['seller_longitude'] ?? '') ?>"
                     data-product-id="<?= (int)$item['id'] ?>"
                     data-weight-gram="<?= (int)$itemWeightGram ?>">
                    <a href="detail.php?id=<?= (int)$item['id'] ?>"><img src="<?= e(revibe_public_file_url($item['image'] ?? 'default.png', 'products')) ?>" alt="<?= e($item['name']) ?>"></a>
                    <div class="checkout-product-info">
                        <a href="detail.php?id=<?= (int)$item['id'] ?>"><strong><?= e($item['name']) ?></strong></a>
                        <p><?= (int)$item['qty'] ?> item • <?= money((int)$item['price'] * (int)$item['qty']) ?> • ±<?= e(number_format($itemWeightGram/1000, 1, ',', '.')) ?> kg</p>
                        <small>Penjual: <?= e(trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''))) ?> • Titik seller: <span class="revibe-coord-name" data-lat="<?= e($item['seller_latitude'] ?? '') ?>" data-lng="<?= e($item['seller_longitude'] ?? '') ?>" data-fallback="<?= e($item['seller_address'] ?? $item['location'] ?? 'Titik lokasi seller belum tersedia') ?>"><?= e($item['seller_address'] ?? $item['location'] ?? 'Mencari nama lokasi...') ?></span></small>
                    </div>
                    <div class="checkout-ship-est"><span class="ship-distance"><?= $itemDistance !== null ? e($itemDistance.' km') : 'Butuh koordinat' ?></span><strong class="ship-cost"><?= money($itemShipping) ?></strong><small class="ship-eta"><?= e($itemEta) ?></small></div>
                </div>
            <?php endforeach; ?>
            <div class="summary-line"><span>Subtotal</span><strong id="checkoutSubtotal"><?= money($subtotal) ?></strong></div>
            <div class="summary-line"><span>Ongkir otomatis</span><strong id="checkoutShipping"><?= money($initialShippingTotal) ?></strong></div>
            <div class="summary-line"><span>Biaya Layanan ReVibe <?= e(revibe_service_fee_percent()) ?>%</span><strong id="checkoutServiceFee"><?= money($initialServiceFee) ?></strong></div>
            <div class="summary-line"><span>Simulasi Cashback Seller <?= e(revibe_seller_cashback_percent()) ?>%</span><strong id="checkoutSellerCashback"><?= money($initialSellerCashback) ?></strong></div>
            <div class="summary-line"><span>Estimasi Margin Platform Demo <?= e(revibe_platform_margin_percent()) ?>%</span><strong id="checkoutPlatformMargin"><?= money($initialPlatformMargin) ?></strong></div>
            <div class="summary-line grand"><span>Total</span><strong id="checkoutGrand"><?= money($subtotal + $initialShippingTotal + $initialServiceFee) ?></strong></div>
            <div class="info-box coordinate-note">Catatan: jika titik lokasi gagal, form alamat manual tetap bisa digunakan dan sistem memakai ongkir minimum/default. <?= e(revibe_service_fee_note()) ?> <?= e(revibe_demo_payment_note()) ?></div>
        </div>
        <form method="POST" class="checkout-card" id="checkoutForm">
            <?= csrf_field() ?>
            <input type="hidden" name="place_order" value="1">
            <?php if(isset($_POST['buy_now_product_id'])): ?>
                <input type="hidden" name="buy_now_product_id" value="<?= (int)$_POST['buy_now_product_id'] ?>">
                <input type="hidden" name="qty" value="<?= (int)($_POST['qty'] ?? 1) ?>">
            <?php endif; ?>
            <label>Alamat Pengiriman</label>
            <div class="checkout-address-card">
                <div class="checkout-address-head"><strong><?= e($buyerLabel) ?> Utama</strong><a href="edit_profile.php#alamat">Ubah Alamat</a></div>
                <p><?= e(trim(($buyer['first_name'] ?? '') . ' ' . ($buyer['last_name'] ?? ''))) ?><?= !empty($buyer['phone']) ? ' • '.e($buyer['phone']) : '' ?></p>
                <p class="checkout-address-full"><?= e($defaultAddress ?: 'Alamat belum diatur. Klik Ubah Alamat untuk melengkapi.') ?></p>
                <div class="mini-map-checkout">
                    <div class="map-bubble small">Alamatmu di sini</div>
                    <div class="map-pin small"></div>
                    <span>Titik lokasi: <b id="buyerCoordText" class="revibe-coord-name" data-lat="<?= e($defaultBuyerLat) ?>" data-lng="<?= e($defaultBuyerLng) ?>" data-fallback="<?= e($buyerLabel . ' - ' . ($defaultAddress ?: 'Alamat belum diatur')) ?>"><?= e($defaultAddress ? revibe_user_region($buyer) : 'Titik lokasi belum dipilih') ?></b></span>
                </div>
            </div>
            <input type="hidden" name="shipping_address" id="shippingAddress" value="<?= e($defaultAddress) ?>">
            <input type="hidden" name="buyer_latitude" id="buyerLat" value="<?= e($defaultBuyerLat) ?>" required>
            <input type="hidden" name="buyer_longitude" id="buyerLng" value="<?= e($defaultBuyerLng) ?>" required>
            <div class="coordinate-card checkout-coordinate-card">
                <label>Titik Lokasi Pembeli</label>
                <div class="coordinate-name-preview">
                    <span>Nama titik dari alamat profil</span>
                    <strong id="buyerCoordPreview" class="revibe-coord-name" data-lat="<?= e($defaultBuyerLat) ?>" data-lng="<?= e($defaultBuyerLng) ?>" data-fallback="<?= e($defaultAddress ?: 'Titik lokasi belum dipilih') ?>"><?= e($buyerRegion ?: $defaultAddress ?: 'Titik lokasi belum dipilih') ?></strong>
                </div>
                <button class="btn secondary full" type="button" id="useMyLocation">📍 Perbarui Titik Lokasi Saat Ini</button>
                <p class="muted">Untuk checkout normal, sistem memakai koordinat profil. Tombol ini hanya untuk memperbarui sementara sebelum membuat pesanan.</p>
            </div>
            <label>Kurir</label>
            <select name="courier" id="courierSelect">
                <?php foreach(revibe_courier_services() as $val => $label): ?>
                    <option value="<?= e($val) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="shipping-rule-note" id="shippingRuleNote">
                Tarif ini memakai estimasi simulasi berdasarkan jarak, zona, dan berat paket minimal 1 kg. Untuk tarif resmi real-time, integrasikan API cek ongkir kurir/agregator.
            </div>
            <label>Metode Pembayaran</label>
            <select name="payment_method" id="paymentMethod">
                <option value="transfer_bank">Transfer Bank Demo ReVibe</option>
                <option value="ewallet">E-Wallet Demo ReVibe</option>
                <option value="cod">COD / Bayar di Tempat</option>
            </select>
            <div class="payment-method-panels">
                <div class="payment-info-card pay-panel" data-pay="transfer_bank">
                    <strong>Transfer Bank Demo ReVibe</strong>
                    <p>Bank: <b>BANK DEMO REVIBE</b></p>
                    <p>No. Rekening: <b>0000000000</b></p>
                    <p>Atas Nama: <b>REVIBE DEMO</b></p>
                    <small>Demo saja, jangan transfer uang asli. Setelah simulasi bayar, upload bukti pembayaran gambar dari halaman Pesanan.</small>
                </div>
                <div class="payment-info-card pay-panel" data-pay="ewallet" style="display:none">
                    <strong>E-Wallet Demo ReVibe</strong>
                    <p>Gunakan simulasi e-wallet demo, bukan QR pembayaran asli.</p>
                    <small>Demo saja, jangan transfer uang asli. Upload screenshot bukti pembayaran JPG, PNG, atau WEBP.</small>
                </div>
                <div class="payment-info-card pay-panel" data-pay="cod" style="display:none">
                    <strong>COD / Bayar di Tempat</strong>
                    <p>Bayar langsung ke penjual saat barang diterima.</p>
                    <small>Seller bisa menandai barang sampai, lalu pembeli klik Konfirmasi Sampai.</small>
                </div>
            </div>
            <label>Catatan</label>
            <textarea name="notes" rows="2" placeholder="Catatan untuk seller"></textarea>
            <button class="btn primary full" type="submit">Buat Pesanan</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<script src="../assets/js/revibe-location.js"></script>
<script>
const rupiah = (n) => 'Rp ' + Math.round(n || 0).toLocaleString('id-ID');
function toRad(v){ return v * Math.PI / 180; }
function validCoord(lat,lng){ return !isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180; }
function distanceKm(lat1,lng1,lat2,lng2){
    if(!validCoord(lat1,lng1) || !validCoord(lat2,lng2)) return null;
    const R=6371, dLat=toRad(lat2-lat1), dLng=toRad(lng2-lng1);
    const a=Math.sin(dLat/2)**2 + Math.cos(toRad(lat1))*Math.cos(toRad(lat2))*Math.sin(dLng/2)**2;
    return Math.round((R*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a))) * 100) / 100;
}
function serviceKey(courier){
    courier = (courier || 'JNE REG').toLowerCase();
    if(courier.includes('cod')) return 'cod';
    if(courier.includes('yes')) return 'jne_yes';
    if(courier.includes('jne')) return 'jne_reg';
    if(courier.includes('super')) return 'jnt_super';
    if(courier.includes('j&t') || courier.includes('jt')) return 'jnt_ez';
    if(courier.includes('best')) return 'sicepat_best';
    if(courier.includes('sicepat')) return 'sicepat_reg';
    return 'jne_reg';
}
function weightKg(weightGram){
    const gram = Math.max(1, parseInt(weightGram || '1000'));
    return Math.max(1, Math.ceil(gram / 1000));
}
function zone(distance){
    if(distance === null) return 'unknown';
    if(distance <= 10) return 'city';
    if(distance <= 30) return 'near_city';
    if(distance <= 75) return 'metro';
    if(distance <= 150) return 'regional';
    if(distance <= 500) return 'intercity';
    return 'far';
}
const shippingRules = {
    jne_reg:{base:{city:10000,near_city:12000,metro:18000,regional:25000,intercity:35000,far:45000,unknown:15000}, extra:8000, min:10000, note:'JNE REG: estimasi regular, minimal 1 kg.'},
    jne_yes:{base:{city:18000,near_city:22000,metro:28000,regional:42000,intercity:60000,far:85000,unknown:25000}, extra:14000, min:18000, note:'JNE YES: estimasi cepat 1 hari untuk rute yang mendukung.'},
    jnt_ez:{base:{city:9000,near_city:11000,metro:17000,regional:24000,intercity:34000,far:45000,unknown:15000}, extra:7500, min:9000, note:'J&T EZ: estimasi regular, minimal 1 kg.'},
    jnt_super:{base:{city:16000,near_city:20000,metro:28000,regional:42000,intercity:62000,far:85000,unknown:24000}, extra:13000, min:16000, note:'J&T Super: ideal untuk paket cepat dan ringan.'},
    sicepat_reg:{base:{city:8000,near_city:10000,metro:16000,regional:23000,intercity:33000,far:44000,unknown:14000}, extra:7000, min:8000, note:'SiCepat REGULAR: estimasi standar 1–3 hari untuk banyak rute.'},
    sicepat_best:{base:{city:17000,near_city:21000,metro:29000,regional:43000,intercity:64000,far:88000,unknown:25000}, extra:13500, min:17000, note:'SiCepat BEST: estimasi cepat 1 hari untuk kota besar/rute yang mendukung.'},
    cod:{base:{city:7000,near_city:12000,metro:22000,regional:35000,intercity:50000,far:75000,unknown:12000}, extra:4000, min:7000, note:'COD Lokal: cocok untuk area dekat; area jauh perlu kesepakatan penjual dan pembeli.'}
};
function deliveryEstimate(distance, courier, weightGram){
    if(distance === null) return 'Estimasi belum tersedia';
    const key = serviceKey(courier), kg = weightKg(weightGram);
    if(key === 'cod'){
        if(distance <= 10) return 'COD lokal: hari ini / maksimal 1 hari';
        if(distance <= 30) return 'COD lokal: 1–2 hari';
        return 'COD luar area lokal: perlu kesepakatan seller (2–4 hari)';
    }
    if(key === 'jne_yes' || key === 'sicepat_best' || key === 'jnt_super'){
        if(key === 'jnt_super' && kg > 3) return 'J&T Super ideal maksimal 3 kg; gunakan EZ/REG untuk paket lebih berat';
        if(distance <= 150) return 'Estimasi 1 hari kerja';
        if(distance <= 500) return 'Estimasi 1–2 hari kerja';
        return 'Estimasi 2–3 hari kerja';
    }
    if(key === 'sicepat_reg'){
        if(distance <= 150) return 'Estimasi 1–3 hari';
        if(distance <= 500) return 'Estimasi 2–5 hari';
        return 'Estimasi 3–7 hari';
    }
    if(key === 'jnt_ez'){
        if(distance <= 75) return 'Estimasi 1–2 hari';
        if(distance <= 500) return 'Estimasi 2–4 hari';
        return 'Estimasi 3–7 hari';
    }
    if(distance <= 75) return 'Estimasi 1–2 hari';
    if(distance <= 500) return 'Estimasi 2–4 hari';
    return 'Estimasi 3–7 hari';
}
function shippingCost(distance, courier, weightGram){
    if(distance === null) return 15000;
    const key = serviceKey(courier), rule = shippingRules[key] || shippingRules.jne_reg;
    const z = zone(distance), kg = weightKg(weightGram);
    let cost = (rule.base[z] || rule.base.unknown) + ((kg - 1) * rule.extra);
    if(key === 'cod' && distance > 30) cost += Math.ceil((distance - 30) * 700);
    cost = Math.max(rule.min, cost);
    return Math.ceil(cost / 1000) * 1000;
}
function updateShippingNote(){
    const courier = document.getElementById('courierSelect')?.value || 'JNE REG';
    const key = serviceKey(courier), el = document.getElementById('shippingRuleNote');
    if(el) el.textContent = (shippingRules[key] || shippingRules.jne_reg).note + ' Tarif final kurir asli tetap perlu dicek lewat sistem resmi/API saat production.';
}
function recalcShipping(){
    const lat = parseFloat(document.getElementById('buyerLat')?.value || '');
    const lng = parseFloat(document.getElementById('buyerLng')?.value || '');
    const courier = document.getElementById('courierSelect')?.value || 'JNE REG';
    let subtotal=0, shipping=0;
    document.querySelectorAll('.checkout-product-row').forEach(row => {
        subtotal += (parseInt(row.dataset.price || '0') * parseInt(row.dataset.qty || '1'));
        const sLat = parseFloat(row.dataset.sellerLat || '');
        const sLng = parseFloat(row.dataset.sellerLng || '');
        const dist = distanceKm(sLat, sLng, lat, lng);
        const weightGram = parseInt(row.dataset.weightGram || '1000');
        const cost = shippingCost(dist, courier, weightGram);
        shipping += cost;
        const distEl = row.querySelector('.ship-distance');
        const costEl = row.querySelector('.ship-cost');
        const etaEl = row.querySelector('.ship-eta');
        if(distEl) distEl.textContent = dist === null ? 'Butuh koordinat seller' : dist + ' km';
        if(costEl) costEl.textContent = rupiah(cost);
        if(etaEl) etaEl.textContent = deliveryEstimate(dist, courier, weightGram);
    });
    const servicePercent = <?= json_encode((float)revibe_service_fee_percent()) ?>;
    const cashbackPercent = <?= json_encode((float)revibe_seller_cashback_percent()) ?>;
    const serviceFee = Math.round(subtotal * servicePercent / 100);
    const sellerCashback = Math.round(subtotal * cashbackPercent / 100);
    const platformMargin = Math.max(0, serviceFee - sellerCashback);
    const shipEl=document.getElementById('checkoutShipping'), serviceEl=document.getElementById('checkoutServiceFee'), cashbackEl=document.getElementById('checkoutSellerCashback'), marginEl=document.getElementById('checkoutPlatformMargin'), grandEl=document.getElementById('checkoutGrand');
    if(shipEl) shipEl.textContent = rupiah(shipping || 15000);
    if(serviceEl) serviceEl.textContent = rupiah(serviceFee);
    if(cashbackEl) cashbackEl.textContent = rupiah(sellerCashback);
    if(marginEl) marginEl.textContent = rupiah(platformMargin);
    if(grandEl) grandEl.textContent = rupiah(subtotal + (shipping || 15000) + serviceFee);
}
function syncBuyerCoord(lat,lng){
    const hiddenLat=document.getElementById('buyerLat'), hiddenLng=document.getElementById('buyerLng');
    const displayLat=document.getElementById('buyerLatDisplay'), displayLng=document.getElementById('buyerLngDisplay');
    if(hiddenLat) hiddenLat.value=lat; if(hiddenLng) hiddenLng.value=lng;
    if(displayLat) displayLat.value=lat; if(displayLng) displayLng.value=lng;
    const txt=document.getElementById('buyerCoordText');
    [txt, document.getElementById('buyerCoordPreview')].forEach(function(el){
        if(!el) return;
        el.dataset.lat = lat || '';
        el.dataset.lng = lng || '';
        el.textContent = 'Mencari nama lokasi...';
    });
    if(window.ReVibeLocation){ window.ReVibeLocation.applyCoordinateLabels(document); }
    recalcShipping();
}
document.getElementById('buyerLatDisplay')?.addEventListener('input', function(){ syncBuyerCoord(this.value, document.getElementById('buyerLngDisplay')?.value || ''); });
document.getElementById('buyerLngDisplay')?.addEventListener('input', function(){ syncBuyerCoord(document.getElementById('buyerLatDisplay')?.value || '', this.value); });
document.getElementById('courierSelect')?.addEventListener('change', function(){ updateShippingNote(); recalcShipping(); });
document.getElementById('paymentMethod')?.addEventListener('change', function(){
    document.querySelectorAll('.pay-panel').forEach(panel => panel.style.display = panel.dataset.pay === this.value ? 'block' : 'none');
    if(this.value === 'cod') document.getElementById('courierSelect').value = 'COD Lokal';
    recalcShipping();
});
document.getElementById('useMyLocation')?.addEventListener('click', function(){
    if(!navigator.geolocation){ alert('Browser tidak mendukung geolocation. Isi koordinat manual dari Google Maps.'); return; }
    this.textContent = 'Mengambil lokasi...';
    navigator.geolocation.getCurrentPosition(pos => {
        syncBuyerCoord(pos.coords.latitude.toFixed(7), pos.coords.longitude.toFixed(7));
        this.textContent = '📍 Perbarui Titik Lokasi Saat Ini';
    }, () => {
        this.textContent = '📍 Perbarui Titik Lokasi Saat Ini';
        alert('Lokasi tidak diizinkan. Isi koordinat manual dari Google Maps.');
    }, {enableHighAccuracy:true, timeout:10000});
});
updateShippingNote();
recalcShipping();
</script>
<?php render_revibe_floating_nav($conn); ?>
<script defer src="../assets/js/loader.js?v=25"></script>
</body></html>
