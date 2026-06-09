<?php
require_once __DIR__ . '/../../config/session.php'; include '../../config/db.php'; require_once '../../config/functions.php'; require_role('admin','../../index.php');
$users=mysqli_query($conn,"SELECT u.*, COALESCE(c.balance,0) coin_balance FROM users u LEFT JOIN coins c ON c.user_id=u.id ORDER BY u.id DESC");
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>User - Admin ReVibe</title><link rel="stylesheet" href="../../assets/css/style.css"><link rel="stylesheet" href="../../assets/css/loader.css?v=25">
</head><body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>
<div class="navbar admin-navbar compact-dashboard-navbar"><a href="index.php" class="btn">← Admin</a><div class="admin-nav-links"><a href="products.php" class="btn">📦 Produk</a><a href="orders.php" class="btn">🧾 Transaksi</a><a href="rankings.php" class="btn">🏆 Peringkat</a><a href="reports.php" class="btn">📊 Laporan</a></div></div><div class="page-shell"><div class="page-header"><h1>Kelola User</h1><p>Role member/admin, status blokir, saldo koin, dan aktivitas jual-beli.</p></div><div class="table-wrap"><table class="rv-table"><tr><th>Nama</th><th>Email</th><th>Role</th><th>Status</th><th>Koin</th><th>Aksi</th></tr><?php while($u=mysqli_fetch_assoc($users)): ?><tr><td><?= e($u['first_name'].' '.$u['last_name']) ?></td><td><?= e($u['email']) ?></td><td><span class="status-pill"><?= e($u['role']) ?></span></td><td><?= e($u['status']??'active') ?></td><td>🪙 <?= number_format($u['coin_balance']) ?></td><td><?php if(($u['status']??'active')==='blocked'): ?><form method="POST" action="actions.php"><?= csrf_field() ?><input type="hidden" name="action" value="unblock_user"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn primary">Unblock</button></form><?php else: ?><form method="POST" action="actions.php"><?= csrf_field() ?><input type="hidden" name="action" value="block_user"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn danger">Block</button></form><?php endif; ?></td></tr><?php endwhile; ?></table></div></div><?php render_revibe_floating_nav($conn); ?>
<script defer src="../../assets/js/loader.js?v=25"></script>
</body></html>
