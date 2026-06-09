<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
$user_id = (int)$_SESSION['user_id'];

$q = mysqli_query($conn, "SELECT c.id AS cart_id, c.qty, p.*, u.first_name, u.last_name FROM cart c JOIN products p ON c.product_id=p.id LEFT JOIN users u ON p.user_id=u.id WHERE c.user_id=$user_id ORDER BY c.id DESC");
$total = 0;
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang - ReVibe Market</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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

<div class="navbar"><a href="../index.php" class="btn">← Lanjut Belanja</a><a href="buyer_orders.php" class="btn">Pesanan Saya</a></div>
<?php if(isset($_SESSION['error'])): ?><div class="rv-toast error"><?= e($_SESSION['error']); unset($_SESSION['error']); ?><button onclick="this.parentElement.remove()">✕</button></div><?php endif; ?>
<?php if(isset($_SESSION['success'])): ?><div class="rv-toast success"><?= e($_SESSION['success']); unset($_SESSION['success']); ?><button onclick="this.parentElement.remove()">✕</button></div><?php endif; ?>

<div class="page-shell">
    <div class="page-header"><h1>Keranjang Belanja</h1><p>Checkout produk preloved pilihanmu.</p></div>

    <?php if(!$q || mysqli_num_rows($q) === 0): ?>
        <div class="empty-state"><h3>Keranjang masih kosong</h3><p>Yuk cari barang preloved berkualitas di ReVibe Market.</p><a href="../index.php" class="btn primary">Belanja Sekarang</a></div>
    <?php else: ?>
        <div class="cart-layout">
            <div class="cart-list">
                <?php while($item = mysqli_fetch_assoc($q)):
                    $imgq = mysqli_query($conn, "SELECT image FROM product_images WHERE product_id=".(int)$item['id']." LIMIT 1");
                    $img = mysqli_fetch_assoc($imgq);
                    $subtotal = (int)$item['price'] * (int)$item['qty'];
                    $total += $subtotal;
                ?>
                    <div class="cart-item">
                        <img src="<?= e(revibe_public_file_url($img['image'] ?? 'default.png', 'products')) ?>" alt="<?= e($item['name']) ?>" loading="lazy" decoding="async">
                        <div class="cart-info">
                            <h3><?= e($item['name']) ?></h3>
                            <p><?= e($item['condition_status'] ?? 'Preloved') ?> • Seller: <?= e(trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''))) ?></p>
                            <strong><?= money($item['price']) ?></strong>
                        </div>
                        <form method="POST" action="update_cart.php" class="cart-qty-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="cart_id" value="<?= (int)$item['cart_id'] ?>">
                            <input type="number" name="qty" min="1" max="<?= (int)$item['stock'] ?>" value="<?= (int)$item['qty'] ?>">
                            <button class="btn" type="submit">Update</button>
                        </form>
                        <div class="cart-subtotal"><?= money($subtotal) ?></div>
                        <form method="POST" action="remove_cart.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="cart_id" value="<?= (int)$item['cart_id'] ?>">
                            <button class="btn danger" type="submit">Hapus</button>
                        </form>
                    </div>
                <?php endwhile; ?>
            </div>
            <div class="summary-card">
                <h3>Ringkasan</h3>
                <div class="summary-line"><span>Subtotal</span><strong><?= money($total) ?></strong></div>
                <div class="summary-line"><span>Estimasi Cashback Seller</span><strong><?= e(revibe_seller_cashback_percent()) ?>%</strong></div>
                <a href="checkout.php" class="btn primary full">Checkout</a>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php render_revibe_floating_nav($conn); ?>
<script defer src="../assets/js/loader.js?v=25"></script>
</body>
</html>
