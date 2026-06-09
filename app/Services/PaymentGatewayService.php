<?php
class PaymentGatewayService {
    protected $conn;
    public function __construct($conn = null) { $this->conn = $conn; }

    public function createPayment(array $order, string $gateway = ''): array {
        $gateway = strtolower($gateway ?: (function_exists('revibe_env') ? (string)revibe_env('PAYMENT_GATEWAY','manual') : 'manual'));
        if ($gateway === 'midtrans') {
            require_once __DIR__.'/MidtransService.php';
            $result = (new MidtransService($this->conn))->createSnap($order);
        } elseif ($gateway === 'xendit') {
            require_once __DIR__.'/XenditService.php';
            $result = (new XenditService($this->conn))->createInvoice($order);
        } else {
            require_once __DIR__.'/ManualPaymentService.php';
            $result = (new ManualPaymentService($this->conn))->createInstruction($order);
        }
        $this->recordGatewayRequest($order, $gateway, $result);
        return $result;
    }

    private function recordGatewayRequest(array $order, string $gateway, array $result): void {
        if (!$this->conn || !function_exists('db_table_exists') || !db_table_exists($this->conn, 'payment_gateway_requests')) return;
        $orderId = (int)($order['id'] ?? $order['order_id'] ?? 0);
        if ($orderId <= 0) return;
        $paymentId = isset($order['payment_id']) ? (int)$order['payment_id'] : null;
        $req = json_encode($order, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $res = json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $status = !empty($result['success']) ? 'created' : 'failed';
        $ref = (string)($result['gateway_reference'] ?? $result['external_id'] ?? $result['snap_token'] ?? '');
        $url = (string)($result['payment_url'] ?? $result['invoice_url'] ?? $result['redirect_url'] ?? '');
        $stmt = mysqli_prepare($this->conn, "INSERT INTO payment_gateway_requests (order_id, payment_id, gateway, request_json, response_json, status, gateway_reference, payment_url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'iissssss', $orderId, $paymentId, $gateway, $req, $res, $status, $ref, $url);
            mysqli_stmt_execute($stmt);
        }
    }
}
