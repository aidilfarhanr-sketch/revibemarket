<?php
class EscrowService {
    private $conn;
    public function __construct($conn) { $this->conn = $conn; }
    public function markOrderPaid(int $orderId, string $source = 'manual'): bool {
        mysqli_begin_transaction($this->conn);
        try {
            $q = mysqli_query($this->conn, "SELECT * FROM orders WHERE id=$orderId FOR UPDATE");
            $o = $q ? mysqli_fetch_assoc($q) : null;
            if (!$o) throw new Exception('Order tidak ditemukan.');
            $old = (string)($o['status'] ?? 'pending_payment');
            if (!in_array($old, ['pending','pending_payment','paid'], true)) throw new Exception('Status order tidak valid untuk paid.');
            mysqli_query($this->conn, "UPDATE orders SET status='paid_waiting_seller', updated_at=NOW() WHERE id=$orderId");
            if (function_exists('revibe_order_status_history')) revibe_order_status_history($this->conn, $orderId, $old, 'paid_waiting_seller', null, 'Payment paid via '.$source);
            $this->createPendingBalance($orderId);
            mysqli_commit($this->conn);
            return true;
        } catch (Throwable $e) { mysqli_rollback($this->conn); if(function_exists('revibe_log')) revibe_log('error','escrow mark paid failed',['order_id'=>$orderId,'error'=>$e->getMessage()]); return false; }
    }
    public function createPendingBalance(int $orderId): bool { return function_exists('revibe_create_pending_seller_balance') ? revibe_create_pending_seller_balance($this->conn, $orderId) : false; }
    public function releaseToSeller(int $orderId): bool { return function_exists('revibe_release_order_settlement') ? revibe_release_order_settlement($this->conn, $orderId) : false; }
    public function holdForComplaint(int $orderId, string $reason = ''): bool {
        if (function_exists('revibe_audit_log')) revibe_audit_log($this->conn, 'escrow_hold_complaint', 'order', $orderId, ['reason'=>$reason]);
        return true;
    }
}
