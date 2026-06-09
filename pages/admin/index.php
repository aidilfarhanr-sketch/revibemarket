<?php
require_once __DIR__ . '/../../config/session.php';
include '../../config/db.php';
require_once '../../config/functions.php';
require_role('admin', '../../index.php');
$counts=[];
foreach(['users','products','orders','complaints','withdrawals'] as $t){ $q=mysqli_query($conn,"SELECT COUNT(*) total FROM `$t`"); $counts[$t]=(int)(mysqli_fetch_assoc($q)['total']??0); }
$revenue=mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(total_price),0) total FROM orders WHERE status IN ('paid','processing','shipped','delivered','completed')"));
$coins=mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(balance),0) total FROM coins"));
$pendingProducts=mysqli_query($conn,"SELECT p.*, u.first_name, u.last_name FROM products p LEFT JOIN users u ON p.user_id=u.id WHERE p.product_status='pending_review' ORDER BY p.id DESC LIMIT 10");
$payments=mysqli_query($conn,"SELECT pay.*, o.order_code FROM payments pay JOIN orders o ON pay.order_id=o.id WHERE pay.status='waiting_verification' ORDER BY pay.id DESC LIMIT 10");
$complaints=mysqli_query($conn,"SELECT c.*, o.order_code FROM complaints c JOIN orders o ON c.order_id=o.id WHERE c.status='open' ORDER BY c.id DESC LIMIT 10");
$withdrawals=mysqli_query($conn,"SELECT w.*, u.first_name, u.last_name FROM withdrawals w JOIN users u ON w.user_id=u.id WHERE w.status='pending' ORDER BY w.id DESC LIMIT 10");
$topRank=mysqli_query($conn,"SELECT u.id,u.first_name,u.last_name,COALESCE(SUM(o.qty),0) monthly_sold FROM users u JOIN orders o ON o.seller_id=u.id WHERE o.status='completed' AND DATE_FORMAT(o.completed_at,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m') GROUP BY u.id,u.first_name,u.last_name ORDER BY monthly_sold DESC LIMIT 3");
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Admin Panel - ReVibe</title><link rel="stylesheet" href="../../assets/css/style.css"><link rel="stylesheet" href="../../assets/css/loader.css?v=25">
</head><body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>

<div class="navbar admin-navbar compact-dashboard-navbar">
    <a href="../../index.php" class="btn">← Beranda</a>
    <div class="admin-nav-links">
        <a href="users.php" class="btn">👥 User</a>
        <a href="products.php" class="btn">📦 Produk</a>
        <a href="orders.php" class="btn">🧾 Transaksi</a>
        <a href="withdrawals.php" class="btn">💰 Penukaran Koin</a>
        <a href="seller_withdrawals.php" class="btn">🏦 Saldo Seller</a>
        <a href="rankings.php" class="btn">🏆 Peringkat & Hadiah</a>
        <a href="reports.php" class="btn">📊 Laporan</a>
    </div>
</div>
<?php if(isset($_SESSION['success'])): ?><div class="rv-toast success"><?= e($_SESSION['success']); unset($_SESSION['success']); ?><button onclick="this.parentElement.remove()">✕</button></div><?php endif; ?>
<div class="page-shell"><div class="page-header"><h1>Admin Panel ReVibe</h1><p>Validasi produk, pembayaran, komplain, refund, dan penarikan koin.</p></div>
<div class="stats-grid revibe-stats"><div class="stat-card"><h2><?= $counts['users'] ?></h2><p>User</p></div><div class="stat-card"><h2><?= $counts['products'] ?></h2><p>Produk</p></div><div class="stat-card"><h2><?= $counts['orders'] ?></h2><p>Transaksi</p></div><div class="stat-card"><h2><?= money($revenue['total']) ?></h2><p>GMV</p></div><div class="stat-card"><h2>🪙 <?= number_format($coins['total']??0) ?></h2><p>Koin Beredar</p></div></div>
<div class="dashboard-grid">
<section class="panel-card"><h2>Produk Menunggu Validasi</h2><table class="rv-table"><tr><th>Produk</th><th>Seller</th><th>Aksi</th></tr><?php while($p=mysqli_fetch_assoc($pendingProducts)): ?><tr><td><?= e($p['name']) ?><br><small><?= e($p['condition_status']??'') ?></small></td><td><?= e(($p['first_name']??'').' '.($p['last_name']??'')) ?></td><td><form method="POST" action="actions.php" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="approve_product"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn primary">Approve</button></form><form method="POST" action="actions.php" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="reject_product"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn danger">Reject</button></form></td></tr><?php endwhile; ?></table></section>
<section class="panel-card"><h2>Pembayaran Menunggu Verifikasi</h2><table class="rv-table"><tr><th>Order</th><th>Jumlah</th><th>Bukti</th><th>Aksi</th></tr><?php while($p=mysqli_fetch_assoc($payments)): ?><tr><td><?= e($p['order_code']) ?></td><td><?= money($p['amount']) ?></td><td><?php if($p['proof_file']): ?><a href="<?= e(revibe_private_file_url($p['proof_file'], 'payment_proofs')) ?>" target="_blank">Lihat</a><?php endif; ?></td><td><form method="POST" action="actions.php"><?= csrf_field() ?><input type="hidden" name="action" value="verify_payment"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn primary">Verifikasi</button></form></td></tr><?php endwhile; ?></table></section>
<section class="panel-card"><h2>Komplain Terbuka</h2><table class="rv-table"><tr><th>Order</th><th>Alasan</th><th>Aksi</th></tr><?php while($c=mysqli_fetch_assoc($complaints)): ?><tr><td><?= e($c['order_code']) ?></td><td><?= e($c['reason']) ?></td><td><form method="POST" action="actions.php" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="resolve_complaint"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn primary">Selesai</button></form><form method="POST" action="actions.php" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="refund_complaint"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn danger">Refund</button></form></td></tr><?php endwhile; ?></table></section>
<section class="panel-card"><div class="panel-title"><h2>Penarikan Koin</h2><a href="withdrawals.php" class="btn primary">Kelola Detail</a></div><table class="rv-table"><tr><th>User</th><th>Nominal</th><th>Aksi</th></tr><?php while($w=mysqli_fetch_assoc($withdrawals)): ?><tr><td><?= e(($w['first_name']??'').' '.($w['last_name']??'')) ?></td><td><?= money($w['amount']) ?></td><td><form method="POST" action="actions.php" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="approve_withdrawal"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><button class="btn primary">Approve</button></form><form method="POST" action="actions.php" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="reject_withdrawal"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><button class="btn danger">Reject</button></form></td></tr><?php endwhile; ?></table></section>
<section class="panel-card"><div class="panel-title"><h2>Top 3 Seller Bulan Ini</h2><a href="rankings.php" class="btn">Kelola Hadiah</a></div><table class="rv-table"><tr><th>Rank</th><th>User</th><th>Terjual</th></tr><?php $r=1; if($topRank && mysqli_num_rows($topRank)>0): while($tr=mysqli_fetch_assoc($topRank)): ?><tr><td>#<?= $r ?></td><td><?= e($tr['first_name'].' '.$tr['last_name']) ?></td><td><?= (int)$tr['monthly_sold'] ?></td></tr><?php $r++; endwhile; else: ?><tr><td colspan="3" class="muted">Belum ada data penjualan selesai bulan ini.</td></tr><?php endif; ?></table></section>
</div></div><?php render_revibe_floating_nav($conn); ?>
<script defer src="../../assets/js/loader.js?v=25"></script>
</body></html>
