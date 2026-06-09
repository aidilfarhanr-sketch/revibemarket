<?php
require_once __DIR__ . '/config/session.php';
include 'config/db.php';
require_once 'config/functions.php';

if (!$conn) {
    die("<h2 style='color:red;text-align:center;margin-top:50px;'>Koneksi database gagal. Periksa config/db.php</h2>");
}

$nav_user = null;
if (isset($_SESSION['user_id'])) {
    $nav_user = current_user($conn);
}
$nav_photo = '';
if ($nav_user && !empty($nav_user['profile_photo'])) {
    $nav_photo = '' . revibe_public_file_url($nav_user['profile_photo'], 'profile');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ReVibe Market adalah marketplace barang preloved berkualitas, eco-friendly, dan aman untuk jual-beli produk bekas dengan sistem wallet, chat, rating, serta seller center.">
    <meta name="theme-color" content="#A7C4BC">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="ReVibe Market - Marketplace Preloved Berkelanjutan">
    <meta property="og:description" content="Jual beli barang preloved berkualitas dengan konsep daur ulang, aman, dan modern.">
    <meta property="og:image" content="<?= e(revibe_asset_url('assets/images/hero-poster.webp')) ?>">
    <link rel="canonical" href="<?= e(revibe_app_url('index.php')) ?>">
    <base href="<?= e(rtrim(revibe_app_url(), '/')) ?>/">
    <link rel="preload" as="image" href="<?= e(revibe_asset_url('assets/images/hero-poster.webp')) ?>" fetchpriority="high">
    <title>ReVibe Market - Marketplace Preloved Berkelanjutan</title>
    <link rel="stylesheet" href="<?= e(revibe_asset_url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(revibe_asset_url('assets/css/loader.css')) ?>">
</head>
<body>
<?php include __DIR__ . '/includes/loader.php'; ?>

<a class="skip-link" href="#produk">Lewati ke daftar produk</a>

<div class="navbar main-navbar">

    <div class="menu-left">
    <a href="index.php" class="btn top-home-nav"><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
  <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
</svg></a>

     <div class="dropdown category-mega">
        <button class="btn nav-icon-btn" type="button" aria-label="Kategori dan filter produk">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg>
            Kategori
        </button>
        <div class="dropdown-content category-panel">
            <div class="category-quick">
                <a href="?kategori=pakaian">Pakaian</a>
                <a href="?kategori=aksesoris">Aksesoris</a>
                <a href="?kategori=pajangan">Pajangan</a>
                <a href="?kategori=tanaman">Tanaman</a>
            </div>

            <form method="GET" class="market-filter-form nav-filter-form">
                <input type="text" name="search" aria-label="Cari produk preloved" placeholder="Cari produk preloved..." value="<?= e($_GET['search'] ?? '') ?>">
                <select name="kategori" aria-label="Pilih kategori produk">
                    <option value="">Semua Kategori</option>
                    <?php foreach(['pakaian'=>'Pakaian','aksesoris'=>'Aksesoris','pajangan'=>'Pajangan','tanaman'=>'Tanaman'] as $val=>$label): ?>
                        <option value="<?= $val ?>" <?= (($_GET['kategori'] ?? '') === $val) ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="condition" aria-label="Pilih kondisi produk">
                    <option value="">Semua Kondisi</option>
                    <?php foreach(['Baru','Like New','Sangat Baik','Baik','Ada Minus Ringan','Perlu Perbaikan'] as $cond): ?>
                        <option value="<?= e($cond) ?>" <?= (($_GET['condition'] ?? '') === $cond) ? 'selected' : '' ?>><?= e($cond) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="filter-price-row">
                    <input type="number" name="min_price" min="0" aria-label="Harga minimum" placeholder="Harga min" value="<?= e($_GET['min_price'] ?? '') ?>">
                    <input type="number" name="max_price" min="0" aria-label="Harga maksimum" placeholder="Harga max" value="<?= e($_GET['max_price'] ?? '') ?>">
                </div>
                <input type="text" name="lokasi" aria-label="Lokasi produk" placeholder="Lokasi" value="<?= e($_GET['lokasi'] ?? '') ?>">
                <div class="filter-actions">
                    <button class="btn primary" type="submit">Filter</button>
                    <a href="index.php" class="btn secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <a href="pages/sell.php" class="btn" onclick="return handleSell(event)">Jual Barang</a>
</div>

    <div class="navbar-logo-center">
        <a href="index.php" aria-label="Beranda ReVibe Market">
            <img src="assets/images/logorv.png" class="logo" width="65" height="65" alt="Logo ReVibe Market">
        </a>
    </div>

<div class="menu-right">

    <?php if(isset($_SESSION['user_id'])): ?>
        <?php $unread_chat = get_unread_chat_count($conn, (int)$_SESSION['user_id']); ?>
        <a href="pages/buyer_orders.php" class="btn nav-icon-btn" title="Pesanan Saya">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h12v20l-3-2-3 2-3-2-3 2V2z"/><path d="M9 7h6"/><path d="M9 11h6"/></svg>
            <span>Pesanan</span>
        </a>
        <a href="pages/seller_center.php" class="btn nav-icon-btn" title="Seller Center">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1.5-5h15L21 9"/><path d="M4 9h16v11H4z"/><path d="M9 20v-6h6v6"/><path d="M3 9c1 2 4 2 5 0 1 2 4 2 5 0 1 2 4 2 5 0 1 2 4 2 5 0"/></svg>
            <span>Seller</span>
        </a>
        <a href="pages/messages.php" class="btn nav-icon-btn chat-nav top-chat-nav" title="Chat ReVibe">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/><path d="M8 9h8"/><path d="M8 13h5"/></svg>
            <span>Chat</span>
            <?php if($unread_chat > 0): ?><span class="cart-count"><?= $unread_chat ?></span><?php endif; ?>
        </a>
        <a href="pages/rankings.php" class="btn nav-icon-btn top-rank-nav" title="Peringkat Seller">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4h10v4a5 5 0 0 1-10 0V4z"/><path d="M5 5H3v2a4 4 0 0 0 4 4"/><path d="M19 5h2v2a4 4 0 0 1-4 4"/></svg>
            <span>Rank</span>
        </a>
        <?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
            <a href="pages/admin/index.php" class="btn nav-icon-btn" title="Admin Panel">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
                <span>Admin</span>
            </a>
        <?php endif; ?>
    <?php endif; ?>

    <form method="GET" class="nav-search-form">
        <input type="text" name="search" aria-label="Cari barang" placeholder="Cari barang..." class="search"
        value="<?= e($_GET['search'] ?? '') ?>">

        <?php if(isset($_GET['kategori'])){ ?>
            <input type="hidden" name="kategori" value="<?= e($_GET['kategori'] ?? '') ?>">
        <?php } ?>

    </form>

    <?php
    $cart_count = 0;
    if (isset($_SESSION['user_id'])) {
        $uid_cart = (int)$_SESSION['user_id'];
        $cart_q = mysqli_query($conn, "SELECT COALESCE(SUM(qty),0) AS total FROM cart WHERE user_id=$uid_cart");
        $cart_count = (int)(mysqli_fetch_assoc($cart_q)['total'] ?? 0);
    }
    ?>

    <a href="pages/cart.php" class="btn cart-nav" title="Keranjang Belanja" aria-label="Keranjang belanja">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 6h15l-1.5 9h-13l-1.5-11H2"></path>
            <circle cx="9" cy="20" r="1"></circle>
            <circle cx="18" cy="20" r="1"></circle>
            <path d="M15 6V3m-3 3l3-3 3 3"></path>
        </svg>
        <?php if($cart_count > 0): ?><span class="cart-count"><?= $cart_count ?></span><?php endif; ?>
    </a>

    <?php if(isset($_SESSION['user_id'])): ?>
            <div class="profile-btn profile-icon-modern nav-avatar-btn" role="button" tabindex="0" aria-label="Buka profil saya" onclick="openProfilePopup()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openProfilePopup()}" title="Profile Saya" style="cursor:pointer;">
                <?php if(!empty($nav_photo)): ?>
                    <img src="<?= e($nav_photo) ?>" alt="Foto Profil">
                <?php else: ?>
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
                <?php endif; ?>
            </div>
            <a href="pages/logout.php" class="btn">Logout</a>
        <?php else: ?>
            <div class="profile-btn profile-icon-modern" role="button" tabindex="0" aria-label="Masuk atau daftar" onclick="openLogin()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openLogin()}" title="Masuk / Daftar">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
        <?php endif; ?>

</div>

</div>

<?php render_revibe_floating_nav($conn); ?>

<main class="main-content">
<?php if (isset($_SESSION['error'])): ?>
<div class="rv-toast error">
    <?= e($_SESSION['error']) ?>
    <button type="button" aria-label="Tutup notifikasi" onclick="this.parentElement.remove()">✕</button>
</div>
<?php unset($_SESSION['error']); endif; ?>

<?php if (isset($_SESSION['success'])): ?>
<div id="successPopup" class="success-popup-overlay">
    <div class="success-popup">
        <button type="button" class="success-close-btn" aria-label="Tutup popup sukses" onclick="closeSuccessPopup()">✕</button>

        <div class="success-icon">🎉🌿</div>

        <h2 id="welcomeTitle">Selamat Datang!</h2>

        <p class="success-subtitle"><?= htmlspecialchars($_SESSION['success']) ?></p>

        <div class="success-greeting">
            Halo, <strong id="userName"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Sahabat ReVibe') ?></strong> 👋
        </div>

        <button onclick="closeSuccessPopup()" class="btn primary full success-btn">
            Lanjut Belanja ♻️
        </button>

        <p class="success-footer">Terima kasih telah mendukung fashion berkelanjutan</p>
    </div>
</div>

<script>

function animateWelcome() {
    const title = document.getElementById('welcomeTitle');
    const name = document.getElementById('userName');

    title.style.opacity = '0';
    title.style.transform = 'translateY(-20px)';

    setTimeout(() => {
        title.style.transition = 'all 0.8s ease';
        title.style.opacity = '1';
        title.style.transform = 'translateY(0)';
    }, 300);

    name.style.opacity = '0';
    setTimeout(() => {
        name.style.transition = 'all 1s ease';
        name.style.opacity = '1';
    }, 800);
}

function closeSuccessPopup() {
    const popup = document.getElementById('successPopup');
    if (popup) {
        popup.style.transition = 'opacity 0.5s ease';
        popup.style.opacity = '0';
        setTimeout(() => popup.remove(), 500);
    }
}

setTimeout(animateWelcome, 100);

setTimeout(closeSuccessPopup, 7000);
</script>

<?php unset($_SESSION['success']); endif; ?>

    <div class="hero-banner">
        <img class="hero-poster" src="assets/images/hero-poster.webp" width="1280" height="720" alt="Tekstur kain preloved ReVibe Market" fetchpriority="high" decoding="async">
        <video class="hero-video" muted loop playsinline preload="none" aria-hidden="true" poster="assets/images/hero-poster.webp" data-src="assets/videos/hero-recycle-lite.mp4"></video>

        <div class="hero-overlay"></div>

        <div class="banner-content">
            <p class="banner-tagline">Fashion Bekas • Daur Ulang • Berkelanjutan</p>
            <h1 class="banner-title">ReVibe Market</h1>
            <p class="banner-subtitle">
                Barang preloved berkualitas dengan cerita baru
            </p>
        </div>
    </div>

    <div class="product-container" id="produk" aria-label="Daftar produk ReVibe Market">

    <?php
    $kategori = $_GET['kategori'] ?? '';
    $search = $_GET['search'] ?? '';
    $condition = $_GET['condition'] ?? '';
    $lokasi = $_GET['lokasi'] ?? '';
    $min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (int)$_GET['min_price'] : 0;
    $max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (int)$_GET['max_price'] : 0;

    $sql = "SELECT * FROM products WHERE 1=1";

    if (db_column_exists($conn, 'products', 'product_status')) {
        $sql .= " AND product_status='approved'";
    }

    if(!empty($kategori)){
        $kategori_safe = mysqli_real_escape_string($conn, $kategori);
        $sql .= " AND category='$kategori_safe'";
    }

    if(!empty($search)){
        $search_safe = mysqli_real_escape_string($conn, $search);
        $sql .= " AND (name LIKE '%$search_safe%' OR description LIKE '%$search_safe%')";
    }

    if(!empty($condition) && db_column_exists($conn, 'products', 'condition_status')){
        $condition_safe = mysqli_real_escape_string($conn, $condition);
        $sql .= " AND condition_status='$condition_safe'";
    }

    if(!empty($lokasi)){
        $lokasi_safe = mysqli_real_escape_string($conn, $lokasi);
        $sql .= " AND location LIKE '%$lokasi_safe%'";
    }

    if($min_price > 0){
        $sql .= " AND price >= $min_price";
    }
    if($max_price > 0){
        $sql .= " AND price <= $max_price";
    }

    $sql .= " ORDER BY id DESC LIMIT 24";

    $q = mysqli_query($conn, $sql);

    if (!$q) {
        revibe_log('error', 'Query produk gagal', ['error' => mysqli_error($conn)]);
        echo "<p class='empty'>Produk belum bisa dimuat. Silakan coba lagi.</p>";
    }
    elseif (mysqli_num_rows($q) == 0) {
        $has_filter = !empty($kategori) || !empty($search) || !empty($condition) || !empty($lokasi) || $min_price > 0 || $max_price > 0;
        if ($has_filter) {
            echo "<div class='empty empty-products'><strong>Produk tidak ditemukan</strong><span>Coba reset filter atau cari dengan kata kunci lain.</span></div>";
        } else {
            echo "<div class='empty empty-products'><strong>Produk masih kosong</strong><span>Belum ada produk yang ditampilkan.</span></div>";
        }
    } else {
        while($p = mysqli_fetch_assoc($q)){
            $img_query = mysqli_query($conn, "SELECT image FROM product_images WHERE product_id=" . (int)$p['id'] . " LIMIT 1");
            $i = mysqli_fetch_assoc($img_query);
    ?>

        <a href="pages/detail.php?id=<?= $p['id'] ?>" class="card">
            <img src="<?= e(revibe_public_file_url($i['image'] ?? 'default.png', 'products')) ?>" alt="<?= e($p['name']) ?>" width="265" height="265" loading="lazy" decoding="async">

            <div class="card-content">
                <h4><?= htmlspecialchars($p['name']) ?></h4>
                <p class="price">Rp <?= number_format($p['price']) ?></p>
                <?php if(!empty($p['condition_status']) || !empty($p['shipping_option']) || !empty($p['badges'])): ?>
                <div class="badge-wrap">
                    <?php if(!empty($p['condition_status'])): ?><span class="mini-badge condition"><?= e($p['condition_status']) ?></span><?php endif; ?>
                    <?php foreach(product_badges($p) as $badge): ?><span class="mini-badge"><?= e($badge) ?></span><?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="card-meta">
                    <span class="rating">⭐ <?= $p['rating'] ?? '4.8' ?></span>
                    <span class="sold">Terjual <?= $p['sold'] ?? rand(20, 300) ?>+</span>
                </div>

                <div class="stock-info">
                    Tersisa: <strong><?= $p['stock'] ?? 0 ?></strong> pcs
                </div>

                <div class="seller-location">
                    📍 <?= !empty($p['location']) ? htmlspecialchars($p['location']) : '<span style="color:#999;">Lokasi tidak diisi</span>' ?>
                </div>
            </div>
        </a>

    <?php
        }
    }
    ?>

    </div>

</main>

<script>

(function(){
  const video = document.querySelector('.hero-video[data-src]');
  if(!video) return;
  if(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  let started = false;
  function startHeroVideo(){
    if(started) return; started = true;
    const src = video.getAttribute('data-src');
    if(!src) return;
    const source = document.createElement('source');
    source.src = src; source.type = 'video/mp4';
    video.appendChild(source);
    video.load();
    video.addEventListener('loadeddata', function(){ video.classList.add('is-ready'); video.play().catch(()=>{}); }, {once:true});
  }
  ['pointermove','touchstart','scroll','keydown'].forEach(evt => window.addEventListener(evt, startHeroVideo, {once:true, passive:true}));
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const categoryDropdown = document.querySelector('.category-mega');
    if(!categoryDropdown) return;
    const categoryButton = categoryDropdown.querySelector('button');
    if(categoryButton){
        categoryButton.addEventListener('click', function(e){
            e.preventDefault();
            categoryDropdown.classList.toggle('open');
        });
    }
    document.addEventListener('click', function(e){
        if(!categoryDropdown.contains(e.target)) categoryDropdown.classList.remove('open');
    });
});
</script>
<script>

document.addEventListener('DOMContentLoaded', function(){
    const params = new URLSearchParams(window.location.search);
    if(params.get('open_login') === '1' && typeof openLogin === 'function'){
        openLogin();
    }
});
</script>

<div class="footer">
    <div class="footer-content">

        <div class="footer-left">
            <img src="assets/images/ReVibe.png" class="footer-logo" width="146" height="60" alt="ReVibe Market">
            <p class="region">Indonesia | Rp</p>
            <p class="desc">
                Konsep bisnis RV menawarkan fashion dan barang bekas berkualitas
                dengan harga terbaik dan cara yang berkelanjutan.
                RV sejak didirikan pada tahun 2026 tumbuh menjadi salah satu
                platform barang bekas terpercaya.
            </p>
            <p class="copyright">© AIDIL FARHAN RARES</p>
            <p class="contact">
                Layanan Demo: <?= e(revibe_env('REVIBE_CONTACT_WHATSAPP', '08xxxxxxxxxx')) ?> (WhatsApp Demo)
            </p>
        </div>
    </div>
</div>

<div class="profile-popup" id="profilePopup">
    <div class="profile-box">
        <button type="button" class="close-btn" aria-label="Tutup profil" onclick="closeProfilePopup()">✕</button>

        <div class="profile-header">
            <div class="profile-avatar">
                <?php if(!empty($nav_photo)): ?>
                    <img src="<?= e($nav_photo) ?>" alt="Foto Profil">
                <?php else: ?>
                    <div class="avatar-letter profile-popup-letter"><?= e(strtoupper(substr($_SESSION['user_name'] ?? 'R', 0, 1))) ?></div>
                <?php endif; ?>
            </div>
            <h3><?= htmlspecialchars($_SESSION['user_name'] ?? 'Sahabat ReVibe') ?></h3>
            <p class="profile-bio">Ubah limbah jadi cerita baru ♻️</p>

            <?php if(isset($_SESSION['user_id'])):
                $uid = (int)$_SESSION['user_id'];
                $user_q = mysqli_query($conn, "SELECT address FROM users WHERE id = $uid");
                $user = mysqli_fetch_assoc($user_q);
                $location = !empty($user['address']) ? htmlspecialchars($user['address']) : 'Jakarta, Indonesia';
            ?>
                <p class="profile-location">📍 <?= $location ?></p>
            <?php endif; ?>

            <?php $popupSold = isset($_SESSION['user_id']) ? get_total_sold_by_user($conn, (int)$_SESSION['user_id']) : 0; ?>
            <div class="badge-level">🌱 <?= e(seller_rank_label($popupSold)) ?> • Member Jual-Beli</div>
            <?php if(isset($_SESSION['user_id'])): ?>
                <div class="quick-links">
                    <a href="pages/edit_profile.php">Edit Profil</a>
                    <a href="pages/buyer_orders.php">Pesanan</a>
                    <a href="pages/messages.php">Chat</a>
                    <a href="pages/wishlist.php">Wishlist</a>
                    <a href="pages/seller_center.php">Seller</a>
                    <a href="pages/rankings.php">Peringkat</a>
                    <?php if(($_SESSION['role'] ?? '') === 'admin'): ?><a href="pages/admin/index.php">Admin</a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if(isset($_SESSION['user_id'])):
            $uid = (int)$_SESSION['user_id'];
            $stats = mysqli_fetch_assoc(mysqli_query($conn, "
                SELECT COUNT(*) as total_products,
                       COALESCE(SUM(sold), 0) as total_sold,
                       COALESCE(SUM(price * sold), 0) as total_earned
                FROM products WHERE user_id = $uid
            "));
            $coins = get_coin_balance($conn, $uid);
            $roleLabel = ucfirst($_SESSION['role'] ?? 'user');
        ?>
        <div class="profile-tabs">
            <button class="tab-btn active" onclick="switchTab(0)">Wallet</button>
            <button class="tab-btn" onclick="switchTab(1)">Eco Impact</button>
            <button class="tab-btn" onclick="switchTab(2)">Produk</button>
        </div>

        <div class="tab-content" id="tab0">
            <div class="wallet-card">
                <h4>💰 ReVibe Wallet</h4>
                <div class="balance">
                    🪙 <strong><?= number_format($coins) ?></strong> Coin<br>
                    <span style="font-size:18px;color:#2F5D50;">Rp <?= number_format($coins) ?></span>
                </div>
                <a href="pages/withdraw.php" class="btn primary full">Tukar ke Rupiah</a>
            </div>
        </div>

        <div class="tab-content" id="tab1" style="display:none;">
            <h4>♻️ Eco Impact Kamu</h4>
            <div class="eco-grid">
                <div><strong><?= $stats['total_sold'] ?></strong><br><small>Terjual</small></div>
                <div><strong><?= $stats['total_products'] ?></strong><br><small>Produk</small></div>
                <div><strong>Rp <?= number_format($stats['total_earned']) ?></strong><br><small>Penjualan</small></div>
                <div><strong><?= number_format($coins) ?></strong><br><small>Coin (6%)</small></div>
            </div>
        </div>

        <div class="tab-content" id="tab2" style="display:none;">
            <a href="pages/profile.php" class="btn primary full">Lihat Semua Produk Saya →</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="login-popup" id="loginPopup">
    <div class="login-box" onclick="event.stopPropagation()">
        <button type="button" class="close-btn" aria-label="Tutup popup" onclick="closeAll()">✕</button>
        <h2>Keanggotaan ReVibe</h2>
        <p class="login-desc">
            Jadilah member — Dapatkan diskon 15% pembelian pertama
            dan simulasi cashback seller 6% setiap penjualan demo.
        </p>
        <button class="btn primary full" onclick="showLoginForm()">Masuk</button>
        <button class="btn secondary full" onclick="showRegisterForm()">Saya mau menjadi member</button>
        <p class="small-text">
            Dengan mendaftar, Anda menyetujui <a href="#">Syarat & Ketentuan</a>
        </p>
    </div>
</div>

<div class="login-form" id="loginForm">
    <div class="login-box plastic-theme" onclick="event.stopPropagation()">

        <button type="button" class="close-btn" aria-label="Tutup popup" onclick="closeAll()">✕</button>

<div class="plastic-header">
    <div class="turtle-container">
        <svg id="turtleSVG" class="turtle-svg" width="240" height="240" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">

            <ellipse cx="100" cy="118" rx="72" ry="58" fill="#4ade80" stroke="#1e3a2f" stroke-width="16"/>
            <g fill="#22c55e" opacity="0.9">
                <ellipse cx="70" cy="105" rx="20" ry="26"/>
                <ellipse cx="130" cy="105" rx="20" ry="26"/>
                <ellipse cx="100" cy="135" rx="26" ry="20"/>
                <ellipse cx="75" cy="128" rx="17" ry="20"/>
                <ellipse cx="125" cy="128" rx="17" ry="20"/>
            </g>
            <ellipse cx="100" cy="118" rx="72" ry="58" fill="none" stroke="#1e3a2f" stroke-width="9"/>

            <ellipse cx="100" cy="127" rx="50" ry="40" fill="#34d399" stroke="#1e3a2f" stroke-width="10"/>

            <ellipse cx="100" cy="68" rx="34" ry="31" fill="#34d399" stroke="#1e3a2f" stroke-width="11"/>

            <g class="turtle-eyes">
                <ellipse id="eyeL" cx="83" cy="65" rx="9.5" ry="13" fill="#1e3a2f"/>
                <ellipse id="eyeR" cx="117" cy="65" rx="9.5" ry="13" fill="#1e3a2f"/>
                <circle id="eyeHighlightL" cx="86" cy="62" r="3.5" fill="#fff"/>
                <circle id="eyeHighlightR" cx="120" cy="62" r="3.5" fill="#fff"/>
            </g>

            <path id="turtleMouth" d="M80 82 Q100 93 120 82" fill="none" stroke="#1e3a2f" stroke-width="4.5" stroke-linecap="round"/>

            <ellipse id="leftFin" cx="48" cy="125" rx="16" ry="26" fill="#34d399" stroke="#1e3a2f" stroke-width="8" transform="rotate(-45 48 125)"/>

            <ellipse id="rightFin" cx="152" cy="125" rx="16" ry="26" fill="#34d399" stroke="#1e3a2f" stroke-width="8" transform="rotate(45 152 125)"/>
        </svg>
    </div>

    <div class="plastic-particles">
        <span class="particle">♻️</span>
        <span class="particle">👕</span>
        <span class="particle">🛍️</span>
        <span class="particle">♻️</span>
        <span class="particle">👕</span>
    </div>

    <h3 class="plastic-title">Masuk ke ReVibe</h3>
    <p class="plastic-subtitle">Daur ulang akunmu, dapatkan diskon & cashback koin</p>
</div>
        <form action="pages/login_process.php" method="POST">
            <?= csrf_field() ?>
            <input type="email" name="email" aria-label="Email" autocomplete="email" placeholder="Email" required>

            <div class="password-box">
                <input type="password" name="password" id="password" aria-label="Password" autocomplete="current-password" placeholder="Password" required>
                <button type="button" onclick="togglePass()" class="toggle-pass" data-password-toggle="password" aria-label="Tampilkan password" aria-pressed="false" title="Tampilkan password">👁</button>
            </div>

            <div class="login-options">
                <label><input type="checkbox" name="remember"> Ingat saya</label>
                <a href="#" onclick="showForgot()">Lupa kata sandi?</a>
            </div>

            <button type="submit" class="btn primary full plastic-btn">Masuk</button>
        </form>

        <p class="switch-text">
            Belum punya akun?
            <a href="#" onclick="showRegisterForm()">Saya mau menjadi member</a>
        </p>

        <div id="loginSuccess" class="success-message" style="display: none;">
            <p>✅ Login Berhasil!</p>
            <p class="recycle-text">Limbah plastikmu sudah didaur ulang menjadi poin reward 🎉</p>
            <button onclick="closeAll()" class="btn secondary full">Kembali ke Beranda</button>
        </div>
    </div>
</div>

<div class="register-form" id="registerForm">
    <div class="login-box" onclick="event.stopPropagation()">
        <button type="button" class="close-btn" aria-label="Tutup popup" onclick="closeAll()">✕</button>
        <h3>Jadi Member ReVibe</h3>

        <form action="pages/register_process.php" method="POST">
            <?= csrf_field() ?>
            <input type="text" name="first_name" aria-label="Nama depan" autocomplete="given-name" placeholder="Nama Depan" required>
            <input type="text" name="last_name" aria-label="Nama belakang" autocomplete="family-name" placeholder="Nama Belakang" required>
            <input type="email" name="email" aria-label="Email" autocomplete="email" placeholder="Email" required>
            <input type="tel" name="phone" aria-label="Nomor HP atau WhatsApp" autocomplete="tel" placeholder="Nomor HP / WhatsApp" required>
            <input type="date" name="birthdate" aria-label="Tanggal lahir" autocomplete="bday" required>

            <div class="password-box">
                <input type="password" name="password" id="reg_password" aria-label="Password pendaftaran" autocomplete="new-password" placeholder="Password" required>
                <button type="button" onclick="toggleRegPass()" class="toggle-pass" data-password-toggle="reg_password" aria-label="Tampilkan password" aria-pressed="false" title="Tampilkan password">👁</button>
            </div>

            <label class="checkbox-label">
                <input type="checkbox" required> Saya setuju dengan syarat & ketentuan ReVibe
            </label>

            <button type="submit" class="btn primary full">Saya mau menjadi member</button>
        </form>

        <p class="switch-text">
            Sudah punya akun? <a href="#" onclick="showLoginForm()">Masuk</a>
        </p>
    </div>
</div>
<script>

function openProfilePopup() {
    const popup = document.getElementById("profilePopup");
    if (!popup) return;
    popup.style.display = "flex";
    popup.classList.add("show");
    document.body.classList.add("rv-modal-open");
}

function closeProfilePopup() {
    const popup = document.getElementById("profilePopup");
    if (!popup) return;
    popup.style.display = "none";
    popup.classList.remove("show");
    document.body.classList.remove("rv-modal-open");
}

function switchTab(n) {
    document.querySelectorAll('.tab-btn').forEach((el,i) => el.classList.toggle('active', i===n));
    document.querySelectorAll('.tab-content').forEach((el,i) => el.style.display = (i===n ? 'block' : 'none'));
}

document.addEventListener("keydown", function(e) {
    if (e.key === "Escape") {
        closeProfilePopup();
        closeAll();
    }
});
</script>

<script>
let isLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;

function openLogin() {
    closeProfilePopup();
    const popup = document.getElementById("loginPopup");
    if (!popup) return;
    popup.style.display = "flex";
    popup.classList.add("show");
    document.body.classList.add("rv-modal-open");
}

function closeAll() {
    ["loginPopup", "loginForm", "registerForm"].forEach(function(id){
        const el = document.getElementById(id);
        if (el) {
            el.style.display = "none";
            el.classList.remove("show");
        }
    });
    document.body.classList.remove("rv-modal-open");
}

function showLoginForm() {
    closeAll();
    const form = document.getElementById("loginForm");
    if (!form) return;
    form.style.display = "flex";
    form.classList.add("show");
    document.body.classList.add("rv-modal-open");
}

function showRegisterForm() {
    closeAll();
    const form = document.getElementById("registerForm");
    if (!form) return;
    form.style.display = "flex";
    form.classList.add("show");
    document.body.classList.add("rv-modal-open");
}

function showForgot() {
    window.location.href = "pages/forgot_password.php";
}

function updatePasswordToggle(toggle, isVisible) {
    if (!toggle) return;
    toggle.textContent = isVisible ? "👁" : "⌣";
    toggle.setAttribute("aria-pressed", isVisible ? "true" : "false");
    toggle.setAttribute("aria-label", isVisible ? "Sembunyikan password" : "Tampilkan password");
    toggle.setAttribute("title", isVisible ? "Sembunyikan password" : "Tampilkan password");
}

function togglePasswordField(inputId) {
    const pass = document.getElementById(inputId);
    if (!pass) return;
    const willShow = pass.type === "password";
    pass.type = willShow ? "text" : "password";
    updatePasswordToggle(document.querySelector('[data-password-toggle="' + inputId + '"]'), willShow);
}

function togglePass() {
    togglePasswordField("password");
}

function toggleRegPass() {
    togglePasswordField("reg_password");
}

function handleSell(event) {
    if (!isLoggedIn) {
        if(event) event.preventDefault();
        showLoginForm();
        return false;
    }
    return true;
}

document.addEventListener("keydown", function(e) {
    if (e.key === "Escape") closeAll();
});
</script>
<script>

document.addEventListener('DOMContentLoaded', function() {
    const turtleSVG = document.getElementById('turtleSVG');
    if (!turtleSVG) return;

    const eyeL = document.getElementById('eyeL');
    const eyeR = document.getElementById('eyeR');
    const mouth = document.getElementById('turtleMouth');
    const leftFin = document.getElementById('leftFin');
    const rightFin = document.getElementById('rightFin');

    function moveTurtleEyes(e) {
        if (!eyeL || !eyeR) return;

        const rect = turtleSVG.getBoundingClientRect();
        const centerX = rect.left + 100;
        const centerY = rect.top + 68;

        const angle = Math.atan2(e.clientY - centerY, e.clientX - centerX);

        const moveX = Math.cos(angle) * 7;
        const moveY = Math.sin(angle) * 6;

        eyeL.setAttribute('cx', 83 + moveX);
        eyeL.setAttribute('cy', 65 + moveY);
        eyeR.setAttribute('cx', 117 + moveX);
        eyeR.setAttribute('cy', 65 + moveY);

        document.getElementById('eyeHighlightL').setAttribute('cx', 86 + moveX * 0.6);
        document.getElementById('eyeHighlightR').setAttribute('cx', 120 + moveX * 0.6);
    }

    document.addEventListener('mousemove', moveTurtleEyes);

    function blinkTurtle() {
        if (!eyeL || !eyeR) return;
        const scale = 0.1;
        eyeL.setAttribute('ry', scale * 13);
        eyeR.setAttribute('ry', scale * 13);

        setTimeout(() => {
            eyeL.setAttribute('ry', 13);
            eyeR.setAttribute('ry', 13);
        }, 120);

        setTimeout(blinkTurtle, Math.random() * 4000 + 2500);
    }
    blinkTurtle();

    document.querySelectorAll('#loginForm input').forEach(input => {
        input.addEventListener('focus', () => {
            if (mouth) mouth.setAttribute('d', "M78 82 Q100 98 122 82");
            if (leftFin) leftFin.setAttribute('transform', 'rotate(-30 48 125)');
            if (rightFin) rightFin.setAttribute('transform', 'rotate(30 152 125)');
        });

        input.addEventListener('blur', () => {
            if (mouth) mouth.setAttribute('d', "M80 82 Q100 93 120 82");
            if (leftFin) leftFin.setAttribute('transform', 'rotate(-45 48 125)');
            if (rightFin) rightFin.setAttribute('transform', 'rotate(45 152 125)');
        });
    });
});
</script>
<script>

window.addEventListener('scroll', () => {
    const navbar = document.querySelector('.navbar');
    if (window.scrollY > 50) {
        navbar.classList.add('stuck');
    } else {
        navbar.classList.remove('stuck');
    }
});
function closeSuccessPopup() {
    const popup = document.getElementById('successPopup');
    if (popup) {
        popup.style.opacity = '0';
        setTimeout(() => {
            popup.remove();
        }, 400);
    }
}
</script>

<script>

document.addEventListener('DOMContentLoaded', function(){
    ['profilePopup','loginPopup','loginForm','registerForm'].forEach(function(id){
        const overlay = document.getElementById(id);
        if(!overlay) return;
        overlay.addEventListener('click', function(e){
            if(e.target !== overlay) return;
            if(id === 'profilePopup') closeProfilePopup();
            else closeAll();
        });
    });
});
</script>


<script>
(function(){
    function rvKeepFooterBottom(){
        const main = document.querySelector('.main-content');
        const nav = document.querySelector('.main-navbar');
        const footer = document.querySelector('.footer');
        if(!main || !footer) return;
        const navHeight = nav ? Math.ceil(nav.getBoundingClientRect().height) : 0;
        const footerHeight = Math.ceil(footer.getBoundingClientRect().height);
        const targetHeight = Math.max(0, window.innerHeight - navHeight - footerHeight);
        main.style.setProperty('--rv-main-min-height', targetHeight + 'px');
        main.style.minHeight = 'var(--rv-main-min-height)';
    }
    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', rvKeepFooterBottom);
    }else{
        rvKeepFooterBottom();
    }
    window.addEventListener('load', rvKeepFooterBottom);
    window.addEventListener('resize', rvKeepFooterBottom);
    window.addEventListener('orientationchange', function(){ setTimeout(rvKeepFooterBottom, 250); });
})();
</script>

<script defer src="assets/js/loader.js?v=25"></script>
</body>
</html>
