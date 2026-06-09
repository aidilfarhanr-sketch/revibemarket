<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
revibe_require_verified_account($conn, 'verification_required.php');
verify_csrf();
if (!revibe_rate_limit('upload_payment_proof', 8, 600)) {
    $_SESSION['error'] = 'Terlalu sering upload bukti pembayaran. Tunggu beberapa menit.';
    header('Location: buyer_orders.php'); exit;
}
$user_id = (int)$_SESSION['user_id'];
$order_id = (int)($_POST['order_id'] ?? 0);

$q = mysqli_query($conn, "SELECT o.*, pay.status AS payment_status FROM orders o LEFT JOIN payments pay ON pay.order_id=o.id WHERE o.id=$order_id AND o.buyer_id=$user_id LIMIT 1");
$order = $q ? mysqli_fetch_assoc($q) : null;
if (!$order) { $_SESSION['error'] = 'Order tidak ditemukan.'; header('Location: buyer_orders.php'); exit; }
if (($order['payment_method'] ?? '') === 'cod') { $_SESSION['error'] = 'Pesanan COD tidak perlu upload bukti pembayaran. Bayar langsung saat barang diterima.'; header('Location: buyer_orders.php'); exit; }
if (!in_array($order['status'] ?? '', ['pending_payment','waiting_payment','paid'], true) || in_array($order['payment_status'] ?? '', ['waiting_verification','verified','refunded'], true)) { $_SESSION['error']='Bukti pembayaran sudah dikirim, sudah diverifikasi, atau status order tidak valid.'; header('Location: buyer_orders.php'); exit; }

$filename = null;
if (!empty($_FILES['payment_proof']['name'])) {
    $filename = revibe_safe_upload($_FILES['payment_proof'], 'payment_proofs', [
        'prefix'=>'pay',
        'allowed'=>['jpg','jpeg','png','webp'],
        'max_size'=>5*1024*1024,
        'private'=>true,
        'user_id'=>$user_id
    ]);
    if (!$filename) {
        $_SESSION['error'] = 'Bukti pembayaran harus berupa JPG, PNG, atau WEBP dan maksimal 5MB.';
        header('Location: buyer_orders.php'); exit;
    }
}

if (!$filename) { $_SESSION['error'] = 'Bukti pembayaran wajib diupload.'; header('Location: buyer_orders.php'); exit; }

if (db_table_exists($conn, 'payments')) {
    $safe = mysqli_real_escape_string($conn, $filename);
    mysqli_query($conn, "UPDATE payments SET proof_file='$safe', status='waiting_verification', paid_at=NOW() WHERE order_id=$order_id AND status IN ('waiting_upload','rejected')");
}
revibe_payment_status_history($conn, null, $order_id, $order['payment_status'] ?? 'waiting_upload', 'waiting_verification', 'manual_upload', 'Buyer upload bukti pembayaran manual');
revibe_audit_log($conn, 'payment_proof_uploaded', 'order', $order_id, ['buyer_id'=>$user_id]);
revibe_notify_user_event($conn, (int)$order['seller_id'], 'payment_uploaded', 'Pembayaran diupload', 'Pembeli sudah upload bukti pembayaran untuk order #' . $order_id . '. Menunggu verifikasi admin.', ['order_id'=>$order_id]);
revibe_queue_notification($conn, null, 'in_app', 'admin_payment_manual', 'Payment manual menunggu verifikasi', 'Order #' . $order_id . ' menunggu verifikasi bukti pembayaran.', '', ['order_id'=>$order_id]);
$_SESSION['success'] = 'Bukti pembayaran berhasil diupload. Menunggu verifikasi admin.';
header('Location: buyer_orders.php'); exit;
?>
