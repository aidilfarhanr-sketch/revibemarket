<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
$user_id=(int)$_SESSION['user_id'];
$q=db_table_exists($conn,'wishlist')?mysqli_query($conn,"SELECT p.*, pi.image FROM wishlist w JOIN products p ON w.product_id=p.id LEFT JOIN product_images pi ON pi.product_id=p.id WHERE w.user_id=$user_id GROUP BY p.id ORDER BY w.id DESC"):false;
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Wishlist - ReVibe</title><link rel="stylesheet" href="../assets/css/style.css"><link rel="stylesheet" href="../assets/css/loader.css?v=25">
</head><body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>
<div class="navbar"><a href="../index.php" class="btn">← Beranda</a></div><div class="page-shell"><div class="page-header"><h1>Wishlist</h1><p>Produk preloved yang kamu simpan.</p></div>
<?php if(!$q || mysqli_num_rows($q)===0): ?><div class="empty-state"><h3>Wishlist kosong</h3><a href="../index.php" class="btn primary">Cari Produk</a></div><?php else: ?><div class="product-container compact-grid"><?php while($p=mysqli_fetch_assoc($q)): ?><a href="detail.php?id=<?= (int)$p['id'] ?>" class="card"><img src="<?= e(revibe_public_file_url($p['image']??'default.png', 'products')) ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async"><div class="card-content"><h4><?= e($p['name']) ?></h4><p class="price"><?= money($p['price']) ?></p></div></a><?php endwhile; ?></div><?php endif; ?></div><?php render_revibe_floating_nav($conn); ?>
<script defer src="../assets/js/loader.js?v=25"></script>
</body></html>
