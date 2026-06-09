<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
verify_csrf();
$user_id = (int)$_SESSION['user_id'];
$order_id = (int)($_POST['order_id'] ?? 0);
if($order_id <= 0){ $_SESSION['error']='Pesanan tidak valid.'; header('Location: buyer_orders.php'); exit; }

mysqli_begin_transaction($conn);
try {
    $q = mysqli_query($conn, "SELECT * FROM orders WHERE id=$order_id AND buyer_id=$user_id FOR UPDATE");
    $order = $q ? mysqli_fetch_assoc($q) : null;
    if(!$order) throw new Exception('Pesanan tidak ditemukan.');
    $status = $order['status'] ?? '';
    $product_id = (int)$order['product_id'];
    $qty = max(1, (int)($order['qty'] ?? 1));
    if(in_array($status, ['pending_payment','pending'], true) || (($order['payment_method'] ?? '')==='cod' && $status==='processing')){
        mysqli_query($conn, "UPDATE products SET stock = stock + $qty WHERE id=$product_id");
        if(db_table_exists($conn,'payments')) mysqli_query($conn, "DELETE FROM payments WHERE order_id=$order_id");
        if(db_table_exists($conn,'shipments')) mysqli_query($conn, "DELETE FROM shipments WHERE order_id=$order_id");
        if(db_table_exists($conn,'order_items')) mysqli_query($conn, "DELETE FROM order_items WHERE order_id=$order_id");
        mysqli_query($conn, "DELETE FROM orders WHERE id=$order_id AND buyer_id=$user_id");
        add_notification($conn, (int)$order['seller_id'], 'Pesanan dibatalkan', 'Pembeli membatalkan pesanan sebelum pembayaran.', 'order');
        $_SESSION['success']='Pesanan berhasil dibatalkan dan dihapus karena belum ada pembayaran platform/pengiriman.';
    } else {
        throw new Exception('Pesanan yang sudah dibayar/diproses tidak bisa dihapus langsung. Ajukan komplain/refund agar admin meninjau dana.');
    }
    mysqli_commit($conn);
} catch(Throwable $e){
    mysqli_rollback($conn);
    revibe_log('error','cancel order failed',['order_id'=>$order_id,'error'=>$e->getMessage()]);
    $_SESSION['error']=revibe_is_debug()?('Gagal membatalkan pesanan: '.$e->getMessage()):'Aksi tidak dapat diproses. Jika masalah berlanjut, hubungi admin.';
}
header('Location: buyer_orders.php'); exit;
