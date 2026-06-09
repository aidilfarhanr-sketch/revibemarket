<?php
require_once __DIR__ . '/../config/session.php';
include '../config/auth.php';
include '../config/db.php';

$user_id = (int)$_SESSION['user_id'];
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
ensure_seller_profile($conn,$user_id);
$seller = db_table_exists($conn,'sellers') ? mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM sellers WHERE user_id=$user_id LIMIT 1")) : null;
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total_products,
           COALESCE(SUM(sold), 0) as total_sold,
           COALESCE(SUM(price * sold), 0) as total_earned
    FROM products WHERE user_id = $user_id
"));
$monthlyQ=mysqli_query($conn,"SELECT COALESCE(SUM(qty),0) total FROM orders WHERE seller_id=$user_id AND status='completed' AND DATE_FORMAT(completed_at,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')");
$monthlySold=(int)(mysqli_fetch_assoc($monthlyQ)['total'] ?? 0);
$coins = get_coin_balance($conn, $user_id);
$sellerBalance = revibe_seller_balance($conn, $user_id);
$totalSold=(int)($stats['total_sold'] ?? 0);
$rank=seller_rank_label($totalSold);
$next=seller_next_rank_target($totalSold);
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Profile - <?= e($user['first_name']) ?> | ReVibe Market</title><link rel="stylesheet" href="../assets/css/style.css"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="stylesheet" href="../assets/css/loader.css?v=25">
</head>
<body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>

<div class="navbar"><a href="../index.php" class="btn">← Beranda</a><a href="edit_profile.php" class="btn primary">Edit Profil</a></div>
<?php if(isset($_SESSION['success'])): ?><div class="rv-toast success"><?= e($_SESSION['success']); unset($_SESSION['success']); ?><button onclick="this.parentElement.remove()">✕</button></div><?php endif; ?>
<div class="profile-container revibe-profile-page">
    <div class="profile-hero-card">
        <?php if(!empty($user['profile_photo'])): ?>
            <img class="profile-hero-photo" src="<?= e(revibe_public_file_url($user['profile_photo'], 'profile')) ?>" alt="Foto Profil">
        <?php else: ?>
            <div class="avatar-letter huge"><?= e(strtoupper(substr($user['first_name'] ?? 'R',0,1))) ?></div>
        <?php endif; ?>
        <div class="profile-hero-info">
            <span class="eyebrow">Member ReVibe Market</span>
            <h1><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h1>
            <p>📍 <?= e($user['city'] ?? $user['address'] ?? 'Lokasi belum diisi') ?></p>
            <p><?= !empty($user['bio']) ? e($user['bio']) : 'Ubah limbah jadi cerita baru ♻️' ?></p>
            <div class="quick-links profile-page-links">
                <a href="edit_profile.php">Edit Profil</a>
                <a href="edit_profile.php#alamat">Alamat & Lokasi</a>
                <a href="buyer_orders.php">Pesanan</a>
                <a href="messages.php">Chat</a>
                <a href="wishlist.php">Wishlist</a>
                <a href="seller_center.php">Seller Center</a>
                <a href="rankings.php">Peringkat</a>
                <a href="withdraw.php">Tarik Koin</a>
                <a href="seller_balance.php">Tarik Saldo</a>
                <?php if(($_SESSION['role'] ?? '') === 'admin'): ?><a href="admin/index.php">Admin</a><?php endif; ?>
            </div>
        </div>
        <div class="rank-card-mini profile-rank"><span>Rank</span><strong><?= e($rank) ?></strong><small><?= $next ? ((int)$next-$totalSold).' produk lagi ke target '.$next : 'Rank tertinggi tercapai' ?></small></div>
    </div>

    <div class="profile-kpi-grid">
        <div class="profile-kpi-card"><span>Produk Dijual</span><strong><?= (int)$stats['total_products'] ?></strong><small>Barang aktif & riwayat upload</small></div>
        <div class="profile-kpi-card"><span>Total Terjual</span><strong><?= $totalSold ?></strong><small>Akumulasi semua produk</small></div>
        <div class="profile-kpi-card"><span>Terjual Bulan Ini</span><strong><?= $monthlySold ?></strong><small>Performa periode berjalan</small></div>
        <div class="profile-kpi-card coin"><span>Coin ReVibe</span><strong>🪙 <?= number_format($coins) ?></strong><small><?= money($coins) ?> siap ditukar</small><a href="withdraw.php" class="coin-exchange-mini-btn">Tukar ke Rupiah</a></div>
    </div>
    <div class="profile-compact-revenue">Total Penjualan: <strong><?= money($stats['total_earned']) ?></strong> • Saldo Penjualan Siap Tarik: <strong><?= money($sellerBalance) ?></strong></div>

    <div class="dashboard-grid">
        <section class="panel-card"><div class="panel-title"><h2>Identitas Jual-Beli</h2><a href="edit_profile.php" class="btn">Edit</a></div>
            <div class="detail-specs profile-specs"><div><strong>Email</strong><span><?= e($user['email']) ?></span></div><div><strong>Nomor HP</strong><span><?= e($user['phone'] ?? '-') ?></span></div><div><strong>Alamat</strong><span><?= e(revibe_user_full_address($user) ?: 'Belum diatur') ?></span></div><div><strong>Titik Lokasi</strong><span class="revibe-coord-name" data-lat="<?= e($user['latitude'] ?? '') ?>" data-lng="<?= e($user['longitude'] ?? '') ?>" data-fallback="<?= e(revibe_user_region($user) ?: revibe_user_full_address($user) ?: 'Titik lokasi belum dipilih') ?>"><?= e(revibe_user_region($user) ?: 'Mencari nama lokasi...') ?></span></div><div><strong>Nama Toko</strong><span><?= e($seller['store_name'] ?? '-') ?></span></div><div><strong>Status Toko</strong><span><?= e($seller['verification_status'] ?? 'verified') ?></span></div></div>
        </section>
        <section class="panel-card"><h2>Progress Peringkat</h2><p class="muted">Setiap selesai menjual produk, angka terjual bertambah. Top 1-3 bulanan bisa diberi hadiah oleh admin.</p><div class="rank-progress"><div style="width:<?= $next ? min(100, round(($totalSold/$next)*100)) : 100 ?>%"></div></div><p><strong><?= $totalSold ?></strong><?= $next ? ' / '.$next : '+' ?> produk terjual</p></section>
    </div>

    <div class="panel-card" style="margin-top:24px;"><div class="panel-title"><h2>🛍️ Produk Saya</h2><a href="sell.php" class="btn primary">+ Jual Barang</a></div>
    <div class="product-container compact-grid profile-product-grid">
        <?php
        $q = mysqli_query($conn, "SELECT * FROM products WHERE user_id = $user_id ORDER BY id DESC");
        if(!$q || mysqli_num_rows($q) == 0){ echo "<div class='empty-state'><h3>Belum ada produk.</h3><a href='sell.php' class='btn primary'>Jual Barang Pertama</a></div>"; }
        while($p = mysqli_fetch_assoc($q)):
            $img_q = mysqli_query($conn, "SELECT image FROM product_images WHERE product_id = ".(int)$p['id']." LIMIT 1");
            $img = mysqli_fetch_assoc($img_q);
        ?>
            <a href="detail.php?id=<?= (int)$p['id'] ?>" class="card small-product-card"><img src="<?= e(revibe_public_file_url($img['image'] ?? 'default.png', 'products')) ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async"><div class="card-content"><h4><?= e($p['name']) ?></h4><p class="price"><?= money($p['price']) ?></p><div class="card-meta"><span><?= (int)$p['sold'] ?> terjual</span><span>Stok <?= (int)$p['stock'] ?></span></div><span class="status-pill"><?= e($p['product_status'] ?? 'aktif') ?></span></div></a>
        <?php endwhile; ?>
    </div></div>
</div>
<script src="../assets/js/revibe-location.js"></script>
<?php render_revibe_floating_nav($conn); ?>
<script defer src="../assets/js/loader.js?v=25"></script>
</body></html>
