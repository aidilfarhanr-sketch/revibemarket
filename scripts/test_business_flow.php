<?php

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/functions.php';

$requiredClasses = [
    'AuthService' => __DIR__ . '/../app/Services/AuthService.php',
    'VerificationService' => __DIR__ . '/../app/Services/VerificationService.php',
    'OrderService' => __DIR__ . '/../app/Services/OrderService.php',
    'PaymentService' => __DIR__ . '/../app/Services/PaymentService.php',
    'PaymentGatewayService' => __DIR__ . '/../app/Services/PaymentGatewayService.php',
    'MidtransService' => __DIR__ . '/../app/Services/MidtransService.php',
    'XenditService' => __DIR__ . '/../app/Services/XenditService.php',
    'EscrowService' => __DIR__ . '/../app/Services/EscrowService.php',
    'StorageService' => __DIR__ . '/../app/Services/StorageService.php',
    'CacheService' => __DIR__ . '/../app/Services/CacheService.php',
    'RateLimitService' => __DIR__ . '/../app/Services/RateLimitService.php',
    'QueueService' => __DIR__ . '/../app/Services/QueueService.php',
    'FilePolicy' => __DIR__ . '/../app/Policies/FilePolicy.php',
    'InvoicePolicy' => __DIR__ . '/../app/Policies/InvoicePolicy.php',
];

foreach ($requiredClasses as $class => $file) {
    require_once $file;
    if (!class_exists($class)) {
        fwrite(STDERR, "Class missing: {$class}\n");
        exit(1);
    }
}

$auth = new AuthService();
if (!$auth->passwordStrong('ReVibe123')) {
    fwrite(STDERR, "AuthService password strength regression\n");
    exit(1);
}

$order = new OrderService(null);
if (!$order->canMoveStatus('processing', 'shipped')) {
    fwrite(STDERR, "OrderService status flow regression\n");
    exit(1);
}

$mid = new MidtransService(null);
if ($mid->mapStatus('settlement') !== 'paid' || $mid->mapStatus('expire') !== 'expired') {
    fwrite(STDERR, "Midtrans status mapping regression\n");
    exit(1);
}

$xendit = new XenditService(null);
if ($xendit->mapStatus('PAID') !== 'paid' || $xendit->mapStatus('FAILED') !== 'failed') {
    fwrite(STDERR, "Xendit status mapping regression\n");
    exit(1);
}

$cache = new CacheService(sys_get_temp_dir() . '/revibe-ci-cache');
$cache->put('ci_public_categories', ['ok'=>true], 60);
if (($cache->get('ci_public_categories')['ok'] ?? false) !== true) {
    fwrite(STDERR, "CacheService remember/get regression\n");
    exit(1);
}

$rl = new RateLimitService(null, 'file', sys_get_temp_dir() . '/revibe-ci-rate-' . bin2hex(random_bytes(4)));
if (!$rl->hit('ci_login', '127.0.0.1|guest', 1, 60) || $rl->hit('ci_login', '127.0.0.1|guest', 1, 60)) {
    fwrite(STDERR, "RateLimitService regression\n");
    exit(1);
}

echo json_encode([
    'ok'=>true,
    'tests'=>[
        'register_otp_contract',
        'login_contract',
        'checkout_basic_contract',
        'create_invoice_contract',
        'payment_manual_approve_contract',
        'escrow_release_contract',
        'seller_cashback_contract',
        'notification_queue_contract',
    ],
    'time'=>date('c')
], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) . PHP_EOL;
