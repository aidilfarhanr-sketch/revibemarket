<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/functions.php';
$ok = (new ErrorTrackingService())->alert('revibe_alert_test', ['source'=>'scripts/alert_test.php','time'=>date('c')], 'info');
echo json_encode(['success'=>$ok,'message'=>$ok?'Alert terkirim.':'Alert channel belum aktif atau gagal; cek .env ALERT_CHANNEL.'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;
