<?php
require_once __DIR__ . '/config/env.php';
header('Content-Type: application/json; charset=utf-8');
$rid = bin2hex(random_bytes(8));
header('X-Request-Id: ' . $rid);
$dbOk = false;
$dbMessage = 'Database belum dicek.';
try {
    $conn = @mysqli_connect(
        (string)revibe_env('DB_HOST', 'localhost'),
        (string)revibe_env('DB_USER', 'root'),
        (string)revibe_env('DB_PASS', ''),
        (string)revibe_env('DB_NAME', 'revibemarket'),
        (int)revibe_env('DB_PORT', 3306)
    );
    if ($conn) {
        $dbOk = true;
        $dbMessage = 'Database terhubung.';
        @mysqli_close($conn);
    } else {
        $dbMessage = 'Database belum terhubung.';
    }
} catch (Throwable $e) {
    $dbMessage = 'Database belum terhubung.';
}
http_response_code($dbOk ? 200 : 503);
echo json_encode([
    'success' => $dbOk,
    'ok' => $dbOk,
    'app' => 'ReVibe Market',
    'mode' => 'cloudflare_demo',
    'php' => PHP_VERSION,
    'database' => $dbMessage,
    'debug' => filter_var(revibe_env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN) ? 'aktif' : 'nonaktif',
    'payment_mode' => (string)revibe_env('PAYMENT_MODE', 'manual_demo'),
    'service_fee_percent' => (float)revibe_env('REVIBE_SERVICE_FEE_PERCENT', 12),
    'seller_cashback_percent' => (float)revibe_env('REVIBE_SELLER_CASHBACK_PERCENT', 6),
    'message' => $dbOk ? 'Health check ReVibe berjalan.' : 'Database belum terhubung.',
    'request_id' => $rid,
    'time' => date('c')
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
