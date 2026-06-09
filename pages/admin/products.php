<?php
require_once __DIR__ . '/../../config/session.php'; include '../../config/db.php'; require_once '../../config/functions.php'; require_role('admin','../../index.php');
$status=mysqli_real_escape_string($conn,$_GET['status']??'');
$where=$status!==''?"WHERE p.product_status='$status'":'';
$products=mysqli_query($conn,"SELECT p.*, u.first_name, u.last_name FROM products p LEFT JOIN users u ON p.user_id=u.id $where ORDER BY p.id DESC");
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Produk - Admin ReVibe</title><link rel="stylesheet" href="../../assets/css/style.css"><link rel="stylesheet" href="../../assets/css/loader.css?v=25">
</head><body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>
<div class="navbar admin-navbar compact-dashboard-navbar"><a href="index.php" class="btn">← Admin</a><div class="admin-nav-links"><a href="?status=pending_review" class="btn">⏳ Pending</a><a href="?status=approved" class="btn">✅ Approved</a><a href="?status=rejected" class="btn">✕ Rejected</a><a href="orders.php" class="btn">🧾 Transaksi</a></div></div><div class="page-shell"><div class="page-header"><h1>Kelola Produk</h1><p>Validasi produk, cek kondisi, dan status marketplace.</p></div><div class="table-wrap"><table class="rv-table"><tr><th>Produk</th><th>Seller</th><th>Kondisi</th><th>Status</th><th>Aksi</th></tr><?php while($p=mysqli_fetch_assoc($products)): ?><tr><td><?= e($p['name']) ?><br><small><?= money($p['price']) ?></small></td><td><?= e(($p['first_name']??'').' '.($p['last_name']??'')) ?></td><td><?= e($p['condition_status']??'-') ?></td><td><span class="status-pill"><?= e($p['product_status']??'') ?></span></td><td><a class="btn" href="../detail.php?id=<?= (int)$p['id'] ?>" target="_blank">Lihat</a><form method="POST" action="actions.php" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="approve_product"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn primary">Approve</button></form><form method="POST" action="actions.php" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="reject_product"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn danger">Reject</button></form></td></tr><?php endwhile; ?></table></div></div><?php render_revibe_floating_nav($conn); ?>
<script defer src="../../assets/js/loader.js?v=25"></script>
</body></html>
