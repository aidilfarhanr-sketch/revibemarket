<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
$leaderboard=mysqli_query($conn,"
    SELECT u.id, u.first_name, u.last_name,
           COALESCE(SUM(p.sold),0) total_sold,
           COALESCE(ms.monthly_sold,0) monthly_sold,
           COALESCE(SUM(p.price*p.sold),0) total_sales
    FROM users u
    LEFT JOIN products p ON p.user_id=u.id
    LEFT JOIN (
        SELECT seller_id, COALESCE(SUM(qty),0) monthly_sold
        FROM orders
        WHERE status='completed' AND DATE_FORMAT(completed_at,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')
        GROUP BY seller_id
    ) ms ON ms.seller_id=u.id
    WHERE COALESCE(u.status,'active')='active'
    GROUP BY u.id, u.first_name, u.last_name, ms.monthly_sold
    HAVING total_sold > 0 OR monthly_sold > 0
    ORDER BY monthly_sold DESC, total_sold DESC
    LIMIT 30
");
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Peringkat Seller - ReVibe</title><link rel="stylesheet" href="../assets/css/style.css"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="stylesheet" href="../assets/css/loader.css?v=25">
</head><body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>

<div class="navbar"><a href="../index.php" class="btn">← Beranda</a><a href="seller_center.php" class="btn">Seller Center</a></div>
<div class="page-shell"><div class="page-header seller-hero"><div><span class="eyebrow">Leaderboard Bulanan</span><h1>🏆 Peringkat ReVibe Seller</h1><p>Peringkat dihitung dari produk yang berhasil selesai terjual bulan ini. Top 1 sampai 3 dapat hadiah dari admin.</p></div><div class="rank-card-mini"><span>Milestone</span><strong>50 • 100 • 250 • 500</strong><small>Semakin banyak terjual, semakin tinggi rank toko kamu.</small></div></div>
<div class="rank-milestone-grid"><div><strong>50</strong><span>Bronze Seller</span></div><div><strong>100</strong><span>Silver Seller</span></div><div><strong>250</strong><span>Gold Seller</span></div><div><strong>500</strong><span>Platinum Seller</span></div><div><strong>1000</strong><span>Diamond ReViber</span></div></div>
<div class="leaderboard-list">
<?php $pos=1; if($leaderboard && mysqli_num_rows($leaderboard)>0): while($u=mysqli_fetch_assoc($leaderboard)): $rank=seller_rank_label($u['total_sold']); ?>
    <div class="leaderboard-card <?= $pos<=3?'top-rank':'' ?>"><div class="rank-number">#<?= $pos ?></div><div class="avatar-letter"><?= e(strtoupper(substr($u['first_name']??'U',0,1))) ?></div><div class="leaderboard-body"><h3><?= e(trim(($u['first_name']??'User').' '.($u['last_name']??''))) ?></h3><p><span class="mini-badge"><?= e($rank) ?></span> <span class="mini-badge condition"><?= (int)$u['monthly_sold'] ?> terjual bulan ini</span></p></div><div class="leaderboard-score"><strong><?= (int)$u['total_sold'] ?></strong><span>Total terjual</span></div></div>
<?php $pos++; endwhile; else: ?><div class="empty-state"><h3>Belum ada peringkat</h3><p class="muted">Peringkat akan muncul setelah produk berhasil selesai terjual.</p></div><?php endif; ?>
</div></div><?php render_revibe_floating_nav($conn); ?>
<script defer src="../assets/js/loader.js?v=25"></script>
</body></html>
