<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
$user_id = (int)$_SESSION['user_id'];
$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0 || !canViewOrder($conn, $order_id, $user_id)) {
    $_SESSION['error'] = 'Payment tidak ditemukan atau akses ditolak.';
    header('Location: buyer_orders.php');
    exit;
}
$q = mysqli_query($conn, "SELECT o.*, pay.id payment_id, pay.method pay_method, pay.amount, pay.status payment_status, pay.gateway, pay.payment_url, pay.expired_at, inv.invoice_number, inv.due_at, inv.service_fee inv_service_fee, inv.total inv_total FROM orders o LEFT JOIN payments pay ON pay.order_id=o.id LEFT JOIN invoices inv ON inv.order_id=o.id WHERE o.id=$order_id LIMIT 1");
$o = $q ? mysqli_fetch_assoc($q) : null;
if (!$o) {
    $_SESSION['error'] = 'Payment tidak ditemukan.';
    header('Location: buyer_orders.php');
    exit;
}
$serviceFee = revibe_order_service_fee($o);
$sellerCashback = revibe_order_seller_cashback($o);
$platformMargin = revibe_order_platform_margin($o);
$amount = revibe_order_grand_total($o);
$method = $o['payment_method'] ?? $o['pay_method'] ?? 'transfer_bank';
$paymentStatus = $o['payment_status'] ?? 'waiting_upload';
$instruction = revibe_payment_instruction($method, $amount);
$canUpload = $method !== 'cod' && in_array($o['status'], ['pending_payment','waiting_payment','paid'], true) && in_array($paymentStatus, ['waiting_upload','rejected',''], true);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Payment - ReVibe</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/loader.css?v=28">
</head>
<body>
<?php include __DIR__ . '/../includes/loader.php'; ?>
<div class="navbar"><a href="buyer_orders.php" class="btn">← Pesanan Saya</a><a href="invoice.php?order_id=<?= (int)$order_id ?>" class="btn">Invoice</a></div>
<div class="page-shell">
<section class="form-card" style="max-width:760px;margin:28px auto">
<h1>Payment Order</h1>
<p>Invoice: <b><?= e($o['invoice_number'] ?? $o['order_code'] ?? '#'.$order_id) ?></b></p>
<div class="stats-grid">
<div class="stat-card"><h2><?= money($amount) ?></h2><p>Total Pembayaran</p></div>
<div class="stat-card"><h2><?= e($paymentStatus ?: '-') ?></h2><p>Status Payment</p></div>
<div class="stat-card"><h2><?= e($o['status'] ?? '-') ?></h2><p>Status Order</p></div>
</div>
<div class="order-payment-box">
<strong><?= e($instruction['title']) ?></strong>
<?php foreach ($instruction['lines'] as $line): ?><p><?= e($line) ?></p><?php endforeach; ?>
<?php if (!empty($instruction['image'])): ?><img src="../assets/images/<?= e($instruction['image']) ?>" class="order-payment-qr" alt="QRIS Manual"><?php endif; ?>
</div>
<div class="summary-line"><span>Harga barang</span><strong><?= money($o['total_price'] ?? 0) ?></strong></div>
<div class="summary-line"><span>Ongkir</span><strong><?= money($o['shipping_cost'] ?? 0) ?></strong></div>
<div class="summary-line"><span>Biaya Layanan ReVibe <?= e(revibe_service_fee_percent()) ?>%</span><strong><?= money($serviceFee) ?></strong></div>
<div class="summary-line"><span>Simulasi Cashback Seller <?= e(revibe_seller_cashback_percent()) ?>%</span><strong><?= money($sellerCashback) ?></strong></div>
<div class="summary-line"><span>Estimasi Margin Platform Demo <?= e(revibe_platform_margin_percent()) ?>%</span><strong><?= money($platformMargin) ?></strong></div>
<div class="summary-line grand"><span>Total bayar buyer</span><strong><?= money($amount) ?></strong></div>
<div class="info-box"><?= e(revibe_service_fee_note()) ?> <?= e(revibe_demo_payment_note()) ?></div>
<?php if (!empty($o['payment_url'])): ?><a class="btn primary full" href="<?= e($o['payment_url']) ?>" target="_blank">Bayar Sekarang</a><?php endif; ?>
<?php if ($paymentStatus === 'waiting_verification'): ?>
<div class="alert success">Bukti pembayaran sudah dikirim. Menunggu verifikasi admin.</div>
<?php elseif ($paymentStatus === 'verified'): ?>
<div class="alert success">Pembayaran sudah diverifikasi admin.</div>
<?php elseif ($canUpload): ?>
<form method="POST" action="payment_upload.php" enctype="multipart/form-data" style="margin-top:16px">
<?= csrf_field() ?>
<input type="hidden" name="order_id" value="<?= (int)$order_id ?>">
<label>Upload Bukti Pembayaran Manual</label>
<input type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp" required>
<button class="btn primary full">Upload Bukti Bayar</button>
</form>
<?php endif; ?>
<p class="muted">Payment manual tetap simulasi demo. Tidak ada payment gateway asli yang aktif pada paket Cloudflare demo ini.</p>
</section>
</div>
<?php render_revibe_floating_nav($conn); ?>
<script defer src="../assets/js/loader.js?v=28"></script>
</body>
</html>
