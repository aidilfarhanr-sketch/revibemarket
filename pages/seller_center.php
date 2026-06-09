<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
revibe_require_verified_account($conn, 'verification_required.php');
$user_id = (int)$_SESSION['user_id'];
ensure_seller_profile($conn, $user_id);
$stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total_products, COALESCE(SUM(sold),0) sold, COALESCE(SUM(price*sold),0) earned FROM products WHERE user_id=$user_id"));
$orderStats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total_orders, COALESCE(SUM(total_price),0) total_sales FROM orders WHERE seller_id=$user_id"));
$monthlySoldQ = mysqli_query($conn, "SELECT COALESCE(SUM(qty),0) total FROM orders WHERE seller_id=$user_id AND status='completed' AND DATE_FORMAT(completed_at,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')");
$monthlySold = (int)(mysqli_fetch_assoc($monthlySoldQ)['total'] ?? 0);
$coinBalance = get_coin_balance($conn, $user_id);
$sellerBalance = revibe_seller_balance($conn, $user_id);
$totalSold = (int)($stats['sold'] ?? 0);
$rankLabel = seller_rank_label($totalSold);
$nextTarget = seller_next_rank_target($totalSold);
$products = mysqli_query($conn, "SELECT * FROM products WHERE user_id=$user_id ORDER BY id DESC LIMIT 20");
$orders = mysqli_query($conn, "SELECT o.*, p.name product_name, u.first_name buyer_first, u.last_name buyer_last FROM orders o JOIN products p ON o.product_id=p.id LEFT JOIN users u ON o.buyer_id=u.id WHERE o.seller_id=$user_id ORDER BY o.id DESC LIMIT 20");
function seller_status_label($s) {
    $map = ['pending_payment'=>'Menunggu pembayaran diverifikasi admin','paid'=>'Dibayar','paid_waiting_seller'=>'Dibayar - Menunggu Seller','processing'=>'Diproses','shipped'=>'Dikirim','delivered'=>'Sampai','completed'=>'Selesai','cancelled'=>'Dibatalkan','expired'=>'Expired','complaint'=>'Komplain','refund'=>'Refund'];
    return $map[$s] ?? $s;
}
function seller_next_status_options($status) {
    if ($status === 'paid_waiting_seller') return ['processing'=>'Diproses'];
    if ($status === 'processing') return ['shipped'=>'Dikirim'];
    if ($status === 'shipped') return ['delivered'=>'Sampai / Sudah diterima'];
    return [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Seller Center - ReVibe</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/loader.css?v=25">
</head>
<body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market"><div class="rv-loader-card"><div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div><p>Loading ReVibe Market...</p><small>Memuat pengalaman belanja preloved terbaik...</small></div></div>
<div class="navbar seller-center-navbar compact-dashboard-navbar"><a href="../index.php" class="btn">← Beranda</a><div class="dash-nav-center"><a href="sell.php" class="btn primary">+ Upload Produk</a></div><a href="rankings.php" class="btn">🏆 Peringkat</a></div>
<?php if(isset($_SESSION['success'])): ?><div class="rv-toast success"><?= e($_SESSION['success']); unset($_SESSION['success']); ?><button onclick="this.parentElement.remove()">✕</button></div><?php endif; ?>
<?php if(isset($_SESSION['error'])): ?><div class="rv-toast error"><?= e($_SESSION['error']); unset($_SESSION['error']); ?><button onclick="this.parentElement.remove()">✕</button></div><?php endif; ?>
<div class="page-shell">
<div class="page-header seller-hero"><div><span class="eyebrow">Jual & beli dalam satu akun</span><h1>Seller Center ReVibe</h1><p>Semua member bisa upload barang, mengelola order, mendapat simulasi cashback <?= e(revibe_seller_cashback_percent()) ?>% setelah order completed, dan masuk sistem peringkat.</p></div><div class="rank-card-mini"><span>Rank Kamu</span><strong><?= e($rankLabel) ?></strong><small><?= $nextTarget ? ((int)$nextTarget - $totalSold).' produk lagi ke rank berikutnya' : 'Rank tertinggi tercapai' ?></small></div></div>
<div class="stats-grid revibe-stats"><div class="stat-card"><h2><?= (int)$stats['total_products'] ?></h2><p>Produk</p></div><div class="stat-card"><h2><?= $totalSold ?></h2><p>Total Terjual</p></div><div class="stat-card"><h2><?= $monthlySold ?></h2><p>Terjual Bulan Ini</p></div><div class="stat-card"><h2><?= money($orderStats['total_sales']) ?></h2><p>Transaksi</p></div><div class="stat-card"><h2>🪙 <?= number_format($coinBalance) ?></h2><p>Saldo Koin</p></div><div class="stat-card"><h2><?= money($sellerBalance) ?></h2><p>Saldo Available</p><small>Pending: <?= money(revibe_seller_pending_balance($conn,$user_id)) ?></small><a href="seller_balance.php" class="mini-link">Tarik Saldo</a></div></div>
<div class="info-box"><strong>Sistem demo:</strong> Biaya Layanan ReVibe <?= e(revibe_service_fee_percent()) ?>%, simulasi cashback seller <?= e(revibe_seller_cashback_percent()) ?>%, estimasi margin platform demo <?= e(revibe_platform_margin_percent()) ?>%. <?= e(revibe_demo_payment_note()) ?></div>
<div class="dashboard-grid">
<section class="panel-card"><div class="panel-title"><h2>Produk Saya</h2><a href="sell.php" class="btn">Tambah</a></div><div class="table-wrap"><table class="rv-table"><thead><tr><th>Produk</th><th>Status</th><th>Stok</th><th>Terjual</th><th>Aksi</th></tr></thead><tbody>
<?php if($products && mysqli_num_rows($products)>0): while($p=mysqli_fetch_assoc($products)): ?>
<tr><td><?= e($p['name']) ?><br><small><?= e($p['condition_status']??'') ?></small></td><td><span class="status-pill"><?= e($p['product_status']??'aktif') ?></span></td><td><?= (int)$p['stock'] ?></td><td><?= (int)$p['sold'] ?></td><td><div class="seller-product-actions"><a class="btn" href="edit_product.php?id=<?= (int)$p['id'] ?>">Edit Foto/Data</a><form method="POST" action="toggle_product.php" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn danger" type="submit"><?= (($p['product_status'] ?? '') === 'inactive') ? 'Aktifkan Ulang' : 'Nonaktif' ?></button></form><form method="POST" action="delete_product.php" class="inline-form" onsubmit="return confirm('Yakin hapus produk ini? Jika belum ada transaksi, produk dan fotonya akan dihapus. Jika sudah ada transaksi, produk akan disembunyikan agar riwayat order tetap aman.');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn danger seller-delete-btn" type="submit">Hapus</button></form></div></td></tr>
<?php endwhile; else: ?><tr><td colspan="5" class="muted">Belum ada produk. Upload barang preloved pertamamu.</td></tr><?php endif; ?>
</tbody></table></div></section>
<section class="panel-card"><div class="panel-title"><h2>Order Masuk</h2><div><a href="withdraw.php" class="btn">Tarik Koin</a> <a href="seller_balance.php" class="btn">Tarik Saldo</a></div></div><div class="table-wrap"><table class="rv-table"><thead><tr><th>Order</th><th>Pembeli</th><th>Status</th><th>Total</th><th>Update</th></tr></thead><tbody>
<?php if($orders && mysqli_num_rows($orders)>0): while($o=mysqli_fetch_assoc($orders)): $opts = seller_next_status_options($o['status'] ?? ''); ?>
<tr><td><?= e($o['product_name']) ?><br><small><?= e($o['order_code']??('#'.$o['id'])) ?></small><br><small><?= e(revibe_payment_label($o['payment_method'] ?? 'transfer_bank')) ?> • Estimasi <?= e($o['delivery_estimate'] ?? revibe_delivery_estimate_text($o['distance_km'] ?? null, $o['courier'] ?? 'JNE')) ?></small></td><td><?= e(($o['buyer_first']??'').' '.($o['buyer_last']??'')) ?></td><td><span class="status-pill status-<?= e($o['status']) ?>"><?= e(seller_status_label($o['status'])) ?></span></td><td><?= money($o['total_price']) ?><br><small>Cashback demo: <?= money(revibe_order_seller_cashback($o)) ?></small></td><td>
<?php if(($o['status'] ?? '') === 'pending_payment'): ?>
<span class="muted">Menunggu pembayaran diverifikasi admin.</span>
<?php elseif(empty($opts)): ?>
<span class="muted">Tidak ada update status yang perlu dilakukan.</span>
<?php else: ?>
<form method="POST" action="seller_order_update.php" class="mini-update-form"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><select name="status"><?php foreach($opts as $value=>$label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select><input type="text" name="tracking_number" placeholder="No. resi / COD"><button class="btn primary" type="submit">Update</button></form>
<?php endif; ?>
</td></tr>
<?php endwhile; else: ?><tr><td colspan="5" class="muted">Belum ada order masuk.</td></tr><?php endif; ?>
</tbody></table></div></section>
</div>
</div>
<?php render_revibe_floating_nav($conn); ?>
<script defer src="../assets/js/loader.js?v=25"></script>
</body>
</html>
