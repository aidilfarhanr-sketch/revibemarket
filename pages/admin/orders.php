<?php
require_once __DIR__ . '/../../config/session.php';
include '../../config/db.php';
require_once '../../config/functions.php';
require_role('admin','../../index.php');
$orders = mysqli_query($conn, "SELECT o.*, p.name product_name, b.first_name buyer_first, b.last_name buyer_last, s.first_name seller_first, s.last_name seller_last, pay.amount payment_amount, pay.status payment_status FROM orders o JOIN products p ON o.product_id=p.id LEFT JOIN users b ON o.buyer_id=b.id LEFT JOIN users s ON o.seller_id=s.id LEFT JOIN payments pay ON pay.order_id=o.id ORDER BY o.id DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transaksi - Admin ReVibe</title>
<link rel="stylesheet" href="../../assets/css/style.css">
<link rel="stylesheet" href="../../assets/css/loader.css?v=25">
</head>
<body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market"><div class="rv-loader-card"><div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div><p>Loading ReVibe Market...</p><small>Memuat pengalaman belanja preloved terbaik...</small></div></div>
<div class="navbar admin-navbar compact-dashboard-navbar"><a href="index.php" class="btn">← Admin</a><div class="admin-nav-links"><a href="users.php" class="btn">👥 User</a><a href="products.php" class="btn">📦 Produk</a><a href="rankings.php" class="btn">🏆 Peringkat</a><a href="reports.php" class="btn">📊 Laporan</a></div></div>
<div class="page-shell"><div class="page-header"><h1>Kelola Transaksi</h1><p>Pantau order, payment, shipment, komplain, refund, service fee, cashback, dan margin demo.</p></div><div class="table-wrap"><table class="rv-table"><tr><th>Order</th><th>Produk</th><th>Buyer</th><th>Seller</th><th>Rincian</th><th>Status</th></tr>
<?php if($orders): while($o=mysqli_fetch_assoc($orders)): $serviceFee=revibe_order_service_fee($o); $cashback=revibe_order_seller_cashback($o); $margin=revibe_order_platform_margin($o); $grand=revibe_order_grand_total($o); ?>
<tr><td><?= e($o['order_code']??('#'.$o['id'])) ?></td><td><?= e($o['product_name']) ?></td><td><?= e(($o['buyer_first']??'').' '.($o['buyer_last']??'')) ?></td><td><?= e(($o['seller_first']??'').' '.($o['seller_last']??'')) ?></td><td>Barang <?= money($o['total_price']) ?><br>Ongkir <?= money($o['shipping_cost'] ?? 0) ?><br>Biaya Layanan ReVibe <?= e(revibe_service_fee_percent()) ?>% <?= money($serviceFee) ?><br>Cashback Seller <?= e(revibe_seller_cashback_percent()) ?>% <?= money($cashback) ?><br>Margin Demo <?= e(revibe_platform_margin_percent()) ?>% <?= money($margin) ?><br><b>Total <?= money($grand) ?></b></td><td><span class="status-pill"><?= e($o['status']) ?></span><br><small>Payment: <?= e($o['payment_status'] ?? '-') ?></small></td></tr>
<?php endwhile; endif; ?>
</table></div></div>
<?php render_revibe_floating_nav($conn); ?>
<script defer src="../../assets/js/loader.js?v=25"></script>
</body>
</html>
