<?php
require_once __DIR__ . '/../../config/session.php'; include '../../config/db.php'; require_once '../../config/functions.php'; require_role('admin','../../index.php');
$period=$_GET['period'] ?? date('Y-m');
$periodSafe=mysqli_real_escape_string($conn,$period);
$top=mysqli_query($conn,"
    SELECT u.id, u.first_name, u.last_name, COALESCE(SUM(o.qty),0) monthly_sold, COALESCE(SUM(o.total_price),0) sales
    FROM users u
    JOIN orders o ON o.seller_id=u.id
    WHERE o.status='completed' AND DATE_FORMAT(o.completed_at,'%Y-%m')='$periodSafe'
    GROUP BY u.id,u.first_name,u.last_name
    ORDER BY monthly_sold DESC, sales DESC
    LIMIT 10
");
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Peringkat & Hadiah - Admin</title><link rel="stylesheet" href="../../assets/css/style.css"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="stylesheet" href="../../assets/css/loader.css?v=25">
</head><body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>

<div class="navbar admin-navbar compact-dashboard-navbar"><a href="index.php" class="btn">← Admin</a><div class="admin-nav-links"><a href="users.php" class="btn">👥 User</a><a href="products.php" class="btn">📦 Produk</a><a href="orders.php" class="btn">🧾 Transaksi</a><a href="reports.php" class="btn">📊 Laporan</a></div></div>
<?php if(isset($_SESSION['success'])): ?><div class="rv-toast success"><?= e($_SESSION['success']); unset($_SESSION['success']); ?><button onclick="this.parentElement.remove()">✕</button></div><?php endif; ?>
<div class="page-shell"><div class="page-header"><h1>Admin Peringkat & Hadiah</h1><p>Atur hadiah top 1-3 seller berdasarkan barang terjual pada bulan tertentu.</p></div>
<form class="form-card rank-period-form" method="GET"><label>Periode</label><input type="month" name="period" value="<?= e($period) ?>"><button class="btn primary">Tampilkan</button></form>
<div class="panel-card"><h2>Top Seller Periode <?= e($period) ?></h2><div class="table-wrap"><table class="rv-table"><tr><th>Rank</th><th>User</th><th>Terjual</th><th>Penjualan</th><th>Hadiah Koin</th><th>Aksi</th></tr>
<?php $pos=1; if($top && mysqli_num_rows($top)>0): while($u=mysqli_fetch_assoc($top)): ?><tr><td>#<?= $pos ?></td><td><?= e($u['first_name'].' '.$u['last_name']) ?><br><small><?= e(seller_rank_label($u['monthly_sold'])) ?> bulan ini</small></td><td><?= (int)$u['monthly_sold'] ?></td><td><?= money($u['sales']) ?></td><td><form method="POST" action="actions.php" class="mini-update-form"><?= csrf_field() ?><input type="hidden" name="action" value="grant_rank_reward"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="rank_position" value="<?= $pos ?>"><input type="hidden" name="sold_count" value="<?= (int)$u['monthly_sold'] ?>"><input type="hidden" name="period" value="<?= e($period) ?>"><input type="number" name="amount" min="0" value="<?= $pos===1?100000:($pos===2?75000:($pos===3?50000:0)) ?>"></td><td><button class="btn primary" <?= $pos>3?'disabled':'' ?>>Beri Hadiah</button></form></td></tr><?php $pos++; endwhile; else: ?><tr><td colspan="6" class="muted">Belum ada transaksi selesai pada periode ini.</td></tr><?php endif; ?>
</table></div></div></div><?php render_revibe_floating_nav($conn); ?>
<script defer src="../../assets/js/loader.js?v=25"></script>
</body></html>
