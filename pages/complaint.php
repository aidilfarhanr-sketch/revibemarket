<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
$user_id=(int)$_SESSION['user_id'];
$order_id=(int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$q=mysqli_query($conn,"SELECT o.*, p.name AS product_name FROM orders o JOIN products p ON o.product_id=p.id WHERE o.id=$order_id AND o.buyer_id=$user_id LIMIT 1");
$order=$q?mysqli_fetch_assoc($q):null;
if(!$order){ $_SESSION['error']='Order tidak ditemukan.'; header('Location: buyer_orders.php'); exit; }
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    if (!revibe_rate_limit('complaint_submit', 5, 600)) {
        $_SESSION['error']='Terlalu sering mengirim komplain. Tunggu beberapa menit.';
        header('Location: complaint.php?order_id='.$order_id); exit;
    }
    $reason=trim($_POST['reason']??'');
    $detail=trim($_POST['detail']??'');
    $evidence=null;
    if(!empty($_FILES['evidence']['name'])){
        $evidence = revibe_safe_upload($_FILES['evidence'], 'complaints', [
            'prefix'=>'complaint',
            'allowed'=>['jpg','jpeg','png','webp','pdf'],
            'max_size'=>4*1024*1024,
            'private'=>true,
            'user_id'=>$user_id
        ]);
        if(!$evidence){ $_SESSION['error']='Bukti komplain harus jpg/png/webp/pdf valid dan maksimal 4MB.'; header('Location: complaint.php?order_id='.$order_id); exit; }
    }
    if(db_table_exists($conn,'complaints')){
        $stmt=mysqli_prepare($conn,"INSERT INTO complaints (order_id, buyer_id, seller_id, reason, detail, evidence_file, status) VALUES (?, ?, ?, ?, ?, ?, 'open')");
        mysqli_stmt_bind_param($stmt,'iiisss',$order_id,$user_id,$order['seller_id'],$reason,$detail,$evidence);
        mysqli_stmt_execute($stmt);
    }
    mysqli_query($conn,"UPDATE orders SET status='complaint', updated_at=NOW() WHERE id=$order_id");
    add_notification($conn,(int)$order['seller_id'],'Komplain baru','Pembeli membuat komplain untuk order #'.$order_id,'complaint');
    $_SESSION['success']='Komplain berhasil dikirim. Admin akan meninjau.';
    header('Location: buyer_orders.php'); exit;
}
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Komplain - ReVibe</title><link rel="stylesheet" href="../assets/css/style.css"><link rel="stylesheet" href="../assets/css/loader.css?v=25">
</head><body>
<div id="rv-page-loader" class="rv-loader" role="status" aria-live="polite" aria-label="Loading ReVibe Market">
  <div class="rv-loader-card">
    <div class="rv-loader-ring"><div class="rv-loader-logo">RV</div></div>
    <p>Loading ReVibe Market...</p>
    <small>Memuat pengalaman belanja preloved terbaik...</small>
  </div>
</div>

<div class="navbar"><a href="buyer_orders.php" class="btn">← Pesanan</a></div>
<div class="page-shell narrow"><div class="page-header"><h1>Ajukan Komplain</h1><p><?= e($order['product_name']) ?></p></div>
<form method="POST" enctype="multipart/form-data" class="form-card"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$order_id ?>">
<label>Alasan</label><select name="reason" required><option>Barang tidak sesuai</option><option>Barang rusak</option><option>Belum dikirim</option><option>Barang palsu</option><option>Lainnya</option></select>
<label>Detail Komplain</label><textarea name="detail" rows="5" required></textarea>
<label>Bukti Foto/PDF</label><input type="file" name="evidence" accept="image/*,.pdf">
<button class="btn danger full" type="submit">Kirim Komplain</button></form></div><?php render_revibe_floating_nav($conn); ?>
<script defer src="../assets/js/loader.js?v=25"></script>
</body></html>
