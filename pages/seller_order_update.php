<?php
require_once __DIR__ . '/../config/session.php';
include '../config/db.php';
require_once '../config/functions.php';
require_login('../index.php');
verify_csrf();
if (!revibe_rate_limit('seller_order_update', 30, 600)) {
    $_SESSION['error'] = 'Terlalu banyak update status. Tunggu sebentar.';
    header('Location: seller_center.php');
    exit;
}
$user_id = (int)$_SESSION['user_id'];
$order_id = (int)($_POST['order_id'] ?? 0);
$status = $_POST['status'] ?? '';
$tracking = trim($_POST['tracking_number'] ?? '');
$orderFlow = [
    'paid_waiting_seller' => 0,
    'processing' => 1,
    'shipped' => 2,
    'delivered' => 3,
];
$currentAllowed = array_keys($orderFlow);

mysqli_begin_transaction($conn);
try {
    $q = mysqli_query($conn, "SELECT * FROM orders WHERE id=$order_id AND seller_id=$user_id FOR UPDATE");
    $o = $q ? mysqli_fetch_assoc($q) : null;
    if (!$o || !in_array($status, $currentAllowed, true)) throw new Exception('Update order tidak valid.');
    $current = $o['status'] ?? '';
    if (($o['payment_method'] ?? '') !== 'cod' && $current === 'pending_payment') throw new Exception('Pembayaran belum diverifikasi admin.');
    if (!in_array($current, $currentAllowed, true)) throw new Exception('Order belum bisa diproses seller atau sudah final.');
    if (in_array($current, ['completed','cancelled','expired','refund','complaint'], true)) throw new Exception('Order sudah final/tidak bisa diubah.');
    $nextIndex = $orderFlow[$current] + 1;
    $targetIndex = $orderFlow[$status];
    if ($targetIndex < $orderFlow[$current]) throw new Exception('Status tidak bisa mundur.');
    if ($targetIndex > $nextIndex) throw new Exception('Status harus berurutan, tidak boleh melompati alur.');
    if ($targetIndex === $orderFlow[$current]) throw new Exception('Pilih status berikutnya untuk memperbarui order.');
    $statusSafe = mysqli_real_escape_string($conn, $status);
    $oldStatus = $current;
    mysqli_query($conn, "UPDATE orders SET status='$statusSafe', updated_at=NOW() WHERE id=$order_id AND seller_id=$user_id");
    if (mysqli_affected_rows($conn) !== 1) throw new Exception('Order tidak berhasil diperbarui.');
    revibe_order_status_history($conn, $order_id, $oldStatus, $statusSafe, $user_id, 'Seller update status/resi');
    if (db_table_exists($conn, 'shipments')) {
        $trackingSafe = mysqli_real_escape_string($conn, $tracking);
        $shipStatus = $status === 'shipped' ? 'shipped' : ($status === 'delivered' ? 'delivered' : 'processing');
        mysqli_query($conn, "UPDATE shipments SET status='$shipStatus', tracking_number='$trackingSafe', shipped_at=IF('$shipStatus'='shipped', NOW(), shipped_at), delivered_at=IF('$shipStatus'='delivered', NOW(), delivered_at) WHERE order_id=$order_id");
    }
    revibe_notify_user_event($conn, (int)$o['buyer_id'], 'shipping_update', 'Pesanan Kamu Diperbarui', 'Seller memperbarui status order #' . $order_id . ' menjadi ' . $status . '. Resi: ' . $tracking, ['order_id'=>$order_id]);
    mysqli_commit($conn);
    $_SESSION['success'] = 'Status order berhasil diperbarui.';
} catch (Throwable $e) {
    mysqli_rollback($conn);
    revibe_log('error', 'seller update order failed', ['order_id'=>$order_id, 'error'=>$e->getMessage()]);
    $_SESSION['error'] = revibe_is_debug() ? ('Gagal update order: ' . $e->getMessage()) : $e->getMessage();
}
header('Location: seller_center.php');
exit;
