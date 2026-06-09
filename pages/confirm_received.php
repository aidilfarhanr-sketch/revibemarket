<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
verify_csrf();
$user_id=(int)$_SESSION['user_id'];
$order_id=(int)($_POST['order_id']??0);

mysqli_begin_transaction($conn);
try{
    $q=mysqli_query($conn,"SELECT * FROM orders WHERE id=$order_id AND buyer_id=$user_id FOR UPDATE");
    $o=$q?mysqli_fetch_assoc($q):null;
    if(!$o || !in_array($o['status'], ['shipped','delivered'], true)) throw new Exception('Pesanan tidak bisa dikonfirmasi.');
    $oldStatus = $o['status'] ?? 'delivered';
    mysqli_query($conn,"UPDATE orders SET status='completed', completed_at=NOW(), updated_at=NOW() WHERE id=$order_id AND buyer_id=$user_id AND status IN ('shipped','delivered')");
    if(mysqli_affected_rows($conn)!==1) throw new Exception('Status pesanan sudah berubah.');
    mysqli_query($conn,"UPDATE products SET sold = COALESCE(sold,0) + ".(int)$o['qty']." WHERE id=".(int)$o['product_id']);
    if (db_table_exists($conn,'shipments')) mysqli_query($conn,"UPDATE shipments SET status='delivered', delivered_at=NOW() WHERE order_id=$order_id");
    revibe_order_status_history($conn, $order_id, $oldStatus, 'completed', $user_id, 'Buyer konfirmasi barang sampai');
    revibe_release_order_settlement($conn,$order_id);
    award_seller_cashback($conn,$order_id);
    revibe_notify_user_event($conn, $user_id, 'order_completed', 'Pesanan Selesai', 'Pesanan #' . $order_id . ' selesai. Kamu bisa memberi review produk.', ['order_id'=>$order_id]);
    mysqli_commit($conn);
    $_SESSION['success']='Pesanan selesai. Cashback seller 6% dan settlement seller diproses aman untuk demo.';
    header('Location: review.php?order_id='.$order_id); exit;
}catch(Throwable $e){
    mysqli_rollback($conn);
    revibe_log('error','confirm received failed',['order_id'=>$order_id,'error'=>$e->getMessage()]);
    $_SESSION['error']=revibe_is_debug()?('Gagal konfirmasi: '.$e->getMessage()):'Aksi tidak dapat diproses. Jika masalah berlanjut, hubungi admin.';
}
header('Location: buyer_orders.php'); exit;
?>
