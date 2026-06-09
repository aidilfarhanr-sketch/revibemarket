<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';

$id = (int)($_GET['id'] ?? 0);

$sellerPhotoSelect = db_column_exists($conn, 'users', 'profile_photo') ? ", u.profile_photo AS seller_photo" : "";
$pq = mysqli_query($conn, "SELECT p.*, u.first_name, u.last_name, u.id AS seller_user_id, u.address AS seller_address $sellerPhotoSelect FROM products p LEFT JOIN users u ON p.user_id=u.id WHERE p.id=$id LIMIT 1");
$p = $pq ? mysqli_fetch_assoc($pq) : null;
if (!$p) {
    http_response_code(404);
    die('<h2 style="text-align:center;margin-top:80px;color:#2F5D50;">Produk tidak ditemukan.</h2>');
}

$images = mysqli_query($conn, "SELECT * FROM product_images WHERE product_id=$id");
$all_images = [];
while($img = mysqli_fetch_assoc($images)){
    $all_images[] = $img['image'];
}

$reviews = db_table_exists($conn, 'reviews') ? mysqli_query($conn, "SELECT r.*, u.first_name, u.last_name FROM reviews r LEFT JOIN users u ON r.user_id=u.id WHERE r.product_id=$id ORDER BY r.id DESC LIMIT 20") : null;
$reviewSummary = revibe_rating_summary($conn, $id, $p['rating'] ?? 0);
$reviewCount = (int)$reviewSummary['count'];
if ($reviewCount === 0) { $reviewSummary['avg'] = 0; }
$sellerId = (int)($p['seller_user_id'] ?? $p['user_id'] ?? 0);
$isOwnProduct = is_logged_in() && ((int)$_SESSION['user_id'] === $sellerId);
?>

<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8">
    <title><?= e($p['name']) ?> - ReVibe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../assets/css/loader.css?v=25">
</head>
<body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>

<div class="navbar">
    <a href="../index.php" class="btn">← Kembali</a>
    <?php if(is_logged_in()): ?>
        <a href="cart.php" class="btn">Keranjang</a>
        <a href="buyer_orders.php" class="btn">Pesanan</a>
    <?php endif; ?>
</div>

<?php if(isset($_SESSION['error'])): ?><div class="rv-toast error"><?= e($_SESSION['error']); unset($_SESSION['error']); ?><button onclick="this.parentElement.remove()">✕</button></div><?php endif; ?>
<?php if(isset($_SESSION['success'])): ?><div class="rv-toast success"><?= e($_SESSION['success']); unset($_SESSION['success']); ?><button onclick="this.parentElement.remove()">✕</button></div><?php endif; ?>

<div class="detail-container modern-detail">
    <div class="detail-images product-gallery-v9">
        <div class="gallery-main-wrap">
            <button type="button" class="gallery-nav prev" aria-label="Foto sebelumnya">‹</button>
            <img src="<?= e(revibe_public_file_url($all_images[0] ?? 'default.png', 'products')) ?>" class="main-img" id="mainProductImg" alt="<?= e($p['name']) ?>" width="720" height="520" fetchpriority="high" decoding="async">
            <button type="button" class="gallery-nav next" aria-label="Foto berikutnya">›</button>
            <span class="gallery-count"><b id="galleryIndex">1</b>/<?= max(1, count($all_images)) ?></span>
        </div>
        <div class="thumbs swipe-thumbs" id="galleryThumbs">
            <?php foreach($all_images as $idx => $img){ ?>
                <img src="<?= e(revibe_public_file_url($img, 'products')) ?>" class="thumb <?= $idx===0?'active':'' ?>" data-index="<?= (int)$idx ?>" alt="Foto produk <?= (int)$idx+1 ?>" width="78" height="78" loading="lazy" decoding="async">
            <?php } ?>
        </div>
        <?php if(!empty($p['minus_photo'])): ?>
            <div class="minus-proof">
                <strong>Foto minus/cacat:</strong><br>
                <img src="<?= e(revibe_public_file_url($p['minus_photo'], 'products')) ?>" alt="Foto minus">
            </div>
        <?php endif; ?>
    </div>

    <div class="detail-info product-detail-panel">
        <div class="badge-wrap detail-badges">
            <?php foreach(product_badges($p) as $badge): ?><span class="mini-badge"><?= e($badge) ?></span><?php endforeach; ?>
        </div>

        <h2><?= e($p['name']) ?></h2>
        <p class="price">Rp <?= number_format($p['price']) ?></p>

        <div class="meta product-rating-line">
            ⭐ <?= e(number_format((float)$reviewSummary['avg'],1)) ?> • <?= $reviewCount > 0 ? $reviewCount.' ulasan' : 'Belum ada ulasan asli' ?> • <?= e($p['sold'] ?? 0) ?> terjual • Stok <?= e($p['stock']) ?>
        </div>

        <div class="detail-specs">
            <div><strong>Kondisi</strong><span><?= e($p['condition_status'] ?? 'Belum diisi') ?></span></div>
            <div><strong>Lokasi</strong><span><?= e($p['location'] ?? '-') ?></span></div>
            <div><strong>Tahun Beli</strong><span><?= e($p['purchase_year'] ?? '-') ?></span></div>
            <div><strong>Kelengkapan</strong><span><?= e($p['completeness'] ?? '-') ?></span></div>
            <div><strong>Metode</strong><span><?= e(($p['shipping_option'] ?? 'shipping') === 'both' ? 'COD & Kirim' : (($p['shipping_option'] ?? '') === 'cod' ? 'COD' : 'Kirim')) ?></span></div>
            <div><strong>Seller</strong><span><?= e(trim(($p['first_name'] ?? 'Seller') . ' ' . ($p['last_name'] ?? ''))) ?></span></div>
        </div>

        <div class="seller-trust-card detail-seller-card">
            <?php if(!empty($p['seller_photo'])): ?>
                <img class="seller-avatar-img" src="<?= e(revibe_public_file_url($p['seller_photo'], 'profile')) ?>" alt="Foto Penjual">
            <?php else: ?>
                <div class="avatar-letter"><?= e(strtoupper(substr($p['first_name'] ?? 'S',0,1))) ?></div>
            <?php endif; ?>
            <div class="seller-card-text"><strong><?= e(trim(($p['first_name'] ?? 'Seller') . ' ' . ($p['last_name'] ?? ''))) ?></strong><p>Penjual ReVibe • <?= e($p['seller_address'] ?? $p['location'] ?? 'Lokasi belum diisi') ?></p></div>
            <?php if($sellerId > 0 && !$isOwnProduct): ?>
                <?php if(is_logged_in()): ?>
                    <a href="start_chat.php?product_id=<?= (int)$p['id'] ?>" class="btn chat-seller-mini">Chat Penjual</a>
                <?php else: ?>
                    <a href="../index.php?open_login=1&redirect=<?= urlencode('pages/detail.php?id='.(int)$p['id']) ?>" class="btn chat-seller-mini">Login untuk Chat</a>
                <?php endif; ?>
            <?php elseif($isOwnProduct): ?>
                <span class="detail-chat-alert">Ini produk kamu sendiri</span>
            <?php else: ?>
                <span class="detail-chat-alert">Penjual belum terhubung ke akun</span>
            <?php endif; ?>
        </div>

        <p class="desc product-description"><?= nl2br(e($p['description'])) ?></p>
        <?php if(!empty($p['reason_sell'])): ?>
            <p class="desc"><strong>Alasan dijual:</strong><br><?= nl2br(e($p['reason_sell'])) ?></p>
        <?php endif; ?>

        <?php if($isOwnProduct): ?>
            <div class="owner-product-notice">
                <strong>Ini produk kamu sendiri</strong>
                <p>Produk milik sendiri tidak bisa dibeli atau dimasukkan ke keranjang untuk menghindari kecurangan transaksi.</p>
                <a href="edit_product.php?id=<?= (int)$p['id'] ?>" class="btn primary">Edit Produk</a>
            </div>
        <?php else: ?>
            <div class="detail-action modern-detail-actions">
                <form method="POST" action="add_to_cart.php" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                    <input type="number" name="qty" value="1" min="1" max="<?= (int)$p['stock'] ?>" class="qty-input">
                    <button type="submit" class="btn nav-icon-btn">🛒 Keranjang</button>
                </form>
                <form method="POST" action="checkout.php" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="buy_now_product_id" value="<?= (int)$p['id'] ?>">
                    <input type="hidden" name="qty" value="1">
                    <button type="submit" class="btn primary">Beli Sekarang</button>
                </form>
                <form method="POST" action="toggle_wishlist.php" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="btn secondary">♡ Wishlist</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if($sellerId > 0 && !$isOwnProduct): ?>
            <?php if(is_logged_in()): ?>
                <a href="start_chat.php?product_id=<?= (int)$p['id'] ?>" class="btn full chat-btn">💬 Chat Penjual / Nego Harga</a>
            <?php else: ?>
                <a href="../index.php?open_login=1&redirect=<?= urlencode('pages/detail.php?id='.(int)$p['id']) ?>" class="btn full chat-btn">💬 Login untuk Chat Penjual</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="detail-extra">
    <section class="info-panel">
        <h3>Trust & Safety</h3>
        <p>Produk preloved wajib jujur soal kondisi, minus, lokasi, dan kelengkapan. Jika barang tidak sesuai, pembeli dapat membuat komplain dari halaman pesanan.</p>
    </section>

    <section class="info-panel review-panel-shopee" id="reviews">
        <div class="panel-title"><h3>Ulasan Produk</h3><span><?= e(number_format((float)$reviewSummary['avg'],1)) ?>/5 • <?= $reviewCount ?> ulasan asli</span></div>
        <div class="review-summary-box">
            <div class="review-score"><strong><?= e(number_format((float)$reviewSummary['avg'],1)) ?></strong><span>★★★★★</span><small><?= $reviewCount > 0 ? $reviewCount.' ulasan pembeli' : 'Belum ada ulasan pembeli asli.' ?></small></div>
            <div class="review-bars">
                <?php for($star=5;$star>=1;$star--): $cnt=(int)($reviewSummary['dist'][$star] ?? 0); $pct=$reviewCount?round(($cnt/$reviewCount)*100):0; ?>
                <div><span><?= $star ?>★</span><b><i style="width:<?= $pct ?>%"></i></b><small><?= $cnt ?></small></div>
                <?php endfor; ?>
            </div>
        </div>
        <?php if($reviews && mysqli_num_rows($reviews) > 0): ?>
            <?php while($r = mysqli_fetch_assoc($reviews)): ?>
                <div class="review-item shopee-review-item">
                    <div class="avatar-letter small"><?= e(strtoupper(substr($r['first_name'] ?? 'R',0,1))) ?></div>
                    <div><strong><?= e(trim(($r['first_name'] ?? 'Pembeli') . ' ' . ($r['last_name'] ?? ''))) ?></strong><span class="review-stars"><?= str_repeat('★', (int)$r['rating']) ?></span><p><?= nl2br(e($r['comment'])) ?></p><small><?= e(date('d M Y', strtotime($r['created_at'] ?? 'now'))) ?></small></div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">Belum ada ulasan pembeli asli.</div>
        <?php endif; ?>
    </section>
</div>

<script>
document.addEventListener("DOMContentLoaded", function(){
    const thumbs = Array.from(document.querySelectorAll(".thumb"));
    const main = document.getElementById('mainProductImg') || document.querySelector('.main-img');
    const indexText = document.getElementById('galleryIndex');
    let current = 0;
    function showImage(i){
        if(!thumbs.length || !main) return;
        current = (i + thumbs.length) % thumbs.length;
        main.src = thumbs[current].src;
        thumbs.forEach(t => t.classList.remove('active'));
        thumbs[current].classList.add('active');
        if(indexText) indexText.textContent = current + 1;
        thumbs[current].scrollIntoView({behavior:'smooth', inline:'center', block:'nearest'});
    }
    thumbs.forEach((img, i) => img.addEventListener("click", () => showImage(i)));
    document.querySelector('.gallery-nav.prev')?.addEventListener('click', () => showImage(current - 1));
    document.querySelector('.gallery-nav.next')?.addEventListener('click', () => showImage(current + 1));
    let startX = null;
    main?.addEventListener('touchstart', e => startX = e.touches[0].clientX, {passive:true});
    main?.addEventListener('touchend', e => {
        if(startX === null) return;
        const diff = e.changedTouches[0].clientX - startX;
        if(Math.abs(diff) > 40) showImage(current + (diff < 0 ? 1 : -1));
        startX = null;
    }, {passive:true});
});
</script>

<div class="footer">
    <div class="footer-content">
        <div class="footer-left">
            <img src="../assets/images/ReVibe.png" class="footer-logo" width="146" height="60" alt="ReVibe Market">
            <p class="region">Indonesia | Rp</p>
            <p class="desc">
                Konsep bisnis RV menawarkan fashion dan barang bekas berkualitas
                dengan harga terbaik dan cara yang berkelanjutan.
                RV sejak didirikan pada tahun 2026 tumbuh menjadi salah satu
                platform barang bekas terpercaya.
            </p>
            <p class="copyright">© AIDIL FARHAN RARES</p>
            <p class="contact"><?= e(revibe_env('REVIBE_CONTACT_WHATSAPP', '08xxxxxxxxxx')) ?> (WhatsApp Demo)</p>
        </div>
    </div>
</div>
<?php render_revibe_floating_nav($conn); ?>
<script defer src="../assets/js/loader.js?v=25"></script>
</body>
</html>
