<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
$enabled = filter_var(revibe_env('ADMIN_2FA_REQUIRED', false), FILTER_VALIDATE_BOOLEAN);
$hasPages = is_file(__DIR__.'/../pages/admin_2fa.php') && is_file(__DIR__.'/../pages/admin_2fa_verify.php') && is_file(__DIR__.'/../pages/admin_2fa_resend.php');
$hasTable = db_table_exists($conn, 'verification_codes');
echo json_encode(['success'=>$hasPages && $hasTable,'admin_2fa_required'=>$enabled,'pages_ok'=>$hasPages,'verification_table_ok'=>$hasTable], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;
