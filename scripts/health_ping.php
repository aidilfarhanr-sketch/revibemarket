<?php
require_once __DIR__ . '/../config/env.php';
$urls = array_filter([
    (string)revibe_env('HEALTHCHECKS_PING_URL',''),
    (string)revibe_env('BETTER_STACK_HEARTBEAT_URL',''),
]);
foreach ($urls as $url) {
    if (function_exists('curl_init')) {
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8]);
        curl_exec($ch);
        curl_close($ch);
    }
}
echo "Health ping selesai\n";
