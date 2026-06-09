<?php
class XenditService {
    private $conn;
    public function __construct($conn=null){$this->conn=$conn;}

    public function createInvoice(array $order): array {
        $apiKey = (string)(function_exists('revibe_env') ? revibe_env('XENDIT_API_KEY','') : '');
        $amount = (int)($order['gross_amount'] ?? $order['amount'] ?? $order['total'] ?? 0);
        $externalId = (string)($order['order_code'] ?? $order['invoice_number'] ?? ('RV-' . ($order['id'] ?? time())));
        if ($apiKey === '' || $amount <= 0) {
            return [
                'success'=>false,
                'gateway'=>'xendit',
                'invoice_url'=>'',
                'message'=>'Xendit belum aktif. Isi XENDIT_API_KEY dan XENDIT_WEBHOOK_TOKEN di .env untuk sandbox asli.'
            ];
        }

        $payload = [
            'external_id' => $externalId,
            'amount' => $amount,
            'description' => 'Pembayaran ReVibe Market ' . $externalId,
            'success_redirect_url' => function_exists('revibe_app_url') ? revibe_app_url('pages/payment.php?order_id=' . (int)($order['id'] ?? 0)) : '',
            'failure_redirect_url' => function_exists('revibe_app_url') ? revibe_app_url('pages/payment.php?order_id=' . (int)($order['id'] ?? 0)) : '',
            'customer' => [
                'given_names' => (string)($order['buyer_name'] ?? 'ReVibe Buyer'),
                'email' => (string)($order['buyer_email'] ?? ''),
                'mobile_number' => (string)($order['buyer_phone'] ?? ''),
            ],
        ];
        $response = $this->postJson('https://api.xendit.co/v2/invoices', $payload, 'Basic ' . base64_encode($apiKey . ':'));
        if (!$response['ok']) {
            if (function_exists('revibe_log')) revibe_log('error', 'xendit invoice create failed', ['external_id'=>$externalId, 'error'=>$response['error'], 'body'=>$response['body']]);
            return ['success'=>false, 'gateway'=>'xendit', 'invoice_url'=>'', 'message'=>'Gagal membuat invoice Xendit. Cek API key dan koneksi server.', 'error'=>$response['error']];
        }
        $body = json_decode($response['body'], true) ?: [];
        return [
            'success'=>true,
            'gateway'=>'xendit',
            'invoice_url'=>(string)($body['invoice_url'] ?? ''),
            'payment_url'=>(string)($body['invoice_url'] ?? ''),
            'gateway_reference'=>(string)($body['id'] ?? $externalId),
            'external_id'=>$externalId,
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

    public function verifyToken(string $token): bool {
        $expected = (string)(function_exists('revibe_env') ? revibe_env('XENDIT_WEBHOOK_TOKEN','') : '');
        return $expected !== '' && hash_equals($expected, $token);
    }

    public function mapStatus(string $status): string {
        $status = strtoupper($status);
        return ['PAID'=>'paid','PENDING'=>'waiting_payment','EXPIRED'=>'expired','FAILED'=>'failed','SETTLED'=>'paid'][$status] ?? 'waiting_payment';
    }
}
