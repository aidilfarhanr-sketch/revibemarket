<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
$user_id = (int)$_SESSION['user_id'];
$q = mysqli_query($conn, "SELECT o.*, p.name AS product_name, p.price, u.first_name AS seller_first, u.last_name AS seller_last, pi.image, pay.proof_file, pay.amount AS payment_amount, pay.method AS pay_method, pay.status AS payment_status, s.tracking_number, COALESCE(o.delivery_estimate, s.delivery_estimate) AS delivery_estimate FROM orders o JOIN products p ON o.product_id=p.id LEFT JOIN users u ON o.seller_id=u.id LEFT JOIN product_images pi ON pi.product_id=p.id LEFT JOIN payments pay ON pay.order_id=o.id LEFT JOIN shipments s ON s.order_id=o.id WHERE o.buyer_id=$user_id GROUP BY o.id ORDER BY o.id DESC");
function status_label($s) {
    $map = ['pending_payment'=>'Menunggu Pembayaran','paid'=>'Dibayar','paid_waiting_seller'=>'Dibayar - Menunggu Seller','processing'=>'Diproses Seller','packed'=>'Dikemas','shipped'=>'Dikirim','delivered'=>'Sampai','completed'=>'Selesai','cancelled'=>'Dibatalkan','complaint'=>'Komplain/Dispute','refund'=>'Refund'];
    return $map[$s] ?? $s;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pesanan Saya - ReVibe</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/loader.css?v=25">
</head>
<body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market"><div class="rv-loader-card"><div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div><p>Loading ReVibe Market...</p><small>Memuat pengalaman belanja preloved terbaik...</small></div></div>
<div class="navbar"><a href="../index.php" class="btn">← Beranda</a><a href="cart.php" class="btn">Keranjang</a></div>
<?php if(isset($_SESSION['error'])): ?><div class="rv-toast error"><?= e($_SESSION['error']); unset($_SESSION['error']); ?><button onclick="this.parentElement.remove()">✕</button></div><?php endif; ?>
<?php if(isset($_SESSION['success'])): ?><div class="rv-toast success"><?= e($_SESSION['success']); unset($_SESSION['success']); ?><button onclick="this.parentElement.remove()">✕</button></div><?php endif; ?>
<div class="page-shell">
<div class="page-header"><h1>Pesanan Saya</h1><p>Lacak status pembayaran, pengiriman, review, dan komplain. <?= e(revibe_demo_payment_note()) ?></p></div>
<?php if(!$q || mysqli_num_rows($q)===0): ?>
<div class="empty-state"><h3>Belum ada pesanan.</h3><a href="../index.php" class="btn primary">Mulai Belanja</a></div>
<?php else: ?>
<div class="order-list">
<?php while($o = mysqli_fetch_assoc($q)): $serviceFee = revibe_order_service_fee($o); $sellerCashback = revibe_order_seller_cashback($o); $platformMargin = revibe_order_platform_margin($o); $grand = revibe_order_grand_total($o); $payInfo = revibe_payment_instruction($o['payment_method'] ?? $o['pay_method'] ?? 'transfer_bank', $grand); $paymentStatus = $o['payment_status'] ?? 'waiting_upload'; ?>
<div class="order-card">
<div class="order-main"><img src="<?= e(revibe_public_file_url($o['image'] ?? 'default.png', 'products')) ?>" alt=""><div><h3><?= e($o['product_name']) ?></h3><p>Order <?= e($o['order_code'] ?? ('#'.$o['id'])) ?> • Seller: <?= e(($o['seller_first'] ?? '') . ' ' . ($o['seller_last'] ?? '')) ?></p><p><?= (int)$o['qty'] ?> item • Barang <?= money($o['total_price']) ?> • Ongkir <?= money($o['shipping_cost'] ?? 0) ?></p><p>Biaya Layanan ReVibe <?= e(revibe_service_fee_percent()) ?>%: <b><?= money($serviceFee) ?></b> • Total: <b><?= money($grand) ?></b></p><p class="muted">Cashback seller demo <?= e(revibe_seller_cashback_percent()) ?>%: <?= money($sellerCashback) ?> • Margin platform demo: <?= money($platformMargin) ?></p><?php if(isset($o['distance_km']) && $o['distance_km'] !== null && $o['distance_km'] !== ''): ?><p class="muted">Jarak seller ke pembeli: <?= e($o['distance_km']) ?> km • Estimasi <?= e($o['delivery_estimate'] ?: revibe_delivery_estimate_text($o['distance_km'], $o['courier'] ?? 'JNE')) ?></p><?php endif; ?><p class="muted">Pembayaran: <?= e(revibe_payment_label($o['payment_method'] ?? $o['pay_method'] ?? 'transfer_bank')) ?> • Status payment: <?= e($paymentStatus ?: '-') ?></p><span class="status-pill status-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span><?php if(!empty($o['tracking_number'])): ?><span class="status-pill">Resi: <?= e($o['tracking_number']) ?></span><?php endif; ?></div></div>
<?php if($o['status']==='pending_payment' || ($o['payment_method'] ?? '')==='cod'): ?>
<div class="order-payment-box"><strong><?= e($payInfo['title']) ?></strong><?php foreach($payInfo['lines'] as $line): ?><p><?= e($line) ?></p><?php endforeach; ?><?php if(!empty($payInfo['image'])): ?><img src="../assets/images/<?= e($payInfo['image']) ?>" alt="QR Pembayaran" class="order-payment-qr"><?php endif; ?></div>
<?php endif; ?>
<div class="order-actions">
<a class="btn" href="invoice.php?order_id=<?= (int)$o['id'] ?>">Invoice</a>
<a class="btn" href="payment.php?order_id=<?= (int)$o['id'] ?>">Payment</a>
<?php if(($o['status']==='pending_payment' || $o['status']==='paid') && (($o['payment_method'] ?? '') !== 'cod') && in_array($paymentStatus, ['waiting_upload','rejected',''], true)): ?>
<form method="POST" action="payment_upload.php" enctype="multipart/form-data" class="upload-proof-form"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp" required><button class="btn primary" type="submit">Upload Bukti Bayar</button></form>
<form method="POST" action="cancel_order.php" class="inline-form" onsubmit="return confirm('Batalkan dan hapus pesanan ini dari daftar? Stok produk akan dikembalikan.');"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><button class="btn danger" type="submit">Batalkan</button></form>
<?php elseif($paymentStatus === 'waiting_verification'): ?>
<span class="muted">Bukti pembayaran sudah dikirim. Menunggu verifikasi admin.</span>
<?php elseif($paymentStatus === 'verified'): ?>
<span class="muted">Pembayaran sudah diverifikasi admin.</span>
<?php endif; ?>
<?php if(in_array($o['status'], ['shipped','delivered'], true)): ?>
<form method="POST" action="confirm_received.php" class="inline-form"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><button class="btn primary" type="submit">Konfirmasi Sampai</button></form>
<?php endif; ?>
<?php if($o['status']==='completed'): ?><a class="btn" href="review.php?order_id=<?= (int)$o['id'] ?>">Beri Ulasan</a><?php endif; ?>
<?php if(!in_array($o['status'], ['completed','cancelled','refund'], true)): ?><a class="btn danger" href="complaint.php?order_id=<?= (int)$o['id'] ?>">Komplain</a><?php endif; ?>
</div>
</div>
<?php endwhile; ?>
</div>
<?php endif; ?>
</div>
<?php render_revibe_floating_nav($conn); ?>
<script defer src="../assets/js/loader.js?v=25"></script>
</body>
</html>
