<?php
require_once __DIR__ . '/../../config/session.php';
include '../../config/db.php';
require_once '../../config/functions.php';
require_role('admin','../../index.php');
$monthly = mysqli_query($conn, "SELECT DATE_FORMAT(created_at,'%Y-%m') month, COUNT(*) total_order, COALESCE(SUM(total_price),0) sales, COALESCE(SUM(shipping_cost),0) shipping, COALESCE(SUM(service_fee),0) stored_fee FROM orders GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY month DESC LIMIT 12");
$summaryQ = mysqli_query($conn, "SELECT COUNT(*) total_order, COALESCE(SUM(total_price),0) goods, COALESCE(SUM(shipping_cost),0) shipping, COALESCE(SUM(service_fee),0) stored_fee FROM orders");
$summary = $summaryQ ? mysqli_fetch_assoc($summaryQ) : ['total_order'=>0,'goods'=>0,'shipping'=>0,'stored_fee'=>0];
$totalGoods = (int)($summary['goods'] ?? 0);
$totalShipping = (int)($summary['shipping'] ?? 0);
$totalServiceFee = (int)($summary['stored_fee'] ?? 0);
if ($totalServiceFee <= 0) $totalServiceFee = revibe_calculate_service_fee($totalGoods);
$totalCashback = revibe_calculate_seller_cashback($totalGoods);
$totalMargin = max(0, $totalServiceFee - $totalCashback);
$topProducts = mysqli_query($conn, "SELECT name, sold, price, category FROM products ORDER BY sold DESC LIMIT 10");
$topCategories = mysqli_query($conn, "SELECT category, COUNT(*) total, COALESCE(SUM(sold),0) sold FROM products GROUP BY category ORDER BY sold DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan - Admin ReVibe</title>
<link rel="stylesheet" href="../../assets/css/style.css">
<link rel="stylesheet" href="../../assets/css/loader.css?v=25">
</head>
<body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market"><div class="rv-loader-card"><div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div><p>Loading ReVibe Market...</p><small>Memuat pengalaman belanja preloved terbaik...</small></div></div>
<div class="navbar admin-navbar compact-dashboard-navbar"><a href="index.php" class="btn">← Admin</a><div class="admin-nav-links"><a href="users.php" class="btn">👥 User</a><a href="products.php" class="btn">📦 Produk</a><a href="orders.php" class="btn">🧾 Transaksi</a><button onclick="window.print()" class="btn primary">Export PDF</button></div></div>
<div class="page-shell"><div class="page-header"><h1>Laporan ReVibe Market</h1><p>Ringkasan penjualan, fee platform demo, cashback seller, margin demo, produk terlaris, dan kategori ramai.</p></div>
<div class="stats-grid"><div class="stat-card"><h2><?= money($totalGoods) ?></h2><p>Total harga barang</p></div><div class="stat-card"><h2><?= money($totalShipping) ?></h2><p>Total ongkir</p></div><div class="stat-card"><h2><?= money($totalServiceFee) ?></h2><p>Biaya layanan <?= e(revibe_service_fee_percent()) ?>%</p></div><div class="stat-card"><h2><?= money($totalCashback) ?></h2><p>Cashback seller <?= e(revibe_seller_cashback_percent()) ?>%</p></div><div class="stat-card"><h2><?= money($totalMargin) ?></h2><p>Margin demo <?= e(revibe_platform_margin_percent()) ?>%</p></div></div>
<div class="info-box"><?= e(revibe_service_fee_note()) ?> <?= e(revibe_demo_payment_note()) ?></div>
<div class="dashboard-grid"><section class="panel-card"><h2>Transaksi Bulanan</h2><table class="rv-table"><tr><th>Bulan</th><th>Order</th><th>Harga Barang</th><th>Ongkir</th><th>Fee 12%</th><th>Cashback 6%</th><th>Margin 6%</th></tr><?php while($m=mysqli_fetch_assoc($monthly)): $goods=(int)$m['sales']; $fee=(int)$m['stored_fee']; if($fee<=0) $fee=revibe_calculate_service_fee($goods); $cashback=revibe_calculate_seller_cashback($goods); $margin=max(0,$fee-$cashback); ?><tr><td><?= e($m['month']) ?></td><td><?= (int)$m['total_order'] ?></td><td><?= money($goods) ?></td><td><?= money($m['shipping']) ?></td><td><?= money($fee) ?></td><td><?= money($cashback) ?></td><td><?= money($margin) ?></td></tr><?php endwhile; ?></table></section><section class="panel-card"><h2>Produk Terlaris</h2><table class="rv-table"><tr><th>Produk</th><th>Kategori</th><th>Terjual</th></tr><?php while($p=mysqli_fetch_assoc($topProducts)): ?><tr><td><?= e($p['name']) ?></td><td><?= e($p['category']) ?></td><td><?= (int)$p['sold'] ?></td></tr><?php endwhile; ?></table></section><section class="panel-card"><h2>Kategori Populer</h2><table class="rv-table"><tr><th>Kategori</th><th>Total Produk</th><th>Terjual</th></tr><?php while($c=mysqli_fetch_assoc($topCategories)): ?><tr><td><?= e($c['category']) ?></td><td><?= (int)$c['total'] ?></td><td><?= (int)$c['sold'] ?></td></tr><?php endwhile; ?></table></section></div></div>
<?php render_revibe_floating_nav($conn); ?>
<script defer src="../../assets/js/loader.js?v=25"></script>
</body>
</html>
