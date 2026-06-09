<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/functions.php';
$debug = filter_var(revibe_env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
echo json_encode(['success'=>true,'app_debug'=>$debug,'expected_user_message'=>$debug?'detail terlihat di development':'error teknis disembunyikan dan request_id masuk log','request_id'=>revibe_request_id()], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;
