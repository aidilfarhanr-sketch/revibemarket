<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if(!is_array($payload)) $payload = [];

$gateway = strtolower((string)($_GET['gateway'] ?? $payload['gateway'] ?? revibe_env('PAYMENT_GATEWAY','manual')));
if (!revibe_rate_limit('payment_webhook_' . $gateway, 120, 60)) revibe_json_response(false,'Webhook terlalu sering',[],'RATE_LIMITED',429);
$signatureValid = false;
$mapped = 'waiting_payment';
$orderRef = '';

if($gateway === 'midtrans'){
    require_once __DIR__ . '/../app/Services/MidtransService.php';
    $svc = new MidtransService($conn);
    $signatureValid = $svc->verifySignature($payload);
    $mapped = $svc->mapStatus((string)($payload['transaction_status'] ?? ''), (string)($payload['fraud_status'] ?? ''));
    $orderRef = (string)($payload['order_id'] ?? '');
} elseif($gateway === 'xendit'){
    require_once __DIR__ . '/../app/Services/XenditService.php';
    $token = $_SERVER['HTTP_X_CALLBACK_TOKEN'] ?? ($_SERVER['HTTP_XENDIT_CALLBACK_TOKEN'] ?? '');
    $svc = new XenditService($conn);
    $signatureValid = $svc->verifyToken($token);
    $mapped = $svc->mapStatus((string)($payload['status'] ?? ''));
    $orderRef = (string)($payload['external_id'] ?? $payload['id'] ?? '');
} else {
    $signature = $_SERVER['HTTP_X_REVIBE_SIGNATURE'] ?? '';
    $signatureValid = (new PaymentService($conn))->verifyWebhookSignature($raw, $signature);
    $mapped = (string)($payload['status'] ?? 'waiting_payment');
    $orderRef = (string)($payload['order_id'] ?? '');
}

$orderId = 0;
if (db_table_exists($conn, 'orders') && $orderRef !== '') {
    $ref = mysqli_real_escape_string($conn, $orderRef);
    $q = mysqli_query($conn, "SELECT id FROM orders WHERE order_code='{$ref}' OR invoice_number='{$ref}' LIMIT 1");
    $r = $q ? mysqli_fetch_assoc($q) : null;
    $orderId = (int)($r['id'] ?? 0);
}
if ($orderId <= 0) {
    $orderId = (int)preg_replace('/[^0-9]/', '', $orderRef);
}

$paymentId = null;
if($orderId > 0 && db_table_exists($conn,'payments')){
    $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id,status FROM payments WHERE order_id={$orderId} LIMIT 1"));
    $paymentId = (int)($p['id'] ?? 0);
}

if(db_table_exists($conn,'payment_logs')){
    $json = mysqli_real_escape_string($conn, substr($raw,0,65000));
    $gw = mysqli_real_escape_string($conn,$gateway);
    $event = mysqli_real_escape_string($conn,(string)($payload['transaction_status'] ?? $payload['status'] ?? 'webhook'));
    $sig = $signatureValid ? 1 : 0;
    mysqli_query($conn, "INSERT INTO payment_logs (order_id,payment_id,gateway,event_type,payload_json,signature_valid,created_at) VALUES ({$orderId},".($paymentId ?: 'NULL').",'{$gw}','{$event}','{$json}',{$sig},NOW())");
}

if(!$signatureValid) {
    revibe_audit_log($conn,'payment_webhook_invalid','order',$orderId,['gateway'=>$gateway]);
    revibe_json_response(false,'Signature webhook tidak valid',[],'WEBHOOK_SIGNATURE_INVALID',403);
}
if($orderId <= 0) {
    revibe_json_response(false,'Order webhook tidak ditemukan',[],'WEBHOOK_ORDER_MISSING',400);
}

$key = 'payment_webhook_'.$gateway.'_'.$orderId.'_'.hash('sha256',$raw);
if(db_table_exists($conn,'idempotency_keys')){
    $safe = mysqli_real_escape_string($conn,$key);
    $exists = mysqli_query($conn,"SELECT id FROM idempotency_keys WHERE action='payment_webhook' AND key_value='{$safe}' LIMIT 1");
    if($exists && mysqli_num_rows($exists)>0) {
        revibe_json_response(true,'Webhook sudah pernah diproses',['idempotent'=>true]);
    }
}

$ok = (new PaymentService($conn))->updatePaymentFromGateway($orderId, $mapped, $gateway, ['raw_ref'=>$orderRef]);
if (!$ok) {
    revibe_json_response(false,'Webhook belum bisa diproses. Coba ulang otomatis.',[],'WEBHOOK_PROCESS_FAILED',500);
}

if(db_table_exists($conn,'idempotency_keys')){
    $safe = mysqli_real_escape_string($conn,$key);
    $hash = hash('sha256', $mapped);
    mysqli_query($conn,"INSERT INTO idempotency_keys (key_value,action,entity_type,entity_id,response_hash,created_at) VALUES ('{$safe}','payment_webhook','order',{$orderId},'{$hash}',NOW())");
}

revibe_json_response(true,'Webhook diproses',['order_id'=>$orderId,'payment_status'=>$mapped]);
