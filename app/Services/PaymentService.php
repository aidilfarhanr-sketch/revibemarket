<?php
class PaymentService {
    private $conn;
    public function __construct($conn = null) { $this->conn = $conn; }

    public function verifyWebhookSignature(string $payload, string $signature): bool {
        $secret = function_exists('revibe_env') ? (string)revibe_env('PAYMENT_WEBHOOK_SECRET', '') : '';
        if ($secret === '') return false;
        return hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    public function updatePaymentFromGateway(int $orderId, string $mappedStatus, string $gateway, array $context = []): bool {
        if (!$this->conn || $orderId <= 0) return false;
        mysqli_begin_transaction($this->conn);
        try {
            $orderQ = mysqli_query($this->conn, "SELECT * FROM orders WHERE id={$orderId} FOR UPDATE");
            $order = $orderQ ? mysqli_fetch_assoc($orderQ) : null;
            if (!$order) throw new Exception('Order tidak ditemukan');
            $payQ = mysqli_query($this->conn, "SELECT * FROM payments WHERE order_id={$orderId} LIMIT 1 FOR UPDATE");
            $payment = $payQ ? mysqli_fetch_assoc($payQ) : null;
            $paymentId = (int)($payment['id'] ?? 0);
            $oldPay = (string)($payment['status'] ?? '');
            $oldOrder = (string)($order['status'] ?? '');
            $gatewaySafe = mysqli_real_escape_string($this->conn, $gateway);
            $statusSafe = mysqli_real_escape_string($this->conn, $mappedStatus);

            if ($mappedStatus === 'paid') {
                mysqli_query($this->conn, "UPDATE payments SET status='verified', paid_at=COALESCE(paid_at,NOW()), verified_at=COALESCE(verified_at,NOW()), gateway='{$gatewaySafe}' WHERE order_id={$orderId}");
                mysqli_query($this->conn, "UPDATE orders SET status='paid_waiting_seller', payment_status='paid', paid_at=COALESCE(paid_at,NOW()), updated_at=NOW() WHERE id={$orderId} AND status IN ('pending','pending_payment','waiting_payment','paid')");
                if (function_exists('revibe_create_pending_seller_balance')) revibe_create_pending_seller_balance($this->conn, $orderId);
            } else {
                mysqli_query($this->conn, "UPDATE payments SET status='{$statusSafe}', gateway='{$gatewaySafe}' WHERE order_id={$orderId}");
                if (in_array($mappedStatus, ['expired','failed','cancelled','refunded'], true)) {
                    mysqli_query($this->conn, "UPDATE orders SET status='{$statusSafe}', payment_status='{$statusSafe}', updated_at=NOW() WHERE id={$orderId}");
                }
            }

            if (function_exists('revibe_payment_status_history')) revibe_payment_status_history($this->conn, $paymentId ?: null, $orderId, $oldPay, $mappedStatus === 'paid' ? 'paid' : $mappedStatus, $gateway, 'Gateway webhook');
            if (function_exists('revibe_order_status_history')) revibe_order_status_history($this->conn, $orderId, $oldOrder, ($mappedStatus==='paid'?'paid_waiting_seller':$mappedStatus), null, 'Gateway webhook');
            if (function_exists('revibe_audit_log')) revibe_audit_log($this->conn, 'payment_webhook_processed', 'order', $orderId, ['gateway'=>$gateway,'status'=>$mappedStatus] + $context);
            mysqli_commit($this->conn);
            return true;
        } catch (Throwable $e) {
            mysqli_rollback($this->conn);
            if (function_exists('revibe_log')) revibe_log('error', 'payment gateway update failed', ['order_id'=>$orderId, 'gateway'=>$gateway, 'status'=>$mappedStatus, 'error'=>$e->getMessage()]);
            if (class_exists('ErrorTrackingService')) {
                (new ErrorTrackingService())->capture($e, ['order_id'=>$orderId, 'gateway'=>$gateway, 'status'=>$mappedStatus], 'error');
                if (filter_var(function_exists('revibe_env') ? revibe_env('ALERT_PAYMENT_WEBHOOK_FAILURE', true) : true, FILTER_VALIDATE_BOOLEAN)) {
                    (new ErrorTrackingService())->alert('payment_webhook_failure', ['order_id'=>$orderId, 'gateway'=>$gateway, 'status'=>$mappedStatus], 'error');
                }
            }
            return false;
        }
    }
}
