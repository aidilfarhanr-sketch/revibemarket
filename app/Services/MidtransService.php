<?php
class MidtransService {
    private $conn;
    public function __construct($conn=null){$this->conn=$conn;}

    public function createSnap(array $order): array {
        $serverKey = (string)(function_exists('revibe_env') ? revibe_env('MIDTRANS_SERVER_KEY','') : '');
        $clientKey = (string)(function_exists('revibe_env') ? revibe_env('MIDTRANS_CLIENT_KEY','') : '');
        $sandbox = !filter_var(function_exists('revibe_env') ? revibe_env('MIDTRANS_IS_PRODUCTION', false) : false, FILTER_VALIDATE_BOOLEAN);
        $amount = (int)($order['gross_amount'] ?? $order['amount'] ?? $order['total'] ?? 0);
        $orderId = (string)($order['order_code'] ?? $order['invoice_number'] ?? ('RV-' . ($order['id'] ?? time())));
        if ($serverKey === '' || $amount <= 0) {
            return [
                'success'=>false,
                'gateway'=>'midtrans',
                'sandbox'=>$sandbox,
                'payment_url'=>'',
                'snap_token'=>'',
                'message'=>'Midtrans belum aktif. Isi MIDTRANS_SERVER_KEY dan MIDTRANS_CLIENT_KEY di .env untuk sandbox asli.'
            ];
        }

        $endpoint = $sandbox ? 'https://app.sandbox.midtrans.com/snap/v1/transactions' : 'https://app.midtrans.com/snap/v1/transactions';
        $payload = [
            'transaction_details' => ['order_id'=>$orderId, 'gross_amount'=>$amount],
            'customer_details' => [
                'first_name' => (string)($order['buyer_name'] ?? 'ReVibe Buyer'),
                'email' => (string)($order['buyer_email'] ?? ''),
                'phone' => (string)($order['buyer_phone'] ?? ''),
            ],
            'callbacks' => ['finish' => function_exists('revibe_app_url') ? revibe_app_url('pages/payment.php?order_id=' . (int)($order['id'] ?? 0)) : ''],
        ];
        $response = $this->postJson($endpoint, $payload, 'Basic ' . base64_encode($serverKey . ':'));
        if (!$response['ok']) {
            if (function_exists('revibe_log')) revibe_log('error', 'midtrans snap create failed', ['order'=>$orderId, 'error'=>$response['error'], 'body'=>$response['body']]);
            return ['success'=>false, 'gateway'=>'midtrans', 'sandbox'=>$sandbox, 'payment_url'=>'', 'snap_token'=>'', 'message'=>'Gagal membuat pembayaran Midtrans sandbox. Cek key dan koneksi server.', 'error'=>$response['error']];
        }
        $body = json_decode($response['body'], true) ?: [];
        return [
            'success'=>true,
            'gateway'=>'midtrans',
            'sandbox'=>$sandbox,
            'client_key'=>$clientKey,
            'payment_url'=>(string)($body['redirect_url'] ?? ''),
            'snap_token'=>(string)($body['token'] ?? ''),
            'gateway_reference'=>$orderId,
            'raw'=>$body,
        ];
    }

    private function postJson(string $url, array $payload, string $auth): array {
        if (!function_exists('curl_init')) return ['ok'=>false, 'error'=>'curl_not_available', 'body'=>''];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST=>true,
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json', 'Accept: application/json', 'Authorization: ' . $auth],
            CURLOPT_POSTFIELDS=>json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT=>20,
        ]);
        $body = (string)curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['ok'=>$err==='' && $code>=200 && $code<300, 'error'=>$err ?: ('http_'.$code), 'body'=>$body, 'code'=>$code];
    }

    public function verifySignature(array $payload): bool {
        $serverKey = (string)(function_exists('revibe_env') ? revibe_env('MIDTRANS_SERVER_KEY','') : '');
        if ($serverKey === '' || empty($payload['signature_key'])) return false;
        $raw = ($payload['order_id'] ?? '').($payload['status_code'] ?? '').($payload['gross_amount'] ?? '').$serverKey;
        return hash_equals(hash('sha512', $raw), (string)$payload['signature_key']);
    }

    public function mapStatus(string $transactionStatus, string $fraudStatus=''): string {
        if (in_array($transactionStatus, ['settlement'], true)) return 'paid';
        if ($transactionStatus === 'capture') return $fraudStatus === 'challenge' ? 'waiting_payment' : 'paid';
        if ($transactionStatus === 'pending') return 'waiting_payment';
        if ($transactionStatus === 'expire') return 'expired';
        if ($transactionStatus === 'cancel') return 'cancelled';
        if (in_array($transactionStatus, ['deny','failure'], true)) return 'failed';
        if ($transactionStatus === 'refund') return 'refunded';
        return 'waiting_payment';
    }
}
