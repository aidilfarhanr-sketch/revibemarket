<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
$user_id = (int)$_SESSION['user_id'];
$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0 || !canViewOrder($conn, $order_id, $user_id)) {
    $_SESSION['error'] = 'Invoice tidak ditemukan atau akses ditolak.';
    header('Location: buyer_orders.php');
    exit;
}
$q = mysqli_query($conn, "SELECT o.*, p.name product_name, p.description product_description, u.first_name seller_first, u.last_name seller_last, b.first_name buyer_first, b.last_name buyer_last, b.email buyer_email, pay.status payment_status, pay.method pay_method, pay.amount payment_amount, inv.invoice_number, inv.subtotal, inv.shipping_cost inv_shipping, inv.service_fee, inv.discount_amount, inv.total inv_total, inv.status invoice_status, inv.due_at, inv.paid_at FROM orders o JOIN products p ON p.id=o.product_id LEFT JOIN users u ON u.id=o.seller_id LEFT JOIN users b ON b.id=o.buyer_id LEFT JOIN payments pay ON pay.order_id=o.id LEFT JOIN invoices inv ON inv.order_id=o.id WHERE o.id=$order_id LIMIT 1");
$o = $q ? mysqli_fetch_assoc($q) : null;
if (!$o) {
    $_SESSION['error'] = 'Invoice tidak ditemukan.';
    header('Location: buyer_orders.php');
    exit;
}
$invoice = $o['invoice_number'] ?: ($o['order_code'] ?: '#'.$o['id']);
$serviceFee = revibe_order_service_fee($o);
$sellerCashback = revibe_order_seller_cashback($o);
$platformMargin = revibe_order_platform_margin($o);
$total = revibe_order_grand_total($o);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Invoice <?= e($invoice) ?> - ReVibe</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/loader.css?v=28">
<style>@media print{.navbar,.btn,.rv-floating-nav{display:none!important}.page-shell{max-width:100%;padding:0}.panel-card{box-shadow:none;border:1px solid #ddd}}</style>
</head>
<body>
<?php include __DIR__ . '/../includes/loader.php'; ?>
<div class="navbar"><a href="buyer_orders.php" class="btn">← Pesanan Saya</a><button onclick="window.print()" class="btn primary">Print Invoice</button></div>
<div class="page-shell"><section class="panel-card">
<div class="panel-title"><div><h1>Invoice ReVibe Market</h1><p><?= e($invoice) ?> • <?= e(date('d M Y H:i', strtotime($o['created_at'] ?? 'now'))) ?></p></div><span class="status-pill"><?= e($o['payment_status'] ?? $o['invoice_status'] ?? '-') ?></span></div>
<div class="checkout-grid"><div><h3>Pembeli</h3><p><?= e(($o['buyer_first']??'').' '.($o['buyer_last']??'')) ?><br><?= e($o['buyer_email']??'') ?><br><?= nl2br(e($o['shipping_address']??'')) ?></p></div><div><h3>Seller</h3><p><?= e(($o['seller_first']??'').' '.($o['seller_last']??'')) ?><br>Status order: <?= e($o['status']) ?><br>Metode: <?= e(revibe_payment_label($o['payment_method'] ?? $o['pay_method'] ?? 'transfer_bank')) ?></p></div></div>
<div class="table-wrap"><table class="rv-table"><tr><th>Produk</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr><tr><td><?= e($o['product_name']) ?></td><td><?= (int)$o['qty'] ?></td><td><?= money($o['total_price'] / max(1,(int)$o['qty'])) ?></td><td><?= money($o['total_price']) ?></td></tr><tr><td colspan="3">Ongkir</td><td><?= money($o['shipping_cost'] ?? $o['inv_shipping'] ?? 0) ?></td></tr><tr><td colspan="3">Biaya Layanan ReVibe <?= e(revibe_service_fee_percent()) ?>%</td><td><?= money($serviceFee) ?></td></tr><tr><td colspan="3">Simulasi Cashback Seller <?= e(revibe_seller_cashback_percent()) ?>%</td><td><?= money($sellerCashback) ?></td></tr><tr><td colspan="3">Estimasi Margin Platform Demo <?= e(revibe_platform_margin_percent()) ?>%</td><td><?= money($platformMargin) ?></td></tr><tr><td colspan="3">Diskon/Voucher</td><td>-<?= money($o['discount_amount'] ?? 0) ?></td></tr><tr><th colspan="3">Total Bayar Buyer</th><th><?= money($total) ?></th></tr></table></div>
<p class="muted"><?= e(revibe_service_fee_note()) ?> <?= e(revibe_demo_payment_note()) ?></p>
</section></div>
<?php render_revibe_floating_nav($conn); ?>
<script defer src="../assets/js/loader.js?v=28"></script>
</body>
</html>
