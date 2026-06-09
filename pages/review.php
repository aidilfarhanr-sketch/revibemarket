<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
$user_id=(int)$_SESSION['user_id'];
$order_id=(int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$q=mysqli_query($conn,"SELECT o.*, p.name AS product_name, p.condition_status, p.price, pi.image FROM orders o JOIN products p ON o.product_id=p.id LEFT JOIN product_images pi ON pi.product_id=p.id WHERE o.id=$order_id AND o.buyer_id=$user_id AND o.status='completed' GROUP BY o.id LIMIT 1");
$order=$q?mysqli_fetch_assoc($q):null;
if(!$order){ $_SESSION['error']='Order tidak ditemukan atau belum selesai.'; header('Location: buyer_orders.php'); exit; }
$existing=null;
if(db_table_exists($conn,'reviews')){
    $rq=mysqli_query($conn,"SELECT * FROM reviews WHERE order_id=$order_id AND user_id=$user_id LIMIT 1");
    $existing=$rq?mysqli_fetch_assoc($rq):null;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $rating=max(1,min(5,(int)($_POST['rating']??5)));
    $comment=trim($_POST['comment']??'');
    if($comment==='') $comment='Barang sudah diterima, sesuai deskripsi dan transaksi berjalan lancar.';
    if(db_table_exists($conn,'reviews')){
        $stmt=mysqli_prepare($conn,"INSERT INTO reviews (order_id, product_id, user_id, seller_id, rating, comment) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating=VALUES(rating), comment=VALUES(comment), updated_at=NOW()");
        mysqli_stmt_bind_param($stmt,'iiiiis',$order_id,$order['product_id'],$user_id,$order['seller_id'],$rating,$comment);
        mysqli_stmt_execute($stmt);
        revibe_sync_product_rating($conn, (int)$order['product_id']);
    }
    add_notification($conn,(int)$order['seller_id'],'Ulasan baru masuk','Pembeli memberi ulasan untuk '.$order['product_name'].'.','review');
    $_SESSION['success']='Ulasan berhasil disimpan dan rating produk otomatis diperbarui.';
    header('Location: detail.php?id='.(int)$order['product_id'].'#reviews'); exit;
}
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Ulasan - ReVibe</title><link rel="stylesheet" href="../assets/css/style.css"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="stylesheet" href="../assets/css/loader.css?v=25">
</head><body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>

<div class="navbar"><a href="buyer_orders.php" class="btn">← Pesanan</a><a href="detail.php?id=<?= (int)$order['product_id'] ?>" class="btn">Detail Produk</a></div>
<div class="page-shell narrow review-write-page"><div class="page-header"><h1>Beri Rating & Ulasan</h1><p>Ulasanmu membantu pembeli lain agar tidak ragu membeli barang preloved.</p></div>
<div class="review-product-card"><img src="<?= e(revibe_public_file_url($order['image'] ?? 'default.png', 'products')) ?>" alt="<?= e($order['product_name']) ?>"><div><strong><?= e($order['product_name']) ?></strong><p><?= e($order['condition_status'] ?? 'Preloved') ?> • <?= money($order['price']) ?></p><small>Order <?= e($order['order_code'] ?? '#'.$order_id) ?></small></div></div>
<form method="POST" class="form-card shopee-review-form"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$order_id ?>">
<label>Rating Produk</label><div class="star-picker" id="starPicker"><?php for($i=1;$i<=5;$i++): ?><button type="button" data-star="<?= $i ?>">★</button><?php endfor; ?></div><input type="hidden" name="rating" id="ratingValue" value="<?= (int)($existing['rating'] ?? 5) ?>">
<label>Ulasan Produk</label><textarea name="comment" rows="6" placeholder="Contoh: Barang sesuai foto, kondisi masih bagus, seller ramah, dan pengiriman aman."><?= e($existing['comment'] ?? '') ?></textarea>
<div class="info-box">Untuk versi awal, ulasan dibuat teks dulu. Nanti bisa dikembangkan upload foto/video barang.</div>
<button class="btn primary full" type="submit">Kirim Ulasan</button></form></div>
<script>
const rating=document.getElementById('ratingValue');
function paintStars(v){document.querySelectorAll('#starPicker button').forEach(btn=>btn.classList.toggle('active', parseInt(btn.dataset.star)<=v));}
document.querySelectorAll('#starPicker button').forEach(btn=>btn.addEventListener('click',()=>{rating.value=btn.dataset.star;paintStars(parseInt(rating.value));}));
paintStars(parseInt(rating.value||5));
</script>
<?php render_revibe_floating_nav($conn); ?>
<script defer src="../assets/js/loader.js?v=25"></script>
</body></html>
