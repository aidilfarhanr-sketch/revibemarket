<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
revibe_require_verified_account($conn, 'verification_required.php');

$user_id=(int)$_SESSION['user_id'];
$peer_id=(int)($_GET['user_id'] ?? $_GET['seller_id'] ?? $_POST['user_id'] ?? $_POST['seller_id'] ?? 0);
$product_id=(int)($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
if($peer_id<=0 || $peer_id===$user_id){ $_SESSION['error']='Chat user tidak valid.'; header('Location: messages.php'); exit; }
if(!db_table_exists($conn, 'chat_messages')){ $_SESSION['error']='Chat belum siap. Jalankan migration database 005_create_chat.sql.'; header('Location: messages.php'); exit; }

$peer=mysqli_fetch_assoc(mysqli_query($conn,"SELECT id, first_name, last_name, profile_photo FROM users WHERE id=$peer_id LIMIT 1"));
if(!$peer){ $_SESSION['error']='User tidak ditemukan.'; header('Location: messages.php'); exit; }

$product=null;
$productImage='default.png';
if($product_id>0){
    $product=mysqli_fetch_assoc(mysqli_query($conn,"SELECT p.*, u.first_name AS seller_first, u.last_name AS seller_last FROM products p LEFT JOIN users u ON p.user_id=u.id WHERE p.id=$product_id LIMIT 1"));
    if($product){ $productImage = revibe_product_image($conn, $product_id); }
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $message=trim($_POST['message']??'');
    if($message!=='' && db_table_exists($conn,'chat_messages')){
        if(!revibe_rate_limit('chat_' . $user_id, 10, 60)){
            $_SESSION['error'] = 'Terlalu banyak pesan. Tunggu sebentar sebelum mengirim lagi.';
        } else {
            $stmt=mysqli_prepare($conn,"INSERT INTO chat_messages (sender_id, receiver_id, product_id, message) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt,'iiis',$user_id,$peer_id,$product_id,$message);
            mysqli_stmt_execute($stmt);
            add_notification($conn,$peer_id,'Pesan baru','Ada pesan baru dari '.($_SESSION['user_name'] ?? 'user ReVibe'),'chat');
        }
    }
    header('Location: chat.php?user_id='.$peer_id.($product_id>0?'&product_id='.$product_id:'')); exit;
}

if(db_table_exists($conn,'chat_messages')){
    mysqli_query($conn,"UPDATE chat_messages SET is_read=1 WHERE receiver_id=$user_id AND sender_id=$peer_id" . ($product_id>0 ? " AND (product_id=$product_id OR product_id IS NULL OR product_id=0)" : ""));
}
$productFilter = $product_id>0 ? " AND (m.product_id=$product_id OR m.product_id IS NULL OR m.product_id=0)" : "";
$messages=db_table_exists($conn,'chat_messages')?mysqli_query($conn,"SELECT m.*, u.first_name FROM chat_messages m JOIN users u ON m.sender_id=u.id WHERE ((m.sender_id=$user_id AND m.receiver_id=$peer_id) OR (m.sender_id=$peer_id AND m.receiver_id=$user_id)) $productFilter ORDER BY m.id ASC"):false;
$peerName = trim(($peer['first_name']??'User').' '.($peer['last_name']??''));
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8">
    <title>Chat - ReVibe</title>
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

<div class="navbar"><a href="messages.php" class="btn">← Pesan</a><a href="../index.php" class="btn">Beranda</a><?php if($product_id>0): ?><a href="detail.php?id=<?= $product_id ?>" class="btn">Detail Produk</a><?php endif; ?></div>
<div class="page-shell narrow">
    <?php if(isset($_SESSION['error'])): ?><div class="alert error"><?= e($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
    <div class="page-header chat-header shopee-chat-title">
        <?php if(!empty($peer['profile_photo'])): ?>
            <img class="chat-avatar-img" src="<?= e(revibe_public_file_url($peer['profile_photo'], 'profile')) ?>" alt="Foto <?= e($peerName) ?>">
        <?php else: ?>
            <div class="avatar-letter"><?= e(strtoupper(substr($peer['first_name'] ?? 'U',0,1))) ?></div>
        <?php endif; ?>
        <div><h1>Chat dengan <?= e($peerName) ?></h1><p><?= $product ? 'Membahas produk: '.e($product['name']) : 'Obrolan antar pengguna ReVibe' ?></p></div>
    </div>

    <?php if($product): ?>
    <a class="chat-product-card" href="detail.php?id=<?= (int)$product['id'] ?>">
        <img src="<?= e(revibe_public_file_url($productImage, 'products')) ?>" alt="<?= e($product['name']) ?>">
        <div class="chat-product-info">
            <span class="eyebrow">Produk yang dibahas</span>
            <strong><?= e($product['name']) ?></strong>
            <p><?= money($product['price']) ?> • <?= e($product['condition_status'] ?? 'Preloved') ?> • Stok <?= (int)($product['stock'] ?? 0) ?></p>
            <small>📍 <?= e($product['location'] ?? 'Lokasi belum diisi') ?> • klik untuk lihat detail</small>
        </div>
        <span class="chat-product-arrow">›</span>
    </a>
    <?php endif; ?>

    <div class="chat-box modern-chat" id="chatBox">
    <?php if($messages && mysqli_num_rows($messages)>0): while($m=mysqli_fetch_assoc($messages)): ?>
        <div class="chat-bubble <?= (int)$m['sender_id']===$user_id?'me':'them' ?>"><strong><?= e($m['first_name']) ?></strong><p><?= nl2br(e($m['message'])) ?></p><small><?= e(date('d M H:i', strtotime($m['created_at']))) ?></small></div>
    <?php endwhile; else: ?>
        <div class="empty-state compact"><h3>Mulai obrolan pertama</h3><p class="muted">Tanyakan kondisi barang, nego harga, atau metode COD/kirim dengan sopan.</p></div>
    <?php endif; ?>
    </div>
    <form method="POST" class="chat-form chat-composer"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$peer_id ?>"><input type="hidden" name="product_id" value="<?= (int)$product_id ?>"><textarea name="message" rows="3" placeholder="Tulis pesan ke <?= e($peer['first_name'] ?? 'user') ?>..." required></textarea><button class="btn primary" type="submit">Kirim</button></form>
</div>
<script>const box=document.getElementById('chatBox'); if(box){box.scrollTop=box.scrollHeight;}</script>
<?php render_revibe_floating_nav($conn); ?>
<script defer src="../assets/js/loader.js?v=25"></script>
</body></html>
