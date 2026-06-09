<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
$user_id=(int)$_SESSION['user_id'];
$conversations=false;
if(db_table_exists($conn,'chat_messages')){
    $conversations=mysqli_query($conn,"
        SELECT c.other_id, c.last_id, c.unread_count, m.message, m.product_id, m.created_at,
               u.first_name, u.last_name, u.profile_photo,
               p.name AS product_name, p.price AS product_price, p.location AS product_location, p.condition_status AS product_condition,
               (SELECT image FROM product_images WHERE product_id=p.id ORDER BY id ASC LIMIT 1) AS product_image
        FROM (
            SELECT CASE WHEN sender_id=$user_id THEN receiver_id ELSE sender_id END AS other_id,
                   MAX(id) AS last_id,
                   SUM(CASE WHEN receiver_id=$user_id AND is_read=0 THEN 1 ELSE 0 END) AS unread_count
            FROM chat_messages
            WHERE sender_id=$user_id OR receiver_id=$user_id
            GROUP BY other_id
        ) c
        JOIN chat_messages m ON m.id=c.last_id
        JOIN users u ON u.id=c.other_id
        LEFT JOIN products p ON p.id=m.product_id
        ORDER BY m.created_at DESC
    ");
}
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Pesan - ReVibe Market</title><link rel="stylesheet" href="../assets/css/style.css"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="stylesheet" href="../assets/css/loader.css?v=25">
</head><body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>

<div class="navbar"><a href="../index.php" class="btn">← Beranda</a><a href="seller_center.php" class="btn">Seller Center</a></div>
<div class="page-shell narrow">
<div class="page-header"><h1>Pesan ReVibe</h1><p>Chat langsung dengan pengguna lain untuk tanya kondisi barang, nego harga, COD, atau pengiriman.</p></div>
<div class="conversation-list shopee-conversation-list">
<?php if($conversations && mysqli_num_rows($conversations)>0): while($c=mysqli_fetch_assoc($conversations)):
    $otherName = trim(($c['first_name']??'User').' '.($c['last_name']??''));
    $chatHref = 'chat.php?user_id='.(int)$c['other_id'].(!empty($c['product_id']) ? '&product_id='.(int)$c['product_id'] : '');
?>
    <div class="conversation-card shopee-conversation-card">
        <a class="conversation-avatar-link" href="<?= e($chatHref) ?>">
            <?php if(!empty($c['profile_photo'])): ?>
                <img class="conversation-avatar-img" src="<?= e(revibe_public_file_url($c['profile_photo'], 'profile')) ?>" alt="<?= e($otherName) ?>">
            <?php else: ?>
                <div class="avatar-letter"><?= e(strtoupper(substr($c['first_name'] ?? 'U',0,1))) ?></div>
            <?php endif; ?>
        </a>
        <a class="conversation-body" href="<?= e($chatHref) ?>">
            <div class="conversation-top"><strong><?= e($otherName) ?></strong><small><?= e(date('d M H:i', strtotime($c['created_at']))) ?></small></div>
            <p><?= e($c['message']) ?></p>
        </a>
        <?php if(!empty($c['product_name'])): ?>
            <a class="conversation-product-mini conversation-product-link" href="detail.php?id=<?= (int)$c['product_id'] ?>">
                <img src="<?= e(revibe_public_file_url($c['product_image'] ?: 'default.png', 'products')) ?>" alt="<?= e($c['product_name']) ?>">
                <div><strong><?= e($c['product_name']) ?></strong><span><?= money($c['product_price']) ?> • <?= e($c['product_condition'] ?? 'Preloved') ?> • <?= e($c['product_location'] ?? 'Lokasi belum diisi') ?></span><em>Lihat detail produk</em></div>
            </a>
        <?php endif; ?>
        <?php if((int)$c['unread_count']>0): ?><span class="cart-count chat-unread"><?= (int)$c['unread_count'] ?></span><?php endif; ?>
    </div>
<?php endwhile; else: ?>
    <div class="empty-state"><h3>Belum ada chat</h3><p class="muted">Buka detail produk lalu klik tombol chat untuk memulai obrolan dengan penjual.</p><a href="../index.php" class="btn primary">Cari Produk</a></div>
<?php endif; ?>
</div>
</div><?php render_revibe_floating_nav($conn); ?>
<script defer src="../assets/js/loader.js?v=25"></script>
</body></html>
